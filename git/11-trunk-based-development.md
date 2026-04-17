# Trunk-Based Development

## Nədir? (What is it?)

Trunk-Based Development (TBD), bütün developer-lərin bir əsas branch-ə (trunk/main) tez-tez inteqrasiya etdiyi branching strategiyasıdır. Branch-lər çox qısa ömürlü olur (bir neçə saat, maksimum 1-2 gün) və feature flag-lar vasitəsilə yarımçıq xüsusiyyətlər gizlədilir.

```
Trunk-Based Development:

main:  ●──●──●──●──●──●──●──●──●──●──●──●──●──●──●──
        ↑  ↑     ↑  ↑     ↑        ↑  ↑     ↑
        │  │     │  │     │        │  │     │
       ●──┘    ●──┘     ●──┘     ●──┘    ●──┘
       (qısa ömürlü branch-lər, 1-2 gün maks.)

GitFlow (müqayisə üçün):

main:     ●─────────────────●─────────────────●──
develop:  ●──●──●──●──●──●──●──●──●──●──●──●──●──
           │           ↑   │              ↑
feature:   ●──●──●──●──┘   ●──●──●──●──●─┘
           (uzun ömürlü branch-lər, həftələr)
```

### TBD-nin İki Forması

```
1. Birbaşa Trunk-a Commit (kiçik komandalar):

   main:  ●──●──●──●──●──●──●──
           ↑  ↑  ↑  ↑  ↑  ↑  ↑
          Dev1  Dev2  Dev1 Dev2...

2. Qısa Ömürlü Branch-lər (böyük komandalar):

   main:  ●──●──●──●──●──●──●──
           ↑     ↑     ↑     ↑
           ●─────┘     ●─────┘
          (maks 1-2 gün, PR ilə)
```

## Əsas Əmrlər (Key Commands)

### Əsas Workflow

```bash
# 1. Main-dən yeni branch (qısa ömürlü)
git checkout main
git pull origin main
git checkout -b feature/add-search-filter

# 2. Kiçik commit-lər edin
git add .
git commit -m "feat: add search filter input component"

# 3. Main-i tez-tez sync edin
git pull origin main --rebase

# 4. PR yaradın və merge edin (squash merge)
git push origin feature/add-search-filter
# GitHub-da PR yaradın, review alın, merge edin

# 5. Yerli branch-i silin
git checkout main
git pull origin main
git branch -d feature/add-search-filter
```

### Trunk-a Birbaşa Push (kiçik komanda)

```bash
git checkout main
git pull origin main

# Dəyişiklik edin
git add .
git commit -m "feat: add search filter"

# Testləri lokal işlədin
php artisan test

# Push
git push origin main
```

### Rebase Workflow

```bash
# Branch-da işləyərkən main-i tez-tez rebase edin
git checkout feature/quick-fix
git fetch origin
git rebase origin/main

# Konflikt varsa həll edin
git add .
git rebase --continue

# Force push (öz branch-iniz olduğu üçün təhlükəsizdir)
git push origin feature/quick-fix --force-with-lease
```

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Feature Flag ilə Yarımçıq Feature Deploy

```php
// config/features.php
return [
    'new_search' => env('FEATURE_NEW_SEARCH', false),
    'dark_mode'  => env('FEATURE_DARK_MODE', false),
    'ai_suggest' => env('FEATURE_AI_SUGGEST', false),
];

// app/Helpers/Feature.php
class Feature
{
    public static function isEnabled(string $feature): bool
    {
        return config("features.{$feature}", false);
    }
}

// Controller-də istifadə
class SearchController extends Controller
{
    public function index(Request $request)
    {
        if (Feature::isEnabled('new_search')) {
            return $this->newSearch($request);
        }

        return $this->legacySearch($request);
    }
}

// Blade template-də
@if(Feature::isEnabled('new_search'))
    <x-new-search-component />
@else
    <x-legacy-search />
@endif
```

### Nümunə 2: Gündəlik TBD Workflow

```bash
# Səhər: Main-dən başla
git checkout main
git pull origin main

# Branch yarat (bugünkü iş üçün)
git checkout -b feature/improve-search-speed

# Kiçik addımlarla işlə
git commit -m "refactor: extract search query builder"
git commit -m "perf: add database index for search"
git commit -m "test: add search performance test"

# Gün ərzində main-i sync et
git pull origin main --rebase

# Günün sonuna qədər PR yarat
git push origin feature/improve-search-speed
gh pr create --title "perf: improve search speed" --body "Add index and optimize query"

# PR approve olduqdan sonra squash merge
# Branch silinir
```

### Nümunə 3: Laravel Feature Flag Package

