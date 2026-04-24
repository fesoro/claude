# LoRA, QLoRA və PEFT: Parameter-Efficient Fine-Tuning Dərin Bələdçi

> **Kim üçündür**: Laravel/PHP arxa plan üzərində işləyən senior mühəndislər ki, komandaları açıq mənbə modeli incə tənzimləmək istəyir. 29-cu fayl (ümumi icmal) və 32-ci fayl (addım-addım bələdçi) LoRA-nın nə olduğunu yüzəyi şəkildə toxunur. Bu fayl həmin yuxarıdan baxışa girməyən riyaziyyat, hiperparametr seçimi, real GPU büdcələri, DoRA/ReFT kimi yeni variantlar və vLLM ilə multi-adapter serving haqqındadır. Burada Python kodu var — bu qovluq Python-a icazə verilən yeganə yerdir, çünki training Python-dadır.

## Məzmun

1. Full Fine-Tuning Niyə Baha Başa Gəlir
2. LoRA-nın Arxasındakı İntuisiya: Low-Rank Hipotezi
3. Riyaziyyat: W + BA Dekompozisiyası
4. Hiperparametrlər: rank, alpha, dropout, target_modules
5. QLoRA: 4-bit Kvantizasiya + LoRA Adapterlər
6. PEFT Kitabxanası Praktikası
7. Unsloth ilə Real Training Resepti
8. Multi-Adapter Serving (vLLM, LoRAX)
9. DoRA və ReFT: LoRA-nın Növbəti Nəsli
10. Adapter Birləşdirmə (Merge) vs Ayrıca Saxlama
11. LoRA Adapterini Necə Qiymətləndirmək
12. LoRA Nə Zaman Kifayət Etmir
13. Bulud GPU Xərc Riyaziyyatı
14. Rollback, Versioning və Production Notlar
15. Xülasə Cədvəli

---

## 1. Full Fine-Tuning Niyə Baha Başa Gəlir

70B parametrli Llama 3.3 modelini tam incə tənzimləmək lazım olduğunu təsəvvür edin. "Tam" o deməkdir ki, 70 milyard çəkinin hər birinə gradient hesablayıb backprop ilə onu yeniləyirsiniz. Komandanıza praktiki baxımdan bunun nə demək olduğuna baxaq.

### Yaddaş Büdcəsi (Tam FT)

70B modelin bfloat16-da yalnız çəkilərini saxlamaq üçün:

```
70,000,000,000 parametr × 2 byte (bf16) = 140 GB
```

Bu, yalnız model çəkiləridir. Training zamanı GPU-da daha nə olmalıdır?

```
┌─────────────────────────────────────────────────┐
│  Training Step-ində GPU-da Yaddaş Sərfiyyatı   │
├─────────────────────────────────────────────────┤
│  Model çəkiləri (bf16):              140 GB     │
│  Gradientlər (bf16):                 140 GB     │
│  AdamW optimizer state (m, v fp32):  560 GB     │
│     (hər parametr üçün 2 × 4 byte)             │
│  Aktivasiyalar (seq_len=2048, bs=1):  ~50 GB    │
│  Gradient checkpointing reduction:   -40 GB     │
│─────────────────────────────────────────────────│
│  Cəmi yaddaş tələbi:                ~850 GB    │
└─────────────────────────────────────────────────┘
```

Bu o deməkdir ki, 80 GB A100-lərdən ~11 ədəd lazımdır, minimum. Praktikada 8×A100 80 GB node-u FSDP + DeepSpeed ZeRO-3 ilə istifadə edirsiniz ki, state-i node-lar arasında paylaşasınız. Hər şey mükəmməl getsə, tam 70B fine-tune çalıştırması $20,000–$80,000 aralığındadır, məlumat ölçüsündən asılı olaraq.

Və ən böyük acı həqiqət: komandanız artıq bu trainingi iki dəfə etməlidir — birincisi hiperparametr axtarışı üçün, ikincisi isə "əsl" iş üçün. İterasiyanın qiyməti real qiymətdir.

### Yaddaş Tələbi Necə Böyüyür

```
Parametr sayı   | bf16 çəkilər | Adam state | Toplam (~) | GPU tələbi
────────────────┼──────────────┼────────────┼────────────┼───────────────
7B              | 14 GB        | 56 GB      | 90 GB      | 2× A100 80GB
13B             | 26 GB        | 104 GB     | 160 GB     | 4× A100 80GB
34B             | 68 GB        | 272 GB     | 420 GB     | 6-8× A100 80GB
70B             | 140 GB       | 560 GB     | 850 GB     | 8-11× A100 80GB
405B (Llama 4)  | 810 GB       | 3240 GB    | ~5 TB      | Multi-node H100
```

Aydın olur ki, əksər komandalar üçün tam fine-tuning 34B-dən sonra qeyri-real olur. 70B-ni fine-tune etmək üçün milli dövlət səviyyəsində büdcə lazımdır. Budur PEFT-in varlıq səbəbi.

---

## 2. LoRA-nın Arxasındakı İntuisiya: Low-Rank Hipotezi

2021-ci ildə Hu və başqaları (Microsoft) bir müşahidə etdi: fine-tuning zamanı çəki matrislərinə edilən dəyişikliklər (ΔW) **low-rank**-dır. Yəni, ΔW-nin effektiv rank-ı ilkin çəki matrisinin rank-ından çox-çox aşağıdır.

