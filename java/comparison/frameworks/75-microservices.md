# Mikroservislər (Microservices)

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

Mikroservis arxitekturası böyük tətbiqləri kiçik, müstəqil xidmətlərə bölməyə əsaslanır. Spring bu sahədə liderdir — Spring Cloud ekosistemi mikroservis arxitekturasının demək olar ki, hər aspektini əhatə edir. Laravel isə monolit framework olaraq dizayn edilib, amma müəyyən yanaşmalarla mikroservis mühitində istifadə oluna bilər.

---

## Spring-də istifadəsi

### Spring Cloud ekosisteminə baxış

Spring Cloud, mikroservis arxitekturası üçün lazım olan bütün komponentləri təmin edir:

```
┌──────────────────────────────────────────────────────────────┐
│                     API Gateway                               │
│                 (Spring Cloud Gateway)                         │
├──────────────────────────────────────────────────────────────┤
│                  Service Discovery                            │
│                   (Eureka Server)                              │
├────────────┬────────────┬────────────┬───────────────────────┤
│  User      │  Product   │  Order     │  Payment              │
│  Service   │  Service   │  Service   │  Service              │
│  :8081     │  :8082     │  :8083     │  :8084                │
├────────────┴────────────┴────────────┴───────────────────────┤
│              Config Server (mərkəzi konfiqurasiya)            │
├──────────────────────────────────────────────────────────────┤
│         Distributed Tracing (Zipkin / Jaeger)                 │
└──────────────────────────────────────────────────────────────┘
```

### 1. Eureka — Service Discovery

Hər servis qeydiyyatdan keçir və digər servisləri IP/port bilmədən adı ilə tapa bilir.

**Eureka Server:**

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-netflix-eureka-server</artifactId>
</dependency>
```

```java
@SpringBootApplication
@EnableEurekaServer
public class EurekaServerApplication {
    public static void main(String[] args) {
        SpringApplication.run(EurekaServerApplication.class, args);
    }
}
```

```yaml
# eureka-server application.yml
server:
  port: 8761

eureka:
  client:
    register-with-eureka: false
    fetch-registry: false
```

**Eureka Client (hər servisdə):**

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-netflix-eureka-client</artifactId>
</dependency>
```

```yaml
# user-service application.yml
spring:
  application:
    name: user-service

eureka:
  client:
    service-url:
      defaultZone: http://localhost:8761/eureka/
  instance:
    prefer-ip-address: true
```

Artıq `user-service` adı ilə bu servis tapıla bilər.

### 2. Spring Cloud Gateway — API Gateway

Bütün sorğular tək nöqtədən keçir, routing, rate limiting, authentication aparılır:

```xml
<dependency>
    <groupId>org.springframework.cloud</groupId>
    <artifactId>spring-cloud-starter-gateway</artifactId>
</dependency>
```

```yaml
# gateway application.yml
spring:
  application:
    name: api-gateway
  cloud:
    gateway:
      routes:
        - id: user-service
          uri: lb://user-service          # lb:// = load balanced (Eureka-dan tapır)
          predicates:
            - Path=/api/users/**
          filters:
            - StripPrefix=1
            - name: CircuitBreaker
              args:
                name: userServiceCB
                fallbackUri: forward:/fallback/users

        - id: product-service
          uri: lb://product-service
          predicates:
            - Path=/api/products/**
          filters:
            - StripPrefix=1

        - id: order-service
          uri: lb://order-service
          predicates:
            - Path=/api/orders/**
          filters:
            - StripPrefix=1
            - name: RequestRateLimiter
              args:
                redis-rate-limiter.replenishRate: 10
                redis-rate-limiter.burstCapacity: 20
```

```java
// Xüsusi Gateway filtr
@Component
public class AuthenticationFilter implements GlobalFilter, Ordered {

    private final JwtUtil jwtUtil;

    public AuthenticationFilter(JwtUtil jwtUtil) {
        this.jwtUtil = jwtUtil;
    }

    @Override
    public Mono<Void> filter(ServerWebExchange exchange, GatewayFilterChain chain) {
        String path = exchange.getRequest().getPath().toString();

        // Açıq endpoint-lər
        if (path.contains("/auth/login") || path.contains("/auth/register")) {
            return chain.filter(exchange);
        }

        String authHeader = exchange.getRequest().getHeaders().getFirst("Authorization");
        if (authHeader == null || !authHeader.startsWith("Bearer ")) {
            exchange.getResponse().setStatusCode(HttpStatus.UNAUTHORIZED);
            return exchange.getResponse().setComplete();
        }

        String token = authHeader.substring(7);
        try {
            Claims claims = jwtUtil.validateToken(token);
            // İstifadəçi məlumatını header-ə əlavə et
            exchange.getRequest().mutate()
                .header("X-User-Id", claims.getSubject())
                .header("X-User-Role", claims.get("role", String.class))
                .build();
        } catch (Exception e) {
            exchange.getResponse().setStatusCode(HttpStatus.UNAUTHORIZED);
            return exchange.getResponse().setComplete();
        }

        return chain.filter(exchange);
    }

    @Override
    public int getOrder() {
        return -1;
    }
}
```

