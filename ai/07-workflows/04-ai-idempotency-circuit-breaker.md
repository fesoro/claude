# AI Çağırışları üçün Idempotency və Circuit Breaker (Senior)

> **Oxucu:** Senior Laravel developerlər, etibarlı sistem qurucular
> **Ön şərtlər:** Redis, middleware, event pattern, Laravel cache
> **Tarix:** 2026-04-21
> **Modellər:** `claude-sonnet-4-5`, `claude-opus-4-5`, `claude-haiku-4-5`

---

## Mündəricat

1. Niyə AI Çağırışları Bu Pattern-lərə Ehtiyac Duyur
2. Idempotency Key Yaratma Strategiyası
3. Redis-backed Idempotency Cache
4. Circuit Breaker: Closed / Open / Half-Open
5. Laravel Middleware — Controller İdempotency
6. Queue Job Wrapper — Idempotent Execution
7. Fallback Chain — Primary → Fallback → Cached → Degradation
8. Laravel `CircuitBreaker` Service Implementation
9. `FallbackChain` Service
10. Real Tests — Unit və Integration
11. Observability və Metrics
12. Yekun — Production Pattern

---

## 1. Niyə AI Çağırışları Bu Pattern-lərə Ehtiyac Duyur

AI API-lər paylanmış sistemin ən etibarsız komponentidir. İki əsas risk:

### Risk 1: Dublikat İcra

Istifadəçi "Faktura yarat" düyməsini iki dəfə basır. Hər ikisi request göndərir. Retry isə daha da çoxaldır. Nəticə: 3 faktura, 3 qat token xərci, müştəri qəzəbi.

### Risk 2: Kaskad Çökmə

Anthropic 10 dəqiqə down olur. Sizin Laravel-də hər request 30 saniyə gözləyir, sonra timeout. Worker-lər tıxanır. Queue backlog partlayır. UI cavab vermir. Bütün sistem düşür.

### Həll

- **Idempotency** — dublikat icranı sıfır dəyərli edir.
- **Circuit breaker** — aşağı xidmət bütün sistemi məhv etməsin.

Bu iki pattern birlikdə "AI reliability layer"-i təşkil edir.

```
┌──────────────────────────────────────────────────────────┐
│                   RELIABILITY LAYER                      │
│                                                          │
│  ┌─────────────────┐       ┌─────────────────┐          │
│  │  Idempotency    │       │ Circuit Breaker │          │
│  │  - Key hash     │       │ - State machine │          │
│  │  - Cache result │       │ - Fail fast     │          │
│  │  - Dedup        │       │ - Recovery probe│          │
│  └────────┬────────┘       └────────┬────────┘          │
│           │                         │                    │
│           └─────────────┬───────────┘                    │
│                         │                                │
│                 ┌───────▼────────┐                       │
│                 │ Fallback Chain │                       │
│                 │  1. Primary    │                       │
│                 │  2. Fallback   │                       │
│                 │  3. Cached     │                       │
│                 │  4. Degraded   │                       │
│                 └────────────────┘                       │
└──────────────────────────────────────────────────────────┘
```

---

## 2. Idempotency Key Yaratma Strategiyası

Key keyfiyyəti idempotency-nin keyfiyyətidir. Yaxşı key iki əsas xüsusiyyətə malikdir:

1. **Determinism**: eyni giriş → eyni key.
2. **Uniqueness**: fərqli semantik giriş → fərqli key.

### Əsas Formul

```
key = hash(tenant_id + user_id + action + prompt + model + params + version)
```

### Niyə Hər Komponent Vacibdir

| Komponent | Səbəb |
|-----------|-------|
| `tenant_id` | Tenant-lar arası izolyasiya |
| `user_id` | İki user eyni prompt yazsa, cache-dən istifadə etsin amma audit ayrı olsun |
| `action` | `summarize` vs `translate` fərqli |
| `prompt` | Ən böyük fərqləndirici |
| `model` | `haiku` vs `opus` — fərqli nəticə |
| `params` | `temperature`, `max_tokens` fərqli olsa fərqli nəticə |
| `version` | Cache invalidation üçün açar — v1 → v2 keçəndə bütün köhnələr etibarsız |

### PHP İmplementasiyası

