# Model Distillation: Böyük Modellərdən Kiçik, Sürətli Modellər Çıxarmaq (Lead)

> **Kim üçündür**: Laravel/PHP arxa plan mühəndisləri ki, komandası böyük model API xərclərindən şikayət edir və ya latency tələbləri ilə qarşılaşır. `01-fine-tuning-overview.md` ümumi fine-tuning-dən, `02-fine-tuning-vs-rag.md` RAG vs FT seçimindən, `04-lora-qlora-peft.md` LoRA/QLoRA-dan bəhs edir. Bu fayl isə fərqli sualı cavablandırır: **"Claude Sonnet bizim use case-də əla işləyir, amma ayda $30K kəsir. Onun "beynindən" daha kiçik və ucuz model çıxara bilərikmi?"** Bəli, edə bilərsiniz — texnika distillation adlanır. Burada Python kodu və real training workflow-ları var.

## Məzmun

1. Distillation Nədir?
2. Niyə Distillation: Speed, Cost, Privacy, Control
3. Black-Box vs White-Box Distillation
4. Task-Specific vs General-Purpose Distillation
5. Riyaziyyat: KL Divergence və Temperature
6. Data Generation Pipeline
7. Anthropic və OpenAI-ın Daxili Praktikası
8. Real Case Study: Llama 70B → 8B
9. Kod: Claude Sonnet-dən Llama 8B-yə Distillation
10. Quality Gap Həqiqəti
11. Xərc Müqayisəsi: Teacher API vs Student Serving
12. Failure Modes
13. Qiymətləndirmə: Teacher-as-Judge Problemi
14. Production Deployment və A/B Test
15. Reasoning Distillation: R1 və CoT
16. Xülasə

---

## 1. Distillation Nədir?

Distillation — böyük, güclü "teacher" (müəllim) modelin "bilməsini" kiçik, sürətli "student" (şagird) modelə köçürmək prosesidir. İlk dəfə 2015-ci ildə Hinton və başq. "Distilling the Knowledge in a Neural Network" məqaləsində təqdim edilib.

Əsas intuisiya sadədir: **teacher-in output-ları tam cavabdan daha çox bilik daşıyır**. Teacher yalnız "Paris Fransanın paytaxtıdır" demir — eyni zamanda "lakin Madrid də mümkündür, baxmayaraq ki, çox aşağı ehtimalla" kimi incə fərqləri öz ehtimal paylanmasında ifadə edir. Student bu incə fərqlərdən öyrənə bilir.

### Klassik Analoqiya

```
Pretraining:  Kitabxanadan kitab oxuyursan (internet korpusu).
              Çoxlu məlumat, amma naviqasiyasız.

Fine-tuning:  Bir usta peşəkarın yazdığı nümunələri kopyalayırsan.
              Dəqiq, amma darağımzdır.

Distillation: Bir usta peşəkarın işləyərkən səni izləməsinə icazə verirsən.
              Onun hər qərar verməsində şahid olursan — yalnız final
              cavabı deyil, eyni zamanda "niyə A B-dən daha üstündür"
              fikrini də.
```

### Tipik Distillation Workflow

```
┌──────────────────┐
│  Teacher Model   │     (məs., Claude Sonnet 4.7, GPT-4o, DeepSeek R1)
│   (böyük, bahalı)│
└────────┬─────────┘
         │ 1. Prompt-ları cavabla
         │ 2. Output-ları saxla (və bəzən logit-ləri)
         ▼
┌──────────────────┐
│  Training Dataset│     10K-1M (prompt, teacher_response) cütü
│  (teacher output)│
└────────┬─────────┘
         │ 3. Student-i bu data üzərində öyrət
         │    (SFT, LoRA, və ya tam FT)
         ▼
┌──────────────────┐
│   Student Model  │     (məs., Llama 3.1 8B, Mistral 7B)
│  (kiçik, sürətli)│
└──────────────────┘
         │ 4. Production-a deploy et
         ▼
     10-100× ucuz inference
```

---

## 2. Niyə Distillation: Speed, Cost, Privacy, Control

Distillation-a niyə ehtiyac var? Dörd əsas səbəb:

### Speed (Latency)

Claude Sonnet 4.7 orta cavab vaxtı ~1500ms (streaming başlanğıcı). Llama 3.1 8B (vLLM + A10G) ~80ms. Real-time istifadə halları (voice assistants, live chat, autocomplete) bu fərq məsələdir.

```
Use case                    | Teacher latency | Student latency
───────────────────────────────────────────────────────────────
Voice assistant             | ~1500ms (too slow) | ~80ms ✓
Autocomplete                | ~800ms (too slow)  | ~40ms ✓
Background email classification | ~1500ms (OK)   | ~50ms (better)
Complex analysis report     | ~3000ms (OK)       | poor quality
```

### Cost

```
Ayda 10M inference üçün xərc müqayisəsi:

Teacher (Claude Sonnet 4.7 via API):
  Input:  10M × 500 tokens × $3/M = $15,000
  Output: 10M × 300 tokens × $15/M = $45,000
  Cəmi: $60,000/ay

Student (Llama 3.1 8B self-hosted):
  GPU: 2× A100 80GB × $1.89/saat × 24 × 30 = $2,722
  Team maintenance: ~$2,000
  Cəmi: $4,722/ay

Qənaət: $55,278/ay = $663,000/il
Distillation xərci (birdəfəlik): ~$5,000
Break-even: 3 gün
```

### Privacy

Bank, səhiyyə və ya dövlət müştəriləri üçün məlumat kənara çıxmamalıdır. Distilled student-i öz data center-ində işlətmək mümkündür. Bu, teacher API-sinin sərhəd keçid problemini həll edir.

### Control

Self-hosted student modeli sənin öz kontrolundədir:
- Versiyasını dondura bilərsən
- A/B test ilə trafikə bölə bilərsən
- Custom instrumentation əlavə edə bilərsən
- Teacher-in gözlənilməz "model upgrade"-lərindən azadsan

