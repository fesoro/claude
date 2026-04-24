# Agent Qiymətləndirilməsi və Evals

## AI Mühəndisliyinin Ən Çətin Problemi

AI sistemlərini qiymətləndirmək ənənəvi proqram təminatını qiymətləndirməkdən fundamental olaraq daha çətindir. `assert response == expected_output` yoxdur. Düzgünlük bulanıq, kontekstdən asılı və tez-tez insan mühakiməsi tələb edir.

Lakin qiymətləndirmə olmadan:
- Prompt dəyişikliyinin yaxşılaşma mı, geriləmə mi olduğunu anlaya bilməzsiniz
- Model versiyalarını obyektiv şəkildə müqayisə edə bilməzsiniz
- İstehsalatda keyfiyyətin aşağı düşməsini aşkar edə bilməzsiniz
- AI məhsul qərarlarını məlumatla əsaslandıra bilməzsiniz

Evals — AI mühəndisliyini intuisiyadan müəndisliyə çevirən bir intizamdır.

---

## Qiymətləndirmə Növləri

### Vahid Evals (Tək Addımlı)

Bir LLM çağırışını ayrıca sınayın. Giriş → gözlənilən çıxış, meyarlara qarşı skor verilir.

```
Giriş:    "Bu müqaviləni 3 siyahı nöqtəsinə xülasə et."
Çıxış:   "• A tərəfi 50K$ ödəməyə razılaşır... • Çatdırılma iyuna qədər..."
Meyarlar: Tamlıq, dəqiqlik, format uyğunluğu
Skor:    0.0 – 1.0
```

**Ən uyğun olduğu yer**: təsnifatçılar, çıxarıcılar, tək addımlı çevirmələr.

### Çox Addımlı Evals

Bir neçə addımda söhbəti sınayın. Agentin kontekstini qoruyub-qorumamasını, ardıcıllı sualları düzgün idarə edib-etmədiyini və doğru cavaba çatıb-çatmadığını qiymətləndirin.

```
Addım 1: "Q3 gəlirimiz nə qədər olub?"     → "2.3M$"
Addım 2: "Q2 ilə müqayisədə necədir?"       → "12% artıb"  (Q3 kontekstini istifadə etməlidir)
Addım 3: "Artımı nə şərtləndirib?"          → ardıcıl analiz
```

**Ən uyğun olduğu yer**: chatbotlar, köməkçilər, istənilən vəziyyətli qarşılıqlı əlaqə.

### Uçdan-Uca (U2U) Evals

Tam agent pipeline-ı real tapşırıqlarda sınayın. Agent məqsədə nail oldumu?

```
Məqsəd: "Q3-dəki ən yüksək gəlirli 3 müştərini tapın və hər birinə e-poçt hazırlayın"

Qiymətləndirmə meyarları:
  ✓ Düzgün məlumatları sorğuladı
  ✓ Düzgün ilk 3-ü müəyyən etdi
  ✓ 3 ayrı e-poçt hazırladı
  ✓ E-poçtlar fərdiləşdirilmiş və peşəkardır
  ✓ < 10 iterasiyada tamamlandı
```

**Ən uyğun olduğu yer**: agentlər, mürəkkəb pipeline-lar, uçdan-uca biznes prosesləri.

---

## Qiymətləndirmə Metrikalari

| Metrik | Tərif | Necə Ölçülür |
|---|---|---|
| **Sədaqət** | Çıxış mənbəyə sadiqdir? | LLM hakim + fakt çıxarma |
| **Uyğunluq** | Cavab suala uyğundur? | LLM hakim |
| **Tamlıq** | Bütün tələb olunan aspektlər əhatə edilib? | Siyahı skorlaması |
| **Dəqiqlik** | Faktiki iddialar düzgündür? | İstinad müqayisəsi |
| **Format** | Çıxış tələb olunan formatadır? | Regex / sxem validasiyası |
| **Alət istifadəsi dəqiqliyi** | Agent düzgün alətləri düzgün arqumentlərlə çağırdı? | Deterministik yoxlama |
| **Məqsədə çatma** | Agent göstərilən məqsədə nail oldu? | İkili ya da çox səviyyəli |
| **Səmərəlilik** | İstifadə edilən iterasiyalar / tokenlər / xərc | Birbaşa ölçülür |

---

## LLM-as-Judge Patterni

