# Atomic Operations (Lead ⭐⭐⭐⭐)

## İcmal
Atomic operation — başqa thread-lərin arasına girə bilmədiyi, ya tam icra edilən, ya da ümumiyyətlə icra edilməyən əməliyyatdır. Lock olmadan thread-safe kod yazmanın əsasıdır. CAS (Compare-And-Swap) instruction CPU səviyyəsindədir — lock-un overhead-i olmadan atomic davranış. Lead interview-larda hardware-dan başlayaraq Java `AtomicInteger`, Go atomics, lock-free struktur dizaynına qədər soruşulur.

## Niyə Vacibdir
Lock-free proqramlamanın fundamentidir. İnterviewer bu sualla sizin lock-free kod yazmağın niyə çətin olduğunu, ABA problemini, memory ordering-in nə demək olduğunu, Java `java.util.concurrent.atomic` paketini, və CAS-ın compare-and-swap loop (spin) ilə işlədiyini bildiyinizi yoxlayır. Metrics counter-lər, rate limiter-lər, sequence generator-lar — hamısı atomic operations üzərindədir.

## Əsas Anlayışlar

- **Atomicity:** Əməliyyat bölünməzdir — ya hamısı, ya heç biri görünür
- **CAS (Compare-And-Swap):** "Dəyər hələ X-dirsə, Y et" — CPU atomic instruction (`CMPXCHG`)
- **Spin Loop / CAS Loop:** CAS uğursuz olarsa, yenidən cəhd et — lock-free, lakin CPU istehlak edir
- **ABA Problem:** Dəyər A→B→A dəyişir, CAS A-nı görür — dəyişiklik olmadığını sanır; yanlış!
- **Stamped Reference / Version Counter:** ABA-nı həll edir — dəyər + versiya birlikdə müqayisə olunur
- **Memory Ordering:** Compiler/CPU instruction-ları reorder edir — `volatile`, `fence`, `barrier` ilə qarşısını al
- **Happens-Before:** Java Memory Model-in qaydası — yazma "happens-before" oxumadan əvvəl olduğunu qarantiya verir
- **`volatile` (Java):** Visibility qarantiyası verir — CAS garantisi vermir; lakin read/write atomikdir
- **`AtomicInteger` (Java):** CAS əsaslı counter — `incrementAndGet()`, `compareAndSet()`
- **`AtomicReference<T>` (Java):** Object reference-ı atomik dəyişdirmək
- **`AtomicStampedReference<T>`:** ABA həlli — value + stamp (version) birlikdə
- **`LongAdder` (Java):** High-contention counter — `AtomicLong`-dan daha performanslıdır; striping
- **`sync/atomic` (Go):** `AddInt64`, `CompareAndSwapInt64`, `LoadPointer` — low-level atomic
- **`fetch_add` (C/C++):** `std::atomic<int>::fetch_add` — LLVM/GCC atomic built-in
- **Relaxed / Acquire / Release / SeqCst:** Memory ordering növləri — C++ ve Rust-da açıq göstərilir
- **False Sharing:** Eyni cache line-da olan iki atomic dəyişən — fərqli thread-lər cache line-ı "öldürür"

## Praktik Baxış

**Interview-da yanaşma:**
- Əvvəlcə "niyə lock lazım deyil?" — CAS hardware atomic, OS mutex deyil
- ABA problemi soruşulacaq — `AtomicStampedReference` ilə həll
- `LongAdder` vs `AtomicLong` fərqini bilmək Lead səviyyəsini göstərir

**Follow-up suallar:**
- "ABA problemi nədir? Real ssenari göstər." — Linked list node-un pointer-i
- "LongAdder niyə AtomicLong-dan sürətlidir?" — Striping: thread-ləri separate cell-lərə yönləndirir
- "volatile = atomic?" — Xeyr; visibility ≠ atomicity; `i++` non-atomic

**Ümumi səhvlər:**
- `volatile` ilə atomic-i qarışdırmaq — `volatile int i; i++` race condition-dır
- Spin loop-da exponential backoff-u bilməmək
- ABA problemini görmürdüm demək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- `LongAdder`-ı bilmək — high-contention metrics counter üçün ideal
- Memory ordering (happens-before, acquire/release) nüanslarını izah etmək
- False sharing-i qeyd etmək — cache line alignment

