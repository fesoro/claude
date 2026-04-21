# HTTP Interface (`@HttpExchange`) + RestClient — Dərin Müqayisə

## Giriş

Üçüncü tərəf API-lar (Stripe, Twilio, Slack, daxili mikroservis-lər) ilə işləmək hər tətbiqin tələbatıdır. Spring-də tarixən **RestTemplate** istifadə olunurdu (legacy), sonra reaktiv **WebClient** gəldi. Spring 6-da iki yenilik oldu: (1) **`@HttpExchange`** — Retrofit/Feign üslubunda declarative HTTP client interface-ləri, (2) **`RestClient`** — sinxron fluent API, `RestTemplate`-in modern əvəzi.

Laravel-də `Http` facade (Guzzle əsaslı) fluent API təqdim edir: `Http::get()`, `Http::post()`, `Http::pool()`, `Http::fake()`. Class-based inteqrasiya üçün **Saloon** paketi populyardır — Connector + Request nümunəsi ilə yaxşı struktur verir, OAuth2, pagination, retry middleware daxilidir.

---

## Spring-də istifadəsi

### 1) `RestClient` (Spring 6.1 / Boot 3.2) — RestTemplate əvəzi

```java
@Configuration
public class HttpConfig {

    @Bean
    public RestClient stripeClient(RestClient.Builder builder, StripeProps props) {
        return builder
            .baseUrl(props.baseUrl())
            .defaultHeader(HttpHeaders.AUTHORIZATION, "Bearer " + props.secretKey())
            .defaultHeader(HttpHeaders.CONTENT_TYPE, MediaType.APPLICATION_FORM_URLENCODED_VALUE)
            .requestInterceptor(new LoggingInterceptor())
            .requestInterceptor(new IdempotencyInterceptor())
            .defaultStatusHandler(HttpStatusCode::is4xxClientError, (req, res) -> {
                StripeError err = new ObjectMapper().readValue(res.getBody(), StripeError.class);
                throw new StripeApiException(err.type(), err.message());
            })
            .build();
    }
}

// İstifadə — fluent sync API
@Service
public class StripeService {
    private final RestClient stripe;

    public Charge createCharge(long amount, String currency, String source) {
        return stripe.post()
            .uri("/v1/charges")
            .body(Map.of(
                "amount", amount,
                "currency", currency,
                "source", source
            ))
            .retrieve()
            .body(Charge.class);
    }

    public PaymentIntent retrieveIntent(String id) {
        return stripe.get()
            .uri("/v1/payment_intents/{id}", id)
            .retrieve()
            .onStatus(HttpStatusCode::is5xxServerError, (req, res) -> {
                throw new TransientApiException("Stripe server error " + res.getStatusCode());
            })
            .body(PaymentIntent.class);
    }
}
```

### 2) `@HttpExchange` declarative interfaces

Əsl qazanc: HTTP call-u Java interface kimi təsvir etmək. Spring `HttpServiceProxyFactory` ilə proxy generate edir.

```java
@HttpExchange(url = "/v1", accept = "application/json")
public interface StripeApiClient {

    @PostExchange("/charges")
    Charge createCharge(@RequestParam("amount") long amount,
                        @RequestParam("currency") String currency,
                        @RequestParam("source") String source,
                        @RequestHeader("Idempotency-Key") String idempotencyKey);

    @GetExchange("/payment_intents/{id}")
    PaymentIntent retrieveIntent(@PathVariable String id);

    @GetExchange("/customers")
    List<Customer> listCustomers(@RequestParam("limit") int limit,
                                 @RequestParam(value = "starting_after", required = false) String cursor);

    @DeleteExchange("/customers/{id}")
    DeleteResult deleteCustomer(@PathVariable String id);

    @PatchExchange("/customers/{id}")
    Customer updateCustomer(@PathVariable String id, @RequestBody CustomerUpdate update);
}
```

Registration:

```java
@Configuration
public class StripeClientConfig {

    @Bean
    public StripeApiClient stripeApiClient(RestClient stripeClient) {
        RestClientAdapter adapter = RestClientAdapter.create(stripeClient);
        HttpServiceProxyFactory factory = HttpServiceProxyFactory.builderFor(adapter).build();
        return factory.createClient(StripeApiClient.class);
    }
}
```

WebClient ilə də eyni interface:

```java
@Bean
public StripeApiClient reactiveStripeClient(WebClient webClient) {
    WebClientAdapter adapter = WebClientAdapter.create(webClient);
    return HttpServiceProxyFactory.builderFor(adapter).build()
        .createClient(StripeApiClient.class);
}
```

