# DPO, ORPO, KTO: RLHF-dən Sonrakı Müasir Alignment Metodları

> **Kim üçündür**: Laravel/PHP arxa plan mühəndisləri ki, komandası fine-tuned open-source model-i istifadəçi üstünlüklərinə uyğunlaşdırmaq istəyir. 33-cü fayl klassik RLHF (SFT → Reward Model → PPO) və Constitutional AI-nı izah edir. Bu fayl RLHF-dən **sonra gələn** sadələşdirilmiş metodları — DPO, IPO, ORPO, KTO, RLAIF — onlayn (online) iterasiya olmadan preference data-ya qarşı birbaşa necə öyrətməyi göstərir. Həmçinin "alignment vergisi", reward hacking, aligned model-lərdə rollback təhlükələri kimi production məsələləri. Python kodu (trl kitabxanası) ilə.

## Məzmun

1. Alignment Probleminin Qısa Xülasəsi
2. PPO Niyə Çox Mürəkkəbdir (Xatırlatma)
3. DPO: Direct Preference Optimization
4. DPO-nun Riyazi Quruluşu
5. DPO vs PPO Müqayisəsi
6. IPO: DPO Overfitting-inə Düzəliş
7. ORPO: SFT və Preference Tək Mərhələdə
8. KTO: Pair Əvəzinə Binary Signal
9. SLiC-HF: Likelihood Sequence Calibration
10. RLAIF: AI Judge-lə RL
11. Hansını Seçmək: Qərar Ağacı
12. Kod Nümunəsi: TRL DPOTrainer
13. Preference Data Hazırlamaq
14. Data Quality Problemləri və Filtrasiya
15. Alignment Vergisi: Non-Preference Metriklərin Degradasiyası
16. Reward Hacking və Çoxlu Yozumlar
17. Production: Rollback-ın Çətin Olan Tərəfi
18. Xülasə

---

## 1. Alignment Probleminin Qısa Xülasəsi

Fine-tune edilmiş base model təlimatlara əməl edə bilir, amma hələ də:

- Uzun-uzun cavab verir (istifadəçi qısa istəyirsə də)
- "Əla sual!" kimi yaltaqlıq sözlərdən istifadə edir
- Bəzən çox ehtiyatlıdır, bəzən çox inamlı səhv
- İstifadəçinin üstünlüklərini təxmin edə bilmir

Alignment (hizalanma), **ideal davranışın necə görünməsini**, istifadəçi preference signal-ı vasitəsilə modelə öyrətmək haqqındadır.

33-cü fayl klassik RLHF pipe-ını izah edir:

```
SFT → Reward Model trained on pairs → PPO policy optimization
```

Bu fayl 2023-2024-cü ildə yaranan daha sadə alternativləri əhatə edir. Hamısı bir şeyi həll edir: **PPO-nu atmaq**.

---

## 2. PPO Niyə Çox Mürəkkəbdir (Xatırlatma)

PPO (Proximal Policy Optimization) RL alqoritmidir. Training zamanı yaddaşda dörd model saxlamalısan:

```
┌──────────────────────────────────────────────────┐
│   PPO Training Step-də GPU Yaddaşı              │
├──────────────────────────────────────────────────┤
│  1. Policy model (active, öyrənilir)             │  ~140 GB
│  2. Reference policy (SFT, donmuş)               │  ~140 GB
│  3. Reward model (skor vermək üçün)              │  ~140 GB
│  4. Value model (critic, baseline üçün)          │  ~140 GB
│                                                  │
│  Cəmi: 4× model ölçüsü yaddaş                    │  ~560 GB
└──────────────────────────────────────────────────┘
```

Həm də mürəkkəb online loop:

```
while not converged:
    1. Policy-dən rollout (response generate et)
    2. Reward model ilə skor ver
    3. Value model ilə advantage hesabla
    4. KL penalty hesabla (reference-dən nə qədər uzaq?)
    5. PPO clipping ilə policy update
    6. Value model-i də update et
```

Hər step həm **inference** (generate), həm **training** (backprop) tələb edir. Hər zaman dəyişən training distribution — hiperparametrlər tənzimləməsi super incədir. Bu səbəbdən bir çox komanda PPO training başlatdıqdan sonra günlərlə diverge görür.

### Real Xərc

70B PPO training: 8× H100 × 2-4 həftə = $50k-$200k. Buna görə 2023-ə qədər yalnız böyük laboratoriyalar (OpenAI, Anthropic, Meta) RLHF istifadə edirdi.

---

## 3. DPO: Direct Preference Optimization

Rafailov və başq. (Stanford, 2023) dramatik bir iddia etdi: **reward model-i lazım deyil**. Siz birbaşa policy-i preference data ilə öyrədə bilərsiniz.

### Açar Müşahidə

Klassik RLHF-də reward model öyrənirsiniz, sonra policy-i həmin reward ilə optimize edirsiniz:

```
r̂(x, y) ← reward model training
π*(y|x) = arg max E[r̂(x, y)] − β · KL(π || π_ref)
```

Rafailov göstərdi ki, bu iki mərhələli prosesi **tək bir closed-form həllə** yığmaq olar. Optimal policy π* ilə reward r arasında analitik əlaqə var:

```
r(x, y) = β · log(π*(y|x) / π_ref(y|x)) + β · log Z(x)
```

