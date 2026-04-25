# 66 — Concurrency: Locks (ReentrantLock, ReadWriteLock, StampedLock)

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [ReentrantLock vs synchronized](#reentrantlock-vs-synchronized)
2. [tryLock, Timed Lock, Interruptible Lock](#trylock-timed-lock-interruptible-lock)
3. [ReadWriteLock](#readwritelock)
4. [StampedLock — Optimistic Reading](#stampedlock)
5. [Condition — await/signal](#condition)
6. [Praktik Müqayisə](#praktik-muqayise)
7. [İntervyu Sualları](#intervyu-sualları)

---

## ReentrantLock vs synchronized

`ReentrantLock` — `java.util.concurrent.locks` paketindən, `synchronized`-ın daha güclü alternativi.

```java
import java.util.concurrent.locks.*;

// synchronized ilə:
public class SynchronizedExample {
    private int counter = 0;

    public synchronized void increment() {
        counter++;
    }
}

// ReentrantLock ilə:
public class ReentrantLockExample {
    private int counter = 0;
    private final ReentrantLock lock = new ReentrantLock();

    public void increment() {
        lock.lock(); // Lock al
        try {
            counter++;
        } finally {
            lock.unlock(); // HƏMİŞƏ finally-də unlock et!
        }
    }
}
```

**YANLIŞ — unlock finally-də deyil:**

```java
// YANLIŞ — exception atılsa unlock çağırılmır, lock əbədi qalar!
lock.lock();
counter++; // Burada exception atılsa...
lock.unlock(); // ...buraya çatılmır → deadlock!

// DOĞRU
lock.lock();
try {
    counter++;
} finally {
    lock.unlock(); // Həmişə icra olunur
}
```

**`synchronized` vs `ReentrantLock` müqayisəsi:**

| Xüsusiyyət               | synchronized       | ReentrantLock           |
|--------------------------|--------------------|-------------------------|
| Syntax                   | Dil səviyyəsindədir | Kod (lock/unlock)       |
| tryLock()                | Xeyr               | Bəli                    |
| Timeout ilə lock         | Xeyr               | Bəli                    |
| Interruptible lock       | Xeyr               | Bəli                    |
| Fair mode                | Xeyr               | Bəli (`new ReentrantLock(true)`) |
| Condition (çoxlu)        | Bir (wait/notify)  | Çoxlu Condition         |
| Unlock unuda bilərsiniz  | Xeyr               | Bəli (risk!)            |
| Performans               | Yaxşı (JVM optimize edir) | Oxşar, bəzən daha yaxşı |

---

## tryLock, Timed Lock, Interruptible Lock

### tryLock() — Blok etmədən cəhd et

```java
public class TryLockDemo {
    private final ReentrantLock lock = new ReentrantLock();

    public boolean tryOperation() {
        // Lock əlçatındırsa al, yoxsa false qaytar (GÖZLƏMƏ!)
        if (lock.tryLock()) {
            try {
                performCriticalOperation();
                return true;
            } finally {
                lock.unlock();
            }
        } else {
            // Lock başqasındadır — alternativ hərəkət
            System.out.println("Resurs məşğuldur, sonra yenidən cəhd edin");
            return false;
        }
    }

    private void performCriticalOperation() {
        System.out.println("Kritik əməliyyat icra olunur...");
    }
}
```

### Timed tryLock() — Müddətli gözləmə

```java
public class TimedLockDemo {
    private final ReentrantLock lockA = new ReentrantLock();
    private final ReentrantLock lockB = new ReentrantLock();

    // Deadlock-ın qarşısını almaq üçün timed tryLock
    public boolean transferFunds(int amount) throws InterruptedException {
        while (true) {
            // lockA-nı 100ms gözlə
            if (lockA.tryLock(100, TimeUnit.MILLISECONDS)) {
                try {
                    // lockB-ni 100ms gözlə
                    if (lockB.tryLock(100, TimeUnit.MILLISECONDS)) {
                        try {
                            // Hər iki lock alındı!
                            System.out.println("Transfer: " + amount);
                            return true;
                        } finally {
                            lockB.unlock();
                        }
                    }
                    // lockB alına bilmədi, lockA buraxılır (finally-də)
                } finally {
                    lockA.unlock();
                }
            }
            // Hər iki lock alına bilmədisə — gözlə və yenidən cəhd et
            System.out.println("Lock alına bilmədi, yenidən cəhd...");
            Thread.sleep(50 + (long)(Math.random() * 100)); // Backoff
        }
    }
}
```

### lockInterruptibly() — Kesilə Bilən Lock

```java
public class InterruptibleLockDemo {
    private final ReentrantLock lock = new ReentrantLock();

    public void performTask() throws InterruptedException {
        // Adi lock.lock() — interrupt-u nəzərə almır
        // lockInterruptibly() — gözləyərkən interrupt gəlsə, InterruptedException atır
        lock.lockInterruptibly();
        try {
            // Uzun müddətli əməliyyat
            for (int i = 0; i < 10; i++) {
                System.out.println("Addım " + i);
                Thread.sleep(500);
            }
        } finally {
            lock.unlock();
        }
    }

    public static void main(String[] args) throws InterruptedException {
        InterruptibleLockDemo demo = new InterruptibleLockDemo();

        Thread t1 = new Thread(() -> {
            try {
                demo.performTask(); // Lock alır
            } catch (InterruptedException e) {
                System.out.println("T1 kesildi");
            }
        });

        Thread t2 = new Thread(() -> {
            try {
                demo.performTask(); // Lock gözləyir — interrupt gəlsə kesilə bilər
            } catch (InterruptedException e) {
                System.out.println("T2 lock gözləyərkən kesildi!");
            }
        });

        t1.start();
        Thread.sleep(100);
        t2.start();
        Thread.sleep(500);

        t2.interrupt(); // t2-ni ləğv et — lock gözləyərkən kesilir
        t1.join();
        t2.join();
    }
}
```

### Fair Mode

```java
// Fair mode — FIFO sırası ilə lock verir (starvation-u aradan qaldırır)
ReentrantLock fairLock = new ReentrantLock(true);

// Unfair mode (default) — performans daha yaxşı, amma starvation riski
ReentrantLock unfairLock = new ReentrantLock(false);

// Fair mode haqqında məlumat
System.out.println("Fair mode: " + fairLock.isFair()); // true
System.out.println("Gözləyən thread sayı: " + fairLock.getQueueLength());
System.out.println("Lock tutulubmu: " + fairLock.isLocked());
System.out.println("Cari thread-dəmi: " + fairLock.isHeldByCurrentThread());
```

---

## ReadWriteLock

Çoxlu oxucu / tək yazıcı — oxuma əməliyyatları paralel, yazma isə eksklüziv.

```java
import java.util.concurrent.locks.*;
import java.util.*;

public class ThreadSafeCache<K, V> {
    private final Map<K, V> cache = new HashMap<>();
    private final ReadWriteLock rwLock = new ReentrantReadWriteLock();
    private final Lock readLock = rwLock.readLock();   // Çoxlu oxucu eyni anda
    private final Lock writeLock = rwLock.writeLock(); // Yalnız bir yazıcı

    public V get(K key) {
        readLock.lock(); // Çoxlu thread eyni anda oxuya bilər!
        try {
            return cache.get(key);
        } finally {
            readLock.unlock();
        }
    }

    public void put(K key, V value) {
        writeLock.lock(); // Yalnız bir thread yaza bilər, oxuma da bloklanır
        try {
            cache.put(key, value);
        } finally {
            writeLock.unlock();
        }
    }

    public int size() {
        readLock.lock();
        try {
            return cache.size();
        } finally {
            readLock.unlock();
        }
    }

    // Atomik "get-or-compute" əməliyyatı
    public V getOrCompute(K key, java.util.function.Supplier<V> supplier) {
        // Əvvəlcə read lock ilə yoxla (performans üçün)
        readLock.lock();
        try {
            V value = cache.get(key);
            if (value != null) return value;
        } finally {
            readLock.unlock();
        }

        // Yoxdursa write lock al
        writeLock.lock();
        try {
            // Double-check — başqa thread artıq yaza bilər
            V value = cache.get(key);
            if (value == null) {
                value = supplier.get();
                cache.put(key, value);
            }
            return value;
        } finally {
            writeLock.unlock();
        }
    }
}
```

**ReadWriteLock qaydaları:**
- Oxuma lock-ı aktivdirsə → digər oxuyanlar girə bilir
- Oxuma lock-ı aktivdirsə → yazıcı gözləyir
- Yazma lock-ı aktivdirsə → oxuyanlar da, yazıcılar da gözləyir
- **Read-heavy** (çox oxuma, az yazma) ssenariləri üçün ideal

---

## StampedLock

Java 8-dən gəlir. `ReadWriteLock`-dan daha performanslı — **optimistic reading** dəstəkləyir.

```java
import java.util.concurrent.locks.*;

public class Point {
    private double x, y;
    private final StampedLock lock = new StampedLock();

    // Yazma — exclusive lock
    public void move(double deltaX, double deltaY) {
        long stamp = lock.writeLock();
        try {
            x += deltaX;
            y += deltaY;
        } finally {
            lock.unlockWrite(stamp);
        }
    }

    // Pessimistic oxuma — ReadWriteLock kimi
    public double distanceFromOriginPessimistic() {
        long stamp = lock.readLock();
        try {
            return Math.sqrt(x * x + y * y);
        } finally {
            lock.unlockRead(stamp);
        }
    }

    // Optimistic oxuma — lock alınmır! Yazma olubsa yenidən cəhd edirik
    public double distanceFromOriginOptimistic() {
        long stamp = lock.tryOptimisticRead(); // Lock almır!

        // Dəyərləri oxu (lock yoxdur, yazma ola bilər)
        double curX = x, curY = y;

        // Oxuma zamanı yazma baş verdimi?
        if (!lock.validate(stamp)) {
            // Bəli, yazma baş verdi — pessimistic oxumaya keç
            stamp = lock.readLock();
            try {
                curX = x;
                curY = y;
            } finally {
                lock.unlockRead(stamp);
            }
        }

        return Math.sqrt(curX * curX + curY * curY);
    }

    // Read lock-dan Write lock-a yüksəltmə (upgrade)
    public void moveIfAtOrigin(double newX, double newY) {
        long stamp = lock.readLock();
        try {
            while (x == 0.0 && y == 0.0) {
                // Read-dan write-a yüksəlt
                long ws = lock.tryConvertToWriteLock(stamp);
                if (ws != 0L) {
                    stamp = ws; // Uğurlu yüksəltmə
                    x = newX;
                    y = newY;
                    break;
                } else {
                    // Yüksəltmə uğursuz — write lock al
                    lock.unlockRead(stamp);
                    stamp = lock.writeLock();
                }
            }
        } finally {
            lock.unlock(stamp);
        }
    }
}
```

**StampedLock vs ReadWriteLock:**

| Xüsusiyyət           | ReadWriteLock     | StampedLock               |
|----------------------|-------------------|---------------------------|
| Optimistic reading   | Xeyr              | Bəli                      |
| Reentrant            | Bəli              | **Xeyr!** (deadlock riski)|
| Condition dəstəyi    | Bəli              | Xeyr                      |
| Performans           | Yaxşı             | Daha yaxşı (az yük)       |
| Mürəkkəblik          | Orta              | Yüksək                    |

**Diqqət:** `StampedLock` **reentrant deyil**! Eyni thread-də iki dəfə lock almağa çalışarsa deadlock yaranır.

---

## Condition — await/signal

`Condition` — `wait()/notify()` mexanizminin daha güclü alternativi. Bir lock üzərində çoxlu şərt (condition) yaratmaq olar.

```java
import java.util.concurrent.locks.*;
import java.util.*;

public class BoundedBuffer<T> {
    private final Queue<T> buffer = new LinkedList<>();
    private final int capacity;

    private final ReentrantLock lock = new ReentrantLock();
    // İki fərqli Condition — notFull və notEmpty
    private final Condition notFull  = lock.newCondition(); // "Boşluq var" şərti
    private final Condition notEmpty = lock.newCondition(); // "Element var" şərti

    public BoundedBuffer(int capacity) {
        this.capacity = capacity;
    }

    public void put(T item) throws InterruptedException {
        lock.lock();
        try {
            while (buffer.size() == capacity) {
                System.out.println("Bufer dolu, gözləyir...");
                notFull.await(); // Yalnız "notFull" şərtini gözlə
            }
            buffer.add(item);
            System.out.println("Əlavə edildi: " + item);
            notEmpty.signal(); // "notEmpty" şərtini gözləyəni oyat
        } finally {
            lock.unlock();
        }
    }

    public T take() throws InterruptedException {
        lock.lock();
        try {
            while (buffer.isEmpty()) {
                System.out.println("Bufer boş, gözləyir...");
                notEmpty.await(); // Yalnız "notEmpty" şərtini gözlə
            }
            T item = buffer.poll();
            System.out.println("Çıxarıldı: " + item);
            notFull.signal(); // "notFull" şərtini gözləyəni oyat
            return item;
        } finally {
            lock.unlock();
        }
    }
}
```

**wait()/notify() ilə müqayisə:**

```java
// synchronized + wait/notify — yalnız bir şərt:
synchronized (lock) {
    while (!conditionMet) lock.wait();
    // iş
    lock.notifyAll(); // Bütün gözləyənlər oyanır (lazımsız oyanmalar)
}

// ReentrantLock + Condition — çoxlu şərt, dəqiq siqnal:
lock.lock();
try {
    while (!conditionMet) specificCondition.await(); // Yalnız bu şərti gözlə
    // iş
    otherCondition.signal(); // Yalnız lazımlı thread oyanır
} finally {
    lock.unlock();
}
```

**Condition metodları:**

```java
Condition cond = lock.newCondition();

cond.await();                              // Gözlə (spurious wakeup mümkün)
cond.await(2, TimeUnit.SECONDS);           // Müddətli gözlə
cond.awaitUninterruptibly();               // Kesilməz gözlə
cond.awaitNanos(1_000_000_000L);           // Nanosaniyə ilə gözlə
cond.awaitUntil(new Date());               // Tarixə qədər gözlə

cond.signal();                             // Bir thread-i oyat
cond.signalAll();                          // Bütün gözləyənləri oyat
```

---

## Praktik Müqayisə

### Hansı Lock-ı Seçmək?

```java
// 1. Sadə mutual exclusion → synchronized (əgər tryLock lazım deyilsə)
public synchronized void simpleMethod() { ... }

// 2. Timeout/tryLock/fair/interruptible lazımdırsa → ReentrantLock
ReentrantLock lock = new ReentrantLock();
if (lock.tryLock(1, TimeUnit.SECONDS)) { ... }

// 3. Çox oxuma, az yazma → ReadWriteLock
ReadWriteLock rwLock = new ReentrantReadWriteLock();
// oxuma: rwLock.readLock().lock()
// yazma: rwLock.writeLock().lock()

// 4. Maksimum performans, çox oxuma → StampedLock
StampedLock sl = new StampedLock();
long stamp = sl.tryOptimisticRead();
// oxu, validate et, lazım olursa readLock al

// 5. Çoxlu şərt lazımdırsa → ReentrantLock + Condition
ReentrantLock lock2 = new ReentrantLock();
Condition notFull = lock2.newCondition();
Condition notEmpty = lock2.newCondition();
```

### Thread-Safe Leaderboard Nümunəsi

```java
public class Leaderboard {
    private final List<String> topPlayers = new ArrayList<>();
    private final ReentrantReadWriteLock rwLock = new ReentrantReadWriteLock();

    // Çox çağırılan oxuma metodu — paralel oxuma
    public List<String> getTop10() {
        rwLock.readLock().lock();
        try {
            return new ArrayList<>(topPlayers.subList(0, Math.min(10, topPlayers.size())));
        } finally {
            rwLock.readLock().unlock();
        }
    }

    // Nadir yazma metodu — eksklüziv
    public void updateScore(String player, int newScore) {
        rwLock.writeLock().lock();
        try {
            topPlayers.remove(player);
            // Sıralı əlavə et
            topPlayers.add(player);
            topPlayers.sort(Comparator.reverseOrder());
        } finally {
            rwLock.writeLock().unlock();
        }
    }
}
```

---

## İntervyu Sualları

**S: `ReentrantLock` nə vaxt `synchronized`-dan üstündür?**
C: 1) `tryLock()` lazımdırsa (blok etmədən), 2) timeout ilə lock lazımdırsa, 3) `lockInterruptibly()` lazımdırsa, 4) fair mode lazımdırsa, 5) çoxlu `Condition` lazımdırsa.

**S: `ReadWriteLock` nə vaxt istifadə etmək lazımdır?**
C: Oxuma əməliyyatları yazma əməliyyatlarından çox olduqda. Read-heavy ssenarilərdə — bir çox thread paralel oxuya bilir, yalnız yazma zamanı bloklanma baş verir.

**S: StampedLock-un optimistic read nədir?**
C: Lock almadan dəyərləri oxuyursan, sonra `validate()` ilə yoxlayırsın ki, oxuma zamanı yazma baş veribmi. Yazma baş veribsə, pessimistic readLock-a keçirsən. Performans üstünlüyü var — lock contention yoxdur.

**S: `StampedLock` niyə reentrant deyil?**
C: Dizayn seçimidir. Reentrant olmaması performans üçün edilib. Eyni thread-də iki dəfə lock almaq deadlock-a səbəb olur.

**S: `Condition.await()` vs `Object.wait()` fərqi?**
C: Hər ikisi mövcud lock-ı buraxır. Fərq: Condition — bir ReentrantLock üzərindəki birdən çox şərt yaratmağa imkan verir. `Object.wait()` — yalnız bir şərt (object-in monitor-u). Condition daha dəqiq `signal()` imkanı verir.

**S: `lock()` vs `lockInterruptibly()` fərqi?**
C: `lock()` — interrupt-u nəzərə almır, gözləməyə davam edir. `lockInterruptibly()` — gözləyərkən interrupt gəlsə `InterruptedException` atır.

**S: unlock() niyə finally-də olmalıdır?**
C: Əgər try blokunda exception atılsa və unlock() finally-də deyilsə, lock əbədi qalar. Digər thread-lər bu lock-ı ala bilməz — deadlock.

**S: ReadWriteLock-da yazma lock-ı alınanda nə baş verir?**
C: Bütün yeni oxuma cəhdləri bloklanır. Mövcud oxumalar bitdikdən sonra yazma lock-ı alınır. Yazma davam edərkən nə oxuma, nə yazma mümkündür.