Eyni interface həm sync (RestClient), həm async (WebClient) ilə işlədilə bilər — method return tipindən asılıdır:

```java
@HttpExchange("/v1")
public interface StripeApiClient {

    @GetExchange("/customers/{id}")
    Customer getCustomerSync(@PathVariable String id);

    @GetExchange("/customers/{id}")
    Mono<Customer> getCustomerReactive(@PathVariable String id);

    @GetExchange("/customers/{id}")
    CompletableFuture<Customer> getCustomerAsync(@PathVariable String id);
}
```

### 3) Interceptor və header idarəetməsi

```java
public class IdempotencyInterceptor implements ClientHttpRequestInterceptor {

    @Override
    public ClientHttpResponse intercept(HttpRequest req, byte[] body,
                                        ClientHttpRequestExecution exec) throws IOException {
        if (HttpMethod.POST.equals(req.getMethod())
         && !req.getHeaders().containsKey("Idempotency-Key")) {
            req.getHeaders().add("Idempotency-Key", UUID.randomUUID().toString());
        }
        return exec.execute(req, body);
    }
}

public class LoggingInterceptor implements ClientHttpRequestInterceptor {
    private static final Logger log = LoggerFactory.getLogger(LoggingInterceptor.class);

    @Override
    public ClientHttpResponse intercept(HttpRequest req, byte[] body,
                                        ClientHttpRequestExecution exec) throws IOException {
        long start = System.nanoTime();
        log.info("HTTP {} {}", req.getMethod(), req.getURI());
        try {
            ClientHttpResponse res = exec.execute(req, body);
            long ms = (System.nanoTime() - start) / 1_000_000;
            log.info("HTTP {} {} -> {} ({} ms)", req.getMethod(), req.getURI(),
                    res.getStatusCode().value(), ms);
            return res;
        } catch (Exception e) {
            log.error("HTTP {} {} FAILED: {}", req.getMethod(), req.getURI(), e.getMessage());
            throw e;
        }
    }
}
```

### 4) Resilience4j ilə retry + circuit breaker

```xml
<dependency>
    <groupId>io.github.resilience4j</groupId>
    <artifactId>resilience4j-spring-boot3</artifactId>
    <version>2.2.0</version>
</dependency>
```

```yaml
resilience4j:
  retry:
    instances:
      stripe:
        max-attempts: 3
        wait-duration: 500ms
        exponential-backoff-multiplier: 2
        retry-exceptions:
          - java.io.IOException
          - com.example.TransientApiException
  circuitbreaker:
    instances:
      stripe:
        sliding-window-size: 20
        failure-rate-threshold: 50
        wait-duration-in-open-state: 30s
  timelimiter:
    instances:
      stripe:
        timeout-duration: 3s
```

```java
@Service
public class StripeService {

    @Retry(name = "stripe")
    @CircuitBreaker(name = "stripe", fallbackMethod = "chargeFallback")
    @TimeLimiter(name = "stripe")
    public CompletableFuture<Charge> createCharge(long amount) {
        return CompletableFuture.supplyAsync(() ->
            stripeClient.createCharge(amount, "usd", "tok_visa", UUID.randomUUID().toString()));
    }

    private CompletableFuture<Charge> chargeFallback(long amount, Throwable t) {
        log.warn("Circuit open, falling back: {}", t.getMessage());
        return CompletableFuture.completedFuture(Charge.failed(amount));
    }
}
```

### 5) Test: `MockRestServiceServer` RestClient üçün

```java
@SpringBootTest
class StripeServiceTest {

    @Autowired RestClient.Builder builder;
    @Autowired StripeService svc;

    private MockRestServiceServer server;

    @BeforeEach
    void setUp() {
        RestClient client = builder.baseUrl("https://api.stripe.com").build();
        server = MockRestServiceServer.bindTo(client).build();
    }

    @Test
    void createsCharge() {
        server.expect(requestTo("https://api.stripe.com/v1/charges"))
              .andExpect(method(HttpMethod.POST))
              .andExpect(header("Idempotency-Key", notNullValue()))
              .andRespond(withSuccess("""
                    {"id":"ch_1","amount":1000,"currency":"usd","status":"succeeded"}
                    """, MediaType.APPLICATION_JSON));

        Charge charge = svc.createCharge(1000);

        assertThat(charge.id()).isEqualTo("ch_1");
        server.verify();
    }
}
```

WebClient üçün `WebTestClient` və ya `MockWebServer` (OkHttp) istifadə olunur.

