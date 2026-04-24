# Spring Cloud Gateway vs Laravel Gateway Patterns — Dərin Müqayisə

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

API Gateway — mikroservis mühitinin qapıçısıdır. Xarici sorğunu qəbul edir, autentikasiya edir, rate limit tətbiq edir, circuit breaker ilə qoruyur, düzgün xidmətə ötürür. Sadə dillə: istifadəçi bir ünvan bilir (`api.myapp.com`), amma arxada 20 fərqli xidmət var.

**Spring Cloud Gateway** reactive stack-dir — WebFlux və Netty üzərində qurulub. Millionlarla eyniyanaşı bağlantı aşağı yaddaşla idarə edə bilir. Route Predicates (şərt) + Filters (dəyişiklik) pattern-i ilə konfiqurasiya olunur.

**Laravel API gateway kimi istifadə edilmir.** Laravel tipik olaraq monolit backend və ya mikroservis rolundadır. Gateway rolunu adətən Nginx, Kong, Envoy, Traefik, AWS API Gateway oynayır. Amma bəzi sadə gateway pattern-ləri Laravel-də middleware + HTTP client ilə simulyasiya etmək mümkündür.

---

## Spring-də istifadəsi

### 1) Gateway quraşdırmaq

```xml
<!-- gateway/pom.xml -->
<dependencies>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-gateway</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-loadbalancer</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.cloud</groupId>
        <artifactId>spring-cloud-starter-circuitbreaker-reactor-resilience4j</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-data-redis-reactive</artifactId>
    </dependency>
</dependencies>
```

```java
@SpringBootApplication
public class GatewayApplication {
    public static void main(String[] args) {
        SpringApplication.run(GatewayApplication.class, args);
    }
}
```

```yaml
# application.yml
server:
  port: 8080

spring:
  application:
    name: api-gateway
  data:
    redis:
      host: redis
      port: 6379
  cloud:
    gateway:
      discovery:
        locator:
          enabled: false
      default-filters:
        - AddResponseHeader=X-Gateway, spring-cloud-gateway
        - DedupeResponseHeader=Access-Control-Allow-Origin
```

### 2) Route Predicates — YAML konfiqurasiyası

```yaml
spring:
  cloud:
    gateway:
      routes:
        - id: orders
          uri: lb://order-service
          predicates:
            - Path=/api/orders/**
            - Method=GET,POST,PUT,DELETE
            - Header=X-Request-Id, \d+
            - Host=**.myapp.com
            - After=2026-04-20T00:00:00+04:00[Asia/Baku]
          filters:
            - RewritePath=/api/orders/(?<segment>.*), /$\{segment}
            - AddRequestHeader=X-Trace-Id, ${random.uuid}

        - id: payments
          uri: lb://payment-service
          predicates:
            - Path=/api/payments/**
            - Cookie=session, [A-Za-z0-9]+
          filters:
            - StripPrefix=2
            - CircuitBreaker=payments-cb
            - RequestRateLimiter=10,20

        - id: public-docs
          uri: http://docs-site.internal
          predicates:
            - Path=/docs/**
            - Weight=docs-cluster, 8
          filters:
            - SetStatus=200
            - SetResponseHeader=Cache-Control, "public, max-age=3600"

        - id: beta-users
          uri: lb://beta-service
          predicates:
            - Path=/api/**
            - Header=X-User-Tier, beta
          filters:
            - AddRequestHeader=X-Beta, true
```

### 3) Route konfiqurasiyası — Java DSL

```java
@Configuration
public class GatewayRouteConfig {

    @Bean
    public RouteLocator routes(RouteLocatorBuilder builder,
                               JwtAuthFilter jwtFilter,
                               RedisRateLimiter rateLimiter) {
        return builder.routes()
            .route("orders", r -> r
                .path("/api/orders/**")
                .and().method(HttpMethod.GET, HttpMethod.POST)
                .filters(f -> f
                    .rewritePath("/api/orders/(?<s>.*)", "/${s}")
                    .filter(jwtFilter.apply(new JwtAuthFilter.Config()))
                    .requestRateLimiter(c -> c
                        .setRateLimiter(rateLimiter)
                        .setKeyResolver(exchange -> exchange.getPrincipal()
                            .map(Principal::getName)
                            .defaultIfEmpty("anonymous")))
                    .circuitBreaker(c -> c
                        .setName("orders-cb")
                        .setFallbackUri("forward:/fallback/orders"))
                    .retry(c -> c.setRetries(3).setMethods(HttpMethod.GET))
                )
                .uri("lb://order-service"))

            .route("payments", r -> r
                .path("/api/payments/**")
                .filters(f -> f
                    .stripPrefix(2)
                    .filter(jwtFilter.apply(new JwtAuthFilter.Config())))
                .uri("lb://payment-service"))

            .route("images", r -> r
                .path("/static/**")
                .filters(f -> f.setResponseHeader("Cache-Control", "max-age=86400"))
                .uri("https://cdn.myapp.com"))

            .build();
    }

    @Bean
    public RedisRateLimiter redisRateLimiter() {
        return new RedisRateLimiter(10, 20, 1);   // 10/sec, burst 20
    }
}
```