```bash
# Laravel Pennant istifadə edin (Laravel 10+)
composer require laravel/pennant
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate
```

```php
// app/Providers/AppServiceProvider.php
use Laravel\Pennant\Feature;

public function boot(): void
{
    Feature::define('new-checkout', function (User $user) {
        return match(true) {
            $user->isAdmin() => true,           // Admin-lər həmişə görür
            $user->isBetaTester() => true,      // Beta testerlər görür
            app()->environment('local') => true, // Local-da həmişə aktiv
            default => false,                    // Qalanları görmür
        };
    });
}

// Controller-də
class CheckoutController extends Controller
{
    public function show()
    {
        if (Feature::active('new-checkout')) {
            return view('checkout.new');
        }
        return view('checkout.legacy');
    }
}

// Route-da middleware kimi
Route::middleware('feature:new-checkout')->group(function () {
    Route::get('/checkout/new', [NewCheckoutController::class, 'show']);
});
```

### Nümunə 4: Abstraction Branch ilə Böyük Refactoring

```php
// Addım 1: Abstraction layer yarat (main-ə merge et)
interface PaymentGatewayInterface
{
    public function charge(float $amount, string $token): PaymentResult;
    public function refund(string $transactionId): RefundResult;
}

// Addım 2: Köhnə implementasiyanı wrap et (main-ə merge et)
class StripeV1Gateway implements PaymentGatewayInterface
{
    public function charge(float $amount, string $token): PaymentResult
    {
        // Köhnə Stripe v1 kodu
        return $this->legacyStripe->processPayment($amount, $token);
    }
}

// Addım 3: Yeni implementasiya (main-ə merge et, amma hələ istifadə olunmur)
class StripeV2Gateway implements PaymentGatewayInterface
{
    public function charge(float $amount, string $token): PaymentResult
    {
        // Yeni Stripe v2 SDK
        return $this->stripe->paymentIntents->create([...]);
    }
}

// Addım 4: Feature flag ilə keçid (main-ə merge et)
// AppServiceProvider.php
$this->app->bind(PaymentGatewayInterface::class, function () {
    if (Feature::isEnabled('stripe_v2')) {
        return new StripeV2Gateway();
    }
    return new StripeV1Gateway();
});

// Addım 5: Flag-ı aktiv et, köhnə kodu sil
```

## Vizual İzah (Visual Explanation)

### TBD vs GitFlow Müqayisəsi

```
Trunk-Based Development:

      Gün 1    Gün 2    Gün 3    Gün 4    Gün 5
main: ●──●──●──●──●──●──●──●──●──●──●──●──●──●──●──
       ↑  ↑  ↑  ↑     ↑  ↑  ↑  ↑  ↑  ↑  ↑  ↑  ↑
       D1 D2 D1 D2    D1 D3 D2 D1 D3 D2 D1 D2 D3

       Deploy: ✓  ✓  ✓  ✓  ✓  ✓  ✓  ✓  ✓  ✓  ✓
       (hər merge-dən sonra deploy mümkün)

─────────────────────────────────────────────────────

GitFlow:

      Həftə 1         Həftə 2         Həftə 3
main:    ●───────────────────────────────●──
develop: ●──●──●──●──●──●──●──●──●──●──●──
          │        ↑                 ↑
feature:  ●──●──●──┘    ●──●──●──●──┘
          (uzun branch-lər)

       Deploy: yalnız release zamanı
```

### Feature Flag Lifecycle

```
┌─────────────────────────────────────────────┐
│            Feature Flag Lifecycle            │
├─────────────────────────────────────────────┤
│                                             │
│  1. Yaradılma                               │
│     flag = OFF (hamı üçün)                  │
│     ┌─────────┐                             │
│     │  OFF    │                             │
│     └────┬────┘                             │
│          ▼                                  │
│  2. Development                             │
│     flag = ON (developer-lər üçün)          │
│     ┌─────────┐                             │
│     │ DEV ON  │                             │
│     └────┬────┘                             │
│          ▼                                  │
│  3. Testing                                 │
│     flag = ON (beta users üçün)             │
│     ┌─────────┐                             │
│     │ BETA ON │                             │
│     └────┬────┘                             │
│          ▼                                  │
│  4. Rollout                                 │
│     flag = ON (10% → 50% → 100%)            │
│     ┌─────────┐                             │
│     │ GRADUAL │                             │
│     └────┬────┘                             │
│          ▼                                  │
│  5. Təmizlik                                │
│     flag silinir, köhnə kod silinir         │
│     ┌─────────┐                             │
│     │ REMOVED │                             │
│     └─────────┘                             │
└─────────────────────────────────────────────┘
```

