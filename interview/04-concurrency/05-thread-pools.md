# Thread Pools (Senior ⭐⭐⭐)

## İcmal
Thread pool — əvvəlcədən yaradılmış thread-lərin bir havuzu olub, task-ları bir-bir icra edir. Hər task üçün yeni thread yaratmaq baha — thread pool thread yaratma overhead-ini aradan qaldırır, thread sayını idarə edir, back-pressure mexanizmi əlavə edir. Bu mövzu Senior interview-larda server-side concurrency dizaynının əsasıdır.

## Niyə Vacibdir
Yanlış thread pool konfiqurasiyası ya resource starvation-a (çox az thread), ya OutOfMemoryError-a (çox thread), ya da pool exhaustion deadlock-a gətirib çıxarır. İnterviewer sizin pool sizing formula-larını, rejection policy-ləri, task type-larına görə pool ayrımını (Bulkhead) bildiyinizi yoxlayır.

---

## Əsas Anlayışlar

- **Core Pool Size:** Daima canlı minimum thread sayı — idle task olmasa belə bu thread-lər saxlanır
- **Max Pool Size:** Əlavə thread-lər yaratmağın üst həddi — core + burst thread-lər daxil
- **Task Queue:** Core thread-lər dolu olduqda task-lar queue-ya gəlir; `LinkedBlockingQueue` (unbounded!), `ArrayBlockingQueue` (bounded)
- **Queue Overflow → Yeni Thread:** Bounded queue dolduqda yeni thread yaradılır, max-a çatana qədər
- **Rejection Policy (max thread + queue dolu olduqda):**
  - `AbortPolicy`: `RejectedExecutionException` — default; client xəbərdar olur
  - `CallerRunsPolicy`: Task-ı göndərən thread özü icra edir — back-pressure mexanizmi; acceptor thread yavaşlayır
  - `DiscardPolicy`: Yeni task-ı səsiz at — data loss riski
  - `DiscardOldestPolicy`: Queue-dakı ən köhnə task-ı at, yenisini qoy
- **Keep-Alive Time:** Core-dan artıq idle thread-lər bu müddətdən sonra terminate olunur; core thread-lər default saxlanır
- **CPU-bound Tasks:** Hesablama işi — optimal pool size: `N+1` (N = CPU core sayı); +1 page fault kimi tıxaclar üçün buffer
- **I/O-bound Tasks:** I/O gözləmə — optimal: `N × (1 + wait_time / compute_time)`; I/O zamanı thread yatır, başqa task ala bilər
- **ForkJoinPool:** Work-stealing — boş thread-lər dolu thread-lərin queue-sundan task oğurlayır; rekursiv task-lar, parallel streams üçün
- **Virtual Thread Pool (Java 21+):** JVM-level user threads — millions simultaneous; I/O-bound workload-da platform thread-dən çox üstün
- **Thread Starvation Deadlock:** Pool thread-lər bir-birini gözləyirsə — outer task inner task-ı pool-dan istəyir, amma pool outer task-lara dolub → deadlock
- **Bulkhead Pattern:** Fərqli iş tipi üçün fərqli pool — payment pool dolu olsa belə notification pool təsirlənmir
- **Thread Factory:** Thread-lərə ad vermək (`worker-1`, `payment-3`), daemon flag, exception handler təyin etmək
- **Metrics:** `getActiveCount()`, `getQueue().size()`, `getCompletedTaskCount()`, `getLargestPoolSize()` — pool health monitoring
- **Executor Framework:** Java-nın `ExecutorService` interface-i — `submit()` Future qaytarır; `execute()` void
- **CompletableFuture + Pool:** `CompletableFuture.supplyAsync(task, executor)` — custom pool-da async
- **Graceful Shutdown:** `executor.shutdown()` + `awaitTermination()` — mövcud task-ları tamamla, yeni task qəbul etmə; `shutdownNow()` daha sert
- **Go Worker Pool:** Channel-based — `jobs chan func()` + N goroutine; buffered channel queue rolunu oynayır

---

## Praktik Baxış

**Interview-da yanaşma:**
- Pool sizing formula-larını bilmək: CPU-bound → `N+1`; I/O-bound → `N × (1 + W/C)` — W wait time, C compute time
- Rejection policy soruşulduqda `CallerRunsPolicy`-nin back-pressure effektini izah edin
- Thread pool exhaustion deadlock-u konkret nümunə ilə göstərin

