# Green Threads / Goroutines / Fibers (Senior ⭐⭐⭐)

## İcmal
Green thread (yaxud fiber, goroutine, virtual thread) — OS tərəfindən deyil, runtime/user-space tərəfindən schedule edilən yüngül icra vahididir. Bir OS thread üzərində minlərlə green thread çalışa bilər. Go goroutine-ləri, Java 21 Virtual Thread-ləri, PHP Fiber-ləri, Python coroutine-lər — hamısı bu ideyanın müxtəlif implementasiyasıdır. Senior interview-larda "niyə goroutine OS thread-dən ucuzdur?" sualı klassikdir.

## Niyə Vacibdir
PHP developer olaraq Laravel Octane (Swoole/RoadRunner), PHP 8.1 Fiber, async framework-lərə keçid üçün bu anlayış kritikdir. İnterviewer bu sualla sizin M:N threading modelini, cooperative vs preemptive scheduling-i, stack growth-u, goroutine-in OS thread-dən fərqini bildiyinizi yoxlayır. Java 21 Virtual Thread-ləri "thread-per-request" modelinin scalability probleminini həll edir — bu bilik müasir backend developer üçün vacibdir.

## Əsas Anlayışlar

- **Green Thread:** User-space-də manage edilən thread — OS kernel-dən xəbərsiz
- **M:N Model:** M green thread → N OS thread — Go, Java 21, Kotlin Coroutine
- **1:1 Model:** Hər thread → OS thread — Java (< 21), C++ `std::thread`
- **N:1 Model:** Bütün green thread-lər tək OS thread — Python asyncio (GIL), Node.js
- **Cooperative Scheduling:** Thread özü yield edir — eski green thread, Python coroutine
- **Preemptive Scheduling:** Runtime thread-i zorla dayandırır — Go runtime (Go 1.14+), Java Virtual Threads
- **Goroutine (Go):** ~2KB başlanğıc stack, growable; Go runtime M:N scheduler (work-stealing)
- **Virtual Thread (Java 21):** `Thread.ofVirtual().start(...)` — Platform thread pool üzərində schedule
- **Fiber (PHP 8.1):** Cooperative, user-space coroutine — Swoole/ReactPHP üçün primitiv
- **Coroutine:** Ümumi anlayış — suspend/resume edilə bilən icra vahidi
- **Stack Growth:** Go goroutine başda 2KB, lazım olduqda böyüyür (max ~1GB); OS thread stack 1-8MB
- **Work Stealing:** Scheduler — boş thread digərinin iş növbəsini götürür; Go runtime default
- **Goroutine Leak:** Channel-dan heç vaxt cavab gəlmir — goroutine gözləməyə davam edir; memory leak
- **`GOMAXPROCS`:** Go-nun OS thread sayı — default CPU sayı qədər
- **Continuation:** Suspend nöqtəsindən davam etmək — coroutine-in əsasıdır
- **Structured Concurrency:** Go `errgroup`, Java `StructuredTaskScope` — goroutine/fiber lifecycle idarəetmə

## Praktik Baxış

**Interview-da yanaşma:**
- "Goroutine niyə thread-dən ucuzdur?" — Kiçik stack (2KB vs 1MB), user-space scheduling, az context switch
- M:N modelini çizin — Go: N goroutine, M OS thread, work stealing
- PHP Fiber-i soruşsalar: async framework-lərin primitivi, özü scheduler deyil

**Follow-up suallar:**
- "1 million goroutine mümkündürmü?" — Bəli, ~2GB RAM; 1 million OS thread → ~8TB
- "Java Virtual Thread vs goroutine fərqi?" — Virtual Thread platform thread pool-a pin olur; goroutine work-stealing scheduler
- "Goroutine leak nədir? Necə detect edilir?" — `runtime.NumGoroutine()`, pprof goroutine dump

**Ümumi səhvlər:**
- "Goroutine = thread" demək — stack size, OS visibility fərqləri var
- Goroutine leak-ı bilməmək — production bug-ı
- PHP Fiber-in cooperative olduğunu qeyd etməmək — caller `resume()` etməlidir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Work-stealing scheduling-i izah etmək
- Goroutine leak ssenarisi: closed channel vs goroutine gözləyir
- Java 21 Virtual Thread-lərin "thread-per-request" modeli necə scalable etdiyini izah etmək

