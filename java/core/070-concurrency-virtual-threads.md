# 070 — Concurrency: Virtual Threads (Java 21)
**Səviyyə:** İrəli


## Mündəricat
1. [Platform Thread vs Virtual Thread](#platform-thread-vs-virtual-thread)
2. [Virtual Thread Yaratmaq](#virtual-thread-yaratmaq)
3. [Virtual Thread-lərin İçi Necə İşləyir](#virtual-thread-lerin-ici-nece-isleyir)
4. [IO-Bound Tapşırıqlar üçün Üstünlük](#io-bound-tapsiriglar-ucun-ustunluk)
5. [Virtual Thread Pinning](#virtual-thread-pinning)
6. [Structured Concurrency](#structured-concurrency)
7. [Virtual Thread nə vaxt İSTİFADƏ ETMƏ](#virtual-thread-ne-vaxt-istifade-etme)
8. [Miqrasiya Tövsiyələri](#migrasi-tovsiyeler)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Platform Thread vs Virtual Thread

**Platform Thread** — OS thread-inin birbaşa əksidir. Hər platform thread ~1MB stack yaddaşı tutur. JVM 10.000-dən çox platform thread saxlaya bilmir.

**Virtual Thread** — JVM tərəfindən idarə edilən, OS-dan müstəqil yüngül thread. Milyonlarla virtual thread yaratmaq mümkündür.

```
Platform Threads:
+---JVM---+         +---OS---+
| Thread-1| ←----→  | OS Thread-1 | (~1MB stack)
| Thread-2| ←----→  | OS Thread-2 | (~1MB stack)
| Thread-3| ←----→  | OS Thread-3 | (~1MB stack)
+---------+         +--------+
(1:1 əlaqə — OS thread sayı məhduddur)

Virtual Threads:
+---JVM---+
|VThread-1|
|VThread-2|  ←----→ Carrier Thread Pool (OS thread-ləri)
|VThread-3|          (adi olaraq CPU sayı qədər)
|...(M)...|
+---------+
(M:N əlaqə — milyonlarla virtual thread, az OS thread)
```

| Xüsusiyyət          | Platform Thread     | Virtual Thread         |
|---------------------|---------------------|------------------------|
| Stack yaddaşı       | ~1MB (sabit)        | ~Kilobytes (dinamik)   |
| Maksimum sayı       | ~10,000             | Milyonlar              |
| Yaratma xərci       | Baha (OS çağırışı)  | Ucuz (JVM)             |
| Blok etmə davranışı | OS thread bloklanır | Carrier thread buraxılır|
| `Thread.sleep()`    | OS thread yatır     | Virtual thread asılı qalır, carrier freed |
| Uyğun tapşırıq      | CPU + IO            | IO-bound               |

---

## Virtual Thread Yaratmaq

```java
import java.util.concurrent.*;
import java.time.Duration;

public class VirtualThreadCreation {
    public static void main(String[] args) throws InterruptedException {

        // Üsul 1: Thread.ofVirtual().start()
        Thread vt1 = Thread.ofVirtual()
            .name("my-virtual-thread")
            .start(() -> {
                System.out.println("Virtual thread: " + Thread.currentThread());
                System.out.println("Virtual-dimi: " + Thread.currentThread().isVirtual()); // true
            });
        vt1.join();

        // Üsul 2: Thread.ofVirtual().unstarted() — sonra start()
        Thread vt2 = Thread.ofVirtual().unstarted(() -> System.out.println("Hazır, sonra başlayır"));
        vt2.start();
        vt2.join();

        // Üsul 3: Thread.startVirtualThread() — ən qısa yol
        Thread vt3 = Thread.startVirtualThread(() -> System.out.println("Ən qısa yol"));
        vt3.join();

        // Üsul 4: ExecutorService ilə (tövsiyə olunan)
        try (ExecutorService executor = Executors.newVirtualThreadPerTaskExecutor()) {
            // Hər tapşırıq üçün yeni virtual thread (platform threadPool deyil!)
            for (int i = 0; i < 10_000; i++) {
                final int taskId = i;
                executor.submit(() -> {
                    // IO əməliyyatı — virtual thread bloklandıqda carrier buraxılır
                    Thread.sleep(Duration.ofMillis(100));
                    return "Tapşırıq-" + taskId + " tamamlandı";
                });
            }
        } // ExecutorService auto-close — shutdown() çağırır
        // 10,000 tapşırıq paralel işləyir, amma yalnız CPU sayı qədər OS thread!

        // Üsul 5: ThreadFactory ilə
        ThreadFactory factory = Thread.ofVirtual().name("worker-", 0).factory();
        Thread vt5 = factory.newThread(() -> System.out.println("Factory ilə yaradıldı"));
        vt5.start();
        vt5.join();

        System.out.println("\nPlatform thread-dimi: " + !Thread.currentThread().isVirtual()); // true
    }
}
```

---

## Virtual Thread-lərin İçi Necə İşləyir

```java
public class VirtualThreadInternals {
    public static void main(String[] args) throws Exception {
        // Virtual thread-in carrier thread-i
        Thread vt = Thread.ofVirtual().start(() -> {
            System.out.println("Virtual thread: " + Thread.currentThread());
            // Carrier = ForkJoinPool worker thread

            try {
                // sleep() çağırıldıqda:
                // 1. Virtual thread "park" olunur
                // 2. Carrier thread (ForkJoinPool worker) buraxılır
                // 3. Başqa virtual thread carrier-i alır
                // 4. sleep() bitdikdə virtual thread yenidən bir carrier-ə yüklənir
                Thread.sleep(Duration.ofSeconds(1));
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }

            System.out.println("Davam edir (bəlkə fərqli carrier-də)");
        });

        vt.join();
    }
}
```

**1,000,000 Virtual Thread Nümunəsi:**

```java
public class MillionVirtualThreads {
    public static void main(String[] args) throws InterruptedException {
        int count = 1_000_000;
        CountDownLatch latch = new CountDownLatch(count);

        long start = System.currentTimeMillis();

        // Platform thread ilə bu IMPOSSIBLE! (OutOfMemoryError)
        // Virtual thread ilə işləyir
        try (var executor = Executors.newVirtualThreadPerTaskExecutor()) {
            for (int i = 0; i < count; i++) {
                executor.submit(() -> {
                    try {
                        Thread.sleep(Duration.ofSeconds(1)); // IO simulyasiyası
                    } catch (InterruptedException e) {
                        Thread.currentThread().interrupt();
                    } finally {
                        latch.countDown();
                    }
                });
            }
        }

        latch.await();
        System.out.println("1,000,000 virtual thread tamamlandı: " +
            (System.currentTimeMillis() - start) + "ms");
        // ~1-2 saniyə (hamısı paralel!)
    }
}
```

---

## IO-Bound Tapşırıqlar üçün Üstünlük

```java
// Əvvəl: Platform thread pool (məhdud paralellik)
public class OldApproach {
    private static final ExecutorService pool = Executors.newFixedThreadPool(200);

    public CompletableFuture<String> fetchData(String url) {
        return CompletableFuture.supplyAsync(() -> {
            // Bu thread IO gözləyərkən bloklanır!
            // 200 thread pool = max 200 paralel sorğu
            return httpClient.get(url); // ~500ms IO gözləmə
        }, pool);
    }
}

// İndi: Virtual thread (demək olar ki, limitsiz paralellik)
public class NewApproach {
    private static final ExecutorService executor =
        Executors.newVirtualThreadPerTaskExecutor();

    public CompletableFuture<String> fetchData(String url) {
        return CompletableFuture.supplyAsync(() -> {
            // Virtual thread IO gözləyərkən carrier buraxılır
            // Effektiv olaraq limitsiz paralel sorğu!
            return httpClient.get(url);
        }, executor);
    }
}
```

**Real HTTP Çoxlu Sorğu:**

```java
import java.net.http.*;
import java.net.URI;

public class ParallelHttpRequests {
    public static void main(String[] args) throws Exception {
        HttpClient client = HttpClient.newBuilder()
            .executor(Executors.newVirtualThreadPerTaskExecutor()) // Virtual thread!
            .build();

        List<String> urls = List.of(
            "https://api.example.com/user/1",
            "https://api.example.com/user/2",
            "https://api.example.com/user/3"
            // ... 10,000 URL
        );

        try (var executor = Executors.newVirtualThreadPerTaskExecutor()) {
            List<Future<String>> futures = urls.stream()
                .map(url -> executor.submit(() -> {
                    HttpRequest request = HttpRequest.newBuilder(URI.create(url)).build();
                    HttpResponse<String> response = client.send(request,
                        HttpResponse.BodyHandlers.ofString());
                    return response.body();
                }))
                .toList();

            // Bütün nəticələri topla
            for (Future<String> f : futures) {
                System.out.println(f.get());
            }
        }
    }
}
```

---

## Virtual Thread Pinning

**Pinning** — virtual thread-in carrier thread-ə "yapışması" — ən vacib problem!

```java
public class PinningDemo {
    private final Object lock = new Object();

    // PROBLEM: synchronized blok içindəki blocking əməliyyat → PINNING!
    public void methodWithPinning() throws InterruptedException {
        synchronized (lock) {
            // Virtual thread burada bloklandıqda carrier thread BURAXILMIR!
            // Carrier thread də bloklanır — virtual thread üstünlüyü itirilir
            Thread.sleep(Duration.ofSeconds(1)); // ← PINNING!

            // Database sorğusu, HTTP sorğusu — bunlar da pinning yaradır!
        }
    }

    // HƏLL 1: ReentrantLock istifadə et
    private final ReentrantLock reentrantLock = new ReentrantLock();

    public void methodWithoutPinning() throws InterruptedException {
        reentrantLock.lock(); // ReentrantLock → pinning yoxdur!
        try {
            Thread.sleep(Duration.ofSeconds(1)); // Carrier buraxılır
        } finally {
            reentrantLock.unlock();
        }
    }

    // HƏLL 2: synchronized blokun xaricindəki blocking əməliyyat
    public void refactoredMethod() throws InterruptedException {
        // IO əvvəl, lock sonra
        String data = fetchFromDatabase(); // Blocking IO — lock xaricindədir

        synchronized (lock) {
            // Yalnız qısa CPU əməliyyatı — pinning qısa müddətlidir
            processData(data);
        }
    }

    private String fetchFromDatabase() { return "data"; }
    private void processData(String d) {}
}
```

**Pinning-i aşkar etmək:**

```bash
# JVM flag ilə pinning-i log et
java -Djdk.tracePinnedThreads=full MyApp

# Çıxış (pinning varsa):
# Thread[#21,ForkJoinPool-1-worker-1,5,CarrierThreads]
#     com.example.PinningDemo.methodWithPinning(PinningDemo.java:15)
#     <Pinned due to: synchronized block>
```

**Pinning baş verən hallar:**

| Ssenari                          | Pinning? | Həll                         |
|----------------------------------|----------|------------------------------|
| `synchronized` + blocking IO    | Bəli     | `ReentrantLock` istifadə et  |
| `synchronized` + `Thread.sleep` | Bəli     | `ReentrantLock` istifadə et  |
| Native method daxilinda blocking | Bəli     | Tövsiyə olunmur              |
| `ReentrantLock` + blocking IO   | Xeyr     | Tövsiyə olunan               |
| `synchronized` + qısa CPU iş    | Texniki bəli, amma qısa | Kabul ediləndir |

---

## Structured Concurrency

Java 21-də `preview`, Java 23-də `final`. Bir neçə tapşırığı idarə etmək üçün struktur yanaşma.

```java
import java.util.concurrent.*;
import java.util.concurrent.StructuredTaskScope.*;

public class StructuredConcurrencyDemo {

    record UserData(String name, String email) {}
    record OrderData(int count, double total) {}
    record PageData(UserData user, OrderData orders) {}

    public static void main(String[] args) throws Exception {
        // ShutdownOnFailure — biri xəta versə hamısını dayandır
        try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {
            // Paralel tapşırıqlar başlat
            Subtask<UserData> userTask = scope.fork(() -> fetchUser(42));
            Subtask<OrderData> orderTask = scope.fork(() -> fetchOrders(42));

            scope.join();           // Hamısının bitməsini gözlə
            scope.throwIfFailed();  // Xəta varsa at

            // Hər ikisi uğurlu tamamlandı
            UserData user = userTask.get();
            OrderData orders = orderTask.get();

            System.out.println("İstifadəçi: " + user);
            System.out.println("Sifarişlər: " + orders);

        } // scope bağlandıqda bütün fork-lar tamamlanır/ləğv edilir

        System.out.println("---");

        // ShutdownOnSuccess — biri uğurlu olanda hamısını dayandır (race)
        try (var scope = new StructuredTaskScope.ShutdownOnSuccess<String>()) {
            scope.fork(() -> fetchFromServer1());  // 300ms
            scope.fork(() -> fetchFromServer2());  // 100ms
            scope.fork(() -> fetchFromServer3());  // 200ms

            scope.join();

            // Ən sürətli serverin nəticəsi
            String result = scope.result();
            System.out.println("İlk nəticə: " + result);
        }
    }

    static UserData fetchUser(int id) throws InterruptedException {
        Thread.sleep(Duration.ofMillis(500));
        return new UserData("Orkhan", "orkhan@example.com");
    }

    static OrderData fetchOrders(int userId) throws InterruptedException {
        Thread.sleep(Duration.ofMillis(300));
        return new OrderData(5, 299.99);
    }

    static String fetchFromServer1() throws InterruptedException {
        Thread.sleep(Duration.ofMillis(300));
        return "Server-1 cavabı";
    }

    static String fetchFromServer2() throws InterruptedException {
        Thread.sleep(Duration.ofMillis(100));
        return "Server-2 cavabı";
    }

    static String fetchFromServer3() throws InterruptedException {
        Thread.sleep(Duration.ofMillis(200));
        return "Server-3 cavabı";
    }
}
```

**Structured Concurrency-nin üstünlükləri:**
- **Ömür idarəsi**: fork-lar scope-dan çıxa bilməz
- **Xəta yayılması**: Bir fork xəta versə, digərləri avtomatik ləğv edilir
- **Thread leak yoxdur**: Scope bağlandıqda bütün fork-lar tamamlanır
- **Debugging**: Stack trace daha aydındır

---

## Virtual Thread nə vaxt İSTİFADƏ ETMƏ

```java
// YANLIŞ 1: CPU-bound tapşırıqlar üçün virtual thread pool
// Virtual thread üstünlüyü yalnız bloklamada — CPU işi bloklamır
try (var executor = Executors.newVirtualThreadPerTaskExecutor()) {
    for (int i = 0; i < 1000; i++) {
        executor.submit(() -> {
            // Sırf CPU hesablaması — pinning olmadan,
            // amma virtual thread-lərin faydası yoxdur
            // ForkJoinPool daha yaxşıdır!
            return intensiveCalculation(); // CPU-bound
        });
    }
}

// DOĞRU: CPU-bound üçün ForkJoinPool
ForkJoinPool.commonPool().submit(() -> intensiveCalculation());

// YANLIŞ 2: ThreadLocal ilə pool-da istifadə (olmaz deyil, amma diqqətli ol)
// Virtual thread-lər qısa ömürlüdür — ThreadLocal-ın mənası azalır
// Hər request yeni virtual thread → ThreadLocal dəyəri başlanğıcda null

// YANLIŞ 3: synchronized blokda blocking IO (pinning!)
// Yuxarıdakı pinning bölməsinə bax

// YANLIŞ 4: Limitsiz virtual thread sayının pool kimi idarə edilməsi
// newVirtualThreadPerTaskExecutor — hər tapşırıq üçün virtual thread
// Bunu ayrıca pool ETMƏ — virtual thread-lər artıq pool kimi işləyir

// YANLIŞ 5: Çox paralel CPU işi
ExecutorService vte = Executors.newVirtualThreadPerTaskExecutor();
// 100,000 CPU-intensive tapşırıq:
// Virtual thread-lər CPU-da sıralanır, context switch xərci var
// Platform thread pool daha yaxşıdır!
```

**Qısa qaydalar:**

```
Virtual Thread İSTİFADƏ ET:       Virtual Thread İSTİFADƏ ETMƏ:
✓ HTTP sorğuları                   ✗ CPU-intensive hesablamalar
✓ Database sorğuları               ✗ synchronized + blocking IO
✓ File IO əməliyyatları            ✗ Thread pool olaraq istifadə
✓ Çox sayda paralel IO tapşırığı   ✗ ThreadLocal-a çox etibar
✓ Web server sorğu idarəsi         ✗ Native method-lu bloklamalar
```

---

## Miqrasiya Tövsiyələri

```java
// Əvvəlki kod:
ExecutorService pool = Executors.newFixedThreadPool(200);
pool.submit(() -> httpRequest()); // IO-bound

// Yeni kod (minimal dəyişiklik):
ExecutorService pool = Executors.newVirtualThreadPerTaskExecutor(); // Yalnız bu sətir!
pool.submit(() -> httpRequest()); // Eyni kod, virtual thread ilə

// Spring Boot 3.2+ — application.properties-də:
// spring.threads.virtual.enabled=true
// Bütün @Async, Web MVC thread-ləri avtomatik virtual thread-ə keçir

// Spring Boot nümunəsi:
@Configuration
public class AsyncConfig {
    @Bean
    public Executor asyncExecutor() {
        return Executors.newVirtualThreadPerTaskExecutor();
    }
}

@Service
public class UserService {
    @Async
    public CompletableFuture<User> findUserAsync(int id) {
        // Virtual thread-də işləyir
        return CompletableFuture.completedFuture(fetchUser(id));
    }
}
```

### synchronized-i ReentrantLock-a çevirmək (pinning üçün)

```java
// ƏVVƏL — pinning var
public class OldService {
    private final Object lock = new Object();
    private final Map<String, String> cache = new HashMap<>();

    public String getOrFetch(String key) throws Exception {
        synchronized (lock) {
            if (!cache.containsKey(key)) {
                // Blocking IO — pinning!
                cache.put(key, fetchFromRemote(key));
            }
            return cache.get(key);
        }
    }
}

// SONRA — pinning yoxdur
public class NewService {
    private final ReentrantLock lock = new ReentrantLock();
    private final Map<String, String> cache = new HashMap<>();

    public String getOrFetch(String key) throws Exception {
        // Əvvəlcə oxu (lock olmadan)
        lock.lock();
        try {
            if (cache.containsKey(key)) return cache.get(key);
        } finally {
            lock.unlock();
        }

        // Fetch (lock olmadan — virtual thread burada suspend ola bilər)
        String value = fetchFromRemote(key);

        // Yaz
        lock.lock();
        try {
            cache.putIfAbsent(key, value);
            return cache.get(key);
        } finally {
            lock.unlock();
        }
    }

    private String fetchFromRemote(String key) throws Exception {
        Thread.sleep(Duration.ofMillis(100)); // IO simulyasiyası
        return "value-" + key;
    }
}
```

---

## ScopedValue (Java 21+)

Virtual thread-lərlə ThreadLocal-ın yeni alternativi:

```java
import java.lang.ScopedValue;

public class ScopedValueDemo {
    // ThreadLocal-dan fərqli: immutable, scope-a bağlı
    static final ScopedValue<String> CURRENT_USER = ScopedValue.newInstance();

    public static void main(String[] args) throws Exception {
        // where().run() — scope başlayır
        ScopedValue.where(CURRENT_USER, "Orkhan").run(() -> {
            System.out.println("İstifadəçi: " + CURRENT_USER.get()); // Orkhan

            // Alt thread-lərə avtomatik keçir (InheritableThreadLocal kimi)
            Thread.startVirtualThread(() -> {
                System.out.println("Alt thread: " + CURRENT_USER.get()); // Orkhan
            });
        });

        // Scope xaricindədir — NoSuchElementException
        // CURRENT_USER.get(); ← xəta!
    }
}
```

**ScopedValue üstünlükləri:**
- **Immutable** — dəyəri dəyişmək olmaz (thread-safe by design)
- **Scope-bound** — scope bitdikdə avtomatik silinir (remove() lazım deyil)
- **Virtual thread ilə mükəmməl** — inheritance avtomatik işləyir
- **ThreadLocal-dan sürətli** — structure daha sadədir

---

## İntervyu Sualları

**S: Virtual thread nədir?**
C: Java 21-dəki yüngül thread — JVM tərəfindən idarə edilir, OS thread-ə 1:1 bağlı deyil. M:N modeili — milyonlarla virtual thread az sayda OS (carrier) thread üzərində işləyir. IO-bound tapşırıqlar üçün mükəmməldir.

**S: Virtual thread bloklandıqda nə baş verir?**
C: Virtual thread "park" olunur — carrier thread (ForkJoinPool worker) buraxılır. Başqa virtual thread həmin carrier-i alır. IO bitdikdə virtual thread bir carrier-ə yenidən yüklənir (bəlkə fərqli carrier).

**S: Pinning nədir və necə qarşısını almaq olar?**
C: Virtual thread-in carrier thread-ə yapışması — carrier thread bloklanır, virtual thread üstünlüyü itirilir. `synchronized` blok içindəki blocking IO pinning yaradır. Həll: `ReentrantLock` istifadə et.

**S: CPU-bound tapşırıqlar üçün virtual thread uyğundur?**
C: Xeyr. Virtual thread-in üstünlüyü yalnız IO bloklamasında — carrier thread buraxılır. CPU-bound tapşırıqlarda carrier həmişə işgüzardır, virtual thread-lərin əlavə fayda vermir. ForkJoinPool daha yaxşıdır.

**S: Structured concurrency nədir?**
C: Paralel tapşırıqların ömrünü idarə etmək üçün Java 21 xüsusiyyəti. `StructuredTaskScope` ilə fork-lar scope-dan çıxa bilmir. ShutdownOnFailure — bir xəta versə hamısını ləğv et. ShutdownOnSuccess — biri uğurlu olanda digərləri ləğv et.

**S: `Executors.newVirtualThreadPerTaskExecutor()` necə işləyir?**
C: Hər `submit()` çağırışında yeni virtual thread yaradır. Platform thread pool kimi thread-ləri geri saxlamır. Hər tapşırıq ayrı virtual thread alır — "pool" deyil, tapşırıq-başına-thread modelidir.

**S: ScopedValue vs ThreadLocal nədir?**
C: ScopedValue — Java 21+, immutable, scope-a bağlı (remove() lazım deyil), virtual thread inheritance avtomatik. ThreadLocal — mutable, əl ilə remove() lazım, virtual thread pool-unda memory leak riski.

**S: Virtual thread-lərlə ThreadLocal istifadəsi düzgündürmü?**
C: İşləyir, amma diqqətli ol. Virtual thread-lər qısa ömürlüdür — pool thread-lər kimi yenidən istifadə olmur. Hər request yeni virtual thread → ThreadLocal başlanğıcda null. ScopedValue daha uyğun alternativdir.
