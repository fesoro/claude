# Memory Models and Visibility (Lead ⭐⭐⭐⭐)

## İcmal
Memory model — proqram yazarkən nəzərdə tutduğunuz "dəyişiklik nə vaxt görünür?" sualının formal cavabıdır. Modern CPU-lar performance üçün instruction-ları reorder edir, cache-ə yazır, write buffer-lar istifadə edir — fərqli thread-lər fərqli "reality" görür. Java Memory Model (JMM), C++ memory_order, Go memory model — hər dil öz qaydalarını təyin edir. Bu mövzu Lead interview-larda heyrətamiz bug-ların kökündə dayanır.

## Niyə Vacibdir
"Niyə singleton-um thread-safe deyil?", "niyə bu flag-ı loop-da görmürəm?" — bu bug-ların cavabı memory model-dir. İnterviewer bu sualla sizin `volatile` niyə lazım olduğunu, double-checked locking-in niyə yanlış olduğunu (JMM əvvəl), happens-before qaydalarını, CPU cache coherence mexanizmini bildiyinizi yoxlayır. Yanlış memory visibility production-da intermittent bug yaradır — ən çətin debuglanır.

## Əsas Anlayışlar

- **Sequential Consistency:** Ən güclü model — hər thread-in instruction-ları yazılma sırasında görünür; real CPU-larda default deyil
- **Relaxed Memory Model:** CPU/compiler performance üçün reorder edir — programmer açıq barrier qoymalıdır
- **Write Buffer:** CPU yazmanı cache-ə deyil, write buffer-a göndərir — digər CPU-lar görə bilmir
- **Cache Coherence (MESI Protocol):** CPU cache-ləri arasında sinxronizasiya — hardware protokolu
- **Memory Barrier / Fence:** Reorder-in qarşısını alan instruction — load fence, store fence, full fence
- **Happens-Before (JMM):** A happens-before B: A-nın side effect-ləri B-yə görünür
- **JMM Happens-Before Qaydaları:**
  - Thread başlamazdan əvvəl `start()` çağırısı
  - `volatile` yazma → sonrakı oxuma
  - `synchronized` blok çıxışı → növbəti giriş
  - `Thread.join()` → join-dən sonrakı kod
- **`volatile` (Java):** Write/read memory barrier insert edir — visibility qarantiyası; reorder-ə mane olur
- **`volatile` atomicity vermir:** `volatile int i; i++` — hələ də race condition
- **Double-Checked Locking (DCL):** `volatile` olmadan broken — `instance` reference görünüb, amma object tam inisializə olmayıb
- **Publication Safety:** Object digər thread-ə necə "paylaşılır" — safe vs unsafe publication
- **`final` fields (JMM):** Constructor bitdikdən sonra `final` sahələr bütün thread-lərə görünür — `volatile` lazım deyil
- **`synchronized` visibility:** `synchronized` blokdan çıxışda bütün yazılar flush edilir
- **Go Memory Model:** Goroutine başlamazdan əvvəl `go` ifadəsi; channel send happens-before receive
- **`sync.Once` (Go):** Bir dəfə icra ediləcəyi və görünəcəyi qarantiyalı — DCL-in düzgün alternatividir
- **`memory_order` (C++/Rust):** `relaxed`, `acquire`, `release`, `seq_cst` — explicit memory ordering

## Praktik Baxış

**Interview-da yanaşma:**
- "Niyə flag-ı görmürəm?" — CPU write buffer-da qalmış ola bilər; `volatile` ilə flush et
- Double-checked locking bug-ını izah edin — JMM-in əsas nümunəsidir
- Java-da `volatile` nə verir, nə vermir — visibility ≠ atomicity

**Follow-up suallar:**
- "`volatile` bütün thread-safety problemlərini həll edirmi?" — Xeyr; atomicity üçün `Atomic*` lazım
- "final field niyə volatile-dan fərqlənir?" — Constructor-dan sonra bütün thread-ə görünür, amma sonra dəyişmir
- "Go-da channel receive, send-in side effect-lərini qarantiya edirmi?" — Bəli, Go Memory Model qaydası

**Ümumi səhvlər:**
- `volatile` = atomic düşünmək
- DCL-i `volatile` olmadan yazmaq
- "synchronized bütün problemi həll edir" düşünmək — visibility həll edir, lakin performance cost var

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- CPU write buffer və MESI protokolunu izah etmək
- `final` field-in publish safety-ni bilmək
- Go channel-ının happens-before semantikasını izah etmək

