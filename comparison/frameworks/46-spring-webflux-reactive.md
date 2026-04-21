# Spring WebFlux (Reactive) — Dərin Müqayisə

## Giriş

Klassik Spring MVC **servlet stack** üzərində qurulub: hər HTTP sorğu bir thread tutur. Əgər thread DB və ya xarici API gözləyirsə, həmin thread blok olur. 200 paralel sorğu = 200 blok thread. Bu, "thread-per-request" modelidir.

Spring **WebFlux** isə **reactive stack**-dır. Netty (və ya Undertow, Jetty) üzərində işləyir, event loop modeli istifadə edir. Bir neçə thread çoxlu sorğunu eyni anda idarə edə bilir — çünki thread I/O-da blok olmur, `onNext` callback-i ilə davam edir. Core-lar sayı qədər thread kifayətdir.

Laravel tərəfində isə "reactive stack" yoxdur. Laravel hər sorğu üçün PHP-FPM prosesi istifadə edir (sinxron). **Octane + Swoole** worker-ləri uzun-ömürlü saxlayır və coroutine-lər ilə concurrent sorğular idarə edir, amma bu "reactive streams" deyil. Parallel HTTP çağırışları üçün `Http::pool()`, real-time üçün **Reverb WebSocket**, streaming üçün **StreamedResponse** var.

---

## Spring-də istifadəsi

### 1) WebFlux starter — Netty üzərində

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-webflux</artifactId>
</dependency>
<dependency>
    <groupId>io.projectreactor</groupId>
    <artifactId>reactor-test</artifactId>
    <scope>test</scope>
</dependency>
```

```yaml
# application.yml
spring:
  main:
    web-application-type: reactive
  webflux:
    base-path: /api
server:
  port: 8080
  netty:
    connection-timeout: 5s
    idle-timeout: 30s
```

WebFlux starter əlavə edildikdə Spring Boot avtomatik Netty server-i işə salır. `spring-boot-starter-web` (Tomcat) ilə **yanaşı saxlamamalısan** — yalnız biri olmalıdır.

### 2) Reactor əsasları — Mono və Flux

Reactor library iki əsas tip verir:

- **`Mono<T>`** — 0 və ya 1 element. Məsələn, bir user qaytaran endpoint.
- **`Flux<T>`** — 0, 1, N, hətta sonsuz axın. Məsələn, stream, SSE, listing.

```java
Mono<User> userMono = Mono.just(new User("ali"));
Mono<User> emptyMono = Mono.empty();
Mono<User> errorMono = Mono.error(new RuntimeException("yoxdur"));

Flux<Integer> numbers = Flux.range(1, 100);
Flux<String> names = Flux.fromIterable(List.of("ali", "veli", "nigar"));
Flux<Long> ticks = Flux.interval(Duration.ofSeconds(1));   // sonsuz axın
```

Vacib qayda: **subscribe olmasa heç nə işləmir**. Reactor "lazy"-dir. Spring WebFlux framework subscribe-i özü edir, sənin kodun yalnız Publisher qaytarır.

### 3) Operator-lar — map, flatMap, filter, zip

```java
@Service
public class UserService {

    private final UserRepository repo;
    private final ProfileClient profileClient;

    public Mono<UserDto> findUser(Long id) {
        return repo.findById(id)                              // Mono<User>
            .filter(u -> u.isActive())                        // yalnız aktiv
            .switchIfEmpty(Mono.error(new NotFoundException()))
            .map(u -> new UserDto(u.getId(), u.getName()))    // sync transform
            .flatMap(this::enrichWithProfile)                 // async transform
            .timeout(Duration.ofSeconds(3))
            .onErrorResume(TimeoutException.class, e -> Mono.just(UserDto.empty()));
    }

    private Mono<UserDto> enrichWithProfile(UserDto dto) {
        return profileClient.fetch(dto.id())
            .map(profile -> dto.withProfile(profile));
    }

    public Flux<UserDto> listActive() {
        return repo.findByActiveTrue()                        // Flux<User>
            .map(u -> new UserDto(u.getId(), u.getName()))
            .take(100);                                       // yalnız ilk 100
    }

