# Multithreading ve Concurrency — Java vs PHP

## Giris

Multithreading ve concurrency (paralel icra) Java ve PHP arasindaki en boyuk arxitektura ferqlerindendir. Java en bashdan multithread proqramlar ucun dizayn olunub ve zengin concurrency alətleri teqdim edir. PHP ise **single-threaded, shared-nothing** arxitektura uzerinde qurulub — her sorgu ayri prosesdir ve yaddash paylashilmir.

Bu ferq her iki dilin felsefesi ve istifade sahesinden qaynaqlanir.

---

## Java-da istifadesi

### Thread yaratma

Java-da thread yaratmağın iki esas yolu var:

```java
// 1-ci yol: Thread sinifini extend etmek
public class MyThread extends Thread {
    @Override
    public void run() {
        for (int i = 0; i < 5; i++) {
            System.out.println(Thread.currentThread().getName() + ": " + i);
            try {
                Thread.sleep(100);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        }
    }
}

// 2-ci yol: Runnable interfeysini implement etmek (daha yaxshi)
public class MyRunnable implements Runnable {
    @Override
    public void run() {
        System.out.println("Runnable isleyir: " + Thread.currentThread().getName());
    }
}

// Istifadesi
public class Main {
    public static void main(String[] args) throws InterruptedException {
        // Thread extend
        MyThread t1 = new MyThread();
        t1.setName("Thread-A");
        t1.start();

        // Runnable implement
        Thread t2 = new Thread(new MyRunnable());
        t2.start();

        // Lambda ile (en qisa yol)
        Thread t3 = new Thread(() -> {
            System.out.println("Lambda thread: " + Thread.currentThread().getName());
        });
        t3.start();

        // Butun thread-lerin bitmesini gozle
        t1.join();
        t2.join();
        t3.join();
    }
}
```

### ExecutorService — Thread Pool

Thread-leri manual idare etmek evezine, `ExecutorService` thread pool mexanizmi teqdim edir:

```java
import java.util.concurrent.*;
import java.util.List;

public class ExecutorServiceExample {

    public static void main(String[] args) throws Exception {

        // 4 thread-den ibaret pool yaradirig
        ExecutorService executor = Executors.newFixedThreadPool(4);

        // Runnable — neticesi olmayan tapshiriq
        executor.submit(() -> {
            System.out.println("Tapshiriq 1: " + Thread.currentThread().getName());
        });

        // Callable — neticesi olan tapshiriq
        Future<String> future = executor.submit(() -> {
            Thread.sleep(1000);
            return "Netice hazirdir!";
        });

        // Neticeni gozle
        String result = future.get(); // blok edir — neticeni gozleyir
        System.out.println(result);

        // Bir nece tapshirigi paralel icra et
        List<Callable<Integer>> tasks = List.of(
            () -> { Thread.sleep(2000); return 1; },
            () -> { Thread.sleep(1000); return 2; },
            () -> { Thread.sleep(3000); return 3; }
        );

        // Hamisi bitene qeder gozle
        List<Future<Integer>> results = executor.invokeAll(tasks);
        for (Future<Integer> f : results) {
            System.out.println("Netice: " + f.get());
        }

        // Pool-u bagla
        executor.shutdown();
        executor.awaitTermination(10, TimeUnit.SECONDS);
    }
}
```

### CompletableFuture — Asinxron proqramlashdirma

Java 8 ile gelen `CompletableFuture` asinxron emeliyyatlari zencirleme imkani verir:

```java
import java.util.concurrent.CompletableFuture;

public class CompletableFutureExample {

    public static void main(String[] args) {

        // Asinxron emeliyyat bashlat
        CompletableFuture<String> userFuture = CompletableFuture.supplyAsync(() -> {
            // Simulate API call
            sleep(1000);
            return "Orxan";
        });

        // Zencirle: istifadeci adini alandan sonra sifarishleri yukle
        CompletableFuture<String> ordersFuture = userFuture
            .thenApplyAsync(userName -> {
                sleep(500);
                return userName + "-nin sifarishleri: [Kitab, Telefon]";
            });

        // Neticeni cap et (bloklamadan callback ile)
        ordersFuture.thenAccept(result -> {
            System.out.println(result);
        });

        // Bir nece asinxron emeliyyati paralel bashlat
        CompletableFuture<String> productsFuture = CompletableFuture.supplyAsync(() -> {
            sleep(800);
            return "Mehsullar yuklendi";
        });

        CompletableFuture<String> reviewsFuture = CompletableFuture.supplyAsync(() -> {
            sleep(600);
            return "Reyler yuklendi";
        });

        // Hamisi bitende...
        CompletableFuture.allOf(productsFuture, reviewsFuture)
            .thenRun(() -> {
                System.out.println("Butun melumatlar hazirdir!");
            });

        // Herhansi biri bitende...
        CompletableFuture.anyOf(productsFuture, reviewsFuture)
            .thenAccept(first -> {
                System.out.println("Ilk neticə: " + first);
            });

        // Exception handling
        CompletableFuture.supplyAsync(() -> {
            if (true) throw new RuntimeException("Xeta bash verdi!");
            return "OK";
        })
        .exceptionally(ex -> {
            System.err.println("Xeta: " + ex.getMessage());
            return "Default dəyər";
        })
        .thenAccept(System.out::println);

        // Main thread-in bitmemesi ucun gozle
        sleep(3000);
    }

    private static void sleep(long ms) {
        try { Thread.sleep(ms); } catch (InterruptedException e) { Thread.currentThread().interrupt(); }
    }
}
```

### synchronized — Thread Safety

Bir nece thread eyni melumatla ishleyende, data race problemi yarana biler. `synchronized` bunu hel edir:

```java
public class BankAccount {
    private double balance;

    // synchronized metod — eyni anda yalniz 1 thread daxil ola biler
    public synchronized void deposit(double amount) {
        double current = balance;
        // Simulate ishleme vaxti
        try { Thread.sleep(10); } catch (InterruptedException e) {}
        balance = current + amount;
    }

    public synchronized void withdraw(double amount) {
        if (balance >= amount) {
            balance -= amount;
        }
    }

    public synchronized double getBalance() {
        return balance;
    }
}

// synchronized blok — daha ince kontrol
public class Counter {
    private int count = 0;
    private final Object lock = new Object();

    public void increment() {
        synchronized (lock) {
            count++;
        }
    }

    public int getCount() {
        synchronized (lock) {
            return count;
        }
    }
}
```

### volatile keyword

`volatile` deyishenin her zaman esas yaddashdan (main memory) oxunmasini temin edir — thread ozunun keshinden deyil:

```java
public class VolatileExample {

    // volatile olmasa, thread-ler ozu keshlediklerini oxuya biler
    private volatile boolean running = true;

    public void start() {
        new Thread(() -> {
            while (running) {
                // Ish gor...
            }
            System.out.println("Thread dayandi");
        }).start();
    }

    public void stop() {
        // Bashqa thread-den caghirilir — volatile sayesinde
        // diger thread bu deyishikliyi derhal gorur
        running = false;
    }
}
```

### Java Concurrency utilites — qisa icmal

```java
import java.util.concurrent.*;
import java.util.concurrent.atomic.*;
import java.util.concurrent.locks.*;

// AtomicInteger — synchronized-siz thread-safe counter
AtomicInteger counter = new AtomicInteger(0);
counter.incrementAndGet(); // thread-safe

// ReentrantLock — synchronized-den daha çevik kilit
ReentrantLock lock = new ReentrantLock();
lock.lock();
try {
    // kritik bolme
} finally {
    lock.unlock();
}

// ConcurrentHashMap — thread-safe HashMap
ConcurrentHashMap<String, Integer> map = new ConcurrentHashMap<>();
map.put("key", 1);

// CountDownLatch — mueyyen sayda emeliyyatin bitmesini gozle
CountDownLatch latch = new CountDownLatch(3);
// her thread: latch.countDown();
latch.await(); // 3 defe countDown caghirilana qeder gozle

// BlockingQueue — producer-consumer pattern
BlockingQueue<String> queue = new LinkedBlockingQueue<>();
queue.put("mesaj");        // queue dolu olsa gozleyir
String msg = queue.take(); // queue bosh olsa gozleyir
```

