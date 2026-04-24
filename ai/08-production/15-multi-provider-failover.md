# Multi-Provider Failover — Claude, GPT, Gemini Arasında Sığorta

> **Problem**: Anthropic API bütün bölgədə 30 dəqiqə down oldu. Prod chatbot-un istifadəçilər üçün dayandı. SLA pozuldu. **Multi-provider failover** — bir provayder düşəndə digərinə avtomatik keçid.

---

## Mündəricat

1. [Niyə Failover Lazımdır](#why)
2. [Gateway Pattern](#gateway)
3. [Model Capability Mapping](#mapping)
4. [Prompt Portability](#portability)
5. [Failover Triggers](#triggers)
6. [Circuit Breaker per Provider](#circuit-breaker)
7. [Cost-Aware Routing](#cost-aware)
8. [Laravel Implementation](#laravel)
9. [Observability](#observability)
10. [A/B Testing Across Providers](#ab-testing)
11. [Incident Playbook](#incident)

---

## Niyə Failover Lazımdır <a name="why"></a>

### Real incident-lər (2024-2025)

- **2024-03**: Anthropic 4-saatlıq US-East outage — bütün enterprise Claude user-lər təsirli
- **2024-06**: OpenAI GPT-4 API degraded mode 2 gün — latency 10x
- **2024-11**: AWS Bedrock bir region-da sertifikat expire, Claude unavailable
- **2025-02**: Google Gemini API breaking change regex schema, retry tələbi

### Business impact

- **Chatbot down** → support costs up, customer frustration
- **AI-heavy feature** (meeting summarizer, code reviewer) — user visible latency
- **Batch jobs fail** → data pipeline behind schedule

### SLA planlaşdırma

Hər provayder öz SLA-sını elan edir (99.9%). Amma downtime distribution-u yox:

```
Single provider: 99.9% uptime → 8.76h/year downtime
Dual provider (independent failures): 99.9999% → 0.5 min/year
```

Failover SLA-nı 3 qat yüksəldir.

---

## Gateway Pattern <a name="gateway"></a>

```
┌─────────────────────────────────┐
│    Application Code              │
│    $ai->chat([...])              │
└────────┬────────────────────────┘
         │
┌────────▼────────────────────────┐
│      AiGateway                   │
│  - Primary: ClaudeDriver         │
│  - Fallback: OpenAIDriver        │
│  - Last resort: GeminiDriver     │
│  - Circuit breaker per driver    │
│  - Cost-aware routing            │
│  - Observability                 │
└────────┬────────────────────────┘
         │
         ▼
    Provider Drivers
    (Claude / OpenAI / Gemini / Bedrock / Vertex)
```

### Interface

```php
<?php
// app/Contracts/LlmDriver.php

namespace App\Contracts;

interface LlmDriver
{
    public function chat(array $messages, array $options = []): LlmResponse;
    public function stream(array $messages, array $options = []): iterable;
    public function name(): string;
    public function supportsStreaming(): bool;
    public function supportsToolUse(): bool;
}

class LlmResponse
{
    public function __construct(
        public string $text,
        public array $toolCalls,
        public array $usage,
        public string $stopReason,
        public string $providerId,   // "claude-sonnet-4-5"
        public string $providerName, // "anthropic"
    ) {}
}
```

---

## Model Capability Mapping <a name="mapping"></a>

Her provayderin model-ləri fərqli quality tier-lərindədir. Mapping table:

| Tier | Claude | OpenAI | Gemini | Use-case |
|------|--------|--------|--------|----------|
| **Frontier** | opus-4-5 | o3 / gpt-5 | gemini-2.5-pro | Kompleks reasoning, deep research |
| **Smart** | sonnet-4-5 | gpt-4o | gemini-2.5-flash | Default production |
| **Fast** | haiku-4-5 | gpt-4o-mini | gemini-2.5-flash-lite | Classification, simple tasks |
| **Embedding** | voyage-3 (via partner) | text-embedding-3-large | text-embedding-004 | RAG indexing |

### Config

```php
<?php
// config/ai.php

return [
    'providers' => [
        'claude' => [
            'driver' => \App\Services\Ai\Drivers\ClaudeDriver::class,
            'api_key' => env('ANTHROPIC_API_KEY'),
            'models' => [
                'frontier' => 'claude-opus-4-5',
                'smart' => 'claude-sonnet-4-5',
                'fast' => 'claude-haiku-4-5',
            ],
        ],
        'openai' => [
            'driver' => \App\Services\Ai\Drivers\OpenAiDriver::class,
            'api_key' => env('OPENAI_API_KEY'),
            'models' => [
                'frontier' => 'o3',
                'smart' => 'gpt-4o',
                'fast' => 'gpt-4o-mini',
            ],
        ],
        'gemini' => [
            'driver' => \App\Services\Ai\Drivers\GeminiDriver::class,
            'api_key' => env('GOOGLE_API_KEY'),
            'models' => [
                'frontier' => 'gemini-2.5-pro',
                'smart' => 'gemini-2.5-flash',
                'fast' => 'gemini-2.5-flash-lite',
            ],
        ],
    ],

    'routing' => [
        'default_tier' => 'smart',
        'primary_provider' => 'claude',
        'fallback_chain' => ['claude', 'openai', 'gemini'],
    ],

    'circuit_breaker' => [
        'threshold' => 5,         // failures before opening
        'cooldown_seconds' => 60, // how long open stays open
        'half_open_attempts' => 2,
    ],
];
```

---

## Prompt Portability <a name="portability"></a>

Claude, GPT və Gemini öz API-larına fərqli sxem istəyir. Gateway bu fərqləri aradan qaldırır.

### Common message format (provider-neutral)

```php
$messages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => 'What is Laravel?'],
    ['role' => 'assistant', 'content' => 'Laravel is a PHP framework...'],
    ['role' => 'user', 'content' => 'Show me a code example.'],
];
```

### Claude adapter

Claude system prompt-u separate field-də istəyir:

```php
class ClaudeDriver implements LlmDriver
{
    public function chat(array $messages, array $options = []): LlmResponse
    {
        $system = null;
        $filteredMessages = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system = $m['content'];
            } else {
                $filteredMessages[] = $m;
            }
        }

        $response = $this->client->messages()->create([
            'model' => $this->resolveModel($options['tier'] ?? 'smart'),
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'system' => $system,
            'messages' => $filteredMessages,
            'tools' => $this->translateTools($options['tools'] ?? []),
        ]);

        return new LlmResponse(
            text: $response->content[0]->text ?? '',
            toolCalls: $this->extractToolCalls($response),
            usage: [
                'input_tokens' => $response->usage->input_tokens,
                'output_tokens' => $response->usage->output_tokens,
            ],
            stopReason: $response->stop_reason,
            providerId: $response->model,
            providerName: 'anthropic',
        );
    }
}
```

### OpenAI adapter

OpenAI system `messages[0]`-da qalır:

```php
class OpenAiDriver implements LlmDriver
{
    public function chat(array $messages, array $options = []): LlmResponse
    {
        $response = $this->client->chat()->create([
            'model' => $this->resolveModel($options['tier'] ?? 'smart'),
            'messages' => $messages,
            'tools' => $this->translateTools($options['tools'] ?? []),
            'max_tokens' => $options['max_tokens'] ?? 1024,
        ]);

        $msg = $response->choices[0]->message;

        return new LlmResponse(
            text: $msg->content ?? '',
            toolCalls: $this->extractToolCalls($msg),
            usage: [
                'input_tokens' => $response->usage->promptTokens,
                'output_tokens' => $response->usage->completionTokens,
            ],
            stopReason: $response->choices[0]->finishReason,
            providerId: $response->model,
            providerName: 'openai',
        );
    }
}
```

### Tool translation

Hər provayder tool schema-sını bir az fərqli istəyir. Common format:

```php
// Portable format
$tools = [
    [
        'name' => 'get_weather',
        'description' => '...',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string'],
            ],
            'required' => ['city'],
        ],
    ],
];

// Claude translation
['name' => 'get_weather', 'description' => '...', 'input_schema' => [...]]

// OpenAI translation
['type' => 'function', 'function' => ['name' => 'get_weather', ...]]

// Gemini translation
['functionDeclarations' => [['name' => 'get_weather', ...]]]
```

### Unsupported features fallback

- Extended thinking — only Claude. OpenAI-də disabled-dır. Gateway `thinking` option-u `ignoreUnsupported` ilə səssiz düşür.
- Prompt caching — Claude və OpenAI fərqli syntax. Gateway unifies.

---

## Failover Triggers <a name="triggers"></a>

Nə zaman növbəti provayderə keç?

| Trigger | Action |
|---------|--------|
| **HTTP 5xx** | Retry once, sonra fallback |
| **HTTP 429 rate limit** | Fallback (öz rate limit backoff işləmir sürətli) |
| **HTTP 408/504 timeout** | Fallback (öz retry edilmiş) |
| **Connection error** | Fallback |
| **Response > 30s** | Timeout və fallback |
| **Circuit breaker OPEN** | Direct fallback (skip primary) |
| **`success=false`** | Fallback yox — legit application error |

```php
class AiGateway
{
    public function chat(array $messages, array $options = []): LlmResponse
    {
        $chain = config('ai.routing.fallback_chain');
        $lastException = null;

        foreach ($chain as $providerName) {
            if ($this->circuit->isOpen($providerName)) {
                Log::warning("ai.circuit_open", ['provider' => $providerName]);
                continue;
            }

            try {
                $start = microtime(true);
                $driver = app("ai.drivers.{$providerName}");
                $response = $driver->chat($messages, $options);

                $this->circuit->recordSuccess($providerName);
                $this->recordMetrics($providerName, $response, microtime(true) - $start);

                return $response;
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->circuit->recordFailure($providerName);
                Log::error("ai.provider_failed", [
                    'provider' => $providerName,
                    'error' => $e->getMessage(),
                ]);

                // Fallback edə bilirsənmi?
                if (!$this->isRecoverable($e)) {
                    throw $e; // application-level error, retry mənasız
                }
                // Davam et next provider
            }
        }

        throw new \RuntimeException("All AI providers failed: " . $lastException?->getMessage());
    }

    private function isRecoverable(\Throwable $e): bool
    {
        // 4xx client errors (except 429) usually mean bad request — no fallback
        if ($e instanceof ClientException) {
            $status = $e->getResponse()?->getStatusCode();
            return in_array($status, [408, 429, 502, 503, 504]);
        }
        return true; // connection errors, 5xx — try next
    }
}
```

---

## Circuit Breaker per Provider <a name="circuit-breaker"></a>

Redis-backed circuit breaker. Detaylar: `/home/orkhan/Projects/claude/ai/07-workflows/04-ai-idempotency-circuit-breaker.md`.

```php
<?php
// app/Services/Ai/CircuitBreaker.php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    private int $threshold;
    private int $cooldown;

    public function __construct()
    {
        $this->threshold = config('ai.circuit_breaker.threshold', 5);
        $this->cooldown = config('ai.circuit_breaker.cooldown_seconds', 60);
    }

    public function isOpen(string $provider): bool
    {
        $state = Redis::get("cb:{$provider}:state");
        if ($state !== 'open') return false;

        $openedAt = (int) Redis::get("cb:{$provider}:opened_at");
        if (time() - $openedAt > $this->cooldown) {
            Redis::set("cb:{$provider}:state", 'half_open');
            return false;  // half-open — qeyri-məhdud sayda yoxla
        }

        return true;
    }

    public function recordFailure(string $provider): void
    {
        $failures = Redis::incr("cb:{$provider}:failures");
        Redis::expire("cb:{$provider}:failures", 300);  // 5dk window

        if ($failures >= $this->threshold) {
            Redis::set("cb:{$provider}:state", 'open');
            Redis::set("cb:{$provider}:opened_at", time());

            Log::alert("circuit_breaker.opened", ['provider' => $provider]);
            // Alert: PagerDuty
        }
    }

    public function recordSuccess(string $provider): void
    {
        Redis::del("cb:{$provider}:failures");
        Redis::del("cb:{$provider}:state");
    }
}
```

---

## Cost-Aware Routing <a name="cost-aware"></a>

Default bütün traffic Claude-a. Amma bəzən Gemini ucuz və kifayətdir:

```php
public function route(string $useCase, array $messages): LlmResponse
{
    $strategy = config("ai.routing.strategies.{$useCase}") ?? 'primary_with_fallback';

    return match ($strategy) {
        'primary_with_fallback' => $this->chain(['claude', 'openai', 'gemini'], $messages),
        'cheapest_first' => $this->chain(['gemini', 'openai', 'claude'], $messages),
        'quality_first' => $this->chain(['claude', 'openai'], $messages),  // no gemini
        'batch_cost_optimized' => $this->useBatchApi('gemini', $messages),
        'random_ab' => $this->routeByExperiment($messages),
    };
}
```

`config/ai.php`:

```php
'routing' => [
    'strategies' => [
        'support_chat' => 'primary_with_fallback',      // quality + reliability
        'email_classification' => 'cheapest_first',     // simple task
        'legal_review' => 'quality_first',              // no fallback to lower-quality
        'log_summarization' => 'batch_cost_optimized',  // non-realtime
    ],
],
```

---

## Laravel Implementation <a name="laravel"></a>

### Service Provider

```php
<?php
// app/Providers/AiServiceProvider.php

namespace App\Providers;

use App\Contracts\LlmDriver;
use App\Services\Ai\{AiGateway, CircuitBreaker};
use App\Services\Ai\Drivers\{ClaudeDriver, OpenAiDriver, GeminiDriver};
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('ai.providers') as $name => $cfg) {
            $this->app->singleton("ai.drivers.{$name}", fn() => new $cfg['driver']($cfg));
        }

        $this->app->singleton(CircuitBreaker::class);
        $this->app->singleton(AiGateway::class);

        $this->app->bind(LlmDriver::class, AiGateway::class);
    }
}
```

### İstifadə

```php
class ChatService
{
    public function __construct(private LlmDriver $llm) {}

    public function reply(string $message): string
    {
        $response = $this->llm->chat(
            messages: [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $message],
            ],
            options: [
                'tier' => 'smart',
                'use_case' => 'support_chat',
                'max_tokens' => 1000,
            ],
        );

        return $response->text;
    }
}
```

---

## Observability <a name="observability"></a>

### Metrics (Prometheus)

```
ai_request_total{provider, model, status}
ai_request_duration_seconds{provider, model}
ai_fallback_total{from_provider, to_provider}
ai_circuit_breaker_open_total{provider}
ai_tokens_total{provider, type}
ai_cost_usd_total{provider, tier}
```

### Grafana Dashboard panels

- Per-provider latency (p50, p95, p99)
- Success/failure rate per provider
- Fallback rate (primary → secondary)
- Circuit breaker state (timeline chart)
- Cost per hour per provider
- Request distribution (which provider served how many)

### Request log structure

```json
{
  "timestamp": "2026-04-21T12:34:56Z",
  "request_id": "req_01HXY...",
  "use_case": "support_chat",
  "tier": "smart",
  "provider_attempted": ["claude", "openai"],
  "provider_succeeded": "openai",
  "failover_reason": "claude:503",
  "latency_ms": 1234,
  "input_tokens": 500,
  "output_tokens": 200,
  "cost_usd": 0.012
}
```

---

## A/B Testing Across Providers <a name="ab-testing"></a>

Quality-ni production trafikdə ölç:

```php
<?php
// app/Services/Ai/Experiment.php

class ProviderAbTest
{
    public function variant(string $userId): string
    {
        $hash = crc32($userId . 'ai-provider-exp-v1');
        return $hash % 100 < 50 ? 'claude' : 'openai';
    }
}

// Gateway-də
if ($options['experiment'] ?? false) {
    $chain = [$this->experiment->variant(auth()->id()), ...];
}
```

Feedback loop:
- User thumbs up/down → track per variant
- Human evaluator sample-ləri oxuyur → quality score
- After 1 week → statistical significance test (chi-square)

Bax: `/home/orkhan/Projects/claude/ai/07-workflows/07-ai-ab-testing.md`.

---

## Incident Playbook <a name="incident"></a>

### Provider down alert gələndə

1. **Check status page**: status.anthropic.com, status.openai.com
2. **Check your dashboard**: Grafana "provider success rate" panel
3. **Circuit breaker auto-opened?** — Gateway fallback işləyir, traffic redirected
4. **Still failing?** — manual override:

```bash
# Force Gemini until Claude stable
php artisan ai:route:force --provider=gemini --duration=30m
```

5. **Customer impact update** — status page-i yenilə, incident notify
6. **Post-incident review**: fallback neçə uğurlu oldu? Quality degradation varsa, A/B data göstər

### Force-route command

```php
<?php
// app/Console/Commands/ForceAiProvider.php

public function handle()
{
    $provider = $this->option('provider');
    $duration = $this->option('duration'); // e.g. "30m"
    $seconds = $this->parseDuration($duration);

    Redis::setex('ai:force_provider', $seconds, $provider);

    $this->info("All AI requests will be routed to {$provider} for {$duration}");
}
```

Gateway-də check:

```php
$forced = Redis::get('ai:force_provider');
if ($forced) {
    $chain = [$forced];  // override fallback chain
}
```

---

## Xülasə

| Element | Faydası |
|---------|---------|
| Gateway pattern | Provider switching bir-line config change |
| Portable message format | System prompt, tools, streaming unified |
| Capability mapping (tier-lər) | "smart" ⇒ sonnet/gpt-4o/gemini-flash |
| Failover triggers | 5xx, 429, timeout, connection error |
| Circuit breaker per provider | Independent isolation |
| Cost-aware routing | Per use-case strategy |
| Force-override command | Incident response 30 saniyə |
| Observability | Prometheus + Grafana dashboard |
| A/B testing | Quality ölçmə production-da |

**Yadda saxla**: failover pattern **day 1-də qurul**. Sonradan retrofit etmək 3x daha çox iş tələb edir. Gateway + interface abstraction faktiki maliyyət: 200 sətir kod. Reward: incident zamanı downtime eliminasiya.

Növbəti: `/home/orkhan/Projects/claude/ai/07-workflows/04-ai-idempotency-circuit-breaker.md` — circuit breaker dərindən.
