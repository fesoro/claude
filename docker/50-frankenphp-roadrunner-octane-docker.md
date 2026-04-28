# FrankenPHP, RoadRunner, Swoole & Laravel Octane in Docker

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

Ənənəvi PHP modelini (request-per-process, boot hər dəfə) əvəzləyən **application server-lər**. Hər request-də Laravel framework-u yenidən boot etmək əvəzinə, app yaddaşda qalır və request-lər arasında paylaşılır.

Nəticə: **5-10x daha çox throughput**. Benchmark-larda Laravel Octane (Swoole ilə) standart PHP-FPM-dən:
- 2500 → 18000+ req/sec
- p99 latency: 120ms → 15ms

Əsas seçimlər:
- **Laravel Octane** — Laravel-in rəsmi paketi, Swoole və ya RoadRunner ilə işləyir
- **FrankenPHP** — Caddy + PHP embed, en yeni (2023+), sadə setup
- **RoadRunner** — Go-da yazılmış, gRPC/workers üçün yaxşı
- **Swoole** — ən məşhur PHP extension, Octane default

Bu fayl hər birini Docker-də necə qurmağı göstərir.

## Niyə Application Server?

### Standart PHP-FPM lifecycle

```
Request 1:
  → Laravel boot (kernel, container, routes, config)
  → Controller action
  → Response
  → Konteyner sıfırlanır
Request 2:
  → Laravel boot (hər şey yenidən!)
  ...
```

Hər request-də ~30-50ms framework boot vaxtı.

### Application Server lifecycle

```
Boot (bir dəfə):
  → Laravel boot
Request 1 → Controller → Response (framework cache-də)
Request 2 → Controller → Response (framework cache-də)
...
```

Hər request-dən "boot" overhead-i silinir.

## Tradeoff-lar — Worker Mode Riskləri

**Sabitlik tələbləri:**
- Static property-lər request-lər arasında paylaşılır → memory leak
- Singleton-lar state saxlayır → "niyə bir istifadəçi o birinin məlumatını gördü?"
- DB connection leak → Too many connections
- Eloquent model cache → yaddaş böyüyür

**Laravel Octane bunları həll edir:**
- Hər request-də `RequestReceived` event yaddaşı təmizləyir
- Konfiqurasiya edilə bilən `listen` callback-lər
- Auto-restart on memory threshold

## Laravel Octane

### Setup (Swoole ilə)

```bash
composer require laravel/octane
php artisan octane:install --server=swoole
```

### Dockerfile

```dockerfile
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        tini \
        libzip-dev libpng-dev icu-dev oniguruma-dev \
        linux-headers \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS autoconf gcc g++ make openssl-dev \
    && docker-php-ext-install bcmath gd intl opcache pcntl pdo_mysql zip \
    && pecl install swoole \
    && docker-php-ext-enable swoole \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /var/www/html

# Composer install
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

COPY . .
RUN php artisan view:cache route:cache event:cache

USER www-data

EXPOSE 8000

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000", "--workers=4", "--task-workers=2"]
```

### Compose

```yaml
services:
  app:
    build: .
    image: myapp:octane
    ports:
      - "8000:8000"
    environment:
      - APP_ENV=production
      - OCTANE_SERVER=swoole
    env_file:
      - .env
    depends_on:
      - mysql
      - redis
```

### Worker Count

```
--workers=4          # Request handler workers (CPU core count)
--task-workers=2     # Background task-lar üçün
--max-requests=500   # Worker 500 request-dən sonra restart (memory leak qoruma)
```

`max-requests` vacibdir — memory leak-lər zamanla böyüyür, periyodik restart təmiz başlayır.

### State Management

Octane yaddaş state-i təmizləmək üçün callback-lər verir:

```php
// config/octane.php
'warm' => [
    ...Octane::defaultServicesToWarm(),
],

'flush' => [
    //
],

'listeners' => [
    RequestReceived::class => [
        // Hər request-dən öncə işləyir
    ],
    RequestHandled::class => [
        // Hər request-dən sonra
        FlushTemporaryContainerInstances::class,
    ],
    WorkerStarting::class => [
        // Worker başlanğıcda
        EnsureUploadedFilesAreValid::class,
        EnsureUploadedFilesCanBeMoved::class,
    ],
    WorkerStopping::class => [
        //
    ],
],
```

