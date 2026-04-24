# Circuit Breaker və Resilience Pattern-ləri

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Distributed sistemlərdə bir xarici servis yavaşlayanda və ya xəta verəndə — sənin tətbiqin də onunla birlikdə düşə bilər. **Circuit Breaker** elektrik yarıkəsici kimi işləyir: xətalar çoxalanda servisi "bağlayır", müəyyən müddət gözləyir, sonra yarı-açıq vəziyyətə keçir. Bundan başqa **Retry** (təkrar cəhd), **RateLimiter** (sürət limiti), **Bulkhead** (resurs izolyasiyası) və **TimeLimiter** (vaxt limiti) pattern-ləri vacibdir.

Java dünyasında **Resilience4j** de facto standartdır (Hystrix 2018-də deprecated oldu). Laravel-də isə **Http::retry()**, custom middleware və `lunarstorm/laravel-circuit-breaker`, `stechstudio/laravel-circuit-breaker` kimi paketlər istifadə olunur.

---

## Spring-də istifadəsi (Resilience4j)

### Dependency-lər

```xml
<dependency>
    <groupId>io.github.resilience4j</groupId>
    <artifactId>resilience4j-spring-boot3</artifactId>
    <version>2.2.0</version>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-aop</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-actuator</artifactId>
</dependency>
```

### Konfiqurasiya

```yaml
resilience4j:
  circuitbreaker:
    instances:
      paymentService:
        registerHealthIndicator: true
        slidingWindowType: COUNT_BASED
        slidingWindowSize: 20                  # Son 20 çağırış
        minimumNumberOfCalls: 10               # Ən azı 10 çağırış ölçmə başlasın
        failureRateThreshold: 50               # 50% xəta → OPEN
        slowCallRateThreshold: 80              # 80% yavaş → OPEN
        slowCallDurationThreshold: 2s          # >2s = yavaş
        waitDurationInOpenState: 30s           # OPEN 30s qalsın
        permittedNumberOfCallsInHalfOpenState: 3
        automaticTransitionFromOpenToHalfOpenEnabled: true

  retry:
    instances:
      paymentService:
        maxAttempts: 3
        waitDuration: 500ms
        enableExponentialBackoff: true
        exponentialBackoffMultiplier: 2        # 500ms, 1s, 2s
        retryExceptions:
          - java.io.IOException
          - java.util.concurrent.TimeoutException
        ignoreExceptions:
          - com.example.ValidationException

  ratelimiter:
    instances:
      paymentService:
        limitForPeriod: 100                    # Saniyədə 100 çağırış
        limitRefreshPeriod: 1s
        timeoutDuration: 0

  bulkhead:
    instances:
      paymentService:
        maxConcurrentCalls: 10                 # Paralel 10-dan çox olmasın
        maxWaitDuration: 100ms

  timelimiter:
    instances:
      paymentService:
        timeoutDuration: 3s
        cancelRunningFuture: true

management:
  endpoints:
    web:
      exposure:
        include: health, circuitbreakers, retries, ratelimiters, bulkheads
```

### Annotation ilə istifadə

```java
@Service
public class PaymentService {

    private final PaymentClient client;

    public PaymentService(PaymentClient client) {
        this.client = client;
    }

    @CircuitBreaker(name = "paymentService", fallbackMethod = "fallbackCharge")
    @Retry(name = "paymentService")
    @RateLimiter(name = "paymentService")
    @Bulkhead(name = "paymentService")
    @TimeLimiter(name = "paymentService")
    public CompletableFuture<PaymentResult> charge(Order order) {
        return CompletableFuture.supplyAsync(() ->
            client.charge(order.getId(), order.getTotal())
        );
    }

    // Fallback — eyni signature, sonunda Throwable
    public CompletableFuture<PaymentResult> fallbackCharge(Order order, Throwable ex) {
        log.warn("Payment fallback: {}", ex.getMessage());

        // 1) Cache-dən çıxar (idempotency yoxla)
        // 2) Queue-a qoy, sonra emal et
        paymentQueue.enqueue(order);

        return CompletableFuture.completedFuture(
            PaymentResult.pending(order.getId(), "Queued for retry")
        );
    }

    // Fərqli exception-lar üçün fərqli fallback
    public CompletableFuture<PaymentResult> fallbackCharge(
            Order order, CallNotPermittedException ex) {
        // Circuit breaker OPEN vəziyyətdədir
        return CompletableFuture.completedFuture(
            PaymentResult.unavailable(order.getId(), "Servis müvəqqəti əlçatmazdır")
        );
    }
}
```

### Programmatic API (annotation olmadan)

