# Embedding Modelləri vs Generativ Modellər: Arxitektura, İstifadə və Trade-off-lar (Middle)

> Hədəf auditoriyası: RAG, semantic search, clustering və ya hybrid sistemlər quran senior backend developerlər. Bu fayl 01-how-ai-works.md-də izah olunan transformer arxitekturasını genişləndirir — encoder-only vs decoder-only ayrımını, embedding-lərin semantik dual-unu və hansı modelin hansı iş üçün istifadə olunduğunu əhatə edir. Rerankerlər 04-rag folderindəki 06 nömrəli sənəddə; vector DB-lər isə sql/51-vector-databases.md-də.

---

## Mündəricat

1. [İki Fərqli İş Üçün İki Fərqli Alət](#two-jobs-two-tools)
2. [Arxitektura: Encoder-only vs Decoder-only vs Encoder-Decoder](#architectures)
3. [Embedding Nədir Həqiqətən?](#what-are-embeddings)
4. [Təlim Məqsədləri: Contrastive vs MLM vs CLM](#training-objectives)
5. [Similarity Metriqaları: Cosine vs Dot Product vs Euclidean](#similarity-metrics)
6. [Real Embedding Modelləri — 2026 Landscape](#embedding-models-landscape)
7. [Hansı Model Nə Üçün?](#model-selection)
8. [Multilingual Considerations (Azərbaycan dili daxil)](#multilingual)
9. [Latency və Cost Müqayisəsi](#latency-cost-table)
10. [Storage Math və Dimension Seçimi](#storage-math)
11. [Matryoshka Representation Learning](#matryoshka)
12. [Laravel Nümunə: Voyage + Claude RAG Flow](#laravel-example)
13. [Rerankers — Qısa Baxış](#rerankers)
14. [Senior Anti-Pattern-lər](#anti-patterns)
15. [Qərar Çərçivəsi](#decision-framework)

---

## İki Fərqli İş Üçün İki Fərqli Alət

Senior developer sıx-sıx iki modeli qarışdırır, çünki hər ikisi "transformer" adlanır. Amma onlar fərqli məqsədlərə xidmət edir:

```
GENERATİV MODEL (məs., Claude, GPT-4):
  Giriş:  mətn prompt-u
  Çıxış:  yeni mətn (token-lər ardıcıl yaradılır)
  Məqsəd: "Növbəti token nədir?"
  Arxitektura: Decoder-only, causal attention
  Faydası: yazmaq, cavablandırmaq, kod yaratmaq, mühakimə

EMBEDDING MODELİ (məs., Voyage-3, OpenAI text-embedding-3):
  Giriş:  mətn (söz / cümlə / sənəd)
  Çıxış:  sıx vektor (məs., 1024 ədəd)
  Məqsəd: "Bu mətnin semantik 'koordinatı' nədir?"
  Arxitektura: Encoder-only, bidirectional attention
  Faydası: axtarış, klasterləşdirmə, uyğunluq, duplikat aşkarlama
```

Bir yerdə istifadə edilərkən:

```
┌──────────────┐   ┌─────────────┐    ┌────────────┐
│ User Query   │──▶│ Embed model │───▶│ Vector DB  │
└──────────────┘   └─────────────┘    │  (search)  │
                                      └─────┬──────┘
                                            │ Top-K docs
                                            ▼
                                      ┌────────────┐    ┌────────────┐
                                      │ Prompt +   │───▶│  Claude    │
                                      │ retrieved  │    │ (generate) │
                                      │ docs       │    └────────────┘
                                      └────────────┘
```

Bu RAG-ın əsasıdır. Embedding modeli **tapır**; generativ model **yazır**.

---

## Arxitektura: Encoder-only vs Decoder-only vs Encoder-Decoder

Orijinal transformer (2017, Vaswani et al.) encoder-decoder strukturuna sahib idi — tərcümə üçün optimallaşdırılmışdı. Sonrakı illərdə sahə üç arxetipə parçalandı:

### Decoder-only (GPT, Claude, Llama, Gemini)

```
Token-lər girişə verilir
    │
    ▼
┌─────────────────────────────┐
│  Causal Self-Attention      │ ← Token N yalnız 1..N-ə baxa bilər
│  (maska ilə gələcək gizlidir)│
└─────────────────────────────┘
    │
    ▼
LM Head → növbəti token proqnozu
```

Mahiyyət: **avtoreqressiv generasiya** üçün optimallaşdırılıb. Her token əvvəlkiləri görür, sonrakıları görmür. Bu, "növbəti söz nədir" işi üçün uyğundur, lakin **bütöv bir cümlənin ümumi semantikasını** bir vektorda təmsil etmək üçün optimal deyil — çünki attention simmetrik deyil.

Embedding üçün istifadə edilərsə (yeni trend — Claude/GPT üçün `last hidden state` götürmək), nəticə "OK" olur, amma purpose-built encoder-lərdən zəifdir.

### Encoder-only (BERT, MPNet, E5, BGE)

```
Token-lər girişə verilir
    │
    ▼
┌─────────────────────────────┐
│  Bidirectional Self-Attention│ ← Token N həm əvvəli, həm sonranı görür
└─────────────────────────────┘
    │
    ▼
Hidden state-lər (hər token üçün) → pooling → tək vektor
```

Mahiyyət: **bütöv mətnin mənasını anlamaq** üçün optimallaşdırılıb. Bidirectional attention — hər token kontekstin tam görüntüsünü alır. Pooling üsulları:

- `[CLS]` token (BERT-dən): xüsusi token-in hidden state-i
- Mean pooling: bütün token-lərin orta vektoru
- Max pooling: element-wise maximum
- Last-token pooling: decoder-oriented modellərdə

Encoder-only modellər **generasiya edə bilmir** (decoder yoxdur). Yalnız təmsiletmə üçün istifadə olunur: təsnifat, axtarış, klasterləşdirmə.

### Encoder-Decoder (T5, BART, Original Transformer)

```
Giriş mətni         Çıxış mətni
    │                    │
    ▼                    ▼
┌─────────┐         ┌──────────┐
│ Encoder │────────▶│ Decoder  │
│ (bidir) │ cross   │ (causal) │
└─────────┘ attn    └──────────┘
```

Mahiyyət: "seq-to-seq" tapşırıqlar üçün — tərcümə, summarization. Encoder girişi anlayır, decoder çıxışı yaradır. Cross-attention mexanizmi decoder-ə encoder-in çıxışına baxmağa imkan verir.

Bu gün (2026) müstəqil encoder-decoder modellər istifadə azalır — decoder-only (instruction-tuned) eyni tapşırıqları daha sadə interfeys ilə edir. Amma RAG-da hələ də **encoder embedding + decoder generasiya** effektiv hibridə sahibdir.

### Arxitektura Müqayisə Cədvəli

| Xüsusiyyət | Decoder-only | Encoder-only | Encoder-Decoder |
|---|---|---|---|
| Attention | Causal | Bidirectional | Mixed |
| Məqsəd | Generasiya | Təmsil | Seq2Seq |
| Misal | GPT-4, Claude | BERT, BGE-M3 | T5, BART |
| Ölçü | Böyük (7B-1T) | Kiçik (100M-7B) | Orta (220M-11B) |
| Inference cost | Yüksək | Aşağı | Orta |
| Batch-ləmə | Çətin (avtoreq.) | Asan | Orta |

---

## Embedding Nədir Həqiqətən?

Embedding = dense, sabit ölçülü vektor (array of floats). Məsələn, 1024 ədəd. Bu vektor mətnin semantik "koordinatıdır" yüksək ölçülü fəzada.

```
"King"    → [0.21, -0.34, 0.88, ..., 0.05]   (1024 ölçü)
"Queen"   → [0.19, -0.36, 0.91, ..., 0.04]   (King-ə yaxın)
"Car"     → [-0.45, 0.67, -0.12, ..., 0.89]  (King-dən uzaq)
```

### Niyə Yüksək Ölçü?

Mətn spesifik çoxlu "aspektdir": mövzu, ton, ölçü, niyyət, zaman və s. Hər ölçü nominal olaraq bir aspekti təmsil edə bilər (reallıqda, öyrənilmiş ortoqonal olmayan kompozisiya). Az ölçü → məlumat itirilir. Çox ölçü → bahalı storage/compute.

Tipik ölçülər:
- 384 (small modellər): sərfəli, aşağı qaydada
- 768 (BERT base, bir çox E5): orta
- 1024-1536 (OpenAI, Voyage): yüksək dəqiqlik
- 3072+ (large modellər): top performans, amma kost yüksək

### Dense vs Sparse

**Dense** embedding — burada bəhs etdiyimiz. Hər ölçüdə float dəyər.

**Sparse** embedding (BM25, SPLADE) — böyük vektor (100k+ ölçü), amma əksəriyyəti sıfırdır. Dəqiq keyword match-lərdə güclüdür, semantik oxşarlıqda zəifdir.

Hybrid search: hər ikisinin birləşməsi. Ən güclü production pattern-idir.

### Normalizasiya

Bir çox embedding modeli `L2-normalizasiya` edilmiş vektor qaytarır (uzunluğu 1). Bu, cosine similarity-ni dot product-a endirir — hesablama ucuzlaşır.

```
Normalizasiya:
v_normalized = v / ||v||
  burada ||v|| = sqrt(sum(v_i²))

Nəticə: ||v_normalized|| = 1
```

---

## Təlim Məqsədləri: Contrastive vs MLM vs CLM

Modelin hansı **obyektiv** üzərində öyrədilməsi onu nə üçün uyğun edir.

### CLM (Causal Language Modeling)

Decoder-only modellərin əsas təlim hədəfi:

```
Giriş: "The cat sat on the ___"
Hədəf: mat
Loss: cross-entropy düzgün token ilə
```

Generasiya üçün optimaldır. Təmsil üçün (ələ keçirmə) side-effect-dir.

### MLM (Masked Language Modeling)

Encoder-only (BERT) təlim hədəfi:

```
Giriş:  "The cat [MASK] on the mat"
Hədəf: sat
```

15% random maskalama. Model kontekstdən maskalanmış tokeni bərpa etməyi öyrənir. Bidirectional attention burada zəruridir — maskadan həm solu, həm sağı görür.

### Contrastive Learning

Müasir embedding modellərinin (E5, BGE, Voyage) əsas hədəfi. İki vektoru **yaxın** və ya **uzaq** etmək:

```
Müsbət cüt (yaxın):
  Anchor: "How to cook pasta"
  Positive: "Pasta cooking recipe"
  
Mənfi cüt (uzaq):
  Anchor: "How to cook pasta"
  Negative: "JavaScript async await"

Loss (InfoNCE):
  -log( exp(sim(anchor, pos)) / 
        (exp(sim(anchor, pos)) + Σ exp(sim(anchor, neg_i))) )
```

### Hard Negatives

Sadə "random" negativelər asandır. Daha effektivdir — "inanılan amma yanlış" hard negativelər:

```
Anchor:    "Laravel Eloquent relationship types"
Easy neg:  "How to bake cookies" (asan)
Hard neg:  "Laravel Blade template syntax" (eyni domen, fərqli mövzu)
```

Hard negative mining — embedding model təliminin ən mühüm texnikasıdır. Bu səbəbdən top-tier modellər (Voyage, Cohere) fine-tuned BGE-dən daha yaxşıdır.

### Instruction Tuning Embedding-də

Müasir modellər (E5-instruct, BGE-M3, Voyage) "instruction-tuned" embedding dəstəkləyir:

```
query: "Represent this question for retrieving relevant documents: 
       How does prompt caching work in Claude?"
doc:   "Represent this document for retrieval: 
       Claude prompt caching uses KV cache..."
```

Query və document üçün fərqli prefix istifadə olunur. Bu, asymmetric search-də performance artırır.

---

## Similarity Metriqaları: Cosine vs Dot Product vs Euclidean

### Cosine Similarity

```
cos_sim(A, B) = (A · B) / (||A|| × ||B||)
              = -1 to 1 aralığında
```

İki vektorun **istiqamət** oxşarlığını ölçür — uzunluqdan müstəqil. Ən çox istifadə olunur.

### Dot Product

```
dot(A, B) = Σ A_i × B_i
```

Cosine-dən fərqli — uzunluğu nəzərə alır. Əgər vektorlar L2-normalizə olunubsa, dot product = cosine similarity. Daha ucuzdur.

### Euclidean Distance

```
euclidean(A, B) = sqrt(Σ (A_i - B_i)²)
```

Fizik məsafə. Embedding üçün az istifadə olunur — direction daha önəmlidir.

### Manhattan (L1)

```
manhattan(A, B) = Σ |A_i - B_i|
```

Sparse vektorlarda bəzən istifadə olunur.

### Seçim Qaydası

- Yeni model: documentasiyaya bax, tövsiyə olunan metrikanı işlət
- Çoxu: cosine (və ya normalized dot)
- Cohere: dot product (öz normalizasiyasını edir)
- OpenAI: cosine
- Voyage: cosine (L2-normalized çıxış)

---

## Real Embedding Modelləri — 2026 Landscape

### Anthropic Ekosistemi (Voyage — 2024-də alındı)

**Voyage-3 / voyage-3-large** — Anthropic-in tövsiyə etdiyi. 1024 ölçü. RAG üçün top performans MTEB leaderboard-da.

```
voyage-3:         1024 dims, 32k context, balanced
voyage-3-large:   1024 dims, 32k context, highest quality
voyage-3-lite:    512 dims, sərfəli
voyage-code-3:    Kod üçün optimallaşdırılmış
voyage-finance-2: Maliyyə domen-ə fine-tune olunmuş
voyage-law-2:     Hüquqi mətnlər
voyage-multilingual-2: 50+ dil (Azərbaycan daxil)
```

### OpenAI

```
text-embedding-3-small:   1536 dims (Matryoshka, 256-1536 aralığında)
text-embedding-3-large:   3072 dims (256-3072 aralığında)
```

Matryoshka dəstəkləyir — dimension-u runtime-da kəsmək olar.

### Cohere

```
embed-v4.0:        1024-1536 dims, multilingual
embed-english-v3:  1024 dims, yalnız İngilis
embed-multilingual-v3: 1024 dims, 100+ dil
```

"Dense + sparse" hybrid avtomatik. Rerank modeli də təklif edir (rerank-v3).

### Açıq Qaynaq

```
bge-m3 (BAAI):         Multilingual, hybrid (dense + sparse + colbert)
bge-large-en-v1.5:     768 dims, İngilis tapşırıqlarda güclü
e5-mistral-7b-instruct: 4096 dims, nəhəng, yüksək keyfiyyət
nomic-embed-text-v1.5: 768 dims, açıq və ticari istifadəyə uyğun
gte-Qwen2-7B-instruct: 3584 dims, top leaderboard
jina-embeddings-v3:    1024 dims, code + multilingual
```

### Müqayisə

| Model | Dim | Context | Dil | Cost (1M tok) | Keyfiyyət |
|---|---|---|---|---|---|
| voyage-3-large | 1024 | 32k | en + çoxlu | $0.18 | Çox yüksək |
| voyage-3 | 1024 | 32k | en | $0.06 | Yüksək |
| text-embedding-3-large | 3072 | 8k | 100+ | $0.13 | Yüksək |
| text-embedding-3-small | 1536 | 8k | 100+ | $0.02 | Orta-yüksək |
| cohere embed-v4 | 1536 | 128k | 100+ | $0.12 | Yüksək |
| bge-m3 (self-host) | 1024 | 8k | 100+ | GPU $ | Orta-yüksək |

*Qiymətlər 2026-cı il təxmini, rəsmi sənədlərə bax*

---

## Hansı Model Nə Üçün?

### Ümumi RAG (İngilis dokumentlər)

→ **voyage-3** və ya **text-embedding-3-small** (ucuz və effektiv)
→ Keyfiyyət prioritetdirsə: **voyage-3-large** və ya **cohere embed-v4**

### Kod Axtarışı

→ **voyage-code-3** (funksiya, snippet, API search)
→ Alternativ: **jina-embeddings-v3** (kod moda dəstəkləyir)

### Multilingual / Azərbaycanca

→ **cohere embed-multilingual-v3** (100+ dil, Azərbaycanca dəstəklənir)
→ **bge-m3** (self-host, multilingual)
→ **voyage-multilingual-2**

### Domen-spesifik

→ **voyage-finance-2**, **voyage-law-2** (hazır fine-tune)
→ Yoxsa: öz verilərində BGE və ya E5 fine-tune

### Böyük Context (uzun sənədlər)

→ **cohere embed-v4** (128k context)
→ Alternativ: sənəd chunk-lara böl və hər chunk-u ayrı embed et

### Low-latency Self-hosted

→ **bge-base / bge-small** GPU-da
→ **nomic-embed-text-v1.5** (MIT license, commercial ok)

### Qısa Cümlələr (classification, similarity)

→ **sentence-transformers/all-MiniLM-L6-v2** (384 dim, CPU-da işləyir)
→ **bge-small** (fast inference)

---

## Multilingual Considerations

Multilingual embedding modelləri iki strategiya istifadə edir:

### 1. Translation-based

Bütün mətnləri bir dilə (İngilis) tərcümə et, sonra İngilis embedding modeli işlət. Problemi: tərcümə itkisi + dupla latency.

### 2. Native Multilingual Pretraining

Model birbaşa çoxlu dil üzərində öyrədilir. Müasir yanaşma.

### Azərbaycan Dili İçin

Azərbaycanca resurs az olduğuna görə, **native multilingual** modellər daha yaxşıdır. Sıralama:

1. **cohere embed-multilingual-v3** — Azərbaycanca explicit dəstək
2. **bge-m3** — geniş multilingual, Azərbaycanca da var
3. **voyage-multilingual-2** — Anthropic ekosisteminə inteqrasiya
4. **text-embedding-3-large** — dolayı dəstək (internet korpusunda var)

### Sınaq: Dil Drift

Multilingual embedding-lərdə problem: **bir dildəki cümlə başqa dildəki bərabər cümləyə yaxın olmalıdır**. Bu həmişə baş vermir.

```
"Paytaxt Fransa" (AZ) və "Capital of France" (EN) — yaxın olmalıdır.
Amma bəzi modellərdə dil vektor fəzada öz "klaster"ini yaradır və
eyni dildəki uyğunsuz cümlələr fərqli dildəki uyğun cümlədən daha yaxın ola bilər.
```

Test: öz korpusunuzdan nümunələr götürün, manual tərcümə edin, cosine similarity ölçün. Yüksək olmalıdır.

---

## Latency və Cost Müqayisəsi

### API Latency (tipik p50)

```
voyage-3 (API):              80-150ms (single query)
text-embedding-3-small (API): 100-200ms
cohere embed-v4 (API):        120-250ms
bge-m3 (self-host, T4 GPU):   30-80ms (batch 1)
bge-m3 (self-host, batch 32): 150ms toplam (≈5ms/item)
```

### Throughput

Batch-ləmə kritikdir. Tipik API limitləri:
- Voyage: 128 mətn / batch
- OpenAI: 2048 mətn / batch (amma total 8k token limiti)
- Cohere: 96 mətn / batch

### Cost Texnikası

```
Tətbiq: 1M sənəd, hər biri 500 token → 500M token embed et

voyage-3:     500M × $0.06/M = $30
3-small:      500M × $0.02/M = $10
3-large:      500M × $0.13/M = $65
cohere v4:    500M × $0.12/M = $60
bge-m3 (GPU): T4 saatı $0.35, ≈ 100M tok/saat → 5 saat = $1.75
```

Self-host böyük volume-də qiymətdə qalibdir, amma infrastruktur kostu və ops yükü var.

### Re-embedding Maliyyəti

Model dəyişəndə bütün korpus re-embed olunmalıdır. 1M sənəd korpusunda bu $30-65 və 2-5 saatdır (API rate limit-ə qarşı). Plan et.

---

## Storage Math və Dimension Seçimi

```
Storage = N docs × D dimensions × 4 bytes (float32)

Misal 1: 1M sənəd × 1536 dim × 4 bytes = 6.14 GB
Misal 2: 10M sənəd × 1024 dim × 4 bytes = 40.96 GB
Misal 3: 100M sənəd × 3072 dim × 4 bytes = 1.23 TB
```

### Quantization

Float32 → int8 → 4x storage qənaəti (minimal keyfiyyət itkisi):

```
1M × 1536 × 1 byte = 1.5 GB (int8 scalar quantization)
```

Bindary quantization (1 bit) → 32x qənaət, amma recall-u azaldır. Rerank ilə recover oluna bilər.

### Index Overhead

Vector DB (pgvector, Pinecone, Qdrant) HNSW / IVF indekslər əlavə edir:

```
HNSW: ~2x raw vector storage overhead (məs., 6GB → 12GB total)
IVF:  ~1.2x overhead, amma recall biraz aşağı
```

### Production Plan

```
100M sənəd × 1024 dim üçün:
- Float32 raw:    400GB
- Int8 quantized: 100GB
- + HNSW index:    200GB (quantized üzərində)
- RAM üçün:       200GB (vector DB node-larına bölünmüş)

→ Xərc: AWS r7g.8xlarge (256GB RAM) təxminən $1000/ay
```

---

## Matryoshka Representation Learning

Ağıllı texnika: model təlim zamanı elə optimallaşdırılır ki, **vektor-un ilk N ölçüsü də** öz-özündə mənalı təmsildir.

```
Tam vektor (1536 dim): ən yüksək keyfiyyət
İlk 768 dim:           biraz aşağı, amma hələ yaxşı
İlk 256 dim:           kafi keyfiyyət, çox sürətli
İlk 64 dim:            kobud, klasterləşdirmə üçün OK
```

### Praktiki İstifadə

```php
// Hierarchical search:
// 1. 256-dim ilə coarse search (10M sənəd → 1000 kandidat)
// 2. 1536-dim ilə re-rank (1000 → top 10)
// → İkiqat dəqiqlik, 6x sürət
```

Dəstəkləyən modellər: OpenAI text-embedding-3, Nomic embed-text-v1.5, bəzi Voyage modelləri.

Bu, storage/latency optimallaşdırma üçün güclü alətdir.

---

## Laravel Nümunə: Voyage + Claude RAG Flow

Tam pipeline: embedding + retrieval + generasiya.

### 1. Config

```php
// config/services.php
'voyage' => [
    'key' => env('VOYAGE_API_KEY'),
    'model' => env('VOYAGE_MODEL', 'voyage-3'),
],
'anthropic' => [
    'key' => env('ANTHROPIC_API_KEY'),
],
```

### 2. Embedding Client (Saloon)

```php
<?php

namespace App\Integrations\Voyage;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class VoyageConnector extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://api.voyageai.com/v1';
    }

    protected function defaultAuth(): \Saloon\Http\Auth\TokenAuthenticator
    {
        return new \Saloon\Http\Auth\TokenAuthenticator(config('services.voyage.key'));
    }

    protected function defaultHeaders(): array
    {
        return ['Content-Type' => 'application/json'];
    }
}
```

```php
<?php

namespace App\Integrations\Voyage\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class EmbedRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        public array $texts,
        public string $inputType = 'document', // 'document' or 'query'
        public string $model = 'voyage-3',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/embeddings';
    }

    protected function defaultBody(): array
    {
        return [
            'input' => $this->texts,
            'model' => $this->model,
            'input_type' => $this->inputType,
        ];
    }
}
```

### 3. EmbeddingService

```php
<?php

namespace App\Services;

use App\Integrations\Voyage\VoyageConnector;
use App\Integrations\Voyage\Requests\EmbedRequest;
use Illuminate\Support\Facades\Cache;

class EmbeddingService
{
    public function __construct(private VoyageConnector $client) {}

    /**
     * Batch şəklində mətnlər üçün embedding.
     * 128 mətn / sorğu limitinə riayət edir.
     */
    public function embedDocuments(array $texts): array
    {
        return $this->embedBatch($texts, 'document');
    }

    public function embedQuery(string $text): array
    {
        $cacheKey = 'embed_query:' . hash('xxh3', $text);
        return Cache::remember($cacheKey, 3600, function () use ($text) {
            $result = $this->embedBatch([$text], 'query');
            return $result[0];
        });
    }

    private function embedBatch(array $texts, string $type): array
    {
        $results = [];
        foreach (array_chunk($texts, 128) as $chunk) {
            $response = $this->client->send(new EmbedRequest(
                texts: $chunk,
                inputType: $type,
                model: config('services.voyage.model'),
            ));

            foreach ($response->json('data') as $item) {
                $results[] = $item['embedding'];
            }
        }
        return $results;
    }
}
```

### 4. Document Eloquent Model (pgvector)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use Pgvector\Laravel\HasNeighbors;

class Document extends Model
{
    use HasNeighbors;

    protected $fillable = ['title', 'content', 'embedding', 'metadata'];

    protected $casts = [
        'embedding' => Vector::class,
        'metadata' => 'array',
    ];
}
```

Migration:

```php
Schema::create('documents', function (Blueprint $t) {
    $t->id();
    $t->string('title');
    $t->text('content');
    $t->vector('embedding', 1024); // voyage-3 ölçüsü
    $t->jsonb('metadata')->nullable();
    $t->timestamps();
});

// HNSW index (PostgreSQL + pgvector 0.7+)
DB::statement('CREATE INDEX documents_embedding_idx 
               ON documents 
               USING hnsw (embedding vector_cosine_ops) 
               WITH (m = 16, ef_construction = 64)');
```

### 5. Ingestion Job

```php
<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class IngestDocumentBatchJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public array $documents) {}

    public function handle(EmbeddingService $embeddings): void
    {
        $texts = array_column($this->documents, 'content');
        $vectors = $embeddings->embedDocuments($texts);

        foreach ($this->documents as $i => $doc) {
            Document::create([
                'title' => $doc['title'],
                'content' => $doc['content'],
                'embedding' => $vectors[$i],
                'metadata' => $doc['metadata'] ?? null,
            ]);
        }
    }
}
```

### 6. RAG Service

```php
<?php

namespace App\Services;

use Anthropic\Anthropic;
use App\Models\Document;

class RAGService
{
    public function __construct(
        private EmbeddingService $embeddings,
        private Anthropic $claude,
    ) {}

    public function answer(string $question, int $topK = 5): string
    {
        // 1. Query embedding
        $queryVector = $this->embeddings->embedQuery($question);

        // 2. Retrieve top-K
        $docs = Document::query()
            ->nearestNeighbors('embedding', $queryVector, 'cosine')
            ->limit($topK)
            ->get();

        if ($docs->isEmpty()) {
            return "Bu sual üzrə məlumat tapılmadı.";
        }

        // 3. Build prompt
        $context = $docs->map(fn($d, $i) =>
            "<doc index=\"{$i}\" title=\"{$d->title}\">\n{$d->content}\n</doc>"
        )->implode("\n\n");

        $systemPrompt = <<<SYSTEM
Siz korporativ məlumat bazası üzrə köməkçisiniz. Yalnız aşağıdakı
sənədlərə əsasən cavab verirsiniz. Sənəddə cavab yoxdursa: "Bu
haqda məlumatım yoxdur" deyin. Hər iddianız üçün [doc N] referans
edin.
SYSTEM;

        $userPrompt = <<<USER
<documents>
{$context}
</documents>

Sual: {$question}
USER;

        // 4. Generate
        $response = $this->claude->messages()->create([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 1024,
            'temperature' => 0.2,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        return $response->content[0]->text;
    }
}
```

### 7. İstifadə

```php
Route::post('/api/ask', function (RAGService $rag, Request $r) {
    $answer = $rag->answer($r->input('question'));
    return response()->json(['answer' => $answer]);
});
```

---

## Rerankers — Qısa Baxış

Embedding axtarışı sürətlidir, amma dəqiqliyi **rerank** mərhələsi artırır. Reranker cüt (query, doc) qəbul edir və relevance skoru qaytarır — bu, **cross-encoder** texnikasıdır.

```
Pipeline:
1. Embedding search (dense): top-100 kandidat
2. Rerank (cross-encoder):   top-100 → top-10
3. Generation:               top-10 → cavab
```

Tövsiyə olunan modellər:
- `cohere rerank-v3` (API)
- `voyage rerank-2`
- `BAAI/bge-reranker-v2-m3` (self-host)

Ətraflı: 04-rag/22-reranking-models.md.

---

## Senior Anti-Pattern-lər

### 1. Claude-i Embedding Kimi İstifadə Etmək

"Claude ümumi intellekt-dir, `mesaj: Bu iki mətn bənzərdir? 1-10 qiymətləndir` istifadə edə bilərəm" — olmaz.

- 100x bahalı
- 50x yavaş
- Qeyri-deterministik
- Batch-ləmə çətin
- Semantik similarity üçün purpose-built deyil

Embedding modeli istifadə et.

### 2. Yalnız Embedding-ə Güvənmək (rerank-siz)

Embedding "semantik" oxşarlıq verir, amma keyword-dəqiq match-ləri bəzən qaçırır. Hybrid (dense + sparse/BM25) və/və ya rerank tətbiq et.

### 3. Chunk-ı Çox Böyük Saxlamaq

10k token chunk → embedding bir-neçə mövzunun orta vektoru olur, dəqiqlik itir. Optimal: 200-800 token chunk-lar.

### 4. Chunk-ı Çox Kiçik Saxlamaq

50 token chunk → kontekst itir, model düzgün cavab verə bilməz. Balans: 200-500 token, 50 token overlap.

### 5. Model Versiyası Migrate Edəndə İkisini Qarışdırmaq

Yeni model versiyasına keçəndə köhnə və yeni embedding fəzaları uyğunsuzdur. Ya hamısını re-embed et, ya da iki fəzanı paralel saxla (feature flag ilə keçid).

### 6. Storage-ı İgnore Etmək

100M × 3072 dim float32 = 1.2TB. Bu ayda $200 EBS-ə bərabərdir — rahat bir layihəni baha edir. Dimension azalt (Matryoshka) və ya quantization et.

### 7. Rate Limit-i Hesablamamaq

Voyage: 300 RPM tipik. 1M sənəd ingest etmək üçün batch-ləmə + parallel Jobs lazımdır. Plan etmədən "bir günə hazır olacaq" deməyin.

### 8. Encoder Modelini "Ucuz GPT" Kimi Qavramaq

Encoder-only model **generasiya edə bilmir**. Onu "yüngül GPT" sanmaq arxitektural yanlışlıqdır.

### 9. Cosine vs Dot Qarışdırmaq

Vektorlar L2-norm-lu deyilsə, dot product yanlış sıralanma verir. Model dokumentasiyasına bax və uyğun metrika işlət.

### 10. Fine-tune Etmədən Domen-Spesifik Tapşırıq

Tibbi, hüquqi, kod domen-lərində general-purpose embedding suboptimaldır. Ya domen-spesifik hazır modeldən istifadə et (voyage-law-2 və s.), ya da BGE/E5 fine-tune et.

---

## Qərar Çərçivəsi

### Embedding model seçərkən

```
Sorğularım nə dildədir?
  └── Yalnız İngilis?
  │      └── RAG-focused? → voyage-3 / text-embedding-3-small
  │      └── Kod? → voyage-code-3
  │      └── Böyük context? → cohere embed-v4
  └── Multilingual?
         └── Azərbaycan daxil? → cohere embed-multilingual-v3 / bge-m3
         └── Global? → voyage-multilingual-2 / text-embedding-3-large

Budget necədir?
  └── Aşağı (test / POC) → text-embedding-3-small
  └── Orta (production) → voyage-3
  └── Yüksək (top keyfiyyət) → voyage-3-large / cohere embed-v4

Self-host lazımdır?
  └── Bəli → bge-m3 (GPU) / nomic-embed (CPU üçün kiçik variantlar)
  └── Yox → API seç
```

### Generativ vs Embedding qərar nöqtələri

| İş | Model Tipi |
|---|---|
| Cavab yaratmaq | Generative (Claude) |
| Mətni klasifikasiya etmək | Embedding + basit classifier |
| Uyğun sənəd tapmaq | Embedding |
| "Bu iki cümlə bənzərmi?" | Embedding |
| Summarize | Generative |
| Parafraz | Generative |
| Duplikatı aşkar etmək | Embedding |
| Niyyət tanımaq (5 kateqoriya) | Embedding + threshold |
| Niyyət tanımaq (100+ niyyət) | Generative (structured output) |

---

## Xülasə

- Generativ (decoder-only) və embedding (encoder-only) modellər fərqli məqsədlərə xidmət edir
- Embedding = mətnin sabit ölçülü semantik vektoru; cosine similarity ilə müqayisə edilir
- Təlim: generativ üçün CLM, embedding üçün contrastive learning + hard negatives
- 2026-da tövsiyə olunan embedding modellərı: voyage-3, text-embedding-3, cohere embed-v4, bge-m3
- Multilingual (Azərbaycan) üçün: cohere / voyage / bge-m3
- Dimension və storage kritik məsələdir — Matryoshka və quantization ilə optimizə et
- RAG-da hybrid (dense + sparse) + rerank ən güclü kombinasiyadır
- Claude-i embedding üçün istifadə etmə, embedding-i generasiya üçün istifadə etmə — hərəsi öz işi üçündür

---

*Növbəti: [11 — Reasoning Models](./08-reasoning-models.md)*
