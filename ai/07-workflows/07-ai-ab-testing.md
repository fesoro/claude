# AI Sistemləri üçün A/B Testinq (Lead)

> **Oxucu:** Baş tərtibatçılar və arxitektlər  
> **Niyə Bu Veb A/B Testindən Daha Çətindir:** AI çıxışları qeyri-deterministikdir, keyfiyyət metriklər subyektivdir və "qalib" yalnız müəyyən giriş paylayışları üçün qalibə çevrilə bilər.

---

## 1. AI A/B Testinin Fərqi Nədir

Ənənəvi A/B testinq klik dərəcələrini, konversiyanı, çıxma dərəcəsini ölçür — hamısı ikili, qeyri-müəyyən siqnallardır. AI A/B testinqi **keyfiyyəti** ölçməyi tələb edir, bu isə:

- Subyektivdir (fərqli istifadəçilər fərqli şeyləri dəyərli sayır)
- Tapşırığa bağlıdır (kod üçün düzgün model yaradıcı yazı üçün düzgün modeldən fərqlənir)
- Ölçmək üçün bahalıdır (insan qiymətləndirilməsi miqyasda işləmir)
- Qeyri-stasionardır (provayder yeniləmələri ilə model keyfiyyəti dəyişir)

**Nəyi test edə bilərsiniz:**

| Ölçü          | Nümunələr                                                        |
|---------------|------------------------------------------------------------------|
| Modellər      | claude-sonnet vs claude-haiku vs gpt-4o                         |
| Promptlar     | Fərqli sistem promptları, göstəriş üslubları                    |
| Temperature   | 0.0 vs 0.7 vs 1.0                                               |
| Kontekst ölçüsü| Tam kontekst vs xülasə edilmiş kontekst                        |
| RAG strategiyası| Top-5 vs top-10 parça, fərqli geri alma metodları             |
| Çıxış formatı | JSON vs nəsr vs strukturlaşdırılmış markdown                    |
| Alət seçimi   | Modelə hansı alətləri təqdim etmək                              |

---

## 2. Metriklər: Nəyi Ölçmək Lazımdır

### 2.1 Avtomatik Metriklər

```
Tapşırığa Xas Metriklər:
├── Klassifikasiya: dəqiqlik, precision, recall, F1
├── Generasiya: ROUGE, BLEU (LLM-lər üçün məhdud fayda)
├── Fakt dəqiqliyi: istinad dəqiqliyi, iddia yoxlama dərəcəsi
└── Kod: testlərin keçmə dərəcəsi, linting balı, icra uğuru

Əməliyyat Metriklər:
├── Gecikmə (P50/P95/P99)
├── Sorğu başına token xərci
├── Xəta dərəcəsi
└── Retry dərəcəsi
```

### 2.2 Hakim kimi LLM

Miqyasda keyfiyyəti ölçmək üçün hal-hazırki ən yaxşı praktika. Çıxış keyfiyyətini qiymətləndirmək üçün daha güclü model istifadə edin:

```
Qiymətləndiricisi (Claude Opus/Sonnet) çıxış keyfiyyətini qiymətləndirir:
- Dəqiqlik (1-5)
- Faydalılıq (1-5)  
- Aydınlıq (1-5)
- Təhlükəsizlik (keçdi/uğursuz oldu)
```

**LLM hakim məhdudiyyətləri:**
- Eyni model ailə qərəzi (Claude hakimləri Claude çıxışlarına üstünlük verir)
- Ətraflılıq qərəzi (uzun = daha yaxşı, yanlış olaraq)
- Mövqe qərəzi (birinci seçim daha çox qalib gəlir)

Azaltmaq: təsadüfi sıralama, çoxlu hakimlər, insan spot-yoxlamaları.

### 2.3 İstifadəçi Əks Əlaqə Siqnalları

