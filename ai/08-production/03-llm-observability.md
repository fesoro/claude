# LLM Müşahidəlilik (Observability): Tracing, Xərc və Eval-in-Prod (Senior)

> **Oxucu kütləsi:** Senior developerlər və arxitektlər
> **Bu faylın 02-dən fərqi:** 02 — ümumi observability prinsipləri. Bu fayl — **konkret tracing backendləri** (Langfuse, Helicone, LangSmith, Phoenix), OpenTelemetry semantic conventions, TTFT/tokens-per-sec breakdown, production-a eval inteqrasiyası və Anthropic SDK-nı Laravel-də instrumentasiya etmək.

---

## 1. LLM Müşahidəliliyinin Üç Prinsipi

Klassik APM (Datadog, New Relic) LLM iş yüklərinə uyğun deyil, çünki:

1. **Latency distribution multimodaldır** — cache hit (200 ms) vs cache miss (8 s) vs streaming (TTFT 400 ms + 20 s total). Mean yarı yalan danışır.
2. **Xərc birinci dərəcəli metrikdir** — CPU saniyə yox, $/sorğu. Per-user, per-feature breakdown olmasa, unit economics hesablaya bilmirsiniz.
3. **Keyfiyyət tiplendirilmiş metrik deyil** — "200 OK" LLM üçün mənasızdır. Cavab hallüsinasiya ola bilər, ama HTTP sağlamdır. Buna görə **eval-in-prod** lazımdır.

### Observability-nin Dörd Qatı (LLM Spesifik)

```
1. Trace      → İstifadəçi sorğusu → bütün LLM çağırışları + tool çağırışları → nəticə
2. Metric     → TTFT, tokens/sec, xərc, cache hit %, error rate, eval score
3. Log        → Prompt + response (PII scrub edildikdən sonra), tool args
4. Dataset    → Production-dan seçilmiş traces → regression test suite-ə axın
```

Son qat — **dataset** — klassik observability-də yoxdur. LLM-də traces sadəcə izləmə deyil, həm də yeni evalların mənbəyidir.

---

## 2. OpenTelemetry GenAI Semantic Conventions

OTel 2025-ci ildə GenAI spesifikasiyasını stabil etdi. Standart attribute adları:

| Attribute | Nümunə dəyər | İzah |
|-----------|--------------|------|
| `gen_ai.system` | `anthropic` | Provayder |
| `gen_ai.request.model` | `claude-opus-4-7` | Sorğu olunan model |
| `gen_ai.response.model` | `claude-opus-4-7-20260115` | Faktiki cavab verən versiya |
| `gen_ai.usage.input_tokens` | `1234` | Input tokenləri |
| `gen_ai.usage.output_tokens` | `567` | Output tokenləri |
| `gen_ai.usage.cache_read_tokens` | `4096` | Cache hit tokenləri |
| `gen_ai.usage.cache_creation_tokens` | `0` | Yeni yazılan cache |
| `gen_ai.request.temperature` | `0.0` | |
| `gen_ai.response.finish_reason` | `end_turn` / `tool_use` / `max_tokens` | |
| `gen_ai.operation.name` | `chat` / `completion` / `embedding` | |

Standartdan istifadə edin — Datadog, Grafana Tempo, Honeycomb, Langfuse hamısı bu attribute adlarını `gen_ai.*` kimi tanıyır.

---

## 3. Bazar Müqayisəsi: Langfuse vs Helicone vs LangSmith vs Phoenix

| Alət | Model | Hosting | Güclü tərəf | Zəif tərəf | Qiymət |
|------|-------|---------|-------------|-----------|--------|
| **Langfuse** | OSS (MIT) | Self-host və ya cloud | Prompt management + eval + trace, ən dolğun paket | UI bəzi yerdə ağır | Cloud $49/ay+, self-host pulsuz |
| **Helicone** | OSS | Proxy (edge) | 1 sətir inteqrasiya (base_url dəyişdir), async logging, zero latency overhead | Trace modeli Langfuse qədər dərin deyil | Pulsuz 100K/ay, $20/ay+ |
| **LangSmith** | Qapalı | SaaS | LangChain inteqrasiyası, güclü eval UI | LangChain olmayan kodda daha çox boilerplate, SaaS-only | $39/istifadəçi/ay+ |
| **Phoenix (Arize)** | OSS | Self-host və ya cloud | OpenTelemetry-native, retrieval analysis | Prompt management yoxdur | Pulsuz OSS, Arize AX ayrıca qiymət |
| **OpenLLMetry** | OSS library | Hansı backend olsa | Vendor-neutral, pure OTel | Sadəcə kitabxana, UI özünüz gətirin | Pulsuz |