**Follow-up suallar:**
1. "Java 21 virtual thread-lər thread pool-u əvəz edirmi?" — I/O-bound workload üçün bəli; CPU-bound üçün xeyr (hələ platform thread lazımdır)
2. "Thread pool exhaustion deadlock necə baş verir?" — Outer task inner task gözləyir; pool outer task-lara dolub; inner task heç vaxt başlamır
3. "`CallerRunsPolicy` niyə back-pressure-dır?" — Göndərən thread (HTTP acceptor) bloklanır; yeni request qəbul etmə yavaşlayır; upstream client-lər tıxac görür
4. "Unbounded `LinkedBlockingQueue` problemi nədir?" — OutOfMemoryError; task-lar toplanır, latency artır; back-pressure yoxdur
5. "ForkJoinPool nə zaman regular pool-dan üstündür?" — Rekursiv, divide-and-conquer task-lar; work-stealing idle core-ları istifadə edir; parallel stream default-da bu pool-u istifadə edir
6. "Bulkhead pattern-i niyə tətbiq edirsiniz?" — Bir servisin yavaşlaması bütün thread pool-u tutaraq kritik path-i bloklamasın deyə

**Code review red flags:**
- `Executors.newFixedThreadPool(100)` — unbounded queue; backpressure yoxdur; OOM riski
- `Executors.newCachedThreadPool()` — unbounded thread-lər; burst-da sistemə zarar verər
- Pool-da inner pool task-ı gözləmək — exhaustion deadlock
- Thread pool-u `static` field-də `shutdown()` çağırmadan qurmaq — JVM exit-dən sonra da işləyər

**Production debugging ssenariləri:**
- API latency artır: `getQueue().size()` böyüyür — ya pool çox kiçik, ya I/O çox yavaş
- `RejectedExecutionException` rain: pool max-a çatıb, queue dolub — rejection policy yanlış; CallerRunsPolicy ya da queue artırılmalı
- OOM `LinkedBlockingQueue`-da: 50.000 task queue-da toplanıb; bounded queue + rejection policy gərəkir
- Thread pool exhaustion deadlock: `jstack`-də outer task-lar `pool.submit()` gözləyir, inner task-lar heç vaxt başlamır

---

## Nümunələr

### Tipik Interview Sualı
"Web server-iniz hər HTTP request üçün thread pool-dan thread alır. Ani 10.000 request gəldikdə pool dolur. Nə baş verir, necə həll edərsiniz?"

### Güclü Cavab
Pool max capacity-ə çatdıqda rejection policy devreye girir. Default `AbortPolicy` ilə client `RejectedExecutionException` alır — pis UX. `CallerRunsPolicy` daha maraqlıdır: task-ı göndərən thread (adətən HTTP acceptor thread) özü icra edir — bu müddətdə yeni connection accept edilmir, natural back-pressure yaranır.

Həll seçimləri: (1) Bounded queue size artırmaq — latency buffer, amma memory artır. (2) Bulkhead — kritik endpoint-lər üçün ayrı pool. (3) Java 21 virtual thread-lər — 10.000 virtual thread yaratmaq ucuzdur; I/O-bound workload-da ideal. (4) Async+non-blocking (WebFlux) — thread-ləri I/O zamanı bloklamır, az thread-lə çox request. (5) Rate limiting — client-ləri giriş nöqtəsindən idarə et.

### Kod Nümunəsi

```java
import java.util.concurrent.*;
import java.util.concurrent.atomic.*;

// ── ThreadPoolExecutor konfiqurasiyası ───────────────────────────
int cores = Runtime.getRuntime().availableProcessors();

ThreadPoolExecutor executor = new ThreadPoolExecutor(
    cores,                                   // Core pool: həmişə canlı
    cores * 4,                               // Max pool: burst üçün
    60L, TimeUnit.SECONDS,                   // Keep-alive: idle thread ömrü
    new ArrayBlockingQueue<>(200),           // Bounded queue — back-pressure
    r -> {                                   // Thread factory — ad + daemon flag
        Thread t = new Thread(r, "api-worker-" + threadNum.incrementAndGet());
        t.setDaemon(true);
        t.setUncaughtExceptionHandler((thread, ex) ->
            log.error("Thread {} crashed", thread.getName(), ex));
        return t;
    },
    new ThreadPoolExecutor.CallerRunsPolicy() // Back-pressure rejection policy
);

AtomicInteger threadNum = new AtomicInteger(0);

// Pool health monitoring
ScheduledExecutorService monitor = Executors.newSingleThreadScheduledExecutor();
monitor.scheduleAtFixedRate(() -> {
    log.info("Pool - Active: {}, Queue: {}, Completed: {}, Largest: {}",
        executor.getActiveCount(),
        executor.getQueue().size(),
        executor.getCompletedTaskCount(),
        executor.getLargestPoolSize());
}, 0, 30, TimeUnit.SECONDS);

// Graceful shutdown
Runtime.getRuntime().addShutdownHook(new Thread(() -> {
    executor.shutdown();
    try {
        if (!executor.awaitTermination(60, TimeUnit.SECONDS)) {
            executor.shutdownNow();
        }
    } catch (InterruptedException e) {
        executor.shutdownNow();
    }
}));
```