```java
@Service
public class InventoryService {

    private final CircuitBreaker circuitBreaker;
    private final Retry retry;
    private final RateLimiter rateLimiter;
    private final InventoryClient client;

    public InventoryService(CircuitBreakerRegistry cbRegistry,
                            RetryRegistry retryRegistry,
                            RateLimiterRegistry rlRegistry,
                            InventoryClient client) {
        this.circuitBreaker = cbRegistry.circuitBreaker("inventory");
        this.retry = retryRegistry.retry("inventory");
        this.rateLimiter = rlRegistry.rateLimiter("inventory");
        this.client = client;
    }

    public Stock checkStock(String sku) {
        Supplier<Stock> supplier = () -> client.getStock(sku);

        // Dekoratorlar zəncirlənir
        Supplier<Stock> decorated = Decorators.ofSupplier(supplier)
            .withCircuitBreaker(circuitBreaker)
            .withRetry(retry)
            .withRateLimiter(rateLimiter)
            .withFallback(List.of(IOException.class),
                ex -> Stock.unknown(sku))
            .decorate();

        return decorated.get();
    }
}
```

### Circuit breaker vəziyyətləri

```
CLOSED → OPEN: failureRateThreshold aşıldı
OPEN → HALF_OPEN: waitDurationInOpenState müddəti keçdi
HALF_OPEN → CLOSED: test çağırışları uğurludur
HALF_OPEN → OPEN: test çağırışlarında yenə xəta
```

### Event listener — monitoring üçün

```java
@Configuration
public class Resilience4jEventLogger {

    @Bean
    public RegistryEventConsumer<CircuitBreaker> cbEventConsumer() {
        return new RegistryEventConsumer<>() {
            @Override
            public void onEntryAddedEvent(EntryAddedEvent<CircuitBreaker> event) {
                event.getAddedEntry().getEventPublisher()
                    .onStateTransition(e -> {
                        log.warn("CB [{}] {} → {}",
                            e.getCircuitBreakerName(),
                            e.getStateTransition().getFromState(),
                            e.getStateTransition().getToState());

                        meterRegistry.counter("circuit_breaker.state_transition",
                            "name", e.getCircuitBreakerName(),
                            "to", e.getStateTransition().getToState().name()
                        ).increment();
                    })
                    .onError(e -> log.error("CB [{}] error: {}",
                        e.getCircuitBreakerName(), e.getThrowable().getMessage()));
            }
            @Override public void onEntryRemovedEvent(EntryRemovedEvent<CircuitBreaker> e) {}
            @Override public void onEntryReplacedEvent(EntryReplacedEvent<CircuitBreaker> e) {}
        };
    }
}
```

### Actuator endpoint

```
GET /actuator/circuitbreakers

{
  "circuitBreakers": {
    "paymentService": {
      "state": "CLOSED",
      "failureRate": "12.5%",
      "slowCallRate": "5.0%",
      "bufferedCalls": 20,
      "failedCalls": 2,
      "slowCalls": 1,
      "notPermittedCalls": 0
    }
  }
}
```

### Feign client ilə inteqrasiya

```java
@FeignClient(name = "payment-service", url = "${payment.url}")
public interface PaymentClient {

    @CircuitBreaker(name = "paymentService", fallback = PaymentClientFallback.class)
    @PostMapping("/charge")
    PaymentResult charge(@RequestBody ChargeRequest request);
}
```

---

## Laravel-də istifadəsi

### `Http::retry()` — sadə retry

```php
use Illuminate\Support\Facades\Http;

$response = Http::retry(3, 500, function ($exception, $request) {
    // Yalnız 5xx və bağlantı xətaları üçün retry
    return $exception instanceof ConnectionException
        || ($exception->response?->serverError() ?? false);
}, throw: false)
    ->timeout(3)
    ->connectTimeout(1)
    ->post('https://payment.api/charge', [
        'order_id' => $order->id,
        'amount'   => $order->total,
    ]);

if ($response->failed()) {
    // Fallback
    $this->queuePayment($order);
}
```

### Exponential backoff

```php
Http::retry(3, function (int $attempt) {
    return (int) (500 * pow(2, $attempt - 1));    // 500ms, 1s, 2s
})
->post('https://payment.api/charge', $data);
```

### Circuit breaker — `ziptastic/laravel-circuit-breaker` və ya custom

```bash
composer require ackintosh/ganesha
```

```php
// config/ganesha.php
return [
    'adapter' => 'redis',
    'redis' => [
        'connection' => 'default',
    ],
    'strategy' => [
        'rate' => [
            'time_window' => 30,           // 30s pəncərə
            'failure_rate_threshold' => 50,// 50% xəta
            'minimum_requests' => 10,
            'interval_to_half_open' => 20, // 20s sonra HALF_OPEN
        ],
    ],
];
```

