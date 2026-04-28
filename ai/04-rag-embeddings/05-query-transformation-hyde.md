# Query Transformation: HyDE, Multi-Query, Step-Back və Sub-Query Decomposition (Senior)

> **Oxucu kütləsi:** Senior backend developerlər (PHP/Laravel), production RAG-da retrieval keyfiyyətini yaxşılaşdırmaq istəyənlər.
> **Bu faylın qonşu fayllarla fərqi:**
> - `06-reranking-hybrid-search.md` — retrieval-dan **sonra** gələn mərhələlər (BM25, fusion, rerank) və HyDE-nin sadə giriş nümunəsi. Bu fayl **retrieval-dan əvvəl** sorğu üzərində aparılan transformasiyaların tam dərinliyidir.
> - `07-contextual-retrieval.md` — **indexing** tərəfində sənədi zənginləşdirir. Bu fayl **query** tərəfini dəyişdirir.
> - `03-rag-architecture.md` — pipeline overview. Burada query transformation mərhələsinin bütün alt-strategiyaları ilə izahı.
> - `11-rag-evaluation-rerank.md` — eval framework. Burada konkret query transformation texnikaları üçün A/B test nümunələri.

---

## Mündəricat

1. Problem: niyə xam sorğu pis retrieval verir
2. Query Transformation taksonomiyası
3. HyDE — Hypothetical Document Embeddings
4. Multi-Query — N reformulation + RRF
5. Step-Back Prompting — abstraksiya səviyyəsini qaldır
6. Sub-Query Decomposition — çoxaddımlı sualların parçalanması
7. Query Expansion (sinonim, synonym injection)
8. RAG-Fusion — Multi-Query + RRF pattern-ı
9. Xərc və latency riyaziyyatı
10. Laravel implementation: QueryTransformer strategy pattern
11. Prompt templates
12. A/B testing — "transformation həqiqətən kömək edirmi?"
13. Anti-pattern-lar və qərar cədvəli

---

## 1. Problem: Niyə Xam Sorğu Pis Retrieval Verir

RAG-ın gizli uğursuzluğu: real istifadəçi sorğuları **qısa, ambigua və sənədlərdəki lüğətdən fərqlidir**.

### Real sorğu nümunələri

Dəstək botundan gələn log-lar:
```
1. "refund"                             -- 1 söz, heç bir kontekst
2. "bu niye işləmir"                     -- mənasız demək olar
3. "login sonra error 500 nece"         -- multi-hop + grammar pozuq
4. "nişan planımı necə dəyişim"          -- sorğu vs sənəd leksik fərqi
                                         -- sənəddə "enrollment modify"
5. "niyə apple-in q3-də gəliri artıb"    -- iki fakt birləşməsi
```

Embedding modeli bu sorğuları bu şəkildə sənədlərlə eşləməkdə zəifdir:
- **Qısa sorğular (1-3 söz)**: embedding siqnalı zəifdir, semantic noise yüksəkdir
- **Qrammatika pozuntuları**: model trenirovka edildiyi distribution-dan kənardadır
- **Lüğət asimmetriyası**: "refund" vs "money-back guarantee", "login" vs "authentication failure"
- **Multi-hop**: "A → B → C səbəb zənciri" tək retrieval call-da tapıla bilməz

### Query Transformation nə edir

Xam sorğunu LLM vasitəsilə **daha retrieval-friendly** formaya çevirir. Variant-lar:

| Texnika | Nə edir | Ən yaxşı işlədiyi case |
|---------|---------|------------------------|
| HyDE | Hipotetik cavab generasiya edir, onu embed edir | Qısa, ambigua sorğular |
| Multi-Query | N fərqli reformulation yaradır | Geniş query intent |
| Step-Back | Sorğunu abstract edir | Spesifik → ümumi bilik |
| Sub-Query Decomposition | Parçalara ayırır | Multi-hop, complex |
| Query Expansion | Sinonim/related term əlavə edir | Texniki jarqon |

---

## 2. Query Transformation Taksonomiyası

```
                    User Query
                         │
                         ▼
              ┌──────────────────────┐
              │ Classifier (optional)│
              │  - simple / complex  │
              │  - factual / synth   │
              └──────────────────────┘
                         │
          ┌──────────────┼──────────────┐
          │              │              │
          ▼              ▼              ▼
  ┌───────────┐  ┌──────────────┐  ┌──────────────┐
  │   HyDE    │  │ Multi-Query  │  │  Sub-Query   │
  │           │  │              │  │ Decomposition│
  │ hypothet. │  │ N variants   │  │ A → B, C, D  │
  │ document  │  │              │  │              │
  └───────────┘  └──────────────┘  └──────────────┘
          │              │              │
          ▼              ▼              ▼
         embed          embed          embed (each)
          │              │              │
          ▼              ▼              ▼
      retrieval      retrieval       retrieval (each)
          │              │              │
          │              ▼              ▼
          │             RRF            merge
          │              │              │
          └──────────────┴──────────────┘
                         │
                         ▼
                    (rerank)
                         │
                         ▼
                     top-k to LLM
```

Query transformation `HyDE OR Multi-Query OR ...` kimi mutual-exclusive deyil. Onları **kombinasiya** etmək olar (məs., Sub-Query Decomposition + HyDE on each sub-query).

---

## 3. HyDE — Hypothetical Document Embeddings

### 3.1 Əsas ideya

