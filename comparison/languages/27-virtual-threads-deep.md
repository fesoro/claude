# Virtual Threads dərindən (Java Loom vs PHP Fibers/Swoole)

## Giriş

**Virtual Threads** (Project Loom, JEP 444 — Java 21-də stable) Java-nın 30 ildə ən böyük dəyişikliklərindən biridir. Platform thread (OS thread) ~1 MB stack tələb edir — 10K thread = ~10 GB. Virtual thread isə ~few KB başlanğıc stack-ı ilə milyonlarla yarana bilər. Fərq: virtual thread-ləri JVM özü idarə edir — "carrier" platform thread-ə mount/unmount olur.

PHP-də analoq **Fibers** (PHP 8.1-də gələn) və **Swoole coroutines**-dir. Lakin fərq var: Virtual Thread blok edən I/O-nu avtomatik unmount edir (`Socket.read()`, `Thread.sleep()` — hamısı "async-aware" oldu). PHP Fiber-də proqramçı özü `Fiber::suspend()` çağırmalıdır — və ya amphp/Swoole kimi runtime bunu etməlidir. Swoole isə `hook_flags` ilə blocking PHP funksiyalarını coroutine-aware edir.

Bu fayl göstərir: Virtual Thread dərinlik (continuation, mounting, pinning), JEP 444-453 xronologiyası, Java 24-də pinning azaldılması, Spring Boot 3.2+ integration, benchmark-lar. PHP tərəfdə: Fiber lifecycle, Swoole coroutine model, OpenSwoole, amphp v3, ReactPHP + Fibers, Laravel Octane `concurrently()`.

---

## Java-da istifadəsi

### 1) Platform vs Virtual Threads — arxitektura

```java
// Platform Thread — OS thread, ~1 MB stack
Thread platform = Thread.ofPlatform()
    .name("worker-", 1)
    .daemon(true)
    .start(() -> doWork());

// Virtual Thread — JVM-lə idarə olunan green thread, ~few KB
Thread virtual = Thread.ofVirtual()
    .name("task-", 1)
    .start(() -> doWork());

// Interface eyni — kod dəyişmir
Runnable task = () -> {
    System.out.println("Thread: " + Thread.currentThread());
    Thread.sleep(Duration.ofSeconds(1));
};
```

**Memory comparison:**

```
Platform Thread:
  Stack: ~1 MB (konfiqurasiya ilə dəyişir, -Xss ilə)
  OS thread: sistem resursu
  Context switch: OS-da (kernel trap)
  Maksimum: 4-8K thread (~4-8 GB)

Virtual Thread:
  Stack: ~200 bytes başlanğıc, grow olur (dinamik)
  Memory: heap-də yaşayır
  Context switch: JVM-də (user space)
  Maksimum: milyonlarla (~10M+ tətbiqə görə)
```

### 2) Continuation və Carrier Thread — daxili işlək

Virtual thread JVM-də **continuation** (icra konteksti snapshot) kimi saxlanılır. Blok edən əməliyyata çatdıqda:

```
1. VT #42 → sock.read() çağırır
2. JVM: I/O hazır deyil — VT-i "park" et
3. Continuation (stack frame-lər) heap-ə saxlanılır
4. Carrier thread boşalır — başqa VT götürür
5. I/O hazır olanda — VT yenidən "mount" olur (eyni və ya başqa carrier thread-də)
6. sock.read() qayıdır, kod davam edir
```

```java
// Carrier thread pool (default: ForkJoinPool, parallelism = CPU core count)
// -Djdk.virtualThreadScheduler.parallelism=8 ilə dəyişir

Thread vt = Thread.ofVirtual().start(() -> {
    System.out.println("Carrier: " + extractCarrier());  // məs, ForkJoinPool-worker-1
    Thread.sleep(Duration.ofSeconds(1));                  // unmount
    System.out.println("Carrier after: " + extractCarrier());  // fərqli ola bilər
});
```

### 3) Pinning problem — synchronized və native

Bəzi əməliyyatlar virtual thread-i carrier-ə "pin" edir — unmount etmir:

- `synchronized` blok içində I/O (Java 21-24 problemi, Java 24-də JEP 491 həll edir)
- Native method (JNI) çağırışı
- `Object.wait()` köhnə stilli kod

```java
// PIN OLUR — SLOW
public synchronized void slow() {
    httpClient.send(request);   // carrier bloklanır
}

// PIN OLMUR — FAST
private final ReentrantLock lock = new ReentrantLock();
public void fast() {
    lock.lock();
    try {
        httpClient.send(request);   // unmount ola bilər
    } finally {
        lock.unlock();
    }
}
```

Pinning aşkar etmək üçün:

```bash
java -Djdk.tracePinnedThreads=full -jar app.jar

# Output:
# Thread[#26,ForkJoinPool-1-worker-1,5,CarrierThreads]
#     java.base/java.lang.VirtualThread$VThreadContinuation.onPinned
#     at LegacyService.slow(LegacyService.java:15) <== monitors:1
```

JEP 491 (Java 24) `synchronized`-də pinning-i həll etdi — köhnə kod dəyişmədən işləyir.

### 4) Thread yaratmaq yolları — Java 21+

```java
// 1. Thread.startVirtualThread — ən qısa
Thread t = Thread.startVirtualThread(() -> task());

// 2. Thread.ofVirtual() builder
Thread t = Thread.ofVirtual()
    .name("order-processor-", 0)   // auto-increment
    .inheritInheritableThreadLocals(false)
    .unstarted(() -> task());
t.start();

// 3. Executors.newVirtualThreadPerTaskExecutor — hər task üçün ayrı VT
try (var executor = Executors.newVirtualThreadPerTaskExecutor()) {
    for (int i = 0; i < 10_000; i++) {
        executor.submit(() -> processOrder(i));
    }
}   // auto-close wait edir

// 4. ThreadFactory ilə
ThreadFactory factory = Thread.ofVirtual().factory();
ExecutorService ex = Executors.newThreadPerTaskExecutor(factory);
```

### 5) Real nümunə — HTTP server 100K sorğu

```java
import java.net.ServerSocket;
import java.net.Socket;
import java.util.concurrent.Executors;

public class VirtualThreadServer {
    public static void main(String[] args) throws Exception {
        try (var serverSocket = new ServerSocket(8080);
             var executor = Executors.newVirtualThreadPerTaskExecutor()) {

            while (true) {
                Socket client = serverSocket.accept();
                executor.submit(() -> handleClient(client));
                // Hər klient üçün VT — 100K concurrent connection OK
            }
        }
    }

    private static void handleClient(Socket client) {
        try (client;
             var in = client.getInputStream();
             var out = client.getOutputStream()) {

            var request = readRequest(in);   // bloklayır — VT unmount
            var response = processRequest(request);
            out.write(response.getBytes());
        } catch (Exception e) {
            // log
        }
    }
}
```

Klassik `newFixedThreadPool(200)` ilə müqayisə:

```
Platform threads (200):
  200 concurrent connection limiti
  Əlavə connection → queue-da gözləyir
  Maksimum throughput: ~200 req/s (əgər hər sorğu 1s-dirsə)

Virtual threads (limit yox):
  10 000+ concurrent connection OK
  Heap-də ~20 MB (10K VT × 2KB)
  Maksimum throughput: 10K+ req/s (I/O limit-ə qədər)
```

### 6) ThreadLocal caveats — milyonlarla VT

`ThreadLocal` hər thread üçün ayrı dəyər saxlayır — virtual thread-lərdə bu yaddaş şişməsi ola bilər:

```java
// Problem: 1M VT × 10 KB ThreadLocal = 10 GB!
private static final ThreadLocal<Map<String, Object>> CONTEXT =
    ThreadLocal.withInitial(HashMap::new);

public void handle() {
    CONTEXT.get().put("userId", 42);
    // VT milyonlarla olsa, memory leak
}
```

Həll: **Scoped Values** (JEP 464, Java 21 preview):

```java
// ScopedValue immutable və bounded
static final ScopedValue<Integer> USER_ID = ScopedValue.newInstance();

public void handle(Request req) {
    ScopedValue.where(USER_ID, req.userId())
        .run(() -> processRequest(req));
}

void processRequest(Request req) {
    int id = USER_ID.get();   // oxu
    // USER_ID.set() YOX — immutable
}
```