### 6) `RestTemplate` vs `RestClient` vs `WebClient` — nə vaxt hansını?

| Aspekt | RestTemplate | RestClient | WebClient |
|---|---|---|---|
| Status | legacy (maintained) | preferred sync | async/reactive |
| API üslubu | template method | fluent chain | fluent + Reactor |
| Return tipi | `ResponseEntity<T>` | `body(T.class)` | `Mono<T>` / `Flux<T>` |
| Threading | blocking | blocking | non-blocking |
| `@HttpExchange` dəstəyi | Yox | Var | Var |
| Spring version | 3.x+ | 6.1+ | 5.0+ |
| Tövsiyə | Yeni kod üçün istifadə etmə | Sync üçün | Async üçün |

### 7) vs OpenFeign

OpenFeign Spring Cloud daxilindədir:

```java
@FeignClient(name = "stripe", url = "${stripe.base-url}")
public interface StripeFeignClient {
    @PostMapping("/v1/charges")
    Charge createCharge(@RequestParam long amount, @RequestParam String currency);
}
```

Müqayisə:

| Aspekt | `@HttpExchange` | OpenFeign |
|---|---|---|
| Məkan | Spring Framework core | Spring Cloud |
| Adapter | RestClient, WebClient | Feign client (Ribbon/LB daxili) |
| Discovery | manual | Eureka, Consul auto |
| Contract | Spring MVC annotations | Feign + Spring MVC |
| Tövsiyə | Standalone və ya kiçik mikroservis | Spring Cloud ekosistemində |

---

## Laravel-də istifadəsi

### 1) `Http` facade — fluent builder

```php
use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
        'Accept' => 'application/json',
    ])
    ->withToken(config('services.stripe.secret'))
    ->asForm()
    ->timeout(5)
    ->connectTimeout(2)
    ->retry(3, 500, throw: false)
    ->post('https://api.stripe.com/v1/charges', [
        'amount' => 1000,
        'currency' => 'usd',
        'source' => 'tok_visa',
    ]);

if ($response->successful()) {
    $charge = $response->json();
    // ...
} elseif ($response->clientError()) {
    logger()->warning('Stripe 4xx', $response->json());
} elseif ($response->serverError()) {
    throw new TransientApiException($response->body());
}
```

### 2) Class-based client (without Saloon)

```php
namespace App\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class StripeClient
{
    public function __construct(private readonly array $config) {}

    public function createCharge(int $amount, string $currency, string $source): array
    {
        return $this->request()
            ->asForm()
            ->post('/charges', [
                'amount' => $amount,
                'currency' => $currency,
                'source' => $source,
            ])
            ->throw()
            ->json();
    }

    public function retrieveIntent(string $id): array
    {
        return $this->request()->get("/payment_intents/{$id}")->throw()->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withToken($this->config['secret'])
            ->withHeaders(['Idempotency-Key' => (string) str()->uuid()])
            ->timeout(5)
            ->retry(3, 500, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException);
    }
}
```

```php
// config/services.php
'stripe' => [
    'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
    'secret'   => env('STRIPE_SECRET'),
],

// AppServiceProvider
$this->app->singleton(StripeClient::class, fn () => new StripeClient(config('services.stripe')));
```

### 3) `Http::pool()` — paralel sorğular

```php
$responses = Http::pool(fn ($pool) => [
    $pool->as('user')->get("https://api.example.com/users/{$id}"),
    $pool->as('orders')->get("https://api.example.com/users/{$id}/orders"),
    $pool->as('balance')->get("https://api.example.com/users/{$id}/balance"),
]);

$user    = $responses['user']->json();
$orders  = $responses['orders']->json();
$balance = $responses['balance']->json();
```

Guzzle promise-lər altında işləyir — 3 sorğu paralel olur. Spring-də analoqu `WebClient` ilə `Mono.zip()` və ya Java 21 virtual thread + `RestClient`.

### 4) `Http::fake()` — test

```php
use Illuminate\Support\Facades\Http;

it('creates stripe charge', function () {
    Http::fake([
        'api.stripe.com/v1/charges' => Http::response([
            'id' => 'ch_1',
            'amount' => 1000,
            'status' => 'succeeded',
        ], 200),
    ]);

    $client = app(StripeClient::class);
    $charge = $client->createCharge(1000, 'usd', 'tok_visa');

    expect($charge['id'])->toBe('ch_1');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.stripe.com/v1/charges'
            && $request->method() === 'POST'
            && $request->hasHeader('Idempotency-Key');
    });
});
```

