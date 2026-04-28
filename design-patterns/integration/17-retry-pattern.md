# Retry Pattern (Middle ⭐⭐)

## İcmal

Retry Pattern — transient failure-larda (müvəqqəti şəbəkə kəsilməsi, rate limit, timeout) avtomatik olaraq əməliyyatı yenidən cəhd etmək strategiyasıdır. Bütün xətalarda retry etmək yanlışdır; yalnız geçici xətalarda, exponential backoff + jitter ilə retry etmək düzgündur. Idempotency olmadan retry iki ödənişə, iki emailə səbəb ola bilər.

## Niyə Vacibdir

SMS API rate limit verdi (429) — bir saniyə gözləyib yenidən cəhd etmək əvəzinə `Exception` atmaq müştərini bildirimsiz buraxır. DB connection momentary drop etdi — 100ms sonra avtomatik retry bağlantını bərpa edir. Amma retry-siz hər transient failure user-ə error kimi görünür. Düzgün retry strategiyası sistemin "self-healing" qabiliyyəti deməkdir.

## Əsas Anlayışlar

- **Transient failure**: müvəqqəti xəta — rate limit (429), network timeout, connection reset, geçici unavailability; retry etmək mənalıdır
- **Permanent failure**: sabit xəta — validation (400), not found (404), authentication (401); retry etmək mənasızdır
- **Linear backoff**: hər retry arası sabit fasilə (1s, 1s, 1s) — rate limit-ə tez çatır
- **Exponential backoff**: fasilə ikiqat artır (1s, 2s, 4s, 8s) — yükü azaldır
- **Jitter**: backoff-a random fasilə əlavə et — "thundering herd" önlər (eyni anda minlərlə client retry etmir)
- **Idempotency key**: eyni əməliyyat ikinci dəfə göndərildikdə provider dublikat yaratmasın

## Praktik Baxış

- **Real istifadə**: HTTP API çağrıları (rate limit, timeout), DB connection retry, queue message redelivery, Laravel job retry, email/SMS provider çağrıları
- **Trade-off-lar**: transient failure-da availability artır; lakin latency artır (retry gözlənilir); yanlış retry → retry storm; server artıq yüklüdürsə retry yükü artırır
- **İstifadə etməmək**: validation, authentication, business logic xətaları üçün (retry dəyişmir); idempotent olmayan əməliyyatlar üçün idempotency key olmadan; başqa client-ə görə rate limit keçildisə
- **Common mistakes**: bütün xətalarda retry etmək; backoff olmadan retry (tight loop); retry sayını çox artırmaq (sonunda timeout yetir)

## Anti-Pattern Nə Zaman Olur?

**Infinite retry — sistem dayanmır:**
`while (true) retry()` — xəta düzəlmirsə sonsuz retry, resurslar israf olunur. Maksimum retry sayı (3–5) mütləq olmalıdır; son cəhddən sonra exception at ya da fallback strategiyası tətbiq et.

**Retry etmənin mənasız olduğu halda retry — validation xətası:**
`POST /orders` → 422 Unprocessable Entity (məs: email formatı yanlış) — retry eyni xəta qaytaracaq. Yalnız transient xətaları (429, 503, connection timeout) retry et; business logic, validation xətalarında retry etmə.

**Backoff olmadan retry — retry storm:**
Servis 10,000 client-ə cavab verə bilmir. Hər client dərhal retry edir — server 20,000 sorğu alır. Jitter + exponential backoff olmadan retry yükü artırır. AWS-in Jitter blog post-u bu problemi "thundering herd" adlandırır.

**Idempotency olmadan retry — duplicate əməliyyat:**
Payment API-yə charge sorğusu göndərilir, timeout verir. Retry edilir — API birinci sorğunu işlətmişdi, ikinci gelince ikinci charge edir. Idempotency key (`X-Idempotency-Key` header) ilə provider dublikatı rədd edir.

## Nümunələr

### Ümumi Nümunə

