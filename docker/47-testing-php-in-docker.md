# PHP/Laravel Test-lərini Docker-də İcra Etmək

> **Səviyyə (Level):** ⭐⭐ Middle
> **Oxu müddəti:** ~15-20 dəqiqə
> **Kateqoriya:** Docker / Testing

## Nədir? (What is it?)

"Mənim lokal-da test-lər keçir, CI-də niyə fail olur?" — bu klassik sualın cavabı sadədir: **dev, CI, prod eyni mühit deyil**. Testləri Docker-də icra etməklə bu problemi həll edirsən:

- **Eyni PHP versiyası** (8.3.14)
- **Eyni extension-lar** (pdo_mysql, redis, gd)
- **Eyni OS** (Alpine və ya Debian)
- **Eyni MySQL / Redis / Postgres versiyası** (Compose service-ləri ilə)
- **Eyni locale, timezone, file permissions**

Həm dev maşınında, həm CI pipeline-da, həm prod-a yaxın staging-də — tam eyni konteyner.

Bu fayl bunu necə qurmaq lazım olduğunu verir: ad-hoc run, dedicated test service, test DB, Xdebug vs PCOV coverage, paralel testing (Pest/paratest), CI inteqrasiyası (GitHub Actions), və tez-tez qarşılaşılan problemlər (file permissions, timezone, seed race).

## Əsas Konseptlər

### 1. İki Əsas Yanaşma

**Yanaşma A: `docker-compose run --rm` — Ad-hoc**

Mövcud `app` service-ində bir dəfəlik test run et:

```bash
docker compose run --rm app php artisan test
```

`--rm` → test bitəndən sonra konteyner silinir, disk doldurmur.

**Üstün:**
- Sadə, sürətli setup
- CI-də də eyni əmr
- Mövcud image istifadə olunur

**Zəif:**
- `app` service-i prod-a oxşardır, test üçün Xdebug/PCOV yoxdur
- Test DB ilə real DB qarışa bilər (unutursan `DB_DATABASE`-i dəyişməyə)

**Yanaşma B: Dedicated Test Service — Strukturlu**

Compose-də ayrı `test` service:

```yaml
services:
  app:
    build:
      context: .
      target: dev
    environment:
      - APP_ENV=local
      - DB_DATABASE=myapp
  
  test:
    build:
      context: .
      target: test   # Multi-stage test target (PCOV əlavə)
    environment:
      - APP_ENV=testing
      - DB_CONNECTION=mysql
      - DB_HOST=mysql_test
      - DB_DATABASE=myapp_test
      - CACHE_STORE=array
      - SESSION_DRIVER=array
      - QUEUE_CONNECTION=sync
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    depends_on:
      mysql_test:
        condition: service_healthy
    profiles: ["test"]
  
  mysql_test:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: myapp_test
    tmpfs:
      - /var/lib/mysql   # RAM-da — 10x sürətli
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 2s
      retries: 10
    profiles: ["test"]
```

İstifadə:
```bash
docker compose --profile test run --rm test php artisan test
```

`profiles: ["test"]` → normal `docker compose up` test service-lərini qaldırmır, yalnız `--profile test` ilə.

### 2. Test Database Strategiyaları

**Variant 1: Ayrı MySQL konteyneri + `tmpfs`**

```yaml
mysql_test:
  image: mysql:8.0
  environment:
    MYSQL_ROOT_PASSWORD: root
    MYSQL_DATABASE: myapp_test
  tmpfs:
    - /var/lib/mysql:size=1G   # RAM-da saxla, disk yox
  command: --default-authentication-plugin=mysql_native_password
```

`tmpfs` həmişəlik storage-dir — konteyner restart olsa data itir. Testlər üçün ideal (`migrate:fresh` onsuz da hər run sıfırdan qurur).

**Variant 2: SQLite in-memory**

`phpunit.xml`:
```xml
<phpunit>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
    </php>
</phpunit>
```

Laravel avtomatik SQLite in-memory işlədir. Heç MySQL konteyneri lazım deyil.

**Üstün:** Tez başlayır, izolyasiya mükəmməldir.
**Zəif:** MySQL-dən fərqlidir (JSON funksiyalar, FULLTEXT, strict mode, stored procedure). Prod MySQL-dirsə, MySQL-də də test et (minimum CI-də).

**Variant 3: Postgres `template` DB**

Postgres `CREATE DATABASE ... TEMPLATE` dəstəkləyir — təmiz schema-ı template kimi saxla, hər test suite sürətlə clone et:

```sql
-- Bir dəfə qur
CREATE DATABASE myapp_test_template;
-- Migrations-ı işlət
-- Sonra:
CREATE DATABASE myapp_test_1 TEMPLATE myapp_test_template;
```

Bu pattern paralel testing üçün əladır — hər test process-i öz DB-sini 50 ms-də yaradır (migrate yerinə).

### 3. Testcontainers Yanaşması

`testcontainers/testcontainers-php` — testlər başladıqca konteyner qaldırır, bitəndə silir. CI-də çox yaxşıdır — compose əvvəldən qalxmır, hər test suite özü qurur.

```php
// tests/Feature/DatabaseTest.php
use Testcontainers\Container\MySQLContainer;

class DatabaseTest extends TestCase
{
    private static MySQLContainer $mysql;

    public static function setUpBeforeClass(): void
    {
        self::$mysql = MySQLContainer::make('8.0')
            ->withMySQLDatabase('myapp_test')
            ->withMySQLUser('test', 'secret');
        
        self::$mysql->run();
        
        config([
            'database.connections.mysql.host' => self::$mysql->getHost(),
            'database.connections.mysql.port' => self::$mysql->getMappedPort(3306),
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::$mysql->stop();
    }

    public function test_user_can_be_created(): void
    {
        // ...
    }
}
```

**Üstün:** Hər testin öz izolyasiyası, CI-də compose lazım deyil.
**Zəif:** Yavaş (hər suite konteyner qaldırır), Docker-in-Docker tələb edir CI-də (`docker.sock` mount).

## Multi-Stage Dockerfile — Test Target

```dockerfile
# ============================================================
# Base — ümumi extension-lar
# ============================================================
FROM php:8.3-fpm-alpine AS base

ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/download/2.2.13/install-php-extensions \
    /usr/local/bin/

RUN install-php-extensions \
        bcmath exif gd intl opcache pcntl pdo_mysql pdo_pgsql redis zip

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ============================================================
# Dev — Xdebug əlavə
# ============================================================
FROM base AS dev

RUN install-php-extensions xdebug

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# ============================================================
# Test — PCOV (sürətli coverage), Xdebug YOX
# ============================================================
FROM base AS test

RUN install-php-extensions pcov

COPY docker/php/pcov.ini /usr/local/etc/php/conf.d/pcov.ini
COPY docker/php/php-test.ini /usr/local/etc/php/conf.d/zz-test.ini

# Composer dev paketləri də daxildir (phpunit, pest, mockery)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# ============================================================
# Production — minimal, coverage yoxdur
# ============================================================
FROM base AS production
# ... (35-ci faylda)
```

### `docker/php/pcov.ini`

```ini
extension=pcov.so
pcov.enabled=1
pcov.directory=/var/www/html/app
pcov.exclude="~vendor~"
```

### `docker/php/php-test.ini`

```ini
display_errors=On
error_reporting=E_ALL
memory_limit=512M
opcache.enable=0
date.timezone=UTC
```

OpCache test-də deaktiv — test failure-ların cache-lənmiş kod üzündən olmasın.

## Coverage: Xdebug vs PCOV

| Tool | Coverage sürəti | Prod istifadə | Step debug |
|------|-----------------|---------------|------------|
| **Xdebug** | Yavaş (2-5x slowdown) | Yox | Bəli |
| **PCOV** | Sürətli (~5-10% overhead) | Təhlükəsiz | Yox |

PCOV **yalnız coverage** edir, debug etmir. Test coverage üçün idealdır.

```bash
# Xdebug ilə (yavaş)
XDEBUG_MODE=coverage php artisan test --coverage

# PCOV ilə (sürətli)
php artisan test --coverage --min=80
```

Real rəqəmlər (Laravel layihə, 850 test):
- Coverage yoxdur: 28 saniyə
- PCOV ilə: 32 saniyə (+14%)
- Xdebug ilə: 180 saniyə (+540%)

CI-də PCOV — coverage həmişə var, slow deyil.

## Parallel Testing — Pest / paratest

Laravel 11 + Pest:
```bash
php artisan test --parallel --processes=4
```

Paratest (PHPUnit):
```bash
./vendor/bin/paratest --processes=4
```

Problem: hər process eyni DB-yə yazır → race condition, data qarışır.

**Həll 1: DB-per-process**