Daha sadə dillə: fine-tuning modelə "tamam yenidən düşünməyi" deyil, **mövcud təsvir sahəsində sürtünmə dəyişikliyi** etməyi öyrədir. Model artıq dili bilir; sən ona yeni bir "mayil olma" öyrədirsən. Bu mayillik çox-az istiqamətdə baş verir.

### Vizual İntuisiya

```
Orijinal çəki matrisi W ∈ R^(d×d):

    ┌─────────────────────────┐
    │                         │
    │   d × d = d² parametr   │
    │   (məs., 4096 × 4096    │
    │    = 16.7M parametr)    │
    │                         │
    └─────────────────────────┘

Fine-tuning zamanı real dəyişiklik ΔW:

    ┌─────────────────────────┐
    │ . . . . . . . . . . . . │
    │ . . . ★ . . . . . . . . │  ★ = əhəmiyyətli dəyişiklik
    │ . . . . . . ★ . . . . . │  . = demək olar ki, sıfır
    │ . . . . . . . . . . . . │
    │ . . ★ . . . . . . . . . │
    │ . . . . . . . . . ★ . . │
    └─────────────────────────┘

    ΔW yalnız bir neçə "istiqamətdə" qeyri-sıfırdır.
    Onu iki daha kiçik matrisin hasili kimi ifadə edə bilərsiniz:

    ΔW  ≈   B  ×  A
    (d×d)  (d×r) (r×d)

    burada r << d (məs., r = 16, d = 4096)
```

Bu sadə müşahidə inqilabi nəticələr verir. Əgər `r = 16`, onda:

```
Full ΔW parametr sayı:        4096 × 4096 = 16,777,216
LoRA (B × A) parametr sayı:   4096 × 16 + 16 × 4096 = 131,072

Azalma: 128× aşağı
```

128 dəfə daha az parametr, demək olar ki, eyni nəticə. Əsl çəkilər (W) training zamanı **tamamilə donmuş qalır** — onlara heç toxunmursan.

---

## 3. Riyaziyyat: W + BA Dekompozisiyası

LoRA-nın riyazi forması sadədir:

```
Orijinal forward pass:
    h = W · x

LoRA ilə modifikasiya edilmiş forward pass:
    h = W · x + (α/r) · B · A · x
        ↑                ↑
        donmuş           yalnız B və A öyrənilir
```

Burada:
- `W ∈ R^(d×d)` — əsas model çəkiləri, **training zamanı donmuş**
- `A ∈ R^(r×d)` — "aşağı düşürən" matris, Gaussian ilə initialize edilir
- `B ∈ R^(d×r)` — "yuxarı qaldıran" matris, **sıfırlarla initialize edilir** (kritik!)
- `r` — rank, adətən 8-64 aralığında
- `α` — scaling faktor, adətən `α = r` və ya `α = 2r`

### Niyə B Sıfırla Initialize Edilir?

Əgər həm A, həm də B random initialize edilsəydi, training-in ilk addımında LoRA adapter-i modelin output-una random küy əlavə edəcəkdi — bu, əsas modelin behaviors-unu pozar. B-ni sıfırla initialize etməklə, ilkin ΔW = B·A = 0·A = 0 olur, yəni training başlayanda model **tam dəqiqliklə əsas modellə identikdir**. Adapter yalnız training gedişatında tədricən təsir göstərməyə başlayır.

### Scaling Faktoru α/r Niyə Lazımdır?

α/r əmsalı LoRA-nın təsirini miqyaslamağa imkan verir. Əgər ranq-ı dəyişirsənsə (məs., r=8-dən r=32-ə keçirsənsə), normal olaraq öyrənmə sürətini (learning rate) də uyğunlaşdırmalı olardın. α/r bu kompensasiyanı avtomatik həyata keçirir. Praktikada:

```
Konvensional seçimlər:
    α = r        → effektiv scale = 1 (ən çox istifadə olunan)
    α = 2r       → effektiv scale = 2 (LoRA paper-in orijinal tövsiyəsi)
    α = r/2      → effektiv scale = 0.5 (çox konservativ)
```

---

## 4. Hiperparametrlər: rank, alpha, dropout, target_modules

### Rank (r) Seçimi

`r` rank-ı ən vacib hiperparametrdir. Nə az, nə çox — düzgün.

```
r = 4:    Çox minimal. Yalnız style/tone dəyişiklikləri.
          Trainable params ~0.05% base. 
          Domain shift kiçikdirsə yaxşıdır.

r = 8:    Çoxu istifadə halı üçün yaxşı başlanğıc.
          Instruction following fine-tuning standartı.
          Trainable params ~0.1%.

r = 16:   Sahə uyğunlaşması üçün balanslı seçim.
          Müştəri dəstəyi, dövlət domain-i, hüquqi dil.
          Trainable params ~0.2%.

r = 32:   Əhəmiyyətli behavior dəyişikliyi tələb olunduqda.
          Coding, SQL generation, structured output.
          Trainable params ~0.4%.

r = 64:   Böyük task-specific pivot.
          Reasoning, math, yeni bacarıq.
          Trainable params ~0.8%.

r = 128+: Yaxınlaşırsan tam fine-tuning-ə. Əgər buna
          ehtiyacın varsa, yəqin ki QLoRA r=128 əvəzinə
          real FT haqqında düşünməlisən.
```