```
Exponential Backoff + Jitter:

Cəhd 1: dərhal                → Xəta
Cəhd 2: 1s + random(0-0.5s)  → Xəta
Cəhd 3: 2s + random(0-1s)    → Xəta
Cəhd 4: 4s + random(0-2s)    → Uğur ✅

Jitter niyə lazımdır:
  Jitter olmadan: 1000 client eyni anda retry → thundering herd
  Jitter ilə:     1000 client random vaxtlarda retry → yük paylanır
```

### PHP/Laravel Nümunəsi

```php
<?php

// Laravel Job retry — built-in
class SendSmsJob implements ShouldQueue
{
    // Maksimum cəhd sayı
    public int $tries = 5;

    // Backoff strategiyası (saniyə) — exponential
    public function backoff(): array
    {
        return [1, 5, 10, 30, 60];  // [1s, 5s, 10s, 30s, 60s]
    }

    // Bu vaxtdan sonra artıq retry etmə
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    public function handle(SmsService $sms): void
    {
        try {
            $sms->send($this->phoneNumber, $this->message);
        } catch (RateLimitException $e) {
            // Rate limit — retry et (release job, delay ilə)
            $this->release(60);  // 60 saniyə sonra yenidən cəhd
        } catch (ValidationException $e) {
            // Validation xətası — retry etmə, işarələ
            $this->fail($e);
        }
    }
}
```

```php
<?php

// Laravel HTTP client retry
$response = \Http::retry(
    times: 3,
    sleepMilliseconds: 100,  // başlanğıc interval
    when: function (\Exception $e, \Illuminate\Http\Client\Response $response) {
        // Yalnız bu hallarda retry et
        return $response->status() === 429   // Rate limit
            || $response->status() === 503   // Service unavailable
            || $e instanceof \Illuminate\Http\Client\ConnectionException; // Network error
    },
    throw: true
)->post('https://sms.api/send', $data);

// throw: true → 3 cəhddən sonra hələ xəta varsa exception at
```

```php
<?php

// Custom RetryService — exponential backoff + jitter
class RetryService
{
    /**
     * @param callable $fn          — cəhd ediləcək əməliyyat
     * @param int      $maxAttempts — maksimum cəhd sayı
     * @param int      $baseDelayMs — başlanğıc fasilə (ms)
     * @param array    $retryOn     — bu exception class-larında retry et
     */
    public function execute(
        callable $fn,
        int $maxAttempts = 3,
        int $baseDelayMs = 100,
        array $retryOn = [\Exception::class],
    ): mixed {
        $attempt = 0;

        while (true) {
            try {
                return $fn();
            } catch (\Exception $e) {
                $attempt++;

                // Bu exception tipi retry edilirmi?
                $shouldRetry = collect($retryOn)->some(fn($class) => $e instanceof $class);

                if (!$shouldRetry || $attempt >= $maxAttempts) {
                    throw $e;
                }

                // Exponential backoff + jitter
                $delay = $this->calculateDelay($attempt, $baseDelayMs);
                usleep($delay * 1000);  // ms → microseconds

                \Log::warning("Retry cəhd {$attempt}/{$maxAttempts}", [
                    'exception' => $e->getMessage(),
                    'delay_ms'  => $delay,
                ]);
            }
        }
    }

    private function calculateDelay(int $attempt, int $baseDelayMs): int
    {
        // Exponential: base * 2^(attempt-1)
        $exponential = $baseDelayMs * (2 ** ($attempt - 1));

        // Jitter: ±50% random
        $jitter = random_int(
            (int) (-$exponential * 0.5),
            (int) ($exponential * 0.5)
        );

        return max(0, $exponential + $jitter);
    }
}

// İstifadə
class PaymentService
{
    public function __construct(private RetryService $retry) {}

    public function charge(array $data): array
    {
        return $this->retry->execute(
            fn: function () use ($data) {
                return \Http::timeout(10)
                    ->withHeader('X-Idempotency-Key', $data['idempotency_key'])
                    ->post(config('payment.url') . '/charge', $data)
                    ->throw()
                    ->json();
            },
            maxAttempts: 3,
            baseDelayMs: 200,
            retryOn: [
                \Illuminate\Http\Client\ConnectionException::class,  // Network xətası
                RateLimitException::class,                           // 429
            ]
        );
    }
}
```