Dolayı: sessiya uzunluğu, verilən sonrakı suallar (daha uzun məşğulluq = daha yaxşı cavab)  
Açıq: bəyənmə/bəyənməmə, ulduz reytinqi, "bu faydalı idi mi?"

---

## 3. AI üçün Statistik Əhəmiyyətlilik

Qeyri-deterministik çıxışlarla ənənəvi A/B testlərindən daha çox nümunəyə ehtiyacınız var. Əsas düsturlar:

- **Minimum aşkarlanabilən effekt (MDE):** Aşkarlamağa dəyər ən kiçik yaxşılaşma (keyfiyyət metriklər üçün adətən 5–10%)
- **Nümunə ölçüsü:** n = 2σ²(Zα + Zβ)² / δ²  
  - Tipik: α=0.05-də 80% güc üçün variant başına 500–2000 nümunə
- **Ardıcıl testinq:** Onlayn eksperimentlər üçün istifadə edin — nəticələr aydındırsa erkən dayandırın

---

## 4. AIExperiment Modeli və Xidməti

```php
<?php
// app/Models/AIExperiment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AIExperiment extends Model
{
    use HasUuids;

    protected $casts = [
        'variants'     => 'array',
        'metrics'      => 'array',
        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
        'is_active'    => 'boolean',
    ];

    /**
     * variants formatı:
     * [
     *   'control'   => ['model' => 'claude-haiku-4-5', 'traffic_pct' => 50, 'config' => [...]],
     *   'treatment' => ['model' => 'claude-sonnet-4-5', 'traffic_pct' => 50, 'config' => [...]],
     * ]
     */

    public function observations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExperimentObservation::class);
    }

    public function isVariantValid(string $variant): bool
    {
        return isset($this->variants[$variant]);
    }

    public function getVariantConfig(string $variant): array
    {
        return $this->variants[$variant] ?? $this->variants['control'];
    }
}
```

```php
<?php
// app/Services/AI/ExperimentService.php

namespace App\Services\AI;

use App\Models\AIExperiment;
use App\Models\ExperimentObservation;
use Illuminate\Support\Facades\Cache;

class ExperimentService
{
    /**
     * İstifadəçini/sorğunu varianta təyin et.
     *
     * Təyin:
     * 1. Yapışqandır — eyni istifadəçi həmişə eyni variantı alır
     * 2. Deterministikdir — DB yazısına ehtiyac yoxdur
     * 3. Stratifikasiya edilmişdir — trafik faizlərinə hörmət edir
     */
    public function assignVariant(AIExperiment $experiment, string $subjectId): string
    {
        $cacheKey = "exp:{$experiment->id}:subject:{$subjectId}";

        return Cache::rememberForever($cacheKey, function () use ($experiment, $subjectId) {
            // Deterministik hash — eyni subyekt həmişə eyni nəticəni alır
            $hash = crc32("{$experiment->id}:{$subjectId}") % 100;

            $cumulative = 0;
            foreach ($experiment->variants as $name => $config) {
                $cumulative += $config['traffic_pct'];
                if ($hash < $cumulative) {
                    return $name;
                }
            }

            return array_key_first($experiment->variants);
        });
    }

    /**
     * Müşahidəni qeyd et (metriklərlə AI çağırışı nəticəsi).
     */
    public function record(
        AIExperiment $experiment,
        string       $variant,
        string       $subjectId,
        array        $metrics,
        ?array       $inputSnapshot = null,
        ?string      $outputSnapshot = null,
    ): ExperimentObservation {
        return ExperimentObservation::create([
            'experiment_id'   => $experiment->id,
            'variant'         => $variant,
            'subject_id'      => $subjectId,
            'metrics'         => $metrics,
            'input_snapshot'  => $inputSnapshot,
            'output_snapshot' => $outputSnapshot,
            'recorded_at'     => now(),
        ]);
    }

    /**
     * Bir xüsusiyyət üçün aktiv eksperimenti al.
     */
    public function getActiveExperiment(string $feature): ?AIExperiment
    {
        return Cache::remember("exp:active:{$feature}", 300, fn() =>
            AIExperiment::where('feature', $feature)
                ->where('is_active', true)
                ->where('started_at', '<=', now())
                ->where(fn($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', now()))
                ->first()
        );
    }
}
```