Burada Z(x) partition function-dur (bütün mümkün cavabların toplamı). İki response arasında müqayisə edərkən Z(x) ləğv olur:

```
r(x, y_w) − r(x, y_l) = β · log(π(y_w|x) / π_ref(y_w|x))
                      − β · log(π(y_l|x) / π_ref(y_l|x))
```

Yəni, **reward fərqi birbaşa policy log-ratio-lar vasitəsilə ifadə olunur**. Reward model lazım deyil.

### DPO Loss

Nəticədə DPO loss funksiyası son dərəcə sadədir:

```
L_DPO(π_θ; π_ref) = −E_(x, y_w, y_l)~D [
    log σ(
        β · log(π_θ(y_w|x) / π_ref(y_w|x)) −
        β · log(π_θ(y_l|x) / π_ref(y_l|x))
    )
]
```

Burada:
- `y_w` — seçilmiş (winner) cavab
- `y_l` — rədd edilmiş (loser) cavab
- `π_θ` — training-də olan model
- `π_ref` — reference (adətən SFT checkpoint)
- `β` — temperature (tipik 0.1)
- `σ` — sigmoid

Bu, sadə supervised learning loss-dur. Standart PyTorch optimizer ilə minimize et. RL yoxdur, rollout yoxdur, reward model yoxdur.

---

## 4. DPO-nun Riyazi Quruluşu

### İntuitiv Yozum

Loss nəyə məcbur edir?

```
log(π_θ(y_w|x) / π_ref(y_w|x))  ←  winner-in əvvəlki model ilə
                                    müqayisədə log-ratio-su
log(π_θ(y_l|x) / π_ref(y_l|x))  ←  loser üçün eyni
```

DPO iki şeyi eyni anda edir:
1. Winner-in probabil-ini **artır** (reference-ə nisbətən)
2. Loser-in probabil-ini **azalt** (reference-ə nisbətən)

Amma tam özbaşına deyil — `β` parametri reference-dən nə qədər uzaqlaşa biləcəyini məhdudlaşdırır.

### β Parametri

```
β = 0.01:   Model referenceə çox yaxın qalır, preference az təsir edir
β = 0.1:    Standard, əksər hallarda balans
β = 0.5:    Aggressive, preference güclü təsir edir
β = 1.0:    Çox aggressive, instability riski
```

Praktikada β = 0.1 istifadə et. Training istəbilsə β-nı azalt.

### Yaddaş Profili

```
┌──────────────────────────────────────────────────┐
│   DPO Training Step-də GPU Yaddaşı              │
├──────────────────────────────────────────────────┤
│  1. Policy model (öyrənilir)                     │  ~140 GB
│  2. Reference model (donmuş)                     │  ~140 GB
│                                                  │
│  Cəmi: 2× model ölçüsü                           │  ~280 GB
└──────────────────────────────────────────────────┘
```

PPO-nun 4× əvəzinə 2×. QLoRA ilə birləşdirdikdə isə daha da yüngül — reference model 4-bit-də, trainable adapter isə kiçik.

---

## 5. DPO vs PPO Müqayisəsi

| Ölçü | PPO | DPO |
|---|---|---|
| Mərhələlər | 3 (SFT → RM → PPO) | 2 (SFT → DPO) |
| Training complexity | Yüksək (RL loop) | Aşağı (SFT-vari) |
| GPU yaddaşı | 4× model ölçüsü | 2× model ölçüsü |
| Online/offline | Online (rollout lazımdır) | Offline (static data) |
| İterasiya sürəti | Yavaş (günlər-həftələr) | Sürətli (saat-gün) |
| Hiperparametr tənzimi | Mürəkkəb, kövrək | Sadə, stabil |
| Reward hacking riski | Yüksək (ayrıca RM hack olunur) | Aşağı (implisit RM) |
| Exploration | Ola bilər (yeni response-lar kəşf edilir) | Yox (yalnız mövcud data) |
| Peak performance | SOTA (mükəmməl tənzimlənərsə) | Çox yaxın (asan tənzimlənir) |
| Single-GPU-da işləyə bilər? | Yox | Bəli (kiçik modellər üçün) |
| Sənayenin 2024-2025 standartı | - | Bəli |

### DPO Hansı Üstünlüklərlə Gəlir

- Academic and industrial uptake sürətli oldu (DPO paper → Mistral, Llama, Qwen hamısı DPO istifadə etdilər)
- Open-source ekosistem zəngin (HuggingFace trl, Axolotl)
- Reproducibility: DPO training iki dəfə başladın, eyni nəticə alın

### DPO Harada Uduzur

- Exploration olmur — data-da olmayan response-ları kəşf edə bilmir
- Data-ya həddindən artıq uyğun (overfit) olmaq asandır
- PPO, çox-çox yaxşı tənzimlənmiş data ilə, DPO-nu hələ də keçə bilir

Əksər production use case-ləri üçün DPO kifayət edir. PPO-ya yalnız frontier labs ehtiyac duyur.

---

## 6. IPO: DPO Overfitting-inə Düzəliş

DPO-nun bir problemi: **Bradley-Terry assumption**. DPO preference data-nın "A həmişə B-dən yaxşıdır" kimi deterministic olduğunu fərz edir. Lakin real data-da bu doğru deyil — annotator-lar razılaşmır, "A 60% yaxşıdır, amma B də qəbul edilə biləndir" kimi hallar olur.

