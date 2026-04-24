# 30 — AI Agent Evaluation Pattern-ləri: Trajectory, End-State, Benchmark

> **Oxucu kütləsi:** Senior developerlər, ML engineer-lər
> **Bu faylın 11-dən fərqi:** 11 — agent eval ümumi giriş. Bu fayl — **konkret eval pattern-ləri**: reference-based vs LLM-as-judge vs human, trajectory match vs end-state correctness, SWE-bench / AgentBench / Terminal-Bench, Braintrust/Promptfoo dataset inteqrasiyası, CI pipeline, Laravel test setup.

---

## 1. Eval Növlərinin Taksonomiyası

```
┌─────────────────────────────────────────────────────────────┐
│                      EVALUATION TİPLƏRİ                      │
└─────────────────────────────────────────────────────────────┘
                  ↓                       ↓
         Reference-based              Reference-free
                  ↓                       ↓
      ┌───────────┴────────┐    ┌────────┴────────┐
      │ Exact/Fuzzy Match  │    │  LLM-as-Judge   │
      │ BLEU / ROUGE       │    │  Human Eval     │
      │ Structural (JSON)  │    │  User Feedback  │
      └────────────────────┘    └─────────────────┘

         TRAJECTORY-LEVEL           END-STATE LEVEL
                ↓                          ↓
       Doğru tool-ları           Yalnız son nəticə
       doğru sırada istifadə      mühümdür
       etdi?
```

### Müqayisə

| Tip | Nə ölçür | Nə vaxt | Qiymət |
|-----|---------|---------|--------|
| **Exact match** | `output == expected` | Strukturlu çıxış (SQL, JSON, classification) | Pulsuz, dəqiq |
| **Fuzzy (BLEU/ROUGE)** | Token overlap | Tərcümə, summary | Pulsuz, kobud |
| **Structural (JSON schema)** | Schema uyğunluğu | Tool args, API response | Pulsuz, ikili |
| **LLM-as-judge** | Semantik keyfiyyət | Chat, summary, analysis | $0.001-0.01/sample |
| **Human eval** | Gold standard | Yeni sistem, ambiguous task | $1-10/sample |
| **User feedback (thumbs)** | Real product fit | Production trafik | Pulsuz (passive) |

---

## 2. Reference-Based Metriklər

### 2.1 Exact Match

`SELECT COUNT(*) FROM users WHERE ...` kimi deterministic output üçün:

```php
$score = (normalizeSQL($predicted) === normalizeSQL($expected)) ? 1.0 : 0.0;
```

Normalization mühümdür — whitespace, case, quotation uyğunsuzluqları rol oynamamalıdır.

### 2.2 BLEU (Bilingual Evaluation Understudy)

N-gram overlap ölçür. Tərcümə tarixindən gəlir. Qısa cavablara qərəzli.

```
BLEU-4 = brevity_penalty × exp(mean(log(precision_1..4)))
```

Kod cavabı üçün **CodeBLEU** daha uyğundur — syntax tree uyğunluğunu da hesablayır.

### 2.3 ROUGE (Recall-Oriented Understudy for Gisting Evaluation)

Summary üçün. ROUGE-L — longest common subsequence-a baxır.

### 2.4 Strukturlu Match (JSON)

```php
<?php
// tests/AI/Evals/StructuredMatchTest.php

use App\Services\AI\Evals\StructuredMatcher;

it('matches tool use structure', function () {
    $expected = [
        'name' => 'query_database',
        'input' => [
            'sql' => 'SELECT id FROM users WHERE email = ?',
            'params' => ['alice@example.com'],
        ],
    ];

    $actual = $agent->generateToolCall("Find user by email alice@example.com");

    $matcher = new StructuredMatcher();
    expect($matcher->score($actual, $expected))->toBeGreaterThan(0.9);
});
```

`StructuredMatcher` key-level uyğunluq (name exact, sql AST-level fuzzy, params exact) qaytarır.

### Limitations

Reference-based metriklər **tək doğru cavab** olduğunu fərz edir. LLM-də çox vaxt **çox doğru cavab** olur. `"Summarize in 3 bullets"` üçün 100 fərqli keyfiyyətli summary var.

---

## 3. LLM-as-Judge Pattern

