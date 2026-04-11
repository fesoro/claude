# Rate Limiting (Sorğu Limiti): Spring vs Laravel

## Giriş

Rate limiting (sorğu limiti) API və web tətbiqləri hücumlardan, sui-istifadədən və həddindən artıq yüklənmədən qorumaq üçün istifadə olunan mexanizmdir. Müəyyən vaxt ərzində bir istifadəçinin və ya IP ünvanının neçə sorğu göndərə biləcəyini məhdudlaşdırır. Laravel-də rate limiting **daxili xüsusiyyət** kimi mövcuddur, Spring-də isə **üçüncü tərəf kitabxanaları** (Bucket4j, Resilience4j) və ya custom həllər tələb olunur.

## Spring-də istifadəsi

Spring Framework-da daxili rate limiting yoxdur. Bir neçə yanaşma mövcuddur:

### Bucket4j ilə Rate Limiting

Bucket4j "token bucket" alqoritminə əsaslanır. Hər bucket-də müəyyən sayda token var, hər sorğu bir token istifadə edir, tokenlar vaxt keçdikcə yenilənir.

```xml
<!-- pom.xml -->
<dependency>
    <groupId>com.bucket4j</groupId>
    <artifactId>bucket4j-core</artifactId>
    <version>8.7.0</version>
</dependency>
```

```java
// Sadə in-memory rate limiter
@Component
public class RateLimitFilter extends OncePerRequestFilter {

    private final Map<String, Bucket> buckets = new ConcurrentHashMap<>();

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                     HttpServletResponse response,
                                     FilterChain filterChain)
            throws ServletException, IOException {

        String clientIp = getClientIp(request);
        Bucket bucket = buckets.computeIfAbsent(clientIp, this::createBucket);

        ConsumptionProbe probe = bucket.tryConsumeAndReturnRemaining(1);

        if (probe.isConsumed()) {
            // Rate limit header-ları əlavə etmək
            response.addHeader("X-Rate-Limit-Remaining",
                String.valueOf(probe.getRemainingTokens()));
            filterChain.doFilter(request, response);
        } else {
            // Limit aşılıb
            response.setStatus(HttpStatus.TOO_MANY_REQUESTS.value());
            response.setContentType("application/json");
            response.getWriter().write("""
                {
                    "error": "Çox sayda sorğu göndərdiniz",
                    "retryAfterSeconds": %d
                }
                """.formatted(
                    TimeUnit.NANOSECONDS.toSeconds(probe.getNanosToWaitForRefill())
                ));
        }
    }

    private Bucket createBucket(String key) {
        return Bucket.builder()
            .addLimit(Bandwidth.classic(
                60,  // 60 token (sorğu)
                Refill.intervally(60, Duration.ofMinutes(1))  // Hər dəqiqə 60 token
            ))
            .build();
    }

    private String getClientIp(HttpServletRequest request) {
        String xForwardedFor = request.getHeader("X-Forwarded-For");
        if (xForwardedFor != null && !xForwardedFor.isEmpty()) {
            return xForwardedFor.split(",")[0].trim();
        }
        return request.getRemoteAddr();
    }
}
```

### Müxtəlif endpoint-lər üçün fərqli limitlər