## Nümunələr

### Tipik Interview Sualı
"Thread A bir boolean flag-ı `true` edir. Thread B loop-da bu flag-ı yoxlayır. Thread B heç çıxa bilmir. Niyə? Necə düzəldərsiniz?"

### Güclü Cavab
Bu klassik memory visibility problemidir. Thread A flag-ı CPU-nun write buffer-ına yazır, lakin main memory-ə flush etmir. Thread B öz CPU cache-indən oxuyur — köhnə dəyəri görür. JVM JIT compiler loop-da dəyişməyən dəyəri register-ə cache-ləyə bilər. Həll: `volatile boolean flag` — Java Memory Model, `volatile` yazmadan əvvəl memory barrier insert edir; bütün əvvəlki yazılar flush edilir. Oxuma zamanı barrier main memory-dən oxumağı məcbur edir. Diqqət: `volatile` atomicity vermir — `flag = !flag` hələ də race condition-dır. Əgər compound operation lazımdırsa, `AtomicBoolean` istifadə edin.

### Kod Nümunəsi
```java
// PROBLEM: volatile olmadan flag — Thread B loop-dan çıxa bilmir
public class VisibilityBug {
    // YANLIŞ: CPU register/cache-də qala bilər
    boolean running = true;

    public void start() {
        new Thread(() -> {
            while (running) {
                // İş et
            }
            System.out.println("Stopped"); // Heç çap edilməyə bilər!
        }).start();

        try { Thread.sleep(100); } catch (InterruptedException e) {}
        running = false; // Bu dəyişiklik digər thread-ə görünməyə bilər
    }

    // DÜZGÜN: volatile — memory barrier, visibility qarantiyası
    volatile boolean runningFixed = true;

    // Double-Checked Locking — YANLIŞ (volatile olmadan)
    private static Singleton instanceBroken;

    public static Singleton getBroken() {
        if (instanceBroken == null) {
            synchronized (Singleton.class) {
                if (instanceBroken == null) {
                    instanceBroken = new Singleton();
                    // PROBLEM: 3 addım:
                    // 1. Memory ayır
                    // 2. Constructor çalışdır
                    // 3. Reference-ı assign et
                    // CPU 1→3→2 reorder edə bilər!
                    // Thread B: reference dolu görür, amma object hələ inisializə olmayıb
                }
            }
        }
        return instanceBroken;
    }

    // DÜZGÜN: volatile ilə DCL
    private static volatile Singleton instanceCorrect;

    public static Singleton getCorrect() {
        if (instanceCorrect == null) {
            synchronized (Singleton.class) {
                if (instanceCorrect == null) {
                    instanceCorrect = new Singleton();
                    // volatile write → reorder qadağandır
                    // Başqa thread-lər ya null, ya da tam inisializə olmuş object görür
                }
            }
        }
        return instanceCorrect;
    }

    // ƏN YAXŞI: Static Holder (Initialization-on-demand)
    // Class loader thread-safe — volatile-a ehtiyac yoxdur
    private static class SingletonHolder {
        static final Singleton INSTANCE = new Singleton();
    }
    public static Singleton getIdiomatic() {
        return SingletonHolder.INSTANCE;
    }
}
```

```java
// JMM Happens-Before — praktik nümunələr
public class HappensBeforeDemo {

    // volatile yazma → oxuma happens-before qarantiyası
    volatile int data = 0;
    volatile boolean ready = false;

    // Thread A (writer)
    public void writer() {
        data = 42;      // Yazma 1
        ready = true;   // Yazma 2 — volatile write: barrier insert edir
        // Bu nöqtədən sonra hər iki yazma görünür
    }

    // Thread B (reader)
    public void reader() {
        while (!ready) {}  // volatile read: barrier — memory-dən oxuyur
        // QARANTIYA: ready==true olanda data==42 görünür
        // Çünki ready-nin volatile write-ı data-nın yazmasından sonra
        assert data == 42;
    }

    // final field — safe publication
    static class ImmutablePoint {
        final int x;
        final int y;

        ImmutablePoint(int x, int y) {
            this.x = x;
            this.y = y;
        }
        // Constructor bitdikdən sonra x, y bütün thread-lərə görünür
        // volatile lazım deyil!
    }

    // synchronized visibility
    int sharedValue = 0;

    synchronized void produce() {
        sharedValue = 100;
    } // Blokdan çıxışda — bütün yazılar flush edilir

    synchronized void consume() {
        // Bloğa girişdə — main memory-dən oxunur
        System.out.println(sharedValue); // 100 görünür
    }
}
```

