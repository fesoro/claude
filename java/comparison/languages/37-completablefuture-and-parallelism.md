# CompletableFuture və Paralelizm (Java vs PHP)

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Paralel/konkurent iş iki formada olur: **I/O-bound** (HTTP, DB, fayl) — proqram gözləməkdən itkilidir və **CPU-bound** (hesablama, şifrələmə, şəkil emalı) — proqram CPU-da itkilidir. Bunların həll yolları fərqlidir.

**Java** 1.5-də `Future`, 8-də `CompletableFuture`, 8-də parallel stream və ForkJoinPool, 21-də Virtual Threads gətirdi. `CompletableFuture` callback zəncirlər, aggregation (`allOf`, `anyOf`), timeout və exception handling verir. Parallel stream isə CPU-bound üçün ForkJoinPool-u istifadə edir.

**PHP** native future yoxdur — request-per-process modelində çox vaxt lazım da olmur. Amma async runtime-larda (amphp, ReactPHP, Swoole) Future/Promise var. Laravel `Http::pool()` birbaşa Java-nın `allOf` analoqudur.

Bu fayl I/O və CPU paralelizmini hər iki dildə dərindən müqayisə edir.

---

## Java-da istifadəsi

### 1) `CompletableFuture` əsas API

`CompletableFuture<T>` — future + callback composition. İki yolla yaradılır:

```java
// 1) Executor-da iş başlat
CompletableFuture<User> cf = CompletableFuture.supplyAsync(
    () -> fetchUser(42),
    executor          // istəsən, default ForkJoinPool.commonPool
);

// 2) Manual olaraq complete
CompletableFuture<String> manual = new CompletableFuture<>();
// Başqa yerdə:
manual.complete("hazır");
// və ya
manual.completeExceptionally(new RuntimeException("xəta"));

// supplyAsync — dəyər qaytarır
CompletableFuture<String> a = CompletableFuture.supplyAsync(() -> "salam");

// runAsync — void iş
CompletableFuture<Void> b = CompletableFuture.runAsync(() -> log.info("başladı"));
```

### 2) Default pool vs custom executor

Default olaraq `CompletableFuture.supplyAsync` `ForkJoinPool.commonPool()` istifadə edir. Thread sayı = CPU core-1. I/O-bound işlərdə bu pool tez dolur və digər `parallelStream` istifadə edən kodu bloklayır.

```java
// XƏTA — default pool-u DB query ilə doldur
CompletableFuture<List<User>> cf = CompletableFuture.supplyAsync(() ->
    jdbcTemplate.query("SELECT * FROM users", userMapper)
);

// DÜZGÜN — custom executor
private static final ExecutorService IO_POOL = Executors.newFixedThreadPool(50);

CompletableFuture<List<User>> cf = CompletableFuture.supplyAsync(
    () -> jdbcTemplate.query("SELECT * FROM users", userMapper),
    IO_POOL
);

// Virtual Thread əsrində (Java 21+)
private static final ExecutorService VIRTUAL_POOL =
    Executors.newVirtualThreadPerTaskExecutor();

CompletableFuture<List<User>> cf = CompletableFuture.supplyAsync(
    () -> jdbcTemplate.query("SELECT * FROM users", userMapper),
    VIRTUAL_POOL
);
```

### 3) Chaining — thenApply, thenCompose, thenCombine

```java
CompletableFuture<Integer> userId = CompletableFuture.supplyAsync(() -> 42);

// thenApply — Sync transform (same thread, like Stream.map)
CompletableFuture<String> name = userId.thenApply(id -> "user-" + id);

// thenCompose — flatMap (futures zəncir)
CompletableFuture<User> user = userId.thenCompose(
    id -> fetchUserAsync(id)          // CompletableFuture<User> qaytarır
);

// thenCombine — iki future birləşdir
CompletableFuture<User> userF = fetchUserAsync(42);
CompletableFuture<Orders> ordersF = fetchOrdersAsync(42);

CompletableFuture<UserDto> dto = userF.thenCombine(ordersF,
    (u, o) -> new UserDto(u, o)
);

// thenAccept — void consumer (terminal)
dto.thenAccept(d -> log.info("DTO hazırdır: {}", d));

// thenRun — void action (dəyərə baxmır)
dto.thenRun(() -> log.info("bitti"));

// Async variantı — yeni executor-da icra et
userId.thenApplyAsync(id -> "user-" + id, executor);
```

