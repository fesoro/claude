# Circuit Breaker Pattern

## Mündəricat
1. [Problem: Cascading Failures](#problem-cascading-failures)
2. [Circuit Breaker nədir?](#circuit-breaker-nədir)
3. [Hallar (States)](#hallar-states)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [Redis ilə Distributed Circuit Breaker](#redis-ilə-distributed-circuit-breaker)
6. [Retry Pattern ilə inteqrasiya](#retry-pattern-ilə-inteqrasiya)
7. [Alətlər](#alətlər)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Cascading Failures

```
Servis A → Servis B → Servis C (çökmüş!)

A, B-ni çağırır. B, C-ni çağırır. C çökmüşdür.
B, C-nin cavabını gözləyir (timeout: 30 saniyə).
A, B-nin cavabını gözləyir.
Bütün thread-lər bloklanır!

┌──────────┐     ┌──────────┐     ┌──────────┐
│ Service A│────►│ Service B│────►│ Service C│
│ (waiting)│     │ (waiting)│     │ (DOWN)   │
└──────────┘     └──────────┘     └──────────┘
     ↑                ↑
  Thread pool      Thread pool
  exhausted!       exhausted!

Nəticə: A da effektiv olaraq çöküb — Cascading Failure
```

---

## Circuit Breaker nədir?

```
Elektrik açarı kimi davranır.
Çox cərəyan keçsə, açar qırılır.
Cihazları qoruyur.

Normal:                    Açar qırılanda:
┌───┐ ─────────── ┌───┐   ┌───┐  ╳  ─── ┌───┐
│ A │             │ B │   │ A │          │ B │
└───┘             └───┘   └───┘          └───┘
  ↑ sorğular keçir          ↑ sorğular fail fast
                             (gözləmə yox!)
```

**Faydaları:**
- Fail fast: Xətalı servisə sorğu göndərmə, dərhal error qaytar
- Cascading failure-ı önlə
- Xətalı servisin recover olmasına vaxt ver
- System-wide monitoring

---

## Hallar (States)

```
                  failure_threshold keçildi
    ┌─────────────────────────────────────┐
    │                                     ▼
┌───┴──┐                            ┌──────────┐
│      │   success                  │          │
│CLOSED│◄────────────────────────── │   OPEN   │
│      │                            │          │
└───┬──┘                            └───┬──────┘
    │                                   │
    │ normal                            │ reset_timeout keçdi
    │ operation                         │
    │                              ┌────▼──────┐
    │                              │           │
    │       failure                │ HALF-OPEN │
    │◄─────────────────────────────│           │
    │                              └───────────┘

CLOSED (Bağlı):
  → Hər şey normal işləyir
  → Xəta sayılır
  → failure_threshold keçsə → OPEN-ə keç

OPEN (Açıq):
  → Bütün sorğular dərhal fail olur (actual call yoxdur)
  → reset_timeout keçəndən sonra → HALF-OPEN-ə keç

HALF-OPEN (Yarım açıq):
  → Test sorğusu göndərilir
  → Uğurlu olarsa → CLOSED-a keç
  → Uğursuz olarsa → OPEN-ə qayıt
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
enum CircuitState: string
{
    case CLOSED    = 'closed';
    case OPEN      = 'open';
    case HALF_OPEN = 'half_open';
}

class CircuitBreaker
{
    private CircuitState $state = CircuitState::CLOSED;
    private int $failureCount   = 0;
    private ?int $openedAt      = null;
    
    public function __construct(
        private int $failureThreshold = 5,
        private int $resetTimeoutSecs = 60,
        private int $successThreshold = 2,   // HALF_OPEN-dən CLOSED-a keçmək üçün
    ) {}
    
    public function call(callable $fn): mixed
    {
        if ($this->isOpen()) {
            throw new CircuitOpenException(
                "Circuit breaker açıqdır. Servis mövcud deyil."
            );
        }
        
        try {
            $result = $fn();
            $this->onSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }
    
    private function isOpen(): bool
    {
        if ($this->state === CircuitState::OPEN) {
            // Reset timeout keçibmi?
            if (time() - $this->openedAt >= $this->resetTimeoutSecs) {
                $this->state = CircuitState::HALF_OPEN;
                return false;
            }
            return true;
        }
        return false;
    }
    
    private function onSuccess(): void
    {
        if ($this->state === CircuitState::HALF_OPEN) {
            $this->failureCount--;
            if ($this->failureCount <= 0) {
                $this->reset();
            }
        } else {
            $this->failureCount = 0;
        }
    }
    
    private function onFailure(): void
    {
        $this->failureCount++;
        
        if ($this->state === CircuitState::HALF_OPEN ||
            $this->failureCount >= $this->failureThreshold) {
            $this->trip();
        }
    }
    
    private function trip(): void
    {
        $this->state    = CircuitState::OPEN;
        $this->openedAt = time();
        Log::warning('Circuit breaker açıldı', [
            'failures' => $this->failureCount,
        ]);
    }
    
    private function reset(): void
    {
        $this->state        = CircuitState::CLOSED;
        $this->failureCount = 0;
        $this->openedAt     = null;
        Log::info('Circuit breaker bağlandı (sağlamlıq bərpa oldu)');
    }
    
    public function getState(): CircuitState
    {
        return $this->state;
    }
}

// İstifadə
class PaymentService
{
    private CircuitBreaker $cb;
    
    public function __construct()
    {
        $this->cb = new CircuitBreaker(
            failureThreshold: 5,
            resetTimeoutSecs: 30
        );
    }
    
    public function charge(int $amount, string $cardToken): PaymentResult
    {
        try {
            return $this->cb->call(function () use ($amount, $cardToken) {
                return $this->paymentGateway->charge($amount, $cardToken);
            });
        } catch (CircuitOpenException $e) {
            // Fallback: Queued payment, user-a bildiriş
            $this->queuePaymentForLater($amount, $cardToken);
            throw new PaymentDeferredException("Ödəniş qısa gecikmə ilə işlənəcək");
        }
    }
}
```

---

## Redis ilə Distributed Circuit Breaker

In-memory CB problemi: Hər server öz CB-ni saxlayır. 5 server varsa, hər biri 5 uğursuzluğa görə açılır = 25 uğursuzluq.

*In-memory CB problemi: Hər server öz CB-ni saxlayır. 5 server varsa, h üçün kod nümunəsi:*
```php
class RedisCircuitBreaker
{
    public function __construct(
        private Redis $redis,
        private string $name,
        private int $failureThreshold = 5,
        private int $resetTimeoutSecs = 60,
        private int $windowSecs       = 60,  // Rolling window
    ) {}
    
    public function call(callable $fn): mixed
    {
        $state = $this->getState();
        
        if ($state === CircuitState::OPEN) {
            throw new CircuitOpenException("Circuit açıqdır: {$this->name}");
        }
        
        try {
            $result = $fn();
            
            if ($state === CircuitState::HALF_OPEN) {
                $this->closeCircuit();
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }
    
    private function getState(): CircuitState
    {
        $openedAt = $this->redis->get("cb:{$this->name}:opened_at");
        
        if ($openedAt === false) {
            return CircuitState::CLOSED;
        }
        
        if (time() - (int) $openedAt >= $this->resetTimeoutSecs) {
            return CircuitState::HALF_OPEN;
        }
        
        return CircuitState::OPEN;
    }
    
    private function recordFailure(): void
    {
        $key = "cb:{$this->name}:failures";
        
        // Rolling window-da xəta say
        $failures = $this->redis->incr($key);
        
        if ($failures === 1) {
            $this->redis->expire($key, $this->windowSecs);
        }
        
        if ($failures >= $this->failureThreshold) {
            $this->openCircuit();
        }
    }
    
    private function openCircuit(): void
    {
        $this->redis->set("cb:{$this->name}:opened_at", time());
        $this->redis->del("cb:{$this->name}:failures");
        Log::warning("Distributed circuit breaker açıldı: {$this->name}");
    }
    
    private function closeCircuit(): void
    {
        $this->redis->del("cb:{$this->name}:opened_at");
        Log::info("Distributed circuit breaker bağlandı: {$this->name}");
    }
    
    public function getMetrics(): array
    {
        return [
            'name'     => $this->name,
            'state'    => $this->getState()->value,
            'failures' => (int) ($this->redis->get("cb:{$this->name}:failures") ?? 0),
            'opened_at'=> $this->redis->get("cb:{$this->name}:opened_at"),
        ];
    }
}
```

---

## Retry Pattern ilə inteqrasiya

*Retry Pattern ilə inteqrasiya üçün kod nümunəsi:*
```php
class ResilientHttpClient
{
    public function __construct(
        private RedisCircuitBreaker $circuitBreaker,
        private int $maxRetries = 3,
        private int $retryDelay = 100, // ms
    ) {}
    
    public function get(string $url): array
    {
        $attempt = 0;
        
        while ($attempt <= $this->maxRetries) {
            try {
                return $this->circuitBreaker->call(function () use ($url) {
                    $response = Http::timeout(5)->get($url);
                    
                    if ($response->serverError()) {
                        throw new ServiceUnavailableException($response->status());
                    }
                    
                    return $response->json();
                });
            } catch (CircuitOpenException $e) {
                // Circuit açıqdır — retry etmə, birbaşa fail
                throw $e;
            } catch (ServiceUnavailableException $e) {
                $attempt++;
                if ($attempt > $this->maxRetries) throw $e;
                
                // Exponential backoff
                usleep($this->retryDelay * (2 ** $attempt) * 1000);
            }
        }
        
        throw new \RuntimeException('Sorğu uğursuz oldu');
    }
}
```

**Fallback strategiyaları:**

```php
class ProductService
{
    public function getProduct(int $id): Product
    {
        try {
            return $this->circuitBreaker->call(function () use ($id) {
                return $this->productApiClient->fetch($id);
            });
        } catch (CircuitOpenException $e) {
            // Fallback 1: Cache-dən qaytar
            $cached = Cache::get("product:$id");
            if ($cached) return Product::fromCache($cached);
            
            // Fallback 2: Default/skeleton product
            return Product::createPlaceholder($id);
        }
    }
}
```

---

## Alətlər

| Alət | Dil/Platform | Qeyd |
|------|--------------|------|
| Hystrix | Java | Netflix, köhnə amma klassik |
| Resilience4j | Java | Hystrix-in varisi |
| Polly | .NET | |
| php-circuit-breaker | PHP | Composer paketi |
| Nginx upstream | Ops | Nginx-in öz CB-si |
| Envoy Proxy | Service Mesh | Istio ilə |

---

## İntervyu Sualları

**1. Circuit Breaker niyə lazımdır?**
Xətalı servisə davamlı sorğu göndərmək thread pool-u tükəndirir və cascading failure-a gətirir. CB, threshold keçildikdə sorğuları fail fast edir — servis recover olana qədər gözləyir. Bu sistem-wide stability-ni qoruyur.

**2. 3 halı izah et.**
CLOSED: Normal iş, xətalar sayılır. OPEN: Threshold keçildi, sorğular dərhal fail fast edilir, servisə heç bir sorğu göndərilmir. HALF-OPEN: Reset timeout keçdikdə test sorğusu göndərilir; uğurlu olarsa CLOSED-a, uğursuzsa OPEN-ə keçir.

**3. In-memory vs distributed CB fərqi nədir?**
In-memory CB hər server öz state-ini saxlayır. 10 server varsa, hər biri ayrıca threshold-ı keçməlidir. Distributed CB (Redis) bütün serverlər üçün shared state saxlayır — bir server threshold-ı keçsə, hamı üçün açılır.

**4. Circuit Breaker açıldıqda fallback strategiyaları nələrdir?**
1) Cache-dən köhnə məlumat qaytar. 2) Default/placeholder cavab qaytar. 3) Sorğunu queue-ya at (later processing). 4) Partial functionality (məs: ödəniş offline queue). 5) Graceful degradation (feature-ı gizlət).

