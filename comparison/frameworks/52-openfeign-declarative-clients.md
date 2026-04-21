# OpenFeign vs Laravel HTTP Client / Saloon — Dərin Müqayisə

## Giriş

Mikroservis mühitində xidmətdən-xidmətə HTTP çağırışlar çox olur. `RestTemplate.getForObject(...)` və ya `Guzzle::get(...)` yazmaq işləyir, amma böyük layihələrdə bunu daha təmiz etmək lazımdır — client-i **interface** kimi elan edib, implementation-u framework-ə həvalə etmək.

**Spring tərəfində OpenFeign** bu problemi həll edir: `@FeignClient` annotation-lu interface yaz, metod imzaları sadə HTTP metodlarla işarələ (`@GetMapping`, `@PostMapping`), Spring proxy yaradır. Netflix-in Ribbon + Feign kombinasiyası Spring Cloud LoadBalancer ilə inteqrasiyalıdır — service discovery + retry + circuit breaker hamısı bir yerdə.

Son zamanlarda **Spring 6 `@HttpExchange` (HTTP Interface Client)** daxili bir alternativ kimi gəldi — OpenFeign-ə bənzər, amma Spring Framework core-unda, heç bir Spring Cloud asılılığı olmadan.

**Laravel tərəfində** üç layer var:
- `Http` facade (Guzzle wrapper) — imperative, sadə
- **Saloon** package — class-based, SDK-style, middleware
- Raw Guzzle — aşağı səviyyə

Bu fayl Feign-in təmiz interface pattern-nin Saloon Connector + Request pattern-i ilə necə eşləşdiyini göstərir.

---

## Spring-də istifadəsi

### 1) OpenFeign dependency və ilk client

```xml
<dependencies>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-openfeign</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-loadbalancer</artifactId>
    </dependency>
    <dependency>
        <groupId>io.github.openfeign</groupId>
        <artifactId>feign-okhttp</artifactId>
    </dependency>
</dependencies>
```

```java
@SpringBootApplication
@EnableFeignClients(basePackages = "com.example.clients")
public class OrderServiceApplication {
    public static void main(String[] args) {
        SpringApplication.run(OrderServiceApplication.class, args);
    }
}
```

```java
// com/example/clients/PaymentClient.java
@FeignClient(
    name = "payment-service",                  // service discovery adı
    path = "/payments",
    configuration = PaymentClientConfig.class
)
public interface PaymentClient {

    @PostMapping("/charges")
    ChargeResponse charge(@RequestBody ChargeRequest request);

    @GetMapping("/charges/{id}")
    ChargeResponse getCharge(@PathVariable("id") String id);

    @GetMapping("/customers/{customerId}/charges")
    List<ChargeResponse> listByCustomer(
        @PathVariable("customerId") String customerId,
        @RequestParam("limit") int limit,
        @RequestParam(value = "status", required = false) String status,
        @RequestHeader("X-Idempotency-Key") String idempotencyKey
    );

    @DeleteMapping("/charges/{id}")
    void cancel(@PathVariable("id") String id);
}
```

İstifadə:

```java
@Service
@RequiredArgsConstructor
public class OrderService {

    private final PaymentClient paymentClient;

    public Order placeOrder(OrderRequest req) {
        Order order = orderRepository.save(Order.draft(req));

        ChargeResponse charge = paymentClient.charge(new ChargeRequest(
            order.getCustomerId(),
            order.getTotalCents(),
            "USD",
            UUID.randomUUID().toString()
        ));

        order.markCharged(charge.getId());
        return orderRepository.save(order);
    }
}
```

Spring proxy yaradır, `lb://payment-service`-i service discovery ilə resolve edir, JSON serialize/deserialize edir.

### 2) Konfiqurasiya — timeout, logging, retry