DPO bu belirsizliyə baxmayaraq hər pair üçün winner-in probabil-ini sonsuz artırmağa çalışır. Nəticədə model overfit olur və generation keyfiyyəti pisləşir.

### IPO Həlli

Identity Preference Optimization (Azar və başq., 2023) DPO loss-a bir kvadrat termin əlavə edir ki, model öz-özünü həddindən artıq confidenty-ə yüksəltməsin:

```
L_IPO = (log(π_θ(y_w|x) / π_ref(y_w|x)) 
      − log(π_θ(y_l|x) / π_ref(y_l|x)) − 1/(2β))²
```

Bu, model-i "doğru miqdarda" preference-ə uyğunlaşdırır, sonsuz itələmir.

### Nə Vaxt İstifadə Etməli

- Preference data-nızda noise varsa (annotator disagreement yüksəkdir)
- DPO training-ində overfit görürsənsə (eval loss artır, train loss düşür)
- Cavab uzunluğu drastic şəkildə artırsa (DPO sycophancy əlaməti)

TRL kitabxanasında `DPOTrainer(loss_type="ipo")` ilə aktiv et.

---

## 7. ORPO: SFT və Preference Tək Mərhələdə

Hong və başq. (2024) başqa bir sadələşdirmə təklif etdilər: **reference model-i ümumiyyətlə atalım**.

Odds Ratio Preference Optimization (ORPO) göstərir ki, əgər SFT və preference training-i birləşdirsəniz, reference model-ə ehtiyac qalmır.

### Niyə Reference Model Lazımdır?

PPO və DPO-da reference model "model ilkin SFT-dən necə sapdığını" ölçmək üçündür. Bu, divergence-i məhdudlaşdırır, unstable training-in qarşısını alır.

Lakin reference model yaddaş istifadə edir və ikinci inference step tələb edir.

### ORPO Loss

```
L_ORPO = L_SFT + λ · L_OR

Harada:
  L_SFT — winner cavab üzərində adi negative log-likelihood
  L_OR — odds ratio loss (winner-i loser-dən üstün tut)
  λ — balans (tipik 0.1)
```

L_OR:

```
L_OR = − log σ(log(odds(y_w|x)) − log(odds(y_l|x)))

Harada: odds(y|x) = P(y|x) / (1 − P(y|x))
```

### Yaddaş Nəticəsi

```
Training step-də yaddaş:
  SFT:   1× model
  DPO:   2× model (policy + reference)
  ORPO:  1× model !
```

ORPO tək model ilə alignment mümkün edir. Yaddaş və kompüt baxımından ən yüngüldür.

### Nə Vaxt ORPO?

- Fresh start: SFT edilməmiş base model-dən alignment edirsinizsə
- Yaddaş kritik mühitdə (kiçik GPU, edge device)
- Sadə workflow istəyirsizsə

### Nə Vaxt DPO?

- SFT artıq edilib və yaxşı bir checkpoint var
- Preference data SFT datadan tamamilə fərqli olduğunda (əks halda ORPO confusion verə bilər)

---

## 8. KTO: Pair Əvəzinə Binary Signal

Bütün yuxarıdakı metodlar (DPO, IPO, ORPO) **pairwise preference data** tələb edir: hər prompt üçün bir winner və bir loser response.

Lakin real-dünyada belə data toplamaq bahalıdır. Daha çox vaxt aşağıdakı kimi data olur:

```
Thumbs up/down feedback:
  Response A: 👍
  Response B: 👎 (amma başqa bir sualın cavabı)
  Response C: 👍
```

Bu, pair deyil — bir cavab "yaxşı" və ya "pis" deyə bin-aryparty işarələnir.

### KTO Həlli

Kahneman-Tversky Optimization (Ethayarajh və başq., 2024) insan seçim nəzəriyyəsindən ilham alır. İnsanlar qazanclara və itkilərə asimmetrik baxırlar — bu, prospect theory-nin əsas prinsipidir.

KTO loss:

```
L_KTO = w_desirable · (1 − σ(r_θ(x, y) − z_0)),  əgər y yaxşıdır
      + w_undesirable · (1 − σ(z_0 − r_θ(x, y))),  əgər y pisdir

Harada:
  r_θ — implicit reward (DPO kimi)
  z_0 — reference point (KL divergence-dən hesablanır)
  w — desirability weight (yaxşı/pis balansı üçün)
```

### Data Formatı

```json
{
  "prompt": "Sifarişim harada?",
  "completion": "Sifarişiniz yolda, sabah çatacaq.",
  "label": true
}
{
  "prompt": "Məhsul pisdir, geri qaytarın.",
  "completion": "Əsla, bu sizin problemdir.",
  "label": false
}
```

Yalnız `(prompt, response, good/bad)` triple — pair yox.

### Data Toplama Üstünlüyü

```
Pair collection:
  Annotator 1 prompt üçün 2 cavab görür, daha yaxşısını seçir
  Zaman: ~45 saniyə
  1000 pair: 12.5 saat × $20 = $250

Binary collection:
  Real production feedback (thumbs up/down)
  Zaman: istifadəçi sıfır əlavə effort
  10000 label: $0 (production signal)
```

