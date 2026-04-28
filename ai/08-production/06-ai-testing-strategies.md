# AI ilə Gücləndirilmiş Tətbiqlərin Test Edilməsi (Senior)

> **Oxucu kütləsi:** Senior developerlər və arxitektlər  
> **Əsas Çətinlik:** LLM-lər qeyri-deterministikdir. Eyni prompt fərqli cavablar qaytara bilər. Ənənəvi assert-lər işləmir.

---

## 1. Ənənəvi Testlərin AI üçün Niyə İşləmədiyi

```php
// Bu test AI sistemləri üçün YANLIŞ-dır:
public function test_summarization(): void
{
    $summary = $ai->summarize("The sky is blue.");
    $this->assertEquals("The sky is blue.", $summary); // Təsadüfi uğursuz olacaq
}

// Daha yaxşısı: dəqiq məzmun yox, çıxışın xüsusiyyətlərini test et
public function test_summarization(): void
{
    $summary = $ai->summarize($longDocument);
    $this->assertLessThan(strlen($longDocument), strlen($summary)); // Daha qısadır
    $this->assertNotEmpty($summary);                                  // Məzmun var
    // Və ya keyfiyyəti yoxlamaq üçün LLM mühakiməsi istifadə et
}
```

**Qeyri-determinizm problemi:**
- Temperature > 0 → hər icrada fərqli çıxış
- Provayder modeli yeniləmələri → kod dəyişmədən çıxış dəyişir
- Kontekst pəncərəsi effektləri → uzun söhbətlərdə incə fərqlər

**Qiymətləndirmə problemi:**
- Xülasə üçün "doğru" nə deməkdir?
- Chatbot cavabının "faydalı" olduğunu necə assert edə bilərsiniz?
- Unit test assert-ləri semantik keyfiyyəti ölçə bilmir

---

## 2. AI Test Piramidası

```
        ┌─────────────────┐
        │   Eval Testlər  │  ← LLM mühakiməsi, qızıl datasetlər
        │  (Yavaş, bahalı)│    Gecə işlət, deploy-dan əvvəl
        ├─────────────────┤
        │  İnteqrasiya    │  ← VCR kasseta (yazılmış cavablar)
        │   Testləri      │    Deterministik; hər PR-da işlət
        ├─────────────────┤
        │   Unit Testlər  │  ← Yalnız deterministik komponentlər
        │  (AI çağırışsız)│    Token sayğacları, prompt qurucular, parser-lər
        └─────────────────┘
```

**Sadə qayda:**
- Unit testlər: sürətli, pulsuz, 100% deterministik
- İnteqrasiya testləri: VCR kasseta, qeydiyyatdan sonra pulsuz, tam deterministik
- Eval testlər: bahalı (real API çağırışları), cədvəl üzrə və böyük buraxılışlardan əvvəl işlət

---

## 3. VCR/Kasseta Nümunəsi: Real Cavabları Yaz, Testlərdə Oynat

Kasseta nümunəsi real API cavablarını yazaraq və sonrakı test icraları zamanı onları oynadaraq determinizm problemini həll edir.

```php
<?php
// app/Testing/AICassette.php

namespace App\Testing;

use Illuminate\Support\Facades\Http;

/**
 * AI API çağırışları üçün VCR tərzi kasseta yazıcısı.
 *
 * İlk icra: real API çağırışları edir və cavabları diskə yazır.
 * Sonrakı icralar: yazılmış cavabları oynadır — sürətli, pulsuz, deterministik.
 *
 * İstifadə:
 *   AICassette::record('mənim-test-kassetam', function() {
 *       // AI çağırışları edən test kodunuz
 *   });
 */
class AICassette
{
    private static ?string $activeCassette = null;
    private static array   $recordings     = [];
    private static int     $playbackIndex  = 0;

    public static function record(string $name, \Closure $test): void
    {
        $cassettePath = self::cassettePath($name);

        if (file_exists($cassettePath)) {
            // Oynatma rejimi
            self::startPlayback($name);
        } else {
            // Yazma rejimi
            self::startRecording($name);
        }

        try {
            $test();
        } finally {
            self::stop($name);
        }
    }

    private static function startRecording(string $name): void
    {
        self::$activeCassette = $name;
        self::$recordings     = [];

        // HTTP çağırışlarını tut
        Http::fake(function ($request) {
            // Real sorğu göndər
            $response = Http::withoutFaking()->send($request->method(), $request->url(), [
                'headers' => $request->headers(),
                'body'    => $request->body(),
            ]);

            // Yaz
            self::$recordings[] = [
                'url'     => $request->url(),
                'method'  => $request->method(),
                'request' => [
                    'headers' => $request->headers(),
                    'body'    => json_decode($request->body(), true),
                ],
                'response' => [
                    'status'  => $response->status(),
                    'headers' => $response->headers(),
                    'body'    => $response->json(),
                ],
                'recorded_at' => now()->toIso8601String(),
            ];

            return Http::response($response->json(), $response->status());
        });
    }

    private static function startPlayback(string $name): void
    {
        self::$recordings    = json_decode(file_get_contents(self::cassettePath($name)), true);
        self::$playbackIndex = 0;

        Http::fake(function ($request) {
            $recording = self::$recordings[self::$playbackIndex] ?? null;

            if (! $recording) {
                throw new \RuntimeException("Kasseta bitdi — yazılmış cavablardan çox sorğu var.");
            }

            self::$playbackIndex++;

            return Http::response(
                $recording['response']['body'],
                $recording['response']['status'],
            );
        });
    }

    private static function stop(string $name): void
    {
        if (! empty(self::$recordings) && ! file_exists(self::cassettePath($name))) {
            // Yeni yazıları saxla
            $dir = dirname(self::cassettePath($name));
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents(
                self::cassettePath($name),
                json_encode(self::$recordings, JSON_PRETTY_PRINT)
            );
        }

        Http::clearFakes();
        self::$activeCassette = null;
        self::$recordings     = [];
        self::$playbackIndex  = 0;
    }

    private static function cassettePath(string $name): string
    {
        return base_path("tests/cassettes/{$name}.json");
    }
}
```

