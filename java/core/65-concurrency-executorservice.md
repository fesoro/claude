# 65 — Concurrency: ExecutorService

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [ExecutorService Nədir?](#executorservice-nedir)
2. [Thread Pool Növləri](#thread-pool-novleri)
3. [submit() vs execute()](#submit-vs-execute)
4. [Future.get()](#futureget)
5. [shutdown() vs shutdownNow()](#shutdown-vs-shutdownnow)
6. [ThreadPoolExecutor Parametrləri](#threadpoolexecutor-parametrleri)
7. [Praktik Nümunələr](#praktik-numuneler)
8. [İntervyu Sualları](#intervyu-sualları)

---

## ExecutorService Nədir?

`ExecutorService` — thread-lərin idarə edilməsi üçün yüksək səviyyəli API-dir. Thread-ləri əl ilə yaratmaq əvəzinə, tapşırıqları thread pool-a veririk.

**Niyə ExecutorService?**

```java
// YANLIŞ — hər tapşırıq üçün yeni thread yaratmaq
// 1000 sorğu gəlsə 1000 thread yaranır — sistem çöküş riski!
for (Request request : requests) {
    new Thread(() -> process(request)).start(); // Təhlükəli!
}

// DOĞRU — thread pool istifadəsi
ExecutorService pool = Executors.newFixedThreadPool(10);
for (Request request : requests) {
    pool.submit(() -> process(request)); // Pool 10 thread ilə idarə edir
}
pool.shutdown();
```

**Üstünlükləri:**
- Thread yaratma xərcini azaldır (pooldan istifadə)
- Thread sayını məhdudlaşdırır
- Tapşırıq növbəsi idarəsi
- Future ilə nəticəni alıb exception-ı idarə etmək

---

## Thread Pool Növləri

### 1. `newFixedThreadPool(n)` — Sabit Ölçülü Pool

```java
import java.util.concurrent.*;

public class FixedThreadPoolDemo {
    public static void main(String[] args) throws InterruptedException {
        // Dəqiq 4 thread saxlayan pool — nə az, nə çox
        ExecutorService executor = Executors.newFixedThreadPool(4);

        for (int i = 1; i <= 10; i++) {
            final int taskId = i;
            executor.submit(() -> {
                System.out.printf("Tapşırıq-%d başladı: %s%n",
                    taskId, Thread.currentThread().getName());
                try {
                    Thread.sleep(1000); // İş simulyasiyası
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
                System.out.printf("Tapşırıq-%d bitdi%n", taskId);
            });
        }

        executor.shutdown();
        executor.awaitTermination(30, TimeUnit.SECONDS);
    }
}
// Nəticə: Eyni anda yalnız 4 tapşırıq işləyir
// Qalan 6-sı LinkedBlockingQueue-da gözləyir
```

**Nə vaxt?** CPU-bound tapşırıqlar üçün: `n = Runtime.getRuntime().availableProcessors()`

### 2. `newCachedThreadPool()` — Dinamik Pool

```java
public class CachedThreadPoolDemo {
    public static void main(String[] args) throws InterruptedException {
        // Lazım olanda yeni thread yaradır, 60s boş qalan thread-i bitirir
        // Minimum: 0 thread, Maksimum: Integer.MAX_VALUE (!)
        ExecutorService executor = Executors.newCachedThreadPool();

        for (int i = 1; i <= 5; i++) {
            final int taskId = i;
            executor.submit(() -> {
                System.out.println("Tapşırıq-" + taskId + " başladı");
                try {
                    Thread.sleep(500);
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
            });
        }

        executor.shutdown();
        executor.awaitTermination(10, TimeUnit.SECONDS);
    }
}
```

**Nə vaxt?** Qısa müddətli, çoxlu IO-bound tapşırıqlar. **Diqqət:** Nəzarətsiz thread artımı riski var!

### 3. `newSingleThreadExecutor()` — Tək Thread

```java
public class SingleThreadExecutorDemo {
    public static void main(String[] args) throws InterruptedException {
        // Yalnız 1 thread — tapşırıqlar sırayla icra olunur (FIFO)
        ExecutorService executor = Executors.newSingleThreadExecutor();

        // Bu tapşırıqlar paralel DEYİL, ardıcıl icra olunur
        executor.submit(() -> System.out.println("Birinci"));
        executor.submit(() -> System.out.println("İkinci"));
        executor.submit(() -> System.out.println("Üçüncü"));

        executor.shutdown();
        executor.awaitTermination(10, TimeUnit.SECONDS);
    }
}
// Çıxış həmişə: Birinci → İkinci → Üçüncü
```

**Nə vaxt?** Sıralı icra tələb edən tapşırıqlar (log yazma, DB miqrasiyası).

### 4. `newScheduledThreadPool(n)` — Planlaşdırılmış Pool

```java
public class ScheduledThreadPoolDemo {
    public static void main(String[] args) throws InterruptedException {
        ScheduledExecutorService scheduler = Executors.newScheduledThreadPool(2);

        // Bir dəfə gecikməli icra
        scheduler.schedule(
            () -> System.out.println("3 saniyə sonra işlədim"),
            3, TimeUnit.SECONDS
        );

        // Sabit gecikmə ilə təkrarlanan icra (əvvəlki iş bitdikdən N saniyə sonra)
        scheduler.scheduleWithFixedDelay(
            () -> System.out.println("Hər 2s-dən bir (bitdikdən sonra)"),
            0, 2, TimeUnit.SECONDS
        );

        // Sabit interval ilə (əvvəlki başladıqdan N saniyə sonra)
        scheduler.scheduleAtFixedRate(
            () -> System.out.println("Hər 1s-dən bir (başladıqdan sonra)"),
            0, 1, TimeUnit.SECONDS
        );

        Thread.sleep(6000);
        scheduler.shutdown();
    }
}
```

**`scheduleAtFixedRate` vs `scheduleWithFixedDelay` fərqi:**
- `AtFixedRate`: T=0 başla, T=1 başla, T=2 başla... (iş uzun sürərsə üst-üstə düşə bilər)
- `WithFixedDelay`: Bitdi + N saniyə gözlə + başla

### 5. `newVirtualThreadPerTaskExecutor()` — Java 21+

```java
// Java 21 virtual thread pool
ExecutorService executor = Executors.newVirtualThreadPerTaskExecutor();

for (int i = 0; i < 1_000_000; i++) {
    executor.submit(() -> {
        // IO-bound tapşırıq — virtual thread mükəmməldir
        Thread.sleep(Duration.ofMillis(100));
        return "bitdi";
    });
}
executor.shutdown();
```

---

## submit() vs execute()

```java
ExecutorService executor = Executors.newFixedThreadPool(2);

// execute() — Runnable qəbul edir, nəticə/exception qaytarmır
executor.execute(() -> {
    System.out.println("execute ilə işləyir");
    // Əgər exception atılsa, UncaughtExceptionHandler-a keçir
    // throw new RuntimeException("Xəta"); — səssizcə udulacaq!
});

// submit() — Runnable və ya Callable qəbul edir, Future qaytarır
Future<String> future = executor.submit(() -> {
    System.out.println("submit ilə işləyir");
    return "Nəticə"; // Callable
});

Future<?> runnableFuture = executor.submit(() -> {
    System.out.println("Runnable submit ilə");
    // throw new RuntimeException("Xəta"); — Future.get()-də görünəcək
});

// Exception-ı tutmaq
try {
    runnableFuture.get(); // Burada ExecutionException ata bilər
} catch (ExecutionException e) {
    System.out.println("Tapşırıq xətası: " + e.getCause());
} catch (InterruptedException e) {
    Thread.currentThread().interrupt();
}

executor.shutdown();
```

**Əsas fərq:**

| Xüsusiyyət         | execute()           | submit()                    |
|--------------------|---------------------|-----------------------------|
| Qəbul etdiyi tip   | Runnable            | Runnable, Callable          |
| Qaytarır           | void                | Future<?>                   |
| Exception idarəsi  | UncaughtExceptionHandler | Future.get()-də ExecutionException |
| Nəticə əldə etmək | Mümkün deyil        | future.get() ilə            |

---

## Future.get()

```java
import java.util.concurrent.*;
import java.util.List;
import java.util.ArrayList;

public class FutureDemo {
    public static void main(String[] args) throws InterruptedException {
        ExecutorService executor = Executors.newFixedThreadPool(3);

        // Bir neçə tapşırıq göndəririk
        List<Future<Integer>> futures = new ArrayList<>();
        for (int i = 1; i <= 5; i++) {
            final int num = i;
            futures.add(executor.submit(() -> {
                Thread.sleep(num * 200L); // Fərqli müddətlər
                return num * num; // Kvadrat
            }));
        }

        // Nəticələri toplayırıq
        for (int i = 0; i < futures.size(); i++) {
            try {
                // get() — blok edicidir! O tamamlanana qədər gözləyir
                Integer result = futures.get(i).get(5, TimeUnit.SECONDS);
                System.out.println((i + 1) + " tapşırığın nəticəsi: " + result);
            } catch (ExecutionException e) {
                System.out.println("Tapşırıq xətası: " + e.getCause().getMessage());
            } catch (TimeoutException e) {
                System.out.println("Tapşırıq vaxtında bitmədi!");
                futures.get(i).cancel(true); // İptal et
            }
        }

        executor.shutdown();
    }
}
```

**Future metodları:**

```java
Future<String> future = executor.submit(callable);

future.get();                           // Blok edərək gözlə
future.get(5, TimeUnit.SECONDS);        // 5 saniyə gözlə, sonra TimeoutException
future.isDone();                        // Tamamlandımı? (uğurla, xəta ilə, və ya iptal)
future.isCancelled();                   // İptal edilibmi?
future.cancel(true);                    // İptal et (true = interrupt göndər)
```

### invokeAll() və invokeAny()

```java
ExecutorService executor = Executors.newFixedThreadPool(3);

List<Callable<String>> tasks = List.of(
    () -> { Thread.sleep(1000); return "Nəticə-1"; },
    () -> { Thread.sleep(2000); return "Nəticə-2"; },
    () -> { Thread.sleep(500);  return "Nəticə-3"; }
);

// invokeAll — hamısı bitənə gözlə
List<Future<String>> allResults = executor.invokeAll(tasks);
for (Future<String> f : allResults) {
    System.out.println(f.get()); // Hamısı artıq bitib
}

// invokeAny — ən birinci bitəni qaytar, qalanlarını iptal et
String firstResult = executor.invokeAny(tasks);
System.out.println("Ən sürətli: " + firstResult); // "Nəticə-3"

executor.shutdown();
```

---

## shutdown() vs shutdownNow()

```java
ExecutorService executor = Executors.newFixedThreadPool(4);

// Tapşırıqlar göndəririk
for (int i = 0; i < 10; i++) {
    executor.submit(longRunningTask());
}

// shutdown() — Yumşaq dayandırma
// Yeni tapşırıq qəbul etmir, amma mövcud tapşırıqlar tamamlanır
executor.shutdown();

try {
    // Maksimum 30 saniyə gözlə
    if (!executor.awaitTermination(30, TimeUnit.SECONDS)) {
        // Timeout oldu, zorla dayandır
        List<Runnable> notExecuted = executor.shutdownNow();
        System.out.println("İcra olunmayan tapşırıq sayı: " + notExecuted.size());

        // Yenə gözlə — interrupt-lara cavab versin
        if (!executor.awaitTermination(10, TimeUnit.SECONDS)) {
            System.err.println("Pool dayandırılmadı!");
        }
    }
} catch (InterruptedException e) {
    executor.shutdownNow();
    Thread.currentThread().interrupt();
}
```

**Fərq:**

| Metod            | Davranış                                                          |
|------------------|-------------------------------------------------------------------|
| `shutdown()`     | Yeni tapşırıq qəbul etmir. Növbədəki + işləyən tapşırıqlar tamamlanır |
| `shutdownNow()`  | İşləyən thread-lərə interrupt göndərir. Növbədəki tapşırıqları qaytarır |
| `awaitTermination(t, unit)` | Bütün tapşırıqların bitməsini gözləyir                |
| `isShutdown()`   | `shutdown()` çağırılıbsa `true`                                  |
| `isTerminated()` | Bütün tapşırıqlar bitibsə `true`                                 |

---

## ThreadPoolExecutor Parametrləri

`Executors` factory metodları `ThreadPoolExecutor`-un üzərindədir. Daha dəqiq konfiqurasiya üçün birbaşa istifadə edə bilərik:

```java
import java.util.concurrent.*;

public class ThreadPoolExecutorDemo {
    public static void main(String[] args) {
        ThreadPoolExecutor executor = new ThreadPoolExecutor(
            2,                              // corePoolSize: Daim saxlanılan thread sayı
            5,                              // maximumPoolSize: Maksimum thread sayı
            30, TimeUnit.SECONDS,           // keepAliveTime: Artıq thread-in boş qalma müddəti
            new ArrayBlockingQueue<>(10),   // workQueue: Tapşırıq növbəsi (10 yerlik)
            Executors.defaultThreadFactory(), // threadFactory: Thread yaratma qaydası
            new ThreadPoolExecutor.CallerRunsPolicy() // rejectionHandler: Növbə dolduqda
        );

        // Monitorinq
        System.out.println("Pool ölçüsü: " + executor.getPoolSize());
        System.out.println("Aktiv thread: " + executor.getActiveCount());
        System.out.println("Tamamlanan: " + executor.getCompletedTaskCount());

        executor.shutdown();
    }
}
```

### Parametrlərin İzahı

**corePoolSize:**
```
Başlanğıcda: 0 thread (lazy yaradılır)
Tapşırıq gəldikcə: corePoolSize-a qədər yeni thread yaradılır
corePoolSize-a çatdıqda: Növbəyə əlavə edilir
```

**maximumPoolSize:**
```
Növbə dolduqda: maximumPoolSize-a qədər əlavə thread yaradılır
maximumPoolSize-a çatıb növbə doluqda: RejectionPolicy işə düşür
```

**workQueue növləri:**
```java
new LinkedBlockingQueue<>()       // Limitsiz (newFixedThreadPool istifadə edir)
new ArrayBlockingQueue<>(100)     // Limitli — tövsiyə olunur
new SynchronousQueue<>()          // Növbə yox, birbaşa thread-ə verir (newCachedThreadPool)
new PriorityBlockingQueue<>()     // Prioritetə görə sıralanır
```

**Rejection Policy-lər:**
```java
// 1. AbortPolicy (default) — RejectedExecutionException atır
new ThreadPoolExecutor.AbortPolicy()

// 2. CallerRunsPolicy — Çağıran thread özü icra edir (backpressure mexanizmi)
new ThreadPoolExecutor.CallerRunsPolicy()

// 3. DiscardPolicy — Tapşırığı səssizcə uçurur
new ThreadPoolExecutor.DiscardPolicy()

// 4. DiscardOldestPolicy — Ən köhnə tapşırığı atır, yenisini əlavə edir
new ThreadPoolExecutor.DiscardOldestPolicy()

// 5. Xüsusi policy
new RejectedExecutionHandler() {
    @Override
    public void rejectedExecution(Runnable r, ThreadPoolExecutor executor) {
        System.err.println("Tapşırıq rədd edildi: " + r);
        // Logla, DB-yə yaz, növbəyə qoy...
    }
}
```

### Thread Pool Ölçüsü Hesablaması

```java
// CPU-bound tapşırıqlar üçün
int cpuCount = Runtime.getRuntime().availableProcessors();
int optimalPoolSize = cpuCount + 1; // +1 yaddaş gözlə üçün

// IO-bound tapşırıqlar üçün (Littles Law əsasında)
// İdeal ölçü = CPU sayı × (1 + Gözlə vaxtı / İş vaxtı)
// Məsələn: 4 CPU, hər tapşırıq 20ms işləyir, 80ms IO gözləyir
int ioPoolSize = 4 * (1 + 80 / 20); // = 20

System.out.println("CPU sayı: " + cpuCount);
System.out.println("CPU-bound pool: " + optimalPoolSize);
System.out.println("IO-bound pool: " + ioPoolSize);
```

---

## Praktik Nümunələr

### Web Server Simulyasiyası

```java
import java.util.concurrent.*;
import java.util.concurrent.atomic.AtomicInteger;

public class WebServerSimulation {
    private static final AtomicInteger requestCounter = new AtomicInteger(0);

    public static void main(String[] args) throws InterruptedException {
        // Real dünya konfiqurasiyası
        ThreadPoolExecutor serverPool = new ThreadPoolExecutor(
            5,                                    // 5 daimi thread
            20,                                   // Pik yükdə 20-yə qədər
            60, TimeUnit.SECONDS,                 // Boş qalan thread 60s sonra silinir
            new ArrayBlockingQueue<>(50),          // 50 sorğu növbəsi
            r -> {
                // Xüsusi thread factory — adlandırma
                Thread t = new Thread(r, "http-worker-" + requestCounter.incrementAndGet());
                t.setDaemon(true);
                return t;
            },
            new ThreadPoolExecutor.CallerRunsPolicy() // Dolu olarsa caller özü icra edir
        );

        // 100 HTTP sorğu gəlir
        CountDownLatch allDone = new CountDownLatch(100);
        for (int i = 1; i <= 100; i++) {
            final int reqId = i;
            serverPool.submit(() -> {
                try {
                    handleRequest(reqId);
                } finally {
                    allDone.countDown();
                }
            });
        }

        allDone.await(30, TimeUnit.SECONDS);

        System.out.println("Cəmi icra olunan: " + serverPool.getCompletedTaskCount());
        serverPool.shutdown();
    }

    private static void handleRequest(int id) {
        try {
            Thread.sleep((long)(Math.random() * 500)); // IO simulyasiyası
            System.out.printf("Sorğu-%d cavablandı: %s%n", id, Thread.currentThread().getName());
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }
}
```

---

## İntervyu Sualları

**S: `newFixedThreadPool` vs `newCachedThreadPool` fərqi?**
C: Fixed — sabit thread sayı, limitsiz növbə. Cached — dinamik thread sayı (0 dan Integer.MAX_VALUE-ya qədər), SynchronousQueue (növbə yox). Fixed CPU-bound, Cached qısa IO-bound tapşırıqlar üçün.

**S: `submit()` vs `execute()` fərqi?**
C: `execute()` — Runnable qəbul edir, void qaytarır, exception UncaughtExceptionHandler-a gedir. `submit()` — Runnable/Callable qəbul edir, Future qaytarır, exception Future.get()-də görünür.

**S: Thread pool-da tapşırıq hansı ardıcıllıqla işlənir?**
C: 1) corePoolSize dolmayıbsa yeni thread yarat. 2) Core dolubsa növbəyə əlavə et. 3) Növbə dolubsa maximumPoolSize-a qədər yeni thread yarat. 4) Max da dolubsa RejectionPolicy işə düş.

**S: `shutdown()` çağırıldıqdan sonra `submit()` nə edir?**
C: `RejectedExecutionException` atır.

**S: Niyə `Executors.newFixedThreadPool()` production-da tövsiyə olunmur?**
C: Çünki `LinkedBlockingQueue` — limitsizdir. Çox tapşırıq gələrsə yaddaş tükənə bilər. `ThreadPoolExecutor` ilə limitli `ArrayBlockingQueue` istifadəsi tövsiyə olunur.

**S: `CallerRunsPolicy` nə üçün faydalıdır?**
C: Backpressure mexanizmi kimi işləyir. Pool dolu olduqda tapşırığı çağıran thread özü icra edir, beləliklə yeni tapşırıq göndərməyi ləngidir. Tapşırıqlar itirilmir.

**S: `awaitTermination()` nə edir?**
C: `shutdown()` çağırıldıqdan sonra bütün tapşırıqların tamamlanmasını gözləyir. Timeout ötürsə `false` qaytarır.

**S: ThreadPoolExecutor-un `keepAliveTime` parametri nə edir?**
C: `corePoolSize`-dan artıq yaradılmış thread-lər bu müddət boş qalsa silinir. `allowCoreThreadTimeOut(true)` çağırılarsa core thread-lərə də tətbiq olunur.