### 5) Macros — reusable builder

```php
// AppServiceProvider::boot()
Http::macro('stripe', function () {
    return Http::baseUrl(config('services.stripe.base_url'))
        ->withToken(config('services.stripe.secret'))
        ->asForm()
        ->timeout(5);
});

// İstifadə
Http::stripe()->post('/charges', ['amount' => 1000, 'currency' => 'usd']);
```

### 6) Saloon paketi — class-based struktur

```bash
composer require saloonphp/saloon
composer require saloonphp/laravel-plugin
```

```php
namespace App\Http\Integrations\Stripe;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class StripeConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public function resolveBaseUrl(): string
    {
        return config('services.stripe.base_url');
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.config('services.stripe.secret'),
        ];
    }

    protected function defaultConfig(): array
    {
        return [
            'timeout' => 5,
        ];
    }
}
```

Request class-ları:

```php
namespace App\Http\Integrations\Stripe\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

class CreateChargeRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $source,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/charges';
    }

    protected function defaultBody(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'source' => $this->source,
        ];
    }
}
```

İstifadə:

```php
$stripe = new StripeConnector();
$response = $stripe->send(new CreateChargeRequest(1000, 'usd', 'tok_visa'));
$charge = $response->json();
```

Saloon üstünlükləri:
- **Plugins** — `AlwaysThrowOnErrors`, `AcceptsJson` və s. trait-lər
- **Middleware** — request/response pipeline
- **OAuth2** — `AuthorizationCodeGrant` daxilindədir
- **Pagination** — `PagedPaginator`, `CursorPaginator`
- **Mock client** — `MockClient::global()`
- **Rate limit** — `RateLimitPlugin`

### 7) Retry + exception handling

```php
Http::retry(
    times: 3,
    sleepMilliseconds: fn ($attempt) => 200 * (2 ** $attempt),  // exponential
    when: fn ($exception, $request) => $exception instanceof ConnectionException
        || ($exception instanceof RequestException && $exception->response->serverError()),
    throw: true,
)
->post(...);
```

### 8) `composer.json`

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.0",
        "saloonphp/saloon": "^3.10",
        "saloonphp/laravel-plugin": "^3.5"
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring (`@HttpExchange` + RestClient) | Laravel (`Http` + Saloon) |
|---|---|---|
| Declarative interface | `@HttpExchange` daxili | Saloon Request class-ları |
| Fluent inline API | RestClient | `Http` facade |
| Async model | WebClient + Mono/Flux | `Http::pool()` (Guzzle promises) |
| Sync model | RestClient | `Http` facade |
| Interceptor | `ClientHttpRequestInterceptor` | Saloon middleware, Guzzle handler stack |
| Retry | Resilience4j və ya Spring Retry | `->retry()` və ya Saloon `RetryAfter` |
| Circuit breaker | Resilience4j | Paket (`ably/circuit-breaker` və s.) |
| Mock | `MockRestServiceServer` | `Http::fake()`, Saloon MockClient |
| OAuth2 client | Spring Security OAuth2 Client | Saloon `AuthorizationCodeGrant` |
| Type safety | Full (Java tipleri) | PHP scalar / array, DTO ilə artırıla bilər |
| Legacy API | RestTemplate | Guzzle (çılpaq) |
| Spring Cloud əlavəsi | OpenFeign + service discovery | N/A |

---

## Niyə belə fərqlər var?

**Spring-in tip həssaslığı.** Java statik dil olduğu üçün `@HttpExchange` interface-ləri compile-time type check verir — method return tipini səhv yazsan, build uğursuz olar. Bu Retrofit/Feign ənənəsindən gəlir və artıq Spring Framework core-una daxildir.

**Laravel-in tez iteration fəlsəfəsi.** PHP dinamik dildir — fluent API-dan istifadə etmək tez və rahatdır. `Http::get('...')->json()` sətri kifayətdir; artıq interface yaratmaq lazım deyil. Amma böyük tətbiqdə struktur lazım olduqda Saloon ənənəsini gətirir.

**Reaktiv model fərqi.** Spring-in WebClient Reactor Project əsaslıdır — Mono/Flux ilə backpressure və composition verir. Laravel-də Guzzle `promise` istifadə edir, amma PHP coroutine modeli olmadığı üçün çox da idiomatic deyil. Əksinə Octane + Swoole-da coroutine-lər var.

