# Read-Write Lock (Senior ⭐⭐⭐)

## İcmal
Read-Write Lock (RWLock) — oxuma əməliyyatlarının paralel, yazma əməliyyatlarının isə eksklusiv icra edilməsinə imkan verən sinxronizasiya primitividir. Klassik Mutex bütün əməliyyatları serializeizə edir; RWLock read-heavy sistemlərdə throughput-u əhəmiyyətli artırır. Senior interview-larda "okuyucu-yazıcı problemi" kimi tanınan klassik concurrency probleminin həlli kimi soruşulur.

## Niyə Vacibdir
Cache, konfiqurasiya, in-memory index kimi read-heavy data strukturları üçün RWLock kritik optimizasiyadır. İnterviewer bu sualla sizin Mutex vs RWLock trade-off-unu, writer starvation riskini, PHP-nin shared memory ssenariisini, Java `ReadWriteLock` API-ını, və Go `sync.RWMutex`-ni bildiyinizi yoxlayır. Cache invalidation, config reload — hər ikisi RWLock-un klassik istifadə nümunəsidir.

## Əsas Anlayışlar

- **Read Lock (Shared Lock):** Bir anda bir neçə reader ala bilər — paralel oxuma
- **Write Lock (Exclusive Lock):** Bir anda yalnız bir writer ala bilər; reader-lar da bloklanır
- **Mutual Exclusion:** Writer aktiv olanda heç bir reader/writer daxil ola bilməz
- **Reader Priority:** Reader-lar üstünlük alırsa — writer starvation riski
- **Writer Priority:** Writer-lar üstünlük alırsa — reader-lar gecikmə yaşaya bilər
- **Fair RWLock:** Gəliş sırasına görə reader/writer növbə alır — starvation yoxdur
- **Writer Starvation:** Daima yeni reader-lar gəlirsə, writer heç vaxt lock ala bilmir
- **Upgrade Lock:** Read → Write lock-a yüksəltmə — deadlock riski var, çox dil dəstəkləmir
- **Downgrade Lock:** Write → Read-ə endirilmə — Java `ReentrantReadWriteLock`-da mümkün
- **Java `ReentrantReadWriteLock`:** `readLock()` + `writeLock()` — reentrant, fair modu var
- **Java `StampedLock`:** Optimistic read — lock almadan oxu, sonra validate et; daha performanslıdır
- **Go `sync.RWMutex`:** `RLock()/RUnlock()` + `Lock()/Unlock()` — writer priority (fair)
- **PHP `ShmOp` / `sem_get`:** Process-lərarası RWLock — shared memory ilə
- **Database Shared Lock:** `SELECT ... FOR SHARE` — database-in RWLock analoqudur
- **`ReadWriteLock` vs `ConcurrentHashMap`:** ConcurrentHashMap daha yaxşı concurrent yazma; RWLock custom struktur üçün
- **Spinlock vs Blocking Lock:** Spinlock CPU istehlak edir; Blocking Lock OS-ə keçir — qısa wait üçün spin üstün

## Praktik Baxış

**Interview-da yanaşma:**
- "Niyə Mutex kifayət deyil?" — reader-lar bir-birini bloklamalı deyil, bu boş gözləmə
- Writer starvation problemini mütləq qeyd edin
- Java `StampedLock`-un optimistic read-ini bilmək sizi fərqləndirər

**Follow-up suallar:**
- "Writer starvation nədir? Necə həll olunur?" — Fair mode, yazıcı prioriteti
- "Read lock tutarkən write lock almaq (upgrade) niyə deadlock yaradır?" — Hər iki reader upgrade etmək istəsə
- "StampedLock RWLock-dan nə ilə üstündür?" — Optimistic read — lock-suz, sonra validate

**Ümumi səhvlər:**
- "RWLock həmişə Mutex-dən yaxşıdır" demək — write-heavy sistemdə overhead artır
- Writer starvation-ı qeyd etməmək
- Lock upgrade-in deadlock riskini bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- `StampedLock` optimistic read-ini izah etmək
- "Write-heavy sistemdə RWLock overkill-dir" demək
- Fair vs unfair mode trade-off-unu izah etmək

