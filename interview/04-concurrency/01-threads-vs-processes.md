# Threads vs Processes (Middle ⭐⭐)

## İcmal
Process — əməliyyat sisteminin ayrı memory space-ə sahib müstəqil icra vahididir. Thread — eyni process içindəki, shared memory-yə malik yüngül icra vahididir. Bu fərq concurrency dizaynının əsasında dayanır. Hər backend developer bu mövzunu bilməlidir, çünki hansı model istifadə etdiyiniz performance-ı, izolasiyani, communication xərclərini müəyyən edir.

## Niyə Vacibdir
PHP-FPM process model, Node.js single-threaded, Go goroutines, Java threads — hər texnologiyanın concurrency modeli vardır. İnterviewer bu sualla sizin seçdiyiniz texnologiyanın niyə belə davrandığını, resource izolasiyasını, IPC mexanizmlərini bildiyinizi yoxlayır.

---

## Əsas Anlayışlar

- **Process:** Özünün virtual address space-i, file descriptor-ları, resource-ları var; digər process-lərlə memory paylaşmır
- **Thread:** Eyni process-in memory-sini paylaşır — heap, global variables, file descriptors
- **Stack:** Hər thread-in özünün stack-ı var — local variables, function call frames; OS thread ~1MB stack
- **Context Switch (Process):** CPU-nun bir process-dən digərinə keçməsi — MMU (Memory Management Unit) cədvəlini dəyişmək lazımdır, baha əməliyyat
- **Context Switch (Thread):** Eyni address space-də — daha ucuz; yalnız register-lər, stack pointer dəyişdirilir
- **Process Creation:** `fork()` system call — copy-on-write ilə optimallaşır; yeni memory map lazımdır
- **Thread Creation:** `pthread_create()` / Java `new Thread()` — ucuz; eyni memory paylaşılır
- **IPC (Inter-Process Communication):** Pipe, socket, shared memory, message queue, signal — process-lərarası ünsiyyət; thread-lər üçün lazım deyil (shared heap var)
- **Race Condition:** Shared memory-yə concurrent access nəticəsindəki data corruption — thread-lərdə daha böyük risk
- **Isolation:** Process crash bütün digər process-ləri etkiləmir; thread crash bütün process-i öldürür
- **GIL (Global Interpreter Lock):** CPython-da — bir anda yalnız bir thread Python bytecode-unu icra edə bilər; CPU-bound task-larda thread-lər faktiki olaraq parallellənmir
- **PHP-FPM Model:** Hər HTTP request üçün ayrı process — tam izolasiya, lakin hər process ≈ 20–50 MB RAM
- **Apache prefork vs mpm_worker:** prefork hər connection üçün process; mpm_worker hər connection üçün thread — ikincisi daha az memory
- **Nginx:** Async, event-driven — az sayda worker process, non-blocking I/O; minlərlə connection bir process-də
- **Fork-exec Model:** `fork()` ilə child process yarat, `exec()` ilə yeni proqram başlat — shell komandaları belə işləyir
- **Copy-on-Write (CoW):** Fork sonrası parent-child eyni physical memory page-ləri paylaşır; bir tərəf dəyişiklik etdikdə yeni page ayrılır — PHP-FPM bu yolu ilə böyük application-ları effektiv fork edir
- **User-space vs Kernel-space Threads:** OS thread-lər kernel idarə edir (1:1 model); Go goroutine-lər, Java virtual thread-lər user-space-də idarə olunur (M:N model) — OS-ə yük azalır
- **Memory Overhead:** OS thread ≈ 1 MB stack; Go goroutine ≈ 2–8 KB initial stack (dinamik böyüyür); Java virtual thread ≈ az heap
- **Daemon Thread:** JVM-də main thread bitdikdə daemon thread-lər də dayanır — background task-lar üçün
- **Process Group, Session:** Unix-də process-lər qruplara aid olur; signal-lar qrupa göndərilə bilər (Nginx reload: `kill -HUP <master_pid>`)