**Səhv**: "Nə qədər yüksək, o qədər yaxşı." Ranq-ı qaldırdıqca overfitting riski artır, xüsusən kiçik məlumat dəstlərində. 1000 nümunə ilə r=128 istifadə etmək adətən daha pis nəticə verir.

### Target Modules

Transformer-də hansı xətti qatlara LoRA tətbiq ediləcəyini seçirsən. Standart seçimlər:

```python
# Minimal (qısa: yalnız attention)
target_modules = ["q_proj", "v_proj"]

# Ənənəvi (attention-ın hər hissəsi)
target_modules = ["q_proj", "k_proj", "v_proj", "o_proj"]

# Bütün xətti qatlar (MLP daxil)
target_modules = [
    "q_proj", "k_proj", "v_proj", "o_proj",  # attention
    "gate_proj", "up_proj", "down_proj",      # MLP (FFN)
]

# Və ya HF PEFT-in avtomatik həll edən variantı:
target_modules = "all-linear"
```

Tədqiqatlar göstərib ki, "all-linear" (bütün linear layers) strategy adətən attention-only-dan ~2-3% daha yaxşı nəticə verir, çünki MLP qatları "bilik saxlanma"nın çoxunu edir. Yaddaş qiyməti nisbətən kiçikdir.

### Dropout

LoRA-nın daxilində dropout regularization aparır. Tipik dəyər:

```
lora_dropout = 0.05  # ənənəvi, əksər hallarda
lora_dropout = 0.10  # kiçik məlumat dəsti, overfit riski varsa
lora_dropout = 0.00  # böyük məlumat dəsti (>100k nümunə)
```

### Xülasə: Standart Recipe

Əksər senior mühəndislər üçün 80% hallarda işləyən bir konfiqurasiya:

```python
peft_config = LoraConfig(
    r=16,
    lora_alpha=32,
    lora_dropout=0.05,
    target_modules="all-linear",
    bias="none",
    task_type="CAUSAL_LM",
)
```

---

## 5. QLoRA: 4-bit Kvantizasiya + LoRA Adapterlər

LoRA həllin bir hissəsidir, lakin hələ də əsas model çəkilərini yaddaşda saxlamalısınız. 70B × 2 byte = 140 GB. Bu, tək 24 GB GPU-da hələ də qeyri-realdır.

QLoRA (Dettmers və başq., 2023) bu problemi həll edir: **əsas model çəkilərini 4-bit formatda saxla**, LoRA adapterlərini isə 16-bit-də öyrət.

### QLoRA-nın Üç Dayağı

1. **4-bit NormalFloat (NF4) kvantizasiya** — adi INT4-dən fərqli olaraq, NF4 neural network çəkilərinin tipik Gaussian paylanmasına optimallaşdırılmış format-dır. Sıfıra yaxın çəkilər üçün daha çox "information capacity" ayrılır.

2. **Double Quantization** — kvantizasiya sabitlərinin özləri də kvantlaşdırılır, əlavə yaddaş qənaəti verir (~0.5 bit per parameter).

3. **Paged Optimizers** — NVIDIA unified memory istifadə edərək optimizer state-i CPU RAM-a "swap" edir, GPU OOM zamanı avtomatik olaraq.

### Yaddaş Nəticəsi

```
7B modelin yaddaş tələbi:
                        bf16       4-bit (QLoRA)
───────────────────────────────────────────────
Çəkilər                 14 GB      3.5 GB
Gradientlər (LoRA)      0.02 GB    0.02 GB
Adam state (LoRA)       0.08 GB    0.08 GB
Aktivasiyalar           ~8 GB      ~8 GB
Cəmi                    ~22 GB     ~12 GB
───────────────────────────────────────────────
GPU tələbi              A100 40    RTX 3090 24GB
```

70B QLoRA artıq 2×A100 80GB-də sığır (əslində 1×A100 80GB+gradient checkpointing ilə belə). Bu, başqa bir dünyadır — məsələ artıq "hansı data center-i icarəyə götürək" yox, "RunPod-dan pod spin up et"-dir.

### Performans Qiyməti

4-bit kvantizasiya ilə performans nə qədər düşür? Tədqiqatlar göstərir:

```
MMLU benchmark-ında (5-shot) itki:
bf16 full FT:         72.5
bf16 LoRA r=16:       72.1  (-0.4)
QLoRA NF4 r=16:       71.8  (-0.7)
QLoRA INT4 r=16:      70.4  (-2.1)
```

Fərq ölçülə bilən, lakin real istifadə halları üçün əhəmiyyətsizdir. NF4 və double quantization ilə 4× yaddaş qənaəti müqabilində ~1% performans itkisi verirsən.

---

## 6. PEFT Kitabxanası Praktikası

Hugging Face PEFT kitabxanası ekosistem standartıdır. Mütərsiz şəkildə `transformers` ilə inteqrasiya edir.