### Şübhəli Kod Nümunələri

**YANLIŞ — state worker-lar arasında paylaşılır:**
```php
class UserService {
    private array $cache = [];    // static yaddaş!
    
    public function find(int $id): User {
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = User::find($id);
        }
        return $this->cache[$id];
    }
}
```

İlk request-də user #5 cache-lənir. İkinci request başqa user-dən #5 istəyir → eyni obyekti alır, amma ikinci request fərqli sessiondadır. **Data leak.**

**DÜZGÜN — hər request üçün yeni instance:**
```php
// app/Providers/AppServiceProvider.php
$this->app->scoped(UserService::class);    // 'singleton' yox, 'scoped'
```

`scoped` — hər request üçün yeni instance, request bitəndən sonra destroy.

## FrankenPHP — En Yeni və Ən Sadə

### Nədir?

Caddy web server + PHP embedded — tək binary, tək process. HTTP/3, automatic HTTPS, worker mode.

### Dockerfile

```dockerfile
FROM dunglas/frankenphp:1-php8.3-alpine

# PHP extension-lar
RUN install-php-extensions \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        zip

WORKDIR /app

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

COPY . .
RUN php artisan view:cache route:cache event:cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Caddy config
COPY docker/Caddyfile /etc/caddy/Caddyfile

EXPOSE 80 443 443/udp

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
```

### `Caddyfile`

```
{
    frankenphp {
        # Worker mode — Laravel-i əzbərdə saxla
        worker ./public/frankenphp-worker.php 4
    }
}

:80 {
    root * /app/public
    encode gzip
    php_server
    file_server
}
```

### Worker Script — `public/frankenphp-worker.php`

```php
<?php
// public/frankenphp-worker.php
ignore_user_abort(true);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$handler = function () use (&$running, $kernel) {
    $request = Illuminate\Http\Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
};

$maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? 500);

for ($i = 0; $i < $maxRequests; ++$i) {
    $keep = \frankenphp_handle_request($handler);
    gc_collect_cycles();
    
    if (!$keep) {
        break;
    }
}
```

### Laravel Octane ilə FrankenPHP

Octane 2.0+ FrankenPHP-ni dəstəkləyir:

```bash
composer require laravel/octane
php artisan octane:install --server=frankenphp
```

```dockerfile
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
```

Tək image — web server + PHP bir yerdə. Ayrı nginx/php-fpm lazım deyil.

### HTTPS Auto

FrankenPHP üçün Caddy avtomatik Let's Encrypt sertifikat alır:

```
my-app.example.com {
    root * /app/public
    php_server
}
```

DNS point et, `docker run` et — HTTPS avtomatik işləyir.

## RoadRunner

### Nədir?

Go-da yazılmış PHP application server. Worker-lar PHP process-lər, əsas server Go-dur. gRPC, Kafka, WebSocket üçün güclü.

### Setup

```bash
composer require spiral/roadrunner-laravel
php artisan octane:install --server=roadrunner
./vendor/bin/rr get          # RoadRunner binary-ni yüklə
```

### Dockerfile

```dockerfile
FROM php:8.3-cli-alpine

RUN apk add --no-cache tini \
    && docker-php-ext-install bcmath opcache pcntl pdo_mysql

# RoadRunner binary
COPY --from=ghcr.io/roadrunner-server/roadrunner:2024 /usr/bin/rr /usr/local/bin/rr

WORKDIR /app

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

COPY . .
COPY .rr.yaml .

EXPOSE 8000

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["rr", "serve", "-c", ".rr.yaml"]
```

### `.rr.yaml`

```yaml
version: "3"

server:
  command: "php artisan octane:start --server=roadrunner"

http:
  address: "0.0.0.0:8000"
  middleware: ["static", "gzip"]
  static:
    dir: "public"
    forbid: [".php", ".htaccess"]
  pool:
    num_workers: 4
    max_jobs: 500
    allocate_timeout: 60s
    destroy_timeout: 60s

metrics:
  address: "0.0.0.0:2112"
```

### RoadRunner-un Üstünlükləri

- Go-da yazılıb → sürətli HTTP layer
- Build-in metrics (Prometheus /metrics)
- Worker supervisor (auto-restart crashed workers)
- Queue, Kafka, gRPC native support