**Test simulyasiyası.** `MockRestServiceServer` Spring MVC test infrastrukturu ilə vahiddir. `Http::fake()` Laravel-də lazer kimi sadədir — bir neçə sətr yazıb bütün HTTP çağırışlarını stub edirsən.

**Resilience4j-in yeri.** Spring cross-cutting concern-lər üçün annotasiya-driven yanaşmanı sevir — `@Retry`, `@CircuitBreaker` metodu aspect ilə əhatə edir. Laravel-də eyni şey imperativ şəkildə (`->retry()`) qaldırılır. Spring-in annotasiya modeli daha çox konfiqurasiyanı xarici (YAML) fayla aparır.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `@HttpExchange` — Java interface-dən HTTP client generate etmək (compile-time type safe)
- Eyni interface həm sync (RestClient), həm async (WebClient) adapter ilə
- Reactor `Mono`/`Flux` ilə reaktiv HTTP client
- `@FeignClient` + Spring Cloud service discovery (Eureka, Consul)
- `MockRestServiceServer` declarative assertion
- `WebClient` backpressure + streaming body (Server-Sent Events)
- Annotasiya-driven Resilience4j (`@Retry`, `@CircuitBreaker`, `@Bulkhead`)
- `RestClientCustomizer` — builder-i qlobal dəyişmək

**Yalnız Laravel-də:**
- `Http::pool()` — bir neçə sorğunu paralel göndərmə (sadə API)
- `Http::fake()` — zero-config HTTP mock
- `Http::macro()` — custom fluent method-lar
- `Http::global()` — bütün sorğulara middleware qoşmaq
- Saloon **Request + Connector** arxitektura nümunəsi
- Saloon **pagination iterator** (`->paginate()->collect()`)
- Saloon OAuth2 auth-code flow daxilində
- Saloon **plugins** trait sistemi (AlwaysThrowOnErrors, RateLimitPlugin)
- Saloon `Laravel\Plugin` ilə Laravel inteqrasiya

---

## Best Practices

1. **`@HttpExchange` interface-lər domain-specific olsun** — Stripe üçün ayrı, internal service üçün ayrı.
2. **`RestClient` bean-lərini domen üzrə ayır** — `stripeClient`, `twilioClient` — hər biri öz base URL və timeout-u ilə.
3. **`RequestInterceptor` ilə idempotency key avtomatik əlavə et** — POST sorğularda.
4. **Resilience4j konfiqurasiyasını YAML-da saxla** — kod dəyişmədən re-tune.
5. **Production-da Saloon və ya class-based client-dən istifadə et** — Laravel fluent inline API kiçik tətbiq üçün uyğundur.
6. **Saloon Connector-u singleton bağla** — connection pooling Guzzle səviyyəsində qazanılır.
7. **Test-də `Http::fake()` və ya `MockRestServiceServer` ilə təqlid** — real API-ya çağırış etmə.
8. **Timeout həmişə qoy** — `timeout(5)`, `connectTimeout(2)` default yoxdur.
9. **Retry yalnız safe method-larda** (GET, PUT, DELETE) və ya idempotency key ilə POST-da.
10. **Logging-ə request ID əlavə et** — trace context W3C `traceparent` header-i əl ilə ötür.
11. **`Accept` və `Content-Type` konkretləşdir** — server-in default-ına güvənmə.
12. **Client error vs server error ayır** — 4xx-i retry etmə, 5xx-i retry et.

---

## Yekun

Spring 6-da **`@HttpExchange`** HTTP client-in declarative, tip-safe təsvirini verir; **`RestClient`** isə `RestTemplate`-in modern fluent varisidir. Hər ikisi bir-birini tamamlayır — yeni layihədə default seçim olmalıdır, köhnə `RestTemplate`-ni addım-addım əvəz edək. `WebClient` reaktiv tətbiqlər üçün qalır.

Laravel-də **`Http` facade** sürətli start verir, **Saloon** isə uzun-ömürlü kod üçün arxitektura gətirir — Connector + Request pattern, plugin-lər, OAuth2, pagination daxilindədir. `Http::pool()` və `Http::fake()` Laravel-in fərqləndirici tərəfləridir — paralel çağırış və test sadəlik baxımından çox rahatdır.

Seçim qaydası: **Spring-də** declarative interface + sync `RestClient`, reaktiv lazım olsa WebClient. **Laravel-də** inline-da `Http` facade, böyük inteqrasiyalarda Saloon. Hər iki tərəf də Guzzle / Apache HttpClient kimi aşağı səviyyə istifadə etmir — framework abstraksiyaları tam kifayətdir.
