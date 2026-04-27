# Garbage Collection Concepts (Lead ⭐⭐⭐⭐)

## İcmal

Garbage collection (GC) — proqramlaşdırma dilinin runtime-ının artıq istifadə olunmayan yaddaş bölgələrini avtomatik müəyyənləşdirib azad etdiyi prosesdir. PHP, Java, Go, Python — hər birinin GC mexanizmi fərqlidir. Senior/Lead developer üçün GC-nin necə işlədiyini bilmək vacibdir: yanlış yazılmış kod GC-ni məğlub edər, performansı pozan "stop-the-world" pause-lar yaradar.

## Niyə Vacibdir

Java-da GC pause-ları aşkar şəkildə latency spike yaradır — "saytımız hər 30 saniyədə bir 200ms donur" — çox güman ki GC. PHP-də circular reference-lar GC-ni məşğul edir. Go-nun concurrent GC-si latency-ni minimize edir, lakin GC pressure yüksəkdirsə throughput düşür. Bu mexanizmləri anlamadan memory tuning edə bilməzsiniz.

## Əsas Anlayışlar

- **GC alqoritmləri:**
  - **Reference counting:** Hər object-in reference sayı sıfıra düşdükdə azad et (PHP birincil)
  - **Mark-and-sweep:** Kökdən başlayaraq əlçatan object-ləri işarələ, qalanları sil (PHP cyclic GC)
  - **Tri-color mark-sweep:** Go concurrent GC (white/grey/black)
  - **Generational GC:** Object-ləri yaşa görə böl (Java: Young/Old/Permanent)
  - **Stop-the-world:** GC işləyərkən bütün thread-lər dayanır
  - **Concurrent / Incremental GC:** GC tətbiqlə eyni vaxtda işləyir

- **PHP GC:**
  - **Zend Engine reference counting** — birincil mexanizm
  - **Cyclic Collector** — circular reference aşkarlamaq üçün mark-and-sweep
  - `gc_enable()` / `gc_disable()` / `gc_collect_cycles()`
  - PHP 8.0+: `gc_mem_caches()` — memory cache-i azad et
  - GC buffer 10,000 root dolduqda avtomatik çalışır

- **Java GC növləri:**
  - **Serial GC** — single-threaded, small heap
  - **Parallel GC** — multi-threaded, throughput-focused
  - **G1 GC (Garbage First)** — region-based, predictable pause (Java 9+ default)
  - **ZGC** — sub-millisecond pause (Java 15+)
  - **Shenandoah** — concurrent, low-latency (RedHat)

- **Go GC:**
  - Concurrent tri-color mark-and-sweep
  - Write barrier ilə concurrent mark
  - GOGC environment variable (default 100 = heap 2x olduqda GC)
  - Manual: `runtime.GC()`, `debug.FreeOSMemory()`

- **Key metrics:**
  - **GC pause time** — stop-the-world müddəti
  - **GC throughput** — GC-yə gedən CPU vaxtı
  - **Heap size** — JVM heap, Go heap
  - **Allocation rate** — saniyədə neçə MB ayrılır
  - **Live set** — əlçatan, sililinə bilməyən object-lər

- **GC tuning:**
  - Daha az allocation → daha az GC pressure
  - Object reuse (object pool pattern)
  - Generational hypothesis: çox object çox tez ölür — short-lived-lar Young gen-dən əliminat
  - Heap size artırmaq GC-ni azaldır (amma memory artır)

## Praktik Baxış

**PHP GC davranışı:**

```php
// Reference counting — normal azad etmə
$a = new stdClass(); // refcount=1
$b = $a;             // refcount=2
unset($a);           // refcount=1
unset($b);           // refcount=0 → dərhal azad olur

// Circular reference — cyclic GC lazım
$a = new stdClass();
$b = new stdClass();
$a->ref = $b;   // a → b
$b->ref = $a;   // b → a
unset($a);      // b-nin ref var, a azad olmur
unset($b);      // a-nın ref var, b azad olmur
// İkisi də yaddaşda qalır — GC cycle lazımdır

gc_collect_cycles(); // manual trigger

// PHP 8.1+ Fibers ilə GC
$fiber = new Fiber(function() {
    // Fiber scope-da da GC işləyir
    $data = range(1, 100000);
    Fiber::suspend();
    // $data burada azad edilir
    unset($data);
    gc_collect_cycles();
});
```

