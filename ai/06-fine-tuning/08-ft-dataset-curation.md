# Fine-Tuning üçün Dataset Hazırlama və Keyfiyyət İdarəsi (Senior)

> **Kim üçündür:** Fine-tuning etmək istəyən senior developerlər. Dataset-in model keyfiyyətini necə müəyyən etdiyini başa düşmək üçün.
>
> **Əsas fikir:** Model keyfiyyəti dataset keyfiyyəti ilə məhdudlaşır. Dünya ən yaxşı training setup-u ilə pis dataset-dən yaxşı model əldə etmək mümkün deyil.

---

## 1. "Garbage In, Garbage Out" — AI-da Xüsusilə Doğrudur

SFT üçün model dataset-dəki nümunələri **imitasiya etməyi** öyrənir. Yanlış nümunə → yanlış davranış.

```
Dataset-dəki problem:       Modeldəki nəticə:
───────────────────────────────────────────────────────
Uyğunsuz format              Cavablar gah JSON, gah plain text
Ziddiyyətli nümunələr        Model "ortalaşır", hər ikisini bilmir
Tarixi köhnə data            Model köhnə metodları tövsiyə edir
Pis ton/üslub nümunələri    Cavab tonu qeyri-peşəkardır
Qısa, səthi cavablar        Model da qısa, səthi cavab verir
```

---

## 2. Dataset Növləri

### 2.1 Instruction-Response (SFT üçün)

```jsonl
{"instruction": "Bu PHP kodunu optimize et:", "input": "foreach ($users as $u) { DB::insert... }", "output": "Bulk insert istifadə edin: User::insert([...])"}
{"instruction": "Bu exception-ı izah et", "input": "SQLSTATE[23000]: Integrity constraint violation", "output": "Bu xəta primary key duplicasiyasını bildirir..."}
```

### 2.2 Multi-turn Chat (Söhbət modeli üçün)

```jsonl
{"messages": [
  {"role": "user",      "content": "Laravel queue-u necə konfiqurasiya etmək olar?"},
  {"role": "assistant", "content": "Queue driver seçimi ilə başlayın: `.env`-dəki..."},
  {"role": "user",      "content": "Redis driver seçsəm nə lazımdır?"},
  {"role": "assistant", "content": "Redis driver üçün `predis/predis` package lazımdır..."}
]}
```

### 2.3 Preference Pairs (DPO/ORPO üçün)

```jsonl
{"prompt": "N+1 problemi nədir?", "chosen": "N+1 problemi hər iteration-da DB sorğusu göndərməkdir...", "rejected": "Bu bir performans problemidir."}
```

### 2.4 Completion (Code generation üçün)

```jsonl
{"text": "<?php\n\nclass UserRepository {\n    public function findActiveUsers(): Collection\n    {"}
```

---

## 3. Data Mənbələri

### 3.1 Manuel Annotation (Ən keyfiyyətli)

İnsan mütəxəssislər cavabları yazır. Bahalı amma "gold standard".

```
Xərc: $10-100/nümunə (mütəxəssis domen biliyindən asılı)
Miqyas: Adətən 500-5000 nümunə
İstifadə: Core task-lər, edge case-lər, alignment
```

**Annotation guide example:**

```markdown
# Annotation Qaydaları

## Yaxşı cavab:
- Konkret, actionable
- Kod nümunəsi varsa işlək olmalıdır
- Azərbaycan dilindədir, texniki terminlər ingilis
- 200-500 söz (mövzudan asılı)

## Pis cavab:
- "Bu mürəkkəbdir, mütəxəssisə soruşun" — faydasız
- Copy-paste Wikipedia — səthi
- Faktiki səhv — yanlış öyrədir
```

### 3.2 Human-in-the-Loop (Yarı avtomatik)

Model cavab yaradır, insan **düzəldir** — sıfırdan yazmaqdan sürətli.

```
Pipeline:
  1. Prompt → [Model cavab yaradır]
  2. İnsan cavabı keyfiyyət üçün qiymətləndirir (1-5)
  3. Zəif cavabları yenidən yazar
  4. Keyfiyyətli cavabları dataset-ə əlavə edir
  
Xərc: ~$2-10/nümunə
Sürət: Manuel-dən 3-5x sürətli
```

### 3.3 Synthetic Data (Ən miqyaslı)

Güclü model (Claude, GPT-4o) zəif modeli train etmək üçün data yaradır.

```
Tövsiyə olunan:
  Teacher model: claude-sonnet-4-6 (keyfiyyətli data yaratır)
  Student model: Llama 3.3 70B (fine-tune olunacaq)
```

---

## 4. Laravel-də Synthetic Data Pipeline

### 4.1 Prompt Template Sistemi

