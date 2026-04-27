# Race Conditions (Middle ⭐⭐)

## İcmal
Race condition — iki və ya daha çox thread/process-in paylaşılan resursa eyni anda müraciət etməsi nəticəsində gözlənilməz davranışın yaranmasıdır. Nəticə icra sırasına görə dəyişir — "race" adı buradan gəlir. Backend interview-larında klassik sual, çünki concurrent sistemlərin ən çox rast gəlinən bug növüdür.

## Niyə Vacibdir
Race condition-lar test etmək çətin olan intermittent bug-lar yaradır — development-da görünmür, production-da nadir baş verir, reproduce etmək çətindir. İnterviewer bu sualla sizin concurrent programlama riskini, atomic operations-ı, lock-ları, database-level race prevention-ı bildiyinizi yoxlayır.

---

## Əsas Anlayışlar

- **Critical Section:** Yalnız bir thread tərəfindən eyni anda icra edilə bilən kod bloku; synchronization olmadan race condition yaranır
- **Atomicity:** Əməliyyatın bölünməz olması — `counter++` üç addımdır (read → increment → write); arada context switch mümkündür
- **Read-Modify-Write:** En klassik pattern — dəyəri oxu, dəyişdir, geri yaz; bu üç addım arasında başqa thread müdaxilə edə bilər
- **Check-Then-Act:** `if (available) reserve()` — check ilə act arasında başqa thread eyni yoxlamanı keçib gələ bilər (TOCTOU)
- **Lazy Initialization Race:** `if (singleton == null) singleton = new X()` — iki thread eyni anda `null` görüb iki instance yarada bilər
- **Visibility Problem:** Bir thread-in yazdığı dəyər digər thread tərəfindən CPU cache-dən görünməyə bilər; `volatile` keyword bunu həll edir
- **happens-before:** Java Memory Model-in formal qarantı — əgər A happens-before B isə, A-nın yazdıqları B tərəfindən görünür
- **Data Race:** İki thread eyni memory location-a, ən azı biri write, synchronization olmadan müraciət edir — Go race detector bunu tapır
- **Logic Race:** Synchronization var, amma business logic order-i düzgün qurulmayıb — daha çətin aşkar edilir
- **TOCTOU (Time-of-Check to Time-of-Use):** File system, database-dəki klassik check-then-act problemi; ən çox security vulnerability-lərdə görünür
- **Mutex/Lock:** Race condition-ın klassik həlli — critical section-u qoruyur, yalnız bir thread daxil ola bilər
- **Atomic Variables:** `AtomicInteger`, `sync/atomic` — lock olmadan CPU-level CAS (Compare-And-Swap) əməliyyatı; mutex-dən sürətli
- **Immutability:** Dəyişilməz data üçün race condition mümkün deyil — functional programming-in üstünlüklərindən biri
- **Thread-local Storage:** Hər thread-in özünə xas data saxlaması — paylaşma yoxdur, race yoxdur; Java `ThreadLocal<T>`
- **Benign Race:** Nəticəyə kritik təsir etməyən race — bəzən performans üçün qəbul edilir (statistika sayğacları kimi)
- **Database Optimistic Lock:** `version` column ilə — update-də version conflict yoxlanır; rollback + retry
- **Database Pessimistic Lock:** `SELECT ... FOR UPDATE` — row-u lock edir, başqa transaction gözləyir
- **CAS (Compare-And-Swap):** CPU instruction — "əgər dəyər X-dirsə, Y yaz, əks halda uğursuz say"; atomic operation-ların əsası
- **Memory Barrier / Fence:** CPU-nun instruction reorder etməsini dayandıran primitiv; `volatile` Java-da memory barrier daxil edir

---

## Praktik Baxış

**Interview-da yanaşma:**
- Klassik counter increment nümunəsini izah edin: "Niyə `counter++` thread-safe deyil?"
- `read → modify → write` üç addımını diaqramla izah edin
- Həll yollarını sıralayın: atomic ops, mutex, immutability, message passing, DB-level lock

