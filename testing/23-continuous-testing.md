# Continuous Testing (Senior)
## İcmal

Continuous testing, testlərin CI/CD (Continuous Integration/Continuous Deployment) pipeline-ında
avtomatik işlədilməsi prosesidir. Hər code push, pull request və ya deployment zamanı testlər
avtomatik icra olunur və nəticəsinə görə pipeline davam edir və ya dayanır.

Əsas məqsəd "shift left" prinsipini tətbiq etməkdir - bug-ları mümkün qədər erkən tapmaq.
Developer kod push etdikdən dəqiqələr sonra test nəticəsini alır, production-a bug getmə
riski minimuma enir.

### Niyə Continuous Testing Vacibdir?

1. **Erkən feedback** - Bug push-dan dəqiqələr sonra tapılır
2. **Avtomatik qoruma** - Fail olan test production-a deployment-i bloklayır
3. **Developer güvəni** - Testlər keçirsə, merge etmək təhlükəsizdir
4. **Regression prevention** - Köhnə bug-lar təkrar yaranmır
5. **Quality gate** - Minimum coverage, test pass tələbi

## Niyə Vacibdir

- **"Shift left" prinsipi** — bug-ı developer maşınında tapmaq production-da tapmaqdan 100x ucuzdur; CI pipeline hər push-da bu fürsəti avtomatik yaradır
- **Merge qapısı kimi** — branch protection rules ilə testlər keçmədən heç bir kod main branch-ə merge edilə bilmir; bu insan faktorunu aradan qaldırır
- **Paralel icra ilə sürət** — unit, feature, integration test-lərini paralel işlətmək 10 dəqiqəlik pipeline-ı 3 dəqiqəyə endirə bilər; developer vaxtı qorunur
- **Flaky test idarəsi** — CI-da müntəzəm işlədilən testlər flaky davranışı erkən aşkar edir; local-da bəzən olan problem CI-da hər dəfə görünür
- **Coverage trend izləmə** — Codecov kimi alətlər PR-larda coverage düşüşünü göstərir; trendə baxmaq test debt-in yığılmasının qarşısını alır

## Əsas Anlayışlar

### CI/CD Pipeline Test Strategiyası

```
Push/PR → [Lint] → [Unit Tests] → [Feature Tests] → [Integration] → [Deploy]
            │          │                │                  │
           ~30s       ~2min           ~5min              ~10min
            │          │                │                  │
         Fastest ──────────────────────────────────── Slowest

Fail-fast prinsipi: Ən sürətli testlər əvvəl işləyir.
Əgər lint fail edirsə, unit testlər işlədilmir (vaxt qənaəti).
```

### Test Stages

```
Stage 1: Static Analysis (saniyələr)
  ├── PHP CS Fixer / Pint
  ├── PHPStan / Psalm
  └── ESLint

Stage 2: Unit Tests (1-3 dəqiqə)
  ├── Sürətli, framework-siz
  └── Paralel icra

Stage 3: Feature/Integration Tests (3-10 dəqiqə)
  ├── Database tələb edir
  ├── HTTP testlər
  └── Paralel icra

Stage 4: E2E/Browser Tests (10-30 dəqiqə)
  ├── Headless browser
  ├── Full stack test
  └── Ən yavaş

Stage 5: Performance/Security (optional)
  ├── Load testing
  └── SAST/DAST scans
```

### Flaky Tests

```
Flaky test: Bəzən pass, bəzən fail olan test.

Əsas səbəblər:
  1. Timing/race conditions
  2. Shared state between tests
  3. External service dependency
  4. Random data generation
  5. Time-dependent logic
  6. File system operations
  7. Network issues

Həll yolları:
  1. Test isolation (RefreshDatabase)
  2. Mock external services
  3. Deterministic test data
  4. Carbon::setTestNow() for time
  5. Retry mechanism (son çarə)
  6. Quarantine flaky tests
```

## Praktik Baxış

### Best Practices

1. **Fail-fast pipeline** - Ən sürətli testlər əvvəl işləsin
2. **Paralel execution** - Mümkün olan yerdə paralel işlət
3. **Caching** - Composer vendor, npm node_modules cache-lə
4. **Coverage tracking** - Trend-i izlə, düşməsinə icazə vermə
5. **Notifications** - Failure halında komandanı xəbərdar et
6. **Branch protection** - Test keçmədən merge olunmasın

### Anti-Patterns

