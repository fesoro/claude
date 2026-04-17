# CI/CD Konseptləri (Continuous Integration & Continuous Delivery/Deployment)

## Nədir? (What is it?)

CI/CD software development prosesini avtomatlaşdıran praktikalar toplusudur. Kod dəyişikliklərinin build, test və deploy mərhələlərindən keçərək production mühitinə çatdırılmasını təmin edir.

**CI (Continuous Integration)** - Developerlərin kodlarını gündə bir neçə dəfə shared repository-yə merge etməsi və hər merge-dən sonra avtomatik build və test proseslərinin işə düşməsi.

**CD (Continuous Delivery)** - CI-dən sonra kodun avtomatik olaraq staging/pre-production mühitinə deploy olunması. Production deploy-u manual approval tələb edir.

**CD (Continuous Deployment)** - CI-dən sonra kodun heç bir manual müdaxilə olmadan avtomatik olaraq production-a deploy olunması.

```
CI                    Continuous Delivery         Continuous Deployment
Code -> Build -> Test -> Stage -> [Manual] -> Prod
Code -> Build -> Test -> Stage -> [Auto] -> Prod
```

## Əsas Konseptlər (Key Concepts)

### Pipeline Stages

Tipik bir CI/CD pipeline aşağıdakı mərhələlərdən ibarətdir:

```
1. Source        - Kod dəyişikliyi trigger edir (push, PR, merge)
2. Build         - Kod compile olunur, dependencies install olunur
3. Test          - Unit, integration, E2E testlər işlədilir
4. Code Quality  - Linting, static analysis, security scanning
5. Package       - Artifact yaradılır (Docker image, zip, etc.)
6. Deploy Stage  - Staging mühitinə deploy olunur
7. Integration   - Staging-də integration testlər işlədilir
8. Approval      - Manual approval (Continuous Delivery-də)
9. Deploy Prod   - Production-a deploy olunur
10. Verify       - Smoke tests, health checks
```

### Build Prosesi

Build mərhələsində application kodu compile və paket olunur:

```bash
# PHP/Laravel Build nümunəsi
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Test Mərhələsi

```bash
# Unit Tests
php artisan test --testsuite=Unit

# Feature/Integration Tests
php artisan test --testsuite=Feature

# Code Coverage
php artisan test --coverage --min=80

# Static Analysis
./vendor/bin/phpstan analyse

# Code Style
./vendor/bin/pint --test
```

### Artifacts

Artifact - pipeline tərəfindən yaradılan build nəticəsidir:

- **Docker Image** - Containerized application
- **JAR/WAR** - Java applications
- **ZIP/TAR** - Compiled files
- **Composer vendor/** - PHP dependencies

```yaml
# GitHub Actions artifact nümunəsi
- name: Upload artifact
  uses: actions/upload-artifact@v4
  with:
    name: laravel-app
    path: |
      app/
      bootstrap/
      config/
      database/
      public/
      resources/
      routes/
      storage/
      vendor/
      composer.json
      artisan
```

### Trunk-Based Development

Trunk-based development - bütün developerlərin bir əsas branch (main/master) üzərində işlədiyi branching strategiyasıdır.

**Prinsipləri:**
- Short-lived feature branches (1-2 gün max)
- Kiçik, tez-tez commit-lər
- Feature flags ilə incomplete features gizlədilir
- Main branch həmişə deployable vəziyyətdədir

```
Feature Branch (short-lived):
main ──●──●──●──●──●──●──●──●──●
        \    /  \  /     \    /
         ●──●    ●●       ●──●
         (1 day)  (hours)  (1 day)

GitFlow (uzun müddətli branches - trunk-based DEYİL):
main ────────────●──────────────────●
develop ──●──●──●──●──●──●──●──●──●
           \        /   \        /
            ●──●──●●     ●──●──●
            (1-2 weeks)  (1-2 weeks)
```

### Feature Flags

Feature flags (feature toggles) - yeni feature-ları kod deploy etdikdən sonra runtime-da enable/disable etmə imkanı verir.

```php
// Laravel Feature Flags - config/features.php
return [
    'new_checkout' => env('FEATURE_NEW_CHECKOUT', false),
    'dark_mode' => env('FEATURE_DARK_MODE', false),
    'api_v2' => env('FEATURE_API_V2', false),
];

// Blade-də istifadə
@if(config('features.new_checkout'))
    @include('checkout.new')
@else
    @include('checkout.legacy')
@endif

// Controller-də istifadə
public function checkout(Request $request)
{
    if (config('features.new_checkout')) {
        return $this->newCheckoutFlow($request);
    }
    return $this->legacyCheckoutFlow($request);
}

// Laravel Pennant (official package) ilə
use Laravel\Pennant\Feature;