## Nümunələr

### Tipik Interview Sualı
"In-memory cache-iniz çox oxunur, az yazılır. Mutex istifadə edirsiniz. Problem nədir? Necə optimallaşdırarsınız?"

### Güclü Cavab
Mutex bütün oxumaları serializeizə edir — eyni anda yalnız bir thread okuyur. Bu read-heavy ssenariidə ciddi bottleneck-dir: 100 thread oxumak istəyir, hamısı gözləyir. RWLock ilə paralel oxumaya icazə verilir, yalnız yazma zamanı eksklusiv lock alınır. Məsələn, konfiqurasiya hər saniyə minlərlə oxunur, saatda bir dəfə yenilənir — RWLock throughput-u dramatik artırır. Amma writer starvation riski var: daima yeni reader gəlirsə, writer heç vaxt lock ala bilmir. Bunu Java-da `ReentrantReadWriteLock(fair=true)` ilə həll etmək olar. Daha ağıllı həll: Java `StampedLock` — optimistic read ilə lock almadan oxu, sonra validate et; contentious olmayan hallarda lock xərci sıfıra yaxınlaşır.

### Kod Nümunəsi
```java
// Java: ReentrantReadWriteLock ilə cache
import java.util.concurrent.locks.*;
import java.util.HashMap;
import java.util.Map;

public class ThreadSafeCache<K, V> {
    private final Map<K, V> cache = new HashMap<>();
    // Fair mode: gəliş sırasına görə — writer starvation yoxdur
    private final ReadWriteLock lock = new ReentrantReadWriteLock(true);
    private final Lock readLock  = lock.readLock();
    private final Lock writeLock = lock.writeLock();

    public V get(K key) {
        readLock.lock();    // Paralel oxumaya icazə
        try {
            return cache.get(key);
        } finally {
            readLock.unlock();
        }
    }

    public void put(K key, V value) {
        writeLock.lock();   // Eksklusiv yazma — bütün reader-lar bloklanır
        try {
            cache.put(key, value);
        } finally {
            writeLock.unlock();
        }
    }

    public V getOrCompute(K key, java.util.function.Supplier<V> supplier) {
        // Əvvəl read lock ilə yoxla
        readLock.lock();
        try {
            V existing = cache.get(key);
            if (existing != null) return existing;
        } finally {
            readLock.unlock();
        }

        // Deyil — write lock al, yenidən yoxla (double-checked locking)
        writeLock.lock();
        try {
            V existing = cache.get(key);
            if (existing != null) return existing; // Başqa thread artıq əlavə edib
            V computed = supplier.get();
            cache.put(key, computed);
            return computed;
        } finally {
            writeLock.unlock();
        }
    }
}
```

```java
// Java StampedLock: Optimistic Read — daha performanslı
import java.util.concurrent.locks.StampedLock;

public class OptimisticCache {
    private double price = 100.0;
    private final StampedLock lock = new StampedLock();

    // Optimistic read: lock almadan oxu, sonra validate et
    public double getPrice() {
        long stamp = lock.tryOptimisticRead(); // Lock ALMIR — sadəcə stamp götür
        double currentPrice = price;

        if (!lock.validate(stamp)) {
            // Oxuma zamanı write olmuş — read lock al, yenidən oxu
            stamp = lock.readLock();
            try {
                currentPrice = price;
            } finally {
                lock.unlockRead(stamp);
            }
        }
        return currentPrice; // Əksər hallarda lock xərci yoxdur!
    }

    // Write lock — eksklusiv
    public void setPrice(double newPrice) {
        long stamp = lock.writeLock();
        try {
            this.price = newPrice;
        } finally {
            lock.unlockWrite(stamp);
        }
    }

    // Read → Write upgrade (StampedLock-da mümkün, ReentrantRWLock-da deyil)
    public void updateIfExpensive(double threshold) {
        long stamp = lock.readLock();
        try {
            while (price > threshold) {
                long writeStamp = lock.tryConvertToWriteLock(stamp);
                if (writeStamp != 0L) {
                    stamp = writeStamp;
                    price *= 0.9; // Write əməliyyatı
                    break;
                } else {
                    lock.unlockRead(stamp);
                    stamp = lock.writeLock(); // Full write lock al
                }
            }
        } finally {
            lock.unlock(stamp);
        }
    }
}
```

