# Test Environment Management

## Nədir? (What is it?)

**Test Environment Management** — test üçün lazım olan infrastruktur, xidmətlər,
verilənlər bazası və konfiqurasiyaların yaradılması, idarə edilməsi və təmizlənməsi
prosesidir.

**Məqsəd:** Test mühiti **production-a oxşar**, **təkrarlanabilir** və **izolyasiya**
edilmiş olmalıdır. "Works on my machine" problemi məhz mühit fərqlərindən yaranır.

**Environment növləri:**
- **Local (dev)** — developer kompüteri
- **CI** — pipeline (GitHub Actions, GitLab CI)
- **Ephemeral** — branch/PR üçün müvəqqəti mühit
- **Staging** — production-un klonu (pre-prod)
- **Production** — real istifadəçilər

## Əsas Konseptlər (Key Concepts)

### 1. Environment Parity

**12-Factor App** prinsipi: dev/staging/prod arasında fərqlər minimal olmalıdır.

| Sahə | Fərq problemi |
|------|---------------|
| **DB** | SQLite-da işləyir, MySQL-də yox |
| **OS** | Mac-da işləyir, Linux-da yox |
| **Versiyalar** | PHP 8.2 vs PHP 8.1 |
| **Servislər** | Mock S3 vs real S3 |
| **Konfiq** | Fərqli env dəyərləri |

### 2. Ephemeral (Keçici) Environments

Hər **PR/branch** üçün avtomatik yaradılan müvəqqəti mühit:
- PR açıldıqda → environment yaradılır
- PR merge/close olduqda → silinir
- URL: `pr-123.staging.example.com`

**Faydaları:**
- QA hər PR-i ayrıca test edir
- Dizayner UI-ni preview edir
- Stakeholder tezliklə demo görür

### 3. Testcontainers

**Kitabxana** — testlər üçün **Docker konteyner-ləri** proqramlaşdırma yolu ilə idarə edir.

```
Test başlayır → MySQL konteyner qalxır → Test işləyir → Konteyner silinir
```

**Üstünlük:** Real MySQL/Redis/Kafka ilə test — production-a yaxın.

### 4. Staging vs Production

**Staging**: production-un klonu, **real traffic yoxdur**, QA burada test edir.
**Production**: real istifadəçilər. Smoke testlər deploy-dan sonra işləyir.

### 5. Smoke Test on Deploy

Deploy-dan **dərhal sonra** işləyən basit testlər — sistem sağdır?
```
GET /health → 200 OK
GET /api/users → 200 OK (basic functionality)
```

### 6. Environment-Specific Configs

`.env.local`, `.env.testing`, `.env.staging`, `.env.production` — hər biri öz
mühiti üçün dəyərlər saxlayır.

## Praktiki Nümunələr

### Nümunə 1: Works on my machine
```
Dev: SQLite, PHP 8.2, Redis 7
Prod: MySQL 5.7, PHP 8.1, Redis 6
→ Prod-da string-case-sensitive search fərqli işləyir
Həll: Dev-də də MySQL istifadə edin (Docker)
```

### Nümunə 2: Ephemeral environment
```
PR #123 açılır → Vercel/Netlify preview URL yaradır:
pr-123.preview.example.com → QA test edir
```

### Nümunə 3: Smoke test on deploy
```
Deploy script:
1. kubectl apply -f manifest.yaml
2. curl https://api.example.com/health → 200
3. Success → traffic switch (blue/green)
```

## PHP/Laravel ilə Tətbiq

### 1. `.env.testing` Fayl

```env
# .env.testing
APP_NAME=Laravel
APP_ENV=testing
APP_KEY=base64:TEST_KEY_HERE
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=:memory:

CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array

# External services disable
STRIPE_KEY=pk_test_fake
STRIPE_SECRET=sk_test_fake
AWS_BUCKET=test-bucket

# Broadcasting
BROADCAST_DRIVER=null

# Disable rate limiting
RATE_LIMIT_ENABLED=false
```

### 2. phpunit.xml konfiqurasiyası

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

### 3. Environment-Specific TestCase

```php
// tests/TestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Yalnız testing environment-də
        if (!app()->environment('testing')) {
            throw new \Exception('Testlər yalnız testing env-də işləyə bilər!');
        }

        // External service-ləri block et
        \Http::preventStrayRequests();
    }
}
```

### 4. Docker Compose - Local Dev (Production Parity)

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build: .
    volumes:
      - .:/var/www/html
    environment:
      DB_HOST: mysql
      REDIS_HOST: redis

  mysql:
    image: mysql:8.0  # Production ilə eyni versiya
    environment:
      MYSQL_DATABASE: laravel
      MYSQL_ROOT_PASSWORD: secret
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7-alpine  # Production ilə eyni versiya

  mailhog:
    image: mailhog/mailhog
    ports:
      - "8025:8025"

volumes:
  mysql_data:
```

### 5. GitHub Actions - CI Environment

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo, pdo_mysql, redis

      - name: Copy .env
        run: cp .env.example .env.testing

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Generate key
        run: php artisan key:generate --env=testing

      - name: Run migrations
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: root
        run: php artisan migrate --env=testing

      - name: Run tests
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          REDIS_HOST: 127.0.0.1
        run: php artisan test
```

### 6. Ephemeral Environment (Preview Deploy)

```yaml
# .github/workflows/preview.yml
name: Preview Deploy

on:
  pull_request:
    types: [opened, synchronize, reopened]

jobs:
  deploy-preview:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Deploy to Kubernetes namespace
        run: |
          NAMESPACE=pr-${{ github.event.number }}
          kubectl create namespace $NAMESPACE || true

          helm upgrade --install app-preview ./chart \
            --namespace $NAMESPACE \
            --set image.tag=${{ github.sha }} \
            --set ingress.host=pr-${{ github.event.number }}.preview.example.com

      - name: Comment PR with URL
        uses: actions/github-script@v6
        with:
          script: |
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: 'Preview URL: https://pr-${{ github.event.number }}.preview.example.com'
            })

  cleanup:
    if: github.event.action == 'closed'
    runs-on: ubuntu-latest
    steps:
      - name: Delete namespace
        run: kubectl delete namespace pr-${{ github.event.number }}
```

### 7. Smoke Test on Deploy

```php
// tests/Smoke/HealthCheckTest.php
namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

class HealthCheckTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('SMOKE_TEST_URL') ?: 'https://api.example.com';
    }

    public function test_health_endpoint_returns_200(): void
    {
        $response = file_get_contents("{$this->baseUrl}/health");
        $data = json_decode($response, true);

        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('connected', $data['database']);
        $this->assertEquals('connected', $data['redis']);
    }

    public function test_api_returns_valid_response(): void
    {
        $ch = curl_init("{$this->baseUrl}/api/v1/status");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertEquals(200, $code);
    }
}
```

### 8. Laravel Health Check Endpoint

```php
// routes/web.php
use Illuminate\Support\Facades\{DB, Redis};

Route::get('/health', function () {
    $checks = [
        'app' => 'ok',
        'database' => 'unknown',
        'redis' => 'unknown',
    ];

    // Database check
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'connected';
    } catch (\Exception $e) {
        $checks['database'] = 'error: ' . $e->getMessage();
    }

    // Redis check
    try {
        Redis::ping();
        $checks['redis'] = 'connected';
    } catch (\Exception $e) {
        $checks['redis'] = 'error: ' . $e->getMessage();
    }

    $status = in_array('error', array_map(fn($v) => str_starts_with($v, 'error') ? 'error' : 'ok', $checks))
        ? 503 : 200;

    return response()->json(array_merge(['status' => 'ok'], $checks), $status);
});
```

### 9. Testcontainers (PHP variantı)

```php
// Using docker-php-testcontainers or manual Docker
use Testcontainers\Container\GenericContainer;

class TestcontainerTest extends TestCase
{
    private GenericContainer $mysql;

    protected function setUp(): void
    {
        $this->mysql = (new GenericContainer('mysql:8.0'))
            ->withEnv('MYSQL_ROOT_PASSWORD', 'secret')
            ->withEnv('MYSQL_DATABASE', 'test')
            ->withExposedPort(3306);

        $this->mysql->start();

        config([
            'database.connections.testing.host' => $this->mysql->getHost(),
            'database.connections.testing.port' => $this->mysql->getMappedPort(3306),
        ]);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->mysql->stop();
        parent::tearDown();
    }

    public function test_real_mysql_query(): void
    {
        $result = DB::connection('testing')->select('SELECT VERSION() as v');
        $this->assertStringContainsString('8.0', $result[0]->v);
    }
}
```

### 10. Blue/Green Deploy Smoke Test

```bash
#!/bin/bash
# deploy.sh

# 1. Deploy to green environment
kubectl apply -f k8s/green.yaml

# 2. Wait for pods
kubectl rollout status deployment/app-green

# 3. Smoke test
SMOKE_URL="https://green.internal.example.com" \
  php artisan test --testsuite=Smoke

if [ $? -ne 0 ]; then
  echo "Smoke test FAILED, rolling back"
  kubectl delete -f k8s/green.yaml
  exit 1
fi

# 4. Switch traffic
kubectl patch service app -p '{"spec":{"selector":{"version":"green"}}}'

echo "Deploy successful"
```

## Interview Sualları (Q&A)

### S1: Environment parity niyə vacibdir?
**C:** Dev, staging və production arasında fərqlər bug-lar yaradır. "Works on my
machine" — dev-də SQLite, prod-da MySQL olduqda queries fərqli davrana bilər.
**12-Factor App** prinsipi: mühitləri mümkün qədər yaxın tutun. Docker bu parity-ni
təmin etməyə kömək edir.

### S2: Ephemeral environment nədir?
**C:** Hər **PR/branch** üçün avtomatik yaradılan müvəqqəti test mühitidir. PR
açıldıqda `pr-123.preview.example.com` yaradılır, merge olduqda silinir. QA,
dizayner və stakeholder-lər hər dəyişikliyi ayrıca test edə bilər.