```php
<?php
// app/Services/DataGeneration/DatasetGenerator.php

namespace App\Services\DataGeneration;

use App\Services\AI\ClaudeService;

class DatasetGenerator
{
    private const INSTRUCTION_TEMPLATES = [
        'code_review'   => 'Aşağıdakı PHP kodunu review et. Problemləri izah et və düzəldilmiş versiyasını ver.',
        'explain'       => 'Aşağıdakı Laravel konseptini senior PHP developer üçün izah et:',
        'debug'         => 'Bu PHP exception-ı izah et və həll yolu göstər:',
        'optimize'      => 'Bu sorğunu optimize et. Niyə original yavaş idi izah et.',
        'compare'       => 'Aşağıdakı iki yanaşmanı müqayisə et: tradeoff-ları izah et.',
    ];

    public function __construct(
        private readonly ClaudeService $claude,
    ) {}

    public function generate(string $type, string $input): array
    {
        $instruction = self::INSTRUCTION_TEMPLATES[$type] ?? throw new \InvalidArgumentException("Unknown type: $type");

        $response = $this->claude->messages(
            messages: [[
                'role'    => 'user',
                'content' => $instruction . "\n\n" . $input,
            ]],
            systemPrompt: $this->buildSystemPrompt(),
            model: 'claude-sonnet-4-6',
            maxTokens: 1500,
            temperature: 0.3,  // Aşağı temp → daha dəqiq, ardıcıl data
        );

        return [
            'instruction' => $instruction,
            'input'       => $input,
            'output'      => $response,
            'type'        => $type,
            'generated_at' => now()->toIso8601String(),
            'model'       => 'claude-sonnet-4-6',
        ];
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
        Sən senior PHP/Laravel developer üçün öyrənmə materialı hazırlayan texniki yazıçısan.

        Qaydalar:
        - Azərbaycan dilindədir, texniki terminlər ingilis dilindədir
        - Praktik, işlək kod nümunələri ver
        - "Bu mürəkkəbdir" demə — izah et
        - Anti-pattern-ləri qeyd et
        - Həmişə "niyə" sualına cavab ver
        PROMPT;
    }
}
```

### 4.2 Bulk Generation Job

```php
<?php
// app/Jobs/GenerateDatasetBatch.php

namespace App\Jobs;

use App\Services\DataGeneration\DatasetGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class GenerateDatasetBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 2;

    public function __construct(
        private readonly array  $seedInputs,  // Code snippets, concepts
        private readonly string $type,
        private readonly string $outputFile,
    ) {}

    public function handle(DatasetGenerator $generator): void
    {
        $results = [];
        $errors  = [];

        foreach ($this->seedInputs as $input) {
            try {
                $item = $generator->generate($this->type, $input);

                // Keyfiyyət yoxlaması
                if ($this->isQualityAcceptable($item)) {
                    $results[] = $item;
                }

                // Rate limiting
                usleep(200_000); // 200ms → 5 req/s

            } catch (\Exception $e) {
                $errors[] = ['input' => $input, 'error' => $e->getMessage()];
            }
        }

        // JSONL formatında saxla
        $content = collect($results)
            ->map(fn($r) => json_encode($r, JSON_UNESCAPED_UNICODE))
            ->implode("\n");

        Storage::put("datasets/{$this->outputFile}", $content);

        \Log::info("Dataset generation complete", [
            'generated' => count($results),
            'errors'    => count($errors),
            'file'      => $this->outputFile,
        ]);
    }

    private function isQualityAcceptable(array $item): bool
    {
        $output = $item['output'];
        
        // Minimum uzunluq
        if (str_word_count($output) < 50) {
            return false;
        }

        // Kod nümunəsi lazımdırsa, olmalıdır
        if (in_array($item['type'], ['code_review', 'optimize', 'debug'])) {
            if (!str_contains($output, '```')) {
                return false;
            }
        }

        // "I don't know", "I'm not sure" kimi cavabları rədd et
        $negativePatterns = ['bilmirəm', "don't know", 'not sure', 'bilmirəm'];
        foreach ($negativePatterns as $pattern) {
            if (str_contains(strtolower($output), $pattern)) {
                return false;
            }
        }

        return true;
    }
}
```

---

## 5. Keyfiyyət Filtrləri

### 5.1 Deduplication

Eyni və ya çox oxşar nümunələr modeli həmin case-ə overfit edir.

```php
<?php
// app/Services/DataGeneration/DuplicateFilter.php

namespace App\Services\DataGeneration;

class DuplicateFilter
{
    private array $seenHashes = [];

    /**
     * Exact duplicate detection: SHA256 hash
     */
    public function isExactDuplicate(string $text): bool
    {
        $hash = hash('sha256', $this->normalize($text));

        if (in_array($hash, $this->seenHashes)) {
            return true;
        }

        $this->seenHashes[] = $hash;
        return false;
    }

