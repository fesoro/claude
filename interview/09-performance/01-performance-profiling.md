# Performance Profiling Approach (Senior ⭐⭐⭐)

## İcmal

Performance profiling — tətbiqin hansı hissəsinin nə qədər resurs istehlak etdiyini ölçən sistematik prosesdir. Profiling olmadan optimizasiya "qaranluqda atılan ox" kimidir: problem hardadır bilmədən kod dəyişdirmək. Senior developer üçün profiling bir alət deyil, düşüncə tərzidir — bir şikayət gəlir, data toplanır, bottleneck tapılır, sonra müdaxilə olunur.

## Niyə Vacibdir

PHP/Laravel mühitlərində ən çox rast gəlinən problemlər — N+1 query, yavaş blade render, artıq middleware işləmələri — hamısı profilinq olmadan tapılmır. Production-da 1 saniyəlik gecikməni aşkar etmək ilkin olaraq simptomun izlənməsini, sonra instrument olunmuş mühitdə reproduce edilməsini tələb edir. Müsahibədə "mən log-a baxdım, bəlkə index kömək edər" deyən kandidat ilə "mən Clockwork açdım, DB query 340ms aparırdı, EXPLAIN ANALYZE çəkdim, seq scan var idi, composite index əlavə etdim, 12ms oldu" deyən kandidat arasındakı fərq profiling biliyidir.

## Əsas Anlayışlar

- **Profiler növləri:**
  - **Sampling profiler** — müəyyən intervallarla stack snapshot alır (Xdebug sampling mode, Blackfire)
  - **Tracing/Instrumenting profiler** — hər function call-ı izləyir (daha dəqiq, amma overhead böyük)
  - **APM agent** — production-da low-overhead izləmə (Datadog, New Relic)
  - **Query profiler** — DB-level izləmə (MySQL slow query log, `EXPLAIN ANALYZE`)

- **Key metrics:**
  - **Wall time** — real keçən zaman (I/O + CPU)
  - **CPU time** — yalnız prosessor vaxtı
  - **Memory peak** — maksimum yaddaş istehlakı
  - **Call count** — funksiya neçə dəfə çağırılıb
  - **Inclusive vs Exclusive time** — alt çağırışlarla/sizin

- **PHP ekosistemi:**
  - **Xdebug** (`xdebug.mode=profile`) — KCachegrind ilə vizuallaşdırma
  - **Blackfire.io** — production-safe, timeline, call graph
  - **Clockwork** / **Laravel Debugbar** — development, HTTP paneli
  - **Tideways** — PHP 8 native, CI/CD inteqrasiyası
  - **Datadog APM** — distributed tracing, production

- **Profiling workflow:**
  1. Baseline ölçmək (benchmark)
  2. Profiler aktivləşdirmək
  3. Hotspot-ları tapmaq (ağacın kökünə baxmaq)
  4. Müdaxilə etmək
  5. Yenidən ölçmək (regression yoxlamaq)

- **Bottleneck növləri:**
  - **CPU-bound** — hesablama çox; algorithm-a baxmaq
  - **I/O-bound** — disk/network gözləmə; async/cache strategiyası
  - **Memory-bound** — çox allocation; object reuse, generator
  - **Lock contention** — paralel proseslər bir resursu gözləyir

## Praktik Baxış

**Real iş axını:**

```
Şikayət: "checkout 4 saniyə çəkir"
↓
APM-dən P99 latency trace götür
↓
Trace-də ən uzun span-ı tap (məs: DB 2.8s)
↓
Slow query log-dan SQL-i tap
↓
EXPLAIN ANALYZE ilə seq scan gör
↓
Composite index əlavə et
↓
Staging-də Blackfire ilə qeyd et: 4s → 0.9s
↓
Deploy → APM-də P99 izlə
```

**Blackfire ilə real profiling nümunəsi:**

```bash
# CLI profiling
blackfire run php artisan some:command

# HTTP profiling (curl vasitəsilə)
blackfire curl https://app.test/api/orders

# Comparison (before/after)
blackfire --reference 1 --samples 10 curl https://app.test/api/orders
```

**Xdebug profiling:**

```php
// php.ini / xdebug.ini
xdebug.mode = profile
xdebug.output_dir = /tmp/xdebug
xdebug.profiler_output_name = cachegrind.out.%p.%r

// Trigger (yalnız istədiyiniz zaman)
xdebug.start_with_request = trigger
// URL: ?XDEBUG_PROFILE=1
```

**Laravel Telescope ilə query profiling:**

```php
// AppServiceProvider
Telescope::filter(function (IncomingEntry $entry) {
    return $entry->isSlowerThan(100); // 100ms-dən yavaş olanları saxla
});
```