```yaml
spring:
  cloud:
    openfeign:
      client:
        config:
          default:
            connect-timeout: 2000
            read-timeout: 5000
            logger-level: BASIC
          payment-service:
            connect-timeout: 1000
            read-timeout: 3000
            logger-level: FULL
      okhttp:
        enabled: true
      compression:
        request:
          enabled: true
        response:
          enabled: true

logging:
  level:
    com.example.clients.PaymentClient: DEBUG
```

Logger səviyyələri:
- `NONE` — heç nə
- `BASIC` — method, URL, status, duration
- `HEADERS` — + header-lər
- `FULL` — + body (secret-ləri log etmə!)

### 3) Custom configuration — interceptor, error decoder, retry

```java
@Configuration
public class PaymentClientConfig {

    @Bean
    public RequestInterceptor authInterceptor(TokenService tokens) {
        return template -> {
            template.header("Authorization", "Bearer " + tokens.current());
            template.header("X-Source", "order-service");
            template.header("X-Trace-Id", MDC.get("traceId"));
        };
    }

    @Bean
    public ErrorDecoder errorDecoder() {
        return new PaymentErrorDecoder();
    }

    @Bean
    public Retryer retryer() {
        return new Retryer.Default(
            /* period */ 200,
            /* maxPeriod */ 2000,
            /* maxAttempts */ 3
        );
    }

    @Bean
    public Request.Options options() {
        return new Request.Options(
            2, TimeUnit.SECONDS,     // connect
            5, TimeUnit.SECONDS,     // read
            true                     // follow redirects
        );
    }

    @Bean
    public Logger.Level feignLoggerLevel() {
        return Logger.Level.BASIC;
    }
}

public class PaymentErrorDecoder implements ErrorDecoder {
    private final ErrorDecoder defaultDecoder = new Default();

    @Override
    public Exception decode(String methodKey, Response response) {
        return switch (response.status()) {
            case 400 -> new PaymentBadRequestException(extractBody(response));
            case 401, 403 -> new PaymentAuthException();
            case 404 -> new PaymentNotFoundException();
            case 409 -> new PaymentConflictException();
            case 429 -> new PaymentRateLimitException(response.headers().get("Retry-After"));
            case 500, 502, 503, 504 -> new RetryableException(
                response.status(), "Payment service unavailable",
                response.request().httpMethod(), null, response.request());
            default -> defaultDecoder.decode(methodKey, response);
        };
    }

    private String extractBody(Response response) {
        try (var is = response.body().asInputStream()) {
            return new String(is.readAllBytes(), StandardCharsets.UTF_8);
        } catch (IOException e) {
            return "<unable to read body>";
        }
    }
}
```

### 4) Feign + Resilience4j circuit breaker

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-circuitbreaker-resilience4j</artifactId>
</dependency>
```

```yaml
spring:
  cloud:
    openfeign:
      circuitbreaker:
        enabled: true
        group:
          enabled: true

resilience4j:
  circuitbreaker:
    instances:
      PaymentClient:
        sliding-window-size: 20
        failure-rate-threshold: 50
        wait-duration-in-open-state: 30s
        permitted-number-of-calls-in-half-open-state: 5
  retry:
    instances:
      PaymentClient:
        max-attempts: 3
        wait-duration: 300ms
        exponential-backoff-multiplier: 2
  timelimiter:
    instances:
      PaymentClient:
        timeout-duration: 5s
```

```java
@FeignClient(
    name = "payment-service",
    fallbackFactory = PaymentClientFallbackFactory.class
)
public interface PaymentClient { ... }

@Component
public class PaymentClientFallbackFactory implements FallbackFactory<PaymentClient> {

    @Override
    public PaymentClient create(Throwable cause) {
        return new PaymentClient() {
            @Override
            public ChargeResponse charge(ChargeRequest req) {
                log.warn("Payment service down, using fallback", cause);
                return ChargeResponse.pending();
            }

            @Override
            public ChargeResponse getCharge(String id) {
                return ChargeResponse.unknown(id);
            }
            // ...
        };
    }
}
```

### 5) Spring 6 `@HttpExchange` — modern alternativ

OpenFeign-in Spring-native versiyasıdır. Spring Cloud asılılığı yoxdur.

```java
@HttpExchange(url = "/payments", accept = "application/json", contentType = "application/json")
public interface PaymentExchange {