```php
<?php
// app/Services/AI/Evals/LLMJudge.php

namespace App\Services\AI\Evals;

use Anthropic\Anthropic;

class LLMJudge
{
    public function __construct(private Anthropic $client) {}

    public function score(string $input, string $output, array $criteria): JudgeResult
    {
        $criteriaList = implode("\n", array_map(
            fn ($c) => "- {$c['name']}: {$c['description']}",
            $criteria
        ));

        $rubric = <<<PROMPT
You are an expert evaluator. Score the assistant's output against each criterion on a 1-5 scale.

CRITERIA:
{$criteriaList}

INPUT:
{$input}

OUTPUT:
{$output}

Return strict JSON:
{
  "scores": [{"criterion": "...", "score": 1-5, "reason": "..."}],
  "overall": 1-5,
  "blocking_issues": ["..."]
}
PROMPT;

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-6', // judge Opus-dan ucuzdur, amma Haiku-dan güclüdür
            'max_tokens' => 1024,
            'temperature' => 0.0, // determinism
            'messages' => [['role' => 'user', 'content' => $rubric]],
        ]);

        $raw = $response->content[0]->text;
        $parsed = json_decode($raw, true);

        return new JudgeResult(
            overall: $parsed['overall'] / 5,
            details: $parsed['scores'],
            issues: $parsed['blocking_issues'] ?? [],
        );
    }
}
```

### Judge Model Seçimi

| Judge | Qiymət (input+output ~500 tok) | Güvən |
|-------|-------------------------------|-------|
| Opus 4.7 | $0.045 | Ən yüksək, slow |
| Sonnet 4.6 | $0.009 | Yaxşı — default seçim |
| Haiku 4.5 | $0.003 | Kifayət qədər, volume üçün |
| GPT-4 / GPT-5 | $0.010-0.020 | Cross-family check faydalıdır |

**Yaxşı praktika:** judge sistemi evaluated sistemdən güclü olmalıdır. Opus output-unu Haiku-ya judge etdirməyin.

### Judge Calibration

Judge özü də xətalıdır. Başlanğıcda:

1. 50 sample-da insan qiymət qoyun (gold).
2. Eyni sample-ları judge-a verin.
3. Cohen's kappa və ya Pearson correlation hesablayın.
4. κ > 0.6 olduqda judge istifadə edilə bilər. Aşağıdırsa, rubric-i düzəldin.

---

## 4. Gold-Set Construction

Eval-in keyfiyyəti dataset-in keyfiyyəti ilə qaytarılır.

### Mənbələr

1. **Production traces** — `03-llm-observability.md`-də göstərildiyi kimi, real trafiki promote edin.
2. **Red-team scenarios** — intentionally hard / adversarial case-lər.
3. **Edge cases** — uzun input, boş input, multi-language, typo-lu.
4. **Recent bugs** — hər production bug eval-ə əlavə olunmalıdır (regression test).
5. **Synthetic generation** — LLM-dən fərqli input variantları yaratmaq üçün.

### Dataset Şeması

```php
// database/migrations/create_eval_sets_table.php

Schema::create('eval_sets', function (Blueprint $table) {
    $table->id();
    $table->string('set_name');                  // 'regression', 'chat-v2', 'code-review'
    $table->string('feature');                    // 'summarize', 'tool-use', və s.
    $table->text('input_prompt');
    $table->text('reference_output')->nullable(); // reference-free eval-lərdə null
    $table->json('metadata')->nullable();         // tool set, context docs, tenant
    $table->json('rubric')->nullable();           // LLM-judge üçün kriteriyalar
    $table->json('tags')->nullable();             // ['source:production', 'captured:2026-04']
    $table->string('difficulty')->default('medium'); // easy/medium/hard/adversarial
    $table->timestamps();
    $table->index(['set_name', 'feature']);
});
```

### Dataset Size Təklifləri

| Faza | Sample | Məqsəd |
|------|--------|--------|
| Prototip | 10-30 | Prompt iteration loop |
| MVP | 100-300 | Əsas regression suite |
| Production | 500-2000 | Model comparison, A/B eval |
| Mature | 5000+ | Statistical significance, çox ölçü |

---

## 5. Regression Testing