KTO, production istifadəçi feedback-indən birbaşa alignment öyrətməyə imkan verir. Bu, real-world preference dataının ən asan toplanan növüdür.

### Nə Vaxt KTO?

- Production-da istifadəçi thumbs up/down var
- Customer support ticket resolution (yaxşı həll / pis həll)
- Moderation signal (flagged / approved)
- Pair data-nın olmadığı hər yer

### Çatışmazlıq

Binary signal pair-dən daha zəifdir. Eyni nəticə üçün 3-5× daha çox data lazımdır. Lakin data pulsuzdursa, bu problem deyil.

---

## 9. SLiC-HF: Likelihood Sequence Calibration

Zhao və başq. (Google, 2023) SLiC-HF təklif etdilər. DPO kimi, lakin ranking loss istifadə edir:

```
L_SLiC = max(0, δ − r(x, y_w) + r(x, y_l)) + λ · L_reg

Harada:
  δ — margin (tipik 1.0)
  L_reg — regularization (SFT loss-a yaxın)
```

Bu, "hinge loss" tipli ranking loss-dur. DPO-nun Bradley-Terry varsayımını atır.

Praktikada DPO və IPO-dan daha az istifadə olunur, amma bəzi hallarda daha stabil ola bilər. TRL kitabxanasında DPOTrainer-in `loss_type="hinge"` variantı budur.

---

## 10. RLAIF: AI Judge-lə RL

RLAIF (Reinforcement Learning from AI Feedback) — preference data yaratmaq üçün insanlar əvəzinə başqa LLM-dən istifadə etməkdir.

### Proses

```
1. SFT model-dən eyni prompt üçün iki cavab yarat: y_A, y_B
2. Judge LLM (məs., Claude Opus) ilə soruş: "Hansı daha yaxşıdır?"
3. Cavab DPO data-nın bir nümunəsi olur: (prompt, y_chosen, y_rejected)
4. DPO/ORPO/KTO ilə model-i öyrət
```

### Üstünlüklər

- Miqyaslana bilir: insan annotator limitimiz yoxdur
- Ucuz: API çağırışı insan annotator-dan 100× ucuzdur
- Ardıcıl: eyni judge həmişə eyni meyarla qiymətləndirir
- Şəffaf: judge prompt-u audit oluna bilər

### Problemlər

- Judge qərəzləri: GPT-4 judge GPT-stilli cavablara üstünlük verir
- Self-reinforcing errors: judge səhvi varsa, model də onu öyrənir
- Evaluation complication: eval üçün də judge istifadə etmək qərəzdir

### Constitutional AI — Anthropic-in Variantı

33-cü fayl CAI-nı dərindən izah edir. Qısa xülasə: RLAIF-də "hansı daha yaxşıdır?" əvəzinə, konstitusiyadakı konkret prinsiplərə görə qiymətləndirmə aparılır. Bu, daha şəffaf və qərəzin açıq olan versiyadır.

---

## 11. Hansını Seçmək: Qərar Ağacı

```
Data formatınız nədir?
│
├─ Pair (chosen, rejected):
│   │
│   ├─ SFT artıq olub, preference-i tətbiq edirsən:
│   │   └─ DPO (ən standart seçim)
│   │
│   ├─ Noisy preference data (annotator disagreement):
│   │   └─ IPO (overfit-ə qarşı güclüdür)
│   │
│   └─ Yaddaş məhdud, fresh model, SFT də lazım:
│       └─ ORPO (ikisini birləşdirir)
│
├─ Binary (thumbs up/down):
│   └─ KTO
│
└─ Constitution / principles ilə scoring:
    └─ RLAIF (AI judge ilə) + DPO
```

### Praktiki Tövsiyə

Əgər 2025-ci ildə alignment-ə başlayırsansa və heç bir xüsusi tələbin yoxdursa:

```
1. SFT ilə başla (instruction tuning)
2. Preference data topla (RLAIF ilə sintetik də olar)
3. DPO tətbiq et (trl kitabxanası ilə)
4. Nəticələri eval et
5. Əgər overfit görürsən → IPO-ya keç
6. Əgər yaddaş problem olursa → ORPO-ya keç
```

---

## 12. Kod Nümunəsi: TRL DPOTrainer