---

## 5. Trafik Bölmə Middleware-i

```php
<?php
// app/Http/Middleware/AIExperimentMiddleware.php

namespace App\Http\Middleware;

use App\Services\AI\ExperimentService;
use Closure;
use Illuminate\Http\Request;

class AIExperimentMiddleware
{
    public function __construct(
        private readonly ExperimentService $experiments,
    ) {}

    /**
     * Eksperiment variantını sorğuya yerləşdir ki, kontrolerlər və AI xidmətləri
     * eksperiment infrastrukturundan xəbərsiz oxuya bilsin.
     */
    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        $experiment = $this->experiments->getActiveExperiment($feature);

        if ($experiment) {
            $subjectId = $this->resolveSubjectId($request);
            $variant   = $this->experiments->assignVariant($experiment, $subjectId);

            // Aşağıdan istifadə üçün sorğuya əlavə et
            $request->merge([
                '__experiment_id' => $experiment->id,
                '__variant'       => $variant,
                '__variant_config'=> $experiment->getVariantConfig($variant),
            ]);

            // AI xidmətlərinin oxuya bilməsi üçün singleton-da da qurun
            app()->instance('ai.experiment', [
                'id'      => $experiment->id,
                'variant' => $variant,
                'config'  => $experiment->getVariantConfig($variant),
            ]);
        }

        return $next($request);
    }

    private function resolveSubjectId(Request $request): string
    {
        // Stabil istifadəçi ID-sinə üstünlük ver; anonim üçün sessiyaya və ya IP-yə get
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }

        return 'session:' . $request->session()->getId();
    }
}
```

```php
// Marşrutlarda istifadə
Route::middleware(['auth:sanctum', 'ai-experiment:summarization'])
    ->post('/summarize', SummarizeController::class);
```

---

## 6. Metrik Toplama və Saxlama

```php
<?php
// app/Observers/AICallObserver.php

namespace App\Observers;

use App\Services\AI\ExperimentService;
use App\Services\AI\LLMJudgeService;

/**
 * Eksperiment müşahidələrini avtomatik qeyd etmək üçün AI xidmət çağırışlarına qoşulun.
 */
class AICallObserver
{
    public function __construct(
        private readonly ExperimentService $experiments,
        private readonly LLMJudgeService   $judge,
    ) {}

    public function onCallComplete(
        string $prompt,
        string $response,
        array  $usage,
        float  $latencyMs,
        string $model,
    ): void {
        $experimentContext = app()->bound('ai.experiment')
            ? app('ai.experiment')
            : null;

        if (! $experimentContext) {
            return;
        }

        $experiment = \App\Models\AIExperiment::find($experimentContext['id']);
        if (! $experiment) return;

        // Avtomatik metriklər topla
        $metrics = [
            'latency_ms'     => $latencyMs,
            'input_tokens'   => $usage['input_tokens'] ?? 0,
            'output_tokens'  => $usage['output_tokens'] ?? 0,
            'cost_usd'       => $this->calculateCost($model, $usage),
            'model'          => $model,
        ];

        // Asinxron LLM hakim qiymətləndirilməsi (cavabı bloklamayın)
        if ($experiment->metrics['use_llm_judge'] ?? false) {
            dispatch(function () use ($experiment, $experimentContext, $metrics, $prompt, $response) {
                $judgment = $this->judge->evaluate($prompt, $response, $experiment->metrics['rubric'] ?? null);
                $metrics['judge_score']   = $judgment['score'];
                $metrics['judge_details'] = $judgment;

                $this->experiments->record(
                    experiment: $experiment,
                    variant: $experimentContext['variant'],
                    subjectId: request()->user()?->id ?? 'anonim',
                    metrics: $metrics,
                    outputSnapshot: substr($response, 0, 500),
                );
            })->afterResponse();
        } else {
            $this->experiments->record(
                experiment: $experiment,
                variant: $experimentContext['variant'],
                subjectId: request()->user()?->id ?? 'anonim',
                metrics: $metrics,
            );
        }
    }

    private function calculateCost(string $model, array $usage): float
    {
        $rates = [
            'claude-opus-4-5'   => ['input' => 15.00, 'output' => 75.00],  // 1M token başına
            'claude-sonnet-4-5' => ['input' => 3.00,  'output' => 15.00],
            'claude-haiku-4-5'  => ['input' => 0.25,  'output' => 1.25],
        ];

        $rate = $rates[$model] ?? $rates['claude-sonnet-4-5'];

        return (($usage['input_tokens'] ?? 0) * $rate['input'] / 1_000_000)
             + (($usage['output_tokens'] ?? 0) * $rate['output'] / 1_000_000);
    }
}
```

