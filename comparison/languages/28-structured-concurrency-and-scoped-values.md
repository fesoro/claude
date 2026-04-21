# Structured Concurrency və Scoped Values (Java vs PHP)

## Giriş

Modern concurrent programming-in iki böyük problemi: **lost threads** (fork edilmiş thread tamamlanmır, cancel olunmur) və **context propagation** (request ID, tenant, user kimi məlumatlar child thread-ə necə keçir).

**Java 21** bu iki problem üçün iki yeni API gətirdi:

1. **Structured Concurrency** (JEP 453, preview) — `StructuredTaskScope` ilə fork edilmiş task-lər avtomatik idarə olunur: scope bağlandıqda hamısı ya tamamlanıb, ya cancel olunub.
2. **Scoped Values** (JEP 464, preview) — `ScopedValue.where().run()` ilə immutable context bloka bağlanır, child VT-lər inherit edir.

Bu API-lər "lost thread" və "ThreadLocal leak" problemlərini həll edir — `CompletableFuture.allOf`-un çatışmazlıqlarını örtür. Spring Security, OpenTelemetry kimi kitabxanalar bu yeni API-ə keçir.

**PHP-də** structured concurrency üçün native API yoxdur. amphp v3 `TaskPool` və `await()` ilə oxşar pattern yaradır. ReactPHP `Promise::all()` var amma cancellation gaps saxlayır. Laravel Octane container binding ilə per-request context propagate edir — ThreadLocal analogu. Fiber özü scope anlayışı gətirmir.

Bu fayl göstərir: `StructuredTaskScope` variant-ları, ScopedValue vs ThreadLocal, real production nümunələr, PHP-də müqayisə (amphp async/await, ReactPHP all/race, Octane per-request binding).

---

## Java-da istifadəsi

### 1) Klassik problem — `CompletableFuture.allOf` çatışmazlıqları

```java
// Sorğu: user + orders + profile paralel fetch et
public UserDto loadUser(long userId) {
    CompletableFuture<User> userF = CompletableFuture
        .supplyAsync(() -> userService.find(userId), executor);

    CompletableFuture<List<Order>> ordersF = CompletableFuture
        .supplyAsync(() -> orderService.findByUser(userId), executor);

    CompletableFuture<Profile> profileF = CompletableFuture
        .supplyAsync(() -> profileService.find(userId), executor);

    CompletableFuture.allOf(userF, ordersF, profileF).join();

    return new UserDto(userF.join(), ordersF.join(), profileF.join());
}
```

Problemlər:

1. **Lost thread:** userService xəta versə, orders və profile davam edir — boş-yerə resurs.
2. **Cancellation çətindir:** `allOf` biri xəta verəndə digərlərini avtomatik cancel etmir.
3. **Thread leak:** executor shutdown olmadan CF-lər orphan qalır.
4. **Error aggregation:** birdən çox xəta varsa, hansını görürük? Diğərləri silent.

### 2) `StructuredTaskScope.ShutdownOnFailure` (JEP 453)

```java
import java.util.concurrent.StructuredTaskScope;

public UserDto loadUser(long userId) throws InterruptedException {
    try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {

        Subtask<User> userTask = scope.fork(() -> userService.find(userId));
        Subtask<List<Order>> ordersTask = scope.fork(() -> orderService.findByUser(userId));
        Subtask<Profile> profileTask = scope.fork(() -> profileService.find(userId));

        scope.join();              // hamısını gözlə (və ya xəta)
        scope.throwIfFailed();     // biri xəta verərsə exception at

        return new UserDto(
            userTask.get(),
            ordersTask.get(),
            profileTask.get()
        );
    }   // scope close → bütün fork-lar avtomatik terminate
}
```

**Davranış:**
- 3 task paralel başlayır (Virtual Thread-də)
- Biri xəta versə, digərləri dərhal `Thread.interrupt()` alır
- `scope.join()` hamısının tamamlanmasını gözləyir
- `throwIfFailed()` ilk xətanı qaldırır
- try-with-resources scope-u bağlayır — orphan thread yoxdur