### 4) `allOf` və `anyOf`

```java
// allOf — hamısı bitənə qədər gözlə
CompletableFuture<User>    userF    = fetchUserAsync(42);
CompletableFuture<Orders>  ordersF  = fetchOrdersAsync(42);
CompletableFuture<Profile> profileF = fetchProfileAsync(42);

CompletableFuture<Void> all = CompletableFuture.allOf(userF, ordersF, profileF);

UserDto dto = all.thenApply(v ->
    new UserDto(userF.join(), ordersF.join(), profileF.join())
).join();

// anyOf — ilk bitən qalib
CompletableFuture<String> primary   = fetchFromPrimaryDc();
CompletableFuture<String> secondary = fetchFromSecondaryDc();

Object fastest = CompletableFuture.anyOf(primary, secondary).join();
// Ilk cavab verən DC-nin data-sı
```

### 5) Error handling — `exceptionally`, `handle`, `whenComplete`

```java
CompletableFuture<User> cf = fetchUserAsync(42)
    .exceptionally(ex -> {                                   // xəta → fallback
        log.error("fetchUser xətası", ex);
        return User.guest();
    });

// handle — həm dəyər, həm xətanı görür
CompletableFuture<String> status = fetchUserAsync(42)
    .handle((user, ex) -> {
        if (ex != null) {
            return "xəta: " + ex.getMessage();
        }
        return "OK: " + user.getId();
    });

// whenComplete — side effect, dəyəri dəyişmir
fetchUserAsync(42)
    .whenComplete((user, ex) -> {
        if (ex != null) {
            metrics.increment("fetch.error");
        } else {
            metrics.increment("fetch.success");
        }
    });

// exceptionallyCompose — xəta halında yeni future (retry kimi)
fetchUserAsync(42)
    .exceptionallyCompose(ex -> fetchUserFromCacheAsync(42));
```

### 6) Timeout — Java 9+

```java
// orTimeout — müəyyən vaxta qədər bitməsə TimeoutException
CompletableFuture<String> cf = fetchRemote()
    .orTimeout(3, TimeUnit.SECONDS);

// completeOnTimeout — timeout halında default dəyər
CompletableFuture<String> cf2 = fetchRemote()
    .completeOnTimeout("default", 3, TimeUnit.SECONDS);

// Tam patern
fetchRemote()
    .completeOnTimeout("default", 3, TimeUnit.SECONDS)
    .exceptionally(ex -> "fallback")
    .thenAccept(result -> log.info(result));
```

### 7) Parallel Stream

Stream API `parallelStream()` və ya `.parallel()` ilə paralel işləyə bilər:

```java
// Seriyalı
long count = numbers.stream()
    .filter(n -> isPrime(n))
    .count();

// Paralel — ForkJoinPool.commonPool
long count = numbers.parallelStream()
    .filter(n -> isPrime(n))
    .count();
```

**Parallel Stream nə vaxt faydalıdır:**
- Çox böyük dataset (min 10k+ element)
- CPU-bound iş (şifrələmə, prime check, matrix)
- Shared mutable state YOXDUR
- Order vacib deyil (`.unordered()` əlavə et performans üçün)

**Nə vaxt ZƏRƏR verir:**
- Kiçik kolleksiya — paralel başa salmaq overhead-i ilə yavaş olur
- I/O work (DB, HTTP) — default pool-u doldurur
- Shared mutable state — race condition
- Order önəmlidir

```java
// ANTI-PATTERN: I/O paralel stream-də
List<User> users = userIds.parallelStream()
    .map(id -> httpClient.fetchUser(id))       // HTTP çağırış
    .toList();
// ForkJoinPool.commonPool dolur — digər parallel stream də dayanır!

// DÜZGÜN: CompletableFuture + custom executor
ExecutorService pool = Executors.newFixedThreadPool(20);
List<CompletableFuture<User>> futures = userIds.stream()
    .map(id -> CompletableFuture.supplyAsync(() -> httpClient.fetchUser(id), pool))
    .toList();
List<User> users = futures.stream().map(CompletableFuture::join).toList();

// VAY Java 24 — Gatherers.mapConcurrent
List<User> users = userIds.stream()
    .gather(Gatherers.mapConcurrent(20, id -> httpClient.fetchUser(id)))
    .toList();
```

### 8) Custom executor — thread naming, sized pool

