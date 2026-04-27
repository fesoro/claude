# Canary Deployment + Feature Flag Rollout (Senior)

## Problem
- Yeni "checkout v2" feature deploy etmək lazımdır
- 100% rollout riskli (revenue impact)
- Strategy: 1% → 10% → 50% → 100% trafik
- Metric-lər pisləşərsə avtomatik rollback

---

## Həll: Feature flag + observability

```
LB → 100% Service v2 (kod deploy olunur)
     ↓
     Feature flag check:
        if user_id % 100 < CANARY_PCT:
            new_checkout_flow()
        else:
            old_checkout_flow()
```

Deploy "code release"-dən ayrıdır. Kod deploy olunur (flag OFF), feature flag dinamik artır.

---

## 1. Feature flag setup (Laravel Pennant)

```bash
composer require laravel/pennant
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate
```

```php
<?php
// app/Providers/AppServiceProvider.php
use Laravel\Pennant\Feature;

public function boot(): void
{
    Feature::define('new-checkout', function (User $user = null) {
        if (!$user) return false;
        
        // Override: admin/beta-tester həmişə görür
        if ($user->isAdmin()) return true;
        if ($user->hasRole('beta-tester')) return true;
        
        // Per-user kill switch (negative override)
        if ($user->feature_flags['new-checkout'] === false) return false;
        
        // Rollout percentage (Redis-də saxla, dinamik dəyiş)
        $pct = (int) Cache::remember(
            'feature:new-checkout:pct',
            10,   // 10s cache
            fn() => Redis::get('feature:new-checkout:pct') ?? 0
        );
        
        // User_id hash → stable assignment
        $bucket = crc32("new-checkout:{$user->id}") % 100;
        return $bucket < $pct;
    });
}
```

```php
<?php
// Controller
class CheckoutController
{
    public function index(Request $req)
    {
        if (Feature::for($req->user())->active('new-checkout')) {
            return $this->newCheckout($req);
        }
        return $this->oldCheckout($req);
    }
}

// Blade
@feature('new-checkout')
    <x-checkout-v2 />
@else
    <x-checkout-v1 />
@endfeature
```

---

## 2. Rollout dashboard (Artisan command)

```php
<?php
// app/Console/Commands/FeatureRollout.php
class FeatureRollout extends Command
{
    protected $signature = 'feature:rollout {feature} {percent}';
    
    public function handle(): int
    {
        $feature = $this->argument('feature');
        $pct = (int) $this->argument('percent');
        
        if ($pct < 0 || $pct > 100) {
            $this->error('Percent must be 0-100');
            return self::FAILURE;
        }
        
        $current = (int) Redis::get("feature:$feature:pct") ?? 0;
        
        if (! $this->confirm("Update '$feature' from {$current}% to {$pct}%?")) {
            return self::FAILURE;
        }
        
        Redis::set("feature:$feature:pct", $pct);
        Cache::forget("feature:$feature:pct");
        
        // Audit log
        FeatureFlagAudit::create([
            'feature'  => $feature,
            'old_pct'  => $current,
            'new_pct'  => $pct,
            'user_id'  => auth()->id(),
            'reason'   => $this->ask('Reason?'),
        ]);
        
        $this->info("Rolled out '$feature' to {$pct}%");
        
        // Slack notify
        Notification::route('slack', config('alerts.slack'))
            ->notify(new FeatureRolloutNotification($feature, $current, $pct));
        
        return self::SUCCESS;
    }
}
```

```bash
# Manual rollout steps
php artisan feature:rollout new-checkout 1     # 1%
# Watch metrics 10 min
php artisan feature:rollout new-checkout 5     # 5%
# Watch metrics 30 min
php artisan feature:rollout new-checkout 25    # 25%
# Watch metrics 1 hour
php artisan feature:rollout new-checkout 100   # full

# Emergency rollback
php artisan feature:rollout new-checkout 0     # kill switch
```

---

## 3. Metrics differentiation (canary-aware)

```php
<?php
// Hər metric-ə "feature variant" label
class CheckoutController
{
    public function index(Request $req, Metrics $metrics)
    {
        $variant = Feature::for($req->user())->active('new-checkout') ? 'v2' : 'v1';
        
        $start = microtime(true);
        try {
            $result = match ($variant) {
                'v2' => $this->newCheckout($req),
                'v1' => $this->oldCheckout($req),
            };
            
            $metrics->increment('checkout.success', ['variant' => $variant]);
            return $result;
        } catch (\Throwable $e) {
            $metrics->increment('checkout.error', ['variant' => $variant]);
            throw $e;
        } finally {
            $metrics->observe('checkout.duration_ms', 
                (microtime(true) - $start) * 1000,
                ['variant' => $variant]
            );
        }
    }
}
```

