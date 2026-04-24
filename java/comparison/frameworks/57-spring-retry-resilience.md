# Spring Retry & Resilience vs Laravel Retry — Dərin Müqayisə

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Distribuited sistemlərdə xətalar qaçılmazdır: şəbəkə qısa müddət düşür, 3rd-party API 503 qaytarır, DB deadlock olur. Bu cür **transient** (keçici) xətaları yenidən cəhd (retry) etmək — sistemi daha davamlı (resilient) edir. Amma retry öz-özlüyündə kifayət deyil: **backoff** (gözləmə artımı), **idempotency** (eyni sorğu iki dəfə gedəndə eyni nəticə), **circuit breaker** (sistem sıradan çıxsa daha cəhd etmə), **bulkhead** (paralel sorğu limiti) — hamısı lazımdır.

**Spring** bu sahədə iki güclü alətlə gəlir: **Spring Retry** (`@Retryable`, `@Recover`, `RetryTemplate`) və **Resilience4j** (`@Retry`, `@CircuitBreaker`, `@Bulkhead`, `@RateLimiter`, `@TimeLimiter`). **Laravel** isə daha sadə aləti: `retry()` helper, Job `$tries` + `$backoff`, `Http::retry()`, Queue worker flag-ları (`--tries`, `--backoff`, `--max-exceptions`). Circuit breaker default-da yoxdur — 3rd-party paket və ya custom middleware lazımdır.

Bu sənəddə Spring Retry policy-lərini (`SimpleRetryPolicy`, `ExceptionClassifierRetryPolicy`, `CircuitBreakerRetryPolicy`), backoff növlərini (fixed, exponential, random), `@Retryable` + `@Recover` pair-i, `RetryTemplate` programmatic API-ni, Resilience4j inteqrasiyasını, `@Transactional` ilə retry qarşılıqlı təsirini araşdırırıq. Laravel tərəfində `retry()` helper, Job retry konfiqurasiyası, `Http::retry()`, `ThrottlesExceptions` middleware, failed job idarəetməsi, idempotent order-creation nümunəsi göstəririk.

---

## Spring-də istifadəsi

### 1) Spring Retry qoşmaq

```xml
<dependency>
    <groupId>org.springframework.retry</groupId>
    <artifactId>spring-retry</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-aop</artifactId>
</dependency>
```

```java
@SpringBootApplication
@EnableRetry
public class App { }
```

### 2) @Retryable — sadə annotation

```java
@Service
public class PaymentService {

    @Retryable(
        retryFor = { IOException.class, TransientException.class },
        noRetryFor = { BusinessException.class },
        maxAttempts = 3,
        backoff = @Backoff(delay = 1000, multiplier = 2, maxDelay = 10_000, random = true)
    )
    public PaymentResult charge(Long orderId, BigDecimal amount) {
        return stripeClient.charge(orderId, amount);
    }

    @Recover
    public PaymentResult chargeFallback(Exception e, Long orderId, BigDecimal amount) {
        // Retry tükəndi — fallback
        log.error("Payment failed after retries for order {}", orderId, e);
        return PaymentResult.failed(e.getMessage());
    }
}
```

**`@Backoff` parametrləri:**
- `delay = 1000` — ilk retry-dan əvvəl 1s gözlə
- `multiplier = 2` — exponential: 1s, 2s, 4s, 8s, ...
- `maxDelay = 10_000` — maksimum 10s
- `random = true` — jitter (gözləməyə təsadüfi ±%30)

### 3) @Recover — fallback metod

`@Recover` metodu imza baxımından retry edilən metodla uyğun olmalıdır:

```java
@Retryable(retryFor = IOException.class, maxAttempts = 3)
public String fetchData(String url, int timeout) {
    return httpClient.get(url, timeout);
}

@Recover
public String fetchDataRecover(IOException e, String url, int timeout) {
    // 1-ci parametr — exception
    // Sonrakı parametrlər — orijinal metodla eyni
    return "fallback-data";
}

// Fərqli exception-lar üçün ayrıca @Recover
@Recover
public String fetchDataRecover(TimeoutException e, String url, int timeout) {
    return "timeout-fallback";
}
```