---

## Praktik Baxış

**Interview-da yanaşma:**
- "PHP niyə process-based?" — izolasiya, shared-nothing dizayn; dezavantaj: yüksək memory overhead
- "Thread niyə race condition yaradır?" — shared memory; synchronized access lazımdır
- Real texnologiya müqayisəsi verin: PHP-FPM vs Node.js event loop vs Go goroutine

**Follow-up suallar:**
1. "Node.js single-threaded-dir, necə concurrent request idarə edir?" — Event loop, non-blocking I/O, libuv
2. "Python-da CPU-bound task üçün nə istifadə edərsiniz?" — `multiprocessing` modulu; GIL-dən qaçmaq üçün
3. "Go-da goroutine OS thread ilə eyni şeydirmi?" — Xeyr; M:N model, user-space scheduler, work-stealing
4. "PHP-FPM worker sayını artırmaq niyə həmişə kömək etmir?" — Memory tükənir, swap istifadəsi arta bilər
5. "Java virtual thread (Project Loom) platform thread-dən nə ilə fərqlənir?" — Virtual thread JVM idarə edir, blocking I/O zamanı platform thread-i boşaltır
6. "Process izolasiyası nə zaman thread-dən üstündür?" — Browser tab (Chrome), microservice worker crash toleration, PHP-FPM

**Code review red flags:**
- Thread-lərdə `synchronized` olmadan shared mutable state
- Java-da `new Thread()` birbaşa istifadəsi — thread pool əvəzinə
- PHP Octane-da static property-ə request-specific data yazmaq
- Python-da CPU-bound task üçün `threading.Thread` — GIL problem olacaq

**Production debugging ssenariləri:**
- PHP-FPM `pm.max_children` dolub — nəticə: 502 Bad Gateway; həll: `pm.status_path` + monitoring
- Java process "zombie" thread-ləri — `jstack` ilə thread dump analizi
- Node.js-də CPU-intensive loop — event loop latensi artır; `clinic.js` ilə diagnoz
- Swoole/Octane-da memory leak — hər request prosesi sıfırlanmır; `php artisan octane:reload`

---

## Nümunələr

### Tipik Interview Sualı
"PHP-FPM worker process model istifadə edir. Bu niyədir? Hansı trade-off-ları var?"

### Güclü Cavab
PHP-FPM hər request üçün ayrı process işlədir. Bunun əsas səbəbi PHP-nin shared-nothing dizaynıdır: hər request öz memory space-inə sahib olur, request bitdikdə avtomatik təmizlənir. Memory leak-lər yalnız o request-i etkiliər, digərini yox. Dezavantaj: hər process ≈ 20–50 MB; 100 worker = 2–5 GB RAM. Nginx kimi event-driven sistemlərlə müqayisədə çox resource-intensivdir. Alternatif: Laravel Octane ilə Swoole/RoadRunner — PHP process-i request-lər arasında saxlayır, reinitialization yoxdur. Lakin bu shared state problemlərini geri gətirir: singleton-lar, static property-lər request-lərarası sıza bilər.

### Kod Nümunəsi

