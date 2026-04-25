# 05 — Spring Cloud OpenFeign

> **Seviyye:** Expert ⭐⭐⭐⭐


## Mündəricat
- [OpenFeign Nədir?](#openfeign-nədir)
- [@EnableFeignClients](#enablefeignclients)
- [@FeignClient Parametrləri](#feignclient-parametrləri)
- [Request Mapping](#request-mapping)
- [FeignClient Konfiqurasiyası](#feignclient-konfiqurasiyası)
- [Fallback](#fallback)
- [Error Decoder](#error-decoder)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## OpenFeign Nədir?

OpenFeign — HTTP client-i interface kimi yazmağa imkan verən declarative HTTP client kitabxanasıdır.

```java
// YANLIŞ — Manual RestTemplate (çox kod, error-prone)
@Service
public class PaymentService {
    private final RestTemplate restTemplate;

    public Payment getPayment(Long id) {
        try {
            return restTemplate.getForObject(
                "http://PAYMENT-SERVICE/api/payments/" + id,
                Payment.class
            );
        } catch (HttpClientErrorException e) {
            if (e.getStatusCode() == HttpStatus.NOT_FOUND) {
                throw new PaymentNotFoundException(id);
            }
            throw new ServiceException("Payment servisi xətası", e);
        }
    }

    public Payment createPayment(PaymentRequest request) {
        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_JSON);
        HttpEntity<PaymentRequest> entity = new HttpEntity<>(request, headers);
        return restTemplate.postForObject(
            "http://PAYMENT-SERVICE/api/payments",
            entity,
            Payment.class
        );
    }
}

// DOĞRU — OpenFeign ilə (deklarativ, təmiz)
@FeignClient(name = "payment-service")
public interface PaymentClient {

    @GetMapping("/api/payments/{id}")
    Payment getPayment(@PathVariable Long id);

    @PostMapping("/api/payments")
    Payment createPayment(@RequestBody PaymentRequest request);
}
```

---

## @EnableFeignClients

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-openfeign</artifactId>
</dependency>
```

```java
@SpringBootApplication
@EnableFeignClients    // Feign client-lərini aktivləşdir
public class OrderServiceApplication {
    public static void main(String[] args) {
        SpringApplication.run(OrderServiceApplication.class, args);
    }
}

// Xüsusi package-ləri göstər
@EnableFeignClients(basePackages = "com.myapp.clients")
// Və ya spesifik class-lar
@EnableFeignClients(clients = {PaymentClient.class, UserClient.class})
```

---

## @FeignClient Parametrləri

```java
@FeignClient(
    name = "payment-service",           // Servis adı (Eureka-dakı ad)
    url = "${payment.service.url}",     // Sabit URL (Eureka olmadan)
    path = "/api/v1",                   // Bütün metodlara əlavə edilən prefix
    configuration = PaymentFeignConfig.class,  // Xüsusi konfiqurasiya
    fallback = PaymentClientFallback.class,    // Fallback class
    fallbackFactory = PaymentClientFallbackFactory.class,  // Fallback factory
    qualifier = "paymentFeignClient"    // Bean qualifier
)
public interface PaymentClient {
    // metodlar...
}
```

### name vs url

```java
// Eureka ilə — name istifadə et (load balancing avtomatik)
@FeignClient(name = "payment-service")
public interface PaymentClient { }

// Eureka olmadan — sabit URL
@FeignClient(name = "payment-client", url = "http://payment-service:8082")
public interface PaymentClient { }

// Mühit dəyişəni ilə URL
@FeignClient(name = "payment-client", url = "${payment.service.base-url}")
public interface PaymentClient { }
```

---

## Request Mapping

### @GetMapping / @PostMapping / @PutMapping / @DeleteMapping

```java
@FeignClient(name = "payment-service")
public interface PaymentClient {

    // GET — @PathVariable
    @GetMapping("/payments/{id}")
    Payment getPayment(@PathVariable("id") Long id);

    // GET — @RequestParam
    @GetMapping("/payments")
    Page<Payment> getPayments(
        @RequestParam(value = "page", defaultValue = "0") int page,
        @RequestParam(value = "size", defaultValue = "20") int size,
        @RequestParam(value = "status", required = false) String status
    );

    // POST — @RequestBody
    @PostMapping("/payments")
    Payment createPayment(@RequestBody PaymentRequest request);

    // PUT — PathVariable + RequestBody
    @PutMapping("/payments/{id}")
    Payment updatePayment(@PathVariable Long id,
                          @RequestBody PaymentUpdateRequest request);

    // DELETE
    @DeleteMapping("/payments/{id}")
    void deletePayment(@PathVariable Long id);

    // Header əlavə et
    @GetMapping("/payments/{id}")
    Payment getPaymentWithAuth(
        @PathVariable Long id,
        @RequestHeader("Authorization") String token
    );

    // Bütün metodlara tətbiq olunan header
    // (Konfigurasiya ilə)
}
```

### @RequestMapping interfeys səviyyəsində

```java
// YANLIŞ — @RequestMapping interface-ə əlavə et (bəzən problemi var)
@FeignClient(name = "payment-service")
@RequestMapping("/api/v1")   // Bu Spring MVC-nin öz controller-ı kimi görə bilər!
public interface PaymentClient {
    @GetMapping("/payments")
    List<Payment> getPayments();
}

// DOĞRU — path parametrini @FeignClient-ə əlavə et
@FeignClient(name = "payment-service", path = "/api/v1")
public interface PaymentClient {
    @GetMapping("/payments")
    List<Payment> getPayments();
}
```

### ResponseEntity qaytarma

```java
@FeignClient(name = "payment-service")
public interface PaymentClient {

    // ResponseEntity — status code-a baxmaq lazımdırsa
    @GetMapping("/payments/{id}")
    ResponseEntity<Payment> getPaymentWithStatus(@PathVariable Long id);

    // Optional — null gəlsə boş Optional
    @GetMapping("/payments/search")
    Optional<Payment> findByReference(@RequestParam String reference);
}
```

---

## FeignClient Konfiqurasiyası

### Logger

```java
@Configuration
public class PaymentFeignConfig {

    // Feign log səviyyəsi
    @Bean
    public Logger.Level feignLogLevel() {
        // NONE — log yoxdur (production default)
        // BASIC — yalnız method, URL, status, time
        // HEADERS — BASIC + header-lər
        // FULL — hər şey (request/response body daxil)
        return Logger.Level.FULL;
    }
}
```

```yaml
# application.yml — Feign log üçün logger konfiq
logging:
  level:
    com.myapp.clients.PaymentClient: DEBUG    # Feign log üçün DEBUG lazımdır
```

### Encoder / Decoder

```java
@Configuration
public class GlobalFeignConfig {

    // Custom Jackson ObjectMapper (JSON encoder/decoder)
    @Bean
    public Encoder feignEncoder(ObjectMapper objectMapper) {
        return new JacksonEncoder(objectMapper);
    }

    @Bean
    public Decoder feignDecoder(ObjectMapper objectMapper) {
        return new JacksonDecoder(objectMapper);
    }
}
```

### Retryer

```java
@Configuration
public class PaymentFeignConfig {

    @Bean
    public Retryer retryer() {
        // period: ilk retry interval (ms)
        // maxPeriod: maksimum interval (ms)
        // maxAttempts: maksimum cəhd sayı
        return new Retryer.Default(
            100,    // 100ms
            1000,   // max 1000ms
            3       // 3 cəhd
        );
    }
}
```

### Request Interceptor (Token əlavə et)

```java
// Hər Feign sorğusuna Authorization header əlavə et
@Component
public class AuthRequestInterceptor implements RequestInterceptor {

    private final TokenProvider tokenProvider;

    public AuthRequestInterceptor(TokenProvider tokenProvider) {
        this.tokenProvider = tokenProvider;
    }

    @Override
    public void apply(RequestTemplate template) {
        String token = tokenProvider.getCurrentToken();
        if (token != null) {
            template.header("Authorization", "Bearer " + token);
        }
        // Correlation ID əlavə et
        template.header("X-Correlation-Id", MDC.get("traceId"));
    }
}
```

```java
// Xüsusi client üçün interceptor
@Configuration
public class PaymentFeignConfig {

    @Bean
    public RequestInterceptor paymentAuthInterceptor() {
        return template -> {
            template.header("X-Service-Token", "payment-service-secret");
            template.header("X-API-Version", "v2");
        };
    }
}
```

### Timeout Konfigurasiyası

```yaml
# application.yml
spring:
  cloud:
    openfeign:
      client:
        config:
          # Bütün Feign client-lər üçün default
          default:
            connect-timeout: 5000      # Bağlantı timeout (ms)
            read-timeout: 10000        # Oxuma timeout (ms)
            logger-level: BASIC

          # Spesifik client üçün
          payment-service:
            connect-timeout: 2000
            read-timeout: 5000
            logger-level: FULL
```

---

## Fallback

### Sadə Fallback Class

```java
// Fallback — payment servisi çöküşündə işə düşür
@Component
public class PaymentClientFallback implements PaymentClient {

    @Override
    public Payment getPayment(Long id) {
        // Default/cache-dən qaytarılan dəyər
        log.warn("Payment servisi əlçatmaz, fallback qaytarılır. id={}", id);
        return Payment.unavailable(id);
    }

    @Override
    public Payment createPayment(PaymentRequest request) {
        // Bu əməliyyatı fallback ilə tamamlamaq mümkün deyil
        throw new ServiceUnavailableException("Ödəniş servisi müvəqqəti əlçatmaz");
    }

    @Override
    public Page<Payment> getPayments(int page, int size, String status) {
        return Page.empty();
    }
}
```

```java
// @FeignClient-ə fallback əlavə et
@FeignClient(
    name = "payment-service",
    fallback = PaymentClientFallback.class
)
public interface PaymentClient {
    @GetMapping("/payments/{id}")
    Payment getPayment(@PathVariable Long id);
}
```

```yaml
# Fallback üçün Circuit Breaker aktivləşdirmə lazımdır
spring:
  cloud:
    openfeign:
      circuitbreaker:
        enabled: true    # Bu false olsa fallback işləmir!
```

### FallbackFactory — Exception Məlumatı ilə

```java
// FallbackFactory — xəta məlumatına görə fallback müəyyən etmək
@Component
public class PaymentClientFallbackFactory
        implements FallbackFactory<PaymentClient> {

    @Override
    public PaymentClient create(Throwable cause) {
        log.error("Payment servisi xətası: {}", cause.getMessage());

        return new PaymentClient() {
            @Override
            public Payment getPayment(Long id) {
                if (cause instanceof FeignException.NotFound) {
                    throw new PaymentNotFoundException(id);
                }
                if (cause instanceof FeignException.ServiceUnavailable) {
                    return Payment.pending(id);  // Gözləmədə qaytarılan dəyər
                }
                throw new ServiceException("Ödəniş servisi xətası", cause);
            }

            @Override
            public Payment createPayment(PaymentRequest request) {
                // Compensation logic
                throw new ServiceUnavailableException(
                    "Ödəniş servisi əlçatmaz: " + cause.getMessage()
                );
            }
        };
    }
}
```

```java
@FeignClient(
    name = "payment-service",
    fallbackFactory = PaymentClientFallbackFactory.class    // factory istifadə et
)
public interface PaymentClient {
    // ...
}
```

---

## Error Decoder

Default olaraq Feign 4xx/5xx status kodlarında `FeignException` atır. Custom `ErrorDecoder` ilə domain exception-lara çevirmək mümkündür.

```java
// YANLIŞ — default FeignException
try {
    paymentClient.getPayment(id);
} catch (FeignException.NotFound e) {
    // FeignException-ı tutmaq — implementation detail sızır
    throw new PaymentNotFoundException(id);
}

// DOĞRU — Custom ErrorDecoder
@Component
public class PaymentErrorDecoder implements ErrorDecoder {

    private final ErrorDecoder defaultDecoder = new Default();

    @Override
    public Exception decode(String methodKey, Response response) {
        switch (response.status()) {
            case 400:
                return new InvalidPaymentRequestException(
                    extractMessage(response)
                );
            case 401:
                return new UnauthorizedException("Ödəniş servisinə giriş rədd edildi");
            case 403:
                return new AccessDeniedException("Ödənişə icazə yoxdur");
            case 404:
                // URL-dən ID-ni çıxar
                Long paymentId = extractPaymentId(methodKey, response.request().url());
                return new PaymentNotFoundException(paymentId);
            case 409:
                return new DuplicatePaymentException(extractMessage(response));
            case 422:
                return new BusinessValidationException(extractMessage(response));
            case 429:
                // Rate limit — retry ilə birlikdə
                return new RateLimitExceededException("Çox sayda ödəniş sorğusu");
            case 503:
                return new ServiceUnavailableException("Ödəniş servisi müvəqqəti dayandırılıb");
            default:
                return defaultDecoder.decode(methodKey, response);
        }
    }

    private String extractMessage(Response response) {
        try {
            if (response.body() != null) {
                return Util.toString(response.body().asReader(StandardCharsets.UTF_8));
            }
        } catch (IOException e) {
            log.warn("Response body oxuna bilmədi", e);
        }
        return "Bilinməyən xəta";
    }

    private Long extractPaymentId(String methodKey, String url) {
        // URL-dən regex ilə ID çıxarma
        Pattern pattern = Pattern.compile("/payments/(\\d+)");
        Matcher matcher = pattern.matcher(url);
        if (matcher.find()) {
            return Long.parseLong(matcher.group(1));
        }
        return null;
    }
}
```

```java
// ErrorDecoder-i konfiqurasiyaya əlavə et
@Configuration
public class PaymentFeignConfig {

    @Bean
    public ErrorDecoder paymentErrorDecoder() {
        return new PaymentErrorDecoder();
    }
}

@FeignClient(
    name = "payment-service",
    configuration = PaymentFeignConfig.class
)
public interface PaymentClient {
    @GetMapping("/payments/{id}")
    Payment getPayment(@PathVariable Long id);
    // Artıq PaymentNotFoundException atılır — FeignException yox
}
```

---

## Tam İstifadə Nümunəsi

```java
// DTOs
public record PaymentRequest(Long orderId, BigDecimal amount, String currency) {}
public record Payment(Long id, Long orderId, BigDecimal amount, String status) {
    public static Payment unavailable(Long orderId) {
        return new Payment(null, orderId, null, "UNAVAILABLE");
    }
    public static Payment pending(Long orderId) {
        return new Payment(null, orderId, null, "PENDING");
    }
}

// Feign Client
@FeignClient(
    name = "payment-service",
    path = "/api/v1",
    configuration = PaymentFeignConfig.class,
    fallbackFactory = PaymentClientFallbackFactory.class
)
public interface PaymentClient {

    @GetMapping("/payments/{id}")
    Payment getPayment(@PathVariable Long id);

    @PostMapping("/payments")
    Payment createPayment(@RequestBody PaymentRequest request);

    @GetMapping("/payments")
    List<Payment> getPaymentsByOrderId(@RequestParam Long orderId);
}

// Service kullanımı
@Service
public class OrderService {

    private final PaymentClient paymentClient;

    public OrderService(PaymentClient paymentClient) {
        this.paymentClient = paymentClient;
    }

    public Order createOrder(OrderRequest request) {
        // Eureka-dan payment-service tapılır, sorğu göndərilir
        Payment payment = paymentClient.createPayment(
            new PaymentRequest(request.orderId(), request.amount(), "AZN")
        );

        if ("FAILED".equals(payment.status())) {
            throw new PaymentFailedException("Ödəniş uğursuz oldu");
        }

        return Order.create(request, payment);
    }
}
```

---

## İntervyu Sualları

**S: OpenFeign ilə RestTemplate/WebClient fərqi nədir?**
C: OpenFeign deklarativdir — interface yaz, implementasiya yazan Spring. RestTemplate imperativdir — hər sorğunu əl ilə yaz. WebClient reaktiv (non-blocking). Feign kod azaltır, oxunuşu asanlaşdırır, Eureka inteqrasiyası avtomatikdir. Ancaq WebFlux mühitdə WebClient üstündür.

**S: @FeignClient-in fallback işləməsi üçün nə lazımdır?**
C: `spring.cloud.openfeign.circuitbreaker.enabled=true` olmalıdır. Fallback class `@Component` olmalıdır. `@FeignClient(fallback=...)` göstərilməlidir. Fallback class Feign interface-ini implement etməlidir.

**S: FallbackFactory ilə Fallback fərqi nədir?**
C: `fallback` — sadə fallback, exception-a baxmır. `fallbackFactory` — exception-ı `Throwable cause` kimi alır, ona görə fərqli davranış seçə bilər. Exception tipinə görə (404 vs 503) fərqli cavab vermək lazımdırsa — factory istifadə et.

**S: ErrorDecoder nəyə lazımdır?**
C: Feign default olaraq HTTP 4xx/5xx üçün `FeignException` atır. ErrorDecoder ilə HTTP status kodlarını domain exception-lara çeviririk. Belə ki, servis 404 qaytarsa — `PaymentNotFoundException`, 422 qaytarsa — `BusinessValidationException` atılır.

**S: RequestInterceptor nə üçün istifadə olunur?**
C: Hər Feign sorğusuna avtomatik header/parametr əlavə etmək üçün. Ən çox istifadə: JWT token əlavə etmə (Authorization header), correlation ID, API version header. Bir dəfə konfiqurasiya et — bütün sorğulara tətbiq olunur.

**S: `name` parametri niyə vacibdir?**
C: Eureka ilə işlədikdə `name` — servis registry-dəki adla uyğun olmalıdır. Spring Cloud LoadBalancer bu ada görə Eureka-dan instance siyahısı alır. `url` verilsə — sabit URL istifadə olunur, Eureka ignored olunur.