```php
<?php

namespace App\AI;

class IdempotencyKey
{
    public static function for(
        int $tenantId,
        int $userId,
        string $action,
        string $prompt,
        string $model,
        array $params = [],
        string $version = 'v1',
    ): string {
        // Normalize prompt — whitespace, case
        $normalized = trim(preg_replace('/\s+/', ' ', $prompt));

        // Parametrləri sort et — key order fərqi olmasın
        ksort($params);
        $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_SORTED);

        $material = implode('|', [
            "t:{$tenantId}",
            "u:{$userId}",
            "a:{$action}",
            "m:{$model}",
            "v:{$version}",
            'p:' . hash('sha256', $normalized),
            'x:' . hash('sha256', $paramsJson),
        ]);

        return 'aiop:' . hash('sha256', $material);
    }
}
```

### İstifadə

```php
$key = IdempotencyKey::for(
    tenantId: 42,
    userId: 1001,
    action: 'summarize_document',
    prompt: "Bu sənədi xülasələ: ...",
    model: 'claude-sonnet-4-5',
    params: ['max_tokens' => 500, 'temperature' => 0.0],
);
// → "aiop:a7f4..."
```

### Client-Provided Keys

Bəzən client özü idempotency key göndərmək istəyir (Stripe pattern). Bu, **daha güclü** idempotency-dir:

```
Header: Idempotency-Key: client-txn-abc123
```

```php
$key = $request->header('Idempotency-Key');
if (!$key) {
    return response()->json(['error' => 'Idempotency-Key header tələb olunur'], 400);
}

// Client key-i namespace et
$fullKey = "client:{$tenantId}:{$key}";
```

---

## 3. Redis-backed Idempotency Cache

Redis idempotency üçün ideal-dır: fast, TTL, atomic operations.

### Storage Strategiyası

Hər key üçün üç ehtimal:
1. **Yoxdur** — birinci dəfə görülən sorğu
2. **`in_progress`** — hal-hazırda icra olunur (başqa request paralel gəldi)
3. **`completed` + result** — artıq icra olunub, cache-dən qaytarırıq

```
Redis Key: aiop:{hash}
Value: JSON {
  status: "completed",
  result: {...},
  created_at: 1714123200,
  expires_at: 1714209600
}
```

### Əsas Class

```php
<?php

namespace App\AI;

use Illuminate\Support\Facades\Redis;

class IdempotencyStore
{
    private const TTL_SECONDS = 86400;  // 24 saat
    private const LOCK_TTL_SECONDS = 60;

    public function remember(string $key, callable $operation): mixed
    {
        $existing = $this->fetch($key);

        if ($existing && $existing['status'] === 'completed') {
            return $existing['result'];
        }

        if ($existing && $existing['status'] === 'in_progress') {
            return $this->waitForCompletion($key);
        }

        // Try to acquire lock
        $acquired = Redis::set(
            "lock:{$key}",
            gethostname() . ':' . getmypid(),
            'EX', self::LOCK_TTL_SECONDS,
            'NX'
        );

        if (!$acquired) {
            return $this->waitForCompletion($key);
        }

        try {
            $this->markInProgress($key);
            $result = $operation();
            $this->markCompleted($key, $result);
            return $result;
        } catch (\Throwable $e) {
            $this->clear($key);
            throw $e;
        } finally {
            Redis::del("lock:{$key}");
        }
    }

    private function fetch(string $key): ?array
    {
        $raw = Redis::get($key);
        return $raw ? json_decode($raw, true) : null;
    }

    private function markInProgress(string $key): void
    {
        Redis::setex($key, self::LOCK_TTL_SECONDS, json_encode([
            'status' => 'in_progress',
            'started_at' => time(),
        ]));
    }

    private function markCompleted(string $key, mixed $result): void
    {
        Redis::setex($key, self::TTL_SECONDS, json_encode([
            'status' => 'completed',
            'result' => $result,
            'created_at' => time(),
            'expires_at' => time() + self::TTL_SECONDS,
        ]));
    }

    private function waitForCompletion(string $key, int $maxWaitMs = 30000): mixed
    {
        $pollIntervalMs = 200;
        $elapsed = 0;

        while ($elapsed < $maxWaitMs) {
            usleep($pollIntervalMs * 1000);
            $elapsed += $pollIntervalMs;

            $entry = $this->fetch($key);
            if ($entry && $entry['status'] === 'completed') {
                return $entry['result'];
            }
        }

        throw new \RuntimeException("Idempotency key '{$key}' waiting timeout");
    }

    public function clear(string $key): void
    {
        Redis::del($key);
    }
}
```

### İstifadə