Feature::define('new-checkout', function (User $user) {
    return $user->is_beta_tester;
});

// İstifadə
if (Feature::active('new-checkout')) {
    // new flow
}
```

### Branching Strategiyaları CI/CD kontekstində

```
Environment Branch Mapping:
─────────────────────────────────────
Branch          -> Environment
─────────────────────────────────────
feature/*       -> PR Preview / Dev
develop         -> Staging
release/*       -> Pre-production
main/master     -> Production
hotfix/*        -> Production (urgent)
```

### Pipeline as Code

Pipeline konfiqurasiyası kod kimi saxlanılır (version controlled):

```yaml
# Məsələn, pipeline.yml
stages:
  - build
  - test
  - security
  - staging
  - production

build:
  stage: build
  script:
    - composer install --no-dev
    - npm ci && npm run build

test:
  stage: test
  script:
    - php artisan test
  coverage: '/Lines:\s+(\d+\.\d+)%/'

security:
  stage: security
  script:
    - composer audit
    - npm audit

staging:
  stage: staging
  script:
    - deploy_to_staging
  environment:
    name: staging
    url: https://staging.example.com

production:
  stage: production
  script:
    - deploy_to_production
  when: manual  # Manual approval
  environment:
    name: production
    url: https://example.com
```

## Praktiki Nümunələr (Practical Examples)

### Sadə CI Pipeline (Laravel)

```bash
#!/bin/bash
# ci.sh - Local CI simulation

set -e  # Exit on error

echo "=== STEP 1: Install Dependencies ==="
composer install --no-interaction --prefer-dist
npm ci

echo "=== STEP 2: Code Style Check ==="
./vendor/bin/pint --test

echo "=== STEP 3: Static Analysis ==="
./vendor/bin/phpstan analyse --memory-limit=512M

echo "=== STEP 4: Run Tests ==="
cp .env.testing .env
php artisan key:generate
php artisan test --coverage --min=80

echo "=== STEP 5: Build Assets ==="
npm run build

echo "=== STEP 6: Security Audit ==="
composer audit
npm audit --production

echo "=== CI PASSED ==="
```

### Deployment Script

```bash
#!/bin/bash
# deploy.sh - Zero-downtime deployment

set -e

APP_DIR="/var/www/laravel"
RELEASE_DIR="$APP_DIR/releases/$(date +%Y%m%d%H%M%S)"
SHARED_DIR="$APP_DIR/shared"
CURRENT_LINK="$APP_DIR/current"

echo "=== Creating release directory ==="
mkdir -p "$RELEASE_DIR"

echo "=== Extracting artifact ==="
tar -xzf /tmp/release.tar.gz -C "$RELEASE_DIR"

echo "=== Linking shared resources ==="
ln -nfs "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
ln -nfs "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

echo "=== Installing dependencies ==="
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

echo "=== Running migrations ==="
php artisan migrate --force

echo "=== Caching config ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== Switching symlink (atomic) ==="
ln -nfs "$RELEASE_DIR" "$CURRENT_LINK"

echo "=== Restarting services ==="
sudo systemctl reload php8.3-fpm
php artisan queue:restart

echo "=== Cleaning old releases (keep last 5) ==="
cd "$APP_DIR/releases"
ls -dt */ | tail -n +6 | xargs rm -rf

echo "=== Deployment complete ==="
```

### Rollback Script

```bash
#!/bin/bash
# rollback.sh

APP_DIR="/var/www/laravel"
RELEASES_DIR="$APP_DIR/releases"

