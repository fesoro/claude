# AOP (Aspekt-Yonumlu Proqramlasdirma)

## Giris

Aspekt-Yonumlu Proqramlasdirma (Aspect-Oriented Programming, AOP) proqramdaki "cross-cutting concerns" - yeni butun tetbiq boyunca tekrarlanan meseleler (logging, tehlukesizlik, tranzaksiya, cache ve s.) ile mubarizae ucun yaranmis bir paradiqmadir. Spring AOP-ni birinci sinif vatendas olaraq destekleyir, Laravel-de ise AOP yoxdur, amma eyni meseleler middleware, event listener ve decorator pattern ile hell olunur.

## Spring-de istifadesi

### AOP terminologiyasi

- **Aspect** - Cross-cutting concern-un modullasdirilmesi (meselen, logging aspekti)
- **Advice** - Aspektin ne vaxt ve ne edeceyini bildirir (@Before, @After, @Around)
- **Pointcut** - Aspektin hansi metodlara tetbiq olunacagini mueyyen edir
- **Join Point** - Aspektin tetbiq olundugu konkret noqtae (metod cagrisi)
- **Weaving** - Aspektin esas koda daxil edilmesi prosesi

### Asililiq

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-aop</artifactId>
</dependency>
```

### Logging Aspekti

```java
@Aspect
@Component
public class LoggingAspect {

    private static final Logger log = LoggerFactory.getLogger(LoggingAspect.class);

    // Butun service siniflerinin butun metodlari
    @Before("execution(* com.example.service.*.*(..))")
    public void logBefore(JoinPoint joinPoint) {
        String methodName = joinPoint.getSignature().getName();
        Object[] args = joinPoint.getArgs();
        log.info("Metod cagirilir: {}({})", methodName, Arrays.toString(args));
    }

    // Metod ugurla bitdikde
    @AfterReturning(
        pointcut = "execution(* com.example.service.*.*(..))",
        returning = "result"
    )
    public void logAfterReturning(JoinPoint joinPoint, Object result) {
        log.info("Metod bitdi: {} -> Netice: {}",
            joinPoint.getSignature().getName(), result);
    }

    // Metod xeta ile bitdikde
    @AfterThrowing(
        pointcut = "execution(* com.example.service.*.*(..))",
        throwing = "ex"
    )
    public void logAfterThrowing(JoinPoint joinPoint, Exception ex) {
        log.error("Metod xeta verdi: {} -> {}",
            joinPoint.getSignature().getName(), ex.getMessage());
    }
}
```

### @Around Advice (en guclu)

```java
@Aspect
@Component
public class PerformanceAspect {

    private static final Logger log = LoggerFactory.getLogger(PerformanceAspect.class);

    @Around("execution(* com.example.service.*.*(..))")
    public Object measureExecutionTime(ProceedingJoinPoint joinPoint)
            throws Throwable {

        long start = System.currentTimeMillis();

        try {
            Object result = joinPoint.proceed(); // Orijinal metodu cagir
            return result;
        } finally {
            long duration = System.currentTimeMillis() - start;
            log.info("{}.{}() - {} ms",
                joinPoint.getTarget().getClass().getSimpleName(),
                joinPoint.getSignature().getName(),
                duration);
        }
    }
}
```

### Xususi annotasiya ile Pointcut

```java
// Xususi annotasiya yaradiriq
@Target(ElementType.METHOD)
@Retention(RetentionPolicy.RUNTIME)
public @interface Cacheable {
    String key() default "";
    int ttlSeconds() default 300;
}

@Target(ElementType.METHOD)
@Retention(RetentionPolicy.RUNTIME)
public @interface RateLimit {
    int maxRequests() default 100;
    int periodSeconds() default 60;
}
```

```java
@Aspect
@Component
public class CacheAspect {

    private final Map<String, CacheEntry> cache = new ConcurrentHashMap<>();

    @Around("@annotation(cacheable)")
    public Object handleCache(ProceedingJoinPoint joinPoint,
                               Cacheable cacheable) throws Throwable {

        String key = cacheable.key().isEmpty()
            ? generateKey(joinPoint)
            : cacheable.key();

        CacheEntry entry = cache.get(key);
        if (entry != null && !entry.isExpired()) {
            return entry.getValue();
        }

        Object result = joinPoint.proceed();
        cache.put(key, new CacheEntry(result, cacheable.ttlSeconds()));
        return result;
    }