### 4) Global Filter — auth, logging, tracing

```java
@Component
@Slf4j
public class RequestLoggingFilter implements GlobalFilter, Ordered {

    @Override
    public Mono<Void> filter(ServerWebExchange exchange, GatewayFilterChain chain) {
        ServerHttpRequest req = exchange.getRequest();
        String traceId = req.getHeaders().getFirst("X-Trace-Id");
        if (traceId == null) {
            traceId = UUID.randomUUID().toString();
            req = req.mutate().header("X-Trace-Id", traceId).build();
            exchange = exchange.mutate().request(req).build();
        }

        long start = System.currentTimeMillis();
        final String finalTrace = traceId;

        return chain.filter(exchange)
            .doOnSuccess(v -> {
                long dur = System.currentTimeMillis() - start;
                log.info("trace={} method={} path={} status={} duration={}ms",
                    finalTrace,
                    exchange.getRequest().getMethod(),
                    exchange.getRequest().getPath(),
                    exchange.getResponse().getStatusCode(),
                    dur);
            });
    }

    @Override
    public int getOrder() { return Ordered.LOWEST_PRECEDENCE; }
}
```

### 5) JWT Auth Gateway Filter

```java
@Component
public class JwtAuthFilter extends AbstractGatewayFilterFactory<JwtAuthFilter.Config> {

    private final JwtDecoder jwtDecoder;

    public JwtAuthFilter(JwtDecoder jwtDecoder) {
        super(Config.class);
        this.jwtDecoder = jwtDecoder;
    }

    public static class Config {
        private List<String> requiredScopes = new ArrayList<>();
        // getters, setters
    }

    @Override
    public GatewayFilter apply(Config config) {
        return (exchange, chain) -> {
            String auth = exchange.getRequest().getHeaders().getFirst(HttpHeaders.AUTHORIZATION);
            if (auth == null || !auth.startsWith("Bearer ")) {
                return unauthorized(exchange);
            }

            String token = auth.substring(7);
            try {
                Jwt jwt = jwtDecoder.decode(token);
                List<String> scopes = jwt.getClaimAsStringList("scope");
                if (!scopes.containsAll(config.getRequiredScopes())) {
                    return forbidden(exchange);
                }

                ServerHttpRequest mutated = exchange.getRequest().mutate()
                    .header("X-User-Id", jwt.getSubject())
                    .header("X-User-Email", jwt.getClaimAsString("email"))
                    .header("X-User-Roles", String.join(",", scopes))
                    .build();

                return chain.filter(exchange.mutate().request(mutated).build());
            } catch (JwtException e) {
                return unauthorized(exchange);
            }
        };
    }

    private Mono<Void> unauthorized(ServerWebExchange exchange) {
        exchange.getResponse().setStatusCode(HttpStatus.UNAUTHORIZED);
        return exchange.getResponse().setComplete();
    }

    private Mono<Void> forbidden(ServerWebExchange exchange) {
        exchange.getResponse().setStatusCode(HttpStatus.FORBIDDEN);
        return exchange.getResponse().setComplete();
    }
}
```

### 6) Circuit Breaker + Fallback

```yaml
resilience4j:
  circuitbreaker:
    instances:
      orders-cb:
        sliding-window-type: COUNT_BASED
        sliding-window-size: 20
        failure-rate-threshold: 50
        wait-duration-in-open-state: 30s
        permitted-number-of-calls-in-half-open-state: 5
        automatic-transition-from-open-to-half-open-enabled: true
  timelimiter:
    instances:
      orders-cb:
        timeout-duration: 2s
```