### 3. Config Server — Mərkəzi konfiqurasiya

Bütün servislər konfiqurasiyalarını bir yerdən alır:

```java
@SpringBootApplication
@EnableConfigServer
public class ConfigServerApplication {
    public static void main(String[] args) {
        SpringApplication.run(ConfigServerApplication.class, args);
    }
}
```

```yaml
# config-server application.yml
spring:
  cloud:
    config:
      server:
        git:
          uri: https://github.com/company/config-repo
          default-label: main
          search-paths: '{application}'
```

Git repo-da hər servis üçün konfiqurasiya faylları:

```
config-repo/
├── user-service/
│   ├── application.yml          # Bütün mühitlər üçün
│   ├── application-dev.yml      # Development
│   └── application-prod.yml     # Production
├── product-service/
│   └── application.yml
└── application.yml              # Bütün servislər üçün ortaq
```

Servislərdə:

```yaml
# user-service bootstrap.yml
spring:
  application:
    name: user-service
  config:
    import: configserver:http://localhost:8888
```

### 4. OpenFeign — Servislər arası kommunikasiya

REST client yazmağa ehtiyac yoxdur — interface yazırsınız, Spring implementasiyanı yaradır:

```java
// Order Service-dən User Service-ə müraciət
@FeignClient(name = "user-service", fallbackFactory = UserClientFallbackFactory.class)
public interface UserClient {

    @GetMapping("/users/{id}")
    UserDto getUserById(@PathVariable("id") Long id);

    @GetMapping("/users")
    List<UserDto> getUsersByIds(@RequestParam("ids") List<Long> ids);

    @PostMapping("/users/{id}/verify")
    void verifyUser(@PathVariable("id") Long id);
}

// Fallback — servis cavab verməsə
@Component
public class UserClientFallbackFactory implements FallbackFactory<UserClient> {

    @Override
    public UserClient create(Throwable cause) {
        return new UserClient() {
            @Override
            public UserDto getUserById(Long id) {
                // Cached və ya default dəyər qaytar
                return new UserDto(id, "Bilinməyən İstifadəçi", null);
            }

            @Override
            public List<UserDto> getUsersByIds(List<Long> ids) {
                return Collections.emptyList();
            }

            @Override
            public void verifyUser(Long id) {
                // Log yaz, sonra retry olacaq
            }
        };
    }
}

// İstifadəsi — adi servis kimi inject edilir
@Service
public class OrderService {

    private final OrderRepository orderRepository;
    private final UserClient userClient;
    private final ProductClient productClient;

    public OrderService(OrderRepository orderRepository,
                        UserClient userClient,
                        ProductClient productClient) {
        this.orderRepository = orderRepository;
        this.userClient = userClient;
        this.productClient = productClient;
    }

    public OrderDto createOrder(CreateOrderRequest request) {
        // User Service-dən istifadəçini yoxla
        UserDto user = userClient.getUserById(request.getUserId());
        if (user == null) {
            throw new UserNotFoundException(request.getUserId());
        }

        // Product Service-dən məhsulları yoxla
        List<ProductDto> products = productClient.getProductsByIds(
            request.getItems().stream().map(Item::getProductId).toList()
        );

        // Sifariş yarat
        Order order = new Order();
        order.setUserId(user.getId());
        // ...
        return orderRepository.save(order).toDto();
    }
}
```

### 5. Resilience4j — Circuit Breaker

Bir servis cavab verməsə, bütün sistemin çökməsinin qarşısını alır:

```java
@Service
public class ProductService {

    private final ProductClient productClient;

    public ProductService(ProductClient productClient) {
        this.productClient = productClient;
    }

    @CircuitBreaker(name = "productService", fallbackMethod = "getProductFallback")
    @Retry(name = "productService", fallbackMethod = "getProductFallback")
    @TimeLimiter(name = "productService")
    public CompletableFuture<ProductDto> getProduct(Long id) {
        return CompletableFuture.supplyAsync(() -> productClient.getProductById(id));
    }

    // Circuit açıq olduqda bu metod çağırılır
    private CompletableFuture<ProductDto> getProductFallback(Long id, Throwable t) {
        // Cache-dən oxu və ya default qaytar
        return CompletableFuture.completedFuture(
            new ProductDto(id, "Məhsul müvəqqəti əlçatmazdır", BigDecimal.ZERO)
        );
    }
}
```