### 4) RetryTemplate — programmatic retry

Annotation əvəzinə obyekt kimi:

```java
@Configuration
public class RetryConfig {

    @Bean
    public RetryTemplate retryTemplate() {
        RetryTemplate template = new RetryTemplate();

        // Policy: hansı exception-larda retry edilsin
        SimpleRetryPolicy policy = new SimpleRetryPolicy(3,
            Map.of(IOException.class, true, BusinessException.class, false));
        template.setRetryPolicy(policy);

        // Backoff
        ExponentialBackOffPolicy backoff = new ExponentialBackOffPolicy();
        backoff.setInitialInterval(1000);
        backoff.setMultiplier(2);
        backoff.setMaxInterval(10_000);
        template.setBackOffPolicy(backoff);

        // Listener — hər cəhddə callback
        template.registerListener(new RetryListener() {
            @Override
            public <T, E extends Throwable> void onError(RetryContext ctx,
                                                         RetryCallback<T, E> cb,
                                                         Throwable e) {
                log.warn("Retry attempt {} failed", ctx.getRetryCount(), e);
            }
        });

        return template;
    }
}

@Service
public class PaymentService {

    private final RetryTemplate retry;

    public PaymentService(RetryTemplate r) { this.retry = r; }

    public PaymentResult charge(Order order) {
        return retry.execute(
            context -> stripeClient.charge(order),             // Retry callback
            context -> PaymentResult.failed("All retries exhausted")   // Recover
        );
    }
}
```

### 5) Retry policy-lər

**SimpleRetryPolicy** — sadə sayma:

```java
new SimpleRetryPolicy(3, Map.of(IOException.class, true));
```

**ExceptionClassifierRetryPolicy** — exception tipinə görə fərqli policy:

```java
ExceptionClassifierRetryPolicy policy = new ExceptionClassifierRetryPolicy();

Map<Class<? extends Throwable>, RetryPolicy> map = new HashMap<>();
map.put(IOException.class, new SimpleRetryPolicy(5));
map.put(SQLException.class, new SimpleRetryPolicy(3));
map.put(RuntimeException.class, new NeverRetryPolicy());

policy.setPolicyMap(map);
```

**CircuitBreakerRetryPolicy** — circuit breaker ilə birlikdə:

```java
CircuitBreakerRetryPolicy cb = new CircuitBreakerRetryPolicy(
    new SimpleRetryPolicy(3)
);
cb.setOpenTimeout(5000);      // 5s — circuit açıq qalacaq
cb.setResetTimeout(20_000);   // 20s — sonra yenidən cəhd
```

**TimeoutRetryPolicy** — vaxt limiti:

```java
TimeoutRetryPolicy policy = new TimeoutRetryPolicy();
policy.setTimeout(30_000);     // 30s — sonra retry dayandır
```

### 6) Backoff növləri

```java
// Fixed — hər retry arası sabit 2s
FixedBackOffPolicy fixedBackoff = new FixedBackOffPolicy();
fixedBackoff.setBackOffPeriod(2000);

// Exponential — 1s, 2s, 4s, 8s, ...
ExponentialBackOffPolicy exp = new ExponentialBackOffPolicy();
exp.setInitialInterval(1000);
exp.setMultiplier(2);
exp.setMaxInterval(30_000);

// Exponential + random jitter
ExponentialRandomBackOffPolicy expRandom = new ExponentialRandomBackOffPolicy();
expRandom.setInitialInterval(1000);
expRandom.setMultiplier(2);

// Uniform random
UniformRandomBackOffPolicy uniform = new UniformRandomBackOffPolicy();
uniform.setMinBackOffPeriod(500);
uniform.setMaxBackOffPeriod(3000);
```

### 7) Resilience4j — circuit breaker, bulkhead, time limiter

Resilience4j Spring Retry-ın üstündə daha zəngin pattern-lər gətirir.