---

## 4. AI Mock Yardımçıları ilə Əsas TestCase

```php
<?php
// tests/TestCase.php

namespace Tests;

use App\Testing\AICassette;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Claude-u sabit cavab ilə mock et — unit testlər üçün.
     */
    protected function mockClaude(string $response, array $usage = []): void
    {
        $defaultUsage = ['input_tokens' => 100, 'output_tokens' => 50, ...$usage];

        $this->mock(\App\Services\AI\ClaudeService::class, function ($mock) use ($response, $defaultUsage) {
            $mock->shouldReceive('complete')
                ->andReturn($response);

            $mock->shouldReceive('messages')
                ->andReturn($response);

            $mock->shouldReceive('stream')
                ->andReturnUsing(function () use ($response) {
                    yield ['type' => 'token', 'text' => $response];
                    yield ['type' => 'usage', 'input_tokens' => 100, 'output_tokens' => 50];
                });
        });
    }

    /**
     * Claude-u bir neçə ardıcıl cavabla mock et.
     * Hər çağırış massivdəki növbəti cavabı alır.
     */
    protected function mockClaudeSequence(array $responses): void
    {
        $this->mock(\App\Services\AI\ClaudeService::class, function ($mock) use ($responses) {
            $mock->shouldReceive('complete')
                ->andReturnValues($responses);
        });
    }

    /**
     * İnteqrasiya testləri üçün VCR kasseta istifadə et.
     */
    protected function withCassette(string $name, \Closure $test): void
    {
        AICassette::record($name, $test);
    }

    /**
     * AI cavabının LLM mühakiməsi vasitəsilə keyfiyyət meyarlarına uyğunluğunu assert et.
     */
    protected function assertAIQuality(
        string $prompt,
        string $response,
        string $criteria,
        float  $minScore = 7.0,
    ): void {
        $judge = app(\App\Services\AI\LLMJudgeService::class);
        $result = $judge->evaluate($prompt, $response, $criteria);

        $this->assertGreaterThanOrEqual(
            $minScore,
            $result['score'],
            "AI cavab keyfiyyəti balı {$result['score']} minimum {$minScore}-dan aşağıdır.\n" .
            "Rəy: {$result['feedback']}"
        );
    }

    /**
     * Çıxışın gözlənilən semantik mənaya uyğunluğunu assert et (dəqiq mətn yox).
     */
    protected function assertSemanticallySimilar(
        string $expected,
        string $actual,
        float  $minSimilarity = 0.85,
    ): void {
        $embeddings = app(\App\Services\AI\EmbeddingService::class);

        $e1 = $embeddings->embed($expected);
        $e2 = $embeddings->embed($actual);

        $similarity = $this->cosineSimilarity($e1, $e2);

        $this->assertGreaterThanOrEqual(
            $minSimilarity,
            $similarity,
            "Semantik oxşarlıq {$similarity} həddən {$minSimilarity}-dən aşağıdır.\n" .
            "Gözlənilən məna: {$expected}\n" .
            "Faktiki cavab: {$actual}"
        );
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot   = array_sum(array_map(fn($x, $y) => $x * $y, $a, $b));
        $normA = sqrt(array_sum(array_map(fn($x) => $x * $x, $a)));
        $normB = sqrt(array_sum(array_map(fn($x) => $x * $x, $b)));

        return $normA * $normB > 0 ? $dot / ($normA * $normB) : 0;
    }
}
```