    /**
     * Near-duplicate detection: n-gram Jaccard similarity
     * Threshold ~0.85 → çox oxşar nümunələr atılır
     */
    public function isSimilarTo(string $text, array $corpus, float $threshold = 0.85): bool
    {
        $ngrams = $this->getNgrams($text, n: 3);

        foreach ($corpus as $existing) {
            $existingNgrams = $this->getNgrams($existing, n: 3);
            $similarity     = $this->jaccardSimilarity($ngrams, $existingNgrams);

            if ($similarity >= $threshold) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($text)));
    }

    private function getNgrams(string $text, int $n): array
    {
        $words  = explode(' ', $this->normalize($text));
        $ngrams = [];

        for ($i = 0; $i <= count($words) - $n; $i++) {
            $ngrams[] = implode(' ', array_slice($words, $i, $n));
        }

        return array_unique($ngrams);
    }

    private function jaccardSimilarity(array $a, array $b): float
    {
        $intersection = count(array_intersect($a, $b));
        $union        = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 0.0 : $intersection / $union;
    }
}
```

### 5.2 Uzunluq Filtrləri

```php
<?php
// app/Services/DataGeneration/LengthFilter.php

class LengthFilter
{
    // Task növünə görə min/max söz sayı
    private const LIMITS = [
        'explain'     => ['min' => 100, 'max' => 600],
        'code_review' => ['min' => 80,  'max' => 500],
        'debug'       => ['min' => 60,  'max' => 400],
        'optimize'    => ['min' => 100, 'max' => 500],
        'compare'     => ['min' => 150, 'max' => 700],
    ];

    public function isAcceptableLength(string $output, string $type): bool
    {
        $wordCount = str_word_count(strip_tags($output));
        $limits    = self::LIMITS[$type] ?? ['min' => 50, 'max' => 1000];

        return $wordCount >= $limits['min'] && $wordCount <= $limits['max'];
    }
}
```

### 5.3 Format Validation

```php
<?php
// app/Services/DataGeneration/FormatValidator.php

class FormatValidator
{
    public function hasValidCodeBlocks(string $output): bool
    {
        // Bütün opened kod blokları bağlanmalıdır
        preg_match_all('/```/', $output, $matches);
        return count($matches[0]) % 2 === 0;
    }

    public function hasValidAzerbaijaniText(string $output): bool
    {
        // Ən azı 30% Azərbaycan sözləri olmalıdır
        $words = str_word_count($output, 1);
        $azWords = array_filter($words, fn($w) => $this->isLikelyAzerbaijani($w));

        return count($azWords) / max(count($words), 1) >= 0.3;
    }

    private function isLikelyAzerbaijani(string $word): bool
    {
        // Azərbaycan əlifbasına xas hərflər
        $azChars = ['ə', 'ğ', 'ı', 'ö', 'ş', 'ü', 'ç', 'Ə', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü', 'Ç'];
        foreach ($azChars as $char) {
            if (str_contains($word, $char)) {
                return true;
            }
        }
        return false;
    }
}
```

---

## 6. Data Balancing

### 6.1 Task Type Balansı

```
Dataset composition (tövsiyə edilən):
  ├── Code review:   20%
  ├── Explain:       30%
  ├── Debug:         20%
  ├── Optimize:      15%
  └── Compare:       15%
```

```php
<?php
// app/Services/DataGeneration/DatasetBalancer.php

class DatasetBalancer
{
    private const TARGET_RATIOS = [
        'explain'     => 0.30,
        'debug'       => 0.20,
        'code_review' => 0.20,
        'optimize'    => 0.15,
        'compare'     => 0.15,
    ];