```java
@RestController
public class FallbackController {

    @RequestMapping("/fallback/orders")
    public Mono<ResponseEntity<Map<String, Object>>> ordersFallback() {
        return Mono.just(ResponseEntity
            .status(HttpStatus.SERVICE_UNAVAILABLE)
            .body(Map.of(
                "error", "service_unavailable",
                "message", "Orders service temporarily down. Try later.",
                "ts", Instant.now().toString()
            )));
    }

    @RequestMapping("/fallback/payments")
    public Mono<ResponseEntity<Map<String, Object>>> paymentsFallback() {
        return Mono.just(ResponseEntity
            .status(HttpStatus.SERVICE_UNAVAILABLE)
            .body(Map.of("error", "payment_unavailable")));
    }
}
```

### 7) Redis-based Rate Limiter

```yaml
spring:
  cloud:
    gateway:
      routes:
        - id: orders
          uri: lb://order-service
          predicates:
            - Path=/api/orders/**
          filters:
            - name: RequestRateLimiter
              args:
                redis-rate-limiter.replenishRate: 10
                redis-rate-limiter.burstCapacity: 20
                redis-rate-limiter.requestedTokens: 1
                key-resolver: "#{@userKeyResolver}"
```

```java
@Bean
public KeyResolver userKeyResolver() {
    return exchange -> Mono.justOrEmpty(
        exchange.getRequest().getHeaders().getFirst("X-User-Id")
    ).defaultIfEmpty(
        Optional.ofNullable(exchange.getRequest().getRemoteAddress())
            .map(a -> a.getAddress().getHostAddress())
            .orElse("anonymous")
    );
}

@Bean
public KeyResolver ipKeyResolver() {
    return exchange -> Mono.just(
        exchange.getRequest().getRemoteAddress().getAddress().getHostAddress()
    );
}
```

### 8) Service Discovery integration

```yaml
spring:
  cloud:
    gateway:
      discovery:
        locator:
          enabled: true
          lower-case-service-id: true
          predicates:
            - name: Path
              args:
                pattern: "'/services/'+serviceId+'/**'"
          filters:
            - name: RewritePath
              args:
                regexp: "'/services/' + serviceId + '/(?<s>.*)'"
                replacement: "'/${s}'"

eureka:
  client:
    service-url:
      defaultZone: http://eureka:8761/eureka
```

`lb://order-service` URI Eureka və ya Consul-dan avtomatik xidmət nümunələrini çəkir və load-balance edir.

### 9) Docker compose — gateway stack

```yaml
version: '3.9'
services:
  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]

  eureka:
    image: mycompany/eureka-server:latest
    ports: ["8761:8761"]

  gateway:
    image: mycompany/api-gateway:latest
    ports: ["8080:8080"]
    environment:
      SPRING_DATA_REDIS_HOST: redis
      EUREKA_CLIENT_SERVICE_URL_DEFAULTZONE: http://eureka:8761/eureka
    depends_on: [redis, eureka]

  order-service:
    image: mycompany/order-service:latest
    environment:
      EUREKA_CLIENT_SERVICE_URL_DEFAULTZONE: http://eureka:8761/eureka

  payment-service:
    image: mycompany/payment-service:latest
    environment:
      EUREKA_CLIENT_SERVICE_URL_DEFAULTZONE: http://eureka:8761/eureka
```

---

## Laravel-də istifadəsi

### 1) Gateway pattern — middleware + HTTP client

Laravel tipik gateway framework deyil, amma sadə səviyyədə oxşar pattern qurmaq mümkündür.

```php
// routes/api.php
Route::prefix('api')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::any('/orders/{path?}', [GatewayController::class, 'forward'])
        ->where('path', '.*')
        ->defaults('target', 'order-service');

    Route::any('/payments/{path?}', [GatewayController::class, 'forward'])
        ->where('path', '.*')
        ->defaults('target', 'payment-service');
});
```

