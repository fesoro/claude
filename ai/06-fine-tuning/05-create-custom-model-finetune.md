# Addım-Addım Bələdçi: Öz Modelinizi İncə Tənzimləmək (Lead)

## Məqsəd: Azərbaycanca Müştəri Dəstəyi Modeli

Bu bələdçi Llama 3.3 8B-ni Azərbaycan dilində müştəri dəstəyi sorğularını idarə etmək üçün incə tənzimləmənin tam boru kəmərini izah edir — əsas modellərdə məhdud əhatəsi olan bir dil. Eyni proses istənilən sahəyə xas incə tənzimləmə tapşırığına şamil edilir.

**Niyə Azərbaycan dili?** Əsas Llama modellərinin Azərbaycan dili keyfiyyəti zəifdir. Azərbaycanın rəsmi dili olaraq ~10 milyon danışanı olan bu dilin Common Crawl və digər ön öyrətmə məlumat dəstlərindəki təmsili minimaldır. Bu, regionda fəaliyyət göstərən şirkətlər üçün real biznes problemidir.

---

## Mərhələ 1: Məlumat Dəsti Strategiyası

### Kəmiyyətdən Çox Keyfiyyət

Ən vacib dərs: 500 mükəmməl nümunə 50,000 orta nümunədən üstündür. Hər aşağı keyfiyyətli nümunə modelə pis davranış öyrədir.

**Hər nümunə üçün keyfiyyət yoxlama siyahısı**:
- [ ] Təlimat açıqdır və real istifadəçi sorğusunu təmsil edir
- [ ] Cavab ən yaxşı işçinizin deyəcəyi şeydir
- [ ] Cavab düzgün dil/ləhcədədir
- [ ] Cavab düzgün uzunluqdadır (nə çox uzun, nə çox qısa)
- [ ] Uydurulmuş məlumat, siyasət pozuntusu və ya brendə uyğun olmayan məzmun yoxdur

### Məlumat Dəsti Mənbələri

1. **Tarixi dəstək biletləri** (keyfiyyətə görə filtrlənmiş)
2. **Claude vasitəsilə sintetik yaratma** (ən genişləndirilə bilən)
3. **Əl ilə seçim** (kənar hallar və yüksək riskli qarşılıqlı əlaqələr üçün)
4. **İngilis məlumat dəstlərinin tərcüməsi** (keyfiyyət yoxlaması ilə)

### Məlumat Formatı

ShareGPT formatından istifadə edin (ən geniş dəstəklənən):

```json
[
  {
    "conversations": [
      {
        "from": "system",
        "value": "Siz Azərbaycanlı müştərilərə kömək edən peşəkar müştəri xidməti nümayəndəsisiniz. Həmişə nəzakətli, yardımsevər və dəqiq olun."
      },
      {
        "from": "human",
        "value": "Sifarişim hara çatdı? Sifariş nömrəm AZ-12345"
      },
      {
        "from": "gpt",
        "value": "Hörmətli müştəri, AZ-12345 nömrəli sifarişinizin statusunu yoxlayıram...\n\nSifarişiniz hal-hazırda çatdırılma mərhələsindədir. Proqnoz: sabah, 14:00-18:00 arasında. Kuryer sizinlə əlaqə saxlayacaq.\n\nBaşqa sualınız varsa, kömək etməkdən məmnuniyyət duyaram! 🙏"
      }
    ]
  }
]
```

### Hədəf Məlumat Dəsti Ölçüsü

Azərbaycan müştəri dəstəyi üçün:
- **Minimum həyata keçirəbilən**: 500 yüksək keyfiyyətli nümunə
- **Tövsiyə edilən**: 2,000-5,000 nümunə
- **Mükəmməl**: müxtəlif ssenarilər ilə 10,000+ nümunə

---

## Mərhələ 2: Laravel + Claude ilə Məlumat Dəsti Yaratma

