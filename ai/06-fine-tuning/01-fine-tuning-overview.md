# LLM-lərin İncə Tənzimlənməsi: Tam Bələdçi (Senior) ⭐⭐⭐

## İncə Tənzimləmə Nədir?

İncə tənzimləmə — **əvvəlcədən öyrədilmiş modelin öz məlumat dəstinizdə öyrənməsini davam etdirmə** prosesidir ki, onun çəkilərini xüsusi davranışa yönəldsin. Əsas model artıq dili və ümumi mühakiməni "bilir" — incə tənzimləmə həmin qabiliyyəti yenidən yönəldir.

Analogiya: Əvvəlcədən öyrədilmiş model bütün tibb üzrə 12 il oxumuş ümumiçi həkimdir. İncə tənzimləmə, onları kardiologiya üzrə ixtisaslaşdıran 6 aylıq rezidenturadır. Ümumi tibbi unutmurlar — yalnız ixtisaslarında çox daha yaxşı olurlar.

### Öyrənmə Yığını

```
┌─────────────────────────────────────────────────────┐
│                  ÖN ÖYRƏNMƏ                         │
│                                                     │
│  Korpus: internetdən trilyonlarla token             │
│  Məqsəd: növbəti tokeni proqnozlaşdır               │
│  Nəticə: ümumi dil anlayışı                         │
│  Xərc: $10M – $100M+                               │
└────────────────────────┬────────────────────────────┘
                         │ (başlanğıc nöqtəsi)
┌────────────────────────▼────────────────────────────┐
│           TƏLİMAT İNCƏ TƏNZİMLƏMƏSİ                │
│          (Nəzarətli İncə Tənzimləmə / SFT)          │
│                                                     │
│  Məlumat dəsti: təlimat-cavab cütləri              │
│  Məqsəd: təlimatları düzgün yerinə yetir            │
│  Nəticə: söhbət/təlimat yerinə yetirmə modeli      │
│  Xərc: $1K – $50K                                  │
└────────────────────────┬────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────┐
│              UYĞUNLAŞDIRMA (RLHF/DPO)               │
│                                                     │
│  Məlumat dəsti: insan üstünlük müqayisələri         │
│  Məqsəd: faydalı, zərərsiz, dürüst ol              │
│  Nəticə: son yerləşdirilmiş model                   │
│  Xərc: $10K – $500K                                │
└────────────────────────┬────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────┐
│          SİZİN İNCƏ TƏNZİMLƏMƏNİZ (İSTƏĞƏ BAĞLI)  │
│                                                     │
│  Məlumat dəsti: sahəyə xas nümunələriniz           │
│  Məqsəd: xüsusi davranışınız/formatınız/üslubunuz  │
│  Nəticə: istifadə halınız üçün xüsusi model        │
│  Xərc: $10 – $10K (bulud GPU-da LoRA istifadə edərək) │
└─────────────────────────────────────────────────────┘
```

---

## İncə Tənzimləmənin Növləri

### Tam İncə Tənzimləmə

Bütün model çəkiləri yenilənir. Şəbəkədəki hər bir parametri öyrədirsiz.

- **Üstünlüklər**: maksimum çeviklik, ən yaxşı performans tavanı
- **Çatışmazlıqlar**: hər incə tənzimləmə üçün modelin tam surətini saxlamaq tələb olunur, böyük hesablama xərci, fəlakətli unutma riski
- **Nə zaman**: milyonlarla nümunə ilə nəhəng sahə-xas məlumatınız və ayrılmış infrastrukturunuz var

70B parametrli model üçün: yalnız çəkiləri saxlamaq üçün ~140GB GPU yaddaşı tələb olunur, üstəgəl gradient yaddaşı. Minimum 4-8 ədəd A100 80GB GPU lazımdır.

### LoRA (Aşağı Ranqlı Uyğunlaşma)

LoRA — əksər praktik istifadə halları üçün üstün incə tənzimləmə üsuludur. Əsas anlayış: **incə tənzimləmə yeniləmələri aşağı ranqlıdır** — hər çəki matrisinə edilən dəyişikliklər iki daha kiçik matrisə parçalana bilər.

```
Orijinal:  W  (d × d matris, milyonlarla parametr)

LoRA əlavə edir: ΔW = A × B
                 A: (d × r)
                 B: (r × d)
                 burada r << d  (r ranqı, məs., r=8 və ya r=16)

Öndən keçid: output = W·x + (A·B)·x × miqyas
```

