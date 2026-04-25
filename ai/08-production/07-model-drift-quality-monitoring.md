# Model Drift və Quality Monitoring: Silent Degradation-u Necə Aşkarlamaq (Lead)

> **Oxucu kütləsi:** Senior developerlər, ML/AI platform engineer-lər, SRE-lər
> **Bu faylın 03 və 06-dan fərqi:** 03 — ümumi observability (tracing, cost, eval-in-prod). 06 — testing strategiyaları. Bu fayl — **drift-in özünə** fokuslanır: niyə LLM drift klassik ML-dən fərqlidir, hansı siqnallar sakit deqradasiyanı göstərir, canary eval pipeline, provider model-version pinning, quality SLO-lar və "keyfiyyət yavaş-yavaş pisləşib" incident-inin runbook-u.

---

## Mündəricat

1. [Silent Degradation Problemi](#silent)
2. [Drift-in Üç Növü](#types)
3. [LLM Drift Niyə Unikaldır](#unique)
4. [Drift Siqnalları](#signals)
5. [Statistik Aşkarlama](#statistics)
6. [Canary Eval Pipeline](#canary)
7. [Model-Version Pinning](#pinning)
8. [Quality SLO Tərifi](#slo)
9. [Laravel + Horizon + Prometheus Setup](#laravel)
10. [Alert Threshold-ları](#alerts)
11. [Runbook: "Quality Regressed" Incident](#runbook)
12. [Root Cause Tree](#root-cause)
13. [İstifadəçiyə Disclosure](#disclosure)
14. [Anti-Pattern-lər](#anti)

---

## 1. Silent Degradation Problemi <a name="silent"></a>

Klassik web servis-də keyfiyyət azalması **səs çıxarır**: 500-lər artır, p99 latency qalxır, error rate grafiki qırmızı olur. Dashboard adama deyir: "nəsə xarabdır".

LLM sistemlərində isə keyfiyyət **sakit şəkildə** pozula bilər:

- HTTP 200 qayıdır
- Latency normaldır
- Cost artmayıb
- Heç bir exception yoxdur
- Amma cavablar 3 həftə əvvəlkindən daha pisdir

İstifadəçi bunu hiss edir, amma sizin monitoring bilmir. Support ticket-lər artır ("bot əvvəlkindən axmaq olub"), NPS azalır, retention düşür — siz hələ də dashboard-da "zəfər" görürsünüz.

### Real Incident Nümunələri

- **2023-07**: Stanford araşdırması GPT-4-ün riyazi suallarda performansının iyun-dan may-a qədər 97.6%-dən 2.4%-ə düşdüyünü göstərdi. Provider ayrıca elan etmədi.
- **2024-Q2**: Klientlər `gpt-4-turbo-2024-04-09` model-inin `2024-01-25` versiyasına nisbətən daha qısa cavablar verdiyini bildirdi. Tokenizer dəyişikliyi ilə bağlı idi.
- **2025-02**: Bir Claude minor update-dən sonra müəyyən müştərilərin JSON schema extraction success rate-i 99%-dən 94%-ə düşdü. Error yox — sadəcə keyfiyyət.
- **2025-11**: RAG chatbot groundedness score-u 3 həftə ərzində 0.87-dən 0.71-ə düşdü. Səbəb: index-ə əlavə edilən yeni dokument-lərdə noise var idi. Model eyni qalmışdı, data drift idi.

Hamısının ortaq cəhəti: **exception yox, silent**. Yalnız eval pipeline-ı olanlar gördü.

---

## 2. Drift-in Üç Növü <a name="types"></a>

```
┌──────────────────────────────────────────────────────────┐
│                  Drift Taxonomy                          │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  1) DATA DRIFT (input distribution shifts)               │
│     • İstifadəçilər fərqli suallar verməyə başlayır     │
│     • RAG korpusu dəyişir (yeni dokumentlər)            │
│     • Məsələ: sizin sistem yeni distribüsyada zəifdir   │
│                                                          │
│  2) CONCEPT DRIFT (input→output relationship shifts)    │
│     • Eyni input artıq eyni düzgün cavabı tələb etmir   │
│     • Məsələn: qiymət dəyişib, siyasət dəyişib         │
│     • Model köhnə context ilə yanlış cavab verir        │
│                                                          │
│  3) MODEL DRIFT (provider silently updates weights)     │
│     • Provider alias-ın arxasında model-i dəyişir      │
│     • Tokenizer update, system prompt inject            │
│     • Sizin code eyni qalır, model davranışı dəyişir    │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### Data Drift (klassik ML-dən tanışdır)

İstifadəçilərin davranışı dəyişir. Məsələn, launch-da istifadəçilər sadəcə account-la bağlı suallar verirdi. 6 ay sonra 40%-i billing sorğusu verir. Sizin system prompt billing-ə hazır deyil.

**Aşkarlama**: input embedding-lərinin orta və dispersiyasını trackleyin. PSI (Population Stability Index) hesablayın.

### Concept Drift

Dünya dəyişir, amma model training cutoff-da donur:

- **Price list** update olur: "iPhone 15 qiyməti?" — model 2025-in qiyməti deyir, amma siz 2026 qiymətinə keçmisiniz
- **Siyasət dəyişir**: "return policy?" — 30 gündən 14 günə düşüb, amma system prompt və RAG 30 gün deyir
- **Yeni məhsul** əlavə olunur: model onu bilmir, "bizdə belə məhsul yoxdur" deyir — halüsinasiya əleyhinə halüsinasiya

Bu, RAG content-inin düzgün maintain olunmaması problemidir.

### Model Drift (ən insidious)

Klassik ML-də modeli siz owner-siniz. `model_v42.pkl` — binary fayldır, dəyişmir. LLM-də provider-in serverindədir. Alias (`claude-sonnet-4-5`) arxasında daxili weight-lər dəyişə bilər.

Bəzi provider-lərin davranışı:

- **Anthropic**: `claude-sonnet-4-5` alias-ı əksər hallarda stabil, amma minor revisiya (`claude-sonnet-4-5-20260115` vs `claude-sonnet-4-5-20260301`) alias-ın arxasında fərqli performance gətirə bilər
- **OpenAI**: `gpt-4-turbo` kimi alias-lar periyodik olaraq yeni snapshot-a point edir
- **Google Gemini**: `gemini-2.0-pro` daxili update-lər alır

---

## 3. LLM Drift Niyə Unikaldır <a name="unique"></a>

Klassik ML-lə müqayisə cədvəli:

| Aspekt | Klassik ML | LLM |
|--------|-----------|-----|
| Model ownership | Siz own edirsiniz | Provider-də |
| Version-ing | Siz kontrol edirsiniz | Alias arxasında dəyişə bilər |
| Tokenizer | Sabit | Minor update-də dəyişə bilər |
| System prompt injection | Yoxdur | Provider öz side-da əlavə edə bilər (safety layer) |
| Refusal behavior | Yoxdur | Təlim yenilənəndə fərqli şeylərdən imtina edir |
| Output format | Determinstik | Stylistik dəyişikliklər (uzunluq, ton) |
| Reproducibility | Tam | Yalnız snapshot pinned-isə |

### Niyə Silent Provider Update-i Pis-dir

1. **Cache inefficiency** — prompt caching tokenizer-ə bağlıdır. Tokenizer dəyişsə, bütün cache miss olur, cost 5x qalxa bilər.
2. **Prompt regression** — siz prompt-u optimize etmisiniz köhnə model üçün. Yeni model fərqli reasoning pattern-inə üstünlük verir.
3. **Structured output dəqiqliyi** — JSON schema compliance minor update-də 99% → 95% düşə bilər. Sizin downstream parser crash edir.
4. **Refusal rate shift** — bəzi sual növlərini əvvəl cavablandırırdı, indi imtina edir. User-visible.

**Ona görə senior pattern**: production-da **snapshot-pinned model ID** istifadə edin (`claude-opus-4-7-20260115`), alias yox. Update özünüz kontrolla edin.

---

## 4. Drift Siqnalları <a name="signals"></a>

Hansı metrikləri trackləmək lazımdır? Aşağıda priority siyahısı:

### Tier 1 — Critical (hər deployment-də dashboard-da olmalıdır)

| Metrik | Nə göstərir | Tipik drift threshold |
|--------|-------------|----------------------|
| `output_length_p50` | Cavab uzunluğunun mediana | ±20% shift = şübhəli |
| `output_length_p99` | Uzun cavabların davranışı | ±30% shift = investigate |
| `refusal_rate` | Model imtina faizi | +5 percentage point = investigate |
| `tool_call_success_rate` | Tool çağırışı valid JSON % | -2 percentage point = critical |
| `thumbs_down_rate` | User negative feedback % | +10% relative = investigate |
| `latency_p50` | TTFT və total | ±25% = investigate |
| `cost_per_request_p50` | $/sorğu mediana | +15% = investigate (token shift) |

### Tier 2 — Important (weekly review)

| Metrik | Nə göstərir |
|--------|-------------|
| `canary_eval_pass_rate` | Golden set-də % pass |
| `semantic_drift_score` | Output embedding-lərin cosine shift-i |
| `json_schema_compliance` | Structured output-da schema pass % |
| `hallucination_rate` (citation check) | Mənbə verilməsi lazım olan cavablarda fake citation % |
| `groundedness_score` | RAG cavabları mənbəyə nə qədər sadiqdir |

### Tier 3 — Diagnostic (incident zamanı baxılır)

- Per-provider, per-model cost breakdown
- Per-endpoint latency heatmap
- Prompt cache hit rate (tokenizer drift siqnalı)
- Token distribution (top-50 sözün tezliyi)

---

## 5. Statistik Aşkarlama <a name="statistics"></a>

### 5.1 Population Stability Index (PSI)

İki distribüsiya (bu həftə vs əvvəlki həftə) nə qədər fərqlidir:

```
PSI = Σ (actual_% - expected_%) × ln(actual_% / expected_%)
```

Tipik threshold:
- `PSI < 0.1` — stabil
- `0.1 ≤ PSI < 0.25` — moderate shift, investigate
- `PSI ≥ 0.25` — significant drift, alert

### 5.2 KL Divergence

İki output distribüsiyasının məsafəsi (output_length, top-token frequency):

```
KL(P || Q) = Σ P(i) × log(P(i) / Q(i))
```

PSI-dən daha həssasdır, amma P və Q-nın eyni support-u olmalıdır (zero bucket-lar problem yaradır).

### 5.3 Embedding Drift

Output-ların mean embedding-i həftə ərzində cosine-də necə dəyişir:

```python
# conceptual
weekly_mean = np.mean([embed(output) for output in this_week_sample])
historical_mean = np.mean([embed(output) for output in baseline_sample])
drift_score = 1 - cosine_similarity(weekly_mean, historical_mean)
```

`drift_score > 0.05` şübhəli, `> 0.1` ciddi.

### 5.4 Three-sigma Rule

Sadə threshold: son 30 gün `mean ± 3σ` range-dən çıxırsa alert. Gaussian olmayan distribüsiyalar üçün percentile-based (`p1` və `p99`) istifadə edin.

### Laravel-də PSI Servisi

```php
<?php
// app/Services/AI/Drift/PSICalculator.php

namespace App\Services\AI\Drift;

class PSICalculator
{
    /**
     * İki distribüsiya arasında PSI hesabla.
     * $baseline və $current: [bucket_label => count].
     */
    public function compute(array $baseline, array $current): float
    {
        $baseTotal = array_sum($baseline) ?: 1;
        $currTotal = array_sum($current) ?: 1;

        $psi = 0.0;
        $allBuckets = array_unique(array_merge(
            array_keys($baseline),
            array_keys($current)
        ));

        foreach ($allBuckets as $bucket) {
            $expected = max(($baseline[$bucket] ?? 0) / $baseTotal, 0.0001);
            $actual   = max(($current[$bucket] ?? 0) / $currTotal, 0.0001);
            $psi += ($actual - $expected) * log($actual / $expected);
        }

        return $psi;
    }

    public function classify(float $psi): string
    {
        return match (true) {
            $psi < 0.1  => 'stable',
            $psi < 0.25 => 'moderate',
            default     => 'significant',
        };
    }
}
```

---

## 6. Canary Eval Pipeline <a name="canary"></a>

Silent drift-ə qarşı ən güclü müdafiə: **hər gecə dondurulmuş 100-500 prompt-luq eval suite-i çalışdır, score-ları trendlə**.

### Pipeline Arxitekturası

```
┌──────────────────────────────────────────────────────────┐
│                 Nightly Canary Eval                      │
├──────────────────────────────────────────────────────────┤
│                                                          │
│   02:00 AM UTC                                           │
│       │                                                  │
│       ▼                                                  │
│   ┌─────────────────┐                                    │
│   │ GoldenSet       │  500 prompt + gözlənilən cavab    │
│   │ (git versioned) │  Hash: sha256 yoxlanılır          │
│   └────────┬────────┘                                    │
│            │                                             │
│            ▼                                             │
│   ┌─────────────────┐                                    │
│   │ Run each prompt │  Parallel, rate-limited            │
│   │ against current │                                    │
│   │ prod config     │                                    │
│   └────────┬────────┘                                    │
│            │                                             │
│            ▼                                             │
│   ┌─────────────────┐                                    │
│   │ Scorers:        │                                    │
│   │  - Exact match  │                                    │
│   │  - Semantic sim │                                    │
│   │  - LLM-as-judge │                                    │
│   │  - Schema valid │                                    │
│   │  - Groundedness │                                    │
│   └────────┬────────┘                                    │
│            │                                             │
│            ▼                                             │
│   ┌─────────────────┐                                    │
│   │ Write to DB     │  evals table                       │
│   │ + Prometheus    │  ai_eval_pass_rate{suite="..."}    │
│   └────────┬────────┘                                    │
│            │                                             │
│            ▼                                             │
│   ┌─────────────────┐                                    │
│   │ Compare to 7d   │                                    │
│   │ rolling avg     │                                    │
│   └────────┬────────┘                                    │
│            │                                             │
│       regress > 3%?                                      │
│            │                                             │
│            ▼                                             │
│   ┌─────────────────┐                                    │
│   │ PagerDuty alert │                                    │
│   └─────────────────┘                                    │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### Golden Set Necə Seçilir

- **Representative**: production distribüsiyasını yansıtmalıdır (sample real traffic-dən, PII scrub et)
- **Stable**: cavablar dünya dəyişsə də dəyişməyənlər (faktları git-də fix edin)
- **Diverse**: bütün əsas intent/feature-ləri əhatə edir
- **Hash-ed**: golden set dəyişibsə, müqayisə mənasızdır — sha256 hash track edin

### Laravel Job

```php
<?php
// app/Jobs/AI/RunCanaryEvalJob.php

namespace App\Jobs\AI;

use App\Models\EvalRun;
use App\Services\AI\Drift\GoldenSetLoader;
use App\Services\AI\Drift\Scorer;
use App\Services\AI\LlmGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Prometheus\CollectorRegistry;

class RunCanaryEvalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 saat
    public int $tries = 1;

    public function __construct(
        public string $suite = 'default',
    ) {}

    public function handle(
        GoldenSetLoader $loader,
        LlmGateway $gateway,
        Scorer $scorer,
        CollectorRegistry $registry,
    ): void {
        $set = $loader->load($this->suite);

        // Suite-in hash-i dəyişibsə, müqayisəni qır və log et
        if ($set->hash !== config('ai.golden_sets.'.$this->suite.'.expected_hash')) {
            logger()->warning('Golden set hash changed', [
                'suite' => $this->suite,
                'expected' => config('ai.golden_sets.'.$this->suite.'.expected_hash'),
                'actual' => $set->hash,
            ]);
        }

        $run = EvalRun::create([
            'suite' => $this->suite,
            'model' => config('ai.default_model'),
            'config_sha' => config('ai.prompt_version'),
            'golden_hash' => $set->hash,
            'started_at' => now(),
        ]);

        $passed = 0;
        $total = count($set->items);

        foreach ($set->items as $item) {
            $response = $gateway->chat([
                'model' => $run->model,
                'messages' => $item->messages,
                'max_tokens' => 1024,
            ]);

            $score = $scorer->score($item, $response);

            $run->items()->create([
                'prompt_id' => $item->id,
                'expected' => $item->expected,
                'actual' => $response->content,
                'score' => $score->value,
                'passed' => $score->passed,
            ]);

            if ($score->passed) {
                $passed++;
            }
        }

        $passRate = $passed / max($total, 1);

        $run->update([
            'finished_at' => now(),
            'pass_rate' => $passRate,
        ]);

        // Prometheus-a yaz
        $gauge = $registry->getOrRegisterGauge(
            'ai',
            'canary_eval_pass_rate',
            'Nightly canary eval pass rate',
            ['suite', 'model', 'config_sha'],
        );
        $gauge->set($passRate, [$this->suite, $run->model, $run->config_sha]);

        // Regression yoxla
        $this->checkRegression($run, $passRate);
    }

    private function checkRegression(EvalRun $run, float $current): void
    {
        $rolling = EvalRun::where('suite', $run->suite)
            ->where('id', '<>', $run->id)
            ->where('started_at', '>', now()->subDays(7))
            ->avg('pass_rate') ?? $current;

        if ($rolling - $current > 0.03) { // 3% drop
            event(new \App\Events\AI\QualityRegressionDetected(
                suite: $run->suite,
                previousRate: $rolling,
                currentRate: $current,
                runId: $run->id,
            ));
        }
    }
}
```

Scheduler-də:

```php
// app/Console/Kernel.php
$schedule->job(new RunCanaryEvalJob('support-bot'))->dailyAt('02:00');
$schedule->job(new RunCanaryEvalJob('sql-assistant'))->dailyAt('02:30');
$schedule->job(new RunCanaryEvalJob('email-classifier'))->dailyAt('03:00');
```

### Scorer Növləri

```php
<?php
// app/Services/AI/Drift/Scorer.php

namespace App\Services\AI\Drift;

class Scorer
{
    public function __construct(
        private SemanticSimilarity $embed,
        private LlmJudge $judge,
    ) {}

    public function score(GoldenItem $item, LlmResponse $response): ScoreResult
    {
        return match ($item->scorer) {
            'exact_match' => $this->exactMatch($item, $response),
            'semantic'    => $this->semantic($item, $response),
            'llm_judge'   => $this->llmJudge($item, $response),
            'json_schema' => $this->jsonSchema($item, $response),
            'groundedness'=> $this->groundedness($item, $response),
        };
    }

    private function semantic(GoldenItem $item, LlmResponse $response): ScoreResult
    {
        $similarity = $this->embed->cosine(
            $item->expected,
            $response->content,
        );
        return new ScoreResult(
            value: $similarity,
            passed: $similarity >= ($item->threshold ?? 0.85),
        );
    }

    private function jsonSchema(GoldenItem $item, LlmResponse $response): ScoreResult
    {
        $data = json_decode($response->content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new ScoreResult(0.0, false);
        }
        $validator = new \JsonSchema\Validator();
        $validator->validate($data, $item->schema);
        return new ScoreResult(
            value: $validator->isValid() ? 1.0 : 0.0,
            passed: $validator->isValid(),
        );
    }
}
```

---

## 7. Model-Version Pinning <a name="pinning"></a>

### Alias vs Snapshot ID

| Format | Nümunə | Üstünlüyü | Riski |
|--------|--------|-----------|-------|
| Alias | `claude-sonnet-4-5` | Provider update-lərini otomatik alır | Silent drift |
| Snapshot | `claude-sonnet-4-5-20260115` | Reproducible | Əl ilə update |
| Version aware | `claude-opus-4-7@latest` vs `@stable` | İki-sıralı channel | Provider-dən asılıdır |

### Production Pattern

```php
// config/ai.php
return [
    'models' => [
        'support-bot' => [
            'primary'   => env('AI_SUPPORT_MODEL', 'claude-opus-4-7-20260115'),
            'fallback'  => env('AI_SUPPORT_FALLBACK', 'claude-sonnet-4-5-20260115'),
        ],
        'email-classifier' => [
            'primary' => env('AI_CLASSIFIER_MODEL', 'claude-haiku-4-20260115'),
        ],
    ],
    'pinning_policy' => 'snapshot',  // never 'alias' in prod
];
```

### Model Update Ritualı

1. Yeni snapshot gələndə **staging-də** dəyişdir
2. Canary eval suite-i çalışdır — pass_rate 7 gün stabil olsun
3. Shadow mode-a çıxar (61-ci fayl) — 1 həftə production trafik parallel
4. Production-a A/B (10% → 50% → 100%) — business metric-lərə bax
5. Köhnə snapshot-u rollback üçün 30 gün saxla

---

## 8. Quality SLO Tərifi <a name="slo"></a>

### SLO Nümunələri

```yaml
slos:
  canary_eval_pass_rate:
    objective: 0.92   # 92% pass
    window: 7d
    alert_burn_rate_fast: 14.4  # 1 saat üçün
    alert_burn_rate_slow: 6     # 6 saat üçün

  json_schema_compliance:
    objective: 0.99
    window: 24h
    alert_threshold: 0.97   # hard alert, SLO yox

  user_thumbs_down_rate:
    objective: 0.05   # <= 5%
    window: 7d

  refusal_rate:
    objective_range: [0.01, 0.05]  # 1%-5% normal
    window: 24h

  groundedness_score:
    objective: 0.85
    window: 24h

  model_snapshot_drift:
    # Alias arxasında model dəyişibsə, bunu aşkarla
    check_weekly: true
    trigger: embedding_drift_score > 0.08
```

### Error Budget Burn Rate

SRE book-dakı klassik formul. Əgər 7 günlük SLO 92%-dirsə, error budget 8%. Son 1 saatda 14.4x burn rate = 14.4 × (1h / 168h) × 8% = 1.2%. Bir saatda budget-in 1/6-sını yedi → fast-burn alert.

---

## 9. Laravel + Horizon + Prometheus Setup <a name="laravel"></a>

### Prometheus Exporter Servisi

```php
<?php
// app/Services/AI/Metrics/LlmMetrics.php

namespace App\Services\AI\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Histogram;
use Prometheus\Gauge;
use Prometheus\Counter;

class LlmMetrics
{
    private Histogram $latency;
    private Histogram $outputLength;
    private Counter $refusals;
    private Counter $totalRequests;
    private Counter $schemaFailures;
    private Gauge $canaryPassRate;

    public function __construct(CollectorRegistry $registry)
    {
        $labels = ['model', 'feature', 'provider'];

        $this->latency = $registry->getOrRegisterHistogram(
            'ai', 'llm_latency_seconds',
            'LLM response latency in seconds',
            $labels,
            [0.1, 0.5, 1, 2, 5, 10, 30]
        );

        $this->outputLength = $registry->getOrRegisterHistogram(
            'ai', 'llm_output_tokens',
            'LLM output token count',
            $labels,
            [50, 100, 250, 500, 1000, 2500, 5000]
        );

        $this->refusals = $registry->getOrRegisterCounter(
            'ai', 'llm_refusals_total',
            'LLM refusal count',
            $labels,
        );

        $this->totalRequests = $registry->getOrRegisterCounter(
            'ai', 'llm_requests_total',
            'LLM request count',
            $labels,
        );

        $this->schemaFailures = $registry->getOrRegisterCounter(
            'ai', 'llm_schema_failures_total',
            'Structured output schema validation failures',
            $labels,
        );

        $this->canaryPassRate = $registry->getOrRegisterGauge(
            'ai', 'canary_eval_pass_rate',
            'Nightly canary pass rate',
            ['suite', 'model', 'config_sha'],
        );
    }

    public function record(LlmCall $call): void
    {
        $labels = [$call->model, $call->feature, $call->provider];

        $this->latency->observe($call->latencySeconds, $labels);
        $this->outputLength->observe($call->outputTokens, $labels);
        $this->totalRequests->inc($labels);

        if ($call->wasRefusal) {
            $this->refusals->inc($labels);
        }

        if ($call->schemaInvalid) {
            $this->schemaFailures->inc($labels);
        }
    }
}
```

### Middleware — Hər AI Call-u Metric-ə Çevir

```php
<?php
// app/Http/Middleware/AiCallInstrumentation.php

namespace App\Http\Middleware;

use App\Services\AI\Metrics\LlmMetrics;
use App\Services\AI\Metrics\RefusalDetector;
use Closure;
use Illuminate\Http\Request;

class AiCallInstrumentation
{
    public function __construct(
        private LlmMetrics $metrics,
        private RefusalDetector $refusal,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $elapsed = microtime(true) - $start;

        // Response-dan LLM meta-nı çıxar
        $meta = $response->headers->get('X-LLM-Meta')
            ? json_decode($response->headers->get('X-LLM-Meta'), true)
            : null;

        if ($meta) {
            $content = $response->getContent();
            $this->metrics->record(new LlmCall(
                model: $meta['model'],
                feature: $meta['feature'],
                provider: $meta['provider'],
                latencySeconds: $elapsed,
                outputTokens: $meta['output_tokens'] ?? 0,
                wasRefusal: $this->refusal->detect($content),
                schemaInvalid: $meta['schema_invalid'] ?? false,
            ));
        }

        return $response;
    }
}
```

### Horizon Metric Export

`config/horizon.php`-də metrics tag-lənməsi:

```php
'metrics' => [
    'trim_snapshots' => [
        'job' => 24,
        'queue' => 24,
    ],
],
```

Horizon `horizon_jobs_processed` və `horizon_waiting_time`-ı avtomatik export edir. LLM job-lar üçün special queue (`ai-critical`, `ai-canary`) saxlayın ki, saturation-ı ayrıca görəsiniz.

---

## 10. Alert Threshold-ları <a name="alerts"></a>

Real production üçün təklif olunan threshold-lar:

```yaml
# Prometheus alert rules
groups:
  - name: llm_quality
    rules:
      - alert: CanaryPassRateDropped
        expr: canary_eval_pass_rate < 0.88
        for: 1h
        labels:
          severity: critical
        annotations:
          summary: "Canary eval pass rate {{ $value }} below 88%"
          runbook: "https://wiki/runbooks/llm-quality-regression"

      - alert: RefusalRateSpike
        expr: |
          rate(llm_refusals_total[10m])
          /
          rate(llm_requests_total[10m])
          > 0.10
        for: 15m
        labels:
          severity: warning

      - alert: SchemaFailureSpike
        expr: |
          rate(llm_schema_failures_total[5m])
          /
          rate(llm_requests_total[5m])
          > 0.02
        for: 10m
        labels:
          severity: critical

      - alert: OutputLengthDrift
        # p50 output length 7 günlük avg-dan 30% uzaq
        expr: |
          abs(
            histogram_quantile(0.5, rate(llm_output_tokens_bucket[1h]))
            -
            avg_over_time(
              histogram_quantile(0.5, rate(llm_output_tokens_bucket[1h]))[7d:1h]
            )
          )
          /
          avg_over_time(
            histogram_quantile(0.5, rate(llm_output_tokens_bucket[1h]))[7d:1h]
          )
          > 0.30
        for: 2h
        labels:
          severity: warning

      - alert: LatencyShift
        expr: |
          histogram_quantile(0.99, rate(llm_latency_seconds_bucket[5m])) > 15
        for: 10m
        labels:
          severity: warning
```

### Threshold Seçərkən Prinsiplər

1. **3-sigma deyil, business-aware** — 30 gün baseline-a baxaraq percentile seçin
2. **Burn rate-based** — SLO error budget tükənməyə yönəlir, sabit threshold deyil
3. **Aged for-clause** — transient spike-lərdən qoruyur
4. **Multi-window** — 1h burn rate həm 6h-də yoxlanılsın (false positive aşağı)

---

## 11. Runbook: "Quality Regressed" Incident <a name="runbook"></a>

```
┌──────────────────────────────────────────────────────────┐
│           Incident: Quality Regression Detected           │
├──────────────────────────────────────────────────────────┤
│                                                           │
│ T+0   Alert fires (PagerDuty)                            │
│       Source: CanaryPassRateDropped                       │
│                                                           │
│ T+2   On-call acknowledges                                │
│       Opens #incident-{id} channel                        │
│                                                           │
│ T+5   Triage qərarı:                                     │
│       - Bu user-facing-dir?                              │
│       - Revenue impact varmı?                            │
│       - SEV1/SEV2/SEV3?                                  │
│                                                           │
│ T+10  Hypothesis tree (aşağı bax):                       │
│       - Prompt dəyişdi? → git log review                 │
│       - Model snapshot dəyişdi? → config diff            │
│       - RAG corpus dəyişdi? → embedding drift check      │
│       - Provider incident? → status page                 │
│                                                           │
│ T+20  Mitigation options:                                │
│       A) Rollback (previous prompt/model/RAG)            │
│       B) Route traffic to fallback provider              │
│       C) Raise error threshold & disable feature         │
│                                                           │
│ T+30  Mitigation applied, monitor for 30 min             │
│                                                           │
│ T+60  If stable → downgrade to SEV3, schedule postmortem │
│       If not → escalate, engage vendor (Anthropic)       │
│                                                           │
└──────────────────────────────────────────────────────────┘
```

### Triage Kommandaları (Laravel)

```bash
# Son 24 saat canary trend
php artisan ai:canary:trend --suite=support-bot --days=7

# Son 24 saat refusal rate
php artisan ai:metrics:refusals --window=24h

# Prompt version diff
git log --oneline --since="14 days ago" -- config/prompts/

# Model snapshot hazırkı vs əvvəlki
php artisan ai:model:current
# claude-opus-4-7-20260115 (since 2026-03-01)
# Previous: claude-opus-4-7-20260101 (2026-02-01 → 2026-02-28)

# Embedding drift bu gün
php artisan ai:drift:embedding --baseline=30d --window=24h
```

---

## 12. Root Cause Tree <a name="root-cause"></a>

```
Quality regressed
│
├─ Prompt dəyişdi?
│  ├─ Yes → git log, revert konkret commit → test
│  └─ No  → növbəti şaxə
│
├─ Model snapshot dəyişdi?
│  ├─ Config diff: snapshot ID-si fərqlidir?
│  │  ├─ Yes → köhnəyə rollback, test → issue provider-də, eskalasiya
│  │  └─ No → alias varmısa, provider silent update etdi
│  │         → snapshot-a pin et, test
│  └─ Model eyni qalıb
│
├─ RAG corpus dəyişdi?
│  ├─ Yeni dokument əlavə olundu? → diff son 7 gün
│  ├─ Embedding-lərin mean-i shiftləndi? → drift score yoxla
│  ├─ Noisy content daxil oldu? → groundedness aşağı düşdü?
│  └─ Chunk size/overlap config-i dəyişdi?
│
├─ Input distribüsyası dəyişdi?
│  ├─ PSI yüksəkdir? (input types dəyişib)
│  ├─ Yeni feature launch edildi → traffic mix fərqli
│  └─ Bot/scraper traffic? → filter-lə
│
├─ Provider incident?
│  ├─ Status page göstərir
│  └─ Fallback provider-ə keç (fayl 15)
│
├─ Tokenizer/caching problemi?
│  ├─ Cache hit rate düşüb? → tokenizer dəyişmiş ola bilər
│  └─ Cost per request artıb? → eyni siqnal
│
└─ Evaluation-ın özü?
   ├─ Golden set hash dəyişibmi? (commit yoxla)
   ├─ Scorer model (LLM-as-judge) dəyişibmi?
   └─ False alarm? → 24 saat monitor et, təkrarlanırsa investigate
```

---

## 13. İstifadəçiyə Disclosure <a name="disclosure"></a>

Keyfiyyət müvəqqəti aşağıdırsa və siz bunu bilirsiniz — **səssiz qalmayın**. Status communication best practice:

- **Public status page**: "We're investigating reduced quality in AI support responses" (impact səviyyəsinə uyğun)
- **In-app banner**: "Our AI assistant is running slower than usual while we investigate"
- **Automatic fallback disclosure**: fallback provayderə keçəndə response-a meta əlavə edin

```php
// Response-a debug flag
[
    'content' => '...',
    'meta' => [
        'model_used' => 'gpt-4o',  // Claude əvəzinə
        'degraded_mode' => true,
        'reason' => 'primary_provider_incident',
    ],
]
```

Front-end bu flag-i görəndə disclaimer göstərir.

### GDPR/Transparency Act Tələbləri

EU AI Act (fayl 16) tələb edir: istifadəçi AI ilə söhbət etdiyini bilməlidir, keyfiyyət aşağı olan periodları audit trail-də saxlamalısınız.

---

## 14. Anti-Pattern-lər <a name="anti"></a>

| Anti-pattern | Niyə pisdir | Düzgün |
|-------------|-------------|--------|
| "HTTP 200 = OK" | LLM cavabı halüsinasiya ola bilər | Quality metric, eval score |
| Model alias (prod-da) | Silent drift | Snapshot ID pinning |
| Canary eval yoxdur | Drift gec kəşf olunur | Daily golden set |
| Golden set hash track edilmir | Eval özü dəyişsə bilinmir | Sha256 version |
| Bütün dəyişiklik bir PR-də | Root cause tapılmır | Atomic change (prompt VƏ YA model) |
| Refusal rate trackrlanmır | User-visible problem gizli qalır | Tier-1 metrik |
| RAG corpus diff-siz update olur | Groundedness düşür, səbəb bilinmir | Corpus versioning |
| Provider-ə yalnız güvən | Provider outage-də hər şey ölür | Multi-provider fallback |
| "Temperature=0 deməli determinstikdir" | LLM-lər 0-da belə non-deterministic | Full determinism gözləməyin, statistical eval |
| Eval yalnız offline-dır | Prod-da drift edə bilər | Prod-da eval-in-prod |
| Prometheus threshold sabit | Distribution dəyişir, false alarm | Rolling baseline, SLO |
| Post-incident eval yox | Eyni drift yenidən gələcək | Every incident → new golden items |

---

## Yekun Təsdiq Siyahısı

Production-a çıxmazdan əvvəl:

- [ ] Model snapshot ID-si pin edilib (alias yox)
- [ ] Golden set 100+ prompt ilə git-də versionlaşdırılıb
- [ ] Nightly canary pipeline qurulub
- [ ] Tier-1 metriklər (length, refusal, tool success, thumbs) Prometheus-da
- [ ] 7 günlük SLO təyin olunub
- [ ] Alert runbook yazılıb və on-call bilir
- [ ] Rollback prosedure test edilib (prompt, model, RAG üçün ayrı-ayrı)
- [ ] RAG corpus versionlaşdırılır (hash + size track)
- [ ] Embedding drift həftəlik yoxlanılır
- [ ] Fallback provider hazırdır (fayl 15)
- [ ] User-facing disclosure mexanizmi var

---

## İstinadlar

- Google SRE Book — Error Budget və Burn Rate: https://sre.google/sre-book/service-level-objectives/
- PSI formulası və application: https://www.mdpi.com/
- Stanford "How is ChatGPT's behavior changing over time?": Chen et al. 2023
- OpenAI model snapshot docs: https://platform.openai.com/docs/models
- Anthropic model versioning: https://docs.claude.com/en/docs/about-claude/models
- Langfuse regression monitoring: https://langfuse.com/docs/evaluation
- NIST AI RMF 1.0: https://www.nist.gov/itl/ai-risk-management-framework