```php
<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use Anthropic\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateFinetuneDataset extends Command
{
    protected $signature = 'finetune:generate
                            {--output=storage/datasets/az-support.jsonl : Çıxış fayl yolu}
                            {--count=2000 : Hədəf nümunə sayı}
                            {--augment : Real biletləri sintetik variasiyalarla artır}';

    protected $description = 'Azərbaycan müştəri dəstəyi üçün incə tənzimləmə məlumat dəsti yarat';

    private const SYSTEM_PROMPT = 'Siz Azərbaycanlı müştərilərə kömək edən peşəkar müştəri xidməti nümayəndəsisiniz. Həmişə nəzakətli, yardımsevər və dəqiq olun. Azerbaycan türkcesinde yazın.';

    public function __construct(
        private readonly Client $claude,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $outputPath = $this->option('output');
        $targetCount = (int) $this->option('count');
        $examples = collect();

        // Addım 1: Real biletlərdən çıxarın
        $this->info('Real dəstək biletlərindən çıxarılır...');
        $realExamples = $this->extractFromRealTickets();
        $examples = $examples->merge($realExamples);
        $this->info("Çıxarılan real nümunələr: {$examples->count()}");

        // Addım 2: Sintetik nümunələr yaradın
        if ($examples->count() < $targetCount) {
            $remaining = $targetCount - $examples->count();
            $this->info("{$remaining} sintetik nümunə yaradılır...");
            $synthetic = $this->generateSynthetic($remaining);
            $examples = $examples->merge($synthetic);
        }

        // Addım 3: Tələb olunarsa variasiyalarla artırın
        if ($this->option('augment')) {
            $this->info('Artırmalar yaradılır...');
            $augmented = $this->augmentExamples($examples->take(200)->toArray());
            $examples = $examples->merge($augmented);
        }

        // Addım 4: Keyfiyyət filtrəsi
        $this->info('Keyfiyyətə görə filtrlənir...');
        $filtered = $this->qualityFilter($examples->toArray());
        $this->info("Keyfiyyət filtrindən sonra: {$filtered->count()} nümunə");

        // Addım 5: Öyrətmə/doğrulama bölgüsü və saxlama
        $shuffled = $filtered->shuffle();
        $valSize  = (int) ($shuffled->count() * 0.05);
        $train    = $shuffled->skip($valSize);
        $val      = $shuffled->take($valSize);

        $this->saveJsonl($train->toArray(), str_replace('.jsonl', '_train.jsonl', $outputPath));
        $this->saveJsonl($val->toArray(), str_replace('.jsonl', '_val.jsonl', $outputPath));

        $this->info("{$train->count()} öyrətmə və {$val->count()} doğrulama nümunəsi saxlandı");

        return self::SUCCESS;
    }

    private function extractFromRealTickets(): \Illuminate\Support\Collection
    {
        return SupportTicket::where('language', 'az')
            ->where('quality_score', '>=', 4) // Yalnız yüksək keyfiyyətli biletlər
            ->where('was_escalated', false)    // Eskalasiyaları çıxarın (pis nümunələr)
            ->limit(1000)
            ->get()
            ->map(function ($ticket) {
                return [
                    'conversations' => [
                        ['from' => 'system', 'value' => self::SYSTEM_PROMPT],
                        ['from' => 'human',  'value' => $ticket->customer_message],
                        ['from' => 'gpt',    'value' => $ticket->agent_response],
                    ],
                ];
            });
    }

    private function generateSynthetic(int $count): \Illuminate\Support\Collection
    {
        $categories = [
            'order_tracking'       => 'Sifariş izləmə sualları',
            'returns'              => 'Məhsul qaytarma prosedurları',
            'payment_issues'       => 'Ödəniş problemləri',
            'product_inquiry'      => 'Məhsul haqqında suallar',
            'account_management'   => 'Hesab idarəetməsi',
            'delivery_problems'    => 'Çatdırılma problemləri',
            'complaints'           => 'Şikayətlər',
            'technical_support'    => 'Texniki dəstək',
        ];

        $examples = collect();
        $perCategory = (int) ($count / count($categories));
        $bar = $this->output->createProgressBar($count);

        foreach ($categories as $category => $description) {
            for ($i = 0; $i < $perCategory; $i++) {
                $example = $this->generateSingleExample($category, $description);
                if ($example) {
                    $examples->push($example);
                }
                $bar->advance();
                usleep(100_000); // Sürət limiti: 10 sorğu/san
            }
        }

        $bar->finish();
        $this->newLine();

        return $examples;
    }

    private function generateSingleExample(string $category, string $description): ?array
    {
        try {
            $response = $this->claude->messages()->create([
                'model'      => 'claude-haiku-4-5', // Toplu yaratma üçün ucuz
                'max_tokens' => 1024,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => <<<PROMPT
                        Azərbaycan dilində müştəri xidməti üçün bir nümunə yazın.
                        Kateqoriya: {$description}

                        Müştəri mesajı (realistic, 1-3 cümlə) və agent cavabı (peşəkar, yardımsevər) yazın.
                        JSON formatında qaytarın:
                        {
                          "customer_message": "...",
                          "agent_response": "..."
                        }

                        Sadəcə JSON, başqa heç nə.
                        PROMPT,
                    ],
                ],
            ]);

            $json = json_decode($response->content[0]->text, true);
            if (!$json || !isset($json['customer_message'], $json['agent_response'])) {
                return null;
            }

            return [
                'conversations' => [
                    ['from' => 'system', 'value' => self::SYSTEM_PROMPT],
                    ['from' => 'human',  'value' => $json['customer_message']],
                    ['from' => 'gpt',    'value' => $json['agent_response']],
                ],
            ];
        } catch (\Throwable $e) {
            $this->warn("Nümunə yaratma uğursuz oldu: {$e->getMessage()}");
            return null;
        }
    }

    private function augmentExamples(array $examples): \Illuminate\Support\Collection
    {
        return collect($examples)->map(function ($example) {
            // Müxtəliflik üçün müştəri mesajını yenidən ifadə edin
            $original = $example['conversations'][1]['value'];

            try {
                $response = $this->claude->messages()->create([
                    'model'      => 'claude-haiku-4-5',
                    'max_tokens' => 256,
                    'messages'   => [
                        [
                            'role'    => 'user',
                            'content' => "Bu müştəri mesajını fərqli bir şəkildə yenidən yazın (eyni məna, fərqli ifadə):\n\n\"{$original}\"\n\nSadəcə yeni versiyasını yazın.",
                        ],
                    ],
                ]);

                $rephrased = $response->content[0]->text;

                return [
                    'conversations' => [
                        $example['conversations'][0],
                        ['from' => 'human', 'value' => $rephrased],
                        $example['conversations'][2],
                    ],
                ];
            } catch (\Throwable) {
                return null;
            }
        })->filter()->values();
    }

    private function qualityFilter(\Illuminate\Support\Collection $examples): \Illuminate\Support\Collection
    {
        return $examples->filter(function ($example) {
            $conversations = $example['conversations'];

            // 3 növbə olmalıdır
            if (count($conversations) !== 3) return false;

            $customerMsg = $conversations[1]['value'] ?? '';
            $agentResp   = $conversations[2]['value'] ?? '';

            // Minimum uzunluq yoxlamaları
            if (mb_strlen($customerMsg) < 10) return false;
            if (mb_strlen($agentResp) < 20) return false;

            // Maksimum uzunluq (öyrətmə zamanı kontekst daşmasının qarşısını almaq üçün)
            if (mb_strlen($customerMsg) > 2000) return false;
            if (mb_strlen($agentResp) > 3000) return false;

            // Əsas Azərbaycan hərflərinin mövcudluğunu yoxlayın
            $azChars = ['ə', 'ı', 'ö', 'ü', 'ğ', 'ç', 'ş', 'Ə', 'İ', 'Ö', 'Ü', 'Ğ', 'Ç', 'Ş'];
            $hasAzChars = collect($azChars)->contains(fn($char) => str_contains($customerMsg . $agentResp, $char));

            return $hasAzChars;
        })->values();
    }

    private function saveJsonl(array $data, string $path): void
    {
        $lines = collect($data)->map(fn($item) => json_encode($item, JSON_UNESCAPED_UNICODE))->join("\n");
        Storage::put($path, $lines);
    }
}
```