---

## 7. Statistik Əhəmiyyətlilik Kalkulyatoru

```php
<?php
// app/Services/AI/StatisticalAnalyzer.php

namespace App\Services\AI;

use App\Models\AIExperiment;
use App\Models\ExperimentObservation;

class StatisticalAnalyzer
{
    /**
     * Kontrol və müalicə arasında metrik üçün iki nümunəli t-test hesabla.
     * Fərqin statistik əhəmiyyətli olub olmadığını qaytarır.
     */
    public function analyzeMetric(
        AIExperiment $experiment,
        string       $metric,
        string       $control   = 'control',
        string       $treatment = 'treatment',
        float        $alpha     = 0.05,
    ): array {
        $controlValues   = $this->getMetricValues($experiment, $control, $metric);
        $treatmentValues = $this->getMetricValues($experiment, $treatment, $metric);

        if (count($controlValues) < 30 || count($treatmentValues) < 30) {
            return [
                'sufficient_data' => false,
                'message'         => 'Etibarlı nəticələr üçün hər variant üçün ən azı 30 nümunə lazımdır.',
                'control_n'       => count($controlValues),
                'treatment_n'     => count($treatmentValues),
            ];
        }

        $controlMean   = $this->mean($controlValues);
        $treatmentMean = $this->mean($treatmentValues);
        $controlStd    = $this->stdDev($controlValues);
        $treatmentStd  = $this->stdDev($treatmentValues);

        // Welch-in t-testi (bərabər dispersiyanı fərz etmir)
        $tStat = ($treatmentMean - $controlMean)
               / sqrt(
                   ($controlStd ** 2 / count($controlValues)) +
                   ($treatmentStd ** 2 / count($treatmentValues))
               );

        // Welch-Satterthwaite sərbəstlik dərəcəsi
        $df = $this->welchDegreesFreedom(
            $controlStd, count($controlValues),
            $treatmentStd, count($treatmentValues)
        );

        $pValue = $this->pValueFromT($tStat, $df);

        $lift = $controlMean > 0
            ? (($treatmentMean - $controlMean) / $controlMean) * 100
            : 0;

        return [
            'sufficient_data'  => true,
            'metric'           => $metric,
            'control_mean'     => round($controlMean, 4),
            'treatment_mean'   => round($treatmentMean, 4),
            'lift_percent'     => round($lift, 2),
            'p_value'          => round($pValue, 4),
            'is_significant'   => $pValue < $alpha,
            'confidence_level' => (1 - $alpha) * 100,
            'control_n'        => count($controlValues),
            'treatment_n'      => count($treatmentValues),
            'recommendation'   => $pValue < $alpha
                ? ($lift > 0 ? "Müalicəni tətbiq et ({$lift}% artım)" : "Kontrolu saxla ({$lift}% geriləmə)")
                : "İşləməyə davam et — hələ əhəmiyyətli fərq yoxdur",
        ];
    }

    public function fullReport(AIExperiment $experiment): array
    {
        $metrics  = ['judge_score', 'latency_ms', 'cost_usd', 'output_tokens'];
        $results  = [];
        $variants = array_keys($experiment->variants);

        // Hər qeyri-kontrol variantı kontrol ilə müqayisə et
        foreach (array_slice($variants, 1) as $treatment) {
            foreach ($metrics as $metric) {
                $key = "{$treatment}_vs_control_{$metric}";
                $results[$key] = $this->analyzeMetric($experiment, $metric, 'control', $treatment);
            }
        }

        return [
            'experiment_id' => $experiment->id,
            'experiment_name'=> $experiment->name,
            'total_observations' => ExperimentObservation::where('experiment_id', $experiment->id)->count(),
            'results'       => $results,
            'generated_at'  => now()->toIso8601String(),
        ];
    }

    private function getMetricValues(AIExperiment $experiment, string $variant, string $metric): array
    {
        return ExperimentObservation::where('experiment_id', $experiment->id)
            ->where('variant', $variant)
            ->whereNotNull("metrics->{$metric}")
            ->pluck("metrics->{$metric}")
            ->map(fn($v) => (float) $v)
            ->toArray();
    }

    private function mean(array $values): float
    {
        return array_sum($values) / count($values);
    }

    private function stdDev(array $values): float
    {
        $mean = $this->mean($values);
        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / (count($values) - 1);
        return sqrt($variance);
    }

    private function welchDegreesFreedom(float $s1, int $n1, float $s2, int $n2): float
    {
        $a = $s1 ** 2 / $n1;
        $b = $s2 ** 2 / $n2;
        return (($a + $b) ** 2) / (($a ** 2 / ($n1 - 1)) + ($b ** 2 / ($n2 - 1)));
    }

    private function pValueFromT(float $t, float $df): float
    {
        // Böyük df üçün normal paylanma istifadə edərək yaxınlaşma
        // İstehsal üçün düzgün statistika kitabxanasından istifadə edin (məs: markrogoyski/math-php)
        if ($df > 30) {
            return 2 * (1 - $this->normalCdf(abs($t)));
        }
        // Sadələşdirilmiş — normalCdf-i yaxınlaşma kimi istifadə et
        return 2 * (1 - $this->normalCdf(abs($t)));
    }

    private function normalCdf(float $x): float
    {
        return 0.5 * (1 + $this->erf($x / sqrt(2)));
    }

    private function erf(float $x): float
    {
        // Abramowitz və Stegun yaxınlaşması
        $t = 1 / (1 + 0.3275911 * abs($x));
        $y = 1 - (((((1.061405429 * $t - 1.453152027) * $t) + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);
        return $x >= 0 ? $y : -$y;
    }
}
```