```java
ExecutorService pool = Executors.newFixedThreadPool(10, r -> {
    Thread t = new Thread(r);
    t.setName("http-worker-" + counter.getAndIncrement());
    t.setDaemon(true);
    return t;
});

// Bounded queue ilə — overflow protection
ExecutorService bounded = new ThreadPoolExecutor(
    10, 20,
    60, TimeUnit.SECONDS,
    new ArrayBlockingQueue<>(100),
    new ThreadPoolExecutor.CallerRunsPolicy()  // queue dolunca caller özü run edir
);
```

### 9) ForkJoinTask — divide-and-conquer

```java
import java.util.concurrent.*;

// RecursiveTask<T> — dəyər qaytarır
class SumTask extends RecursiveTask<Long> {
    private final long[] arr;
    private final int start, end;
    private static final int THRESHOLD = 10_000;

    SumTask(long[] arr, int start, int end) {
        this.arr = arr; this.start = start; this.end = end;
    }

    @Override
    protected Long compute() {
        if (end - start <= THRESHOLD) {
            long sum = 0;
            for (int i = start; i < end; i++) sum += arr[i];
            return sum;
        }
        int mid = (start + end) / 2;
        SumTask left  = new SumTask(arr, start, mid);
        SumTask right = new SumTask(arr, mid, end);
        left.fork();                   // async başlat
        long rightResult = right.compute();
        long leftResult  = left.join();
        return leftResult + rightResult;
    }
}

long[] huge = new long[10_000_000];
ForkJoinPool pool = ForkJoinPool.commonPool();
Long total = pool.invoke(new SumTask(huge, 0, huge.length));

// RecursiveAction<void> — dəyər qaytarmır (sıralama kimi)
class SortTask extends RecursiveAction {
    // ...
}
```

### 10) Spring `@Async` və CompletableFuture

```java
@Service
public class NotificationService {

    @Async("notificationExecutor")
    public CompletableFuture<Boolean> sendEmail(String to, String subject) {
        // Uzun əməliyyat
        emailClient.send(to, subject);
        return CompletableFuture.completedFuture(true);
    }
}

@Configuration
@EnableAsync
public class AsyncConfig {
    @Bean("notificationExecutor")
    public Executor notificationExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(10);
        executor.setMaxPoolSize(30);
        executor.setQueueCapacity(100);
        executor.setThreadNamePrefix("notif-");
        executor.initialize();
        return executor;
    }
}

// Controller-də
@GetMapping("/send")
public CompletableFuture<String> send() {
    return notificationService.sendEmail("a@x.com", "Salam")
        .thenApply(ok -> ok ? "göndərildi" : "xəta");
}
```

### 11) Reactor Mono ↔ CompletableFuture

```java
import reactor.core.publisher.Mono;

// Mono → CompletableFuture
Mono<User> mono = webClient.get().uri("/user/42").retrieve().bodyToMono(User.class);
CompletableFuture<User> cf = mono.toFuture();

// CompletableFuture → Mono
CompletableFuture<User> cf2 = fetchUserAsync(42);
Mono<User> mono2 = Mono.fromFuture(cf2);
```

### 12) Real pipeline — fetch 10 HTTP API və aggregate

```java
record DashboardDto(User user, List<Order> orders, List<Notification> notifications) {}

public class DashboardService {
    private final ExecutorService pool = Executors.newVirtualThreadPerTaskExecutor();
    private final HttpClient client = HttpClient.newBuilder().build();

    public DashboardDto build(int userId) {
        CompletableFuture<User> userF = CompletableFuture.supplyAsync(
            () -> get("/users/" + userId, User.class), pool);

        List<CompletableFuture<Order>> orderFs = IntStream.range(1, 8)
            .mapToObj(i -> CompletableFuture.supplyAsync(
                () -> get("/orders/" + userId + "/item/" + i, Order.class), pool))
            .toList();

        CompletableFuture<List<Notification>> notifF = CompletableFuture.supplyAsync(
            () -> get("/notifications/" + userId, Notification[].class), pool)
            .thenApply(List::of);

        CompletableFuture<List<Order>> ordersF = CompletableFuture
            .allOf(orderFs.toArray(CompletableFuture[]::new))
            .thenApply(v -> orderFs.stream().map(CompletableFuture::join).toList());

        return userF.thenCombine(ordersF, (u, os) -> new Object[]{u, os})
            .thenCombine(notifF, (pair, notifs) ->
                new DashboardDto((User) pair[0], (List<Order>) pair[1], notifs))
            .orTimeout(5, TimeUnit.SECONDS)
            .exceptionally(ex -> DashboardDto.empty(userId))
            .join();
    }

    private <T> T get(String path, Class<T> type) {
        // HTTP GET
        return null;
    }
}
```