### Continuous Integration Pipeline

```
Developer push edir
       │
       ▼
┌──────────────┐
│  Lint/Format │─── Fail → Block merge
└──────┬───────┘
       ▼
┌──────────────┐
│  Unit Tests  │─── Fail → Block merge
└──────┬───────┘
       ▼
┌──────────────┐
│ Integration  │─── Fail → Block merge
│    Tests     │
└──────┬───────┘
       ▼
┌──────────────┐
│  Code Review │─── 1+ approve lazım
└──────┬───────┘
       ▼
┌──────────────┐
│ Squash Merge │
│   to main    │
└──────┬───────┘
       ▼
┌──────────────┐
│ Auto Deploy  │─── staging/production
└──────────────┘
```

## PHP/Laravel Layihələrdə İstifadə

### Laravel Pennant ilə Feature Flags

```php
// database/migrations/create_features_table.php
// (Pennant avtomatik yaradır)

// Percentage rollout
Feature::define('new-dashboard', function (User $user) {
    // İstifadəçilərin 20%-i üçün aktiv
    return $user->id % 10 < 2;
});

// A/B testing
Feature::define('checkout-variant', function (User $user) {
    if ($user->id % 2 === 0) {
        return 'variant-a';
    }
    return 'variant-b';
});

// Controller
class DashboardController extends Controller
{
    public function index()
    {
        return Feature::active('new-dashboard')
            ? view('dashboard.new')
            : view('dashboard.classic');
    }
}

// Test-lərdə
class DashboardTest extends TestCase
{
    public function test_new_dashboard_displays_correctly()
    {
        Feature::activate('new-dashboard');

        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertViewIs('dashboard.new');
    }

    public function test_classic_dashboard_when_flag_off()
    {
        Feature::deactivate('new-dashboard');

        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertViewIs('dashboard.classic');
    }
}
```

### CI/CD Pipeline (GitHub Actions)

```yaml
# .github/workflows/trunk-based.yml
name: Trunk-Based CI/CD

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-interaction

      - name: PHP-CS-Fixer
        run: ./vendor/bin/php-cs-fixer fix --dry-run --diff

      - name: PHPStan
        run: ./vendor/bin/phpstan analyse

      - name: Tests
        run: php artisan test --parallel

  deploy:
    needs: quality
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to production
        run: |
          # Laravel Forge, Envoyer, və ya custom deploy
          curl -X POST ${{ secrets.DEPLOY_WEBHOOK }}
```

### Branch Protection Rules

```
GitHub Settings → Branches → main:
  ✓ Require pull request reviews (1 approval)
  ✓ Require status checks to pass
    ✓ quality (CI job)
  ✓ Require branches to be up to date
  ✓ Require linear history (squash merge only)
  ✗ Allow force pushes (disabled!)
  ✗ Allow deletions (disabled!)
```

## Interview Sualları

### S1: Trunk-Based Development nədir?

**Cavab**: TBD, bütün developer-lərin bir əsas branch-ə (main/trunk) tez-tez və kiçik dəyişikliklər etdiyi strategiyadır. Branch-lər qısa ömürlü olur (maksimum 1-2 gün). Yarımçıq feature-lar feature flag-lar vasitəsilə gizlədilir. Bu, continuous integration və continuous deployment-i asanlaşdırır.

### S2: TBD ilə GitFlow arasındakı əsas fərqlər nələrdir?

**Cavab**:
| Xüsusiyyət | TBD | GitFlow |
|------------|-----|---------|
| Branch ömrü | Saatlar, maks 1-2 gün | Həftələr, aylar |
| Branch sayı | Az (main + qısa branch-lər) | Çox (main, develop, feature, release, hotfix) |
| Deploy tezliyi | Gündə bir neçə dəfə | Planlanmış release-lər |
| Merge conflict | Az (kiçik diff-lər) | Çox (böyük diff-lər) |
| Feature flags | Vacibdir | Nadir istifadə olunur |
| CI/CD | Güclü CI/CD tələb edir | CI/CD olmadan da işləyir |
| Komanda ölçüsü | İstənilən, amma disiplin lazım | Böyük komandalar üçün yaxşı |

### S3: Feature flag-lar nədir və niyə TBD-də vacibdir?

**Cavab**: Feature flag-lar, kodda xüsusiyyətləri runtime-da aktiv/deaktiv etmək üçün şərti mexanizmdir. TBD-də vacibdir çünki:
1. Yarımçıq kod main-ə merge oluna bilər (flag off)
2. Tədricən rollout mümkündür (10% → 50% → 100%)
3. Problem olarsa, deploy etmədən feature söndürülə bilər
4. A/B testing asanlaşır

