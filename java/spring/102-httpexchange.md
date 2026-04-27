# @HttpExchange — Deklarativ HTTP Client (Senior)

> **Seviyye:** Senior ⭐⭐⭐

## İcmal

**@HttpExchange** — Spring 6.0+ ilə gəlmiş deklarativ HTTP client mexanizmi. Interface yaradılır, metodlar annotasiya ilə işarələnir, Spring proxy yaradır. Əlavə dependency yoxdur — Spring Framework-in özündə qurulub.

OpenFeign (Feign client) ilə eyni fikir, lakin Spring-native: Spring Security, Spring Observability, Spring Retry ilə avtomatik inteqrasiya. Feign kimi `@FeignClient` annotation yox — `@HttpExchange` interface üzərindədir.

---

## Niyə Vacibdir

```
Problem: 10 fərqli external API endpoint-i var.
Hər biri üçün RestClient kodu yazmaq — boilerplate.

Həll: Interface yaz, Spring proxy yaratsın.

RestClient (imperative):
  return restClient.get().uri("/payments/{id}", id).retrieve().body(Payment.class);

@HttpExchange (declarative):
  Payment getPayment(@PathVariable Long id);
  // RestClient kodu yoxdur — Spring proxy edir
```

PHP Laravel Saloon SDK ilə müqayisə: Saloon-da `Request` class-ları yaradılır, `Connector`-a qeydiyyatdan keçirilir. @HttpExchange daha yığcam — sadəcə interface + method annotation.

---

## Əsas Anlayışlar

- **@HttpExchange** — interface səviyyəsindəki annotation: URL, content-type, accept
- **@GetExchange / @PostExchange / ...** — method səviyyəsindəki annotation: HTTP metod + URL
- **HttpServiceProxyFactory** — interface-i proxy-yə çevirmək üçün Spring sinifi
- **RestClientAdapter** — `RestClient` əsasında blocking proxy
- **WebClientAdapter** — `WebClient` əsasında reactive proxy
- **@PathVariable, @RequestParam, @RequestHeader, @RequestBody** — method parametrləri Spring MVC ilə eynidir

---

## Praktik Baxış

**Ne vaxt @HttpExchange:**
- Bir neçə endpoint-i olan external API client (payment gateway, notification service, auth service)
- Multiple API-yə çağırış edən microservice
- Interface-based abstraction lazımdır (mock/test asan olur)

**Ne vaxt RestClient (imperative):**
- Sadə, bir-iki endpoint
- Runtime-da URL dinamik formalaşır
- Conditional request logic tələb olunur

**OpenFeign vs @HttpExchange:**

| Meyar | OpenFeign | @HttpExchange |
|-------|-----------|---------------|
| Dependency | `spring-cloud-starter-openfeign` | Spring Framework built-in |
| Auto-configuration | @EnableFeignClients lazım | Sadəcə @Bean |
| Ribbon/LB | Cloud native, service discovery | Özün konfiqurasiya |
| Reactive | Məhdud dəstək | WebClientAdapter ilə tam Reactive |

Spring Cloud layihəsindəsənsə OpenFeign rahatlığı var (service discovery, load balancing). Sıradan tətbiqdə @HttpExchange daha sadədir.

**Common mistakes:**
- `HttpServiceProxyFactory.create()` çağırarkən adapter-i unutmaq
- Proxy-ni `@Bean` kimi register etməmək — `@Autowired` işləməz
- `@HttpExchange` interface-ini `@Component` etmək — işləmir, Spring onu scan etmir

---

## Nümunələr

### Ümumi Nümunə

Ödəniş şirkəti ilə inteqrasiya: `PaymentClient` interface-i yaradılır, Spring proxy əmələ gətirir. `PaymentService` bu interface-i inject edib işlədir — RestClient kodu `PaymentService`-dən tamamilə gizlənir.

### Kod Nümunəsi

**1. @HttpExchange interface-i**