### S3: Testcontainers nədir?
**C:** Test zamanı **Docker konteynerlərini proqramatik idarə edən** kitabxanadır.
Məs. testdən əvvəl real MySQL konteyner qaldırır, test sonunda silir. Bu, mock əvəzinə
**real xidmət** ilə test etməyə imkan verir — production-a daha yaxın.

### S4: `.env.testing` nə üçündür?
**C:** Yalnız test zamanı istifadə olunan konfiqurasiya üçün. PHPUnit `APP_ENV=testing`
ilə işlədikdə Laravel bu faylı yükləyir. Burada:
- SQLite in-memory DB
- Array cache/session
- Sync queue
- Fake external keys

### S5: Staging və production fərqi nədir?
**C:** **Staging** — production-un klonudur, **real traffic yoxdur**, QA burada test
edir, yeni feature-lər yoxlanır. **Production** — real istifadəçilərin girdiyi mühit.
Staging sayəsində production-da bug çıxma ehtimalı azalır.

### S6: Smoke test on deploy nə deməkdir?
**C:** Deploy-dan dərhal sonra işləyən **minimal testlər** — sistem işləyir?
```
GET /health → 200
Login endpoint → 200
Critical API → 200
```
Əgər uğursuz olsa, avtomatik **rollback** edilir. Blue/green deploy-da yeni versiyaya
traffic yönəltməzdən əvvəl işlədilir.

### S7: In-memory SQLite vs real MySQL testlərində?
**C:**
- **SQLite in-memory**: Sürətlidir, setup asandır, CI-da yaxşıdır
- **MySQL**: Production-a yaxındır, MySQL-specific feature-ləri test edir (JSON, fulltext)

**Tövsiyə**: Unit testlər — SQLite; Integration testlər — MySQL (Testcontainers).

### S8: Hər branch üçün ephemeral environment-in dəyəri varmı?
**C:** Bəli, amma qiymət də var:
- **+** Early feedback, QA izolasiyası, demo
- **-** Infrastructure xərci, setup mürəkkəbliyi

**Vercel/Netlify** frontend üçün ucuz və asandır. Backend üçün Kubernetes ilə
daha mürəkkəbdir, amma böyük komandalarda dəyər verir.

### S9: Test zamanı production DB-yə bağlanmamaq üçün necə qoruyursunuz?
**C:**
```php
protected function setUp(): void
{
    parent::setUp();
    if (str_contains(config('database.default'), 'prod')) {
        throw new \Exception('PROD DB-yə bağlanmaq qadağandır!');
    }
}
```
Həmçinin `phpunit.xml`-də `DB_CONNECTION=sqlite` hardcode edin və CI-da secret
manager-dən prod credentials istifadə etməyin.

### S10: Environment variable-ları test-lərdə necə idarə edirsiniz?
**C:**
- **phpunit.xml**-də default-lar
- **`.env.testing`**-də test-spesifik konfiq
- **CI secret**-lərdə həssas dəyərlər
- **Test içində**: `config(['key' => 'value'])` runtime override

Heç vaxt production secret-ləri test env-də istifadə etməyin.

## Best Practices / Anti-Patterns

### Best Practices
1. **Docker ilə parity** — dev, CI, prod eyni image
2. **`.env.testing`** — ayrıca test konfiq
3. **Ephemeral environments** — hər PR üçün
4. **Smoke test on deploy** — rollback avtomatik
5. **Testcontainers** — real servislərlə integration test
6. **Health endpoint** — `/health` Kubernetes/monitoring üçün
7. **CI secret management** — GitHub/GitLab secrets
8. **Env validation** — setup zamanı environment yoxla
9. **Blue/green deploy** — zero-downtime
10. **Feature flags** — env-specific davranış

### Anti-Patterns
- **Dev-də SQLite, prod-da MySQL** — parity yoxdur
- **Hardcoded credentials** — env variable əvəzinə
- **Testing in production** — real istifadəçilərlə
- **Shared staging** — bir developer digərinin datasını pozur
- **No smoke test** — deploy uğursuz olur, amma bilinmir
- **`.env` git-ə commit** — secret leak
- **Production seeder test-də** — yanlışlıqla prod-a data
- **Manual deploy** — avtomatlaşdırma yoxdur
- **No rollback strategy** — deploy pozularsa nə?
- **CI env və local env fərqli** — "CI-da işləmir" bug-ları

### Environment Checklist
- [ ] Docker ilə dev environment
- [ ] `.env.example` commit edilib, `.env` isə yox
- [ ] `.env.testing` ilə test konfiq
- [ ] CI pipeline-da eyni versiyalar (PHP, MySQL, Redis)
- [ ] Health endpoint mövcuddur
- [ ] Smoke testlər deploy-dan sonra işləyir
- [ ] Ephemeral environments PR üçün
- [ ] Secret management (Vault, GitHub Secrets)
- [ ] Rollback strategiyası mövcuddur
- [ ] Staging production-a oxşar
