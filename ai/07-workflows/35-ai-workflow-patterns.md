# 35 — AI Workflow və Zəncir Pattern-ləri

> **Mənbə:** Anthropic-in rəsmi agent sənədləri və tədqiqatları  
> **Oxucu:** Baş tərtibatçılar və arxitektlər  
> **Məqsəd:** AI işini birləşdirmək üçün beş kanonik pattern-i mənimsəmək və onları Laravel-də həyata keçirmək

---

## 1. Niyə AI Sistemlərində Pattern-lər Vacibdir

Tək LLM çağırışları sadə tapşırıqları yaxşı idarə edir. Lakin real dünya AI tətbiqləri **birləşdirə bilmə** tələb edir: mürəkkəb işi addımlara bölmək, paralel işlər aparmaq, mütəxəssislərə yönləndirmək və keyfiyyət üçün təkrar etmək.

Anthropic-in tədqiqatları real dünya AI workflow ehtiyaclarının ~95%-ni əhatə edən beş əsas pattern müəyyən edir. Hər birinin fərqli mübadilələri var:

| Pattern             | Gecikmə | Xərc | Keyfiyyət | Mürəkkəblik |
|---------------------|---------|------|-----------|-------------|
| Prompt Chaining     | Yüksək  | Aşağı| Orta      | Aşağı       |
| Parallelization     | Aşağı   | Yüksək| Yüksək   | Orta        |
| Routing             | Aşağı   | Aşağı| Yüksək   | Orta        |
| Orchestrator-Workers| Orta    | Yüksək| Ən yüksək| Yüksək      |
| Evaluator-Optimizer | Ən yüksək| Yüksək| Ən yüksək| Yüksək     |

Pattern seçərkən əsas sual: **darboğaz nədir?**
- Keyfiyyət → Evaluator-Optimizer və ya Orchestrator-Workers
- Gecikmə → Parallelization
- Xərc → Routing
- Sadəlik → Prompt Chaining

---

## 2. Pattern 1: Prompt Chaining

### Nəzəriyyə

Bir LLM çağırışının çıxışı növbətisinin girişinə çevrilir. Əsas anlayış odur ki, **hər addım müstəqil olaraq optimallaşdırıla bilər** — çıxarış üçün daha sadə, ucuz model, sintez üçün daha güclü model.

```
Giriş → [Addım 1: Çıxar] → [Addım 2: Çevir] → [Addım 3: Formatla] → Çıxış
```

**Nə vaxt istifadə edilir:**
- Tapşırıq təbii olaraq ardıcıldır (pipeline-ları düşünün)
- Hər addımın ayrı, yoxlanıla bilən məqsədi var
- Hər addımda fərqli model/temperature tətbiq etmək istəyirsiniz
- Aralıq çıxışların insan yoxlama nöqtələri lazımdır

**Nə vaxt istifadə edilmir:**
- Addımlar müstəqildir (əvəzinə Parallelization istifadə edin)
- Zəncirdə 5–6-dan çox addım var (Orchestrator-Workers istifadə edin)
- Gecikmə kritikdir

**Xəta yayılması** əsas riskdir: 1-ci addımdakı xətalar bütün sonrakı addımları pozur. Həmişə aralıq çıxışları yoxlayın.

