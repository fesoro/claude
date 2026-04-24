# 067 — Concurrency: Semaphore, CountDownLatch, CyclicBarrier, Phaser, Exchanger
**Səviyyə:** İrəli


## Mündəricat
1. [Semaphore](#semaphore)
2. [CountDownLatch](#countdownlatch)
3. [CyclicBarrier](#cyclicbarrier)
4. [CountDownLatch vs CyclicBarrier](#countdownlatch-vs-cyclicbarrier)
5. [Phaser](#phaser)
6. [Exchanger](#exchanger)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Semaphore

**Semaphore** — eyni anda resursdan istifadə edə biləcək thread sayını məhdudlaşdırır. `n` sayda **permit** saxlayır.

```java
import java.util.concurrent.*;

public class SemaphoreDemo {
    // Yalnız 3 thread eyni anda daxil ola bilər (VIP otaq kimi)
    private final Semaphore semaphore = new Semaphore(3);

    public void accessResource() throws InterruptedException {
        semaphore.acquire(); // Permit al (yoxdursa gözlə)
        try {
            System.out.println(Thread.currentThread().getName() + " resursa daxil oldu");
            Thread.sleep(2000); // Resursdan istifadə
        } finally {
            semaphore.release(); // Permit qaytar
            System.out.println(Thread.currentThread().getName() + " resursu buraxdı");
        }
    }

    public static void main(String[] args) {
        SemaphoreDemo demo = new SemaphoreDemo();

        // 10 thread eyni anda cəhd edir, amma yalnız 3-ü daxil ola bilər
        for (int i = 1; i <= 10; i++) {
            final int threadId = i;
            new Thread(() -> {
                try {
                    demo.accessResource();
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
            }, "Thread-" + threadId).start();
        }
    }
}
```

### acquire() variantları

```java
Semaphore sem = new Semaphore(5);

sem.acquire();              // Blok edərək gözlə
sem.acquire(3);             // 3 permit al (blok edərək)
sem.acquireUninterruptibly(); // Interrupt-u nəzərə almadan gözlə
boolean got = sem.tryAcquire(); // Dərhal — permit varsa true, yoxdursa false (gözləmə!)
boolean got2 = sem.tryAcquire(2, TimeUnit.SECONDS); // 2 saniyə gözlə

sem.release();              // 1 permit qaytar
sem.release(3);             // 3 permit qaytar

System.out.println(sem.availablePermits()); // Mövcud permit sayı
System.out.println(sem.getQueueLength());   // Gözləyən thread sayı
```

### Semaphore-u Mutex kimi istifadə (Binary Semaphore)

```java
// 1 permitli Semaphore — synchronized kimi davranır
// Üstünlük: bir thread lock alır, BAŞQA bir thread buraxır!
Semaphore mutex = new Semaphore(1);

// Thread-A:
mutex.acquire(); // Lock al
try {
    // Kritik bölgə
    performWork();
} finally {
    mutex.release(); // Thread-A da, Thread-B də buraxа bilər!
}

// synchronized isə yalnız lock alan thread tərəfindən buraxılır
```

### Resurs Hovuzu (Connection Pool)

```java
public class ConnectionPool {
    private final Semaphore available;
    private final Queue<Connection> connections;

    public ConnectionPool(int poolSize) {
        available = new Semaphore(poolSize, true); // fair=true
        connections = new ConcurrentLinkedQueue<>();
        // Başlanğıc connection-ları yarat
        for (int i = 0; i < poolSize; i++) {
            connections.add(createConnection(i));
        }
    }

    public Connection getConnection() throws InterruptedException {
        available.acquire(); // Connection mövcud olana qədər gözlə
        return connections.poll(); // Növbədən al
    }

    public void releaseConnection(Connection conn) {
        connections.offer(conn); // Növbəyə qaytar
        available.release();     // Permit qaytar
    }

    private Connection createConnection(int id) {
        return new Connection(id);
    }

    record Connection(int id) {}
}
```

### Rate Limiter

```java
// Semaphore ilə sadə rate limiter — saniyədə 10 sorğu
public class RateLimiter {
    private final Semaphore semaphore = new Semaphore(10);

    public RateLimiter() {
        // Hər saniyə 10 permit əlavə edir
        ScheduledExecutorService scheduler = Executors.newSingleThreadScheduledExecutor();
        scheduler.scheduleAtFixedRate(() -> {
            int released = 10 - semaphore.availablePermits();
            if (released > 0) {
                semaphore.release(released); // Maksimum 10-a qədər doldur
            }
        }, 1, 1, TimeUnit.SECONDS);
    }

    public boolean tryRequest() {
        return semaphore.tryAcquire(); // Permit varsa true
    }
}
```

---

## CountDownLatch

**CountDownLatch** — bir və ya bir neçə thread-in, digər thread-lərin müəyyən əməliyyatları tamamlamasını gözləməsi üçün. **Bir dəfəlik** — sıfıra çatdıqdan sonra yenidən istifadə edilə bilməz.

```java
import java.util.concurrent.*;

public class CountDownLatchDemo {
    public static void main(String[] args) throws InterruptedException {
        int serviceCount = 3;
        CountDownLatch startSignal = new CountDownLatch(1);  // Başlama siqnalı
        CountDownLatch doneSignal = new CountDownLatch(serviceCount); // Bitmə siqnalı

        // Servis thread-lərini yarat — başlama siqnalını gözləyirlər
        for (int i = 1; i <= serviceCount; i++) {
            final int serviceId = i;
            new Thread(() -> {
                try {
                    System.out.println("Servis-" + serviceId + " başlamağa hazır");
                    startSignal.await(); // Başlama siqnalını gözlə

                    System.out.println("Servis-" + serviceId + " işləyir...");
                    Thread.sleep((long)(Math.random() * 2000));
                    System.out.println("Servis-" + serviceId + " tamamlandı");

                    doneSignal.countDown(); // Bitdim bildir
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
            }, "Servis-" + serviceId).start();
        }

        System.out.println("Hazırlıq tamamlandı — başlama siqnalı verilir");
        Thread.sleep(500);
        startSignal.countDown(); // Hamısını eyni anda başlat (count: 1 → 0)

        System.out.println("Bütün servislər bitənə gözləyirəm...");
        doneSignal.await(); // Hamısı bitənə qədər gözlə (count: 3 → 0)

        System.out.println("Bütün servislər tamamlandı! Növbəti mərhələyə keçirik.");
    }
}
```

### Paralel Test İşlətmə

```java
public class ParallelTestRunner {
    public static void main(String[] args) throws InterruptedException {
        int testCount = 5;
        CountDownLatch allDone = new CountDownLatch(testCount);
        List<String> failures = new CopyOnWriteArrayList<>();

        ExecutorService executor = Executors.newFixedThreadPool(testCount);

        String[] tests = {"testLogin", "testCheckout", "testSearch", "testPayment", "testLogout"};

        long startTime = System.currentTimeMillis();

        for (String testName : tests) {
            executor.submit(() -> {
                try {
                    runTest(testName, failures);
                } finally {
                    allDone.countDown(); // Xəta olsa belə sayğacı azalt
                }
            });
        }

        // Bütün testlər bitənə qədər gözlə (max 30 saniyə)
        boolean finished = allDone.await(30, TimeUnit.SECONDS);

        long elapsed = System.currentTimeMillis() - startTime;
        System.out.println("Test müddəti: " + elapsed + "ms");

        if (!finished) {
            System.err.println("Testlər vaxtında tamamlanmadı!");
        }

        if (failures.isEmpty()) {
            System.out.println("Bütün testlər uğurludur!");
        } else {
            System.out.println("Uğursuz testlər: " + failures);
        }

        executor.shutdown();
    }

    static void runTest(String name, List<String> failures) {
        try {
            Thread.sleep((long)(Math.random() * 1000));
            if (Math.random() < 0.2) { // 20% xəta ehtimalı
                failures.add(name);
                System.out.println(name + " UĞURSUZ");
            } else {
                System.out.println(name + " uğurlu");
            }
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }
}
```

---

## CyclicBarrier

**CyclicBarrier** — bütün thread-lər müəyyən nöqtəyə (barrier-ə) çatana qədər hamısı gözləyir. **Yenidən istifadə edilə bilər** (cyclic).

```java
import java.util.concurrent.*;

public class CyclicBarrierDemo {
    public static void main(String[] args) {
        int partyCount = 4; // 4 thread eyni anda barrier-ə çatmalıdır

        // Barrier action — hamısı çatdıqda icra edilən hərəkət
        CyclicBarrier barrier = new CyclicBarrier(partyCount, () -> {
            System.out.println("\n=== Bütün thread-lər barrier-ə çatdı! ===\n");
        });

        // Faz 1 + Faz 2 simulyasiyası
        for (int i = 1; i <= partyCount; i++) {
            final int workerId = i;
            new Thread(() -> {
                try {
                    // FAZ 1: Məlumat yüklə
                    System.out.println("İşçi-" + workerId + ": Faz-1 başladı");
                    Thread.sleep((long)(Math.random() * 2000));
                    System.out.println("İşçi-" + workerId + ": Faz-1 tamamlandı, barrier-i gözləyir");

                    barrier.await(); // Hamısı burada gözləyir

                    // FAZ 2: Məlumatı emal et
                    System.out.println("İşçi-" + workerId + ": Faz-2 başladı");
                    Thread.sleep((long)(Math.random() * 2000));
                    System.out.println("İşçi-" + workerId + ": Faz-2 tamamlandı");

                    barrier.await(); // Yenidən istifadə!

                    // FAZ 3
                    System.out.println("İşçi-" + workerId + ": Faz-3 tamamlandı");

                } catch (InterruptedException | BrokenBarrierException e) {
                    Thread.currentThread().interrupt();
                }
            }, "İşçi-" + workerId).start();
        }
    }
}
```

### Matrix Hesablaması Paralel

```java
public class ParallelMatrixMultiplication {
    private static final int SIZE = 4; // 4x4 matris
    private static final int THREADS = 4; // Hər thread bir sıranı hesablayır
    private static final int[][] A = new int[SIZE][SIZE];
    private static final int[][] B = new int[SIZE][SIZE];
    private static final int[][] C = new int[SIZE][SIZE];

    public static void main(String[] args) throws InterruptedException {
        // Matrisləri doldur
        for (int i = 0; i < SIZE; i++)
            for (int j = 0; j < SIZE; j++) {
                A[i][j] = i + j;
                B[i][j] = i * j;
            }

        CyclicBarrier barrier = new CyclicBarrier(THREADS, () -> {
            System.out.println("Bütün sıralar hesablandı!");
        });

        for (int t = 0; t < THREADS; t++) {
            final int row = t;
            new Thread(() -> {
                // Hər thread bir sıranı hesablayır
                for (int j = 0; j < SIZE; j++) {
                    C[row][j] = 0;
                    for (int k = 0; k < SIZE; k++) {
                        C[row][j] += A[row][k] * B[k][j];
                    }
                }
                System.out.println("Sıra " + row + " tamamlandı");

                try {
                    barrier.await();
                } catch (InterruptedException | BrokenBarrierException e) {
                    Thread.currentThread().interrupt();
                }
            }).start();
        }
    }
}
```

---

## CountDownLatch vs CyclicBarrier

| Xüsusiyyət              | CountDownLatch          | CyclicBarrier              |
|-------------------------|-------------------------|----------------------------|
| Yenidən istifadə        | Xeyr (bir dəfəlik)      | Bəli (cyclic)              |
| Kim gözləyir            | Bir və ya bir neçə thread | Hamı bir-birini gözləyir  |
| Sayğac kim azaldır      | İstənilən thread        | barrier.await() çağıran   |
| Barrier action          | Xeyr                    | Bəli (hamısı çatdıqda)    |
| Broken barrier          | Yoxdur                  | BrokenBarrierException     |
| İstifadə halı           | Thread-ləri başlatmaq/gözləmək | Fazlı icra             |

```java
// CountDownLatch — "Hamı bitənə gözlə"
// Məs: 3 servis başlayana qədər əsas proqram gözləyir
CountDownLatch latch = new CountDownLatch(3);
// Thread-lər: latch.countDown()
// Əsas: latch.await()

// CyclicBarrier — "Hamı bu nöqtəyə çatana qədər hamı gözlə"
// Məs: Hər faz hamısı tamamlayana qədər növbəti faza keçilmir
CyclicBarrier barrier = new CyclicBarrier(3);
// Hər thread: barrier.await() (hamısı eyni yerdə gözləyir)
```

---

## Phaser

**Phaser** — `CyclicBarrier`-in daha çevik versiyası. Party (iştirakçı) sayı dinamik olaraq dəyişə bilər.

```java
import java.util.concurrent.*;

public class PhaserDemo {
    public static void main(String[] args) {
        Phaser phaser = new Phaser(1); // 1 party — əsas thread (registrator)

        int workerCount = 3;

        for (int i = 1; i <= workerCount; i++) {
            phaser.register(); // Hər worker üçün bir party əlavə et
            final int workerId = i;

            new Thread(() -> {
                // FAZ 0
                System.out.println("İşçi-" + workerId + ": Faz-0");
                phaser.arriveAndAwaitAdvance(); // Hamısını gözlə, faza keç

                // FAZ 1
                System.out.println("İşçi-" + workerId + ": Faz-1");
                phaser.arriveAndAwaitAdvance();

                // FAZ 2 — bəzi işçilər çıxa bilər
                if (workerId == 2) {
                    System.out.println("İşçi-" + workerId + ": Çıxıram");
                    phaser.arriveAndDeregister(); // Bu worker artıq gözlənilmir
                    return;
                }

                System.out.println("İşçi-" + workerId + ": Faz-2");
                phaser.arriveAndAwaitAdvance();

                System.out.println("İşçi-" + workerId + ": Bütün fazlar tamamlandı");
                phaser.arriveAndDeregister(); // Çıx
            }, "İşçi-" + workerId).start();
        }

        // Əsas thread fazlara nəzarət edir
        phaser.arriveAndAwaitAdvance(); // Faz-0 başla
        System.out.println("=== Faz-0 tamamlandı ===");

        phaser.arriveAndAwaitAdvance(); // Faz-1 başla
        System.out.println("=== Faz-1 tamamlandı ===");

        phaser.arriveAndAwaitAdvance(); // Faz-2
        System.out.println("=== Faz-2 tamamlandı ===");

        phaser.arriveAndDeregister();
    }
}
```

### Phaser ilə İtərativ Alqoritm

```java
// Hər iterasiyada eyni worker-lar iştirak edir
public class IterativeAlgorithm {
    public static void main(String[] args) {
        int workerCount = 4;
        int maxPhases = 5;

        Phaser phaser = new Phaser(workerCount) {
            @Override
            protected boolean onAdvance(int phase, int registeredParties) {
                System.out.println("Faz " + phase + " tamamlandı, iştirakçı: " + registeredParties);
                return phase >= maxPhases - 1; // maxPhases-dən sonra dayandır
            }
        };

        for (int i = 0; i < workerCount; i++) {
            final int workerId = i;
            new Thread(() -> {
                while (!phaser.isTerminated()) {
                    // İş görülür...
                    System.out.println("İşçi-" + workerId + ": Faz " + phaser.getPhase());
                    phaser.arriveAndAwaitAdvance(); // Növbəti faza keç
                }
                System.out.println("İşçi-" + workerId + ": Phaser dayandı");
            }).start();
        }
    }
}
```

**Phaser metodları:**

```java
Phaser p = new Phaser(3);

p.register();                   // Yeni party əlavə et
p.bulkRegister(5);              // 5 party birdən əlavə et
p.arriveAndAwaitAdvance();      // Çat + gözlə + faza keç
p.arriveAndDeregister();        // Çat + çıx (növbəti fazda gözlənilmirəm)
p.arrive();                     // Çat amma gözləmə
int phase = p.awaitAdvance(0);  // Faz 0 bitənə qədər gözlə
p.getPhase();                   // Cari faz nömrəsi
p.getRegisteredParties();       // Qeydiyyatda olan party sayı
p.getArrivedParties();          // Çatan party sayı
p.isTerminated();               // Dayandırılıbmı?
```

---

## Exchanger

**Exchanger** — İki thread arasında məlumat mübadiləsi. İki thread "görüşmə nöqtəsinə" çatana qədər gözləyir, sonra məlumat dəyişirlər.

```java
import java.util.concurrent.*;

public class ExchangerDemo {
    public static void main(String[] args) {
        Exchanger<String> exchanger = new Exchanger<>();

        // Thread 1: Məlumat göndərir, cavab alır
        Thread producer = new Thread(() -> {
            try {
                String sentData = "İstehsalçıdan məlumat";
                System.out.println("İstehsalçı göndərir: " + sentData);

                // exchange() — tərəf çatana qədər gözlə, sonra dəyiş
                String received = exchanger.exchange(sentData);

                System.out.println("İstehsalçı aldı: " + received);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        }, "İstehsalçı");

        // Thread 2: Məlumat göndərir, cavab alır
        Thread consumer = new Thread(() -> {
            try {
                String sentData = "İstehlakçıdan cavab";
                System.out.println("İstehlakçı göndərir: " + sentData);

                String received = exchanger.exchange(sentData);

                System.out.println("İstehlakçı aldı: " + received);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        }, "İstehlakçı");

        producer.start();
        consumer.start();
    }
}
```

### Buffer Doldurma/Boşaltma

```java
// Exchanger ilə ikili bufer sistemi
public class DoubleBuffering {
    public static void main(String[] args) throws InterruptedException {
        Exchanger<List<Integer>> exchanger = new Exchanger<>();

        // Doldurma thread-i
        Thread filler = new Thread(() -> {
            List<Integer> buffer = new ArrayList<>();
            try {
                for (int cycle = 0; cycle < 3; cycle++) {
                    // Buferi doldur
                    for (int i = 0; i < 5; i++) {
                        buffer.add(cycle * 5 + i);
                    }
                    System.out.println("Dolduruldu: " + buffer);

                    // Dolu buferi ver, boş buferi al
                    buffer = exchanger.exchange(buffer);
                    buffer.clear(); // Boş bufer — yenidən doldur
                }
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        });

        // Boşaltma thread-i
        Thread drainer = new Thread(() -> {
            List<Integer> buffer = new ArrayList<>(); // Boş bufer ilə başla
            try {
                for (int cycle = 0; cycle < 3; cycle++) {
                    // Boş buferi ver, dolu buferi al
                    buffer = exchanger.exchange(buffer);
                    System.out.println("İşləndi: " + buffer);
                }
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        });

        filler.start();
        drainer.start();

        filler.join();
        drainer.join();
    }
}
```

---

## İntervyu Sualları

**S: Semaphore nədir və nəyə görə istifadə olunur?**
C: Permit-lərlə resursa eyni anda daxil ola biləcək thread sayını məhdudlaşdırır. `acquire()` permit alır (yoxdursa gözləyir), `release()` permit qaytarır. Connection pool, rate limiting, resurs throttling üçün istifadə olunur.

**S: Binary semaphore vs mutex fərqi?**
C: 1-permitli semaphore mutex kimi işləyir. Fərq: Mutexdə (synchronized) yalnız lock alan thread unlock edə bilər. Semaphore-da bir thread acquire edir, başqa bir thread release edə bilər. Bu signal əməliyyatları üçün faydalıdır.

**S: `CountDownLatch` niyə yenidən istifadə edilə bilmir?**
C: Sıfıra çatdıqdan sonra count artırıla bilmir. Bu dizayn seçimidir — bir dəfəlik hadisə siqnalı üçündür. Yenidən istifadə lazımdırsa `CyclicBarrier` və ya `Phaser` istifadə et.

**S: `CyclicBarrier` faza tamamlandıqda baş action icra etmirmi?**
C: Bəli. `CyclicBarrier` konstruktorunun ikinci parametri `Runnable` — hamısı barrier-ə çatdıqda icra olunur. Bu aggregation, log yazma, fazlar arası koordinasiya üçün istifadə olunur.

**S: `Phaser` vs `CyclicBarrier` fərqi?**
C: CyclicBarrier — sabit party sayı, sadə. Phaser — dinamik party sayı (register/deregister), fazlara nəzarət (onAdvance callback), daha çevik. Çoxlu mərhələli alqoritmlər üçün Phaser daha uyğundur.

**S: `Exchanger` nə zaman faydalıdır?**
C: İki thread arasında iki-yönlü məlumat mübadiləsi lazım olduqda. Producer-Consumer bufer dəyişdirmə (double buffering), pipeline arxitekturalarında data handoff üçün.

**S: `CountDownLatch.await(timeout, unit)` nə qaytarır?**
C: `true` — count sıfıra çatdı. `false` — timeout doldu, count hələ sıfır deyil. Bu sayəsə timeout-u idarə etmək olar.

**S: `BrokenBarrierException` nə vaxt baş verir?**
C: CyclicBarrier-dəki thread-lərdən biri interrupt edildikdə və ya timeout baş verdikdə — barrier "sınır" (broken). Digər gözləyən bütün thread-lər `BrokenBarrierException` alır. `barrier.reset()` ilə yenidən istifadəyə hazır edilə bilər.