---

## PHP-də istifadəsi

### 1) Native PHP-də future yoxdur

Adi PHP sinxron-dur. `curl_multi_*` funksiyaları paralel HTTP üçün var, amma aşağı səviyyəli və çox gəzilmir:

```php
$mh = curl_multi_init();
$handles = [];

foreach (['url1', 'url2', 'url3'] as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($mh, $ch);
    $handles[$url] = $ch;
}

do {
    $status = curl_multi_exec($mh, $active);
    curl_multi_select($mh);
} while ($active && $status === CURLM_OK);

$results = [];
foreach ($handles as $url => $ch) {
    $results[$url] = curl_multi_getcontent($ch);
    curl_multi_remove_handle($mh, $ch);
}
curl_multi_close($mh);
```

Bu kod oxuması çətindir — modern PHP amphp, ReactPHP, Guzzle istifadə edir.

### 2) Guzzle — Promise

```bash
composer require guzzlehttp/guzzle
```

```php
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;

$client = new Client();

$promises = [
    'user'     => $client->getAsync('https://api.com/users/42'),
    'orders'   => $client->getAsync('https://api.com/orders/42'),
    'profile'  => $client->getAsync('https://api.com/profile/42'),
];

// allOf analoqu — amma xəta halında fail
$responses = Utils::unwrap($promises);

// settle — hamısı bitsin, xəta olsa belə
$results = Utils::settle($promises)->wait();
foreach ($results as $key => $result) {
    if ($result['state'] === 'fulfilled') {
        echo $key, ': OK', PHP_EOL;
    } else {
        echo $key, ': XƏTA — ', $result['reason']->getMessage(), PHP_EOL;
    }
}
```

### 3) amphp v3 — Future və await

```bash
composer require amphp/amp amphp/http-client
```

```php
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use function Amp\async;
use function Amp\Future\await;
use function Amp\Future\awaitFirst;
use function Amp\Future\awaitAll;
use function Amp\Future\awaitAny;

$client = HttpClientBuilder::buildDefault();

// awaitAll — Java allOf analoqu
$futures = [
    'user'    => async(fn() => $client->request(new Request('https://api.com/users/42'))),
    'orders'  => async(fn() => $client->request(new Request('https://api.com/orders/42'))),
    'profile' => async(fn() => $client->request(new Request('https://api.com/profile/42'))),
];

$responses = await($futures);
// Hamısı paralel — cəmi ən uzun sorğu qədər

// awaitFirst — Java anyOf analoqu (ilk bitən)
$fastest = awaitFirst([
    async(fn() => fetchFromPrimaryDc()),
    async(fn() => fetchFromSecondaryDc()),
]);

// awaitAll — hamısı bitsin (xəta halında belə)
[$errors, $successes] = awaitAll($futures);

// awaitAny — ilk uğurlu (xəta ignoring)
$ok = awaitAny($futures);
```

### 4) amphp Future — chaining

```php
use Amp\Future;
use function Amp\async;

// Sync transform (thenApply kimi)
$userIdF = async(fn() => 42);
$nameF   = $userIdF->map(fn($id) => "user-{$id}");

// flatMap (thenCompose)
// amphp-də yerli flatMap yoxdur — daxilində async istifadə et
$userF = async(function () use ($userIdF) {
    $id = $userIdF->await();
    return fetchUser($id);
});

// Exception handling
try {
    $user = $userF->await();
} catch (\Throwable $e) {
    $user = User::guest();
}

// Timeout
$userWithTimeout = async(function () use ($userF) {
    return $userF->await(new Amp\TimeoutCancellation(3));   // 3 saniyə
});
```

### 5) ReactPHP — Promise API

```bash
composer require react/http react/promise
```

