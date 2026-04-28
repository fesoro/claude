# Rate Limits, Retries və Backoff — Laravel Production (Senior)

> Hədəf auditoriyası: Production-da Anthropic API istifadə edən Laravel developerlər. Bu sənəd rate limit-lərin nə olduğunu, 429 xətalarının necə idarə edildiyini və production-ready resilient servis qurmağın tam planını təqdim edir.

---

## Mündəricat

1. [Rate Limit-lər Nə Üçün Mövcuddur](#niyə-rate-limit)
2. [Anthropic-in Rate Limit Strukturu](#anthropic-limits)
3. [429 və Digər Xətalar](#xətalar)
4. [Exponential Backoff + Jitter](#backoff)
5. [Retry Strategiyası](#retry-strategy)
6. [Circuit Breaker Pattern](#circuit-breaker)
7. [Laravel Queue-based Throttling](#queue-throttling)
8. [RateLimited Middleware](#rate-limited-middleware)
9. [ThrottlesExceptions](#throttles-exceptions)
10. [Bus::chain və Sequential Jobs](#bus-chain)
11. [Horizon Supervisor Konfiqurasiyası](#horizon)
12. [Per-Tenant Quota](#per-tenant)
13. [Dead Letter Queue](#dlq)
14. [Observability](#observability)
15. [Resilient ClaudeService Tam Kod](#claude-service)
16. [Production Checklist](#checklist)

---

## Niyə Rate Limit

Anthropic (və bütün LLM provider-ləri) rate limit tətbiq edir ki:

```
1. Model server-lərini qoru   → GPU capacity məhduddur
2. Abuse-u dayandır           → bir client-in sistem çöküşünə səbəb olması
3. Capacity paylaşdır         → bütün müştərilər üçün fair share
4. Predictable cost           → müştərinin təsadüfən $50k xərcləməsini blokla
```

Rate limit tətbiq edilməsə, tək bir loop buraxılmış client saatda milyonlarla sorğu göndərə bilər və sistem öz ayağını kəsər. Senior developer kimi rate limit-i düşmən deyil, dost kimi qəbul etmək lazımdır.

### Real Dünyada Nə Baş Verir

```
Günün saat 14:00-ı, Black Friday. Müştəri chat bot-u Laravel-də işləyir.
Hər istifadəçi sorğusu Claude API-yə gedir.

Normal trafik: 100 sorğu/dəqiqə.
Black Friday: 5000 sorğu/dəqiqə.

Rate limit olmasa:
  - İlk dəqiqə: 5000 sorğu, hamısı keçir
  - Cost: $50+ dəqiqədə
  - API response ləngiyir (queue məşğul)
  - Xidmət kaskad şəkildə çökür

Rate limit varsa:
  - 4000 sorğu keçir, 1000-i queue-ya düşür
  - Queue-da exponential backoff ilə retry
  - Cost kontrol altında
  - Xidmət stabil
```

---

## Anthropic Limits

Anthropic bir neçə rate limit ölçüsü izləyir:

```
RPM  — Requests Per Minute        (sorğu sayı)
ITPM — Input Tokens Per Minute    (input token həcmi)
OTPM — Output Tokens Per Minute   (output token həcmi)
TPD  — Tokens Per Day             (daily cap)
```

### Typical Tier-lər (2026-04)

| Tier | RPM | ITPM | OTPM | Şərt |
|------|-----|------|------|------|
| Free/Build | 50 | 50k | 10k | Yeni hesab |
| Build Tier 1 | 1k | 100k | 20k | $5 ilk depozit |
| Build Tier 2 | 2k | 200k | 40k | $40 xərclənmiş |
| Build Tier 3 | 4k | 400k | 80k | $200 xərclənmiş |
| Build Tier 4 | 5k | 800k | 160k | $400 xərclənmiş |
| Scale | custom | custom | custom | Sales ilə |

### Rate Limit Response Header-ləri

Hər sorğudan sonra API header-lərdə limit statusunu qaytarır:

```
anthropic-ratelimit-requests-limit: 4000
anthropic-ratelimit-requests-remaining: 3991
anthropic-ratelimit-requests-reset: 2026-04-21T14:32:15Z

anthropic-ratelimit-input-tokens-limit: 400000
anthropic-ratelimit-input-tokens-remaining: 398543
anthropic-ratelimit-input-tokens-reset: 2026-04-21T14:32:00Z

anthropic-ratelimit-output-tokens-limit: 80000
anthropic-ratelimit-output-tokens-remaining: 79998
anthropic-ratelimit-output-tokens-reset: 2026-04-21T14:32:00Z
```

Bu header-ləri mütləq oxuyub metrikalara yaz. Limit yaxınlaşdığını bilmək istəyirsən — çatmamış, yox.

### Concurrent Requests

RPM-dən ayrı, concurrent request limiti də var — eyni vaxtda açıq olan sorğu sayı. Uzun streaming sessiyalarında bu önəmlidir.

```
Tier 3: ~100 concurrent connection
Tier 4: ~200 concurrent connection
```

---

## Xətalar

Anthropic API müxtəlif HTTP status kod qaytarır:

| Status | Mənası | Retry? |
|--------|--------|--------|
| 200 | OK | - |
| 400 | Invalid request (bad prompt) | YOX |
| 401 | Invalid API key | YOX |
| 403 | Permission denied | YOX |
| 404 | Not found | YOX |
| 413 | Request too large | YOX (prompt kəs) |
| 429 | Rate limit | HƏ (backoff ilə) |
| 500 | Server error | HƏ |
| 502 | Bad gateway | HƏ |
| 503 | Overloaded | HƏ (uzun backoff) |
| 504 | Gateway timeout | HƏ |
| 529 | Overloaded (custom) | HƏ |

### 429 Response Nümunəsi

```json
{
  "type": "error",
  "error": {
    "type": "rate_limit_error",
    "message": "Number of request tokens has exceeded your per-minute rate limit"
  }
}
```

Header-də `retry-after` də gəlir:

```
retry-after: 45   (saniyə)
```

Bu dəyəri riayət et — API bunu əbəs yerə göndərmir.

---

## Backoff

Rate limit vurduqda dərhal yenidən cəhd etmək — yanlışdır. **Exponential backoff** istifadə olunur:

```
cəhd 1: 1s gözlə
cəhd 2: 2s gözlə
cəhd 3: 4s gözlə
cəhd 4: 8s gözlə
cəhd 5: 16s gözlə
max gözləmə: 60s
```

### Niyə Jitter

Əgər 1000 client eyni vaxtda rate limit vurub eyni backoff ilə retry edirsə — hamısı eyni anda qayıdır. Bu, **thundering herd** problemidir.

Həll: hər retry-da təsadüfi "jitter" əlavə et:

```
backoff_seconds = base * (2 ^ attempt) + random(0, 1)

cəhd 1: 1 + random(0, 1) = 1.3s
cəhd 2: 2 + random(0, 1) = 2.7s
cəhd 3: 4 + random(0, 1) = 4.4s
```

Jitter 1000 client-in geri gəlmə vaxtını spread edir — server tez bərpa olur.

### Full Jitter vs Equal Jitter

```
Full jitter:   sleep = random(0, base * 2^attempt)
Equal jitter:  sleep = base * 2^attempt / 2 + random(0, base * 2^attempt / 2)
Decorrelated: sleep = min(cap, random(base, previous_sleep * 3))
```

Praktikada **decorrelated jitter** ən yaxşı nəticə verir AWS-in araşdırmasına görə.

### PHP Implementasiyası

```php
<?php

namespace App\Services\AI\Support;

class BackoffCalculator
{
    public function __construct(
        private readonly int $baseMs = 500,
        private readonly int $maxMs = 60_000,
    ) {}

    public function exponential(int $attempt): int
    {
        $exponential = $this->baseMs * (2 ** $attempt);
        $jitter = random_int(0, $this->baseMs);
        return min($exponential + $jitter, $this->maxMs);
    }

    public function decorrelated(int $previousMs): int
    {
        $next = random_int($this->baseMs, $previousMs * 3);
        return min($next, $this->maxMs);
    }

    public function fromRetryAfter(string $headerValue): int
    {
        if (is_numeric($headerValue)) {
            return ((int) $headerValue) * 1000;
        }

        $timestamp = strtotime($headerValue);
        if ($timestamp !== false) {
            return max(0, ($timestamp - time()) * 1000);
        }

        return $this->baseMs;
    }
}
```

---

## Retry Strategy

Retry qaydaları:

```
1. Yalnız idempotent əməliyyatları retry et
   → Claude API POST /v1/messages idempotent deyil (charge olunur),
     amma message göndərmə side-effect yaratmır, ona görə retry təhlükəsizdir

2. Transient xətaları retry et: 429, 500, 502, 503, 504, 529
   → timeout, connection error də daxildir

3. Permanent xətaları retry ETMƏ: 400, 401, 403, 404, 413
   → prompt səhvdirsə retry məsələni həll etməz

4. Max retry qoy: 5-7
   → 7-dən sonra ümumi gözləmə ~2 dəqiqə, mənalıdır dayanmaq

5. Total timeout qoy: 3 dəqiqə
   → user gözləmir

6. Idempotency key istifadə et (varsa)
   → eyni retry duplicate processing yaratmasın
```

### Retry Decision Tree

```
                    Exception baş verdi
                          │
                          ▼
                   HTTP response var?
                    ┌─────┴─────┐
                    │           │
                  Yox           Hə
                    │           │
                    ▼           ▼
              Network/timeout  Status kod?
              RETRY           │
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
          4xx (400-413)    429             5xx (500-504)
          ABORT            RETRY           RETRY
                           (retry-after)   (exp backoff)
```

---

## Circuit Breaker

Circuit breaker — API tamamilə çökdükdə, lazımsız retry-ları dayandırır:

```
States:
  CLOSED    — hər şey normaldır, sorğular keçir
  OPEN      — çox səhv, heç bir sorğu göndərmir
  HALF_OPEN — test sorğuları göndərir, uğurlu olsa CLOSED-ə qayıt

Transitions:
  CLOSED → OPEN: 
    son 60s-də error rate > 50% və total sorğu > 10

  OPEN → HALF_OPEN:
    30s keçdi

  HALF_OPEN → CLOSED:
    test sorğu uğurlu

  HALF_OPEN → OPEN:
    test sorğu uğursuz
```

### Niyə Circuit Breaker

Anthropic saatlarla down olsa:
- Circuit breaker yoxdur → hər client öz retry-larını edir → rate limit vurur → daha çox retry → cascade failure
- Circuit breaker var → ilk dəqiqədən sonra bütün sorğular dərhal uğursuz → client cache-dən/fallback-dən cavab verir → sistem stabil

### Laravel Circuit Breaker Implementasiyası

```php
<?php

namespace App\Services\AI\Support;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public function __construct(
        private readonly string $name,
        private readonly int $failureThreshold = 10,
        private readonly float $failureRatio = 0.5,
        private readonly int $windowSeconds = 60,
        private readonly int $openSeconds = 30,
    ) {}

    public function allow(): bool
    {
        $state = $this->state();
        
        if ($state === 'open') {
            if ($this->shouldTryHalfOpen()) {
                $this->setState('half_open');
                return true;
            }
            return false;
        }

        return true;  // closed or half_open
    }

    public function recordSuccess(): void
    {
        if ($this->state() === 'half_open') {
            $this->setState('closed');
            $this->resetCounters();
        }

        $this->increment('success');
    }

    public function recordFailure(): void
    {
        if ($this->state() === 'half_open') {
            $this->setState('open');
            Cache::put($this->key('opened_at'), time(), $this->openSeconds);
            return;
        }

        $this->increment('failure');
        $this->evaluateThreshold();
    }

    private function evaluateThreshold(): void
    {
        $failures = (int) Cache::get($this->key('failure'), 0);
        $successes = (int) Cache::get($this->key('success'), 0);
        $total = $failures + $successes;

        if ($total < $this->failureThreshold) {
            return;
        }

        if (($failures / $total) >= $this->failureRatio) {
            $this->setState('open');
            Cache::put($this->key('opened_at'), time(), $this->openSeconds);
        }
    }

    private function state(): string
    {
        return (string) Cache::get($this->key('state'), 'closed');
    }

    private function setState(string $state): void
    {
        Cache::put($this->key('state'), $state, $this->windowSeconds);
    }

    private function shouldTryHalfOpen(): bool
    {
        $openedAt = (int) Cache::get($this->key('opened_at'), 0);
        return (time() - $openedAt) >= $this->openSeconds;
    }

    private function increment(string $type): void
    {
        Cache::add($this->key($type), 0, $this->windowSeconds);
        Cache::increment($this->key($type));
    }

    private function resetCounters(): void
    {
        Cache::forget($this->key('success'));
        Cache::forget($this->key('failure'));
    }

    private function key(string $suffix): string
    {
        return "circuit_breaker.{$this->name}.{$suffix}";
    }
}
```

### İstifadə

```php
$breaker = new CircuitBreaker('anthropic-api');

if (!$breaker->allow()) {
    throw new ServiceUnavailableException('AI temporarily unavailable');
}

try {
    $response = $client->messages()->create([...]);
    $breaker->recordSuccess();
    return $response;
} catch (\Throwable $e) {
    $breaker->recordFailure();
    throw $e;
}
```

---

## Queue Throttling

Laravel queue-da rate limit tətbiq etmək üçün bir neçə yanaşma var:

### Yanaşma 1: Redis-based Rate Limiter

```php
use Illuminate\Support\Facades\Redis;

Redis::throttle('anthropic-api')
    ->allow(1000)     // 1000 sorğu
    ->every(60)       // 60 saniyədə
    ->then(function () {
        // sorğunu göndər
    }, function () {
        // limit vurulub — retry üçün yenidən queue-ya qoy
        $this->release(10);
    });
```

### Yanaşma 2: Lua-based Token Bucket

```php
<?php

namespace App\Services\AI\Support;

use Illuminate\Support\Facades\Redis;

class TokenBucket
{
    private const LUA_SCRIPT = <<<'LUA'
    local key = KEYS[1]
    local capacity = tonumber(ARGV[1])
    local refill_rate = tonumber(ARGV[2])
    local now = tonumber(ARGV[3])
    local tokens_requested = tonumber(ARGV[4])

    local bucket = redis.call('HMGET', key, 'tokens', 'last_refill')
    local tokens = tonumber(bucket[1]) or capacity
    local last_refill = tonumber(bucket[2]) or now

    local elapsed = now - last_refill
    tokens = math.min(capacity, tokens + elapsed * refill_rate)

    if tokens < tokens_requested then
        redis.call('HMSET', key, 'tokens', tokens, 'last_refill', now)
        redis.call('EXPIRE', key, 3600)
        return 0
    end

    tokens = tokens - tokens_requested
    redis.call('HMSET', key, 'tokens', tokens, 'last_refill', now)
    redis.call('EXPIRE', key, 3600)
    return 1
    LUA;

    public function tryAcquire(string $key, int $tokens = 1): bool
    {
        $result = Redis::eval(
            self::LUA_SCRIPT,
            1,
            $key,
            4000,      // capacity (RPM)
            4000 / 60, // refill rate (per second)
            time(),
            $tokens,
        );

        return $result === 1;
    }
}
```

### Queue-lanmış Claude Job

```php
<?php

namespace App\Jobs;

use App\Services\AI\ClaudeService;
use App\Services\AI\Support\TokenBucket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessClaudeRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;
    public int $timeout = 180;
    public array $backoff = [5, 10, 20, 40, 80, 120];

    public function __construct(
        public string $requestId,
        public array $payload,
    ) {}

    public function handle(ClaudeService $service, TokenBucket $bucket): void
    {
        if (!$bucket->tryAcquire('anthropic.global')) {
            // limit vurulub, 10s sonra yenidən cəhd et
            $this->release(10);
            return;
        }

        $response = $service->message($this->payload);

        ClaudeRequest::where('id', $this->requestId)->update([
            'status' => 'completed',
            'response' => $response,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        ClaudeRequest::where('id', $this->requestId)->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## RateLimited Middleware

Laravel 9+-da queue middleware var:

```php
namespace App\Jobs;

use Illuminate\Queue\Middleware\RateLimited;

class ProcessClaudeRequest implements ShouldQueue
{
    public function middleware(): array
    {
        return [new RateLimited('anthropic-api')];
    }
}
```

Konfiqurasiya AppServiceProvider-da:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('anthropic-api', function ($job) {
        return Limit::perMinute(4000)->by('global');
    });
}
```

### Per-tier Limits

```php
RateLimiter::for('anthropic-api', function ($job) {
    return [
        Limit::perMinute(4000)->by('global'),
        Limit::perMinute(100)->by("tenant.{$job->tenantId}"),
    ];
});
```

Bu, iki səviyyəli limit qoyur: qlobal 4000/dəq və tenant başına 100/dəq.

---

## Throttles Exceptions

`ThrottlesExceptions` middleware, müəyyən xətadan sonra backoff tətbiq edir:

```php
use Illuminate\Queue\Middleware\ThrottlesExceptions;

class ProcessClaudeRequest implements ShouldQueue
{
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(maxAttempts: 10, decayMinutes: 5))
                ->backoff(5),
        ];
    }
}
```

Bu necə işləyir:
- İlk xətadan sonra 5 dəqiqə decay window başlayır
- 10 xəta olarsa, decay sonuna qədər bu job-ın bütün instance-ları 5 dəqiqə səbirli gözləyir
- Fail olan bütün job-lar release olunur (re-queue)

### ThrottlesExceptionsWithRedis

Redis-based variant multi-worker mühitdə paylaşılmış state saxlayır:

```php
use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;

public function middleware(): array
{
    return [
        (new ThrottlesExceptionsWithRedis(10, 5))
            ->backoff(5)
            ->by('anthropic-api'),
    ];
}
```

---

## Bus Chain

Bir neçə sequential LLM çağırışı lazım olanda `Bus::chain` istifadə et. Bu, bir job uğursuz olanda chain-i dayandırır:

```php
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ExtractEntitiesJob($documentId),
    new SummarizeDocumentJob($documentId),
    new GenerateReportJob($documentId),
])
->catch(function (Throwable $e) {
    Log::error('Document pipeline failed', ['error' => $e]);
})
->dispatch();
```

### Bus Batch

Parallel execution üçün:

```php
Bus::batch([
    new ClassifyTicketJob($ticket1),
    new ClassifyTicketJob($ticket2),
    new ClassifyTicketJob($ticket3),
])
->then(fn ($batch) => Log::info('All classified'))
->catch(fn ($batch, $e) => Log::error('Batch failed'))
->allowFailures()
->dispatch();
```

Batch ilə işləyərkən hər job-ın öz rate limit middleware-i olur — batch kollektiv olaraq limit-ə riayət edir.

---

## Horizon

Horizon production-da queue idarəetməsi üçün əsasdır. AI workload üçün konfiqurasiya:

### config/horizon.php

```php
'environments' => [
    'production' => [
        'ai-supervisor' => [
            'connection' => 'redis',
            'queue' => ['ai-high', 'ai-default', 'ai-low'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'size',
            'minProcesses' => 2,
            'maxProcesses' => 20,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 512,
            'tries' => 1,     // retry middleware-də idarə edilir
            'timeout' => 180,
            'nice' => 0,
        ],

        'ai-batch-supervisor' => [
            'connection' => 'redis',
            'queue' => ['ai-batch'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 5,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 600,
        ],
    ],
],
```

### Niyə Ayrı Supervisor-lar

```
ai-supervisor:       real-time user sorğuları, sürətli
ai-batch-supervisor: gecə batch işləri, yavaş

Bu ayrıcalıq gərəklidir çünki:
  - Real-time job-lar sürətli bitməlidir
  - Batch job-lar uzun işləyə bilər
  - Qarışdırma → real-time trafiki blokla
```

### Queue Prioritetləşdirmə

```php
// Vacib sorğu — yüksək prioritet
ProcessClaudeRequest::dispatch($req)->onQueue('ai-high');

// Normal
ProcessClaudeRequest::dispatch($req)->onQueue('ai-default');

// Background analytics
ProcessClaudeRequest::dispatch($req)->onQueue('ai-low');
```

---

## Per-Tenant

Multi-tenant SaaS-də hər tenant-a öz quota verilməlidir:

```php
<?php

namespace App\Services\AI\Support;

use Illuminate\Support\Facades\Redis;

class TenantQuota
{
    public function __construct(
        private readonly string $tenantId,
    ) {}

    public function dailyLimit(): int
    {
        // tenant plan-ına görə
        $plan = \App\Models\Tenant::find($this->tenantId)->plan;
        
        return match ($plan) {
            'free' => 100,
            'starter' => 1_000,
            'pro' => 10_000,
            'enterprise' => 100_000,
            default => 0,
        };
    }

    public function currentUsage(): int
    {
        return (int) Redis::get($this->key());
    }

    public function canSpend(int $tokens = 1): bool
    {
        return ($this->currentUsage() + $tokens) <= $this->dailyLimit();
    }

    public function consume(int $tokens = 1): bool
    {
        if (!$this->canSpend($tokens)) {
            return false;
        }

        Redis::incrby($this->key(), $tokens);
        Redis::expire($this->key(), 86400);

        return true;
    }

    private function key(): string
    {
        $date = now()->toDateString();
        return "tenant_quota.{$this->tenantId}.{$date}";
    }
}
```

### İstifadə

```php
$quota = new TenantQuota($tenantId);

if (!$quota->canSpend(1)) {
    throw new QuotaExceededException("Daily limit reached for tenant {$tenantId}");
}

$response = $claude->messages()->create([...]);
$quota->consume(1);
```

### Token-based Quota

Request sayı yerinə token həcmi ilə quota daha ədalətlidir:

```php
$estimatedTokens = $tokenEstimator->estimate($payload);

if (!$quota->canSpend($estimatedTokens)) {
    throw new QuotaExceededException();
}

$response = $claude->messages()->create($payload);
$actualTokens = $response['usage']['input_tokens'] + $response['usage']['output_tokens'];
$quota->consume($actualTokens);
```

---

## DLQ

Dead Letter Queue — 5 retry-dan sonra uğursuz olan job-ları insan müdaxiləsi üçün saxlayır:

### Laravel Failed Jobs

```php
// config/queue.php
'failed' => [
    'driver' => 'database-uuids',
    'database' => 'mysql',
    'table' => 'failed_jobs',
],
```

### Failed Job Handler

```php
namespace App\Jobs;

class ProcessClaudeRequest implements ShouldQueue
{
    public function failed(\Throwable $exception): void
    {
        // Sentry-ə göndər
        \Sentry\captureException($exception);

        // DB-də status yenilə
        ClaudeRequest::where('id', $this->requestId)->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'failed_at' => now(),
        ]);

        // Dead letter queue-ya xüsusi job-a köçür
        DlqJob::dispatch($this->requestId, $this->payload, $exception->getMessage());

        // İstifadəçini məlumatlandır
        event(new AIRequestFailed($this->requestId));
    }
}
```

### DLQ İdarəetmə Komandı

```php
php artisan queue:failed           // bütün fail olanları göstər
php artisan queue:retry all         // hamısını yenidən cəhd et
php artisan queue:retry <uuid>      // birini retry et
php artisan queue:forget <uuid>     // birini sil
php artisan queue:flush             // hamısını sil
```

### Custom DLQ Table

Daha detallı DLQ lazımdırsa:

```php
Schema::create('ai_dlq', function (Blueprint $table) {
    $table->id();
    $table->string('request_id');
    $table->string('tenant_id')->nullable();
    $table->json('payload');
    $table->string('error_type');
    $table->text('error_message');
    $table->integer('retry_count');
    $table->string('last_status_code')->nullable();
    $table->timestamp('failed_at');
    $table->timestamp('resolved_at')->nullable();
    $table->string('resolved_by')->nullable();
});
```

---

## Observability

Produksiya sisteminin mütləq ölçməli olduğu metrikalar:

```
1. Request rate (req/s)
2. Error rate (error/s, by type)
3. P50/P95/P99 latency
4. Token usage (input/output/cached)
5. Cost (per tenant, per feature)
6. Rate limit remaining (from response headers)
7. Circuit breaker state
8. Queue depth (per queue)
9. Retry count histogram
10. DLQ size
```

### Prometheus Metrikalar

```php
namespace App\Services\AI\Support;

use Prometheus\CollectorRegistry;

class AiMetrics
{
    public function __construct(
        private readonly CollectorRegistry $registry,
    ) {}

    public function recordRequest(string $model, int $statusCode, float $durationMs): void
    {
        $this->registry->getOrRegisterCounter(
            'ai',
            'requests_total',
            'Total LLM requests',
            ['model', 'status'],
        )->inc([$model, (string) $statusCode]);

        $this->registry->getOrRegisterHistogram(
            'ai',
            'request_duration_ms',
            'LLM request duration',
            ['model'],
            [100, 500, 1000, 2000, 5000, 10000, 30000],
        )->observe($durationMs, [$model]);
    }

    public function recordTokens(string $model, int $input, int $output): void
    {
        $this->registry->getOrRegisterCounter(
            'ai',
            'input_tokens_total',
            'Total input tokens',
            ['model'],
        )->incBy($input, [$model]);

        $this->registry->getOrRegisterCounter(
            'ai',
            'output_tokens_total',
            'Total output tokens',
            ['model'],
        )->incBy($output, [$model]);
    }

    public function recordRateLimitRemaining(array $headers): void
    {
        if (isset($headers['anthropic-ratelimit-requests-remaining'])) {
            $this->registry->getOrRegisterGauge(
                'ai',
                'rate_limit_requests_remaining',
                'Remaining request quota',
            )->set((float) $headers['anthropic-ratelimit-requests-remaining'][0]);
        }
    }
}
```

### Structured Logging

```php
Log::withContext([
    'request_id' => $requestId,
    'tenant_id' => $tenantId,
    'model' => $model,
])->info('claude.request', [
    'input_tokens' => $inputTokens,
    'output_tokens' => $outputTokens,
    'latency_ms' => $latencyMs,
    'cost_usd' => $cost,
    'attempt' => $attempt,
]);
```

---

## Claude Service

Burada əvvəlki bütün pattern-ları birləşdirən production-ready servis:

```php
<?php

namespace App\Services\AI;

use App\Exceptions\AiRateLimitException;
use App\Exceptions\AiServerException;
use App\Exceptions\AiClientException;
use App\Services\AI\Support\BackoffCalculator;
use App\Services\AI\Support\CircuitBreaker;
use App\Services\AI\Support\AiMetrics;
use App\Services\AI\Support\TenantQuota;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly BackoffCalculator $backoff,
        private readonly CircuitBreaker $breaker,
        private readonly AiMetrics $metrics,
        private readonly PricingCalculator $pricing,
        private readonly UsageTracker $tracker,
        private readonly int $maxAttempts = 5,
        private readonly int $totalTimeoutMs = 180_000,
    ) {}

    public function message(array $payload, array $context = []): array
    {
        if (!$this->breaker->allow()) {
            throw new AiServerException('Circuit breaker open');
        }

        $tenant = $context['tenant_id'] ?? null;
        if ($tenant) {
            $quota = new TenantQuota($tenant);
            if (!$quota->canSpend(1)) {
                throw new AiClientException('Tenant quota exceeded');
            }
        }

        $startedAt = (int) (microtime(true) * 1000);
        $attempt = 0;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            if (((int) (microtime(true) * 1000) - $startedAt) > $this->totalTimeoutMs) {
                throw new AiServerException('Total timeout exceeded');
            }

            try {
                $response = $this->doRequest($payload);
                $this->breaker->recordSuccess();
                $this->metrics->recordRequest($payload['model'], 200, (microtime(true) * 1000) - $startedAt);
                $this->metrics->recordRateLimitRemaining($response['_headers'] ?? []);

                $this->tracker->track($response, $context);

                return $response;
            } catch (AiRateLimitException $e) {
                $this->breaker->recordFailure();
                $this->metrics->recordRequest($payload['model'], 429, 0);

                if ($attempt >= $this->maxAttempts) {
                    throw $e;
                }

                $sleep = $e->retryAfterMs ?? $this->backoff->exponential($attempt);
                Log::warning('claude.rate_limit', ['attempt' => $attempt, 'sleep_ms' => $sleep]);
                usleep($sleep * 1000);
            } catch (AiServerException $e) {
                $this->breaker->recordFailure();
                $this->metrics->recordRequest($payload['model'], $e->statusCode, 0);

                if ($attempt >= $this->maxAttempts) {
                    throw $e;
                }

                $sleep = $this->backoff->exponential($attempt);
                Log::warning('claude.server_error', [
                    'attempt' => $attempt,
                    'status' => $e->statusCode,
                    'sleep_ms' => $sleep,
                ]);
                usleep($sleep * 1000);
            } catch (ConnectionException $e) {
                $this->breaker->recordFailure();

                if ($attempt >= $this->maxAttempts) {
                    throw new AiServerException('Connection failed: ' . $e->getMessage(), 0, $e);
                }

                $sleep = $this->backoff->exponential($attempt);
                Log::warning('claude.connection_error', ['attempt' => $attempt, 'sleep_ms' => $sleep]);
                usleep($sleep * 1000);
            } catch (AiClientException $e) {
                // 4xx xətaları retry etmə
                $this->metrics->recordRequest($payload['model'], $e->statusCode, 0);
                throw $e;
            }
        }

        throw new AiServerException('Max attempts exhausted');
    }

    private function doRequest(array $payload): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
        ->timeout(60)
        ->connectTimeout(10)
        ->post("{$this->baseUrl}/v1/messages", $payload);

        $body = $response->json() ?? [];
        $status = $response->status();

        if ($status === 429) {
            $retryAfter = $response->header('retry-after');
            throw new AiRateLimitException(
                message: $body['error']['message'] ?? 'Rate limited',
                retryAfterMs: $retryAfter ? $this->backoff->fromRetryAfter($retryAfter) : null,
            );
        }

        if ($status >= 500) {
            throw new AiServerException(
                message: $body['error']['message'] ?? 'Server error',
                statusCode: $status,
            );
        }

        if ($status >= 400) {
            throw new AiClientException(
                message: $body['error']['message'] ?? 'Client error',
                statusCode: $status,
            );
        }

        return [
            ...$body,
            '_headers' => $response->headers(),
            '_latency_ms' => (int) ($response->handlerStats()['total_time'] ?? 0) * 1000,
        ];
    }
}
```

### Exception Classes

```php
namespace App\Exceptions;

class AiRateLimitException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterMs = null,
    ) {
        parent::__construct($message);
    }
}

class AiServerException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 500,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

class AiClientException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 400,
    ) {
        parent::__construct($message);
    }
}
```

### ServiceProvider Binding

```php
namespace App\Providers;

use App\Services\AI\ClaudeService;
use App\Services\AI\Support\BackoffCalculator;
use App\Services\AI\Support\CircuitBreaker;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClaudeService::class, function ($app) {
            return new ClaudeService(
                apiKey: config('services.anthropic.key'),
                baseUrl: config('services.anthropic.url', 'https://api.anthropic.com'),
                backoff: new BackoffCalculator(baseMs: 500, maxMs: 60_000),
                breaker: new CircuitBreaker(
                    name: 'anthropic',
                    failureThreshold: 10,
                    failureRatio: 0.5,
                    windowSeconds: 60,
                    openSeconds: 30,
                ),
                metrics: $app->make(\App\Services\AI\Support\AiMetrics::class),
                pricing: $app->make(\App\Services\AI\PricingCalculator::class),
                tracker: $app->make(\App\Services\AI\UsageTracker::class),
                maxAttempts: 5,
                totalTimeoutMs: 180_000,
            );
        });
    }
}
```

---

## Checklist

Production-da göndərmədən əvvəl yoxla:

### Rate Limit və Retry

- [ ] Exponential backoff jitter ilə
- [ ] `retry-after` header-ini oxuyur və riayət edir
- [ ] Max retry count qoyulub (5-7)
- [ ] Total timeout qoyulub (3 dəq)
- [ ] 4xx xətalar retry ETMİR
- [ ] 5xx/429 xətalar retry EDİR

### Circuit Breaker

- [ ] Failure threshold qoyulub
- [ ] Open/half-open/closed state idarə olunur
- [ ] Metrikalar göndərilir

### Queue

- [ ] Horizon konfiqurasiya edilib
- [ ] Real-time və batch ayrı queue-lardadır
- [ ] Prioritet queue-ları mövcuddur
- [ ] RateLimited middleware aktivdir
- [ ] ThrottlesExceptions aktivdir

### Quota

- [ ] Per-tenant daily/monthly limit var
- [ ] Request count + token count izlənir
- [ ] Over-quota üçün 402 (Payment Required) qaytarılır

### DLQ

- [ ] failed_jobs table var
- [ ] Failed handler Sentry-ə göndərir
- [ ] DLQ review prosesi təyin edilib

### Observability

- [ ] Prometheus metrikalar
- [ ] Structured logging (request_id, tenant_id)
- [ ] Grafana dashboard var (RPS, error, latency, cost)
- [ ] Rate limit remaining alerting var (<20% qalanda)
- [ ] Cost alerting var (günlük eşik)

### Cost

- [ ] Per-request cost tracked
- [ ] Per-tenant monthly sum
- [ ] Budget exceeded xəbərdarlığı

### Testing

- [ ] Integration test: 429 handled düzgün
- [ ] Integration test: 500 retry olur
- [ ] Integration test: 4xx retry ETMİR
- [ ] Load test: 1000 concurrent request
- [ ] Chaos test: Anthropic down olduqda nə olur

---

## Yekun

Rate limit, retry və backoff — LLM production-un üç ayağıdır:

1. **Rate limit** qaçılmazdır — düşmən kimi deyil, qoruyucu kimi qəbul et
2. **Exponential backoff + jitter** — thundering herd-i önləyir
3. **Circuit breaker** — cascade failure-dan qoruyur
4. **Queue-based throttling** — burst trafiki hamarlamaq üçün
5. **Per-tenant quota** — multi-tenant-də bir istifadəçinin bütün pulu yeməməsi üçün
6. **DLQ** — insan müdaxiləsi üçün geri qalan işlər
7. **Observability** — gözləri olmadan optimizasiya mümkün deyil

Senior PHP developer kimi bu pattern-ları öz ClaudeService-in içində bir dəfə implement etdikdə, bütün şirkət üçün dayanıqlı bir özül yaratmış olursan. Əks halda hər feature team öz naive retry-ını yazır və Anthropic outage zamanı sistem dizdən qatlanır.

Bu sənəddə göstərilən `ClaudeService` — Laravel-in bütün primitive-lərini istifadə edərək Anthropic API-nin bütün xırdalıqlarını adi developer-dən gizlədir. Nəticə: feature team-lər yalnız `$claude->message([...])` çağırır — və rest infrastruktur öz işini görür.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Exponential Backoff Testi

429 xətasını simulate et (test environment-də mock istifadə et). `ClaudeService`-in backoff davranışını yoxla: 1→2→4 saniyə gözləyir? Max retry sayına çatanda exception atır? Hər retry cəhdini log-a yazar? Retry-ların `claude_retries` log cədvəlinde izlənilməsini test et.

### Tapşırıq 2: Circuit Breaker Monitoring

`ClaudeCircuitBreaker`-i Filament dashboard-da göstər. Son 100 sorğu üçün: success rate, average latency, circuit state (open/closed/half-open). Circuit `open` vəziyyətinə keçdikdə Slack-ə alert göndər. Circuit `half-open`-dan `closed`-a keçəndə auto-recovery confirm et.

### Tapşırıq 3: Header Parsing

Real 429 response-undan `retry-after` header-ini parse et. `x-ratelimit-reset-requests` və `x-ratelimit-reset-tokens` header-lərini log et. Token limit vs request limit ayrımını anla. Token limit reseti gözlədikdə request limit reseti gözləməyə qalxırsınmı?

---

## Əlaqəli Mövzular

- `01-claude-api-guide.md` — API əsasları
- `../07-workflows/04-ai-idempotency-circuit-breaker.md` — Circuit breaker pattern-i dərinləşdir
- `../08-production/15-multi-provider-failover.md` — Rate limit zamanı provayder dəyişikliyi
- `../01-fundamentals/09-llm-provider-comparison.md` — Provayderlər üzrə rate limit müqayisəsi