---

## 3. Black-Box vs White-Box Distillation

Distillation-ın iki əsas variantı var, teacher-ə necə girişin olduğundan asılı olaraq.

### Black-Box Distillation (API-based)

Yalnız teacher-in **text output-una** girişin var. Logits, intermediate activation-lar əlçatan deyil. Bu, Claude, GPT-4, Gemini kimi closed models üçün yeganə variantdır.

```
1. Prompt-lar yığ: 50,000 real production prompt və ya sintetik
2. Teacher-ə yollat: response-ları topla
3. Quality filter: zəif/uyğunsuz response-ları çıxart
4. Student-i (prompt, response) SFT data ilə öyrət
```

Üstünlük: Claude, GPT-4, Gemini kimi məhdud modellərdən işləyir.
Çatışmazlıq: signal daha zəifdir — yalnız hard labels (seçilmiş cavab), soft distribution yox.

### White-Box Distillation (Logit-based)

Teacher-in logit-lərinə (probability distribution over vocabulary) girişin var. Open-source modellərdə mümkündür (Llama, Mistral, Qwen).

```
Hər token üçün teacher logit-ləri əlçatandır:
  teacher_logits[t] = [3.2, -1.5, 0.8, 5.1, ...]   (vocab_size=32000)

Student öyrənir həm:
  - Hard label: teacher-in seçdiyi token (argmax)
  - Soft distribution: teacher-in bütün vocabulary üzərindəki ehtimalı
```

White-box distillation adətən black-box-dan 15-25% daha yaxşı nəticə verir, xüsusən difficult reasoning task-ları üçün. Sebəb: student, teacher-in "yerli" uncertainty-lərindən öyrənir — hansı token-lar arasında teacher tərəddüd edirdi, hansıları əmin seçirdi.

### Hybrid Yanaşma

Komandalar bəzən bu kombinasiyanı seçir:

```
1. Claude Sonnet-dən (teacher) 50k high-quality response topla
2. Qwen 2.5 72B-dən (open-source proxy teacher) həmin data üzərində
   logit-ləri çıxar
3. Llama 3.1 8B student-i həm text response-lara, həm də proxy
   teacher-in logit-lərinə görə öyrət
```

Bu, Claude-un kalitessini götürür, open-source logit signal ilə gücləndirilir.

---

## 4. Task-Specific vs General-Purpose Distillation

### Task-Specific Distillation (Dar Distillation)

Student yalnız bir və ya bir neçə task-da teacher-ə yaxınlaşmaq üçün öyrədilir. Məsələn:

- Müştəri dəstəyi chat
- SQL generation
- E-mail klassifikasiyası
- Code completion

Üstünlük: student kiçik qala bilər (1B-8B), bu tapşırıqda teacher-ə çox yaxınlaşa bilər (~95% quality).
Çatışmazlıq: öz sahəsindən çıxanda tez çöküşə uğrayır.

### General-Purpose Distillation (Geniş Distillation)

Student bütün use case-lərdə teacher-in yerini tutmağa çalışır. Haiku və GPT-4o-mini kimi modellər belə yaradılıb.

Üstünlük: çevikdir, bir çox tapşırıq üçün istifadə olunur.
Çatışmazlıq: 70-85% teacher quality-də qalır, ixtisaslaşmış task-larda dar modellər bundan üstün olur.

### Praktiki Tövsiyə

```
Sənin istifadə halın nədir?
│
├─ 1-3 konkret task?
│   → Task-specific distillation, kiçik student (1B-8B)
│   → Teacher quality-nin 92-97%-i əldə edilə bilər
│
├─ 5+ fərqli task?
│   → General-purpose distillation, orta student (13B-32B)
│   → Teacher quality-nin 75-85%-i
│
└─ Open-ended chatbot?
    → Distillation yox, direkt teacher istifadə et və ya
      instruction-tuned modeli fine-tune et
```

---

## 5. Riyaziyyat: KL Divergence və Temperature

Klassik distillation loss funksiyası KL divergence əsaslıdır.

### Hard Label vs Soft Label

Standart supervised training-də hard label istifadə olunur:

```
teacher_choice = "Paris"
student öyrənir: P(Paris | prompt) = 1.0
```

Distillation-da teacher-in bütün ehtimal paylanması istifadə olunur:

```
teacher distribution:
  P(Paris) = 0.82
  P(Lyon)  = 0.08
  P(Madrid) = 0.04
  P(Rome)  = 0.03
  ...
```

Student bu paylanmanı kopyalamağa çalışır.

### Temperature

Softmax-a bir temperature parametri əlavə edilir:

```
p_i = exp(z_i / T) / Σ exp(z_j / T)

T = 1: standart softmax
T > 1: paylanma "yumşalır" (daha uniform)
T < 1: paylanma "kəskinləşir"
```

Distillation zamanı T = 2-5 istifadə olunur. Bu, teacher-in incə fərqlərini daha aydın ifadə edir:

```
T = 1 ilə (kəskin):
  Paris: 0.97, Madrid: 0.02, others: 0.01

T = 4 ilə (yumşaq):
  Paris: 0.68, Madrid: 0.14, Lyon: 0.09, Rome: 0.05, others: 0.04
  ↑ indi student Madrid-i ikinci alternativ kimi öyrənə bilir
```

### Distillation Loss

Toplam loss iki hissədən ibarətdir:

```
L = α · L_CE(student, hard_label) + (1-α) · T² · KL(student_soft || teacher_soft)

Harada:
  L_CE — standart cross-entropy (ground truth ilə)
  KL   — KL divergence (teacher paylanması ilə)
  T²   — temperature-i compensate edir
  α    — iki loss arasında balans (tipik 0.5)
```

### PyTorch Implementation