```php
$store = app(IdempotencyStore::class);
$key = IdempotencyKey::for(...);

$result = $store->remember($key, function () use ($claude, $prompt) {
    return $claude->chat([
        'model' => 'claude-sonnet-4-5',
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ]);
});
```

---

## 4. Circuit Breaker: Closed / Open / Half-Open

Circuit breaker üç state-də olur:

```
┌──────────────────────────────────────────────────────────┐
│                    STATE MACHINE                         │
│                                                          │
│              error rate > threshold                      │
│              ┌─────────────────────┐                     │
│              │                     ▼                     │
│        ┌───────────┐         ┌──────────┐                │
│        │  CLOSED   │         │   OPEN   │                │
│        │ (normal)  │         │(failing) │                │
│        └───────────┘         └──────────┘                │
│              ▲                     │                     │
│              │                     │ cooldown timeout    │
│              │                     ▼                     │
│              │               ┌─────────────┐             │
│        success │             │  HALF-OPEN  │             │
│              │               │ (testing)   │             │
│              │               └─────────────┘             │
│              │                     │                     │
│              └─────────────────────┘                     │
│                   probe failure → OPEN                   │
└──────────────────────────────────────────────────────────┘
```

### State Mənaları

| State | Davranış |
|-------|----------|
| CLOSED | Normal axın — bütün request-lər keçir |
| OPEN | Bütün request-lər fail-fast olunur (upstream çağırılmır) |
| HALF-OPEN | Məhdud sayda probe request keçirilir |

### Threshold Parametrləri

```php
class CircuitBreakerConfig
{
    public int $failureThreshold = 5;        // neçə uğursuz → OPEN
    public int $successThreshold = 2;        // HALF-OPEN-dən CLOSED-a neçə uğur
    public int $windowSizeSeconds = 60;      // error rate hesablanma pəncərəsi
    public int $cooldownSeconds = 30;        // OPEN-də nə qədər qal
    public float $failureRateThreshold = 0.5; // 50%+ failure
    public int $minRequests = 10;            // rate hesablamaq üçün minimum
}
```

Praktik konfiq `claude-sonnet-4-5` üçün:
- 1 dəqiqədə 10+ sorğu olmalı (dəyərli signal üçün)
- 50%+ fail → OPEN
- OPEN 30 saniyə
- HALF-OPEN-də ardıcıl 2 uğur → CLOSED

---

## 5. Laravel Middleware — Controller İdempotency

HTTP endpoints üçün middleware client-provided idempotency key-i qəbul edir.

```php
<?php

namespace App\Http\Middleware;

use App\AI\IdempotencyStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdempotency
{
    public function __construct(private IdempotencyStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PATCH', 'PUT'])) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (!$key) {
            return response()->json([
                'error' => 'Idempotency-Key header tələb olunur',
                'hint' => 'UUIDv4 istifadə edin',
            ], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9-]{8,64}$/', $key)) {
            return response()->json(['error' => 'Key formatı yanlışdır'], 400);
        }

        $user = $request->user();
        $fullKey = "http:{$user->tenant_id}:{$user->id}:{$request->path()}:{$key}";

        // Key + body hash — eyni key fərqli body ilə səhvi yaxalasın
        $bodyHash = hash('sha256', $request->getContent());
        $fullKey .= ':' . substr($bodyHash, 0, 16);

        return $this->store->remember($fullKey, function () use ($request, $next) {
            $response = $next($request);

            return [
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
                'content_type' => $response->headers->get('Content-Type', 'application/json'),
            ];
        })['body'] ?? response()->noContent();
    }
}
```

**Vacib**: response cache-lənmə format-ı diqqətlə seçilməlidir. Yalnız serializable məlumat.

### Daha düzgün implementasiya

```php
public function handle(Request $request, Closure $next): Response
{
    // ... key validation

    $cached = $this->store->fetch($fullKey);
    if ($cached && $cached['status'] === 'completed') {
        return response($cached['result']['body'], $cached['result']['status'])
            ->header('Content-Type', $cached['result']['content_type'])
            ->header('Idempotency-Replayed', 'true');
    }

    return $this->store->remember($fullKey, function () use ($request, $next) {
        $response = $next($request);
        return [
            'status' => $response->getStatusCode(),
            'body' => $response->getContent(),
            'content_type' => $response->headers->get('Content-Type'),
        ];
    });
}
```

### Route-da Tətbiq