2022-ci ildə (Gao et al., "Precise Zero-Shot Dense Retrieval without Relevance Labels") önə sürülən texnika. Sorğunu birbaşa embed etmək əvəzinə:

1. LLM-dən **sorğuya uyğun hipotetik sənəd** generasiya etməsini xahiş et
2. Bu hipotetik sənədi embed et
3. Hipotetik embedding ilə vector search apar
4. Real sənədlər qaytarılır

**Niyə işləyir?** Embedding fəzası "soruğu"-"sənəd" paralellərini deyil, "sənəd"-"sənəd" paralellərini daha dəqiq modelləşdirir. Qısa sorğu embedding-i iki tərəfdə uzun sənədlərlə zəif eşlənir, amma hipotetik sənəd embedding-i gerçək sənədlərlə yaxşı eşlənir.

### 3.2 Nümunə

**Sorğu**: "refund"

**Hipotetik sənəd** (Haiku tərəfindən):
```
Our refund policy allows customers to request a full refund within 30 days 
of purchase. To initiate a refund, customers must submit a request through 
their account dashboard or contact support. Refunds are processed to the 
original payment method within 5-10 business days. Certain items may be 
excluded from refund eligibility, including digital downloads after access 
has been granted.
```

Bu mətn həqiqi refund policy sənədinə embedding fəzasında **çox daha yaxındır** "refund" kəlməsindən.

### 3.3 HyDE üstünlükləri və çatışmazlıqları

**Üstünlüklər**:
- Qısa, ambigua sorğularda dramatik uplift (20-40%)
- Lüğət asimmetriyasını avtomatik həll edir
- Hər domainə adaptasiya olur (LLM domain-specific vocab generasiya edir)

**Çatışmazlıqlar**:
- Hər sorğu üçün bir LLM call (latency + xərc)
- LLM-in hallüsinasiyası retrieval-ı yanlış istiqamətə yönləndirə bilər (məs., uydurulmuş "article 5.2" gerçək sənədi kənarlaşdırır)
- Spesifik ID/kod axtarışında pis işləyir (LLM "E-7834" xəta kodunu hipotetik sənədə düzgün yerləşdirə bilməz)

### 3.4 HyDE Variantları

**Single-document HyDE** (klassik):
- 1 hipotetik sənəd generasiya et, embed et, search et.

**Multi-document HyDE**:
- 3-5 fərqli "zaviyədən" sənəd generasiya et
- Hər birini embed et, axtar
- Nəticələri RRF ilə birləşdir
- Daha yaxşı keyfiyyət, 3-5× xərc

**Hybrid HyDE** (query + hypothetical doc):
- Orijinal sorğunun embedding-i + hipotetik sənədin embedding-i
- Weighted average və ya RRF

```php
$queryEmb = $embedder->embed($query);
$hydeEmb  = $embedder->embed($hydeService->generateDoc($query));

// 0.3 real query + 0.7 hypothetical
$combinedEmb = array_map(
    fn($q, $h) => 0.3 * $q + 0.7 * $h,
    $queryEmb, $hydeEmb
);
```

---

## 4. Multi-Query — N Reformulation + RRF

### 4.1 Əsas ideya

Bir sorğunun yalnız bir "doğru" embedding-i yoxdur. LLM-dən N fərqli reformulation istə, hər birini axtar, nəticələri birləşdir.

**Orijinal**: "How do I reset my password?"

**LLM-dən 4 reformulation**:
1. "password reset procedure"
2. "I forgot my password, how can I recover it?"
3. "account recovery steps when credentials are lost"
4. "change password when unable to log in"

Hər biri fərqli sənədləri tapa bilər:
- #1 → "Password Management Guide"
- #2 → "Account Recovery FAQ"
- #3 → "Identity Recovery Process"
- #4 → "Troubleshooting Login Issues"

RRF ilə birləşdirilmiş nəticələr tək-sorğu axtarışından daha zəngin olur.

### 4.2 Pipeline

```
query ──► LLM ──► [q1, q2, q3, q4, q5]
                      │
                      ▼
                  embed each
                      │
         ┌────────────┼────────────┐
         ▼            ▼            ▼
   vector search  vector search  ...
   (q1, top-30)  (q2, top-30)
         │            │
         └────────────┴────────────┘
                      │
                      ▼
              RRF fusion (k=60)
                      │
                      ▼
                 top-10 results
```

### 4.3 Multi-Query-də diversity-nin əhəmiyyəti

Reformulation-lar bir-birinə çox oxşar olsa, RRF fayda gətirməz. Prompt-da **diversity** tələb et:

```
Generate 5 DIFFERENT reformulations of this query. Each reformulation should 
approach the query from a different angle:
1. A paraphrase using different vocabulary
2. A more specific, detailed version
3. A more abstract, conceptual version
4. A version phrased as a how-to question
5. A version focused on the underlying goal

Original: "{query}"

Return as JSON array of strings.
```

### 4.4 N-in optimal dəyəri

| N (reformulation count) | Recall uplift | Latency | Xərc |
|-------------------------|---------------|---------|------|
| 1 (no transform) | baseline | +0 ms | +$0 |
| 3 | +15% | +400 ms | +$0.001 |
| 5 | +22% | +500 ms | +$0.002 |
| 10 | +24% | +800 ms | +$0.004 |

**Praktik qayda**: N=3-5 sweet spot. N>5 marginal return.

---

## 5. Step-Back Prompting — Abstraksiya Səviyyəsini Qaldır

### 5.1 Əsas ideya

