# Async və Koroutinlər (Java Virtual Threads vs PHP Fibers)

## Giriş

"Async" müxtəlif şeylər deməkdir: bloklanmayan I/O, paralel hesablama, green thread-lər, coroutine-lər, event loop. Java və PHP bu məsələyə çox fərqli yollardan yanaşır.

**Java** tarix boyu OS thread-lərinə güvənib: `Thread`, `ExecutorService`, sonra `CompletableFuture` (Java 8), daha sonra reaktiv kitabxanalar (Reactor, RxJava). JDK 21-də gələn **Virtual Threads (Project Loom)** oyunu dəyişdi — hər tapşırıq üçün "ucuz" thread yaratmaq mümkün oldu.

**PHP** tarix boyu request-per-process modelində işləyib — hər HTTP sorğu sıfırdan boot olur. PHP 8.1-in **Fibers**-i ilə coroutine-lər dilə gəldi. Mühitlə işləmək üçün **amphp**, **ReactPHP**, **Swoole**, **RoadRunner**, **Octane** kimi runtime-lar istifadə olunur.

---

## Java-da istifadəsi

### 1) Klassik Thread və ExecutorService

```java
// Hər tapşırıq üçün Platform Thread (OS thread)
ExecutorService executor = Executors.newFixedThreadPool(10);

Future<String> future = executor.submit(() -> {
    Thread.sleep(1000);
    return "nəticə";
});

String result = future.get();    // bloklanır
executor.shutdown();

// Problem: OS thread bahalıdır (~1 MB stack). 10 000 thread = 10 GB yaddaş
```

### 2) `CompletableFuture` — callback-əsaslı

```java
CompletableFuture<String> cf = CompletableFuture
    .supplyAsync(() -> fetchUser(42), executor)          // async başla
    .thenApply(user -> user.getEmail())                  // transform
    .thenCompose(email -> CompletableFuture.supplyAsync(
        () -> sendEmail(email), executor))               // zəncir
    .exceptionally(ex -> {
        log.error("Xəta", ex);
        return "default";
    });

String result = cf.join();

// Paralel
CompletableFuture<User> userFuture = CompletableFuture.supplyAsync(() -> fetchUser(42));
CompletableFuture<Orders> ordersFuture = CompletableFuture.supplyAsync(() -> fetchOrders(42));
CompletableFuture<Profile> profileFuture = CompletableFuture.supplyAsync(() -> fetchProfile(42));

CompletableFuture.allOf(userFuture, ordersFuture, profileFuture).join();

// allOf() sonrası join() bloklanmır (hamısı bitib)
UserDto dto = new UserDto(userFuture.join(), ordersFuture.join(), profileFuture.join());
```

### 3) Virtual Threads (Java 21+) — Project Loom

```java
// Platform thread (OS thread) — baha
Thread.ofPlatform().start(() -> task());

// Virtual thread (green thread, JVM-lə idarə olunur) — ucuz
Thread.ofVirtual().start(() -> task());

// Milyonlarla virtual thread açmaq mümkündür
try (ExecutorService executor = Executors.newVirtualThreadPerTaskExecutor()) {
    List<Future<String>> futures = IntStream.range(0, 100_000)
        .mapToObj(i -> executor.submit(() -> {
            Thread.sleep(Duration.ofSeconds(1));    // bloklayır, amma virtual thread
            return "task-" + i;
        }))
        .toList();

    for (Future<String> f : futures) {
        System.out.println(f.get());
    }
}
```

**Virtual Threads-in sirri:** `Thread.sleep()`, `InputStream.read()`, `Socket.read()` kimi bloklayan əməliyyatlar JVM tərəfindən "unparking" edilir — virtual thread "yuxuya gedir", OS thread (carrier thread) başqa virtual thread götürür. Proqramçı bloklayan kod yazır, JVM isə altdan async edir.

**Kimə uyğundur:** I/O-bound (DB, HTTP) tapşırıqlar. CPU-bound tapşırıqlar üçün platform thread daha yaxşıdır.

### 4) Structured Concurrency (Java 21 preview, 24-də stable)