```php
// routes/api.php
Route::middleware(['auth:sanctum', EnforceIdempotency::class])->group(function () {
    Route::post('/ai/summarize', [AIController::class, 'summarize']);
    Route::post('/invoices', [InvoiceController::class, 'create']);
});
```

---

## 6. Queue Job Wrapper — Idempotent Execution

Job-larda middleware yoxdur — amma base class istifadə etmək olar.

```php
<?php

namespace App\Jobs;

use App\AI\IdempotencyKey;
use App\AI\IdempotencyStore;
use Illuminate\Contracts\Queue\ShouldQueue;

abstract class IdempotentJob implements ShouldQueue
{
    use \Illuminate\Bus\Queueable,
        \Illuminate\Foundation\Bus\Dispatchable,
        \Illuminate\Queue\InteractsWithQueue,
        \Illuminate\Queue\SerializesModels;

    abstract protected function idempotencyMaterial(): array;
    abstract protected function execute(): mixed;

    public function handle(IdempotencyStore $store): mixed
    {
        $material = $this->idempotencyMaterial();
        $key = 'job:' . static::class . ':' . hash('sha256', json_encode($material));

        return $store->remember($key, fn() => $this->execute());
    }
}
```

### Konkret İstifadə

```php
class GenerateInvoicePdfJob extends IdempotentJob
{
    public function __construct(
        public int $invoiceId,
        public string $version = 'v1',
    ) {}

    protected function idempotencyMaterial(): array
    {
        return [
            'invoice' => $this->invoiceId,
            'version' => $this->version,
        ];
    }

    protected function execute(): mixed
    {
        $invoice = \App\Models\Invoice::findOrFail($this->invoiceId);
        $pdf = app(\App\Services\PdfGenerator::class)->render($invoice);
        $invoice->update(['pdf_path' => $pdf->path]);

        return ['path' => $pdf->path, 'size' => $pdf->size];
    }
}
```

---

## 7. Fallback Chain

Strateji ardıcıllıq: **primary → fallback model → cached response → graceful degradation**.

```
┌───────────────────────────────────────────────────────────┐
│  FALLBACK AXINI                                           │
│                                                           │
│  İstifadəçi sorğusu                                       │
│         │                                                 │
│         ▼                                                 │
│  [1] Primary: claude-sonnet-4-5                           │
│         │                                                 │
│         ├── uğurlu? ──▶ cavab qaytar                      │
│         │                                                 │
│         │ circuit breaker OPEN / timeout / 5xx            │
│         ▼                                                 │
│  [2] Fallback: claude-haiku-4-5 (daha ucuz, daha sürətli) │
│         │                                                 │
│         ├── uğurlu? ──▶ cavab qaytar (degraded flag)      │
│         │                                                 │
│         ▼                                                 │
│  [3] Cached: son uğurlu oxşar cavab                       │
│         │                                                 │
│         ├── tapıldı? ──▶ cavab qaytar (stale flag)        │
│         │                                                 │
│         ▼                                                 │
│  [4] Graceful degradation:                                │
│     "Hal-hazırda AI xidməti əlçatmazdır,                  │
│      bir neçə dəqiqə sonra cəhd edin."                    │
│     + manual task kimi qeyd et                            │
└───────────────────────────────────────────────────────────┘
```

### Niyə `haiku` Fallback-dur?

`claude-haiku-4-5` `claude-sonnet-4-5`-dən:
- ~5x ucuz
- ~3x daha sürətli
- Rate limit daha səxavətli
- Oxşar prompt strukturu ilə işləyir

Bu, "sliding quality" deməkdir — tam uğursuzluq əvəzinə aşağı keyfiyyətli cavab veririk.

---

## 8. Laravel `CircuitBreaker` Service