```python
# dpo_train.py
from transformers import AutoTokenizer, AutoModelForCausalLM, BitsAndBytesConfig
from peft import LoraConfig, get_peft_model, prepare_model_for_kbit_training
from trl import DPOTrainer, DPOConfig
from datasets import load_dataset
import torch

# 1. Base model (SFT-done checkpoint)
model_name = "./checkpoints/llama-8b-sft-az-support"

bnb_config = BitsAndBytesConfig(
    load_in_4bit=True,
    bnb_4bit_quant_type="nf4",
    bnb_4bit_compute_dtype=torch.bfloat16,
    bnb_4bit_use_double_quant=True,
)

model = AutoModelForCausalLM.from_pretrained(
    model_name,
    quantization_config=bnb_config,
    device_map="auto",
    attn_implementation="flash_attention_2",
)
model = prepare_model_for_kbit_training(model)

# 2. LoRA adapter (DPO üçün)
lora_config = LoraConfig(
    r=16,
    lora_alpha=32,
    lora_dropout=0.05,
    target_modules=[
        "q_proj", "k_proj", "v_proj", "o_proj",
        "gate_proj", "up_proj", "down_proj",
    ],
    bias="none",
    task_type="CAUSAL_LM",
)
model = get_peft_model(model, lora_config)

# 3. Tokenizer
tokenizer = AutoTokenizer.from_pretrained(model_name)
tokenizer.pad_token = tokenizer.eos_token

# 4. Preference dataset
# Format:
#   {"prompt": "...", "chosen": "...", "rejected": "..."}
dataset = load_dataset("json", data_files="data/preference_train.jsonl", split="train")
eval_ds = load_dataset("json", data_files="data/preference_eval.jsonl", split="train")

# 5. DPO konfiqurasiyası
dpo_config = DPOConfig(
    output_dir="./checkpoints/az-support-dpo",
    num_train_epochs=1,              # DPO adətən 1 epoch kifayətdir
    per_device_train_batch_size=2,
    gradient_accumulation_steps=4,
    learning_rate=5e-6,              # SFT-dən KİÇİK LR (5e-6 — 1e-5)
    lr_scheduler_type="cosine",
    warmup_ratio=0.1,
    bf16=True,
    optim="paged_adamw_8bit",
    logging_steps=10,
    save_strategy="epoch",
    eval_strategy="steps",
    eval_steps=100,
    gradient_checkpointing=True,
    max_length=2048,                 # prompt + chosen/rejected
    max_prompt_length=1024,
    beta=0.1,                        # DPO temperature
    loss_type="sigmoid",             # DPO; "ipo" üçün IPO; "hinge" SLiC
    report_to=["wandb"],
    run_name="az-support-dpo-v1",
)

# 6. Trainer
# Qeyd: ref_model=None olduqda, peft adapter-siz model reference olur
trainer = DPOTrainer(
    model=model,
    ref_model=None,
    args=dpo_config,
    train_dataset=dataset,
    eval_dataset=eval_ds,
    tokenizer=tokenizer,
    peft_config=lora_config,
)

trainer.train()

# 7. Adapter-i saxla
trainer.save_model("./checkpoints/az-support-dpo/final")
```

### DPO Training-də Nəyə Diqqət Etməli

**Öyrənmə sürəti (LR)**: SFT-dən 10-20× daha kiçik. SFT-də 2e-4 istifadə edirsənsə, DPO-da 5e-6 və ya 1e-5.

**Epoch sayı**: Adətən 1 epoch kifayətdir. Çox epoch overfit verir.

**Monitoring metric**: `rewards/chosen` artmalı, `rewards/rejected` azalmalıdır. `rewards/margin` artmalıdır. Əgər `rewards/chosen` də düşürsə → LR çox yüksəkdir.

**β seçimi**: 0.1 ilə başla. Əgər model preference-ə az reaksiya verirsə β artır. Əgər cavablar lazımsız dəyişirsə β azalt.

### İstifadə Nümunəsi: ORPO

ORPO üçün trl-nin `ORPOTrainer`-i istifadə et:

```python
from trl import ORPOTrainer, ORPOConfig

orpo_config = ORPOConfig(
    output_dir="./checkpoints/az-support-orpo",
    num_train_epochs=3,
    per_device_train_batch_size=2,
    gradient_accumulation_steps=4,
    learning_rate=8e-6,
    lr_scheduler_type="cosine",
    bf16=True,
    optim="paged_adamw_8bit",
    max_length=2048,
    max_prompt_length=1024,
    beta=0.1,                # λ parameter (SFT vs OR balance)
    remove_unused_columns=False,
    report_to=["wandb"],
)

trainer = ORPOTrainer(
    model=model,
    args=orpo_config,
    train_dataset=dataset,
    eval_dataset=eval_ds,
    tokenizer=tokenizer,
    peft_config=lora_config,
)
trainer.train()
```

### İstifadə Nümunəsi: KTO

```python
from trl import KTOTrainer, KTOConfig

kto_config = KTOConfig(
    output_dir="./checkpoints/az-support-kto",
    num_train_epochs=1,
    per_device_train_batch_size=2,
    gradient_accumulation_steps=4,
    learning_rate=5e-6,
    bf16=True,
    max_length=2048,
    beta=0.1,
    desirable_weight=1.0,
    undesirable_weight=1.0,
    report_to=["wandb"],
)

# Format: {"prompt": "...", "completion": "...", "label": true|false}
trainer = KTOTrainer(
    model=model,
    ref_model=None,
    args=kto_config,
    train_dataset=kto_dataset,
    tokenizer=tokenizer,
    peft_config=lora_config,
)
trainer.train()
```

---

## 13. Preference Data Hazırlamaq

Alignment-in uğuru data keyfiyyətindən asılıdır. Preference data yaratmaq üçün üç əsas yol:

### 1. Human Pairwise Annotation

Ən keyfiyyətli, lakin ən bahalı.

```
Workflow:
1. SFT model-dən eyni prompt üçün 2 cavab generate et
2. Annotator "A daha yaxşıdır" və ya "B daha yaxşıdır" seçir
3. Quality control: hər nümunə 3 annotator görür, majority vote

Xərc:
  Annotator: $15-30/saat
  Throughput: ~80 nümunə/saat
  1000 pair: ~$400-800
  10,000 pair: $4,000-8,000
```

### 2. RLAIF (LLM-as-Judge)

Daha ucuz, miqyaslana bilir.