    // Paralel çağırışlar — zip
    public Mono<Dashboard> dashboard(Long userId) {
        return Mono.zip(
            repo.findById(userId),
            orderClient.recent(userId),
            notificationClient.unread(userId)
        ).map(tuple -> new Dashboard(
            tuple.getT1(),      // User
            tuple.getT2(),      // List<Order>
            tuple.getT3()       // Integer
        ));
    }
}
```

**`map` vs `flatMap` fərqi** çox vacibdir:

- `map(fn)` — sinxron transform. `User -> UserDto`.
- `flatMap(fn)` — async transform. `User -> Mono<UserDto>`. Daxildəki Mono-nu "yastılayır".

Əgər `flatMap` əvəzinə `map` istifadə etsən və funksiya Mono qaytarsa, nəticə `Mono<Mono<UserDto>>` olar — səhvdir.

### 4) Annotated controllers — MVC-yə bənzər

```java
@RestController
@RequestMapping("/users")
public class UserController {

    private final UserService service;

    @GetMapping("/{id}")
    public Mono<UserDto> findOne(@PathVariable Long id) {
        return service.findUser(id);
    }

    @GetMapping
    public Flux<UserDto> list() {
        return service.listActive();
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public Mono<UserDto> create(@RequestBody @Valid Mono<CreateUserRequest> body) {
        return body.flatMap(service::create);
    }
}
```

Controller MVC-yə çox bənzəyir — fərq yalnız qaytarılan tip-in `Mono`/`Flux` olmasıdır. `@Valid`, `@RequestBody`, `@PathVariable` eyni işləyir.

### 5) Functional endpoints — RouterFunction

Annotated controllers əvəzinə DSL stili də var:

```java
@Configuration
public class UserRoutes {

    @Bean
    public RouterFunction<ServerResponse> routes(UserHandler handler) {
        return route()
            .path("/users", builder -> builder
                .GET("/{id}", handler::findOne)
                .GET("", handler::list)
                .POST("", accept(APPLICATION_JSON), handler::create)
                .DELETE("/{id}", handler::delete)
            )
            .filter(new LoggingFilter())
            .build();
    }
}

@Component
public class UserHandler {

    private final UserService service;

    public Mono<ServerResponse> findOne(ServerRequest req) {
        Long id = Long.parseLong(req.pathVariable("id"));
        return service.findUser(id)
            .flatMap(dto -> ServerResponse.ok().bodyValue(dto))
            .switchIfEmpty(ServerResponse.notFound().build());
    }

    public Mono<ServerResponse> list(ServerRequest req) {
        return ServerResponse.ok().body(service.listActive(), UserDto.class);
    }

    public Mono<ServerResponse> create(ServerRequest req) {
        return req.bodyToMono(CreateUserRequest.class)
            .flatMap(service::create)
            .flatMap(dto -> ServerResponse.status(CREATED).bodyValue(dto));
    }
}
```

Functional endpoints kod sınaqdan keçirmə tərəfdən daha asandır və annotated variantdan daha "explicit"-dir. Annotated daha tanış gəlir, ona görə komandalar əsasən annotated seçir.

### 6) Backpressure — request(n), buffer, drop

Backpressure: producer data-nı consumer-dən sürətli çıxarırsa nə etmək? Reactor `Flux` default `request(Long.MAX_VALUE)` qaytarır — yəni bütün data. Amma bu yaddaşı partlada bilər.

```java
Flux<Event> events = eventStream.fetch();

// 1) Buffer — bloklar halında
events.buffer(100)                            // 100-lük list
    .flatMap(batch -> processBatch(batch));

// 2) Drop — consumer gecikərsə data atıl
events.onBackpressureDrop(e -> log.warn("dropped {}", e.id()));

// 3) Latest — yalnız ən yeni
events.onBackpressureLatest();

// 4) Error — pressure olsa exception
events.onBackpressureError();

// 5) Limit rate — hər request N qədər
events.limitRate(50);                         // hər dəfə 50 element istə
```

Məsələn, Kafka-dan saniyədə 10k mesaj gəlir, DB yalnız 1k yaza bilir. `onBackpressureDrop` məqbul olmur — `limitRate` + batch insert daha doğru seçimdir.

### 7) WebClient — async HTTP client

`RestTemplate` blok-sinxrondur, WebFlux-ə uyğun deyil. `WebClient` ise non-blocking:

```java
@Configuration
public class ClientConfig {

