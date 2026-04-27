# Deadlock Detection and Prevention (Senior ⭐⭐⭐)

## İcmal
Deadlock — iki və ya daha çox thread-in bir-birinin resursu buraxmasını gözlədiyindən heç birinin irəliləyə bilmədiyi vəziyyətdir. Database deadlock-larından fərqli olaraq application-level deadlock-lar database tərəfindən avtomatik detect edilmir — program donur. Bu mövzu Senior interview-larda həm nəzəriyyə, həm real debug kimi soruşulur.

## Niyə Vacibdir
Application deadlock debug etmək ən çətin problemlərdən biridir — program donur, exception yoxdur, log yoxdur. İnterviewer bu sualla sizin deadlock-un Coffman şərtlərini, prevention strategiyalarını, thread dump analizi, livelock/starvation fərqlərini bildiyinizi yoxlayır.

---

## Əsas Anlayışlar

**Coffman Şərtlər — 4-ü eyni anda olmalıdır:**
1. **Mutual Exclusion:** Resurs yalnız bir thread tərəfindən istifadə oluna bilər
2. **Hold and Wait:** Thread resurs tutaraq digərini gözləyir
3. **No Preemption:** Resurs zorla alına bilməz, sahibi könüllü buraxmalıdır
4. **Circular Wait:** T1 → Lock(A) → gözlər Lock(B); T2 → Lock(B) → gözlər Lock(A) — dövr

- **Deadlock Prevention:** 4 Coffman şərtindən birini aradan qaldırmaq — lock ordering Circular Wait-i aradan qaldırır
- **Deadlock Detection:** Wait-for graph-ı qurmaq; dövr varsa deadlock var — database engine bunu avtomatik edir
- **Deadlock Avoidance:** Banker's Algorithm — resource allocation-dan əvvəl "safe state" yoxlanır; praktikada nadir istifadə olunur
- **Lock Ordering:** Həmişə eyni sırada lock al — T1 A→B, T2 B→A deyil, T2 də A→B almalıdır; Circular Wait aradan qalxır
- **Lock Timeout:** `tryLock(timeout)` — müəyyən vaxt gözlədikdən sonra lock-dan çəkil; deadlock törəmir amma starvation riski var
- **Thread Dump:** JVM-in cari thread vəziyyətinin snapshotı — `jstack <pid>` ilə alınır; "Found one Java-level deadlock" mesajı
- **jstack:** JDK-nın thread dump aləti — `jstack <pid>` > `threads.txt`; production-da düz `kill -3 <pid>` da işləyir
- **jcmd:** `jcmd <pid> Thread.print` — modern alternativ; daha çox məlumat verir
- **Livelock:** Deadlock deyil — hər iki thread aktiv, lakin bir-birinə "hörmət edərək" irəliləmir; routing loop kimi
- **Starvation:** Thread heç vaxt resurs ala bilmir — digərləri həmişə əvvəl alır; fair lock ilə həll edilir
- **Resource Hierarchy:** Resurslara iyerarxik order ver, kiçikdən böyüyə lock al — lock ordering-in formal versiyası
- **Lock-free Programming:** Lock olmadan atomic CAS operasiyaları ilə — deadlock mümkün deyil; amma ABA problem, retry loop mürəkkəbliyi var
- **Goroutine Deadlock (Go):** Go runtime bütün goroutine-lər blocked olduqda: "all goroutines are asleep — deadlock!" xətası verir
- **Database Deadlock vs Application Deadlock:** DB engine deadlock detect edib birini rollback edir; application-level-da bu mexanizm yoxdur
- **Nested Lock Antipattern:** `lock(A) { lock(B) { lock(C) {...} } }` — nə qədər dərin, o qədər deadlock riski
- **Lock Coarsening:** Bir neçə əməliyyatı tək lock altında birləşdirmək — daha az lock acquisition, deadlock riski azalır, amma contention artır
- **StampedLock (Java 8+):** RWLock-un daha performanslı variantı — optimistic read (lock almadan), validate, sonra full lock; throughput yüksək

---

## Praktik Baxış

**Interview-da yanaşma:**
- 4 Coffman şərtini saya bilmək — "lock ordering hansı şərti aradan qaldırır?" → Circular Wait
- Classic "bank transfer deadlock" nümunəsi hazırlayın — iki account, iki thread, əks sıra
- Prevention (lock ordering, timeout) vs Detection (thread dump, wait-for graph) fərqini izah edin

