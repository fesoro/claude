# 73 — Rate Limiting & Bucket4j — Geniş İzah

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [Rate Limiting nədir?](#rate-limiting-nədir)
2. [Bucket4j — Token Bucket alqoritmi](#bucket4j--token-bucket-alqoritmi)
3. [Spring Boot ilə Bucket4j](#spring-boot-ilə-bucket4j)
4. [Redis ilə Distributed Rate Limiting](#redis-ilə-distributed-rate-limiting)
5. [Spring Cloud Gateway ilə Rate Limiting](#spring-cloud-gateway-ilə-rate-limiting)
6. [Rate Limiting strategiyaları](#rate-limiting-strategiyaları)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Rate Limiting nədir?

**Rate Limiting** — bir client-in müəyyən vaxt ərzindəki maksimum sorğu sayını məhdudlaşdırmaq.

```
Rate Limiting olmadan:
  → DDoS hücumları — server çökür
  → API abuzus — botu yüklər sistemi
  → Fair use pozulması — bir user hamının resursunu yeyir
  → Cost spike (cloud) — milyonlarla sorğu = böyük hesab

Rate Limiting ilə:
  → Müştəri: 100 req/dəq (standard plan)
  → Premium müştəri: 1000 req/dəq
  → Public API: 10 req/dəq (anonim)

Alqoritmlər:
  → Token Bucket — ən çevik (burst allows, refill üçün)
  → Leaky Bucket — sabit çıxış sürəti
  → Fixed Window — ən sadə, edge case var
  → Sliding Window — daha dəqiq, daha mürəkkəb
```

---

## Bucket4j — Token Bucket alqoritmi

```
Token Bucket:
  [🪣 Bucket] ← Hər saniyə N token əlavə olunur
  
  Request gəlir → 1 token çıxarılır → icra olunur
  Token yoxdur → 429 Too Many Requests
  
  Bucket capacity: 10 token
  Refill: saniyədə 1 token
  
  Burst: 10 sorğu dərhal icra oluna bilər (token var)
  Sonra: saniyədə 1 sorğu (refill sürəti)
```

```xml
<!-- pom.xml -->
<dependency>
    <groupId>com.bucket4j</groupId>
    <artifactId>bucket4j-core</artifactId>
    <version>8.7.0</version>
</dependency>
<!-- Redis ilə distributed -->
<dependency>
    <groupId>com.bucket4j</groupId>
    <artifactId>bucket4j-redis</artifactId>
    <version>8.7.0</version>
</dependency>
```

---

## Spring Boot ilə Bucket4j

```java
// ─── Sadə in-memory rate limiter ─────────────────────
@Component
public class RateLimiterService {

    // Hər client üçün ayrı bucket
    private final Map<String, Bucket> buckets = new ConcurrentHashMap<>();

    public Bucket getBucketForClient(String clientId) {
        return buckets.computeIfAbsent(clientId, key -> createBucket());
    }

    private Bucket createBucket() {
        return Bucket.builder()
            .addLimit(Bandwidth.classic(
                100,                              // capacity: 100 token
                Refill.greedy(100, Duration.ofMinutes(1)) // dəqiqədə 100 refill
            ))
            .build();
    }
}

// ─── Filter/Interceptor ilə rate limiting ─────────────
@Component
public class RateLimitingFilter extends OncePerRequestFilter {

    private final RateLimiterService rateLimiterService;

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain chain) throws IOException, ServletException {

        String clientId = extractClientId(request);
        Bucket bucket = rateLimiterService.getBucketForClient(clientId);

        ConsumptionProbe probe = bucket.tryConsumeAndReturnRemaining(1);

        if (probe.isConsumed()) {
            // Limit haqqında response header-lər
            response.addHeader("X-Rate-Limit-Remaining",
                String.valueOf(probe.getRemainingTokens()));
            chain.doFilter(request, response);
        } else {
            // 429 Too Many Requests
            response.setStatus(HttpStatus.TOO_MANY_REQUESTS.value());
            response.setContentType("application/json");
            response.addHeader("X-Rate-Limit-Retry-After-Seconds",
                String.valueOf(probe.getNanosToWaitForRefill() / 1_000_000_000));
            response.getWriter().write("""
                {
                  "error": "Too Many Requests",
                  "message": "Rate limit aşıldı. Bir dəqiqə sonra yenidən cəhd edin."
                }
                """);
        }
    }

    private String extractClientId(HttpServletRequest request) {
        // API Key-dən
        String apiKey = request.getHeader("X-API-Key");
        if (apiKey != null) return "apikey:" + apiKey;

        // JWT-dən user ID
        String auth = request.getHeader("Authorization");
        if (auth != null && auth.startsWith("Bearer ")) {
            return "user:" + extractUserIdFromJwt(auth.substring(7));
        }

        // IP ünvanından (fallback)
        return "ip:" + getClientIp(request);
    }
}

// ─── Annotation əsaslı rate limiting ─────────────────
@Target(ElementType.METHOD)
@Retention(RetentionPolicy.RUNTIME)
public @interface RateLimit {
    int capacity() default 100;
    int refillTokens() default 100;
    int refillPeriodSeconds() default 60;
}

@Aspect
@Component
public class RateLimitAspect {

    private final Map<String, Bucket> buckets = new ConcurrentHashMap<>();

    @Around("@annotation(rateLimit)")
    public Object around(ProceedingJoinPoint joinPoint, RateLimit rateLimit) throws Throwable {
        String key = joinPoint.getSignature().toShortString();
        Bucket bucket = buckets.computeIfAbsent(key, k ->
            Bucket.builder()
                .addLimit(Bandwidth.classic(
                    rateLimit.capacity(),
                    Refill.greedy(
                        rateLimit.refillTokens(),
                        Duration.ofSeconds(rateLimit.refillPeriodSeconds())
                    )
                ))
                .build()
        );

        if (bucket.tryConsume(1)) {
            return joinPoint.proceed();
        } else {
            throw new RateLimitExceededException("Rate limit aşıldı: " + key);
        }
    }
}

// İstifadə:
@RestController
@RequestMapping("/api/orders")
public class OrderController {

    @PostMapping
    @RateLimit(capacity = 10, refillTokens = 10, refillPeriodSeconds = 60)
    public ResponseEntity<OrderResponse> createOrder(@RequestBody OrderRequest request) {
        return ResponseEntity.ok(orderService.createOrder(request));
    }
}
```

---

## Redis ilə Distributed Rate Limiting

```java
// ─── Multi-instance üçün distributed rate limiting ────
// Hər pod öz bucket-ını saxlasa → məcmu 3x limit aşılır!
// Həll: Redis-də paylaşılan bucket

@Configuration
public class DistributedRateLimiterConfig {

    @Bean
    public ProxyManager<String> redisProxyManager(
            RedissonClient redissonClient) {
        return Bucket4jRedisson.casBasedBuilder(redissonClient)
            .build();
    }
}

@Component
public class DistributedRateLimiterService {

    private final ProxyManager<String> proxyManager;

    public boolean tryConsume(String clientId) {
        BucketConfiguration config = BucketConfiguration.builder()
            .addLimit(Bandwidth.classic(
                100,
                Refill.greedy(100, Duration.ofMinutes(1))
            ))
            .build();

        // Redis-dəki bucket (client üçün unikal key)
        BucketProxy bucket = proxyManager.builder()
            .build(clientId, config);

        ConsumptionProbe probe = bucket.tryConsumeAndReturnRemaining(1);
        return probe.isConsumed();
    }
}

// ─── Redisson konfiqurasiyası ─────────────────────────
@Configuration
public class RedissonConfig {

    @Bean(destroyMethod = "shutdown")
    public RedissonClient redissonClient() {
        Config config = new Config();
        config.useSingleServer()
            .setAddress("redis://localhost:6379")
            .setConnectionPoolSize(10);
        return Redisson.create(config);
    }
}
```

---

## Spring Cloud Gateway ilə Rate Limiting

```yaml
# application.yml — Spring Cloud Gateway
spring:
  cloud:
    gateway:
      routes:
        - id: order-service
          uri: lb://order-service
          predicates:
            - Path=/api/orders/**
          filters:
            - name: RequestRateLimiter
              args:
                redis-rate-limiter.replenishRate: 10   # Saniyədə 10 token refill
                redis-rate-limiter.burstCapacity: 20   # Maksimum 20 token burst
                redis-rate-limiter.requestedTokens: 1  # Hər sorğu 1 token
                key-resolver: "#{@userKeyResolver}"
```

```java
@Configuration
public class GatewayRateLimiterConfig {

    // Hansi key üzrə limit? — user ID, IP, API key
    @Bean
    public KeyResolver userKeyResolver() {
        return exchange -> {
            // JWT-dən user ID
            String auth = exchange.getRequest()
                .getHeaders().getFirst("Authorization");

            if (auth != null && auth.startsWith("Bearer ")) {
                String userId = extractUserIdFromJwt(auth.substring(7));
                return Mono.just("user:" + userId);
            }

            // IP-dən
            String ip = exchange.getRequest()
                .getRemoteAddress().getAddress().getHostAddress();
            return Mono.just("ip:" + ip);
        };
    }
}
```

---

## Rate Limiting strategiyaları

```java
// ─── Tiered rate limits ───────────────────────────────
// Müxtəlif plan-lar üçün müxtəlif limitlər

@Component
public class TieredRateLimiterService {

    private final Map<String, Bucket> buckets = new ConcurrentHashMap<>();

    public boolean tryConsume(String userId, UserPlan plan) {
        String key = plan.name() + ":" + userId;

        Bucket bucket = buckets.computeIfAbsent(key, k ->
            createBucketForPlan(plan));

        return bucket.tryConsume(1);
    }

    private Bucket createBucketForPlan(UserPlan plan) {
        Bandwidth limit = switch (plan) {
            case FREE -> Bandwidth.classic(10, Refill.greedy(10, Duration.ofMinutes(1)));
            case STANDARD -> Bandwidth.classic(100, Refill.greedy(100, Duration.ofMinutes(1)));
            case PREMIUM -> Bandwidth.classic(1000, Refill.greedy(1000, Duration.ofMinutes(1)));
            case ENTERPRISE -> Bandwidth.classic(10000, Refill.greedy(10000, Duration.ofMinutes(1)));
        };

        return Bucket.builder().addLimit(limit).build();
    }
}

// ─── Multiple limits — sliding window ────────────────
// Eyni endpoint üçün həm qısa, həm uzun müddət limiti

@Bean
public Bucket multiLimitBucket() {
    return Bucket.builder()
        // Saniyədə maksimum 5 sorğu (burst control)
        .addLimit(Bandwidth.classic(5, Refill.greedy(5, Duration.ofSeconds(1))))
        // Saatda maksimum 1000 sorğu (quota)
        .addLimit(Bandwidth.classic(1000, Refill.greedy(1000, Duration.ofHours(1))))
        // Gündə maksimum 10000 sorğu (daily quota)
        .addLimit(Bandwidth.classic(10000, Refill.greedy(10000, Duration.ofDays(1))))
        .build();
}

// ─── Cost-based rate limiting ─────────────────────────
// Hər sorğu fərqli "token xərci" var

@RateLimitCost(tokens = 1)
public List<Order> getOrders() { ... }

@RateLimitCost(tokens = 5)    // Daha ağır sorğu
public Report generateReport() { ... }

@RateLimitCost(tokens = 10)   // Ən ağır
public File exportData() { ... }

// Token xərci aspect-ı:
@Around("@annotation(cost)")
public Object around(ProceedingJoinPoint jp, RateLimitCost cost) throws Throwable {
    if (!bucket.tryConsume(cost.tokens())) {
        throw new RateLimitExceededException();
    }
    return jp.proceed();
}

// ─── Rate limit response headers ─────────────────────
// Standart: RateLimit-* headers (RFC 6585 draft)
// X-RateLimit-Limit: 100
// X-RateLimit-Remaining: 47
// X-RateLimit-Reset: 1640000000  (unix timestamp)
// Retry-After: 30  (saniyə)
```

---

## İntervyu Sualları

### 1. Rate Limiting niyə lazımdır?
**Cavab:** (1) **DDoS/abuzus qorunma** — sistemi sıradan çıxara biləcək yükü bloklamaq. (2) **Fair use** — bir client hamının resursunu istifadə edə bilməz. (3) **Cost control** (cloud API-lər) — limitsiz sorğu böyük xərc yaradır. (4) **API monetization** — pulsuz/premium plan fərqli limitlər. (5) **SLA qorunma** — servisin cavab sürətini zəmanət etmək.

### 2. Token Bucket alqoritmi necə işləyir?
**Cavab:** Bucket-da N token var. Hər sorğu 1 (ya da daha çox) token istehlak edir. Hər saniyə (ya da müəyyən vaxtda) M token əlavə olunur (refill). Token yoxdursa sorğu rədd edilir (429). Üstünlük: burst icazə verir — qısa müddətdə çox sorğu mümkündür (bucket doluyursa). Uzunmüddətli ortalama refill sürəti ilə məhdudlanır.

### 3. In-memory vs Redis rate limiting fərqi?
**Cavab:** **In-memory** — hər application instance-ı öz bucket-ını saxlayır; 3 pod → hər pod 100 limit → real limit 300. Horizontal scaling-də problem. **Redis** — paylaşılan bucket; bütün pod-lar eyni Redis key-ini istifadə edir; real 100 limit. Production multi-instance mühitdə Redis rate limiting mütləqdir.

### 4. Sliding Window vs Fixed Window?
**Cavab:** **Fixed Window** — hər dəqiqə başında sayğac sıfırlanır; edge case: 59s-da 100, 61s-da 100 → 2s-da 200 sorğu. **Sliding Window** — son 60 saniyəni hesablayır; daha dəqiq, amma daha çox hesablama. **Token Bucket** — hybrid: burst icazəsi + average rate limiting. Bucket4j Token Bucket istifadə edir — ən çevik.

### 5. Rate limit aşıldıqda response necə olmalıdır?
**Cavab:** HTTP 429 Too Many Requests. Headers: `Retry-After: 30` (neçə saniyə gözlənilsin), `X-RateLimit-Limit: 100`, `X-RateLimit-Remaining: 0`, `X-RateLimit-Reset: <unix_timestamp>`. Body: JSON error message. Client-lər bu headerlardan baxaraq exponential backoff tətbiq edə bilər. `Retry-After` RFC 7231-də standartlaşdırılmışdır.

*Son yenilənmə: 2026-04-10*
