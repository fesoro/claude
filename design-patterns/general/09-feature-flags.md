# Feature Flags / Feature Toggles (Middle ⭐⭐)

## İcmal

Feature Flag (Feature Toggle) — kodda bir funksiyanı deploy etmədən aktivləşdirmək/söndürmək üçün istifadə olunan runtime konfiqurasiya mexanizmidir. "Dark launch" — kod production-dadır, lakin hələ user görə bilmir. Bir switch açılır, feature aktiv olur — yeni deploy lazım deyil.

## Niyə Vacibdir

**Trunk-based development** mümkün olur: hər developer `main` branch-a push edir, feature flag arxasında inkişaf edir. Long-lived feature branch-ları olmur, merge conflict-lər azalır. **Canary release** asanlaşır: flag %1 → %10 → %50 → %100 açılır, bir şey pis gedirsə anında bağlanır. **Kill switch** istifadəsi: production-da bug aşkarlandıqda feature söndürülür, 2 saat gözləmədən hotfix deploy. **A/B testing** — kod dəyişmədən fərqli versiyalar müqayisə olunur.

## Əsas Anlayışlar

- **Release Toggle**: yeni feature hazır deyil amma deploy olunub; `false` olanda eski kod işləyir
- **Experiment Toggle (A/B)**: iki qrup müxtəlif experience alır; conversion ölçülür
- **Operational Toggle (Kill Switch)**: production incident-ında tez söndürmək üçün; həmişə manual
- **Permission Toggle (Beta)**: yalnız müəyyən user/plan feature-a çıxış alır (premium feature, beta proqram)
- **Flag Storage**: `.env` (static, deploy lazım), DB/Redis (runtime, dynamic), xarici servis (LaunchDarkly, Unleash, Laravel Pennant)
- **Flag Rot**: köhnə, artıq lazımsız flag-lər silunmır — texniki borcun formalarından biri
- **Targeting**: flag-i user ID, country, plan, percentage, attribute-a görə açmaq

## Praktik Baxış

- **Release Toggle** — deployment zamanında: yeni payment processor; flag açılır, test edilir, köhnə kod silinir, flag silinir. Lifecycle: 1-4 həftə.
- **Kill Switch** — həmişə əl altında: yeni feed algorithm performans yeyirsə, admin paneldən bağlanır. Lifecycle: aylar/illər.
- **A/B Toggle** — experiment tamamlandıqda qalib veriant default olur, flag silinir. Lifecycle: 1-4 həftə.
- **Permission Toggle** — plan-based access: premium feature yalnız Pro plan üçün. Lifecycle: uzunmüddətli.

**Hansı hallarda istifadə etməmək:**
- Hər kiçik dəyişiklik üçün — overhead artır, kod oxunmaz olur
- Security fix üçün — birbaşa deploy edin, flag arxasında saxlamayın
- Database migration üçün — schema dəyişikliyi ayrıca idarə olunmalıdır

**Common mistakes:**
- Flag-ləri silməmək — 6 aydan sonra heç kim nə etdiyini bilmir
- Nested flag-lər: `if flagA && flagB && flagC` — test etmək qeyri-mümkün
- Flag-ə bağlı business logic — `if feature_enabled('new_pricing')` əvəzinə `if user->plan == 'pro'`

### Anti-Pattern Nə Zaman Olur?

**Flag explosion**: 50+ aktiv flag, hər biri digəri ilə interact edə bilər — 2⁵⁰ kombinasiya. Test etmək mümkünsüzdür. Flag sayına limit qoyun, köhnələri sistematik silin (ownership, expiry date).

**Permanent temporary flags**: "elə release toggle kimi yazdıq" — 2 il sonra silinməyib, kim açar bilmir. Hər flag-in owner-i və expiry date-i olmalıdır.

---

## Nümunələr

### Ümumi Nümunə

Flag olmadan yeni feature deploy etmək: ya hamı görür, ya heç kim. Flag ilə: kod production-dadır, `false` = köhnə path, `true` = yeni path; deployment və activation ayrıdır.

```
Request → Flag Check → true  → New Feature Code
                    → false → Old Feature Code
```

### Kod Nümunəsi

**Sadə Laravel tətbiqi — DB-based flag:**

```php
<?php

// Migration
Schema::create('feature_flags', function (Blueprint $table) {
    $table->string('name')->unique();
    $table->boolean('enabled')->default(false);
    $table->json('targeting')->nullable(); // {"user_ids": [1,2,3], "percentage": 10}
    $table->timestamps();
});

// FeatureFlag Service
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class FeatureFlag
{
    public function enabled(string $flag, ?int $userId = null): bool
    {
        $config = Cache::remember("flag:{$flag}", 60, function () use ($flag) {
            return \DB::table('feature_flags')->where('name', $flag)->first();
        });

        if (!$config || !$config->enabled) {
            return false;
        }

        $targeting = json_decode($config->targeting, true);
        if (!$targeting) {
            return true; // flag açıqdır, hamıya
        }

        $userId ??= Auth::id();

        // Specific user-lara
        if (isset($targeting['user_ids']) && in_array($userId, $targeting['user_ids'])) {
            return true;
        }

        // Percentage rollout — deterministic: eyni user həmişə eyni cavab alır
        if (isset($targeting['percentage']) && $userId) {
            return ($userId % 100) < $targeting['percentage'];
        }

        return false;
    }
}

// Façade binding (AppServiceProvider-də)
// app()->singleton('feature', fn() => new FeatureFlag());

// İstifadə
if (app('feature')->enabled('new_checkout')) {
    return $this->newCheckoutService->process($order);
}
return $this->legacyCheckout->process($order);
```