```php
<?php
// app/AI/Patterns/PromptChain.php

namespace App\AI\Patterns;

use App\Services\AI\ClaudeService;
use Closure;

class PromptChain
{
    private array $steps = [];
    private array $checkpoints = [];

    public function __construct(
        private readonly ClaudeService $claude,
    ) {}

    /**
     * Zəncirə emal addımı əlavə et.
     *
     * @param string  $name     Loqlama/sazlama üçün açıqlayıcı ad
     * @param Closure $handler  fn(mixed $input, array $context): mixed
     * @param bool    $validate Davam etməzdən əvvəl çıxışı yoxlamaq lazımdırmı
     */
    public function step(string $name, Closure $handler, bool $validate = false): static
    {
        $this->steps[] = compact('name', 'handler', 'validate');
        return $this;
    }

    /**
     * Yoxlama nöqtəsi əlavə et — insan və ya avtomatik yoxlama nöqtəsi.
     * Zəncir dayanır və yoxlama nöqtəsinin təmizlənməsini gözləyir.
     */
    public function checkpoint(string $afterStep, Closure $reviewer): static
    {
        $this->checkpoints[$afterStep] = $reviewer;
        return $this;
    }

    public function run(mixed $input, array $context = []): ChainResult
    {
        $current = $input;
        $trace   = [];

        foreach ($this->steps as $step) {
            $start = microtime(true);

            try {
                $current = ($step['handler'])($current, $context);
            } catch (\Throwable $e) {
                return ChainResult::failure($step['name'], $e, $trace);
            }

            $trace[] = [
                'step'    => $step['name'],
                'latency' => round((microtime(true) - $start) * 1000),
                'output'  => is_string($current) ? substr($current, 0, 100) . '...' : '[qeyri-sətir]',
            ];

            // Tələb olunarsa aralıq çıxışı yoxla
            if ($step['validate'] && ! $this->isValidOutput($current)) {
                return ChainResult::failure($step['name'], new \RuntimeException("Addımdan etibarsız çıxış: {$step['name']}"), $trace);
            }

            // Yoxlama nöqtəsini yoxla
            if (isset($this->checkpoints[$step['name']])) {
                $approved = ($this->checkpoints[$step['name']])($current, $context);
                if (! $approved) {
                    return ChainResult::stopped($step['name'], $current, $trace);
                }
            }
        }

        return ChainResult::success($current, $trace);
    }

    private function isValidOutput(mixed $output): bool
    {
        if (is_null($output))    return false;
        if (is_string($output))  return strlen(trim($output)) > 0;
        if (is_array($output))   return count($output) > 0;
        return true;
    }
}

// İstifadə: Sənəd emal zənciri
$chain = (new PromptChain($claude))
    ->step('faktları-çıxar', fn($doc) => $claude->complete(
        prompt: "Bu sənəddən əsas faktları JSON olaraq çıxarın: {$doc}",
        model: 'claude-haiku-4-5',  // çıxarış üçün ucuz model
    ))
    ->step('faktları-yoxla', fn($facts) => $claude->complete(
        prompt: "Bu faktların ağlabatan və ardıcıl olduğunu yoxlayın: {$facts}",
        model: 'claude-haiku-4-5',
    ), validate: true)
    ->step('hesabat-yarat', fn($facts) => $claude->complete(
        prompt: "Bu yoxlanılmış faktlara əsasən icra xülasəsi yazın: {$facts}",
        model: 'claude-sonnet-4-5',  // son sintez üçün güclü model
    ));

$result = $chain->run($documentContent);
```

---

## 3. Pattern 2: Parallelization

### Nəzəriyyə

Bir neçə LLM çağırışı eyni vaxtda işləyir və çıxışları birləşdirilir. İki alt pattern:

**Fan-out/Fan-in:** Eyni giriş, çoxlu müstəqil prosessorlar, nəticələri birləşdir.
**Bölmə:** Böyük giriş hissələrə bölünür, paralel emal edilir, yenidən yığılır.

```
         ┌→ [İşçi 1] ─┐
Giriş ───┼→ [İşçi 2] ─┼→ Birləşdirici → Çıxış
         └→ [İşçi 3] ─┘
```

**Nə vaxt istifadə edilir:**
- Tapşırıqlar müstəqil alt tapşırıqlara bölünə bilər
- Çoxlu "rəy" və ya "səs" istəyirsiniz (özünü-ardıcıllıq)
- Böyük sənəd kontekst pəncərəsini aşır
- Gecikmə SLA tələbləriniz var

**Mübadilə:** Xərc işçilər sayına mütənasib artır. Həmişə soruşun: "Paralel keyfiyyət qazancı xərci doğruldurmu?"

**Birləşdirmə strategiyaları:**
- **Çoxluq səsi** — klassifikasiya tapşırıqları üçün
- **Ən yaxşısı-N** — ən yaxşı çıxışı seçmək üçün qiymətləndiricini işlət
- **Birləşdir** — tamamlayıcı perspektivləri birləşdir
- **Azalt** — bütün çıxışları birə xülasə et