```java
try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {
    Supplier<User>     userTask    = scope.fork(() -> fetchUser(42));
    Supplier<Orders>   ordersTask  = scope.fork(() -> fetchOrders(42));
    Supplier<Profile>  profileTask = scope.fork(() -> fetchProfile(42));

    scope.join()                           // hamısını gözlə
         .throwIfFailed();                 // biri xəta verərsə, digərini ləğv et

    return new UserDto(userTask.get(), ordersTask.get(), profileTask.get());
}
```

### 5) Reaktiv programming — Reactor

```java
Mono<User> user = userRepository.findById(42);
Flux<Order> orders = orderRepository.findByUserId(42);

Mono<UserDto> dto = user
    .zipWith(orders.collectList())
    .map(tuple -> new UserDto(tuple.getT1(), tuple.getT2()))
    .timeout(Duration.ofSeconds(3))
    .retry(2)
    .onErrorResume(ex -> Mono.just(UserDto.empty()));
```

Reaktiv proqramlaşdırma backpressure, stream transformation verir, amma "callback hell" riski var. Virtual Thread-lərlə çoxlu halda reaktiv-ə ehtiyac azalır — imperative kod yazıb, eyni scale-ı almaq olur.

### 6) JMM — Java Memory Model

Thread-lər arasında yaddaş paylaşımı üçün `synchronized`, `volatile`, `AtomicReference`, `java.util.concurrent` primitivləri istifadə olunur. Virtual thread-lərdə də eyni qaydalar.

```java
private final AtomicInteger counter = new AtomicInteger();

counter.incrementAndGet();                  // atomic

private final ConcurrentHashMap<String, Cache> cache = new ConcurrentHashMap<>();
cache.computeIfAbsent(key, k -> loadFromDb(k));
```

---

## PHP-də istifadəsi

### 1) Klassik sinxron model — request-per-process

PHP-FPM hər HTTP sorğu üçün ayrı prosesdə işləyir. Hər sorğu sıfırdan bootstrap olur (composer autoload, framework boot, config yüklə). Bu model sadə və stabil-dir, amma:
- Hər sorğu başlanğıc vaxtı 50-100ms ola bilər
- Paralel I/O sadə yolla etmək mümkün deyil
- State sorğular arasında saxlanılmır

```php
// Adi PHP — sinxron, hər şey sırayla
$user = $userRepo->find(42);           // 100ms
$orders = $orderRepo->forUser(42);     // 100ms
$profile = $profileRepo->find(42);     // 100ms
// Cəmi: 300ms
```

### 2) PHP 8.1 Fibers — coroutine əsası

Fiber özü async deyil — sadəcə icra axını dayandırıb davam etdirmək imkanıdır. Üstündə "scheduler" qurulmalıdır.

```php
$fiber = new Fiber(function (): void {
    echo "Fiber başladı\n";
    $value = Fiber::suspend('orta nəticə');   // dayandır
    echo "Fiber davam etdi: $value\n";
});

$initial = $fiber->start();           // "Fiber başladı" + return 'orta nəticə'
echo "Aldıq: $initial\n";

$fiber->resume('final dəyər');        // "Fiber davam etdi: final dəyər"
```

Fiber tək başına istifadə olunmur — amphp, ReactPHP kimi kitabxanalar üzərində scheduler qurur.

### 3) amphp v3 — Fiber əsaslı async

```bash
composer require amphp/amp amphp/http-client amphp/mysql
```

```php
use function Amp\async;
use function Amp\Future\await;
use Amp\Http\Client\HttpClientBuilder;

$client = HttpClientBuilder::buildDefault();

// Paralel HTTP sorğular
$responses = await([
    async(fn() => $client->request(new Request('https://api1.com'))),
    async(fn() => $client->request(new Request('https://api2.com'))),
    async(fn() => $client->request(new Request('https://api3.com'))),
]);
// Cəmi ən uzun sorğu qədər (məs: 200ms, 3 x 200ms yox)

// Sinxron görünən async kod (Fiber sayəsində)
function fetchUser(int $id): User
{
    $response = $client->request(new Request("https://api.com/users/$id"));
    return User::fromArray(json_decode($response->getBody()->buffer(), true));
    // Request zamanı Fiber suspend olur, event loop başqa iş görür
}
```