2023-cü ildə Google Research (Zheng et al., "Take a Step Back") təqdim etdi. Bəzi spesifik sorğular cavab vermək üçün **ümumi prinsipləri** tapmağı tələb edir.

**Spesifik sorğu**: "Why did Apple's revenue grow 12% in Q3 2024?"

**Step-back sorğu**: "What factors typically drive Apple's quarterly revenue growth?"

Step-back sorğu retrieval-da daha çox uyğun sənəd tapır (genişlik), sonra LLM spesifik suala cavab verir (dəqiqlik).

### 5.2 Nə vaxt faydalıdır

- **Factoid + context requires reasoning**: "X-in Y olmasının səbəbi" tipli suallar
- **Niche sorğular**: spesifik hadisə/rəqəm haqqında, amma kontekst geniş sənəddədir
- **Temporal sorğular**: "2024-cü ilin üçüncü rübünün X-i" — bəzən 2024-ün ümumi trendi daha çox kontekst verir

### 5.3 Step-back + original dual retrieval

```
          query: "Why did Apple Q3 2024 revenue grow 12%?"
                            │
                 ┌──────────┴──────────┐
                 │                     │
                 ▼                     ▼
          LLM: step-back       (use original)
                 │                     │
                 ▼                     │
     "What drives Apple's              │
      quarterly revenue?"              │
                 │                     │
                 ▼                     ▼
            retrieval              retrieval
            (10 chunks)           (10 chunks)
                 │                     │
                 └──────────┬──────────┘
                            │
                            ▼
                       merge + dedupe
                            │
                            ▼
                    top-10 to LLM
```

### 5.4 Step-back prompt template

```
Given a user question, generate a more abstract "step-back" question that 
asks about the underlying principle, category, or general context.

User question: "{query}"

Rules:
- The step-back question should be broader but still related.
- It should help retrieve supporting background information.
- Do not add new entities or time periods.

Step-back question:
```

---

## 6. Sub-Query Decomposition — Çoxaddımlı Sualların Parçalanması

### 6.1 Əsas ideya

Bəzi sorğular **tək retrieval** ilə cavablandırıla bilməz:

**Kompleks sorğu**: "Which Apple product had the highest revenue growth in Q3 2024, and how does that compare to the same product's Q3 2023 performance?"

Bu sorğuda **üç alt-sual** var:
1. "Apple Q3 2024-də ən yüksək revenue growth-u olan məhsul hansıdır?"
2. "O məhsulun Q3 2023-də performansı nə idi?"
3. "İki rəqəmin müqayisəsi nədir?"

Tək retrieval call bu üç məlumat qrupunu eyni anda tapa bilmir.

### 6.2 Decomposition pipeline

```
complex query
    │
    ▼
  LLM: decompose
    │
    ▼
[sub_q1, sub_q2, sub_q3]
    │
    ├──► retrieve(sub_q1) ──► chunks_1
    ├──► retrieve(sub_q2) ──► chunks_2
    └──► retrieve(sub_q3) ──► chunks_3
            │
            ▼
   merge + dedupe
            │
            ▼
      context for LLM
            │
            ▼
         LLM answer
```

### 6.3 Decomposition prompt

```
Break this complex question into 2-4 simpler sub-questions that can each be 
answered independently by searching a knowledge base.

Complex question: "{query}"

Rules:
- Each sub-question must be answerable from documents (not reasoning).
- Sub-questions should not overlap.
- If the question is already simple, return it unchanged.

Return as JSON array of strings:
```

### 6.4 Iterative vs Parallel decomposition

**Parallel**: Bütün sub-query-ləri eyni anda retrieve et (sadə, sürətli).

**Iterative** (Agentic RAG-da təsvir olunur — fayl 10-a bax): Bir sub-query-nin cavabı növbətini müəyyən edir.

Məsələn:
- sub_q1 retrieve et → "iPhone ən yüksək growth-u göstərdi" kəşf et
- sub_q2 dinamik yarat: "iPhone Q3 2023 performance nədir?" (iPhone-u bildikdən sonra)
- sub_q2 retrieve et

Iterative daha dəqiqdir, amma multi-turn latency və xərc yaradır.

---

## 7. Query Expansion — Sinonim Injection

### 7.1 Əsas ideya

Orijinal sorğuya **əlavə sinonim və related terminlər** əlavə et, sonra embed et. Bu, sadə amma güclü texnikadır.

**Orijinal**: "database connection pool"

**Expanded**:
```
database connection pool, DB connection pool, connection pooling, 
PDO pool, HikariCP, pgBouncer, connection reuse, database session management
```

### 7.2 Təhlükə: over-expansion

Çox sinonim əlavə etmək embedding-i **dilute** edir. Orijinal sorğunun spesifikliyi itir. Praktiki qayda: **3-7 əlavə termin**.

### 7.3 Expansion strategiyaları

1. **LLM-based**: Haiku-dan sinonim list istə
2. **Lexical** (ucuz): WordNet, thesaurus API
3. **Corpus-based**: Öz sənədlərinizdən tez-tez yanaşı görünən terminlər (word2vec, co-occurrence matrix)
4. **Hybrid**: LLM + corpus filter (yalnız korpusda mövcud olan terminləri qəbul et)

### 7.4 Expansion BM25 üçün daha faydalıdır

Vector embedding artıq "semantic neighbors"-u əhatə edir — expansion marginal uplift verir. Amma **BM25** dəqiq termin uyğunluğu istədiyi üçün expansion keyfiyyəti çox yaxşılaşdırır:

```sql
-- Əvəz: WHERE content_tsv @@ to_tsquery('database & connection & pool')
-- İlə: WHERE content_tsv @@ to_tsquery(
--   'database | connection | pool | pooling | pgbouncer | hikaricp'
-- )
```

---

## 8. RAG-Fusion — Multi-Query + RRF Pattern

### 8.1 Əsas ideya

RAG-Fusion (Raudaschl, 2023) Multi-Query + RRF-in standart patter-ıdır. Əslində Multi-Query-nin daha formal bir variantıdır:

1. LLM: N reformulation (adətən 4)
2. Hər birini paralel retrieve et
3. RRF ilə birləşdir
4. Top-k-nı LLM-ə göndər

### 8.2 Multi-Query-dən fərqi

RAG-Fusion yalnız **RRF-i defolt** edir və N=4-ü standart kimi istifadə edir. Multi-Query-nin spesifik instansiyası.

### 8.3 Niyə bu geniş qəbul olundu

- Sadə implement edilir
- RRF mütləq ballar problemini həll edir (fayl 06-ya bax)
- Hər istənilən retriever-lə işləyir (vector, BM25, hybrid)

---

## 9. Xərc və Latency Riyaziyyatı

### 9.1 Transformation xərcləri

Əksər transformation-lar 1 LLM call tələb edir. Haiku 4.5 ilə:
- Input: ~200-500 token (sorğu + system prompt)
- Output: ~100-300 token (reformulation-lar və ya hypothetical doc)
- Per-call xərc: ~$0.001-0.003

### 9.2 Latency profili

| Texnika | P50 əlavə latency | P99 | Qeyd |
|---------|-------------------|-----|------|
| No transform | 0 ms | 0 ms | baseline |
| HyDE | 400-600 ms | 1200 ms | 1 LLM call + 1 embed |
| Multi-Query (N=4) | 500-700 ms | 1500 ms | 1 LLM + 4 parallel embed |
| Step-Back | 400-500 ms | 1000 ms | 1 LLM + 2 retrieval |
| Sub-Query Decomp (3 subs) | 600-900 ms | 2000 ms | 1 LLM + 3 retrievals |
| Query Expansion (LLM-based) | 300-500 ms | 800 ms | 1 LLM + 1 retrieval |
| Query Expansion (lexical) | 10-30 ms | 100 ms | dict lookup, LLM yox |

### 9.3 Yüksək-volume xidmətlərdə xərc

100K sorğu/gün olan chat botda:

| Strategy | Günlük xərc (Haiku) | Aylıq |
|----------|---------------------|-------|
| No transform | $0 | $0 |
| HyDE (hər sorğuda) | $100-300 | $3K-9K |
| Multi-Query (hər sorğuda) | $150-400 | $4.5K-12K |
| Conditional (20% sorğuda) | $30-80 | $1K-2.5K |

Default olaraq **hər sorğuda transform** tətbiq etmək xərc baxımından aqressivdir. Classifier əsaslı selection (bax §13) daha praktikdir.

---

## 10. Laravel Implementation: QueryTransformer Strategy Pattern

### 10.1 Interface

```php
<?php
// app/Services/RAG/QueryTransformation/QueryTransformer.php

namespace App\Services\RAG\QueryTransformation;

interface QueryTransformer
{
    /**
     * Xam sorğunu bir və ya bir neçə transformed sorğuya çevir.
     * Hər transformed sorğu ayrıca retrieval üçün istifadə oluna bilər.
     *
     * @param string $query Orijinal istifadəçi sorğusu
     * @return TransformedQueries Transformation nəticəsi
     */
    public function transform(string $query): TransformedQueries;
}

class TransformedQueries
{
    /**
     * @param string $original Orijinal sorğu
     * @param array<string> $queries Retrieval üçün sorğular
     * @param string $strategy İstifadə olunan strategy adı
     * @param array $metadata Debug / observability üçün
     */
    public function __construct(
        public readonly string $original,
        public readonly array $queries,
        public readonly string $strategy,
        public readonly array $metadata = [],
    ) {}
}
```

### 10.2 HyDE implementation

```php
<?php
// app/Services/RAG/QueryTransformation/HydeTransformer.php

namespace App\Services\RAG\QueryTransformation;

use Illuminate\Support\Facades\Http;

class HydeTransformer implements QueryTransformer
{
    private const MODEL = 'claude-haiku-4-5';
    private const PROMPT = <<<'PROMPT'
Write a short, factual document passage (2-3 paragraphs) that would answer 
this question if it appeared in a company knowledge base.

Write in the voice of documentation — specific, concise, no meta-commentary 
like "this document explains". Just write the content directly.

If the question contains specific identifiers (error codes, product names, 
dates), include them literally in the passage.

Question: %s

Passage:
PROMPT;

    public function transform(string $query): TransformedQueries
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(30)
        ->post('https://api.anthropic.com/v1/messages', [
            'model' => self::MODEL,
            'max_tokens' => 300,
            'messages' => [[
                'role' => 'user',
                'content' => sprintf(self::PROMPT, $query),
            ]],
        ]);

        $hypotheticalDoc = trim($response->json('content.0.text') ?? '');

        if ($hypotheticalDoc === '') {
            // Fallback: orijinal sorğu
            return new TransformedQueries(
                original: $query,
                queries: [$query],
                strategy: 'hyde-fallback',
            );
        }

        return new TransformedQueries(
            original: $query,
            // HyDE-də yalnız hipotetik sənədi embed edirik
            // (orijinal sorğu deyil — bax dual retrieval variantı §10.6)
            queries: [$hypotheticalDoc],
            strategy: 'hyde',
            metadata: [
                'hypothetical_doc_length' => strlen($hypotheticalDoc),
                'hypothetical_doc' => $hypotheticalDoc,
            ],
        );
    }
}
```