### 3) `StructuredTaskScope.ShutdownOnSuccess`

İlk cavabı qaytarır — qalanlar cancel olur. Redundant API call pattern üçün.

```java
public String fetchFromReplica(String key) throws InterruptedException {
    try (var scope = new StructuredTaskScope.ShutdownOnSuccess<String>()) {

        scope.fork(() -> replicaA.fetch(key));
        scope.fork(() -> replicaB.fetch(key));
        scope.fork(() -> replicaC.fetch(key));

        scope.join();

        return scope.result();   // ilk uğurlu cavab
    }
}

// Davranış:
// A cavab verdi (100ms) → scope.result() qayıdır
// B və C dərhal interrupt olur
```

### 4) Custom policy — `StructuredTaskScope` subclass

Bəzən "ilk 2 uğurlu cavab kifayətdir" kimi məntiq lazımdır:

```java
public class QuorumScope<T> extends StructuredTaskScope<T> {
    private final int required;
    private final List<T> results = new CopyOnWriteArrayList<>();
    private final AtomicInteger failures = new AtomicInteger();

    public QuorumScope(int required) {
        this.required = required;
    }

    @Override
    protected void handleComplete(Subtask<? extends T> task) {
        switch (task.state()) {
            case SUCCESS -> {
                results.add(task.get());
                if (results.size() >= required) {
                    shutdown();   // kifayət qədər cavab var
                }
            }
            case FAILED -> {
                if (failures.incrementAndGet() > 3) {
                    shutdown();   // çox xəta — dayan
                }
            }
        }
    }

    public List<T> results() {
        return List.copyOf(results);
    }
}

// İstifadə
try (var scope = new QuorumScope<String>(2)) {
    scope.fork(() -> replicaA.fetch("key"));
    scope.fork(() -> replicaB.fetch("key"));
    scope.fork(() -> replicaC.fetch("key"));
    scope.fork(() -> replicaD.fetch("key"));
    scope.fork(() -> replicaE.fetch("key"));

    scope.join();

    return scope.results();   // ilk 2 uğurlu (qalanlar cancel)
}
```

### 5) Nested scope — iyerarxiya

StructuredTaskScope nested ola bilər — inner scope bağlanmasa, outer də bağlanmır.

```java
public Dashboard loadDashboard(long userId) throws InterruptedException {
    try (var outer = new StructuredTaskScope.ShutdownOnFailure()) {

        var userTask = outer.fork(() -> userService.find(userId));

        var activityTask = outer.fork(() -> {
            // Inner scope — user-in çoxlu aktivliklərini paralel yüklə
            try (var inner = new StructuredTaskScope.ShutdownOnFailure()) {
                var loginsT = inner.fork(() -> loginHistory(userId));
                var ordersT = inner.fork(() -> orderHistory(userId));
                var viewsT = inner.fork(() -> pageViews(userId));

                inner.join();
                inner.throwIfFailed();

                return new Activity(loginsT.get(), ordersT.get(), viewsT.get());
            }
        });

        outer.join();
        outer.throwIfFailed();

        return new Dashboard(userTask.get(), activityTask.get());
    }
}
```

### 6) Timeout support

```java
try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {
    var userTask = scope.fork(() -> userService.find(42));
    var ordersTask = scope.fork(() -> orderService.findByUser(42));

    // Timeout ilə join
    scope.joinUntil(Instant.now().plus(Duration.ofSeconds(5)));
    scope.throwIfFailed();

    return new Result(userTask.get(), ordersTask.get());
} catch (TimeoutException e) {
    // 5 saniyə keçdi — bütün fork-lar cancel
    throw new ServiceUnavailableException("Timeout");
}
```

### 7) ScopedValue (JEP 464) — ThreadLocal alternativ

ThreadLocal problemləri:

```java
// Problem 1: mutable — kim isə set etsə, hər yerdə dəyişir
static final ThreadLocal<String> USER = new ThreadLocal<>();

USER.set("alice");
doSomething();         // "alice"
nested();              // "alice" — amma nested-də set olsa, hər yer dəyişdi
USER.get();            // bəlkə "bob" (leaked)

// Problem 2: memory leak — virtual thread-lər milyonlarla, hər biri öz map-ı
// Problem 3: inherited ThreadLocal child thread-də kopya olur (GC problem)
```

ScopedValue həlli:

```java
import java.lang.ScopedValue;

public class RequestContext {
    static final ScopedValue<String> USER = ScopedValue.newInstance();
    static final ScopedValue<String> TRACE_ID = ScopedValue.newInstance();

    public static void handleRequest(Request req) {
        ScopedValue
            .where(USER, req.userId())
            .where(TRACE_ID, req.traceId())
            .run(() -> processRequest(req));
        // bloka əsasən — scope-dan çıxanda dəyərlər "silinir"
    }

    static void processRequest(Request req) {
        String userId = USER.get();   // "alice"
        callService();
    }

    static void callService() {
        // ScopedValue auto-inherit olur child VT-də
        String traceId = TRACE_ID.get();
        log.info("Trace: {}", traceId);
    }
}
```

### 8) ScopedValue vs ThreadLocal — müqayisə

| Xüsusiyyət | ThreadLocal | ScopedValue |
|---|---|---|
| Mutability | `.set()` çağır | Immutable bir dəfə set |
| Scope | Thread ömrü | `run()` blok ömrü |
| Memory | Hər thread map saxlayır | Stack-də yaşayır |
| Virtual Threads | 1M VT × map = leak | 1M VT-də problemsiz |
| Child inheritance | `InheritableThreadLocal` (copy) | Avtomatik (immutable, shared) |
| Cleanup | `remove()` əl ilə | Avtomatik (scope close) |
| Override | `.set()` ilə | `.where()` nested scope |

### 9) ScopedValue + StructuredTaskScope kombinasiyası

```java
static final ScopedValue<String> REQUEST_ID = ScopedValue.newInstance();

public UserDto loadUser(long userId) throws Exception {
    return ScopedValue
        .where(REQUEST_ID, UUID.randomUUID().toString())
        .call(() -> {
            try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {
                var userTask = scope.fork(this::fetchUser);
                var ordersTask = scope.fork(this::fetchOrders);

                scope.join();
                scope.throwIfFailed();

                return new UserDto(userTask.get(), ordersTask.get());
            }
        });
}

private User fetchUser() {
    String reqId = REQUEST_ID.get();   // parent scope-dan inherit
    log.info("req={} fetching user", reqId);
    return userRepo.find(42);
}

private List<Order> fetchOrders() {
    String reqId = REQUEST_ID.get();   // eyni dəyər
    log.info("req={} fetching orders", reqId);
    return orderRepo.findByUser(42);
}
```

### 10) OpenTelemetry trace propagation

Modern observability ScopedValue ilə daha təmiz olur:

```java
static final ScopedValue<SpanContext> TRACE_CONTEXT = ScopedValue.newInstance();

public void handleHttp(Request req) {
    SpanContext parentSpan = extractFromHeaders(req.headers());

    ScopedValue.where(TRACE_CONTEXT, parentSpan).run(() -> {
        try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {
            scope.fork(() -> callServiceA());   // trace context inherit
            scope.fork(() -> callServiceB());
            scope.join();
        }
    });
}

void callServiceA() {
    SpanContext parent = TRACE_CONTEXT.get();
    Span span = tracer.spanBuilder("service-a")
        .setParent(Context.current().with(parent))
        .startSpan();
    try {
        apiClient.call("http://service-a");
    } finally {
        span.end();
    }
}
```

### 11) Spring Security 6.2+ ScopedValue support

