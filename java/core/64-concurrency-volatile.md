# 64 — Concurrency: volatile Açar Sözü və Java Memory Model

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Visibility Problem — Görünürlük Problemi](#visibility-problem)
2. [volatile — Nə Zəmanət Verir?](#volatile--ne-zemanhet-verir)
3. [volatile Atomikliyi Zəmanət Vermir](#volatile-atomikligi-zemanhet-vermir)
4. [Happens-Before Münasibəti](#happens-before-munasibeti)
5. [Java Memory Model (JMM) Əsasları](#java-memory-model-jmm)
6. [Memory Barriers](#memory-barriers)
7. [volatile nə vaxt kifayətdir?](#volatile-ne-vaxt-kifayetdir)
8. [Double-Checked Locking](#double-checked-locking)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Visibility Problem — Görünürlük Problemi

Müasir CPU-lar performans üçün dəyişənləri **CPU cache**-də saxlayır. Bir thread dəyəri dəyişdirə bilər, amma digər thread köhnə cached dəyəri görməkdə davam edər.

```java
// PROBLEM — görünürlük problemi
public class VisibilityProblem {
    // volatile olmadan — JVM/CPU bu dəyəri cache-ə sala bilər
    private static boolean running = true;

    public static void main(String[] args) throws InterruptedException {
        Thread worker = new Thread(() -> {
            // Worker thread running-i öz CPU cache-indən oxuyur
            while (running) { // running=true, döngü davam edir
                // Bəzi sistemlərdə bu döngü heç vaxt bitmir!
                // Çünki worker thread running=false-u görMƏYƏ bilər
            }
            System.out.println("Worker bitdi");
        });

        worker.start();
        Thread.sleep(1000);

        running = false; // Main thread yazır — amma worker görmürsə?
        System.out.println("Main running=false etdi");

        worker.join(2000);
        System.out.println("Worker hələ işləyir: " + worker.isAlive()); // true ola bilər!
    }
}
```

**Problem niyə baş verir?**

```
CPU-1 (main thread):          CPU-2 (worker thread):
Cache: running=false          Cache: running=true  ← Köhnə dəyər!
RAM:   running=false          RAM-i oxumur (cache-dən oxuyur)
```

---

## volatile — Nə Zəmanət Verir?

`volatile` açar sözü iki şey zəmanət edir:

1. **Visibility** — Bir thread-in yazdığı dəyər digər thread-lər tərəfindən görülür (cache bypass)
2. **Ordering** — volatile dəyişən ətrafında instruction reordering baş vermir

```java
// HƏLL — volatile ilə görünürlük zəmanəti
public class VolatileSolution {
    private static volatile boolean running = true; // ← volatile!

    public static void main(String[] args) throws InterruptedException {
        Thread worker = new Thread(() -> {
            // İndi hər dəfə RAM-dan oxuyur — volatile!
            while (running) {
                // işlə
            }
            System.out.println("Worker bitdi"); // Bu çap olunacaq
        });

        worker.start();
        Thread.sleep(1000);

        running = false; // Bu dəyişiklik worker thread-ə görünəcək
        worker.join();
        System.out.println("Bütün işlər tamamlandı");
    }
}
```

**volatile necə işləyir:**

```
volatile yazma:
  1. CPU cache-dən main memory-ə flush et
  2. Digər CPU cache-lərini invalidate et (MESI protocol)

volatile oxuma:
  1. CPU cache-i keç, birbaşa main memory-dən oxu
```

---

## volatile Atomikliyi Zəmanət Vermir

```java
// YANLIŞ — volatile sayğac
public class VolatileCounter {
    private volatile int count = 0;

    // volatile görünürlük verir, amma ++ atomik deyil!
    public void increment() {
        count++; // Bu 3 addımdır: oxu, artır, yaz
        // Thread-A: count oxu (0)
        // Thread-B: count oxu (0) ← eyni anda!
        // Thread-A: count = 0 + 1 = 1 yaz
        // Thread-B: count = 0 + 1 = 1 yaz ← race condition!
        // Nəticə: 1 (olmalıydı 2)
    }
}

// DOĞRU — ya synchronized, ya da AtomicInteger
public class CorrectCounter {
    private final AtomicInteger count = new AtomicInteger(0);

    public void increment() {
        count.incrementAndGet(); // Atomik CAS əməliyyatı
    }
}
```

**volatile-in atomikliyi YALNIZ aşağıdakılar üçün keçərlidir:**
- `boolean` oxuma/yazma — atomikdir
- `int`, `long`, `double` oxuma/yazma — atomikdir (ancaq **compound action** deyil!)
- Reference oxuma/yazma — atomikdir

```java
volatile int x;
x = 42;          // Atomik — bir əməliyyat
int y = x;       // Atomik — bir əməliyyat
x++;             // ATOMIK DEYİL — üç əməliyyat: oxu, artır, yaz
x = x + y;      // ATOMIK DEYİL — compound action
```

---

## Happens-Before Münasibəti

**Happens-Before** (HB) — JMM-in qaydası: Əgər A happens-before B-dirsə, A-nın bütün dəyişiklikləri B tərəfindən görünür.

**volatile Happens-Before qaydası:**
> Volatile dəyişənə yazma, həmin dəyişənin sonrakı oxunmasından **happens-before**-dur.

```java
public class HappensBeforeDemo {
    private volatile boolean flag = false;
    private int data = 0; // volatile deyil!

    // Thread-A:
    public void writer() {
        data = 42;          // 1. data yazılır (non-volatile)
        flag = true;        // 2. flag yazılır (volatile) ← HB nöqtəsi
    }

    // Thread-B:
    public void reader() {
        if (flag) {         // 3. flag oxunur (volatile) ← HB nöqtəsi
            // flag=true görüldüsə, data=42 də görünür!
            // Çünki: (1) HB (2) HB (3) → (1) HB (3)
            System.out.println(data); // 42 çap olunur — zəmanətli!
        }
    }
}
```

**Digər HB qaydaları:**

```java
// 1. Program Order: Eyni thread-də əvvəlki əməliyyat sonrakıdan HB-dur
int x = 1; // HB
int y = 2; // bu əməliyyatdan

// 2. Monitor Lock: unlock() sonrakı lock()-dan HB-dur
synchronized (obj) {
    data = 42;
} // ← unlock (HB)

synchronized (obj) { // ← lock
    System.out.println(data); // 42 görünür
}

// 3. Thread Start: Thread.start() həmin thread-in bütün əməliyyatlarından HB-dur
data = 42;
thread.start(); // HB
// thread daxilində data=42 görünür

// 4. Thread Join: thread-in bütün əməliyyatları join()-dan HB-dur
thread.join(); // join bitdikdən sonra
// thread-in bütün yazdıqları görünür

// 5. Static Initializer: Sinif başlatma (clinit) həmin sinifin istifadəsindən HB-dur
```

---

## Java Memory Model (JMM) Əsasları

**JMM** — Java proqramlarında thread-lərin yaddaşla necə qarşılıqlı əlaqəyə girəcəyini müəyyən edir.

```
JMM Modeli:

+------------------+    +------------------+
|   Thread-A       |    |   Thread-B       |
|   Working Memory |    |   Working Memory |
|   (CPU cache)    |    |   (CPU cache)    |
+--------+---------+    +---------+--------+
         |                        |
         +----------+   +---------+
                    |   |
              +-----+---+------+
              |   Main Memory   |
              |   (RAM/Heap)    |
              +-----------------+
```

**JMM olmadan nə ola bilər?**

```java
// JVM/JIT compiler instruction reordering edə bilər:
int a = 1;      // 1
int b = 2;      // 2 — JVM 2-ni 1-dən əvvəl icra edə bilər (eyni nəticə, single-thread üçün)
int c = a + b;  // 3 — amma bu həmişə 3-dən sonra

// Çox thread-li ssenarilərdə bu reordering problem yarada bilər!
// volatile və synchronized bu reorder-i məhdudlaşdırır
```

**Reordering nümunəsi:**

```java
// YANLIŞ — reordering problemi
public class ReorderingDemo {
    static int x = 0, y = 0;
    static int a = 0, b = 0;

    public static void main(String[] args) throws InterruptedException {
        for (int i = 0; i < 1_000_000; i++) {
            x = 0; y = 0; a = 0; b = 0;

            Thread t1 = new Thread(() -> { a = 1; x = b; });
            Thread t2 = new Thread(() -> { b = 1; y = a; });

            t1.start(); t2.start();
            t1.join(); t2.join();

            // Gözlənilən nəticələr:
            // t1 əvvəl: a=1, x=1, b=1, y=1 → x=1, y=1
            // t2 əvvəl: b=1, y=0, a=1, x=1 → x=1, y=0
            // Paralel:  a=1, b=1, x=1, y=1 → x=1, y=1

            // Amma reordering ilə: x=0, y=0 da mümkündür!
            // Çünki: x=b (b=0 oxunur) əvvəl, a=1 sonra
            if (x == 0 && y == 0) {
                System.out.println("Reordering baş verdi! İterасiya: " + i);
                break;
            }
        }
    }
}
```

---

## Memory Barriers

**Memory barrier** (fence) — CPU-ya müəyyən əməliyyatların sırasını pozmamaq əmridir.

```
Load Barrier (LoadLoad):  Sonrakı oxumalar bu oxumadan sonra gəlir
Store Barrier (StoreStore): Sonrakı yazmalar bu yazmadan sonra gəlir
Full Barrier (StoreLoad):   Ən güclü — həm oxuma, həm yazma sırası
```

`volatile` bütün bu barrier-ləri əlavə edir:

```java
volatile int v;

// YAZMA əvvəl:  StoreStore barrier (yazma öncəsi dəyişiklikler görünür)
v = 42;         // volatile yazma
// YAZMA sonra: StoreLoad barrier (bu yazmadan sonra oxumalar köhnə dəyər görməz)

// OXUMA əvvəl: LoadLoad barrier
int x = v;      // volatile oxuma
// OXUMA sonra: LoadStore barrier (bu oxumadan sonra yazmalar görünür)
```

---

## volatile nə vaxt kifayətdir?

```java
// KİFAYƏTDİR — Sadə flag (yalnız bir thread yazır, digərlər oxuyur)
public class ServiceManager {
    private volatile boolean shutdownRequested = false;

    public void requestShutdown() {
        shutdownRequested = true; // Yalnız bir thread çağırır
    }

    public void run() {
        while (!shutdownRequested) { // Çoxlu thread oxuya bilər
            doWork();
        }
    }
}

// KİFAYƏTDİR — Bir dəfəlik yayım (publication)
public class ImmutableHolder {
    private volatile String value = null; // Yalnız bir dəfə set olunur

    public void publish(String v) {
        this.value = v; // Yalnız bir dəfə
    }

    public String get() {
        return value; // Oxuma
    }
}

// KİFAYƏT DEYİL — Compound action
public class UnsafeVolatileCounter {
    private volatile int count = 0;

    public void increment() {
        count++; // Atomik deyil! synchronized və ya AtomicInteger lazımdır
    }
}

// KİFAYƏT DEYİL — Check-then-act
public class UnsafeLazySingleton {
    private static volatile Singleton instance;

    // Bu YANLIŞ — check-then-act atomik deyil
    public static Singleton getInstance_WRONG() {
        if (instance == null) {
            instance = new Singleton(); // Race condition!
        }
        return instance;
    }

    // DOĞRU — double-checked locking
    public static Singleton getInstance_CORRECT() {
        if (instance == null) {
            synchronized (UnsafeLazySingleton.class) {
                if (instance == null) {
                    instance = new Singleton();
                }
            }
        }
        return instance;
    }
}
```

**Qiymətləndirmə:**

| Ssenari                          | volatile | synchronized/Atomic |
|----------------------------------|----------|---------------------|
| Sadə flag (bir yazıcı)           | ✓        |                     |
| Sayğac (çoxlu yazıcı)            |          | ✓ (AtomicInteger)   |
| Compound action (check-then-act) |          | ✓ (synchronized)    |
| Lazy initialization              |          | ✓ (DCL və ya Holder)|
| İki dəyişənin atomik yenilənməsi |          | ✓ (synchronized)    |

---

## Double-Checked Locking

`volatile` olmadan DCL **işləmir** — JVM başlatma əməliyyatını reorder edə bilər.

```java
// YANLIŞ — volatile olmadan DCL (Java 5-dən əvvəl işləmirdi)
public class BrokenSingleton {
    private static BrokenSingleton instance; // volatile YOX!

    public static BrokenSingleton getInstance() {
        if (instance == null) {                         // Yoxlama
            synchronized (BrokenSingleton.class) {
                if (instance == null) {
                    instance = new BrokenSingleton();   // Reorder problemi!
                    // JVM bunları bu sırada icra edə bilər:
                    // 1. Yaddaş ayır
                    // 2. instance-ə null olmayan pointer yaz ← başqa thread görür!
                    // 3. Konstruktoru çağır
                    // Thread-B instance != null görür, amma tam başlatılmamış obyekti alır!
                }
            }
        }
        return instance;
    }
}

// DOĞRU — volatile ilə DCL (Java 5+)
public class CorrectSingleton {
    private static volatile CorrectSingleton instance; // volatile!

    private CorrectSingleton() {}

    public static CorrectSingleton getInstance() {
        if (instance == null) {              // 1. Yoxlama (lock olmadan — sürətli yol)
            synchronized (CorrectSingleton.class) {
                if (instance == null) {      // 2. Yoxlama (lock içində — təhlükəsiz)
                    instance = new CorrectSingleton(); // volatile — reorder yoxdur
                }
            }
        }
        return instance;
    }
}

// DAHA YAXŞI — Initialization-on-demand Holder (volatile lazım deyil!)
public class HolderSingleton {
    private HolderSingleton() {}

    // Inner sinif yalnız first access zamanı yüklənir — thread-safe (JVM zəmanəti)
    private static class Holder {
        static final HolderSingleton INSTANCE = new HolderSingleton();
    }

    public static HolderSingleton getInstance() {
        return Holder.INSTANCE;
    }
}
```

---

## Praktik Nümunə: Düzgün State Maşını

```java
public class ServiceLifecycle {
    // volatile — vəziyyət bir thread-dən digərinə görünür
    private volatile State state = State.STOPPED;

    enum State { STOPPED, STARTING, RUNNING, STOPPING }

    // Başlatma (Admin thread çağırır)
    public synchronized void start() {
        if (state != State.STOPPED) {
            throw new IllegalStateException("Xidmət artıq işləyir: " + state);
        }
        state = State.STARTING;

        new Thread(() -> {
            initialize(); // Başlatma işi
            state = State.RUNNING; // volatile — bütün thread-lər görür
        }).start();
    }

    // Dayandırma
    public synchronized void stop() {
        if (state != State.RUNNING) return;
        state = State.STOPPING;

        new Thread(() -> {
            cleanup();
            state = State.STOPPED; // volatile
        }).start();
    }

    // Worker thread-lər hər iterasiyada yoxlayır
    public void doWork() {
        while (state == State.RUNNING) { // volatile oxuma — həmişə təzə
            processNextItem();
        }
    }

    private void initialize() { System.out.println("Başladılır..."); }
    private void cleanup() { System.out.println("Dayandırılır..."); }
    private void processNextItem() { /* işlə */ }
}
```

---

## İntervyu Sualları

**S: `volatile` nə zəmanət verir?**
C: 1) Visibility — volatile dəyişənə yazılan dəyər digər thread-lər tərəfindən görülür (cache-i bypass edir). 2) Ordering — volatile ətrafında instruction reordering olmur (happens-before).

**S: `volatile` atomikliyi zəmanət verirmi?**
C: Xeyr! Sadə oxuma/yazma atomikdir, amma compound action (`count++`, check-then-act) atomik deyil. Bunlar üçün `synchronized` və ya `AtomicInteger` lazımdır.

**S: `volatile` vs `synchronized` fərqi?**
C: synchronized — mutual exclusion + visibility + atomiklik. volatile — yalnız visibility + ordering (lock yoxdur, mutual exclusion yoxdur). synchronized daha güclüdür, volatile daha yüngüldür.

**S: Happens-before nədir?**
C: JMM qaydası. A HB B deməkdir — A-nın bütün dəyişiklikləri B tərəfindən görünür. volatile yazma sonrakı volatile oxumadan HB-dur. synchronized unlock sonrakı lock-dan HB-dur.

**S: Double-checked locking-də niyə `volatile` lazımdır?**
C: JVM obyekt yaratmanı reorder edə bilər: 1. Yaddaş ayır, 2. Pointeri yaz (instance != null), 3. Konstruktoru çağır. volatile olmadan başqa thread yarı başladılmış obyekti görə bilər. volatile reorder-i önləyir.

**S: CPU cache invalidation necə işləyir?**
C: MESI protocol: Modified, Exclusive, Shared, Invalid. volatile yazma zamanı CPU digər core-ların cache xəttini "Invalid" olaraq işarələyir. Digər core-lar növbəti oxumada main memory-dən oxumalıdır.

**S: `long` və `double` niyə xüsusidir?**
C: 32-bit JVM-lərdə `long` (64-bit) yazma/oxuma iki 32-bit əməliyyata bölünə bilər — non-atomic. volatile `long`/`double` isə atomikdir. 64-bit JVM-lərdə bu adətən problem deyil, amma standarta görə volatile tövsiyə olunur.

**S: Visibility problemi nədir?**
C: Thread-lər dəyişənləri CPU cache-də saxlayır. Bir thread dəyişdirdikdə digər thread-lərin cache-i köhnə dəyəri saxlaya bilər. volatile cache bypass edərək həmişə main memory ilə sinxronizasiya edir.