```php
<?php
// app/AI/Patterns/Parallelizer.php

namespace App\AI\Patterns;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class Parallelizer
{
    /**
     * Fan-out: eyni girişi N işçiyə göndər, nəticələri birləşdir.
     *
     * Həqiqi eyni vaxtlı HTTP sorğuları üçün Laravel-in Http::pool() istifadə edir.
     * CPU-yüklü tapşırıqlar üçün paralel növbələr istifadə edin.
     */
    public function fanOut(
        mixed   $input,
        array   $workers,   // [string $name => Closure $handler]
        Closure $aggregator,
    ): mixed {
        $results = [];
        $errors  = [];

        // Bütün işçiləri eyni vaxtda icra et fiber/async istifadə edərək
        $promises = [];
        foreach ($workers as $name => $handler) {
            $promises[$name] = $handler;
        }

        // Paralel işlət — CPU-ağır üçün ProcessPool, I/O üçün Http pool
        $responses = $this->runConcurrently($input, $promises);

        foreach ($responses as $name => $result) {
            if ($result instanceof \Throwable) {
                $errors[$name] = $result->getMessage();
            } else {
                $results[$name] = $result;
            }
        }

        return $aggregator($results, $errors, $input);
    }

    /**
     * Bölmə: böyük girişi hissələrə böl, paralel emal et, birləşdir.
     */
    public function section(
        string  $largeInput,
        int     $chunkSize,
        Closure $processor,
        Closure $merger,
    ): mixed {
        // Sərhədlərdə konteksti qorumaq üçün örtüşən hissələrə böl
        $chunks = $this->splitWithOverlap($largeInput, $chunkSize, overlap: 200);

        $results = [];
        foreach ($chunks as $i => $chunk) {
            $results[$i] = $processor($chunk, $i, count($chunks));
        }

        return $merger($results);
    }

    /**
     * Çoxluq səsi — klassifikasiya/fakt yoxlaması üçün faydalıdır.
     * Eyni promptu N dəfə işlət, ən çox rastlanan cavabı qaytar.
     */
    public function majorityVote(
        Closure $processor,
        mixed   $input,
        int     $n = 5,
    ): mixed {
        $votes = [];

        // N dəfə işlət — özünü-ardıcıllıq üçün
        for ($i = 0; $i < $n; $i++) {
            $votes[] = $processor($input);
        }

        // Say və ən çox rastlananı qaytar
        $counts = array_count_values(array_map('strval', $votes));
        arsort($counts);

        return [
            'answer'     => array_key_first($counts),
            'confidence' => max($counts) / $n,
            'votes'      => $votes,
        ];
    }

    private function runConcurrently(mixed $input, array $workers): array
    {
        // Tək proses daxilində yüngül eyni vaxtlılıq üçün Laravel Fiber-lərindən istifadə et
        $fibers  = [];
        $results = [];

        foreach ($workers as $name => $worker) {
            $fiber = new \Fiber(fn() => $worker($input));
            $fibers[$name] = $fiber;
            $fiber->start();
        }

        // Nəticələri topla
        foreach ($fibers as $name => $fiber) {
            try {
                while (! $fiber->isTerminated()) {
                    $fiber->resume();
                }
                $results[$name] = $fiber->getReturn();
            } catch (\Throwable $e) {
                $results[$name] = $e;
            }
        }

        return $results;
    }

    private function splitWithOverlap(string $text, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $start  = 0;
        $length = strlen($text);

        while ($start < $length) {
            $end = min($start + $chunkSize, $length);

            // Söz sərhədinə uyğunlaş
            if ($end < $length) {
                $spacePos = strrpos(substr($text, $start, $end - $start), ' ');
                if ($spacePos !== false) {
                    $end = $start + $spacePos;
                }
            }

            $chunks[] = substr($text, $start, $end - $start);
            $start    = $end - $overlap; // kontekst davamlılığı üçün örtüşmə
        }

        return $chunks;
    }
}
```

---

## 4. Pattern 3: Routing

### Nəzəriyyə

Yüngül "klassifikator" LLM çağırışı hansı ixtisaslaşmış işleyicinin sorğunu emal etməli olduğunu müəyyən edir. Bu, hər işleyicinin öz xüsusi sahəsi üçün optimallaşdırılmasına imkan verir.