Əsas fikir: bir (potensial olaraq zəif) LLM-in çıxışını qiymətləndirmək üçün güclü LLM istifadə edin. Bu, hər cavabı əllə etiketləmədən böyük miqyasda avtomatik qiymətləndirməyə imkan verir.

```
                   ┌──────────────┐
   Giriş + Məqsəd ──▶│  Agent/LLM   │──▶ Çıxış
                   └──────────────┘
                          │
                          │ (çıxış hakimə göndərilir)
                          ▼
                   ┌──────────────┐
   Rubrik ────────▶│   Claude     │──▶ Skor (0-5) + Mühakimə
   (meyarlar)      │   (Hakim)    │
                   └──────────────┘
```

**LLM hakimləri üçün əsas dizayn qərarları**:
1. **Qiymətləndirilən modeldən daha güclü model hakim kimi istifadə edin**
2. **Skor vermədən əvvəl düşüncə zənciri**: hakimi rəqəm vermədən əvvəl düşünməyə məcbur edin
3. **Kalibrlənmiş rubrikalar**: dəqiq meyarlar hakim fərqliliyini azaldır
4. **Mövqe qərəzliyindən qaçın**: cütlü müqayisələr üçün A/B sırasını təsadüfi seçin və ortalamasını alın

---

## Qızıl Datasetlər Qurmaq

Qızıl dataset — real istifadə hallarının paylanmasını təmsil edən seçilmiş (giriş, gözlənilən çıxış) cütlər toplusudur.

### Toplama Strategiyası

1. **İstehsalat qeydləri**: istifadəçi razılığı ilə istehsalatdan real sorğuları nümunə alın
2. **Sintetik yaratma**: müxtəlif test halları yaratmaq üçün LLM-lərdən istifadə edin
3. **Düşmənçilik halları**: qəsdən çətin girişlər — kənar hallar, qeyri-müəyyən sorğular
4. **Ekspert seçimi**: sahə mütəxəssisləri düzgünlüyü təsdiqləyir

### Dataset Keyfiyyət Tələbləri

```
Minimum reallaşdırılabilir eval dataseti:
  - Hər kateqoriya/imkan üçün 50+ nümunə
  - Təmsili paylanma (yalnız asan hallar deyil)
  - Ən azı 20% üçün insan tərəfindən doğrulanmış əsas həqiqət
  - Uğursuzluq rejimləri arasında balans

İstehsalat dərəcəli:
  - 500+ nümunə
  - Yeni istehsalat nümunələri ilə aylıq yeniləmə
  - A/B validasiyası: insan vs LLM hakim uyğunluğu > 85%
```

---

## Laravel İmplementasiyası

### 1. Evals üçün Verilənlər Bazası Sxemi

```php
<?php
// database/migrations/xxxx_create_eval_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eval_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version')->default('1.0');
            $table->text('description')->nullable();
            $table->string('type'); // unit|multi_turn|e2e
            $table->timestamps();
        });

        Schema::create('eval_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('eval_datasets')->cascadeOnDelete();
            $table->json('input');            // Prompt / mesajlar
            $table->json('expected_output')->nullable(); // Qızıl cavab (varsa)
            $table->json('criteria');         // Nəyə qarşı qiymətləndiriləcək
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('eval_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('eval_datasets');
            $table->string('model');
            $table->string('prompt_version');
            $table->string('git_commit', 40)->nullable();
            $table->json('config')->nullable();   // Temperatur və s.
            $table->float('avg_score')->nullable();
            $table->json('score_breakdown')->nullable();
            $table->unsignedInteger('total_cases');
            $table->unsignedInteger('passed_cases')->default(0);
            $table->unsignedInteger('failed_cases')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->float('total_cost_usd')->default(0);
            $table->string('status')->default('pending'); // pending|running|completed|failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('eval_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('eval_runs')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('eval_cases');
            $table->json('actual_output');
            $table->float('score');           // 0.0 - 1.0
            $table->json('score_details');    // Meyara görə skorlar
            $table->text('judge_reasoning')->nullable();
            $table->boolean('passed');
            $table->unsignedInteger('tokens_used')->default(0);
            $table->float('cost_usd')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
        });
    }
};
```

### 2. EvalRunner

