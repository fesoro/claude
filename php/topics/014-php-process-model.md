# PHP Proses Modeli (Junior)

## Mündəricat
1. [Shared-Nothing Arxitekturası](#shared-nothing-arxitekturası)
2. [PHP-FPM Worker Lifecycle](#php-fpm-worker-lifecycle)
3. [CLI Proses Modeli](#cli-proses-modeli)
4. [Async PHP Modelləri](#async-php-modelləri)
5. [Memory İzolyasiyası](#memory-izolyasiyası)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Shared-Nothing Arxitekturası

```
PHP-nin əsas dizayn prinsipi: hər request tamamilə izolyasiyalıdır.

Request 1:              Request 2:
┌──────────────────┐    ┌──────────────────┐
│  Worker Process  │    │  Worker Process  │
│  ┌────────────┐  │    │  ┌────────────┐  │
│  │ $variable  │  │    │  │ $variable  │  │
│  │ heap/stack │  │    │  │ heap/stack │  │
│  └────────────┘  │    │  └────────────┘  │
│  Request başlar  │    │  Request başlar  │
│  → işlər        │    │  → işlər         │
│  → bütün memory │    │  → bütün memory  │
│    silinir      │    │    silinir       │
└──────────────────┘    └──────────────────┘
         ↑                       ↑
    Paylaşılan yoxdur! (OPcache shared memory istisna)

Faydaları:
  + Bir request crash olsa digərinə təsir yoxdur
  + Race condition riski minimal
  + Debug sadədir
  
Çatışmazlıqları:
  - Hər request üçün bootstrap xərci (DI container, config yüklənir)
  - Proseslər arası state paylaşmaq çətin (Redis/DB lazımdır)
```

---

## PHP-FPM Worker Lifecycle

```
┌─────────────────────────────────────────────────────────┐
│                   Worker Lifecycle                      │
│                                                         │
│  FPM Start                                              │
│      │                                                  │
│      ▼                                                  │
│  Worker fork()──────────────────────────────────┐      │
│      │                                          │      │
│      ▼                                          │      │
│  Wait for request ◄──────────────────┐          │      │
│      │                               │          │      │
│      ▼                               │          │      │
│  Accept connection                   │          │      │
│      │                               │          │      │
│      ▼                               │          │      │
│  Execute PHP script                  │          │      │
│      │                               │          │      │
│      ▼                               │          │      │
│  Send response                       │          │      │
│      │                               │          │      │
│      ▼                               │          │      │
│  Cleanup (destruct, gc)              │          │      │
│      │                               │          │      │
│      ▼                               │          │      │
│  max_requests çatıbmı? ──── No ──────┘          │      │
│      │ Yes                                      │      │
│      ▼                                          │      │
│  Worker exit + yeni fork() ─────────────────────┘      │
└─────────────────────────────────────────────────────────┘

pm.max_requests = 500 → 500 request-dən sonra worker yenidən başlayır
Bu memory leak-lərin qarşısını alır
```

---

## CLI Proses Modeli

```
CLI skriptlər uzun müddət işləyə bilər (server-kimi davranır).

Web (FPM):                CLI:
┌──────────────────┐      ┌──────────────────────────────┐
│ Request başlar   │      │ Proses başlayır              │
│ PHP yüklənir     │      │ PHP yüklənir                 │
│ Script işləyir   │      │ Script işləyir               │
│ Response göndər  │      │ ...əmrsiz davam edir...      │
│ Memory sıfırla   │      │ Memory artır (leak riski!)   │
│ Gözlə           │      │ Siqnal alana kimi işləyir    │
└──────────────────┘      └──────────────────────────────┘

CLI-da diqqət edilməlidir:
  - Memory leak-lər toplanır
  - gc_collect_cycles() əl ilə çağırmaq lazım ola bilər
  - Siqnal handling (SIGTERM, SIGINT) implement edilməlidir
  - Böyük data set-lərini generator ilə işlə
```

---

## Async PHP Modelləri

```
Ənənəvi PHP:       Swoole/ReactPHP:        Fibers (PHP 8.1):
┌────────────┐     ┌─────────────────┐     ┌──────────────────┐
│  Process 1 │     │  Event Loop     │     │  Fiber 1  ──────►│
│  request 1 │     │  ┌───────────┐  │     │  Fiber 2  ──────►│
│  (blocking)│     │  │ Coroutine │  │     │  Fiber 3  ──────►│
└────────────┘     │  │ Coroutine │  │     │  (cooperative)   │
┌────────────┐     │  │ Coroutine │  │     └──────────────────┘
│  Process 2 │     │  └───────────┘  │
│  request 2 │     │  Non-blocking   │
│  (blocking)│     │  I/O            │
└────────────┘     └─────────────────┘

FPM model:
  - Hər request ayrı proses/thread
  - Sadə, izolasiyalı
  - I/O-da bloklanır

Swoole model:
  - Bir proses çox request
  - Event loop, non-blocking I/O
  - Swoole shared memory ilə state paylaşır
  - FPM-dən fərqli: state request-lər arası qalır!
  
Fibers (PHP 8.1):
  - Cooperative multitasking
  - Fiber.suspend() ilə yield edir
  - Event loop üçün building block
```

---

## Memory İzolyasiyası

```
FPM-də memory izolyasiyası:

Request A Worker:        Request B Worker:
┌──────────────────┐    ┌──────────────────┐
│ $user = new User │    │ $user = new User │
│ Heap: 0x1234     │    │ Heap: 0x1234     │ ← eyni ünvan
│ (ayrı proses     │    │ (ayrı proses     │   amma fərqli
│  ünvan fəzası)   │    │  ünvan fəzası)   │   virtual memory
└──────────────────┘    └──────────────────┘
         │                       │
         └──────────┬────────────┘
                    ▼
           OPcache (shared)
           ┌────────────────────┐
           │ Compiled bytecode  │
           │ (read-only)        │
           └────────────────────┘

Paylaşılan:       Paylaşılmayan:
  OPcache           PHP variables
  Kernel code       Heap objects
  Shared libraries  Stack
                    Request data
```

---

## PHP İmplementasiyası

```php
<?php
// Shared-nothing-u sübut edən nümunə
// global state-in request-lər arası paylaşılmadığını göstərir

// Bu dəyər hər request-də sıfırdan başlayır
// FPM-də request-lər arası qalmır
$counter = 0;
$counter++;

// APCu ilə paylaşmaq mümkündür (shared memory)
apcu_inc('global_counter'); // ← bu request-lər arası qalır

// OPcache-də yalnız compiled bytecode paylaşılır
// Runtime dəyişənlər paylaşılmır
```

```php
<?php
// CLI process — memory monitoring
function getMemoryUsageMB(): float
{
    return round(memory_get_usage(true) / 1024 / 1024, 2);
}

$iteration = 0;
while (true) {
    $iteration++;

    // Hər 1000 iterasiyada memory yoxla
    if ($iteration % 1000 === 0) {
        $memory = getMemoryUsageMB();
        echo "Iteration: {$iteration}, Memory: {$memory} MB\n";

        // Memory leak varsa xəbərdarlıq
        if ($memory > 100) {
            echo "WARNING: Memory leak detected!\n";
            gc_collect_cycles(); // garbage collection çağır
        }
    }

    // Böyük obyektləri unset et
    $data = processNextBatch();
    unset($data); // əl ilə boşalt

    usleep(10000); // 10ms
}
```

```php
<?php
// PHP Fiber nümunəsi — cooperative multitasking
$fiber1 = new Fiber(function(): void {
    echo "Fiber 1: başladı\n";
    Fiber::suspend(); // yield — digərinə ver
    echo "Fiber 1: davam edir\n";
    Fiber::suspend();
    echo "Fiber 1: tamamlandı\n";
});

$fiber2 = new Fiber(function(): void {
    echo "Fiber 2: başladı\n";
    Fiber::suspend();
    echo "Fiber 2: tamamlandı\n";
});

$fiber1->start();
$fiber2->start();
$fiber1->resume();
$fiber2->resume();
$fiber1->resume();

// Çıxış:
// Fiber 1: başladı
// Fiber 2: başladı
// Fiber 1: davam edir
// Fiber 2: tamamlandı
// Fiber 1: tamamlandı
```

---

## İntervyu Sualları

- PHP-nin "shared-nothing" modeli nə deməkdir? Faydaları nədir?
- FPM worker-ı nə vaxt yenidən başlayır? `pm.max_requests` niyə vacibdir?
- CLI PHP prosesini FPM worker-dan fərqləndirən əsas cəhət nədir?
- Swoole-da request-lər arası state qalır — bu FPM-dən nə ilə fərqlənir?
- PHP Fiber-ları nədir? OS thread-lərindən fərqi nədir?
- OPcache worker-lar arasında necə paylaşılır? Race condition riski varmı?
- Memory leak-i CLI prosesdə necə aşkarlayarsınız?