```promql
# Prometheus query — variant fərqi
rate(checkout_error[5m]) by (variant)
# v1: 0.001/s, v2: 0.05/s → v2 50× daha çox error!

histogram_quantile(0.99,
  rate(checkout_duration_ms_bucket[5m])
) by (variant)
# v1: 200ms, v2: 850ms → v2 4× yavaşdır
```

---

## 4. Auto-rollback (alert-based)

```yaml
# prometheus alerts
groups:
- name: feature_canary
  rules:
  - alert: CanaryErrorRateHigh
    expr: |
      (rate(checkout_error_total{variant="v2"}[5m])
       / rate(checkout_total{variant="v2"}[5m])) > 0.02
    for: 2m
    annotations:
      summary: "v2 error rate > 2%"
      runbook: "https://wiki/runbooks/canary-rollback"
  
  - alert: CanaryLatencyP99High
    expr: |
      histogram_quantile(0.99,
        rate(checkout_duration_ms_bucket{variant="v2"}[5m])
      ) > histogram_quantile(0.99,
        rate(checkout_duration_ms_bucket{variant="v1"}[5m])
      ) * 1.5
    for: 5m
```

```php
<?php
// Webhook handler (Alertmanager → app)
Route::post('/internal/alert-rollback', function (Request $req) {
    $alert = $req->json('alerts.0');
    
    if ($alert['labels']['alertname'] === 'CanaryErrorRateHigh') {
        $feature = $alert['labels']['feature'] ?? 'new-checkout';
        
        // EMERGENCY ROLLBACK
        Redis::set("feature:$feature:pct", 0);
        Cache::forget("feature:$feature:pct");
        
        FeatureFlagAudit::create([
            'feature' => $feature,
            'new_pct' => 0,
            'reason'  => 'AUTO ROLLBACK: ' . $alert['annotations']['summary'],
            'user_id' => null,   // system
        ]);
        
        Log::critical('Auto rollback triggered', $alert);
        
        return response()->json(['rolled_back' => true]);
    }
    
    return response()->json(['ok' => true]);
});
```

---

## 5. A/B test variant (feature flag + analytics)

```php
<?php
// İki variant analiz üçün
Feature::define('checkout-button-color', function (User $user) {
    return match (crc32("color:{$user->id}") % 3) {
        0 => 'control',
        1 => 'green',
        2 => 'red',
    };
});

// Track
$variant = Feature::for($user)->value('checkout-button-color');
Analytics::track('checkout.click', ['variant' => $variant]);

// Statistical significance check (>95% confidence)
// SQL:
//   SELECT variant, COUNT(*) as clicks, AVG(converted) as rate
//   FROM events WHERE event = 'checkout.click'
//   GROUP BY variant
```

---

## 6. Pitfalls

```
❌ User experience flicker — A user-i 1 dəq sonra B-yə keç
   ✓ Stable assignment (user_id hash, sticky)

❌ Cache stale — flag dəyişdi, cache 1 saat saxlayır
   ✓ Short TTL (5-10s) və ya event-driven invalidation

❌ Code branch divergence böyüyür
   ✓ Flag-ları clean up et (rollout 100% sonra silinsin)

❌ Test çətinliyi (hər iki branch test edilməlidir)
   ✓ Feature mode injection (`Feature::activate`/`deactivate` test-də)

❌ Database migration-larla feature flag sinxron deyil
   ✓ Backward-compatible schema dəyişiklikləri (expand/contract)

❌ Metrics variant label cardinality explosion
   ✓ Yalnız aktiv canary üçün variant label, sonradan sil

❌ Auto rollback false positive
   ✓ Threshold dəqiq tune et (2 min for, sample size kifayət)
```

---

## 7. Rollout timeline nümunə

```
Day 1, 09:00 — Deploy v2 code (flag = 0%)
Day 1, 09:30 — Rollout 1% (internal users)
Day 1, 10:30 — Rollout 5% (1 saat metric monitor)
Day 1, 14:00 — Rollout 25%
Day 2, 09:00 — Rollout 50%
Day 2, 14:00 — Rollout 100%
Day 5         — Code cleanup PR (delete v1)
Day 7         — Merge cleanup
Day 10        — Delete feature flag definition
```