Laravel `ParallelTesting` helper:
```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\ParallelTesting;

public function boot(): void
{
    ParallelTesting::setUpProcess(function (int $token) {
        DB::statement("CREATE DATABASE IF NOT EXISTS myapp_test_{$token}");
    });

    ParallelTesting::tearDownProcess(function (int $token) {
        DB::statement("DROP DATABASE myapp_test_{$token}");
    });
}
```

`TEST_TOKEN` env var hər process üçün fərqli (`1`, `2`, `3`, `4`). `phpunit.xml`:
```xml
<env name="DB_DATABASE" value="myapp_test_${TEST_TOKEN}"/>
```

**Həll 2: Transactional**

`DatabaseTransactions` trait → hər test öz transaction-ında, sonda rollback. Parallel-də izolasiya yoxdur (eyni DB), amma SQLite in-memory ilə bu şəkildə də işləyir — hər process öz memory-si var.

## Compose Nümunəsi — Tam

```yaml
# docker-compose.yml
services:
  app:
    build:
      context: .
      target: dev
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - DB_DATABASE=myapp
    depends_on:
      mysql:
        condition: service_healthy
  
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: myapp
    volumes:
      - mysql-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
  
  # Test service — yalnız --profile test ilə qalxır
  test:
    build:
      context: .
      target: test
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
      - ./coverage:/var/www/html/coverage
      - ./build:/var/www/html/build
    environment:
      - APP_ENV=testing
      - DB_HOST=mysql_test
      - DB_DATABASE=myapp_test
      - DB_USERNAME=root
      - DB_PASSWORD=root
      - CACHE_STORE=array
      - SESSION_DRIVER=array
      - QUEUE_CONNECTION=sync
      - MAIL_MAILER=array
      - TZ=UTC
    depends_on:
      mysql_test:
        condition: service_healthy
      redis_test:
        condition: service_started
    profiles: ["test"]
  
  mysql_test:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: myapp_test
    tmpfs:
      - /var/lib/mysql:size=1G
    command:
      - --default-authentication-plugin=mysql_native_password
      - --innodb-flush-log-at-trx-commit=0   # Sürət üçün, crash safety vecsiz
      - --sync-binlog=0
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 2s
      retries: 15
    profiles: ["test"]
  
  redis_test:
    image: redis:7-alpine
    command: redis-server --save "" --appendonly no   # In-memory only
    profiles: ["test"]

volumes:
  mysql-data:
```

İstifadə:
```bash
# Bütün test suite
docker compose --profile test run --rm test php artisan test

# Yalnız dəyişən testlər
docker compose --profile test run --rm test php artisan test --filter=UserTest

# Coverage ilə
docker compose --profile test run --rm test php artisan test --coverage --min=80

# Parallel
docker compose --profile test run --rm test php artisan test --parallel --processes=4

# Pest
docker compose --profile test run --rm test ./vendor/bin/pest

# Pest parallel
docker compose --profile test run --rm test ./vendor/bin/pest --parallel --processes=4
```

## CI Integration — GitHub Actions

### Variant 1: `services:` — Built-in Docker

```yaml
# .github/workflows/test.yml
name: Test

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: myapp_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h localhost"
          --health-interval=5s
          --health-timeout=5s
          --health-retries=10
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v4
      
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: bcmath, gd, intl, pdo_mysql, redis, zip
          coverage: pcov
      
      - name: Cache composer
        uses: actions/cache@v4
        with:
          path: ~/.composer/cache
          key: composer-${{ hashFiles('composer.lock') }}
      
      - run: composer install --no-interaction --prefer-dist
      
      - run: cp .env.ci .env
      
      - run: php artisan key:generate
      
      - run: php artisan migrate --force
        env:
          DB_HOST: 127.0.0.1
      
      - run: php artisan test --coverage --min=80
        env:
          DB_HOST: 127.0.0.1
          REDIS_HOST: 127.0.0.1
      
      - uses: actions/upload-artifact@v4
        with:
          name: coverage
          path: coverage/
```

### Variant 2: Eyni Docker Image CI-də

CI-də **eyni image** istifadə et:

```yaml
jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Build test image
        run: docker build --target=test -t myapp:test .
      
      - name: Start services
        run: docker compose --profile test up -d mysql_test redis_test
      
      - name: Wait for DB
        run: |
          for i in {1..30}; do
            docker compose exec -T mysql_test mysqladmin ping -h localhost && break
            sleep 1
          done
      
      - name: Run tests
        run: |
          docker compose --profile test run --rm \
            -e CI=true \
            test php artisan test --coverage --min=80
      
      - name: Copy coverage
        if: always()
        run: docker compose --profile test cp test:/var/www/html/coverage ./coverage
      
      - uses: actions/upload-artifact@v4
        if: always()
        with:
          name: coverage
          path: coverage/
```

