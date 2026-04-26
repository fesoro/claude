# Circuit Breaker Pattern (Middle)

## İcmal

Circuit breaker bir service fail olduqda cascade failure-ı önləyən patterndir. Elektrik
sigortası kimi işləyir - problem olduqda "açılır" və daha çox zərər verməsinin qarşısını alır.
Əgər downstream service yavaş və ya down olubsa, request göndərməyə davam etmək əvəzinə,
tez fail edib fallback qaytarır.

```
Normal:       App -> [Circuit Breaker: CLOSED] -> Service (OK)
Failures:     App -> [Circuit Breaker: CLOSED] -> Service (Error x5)
Tripped:      App -> [Circuit Breaker: OPEN] -> Fallback (Service-ə getmir)
Recovery:     App -> [Circuit Breaker: HALF-OPEN] -> Service (test request)
  Success:    App -> [Circuit Breaker: CLOSED] -> Service (normal)
  Fail:       App -> [Circuit Breaker: OPEN] -> Fallback (yenidən gözlə)
```


## Niyə Vacibdir

Downstream servis yavaşladıqda kaskad xəta bütün sistemi çökdürə bilər. Circuit breaker fail-fast davranışı ilə thread pool-ların tükənməsinin qarşısını alır; fallback mexanizmi istifadəçiyə degraded service göstərməyə imkan verir. Resilience4j, Laravel Spatie — bu pattern hər backend-də lazımdır.

## Əsas Anlayışlar

### Circuit Breaker States

**CLOSED (Bağlı - Normal vəziyyət)**
- Bütün request-lər service-ə gedir
- Failure counter saxlanılır
- Failure threshold aşıldıqda OPEN-ə keçir

**OPEN (Açıq - Qoruma vəziyyəti)**
- Heç bir request service-ə getmir
- Dərhal fallback/error qaytarılır
- Timeout müddəti bitdikdə HALF-OPEN-ə keçir

**HALF-OPEN (Yarım açıq - Test vəziyyəti)**
- Limitli sayda test request göndərilir
- Uğurlu olarsa CLOSED-a qayıdır
- Fail olarsa OPEN-ə qayıdır

```
         failure_count >= threshold
  CLOSED ────────────────────────────> OPEN
    ^                                   |
    |                             timeout expires
    |                                   |
    |                                   v
    +──── success ────────────── HALF-OPEN
                                   |
                              failure ──> OPEN
```

### Əlaqəli Patternlər

**Retry Pattern**
Müvəqqəti xətalar üçün yenidən cəhd.

```
Request -> Fail -> Wait 1s -> Retry -> Fail -> Wait 2s -> Retry -> Success

Exponential backoff: 1s, 2s, 4s, 8s, 16s...
Jitter əlavə et: random(0, backoff) - thundering herd-dən qorunmaq üçün
```

**Timeout Pattern**
Response gəlməsə müəyyən müddət sonra timeout.

```
Request -> Service (cavab vermir) -> 5s timeout -> Error

Connect timeout: 2s (TCP connection qurmaq)
Read timeout: 10s (response gözləmək)
```

**Bulkhead Pattern**
Resource-ları izolasiya edir ki, bir service-in problemləri digərlərinə təsir etməsin.

```
Bulkhead olmadan:
  Thread pool: 100 threads (shared)
  Payment Service slow -> 100 thread blocked -> Bütün app down!

Bulkhead ilə:
  Payment Service pool: 20 threads
  Order Service pool: 30 threads
  User Service pool: 20 threads
  Free pool: 30 threads
  Payment slow -> yalnız 20 thread blocked, digər service-lər işləyir
```

**Fallback Pattern**
Primary service fail olduqda alternativ cavab.

```
Primary: Real-time price from API
Fallback 1: Cached price from Redis
Fallback 2: Last known price from DB
Fallback 3: Default/static price
```

### Circuit Breaker Configuration

```
failure_threshold: 5        # Neçə failure-dan sonra OPEN olsun
success_threshold: 3        # HALF-OPEN-da neçə success-dən sonra CLOSED
timeout: 30s                # OPEN state-dən HALF-OPEN-a keçmə müddəti
monitoring_window: 60s      # Failure-ları say bu window ərzində
failure_rate_threshold: 50% # 50%+ failure olsa OPEN et
slow_call_threshold: 5s     # Bundan yavaş response failure sayılır
slow_call_rate: 80%         # 80%+ slow call olsa OPEN et
```

## Arxitektura

### Service Mesh Circuit Breaker

```
[Service A] -> [Sidecar Proxy] -> [Sidecar Proxy] -> [Service B]
                    |                    |
               Circuit Breaker      Circuit Breaker
                    |                    |
              [Istio Control Plane / Envoy Config]

Istio/Envoy sidecar proxy circuit breaker-i service kodu dəyişmədən təmin edir.
```

### Multi-Service Circuit Breaker

