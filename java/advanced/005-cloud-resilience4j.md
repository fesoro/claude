# 005 — Spring Cloud Resilience4j
**Səviyyə:** Ekspert


## Mündəricat
- [Resilience4j Nədir?](#resilience4j-nədir)
- [@CircuitBreaker](#circuitbreaker)
- [@Retry](#retry)
- [@Bulkhead](#bulkhead)
- [@RateLimiter](#ratelimiter)
- [@TimeLimiter](#timelimiter)
- [fallbackMethod](#fallbackmethod)
- [Metric Exposure](#metric-exposure)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Resilience4j Nədir?

Resilience4j — Java 8 üçün hazırlanmış yüngül fault tolerance (nasazlıq dözümlülüğü) kitabxanasıdır. Netflix Hystrix-in Spring Boot 3.x əvəzidir.

```
Servis çöküşü ssenarisi:
┌──────────────┐     HTTP      ┌──────────────────┐
│ Order Service│ ────────────▶ │ Payment Service  │
└──────────────┘               │   (ÇÖKÜB!)       │
                               └──────────────────┘
                                        │
                     Nə baş verir?      │
                     - Sorğu gözləyir   │
                     - Timeout         │
                     - Thread bloklanır│
                     - Cascade failure │

Resilience4j ilə:
┌──────────────┐  CircuitBreaker ┌──────────────────┐
│ Order Service│ ───[AÇIQ]──────▶│ Payment Service  │
└──────────────┘                 └──────────────────┘
       │
       ▼ (fallback)
 Cached/Default response
 (Order Service sağlam qalır!)
```

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-circuitbreaker-resilience4j</artifactId>
</dependency>

<!-- Actuator metrics üçün -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-actuator</artifactId>
</dependency>

<!-- AOP — annotasiya əsaslı istifadə üçün -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-aop</artifactId>
</dependency>
```

---

## @CircuitBreaker

Circuit Breaker — elektrik dövrəsi açarı kimi işləyir: servis xəta verəndə əlaqəni kəsir.

### Hallar (States)

```
CLOSED (Qapalı) — Normal işlər
  Bütün sorğular keçir, xəta sayılır
  failureRateThreshold aşılarsa → OPEN-ə keçir

OPEN (Açıq) — Servis çöküb
  Bütün sorğular rədd edilir (fallback çağrılır)
  waitDurationInOpenState keçdikdən sonra → HALF_OPEN-ə keçir

HALF_OPEN (Yarım Açıq) — Test rejimi
  permittedNumberOfCallsInHalfOpenState sorğu buraxılır
  Bunlar uğurlu olsa → CLOSED-ə qayıdır
  Uğursuz olsa → yenidən OPEN-ə keçir

CLOSED → (xəta rate aşılır) → OPEN → (timeout) → HALF_OPEN → CLOSED/OPEN
```

### COUNT_BASED Sliding Window

```yaml
# application.yml
resilience4j:
  circuitbreaker:
    instances:
      paymentService:
        # Son N sorğuya bax (COUNT_BASED)
        sliding-window-type: COUNT_BASED
        sliding-window-size: 10           # Son 10 sorğu
        failure-rate-threshold: 50        # 50% xəta → OPEN
        slow-call-rate-threshold: 100     # 100% yavaş çağırış → OPEN (default deaktiv)
        slow-call-duration-threshold: 2s  # 2s-dən yavaş = yavaş çağırış
        minimum-number-of-calls: 5        # Ən azı 5 sorğu olsun ki, hesablansın

        # OPEN vəziyyətdə gözləmə müddəti
        wait-duration-in-open-state: 10s  # 10 saniyə OPEN qal

        # HALF_OPEN-də test sorğu sayı
        permitted-number-of-calls-in-half-open-state: 3

        # Avtomatik HALF_OPEN-ə keç (true = gözlə, false = avtomatik keç)
        automatic-transition-from-open-to-half-open-enabled: true

        # Hansı exception-lar xəta sayılır
        record-exceptions:
          - java.io.IOException
          - java.util.concurrent.TimeoutException
          - feign.FeignException

        # Hansı exception-lar sayılmır (ignore)
        ignore-exceptions:
          - com.myapp.exception.BusinessValidationException
          - com.myapp.exception.ResourceNotFoundException
```

### TIME_BASED Sliding Window

```yaml
resilience4j:
  circuitbreaker:
    instances:
      inventoryService:
        # Son N saniyənin sorğularına bax
        sliding-window-type: TIME_BASED
        sliding-window-size: 60          # Son 60 saniyə
        failure-rate-threshold: 50
        minimum-number-of-calls: 10
        wait-duration-in-open-state: 30s
```

### Java Annotasiyası

```java
@Service
@Slf4j
public class PaymentService {

    private final PaymentClient paymentClient;

    public PaymentService(PaymentClient paymentClient) {
        this.paymentClient = paymentClient;
    }

    @CircuitBreaker(name = "paymentService", fallbackMethod = "getPaymentFallback")
    public Payment getPayment(Long id) {
        log.info("Payment alınır: {}", id);
        return paymentClient.getPayment(id);
    }

    // Fallback metod — eyni parametrlər + Exception
    private Payment getPaymentFallback(Long id, Exception e) {
        log.warn("Circuit breaker açıq, fallback qaytarılır. id={}, xəta={}", id, e.getMessage());
        return Payment.unavailable(id);
    }

    @CircuitBreaker(name = "paymentService", fallbackMethod = "createPaymentFallback")
    public Payment createPayment(PaymentRequest request) {
        return paymentClient.createPayment(request);
    }

    // Fallback — xəta tipinə görə
    private Payment createPaymentFallback(PaymentRequest request, CallNotPermittedException e) {
        // Dövrə açıqdır — dərhal rədd et
        throw new ServiceUnavailableException("Ödəniş servisi müvəqqəti əlçatmaz (circuit open)");
    }

    private Payment createPaymentFallback(PaymentRequest request, Exception e) {
        // Digər xətalarda
        throw new ServiceException("Ödəniş işlənə bilmədi", e);
    }
}
```

---

## @Retry

Retry — müvəqqəti xətalarda sorğunu təkrar edir.

```yaml
resilience4j:
  retry:
    instances:
      paymentService:
        max-attempts: 3              # Maksimum 3 cəhd (ilk + 2 retry)
        wait-duration: 500ms         # Retry-lər arası gözləmə
        enable-exponential-backoff: true     # Exponential backoff
        exponential-backoff-multiplier: 2    # Hər dəfə 2x artır (500ms → 1s → 2s)
        exponential-max-wait-duration: 10s  # Maksimum gözləmə

        # Hansı exception-larda retry
        retry-exceptions:
          - java.io.IOException
          - java.net.SocketTimeoutException
          - feign.RetryableException

        # Hansı exception-larda retry etmə
        ignore-exceptions:
          - com.myapp.exception.BusinessValidationException
```

```java
@Service
public class OrderService {

    @Retry(name = "paymentService", fallbackMethod = "createPaymentFallback")
    @CircuitBreaker(name = "paymentService", fallbackMethod = "createPaymentFallback")
    public Payment createPayment(PaymentRequest request) {
        // Əvvəlcə Circuit Breaker yoxlanır
        // Sonra Retry aktiv olur
        return paymentClient.createPayment(request);
    }

    // Retry tükəndi + Circuit Breaker açıq
    private Payment createPaymentFallback(PaymentRequest request, Exception e) {
        log.error("Bütün retry-lər tükəndi: {}", e.getMessage());
        // DLQ-ya at, audit log yaz, istifadəçiyə xəbər ver
        eventPublisher.publishEvent(new PaymentFailedEvent(request));
        throw new ServiceUnavailableException("Ödəniş sistemə əlavə edildi, tezliklə işlənəcək");
    }
}
```

### Annotasiya sırası vacibdir!

```java
// YANLIŞ sıralama — Retry işləmir
@CircuitBreaker(name = "svc")    // CB açıqsa Retry-a çatmır
@Retry(name = "svc")
public void doSomething() { }

// DOĞRU sıralama — Retry CB-dən əvvəl tətbiq olunur
@Retry(name = "svc")             // Əvvəl Retry
@CircuitBreaker(name = "svc")   // Sonra CB (xarici)
public void doSomething() { }
```

---

## @Bulkhead

Bulkhead — gəminin bölmə divarı metaforu. Resursları izolyasiya edir ki, bir servisin problemli işi digərini etkiləməsin.

```yaml
resilience4j:
  bulkhead:
    instances:
      paymentService:
        max-concurrent-calls: 10      # Eyni anda maksimum 10 sorğu
        max-wait-duration: 500ms      # Boş yer gözləmə vaxtı (0 = gözləmə)
```

```java
@Service
public class PaymentService {

    @Bulkhead(name = "paymentService", type = Bulkhead.Type.SEMAPHORE)
    public Payment getPayment(Long id) {
        // Eyni anda maksimum 10 thread bu metodu icra edə bilər
        return paymentClient.getPayment(id);
    }
}
```

### Thread Pool Bulkhead

```yaml
resilience4j:
  thread-pool-bulkhead:
    instances:
      reportService:
        core-thread-pool-size: 5       # Əsas thread sayı
        max-thread-pool-size: 10       # Maksimum thread sayı
        queue-capacity: 100            # Gözləmə queue-si
        keep-alive-duration: 20ms
```

```java
@Bulkhead(name = "reportService", type = Bulkhead.Type.THREADPOOL)
public CompletableFuture<Report> generateReport(Long reportId) {
    // Ayrı thread pool-da işləyir
    return CompletableFuture.supplyAsync(() -> reportClient.generate(reportId));
}
```

---

## @RateLimiter

Rate Limiter — müəyyən müddətdə izin verilən sorğu sayını məhdudlaşdırır.

```yaml
resilience4j:
  rate-limiter:
    instances:
      paymentService:
        limit-for-period: 10         # Hər period-da 10 sorğu
        limit-refresh-period: 1s     # Period = 1 saniyə
        timeout-duration: 500ms      # Token gözləmə timeout-u
```

```java
@Service
public class ExternalApiService {

    @RateLimiter(name = "externalApi", fallbackMethod = "rateLimitFallback")
    public ApiResponse callExternalApi(String data) {
        return externalApiClient.call(data);
    }

    private ApiResponse rateLimitFallback(String data, RequestNotPermitted e) {
        log.warn("Rate limit aşıldı, sorğu rədd edildi");
        throw new RateLimitExceededException("Çox sürətli sorğu, bir az gözləyin");
    }
}
```

---

## @TimeLimiter

TimeLimiter — asinxron əməliyyatlar üçün timeout müəyyən edir.

```yaml
resilience4j:
  timelimiter:
    instances:
      paymentService:
        timeout-duration: 3s        # 3 saniyə timeout
        cancel-running-future: true # Timeout olarsa Future-u ləğv et
```

```java
@Service
public class AsyncPaymentService {

    @TimeLimiter(name = "paymentService", fallbackMethod = "timeoutFallback")
    @CircuitBreaker(name = "paymentService")
    public CompletableFuture<Payment> processPaymentAsync(PaymentRequest request) {
        // TimeLimiter CompletableFuture ilə işləyir
        return CompletableFuture.supplyAsync(() ->
            paymentClient.processPayment(request)
        );
    }

    private CompletableFuture<Payment> timeoutFallback(
            PaymentRequest request, TimeoutException e) {
        log.warn("Ödəniş əməliyyatı timeout oldu");
        return CompletableFuture.completedFuture(Payment.pending(request.orderId()));
    }
}
```

---

## fallbackMethod

Fallback metod yazarkən qaydalara riayət etmək vacibdir:

```java
@Service
public class OrderService {

    // ANA METOD
    @CircuitBreaker(name = "inventory", fallbackMethod = "checkInventoryFallback")
    public InventoryStatus checkInventory(Long productId, int quantity) {
        return inventoryClient.check(productId, quantity);
    }

    // DOĞRU Fallback — eyni parametrlər + Exception tip
    // Fallback metod ANA METODLA eyni class-da olmalıdır
    private InventoryStatus checkInventoryFallback(Long productId, int quantity, Exception e) {
        log.warn("Anbar yoxlanması uğursuz: productId={}, xəta={}", productId, e.getMessage());
        // Optimist fallback — stokda var fərz et
        return InventoryStatus.ASSUMED_AVAILABLE;
    }

    // Çoxlu fallback — xəta tipinə görə
    // Daha spesifik exception-lar əvvəl gəlməlidir
    private InventoryStatus checkInventoryFallback(Long productId, int quantity,
                                                    CallNotPermittedException e) {
        // Circuit açıqdır
        return InventoryStatus.SERVICE_UNAVAILABLE;
    }

    private InventoryStatus checkInventoryFallback(Long productId, int quantity,
                                                    TimeoutException e) {
        // Timeout
        return InventoryStatus.TIMEOUT;
    }
}
```

### Fallback Zənciri

```java
// Fallback-in fallback-ı
@CircuitBreaker(name = "primary", fallbackMethod = "primaryFallback")
public Data fetchData(String key) {
    return primaryClient.fetch(key);
}

// Birinci fallback — ikinci servisi çağırır
@CircuitBreaker(name = "secondary", fallbackMethod = "finalFallback")
private Data primaryFallback(String key, Exception e) {
    log.warn("Primary servis çöküb, secondary-ə keçilir");
    return secondaryClient.fetch(key);  // Bu da circuit breaker-ə malikdir
}

// Son fallback — hər iki servis çöküb
private Data finalFallback(String key, Exception e) {
    log.error("Bütün servisler çöküb! key={}", key);
    return Data.fromCache(key);  // Cache-dən qaytar
}
```

---

## Metric Exposure

```yaml
# application.yml — metrics açmaq
management:
  endpoints:
    web:
      exposure:
        include: health, metrics, circuitbreakers, retries
  endpoint:
    health:
      show-details: always

  health:
    circuitbreakers:
      enabled: true
    retryevents:
      enabled: true
```

```bash
# Circuit breaker statusu
GET /actuator/health
# Response:
# {
#   "components": {
#     "circuitBreakers": {
#       "details": {
#         "paymentService": {
#           "details": {
#             "failureRate": "20.0%",
#             "state": "CLOSED"
#           }
#         }
#       }
#     }
#   }
# }

# Detailed metrics
GET /actuator/metrics/resilience4j.circuitbreaker.state
GET /actuator/metrics/resilience4j.circuitbreaker.failure.rate
GET /actuator/metrics/resilience4j.retry.calls
GET /actuator/metrics/resilience4j.bulkhead.available.concurrent.calls
GET /actuator/metrics/resilience4j.ratelimiter.available.permissions
```

```yaml
# Prometheus üçün metrics
management:
  metrics:
    tags:
      application: ${spring.application.name}
```

### Prometheus Metrics Nümunəsi

```
# Circuit Breaker state (0=CLOSED, 1=OPEN, 2=HALF_OPEN)
resilience4j_circuitbreaker_state{application="order-service",name="paymentService"} 0.0

# Uğurlu çağırış sayı
resilience4j_circuitbreaker_calls_total{application="order-service",kind="successful",name="paymentService"} 150

# Uğursuz çağırış sayı
resilience4j_circuitbreaker_calls_total{application="order-service",kind="failed",name="paymentService"} 5

# Retry uğurlu
resilience4j_retry_calls_total{application="order-service",kind="successful_with_retry",name="paymentService"} 12
```

---

## Kombinasiya Nümunəsi

```java
@Service
@Slf4j
public class ResilientPaymentService {

    private final PaymentClient paymentClient;
    private final CacheManager cacheManager;

    @RateLimiter(name = "paymentService")
    @Bulkhead(name = "paymentService")
    @CircuitBreaker(name = "paymentService", fallbackMethod = "getPaymentFallback")
    @Retry(name = "paymentService")
    public Payment getPayment(Long id) {
        log.info("Payment alınır: id={}", id);
        return paymentClient.getPayment(id);
    }

    private Payment getPaymentFallback(Long id, Exception e) {
        // 1. Cache yoxla
        Cache cache = cacheManager.getCache("payments");
        Payment cached = cache != null ? cache.get(id, Payment.class) : null;

        if (cached != null) {
            log.info("Payment cache-dən qaytarıldı: id={}", id);
            return cached;
        }

        // 2. Default dəyər
        log.warn("Payment əlçatmaz, default qaytarılır: id={}", id);
        return Payment.unavailable(id);
    }
}
```

```yaml
# Tam konfigurasiya nümunəsi
resilience4j:
  circuitbreaker:
    instances:
      paymentService:
        sliding-window-type: COUNT_BASED
        sliding-window-size: 10
        failure-rate-threshold: 50
        minimum-number-of-calls: 5
        wait-duration-in-open-state: 10s
        permitted-number-of-calls-in-half-open-state: 3
        automatic-transition-from-open-to-half-open-enabled: true

  retry:
    instances:
      paymentService:
        max-attempts: 3
        wait-duration: 500ms
        enable-exponential-backoff: true
        exponential-backoff-multiplier: 2

  bulkhead:
    instances:
      paymentService:
        max-concurrent-calls: 20
        max-wait-duration: 100ms

  rate-limiter:
    instances:
      paymentService:
        limit-for-period: 50
        limit-refresh-period: 1s
        timeout-duration: 500ms

  timelimiter:
    instances:
      paymentService:
        timeout-duration: 5s
```

---

## İntervyu Sualları

**S: Circuit Breaker-in 3 vəziyyəti hansılardır?**
C: CLOSED (normal iş — sorğular keçir, xəta sayılır), OPEN (xəta həddindən artıq — sorğular rədd edilir), HALF_OPEN (test rejimi — az sayda sorğu buraxılır, nəticəyə görə CLOSED/OPEN-ə keçilir).

**S: failureRateThreshold nə deməkdir?**
C: Son N sorğunun neçə faizi xəta ilə nəticələnibsə OPEN-ə keçmə həddi. 50% threshold + 10 sliding window → 10 sorğudan 5-i xəta olarsa OPEN-ə keçir. `minimum-number-of-calls` tam olmayana qədər hesablanmır.

**S: Retry ilə CircuitBreaker birlikdə istifadə zamanı sıra nədir?**
C: Annotasiyaların tətbiq sırası: inner-dan outer-a doğru. `@Retry` `@CircuitBreaker`-dən əvvəl yazılsa, retry CB-nin içindədir. Yəni: 1 sorğu gəlir → CB yoxlanır → CB qapalıdırsa sorğu keçir → Retry 3 dəfə cəhd edir → CB bu 3 cəhdi sayır.

**S: Bulkhead nəyə lazımdır?**
C: İzolyasiya üçün. Yavaş Payment servisinin 100 thread-i bloklaması nəticəsində Order servisi də cavab verə bilmir. Bulkhead Payment servisini 10 concurrent sorğu ilə məhdudlaşdırır — qalan thread-lər digər işlər üçün serbest qalır.

**S: RateLimiter nəyin fərqi var Bulkhead-dən?**
C: Bulkhead — concurrent (eyni anda) sorğu sayını məhdudlaşdırır. RateLimiter — zaman dövründə (saniyə, dəqiqə) sorğu sayını məhdudlaşdırır. RateLimiter 10 req/s = saniyəyə 10 sorğu, Bulkhead 10 = eyni anda 10 sorğu.

**S: @TimeLimiter nə vaxt lazımdır?**
C: Asinxron (`CompletableFuture`) əməliyyatlar üçün timeout. Sinxron metodlarda `@CircuitBreaker`-in `slow-call-duration-threshold`-u istifadə olunur. TimeLimiter `CompletableFuture`-u vaxt keçdikdə ləğv edə bilər (`cancel-running-future: true`).
