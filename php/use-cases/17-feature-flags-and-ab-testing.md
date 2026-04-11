# Feature Flags və A/B Testing Sistemi

## Problem

Böyük komandalar eyni kod bazasında işləyir. Yeni feature hazırlandıqda necə təhlükəsiz deployment etmək olar?

- **Trunk-Based Development**: Hamı `main` branch-a push edir → feature yarımçıqdır, lakin production-a gedir
- **Gradual Rollout**: Əvvəlcə 1%, sonra 10%, sonra 100% istifadəçiyə çatdır
- **Kill Switch**: Bir şey səhv getsə, 1 klik ilə yeni feature-ı söndür
- **A/B Testing**: "Yeni checkout düyməsi dönüşümü artırırmı?" sualını statistika ilə cavabla
- **Dark Launch**: Feature hazır olmadan yükü test et, istifadəçiyə görünmürsün

---

## Feature Flag Növləri

### 1. Release Flags (Geçici)
Yarımçıq feature-ı gizlətmək üçün. Deployment bitəndən sonra silinir.
```
Nümunə: new_checkout_flow — yeni ödəniş axını hazır olana qədər gizli
```

### 2. Experiment Flags (Geçici)
A/B test üçün. Test bitəndən sonra qalib variant production-a çıxarılır, flag silinir.
```
Nümunə: checkout_button_color — Qırmızı vs Yaşıl düymə
```

### 3. Ops Flags (Uzunömürlü)
İşləmə zamanı sistemi idarə etmək — kill switch, circuit breaker.
```
Nümunə: enable_payment_gateway_v2, maintenance_mode
```

### 4. Permission Flags (Uzunömürlü)
Müəyyən istifadəçilərə xüsusi giriş — premium, beta, admin.
```
Nümunə: beta_dashboard_access, advanced_reporting
```

---

## Targeting Rules

Feature flag kimin üçün aktiv olacağını müəyyən edən qaydalar:

```
IF user.plan == 'premium'        → enabled
IF user.id IN [1, 2, 3, 100]    → enabled (specific users)
IF user.country == 'AZ'         → enabled (geo targeting)
IF user.id % 100 < 10           → enabled (10% rollout)
IF user.created_at > '2024-01-01' → enabled (new users only)
ELSE                             → disabled
```

---

## A/B Testing Statistikası (Qısa)

**Hypothesis**: Yeni checkout düyməsi dönüşüm faizini artıracaq.

**Metrics**:
- Kontrol (A): Mövcud düymə — 100 ziyarətçi, 5 konversiya = **5%**
- Variant (B): Yeni düymə — 100 ziyarətçi, 8 konversiya = **8%**

**Statistical Significance**: Nəticə təsadüfi deyil, statistik olaraq real fərqdir.
- **p-value < 0.05**: 95% etibarlılıq — fərq real qəbul edilir
- **p-value > 0.05**: Fərq yüksək ehtimalla statistik gürültüdür

**Minimum Sample Size**: Test kifayət qədər istifadəçi olmadan dayandırılmamalıdır (early stopping bias).

**Practical Rule of Thumb**: Hər variant üçün ən az 1000 istifadəçi, 1-2 həftə.

---

## Gradual Rollout

```
Gün 1:  1% → İlk canary test, monitorinq
Gün 3:  5% → Hələ də az, error-lar izlənir
Gün 7: 20% → Orta miqyas test
Gün 10: 50% → Yarım istifadəçi
Gün 14: 100% → Tam rollout
```

**Canary Deployment**: Yeni versiyanı 1-2% "canary" server-da yayımla. Əgər error rate artmırsa, tədricən genişlət.

---

## Database Schema

*Bu kod feature flag-lar, assignment-lər və A/B test eventləri üçün verilənlər bazası strukturunu yaradır:*