```php
// app/Services/PaymentService.php
use Ackintosh\Ganesha;
use Ackintosh\Ganesha\Exception\RejectedException;

class PaymentService
{
    public function __construct(
        private Ganesha $ganesha,
        private PaymentClient $client,
    ) {}

    public function charge(Order $order): PaymentResult
    {
        $service = 'payment_api';

        if (! $this->ganesha->isAvailable($service)) {
            return $this->fallback($order, 'Circuit open');
        }

        try {
            $result = $this->client->charge($order);
            $this->ganesha->success($service);
            return $result;
        } catch (\Throwable $e) {
            $this->ganesha->failure($service);
            return $this->fallback($order, $e->getMessage());
        }
    }

    private function fallback(Order $order, string $reason): PaymentResult
    {
        Log::warning('Payment fallback', ['order' => $order->id, 'reason' => $reason]);
        dispatch(new ProcessPaymentAsync($order))->onQueue('payments-retry');

        return PaymentResult::pending($order->id, 'Queued');
    }
}
```

### Custom middleware ilə circuit breaker

```php
// app/Support/CircuitBreaker.php
class CircuitBreaker
{
    public function __construct(
        private string $service,
        private int $threshold = 5,
        private int $decaySeconds = 60,
    ) {}

    public function available(): bool
    {
        $state = Cache::get($this->stateKey(), 'closed');

        if ($state === 'open') {
            $openedAt = Cache::get($this->openedAtKey());
            if (now()->diffInSeconds($openedAt) >= $this->decaySeconds) {
                Cache::put($this->stateKey(), 'half-open', now()->addMinutes(5));
                return true;
            }
            return false;
        }

        return true;
    }

    public function recordSuccess(): void
    {
        Cache::forget($this->failuresKey());
        Cache::put($this->stateKey(), 'closed', now()->addHour());
    }

    public function recordFailure(): void
    {
        $failures = Cache::increment($this->failuresKey());
        Cache::expire($this->failuresKey(), $this->decaySeconds);

        if ($failures >= $this->threshold) {
            Cache::put($this->stateKey(), 'open', now()->addHour());
            Cache::put($this->openedAtKey(), now());

            Log::error("Circuit [{$this->service}] opened", ['failures' => $failures]);
        }
    }

    private function stateKey(): string { return "cb:{$this->service}:state"; }
    private function failuresKey(): string { return "cb:{$this->service}:failures"; }
    private function openedAtKey(): string { return "cb:{$this->service}:opened_at"; }
}
```

```php
// İstifadə
class PaymentService
{
    public function charge(Order $order): PaymentResult
    {
        $cb = new CircuitBreaker('payment_api', threshold: 5, decaySeconds: 30);

        if (! $cb->available()) {
            return $this->fallback($order);
        }

        try {
            $response = Http::timeout(3)
                ->retry(3, 500, throw: false)
                ->post(config('services.payment.url') . '/charge', $order->toArray())
                ->throw();

            $cb->recordSuccess();
            return PaymentResult::fromResponse($response);

        } catch (\Throwable $e) {
            $cb->recordFailure();
            return $this->fallback($order);
        }
    }
}
```

### Rate limiter

```php
use Illuminate\Support\Facades\RateLimiter;

class PaymentService
{
    public function charge(Order $order): PaymentResult
    {
        $executed = RateLimiter::attempt(
            'payment-api',
            perMinute: 100,
            callback: fn () => $this->doCharge($order),
            decaySeconds: 60,
        );

        if (! $executed) {
            return PaymentResult::rateLimited($order->id);
        }

        return $executed;
    }
}
```

### Bulkhead — semaphore pattern

```php
// Redis əsaslı concurrent limit
class Bulkhead
{
    public function __construct(
        private string $key,
        private int $maxConcurrent = 10,
    ) {}

    public function execute(callable $callback): mixed
    {
        $lock = Cache::lock("bulkhead:{$this->key}:slot", 10);

        if (! $lock->get()) {
            throw new \RuntimeException('Bulkhead dolu');
        }

        $current = Cache::increment("bulkhead:{$this->key}:count");

        try {
            if ($current > $this->maxConcurrent) {
                throw new \RuntimeException('Bulkhead limitini aşdı');
            }
            return $callback();
        } finally {
            Cache::decrement("bulkhead:{$this->key}:count");
            $lock->release();
        }
    }
}
```

### Queue job retry ilə resilience

