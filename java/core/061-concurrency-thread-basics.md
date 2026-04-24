# 061 — Concurrency: Thread Basics
**Səviyyə:** İrəli


## Mündəricat
1. [Process vs Thread](#process-vs-thread)
2. [Thread Lifecycle](#thread-lifecycle)
3. [Thread Yaratma Üsulları](#thread-yaratma-usullari)
4. [start() vs run()](#start-vs-run)
5. [Daemon Threads](#daemon-threads)
6. [Thread Priority](#thread-priority)
7. [join(), sleep(), interrupt()](#join-sleep-interrupt)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Process vs Thread

**Process** — öz yaddaş sahəsi olan müstəqil proqram. Hər process ayrı JVM instance-ı kimi düşünülebilir.

**Thread** — process daxilindəki icra vahidi. Eyni process daxilindəki threadlər yaddaşı (heap) paylaşır, lakin hər birinin öz stack-i var.

```
Process (JVM)
├── Heap (paylaşılan)
├── Thread-1 (öz stack-i)
├── Thread-2 (öz stack-i)
└── Thread-3 (öz stack-i)
```

**Əsas fərqlər:**

| Xüsusiyyət       | Process               | Thread                  |
|------------------|-----------------------|-------------------------|
| Yaddaş           | Ayrı (izolasiya)      | Paylaşılan (heap)       |
| Yaratma xərci    | Yüksək                | Aşağı                   |
| Kommunikasiya    | IPC (socket, pipe...) | Paylaşılan dəyişənlər   |
| Çöküş təsiri     | Digər process-ə yox   | Bütün thread-lərə ola bilər |

---

## Thread Lifecycle

Java-da bir thread-in 6 vəziyyəti var (`Thread.State` enum-u):

```
NEW → RUNNABLE → (BLOCKED | WAITING | TIMED_WAITING) → TERMINATED
```

```java
// Thread vəziyyətlərini izləmək üçün nümunə
public class ThreadLifecycleDemo {
    public static void main(String[] args) throws InterruptedException {
        Object lock = new Object();

        Thread thread = new Thread(() -> {
            try {
                synchronized (lock) {
                    // WAITING vəziyyətinə keçir
                    lock.wait();
                }
                // TIMED_WAITING
                Thread.sleep(1000);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        });

        System.out.println("Yaradıldıqdan sonra: " + thread.getState()); // NEW

        thread.start();
        Thread.sleep(100); // Ana thread gözləyir

        System.out.println("start()-dan sonra: " + thread.getState()); // WAITING

        synchronized (lock) {
            lock.notify(); // Thread-i oyat
        }

        Thread.sleep(100);
        System.out.println("notify()-dan sonra: " + thread.getState()); // TIMED_WAITING

        thread.join();
        System.out.println("Bitdikdən sonra: " + thread.getState()); // TERMINATED
    }
}
```

**Vəziyyətlərin izahı:**

- **NEW** — Thread yaradılıb, amma hələ `start()` çağırılmayıb
- **RUNNABLE** — Thread işləyir və ya işləməyə hazırdır (OS scheduler qərar verir)
- **BLOCKED** — Synchronized bloka girməyə çalışır, lakin lock başqasındadır
- **WAITING** — `wait()`, `join()` və ya `LockSupport.park()` çağırıldıqda — qeyri-müəyyən müddət gözləyir
- **TIMED_WAITING** — `sleep(ms)`, `wait(ms)`, `join(ms)` — müəyyən müddət gözləyir
- **TERMINATED** — Thread işini bitirib

---

## Thread Yaratma Üsulları

### 1. `extends Thread`

```java
// YANLIŞ praktika — yalnız run() override etmək üçün Thread-i extend etmək
// "is-a Thread" münasibəti yaranır, bu isə adətən istənilən deyil
class MyThread extends Thread {
    @Override
    public void run() {
        System.out.println("Thread işləyir: " + getName());
    }
}

// İstifadəsi
MyThread t = new MyThread();
t.start();
```

### 2. `implements Runnable` — Tövsiyə olunan

```java
// DOĞRU praktika — tapşırığı thread-dən ayırır
class MyTask implements Runnable {
    private final String taskName;

    MyTask(String taskName) {
        this.taskName = taskName;
    }

    @Override
    public void run() {
        // Burada biznes məntiqi
        System.out.println(taskName + " icra edilir: " + Thread.currentThread().getName());
    }
}

// İstifadəsi
Thread thread = new Thread(new MyTask("Tapşırıq-1"));
thread.start();

// Lambda ilə (Java 8+)
Thread lambdaThread = new Thread(() -> {
    System.out.println("Lambda tapşırığı: " + Thread.currentThread().getName());
});
lambdaThread.start();
```

### 3. `implements Callable<V>` — Nəticə qaytaran

```java
import java.util.concurrent.*;

// Nəticə qaytarır və exception ata bilir
class CalculationTask implements Callable<Integer> {
    private final int number;

    CalculationTask(int number) {
        this.number = number;
    }

    @Override
    public Integer call() throws Exception {
        // Hesablama aparılır
        Thread.sleep(1000); // IO əməliyyatı simulyasiyası
        return number * number; // Kvadratını qaytarır
    }
}

// İstifadəsi — ExecutorService ilə birlikdə
ExecutorService executor = Executors.newSingleThreadExecutor();
Future<Integer> future = executor.submit(new CalculationTask(5));

try {
    Integer result = future.get(); // Blok edici — nəticəni gözləyir
    System.out.println("Nəticə: " + result); // 25
} catch (ExecutionException e) {
    System.err.println("Tapşırıq xəta verdi: " + e.getCause());
} finally {
    executor.shutdown();
}
```

**Müqayisə:**

| Xüsusiyyət       | extends Thread | implements Runnable | implements Callable |
|------------------|----------------|---------------------|---------------------|
| Nəticə qaytarır  | Xeyr           | Xeyr                | Bəli (`Future<V>`)  |
| Exception ata bilir | Yalnız unchecked | Yalnız unchecked | Checked də ata bilər |
| Çeviklik         | Az (single inherit) | Yüksək          | Yüksək              |
| Tövsiyə          | Xeyr           | Bəli                | Bəli (nəticə lazımdırsa) |

---

## start() vs run()

Bu ən çox verilən müsahibə suallarından biridir!

```java
// YANLIŞ — run() birbaşa çağırılır, yeni thread yaranmır!
class WrongExample {
    public static void main(String[] args) {
        Thread t = new Thread(() -> {
            // Bu main thread-də işləyəcək, yeni thread-də DEYİL!
            System.out.println("Cari thread: " + Thread.currentThread().getName());
        });

        t.run();  // ← YANLIŞ! main thread-də çağırılır
        // Çıxış: "Cari thread: main"
    }
}

// DOĞRU — start() yeni thread yaradır
class CorrectExample {
    public static void main(String[] args) {
        Thread t = new Thread(() -> {
            System.out.println("Cari thread: " + Thread.currentThread().getName());
        });

        t.start();  // ← DOĞRU! JVM yeni thread yaradır
        // Çıxış: "Cari thread: Thread-0"
    }
}
```

**Mühüm:** Eyni thread üzərində `start()` ikinci dəfə çağırılsa `IllegalThreadStateException` atılır:

```java
Thread t = new Thread(() -> System.out.println("İşləyir"));
t.start();
t.start(); // IllegalThreadStateException! Thread artıq başlayıb
```

---

## Daemon Threads

Daemon thread-lər arxa fon xidmətləri üçündür. JVM bütün non-daemon threadlər bitdikdə, daemon thread-lərin işini gözləmədən bağlanır.

```java
public class DaemonThreadDemo {
    public static void main(String[] args) throws InterruptedException {
        // Non-daemon (default)
        Thread userThread = new Thread(() -> {
            System.out.println("User thread başladı");
            try {
                Thread.sleep(5000);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
            System.out.println("User thread bitdi"); // Bu çap olunacaq
        });

        // Daemon thread
        Thread daemonThread = new Thread(() -> {
            while (true) {
                System.out.println("Daemon işləyir...");
                try {
                    Thread.sleep(500);
                } catch (InterruptedException e) {
                    break;
                }
            }
            System.out.println("Daemon bitdi"); // Bu ÇAP OLUNMAYA BİLƏR
        });

        // start()-dan ƏVVƏL daemon olaraq işarələnməlidir
        daemonThread.setDaemon(true);

        userThread.start();
        daemonThread.start();

        userThread.join(); // User thread bitənə gözlə
        // User thread bitdi → JVM daemon-u dayandırır
    }
}
```

**İstifadə halları:**
- Garbage Collection (JVM daemon thread-i)
- Log yazma
- Heartbeat check
- Cache invalidation

```java
// YANLIŞ — start()-dan sonra daemon etməyə çalışmaq
Thread t = new Thread(task);
t.start();
t.setDaemon(true); // IllegalThreadStateException!

// DOĞRU
Thread t = new Thread(task);
t.setDaemon(true); // start()-dan əvvəl
t.start();
```

---

## Thread Priority

Java 1-10 arası prioritet dəstəkləyir (default: 5).

```java
public class ThreadPriorityDemo {
    public static void main(String[] args) {
        Thread lowPriority = new Thread(() -> {
            for (int i = 0; i < 5; i++) {
                System.out.println("Aşağı prioritet: " + i);
                Thread.yield(); // CPU-nu digər thread-lərə ver
            }
        });

        Thread highPriority = new Thread(() -> {
            for (int i = 0; i < 5; i++) {
                System.out.println("Yüksək prioritet: " + i);
                Thread.yield();
            }
        });

        lowPriority.setPriority(Thread.MIN_PRIORITY);  // 1
        highPriority.setPriority(Thread.MAX_PRIORITY); // 10

        lowPriority.start();
        highPriority.start();
    }
}
```

**Diqqət:** Thread prioriteti **tövsiyədir**, zəmanət deyil. OS scheduler-i prioritetlərə əməl etməyə bilər. Kritik iş məntiqi üçün prioritetə güvənmə!

---

## join(), sleep(), interrupt()

### join() — Thread-in bitməsini gözlə

```java
public class JoinDemo {
    public static void main(String[] args) throws InterruptedException {
        Thread dataLoader = new Thread(() -> {
            System.out.println("Məlumat yüklənir...");
            try {
                Thread.sleep(2000); // Yükləmə simulyasiyası
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
                return;
            }
            System.out.println("Məlumat yükləndi!");
        });

        dataLoader.start();

        // dataLoader bitənə qədər gözlə
        dataLoader.join();
        // Alternativ: dataLoader.join(3000); — maksimum 3 saniyə gözlə

        System.out.println("İndi məlumatı işlədə bilərik"); // dataLoader bitdikdən sonra
    }
}
```

### sleep() — Müəyyən müddət gözlə

```java
public class SleepDemo {
    public static void main(String[] args) {
        System.out.println("Başlayır");

        try {
            Thread.sleep(2000); // 2 saniyə gözlə (lock buraxmır!)
        } catch (InterruptedException e) {
            // sleep() interrupt edildikdə interrupted flag-i təmizlənir
            // Onu yenidən set etmək lazımdır
            Thread.currentThread().interrupt();
            System.out.println("Yuxu pozuldu!");
            return;
        }

        System.out.println("2 saniyə sonra");
    }
}
```

**Mühüm fərq:** `sleep()` lock-ı buraxmır, `wait()` isə buraxır!

### interrupt() — Thread-i dayandırmaq üçün siqnal

```java
public class InterruptDemo {
    public static void main(String[] args) throws InterruptedException {
        Thread worker = new Thread(() -> {
            // İnterrupt-u düzgün idarə etmək
            while (!Thread.currentThread().isInterrupted()) {
                System.out.println("İşləyirəm...");
                try {
                    Thread.sleep(500);
                } catch (InterruptedException e) {
                    // sleep() interrupt-u "udur" — flag-i yenidən set et!
                    Thread.currentThread().interrupt();
                    System.out.println("Dayandırıldım!");
                    break; // Döngüdən çıx
                }
            }
            System.out.println("Worker bitdi");
        });

        worker.start();

        Thread.sleep(2000); // 2 saniyə işləsin
        worker.interrupt();  // Dayandırma siqnalı göndər

        worker.join();
        System.out.println("Hər şey bitdi");
    }
}
```

**YANLIŞ interrupt idarəsi:**

```java
// YANLIŞ — interrupt-u udur, işarəni itiririk
try {
    Thread.sleep(1000);
} catch (InterruptedException e) {
    // Heç nə etmirik — interrupt siqnalı itdi!
    // e.printStackTrace() — bu da kifayət deyil
}

// DOĞRU — interrupt-u yenidən set edirik
try {
    Thread.sleep(1000);
} catch (InterruptedException e) {
    Thread.currentThread().interrupt(); // Siqnalı bərpa et
    return; // və ya döngüdən çıx
}
```

---

## Kompleks Nümunə: Producer-Consumer

```java
import java.util.concurrent.*;

public class ProducerConsumerDemo {
    private static final BlockingQueue<Integer> queue = new LinkedBlockingQueue<>(10);

    public static void main(String[] args) throws InterruptedException {
        Thread producer = new Thread(() -> {
            for (int i = 1; i <= 20; i++) {
                try {
                    queue.put(i); // Dolu olarsa blok edir
                    System.out.println("İstehsal edildi: " + i);
                    Thread.sleep(100);
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    break;
                }
            }
        }, "İstehsalçı");

        Thread consumer = new Thread(() -> {
            while (true) {
                try {
                    Integer item = queue.poll(2, TimeUnit.SECONDS); // 2 saniyə gözlə
                    if (item == null) {
                        System.out.println("Timeout — istehlakçı bitir");
                        break;
                    }
                    System.out.println("İstehlak edildi: " + item);
                    Thread.sleep(200);
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    break;
                }
            }
        }, "İstehlakçı");

        producer.start();
        consumer.start();

        producer.join();
        consumer.join();
    }
}
```

---

## İntervyu Sualları

**S: `start()` ilə `run()` arasındakı fərq nədir?**
C: `start()` yeni OS thread-i yaradır və JVM tərəfindən `run()` həmin thread-də çağırılır. `run()` birbaşa çağırılarsa, yeni thread yaranmır — metod cari thread-də sadə bir metod kimi icra olunur.

**S: Thread-in 6 vəziyyəti hansılardır?**
C: NEW, RUNNABLE, BLOCKED, WAITING, TIMED_WAITING, TERMINATED.

**S: BLOCKED ilə WAITING arasındakı fərq nədir?**
C: BLOCKED — synchronized bloka girməyə çalışır, lock başqasındadır. WAITING — `wait()` və ya `join()` çağırılıb, başqa thread-in siqnalını gözləyir.

**S: Daemon thread nədir?**
C: Arxa fon thread-i. Bütün non-daemon thread-lər bitdikdə JVM daemon thread-ləri zorla bitirir. GC daemon thread-ə nümunədir.

**S: `sleep()` lock-ı buraxırmı?**
C: Xeyr! `sleep()` lock-ı buraxmır. `wait()` isə synchronized blok daxilində lock-ı buraxır.

**S: `InterruptedException` tutduqda nə etmək lazımdır?**
C: `Thread.currentThread().interrupt()` çağırıb interrupted flag-i bərpa etmək lazımdır, çünki exception atıldıqda bu flag avtomatik təmizlənir.

**S: `Runnable` vs `Callable` fərqi nədir?**
C: `Runnable.run()` nəticə qaytarmır (`void`) və checked exception ata bilmir. `Callable.call()` nəticə qaytarır (`V`) və checked exception ata bilər.

**S: Eyni thread üzərində `start()` iki dəfə çağırıla bilərmi?**
C: Xeyr. `IllegalThreadStateException` atılır. Thread bir dəfə TERMINATED vəziyyətinə keçdikdən sonra yenidən başladıla bilməz.

**S: Thread priority zəmanət verirmi?**
C: Xeyr. Yalnız OS scheduler-ə tövsiyədir. Platforma asılı davranışdır.

**S: `join(0)` nə edir?**
C: `join(0)` — `join()` ilə eynidir, qeyri-müəyyən müddət gözləyir (0 ms timeout deməkdir ki, heç vaxt timeout olmasın).