```java
// Interface səviyyəsindəki annotation — bütün metodlara default tətbiq olunur
@HttpExchange(url = "/v1/payments", accept = "application/json", contentType = "application/json")
public interface PaymentClient {

    // GET /v1/payments/{id}
    @GetExchange("/{id}")
    Payment getPayment(@PathVariable Long id);

    // GET /v1/payments?status=PENDING&page=0&size=20
    @GetExchange
    Page<Payment> listPayments(
        @RequestParam String status,
        @RequestParam int page,
        @RequestParam int size
    );

    // POST /v1/payments
    @PostExchange
    PaymentResponse createPayment(@RequestBody CreatePaymentRequest request);

    // PUT /v1/payments/{id}
    @PutExchange("/{id}")
    PaymentResponse updatePayment(@PathVariable Long id, @RequestBody UpdatePaymentRequest request);

    // DELETE /v1/payments/{id}
    @DeleteExchange("/{id}")
    void cancelPayment(@PathVariable Long id);

    // Dinamik header — sorğuya görə dəyişən header
    @PostExchange("/refund")
    RefundResponse refund(@RequestBody RefundRequest request, @RequestHeader("X-Idempotency-Key") String idempotencyKey);
}
```

**2. @HttpExchange attributes**

```java
// Tam nümunə — bütün attributlar
@HttpExchange(
    url = "/webhooks",
    method = "POST",                              // default: method annotasiyasından
    contentType = MediaType.APPLICATION_JSON_VALUE,
    accept = MediaType.APPLICATION_JSON_VALUE
)
public interface WebhookClient {
    // ...
}
```

**3. HttpServiceProxyFactory — @Bean konfiqurasiyası**

```java
@Configuration
public class PaymentClientConfig {

    @Value("${payment.api.url}")
    private String baseUrl;

    @Value("${payment.api.key}")
    private String apiKey;

    // RestClient bean-ı
    @Bean
    public RestClient paymentRestClient() {
        var factory = new HttpComponentsClientHttpRequestFactory();
        factory.setConnectTimeout(Duration.ofSeconds(5));
        factory.setReadTimeout(Duration.ofSeconds(10));

        return RestClient.builder()
            .baseUrl(baseUrl)
            .defaultHeader("X-Api-Key", apiKey)
            .requestFactory(factory)
            .build();
    }

    // RestClient-dən blocking proxy
    @Bean
    public PaymentClient paymentClient(RestClient paymentRestClient) {
        HttpServiceProxyFactory factory = HttpServiceProxyFactory
            .builderFor(RestClientAdapter.create(paymentRestClient))
            .build();

        return factory.createClient(PaymentClient.class);
    }
}
```

**4. WebClient ilə reactive proxy**

```java
@Bean
public PaymentClient reactivePaymentClient(WebClient webClient) {
    HttpServiceProxyFactory factory = HttpServiceProxyFactory
        .builderFor(WebClientAdapter.create(webClient))
        .build();
    return factory.createClient(PaymentClient.class);
}

// Interface-dəki return type-lar Mono/Flux olmalıdır:
@HttpExchange("/v1/payments")
public interface ReactivePaymentClient {

    @GetExchange("/{id}")
    Mono<Payment> getPayment(@PathVariable Long id);

    @GetExchange
    Flux<Payment> listPayments(@RequestParam String status);
}
```

**5. @Service-də istifadə**

```java
@Service
@RequiredArgsConstructor
public class PaymentService {

    private final PaymentClient paymentClient;  // proxy inject olunur

    public Payment findPayment(Long id) {
        try {
            return paymentClient.getPayment(id);
        } catch (HttpClientErrorException.NotFound e) {
            throw new PaymentNotFoundException("Payment not found: " + id);
        }
    }

    public PaymentResponse processPayment(CreatePaymentRequest request) {
        String idempotencyKey = UUID.randomUUID().toString();
        return paymentClient.createPayment(request);
    }
}
```

**6. Bir neçə müxtəlif API client**