```sql
CREATE TABLE feature_flags (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,    -- 'new_checkout_flow'
    type        ENUM('release','experiment','ops','permission') NOT NULL,
    description TEXT NULL,
    is_active   BOOLEAN DEFAULT FALSE,           -- Global on/off
    rules       JSON NULL,                       -- Targeting rules
    variants    JSON NULL,                       -- A/B test variantları
    rollout_percentage TINYINT DEFAULT 0,        -- 0-100
    starts_at   TIMESTAMP NULL,
    ends_at     TIMESTAMP NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
);

CREATE TABLE feature_flag_assignments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flag_id         INT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    variant         VARCHAR(50) DEFAULT 'enabled',  -- 'control', 'variant_a', 'variant_b'
    assigned_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_flag_user (flag_id, user_id),
    FOREIGN KEY (flag_id) REFERENCES feature_flags(id)
);

CREATE TABLE ab_test_events (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flag_name   VARCHAR(100) NOT NULL,
    variant     VARCHAR(50) NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    event_name  VARCHAR(100) NOT NULL,   -- 'conversion', 'click', 'purchase'
    value       DECIMAL(10,2) NULL,      -- Pul dəyəri (A/B revenue test)
    metadata    JSON NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_flag_variant (flag_name, variant, event_name),
    INDEX idx_user (user_id)
);
```

---

## FeatureFlag Model