```xml
<dependency>
    <groupId>io.github.resilience4j</groupId>
    <artifactId>resilience4j-spring-boot3</artifactId>
</dependency>
```

```yaml
resilience4j:
  retry:
    instances:
      stripe:
        max-attempts: 3
        wait-duration: 1s
        exponential-backoff-multiplier: 2
        retry-exceptions:
          - java.io.IOException

  circuitbreaker:
    instances:
      stripe:
        sliding-window-size: 20
        minimum-number-of-calls: 10
        failure-rate-threshold: 50        # 50% fail-dən sonra OPEN
        wait-duration-in-open-state: 30s
        permitted-number-of-calls-in-half-open-state: 3

  bulkhead:
    instances:
      stripe:
        max-concurrent-calls: 10          # eyni anda 10 call

  timelimiter:
    instances:
      stripe:
        timeout-duration: 5s
        cancel-running-future: true

  ratelimiter:
    instances:
      stripe:
        limit-for-period: 100
        limit-refresh-period: 1s
        timeout-duration: 500ms
```

İstifadə:

```java
@Service
public class StripeService {

    @Retry(name = "stripe", fallbackMethod = "chargeFallback")
    @CircuitBreaker(name = "stripe", fallbackMethod = "chargeFallback")
    @Bulkhead(name = "stripe")
    @TimeLimiter(name = "stripe")
    public CompletableFuture<ChargeResult> charge(Long orderId, BigDecimal amount) {
        return CompletableFuture.supplyAsync(() -> stripeClient.charge(orderId, amount));
    }

    public CompletableFuture<ChargeResult> chargeFallback(Long orderId, BigDecimal amount, Throwable e) {
        log.error("Stripe unavailable, fallback for order {}", orderId, e);
        return CompletableFuture.completedFuture(ChargeResult.pending());
    }
}
```

**Annotation sırası (vacib!):**
```
Retry ← CircuitBreaker ← RateLimiter ← TimeLimiter ← Bulkhead ← Method
```

Yəni: önsə Bulkhead (paralel limit), sonra TimeLimiter (vaxt), sonra RateLimiter, sonra CircuitBreaker (uğursuzluq nisbəti), ən xaricdə Retry.

### 8) Circuit breaker state-ləri

```
CLOSED    → Hər call icra olunur
             (əgər failure-rate-threshold keçsə → OPEN)

OPEN      → Heç bir call icra olunmur, dərhal fallback
             (wait-duration sonrası → HALF_OPEN)

HALF_OPEN → Kiçik test call-lar (permitted-number-of-calls...)
             (uğurlu → CLOSED, uğursuz → OPEN)
```

Metrics:

```java
@Autowired
CircuitBreakerRegistry registry;

CircuitBreaker cb = registry.circuitBreaker("stripe");
cb.getState();                        // CLOSED / OPEN / HALF_OPEN
cb.getMetrics().getFailureRate();
cb.getMetrics().getNumberOfFailedCalls();
```

### 9) @Retryable + @Transactional — diqqət

Retry ilə transaction iç-içə istifadə edilsə, sıra önəmlidir:

**Səhv:** `@Transactional` xarici, `@Retryable` daxili — bütün retry-lar **eyni transaction-da** işləyir. DB deadlock ilə retry etmək mənasızdır — eyni tx yenidən fail olur.

**Düzgün:** `@Retryable` xarici, `@Transactional` daxili — hər retry **yeni transaction** başladır.

```java
// YANLIŞ — outer tx
@Service
public class OrderService {

    @Transactional
    public void processOrder(Long id) {
        retryableHelper.save(id);        // daxili @Retryable
    }
}

// DÜZGÜN — outer retry
@Service
public class OrderService {

    @Retryable(retryFor = DeadlockLoserDataAccessException.class, maxAttempts = 3)
    public void processOrder(Long id) {
        retryableHelper.saveInNewTx(id);  // daxili @Transactional
    }
}

@Service
public class RetryableHelper {

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void saveInNewTx(Long id) {
        repository.save(...);
    }
}
```

### 10) Idempotency açar ilə retry — tam nümunə

