# 41 — AI Sistemləri üçün Müşahidəlilik (Observability)

> **Oxucu kütləsi:** Senior developerlər və arxitektlər  
> **AI müşahidəliliyinin fərqi nədir:** Qeyri-deterministik çıxışlar, prompt-cavab cütləri əsas artefakt kimi, xərc birinci dərəcəli metrik, keyfiyyətin geriləməsinin aşkarlanması.

---

## 1. AI Müşahidəliliyinin Fərqli Tərəfləri

Ənənəvi tətbiq müşahidəliliyi bunları izləyir: sorğu sürəti, xəta sürəti, gecikmə, CPU/yaddaş.

AI müşahidəliliyi bunları əlavə edir:
- **Prompt/cavab cütləri** — keyfiyyət problemlərini debug etmək üçün faktiki məzmun vacibdir
- **Token istifadəsi** — birbaşa xərclə əlaqələnir
- **İstifadə olunan model** — sorğu başına keyfiyyət və xərcə təsir edir
- **Prompt versiyaları** — hansı prompt versiyasının produksiyada olduğunu izlə
- **Keyfiyyət balları** — LLM mühakiməsi və ya istifadəçi rəyi
- **Alət çağırışları** — agent sistemlər üçün hər alət çağırışı izlənməlidir
- **Çoxpilləli izlər** — tək bir istifadəçi sorğusu 5–10 LLM çağırışını əhatə edə bilər

### AI Sistemləri üçün Dörd Sütun

```
Logs (Jurnallar)  → Nə baş verdi (prompt, cavab, xətalar)
Metrics (Metriklər) → Necə işlədi (gecikmə, token, xərc, keyfiyyət)
Traces (İzlər)    → Tam yol (istifadəçi sorğusundakı bütün AI çağırışları)
Profiles (Profillər) → Niyə yavaşdır (hansı addım, hansı model, hansı prompt)
```

---

## 2. AI Observer: Bütün AI Çağırışlarını DB-ə Yaz

```php
<?php
// app/Services/AI/AIObserver.php

namespace App\Services\AI;

use App\Models\AICallLog;
use Illuminate\Support\Str;

/**
 * Bütün AI API çağırışları üçün mərkəzi observer.
 * Hər AI çağırışını jurnallama, xərc izləmə və xəbərdarlıqla əhatə edir.
 *
 * İstifadə: ClaudeService-ə inject edin, jurnallama avtomatik baş verir.
 */
class AIObserver
{
    private string $correlationId;

    public function __construct()
    {
        $this->correlationId = request()->header('X-Request-ID')
            ?? Str::uuid()->toString();
    }

    /**
     * AI çağırışını tam müşahidəlilik ilə əhatə et.
     *
     * @param array   $context  Çağırış haqqında metadata (xüsusiyyət, istifadəçi, tenant, və s.)
     * @param Closure $callable Faktiki AI çağırışı
     */
    public function observe(array $context, \Closure $callable): mixed
    {
        $callId = Str::uuid()->toString();
        $start  = hrtime(true);

        $log = AICallLog::create([
            'id'             => $callId,
            'correlation_id' => $this->correlationId,
            'parent_call_id' => $context['parent_call_id'] ?? null,
            'feature'        => $context['feature'] ?? 'unknown',
            'model'          => $context['model'] ?? 'unknown',
            'user_id'        => auth()->id(),
            'tenant_id'      => auth()->user()?->tenant_id,
            'prompt_hash'    => md5($context['prompt'] ?? ''),
            'prompt_version' => $context['prompt_version'] ?? null,
            'prompt_preview' => substr($context['prompt'] ?? '', 0, 500),
            'status'         => 'started',
            'started_at'     => now(),
        ]);

        try {
            $result = $callable($callId);

            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            $log->update([
                'status'          => 'completed',
                'latency_ms'      => (int) $latencyMs,
                'input_tokens'    => $context['usage']['input_tokens'] ?? null,
                'output_tokens'   => $context['usage']['output_tokens'] ?? null,
                'cache_read_tokens'=> $context['usage']['cache_read_input_tokens'] ?? null,
                'cost_usd'        => $this->calculateCost($context),
                'response_preview'=> substr($result ?? '', 0, 500),
                'completed_at'    => now(),
            ]);

            $this->checkAlerts($log, $latencyMs);

            return $result;

        } catch (\Throwable $e) {
            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            $log->update([
                'status'       => 'failed',
                'latency_ms'   => (int) $latencyMs,
                'error_class'  => $e::class,
                'error_message'=> $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    private function calculateCost(array $context): float
    {
        $model  = $context['model'] ?? 'claude-sonnet-4-5';
        $usage  = $context['usage'] ?? [];

        return app(TokenCostCalculator::class)->calculate(
            model:        $model,
            inputTokens:  $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
            cacheRead:    $usage['cache_read_input_tokens'] ?? 0,
        );
    }

    private function checkAlerts(AICallLog $log, float $latencyMs): void
    {
        // Yavaş çağırışlar üçün xəbərdarlıq
        if ($latencyMs > 30000) {
            logger()->warning('Çox yavaş AI çağırışı', ['call_id' => $log->id, 'latency_ms' => $latencyMs]);
        }

        // Bahalı çağırışlar üçün xəbərdarlıq
        if (($log->cost_usd ?? 0) > 0.10) {
            logger()->warning('Bahalı AI çağırışı', ['call_id' => $log->id, 'cost_usd' => $log->cost_usd]);
        }
    }
}
```