```python
async def generate_preference_pair(prompt: str, sft_model, judge) -> dict:
    # SFT-dən 2 cavab generate et (temperature=0.7)
    resp_A = await sft_model.complete(prompt, temperature=0.7)
    resp_B = await sft_model.complete(prompt, temperature=0.7)
    
    judge_prompt = f"""
Prompt:
{prompt}

Response A:
{resp_A}

Response B:
{resp_B}

Bu iki cavabdan hansı daha yaxşıdır? Aşağıdakı meyarları nəzərə al:
1. Dəqiqlik
2. Kömək səviyyəsi
3. Düzgün ton
4. Format uyğunluğu

Yalnız "A" və ya "B" cavabla.
""".strip()

    judgment = await judge.complete(judge_prompt, temperature=0)
    
    if judgment.strip() == "A":
        return {"prompt": prompt, "chosen": resp_A, "rejected": resp_B}
    else:
        return {"prompt": prompt, "chosen": resp_B, "rejected": resp_A}

# Massə generate et
import asyncio

async def build_preference_dataset(prompts: list[str], sft_model, judge) -> list:
    tasks = [generate_preference_pair(p, sft_model, judge) for p in prompts]
    return await asyncio.gather(*tasks)
```

```
Xərc (Claude Sonnet as judge):
  Hər pair: ~$0.01 (3 API call: 2 generate + 1 judge)
  1000 pair: ~$10
  10,000 pair: ~$100
```

### 3. Production Feedback (KTO üçün)

```
Laravel production log:
  user_query → bot_response → user_reaction (thumbs up/down)

Bu data-nı KTO formatına çevir:
  {prompt: user_query, completion: bot_response, label: thumbs_up}
```

Xərci: sıfır. Real-world signal. Amma KTO istifadə etməlisən (pair yoxdur).

### 4. Rule-Based Pair Generation

Bəzi hallarda qayda-əsaslı şəkildə preference pair yarada bilərsən:

```
Scenario: "Rude tone" vs "Polite tone"
  Chosen: claude.completion(prompt, style="polite")
  Rejected: claude.completion(prompt, style="rude")

Scenario: "With policy violation" vs "Safe"
  Rejected: gpt_jailbroken.completion(risky_prompt)
  Chosen: safe_model.completion(risky_prompt)

Scenario: "Correct format" vs "Wrong format"
  Chosen: structured_response
  Rejected: unstructured_response
```

Bu, xüsusən format, safety, tone alignment üçün çox effektivdir.

---

## 14. Data Quality Problemləri və Filtrasiya

Preference data-da typical problems:

### 1. Identical Pairs

Chosen və rejected cavablar demək olar ki, eynidir. Bu, signal vermir.

```python
from difflib import SequenceMatcher

def filter_identical(pairs, threshold=0.95):
    return [
        p for p in pairs
        if SequenceMatcher(None, p["chosen"], p["rejected"]).ratio() < threshold
    ]
```

### 2. Length Bias

Chosen cavablar həmişə uzundursa, model "uzun = yaxşı" öyrənir. Bu, Goodhart's Law classic case-dir.

```python
import numpy as np

def check_length_bias(pairs):
    chosen_lens = [len(p["chosen"]) for p in pairs]
    rejected_lens = [len(p["rejected"]) for p in pairs]
    
    chosen_mean = np.mean(chosen_lens)
    rejected_mean = np.mean(rejected_lens)
    
    ratio = chosen_mean / rejected_mean
    if ratio > 1.3 or ratio < 0.77:
        print(f"WARNING: Length bias detected. Ratio: {ratio:.2f}")
        return False
    return True
```

Həll: Data-nı length-matched şəkildə filter et. Və ya DPO training-də length penalty əlavə et.

### 3. Prompt Diversity

Əgər 1000 pair-in hamısı "refund" haqqındadırsa, model yalnız refund scenario-larında alignment olar. Digər topics regress edə bilər.

```python
from sklearn.cluster import KMeans
from sentence_transformers import SentenceTransformer

def analyze_prompt_diversity(pairs, n_clusters=20):
    model = SentenceTransformer("all-MiniLM-L6-v2")
    embeddings = model.encode([p["prompt"] for p in pairs])
    
    kmeans = KMeans(n_clusters=n_clusters)
    labels = kmeans.fit_predict(embeddings)
    
    from collections import Counter
    counts = Counter(labels)
    print("Cluster distribution:", counts.most_common())
    
    max_cluster_size = max(counts.values())
    if max_cluster_size > len(pairs) * 0.3:
        print("WARNING: Data heavily skewed toward one topic")
```

### 4. Contamination

Evaluation prompt-ları training-də varsa, evaluation süni şəkildə yüksəkdir.

```python
from datasketch import MinHash, MinHashLSH

def check_contamination(train_prompts, eval_prompts, threshold=0.8):
    lsh = MinHashLSH(threshold=threshold, num_perm=128)
    
    for i, p in enumerate(train_prompts):
        m = MinHash(num_perm=128)
        for word in p.lower().split():
            m.update(word.encode("utf8"))
        lsh.insert(f"train_{i}", m)
    
    contaminated = []
    for i, p in enumerate(eval_prompts):
        m = MinHash(num_perm=128)
        for word in p.lower().split():
            m.update(word.encode("utf8"))
        if lsh.query(m):
            contaminated.append(i)
    
    print(f"Contaminated eval prompts: {len(contaminated)}/{len(eval_prompts)}")
    return contaminated
```