### Virtual Threads (Java 21+) — Project Loom

Java 21 ile virtual thread-ler gəldi — milyonlarla yungul thread yaratmaq mumkundur:

```java
// Klassik platform thread — OS seviyyesinde, agir
Thread platformThread = new Thread(() -> System.out.println("Platform thread"));

// Virtual thread — JVM seviyyesinde, yungul
Thread virtualThread = Thread.ofVirtual().start(() -> {
    System.out.println("Virtual thread: " + Thread.currentThread());
});

// Milyonlarla virtual thread yaratmaq mumkundur
try (var executor = Executors.newVirtualThreadPerTaskExecutor()) {
    for (int i = 0; i < 100_000; i++) {
        executor.submit(() -> {
            Thread.sleep(1000);
            return "done";
        });
    }
}
```

---

## PHP-de istifadesi

### PHP niye single-threaded-dir?

PHP en bashdan web sorğulari ucun dizayn olunub. Her HTTP sorgusu ayri bir proses (ve ya thread) terefinden idare olunur ve prosesler arasinda yaddash **paylashilmir** (shared-nothing architecture).

```
Istifadeci sorgusu → Web server (Apache/Nginx)
                           ↓
                    PHP prosesi bashlayir
                           ↓
                    Script icra olunur
                           ↓
                    Cavab gonderilir
                           ↓
                    Proses sonlanir (yaddash temizlenir)
```

Bu arxitekturanin ustuunlukleri:
- **Sadelik** — data race, deadlock, race condition kimi problemler yoxdur
- **Izolyasiya** — bir sorgunun xetasi digerlerine tesir etmir
- **Olceklene bilme** — yeni server/proses elave etmekle yatay olcekleme asan
- **Yaddash temizliyi** — her sorgudan sonra butun yaddash azad olunur

### PHP Fibers (PHP 8.1)

Fiber-ler PHP-ye cooperative multitasking imkani verir. Bu, esleshdirme (concurrency) dir, lakin parallellik (parallelism) deyil:

```php
<?php

// Fiber yaratma
$fiber = new Fiber(function (): void {
    $value = Fiber::suspend('birinci dayanma');
    echo "Fiber davam edir, alinan deyer: $value\n";

    Fiber::suspend('ikinci dayanma');
    echo "Fiber yeniden davam edir\n";
});

// Fiber-i bashlat
$result = $fiber->start();
echo "Birinci neticə: $result\n";  // "birinci dayanma"

// Fiber-e deyer gonder ve davam etdir
$result = $fiber->resume('salam');
echo "Ikinci neticə: $result\n";   // "ikinci dayanma"

// Son defe davam etdir
$fiber->resume();
echo "Fiber bitdi\n";
```

Fiber-ler asinxron frameworklerin (ReactPHP, Amp) temelini teshkil edir:

```php
<?php

// Fiber ile sadə asinxron simulator
function asyncTask(string $name, int $ms): Fiber
{
    return new Fiber(function () use ($name, $ms) {
        echo "$name bashladi\n";
        // Real dunya-da burada I/O gozleme olardi
        Fiber::suspend();
        echo "$name tamamlandi ($ms ms)\n";
    });
}

$tasks = [
    asyncTask('Database sorgusu', 200),
    asyncTask('API caghirishi', 500),
    asyncTask('Fayl oxuma', 100),
];

// Hamisi bashlasin
foreach ($tasks as $task) {
    $task->start();
}

// Hamisi davam etsin
foreach ($tasks as $task) {
    if (!$task->isTerminated()) {
        $task->resume();
    }
}
```

### pcntl — Process Control

PHP-de eyni anda bir nece ish gormek ucun proses fork etmek mumkundur (yalniz CLI):

