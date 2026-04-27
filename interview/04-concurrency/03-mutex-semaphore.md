# Mutex vs Semaphore (Middle ⭐⭐)

## İcmal
Mutex (Mutual Exclusion) və Semaphore — concurrent sistemlərdə shared resurslara girişi idarə edən synchronization primitiv-ləridir. Mutex yalnız bir thread-in keçməsinə icazə verir; Semaphore N thread-in eyni anda keçməsinə. Bu mövzu interview-da əsas synchronization primitiv-ləri bildiyinizi yoxlamaq üçün çıxır.

## Niyə Vacibdir
Yanlış synchronization ya race condition-a, ya deadlock-a, ya da performance problemə yol açır. İnterviewer bu sualla sizin hansı primitivi nə vaxt seçəcəyinizi, binary semaphore ilə mutex fərqini, real-world use case-ləri bildiyinizi yoxlayır.

---

## Əsas Anlayışlar

- **Mutex:** Binary lock — `lock()` / `unlock()`; yalnız lock edən thread unlock edə bilər (ownership semantikası)
- **Semaphore:** Counter-based — `signal()` / `acquire()` (`wait()`); ownership yoxdur — bir thread acquire, başqası release edə bilər
- **Binary Semaphore:** Dəyəri 0 ya 1 — mutex-ə bənzər davranış, amma ownership yoxdur; digər thread release edə bilər
- **Counting Semaphore:** Başlanğıc dəyəri N — N thread-in eyni anda resursa girişinə icazə verir; connection pool, rate limiting üçün ideal
- **Lock Ownership:** Mutex-i yalnız lock edən thread unlock edə bilər; ownership pozulsa — Java `IllegalMonitorStateException`; deadlock halında owned thread terminate olduqda OS mutex-i release edə bilər (robust mutex)
- **Priority Inversion:** Aşağı prioritetli thread mutex tutur, yüksək prioritetli gözləyir — real-time sistemlərdə ciddi problem; Mars Pathfinder mission (1997) bu bug-dan crash etdi
- **Priority Inheritance:** Priority inversion-a qarşı həll — mutex tutan aşağı-prioritetli thread-in prioriteti müvəqqəti olaraq yüksəldilir
- **Spinlock:** Lock boşalamayana qədər CPU-da aktiv dövrü (busy-waiting) — qısa gözləmə üçün effektiv, uzun gözləmədə CPU israfı
- **RWLock (Read-Write Lock):** Çoxlu reader eyni anda, yalnız bir writer; read-heavy iş yükündə plain mutex-dən xeyli sürətli
- **Reentrant (Recursive) Mutex:** Eyni thread-in eyni mutex-i bir neçə dəfə lock etməsinə icazə verir — `ReentrantLock` Java-da; count saxlanır
- **Monitor:** Mutex + Condition Variable birlikdə — Java-da `synchronized` + `wait()` / `notify()` / `notifyAll()`
- **Condition Variable:** Thread-in müəyyən şərt yerinə gələnə qədər gözləməsini təmin edir — `await()` mutex-i buraxır və thread-i uyudur; `signal()` oyandırır
- **POSIX Mutex:** `pthread_mutex_t` — C/C++ əsaslı sistemlərdə; `PTHREAD_MUTEX_RECURSIVE` type ilə reentrant
- **Java `synchronized`:** Implicit monitor — hər Java object-in implicit lock-u var; `synchronized(obj){}` bloku
- **Go `sync.Mutex`:** `Lock()`, `Unlock()` — defer ilə birlikdə istifadə edilir; `sync.RWMutex` read-heavy üçün
- **Futex (Fast Userspace Mutex):** Linux kernel-in optimizasiyası — contention olmadığında user-space-də həll edilir, yalnız contention-da kernel-ə gedir
- **Semaphore vs Channel (Go):** Go-da semaphore üçün standart primitiv yoxdur — buffered channel ilə implement edilir
- **Starvation:** Lock waiting thread-lər növbə yoxdursa, bəziləri əbədi gözləyə bilər; `ReentrantLock(fair=true)` FIFO qarantı verir