```php
<?php

namespace App\AI\Evals;

use App\Models\EvalDataset;
use App\Models\EvalRun;
use App\Models\EvalResult;
use Anthropic\Client;
use Illuminate\Support\Facades\Log;

class EvalRunner
{
    private const PASS_THRESHOLD = 0.7; // Skor >= 0.7 = keçdi

    public function __construct(
        private readonly Client $claude,
        private readonly LlmJudge $judge,
    ) {}

    public function run(
        EvalDataset $dataset,
        string $model,
        string $promptVersion,
        callable $systemUnderTest, // fn(array $input) → string
        array $config = [],
    ): EvalRun {
        $evalRun = EvalRun::create([
            'dataset_id'     => $dataset->id,
            'model'          => $model,
            'prompt_version' => $promptVersion,
            'git_commit'     => $this->getGitCommit(),
            'config'         => $config,
            'total_cases'    => $dataset->cases()->active()->count(),
            'status'         => 'running',
            'started_at'     => now(),
        ]);

        $scores = [];
        $totalTokens = 0;
        $totalCost = 0.0;

        foreach ($dataset->cases()->active()->cursor() as $case) {
            try {
                $startTime = microtime(true);

                // Test edilən sistemi çalıştırın
                $actualOutput = $systemUnderTest($case->input);

                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                // Çıxışı hakimlə qiymətləndirin
                $judgment = $this->judge->evaluate(
                    input: $case->input,
                    actualOutput: $actualOutput,
                    expectedOutput: $case->expected_output,
                    criteria: $case->criteria,
                );

                $passed = $judgment->score >= self::PASS_THRESHOLD;
                $scores[] = $judgment->score;

                EvalResult::create([
                    'run_id'          => $evalRun->id,
                    'case_id'         => $case->id,
                    'actual_output'   => ['text' => $actualOutput],
                    'score'           => $judgment->score,
                    'score_details'   => $judgment->criterionScores,
                    'judge_reasoning' => $judgment->reasoning,
                    'passed'          => $passed,
                    'tokens_used'     => $judgment->tokensUsed,
                    'cost_usd'        => $judgment->costUsd,
                    'latency_ms'      => $latencyMs,
                ]);

                $totalTokens += $judgment->tokensUsed;
                $totalCost   += $judgment->costUsd;

                if ($passed) {
                    $evalRun->increment('passed_cases');
                } else {
                    $evalRun->increment('failed_cases');
                    Log::info('Eval halı uğursuz oldu', [
                        'run_id'  => $evalRun->id,
                        'case_id' => $case->id,
                        'score'   => $judgment->score,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Eval halında xəta', [
                    'run_id'  => $evalRun->id,
                    'case_id' => $case->id,
                    'error'   => $e->getMessage(),
                ]);
                $evalRun->increment('failed_cases');
            }
        }

        $avgScore = !empty($scores) ? array_sum($scores) / count($scores) : 0.0;

        $evalRun->update([
            'avg_score'       => $avgScore,
            'total_tokens'    => $totalTokens,
            'total_cost_usd'  => $totalCost,
            'status'          => 'completed',
            'completed_at'    => now(),
            'score_breakdown' => $this->computeBreakdown($evalRun),
        ]);

        return $evalRun->refresh();
    }

    private function computeBreakdown(EvalRun $run): array
    {
        $results = $run->results()->get();

        return [
            'pass_rate'         => $run->passed_cases / max($run->total_cases, 1),
            'avg_latency_ms'    => $results->avg('latency_ms'),
            'p95_latency_ms'    => $results->sortBy('latency_ms')
                ->values()
                ->get((int) ($results->count() * 0.95))
                ?->latency_ms,
            'score_distribution' => [
                'excellent' => $results->where('score', '>=', 0.9)->count(),
                'good'      => $results->whereBetween('score', [0.7, 0.9])->count(),
                'poor'      => $results->where('score', '<', 0.7)->count(),
            ],
        ];
    }

    private function getGitCommit(): ?string
    {
        $output = shell_exec('git rev-parse HEAD 2>/dev/null');
        return $output ? trim($output) : null;
    }
}
```

### 3. LLM-as-Judge