**Öyrənmə zamanı**: Yalnız A və B yenilənir (adətən ümumi parametrlərin 0.1-1%-i).
**Nəticə çıxarma üçün**: `W + A·B`-ni geri W-yə birləşdirin — sıfır nəticə çıxarma yükü.

**Yaddaş azalması**: 7B model üçün r=16 ilə LoRA tam incə tənzimləmə üçün 7B-ə qarşı ~4M parametr öyrədir. Tək A100 40GB-a sığır.

**Hiperarametri `r` (ranq)**:
- r=4: minimal dəyişiklik, üslub/format üçün istifadə edin
- r=16: əksər tapşırıqlar üçün yaxşı tarazlıq
- r=64: əhəmiyyətli davranış dəyişikliyi
- r=128+: tam incə tənzimləmə ərazisinə yaxınlaşır

**Alpha (α)**: LoRA yeniləmələri üçün miqyaslama faktoru. Konvensiya: `α = 2r` (alpha = 2 × ranq). Tətbiq olunan faktiki miqyas `α/r`-dir.

### QLoRA (Kvantlı LoRA)

QLoRA, LoRA-nı əsas modelin 4-bitlik kvantlaşdırılması ilə birləşdirir. Əsas çəkilər 4-bitlik tam ədədlərə (NF4 formatı) sıxışdırılır, yaddaşı ~4 dəfə azaldır, LoRA adapterləri isə 16-bitdə öyrədilir.

```
Əsas model çəkiləri: 4-bitlik NF4 (donmuş)
LoRA adapterləri:    16-bitlik bfloat16 (öyrəniləcək)

Yaddaş: 7B model ~6GB GPU RAM-da sığır
        (16-bitlik nəticə çıxarma üçün ~28GB idi)
```

**Uçuşda dekvantlaşdırma**: öndən keçid zamanı çəkilər 16-bitə dekvantlaşdırılır, əməliyyat yerinə yetirilir, nəticə LoRA yeniləməsi üçün istifadə olunur. Əsas çəkilərin özü heç vaxt dəyişdirilmir.

**Mübadilə**: 4 dəfə yaddaş qənaəti müqabilində tam dəqiqlikdən ~1-2% performans enişi. Adətən dəyər.

### PEFT (Parametr-Effektiv İncə Tənzimləmə)

PEFT — parametrlərin yalnız kiçik bir hissəsini yeniləyən metodlar üçün ümumi termindir:

| Metod | Yanaşma | Yaddaş | Performans |
|---|---|---|---|
| LoRA | Aşağı ranqlı matris parçalanması | Aşağı | Əla |
| QLoRA | LoRA + 4-bitlik kvantlaşdırma | Çox aşağı | Çox yaxşı |
| Prefiks Tənzimlənməsi | Öyrəniləcək tokenlar əlavə edin | Aşağı | Yaxşı |
| Prompt Tənzimlənməsi | Yumşaq prompt optimallaşdırması | Çox aşağı | Orta |
| (IA)³ | Aktivləşmələri miqyaslandır | Çox aşağı | Yaxşı |

Əksər praktikantlar üçün: **QLoRA standart seçimdir** — ən yaxşı effektivlik/performans nisbəti.

---

## İncə Tənzimləmə Prosesi

### Addım 1: Məlumat Dəstinin Hazırlanması

Ən vacib addım. Model keyfiyyəti məlumat keyfiyyəti ilə məhdudlaşır.

**Format** (standart təlimat tənzimləmə formatı):
```json
[
  {
    "instruction": "Aşağıdakını fransızcaya çevirin:",
    "input": "The weather is nice today.",
    "output": "Le temps est beau aujourd'hui."
  }
]
```

Və ya ChatML formatında (əksər müasir modellər tərəfindən istifadə olunur):
```
<|im_start|>system
You are a helpful assistant.<|im_end|>
<|im_start|>user
What is the capital of France?<|im_end|>
<|im_start|>assistant
Paris.<|im_end|>
```

**Ölçü tələbləri**:
- Üslub/format dəyişiklikləri: 100-500 nümunə
- Sahə uyğunlaşması: 1.000-10.000 nümunə
- Yeni bacarıq/davranış: 10.000-100.000 nümunə
- Keyfiyyət >> kəmiyyət: 100 mükəmməl nümunə 10.000 küylü nümunədən üstündür

