# Circuit Breaker Pattern (Lead ⭐⭐⭐⭐)

## İcmal
Circuit Breaker, bir service-in xətalı və ya yavaş cavab verən downstream service-ə təkrar-təkrar sorğu göndərməsinin qarşısını alan resilience pattern-dır. Elektrik sigortasından ilham alıb: xəta aşkarlayanda "circuit açılır", sorğular birbaşa fail edir (downstream-i gözləmədən), müddət sonra "yarı açıq" vəziyyətdə test edilir. Netflix Hystrix-i məşhur etdi, indi hər resilience library bu pattern-ı dəstəkləyir.

## Niyə Vacibdir
Distributed sistemdə cascade failure ən ciddi problemlərdən biridir: Service A → Service B (slow) → Service A thread-ləri tükənir → Service A da fails → Service C-ni çağıran da fails → bütün sistem çökür. Circuit breaker bu domino effektini dayandırır. Lead mühəndis state machine-i, failure threshold-u, half-open testing-i, fallback strategy-ni izah edə bilir.

## Əsas Anlayışlar

### 1. Circuit Breaker State Machine
```
         ┌────────────────────────────────┐
         │        CLOSED (normal)          │
         │  Requests → downstream         │
         │  Failure counter: track errors │
         └────────────┬───────────────────┘
                      │ Failure threshold exceeded
                      │ (e.g., 50% errors in 60s)
                      ▼
         ┌────────────────────────────────┐
         │         OPEN (broken)          │
         │  Requests → FAIL FAST          │
         │  No downstream calls           │
         │  Timer: 60 seconds             │
         └────────────┬───────────────────┘
                      │ Timer expires
                      ▼
         ┌────────────────────────────────┐
         │     HALF-OPEN (testing)        │
         │  Limited requests → downstream │
         │  Success? → CLOSED             │
         │  Failure? → OPEN again         │
         └────────────────────────────────┘
```

### 2. State Detalları

**CLOSED (normal vəziyyət):**
```
Hər request keçir
Sliding window: Son 100 request-in error rate-ini hesabla
Error rate > 50% threshold → OPEN keç
Slow call rate > 50% → OPEN keç (latency-based)
```

**OPEN (circuit açıqdır):**
```
Bütün sorğular dərhal fail edir (downstream call yoxdur)
Exception: CircuitBreakerOpenException
Client: Fallback response alır
Timer: 60 saniyə sonra HALF-OPEN-ə keç
Fayda: Downstream-ə recovery üçün vaxt verir
```

**HALF-OPEN (test vəziyyəti):**
```
N test request keçir (e.g., 5 request)
Əgər hamısı uğurlu → CLOSED (normal)
Əgər biri fail olur → OPEN (yenidən reset)
Çox sayda request keçmir → downstream-i yükləmir
```

### 3. Resilience4j (Java) Nümunəsi
```java
// Circuit Breaker konfigurasiya
CircuitBreakerConfig config = CircuitBreakerConfig.custom()
    .slidingWindowType(COUNT_BASED)    // ya TIME_BASED
    .slidingWindowSize(100)             // Son 100 call
    .failureRateThreshold(50)           // 50% xəta → OPEN
    .slowCallRateThreshold(50)          // 50% yavaş → OPEN
    .slowCallDurationThreshold(Duration.ofSeconds(2))
    .waitDurationInOpenState(Duration.ofSeconds(60))
    .permittedNumberOfCallsInHalfOpenState(5)
    .build();

CircuitBreaker cb = CircuitBreaker.of("payment-service", config);

// İstifadə
Try<String> result = Try.ofSupplier(
    CircuitBreaker.decorateSupplier(cb, () -> paymentService.charge(100))
).recover(CallNotPermittedException.class, ex -> "Fallback: Service unavailable");
```

### 4. Golang Circuit Breaker (gobreaker)
```go
var cb *gobreaker.CircuitBreaker

func init() {
    settings := gobreaker.Settings{
        Name:        "payment-service",
        MaxRequests: 5,        // half-open max requests
        Interval:    60 * time.Second,
        Timeout:     60 * time.Second,
        ReadyToTrip: func(counts gobreaker.Counts) bool {
            failureRatio := float64(counts.TotalFailures) / float64(counts.Requests)
            return counts.Requests >= 3 && failureRatio >= 0.5
        },
    }
    cb = gobreaker.NewCircuitBreaker(settings)
}

func callPaymentService(amount int) (string, error) {
    result, err := cb.Execute(func() (interface{}, error) {
        return paymentService.Charge(amount)
    })
    if err != nil {
        return "service_unavailable", err
    }
    return result.(string), nil
}
```