```php
<?php

namespace App\AI;

use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private string $name,
        private int $failureThreshold = 5,
        private int $successThreshold = 2,
        private int $windowSeconds = 60,
        private int $cooldownSeconds = 30,
        private int $minRequests = 10,
    ) {}

    public function execute(callable $operation, ?callable $fallback = null): mixed
    {
        $state = $this->state();

        if ($state === self::STATE_OPEN) {
            if ($this->shouldTransitionToHalfOpen()) {
                $this->transitionToHalfOpen();
            } else {
                if ($fallback) {
                    return $fallback();
                }
                throw new \App\AI\Exceptions\CircuitBreakerOpenException(
                    "Circuit breaker '{$this->name}' OPEN"
                );
            }
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            if ($fallback) {
                return $fallback();
            }
            throw $e;
        }
    }

    public function state(): string
    {
        return Redis::get($this->key('state')) ?? self::STATE_CLOSED;
    }

    private function recordSuccess(): void
    {
        $now = time();
        Redis::zadd($this->key('success'), $now, "{$now}-" . \Str::random(8));
        Redis::expire($this->key('success'), $this->windowSeconds + 60);

        if ($this->state() === self::STATE_HALF_OPEN) {
            $probeSuccess = Redis::incr($this->key('half_open_success'));
            if ($probeSuccess >= $this->successThreshold) {
                $this->transitionToClosed();
            }
        }
    }

    private function recordFailure(): void
    {
        $now = time();
        Redis::zadd($this->key('failure'), $now, "{$now}-" . \Str::random(8));
        Redis::expire($this->key('failure'), $this->windowSeconds + 60);

        if ($this->state() === self::STATE_HALF_OPEN) {
            $this->transitionToOpen();
            return;
        }

        $this->evaluateTransition();
    }

    private function evaluateTransition(): void
    {
        $cutoff = time() - $this->windowSeconds;

        Redis::zremrangebyscore($this->key('success'), 0, $cutoff);
        Redis::zremrangebyscore($this->key('failure'), 0, $cutoff);

        $successes = Redis::zcard($this->key('success'));
        $failures = Redis::zcard($this->key('failure'));
        $total = $successes + $failures;

        if ($total < $this->minRequests) return;

        $failureRate = $failures / $total;

        if ($failures >= $this->failureThreshold && $failureRate >= 0.5) {
            $this->transitionToOpen();
        }
    }

    private function shouldTransitionToHalfOpen(): bool
    {
        $openedAt = (int) Redis::get($this->key('opened_at'));
        return $openedAt && (time() - $openedAt) >= $this->cooldownSeconds;
    }

    private function transitionToOpen(): void
    {
        Redis::set($this->key('state'), self::STATE_OPEN);
        Redis::set($this->key('opened_at'), time());
        Redis::del($this->key('half_open_success'));

        event(new \App\Events\CircuitBreakerOpened($this->name));
        \Log::warning("Circuit breaker OPEN", ['name' => $this->name]);
    }

    private function transitionToHalfOpen(): void
    {
        Redis::set($this->key('state'), self::STATE_HALF_OPEN);
        Redis::set($this->key('half_open_success'), 0);
        \Log::info("Circuit breaker HALF-OPEN", ['name' => $this->name]);
    }

    private function transitionToClosed(): void
    {
        Redis::set($this->key('state'), self::STATE_CLOSED);
        Redis::del($this->key('half_open_success'));
        Redis::del($this->key('opened_at'));

        event(new \App\Events\CircuitBreakerClosed($this->name));
        \Log::info("Circuit breaker CLOSED", ['name' => $this->name]);
    }

    private function key(string $suffix): string
    {
        return "cb:{$this->name}:{$suffix}";
    }
}
```

### İstifadə

```php
$breaker = new CircuitBreaker(
    name: 'anthropic-sonnet',
    failureThreshold: 5,
    cooldownSeconds: 30,
);

$result = $breaker->execute(
    operation: fn() => $claude->chat([...]),
    fallback: fn() => $claude->chat([...'model' => 'claude-haiku-4-5']),
);
```

---

## 9. `FallbackChain` Service

```php
<?php

namespace App\AI;

class FallbackChain
{
    /** @var array<callable> */
    private array $stages = [];

    public function add(string $name, callable $stage, ?callable $shouldRun = null): self
    {
        $this->stages[] = [
            'name' => $name,
            'fn' => $stage,
            'guard' => $shouldRun,
        ];
        return $this;
    }

    public function run(array $context = []): array
    {
        $attempts = [];

        foreach ($this->stages as $i => $stage) {
            if ($stage['guard'] && !($stage['guard'])($context)) {
                $attempts[] = ['stage' => $stage['name'], 'skipped' => true];
                continue;
            }

            try {
                $result = ($stage['fn'])($context);
                return [
                    'success' => true,
                    'stage' => $stage['name'],
                    'stage_index' => $i,
                    'result' => $result,
                    'attempts' => $attempts,
                    'degraded' => $i > 0,
                ];
            } catch (\Throwable $e) {
                $attempts[] = [
                    'stage' => $stage['name'],
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ];
                \Log::warning("FallbackChain stage failed", [
                    'stage' => $stage['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \App\AI\Exceptions\AllFallbacksFailedException(
            "Bütün fallback mərhələlər fail oldu",
            0,
            null,
            $attempts
        );
    }
}
```