---

## 3. Model üzrə Token Xərc Kalkulyatoru

```php
<?php
// app/Services/AI/TokenCostCalculator.php

namespace App\Services\AI;

class TokenCostCalculator
{
    /**
     * 1M token başına qiymətlər (USD) — 2025-ci ilin aprel ayı üçün.
     * Anthropic qiymətləri dəyişdirdikdə yeniləyin.
     */
    private array $pricing = [
        'claude-opus-4-5' => [
            'input'      => 15.00,
            'output'     => 75.00,
            'cache_read' => 1.50,   // Adi giriş qiymətinə nisbətən 90% endirim
            'cache_write'=> 18.75,  // Keş yazma üçün 25% əlavə
        ],
        'claude-sonnet-4-5' => [
            'input'      => 3.00,
            'output'     => 15.00,
            'cache_read' => 0.30,
            'cache_write'=> 3.75,
        ],
        'claude-haiku-4-5' => [
            'input'      => 0.25,
            'output'     => 1.25,
            'cache_read' => 0.03,
            'cache_write'=> 0.30,
        ],
        // Embedding modelləri
        'text-embedding-3-small' => [
            'input'  => 0.02,
            'output' => 0,
        ],
        'text-embedding-3-large' => [
            'input'  => 0.13,
            'output' => 0,
        ],
    ];

    public function calculate(
        string $model,
        int    $inputTokens,
        int    $outputTokens,
        int    $cacheRead = 0,
        int    $cacheWrite = 0,
    ): float {
        $rates = $this->pricing[$model] ?? $this->pricing['claude-sonnet-4-5'];

        $regularInput = max(0, $inputTokens - $cacheRead - $cacheWrite);

        return (
            ($regularInput  * $rates['input'])       +
            ($outputTokens  * ($rates['output'] ?? 0)) +
            ($cacheRead     * ($rates['cache_read'] ?? 0)) +
            ($cacheWrite    * ($rates['cache_write'] ?? 0))
        ) / 1_000_000;
    }

    /**
     * Cari istifadəyə əsasən aylıq xərci proqnozlaşdır.
     */
    public function projectMonthlyCost(int $tenantId): array
    {
        $last7Days = \App\Models\AICallLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('SUM(cost_usd) as total_cost, COUNT(*) as total_calls, SUM(input_tokens) as total_input, SUM(output_tokens) as total_output')
            ->first();

        $dailyAvg = ($last7Days->total_cost ?? 0) / 7;

        return [
            'last_7_days_cost'    => round($last7Days->total_cost ?? 0, 4),
            'daily_average'       => round($dailyAvg, 4),
            'projected_monthly'   => round($dailyAvg * 30, 2),
            'total_calls_7d'      => $last7Days->total_calls ?? 0,
            'total_input_tokens'  => $last7Days->total_input ?? 0,
            'total_output_tokens' => $last7Days->total_output ?? 0,
        ];
    }
}
```

---

## 4. Çoxpilləli AI İş Axınları üçün Paylanmış İzləmə (Distributed Tracing)