```java
@Component
public class ApiRateLimitFilter extends OncePerRequestFilter {

    private final Map<String, Bucket> buckets = new ConcurrentHashMap<>();

    // Endpoint üzrə fərqli konfiqurasiyalar
    private enum RateLimitPlan {
        FREE(20, Duration.ofMinutes(1)),
        STANDARD(60, Duration.ofMinutes(1)),
        PREMIUM(200, Duration.ofMinutes(1)),
        AUTH(5, Duration.ofMinutes(15));  // Login cəhdləri

        final int capacity;
        final Duration period;

        RateLimitPlan(int capacity, Duration period) {
            this.capacity = capacity;
            this.period = period;
        }
    }

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                     HttpServletResponse response,
                                     FilterChain filterChain)
            throws ServletException, IOException {

        String key = resolveKey(request);
        RateLimitPlan plan = resolvePlan(request);

        Bucket bucket = buckets.computeIfAbsent(key,
            k -> createBucket(plan));

        ConsumptionProbe probe = bucket.tryConsumeAndReturnRemaining(1);

        response.addHeader("X-Rate-Limit-Limit", String.valueOf(plan.capacity));
        response.addHeader("X-Rate-Limit-Remaining",
            String.valueOf(probe.getRemainingTokens()));

        if (probe.isConsumed()) {
            filterChain.doFilter(request, response);
        } else {
            long retryAfter = TimeUnit.NANOSECONDS.toSeconds(
                probe.getNanosToWaitForRefill());
            response.addHeader("Retry-After", String.valueOf(retryAfter));
            response.setStatus(429);
            response.setContentType("application/json");
            response.getWriter().write(
                "{\"error\":\"Rate limit aşıldı\",\"retryAfter\":" + retryAfter + "}");
        }
    }

    private String resolveKey(HttpServletRequest request) {
        // Autentifikasiya olunmuş istifadəçi üçün user ID
        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        if (auth != null && auth.isAuthenticated()
                && !(auth instanceof AnonymousAuthenticationToken)) {
            return "user:" + auth.getName() + ":" + request.getRequestURI();
        }
        // Anonim üçün IP
        return "ip:" + getClientIp(request) + ":" + request.getRequestURI();
    }

    private RateLimitPlan resolvePlan(HttpServletRequest request) {
        String path = request.getRequestURI();

        if (path.startsWith("/api/auth/login")) {
            return RateLimitPlan.AUTH;
        }

        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        if (auth != null && auth.getAuthorities().stream()
                .anyMatch(a -> a.getAuthority().equals("ROLE_PREMIUM"))) {
            return RateLimitPlan.PREMIUM;
        }

        if (auth != null && auth.isAuthenticated()) {
            return RateLimitPlan.STANDARD;
        }

        return RateLimitPlan.FREE;
    }

    private Bucket createBucket(RateLimitPlan plan) {
        return Bucket.builder()
            .addLimit(Bandwidth.classic(
                plan.capacity,
                Refill.intervally(plan.capacity, plan.period)
            ))
            .build();
    }

    private String getClientIp(HttpServletRequest request) {
        String xff = request.getHeader("X-Forwarded-For");
        return xff != null ? xff.split(",")[0].trim() : request.getRemoteAddr();
    }
}
```

### Redis əsaslı rate limiting

Distributed mühitdə Redis istifadə olunmalıdır:

```xml
<!-- pom.xml -->
<dependency>
    <groupId>com.bucket4j</groupId>
    <artifactId>bucket4j-redis</artifactId>
    <version>8.7.0</version>
</dependency>
```

```java
@Configuration
public class RateLimitConfig {

    @Bean
    public ProxyManager<String> proxyManager(LettuceConnectionFactory connectionFactory) {
        RedisClient redisClient = RedisClient.create(
            RedisURI.create("localhost", 6379));
        StatefulRedisConnection<String, byte[]> connection =
            redisClient.connect(RedisCodec.of(StringCodec.UTF8, ByteArrayCodec.INSTANCE));

        return LettuceBasedProxyManager.builderFor(connection)
            .withExpirationStrategy(
                ExpirationAfterWriteStrategy.basedOnTimeForRefillingBucketUpToMax(
                    Duration.ofMinutes(5)))
            .build();
    }
}

@Component
public class RedisRateLimitFilter extends OncePerRequestFilter {

    @Autowired
    private ProxyManager<String> proxyManager;

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                     HttpServletResponse response,
                                     FilterChain filterChain)
            throws ServletException, IOException {

        String key = "rate-limit:" + getClientIp(request);

        BucketConfiguration config = BucketConfiguration.builder()
            .addLimit(Bandwidth.classic(60, Refill.intervally(60, Duration.ofMinutes(1))))
            .build();

        Bucket bucket = proxyManager.builder()
            .build(key, () -> config);

        ConsumptionProbe probe = bucket.tryConsumeAndReturnRemaining(1);

        if (probe.isConsumed()) {
            response.addHeader("X-Rate-Limit-Remaining",
                String.valueOf(probe.getRemainingTokens()));
            filterChain.doFilter(request, response);
        } else {
            response.setStatus(429);
            response.getWriter().write("{\"error\":\"Rate limit aşıldı\"}");
        }
    }

    private String getClientIp(HttpServletRequest request) {
        String xff = request.getHeader("X-Forwarded-For");
        return xff != null ? xff.split(",")[0].trim() : request.getRemoteAddr();
    }
}
```