## Nümunələr

### Tipik Interview Sualı
"Go 1 million goroutine dəstəkləyə bilər — niyə 1 million OS thread mümkün deyil? Goroutine-in daxili mexanizmi nədir?"

### Güclü Cavab
OS thread yaratmaq baha əməliyyatdır: kernel struct (~8KB), default stack (~1-8MB), OS scheduling — 1 million thread yüzlərlə GB RAM tələb edir. Goroutine user-space-dir: runtime struct, 2KB başlanğıc stack (lazım olduqda böyüyür), Go scheduler tərəfindən idarə edilir. Go M:N model: N goroutine, M OS thread — default olaraq CPU sayı qədər. Work-stealing scheduler: boş P (processor) digər P-nin run queue-sundan goroutine götürür — CPU-lar bərabər yüklənir. Goroutine I/O-da yield edir: OS thread bloklanmır, scheduler başqa goroutine-i icra edir. Nəticə: 1 million goroutine ≈ 2-4GB RAM; 1 million OS thread ≈ 8TB. Amma: goroutine leak-dan ehtiyatlı olun — channel gözləyən goroutine heç vaxt tamamlanmır.

### Kod Nümunəsi
```go
// Go: Goroutine əsasları + leak detection
package main

import (
    "context"
    "fmt"
    "runtime"
    "sync"
    "time"

    "golang.org/x/sync/errgroup"
)

// Goroutine leak nümunəsi
func goroutineLeak() {
    ch := make(chan int) // Unbuffered — heç kim göndərmirsə goroutine bloklanır

    go func() {
        val := <-ch // Heç vaxt gəlmirsə — leak!
        fmt.Println(val)
    }()

    // ch-ə heç vaxt göndərilmir — goroutine "leak" oldu
    fmt.Printf("Goroutines: %d\n", runtime.NumGoroutine()) // Artır
}

// Düzgün: Context ilə goroutine lifecycle
func withContext(ctx context.Context) {
    ch := make(chan int, 1)

    go func() {
        select {
        case val := <-ch:
            fmt.Println("Got:", val)
        case <-ctx.Done():
            fmt.Println("Cancelled — goroutine exits cleanly")
            return // Goroutine temizlənir
        }
    }()

    time.Sleep(10 * time.Millisecond)
    // context cancel → goroutine çıxır
}

// errgroup — structured concurrency
func fetchAll(urls []string) error {
    g, ctx := errgroup.WithContext(context.Background())

    results := make([]string, len(urls))

    for i, url := range urls {
        i, url := i, url // Loop variable capture
        g.Go(func() error {
            result, err := fetch(ctx, url)
            if err != nil {
                return fmt.Errorf("fetch %s: %w", url, err)
            }
            results[i] = result
            return nil
        })
    }

    if err := g.Wait(); err != nil {
        return err // Bir uğursuz → digərləri cancel olunur
    }

    for _, r := range results {
        fmt.Println(r)
    }
    return nil
}

func fetch(ctx context.Context, url string) (string, error) {
    // Simulasiya
    time.Sleep(10 * time.Millisecond)
    return "result:" + url, nil
}

// 1 million goroutine
func millionGoroutines() {
    var wg sync.WaitGroup
    start := time.Now()

    for i := 0; i < 1_000_000; i++ {
        wg.Add(1)
        go func(n int) {
            defer wg.Done()
            // Minimal iş
            _ = n * n
        }(i)
    }

    wg.Wait()
    fmt.Printf("1M goroutines: %v\n", time.Since(start))
    fmt.Printf("Memory: check with pprof\n")
    // Tipik: < 2 saniyə, ~2-4GB RAM
}

func main() {
    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()

    fmt.Printf("CPUs: %d, GOMAXPROCS: %d\n", runtime.NumCPU(), runtime.GOMAXPROCS(0))
    withContext(ctx)
    millionGoroutines()
}
```