```
Giriş → [Klassifikator] → yönləndirmə qərarı
                        ├→ [İşleyici A: Texniki]
                        ├→ [İşleyici B: Ödəniş]
                        └→ [İşleyici C: Ümumi]
```

**Nə vaxt istifadə edilir:**
- Fərqli giriş növləri əsaslı olaraq fərqli promptlar tələb edir
- Fərqli modellər istifadə etmək istəyirsiniz (sadə üçün ucuz, mürəkkəb üçün bahalı)
- Sahə mütəxəssisləriniz var (ixtisaslaşmış incə tənzimlənmiş modellər və ya sistem promptları)

**Klassifikator sürətli və ucuz olmalıdır** — yönləndirmə xərci ixtisaslaşmadan gələn keyfiyyət artımından az olmalıdır.

```php
<?php
// app/AI/Patterns/Router.php

namespace App\AI\Patterns;

use App\Services\AI\ClaudeService;

class Router
{
    private array $routes = [];
    private ?string $defaultRoute = null;

    public function __construct(
        private readonly ClaudeService $claude,
    ) {}

    /**
     * Təsviri (klassifikator tərəfindən istifadə edilir) və işleyici ilə marşrut qeydiyyat et.
     */
    public function route(string $name, string $description, Closure $handler): static
    {
        $this->routes[$name] = compact('description', 'handler');
        return $this;
    }

    public function fallback(string $routeName): static
    {
        $this->defaultRoute = $routeName;
        return $this;
    }

    public function dispatch(string $input, array $context = []): mixed
    {
        $routeName = $this->classify($input);

        if (! isset($this->routes[$routeName])) {
            $routeName = $this->defaultRoute
                ?? throw new \RuntimeException("Marşrut tapılmadı və fallback qurulmayıb");
        }

        $handler = $this->routes[$routeName]['handler'];
        return $handler($input, $context);
    }

    private function classify(string $input): string
    {
        $descriptions = collect($this->routes)
            ->map(fn($r, $name) => "- {$name}: {$r['description']}")
            ->implode("\n");

        $response = $this->claude->complete(
            model: 'claude-haiku-4-5',  // Yönləndirmə üçün həmişə ən ucuz modeli istifadə et
            prompt: <<<PROMPT
            Aşağıdakı istifadəçi girişini dəqiq bir kateqoriyaya təsnif edin.
            Yalnız kateqoriya adı ilə cavab verin, başqa heç nə əlavə etməyin.

            Kateqoriyalar:
            {$descriptions}

            Giriş: {$input}

            Kateqoriya:
            PROMPT,
            maxTokens: 20,  // Çox qısa cavab — yalnız kateqoriya adı
        );

        return strtolower(trim($response));
    }
}

// İstifadə
$router = (new Router($claude))
    ->route('texniki', 'Kod, API, infrastruktur haqqında texniki suallar', function ($input) use ($claude) {
        return $claude->complete(
            model: 'claude-sonnet-4-5',
            system: 'Siz baş proqram arxitektisisiniz. Dəqiq və texniki olun.',
            prompt: $input,
        );
    })
    ->route('odenis', 'Ödənişlər, qaimə-fakturalar, abunəliklər haqqında suallar', function ($input) use ($claude) {
        return $claude->complete(
            model: 'claude-haiku-4-5',
            system: 'Siz köməkçi ödəniş dəstək agentsisiniz. Qısa və mehriban olun.',
            prompt: $input,
        );
    })
    ->route('umumi', 'Ümumi suallar və hər şey', function ($input) use ($claude) {
        return $claude->complete(model: 'claude-haiku-4-5', prompt: $input);
    })
    ->fallback('umumi');
```

---

## 5. Pattern 4: Orchestrator-Workers

### Nəzəriyyə

Orchestrator LLM hansı tapşırıqları icra edəcəyini və hansı sırada dinamik olaraq qərara alır. İşçilər fərdi tapşırıqları icra edən ixtisaslaşmış alt-agentlərdir. Orchestrator-un agentliyi var — aralıq nəticələrə əsasən planı uyğunlaşdıra bilər.