**5. Retry ilə birlikdə istifadə zamanı nəyə diqqət etmək lazımdır?**
CB açıqdırsa retry etmə — birbaşa exception yüksəlt. Retry yalnız CLOSED halda mənalıdır. Exponential backoff istifadə et. Retry sayı çox olarsa CB daha tez açılır — bunu nəzərə al.

**6. Circuit Breaker-in "failure threshold"-unu necə kalibrlə etmək lazımdır?**
Sabit sayısal threshold (məs: 5 xəta) əvəzinə faiz əsaslı threshold daha effektiv: "son 20 sorğunun 50%-i xəta olduqda aç". Bu say əsaslı threshold-dan üstündür çünki yük azdırsa 5 xəta normal ola bilər. Threshold — servisin normal xəta nisbətinin 2-3 qatı kimi seçilməlidir.

**7. Hystrix, Resilience4j kimi Java kitabxanaları PHP-yə alternativdir?**
PHP ekosisteminin dedicated paketi azdır: `ejsmont/circuit-breaker` (Redis-based), `ackintosh/ganesha`. Amma PHP-nin request-per-process modeli CB-ni çətinləşdirir — hər request yeni prosesdir, state paylaşılmır. Buna görə PHP-də CB mütləq Redis kimi paylaşılan storage-da saxlanmalıdır. Service Mesh (Istio/Envoy) istifadə edilərsə, CB kod səviyyəsindəki yük infrastruktura verilir.