    @PostExchange("/charges")
    ChargeResponse charge(@RequestBody ChargeRequest request);

    @GetExchange("/charges/{id}")
    ChargeResponse getCharge(@PathVariable String id);

    @GetExchange("/customers/{customerId}/charges")
    List<ChargeResponse> listByCustomer(
        @PathVariable String customerId,
        @RequestParam int limit,
        @RequestParam(required = false) String status,
        @RequestHeader("X-Idempotency-Key") String idempotencyKey
    );

    @DeleteExchange("/charges/{id}")
    void cancel(@PathVariable String id);
}

@Configuration
public class HttpExchangeConfig {

    @Bean
    public PaymentExchange paymentExchange(RestClient.Builder builder) {
        RestClient client = builder
            .baseUrl("http://payment-service")   // @LoadBalanced lazımdır
            .defaultHeader("X-Source", "order-service")
            .build();

        RestClientAdapter adapter = RestClientAdapter.create(client);
        return HttpServiceProxyFactory.builderFor(adapter)
            .build()
            .createClient(PaymentExchange.class);
    }
}
```

### 6) Feign vs `@HttpExchange` fərqləri

| Xüsusiyyət | Feign | `@HttpExchange` |
|---|---|---|
| Scope | Spring Cloud | Spring Framework core |
| Load balancer | Auto via `spring-cloud-loadbalancer` | Manual `@LoadBalanced RestClient` |
| Circuit breaker | `fallbackFactory` + R4j | `RetryTemplate` + R4j manual |
| Retry | `Retryer.Default` | `RetryTemplate` |
| Interceptor | `RequestInterceptor` | `ClientHttpRequestInterceptor` |
| Error decoder | `ErrorDecoder` | `ResponseErrorHandler` |
| Reactive | `@FeignClient` + `Mono`/`Flux` (feign-reactor) | Native `WebClientAdapter` |
| Config YAML | `spring.cloud.openfeign.client.config.*` | Yoxdur, bean-lərdə |

### 7) Feign logging level

```java
@Bean
Logger feignLogger() {
    return new Slf4jLogger();
}