```python
import torch
import torch.nn.functional as F

def distillation_loss(
    student_logits: torch.Tensor,   # [batch, seq_len, vocab]
    teacher_logits: torch.Tensor,   # [batch, seq_len, vocab]
    hard_labels: torch.Tensor,      # [batch, seq_len]
    temperature: float = 4.0,
    alpha: float = 0.5,
) -> torch.Tensor:
    # Hard loss (standart cross-entropy)
    hard_loss = F.cross_entropy(
        student_logits.view(-1, student_logits.size(-1)),
        hard_labels.view(-1),
        ignore_index=-100,
    )
    
    # Soft loss (KL divergence)
    student_soft = F.log_softmax(student_logits / temperature, dim=-1)
    teacher_soft = F.softmax(teacher_logits / temperature, dim=-1)
    
    soft_loss = F.kl_div(
        student_soft,
        teacher_soft,
        reduction="batchmean",
    ) * (temperature ** 2)
    
    # Birləşdir
    return alpha * hard_loss + (1 - alpha) * soft_loss
```

---

## 6. Data Generation Pipeline

Distillation-ın keyfiyyəti data keyfiyyəti ilə məhdudlaşır. Budur real pipeline:

```
┌─────────────────────────────────────────────────────────┐
│  Addım 1: Prompt Collection                             │
│  ─ Real production logları (tərcih edilən)              │
│  ─ Və ya sintetik generasiya (Claude ilə)               │
│  ─ Hədəf: 10k-100k unikal prompt                        │
└─────────────┬───────────────────────────────────────────┘
              │
┌─────────────▼───────────────────────────────────────────┐
│  Addım 2: Deduplication və Quality Filter               │
│  ─ Near-duplicate-ləri sil (MinHash/SimHash)            │
│  ─ Təhlükəli və ya qadağan edilmiş prompt-ları çıxart   │
│  ─ Token uzunluğu limiti tətbiq et                      │
└─────────────┬───────────────────────────────────────────┘
              │
┌─────────────▼───────────────────────────────────────────┐
│  Addım 3: Teacher Inference                             │
│  ─ Parallel batch (AsyncOpenAI, AsyncAnthropic)         │
│  ─ Temperature=0 və ya nizamlı sample                   │
│  ─ Hər prompt üçün 1 və ya k response                   │
│  ─ Xərc monitor et (bu pipeline-ın ən bahalı hissəsidir)│
└─────────────┬───────────────────────────────────────────┘
              │
┌─────────────▼───────────────────────────────────────────┐
│  Addım 4: Response Quality Filter                       │
│  ─ LLM-judge (fərqli teacher) qiymətləndirir            │
│  ─ Format compliance, hallucination check               │
│  ─ Rədd edilənləri ayır, saxla amma train-də istifadə et│
└─────────────┬───────────────────────────────────────────┘
              │
┌─────────────▼───────────────────────────────────────────┐
│  Addım 5: Train/Val Split                               │
│  ─ 90/10 və ya 95/5                                     │
│  ─ Held-out test set: heç training-də istifadə edilməsin│
└─────────────────────────────────────────────────────────┘
```

### Sintetik Prompt Generasiyası

Əgər real log-ların yoxdursa, sintetik generasiya edə bilərsən:

```python
from anthropic import AsyncAnthropic
import asyncio
import json

client = AsyncAnthropic()

SEED_TOPICS = [
    "order tracking", "refund request", "delivery delay",
    "product defect", "payment failed", "shipping to region",
    # ... 50+ başlıq
]

async def generate_prompt_for_topic(topic: str, count: int = 20) -> list[str]:
    response = await client.messages.create(
        model="claude-opus-4-7",
        max_tokens=4096,
        messages=[{
            "role": "user",
            "content": f"""Generate {count} realistic customer support prompts
on the topic '{topic}' in Azerbaijani. They should:
- Vary in length (short to paragraph)
- Include typos and informal language occasionally
- Cover different user personas (new, angry, confused, technical)
- Each prompt on new line, no numbering

Generate now:"""
        }],
    )
    text = response.content[0].text
    return [line.strip() for line in text.split("\n") if line.strip()]

async def main():
    tasks = [generate_prompt_for_topic(t, 40) for t in SEED_TOPICS]
    results = await asyncio.gather(*tasks)
    
    all_prompts = [p for batch in results for p in batch]
    print(f"Generated {len(all_prompts)} prompts")
    
    with open("prompts.jsonl", "w") as f:
        for p in all_prompts:
            f.write(json.dumps({"prompt": p}, ensure_ascii=False) + "\n")

asyncio.run(main())
```

### Teacher Response Yaratma (Parallel)

```python
import asyncio
from anthropic import AsyncAnthropic
from anthropic.types import MessageParam
import json
from pathlib import Path

client = AsyncAnthropic()
SEMAPHORE = asyncio.Semaphore(20)  # rate limit üçün

SYSTEM_PROMPT = """You are a professional Azerbaijani customer service 
representative. Always be polite, helpful, and accurate. Respond in Azerbaijani."""

async def get_teacher_response(prompt: str) -> dict | None:
    async with SEMAPHORE:
        try:
            resp = await client.messages.create(
                model="claude-sonnet-4-7",
                max_tokens=1024,
                system=SYSTEM_PROMPT,
                messages=[{"role": "user", "content": prompt}],
                temperature=0.3,
            )
            return {
                "prompt": prompt,
                "response": resp.content[0].text,
                "model": "claude-sonnet-4-7",
                "usage": {
                    "input_tokens": resp.usage.input_tokens,
                    "output_tokens": resp.usage.output_tokens,
                },
            }
        except Exception as e:
            print(f"Error on prompt: {e}")
            return None

async def main():
    prompts = [
        json.loads(line)["prompt"]
        for line in Path("prompts.jsonl").read_text().splitlines()
    ]
    
    tasks = [get_teacher_response(p) for p in prompts]
    
    output_path = Path("teacher_responses.jsonl")
    with output_path.open("w") as f:
        for coro in asyncio.as_completed(tasks):
            result = await coro
            if result:
                f.write(json.dumps(result, ensure_ascii=False) + "\n")
                f.flush()

asyncio.run(main())
```