### Necə seçilir

- **Yeni başlayırsınız, prompt versioning + trace + eval lazımdır** → Langfuse (self-host)
- **Zero-config, sadəcə "bütün çağırışları log et"** → Helicone proxy
- **Artıq Datadog/Honeycomb/Grafana varsa** → OpenLLMetry + mövcud OTel backend
- **Retrieval/RAG heavy** → Phoenix (ən yaxşı vektor trace vizualizasiyası)

---

## 4. Laravel Middleware — Anthropic SDK Instrumentasiyası

Aşağıda `AnthropicTracer` servis — hər çağırışı OTel span-a və Langfuse trace-ə yazır.

```php
<?php
// app/Services/AI/Observability/AnthropicTracer.php

namespace App\Services\AI\Observability;

use Anthropic\Anthropic;
use Anthropic\Resources\Messages;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

/**
 * Bütün Anthropic SDK çağırışlarını OpenTelemetry span-a çevirir.
 *
 * GenAI semantic conventions-ə riayət edir. Datadog, Grafana Tempo,
 * Langfuse, Phoenix — hamısı eyni attribute-ları başa düşür.
 */
class AnthropicTracer
{
    public function __construct(
        private Anthropic $client,
        private PiiScrubber $scrubber,
        private CostCalculator $cost,
    ) {}

    public function messages(): TracedMessages
    {
        return new TracedMessages(
            $this->client->messages(),
            $this->scrubber,
            $this->cost,
        );
    }
}

class TracedMessages
{
    public function __construct(
        private Messages $inner,
        private PiiScrubber $scrubber,
        private CostCalculator $cost,
    ) {}

    public function create(array $params): mixed
    {
        $tracer = Globals::tracerProvider()->getTracer('anthropic-sdk', '1.0');
        $spanName = 'anthropic.messages.create';

        $span = $tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('gen_ai.system', 'anthropic')
            ->setAttribute('gen_ai.operation.name', 'chat')
            ->setAttribute('gen_ai.request.model', $params['model'] ?? 'unknown')
            ->setAttribute('gen_ai.request.max_tokens', $params['max_tokens'] ?? null)
            ->setAttribute('gen_ai.request.temperature', $params['temperature'] ?? 1.0)
            ->setAttribute('user.id', (string) (auth()->id() ?? 'anonymous'))
            ->setAttribute('feature', request()->header('X-AI-Feature', 'unknown'))
            ->startSpan();

        $scope = $span->activate();
        $startNs = hrtime(true);

        try {
            $response = $this->inner->create($params);

            $latencyMs = (hrtime(true) - $startNs) / 1e6;
            $usage = $response->usage;
            $model = $response->model;

            $span->setAttributes([
                'gen_ai.response.model' => $model,
                'gen_ai.response.finish_reason' => $response->stopReason,
                'gen_ai.usage.input_tokens' => $usage->inputTokens,
                'gen_ai.usage.output_tokens' => $usage->outputTokens,
                'gen_ai.usage.cache_read_tokens' => $usage->cacheReadInputTokens ?? 0,
                'gen_ai.usage.cache_creation_tokens' => $usage->cacheCreationInputTokens ?? 0,
                'gen_ai.latency_ms' => $latencyMs,
                'gen_ai.cost_usd' => $this->cost->estimate($model, $usage),
            ]);

            // PII scrub edilmiş prompt/response-u event kimi əlavə et
            $span->addEvent('gen_ai.content.prompt', [
                'content' => $this->scrubber->scrub(
                    json_encode($params['messages'] ?? [])
                ),
            ]);

            $span->addEvent('gen_ai.content.completion', [
                'content' => $this->scrubber->scrub(
                    $this->extractText($response)
                ),
            ]);

            $span->setStatus(StatusCode::STATUS_OK);
            return $response;

        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->setAttribute('error.type', $this->classifyError($e));
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    private function classifyError(\Throwable $e): string
    {
        return match (true) {
            str_contains($e->getMessage(), 'rate_limit') => 'rate_limit',
            str_contains($e->getMessage(), 'overloaded') => 'overloaded',
            str_contains($e->getMessage(), 'context_length') => 'context_overflow',
            str_contains($e->getMessage(), 'invalid_request') => 'invalid_request',
            $e->getCode() >= 500 => 'provider_5xx',
            default => 'unknown',
        };
    }

    private function extractText($response): string
    {
        $parts = [];
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $parts[] = $block->text;
            } elseif ($block->type === 'tool_use') {
                $parts[] = "[tool_use:{$block->name}]";
            }
        }
        return implode("\n", $parts);
    }
}
```