### S4: TBD-nin risklər nələrdir və necə azaldılır?

**Cavab**:
- **Risk**: Yarımçıq kod production-a düşə bilər → **Həll**: Feature flags
- **Risk**: Sınmış kod main-ə merge oluna bilər → **Həll**: Güclü CI pipeline, code review
- **Risk**: Böyük refactoring çətin olur → **Həll**: Abstraction branch pattern
- **Risk**: Feature flag borcu yaranır → **Həll**: Flag-ları vaxtında təmizləmək

### S5: "Abstraction Branch" pattern nədir?

**Cavab**: Böyük refactoring-i TBD-də etmək üçün pattern:
1. Abstraction layer (interface) yaradın, main-ə merge edin
2. Köhnə implementasiyanı interface-ə uyğunlaşdırın, main-ə merge edin
3. Yeni implementasiyanı yazın, main-ə merge edin (hələ istifadə olunmur)
4. Feature flag ilə yeni implementasiyaya keçin
5. Köhnə kodu silin

### S6: TBD üçün hansı CI/CD tələbləri var?

**Cavab**:
- Sürətli test suite (10-15 dəqiqədən az)
- Avtomatik lint və format yoxlaması
- Branch protection (test keçmədən merge yoxdur)
- Avtomatik deploy pipeline
- Monitoring və alerting (sınma tez aşkarlanmalıdır)

### S7: Nə zaman TBD seçməlisiniz, nə zaman GitFlow?

**Cavab**:
**TBD seçin**: SaaS layihələr, sürətli deployment, güclü CI/CD pipeline olan komandalar, microservice-lər.
**GitFlow seçin**: Mobil tətbiqlər (app store release), versioned software, enterprise layihələr, zəif CI/CD olan komandalar.

### S8: Trunk-a birbaşa push etmək təhlükəsiz deyil. Bunu necə təmin edirsiniz?

**Cavab**: 
1. Branch protection rules ilə birbaşa push-u bloklayın
2. PR tələb edin (ən azı 1 approve)
3. CI pipeline keçməlidir (tests, lint, static analysis)
4. Squash merge istifadə edin (təmiz tarixçə)
5. Avtomatik rollback mexanizmi quraşdırın

## Best Practices

### 1. Branch-ləri Qısa Saxlayın

```
✅ Yaxşı: 4 saat → PR → merge
✅ Yaxşı: 1 gün → PR → merge
⚠️  Diqqət: 2 gün → PR → merge
❌ Pis: 1 həftə → PR → merge
❌ Çox pis: 2+ həftə → PR → merge
```

### 2. Kiçik Commit-lər Edin

```bash
# Böyük feature-ı kiçik hissələrə bölün
# Hər hissə ayrıca PR olaraq merge olunur

# PR 1: Database layer
git commit -m "feat: add products table migration"
git commit -m "feat: add Product model with relationships"

# PR 2: Business logic
git commit -m "feat: add ProductService"
git commit -m "test: add ProductService unit tests"

# PR 3: API endpoints (feature flag ilə)
git commit -m "feat: add product API endpoints behind flag"
```

### 3. Feature Flag Təmizliyi

```php
// Feature flag-ları vaxtında silin!
// Flag yaratarkən "expiry date" qoyun

// Feature flag registry
return [
    'new_search' => [
        'enabled' => true,
        'created_at' => '2026-03-01',
        'remove_by' => '2026-05-01',  // Bu tarixdən sonra silinməlidir
        'owner' => 'search-team',
    ],
];
```

### 4. Güclü CI Pipeline

```
Minimum CI requirements üçün TBD:
  ✓ Unit tests (< 5 dəqiqə)
  ✓ Integration tests (< 10 dəqiqə)
  ✓ Static analysis (PHPStan, Psalm)
  ✓ Code formatting (PHP-CS-Fixer)
  ✓ Security scanning
  Total: < 15 dəqiqə
```

### 5. Main Həmişə Deploy Oluna Bilməlidir

```
Qızıl qayda: Main branch həmişə production-a deploy oluna bilər.
Əgər main sınıqsa, hər şeyi dayandırın və düzəldin.

main sınıq = bütün komanda bloklanır = ən yüksək prioritet
```

### 6. Code Review Sürətli Olmalıdır

```
TBD-də review gözləmə vaxtı:
  ✅ İdeal: 1-2 saat
  ⚠️  Qəbul edilir: yarım gün
  ❌ Çox uzun: 1+ gün

Sürətli review üçün:
  - PR-lar kiçik olmalıdır (200-300 sətir maks)
  - Reviewer rotation sistemi qurun
  - Avtomatik reviewer assign edin
```
