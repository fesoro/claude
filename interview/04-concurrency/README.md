# Concurrency Interview Mövzuları

Bu folder concurrency və parallel proqramlaşdırma üzrə interview suallarını əhatə edir. Middle səviyyəsindən başlayaraq Architect səviyyəsinə qədər 15 mövzu — fundamental thread anlayışlarından lock-free data strukturlarına, Actor Model-ə qədər.

---

## Mövzular — Səviyyəyə Görə

### Middle ⭐⭐ (Əsas anlayışlar)

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-threads-vs-processes.md](01-threads-vs-processes.md) | Threads vs Processes |
| 02 | [02-race-conditions.md](02-race-conditions.md) | Race Conditions |
| 03 | [03-mutex-semaphore.md](03-mutex-semaphore.md) | Mutex and Semaphore |

---

### Senior ⭐⭐⭐ (Praktik tətbiq)

| # | Fayl | Mövzu |
|---|------|-------|
| 04 | [04-deadlock-prevention.md](04-deadlock-prevention.md) | Deadlock Detection and Prevention |
| 05 | [05-thread-pools.md](05-thread-pools.md) | Thread Pools |
| 06 | [06-async-await.md](06-async-await.md) | Async/Await and Futures |
| 07 | [07-event-loop.md](07-event-loop.md) | Event Loop |
| 08 | [08-producer-consumer.md](08-producer-consumer.md) | Producer-Consumer Pattern |
| 09 | [09-read-write-lock.md](09-read-write-lock.md) | Read-Write Lock |
| 13 | [13-green-threads.md](13-green-threads.md) | Green Threads / Goroutines / Fibers |

---

### Lead ⭐⭐⭐⭐ (Dərin anlayış)

| # | Fayl | Mövzu |
|---|------|-------|
| 10 | [10-atomic-operations.md](10-atomic-operations.md) | Atomic Operations |
| 11 | [11-memory-models.md](11-memory-models.md) | Memory Models and Visibility |
| 12 | [12-lock-free-structures.md](12-lock-free-structures.md) | Lock-Free Data Structures |
| 14 | [14-reactive-programming.md](14-reactive-programming.md) | Reactive Programming |

---

### Architect ⭐⭐⭐⭐⭐ (Sistem dizaynı)

| # | Fayl | Mövzu |
|---|------|-------|
| 15 | [15-actor-model.md](15-actor-model.md) | Actor Model |

---

## Reading Paths

### PHP / Laravel Developer üçün başlangıc yolu
Concurrency biliklərini dərinləşdirmək, Laravel Queue, Octane, Fiber anlayışı:

```
01 → 02 → 03 → 08 (Producer-Consumer / Queue)
           ↓
       06 → 07 (Async/Await, Event Loop)
           ↓
       13 (Fiber, Green Threads — PHP 8.1)
           ↓
       05 (Thread Pools)
```

### Java / Spring Developer üçün
Concurrent programming, WebFlux, Virtual Threads:

```
01 → 02 → 03 → 04 (Deadlock)
           ↓
       05 (Thread Pool / ExecutorService)
       09 (RWLock / StampedLock)
       10 (Atomic / CAS / LongAdder)
       11 (JMM / happens-before / volatile)
       12 (ConcurrentLinkedQueue / SkipList)
       13 (Virtual Threads — Java 21)
       14 (Reactor / WebFlux)
```

### Go Developer üçün
Goroutine, channel, sync paketi:

```
01 → 02 → 03 → 13 (Goroutine — əvvəlcə bunu)
           ↓
       04 (Deadlock — Go runtime detect edir)
       07 (Event Loop vs Go scheduler)
       08 (Channel = Producer-Consumer)
       09 (sync.RWMutex)
       10 (sync/atomic)
       12 (Lock-Free Structures)
```

### System Design / Architect üçün
Distributed concurrency, Actor Model, Reactive:

```
10 → 11 → 12 (Lock-Free foundation)
           ↓
       14 (Reactive Streams / backpressure)
           ↓
       15 (Actor Model — distributed concurrency)
```

### Senior Interview Hazırlığı (Sürətli)
2-3 günlük fokuslu hazırlıq:

```
Gün 1: 01, 02, 03, 04 (Əsaslar + Deadlock)
Gün 2: 05, 06, 07, 08, 09 (Practical Senior mövzuları)
Gün 3: 13, 10, 11 (Green Threads, Atomic, Memory Model)
```

---

## Ən Çox Soruşulan Suallar

**Middle/Junior:**
- Thread-Process fərqi nədir?
- Race condition nədir? Nümunə ver.
- Mutex vs Semaphore fərqi?

**Senior:**
- Deadlock-u necə reproduce edərdiniz? Necə həll edərdiniz?
- PHP-FPM niyə process-based-dir?
- Node.js single-threaded olduğu halda necə concurrent-dir?
- Producer-Consumer bounded queue + backpressure necə işləyir?
- RWLock-da writer starvation nədir? Necə həll olunur?
- Goroutine OS thread-dən niyə ucuzdur?

**Lead:**
- ABA problemi nədir? `AtomicStampedReference` niyə lazımdır?
- `volatile` = atomic? (`volatile int i; i++` thread-safe-dirmi?)
- `LongAdder` vs `AtomicLong` — nə vaxt hansı?
- Double-checked locking niyə `volatile` tələb edir?
- Lock-free stack necə yazılır? ABA burada nə yaradır?
- `flatMap` vs `concatMap` fərqi? Nə zaman hansı?

**Architect:**
- Actor Model-i distributed sistemdə niyə seçərdiniz?
- Erlang-ın "Let it crash" fəlsəfəsi nə deməkdir?
- Supervision tree-nin "error-kernel" pattern-i nədir?
- Actor Model-da deadlock mümkündürmü?