```
[Orchestrator] ←──── nəticələri müşahidə edir
       │
       ├─→ [İşçi A: Axtarış]   ──→ nəticə
       ├─→ [İşçi B: Hesabla]   ──→ nəticə  
       └─→ [İşçi C: Yaz]       ──→ nəticə
```

**Bu, müasir AI agentlərinin əksəriyyəti tərəfindən istifadə edilən pattern-dir.**

Zəncirdən əsas fərq: orchestrator **dinamik olaraq** sonra nə edəcəyini qərara alır. Zəncir əvvəlcədən müəyyən edilmir.

**Nə vaxt istifadə edilir:**
- Planlaşdırma və uyğunlaşma tələb edən mürəkkəb tapşırıqlar
- Alt tapşırıqların tam dəsti əvvəlcədən bilinmir
- Alt tapşırıqların icra zamanı kəşf edilən asılılıqları ola bilər
- Muxtar agent lazımdır

```php
<?php
// app/AI/Patterns/OrchestratorWorkers.php

namespace App\AI\Patterns;

use App\Services\AI\ClaudeService;

class OrchestratorWorkers
{
    private array $workers = [];

    public function __construct(
        private readonly ClaudeService $claude,
        private readonly int $maxIterations = 10,
    ) {}

    public function worker(string $name, string $description, Closure $executor): static
    {
        $this->workers[$name] = compact('description', 'executor');
        return $this;
    }

    public function run(string $goal, array $context = []): OrchestratorResult
    {
        $history    = [];
        $completed  = false;
        $iterations = 0;

        while (! $completed && $iterations < $this->maxIterations) {
            $iterations++;

            // Orchestrator-dan sonra nə etmək lazım olduğunu soruşun
            $decision = $this->orchestrate($goal, $history, $context);

            if ($decision['action'] === 'complete') {
                $completed = true;
                break;
            }

            if ($decision['action'] === 'delegate') {
                $workerName = $decision['worker'];
                $workerInput = $decision['input'];

                if (! isset($this->workers[$workerName])) {
                    $history[] = ['worker' => $workerName, 'error' => 'İşçi tapılmadı'];
                    continue;
                }

                try {
                    $result = ($this->workers[$workerName]['executor'])($workerInput, $context);
                    $history[] = ['worker' => $workerName, 'input' => $workerInput, 'result' => $result];
                } catch (\Throwable $e) {
                    $history[] = ['worker' => $workerName, 'input' => $workerInput, 'error' => $e->getMessage()];
                }
            }
        }

        return new OrchestratorResult($goal, $history, $completed, $iterations);
    }

    private function orchestrate(string $goal, array $history, array $context): array
    {
        $workerList = collect($this->workers)
            ->map(fn($w, $name) => "- {$name}: {$w['description']}")
            ->implode("\n");

        $historyText = empty($history)
            ? 'Hələ yoxdur.'
            : collect($history)
                ->map(fn($h) => "İşçi: {$h['worker']}\nNəticə: " . ($h['result'] ?? $h['error'] ?? 'bilinmir'))
                ->implode("\n\n");

        $response = $this->claude->complete(
            model: 'claude-sonnet-4-5',
            prompt: <<<PROMPT
            Siz bir orchestrator-sunuz. Məqsədiniz: {$goal}

            Mövcud işçilər:
            {$workerList}

            İş tarixi:
            {$historyText}

            Növbəti əməliyyatı qərara alın. Aşağıdakılardan biri olaraq JSON ilə cavab verin:
            {"action": "delegate", "worker": "<ad>", "input": "<işçidən nə istəmək lazımdır>", "reasoning": "<niyə>"}
            {"action": "complete", "final_answer": "<sintez edilmiş nəticə>"}

            Qərarlı olun. Kifayət qədər məlumatınız varsa, tamamlayın.
            PROMPT,
        );

        return json_decode($response, true) ?? ['action' => 'complete', 'final_answer' => $response];
    }
}
```

---

## 6. Pattern 5: Evaluator-Optimizer Dövrü

### Nəzəriyyə

Generator çıxış yaradır, evaluator onu qiymətləndirir və nəticə yaxşılaşdırma üçün generatora geri ötürülür. Bu keyfiyyət həddine çatılana və ya maksimum iterasiyaya çatılana qədər davam edir.