**Custom timing (production-safe):**

```php
$start = hrtime(true);
$result = $this->heavyOperation();
$elapsed = (hrtime(true) - $start) / 1e6; // ms

Log::channel('performance')->info('heavy_operation', [
    'duration_ms' => $elapsed,
    'user_id' => auth()->id(),
]);
```

**N+1 detection (Laravel):**

```php
// Development-da
DB::enableQueryLog();
$orders = Order::with('items', 'user')->get();
dd(DB::getQueryLog()); // neçə query olduğunu gör

// Production-da (Telescope)
// N+1 queries avtomatik flag olunur
```

**Trade-offs:**
- Profiler overhead-i production-da qəbuledilməzdir (Xdebug 2-10x yavaşladır)
- Sampling profiler az overhead, az dəqiqlik; tracing — əksi
- Development profile ≠ production profile (data volume, cache state fərqlənir)

**Common mistakes:**
- Profiling olmadan "mən bilirik harada problem var" demək
- Development-da profiling edib production problem olduğunu düşünmək
- Yalnız ortalama (mean) baxmaq — P95/P99-a baxmamaq
- Bir dəyişiklik edib re-profile etməmək

## Nümunələr

### Ümumi Profiling Ssenarisi

```
Scenario: E-commerce saytda admin panel "Orders" səhifəsi 6 saniyə çəkir.

1. Clockwork açıldı → 847 DB query görüldü
2. Query log analiz → hər sifariş üçün ayrıca "user" + "items" query
3. Kod: Order::all() — with() yoxdur
4. Düzəliş: Order::with('user', 'items', 'items.product')->paginate(50)
5. Nəticə: 847 query → 4 query, 6s → 0.3s
```

### Kod Nümunəsi

```php
<?php

// app/Services/PerformanceProfiler.php

class PerformanceProfiler
{
    private array $marks = [];

    public function mark(string $label): void
    {
        $this->marks[$label] = [
            'time' => hrtime(true),
            'memory' => memory_get_usage(true),
        ];
    }

    public function measure(string $from, string $to): array
    {
        $start = $this->marks[$from];
        $end = $this->marks[$to];

        return [
            'duration_ms' => ($end['time'] - $start['time']) / 1e6,
            'memory_delta_kb' => ($end['memory'] - $start['memory']) / 1024,
        ];
    }
}

// İstifadə:
$profiler = new PerformanceProfiler();
$profiler->mark('start');

$orders = Order::with('user', 'items')->where('status', 'pending')->get();
$profiler->mark('after_query');

$transformed = $orders->map(fn($o) => $this->transform($o));
$profiler->mark('after_transform');

Log::info('checkout_perf', [
    'query' => $profiler->measure('start', 'after_query'),
    'transform' => $profiler->measure('after_query', 'after_transform'),
]);
```

```php
// Middleware: request-level profiling
class PerformanceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $queryCount = 0;

        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $next($request);

        $duration = (hrtime(true) - $start) / 1e6;

        if ($duration > 500 || $queryCount > 20) {
            Log::warning('slow_request', [
                'path' => $request->path(),
                'duration_ms' => round($duration, 2),
                'query_count' => $queryCount,
                'user_id' => auth()->id(),
            ]);
        }

        return $response;
    }
}
```

## Praktik Tapşırıqlar

1. **Xdebug qur:** Local Laravel proyektdə Xdebug profiling mode-u aktiv et, KCachegrind ilə `/api/orders` endpoint-ini analiz et.

2. **N+1 tap:** Mövcud bir controller götür, `DB::enableQueryLog()` əlavə et, neçə query olduğunu hesabla, `with()` ilə optimallaşdır.

3. **Benchmark yaz:** PHPBench ilə bir collection transform funksiyasını benchmark et — array vs Collection vs Generator.

4. **Blackfire comparison:** Bir endpoint-i optimallaşdırmadan əvvəl və sonra Blackfire ilə profile et, call graph-dəki fərqi izah et.

5. **Custom profiler:** Yuxarıdakı `PerformanceProfiler` class-ını genişləndir — nested marks, HTML report, threshold alerts.

6. **Slow query log:** MySQL `slow_query_log = ON, long_query_time = 0.5` aktiv et, Laravel seeder ilə 10K order yarat, `/orders` endpointini çağır, slow log-u analiz et.

## Əlaqəli Mövzular

- `02-query-optimization.md` — DB bottleneck-ləri həll etmək
- `11-apm-tools.md` — Production observability
- `13-flame-graphs.md` — CPU profiling vizuallaşdırması
- `15-indexing-strategy.md` — Query performansını index ilə artırmaq
- `06-memory-leak-detection.md` — Memory profiling