### 10.3 Multi-Query implementation

```php
<?php
// app/Services/RAG/QueryTransformation/MultiQueryTransformer.php

namespace App\Services\RAG\QueryTransformation;

use Illuminate\Support\Facades\Http;

class MultiQueryTransformer implements QueryTransformer
{
    public function __construct(
        private int $numVariants = 4,
    ) {}

    public function transform(string $query): TransformedQueries
    {
        $prompt = <<<PROMPT
Generate {$this->numVariants} DIFFERENT reformulations of the following query. 
Each should approach it from a different angle:
1. A paraphrase using alternative vocabulary
2. A more specific, detailed version
3. A more abstract, conceptual version
4. A version phrased as a how-to question

Original: "{$query}"

Return ONLY a JSON array of strings, no other text:
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(30)
        ->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 512,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $raw = $response->json('content.0.text') ?? '[]';
        $variants = $this->parseJsonArray($raw);

        if (empty($variants)) {
            return new TransformedQueries(
                original: $query,
                queries: [$query],
                strategy: 'multi-query-fallback',
            );
        }

        // Original sorğunu da əlavə et — bəzən baseline ən yaxşısıdır
        $queries = array_merge([$query], $variants);

        return new TransformedQueries(
            original: $query,
            queries: array_unique($queries),
            strategy: 'multi-query',
            metadata: ['variant_count' => count($variants)],
        );
    }

    private function parseJsonArray(string $raw): array
    {
        // LLM bəzən markdown code block ilə cavab verir
        $raw = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $raw);
        $parsed = json_decode(trim($raw), true);

        return is_array($parsed) ? array_values(array_filter($parsed, 'is_string')) : [];
    }
}
```

### 10.4 Step-Back implementation

```php
<?php
// app/Services/RAG/QueryTransformation/StepBackTransformer.php

namespace App\Services\RAG\QueryTransformation;

use Illuminate\Support\Facades\Http;

class StepBackTransformer implements QueryTransformer
{
    public function transform(string $query): TransformedQueries
    {
        $prompt = <<<PROMPT
Given this specific question, generate a broader "step-back" question that 
asks about the general principle, category, or background context.

Rules:
- The step-back must be more abstract but still related
- Do not add new entities or time periods
- Return only the step-back question, no preamble

Specific question: "{$query}"

Step-back question:
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(20)
        ->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 150,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $stepBack = trim($response->json('content.0.text') ?? '');

        $queries = $stepBack !== '' ? [$query, $stepBack] : [$query];

        return new TransformedQueries(
            original: $query,
            queries: $queries,
            strategy: 'step-back',
            metadata: ['step_back_query' => $stepBack],
        );
    }
}
```

### 10.5 Sub-Query Decomposition implementation

```php
<?php
// app/Services/RAG/QueryTransformation/SubQueryDecomposer.php

namespace App\Services\RAG\QueryTransformation;

use Illuminate\Support\Facades\Http;

class SubQueryDecomposer implements QueryTransformer
{
    public function transform(string $query): TransformedQueries
    {
        $prompt = <<<PROMPT
Break this complex question into 2-4 simpler sub-questions that can each be 
answered independently by searching a knowledge base.

Rules:
- Each sub-question must be answerable from documents (not pure reasoning)
- Sub-questions should not overlap in what they ask for
- If the question is already simple, return it unchanged as the only item

Complex question: "{$query}"

Return ONLY a JSON array of sub-questions, no other text:
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(30)
        ->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 512,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $raw = $response->json('content.0.text') ?? '[]';
        $raw = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $raw);
        $subQueries = json_decode(trim($raw), true) ?? [$query];

        $subQueries = array_values(array_filter($subQueries, 'is_string'));

        return new TransformedQueries(
            original: $query,
            queries: $subQueries,
            strategy: 'sub-query-decomposition',
            metadata: ['sub_query_count' => count($subQueries)],
        );
    }
}
```

### 10.6 Retrieval-a inteqrasiya