```php
<?php

// pcntl yalniz Unix/Linux-da ve CLI rejiminde isleyir
// Web server muhitinde istifade olunmamalidir!

$pid = pcntl_fork();

if ($pid === -1) {
    die("Fork ugursuz oldu!\n");
} elseif ($pid === 0) {
    // Ushaq proses
    echo "Ushaq proses (PID: " . getmypid() . ")\n";
    sleep(2);
    echo "Ushaq proses bitdi\n";
    exit(0);
} else {
    // Ana proses
    echo "Ana proses (PID: " . getmypid() . "), ushaq PID: $pid\n";
    pcntl_waitpid($pid, $status); // Ushaq prosesi gozle
    echo "Ana proses: ushaq bitdi, status: $status\n";
}

// Bir nece proses ile paralel ishleme
$childPids = [];
$tasks = ['Database import', 'Email gonderme', 'Hesabat yaratma'];

foreach ($tasks as $task) {
    $pid = pcntl_fork();

    if ($pid === 0) {
        // Her ushaq proses oz tapshirigini icra edir
        echo "[$task] bashladi (PID: " . getmypid() . ")\n";
        sleep(rand(1, 3));
        echo "[$task] tamamlandi\n";
        exit(0);
    }

    $childPids[] = $pid;
}

// Ana proses butun ushaqlari gozleyir
foreach ($childPids as $pid) {
    pcntl_waitpid($pid, $status);
}
echo "Butun tapshiriqlar tamamlandi!\n";
```

### ReactPHP — Event-driven asinxron proqramlashdirma

ReactPHP Node.js-e oxshar event-loop modeli teqdim edir:

```php
<?php

require 'vendor/autoload.php';

use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\Promise;

// Event loop
$browser = new Browser();

// Paralel HTTP sorgulari
$promise1 = $browser->get('https://api.example.com/users');
$promise2 = $browser->get('https://api.example.com/products');
$promise3 = $browser->get('https://api.example.com/orders');

// Hamisi bitende cavabi al
React\Promise\all([$promise1, $promise2, $promise3])
    ->then(function (array $responses) {
        foreach ($responses as $response) {
            echo "Status: " . $response->getStatusCode() . "\n";
        }
    })
    ->catch(function (\Throwable $e) {
        echo "Xeta: " . $e->getMessage() . "\n";
    });

// Asinxron TCP server
$server = new React\Socket\SocketServer('127.0.0.1:8080');
$server->on('connection', function (React\Socket\ConnectionInterface $connection) {
    $connection->write("Salam!\n");
    $connection->on('data', function (string $data) use ($connection) {
        $connection->write("Siz yazdiz: $data");
    });
});

echo "Server 8080 portunda isleyir...\n";
```

### Swoole — Coroutine destekli PHP extension

Swoole PHP-ni long-running prosese cevirir (Java-ya oxshar):

```php
<?php

// Swoole ile HTTP server — her sorgu asinxrondir
$server = new Swoole\Http\Server("0.0.0.0", 9501);

$server->on("request", function ($request, $response) {

    // Coroutine ile paralel sorgular
    $results = [];

    Swoole\Coroutine\run(function () use (&$results) {
        // Bu iki sorgu PARALEL icra olunur
        $wg = new Swoole\Coroutine\WaitGroup();

        $wg->add(1);
        go(function () use (&$results, $wg) {
            // Asinxron database sorgusu
            $db = new Swoole\Coroutine\MySQL();
            $db->connect(['host' => '127.0.0.1', 'database' => 'test']);
            $results['users'] = $db->query("SELECT * FROM users LIMIT 10");
            $wg->done();
        });

        $wg->add(1);
        go(function () use (&$results, $wg) {
            // Asinxron HTTP sorgusu
            $client = new Swoole\Coroutine\Http\Client('api.example.com', 443, true);
            $client->get('/products');
            $results['products'] = $client->body;
            $wg->done();
        });

        $wg->wait();
    });

    $response->end(json_encode($results));
});

$server->start();
```

---

## Esas ferqler