```php
// app/Http/Controllers/GatewayController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class GatewayController extends Controller
{
    private array $services = [
        'order-service'   => 'http://order-service.internal:8081',
        'payment-service' => 'http://payment-service.internal:8082',
    ];

    public function forward(Request $request, string $target, ?string $path = null): Response
    {
        $base = $this->services[$target] ?? abort(502, 'Unknown service');
        $url = $base . '/' . ltrim($path ?? '', '/');

        $response = Http::withHeaders($this->copyHeaders($request))
            ->withOptions([
                'http_errors' => false,
                'timeout'     => 10,
                'connect_timeout' => 2,
            ])
            ->retry(2, 200, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException)
            ->send($request->method(), $url, [
                'query' => $request->query(),
                'body'  => $request->getContent(),
            ]);

        return response(
            $response->body(),
            $response->status(),
            $this->filterResponseHeaders($response->headers())
        );
    }

    private function copyHeaders(Request $request): array
    {
        $allow = ['accept', 'content-type', 'authorization', 'x-trace-id'];
        $headers = [];
        foreach ($request->headers->all() as $key => $value) {
            if (in_array(strtolower($key), $allow, true)) {
                $headers[$key] = $value[0];
            }
        }
        $headers['X-User-Id'] = (string) auth()->id();
        $headers['X-User-Email'] = (string) optional(auth()->user())->email;
        $headers['X-Trace-Id'] = $request->header('X-Trace-Id', (string) \Str::uuid());
        return $headers;
    }

    private function filterResponseHeaders(array $headers): array
    {
        $drop = ['transfer-encoding', 'connection', 'keep-alive'];
        return collect($headers)
            ->reject(fn ($v, $k) => in_array(strtolower($k), $drop, true))
            ->map(fn ($v) => $v[0])
            ->all();
    }
}
```

### 2) Auth middleware (gateway-level)

```php
// app/Http/Middleware/JwtGatewayAuth.php
namespace App\Http\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtGatewayAuth
{
    public function handle($request, \Closure $next)
    {
        $auth = $request->header('Authorization', '');
        if (! str_starts_with($auth, 'Bearer ')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        try {
            $payload = JWT::decode(substr($auth, 7), new Key(config('auth.jwt_public'), 'RS256'));
            $request->attributes->set('user_id', $payload->sub);
            $request->attributes->set('user_email', $payload->email);
            $request->headers->set('X-User-Id', $payload->sub);
            $request->headers->set('X-User-Email', $payload->email);
            $request->headers->set('X-User-Roles', implode(',', $payload->scope ?? []));
        } catch (\Throwable $e) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        return $next($request);
    }
}
```

### 3) Rate limiting

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('api', function ($request) {
        $key = $request->user()?->id ?: $request->ip();
        return Limit::perMinute(60)->by($key)
            ->response(fn () => response()->json(['error' => 'too_many_requests'], 429));
    });

    RateLimiter::for('payments', function ($request) {
        return [
            Limit::perMinute(30)->by($request->user()?->id),
            Limit::perMinute(100)->by($request->ip()),
        ];
    });
}
```

### 4) Circuit breaker pattern (sadə)

Laravel-də built-in circuit breaker yoxdur. `ganyariya/circuit-breaker` və ya custom implementation lazımdır.

```php
// app/Support/CircuitBreaker.php
namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public function __construct(
        private string $name,
        private int $failureThreshold = 5,
        private int $openSeconds = 30,
    ) {}

    public function run(callable $op, callable $fallback): mixed
    {
        if ($this->isOpen()) {
            return $fallback();
        }

        try {
            $result = $op();
            $this->reset();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            if ($this->shouldOpen()) {
                $this->trip();
            }
            return $fallback();
        }
    }

    private function key(string $suffix): string { return "cb:{$this->name}:{$suffix}"; }

    private function isOpen(): bool
    {
        return Cache::get($this->key('open'), false);
    }

    private function shouldOpen(): bool
    {
        return Cache::get($this->key('failures'), 0) >= $this->failureThreshold;
    }

    private function recordFailure(): void
    {
        Cache::increment($this->key('failures'));
        Cache::put($this->key('failures'), Cache::get($this->key('failures'), 0), 60);
    }

    private function trip(): void
    {
        Cache::put($this->key('open'), true, $this->openSeconds);
    }

    private function reset(): void
    {
        Cache::forget($this->key('failures'));
        Cache::forget($this->key('open'));
    }
}

// istifadə
$cb = new CircuitBreaker('payments', 5, 30);
$result = $cb->run(
    fn () => Http::timeout(2)->get('http://payment-service/health'),
    fn () => response()->json(['error' => 'service_unavailable'], 503)
);
```

### 5) Nginx əsl gateway kimi (praktik pattern)

Gerçək Laravel production-da gateway rolunu **Nginx** və ya **Kong** oynayır. Laravel xidməti downstream-də durur.

```nginx
# /etc/nginx/sites-available/gateway.conf
upstream order_service {
    least_conn;
    server order-1.internal:8081 max_fails=3 fail_timeout=10s;
    server order-2.internal:8081 max_fails=3 fail_timeout=10s;
    keepalive 32;
}

