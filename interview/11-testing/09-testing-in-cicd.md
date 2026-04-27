# Testing in CI/CD Pipeline (Senior ⭐⭐⭐)

## İcmal
CI/CD pipeline-da testing — automated testlərin commit-dən deploy-a qədər olan prosesin ayrılmaz hissəsini təşkil etməsi deməkdir. Sadəcə "testləri CI-da işlətmək" deyil, testləri doğru sıra ilə, doğru parallelizasiya ilə, doğru qapı mexanizmləri ilə qurmaq — bu Senior engineering bacarığıdır. Interview-larda "CI/CD pipeline-ınız necə qurulmuşdur?" sualının test tərəfi bu mövzunu əhatə edir.

## Niyə Vacibdir
Developer feedback loop-un uzunluğu birbaşa produktivliyə təsir edir. CI pipeline 45 dəqiqə işləyirsə developer-lər push etməkdən çəkinir, böyük PR-lar yaranır, merge conflict-lər artır. Sürətli, etibarlı CI pipeline-ı qurmaq team-in sürətini artırır. Test stratejisi pipeline dizaynının ən vacib komponentidir.

## Əsas Anlayışlar

### Test Stages — Sürətdən Ağıra Prinsipi:

Testlər sürətdən ağıra doğru sıralanmalıdır. Yavaş test əvvəl işlərsə, developer uzun müddət gözləyir:

```
Stage 1: Static Analysis (10-30s)
  ├── PHP-CS-Fixer / PHPCS (code style)
  ├── PHPStan / Psalm (static analysis)
  └── Composer security check

Stage 2: Unit Tests (1-3min)
  ├── PHPUnit unit test suite
  └── Code coverage threshold check

Stage 3: Integration Tests (3-8min)
  ├── Database migrations
  ├── PHPUnit feature/integration tests
  └── API contract verification

Stage 4: E2E Tests (5-20min) — sadəcə main/staging branch
  ├── Dusk / Playwright
  └── Critical user journey-lar

Stage 5: Performance Tests (10-30min) — release branch
  └── k6 load test
```

---

### Fast Fail Strategiyası:

Ən sürətli yoxlamalar əvvəl gəlir. Bu sayədə developer xəta haqqında 30 saniyədə xəbər tutur:

```yaml
# GitHub Actions nümunəsi
jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-dev
      - run: vendor/bin/phpstan analyse src --level=8
      - run: vendor/bin/php-cs-fixer check

  unit-tests:
    needs: lint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install
      - run: vendor/bin/phpunit --testsuite=unit

  integration-tests:
    needs: unit-tests
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: test_db
          POSTGRES_PASSWORD: secret
    steps:
      - uses: actions/checkout@v4
      - run: composer install
      - run: php artisan migrate --env=testing
      - run: vendor/bin/phpunit --testsuite=integration
```

---

### Test Parallelization:

Böyük test suite-ləri parallel icra etmək sürəti kəskin azaldır.

**PHPUnit Parallel Runner (paratest):**
```bash
# paratest — PHP parallel test runner
composer require --dev brianium/paratest

vendor/bin/paratest --processes=4 --runner=WrapperRunner
```

**GitHub Actions matrix strategy:**
```yaml
jobs:
  test:
    strategy:
      matrix:
        php: [8.2, 8.3]
        test-group: [unit, integration, feature]
    steps:
      - run: vendor/bin/phpunit --group=${{ matrix.test-group }}
```

**Test database isolation for parallel tests:**
```php
// Hər parallel process ayrı database istifadə edir
// .env.testing-da:
// DB_DATABASE=test_${TEST_TOKEN}

// ya da RefreshDatabase trait ilə transaction rollback:
use RefreshDatabase; // hər test transaction içində icra edilir
```

---

### Coverage Gates:

Coverage threshold-ları CI-da enforced olmalıdır:

**PHPUnit coverage threshold:**
```xml
<!-- phpunit.xml -->
<coverage>
    <report>
        <clover outputFile="coverage.xml"/>
    </report>
</coverage>
```

```bash
# Exit code 1 əgər coverage thresholds keçilməsə
vendor/bin/phpunit --coverage-clover=coverage.xml

# Codecov upload + threshold
./codecov --token=$CODECOV_TOKEN --fail-on-error
```

**SonarQube quality gate:**
```yaml
- name: SonarQube Scan
  uses: sonarqube-quality-gate-action@v1
  with:
    scanMetadataReportFile: .scannerwork/report-task.txt
  env:
    SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
# PR coverage 80% altına düşərsə quality gate fail olur
```

---

### Test Database Strategiyaları CI-da:

**Option 1: GitHub Actions service containers:**
```yaml
services:
  mysql:
    image: mysql:8.0
    env:
      MYSQL_DATABASE: testing
      MYSQL_ROOT_PASSWORD: secret
    ports:
      - 3306:3306
    options: --health-cmd="mysqladmin ping" --health-interval=10s
```