---

## 5. LLM Mühakiməsi ilə Eval Əsaslı Assert-lər

```php
<?php
// app/Services/AI/LLMJudgeService.php

namespace App\Services\AI;

class LLMJudgeService
{
    public function __construct(
        private readonly ClaudeService $claude,
    ) {}

    /**
     * Claude-u mühakimə kimi istifadə edərək AI çıxış keyfiyyətini qiymətləndir.
     *
     * @return array{score: float, feedback: string, details: array}
     */
    public function evaluate(
        string  $prompt,
        string  $response,
        ?string $rubric = null,
        ?string $referenceAnswer = null,
    ): array {
        $rubricText = $rubric ?? 'Faydalılıq (30%), Dəqiqlik (40%), Aydınlıq (30%)';

        $referenceSection = $referenceAnswer
            ? "\n<reference_answer>\n{$referenceAnswer}\n</reference_answer>"
            : '';

        $judgment = $this->claude->complete(
            model: 'claude-sonnet-4-5',
            prompt: <<<PROMPT
            Siz ekspert qiymətləndiricisisiniz. Prompta verilən AI cavabını qiymətləndirin.

            <prompt>{$prompt}</prompt>
            <response>{$response}</response>
            {$referenceSection}

            Qiymətləndirmə rubrikası: {$rubricText}

            1–10 arasında bal verin (10 = əla). Ciddi olun — 8+ produksiyaya hazır deməkdir.

            Yalnız JSON formatında cavab verin:
            {
              "score": <1-10 arası rəqəm>,
              "strengths": ["..."],
              "weaknesses": ["..."],
              "feedback": "<bir cümlədə xülasə>",
              "would_you_use_this_response": <true|false>
            }
            PROMPT,
            maxTokens: 500,
        );

        $data = json_decode($judgment, true) ?? [];

        return [
            'score'       => (float) ($data['score'] ?? 0),
            'feedback'    => $data['feedback'] ?? '',
            'strengths'   => $data['strengths'] ?? [],
            'weaknesses'  => $data['weaknesses'] ?? [],
            'would_use'   => $data['would_you_use_this_response'] ?? false,
            'raw'         => $data,
        ];
    }

    /**
     * İki cavabı müqayisə et və daha yaxşısını seç.
     */
    public function compare(string $prompt, string $responseA, string $responseB): array
    {
        $result = $this->claude->complete(
            model: 'claude-sonnet-4-5',
            prompt: <<<PROMPT
            Eyni prompta verilən bu iki AI cavabını müqayisə edin.

            <prompt>{$prompt}</prompt>
            <response_a>{$responseA}</response_a>
            <response_b>{$responseB}</response_b>

            Hansı daha yaxşıdır? JSON formatında cavab verin:
            {
              "winner": "A" və ya "B" və ya "tie",
              "confidence": <0.0-1.0>,
              "reason": "<niyə>"
            }
            PROMPT,
        );

        return json_decode($result, true) ?? ['winner' => 'tie', 'confidence' => 0];
    }
}
```

---

## 6. Qızıl Dataset Test İcraçısı