**Follow-up suallar:**
1. "Database-də race condition necə həll olunur?" — `SELECT FOR UPDATE` (pessimistic), version column (optimistic), atomic UPDATE
2. "`counter++` niyə thread-safe deyil — amma `counter = 5` safe deyilmi?" — Assignment da safe deyil 64-bit dəyərlər üçün 32-bit sistemdə (word-tearing)
3. "Immutable data race condition-u həll edirmi?" — Bəli, read-only data üçün race yoxdur
4. "Go-da race detector necə işləyir?" — `go run -race` flag; runtime instrumentation; memory access-ləri track edir
5. "`volatile` keyword race condition-u həll edirmi Java-da?" — Visibility-ni həll edir (happens-before), amma atomicity-ni yox; `counter++` üçün kifayət deyil
6. "PHP-də race condition mümkündürmü? PHP single-threaded-dir." — Bəli! Process-level race var; eyni anda iki PHP-FPM worker eyni DB row-unu yeniləyə bilər

**Code review red flags:**
- `if ($x !== null)` + sonra `$x->method()` — lazy init race (thread-safe deyil)
- `DB::select` + sonra `DB::update` ayrı query-lər — check-then-act
- Shared counter-lər `static` property-lərdə — Octane-da process paylaşılır
- Java-da `HashMap` concurrent access — `ConcurrentHashMap` lazımdır

**Production debugging ssenariləri:**
- Ticket satışında oversell: 100 bilet var, 105 satılır — `WHERE available > 0` check atomik deyildi
- Coupon double-use: eyni anda iki request `coupon.used = false` görür, ikisi də istifadə edir
- Duplicate record-lar: `INSERT IGNORE` ya da unique constraint olmadan concurrent insert
- Bank transfer overdraft: balance check ilə debit arasında başqa transfer baş verir

---

## Nümunələr

### Tipik Interview Sualı
"100 thread eyni anda `counter++` çalışdırır. Nəticə 100 olmalıdır, amma 87 gəlir. Niyə? Necə düzəldirsiniz?"

### Güclü Cavab
Bu klassik read-modify-write race condition-dur. `counter++` görünüşdə bir əməliyyat kimi görünür, amma CPU səviyyəsindən 3 addımdır: (1) `counter`-in dəyərini register-ə oxu, (2) register-i artır, (3) register-i `counter`-ə yaz. İki thread eyni anda step 1-i icra edə bilər — hər ikisi "0" oxuyur, hər ikisi "1" yazır, 2 artım əvəzinə 1 artım baş verir. Nəticə deterministik deyil — "race"-in nəticəsi scheduling-dən asılıdır.

Həll seçimləri: Java-da `AtomicInteger.incrementAndGet()` — lock olmadan, CPU-level CAS əməliyyatı, ən sürətli. `synchronized` blok — lakin daha yavaş, contention-da bottleneck. `volatile` — bu case-də KİFAYƏT DEYİL; visibility-ni həll edir, atomicity-ni yox. High-contention üçün `LongAdder` — striped counter, `AtomicInteger`-dən belə sürətli.

### Kod Nümunəsi