    private String generateKey(ProceedingJoinPoint joinPoint) {
        return joinPoint.getSignature().toShortString()
            + Arrays.toString(joinPoint.getArgs());
    }
}
```

```java
// Istifadesi - metoda annotasiya elave etmek kifayetdir
@Service
public class ProductService {

    @Cacheable(key = "products-all", ttlSeconds = 600)
    public List<Product> getAllProducts() {
        // Agir DB sorgusu - neticesi cache-lenir
        return productRepository.findAll();
    }

    @RateLimit(maxRequests = 10, periodSeconds = 60)
    public void sendNotification(String userId, String message) {
        // Rate limit tetbiq olunur
    }
}
```

### Muxtlif Pointcut ifadeleri

```java
@Aspect
@Component
public class AdvancedPointcuts {

    // Mueyyen paketdeki butun metodlar
    @Pointcut("execution(* com.example.service..*.*(..))")
    public void serviceLayer() {}

    // Mueyyen annotasiyali sinifler
    @Pointcut("within(@org.springframework.stereotype.Service *)")
    public void serviceClasses() {}

    // Mueyyen annotasiyali metodlar
    @Pointcut("@annotation(com.example.annotation.Auditable)")
    public void auditableMethods() {}

    // Pointcut-larin birlesdirilmesi
    @Before("serviceLayer() && !auditableMethods()")
    public void beforeNonAuditableServiceMethods(JoinPoint joinPoint) {
        // Service metodlarinda, amma @Auditable olmayanlarda isleyir
    }

    // Mueyyen parametr tipli metodlar
    @Before("execution(* com.example.service.*.*(Long, ..))")
    public void methodsWithLongFirstParam(JoinPoint joinPoint) {
        // Ilk parametri Long olan service metodlarinda isleyir
    }
}
```

### Audit (Tetkiq) Aspekti

```java
@Aspect
@Component
public class AuditAspect {

    private final AuditLogRepository auditLogRepository;

    public AuditAspect(AuditLogRepository auditLogRepository) {
        this.auditLogRepository = auditLogRepository;
    }

    @Around("@annotation(auditable)")
    public Object audit(ProceedingJoinPoint joinPoint,
                        Auditable auditable) throws Throwable {

        String username = SecurityContextHolder.getContext()
            .getAuthentication().getName();
        String action = auditable.action();
        String method = joinPoint.getSignature().toShortString();

        AuditLog log = new AuditLog();
        log.setUsername(username);
        log.setAction(action);
        log.setMethod(method);
        log.setArgs(Arrays.toString(joinPoint.getArgs()));
        log.setTimestamp(Instant.now());

        try {
            Object result = joinPoint.proceed();
            log.setStatus("SUCCESS");
            return result;
        } catch (Exception ex) {
            log.setStatus("FAILURE");
            log.setErrorMessage(ex.getMessage());
            throw ex;
        } finally {
            auditLogRepository.save(log);
        }
    }
}

// Istifadesi
@Service
public class UserService {

    @Auditable(action = "USER_DELETE")
    public void deleteUser(Long userId) {
        userRepository.deleteById(userId);
    }

    @Auditable(action = "USER_UPDATE")
    public User updateUser(Long userId, UserDto dto) {
        // ...
    }
}
```

## Laravel-de istifadesi

Laravel-de AOP yoxdur, amma eyni meseleler basqa yollarla hell olunur:

### Middleware (en yaxin ekvivalent)

```php
// Logging middleware - @Around advice-e benzer
class LogRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        Log::info('Sorgu basladi', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
        ]);

        $response = $next($request); // Orijinal kodu islet (proceed() kimi)

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::info('Sorgu bitdi', [
            'status' => $response->getStatusCode(),
            'duration' => $duration . 'ms',
        ]);

        return $response;
    }
}
```

```php
// Rate limit middleware
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60)
    {
        $key = $request->ip() . ':' . $request->path();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            abort(429, 'Cox sayda sorgu. Zehmet olmasa gozleyin.');
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
```

### Event Listener sistemi

```php
// Hadise yaratmaq
class UserDeleted
{
    public function __construct(
        public User $user,
        public string $deletedBy
    ) {}
}

// Dinleyici - audit ucun
class AuditUserDeletion
{
    public function handle(UserDeleted $event): void
    {
        AuditLog::create([
            'action' => 'USER_DELETE',
            'user_id' => $event->user->id,
            'performed_by' => $event->deletedBy,
            'timestamp' => now(),
        ]);
    }
}

// Service-de istifade
class UserService
{
    public function deleteUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->delete();

        event(new UserDeleted($user, auth()->user()->name));
    }
}
```

### Model Observer-ler

```php
// php artisan make:observer UserObserver --model=User