---

## Mərhələ 3: Unsloth ilə Öyrətmə

Unsloth standart HuggingFace incə tənzimləməsindən 2x sürətli və 60% az yaddaş istifadə edir.

### Mühit Qurulumu (RunPod)

```bash
# RunPod nümunəsi başladın: A100 40GB, PyTorch şablonu
# SSH ilə qoşulun və işlədin:

pip install unsloth
pip install --upgrade --no-cache-dir "unsloth[colab-new] @ git+https://github.com/unslothai/unsloth.git"

# Eksperiment izləmə üçün wandb quraşdırın
pip install wandb
wandb login
```

### Öyrətmə Skripti

```python
# train.py

from unsloth import FastLanguageModel
from datasets import load_dataset
from trl import SFTTrainer
from transformers import TrainingArguments
import torch

# ===== Konfiqurasiya =====
MODEL_NAME    = "unsloth/Meta-Llama-3.1-8B-Instruct"
MAX_SEQ_LEN   = 2048
OUTPUT_DIR    = "./az-support-model"
DATASET_TRAIN = "train_data.jsonl"
DATASET_VAL   = "val_data.jsonl"

LORA_R        = 16
LORA_ALPHA    = 32
LORA_DROPOUT  = 0.05

LEARNING_RATE = 2e-4
BATCH_SIZE    = 2
GRAD_ACCUM    = 4  # Effektiv batch = 8
NUM_EPOCHS    = 3
WARMUP_RATIO  = 0.05

# ===== Unsloth ilə Model Yükləmə =====
model, tokenizer = FastLanguageModel.from_pretrained(
    model_name = MODEL_NAME,
    max_seq_length = MAX_SEQ_LEN,
    dtype = None,           # Avtomatik aşkarlama (A100-da bfloat16)
    load_in_4bit = True,    # QLoRA
)

# LoRA tətbiq et
model = FastLanguageModel.get_peft_model(
    model,
    r = LORA_R,
    target_modules = [
        "q_proj", "k_proj", "v_proj", "o_proj",
        "gate_proj", "down_proj", "up_proj"
    ],
    lora_alpha = LORA_ALPHA,
    lora_dropout = LORA_DROPOUT,
    bias = "none",
    use_gradient_checkpointing = "unsloth",  # Yaddaş optimallaşdırması
    use_rslora = False,
)

print(model.print_trainable_parameters())
# trainable params: 41,943,040 || all params: 8,072,220,672 || trainable%: 0.5196

# ===== ChatML formatlama funksiyası =====
def format_conversations(examples):
    conversations = examples["conversations"]
    texts = []
    for conv in conversations:
        text = ""
        for turn in conv:
            role = turn["from"]
            content = turn["value"]

            if role == "system":
                text += f"<|im_start|>system\n{content}<|im_end|>\n"
            elif role == "human":
                text += f"<|im_start|>user\n{content}<|im_end|>\n"
            elif role == "gpt":
                text += f"<|im_start|>assistant\n{content}<|im_end|>\n"

        texts.append(text)
    return {"text": texts}

# ===== Məlumat dəstlərini yükləyin və formatlayin =====
train_dataset = load_dataset("json", data_files=DATASET_TRAIN, split="train")
val_dataset   = load_dataset("json", data_files=DATASET_VAL, split="train")

train_dataset = train_dataset.map(format_conversations, batched=True)
val_dataset   = val_dataset.map(format_conversations, batched=True)

print(f"Öyrətmə nümunələri: {len(train_dataset)}")
print(f"Doğrulama nümunələri: {len(val_dataset)}")

# Bir nümunəyə baxış
print("\nFormatlanmış nümunə:")
print(train_dataset[0]["text"][:500])

# ===== Öyrətmə Arqumentləri =====
training_args = TrainingArguments(
    output_dir            = OUTPUT_DIR,
    num_train_epochs      = NUM_EPOCHS,
    per_device_train_batch_size = BATCH_SIZE,
    gradient_accumulation_steps = GRAD_ACCUM,
    learning_rate         = LEARNING_RATE,
    lr_scheduler_type     = "cosine",
    warmup_ratio          = WARMUP_RATIO,
    bf16                  = True,
    evaluation_strategy   = "steps",
    eval_steps            = 100,
    save_strategy         = "steps",
    save_steps            = 100,
    save_total_limit      = 3,
    load_best_model_at_end = True,
    metric_for_best_model = "eval_loss",
    greater_is_better     = False,
    logging_steps         = 25,
    report_to             = "wandb",
    run_name              = "az-support-llama-8b",
    dataloader_num_workers= 4,
    group_by_length       = True,  # Öyrətməni sürətləndirir
)

# ===== Öyrədici =====
trainer = SFTTrainer(
    model            = model,
    tokenizer        = tokenizer,
    train_dataset    = train_dataset,
    eval_dataset     = val_dataset,
    dataset_text_field = "text",
    max_seq_length   = MAX_SEQ_LEN,
    args             = training_args,
    packing          = False,
)

# ===== Öyrədin =====
print("Öyrətmə başlayır...")
trainer.train()

# ===== Modeli saxlayın =====
model.save_pretrained(OUTPUT_DIR + "/lora-adapters")
tokenizer.save_pretrained(OUTPUT_DIR + "/lora-adapters")

# LoRA-nı əsas modelə birləşdirin və tam modeli saxlayın
model.save_pretrained_merged(
    OUTPUT_DIR + "/merged",
    tokenizer,
    save_method = "merged_16bit",
)

print("Öyrətmə tamamlandı! Model saxlandı.")
```