```php
<?php
// tests/AI/GoldenDatasetTest.php

namespace Tests\AI;

use App\Services\AI\LLMJudgeService;
use Tests\TestCase;

/**
 * Qızıl dataset testləri AI keyfiyyətinin geriləmədiyini yoxlayır.
 *
 * Qızıl dataset formatı (tests/datasets/summarization.json):
 * [
 *   {
 *     "id": "test-001",
 *     "input": "Uzun sənəd mətni...",
 *     "expected_properties": {
 *       "min_length": 50,
 *       "max_length": 300,
 *       "must_contain": ["əsas nəticə"],
 *       "must_not_contain": ["uydurulmuş iddia"]
 *     },
 *     "min_quality_score": 7.5
 *   }
 * ]
 */
class GoldenDatasetTest extends TestCase
{
    private LLMJudgeService $judge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->judge = app(LLMJudgeService::class);
    }

    /**
     * @dataProvider summarizationDataset
     */
    public function test_summarization_quality(array $testCase): void
    {
        $service  = app(\App\Services\AI\SummarizationService::class);
        $summary  = $service->summarize($testCase['input']);
        $props    = $testCase['expected_properties'];

        // Xüsusiyyət yoxlamaları (deterministik)
        if (isset($props['min_length'])) {
            $this->assertGreaterThan($props['min_length'], strlen($summary), "Xülasə çox qısadır");
        }
        if (isset($props['max_length'])) {
            $this->assertLessThan($props['max_length'], strlen($summary), "Xülasə çox uzundur");
        }
        foreach ($props['must_contain'] ?? [] as $phrase) {
            $this->assertStringContainsStringIgnoringCase($phrase, $summary, "Tələb olunan ifadə yoxdur: {$phrase}");
        }
        foreach ($props['must_not_contain'] ?? [] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase($phrase, $summary, "Qadağan olunmuş ifadə var: {$phrase}");
        }

        // Keyfiyyət yoxlaması (LLM mühakiməsi) — yalnız API açarı varsa
        if (config('services.anthropic.key') && ($testCase['min_quality_score'] ?? false)) {
            $eval = $this->judge->evaluate(
                prompt: "Xülasə et: " . $testCase['input'],
                response: $summary,
                rubric: "Dəqiqlik (50%), Qısalıq (30%), Aydınlıq (20%)",
            );

            $this->assertGreaterThanOrEqual(
                $testCase['min_quality_score'],
                $eval['score'],
                "Test {$testCase['id']} üzrə keyfiyyət geriləməsi: {$eval['feedback']}"
            );
        }
    }

    public static function summarizationDataset(): array
    {
        $datasetPath = base_path('tests/datasets/summarization.json');

        if (! file_exists($datasetPath)) {
            return []; // Dataset yoxdursa atla
        }

        $cases = json_decode(file_get_contents($datasetPath), true);

        return collect($cases)
            ->mapWithKeys(fn($c) => [$c['id'] => [$c]])
            ->toArray();
    }
}
```

---

## 7. Kasseta ilə İnteqrasiya Testi Nümunəsi

```php
<?php
// tests/Feature/AI/SummarizationTest.php

namespace Tests\Feature\AI;

use App\Jobs\AI\SummarizeDocumentJob;
use App\Models\Document;
use Tests\TestCase;

class SummarizationTest extends TestCase
{
    public function test_summarizes_document_and_stores_result(): void
    {
        $this->withCassette('summarize-document-basic', function () {
            $document = Document::factory()->create([
                'content' => 'Rüblük nəticələr gəlirdə 15% artım göstərir...',
            ]);

            SummarizeDocumentJob::dispatchSync($document->id);

            $document->refresh();

            $this->assertNotNull($document->summary);
            $this->assertNotNull($document->summarized_at);
            $this->assertNotNull($document->summary_model);
            $this->assertLessThan(strlen($document->content), strlen($document->summary));
        });
    }

    public function test_falls_back_to_haiku_when_sonnet_unavailable(): void
    {
        $this->withCassette('summarize-fallback-to-haiku', function () {
            // Kasseta uğursuz Sonnet çağırışı + uğurlu Haiku çağırışı ehtiva etməlidir
            $document = Document::factory()->create();

            SummarizeDocumentJob::dispatchSync($document->id);

            $document->refresh();
            $this->assertEquals('claude-haiku-4-5', $document->summary_model);
        });
    }
}
```

---

## 8. Deterministik Komponentlər üçün Unit Testlər

```php
<?php
// tests/Unit/AI/TokenCounterTest.php

namespace Tests\Unit\AI;

use App\Services\AI\TokenCounter;
use Tests\TestCase;

class TokenCounterTest extends TestCase
{
    private TokenCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->counter = new TokenCounter();
    }

    public function test_estimates_token_count_for_short_text(): void
    {
        $estimate = $this->counter->estimate("Hello world");
        $this->assertGreaterThan(0, $estimate);
        $this->assertLessThan(10, $estimate);
    }

    public function test_validates_request_within_context_window(): void
    {
        $messages = [
            ['role' => 'user', 'content' => str_repeat('word ', 100)],
        ];

        $result = $this->counter->validateRequest($messages, 1000, 'claude-sonnet-4-5');
        $this->assertTrue($result->valid);
    }

    public function test_rejects_request_exceeding_context_window(): void
    {
        $messages = [
            ['role' => 'user', 'content' => str_repeat('word ', 60000)], // ~75k token
        ];

        $result = $this->counter->validateRequest($messages, 150000, 'claude-sonnet-4-5');
        $this->assertFalse($result->valid);
        $this->assertTrue($result->willExceedWindow);
    }
}
```

