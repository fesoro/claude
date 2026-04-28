# Circuit Breaker (Senior ⭐⭐⭐)

## İcmal

Circuit Breaker — xarici servis çöküşündə fail-fast davranışı təmin edən resilience pattern-i. Elektrik sigortası kimi işləyir: normal halda sorğular keçir (Closed); çox xəta baş verdikdə dövrəni qırır (Open), geri sorğular göndərmir; bir müddət sonra test sorğusu göndərib (Half-Open) servisin düzəlib-düzəlmədiyini yoxlayır. Bu şəkildə bir servisin çöküşü bütün sistemi bloklamır.

## Niyə Vacibdir

Payment gateway timeout verməyə başlayır. CB olmadan: hər sorğu 30 saniyə gözləyir, thread pool tükənir, bütün sistem bloklanır — cascading failure. CB ilə: 5 uğursuzluqdan sonra dövrə açılır, geri sorğular dərhal fail edir (fast fail), sistem responsiveness-ini qoruyur, payment gateway recover olduqda CB özü bağlanır.

## Əsas Anlayışlar

- **Closed (Normal)**: sorğular keçir; uğursuzluqlar sayılır; threshold aşılsa → Open
- **Open (Fail-fast)**: sorğular dərhal reject edilir (xarici servisə getmir); timeout sonra → Half-Open
- **Half-Open (Sınaq)**: bir test sorğusu göndərilir; uğurludursa → Closed; uğursuzsa → Open
- **Failure threshold**: neçə ardıcıl xəta CB-ni açır (məs: 5 xəta)
- **Recovery timeout**: Open vəziyyətdə neçə saniyə gözlənilir (məs: 30 saniyə)
- **State store**: Redis — distributed sistem üçün shared state; FPM multi-process-də in-memory olmaz

## Praktik Baxış

- **Real istifadə**: payment gateway, SMS/email provider, third-party API, microservice arası çağrılar, DB connection (yazma tərəfi üçün)
- **Trade-off-lar**: cascading failure önlənir; sistem responsiveness qorunur; lakin false positive (geçici yüksək latency CB-ni açır); state store (Redis) SPOF ola bilər; monitoring olmadan CB-nin nə vaxt açıldığı bilinmir
- **İstifadə etməmək**: idempotent olmayan əməliyyatlar üçün diqqətli ol (CB açıq ikən sorğu getmiyibsə, nə etmək lazım?); yalnız HTTP üçün deyil — DB, queue, file system üçün də lazım ola bilər
- **Common mistakes**: threshold-u çox aşağı qoymaq (normal spike-da açılır); recovery timeout çox qısa (hər 5 saniyədə test sorğusu göndərir, servis hələ hazır deyil); CB-ni yalnız HTTP üçün istifadə etmək

## Anti-Pattern Nə Zaman Olur?

**CB threshold-unu çox aşağı/yuxarı qoymaq:**
Threshold=1 — bir xəta bütün sistemi dayandırır; threshold=1000 — cascading failure baş verənə qədər CB açılmır. Production load-u analiz edib məqsədəuyğun threshold seç: normal traffic-də false positive olmamalı, real problem-də tez açılmalı. Tip: 5–20 ardıcıl xəta, ya da 50% error rate 10 sorğu üzərindən.

**CB-ni yalnız HTTP üçün istifadə etmək:**
DB slave host çöküb, Redis timeout verir, external file storage cavab vermir — bunlar üçün də CB lazımdır. HTTP-dən başqa hər dependency-ə CB tətbiq edin. `DatabaseCircuitBreaker`, `RedisCircuitBreaker` ayrı-ayrı state saxlasın.

**State store olmadan multi-process mühitdə:**
PHP FPM: hər request ayrı process-dir. In-memory CB state FPM-də mənasızdır — hər process öz state-ini saxlayır, CB-nin açıldığından digər process-lər xəbərdar olmur. Redis-də shared state mütləq lazımdır.

**CB açıq ikən fallback olmadan:**
CB açıldıqda sorğu reject edilir — client nə görür? Error 500? Boş cavab? CB pattern-i fallback strategiyası ilə birgə: cached data, default response, queue-ya at, user-ə "bir az sonra yenidən cəhd edin" mesajı.