---

## Praktik Baxış

**Interview-da yanaşma:**
- "Mutex nə vaxt, semaphore nə vaxt?" — Exclusive access (bir resurs, bir istifadəçi) → mutex; N concurrent access → counting semaphore
- Connection pool nümunəsi ilə semaphore-u izah edin — anlaşıqlı real-world nümunədir
- Binary semaphore vs mutex ownership fərqini mütləq qeyd edin

**Follow-up suallar:**
1. "Binary semaphore ilə mutex eyni şeydirmi?" — Xeyr; mutex ownership var — yalnız locker unlocker ola bilər; semaphore-da bu yoxdur
2. "Read-heavy workload üçün nə istifadə edərsiniz?" — RWLock; `sync.RWMutex`, Java `ReentrantReadWriteLock`
3. "Mutex-i lock edən thread crash olsa nə baş verir?" — Deadlock riski; robust mutex bu halda `EOWNERDEAD` qaytarır
4. "Reentrant mutex nə vaxt lazımdır?" — Eyni thread-in eyni mutex-i tutan funksiyadan lock tutan başqa funksiyaya çağırış etdiyi hallarda
5. "Spinlock nə vaxt mutex-dən üstündür?" — Context switch-in semaphore gözləmə müddətindən baha olduğu çox qısa gözləmə ssenariləri; kernel driver-larda geniş istifadə olunur
6. "Java-da `wait()` niyə `synchronized` blok içərisindən çağırılmalıdır?" — Monitor-a sahib olmaq lazımdır; əks halda `IllegalMonitorStateException`

**Code review red flags:**
- `lock()` finally bloğu olmadan — exception halında unlock olmayacaq, deadlock
- `synchronized(this)` əvəzinə `synchronized(new Object())` hər dəfə — lock heç vaxt tutulmur!
- RWLock-da write-heavy workload — write lock read-ləri blokladığından tıxac yarana bilər
- Semaphore `acquire()` + `release()` arasındakı kod exception ata bilir — try-finally lazımdır

**Production debugging ssenariləri:**
- Web server thread-ləri DB connection pool-unu gözləyir — semaphore-un permits sayı az qoyulub; `Active: 20, Queue: 150`
- Java application donur — `jstack` thread dump-da "waiting to lock" görünür, mutex deadlock
- Priority inversion — yüksək prioritetli real-time task adi thread-in lock-unu gözləyir, sistem gecikmə yaşayır
- `ReentrantLock.tryLock()` olmadan — birdəfəlik uğursuzluq bütün işi dayandırır

---

## Nümunələr

### Tipik Interview Sualı
"Connection pool dizayn edirsiniz: maksimum 10 connection olacaq. Hansı synchronization primitivi istifadə edərdiniz? Niyə?"

### Güclü Cavab
Connection pool üçün counting semaphore mükəmməl uyğundur. Semaphore-u 10 ilə başladıram: hər connection götürəndə `acquire()` — counter azalır; connection qaytaranda `release()` — counter artır. Counter sıfıra çatanda yeni thread bloklanır, connection boşalana qədər gözləyir.

Mutex istifadə etsəydim, hər dəfə bütün pool-u lock etməli olardım — yalnız bir thread connection götürərdi. Semaphore eyni anda 10 thread-in aktiv connection almasına imkan verir.

Ownership tələb olunmur — bir thread connection alan, başqa bir thread onu qaytara bilər (məs: async workflow). Bu semaphore-un mutex-dən üstün olduğu nöqtədir.

### Kod Nümunəsi