```php
use React\Http\Browser;
use React\EventLoop\Loop;
use function React\Promise\all;
use function React\Promise\race;
use function React\Promise\any;

$browser = new Browser();

// all — Java allOf
all([
    'user'    => $browser->get('https://api.com/users/42'),
    'orders'  => $browser->get('https://api.com/orders/42'),
    'profile' => $browser->get('https://api.com/profile/42'),
])->then(
    function (array $responses) {
        echo "Hamısı: ", count($responses), PHP_EOL;
    },
    function (\Throwable $e) {
        echo "Xəta: ", $e->getMessage(), PHP_EOL;
    }
);

// race — anyOf
race([
    $browser->get('https://primary.api/data'),
    $browser->get('https://secondary.api/data'),
])->then(function ($response) {
    echo $response->getBody();
});

// Promise chain
$browser->get('https://api.com/users/42')
    ->then(fn($r) => json_decode((string) $r->getBody(), true))
    ->then(fn($d) => $d['email'])
    ->then(fn($email) => $browser->post('https://email.api/send', [], $email))
    ->catch(fn($e) => error_log($e->getMessage()));

Loop::run();
```

### 6) Laravel `Http::pool()` — `allOf` eşit

```php
use Illuminate\Support\Facades\Http;

// allOf ekvivalenti
$responses = Http::pool(fn ($pool) => [
    $pool->as('user')->get('https://api.com/users/42'),
    $pool->as('orders')->get('https://api.com/orders/42'),
    $pool->as('profile')->get('https://api.com/profile/42'),
]);

$user    = $responses['user']->json();
$orders  = $responses['orders']->json();
$profile = $responses['profile']->json();

// Laravel daxildə Guzzle Promise istifadə edir
// Hər sorğu paralel gedir
```

### 7) Swoole — goroutine və channel

```bash
pecl install swoole
```

```php
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Http\Client;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

run(function () {
    $channel = new Channel(3);

    go(function () use ($channel) {
        $client = new Client('api.com', 443, true);
        $client->get('/users/42');
        $channel->push(['user', $client->body]);
    });

    go(function () use ($channel) {
        $client = new Client('api.com', 443, true);
        $client->get('/orders/42');
        $channel->push(['orders', $client->body]);
    });

    go(function () use ($channel) {
        $client = new Client('api.com', 443, true);
        $client->get('/profile/42');
        $channel->push(['profile', $client->body]);
    });

    $results = [];
    for ($i = 0; $i < 3; $i++) {
        [$key, $data] = $channel->pop();
        $results[$key] = json_decode($data, true);
    }

    var_dump($results);
});
```

### 8) CPU-bound paralelizm — PHP-də necə?

PHP single-threaded-dir, thread yoxdur. CPU-paralel üçün:

**A) pcntl_fork (UNIX)**

```php
$pid = pcntl_fork();
if ($pid === -1) {
    die('fork fail');
} elseif ($pid === 0) {
    // Child process
    $result = expensiveCompute();
    file_put_contents("/tmp/result_{$_SERVER['PID']}", serialize($result));
    exit(0);
} else {
    // Parent process
    pcntl_wait($status);
    $result = unserialize(file_get_contents("/tmp/result_{$pid}"));
}
```

**B) Queue worker — fan-out**

```php
// Laravel — job dispatch
use App\Jobs\ProcessChunk;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$chunks = array_chunk($hugeData, 1000);

$batch = Bus::batch(
    array_map(fn($chunk) => new ProcessChunk($chunk), $chunks)
)
->then(function (Batch $batch) {
    // Hamısı bitdi
})
->catch(function (Batch $batch, \Throwable $e) {
    // Xəta
})
->dispatch();

// N işçi paralel icra edir (queue:work --queue=default --processes=8)
```

**C) amphp/parallel — worker pool**

```bash
composer require amphp/parallel
```

```php
use Amp\Parallel\Worker;

// Heavy iş worker process-də
$result = Worker\submit(new Task\FibTask(40));

// Paralel tasks
$results = await([
    async(fn() => Worker\submit(new Task\FibTask(40))->getFuture()->await()),
    async(fn() => Worker\submit(new Task\FibTask(41))->getFuture()->await()),
]);
```

### 9) Laravel Concurrency facade (Laravel 11+)

Laravel 11 `Concurrency` facade gətirdi — arxada amphp/parallel istifadə edir:

```php
use Illuminate\Support\Facades\Concurrency;

[$users, $orders, $stats] = Concurrency::run([
    fn() => User::all(),
    fn() => Order::all(),
    fn() => Stats::compute(),
]);

// defer — response-dan sonra icra et
Concurrency::defer(function () {
    // user response aldıqdan sonra işləsin
    app(NotificationService::class)->sendLogs();
});
```

### 10) Real pipeline — 10 HTTP API fetch