---

## 8. Dashboard Məlumat API-si

```php
<?php
// app/Http/Controllers/AI/ExperimentDashboardController.php

namespace App\Http\Controllers\AI;

use App\Models\AIExperiment;
use App\Models\ExperimentObservation;
use App\Services\AI\StatisticalAnalyzer;
use Illuminate\Http\JsonResponse;

class ExperimentDashboardController extends Controller
{
    public function __construct(
        private readonly StatisticalAnalyzer $analyzer,
    ) {}

    public function summary(AIExperiment $experiment): JsonResponse
    {
        $this->authorize('view', $experiment);

        // Zaman üzrə variant başına müşahidələr (trend qrafiki üçün)
        $timeSeries = ExperimentObservation::where('experiment_id', $experiment->id)
            ->selectRaw('variant, DATE(recorded_at) as date, COUNT(*) as n, AVG(metrics->>"$.judge_score") as avg_score, AVG(metrics->>"$.cost_usd") as avg_cost')
            ->groupBy('variant', 'date')
            ->orderBy('date')
            ->get();

        // Statistik analiz
        $stats = $this->analyzer->fullReport($experiment);

        // Keyfiyyət yoxlaması üçün nümunə çıxışlar
        $samples = ExperimentObservation::where('experiment_id', $experiment->id)
            ->whereNotNull('output_snapshot')
            ->inRandomOrder()
            ->limit(10)
            ->get(['variant', 'output_snapshot', 'metrics', 'recorded_at']);

        return response()->json([
            'experiment'  => [
                'id'         => $experiment->id,
                'name'       => $experiment->name,
                'status'     => $experiment->is_active ? 'aktiv' : 'dayandırıldı',
                'started_at' => $experiment->started_at,
                'variants'   => $experiment->variants,
            ],
            'statistics'  => $stats,
            'time_series' => $timeSeries,
            'samples'     => $samples,
        ]);
    }

    public function declare(AIExperiment $experiment): JsonResponse
    {
        $this->authorize('manage', $experiment);

        $request = request()->validate([
            'winner'  => 'required|string',
            'reason'  => 'required|string',
            'action'  => 'required|in:ship,rollback,continue',
        ]);

        $experiment->update([
            'is_active'    => false,
            'ended_at'     => now(),
            'winner'       => $request['winner'],
            'conclusion'   => $request['reason'],
            'final_action' => $request['action'],
        ]);

        // Göndərirsə — xüsusiyyət flag-ını müalicənin 100%-nə yenilə
        if ($request['action'] === 'ship') {
            app(\App\Services\FeatureFlagService::class)->setVariant(
                $experiment->feature,
                $request['winner'],
                100
            );
        }

        return response()->json(['status' => 'yekunlaşdırıldı']);
    }
}
```