Bu wrapper-i `AppServiceProvider`-də bind edin:

```php
$this->app->singleton(AnthropicTracer::class, function ($app) {
    return new AnthropicTracer(
        Anthropic::factory()->withApiKey(config('services.anthropic.key'))->make(),
        $app->make(PiiScrubber::class),
        $app->make(CostCalculator::class),
    );
});
```

Artıq bütün kod `$anthropic->messages()` əvəzinə `$tracer->messages()` istifadə edəndə avtomatik instrumentasiya olur.

---

## 5. Cost Calculator (Current 2026-04 Prices)

```php
<?php
// app/Services/AI/Observability/CostCalculator.php

namespace App\Services\AI\Observability;

class CostCalculator
{
    /**
     * Qiymətlər $ / 1M token. 2026-04 itibariilə Anthropic rəsmi qiymətləri.
     * Cache read 10% əsas input qiymətindən. Cache write 25% əlavə.
     */
    private const PRICING = [
        'claude-opus-4-7' => [
            'input' => 15.00,
            'output' => 75.00,
            'cache_read' => 1.50,
            'cache_write' => 18.75,
        ],
        'claude-sonnet-4-6' => [
            'input' => 3.00,
            'output' => 15.00,
            'cache_read' => 0.30,
            'cache_write' => 3.75,
        ],
        'claude-haiku-4-5-20251001' => [
            'input' => 1.00,
            'output' => 5.00,
            'cache_read' => 0.10,
            'cache_write' => 1.25,
        ],
    ];

    public function estimate(string $model, $usage): float
    {
        $base = $this->resolvePricing($model);

        $input = ($usage->inputTokens ?? 0) * $base['input'];
        $output = ($usage->outputTokens ?? 0) * $base['output'];
        $cacheRead = ($usage->cacheReadInputTokens ?? 0) * $base['cache_read'];
        $cacheWrite = ($usage->cacheCreationInputTokens ?? 0) * $base['cache_write'];

        return ($input + $output + $cacheRead + $cacheWrite) / 1_000_000;
    }

    private function resolvePricing(string $model): array
    {
        // claude-opus-4-7-20260115 → claude-opus-4-7
        foreach (self::PRICING as $prefix => $prices) {
            if (str_starts_with($model, $prefix)) {
                return $prices;
            }
        }
        // Unknown model — fallback Sonnet qiymətinə
        return self::PRICING['claude-sonnet-4-6'];
    }
}
```

---

## 6. Latency Breakdown: TTFT, Tokens/Sec, İdle Time

Streaming cavabı üçün üç fərqli latency metrikası var:

### TTFT (Time To First Token)

İstifadəçi "göndər" basandan ilk token ekranda görünənə qədər. UX-də ən kritik metrik — 500 ms-dən yüksəksə, istifadəçi laqqırtı hiss edir.

### TBT (Time Between Tokens) / Tokens-per-Second

Stream başlayandan sonra, tokenlərin axma sürəti. Claude Opus orta 40-60 tok/s, Sonnet 60-80, Haiku 100-140.

### Total Latency

TTFT + stream length × (1 / tok_per_sec). Uzun cavablarda stream length dominant olur.

```php
<?php
// Streaming zamanı bu üçünü ayrıca ölç

$start = hrtime(true);
$firstTokenNs = null;
$tokenCount = 0;

$stream = $anthropic->messages()->createStream([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => $messages,
]);

foreach ($stream as $event) {
    if ($event->type === 'content_block_delta') {
        if ($firstTokenNs === null) {
            $firstTokenNs = hrtime(true);
            $ttftMs = ($firstTokenNs - $start) / 1e6;
            $span->setAttribute('gen_ai.ttft_ms', $ttftMs);
        }
        $tokenCount++;
    }
}

$totalMs = (hrtime(true) - $start) / 1e6;
$streamMs = (hrtime(true) - $firstTokenNs) / 1e6;
$tokPerSec = $tokenCount / ($streamMs / 1000);

$span->setAttributes([
    'gen_ai.total_latency_ms' => $totalMs,
    'gen_ai.stream_latency_ms' => $streamMs,
    'gen_ai.tokens_per_second' => round($tokPerSec, 1),
    'gen_ai.output_token_count' => $tokenCount,
]);
```