```
[API Gateway]
     |
     ├─[CB]─> Payment Service  (CLOSED - normal)
     |         Fallback: "Payment processing delayed"
     |
     ├─[CB]─> Inventory Service (OPEN - down)
     |         Fallback: cached inventory data
     |
     ├─[CB]─> Email Service (HALF-OPEN - testing)
     |         Fallback: queue email for later
     |
     └─[CB]─> User Service (CLOSED - normal)

Hər service üçün ayrı circuit breaker.
```

## Nümunələr

### Circuit Breaker Implementation

```php
// app/Services/CircuitBreaker/CircuitBreaker.php
namespace App\Services\CircuitBreaker;

use Illuminate\Support\Facades\Cache;
use Closure;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private string $service,
        private int $failureThreshold = 5,
        private int $successThreshold = 3,
        private int $timeout = 30, // seconds
    ) {}

    public function call(Closure $action, Closure $fallback = null): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset()) {
                $this->setState(self::STATE_HALF_OPEN);
                return $this->attemptCall($action, $fallback);
            }
            return $this->handleFallback($fallback);
        }

        return $this->attemptCall($action, $fallback);
    }

    private function attemptCall(Closure $action, ?Closure $fallback): mixed
    {
        try {
            $result = $action();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            if ($fallback) {
                return $this->handleFallback($fallback);
            }
            throw $e;
        }
    }

    private function recordSuccess(): void
    {
        $state = $this->getState();
        if ($state === self::STATE_HALF_OPEN) {
            $successes = Cache::increment($this->key('successes'));
            if ($successes >= $this->successThreshold) {
                $this->setState(self::STATE_CLOSED);
                $this->resetCounters();
            }
        } elseif ($state === self::STATE_CLOSED) {
            // Reset failure counter on success
            Cache::put($this->key('failures'), 0, $this->timeout * 2);
        }
    }

    private function recordFailure(): void
    {
        $failures = Cache::increment($this->key('failures'));

        if ($failures >= $this->failureThreshold) {
            $this->setState(self::STATE_OPEN);
            Cache::put($this->key('opened_at'), time(), $this->timeout * 2);
        }
    }

    private function shouldAttemptReset(): bool
    {
        $openedAt = Cache::get($this->key('opened_at'), 0);
        return (time() - $openedAt) >= $this->timeout;
    }

    private function handleFallback(?Closure $fallback): mixed
    {
        if ($fallback) {
            return $fallback();
        }
        throw new CircuitBreakerOpenException(
            "Circuit breaker is open for service: {$this->service}"
        );
    }

    private function getState(): string
    {
        return Cache::get($this->key('state'), self::STATE_CLOSED);
    }

    private function setState(string $state): void
    {
        Cache::put($this->key('state'), $state, $this->timeout * 2);
    }

    private function resetCounters(): void
    {
        Cache::put($this->key('failures'), 0, $this->timeout * 2);
        Cache::put($this->key('successes'), 0, $this->timeout * 2);
    }

    private function key(string $suffix): string
    {
        return "circuit_breaker:{$this->service}:{$suffix}";
    }
}
```

### İstifadə Nümunəsi

```php
// app/Services/PaymentService.php
namespace App\Services;

use App\Services\CircuitBreaker\CircuitBreaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PaymentService
{
    private CircuitBreaker $circuitBreaker;

    public function __construct()
    {
        $this->circuitBreaker = new CircuitBreaker(
            service: 'payment-gateway',
            failureThreshold: 5,
            successThreshold: 3,
            timeout: 30,
        );
    }

    public function charge(float $amount, string $token): array
    {
        return $this->circuitBreaker->call(
            action: function () use ($amount, $token) {
                $response = Http::timeout(5)
                    ->retry(2, 100)
                    ->post('https://api.payment-gateway.com/charge', [
                        'amount' => $amount,
                        'token' => $token,
                    ]);

                if ($response->failed()) {
                    throw new \RuntimeException('Payment failed: ' . $response->body());
                }

                return $response->json();
            },
            fallback: function () use ($amount, $token) {
                // Fallback: queue for later processing
                ProcessPaymentJob::dispatch($amount, $token);
                return [
                    'status' => 'pending',
                    'message' => 'Payment queued for processing',
                ];
            }
        );
    }
}
```

### Retry with Exponential Backoff

```php
// app/Services/RetryableHttpClient.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class RetryableHttpClient
{
    public static function request(
        string $method,
        string $url,
        array $data = [],
        int $maxRetries = 3,
        int $baseDelay = 100, // ms
    ): \Illuminate\Http\Client\Response {
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(10)->{$method}($url, $data);

                if ($response->successful() || $response->status() < 500) {
                    return $response;
                }

                throw new \RuntimeException("HTTP {$response->status()}");
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < $maxRetries) {
                    // Exponential backoff with jitter
                    $delay = $baseDelay * (2 ** $attempt);
                    $jitter = random_int(0, $delay / 2);
                    usleep(($delay + $jitter) * 1000);
                }
            }
        }

        throw $lastException;
    }
}
```

### Laravel HTTP Client Retry (Built-in)