```java
public record CreateOrderCommand(
    String idempotencyKey,
    Long userId,
    List<OrderItem> items
) { }

@Service
public class IdempotentOrderService {

    private final OrderRepository orders;
    private final IdempotencyRepository keys;

    @Retryable(
        retryFor = { SQLTransientConnectionException.class, DeadlockLoserDataAccessException.class },
        maxAttempts = 3,
        backoff = @Backoff(delay = 200, multiplier = 2)
    )
    public Order createOrder(CreateOrderCommand cmd) {
        // 1. Idempotency key yoxla
        Optional<IdempotencyRecord> existing = keys.findByKey(cmd.idempotencyKey());
        if (existing.isPresent()) {
            return orders.findById(existing.get().getOrderId()).orElseThrow();
        }

        // 2. Yeni order yarat (yeni tx-də)
        return createInTx(cmd);
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    protected Order createInTx(CreateOrderCommand cmd) {
        Order order = new Order(cmd.userId(), cmd.items());
        orders.save(order);

        keys.save(new IdempotencyRecord(
            cmd.idempotencyKey(),
            order.getId(),
            Instant.now().plus(Duration.ofDays(1))      // TTL
        ));

        return order;
    }

    @Recover
    public Order createOrderRecover(Exception e, CreateOrderCommand cmd) {
        throw new OrderCreationFailed("Could not create order: " + cmd.idempotencyKey(), e);
    }
}
```

### 11) application.yml tam retry setup

```yaml
resilience4j:
  retry:
    configs:
      default:
        max-attempts: 3
        wait-duration: 500ms
        exponential-backoff-multiplier: 2
        enable-exponential-backoff: true
        enable-randomized-wait: true
    instances:
      stripe:
        base-config: default
        max-attempts: 5
      inventory:
        base-config: default
        max-attempts: 2

  circuitbreaker:
    configs:
      default:
        register-health-indicator: true
        sliding-window-type: COUNT_BASED
        sliding-window-size: 20
        minimum-number-of-calls: 10
        failure-rate-threshold: 50
        wait-duration-in-open-state: 30s
        automatic-transition-from-open-to-half-open-enabled: true
    instances:
      stripe:
        base-config: default
```

---

## Laravel-də istifadəsi

### 1) retry() helper — sadə hal

```php
use Illuminate\Support\Facades\Log;

// 3 cəhd, 100ms gözlə
$result = retry(3, function () {
    return Http::get('https://api.example.com/data')->throw()->json();
}, 100);

// Exponential backoff — array kimi verirsən
$result = retry([100, 500, 2000], function () {
    return Http::get(...)->throw()->json();
});

// Yalnız müəyyən exception-larda retry
$result = retry(5, fn () => doWork(), 200, function ($e) {
    // true qaytarsa — retry et
    return $e instanceof \Illuminate\Http\Client\ConnectionException;
});
```

### 2) HTTP client retry

Laravel built-in HTTP client (`Illuminate\Http\Client`) retry dəstəkləyir:

```php
use Illuminate\Http\Client\Response;

$response = Http::retry(3, 100, function ($e, $request) {
    // Yalnız 5xx və ya connection xətası
    return $e instanceof \Illuminate\Http\Client\ConnectionException
        || ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->serverError());
})
->timeout(10)
->withToken(config('services.stripe.secret'))
->post('https://api.stripe.com/v1/charges', [
    'amount' => $amountCents,
    'currency' => 'usd',
    'source' => $token,
])
->throw();
```

Exponential backoff üçün callback:

```php
Http::retry(3, function (int $attempt, \Throwable $exception) {
    // ms olaraq gözləmə qaytar
    return $attempt * 1000;    // 1s, 2s, 3s
}, throw: true)
->get('https://api.example.com');
```

### 3) Queue Job retry

Job class-da property-lərlə:

```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChargeOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;                       // maksimum cəhd sayı
    public int $maxExceptions = 3;               // fərqli exception limit
    public int $timeout = 30;                    // saniyə
    public bool $failOnTimeout = true;

    // Exponential backoff — array verilir
    public array $backoff = [10, 30, 60, 120, 300];

    // Və ya dinamik
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    // Vaxt əsaslı retry — bu vaxta qədər cəhd et
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function __construct(public readonly Order $order) {}

    public function handle(): void
    {
        // Əgər exception atarsa — Laravel worker avtomatik retry edir
        $charge = Stripe::charge($this->order);
        $this->order->update(['charge_id' => $charge->id]);
    }

    public function failed(\Throwable $e): void
    {
        // $tries bitdi, daha retry olmayacaq
        $this->order->update(['status' => 'payment_failed']);
        Mail::to($this->order->user)->send(new PaymentFailed($this->order, $e));
    }
}
```

### 4) Queue worker flag-ları

```bash
# Global default
php artisan queue:work --tries=3 --backoff=10 --timeout=60 --max-exceptions=3

# Job-da property qeyd olunubsa, worker flag override etmir
```

Horizon supervisor-da:

```php
// config/horizon.php
'production' => [
    'payments-supervisor' => [
        'connection' => 'redis',
        'queue' => ['payments'],
        'balance' => 'auto',
        'maxProcesses' => 10,
        'tries' => 3,
        'timeout' => 30,
        'maxTime' => 3600,
        'maxJobs' => 1000,
    ],
],
```

### 5) ThrottlesExceptions middleware

`ThrottlesExceptions` — müəyyən sayda exception-dan sonra job-u pause edir. Spring circuit breaker-ə bənzər, amma job səviyyəsində.

```php
use Illuminate\Queue\Middleware\ThrottlesExceptions;

class ChargeOrder implements ShouldQueue
{
    public int $tries = 10;

    public function middleware(): array
    {
        return [
            // 10 exception 5 dəqiqə ərzində olarsa — job-u 10 dəqiqə pause et
            (new ThrottlesExceptions(10, 5 * 60))->backoff(5),
        ];
    }
}
```

Spesifik key (Redis lock):

```php
(new ThrottlesExceptions(10, 5 * 60))
    ->by('stripe-api')           // bütün Stripe API job-ları bir qrupda
    ->backoff(5);
```

### 6) RateLimited middleware

```php
// AppServiceProvider::boot
RateLimiter::for('stripe-api', function () {
    return Limit::perMinute(100);
});

// Job
public function middleware(): array
{
    return [new RateLimited('stripe-api')];
}
```

### 7) WithoutOverlapping middleware

Eyni key-li job-lar paralel işləməsin — lightweight bulkhead:

```php
use Illuminate\Queue\Middleware\WithoutOverlapping;

public function middleware(): array
{
    return [
        new WithoutOverlapping($this->order->user_id)
            ->expireAfter(120)
            ->releaseAfter(30),
    ];
}
```

### 8) Failed jobs

```bash
php artisan queue:failed                   # Uğursuz job-ları göstər
php artisan queue:retry all                # Hamısını yenidən cəhd et
php artisan queue:retry {id}               # Konkret id
php artisan queue:forget {id}              # Sil
php artisan queue:flush                    # Bütün failed job-ları sil
```

Laravel 11+ `FAILED_JOBS_DRIVER=database-uuids` və ya `dynamodb`.

### 9) Idempotent order creation

```php
namespace App\Services;

use App\Models\Order;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;

class IdempotentOrderService
{
    public function create(string $idempotencyKey, array $data): Order
    {
        // 1. Yoxla — artıq varsa həmin order-u qaytar
        $existing = IdempotencyKey::where('key', $idempotencyKey)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return Order::findOrFail($existing->order_id);
        }

        // 2. Retry-safe yarat — tx + unique constraint
        return DB::transaction(function () use ($idempotencyKey, $data) {
            $order = Order::create($data);

            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'order_id' => $order->id,
                'expires_at' => now()->addDay(),
            ]);

            return $order;
        });
    }
}

// İstifadə — retry ilə
use function retry;

$order = retry(3, function () use ($key, $data) {
    return app(IdempotentOrderService::class)->create($key, $data);
}, 200, fn ($e) => $e instanceof \PDOException);
```