### 5. PHP Laravel nümunəsi (Guzzle + custom CB)
```php
class CircuitBreaker {
    private string $key;
    private int $threshold;
    private int $timeout;
    
    public function __construct(string $service, int $threshold = 5, int $timeout = 60) {
        $this->key = "cb:{$service}";
        $this->threshold = $threshold;
        $this->timeout = $timeout;
    }
    
    public function isOpen(): bool {
        $state = Redis::get($this->key . ':state');
        if ($state === 'OPEN') {
            $openedAt = Redis::get($this->key . ':opened_at');
            if (time() - $openedAt > $this->timeout) {
                Redis::set($this->key . ':state', 'HALF_OPEN');
                return false;  // Allow test request
            }
            return true;
        }
        return false;
    }
    
    public function recordSuccess(): void {
        Redis::del([$this->key . ':failures', $this->key . ':state']);
    }
    
    public function recordFailure(): void {
        $failures = Redis::incr($this->key . ':failures');
        Redis::expire($this->key . ':failures', 300);
        
        if ($failures >= $this->threshold) {
            Redis::set($this->key . ':state', 'OPEN');
            Redis::set($this->key . ':opened_at', time());
        }
    }
}
```

### 6. Fallback Strategies
```
Circuit OPEN → Fallback lazımdır:

1. Static fallback:
   "Service temporarily unavailable. Please try again."
   Use case: Non-critical service (recommendations)

2. Cache fallback:
   Cache-dən son known good data qaytar
   Use case: Product catalog, user profile
   "Showing cached results (may be outdated)"

3. Default value:
   recommendations = [] (boş list)
   tax_rate = 0.18 (default dəyər)
   Use case: Non-blocking supplementary data

4. Degraded experience:
   "Some features temporarily unavailable"
   Core feature-lər işləyir, supplementary deyil

5. Queue request:
   Request-i queue-a at, cavab sonra göndər
   Use case: Notifications, non-urgent writes

6. Fail fast to user:
   HTTP 503 Service Unavailable
   Retry-After header
   Use case: Critical service, no valid fallback
```

### 7. Cascade Failure Prevention
```
Scenario olmadan CB:
  User → A → B (slow, 30s timeout)
  A: 200 threads B-ni gözləyir
  A: Thread pool tükəndi → A fails
  User → C → A → C fails
  → Cascade failure

CB ilə:
  User → A → CB(B) → OPEN
  A: Dərhal CircuitOpenException alır
  A: Fallback response qaytarır
  A: Thread pool sağlam qalır
  User: "B feature unavailable" alır, amma A/C işləyir
```

### 8. Timeout + Retry + Circuit Breaker Birlikdə
```
Resilience4j kombinasiyası (layered):

Order: Timeout → Retry → Circuit Breaker → Fallback

Timeout: 2 saniyə (uzun gözləmə yoxdur)
Retry: 3 cəhd, exponential backoff (1s, 2s, 4s)
Circuit Breaker: 5 ardıcıl fail → OPEN
Fallback: Cache ya da default response

Exponential backoff with jitter:
  Attempt 1: wait 1s
  Attempt 2: wait 2s + random(0-1s)
  Attempt 3: wait 4s + random(0-2s)
  → Thundering herd qarşısı alınır
```

### 9. Distributed Circuit Breaker
```
Problem: Service A-nın 10 instance-ı var
  Instance 1: CB OPEN (B-ni 10 xəta gördü)
  Instance 2: CB CLOSED (hələ bilmir)
  
Hər instance öz local CB-ni idarə edir
Inconsistent state!

Həll 1: Centralized CB state (Redis)
  CB state Redis-də saxlanır
  Bütün instance-lar eyni state görür
  Redis overhead: hər request üçün lookup

Həll 2: Service Mesh (Istio)
  Envoy sidecar-ı CB-ni manage edir
  Application-dan şəffaf
  Centralized control plane
  
Həll 3: Accept inconsistency
  Hər instance local CB
  Eventually all instances will see failures
  Simple, acceptable for most use cases
```