1. **Yavaş pipeline** - 30+ dəqiqə CI developer-ləri gözlətir
2. **Flaky testləri ignore etmək** - Bütün CI-ya güvəni sarsıdır
3. **Test-siz deploy** - Heç bir test olmadan production-a deploy etmək
4. **Yalnız main branch-da test** - PR-da da test işləməlidir
5. **Manual test trigger** - Avtomatik olmalıdır, manual deyil
6. **Retry ilə flaky test-i gizlətmək** - Root cause-u tapın, retry son çarədir

## Nümunələr

### GitHub Actions - Tam Pipeline

```yaml
# .github/workflows/ci.yml
name: CI Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  # Stage 1: Static Analysis
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: php-cs-fixer, phpstan

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Code style check
        run: vendor/bin/pint --test

      - name: Static analysis
        run: vendor/bin/phpstan analyse --no-progress

  # Stage 2: Unit Tests
  unit-tests:
    needs: lint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: pcov

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Run unit tests
        run: vendor/bin/phpunit --testsuite=Unit --coverage-clover=coverage.xml

      - name: Upload coverage
        uses: codecov/codecov-action@v4
        with:
          file: ./coverage.xml

  # Stage 3: Feature Tests
  feature-tests:
    needs: lint
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

      redis:
        image: redis:7
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Prepare environment
        run: |
          cp .env.testing .env
          php artisan key:generate

      - name: Run feature tests
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password
          REDIS_HOST: 127.0.0.1
        run: php artisan test --testsuite=Feature --parallel --processes=4

  # Stage 4: Deploy (yalnız main branch)
  deploy:
    needs: [unit-tests, feature-tests]
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to production
        run: echo "Deploying..."
```

### Parallel Test Configuration

```yaml
# Paralel test execution with matrix
feature-tests:
  runs-on: ubuntu-latest
  strategy:
    matrix:
      testsuite: [Feature/Api, Feature/Web, Feature/Console]
    fail-fast: true

  steps:
    - uses: actions/checkout@v4
    - name: Run test suite
      run: vendor/bin/phpunit tests/${{ matrix.testsuite }}
```

## Praktik Tapşırıqlar

### .env.testing Konfiqurasiyası

```env
APP_ENV=testing
APP_DEBUG=true
APP_KEY=base64:test-key-here

DB_CONNECTION=sqlite
DB_DATABASE=:memory:

CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array

TELESCOPE_ENABLED=false
```

### Test Reporter/Logger

```php
<?php

namespace Tests\Support;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;

class SlowTestReporter implements TestListener
{
    private float $startTime;
    private array $slowTests = [];
    private float $threshold = 1.0; // 1 saniyə

    public function startTest(Test $test): void
    {
        $this->startTime = microtime(true);
    }

    public function endTest(Test $test, float $time): void
    {
        if ($time > $this->threshold) {
            $this->slowTests[] = [
                'name' => $test->getName(),
                'class' => get_class($test),
                'time' => round($time, 2),
            ];
        }
    }

    public function endTestSuite(TestSuite $suite): void
    {
        if (!empty($this->slowTests) && $suite->getName() === '') {
            echo "\n\n🐌 Slow Tests (>{$this->threshold}s):\n";
            usort($this->slowTests, fn ($a, $b) => $b['time'] <=> $a['time']);

            foreach ($this->slowTests as $test) {
                echo "  {$test['time']}s - {$test['class']}::{$test['name']}\n";
            }
        }
    }

    // Digər interface method-ları (boş implement)
    public function addError(Test $test, \Throwable $t, float $time): void {}
    public function addFailure(Test $test, \PHPUnit\Framework\AssertionFailedError $e, float $time): void {}
    public function addWarning(Test $test, \PHPUnit\Framework\Warning $e, float $time): void {}
    public function addIncompleteTest(Test $test, \Throwable $t, float $time): void {}
    public function addRiskyTest(Test $test, \Throwable $t, float $time): void {}
    public function addSkippedTest(Test $test, \Throwable $t, float $time): void {}
    public function startTestSuite(TestSuite $suite): void {}
}
```

### Flaky Test Retry Mechanism

```php
<?php

namespace Tests\Support\Traits;

trait RetryOnFailure
{
    /**
     * Flaky testlər üçün retry mexanizmi
     * İstifadəsi: $this->retryTest(fn() => $this->someAssertion(), 3);
     */
    protected function retryTest(callable $test, int $maxRetries = 3): void
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $test();
                return; // Uğurlu
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < $maxRetries) {
                    // Database/state reset
                    $this->refreshApplication();
                    usleep(100_000 * $attempt); // Progressive delay
                }
            }
        }

        throw $lastException;
    }
}
```