```php
class ProcessPayment implements ShouldQueue
{
    public $tries = 3;
    public $backoff = [5, 30, 120];     // exponential
    public $timeout = 30;

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(15);
    }

    public function handle(PaymentClient $client): void
    {
        try {
            $client->charge($this->order);
        } catch (TimeoutException | ConnectionException $e) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            return;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->order->markAsPaymentFailed($e->getMessage());
        Notification::send($this->order->user, new PaymentFailedNotification());
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring (Resilience4j) | Laravel |
|---|---|---|
| Circuit Breaker | Daxili, annotation ilə | 3rd party paket (Ganesha) və ya custom |
| Retry | `@Retry` annotation | `Http::retry()` sadə, `$tries` queue-da |
| Rate Limiter | `@RateLimiter` annotation | `RateLimiter::attempt()` facade |
| Bulkhead | `@Bulkhead` (semaphore/thread pool) | Custom (Redis semaphore) |
| Time Limiter | `@TimeLimiter` + `CompletableFuture` | `Http::timeout()` + PHP `set_time_limit` |
| Fallback | Eyni-signature method | Manual if/try-catch |
| State | In-memory (JVM heap) | Redis/cache (proseslərarası paylaşım lazım) |
| Metrics | Micrometer auto | Custom və ya Pulse |
| Config | YAML + annotation | PHP config + code |
| Event listener | `RegistryEventConsumer` | Log və ya custom event |
| Actuator | `/actuator/circuitbreakers` | Pulse və ya custom endpoint |
| Async support | `CompletableFuture` natural | Queue əsaslı async |

---

## Niyə belə fərqlər var?

**Java-nın stay-alive runtime üstünlüyü.** JVM prosesi davamlı işlədiyi üçün circuit breaker state-i in-memory saxlamaq mümkündür. Resilience4j atomic counter-lər istifadə edir — çox sürətli (saniyədə milyon çağırış). Hər node öz state-ini saxlayır (node-local breaker).

**PHP-nin request-per-process problemi.** PHP-FPM-də hər sorğu ayrı proses olduğundan, circuit state-i paylaşmaq üçün Redis lazımdır — Java-dan ~10x yavaş. Octane/Swoole-da bu problem azalır, amma mainstream Laravel-də Redis-based breaker standartdır.

**Annotation vs imperative.** Resilience4j AOP vasitəsilə annotation oxuyur, metodun ətrafına proxy qoyur. Laravel-də belə dərin AOP dəstəyi yoxdur — manual `try-catch` və ya middleware lazımdır. Bu Laravel-i verbose edir, amma şəffaf — nə baş verdiyini görürsən.

**Fallback pattern fərqli.** Spring-də fallback metod eyni signature-ə malik olmalıdır və sonda `Throwable` parametri qəbul edir — compiler yoxlayır. Laravel-də fallback `catch` bloku daxilindədir — daha çevik, amma signature uyğunluğu yoxlanılmır.

**Queue-centric resilience (Laravel).** Laravel ekosisteminin əksəriyyət async əməliyyatları queue-a atır. Bu təbii retry, backoff və fallback verir — `$tries`, `$backoff`, `failed()`. Hystrix/Resilience4j fəlsəfəsindən fərqli olaraq: "xəta olursa, qeyri-sinxron təkrar cəhd et" vs "xəta olursa, dərhal fallback qaytar".

**TimeLimiter fərqi.** Java-da `@TimeLimiter` `CompletableFuture.orTimeout()` istifadə edir — thread-i zorla ləğv edir. PHP-də HTTP timeout Guzzle-un timeout mexanizmi ilə edilir, amma bütün uzun hesablamaları kəsmək üçün `set_time_limit()` proses-level lazımdır.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də (Resilience4j):**
- Decorator composition API (`Decorators.ofSupplier(...)`)
- 5 pattern tək kitabxanada: CircuitBreaker, Retry, RateLimiter, Bulkhead, TimeLimiter
- Thread-pool bulkhead — ayrıca thread pool hər servis üçün
- `COUNT_BASED` və `TIME_BASED` sliding window
- Actuator ilə real-time dashboard
- Micrometer metric-ləri avtomatik
- `CallNotPermittedException` ilə breaker-spesifik fallback
- Reactor (`Mono`, `Flux`) və `CompletableFuture` dəstəyi
- Programmatic və annotation API paralel

**Yalnız Laravel-də:**
- `Http::retry()` bir-sətirlik sadə retry
- Queue-level retry ilə cron-style backoff
- `Http::fake()` testlərdə retry simulation
- `RateLimiter::for()` — named limiter + response customization
- `Http::pool()` — paralel HTTP sorğuları (natural bulkhead)
- Job `backoff` array — hər cəhd üçün fərqli gecikmə
- `failed()` metodu ilə son xəta handler
- Dead letter queue (`failed_jobs` cədvəli)