### Dashboard

Grafana-da 3 ayrı panel:

1. **TTFT p50/p95/p99** — UX SLO-su. Məsələn: p95 < 800 ms.
2. **Tokens/sec p50** — provayder performance. Ani düşmə → Anthropic-də problem.
3. **Total latency histogram** — istifadəçi baxımından tam gözləmə.

Tək bir "latency" metriki ilə bu üç fərqli problemi qarışdırırsınız.

---

## 7. Token Distribution Analizi

"Orta" prompt uzunluğu çoxluqla yalan danışır. Real istifadəyə baxdıqda distribution bimodal və ya long-tail olur.

```sql
-- ClickHouse və ya BigQuery-da
SELECT
    feature,
    quantile(0.5)(input_tokens) AS p50,
    quantile(0.95)(input_tokens) AS p95,
    quantile(0.99)(input_tokens) AS p99,
    max(input_tokens) AS max,
    count() AS calls,
    sum(cost_usd) AS total_cost
FROM ai_calls
WHERE timestamp > now() - INTERVAL 7 DAY
GROUP BY feature
ORDER BY total_cost DESC;
```

Nümunə nəticə:

| Feature | p50 | p95 | p99 | Max | Calls | Cost |
|---------|-----|-----|-----|-----|-------|------|
| chat | 800 | 12K | 45K | 180K | 1.2M | $4.2K |
| code-review | 6K | 80K | 150K | 200K | 45K | $8.1K |
| summarize | 3K | 25K | 40K | 50K | 120K | $1.8K |

Burada `code-review` feature total sorğu sayı az, ama xərc dominant — hər çağırışda 6-80K token. Optimizasiya prioriteti: cache + chunk ayrılması.

---

## 8. Error Classification

HTTP status kodu kifayət deyil. Anthropic API-dan gələn xətaları 5-6 kateqoriyaya ayırın:

| Kateqoriya | Tipik səbəb | Response strategiyası |
|------------|-------------|----------------------|
| `rate_limit` (429) | RPM/TPM aşımı | Backoff + retry, token bucket |
| `overloaded` (529) | Provider capacity | Fallback model (Sonnet → Haiku) |
| `context_overflow` | Input > 200K / 1M | Prompt truncate, chunking |
| `invalid_request` (400) | Tool schema səhvdir, malformed JSON | Sərt failure — code bug |
| `provider_5xx` | Anthropic internal | Retry 1-2 dəfə, sonra alert |
| `content_policy` | Claude imtina etdi | Retry etmə, user-ə bildir |
| `timeout` | Network və ya çox uzun gen | Retry + timeout artır |

Hər birini ayrı metrik kimi izləyin. `content_policy` sıçraması prompt injection hücumu ola bilər.

---

## 9. PII Scrubbing

Prompt/response-u log edəndə GDPR/HIPAA/PCI narahatlığı var. Heç vaxt raw text log etməyin.

```php
<?php
// app/Services/AI/Observability/PiiScrubber.php

namespace App\Services\AI\Observability;

class PiiScrubber
{
    private array $patterns = [
        // Email
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '<EMAIL>',
        // Kredit kartı (sadə Luhn yoxlamadan sonra tətbiq oluna bilər)
        '/\b(?:\d[ -]*?){13,19}\b/' => '<CC>',
        // SSN (US)
        '/\b\d{3}-\d{2}-\d{4}\b/' => '<SSN>',
        // Telefon (E.164-ə yaxın)
        '/\+?\d{1,3}[-.\s]?\(?\d{3}\)?[-.\s]?\d{3,4}[-.\s]?\d{4}/' => '<PHONE>',
        // IP (v4)
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '<IP>',
        // API açarları (sk-*, AKIA*, Bearer tokenlər)
        '/(sk-[A-Za-z0-9]{32,}|AKIA[A-Z0-9]{16}|Bearer\s+[A-Za-z0-9._-]{20,})/' => '<SECRET>',
        // UUID (ehtimalla user_id və ya tenant_id — kontekst yox etmək istəmirsiniz)
        // Burada saxlayırıq, amma kompliansa görə açın:
        // '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i' => '<UUID>',
    ];

    public function scrub(string $text): string
    {
        foreach ($this->patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        return $text;
    }
}
```

Daha dərin scrubbing üçün Microsoft **Presidio** (Python service) və ya AWS **Comprehend PII** API çağırın. Regex yalnız ilkin müdafiə qatıdır.

---