```python
from transformers import AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig
from peft import LoraConfig, get_peft_model, prepare_model_for_kbit_training
import torch

# Addım 1: 4-bit kvantlaşdırma konfiqurasiyası
bnb_config = BitsAndBytesConfig(
    load_in_4bit=True,
    bnb_4bit_quant_type="nf4",
    bnb_4bit_compute_dtype=torch.bfloat16,
    bnb_4bit_use_double_quant=True,
)

# Addım 2: Modeli 4-bit-də yüklə
model_id = "meta-llama/Llama-3.1-8B-Instruct"
model = AutoModelForCausalLM.from_pretrained(
    model_id,
    quantization_config=bnb_config,
    device_map="auto",
    attn_implementation="flash_attention_2",
)
tokenizer = AutoTokenizer.from_pretrained(model_id)
tokenizer.pad_token = tokenizer.eos_token

# Addım 3: QLoRA üçün modeli hazırla
model = prepare_model_for_kbit_training(
    model,
    use_gradient_checkpointing=True,
)

# Addım 4: LoRA konfiqurasiyası
lora_config = LoraConfig(
    r=16,
    lora_alpha=32,
    target_modules=[
        "q_proj", "k_proj", "v_proj", "o_proj",
        "gate_proj", "up_proj", "down_proj",
    ],
    lora_dropout=0.05,
    bias="none",
    task_type="CAUSAL_LM",
)

# Addım 5: LoRA-nı modelə əlavə et
model = get_peft_model(model, lora_config)
model.print_trainable_parameters()
# trainable params: 20,971,520 || all params: 8,051,228,672 || trainable: 0.26%
```

Bax: 8 milyard parametrdən yalnız 21 milyonu (0.26%) trainable-dir. Bu, tək bir GPU-da sığan, bir neçə saat ərzində training-i tamamlayan bir konfiqurasiyadır.

### Training Loop (TRL SFTTrainer ilə)

```python
from trl import SFTTrainer, SFTConfig
from datasets import load_dataset

# Məlumat dəsti (ShareGPT və ya Alpaca format)
dataset = load_dataset("json", data_files="data/train.jsonl", split="train")
eval_ds = load_dataset("json", data_files="data/eval.jsonl", split="train")

training_args = SFTConfig(
    output_dir="./checkpoints/az-support-lora",
    num_train_epochs=3,
    per_device_train_batch_size=2,
    gradient_accumulation_steps=8,   # effektiv batch = 16
    learning_rate=2e-4,
    lr_scheduler_type="cosine",
    warmup_ratio=0.03,
    logging_steps=10,
    save_strategy="epoch",
    eval_strategy="steps",
    eval_steps=100,
    bf16=True,
    optim="paged_adamw_8bit",  # QLoRA üçün paged optimizer
    gradient_checkpointing=True,
    max_seq_length=2048,
    packing=True,              # training speedup
    neftune_noise_alpha=5,     # adaptive noise, daha yaxşı generalization
    report_to=["wandb"],
    run_name="az-support-llama3-8b-qlora-r16",
)

trainer = SFTTrainer(
    model=model,
    tokenizer=tokenizer,
    train_dataset=dataset,
    eval_dataset=eval_ds,
    args=training_args,
)

trainer.train()

# LoRA adapterini saxla (yalnız adapter — əsas model deyil!)
trainer.save_model("./checkpoints/az-support-lora/final")
```

Qeyd edin: `save_model` yalnız LoRA adapter çəkilərini və konfiqurasiyasını saxlayır — adətən 40-200 MB. Əsas model çəkiləri yaddaşda saxlanmır. Bu, versiyalaşdırma baxımından böyük üstünlükdür.

---

## 7. Unsloth ilə Real Training Resepti

Unsloth, LoRA/QLoRA training-ini 2× sürətləndirən, yaddaş istifadəsini 40% azaldan bir kitabxanadır. Custom Triton kernel-ləri istifadə edir. 2025-ci ildə bir çox komanda ilkin trainingi Unsloth ilə etdir.

```python
from unsloth import FastLanguageModel
from unsloth.chat_templates import get_chat_template
from trl import SFTTrainer, SFTConfig
from datasets import load_dataset

# Unsloth avtomatik NF4 + bf16 compute dtype seçir
model, tokenizer = FastLanguageModel.from_pretrained(
    model_name="unsloth/Meta-Llama-3.1-8B-Instruct-bnb-4bit",
    max_seq_length=4096,
    dtype=None,  # auto-detect
    load_in_4bit=True,
)

# LoRA adapter əlavə et
model = FastLanguageModel.get_peft_model(
    model,
    r=16,
    target_modules=[
        "q_proj", "k_proj", "v_proj", "o_proj",
        "gate_proj", "up_proj", "down_proj",
    ],
    lora_alpha=32,
    lora_dropout=0,           # Unsloth-da dropout=0 daha sürətli
    bias="none",
    use_gradient_checkpointing="unsloth",  # optimized variant
    random_state=3407,
    use_rslora=False,         # rank-stabilized LoRA — daha sonra bax
    loftq_config=None,
)

# Chat template tətbiq et
tokenizer = get_chat_template(
    tokenizer,
    chat_template="llama-3.1",
)

def format_example(examples):
    convos = examples["conversations"]
    texts = [
        tokenizer.apply_chat_template(convo, tokenize=False, add_generation_prompt=False)
        for convo in convos
    ]
    return {"text": texts}

dataset = load_dataset("json", data_files="data/az_support.jsonl", split="train")
dataset = dataset.map(format_example, batched=True)

trainer = SFTTrainer(
    model=model,
    tokenizer=tokenizer,
    train_dataset=dataset,
    dataset_text_field="text",
    max_seq_length=4096,
    dataset_num_proc=4,
    packing=True,
    args=SFTConfig(
        per_device_train_batch_size=2,
        gradient_accumulation_steps=8,
        warmup_steps=50,
        num_train_epochs=3,
        learning_rate=2e-4,
        bf16=True,
        logging_steps=10,
        optim="adamw_8bit",
        weight_decay=0.01,
        lr_scheduler_type="linear",
        seed=3407,
        output_dir="outputs",
        save_steps=500,
    ),
)

stats = trainer.train()
print(f"Training time: {stats.metrics['train_runtime']:.1f}s")
print(f"Peak GPU memory: {torch.cuda.max_memory_reserved() / 1e9:.1f} GB")

# Save (adapter-only, ~50 MB)
model.save_pretrained("az-support-adapter")
tokenizer.save_pretrained("az-support-adapter")

# İstəyə görə: merged model (inference üçün)
model.save_pretrained_merged(
    "az-support-merged-16bit",
    tokenizer,
    save_method="merged_16bit",
)
```