### Ollama üçün GGUF-a İxrac

```python
# export_gguf.py

from unsloth import FastLanguageModel

model, tokenizer = FastLanguageModel.from_pretrained(
    "./az-support-model/lora-adapters",
    max_seq_length = 2048,
    load_in_4bit = True,
)

# Q4_K_M kvantlaşdırması ilə GGUF-a ixrac edin
model.save_pretrained_gguf(
    "./az-support-model/gguf-q4",
    tokenizer,
    quantization_method = "q4_k_m"
)

# Daha yüksək keyfiyyət üçün Q8 versiyasını da ixrac edin
model.save_pretrained_gguf(
    "./az-support-model/gguf-q8",
    tokenizer,
    quantization_method = "q8_0"
)

print("GGUF faylları yaradıldı. Ollama models qovluğuna kopyalayın.")
```

---

## Mərhələ 4: Ollama-ya Yerləşdirmə

```bash
# Ollama üçün Modelfile yaradın
cat > Modelfile << 'EOF'
FROM ./az-support-model/gguf-q4/az-support-llama-8b-q4_k_m.gguf

# Model faylına daxil edilmiş sistem promptu
SYSTEM """Siz Azərbaycanlı müştərilərə kömək edən peşəkar müştəri xidməti nümayəndəsisiniz.
Həmişə nəzakətli, yardımsevər və dəqiq olun. Azerbaycan türkcesinde yazın."""

# Nəticəçıxarma parametrləri
PARAMETER temperature 0.7
PARAMETER top_p 0.9
PARAMETER top_k 40
PARAMETER num_ctx 4096
PARAMETER stop "<|im_end|>"
PARAMETER stop "<|im_start|>"

# Şablon
TEMPLATE """{{ if .System }}<|im_start|>system
{{ .System }}<|im_end|>
{{ end }}{{ if .Prompt }}<|im_start|>user
{{ .Prompt }}<|im_end|>
<|im_start|>assistant
{{ end }}{{ .Response }}<|im_end|>"""
EOF

# Ollama modelini yaradın
ollama create az-support-agent -f Modelfile

# Sınayın
ollama run az-support-agent "Sifariş nömrəm AZ-12345. Nə vaxt gəlir?"
```