### Dezavantaj

- Swoole/FrankenPHP-dən az populyar
- Ayrı binary (Go) saxlanılmalıdır
- FrankenPHP artıq əksər feature-ları verir

## Swoole — Klassik Seçim

### Direct Swoole (Octane-siz)

Swoole özü də web server ola bilər, amma Octane wrap edir.

### Laravel Octane + Swoole

```bash
composer require laravel/octane
php artisan octane:install --server=swoole
php artisan octane:start --server=swoole
```

### Dockerfile

Yuxarıda Octane Swoole nümunəsinə bax.

### Swoole-un Unikal Xüsusiyyətləri

```php
use Laravel\Octane\Facades\Octane;

// Parallel execution (Swoole only)
[$users, $posts, $comments] = Octane::concurrently([
    fn () => User::all(),
    fn () => Post::all(),
    fn () => Comment::all(),
]);

// 3 query paralel işləyir — total vaxt = ən yavaşının vaxtı
```

```php
// Interval tasks
Octane::tick('simple-ticker', function () {
    info('Every 10 seconds');
})->seconds(10);
```

## Performance Müqayisəsi (Benchmark)

[wrk](https://github.com/wg/wrk) ilə 4-core maşın, sadə `/` endpoint, 100 concurrent:

| Server | Req/sec | p50 (ms) | p99 (ms) | Memory |
|--------|---------|----------|----------|---------|
| PHP-FPM + Nginx | 2,500 | 15 | 120 | 150 MB |
| Octane + Swoole | 18,000 | 2 | 15 | 300 MB |
| Octane + RoadRunner | 15,000 | 3 | 20 | 280 MB |
| FrankenPHP (worker) | 16,500 | 2 | 18 | 250 MB |

**Qeyd:** Real trafik N+1 query-li controller-lərdə fərq azalır — bottleneck DB olur. İdeal sadə endpoint-lərdə fərq maksimumdur.

## Production Concerns

### Memory Leak Detection

```php
// config/octane.php
'max_execution_time' => 30,        // Request max vaxt
'garbage' => 50,                   // MB, request-dən sonra
'max_requests' => 500,             // Worker restart threshold
```

Monitoring:
```php
WorkerStopping::class => [
    LogMemoryUsage::class,         // custom listener
],
```

### DB Connection Pooling

Swoole-də:
```php
// config/database.php
'mysql' => [
    'pool' => [
        'min_active' => 2,
        'max_active' => 10,
        'max_wait_time' => 5,
        'max_idle_time' => 30,
    ],
],
```

Octane persistent connections istifadə edir — her request yeni connection açmır.

### Horizontal Scaling

Worker mode app-lər **stateless** olmalıdır — session, cache Redis-də. Bir worker-in yaddaşında data saxlasan, load balancer başqa worker-ə göndərəcək.

```yaml
# K8s
spec:
  replicas: 3
  template:
    spec:
      containers:
      - name: app
        image: myapp:octane
        ports:
        - containerPort: 8000
        env:
        - name: SESSION_DRIVER
          value: redis
        - name: CACHE_DRIVER
          value: redis
        resources:
          requests:
            memory: 512Mi
            cpu: 500m
          limits:
            memory: 1Gi
            cpu: 2000m
```

### Health Check

```php
// routes/web.php
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'memory' => memory_get_usage(true),
    'peak_memory' => memory_get_peak_usage(true),
]));
```

```yaml
livenessProbe:
  httpGet:
    path: /health
    port: 8000
```

### Deployment — Zero-Downtime

Octane worker-lar yaddaşda code cache edir. Deploy zamanı yeni kod dəyişikliklərini görmür. Həll:

1. **Blue-green** — yeni pod-lar, köhnə pod-ları öldür
2. **`octane:reload`** — worker-ları graceful restart et (preferred):
```bash
docker compose exec app php artisan octane:reload
```

Docker Compose-də:
```yaml
services:
  app:
    image: myapp:${VERSION}
    deploy:
      update_config:
        order: start-first        # yeni başlasın, sonra köhnə öldürülsün
        parallelism: 1
        delay: 10s
```

## FrankenPHP və ya Octane+Swoole?

### FrankenPHP seç, əgər:
- Yeni layihə
- HTTP/3, automatic HTTPS lazımdır
- Ayrı Nginx/Caddy istəmirsən
- Sadə setup (tək image, tək process)

### Octane + Swoole seç, əgər:
- Parallel task-lar (`Octane::concurrently`) istifadə edirsən
- Interval/tick tasks lazımdır
- Komanda Swoole ilə tanışdır
- Legacy Octane setup var

### RoadRunner seç, əgər:
- gRPC, Kafka, WebSocket native lazım
- Go-based infrastructure
- Prometheus metrics out-of-box

## Tipik Səhvlər (Gotchas)

### 1. Singleton state leak

Singleton binding-lər worker-lar arasında state saxlayır.

**Həll:** `$this->app->scoped(...)`, Octane `listeners`-də flush.

### 2. Database connection drop

Long-running worker-də connection 8 saat idle qalsa MySQL drop edir.

**Həll:** Config-də `'pool'`, və ya `DB::reconnect()` periodic.

### 3. Memory yaxınlaşır, worker yavaşlayır

**Həll:** `--max-requests=500`. `memory_get_usage()` monitor et.

### 4. File upload worker-ı crash edir

Swoole default-da multipart/form-data-nı farklı idarə edir.

**Həll:** Octane `EnsureUploadedFilesAreValid` listener-i aktiv et.

### 5. Laravel Scout/Broadcasting paket-ləri

Bəzi paketlər Octane ilə incompatible (static state). 

**Həll:** Octane docs-da `incompatible packages` list-inə bax.

### 6. `header()` / `echo` birbaşa istifadə

```php
echo "debug";    // stdout-a yazır, response pozulur
header("X: Y"); // bütün future response-lara əlavə olunur!
```

**Həll:** Həmişə Laravel `response()` istifadə et.

### 7. Dev-də auto-reload yoxdur

Kod dəyişəndə worker görmür.

**Həll:** Watch mode:
```bash
php artisan octane:start --watch
```

FrankenPHP-də `config/octane.php`:
```php
'watch' => [
    'app',
    'bootstrap',
    'config/**/*.php',
    'database/**/*.php',
    'public/**/*.php',
    'resources/**/*.php',
    'routes',
    '.env',
],
```

## Interview sualları

- **Q:** Octane niyə PHP-FPM-dən sürətlidir?
  - Framework boot yalnız bir dəfə (worker start-da). Sonrakı request-lər yaddaşdakı app instance-i istifadə edir — hər request-dən 30-50ms boot overhead-i silinir.

- **Q:** Worker mode-da hansı risk-lər var?
  - Singleton/static state request-lər arasında paylaşılır — data leak. DB connection leak. Memory leak zamanla böyüyür. Həll: `scoped()`, `max_requests`, flush listeners.

- **Q:** FrankenPHP vs Octane+Swoole?
  - FrankenPHP: tək binary (Caddy + PHP), HTTPS auto, HTTP/3, yeni tətbiqlər üçün. Octane+Swoole: parallel execution, tick-tasks, daha geniş community.

- **Q:** `max_requests` niyə?
  - Worker periyodik restart ilə memory leak-dən qoruyur. 500 request-dən sonra işləsə, yaddaş 100 MB → 500 MB böyüyə bilər. Restart → təmiz başlanğıc.

- **Q:** Octane-də `Octane::concurrently()` necə işləyir?
  - Swoole-un coroutine-lərindən istifadə edir. 3 query paralel icra olunur, ümumi vaxt ən yavaşınınki qədərdir. DB/API yükü artır amma latency azalır.

- **Q:** Deploy zamanı code dəyişiklikləri necə fetch olunur?
  - Worker-lar yaddaşdakı kod cache istifadə edir. `php artisan octane:reload` ilə graceful restart, və ya yeni pod-lar (K8s rollout) — yeni kod oxunur.


## Əlaqəli Mövzular

- [php-laravel-production-dockerfile.md](36-php-laravel-production-dockerfile.md) — Production Dockerfile
- [php-fpm-tuning-docker.md](37-php-fpm-tuning-docker.md) — PHP-FPM tuning müqayisəsi
- [nginx-php-fpm-container-setup.md](38-nginx-php-fpm-container-setup.md) — Nginx arxitekturası
- [resource-limits-sizing-php.md](48-resource-limits-sizing-php.md) — Worker mode resource sizing