### Nəyə Diqqət Etməli

- **`packing=True`**: bir neçə qısa nümunəni bir sequence-ə "paketləyir". Training sürətini 2-5× artırır, xüsusən qısa instruction-response cütləri üçün.
- **`gradient_checkpointing="unsloth"`**: Unsloth-un custom implementasiyası, standart HF variantından 20% daha sürətli.
- **`use_rslora=False`**: rank-stabilized LoRA üçün yeni feature, xüsusən r>=64-də faydalıdır. Sonrakı bölmədə.

---

## 8. Multi-Adapter Serving (vLLM, LoRAX)

LoRA-nın production-da ən güclü xüsusiyyətlərindən biri: **bir əsas model üzərində paralel olaraq onlarla fərqli adapter xidmət göstərə bilərsiniz**. Hər müştəri, hər domain, hər A/B test variantı üçün ayrıca adapter.

### Mimari

```
         Client A request (az-support adapter)
                  │
                  ▼
┌─────────────────────────────────────────────────────┐
│   vLLM Server (Llama 3.1 8B base, loaded once)     │
│                                                     │
│   ┌──────────┐  ┌──────────┐  ┌──────────┐        │
│   │ Adapter  │  │ Adapter  │  │ Adapter  │        │
│   │az-support│  │en-sales  │  │ru-hr     │        │
│   │  (50MB)  │  │  (48MB)  │  │  (52MB)  │        │
│   └──────────┘  └──────────┘  └──────────┘        │
│                                                     │
│   Batched inference: müxtəlif request-lər müxtəlif │
│   adapter-dən istifadə edərək eyni anda emal edilir│
└─────────────────────────────────────────────────────┘
                  │
                  ▼
         Client B request (en-sales adapter)
```

### vLLM ilə Multi-LoRA

```bash
# Server başlat
vllm serve meta-llama/Llama-3.1-8B-Instruct \
  --enable-lora \
  --max-loras 8 \
  --max-lora-rank 32 \
  --lora-modules \
    az-support=/models/adapters/az-support \
    en-sales=/models/adapters/en-sales \
    ru-hr=/models/adapters/ru-hr
```

Request zamanı hansı adapter-i istifadə ediləcəyini göstər:

```python
import openai

client = openai.OpenAI(
    base_url="http://localhost:8000/v1",
    api_key="EMPTY",
)

response = client.chat.completions.create(
    model="az-support",  # adapter adı
    messages=[
        {"role": "user", "content": "Sifarişim hara çatdı?"},
    ],
)
```

### İqtisadiyyat

Adi halda, 10 fərqli fine-tuned model üçün 10 ayrıca GPU server lazımdır. Multi-LoRA ilə:

```
Ənənəvi yanaşma (10 model):
  10 × (1× A100 80GB) = $20/saat (RunPod)
  
Multi-LoRA yanaşma:
  1 × (1× A100 80GB) = $2/saat
  Hər adapter: +0 GPU, +~50MB RAM

İllik qənaət (24/7): $157,680
```

### LoRAX (Predibase)

LoRAX, multi-LoRA serving üçün ixtisaslaşmış açıq mənbə server-dir. vLLM-dən daha yüksək rank dəstəkləyir və adapter-lər arasında daha sürətli keçid edir. Yüzlərlə adapter-lə işləyən komandalar üçün üstündür.

---

## 9. DoRA və ReFT: LoRA-nın Növbəti Nəsli

### DoRA (Weight-Decomposed LoRA)

2024-cü ildə NVIDIA tərəfindən təqdim edilmiş DoRA, LoRA-nın bir tərpənməzliyini düzəldir: LoRA yalnız çəkilərin **istiqamətini** öyrənir, böyüklüyünü deyil. Full fine-tuning həm istiqaməti, həm də böyüklüyü dəyişir.

```
LoRA:     W + ΔW = W + BA
          (istiqamət və böyüklük hər ikisi B·A-dan gəlir)

DoRA:     W + ΔW-ni iki hissəyə böl:
          W = m · (V / ||V||)
          burada m = böyüklük, V = istiqamət
          
          Fine-tune:
          - m-i ayrıca öyrən (kiçik vektor)
          - V-ni LoRA stil ilə öyrən (B·A)
```