```python
# Python: Thread vs Process — GIL effektinin ölçülməsi
import threading
import multiprocessing
import time

def cpu_bound(n):
    """Sırf CPU hesablaması — GIL-i test edir"""
    count = 0
    while count < n:
        count += 1

N = 10_000_000

# 1) Serialdə
start = time.time()
cpu_bound(N)
cpu_bound(N)
serial_time = time.time() - start

# 2) Thread ilə — GIL səbəbindən faktiki paralel deyil
start = time.time()
t1 = threading.Thread(target=cpu_bound, args=(N,))
t2 = threading.Thread(target=cpu_bound, args=(N,))
t1.start(); t2.start()
t1.join();  t2.join()
thread_time = time.time() - start

# 3) Process ilə — GIL yoxdur, həqiqi paralel
start = time.time()
p1 = multiprocessing.Process(target=cpu_bound, args=(N,))
p2 = multiprocessing.Process(target=cpu_bound, args=(N,))
p1.start(); p2.start()
p1.join();  p2.join()
process_time = time.time() - start

print(f"Serial:  {serial_time:.2f}s")
print(f"Thread:  {thread_time:.2f}s  (GIL: serialdən az sürətli ola bilər!)")
print(f"Process: {process_time:.2f}s  (Demək olar ki yarısı)")

# I/O-bound üçün thread yaxşıdır — GIL I/O gözlərkən buraxılır
import urllib.request

def fetch(url):
    urllib.request.urlopen(url).read()

urls = ["http://httpbin.org/delay/1"] * 4

start = time.time()
with multiprocessing.pool.ThreadPool(4) as pool:
    pool.map(fetch, urls)
print(f"Thread I/O: {time.time() - start:.2f}s")  # ~1s, paralel gözləyir
```

```java
// Java: Thread — shared memory, race condition nümunəsi
public class SharedMemoryDemo {
    // SHARED mutable state — bütün thread-lər görür
    static int sharedCounter = 0;

    public static void main(String[] args) throws InterruptedException {
        Thread t1 = new Thread(() -> {
            for (int i = 0; i < 100_000; i++) sharedCounter++; // NOT atomic!
        });
        Thread t2 = new Thread(() -> {
            for (int i = 0; i < 100_000; i++) sharedCounter++; // NOT atomic!
        });

        t1.start(); t2.start();
        t1.join();  t2.join();

        // 200000 gözlənilir, amma 150000-180000 arası gəlir
        System.out.println("Result: " + sharedCounter + " (expected 200000)");

        // Fix: AtomicInteger
        java.util.concurrent.atomic.AtomicInteger atomic = new java.util.concurrent.atomic.AtomicInteger(0);
        Thread t3 = new Thread(() -> { for (int i = 0; i < 100_000; i++) atomic.incrementAndGet(); });
        Thread t4 = new Thread(() -> { for (int i = 0; i < 100_000; i++) atomic.incrementAndGet(); });
        t3.start(); t4.start();
        t3.join();  t4.join();
        System.out.println("Atomic: " + atomic.get()); // Həmişə 200000
    }
}
```

```go
// Go: Goroutine — user-space M:N model
package main

import (
    "fmt"
    "runtime"
    "sync"
)

func main() {
    fmt.Printf("CPU cores: %d\n", runtime.NumCPU())
    fmt.Printf("OS threads (GOMAXPROCS): %d\n", runtime.GOMAXPROCS(0))

    var wg sync.WaitGroup

    // 10.000 goroutine — yalnız bir neçə OS thread istifadə edir
    for i := 0; i < 10_000; i++ {
        wg.Add(1)
        go func(n int) {
            defer wg.Done()
            // I/O simulate: goroutine yields, OS thread başqasını icra edir
            // time.Sleep(time.Millisecond) — burada goroutine bloklanır,
            // Go runtime həmin OS thread-i başqa goroutine-ə verir
            _ = n * n
        }(i)
    }

    wg.Wait()
    fmt.Printf("Goroutines now: %d\n", runtime.NumGoroutine()) // ~1
    // 10.000 goroutine ≈ 20–80 MB (hər biri ~2KB initial stack)
    // 10.000 OS thread ≈ 10 GB (hər biri ~1MB stack)
}
```

### Yanlış Kod + Düzgün Kod

```php
// YANLIŞ: Octane-da static property-yə request data yazmaq
class CurrentUser
{
    private static ?User $user = null;

    public static function set(User $user): void
    {
        self::$user = $user; // ← Octane-da process restart olmur!
        // Request 1: user = Alice
        // Request 2: eyni process, self::$user hələ də Alice!
    }

    public static function get(): ?User
    {
        return self::$user;
    }
}

// DÜZGÜN: Request-scoped container binding istifadə et
// AppServiceProvider-da:
$this->app->scoped(CurrentUserService::class, function () {
    return new CurrentUserService(); // Hər request üçün yeni instance
});

// Ya da Laravel-in Request object-ini istifadə et
class CurrentUserService
{
    public function __construct(private Request $request) {}

    public function get(): ?User
    {
        return $this->request->user(); // Request-ə bağlıdır, leak yoxdur
    }
}
```