class UserObserver
{
    public function creating(User $user): void
    {
        Log::info('Yeni istifadeci yaradilir', ['email' => $user->email]);
    }

    public function created(User $user): void
    {
        Log::info('Istifadeci yaradildi', ['id' => $user->id]);
        // E-poct gonder, cache yenile ve s.
    }

    public function updated(User $user): void
    {
        $changes = $user->getChanges();
        Log::info('Istifadeci yenilendi', [
            'id' => $user->id,
            'changes' => $changes,
        ]);
    }

    public function deleted(User $user): void
    {
        Log::info('Istifadeci silindi', ['id' => $user->id]);
        AuditLog::create([
            'action' => 'USER_DELETED',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }
}

// Qeydiyyat
// AppServiceProvider-da:
User::observe(UserObserver::class);
```

### Decorator pattern ile service wrapping

```php
// Interface
interface PaymentGateway
{
    public function charge(float $amount, string $token): PaymentResult;
}

// Esas implementasiya
class StripePaymentGateway implements PaymentGateway
{
    public function charge(float $amount, string $token): PaymentResult
    {
        // Stripe API cagrisi
        return new PaymentResult(true, 'ch_xxx');
    }
}

// Logging decorator
class LoggingPaymentGateway implements PaymentGateway
{
    public function __construct(
        private PaymentGateway $gateway
    ) {}

    public function charge(float $amount, string $token): PaymentResult
    {
        Log::info('Odenish baslayir', ['amount' => $amount]);

        $result = $this->gateway->charge($amount, $token);

        Log::info('Odenish neticesi', [
            'success' => $result->success,
            'id' => $result->transactionId,
        ]);

        return $result;
    }
}

// Retry decorator
class RetryingPaymentGateway implements PaymentGateway
{
    public function __construct(
        private PaymentGateway $gateway,
        private int $maxRetries = 3
    ) {}

    public function charge(float $amount, string $token): PaymentResult
    {
        $attempts = 0;
        while (true) {
            try {
                return $this->gateway->charge($amount, $token);
            } catch (\Exception $e) {
                $attempts++;
                if ($attempts >= $this->maxRetries) {
                    throw $e;
                }
                sleep(1);
            }
        }
    }
}

// Service container-da birlesdirmek
// AppServiceProvider
$this->app->bind(PaymentGateway::class, function ($app) {
    $stripe = new StripePaymentGateway();
    $logging = new LoggingPaymentGateway($stripe);
    return new RetryingPaymentGateway($logging);
});
```

### PHP Attributes ile manual AOP benzeri sistem

```php
// PHP 8 Attribute yaratmaq
#[Attribute(Attribute::TARGET_METHOD)]
class Cacheable
{
    public function __construct(
        public string $key = '',
        public int $ttl = 300
    ) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class Loggable {}

// Service sinifinde istifade
class ProductService
{
    #[Cacheable(key: 'products.all', ttl: 600)]
    #[Loggable]
    public function getAllProducts(): Collection
    {
        return Product::all();
    }
}

// Amma bu attribute-lar ozleri hec ne etmir!
// Onlari oxuyub islemek ucun ayri kod yazmaq lazimdir.
// Bu, Spring AOP-nin avtomatik weaving-inden ferqli olaraq,
// Laravel-de manual implementasiya teleb edir.
```

## Esas ferqler

| Xususiyyet | Spring AOP | Laravel alternativi |
|---|---|---|
| **Cross-cutting concerns** | `@Aspect` sinfi | Middleware, Observer, Event |
| **Metod intercept** | `@Before/@After/@Around` | Middleware `handle()` |
| **Pointcut** | Expression ile mueyyen etmek | Route/Middleware qrupu ile |
| **Annotasiya ile tetiklemek** | `@annotation(...)` pointcut | PHP Attribute (manual isletmek) |
| **Model lifecycle** | Yoxdur (ayri concern) | Model Observer |
| **Audit** | `@Around` + xususi annotasiya | Event + Listener |
| **Caching** | `@Cacheable` (Spring Cache) | `Cache::remember()` |
| **Transaction** | `@Transactional` (AOP ile) | `DB::transaction()` |
| **Logging** | Aspect ile avtomatik | Middleware ve ya manual |
| **Proxy mexanizmi** | JDK Dynamic Proxy / CGLIB | Yoxdur |

## Niye bele ferqler var?

**Spring niye AOP-a ehtiyac duyur?**

Java statik tipli, compile edilen bir dildir. Kod yazildiqdan sonra runtime-da deyisemek cetindir. AOP bu meseleye "proxy" mexanizmi ile cavab verir - Spring sizin sinfinizin etrafinda avtomatik proxy yaradir ve metod cagrilarini intercept edir. Bu, kodu deyismeden yeni davranis elave etmek imkanini verir.

Bundan elave, Java-da "convention" yanasmasi zeifdir - her sey aciq sekilde bildirilmelidir. `@Transactional` annotasiyasi AOP vasitesile isleyir: Spring proxy yaradir, metod cagrilandan evvel tranzaksiya acir, bitdikden sonra commit edir. Bu olmadan her metoda manual `try-catch-commit-rollback` kodu yazmaq lazim olardi.

**Laravel niye AOP-suz kecinir?**

PHP dinamik dildir ve runtime-da sinif davranisini asanliqla deyismek mumkundur. Bundan elave:

1. **Middleware** - HTTP sorgu/cavab dongusu boyunca cross-cutting concerns-u hell edir (logging, auth, rate limit).
2. **Event/Listener** - Hadise esasli ayirma ile audit, bildiris kimi meseleler hell olunur.
3. **Model Observer** - Eloquent model-lerin lifecycle hadiseleri (creating, updated, deleted) avtomatik izlenir.
4. **Decorator pattern** - Interface esasli wrapping ile logging, retry, caching elave olunur.

Laravel-in felsefesi "sadece lazim olduqda murakkeblesdir" prinsipidir. AOP guclu, amma murakkeb bir aletdir. Laravel-in teklif etdiyi alternativler ekser hallarda kifayet edir ve daha sadedir.

**Teknik sebebler:** PHP-nin request lifecycle-i qisadir (her sorgu ucun proses baslayir ve bitir), buna gore uzunmuddeitli proxy obyektlerin idarae olunmasi Java qeder vacib deyil. Java-da ise JVM daimi isleyir, proxy-ler bir defe yaranir ve butun tetbiq boyunca istifade olunur.

## Hansi framework-de var, hansinda yoxdur?

- **`@Aspect`, `@Before`, `@After`, `@Around`** - Yalniz Spring-de. Laravel-de bele mexanizm yoxdur.
- **Pointcut expression language** - Yalniz Spring-de. Hansi metodlara aspektin tetbiq olunacagini deqiq mueyyen etmek mumkundur.
- **CGLIB/JDK Proxy** - Yalniz Spring-de. Avtomatik proxy yaratma mexanizmi.
- **Model Observer** - Yalniz Laravel-de. Spring-de Hibernate lifecycle callback-lar (`@PrePersist`, `@PostUpdate`) var, amma observer pattern deyil.
- **Middleware chain** - Laravel-de daha guclu ve elastikdir. Spring-de `HandlerInterceptor` var, amma middleware qeder intuitiv deyil.
- **`@Cacheable`, `@Transactional`** - Spring-de bu annotasiyalar AOP vasitesile isleyir. Laravel-de eyni seyler manual metod cagirilari ile edilir (`Cache::remember()`, `DB::transaction()`), amma daha az "sehrli"dir.
- **PHP Attributes** - PHP 8-den sonra annotasiya benzeri mexanizm var, amma Spring-in annotasiyalarindan ferqli olaraq, avtomatik islemir - manual kod yazmaq lazimdir.
