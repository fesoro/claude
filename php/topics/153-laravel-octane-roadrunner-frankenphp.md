# Laravel Octane, RoadRunner, FrankenPHP (Senior)

## Mündəricat
1. [Problem: Niyə long-running PHP?](#problem-niyə-long-running-php)
2. [Three way müqayisə](#three-way-müqayisə)
3. [Laravel Octane + Swoole](#laravel-octane--swoole)
4. [RoadRunner](#roadrunner)
5. [FrankenPHP](#frankenphp)
6. [Memory leaks və state problemləri](#memory-leaks-və-state-problemləri)
7. [Singleton və DI container traps](#singleton-və-di-container-traps)
8. [Production deploy](#production-deploy)
9. [Performance benchmarks](#performance-benchmarks)
10. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Niyə long-running PHP?

```
Klassik PHP (PHP-FPM):
  Request gəldi → PHP prosesi başladı → autoload → framework boot →
  route match → controller → response → proses ÖLDÜ → state itdi.

  Hər request üçün:
    - Composer autoload: 10-30ms
    - Laravel bootstrap: 30-100ms
    - Config, route, view cache yüklənməsi
    - DB connection qurma (PDO::connect)

  1000 req/s üçün:
    → 1000 × 50ms = 50 saniyəlik CPU yalnız bootstrap üçün

Long-running (Octane/RR/FrankenPHP):
  Worker proses bir dəfə boot edilir — sonra request cycles davam edir.
  
  Worker lifecycle:
    1. Boot (bir dəfə): autoload + framework init
    2. Loop:
       - Request qəbul et
       - Handler işlət
       - Response qaytar
       - Request scope clean up
    3. Max request sayı dolanda proses restart (memory leak qorunma)

  Fayda:
    - Bootstrap cost yox
    - DB connection pool
    - Cache keepalive (Redis)
    - 2-10× TPS artımı
  
  Qiymət:
    - Global state qorunmur (memory leak riski)
    - Request-arası "qalıq" (stale singleton state)
    - Debugging çətin
```

---

## Three way müqayisə

```
Xüsusiyyət           PHP-FPM     Swoole/Octane    RoadRunner     FrankenPHP
──────────────────────────────────────────────────────────────────────────
Dil (worker)         PHP         PHP              Go             Go/PHP
HTTP/2               Nginx       No               No             Yes
HTTP/3               No          No               No             Yes
WebSocket            No          Yes              Yes            Yes
gRPC                 No          Yes              Yes            Yes
Memory shared        No          Yes (ext)        No             Yes
Static files         Nginx       No               Nginx          Yes (built-in)
Extension PHP        Yes         Yes              No             Yes
Docker size          Orta        Böyük            Orta           Kiçik
Hot reload           -           -                Yes            Yes
Laravel official     Yes         Yes (Octane)     Yes (Octane)   Yes (community)
Windows dəstəyi      Yes         No               Yes            Yes

Sürət reytingi (Laravel hello-world):
  PHP-FPM:      ~500 req/s
  RoadRunner:   ~3000 req/s  (6×)
  Swoole:       ~5000 req/s  (10×)
  FrankenPHP:   ~4500 req/s  (9×)
```

---

## Laravel Octane + Swoole

```bash
# Quraşdırma
composer require laravel/octane
php artisan octane:install --server=swoole

# Swoole extension
pecl install swoole
# ya da Docker: php:8.3-cli + pecl install swoole

# Start
php artisan octane:start --server=swoole --workers=4 --task-workers=2 --max-requests=500
```

```php
<?php
// config/octane.php — əsas settings
return [
    'server' => 'swoole',
    
    // Hər N request-dən sonra worker restart
    // Memory leak qorunması üçün kritikdir
    'max_requests' => 500,
    
    // Warm-up — cold start overhead azaltmaq
    'warm' => [
        ...Octane::defaultServicesToWarm(),
    ],
    
    // Flush qaydaları — request-lərarası state təmizliyi
    'flush' => [
        // Bu singleton-lar hər request-dən sonra reset olunur
    ],
    
    // Listener — manual cleanup
    'listeners' => [
        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
        ],
        RequestHandled::class => [
            // Custom cleanup
        ],
        WorkerStopping::class => [
            CloseLogChannels::class,
        ],
    ],
    
    // Concurrent tasks (Swoole-specific)
    // Bir request içində paralel Go-routine kimi
    'tables' => [
        'cache' => [
            'size' => 1000,
            'columns' => [
                ['name' => 'key',   'type' => 'string', 'size' => 100],
                ['name' => 'value', 'type' => 'string', 'size' => 1000],
            ],
        ],
    ],
];
```

```php
<?php
// Swoole concurrent tasks — bir request daxilində paralel
use Laravel\Octane\Facades\Octane;

[$users, $orders, $analytics] = Octane::concurrently([
    fn() => User::all(),                // DB query 1
    fn() => Order::pending()->get(),    // DB query 2
    fn() => Analytics::lastHour(),      // DB query 3
], waitMilliseconds: 500);

// 3 query paralel — toplam latency = max(q1, q2, q3), cəmi yox.
```

---

## RoadRunner

```bash
# Quraşdırma
composer require spiral/roadrunner laravel/octane
php artisan octane:install --server=roadrunner

# rr binary endir (Go ilə yazılıb)
./vendor/bin/rr get-binary

# Start
php artisan octane:start --server=roadrunner --workers=4
# ya da:
./rr serve -c .rr.yaml
```

```yaml
# .rr.yaml — RoadRunner config
version: "3"

server:
  command: "php artisan octane:start --server=roadrunner"
  relay: pipes

http:
  address: "0.0.0.0:8000"
  middleware: ["gzip", "headers", "static"]
  
  # Static file serving
  static:
    dir: "public"
    forbid: [".php", ".htaccess"]
  
  pool:
    num_workers: 4
    max_jobs: 500      # max_requests ekvivalenti
    allocate_timeout: 60s
    destroy_timeout: 60s

# Supervisor — auto-restart
reload:
  interval: 1s
  patterns: [".php"]
  services:
    http:
      recursive: true
      dirs: ["app", "routes", "config"]

metrics:
  address: "0.0.0.0:2112"   # Prometheus scrape

# Background jobs (built-in!)
jobs:
  pool:
    num_workers: 2
  pipelines:
    default:
      driver: memory
      priority: 10
```

```
RoadRunner üstünlükləri:
  ✓ Go worker manager — çox stabil
  ✓ PHP extension lazım deyil (PHP standard binary)
  ✓ Built-in static file serving
  ✓ Built-in job queue (Redis, Kafka, AMQP driver)
  ✓ Prometheus metrics built-in
  ✓ gRPC server built-in
  ✓ Hot reload (dev mode)

Çatışmazlıq:
  ✗ Swoole/ReactPHP kimi koroutine yox (worker-per-request)
  ✗ Laravel Broadcast / WebSocket zəif dəstəklənir
```

---

## FrankenPHP

```bash
# Docker image
docker run -v $PWD:/app -p 80:80 -p 443:443 \
    dunglas/frankenphp

# Ya da binary
curl https://frankenphp.dev/install.sh | sh
./frankenphp php-server --listen :8000 -r "public"
```

```dockerfile
# Dockerfile (production-ready)
FROM dunglas/frankenphp:latest AS base

RUN install-php-extensions \
    pdo_mysql \
    redis \
    opcache \
    intl \
    zip

COPY . /app
WORKDIR /app

RUN composer install --no-dev --optimize-autoloader \
 && php artisan config:cache \
 && php artisan route:cache \
 && php artisan view:cache

# HTTP/3 + automatic HTTPS (Caddy üzərində)
ENV SERVER_NAME=":443"
ENV CADDY_EXTRA_CONFIG="auto_https disable_redirects"

# Worker mode
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
```

```
FrankenPHP üstünlükləri:
  ✓ Caddy əsaslı — auto HTTPS (Let's Encrypt)
  ✓ HTTP/2, HTTP/3 built-in
  ✓ Static fayl serving built-in (Nginx lazım deyil)
  ✓ Worker mode: Laravel Octane kimi long-running
  ✓ "Classic mode" — PHP-FPM kimi per-request (hər ikisi mümkün!)
  ✓ PHP as library (C binding)
  ✓ Standalone binary — "single binary" deploy

Çatışmazlıq:
  ✗ Daha yenidir (2023+), production track-record qısa
  ✗ Windows dəstəyi məhduddur
  ✗ Swoole extension-ları işləmir
```

---

## Memory leaks və state problemləri

```php
<?php
// PROBLEM 1: Static property
class Counter
{
    public static int $count = 0;  // Request-lər arası qalır!
}

Counter::$count++;  // 1-ci request: 1
Counter::$count++;  // 2-ci request: 2  (bug!)

// Həll: static property-lərdən qaç,
// ya da Octane flush listener-də sıfırla.
```

```php
<?php
// PROBLEM 2: Singleton container state
// Service Provider-də register edilmiş singleton bütün request-lər görür.

app()->singleton(UserContext::class, function () {
    return new UserContext();  // Boot-da bir dəfə yaranır
});

// request-1:
app(UserContext::class)->setUser(1);
// request-2:
app(UserContext::class)->getUser();  // ← 1 qaytarır! (leaked!)

// Həll: per-request reset və ya scoped binding
app()->scoped(UserContext::class, fn() => new UserContext());
// Octane avtomatik flush edir.
```

```php
<?php
// PROBLEM 3: Eloquent model static events
// Model::creating(), Model::saving() listener-ləri təkrar qeydiyyatdan keçə bilər.

class User extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            // Hər request-də yenidən qeydiyyatdan keçməz,
            // AMMA əgər Octane flush siyahısında deyilsə stale qala bilər.
        });
    }
}
```

```php
<?php
// PROBLEM 4: Closure capture (memory leak)
Route::get('/data', function () use ($hugeArray) {
    return response()->json($hugeArray);
});
// Worker startup-da $hugeArray memory-də qalır — bütün request-lər üçün.

// PROBLEM 5: Opened file handles / DB connections
// Request içində fopen(), DB::connection() açılıbsa — bağlanmalıdır.
// Octane RequestHandled listener-də cleanup lazım.
```

```
GENERAL RULES (Octane-da):
  ✓ Facade cache-lərinə arxayın olma — flush et
  ✓ Request() helper-i HƏMƏŞƏ cari request-i göstərməyə bilər
  ✓ Per-request state singleton-a deyil, scoped-a qoy
  ✓ File handle, DB connection, Redis connection leak-lərini izlə
  ✓ max_requests = 500-1000 ilə worker restart et (safety net)
  ✓ Memory metric (Prometheus) ilə təqib et — artış = leak
```

---

## Singleton və DI container traps

```php
<?php
// YANLIŞ — request state singleton-da
class RequestLogger
{
    private array $logs = [];
    
    public function log(string $msg): void
    {
        $this->logs[] = $msg;
    }
}

app()->singleton(RequestLogger::class);
// Request-1: 100 log əlavə etdi
// Request-2: həmin singleton — $logs-da 100 köhnə entry var!

// DOĞRU — scoped (per-request)
app()->scoped(RequestLogger::class);
// Octane hər request-dən sonra flush edir.

// DOĞRU — singleton, amma state yox
class Logger
{
    public function __construct(private Writer $writer) {}
    
    public function log(string $msg): void
    {
        $this->writer->write($msg);  // external state-ə yaz, daxildə saxlama
    }
}
```

---

## Production deploy

```bash
# Systemd unit (RoadRunner)
# /etc/systemd/system/app-rr.service
[Unit]
Description=RoadRunner Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/app
ExecStart=/var/www/app/rr serve -c /var/www/app/.rr.yaml
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

```yaml
# Kubernetes deployment (Octane+Swoole)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-octane
spec:
  replicas: 3
  template:
    spec:
      containers:
      - name: app
        image: myapp:latest
        command: ["php", "artisan", "octane:start"]
        args: ["--server=swoole", "--host=0.0.0.0", "--port=8000", "--workers=4"]
        # Graceful shutdown — SIGTERM gələndə worker-lər tamamlansın
        lifecycle:
          preStop:
            exec:
              command: ["php", "artisan", "octane:stop"]
        # Readiness probe
        readinessProbe:
          httpGet:
            path: /health
            port: 8000
          initialDelaySeconds: 10
          periodSeconds: 5
        # Memory limit — worker max_requests ilə birgə
        resources:
          limits:
            memory: "512Mi"
          requests:
            memory: "256Mi"
```

---

## Performance benchmarks

```
Laravel 10 hello-world benchmark (m5.large, 2 vCPU, 8 GB RAM):

                    Req/s      P50 latency    P99 latency    Memory/worker
──────────────────────────────────────────────────────────────────────────
PHP-FPM             520        18ms           42ms           32 MB
RoadRunner 4w       2,850      3.5ms          12ms           85 MB
Swoole/Octane 4w    4,920      2.0ms          8ms            110 MB
FrankenPHP 4w       4,400      2.2ms          9ms            90 MB

Laravel API (DB query + cache):

                    Req/s      P50            P99
──────────────────────────────────────────────────
PHP-FPM             280        35ms           85ms
RoadRunner 4w       1,100      9ms            25ms
Swoole/Octane 4w    1,850      5ms            18ms

Nə vaxt Octane/RR sərfəli DEYİL:
  - Tək server, aşağı trafik (< 50 req/s)
  - Memory-sensitive app (hər MB vacibdir)
  - Ciddi memory leak-ləri olan legacy kod
  - Short-lived container (Lambda-style)
```

---

## İntervyu Sualları

- PHP-FPM ilə Octane/RoadRunner arasındakı əsas fərq nədir?
- Swoole və RoadRunner-dən hansını seçərdiniz və niyə?
- FrankenPHP niyə yeni bir nəsil sayılır? HTTP/3 və auto-HTTPS fərqi nədir?
- Long-running PHP-də hansı məmory leak riskləri var?
- `app()->singleton()` və `app()->scoped()` arasında fərq nədir?
- `max_requests` niyə lazımdır, 500 dəyəri hardan gəlir?
- Octane-da static property-lərdən niyə qaçmaq lazımdır?
- Graceful shutdown Swoole-da necə işləyir? `terminationGracePeriodSeconds` nə qədər olmalıdır?
- Request-arası state-i necə debug edirsiniz?
- Swoole Concurrent tasks və Laravel queue arasındakı fərq nədir?
- RoadRunner-in built-in queue-nu Laravel queue ilə müqayisə edin.
- Octane-da memory artımını necə monitor edirsiniz?
