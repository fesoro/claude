# 119 - Spring Cloud Gateway

## Mündəricat
- [Gateway Nədir?](#gateway-nədir)
- [Reaktiv Arxitektura (Netty)](#reaktiv-arxitektura-netty)
- [Route Konfiqurasiyası](#route-konfiqurasiyası)
- [Predicates](#predicates)
- [Filters](#filters)
- [GlobalFilter](#globalfilter)
- [Custom Filter](#custom-filter)
- [Rate Limiting](#rate-limiting)
- [Circuit Breaker inteqrasiyası](#circuit-breaker-inteqrasiyası)
- [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Gateway Nədir?

Spring Cloud Gateway — microservice arxitekturasında tək giriş nöqtəsi (single entry point) rolunu oynayan reaktiv API gateway-dir.

```
Xarici Sorğu
      │
      ▼
┌─────────────────────────────────────┐
│         API Gateway (:8080)         │
│  ┌─────────────────────────────┐   │
│  │  Predicate Match?           │   │
│  │  /api/orders/** → Order Svc │   │
│  │  /api/payments/** → Pay Svc │   │
│  └─────────────────────────────┘   │
│  ┌─────────────────────────────┐   │
│  │  Filters                    │   │
│  │  Auth Check, Rate Limit...  │   │
│  └─────────────────────────────┘   │
└─────────────────────────────────────┘
      │               │
      ▼               ▼
 Order Svc       Payment Svc
 (:8081)          (:8082)
```

**Niyə Gateway?**
- **Security** — Auth yoxlaması mərkəzləşdirilir
- **Load Balancing** — Servis instanceları arasında yük paylanır
- **Rate Limiting** — Hər client üçün sorğu limiti
- **Request/Response transformation** — Header, path dəyişdirmə
- **Observability** — Bütün sorğular izlənir

---

## Reaktiv Arxitektura (Netty)

Spring Cloud Gateway Spring WebFlux üzərindədir — Netty server istifadə edir (Tomcat yox).

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-gateway</artifactId>
    <!-- Daxilində: spring-boot-starter-webflux, reactor-netty -->
</dependency>

<!-- Eureka ilə birlikdə istifadə -->
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-netflix-eureka-client</artifactId>
</dependency>
```

```java
// YANLIŞ — Gateway layihəsinə spring-boot-starter-web əlavə etmə!
// Web MVC və WebFlux bir arada işləmir

// DOĞRU — yalnız WebFlux əsaslı dependency-lər
// spring-cloud-starter-gateway artıq WebFlux-u daxil edir
```

---

## Route Konfiqurasiyası

Route — giriş sorğusunu hara yönləndirəcəyimizi müəyyən edir.

### YAML Konfiqurasiyası

```yaml
spring:
  application:
    name: api-gateway
  cloud:
    gateway:
      routes:
        # Route 1 — Order Servisi
        - id: order-service-route          # Unikal identifikator
          uri: lb://ORDER-SERVICE          # lb:// — Eureka-dan load balance
          predicates:
            - Path=/api/orders/**          # Bu path-ə uyğun gəlsə
          filters:
            - StripPrefix=1               # /api prefixini sil

        # Route 2 — Payment Servisi
        - id: payment-service-route
          uri: lb://PAYMENT-SERVICE
          predicates:
            - Path=/api/payments/**
          filters:
            - StripPrefix=1

        # Route 3 — Statik URI
        - id: external-api-route
          uri: https://api.external.com
          predicates:
            - Path=/external/**

      # Bütün servislər üçün default filter
      default-filters:
        - AddRequestHeader=X-Gateway-Source, spring-cloud-gateway
        - AddResponseHeader=X-Response-Time, ${spring.application.name}
```

### Java Konfiqurasiyası (RouteLocator)

```java
@Configuration
public class GatewayConfig {

    @Bean
    public RouteLocator customRouteLocator(RouteLocatorBuilder builder) {
        return builder.routes()
            // Order servisi route-u
            .route("order-service-route", r -> r
                .path("/api/orders/**")              // Path predicate
                .filters(f -> f
                    .stripPrefix(1)                  // /api sil
                    .addRequestHeader("X-Request-Source", "gateway")
                    .circuitBreaker(config -> config
                        .setName("orderCircuitBreaker")
                        .setFallbackUri("forward:/fallback/order")
                    )
                )
                .uri("lb://ORDER-SERVICE")
            )
            // Auth servisi route-u
            .route("auth-service-route", r -> r
                .path("/api/auth/**")
                .and()
                .method(HttpMethod.POST)             // Yalnız POST
                .filters(f -> f.stripPrefix(1))
                .uri("lb://AUTH-SERVICE")
            )
            .build();
    }
}
```

---

## Predicates

Predicate — sorğunun hansı route-a uyğun gəldiğini müəyyən edir.

### Path Predicate

```yaml
predicates:
  - Path=/api/orders/**         # Wildcard ilə
  - Path=/api/v{version}/**    # Template variable
```

### Method Predicate

```yaml
predicates:
  - Method=GET,POST             # Yalnız GET və POST
```

### Header Predicate

```yaml
predicates:
  - Header=X-API-Version, v2   # Header dəyəri regex ilə uyğunlaşır
  - Header=Authorization       # Header mövcuddursa (dəyər yoxlanmadan)
```

### Query Predicate

```yaml
predicates:
  - Query=debug                # debug parametri mövcuddursa
  - Query=status, active       # status=active
```

### Host Predicate

```yaml
predicates:
  - Host=**.mycompany.com      # subdomain wildcard
  - Host=api.mycompany.com,admin.mycompany.com
```

### Time-based Predicates

```yaml
predicates:
  # Müəyyən vaxtdan sonra
  - After=2024-01-01T00:00:00+04:00[Asia/Baku]

  # Müəyyən vaxtdan əvvəl
  - Before=2024-12-31T23:59:59+04:00[Asia/Baku]

  # Zaman aralığında
  - Between=2024-01-01T00:00:00+04:00[Asia/Baku], 2024-12-31T23:59:59+04:00[Asia/Baku]
```

### Weight Predicate (A/B Testing)

```yaml
routes:
  - id: service-v1
    uri: lb://SERVICE-V1
    predicates:
      - Path=/api/service/**
      - Weight=service-group, 80    # 80% sorğu v1-ə
  - id: service-v2
    uri: lb://SERVICE-V2
    predicates:
      - Path=/api/service/**
      - Weight=service-group, 20    # 20% sorğu v2-ə (canary release)
```

### RemoteAddr Predicate

```yaml
predicates:
  - RemoteAddr=192.168.1.0/24   # IP aralığı (CIDR notation)
```

### Java ilə Composite Predicate

```java
.route("complex-route", r -> r
    .path("/api/admin/**")
    .and()                                    // VƏ şərti
    .header("X-Admin-Token")
    .and()
    .method(HttpMethod.GET, HttpMethod.POST)
    .uri("lb://ADMIN-SERVICE")
)
```

---

## Filters

Filters — sorğu/cavabı dəyişdirmək üçün işlənir. Pre-filter (sorğu göndərilmədən) və Post-filter (cavab alındıqdan sonra) olaraq işləyir.

### AddRequestHeader / AddResponseHeader

```yaml
filters:
  - AddRequestHeader=X-Request-Id, ${random.uuid}
  - AddResponseHeader=X-Response-Gateway, spring-gateway
  - RemoveRequestHeader=Cookie          # Cookie header-ı sil
  - RemoveResponseHeader=X-Internal-Id # Daxili header-ı cavabdan sil
```

### SetRequestHeader / SetResponseHeader

```yaml
filters:
  - SetRequestHeader=X-Request-Color, blue   # Dəyər yenidən yaz (əlavə etmə)
```

### RewritePath

```yaml
filters:
  # /api/orders/123  →  /orders/123
  - RewritePath=/api/(?<segment>.*), /${segment}
```

```java
// Java ilə RewritePath
.filters(f -> f.rewritePath(
    "/api/(?<segment>.*)",   // Regex
    "/${segment}"             // Əvəzetmə
))
```

### StripPrefix

```yaml
filters:
  - StripPrefix=2   # İlk 2 path segmentini sil
  # /api/v1/orders  →  /orders
```

### PrefixPath

```yaml
filters:
  - PrefixPath=/v1   # Path əvvəlinə əlavə et
  # /orders  →  /v1/orders
```

### SetStatus

```yaml
filters:
  - SetStatus=201   # Response status dəyiş
```

### RequestSize

```yaml
filters:
  - name: RequestSize
    args:
      maxSize: 5MB   # Maksimum sorğu ölçüsü
```

### Redirect

```yaml
filters:
  - RedirectTo=302, https://new.mycompany.com
```

---

## GlobalFilter

Global filter — bütün route-lara tətbiq olunur. Authentication, logging üçün idealdır.

```java
@Component
@Slf4j
public class AuthenticationGlobalFilter implements GlobalFilter, Ordered {

    private static final String AUTH_HEADER = "Authorization";

    @Override
    public Mono<Void> filter(ServerWebExchange exchange, GatewayFilterChain chain) {
        ServerHttpRequest request = exchange.getRequest();
        String path = request.getPath().value();

        // Public endpoint-lər üçün auth yoxlanmaz
        if (isPublicPath(path)) {
            return chain.filter(exchange);
        }

        // Authorization header yoxla
        if (!request.getHeaders().containsKey(AUTH_HEADER)) {
            log.warn("Auth header tapılmadı: {}", path);
            return unauthorized(exchange);
        }

        String token = request.getHeaders().getFirst(AUTH_HEADER);

        // Token validate et (JWT yoxlama)
        if (!isValidToken(token)) {
            log.warn("Etibarsız token: {}", path);
            return unauthorized(exchange);
        }

        // Downstream servisə user info göndər
        ServerHttpRequest modifiedRequest = request.mutate()
            .header("X-User-Id", extractUserId(token))
            .header("X-User-Role", extractRole(token))
            .build();

        return chain.filter(exchange.mutate().request(modifiedRequest).build());
    }

    @Override
    public int getOrder() {
        // Kiçik rəqəm = yüksək prioritet (əvvəl işlənir)
        return -100;
    }

    private Mono<Void> unauthorized(ServerWebExchange exchange) {
        exchange.getResponse().setStatusCode(HttpStatus.UNAUTHORIZED);
        return exchange.getResponse().setComplete();
    }

    private boolean isPublicPath(String path) {
        return path.startsWith("/api/auth/") ||
               path.startsWith("/api/public/") ||
               path.equals("/actuator/health");
    }

    private boolean isValidToken(String token) {
        // JWT validation logic
        return token != null && token.startsWith("Bearer ");
    }

    private String extractUserId(String token) {
        // JWT-dən user ID çıxar
        return "user-123"; // Simplified
    }

    private String extractRole(String token) {
        return "ROLE_USER";
    }
}
```

### Logging Global Filter

```java
@Component
@Slf4j
public class RequestLoggingGlobalFilter implements GlobalFilter, Ordered {

    @Override
    public Mono<Void> filter(ServerWebExchange exchange, GatewayFilterChain chain) {
        long startTime = System.currentTimeMillis();
        String path = exchange.getRequest().getPath().value();
        String method = exchange.getRequest().getMethod().name();

        log.info("→ Sorğu: {} {}", method, path);

        return chain.filter(exchange)
            .doFinally(signalType -> {
                long duration = System.currentTimeMillis() - startTime;
                int statusCode = exchange.getResponse().getStatusCode() != null
                    ? exchange.getResponse().getStatusCode().value()
                    : 0;
                log.info("← Cavab: {} {} → {} ({}ms)", method, path, statusCode, duration);
            });
    }

    @Override
    public int getOrder() {
        return -200; // Auth filter-dən əvvəl
    }
}
```

---

## Custom Filter

```java
// Custom GatewayFilter Factory
@Component
public class RequestValidationGatewayFilterFactory
        extends AbstractGatewayFilterFactory<RequestValidationGatewayFilterFactory.Config> {

    public RequestValidationGatewayFilterFactory() {
        super(Config.class);
    }

    @Override
    public GatewayFilter apply(Config config) {
        return (exchange, chain) -> {
            ServerHttpRequest request = exchange.getRequest();

            // Required header yoxla
            if (config.isRequireApiKey() &&
                !request.getHeaders().containsKey("X-API-Key")) {
                exchange.getResponse().setStatusCode(HttpStatus.BAD_REQUEST);
                return exchange.getResponse().setComplete();
            }

            return chain.filter(exchange);
        };
    }

    @Data
    public static class Config {
        private boolean requireApiKey = false;
        private String allowedContentType = "application/json";
    }

    @Override
    public List<String> shortcutFieldOrder() {
        return List.of("requireApiKey");
    }
}
```

```yaml
# Custom filter istifadəsi
filters:
  - name: RequestValidation
    args:
      requireApiKey: true
```

---

## Rate Limiting

```xml
<!-- Rate Limiter üçün Redis lazımdır -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-redis-reactive</artifactId>
</dependency>
```

```java
@Configuration
public class RateLimiterConfig {

    // Rate limiter key-i müəyyən et (hər user üçün ayrı limit)
    @Bean
    public KeyResolver userKeyResolver() {
        // IP ünvanına görə limit
        return exchange -> Mono.just(
            exchange.getRequest().getRemoteAddress().getAddress().getHostAddress()
        );
    }

    // Authenticated user üçün
    @Bean
    public KeyResolver authUserKeyResolver() {
        return exchange -> Mono.justOrEmpty(
            exchange.getRequest().getHeaders().getFirst("X-User-Id")
        ).defaultIfEmpty("anonymous");
    }
}
```

```yaml
spring:
  cloud:
    gateway:
      routes:
        - id: order-service
          uri: lb://ORDER-SERVICE
          predicates:
            - Path=/api/orders/**
          filters:
            - name: RequestRateLimiter
              args:
                redis-rate-limiter.replenishRate: 10    # Saniyədə 10 sorğu doldurulur
                redis-rate-limiter.burstCapacity: 20    # Maksimum burst 20 sorğu
                redis-rate-limiter.requestedTokens: 1   # Hər sorğu 1 token istehlak edir
                key-resolver: "#{@userKeyResolver}"     # Bean referansı

  data:
    redis:
      host: localhost
      port: 6379
```

---

## Circuit Breaker inteqrasiyası

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-circuitbreaker-reactor-resilience4j</artifactId>
</dependency>
```

```yaml
spring:
  cloud:
    gateway:
      routes:
        - id: order-service
          uri: lb://ORDER-SERVICE
          predicates:
            - Path=/api/orders/**
          filters:
            - name: CircuitBreaker
              args:
                name: orderCircuitBreaker
                fallbackUri: forward:/fallback/order   # Fallback endpoint-ə yönləndir

resilience4j:
  circuitbreaker:
    instances:
      orderCircuitBreaker:
        slidingWindowSize: 10
        failureRateThreshold: 50
        waitDurationInOpenState: 10s
```

```java
// Fallback Controller
@RestController
@RequestMapping("/fallback")
public class FallbackController {

    @GetMapping("/order")
    public Mono<ResponseEntity<Map<String, String>>> orderFallback(
            ServerWebExchange exchange) {
        // Fallback cavab — servis müvəqqəti əlçatmaz
        Map<String, String> response = Map.of(
            "status", "unavailable",
            "message", "Sifariş servisi müvəqqəti olaraq əlçatmaz",
            "timestamp", Instant.now().toString()
        );
        return Mono.just(ResponseEntity.status(HttpStatus.SERVICE_UNAVAILABLE).body(response));
    }
}
```

---

## Gateway Actuator Endpoints

```yaml
management:
  endpoints:
    web:
      exposure:
        include: gateway, health, info
  endpoint:
    gateway:
      enabled: true
```

```bash
# Bütün route-ları gör
GET /actuator/gateway/routes

# Xüsusi route-u gör
GET /actuator/gateway/routes/order-service-route

# Route-ları dynamic olaraq əlavə et
POST /actuator/gateway/routes/{id}

# Route-ları sil
DELETE /actuator/gateway/routes/{id}

# Route cache-i yenilə
POST /actuator/gateway/refresh
```

---

## İntervyu Sualları

**S: Spring Cloud Gateway Spring MVC Gateway-dən necə fərqlənir?**
C: Spring Cloud Gateway WebFlux + Netty üzərindədir (non-blocking, reaktiv). Köhnə Spring Cloud Netflix Zuul isə Servlet API üzərindədir (blocking). Gateway çox daha yüksək throughput göstərir, xüsusilə çoxlu concurrent connection zamanı.

**S: Predicate ilə Filter arasındakı fərq nədir?**
C: Predicate — sorğunun bu route-a uyğun olub olmadığına qərar verir (routing decision). Filter isə sorğu/cavabı dəyişdirir (transformation). Predicate əvvəl işlənir, uyğunlaşarsa filter-lər tətbiq olunur.

**S: GlobalFilter ilə GatewayFilter fərqi nədir?**
C: GlobalFilter bütün route-lara avtomatik tətbiq olunur. GatewayFilter isə yalnız konfiqurasiya edildiyi route-a tətbiq olunur. Auth yoxlaması üçün GlobalFilter, path dəyişdirmə üçün GatewayFilter istifadə olunur.

**S: Rate Limiting nəyə görə Redis tələb edir?**
C: Gateway çoxlu instance-da işləyə bilər (horizontal scaling). Hər instance-ın limiti ayrı ayrı hesablaması limiti deşə bilər. Redis mərkəzləşdirilmiş sayac rolunu oynayır — bütün instance-lar eyni Redis-i oxuyur.

**S: lb:// URI-si nə deməkdir?**
C: lb:// — load balanced deməkdir. Gateway Eureka-dan (və ya digər service registry-dən) servisin bütün instance-larını əldə edir və Spring Cloud LoadBalancer ilə sorğuları paylaşdırır.

**S: Circuit Breaker Gateway-də necə konfiqurasiya olunur?**
C: CircuitBreaker filter əlavə olunur, Resilience4j circuit breaker instansiyasına istinad edilir, fallbackUri ilə servis çöküşündə alternativ endpoint göstərilir. Zəncir: Gateway → CB filter → backend (çöküşdə → fallback endpoint).