upstream payment_service {
    server payment-1.internal:8082 max_fails=3 fail_timeout=10s;
    server payment-2.internal:8082 max_fails=3 fail_timeout=10s;
    keepalive 32;
}

limit_req_zone $binary_remote_addr zone=api_zone:10m rate=10r/s;

server {
    listen 443 ssl http2;
    server_name api.myapp.com;

    ssl_certificate     /etc/letsencrypt/live/myapp.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/myapp.com/privkey.pem;

    location /api/orders/ {
        auth_request /_authz;
        limit_req zone=api_zone burst=20 nodelay;
        rewrite ^/api/orders/(.*)$ /$1 break;

        proxy_pass http://order_service;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-User-Id $upstream_http_x_user_id;
        proxy_connect_timeout 2s;
        proxy_read_timeout 10s;
        proxy_next_upstream error timeout http_502 http_503;
    }

    location /api/payments/ {
        auth_request /_authz;
        limit_req zone=api_zone burst=10 nodelay;
        rewrite ^/api/payments/(.*)$ /$1 break;
        proxy_pass http://payment_service;
    }

    location = /_authz {
        internal;
        proxy_pass http://auth-service.internal/verify;
        proxy_set_header Authorization $http_authorization;
        proxy_pass_request_body off;
        proxy_set_header Content-Length "";
    }
}
```

### 6) Kong Gateway + Laravel backend

```yaml
# kong.yml (declarative config)
_format_version: "3.0"

services:
  - name: order-service
    url: http://order-service.internal:8081
    routes:
      - name: orders
        paths: ["/api/orders"]
        strip_path: true
    plugins:
      - name: rate-limiting
        config: { minute: 60, policy: redis, redis_host: redis }
      - name: jwt
      - name: circuit-breaker
        config: { failure_threshold: 5, window: 30 }

  - name: payment-service
    url: http://payment-service.internal:8082
    routes:
      - name: payments
        paths: ["/api/payments"]
    plugins:
      - name: rate-limiting
        config: { minute: 30 }
      - name: correlation-id
```

### 7) Traefik + Docker

```yaml
version: '3.9'
services:
  traefik:
    image: traefik:v3.1
    command:
      - --providers.docker=true
      - --entrypoints.web.address=:80
      - --entrypoints.websecure.address=:443
    ports: ["80:80", "443:443", "8080:8080"]
    volumes: ["/var/run/docker.sock:/var/run/docker.sock"]

  order-service:
    image: mycompany/order-service-laravel:latest
    labels:
      - traefik.enable=true
      - traefik.http.routers.orders.rule=PathPrefix(`/api/orders`)
      - traefik.http.routers.orders.middlewares=orders-strip,orders-ratelimit
      - traefik.http.middlewares.orders-strip.stripprefix.prefixes=/api/orders
      - traefik.http.middlewares.orders-ratelimit.ratelimit.average=100

  payment-service:
    image: mycompany/payment-service-laravel:latest
    labels:
      - traefik.enable=true
      - traefik.http.routers.payments.rule=PathPrefix(`/api/payments`)
      - traefik.http.middlewares.payments-retry.retry.attempts=3
      - traefik.http.routers.payments.middlewares=payments-retry
