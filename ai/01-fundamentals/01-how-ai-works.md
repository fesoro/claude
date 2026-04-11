# LLM-lər Necə İşləyir — Transformer Arxitekturası, Attention Mexanizmi və Inference

> Hədəf auditoriyası: Böyük dil modellərinin necə işlədiyini — sadəcə "növbəti token-i proqnozlaşdırır" deyil, *nə üçün* və *necə* işlədiyini dərindən başa düşmək istəyən senior developerlər və arxitektorlar.

---

## Mündəricat

1. [Ümumi Mənzərə](#the-big-picture)
2. [Tokenizasiya — Mətn Rəqəmlərə Çevrilir](#tokenization)
3. [Embedding-lər — Mənalar Həndəsə Kimi](#embeddings)
4. [Transformer Arxitekturası](#transformer-architecture)
5. [Self-Attention — Əsas Mexanizm](#self-attention)
6. [Multi-Head Attention](#multi-head-attention)
7. [Feed-Forward Qatlar](#feed-forward-layers)
8. [Pozisional Encoding](#positional-encoding)
9. [Tam Forward Ötürümü](#the-full-forward-pass)
10. [Öyrənmə — Ön Öyrənmə və RLHF](#training)
11. [Inference — Avtoreqressiv Generasiya](#inference)
12. [Temperature və Nümunə Seçimi](#temperature-and-sampling)
13. [Prompt Göndərəndə Nə Baş Verir](#what-happens-when-you-send-a-prompt)
14. [Arxitektorlar üçün Mental Modellər](#mental-models-for-architects)

---

## Ümumi Mənzərə

Böyük dil modeli, öz mahiyyəti etibarilə, **token ardıcıllıqları üzərindəki ehtimal paylaşımıdır**. Modelin əlindəki token ardıcıllığına görə, o, hər mümkün növbəti token üçün ehtimal hesablayır. Generasiya isə bu paylaşımdan dəfələrlə nümunə götürmə aktıdır.

Amma bu izah əslində baş verənin qiymətini azaldır. Model, milyardlarla parametrə insanın yaratdığı nəhəng bir mətn korpusundan gəlmə nümunələrin sıxışdırılmış bir təsvirini kodlaşdırmışdır. Modelin "növbəti token-i proqnozlaşdırması", əslində dilin, mühakimənin, faktların və kodun necə qurulduğuna dair itkili, lakin güclü sıxışdırmanı istifadə etməkdir.

```
Giriş mətni:  "Fransanın paytaxtı"
Tokenizasiya: ["Fransa", "nın", " paytaxtı"]
Model çıxışı: ~100k+ lüğət token-i üzərindəki ehtimal paylaşımı
Ən yüksək:   " Paris" (0.94), " Lyon" (0.02), " bir" (0.01), ...
Seçildi:      " Paris"
```

Bu tək addım, avtoreqressiv şəkildə təkrarlanaraq bütün çıxışları — esseleri, kodu, mühakimə zəncirlərini, hər şeyi — yaradır.

---

## Tokenizasiya

### Token Nədir?

Token, modelin üzərində işlədiyi mətnin atomik vahididir. Token-lər SÖZ DEYİL. Onlar, modelin öyrənmə korpusunda öyrədilmiş sıxışdırma alqoritmi ilə müəyyən edilən alt-söz vahidləridir.

**Byte-Pair Encoding (BPE)** ən geniş yayılmış alqoritmdir:

```
Alqoritm:
1. Ayrı-ayrı simvollardan (baytlardan) ibarət lüğətlə başla
2. Korpusdakı bütün bitişik simvol cütlərini say
3. Ən tez-tez rast gəlinən cütü yeni token-ə birləşdir
4. Lüğət hədəf ölçüyə çatana qədər təkrar et (GPT-4 üçün, məs., 100,277)

Birləşdirmə ardıcıllığının nümunəsi:
"lower" → ['l','o','w','e','r']
(lo) birləşdirildikdən sonra: ['lo','w','e','r']
(low) birləşdirildikdən sonra: ['low','e','r']
(lower) birləşdirildikdən sonra: ['lower']
```

### Tokenizasiya Davranışı üçün Sezgi

```
"hello"          → 1 token
"Hello"          → 1 token (kiçik hərfdəkindən fərqli token!)
"HELLO"          → 3 token (H, EL, LO — daha az yayılmış)
"tokenization"   → 3 token (token, ization, ...)
"1234567"        → 7 token (hər rəqəm çox vaxt öz token-i olur)
"2024-01-15"     → 5-7 token
" Paris"         → 1 token (aparıcı boşluq token-İN HİSSƏSİDİR)
"Paris"          → 1 token (" Paris"-dən fərqli token)
```

Buna görə modellər simvol səviyyəsindəki tapşırıqlarla (məs., "'strawberry' sözündəki 'r' hərifinin sayını tap") çətinlik çəkir — model ayrı-ayrı simvolları heç vaxt görmür; sıxışdırılmış parçaları görür.

### Lüğət Ölçüsü Mübadilələri

| Lüğət Ölçüsü | Üstünlüklər | Çatışmazlıqlar |
|----------------|------|------|
| Kiçik (~10k) | Kompakt model, az embedding | Uzun ardıcıllıqlar, zəif alt-söz əhatəsi |
| Orta (~50k) | Balans | — |
| Böyük (~100k+) | Qısa ardıcıllıqlar, yaxşı əhatə | Embedding cədvəli üçün daha çox yaddaş |

---

## Embedding-lər — Mənalar Həndəsə Kimi

Token transformer-ə daxil olmadan əvvəl, **embedding cədvəlindən** axtarılır: `[vocab_size × d_model]` ölçülü öyrənilmiş matris. Hər token ID-si `d_model` ölçülü sıx bir vektora çevrilir (bir çox böyük modeldə, məs., 4096).

```
Token ID: 15496 ("hello")
Embedding: [0.23, -0.41, 0.87, ..., 0.12]  ← 4096 ədəd
```

Bu embedding-lər ƏLLƏ YAZILMAYIB. Onlar öyrənmə prosesindən meydana çıxır. Embedding fəzasının həndəsəsi semantik əlaqələri ələ keçirir:

```
Cəbri anologiya (məşhur nümunə):
vector("kral") - vector("kişi") + vector("qadın") ≈ vector("kraliça")

Klasterləşmə:
Oxşar mənalı token-lər embedding fəzasında bir-birinə yaxın yığılır.
"it", "pişik", "bala köpək" həndəsi baxımdan yaxındır.
"Paris", "Berlin", "Tokyo" fərqli bir klasterdədir.
```

Buna görə "anlama" meydana çıxır — bu sehrli deyil, qradient eniş metodunun modelin ümumiləşdirə biləcəyi faydalı həndəsi quruluş kəşf etməsidir.

---

## Transformer Arxitekturası

2017-ci ildə "Attention Is All You Need" (Vaswani et al.) məqaləsində təqdim edilən transformer, rekurrent şəbəkələri (LSTM-ləri) attention əsaslı, tam paralel işlənə bilən bir arxitektura ilə əvəz etdi.

```
Yalnız-dekoder transformer-in yüksək səviyyəli arxitekturası (GPT üslubu):

Giriş token-ləri
     │
     ▼
[Embedding Qatı]  ← token embedding-lər + pozisional encoding-lər
     │
     ▼
┌─────────────────────────────────────────┐
│  Transformer Bloku × N (böyük modellər  │
│  üçün, məs., 96)                        │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │  Layer Norm                     │   │
│  │  Multi-Head Self-Attention      │   │
│  │  Residual Bağlantı (+ giriş)   │   │
│  ├─────────────────────────────────┤   │
│  │  Layer Norm                     │   │
│  │  Feed-Forward Network (MLP)     │   │
│  │  Residual Bağlantı (+ giriş)   │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
     │
     ▼
[Son Layer Norm]
     │
     ▼
[Language Model Head]  ← vocab_size-a xətti proyeksiya
     │
     ▼
[Softmax]  ← logit-ləri ehtimallara çevir
     │
     ▼
Növbəti token ehtimal paylaşımı
```

**Əsas dizayn qərarları:**
- **Residual bağlantılar**: Hər blok öz çıxışını girişinə əlavə edir. Bu, qradientlərin çıxışdan erkən qatlara birbaşa axmasına imkan verir, yoxa çıxan qradient problemini həll edir.
- **Layer normalizasiyası**: Hər alt-qatdan əvvəl aktivasiyaları normallaşdıraraq öyrənməni sabitləşdirir.
- **Rekurrentsizlik**: Bütün ardıcıllıq (öyrənmə zamanı) paralel işlənir. Buna görə transformer-lər GPU-larda bu qədər yaxşı miqyaslana bilir.

---

## Self-Attention — Əsas Mexanizm

Self-attention, ardıcıllıqdakı hər token-in digər hər token-ə "diqqət etməsinə" və lazımi məlumatı toplamasına imkan verən mexanizmdir. Bu, transformer-in mühərrikidir.

### Query-Key-Value Formulasiyası

Hər token-in embedding vektoru `x` üçün, öyrənilmiş çəki matrisləri ilə üç vektor hesablanır:

```
Q = x · W_Q    (Query  — "nə axtarıram?")
K = x · W_K    (Key    — "nə təklif edirəm?")
V = x · W_V    (Value  — "hansı məlumatı daşıyıram?")
```

Bunu yumşaq bir verilənlər bazası sorğusu kimi düşünün:
- **Query** axtarış terminidir
- **Key**-lər indeks girişləridir
- **Value**-lar isə əsl məlumatdır
- Axtarış diferensialdır və **çəkilidir** — hər açarın sorğuya uyğunluq dərəcəsinə görə çəkiləndirilmiş bütün dəyərlərin qarışığını alırsınız

### Attention Düsturu

```
Attention(Q, K, V) = softmax(Q·Kᵀ / √d_k) · V

Burada:
  Q: [seq_len × d_k]    — bütün query-lər yığılmış
  K: [seq_len × d_k]    — bütün key-lər yığılmış
  V: [seq_len × d_v]    — bütün value-lar yığılmış
  d_k: key vektorlarının ölçüsü (miqyaslandırma üçün istifadə edilir)
```

5 uzunluğunda ardıcıllıq üçün addım-addım:

```
Addım 1: Attention skorlarını hesabla (Q·Kᵀ)
         token1  token2  token3  token4  token5
token1 [  8.1    -2.3    0.4     5.2    -1.1  ]
token2 [ -1.2    9.4     3.1    -0.8     2.3  ]
token3 [  0.3    2.8    11.2     1.4     0.6  ]
...

Addım 2: √d_k ilə miqyaslandır (softmax doymasının qarşısını alır)
         token1  token2  token3  token4  token5
token1 [  2.0    -0.6    0.1     1.3    -0.3  ]
...

Addım 3: Kauzal maska tətbiq et (dekoder üçün — gələcəyə baxa bilməz)
         token1  token2  token3  token4  token5
token1 [  2.0    -inf    -inf    -inf    -inf  ]
token2 [ -1.2    9.4     -inf    -inf    -inf  ]
token3 [  0.3    2.8    11.2     -inf    -inf  ]
...

Addım 4: Softmax (sıraya görə) — ehtimallara çevir
         token1  token2  token3  token4  token5
token1 [  1.0     0       0       0       0   ]
token2 [  0.01   0.99     0       0       0   ]
token3 [  0.02   0.13    0.85     0       0   ]
...

Addım 5: Value-ların çəkili cəmi
output_token3 = 0.02 * V[token1] + 0.13 * V[token2] + 0.85 * V[token3]
```

Kauzal maska kritikdir: generasiya zamanı bir token yalnız özündən əvvəl gələn token-lərə (və özünə) baxa bilər. Bu, avtoreqressiv xüsusiyyəti qoruyur.

### Nə Üçün Attention Uzun Məsafəli Asılılıqları Ələ Keçirir

RNN-lərdən fərqli olaraq, burada məlumat hər aralıq addımdan keçmək (və seyrəlmək) məcburiyyətindədir; attention isə ardıcıllıqdakı məsafədən asılı olmayaraq istənilən iki token-i O(1) əməliyyatla birləşdirir. 4096-cı mövqedəki token, 1-ci mövqedəki token-ə eyni asanlıqla birbaşa diqqət yetirə bilər.

Qiymət: attention ardıcıllığın uzunluğu baxımından O(n²)-dir — hər token cütü üçün bir skor hesablayırsınız. Buna görə uzun context baha başa gəlir.

---

## Multi-Head Attention

Attention-u bir dəfə yerinə yetirmək əvəzinə, müasir transformer-lər onu **h dəfə paralel** işlədir (h = başların sayı, məs., 32 və ya 64), hər biri fərqli öyrənilmiş W_Q, W_K, W_V matrislərindən istifadə edir.

```
MultiHead(Q, K, V) = Concat(head_1, ..., head_h) · W_O

Burada head_i = Attention(Q·W_Q_i, K·W_K_i, V·W_V_i)
```

**Niyə çoxlu başlar?**

Hər baş eyni zamanda müxtəlif növ əlaqələrə diqqət etməyi öyrənə bilər:
- 1-ci baş **sintaktik asılılıqlara** (mübtəda-xəbər uyğunluğu) diqqət edə bilər
- 2-ci baş **koreferansa** ("o" kimin haqqında deyilir?) diqqət edə bilər
- 3-cü baş **mövqe yaxınlığına** diqqət edə bilər
- 4-cü baş **semantik oxşarlığa** diqqət edə bilər

Bu, öyrədilmiş modellerdəki attention nümunələri analiz edilərək empirik yolla müşahidə edilmişdir.

```
Nümunə: "Kubok çamadana sığmadı, çünki o çox böyük idi"

A başı (koreferans): "o" güclü şəkildə "kuboka" diqqət edir (çamadana yox)
B başı (sintaksis):  "sığmadı" "kuboka" diqqət edir (mübtəda)
C başı (inkar):      "sığmadı" "sığmadı" ifadəsindəki inkara diqqət edir
```

---

## Feed-Forward Qatlar

Attention alt-qatından sonra, hər mövqenin təsviri **mövqe üzrə feed-forward şəbəkəsindən (FFN)** keçir:

```
FFN(x) = max(0, x·W_1 + b_1) · W_2 + b_2

Yaxud GELU aktivasiyası ilə (müasir modellerdə daha çox yayılmış):
FFN(x) = GELU(x·W_1 + b_1) · W_2 + b_2

Ölçülər:
  Giriş:  d_model (məs., 4096)
  Gizli:  4 × d_model (məs., 16384)  ← "genişləmə nisbəti"
  Çıxış:  d_model (məs., 4096)
```

FFN hər mövqedə **müstəqil** işləyir — burada ardıcıllıq mövqeləri arasında heç bir qarışma yoxdur (bu, attention-un işidir).

**FFN qatları nə saxlayır?** Tədqiqatlar FFN qatlarının **açar-dəyər yaddaşı** forması kimi fəaliyyət göstərdiyini — faktik assosiasiyaları saxladığını göstərir. Əgər attention "hansı kontekst lazımdır, onu al" deməkdirsə, FFN "o kontekst haqqında bildiklərimi axtar" deməkdir.

Genişləmə nisbəti (4×) modelə mürəkkəb transformasiyaları ifadə etmək üçün böyük aralıq fəza verir. 70 milyard parametrli bir modeldə bütün parametrlərin təxminən **2/3-ü** FFN qatlarında yerləşir.

---

## Pozisional Encoding

Transformer bütün token-ləri paralel işlətdiyi üçün (xüsusi sıra yoxdur), mövqe məlumatını açıq şəkildə əlavə etmək lazımdır.

### Sinusoidal (Orijinal)

```
PE(pos, 2i)   = sin(pos / 10000^(2i/d_model))
PE(pos, 2i+1) = cos(pos / 10000^(2i/d_model))

Xüsusiyyətlər:
- Hər mövqe unikal bir nümunə alır
- Encoding düzgündür (yaxın mövqelər oxşardır)
- Model görünməmiş mövqelərə ümumiləşdirə bilər (müəyyən həddə qədər)
```

### RoPE (Rotary Position Embedding) — Müasir Standart

LLaMA, Mistral, Claude və əksər müasir modellər tərəfindən istifadə edilir. Embedding-lərə mövqe əlavə etmək əvəzinə, RoPE attention hesablanmadan əvvəl query və key vektorlarını mövqelərinə mütənasib bir bucaqla **fırladır**.

```
Əsas fikir: Q · K^T hesablayarkən, əgər Q θ_m qədər fırladılıbsa
və K θ_n qədər fırladılıbsa, nöqtəvi hasil (m - n) — YƏNİ NİSBİ
MÖVQEyə bağlıdır — mütləq mövqelərə yox. Bu, modelə nisbi məsafə üçün
güclü induktiv meyl verir.

Üstünlüklər:
- Daha yaxşı uzunluq ümumiləşdirməsi
- YaRN, NTK-aware scaling kimi üsullarla genişləndirilə bilər
- Ardıcıllıq sırasının daha təbii təsviri
```

---

## Tam Forward Ötürümü

Hər şeyi bir araya gətirərək, tək giriş ardıcıllığına nə baş verdiyini izah edək:

```
Giriş: "Fransanın paytaxtı nədir?"

1. TOKENİZASİYA
   ["Fransa", "nın", " paytaxtı", " nə", "dir", "?"]
   Token ID-ləri: [1921, 318, 262, 3139, 286, 30]

2. EMBEDDING AXTARIŞI
   Hər token ID → embedding cədvəlindən 4096 ölçülü vektor
   Nəticə: [6 × 4096] ölçülü matris

3. POZİSİONAL ENCODING ƏLAVƏ ET
   Hər sıraya fırlanmış (RoPE) və ya toplanmış (sinusoidal) mövqe məlumatı əlavə edilir

4. TRANSFORMER BLOKLARI (böyük model üçün ×96)
   Hər blok üçün:
   a. Layer Norm
   b. Multi-Head Self-Attention (bütün token-lər əvvəlki bütün token-lərə diqqət edir)
   c. Residual bağlantı (orijinal girişi attention çıxışına əlavə et)
   d. Layer Norm
   e. Feed-Forward Network
   f. Residual bağlantı

5. SON LAYER NORM

6. LM HEAD (Xətti proyeksiya)
   [6 × 4096] → [6 × 100277] (hər mövqe, hər lüğət token-i üçün bir logit)
   Yalnız SON mövqe ilə maraqlanırıq: [1 × 100277]

7. SOFTMAX
   Logit-ləri ehtimallara çevir

8. NÜMUNƏLƏŞDİRMƏ
   Paylaşımdan nümunə götür → " Paris" token-i
   (yaxud həris: argmax al)

9. ƏLAVƏ ET VƏ TƏKRARLA
   Yeni ardıcıllıq: [..., " Paris"]
   Növbəti token üçün modeli yenidən işlət
```

---

## Öyrənmə

### Ön Öyrənmə: İnternetdən Öyrənmək

Ön öyrənmə, modelin nəhəng bir mətn korpusundan (internetdən, kitablardan, koddan gəlmə trilyon token-lər) öyrəndiyi mərhələdir.

**Məqsəd**: Növbəti token proqnozu (kauzal dil modelləşdirməsi)

```
"Pişik döşəmənin üstündə oturdu" öyrənmə ardıcıllığı üçün:

Giriş:  ["Pişik", " döşəmənin", " üstündə"]
Hədəf: [" döşəmənin", " üstündə", " oturdu"]

İtki = -Σ log P(hədəf_i | giriş_1...giriş_i)
     = proqnozlaşdırılan paylaşım ilə bir-sıcaq hədəf arasındakı çarpaz-entropi

Qradient eniş bütün çəkiləri bu itki boyunca minimuma endirmək üçün tənzimləyir
```

Bu **özünü-nəzarət** edir — insan etiketləri lazım deyil. Öyrənmə siqnalı mətnin özüdür.

**Miqyas**:
- GPT-3: 175 milyard parametr, 300 milyard token
- LLaMA 3: 405 milyard parametr, 15+ trilyon token
- Öyrənmə dəyəri: milyonlarla dollar, minlərlə GPU, həftələr/aylar

### Nəzarət Altında İncə Ayarlama (SFT)

Ön öyrənmədən sonra model dili bilir, amma faydalı bir köməkçi olmağı bilmir. SFT onu seçilmiş tapşırıq + ideal cavab nümunələri üzərində öyrədir:

```
Nümunə öyrənmə cütü:
İstifadəçi:   "Rekursiyanı 10 yaşlı uşağa izah et"
Köməkçi: "Lüğətdə söz axtardığını təsəvvür et..."

Model öyrənir: giriş bir tapşırıq kimi görünürsə, çıxış
faydalı bir cavab kimi görünməlidir.
```

### RLHF — İnsan Rəyindən Gücləndirilmiş Öyrənmə

RLHF, ChatGPT və Claude kimi modelleri faydalı, zərərsiz və dürüst edən əsas texnikadır.

```
Mərhələ 1: MÜKAFAT MODELİNİN ÖYRƏDİLMƏSİ
  - Eyni prompt üçün bir neçə model çıxışı yarat
  - İnsan qiymətləndiricilər çıxışları sıralayır (A > B > C)
  - İnsan üstünlüklərini proqnozlaşdırmaq üçün ayrıca "mükafat modeli" öyrət
  - Mükafat modeli: (prompt, cavab) → skalar skor

Mərhələ 2: RL İNCƏ AYARLAMA (PPO və ya oxşarı)
  - Optimallaşdırma siqnalı kimi mükafat modelindən istifadə et
  - Gözlənilən mükafatı maksimuma çatdırmaq üçün LLM-i incə ayarla
  - SFT bazasından çox uzaq sürüklənməni önləmək üçün
    KL divergensiyası cəzası əlavə et (mükafat hacklənməsini önləyir)

  Məqsəd:
  E[R(x, y)] - β * KL(π_RL || π_SFT) maksimuma çatdırılır
  
  Burada:
    R: mükafat modeli skoru
    π_RL: cari RL politikası (öyrədilən model)
    π_SFT: SFT modeli (istinad politikası)
    β: KL cəzası əmsalı
```

**Constitutional AI (Claude-ə xas)**: Anthropic, modelin öz çıxışlarını bir sıra prinsiplər ("konstitusiya") toplusuna qarşı tənqid etdiyi və bu prinsiplərə daha yaxşı uyan reviziyaları üstün tutmağı öyrəndiyi bir variant istifadə edir. Bu, zərərli məzmun üçün insan rəyinə olan ehtiyacı azaldır.

---

## Inference — Avtoreqressiv Generasiya

Inference, öyrədilmiş modeli mətn yaratmaq üçün istifadə etmə prosesidir.

### Avtoreqressiv Dövrə

```
┌─────────────────────────────────────────────────────┐
│  AVTOREQRESSİV GENERASİYA DÖVRÜsü                  │
│                                                     │
│  token-lər = tokenizasiya(prompt)                   │
│                                                     │
│  bitənə qədər:                                      │
│    logit-lər = model.forward(token-lər) ← tam pass  │
│    növbəti_token = nümunə(logit-lər[-1]) ← son nümunə│
│    token-lər.append(növbəti_token)                  │
│    if növbəti_token == EOS: dayandır                │
│    if len(token-lər) >= max_tokens: dayandır        │
│                                                     │
│  return tokenizasiyasızlaşdır(token-lər[len(prompt):])│
└─────────────────────────────────────────────────────┘
```

**Kritik fikir**: Hər yeni token bütün transformer qatlarından **tam bir forward ötürümü** tələb edir. 1000 token yaradan 96 qatlı bir model üçün bu 96.000 matris vurma deməkdir. Buna görə inference baha başa gəlir.

### KV Cache — Əsas Optimallaşdırma

Cache olmadan, hər addımda bütün əvvəlki token-lər üçün K və V yenidən hesablanardı (dəyişmədikləri halda). Müasir inference mühərrikləri bunları cache-ə alır:

```
Addım 1: "Fransanın paytaxtı" prompt-unu işlə
  Bütün 5 token üçün K, V hesabla → KV cache-ə saxla
  
Addım 2: " nədir" yarat
  Yalnız yeni " nədir" token-i üçün Q hesabla
  Əvvəlki 5 token üçün K, V-ni cache-dən al
  Cache edilmiş K, V istifadə edərək attention işlət
  Yeni K, V-ni cache-ə əlavə et

Addım 3: " Paris" yarat
  Yalnız yeni " Paris" token-i üçün Q hesabla
  Əvvəlki 6 token üçün K, V-ni cache-dən al
  ...
```

Bu, addım başına hesablamanı dramatik şəkildə azaldır. Lakin KV cache ardıcıllığın uzunluğuyla xətti artır və GPU yaddaşında saxlanılır — buna görə uzun context-lər xidmət etmək yaddaş tutumlu olur.

---

## Temperature və Nümunəgötürmə

Forward ötürümündən sonra **logit**-lərimiz var — hər lüğət token-i üçün normallaşdırılmamış skorlar. Bunları ehtimal paylaşımına çevirməli və nümunə götürməliyik.

### Temperature

Temperature `T` paylaşımın "kəskinliyini" idarə edir:

```
P(token_i) = softmax(logit-lər / T)[i]
           = exp(logit_i / T) / Σ exp(logit_j / T)

T = 1.0: standart paylaşım (modelin əsl çıxışı)
T → 0:   həris deşifrəyə yaxınlaşır (argmax) — həmişə ən çox
          ehtimallı token-i seçir. Deterministik, lakin monoton.
T > 1:   paylaşımı düzləşdirir — daha vahid, daha təsadüfi,
          daha "yaradıcı" (ya da daha yanlış)
T = 0.7: bir qədər kəskinləşdirilmiş — bir çox tapşırıq üçün optimal

Nümunə logit-lər: [" Paris": 3.2, " London": 1.1, " Berlin": 0.8, ...]

T=0.1-də:  P(" Paris") ≈ 0.9998, P(" London") ≈ 0.0001
T=1.0-da:  P(" Paris") ≈ 0.72,   P(" London") ≈ 0.10
T=2.0-da:  P(" Paris") ≈ 0.37,   P(" London") ≈ 0.22
```

### Top-p (Nucleus Nümunəgötürmə)

Tam paylaşımdan nümunə götürmək əvəzinə, yalnız kumulyativ ehtimalı p-dən yüksək olan ən kiçik token dəstindən nümunə götür:

```
Sıralanmış token-lər: [" Paris": 0.72, " London": 0.10, " Berlin": 0.07, ...]
Kumulyativ:           [0.72, 0.82, 0.89, ...]

top_p=0.9 ilə: yalnız {" Paris", " London", " Berlin"}-dən nümunə götür
(kumulyativ ehtimal 0.9-a çatanda dayanır)

Bu, ehtimalsız token-lərin "uzun quyruğunu" kəsir, lakin
ən yaxşı namizədlər arasında dəyişkənliyə icazə verir.
```

### Top-k

Yalnız ən yüksək ehtimallı k token-dən nümunə götür:

```
top_k=50 ilə: ehtimal paylaşımından asılı olmayaraq həmişə
tam olaraq ən yaxşı 50 token-dən nümunə götür.

Top-p-dən daha az adaptivdir (bəzən 50 token ehtimal
kütləsinin 99.9%-ni əhatə edir, bəzən yalnız 60%-ni).
```

### Onların Birləşdirilməsi

Əməldə temperature, top-p və top-k çox zaman birlikdə istifadə edilir. Ümumi resept:

```
Temperature: 0.8    (yüngül kəskinləşdirmə)
Top-p:       0.95   (çox ehtimalsız token-ləri çıxar)
Top-k:       50     (namizədlər üzərindəki sərt hədd)

Nümunəgötürmə sırası:
1. Top-k filtrini tətbiq et
2. Top-p filtrini tətbiq et (qalan token-lərə)
3. Temperature tətbiq et
4. Softmax + nümunə götür
```

---

## Prompt Göndərəndə Nə Baş Verir

Bir model API-na mesaj göndərdiyin andan aşağıdakılar baş verir:

```
[SƏNİN TƏTBİQİN]
       │
       │  HTTP POST /v1/messages
       │  {"model": "claude-sonnet-4-6", "messages": [...]}
       ▼
[API GATEWAY / YÜK BALANSLAŞDIRICI]
       │  Autentifikasiya, sürət məhdudiyyəti, marşrutlaşdırma
       ▼
[INFERENCE SERVER]  (məs., A100/H100 GPU klasterlər)
       │
       ├── 1. TOKENİZASİYA
       │      Mətnin → token ID-lərinə çevrilməsi (sürətli, CPU əməliyyatı)
       │
       ├── 2. PROMPT İŞLƏNMƏSİ (prefill mərhələsi)
       │      Bütün prompt token-ləri PARALELdə işlənir
       │      Bütün prompt token-ləri üçün KV cache doldurulur
       │      Bu sürətlidir — bütün token-lər arasında parallellik
       │      Vaxt: O(n), burada n = prompt uzunluğu
       │
       ├── 3. GENERASİYA MƏRHƏLƏSİ (deşifrə mərhələsi)
       │      Token-lər BİR-BİR yaradılır (avtoreqressiv)
       │      Hər token: KV cache istifadə edərək bir forward ötürümü
       │      Vaxt: O(m), burada m = çıxış uzunluğu
       │      Məhsuldarlıq: adətən 30-100 token/saniyə
       │
       ├── 4. AXIN (aktivləşdirildiyi halda)
       │      Hər token SSE/parçalanmış HTTP vasitəsilə dərhal göndərilir
       │      Çıxışın söz-söz göründüyünü görürsən
       │
       └── 5. TAMAMLANMA
              Son cavab qaytarıldı (ya da axın bağlandı)
              Hesablama: giriş + çıxış token-lərinə görə
```

### Vaxt Büdcəsi (Təxmini, Claude miqyasında model)

```
Prefill (1000 token prompt):     ~200-500ms  (paralel, sürətli)
Generasiya (100 token çıxış):   ~1000-3000ms (ardıcıl, daha yavaş)
Şəbəkə yükü:                    ~50-100ms

Cəmi:                           Tipik sorğu üçün ~1.5-4 saniyə
```

Buna görə **ilk tokena qədər vaxt** və **saniyə başına token** LLM API-ları üçün iki əsas gecikmə metrikasıdır.

---

## Arxitektorlar üçün Mental Modellər

### Model Sıxışdırma Kimi

LLM, öyrənmə məlumatlarının itkili sıxışdırılmasıdır. "Paris-in Fransanın paytaxtı olduğunu bildiyi" zaman, bu bir verilənlər bazası sorğusu deyil — öyrənmə zamanı bu konseptlərin milyardlarla birgə baş verməsi çəkiləri "Fransanın paytaxtıdır" ifadəsindən sonra "Paris" yaratmanın yüksək ehtimallı olduğu konfiqurasiyaya doğru itələyib. Bu mental model aşağıdakıları izah edir:

- Modellər hallüsinasiya edir: sıxışdırma itkilidir, nadir faktlar yaxşı qorunmur
- Modellərın bilik kəsilmə tarixi var: kəsilmə tarixindən sonrakı məlumat heç vaxt sıxışdırılmayıb
- Modellər nadir nümunələrdən daha çox yayılmış nümunələrdə yaxşıdır

### Attention Marşrutlaşdırma Kimi

Attention mexanizmini dinamik bir marşrutlaşdırma sistemi kimi düşünün. Hər qatda, hər mövqe hansı digər mövqelərin məlumatına ehtiyac duyduğuna qərar verir və onu çəkib götürür. Daha dərin qatlar, attention-ın çoxlu hoplarla məlumatı marşrutlaşdıraraq daha mücərrəd təsvirlər qurur.

### Parametrlər Donmuş Qradientlər Kimi

Modelin bütün "bilikleri" çəkilərindədir — trilyon nümunə boyunca qradient eniş metoduyla şəkilləndirilimiş milyardlarla tam ədəd. Bu çəkilər **inference zamanı dondurulur**. Model söhbətimizdən öyrənə bilməz (incə ayarlama etmədikdə). Context window onun işçi yaddaşıdır; çəkilər isə uzunmüddətli yaddaşdır.

### Yaranan Davranış Həddı

Müəyyən parametr/məlumat miqyası həddlərindəki modellerin keyfiyyətcə yeni imkanlara nail olduğuna dair yaxşı sənədləşdirilmiş bir fenomen mövcuddur:

```
~1 milyard parametr:  Əsas dil tapşırıqları
~7 milyard parametr:  Tapşırıq izlənilməsi, əsas mühakimə
~70 milyard parametr: Mürəkkəb mühakimə, kod generasiyası
~700+ milyard parametr: Bir çox göstəricidə insana yaxın performans

Bu "yaranma" tam başa düşülmür — aktiv araşdırma sahəsidir.
```

### Developer Kimi Sizi Maraqlandıran Əsas Arxitektura Qərarları

| Qərar | Sizin üçün Niyə Vacibdir |
|---|---|
| Context window ölçüsü | Bir sorğuya nə qədər söhbətin/sənədin sığdığını məhdudlaşdırır |
| Lüğət ölçüsü | Tokenizasiya səmərəliliyinə təsir edir (daha çox token = daha çox xərc) |
| Parametr sayı | Daha böyük = daha ağıllı, lakin yavaş və baha |
| KV cache ölçüsü | Xidmət üçün maksimum faydalı toplu ölçünü müəyyən edir |
| Öyrənmə məlumatlarının kəsilmə tarixi | Son hadisələr haqqında bilikləri məhdudlaşdırır |
| RLHF uyğunlaşdırması | Təhlükəsizlik davranışlarını, imtinalarını, tapşırıq izlənilmə keyfiyyətini müəyyən edir |

---

## Xülasə

- LLM-lər transformer arxitekturasıyla işləyən növbəti-token proqnozlaşdırıcılardır
- Self-attention hər token-in digər hər token-ə diqqət etməsinə, O(1) addımda uzun məsafəli asılılıqları ələ keçirməsinə imkan verir
- Multi-head attention modelin eyni zamanda çoxlu növ əlaqələri izləməsinə imkan verir
- FFN qatları faktik assosiasiyaları saxlayan açar-dəyər yaddaşı kimi fəaliyyət göstərir
- Nəhəng mətn korpusları üzərindəki ön öyrənmə modelə dili öyrədir; RLHF isə faydalı olmağı öyrədir
- Inference avtoreqressivdir — bir anda bir token — bu da onu mahiyyətcə ardıcıl edir
- Temperature çıxış paylaşımından nümunəgötürmənin təsadüfiliyini idarə edir
- KV cache xidməti mümkün edən əsas optimallaşdırmadır
- Model davranışı haqqında müşahidə etdiyiniz hər şey (hallüsinasiyalar, kəsilmə tarixləri, yaranan mühakimə) birbaşa bu arxitekturadan irəli gəlir

---

*Növbəti: [02 — Modellərə Baxış](./02-models-overview.md)*