```php
<?php

namespace App\AI\Evals;

use Anthropic\Client;

final class JudgmentResult
{
    public function __construct(
        public readonly float  $score,          // 0.0 - 1.0
        public readonly array  $criterionScores, // meyara görə
        public readonly string $reasoning,
        public readonly int    $tokensUsed,
        public readonly float  $costUsd,
    ) {}
}

class LlmJudge
{
    private const JUDGE_MODEL = 'claude-opus-4-5'; // Hakimlik üçün ən güclü model
    private const JUDGE_PROMPT = <<<'PROMPT'
    You are an expert evaluator for AI systems. Your job is to objectively assess AI outputs against specified criteria.

    You will be given:
    - INPUT: The original input/prompt
    - ACTUAL OUTPUT: What the AI produced
    - EXPECTED OUTPUT: The ideal answer (if available, otherwise null)
    - CRITERIA: What to evaluate against

    For each criterion, provide:
    1. A score from 0-5 (0=completely fails, 5=perfect)
    2. Brief reasoning

    Then provide an overall score from 0.0 to 1.0.

    IMPORTANT:
    - Be calibrated: a 0.7 should mean "acceptable but has notable issues"
    - Use the full range: don't cluster around 0.8
    - Be specific in your reasoning, citing examples from the output
    - Return valid JSON only, no other text

    JSON format:
    {
      "criterion_scores": {
        "criterion_name": { "score": 0-5, "reasoning": "..." }
      },
      "overall_score": 0.0-1.0,
      "reasoning": "Overall assessment...",
      "key_issues": ["issue1", "issue2"]
    }
    PROMPT;

    public function __construct(
        private readonly Client $claude,
    ) {}

    public function evaluate(
        mixed  $input,
        string $actualOutput,
        mixed  $expectedOutput,
        array  $criteria,
    ): JudgmentResult {
        $inputText    = is_array($input) ? json_encode($input, JSON_PRETTY_PRINT) : $input;
        $expectedText = $expectedOutput
            ? (is_array($expectedOutput) ? json_encode($expectedOutput) : $expectedOutput)
            : "No specific expected output — evaluate on absolute quality.";

        $criteriaText = collect($criteria)->map(
            fn($desc, $name) => "- {$name}: {$desc}"
        )->join("\n");

        $response = $this->claude->messages()->create([
            'model'      => self::JUDGE_MODEL,
            'max_tokens' => 1024,
            'system'     => self::JUDGE_PROMPT,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => <<<EVAL
                    INPUT:
                    {$inputText}

                    ACTUAL OUTPUT:
                    {$actualOutput}

                    EXPECTED OUTPUT:
                    {$expectedText}

                    CRITERIA:
                    {$criteriaText}

                    Evaluate now.
                    EVAL,
                ],
            ],
        ]);

        $content = $response->content[0]->text;
        $parsed  = json_decode($content, true);

        $tokensUsed = $response->usage->inputTokens + $response->usage->outputTokens;

        // Claude Opus qiymətləndirməsi
        $costUsd = ($response->usage->inputTokens  / 1_000_000 * 15.00)
                 + ($response->usage->outputTokens / 1_000_000 * 75.00);

        return new JudgmentResult(
            score:           (float) ($parsed['overall_score'] ?? 0),
            criterionScores: $parsed['criterion_scores'] ?? [],
            reasoning:       $parsed['reasoning'] ?? '',
            tokensUsed:      $tokensUsed,
            costUsd:         $costUsd,
        );
    }

    /**
     * Cütlü müqayisə: hansı çıxış daha yaxşıdır?
     */
    public function compare(
        mixed  $input,
        string $outputA,
        string $outputB,
        array  $criteria,
    ): string { // 'A', 'B' ya da 'tie' qaytarır
        // Mövqe qərəzliyinin qarşısını almaq üçün sıranı təsadüfi seçin
        $swap = rand(0, 1) === 1;
        [$first, $second] = $swap ? [$outputB, $outputA] : [$outputA, $outputB];

        $response = $this->claude->messages()->create([
            'model'      => self::JUDGE_MODEL,
            'max_tokens' => 512,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => "Compare these two AI outputs for the given input. Which is better?\n\nInput: " . json_encode($input) . "\n\nOutput 1:\n{$first}\n\nOutput 2:\n{$second}\n\nCriteria: " . json_encode($criteria) . "\n\nRespond with JSON: {\"winner\": \"1\" or \"2\" or \"tie\", \"reasoning\": \"...\"}",
                ],
            ],
        ]);

        $result = json_decode($response->content[0]->text, true);
        $winner = $result['winner'] ?? 'tie';

        // Əvəzetmə nəzərə alınaraq geri çevirin
        if ($swap) {
            return match($winner) {
                '1' => 'B',
                '2' => 'A',
                default => 'tie',
            };
        }

        return match($winner) {
            '1' => 'A',
            '2' => 'B',
            default => 'tie',
        };
    }
}
```

### 4. Geriləmə İzlənməsi