Nəticədə DoRA daha sabit training verir və aşağı rank-larda LoRA-dan ~1-2% daha yaxşı nəticə göstərir. PEFT kitabxanasında aktiv etmək asandır:

```python
lora_config = LoraConfig(
    r=16,
    lora_alpha=32,
    target_modules="all-linear",
    use_dora=True,  # ← tək dəyişiklik
    task_type="CAUSAL_LM",
)
```

Qiymət: training ~20% yavaşdır, inference eyni. Kiçik məlumat dəstləri və aşağı rank-lar üçün dəyərdir.

### ReFT (Representation Fine-Tuning)

2024-cü ildə Stanford-dan ReFT, çəkiləri ümumiyyətlə dəyişdirmir — əvəzinə **aktivasiyaları** (hidden states) dəyişdirir. Forward pass-in müəyyən qatlarında aktivasiya vektorunu kiçik bir şəbəkə ilə fırladır.

```
Adi forward pass:
  h = layer(x)

ReFT forward pass:
  h = layer(x)
  h = h + ReFT_intervention(h)   # kiçik low-rank intervention
```

Parametr sayı LoRA-dan 15-65× aşağı. Hələ tam production-ready deyil, amma tədqiqatda perspektivlidir. Ağır edge cases üçün yadda saxla.

### rsLoRA (Rank-Stabilized LoRA)

Standart LoRA scaling factor `α/r`-dir. Rank böyüdükcə (r=64, 128) bu scaling training-i destabilizasiya edə bilər. rsLoRA əvəzinə `α/√r` istifadə edir:

```python
peft_config = LoraConfig(
    r=128,
    lora_alpha=32,
    use_rslora=True,  # α/√r scaling tətbiq edilir
    ...
)
```

Yüksək rank istifadə edirsənsə mütləq yandır.

---

## 10. Adapter Birləşdirmə (Merge) vs Ayrıca Saxlama

Training bitdikdən sonra iki variant var:

### Variant A: Merge Et

LoRA adapter-i əsas model çəkilərinə birləşdir. Nəticə tək bir model fayl dəstidir.

```python
from peft import PeftModel

base_model = AutoModelForCausalLM.from_pretrained(
    "meta-llama/Llama-3.1-8B-Instruct",
    torch_dtype=torch.bfloat16,
)
peft_model = PeftModel.from_pretrained(base_model, "az-support-adapter")

# Merge və ixrac
merged_model = peft_model.merge_and_unload()
merged_model.save_pretrained("az-support-merged")
```

Üstünlüklər:
- Inference overhead sıfır (əlavə matrix multiplication yoxdur)
- Ollama, vLLM, llama.cpp-də adi model kimi yüklənir
- GGUF-a çevrilə bilir

Çatışmazlıqlar:
- Hər merge = tam model faylı (~15 GB 8B üçün, ~140 GB 70B üçün)
- Multi-LoRA serving mümkün deyil
- Versioning ağırdır

### Variant B: Ayrıca Saxla

Adapter əsas modeldən kənar saxlanır.

Üstünlüklər:
- Adapter fayl ~50 MB (model fayl ~15 GB-a qarşı)
- Multi-adapter serving
- A/B test üçün asan rollback
- Versioning rahatdır (git LFS-də saxla)

Çatışmazlıqlar:
- Inference-də kiçik overhead (~3-5% latency artımı)
- Deployment mürəkkəbliyi artır
- Base model əlçatan olmalıdır

### Tövsiyə

```
Production single-model istifadə:     Merge
Production multi-tenant:              Ayrıca (LoRAX/vLLM)
A/B testing:                          Ayrıca
Edge deployment (llama.cpp):          Merge → GGUF
İterasiyalı development:              Ayrıca
```

---

## 11. LoRA Adapterini Necə Qiymətləndirmək

Training loss aşağı düşürsə — bu, model yaxşı işləyir demək deyil. LoRA adapterlərini qiymətləndirmək üçün çoxölçülü yanaşma lazımdır.

### Perplexity Kifayət Deyil

Perplexity (PPL) — modelin eval məlumat dəstini nə qədər yaxşı "gözləmədiyini" ölçür. Ənənəvi metrikdir, amma:

- Daha aşağı PPL avtomatik olaraq daha yaxşı istifadəçi təcrübəsi demək deyil
- Qısalanmış cavablar PPL-i pisləşdirə bilər, amma istifadəçi üçün yaxşı ola bilər
- PPL format keyfiyyətini ölçmür

### Task-Specific Evaluation

```python
# Qiymətləndirmə dəsti (HELD-OUT, training-də görülməyib)
eval_cases = load_dataset("json", data_files="eval/az_support_eval.jsonl")

# Metrikalar kolleksiyası
results = {
    "format_compliance": 0,   # brand guidelines-a uyğunluq
    "language_correctness": 0, # Azərbaycan dili qrammatikası
    "policy_violations": 0,    # qadağan edilmiş məzmun
    "helpful_rate": 0,         # həqiqətən kömək edirmi?
    "hallucination_rate": 0,   # uydurma məlumat verirmi?
}

for case in eval_cases:
    response = model_generate(case["prompt"])
    
    # LLM-as-judge qiymətləndirmə (Claude Opus)
    judgment = claude_judge(
        prompt=case["prompt"],
        response=response,
        criteria=case["criteria"],
    )
    
    for metric in results:
        results[metric] += judgment[metric]

for metric in results:
    results[metric] /= len(eval_cases)

print(results)
```