*Bu kod FeatureFlag Eloquent modelini — cast-ları, əlaqələri və cədvəl vaxt çərçivəsini yoxlayan metodu — müəyyən edir:*

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureFlag extends Model
{
    protected $fillable = [
        'name', 'type', 'description', 'is_active',
        'rules', 'variants', 'rollout_percentage',
        'starts_at', 'ends_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'rules'      => 'array',
        'variants'   => 'array',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(FeatureFlagAssignment::class, 'flag_id');
    }

    public function isWithinSchedule(): bool
    {
        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false; // Hələ başlamamış
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false; // Müddəti bitib
        }

        return true;
    }
}
```

---

## FeatureFlagService

*Bu kod feature flag-ın aktiv olub-olmadığını, A/B test variantını, targeting qaydalarını və rollout-u idarə edən servis sinfini göstərir:*

```php
<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\FeatureFlagAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FeatureFlagService
{
    private const CACHE_TTL = 300; // 5 dəqiqə

    /**
     * Verilen flag user üçün aktivdirmi?
     */
    public function isEnabled(string $flagName, ?User $user = null): bool
    {
        $flag = $this->getFlag($flagName);

        if (!$flag || !$flag->is_active) {
            return false;
        }

        if (!$flag->isWithinSchedule()) {
            return false;
        }

        // Targeting rules yoxla
        if ($user && !empty($flag->rules)) {
            return $this->evaluateRules($flag->rules, $user);
        }

        // Percentage rollout
        if ($flag->rollout_percentage < 100) {
            return $this->isInRollout($flag, $user);
        }

        return true;
    }

    /**
     * A/B test variant-ını al
     * Sticky bucketing: Eyni user həmişə eyni varianta düşür
     */
    public function getVariant(string $flagName, ?User $user = null): string
    {
        $flag = $this->getFlag($flagName);

        if (!$flag || !$flag->is_active || empty($flag->variants)) {
            return 'control';
        }

        if (!$user) {
            // Anonymous user üçün session-based bucketing
            return $this->getAnonymousVariant($flag);
        }

        // DB-dən mövcud assignment yoxla (sticky)
        $assignment = FeatureFlagAssignment::where('flag_id', $flag->id)
            ->where('user_id', $user->id)
            ->first();

        if ($assignment) {
            return $assignment->variant;
        }

        // Yeni assignment yarat
        $variant = $this->assignVariant($flag, $user);

        FeatureFlagAssignment::create([
            'flag_id' => $flag->id,
            'user_id' => $user->id,
            'variant' => $variant,
        ]);

        return $variant;
    }

    /**
     * Targeting rules-u qiymətləndir
     */
    private function evaluateRules(array $rules, User $user): bool
    {
        foreach ($rules as $rule) {
            $result = match ($rule['type']) {
                'user_ids'    => in_array($user->id, $rule['values']),
                'plan'        => in_array($user->plan, $rule['values']),
                'country'     => in_array($user->country, $rule['values']),
                'email_domain'=> str_ends_with($user->email, '@' . $rule['value']),
                'created_after' => $user->created_at->gt($rule['date']),
                'user_segment' => $this->isInSegment($user, $rule['segment']),
                default       => false,
            };

            // AND/OR məntiqi
            if ($rule['operator'] === 'OR' && $result) {
                return true;
            }
            if ($rule['operator'] === 'AND' && !$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Percentage rollout — deterministik (user_id-ə görə)
     */
    private function isInRollout(FeatureFlag $flag, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Deterministik hash: eyni user həmişə eyni bucket-da olur
        $hash = crc32($flag->name . ':' . $user->id);
        $bucket = abs($hash) % 100;

        return $bucket < $flag->rollout_percentage;
    }

    /**
     * Variant təyin et — variant ağırlıqlarına görə
     * variants: [['name' => 'control', 'weight' => 50], ['name' => 'variant_a', 'weight' => 50]]
     */
    private function assignVariant(FeatureFlag $flag, User $user): string
    {
        $variants = $flag->variants;

        // Deterministik hash ilə variant seç
        $hash = abs(crc32($flag->name . ':variant:' . $user->id)) % 100;

        $cumulative = 0;
        foreach ($variants as $variant) {
            $cumulative += $variant['weight'];
            if ($hash < $cumulative) {
                return $variant['name'];
            }
        }

        return $variants[0]['name'] ?? 'control';
    }

    private function getAnonymousVariant(FeatureFlag $flag): string
    {
        $sessionKey = 'flag_variant_' . $flag->name;

        if (session()->has($sessionKey)) {
            return session($sessionKey);
        }

        $variant = $flag->variants[array_rand($flag->variants)]['name'] ?? 'control';
        session([$sessionKey => $variant]);

        return $variant;
    }

    private function isInSegment(User $user, string $segment): bool
    {
        return match ($segment) {
            'power_users' => $user->orders()->count() > 10,
            'new_users'   => $user->created_at->gt(now()->subDays(7)),
            'premium'     => $user->plan === 'premium',
            default       => false,
        };
    }

    /**
     * Flag-i cache-lə (DB-ni hər request-də sorğulamamaq üçün)
     */
    private function getFlag(string $flagName): ?FeatureFlag
    {
        return Cache::remember(
            "feature_flag:{$flagName}",
            self::CACHE_TTL,
            fn() => FeatureFlag::where('name', $flagName)->first()
        );
    }

    /**
     * Flag yeniləndikdə cache-i sil
     */
    public function invalidateCache(string $flagName): void
    {
        Cache::forget("feature_flag:{$flagName}");
    }
}
```

---

## Blade Directive

*Bu kod Blade template-lərdə `@feature` və `@variant` direktivlərini qeydiyyatdan keçirir:*

```php
<?php

namespace App\Providers;

use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $flagService = app(FeatureFlagService::class);

        // @feature('new_checkout_flow') ... @endfeature
        Blade::directive('feature', function (string $flag) {
            return "<?php if(app(\App\Services\FeatureFlagService::class)->isEnabled({$flag}, auth()->user())): ?>";
        });

        Blade::directive('endfeature', function () {
            return '<?php endif; ?>';
        });

        // @variant('checkout_button_color', 'variant_a') ... @endvariant
        Blade::directive('variant', function (string $expression) {
            [$flag, $variant] = explode(',', $expression, 2);
            $flag = trim($flag);
            $variant = trim($variant);
            return "<?php if(app(\App\Services\FeatureFlagService::class)->getVariant({$flag}, auth()->user()) === {$variant}): ?>";
        });

        Blade::directive('endvariant', function () {
            return '<?php endif; ?>';
        });
    }
}
```

*Bu kod Blade template-də `@feature` və `@variant` direktivlərindən istifadə nümunəsini göstərir:*

```blade
{{-- Blade template istifadəsi --}}

@feature('new_checkout_flow')
    <div class="new-checkout">
        {{-- Yeni checkout axını --}}
    </div>
@else
    <div class="old-checkout">
        {{-- Köhnə checkout axını --}}
    </div>
@endfeature

{{-- A/B test variant --}}
@variant('checkout_button_color', 'control')
    <button class="btn btn-blue">Sifarişi Tamamla</button>
@endvariant

@variant('checkout_button_color', 'variant_a')
    <button class="btn btn-green">İndi Al →</button>
@endvariant
```

---

## Middleware-Based Feature Access

*Bu kod route-ları feature flag ilə qoruyub icazəsiz istifadəçiləri bloklayan middleware-i göstərir:*

```php
<?php

namespace App\Http\Middleware;

use App\Services\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;

class FeatureFlagMiddleware
{
    public function __construct(
        private readonly FeatureFlagService $flagService
    ) {}

    /**
     * Route-u feature flag ilə qoru
     * Route::get('/beta', ...)->middleware('feature:beta_dashboard')
     */
    public function handle(Request $request, Closure $next, string $flagName): mixed
    {
        if (!$this->flagService->isEnabled($flagName, $request->user())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'feature_not_available',
                    'message' => 'Bu funksiya sizin hesabınız üçün hələ aktiv deyil',
                ], 403);
            }

            return redirect()->route('home')->with(
                'info',
                'Bu funksiya tezliklə sizin üçün də aktiv olacaq!'
            );
        }

        return $next($request);
    }
}
```

---

## A/B Test Event Tracking

*Bu kod A/B test eventlərini qeydə alan və hər variant üçün konversiya statistikasını hesablayan tracker sinfini göstərir:*

```php
<?php

