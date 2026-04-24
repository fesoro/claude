# Contextual Retrieval: Anthropic Texnikası ilə RAG Dəqiqliyini 49% Artırmaq

> **Oxucu kütləsi:** Senior backend developerlər (PHP/Laravel), production RAG sistemi qurmuş və ya qurmaq üzrə olanlar.
> **Bu faylın qonşu fayllarla fərqi:**
> - `04-chunking-strategies.md` — chunk-ların necə kəsildiyi (strategiyalar, ölçülər). Bu fayl chunk-ı dəyişmir, **onun ətrafına kontekst əlavə edir**.
> - `06-reranking-hybrid-search.md` — retrieval-dan sonra sıralama. Bu fayl **retrieval-dan əvvəl indexing fazasında** işləyir.
> - `18-embeddings-and-vectorstores.md` — embedding fundamental-ları. Bu fayl isə embed ediləcək **mətni dəyişdirir**, modeli yox.
> - `03-rag-architecture.md` — tam RAG pipeline. Bu fayl pipeline-a bir ingestion addımı əlavə edir.
> - `11-rag-evaluation-rerank.md` — eval metrikaleri. Burada contextualization-ın eval-i haqqında danışılır, amma faylın özü indexing texnikası haqqındadır.

---

## Mündəricat

1. Problem: Chunk-lar təcrid olunduqda kontekstini itirir
2. Anthropic Contextual Retrieval nədir
3. İki variant: Contextual Embeddings + Contextual BM25
4. Niyə işləyir — intuisiya və Anthropic-in eval nəticələri
5. Xərc riyaziyyatı: prompt caching niyə bu texnikanı mümkün edir
6. Laravel implementation: ingestion pipeline + queue
7. Prompt caching strategiyası
8. Reranking ilə birləşdirmə — 49% → 67% uplift
9. Nə vaxt late chunking / ColBERT-dən üstündür
10. Production gotchas və edge case-lər
11. Monitoring: contextualization keyfiyyəti
12. Qərar cədvəli və müsahibə xülasəsi

---

## 1. Problem: Chunk-lar Təcrid Olunduqda Kontekstini İtirir

Klassik RAG pipeline-da sənəd chunk-lara bölünür, hər chunk müstəqil olaraq embed edilir və vektor store-a yazılır. Bu prosesin gizli uğursuzluğu:

**Orijinal sənəd (bir SEC filing):**
```
Apple Inc. Q3 2024 Earnings Report
...
The company's revenue grew by 12% year-over-year, reaching $85.8 billion.
iPhone sales drove this growth, particularly in emerging markets.
...
```

Bu parçanı `recursive` chunking ilə kəsdikdə, bir chunk belə görünə bilər:

```
The company's revenue grew by 12% year-over-year, reaching $85.8 billion.
iPhone sales drove this growth, particularly in emerging markets.
```

İndi embedding prosesinə baxaq. Embedding model yalnız bu iki cümləni görür. "The company" — hansı şirkət? "Q3 2024" tarixi haradadır? Hansı bazar seqmenti? Bu chunk **orijinal konteksti itirib**.

### Praktik nəticələr

İstifadəçi soruşur: **"Apple-ın Q3 2024 gəliri nə qədər artdı?"**

Vektor axtarışı bu chunk-ı yaxşı reytinqlə gətirməyə bilər, çünki:
1. "Apple" sözü chunk-da yoxdur
2. "Q3 2024" da yoxdur
3. "revenue grew by 12%" semantic olaraq digər 50 şirkətin 12% artımı ilə qarışır

BM25 də eyni səbəbdən uğursuz olur — "Apple" termi chunk-da görünmür, IDF skorlaması sıfır verir.

### Bu niyə geniş yayılmış problemdir

Korporativ sənədlərdə bu pattern demək olar ki, hər yerdədir:
- **Maliyyə hesabatları**: "the company", "this quarter", "our products"
- **Texniki sənədlər**: "this function", "the service", "the module"
- **HR siyasətləri**: "this benefit", "eligible employees", "the policy"
- **Hüquqi müqavilələr**: "the party", "herein", "such obligation"

Bu referanslara **anaphora** deyilir (əvvəlki kontekstə istinad edən əvəzlik/ifadə). Anaphora resolution tam sənəd konteksti tələb edir — chunk səviyyəsində mümkün deyil.

### Klassik həllər və onların məhdudiyyətləri

| Həll | Nə edir | Məhdudiyyəti |
|------|---------|--------------|
| Böyük chunk-lar (2000+ token) | Daha çox kontekst saxlayır | Retrieval precision aşağı düşür, cavab noisy olur |
| Document title-ı chunk-a prepend | Sənəd adı hər chunk-ın başında | Yalnız bir level kontekst, bölmələr arası hələ problemlidir |
| Heading hierarchy injection | Başlıqları metadata kimi saxla | Başlıqlar həmişə informativ deyil ("Introduction", "Overview") |
| Parent-child chunking | Kiçik embed, böyük qaytar | Retrieval dəqiq olmasa, valideyn də səhv gətirilir |

Bu həllərin heç biri **semantic-level** disambiguation etmir. "The company" yenə də "Apple" olaraq translate olunmur.

---

## 2. Anthropic Contextual Retrieval Nədir