```php
// Laravel-in öz retry mexanizmi
$response = Http::retry(3, 100, function ($exception, $request) {
    // Yalnız server error-larında retry et
    return $exception instanceof ConnectionException
        || ($exception instanceof RequestException && $exception->response->status() >= 500);
}, throw: false)
->timeout(10)
->connectTimeout(3)
->post('https://api.example.com/data', $payload);

if ($response->failed()) {
    // Bütün retry-lar fail oldu
    logger()->error('API call failed after retries', [
        'status' => $response->status(),
        'body' => $response->body(),
    ]);
}
```

### Bulkhead Pattern (Laravel)

```php
// Semaphore-based bulkhead with Redis
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class Bulkhead
{
    public function __construct(
        private string $name,
        private int $maxConcurrent = 10,
        private int $timeout = 30,
    ) {}

    public function execute(callable $action): mixed
    {
        $permit = $this->acquirePermit();

        if (!$permit) {
            throw new BulkheadFullException(
                "Bulkhead '{$this->name}' is full ({$this->maxConcurrent} concurrent)"
            );
        }

        try {
            return $action();
        } finally {
            $this->releasePermit();
        }
    }

    private function acquirePermit(): bool
    {
        $key = "bulkhead:{$this->name}:count";
        $count = (int) Cache::get($key, 0);

        if ($count >= $this->maxConcurrent) {
            return false;
        }

        Cache::increment($key);
        Cache::put($key, Cache::get($key), $this->timeout);
        return true;
    }

    private function releasePermit(): void
    {
        $key = "bulkhead:{$this->name}:count";
        Cache::decrement($key);
    }
}

// İstifadə
$bulkhead = new Bulkhead('payment-service', maxConcurrent: 20);
$result = $bulkhead->execute(fn() => $paymentService->charge($amount));
```

## Real-World Nümunələr

**Netflix Hystrix:** Circuit breaker-in pioneri (artıq sunset). Hər microservice call-ı
Hystrix command ilə wrap olunurdu. Real-time dashboard, fallback, bulkhead dəstəyi.
İndi Resilience4j (Java) tövsiyə olunur.

**AWS:** AWS SDK-da built-in retry with exponential backoff. Service-specific retry
strategiyaları. DynamoDB, S3, SQS-də default retry mexanizmi.

**Uber:** Service mesh (Envoy) ilə circuit breaker. Microservice-lər arası bütün
call-lar proxy-dən keçir. Central configuration ilə threshold-lar idarə olunur.

## Praktik Tapşırıqlar

**S: Circuit breaker nə vaxt istifadə olunmalıdır?**
C: External service call-larda (payment gateway, third-party API), microservice-lər arası
kommunikasiyada, database əlçatan olmadıqda. Hər yerdə deyil - yalnız failure-ın
cascade edə biləcəyi yerlərdə.

**S: Circuit breaker vs retry fərqi?**
C: Retry müvəqqəti xətalar üçün - bir neçə cəhddən sonra uğurlu ola bilər.
Circuit breaker uzunmüddətli problemlər üçün - service down olduqda request göndərməyi
dayandırır. İkisi birlikdə istifadə olunur: retry -> threshold aşılır -> circuit opens.

**S: Fallback strategiyaları hansılardır?**
C: 1) Cached data qaytarmaq, 2) Default/static response, 3) Queue-ya əlavə edib
sonra emal etmək, 4) Degraded response (az feature ilə), 5) Error mesajı ilə graceful fail.

**S: Half-open state niyə lazımdır?**
C: Service bərpa olubsa yoxlamaq üçün. Birbaşa OPEN-dən CLOSED-a keçsək, service
hələ hazır deyilsə bütün trafik yenidən fail olacaq. Half-open limited request göndərir,
service hazırdırsa tədricən normal vəziyyətə qayıdır.

## Praktik Baxış

1. **Hər downstream service üçün ayrı circuit breaker** - Global deyil, per-service
2. **Meaningful fallbacks** - Boş cavab əvəzinə faydalı fallback: cached data, degraded mode
3. **Monitor circuit state** - OPEN olduqda alert göndərin, dashboard-da göstərin
4. **Timeout-ları tune edin** - Çox qısa: false positives. Çox uzun: slow cascade
5. **Retry + Circuit Breaker birlikdə** - Retry qısamüddətli, CB uzunmüddətli problemlər üçün
6. **Bulkhead ilə kombinasiya** - Thread/connection pool izolasiyası əlavə edin
7. **Test edin** - Chaos engineering ilə circuit breaker-in düzgün işlədiyini yoxlayın
8. **Gradual recovery** - Half-open-da trafiki tədricən artırın


## Əlaqəli Mövzular

- [Message Queues](05-message-queues.md) — async ilə circuit breaker alternativ
- [Microservices](10-microservices.md) — circuit breaker-in əsas istifadə yeri
- [Backpressure](57-backpressure-load-shedding.md) — sistemik yük idarəsi
- [Service Discovery](29-service-discovery.md) — sağlam endpoint seçimi
- [Webhook Delivery](82-webhook-delivery-system.md) — external servis circuit breaker