### Test Performance Monitoring

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected int $queryCount = 0;
    protected float $testStartTime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStartTime = microtime(true);
        $this->queryCount = 0;

        DB::listen(function () {
            $this->queryCount++;
        });
    }

    protected function tearDown(): void
    {
        $duration = microtime(true) - $this->testStartTime;

        if ($duration > 2.0) {
            fwrite(STDERR, sprintf(
                "\n⚠️  Slow test: %s (%.2fs, %d queries)\n",
                $this->name(),
                $duration,
                $this->queryCount,
            ));
        }

        parent::tearDown();
    }

    protected function assertQueryCountLessThan(int $max): void
    {
        $this->assertLessThan(
            $max,
            $this->queryCount,
            "Expected fewer than {$max} queries, got {$this->queryCount}"
        );
    }
}
```

### GitHub Actions Status Badge

```markdown
<!-- README.md-də -->
![CI](https://github.com/username/repo/actions/workflows/ci.yml/badge.svg)
![Coverage](https://codecov.io/gh/username/repo/branch/main/graph/badge.svg)
```

### Branch Protection Rules

```
GitHub Settings → Branches → Branch protection rules:

✅ Require status checks to pass before merging
  ✅ lint
  ✅ unit-tests
  ✅ feature-tests

✅ Require branches to be up to date before merging
✅ Require pull request reviews before merging
```

## Ətraflı Qeydlər

### 1. CI/CD pipeline-da testlər necə təşkil olunmalıdır?
**Cavab:** Fail-fast prinsipi ilə: əvvəl lint/static analysis (saniyələr), sonra unit tests (dəqiqələr), sonra feature tests (dəqiqələr), sonra E2E (əgər varsa). Hər stage əvvəlkindən asılıdır - lint fail edirsə, testlər işləmir. Unit və feature testlər paralel işləyə bilər.

### 2. Flaky testlərlə necə mübarizə edirsiniz?
**Cavab:** Root cause analizi ən vacibdir - timing, shared state, external dependency. Həll: mock external services, RefreshDatabase trait, Carbon::setTestNow() zaman üçün, deterministic test data. Retry son çarədir. Flaky testləri quarantine edib ayrıca izləyirəm. CI-da flaky test alert sistemi qururam.

### 3. Test parallelization-ın üstünlükləri və çətinlikləri nələrdir?
**Cavab:** Üstünlükləri: 4x process 4x sürətli (təxminən), CI/CD pipeline vaxtını azaldır. Çətinlikləri: testlər arası shared state olmamalı, hər process ayrı database lazımdır, file system conflict ola bilər, bəzi testlər paraleldə fail ola bilər. Laravel --parallel flag-i database izolyasiyanı avtomatik idarə edir.

### 4. Test coverage gate nədir?
**Cavab:** CI/CD-də minimum coverage tələbi. Məsələn `--coverage-min=80` - coverage 80%-dən aşağı düşsə build fail olur. Bu yeni kodun test olmadan merge edilməsinin qarşısını alır. Coverage-ı artırmaq məcburiyyəti yox, düşməsinin qarşısını almaq məqsədi daşıyır.

### 5. GitHub Actions-da test mühitini necə qurarsınız?
**Cavab:** `services` ilə MySQL/Redis container qaldırılır, `shivammathur/setup-php` action ilə PHP qurulur, `actions/cache` ilə composer vendor cache-lənir, `.env.testing` konfiqurasiyası istifadə olunur. Matrix strategy ilə fərqli PHP versiyalarında test etmək mümkündür.

### 6. Test reporting nə üçün vacibdir?
**Cavab:** Test nəticələrini vizual göstərir, trend-ləri izləyir, slow testləri tapır, flaky testləri müəyyən edir. JUnit XML formatı çox CI tool ilə uyğundur. Codecov/Coveralls coverage trend-ini göstərir. Slack/email notification test failure halında komandanı xəbərdar edir.

### 7. Deployment pipeline-da smoke testlər nə üçün lazımdır?
**Cavab:** Deploy-dan sonra production-da əsas funksionallığın işlədiyini yoxlayan minimal test setidir. Login edə bilir? Əsas səhifələr açılır? API cavab verir? Smoke test fail edirsə, avtomatik rollback edilir. Full test suite deyil, yalnız critical path test edilir.

## Əlaqəli Mövzular

- [Testing Best Practices (Senior)](30-testing-best-practices.md)
- [Performance Testing (Senior)](20-performance-testing.md)
- [Mutation Testing (Senior)](22-mutation-testing.md)
- [Code Coverage (Middle)](12-code-coverage.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
- [Test Organization (Middle)](13-test-organization.md)