```java
// YANLIŞ: Thread-in daxilindən shared list-ə lock olmadan yazmaq
import java.util.ArrayList;
import java.util.List;

public class SharedListProblem {
    private static final List<Integer> results = new ArrayList<>(); // NOT thread-safe

    public static void main(String[] args) throws InterruptedException {
        Thread t1 = new Thread(() -> {
            for (int i = 0; i < 1000; i++) results.add(i); // ConcurrentModificationException risk!
        });
        Thread t2 = new Thread(() -> {
            for (int i = 1000; i < 2000; i++) results.add(i);
        });
        t1.start(); t2.start();
        t1.join();  t2.join();
        System.out.println("Size: " + results.size()); // 2000-dən az ola bilər, exception da mümkün
    }
}

// DÜZGÜN 1: CopyOnWriteArrayList — read-heavy
import java.util.concurrent.CopyOnWriteArrayList;
private static final List<Integer> safeList = new CopyOnWriteArrayList<>();

// DÜZGÜN 2: Collections.synchronizedList
private static final List<Integer> syncList = java.util.Collections.synchronizedList(new ArrayList<>());

// DÜZGÜN 3: ConcurrentLinkedQueue — yüksək concurrent write
import java.util.concurrent.ConcurrentLinkedQueue;
private static final java.util.Queue<Integer> queue = new ConcurrentLinkedQueue<>();
```

```go
// YANLIŞ: Goroutine-lər arasında map-ə concurrent write
package main

import "sync"

func badMap() {
    m := make(map[string]int) // NOT concurrent-safe!
    var wg sync.WaitGroup

    for i := 0; i < 100; i++ {
        wg.Add(1)
        go func(n int) {
            defer wg.Done()
            m[fmt.Sprintf("key%d", n)] = n // RACE CONDITION — concurrent map write!
            // go run -race main.go → "concurrent map writes" fatal error
        }(i)
    }
    wg.Wait()
}

// DÜZGÜN: sync.Map — concurrent-safe map
func goodMap() {
    var m sync.Map
    var wg sync.WaitGroup

    for i := 0; i < 100; i++ {
        wg.Add(1)
        go func(n int) {
            defer wg.Done()
            m.Store(fmt.Sprintf("key%d", n), n)
        }(i)
    }
    wg.Wait()

    m.Range(func(k, v any) bool {
        fmt.Printf("%v: %v\n", k, v)
        return true
    })
}
```

---

## Praktik Tapşırıqlar

- Python-da CPU-bound task üçün Thread vs Process benchmark edin; GIL effektini ölçün
- PHP-FPM `pm.max_children` dəyişdirin, memory istifadəsini `htop` ilə izləyin
- Java-da `synchronized` olmadan counter artırın, race condition reproduce edin, sonra `AtomicInteger` ilə düzəldin
- Go-da 100.000 goroutine çalışdırın, `runtime.NumGoroutine()` və `runtime.NumCPU()` fərqini görün
- `ps aux | grep php-fpm` ilə PHP-FPM process-lərini sayın; Node.js ilə müqayisə edin
- Java-da `ArrayList` vs `CopyOnWriteArrayList` vs `ConcurrentLinkedQueue` concurrent write benchmark edin
- PHP Octane-da static property leak-ini simulate edin: iki ardıcıl request-də state-in sızdığını görün

## Əlaqəli Mövzular
- `02-race-conditions.md` — Shared memory ilə gələn problem
- `03-mutex-semaphore.md` — Thread synchronization primitiv-ləri
- `05-thread-pools.md` — Thread idarəetmə strategiyaları
- `07-event-loop.md` — Single-threaded concurrent model