Real-dünya qeydi: 50,000 prompt × 500 token cavab × $15/M output = **$375 data generation xərci** Claude Sonnet üçün. Bu xərc distillation layihəsinin başlanğıc investisiyasıdır.

---

## 7. Anthropic və OpenAI-ın Daxili Praktikası

### Anthropic: Opus → Sonnet → Haiku

Anthropic-in model ailəsinin üçlü strukturu distillation əsasında qurulub:

```
Opus (ən böyük, ən ağıllı)
  │
  │ distillation training signal
  ▼
Sonnet (balanslı, əksər use case-lər)
  │
  │ distillation + ixtisar
  ▼
Haiku (sürətli, ucuz)
```

Hər model ailəsi həmin ailənin böyük variantından öyrənir. Bu, Anthropic-in "Claude-un ailə dəyərlərinin" bütün modellərdə bərabər tutmasının əsas səbəbidir. Haiku-nun neçə parametrli olduğu rəsmi açıqlanmayıb, amma təxminlər 10-20B aralığındadır.

### OpenAI: GPT-4o → GPT-4o-mini

OpenAI 2024-cü ildə "Distillation API" buraxdı:

```python
from openai import OpenAI

client = OpenAI()

# Real istifadədə production logları yığ
completion = client.chat.completions.create(
    model="gpt-4o",
    messages=[...],
    store=True,              # ← production log store
    metadata={"project": "support-bot"},
)

# Sonradan distillation job başlad
job = client.fine_tuning.jobs.create(
    training_file=None,
    method={
        "type": "distillation",
        "distillation": {
            "teacher_model": "gpt-4o-2024-08-06",
            "stored_completions_source": {
                "metadata": {"project": "support-bot"},
                "limit": 50000,
            },
        },
    },
    model="gpt-4o-mini-2024-07-18",
)
```

Bu, OpenAI-ın "platformada" student model yaratma yanaşmasıdır — teacher-in tam logit-lərinə girişi var (white-box) çünki hər iki model onların infrastrukturundadır.

### DeepSeek R1 → Kiçik Variantlar

DeepSeek-R1 release-i ilə birlikdə R1-dən distill edilmiş kiçik variantları da buraxdı:

```
DeepSeek-R1 (671B)
  │
  ├─ DeepSeek-R1-Distill-Llama-70B
  ├─ DeepSeek-R1-Distill-Qwen-32B
  ├─ DeepSeek-R1-Distill-Qwen-14B
  ├─ DeepSeek-R1-Distill-Qwen-7B
  └─ DeepSeek-R1-Distill-Llama-8B
```

Bu variantlar reasoning chain-of-thought (CoT) distillation ilə öyrədilib. Student yalnız final cavabı deyil, eyni zamanda CoT "düşüncə" prosesini də təkrarlayır.

---

## 8. Real Case Study: Llama 70B → 8B

**Ssenari**: Fintech şirkəti, Llama 3.1 70B fine-tuned modeli ilə transaction classification edir. Serving xərci ayda $15k. Komanda 8B-yə distill etməyi planlaşdırır.

### Hazırlıq

```
Teacher: Llama 3.1 70B (fine-tuned, domain-adapted)
Student: Llama 3.1 8B (base)
Task: Multi-class transaction classification (47 kateqoriya)
Data: 500k real transaction logs (anonimləşdirilmiş)
```

### Distillation Run

```python
from unsloth import FastLanguageModel
import torch
import torch.nn.functional as F

# Teacher-i yüklə (bf16, inference-only)
teacher, teacher_tokenizer = FastLanguageModel.from_pretrained(
    model_name="company/llama-70b-transaction-classifier",
    max_seq_length=1024,
    load_in_4bit=False,       # teacher tam dəqiqlikdə
)
FastLanguageModel.for_inference(teacher)

# Student-i QLoRA ilə hazırla
student, tokenizer = FastLanguageModel.from_pretrained(
    model_name="meta-llama/Llama-3.1-8B",
    max_seq_length=1024,
    load_in_4bit=True,
)
student = FastLanguageModel.get_peft_model(
    student,
    r=32,
    lora_alpha=64,
    target_modules="all-linear",
)

# Custom training loop (teacher logit-ləri çıxarmaq üçün)
from torch.utils.data import DataLoader

def distillation_step(batch, temperature=4.0, alpha=0.5):
    with torch.no_grad():
        teacher_logits = teacher(**batch).logits
    
    student_logits = student(**batch).logits
    
    # Hard loss (ground truth labels)
    hard_loss = F.cross_entropy(
        student_logits.view(-1, student_logits.size(-1)),
        batch["labels"].view(-1),
        ignore_index=-100,
    )
    
    # Soft loss (teacher distribution)
    T = temperature
    student_soft = F.log_softmax(student_logits / T, dim=-1)
    teacher_soft = F.softmax(teacher_logits / T, dim=-1)
    soft_loss = F.kl_div(student_soft, teacher_soft, reduction="batchmean") * T * T
    
    return alpha * hard_loss + (1 - alpha) * soft_loss

optimizer = torch.optim.AdamW(student.parameters(), lr=2e-4)
for epoch in range(3):
    for batch in dataloader:
        loss = distillation_step(batch)
        loss.backward()
        optimizer.step()
        optimizer.zero_grad()
```

### Nəticələr

```
Metric                          | 70B Teacher | 8B Student | Delta
─────────────────────────────────────────────────────────────────
Classification accuracy         | 94.2%       | 91.8%      | -2.4%
F1 score (weighted)             | 0.941       | 0.914      | -0.027
Inference latency (p50)         | 280ms       | 45ms       | -84%
Throughput (requests/GPU/sec)   | 18          | 240        | +1233%
Monthly serving cost            | $15,000     | $1,800     | -88%
```