## 10. Evals-in-Prod: Production Trafikdə Keyfiyyət Ölçmə

Offline eval dataset statik olur. Production trafikdə real davranışı ölçmək üçün **LLM-as-judge** həlqəsi qurun.

```php
<?php
// app/Jobs/ScoreProductionTraceJob.php

namespace App\Jobs;

use App\Models\AICallLog;
use App\Services\AI\Observability\LLMJudge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ScoreProductionTraceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public string $callId) {}

    public function handle(LLMJudge $judge): void
    {
        $call = AICallLog::findOrFail($this->callId);

        // Sampling — hər 100 çağırışdan 1-ni qiymətləndir
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        $score = $judge->score(
            prompt: $call->prompt_preview,
            response: $call->response_preview,
            criteria: match ($call->feature) {
                'summarize' => ['faithfulness', 'coverage', 'brevity'],
                'code-review' => ['correctness', 'specificity', 'tone'],
                'chat' => ['helpfulness', 'safety', 'relevance'],
                default => ['overall_quality'],
            },
        );

        $call->update([
            'eval_score' => $score->average,
            'eval_details' => $score->details,
            'eval_judge_model' => 'claude-haiku-4-5-20251001',
            'eval_at' => now(),
        ]);

        // Eşik altındakıları alert et
        if ($score->average < 0.6) {
            event(new LowQualityAIResponse($call, $score));
        }
    }
}
```

### Sampling strategiyaları

- **Uniform 1%** — bazar nəzarəti
- **Error-biased 100%** — hər xətanı qiymətləndir
- **Stratified by feature** — az istifadə olunan features-də nisbət artır
- **Adversarial sampling** — low confidence üçün 100%

---

## 11. A/B Testing İnteqrasiyası

Prompt v1 vs v2, Opus vs Sonnet, temperature 0 vs 0.7 — hansı daha yaxşıdır?

```php
<?php
// app/Services/AI/PromptExperiment.php

namespace App\Services\AI;

use App\Services\AI\Observability\AnthropicTracer;

class PromptExperiment
{
    public function __construct(
        private AnthropicTracer $tracer,
        private ExperimentFlags $flags,
    ) {}

    public function summarize(string $text): string
    {
        $userId = auth()->id();
        $variant = $this->flags->assign('summarize_v2_experiment', $userId);

        $prompt = match ($variant) {
            'control' => "Summarize this text in 3 bullets:\n\n{$text}",
            'treatment' => "You are a professional editor. Extract the 3 most important points as concise bullets. Text:\n\n{$text}",
        };

        $response = $this->tracer->messages()->create([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 512,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'metadata' => [
                'user_id' => (string) $userId,
            ],
        ]);

        // Trace-ə variant attach et
        \OpenTelemetry\API\Trace\Span::getCurrent()
            ->setAttribute('experiment.name', 'summarize_v2_experiment')
            ->setAttribute('experiment.variant', $variant);

        return $this->extractText($response);
    }
}
```

Sonra dashboard-da variant breakdown: eval_score(control) vs eval_score(treatment), cost, latency. Statistical significance üçün ən az 1000 trace hər variant-da gözləyin.

---

## 12. Dashboard Layoutu (Grafana nümunəsi)

Bir LLM dashboard-unda olmalı panel-lər:

**Sıra 1 — Biznes KPI-ləri**
- Günlük total xərc ($)
- Günlük çağırış sayı
- Xərc per 1000 aktiv istifadəçi
- Eval score orta (7 günlük)

**Sıra 2 — Performans**
- TTFT p50/p95/p99 (streaming)
- Total latency p50/p95 (non-stream)
- Tokens/sec orta
- Cache hit faizi

**Sıra 3 — Sağlamlıq**
- Error rate by error.type (stacked)
- Rate limit hits per dəqiqə
- Fallback model usage faizi
- Content policy block-ları per saat

**Sıra 4 — Feature breakdown**
- Top 10 feature by cost
- Top 10 feature by latency
- Input token p95 by feature
- Output token p95 by feature

**Sıra 5 — Keyfiyyət**
- Eval score trend (7 / 30 gün)
- Low-score traces siyahısı (klik → detallı view)
- A/B variant müqayisəsi

---

## 13. Xərcin Per-User və Per-Feature Attribution

Abunə SaaS-da unit economics kritikdir. "AI xərci gəlirin 12%-i" kimi bir göstərici lazımdır, ama yalnız total məlum olsa, hansı istifadəçinin və hansı feature-in dominant olduğunu bilmirsiniz.