**Java GC monitoring:**

```bash
# JVM flags
java -XX:+UseG1GC \
     -XX:MaxGCPauseMillis=200 \
     -XX:+PrintGCDetails \
     -XX:+PrintGCDateStamps \
     -Xms2g -Xmx4g \
     -jar app.jar

# GC log analiz
java -XX:+UseZGC \
     -Xmx8g \
     -Xlog:gc*:file=/var/log/gc.log \
     -jar app.jar
```

```java
// Java: object reuse ilə allocation azalt
// ❌ Hər request üçün yeni object
public Response processRequest(Request req) {
    StringBuilder sb = new StringBuilder(); // hər dəfə yeni
    sb.append("Result: ").append(compute(req));
    return new Response(sb.toString());
}

// ✅ ThreadLocal ilə reuse
private static final ThreadLocal<StringBuilder> SB_POOL =
    ThreadLocal.withInitial(StringBuilder::new);

public Response processRequest(Request req) {
    StringBuilder sb = SB_POOL.get();
    sb.setLength(0); // reset, yeni object yox
    sb.append("Result: ").append(compute(req));
    return new Response(sb.toString());
}
```

**Go GC tuning:**

```go
package main

import (
    "runtime"
    "runtime/debug"
)

func init() {
    // GOGC=200: heap 3x olduqda GC (default: 2x)
    debug.SetGCPercent(200)

    // Memory limit (Go 1.19+): max 4GB heap
    debug.SetMemoryLimit(4 * 1024 * 1024 * 1024)
}

// Object pool ilə allocation azalt
var bufPool = sync.Pool{
    New: func() interface{} {
        buf := make([]byte, 0, 4096)
        return &buf
    },
}

func processData(data []byte) []byte {
    bufPtr := bufPool.Get().(*[]byte)
    defer bufPool.Put(bufPtr) // geri qoy

    buf := (*bufPtr)[:0] // reset
    buf = append(buf, data...)
    // process...
    result := make([]byte, len(buf))
    copy(result, buf)
    return result
}

// GC monitoring
func gcStats() {
    var stats runtime.MemStats
    runtime.ReadMemStats(&stats)

    log.Printf("HeapAlloc: %d MB", stats.HeapAlloc/1024/1024)
    log.Printf("NumGC: %d", stats.NumGC)
    log.Printf("PauseTotalNs: %d ms", stats.PauseTotalNs/1e6)
    log.Printf("GCCPUFraction: %.2f%%", stats.GCCPUFraction*100)
}
```

**PHP GC statistikası:**

```php
// PHP 7.3+
$gcStatus = gc_status();
/*
[
    'running' => false,
    'protected' => false,
    'full' => false,
    'roots' => 0,
    'threshold' => 10001,
    'collected' => 12345,  // toplam silınən object
    'runs' => 100,         // neçə dəfə GC çalışdı
    'application_time' => 0.5,  // tətbiq vaxtı
    'collector_time' => 0.01,   // GC-yə gedən vaxt
]
*/

// Uzun ömürlü prosesdə manual tuning
class WorkerLoop
{
    private int $jobCount = 0;

    public function run(): void
    {
        while (true) {
            $this->processJob();
            $this->jobCount++;

            if ($this->jobCount % 100 === 0) {
                // GC forcefully çalışdır
                $before = memory_get_usage(true);
                gc_collect_cycles();
                gc_mem_caches();
                $freed = ($before - memory_get_usage(true)) / 1024;

                if ($freed > 1024) { // 1MB+ azad olubsa log
                    Log::debug("GC freed {$freed} KB");
                }
            }
        }
    }
}
```

**JVM GC tuning cheat sheet:**

```bash
# G1GC — production default (Java 11+)
-XX:+UseG1GC
-XX:MaxGCPauseMillis=100    # target pause: 100ms
-XX:G1HeapRegionSize=8m     # region size
-XX:InitiatingHeapOccupancyPercent=45

# ZGC — low-latency (Java 17+)
-XX:+UseZGC
# Sub-millisecond pause, amma throughput az

# Shenandoah
-XX:+UseShenandoahGC
-XX:ShenandoahGCHeuristics=adaptive

# Heap tuning
-Xms4g -Xmx4g   # initial = max → heap resize overhead yox
-XX:+AlwaysPreTouch  # startup-da memory pre-allocate
```