```php
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;

final class DashboardService
{
    public function __construct(
        private readonly Client $http,
    ) {}

    public function build(int $userId): array
    {
        $promises = [
            'user'          => $this->http->getAsync("/users/{$userId}"),
            'notifications' => $this->http->getAsync("/notifications/{$userId}"),
        ];

        for ($i = 1; $i <= 8; $i++) {
            $promises["order_{$i}"] = $this->http->getAsync("/orders/{$userId}/item/{$i}");
        }

        // settle — xəta olsa belə hamısını gözlə
        $results = Utils::settle($promises)->wait();

        $user = $this->decode($results['user']);
        $notifications = $this->decode($results['notifications']);

        $orders = [];
        for ($i = 1; $i <= 8; $i++) {
            $order = $this->decode($results["order_{$i}"]);
            if ($order !== null) {
                $orders[] = $order;
            }
        }

        return compact('user', 'orders', 'notifications');
    }

    private function decode(array $result): ?array
    {
        if ($result['state'] !== 'fulfilled') {
            return null;
        }
        return json_decode((string) $result['value']->getBody(), true);
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Future tip | `CompletableFuture<T>` | Guzzle Promise, amphp Future, React Promise |
| `allOf` | `CompletableFuture.allOf` | `Utils::unwrap`, `Http::pool`, `await()` |
| `anyOf` | `CompletableFuture.anyOf` | `awaitFirst`, React `race` |
| `thenApply` (map) | var | `->then()`, amphp `->map()` |
| `thenCompose` (flatMap) | var | `->then(fn => promise)` (auto-flatten) |
| `thenCombine` | var | Əl ilə, və ya `Utils::all` |
| Timeout | `orTimeout`, `completeOnTimeout` | amphp `TimeoutCancellation`, Guzzle `timeout` |
| Exception handling | `exceptionally`, `handle`, `whenComplete` | `->catch()`, try/catch `await()` |
| CPU-bound paralelizm | Parallel Stream, ForkJoinPool | pcntl_fork, queue worker, amphp/parallel |
| I/O-bound paralelizm | Virtual Thread + CF | amphp, ReactPHP, Swoole, Http::pool |
| Memory per "task" | VT: ~1 KB, CF overhead az | Swoole: ~8 KB, Fiber: ~100 KB |
| Fork-join | `RecursiveTask`, `RecursiveAction` | Yoxdur — manual |
| Default pool | `ForkJoinPool.commonPool()` | Runtime-dən asılı |
| Framework integration | Spring `@Async`, Reactor | Laravel `Http::pool`, `Concurrency` |
| Reactive stream | Reactor, RxJava | RxPHP (az) |
| Backpressure | Reactor native | Amphp manuell |

---

## Niyə belə fərqlər var?

**Java async tarixi — 20 il evolution.** Java 1.5-də `Future` çox primitive idi (yalnız `get()`, iptal). Java 8-də `CompletableFuture` gəldi — composition, transformation, error handling. Java 21-də Virtual Threads ilə "async imperative" mümkün oldu. Bu uzun evolution zəngin API verir.

**PHP-nin request-per-process təməli.** PHP orijinal dizaynı "hər sorğu müstəqil" idi — async concept dilə kök salmayıb. Future/Promise framework səviyyəsində gəldi (Guzzle, amphp, React). Native API yoxdur — hər library öz stil-ini istifadə edir.

**CPU paralelizm fərqi.** Java JVM multi-threaded — `parallelStream`, `ForkJoinPool` birbaşa CPU core-lardan istifadə edir. PHP single-threaded interpretator — CPU paralelizm üçün proses fork etmək lazım (yaddaş bahalıdır) və ya queue worker (network latency var).

**Default pool tərxinədici.** Java-da `ForkJoinPool.commonPool()` default-dur — bu hər kəs bilməlidir, çünki I/O işini ora atsan, parallel stream yavaşlayır. PHP-də hər library (Guzzle, amphp, Swoole) öz pool-unu idarə edir — "default" yoxdur.

**Reactive tale.** Java-da Reactor və RxJava ciddi istifadə olunur — Spring WebFlux bütün stack-i reactive edir. PHP-də RxPHP var amma az istifadə olunur — Promise və Fiber daha çox tutur.

**Virtual Thread "async killer".** Java 21+ Virtual Thread-lərlə `CompletableFuture` zənciri yazmağa ehtiyac azaldı — sadə `try { user = fetchUser(); } catch {...}` yaz, virtual thread bloklayan kodu async edir. PHP-də Fiber bu yolu açır, amma hələ kitabxanalar bloklayan API-ləri Fiber-aware etmir.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- `CompletableFuture` — native future + composition
- `thenApply`, `thenCompose`, `thenCombine`, `thenAccept`, `thenRun`
- `allOf`, `anyOf` — birbaşa static metod
- `orTimeout`, `completeOnTimeout` (Java 9+)
- `exceptionally`, `handle`, `whenComplete`, `exceptionallyCompose`
- `ForkJoinPool`, `RecursiveTask`, `RecursiveAction` — divide-and-conquer
- `parallelStream()` — native paralel Stream
- `ThreadPoolExecutor` — bounded queue, rejection policy
- Spring `@Async` + `CompletableFuture` — framework integration
- Reactor Mono/Flux ↔ CompletableFuture conversion
- `Gatherers.mapConcurrent` (Java 24) — virtual thread concurrent map
- JMM happens-before garanti

**Yalnız PHP-də:**
- Swoole `go { ... }` — inline coroutine
- Swoole Channel — goroutine arası komunikasiya
- Swoole Table — shared memory
- Laravel `Http::pool()` — fluent parallel HTTP
- Laravel `Concurrency` facade (Laravel 11+)
- `pcntl_fork` — proses fork
- amphp/parallel — worker pool with task abstraction
- `curl_multi_*` — low-level paralel HTTP
- RoadRunner worker model — long-lived PHP worker
- ReactPHP — Node.js kimi event loop
- Laravel Queue `Bus::batch()` — distributed fan-out

---

## Best Practices

**Java:**
- Default `ForkJoinPool.commonPool()` I/O üçün istifadə etmə — custom executor yarat
- Java 21+ istifadə edirsənsə `Executors.newVirtualThreadPerTaskExecutor()` I/O üçün ideal
- Parallel stream yalnız CPU-bound + 10k+ element + stateless üçün
- `.join()` deadlock yaradar əgər callback-dən çağırılsa — başqa thread-dən istifadə et
- `orTimeout` mütləq əlavə et — timeout olmayan future resource leak-dir
- Spring `@Async` üçün custom executor konfiqurasiya et (default SimpleAsyncTaskExecutor yanlış seçimdir)
- Reactor və CompletableFuture qarışdırma — birini seç
- Exception-ları `exceptionally` və ya `handle` ilə yaxalama — `join()` ilə qiyamət olmasın

**PHP:**
- Laravel-də paralel HTTP üçün `Http::pool()` istifadə et — Guzzle-dən oxunaqlı
- amphp `await(async(fn() => ...))` pattern-i standartdır — Future-ları dəyişəndə saxla
- CPU-bound iş queue-ya at — PHP-FPM-də fork etmə (memory bahadır)
- Swoole/RoadRunner-də state leak riskini bil — singleton-ları təmizlə
- Promise chain-də `->catch()` əlavə et, uncaught promise rejection debug çətinləşdirir
- `curl_multi` low-level-dir — yüksək səviyyə library istifadə et (Guzzle, amphp)
- Laravel 11 `Concurrency` facade — sync kod paralel etmək üçün yaxşıdır
- ReactPHP və amphp qarışdırma — runtime konflikti ola bilər

---

## Yekun

Java paralelizm üçün zəngin, vahid API verir: `CompletableFuture` composition, `allOf`/`anyOf`/timeout/exception handling, parallel stream CPU-bound üçün, ForkJoinPool fork-join, Virtual Thread Java 21+ I/O-bound üçün. Spring ekosistemi bunu framework səviyyəsində inteqrasiya edir.

PHP native future vermir, amma Guzzle Promise, amphp Future, React Promise, Swoole coroutine, Laravel `Http::pool()` və `Concurrency` facade kimi zəngin ekosistem var. CPU-bound üçün queue worker və pcntl_fork əsas yoldur — yaddaş bahalıdır, amma proses izolasiyası stabillik verir.

Praktik seçim: I/O-bound paralel fetch hər iki dildə oxşar sadəlikdə (Java `allOf`, PHP `Http::pool`). CPU-bound hesablamada Java açıq-aydın üstündür — JVM multi-threading və parallel stream. PHP-də bu tapşırıq ya queue-ya, ya da extension-a (Swoole, pthreads) verilir.