```java
// ── Bulkhead Pattern — kritik path izolasiyası ───────────────────
@Component
public class ServiceWithBulkhead {

    // Kritik: payment (az thread, yüksək priority)
    private final ExecutorService paymentPool = new ThreadPoolExecutor(
        10, 20, 60, TimeUnit.SECONDS,
        new ArrayBlockingQueue<>(50),
        threadFactory("payment"),
        new ThreadPoolExecutor.AbortPolicy() // Tam dolduqda exception — kritik iş
    );

    // I/O-bound: external API call-lar (çox thread, I/O gözləmə)
    private final ExecutorService httpPool = new ThreadPoolExecutor(
        50, 200, 60, TimeUnit.SECONDS,
        new ArrayBlockingQueue<>(500),
        threadFactory("http-client"),
        new ThreadPoolExecutor.CallerRunsPolicy()
    );

    // Background: email, report (aşağı priority)
    private final ExecutorService backgroundPool = new ThreadPoolExecutor(
        5, 10, 120, TimeUnit.SECONDS,
        new LinkedBlockingQueue<>(1000),
        threadFactory("background"),
        new ThreadPoolExecutor.DiscardOldestPolicy()
    );

    public CompletableFuture<PaymentResult> processPayment(Order order) {
        // paymentPool dolsa belə httpPool-u etkiləmir
        return CompletableFuture.supplyAsync(
            () -> paymentGateway.charge(order),
            paymentPool
        );
    }

    public CompletableFuture<String> callExternalApi(String url) {
        return CompletableFuture.supplyAsync(
            () -> httpClient.get(url),
            httpPool
        );
    }

    public void sendEmail(String to, String body) {
        backgroundPool.submit(() -> emailService.send(to, body));
    }

    private ThreadFactory threadFactory(String prefix) {
        AtomicInteger n = new AtomicInteger(0);
        return r -> {
            Thread t = new Thread(r, prefix + "-" + n.incrementAndGet());
            t.setDaemon(true);
            return t;
        };
    }
}
```

```java
// ── Thread Pool Exhaustion Deadlock — antipattern ─────────────────
public class ExhaustionDeadlockAntipattern {
    // YANLIŞ: Eyni pool-da nested task
    private final ExecutorService pool = Executors.newFixedThreadPool(2);

    public void badPattern() {
        pool.submit(() -> {                        // Outer task — thread 1 alır
            System.out.println("Outer started");

            // Inner task eyni pool-dan thread gözləyir
            // Pool-da 2 thread var — hər ikisi outer task-da
            Future<String> inner = pool.submit(() -> "inner result");

            try {
                String result = inner.get(); // Əbədi gözləyir! DEADLOCK
                System.out.println("Inner: " + result);
            } catch (Exception e) {
                e.printStackTrace();
            }
        });

        pool.submit(() -> {                        // Outer task — thread 2 alır
            Future<String> inner = pool.submit(() -> "inner 2"); // Queue-da gözləyir
            try {
                inner.get(); // DEADLOCK — pool-da yer yoxdur
            } catch (Exception e) {}
        });
    }

    // DÜZGÜN 1: Ayrı pool
    private final ExecutorService outerPool = Executors.newFixedThreadPool(4);
    private final ExecutorService innerPool = Executors.newFixedThreadPool(4);

    public void goodPattern() {
        outerPool.submit(() -> {
            Future<String> inner = innerPool.submit(() -> "inner"); // Fərqli pool
            try {
                System.out.println(inner.get()); // Deadlock yoxdur
            } catch (Exception e) {}
        });
    }

    // DÜZGÜN 2: CompletableFuture ilə non-blocking
    public CompletableFuture<String> asyncPattern() {
        return CompletableFuture
            .supplyAsync(() -> "outer work", outerPool)
            .thenCompose(outer ->
                CompletableFuture.supplyAsync(() -> outer + " + inner", innerPool)
            );
    }
}
```