### 10) Laravel-də circuit breaker

Default-da yoxdur. Variant-lar:

**1. `ThrottlesExceptions` + custom state** — yuxarıda göstərilib. Sadə hal üçün kifayətdir.

**2. `spatie/laravel-failed-job-monitor`** — failed job-ları izlə.

**3. Custom middleware:**

```php
namespace App\Queue\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public function __construct(
        private readonly string $key,
        private readonly int $failureThreshold = 5,
        private readonly int $openDurationSeconds = 60,
    ) {}

    public function handle(object $job, Closure $next): void
    {
        if ($this->isOpen()) {
            $job->release(30);       // 30s sonra yenidən
            return;
        }

        try {
            $next($job);
            $this->reset();
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        $failures = (int) Cache::get("cb:{$this->key}:failures", 0);
        $openUntil = Cache::get("cb:{$this->key}:open_until");

        if ($openUntil && now()->lt($openUntil)) {
            return true;
        }

        return $failures >= $this->failureThreshold;
    }

    private function recordFailure(): void
    {
        $failures = Cache::increment("cb:{$this->key}:failures");

        if ($failures >= $this->failureThreshold) {
            Cache::put("cb:{$this->key}:open_until", now()->addSeconds($this->openDurationSeconds));
        }
    }

    private function reset(): void
    {
        Cache::forget("cb:{$this->key}:failures");
        Cache::forget("cb:{$this->key}:open_until");
    }
}

// İstifadə
public function middleware(): array
{
    return [new CircuitBreaker('stripe', failureThreshold: 10, openDurationSeconds: 60)];
}
```

### 11) Tam nümunə — ChargeOrder job

```php
namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ChargeOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $maxExceptions = 3;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public readonly Order $order) {}

    public function handle(): void
    {
        // Idempotency — eyni order iki dəfə charge olunmasın
        if ($this->order->charge_id) {
            return;
        }

        $idempotencyKey = 'order:' . $this->order->id . ':charge';

        $response = Http::retry(3, 200, function ($e) {
            return $e instanceof \Illuminate\Http\Client\ConnectionException;
        })
        ->withToken(config('services.stripe.secret'))
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->timeout(10)
        ->post('https://api.stripe.com/v1/charges', [
            'amount' => (int) ($this->order->total * 100),
            'currency' => 'usd',
            'source' => $this->order->payment_token,
        ])
        ->throw();

        $this->order->update([
            'charge_id' => $response->json('id'),
            'status' => 'paid',
        ]);
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping('order-' . $this->order->id),
            (new ThrottlesExceptions(10, 5 * 60))->backoff(5),
        ];
    }

    public function failed(\Throwable $e): void
    {
        $this->order->update(['status' => 'payment_failed']);
        event(new \App\Events\OrderPaymentFailed($this->order, $e));
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }
}
```

### 12) config/queue.php retry_after

```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,            // job-un timeout-undan BÖYÜK olmalıdır
    'block_for' => 5,
],
```

Qayda: `retry_after > $timeout`. Əks halda Laravel worker-in işləyən job-unu "ölmüş" hesab edib yenidən başlayar.

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Annotation | `@Retryable`, `@Recover` | Yox — helper və property |
| Helper | `RetryTemplate` | `retry()` |
| HTTP retry | RestTemplate + RetryTemplate | `Http::retry(3, 100)` |
| Backoff | `@Backoff(delay, multiplier, maxDelay, random)` | Array `[10, 30, 60]` |
| Fallback | `@Recover` metod | Job `failed()` metod |
| Exception filter | `retryFor`, `noRetryFor` | Closure argument |
| Circuit breaker | Resilience4j `@CircuitBreaker` | `ThrottlesExceptions` + custom |
| Bulkhead | Resilience4j `@Bulkhead` | `WithoutOverlapping` middleware |
| Rate limiter | Resilience4j `@RateLimiter` | `RateLimited` middleware |
| Time limiter | Resilience4j `@TimeLimiter` | Job `$timeout` property |
| Retry + TX | Diqqət: `@Retryable` xarici | `DB::transaction` içərisində yenidən cəhd |
| Failed storage | Queue broker DLQ | `failed_jobs` cədvəli |
| Retry until | `TimeoutRetryPolicy` | `retryUntil()` metod |
| Max exceptions | Əllə implement | `$maxExceptions` property |
| Job-based async retry | Yox (thread model) | Built-in — worker retry-lar |
| Queue worker retry | Yox | `--tries`, `--backoff` flag |