```php
<?php
// app/Services/AI/AITracer.php

namespace App\Services\AI;

use Illuminate\Support\Str;

/**
 * Korrelyasiya ID-ləri ilə çoxpilləli AI iş axınlarını izlə.
 *
 * Tək bir istifadəçi chat mesajı bunları əhatə edə bilər:
 * 1. Embedding (RAG üçün)
 * 2. Vektor axtarışı
 * 3. Kontekst sıralama (LLM çağırışı)
 * 4. Əsas generasiya (LLM çağırışı)
 * 5. Təhlükəsizlik yoxlaması (LLM çağırışı)
 *
 * Bunların hamısı tək bir trace ID altında əlaqələndirilməlidir.
 */
class AITracer
{
    private string $traceId;
    private string $spanStack = '';
    private array  $spans = [];

    public function __construct()
    {
        $this->traceId = request()->header('X-Trace-ID')
            ?? Str::uuid()->toString();
    }

    /**
     * Cari trace daxilindəki adlandırılmış bir span başlat.
     */
    public function span(string $name, \Closure $callable): mixed
    {
        $spanId = Str::uuid()->toString();
        $parent = $this->spanStack;
        $start  = hrtime(true);

        $this->spanStack = $spanId;

        try {
            $result = $callable($spanId);

            $this->spans[] = [
                'span_id'    => $spanId,
                'parent_id'  => $parent ?: null,
                'trace_id'   => $this->traceId,
                'name'       => $name,
                'duration_ms'=> (hrtime(true) - $start) / 1_000_000,
                'status'     => 'ok',
            ];

            return $result;

        } catch (\Throwable $e) {
            $this->spans[] = [
                'span_id'    => $spanId,
                'parent_id'  => $parent ?: null,
                'trace_id'   => $this->traceId,
                'name'       => $name,
                'duration_ms'=> (hrtime(true) - $start) / 1_000_000,
                'status'     => 'error',
                'error'      => $e->getMessage(),
            ];

            throw $e;

        } finally {
            $this->spanStack = $parent;
        }
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpans(): array
    {
        return $this->spans;
    }

    /**
     * İzi Jaeger/Zipkin üçün OpenTelemetry formatında ixrac et.
     */
    public function export(): array
    {
        return [
            'traceId'   => $this->traceId,
            'spans'     => array_map(fn($s) => [
                'traceId'     => $s['trace_id'],
                'spanId'      => $s['span_id'],
                'parentSpanId'=> $s['parent_id'],
                'name'        => $s['name'],
                'duration'    => (int) ($s['duration_ms'] * 1000), // mikrosaniyə
                'tags'        => [
                    'status' => $s['status'],
                    'error'  => $s['error'] ?? null,
                ],
            ], $this->spans),
        ];
    }
}

// RAG chat servisində istifadə
class RAGChatService
{
    public function __construct(
        private readonly AITracer $tracer,
        private readonly EmbeddingService $embeddings,
        private readonly ClaudeService $claude,
    ) {}

    public function chat(string $query): string
    {
        return $this->tracer->span('chat', function (string $rootSpanId) use ($query): string {
            $embedding = $this->tracer->span('embed-query', fn() =>
                $this->embeddings->embed($query)
            );

            $chunks = $this->tracer->span('vector-search', fn() =>
                $this->searchVectors($embedding)
            );

            return $this->tracer->span('generate', fn() =>
                $this->claude->complete(
                    prompt: $this->buildPrompt($query, $chunks),
                    model: 'claude-sonnet-4-5',
                )
            );
        });
    }
}
```

---

## 5. AI Çağırışları üçün Yavaş Sorğu Jurnalı

```php
<?php
// app/Listeners/LogSlowAICalls.php

namespace App\Listeners;

use App\Events\AICallCompleted;
use Illuminate\Support\Facades\DB;

class LogSlowAICalls
{
    private const SLOW_THRESHOLD_MS = 10000;

    public function handle(AICallCompleted $event): void
    {
        if ($event->latencyMs < self::SLOW_THRESHOLD_MS) {
            return;
        }

        DB::table('ai_slow_calls')->insert([
            'call_id'      => $event->callId,
            'feature'      => $event->feature,
            'model'        => $event->model,
            'latency_ms'   => $event->latencyMs,
            'input_tokens' => $event->inputTokens,
            'prompt_hash'  => $event->promptHash,
            'user_id'      => $event->userId,
            'tenant_id'    => $event->tenantId,
            'created_at'   => now(),
        ]);

        // Çox yavaşdırsa xəbərdarlıq et
        if ($event->latencyMs > 30000) {
            \Illuminate\Support\Facades\Notification::route('slack', config('services.slack.ai_alerts'))
                ->notify(new \App\Notifications\SlowAICallAlert($event));
        }
    }
}
```