## Nümunələr

### Tipik Interview Sualı
"Thread-safe counter lazımdır, lakin Mutex istifadə etmək istəmirsiniz. Necə yazarsınız? ABA problemi nədir?"

### Güclü Cavab
Lock-free counter üçün `AtomicInteger.incrementAndGet()` istifadə edilir — daxilən CAS (Compare-And-Swap) instruction-u ilə işləyir. `i++` üç addımdır: oxu, artır, yaz — thread-lərin arasına girə bilər. CAS isə hardware səviyyəsında bölünməzdir: "dəyər hələ 5-dirsə, 6 et; deyilsə, yenidən cəhd et." Hətta spin loop belə mutex-dən sürətlidir — OS context switch yoxdur. ABA problemi: thread A, dəyəri A oxuyur; B, A→B→A dəyişir; A CAS-ı uğurla icra edir — sanki heç nə olmayıb. Linked list-də bu pointer corruption yaradır. Həll: `AtomicStampedReference` — dəyər + versiya counter birlikdə müqayisə olunur, ABA artıq keçmir. Yüksək contention ssenarisi üçün `LongAdder` daha yaxşıdır — thread-ləri ayrı cell-lərə yönləndirir, sonra cəmləyir.

### Kod Nümunəsi
```java
// Java: AtomicInteger — lock-free counter
import java.util.concurrent.atomic.*;

public class AtomicDemo {

    // YANLIŞ: volatile int — visibility var, amma atomicity yox
    volatile int badCounter = 0;
    // badCounter++ → read + increment + write → üç ayrı addım!

    // DOĞRU: AtomicInteger — CAS əsaslı
    AtomicInteger counter = new AtomicInteger(0);

    public void increment() {
        counter.incrementAndGet(); // Atomic, thread-safe
    }

    // CAS əl ilə — spin loop
    public boolean updateIfEquals(AtomicInteger ref, int expected, int newVal) {
        while (true) {
            int current = ref.get();
            if (current != expected) return false;
            if (ref.compareAndSet(current, newVal)) return true;
            // CAS uğursuz — başqa thread dəyərdi, yenidən cəhd et
        }
    }

    // AtomicReference — object reference-ı dəyişdirmək
    AtomicReference<String> configRef = new AtomicReference<>("v1");

    public void reloadConfig(String oldConfig, String newConfig) {
        if (!configRef.compareAndSet(oldConfig, newConfig)) {
            System.out.println("Config already changed by another thread");
        }
    }
}
```

```java
// ABA Problemi və həlli
import java.util.concurrent.atomic.AtomicStampedReference;

public class ABADemo {

    // ABA PROBLEMI: AtomicReference ilə
    static AtomicReference<Integer> ref = new AtomicReference<>(1);

    static void abaScenario() throws InterruptedException {
        // Thread A: dəyər 1-i görür
        int observed = ref.get();  // 1

        // Thread B: 1 → 2 → 1 dəyişdirir (məsələn, stack push/pop)
        Thread b = new Thread(() -> {
            ref.set(2);  // A→B
            ref.set(1);  // B→A  (ABA!)
        });
        b.start();
        b.join();

        // Thread A: CAS uğurlu — sanki heç nə olmayıb!
        boolean success = ref.compareAndSet(observed, 99);
        System.out.println("CAS success: " + success);  // true — yanlış!
    }

    // HƏLL: AtomicStampedReference — value + version
    static AtomicStampedReference<Integer> stampedRef =
        new AtomicStampedReference<>(1, 0);  // (value, stamp)

    static void abaSolution() throws InterruptedException {
        int[] stampHolder = new int[1];
        int observed = stampedRef.get(stampHolder);   // dəyər + stamp
        int observedStamp = stampHolder[0];            // stamp = 0

        Thread b = new Thread(() -> {
            int[] sh = new int[1];
            int v = stampedRef.get(sh);
            stampedRef.compareAndSet(v, 2, sh[0], sh[0] + 1);  // stamp: 0→1
            v = stampedRef.get(sh);
            stampedRef.compareAndSet(v, 1, sh[0], sh[0] + 1);  // stamp: 1→2
        });
        b.start();
        b.join();

        // Thread A: CAS uğursuz — stamp dəyişib (0 ≠ 2)!
        boolean success = stampedRef.compareAndSet(observed, 99, observedStamp, observedStamp + 1);
        System.out.println("CAS success: " + success);  // false — düzgün!
    }
}
```