    @Bean
    public WebClient paymentClient(WebClient.Builder builder) {
        return builder
            .baseUrl("https://api.payment.com")
            .defaultHeader(HttpHeaders.AUTHORIZATION, "Bearer " + apiKey)
            .clientConnector(new ReactorClientHttpConnector(
                HttpClient.create()
                    .responseTimeout(Duration.ofSeconds(5))
                    .option(ChannelOption.CONNECT_TIMEOUT_MILLIS, 2000)))
            .filter(logRequest())
            .filter(retry())
            .build();
    }
}

@Service
public class PaymentService {

    private final WebClient client;

    public Mono<PaymentResponse> charge(ChargeRequest req) {
        return client.post()
            .uri("/charges")
            .contentType(APPLICATION_JSON)
            .bodyValue(req)
            .retrieve()
            .onStatus(HttpStatusCode::is4xxClientError,
                resp -> resp.bodyToMono(ErrorBody.class)
                    .map(err -> new PaymentException(err.code())))
            .onStatus(HttpStatusCode::is5xxServerError,
                resp -> Mono.error(new UpstreamException()))
            .bodyToMono(PaymentResponse.class)
            .retryWhen(Retry.backoff(3, Duration.ofMillis(500))
                .filter(ex -> ex instanceof UpstreamException))
            .timeout(Duration.ofSeconds(10));
    }