2024-cü ilin sentyabrında Anthropic [Contextual Retrieval: Better document understanding for retrieval augmented generation](https://www.anthropic.com/news/contextual-retrieval) adlı bir research post dərc etdi. Əsas fikir sadədir:

**Hər chunk-ı embed etməzdən əvvəl, LLM-dən həmin chunk-ın sənəddəki yerini 50-100 token ilə xülasə etməsini istə. Bu xülasəni chunk mətninin əvvəlinə əlavə et. İndi embed et.**

Yuxarıdakı nümunədə:

**Əvvəl (klassik):**
```
The company's revenue grew by 12% year-over-year, reaching $85.8 billion.
iPhone sales drove this growth, particularly in emerging markets.
```

**Sonra (contextualized):**
```
This chunk is from Apple Inc.'s Q3 2024 Earnings Report, in the 
Financial Performance section. It describes Apple's overall revenue 
growth for the quarter.

The company's revenue grew by 12% year-over-year, reaching $85.8 billion.
iPhone sales drove this growth, particularly in emerging markets.
```

İndi embedding və BM25 hər ikisi "Apple", "Q3 2024", "Earnings" söz və məna siqnallarına malikdir. Retrieval dramatik şəkildə yaxşılaşır.

### Anthropic-in eval nəticələri

Anthropic öz eval-lərində (9 domain: FinanceBench, kod bazaları, hüquqi müqavilələr və s.) aşağıdakı uplift ölçdü:

| Texnika | Top-20 retrieval failure rate | Uplift |
|---------|-------------------------------|--------|
| Baseline (vector + BM25) | 5.7% | — |
| + Contextual Embeddings | 3.7% | **35% reduction** |
| + Contextual Embeddings + Contextual BM25 | 2.9% | **49% reduction** |
| + Contextual + Reranking (cross-encoder) | 1.9% | **67% reduction** |

49% az retrieval failure — bu, ən yaxşı embedding model upgrade-indən də böyük bir irəliləyişdir. Və əhəmiyyətlisi: texnikanı **ingestion zamanı bir dəfə** edirsiniz; sorğu zamanı heç bir əlavə latency yoxdur.

---

## 3. İki Variant: Contextual Embeddings + Contextual BM25

Contextual Retrieval iki müstəqil, lakin tamamlayıcı texnikadan ibarətdir:

### 3.1 Contextual Embeddings

Contextualized mətni (kontekst prefiksi + orijinal chunk) embedding modelinə ötür. Vektor store-da bu contextualized vektoru saxla. Orijinal chunk mətnini **ayrıca bir sütunda** saxla — LLM-ə qaytarılanda, yalnız orijinal chunk göndərilir (kontekst prefiksi yox).

```
┌─────────────────────────────────────────────────────┐
│ INDEXING                                            │
│                                                     │
│ raw_chunk ──┐                                       │
│             ├──► LLM (Haiku) ──► context_prefix     │
│ full_doc ───┘                                       │
│                                                     │
│ contextualized = context_prefix + "\n\n" + raw_chunk│
│                                                     │
│ embedding = embed(contextualized)                   │
│                                                     │
│ store: { content: raw_chunk,                        │
│          context: context_prefix,                   │
│          embedding: embedding }                     │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ RETRIEVAL                                           │
│                                                     │
│ query ──► embed ──► vector search                   │
│                       (contextualized vectors)      │
│                         │                           │
│                         ▼                           │
│                   top-k results                     │
│                         │                           │
│                         ▼                           │
│               return raw_chunk to LLM               │
│        (LLM doesn't need to see the prefix)         │
└─────────────────────────────────────────────────────┘
```

### 3.2 Contextual BM25

BM25 indexi üçün də eyni kontekst prefiksi əlavə et. PostgreSQL-də bu, `content_tsv` sütununun contextualized mətn üzərində qurulması deməkdir:

```sql
ALTER TABLE knowledge_chunks
ADD COLUMN IF NOT EXISTS contextualized_content TEXT;

ALTER TABLE knowledge_chunks
ADD COLUMN IF NOT EXISTS contextualized_tsv tsvector
GENERATED ALWAYS AS (to_tsvector('english', contextualized_content)) STORED;

CREATE INDEX idx_contextualized_tsv ON knowledge_chunks
USING gin(contextualized_tsv);
```

Bu o deməkdir ki, BM25 axtarışı "Apple Q3 2024" kimi terminləri hətta orijinal chunk-da olmasa belə tapa bilir.

### 3.3 İkisi birlikdə (RRF ilə)

Pipeline belə olur:

```
query
  │
  ├──► contextualized_vector_search ──► candidates_A (top 50)
  │
  └──► contextualized_bm25_search   ──► candidates_B (top 50)
                                            │
                                            ▼
                    Reciprocal Rank Fusion (k=60)
                                            │
                                            ▼
                              top-20 fused candidates
                                            │
                                            ▼
                         (optional) cross-encoder rerank
                                            │
                                            ▼
                                     top-5 to LLM
                              (with raw_chunk content)
```

---

## 4. Niyə İşləyir — İntuisiya

### Embedding fəzasında "jitter" azalır

Təcrid edilmiş chunk "The company's revenue grew by 12%" embedding fəzasında yüzlərlə başqa "company revenue growth" cümləsi ilə kələklənir. Kontekst prefiksi bu chunk-ı spesifik bir nöqtəyə çəkir:

```
Sən "Apple Q3 2024 earnings" kontekstindəsən.
Apple-a aid 12% revenue growth cümləsi.
```

Embedding modelinin diqqət mexanizmi bu iki siqnal arasında bir əlaqə qurur və "Apple revenue 12%" sorğusu bu chunk-a daha yaxın düşür.

### Anaphora resolution pulsuz gəlir

LLM kontekst prefiksini generasiya edərkən öz-özlüyündə anaphora-nı həll edir. "This company" → "Apple Inc." çevrilir. Sən bunu əl ilə yazmaq, regex ilə tapıb əvəz etmək məcburiyyətində deyilsən.

### BM25-də "out-of-vocabulary" həll olunur

"Apple" sözü orijinal chunk-da olmasa belə, kontekst prefiksindədir. İstifadəçi "Apple revenue" yazırsa, BM25 bu chunk-ı dəqiq termin uyğunluğu ilə tapır.

### Cross-encoder rerank-da da kömək edir

Rerank mərhələsində cross-encoder sorğu və namizədi birlikdə görür. Namizəd artıq kontekstlidirsə, cross-encoder-in "bu chunk bu sorğuya uyğundur" qərarı daha keyfiyyətli olur.

---

## 5. Xərc Riyaziyyatı: Prompt Caching Niyə Bu Texnikanı Mümkün Edir

Naiv implementation-da hər chunk üçün bir LLM çağırışı edirsən. Milyon-tokenlik sənəd bazasında bu astronomik xərc yaradır.

### Naiv yanaşma (prompt caching OLMADAN)

Tutaq ki, sənədin ümumi uzunluğu 50,000 token, 100 chunk-a bölünüb. Hər chunk üçün LLM-ə göndərirsən:
- System prompt: ~200 token
- Tam sənəd: 50,000 token
- Mövcud chunk: ~500 token
- Cavab (context prefix): ~100 token

**Hər chunk üçün xərc** (Claude Haiku 4.5, input $1/M, output $5/M):
```
Input:  (200 + 50000 + 500) tokens × $1/M  = $0.0507
Output: 100 tokens × $5/M                   = $0.0005
Total:  ≈ $0.0512 per chunk
```

100 chunk × $0.0512 = **$5.12 per document**. 10,000 sənəd = $51,200. Qeyri-mümkündür.

### Prompt caching ilə

Anthropic prompt caching-də sistem prompt + tam sənədi cache-ləyirsən (1 saat ephemeral cache). İlk çağırış "cache write" olur (1.25× normal qiymət), qalan çağırışlar "cache read" olur (0.1× normal qiymət).

Claude Haiku 4.5 qiymətləri:
- Input (normal): $1.00 / M tokens
- Cache write (1h): $1.25 / M tokens
- Cache read: $0.10 / M tokens

**Hər chunk üçün xərc (cache-dən sonra):**
```
Cached input (tam sənəd): 50,000 × $0.10/M   = $0.005
Fresh input (mövcud chunk): 500 × $1.00/M    = $0.0005
Output: 100 × $5.00/M                         = $0.0005
Total: ≈ $0.006 per chunk
```

100 chunk × $0.006 = **$0.60 per document**. 10,000 sənəd = $6,000. Hələ də böyükdür, amma mümkündür. Əsas irəliləmə **8.5× ucuzlaşma**.

İlk "cache write" çağırışı üçün əlavə ~$0.063 var (50,000 × $1.25/M), amma bu 100 chunk arasında bölüşür.

### Anthropic-in rəqəmi

Anthropic öz rəqəmini belə verir: **$1.02 per million document tokens** (Haiku + caching ilə). Bu rəqəm adekvatdır əksər production use case-ləri üçün, amma böyük sənəd bazası üçün plan qurulmalıdır.

### Xərc müqayisəsi cədvəli

| Yanaşma | Input xərc model | 1M doc token üçün xərc |
|---------|------------------|------------------------|
| Klassik RAG (contextualization yox) | — | **$0** (yalnız embedding) |
| Contextual + Haiku + caching | $1.02 / M tokens | **~$1.02 + embedding** |
| Contextual + Haiku, caching YOX | $50+ / M tokens | **~$50+** (iqtisadi deyil) |
| Contextual + Sonnet + caching | $3-4 / M tokens | **~$3-4** (keyfiyyət uplift marginal) |
| Contextual + GPT-4o-mini + caching | ~$1.50 / M tokens | **~$1.50** (alternativ provider) |

Praktiki qayda: **Haiku + prompt caching** standart seçim olmalıdır. Yalnız keyfiyyət eval-ində Sonnet əhəmiyyətli uplift göstərirsə, yüksəldin.

---

## 6. Laravel Implementation

### 6.1 Schema dəyişiklikləri

```php
<?php
// database/migrations/2026_04_24_add_contextual_retrieval_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            // LLM-in generasiya etdiyi kontekst prefiksi
            $table->text('context_prefix')->nullable()->after('content');

            // Orijinal + prefiks birləşdirilmiş mətn (embedding və BM25 üçün)
            $table->text('contextualized_content')->nullable()->after('context_prefix');

            // Contextualization statusu və metadata
            $table->string('contextualization_status')->default('pending');
            $table->string('contextualization_model')->nullable();
            $table->integer('contextualization_tokens')->nullable();
            $table->timestamp('contextualized_at')->nullable();

            $table->index('contextualization_status');
        });

        // Yeni tsvector — contextualized content üzərində
        DB::statement(<<<SQL
            ALTER TABLE knowledge_chunks
            ADD COLUMN contextualized_tsv tsvector
            GENERATED ALWAYS AS (
                to_tsvector('english', COALESCE(contextualized_content, content))
            ) STORED
        SQL);

        DB::statement(<<<SQL
            CREATE INDEX knowledge_chunks_contextualized_tsv_idx
            ON knowledge_chunks USING gin(contextualized_tsv)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS knowledge_chunks_contextualized_tsv_idx');
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            $table->dropColumn([
                'context_prefix',
                'contextualized_content',
                'contextualization_status',
                'contextualization_model',
                'contextualization_tokens',
                'contextualized_at',
                'contextualized_tsv',
            ]);
        });
    }
};
```

### 6.2 ContextualizationService

```php
<?php
// app/Services/RAG/ContextualizationService.php

namespace App\Services\RAG;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContextualizationService
{
    private const MODEL = 'claude-haiku-4-5';
    private const MAX_CONTEXT_TOKENS = 100;

    private const PROMPT_TEMPLATE = <<<'PROMPT'
<document>
%s
</document>

Here is a chunk from this document:
<chunk>
%s
</chunk>

Generate a short, standalone context (50-100 tokens) that situates this chunk 
within the full document. Include:
- What document this is from (if identifiable)
- What section or topic the chunk covers
- Any entities or time periods that the chunk refers to but doesn't name explicitly

Answer ONLY with the context — no preamble, no quotes, no "Here is the context:".
PROMPT;

    /**
     * Tək bir chunk üçün kontekst prefiksi generasiya et.
     * Prompt caching document hissəsində tətbiq olunur.
     */
    public function contextualize(
        KnowledgeDocument $document,
        KnowledgeChunk $chunk,
    ): string {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
        ->timeout(60)
        ->retry(3, 1000, function ($exception, $request) {
            // Yalnız 429/500-lərdə retry
            return $exception->response?->status() >= 429;
        })
        ->post('https://api.anthropic.com/v1/messages', [
            'model' => self::MODEL,
            'max_tokens' => 256,
            'system' => [
                [
                    'type' => 'text',
                    'text' => 'You contextualize document chunks for retrieval. Be concise and specific.',
                ],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        // Document hissəsi — cache-lənəcək
                        'text' => sprintf(
                            "<document>\n%s\n</document>",
                            $document->raw_content
                        ),
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                    [
                        'type' => 'text',
                        // Chunk hissəsi — hər dəfə dəyişir
                        'text' => sprintf(
                            "Here is a chunk from this document:\n<chunk>\n%s\n</chunk>\n\n%s",
                            $chunk->content,
                            $this->instructionTail()
                        ),
                    ],
                ],
            ]],
        ]);

        if ($response->failed()) {
            Log::warning('Contextualization failed, using fallback', [
                'document_id' => $document->id,
                'chunk_id' => $chunk->id,
                'status' => $response->status(),
            ]);
            return $this->fallbackPrefix($document, $chunk);
        }

        $data = $response->json();
        $prefix = trim($data['content'][0]['text'] ?? '');

        // Cache statistikasını log et (observability üçün kritikdir)
        Log::info('Contextualization complete', [
            'chunk_id' => $chunk->id,
            'cache_creation_tokens' => $data['usage']['cache_creation_input_tokens'] ?? 0,
            'cache_read_tokens' => $data['usage']['cache_read_input_tokens'] ?? 0,
            'input_tokens' => $data['usage']['input_tokens'] ?? 0,
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
        ]);

        return $prefix;
    }

    private function instructionTail(): string
    {
        return <<<'TAIL'
Generate a short, standalone context (50-100 tokens) that situates this chunk 
within the full document. Include:
- What document this is from (if identifiable)
- What section or topic the chunk covers
- Any entities or time periods that the chunk refers to but doesn't name explicitly

Answer ONLY with the context — no preamble, no quotes, no "Here is the context:".
TAIL;
    }

    /**
     * LLM uğursuz olduqda sadə fallback.
     * Yaxşı olmasa da, embedding-in tamamilə qaçırılmasından yaxşıdır.
     */
    private function fallbackPrefix(
        KnowledgeDocument $document,
        KnowledgeChunk $chunk,
    ): string {
        $title = $document->title;
        $section = $chunk->metadata['section_heading'] ?? null;
        $position = $chunk->metadata['position_pct'] ?? null;

        $parts = ["This is a chunk from the document \"{$title}\"."];
        if ($section) {
            $parts[] = "It appears in the section \"{$section}\".";
        }
        if ($position !== null) {
            $parts[] = "It is located approximately {$position}% through the document.";
        }

        return implode(' ', $parts);
    }
}
```

### 6.3 Queued Ingestion Job

```php
<?php
// app/Jobs/ContextualizeChunkJob.php

namespace App\Jobs;

use App\Models\KnowledgeChunk;
use App\Services\AI\EmbeddingService;
use App\Services\RAG\ContextualizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;

class ContextualizeChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(
        public int $chunkId,
    ) {}

    public function middleware(): array
    {
        // Anthropic rate limit-ini aşmamaq üçün
        return [new RateLimited('anthropic-contextualization')];
    }

    public function handle(
        ContextualizationService $contextualizer,
        EmbeddingService $embeddings,
    ): void {
        $chunk = KnowledgeChunk::with('document')->find($this->chunkId);

        if (!$chunk || !$chunk->document) {
            return;
        }

        if ($chunk->contextualization_status === 'completed') {
            return; // idempotent
        }

        $chunk->update(['contextualization_status' => 'processing']);

        try {
            // 1. Kontekst prefiksi generasiya et
            $prefix = $contextualizer->contextualize($chunk->document, $chunk);
            $contextualized = $prefix . "\n\n" . $chunk->content;

            // 2. Yeni embedding yarat (contextualized content üzərində)
            $embedding = $embeddings->embed($contextualized);
            $vectorString = '[' . implode(',', $embedding) . ']';

            // 3. Bütün sütunları atomik şəkildə yenilə
            DB::transaction(function () use ($chunk, $prefix, $contextualized, $vectorString) {
                $chunk->update([
                    'context_prefix' => $prefix,
                    'contextualized_content' => $contextualized,
                    'contextualization_status' => 'completed',
                    'contextualization_model' => 'claude-haiku-4-5',
                    'contextualized_at' => now(),
                ]);

                DB::statement(
                    'UPDATE knowledge_chunks SET embedding = ? WHERE id = ?',
                    [$vectorString, $chunk->id]
                );
            });
        } catch (\Throwable $e) {
            $chunk->update(['contextualization_status' => 'failed']);
            throw $e;
        }
    }
}
```

### 6.4 Rate limiter konfiqurasiyası

```php
// app/Providers/AppServiceProvider.php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Anthropic Tier 2: 50 RPM, ~450K TPM for Haiku
    // Prompt caching ilə hər çağırış ~500 "fresh" token işlədir
    // Konservativ: dəqiqədə 40 çağırış
    RateLimiter::for('anthropic-contextualization', function () {
        return Limit::perMinute(40);
    });
}
```

### 6.5 Document ingestion-a inteqrasiya

```php
// DocumentIngestionPipeline::ingest() metodunun sonunda:

// Mövcud: chunk-ları klassik embedding ilə yarat
// ...

// Yeni: hər chunk üçün contextualization job dispatch et
foreach ($chunks as $chunk) {
    ContextualizeChunkJob::dispatch($chunk->id)
        ->onQueue('rag-contextualization')
        ->delay(now()->addSeconds(random_int(0, 5))); // rate limiter-i smooth et
}
```

Bu dizayn sinxron indexing blokunu dəyişdirmir — chunk-lar əvvəlcə fallback prefix ilə saxlanıla, sonra async şəkildə contextualize oluna bilər.

### 6.6 Hibrid axtarışın adaptasiyası

```php
// HybridSearchService::search() metodunun dəyişikliyi

private function vectorSearch(string $query, int $limit): Collection
{
    $queryVector = $this->embeddingService->embed($query);
    $vectorString = '[' . implode(',', $queryVector) . ']';

    return DB::table('knowledge_chunks')
        ->selectRaw(<<<SQL
            id,
            content,                    -- orijinal chunk, LLM-ə bu göndərilir
            contextualized_content,     -- monitoring/debug üçün
            context_prefix,
            metadata,
            document_id,
            1 - (embedding <=> ?) as vector_score
        SQL, [$vectorString])
        ->where('contextualization_status', 'completed')
        ->orderByRaw('embedding <=> ?', [$vectorString])
        ->limit($limit)
        ->get();
}

private function bm25Search(string $query, int $limit): Collection
{
    $tsQuery = $this->buildTsQuery($query);

    return DB::table('knowledge_chunks')
        ->selectRaw(<<<SQL
            id,
            content,
            contextualized_content,
            metadata,
            document_id,
            ts_rank_cd(contextualized_tsv, to_tsquery('english', ?), 32) as bm25_score
        SQL, [$tsQuery])
        ->whereRaw("contextualized_tsv @@ to_tsquery('english', ?)", [$tsQuery])
        ->where('contextualization_status', 'completed')
        ->orderByDesc('bm25_score')
        ->limit($limit)
        ->get();
}
```

Qeyd: LLM-ə prompt qurarkən `content`-i göndəririk, `contextualized_content`-i yox. Model kontekst prefiksini görmür; prefiks yalnız retrieval-ın keyfiyyəti üçündür.

---

## 7. Prompt Caching Strategiyası

### 7.1 Cache key-in optimal dizaynı

Anthropic cache key-lərini `cache_control: {type: "ephemeral"}` ilə markalanmış content blok-larının **tam byte sequence-ı** üzərində hesablayır. Bu o deməkdir ki:

- **Hər unikal sənəd** ayrıca cache entry yaradır (gözlənilir)
- Sənədin kiçik dəyişikliyi (tək karakter) yeni cache yaradır — köhnə istilik itir
- Cache 5 dəqiqə sonra passivləşir (ephemeral), 1 saatlıq variant da var

### 7.2 Chunk-ları ardıcıl emal et — paralel yox

Naiv implementation bütün chunk-ları paralel queue worker-lərə göndərir. Bu bir nüansa qarşı çıxır:

```
Worker 1: chunk_1-i göndərir → cache write (1.25×)
Worker 2: chunk_2-ni göndərir (eyni anda) → cache write (1.25×) [!]
Worker 3: chunk_3-ü göndərir (eyni anda) → cache write (1.25×) [!]
```

Əgər paralel çağırışlar bir-birindən əvvəl server tərəfindən yazılmırsa, hər biri öz "cache miss"-ini yaradır. Anthropic bu case-i deduplicate edir, amma qaranlıq region var.

**Təhlükəsiz pattern**: Hər sənəd üçün chunk-ları **ardıcıl** emal et. Bir document başına bir worker. Sənədlər arası paralellik aparılsın:

```php
// Sənəd başına ardıcıl batch job
class ContextualizeDocumentJob implements ShouldQueue
{
    public function __construct(public int $documentId) {}

    public function handle(
        ContextualizationService $contextualizer,
        EmbeddingService $embeddings,
    ): void {
        $document = KnowledgeDocument::with('chunks')->find($this->documentId);

        // İlk çağırış cache write — qalanlar cache read
        foreach ($document->chunks as $chunk) {
            (new ContextualizeChunkJob($chunk->id))->handle($contextualizer, $embeddings);
        }
    }
}
```

### 7.3 Cache TTL seçimi

| TTL | Qiymət | Uyğun case |
|-----|--------|------------|
| 5 dəqiqə (default ephemeral) | Ucuz | Kiçik sənəd (< 100 chunk), ardıcıl emal |
| 1 saat | Cache write 2×, read eynidir | Böyük sənəd (500+ chunk), paralel worker-lər, retry-lar |

Böyük sənədlər üçün 1 saatlıq cache job retry-larını və slow worker-ləri əhatə etmək üçün faydalıdır.

### 7.4 Cache invalidation

Sənəd dəyişdikdə:
1. Bütün chunk-ları `contextualization_status = 'pending'` etibarlı et
2. Hər chunk üçün yeni job dispatch et
3. Köhnə prefix-lər yenilənənə qədər qalacaq (istifadə edilmir, çünki embedding köhnə)

Optimallaşdırma: yalnız **dəyişən** chunk-ları yenidən kontekstlə. Sənədin 80%-i eynidirsə, diff-based update.

---

## 8. Reranking ilə Birləşdirmə — 49% → 67% Uplift

Contextual Retrieval retrieval keyfiyyətini artırır, amma rerank mərhələsi bu upliftə əlavə keyfiyyət gətirir.

### 8.1 Tam pipeline

```
┌───────────────────────────────────────────────────────┐
│ INGESTION (offline, bir dəfə)                         │
├───────────────────────────────────────────────────────┤
│ 1. Sənədi chunk-la                                    │
│ 2. Hər chunk üçün context_prefix generasiya et (Haiku)│
│ 3. contextualized = prefix + chunk                    │
│ 4. embedding = embed(contextualized)                  │
│ 5. BM25 index: contextualized                         │
│ 6. Store: content (raw), prefix, contextualized,      │
│          embedding                                    │
└───────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────┐
│ RETRIEVAL (online, hər sorğu)                         │
├───────────────────────────────────────────────────────┤
│ query ──► vector search (top 150)                     │
│       └─► BM25 search (top 150)                       │
│                  │                                    │
│                  ▼                                    │
│            RRF fusion (top 150)                       │
│                  │                                    │
│                  ▼                                    │
│        cross-encoder rerank (e.g. Cohere Rerank 3)    │
│                  │                                    │
│                  ▼                                    │
│             top 20 final                              │
│                  │                                    │
│                  ▼                                    │
│         LLM prompt (raw chunk content)                │
└───────────────────────────────────────────────────────┘
```

### 8.2 Rerank mərhələsində contextualized content istifadə et?

İki seçim var:

**Seçim A**: Reranker-ə **raw content** göndər.
- Üstünlük: Rerank qərarı orijinal sənədə uyğundur
- Çatışmazlıq: Reranker contextualized search-in gətirdiyi qazancı tam istifadə edə bilmir

**Seçim B**: Reranker-ə **contextualized content** göndər.
- Üstünlük: Reranker də "Apple Q3 2024" kontekstini görür
- Çatışmazlıq: Daha uzun mətn, rerank latency və xərc artır

Anthropic eval-ində **Seçim B** bir qədər daha yaxşı nəticə verir. Qərar: default olaraq B, gecikmə kritikdirsə A.

```php
// Cohere Rerank-a contextualized content göndər
$documents = $candidates->map(
    fn($c) => $c->contextualized_content ?? $c->content
)->toArray();

$response = Http::withToken(config('services.cohere.key'))
    ->post('https://api.cohere.ai/v2/rerank', [
        'model' => 'rerank-english-v3.0',
        'query' => $query,
        'documents' => $documents,
        'top_n' => 20,
    ]);
```

### 8.3 Uplift-in additive olması

Anthropic-in ölçüləri göstərir ki, uplift-lər "additive" deyil — multiplikativdir:
- Baseline failure rate: 5.7%
- Contextual: 2.9% (49% reduction)
- Contextual + Rerank: 1.9% (67% reduction)

Rerank tək başına baseline üzərində ~40% reduction verirdi. Contextual + rerank 67% reduction verir, amma (1 - 0.49) × (1 - 0.40) = 0.306 (69% reduction gözlənir) — yəni həqiqi uplift gözlənilən addisional töhfədən bir qədər aşağıdır. Bu, contextualization-ın rerank-ın gətirdiyi bəzi dəyərləri artıq "topladığı" deməkdir.

---

## 9. Nə Vaxt Late Chunking / ColBERT-dən Üstündür

Contextual Retrieval alternativ "long-context-aware retrieval" texnikaları ilə rəqabətdədir.

### 9.1 Late Chunking

Late chunking (jina-embeddings-v2 və digər modellərdə) sənədi tam kontekstdə embed edir, sonra chunk-a ayırır. Amma:

| Aspekt | Contextual Retrieval | Late Chunking |
|--------|----------------------|---------------|
| Embedding model asılılığı | Heç biri (istənilən model) | Yalnız dəstəkləyən modellər (jina, bge-v1.5 və s.) |
| Sənəd uzunluğu limiti | Tam sənədin LLM-in context window-una sığması | Embedding modelinin context window-u (adətən 8K) |
| Keyfiyyət (uzun sənədlərdə) | Yüksək | Orta (8K-dan uzun sənədlərdə itir) |
| Xərc | ~$1/M doc token | ~$0 (yalnız bir embedding) |
| BM25 contextualization | Dəstəkləyir | Dəstəkləmir (yalnız embedding) |

**Qərar**: Uzun sənədlər (50K+ token) və BM25 hybrid istifadə edirsənsə — **Contextual**. Qısa sənədlər (< 8K) və yalnız vector search — **Late Chunking** ucuzdur və kifayətdir.

### 9.2 ColBERT / Late Interaction

ColBERT hər token üçün ayrı embedding saxlayır və sorğu zamanı MaxSim operasiya aparır. Çox güclüdür, amma:

| Aspekt | Contextual | ColBERT |
|--------|-----------|---------|
| Storage | 1 vektor / chunk | 512+ vektor / chunk (100× daha çox yaddaş) |
| Query latency | Standard ANN | 10-50× daha yavaş (MaxSim-ə görə) |
| Infrastructure | Standart pgvector/Pinecone | Xüsusi ColBERT serving (rarely in PHP ecosystem) |
| Precision uplift | 35-50% | 40-60% (amma yalnız axtarışda, BM25-də yox) |

**Qərar**: Əgər yüzlərlə million chunk-ın var və ML team infrastructure qura bilir — ColBERT. PHP/Laravel shop — **Contextual** (tək bir LLM call, existing infrastructure).

---

## 10. Production Gotchas və Edge Case-lər

### 10.1 Sənəd 200K+ tokendir

Claude 3.5 Sonnet-in context window-u 200K token-dir. Sənəd bundan uzundursa:
- **Variant 1**: Sənədi bölmələrə böl, hər bölmə üçün ayrıca contextualize et (bölmə sərhədi + chunk). Kontekst biliyi itir, amma hələ də işləyir.
- **Variant 2**: Sənədi xülasə et (1-2 abzas), sonra hər chunk üçün xülasə + chunk-ı göndər.
- **Variant 3**: Claude 4.7 (1M context) — daha bahalı, amma tək call.

```php
if ($documentTokens > 150_000) {
    // Option 2: summarize first
    $summary = $summarizer->summarize($document);
    $prefix = $contextualizer->contextualizeWithSummary($summary, $chunk);
}
```

### 10.2 Structured data (cədvəllər, JSON)

LLM cədvəl üçün lazımsız "this table shows data about X" generasiya edə bilər. Cədvəllər üçün xüsusi prompt:

```
This chunk is a structured data table. Generate a context that identifies:
- What document this table is from
- The table's purpose and headers
- Any units or time periods implicit in the data
Do not reformat or summarize the rows themselves.
```

### 10.3 Multi-lingual sənədlər

Contextualization prompt-ı chunk-ın dilində yazılmalıdır, yoxsa prefix səhv dildə olar. Laravel-də:

```php
$language = $document->detected_language ?? 'en';
$prompt = $this->loadPromptTemplate($language);
```

Azərbaycan dili üçün ayrı prompt şablonu saxlamaq optimal olar. Claude azərbaycanca yaxşı işləyir, amma instruction-ları da Azərbaycanca verməlisən, yoxsa cavab ingilis dilində gəlir.

### 10.4 Code chunk-ları

Kod üçün contextualization faydalı olsa da, kod chunk-ı adətən artıq self-contained-dir (funksiya imzası + docblock). Contextualization bəzən redundant "this is a function that..." generasiya edir.

Qərar: Kod üçün **prefix-i docblock + class path ilə əvəz et**, LLM çağırışını et.

```php
if ($chunk->metadata['strategy'] === 'code') {
    $prefix = sprintf(
        "File: %s\nClass: %s\nFunction: %s",
        $chunk->metadata['file_path'],
        $chunk->metadata['class_name'] ?? '(top-level)',
        $chunk->metadata['function_name'] ?? '(anonymous)'
    );
} else {
    $prefix = $contextualizer->contextualize($document, $chunk);
}
```

### 10.5 Anaphora açılmaması

Bəzən LLM anaphora-nı həll etmir — "the company" olaraq qalır, "Apple" ilə əvəz etmir. Bu, sənədin əvvəlində şirkət adının olmamasına görə ola bilər. Monitoring:

```sql
-- Problemli prefix-ləri tap
SELECT id, context_prefix
FROM knowledge_chunks
WHERE context_prefix ILIKE '%the company%'
   OR context_prefix ILIKE '%this chunk%'
LIMIT 20;
```

### 10.6 Haiku-nun hallüsinasiyası

Haiku nadir hallarda sənəddə olmayan məlumatı prefix-ə daxil edə bilər. Bunun eval-i:

```php
// periyodik sample check
public function auditContextualization(int $sampleSize = 100): array
{
    $samples = KnowledgeChunk::inRandomOrder()
        ->where('contextualization_status', 'completed')
        ->limit($sampleSize)
        ->with('document')
        ->get();

    $hallucinationCount = 0;
    foreach ($samples as $chunk) {
        $isFaithful = $this->checkPrefixFaithfulness(
            $chunk->context_prefix,
            $chunk->document->raw_content
        );
        if (!$isFaithful) $hallucinationCount++;
    }

    return [
        'sample_size' => $sampleSize,
        'hallucination_rate' => $hallucinationCount / $sampleSize,
    ];
}
```

`checkPrefixFaithfulness` Haiku-nun cavabını yenidən Sonnet ilə yoxlayır. 1-2% hallüsinasiya normaldır, 5%+ model dəyişilməli və ya prompt daha sərt edilməlidir.

### 10.7 Anthropic-in cookbook referansı

Anthropic tam işləyən nümunə kodu bu repo-da saxlayır: [`anthropic-cookbook/skills/contextual-embeddings`](https://github.com/anthropics/anthropic-cookbook/tree/main/skills/contextual-embeddings). Python-dur, amma prompt strukturu və caching pattern-ları bir-birinə tam uyğundur — PHP-ə çevirərkən istinad et.

---

## 11. Monitoring: Contextualization Keyfiyyəti

### 11.1 Offline eval — retrieval uplift

Gold set üzərində NDCG@5 və hit@5 ölç (fayl 11-dəki metodologiya ilə):

```php
// Artisan command
php artisan ai:eval-retrieval --setup=classic
php artisan ai:eval-retrieval --setup=contextual

// Nəticə müqayisəsi:
// classic:      NDCG@5 = 0.68, hit@5 = 0.82
// contextual:   NDCG@5 = 0.79, hit@5 = 0.93
// uplift:       +16%,          +13%
```

Uplift 10%+ olmayırsa, contextualization prompt-ını təkrar nəzərdən keçir.

### 11.2 Online metriklər

Production-da hər sorğuda izlə:

| Metric | Target | Alert |
|--------|--------|-------|
| Contextualized chunks % | > 98% | Status != 'completed' 100+ chunk |
| Contextualization job failure rate | < 1% | > 3% / 15 min |
| Prompt cache hit rate | > 80% | < 50% (batching problemi) |
| Avg prefix length | 50-150 token | < 30 və ya > 200 |

### 11.3 Cache hit rate SQL

```sql
SELECT
  DATE_TRUNC('hour', created_at) AS hour,
  COUNT(*) AS total_calls,
  SUM(cache_read_tokens) AS read_tokens,
  SUM(cache_creation_tokens) AS write_tokens,
  ROUND(
    SUM(cache_read_tokens)::numeric /
    NULLIF(SUM(cache_read_tokens + cache_creation_tokens), 0),
    3
  ) AS cache_hit_rate
FROM contextualization_logs
WHERE created_at >= NOW() - INTERVAL '24 hours'
GROUP BY hour
ORDER BY hour DESC;
```

Cache hit rate 80%-dən aşağıdırsa, ya sənədlər çox kiçikdir (hər document az chunk), ya da worker-lər sənəd sərhədini paralelləşdirir.

---

## 12. Qərar Cədvəli və Müsahibə Xülasəsi

### 12.1 Nə vaxt Contextual Retrieval tətbiq etməli

| Ssenari | Contextual Retrieval? | Səbəb |
|---------|----------------------|-------|
| Korporativ sənədlər (SEC, HR, hüquqi) | Bəli | Anaphora-ya görə kritik uplift |
| Texniki sənədlər (API, arxitektura) | Bəli (orta uplift) | Referanslar ("this service") həll olunur |
| FAQ / dəstək məqalələri | Şərtli | Hər Q&A self-contained-dirsə, uplift kiçikdir |
| Kod axtarışı | Qismən | Prefix-i metadata ilə əvəz et (file+class path) |
| Qısa twit-lər / mesajlar (< 500 token) | Xeyr | Kontekst prefix özündən daha uzundur |
| Single-document RAG (< 10K token) | Xeyr | Long context window daha ucuz və keyfiyyətlidir |
| Real-time indexing tələbi | Şərtli | Async job pattern ilə mümkündür, amma latency artır |
| Tight cost budget | Şərtli | $1/M doc token məqbuldur, yoxsa bypass |

### 12.2 Müsahibə üçün 30 saniyəlik cavab

"Contextual Retrieval nədir?"

> Anthropic-in sentyabr 2024 texnikasıdır. Hər chunk-ı embed etməzdən əvvəl, LLM (Haiku) ilə o chunk-ın sənəddəki yerini 50-100 token ilə xülasə edirik və bu xülasəni chunk-ın əvvəlinə əlavə edirik. Sonra contextualized mətni həm embedding, həm də BM25 indexinə yazırıq. Bu, chunk-ın təcrid olduqda itirdiyi kontekst məlumatını (məsələn, "the company" → "Apple") bərpa edir. Prompt caching sayəsində xərc ~$1 per million doc token-dir. Anthropic-in ölçüləri: tək başına 35% retrieval failure reduction, reranking ilə birləşdiriləndə 67%. Mən Laravel-də bunu async queue job ilə implement edirəm — sənəd ingest olunanda hər chunk üçün bir job dispatch olur, LLM chunk üçün prefix generasiya edir, contextualized content həm pgvector embedding-ində, həm də GIN tsvector index-ində saxlanılır.

### 12.3 Ən tez-tez xətalar (anti-patterns)

1. **Prompt caching-i atlamaq**. Xərc 50× artır, iqtisadi deyil.
2. **Hər chunk üçün paralel worker**. Cache hit rate düşür. Sənəd başına ardıcıl emal et.
3. **LLM-ə contextualized content göndərmək**. LLM prefix-i görməməlidir, yalnız raw chunk. Retrieval üçün contextualized, generation üçün raw.
4. **Status column olmadan deploy**. Job failure olanda yarı-contextualized chunk-lar retrieval nəticələrinə qarışıb keyfiyyəti korlayır. `status = 'completed'` filterini mütləq tətbiq et.
5. **Eval olmadan rollout**. Contextual Retrieval "artırır" iddiası öz dataset-in üzərində test edilməlidir. Bəzi domenlərdə (təmiz self-contained FAQ) uplift sıfırdır.
6. **Fallback prefix yoxluğu**. LLM API uğursuz olursa, chunk retrieval-dan tamamilə çıxarılır və ya səhv data ilə indekslənir. Metadata-based fallback həmişə lazımdır.
7. **Chunk-ın kiçik dəyişikliyində tam document-i yenidən contextualize etmək**. Diff-based update tətbiq et.

### 12.4 Əsas çıxarışlar

- Contextual Retrieval — ingestion-da tətbiq olunan, sorğu-zamanı latency-ə təsir etməyən texnika
- İki variant: Contextual Embeddings + Contextual BM25, ən yaxşı effekt birlikdə
- Prompt caching mütləq şərtdir — xərc $50/M-dən $1/M-ə endirir
- Uplift 35-49% retrieval failure reduction; reranking əlavə etdikdə 67%
- Laravel implementation: queue job + rate limiter + status sütunu + fallback
- Qonşu texnikalar: late chunking (qısa sənədlər üçün ucuz), ColBERT (çox böyük corpus üçün güclü, amma infrastruktur bahalı)
- Gold-set eval olmadan rollout etmə — bəzi domenlərdə uplift marginaldır
