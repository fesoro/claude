# Memory Leak Detection (Lead ⭐⭐⭐⭐)

## İcmal

Memory leak — ayrılmış yaddaşın artıq lazım olmadıqda sərbəst buraxılmaması vəziyyətidir. PHP-nin garbage collector-ı bunu çox halda özü idarə edir, lakin uzun ömürlü proseslər (Octane, Queue worker, RoadRunner), circular reference-lar, static property-lər, event listener-lər — bunlar yavaş-yavaş RAM istehlakını artırır. Ciddi olduqda proses crash edir.

## Niyə Vacibdir

PHP-FPM mühitlərində hər request prosesin məhv olması ilə bitir — leak nisbətən az problem yaradır. Lakin Laravel Queue worker, Octane, Horizon, artisan daemon-lar — bunlar saatlarla, günlərlə işləyir. 1 MB/request leak olan bir worker 24 saatda yüzlərlə MB RAM istehlak edər. Production-da anlaşılmaz şəkildə artan memory, kernel OOM killer-in prosesləri öldürməsi, servis restart-ları — hamısının kökündə memory leak ola bilər.

## Əsas Anlayışlar

- **PHP memory modeli:**
  - Hər PHP variable reference count-a sahibdir
  - `refcount=0` → garbage collector yaddaşı azad edir
  - **Circular reference:** A → B, B → A — ikisi də 0-a düşmür
  - PHP 5.3+ cyclic garbage collector: periodic scan

- **Leak mənbələri:**
  - **Static property-lər:** Process ölənə qədər yaşayır
  - **Global variable-lər:** `global $var` — uzun ömürlü
  - **Event listener-lər:** Model observer, closure, unsubscribe olmadan
  - **Circular reference:** Object graph-da loop
  - **External resource handle:** `fopen()`, `curl_init()` — bağlanmayan
  - **Cache accumulation:** In-process array cache limitsiz böyüyür
  - **Database connection:** Bağlanmayan connection

- **Detection alətləri:**
  - `memory_get_usage()` — cari PHP heap
  - `memory_get_peak_usage()` — maksimum RAM
  - **PHP-memprof** — function-level allocation profiling
  - **Valgrind** — C extension leak-ləri
  - **Xdebug** — heap snapshot (experimental)
  - **Blackfire** — memory timeline
  - **Sentry** — production memory anomaly alert
  - `ps aux` / `top` — OS-level RSS izlə

- **Uzun ömürlü proses leak pattern:**
  ```
  Worker başlayır: 30 MB
  1000 job: 31 MB
  5000 job: 45 MB
  10000 job: 80 MB   ← linear artış = leak
  20000 job: crash (OOM)
  ```

- **Garbage collector:**
  - `gc_collect_cycles()` — manual cyclic GC tetiklə
  - `gc_status()` — GC statistikası (PHP 7.3+)
  - `gc_disable()` / `gc_enable()`

## Praktik Baxış

**Memory growth monitoring (Queue worker):**

```php
// app/Jobs/BaseJob.php
abstract class BaseJob implements ShouldQueue
{
    protected function trackMemory(string $label): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug("Memory [{$label}]", [
            'current_mb' => round(memory_get_usage(true) / 1048576, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ]);
    }

    public function handle(): void
    {
        $this->trackMemory('start');
        $this->process();
        $this->trackMemory('end');

        // Cyclic GC tetiklə
        gc_collect_cycles();
    }
}
```

**Circular reference nümunəsi:**

```php
// ❌ Circular reference: memory leak
class Node
{
    public ?Node $parent = null;
    public array $children = [];

    public function addChild(Node $child): void
    {
        $child->parent = $this;        // child → parent
        $this->children[] = $child;    // parent → child
        // Hər ikisi birbirinə reference → refcount 0-a düşmür
    }
}

// ✅ WeakReference ilə həll (PHP 8.0+)
class Node
{
    private ?WeakReference $parent = null;
    public array $children = [];

    public function setParent(Node $parent): void
    {
        $this->parent = WeakReference::create($parent);
        // Weak ref: GC üçün maneə deyil
    }

    public function getParent(): ?Node
    {
        return $this->parent?->get();
    }
}
```

**Static accumulation (leak):**

```php
// ❌ Static array limitsiz böyüyür
class EventDispatcher
{
    private static array $log = [];

    public function dispatch(Event $event): void
    {
        self::$log[] = $event; // proses ölənə qədər yığılır
        // ...
    }
}

// ✅ Limit ilə:
class EventDispatcher
{
    private static array $log = [];
    private const MAX_LOG = 1000;

    public function dispatch(Event $event): void
    {
        self::$log[] = $event;
        if (count(self::$log) > self::MAX_LOG) {
            array_shift(self::$log); // köhnəni sil
        }
    }
}
```

**Laravel Horizon / Queue worker leak detection:**

```php
// app/Console/Commands/MonitorWorkerMemory.php
class MonitorWorkerMemory extends Command
{
    protected $signature = 'worker:memory-check';

    public function handle(): void
    {
        $baseline = memory_get_usage(true);
        $iterations = 0;
        $samples = [];

        while (true) {
            // 1 iş simulyasiya et
            $this->runOneJob();

            $iterations++;

            if ($iterations % 100 === 0) {
                $current = memory_get_usage(true);
                $samples[] = $current;
                $growth = ($current - $baseline) / 1048576;

                $this->info("Iter {$iterations}: " . round($current / 1048576, 2) . " MB (+{$growth} MB)");

                if ($growth > 50) { // 50 MB artıbsa alarm
                    $this->error('Memory leak detected!');
                    break;
                }
            }
        }
    }
}
```

