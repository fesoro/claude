# 72 — Spring Retry — Geniş İzah

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Spring Retry nədir?](#spring-retry-nədir)
2. [@Retryable annotation](#retryable-annotation)
3. [RetryTemplate](#retrytemplate)
4. [Backoff strategiyaları](#backoff-strategiyaları)
5. [@Recover — fallback](#recover--fallback)
6. [Resilience4j ilə müqayisə](#resilience4j-ilə-müqayisə)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Spring Retry nədir?

**Spring Retry** — müvəqqəti xətalarda metodu avtomatik yenidən çağıran framework.

```
Nə vaxt retry lazımdır?
  ├── Şəbəkə timeout-u (HttpClient, WebClient)
  ├── DB connection bağlantı xətası
  ├── External API müvəqqəti 503
  ├── Kafka consumer geçici xəta
  └── Optimistic locking conflict

Nə vaxt retry lazım DEYİL?
  ├── 4xx (Bad Request, Not Found) — retry faydasız
  ├── Business logic xətaları (validation)
  └── Idempotent olmayan əməliyyatlar (uğurlu amma xəta ilə bitən)
```

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.retry</groupId>
    <artifactId>spring-retry</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework</groupId>
    <artifactId>spring-aspects</artifactId>
</dependency>
```

```java
// ─── Enable ────────────────────────────────────────────
@SpringBootApplication
@EnableRetry
public class Application { }
```

---

## @Retryable annotation

```java
@Service
public class PaymentService {

    // ─── Sadə retry ───────────────────────────────────
    @Retryable(
        retryFor = {PaymentGatewayException.class, ConnectTimeoutException.class},
        maxAttempts = 3,
        backoff = @Backoff(delay = 1000) // 1s gözlə
    )
    public PaymentResult processPayment(PaymentRequest request) {
        return paymentGateway.charge(request);
    }

    // ─── Exponential backoff ───────────────────────────
    @Retryable(
        retryFor = {ServiceUnavailableException.class},
        maxAttempts = 5,
        backoff = @Backoff(
            delay = 500,         // İlk gözləmə: 500ms
            multiplier = 2.0,    // Hər dəfə 2x artır
            maxDelay = 10000     // Maksimum 10s
        )
        // Cəhd 1: 500ms gözlə
        // Cəhd 2: 1000ms gözlə
        // Cəhd 3: 2000ms gözlə
        // Cəhd 4: 4000ms gözlə
        // Cəhd 5: son cəhd
    )
    public ExternalServiceResponse callExternalService(String payload) {
        return externalClient.send(payload);
    }

    // ─── Random jitter ────────────────────────────────
    @Retryable(
        retryFor = Exception.class,
        maxAttempts = 4,
        backoff = @Backoff(
            delay = 1000,
            multiplier = 1.5,
            random = true  // Jitter — thundering herd problem-inin həlli
        )
    )
    public void syncInventory(String productId) {
        inventoryService.sync(productId);
    }

    // ─── Şərtli retry — retryFor / noRetryFor ────────
    @Retryable(
        retryFor = {IOException.class, HttpServerErrorException.class},
        noRetryFor = {HttpClientErrorException.class}, // 4xx → retry etmə
        maxAttempts = 3,
        backoff = @Backoff(delay = 2000)
    )
    public ApiResponse callApi(String endpoint) {
        return restClient.get(endpoint);
    }

    // ─── maxAttempts = 1 → retry yoxdur ──────────────
    @Retryable(maxAttempts = 1)
    public void noRetry() {
        // İstisna atılarsa → birbaşa @Recover-ə keçir
    }
}
```

---

## RetryTemplate

```java
// ─── Proqramatik retry ────────────────────────────────
@Configuration
public class RetryConfig {

    @Bean
    public RetryTemplate retryTemplate() {
        return RetryTemplate.builder()
            .maxAttempts(3)
            .exponentialBackoff(500, 2.0, 10000)
            .retryOn(PaymentGatewayException.class)
            .retryOn(ConnectTimeoutException.class)
            .notRetryOn(IllegalArgumentException.class)
            .build();
    }

    @Bean
    public RetryTemplate retryWithListener() {
        RetryTemplate template = new RetryTemplate();

        // Backoff policy
        ExponentialBackOffPolicy backoff = new ExponentialBackOffPolicy();
        backoff.setInitialInterval(1000);
        backoff.setMultiplier(2.0);
        backoff.setMaxInterval(30_000);
        template.setBackOffPolicy(backoff);

        // Retry policy
        SimpleRetryPolicy retryPolicy = new SimpleRetryPolicy();
        retryPolicy.setMaxAttempts(5);
        template.setRetryPolicy(retryPolicy);

        // Listener
        template.registerListener(new RetryListenerSupport() {
            @Override
            public <T, E extends Throwable> void onError(
                    RetryContext context, RetryCallback<T, E> callback, Throwable throwable) {
                log.warn("Retry cəhdi #{}: {}",
                    context.getRetryCount(),
                    throwable.getMessage());
            }

            @Override
            public <T, E extends Throwable> void close(
                    RetryContext context, RetryCallback<T, E> callback, Throwable throwable) {
                if (throwable != null) {
                    log.error("Bütün retry-lar uğursuz oldu: {}", throwable.getMessage());
                }
            }
        });

        return template;
    }
}

// ─── RetryTemplate istifadəsi ─────────────────────────
@Service
public class OrderSyncService {

    private final RetryTemplate retryTemplate;

    public void syncOrder(Order order) {
        retryTemplate.execute(context -> {
            log.info("Cəhd #{}", context.getRetryCount() + 1);
            return externalOrderService.sync(order);
        });
    }

    // Fallback ilə
    public SyncResult syncOrderWithFallback(Order order) {
        return retryTemplate.execute(
            context -> externalOrderService.sync(order),    // retry callback
            context -> SyncResult.failed("Max retry keçildi") // recovery callback
        );
    }

    // Exception type-a görə fərqli davranış
    public void smartRetry(String jobId) {
        Map<Class<? extends Throwable>, Boolean> retryExceptions = Map.of(
            NetworkException.class, true,    // retry et
            TimeoutException.class, true,    // retry et
            ValidationException.class, false // retry etmə
        );

        SimpleRetryPolicy policy = new SimpleRetryPolicy(3, retryExceptions);

        RetryTemplate template = RetryTemplate.builder()
            .customPolicy(policy)
            .build();

        template.execute(ctx -> jobService.run(jobId));
    }
}
```

---

## Backoff strategiyaları

```java
@Configuration
public class BackoffPoliciesConfig {

    // ─── FixedBackOff — sabit gözləmə ────────────────
    @Bean
    public RetryTemplate fixedRetry() {
        FixedBackOffPolicy backoff = new FixedBackOffPolicy();
        backoff.setBackOffPeriod(2000); // 2s gözlə

        RetryTemplate template = new RetryTemplate();
        template.setBackOffPolicy(backoff);
        template.setRetryPolicy(new SimpleRetryPolicy(3));
        return template;
    }

    // ─── ExponentialBackOff ───────────────────────────
    @Bean
    public RetryTemplate exponentialRetry() {
        return RetryTemplate.builder()
            .exponentialBackoff(
                500,    // initial interval
                2.0,    // multiplier
                30000   // max interval
            )
            .maxAttempts(5)
            .build();
    }

    // ─── ExponentialRandomBackOff — jitter ilə ────────
    @Bean
    public RetryTemplate exponentialJitterRetry() {
        ExponentialRandomBackOffPolicy backoff = new ExponentialRandomBackOffPolicy();
        backoff.setInitialInterval(1000);
        backoff.setMultiplier(2.0);
        backoff.setMaxInterval(30_000);

        // Jitter: delay × (1 ± random * multiplier)
        // Thundering herd problem: eyni anda çox client retry edərsə
        // bütün serveri zərbə vurur — jitter scatter edir

        RetryTemplate template = new RetryTemplate();
        template.setBackOffPolicy(backoff);
        template.setRetryPolicy(new SimpleRetryPolicy(4));
        return template;
    }

    // ─── UniformRandomBackOff — tam random ───────────
    @Bean
    public RetryTemplate uniformRandomRetry() {
        UniformRandomBackOffPolicy backoff = new UniformRandomBackOffPolicy();
        backoff.setMinBackOffPeriod(500);
        backoff.setMaxBackOffPeriod(5000); // 500ms-5000ms arası random

        RetryTemplate template = new RetryTemplate();
        template.setBackOffPolicy(backoff);
        template.setRetryPolicy(new SimpleRetryPolicy(3));
        return template;
    }
}
```

---

## @Recover — fallback

```java
@Service
public class NotificationService {

    // ─── @Recover — bütün retry-lar uğursuz olduqda ──
    @Retryable(
        retryFor = {EmailSendException.class},
        maxAttempts = 3,
        backoff = @Backoff(delay = 2000)
    )
    public void sendEmail(String to, String subject, String body) {
        emailClient.send(to, subject, body);
        // Uğurlu olduqda → normal return
        // Exception atıldıqda → retry
        // Bütün retry uğursuz → @Recover
    }

    // Eyni metod imzası (exception tipi əlavə)
    @Recover
    public void recoverEmailSend(EmailSendException ex, String to,
                                  String subject, String body) {
        log.error("Email göndərilə bilmədi: {} → {}", to, ex.getMessage());
        // Alternativ kanal
        smsService.sendNotification(to, "Email xətası: " + subject);
        // Ya da dead letter queue-ya göndər
        deadLetterService.storeFailedEmail(to, subject, body);
    }

    // ─── Qaytarma tipi olan recover ──────────────────
    @Retryable(
        retryFor = {ApiException.class},
        maxAttempts = 3,
        backoff = @Backoff(delay = 1000)
    )
    public ProductInfo fetchProductInfo(String productId) {
        return externalCatalog.getProduct(productId);
    }

    @Recover
    public ProductInfo recoverFetchProduct(ApiException ex, String productId) {
        log.warn("External catalog əlçatmazdır, cache istifadə edilir: {}", productId);
        // Cache-dən ya da local DB-dən
        return productCacheService.getFromCache(productId)
            .orElseThrow(() -> new ProductNotFoundException(productId));
    }

    // ─── Multiple recover metodları ──────────────────
    @Retryable(
        retryFor = {ConnectException.class, HttpServerErrorException.class},
        maxAttempts = 3
    )
    public OrderStatus checkOrderStatus(String orderId) {
        return externalOrderService.getStatus(orderId);
    }

    @Recover
    public OrderStatus recoverFromNetworkError(ConnectException ex, String orderId) {
        log.warn("Şəbəkə xətası — local DB-dən oxunur: {}", orderId);
        return orderRepository.findById(orderId)
            .map(Order::getStatus)
            .orElse(OrderStatus.UNKNOWN);
    }

    @Recover
    public OrderStatus recoverFromServerError(HttpServerErrorException ex,
                                               String orderId) {
        log.error("Server xətası ({}): {}", ex.getStatusCode(), orderId);
        return OrderStatus.UNKNOWN;
    }
}
```

---

## Resilience4j ilə müqayisə

```java
// ─── Spring Retry vs Resilience4j ────────────────────

// Spring Retry — sadə, spring-native
@Retryable(maxAttempts = 3, backoff = @Backoff(delay = 1000))
public String simpleRetry() {
    return externalService.call();
}

// Resilience4j — daha güclü, Circuit Breaker + Retry
@CircuitBreaker(name = "external", fallbackMethod = "fallback")
@Retry(name = "external")
@Bulkhead(name = "external")
@TimeLimiter(name = "external")
public String resilience4jRetry() {
    return externalService.call();
}

/*
─────────────────────────────────────────────────────
Xüsusiyyət          Spring Retry      Resilience4j
─────────────────────────────────────────────────────
Sadəlik             ✅ Sadə           Mürəkkəb
Circuit Breaker     ❌ Yoxdur         ✅ Var
Bulkhead            ❌ Yoxdur         ✅ Var
Rate Limiter        ❌ Yoxdur         ✅ Var
Time Limiter        ❌ Yoxdur         ✅ Var
Actuator metrics    Limitli           ✅ Zengin
AOP support         ✅ Var            ✅ Var
─────────────────────────────────────────────────────

Tövsiyə:
  Sadə retry → Spring Retry
  Circuit breaker, bulkhead lazımdırsa → Resilience4j
  Spring Cloud → Resilience4j (Spring Cloud OpenFeign default)
*/

// ─── Resilience4j Retry nümunəsi ─────────────────────
@Configuration
public class Resilience4jRetryConfig {

    @Bean
    public RetryRegistry retryRegistry() {
        return RetryRegistry.of(RetryConfig.custom()
            .maxAttempts(3)
            .waitDuration(Duration.ofMillis(500))
            .exponentialBackoffMultiplier(2.0)
            .retryExceptions(ConnectException.class, TimeoutException.class)
            .ignoreExceptions(IllegalArgumentException.class)
            .build());
    }
}
```

---

## İntervyu Sualları

### 1. Spring Retry necə işləyir?
**Cavab:** Spring AOP istifadə edir — `@Retryable` annotasiyalı metod proxy ilə bürünür. Exception atıldıqda backoff policy gözləyir, sonra yenidən çağırır. `maxAttempts` limitinə çatdıqda ya exception rethrow olunur, ya da `@Recover` metodu çağırılır. `@EnableRetry` AspectJ-ni aktivləşdirir.

### 2. Exponential backoff niyə lazımdır?
**Cavab:** Sabit interval (fixed backoff) ilə çox client eyni anda retry edərsə server yenidən həmin yükü alır — "thundering herd problem". Exponential backoff — hər uğursuz cəhddə gözləmə vaxtını artırır (1s → 2s → 4s). Jitter (random) — eyni servisten gələn retry-lar scatter olur, peak load azalır. Bu, server-ə bərpa üçün vaxt verir.

### 3. @Recover metodu hansı şərtlərə uyğun olmalıdır?
**Cavab:** Eyni qaytarma tipinə malik olmalıdır. İlk parametr exception tipi olmalıdır (hansı exception-a recovery edir). Sonrakı parametrlər `@Retryable` metodun parametrləri ilə eyni olmalıdır (eyni sıra). Eyni sinifdə olmalıdır. Bir neçə `@Recover` müxtəlif exception tipləri üçün ola bilər — Spring ən spesifik tipi seçir.

### 4. Spring Retry vs Resilience4j fərqi?
**Cavab:** Spring Retry — sadə retry + backoff, spring-native, az konfigurасiya. Resilience4j — retry + circuit breaker + bulkhead + rate limiter + time limiter; daha tam fault tolerance library. Resilience4j Circuit Breaker — servis davamlı xəta verərsə çağırışları kəsir (open state), bərpa gözləyir (half-open). Spring Retry circuit breaker yoxdur. Microservice-lərdə Resilience4j tövsiyə edilir.

### 5. Idempotency niyə retry-da vacibdir?
**Cavab:** Retry-da eyni əməliyyat birdən çox icra olunur. Əgər əməliyyat idempotent deyilsə (ödəniş, email göndər) — duplicate problem. Həll: (1) İdempotency key — eyni key ilə ikinci çağırış əvvəlkilin nəticəsini qaytarır; (2) Verilənlər bazasında `ON CONFLICT DO NOTHING`; (3) Unique constraint + retry yalnız exception tipinə görə. `@Retryable` istifadə edərkən metodun idempotent olmasını təmin edin.

*Son yenilənmə: 2026-04-10*