```sql
-- Top 10 bahalı user, feature breakdown ilə
SELECT
    user_id,
    sum(cost_usd) AS total_cost,
    sum(if(feature = 'chat', cost_usd, 0)) AS chat_cost,
    sum(if(feature = 'code-review', cost_usd, 0)) AS code_cost,
    sum(if(feature = 'summarize', cost_usd, 0)) AS summary_cost,
    count() AS calls
FROM ai_calls
WHERE timestamp > now() - INTERVAL 30 DAY
GROUP BY user_id
ORDER BY total_cost DESC
LIMIT 10;
```

Burada bir istifadəçinin $500/ay xərci olarsa və onun abunəliyi $20/aydırsa, ya price plan yoxdur, ya da abuse var. Hər ikisi action tələb edir.

### Per-user xərc guardrail

```php
<?php
// app/Services/AI/UserCostGuard.php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserCostGuard
{
    public function check(int $userId): void
    {
        $dailyCost = Cache::remember(
            "ai_cost:user:{$userId}:" . now()->toDateString(),
            300, // 5 dəq cache
            fn () => DB::table('ai_calls')
                ->where('user_id', $userId)
                ->whereDate('created_at', now()->toDateString())
                ->sum('cost_usd')
        );

        $plan = auth()->user()->plan;
        $limit = match ($plan) {
            'free' => 0.50,
            'pro' => 10.00,
            'team' => 100.00,
            default => 1.00,
        };

        if ($dailyCost > $limit) {
            throw new DailyQuotaExceeded($plan, $limit, $dailyCost);
        }

        if ($dailyCost > $limit * 0.8) {
            // Notification: 80% istifadə
            event(new ApproachingQuota($userId, $dailyCost, $limit));
        }
    }
}
```

---

## 14. Dataset Akkumulyasiyası — Production-dan Regression Suite

Ən vacib observability pattern-lərindən biri: real production trace-ləri eval dataset-ə axıtmaq.

```php
<?php
// app/Console/Commands/PromoteTraceToEvalSet.php

namespace App\Console\Commands;

use App\Models\AICallLog;
use App\Models\EvalSet;
use Illuminate\Console\Command;

class PromoteTraceToEvalSet extends Command
{
    protected $signature = 'ai:promote-trace {callId} {--set=regression} {--expected=}';

    public function handle(): int
    {
        $call = AICallLog::findOrFail($this->argument('callId'));
        $setName = $this->option('set');
        $expected = $this->option('expected');

        EvalSet::updateOrCreate(
            ['set_name' => $setName, 'source_call_id' => $call->id],
            [
                'input_prompt' => $call->prompt_preview,
                'reference_output' => $expected ?? $call->response_preview,
                'feature' => $call->feature,
                'model_at_capture' => $call->model,
                'tags' => ['source:production', 'captured:' . now()->toDateString()],
            ]
        );

        $this->info("Trace {$call->id} promoted to eval set '{$setName}'.");
        return 0;
    }
}
```

CI-də bu dataset üzərində hər prompt dəyişikliyindən əvvəl regression run işlədin.

---

## 15. Müsahibə Xülasəsi

- **LLM observability klassik APM-dən fərqlidir**: xərc first-class, keyfiyyət non-trivial, latency multimodal.
- **OpenTelemetry GenAI semantic conventions** standart attribute adlarıdır — `gen_ai.system`, `gen_ai.request.model`, `gen_ai.usage.input_tokens`.
- **Tracing alətləri**: Langfuse (all-in-one OSS), Helicone (proxy zero-config), LangSmith (LangChain SaaS), Phoenix (OTel-native retrieval).
- **Latency breakdown**: TTFT + tokens/sec + total — üçü ayrıca izlənməlidir. Mean yanlış addırır.
- **Token distribution**: p50 yox, p95/p99 bahalı feature-ləri göstərir.
- **Error taxonomy**: HTTP status kodundan çox — rate_limit / overloaded / context_overflow / invalid / content_policy / provider_5xx.
- **PII scrubbing** məcburidir — regex first-line, Presidio second-line.
- **Eval-in-prod**: LLM-as-judge sampling ilə production keyfiyyətini real vaxtda izləyir; low score → alert.
- **A/B testing**: experiment variant-ı trace attribute kimi əlavə et, 1000+ sample sonra statistical analysis.
- **Dataset akkumulyasiyası**: production trace → eval set — regression testin canlı mənbəyi.
- **Per-user/per-feature xərc attribution** unit economics və abuse detection üçün şərtdir.