    // Stream cavab
    public Flux<Event> streamEvents() {
        return client.get()
            .uri("/events/stream")
            .accept(TEXT_EVENT_STREAM)
            .retrieve()
            .bodyToFlux(Event.class);
    }
}
```

Spring 6.1+ `RestClient` adlı yeni sinxron client də təqdim edir — `RestTemplate`-in modern əvəzi. Fluent API-si WebClient-ə bənzəyir, amma blokinqdir (MVC üçün). Yəni indi seçim budur:

- `RestClient` — sinxron, modern API (MVC üçün)
- `WebClient` — async, reactive (WebFlux üçün)
- `RestTemplate` — köhnə, yeni kod üçün tövsiyə olunmur

### 8) R2DBC — reactive SQL

JDBC blok-sinxrondur. Reactive tətbiqdə JDBC çağırsan — thread bloklanır, WebFlux-un üstünlüyü gedir. **R2DBC** (Reactive Relational Database Connectivity) non-blocking SQL driver-dir. PostgreSQL, MySQL, MSSQL, H2 dəstəklənir.

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-r2dbc</artifactId>
</dependency>
<dependency>
    <groupId>org.postgresql</groupId>
    <artifactId>r2dbc-postgresql</artifactId>
</dependency>
```

```yaml
spring:
  r2dbc:
    url: r2dbc:postgresql://localhost:5432/app
    username: app
    password: secret
    pool:
      initial-size: 5
      max-size: 20
```

```java
public interface UserRepository extends R2dbcRepository<User, Long> {
    Flux<User> findByActiveTrue();
    Mono<User> findByEmail(String email);

    @Query("SELECT * FROM users WHERE created_at > :since")
    Flux<User> recentUsers(LocalDateTime since);
}

@Service
public class UserService {
    public Mono<User> register(RegisterRequest req) {
        return repo.findByEmail(req.email())
            .flatMap(existing -> Mono.<User>error(new AlreadyExists()))
            .switchIfEmpty(Mono.defer(() -> repo.save(new User(req.email()))));
    }
}
```

R2DBC limit: JPA/Hibernate yoxdur. Lazy loading, dirty checking, `@OneToMany` kimi ORM feature-lər olmur. Yalnız query və template əsaslı yanaşma.

### 9) Error handling — onErrorMap, onErrorResume

```java
public Mono<Order> placeOrder(OrderRequest req) {
    return inventory.reserve(req)
        .flatMap(reservation -> payment.charge(req.amount()))
        .flatMap(charge -> orderRepo.save(new Order(req, charge)))
        .onErrorMap(SQLException.class,
            e -> new DataAccessException("DB xətası", e))   // exception transform
        .onErrorResume(PaymentException.class,
            e -> handlePaymentFailure(req, e))              // fallback Mono
        .onErrorReturn(NetworkException.class, Order.failed())   // sabit dəyər
        .doOnError(e -> log.error("Order failed", e))
        .doFinally(signal -> metrics.record(signal));
}

// Global error handler
@Component
@Order(-2)
public class GlobalErrorHandler implements WebExceptionHandler {

    @Override
    public Mono<Void> handle(ServerWebExchange exchange, Throwable ex) {
        HttpStatus status = resolveStatus(ex);
        ErrorResponse body = new ErrorResponse(ex.getMessage(), status.value());

        exchange.getResponse().setStatusCode(status);
        exchange.getResponse().getHeaders().setContentType(APPLICATION_JSON);

        byte[] bytes = toJson(body);
        return exchange.getResponse().writeWith(
            Mono.just(exchange.getResponse().bufferFactory().wrap(bytes)));
    }
}
```

Alternativ: `@RestControllerAdvice` + `@ExceptionHandler` də WebFlux-da işləyir.

### 10) Server-Sent Events (SSE)

```java
@GetMapping(value = "/prices", produces = MediaType.TEXT_EVENT_STREAM_VALUE)
public Flux<PriceUpdate> prices() {
    return Flux.interval(Duration.ofSeconds(1))
        .map(tick -> priceService.current())
        .map(price -> new PriceUpdate(price.symbol(), price.value()));
}

@GetMapping(value = "/chat/{room}", produces = MediaType.TEXT_EVENT_STREAM_VALUE)
public Flux<ServerSentEvent<Message>> chat(@PathVariable String room) {
    return chatService.stream(room)
        .map(msg -> ServerSentEvent.<Message>builder()
            .id(msg.getId())
            .event("message")
            .retry(Duration.ofSeconds(5))
            .data(msg)
            .build());
}
```

SSE tək istiqamətlidir (server → client). Real-time qiymətlər, notifikasiyalar, live feed üçün idealdır. WebSocket əvəzinə çox vaxt SSE kifayət edir.

### 11) WebSocket

```java
@Configuration
public class WebSocketConfig {
    @Bean
    public HandlerMapping handlerMapping(ChatHandler handler) {
        Map<String, WebSocketHandler> map = Map.of("/ws/chat", handler);
        return new SimpleUrlHandlerMapping(map, -1);
    }
}

@Component
public class ChatHandler implements WebSocketHandler {
    @Override
    public Mono<Void> handle(WebSocketSession session) {
        Flux<String> input = session.receive()
            .map(WebSocketMessage::getPayloadAsText)
            .doOnNext(msg -> log.info("rx: {}", msg));

        Flux<WebSocketMessage> output = chatService.subscribe()
            .map(msg -> session.textMessage(toJson(msg)));

        return session.send(output).and(input);
    }
}
```

### 12) StepVerifier ilə test

```java
@Test
void shouldReturnUser() {
    Mono<UserDto> result = service.findUser(1L);

    StepVerifier.create(result)
        .expectNextMatches(u -> u.name().equals("ali"))
        .verifyComplete();
}

@Test
void shouldEmitThreeEvents() {
    Flux<Event> stream = service.eventStream()
        .take(3);

    StepVerifier.create(stream)
        .expectNextCount(3)
        .verifyComplete();
}

@Test
void shouldTimeoutAfterThreeSeconds() {
    StepVerifier.withVirtualTime(() -> service.slowCall())
        .expectSubscription()
        .thenAwait(Duration.ofSeconds(3))
        .expectError(TimeoutException.class)
        .verify();
}
```

`StepVerifier` reactive stream-ləri test etmək üçün standart alətdir. Virtual time imkanı var — real 3 saniyə gözləməyə ehtiyac yoxdur.

### 13) Virtual Threads — WebFlux-a alternativ

Java 21 Virtual Threads gətirdi. `spring-boot 3.2+`-da:

```yaml
spring:
  threads:
    virtual:
      enabled: true
```

Bu flag ilə **Tomcat + MVC** istifadə edib, hər sorğu virtual thread-də işləyir. Thread blok olsa belə OS thread azad qalır. Yəni sinxron, blokinq kodla yüksək konkurentlik alırsan — Reactor öyrənmədən.

Nə vaxt WebFlux, nə vaxt Virtual Threads?

- **Virtual Threads**: Əgər sən sadə CRUD, JPA, sinxron kod yazırsansa, Virtual Threads kifayətdir. Kod oxunaqlı qalır.
- **WebFlux**: Stream, backpressure, SSE, geniş reactive kompozisiya (zip, merge, buffer) lazımdırsa. Reactor operator-ları Virtual Threads-də yoxdur.

Yəni WebFlux üstünlüyü indi daha dardır. Yeni layihələr əksər hallarda MVC + Virtual Threads seçir.

---

## Laravel-də istifadəsi

### 1) Standart sinxron model

Laravel-da default PHP-FPM istifadə olunur. Hər HTTP sorğu:

1. Nginx/Apache → PHP-FPM worker
2. Worker Laravel-i boot edir
3. Request emal olunur
4. Cavab qaytarılır
5. Worker "cleanup" edir, sonrakı sorğunu gözləyir

Bu "shared-nothing" modeldir. Paralelizm = daha çox PHP-FPM worker.

```nginx
# Tipik PHP-FPM pool
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
```

Yəni 50 sorğu eyni anda — amma hər biri başqa proses, heç bir shared state yox. DB pooling yalnız proses daxilində, Redis bağlantı hər sorğuda yenidən qurulur (persistent connection açıla bilər).

### 2) Octane — long-running worker (Swoole/RoadRunner/FrankenPHP)

```bash
composer require laravel/octane
php artisan octane:install --server=swoole
php artisan octane:start --workers=8 --task-workers=4
```

```php
// config/octane.php
return [
    'server' => env('OCTANE_SERVER', 'swoole'),
    'https' => env('OCTANE_HTTPS', false),
    'warm' => [
        ...Octane::defaultServicesToWarm(),
    ],
    'flush' => [
        // Hər sorğudan sonra təmizlənəcək
    ],
    'tables' => [
        'example:1000' => [
            'name' => 'string:1000',
            'votes' => 'int',
        ],
    ],
    'cache' => [
        'rows' => 1000,
        'bytes' => 10000,
    ],
];
```

Octane ilə:

- Framework **bir dəfə** boot olunur, yaddaşda qalır
- Cavab sürəti 3-5 qat artır
- Swoole **coroutine**-lər gətirir — eyni worker daxilində eyni anda bir neçə sorğu emal olunur (I/O gözləyərkən)
- Concurrent HTTP çağırışları üçün `Octane::concurrently()` var

```php
use Laravel\Octane\Facades\Octane;

[$user, $orders, $profile] = Octane::concurrently([
    fn () => User::find($id),
    fn () => Order::where('user_id', $id)->get(),
    fn () => Http::get("https://profile.api/{$id}")->json(),
]);
```

Bu WebFlux `Mono.zip()`-ə ən yaxın analoqdur. Amma altında coroutine işləyir, reactive stream yoxdur.

### 3) `Http::pool()` — paralel HTTP çağırışlar

```php
use Illuminate\Http\Client\Pool;

$responses = Http::pool(fn (Pool $pool) => [
    $pool->as('user')->get("https://api.example.com/users/{$id}"),
    $pool->as('orders')->get("https://api.example.com/orders?user={$id}"),
    $pool->as('wallet')->get("https://api.example.com/wallets/{$id}"),
]);

return [
    'user'   => $responses['user']->json(),
    'orders' => $responses['orders']->json(),
    'wallet' => $responses['wallet']->json(),
];
```

Bu Guzzle-ın `Promise::all()` üzərindədir. Hər çağırış eyni vaxtda başlayır, hamısı tamamlananda qaytarılır. WebClient + `Mono.zip` pattern-inin ekvivalentidir.

### 4) Lazy Collections + Generators — stream-ə ən yaxın

Laravel-də "reactive stream" yoxdur, amma böyük data-nı yaddaşa yığmamaq üçün **LazyCollection** və generator var.

```php
use Illuminate\Support\LazyCollection;

LazyCollection::make(function () {
    $handle = fopen('big-file.csv', 'r');
    while (($line = fgets($handle)) !== false) {
        yield str_getcsv($line);
    }
    fclose($handle);
})
->chunk(1000)
->each(function ($chunk) {
    User::insert($chunk->toArray());
});

// DB cursor — hər row ayrı fetch
User::where('active', true)
    ->lazy()            // lazy() və ya cursor()
    ->each(function (User $user) {
        dispatch(new SyncUser($user));
    });
```

`lazy()` altda chunk-əsaslı query işlədir (default 1000). `cursor()` isə PDO `PDOStatement::fetch()` ilə hər row-u ayrı çəkir — ən az yaddaş.

Bu Reactor `Flux`-a oxşar baxımdan (tək-tək element, yaddaşa yığılmır), amma backpressure, operator kompozisiyası (flatMap, zip), async I/O yoxdur.

### 5) StreamedResponse — SSE və böyük download

```php
// routes/web.php
Route::get('/prices', function () {
    return response()->stream(function () {
        while (true) {
            $price = PriceService::current();
            echo "data: " . json_encode($price) . "\n\n";
            ob_flush();
            flush();
            sleep(1);
        }
    }, 200, [
        'Content-Type'  => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
});
```

Problem: **standart PHP-FPM worker**-ı bu sorğu bloklayır. 50 SSE connection = 50 worker tutulur. Buna görə SSE tətbiqləri Octane + Swoole üzərində işlədilməlidir. Ya da Reverb WebSocket istifadə olunmalıdır.

Octane ilə variant:

```php
Route::get('/events', function () {
    return new StreamedResponse(function () {
        Event::cursor()->each(function ($event) {
            echo "event: {$event->type}\n";
            echo "data: " . json_encode($event) . "\n\n";
            ob_flush();
            flush();
        });
    }, 200, ['Content-Type' => 'text/event-stream']);
});
```

### 6) Broadcasting + Reverb — real-time

Laravel-in real-time strategiyası **broadcasting**-dir: event JS client-ə WebSocket üzərindən push olunur.

```php
// config/broadcasting.php
'default' => env('BROADCAST_CONNECTION', 'reverb'),
'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key'    => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host'   => env('REVERB_HOST', 'localhost'),
            'port'   => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],
    ],
],
```

```bash
composer require laravel/reverb
php artisan reverb:install
php artisan reverb:start
```

```php
// app/Events/OrderShipped.php
class OrderShipped implements ShouldBroadcast
{
    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->order->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'order.shipped';
    }
}

// controller
event(new OrderShipped($order));
```

JS tərəfi:

```js
Echo.private(`user.${userId}`)
    .listen('.order.shipped', (e) => console.log(e.order));
```

Reverb Laravel-in rəsmi WebSocket server-idir (PHP-də yazılıb, Swoole üzərində). Spring WebFlux WebSocket handler-inin ekvivalenti budur — amma ayrıca proses kimi işləyir.

### 7) Queues ilə async — sinxron app içində

Laravel-də "async endpoint" anlayışı yoxdur. Bunun əvəzinə HTTP endpoint dərhal cavab qaytarır, iş queue-ya atılır:

```php
Route::post('/reports', function (Request $req) {
    $report = Report::create(['status' => 'pending']);
    dispatch(new GenerateReport($report));
    return response()->json(['id' => $report->id, 'status' => 'pending'], 202);
});

Route::get('/reports/{id}', fn ($id) => Report::findOrFail($id));
```

Client poll edir və ya broadcast kanalına qulaq asır. Bu pattern "async endpoint"-lərin ekvivalentidir.

### 8) WebClient ekvivalenti — Http client

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->timeout(5)
    ->retry(3, 200, function ($exception, $request) {
        return $exception instanceof ConnectionException;
    })
    ->acceptJson()
    ->post('https://api.payment.com/charges', [
        'amount'   => 1000,
        'currency' => 'USD',
    ])
    ->throw()           // 4xx/5xx exception
    ->json();
```

`Http::macro` ilə reusable client yarada bilərsən:

```php
// AppServiceProvider
Http::macro('payment', function () {
    return Http::baseUrl(config('services.payment.url'))
        ->withToken(config('services.payment.key'))
        ->timeout(5)
        ->retry(3, 200);
});

// istifadə
Http::payment()->post('/charges', [...])->json();
```

Laravel `Http` client sinxron + promise əsaslıdır (Guzzle). `Http::async()` isə `PromiseInterface` qaytarır:

```php
$promise = Http::async()->get('https://api.example.com/data');
$response = $promise->wait();
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring WebFlux | Laravel |
|---|---|---|
| Konkurentlik modeli | Event loop (Netty) + Reactor | PHP-FPM proseslər; Octane + Swoole coroutine |
| Stream abstraksiya | `Mono<T>`, `Flux<T>` | LazyCollection + Generator |
| Backpressure | `onBackpressureBuffer/Drop/Latest` | Yoxdur (manuel chunk/sleep) |
| Async HTTP client | `WebClient` (non-blocking) | `Http::pool()`, `Http::async()` (Guzzle promise) |
| Sinxron HTTP client | `RestClient` (modern), `RestTemplate` (köhnə) | Default `Http` facade (sinxron) |
| Reactive SQL | R2DBC (Postgres, MySQL, MSSQL) | Yoxdur — PDO sinxrondur |
| SSE | `Flux<ServerSentEvent<T>>` | `StreamedResponse` + SSE format |
| WebSocket | `WebSocketHandler` + Reactor | Laravel Reverb (Swoole) + Broadcasting |
| Parallel calls | `Mono.zip()`, `Flux.merge()` | `Http::pool()`, `Octane::concurrently()` |
| Test alətləri | `StepVerifier`, virtual time | PHPUnit + Http::fake() |
| Global error | `WebExceptionHandler` | Exception Handler + middleware |
| Async alternativi | Virtual Threads (Java 21) + MVC | Octane (Swoole/RoadRunner/FrankenPHP) |
| Thread per request | Xeyir — event loop | Bəli — proses per request |
| Öyrənmə əyrisi | Yüksək (Reactor operator-ları) | Aşağı (sinxron kod) |

---

## Niyə belə fərqlər var?

**Java ekosisteminin uzun-ömürlü JVM-i.** JVM bir proses kimi qalır, threads, event loop, asinxron callback-lər təbii uyğundur. Netty Java-da 15 ildən çox istifadə olunan network library-dir. WebFlux üçün ideal əsasdır. Reactor layihəsi 2013-cü ildə başlayıb, 2017-də Spring Framework 5-in əsas "reactive stack"-i oldu.

**PHP-nin "shared-nothing" fəlsəfəsi.** PHP hər sorğu üçün yeni proses/worker istifadə edir. Bu model sadədir: kod asan yazılır, debug asan olur, "memory leak" az olur (hər sorğudan sonra hər şey təmizlənir). Amma event loop, reactive stream, backpressure konseptləri bu modelə uyğun gəlmir. Buna görə Laravel "queue + broadcast" pattern-ini seçib.

**Octane + Swoole fərqli yoldur.** Swoole PHP-ə coroutine və event loop gətirdi. Bu, PHP-ni Java-nın yerinə qoymur, amma concurrent sorğular, uzun-ömürlü memory, WebSocket server imkanı verir. Laravel Octane bu texnologiyanı Laravel framework-una inteqrasiya edir. Reverb bu bazada WebSocket server-idir.

**Reactive öyrənmə əyrisi yüksəkdir.** `flatMap`, `switchMap`, `publishOn`, `subscribeOn`, `onErrorContinue` kimi 50+ operator var. Kod oxunmaqda çətindir, debug stack trace qeyri-adi görünür. Virtual Threads (Java 21) gələndən sonra WebFlux seçim sahəsi daraldı — indi yalnız backpressure və stream kompozisiyası vacib olan yerlərdə istifadə olunur.

**Laravel-in "queue-first" yanaşması.** Laravel uzun işləri controller-də etmir — queue-ya atır. Bu "async endpoint"-lərin ehtiyacını aradan qaldırır. JS tərəfi broadcast kanalına qulaq asır və progress alır. Bu pattern Spring-də də işləyir, amma WebFlux reactive endpoint ilə birlikdə istifadə oluna bilir.

**R2DBC vs PDO.** R2DBC sıfırdan non-blocking driver kimi dizayn olunub. PHP-də PDO sinxrondur və "Swoole SQL hook" kimi workaround-lar var. PDO-nu non-blocking etmək üçün Swoole-un hook sistemi IO çağırışlarını coroutine-ə "yield" edir. Bu işləyir, amma R2DBC kimi geniş ecosystem yoxdur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `Mono<T>`, `Flux<T>` reactive abstraksiyalar
- Backpressure strategiyalar (`onBackpressureDrop`, `limitRate`, `onBackpressureBuffer`)
- R2DBC — reactive SQL driver
- WebClient (non-blocking) + RestClient (sinxron, modern)
- Reactor operator-ları: `flatMap`, `zip`, `merge`, `concatMap`, `switchIfEmpty`, `retryWhen`, `onErrorResume`
- `StepVerifier` + virtual time testing
- Functional RouterFunction DSL
- Virtual Threads alternativi (Java 21) — reactive öyrənmədən konkurentlik
- `publishOn` / `subscribeOn` ilə scheduler seçimi (parallel, boundedElastic)
- Netty əsaslı server (Tomcat əvəzi)
- `ServerSentEvent<T>` first-class type

**Yalnız Laravel-də (və ya daha sadə):**
- Octane + Swoole coroutine — eyni worker daxilində concurrent requests
- `Octane::concurrently()` — sadə paralelizm API
- `Http::pool()` — Guzzle əsaslı paralel HTTP
- Reverb — rəsmi WebSocket server (Swoole)
- Broadcasting — event → JS client pattern, çox sadə
- `LazyCollection` + `cursor()` — yaddaş-dost iteration
- Queue-first async pattern — complexity aşağı düşür
- Promise əsaslı `Http::async()` (Guzzle)
- FrankenPHP — Caddy əsaslı modern server
- StreamedResponse ilə sadə streaming

---

## Best Practices

**Spring WebFlux:**
- MVC + Virtual Threads kifayət edirsə, WebFlux seçməyin — kod daha sadədir
- Reactive pipeline-da sinxron blokinq kod çağırmayın (JDBC, `Thread.sleep`). `Schedulers.boundedElastic()`-ə keçirin: `.publishOn(Schedulers.boundedElastic())`
- `block()` yalnız main metodda və ya test-də istifadə olunmalıdır — controller/service-də heç vaxt
- Exception-lar `onErrorMap` ilə domain exception-a çevrilməlidir
- `timeout()` hər xarici çağırış üçün mütləqdir
- `subscribeOn` və `publishOn` fərqini başa düşün — subscribeOn yuxarı pipeline-ı, publishOn aşağını təsir edir
- Backpressure düşünmədən sonsuz `Flux`-lar yaratmayın (SSE, Kafka consumer)
- StepVerifier ilə bütün reactive metodlar test edilməlidir — reactive bug-ları adi test tapmır
- `Mono<Void>` və `Mono.empty()` fərqinə diqqət — `then()` və `thenEmpty()` fərqlənir
- WebClient-i `@Bean` kimi yaradın və reusable edin — hər sorğuda yeni `WebClient.Builder().build()` etməyin

**Laravel:**
- Uzun işlər üçün həmişə queue istifadə edin — controller-də 3 saniyədən çox gözlənilən heç nə olmamalıdır
- Paralel xarici çağırışlar üçün `Http::pool()` tətbiq edin
- Böyük data iterate edəndə `cursor()` və ya `lazy()` istifadə edin — `get()` deyil
- Octane istifadə edirsinizsə, singleton-larda request state saxlamayın (yaddaşa yığılar)
- Octane ilə `Octane::tick()` və ya interval task-ları ilə memory təmizləyin
- Reverb production-da ayrı proses kimi (supervisor) qaldırın
- SSE üçün PHP-FPM olmaz — Octane və ya Reverb seçin
- Http client-də `retry()` + `timeout()` həmişə birlikdə qoyun
- Test zamanı `Http::fake()` ilə xarici API-ları simulyasiya edin
- Real-time üçün polling əvəzinə broadcasting istifadə edin — istifadəçi təcrübəsi daha yaxşıdır

---

## Yekun

Spring WebFlux güclü reactive platformadır — stream, backpressure, SSE, reactive SQL, non-blocking HTTP client hamısı birinci dərəcəli dəstəklənir. Amma öyrənmə əyrisi yüksəkdir və indi Java 21 Virtual Threads çoxsaylı hallarda daha sadə alternativ verir. Yəni əksər yeni Spring layihəsi MVC + Virtual Threads seçir, WebFlux yalnız stream/backpressure məcburi olanda çıxır.

Laravel fərqli fəlsəfə seçib: sinxron kod, queue-first async, broadcasting ilə real-time. Octane və Reverb PHP ekosisteminə coroutine, long-running memory, WebSocket gətirir — amma reactive stream (Mono/Flux, backpressure operator-ları) yoxdur. Laravel komandası bu kompleksliyi qəsdən gətirmir: "lazy collection + cursor" + queue + broadcast kombinasiyası praktiki problemlərin 90%-ini həll edir.

Seçim qaydası: **çox konkurent, stream-heavy, I/O-bound, backpressure kritikdir** — Spring WebFlux. **Sadə kod, sürətli development, PHP/Laravel komandası** — Laravel + Octane + Reverb. Hər iki yanaşma production-da stabil işləyir, amma sistem dizaynı və komanda bilikləri əsasında seçim edilməlidir.