---

## 6. Laravel Telescope İnteqrasiyası

```php
<?php
// app/Providers/TelescopeServiceProvider.php (əlavələr)

use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local')) {
                return true;
            }

            // Produksiyada yalnız AI ilə əlaqəli sorğuları və istisnaları jurnal et
            return $entry->isReportableException() ||
                   $entry->isFailedJob() ||
                   $entry->type === 'ai-call' ||  // Xüsusi giriş tipi
                   ($entry->isRequest() && str_contains($entry->content['uri'] ?? '', 'ai/'));
        });
    }
}
```

```php
<?php
// AI çağırışları üçün xüsusi Telescope watcher qeydiyyatı
// app/Telescope/AICallWatcher.php

namespace App\Telescope;

use App\Events\AICallCompleted;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class AICallWatcher
{
    public function register(): void
    {
        \Illuminate\Support\Facades\Event::listen(AICallCompleted::class, function (AICallCompleted $event) {
            Telescope::recordGenericEntry(
                IncomingEntry::make([
                    'type'         => 'ai-call',
                    'feature'      => $event->feature,
                    'model'        => $event->model,
                    'latency_ms'   => $event->latencyMs,
                    'input_tokens' => $event->inputTokens,
                    'output_tokens'=> $event->outputTokens,
                    'cost_usd'     => $event->costUsd,
                    'status'       => $event->status,
                    'call_id'      => $event->callId,
                ])->type('ai-call')
            );
        });
    }
}
```

---

## 7. Prometheus Metriklərinin İxracı

```php
<?php
// app/Http/Controllers/MetricsController.php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MetricsController extends Controller
{
    /**
     * Metrикləri Prometheus mətn formatında ixrac et.
     * Scrape endpoint-i: GET /metrics
     * IP icazə siyahısı və ya gizli token ilə qoru.
     */
    public function prometheus(): Response
    {
        $lines = [];

        // Model və status üzrə AI çağırış sayğacları
        $callStats = DB::table('ai_call_logs')
            ->selectRaw('model, status, COUNT(*) as count')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->groupBy('model', 'status')
            ->get();

        $lines[] = '# HELP ai_calls_total Ümumi AI API çağırışları';
        $lines[] = '# TYPE ai_calls_total counter';
        foreach ($callStats as $stat) {
            $lines[] = "ai_calls_total{model=\"{$stat->model}\",status=\"{$stat->status}\"} {$stat->count}";
        }

        // Token istifadəsi
        $tokenStats = DB::table('ai_call_logs')
            ->selectRaw('model, SUM(input_tokens) as input, SUM(output_tokens) as output')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->groupBy('model')
            ->get();

        $lines[] = '# HELP ai_tokens_total İstifadə olunan ümumi tokenlar';
        $lines[] = '# TYPE ai_tokens_total counter';
        foreach ($tokenStats as $stat) {
            $lines[] = "ai_tokens_total{model=\"{$stat->model}\",type=\"input\"} {$stat->input}";
            $lines[] = "ai_tokens_total{model=\"{$stat->model}\",type=\"output\"} {$stat->output}";
        }

        // Xərc
        $costStats = DB::table('ai_call_logs')
            ->selectRaw('model, SUM(cost_usd) as cost')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->groupBy('model')
            ->get();

        $lines[] = '# HELP ai_cost_usd_total USD-də ümumi AI xərci';
        $lines[] = '# TYPE ai_cost_usd_total counter';
        foreach ($costStats as $stat) {
            $lines[] = "ai_cost_usd_total{model=\"{$stat->model}\"} {$stat->cost}";
        }

        // Gecikmə histoqramları
        $latencyStats = DB::table('ai_call_logs')
            ->selectRaw('model, AVG(latency_ms) as avg_latency, MAX(latency_ms) as max_latency')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->where('status', 'completed')
            ->groupBy('model')
            ->get();

        $lines[] = '# HELP ai_latency_ms AI çağırış gecikməsi millisaniyədə';
        $lines[] = '# TYPE ai_latency_ms gauge';
        foreach ($latencyStats as $stat) {
            $lines[] = "ai_latency_ms{model=\"{$stat->model}\",quantile=\"avg\"} {$stat->avg_latency}";
            $lines[] = "ai_latency_ms{model=\"{$stat->model}\",quantile=\"max\"} {$stat->max_latency}";
        }

        // Circuit breaker vəziyyətləri
        $models = ['claude-sonnet-4-5', 'claude-haiku-4-5', 'claude-opus-4-5'];
        $lines[] = '# HELP ai_circuit_breaker_state Circuit breaker vəziyyəti (0=qapalı, 1=açıq, 2=yarıaçıq)';
        $lines[] = '# TYPE ai_circuit_breaker_state gauge';
        foreach ($models as $model) {
            $state = Cache::get("cb:state:{$model}", 'closed');
            $stateValue = match ($state) {
                'closed'    => 0,
                'open'      => 1,
                'half_open' => 2,
                default     => 0,
            };
            $lines[] = "ai_circuit_breaker_state{model=\"{$model}\"} {$stateValue}";
        }

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }
}
```