```php
<?php

namespace App\AI\Evals;

use App\Models\EvalRun;

class RegressionDetector
{
    private const REGRESSION_THRESHOLD = 0.05; // 5% skor düşüşü = geriləmə

    /**
     * Yeni işi baza xətti ilə müqayisə edin, geriləmələri aşkar edin.
     */
    public function detect(EvalRun $newRun): RegressionReport
    {
        $baseline = $this->findBaseline($newRun);

        if (!$baseline) {
            return RegressionReport::noBaseline($newRun);
        }

        $scoreDelta = $newRun->avg_score - $baseline->avg_score;
        $isRegression = $scoreDelta < -self::REGRESSION_THRESHOLD;

        // Geriyə gedən xüsusi halları tapın
        $regressedCases = [];
        if ($isRegression) {
            $regressedCases = $this->findRegressedCases($newRun, $baseline);
        }

        return new RegressionReport(
            newRun:          $newRun,
            baselineRun:     $baseline,
            scoreDelta:      $scoreDelta,
            isRegression:    $isRegression,
            regressedCases:  $regressedCases,
        );
    }

    private function findBaseline(EvalRun $run): ?EvalRun
    {
        return EvalRun::where('dataset_id', $run->dataset_id)
            ->where('status', 'completed')
            ->where('id', '!=', $run->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function findRegressedCases(EvalRun $newRun, EvalRun $baseline): array
    {
        // İşlər arasında hal başına skorları müqayisə edin
        $newResults      = $newRun->results()->with('case')->get()->keyBy('case_id');
        $baselineResults = $baseline->results()->get()->keyBy('case_id');

        return $newResults
            ->filter(function ($newResult, $caseId) use ($baselineResults) {
                $baselineResult = $baselineResults[$caseId] ?? null;
                if (!$baselineResult) return false;

                return ($newResult->score - $baselineResult->score) < -self::REGRESSION_THRESHOLD;
            })
            ->map(fn($r, $caseId) => [
                'case_id'        => $caseId,
                'new_score'      => $r->score,
                'baseline_score' => $baselineResults[$caseId]->score,
                'delta'          => $r->score - $baselineResults[$caseId]->score,
            ])
            ->values()
            ->toArray();
    }
}

final class RegressionReport
{
    public function __construct(
        public readonly EvalRun $newRun,
        public readonly ?EvalRun $baselineRun,
        public readonly float $scoreDelta,
        public readonly bool $isRegression,
        public readonly array $regressedCases,
    ) {}

    public static function noBaseline(EvalRun $run): static
    {
        return new static($run, null, 0.0, false, []);
    }

    public function summary(): string
    {
        if (!$this->baselineRun) {
            return "Bu dataset üçün ilk iş — müqayisə üçün baza xətti yoxdur.";
        }

        $direction = $this->scoreDelta >= 0 ? 'yaxşılaşdı' : 'pisləşdi';
        $change    = abs(round($this->scoreDelta * 100, 1));

        $msg = "Skor baza xəttinə nisbətən {$change}% {$direction} (iş #{$this->baselineRun->id}).";

        if ($this->isRegression) {
            $msg .= " GERİLƏMƏ AŞKAR EDİLDİ. {$this->regressedCases->count()} hal geridə qaldı.";
        }

        return $msg;
    }
}
```

### 5. CI İnteqrasiyası (GitHub Actions)

```yaml
# .github/workflows/eval.yml

name: AI Eval Dəsti

on:
  push:
    paths:
      - 'app/AI/**'
      - 'resources/prompts/**'
  pull_request:
    branches: [main]

jobs:
  evals:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: pgvector/pgvector:pg16
        env:
          POSTGRES_DB: eval_test
          POSTGRES_PASSWORD: secret
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s

    steps:
      - uses: actions/checkout@v4

      - name: PHP qur
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_pgsql, redis

      - name: Asılılıqları qur
        run: composer install --no-dev

      - name: Eval dəstini çalıştır
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
          DB_CONNECTION: pgsql
          DB_DATABASE: eval_test
        run: php artisan eval:run --dataset=core --fail-on-regression

      - name: Eval nəticələrini yüklə
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: eval-results-${{ github.sha }}
          path: storage/eval-reports/
```

### Laravel Artisan Əmri