**Closure leak (event listener):**

```php
// ❌ Anonymous listener memory leak
class OrderService
{
    public function __construct(private EventDispatcher $events)
    {
        // Bu closure $this-ı capture edir — circular ref riski
        $this->events->listen('order.placed', function (OrderPlaced $event) {
            $this->notifyAdmin($event->order);
        });
    }
}

// ✅ Düzgün: named method reference
class OrderService
{
    public function __construct(private EventDispatcher $events)
    {
        $this->events->listen('order.placed', [$this, 'onOrderPlaced']);
        // Və ya: static method ilə closure yoxdur
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        $this->notifyAdmin($event->order);
    }
}
```

**Resource handle leak:**

```php
// ❌ fopen bağlanmır
function processFile(string $path): void
{
    $handle = fopen($path, 'r');
    // exception gəlsə handle bağlanmır
    while ($line = fgets($handle)) {
        $this->process($line);
    }
    fclose($handle);
}

// ✅ try/finally ilə:
function processFile(string $path): void
{
    $handle = fopen($path, 'r');
    try {
        while ($line = fgets($handle)) {
            $this->process($line);
        }
    } finally {
        fclose($handle); // həmişə bağlanır
    }
}
```

**PHP-memprof ilə profiling:**

```php
// composer require krakjoe/memprof (PECL extension)

memprof_enable();

// ... memory leak olan kod ...

memprof_dump_callgrind(fopen('/tmp/memprof.out', 'w'));
// KCachegrind ilə vizuallaşdır
```

**Trade-offs:**
- `gc_collect_cycles()` — CPU spike yaradır, hər job-dan sonra çağırmaq performansı azaldır
- WeakReference — object-in yaşayıb-yaşamadığını yoxlamaq lazımdır
- Worker restart (--max-jobs) — leak-i maskalar, həll etmir
- PHP-memprof — production-da overhead böyük

**Common mistakes:**
- Queue worker-i restart etməklə leak-i həll etdiyini düşünmək
- Static cache-lərə limit qoymamaq
- Closure-da `$this` capture-ı gözardı etmək
- File handle-ları exception halında bağlamamaq
- Memory growth-u "normallaşma" olaraq qəbul etmək

## Nümunələr

### Real Ssenari: Horizon worker 6 saatdan sonra crash

```
Simptom: horizon:work prosesləri ~6 saatdan sonra OOM xətası ilə ölür.

Debug:
1. supervisord log-da "Killed" (OOM killer)
2. ps aux ilə worker PID memory artışını saatbəsaat izlə
3. 30MB → 200MB → 500MB → crash

İzleme:
dd('Worker memory: ' . memory_get_usage(true) / 1048576 . ' MB');
// Hər 1000 job-dan sonra 5MB artış

Root cause:
- OrderObserver::booted() static closure register edir
- Hər job processOrderCreated işlədikdə model boot olur
- Static $dispatcher->listeners[] hər dəfə closure əlavə edir

Həll:
- Observer-i AppServiceProvider-a köçürdük (yalnız 1 dəfə register)
- Limit: listeners array max 500 element
- gc_collect_cycles() hər 100 job-dan sonra

Nəticə: 6 saatdan sonra crash → 30 gündə heç crash yox
```

### Kod Nümunəsi

```php
<?php

// app/Services/LongRunningJobRunner.php
class LongRunningJobRunner
{
    private int $jobCount = 0;
    private int $initialMemory;

    public function run(): void
    {
        $this->initialMemory = memory_get_usage(true);

        while ($job = $this->fetchNextJob()) {
            $this->executeJob($job);
            $this->afterJob();
        }
    }

    private function afterJob(): void
    {
        $this->jobCount++;

        // Hər 50 job-dan sonra GC
        if ($this->jobCount % 50 === 0) {
            gc_collect_cycles();
            gc_mem_caches(); // PHP 7.0+

            $current = memory_get_usage(true);
            $growthMb = ($current - $this->initialMemory) / 1048576;

            if ($growthMb > 100) {
                Log::critical('Memory leak in worker', [
                    'job_count' => $this->jobCount,
                    'growth_mb' => round($growthMb, 2),
                ]);
                // Prosesi restart et (supervisor yenidən başladacaq)
                exit(1);
            }
        }
    }
}
```

## Praktik Tapşırıqlar

1. **Leak simulyasiya et:** Static array-a hər iteration-da böyük data əlavə edən bir script yaz, `memory_get_usage()` ilə böyüməni izlə.

2. **Circular ref test:** `WeakReference` olmadan və ilə circular reference yarat, `gc_collect_cycles()` çağır, fərqi ölç.

3. **Worker monitor:** Artisan command yaz — hər 100 job-dan sonra memory ölçsün, `Log::info`-ya yazsın, 50MB artdıqda `exit(1)` etsin.

4. **Horizon leak:** Horizon worker başlat, 10K job göndər, `ps aux --sort rss` ilə memory artışını hər dəqiqə izlə.

5. **Blackfire memory:** Blackfire CLI ilə memory-intensive bir command profile et, top allocation-ları tap.

## Əlaqəli Mövzular

- `07-garbage-collection.md` — PHP GC mexanizmi dərindən
- `01-performance-profiling.md` — Memory profiling tool-ları
- `09-async-batch-processing.md` — Long-running job memory idarəetməsi
- `13-flame-graphs.md` — Memory allocation vizuallaşdırması
- `11-apm-tools.md` — Production memory anomaly alert