---

## Niyə belə fərqlər var?

**Annotation gücü.** Spring AOP annotation-əsaslı retry təklif edir — koda əlavə `if`/`try` yazmağa ehtiyac yoxdur. Java tip sistemi + proxy retry-i şəffaf edir. PHP-də annotation-lar zəif, runtime-da reflection lazımdır — Laravel closure/property yolunu seçib.

**Synchronous vs queue-based retry.** Spring Retry eyni thread-də sinxron işləyir — HTTP call-da blocking retry. Laravel-də retry əsasən queue worker-də (async) olur — job fail edirsə, worker yenidən cəhd edir. Sinxron retry üçün `retry()` helper var, amma əsas yer queue-dadır.

**Circuit breaker.** Spring üçün Resilience4j first-class paketdir, Spring Boot Starter var. Laravel-də built-in yoxdur — çünki çoxlu request PHP-FPM model-ində hər biri ayrıca proses, cross-request state Redis-də saxlanmalıdır. `ThrottlesExceptions` job-level circuit deməkdir, HTTP request-level circuit isə custom və ya paket lazımdır.

**Bulkhead.** Java thread model-ində bulkhead təbii konsept: eyni resource-a eyni anda N thread-dən çox girməsin. PHP-də PHP-FPM hər request yeni prosesdir — OS səviyyəsində `pm.max_children` bulkhead-dir. Laravel-də job-level `WithoutOverlapping` bənzər funksionallıq verir.

**Idempotency.** Hər iki sistemdə retry iki tərəfi tələb edir: client side (retry) + server side (idempotency key). Stripe-ın `Idempotency-Key` header pattern-i universaldır. Spring və Laravel bunu eyni şəkildə implement edə bilir — key DB-də saxlanır, gəlsə eyni cavab qaytarılır.

**Transaction + retry.** Spring-də `@Transactional` + `@Retryable` qarşılıqlı təsiri güclü abstraction tələb edir. Outer retry + REQUIRES_NEW inner tx yeganə düzgün yoldur. Laravel-də `DB::transaction(fn () => ...)` closure daxilindədir, retry edirsənsə xaricində yerləşdirmək aydındır.

**Worker-level retry.** Laravel-in `--tries` + `--backoff` worker flag-ları job-u yaddaşdan yox, queue-dan yenidən götürür — yaddaş sızma riski yoxdur. Spring-də `@Async + @Retryable` thread saxlayır — daha sürətli, amma OutOfMemory riski var əgər çox retry yığılsa.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `@Retryable` + `@Recover` annotation pair
- `RetryTemplate` — programmatic API
- `RetryListener` — hər cəhddə callback
- `SimpleRetryPolicy`, `ExceptionClassifierRetryPolicy`, `CircuitBreakerRetryPolicy`, `TimeoutRetryPolicy`
- `ExponentialRandomBackOffPolicy` — jitter ilə
- Resilience4j inteqrasiyası (`@CircuitBreaker`, `@Bulkhead`, `@RateLimiter`, `@TimeLimiter`)
- Circuit breaker state machine (CLOSED → OPEN → HALF_OPEN)
- Annotation kombinasiya sırası (Retry ← CB ← RL ← TL ← Bulkhead)
- `Retryable(stateful = true)` — state-ful retry cross-transaction
- Actuator inteqrasiyası — circuit breaker health endpoint