```java
import java.util.concurrent.*;
import java.util.concurrent.locks.*;

// ── Mutex: Exclusive access (BankAccount) ─────────────────────────
public class BankAccount {
    private double balance;
    private final ReentrantLock lock = new ReentrantLock();

    public void deposit(double amount) {
        lock.lock();
        try {
            balance += amount;
        } finally {
            lock.unlock(); // Həmişə finally-də — exception-da da unlock olur
        }
    }

    public boolean withdraw(double amount) {
        lock.lock();
        try {
            if (balance >= amount) {
                balance -= amount;
                return true;
            }
            return false;
        } finally {
            lock.unlock();
        }
    }

    // tryLock — gözləmədən cəhd et
    public boolean withdrawWithTimeout(double amount, long ms) throws InterruptedException {
        if (lock.tryLock(ms, TimeUnit.MILLISECONDS)) {
            try {
                if (balance >= amount) {
                    balance -= amount;
                    return true;
                }
                return false;
            } finally {
                lock.unlock();
            }
        }
        return false; // Lock ala bilmədik — başqa iş gör
    }
}

// ── Counting Semaphore: Connection Pool ───────────────────────────
public class ConnectionPool {
    private final Semaphore semaphore;
    private final Queue<Connection> connections;
    private final Object poolLock = new Object();

    public ConnectionPool(int poolSize) {
        this.semaphore   = new Semaphore(poolSize, true); // fair=true: FIFO
        this.connections = new LinkedList<>(createConnections(poolSize));
    }

    public Connection acquire() throws InterruptedException {
        semaphore.acquire(); // Counter azalt; 0-da bloklan
        synchronized (poolLock) {
            return connections.poll();
        }
    }

    public Connection tryAcquire(long timeout, TimeUnit unit) throws InterruptedException {
        if (!semaphore.tryAcquire(timeout, unit)) {
            return null; // Timeout — connection boşalmadı
        }
        synchronized (poolLock) {
            return connections.poll();
        }
    }

    public void release(Connection conn) {
        synchronized (poolLock) {
            connections.offer(conn);
        }
        semaphore.release(); // Counter artır — gözləyənlər oyanır
        // NOT: acquire edən thread release etməyə bilər (async use case)
    }
}
```

```java
// ── Monitor Pattern: Mutex + Condition Variable (Bounded Buffer) ──
import java.util.concurrent.locks.*;

public class BoundedBuffer<T> {
    private final java.util.Queue<T> queue = new java.util.LinkedList<>();
    private final int capacity;
    private final ReentrantLock lock     = new ReentrantLock();
    private final Condition     notFull  = lock.newCondition();
    private final Condition     notEmpty = lock.newCondition();

    public BoundedBuffer(int capacity) {
        this.capacity = capacity;
    }

    public void put(T item) throws InterruptedException {
        lock.lock();
        try {
            while (queue.size() == capacity) {
                notFull.await(); // Full olduqda gözlə (mutex müvəqqəti buraxılır)
            }
            queue.add(item);
            notEmpty.signal(); // Consumer-ı xəbərdar et
        } finally {
            lock.unlock();
        }
    }

    public T take() throws InterruptedException {
        lock.lock();
        try {
            while (queue.isEmpty()) {
                notEmpty.await(); // Boş olduqda gözlə
            }
            T item = queue.poll();
            notFull.signal(); // Producer-ı xəbərdar et
            return item;
        } finally {
            lock.unlock();
        }
    }

    public int size() {
        lock.lock();
        try { return queue.size(); } finally { lock.unlock(); }
    }
}
```