| Xususiyyet | Java | PHP |
|---|---|---|
| **Arxitektura** | Multi-threaded, long-running proses | Single-threaded, shared-nothing, request-based |
| **Thread desteyi** | Native, dilin ozunde | Yoxdur (pcntl ile proses fork) |
| **Thread pool** | ExecutorService, ForkJoinPool | Yoxdur (Swoole ile mumkun) |
| **Asinxron** | CompletableFuture, Virtual Threads | Fibers (8.1), ReactPHP, Swoole |
| **Yaddash paylashma** | Thread-ler yaddashi paylashir | Prosesler yaddashi paylashmir |
| **synchronized** | Var — dilin acari sozu | Yoxdur (ehtiyac yoxdur) |
| **volatile** | Var | Yoxdur |
| **Data race riski** | Var — diqqetli olmaq lazimdir | Yoxdur (shared-nothing) |
| **Deadlock riski** | Var | Yoxdur (tekhel proses) |
| **Olcekleme** | Vertical + horizontal | Horizontal (proses/server elave et) |
| **Coroutine/Fiber** | Virtual Threads (Java 21+) | Fibers (PHP 8.1+) |

---

## Niye bele ferqler var?

### Esas arxitektura ferqi

Bu ferqi anlamaq ucun her iki dilin tarixine baxmaq lazimdir:

**Java (1995)**: Java en bashdan desktop ve server tetbiqleri ucun dizayn olunub. Bir Java proqrami bashladiqda, JVM prosesi isleyir ve **saatlarla, gunlerle, heftelerle** ishleye biler. Muxtelif istifadecilerin sorqulari eyni prosesin icinde muxtelif thread-lerde icra olunur. Buna gore yaddash paylashmasi, thread safety ve concurrency mexanizmleri vacibdir.

```
Java Server:
┌──────────────────────────────┐
│         JVM Prosesi          │
│  ┌─────┐ ┌─────┐ ┌─────┐   │
│  │ T-1 │ │ T-2 │ │ T-3 │   │  ← Thread-ler yaddashi paylashir
│  └─────┘ └─────┘ └─────┘   │
│  ┌──────────────────────┐   │
│  │   Ortaq yaddash      │   │  ← Butun thread-ler bu yaddasha catir
│  └──────────────────────┘   │
└──────────────────────────────┘
```

**PHP (1995)**: PHP veb sehifeler yaratmaq ucun dizayn olunub. Her HTTP sorgusu ayri PHP prosesi/thread-inde isleyir. Sorgu bitende butun yaddash silinir. Prosesler bir-birinin yaddashina catmir.

```
PHP (Apache/PHP-FPM):
┌──────────┐  ┌──────────┐  ┌──────────┐
│ Proses-1 │  │ Proses-2 │  │ Proses-3 │
│ Sorgu: A │  │ Sorgu: B │  │ Sorgu: C │
│ [Yaddash]│  │ [Yaddash]│  │ [Yaddash]│  ← Her prosesin oz yaddashi
└──────────┘  └──────────┘  └──────────┘
     ↓              ↓             ↓
   Cavab          Cavab         Cavab
     ↓              ↓             ↓
   Silinir        Silinir       Silinir    ← Yaddash azad olunur
```

### Niye PHP shared-nothing secdi?

1. **Sadelik**: Veb developer ucun thread safety, mutex, deadlock kimi anlayishlarla meşğul olmaq lazim deyil
2. **Etibarliliq**: Bir sorgunun xetasi digerlerine tesir etmir — proses crush etse bele yalniz 1 istifadecini tesir edir
3. **Olcekleme**: Yeni server elave etmek kifayetdir, melumat paylashma problemi yoxdur (database ortaq noqte olaraq istifade olunur)

### Java ve PHP-nin modern yaxinlashmasi

Maraqli odur ki, her iki dil bir-birine yaxinlashir:
- **Java 21** Virtual Threads ile PHP-nin "yungul, cox sayda emeliyyat" modeline yaxinlashdi
- **PHP + Swoole** Java-nin "long-running proses" modeline yaxinlashdi
- **PHP Fibers** Java-nin coroutine-lerine benzer mexanizm teqdim edir

Lakin fundamental ferq qalir: Java proqramcisi MECBURDUR ki thread safety haqqinda dushunusn, PHP proqramcisi ise adeteri buna ehtiyac duymar.