---

## 15. Alignment Vergisi: Non-Preference Metriklərin Degradasiyası

**Alignment tax** (alignment vergisi) — alignment-dən sonra model-in *başqa* metriklərdə itirdiyi performans. Bu real və ölçüləndir.

### Klassik Müşahidə

OpenAI-ın InstructGPT paper-i qeyd etdi: RLHF-dən sonra:

```
                        Base model   After RLHF
───────────────────────────────────────────────
TriviaQA (fakt qiyməti) 55.2         53.1   (-2.1)
HellaSwag (reasoning)   78.4         76.9   (-1.5)
Instruction following   3.2/5        4.6/5  (+1.4) ✓
User preference         35%          62%    (+27%) ✓
```

Preference metric-də dramatic yüksəliş, amma reasoning və fakt metric-lərində degradation.

### DPO-da Eyni Fenomen

DPO training-i də alignment tax gətirir. Səbəblər:

1. **Over-confidence**: model preference ilə uyğunsuz düşünmür (knowledge retrieval pisləşir)
2. **Format drift**: DPO "yaxşı formatlı" cavabları üstün tutur, amma bəzən bu dəqiqlikdən itkidir
3. **Sycophancy amplification**: winner cavablar user-dən razılıq alırsa, model yaltaqlaşır

### Monitoring: Alignment Vergisini Ölç

```python
evaluation_suite = {
    # Preference alignment metriklərı (yaxşılaşmalı)
    "preference_win_rate": lambda m: judge_win_rate(m),
    "instruction_compliance": lambda m: check_instructions(m),
    
    # Non-preference metriklərı (degradasiyalı izlə!)
    "mmlu": lambda m: benchmark_mmlu(m),           # ümumi bilik
    "truthfulqa": lambda m: benchmark_tqa(m),      # dürüstlük
    "hellaswag": lambda m: benchmark_hs(m),        # reasoning
    "humaneval": lambda m: benchmark_code(m),      # kod
    "gsm8k": lambda m: benchmark_math(m),          # math
}

# Hər DPO epoch-dan sonra ölç
for epoch in range(num_epochs):
    train_one_epoch(model)
    for name, fn in evaluation_suite.items():
        score = fn(model)
        print(f"{name}: {score}")
```

### Alignment Vergisini Azaltmaq

1. **KL penalty sahibi ol**: β-nı kiçildə. Model reference-dən çox uzaqlaşmasın.

2. **Mixed training**: SFT data + preference data birlikdə öyrət (ORPO və ya DPO + SFT regularization).

3. **Conservative epoch count**: 1 epoch kifayətdir. Çox epoch = çox alignment tax.

4. **Eval-based early stopping**: non-preference metriklər degradasiya başlayanda dayan.

5. **NEFTune noise**: training zamanı embedding-ə random noise əlavə etmək alignment tax-ı azaldır.

---

## 16. Reward Hacking və Çoxlu Yozumlar

Klassik RLHF-də reward hacking açıq bir problem idi. DPO-da eyni şey daha incə formada baş verir.

### DPO Reward Hacking Nümunələri

**Problem 1: Length exploitation**

DPO data-da uzun cavablar daha tez chosen olursa, model "daha uzun cavab yaz" strategiyasını öyrənir. Real quality artmayıb, sadəcə uzunluq.

```
Before DPO: 180 tokens average
After DPO:  420 tokens average  (+133%)
```

**Problem 2: Confidence inflation**

Winner cavablarda "Əminəm ki..." kimi ifadələr varsa, model uncertainty-ni gizləməyi öyrənir. Dürüstlük itkisi.

**Problem 3: Format locking**

DPO data bullet point formatlı cavabları chosen edirsə, model hər cavabı bullet point-lərə çevirir — hətta narrative cavab uyğun olduğu hallarda.

### Qorunma

```python
# Length constraint ilə DPO
class LengthRegularizedDPO(DPOTrainer):
    def dpo_loss(self, policy_chosen_logps, policy_rejected_logps,
                 reference_chosen_logps, reference_rejected_logps,
                 chosen_lens, rejected_lens):
        loss = super().dpo_loss(...)
        
        # Length penalty əlavə et
        length_diff = chosen_lens.mean() - rejected_lens.mean()
        length_penalty = 0.01 * length_diff.abs()
        
        return loss + length_penalty
```

---

## 17. Production: Rollback-ın Çətin Olan Tərəfi

Aligned model production-a getdikdən sonra ciddi bir problem yaranır: **rollback asan deyil**.

### Səbəb

Klassik software rollback: git revert, deploy. Tamamlandı.

Aligned LLM rollback isə mənalı tradeoff-ları açır:

- SFT checkpoint-ə qayıtmaq = alignment bütün qabiliyyətlərini itirmək (yaltaqlıq yox, amma heç bir polish da yox)
- DPO v1-ə qayıtmaq = DPO v2-dən sonra öyrənilmiş bütün yaxşılaşmaları itirmək
- İrəli "hot fix" = yeni alignment run, günlər və ya həftələr çəkir

### Production Strategy: Multi-Checkpoint Ladder