```php
<?php
// app/Services/RAG/TransformedRetrievalService.php

namespace App\Services\RAG;

use App\Services\AI\EmbeddingService;
use App\Services\RAG\QueryTransformation\QueryTransformer;
use App\Services\RAG\QueryTransformation\TransformedQueries;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransformedRetrievalService
{
    private const RRF_K = 60;

    public function __construct(
        private QueryTransformer $transformer,
        private EmbeddingService $embeddings,
    ) {}

    public function retrieve(string $query, int $topK = 10, int $candidatesPerQuery = 30): Collection
    {
        // 1. Transform
        $transformed = $this->transformer->transform($query);

        // 2. Hər transformed sorğu üçün paralel retrieval
        // (Laravel-də həqiqi paralel üçün ReactPHP/Amp/Guzzle Pool istifadə et)
        $allResults = [];
        foreach ($transformed->queries as $q) {
            $allResults[] = $this->vectorSearch($q, $candidatesPerQuery);
        }

        // 3. RRF ilə birləşdir
        $fused = $this->reciprocalRankFusion($allResults);

        return $fused->take($topK);
    }

    private function vectorSearch(string $query, int $limit): Collection
    {
        $vec = $this->embeddings->embed($query);
        $vecStr = '[' . implode(',', $vec) . ']';

        return collect(DB::select(<<<SQL
            SELECT id, content, document_id, metadata,
                   1 - (embedding <=> ?) AS score
            FROM knowledge_chunks
            WHERE embedding IS NOT NULL
            ORDER BY embedding <=> ?
            LIMIT ?
        SQL, [$vecStr, $vecStr, $limit]));
    }

    private function reciprocalRankFusion(array $resultSets): Collection
    {
        $scores = [];
        $data = [];

        foreach ($resultSets as $results) {
            foreach ($results as $rank => $chunk) {
                $id = $chunk->id;
                $scores[$id] = ($scores[$id] ?? 0) + 1 / (self::RRF_K + $rank + 1);
                $data[$id] ??= $chunk;
            }
        }

        arsort($scores);

        return collect(array_keys($scores))->map(fn($id) => (object)[
            'chunk' => $data[$id],
            'rrf_score' => $scores[$id],
        ]);
    }
}
```

### 10.7 Strategy selector (classifier-based)

Hər sorğuya transformation tətbiq etmək aqressivdir. Sadə heuristic classifier:

```php
<?php
// app/Services/RAG/QueryTransformation/StrategySelector.php

namespace App\Services\RAG\QueryTransformation;

class StrategySelector
{
    public function __construct(
        private HydeTransformer $hyde,
        private MultiQueryTransformer $multi,
        private SubQueryDecomposer $decomp,
        private PassthroughTransformer $passthrough,
    ) {}

    public function select(string $query): QueryTransformer
    {
        $wordCount = str_word_count($query);
        $hasMultipleClauses = $this->hasMultipleClauses($query);
        $hasSpecificId = $this->hasSpecificIdentifier($query);

        // Xüsusi ID (error code, SKU) — transformation pozar
        if ($hasSpecificId) {
            return $this->passthrough;
        }

        // Çox qısa sorğu (1-3 söz) — HyDE
        if ($wordCount <= 3) {
            return $this->hyde;
        }

        // Çox cümləli / konjunktiv — Sub-query decomposition
        if ($hasMultipleClauses) {
            return $this->decomp;
        }

        // Default: Multi-query
        return $this->multi;
    }

    private function hasSpecificIdentifier(string $query): bool
    {
        // E-1234, SKU-ABC, user_id format-ları
        return preg_match('/\b[A-Z]+-?\d+\b|SKU[-_ ]?\w+|user[-_ ]?id/i', $query) === 1;
    }

    private function hasMultipleClauses(string $query): bool
    {
        // "and", "while", "but also", ";", "?" çoxluğu
        return preg_match('/\b(and|while|also|compared to|versus)\b|;/i', $query) === 1
            || substr_count($query, '?') > 1;
    }
}
```

---

## 11. Prompt Templates

### 11.1 Prompt engineering qaydaları (transformation üçün)

1. **Output formatı sərt qoyun**: JSON array, tək sətir, və s. — parsing-i deterministik et
2. **"Do not add preamble" əlavə et**: LLM "Here are the reformulations:" qatır
3. **Entity preservation tələb et**: xüsusi adlar, rəqəmlər, tarixlər orijinal sorğudan saxlanılmalıdır
4. **"If uncertain, return original" fallback**: decomposition çətin olduqda orijinalı qaytar
5. **Azərbaycanca sorğular üçün**: prompt-u Azərbaycanca yaz, yoxsa Claude ingiliscə cavab verir

### 11.2 Azərbaycanca versiya

```php
// Azərbaycanca Multi-Query prompt
private const AZ_MULTI_QUERY_PROMPT = <<<'PROMPT'
Bu sualın {n} müxtəlif yenidən ifadəsini yaradın. Hər ifadə fərqli yanaşma 
olsun:
1. Alternativ sözlərlə parafraz
2. Daha spesifik, detallı versiya  
3. Daha ümumi, konseptual versiya
4. "Necə edim" formasında sual

Orijinal: "{query}"

YALNIZ JSON string massivi qaytarın, başqa heç nə:
PROMPT;
```

### 11.3 Prompt caching — transformation system prompt-u

Transformation prompt-ları qısa-dır (~200 token), amma eyni prompt hər sorğuda istifadə olunur. System prompt-u cache-ləmək olar:

```php
'system' => [
    [
        'type' => 'text',
        'text' => self::SYSTEM_PROMPT,  // ~200 tokens, cache olunur
        'cache_control' => ['type' => 'ephemeral'],
    ],
],
'messages' => [['role' => 'user', 'content' => $query]],
```

Caching yalnız system prompt ≥1024 token olduqda effektivdir. Qısa prompt-larda saving azdır — caching üçün prompt-u daha böyük nümunələrlə genişləndir:

```
Examples:
Query: "password reset"
Reformulations: ["how to reset password", "forgot password recovery", ...]

Query: "billing issue"
Reformulations: [...]

[... 10+ few-shot examples]

Now transform:
Query: "{user_query}"
```

Bu yanaşma:
- Cache-ə dəyən system prompt
- Few-shot ilə daha yaxşı keyfiyyət
- 80%+ cache hit rate tipikdir

---

## 12. A/B Testing: "Transformation Həqiqətən Kömək Edirmi?"

### 12.1 Niyə eval lazımdır