**Öyrənmə/yoxlama bölgüsü**: 90/10 və ya 95/5. Heç vaxt öyrənmə məlumatında test etməyin.

### Addım 2: Öyrənmə Konfiqurasiyası

```yaml
# Unsloth/Axolotl konfiqurasiya nümunəsi
base_model: meta-llama/Llama-3.3-70B-Instruct
model_type: LlamaForCausalLM

datasets:
  - path: data/train.jsonl
    type: alpaca

val_set_size: 0.05
output_dir: ./output

# LoRA konfiqurasiyası
lora_r: 16
lora_alpha: 32
lora_dropout: 0.05
lora_target_modules:
  - q_proj
  - v_proj
  - k_proj
  - o_proj
  - gate_proj
  - down_proj
  - up_proj

# Öyrənmə parametrləri
sequence_len: 2048
num_epochs: 3
micro_batch_size: 2
gradient_accumulation_steps: 4
learning_rate: 0.0002
lr_scheduler: cosine
warmup_steps: 100

# Kvantlaşdırma
load_in_4bit: true
bf16: true

# Jurnallaşdırma
wandb_project: my-fine-tune
```

### Addım 3: Öyrənməyə Nəzarət

Öyrənmə zamanı izlənəcək əsas ölçülər:

**Öyrənmə itkisi**: sabit şəkildə azalmalıdır. Erkən duraqlanırsa, daha yüksək öyrənmə sürəti sınayın. Zirvə vurursa, daha aşağı öyrənmə sürəti sınayın.

**Yoxlama itkisi**: çox vacibdir. Öyrənmə itkisi azalır amma yoxlama itkisi artırsa → **həddindən artıq uyğunlaşma**. Öyrənməni dayandırın.

```
         İtki
          │
    yüksək─┤  Öyrənmə itkisi
          │   ╲
          │    ╲
          │     ╲___________  ← Öyrənmə azalmağa davam edir
          │
          │     Yoxlama itkisi
          │   ╲
          │    ╲____
          │         ╲________  ← Yoxlama itkisi artmağa başlayır = BURDA DAYANIN
    aşağı─┤
          └────────────────────── Dövrlər
                    ↑
              Erkən dayanma nöqtəsi
```

**Gradient norması**: sabit olmalıdır (0.1-2.0 aralığı). Böyük zirvələr qeyri-sabitliyi göstərir.

### Addım 4: Qiymətləndirmə

Yerləşdirməzdən əvvəl aşağıdakılara qarşı qiymətləndirin:
1. Yoxlama dəstiniz (obyektiv ölçülər)
2. LLM-qiymətləndiricisi (keyfiyyət qiymətləndirməsi)
3. Etalon tapşırıqlar (sahəyə xas testlər)
4. Ayrılmış test dəsti (öyrənmə zamanı heç vaxt istifadə edilməmişdir)

### Addım 5: Yerləşdirmə Seçimləri

1. **Birləşdir və ixrac et**: LoRA adapterlərini əsas modellə birləşdirin → tək model faylı → Ollama, vLLM, llama.cpp-ə yerləşdirin
2. **Adapter xidməti**: əsas modeli saxlayın + adaptərləri ayrıca xidmət göstərin (HuggingFace PEFT)
3. **Bulud incə tənzimləmə API-ləri**: OpenAI, Anthropic, Together AI — məlumatları yükləyin, onlar incə tənzimləyir və host edir
4. **GGUF-a ixrac edin**: Ollama lokal yerləşdirməsi üçün (`03-open-source-models-ollama.md`)

---

## Geniş Yayılmış Yanlış Anlayışlar

### Yanlış Anlayış 1: "İncə tənzimləmə modelə bilik əlavə edir"

**Yanlışdır.** İncə tənzimləmə modelə yeni faktlar öyrətmir. Çəkilər öyrənilmiş təsvirləri kodlayır — incə tənzimləmə həmin təsvirlərin *necə* aktivləşdirildiyi və ifadə edildiyi dəyişir, əsas biliklər deyil.

Modeliniz ön öyrənmə məlumatlarında şirkətinizin daxili məhsul adlarını bilmirsə, incə tənzimləmə həmin adları öyrətməyəcək. Bilik inyeksiyası üçün RAG istifadə edin.