### 10. Circuit Breaker Metrics
```
Monitor:
- State changes (CLOSED → OPEN → HALF-OPEN)
- Failure rate (current window)
- Slow call rate
- Number of calls in each state
- Time in OPEN state

Alerting:
- CB OPEN: PagerDuty alert (downstream failing)
- OPEN duration > 5 min: investigate
- Frequent state changes: flapping problem

Dashboard:
- CB state heatmap (hangi service ne durumda)
- Failure rate trend
- Fallback call rate
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Cascade failure nümunəsi ilə problemi motivasiya et
2. State machine-i izah et (3 vəziyyət)
3. Fallback strategiyasını müzakirə et
4. Timeout + Retry + CB kombinasiyasını qeyd et
5. Half-open testing-in niyə lazım olduğunu izah et

### Ümumi Namizəd Səhvləri
- "Timeout əlavə edirəm" demək, CB-i unutmaq
- Half-open vəziyyəti bilməmək
- Fallback strategiyasız CB dizayn etmək
- Distributed CB-nin inconsistency problemini bilməmək
- Retry storm + CB kombinasiyasını nəzərə almamaq (CB olmasa retry storm downstream-i öldürür)

### Senior vs Architect Fərği
**Senior**: CB konfigurasiya edir, fallback implement edir, metric-ləri izləyir.

**Architect**: Distributed CB strategiyasını planlaşdırır (local vs centralized), CB threshold-larını SLO/SLA ilə əlaqələndirir, service mesh-ə CB delegasiya edir, CB-nin bulkhead pattern ilə kombinasiyasını dizayn edir, adaptive thresholds (dinamik threshold sistemin load-una görə dəyişir), CB-nin monitoring-ünü incident response workflow-la inteqrasiya edir.

## Nümunələr

### Tipik Interview Sualı
"Design the resilience layer for a microservices system where payment service calls are failing intermittently."

### Güclü Cavab
```
Payment service resilience:

Problem:
- Payment Service: 30% xəta (3rd party processor intermittent issues)
- 10 service payment-ı call edir
- Timeout: 5s → thread exhaustion

Solution: CB + Timeout + Retry + Fallback

Layer 1: Timeout (2s)
  Connect timeout: 500ms
  Read timeout: 2s
  → Yavaş response-lar thread-ləri tutmaz

Layer 2: Retry (limited)
  Max attempts: 2 (initial + 1 retry)
  Retry only on: timeout, 5xx (not 4xx!)
  Backoff: 500ms (no jitter for payments)
  → Transient failures handle edilir

Layer 3: Circuit Breaker
  Window: Count-based, 20 calls
  Failure threshold: 50% (10 of 20 fails)
  Slow call threshold: 50% calls > 2s
  Wait duration: 60s (OPEN state)
  Half-open: 3 test calls

  CLOSED → OPEN triggers:
  - 10/20 requests fail
  - 10/20 requests > 2s

Layer 4: Fallback
  OPEN state fallback strategy:
  - Queue payment request (async, try later)
  - Show user: "Payment is being processed, check back in 5 minutes"
  - Email confirmation when processed
  - NOT: "Payment failed" (may cause user to retry manually = double charge)

Monitoring:
  - CB state: CLOSED/OPEN/HALF_OPEN → dashboard
  - Failure rate: real-time graph
  - OPEN event: immediate PagerDuty alert
  - Fallback count: business impact metric
  
Service Mesh option (Istio):
  Instead of library-based CB:
  DestinationRule:
    outlierDetection:
      consecutiveGatewayErrors: 5
      interval: 30s
      baseEjectionTime: 60s
      maxEjectionPercent: 100
  → Envoy sidecar manages CB transparently
```

### Circuit Breaker State Diagram
```
Normal Traffic:
  [Order Svc] ──────► [Payment CB: CLOSED] ──► [Payment Svc: OK]

Failure Detected:
  [Order Svc] ──────► [Payment CB: OPEN] ──► [Fallback Queue]
                                     └───────────────────────►(immediate fail fast)

Recovery Test:
  [Order Svc] ──5 test──► [Payment CB: HALF-OPEN] ──► [Payment Svc: OK?]
                                success → CLOSED
                                fail    → OPEN (reset timer)
```

## Praktik Tapşırıqlar
- Resilience4j ilə Spring Boot CB implement edin
- PHP-də Redis-based CB yazın
- Chaos engineering: Service-i mock fail edin, CB behavior müşahidə edin
- Cascade failure simulasiyası: 5 service, tək failure → hamısı uçur
- CB metric-lərini Grafana-da vizualizasiya edin

## Əlaqəli Mövzular
- [09-rate-limiting.md](09-rate-limiting.md) — Rate limiting ilə əlaqə
- [08-message-queues.md](08-message-queues.md) — Queue-based fallback
- [16-circuit-breaker.md] ← bu fayl
- [21-backpressure.md](21-backpressure.md) — Backpressure ilə kombinasiya
- [20-monitoring-observability.md](20-monitoring-observability.md) — CB metrics, alerting