namespace App\Services;

use App\Models\AbTestEvent;
use Illuminate\Support\Facades\Auth;

class AbTestTracker
{
    public function __construct(
        private readonly FeatureFlagService $flagService
    ) {}

    /**
     * A/B test eventi qeyd et
     */
    public function track(
        string $flagName,
        string $eventName,
        ?float $value = null,
        array $metadata = []
    ): void {
        $user = Auth::user();
        $variant = $this->flagService->getVariant($flagName, $user);

        AbTestEvent::create([
            'flag_name'  => $flagName,
            'variant'    => $variant,
            'user_id'    => $user?->id,
            'event_name' => $eventName,
            'value'      => $value,
            'metadata'   => $metadata,
        ]);
    }

    /**
     * A/B test nəticələrini hesabla
     */
    public function getResults(string $flagName): array
    {
        $results = AbTestEvent::where('flag_name', $flagName)
            ->selectRaw('
                variant,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(*) as total_events,
                SUM(CASE WHEN event_name = "conversion" THEN 1 ELSE 0 END) as conversions,
                SUM(CASE WHEN event_name = "conversion" THEN value ELSE 0 END) as revenue,
                AVG(CASE WHEN event_name = "conversion" THEN value ELSE NULL END) as avg_order_value
            ')
            ->groupBy('variant')
            ->get();

        $summary = [];
        foreach ($results as $row) {
            $conversionRate = $row->unique_users > 0
                ? round(($row->conversions / $row->unique_users) * 100, 2)
                : 0;

            $summary[$row->variant] = [
                'unique_users'    => $row->unique_users,
                'conversions'     => $row->conversions,
                'conversion_rate' => $conversionRate . '%',
                'revenue'         => $row->revenue,
                'avg_order_value' => $row->avg_order_value,
            ];
        }

        return $summary;
    }
}
```

### Controller-da istifadə

*Bu kod checkout controller-ında variant oxunması və konversiya tracking-in necə aparıldığını göstərir:*

```php
<?php

namespace App\Http\Controllers;

use App\Services\AbTestTracker;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly FeatureFlagService $flagService,
        private readonly AbTestTracker $tracker
    ) {}

    public function show(): \Illuminate\View\View
    {
        $user = auth()->user();
        $variant = $this->flagService->getVariant('checkout_button_color', $user);

        // "View" eventini track et
        $this->tracker->track('checkout_button_color', 'checkout_viewed');

        return view('checkout.show', compact('variant'));
    }

    public function complete(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Sifariş tamamlama məntiqi...
        $order = $this->processOrder($request);

        // A/B test konversiyasını track et
        $this->tracker->track(
            flagName: 'checkout_button_color',
            eventName: 'conversion',
            value: $order->total,
            metadata: ['order_id' => $order->id]
        );

        return redirect()->route('order.success', $order);
    }
}
```

---

## Laravel Pennant Paketi

Laravel 10+ ilə gələn rəsmi feature flag paketi:

*Bu kod Laravel Pennant paketindən istifadə edərək feature flag-ların necə müəyyən edilib tətbiq edildiyini göstərir:*

```php
// Installation
// composer require laravel/pennant