### Konkret Configuration

```php
namespace App\Services;

use App\AI\ClaudeGateway;
use App\AI\CircuitBreaker;
use App\AI\FallbackChain;
use Illuminate\Support\Facades\Cache;

class ResilientSummarizer
{
    public function __construct(
        private ClaudeGateway $claude,
    ) {}

    public function summarize(string $text, int $tenantId): array
    {
        $cacheKey = 'summary:' . hash('sha256', $text);

        $chain = (new FallbackChain())
            ->add('primary', function () use ($text) {
                $breaker = new CircuitBreaker('anthropic-sonnet');
                return $breaker->execute(fn() => $this->callModel(
                    'claude-sonnet-4-5', $text, maxTokens: 500
                ));
            })
            ->add('fallback_model', function () use ($text) {
                $breaker = new CircuitBreaker('anthropic-haiku');
                return $breaker->execute(fn() => $this->callModel(
                    'claude-haiku-4-5', $text, maxTokens: 300
                ));
            })
            ->add('cached', function () use ($cacheKey) {
                $cached = Cache::get($cacheKey);
                if (!$cached) {
                    throw new \RuntimeException('Cache-də yoxdur');
                }
                return [...$cached, 'stale' => true];
            })
            ->add('degraded', function () use ($text) {
                return [
                    'summary' => substr($text, 0, 200) . '...',
                    'model' => 'fallback_truncation',
                    'degraded' => true,
                    'message' => 'AI xidməti hal-hazırda əlçatmazdır. Mətn qısaldıldı.',
                ];
            });

        $outcome = $chain->run();

        // Uğurlu cavabı cache et (yalnız primary və fallback_model üçün)
        if (in_array($outcome['stage'], ['primary', 'fallback_model'])) {
            Cache::put($cacheKey, $outcome['result'], 3600 * 24);
        }

        return $outcome;
    }

    private function callModel(string $model, string $text, int $maxTokens): array
    {
        $result = $this->claude->chat([
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [[
                'role' => 'user',
                'content' => "Xülasələ:\n\n{$text}",
            ]],
        ]);

        return [
            'summary' => $result['content'][0]['text'],
            'model' => $model,
            'tokens' => $result['usage'],
        ];
    }
}
```

### Controller-də İstifadə

```php
class SummaryController extends Controller
{
    public function store(Request $r, ResilientSummarizer $svc)
    {
        $outcome = $svc->summarize($r->input('text'), $r->user()->tenant_id);

        return response()->json($outcome['result'])
            ->header('X-AI-Stage', $outcome['stage'])
            ->header('X-AI-Degraded', $outcome['degraded'] ? '1' : '0');
    }
}
```

---

## 10. Real Tests — Unit və Integration

### `CircuitBreaker` Unit Test

```php
<?php

use App\AI\CircuitBreaker;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::flushdb();
    $this->breaker = new CircuitBreaker(
        name: 'test',
        failureThreshold: 3,
        successThreshold: 2,
        windowSeconds: 60,
        cooldownSeconds: 2,
        minRequests: 3,
    );
});

test('normal axın CLOSED-da qalır', function () {
    $result = $this->breaker->execute(fn() => 'ok');
    expect($result)->toBe('ok');
    expect($this->breaker->state())->toBe('closed');
});

test('3 uğursuzluq sonra OPEN olur', function () {
    for ($i = 0; $i < 3; $i++) {
        try {
            $this->breaker->execute(fn() => throw new \Exception('fail'));
        } catch (\Throwable $e) {}
    }
    expect($this->breaker->state())->toBe('open');
});

test('OPEN-də fail-fast olur', function () {
    for ($i = 0; $i < 3; $i++) {
        try {
            $this->breaker->execute(fn() => throw new \Exception('fail'));
        } catch (\Throwable $e) {}
    }

    expect(fn() => $this->breaker->execute(fn() => 'ok'))
        ->toThrow(\App\AI\Exceptions\CircuitBreakerOpenException::class);
});

test('fallback OPEN-də istifadə olunur', function () {
    for ($i = 0; $i < 3; $i++) {
        try {
            $this->breaker->execute(fn() => throw new \Exception('fail'));
        } catch (\Throwable $e) {}
    }

    $result = $this->breaker->execute(
        operation: fn() => throw new \Exception('still failing'),
        fallback: fn() => 'from_fallback',
    );

    expect($result)->toBe('from_fallback');
});

test('cooldown sonrası HALF-OPEN', function () {
    for ($i = 0; $i < 3; $i++) {
        try {
            $this->breaker->execute(fn() => throw new \Exception('fail'));
        } catch (\Throwable $e) {}
    }

    expect($this->breaker->state())->toBe('open');

    sleep(3);  // cooldown

    $this->breaker->execute(fn() => 'probe1');
    $this->breaker->execute(fn() => 'probe2');

    expect($this->breaker->state())->toBe('closed');
});

test('HALF-OPEN-də fail → OPEN', function () {
    for ($i = 0; $i < 3; $i++) {
        try {
            $this->breaker->execute(fn() => throw new \Exception('fail'));
        } catch (\Throwable $e) {}
    }

    sleep(3);

    try {
        $this->breaker->execute(fn() => throw new \Exception('probe fail'));
    } catch (\Throwable $e) {}

    expect($this->breaker->state())->toBe('open');
});
```