### Resilience4j ilə rate limiting

```xml
<dependency>
    <groupId>io.github.resilience4j</groupId>
    <artifactId>resilience4j-ratelimiter</artifactId>
    <version>2.1.0</version>
</dependency>
```

```java
@Configuration
public class Resilience4jConfig {

    @Bean
    public RateLimiter apiRateLimiter() {
        RateLimiterConfig config = RateLimiterConfig.custom()
            .limitForPeriod(50)                    // Period başına 50 sorğu
            .limitRefreshPeriod(Duration.ofMinutes(1))  // Hər dəqiqə yenilənir
            .timeoutDuration(Duration.ofSeconds(5))     // Gözləmə müddəti
            .build();

        return RateLimiter.of("api", config);
    }
}

// Controller-də istifadə
@RestController
@RequestMapping("/api")
public class ApiController {

    @Autowired
    private RateLimiter apiRateLimiter;

    @GetMapping("/data")
    public ResponseEntity<?> getData() {
        // Dekorativ yanaşma
        Supplier<ResponseEntity<?>> supplier = RateLimiter
            .decorateSupplier(apiRateLimiter, () -> {
                List<Data> data = dataService.findAll();
                return ResponseEntity.ok(data);
            });

        try {
            return supplier.get();
        } catch (RequestNotPermitted e) {
            return ResponseEntity.status(429)
                .body(Map.of("error", "Sorğu limiti aşıldı"));
        }
    }
}

// Annotation ilə (AOP)
@RestController
@RequestMapping("/api/products")
public class ProductApiController {

    @GetMapping
    @RateLimiter(name = "api", fallbackMethod = "rateLimitFallback")
    public ResponseEntity<List<Product>> getProducts() {
        return ResponseEntity.ok(productService.findAll());
    }

    public ResponseEntity<Map<String, String>> rateLimitFallback(
            RequestNotPermitted ex) {
        return ResponseEntity.status(429)
            .body(Map.of("error", "Çox sayda sorğu. Zəhmət olmasa gözləyin."));
    }
}
```

### Custom Annotation ilə rate limiting

```java
// Custom annotation
@Target(ElementType.METHOD)
@Retention(RetentionPolicy.RUNTIME)
public @interface RateLimit {
    int requests() default 60;
    int minutes() default 1;
    String key() default "";  // boş = IP əsaslı
}

// AOP aspect
@Aspect
@Component
public class RateLimitAspect {

    private final Map<String, Bucket> buckets = new ConcurrentHashMap<>();

    @Autowired
    private HttpServletRequest request;

    @Around("@annotation(rateLimit)")
    public Object checkRateLimit(ProceedingJoinPoint joinPoint,
                                  RateLimit rateLimit) throws Throwable {
        String key = resolveKey(rateLimit);

        Bucket bucket = buckets.computeIfAbsent(key, k ->
            Bucket.builder()
                .addLimit(Bandwidth.classic(
                    rateLimit.requests(),
                    Refill.intervally(rateLimit.requests(),
                        Duration.ofMinutes(rateLimit.minutes()))
                ))
                .build()
        );

        if (bucket.tryConsume(1)) {
            return joinPoint.proceed();
        }

        throw new ResponseStatusException(
            HttpStatus.TOO_MANY_REQUESTS,
            "Sorğu limiti aşıldı"
        );
    }

    private String resolveKey(RateLimit rateLimit) {
        if (!rateLimit.key().isEmpty()) {
            return rateLimit.key() + ":" + request.getRemoteAddr();
        }
        return request.getRemoteAddr();
    }
}

// İstifadəsi
@RestController
@RequestMapping("/api")
public class ApiController {

    @GetMapping("/products")
    @RateLimit(requests = 100, minutes = 1)
    public List<Product> products() {
        return productService.findAll();
    }

    @PostMapping("/auth/login")
    @RateLimit(requests = 5, minutes = 15, key = "login")
    public ResponseEntity<?> login(@RequestBody LoginRequest request) {
        // login logic
    }

    @PostMapping("/export")
    @RateLimit(requests = 3, minutes = 60, key = "export")
    public ResponseEntity<?> exportData() {
        // ağır əməliyyat
    }
}
```

