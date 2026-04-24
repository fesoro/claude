# 016 — API Gateway Patterns — Geniş İzah
**Səviyyə:** Ekspert


## Mündəricat
1. [API Gateway nədir?](#api-gateway-nədir)
2. [Spring Cloud Gateway](#spring-cloud-gateway)
3. [Route konfiqurasiyası](#route-konfiqurasiyası)
4. [Filter-lər — Pre/Post](#filter-lər--prepost)
5. [Authentication & Authorization](#authentication--authorization)
6. [Gateway patterns](#gateway-patterns)
7. [İntervyu Sualları](#intervyu-sualları)

---

## API Gateway nədir?

```
Microservice arxitekturasında problem:
  Client → Order Service (:8081)
  Client → Payment Service (:8082)
  Client → Inventory Service (:8083)
  Client → User Service (:8084)

  Hər servis üçün:
  → Ayrı URL
  → Ayrı Auth
  → Ayrı CORS
  → Ayrı rate limiting
  → Client çox mürəkkəbdir!

API Gateway həlli:
  Client → [API Gateway :8080]
                ↓
    ┌───────────────────────────┐
    │  Route /api/orders → 8081 │
    │  Route /api/payments → 8082│
    │  Route /api/inventory → 8083│
    └───────────────────────────┘

Gateway funksiyaları:
  ✅ Single entry point
  ✅ Authentication/Authorization (JWT doğrulama)
  ✅ Rate Limiting
  ✅ Load Balancing (lb://service-name)
  ✅ SSL Termination (HTTPS → HTTP daxili)
  ✅ Request/Response transformation
  ✅ Circuit Breaker
  ✅ Logging & Monitoring
  ✅ Caching

Populyar API Gateway-lər:
  Spring Cloud Gateway (Java/Spring ekosistemi)
  Kong (Lua + Nginx)
  AWS API Gateway (serverless)
  Nginx / Traefik (infrastructure-level)
  Netflix Zuul (köhnə, Spring Cloud Gateway ilə əvəzləndi)
```

---

## Spring Cloud Gateway

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-gateway</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-netflix-eureka-client</artifactId>
</dependency>
<!-- Circuit Breaker -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-circuitbreaker-reactor-resilience4j</artifactId>
</dependency>
<!-- Rate Limiting (Redis lazım) -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-redis-reactive</artifactId>
</dependency>
```

```yaml
# application.yml — minimal konfiqurasiya
spring:
  application:
    name: api-gateway
  cloud:
    gateway:
      discovery:
        locator:
          enabled: true      # Eureka-dan avtomatik route
          lower-case-service-id: true  # ORDER-SERVICE → order-service
```

---

## Route konfiqurasiyası

```yaml
# application.yml — YAML-based route konfiqurasiyası
spring:
  cloud:
    gateway:
      routes:
        # ─── Order Service ──────────────────────────────
        - id: order-service
          uri: lb://order-service          # Eureka-dan load balanced
          predicates:
            - Path=/api/orders/**          # /api/orders/... → order-service
          filters:
            - StripPrefix=1                # /api/orders → /orders (prefix sil)
            - name: CircuitBreaker
              args:
                name: orderCircuitBreaker
                fallbackUri: forward:/fallback/orders

        # ─── Payment Service ────────────────────────────
        - id: payment-service
          uri: lb://payment-service
          predicates:
            - Path=/api/payments/**
            - Method=POST,GET              # Yalnız bu metodlar
          filters:
            - AddRequestHeader=X-Gateway-Source, api-gateway
            - AddResponseHeader=X-Response-Time, 100ms

        # ─── User Service — version predicate ──────────
        - id: user-service-v2
          uri: lb://user-service
          predicates:
            - Path=/api/users/**
            - Header=X-API-Version, 2      # Yalnız v2 header
          filters:
            - RewritePath=/api/users/(?<segment>.*), /v2/users/${segment}

        # ─── Static route — xarici URL ─────────────────
        - id: external-api
          uri: https://api.external.com
          predicates:
            - Path=/external/**
          filters:
            - StripPrefix=1
            - AddRequestHeader=Authorization, Bearer ${external.api.token}

      # ─── Default filters — bütün route-lara tətbiq ──
      default-filters:
        - DedupeResponseHeader=Access-Control-Allow-Origin
        - name: RequestRateLimiter
          args:
            redis-rate-limiter.replenishRate: 10
            redis-rate-limiter.burstCapacity: 20
            key-resolver: "#{@ipKeyResolver}"

      # ─── Global CORS ─────────────────────────────────
      globalcors:
        corsConfigurations:
          '[/**]':
            allowedOrigins:
              - "https://myapp.com"
              - "http://localhost:3000"
            allowedMethods:
              - GET
              - POST
              - PUT
              - DELETE
              - OPTIONS
            allowedHeaders: "*"
            allowCredentials: true
            maxAge: 3600
```

```java
// ─── Java-based Route konfiqurasiyası ────────────────────
@Configuration
public class GatewayRouteConfig {

    @Bean
    public RouteLocator customRouteLocator(RouteLocatorBuilder builder) {
        return builder.routes()
            // Order service route
            .route("order-service", r -> r
                .path("/api/orders/**")
                .and()
                .method(HttpMethod.GET, HttpMethod.POST, HttpMethod.PUT)
                .filters(f -> f
                    .stripPrefix(1)
                    .addRequestHeader("X-Gateway-Source", "api-gateway")
                    .circuitBreaker(c -> c
                        .setName("orderCB")
                        .setFallbackUri("forward:/fallback/orders"))
                    .retry(config -> config
                        .setRetries(3)
                        .setStatuses(HttpStatus.INTERNAL_SERVER_ERROR)
                        .setMethods(HttpMethod.GET))
                )
                .uri("lb://order-service")
            )
            // Redirect pattern
            .route("legacy-redirect", r -> r
                .path("/old-api/**")
                .filters(f -> f.redirect(301, "https://newapi.example.com"))
                .uri("no://op")
            )
            .build();
    }
}
```

---

## Filter-lər — Pre/Post

```java
// ─── Global Pre Filter — bütün sorğular üçün ─────────────
@Component
@Order(1)
public class RequestLoggingFilter implements GlobalFilter {

    @Override
    public Mono<Void> filter(ServerWebExchange exchange, GatewayFilterChain chain) {
        ServerHttpRequest request = exchange.getRequest();

        String requestId = UUID.randomUUID().toString();
        long startTime = System.currentTimeMillis();

        log.info("[{}] → {} {}", requestId, request.getMethod(), request.getURI());

        // Request-ə attribute əlavə et
        exchange.getAttributes().put("requestId", requestId);
        exchange.getAttributes().put("startTime", startTime);

        // Pre filter → chain.filter() → Post filter
        return chain.filter(exchange).then(
            Mono.fromRunnable(() -> {
                // Post filter — response gəldikdən sonra
                long duration = System.currentTimeMillis() - startTime;
                int statusCode = exchange.getResponse().getStatusCode() != null
                    ? exchange.getResponse().getStatusCode().value()
                    : 0;
                log.info("[{}] ← {} {}ms", requestId, statusCode, duration);
            })
        );
    }
}

// ─── Custom Gateway Filter Factory ───────────────────────
@Component
public class RequestValidationFilterFactory
        extends AbstractGatewayFilterFactory<RequestValidationFilterFactory.Config> {

    public RequestValidationFilterFactory() {
        super(Config.class);
    }

    @Override
    public GatewayFilter apply(Config config) {
        return (exchange, chain) -> {
            ServerHttpRequest request = exchange.getRequest();

            // Content-Type yoxla
            if (HttpMethod.POST.equals(request.getMethod()) ||
                HttpMethod.PUT.equals(request.getMethod())) {

                MediaType contentType = request.getHeaders().getContentType();
                if (contentType == null || !contentType.isCompatibleWith(MediaType.APPLICATION_JSON)) {
                    exchange.getResponse().setStatusCode(HttpStatus.UNSUPPORTED_MEDIA_TYPE);
                    return exchange.getResponse().setComplete();
                }
            }

            // Max request size
            String contentLength = request.getHeaders().getFirst("Content-Length");
            if (contentLength != null && Long.parseLong(contentLength) > config.getMaxBodySize()) {
                exchange.getResponse().setStatusCode(HttpStatus.PAYLOAD_TOO_LARGE);
                return exchange.getResponse().setComplete();
            }

            return chain.filter(exchange);
        };
    }

    @Data
    public static class Config {
        private long maxBodySize = 10 * 1024 * 1024; // 10MB default
    }
}

// ─── Fallback Controller ──────────────────────────────────
@RestController
@RequestMapping("/fallback")
public class FallbackController {

    @GetMapping("/orders")
    public ResponseEntity<Map<String, String>> ordersFallback() {
        return ResponseEntity.status(HttpStatus.SERVICE_UNAVAILABLE).body(Map.of(
            "error", "Order Service müvəqqəti əlçatmazdır",
            "message", "Bir az sonra yenidən cəhd edin"
        ));
    }

    @GetMapping("/payments")
    public ResponseEntity<Map<String, String>> paymentsFallback() {
        return ResponseEntity.status(HttpStatus.SERVICE_UNAVAILABLE).body(Map.of(
            "error", "Payment Service müvəqqəti əlçatmazdır"
        ));
    }
}
```

---

## Authentication & Authorization

```java
// ─── JWT Authentication Filter ────────────────────────────
@Component
@Order(0)  // Ən əvvəl çalışır
public class JwtAuthenticationFilter implements GlobalFilter {

    private final JwtTokenValidator tokenValidator;

    // Bu path-lər authentication tələb etmir
    private static final Set<String> PUBLIC_PATHS = Set.of(
        "/api/auth/login",
        "/api/auth/register",
        "/api/auth/refresh",
        "/actuator/health"
    );

    @Override
    public Mono<Void> filter(ServerWebExchange exchange, GatewayFilterChain chain) {
        String path = exchange.getRequest().getPath().value();

        // Public endpoint — token lazım deyil
        if (isPublicPath(path)) {
            return chain.filter(exchange);
        }

        // Authorization header yoxla
        String authHeader = exchange.getRequest()
            .getHeaders()
            .getFirst(HttpHeaders.AUTHORIZATION);

        if (authHeader == null || !authHeader.startsWith("Bearer ")) {
            return unauthorized(exchange, "Authorization header tələb olunur");
        }

        String token = authHeader.substring(7);

        // Token doğrula
        return tokenValidator.validate(token)
            .flatMap(claims -> {
                // User məlumatlarını downstream service-ə ötür
                ServerHttpRequest mutatedRequest = exchange.getRequest().mutate()
                    .header("X-User-Id", claims.getSubject())
                    .header("X-User-Email", claims.get("email", String.class))
                    .header("X-User-Roles", String.join(",",
                        claims.get("roles", List.class)))
                    .build();

                return chain.filter(exchange.mutate().request(mutatedRequest).build());
            })
            .onErrorResume(JwtException.class, e ->
                unauthorized(exchange, "Token etibarsızdır: " + e.getMessage()));
    }

    private Mono<Void> unauthorized(ServerWebExchange exchange, String message) {
        exchange.getResponse().setStatusCode(HttpStatus.UNAUTHORIZED);
        exchange.getResponse().getHeaders().add(
            HttpHeaders.CONTENT_TYPE, MediaType.APPLICATION_JSON_VALUE);
        String body = """
            {"error": "Unauthorized", "message": "%s"}
            """.formatted(message);
        DataBuffer buffer = exchange.getResponse().bufferFactory()
            .wrap(body.getBytes(StandardCharsets.UTF_8));
        return exchange.getResponse().writeWith(Mono.just(buffer));
    }

    private boolean isPublicPath(String path) {
        return PUBLIC_PATHS.stream().anyMatch(path::startsWith);
    }
}

// ─── Rate Limiting with Redis ─────────────────────────────
@Configuration
public class RateLimitConfig {

    @Bean
    public KeyResolver userKeyResolver() {
        return exchange -> {
            // JWT-dən user ID (JWT filter əvvəl çalışır)
            String userId = exchange.getRequest().getHeaders()
                .getFirst("X-User-Id");
            if (userId != null) {
                return Mono.just("user:" + userId);
            }
            // IP fallback
            return Mono.just("ip:" +
                Objects.requireNonNull(exchange.getRequest().getRemoteAddress())
                    .getAddress().getHostAddress());
        };
    }

    @Bean
    public KeyResolver ipKeyResolver() {
        return exchange -> Mono.just(
            Objects.requireNonNull(exchange.getRequest().getRemoteAddress())
                .getAddress().getHostAddress()
        );
    }
}
```

---

## Gateway patterns

```java
// ─── Backend for Frontend (BFF) pattern ──────────────────
// Mobile üçün ayrı gateway, web üçün ayrı

@Configuration
public class MobileBffRouteConfig {

    @Bean
    public RouteLocator mobileRoutes(RouteLocatorBuilder builder) {
        return builder.routes()
            // Mobile-a optimized response (az field)
            .route("mobile-orders", r -> r
                .path("/mobile/orders/**")
                .filters(f -> f
                    .stripPrefix(1)
                    .modifyResponseBody(String.class, String.class,
                        (exchange, body) -> Mono.just(
                            filterMobileFields(body) // Lazımsız field-ləri çıxar
                        )
                    )
                )
                .uri("lb://order-service")
            )
            .build();
    }
}

// ─── Request Aggregation pattern ─────────────────────────
// Bir client sorğusunu çox service-ə göndər, nəticəni birləşdir

@RestController
@RequestMapping("/api/dashboard")
public class DashboardAggregatorController {

    private final WebClient orderClient;
    private final WebClient paymentClient;
    private final WebClient inventoryClient;

    @GetMapping("/summary/{userId}")
    public Mono<DashboardSummary> getDashboardSummary(@PathVariable String userId) {
        // Paralel sorğular
        Mono<List<OrderDto>> orders = orderClient.get()
            .uri("/orders?userId=" + userId)
            .retrieve()
            .bodyToFlux(OrderDto.class)
            .collectList();

        Mono<AccountBalance> balance = paymentClient.get()
            .uri("/accounts/" + userId + "/balance")
            .retrieve()
            .bodyToMono(AccountBalance.class);

        Mono<Integer> lowStockAlerts = inventoryClient.get()
            .uri("/alerts/count")
            .retrieve()
            .bodyToMono(Integer.class);

        // Hamısını gözlə, birləşdir
        return Mono.zip(orders, balance, lowStockAlerts)
            .map(tuple -> new DashboardSummary(
                tuple.getT1(), tuple.getT2(), tuple.getT3()));
    }
}

// ─── Canary Deployment pattern ────────────────────────────
@Configuration
public class CanaryRouteConfig {

    @Bean
    public RouteLocator canaryRoutes(RouteLocatorBuilder builder) {
        return builder.routes()
            // 10% traffic → v2 (yeni versiya)
            .route("order-service-canary", r -> r
                .path("/api/orders/**")
                .and()
                .weight("order-group", 10)
                .uri("lb://order-service-v2")
            )
            // 90% traffic → v1 (stabil)
            .route("order-service-stable", r -> r
                .path("/api/orders/**")
                .and()
                .weight("order-group", 90)
                .uri("lb://order-service-v1")
            )
            .build();
    }
}

// ─── Actuator + Metrics ───────────────────────────────────
/*
management:
  endpoints:
    web:
      exposure:
        include: health,info,metrics,gateway
  endpoint:
    gateway:
      enabled: true

# /actuator/gateway/routes → bütün route-lar
# /actuator/gateway/refresh → route-ları yenilə (runtime)
# /actuator/metrics/spring.cloud.gateway.requests → request metrics
*/
```

---

## İntervyu Sualları

### 1. API Gateway niyə lazımdır?
**Cavab:** Microservice arxitekturasında çoxlu servis mövcuddur. Client hər birinə ayrı-ayrı danışmaq əvəzinə bir Gateway ilə danışır. Gateway: (1) **Single entry point** — client sadəliyi; (2) **Cross-cutting concerns** — auth, rate limiting, logging hər servisdə deyil, bir yerdə; (3) **SSL termination** — HTTPS gateway-də bitir, daxildə HTTP; (4) **Load balancing** — servislər arasında yük bölgüsü; (5) **Service discovery** — Eureka/Consul inteqrasiyası; (6) **Circuit breaker** — servislər çöksə fallback. Dezavantaj: single point of failure (HA deployment lazım), latency artır.

### 2. Spring Cloud Gateway Zuul-dan nəyi ilə fərqlənir?
**Cavab:** **Zuul 1.x** — synchronous, blocking, Servlet API, thread-per-request. Yüksək yük altında thread pool tükənir. **Spring Cloud Gateway** — reaktif, non-blocking, Project Reactor/WebFlux, az thread ilə çox sorğu idarə edir. Gateway WebFilter (reaktif) vs Zuul Filter (servlet) istifadə edir. Performans: Gateway Zuul-dan xeyli üstün. Netflix özü Zuul 2.x-i yazdı (reaktiv), amma Spring ekosistemi Spring Cloud Gateway-i qəbul etdi.

### 3. Pre vs Post Filter fərqi nədir?
**Cavab:** **Pre Filter** (`chain.filter()` çağırılmadan əvvəl) — auth yoxla, rate limit tətbiq et, request header əlavə et, logging başlat. **Post Filter** (`.then()` blokunun içi — response gəldikdən sonra) — response header əlavə et, response body transformasiya et, latency hesabla, audit log yaz. Spring Cloud Gateway-də GlobalFilter `chain.filter(exchange).then(Mono.fromRunnable(...))` pattern-i ilə hər iki mərhələni bir filter-də birləşdirir.

### 4. BFF (Backend for Frontend) pattern nədir?
**Cavab:** Hər frontend tipi üçün (mobile, web, TV) ayrı Gateway ya da ayrı endpoint-lər. Səbəb: mobile az data istəyir (bandwidth), web daha çox; mobile OAuth flow fərqlidir; mobile cache siyasəti fərqlidir. BFF mobile üçün ayrıca aggregate edir — bir çağırışda lazımi data əldə edir (chatty API problemini həll edir). Web BFF-i daha çox data, daha çox transformasiya edə bilər. Dezavantaj: kod tekrarı.

### 5. Canary Deployment Gateway-də necə tətbiq olunur?
**Cavab:** Spring Cloud Gateway `weight` predicate-i ilə — eyni path-ə iki route müəyyən çəki nisbətilə (10:90) tanımlanır. 10% traffic yeni versiyaya (canary), 90% köhnə versiyaya gedir. Monitoring: canary-dəki error rate, latency izlənilir. Sağlamsa: 50:50 → 100:0. Problemsə: sıfıra endirilib rollback edilir. Bu, tam deployment-dən əvvəl real istifadəçilər üzərində test imkanı verir.

*Son yenilənmə: 2026-04-10*