```java
// Java 21: Virtual Threads
import java.util.concurrent.*;

public class VirtualThreadsDemo {

    public static void main(String[] args) throws Exception {
        // Platform (OS) thread — baha, limited sayda
        Thread platformThread = Thread.ofPlatform().start(() -> {
            System.out.println("Platform thread: " + Thread.currentThread().isVirtual()); // false
        });

        // Virtual Thread — ucuz, milyonlar
        Thread virtualThread = Thread.ofVirtual().start(() -> {
            System.out.println("Virtual thread: " + Thread.currentThread().isVirtual()); // true
        });

        // Thread-per-request — scalable (Virtual Thread ilə)
        try (var executor = Executors.newVirtualThreadPerTaskExecutor()) {
            for (int i = 0; i < 100_000; i++) {
                int taskId = i;
                executor.submit(() -> {
                    simulateIO(taskId);
                });
            }
        } // AutoCloseable — bütün task-lar gözlənilir

        // Structured Concurrency (Java 21 preview)
        try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {
            Future<String> user   = scope.fork(() -> fetchUser(1L));
            Future<String> order  = scope.fork(() -> fetchOrder(1L));

            scope.join().throwIfFailed(); // Hər ikisi bitir və ya fail olunca çıx

            System.out.println(user.resultNow() + " " + order.resultNow());
        }
    }

    static void simulateIO(int id) {
        try {
            Thread.sleep(10); // Virtual Thread-də: platform thread block olmur
            // JVM blocking I/O (JDBC, socket) virtual thread-i unmount edir
            // Platform thread başqa virtual thread icra edir
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }

    static String fetchUser(long id) throws InterruptedException {
        Thread.sleep(50); // Simulate DB query
        return "User-" + id;
    }

    static String fetchOrder(long id) throws InterruptedException {
        Thread.sleep(30); // Simulate API call
        return "Order-" + id;
    }
}
```

```php
// PHP 8.1: Fiber — cooperative coroutine
<?php

// Fiber — cooperative, user-space — özü schedule etmir
$fiber = new Fiber(function(): string {
    echo "Fiber starts\n";

    $value = Fiber::suspend('first suspension'); // Caller-a qayıt
    echo "Resumed with: $value\n";

    $value2 = Fiber::suspend('second suspension');
    echo "Resumed again with: $value2\n";

    return 'fiber done';
});

// Fiber-i başlat
$result1 = $fiber->start();     // 'first suspension'
echo "Got: $result1\n";

// Fiber-i davam etdir
$result2 = $fiber->resume('hello');   // 'second suspension'
echo "Got: $result2\n";

$result3 = $fiber->resume('world');   // null — fiber bitdi
echo "Fiber return: " . $fiber->getReturn() . "\n"; // 'fiber done'

// Swoole / ReactPHP Fiber-i callback hell əvəzinə istifadə edir
// Laravel Octane + Swoole: hər request fiber kimi işlənir
// Sync görünən kod, async icra olunur

// Async HTTP client (Guzzle Promises əsasında)
// use GuzzleHttp\Client;
// use GuzzleHttp\Promise;
//
// $client = new Client();
// $promises = [
//     'user'  => $client->getAsync('/user/1'),
//     'order' => $client->getAsync('/order/1'),
// ];
// $results = Promise\Utils::unwrap($promises); // Paralel!
```

## Praktik Tapşırıqlar

- Go-da goroutine leak reproduce edin, `runtime.NumGoroutine()` ilə sayı izləyin, context ilə həll edin
- Java 21 Virtual Thread ilə 100.000 thread yaradın; platform thread ilə müqayisə edin — RAM fərqini ölçün
- PHP Fiber ilə sadə generator yazın: Fibonacci seriyası suspend/resume ilə
- Go `pprof` goroutine dump alın: `go tool pprof http://localhost:6060/debug/pprof/goroutine`
- errgroup ilə fan-out pattern: 10 URL-i paralel fetch edin, birisi fail olarsa digərləri cancel olsun

## Əlaqəli Mövzular
- `01-threads-vs-processes.md` — OS thread vs green thread müqayisəsi
- `06-async-await.md` — Coroutine-in abstraction qatı
- `07-event-loop.md` — Event loop vs goroutine scheduler
- `14-reactive-programming.md` — Reactive stream goroutine/fiber ilə