Hər prompt / model dəyişikliyindən əvvəl keçir, score düşmə yaranmasın.

```php
<?php
// tests/Feature/AI/SummarizeRegressionTest.php

use App\Models\EvalSet;
use App\Services\AI\SummarizeService;
use App\Services\AI\Evals\LLMJudge;

it('summarize feature maintains score above baseline', function () {
    $samples = EvalSet::where('set_name', 'summarize-regression')
        ->where('difficulty', '!=', 'adversarial')
        ->get();

    $service = app(SummarizeService::class);
    $judge = app(LLMJudge::class);

    $scores = [];
    foreach ($samples as $sample) {
        $output = $service->summarize($sample->input_prompt);
        $result = $judge->score(
            input: $sample->input_prompt,
            output: $output,
            criteria: $sample->rubric,
        );
        $scores[] = $result->overall;
    }

    $mean = array_sum($scores) / count($scores);

    // Baseline — əvvəlki release-dən saxlanır
    $baseline = 0.82;
    $tolerance = 0.03;

    expect($mean)->toBeGreaterThanOrEqual($baseline - $tolerance);
})->group('ai-regression');
```

CI-də yalnız `ai-regression` group-u spesifik job-da çalışdırın — çünki yavaş və LLM xərci var.

---

## 6. Multi-Turn Evals

Agent çoxaddımlı söhbətdə kontekst saxlaya bilirmi?

```php
it('preserves context across turns', function () {
    $conversation = [
        ['user' => 'Q3 gəlirimiz nə qədər olub?', 'expects' => '/\$2[\.,]3M/'],
        ['user' => 'Q2 ilə müqayisədə necədir?', 'expects' => '/12%.*art/i'],
        ['user' => 'Artımı nə şərtləndirib?', 'expects' => null], // yalnız non-empty + relevance
    ];

    $agent = app(FinancialAgent::class);
    $session = $agent->startSession();

    foreach ($conversation as $turn) {
        $response = $session->send($turn['user']);

        if ($turn['expects']) {
            expect($response)->toMatch($turn['expects']);
        } else {
            expect($response)->not->toBeEmpty();
        }
    }
});
```

---

## 7. Tool-Use Evals: Trajectory vs End-State

Agent tool-larla işləyəndə iki fərqli sual:

### 7.1 Trajectory Match

Agent **doğru tool-ları doğru sırada** çağırdı?

```php
it('follows expected tool trajectory', function () {
    $agent = app(SupportAgent::class);

    // Input
    $ticket = 'My subscription was charged twice last month.';

    // Expected sequence
    $expected = [
        ['tool' => 'lookup_customer', 'order' => 1],
        ['tool' => 'fetch_charges', 'order' => 2],
        ['tool' => 'issue_refund', 'order' => 3],
    ];

    $trace = $agent->run($ticket);
    $actualTools = collect($trace->toolCalls)->pluck('name')->toArray();

    // Exact match strict — LLM-in istənilən variasiyasına icazə vermir
    // Subset match daha yaxşıdır
    foreach ($expected as $step) {
        expect($actualTools)->toContain($step['tool']);
    }
});
```

Problem: LLM çox vaxt fərqli yollar tapır — məsələn, `fetch_charges`-dən sonra əvvəl `send_email` edir, sonra `issue_refund`. Strict sequence match overly-strict-dir.

### 7.2 End-State Correctness

Agent **son vəziyyəti doğru** etdimi? Tool sırası mühüm deyil, yalnız nəticə.

```php
it('reaches correct end state', function () {
    $agent = app(SupportAgent::class);

    // Sandbox DB state
    $customer = Customer::factory()->create(['id' => 42]);
    $charge1 = Charge::factory()->for($customer)->create(['amount' => 1000]);
    $charge2 = Charge::factory()->for($customer)->create(['amount' => 1000]);

    $agent->run("Refund customer 42's duplicate charge.");

    // End state: 1 refund yaradılıb, 1 charge qalıb
    expect(Refund::where('customer_id', 42)->count())->toBe(1);
    expect(Refund::sum('amount'))->toBe(1000);
});
```

**End-state eval ümumən daha sağlamdır.** Trajectory eval yalnız xüsusi compliance tələbi olanda (audit trail, approval chain) istifadə olunur.