```java
@Configuration
class SecurityConfig {
    @Bean
    SecurityContextHolderStrategy securityContextHolderStrategy() {
        return new ScopedValueSecurityContextHolderStrategy();
        // SecurityContext artıq ScopedValue-də saxlanır
    }
}

// Controller-də
@GetMapping("/me")
Mono<User> me() {
    // SecurityContext VT-lər arasında avtomatik propagate olur
    return Mono.just(SecurityContextHolder.getContext().getAuthentication());
}
```

---

## PHP-də istifadəsi

### 1) Klassik Promise::all() — ReactPHP

PHP-də structured concurrency native deyil. ReactPHP `Promise::all()` oxşar pattern verir:

```php
<?php
use React\Http\Browser;
use React\EventLoop\Loop;
use function React\Promise\all;

$browser = new Browser();

$userId = 42;

$promises = [
    'user'    => $browser->get("https://api/users/$userId"),
    'orders'  => $browser->get("https://api/orders?user=$userId"),
    'profile' => $browser->get("https://api/profiles/$userId"),
];

all($promises)->then(function (array $responses) {
    return [
        'user'    => json_decode($responses['user']->getBody()),
        'orders'  => json_decode($responses['orders']->getBody()),
        'profile' => json_decode($responses['profile']->getBody()),
    ];
})->catch(function (\Throwable $e) {
    // Problem: qalan 2 promise hələ də işləyir
    // Cancellation manuel etmək lazımdır
    error_log("Error: " . $e->getMessage());
});

Loop::run();
```

**Problemlər:**
- Biri xəta verdikdə digərləri davam edir (Java `ShutdownOnFailure`-un etdiyi şey)
- Manual cancellation kod yazmalısan
- Scope anlayışı yoxdur — callback hell yarada bilər

### 2) amphp v3 async/await + structured scope

amphp v3 Fiber üstündə sync-görünən async kod verir:

```bash
composer require amphp/amp:^3 amphp/http-client:^5
```

```php
<?php
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\CancelledException;
use function Amp\async;
use function Amp\Future\await;
use function Amp\Future\awaitFirst;
use function Amp\Future\awaitAny;

$client = HttpClientBuilder::buildDefault();

function loadUser(int $userId, $client): array
{
    // Structured: bir scope-da hamısını idarə et
    try {
        [$user, $orders, $profile] = await([
            async(fn() => fetchUser($userId, $client)),
            async(fn() => fetchOrders($userId, $client)),
            async(fn() => fetchProfile($userId, $client)),
        ]);

        return [
            'user'    => $user,
            'orders'  => $orders,
            'profile' => $profile,
        ];
    } catch (\Throwable $e) {
        // Birinin xətasında amphp digərlərini cancel etmir avtomatik
        // Manual cancellation lazım olsa:
        throw $e;
    }
}

function fetchUser(int $id, $client): array
{
    $response = $client->request(new Request("https://api/users/$id"));
    return json_decode($response->getBody()->buffer(), true);
}
```

### 3) amphp DeferredCancellation — cancellation

amphp v3-də cancellation token ilə manuel həll:

```php
<?php
use Amp\DeferredCancellation;
use Amp\Cancellation;
use function Amp\async;

function loadUserWithCancel(int $userId, $client): array
{
    $deferred = new DeferredCancellation();
    $cancel = $deferred->getCancellation();

    $futures = [
        async(fn() => fetchUser($userId, $client, $cancel)),
        async(fn() => fetchOrders($userId, $client, $cancel)),
        async(fn() => fetchProfile($userId, $client, $cancel)),
    ];

    try {
        return await($futures);
    } catch (\Throwable $e) {
        // Biri xəta verdi — qalanları cancel et
        $deferred->cancel($e);
        throw $e;
    }
}

function fetchUser(int $id, $client, Cancellation $cancel): array
{
    $response = $client->request(
        new Request("https://api/users/$id"),
        $cancel    // cancellation-aware request
    );
    return json_decode($response->getBody()->buffer(), true);
}
```