---

## 9. AI Dəyişiklikləri üçün Canary Deployment-lər

```php
<?php
// Canary deployment: 5%-dən başla, izlə, tədricən artır

// Addım 1: Yeni prompta 5% trafik ilə eksperiment yarat
$experiment = AIExperiment::create([
    'name'       => 'Yeni xülasə promptu v2',
    'feature'    => 'summarization',
    'is_active'  => true,
    'started_at' => now(),
    'variants'   => [
        'control'   => ['traffic_pct' => 95, 'prompt_version' => 'v1'],
        'treatment' => ['traffic_pct' => 5,  'prompt_version' => 'v2'],
    ],
    'metrics'    => ['use_llm_judge' => true, 'rubric' => 'Dəqiqlik və tamlıq (1-10)'],
]);

// Addım 2: 500 müşahidədən sonra — nəticələri yoxla
// Addım 3: p < 0.05 və artım > 0 olarsa → 50%-ə artır
// Addım 4: 50%-də sabit olarsa → 100%-ə artır və yekunlaşdır
```

---

## 10. AI A/B Testinqi üçün Əsas Prinsiplər

1. **Əvvəlcə minimum nümunə ölçüsü** — başlamazdan əvvəl tələb olunan nümunələri hesablayın. Gücsüz testlər yürütmək hesablamanı israf edir və qərarları yanlış istiqamətləndirir.
2. **Eyni anda bir dəyişkəni test edin** — model VƏ promptu eyni vaxtda dəyişdirmək atribusiyanı qeyri-mümkün edir.
3. **Giriş tipi ilə seqmentasiya edin** — ümumiyyətlə qalibi gələn model müəyyən giriş kateqoriyaları üçün uduza bilər. Həmişə alt qrup performansını yoxlayın.
4. **Erkən dayandırmayın** — AI metriklər klik dərəcələrindən daha yüksək dispersiyaya malikdir. Erkən dayandırmaya ehtiyacınız varsa ardıcıl test metodlarından istifadə edin.
5. **LLM hakimlərin kalibrasiyası lazımdır** — model sürüşməsini aşkar etmək üçün hakim ballarını vaxtaşırı insan reytinqləri ilə yoxlayın.