```java
@HttpExchange(url = "https://api.notify.io/v2")
public interface NotificationClient {
    @PostExchange("/email")
    void sendEmail(@RequestBody EmailRequest request);

    @PostExchange("/sms")
    void sendSms(@RequestBody SmsRequest request);
}

@HttpExchange(url = "https://api.identity.io/v1")
public interface IdentityClient {
    @GetExchange("/users/{externalId}")
    ExternalUser getUser(@PathVariable String externalId);
}

// Hər biri üçün ayrı @Bean:
@Bean
public NotificationClient notificationClient() {
    RestClient client = RestClient.builder()
        .baseUrl("https://api.notify.io/v2")
        .defaultHeader("Authorization", "Bearer " + notifyApiKey)
        .build();
    return HttpServiceProxyFactory.builderFor(RestClientAdapter.create(client))
        .build().createClient(NotificationClient.class);
}

@Bean
public IdentityClient identityClient() {
    RestClient client = RestClient.builder()
        .baseUrl("https://api.identity.io/v1")
        .defaultHeader("X-Client-Id", identityClientId)
        .build();
    return HttpServiceProxyFactory.builderFor(RestClientAdapter.create(client))
        .build().createClient(IdentityClient.class);
}
```

**7. ResponseEntity return type**

```java
@HttpExchange("/v1/payments")
public interface PaymentClient {

    // Status kodu + body lazımdırsa
    @PostExchange
    ResponseEntity<PaymentResponse> createPaymentWithMeta(@RequestBody CreatePaymentRequest request);

    // Location header-ını oxumaq üçün
    public PaymentResponse create(CreatePaymentRequest req) {
        ResponseEntity<PaymentResponse> response = paymentClient.createPaymentWithMeta(req);
        URI location = response.getHeaders().getLocation();
        log.info("Created at: {}", location);
        return response.getBody();
    }
}
```

**8. PHP Saloon SDK ilə müqayisə**

```php
// Saloon — PHP deklarativ HTTP client
class GetPaymentRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return "/v1/payments/{$this->id}";
    }
}

class PaymentConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return config('payment.api_url');
    }
}

// İstifadə:
$connector = new PaymentConnector();
$response = $connector->send(new GetPaymentRequest($id));
$payment = $response->dto();
```

```java
// @HttpExchange — daha yığcam
@HttpExchange("/v1/payments")
public interface PaymentClient {
    @GetExchange("/{id}")
    Payment getPayment(@PathVariable Long id);
}
// Bir dəfə @Bean — sonra sadəcə inject et
```

Saloon-da hər sorğu ayrı class, @HttpExchange-də sadəcə method.

---

## Praktik Tapşırıqlar

**Tapşırıq 1 — External API client**
1. `https://jsonplaceholder.typicode.com` üçün `JsonPlaceholderClient` interface-i yaz
2. `GET /posts/{id}`, `GET /users/{userId}/posts`, `POST /posts` endpoint-lərini əlavə et
3. `@Configuration`-da `RestClient` + `HttpServiceProxyFactory` ilə `@Bean` yarat
4. `@Service`-də inject edib test et

**Tapşırıq 2 — Multiple clients**
1. `UserClient` və `PostClient` — iki ayrı interface
2. Hər biri üçün ayrı `RestClient` bean: fərqli base URL, fərqli headers
3. `AggregationService`-də hər ikisini inject et, bir metod hər ikisini çağırsın

**Tapşırıq 3 — Error handling + test**
1. `PaymentClient`-in `getPayment` metodu 404 qaytardıqda `PaymentNotFoundException` at
2. `@RestClientTest` ilə `MockRestServiceServer` istifadə edərək hər iki ssenarini test et (→ 103)

---

## Əlaqəli Mövzular

- [101 — RestClient](101-restclient.md) — @HttpExchange-in istifadə etdiyi HTTP altyapısı
- [103 — @RestClientTest](103-rest-client-test.md) — @HttpExchange interface-lərini test etmək
- [84 — WebFlux/WebClient](84-webflux.md) — Reactive adapter ilə @HttpExchange
- [72 — Spring Retry](72-retry.md) — Retry logic əlavə etmək