**Option 2: SQLite in-memory (sürətli, amma fərqli):**
```ini
; phpunit.xml
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

**Option 3: Testcontainers (ideal, amma kompleks):**
```php
use Testcontainers\Containers\GenericContainer;

$container = (new GenericContainer('postgres:16'))
    ->withEnv(['POSTGRES_DB=test', 'POSTGRES_PASSWORD=test'])
    ->withExposedPorts(5432)
    ->start();
```

---

### Branch Strategy ilə Test Scope:

| Branch | Hansı testlər | Məqsəd |
|--------|--------------|--------|
| feature/* | Lint + Unit + Integration | Sürətli feedback |
| main | Lint + Unit + Integration + E2E | Merge quality gate |
| release/* | Hamı + Performance + Security scan | Deploy gate |

---

### Flaky Test İdarəsi CI-da:

```yaml
# Flaky test retry mexanizmi (GitHub Actions)
- name: Run tests with retry
  uses: nick-fields/retry@v2
  with:
    max_attempts: 3
    command: vendor/bin/phpunit --testsuite=e2e
    timeout_minutes: 15
```

---

### Test Result Reporting:

```yaml
# JUnit format output → GitHub PR annotations
- name: Run PHPUnit
  run: vendor/bin/phpunit --log-junit junit.xml

- name: Publish Test Results
  uses: EnricoMi/publish-unit-test-result-action@v2
  if: always()
  with:
    junit_files: junit.xml
```

---

### Shift-Left Testing:

"Shift-left" — testləri development pipeline-ında mümkün qədər sola (erkənə) çəkmək deməkdir:

- Pre-commit hook ilə lint + unit test (commitdən əvvəl)
- PR-da integration test (merge etmədən əvvəl)
- Staging-da E2E test (production-dan əvvəl)

**Husky / PHP pre-commit hook:**
```bash
#!/bin/sh
# .git/hooks/pre-commit
vendor/bin/phpstan analyse src --level=8
vendor/bin/phpunit --testsuite=unit
```

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"CI/CD pipeline-ınızda test necə qurulmuşdur?" sualına yalnız "GitHub Actions istifadə edirik, testlər orada işləyir" demə. Stage-lər, parallelization, coverage gate, fast-fail strategiyası haqqında danış. "Unit test ilk, ən sürətli stage" niyə vacibdir — izah et.

**Follow-up suallar:**
- "Pipeline 40 dəqiqə işlədikdə nə edirsiniz?"
- "Flaky test CI-da nasıl idarə edilir?"
- "Feature branch-lərdə E2E test icra edirsinizmi?"

**Ümumi səhvlər:**
- Bütün testləri bir stage-də icra etmək (yavaş feedback)
- Coverage gate olmadan deploy etmək
- Test database-i parallel process-lər arasında paylaşmaq (race condition)
- E2E testlər hər PR-da icra etmək (çox yavaş)

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Testləri CI-da işlədirəm" vs "Testlər 3 stage-dədir — lint/unit sürətli, integration sonra. Feature branch-lərdə E2E skip olunur, main-də tam icra edilir. Paratest ilə 4 parallel process, 8 dəqiqədan 3 dəqiqəyə endirdik."

## Nümunələr

### Tipik Interview Sualı
"CI pipeline-ınızda test strategiyanız necədir? Niyə bu şəkildə qurulmuşdur?"

### Güclü Cavab
"CI pipeline-ımız üç əsas stage-dən ibarətdir. Birincisi, 30 saniyəlik lint + static analysis — PHPStan, PHP-CS-Fixer. Developer syntax xətasını dərhal görür. İkincisi, unit testlər — paratest ilə 4 parallel process, 2 dəqiqə. Üçüncüsü, integration testlər — real PostgreSQL ilə, GitHub Actions service container, 5 dəqiqə. E2E testlər yalnız main branch-ə merge olduqda işləyir — feature PR-larında skip edilir. Coverage threshold 80% — altına düşəndə PR merge olmur. Bu strukturla developer feedback loop 8 dəqiqədir."

## Praktik Tapşırıqlar
- GitHub Actions workflow yaz: lint → unit → integration stage-ləri ilə
- Paratest install et, test suite parallel icra et
- Coverage gate əlavə et: PR coverage 75% altına düşəndə fail olsun
- Pre-commit hook qur: commit etmədən əvvəl unit test icra edilsin

## Əlaqəli Mövzular
- [01-testing-pyramid.md](01-testing-pyramid.md) — CI stage-ləri pyramid ilə uyğunlaşır
- [10-flaky-tests.md](10-flaky-tests.md) — CI-da flaky test idarəsi
- [01-cicd-pipeline-design.md](../12-devops/01-cicd-pipeline-design.md) — Pipeline arxitekturası
- [05-test-coverage-metrics.md](05-test-coverage-metrics.md) — Coverage gate qurulması