```
[Generator] ──→ [Evaluator] ──→ bal < hədd
     ↑                                │
     └──────── [Optimizer] ←──────────┘
                                      │
                               bal ≥ hədd
                                      │
                                    Çıxış
```

**Evaluator ola bilər:**
- Başqa LLM çağırışı (hakim kimi LLM)
- Proqramatik yoxlama (vahid testlər, linter-lər)
- İnsan yoxlayıcı
- Yuxarıdakıların ensemble-ı

**Nə vaxt istifadə edilir:**
- Çıxış keyfiyyəti kritikdir və əlavə xərci haqlı çıxarır
- Aydın keyfiyyət meyarları müəyyən edilə bilər (rubrik)
- Kod generasiyası, yazı, tərcümə, mühakimə kimi tapşırıqlar

```php
<?php
// app/AI/Patterns/EvaluatorOptimizer.php

namespace App\AI\Patterns;

use App\Services\AI\ClaudeService;

class EvaluatorOptimizer
{
    public function __construct(
        private readonly ClaudeService $claude,
        private readonly float $qualityThreshold = 8.0,
        private readonly int $maxIterations = 3,
    ) {}

    /**
     * @param Closure $generator fn(string $task, ?string $feedback): string
     * @param Closure $evaluator fn(string $output, string $task): EvalResult
     */
    public function optimize(
        string  $task,
        Closure $generator,
        Closure $evaluator,
    ): OptimizationResult {
        $history   = [];
        $best      = null;
        $bestScore = 0;

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $feedback = ! empty($history) ? $this->buildFeedback($history) : null;

            // Yarat (və ya əks əlaqə ilə yenidən yarat)
            $output = $generator($task, $feedback);

            // Qiymətləndir
            $eval = $evaluator($output, $task);

            $history[] = [
                'iteration' => $i + 1,
                'output'    => $output,
                'score'     => $eval->score,
                'feedback'  => $eval->feedback,
            ];

            if ($eval->score > $bestScore) {
                $bestScore = $eval->score;
                $best = $output;
            }

            if ($eval->score >= $this->qualityThreshold) {
                break; // Keyfiyyət həddine çatıldı
            }
        }

        return new OptimizationResult(
            output: $best,
            score: $bestScore,
            iterations: count($history),
            history: $history,
            converged: $bestScore >= $this->qualityThreshold,
        );
    }

    /**
     * Qiymətləndirmə rubriği istifadə edərək daxili LLM evaluator.
     */
    public function llmEvaluator(string $rubric): Closure
    {
        return function (string $output, string $task) use ($rubric): EvalResult {
            $response = $this->claude->complete(
                model: 'claude-sonnet-4-5',
                prompt: <<<PROMPT
                Aşağıdakı çıxışı tapşırıq və rubrikə qarşı qiymətləndirin.

                Tapşırıq: {$task}
                Rubrik: {$rubric}
                Çıxış: {$output}

                JSON ilə cavab verin:
                {
                  "score": <0-10 onluq ədəd>,
                  "strengths": ["..."],
                  "weaknesses": ["..."],
                  "specific_improvements": ["..."]
                }
                PROMPT,
            );

            $data = json_decode($response, true) ?? [];

            return new EvalResult(
                score: (float) ($data['score'] ?? 0),
                feedback: implode('; ', $data['specific_improvements'] ?? []),
                raw: $data,
            );
        };
    }

    private function buildFeedback(array $history): string
    {
        $last = end($history);
        return "Əvvəlki cəhd {$last['score']}/10 bal aldı. Əks əlaqə: {$last['feedback']}. Bu xüsusi məqamları yaxşılaşdırın.";
    }
}
```

---

## 7. WorkflowBuilder — Axıcı API

`WorkflowBuilder` pattern-ləri birləşdirmək üçün vahid, axıcı interfeys təqdim edir:

```php
<?php
// app/AI/WorkflowBuilder.php

namespace App\AI;

use App\AI\Patterns\{PromptChain, Parallelizer, Router, OrchestratorWorkers, EvaluatorOptimizer};
use App\Services\AI\ClaudeService;

class WorkflowBuilder
{
    private array $stages = [];

    public function __construct(
        private readonly ClaudeService $claude,
    ) {}

    public static function make(ClaudeService $claude): static
    {
        return new static($claude);
    }

    public function chain(Closure $configure): static
    {
        $chain = new PromptChain($this->claude);
        $configure($chain);
        $this->stages[] = ['type' => 'chain', 'pattern' => $chain];
        return $this;
    }

    public function parallel(array $workers, Closure $aggregator): static
    {
        $parallelizer = new Parallelizer();
        $this->stages[] = [
            'type'       => 'parallel',
            'workers'    => $workers,
            'aggregator' => $aggregator,
            'pattern'    => $parallelizer,
        ];
        return $this;
    }

    public function route(Closure $configure): static
    {
        $router = new Router($this->claude);
        $configure($router);
        $this->stages[] = ['type' => 'route', 'pattern' => $router];
        return $this;
    }

    public function optimize(Closure $generator, string $rubric, float $threshold = 8.0): static
    {
        $optimizer = new EvaluatorOptimizer($this->claude, $threshold);
        $this->stages[] = [
            'type'      => 'optimize',
            'generator' => $generator,
            'evaluator' => $optimizer->llmEvaluator($rubric),
            'pattern'   => $optimizer,
        ];
        return $this;
    }

    public function run(mixed $input): WorkflowResult
    {
        $current = $input;
        $trace   = [];

        foreach ($this->stages as $stage) {
            $start = microtime(true);

            $current = match ($stage['type']) {
                'chain'    => $stage['pattern']->run($current)->output,
                'parallel' => $stage['pattern']->fanOut($current, $stage['workers'], $stage['aggregator']),
                'route'    => $stage['pattern']->dispatch($current),
                'optimize' => $stage['pattern']->optimize($current, $stage['generator'], $stage['evaluator'])->output,
            };

            $trace[] = [
                'stage'   => $stage['type'],
                'latency' => round((microtime(true) - $start) * 1000),
            ];
        }

        return new WorkflowResult($current, $trace);
    }
}

// Nümunə: Mürəkkəb sənəd emal workflow-u
$result = WorkflowBuilder::make($claude)
    ->route(fn(Router $r) => $r
        ->route('texniki', 'Texniki sənədlər', fn($d) => preprocess($d, 'technical'))
        ->route('huquqi', 'Hüquqi sənədlər', fn($d) => preprocess($d, 'legal'))
        ->fallback('umumi')
    )
    ->chain(fn(PromptChain $c) => $c
        ->step('çıxar', fn($d) => $claude->complete("Əsas iddialar çıxarın: {$d}", model: 'claude-haiku-4-5'))
        ->step('yoxla', fn($claims) => $claude->complete("Ardıcıllığı yoxlayın: {$claims}"))
    )
    ->optimize(
        generator: fn($task, $feedback) => $claude->complete($feedback ? "{$task}\n\nƏks əlaqəyə uyğun düzəliş: {$feedback}" : $task),
        rubric: 'Dəqiqlik (40%), Aydınlıq (30%), Tamlıq (30%)',
        threshold: 8.5,
    )
    ->run($documentContent);
```

---

## 8. Pattern Seçim Bələdçisi

```
Tapşırıq ardıcıldırmı?
├── Bəli → Prompt Chaining
└── Xeyr → Paralelləşdirilə bilərmi?
    ├── Bəli → Parallelization
    └── Xeyr → Giriş tipi heterojendir?
        ├── Bəli → Routing
        └── Xeyr → Tapşırıq adaptiv planlaşdırma tələb edirmi?
            ├── Bəli → Orchestrator-Workers
            └── Xeyr → Keyfiyyət əsas narahatlıqdırmı?
                ├── Bəli → Evaluator-Optimizer
                └── Xeyr → Tək LLM çağırışı (pattern lazım olmaya bilər)
```

> **Başlanğıc nöqtəsi:** Işə yarayacaq ən sadə pattern ilə başlayın. Mürəkkəbliyi yalnız daha mürəkkəb pattern-in lazım olduğuna dair sübutunuz olduqda (metriklər, keyfiyyət benchmarkları) artırın.
