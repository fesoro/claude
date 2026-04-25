# 63 — Concurrency: Synchronized və Deadlock

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [Race Condition Nədir?](#race-condition-nedir)
2. [synchronized Method](#synchronized-method)
3. [synchronized Block](#synchronized-block)
4. [Object Lock vs Class Lock](#object-lock-vs-class-lock)
5. [Reentrant (Yenidən Girə Bilən) Xüsusiyyəti](#reentrant-xususiyyeti)
6. [Deadlock — Necə Yaranır?](#deadlock)
7. [Deadlock-un Qarşısını Almaq](#deadlockun-qarsisini-almaq)
8. [Livelock](#livelock)
9. [Starvation](#starvation)
10. [İntervyu Sualları](#intervyu-sualları)

---

## Race Condition Nədir?

**Race condition** — iki və ya daha çox thread eyni paylaşılan resursa eyni anda daxil olduqda və icra nəticəsi thread-lərin icra sırasına görə dəyişdikdə baş verir.

```java
// YANLIŞ — race condition nümunəsi
public class BankAccount {
    private int balance = 1000; // Hesab balansı

    public void withdraw(int amount) {
        // Bu əməliyyat 3 addımdır (atomik DEYİL!):
        // 1. balance-i oxu (məs: 1000)
        // 2. amount-u çıx (1000 - 500 = 500)
        // 3. Nəticəni yaz (balance = 500)

        if (balance >= amount) {     // Thread-A: balance=1000, şərt doğrudur
            // Thread-B buraya girir: balance=1000, şərt doğrudur
            balance -= amount;       // Thread-A: balance = 500
            // Thread-B: balance = 500 (yanlış! 0 olmalıydı)
            System.out.println("Çəkildi: " + amount + ", Qalıq: " + balance);
        } else {
            System.out.println("Kifayət qədər balans yoxdur");
        }
    }
}

// Sınaq:
public class RaceConditionDemo {
    public static void main(String[] args) throws InterruptedException {
        BankAccount account = new BankAccount();

        Thread t1 = new Thread(() -> account.withdraw(500), "Thread-A");
        Thread t2 = new Thread(() -> account.withdraw(500), "Thread-B");

        t1.start();
        t2.start();

        t1.join();
        t2.join();

        // Gözlənilən: 0 (iki dəfə 500 çəkildi)
        // Mümkün nəticə: -500 (hər iki thread eyni anda şərti yoxladı!)
    }
}
```

**Niyə baş verir?** Java-da `balance -= amount` bir bytecode deyil, bir neçə addımdır:
```
getfield balance      // 1. oxu
isub                  // 2. çıxart
putfield balance      // 3. yaz
```
Thread-lər bu addımlar arasında keçiş edə bilər.

---

## synchronized Method

```java
// DOĞRU — synchronized method
public class SafeBankAccount {
    private int balance = 1000;

    // synchronized — bu metoda yalnız bir thread daxil ola bilər
    public synchronized void withdraw(int amount) {
        if (balance >= amount) {
            balance -= amount;
            System.out.println(Thread.currentThread().getName() +
                " çəkdi: " + amount + ", Qalıq: " + balance);
        } else {
            System.out.println("Balans azdır: " + balance);
        }
    }

    public synchronized int getBalance() {
        return balance;
    }

    // synchronized olmayan metod — eyni anda çoxlu thread daxil ola bilər
    public String getAccountInfo() {
        return "Hesab məlumatı"; // Yalnız oxuma, dəyişdirmə yox
    }
}
```

**Necə işləyir?** `synchronized` metod — `this` obyektinin lock-ını alır. İkinci thread lock almağa çalışdığında BLOCKED vəziyyətinə keçir.

---

## synchronized Block

```java
// synchronized block — daha dəqiq, performans üçün daha yaxşı
public class BankAccountWithBlock {
    private int balance = 1000;
    private final Object balanceLock = new Object(); // Xüsusi lock obyekti

    public void withdraw(int amount) {
        // Yalnız kritik bölgəni lock et — bütün metodu yox!
        synchronized (balanceLock) {
            if (balance >= amount) {
                balance -= amount;
            }
        }
        // Bu hissə lock olmadan işləyir — performans üstünlüyü
        System.out.println("Əməliyyat tamamlandı");
    }

    public void deposit(int amount) {
        synchronized (balanceLock) {
            balance += amount;
        }
    }
}
```

**synchronized method vs synchronized block:**

```java
// Bu iki kod ekvivalentdir:

// 1. synchronized method
public synchronized void method() {
    // bədən
}

// 2. synchronized block (this üzərində)
public void method() {
    synchronized (this) {
        // bədən
    }
}
```

**Niyə block daha yaxşıdır?**

```java
public class FileProcessor {
    private final List<String> processedFiles = new ArrayList<>();
    private final Object listLock = new Object();

    public void processFile(String filename) {
        // 1. Faili oxu — lock lazım deyil, uzun müddətli əməliyyat
        String content = readFileFromDisk(filename); // ~100ms

        // 2. Məzmunu emal et — lock lazım deyil
        String processed = processContent(content); // ~200ms

        // 3. Yalnız siyahıya əlavə edərkən lock al — qısa müddət
        synchronized (listLock) {
            processedFiles.add(filename); // ~1ms
        }

        // synchronized method olsaydı, 300ms ərzində başqa thread girə bilməzdi!
    }

    private String readFileFromDisk(String filename) { return "content"; }
    private String processContent(String content) { return content.toUpperCase(); }
}
```

---

## Object Lock vs Class Lock

```java
public class LockTypesDemo {
    private static int staticCounter = 0;
    private int instanceCounter = 0;

    // OBJECT LOCK — this üzərindəki lock
    // Hər instance üçün ayrı lock — iki instance paralel çalışa bilər
    public synchronized void incrementInstance() {
        instanceCounter++;
    }

    // CLASS LOCK — LockTypesDemo.class üzərindəki lock
    // Bütün instance-lar paylaşır — eyni zamanda yalnız biri icra edir
    public static synchronized void incrementStatic() {
        staticCounter++;
    }

    // Açıq şəkildə class lock
    public void method() {
        synchronized (LockTypesDemo.class) {
            staticCounter++;
        }
    }
}

// Sınaq:
LockTypesDemo obj1 = new LockTypesDemo();
LockTypesDemo obj2 = new LockTypesDemo();

// Bu iki thread paralel işləyə bilər (fərqli object lock-ları)
Thread t1 = new Thread(obj1::incrementInstance);
Thread t2 = new Thread(obj2::incrementInstance);

// Bu iki thread paralel işləyə BILMƏZ (eyni class lock)
Thread t3 = new Thread(LockTypesDemo::incrementStatic);
Thread t4 = new Thread(LockTypesDemo::incrementStatic);
```

---

## Reentrant Xüsusiyyəti

Java-nın intrinsic lock-ları **reentrant**-dır — eyni thread öz artıq sahib olduğu lock-ı yenidən ala bilər.

```java
public class ReentrantDemo {
    // Reentrant olmasaydı bu kod deadlock yaradardı!
    public synchronized void methodA() {
        System.out.println("methodA-da");
        methodB(); // Bu thread artıq lock-a sahibdir, yenidən ala bilər
    }

    public synchronized void methodB() {
        System.out.println("methodB-də");
        // Əgər reentrant olmasaydı: thread öz lock-ını gözlərdi → deadlock
    }
}

// Miras ilə reentrant:
class Parent {
    public synchronized void doWork() {
        System.out.println("Parent işi");
    }
}

class Child extends Parent {
    @Override
    public synchronized void doWork() {
        System.out.println("Child işi");
        super.doWork(); // Eyni thread, eyni lock (this üzərindəki) — reentrant!
    }
}
```

---

## Deadlock

**Deadlock** — iki və ya daha çox thread bir-birini gözlədikdə heç biri davam edə bilmir.

```java
// KLASSİK DEADLOCK nümunəsi
public class DeadlockDemo {
    private static final Object LOCK_A = new Object();
    private static final Object LOCK_B = new Object();

    public static void main(String[] args) {
        Thread thread1 = new Thread(() -> {
            synchronized (LOCK_A) {                    // Thread-1: A-nı alır
                System.out.println("Thread-1: A alındı");
                try { Thread.sleep(100); } catch (InterruptedException e) {}

                synchronized (LOCK_B) {                // Thread-1: B-ni gözləyir
                    System.out.println("Thread-1: B alındı");
                }
            }
        }, "Thread-1");

        Thread thread2 = new Thread(() -> {
            synchronized (LOCK_B) {                    // Thread-2: B-ni alır
                System.out.println("Thread-2: B alındı");
                try { Thread.sleep(100); } catch (InterruptedException e) {}

                synchronized (LOCK_A) {                // Thread-2: A-nı gözləyir
                    System.out.println("Thread-2: A alındı");
                }
            }
        }, "Thread-2");

        thread1.start();
        thread2.start();

        // DEADLOCK! Thread-1 B-ni, Thread-2 A-nı gözləyir — əbədi!
    }
}
```

**Deadlock-un 4 Şərti (Coffman şərtləri):**
1. **Mutual Exclusion** — resurs yalnız bir thread tərəfindən istifadə edilə bilər
2. **Hold and Wait** — thread resurs tutaraq digər resursu gözləyir
3. **No Preemption** — resurs zorla alına bilməz
4. **Circular Wait** — thread-lər dövri asılılıq içindədir (A→B→C→A)

**Deadlock-u Aşkar Etmək:**

```bash
# JVM deadlock-ı thread dump-da aşkar edir
jstack <pid>

# Və ya MBean ilə:
ThreadMXBean bean = ManagementFactory.getThreadMXBean();
long[] deadlockedThreads = bean.findDeadlockedThreads();
if (deadlockedThreads != null) {
    System.out.println("DEADLOCK AŞKAR EDİLDİ!");
    ThreadInfo[] info = bean.getThreadInfo(deadlockedThreads);
    for (ThreadInfo ti : info) {
        System.out.println(ti.getThreadName() + " gözləyir: " + ti.getLockName());
    }
}
```

---

## Deadlock-un Qarşısını Almaq

### 1. Sabit Sıra ilə Lock Almaq

```java
// YANLIŞ — sıra fərqlidir, deadlock riski
void transfer_WRONG(Account from, Account to, int amount) {
    synchronized (from) {      // Thread-1: from=AccountA alır
        synchronized (to) {    // Thread-2: to=AccountA-nı gözləyir (deadlock!)
            from.debit(amount);
            to.credit(amount);
        }
    }
}

// DOĞRU — həmişə System.identityHashCode() ilə sıralayırıq
void transfer_CORRECT(Account acc1, Account acc2, int amount) {
    // Kiçik hash-lı olanı həmişə birinci al
    Account first  = System.identityHashCode(acc1) < System.identityHashCode(acc2) ? acc1 : acc2;
    Account second = first == acc1 ? acc2 : acc1;

    synchronized (first) {
        synchronized (second) {
            // Həmişə eyni sırada lock alınır — deadlock yoxdur
            if (first == acc1) {
                acc1.debit(amount);
                acc2.credit(amount);
            } else {
                acc2.debit(amount);
                acc1.credit(amount);
            }
        }
    }
}
```

### 2. Timeout ilə Lock (tryLock)

```java
import java.util.concurrent.locks.*;

// ReentrantLock ilə timeout
ReentrantLock lockA = new ReentrantLock();
ReentrantLock lockB = new ReentrantLock();

boolean transferWithTimeout(int amount) throws InterruptedException {
    while (true) {
        if (lockA.tryLock(100, TimeUnit.MILLISECONDS)) {
            try {
                if (lockB.tryLock(100, TimeUnit.MILLISECONDS)) {
                    try {
                        // Hər iki lock alındı
                        performTransfer(amount);
                        return true;
                    } finally {
                        lockB.unlock();
                    }
                }
                // lockB alına bilmədisə, lockA-nı burax
            } finally {
                lockA.unlock();
            }
        }
        // Deadlock potensialı yarandısa, gözlə və yenidən cəhd et
        Thread.sleep(50 + (long)(Math.random() * 50)); // Rastgele gecikmə
    }
}
```

### 3. Bütün Resursları Birdən Al

```java
// Çoxlu lock-ı atomik olaraq almaq üçün global lock
private static final Object GLOBAL_LOCK = new Object();

void safeOperation(Object res1, Object res2) {
    synchronized (GLOBAL_LOCK) {
        // Hamısına birdən sahib olduqdan sonra istifadə et
        useResources(res1, res2);
    }
    // Performans baxımından pis (darboğaz), amma sadədir
}
```

---

## Livelock

**Livelock** — Thread-lər deadlock olmur, işləyir, amma irəliləmirlər.

```java
// İki həmkənar bir yolda bir-birinə yol verməyə çalışır — heç biri keçmir!
public class LivelockDemo {
    static boolean leftPersonStepped = false;
    static boolean rightPersonStepped = false;

    public static void main(String[] args) {
        Thread leftPerson = new Thread(() -> {
            while (!rightPersonStepped) {
                System.out.println("Sol: Sağa keçirəm");
                leftPersonStepped = true;
                try { Thread.sleep(100); } catch (InterruptedException e) { break; }

                if (rightPersonStepped) {
                    System.out.println("Sol: Keçdim!");
                    break;
                }
                // Yenə geri çəkil
                leftPersonStepped = false;
                System.out.println("Sol: Geri çəkilirəm");
            }
        });

        // Hər ikisi daim yer dəyişir, amma heç biri keçmir
        // Həll: Rastgele gözlə müddəti (Ethernet CSMA/CD prinsipi)
    }
}
```

---

## Starvation

**Starvation** — Thread işləyir, amma lazımi resursa heç vaxt çata bilmir (yüksək prioritetli thread-lər həmişə qabağa keçir).

```java
// Yüksək prioritetli threadlər həmişə lock alır, aşağı prioritetli gözləyir
public class StarvationDemo {
    private static final Object LOCK = new Object();

    public static void main(String[] args) {
        // Aşağı prioritetli thread — starve olur
        Thread lowPriority = new Thread(() -> {
            System.out.println("Aşağı prioritet başladı...");
            synchronized (LOCK) {
                System.out.println("Aşağı prioritet işlədi!"); // Çox gec gəlir
            }
        });
        lowPriority.setPriority(Thread.MIN_PRIORITY);

        // Çox sayda yüksək prioritetli thread
        for (int i = 0; i < 10; i++) {
            Thread highPriority = new Thread(() -> {
                synchronized (LOCK) {
                    System.out.println("Yüksək prioritet işlədi");
                    try { Thread.sleep(100); } catch (InterruptedException e) {}
                }
            });
            highPriority.setPriority(Thread.MAX_PRIORITY);
            highPriority.start();
        }

        lowPriority.start();
        // Aşağı prioritetli thread çox gec işləyə bilər (starvation)
    }
}
```

**Həll:** `ReentrantLock(true)` — fair mode, FIFO sırası ilə lock verir.

```java
ReentrantLock fairLock = new ReentrantLock(true); // fair=true
// İndi gözləyən thread-lər FIFO sırası ilə lock alır — starvation yoxdur
```

---

## wait() / notify() / notifyAll()

```java
// Klassik Producer-Consumer wait/notify ilə
public class ProducerConsumerWaitNotify {
    private final Queue<Integer> buffer = new LinkedList<>();
    private final int CAPACITY = 5;

    public synchronized void produce(int item) throws InterruptedException {
        while (buffer.size() == CAPACITY) {
            System.out.println("Bufer dolu, istehsalçı gözləyir...");
            wait(); // Lock buraxır + gözləyir
        }
        buffer.add(item);
        System.out.println("İstehsal edildi: " + item);
        notifyAll(); // Gözləyən bütün thread-ləri oyat
    }

    public synchronized int consume() throws InterruptedException {
        while (buffer.isEmpty()) {
            System.out.println("Bufer boş, istehlakçı gözləyir...");
            wait(); // Lock buraxır + gözləyir
        }
        int item = buffer.poll();
        System.out.println("İstehlak edildi: " + item);
        notifyAll(); // İstehsalçını oyat
        return item;
    }
}
```

**Mühüm qaydalar:**
- `wait()`, `notify()`, `notifyAll()` — yalnız `synchronized` blok içindədir
- `wait()` — lock-ı buraxır (sleep()-dən fərqli!)
- `notify()` — yalnız bir thread-i oyadır (hansını — bilinmir)
- `notifyAll()` — bütün gözləyən thread-ləri oyadır (adətən daha təhlükəsiz)
- `while` ilə yoxla, `if` ilə yox — spurious wakeup-dan qorun!

---

## İntervyu Sualları

**S: Race condition nədir?**
C: İki thread eyni paylaşılan resursu sinxronizasiya olmadan dəyişdirdikdə baş verir. Nəticə thread-lərin icra sırasından asılı olur və qeyri-deterministikdir.

**S: `synchronized` method vs `synchronized` block?**
C: Method — bütün metod body-sini lock edir, this (və ya Class) lock-ı alır. Block — yalnız müəyyən hissəni lock edir, istənilən obyekti lock olaraq istifadə etmək olar. Block performans baxımından daha yaxşıdır.

**S: Object lock vs Class lock?**
C: Object lock — `synchronized(this)` və ya `synchronized` instance method — yalnız həmin instance-a tətbiq olunur. Class lock — `synchronized(MyClass.class)` və ya `static synchronized` — bütün instance-lar paylaşır.

**S: Deadlock-un 4 şərti hansılardır?**
C: Mutual Exclusion, Hold and Wait, No Preemption, Circular Wait. Bunlardan biri aradan qaldırılsa deadlock mümkün deyil.

**S: `wait()` lock-ı buraxırmı?**
C: Bəli! `wait()` çağrıldıqda thread lock-ı buraxır və WAITING vəziyyətinə keçir. `sleep()` isə lock-ı buraxmır.

**S: `notify()` vs `notifyAll()`?**
C: `notify()` — bir thread-i oyadır (hansını JVM seçir). `notifyAll()` — bütün gözləyənləri oyadır. Çoxlu şərt varsa `notifyAll()` daha təhlükəsizdir, çünki `notify()` yanlış thread-i oyada bilər.

**S: `while` ilə wait() niyə istifadə edirik?**
C: Spurious wakeup (səbəbsiz oyanma) ola bilər. `while` ilə şərti yenidən yoxlayırıq. `if` istifadəsə, thread şərt doğru olmadığı halda da davam edər.

**S: Livelock vs Deadlock?**
C: Deadlock — thread-lər dayanıb gözləyir. Livelock — thread-lər işləyir amma irəliləmir (bir-birinə yol verməyə çalışır).

**S: Starvation-dan necə qorunmaq olar?**
C: `ReentrantLock(true)` — fair mode FIFO sırası ilə lock verir. Həmçinin priority-based scheduling-dən uzaq duraq.