// AppServiceProvider-da Feature define et
use Laravel\Pennant\Feature;

Feature::define('new-checkout-flow', function (User $user) {
    // Premium users üçün aktiv
    return $user->plan === 'premium';
});

// Percentage-based rollout
Feature::define('dark-mode', fn(User $user) => (
    $user->id % 100 < 20 // 20%
));

// Controller-da istifadə
use Laravel\Pennant\Feature;

if (Feature::active('new-checkout-flow')) {
    // Yeni axın
}

// Middleware
Route::get('/new-feature', NewFeatureController::class)
    ->middleware(\Laravel\Pennant\Middleware\EnsureFeaturesAreActive::using('new-checkout-flow'));

// Blade
@feature('new-checkout-flow')
    <div>Yeni xüsusiyyət</div>
@endfeature
```

---

## Admin Panel — Flag Management

*Bu kod admin panel üçün flag-ları siyahılamaq, aktivləşdirmək/deaktivləşdirmək və rollout faizini idarə edən controller-ı göstərir:*

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\FeatureFlag;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function __construct(
        private readonly FeatureFlagService $flagService
    ) {}

    public function index(): \Illuminate\View\View
    {
        $flags = FeatureFlag::orderBy('type')->orderBy('name')->get();
        return view('admin.feature-flags.index', compact('flags'));
    }

    public function toggle(FeatureFlag $flag): \Illuminate\Http\RedirectResponse
    {
        $flag->update(['is_active' => !$flag->is_active]);
        $this->flagService->invalidateCache($flag->name);

        $status = $flag->is_active ? 'aktivləşdirildi' : 'deaktivləşdirildi';
        return back()->with('success', "Flag '{$flag->name}' {$status}.");
    }

    public function updateRollout(Request $request, FeatureFlag $flag): \Illuminate\Http\JsonResponse
    {
        $request->validate(['percentage' => 'required|integer|min:0|max:100']);

        $flag->update(['rollout_percentage' => $request->percentage]);
        $this->flagService->invalidateCache($flag->name);

        return response()->json(['success' => true, 'percentage' => $request->percentage]);
    }
}
```

---

## Rollback Strategiyası

*Bu kod flag-ı söndürmək, rollout faizini azaltmaq və xüsusi istifadəçiləri qaydalardan çıxarmaq üçün rollback əməliyyatlarını göstərir:*

```php
// Kill switch: Anında söndür
$flag->update(['is_active' => false]);
Cache::forget("feature_flag:{$flag->name}");

// Rollout-u azalt: 50% → 5%
$flag->update(['rollout_percentage' => 5]);

// Xüsusi istifadəçiləri çıxar: rules-dan sil
$flag->update(['rules' => array_filter(
    $flag->rules,
    fn($rule) => $rule['type'] !== 'user_ids'
)]);
```

**Circuit Breaker ilə inteqrasiya:**

*Bu kod circuit breaker açıqdırsa flag aktiv olsa belə feature-ı deaktiv sayan məntiqi göstərir:*

```php
// Error rate yüksəkdirsə, flag-i avtomatik söndür
if ($circuitBreaker->isOpen('new_checkout_flow')) {
    return false; // Flag aktiv olsa belə, circuit açıqdırsa deaktiv say
}
```

---

## İntervyu Sualları

**S: Feature flag vs code branch fərqi nədir, nə zaman feature flag istifadə edərsiniz?**

C: Code branch-da merge conflict riski var, uzunmüddətli branch-lar gec integrate edilir. Feature flag ilə kod həmişə main-dədir, ancaq davranış runtime-da idarə edilir. Trunk-based development üçün feature flag ideal seçimdir.

**S: Sticky bucketing nədir, nə üçün vacibdir?**

C: A/B test-də eyni istifadəçi hər dəfə fərqli varianta düşməməlidir — bu test nəticələrini pozur. Sticky bucketing istifadəçinin ilk assignment-ini DB-də saxlayır, sonrakı sorğularda eyni variant göstərilir.

**S: Feature flag-lərin sayı artanda texniki borc (technical debt) necə idarə edilir?**