```go
// ── Go: sync.Mutex + sync.RWMutex ────────────────────────────────
package main

import (
    "sync"
    "time"
)

// RWMutex: Read-heavy cache
type SafeCache struct {
    mu    sync.RWMutex
    store map[string]string
}

func (c *SafeCache) Get(key string) (string, bool) {
    c.mu.RLock()   // Çoxlu reader eyni anda — yalnız writer-ları bloklayır
    defer c.mu.RUnlock()
    v, ok := c.store[key]
    return v, ok
}

func (c *SafeCache) Set(key, value string) {
    c.mu.Lock()    // Exclusive — heç bir reader/writer yoxdur
    defer c.mu.Unlock()
    c.store[key] = value
}

func (c *SafeCache) Delete(key string) {
    c.mu.Lock()
    defer c.mu.Unlock()
    delete(c.store, key)
}

// ── Go-da Semaphore (buffered channel ilə) ───────────────────────
type Semaphore struct {
    ch chan struct{}
}

func NewSemaphore(n int) *Semaphore {
    return &Semaphore{ch: make(chan struct{}, n)}
}

func (s *Semaphore) Acquire() {
    s.ch <- struct{}{} // Dolu olduqda bloklanır
}

func (s *Semaphore) TryAcquire(timeout time.Duration) bool {
    select {
    case s.ch <- struct{}{}:
        return true
    case <-time.After(timeout):
        return false // Timeout
    }
}

func (s *Semaphore) Release() {
    <-s.ch // Yer açır
}

// İstifadə — rate limiting
func processRequest(sem *Semaphore, data string) error {
    if !sem.TryAcquire(5 * time.Second) {
        return fmt.Errorf("server busy, try later")
    }
    defer sem.Release()

    // Yalnız N concurrent request buraya çata bilər
    return doWork(data)
}
```

```php
// PHP: Redis ilə distributed semaphore (multi-process race prevention)
use Illuminate\Support\Facades\Redis;

class DistributedSemaphore
{
    public function __construct(
        private readonly string $key,
        private readonly int    $maxConcurrent,
        private readonly int    $ttlSeconds = 30,
    ) {}

    public function acquire(string $token): bool
    {
        // INCR atomic-dir — race condition yoxdur
        $current = Redis::incr($this->key);

        if ($current === 1) {
            Redis::expire($this->key, $this->ttlSeconds);
        }

        if ($current > $this->maxConcurrent) {
            Redis::decr($this->key); // Geri al
            return false;
        }

        return true;
    }

    public function release(): void
    {
        Redis::decr($this->key);
    }
}

// İstifadə: eyni anda max 5 video conversion
$sem = new DistributedSemaphore('video:convert', maxConcurrent: 5);

if (!$sem->acquire($requestId)) {
    return response()->json(['error' => 'Server busy'], 503);
}

try {
    convertVideo($file);
} finally {
    $sem->release();
}
```

### Yanlış Kod + Düzgün Kod

```java
// YANLIŞ: unlock finally-siz
public void unsafeMethod() {
    lock.lock();
    processData(); // Exception ata bilər!
    lock.unlock(); // Buraya çatmayabilir → deadlock!
}

// DÜZGÜN: həmişə try-finally
public void safeMethod() {
    lock.lock();
    try {
        processData();
    } finally {
        lock.unlock(); // Exception-dan asılı olmayaraq buraxılır
    }
}

// YANLIŞ: Semaphore-da try-finally unutmaq
public void badSemaphoreUse() throws InterruptedException {
    semaphore.acquire();
    riskyOperation(); // Exception → semaphore release olmayır → leak!
    semaphore.release();
}

// DÜZGÜN
public void goodSemaphoreUse() throws InterruptedException {
    semaphore.acquire();
    try {
        riskyOperation();
    } finally {
        semaphore.release();
    }
}
```

---

## Praktik Tapşırıqlar

- Java-da `synchronized` blok olmadan `HashMap`-ə concurrent write test edin, data corruption görün; `ConcurrentHashMap` ilə müqayisə edin
- `Semaphore(3)` ilə 3-lü connection pool implement edin, 10 thread-in ehtiyatla connection aldığını görün
- `ReentrantLock(fair=true)` vs `fair=false` — starvation ssenariasında fərqi ölçün
- RWLock-da read-heavy workload benchmark edin: `ReentrantLock` vs `ReentrantReadWriteLock` throughput fərqi
- Go-da buffered channel ilə semaphore implement edin, rate limiting üçün istifadə edin

## Əlaqəli Mövzular
- `02-race-conditions.md` — Mutex race condition-u həll edir
- `04-deadlock-prevention.md` — Yanlış mutex → deadlock
- `05-thread-pools.md` — Thread pool + semaphore back-pressure
- `01-threads-vs-processes.md` — Shared memory konteksti