```go
// Go Memory Model
package main

import (
    "fmt"
    "sync"
)

// PROBLEM: Data race — Go race detector tapacaq
func dataRace() {
    done := false
    data := 0

    go func() {
        data = 42
        done = true
    }()

    for !done {} // done-u görməyə bilər — memory model qarantiyası yoxdur
    fmt.Println(data) // 0 ola bilər!
}

// HƏLL 1: sync.Mutex ilə
func withMutex() {
    var mu sync.Mutex
    done := false
    data := 0

    go func() {
        data = 42
        mu.Lock()
        done = true
        mu.Unlock()
    }()

    for {
        mu.Lock()
        if done {
            mu.Unlock()
            break
        }
        mu.Unlock()
    }
    fmt.Println(data) // Həmişə 42
}

// HƏLL 2: Channel — Go-nun idiomatic yolu
// Channel send happens-before channel receive (Go Memory Model qaydası)
func withChannel() {
    done := make(chan struct{})
    data := 0

    go func() {
        data = 42
        close(done) // send happens-before receive
    }()

    <-done              // Receive: data=42 görünür — qarantiyalı
    fmt.Println(data)   // Həmişə 42
}

// sync.Once — DCL-in Go alternatividir
var (
    once     sync.Once
    instance *Service
)

func GetService() *Service {
    once.Do(func() {
        instance = &Service{}
        instance.Init()
    }) // Bir dəfə, thread-safe, lazy initialization
    return instance
}

type Service struct{}
func (s *Service) Init() {}

// go race detector: go run -race main.go
// Data race-ləri real zamanda tapır
```

```php
// PHP: Process-isolated — memory model "problem" yoxdur
// Amma shared state olduqda (opcache, APCu, Redis) visibility vacibdir

// PHP-FPM hər request ayrı process — shared memory yoxdur, race condition yoxdur
// Lakin:

// OPcache — compile edilmiş PHP bytecode paylaşılır
// Config dəyişsə, opcache.revalidate_freq saniyəsi gözləmək lazımdır
// opcache_reset() — dərhal flush

// APCu — shared memory key-value store
// Process-lərarası görünür, amma yazma/oxuma atomik deyil (compound ops)
function incrementCounter(string $key): int
{
    // apcu_inc — atomic increment, thread/process-safe
    return apcu_inc($key, 1, $success);
}

// Redis ilə distributed visibility
// WATCH + MULTI/EXEC — optimistic locking
function transferBalance(Redis $redis, int $from, int $to, int $amount): bool
{
    return $redis->transaction(function ($tx) use ($from, $to, $amount) {
        $tx->watch("balance:$from");
        $fromBalance = (int) $redis->get("balance:$from");

        if ($fromBalance < $amount) {
            $tx->unwatch();
            return false;
        }

        $tx->multi();
        $tx->decrBy("balance:$from", $amount);
        $tx->incrBy("balance:$to", $amount);
        return $tx->exec() !== null; // WATCH dəyişibsə — null qaytar, retry
    });
}
```

## Praktik Tapşırıqlar

- Java-da `volatile` olmadan flag loop ssenarini reproduce edin — Thread B niyə çıxa bilmir?
- Double-checked locking-i `volatile` olmadan yazın, `jcstress` (Java Concurrency Stress) ilə bug-ı göstərin
- Go-da `go run -race main.go` ilə data race-i detect edin, channel ilə həll edin
- `volatile` vs `synchronized` visibility ssenarini benchmark edin
- PHP-də APCu-da race condition ssenarini yazın: iki process eyni sayacı artırır

## Əlaqəli Mövzular
- `10-atomic-operations.md` — CAS-ın memory ordering-dən istifadəsi
- `12-lock-free-structures.md` — Memory model üzərindəki lock-free kod
- `03-mutex-semaphore.md` — synchronized visibility qarantiyası
- `02-race-conditions.md` — Visibility problemi race condition-ın bir növüdür