```yaml
# Resilience4j konfiqurasiyası
resilience4j:
  circuitbreaker:
    instances:
      productService:
        sliding-window-size: 10
        failure-rate-threshold: 50       # 50% uğursuzluqda aç
        wait-duration-in-open-state: 10s # 10 saniyə gözlə
        permitted-number-of-calls-in-half-open-state: 3
  retry:
    instances:
      productService:
        max-attempts: 3
        wait-duration: 500ms
  timelimiter:
    instances:
      productService:
        timeout-duration: 3s
```

### 6. Distributed Tracing — Paylanmış izləmə

Bir sorğunun bütün servislər arasında yolunu izləmək:

```xml
<dependency>
    <groupId>io.micrometer</groupId>
    <artifactId>micrometer-tracing-bridge-otel</artifactId>
</dependency>
<dependency>
    <groupId>io.opentelemetry</groupId>
    <artifactId>opentelemetry-exporter-zipkin</artifactId>
</dependency>
```

```yaml
management:
  tracing:
    sampling:
      probability: 1.0     # Development-də 100%, production-da 0.1
  zipkin:
    tracing:
      endpoint: http://localhost:9411/api/v2/spans
```

Heç bir kod dəyişikliyi lazım deyil — Spring avtomatik olaraq hər HTTP sorğusuna trace ID əlavə edir. Zipkin UI-da bir istifadəçi sorğusunun Gateway -> User Service -> Order Service -> Payment Service yolunu görmək olur.

---

## Laravel-də istifadəsi

Laravel monolit framework-dür, amma mikroservis mühitində müəyyən yanaşmalarla istifadə oluna bilər.

### Laravel Octane — Performans

Octane, Laravel-i long-running proses kimi işlədərək hər sorğuda framework-ü yenidən yükləməkdən qaçır:

```bash
composer require laravel/octane
php artisan octane:install
```

```php
// config/octane.php
return [
    'server' => 'swoole', // və ya 'roadrunner'
    'workers' => env('OCTANE_WORKERS', 4),
    'task_workers' => env('OCTANE_TASK_WORKERS', 2),
    'max_requests' => 500,
];
```

```bash
php artisan octane:start --workers=4 --port=8000
```

### Queue Workers — Servis kimi

Laravel queue worker-ləri ayrı proseslər kimi işlədilə bilər:

```php
// Sifarişləri emal edən ayrı worker
// app/Jobs/ProcessOrder.php
class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function handle(
        PaymentGateway $paymentGateway,
        InventoryService $inventoryService,
        NotificationService $notificationService
    ): void {
        // Ödəniş
        $payment = $paymentGateway->charge(
            $this->order->total,
            $this->order->payment_method
        );

        if ($payment->successful()) {
            // Stok azalt
            $inventoryService->decrementStock($this->order->items);

            // Bildiriş göndər
            $notificationService->orderConfirmed($this->order);

            $this->order->update(['status' => 'processing']);
        } else {
            $this->order->update(['status' => 'payment_failed']);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->order->update(['status' => 'failed']);
        Log::error('Sifariş emalı uğursuz', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

Fərqli queue-ları ayrı proseslərdə işlətmək:

```bash
# Sifariş queue-su
php artisan queue:work redis --queue=orders --tries=3

# Bildiriş queue-su
php artisan queue:work redis --queue=notifications --tries=5

# Email queue-su
php artisan queue:work redis --queue=emails --tries=3 --timeout=30
```

### Servislər arası HTTP kommunikasiya

```php
// app/Services/ExternalUserService.php
class ExternalUserService
{
    public function __construct(
        private Http $http
    ) {}

    public function getUser(int $id): ?array
    {
        $response = Http::baseUrl(config('services.user_service.url'))
            ->withToken(config('services.user_service.token'))
            ->timeout(5)
            ->retry(3, 100)
            ->get("/api/users/{$id}");

        if ($response->failed()) {
            Log::warning("User service cavab vermədi", [
                'user_id' => $id,
                'status' => $response->status(),
            ]);
            return null;
        }

        return $response->json('data');
    }