### A/B Production Test

Live traffic-in bir hissəsinə (məs., 5%) fine-tuned modeli yolla, qalanına base modeli. Bir həftə sonra:

- CSAT (Customer Satisfaction Score) müqayisə et
- Human escalation rate-i müqayisə et
- Mean Time To Resolution (MTTR) müqayisə et

Fine-tune-un real dəyəri bu real metriklərdə görünür — validation loss-da deyil.

---

## 12. LoRA Nə Zaman Kifayət Etmir

LoRA hər şeyin həlli deyil. Aşağıdakı hallarda tam fine-tuning və ya fərqli yanaşma lazımdır:

### Domain Shift Çox Böyükdür

Əsas model ingilis dilidir, sən onu yapon tibbi sahəsinə tətbiq etmək istəyirsən. LoRA bu qədər böyük shift-i yaxşı tuta bilməz. 3-5 mərhələdən ibarət qatı strategiya daha yaxşı işləyir:

```
1. Continued Pretraining (full FT) — yapon təbii dilində
2. Domain Adaptation (LoRA) — tibbi korpus üzərində
3. Instruction Tuning (LoRA) — təlimat-cavab formatında
4. DPO/ORPO (LoRA) — insan üstünlüklərinə uyğunlaşma
```

### Yeni Bacarıq Lazımdır

Model heç vaxt SQL yazmayıb və sən ona 2000 SQL nümunə ilə LoRA öyrətməyə çalışırsan. LoRA bu tip "sıfırdan" bacarıq əlavə etməyə pisdir — yüksək rank (r=128+) lazım olacaq, amma hələ də tam FT-dən pis olacaq.

### Çox-Çox Faktual Yaddaş Lazımdır

"Bu müştərinin sifariş tarixçəsi" — bu LoRA deyil, RAG-dır. LoRA fakt yaddasında pisdir.

### Təhlükəsizlik-Kritik Tətbiqlər

Tibbi, hüquqi və ya maliyyə sferasında LoRA adapter-lər base model-in səhv behaviors-unu tam üst-üstə düşmür — onlar tamamilə silə bilmir. Full FT daha güclü kontrol verir.

---

## 13. Bulud GPU Xərc Riyaziyyatı

### Real Training Run Xərc Nümunələri

**Ssenari 1**: 7B model, 5000 nümunə, r=16, 3 epoch

```
GPU: RTX 4090 24GB (RunPod spot) — $0.45/saat
Training müddəti: ~1.5 saat
Xərc: $0.68

Iterasiya (4 dəfə): $2.72
Final training: $0.68
─────────────────────────
Cəmi: ~$3.40
```

**Ssenari 2**: Llama 3.1 8B, 20,000 nümunə, r=16, 3 epoch

```
GPU: A100 80GB (RunPod) — $1.89/saat
Training müddəti: ~4 saat
Xərc: $7.56

Iterasiya (5 dəfə): $37.80
Final training: $7.56
─────────────────────────
Cəmi: ~$45.36
```

**Ssenari 3**: Llama 3.3 70B, 10,000 nümunə, r=32, 2 epoch, QLoRA

```
GPU: 2× A100 80GB (RunPod) — $3.78/saat
Training müddəti: ~8 saat
Xərc: $30.24

Iterasiya (3 dəfə): $90.72
Final training: $30.24
─────────────────────────
Cəmi: ~$120
```

**Ssenari 4**: Llama 3.3 70B TAM fine-tuning (LoRA yox)

```
GPU: 8× H100 80GB (Lambda) — $24/saat
Training müddəti: ~48 saat
Xərc: $1,152

Iterasiya (minimum 2): $2,304
─────────────────────────
Cəmi: ~$2,500-$5,000
```

QLoRA vs tam FT fərqi: ~30× ucuz. Bax buna görə əksər komandalar QLoRA-dan başlayır.

### Provider Müqayisəsi

| Provider | GPU | $/saat | Qeyd |
|---|---|---|---|
| RunPod Secure Cloud | A100 80GB | $1.89 | Yaxşı başlanğıc |
| RunPod Community | A100 80GB | $1.19 | Spot, amma stabil |
| Lambda Labs | A100 80GB | $1.29 | On-demand |
| Modal | A100 80GB | $1.99 | Serverless, saniyələrlə bilirlənir |
| Together AI | A100 80GB | $1.76 | Managed training API |
| AWS (p4d.24xl) | 8× A100 40GB | $32.77 | Spot rezervəsiz qiymət |
| Azure (ND96asr) | 8× A100 80GB | $27.20 | Qurum komandası üçün |

Qeyd: Modal-ın $1.99/saat qiyməti yüksək görünür, amma saniyəlik bilirlənmə və tam idle sıfır xərc o deməkdir ki, qısa iterasiya cycle-ları ucuz başa gəlir.

---

## 14. Rollback, Versioning və Production Notlar