    public function balance(array $dataset, int $targetSize): array
    {
        $grouped = collect($dataset)->groupBy('type');
        $result  = [];

        foreach (self::TARGET_RATIOS as $type => $ratio) {
            $targetCount = (int) ($targetSize * $ratio);
            $items       = $grouped->get($type, collect())->toArray();

            if (count($items) >= $targetCount) {
                // Subsample
                shuffle($items);
                $result = array_merge($result, array_slice($items, 0, $targetCount));
            } else {
                // Bütün mövcud nümunələri istifadə et
                $result = array_merge($result, $items);
            }
        }

        shuffle($result);
        return $result;
    }
}
```

### 6.2 Difficulty Balansı

```
Çətin / edge case: 20%
Orta:              50%
Asan / giriş:      30%
```

---

## 7. Data Augmentation

### 7.1 Paraphrase Augmentation

Eyni məzmunu fərqli sözlərlə ifadə etmək:

```php
public function paraphrase(string $instruction): string
{
    return $this->claude->messages(
        messages: [[
            'role'    => 'user',
            'content' => "Bu sualı eyni mənada, amma fərqli sözlərlə yenidən yaz: {$instruction}",
        ]],
        temperature: 0.7,
        model: 'claude-haiku-4-5',
    );
}
```

### 7.2 Negative Examples

Fine-tuning üçün **yanlış** cavab nümunələri — alignment üçün lazımdır:

```php
public function generateNegativeExample(string $instruction, string $goodAnswer): string
{
    // Pis cavab: səthi, qısa, konkret deyil
    return $this->claude->messages(
        messages: [[
            'role'    => 'user',
            'content' => "Bu suala pis, səthi bir cavab yaz (DPO dataset üçün rejected sample): {$instruction}",
        ]],
        systemPrompt: "Qəsdən pis cavab yaz: qısa, səthi, nümunəsiz.",
        model: 'claude-haiku-4-5',
        temperature: 0.8,
    );
}
```

---

## 8. Dataset Versioning

```
datasets/
  v1/
    train.jsonl        # 2000 nümunə
    val.jsonl          # 200 nümunə
    test.jsonl         # 200 nümunə
    metadata.json      # versiya, tarix, filtr parametrləri
  v2/
    train.jsonl        # 5000 nümunə (v1 + yeni)
    ...
```

```json
// datasets/v2/metadata.json
{
  "version": "2.0",
  "created_at": "2026-04-28",
  "sources": {
    "manual_annotation": 500,
    "synthetic_claude_sonnet": 3500,
    "augmented": 1000
  },
  "filters_applied": {
    "min_word_count": 60,
    "max_word_count": 600,
    "dedup_threshold": 0.85,
    "quality_score_min": 3
  },
  "split": {
    "train": 4000,
    "val": 500,
    "test": 500
  },
  "model_trained": "llama-3.3-70b-lora-r16",
  "eval_scores": {
    "val_loss": 0.842,
    "task_accuracy": 0.76
  }
}
```

---

## 9. Anti-Pattern-lər

### Historical Data-nı Filtrləmədən İstifadə

```
Problem: 3 illik müştəri dəstəyi logları əldən keçirildi
         Nümunələrin 30%-i: "Baxacağıq..." (faydasız)
         15%-i: köhnə API versiyaları
         10%-i: səhv məlumat

Nəticə: Model "Baxacağıq" cavabı verir, köhnə metodlar tövsiyə edir
```

### Yalnız Sintaktik Doğrulama

```
Doğru: JSON formatındadır ✓
Yanlış: Semantik yoxlama aparılmamışdı ✗

Nümunə:
{"instruction": "...", "output": "```php\n<?php\n// bu kod işləməyəcək\n```"}
Sintaksis düzgündür, amma kod səhvdir!
```

### Test Set-in Çirklənməsi

```php
// YANLIŞ: val data ilə quality filter train etmək
$qualityThreshold = calibrateOn($validationSet); // Data leak!

// DOĞRU: Ayrı calibration set
$calibrationSet = $dataset->slice(0, 100);
$validationSet  = $dataset->slice(100, 500);
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Automated Quality Filter

500 nümunəlik raw dataset üçün `DatasetQualityFilter` sinifi yaz. Filtrləmə meyarları: (a) response uzunluğu 20-500 token, (b) LLM-as-judge score ≥ 4/5, (c) duplicate detection (cosine similarity > 0.95). Filter öncəsi vs sonrasındakı dataset ölçüsünü qeyd et.

### Tapşırıq 2: Synthetic Data Generation

Claude Haiku ilə synthetic training data yarat. Mövzu: şirkətin support bot-u. Template: domain + difficulty_level → Claude generasiya edir. 1000 nümunə yarat. LLM-judge filter tətbiq et. Real data ilə qarışdır (50/50). Synthetic data-nın model performansına təsirini ölç.

### Tapşırıq 3: Train/Val/Test Split

500 nümunəni `stratified split` ilə böl: 70% train, 15% validation, 15% test. Hər split-dəki topic distribution-ı yoxla — homojen olmalıdır. Test set-i yalnız final evaluation üçün istifadə et (heç bir training/tuning qərarında istifadə etmə). Bu prinsipə niyə ciddi əməl etmək lazımdır?

---

## Əlaqəli Mövzular

- [05-create-custom-model-finetune.md](05-create-custom-model-finetune.md) — Dataset-i istifadə edərək train etmək
- [07-rlhf-dpo-alignment.md](07-rlhf-dpo-alignment.md) — Preference data ilə alignment
- [09-vllm-model-serving.md](09-vllm-model-serving.md) — Train olunan modeli deploy etmək