Query transformation **avtomatik keyfiyyət artımı deyil**. Bəzi dataset-lərdə:
- HyDE pis işləyir (LLM hallüsinasiyası retrieval-ı korlayır)
- Multi-Query marginal (sənədlər zəngindirsə, tək sorğu kifayətdir)
- Decomposition over-splits (sadə sorğuları lazımsız parçalara bölür)

### 12.2 Eval pattern (fayl 11-dən Paraphrase)

```php
<?php
// app/Console/Commands/EvaluateQueryTransform.php

namespace App\Console\Commands;

use App\Models\EvalQuery;
use App\Services\RAG\QueryTransformation\{
    HydeTransformer, MultiQueryTransformer, PassthroughTransformer
};
use App\Services\RAG\TransformedRetrievalService;
use App\Services\AI\Evals\RetrievalMetrics;
use Illuminate\Console\Command;

class EvaluateQueryTransform extends Command
{
    protected $signature = 'ai:eval-query-transform';

    public function handle(
        RetrievalMetrics $metrics,
        PassthroughTransformer $none,
        HydeTransformer $hyde,
        MultiQueryTransformer $multi,
    ): int {
        $queries = EvalQuery::with('relevantChunks')->get();

        $strategies = [
            'baseline' => $none,
            'hyde' => $hyde,
            'multi-query' => $multi,
        ];

        $results = [];

        foreach ($strategies as $name => $transformer) {
            $retriever = app(TransformedRetrievalService::class, [
                'transformer' => $transformer,
            ]);

            $ndcgSum = 0; $hitSum = 0; $latencySum = 0;

            foreach ($queries as $q) {
                $start = microtime(true);
                $retrieved = $retriever->retrieve($q->text, topK: 10);
                $latency = (microtime(true) - $start) * 1000;

                $retrievedIds = $retrieved->map(fn($r) => $r->chunk->id)->toArray();
                $relevant = $q->relevantChunks->pluck('grade', 'chunk_id')->toArray();

                $ndcgSum += $metrics->ndcgAtK($retrievedIds, $relevant, 5);
                $hitSum += $metrics->hitAtK($retrievedIds, $relevant, 5);
                $latencySum += $latency;
            }

            $n = count($queries);
            $results[$name] = [
                'ndcg@5' => round($ndcgSum / $n, 3),
                'hit@5' => round($hitSum / $n, 3),
                'avg_latency_ms' => round($latencySum / $n),
            ];
        }

        $this->table(
            ['Strategy', 'NDCG@5', 'Hit@5', 'Latency (ms)'],
            collect($results)->map(fn($r, $name) => array_merge(['strategy' => $name], $r))->toArray()
        );

        return 0;
    }
}
```

### 12.3 Nümunə nəticələr (müxtəlif korpuslarda tipik)

| Corpus tipi | Baseline | HyDE | Multi-Query | Decomp |
|-------------|----------|------|-------------|--------|
| Hüquqi sənədlər | NDCG 0.72 | 0.78 | 0.80 | 0.79 |
| Texniki docs | 0.81 | 0.79 (-) | 0.83 | 0.82 |
| FAQ / Support | 0.69 | 0.82 | 0.79 | 0.70 |
| Finansial hesabatlar | 0.65 | 0.75 | 0.78 | 0.82 |
| Kod bazası | 0.88 | 0.85 (-) | 0.87 | 0.89 |

Əsas dərs: **optimal strategy domenə bağlıdır**. Default prod-a göndərməzdən əvvəl öz eval set-ində yoxla.

### 12.4 Online A/B test

```php
// app/Services/RAG/QueryTransformation/AbSelector.php

class AbSelector implements QueryTransformer
{
    public function __construct(
        private PassthroughTransformer $control,
        private MultiQueryTransformer $treatment,
        private FeatureFlags $flags,
    ) {}

    public function transform(string $query, int $userId): TransformedQueries
    {
        $variant = $this->flags->assign('query-transform-multi', $userId);

        $result = $variant === 'treatment'
            ? $this->treatment->transform($query)
            : $this->control->transform($query);

        $result->metadata['ab_variant'] = $variant;

        return $result;
    }
}
```

Sonra downstream-də thumbs-up/down, dwell time, və retrieval success metric-lərini variant üzrə analiz et.

---

## 13. Anti-Pattern-lar və Qərar Cədvəli

### 13.1 Ümumi anti-pattern-lar

1. **Hər sorğuya transformation tətbiq etmək**.
   - Real sorğuların 60-80%-i sadədir. Transformation latency və xərc artırır, uplift marginaldır.
   - Həll: Classifier-based selection (bax §10.7).

2. **Özəl ID-ləri içərən sorğularda HyDE**.
   - "error E-7834" sorğusunda LLM uydurulmuş detallarla hipotetik sənəd yaradır.
   - Həll: Özəl ID-ləri detect et və passthrough et.

3. **Multi-Query-də mental lock-in**.
   - Hamısı eyni phrasing-in minor variantı. RRF heç nə artırmır.
   - Həll: Prompt-da "DIFFERENT angles" sərt tələb et.

4. **Decomposition sadə sorğuda**.
   - "refund policy" → ["what is refund?", "what is policy?"] — absurd parçalama.
   - Həll: "If simple, return unchanged" fallback prompt-da.

5. **Transformation-i rerank ilə əvəz etmək**.
   - Transformation və rerank **tamamlayıcıdır**. Transformation recall artırır, rerank precision.
   - Həll: Hər ikisini pipeline-da istifadə et.