### 4) ReactPHP — promise-əsaslı event loop

```bash
composer require react/http react/promise
```

```php
use React\Http\Browser;
use React\EventLoop\Loop;
use function React\Promise\all;

$browser = new Browser();

$promises = [
    $browser->get('https://api1.com'),
    $browser->get('https://api2.com'),
    $browser->get('https://api3.com'),
];

all($promises)->then(function (array $responses) {
    foreach ($responses as $response) {
        echo $response->getBody() . "\n";
    }
})->catch(function (\Throwable $e) {
    echo "Xəta: " . $e->getMessage();
});

Loop::run();
```

### 5) Swoole — C extension ilə coroutine

```bash
pecl install swoole
```

```php
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;

Coroutine\run(function () {
    $results = [];

    $wg = new Coroutine\WaitGroup();

    foreach (['api1.com', 'api2.com', 'api3.com'] as $host) {
        $wg->add();
        Coroutine::create(function () use ($host, $wg, &$results) {
            $client = new Client($host, 443, true);
            $client->get('/data');
            $results[$host] = $client->body;
            $wg->done();
        });
    }

    $wg->wait();

    var_dump($results);   // 3 sorğu paralel gedir
});
```

Swoole həm də HTTP server kimi işləyir — PHP-FPM əvəzinə:

```php
$server = new Swoole\Http\Server('0.0.0.0', 9501);

$server->on('request', function ($request, $response) {
    // Hər sorğu ayrı coroutine-dir
    $response->end('Salam ' . $request->get['name']);
});

$server->start();
```

### 6) RoadRunner — Go-da yazılmış application server

RoadRunner-də PHP worker-lər uzun-ömürlü olur (Swoole kimi), amma Go-da yazılıb — əlavə extension lazım deyil.

```yaml
# .rr.yaml
rpc:
  listen: tcp://127.0.0.1:6001

http:
  address: 0.0.0.0:8080
  pool:
    num_workers: 8
    max_jobs: 1000
```

```php
use Spiral\RoadRunner\Http\PSR7Worker;

$worker = new PSR7Worker(...);

while ($request = $worker->waitRequest()) {
    try {
        $response = $app->handle($request);
        $worker->respond($response);
    } catch (\Throwable $e) {
        $worker->getWorker()->error((string) $e);
    }
}
```

### 7) Laravel Octane — stay-alive runtime

Octane Swoole və ya RoadRunner üzərində Laravel-i "stay-alive" edir — framework bir dəfə boot olur, hər sorğu üçün təkrar bootstrap yoxdur.

```bash
composer require laravel/octane
php artisan octane:install --server=swoole
php artisan octane:start --workers=8 --task-workers=6 --max-requests=500
```

Üstünlüklər:
- Tətbiq ~3-5x sürətli (bootstrap yox)
- Tick və concurrent task dəstəyi
- Shared state workers arasında

Problemlər:
- State scoping problemləri (singleton-lar sorğular arasında paylaşılır)
- Static data sızıntısı
- DB connection pool lazım olur
- `dd()`, `die()` worker-i öldürür

### 8) Cold-boot vs stay-alive

```
Cold-boot (PHP-FPM):
  Hər sorğu: bootstrap (50ms) + iş (100ms) = 150ms

Stay-alive (Octane/Swoole/RR):
  Birinci sorğu: bootstrap (50ms) + iş (100ms) = 150ms
  Növbəti sorğular: iş (100ms) = 100ms
```