---

## Mərhələ 5: Qiymətləndirmə

```php
<?php

namespace App\Console\Commands;

use App\AI\Clients\OllamaClient;
use Anthropic\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EvaluateFinetunedModel extends Command
{
    protected $signature = 'finetune:evaluate
                            {--model=az-support-agent : Ollama model adı}
                            {--test-file=datasets/az-support-test.jsonl : Test nümunələri}';

    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly Client $claude,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $testData = $this->loadTestData($this->option('test-file'));
        $modelName = $this->option('model');

        $this->info("{$modelName} modeli {$testData->count()} test halında qiymətləndirilir...");

        $scores = [];
        $bar = $this->output->createProgressBar($testData->count());

        foreach ($testData as $example) {
            $conversations = $example['conversations'];
            $systemPrompt  = $conversations[0]['value'];
            $userMessage   = $conversations[1]['value'];
            $expectedOutput = $conversations[2]['value'];

            // Model çıxışını alın
            $response = $this->ollama->chat(
                model: $modelName,
                messages: [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
            );

            $actualOutput = $response->content;

            // Claude ilə qiymətləndirin
            $score = $this->judgeResponse(
                query:    $userMessage,
                expected: $expectedOutput,
                actual:   $actualOutput,
            );

            $scores[] = $score;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $avgScore = array_sum($scores) / count($scores);
        $passRate = count(array_filter($scores, fn($s) => $s >= 0.7)) / count($scores);

        $this->table(
            ['Metrik', 'Dəyər'],
            [
                ['Orta Bal', number_format($avgScore, 3)],
                ['Keçid Nisbəti (≥0.7)', number_format($passRate * 100, 1) . '%'],
                ['Test Halları', count($scores)],
            ],
        );

        return $avgScore >= 0.75 ? self::SUCCESS : self::FAILURE;
    }

    private function judgeResponse(string $query, string $expected, string $actual): float
    {
        $response = $this->claude->messages()->create([
            'model'      => 'claude-haiku-4-5',
            'max_tokens' => 256,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => <<<PROMPT
                    Müştəri xidməti cavabını qiymətləndirin (0.0-1.0):

                    Müştəri sualı: {$query}
                    İdeal cavab: {$expected}
                    Model cavabı: {$actual}

                    Qiymətləndirmə meyarları:
                    - Dil keyfiyyəti (Azərbaycan dili)
                    - Problemi həll etmə
                    - Nəzakət
                    - Dəqiqlik

                    Sadəcə 0.0-1.0 arasında rəqəm yazın.
                    PROMPT,
                ],
            ],
        ]);

        return (float) trim($response->content[0]->text);
    }

    private function loadTestData(string $path): \Illuminate\Support\Collection
    {
        $content = Storage::get($path);
        return collect(explode("\n", trim($content)))
            ->filter()
            ->map(fn($line) => json_decode($line, true));
    }
}
```