Bu manual amma Java `StructuredTaskScope` strukturuna bənzəyir — `DeferredCancellation` → `scope`, `getCancellation()` → scope token.

### 4) awaitFirst — ShutdownOnSuccess analogu

```php
<?php
use function Amp\Future\awaitFirst;
use function Amp\async;

function fetchFromAnyReplica(string $key, array $replicas): string
{
    $futures = array_map(
        fn($replica) => async(fn() => $replica->fetch($key)),
        $replicas
    );

    // İlk uğurlu cavab qayıdır, qalanları cancel olur
    return awaitFirst($futures);
}

// awaitAny — biri uğurlu olana qədər xətaları nəzərə alma
use function Amp\Future\awaitAny;

function fetchWithFallback(array $replicas): string
{
    $futures = array_map(
        fn($replica) => async(fn() => $replica->fetch()),
        $replicas
    );

    // Hamısı xəta versə exception, biri uğurlu olsa onu qaytar
    return awaitAny($futures);
}
```

### 5) Context propagation — Laravel container binding

Java ScopedValue analoqu PHP-də **DI container** binding ilə edilir. Laravel/Symfony-də "scoped" binding:

```php
<?php
// AppServiceProvider
public function register(): void
{
    // Request başına unikal dəyər
    $this->app->scoped(RequestContext::class, function ($app) {
        return new RequestContext(
            traceId: request()->header('X-Trace-Id') ?? (string) Str::uuid(),
            userId: auth()->id(),
            tenantId: request()->header('X-Tenant'),
        );
    });
}
```

```php
<?php
// RequestContext.php
class RequestContext
{
    public function __construct(
        public readonly string $traceId,
        public readonly ?int $userId,
        public readonly ?string $tenantId,
    ) {}
}

// Controller-də
class OrderController
{
    public function show(int $id, RequestContext $ctx): JsonResponse
    {
        Log::info("Order fetch", [
            'trace_id' => $ctx->traceId,
            'user_id'  => $ctx->userId,
        ]);
        return response()->json($this->service->find($id));
    }
}

// Service-də də eyni dəyər
class OrderService
{
    public function __construct(private RequestContext $ctx) {}

    public function find(int $id): Order
    {
        $order = Order::find($id);
        // $this->ctx->traceId eyni Controller-dəki kimi
        return $order;
    }
}
```

Fərq: **scoped binding per-request işləyir, amma coroutine-lər arasında inherit OLMUR**. Swoole coroutine-də əl ilə ötürməlisən.

### 6) Swoole Context — per-coroutine storage

Swoole-da `Coroutine::getContext()` hər coroutine üçün ayrı storage verir — ScopedValue-yə yaxındır:

```php
<?php
use Swoole\Coroutine;

function handleRequest(Request $req): void
{
    $ctx = Coroutine::getContext();
    $ctx['trace_id'] = $req->header['x-trace-id'] ?? Str::uuid();
    $ctx['user_id']  = extractUser($req);

    // Child coroutine yaradanda context inherit OLMUR
    // Əl ilə ötürmək lazımdır
    $parentCtx = Coroutine::getContext();

    Coroutine::create(function () use ($parentCtx) {
        $myCtx = Coroutine::getContext();
        $myCtx['trace_id'] = $parentCtx['trace_id'];  // manual inherit

        processOrderAsync();
    });
}

function processOrderAsync(): void
{
    $traceId = Coroutine::getContext()['trace_id'] ?? null;
    log::info("Processing", ['trace' => $traceId]);
}
```

### 7) Octane Concurrently — ShutdownOnFailure-a bənzər

Laravel Octane paralel coroutine `concurrently()`:

```php
<?php
use Laravel\Octane\Facades\Octane;

class DashboardController
{
    public function show(int $userId)
    {
        try {
            [$user, $orders, $profile] = Octane::concurrently([
                fn() => User::find($userId),
                fn() => Order::where('user_id', $userId)->get(),
                fn() => Profile::where('user_id', $userId)->first(),
            ], 3000);   // 3s timeout

            return view('dashboard', compact('user', 'orders', 'profile'));
        } catch (\Throwable $e) {
            // Biri xəta versə, timeout olsa — bütün tapşırıqlar ləğv olur
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

Daxildə Swoole coroutine + WaitGroup + timeout istifadə edir.

### 8) Symfony HttpClient — async batch

Symfony HttpClient `stream()` + batch sorğu:

```php
<?php
use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();

$responses = [];
$responses[] = $client->request('GET', 'https://api/users/42');
$responses[] = $client->request('GET', 'https://api/orders?user=42');
$responses[] = $client->request('GET', 'https://api/profiles/42');

$data = [];
foreach ($client->stream($responses) as $response => $chunk) {
    if ($chunk->isLast()) {
        $data[] = $response->toArray();
    }
}

// Problem: biri timeout olsa, digərlərini cancel etmək üçün manual kod
```

### 9) ReactPHP Promise::race, Promise::any

```php
<?php
use function React\Promise\race;
use function React\Promise\any;

// race — ilk cavab (uğurlu və ya xəta)
$winner = race([
    $browser->get('https://replica-a/data'),
    $browser->get('https://replica-b/data'),
    $browser->get('https://replica-c/data'),
]);

$winner->then(fn($r) => doSomething($r));

// any — ilk uğurlu cavab (xətaları ignore et)
$first = any([
    $browser->get('https://replica-a/data'),
    $browser->get('https://replica-b/data'),
    $browser->get('https://replica-c/data'),
]);
```

**Kritik fərq:** race/any sonra qalan promise-ləri **avtomatik cancel etmir** — onlar arxa planda davam edir. Java `ShutdownOnSuccess` avtomatik cancel edir.

### 10) Fiber manual scope pattern

Fiber native istifadə olunanda, "scope" özümüz yazırıq:

```php
<?php
class TaskScope
{
    private array $fibers = [];
    private array $results = [];
    private array $errors = [];

    public function fork(callable $task): void
    {
        $fiber = new Fiber($task);
        $fiber->start();
        $this->fibers[] = $fiber;
    }