### `IdempotencyStore` Test

```php
test('eyni key dəfələrlə remember çağırılsa operation bir dəfə icra olunur', function () {
    $store = app(IdempotencyStore::class);
    $counter = 0;

    for ($i = 0; $i < 5; $i++) {
        $result = $store->remember('test-key-1', function () use (&$counter) {
            $counter++;
            return ['value' => 42];
        });
        expect($result)->toBe(['value' => 42]);
    }

    expect($counter)->toBe(1);
});

test('operation fail olsa key silinir', function () {
    $store = app(IdempotencyStore::class);

    try {
        $store->remember('failing-key', fn() => throw new \Exception('x'));
    } catch (\Throwable $e) {}

    $counter = 0;
    $result = $store->remember('failing-key', function () use (&$counter) {
        $counter++;
        return 'retry-ok';
    });

    expect($counter)->toBe(1);
    expect($result)->toBe('retry-ok');
});
```

### Integration Test — FallbackChain

```php
test('primary fail olanda fallback_model işləyir', function () {
    $mock = Mockery::mock(ClaudeGateway::class);

    $mock->shouldReceive('chat')
        ->once()
        ->with(Mockery::on(fn($args) => $args['model'] === 'claude-sonnet-4-5'))
        ->andThrow(new \Exception('sonnet down'));

    $mock->shouldReceive('chat')
        ->once()
        ->with(Mockery::on(fn($args) => $args['model'] === 'claude-haiku-4-5'))
        ->andReturn([
            'content' => [['text' => 'Xülasə haiku-dan']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]);

    $svc = new ResilientSummarizer($mock);
    $outcome = $svc->summarize('Uzun mətn...', 42);

    expect($outcome['stage'])->toBe('fallback_model');
    expect($outcome['result']['summary'])->toBe('Xülasə haiku-dan');
    expect($outcome['degraded'])->toBe(true);
});
```

---

## 11. Observability və Metrics

### Qızıl Siqnallar

```
┌────────────────────────────────────────────────────────┐
│ IDEMPOTENCY METRICS                                    │
├────────────────────────────────────────────────────────┤
│ idempotency_cache_hit{operation}          Counter      │
│ idempotency_cache_miss{operation}         Counter      │
│ idempotency_wait_duration_ms              Histogram    │
│ idempotency_concurrent_wait_count         Gauge        │
├────────────────────────────────────────────────────────┤
│ CIRCUIT BREAKER METRICS                                │
├────────────────────────────────────────────────────────┤
│ circuit_breaker_state{name}               Gauge (0,1,2)│
│ circuit_breaker_state_transition{name,to} Counter      │
│ circuit_breaker_request{name,result}      Counter      │
├────────────────────────────────────────────────────────┤
│ FALLBACK METRICS                                       │
├────────────────────────────────────────────────────────┤
│ fallback_stage_used{name}                 Counter      │
│ fallback_degraded_response                Counter      │
│ fallback_total_failure                    Counter      │
└────────────────────────────────────────────────────────┘
```

### Event Listener → Prometheus

```php
// app/Listeners/CircuitBreakerStateListener.php

class CircuitBreakerStateListener
{
    public function handleOpened(\App\Events\CircuitBreakerOpened $e): void
    {
        \Prometheus\Counter::inc('circuit_breaker_state_transition', [
            'name' => $e->name,
            'to' => 'open',
        ]);
        \Prometheus\Gauge::set('circuit_breaker_state', 1, ['name' => $e->name]);
    }

    public function handleClosed(\App\Events\CircuitBreakerClosed $e): void
    {
        \Prometheus\Counter::inc('circuit_breaker_state_transition', [
            'name' => $e->name,
            'to' => 'closed',
        ]);
        \Prometheus\Gauge::set('circuit_breaker_state', 0, ['name' => $e->name]);
    }
}
```