### 7) Spring Boot 3.2+ integration

```properties
# application.properties
spring.threads.virtual.enabled=true
```

Bu flag ilə:
- Tomcat request thread → Virtual Thread
- `@Async` → Virtual Thread executor
- `RestTemplate`, `WebClient` blocking calls — VT unmount edir
- Scheduled task executor → Virtual Thread

```java
@SpringBootApplication
public class App {
    public static void main(String[] args) {
        SpringApplication.run(App.class, args);
    }

    // Async-də da VT istifadə et
    @Bean
    public TaskExecutor taskExecutor() {
        return new SimpleAsyncTaskExecutor(
            Thread.ofVirtual().name("async-", 0).factory()
        );
    }
}

@RestController
public class OrderController {
    @GetMapping("/orders/{id}")
    public Order get(@PathVariable Long id) {
        // Bu method Virtual Thread-də işləyir (spring.threads.virtual.enabled=true)
        return orderService.findById(id);   // JDBC blocking — VT unmount
    }
}
```

### 8) Benchmark — Thread Pool vs Virtual Thread

I/O-bound iş yükü (HTTP + DB):

```java
// Setup: 10K sorğu, hər biri 100ms DB + 100ms HTTP call
public class Benchmark {
    public static void main(String[] args) throws Exception {
        int tasks = 10_000;

        // 1. Fixed thread pool
        long start = System.nanoTime();
        try (var ex = Executors.newFixedThreadPool(200)) {
            for (int i = 0; i < tasks; i++) {
                ex.submit(() -> doIoWork());
            }
        }
        System.out.println("Platform (pool 200): " + (System.nanoTime() - start) / 1_000_000 + "ms");

        // 2. Virtual thread per task
        start = System.nanoTime();
        try (var ex = Executors.newVirtualThreadPerTaskExecutor()) {
            for (int i = 0; i < tasks; i++) {
                ex.submit(() -> doIoWork());
            }
        }
        System.out.println("Virtual: " + (System.nanoTime() - start) / 1_000_000 + "ms");
    }

    private static void doIoWork() {
        Thread.sleep(Duration.ofMillis(200));  // DB + HTTP simulation
    }
}

// Nəticə (tipik):
// Platform (pool 200): 10_500ms
// Virtual: 210ms
```

Real təsir: VT blocking I/O-nu parallelize edir — CPU-bound iş yükündə fərq yoxdur (hətta overhead ola bilər).

### 9) Java 24+ — pinning fix və yeni API

JEP 491 (Java 24) `synchronized` pinning-i aradan qaldırdı:

```java
// Əvvəl Java 21-də PIN OLURDU
public synchronized Response callApi() {
    return httpClient.send(request);   // VT pin, carrier blok
}

// Java 24-də avtomatik unmount olur — kod dəyişmədi
```

Digər yaxşılaşdırmalar:
- Pinning diagnostics JFR event kimi (Java 22+)
- Thread dump-larda VT-lər ayrıca göstərilir (`jcmd <pid> Thread.dump_to_file`)
- `jcmd <pid> Thread.dump_to_file -format=json vt-dump.json` — VT-lər daxil

### 10) Debugging və observability

```bash
# Thread dump — bütün VT-lər daxil
jcmd <pid> Thread.dump_to_file -format=json /tmp/dump.json

# Pinning tracking
java -Djdk.tracePinnedThreads=full -jar app.jar

# JFR event-lər VT üçün
# jdk.VirtualThreadPinned
# jdk.VirtualThreadSubmitFailed

# JFR record + VT events
jcmd <pid> JFR.start settings=profile duration=60s filename=vt.jfr
# JMC-də açın
```

---

## PHP-də istifadəsi

### 1) PHP 8.1 Fibers — manual coroutine

Fiber özü scheduler deyil — sadəcə "suspendable function"-dur. `Fiber::suspend()` və `$fiber->resume()` ilə idarə olunur.