**Follow-up suallar:**
1. "Livelock nədir? Deadlock-dan nə ilə fərqlənir?" — Deadlock: thread-lər blocked, CPU 0%; Livelock: thread-lər active, CPU yüksək, amma irəliləmir
2. "Thread dump necə alınır, necə oxunur?" — `jstack <pid>`; "waiting to lock", "locked" ifadələri; circular dependency axtarılır
3. "Lock ordering production-da niyə çətin tətbiq edilir?" — Dinamik lock order: `transfer(from, to)` — from.id < to.id göstəricisi lazımdır; statik sıra mümkün deyil
4. "Database-dəki deadlock application deadlock-dan nə ilə fərqlənir?" — DB engine detect edib victim seçir, rollback edir; application-da bu avtomatik baş vermir
5. "Deadlock aşkar etmək üçün nə izləyərsiniz?" — Thread-in blocked müddəti; lock acquisition latency; thread count in BLOCKED state
6. "Lock-free kodun deadlock-u ola bilərmi?" — Xeyr; amma livelock ola bilər (CAS spin loop daima fail olursa)

**Code review red flags:**
- `lock(A)` içərisindən başqa `lock(B)` çağırışı — sifarişsiz nested lock; lock order sənədlənməyib
- `lock()` finally-siz — exception-da deadlock
- Callback-lər içindən lock tutmaq — callback başqa lock tutarsa circular dependency
- `synchronized` method-dan başqa `synchronized` method-a çağırış — reentrant-sa ok, deyilsə deadlock

**Production debugging ssenariləri:**
- Java service donur — CPU 0%, heap normal; `jstack` "Found one Java-level deadlock" göstərir; lock ordering düzəldilir
- Microservice mutual dependency: Service A, B-yi çağırır; B, A-yı çağırır; timeout yoxdur — circular call deadlock
- DB connection pool exhaustion deadlock: Outer task inner task-ı gözləyir, inner task pool-da thread gözləyir, pool outer task-ları gözləyir
- PHP queue worker deadlock: Redis lock + DB transaction — sıra fərqlidir; lock ordering qaydası tətbiq edilir

---

## Nümunələr

### Tipik Interview Sualı
"Java servisiniz production-da donur — exception yoxdur, log yoxdur, CPU 0%. Nə edərdiniz?"

### Güclü Cavab
Bu klassik deadlock əlamətidir: CPU sıfır, process hələ var, amma irəliləmir. Addım 1: `jstack <pid>` ilə thread dump alıram. Dump-da "Found one Java-level deadlock" görünsə, hansı thread-lərin hansı lock-ları tutub bir-birindən gözlədiyini aydın görürəm.

Root cause adətən lock ordering problemidir: T1 Lock-A alır sonra Lock-B gözləyir; T2 Lock-B alır sonra Lock-A gözləyir — dövr yaranır.

Fix: lock-ları həmişə eyni sırada al. Bank transfer case-inde: `account.id` kiçikdən böyüyə görə lock al — A.id < B.id isə əvvəl A, sonra B; əks halda əvvəl B, sonra A.

Əgər lock ordering mümkün deyilsə: `ReentrantLock.tryLock(100ms)` — lock ala bilmirsə geri çəkil, bütün lock-ları burax, gözlə, yenidən cəhd et. Daha uzunmüddətli həll: lock-free data structures (`ConcurrentHashMap`, `AtomicReference`) istifadə et.

### Kod Nümunəsi