@Bean
Logger.Level feignLoggerLevel() {
    return Logger.Level.BASIC;
}
```

Log nümunəsi (BASIC):
```
[PaymentClient#charge] ---> POST http://payment-service/payments/charges HTTP/1.1
[PaymentClient#charge] <--- HTTP/1.1 200 OK (243ms)
```

### 8) WireMock ilə testing

```xml
<dependency>
    <groupId>com.github.tomakehurst</groupId>
    <artifactId>wiremock-jre8</artifactId>
    <scope>test</scope>
</dependency>
```

```java
@SpringBootTest(webEnvironment = RANDOM_PORT)
@AutoConfigureWireMock(port = 0)
class PaymentClientTest {

    @Autowired
    PaymentClient paymentClient;

    @Test
    void charges_a_customer() {
        stubFor(post(urlEqualTo("/payments/charges"))
            .withHeader("Authorization", matching("Bearer .+"))
            .withRequestBody(matchingJsonPath("$.customerId", equalTo("c-1")))
            .willReturn(aResponse()
                .withStatus(200)
                .withHeader("Content-Type", "application/json")
                .withBody("""
                    {"id":"ch-1","status":"succeeded","amountCents":1200}
                """)));

        ChargeResponse resp = paymentClient.charge(new ChargeRequest("c-1", 1200, "USD", "idem-1"));

        assertThat(resp.getId()).isEqualTo("ch-1");
        assertThat(resp.getStatus()).isEqualTo("succeeded");
        verify(postRequestedFor(urlEqualTo("/payments/charges")));
    }
}
```

### 9) Full example — service discovery + retry + CB

```java
@FeignClient(
    name = "payment-service",              // Eureka/Consul service name
    path = "/payments",
    configuration = PaymentClientConfig.class,
    fallbackFactory = PaymentClientFallbackFactory.class
)
public interface PaymentClient {

    @PostMapping(value = "/charges", consumes = "application/json", produces = "application/json")
    @CircuitBreaker(name = "payments", fallbackMethod = "chargeFallback")
    @Retry(name = "payments")
    @TimeLimiter(name = "payments")
    CompletableFuture<ChargeResponse> chargeAsync(@RequestBody ChargeRequest request);
}
```

---

## Laravel-də istifadəsi

### 1) Laravel HTTP Client — imperative stil

```php
// app/Clients/PaymentClient.php
namespace App\Clients;

use Illuminate\Support\Facades\Http;
use App\Data\ChargeRequest;
use App\Data\ChargeResponse;

class PaymentClient
{
    public function charge(ChargeRequest $request): ChargeResponse
    {
        $response = Http::baseUrl(config('services.payment.url'))
            ->withToken($this->tokenService->current())
            ->withHeaders([
                'X-Source' => 'order-service',
                'X-Idempotency-Key' => $request->idempotencyKey,
                'X-Trace-Id' => request()->header('X-Trace-Id', (string) Str::uuid()),
            ])
            ->timeout(5)
            ->connectTimeout(2)
            ->retry(3, 300, function ($e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException
                    || ($e instanceof \Illuminate\Http\Client\RequestException
                        && $e->response->serverError());
            }, throw: false)
            ->acceptJson()
            ->post('/payments/charges', [
                'customerId'  => $request->customerId,
                'amountCents' => $request->amountCents,
                'currency'    => $request->currency,
            ]);

        if ($response->clientError()) {
            $this->throwFor($response);
        }

        $response->throw();

        return ChargeResponse::fromArray($response->json());
    }

    public function getCharge(string $id): ChargeResponse
    {
        $response = Http::baseUrl(config('services.payment.url'))
            ->withToken($this->tokenService->current())
            ->timeout(3)
            ->get("/payments/charges/{$id}")
            ->throw();

        return ChargeResponse::fromArray($response->json());
    }

    private function throwFor($response): never
    {
        throw match ($response->status()) {
            400 => new PaymentBadRequestException($response->body()),
            401, 403 => new PaymentAuthException(),
            404 => new PaymentNotFoundException(),
            409 => new PaymentConflictException(),
            429 => new PaymentRateLimitException($response->header('Retry-After')),
            default => new PaymentException('Unexpected ' . $response->status()),
        };
    }
}
```

### 2) Global macro və pool

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Http::macro('payment', function () {
        return Http::baseUrl(config('services.payment.url'))
            ->withToken(app(TokenService::class)->current())
            ->withHeaders(['X-Source' => 'order-service'])
            ->timeout(5)
            ->retry(2, 200);
    });
}

// istifadə
$response = Http::payment()->post('/payments/charges', $payload);

// paralel sorğular
[$order, $payment, $shipping] = Http::pool(fn ($pool) => [
    $pool->get("/orders/{$id}"),
    $pool->get("/payments/{$id}"),
    $pool->get("/shipping/{$id}"),
]);
```

### 3) Saloon — class-based SDK pattern

```bash
composer require sammyjo20/saloon
```

```php
// app/Http/Integrations/Payment/PaymentConnector.php
namespace App\Http\Integrations\Payment;

use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;

class PaymentConnector extends Connector
{
    use AcceptsJson;

    public function __construct(
        protected string $baseUrl,
        protected string $token,
    ) {}

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function defaultAuth(): ?Authenticator
    {
        return new TokenAuthenticator($this->token);
    }

    protected function defaultHeaders(): array
    {
        return [
            'X-Source' => 'order-service',
            'Accept'   => 'application/json',
        ];
    }

    protected function defaultConfig(): array
    {
        return [
            'timeout'         => 5,
            'connect_timeout' => 2,
        ];
    }
}
```

```php
// app/Http/Integrations/Payment/Requests/ChargeRequest.php
namespace App\Http\Integrations\Payment\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class ChargeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        public string $customerId,
        public int $amountCents,
        public string $currency = 'USD',
        public ?string $idempotencyKey = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/payments/charges';
    }

    protected function defaultBody(): array
    {
        return [
            'customerId'  => $this->customerId,
            'amountCents' => $this->amountCents,
            'currency'    => $this->currency,
        ];
    }

    protected function defaultHeaders(): array
    {
        return array_filter([
            'X-Idempotency-Key' => $this->idempotencyKey,
        ]);
    }

    public function createDtoFromResponse($response): \App\Data\ChargeResponse
    {
        return \App\Data\ChargeResponse::fromArray($response->json());
    }
}
```

```php
// app/Http/Integrations/Payment/Requests/GetChargeRequest.php
class GetChargeRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public string $id) {}

    public function resolveEndpoint(): string
    {
        return "/payments/charges/{$this->id}";
    }
}
```

```php
// istifadə
$connector = new PaymentConnector(
    baseUrl: config('services.payment.url'),
    token: app(TokenService::class)->current(),
);

$response = $connector->send(new ChargeRequest(
    customerId: 'c-1',
    amountCents: 1200,
    idempotencyKey: Str::uuid()->toString(),
));

$charge = $response->dto();
```

### 4) Saloon middleware və plugins

```php
// app/Http/Integrations/Payment/Plugins/AddsTraceId.php
namespace App\Http\Integrations\Payment\Plugins;

use Saloon\Http\PendingRequest;

trait AddsTraceId
{
    public function bootAddsTraceId(PendingRequest $request): void
    {
        $trace = request()?->header('X-Trace-Id') ?? (string) \Str::uuid();
        $request->headers()->add('X-Trace-Id', $trace);
    }
}

// Connector-də
class PaymentConnector extends Connector
{
    use AcceptsJson, AddsTraceId;
}
```

```php
// middleware
$connector->middleware()
    ->onRequest(function (PendingRequest $request) {
        Log::info('Payment request', [
            'method' => $request->getMethod()->value,
            'url'    => $request->getUrl(),
        ]);
    })
    ->onResponse(function ($response) {
        Log::info('Payment response', [
            'status'   => $response->status(),
            'duration' => $response->getPendingRequest()->getDuration(),
        ]);
    });
```

### 5) Retry və circuit breaker Saloon-da

```php
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\RedisStore;

class PaymentConnector extends Connector
{
    use HasRateLimits;

    protected function resolveLimits(): array
    {
        return [
            Limit::allow(100)->everyMinute(),
            Limit::allow(1000)->everyHour(),
        ];
    }

    protected function resolveRateLimitStore(): \Saloon\RateLimitPlugin\Contracts\RateLimitStore
    {
        return new RedisStore(\Illuminate\Support\Facades\Redis::connection()->client());
    }
}

// retry
$connector->tries = 3;
$connector->retryInterval = 300;

$response = $connector->send(new ChargeRequest(...));
```

Circuit breaker üçün `saloonphp/rate-limit-plugin` və ya custom wrapper istifadə olunur (Laravel-də 1st-class CB yoxdur).

### 6) Http::fake() testing

```php
// tests/Feature/PaymentClientTest.php
use Illuminate\Support\Facades\Http;

class PaymentClientTest extends \Tests\TestCase
{
    public function test_charges_a_customer(): void
    {
        Http::fake([
            config('services.payment.url') . '/payments/charges' => Http::response([
                'id' => 'ch-1',
                'status' => 'succeeded',
                'amountCents' => 1200,
            ], 200),
        ]);

        $client = app(\App\Clients\PaymentClient::class);
        $charge = $client->charge(new \App\Data\ChargeRequest('c-1', 1200, 'USD', 'idem-1'));

        $this->assertSame('ch-1', $charge->id);
        $this->assertSame('succeeded', $charge->status);

        Http::assertSent(function (\Illuminate\Http\Client\Request $req) {
            return $req->url() === config('services.payment.url') . '/payments/charges'
                && $req->method() === 'POST'
                && $req->data()['customerId'] === 'c-1';
        });
    }

    public function test_handles_429_rate_limit(): void
    {
        Http::fake([
            '*/payments/charges' => Http::response(['error' => 'rate_limited'], 429, ['Retry-After' => '30']),
        ]);

        $this->expectException(PaymentRateLimitException::class);
        app(\App\Clients\PaymentClient::class)
            ->charge(new ChargeRequest('c-1', 1200));
    }
}
```

### 7) Saloon MockClient testing

```php
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

$mockClient = new MockClient([
    ChargeRequest::class => MockResponse::make([
        'id' => 'ch-mock',
        'status' => 'succeeded',
    ], 200),

    GetChargeRequest::class => function ($request) {
        return MockResponse::make(['id' => $request->id, 'status' => 'succeeded']);
    },
]);

$connector = new PaymentConnector('https://fake', 'token');
$connector->withMockClient($mockClient);

$response = $connector->send(new ChargeRequest('c-1', 1200));

$mockClient->assertSent(ChargeRequest::class);
$mockClient->assertSentCount(1);
```

### 8) Guzzle directly — aşağı səviyyə

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

$stack = HandlerStack::create();
$stack->push(Middleware::retry(function ($retries, $request, $response) {
    if ($retries >= 3) return false;
    return $response && $response->getStatusCode() >= 500;
}, fn ($retries) => 1000 * pow(2, $retries)));

$client = new Client([
    'base_uri' => config('services.payment.url'),
    'handler'  => $stack,
    'timeout'  => 5,
]);
```

---

## Əsas fərqlər

| Xüsusiyyət | OpenFeign / `@HttpExchange` | Laravel Http / Saloon |
|---|---|---|
| Stil | Declarative interface | Fluent builder (Http) + class-based (Saloon) |
| Method binding | `@GetMapping`, `@PostMapping` | Manual `->get()`, `->post()` |
| Path variable | `@PathVariable("id")` | URL sətr konkatenasiya |
| Query param | `@RequestParam` | `->get($url, ['q' => ...])` |
| Body serialize | JSON auto (Jackson) | `->asJson()->post(...)` |
| Service discovery | `name = "service-name"` | Manual URL resolve |
| Load balancing | Spring Cloud LoadBalancer auto | Manual |
| Interceptor | `RequestInterceptor` bean | Saloon middleware, Http::globalRequestMiddleware |
| Error handling | `ErrorDecoder` interface | `->throw()` + match statement |
| Retry | `Retryer.Default` | `->retry(n, ms, condition)` |
| Timeout | `Request.Options` | `->timeout(s)->connectTimeout(s)` |
| Circuit breaker | `fallbackFactory` + R4j | Custom (no 1st-class) |
| Logging levels | `Logger.Level` enum | Http logger manual |
| Testing | WireMock, `@MockBean` | `Http::fake()`, Saloon MockClient |
| DTO mapping | `@RequestBody`/return auto | Manual (`::fromArray()`) |
| Reactive | `feign-reactor` + `Mono`/`Flux` | N/A (Laravel sync) |

---

## Niyə belə fərqlər var?

**Java interface-əsaslı dizayn.** Feign Retrofit-dən ilham alıb: interface elan et, implementation-u framework yazsın. Bu Java-nın proxy imkanları (JDK dinamic proxy, ByteBuddy) sayəsində rahat işləyir. Annotation-lar metadata-dır; Spring boot time-da scan edir və proxy yaradır. Tip-təhlükəsiz, refactor-dostu, IDE-də auto-complete verir.

**PHP-də interface pattern az yayılıb.** PHP 8-dən qabaq annotation yox idi; attribute-lar yeni gəldi (PHP 8.0). Saloon class-based yanaşması bu boşluğu doldurur — hər HTTP sorğu bir class-dır, Connector isə base URL + auth saxlayır. Bu, Feign interface-inin yerinə gəlir.

**Spring Cloud tight integration.** Feign `@FeignClient(name = "payment-service")` yazanda Spring Cloud LoadBalancer Eureka-dan instance oxuyur, retry/CB bağlanır, compression gedir. Laravel ekosistemində bu inteqrasiya yoxdur — hər layer-i özün birləşdirməlisən (Consul SDK + Saloon + custom CB).

**Error decoder vs fluent throw.** Feign `ErrorDecoder` pattern-i hər status code üçün strukturlu exception xəritələndirməni asanlaşdırır. Laravel `->throw()` və `match` ilə oxşar effekt almaq olur, amma pattern reusable deyil — hər client-də təkrarlanır. Saloon `createDtoFromResponse` və plugin-lər bu mesələni bir az yumşaldır.

**WireMock vs Http::fake().** WireMock ayrıca HTTP server qaldırır — real network layer-i test edir. `Http::fake()` isə Laravel HTTP client səviyyəsində intercept edir — daha sürətli, amma real request-response cycle-ni test etmir. İkisinin də yeri var.

**Circuit breaker first-class hüququ.** Resilience4j və Spring Cloud Circuit Breaker Feign ilə bir xətdə işləyir. Laravel-də analoq yoxdur — Envoy sidecar və ya custom implementation lazımdır. Bu, "discovery+retry+CB" bundle-nın Spring-in əsas üstünlüyüdür.

**`@HttpExchange` niyə doğuldu?** Spring Framework 6 OpenFeign-dən ilham alıb — amma yalnız core Spring, Spring Cloud asılılığı olmadan. Kiçik və orta layihələr üçün yetərlidir. OpenFeign isə service discovery + CB + retry + logger full bundle verir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring / OpenFeign-də:**
- Interface-based declarative client
- `@FeignClient(name = "service-name")` service discovery inteqrasiya
- `@EnableFeignClients` auto-scan
- `RequestInterceptor` bean — global header inject
- `ErrorDecoder` pattern — status → exception xəritəsi
- `Retryer.Default` — built-in exponential retry
- `Logger.Level` 4 pillə logging (NONE/BASIC/HEADERS/FULL)
- `fallbackFactory` — cause-aware fallback
- Feign + Resilience4j tam bundle
- Feign + Eureka/Consul zero-config
- `@HttpExchange` Spring 6 native alternative
- feign-okhttp, feign-httpclient 3rd-party backend swap
- Feign Reactive (`Mono`, `Flux` return types)
- `spring.cloud.openfeign.client.config.default.*` YAML-dən per-client tuning

**Yalnız Laravel / Saloon-da:**
- Saloon Connector + Request class pattern (SDK-style)
- `createDtoFromResponse()` hook — auto DTO transformation
- Saloon plugin traits (`AcceptsJson`, `HasRateLimits`, custom)
- `Http::pool()` — paralel sorğular bir syntax-da
- `Http::macro()` — chainable builder extension
- `Http::fake()` — intercept at framework level
- Saloon `MockClient` — per-request stub
- `Http::globalRequestMiddleware()` — bütün outgoing sorğular
- OAuth 2 / Passport flow helpers Saloon-da
- Saloon OAuth2 connector trait
- Laravel `->withUrlParameters()` — clean path binding
- `->dump()` və `->dd()` debugging helpers

**Hər iki ekosistemdə ortaq:**
- JSON serialize/deserialize auto
- HTTP retry with backoff
- Connect/read timeout ayrıca
- Request/response middleware chain
- Mock/stub test dəstəyi
- Basic, Bearer, Digest auth
- Multipart upload
- SSL peer verification control

---

## Best Practices

**OpenFeign üçün:**
- Interface hər xidmət üçün ayrı olsun — `PaymentClient`, `InventoryClient`, `ShippingClient`
- `@FeignClient(name=...)` service discovery adı ilə eyni olsun
- Timeout-u qısa ver — `connect=1-2s`, `read=3-5s`
- Retry yalnız idempotent (GET, PUT, DELETE) — POST-da yox
- `fallbackFactory` istifadə et (sadə `fallback` yox) — cause səbəbini log edəsən
- `ErrorDecoder` ilə domain exception-lar yarat — HTTP status-u iş qatına sızdırma
- `Logger.Level=BASIC` prod-da, `FULL` yalnız dev-də (secret sızmasın)
- Circuit breaker + timelimiter həmişə bir yerdə — timeout CB-ni tetiklesin
- Jackson JSON-da unknown property ignore et
- `@RequestHeader` ilə trace-id və idempotency-key yay
- WireMock ilə contract test yaz

**Laravel / Saloon üçün:**
- Saloon istifadə et 3rd-party API-lər üçün — SDK-style təmiz çıxır
- Daxili microservice-lər üçün Http::macro() + DTO kifayət
- Timeout-u hər client-də açıq ver — default hər yerə uyğun deyil
- `->retry()` closure-unda hansı exception-ın retry olunacağını açıq yaz
- `->throw()` + custom exception map — controller-də fərqli cavab ver
- `Http::fake()` test üçün həmişə — real API-yə sorğu göndərmə
- Saloon plugin-ləri ilə cross-cutting (trace, auth, logging) mərkəzləşdir
- Rate limit üçün Redis store istifadə et, memory store yox
- `Http::pool()` — aggregation endpoint-lərdə paralel sorğu üçün
- Idempotency-Key hər POST-da göndər — retry safe olsun

**Ümumi:**
- Client-side load balancing server-side-dən fərqlidir — K8s mühitində adətən Service DNS kifayətdir
- Hər outgoing sorğuya trace-id yay (OpenTelemetry propagation)
- 5xx retry, 4xx retry-ETMƏ (400 düzəlməyəcək)
- 429-da `Retry-After` header-ə hörmət et
- Connection pool tune et — hər host üçün 20–50 connection
- Downstream sancısı — circuit breaker açıq olsa, gözləmədən fallback ver
- Audit log outgoing HTTP — hansı servis, hansı endpoint, nə qədər

---

## Yekun

**OpenFeign** Java mikroservis ekosistemində HTTP klient yazmağın standartıdır. Interface elan edirsən, Spring proxy yaradır, service discovery + load balancer + retry + circuit breaker avtomatik inteqrasiyalı gəlir. `@HttpExchange` Spring 6-nın core-undadır və Spring Cloud olmadan oxşar declarative pattern verir.

**Laravel HTTP Client** sadə sorğular üçün ideal — fluent API, `Http::fake()` ilə test etmək asan, macro-larla yenidən istifadə olunur. **Saloon** isə 3rd-party API inteqrasiya üçün SDK-style pattern verir — Connector + Request class, plugin sistemi, middleware, MockClient.

**Seçim qaydası:**
- **Java microservice + service discovery** → OpenFeign
- **Java modern / Spring 6 minimal** → `@HttpExchange`
- **Laravel daxili mikroservislərə çağırış** → `Http::macro()` + DTO
- **Laravel → 3rd-party API (Stripe, Twilio, GitHub)** → Saloon (SDK-style təmizdir)
- **Laravel primitive təşkilatdan-təşkilata** → raw Guzzle (nadir hallarda)

Hər iki ekosistemdə əsas prinsiplər eynidir: qısa timeout, idempotent retry, structured error, mock-based test, trace-id propagation. Sadəcə sintaksis və ekosistem inteqrasiyası fərqlidir — OpenFeign daha "all-included", Saloon daha "compose yourself".