---

## Anti-patternlər

**1. Circuit Breaker olmadan xətalı servisə davamlı sorğu göndərmək**
Servis down olduqda hər sorğu timeout gözləyir — thread pool tükənir, bütün sistem cavabsız qalır. Circuit Breaker OPEN olunca sorğular dərhal fail fast edilir, resurslar azad olur, cascading failure önlənir.

**2. Hər servis üçün eyni threshold istifadəsi**
Kritik payment servisi ilə notification servisini eyni 50% xəta threshold-u ilə açmaq — əhəmiyyətsiz servis üçün CB açılır, kritik üçün gecikir. Hər servis üçün ayrıca CB konfiqurasiyası: kritiklərə dar, az kritiklərə geniş threshold.

**3. In-memory CB ilə çox instansiyalı deploy**
10 server varsa hər biri ayrıca threshold sayır — servis faktiki olaraq down olsa belə heç bir server CB-ni açmır. Redis-based distributed CB istifadə edin ki, bütün instansiyalar eyni shared state-i paylaşsın.

**4. HALF-OPEN-də çox test sorğusu göndərmək**
Recovery yoxlamaq üçün eyni anda 10 sorğu buraxmaq — zəif servis yenidən əzilir. HALF-OPEN-də yalnız 1 test sorğusu buraxın; uğurlu olarsa CLOSED-a, uğursuzsa OPEN-ə qayıdın.

**5. Fallback strategiyası olmadan CB açmaq**
CB açılanda istifadəçiyə sadəcə 503 qaytarmaq — pis UX, biznes itkisi. Fallback müəyyən edin: cache-dən köhnə data, default cavab, offline queue, ya da partial functionality ilə graceful degradation.

**6. Retry ilə CB-ni koordinasiyasız istifadə**
CB OPEN olduqda da exponential backoff retry cəhd edir — retry storm yaranır, servis toparlanma şansı tapır. CB açıqdırsa retry dərhal dayansın; retry yalnız CLOSED vəziyyətdə mənalıdır.