6. **Eval olmadan deploy**.
   - Dostin dataset-ində +20% olan HyDE sənin şirkətinin FAQ-ında -5% ola bilər.
   - Həll: Gold set + A/B test.

7. **Latency büdcəsini ignore etmək**.
   - Chat UX-də 1.5s əlavə latency istifadəçini itirir.
   - Həll: Streaming UI + transformation-i paralel aparmaq.

### 13.2 Qərar cədvəli — hansı transformation?

| Sorğu xüsusiyyəti | Tövsiyə | Niyə |
|-------------------|---------|------|
| 1-3 söz, ambigua | HyDE | Qısa sorğular embedding-də zəifdir |
| 4-10 söz, aydın intent | Multi-Query və ya None | Geniş retrieval yaxşıdır |
| "X və Y-ni müqayisə et" | Sub-Query Decomp | Multi-hop |
| Spesifik kod/ID | None (passthrough) | BM25 dəqiq uyğunluq yaxşıdır |
| "Niyə" / "necə" | Step-Back | Ümumi prinsip retrieval-a kömək edir |
| Texniki jargon | Query Expansion | Sinonim variantları əhatə et |
| Azərbaycanca sorğu | Multi-Query (AZ prompt) | Leksik fərqlər çoxdur |
| Long tail / specific | Sub-Query + HyDE | Ağır yanaşma, yüksək dəyər |

### 13.3 Latency büdcəsinə görə

| Latency budget | Tövsiyə |
|---------------|---------|
| < 500 ms | None və ya Query Expansion (lexical) |
| 500-1000 ms | HyDE və ya Step-Back |
| 1000-2000 ms | Multi-Query |
| 2000 ms+ | Sub-Query Decomposition |
| Batch / offline | Hamısı + cascade |

### 13.4 Müsahibə xülasəsi

> Query transformation — xam sorğunu LLM vasitəsilə retrieval-friendly formaya çevirmək. Əsas texnikalar: HyDE (hipotetik sənəd generasiya et, onu embed et — qısa sorğular üçün), Multi-Query (N reformulation + RRF — ümumi recall uplift), Step-Back (daha abstract sual + retrieval — reasoning-heavy sorğular), Sub-Query Decomposition (kompleks sorğu-nu parçala — multi-hop), Query Expansion (sinonim inject et — xüsusilə BM25 üçün). Hər transformation 1 LLM call = ~$0.001-0.003 və 300-700 ms əlavə latency. Anti-pattern: hər sorğuya transform tətbiq etmək; classifier-based selection optimal. A/B test öz dataset-ində mütləqdir — bəzi domenlərdə HyDE -5%, bəzilərində +20% ola bilər. Laravel-də Strategy pattern ilə implementasiya, transformation-dan sonra paralel retrieval + RRF fusion. Prompt caching + few-shot prompt-lar xərcə və dəqiqliyə kömək edir.

---

## 14. Əsas Çıxarışlar

- Query transformation retrieval-dan **əvvəl** sorğunu yaxşılaşdıran texnikadır (contextual retrieval isə indexing-də tətbiq olunur)
- Əsas strategiyalar: HyDE, Multi-Query, Step-Back, Sub-Query Decomposition, Query Expansion
- Hər transformation 1 LLM call və 300-900 ms əlavə latency yaradır
- **Hər sorğuya transform tətbiq etmək anti-pattern-dir** — classifier ilə selective olun
- Strategy pattern + selector arxitekturası Laravel-də təmiz implementasiyadır
- RRF fusion Multi-Query və Sub-Query-də nəticələri birləşdirmək üçün standart metoddur
- A/B test və eval öz dataset-inizdə mütləqdir — uplift domenə görə dəyişir
- Prompt template-lər entity preservation, JSON format və fallback daxil etməlidir
- Online monitoring: variant üzrə NDCG@5, latency, cost/query

---

## Praktik Tapşırıqlar

### Tapşırıq 1: HyDE vs Direct Retrieval Benchmark

30 sorğu üçün iki yanaşmanı müqayisə et: (a) sorğunu birbaşa embed et, (b) HyDE — əvvəlcə hypothetical cavabı generasiya et, onu embed et. Hər halda NDCG@5 hesabla. HyDE-nin keyfiyyəti artırdığı sorğu tiplərini müəyyənləşdir (qısa vs uzun, texniki vs ümumi).

### Tapşırıq 2: Multi-Query Implementation

`MultiQueryTransformer` implement et: bir sorğudan 3 variant generasiya et, hər birini ayrıca search et, RRF fusion-la nəticəni birləşdir. Tək sorğu vs multi-query-nin NDCG@5 fərqini ölç. Extra LLM call-ın xərci artıma dəyərmi?

### Tapşırıq 3: Query Router

Sorğuyu analiz edib ən uyğun transformation-ı seçən `QueryRouter` implement et: sadə sorğular → direct embedding, mürəkkəb multi-hop → sub-query decomposition, abstract → step-back. 50 sorğu üzərindən routing qərarının keyfiyyətini insan ilə yoxla.

---

## Əlaqəli Mövzular

- `01-embeddings-vector-search.md` — Query embedding-inin əsası
- `06-reranking-hybrid-search.md` — Transformasiyadan sonra hybrid search
- `07-contextual-retrieval.md` — Transformasiya + contextual retrieval kombinasiyası
- `11-rag-evaluation-rerank.md` — NDCG@5 ilə transformation effektini ölç