    public function join(): void
    {
        while (!empty($this->fibers)) {
            foreach ($this->fibers as $i => $fiber) {
                if ($fiber->isTerminated()) {
                    try {
                        $this->results[] = $fiber->getReturn();
                    } catch (\Throwable $e) {
                        $this->errors[] = $e;
                    }
                    unset($this->fibers[$i]);
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            }
        }
    }

    public function results(): array { return $this->results; }
    public function errors(): array  { return $this->errors; }
}

// İstifadə
$scope = new TaskScope();
$scope->fork(function () { return fetchUser(42); });
$scope->fork(function () { return fetchOrders(42); });
$scope->fork(function () { return fetchProfile(42); });
$scope->join();

if (!empty($scope->errors())) {
    throw $scope->errors()[0];
}
[$user, $orders, $profile] = $scope->results();
```

Bu sadə implementasiyadır — real scheduler (amphp) olmadan I/O async işləməz.

---

## Əsas fərqlər

| Xüsusiyyət | Java (Loom + ScopedValue) | PHP (Fiber + amphp/React) |
|---|---|---|
| Structured concurrency | `StructuredTaskScope` (JEP 453) native | amphp `await()`, React `all()` — scope yoxdur |
| ShutdownOnFailure | Hazır sinif | Manual DeferredCancellation |
| ShutdownOnSuccess | Hazır sinif | amphp `awaitFirst`, React `race` |
| Custom policy | `StructuredTaskScope` subclass | Manual fiber loop |
| Avtomatik cancellation | Scope close → bütün fork cancel | Manual cancel çağırmalısan |
| Nested scope | Inner bağlanmasa outer bağlanmır | Manual nested Promise |
| Timeout | `scope.joinUntil()` native | amphp `Timeout`, React `timeout()` |
| Context storage | ScopedValue (JEP 464) | Swoole Context, DI container |
| Immutable context | Bəli, ScopedValue | Container scoped binding mutable |
| Child inheritance | Avtomatik (ScopedValue) | Manual ötürmə |
| ThreadLocal alternative | ScopedValue | Swoole Context per-coroutine |
| Integration | Spring Security 6.2+, OpenTelemetry | Laravel Octane `concurrently()` |
| Error aggregation | `throwIfFailed()` | Manual foreach errors |
| Debugging | Thread dump struct-i göstərir | Stack trace fiber-də mürəkkəb |

---

## Niyə belə fərqlər var?

**Structured programming bir addım irəli.** Java Loom + StructuredTaskScope "structured concurrency" akademik nəzəriyyəni (Nathaniel J. Smith-in "Notes on structured concurrency, or: Go statement considered harmful") standart API-yə çevirdi. PHP-də hələ bu səviyyə abstraksiya yoxdur — ekosistem parçalanmışdır (amphp/React/Swoole ayrı), standart API yoxdur.

**Cancellation propagation dilin dəstəyi.** Java-da `Thread.interrupt()` və Virtual Thread tərəfindən avtomatik yayılır. PHP-də Fiber-də "cancel" anlayışı yoxdur — əl ilə `DeferredCancellation` token ötürmək lazımdır. Bu əlavə kod və xəta riskidir.

**Context propagation mental model fərqi.** Java `ThreadLocal` və `ScopedValue` thread-oriented-dir — hər thread öz storage-ı. PHP-də tradition "request-oriented" idi (hər sorğu ayrı proses) — container binding request ömrü boyunca yaşayır. Coroutine gələndə bu model pozuldu — Swoole Context verdi, amma standart deyil.

**Immutability strong vs weak.** ScopedValue immutable — bir dəfə set olur, nested block-da override olur, amma parent-də dəyişmir. Bu, "lokal düşünmə"-ni asanlaşdırır — kod oxuyanda "burada dəyər nə?" sualı tək bir yerdə cavablanır. PHP container binding mutable — kim isə `app()->instance()` ilə dəyişə bilər.

**Ecosystem uğuru.** Spring Security, OpenTelemetry, Micrometer kimi Java kitabxanaları ScopedValue-yə keçir — standart var, hamı ona uyğunlaşır. PHP-də Laravel, Symfony, amphp, Swoole hər biri fərqli context API istifadə edir — universal propagation yoxdur.

**Enterprise sürəti.** Java ekosistemi böyükdür, JEP prosesi uzun-müddətli planlama verir. PHP-də Fiber 8.1-də gəldi, hələ ekosistem ona öyrəşir — Symfony/Laravel integration bir-bir gəlir.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- `StructuredTaskScope` native API (JEP 453)
- `ShutdownOnFailure`, `ShutdownOnSuccess` built-in policy
- Custom scope subclass (`handleComplete()` override)
- `scope.joinUntil(deadline)` timeout
- `ScopedValue.where().run()` (JEP 464)
- Immutable context per scope
- Child VT-də avtomatik ScopedValue inheritance
- `scope.throwIfFailed()` error aggregation
- Nested scope iyerarxiya
- Spring Security 6.2+ ScopedValue backend
- OpenTelemetry trace propagation via ScopedValue
- Thread dump-da struct göstərilir

**Yalnız PHP-də:**
- Fiber (PHP 8.1+) — manual suspend/resume
- amphp v3 `async()`, `await()`, `awaitFirst()`, `awaitAny()`
- ReactPHP Promise (all, race, any)
- Swoole Coroutine\WaitGroup, Coroutine\Channel
- Swoole Context (per-coroutine storage)
- Laravel Octane `Octane::concurrently()`
- Laravel container `scoped()` binding
- `DeferredCancellation` (amphp manual cancel)
- Revolt event loop (amphp ilə paylaşıla bilər)
- Container-based context propagation

---

## Best Practices

**Java tərəfdə:**
- `CompletableFuture.allOf` əvəzinə `StructuredTaskScope.ShutdownOnFailure` istifadə et
- Paralel API call-lar üçün `ShutdownOnSuccess` + replica-lar
- Custom policy üçün `StructuredTaskScope` subclass et (`handleComplete()` override)
- Scope-u `try-with-resources` ilə aç — orphan thread riski yox
- `joinUntil()` ilə timeout qoy — infinite wait qarşısı
- Nested scope-larda inner bağlanmasa, outer da bağlanmır — care
- ThreadLocal əvəzinə ScopedValue (xüsusilə milyon VT-də)
- ScopedValue immutable — mutable kontekst üçün container obyekt
- Trace ID, user ID, tenant — ScopedValue-də propagate et
- Spring Security `ScopedValueSecurityContextHolderStrategy` — modern
- Preview API olduğu üçün `--enable-preview` flag (Java 21-23)
- Java 24 target-da stable olur — production istifadə üçün hazır

**PHP tərəfdə:**
- ReactPHP Promise::all() yetərsizdir — manual cancellation əlavə et
- amphp v3 + DeferredCancellation — structured concurrency yaxın
- `awaitFirst()` ShutdownOnSuccess analogu — replica pattern üçün
- Laravel Octane `concurrently()` paralel API call (timeout ilə)
- Swoole Context per-coroutine — request-scoped data üçün
- Child coroutine-yə context əl ilə ötür (auto-inherit yoxdur)
- Container `scoped()` binding — request ömrü üçün (PHP-FPM)
- Octane-də `scoped()` mütləq lazım — `singleton` sızır
- OpenTelemetry PHP SDK — trace context header-də ötürülür
- Laravel `Log::withContext()` ilə structured logging
- Swoole-də global state yaratma — coroutine-lər arasında paylaşılır
- Fiber manual istifadə etmə — amphp/ReactPHP/Swoole scheduler lazımdır

---

## Yekun

- Structured Concurrency (JEP 453, Java 21 preview) — `StructuredTaskScope` ilə fork/join/cancel idarə olunur
- `ShutdownOnFailure` — biri xəta versə hamısı cancel. `ShutdownOnSuccess` — ilk uğur qalıb digərləri cancel
- `StructuredTaskScope` subclass ilə custom policy (quorum, percentile və s.)
- `scope.joinUntil(deadline)` timeout support, try-with-resources avtomatik cleanup
- `CompletableFuture.allOf` "lost thread", error aggregation, cancellation problemlərini həll etmir — Structured Concurrency həll edir
- Scoped Values (JEP 464) — immutable, bounded, inherit olan context (ThreadLocal alternativi)
- ScopedValue milyonlarla VT-də yaddaş şişməsi yaratmır (ThreadLocal yaradırdı)
- ScopedValue + StructuredTaskScope kombinasiyası — trace ID, user ID propagate
- Spring Security 6.2+, OpenTelemetry ScopedValue-yə keçir
- PHP-də native structured concurrency API yoxdur — amphp, ReactPHP, Swoole fərqli həllər
- amphp v3 `async()` + `await()` Fiber üstündə sync-görünən async
- amphp `awaitFirst()` ShutdownOnSuccess analogu, manual `DeferredCancellation` cancel üçün
- ReactPHP Promise::all/race/any — auto-cancel yoxdur, manual lazımdır
- Laravel Octane `concurrently()` paralel coroutine (Swoole əsaslı)
- Swoole Context per-coroutine storage — ScopedValue-ə yaxın, amma child inherit manual
- Context propagation PHP-də DI container scoped() binding ilə (request-level)
- Java 24 target-da Structured Concurrency + ScopedValue stable olur