## Laravel-də istifadəsi

### Daxili RateLimiter

Laravel-də rate limiting framework-ün daxili xüsusiyyətidir:

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    protected function configureRateLimiting(): void
    {
        // Defolt API limiti
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Login cəhdləri üçün
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->input('email') . '|' . $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error' => 'Çox sayda uğursuz cəhd. Zəhmət olmasa gözləyin.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429, $headers);
                });
        });

        // Premium istifadəçilər üçün yüksək limit
        RateLimiter::for('premium-api', function (Request $request) {
            if ($request->user()?->isPremium()) {
                return Limit::perMinute(200)->by($request->user()->id);
            }

            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Bir neçə limit birlikdə
        RateLimiter::for('uploads', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->user()->id),      // dəqiqədə 10
                Limit::perHour(50)->by($request->user()->id),        // saatda 50
                Limit::perDay(200)->by($request->user()->id),        // gündə 200
            ];
        });

        // IP əsaslı global limit
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(1000)
                ->by($request->ip());
        });

        // Heç bir limit yoxdur (whitelist)
        RateLimiter::for('none', function (Request $request) {
            return Limit::none();
        });
    }
}
```

### Throttle Middleware

```php
// routes/api.php

// Defolt API rate limiting
Route::middleware('throttle:api')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
});

// Sadə throttle: dəqiqədə 60 sorğu
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/search', [SearchController::class, 'index']);
});

// Named rate limiter istifadə
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Premium API
Route::middleware(['auth:sanctum', 'throttle:premium-api'])->group(function () {
    Route::get('/api/analytics', [AnalyticsController::class, 'index']);
    Route::post('/api/export', [ExportController::class, 'store']);
});

// Upload-lar üçün
Route::middleware(['auth', 'throttle:uploads'])->group(function () {
    Route::post('/upload', [UploadController::class, 'store']);
});
```

### Controller-də manual rate limiting

```php
class VerificationController extends Controller
{
    public function sendCode(Request $request)
    {
        $key = 'verify-code:' . $request->user()->id;

        // Manual yoxlama
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'error' => "Çox sayda cəhd. {$seconds} saniyə sonra yenidən cəhd edin.",
                'retry_after' => $seconds,
            ], 429);
        }

        // Cəhd sayğacını artırmaq
        RateLimiter::hit($key, 60 * 5); // 5 dəqiqə sonra sıfırlanır

        // SMS göndərmək
        $request->user()->sendVerificationCode();

        return response()->json([
            'message' => 'Təsdiq kodu göndərildi.',
            'remaining_attempts' => RateLimiter::remaining($key, 3),
        ]);
    }

    // Sayğacı sıfırlamaq
    public function verifyCode(Request $request)
    {
        if ($this->isValidCode($request->code)) {
            RateLimiter::clear('verify-code:' . $request->user()->id);
            return response()->json(['message' => 'Təsdiqləndi']);
        }

        return response()->json(['error' => 'Yanlış kod'], 422);
    }
}
```

### Rate Limit Response Header-ları

Laravel avtomatik olaraq rate limit header-ları əlavə edir:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 57
Retry-After: 30  (yalnız limit aşıldıqda)
```