    public function getUsersByIds(array $ids): array
    {
        $response = Http::baseUrl(config('services.user_service.url'))
            ->withToken(config('services.user_service.token'))
            ->timeout(10)
            ->retry(3, 100)
            ->get('/api/users', ['ids' => implode(',', $ids)]);

        return $response->json('data', []);
    }
}
```

```php
// config/services.php
return [
    'user_service' => [
        'url' => env('USER_SERVICE_URL', 'http://user-service:8001'),
        'token' => env('USER_SERVICE_TOKEN'),
    ],
    'product_service' => [
        'url' => env('PRODUCT_SERVICE_URL', 'http://product-service:8002'),
        'token' => env('PRODUCT_SERVICE_TOKEN'),
    ],
    'payment_service' => [
        'url' => env('PAYMENT_SERVICE_URL', 'http://payment-service:8003'),
        'token' => env('PAYMENT_SERVICE_TOKEN'),
    ],
];
```

### API Gateway pattern — Laravel ilə

Laravel özü API Gateway kimi istifadə oluna bilər:

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // Proxy sorğuları uyğun servislərə yönləndir
    Route::any('users/{path?}', [GatewayController::class, 'users'])
        ->where('path', '.*');
    Route::any('products/{path?}', [GatewayController::class, 'products'])
        ->where('path', '.*');
    Route::any('orders/{path?}', [GatewayController::class, 'orders'])
        ->where('path', '.*');
});

class GatewayController extends Controller
{
    public function users(Request $request, ?string $path = '')
    {
        return $this->proxy($request, 'user_service', $path);
    }

    public function products(Request $request, ?string $path = '')
    {
        return $this->proxy($request, 'product_service', $path);
    }

    public function orders(Request $request, ?string $path = '')
    {
        return $this->proxy($request, 'order_service', $path);
    }

    private function proxy(Request $request, string $service, string $path): Response
    {
        $baseUrl = config("services.{$service}.url");
        $url = "{$baseUrl}/api/{$path}";

        $response = Http::withHeaders([
                'X-User-Id' => $request->user()?->id,
                'X-Request-Id' => $request->header('X-Request-Id', Str::uuid()),
            ])
            ->timeout(10)
            ->send(
                $request->method(),
                $url,
                [
                    'query' => $request->query(),
                    'body' => $request->getContent(),
                    'headers' => ['Content-Type' => $request->header('Content-Type')],
                ]
            );

        return response($response->body(), $response->status())
            ->withHeaders($response->headers());
    }
}
```

### Circuit Breaker — Laravel-də əl ilə