```
Production-da həmişə bir neçə checkpoint saxla:
  
  models/
  ├── base/                      (Llama 8B, raw)
  ├── sft-v1.0/                  (SFT done)
  ├── dpo-v1.0/                  (DPO on top of SFT)
  ├── dpo-v2.0/                  (current prod)
  └── dpo-v2.1-candidate/        (canary)

Rollback levels:
  Level 1: v2.0 (previous prod DPO)  — light rollback
  Level 2: v1.0 (earlier DPO)        — medium rollback
  Level 3: SFT                        — heavy rollback (alignment itkisi)
```

### Canary Strategy

Heç bir alignment model-i full production-a birbaşa atılmaz:

```
Day 1-3: Shadow mode
  - Yeni model response-ları generate edir
  - İstifadəçi görmür, yalnız saxlanır
  - LLM-judge ilə müqayisə: yeni vs köhnə

Day 4-7: 5% canary
  - 5% real istifadəçiyə yeni model
  - Kritik metriklər: CSAT, task completion, error rate
  - Auto-rollback trigger: CSAT < X, errors > Y

Day 8-14: 25% rollout
  - Scale, lakin fallback hazır

Day 15+: Full rollout
  - Köhnə model hələ 1 ay saxlanır (emergency rollback)
```

### Alignment Diff Audit

Yeni alignment model production-a getməzdən əvvəl bu audit-i et:

```python
def alignment_audit(old_model, new_model, test_prompts):
    report = {
        "same_response": 0,
        "similar_response": 0,
        "different_safe": 0,
        "different_concerning": 0,
    }
    
    for prompt in test_prompts:
        old_resp = old_model.complete(prompt)
        new_resp = new_model.complete(prompt)
        
        if old_resp == new_resp:
            report["same_response"] += 1
        elif similarity(old_resp, new_resp) > 0.8:
            report["similar_response"] += 1
        else:
            # Fərqli cavab — təhlükəsiz fərqdirmi?
            judgment = safety_judge(prompt, old_resp, new_resp)
            if judgment["safe"]:
                report["different_safe"] += 1
            else:
                report["different_concerning"] += 1
    
    return report
```

Əgər `different_concerning` > threshold, production-a getmə.

---

## 18. Xülasə

### Alignment Method Müqayisəsi

| Metod | Data tələbi | Yaddaş | Stability | İstifadə halı |
|---|---|---|---|---|
| PPO | Pair preference | 4× model | Low | Frontier labs, extreme performance |
| DPO | Pair preference | 2× model | High | Standart seçim |
| IPO | Pair preference | 2× model | Very high | Noisy preference data |
| ORPO | Pair preference | 1× model | High | Fresh model, yaddaş məhdud |
| KTO | Binary label | 2× model | High | Production feedback data |
| SLiC-HF | Pair preference | 2× model | Medium | Hinge loss preferring case |
| RLAIF | Judge LLM | 2× model | High | Miqyaslı data yaratma |

### Tipik Workflow

```
1. SFT (29-30-cu fayl)
   ↓
2. Preference data topla
   ├── Human annotation (əla keyfiyyət, bahalı)
   ├── RLAIF (miqyaslı, orta keyfiyyət)
   └── Production feedback (pulsuz, KTO üçün)
   ↓
3. Data filtrasiya (uzunluq, similarity, contamination)
   ↓
4. Alignment (DPO default-dur, variantlarını sına)
   ↓
5. Dual evaluation
   ├── Preference metriklər yüksəlir?
   └── Non-preference metriklər dəyişməyib? (alignment tax)
   ↓
6. Shadow mode → Canary → Gradual rollout
   ↓
7. Multi-checkpoint ladder saxla (rollback üçün)
```

### Əsas Nəticələr

1. **DPO 2024-2025-in standartıdır.** PPO-ya ehtiyac yoxdursa, DPO ilə başla. Daha sadə, daha stabil, daha ucuz.

2. **Data keyfiyyəti alignment-i müəyyən edir.** Length bias, contamination, diversity problemləri alignment-ı qısa vaxtda pozur. Data audit olmadan training-ə başlama.

3. **Alignment tax real.** Hər alignment mərhələsi non-preference metrikləri pozur. Continuous monitoring olmadan fərqinə varmaya bilərsən.

4. **KTO production feedback-dən alignment öyrənməyə imkan verir.** Thumbs up/down signal varsa, KTO ilə pulsuz alignment datası var.

5. **ORPO yaddaş məhdud mühitlərdə üstündür.** Reference model saxlamaq lazım deyil, SFT + preference birlikdə edilir.

6. **Rollback çətindir, çünki alignment bir yollu küçə kimi hiss olur.** Multi-checkpoint ladder və canary rollout olmadan alignment production-a getmə.

7. **Judge LLM qərəzləri real.** RLAIF data qiymətlidir, amma həmin judge ilə evaluation etmə — circular bias-a düşərsən.

8. **Constitutional AI RLAIF-in şəffaf formasıdır.** Principles açıq yazılır, audit edilə bilir, öyrədilmiş model-in davranışının kökündəki dəyərlər görünür olur. 33-cü fayl dərin izah edir.

9. **Alignment modeli öldürmür, amma qabiliyyətlərini dəyişdirir.** Dyunavı mühəndislik kimi — model yenidən yazılmır, amma personality-si yenidən formalaşır. Lazımsız hər alignment iterasiyası qabiliyyət itkisi ilə gəlir.