## Nümunələr

### Ümumi Nümunə

```
Circuit Breaker State Machine:

   5 xəta     Recovery timeout    Uğurlu test
Closed ──────► Open ─────────────► Half-Open ──► Closed
  ▲                                    │
  │                                    │ Uğursuz test
  └────────────────────────────────────┘
                                       ▼
                                      Open

Closed: sorğular keçir, xətalar sayılır
Open:   sorğular dərhal reject edilir (30s)
Half-Open: 1 test sorğusu; uğurlu → Closed; uğursuz → Open
```

### PHP/Laravel Nümunəsi

```php
<?php

// Redis-based Circuit Breaker — PHP FPM multi-process üçün shared state
class RedisCircuitBreaker
{
    private const STATE_CLOSED    = 'closed';
    private const STATE_OPEN      = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private \Illuminate\Redis\Connections\Connection $redis,
        private string $name,
        private int $failureThreshold  = 5,    // neçə xəta CB-ni açır
        private int $recoveryTimeoutSec = 30,  // Open-da neçə saniyə gözlə
        private int $successThreshold  = 2,    // Half-Open-da neçə uğur Closed edir
    ) {}

    public function call(callable $fn): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            // Fast fail — xarici servisə getmir
            throw new CircuitBreakerOpenException(
                "Circuit Breaker '{$this->name}' açıqdır. Servis müvəqqəti əlçatmazdır."
            );
        }

        if ($state === self::STATE_HALF_OPEN) {
            return $this->tryHalfOpen($fn);
        }

        // STATE_CLOSED — normal axın
        return $this->tryCall($fn);
    }

    private function tryCall(callable $fn): mixed
    {
        try {
            $result = $fn();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    private function tryHalfOpen(callable $fn): mixed
    {
        try {
            $result = $fn();
            $this->recordHalfOpenSuccess();
            return $result;
        } catch (\Exception $e) {
            // Test sorğusu uğursuz — geri Open-a qayıt
            $this->trip();
            throw $e;
        }
    }

    private function recordFailure(): void
    {
        $key          = "cb:{$this->name}:failures";
        $failures     = $this->redis->incr($key);
        $this->redis->expire($key, $this->recoveryTimeoutSec * 2);

        if ($failures >= $this->failureThreshold) {
            $this->trip();
        }
    }

    private function recordSuccess(): void
    {
        $this->redis->del("cb:{$this->name}:failures");
    }

    private function recordHalfOpenSuccess(): void
    {
        $key     = "cb:{$this->name}:successes";
        $successes = $this->redis->incr($key);

        if ($successes >= $this->successThreshold) {
            // Kifayət qədər uğur → Closed-a qayıt
            $this->redis->del("cb:{$this->name}:state");
            $this->redis->del("cb:{$this->name}:failures");
            $this->redis->del("cb:{$this->name}:successes");
            \Log::info("Circuit Breaker '{$this->name}' bağlandı (Closed).");
        }
    }

    private function trip(): void
    {
        $this->redis->setex(
            "cb:{$this->name}:state",
            $this->recoveryTimeoutSec,
            self::STATE_OPEN
        );
        \Log::warning("Circuit Breaker '{$this->name}' açıldı (Open). {$this->recoveryTimeoutSec}s sonra Half-Open.");
    }

    private function getState(): string
    {
        $stateKey = "cb:{$this->name}:state";
        $state    = $this->redis->get($stateKey);

        if ($state === null) {
            // Key yoxdur ya da expire olub → Closed (ya da Half-Open sınağı)
            $failures = (int) ($this->redis->get("cb:{$this->name}:failures") ?? 0);
            return $failures >= $this->failureThreshold
                ? self::STATE_HALF_OPEN  // Open timeout bitdi, sınaq vaxtı
                : self::STATE_CLOSED;
        }

        return $state;
    }
}
```