```java
// LongAdder vs AtomicLong — high-contention benchmark
import java.util.concurrent.atomic.*;
import java.util.concurrent.*;

public class CounterBenchmark {

    public static void main(String[] args) throws Exception {
        int threads = 16;
        int iterations = 1_000_000;

        // AtomicLong — tək CAS cell, high contention
        AtomicLong atomicLong = new AtomicLong(0);
        long t1 = benchmark(threads, iterations, () -> atomicLong.incrementAndGet());

        // LongAdder — striped cells, az contention
        // Thread-ləri ayrı cell-lərə yönləndirir, sum() zamanı cəmləyir
        LongAdder longAdder = new LongAdder();
        long t2 = benchmark(threads, iterations, () -> longAdder.increment());

        System.out.println("AtomicLong:  " + t1 + "ms");
        System.out.println("LongAdder:   " + t2 + "ms"); // 3-5x daha sürətli
        System.out.println("LongAdder sum: " + longAdder.sum());
    }

    static long benchmark(int threads, int iters, Runnable op) throws Exception {
        ExecutorService exec = Executors.newFixedThreadPool(threads);
        long start = System.currentTimeMillis();
        List<Future<?>> futures = new ArrayList<>();
        for (int i = 0; i < threads; i++) {
            futures.add(exec.submit(() -> {
                for (int j = 0; j < iters; j++) op.run();
            }));
        }
        for (Future<?> f : futures) f.get();
        exec.shutdown();
        return System.currentTimeMillis() - start;
    }
}
```

```go
// Go: sync/atomic
package main

import (
    "fmt"
    "sync"
    "sync/atomic"
)

type AtomicCounter struct {
    // Cache line padding — false sharing qarşısını almaq üçün
    _ [64]byte
    value int64
    _ [64]byte
}

func (c *AtomicCounter) Increment() {
    atomic.AddInt64(&c.value, 1)
}

func (c *AtomicCounter) Get() int64 {
    return atomic.LoadInt64(&c.value)
}

// CAS spin loop
func atomicUpdateSpinLoop(ref *int64, expected, newVal int64) bool {
    for {
        current := atomic.LoadInt64(ref)
        if current != expected {
            return false
        }
        if atomic.CompareAndSwapInt64(ref, current, newVal) {
            return true
        }
        // CAS uğursuz — yenidən cəhd et (spin)
        // Production-da: runtime.Gosched() ilə yield et — CPU-nu burax
    }
}

func main() {
    var counter int64

    var wg sync.WaitGroup
    for i := 0; i < 1000; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            atomic.AddInt64(&counter, 1)
        }()
    }
    wg.Wait()
    fmt.Println("Counter:", atomic.LoadInt64(&counter))  // 1000 — həmişə
}
```

## Praktik Tapşırıqlar

- `volatile int` ilə 100 thread `i++` icra etsin — race condition-ı görün; `AtomicInteger` ilə düzəldin
- ABA ssenarini reproduce edin: `AtomicReference` CAS-ı yanlış qaytarır; `AtomicStampedReference` ilə həll edin
- `LongAdder` vs `AtomicLong` benchmark-ını 16 thread ilə çalışdırın — fərqi ölçün
- Go-da `atomic.AddInt64` vs mutex-li counter-ı benchmark edin
- False sharing nümunəsi: iki `AtomicLong` eyni cache line-da — padding ilə fərqi ölçün

## Əlaqəli Mövzular
- `11-memory-models.md` — Happens-before, visibility qarantiyaları
- `12-lock-free-structures.md` — Atomic əsaslı data strukturlar
- `03-mutex-semaphore.md` — Lock-based alternativ
- `09-read-write-lock.md` — StampedLock optimistic read atomic-dən istifadə edir