**İncə tənzimləmənin faktiki dəyişdirdikləri**:
- Çıxış üslubu və formatı
- Ton və persona
- Sahəyə xas mühakimə nümunələri
- Tapşırığa xas davranış (müəyyən sorğu növlərinə necə cavab verməli)
- Dil nümunələri (məs., daha rəsmi, daha qısa, xüsusi terminologiya istifadə edir)

### Yanlış Anlayış 2: "Daha çox məlumat həmişə kömək edir"

Keyfiyyət küylü məlumatla aşağı düşür. 10.000 uyuşmaz nümunədə öyrənmək 500 yüksək keyfiyyətli nümunədən daha pis ola bilər. Uyuşmaz nümunələr modeli ziddiyyətli davranışlar arasında "ortalaşdırmağa" sövq edir, orta keyfiyyətli nəticələr istehsal edir.

**Prinsip**: aqressiv filtrləyin. Bir nümunəni yeni bir işçinin qarşısına düzgün davranış nümunəsi kimi qoymazdınızsa, öyrənmə məlumatlarınıza da qoymayın.

### Yanlış Anlayış 3: "İncə tənzimlənmiş modellər həmişə prompt-la işlənənlərdən daha yaxşıdır"

İncə tənzimləmə **ardıcıl format və üslubda** üstündür. Lakin mürəkkəb mühakimə üçün, son texnoloji prompt-la işlənən model (Claude Opus, GPT-4o) çox vaxt incə tənzimlənmiş kiçik modeldən üstündür — çünki daha böyük modelin daha çox xam qabiliyyəti var.

`02-fine-tuning-vs-rag.md` faylındakı qərar çərçivəsindən istifadə edin.

### Yanlış Anlayış 4: "İncə tənzimləmə çox bahalıdır"

**Artıq deyil.** Bulud GPU-larda QLoRA ilə:
- 7B modeli 1.000 nümunədə incə tənzimləyin: RunPod-da ~$5
- Llama 3.3 70B-ni 10.000 nümunədə incə tənzimləyin: A100-lərdə ~$100-200
- Bahalı hissə iterasiyadır (hiperparametrlərı düzəltmək üçün bir neçə öyrənmə işi)

---

## Xərclər və İnfrastruktur

### Bulud GPU Qiymətləri (2025)

| GPU | VRAM | $/saat | İstifadə halı |
|---|---|---|---|
| RTX 4090 | 24GB | ~$0.45 | 7B modellər, prototipləmə |
| A100 40GB | 40GB | ~$1.50 | 13B-34B QLoRA |
| A100 80GB | 80GB | ~$2.00 | 70B QLoRA, 13B tam FT |
| H100 80GB | 80GB | ~$3.50 | 70B tam FT, böyük paketlər |

### Öyrənmə Vaxtının Hesablanması

```
Təxmini formula:
Öyrənmə vaxtı ≈ (token_sayı × dövrlərin_sayı) / (saniyəyə_token × gpu_sayı)

Nümunə:
  10.000 nümunə × hər biri 500 token = 5M token
  3 dövrü = 15M ümumi token
  A100: 7B QLoRA üçün ~20.000 token/saniyə
  Vaxt ≈ 15.000.000 / 20.000 = 750 saniyə = ~13 dəqiqə
  Xərc ≈ 13dəq × $1.50/saat = ~$0.32

70B QLoRA üçün:
  Eyni məlumat, A100 80GB-da ~2.500 token/saniyə
  Vaxt ≈ 15.000.000 / 2.500 = 6.000 saniyə = ~100 dəqiqə
  Xərc ≈ 100dəq × $2.00/saat = ~$3.30
```

### İncə Tənzimləmə Xərci Nə Zaman Haqlıdır

İncə tənzimləmə davam edən nəticə çıxarma faydaları olan kapital xərcidir:

```
Ssenari: Ayda 1M sorğu emal edən müştəri dəstəyi botu

Seçim A: Claude Sonnet 3.7 (incə tənzimləmə yoxdur)
  Xərc: 1M × $0.003/sorğu = $3.000/ay

Seçim B: RunPod-da incə tənzimlənmiş Llama 3.3 70B
  İncə tənzimləmə xərci: $200 (bir dəfəlik)
  Nəticə çıxarma xərci: ~$0.0003/sorğu (20 dəfə ucuz)
  Aylıq xərc: 1M × $0.0003 = $300/ay

Geri ödəmə müddəti: < 1 ay
İllik qənaət: $32.000+
```