Adapter-ləri git LFS və ya S3-də artefakt kimi versiyalaşdır.

```
models/
├── llama3-8b-base/                    (read-only snapshot)
├── adapters/
│   ├── az-support-v1.0/               (prod)
│   │   ├── adapter_config.json
│   │   ├── adapter_model.safetensors
│   │   └── README.md  (training run info)
│   ├── az-support-v1.1/               (canary, 5% traffic)
│   └── az-support-v2.0-exp/           (internal test only)
```

### Training Run Metadata

Hər adapter üçün README-də bu məlumatları saxla:

```markdown
# az-support-v1.1

- **Base model**: meta-llama/Llama-3.1-8B-Instruct (sha: abc123...)
- **Training data**: s3://datasets/az-support-v1.1/train.jsonl
  - 5,204 examples
  - Data card: ./DATA_CARD.md
- **Hyperparameters**:
  - rank: 16, alpha: 32
  - epochs: 3
  - lr: 2e-4
  - seed: 3407
- **Eval results**:
  - CSAT (LLM judge): 4.2/5
  - Policy violations: 0.3%
  - Hallucination rate: 1.1%
- **Training run**: wandb.ai/company/az-support/runs/xyz
- **Trained by**: data-team
- **Date**: 2026-04-20
- **Approved for prod by**: @senior-eng
```

### Rollback Prosesi

Problem aşkarlanırsa:

```bash
# 1-ci addım: canary traffic-i 0%-ə endir
kubectl patch service az-support \
  --type=json \
  -p='[{"op": "replace", "path": "/spec/weights/v1.1", "value": 0}]'

# 2-ci addım: adapter-i pod-dan sil
kubectl exec vllm-pod -- rm -rf /models/adapters/az-support-v1.1

# 3-cü addım: audit logunda qeyd et
cat >> /audit/rollbacks.log <<EOF
$(date): rolled back az-support v1.1 → v1.0
Reason: hallucination rate spike >5%
Responsible: @on-call-eng
EOF
```

### Inference-də Monitoring

Fine-tuned adapter istifadə edən hər request üçün loqla:

- Model versiyası (adapter sha)
- Latency (p50, p99)
- Response length
- User feedback (varsa)
- LLM-judge skor (sampling ilə)

Bu metriklər time-series-də degradasiyanı avtomatik aşkar etməyə imkan verir.

---

## 15. Xülasə Cədvəli

| Sual | Cavab |
|---|---|
| Niyə LoRA? | Tam FT 10-100× bahalıdır, LoRA oxşar keyfiyyət verir |
| Hansı rank? | Əksər hallarda r=16, aşağı r=8, yüksək r=32-64 |
| α nə olsun? | α = r və ya α = 2r |
| Target modules? | `all-linear` (attention + MLP) |
| QLoRA nə zaman? | Hər zaman — performans itkisi kiçik, yaddaş qənaəti böyükdür |
| 70B modeli fine-tune edə bilərəm? | Bəli, QLoRA ilə 2×A100 80GB kifayətdir |
| Merge edim, yoxsa ayrıca saxlayım? | Multi-tenant üçün ayrıca, single-model üçün merge |
| DoRA istifadə edim? | Kiçik məlumat dəsti və ya aşağı rank-da dəyərlidir |
| LoRA kifayət etmir? | Domain shift çox böyük, yeni bacarıq, security-critical |
| İlk training nə qədər tutur? | 8B/5k nümunə/r=16: ~2 saat, ~$3-5 |
| İterasiyalar nə qədər tutur? | Ümumi xərcin 70-80%-i iterasiyadır |
| Production-a necə çatdırım? | Adapter versioning + canary deployment + LLM-judge monitoring |

---

## Əsas Nəticələr

1. **LoRA, 2025-ci ildə praktik fine-tuning-in standartıdır.** QLoRA isə onu tək bir 24 GB GPU-ya endirir. Fine-tune axtarışı ilə başlayan hər komanda birbaşa QLoRA-dan başlamalıdır.

2. **Rank seçimi overfitting-lə underfitting arasında balansdır.** Kiçik datasetdə böyük rank istifadə etmək klassik səhvdir. Əksinə, böyük domain shift-də r=8 ilə başlayıb "niyə işləmir" demək də səhvdir.

3. **Multi-LoRA serving bir infrastruktur strategiyasıdır.** Hər müştəri üçün ayrıca model fine-tune etməyə çəkinməyin — birlikdə eyni base üzərində yaşayırlar.

4. **Fine-tuned model monitoringdən ayrı yaşaya bilməz.** Adapter production-a düşdükdən sonra keyfiyyət degradasiyası yavaş-yavaş başlaya bilər — yalnız ölçürsənsə görürsən.

5. **İterasiya qiymətidir, final training deyil.** Büdcəni iterasiyaya ayır. 5 sürətli iterasiya 1 "mükəmməl" trainingdən həmişə üstündür.

6. **LoRA adapter versioningi git-də saxla.** Rollback-ı dəqiqələrlə edə bilmək production-a etimadın əsasıdır.

7. **DoRA, rsLoRA, ReFT — bunları edge cases üçün yadda saxla.** Əksər problemlər üçün adi LoRA/QLoRA kifayət edir. Lazımsız mürəkkəblik əlavə etmə.