### Custom response

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)
        ->by($request->user()?->id ?: $request->ip())
        ->response(function (Request $request, array $headers) {
            return response()->json([
                'message' => 'Sorğu limiti aşıldı.',
                'limit' => $headers['X-RateLimit-Limit'] ?? null,
                'retry_after' => $headers['Retry-After'] ?? null,
            ], 429);
        });
});
```

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Daxili dəstək | Yoxdur - üçüncü tərəf lazım | Tam daxili dəstək |
| Konfiqurasiya | Java kodu / filter / aspect | `configureRateLimiting()` metodu |
| Middleware | Custom filter yazmaq lazım | `throttle:60,1` bir söz ilə |
| Alqoritm | Token bucket (Bucket4j) | Fixed window (daxili) |
| Redis dəstəyi | Bucket4j-redis əlavə kitabxana | Laravel Cache driver vasitəsilə |
| Per-user limiting | Manual implementasiya | `->by($request->user()->id)` |
| Per-IP limiting | Manual implementasiya | `->by($request->ip())` |
| Bir neçə limit | Manual implementasiya | Array qaytarmaq kifayətdir |
| Response header-lar | Manual əlavə etmək | Avtomatik |
| Fallback response | Manual | `->response()` metodu |
| Code səviyyəsində limit | Custom annotation + AOP | `RateLimiter::hit()` / `tooManyAttempts()` |
| Kod miqdarı | ~50-100 sətir minimum | ~5-10 sətir |

## Niyə belə fərqlər var?

### Spring-in modular yanaşması

Spring Framework "minimal core, genişlənən ekosistem" fəlsəfəsinə əsaslanır. Rate limiting kimi xüsusiyyətlər framework-ün əsas vəzifəsi hesab edilmir. Spring düşünür ki, hər layihənin rate limiting ehtiyacları fərqlidir - bəzisinə token bucket lazımdır, bəzisinə sliding window, bəzisinə leaky bucket. Buna görə seçimi developer-ə buraxır.

Əslində real production mühitlərində rate limiting çox vaxt API Gateway səviyyəsində (Spring Cloud Gateway, Kong, Nginx) həyata keçirilir, application səviyyəsində deyil. Bu da Spring-in niyə bu xüsusiyyəti daxil etmədiyini izah edir.

### Laravel-in "batteries included" yanaşması

Laravel hesab edir ki, rate limiting hər web tətbiq üçün lazımlı əsas xüsusiyyətdir. API-lər, login formları, SMS göndərmə - hamısında rate limiting lazımdır. Buna görə sadə, lakin effektiv bir həll daxil edilib. `throttle:60,1` yazmaq kifayətdir - heç bir əlavə paket, konfiqurasiya və ya filter yazmaq lazım deyil.

### Alqoritm fərqi

Bucket4j-nin token bucket alqoritmi daha çevik və "burst" sorğulara imkan verir - əgər az istifadə edirsinizsə, tokenlar yığılır. Laravel-in fixed window yanaşması daha sadədir - dəqiqəyə 60 sorğu deməkdir dəqiq 60 sorğu, nə az, nə çox. Lakin Laravel-in cache əsaslı yanaşması distributed mühitlərdə Redis ilə asanlıqla işləyir.

## Hansı framework-də var, hansında yoxdur?

### Yalnız Laravel-də olan xüsusiyyətlər:
- **Daxili `throttle` middleware** - bir söz ilə rate limiting
- **`RateLimiter::for()`** - named rate limiter-lər
- **`Limit::perMinute()`, `perHour()`, `perDay()`** - vaxt əsaslı limitlər
- **`Limit::none()`** - whitelist üçün limit yoxdur
- **Avtomatik response header-lar** - X-RateLimit-Limit, X-RateLimit-Remaining
- **`RateLimiter::hit()` / `tooManyAttempts()`** - controller daxilində manual limit
- **Array ilə çoxsəviyyəli limitlər** - dəqiqə + saat + gün limiti birlikdə
- **`->response()` callback** - custom 429 cavabı

### Yalnız Spring-də olan xüsusiyyətlər:
- **Token bucket alqoritmi** (Bucket4j) - burst sorğulara imkan
- **Resilience4j inteqrasiyası** - rate limiting + circuit breaker + retry birlikdə
- **Custom annotation + AOP** - metod səviyyəsində dekorativ rate limiting
- **Spring Cloud Gateway rate limiting** - API Gateway səviyyəsində limit
- **`Refill.greedy()` vs `Refill.intervally()`** - token yenilənmə strategiyası seçimi
- **Bandwidth.classic() vs Bandwidth.simple()** - müxtəlif bandwidth konfiqurasiyaları