İqtisadiyyat miqyasda incə tənzimləməni güclü şəkildə dəstəkləyir. Zərər-mənfəət nöqtəsi adətən tapşırığın mürəkkəbliyindən asılı olaraq ayda 50.000-200.000 sorğudur.

---

## Xülasə: Nə Zaman İncə Tənzimləmə Etməli

İncə tənzimləmə aşağıdakılara ehtiyac duyduğunuzda haqlıdır:

| Ehtiyac | Tövsiyə olunan yanaşma |
|---|---|
| Ardıcıl çıxış formatı | İncə tənzimləmə (LoRA, kiçik r) |
| Xüsusi yazı üslubu/personası | İncə tənzimləmə |
| Sahəyə xas terminologiya | İncə tənzimləmə və ya RAG |
| Cari/dinamik biliklər | RAG |
| Yeni faktiki biliklər | RAG |
| Xüsusi tapşırıq növündə daha yaxşı mühakimə | İncə tənzimləmə (daha böyük r) |
| Miqyasda xərc azaldılması (>100K sorğu/ay) | Açıq mənbəli incə tənzimləmə |
| Məxfilik (məlumat serverlərinizə tərk etmir) | İncə tənzimləmə + lokal yerləşdirmə |
| Sürətli iterasiya və eksperiment | Əvvəlcə prompt mühəndisliyi |

---

## Praktik Tapşırıqlar

### Tapşırıq 1: İlk QLoRA Fine-Tune (Kiçik Model)

**Məqsəd:** 7B modeli Azərbaycan dilindəki PHP/Laravel suallarına cavab vermək üçün incə tənzimləyin.

**Addımlar:**
1. RunPod-da RTX 4090 kirayə edin (~$0.45/saat)
2. 200 nümunəlik dataset hazırlayın (instruction + output format)
3. Axolotl ilə QLoRA konfiqurasiyası yaradın (r=16, alpha=32)
4. 3 epoch üçün öyrənin (~15-20 dəqiqə)
5. Yoxlama itkisini izləyin — həddindən artıq uyğunlaşmanı aşkarlayın
6. LoRA adapterlərini birləşdirin və GGUF-a ixrac edin

```bash
# RunPod-da sürətli başlanğıc
pip install unsloth axolotl
axolotl train config.yml --num_epochs 3
```

**Gözlənilən nəticə:** Yalnız format/üslub üçün ~100 nümunə kifayətdir. Yoxlama itkisi öyrənmə itkisi ilə birlikdə azalmalıdır.

### Tapşırıq 2: Artımlı Dataset Keyfiyyətinin Yoxlanması

**Məqsəd:** Pis nümunələrin modeli necə xarab etdiyini anlamaq.

**Addım-addım:**
1. 50 keyfiyyətli + 50 pis (səthi, qısa) nümunəlik iki dataset yaradın
2. Eyni modeli, eyni hiperparametrlərlə hər iki dataset-də öyrədin
3. Eyni test sualları üçün hər iki modelin çıxışlarını müqayisə edin
4. Keyfiyyətin fərqini sənədləşdirin

**Nəticə:** 100 yüksək keyfiyyətli nümunə 1,000 küylü nümunədən üstündür.

### Tapşırıq 3: Zərər-Mənfəət Hesablaması

**Məqsəd:** Xüsusi istifadə halınız üçün incə tənzimləməni API xərci ilə müqayisə edin.

```
Hesab edin:
  Aylıq həcminiz: _____ sorğu
  Claude Haiku xərci: _____ $/ay
  Fine-tune xərci (bir dəfəlik): $_____
  Geri ödəmə müddəti: _____ ay

Əgər < 6 ay → fine-tune edin
Əgər > 12 ay → API-ni davam etdirin
```

---

## Əlaqəli Mövzular

- [02-fine-tuning-vs-rag.md](02-fine-tuning-vs-rag.md) — Hansı yanaşmanı seçmək
- [03-open-source-models-ollama.md](03-open-source-models-ollama.md) — Base model seçimi (Llama, Qwen, Mistral)
- [04-lora-qlora-peft.md](04-lora-qlora-peft.md) — LoRA/QLoRA texnikası dərindən
- [05-create-custom-model-finetune.md](05-create-custom-model-finetune.md) — Praktik fine-tune addım-addım
- [08-ft-dataset-curation.md](08-ft-dataset-curation.md) — Dataset hazırlama və keyfiyyət