```php
<?php
$fiber = new Fiber(function (): string {
    echo "Start\n";
    $feedback = Fiber::suspend('first yield');   // yer ver
    echo "Resumed with: $feedback\n";

    $feedback2 = Fiber::suspend('second yield');
    echo "Again: $feedback2\n";

    return 'final';
});

$val = $fiber->start();         // Start, return 'first yield'
echo "Got: $val\n";

$val = $fiber->resume('A');     // Resumed with: A, return 'second yield'
echo "Got: $val\n";

$val = $fiber->resume('B');     // Again: B, terminated
echo "Final: " . $fiber->getReturn() . "\n";
```

Output:
```
Start
Got: first yield
Resumed with: A
Got: second yield
Again: B
Final: final
```

### 2) Fiber lifecycle — start, suspend, resume, throw, getReturn

```php
<?php
$fiber = new Fiber(function (int $x): int {
    try {
        $y = Fiber::suspend($x * 2);
        return $y + 1;
    } catch (\RuntimeException $e) {
        return -1;
    }
});

// start: ilk dəfə başlat, argument ötür
$half = $fiber->start(21);   // 42
var_dump($fiber->isStarted());    // true
var_dump($fiber->isSuspended());  // true
var_dump($fiber->isTerminated()); // false

// resume: davam etdir, dəyər ver
$result = $fiber->resume(100);    // return 101
var_dump($fiber->getReturn());    // 101

// throw: exception at et (resume əvəzinə)
$fiber2 = new Fiber(function () { Fiber::suspend(); });
$fiber2->start();
$fiber2->throw(new \RuntimeException('boom'));
```

### 3) Swoole coroutines — C extension

Swoole PHP-ə native coroutine əlavə edir — hook flag-ları ilə blocking PHP funksiyalarını async edir.

```bash
pecl install swoole
```

```php
<?php
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

// Hook-lar: file_get_contents, sleep, mysqli, curl async olur
Coroutine::set([
    'hook_flags' => SWOOLE_HOOK_ALL,
]);

Coroutine\run(function () {
    $start = microtime(true);

    $wg = new WaitGroup();
    $results = [];

    for ($i = 0; $i < 100; $i++) {
        $wg->add();
        Coroutine::create(function () use ($i, $wg, &$results) {
            // Bu artıq blok etmir — coroutine suspend olur
            $html = file_get_contents("https://httpbin.org/delay/1");
            $results[$i] = strlen($html);
            $wg->done();
        });
    }

    $wg->wait();

    $duration = microtime(true) - $start;
    echo "100 request: {$duration}s\n";   // ~1-2s (parallel)
});
```

### 4) Swoole HTTP Server — PHP-FPM əvəzinə

```php
<?php
use Swoole\Http\Server;

$server = new Server('0.0.0.0', 9501);

$server->set([
    'worker_num' => 4,           // iş prosesləri (CPU core qədər)
    'task_worker_num' => 4,
    'max_request' => 10_000,     // worker restart count
    'enable_coroutine' => true,
    'hook_flags' => SWOOLE_HOOK_ALL,
]);

$server->on('request', function ($request, $response) {
    // Hər sorğu ayrı coroutine-dir
    $result = file_get_contents('http://internal-api/data');
    $response->end($result);
});

$server->start();
```

### 5) OpenSwoole — community fork

OpenSwoole Swoole-un fork-udur — API oxşardır, amma yeni feature-lər var.

```bash
pecl install openswoole
```

```php
<?php
use OpenSwoole\Coroutine;
use OpenSwoole\HTTP\Server;

Coroutine::set(['hook_flags' => OPENSWOOLE_HOOK_ALL]);

Coroutine\run(function () {
    // Eyni API, fərqli namespace
    $wg = new Coroutine\WaitGroup();
    // ...
});
```

### 6) ReactPHP Fibers ilə — v3

ReactPHP 1.4+ Fibers istifadə edir — async I/O sync kod kimi yazıla bilir.

```bash
composer require react/async react/http
```

```php
<?php
use function React\Async\await;
use function React\Async\async;
use function React\Async\parallel;
use React\Http\Browser;

$browser = new Browser();

// sync görünən async kod (daxildə Fiber)
$fetch = async(function (string $url) use ($browser): string {
    $response = await($browser->get($url));
    return (string) $response->getBody();
});

$results = await(parallel([
    fn() => $fetch('https://api1.com'),
    fn() => $fetch('https://api2.com'),
    fn() => $fetch('https://api3.com'),
]));

foreach ($results as $body) {
    echo strlen($body) . "\n";
}
```