**Laravel Pennant (official package, Laravel 10+):**

```php
<?php

use Laravel\Pennant\Feature;

// AppServiceProvider-də define et
Feature::define('new-checkout', function (User $user) {
    // Percentage rollout
    return $user->id % 100 < 20; // 20% user

    // Və ya plan-based
    // return $user->plan === 'pro';

    // Və ya lottery (random, session-a bağlı)
    // return Feature::lottery(0.1);
});

// Controller-də
public function checkout(Order $order): JsonResponse
{
    if (Feature::active('new-checkout')) {
        return $this->newCheckout($order);
    }
    return $this->legacyCheckout($order);
}

// Blade-də
@feature('new-checkout')
    <x-new-checkout :order="$order" />
@else
    <x-legacy-checkout :order="$order" />
@endfeature

// Middleware ilə — route-a bağlamaq
Route::middleware(['feature:new-api-v2'])->group(function () {
    Route::get('/api/v2/orders', [OrderController::class, 'index']);
});

// Feature aktiv etmək/söndürmək
Feature::activate('new-checkout'); // hamı üçün
Feature::deactivate('new-checkout'); // hamıdan söndür
Feature::activateForEveryone('new-checkout');

// Scope-a görə (user-specific)
Feature::for($user)->activate('beta-dashboard');
Feature::for($user)->active('beta-dashboard'); // true/false
```

**Kill Switch pattern — Operational Toggle:**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function processPayment(Order $order): PaymentResult
    {
        // Kill switch: Redis-dən oxunur, ~1ms
        if (Cache::get('kill_switch:new_payment_provider', false)) {
            Log::warning('New payment provider disabled via kill switch', [
                'order_id' => $order->id,
            ]);
            return $this->legacyProvider->charge($order);
        }

        try {
            return $this->newProvider->charge($order);
        } catch (\Exception $e) {
            // Avtomatik fallback + kill switch aktivləşdir
            Cache::put('kill_switch:new_payment_provider', true, now()->addHours(1));
            Log::error('New payment provider failed, kill switch activated', [
                'exception' => $e->getMessage(),
            ]);
            return $this->legacyProvider->charge($order);
        }
    }
}

// Admin controller-dən kill switch idarəsi
class KillSwitchController extends Controller
{
    public function toggle(string $switch, bool $enabled): JsonResponse
    {
        $this->authorize('manage-kill-switches');
        Cache::put("kill_switch:{$switch}", !$enabled, now()->addDays(1));
        return response()->json(['switch' => $switch, 'enabled' => !$enabled]);
    }
}
```

**A/B Test Toggle:**

```php
<?php

use Laravel\Pennant\Feature;

// Define: user-ı deterministik olaraq qruplara böl
Feature::define('checkout-button-color', function (User $user) {
    return match (true) {
        $user->id % 2 === 0 => 'blue',   // A qrupu
        default             => 'green',   // B qrupu
    };
});

// Blade
@php $variant = Feature::value('checkout-button-color') @endphp
<button class="btn-{{ $variant }}">Ödə</button>

// Konversiya izlə
Feature::define('checkout-button-color', function (User $user) {
    $variant = $user->id % 2 === 0 ? 'blue' : 'green';
    // Analytics event
    event(new ExperimentAssigned($user, 'checkout-button-color', $variant));
    return $variant;
});
```

**Feature flag cleanup — expiry tracking:**

```php
<?php

// Flag definition — owner və expiry ilə
Schema::table('feature_flags', function (Blueprint $table) {
    $table->string('owner')->nullable();
    $table->date('expires_at')->nullable();
});

// Artisan command — köhnə flag-ləri tap
class FindExpiredFeatureFlags extends Command
{
    protected $signature = 'flags:expired';

    public function handle(): void
    {
        $expired = \DB::table('feature_flags')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $flag) {
            $this->warn("Expired flag: {$flag->name} (owner: {$flag->owner})");
        }
    }
}
```

## Praktik Tapşırıqlar

1. Laravel Pennant install et (`composer require laravel/pennant`); `new-dashboard` adlı feature flag yaz; 50% user üçün activ, qalan 50% köhnə dashboard görsün; Blade-də `@feature` directive ilə render et
2. DB-based kill switch hazırla: `PaymentService`-ə kill switch əlavə et; Redis-də saxla; manual aktivləşdirmə üçün admin API endpoint yaz; kill switch açıldıqda legacy provider-ə fallback et + log at
3. Mövcud layihənizdə 3+ release toggle tap; hər birinin owner-ini və expiry date-ini sənədləndir; köhnəsini silin (toggle + if/else kod hər ikisini); əmin olun ki, yalnız bir path qalır
4. A/B test experiment: "Submit" vs "Sifariş ver" button text; hər variant üçün click tracking əlavə et; 1 həftə sonra hansı daha yaxşı konvertasiya edir analiz et

## Əlaqəli Mövzular

- [Caching Strategiyaları](08-caching-strategies.md) — flag-lər Redis/Cache-də saxlanır
- [Strangler Fig Pattern](../integration/06-strangler-fig-pattern.md) — flag-lər köhnə sistemdən yeni sistemə kəsilməsiz keçid üçün
- [Circuit Breaker](../integration/16-circuit-breaker.md) — hər ikisi fail-fast + fallback mexanizmidir
- [ADR](06-architecture-decision-records.md) — "LaunchDarkly vs Pennant vs custom?" qərarını sənədləndir
- [Multi-Tenancy](04-multi-tenancy.md) — tenant-based flag targeting