### Hybrid: Invariant Check

Trajectory-dən və end-state-dən invariant-lar çıxarın:

- "issue_refund heç vaxt lookup_customer-dan əvvəl gəlməməlidir" (sequence constraint)
- "refund amount <= sum of original charges" (business rule)
- "send_email yalnız bir dəfə çağırılsın" (frequency)

---

## 8. Agent Benchmarkları

### 8.1 SWE-bench

Real GitHub issue-ları həll etmə tapşırığı. Agent repo-ya verilir, issue-u həll edib PR göndərməlidir. Test suite passed olduqda success.

- **SWE-bench Verified** — 500 verified problem.
- Pass@1 metric: neçə faiz issue-nu bir cəhddə həll etdi.
- 2025 leaderboard-un başında: Claude Opus 4.7 ~70%, GPT-5 ~65%.

### 8.2 AgentBench

8 mühitdə agent qabiliyyəti: OS, DB, Knowledge Graph, Digital Card Game, Lateral Thinking Puzzle, House Holding, Web Shopping, Web Browsing.

### 8.3 Terminal-Bench

Terminal-də çoxaddımlı tapşırıqlar: "install package X, run it, fix the resulting error". Real sandbox, real state.

### 8.4 TAU-Bench (Tool-Agent-User)

Customer service simulation. Real user policy-ləri + tool API-ləri. Agent iki sub-agent ilə danışır (user-persona LLM, policy-LLM).

### 8.5 GAIA

128 "simple for human, hard for AI" suallar. Multi-step web research.

### Öz Benchmark-ınızı Qurmaq

Production domain-inizdə benchmark ayrı olmalıdır — SWE-bench üçün optimal agent sizin e-commerce support agent-inizdə zəif ola bilər.

---

## 9. Braintrust / Promptfoo / Langfuse Dataset İnteqrasiyası

### Promptfoo (YAML driven)

```yaml
# promptfooconfig.yaml
prompts:
  - "Summarize: {{text}}"
providers:
  - anthropic:messages:claude-sonnet-4-6
  - anthropic:messages:claude-haiku-4-5-20251001
tests:
  - vars: { text: "..." }
    assert:
      - type: llm-rubric
        value: "Summary is 3 bullets, each under 20 words"
      - type: contains
        value: "revenue"
  - vars: { text: "..." }
    assert:
      - type: factuality
        value: "Ground truth: Q3 was $2.3M"
```

Run: `promptfoo eval && promptfoo view` — HTML comparison matrix.

### Langfuse Dataset (Python SDK, also Node/PHP)

```typescript
import { Langfuse } from 'langfuse';

const lf = new Langfuse();

// Dataset yüklə
const dataset = await lf.getDataset('summarize-regression');

for (const item of dataset.items) {
  const output = await runAgent(item.input);
  await item.link(output, 'run-' + Date.now(), {
    evaluator: async (i, o) => llmJudge(i, o),
  });
}
```

### Braintrust

```typescript
import { Eval } from 'braintrust';

Eval("Summarize", {
  data: async () => fetchDataset('summarize-v1'),
  task: async (input) => runAgent(input),
  scores: [Factuality, ClosedQA, Humor],
});
```

---

## 10. CI Pipeline İnteqrasiyası

```yaml
# .github/workflows/ai-evals.yml
name: AI Regression Evals

on:
  pull_request:
    paths:
      - 'app/Services/AI/**'
      - 'config/ai.php'
      - 'prompts/**'

jobs:
  regression:
    runs-on: ubuntu-latest
    timeout-minutes: 30
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-interaction

      - name: Run AI regression suite
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
          EVAL_BASELINE_SCORE: '0.82'
        run: ./vendor/bin/pest --group=ai-regression --parallel

      - name: Upload eval results
        uses: actions/upload-artifact@v4
        with:
          name: eval-results
          path: storage/eval-results/
```

### Cost Budget Guard

```php
<?php
// tests/Feature/AI/CostGuardTest.php

it('regression suite stays within cost budget', function () {
    $totalCost = 0.0;
    $startTime = microtime(true);

    // ... run all evals, accumulate cost ...

    $duration = microtime(true) - $startTime;

    expect($totalCost)->toBeLessThan(5.00); // $5 per CI run
    expect($duration)->toBeLessThan(600); // 10 min
});
```