```java
import java.util.concurrent.TimeUnit;
import java.util.concurrent.locks.ReentrantLock;

// ── PROBLEM: Deadlock — əks sıralı lock alma ─────────────────────
public class DeadlockDemo {
    private final Object lockA = new Object();
    private final Object lockB = new Object();

    public void method1() throws InterruptedException {
        synchronized (lockA) {
            System.out.println("T1: lockA alındı, lockB gözləyir...");
            Thread.sleep(100); // Race pəncərəsini aç
            synchronized (lockB) { // T2 lockB-ni tutub → DEADLOCK!
                System.out.println("T1: hər ikisi alındı");
            }
        }
    }

    public void method2() throws InterruptedException {
        synchronized (lockB) {
            System.out.println("T2: lockB alındı, lockA gözləyir...");
            Thread.sleep(100);
            synchronized (lockA) { // T1 lockA-nı tutub → DEADLOCK!
                System.out.println("T2: hər ikisi alındı");
            }
        }
    }

    public static void main(String[] args) {
        DeadlockDemo demo = new DeadlockDemo();
        Thread t1 = new Thread(() -> { try { demo.method1(); } catch (Exception e) {} });
        Thread t2 = new Thread(() -> { try { demo.method2(); } catch (Exception e) {} });
        t1.start();
        t2.start();
        // Program donur — "Found one Java-level deadlock" in jstack
    }
}

// ── HƏLL 1: Lock Ordering — həmişə eyni sıra ─────────────────────
public class DeadlockFix1 {
    private final Object lockA = new Object();
    private final Object lockB = new Object();

    private void doTransfer() {
        synchronized (lockA) {      // Həmişə A əvvəl
            synchronized (lockB) {  // Sonra B — eyni sıra
                System.out.println("Transfer executed");
            }
        }
    }

    // Hər iki thread eyni sıranı izləyir — circular wait yoxdur
}

// ── HƏLL 2: Bank Transfer — ID-yə görə sıra ─────────────────────
public class BankTransfer {
    public static void transfer(Account from, Account to, double amount) {
        // ID-yə görə sıra — deadlock mümkün deyil
        Account first  = from.getId() < to.getId() ? from : to;
        Account second = from.getId() < to.getId() ? to : from;

        synchronized (first) {
            synchronized (second) {
                if (from.getBalance() >= amount) {
                    from.debit(amount);
                    to.credit(amount);
                }
            }
        }
    }
}

// ── HƏLL 3: tryLock timeout ilə ─────────────────────────────────
public class TransferWithTimeout {
    private final ReentrantLock lockA = new ReentrantLock();
    private final ReentrantLock lockB = new ReentrantLock();

    public boolean transfer(double amount) {
        boolean gotA = false, gotB = false;
        try {
            gotA = lockA.tryLock(100, TimeUnit.MILLISECONDS);
            gotB = lockB.tryLock(100, TimeUnit.MILLISECONDS);

            if (gotA && gotB) {
                performTransfer(amount);
                return true;
            }
            // Lock-lardan birini ala bilmədik — deadlock yoxdur, amma yenidən cəhd et
            return false;
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            return false;
        } finally {
            // Sıra vacibdir — ikisini də burax
            if (gotB) lockB.unlock();
            if (gotA) lockA.unlock();
        }
    }

    private void performTransfer(double amount) {
        System.out.println("Transfer: " + amount);
    }
}
```

```java
// ── Thread Dump analizi (jstack output nümunəsi) ─────────────────
/*
  Komanda: jstack <pid>
  Çıxış:

  Found one Java-level deadlock:
  =============================
  "Thread-1":
    waiting to lock monitor 0x00007f... (a java.lang.Object)
    which is held by "Thread-0"

  "Thread-0":
    waiting to lock monitor 0x00007f... (a java.lang.Object)
    which is held by "Thread-1"

  Java stack information for the threads listed above:
  ===================================================
  "Thread-1":
      at DeadlockDemo.method2(DeadlockDemo.java:21)
      - waiting to lock <0x...> (a java.lang.Object)   ← lockA gözləyir
      - locked <0x...> (a java.lang.Object)             ← lockB tutub
      at java.lang.Thread.run(Thread.java:748)

  "Thread-0":
      at DeadlockDemo.method1(DeadlockDemo.java:11)
      - waiting to lock <0x...> (a java.lang.Object)   ← lockB gözləyir
      - locked <0x...> (a java.lang.Object)             ← lockA tutub
      at java.lang.Thread.run(Thread.java:748)

  Oxuma qaydası:
  - "waiting to lock" = gözlənilən lock
  - "locked" = tutulmuş lock
  - Hər iki thread bir-birinin "locked"-ini "waiting"-dir → dövr → deadlock
*/

// Thread dump alma üsulları:
// 1) kill -3 <pid>     — SIGQUIT signal (JVM stdout-a yazar)
// 2) jstack <pid>      — JDK tool
// 3) jcmd <pid> Thread.print  — modern, daha çox detail
// 4) VisualVM / JConsole — GUI ilə
```