Classification accuracy-də 2.4% itki **bu şirkət üçün qəbul edilə bilən idi**, çünki aylıq $13k qənaət təmin etdi. Bu qərar context-dən asılıdır — başqa komanda üçün 2.4% qəbul edilməz ola bilər.

---

## 9. Kod: Claude Sonnet-dən Llama 8B-yə Distillation

Tam end-to-end nümunə (black-box distillation):

```python
# distill.py
import json
from pathlib import Path
from datasets import Dataset
from trl import SFTTrainer, SFTConfig
from unsloth import FastLanguageModel
from unsloth.chat_templates import get_chat_template

# 1. Teacher response data-nı yüklə (əvvəlcədən Claude-dan toplanmış)
def load_teacher_data(path: str) -> Dataset:
    records = []
    for line in Path(path).read_text().splitlines():
        item = json.loads(line)
        records.append({
            "conversations": [
                {"role": "system", "content": "Siz Azərbaycanlı müştərilərə kömək edirsiniz."},
                {"role": "user", "content": item["prompt"]},
                {"role": "assistant", "content": item["response"]},
            ],
        })
    return Dataset.from_list(records)

# 2. Student-i hazırla
student, tokenizer = FastLanguageModel.from_pretrained(
    model_name="unsloth/Meta-Llama-3.1-8B-Instruct-bnb-4bit",
    max_seq_length=4096,
    load_in_4bit=True,
)

student = FastLanguageModel.get_peft_model(
    student,
    r=32,
    lora_alpha=64,
    lora_dropout=0.05,
    target_modules="all-linear",
    use_gradient_checkpointing="unsloth",
    random_state=3407,
)

tokenizer = get_chat_template(tokenizer, chat_template="llama-3.1")

# 3. Data-nı formatla
def format_chat(examples):
    texts = []
    for convo in examples["conversations"]:
        text = tokenizer.apply_chat_template(
            convo, tokenize=False, add_generation_prompt=False
        )
        texts.append(text)
    return {"text": texts}

train_dataset = load_teacher_data("teacher_responses_train.jsonl")
eval_dataset = load_teacher_data("teacher_responses_eval.jsonl")

train_dataset = train_dataset.map(format_chat, batched=True)
eval_dataset = eval_dataset.map(format_chat, batched=True)

# 4. Train
trainer = SFTTrainer(
    model=student,
    tokenizer=tokenizer,
    train_dataset=train_dataset,
    eval_dataset=eval_dataset,
    dataset_text_field="text",
    max_seq_length=4096,
    packing=True,
    args=SFTConfig(
        output_dir="outputs/az-support-student",
        num_train_epochs=3,
        per_device_train_batch_size=4,
        gradient_accumulation_steps=4,
        learning_rate=2e-4,
        lr_scheduler_type="cosine",
        warmup_ratio=0.03,
        bf16=True,
        optim="paged_adamw_8bit",
        logging_steps=20,
        save_strategy="epoch",
        eval_strategy="steps",
        eval_steps=200,
        report_to=["wandb"],
        run_name="distill-claude-to-llama8b",
    ),
)

trainer.train()

# 5. Save
student.save_pretrained_merged(
    "az-support-student-8b",
    tokenizer,
    save_method="merged_16bit",
)
```

Bu, black-box distillation-dır — sadəcə SFT teacher response-larına uyğunlaşmaqdır. White-box üçün custom training loop lazım olar (əvvəlki bölməyə bax).

---

## 10. Quality Gap Həqiqəti

Distillation sehr deyil. Student adətən teacher-i tam əvəz edə bilmir, xüsusən:

### Narrow task-larda: Student 92-98% quality

```
Task: SQL generation (specific schema)
Teacher (GPT-4o):    exec accuracy 89.3%
Student (7B):        exec accuracy 86.7%  (-2.6%)
```

Real use case üçün kifayətdir.

### Ümumi chat-də: Student 70-85% quality

```
Task: Open-ended chat (MT-Bench)
Teacher (GPT-4):     8.99
Student (7B):        6.87  (-24%)
```

Çox fərqlidir. Edge case-lərdə daha böyük düşüş ola bilər.

### Reasoning-də: Daha çətin

```
Task: GSM8K (math word problems)
Teacher (R1):        95.1%
Student (7B distill): 82.1%  (-13%)
Student (32B distill): 92.3% (-2.8%)
```

Kiçik student-lər reasoning chain-lərini tam ifa edə bilmir. Bu üçün daha böyük student (32B+) lazımdır.

### Qanun: Student Heç Vaxt Teacher-i Keçmir

Əsas qayda: student teacher-in peak performansını keçə bilmir. Teacher-in "bilmədiyini" student öyrənə bilmir. Buna görə **teacher seçimi kritikdir** — zəif teacher ilə distill etmə.

Lakin student teacher-dən bir şeydə daha yaxşı ola bilər: **öz darağımzdır sahəsində ardıcıllıq**. Teacher geniş istifadə halı üçün tuned olduğu üçün bəzən sizin darağımzdır use case-də variable nəticə verə bilər. Student, yalnız sizin distillation data-nıza tuned olduğu üçün, həmin datada daha ardıcıl davranır.

---

## 11. Xərc Müqayisəsi: Teacher API vs Student Serving

Distillation ROI analizi üçün nümunə:

```
Scenario: SaaS şirkəti, 2M inference/ay ehtiyacı

────────────────────────────────────────────────────────
Variant A: Teacher API (Claude Sonnet) hər şey üçün
────────────────────────────────────────────────────────
Data generation xərci: $0 (ayrı distillation yox)
Aylıq inference: 2M × ($0.003 + $0.015) = $36,000
Yearly: $432,000


────────────────────────────────────────────────────────
Variant B: Distilled Student (8B self-hosted)
────────────────────────────────────────────────────────
ONE-TIME:
  Data generation (50k prompt teacher çağırışı):  $750
  Distillation training (2× A100 × 6 saat):        $25
  Engineering effort (2 week, 1 senior):           $6,000
  Evaluation & A/B setup:                          $3,000
  Total one-time:                                  $9,775

MONTHLY:
  2× A100 80GB serving (24/7):                     $2,722
  Monitoring & maintenance:                        $1,500
  Total monthly:                                   $4,222
  Yearly:                                          $50,664

Yearly savings vs Variant A: $381,336
Break-even: ~11 days


────────────────────────────────────────────────────────
Variant C: Hybrid (Student 90% + Teacher fallback 10%)
────────────────────────────────────────────────────────
Student serving: $4,222/ay
Teacher fallback (200k × rate): $3,600/ay
Total: $7,822/ay
Yearly: $93,864

Üstünlük: complex query-lər hələ də teacher-ə yollanır
         keyfiyyət kompromisi minimal
```

Variant C (Hybrid) reallıqda ən çox görünən pattern-dir — kiçik, "asan" query-lər student-ə, mürəkkəb olanlar teacher-ə gedir.

### Hybrid Routing Nümunəsi

```python
class HybridRouter:
    def __init__(self, student_client, teacher_client):
        self.student = student_client
        self.teacher = teacher_client
        self.classifier = load_complexity_classifier()
    
    async def respond(self, prompt: str) -> str:
        complexity = self.classifier.predict(prompt)
        
        if complexity == "simple":
            # 90% trafik buradan keçir
            return await self.student.complete(prompt)
        
        if complexity == "complex":
            # 10% trafik, amma kritik
            return await self.teacher.complete(prompt)
        
        # Uncertain: ikisini paralel çağır, hard judge et
        student_result, teacher_result = await asyncio.gather(
            self.student.complete(prompt),
            self.teacher.complete(prompt),
        )
        return teacher_result  # uncertain hallarda teacher-ə üstünlük
```

---

## 12. Failure Modes

Distillation layihələrində tez-tez qarşılaşılan problemlər:

### 1. Student Teacher-in Səhvlərini Yadda Saxlayır

Teacher hallucinates (uydurur) — student da eyni hallucinationları kopyalayır, amma teacher-dən daha güclü şəkildə. Bu, "error amplification" deyilir.

**Həll**: Teacher response-larını LLM-judge ilə filter et. 5-10% uyğunsuz response-ları sil.

### 2. Distribution Shift

Training data sintetik prompt-lardır, amma production-da real istifadəçi başqa sözlər, başqa dil, başqa format istifadə edir. Student real prompt-larda çöküşə uğrayır.

**Həll**: Production prompt-larını minimum 50% data mix-ində saxla.

### 3. Teacher Stylistic Quirks

Teacher hər cavabı "Əla sual!" ilə başlayırsa (yaltaqlıq — RLHF artefaktı), student də bunu edəcək. Daha pis: student yaltaqlığın 2× gücündə edəcək.

**Həll**: Teacher response-larını post-process et, sistemli prefix-ləri sil.

### 4. Catastrophic Forgetting

8B student-ə task-specific distillation edirsən, amma student əvvəlcə ümumi dil qabiliyyətlərini itirir.

**Həll**: 
- Mixed training: 70% task data + 30% ümumi instruction data
- LoRA əvəzinə tam fine-tuning istifadə etmə (əgər lazım olmasa)

### 5. Model Collapse (Recursive Distillation)

Student-dən yenidən teacher kimi istifadə edib daha kiçik student yaratsan, keyfiyyət eksponensial düşür. Hər generasiya 10-20% keyfiyyət itkisi.

**Həll**: Həmişə real, yaxşı teacher-ə qayıt. Student-dən distill etmə.

### 6. Evaluation Data Leakage

Training set-də yoxlama (evaluation) data-sı mövcuddursa, nəticələr süni yüksək görünəcək. Production-da isə bu görünməyəcək.

**Həll**:
- Eval set-i ayrıca time cut ilə yığ
- MinHash ilə training-də evaluation prompt-larını axtar və sil
- Human review edilmiş held-out test set saxla

---

## 13. Qiymətləndirmə: Teacher-as-Judge Problemi

**Sual**: Student qiymətləndirmək üçün teacher-i judge olaraq istifadə edə bilərəmmi?

**Cavab**: Ehtiyatla. Aşağıdakı problemlər var:

### Teacher Öz Tərzini Üstün Tutur

Claude Sonnet Claude response-larını daha yüksək qiymətləndirir. GPT-4 isə GPT formatındakı response-ları. Buna görə teacher-as-judge distilled student-i qərəzli şəkildə aşağı qiymətləndirir.

### Həll: Üç Vəkili Sistem

```
Student response
      │
      ▼
┌─────────────┐  ┌──────────────┐  ┌──────────────┐
│Claude Opus  │  │  GPT-4o      │  │  Qwen 2.5    │
│  as judge   │  │  as judge    │  │  as judge    │
└──────┬──────┘  └──────┬───────┘  └──────┬───────┘
       │                │                  │
       └────────────────┼──────────────────┘
                        ▼
                Majority vote və ya mean score
```

### Daha Yaxşı: Human Eval + LLM Judge

```
1. Production sample-dən 500 prompt götür
2. Student və teacher-in hər birini çağır
3. İnsan annotator-lar 100-ünü qiymətləndirir (absolute score)
4. LLM-judge qalanları qiymətləndirir
5. LLM-judge scoring-i human scoring ilə korrelasiya edirsə etibar et
```

### Metriklər Beyond Accuracy

Yalnız accuracy və ya F1 deyil:

```python
evaluation_metrics = {
    # Keyfiyyət
    "accuracy": ...,
    "f1_weighted": ...,
    "llm_judge_score": ...,     # 1-5 scale
    
    # Format
    "format_compliance_rate": ..., # JSON parse, struktur uyğunluğu
    "length_deviation": ...,       # teacher vs student uzunluq fərqi
    
    # Behavior
    "refusal_rate": ...,           # lazımsız red
    "hallucination_rate": ...,     # uydurma məlumat
    "sycophancy_rate": ...,        # yaltaqlıq
    
    # Operational
    "latency_p50_ms": ...,
    "latency_p99_ms": ...,
    "throughput_rps": ...,
    "vram_peak_mb": ...,
}
```

---

## 14. Production Deployment və A/B Test

Distilled student production-a çıxarma strategiyası:

### Mərhələli Rollout

```
Həftə 1: Shadow mode
  ─ Hər production request həm teacher-ə, həm student-ə gedir
  ─ İstifadəçi yalnız teacher response görür
  ─ Student response loqlanır, müqayisə üçün saxlanır
  ─ Goal: production-da divergence-i ölç

Həftə 2: Canary 5%
  ─ 5% trafik student-ə
  ─ CSAT, response time, error rate monitor
  ─ Automatic fallback teacher-ə əgər student error rate > 2%

Həftə 3: 25%
  ─ Metriklər yaxşıdırsa genişləndir
  ─ Edge case-ləri axtar

Həftə 4: 50%-75%
  ─ A/B təhlil: keyfiyyət fərqi statistical significant-dirmi?
  ─ Business metric: CSAT, conversion, MRR

Həftə 5+: 95%+ (hybrid routing)
  ─ Student əksəriyyətini həll edir
  ─ Teacher yalnız complexity classifier "complex" dediyi hallar üçün
```

### Monitoring və Fallback

```python
from dataclasses import dataclass
import time

@dataclass
class InferenceResult:
    response: str
    model: str
    latency_ms: float
    confidence: float | None = None

class StudentWithFallback:
    def __init__(self, student, teacher, max_student_latency_ms=500):
        self.student = student
        self.teacher = teacher
        self.max_latency = max_student_latency_ms
        self.student_failures = 0  # circuit breaker
    
    async def infer(self, prompt: str) -> InferenceResult:
        if self.student_failures > 10:
            # Circuit open — birbaşa teacher-ə get
            return await self._call_teacher(prompt)
        
        try:
            start = time.perf_counter()
            result = await asyncio.wait_for(
                self.student.complete(prompt),
                timeout=self.max_latency / 1000,
            )
            latency = (time.perf_counter() - start) * 1000
            
            self.student_failures = max(0, self.student_failures - 1)
            return InferenceResult(
                response=result.text,
                model="student-v1.2",
                latency_ms=latency,
                confidence=result.confidence,
            )
        except (asyncio.TimeoutError, Exception) as e:
            self.student_failures += 1
            return await self._call_teacher(prompt)
    
    async def _call_teacher(self, prompt: str) -> InferenceResult:
        start = time.perf_counter()
        result = await self.teacher.complete(prompt)
        return InferenceResult(
            response=result.text,
            model="teacher-claude-sonnet",
            latency_ms=(time.perf_counter() - start) * 1000,
        )
```

### Rollback Strategy

Student model pisləşirsə:

```
1. Immediate: Load balancer weight-ləri təzələ
   student: 90% → 0%
   teacher: 10% → 100%

2. Root cause analysis:
   ─ Hansı use case-lər regress etdi?
   ─ Distribution shift mövcuddurmu?
   ─ Adversarial prompt varmı?

3. Fix:
   ─ Training data-ya problematic case-lər əlavə et
   ─ Retrain student
   ─ Shadow mode-da test et
   ─ Yenidən canary
```

---

## 15. Reasoning Distillation: R1 və CoT

2025-ci ilin böyük trendi: **reasoning model distillation**. DeepSeek R1 buraxıldıqdan sonra geniş yayılıb.

### Chain-of-Thought (CoT) Distillation

Klassik distillation-da student yalnız final response-u öyrənir. CoT distillation-da student həm **thinking trace**-i (reasoning process), həm də final response-u öyrənir.

```
Klassik distillation data:
{
  "prompt": "Ivan 5 alma aldı, 2-ni yedi, 3 daha aldı. Neçə alma var?",
  "response": "Ivan-da 6 alma var."
}

CoT distillation data:
{
  "prompt": "Ivan 5 alma aldı, 2-ni yedi, 3 daha aldı. Neçə alma var?",
  "thinking": "Əvvəlcə 5 alma. 2-ni yeməyindən sonra: 5 - 2 = 3. Sonra 3 daha: 3 + 3 = 6. Yekun: 6 alma.",
  "response": "Ivan-da 6 alma var."
}
```

Student, thinking prosessinin özünü öyrənir. Bu, aşağı parametrli modellərin math, coding, reasoning task-larında dramatik yaxşılaşmaqlarına səbəb oldu.

### DeepSeek-R1-Distill Workflow

```
1. Teacher (R1): promptu həll et, tam thinking trace çıxart
2. Data format:
   <think>
   Long reasoning trace...
   </think>
   Final answer.
3. Student-i bu data-da SFT ilə öyrət
4. Student yaradır thinking + answer — small model-də reasoning!
```

### Python Nümunə (R1 → 7B distillation)

```python
from unsloth import FastLanguageModel

# Distilled student-i yüklə (zatən R1 distillation edilib)
model, tokenizer = FastLanguageModel.from_pretrained(
    "unsloth/DeepSeek-R1-Distill-Qwen-7B-bnb-4bit",
    max_seq_length=8192,
    load_in_4bit=True,
)
FastLanguageModel.for_inference(model)

# Inference — thinking traces ilə
prompt = "Ivan 5 alma aldı, 2-ni yedi, 3 daha aldı. Neçə alma var?"
messages = [{"role": "user", "content": prompt}]
inputs = tokenizer.apply_chat_template(
    messages, tokenize=True, return_tensors="pt", add_generation_prompt=True,
).to("cuda")

outputs = model.generate(
    inputs,
    max_new_tokens=2048,
    temperature=0.6,    # R1 tövsiyəsi
    do_sample=True,
)

response = tokenizer.decode(outputs[0], skip_special_tokens=False)
print(response)
# Output:
# <think>
# Ivan əvvəlcə 5 alma aldı. Sonra 2-ni yedi, beləliklə 5-2=3.
# Sonra 3 alma daha aldı, beləliklə 3+3=6.
# </think>
# 6 alma var.
```