### 7) amphp v3 — Fiber əsaslı

```bash
composer require amphp/amp:^3 amphp/http-client:^5
```

```php
<?php
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use function Amp\async;
use function Amp\Future\await;

$client = HttpClientBuilder::buildDefault();

// Fiber-based suspendable function
function fetchUser(int $id, $client): array
{
    $response = $client->request(new Request("https://api.com/users/$id"));
    return json_decode($response->getBody()->buffer(), true);
    // buffer() async — amma sync kod kimi yazılıb (Fiber sayəsində)
}

// Paralel async tasks
$futures = [];
for ($i = 1; $i <= 100; $i++) {
    $futures[] = async(fn() => fetchUser($i, $client));
}

$users = await($futures);
echo "100 users fetched\n";
```

### 8) Laravel Octane `concurrently()` — paralel coroutine

Octane Swoole üstündə `Octane::concurrently()` API verir — bir neçə tapşırığı paralel işlə:

```php
<?php
use Laravel\Octane\Facades\Octane;

public function show(int $userId)
{
    [$user, $orders, $profile] = Octane::concurrently([
        fn() => User::find($userId),
        fn() => Order::where('user_id', $userId)->get(),
        fn() => Profile::where('user_id', $userId)->first(),
    ], 3000);   // 3s timeout

    return view('show', compact('user', 'orders', 'profile'));
}
```

Hər callback ayrı Swoole coroutine-dir — sequence olsaydı 300ms, paralel ~100ms.

### 9) RoadRunner workers — proses modeli (VT deyil)

RoadRunner Swoole-dan fərqli yanaşır — hər worker Go-da yaradılmış long-lived PHP proces-dir, coroutine yox.

```yaml
# .rr.yaml
http:
  address: 0.0.0.0:8080
  pool:
    num_workers: 16
    max_jobs: 1000
    supervisor:
      max_worker_memory: 256
      ttl: 3600
```

```php
<?php
// worker.php
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

$worker = Worker::create();
$psr7 = new PSR7Worker($worker, ...);

while ($request = $psr7->waitRequest()) {
    try {
        $response = $app->handle($request);
        $psr7->respond($response);
    } catch (\Throwable $e) {
        $psr7->respond(new Response(500, [], $e->getMessage()));
    }
}
```

Fərq: RoadRunner-də coroutine yox, hər worker ayrı prosesdir — 16 worker = 16 concurrent sorğu. Daxildə amphp/ReactPHP istifadə edərək coroutine əlavə etmək olar.

### 10) Benchmark — PHP-FPM vs Swoole vs RoadRunner

10K HTTP sorğu, hər biri 100ms external API + 100ms DB:

```
PHP-FPM (pm.max_children=200):
  Concurrent limit: 200
  Total time: ~100s (hər sorğu 200ms, seq)
  Throughput: ~1000 req/s

RoadRunner (16 worker):
  Concurrent limit: 16
  Total time: ~125s
  Throughput: ~800 req/s

Swoole coroutine (4 worker × 10K coroutine):
  Concurrent limit: ~40K
  Total time: ~0.25s
  Throughput: ~40K req/s
```

Fərq açıqdır: Swoole coroutine + hook flags = Java Virtual Thread-ə bənzər performance. Amma proqramçıya əl ilə konfiqurasiya, extension install lazımdır.

---

## Əsas fərqlər