```go
// ── Go-da deadlock: runtime avtomatik aşkar edir ─────────────────
package main

import (
    "fmt"
    "sync"
    "unsafe"
)

// YANLIŞ: Deadlock
func deadlockDemo() {
    var mu1, mu2 sync.Mutex
    done := make(chan bool)

    go func() {
        mu1.Lock()
        fmt.Println("G1: mu1 alındı")
        // time.Sleep(1ms) — race pəncərəsi
        mu2.Lock() // G2 mu2-ni tutub → DEADLOCK!
        fmt.Println("G1: mu2 alındı")
        mu2.Unlock()
        mu1.Unlock()
        done <- true
    }()

    go func() {
        mu2.Lock()
        fmt.Println("G2: mu2 alındı")
        mu1.Lock() // G1 mu1-i tutub → DEADLOCK!
        fmt.Println("G2: mu1 alındı")
        mu1.Unlock()
        mu2.Unlock()
        done <- true
    }()

    <-done // Go runtime: "all goroutines are asleep — deadlock!"
}

// DÜZGÜN: Pointer address-ə görə sıra
func safeOperation(mu1, mu2 *sync.Mutex) {
    first, second := mu1, mu2
    if uintptr(unsafe.Pointer(mu2)) < uintptr(unsafe.Pointer(mu1)) {
        first, second = mu2, mu1
    }
    first.Lock()
    defer first.Unlock()
    second.Lock()
    defer second.Unlock()
    fmt.Println("Safe operation executed")
}

// Channel-based — lock-free, deadlock mümkün deyil
func channelTransfer(from, to chan int, amount int) {
    // Goroutine-lar arasında məlumat ötürmək üçün channel
    // Lock olmadan → deadlock riski sıfır
    fromBalance := <-from
    from <- fromBalance - amount
    toBalance := <-to
    to <- toBalance + amount
}
```

```php
// PHP: Laravel DB deadlock — retry middleware
use Illuminate\Database\QueryException;

function transferWithRetry(int $fromId, int $toId, float $amount, int $maxRetries = 3): void
{
    $attempt = 0;

    while ($attempt < $maxRetries) {
        try {
            DB::transaction(function () use ($fromId, $toId, $amount) {
                // Lock ordering: həmişə kiçik ID əvvəl
                [$firstId, $secondId] = $fromId < $toId
                    ? [$fromId, $toId]
                    : [$toId, $fromId];

                // Pessimistic lock — sıralı
                $first  = Account::where('id', $firstId)->lockForUpdate()->first();
                $second = Account::where('id', $secondId)->lockForUpdate()->first();

                $from = $fromId === $first->id ? $first : $second;
                $to   = $fromId === $first->id ? $second : $first;

                if ($from->balance < $amount) {
                    throw new \RuntimeException('Insufficient funds');
                }

                $from->decrement('balance', $amount);
                $to->increment('balance', $amount);
            });

            return; // Uğurlu

        } catch (QueryException $e) {
            // MySQL deadlock error code: 1213
            if ($e->getCode() !== '40001' || $attempt >= $maxRetries - 1) {
                throw $e;
            }

            $attempt++;
            usleep(rand(10_000, 100_000)); // 10-100ms random backoff
        }
    }
}
```

### Yanlış Kod + Düzgün Kod

```java
// YANLIŞ: Livelock — hərhərə geri çəkilir
public class LivelockDemo {
    private boolean resource = false;

    public void thread1() {
        while (true) {
            if (!resource) {        // 1. resource boş görünür
                resource = true;    // 2. götür
                if (resource) {     // 3. başqası da götürdü? — həmişə true
                    resource = false; // 4. hörmətlə geri qoy
                    continue;       // 5. yenidən cəhd et
                }
                break;
            }
        }
    }
    // Thread1 və Thread2 eyni anda: hər ikisi geri qoyur, hər ikisi yenidən cəhd edir
    // CPU yanır, amma heç kim resursu istifadə etmir
}

// DÜZGÜN: Random backoff ilə — livelock-u kırır
public void safeThread() throws InterruptedException {
    Random rand = new Random();
    while (true) {
        if (lock.tryLock(rand.nextInt(50), TimeUnit.MILLISECONDS)) { // Random gözləmə
            try {
                doWork();
                return;
            } finally {
                lock.unlock();
            }
        }
        // Uğursuzsa random müddət gözlə — "eyni anda geri çəkilmə" çox azalır
        Thread.sleep(rand.nextInt(20));
    }
}
```

---

## Praktik Tapşırıqlar

- Java-da iki thread ilə bank transfer deadlock reproduce edin, `jstack` ilə thread dump alın
- Lock ordering tətbiq edin (ID-yə görə sıra), eyni ssenariyin deadlock-suz işlədiyini görün
- `tryLock(timeout)` + retry loop yazın; starvation halını da simulyasiya edin
- Go-da goroutine deadlock-u runtime-ın necə detect etdiyini görün; `runtime.NumGoroutine()` ilə izləyin
- Livelock scenario yaradın: hər thread digərindən "hörmət edir", random backoff ilə düzəldin

## Əlaqəli Mövzular
- `03-mutex-semaphore.md` — Lock primitiv-ləri
- `02-race-conditions.md` — Sinxronizasiya problematikası
- `05-thread-pools.md` — Thread pool exhaustion deadlock
- `01-threads-vs-processes.md` — Thread model fundamentals