```java
// ── Java 21: Virtual Threads ─────────────────────────────────────
import java.util.concurrent.Executors;

public class VirtualThreadDemo {
    public static void main(String[] args) throws Exception {
        // Hər task üçün virtual thread — platform thread-i bloklamır
        try (var executor = Executors.newVirtualThreadPerTaskExecutor()) {

            var futures = new java.util.ArrayList<java.util.concurrent.Future<?>>();

            for (int i = 0; i < 100_000; i++) {
                final int id = i;
                futures.add(executor.submit(() -> {
                    // "Blocking" I/O — virtual thread yields, platform thread boşalır
                    Thread.sleep(100); // I/O simulate
                    return "task-" + id;
                }));
            }

            // Hamısı gözlə
            for (var f : futures) f.get();
        }
        // 100.000 virtual thread — ~100ms + overhead
        // 100.000 platform thread — OutOfMemoryError!
    }
}

// Spring Boot 3.2+ aktivasiya
// application.properties:
// spring.threads.virtual.enabled=true
//
// Bu qədər! Framework-in thread pool-larını virtual thread-ə çevirir.
// Tomcat, @Async, scheduled tasks — hamısı virtual thread-ə keçir.
```

```go
// ── Go Worker Pool Pattern ───────────────────────────────────────
package main

import (
    "context"
    "fmt"
    "sync"
    "time"
)

type Job struct {
    ID   int
    Data string
}

type Result struct {
    JobID  int
    Output string
    Err    error
}

type WorkerPool struct {
    workers    int
    jobs       chan Job
    results    chan Result
    wg         sync.WaitGroup
    cancelFunc context.CancelFunc
}

func NewWorkerPool(workerCount, queueSize int) *WorkerPool {
    ctx, cancel := context.WithCancel(context.Background())
    pool := &WorkerPool{
        workers:    workerCount,
        jobs:       make(chan Job, queueSize),
        results:    make(chan Result, queueSize),
        cancelFunc: cancel,
    }

    for i := 0; i < workerCount; i++ {
        pool.wg.Add(1)
        go pool.worker(ctx, i)
    }

    return pool
}

func (p *WorkerPool) worker(ctx context.Context, id int) {
    defer p.wg.Done()
    for {
        select {
        case job, ok := <-p.jobs:
            if !ok {
                return // Channel bağlandı
            }
            result := p.process(job)
            p.results <- result
        case <-ctx.Done():
            return // Context ləğv edildi
        }
    }
}

func (p *WorkerPool) process(job Job) Result {
    time.Sleep(10 * time.Millisecond) // İş simulate
    return Result{JobID: job.ID, Output: "processed: " + job.Data}
}

func (p *WorkerPool) Submit(job Job) {
    p.jobs <- job // Dolu olduqda bloklanır (back-pressure)
}

func (p *WorkerPool) Shutdown() []Result {
    close(p.jobs)        // Worker-ları bitirir
    p.wg.Wait()          // Bütün worker-ları gözlə
    close(p.results)

    var all []Result
    for r := range p.results {
        all = append(all, r)
    }
    return all
}

func main() {
    pool := NewWorkerPool(5, 100) // 5 worker, 100 job buffer

    for i := 0; i < 50; i++ {
        pool.Submit(Job{ID: i, Data: fmt.Sprintf("data-%d", i)})
    }

    results := pool.Shutdown()
    fmt.Printf("Processed %d jobs\n", len(results))
}
```

---

## Praktik Tapşırıqlar

- `ThreadPoolExecutor` yaradın, `getActiveCount()` vs `getQueue().size()` Prometheus metric kimi expose edin
- Thread pool exhaustion deadlock reproduce edin: 2-thread pool-da nested `pool.submit().get()`
- `CallerRunsPolicy` back-pressure effektini ölçün: producer sürəti pool dolduqda necə yavaşlayır
- Bulkhead: payment + notification üçün iki ayrı pool; birini doldurun, digərinin işlədiyini görün
- Java 21 virtual thread pool-u (I/O-bound task) vs `ThreadPoolExecutor` benchmark edin: 10.000 concurrent task

## Əlaqəli Mövzular
- `01-threads-vs-processes.md` — Thread model fundamentals
- `06-async-await.md` — Thread pool-un async alternativləri
- `07-event-loop.md` — Non-blocking, thread pool-suz model
- `04-deadlock-prevention.md` — Pool exhaustion deadlock