```php
<?php
// tests/Unit/AI/ModelRouterTest.php

namespace Tests\Unit\AI;

use App\Services\AI\ModelRouter;
use Tests\TestCase;

class ModelRouterTest extends TestCase
{
    private ModelRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new ModelRouter();
    }

    public function test_routes_classification_to_haiku(): void
    {
        $model = $this->router->route('classify', 'Bu müsbətdir yoxsa mənfi?');
        $this->assertEquals('claude-haiku-4-5', $model);
    }

    public function test_routes_complex_reasoning_to_opus(): void
    {
        $model = $this->router->route('complex-reasoning', 'Səbəb-nəticə amillərini analiz et...');
        $this->assertEquals('claude-opus-4-5', $model);
    }

    public function test_complex_long_input_routes_to_sonnet_or_opus(): void
    {
        $longInput = str_repeat('Bu mürəkkəb bir analizdir. ', 500);
        $model = $this->router->route('summarize', $longInput);
        $this->assertContains($model, ['claude-sonnet-4-5', 'claude-opus-4-5']);
    }
}
```

---

## 9. CI/CD İnteqrasiyası

```yaml
# .github/workflows/ai-tests.yml

name: AI Testləri

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]
  schedule:
    - cron: '0 2 * * *'  # Gecəlik eval testləri

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Unit testlər işlət (API çağırışsız)
        run: php artisan test --testsuite=Unit

  integration-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: İnteqrasiya testlərini işlət (kasseta oynatma)
        run: php artisan test --testsuite=Feature
        # API açarı lazım deyil — kasseta cavabları təmin edir

  eval-tests:
    runs-on: ubuntu-latest
    if: github.event_name == 'schedule' || contains(github.event.head_commit.message, '[run-evals]')
    steps:
      - uses: actions/checkout@v4
      - name: Eval testlərini işlət (real API çağırışları)
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
        run: php artisan test --testsuite=Eval
```

---

## 10. Qaçınılmalı Test Anti-Nümunələri

| Anti-Nümunə                            | Problem                               | Həll                            |
|----------------------------------------|---------------------------------------|---------------------------------|
| AI çıxışında dəqiq string uyğunlaşdırma | Hər cavab variantında uğursuz olur  | Xüsusiyyət/keyfiyyət assert-ləri |
| HTTP qatında təsadüfi mock etmə        | Testlər keçir amma real kod test edilmir | İnteqrasiya üçün kasseta istifadə et |
| API açarı olmadıqda testləri atlamaq   | CI-da geriləmələri qaçırır            | Kasseta açarsız işləyir          |
| Hər commit-də eval testlərini işlətmək | Çox yavaş və bahalıdır                | Gecəlik cədvəllə                 |
| Kassetaları versiya idarəsinə almamaq  | Təkrarlanmayan test icraları           | Kassetaları git-ə commit et      |
| Testlərdə produksiya prompt-larını istifadə etmək | Prompt dəyişdikdə testlər uğursuz olur | Davranışla test et, prompt-la yox |

## Praktik Tapşırıqlar

### 1. Kasseta (Cassette) Test Suite
`php-vcr` kitabxanasını quraşdırın. 20 real Claude sorğusunu kasseta kimi record edin. Bu kassetalar üzərindən deterministic unit test suite yazın. CI pipeline-a əlavə edin: `phpunit --testsuite=ai-unit`. Bütün testlər API açarı olmadan işləməlidir. Kassetaları `tests/cassettes/` qovluğuna git-ə commit edin.

### 2. Nightly Eval Runner
Laravel Command yazın: `php artisan ai:eval-nightly`. Bu command 50 benchmark sorğusunu real API-ə göndərir, LLM-as-judge ilə score-ları hesablayır, nəticəni `eval_runs` cədvəlinə yazır. Əgər ortalama score əvvəlki gecəyə nisbətən `>5%` azalıbsa, sabah sübh Slack-a xəbərdarlıq göndərir. GitHub Actions cron (`0 2 * * *`) ilə avtomatlaşdırın.

### 3. Regression Test Dataset
Production-dan keçmiş 200 uğurlu AI sorğusu toplayın (user-approved və ya high-score). Bunları `eval_golden_set` cədvəlinə əlavə edin. Hər yeni model/prompt dəyişikliyindən əvvəl bu dataset üzərindən eval keçirin. Regression `>3%` olduqda deploy blokla. CI pipeline-da `php artisan ai:regression-check --threshold=0.03` kimi işləyin.

## Əlaqəli Mövzular

- [LLM Observability](./03-llm-observability.md)
- [Model Drift Monitoring](./07-model-drift-quality-monitoring.md)
- [Canary Shadow Deploy](./14-canary-shadow-llm-deploy.md)
- [Agent Evaluation Patterns](../05-agents/12-ai-agent-evaluation-patterns.md)
- [Observability Logging](./02-observability-logging.md)