### Alert Rules

```yaml
- alert: CircuitBreakerOpen
  expr: circuit_breaker_state{name="anthropic-sonnet"} == 1
  for: 1m
  labels: { severity: warning }
  annotations:
    summary: "Claude sonnet circuit breaker OPEN"

- alert: DegradedResponseRateHigh
  expr: rate(fallback_degraded_response_total[5m]) > 5
  for: 5m
  labels: { severity: warning }
  annotations:
    summary: "5+ req/s fallback model istifadə edir"

- alert: AllFallbacksFailing
  expr: rate(fallback_total_failure_total[5m]) > 0.1
  for: 2m
  labels: { severity: critical }
  annotations:
    summary: "AI fallback chain tamamilə fail olur"
```

### Grafana Panel

Əsas görünüş tövsiyə olunanlar:
- Circuit breaker state timeline (per model)
- Idempotency hit rate %
- Fallback stage distribution (stacked area)
- p95 latency per stage

---

## 12. Yekun — Production Pattern

AI sistemləri production-da aşağıdakı layer-lərə ehtiyac duyur:

```
┌────────────────────────────────────────────────────────┐
│  İstifadəçi / Worker                                   │
└──────────────────────┬─────────────────────────────────┘
                       │
              ┌────────▼────────┐
              │ Idempotency     │  dublikat icradan qoru
              │ Store + Key     │
              └────────┬────────┘
                       │
              ┌────────▼────────┐
              │ Fallback Chain  │  primary → fallback → cached → degraded
              └────────┬────────┘
                       │
              ┌────────▼────────┐
              │ Circuit Breaker │  hər stage üçün ayrıca
              └────────┬────────┘
                       │
              ┌────────▼────────┐
              │ Claude API      │
              └─────────────────┘
```

### Prinsiplər

1. **Hər yaradıcı əməliyyat idempotent olmalıdır** — key generation yerində ardıcıl.
2. **Circuit breaker hər xarici asılılıq üçün** — Anthropic, OpenAI, cache, DB.
3. **Fallback chain ən azı 3 mərhələli** — primary + fallback + degraded.
4. **State machine açıq şəkildə model olunur** — hiss yox, logic.
5. **Bütün state transition-lar observable-dır** — event + metric.
6. **Test bütün state-lər üçün** — CLOSED, OPEN, HALF-OPEN, transition-lar.

Bu layer olmadan AI feature "gün üçün yaxşı" amma production-qrafiq-ə-çıxış-yoxdur. Onunla — AI reliability-ni klassik microservice-lə eyni səviyyəyə çatdırır.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Idempotency Key Test

`ProcessInvoiceJob`-a idempotency key əlavə et: `invoice_id`-dən SHA256 hash yarat. Eyni invoice üçün job-u 3 dəfə dispatch et. Yalnız bir dəfə real API çağırışı edilməli, digərləri cache-dən cavab verməlidir. Idempotency key TTL-i 24 saat et.

### Tapşırıq 2: Circuit Breaker State Machine

`ClaudeCircuitBreaker`-i test et: (a) 5 ardıcıl timeout → `OPEN`, (b) 30 saniyə sonra `HALF-OPEN`, (c) 1 uğurlu request → `CLOSED`. Hər state transition-ı log et. Filament-də circuit state-i real-time göstər. `OPEN` vəziyyətindən Slack-ə alert göndər.

### Tapşırıq 3: Graceful Degradation

Circuit breaker `OPEN` olduqda fallback strategiyasını implement et: (a) cache-dən köhnə cavabı qaytar (varsa), (b) yoxdursa rule-based cavab ver, (c) "AI müvəqqəti əlçatan deyil" mesajı göstər. Fallback halında user experience-i test et.

---

## Əlaqəli Mövzular

- `03-laravel-queue-ai-patterns.md` — Queue job-larda idempotency tətbiqi
- `../02-claude-api/11-rate-limits-retry-php.md` — Rate limit zamanı circuit breaker
- `../08-production/15-multi-provider-failover.md` — Circuit breaker + provider failover
- `05-webhook-async-ai.md` — Async workflow-da idempotency