```php
// app/Services/CircuitBreaker.php
class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private string $service,
        private int $threshold = 5,
        private int $timeout = 30,
    ) {}

    public function call(callable $action, callable $fallback = null): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset()) {
                Cache::put("circuit:{$this->service}:state", self::STATE_HALF_OPEN, $this->timeout);
            } else {
                return $fallback ? $fallback() : throw new ServiceUnavailableException($this->service);
            }
        }

        try {
            $result = $action();
            $this->recordSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->recordFailure();

            if ($this->getFailureCount() >= $this->threshold) {
                $this->trip();
            }

            return $fallback ? $fallback() : throw $e;
        }
    }

    private function getState(): string
    {
        return Cache::get("circuit:{$this->service}:state", self::STATE_CLOSED);
    }

    private function trip(): void
    {
        Cache::put("circuit:{$this->service}:state", self::STATE_OPEN, $this->timeout);
        Cache::put("circuit:{$this->service}:tripped_at", now()->timestamp, $this->timeout * 2);
    }

    private function shouldAttemptReset(): bool
    {
        $trippedAt = Cache::get("circuit:{$this->service}:tripped_at", 0);
        return (now()->timestamp - $trippedAt) >= $this->timeout;
    }

    private function recordFailure(): void
    {
        Cache::increment("circuit:{$this->service}:failures");
    }

    private function recordSuccess(): void
    {
        Cache::forget("circuit:{$this->service}:failures");
        Cache::put("circuit:{$this->service}:state", self::STATE_CLOSED);
    }

    private function getFailureCount(): int
    {
        return (int) Cache::get("circuit:{$this->service}:failures", 0);
    }
}

// İstifadəsi
class ProductService
{
    public function getProduct(int $id): ?ProductDto
    {
        $circuitBreaker = new CircuitBreaker('product-service', threshold: 5, timeout: 30);

        return $circuitBreaker->call(
            action: fn () => Http::get("http://product-service/api/products/{$id}")->json(),
            fallback: fn () => Cache::get("product:{$id}"), // Cache-dən oxu
        );
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Mikroservis ekosistemi | Tam (Spring Cloud) | Yoxdur (əl ilə qurulur) |
| Service Discovery | Eureka (daxili) | Consul/etcd (xarici, əl ilə) |
| API Gateway | Spring Cloud Gateway | Əl ilə proxy və ya Nginx/Kong |
| Config Server | Spring Cloud Config | Yoxdur (.env faylları) |
| Circuit Breaker | Resilience4j (annotasiya ilə) | Əl ilə yazılır və ya xarici paket |
| Service-to-service çağırış | OpenFeign (interface-based) | HTTP Client (əl ilə) |
| Distributed Tracing | Micrometer + Zipkin/Jaeger | Xarici alətlər (əl ilə inteqrasiya) |
| Load Balancing | Client-side (Ribbon/LoadBalancer) | Server-side (Nginx, HAProxy) |
| Micro-framework | Yoxdur (Spring Boot kifayətdir) | Lumen (artıq deprecated) |
| Long-running proses | Default (JVM) | Octane (Swoole/RoadRunner) |

---

## Niyə belə fərqlər var?

Bu, ən böyük fərqlərdən biridir və əsas səbəblər bunlardır:

**1. JVM vs PHP Runtime**

Java tətbiqləri uzunmüddətli proseslər kimi işləyir — bir dəfə başlayır, yaddaşda qalır, minlərlə sorğu emal edir. Bu, service discovery, circuit breaker, connection pooling kimi konseptləri təbii edir. PHP isə tradisional olaraq hər sorğuda yenidən başlayır — bu, mikroservis kommunikasiyası üçün əlverişli deyil (hər sorğuda yeni HTTP bağlantısı, yeni service discovery sorğusu lazım olur).

**2. Enterprise vs Web nişanı**

Spring, enterprise Java dünyasından gəlir — böyük şirkətlər, çoxlu komandalar, mürəkkəb sistemlər. Mikroservislər bu mühitin tələbidir. Laravel isə web development üçün yaradılıb — çox vaxt tək komanda bir monolit tətbiq hazırlayır.

**3. İnvestisiya və icma**

Spring Cloud-un arxasında VMware/Broadcom (əvvəl Pivotal) dayanır — milyonlarla dollar investisiya. Netflix, Alibaba kimi şirkətlər Spring Cloud-a dəstək verib. Laravel-in arxasında isə bir developer (Taylor Otwell) və icma var — mikroservis ekosistemi yaratmaq üçün resurs kifayət deyil.

**4. Monolit kifayətdir**

Laravel icması üçün monolit arxitektura əksər hallarda kifayətdir. Queue worker-ləri ayrı proseslərdə işlədərək, Redis/database ilə kommunikasiya edərək, və horizontal scaling ilə böyük yükü daşımaq mümkündür. Hər şeyi mikroservisə bölmək lazım deyil.

**5. Konteynerləşdirmə dövründə**

Docker və Kubernetes kimi alətlər bəzi Spring Cloud funksiyalarını (service discovery, config management, load balancing) platform səviyyəsində həll edir. Bu, Laravel tətbiqinin də mikroservis mühitində istifadəsini asanlaşdırır — çünki Kubernetes özü service discovery, health check, auto-scaling təmin edir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Eureka ilə avtomatik service discovery
- Spring Cloud Gateway ilə tam funksional API Gateway
- Spring Cloud Config ilə mərkəzi konfiqurasiya idarəsi
- OpenFeign ilə deklarativ service-to-service çağırışlar
- Resilience4j ilə annotasiya əsaslı circuit breaker
- Spring Cloud Stream ilə event-driven arxitektura
- Spring Cloud Sleuth/Micrometer Tracing ilə distributed tracing
- Client-side load balancing

**Yalnız Laravel-də:**
- Octane ilə PHP-nin performans limitlərini aşmaq (Swoole/RoadRunner)
- Artisan queue worker-ləri ilə sadə asinxron emal
- Horizon ilə queue monitoring
- Laravel-in sadəliyi sayəsində sürətli prototipləmə
- Monolit arxitekturada bütün biznes məntiqini bir yerdə saxlamaq imkanı

**Praktiki tövsiyə:** Əgər mikroservis arxitekturası lazımdırsa, Spring açıq liderdir. Əgər monolit kifayətdirsə (və əksər hallarda kifayətdir), Laravel daha sürətli nəticə verir. Hibrid yanaşma da mümkündür — əsas tətbiq Laravel, kritik xidmətlər (ödəniş, real-time) Spring və ya Go ilə.
