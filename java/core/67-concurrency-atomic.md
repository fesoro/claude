# 67 — Concurrency: Atomic Variables

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [Niyə Atomic?](#niye-atomic)
2. [CAS — Compare-And-Swap](#cas--compare-and-swap)
3. [AtomicInteger, AtomicLong, AtomicBoolean](#atomicinteger-atomiclong-atomicboolean)
4. [AtomicReference](#atomicreference)
5. [AtomicIntegerArray / AtomicReferenceArray](#atomicintegerarray)
6. [LongAdder vs AtomicLong](#longadder-vs-atomiclong)
7. [VarHandle (Java 9+)](#varhandle)
8. [Praktik Nümunələr](#praktik-numuneler)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə Atomic?

`synchronized` lock istifadə edir — yavaş, thread-lər bloklanır. **Atomic** siniflər lock-free CAS əməliyyatları ilə işləyir — daha sürətli.

```java
// YANLIŞ — race condition var
public class UnsafeCounter {
    private int count = 0;

    public void increment() {
        count++; // Atomik deyil! 3 addım: oxu, artır, yaz
    }
}

// synchronized ilə — düzgün amma lock var
public class SynchronizedCounter {
    private int count = 0;

    public synchronized void increment() {
        count++;
    }
}

// DOĞRU — lock-free, sürətli
import java.util.concurrent.atomic.*;

public class AtomicCounter {
    private final AtomicInteger count = new AtomicInteger(0);

    public void increment() {
        count.incrementAndGet(); // Lock-free, atomik!
    }

    public int get() {
        return count.get();
    }
}
```

---

## CAS — Compare-And-Swap

**CAS** — hardware səviyyəsindəki atomik əməliyyat. `compareAndSet(expected, newValue)`:
- "Əgər cari dəyər **expected**-ə bərabərdirsə, onu **newValue** ilə dəyişdir, true qaytar"
- "Əgər deyilsə, heç nə etmə, false qaytar"

```java
// CAS-ın simulyasiyası (real halda hardware əməliyyatıdır)
// Əsl CAS atomikdir — interrupt oluna bilməz!
public class SimulatedCAS {
    private volatile int value;

    // Bu atomik deyil — sadəcə konsepti göstərir
    public synchronized boolean compareAndSwap(int expected, int newValue) {
        if (value == expected) {
            value = newValue;
            return true;
        }
        return false; // Başqa thread dəyişdirdi
    }
}

// AtomicInteger ilə real CAS:
AtomicInteger ai = new AtomicInteger(10);

// Cari dəyər 10-dursa, 20 et
boolean success = ai.compareAndSet(10, 20);
System.out.println("Uğurlu: " + success + ", Dəyər: " + ai.get()); // true, 20

// İndi dəyər 20 — 10-la müqayisə uğursuz olacaq
success = ai.compareAndSet(10, 30);
System.out.println("Uğurlu: " + success + ", Dəyər: " + ai.get()); // false, 20
```

**CAS əsaslı incrementAndGet-in daxili işi:**

```java
// AtomicInteger.incrementAndGet()-in pseudokodu:
public int incrementAndGet() {
    while (true) {
        int current = get();         // Cari dəyəri oxu
        int next = current + 1;      // Yeni dəyəri hesabla
        if (compareAndSet(current, next)) { // CAS cəhdi
            return next;             // Uğurlu — yeni dəyəri qaytar
        }
        // Uğursuz (başqa thread dəyişdirdi) — yenidən cəhd et (spin)
    }
}
```

**ABA problemi:**

```java
// ABA: Dəyər A → B → A olur. CAS A-nı görür, sanki dəyişməyib
// Həll: AtomicStampedReference — versiya nömrəsi əlavə edir
AtomicStampedReference<String> ref =
    new AtomicStampedReference<>("A", 0); // dəyər + stamp (versiya)

int[] stampHolder = new int[1];
String value = ref.get(stampHolder);
int currentStamp = stampHolder[0];

// CAS: dəyər "A" VƏ stamp 0 olduqda dəyiş
boolean updated = ref.compareAndSet("A", "B", currentStamp, currentStamp + 1);
// İndi stamp 1 — əgər kimsə A→B→A etsə, stamp artır → CAS uğursuz olur
```

---

## AtomicInteger, AtomicLong, AtomicBoolean

```java
import java.util.concurrent.atomic.*;

public class AtomicBasicsDemo {
    public static void main(String[] args) {
        // AtomicInteger
        AtomicInteger ai = new AtomicInteger(0);

        System.out.println(ai.get());                   // 0 — cari dəyər
        System.out.println(ai.getAndIncrement());       // 0 — əvvəlki qaytarır, sonra artırır
        System.out.println(ai.incrementAndGet());       // 2 — əvvəlcə artırır, sonra qaytarır
        System.out.println(ai.getAndAdd(5));            // 2 — əvvəlki qaytarır, 5 əlavə edir
        System.out.println(ai.get());                   // 7

        System.out.println(ai.getAndDecrement());       // 7 — azaldır
        System.out.println(ai.decrementAndGet());       // 5

        ai.set(100);                                    // Birbaşa set
        System.out.println(ai.getAndSet(200));          // 100 — köhnəni qaytarır, yenisini qoyur

        // Java 8+ — updateAndGet (funksional)
        System.out.println(ai.updateAndGet(x -> x * 2)); // 400
        System.out.println(ai.accumulateAndGet(10, Integer::sum)); // 410

        // AtomicLong — eyni əməliyyatlar, long üçün
        AtomicLong al = new AtomicLong(0L);
        al.incrementAndGet();

        // AtomicBoolean — flag idarəsi üçün
        AtomicBoolean flag = new AtomicBoolean(false);

        // Yalnız bir thread bu bloku ilk dəfə icra edir
        if (flag.compareAndSet(false, true)) {
            System.out.println("İlk dəfə icra olunur — initialization");
        } else {
            System.out.println("Artıq icra olunmuşdur");
        }

        // AtomicBoolean-ın tipik istifadəsi: bir dəfəlik başlatma
        AtomicBoolean initialized = new AtomicBoolean(false);
        if (initialized.compareAndSet(false, true)) {
            // Bu blok yalnız BİR THREAD tərəfindən icra ediləcək
            initializeResources();
        }
    }

    static void initializeResources() {
        System.out.println("Resurslar başladıldı");
    }
}
```

---

## AtomicReference

```java
import java.util.concurrent.atomic.*;

public class AtomicReferenceDemo {
    record User(String name, int age) {}

    public static void main(String[] args) {
        AtomicReference<User> currentUser = new AtomicReference<>(new User("Orkhan", 28));

        // Cari istifadəçini al
        User user = currentUser.get();
        System.out.println("Cari: " + user);

        // CAS ilə atomik dəyişdirmə
        User oldUser = currentUser.get();
        User newUser = new User(oldUser.name(), oldUser.age() + 1); // Yaşı artır

        boolean updated = currentUser.compareAndSet(oldUser, newUser);
        System.out.println("Yeniləndi: " + updated + ", Yeni: " + currentUser.get());

        // updateAndGet — funksional yeniləmə
        currentUser.updateAndGet(u -> new User(u.name().toUpperCase(), u.age()));
        System.out.println("Sonra: " + currentUser.get());

        // Lock-free stack nümunəsi
        LockFreeStack<Integer> stack = new LockFreeStack<>();
        stack.push(1);
        stack.push(2);
        stack.push(3);
        System.out.println("Pop: " + stack.pop()); // 3
        System.out.println("Pop: " + stack.pop()); // 2
    }
}

// AtomicReference ilə lock-free stack
class LockFreeStack<T> {
    private static class Node<T> {
        T value;
        Node<T> next;
        Node(T value, Node<T> next) {
            this.value = value;
            this.next = next;
        }
    }

    private final AtomicReference<Node<T>> top = new AtomicReference<>(null);

    public void push(T value) {
        while (true) {
            Node<T> currentTop = top.get();
            Node<T> newNode = new Node<>(value, currentTop);
            if (top.compareAndSet(currentTop, newNode)) {
                return; // Uğurlu
            }
            // Uğursuz — başqa thread dəyişdirdi, yenidən cəhd et
        }
    }

    public T pop() {
        while (true) {
            Node<T> currentTop = top.get();
            if (currentTop == null) return null; // Boş stack
            if (top.compareAndSet(currentTop, currentTop.next)) {
                return currentTop.value;
            }
        }
    }
}
```

---

## AtomicIntegerArray

```java
import java.util.concurrent.atomic.*;

public class AtomicArrayDemo {
    public static void main(String[] args) throws InterruptedException {
        // Thread-safe massiv — hər element ayrı-ayrılıqda atomik əməliyyat dəstəkləyir
        AtomicIntegerArray array = new AtomicIntegerArray(10); // 10 elementli, 0 ilə başlayır

        // Elementlərə atomik əməliyyatlar
        array.set(0, 100);
        System.out.println(array.get(0));              // 100
        System.out.println(array.getAndIncrement(0));  // 100
        System.out.println(array.incrementAndGet(1));  // 1
        System.out.println(array.compareAndSet(0, 101, 200)); // true

        // Hər indeks üçün müstəqil atomik — paralel yeniləmə
        int[] indices = {0, 1, 2, 3, 4};
        Thread[] threads = new Thread[5];

        for (int i = 0; i < 5; i++) {
            final int idx = i;
            threads[i] = new Thread(() -> {
                for (int j = 0; j < 1000; j++) {
                    array.incrementAndGet(idx); // Hər thread öz indeksini artırır
                }
            });
            threads[i].start();
        }

        for (Thread t : threads) t.join();

        System.out.println("Hər element 1000 olmalıdır:");
        for (int i = 0; i < 5; i++) {
            System.out.println("array[" + i + "] = " + array.get(i)); // 1000
        }
    }
}
```

---

## LongAdder vs AtomicLong

Yüksək yükdə `AtomicLong` çox CAS uğursuzluğu (spin) yaşayır. `LongAdder` — daxili olaraq bölünmüş sayğaclar saxlayır, `sum()` çağrıldıqda cəmləyir.

```java
import java.util.concurrent.atomic.*;
import java.util.concurrent.*;

public class LongAdderVsAtomicLong {
    public static void main(String[] args) throws InterruptedException {
        int threadCount = 16;
        int iterations = 1_000_000;
        ExecutorService exec = Executors.newFixedThreadPool(threadCount);

        // AtomicLong — yüksək contention-da yavaş
        AtomicLong atomicLong = new AtomicLong(0);
        long start1 = System.currentTimeMillis();
        CountDownLatch latch1 = new CountDownLatch(threadCount);
        for (int i = 0; i < threadCount; i++) {
            exec.submit(() -> {
                for (int j = 0; j < iterations; j++) {
                    atomicLong.incrementAndGet(); // Yüksək contention!
                }
                latch1.countDown();
            });
        }
        latch1.await();
        System.out.println("AtomicLong: " + (System.currentTimeMillis() - start1) + "ms, Dəyər: " + atomicLong.get());

        // LongAdder — yüksək contention-da daha sürətli
        LongAdder longAdder = new LongAdder();
        long start2 = System.currentTimeMillis();
        CountDownLatch latch2 = new CountDownLatch(threadCount);
        for (int i = 0; i < threadCount; i++) {
            exec.submit(() -> {
                for (int j = 0; j < iterations; j++) {
                    longAdder.increment(); // Az contention — hər thread öz hücrəsinə yazır
                }
                latch2.countDown();
            });
        }
        latch2.await();
        System.out.println("LongAdder:  " + (System.currentTimeMillis() - start2) + "ms, Dəyər: " + longAdder.sum());

        exec.shutdown();
    }
}
```

**Fərqlər:**

| Xüsusiyyət          | AtomicLong            | LongAdder               |
|---------------------|-----------------------|-------------------------|
| Cari dəyər alma     | `get()` — dəqiq       | `sum()` — approximate*  |
| İnkrement           | `incrementAndGet()`   | `increment()`           |
| Contention-da       | Yavaş (CAS retry)     | Sürətli (ayrı hücrələr) |
| Yaddaş              | Az                    | Biraz çox               |
| CAS+get atomik      | Bəli                  | Xeyr                    |
| İstifadə halı       | CAS-la oxuma lazımdırsa | Yalnız sayma üçün     |

*`sum()` — `add()` əməliyyatları davam edərkən approximate ola bilər.

**LongAccumulator** — xüsusi funksiya ilə:

```java
// LongAccumulator — max, min, xor, hər hansı ikili əməliyyat
LongAccumulator maxAccumulator = new LongAccumulator(Long::max, Long.MIN_VALUE);
maxAccumulator.accumulate(42);
maxAccumulator.accumulate(17);
maxAccumulator.accumulate(99);
System.out.println("Max: " + maxAccumulator.get()); // 99

LongAccumulator sumAccumulator = new LongAccumulator(Long::sum, 0);
```

---

## VarHandle

Java 9-dan gəlir. `AtomicInteger` və ya `Unsafe` olmadan field üzərindəki atomik əməliyyatlar üçün.

```java
import java.lang.invoke.*;

public class VarHandleDemo {
    private volatile int value = 0; // volatile VarHandle üçün lazımdır

    // VarHandle-ı static field olaraq saxla
    private static final VarHandle VALUE_HANDLE;

    static {
        try {
            VALUE_HANDLE = MethodHandles.lookup()
                .findVarHandle(VarHandleDemo.class, "value", int.class);
        } catch (ReflectiveOperationException e) {
            throw new ExceptionInInitializerError(e);
        }
    }

    // Atomik əməliyyatlar VarHandle vasitəsilə
    public int incrementAndGet() {
        return (int) VALUE_HANDLE.getAndAdd(this, 1) + 1;
    }

    public boolean compareAndSet(int expected, int newValue) {
        return VALUE_HANDLE.compareAndSet(this, expected, newValue);
    }

    public int getVolatile() {
        return (int) VALUE_HANDLE.getVolatile(this);
    }

    public void setVolatile(int newValue) {
        VALUE_HANDLE.setVolatile(this, newValue);
    }

    // Relaxed (reorder icazəsi verilir — yalnız single-threaded ssenarilərdə)
    public int getOpaque() {
        return (int) VALUE_HANDLE.getOpaque(this);
    }

    public static void main(String[] args) {
        VarHandleDemo demo = new VarHandleDemo();
        System.out.println(demo.incrementAndGet()); // 1
        System.out.println(demo.incrementAndGet()); // 2
        System.out.println(demo.compareAndSet(2, 100)); // true
        System.out.println(demo.getVolatile()); // 100
    }
}
```

**VarHandle üstünlükləri:**
- `AtomicInteger` wrapper olmadan birbaşa field üzərindədir (az yaddaş)
- `sun.misc.Unsafe` əvəzinə — standart API
- Fərqli consistency modeları: `getVolatile`, `getOpaque`, `getAcquire`, `getRelease`

---

## Praktik Nümunələr

### Thread-safe Statistika Toplayıcı

```java
import java.util.concurrent.atomic.*;
import java.util.concurrent.*;

public class RequestStatistics {
    private final AtomicLong totalRequests = new AtomicLong(0);
    private final AtomicLong failedRequests = new AtomicLong(0);
    private final AtomicLong totalResponseTimeMs = new AtomicLong(0);
    private final LongAdder concurrentRequests = new LongAdder(); // Anlık sayğac

    public void recordRequest(boolean success, long responseTimeMs) {
        totalRequests.incrementAndGet();
        if (!success) failedRequests.incrementAndGet();
        totalResponseTimeMs.addAndGet(responseTimeMs);
    }

    public void requestStarted() { concurrentRequests.increment(); }
    public void requestFinished() { concurrentRequests.decrement(); }

    public void printStats() {
        long total = totalRequests.get();
        long failed = failedRequests.get();
        long totalTime = totalResponseTimeMs.get();

        System.out.printf("""
            === Sorğu Statistikası ===
            Cəmi sorğu:     %d
            Uğursuz sorğu:  %d (%.1f%%)
            Orta cavab:     %.1f ms
            Aktiv sorğu:    %d
            """,
            total,
            failed,
            total > 0 ? (double) failed / total * 100 : 0,
            total > 0 ? (double) totalTime / total : 0,
            concurrentRequests.sum()
        );
    }
}
```

### Lock-free Singleton

```java
public class LockFreeSingleton {
    // volatile — visibility zəmanəti
    private static volatile LockFreeSingleton instance;

    private LockFreeSingleton() {}

    // Double-checked locking — thread-safe, performanslı
    public static LockFreeSingleton getInstance() {
        if (instance == null) { // Birinci yoxlama — lock olmadan (sürətli yol)
            synchronized (LockFreeSingleton.class) {
                if (instance == null) { // İkinci yoxlama — lock içində
                    instance = new LockFreeSingleton();
                }
            }
        }
        return instance;
    }

    // Alternativ: AtomicReference ilə (daha az istifadə olunur)
    private static final AtomicReference<LockFreeSingleton> atomicInstance =
        new AtomicReference<>(null);

    public static LockFreeSingleton getInstanceAtomic() {
        LockFreeSingleton current = atomicInstance.get();
        if (current != null) return current;

        LockFreeSingleton newInstance = new LockFreeSingleton();
        return atomicInstance.compareAndSet(null, newInstance)
            ? newInstance
            : atomicInstance.get(); // Başqa thread artıq qoyub
    }
}
```

---

## İntervyu Sualları

**S: Atomic siniflər necə işləyir?**
C: CAS (Compare-And-Swap) hardware əməliyyatı əsasında. Lock istifadə etmir. `compareAndSet(expected, new)` — cari dəyər expected-ə bərabərdirsə new ilə dəyişir, əks halda false qaytarır. Uğursuz olduqda spin loop ilə yenidən cəhd edir.

**S: `AtomicInteger.incrementAndGet()` atomikdir?**
C: Bəli! CAS loop ilə implementasiya edilib. Nəticə həmişə doğrudur, lakin yüksək contention-da çox retry ola bilər.

**S: `LongAdder` niyə `AtomicLong`-dan sürətlidir?**
C: LongAdder daxili olaraq bir neçə hücrə saxlayır (Striped64). Hər thread öz hücrəsinə yazır — az CAS uğursuzluğu. `sum()` bütün hücrələri cəmləyir. Yüksək contention-da daha sürətli, lakin dəqiq anlık dəyər almaq olmaz.

**S: ABA problemi nədir?**
C: Dəyər A idi → B oldu → A oldu. CAS yoxlama zamanı A görür, sanki dəyişməyib qəbul edir — halbuki dəyişibdi. `AtomicStampedReference` versiya (stamp) nömrəsi əlavə edərək bunu həll edir.

**S: `AtomicReference` vs `volatile` fərqi?**
C: `volatile` — visibility zəmanəti, yazma atomikdir, amma CAS dəstəkləmir. `AtomicReference` — CAS (compareAndSet) dəstəkləyir, lock-free conditional update mümkündür.

**S: VarHandle nə üçün lazımdır?**
C: Java 9-dan AtomicInteger wrapper olmadan birbaşa field üzərindəki atomik əməliyyatlar üçün. `sun.misc.Unsafe`-in standart alternativi. Fərqli memory consistency modeları var.

**S: `getAndIncrement()` vs `incrementAndGet()` fərqi?**
C: `getAndIncrement()` — əvvəlki dəyəri qaytarır, sonra artırır (post-increment). `incrementAndGet()` — əvvəlcə artırır, sonra yeni dəyəri qaytarır (pre-increment).