**Üstün:** CI mühiti **dəqiq** lokaldakı kimidir.
**Zəif:** Build 1-2 dəqiqə əlavə. BuildKit cache ilə azaldılır.

## Sürət Tips — Test 180s → 20s

1. **`tmpfs` MySQL volume** — diskə yazmır, RAM-da. 3-5x sürət.
2. **`QUEUE_CONNECTION=sync`** — queue worker lazım deyil.
3. **`CACHE_STORE=array`** — Redis lazım deyil test unit üçün (feature test-lərdə saxla).
4. **`MAIL_MAILER=array`** — real mail göndərməz.
5. **`innodb-flush-log-at-trx-commit=0`** — MySQL fsync etmir hər commit.
6. **PCOV ilə coverage** — Xdebug-dan 10x sürətli.
7. **`php artisan test --testdox`** — yavaş test-ləri görürsən.
8. **`php artisan test --filter`** — dəyişiklik üzərində test-lər.
9. **`php artisan test --parallel --processes=4`** — 4x CPU.
10. **`RefreshDatabase` əvəzinə `DatabaseTransactions`** — `migrate:fresh` yox, sadəcə rollback.

## `.env.testing` və ya `phpunit.xml`

Laravel `.env.testing`-i oxuyur `APP_ENV=testing` olanda:

```env
# .env.testing
APP_ENV=testing
APP_KEY=base64:test-key-32-bytes-fixed-value==
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=mysql_test
DB_PORT=3306
DB_DATABASE=myapp_test
DB_USERNAME=root
DB_PASSWORD=root

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array

TZ=UTC
```

Və ya `phpunit.xml`-də direct:

```xml
<phpunit>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_DATABASE" value="myapp_test"/>
        <env name="CACHE_STORE" value="array"/>
    </php>
</phpunit>
```

## Tələlər (Gotchas)

### 1. `coverage/` qovluğu root-owned

Container `root` kimi yazır, sonra lokal-da `rm coverage/` permission denied.

**Həll:** Container-də user match:
```yaml
test:
  user: "${UID:-1000}:${GID:-1000}"
```

Və ya bind mount əvəzinə `docker cp` ilə artifact çıxar.

### 2. Test DB-si dev DB-sini yazır

`.env.testing` yüklənmir — `APP_ENV=local` qalır, `DB_DATABASE=myapp` istifadə olunur. **Production-da bu katastrofadır**.

**Həll:** `phpunit.xml`-də env bloku (yuxarıdakı). Container-də `APP_ENV=testing` environment var.

### 3. Timezone CI-də fərqli

Lokal-da UTC, CI-də UTC, amma image default-u Europe/Moscow → tarix test-ləri fail.

**Həll:**
```dockerfile
RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/UTC /etc/localtime \
    && echo "UTC" > /etc/timezone
```

Və `php.ini`:
```ini
date.timezone=UTC
```

### 4. Locale CI-də fərqli

`strtolower('İSTANBUL')` lokal-da `"i̇stanbul"` (Türk), CI-də `"istanbul"` (C).

**Həll:** `LC_ALL=C.UTF-8` set et environment-də:
```yaml
test:
  environment:
    - LC_ALL=C.UTF-8
    - LANG=C.UTF-8
```

### 5. Paralel testlərdə seeder race

İki process eyni anda `users` cədvəlinə yazır, duplicate email.

**Həll:** `ParallelTesting::setUpProcess` ilə hər process üçün ayrı DB (yuxarıda nümunə).

### 6. Test DB-si seed olunmur

`RefreshDatabase` trait `migrate:fresh` edir amma `db:seed` etmir.

**Həll:**
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;
    
    protected $seed = true;   // Laravel 8+
    protected $seeder = DatabaseSeeder::class;
}
```

### 7. Redis key-ləri test-lər arasında qalır

`CACHE_STORE=redis` testdə, bir test key yazdı, digəri oxudu — flaky.

**Həll:** Test başında `Redis::flushdb()` və ya `CACHE_STORE=array` (Redis testə lazım deyilsə).

### 8. `storage/logs/laravel.log` yazma icazəsi yoxdur

Container-də `www-data`, bind mount-da lokal user. Log yazıla bilmir.

**Həll:**
```yaml
test:
  user: "${UID}:${GID}"