```php
<?php

// Idempotency key — duplicate charge önləmək
class OrderPaymentService
{
    public function chargeForOrder(string $orderId, int $amountCents): PaymentResult
    {
        // WHY: idempotency key = order-a bağlıdır; eyni order iki dəfə charge edilsə
        // API ikinci sorğunu rədd edir (ya da eyni cavabı qaytarır)
        $idempotencyKey = "order-charge-{$orderId}";

        try {
            $result = $this->retry->execute(function () use ($data, $idempotencyKey) {
                return \Http::withHeader('X-Idempotency-Key', $idempotencyKey)
                    ->post(config('payment.url') . '/charges', [
                        'amount'   => $amountCents,
                        'currency' => 'USD',
                        'order_id' => $orderId,
                    ])
                    ->throw()
                    ->json();
            });

            return new PaymentResult(success: true, transactionId: $result['id']);

        } catch (\Exception $e) {
            \Log::error("Charge uğursuz", ['order_id' => $orderId, 'error' => $e->getMessage()]);
            throw new PaymentFailedException("Ödəniş {$orderId} üçün alınmadı");
        }
    }
}
```

```php
<?php

// Guzzle Retry Middleware — daha çevik konfiqurasiya
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function createRetryMiddleware(): callable
{
    return Middleware::retry(
        decider: function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Exception $exception = null
        ): bool {
            // Maksimum 3 cəhd
            if ($retries >= 3) {
                return false;
            }

            // Server xətaları və connection xətaları
            if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                return true;
            }

            if ($response && in_array($response->getStatusCode(), [429, 502, 503, 504])) {
                return true;
            }

            return false;
        },
        delay: function (int $retries, ResponseInterface $response): int {
            // Rate limit header-dan delay oxu
            if ($response->hasHeader('Retry-After')) {
                return (int) $response->getHeaderLine('Retry-After') * 1000;
            }

            // Exponential backoff + jitter (ms)
            return (int) (100 * (2 ** $retries) + random_int(0, 100));
        }
    );
}

$stack = HandlerStack::create();
$stack->push(createRetryMiddleware());

$client = new Client(['handler' => $stack]);
```

## Praktik Tapşırıqlar

1. `RetryService` class yazın: exponential backoff + jitter; `maxAttempts`, `baseDelayMs`, `retryOn` parametrləri; test: 3 cəhd sonra uğur; 3 cəhd sonra uğursuzluq → exception
2. Laravel `Http::retry()` ilə SMS API çağrısını yazın: 429 → 60s gözlə; 503 → 5s gözlə; 400 → retry etmə; test: mock server 2 dəfə 503 qaytarır, 3-cüdə 200 qaytarır
3. Laravel job retry konfiqurasyonu: `$tries = 5`, `backoff() → [1, 5, 15, 60, 180]`, `retryUntil() → 24 saat`; `RateLimitException`-da `release(60)`, `ValidationException`-da `fail()`; test
4. Idempotency key əlavə edin: `PaymentService.charge()` — `X-Idempotency-Key: order-{orderId}`; test: eyni order ID ilə 3 retry — API bir dəfə charge edir (mock server idempotency key-i tanısın)

## Əlaqəli Mövzular

- [Circuit Breaker](16-circuit-breaker.md) — CB + Retry: transient failure-da retry, sistematik problem-də CB fast fail
- [Bulkhead Pattern](07-bulkhead-pattern.md) — retry storm bulkhead-i doldurmasın deyə koordinasiya lazımdır
- [Outbox Pattern](04-outbox-pattern.md) — outbox relay-i retry edir; idempotency vacibdir
- [Saga Pattern](03-saga-pattern.md) — saga addımları idempotent olmalı ki, retry etmək mümkün olsun
- [Throttling / Rate Limiting](18-throttling-rate-limiting.md) — retry 429 alırsa rate limit keçilmişdir