```java
// YANLIŞ: Race condition — counter++
import java.util.List;
import java.util.ArrayList;
import java.util.concurrent.atomic.*;

public class RaceConditionDemo {
    static int unsafeCounter = 0;

    public static void main(String[] args) throws InterruptedException {
        // Test 1: Unsafe counter
        List<Thread> threads = new ArrayList<>();
        for (int i = 0; i < 100; i++) {
            threads.add(new Thread(() -> unsafeCounter++)); // NOT THREAD-SAFE
        }
        threads.forEach(Thread::start);
        for (Thread t : threads) t.join();
        System.out.println("Unsafe: " + unsafeCounter); // 87? 93? 100? Unpredictable!

        // Test 2: AtomicInteger — lock-free, CAS əsaslı
        AtomicInteger atomicCounter = new AtomicInteger(0);
        List<Thread> atomicThreads = new ArrayList<>();
        for (int i = 0; i < 100; i++) {
            atomicThreads.add(new Thread(() -> atomicCounter.incrementAndGet()));
        }
        atomicThreads.forEach(Thread::start);
        for (Thread t : atomicThreads) t.join();
        System.out.println("Atomic: " + atomicCounter.get()); // Həmişə 100

        // Test 3: synchronized — mutex
        final Object lock = new Object();
        final int[] syncCounter = {0};
        List<Thread> syncThreads = new ArrayList<>();
        for (int i = 0; i < 100; i++) {
            syncThreads.add(new Thread(() -> {
                synchronized (lock) {
                    syncCounter[0]++; // Yalnız bir thread daxil ola bilər
                }
            }));
        }
        syncThreads.forEach(Thread::start);
        for (Thread t : syncThreads) t.join();
        System.out.println("Sync: " + syncCounter[0]); // Həmişə 100

        // Test 4: LongAdder — yüksək contention-da ən sürətli
        LongAdder adder = new LongAdder();
        List<Thread> adderThreads = new ArrayList<>();
        for (int i = 0; i < 100; i++) {
            adderThreads.add(new Thread(adder::increment));
        }
        adderThreads.forEach(Thread::start);
        for (Thread t : adderThreads) t.join();
        System.out.println("LongAdder: " + adder.sum()); // Həmişə 100
    }
}
```

```go
// Go: Race detector ilə data race aşkar etmə
// Komanda: go run -race main.go
package main

import (
    "fmt"
    "sync"
    "sync/atomic"
)

var (
    unsafeCounter int64      // Race condition!
    safeCounter   int64      // atomic
    mu            sync.Mutex // mutex
)

func main() {
    var wg sync.WaitGroup
    n := 10_000

    // YANLIŞ: data race
    for i := 0; i < n; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            unsafeCounter++ // -race flag ilə: "DATA RACE" warning
        }()
    }
    wg.Wait()
    fmt.Println("Unsafe:", unsafeCounter) // ~8000-9999

    // DÜZGÜN 1: atomic
    unsafeCounter = 0
    for i := 0; i < n; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            atomic.AddInt64(&safeCounter, 1)
        }()
    }
    wg.Wait()
    fmt.Println("Atomic:", safeCounter) // 10000

    // DÜZGÜN 2: mutex
    count := 0
    for i := 0; i < n; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            mu.Lock()
            count++
            mu.Unlock()
        }()
    }
    wg.Wait()
    fmt.Println("Mutex:", count) // 10000
}
```

```php
// PHP: Database-level race condition — TOCTOU pattern
// YANLIŞ: Check-Then-Act — iki request eyni anda keçə bilər
function reserveTicketUnsafe(int $ticketId): bool
{
    // Request A: available = 1 görür
    // Request B: available = 1 görür (eyni anda!)
    $ticket = DB::table('tickets')->where('id', $ticketId)->first();

    if ($ticket->available > 0) {
        // Request A: decrement edir → available = 0
        // Request B: decrement edir → available = -1 (!)
        DB::table('tickets')
            ->where('id', $ticketId)
            ->decrement('available');
        return true;
    }
    return false;
}

// DÜZGÜN 1: Atomic UPDATE — check + act eyni əməliyyatda
function reserveTicketAtomic(int $ticketId): bool
{
    // WHERE available > 0 şərti ilə decrement — SQL atomic-dir
    // Eyni anda iki sorğu: biri 1 affected row görür, digəri 0
    $affected = DB::table('tickets')
        ->where('id', $ticketId)
        ->where('available', '>', 0)
        ->decrement('available');

    return $affected > 0; // 0 = başqası bizdən əvvəl aldı
}

// DÜZGÜN 2: Pessimistic Lock — SELECT FOR UPDATE
function reserveTicketPessimistic(int $ticketId): bool
{
    return DB::transaction(function () use ($ticketId) {
        // Row-u lock edir — başqa transaction gözlər
        $ticket = DB::table('tickets')
            ->where('id', $ticketId)
            ->lockForUpdate()  // SELECT ... FOR UPDATE
            ->first();

        if (!$ticket || $ticket->available <= 0) {
            return false;
        }

        DB::table('tickets')
            ->where('id', $ticketId)
            ->decrement('available');

        return true;
    });
}

// DÜZGÜN 3: Optimistic Lock — version column ilə
function reserveTicketOptimistic(int $ticketId): bool
{
    $ticket = DB::table('tickets')->where('id', $ticketId)->first();

    if (!$ticket || $ticket->available <= 0) {
        return false;
    }

    // version eyni qaldıqda update et; başqası dəyişdibsə 0 row affected
    $updated = DB::table('tickets')
        ->where('id', $ticketId)
        ->where('version', $ticket->version)   // Conflict check
        ->where('available', '>', 0)
        ->update([
            'available' => DB::raw('available - 1'),
            'version'   => DB::raw('version + 1'),
        ]);

    if ($updated === 0) {
        // Conflict — retry məntiqi əlavə edə bilərsiniz
        return false;
    }

    return true;
}
```