```php
<?php

// Payment gateway-i CB ilə wrap etmək
class PaymentService
{
    private RedisCircuitBreaker $cb;

    public function __construct()
    {
        $this->cb = new RedisCircuitBreaker(
            redis:              \Redis::connection(),
            name:               'payment-gateway',
            failureThreshold:   5,
            recoveryTimeoutSec: 30,
        );
    }

    public function charge(array $data): array
    {
        try {
            return $this->cb->call(function () use ($data) {
                return \Http::timeout(10)
                    ->post(config('payment.url') . '/charge', $data)
                    ->throw()
                    ->json();
            });
        } catch (CircuitBreakerOpenException $e) {
            // CB açıqdır — fallback strategiyası
            // Seçim 1: queue-ya at, sonra işlə
            dispatch(new RetryPaymentJob($data))->delay(now()->addMinutes(1));
            throw new PaymentDeferredException('Ödəniş sistemlə bağlantı müvəqqəti kəsilibdir.');

            // Seçim 2: cached nəticəni qaytar (read-only kontekstdə)
            // return Cache::get("payment:last_result:{$data['order_id']}");
        } catch (\Exception $e) {
            throw $e;  // CB xəta sayacağı üçün yenidən at
        }
    }
}
```

```php
<?php

// Laravel Http::retry() — sadə transient failure üçün
// CB-dən fərqi: Http::retry() ardıcıl xəta saymır, hər sorğu üçün ayrı retry edir
// CB: state saxlayır, sistematik problem-lərdə fast fail edir

$response = \Http::retry(3, 100, function (\Exception $e) {
    // Yalnız connection xətalarında retry et, validation xətasında deyil
    return $e instanceof \Illuminate\Http\Client\ConnectionException;
})->post('https://payment.api/charge', $data);
```

```php
<?php

// Health check endpoint — CB status-u monitorinq üçün
// routes/api.php
Route::get('/health/circuit-breakers', function () {
    $checks = [
        'payment-gateway' => app(RedisCircuitBreaker::class, ['name' => 'payment-gateway'])->getState(),
        'sms-provider'    => app(RedisCircuitBreaker::class, ['name' => 'sms-provider'])->getState(),
        'email-service'   => app(RedisCircuitBreaker::class, ['name' => 'email-service'])->getState(),
    ];

    $allHealthy = collect($checks)->every(fn($state) => $state === 'closed');

    return response()->json([
        'status'         => $allHealthy ? 'healthy' : 'degraded',
        'circuit_breakers' => $checks,
    ], $allHealthy ? 200 : 503);
});
```

## Praktik Tapşırıqlar

1. `RedisCircuitBreaker` class yazın: 3 state (Closed/Open/Half-Open); Redis-də state saxlayın; test: 5 ardıcıl xəta → Open; 30s sonra Half-Open; 2 uğurlu sorğu → Closed
2. Payment service-i CB ilə wrap edin: xarici gateway timeout verəndə CB açılsın; CB açıq ikən sorğular fast fail etsin; recovery sonra avtomatik bağlansın
3. Health check endpoint yazın: bütün CB-lərin state-ini göstərsin; `/health/circuit-breakers` → `{"payment": "open", "sms": "closed"}`; monitoring sistemini bu endpoint-ə point edin
4. `spatie/laravel-circuit-breaker` paketi quraşdırın; `CircuitBreaker::attempt('payment', ...)` ilə payment gateway-i wrap edin; package-in konfiqurasiyasını öyrənin; custom exception handling əlavə edin

## Əlaqəli Mövzular

- [Retry Pattern](17-retry-pattern.md) — CB + Retry birlikdə; transient failure-da retry, sistematik problem-də CB
- [Bulkhead Pattern](07-bulkhead-pattern.md) — CB + Bulkhead = tam resilience; CB xətalı servisi, Bulkhead resource-u qoruyur
- [BFF Pattern](09-bff-pattern.md) — BFF-dən downstream çağrılarında CB tətbiq edilir
- [Ambassador Pattern](08-anti-corruption-layer.md) — Ambassador outbound proxy-dir; CB Ambassador içindədir
- [Saga Pattern](03-saga-pattern.md) — saga addımlarında CB; servis down olsa saga fast fail edir