Stay-alive scale üçün daha yaxşıdır, amma memory leak və state isolation sorunları yaradır.

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Runtime | JVM stay-alive | PHP-FPM cold-boot, Octane/Swoole stay-alive |
| OS thread | Platform Thread | Əlçatmaz (PHP özü single-threaded) |
| Green thread | Virtual Thread (JDK 21) | Fiber (PHP 8.1) |
| Event loop | Reactor, NIO | ReactPHP, amphp, Swoole |
| Callback async | `CompletableFuture` | Promise (React) |
| Imperative async | Virtual Thread ilə | amphp v3 ilə (Fiber əsaslı) |
| Structured concurrency | `StructuredTaskScope` (JDK 21+) | amphp `Future::await()` |
| Shared memory | `volatile`, `AtomicRef`, `ConcurrentHashMap` | Swoole Table, Redis (proseslər arası) |
| HTTP server | Embedded (Tomcat, Jetty, Netty) | PHP-FPM + Nginx, Swoole, RoadRunner |
| CPU-bound paralelizm | `ForkJoinPool`, parallel stream | `pcntl_fork()`, queue worker |
| Memory per "thread" | Virtual Thread: ~1 KB | Fiber: ~100 KB, Swoole coroutine: ~8 KB |
| Max concurrent | Milyonlarla virtual thread | 10K+ coroutine (Swoole), məhdud Fiber |
| Debugging | Stack trace təmiz | Fiber-də stack daha mürəkkəb |
| Reactive libs | Reactor, RxJava | RxPHP (az istifadə) |

---

## Niyə belə fərqlər var?

**Java-nın thread-centric tarixi.** Java 1995-dən OS thread ilə işləyib — synchronized, monitor, JMM bunun üstünə qurulub. Loom (Virtual Threads) bu ekosistemə "async-compatible" çevirdi — klassik `Thread.sleep()`, `Socket.read()` kodları dəyişmədən virtual thread-də işləyir. Bu, "async all the way" prinsipini aradan qaldırdı.

**PHP-nin shared-nothing tarixi.** PHP başlanğıcda "hər sorğu təmiz" prinsipi ilə yaradılıb — hər sorğu ayrı proses, state paylaşılmır, crash digərlərinə təsir etmir. Bu stabilliyi verdi, amma scale üçün problemdir. Fibers və Swoole/Octane bu modeli dəyişməyə çalışır, amma "default" deyil.

**CPU vs I/O bound.** Java JVM bytecode hazırdır paralel CPU tapşırıqları üçün (`parallelStream`, `ForkJoinPool`). PHP isə single-threaded interpretator-dir — CPU paralelizm üçün proses-level (`pcntl_fork`) və ya queue worker lazımdır.

**Memory model fərqi.** Java-da thread-lər eyni yaddaşı paylaşır — `volatile`, mutex, atomic istifadə edərək. PHP-də isə proseslər arasında yaddaş paylaşımı yoxdur — Redis, APCu, Swoole Table kimi xarici yaddaş lazımdır.

**Cold boot vs stay-alive dilemması.** Cold-boot stabil və sadə, amma yavaş. Stay-alive sürətli, amma state leak riski var. Java-da stay-alive default-dur (JVM uzun işləyir), PHP-də isə "default PHP-FPM" cold-boot — dəyişmək üçün Octane/Swoole istifadə etmək lazımdır.

**Ekosistem parçalanması (PHP).** PHP-də async üçün amphp, ReactPHP, Swoole, Octane kimi ayrı-ayrı həllər var — hər biri fərqli API, fərqli paradigma. Java-da isə Virtual Thread vahid standartlaşmış API verir.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- Virtual Threads — milyonlarla ucuz green thread tək JVM-də
- `StructuredTaskScope` — strukturlu concurrency (Java 21+)
- `CompletableFuture` — callback və composition API
- `ForkJoinPool` + parallel stream (CPU-bound paralelizm)
- `synchronized`, `volatile`, `AtomicReference` — shared memory primitivləri
- Reactor/RxJava — tam reaktiv proqramlaşdırma
- JMM (Java Memory Model) — precise happens-before qaydaları
- `ThreadLocal` + scoped value (virtual thread-lə)
- JIT optimized thread scheduling (C2 compiler)

**Yalnız PHP-də:**
- Fiber — lightweight coroutine (PHP 8.1+)
- Swoole — C extension ilə yazılmış coroutine runtime
- RoadRunner — Go-da yazılmış application server
- Octane — Laravel-i stay-alive etmək üçün
- amphp v3 — Fiber üstündə future/await API
- ReactPHP — promise əsaslı event loop (Node.js kimi)
- Cold-boot model (hər sorğu təmiz state)
- Swoole Table — shared memory worker-lər arasında
- `pcntl_fork()` — UNIX-də proses fork etmək