Bu yanaşma sayəsində 7B model, GSM8K-da 82% accuracy-ə çatır — əvvəl yalnız 70B+ modellər bu nəticəni göstərirdi.

### Öz CoT Distillation-nı Yaratma

```python
async def collect_cot_data(prompts: list[str]) -> list[dict]:
    records = []
    for prompt in prompts:
        response = await claude.messages.create(
            model="claude-opus-4-7",
            max_tokens=8192,
            system="Think step by step inside <think></think> tags, "
                   "then provide final answer.",
            messages=[{"role": "user", "content": prompt}],
        )
        text = response.content[0].text
        
        # <think>...</think> və answer ayır
        import re
        match = re.match(r"<think>(.*?)</think>\s*(.*)", text, re.DOTALL)
        if match:
            thinking, answer = match.group(1).strip(), match.group(2).strip()
            records.append({
                "prompt": prompt,
                "thinking": thinking,
                "answer": answer,
                "formatted": f"<think>\n{thinking}\n</think>\n{answer}",
            })
    return records
```

---

## 16. Xülasə

| Sual | Qısa Cavab |
|---|---|
| Distillation nə zaman istifadə edim? | Teacher API xərci ayda >$10k və ya latency < 200ms tələb olunur |
| Black-box, yoxsa white-box? | API teacher (Claude/GPT): black-box. Open-source: white-box |
| Necə teacher seçim? | Ən güclü əlçatan model. Zəif teacher ilə distillation dəyməz |
| Student ölçüsü nə olsun? | Task darağımzdır: 1B-8B. Geniş task: 13B-32B. Reasoning: 32B+ |
| Data necə toplayım? | Real production logları ən yaxşısıdır. Sintetik əlavə et |
| Neçə nümunə lazımdır? | Task-spec: 10k-50k. General: 100k+. CoT reasoning: 50k+ |
| Xərc nə qədərdir? | Data generation: $500-$5k. Training: $50-$500. Engineering: 2-4 hərəftə |
| ROI nə vaxt gəlir? | Yüksək volume use case-lərdə 1-2 ay ərzində |
| Quality kompromisi? | Task-spec: -2-8%. General chat: -15-25% |
| Necə qiymətləndirim? | LLM-judge + human eval + production A/B |
| Production-a necə çıxarım? | Shadow → Canary → Gradual rollout + circuit breaker |
| Nə zaman distillation etməməliyəm? | Keyfiyyət hər şeydən vacibdir, data yoxdur, use case dəyişkəndir |

---

## Əsas Nəticələr

1. **Distillation inferens xərcini 10-100× aşağı salır** — amma yalnız yüksək volume use case-lərdə dəyir. Aşağı volume-də teacher API daha sadə və ucuzdur.

2. **Student teacher-i keçə bilmir.** Teacher seçimi distillation keyfiyyətinin sərhədini təyin edir. Zəif teacher ilə vaxt itirmə.

3. **Narrow task üçün kiçik student möcüzə göstərə bilir** — 92-98% teacher keyfiyyətini 5-10% ölçüdə. General chat üçün fərq böyük olur.

4. **Hybrid routing əksər production sistemlərinin cavabıdır.** 90% trafik student-ə, 10% complex case-lər teacher-ə. Ən yaxşı qiymət/keyfiyyət balansı.

5. **Reasoning distillation 2025-in oyun dəyişdiricisidir.** DeepSeek R1 → Llama 8B/Qwen 7B distillation kiçik modellərin math və coding task-larında necə davrandığını inqilabi şəkildə dəyişdirdi.

6. **Teacher-as-judge qərəzlidir.** Üçlü judge sistemindən istifadə et və ya real insan qiymətləndirməsini saxla.

7. **Production-a qatı rollback hazırlığı olmadan çıxarma.** Circuit breaker, canary, shadow mode — bunların hamısı distillation rollout-un ayrılmaz hissəsidir.

8. **Data keyfiyyəti distillation-ın ürəyidir.** Teacher response-larını LLM-judge ilə filter et. 5-10% keyfiyyətsiz data bütün layihəni zədələyə bilər.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Teacher-Student Pipeline

Claude Sonnet (teacher) ilə 500 domain sorğusunu cavabla, nəticəni saxla. Bu dataset-i Llama-3.2-1B (student) modeli üçün fine-tuning dataseti kimi istifadə et. Student model accuracy-sini baseline (pretrained) vs distilled versiyada müqayisə et.

### Tapşırıq 2: Quality Filter

Teacher response-larını LLM-as-judge ilə filter et: 4/5-dən aşağı cavabları dataset-dən çıxar. Filter olmadan vs olduqda student model-in accuracy-sini müqayisə et. 10% keyfiyyətsiz data-nın student performance-a təsirini ölç.

### Tapşırıq 3: Cost-Quality Trade-off

3 scenario müqayisə et: (a) Claude Sonnet direkt istifadə, (b) distilled 3B model, (c) distilled 1B model. Hər biri üçün 1000 sorğu üzərindən: accuracy, latency, cost per query. Student model hansı accuracy threshold-da teacher modelini əvəz edə bilər?

---

## Əlaqəli Mövzular

- `04-lora-qlora-peft.md` — Student modelin efficient fine-tuning-i
- `05-create-custom-model-finetune.md` — Full fine-tuning pipeline
- `08-ft-dataset-curation.md` — Distillation dataset-inin keyfiyyət idarəsi
- `09-vllm-model-serving.md` — Distilled modeli production-da serve et