```php
<?php

namespace App\Console\Commands;

use App\AI\Evals\EvalRunner;
use App\AI\Evals\LlmJudge;
use App\AI\Evals\RegressionDetector;
use App\Models\EvalDataset;
use Illuminate\Console\Command;

class RunEvals extends Command
{
    protected $signature = 'eval:run
                            {--dataset=core : Çalıştırılacaq dataset adı}
                            {--model=claude-haiku-4-5 : Qiymətləndiriləcək model}
                            {--prompt-version= : Prompt versiya etiketi}
                            {--fail-on-regression : Geriləmə aşkar edilsə 1 kodla çıx}';

    protected $description = 'AI sisteminə qarşı qiymətləndirmə dəstini çalıştır';

    public function handle(EvalRunner $runner, LlmJudge $judge, RegressionDetector $detector): int
    {
        $dataset = EvalDataset::where('name', $this->option('dataset'))->firstOrFail();
        $model   = $this->option('model');
        $version = $this->option('prompt-version') ?? 'HEAD';

        $this->info("Çalıştırılır: {$dataset->cases()->active()->count()} eval halı...");
        $bar = $this->output->createProgressBar($dataset->cases()->active()->count());

        // Test edilən sistem — real AI sisteminizlə əvəzləyin
        $systemUnderTest = function (array $input) use ($model) {
            $response = app(\Anthropic\Client::class)->messages()->create([
                'model'    => $model,
                'max_tokens' => 2048,
                'messages' => $input['messages'],
                'system'   => $input['system'] ?? null,
            ]);
            $bar->advance();
            return $response->content[0]->text;
        };

        $run = $runner->run(
            dataset:        $dataset,
            model:          $model,
            promptVersion:  $version,
            systemUnderTest: $systemUnderTest,
        );

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metrik', 'Dəyər'],
            [
                ['Ortalama Skor',    number_format($run->avg_score, 3)],
                ['Keçmə Faizi',    number_format($run->score_breakdown['pass_rate'] * 100, 1) . '%'],
                ['Keçən Hallar', $run->passed_cases],
                ['Uğursuz Hallar', $run->failed_cases],
                ['Ümumi Xərc',   '$' . number_format($run->total_cost_usd, 4)],
            ],
        );

        $report = $detector->detect($run);
        $this->line($report->summary());

        if ($report->isRegression && $this->option('fail-on-regression')) {
            $this->error('GERİLƏMƏ AŞKAR EDİLDİ — CI build uğursuz edilir');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
```

---

## Eval Dizaynının Anti-Patternləri

### 1. Eval Datasetinizə Həddən Artıq Uyğunlaşmaq

Eval skorlarını xüsusi olaraq optimize etdikdə real sistemi yaxşılaşdırmaqdan əl çəkmiş olursunuz. Əlamətlər: eval skorları artır, istehsalat keyfiyyəti sabit qalır ya da pisləşir.

**Düzəliş**: Heç kim tərəfindən optimize edilməyən "gizli" test dəsti ayırın. Yalnız rüblük qiymətləndirmə üçün istifadə edin.

### 2. Qərəzli Qızıl Datasetlər

Qızıl dataset indi yaxşılaşdırdığınız eyni prompt/model tərəfindən yaradıldıysa, orijinal modelin qərəzlərini kodlaya bilər.

**Düzəliş**: Əsas həqiqəti müxtəlif insanların yaratmasını təmin edin. Bir neçə etiketçi ilə doğrulayın, etiketçilər arası uyğunluğu ölçün.

### 3. Tək Metrik Qiymətləndirmə

Tək skoru (məsələn, uyğunluq) optimize etmək, digərlərini (məsələn, təhlükəsizlik, format uyğunluğu) nəzərə almadan tək oxlu optimizasiyaya gətirib çıxarır.

**Düzəliş**: Çəkili çox ölçülü skorlama istifadə edin. Ölçüləri biznes əhəmiyyətinə görə çəkə verin.

### 4. Paylanma Dəyişikliyini Nəzərə Almamaq

Eval dəstiniz 6 ay əvvəl yaradılmışdır. İstifadəçilərinizin sorğuları inkişaf etmişdir. Eval artıq reallığı əks etdirmir.

**Düzəliş**: Son istehsalat nümunələri ilə eval datasetlərinin aylıq yenilənməsi.

### 5. Kalibrasiya Olmadan LLM Hakim

Kalibrasiya edilməmiş LLM hakim sistematik qərəzli olacaq — məsələn, həmişə geniş cavablara yüksək skor verəcək ya da müəyyən bir modelin çıxışlarını üstün tutacaq.

**Düzəliş**: 50+ nümunədə insan mühakimələri ilə LLM hakiminin uyğunluğunu doğrulayın. ≥ 85% korrelyasiya hədəfləyin.