---

## 11. Laravel Test Setup

`phpunit.xml` / `pest.php`:

```php
// tests/Pest.php
uses(Tests\TestCase::class)
    ->group('ai-regression')
    ->in('Feature/AI');

// Custom expect() extensions
expect()->extend('toMatchRubric', function (array $criteria, float $threshold = 0.7) {
    $judge = app(\App\Services\AI\Evals\LLMJudge::class);
    $result = $judge->score('', $this->value, $criteria);
    return $this->toPassWhen($result->overall >= $threshold);
});
```

Istifadə:

```php
it('returns polite tone', function () {
    $output = $agent->respond('I want a refund');
    expect($output)->toMatchRubric([
        ['name' => 'politeness', 'description' => 'Response uses polite tone'],
        ['name' => 'solution', 'description' => 'Response offers a concrete next step'],
    ], 0.8);
});
```

---

## 12. Eval Bias və Limitations

### 12.1 Judge Bias

LLM judge özünə bənzər model outputunu üstün tuta bilər ("self-preference bias"). Həll: fərqli model ailəsi istifadə et (Claude judges GPT output və ya tərsinə).

### 12.2 Position Bias

A/B müqayisəsində judge A-nı (birinci) üstün tutur. Həll: pozisiyaları randomize et, 2-yolluq qiymətləndir.

### 12.3 Verbosity Bias

Uzun cavablar judge-dan daha yüksək score alır. Həll: rubric-də "be concise" kriteriyasını explicit əlavə et.

### 12.4 Eval Set Dataset Shift

Production trafik dəyişir. Eval set 6 ay köhnə olarsa, artıq reprezentativ deyil. Həll: regulyar promote production traces + versioning.

### 12.5 Flakiness

Temperature > 0 və ya non-det model davranışı scorelardan variance əlavə edir. Həll: 3-5 sample orta götür, temperature 0, seed fix (mümkün olsa).

---

## 13. Eval Cost Budget

Regression run-nın qiymətini hesablayın:

```
N samples × (avg input + output tokens) × model cost/tok
+ N samples × judge input/output × judge cost/tok
```

**Tipik 300-sample regression suite**:

- Main model (Sonnet 4.6): 300 × 3000 tok × ~$9/1M ≈ $8.1
- Judge (Haiku 4.5): 300 × 1500 tok × ~$3/1M ≈ $1.35
- **Total: ~$10/run**

Hər PR-də tam run = $10 × 20 PR/gün = $200/gün. **Slice stratejisi**:

- PR-də yalnız touched feature-ə aid alt-set (100 sample) — $3/PR
- Tam run yalnız release branch-da və ya gecə cron-da

---

## 14. Müsahibə Xülasəsi

- **Reference-based vs reference-free**: exact/BLEU/ROUGE strukturlu output üçün; LLM-judge/human ambiguous task üçün.
- **Trajectory vs end-state**: trajectory match fragile (çox doğru yol var); end-state correctness daha sağlam; invariant check hybrid.
- **LLM-as-judge calibration**: insan qiymət ilə κ > 0.6 olmalı, judge evaluated sistemindən güclü olmalı.
- **Judge bias-ları**: self-preference, position, verbosity — hər birinə spesifik mitigation.
- **Gold set mənbələri**: production traces, red-team, edge cases, bug regressions, synthetic.
- **Agent benchmarkları**: SWE-bench (real PR), AgentBench (8 env), Terminal-Bench (shell), TAU-Bench (support sim), GAIA.
- **Multi-turn eval** kontekst persistance-ı ölçür.
- **Dataset size ierarxiyası**: 10→100→500→5000 sample faza üzrə.
- **Promptfoo (YAML)** / **Braintrust (TS)** / **Langfuse (multi-SDK)** — bazar alətləri.
- **CI inteqrasiyası**: `ai-regression` group, cost budget assert, PR-də slice-only, nightly full run.
- **Laravel Pest setup**: custom `expect()->toMatchRubric()` extension.
- **Flakiness**: temperature 0, seed fix, 3-5 sample orta.