| Xüsusiyyət | Java Virtual Thread | PHP Fiber / Swoole |
|---|---|---|
| Versiya | Java 21 stable (JEP 444) | Fiber: PHP 8.1, Swoole: extension |
| Runtime | JVM daxili | Fiber: core, Swoole/amphp əlavə |
| Memory/thread | ~few KB (grow olur) | Fiber ~100KB, Swoole ~8KB |
| Maksimum | Milyonlarla | Swoole 10K+, Fiber orta |
| Scheduler | Avtomatik (ForkJoinPool) | Fiber manual, Swoole avtomatik |
| Blocking I/O | VT unmount (avtomatik) | Fiber özü etmir — scheduler etməlidir |
| Hook flags | Lazım deyil — JDK I/O async | Swoole `hook_flags` lazımdır |
| `sleep()` | `Thread.sleep()` unmount | Swoole `sleep()` hook, Fiber əl ilə suspend |
| Pinning | `synchronized` (Java 21-23), native | Fiber C extension çağırışları |
| Pinning fix | JEP 491 (Java 24) | Swoole hook-lar |
| Integration | Spring Boot 3.2+ native | Laravel Octane, Symfony Runtime |
| Thread-per-task | `newVirtualThreadPerTaskExecutor()` | Swoole `Coroutine::create()` |
| Cancellation | `Thread.interrupt()` | Swoole `Coroutine::cancel()` |
| Context | ThreadLocal, ScopedValue (JEP 464) | Swoole Context (per coroutine) |
| Debug | `jcmd Thread.dump`, JFR events | Swoole tracker, Xdebug |
| Learning curve | Minimal (köhnə kod işləyir) | Swoole: yüksək, Fiber: orta |

---

## Niyə belə fərqlər var?

**Threading tarixində fərq.** Java 30 ildir thread API-yə sahibdir — `Thread`, `Runnable`, `synchronized`, JMM. Loom bu infrastrukturu "async-aware" etdi — proqramçı köhnə üslubda bloklayıcı kod yazır, JVM altdan unmount edir. PHP isə single-threaded dildir — thread API heç olmadı. Fiber coroutine əlavə etdi, amma "threading" infrastructure yoxdur.

**JDK I/O avtomatik async.** JDK-nın `Socket`, `FileChannel`, `InputStream` sinifləri Loom-la birlikdə yenidən yazıldı — bloklanan metodlar VT-də avtomatik unmount edir. PHP-də standard I/O (file_get_contents, curl) blocking qalır — Swoole "hook" ilə onları coroutine-aware edir, amma bu extension tələbidir.

**Scheduler kim yazır.** JDK Loom üçün built-in ForkJoinPool scheduler var. PHP-də Fiber scheduler-siz gəlir — amphp, ReactPHP, Swoole hər biri öz scheduler-ini yazır. Proqramçı ya bu ekosistemləri öyrənməli, ya da əl ilə Fiber idarə etməlidir.

**Stay-alive runtime zəruriliyi.** Virtual Thread JVM-də mənalıdır — JVM saatlarla işləyir, minlərlə concurrent connection handle edir. PHP-FPM cold-boot modelində coroutine mənasızdır — hər sorğu təmiz başlayır, state yoxdur. Swoole/Octane stay-alive model yaradır — coroutine bu kontekstdə mənalı olur.

**Memory per coroutine.** Swoole coroutine ~8KB, VT ~few KB. Fərq kiçik görünsə də, milyonlarla coroutine-də əhəmiyyətli olur — Swoole 10K-100K, JVM milyonlarla.

**Pinning real problem.** Java 21-də `synchronized` pinning qarşısı alınmaz problem idi — legacy kitabxanalar bu pattern istifadə edir. JEP 491 (Java 24) həll etdi. PHP-də analoq problem yoxdur — Fiber başqa prinsipdə işləyir, amma C extension çağırışı (Swoole hook-suz) blok edir.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- Virtual Threads (Loom) — milyonlarla ucuz thread
- `Thread.ofVirtual()` builder
- `Executors.newVirtualThreadPerTaskExecutor()`
- Continuation-based implementation
- Carrier thread automatic mount/unmount
- JDK I/O avtomatik async-aware
- ForkJoinPool as default scheduler
- JEP 491 (Java 24) `synchronized` pinning fix
- Spring Boot 3.2+ `spring.threads.virtual.enabled=true`
- JFR VirtualThreadPinned event
- `jcmd Thread.dump_to_file -format=json` VT included
- ScopedValue (JEP 464) — immutable context per VT