# Get previous release
PREVIOUS=$(ls -dt "$RELEASES_DIR"/*/ | sed -n '2p')

if [ -z "$PREVIOUS" ]; then
    echo "ERROR: No previous release found"
    exit 1
fi

echo "Rolling back to: $PREVIOUS"
ln -nfs "$PREVIOUS" "$APP_DIR/current"

cd "$PREVIOUS"
php artisan migrate:rollback --force
php artisan config:cache
php artisan route:cache

sudo systemctl reload php8.3-fpm
php artisan queue:restart

echo "Rollback complete"
```

## PHP/Laravel ilə İstifadə

### Laravel-də CI/CD Best Practices

```php
// config/deploy.php - Deployment configuration
return [
    'maintenance_mode' => [
        'secret' => env('MAINTENANCE_SECRET', 'deploy-bypass-token'),
        'template' => resource_path('views/maintenance.blade.php'),
    ],

    'health_check' => [
        'url' => '/api/health',
        'timeout' => 30,
        'retries' => 3,
    ],
];

// app/Http/Controllers/HealthCheckController.php
class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = !in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'version' => config('app.version'),
            'timestamp' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            Cache::put('health_check', true, 10);
            return Cache::get('health_check') === true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        try {
            $size = Queue::size();
            return $size < 1000; // Alert if queue is too large
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            Storage::put('health_check.txt', 'ok');
            Storage::delete('health_check.txt');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
```

### Environment-specific Konfiqurasiya

```bash
# .env.testing
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
SESSION_DRIVER=array

# .env.staging
APP_ENV=staging
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=staging-db.internal
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# .env.production
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=prod-db.internal
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

## Interview Sualları

### Q1: CI və CD arasında fərq nədir?
**Cavab:** CI (Continuous Integration) developerlərin kodlarını tez-tez shared repository-yə merge etməsidir - hər merge-dən sonra avtomatik build və test işləyir. CD iki anlama gələ bilər: Continuous Delivery - kod avtomatik staging-ə deploy olunur amma production-a manual approval lazımdır; Continuous Deployment - kod heç bir manual müdaxilə olmadan avtomatik production-a deploy olunur.

### Q2: Pipeline-da test fail olsa nə baş verir?
**Cavab:** Pipeline fail-safe dizayn olunmalıdır. Test fail olanda pipeline dayandırılır, deployment baş vermir. Developer notification alır (email, Slack). Fix commit push olunanda pipeline yenidən işləyir. Branch protection rules ilə failing tests olan PR merge oluna bilməz.

### Q3: Trunk-based development nədir və niyə istifadə olunur?
**Cavab:** Bütün developerlərin bir main branch üzərində işlədiyi strategiyadır. Feature branches çox qısa ömürlü olur (1-2 gün). Üstünlükləri: merge conflicts azalır, CI daha effektiv olur, deployment frequency artır. Feature flags ilə incomplete features gizlədilir.

### Q4: Feature flags nə üçün istifadə olunur?
**Cavab:** Feature flags incomplete və ya risky feature-ları production-da gizlətmək üçün istifadə olunur. Faydaları: trunk-based development enable edir, A/B testing imkanı verir, canary releases mümkün edir, problem olduqda feature-u kod deploy etmədən disable etmək olur.

### Q5: Zero-downtime deployment necə edilir?
**Cavab:** Symlink-based deployment istifadə olunur - yeni release ayrı qovluğa deploy olunur, hazır olduqda symlink atomic olaraq dəyişdirilir. Blue-green deployment - iki identik mühit saxlanılır, traffic switch olunur. Rolling deployment - instance-lar tədricən yenilənir. Load balancer arxasında health check istifadə olunur.

### Q6: CI/CD pipeline-da security necə təmin olunur?
**Cavab:** Secrets management (environment variables, vault), dependency scanning (composer audit, npm audit), SAST (static analysis), DAST (dynamic testing), container image scanning, signed artifacts, least privilege access, audit logs.

### Q7: Artifact nədir və niyə vacibdir?
**Cavab:** Artifact pipeline-ın build mərhələsində yaranan nəticədir (Docker image, ZIP, JAR). Vacibdir çünki: "build once, deploy many" prinsipini təmin edir - eyni artifact staging və production-a deploy olunur. Reproducibility verir - hər build-in nəticəsi saxlanılır və rollback mümkündür.

### Q8: Monorepo vs multirepo - CI/CD-yə təsiri?
**Cavab:** Monorepo-da bir CI pipeline bütün servislər üçün işləyir, affected service detection lazımdır, build times uzun ola bilər. Multirepo-da hər servisin öz pipeline-ı olur, idarəetmə sadədir amma cross-service dəyişikliklər çətindir. Monorepo üçün Bazel, Nx kimi build toollar istifadə olunur.

## Best Practices

1. **Pipeline Speed** - Pipeline 10 dəqiqədən az olmalıdır. Caching, parallel testing istifadə edin
2. **Fail Fast** - Ən tez fail olan testlər əvvəl işləsin (lint -> unit -> integration -> e2e)
3. **Idempotent Deployments** - Eyni deployment iki dəfə işləsə eyni nəticə verməlidir
4. **Immutable Artifacts** - Build bir dəfə olsun, eyni artifact hər yerə deploy olunsun
5. **Environment Parity** - Staging production-a mümkün qədər oxşar olmalıdır
6. **Automated Rollback** - Health check fail olsa avtomatik rollback baş verməlidir
7. **Secrets in Vault** - Heç vaxt secrets-i pipeline YAML-da hardcode etməyin
8. **Branch Protection** - Main branch-a direct push qadağan, PR required, CI must pass
9. **Monitoring After Deploy** - Deploy-dan sonra metrics və logs izlənilməlidir
10. **Documentation** - Pipeline-ların dokumentasiyası aktual saxlanılmalıdır