**Yalnız Laravel-də:**
- Queue worker-level retry (built-in, heç bir paket lazım deyil)
- `retry()` helper funksiyası — dərhal istifadə
- `Http::retry(3, 100)` — HTTP client üzərində
- Job `$backoff = [...]` array — exponential müəyyən et
- `retryUntil()` metod — vaxt əsaslı limit
- `$maxExceptions` — fərqli exception limit
- `ThrottlesExceptions` middleware — failure-based pausing
- `WithoutOverlapping` middleware — asan bulkhead
- `RateLimited` middleware — asan rate limit
- `failed_jobs` cədvəli — GUI/CLI ilə retry
- Horizon dashboard — failed job-ların interactive retry-ı
- `failed(Throwable $e)` method — job-da terminal callback

---

## Best Practices

1. **Idempotency açarı istifadə et.** Retry = eyni sorğu iki dəfə getməlidir. Server idempotency key ilə iki dəfə işləməsin.
2. **Exponential backoff + jitter.** Sabit 1s retry "thundering herd" yaradır. Exponential (1s, 2s, 4s) + random jitter yaxşıdır.
3. **Yalnız transient exception-da retry et.** `retryFor = { IOException.class, SQLTransientException.class }` — `ValidationException` retry edilməməlidir.
4. **Max retry sayını məhdudlaşdır.** 3-5 arası norma. Sonsuz retry yaddaş, queue və yuxarı sistemləri aşır.
5. **Circuit breaker 3rd-party API üçün.** Stripe çöküb 500 qaytarırsa — 1000 retry heç nəyi düzəltmir. Circuit bağlayıb fallback ver.
6. **Spring-də `@Retryable` xaricdə, `@Transactional` REQUIRES_NEW daxildə.** Əks halda eyni tx yenidən fail olur.
7. **Laravel-də `retry_after > $timeout`** — əks halda worker ölmüş job-u yenidən başlayar.
8. **Timeout + retry birgə.** Timeout olmasa retry heç vaxt işləməyəcək (hang). Spring: `@TimeLimiter`. Laravel: `$timeout`.
9. **Fallback mənalı olsun.** `@Recover` metodu və ya `failed()` metodu boş qoyma — user-ə "pending" state, admin-ə bildiriş göndər.
10. **Log hər cəhdi.** `RetryListener` və ya `failed()` metod — hansı API neçə dəfə fail edir görünsün.
11. **Laravel-də failed job-a Slack alert** — `QueueFailedJobNotifier` event və ya `failed()` metodda notification.
12. **Spring-də Resilience4j metric-lərini Prometheus-a export et** — circuit state, retry sayı, failure rate.

---

## Yekun

Spring Retry + Resilience4j zəngin, enterprise-ready resilience stack verir: `@Retryable` + `@Recover` sadə istifadə, `RetryTemplate` programmatic, policy-lər (`SimpleRetryPolicy`, `ExceptionClassifierRetryPolicy`, `CircuitBreakerRetryPolicy`), backoff növləri (fixed, exponential, random), Resilience4j ilə `@CircuitBreaker`, `@Bulkhead`, `@TimeLimiter`, `@RateLimiter`. Laravel sadə amma effektiv aləti təklif edir: `retry()` helper, `Http::retry()`, Job `$tries` + `$backoff`, `ThrottlesExceptions` + `WithoutOverlapping` + `RateLimited` middleware, worker-level retry.

Spring thread-based sinxron retry + AOP proxy ilə güclüdür — enterprise integrations üçün ideal. Laravel queue-based async retry + worker process model-i ilə distributed resilience verir. Hər iki sistemdə idempotency key şərt, exponential backoff + jitter məcburi, fallback mənalı olmalıdır. Circuit breaker Laravel-də default yoxdur — job-level `ThrottlesExceptions` və ya custom middleware. Spring-də Resilience4j ilə tam state machine.

Retry sistemi simple olsun — çox çətinləşdirsən debug olunmur. Idempotency olmadan retry təhlükəlidir. Yalnız transient xətada retry, həmişə timeout qoy, max retry sayını məhdudlaşdır — əsas qaydalar hər iki framework üçün eynidir.