C: Hər flag-in `ends_at` tarixi olmalıdır. Müntəzəm "flag cleanup sprint"-lər keçirilməlidir. Release flag-lər yayımdan 2 həftə sonra silinməlidir. Flag registry-də owner müəyyən edilməlidir.

**S: A/B test nəticəsini erkən dayandırmaq (peeking problem) niyə yanlışdır?**

C: P-value hesablanması sample size-dan asılıdır. Az data ilə müşahidə etdiyiniz fərq statistik gürültü ola bilər. Minimum sample size əvvəlcədən hesablanmalı, test o həddə çatana qədər dayandırılmamalıdır.

**S: Percentage rollout-u deterministik necə edirsiz?**

C: `crc32(flagName + ':' + userId) % 100 < rolloutPercentage` — eyni user həmişə eyni bucket-a düşür. Random deyil, hash-based olduğu üçün 10%-ə qoyduğumuzda həmişə eyni 10% görür.

**S: LaunchDarkly/Unleash kimi xidmətlər xüsusi implementasiyadan nə üstündür?**
C: Managed servis üstünlükləri: real-time flag update (SSE/WebSocket ilə push — Redis cache TTL gözlənilmir), targeting rules UI, A/B analytics built-in, audit trail, multi-environment (dev/staging/prod), SDK-lar. Xüsusi implementasiya: tam kontrol, əlavə xərc yoxdur, vendor lock-in yoxdur. Böyük komanda + çox flag + advanced targeting lazımdırsa managed servis. Kiçik komanda + sadə use-case + infrastructure artıq varsa xüsusi implementasiya.

**S: Flag evaluation performance — hər request-də DB sorğusu etmək niyə pisdir?**
C: 1000 RPS olan sistemdə hər request-də `SELECT * FROM feature_flags WHERE name = ?` → 1000 DB sorğusu/saniyə yalnız flag üçün. Redis cache (5-30 saniyə TTL) ilə bu sorğuların 99%+ keşdən cavablanır. Flag dəyişdikdə `Cache::forget()` + Observer ilə cache invalidation. Application-level in-memory cache (PHP process-in yaddaşında) daha sürətlidir amma flag update-ləri bir az gecikmə ilə tətbiq olunur.

**S: Canary deployment ilə feature flag fərqi nədir?**
C: Canary deployment: yeni kod versiyasını 1-5% server-a deploy et, problem yoxdursa artır. Feature flag: bütün serverlarda eyni kod var, müəyyən user-lər üçün feature runtime-da açılır. Feature flag daha incə kontrolu verir (spesifik user, ölkə, plan), canary isə infra-level risk azaldır (köhnə kod hələ də çalışır). Birlikdə istifadə edilir: yeni kod canary server-a gedir, feature flag ilə 1% user yeni kodu görür.

---

## Anti-patternlər

**1. Feature flag-ları kod içinə hardcode etmək**
`if ($userId === 123)` — deploy olmadan dəyişdirilə bilməz. Flag-ları DB-yə ya da LaunchDarkly/Unleash kimi sisteme köçürün.

**2. Köhnə flag-ları silməmək**
6 aylıq test bitdi, flag silindi mi? Yüzlərlə dead flag — kod mürəkkəbləşir, hansının aktiv olduğu bilinmir. Flag lifecycle policy: test bitdikdən 2 həftə sonra silin.

**3. A/B testi erkən dayandırmaq (Peeking Problem)**
P-value < 0.05 görüb sevinmək — false positive. Minimum sample size əvvəlcədən hesablanmalı, test o həddə çatana qədər baxılmamalıdır.

**4. Qeyri-deterministik rollout**
`rand() % 100 < 10` — eyni user hər refresh-də fərqli variant görür. `crc32(flagName.userId) % 100` hash-based assignment istifadə edin.

**5. Flag-ları cache etməmək**
Hər request-də DB-dən flag oxumaq — yüksək yükdə DB bottleneck. Redis-də TTL ilə cache edin (5-30 saniyə), flag dəyişikliyi cache invalidation trigger edir.

**6. Variant assignment-i log etməmək**
Kimin hansı variant gördüyünü bilmirsiniz — A/B nəticəsini hesablaya bilmirsiniz. Assignment event-lərini analytics-ə göndərin.