```go
// Go: sync.RWMutex
package main

import (
    "fmt"
    "sync"
    "time"
)

type Config struct {
    mu   sync.RWMutex
    data map[string]string
}

func NewConfig() *Config {
    return &Config{data: make(map[string]string)}
}

// Paralel oxuma — RLock
func (c *Config) Get(key string) (string, bool) {
    c.mu.RLock()
    defer c.mu.RUnlock()
    val, ok := c.data[key]
    return val, ok
}

// Eksklusiv yazma — Lock
func (c *Config) Set(key, value string) {
    c.mu.Lock()
    defer c.mu.Unlock()
    c.data[key] = value
}

// Reload — bütün konfiqurasiyani yenilə
func (c *Config) Reload(newData map[string]string) {
    c.mu.Lock()
    defer c.mu.Unlock()
    c.data = newData
    fmt.Println("Config reloaded")
}

func main() {
    cfg := NewConfig()
    cfg.Set("db_host", "localhost")
    cfg.Set("db_port", "5432")

    var wg sync.WaitGroup

    // 100 paralel reader
    for i := 0; i < 100; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            val, _ := cfg.Get("db_host")
            _ = val
        }()
    }

    // 1 writer (nadir)
    wg.Add(1)
    go func() {
        defer wg.Done()
        time.Sleep(10 * time.Millisecond)
        cfg.Reload(map[string]string{
            "db_host": "replica.example.com",
            "db_port": "5433",
        })
    }()

    wg.Wait()
    fmt.Println("Done")
}

// Writer starvation demo: Go-da RWMutex writer priority-dir
// Yeni reader-lar writer gözləyərkən bloklanır — fair davranış
```

```php
// PHP: APCu / opcache ilə shared memory RWLock ssenarisi
// PHP-FPM process-based — process-lərarası shared state üçün

// APCu atomik əməliyyatlar istifadə edir — built-in RW semantics
class SharedCache
{
    public function get(string $key): mixed
    {
        // APCu daxilən read lock istifadə edir
        return apcu_fetch($key);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        // APCu daxilən write lock istifadə edir
        apcu_store($key, $value, $ttl);
    }

    public function getOrSet(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $value = apcu_fetch($key, $success);
        if ($success) {
            return $value;
        }

        // Race condition mümkün — iki process eyni anda bura gələ bilər
        // apcu_add ilə atomic "set if not exists" — yalnız biri uğurlu olacaq
        $computed = $callback();
        apcu_add($key, $computed, $ttl); // Atomik — artıq varsa skip edir

        return apcu_fetch($key) ?? $computed;
    }
}
```

## Praktik Tapşırıqlar

- Java-da `ReentrantReadWriteLock` vs plain `synchronized` — 100 reader, 1 writer ssenariisini benchmark edin
- Go-da `sync.Mutex` vs `sync.RWMutex` performans fərqini ölçün — read-heavy yük altında
- Writer starvation-ı reproduce edin: daima yeni reader-lar gəlir, writer nə qədər gözləyir?
- `StampedLock` optimistic read-in contentious olmayan hallarda nə qədər sürətli olduğunu ölçün
- Cache invalidation ssenariisini simulasiya edin: config dəyişir, bütün oxuyanlar yeni dəyəri görür

## Əlaqəli Mövzular
- `03-mutex-semaphore.md` — Əsas lock primitivi
- `04-deadlock-prevention.md` — RWLock-da upgrade deadlock riski
- `10-atomic-operations.md` — Lock-free alternativ — StampedLock-un əsası
- `12-lock-free-structures.md` — RWLock-suz concurrent data struktur
- `08-producer-consumer.md` — Queue-ya concurrent access