```

### 8) API resource transformation (BFF layer)

Laravel "Backend for Frontend" kimi bir neçə xidmətdən məlumat çəkib bir cavabda birləşdirmək üçün yaxşı işləyir.

```php
// app/Http/Controllers/OrderDetailAggregator.php
class OrderDetailAggregator extends Controller
{
    public function show(string $orderId)
    {
        [$order, $payment, $shipping] = Http::pool(fn ($pool) => [
            $pool->get("http://order-service/orders/{$orderId}"),
            $pool->get("http://payment-service/orders/{$orderId}/payment"),
            $pool->get("http://shipping-service/orders/{$orderId}/status"),
        ]);

        return response()->json([
            'order'    => $order->json(),
            'payment'  => $payment->ok() ? $payment->json() : null,
            'shipping' => $shipping->ok() ? $shipping->json() : null,
        ]);
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Cloud Gateway | Laravel |
|---|---|---|
| Reactive stack | WebFlux + Netty | Nginx / Kong (Laravel özü sync) |
| Route DSL | YAML + Java Builder | Yoxdur (manual controller) |
| Path predicate | `Path=/api/orders/**` | `Route::any('/orders/{path}')` |
| Method predicate | `Method=GET,POST` | `Route::match(['get', 'post'])` |
| Header predicate | `Header=X-Tier, beta` | Custom middleware |
| Host/Weight/After | Built-in predicates | Manual |
| Circuit breaker | `CircuitBreaker` filter + Resilience4j | Custom və ya Kong/Envoy |
| Rate limiting | Redis rate limiter filter | `RateLimiter::for()` (per-app) |
| Service discovery | `lb://service-name` + Eureka/Consul | Manual (env URL) |
| Load balancing | Spring Cloud LoadBalancer | Nginx upstream |
| Auth filter | Global or per-route filter | Middleware (`auth:sanctum`, custom) |
| Retries | `retry` filter built-in | `Http::retry()` manual |
| Request modification | Filter DSL | Middleware `$next` manipulation |
| Fallback | `fallbackUri` forward | `try/catch` + response |
| Production role | Dedicated gateway service | Nginx/Kong/Traefik in front |

---

## Niyə belə fərqlər var?

**Spring Cloud Gateway reactive-dir.** WebFlux üzərindədir — tək thread 10K+ bağlantı idarə edə bilir. Gateway-in işi əsasən I/O (downstream-ə çağırış gözləmək) olduğuna görə reactive burada çox faydalıdır. Netflix Zuul 1-dən Zuul 2-yə keçid eyni səbəbdən oldu. Spring Cloud Gateway Zuul 2-yə cavab kimi doğuldu.

**PHP event-loop-əsaslı deyil.** PHP klassik process-per-request modelindədir. Octane (Swoole/RoadRunner) bunu dəyişsə də, Laravel gateway kimi qurulmayıb. 10K bağlantını aşağı yaddaşla idarə etmək üçün proper reactive gateway lazımdır — bu iş adətən Nginx (C), Envoy (C++), Kong (OpenResty/Lua), Traefik (Go) kimi alətlərə verilir.

**Java stack tam özü-özlüyüdür.** Spring ekosistemində Gateway, Config Server, Eureka, LoadBalancer, Resilience4j — hamısı bir-birilə uyğun dizayn olunub. Gateway Eureka-dan xidmət oxuyur, Resilience4j ilə CB yazır, Redis ilə rate limit edir. Hamısı `lb://name`, `@EnableEurekaClient` kimi bir cümlə ilə işə düşür.

**Laravel polyglot dünyada yaşayır.** Laravel ekosistemində "mikroservis framework" yoxdur — real production-da Laravel adətən Nginx arxasında, Kong və ya AWS API Gateway-in downstream-ində durur. Bu fərqli rolun təbii nəticəsidir: Laravel backend xidmətidir, gateway deyil.

**Filter DSL vs Middleware.** Spring Gateway filter-ləri declarative (YAML), type-safe (Java Builder), reactive (Mono). Laravel middleware — imperative, per-request, sync. Gateway filter-i request body stream-ini buffer etmədən dəyişə bilər — Laravel middleware-də bu daha çətindir.

**Circuit breaker fərqi.** Spring Cloud Gateway + Resilience4j built-in inteqrasiya verir — `CircuitBreaker=name` yaz, fallback URL ver, hazırdır. Laravel-də CB kitabxanalı deyil. Adətən bu iş service mesh (Istio, Linkerd) və ya Envoy-a verilir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring Cloud Gateway-də:**
- WebFlux reactive route engine
- Built-in Route Predicates (Path, Method, Header, Host, After, Before, Between, Cookie, Query, RemoteAddr, Weight)
- Built-in Filters (30+): RewritePath, StripPrefix, SetPath, AddRequestHeader, AddResponseHeader, CircuitBreaker, RequestRateLimiter, Retry, DedupeResponseHeader, ModifyRequestBody, SecureHeaders, SetStatus, RedirectTo və s.
- `lb://service-name` native service discovery URI
- Spring Cloud LoadBalancer inteqrasiyası
- Reactive Redis RateLimiter (token bucket)
- `GlobalFilter` interface ilə cross-cutting logic
- GatewayFilterFactory ilə custom filter yazma
- `forward:` və `fallbackUri` — internal route yönləndirmə
- Actuator `/actuator/gateway/routes` — live route listi
- Java DSL `RouteLocatorBuilder` ilə kod-dan route
- ReactiveDiscoveryClient ilə Consul/Eureka/K8s inteqrasiyası

**Laravel-də olmayan amma ekosistemdən gələn (Nginx/Kong/Traefik):**
- Dynamic route via labels (Traefik)
- Kong plugins (200+ hazır plugin)
- Envoy xDS protocol (dynamic config)
- Istio/Linkerd service mesh layer-i
- AWS API Gateway (managed, usage plans, API keys)
- Akamai/Cloudflare Edge Workers

**Hər iki ekosistemdə istifadə olunan:**
- JWT validation at edge
- Rate limiting (Redis backed)
- Circuit breaker pattern
- Retry with backoff
- Request/response transformation
- TLS termination
- CORS handling
- Compression

---

## Best Practices

**Spring Cloud Gateway üçün:**
- Route-ları YAML-da saxla — deploy-dan asılı olsun, runtime-da dəyişdir
- Hər route üçün circuit breaker qoy, timeout ver (2–5 saniyə)
- Retry yalnız idempotent method-larda (GET, PUT, DELETE) — POST-da yox
- `RequestRateLimiter` üçün istifadəçi key-resolver — IP yalnız fallback
- Global filter Order ver — auth → logging → business
- Downstream timeout < gateway timeout — cascade timeout olmasın
- Actuator endpoint-lərini internal network-də gizlət
- TLS termination gateway-də və ya ondan əvvəl ingress-də
- Gateway-in özü üçün HA qur (ən azı 2 instance)
- `spring.cloud.gateway.httpclient.pool` — connection pool tune et

**Laravel gateway pattern üçün:**
- Laravel-i gateway kimi qurma — Nginx/Kong/Traefik istifadə et
- Backend Laravel xidmətlərində yalnız auth şifrələnməyi yoxla (gateway JWT-ni yoxlayıb)
- `Http::pool()` ilə paralel downstream çağırışları et (BFF aggregator üçün)
- Retry və timeout-u HTTP client-də ver — hardcode etmə
- Rate limiter gateway səviyyəsində işə düşsün, Laravel yalnız auxiliary
- Kong plugin və ya Envoy filter yaz — framework-də yox, proxy-də
- BFF pattern-də response cache qoy — eyni istifadəçi üçün aggregation kəsişsin
- Header-ləri whitelist — X-User-Id və s. yalnız gateway tərəfindən gəlsin
- Laravel Sanctum yalnız edge-də deyil — internal service-to-service üçün JWT istifadə et

**Ümumi:**
- API gateway single point of failure olmamalıdır — HA + auto-scale
- Gateway observability: hər sorğu üçün trace-id yay, OpenTelemetry-yə göndər
- Canary və blue-green deploy-i gateway səviyyəsində idarə et
- Gateway-də business logic yazma — yalnız cross-cutting (auth, rate, transform)
- Request size limit qoy — 10MB-dan böyük gateway-dən keçməsin

---

## Yekun

**Spring Cloud Gateway** Java mikroservis mühitində full-featured reactive API gateway-dir. Route Predicates + Filters declarative DSL, service discovery inteqrasiyası, circuit breaker, Redis rate limiter — hamısı built-in. Əgər stack Java-dırsa və 20+ xidmət idarə olunursa, bu, seçim olur.

**Laravel-də gateway rolu framework-dən kənardadır.** Laravel application-layer backend-dir, edge-də Nginx, Kong, Traefik və ya AWS API Gateway durur. Laravel `Http::pool()`, middleware chain, controller-forward pattern-i ilə sadə gateway-gibi funksiyalar yaza bilər, amma bu tam həll deyil.

Seçim qaydası: **Polyglot mühit və ya hər hansı non-Java stack üçün Kong, Traefik, Envoy-u seç.** Java-only və Spring ekosisteminə bağlıdırsa, Spring Cloud Gateway daha təbii inteqrasiya verir. Hybrid mühit (Java + PHP + Node) üçün ortaq edge — **Kong və ya Envoy** — hər iki tərəfi əhatə edir və framework-dən asılı olmayan control plane verir.