### Yanlış Kod + Düzgün Kod

```java
// YANLIŞ: Lazy initialization race condition
public class LazyInitRace {
    private static ExpensiveService instance;

    public static ExpensiveService getInstance() {
        if (instance == null) {                   // Thread A: null görür
            instance = new ExpensiveService();    // Thread B: null görür → iki instance!
        }
        return instance;
    }
}

// DÜZGÜN 1: synchronized — sadə, lakin hər call lock alır
public class SafeLazy1 {
    private static ExpensiveService instance;

    public static synchronized ExpensiveService getInstance() {
        if (instance == null) {
            instance = new ExpensiveService();
        }
        return instance;
    }
}

// DÜZGÜN 2: Double-checked locking + volatile (Java 5+)
public class SafeLazy2 {
    private static volatile ExpensiveService instance; // volatile: visibility guarantee

    public static ExpensiveService getInstance() {
        if (instance == null) {                        // İlk check: lock almadan
            synchronized (SafeLazy2.class) {
                if (instance == null) {                // İkinci check: lock içində
                    instance = new ExpensiveService();
                }
            }
        }
        return instance; // Artıq lock yoxdur
    }
}

// DÜZGÜN 3: Initialization-on-demand holder (ən elegant)
public class SafeLazy3 {
    private SafeLazy3() {}

    private static class Holder {
        // Class yüklənəndə JVM tərəfindən thread-safe initialize edilir
        static final ExpensiveService INSTANCE = new ExpensiveService();
    }

    public static ExpensiveService getInstance() {
        return Holder.INSTANCE; // Lock olmadan, həmişə thread-safe
    }
}
```

---

## Praktik Tapşırıqlar

- Java-da 100 thread ilə `counter++` çalışdırın, race condition reproduce edin, sonra `AtomicInteger` ilə düzəldin
- `go run -race` ilə Go data race detector-u istifadə edin: sadə bir map-ə concurrent write test edin
- PHP-də Apache Bench (`ab -c 50 -n 500`) ilə ticket reservation endpoint-inə concurrent request göndərin — oversell-i reproduce edin
- Atomic vs Mutex — Java-da benchmark edin: `JMH` ilə throughput fərqini ölçün
- `SELECT FOR UPDATE` vs optimistic lock — hansı ssenariodə hangisi daha yaxşıdır? Həm latency, həm throughput ölçün

## Əlaqəli Mövzular
- `03-mutex-semaphore.md` — Race condition həll mexanizmləri
- `04-deadlock-prevention.md` — Lock əlavə etmənin gətirdiyi risk
- `01-threads-vs-processes.md` — Shared memory modeli
- `05-thread-pools.md` — Thread idarəetmə
