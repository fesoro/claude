# PHP Profiling Tools

## Mündəricat
1. [Profiling nədir?](#profiling-nədir)
2. [Əsas Alətlər](#əsas-alətlər)
3. [Xdebug Profiler](#xdebug-profiler)
4. [Blackfire](#blackfire)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Profiling nədir?

```
Profiling — tətbiqin performansını ölçmək:
  Hansı funksiya ən çox vaxt aparır?
  Hansı funksiya ən çox çağırılır?
  Memory harada artır?

Profiling növləri:

  CPU Profiling:
    Hər funksiyanın icra müddəti
    "Ən yavaş yer hardadır?"

  Memory Profiling:
    Hər funksiyanın memory istifadəsi
    Memory leak axtarışı

  Wall-clock vs CPU time:
    Wall-clock: real vaxt (I/O gözləmə daxil)
    CPU time: yalnız CPU istifadəsi

Sampling vs Instrumentation:
  Instrumentation: hər funksiya çağırışını intercept edir
    → Dəqiq amma overhead böyükdür
  Sampling: periodik olaraq stack trace çıxarır
    → Az overhead, az dəqiq
```

---

## Əsas Alətlər

```
Xdebug Profiler:
  PHP extension
  Cachegrind format (KCacheGrind, Webgrind ilə)
  Development-də istifadə
  Production-da overhead çoxdur

Blackfire:
  SaaS profiler (Blackfire.io)
  Low overhead (sampling)
  CI/CD inteqrasiyası
  Performance assertions
  Production-da istifadə edilə bilər

Tideways / SPX:
  Open source PHP profiler
  Low overhead
  Web UI

Swoole Tracker:
  Swoole tətbiqləri üçün
  Async code profiling

Linux perf + BPF:
  Sistem səviyyəli profiling
  PHP JIT + native kod

APM (Application Performance Monitoring):
  Datadog APM, New Relic, Elastic APM
  Production tracing
  Distributed tracing (mikroservislər)
```

---

## Xdebug Profiler

```
Konfiqurasiya (php.ini):
  xdebug.mode = profile
  xdebug.output_dir = /tmp/xdebug
  xdebug.profiler_output_name = cachegrind.out.%p.%H

Trigger:
  URL: ?XDEBUG_PROFILE=1
  Environment: XDEBUG_MODE=profile
  PHP: xdebug_start_profiling()

Cachegrind output analizi:
  KCacheGrind (Linux) / QCacheGrind (Mac/Windows)
  Webgrind (browser-based)

Məlumatlar:
  Inclusive time: funksiyanın özü + çağırdıqları
  Exclusive time: yalnız funksiyanın özü
  Call count: neçə dəfə çağırılıb

Nümunə çıxış (WebGrind):
  Function                    | Calls | Total Self Time
  App\Service\OrderService::place | 1 | 250ms  12ms
  App\Repository\OrderRepository::save | 1 | 180ms 180ms ← bottleneck!
```

---

## Blackfire

```
Blackfire xüsusiyyətləri:
  SDK + Browser Extension
  Comparison (A/B): dəyişiklikdən əvvəl/sonra
  Assertions: "bu sorğu 100ms-dən çox olmamalıdır"
  Timeline görünüşü

Blackfire assertions (nümunə):
  # .blackfire.yaml
  tests:
    "Order creation must be fast":
      path: /api/orders
      assertions:
        - "main.wall_time < 200ms"
        - "metrics.sql.queries.count < 5"
        - "metrics.http.requests.count == 0"

CI/CD inteqrasiyası:
  GitHub Actions:
    - name: Blackfire
      run: blackfire run --assert="main.wall_time < 500ms" php artisan ...
```

---

## PHP İmplementasiyası

```php
<?php
// 1. Manual timing (sadə profiling)
class SimpleProfiler
{
    private array $timers = [];

    public function start(string $label): void
    {
        $this->timers[$label] = [
            'start'  => hrtime(true),
            'memory' => memory_get_usage(true),
        ];
    }

    public function stop(string $label): array
    {
        if (!isset($this->timers[$label])) {
            throw new \RuntimeException("Timer '{$label}' başladılmayıb");
        }

        $elapsed = (hrtime(true) - $this->timers[$label]['start']) / 1e6; // ms
        $memory  = memory_get_usage(true) - $this->timers[$label]['memory'];

        unset($this->timers[$label]);

        return [
            'label'      => $label,
            'duration_ms' => round($elapsed, 2),
            'memory_bytes' => $memory,
        ];
    }
}

// İstifadə:
$profiler = new SimpleProfiler();
$profiler->start('db_query');
$orders = $repository->findAll();
$result = $profiler->stop('db_query');
// ['label' => 'db_query', 'duration_ms' => 45.23, 'memory_bytes' => 204800]
```

```php
<?php
// 2. Decorator ilə transparent profiling
class ProfilingRepositoryDecorator implements OrderRepository
{
    public function __construct(
        private OrderRepository $inner,
        private MetricsCollector $metrics,
    ) {}

    public function findById(string $id): ?Order
    {
        $start = hrtime(true);

        try {
            $result = $this->inner->findById($id);
            $this->metrics->histogram(
                'repository.query.duration',
                (hrtime(true) - $start) / 1e6,
                ['method' => 'findById', 'status' => 'success']
            );
            return $result;
        } catch (\Throwable $e) {
            $this->metrics->histogram(
                'repository.query.duration',
                (hrtime(true) - $start) / 1e6,
                ['method' => 'findById', 'status' => 'error']
            );
            throw $e;
        }
    }
}
```

```php
<?php
// 3. Xdebug trigger middleware (development-də)
class XdebugProfilerMiddleware
{
    public function process(Request $request, Handler $handler): Response
    {
        $shouldProfile = $request->query->has('XDEBUG_PROFILE')
            && $this->isAllowedToProfile($request);

        if ($shouldProfile && extension_loaded('xdebug')) {
            xdebug_start_profiling();
        }

        $response = $handler->handle($request);

        if ($shouldProfile && extension_loaded('xdebug')) {
            xdebug_stop_profiling();
        }

        return $response;
    }

    private function isAllowedToProfile(Request $request): bool
    {
        // Yalnız development IP-lərdən
        return in_array($request->getClientIp(), ['127.0.0.1', '::1']);
    }
}
```

```php
<?php
// 4. SPX (Simple PHP eXtension) — low overhead profiler
// php.ini:
// extension=spx.so
// spx.http_enabled=1
// spx.http_key="dev-secret"
// spx.http_ip_whitelist="127.0.0.1"

// URL ilə trigger:
// http://localhost/?SPX_ENABLED=1&SPX_KEY=dev-secret&SPX_FP_LIVE=1

// Metrics seçimi:
// SPX_METRICS=wt,ct,zm  (wall time, cpu time, memory)
```

---

## İntervyu Sualları

- Profiling ilə benchmarking arasındakı fərq nədir?
- Xdebug profiler production-da niyə istifadə edilmir?
- Blackfire-ın performance assertion funksiyası nəyə lazımdır?
- Inclusive time ilə exclusive time nə deməkdir?
- Memory leak profiling üçün hansı alətdən istifadə edərdiniz?
- APM (Datadog, New Relic) lokal profiler-dan nə ilə fərqlənir?