**Yalnız PHP-də:**
- Fiber (PHP 8.1+) — manual suspendable function
- `Fiber::suspend()`, `$fiber->resume()`, `$fiber->throw()`
- Swoole coroutine (C extension)
- Swoole `hook_flags` — blocking PHP funksiyaları async
- OpenSwoole community fork
- amphp v3 `async()` + `await()` (Fiber üstündə)
- ReactPHP Fibers integration (v3+)
- Laravel Octane `Octane::concurrently()`
- RoadRunner worker model (proses-based)
- Swoole Table (shared memory proseslər arası)
- Swoole WaitGroup, Coroutine\Channel

---

## Best Practices

**Java tərəfdə:**
- Virtual Thread I/O-bound tapşırıqlar üçün — DB, HTTP, file
- CPU-bound üçün platform thread (`ForkJoinPool`, `parallelStream`)
- Thread pool yaradıcısı Swoole-da `max_request` var, VT-də pool lazım deyil — per-task
- Spring Boot 3.2+ `spring.threads.virtual.enabled=true` set et
- `synchronized` əvəzinə `ReentrantLock` istifadə et (Java 21-23)
- Java 24+-də pinning problemi yoxdur — kod dəyişməsi lazım deyil
- `ThreadLocal` əvəzinə `ScopedValue` — milyonlarla VT-də memory şişməsi
- `jdk.tracePinnedThreads=full` ilə pinning aşkar et (development)
- JDBC driver-in VT-safe olduğunu yoxla (HikariCP OK, köhnə driver-lər pin edə bilər)
- Blocking kitabxana çağırırsan — VT-ə qoy, narahat olma (CompletableFuture lazım deyil)
- Profile JFR ilə — jdk.VirtualThreadPinned event-i izlə

**PHP tərəfdə:**
- Fiber low-level API-dir — amphp, ReactPHP, Swoole ilə işlə
- Swoole-də `hook_flags=SWOOLE_HOOK_ALL` set et — file_get_contents, sleep, mysqli async olur
- PDO + Swoole: `hook_flags=SWOOLE_HOOK_PDO_PGSQL` kimi spesifik flag-lar
- Laravel Octane üçün `max_requests=500` — memory leak qarşısı
- `Octane::concurrently()` paralel external API çağırışları üçün
- Global state yaradma — Octane-də sızır
- WaitGroup coroutine tamamlanmasını gözləmək üçün
- `Coroutine\Channel` coroutine-lər arası mesaj ötürmə
- Connection pool PDO və Redis üçün — Swoole-də per-worker connection pool
- Swoole Table shared memory-də cache üçün — Redis əvəzinə lokal
- RoadRunner proses-based — memory leak daha az problem, amma coroutine yox
- `concurrently()` timeout ilə (3s, 5s) — hang qarşısı

---

## Yekun

- Virtual Threads (Java 21, JEP 444) — milyonlarla ucuz thread, JVM tərəfindən idarə olunur. Carrier thread-ə mount/unmount edir
- Platform thread ~1 MB, Virtual thread ~few KB — I/O-bound iş yükü üçün oyun-dəyişdirici
- `Thread.ofVirtual()`, `Executors.newVirtualThreadPerTaskExecutor()` — thread-per-task model
- Pinning problem: `synchronized` və JNI — Java 24 JEP 491 ilə `synchronized` problemi həll olundu
- Spring Boot 3.2+ `spring.threads.virtual.enabled=true` ilə Tomcat request-ləri VT-yə keçir
- ThreadLocal milyonlarla VT-də memory şişmə — ScopedValue (JEP 464) alternativ
- PHP Fiber (8.1+) low-level suspendable function — Scheduler-siz gəlir
- Swoole coroutine C extension ilə — `hook_flags` blocking funksiyaları async edir
- amphp v3, ReactPHP Fibers üstündə sync-görünən async API verir
- Laravel Octane `concurrently()` paralel external calls üçün
- RoadRunner proses-model (coroutine yox) — Go worker manager + PHP subprocess
- Benchmark: Swoole 40K req/s (10K concurrent I/O), PHP-FPM ~1K req/s, VT oxşar Swoole-a
- Debug: JVM `jcmd Thread.dump`, JFR events. PHP Xdebug, Swoole tracker
- Ekosistem fərqi: Java-da vahid API, PHP-də parçalanmış (Fiber/Swoole/amphp/ReactPHP/Octane)