**Trade-offs:**
- Böyük heap → az GC, amma daha uzun pause (G1 istisna)
- ZGC → az pause, amma daha çox CPU
- `gc_disable()` PHP-də → cyclic GC yox, amma leak riski
- Object pool → GC azalır, amma kod mürəkkəbləşir
- GOGC düşük → tez-tez GC, az memory; yüksək → nadir GC, çox memory

**Common mistakes:**
- Java-da heap-i maximum artırmaq (GC pause da artır)
- PHP-də GC-ni disable etmək (leak qaçılmaz)
- Go-da global variable-a slice append etmək (leak riski)
- Object-ləri pool-a qaytarmamaq (pool azalır, yeni allocation)
- GC metrics-i izləməmək (problem gəlir, anlayırsan)

## Nümunələr

### Real Ssenari: Java microservice 30 saniyədə bir gecikir

```
Simptom: API p99 latency 30 saniyədə bir 2 saniyəyə qalxır.

Debug:
1. Grafana → latency spike hər 30s
2. GC log: "GC pause: 1.8s" — eyni interval
3. Heap: 90% dolub, Full GC tetiklənir

Analiz:
-Xmx2g: Young gen dolur, promote olur, Old gen dolur → Full GC

Həll:
1. -Xmx2g → -Xmx4g (heap artır, promote azalır)
2. G1GC aktiv: -XX:+UseG1GC -XX:MaxGCPauseMillis=100
3. Allocation analiz: large byte[] buffer-lar Old gen-ə gedir
4. Buffer pool pattern ilə allocation azaltdıq

Nəticə: GC pause 1.8s → 80ms, p99 spikes yox oldu
```

### Kod Nümunəsi

```php
<?php

// PHP GC-friendly object design
class CacheEntry
{
    // Circular reference qarşısı: WeakReference
    private ?WeakReference $parent = null;

    public function __construct(
        public readonly string $key,
        public mixed $value,
        private readonly int $expiresAt,
    ) {}

    public function setParent(CacheEntry $parent): void
    {
        $this->parent = WeakReference::create($parent);
    }

    public function isExpired(): bool
    {
        return time() > $this->expiresAt;
    }

    // Explicit cleanup (GC-yə kömək)
    public function destroy(): void
    {
        $this->value = null;
        $this->parent = null;
    }
}

// Worker-də GC sağlığı izləmə
class GcHealthMonitor
{
    public function getReport(): array
    {
        $stats = gc_status();
        $mem = [
            'current_mb' => round(memory_get_usage(true) / 1048576, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];

        return [
            'memory' => $mem,
            'gc' => [
                'runs' => $stats['runs'],
                'collected' => $stats['collected'],
                'roots_buffered' => $stats['roots'],
                'gc_time_ratio' => $stats['runs'] > 0
                    ? round($stats['collector_time'] / ($stats['application_time'] + 0.001) * 100, 2) . '%'
                    : '0%',
            ],
        ];
    }
}
```

## Praktik Tapşırıqlar

1. **PHP circular ref:** `Node` class-ı ilə circular reference yarat, `gc_collect_cycles()` əvvəl/sonra `memory_get_usage()` qeyd et.

2. **GC stats monitor:** `gc_status()` əsasında worker loop yazın, hər 1000 iteration-da GC report log edin.

3. **Java GC log:** Spring Boot layihəsinə `-XX:+PrintGCDetails -Xlog:gc*` əlavə edin, load test run edin, GC pause-ları analiz edin.

4. **Go pool:** `sync.Pool` ilə buffer pool implement edin, benchmark ilə pool olmadan vs pool ilə allocation fərqini ölçün.

5. **WeakReference:** PHP 8.0+ WeakReference ilə event listener pattern implement edin — listener object GC-dən sonra avtomatik deregistered olsun.

## Əlaqəli Mövzular

- `06-memory-leak-detection.md` — Memory leak mənbələri
- `01-performance-profiling.md` — GC profiling alətləri
- `09-async-batch-processing.md` — Long-running proseslərdə GC
- `13-flame-graphs.md` — GC pause-ları flame graph-da görmək
- `12-load-testing.md` — GC pressure-ni load test ilə tetikləmək