volumes:
  - ./storage/logs:/var/www/html/storage/logs
```

Və ya log-u `php://stderr`-ə yaz:
```ini
LOG_CHANNEL=stderr
```

### 9. BuildKit cache CI-də istifadə olunmur

Hər CI run composer install sıfırdan — 2 dəqiqə.

**Həll:**
```yaml
- uses: docker/setup-buildx-action@v3
- uses: docker/build-push-action@v5
  with:
    target: test
    tags: myapp:test
    cache-from: type=gha
    cache-to: type=gha,mode=max
```

### 10. Test image prod image-dən fərqlidir

Dev-də PCOV yoxdur, test-də var — test keçir amma prod-da başqa davranış.

**Həll:** Test image-i prod base-dən qur (`FROM production AS test`), yalnız PCOV əlavə et. Yəni eyni OS, eyni extension, eyni composer versiyası.

## Müsahibə Sualları

- **Q:** Niyə testləri Docker-də icra edirsiz?
  - Dev / CI / staging arasında eyni mühit. PHP versiyası, extension-lar, MySQL versiyası — hamısı eyni. "Mənim maşında işləyir" problemi aradan qalxır. Test failure həqiqi bug-dır, environment drift deyil.

- **Q:** Xdebug və PCOV coverage üçün fərqi?
  - Xdebug 2-5x yavaşladır, debug üçün əsasən. PCOV yalnız coverage edir — ~10% overhead. CI-də PCOV istifadə et. Lokal debug üçün Xdebug.

- **Q:** Test DB-sini necə təşkil edirsiz?
  - **Üç variant:** (1) Ayrı MySQL konteyneri `tmpfs`-lə (RAM-da) — prod-a ən yaxın; (2) SQLite in-memory — ən sürətli, amma MySQL-dən fərqli; (3) Postgres template database. Mən adətən CI-də MySQL, lokal dev-də SQLite seçirəm.

- **Q:** Paralel test-lərdə DB-ni necə izolyasiya edirsiz?
  - Laravel `ParallelTesting::setUpProcess` ilə hər process üçün ayrı DB (`myapp_test_1`, `myapp_test_2`...). `TEST_TOKEN` env var ilə hansı DB. Və ya SQLite in-memory — hər process öz memory-sidir.

- **Q:** Coverage output-u CI-də artifact kimi necə çıxarırsız?
  - Bind mount `./coverage:/var/www/html/coverage`, test bitəndən sonra `actions/upload-artifact` ilə upload. Permission problem: container user-i host user ilə match etmək lazımdır (`user: "${UID}:${GID}"`).

- **Q:** Testcontainers nədir, nə üçün istifadə edərdiniz?
  - Runtime-da konteyner başladıb test bitəndə silmək. Fayda: compose əvvəldən qalxmır, hər test öz izolyasiyasını yaradır. Zərər: yavaş (hər suite konteyner qaldırır), Docker-in-Docker lazımdır CI-də.

- **Q:** Test 5 dəqiqə çəkir. Necə sürətləndirərsiniz?
  - Parallel (`--parallel --processes=4`), `tmpfs` MySQL volume, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, PCOV coverage (Xdebug yox), MySQL `innodb-flush-log-at-trx-commit=0`, `--filter` ilə yalnız dəyişən testlər. Adətən 180s → 20s.

- **Q:** Lokal keçir, CI-də fail — nəyi yoxlayarsınız?
  - Timezone (`date.timezone`), locale (`LC_ALL`), PHP versiyası, extension versiyası (Redis 5 vs 6), MySQL versiyası (5.7 vs 8.0 utf8mb4 default), file permissions, `.env.testing` yüklənirmi, random seed. CI-də `php --version && php -m && mysql --version` print et — fərqi tut.

- **Q:** Pest `--parallel` ilə DB isolation necə?
  - Eyni mexanizm — Laravel `ParallelTesting::setUpProcess`. Pest Laravel trait-lərinə sərbəst mindir. Çağırış eynidir.

- **Q:** Prod image-dən test image-i necə qurursunuz?
  - Multi-stage: `FROM base AS test` — prod base + PCOV + dev composer paketlər. Eyni OS, eyni extension versiyası, yalnız test üçün əlavələr. Test image CI-də qurulur, prod image-lə eyni foundation-dan.