---

## 8. AI Jurnalları üçün Verilənlər Bazası Sxemi

```sql
CREATE TABLE ai_call_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    correlation_id  UUID NOT NULL,
    parent_call_id  UUID REFERENCES ai_call_logs(id),
    feature         VARCHAR(100) NOT NULL,
    model           VARCHAR(100) NOT NULL,
    user_id         BIGINT REFERENCES users(id),
    tenant_id       BIGINT REFERENCES tenants(id),
    prompt_hash     VARCHAR(32),
    prompt_version  VARCHAR(50),
    prompt_preview  TEXT,
    response_preview TEXT,
    status          VARCHAR(20) NOT NULL,  -- started|completed|failed
    latency_ms      INT,
    input_tokens    INT,
    output_tokens   INT,
    cache_read_tokens INT,
    cost_usd        DECIMAL(10, 6),
    error_class     VARCHAR(200),
    error_message   TEXT,
    started_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Ümumi sorğu nümunələri üçün indekslər
CREATE INDEX ai_logs_tenant_date ON ai_call_logs(tenant_id, created_at DESC);
CREATE INDEX ai_logs_user_date   ON ai_call_logs(user_id, created_at DESC);
CREATE INDEX ai_logs_correlation ON ai_call_logs(correlation_id);
CREATE INDEX ai_logs_feature     ON ai_call_logs(feature, created_at DESC);
CREATE INDEX ai_logs_slow        ON ai_call_logs(latency_ms DESC) WHERE latency_ms > 5000;

-- Böyük yerləşdirmələr üçün aylıq bölmələmə
-- CREATE TABLE ai_call_logs PARTITION BY RANGE (created_at);
```

---

## 9. Müşahidəlilik Yoxlama Siyahısı

| İzlənəcək          | Niyə                                     | Harada               |
|--------------------|------------------------------------------|----------------------|
| Prompt + cavab     | Keyfiyyət geriliyəsinə debug et          | ai_call_logs         |
| Token istifadəsi   | Xərci əsaslandır və büdcələ             | ai_call_logs         |
| İstifadə olunan model | Keyfiyyət + xərc fərqliliyini analiz et | ai_call_logs       |
| Korrelyasiya ID    | Bir istifadəçi sorğusundakı bütün çağırışları əlaqələndir | Bütün jurnallar |
| Prompt versiyası   | Prompt dəyişiklikləri keyfiyyəti pozanda aşkar et | ai_call_logs  |
| Alət çağırışları   | Agent uğursuzluqlarına debug et          | Ayrıca tool_calls    |
| Keş vur/miss       | Keş effektivliyini ölç                   | Metriklər            |
| Circuit vəziyyəti  | Provayder sağlamlığını izlə              | Redis + Prometheus   |
| Xüsusiyyət başına xərc | Bahalı xüsusiyyətləri tap            | ai_call_logs         |
| Tenant başına xərc | Hesablama və anomaliya aşkarlaması       | ai_call_logs         |