---

## LoRA Konfiqurasiya Bələdçisi

### `r` (Rank) Seçimi

```
r = 4:   Minimal dəyişiklik — format tənzimləmələri, kiçik üslub düzəlişləri
r = 8:   Yüngül uyğunlaşma — ton, terminologiya
r = 16:  Standart — sahə uyğunlaşması üçün tövsiyə edilən standart
r = 32:  Güclü — əhəmiyyətli davranış dəyişikliyi
r = 64+: Demək olar tam incə tənzimləmə — böyük imkan əlavələri üçün istifadə edin
```

### Hədəf Modullar

Ən yaxşı nəticə üçün bütün proyeksiya qatlarını hədəf alın:

```python
target_modules = [
    "q_proj",    # Sorğu proyeksiyaları (diqqət)
    "k_proj",    # Açar proyeksiyaları
    "v_proj",    # Dəyər proyeksiyaları
    "o_proj",    # Çıxış proyeksiyaları
    "gate_proj", # FFN qapısı
    "down_proj", # FFN aşağı proyeksiya
    "up_proj",   # FFN yuxarı proyeksiya
]
```

Daha çox modul = daha çox ifadəlilik = daha çox yaddaş. Hamısı ilə başlayın, yaddaş məhdudiyəti varsa azaldın.

### Öyrənmə Sürəti Cədvəli

```
Çox yüksək (> 5e-4):  İtki sıçrayışları, qeyri-sabit öyrətmə, potensial ayrılma
Yaxşı aralıq:         1e-4 to 3e-4
Çox aşağı (< 5e-5):   Yavaş konvergensiya, tapşırıq nümunənizi öyrənməyə bilər
```

İstiləşmə ilə kosinüs cədvəlindən istifadə edin:
```
İstiləşmə: ümumi addımların 5%-i (erkən qeyri-sabitliyin qarşısını alır)
Zirvə:     2e-4
Son:       zirvənin 10%-i (~2e-5)
```

---

## Bu Nümunə üçün Xərc Xülasəsi

```
Məlumat dəsti yaratma:
  - Claude Haiku vasitəsilə 2,000 sintetik nümunə
  - Nümunə başına ~200 token giriş + 200 token çıxış
  - Xərc: 2,000 × 400 token / 1M × $4.00 = $3.20

Öyrətmə işi:
  - 2,000 nümunə × 3 dövr = 6,000 nümunə
  - Hər biri ~800 token = 4.8M token
  - RunPod-da A100 40GB: $1.50/saat
  - Öyrətmə müddəti: ~45 dəqiqə
  - Xərc: 0.75saat × $1.50 = $1.13

GGUF ixracı:
  - Eyni RunPod sessiyasının bir hissəsi
  - Əlavə 15 dəqiqə

Ümumi xərc: İstehsala hazır Azərbaycan dəstəyi modeli üçün ~$5
```

Ayda 100K sorğu ilə Claude Haiku-ya nisbətən təxmini aylıq nəticəçıxarma qənaəti: ~$270.
