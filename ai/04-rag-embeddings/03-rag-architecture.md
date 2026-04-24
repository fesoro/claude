# RAG Arxitekturası: Tam Bələdçi

## RAG Niyə Mövcuddur

Böyük dil modelləri RAG-ın birbaşa həll etdiyi üç fundamental məhdudiyyətə malikdir:

### 1. Bilik Kəsim Tarixi
LLM-lər veb-in statik bir görüntüsü üzərindən əyidilir. GPT-4-ün kəsim tarixi 2024-cü ilin əvvəlləri; Claude 3.5-inkisə 2025-ci ilin əvvəlləridir. Bu tarixdən sonrakı hər hansı məlumat bilinmir. Aşağıdakılarla məşğul olan korporativ sistemlər üçün:
- Hər gün yenilənən daxili sənədlər
- Son məhsul dəyişiklikləri
- Real vaxt rejimli iş məlumatları
...statik model kifayət etmir.

### 2. Şəxsi və Mülkiyyət Məlumatları
LLM-lər şirkətinizin Confluence wiki-si, kod bazanız, müştəri qeydləriniz və ya daxili siyasətləriniz üzərindən əyidilməyib. Fine-tuning ümumi bilik əlavə edə bilər, amma bahalıdır, yenilənməsi yavaşdır və yenə də dinamik məlumatları idarə etmir.

### 3. Halüsinasiyaların Azaldılması
LLM bir şeyi bilmədikdə, çox vaxt əminliklə cavab uydurur. RAG modeli **yalnız əldə edilmiş kontekstdən** cavab verməyə məcbur edir, bu da faktiki sorğular üçün halüsinasiyaları əhəmiyyətli dərəcədə azaldan faktiki əsas verir.

### RAG vs Fine-tuning

| Narahatlıq | RAG | Fine-tuning |
|---------|-----|-------------|
| Yeni faktlar əlavə etmək | Asan (yenidən indeksləmə) | Bahalı (yenidən əyitmə) |
| Dinamik məlumatlar | Mükəmməl | Zəif |
| Şəxsi məlumatlar | Mükəmməl | Yaxşı (amma statik) |
| Davranış dəyişiklikləri | Zəif | Mükəmməl |
| Üslub/format dəyişiklikləri | Zəif | Mükəmməl |
| Gecikmə | Retrieval yükü əlavə edir | Heç bir yük yoxdur |
| Xərc | Retrieval + inference | Yüksək ilkin, sorğu başına aşağı |

Baş qayda: **Bilik üçün RAG, davranış üçün fine-tuning.**

---

## Tam RAG Pipeline-ı

### Mərhələ 1: İndeksləmə (Oflayn)

```
Xam Sənədlər
     │
     ▼
Sənəd Yükləmə (PDF, HTML, DOCX, DB qeydləri...)
     │
     ▼
Mətn Çıxarma və Təmizlənmə
     │
     ▼
Chunking (sabit ölçülü, semantik, iyerarxik...)
     │
     ▼
Metadata Çıxarma (mənbə, tarix, bölmə başlıqları...)
     │
     ▼
Embedding Yaratma (mətn → vektor)
     │
     ▼
Vector Store (pgvector, Pinecone, Qdrant...)
```

### Mərhələ 2: Retrieval (Onlayn, hər sorğu üçün)

```
İstifadəçi Sorğusu
     │
     ▼
Sorğu Anlama (istəyə bağlı: genişlənmə, HyDE, klassifikasiya)
     │
     ▼
Sorğu Embedding-i
     │
     ▼
Vector Search (vector store-da ANN axtarışı)
     │
     ▼
İstəyə bağlı: Reranking (cross-encoder, Cohere Rerank)
     │
     ▼
Ən Yaxşı-K Əldə Edilmiş Chunk-lar
```

### Mərhələ 3: Artırma

```
Ən Yaxşı-K Chunk-lar + İstifadəçi Sorğusu
     │
     ▼
Prompt Qurma (sistem promptu + kontekst + sorğu)
     │
     ▼
Kontekst Pəncərəsi İdarəetməsi (model limitlərə sığdır)
```

### Mərhələ 4: Yaratma

```
Artırılmış Prompt
     │
     ▼
LLM (Claude, GPT-4, Gemini...)
     │
     ▼
İstinadlarla Cavab
     │
     ▼
Son İşlənmə (istinadları çıxar, cavabı formatla)
```

---

## Sadə RAG vs Qabaqcıl RAG

### Sadə RAG (Başlanğıc Xətti)

Ən sadə tətbiq:
1. Sənədləri sabit ölçülü parçalara böl
2. Hər chunk-ı embed et
3. Sorğu zamanı: sorğunu embed et, cosine axtarışı yap, ilk 5-i götür
4. Prompta yerləşdir, yarat

**Uğursuzluq halları**:
- Zəif chunking konteksti itirir (cümlələri bölür, başlıqları məzmundan ayırır)
- Sorğu-sənəd asimmetriyası (qısa sorğu vs uzun sənəd)
- Lüğət uyğunsuzluğuna görə uyğun chunk-ların atlanması
- Cavab doğrulama yoxdur

### Qabaqcıl RAG

Hər mərhələdə tətbiq edilən yaxşılaşdırmalar:

**Retrieval-dan əvvəl**:
- Sorğu yenidən yazma / genişlənmə
- HyDE (hipotetik sənəd yarat, bunun yerinə embed et)
- Sorğu yönləndirmə (fərqli sorğu növləri üçün fərqli retrieval strategiyaları)

**Retrieval**:
- Hibrid axtarış (BM25 + vector)
- Metadata filtrləmə
- Fərqli məzmun növləri üçün çoxlu vector store-lar

**Retrieval-dan sonra**:
- Cross-encoder ilə reranking
- Kontekst sıxışdırma (əldə edilmiş chunk-ları xülasə et)
- Müxtəliflik filtrləmə (demək olar ki eyni chunk-ları sil)

**Yaratma**:
- İstinad izlənməsi
- Sədaqət doğrulanması
- Özünü-ardıcıllıq yoxlaması (bir neçə dəfə yarat, müqayisə et)

---

## RAG Qiymətləndirmə Metrikalari

### RAGAS Framework Metrikalari

| Metrika | Ölçdüyü | Necə |
|--------|----------|-----|
| **Faithfulness** | Cavab yalnız kontekstdəki iddiaları ehtiva edirmi? | LLM-as-judge: iddiaları çıxar, kontekstlə doğrula |
| **Cavab Uyğunluğu** | Cavab sualı ünvanlandırırmı? | Cavabdan suallar yarat, orijinalla müqayisə et |
| **Kontekst Dəqiqliyi** | Əldə edilmiş chunk-lar uyğundurmu? Retrieval dəqiqliyi | Əldə edilmiş chunk-ların neçə faizi uyğundur |
| **Kontekst Tam Əhatəsi** | Bütün uyğun faktlar əldə edildimi? | Əsas həqiqətin neçə faizi əldə edilmiş kontekstdədir |
| **Cavab Düzgünlüyü** | Əsas həqiqətə qarşı faktiki dəqiqlik | F1 balı: semantik oxşarlıq + faktiki üst-üstə düşmə |

### Praktik Qiymətləndirmə Döngüsü

```
Qiymətləndirmə dataseti qur:
- Məlum əsas həqiqəti olan 50-200 sual/cavab cütü
- Fərqli sorğu növlərini əhatə et (faktiki, müqayisəli, çox addımlı)

Hər sorğu üçün:
1. Retrieval işlət → kontekst dəqiqliyi və tam əhatəsini ölç
2. Yaratmanı işlət → faithfulness və cavab uyğunluğunu ölç
3. Əsas həqiqətlə müqayisə et → cavab düzgünlüyünü ölç

Aşağıdakılarda iterasiya et:
- Chunking strategiyası (kontekst tam əhatəsi aşağıdırsa)
- Retrieval (kontekst dəqiqliyi aşağıdırsa)
- Prompt (faithfulness aşağıdırsa)
- Model (cavab keyfiyyəti aşağıdırsa)
```

---

## Laravel Tətbiqi: Tam RAG Sistemi

### Verilənlər Bazası Miqrasiyaları

```php
<?php
// database/migrations/create_rag_system_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source_type'); // 'file', 'url', 'database', 'api'
            $table->string('source_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('raw_content')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('status')->default('pending'); // pending, indexing, indexed, failed
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('source_type');
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('knowledge_documents')
                ->cascadeOnDelete();
            $table->text('content');
            $table->integer('chunk_index');
            $table->integer('token_count')->nullable();
            $table->jsonb('metadata')->default('{}'); // səhifə, bölmə, başlıqlar və s.
            $table->string('embedding_model')->nullable();
            $table->timestamps();
        });

        // Vektor sütunu ayrıca əlavə edilir
        DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding vector(1536)');

        // Sürətli ANN axtarışı üçün HNSW indeksi
        DB::statement('
            CREATE INDEX knowledge_chunks_embedding_idx
            ON knowledge_chunks
            USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ');

        Schema::create('rag_queries', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->nullable();
            $table->text('query');
            $table->text('answer')->nullable();
            $table->jsonb('retrieved_chunks')->default('[]');
            $table->jsonb('citations')->default('[]');
            $table->float('faithfulness_score')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_queries');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
    }
};
```

### Sənəd Qəbul Pipeline-ı

```php
<?php

namespace App\Services\RAG;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeChunk;
use App\Services\AI\EmbeddingService;
use App\Services\RAG\ChunkingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentIngestionPipeline
{
    public function __construct(
        private EmbeddingService $embeddingService,
        private ChunkingService $chunkingService,
        private TextExtractor $textExtractor,
    ) {}

    /**
     * Tam pipeline: xam mətn və ya fayldan sənəd qəbul et.
     */
    public function ingest(
        string $title,
        string $content,
        string $sourceType = 'text',
        array $metadata = [],
    ): KnowledgeDocument {
        return DB::transaction(function () use ($title, $content, $sourceType, $metadata) {
            // 1. Sənəd qeydi yarat
            $document = KnowledgeDocument::create([
                'title' => $title,
                'source_type' => $sourceType,
                'raw_content' => $content,
                'metadata' => $metadata,
                'status' => 'indexing',
            ]);

            try {
                // 2. Mətni təmizlə və ön-işlə
                $cleanedContent = $this->preprocessText($content);

                // 3. Sənədi chunk-la
                $chunks = $this->chunkingService->chunk($cleanedContent, [
                    'strategy' => 'recursive',
                    'chunk_size' => 512,
                    'overlap' => 50,
                    'metadata' => $metadata,
                ]);

                // 4. Toplu embeddingləri yarat
                $texts = array_column($chunks, 'text');
                $embeddings = $this->embeddingService->embedBatch($texts);

                // 5. Chunk-ları embeddinglərlə saxla
                foreach ($chunks as $index => $chunk) {
                    $knowledgeChunk = KnowledgeChunk::create([
                        'document_id' => $document->id,
                        'content' => $chunk['text'],
                        'chunk_index' => $index,
                        'token_count' => $chunk['token_count'] ?? null,
                        'metadata' => array_merge($chunk['metadata'] ?? [], [
                            'document_title' => $title,
                            'source_type' => $sourceType,
                        ]),
                        'embedding_model' => 'text-embedding-3-small',
                    ]);

                    // Vektoru saxla
                    $vectorString = '[' . implode(',', $embeddings[$index]) . ']';
                    DB::statement(
                        'UPDATE knowledge_chunks SET embedding = ? WHERE id = ?',
                        [$vectorString, $knowledgeChunk->id]
                    );
                }

                $document->update([
                    'status' => 'indexed',
                    'indexed_at' => now(),
                ]);

                Log::info('Sənəd indeksləndi', [
                    'document_id' => $document->id,
                    'chunks' => count($chunks),
                ]);

            } catch (\Throwable $e) {
                $document->update(['status' => 'failed']);
                Log::error('Sənəd qəbulu uğursuz oldu', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            return $document->fresh();
        });
    }

    /**
     * Fayldan qəbul et (PDF, DOCX, HTML, TXT).
     */
    public function ingestFile(string $filePath, array $metadata = []): KnowledgeDocument
    {
        $content = $this->textExtractor->extract($filePath);
        $title = pathinfo($filePath, PATHINFO_FILENAME);
        $mimeType = mime_content_type($filePath);

        return $this->ingest($title, $content, 'file', array_merge($metadata, [
            'file_path' => $filePath,
            'mime_type' => $mimeType,
        ]));
    }

    private function preprocessText(string $text): string
    {
        // Artıq boşluqları sil
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Yeni sətir xaricindəki idarəetmə simvollarını sil
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        return trim($text);
    }
}
```

### Retrieval Xidməti

```php
<?php

namespace App\Services\RAG;

use App\Models\KnowledgeChunk;
use App\Services\AI\EmbeddingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RetrievalService
{
    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Sorğu üçün ən uyğun chunk-ları əldə et.
     *
     * @param string $query İstifadəçinin təbii dil sualı
     * @param int $topK Qaytarılacaq chunk sayı
     * @param float $threshold Minimum oxşarlıq balı
     * @param array $filters Metadata filtrləri (məs., ['document_type' => 'policy'])
     */
    public function retrieve(
        string $query,
        int $topK = 5,
        float $threshold = 0.60,
        array $filters = [],
    ): Collection {
        // Sorğunu embed et
        $queryVector = $this->embeddingService->embed($query);
        $vectorString = '[' . implode(',', $queryVector) . ']';

        $queryBuilder = KnowledgeChunk::query()
            ->selectRaw("
                knowledge_chunks.*,
                1 - (embedding <=> ?) as similarity
            ", [$vectorString])
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.document_id')
            ->where('knowledge_documents.status', 'indexed')
            ->whereNotNull('knowledge_chunks.embedding')
            ->whereRaw('1 - (embedding <=> ?) >= ?', [$vectorString, $threshold])
            ->orderByRaw('embedding <=> ?', [$vectorString])
            ->limit($topK * 2); // Müxtəliflik filtrləmə üçün lazımdan çox əldə et

        // Metadata filtrləri tətbiq et
        foreach ($filters as $key => $value) {
            $queryBuilder->whereRaw(
                "knowledge_chunks.metadata->>? = ?",
                [$key, $value]
            );
        }

        $results = $queryBuilder->get();

        // Müxtəliflik filtrləmə: demək olar ki eyni chunk-ları sil
        $deduplicated = $this->diversityFilter($results, maxSimilarity: 0.95);

        return $deduplicated->take($topK)->map(fn($chunk) => [
            'id' => $chunk->id,
            'document_id' => $chunk->document_id,
            'content' => $chunk->content,
            'similarity' => round($chunk->similarity, 4),
            'metadata' => $chunk->metadata,
            'chunk_index' => $chunk->chunk_index,
        ]);
    }

    /**
     * Bir-birinə çox oxşar olan artıq chunk-ları sil.
     * Əldə edilmiş kontekstdə müxtəlifliyi təmin edir.
     */
    private function diversityFilter(Collection $chunks, float $maxSimilarity = 0.95): Collection
    {
        $selected = collect();

        foreach ($chunks as $chunk) {
            $isDuplicate = false;

            foreach ($selected as $selectedChunk) {
                // Eyni sənəd, bitişik chunk-lar — mətn üst-üstə düşməsini yoxla
                if ($chunk->document_id === $selectedChunk->document_id) {
                    $overlap = $this->textOverlap($chunk->content, $selectedChunk->content);
                    if ($overlap > $maxSimilarity) {
                        $isDuplicate = true;
                        break;
                    }
                }
            }

            if (!$isDuplicate) {
                $selected->push($chunk);
            }
        }

        return $selected;
    }

    private function textOverlap(string $a, string $b): float
    {
        $wordsA = array_flip(str_word_count(strtolower($a), 1));
        $wordsB = str_word_count(strtolower($b), 1);

        $commonWords = count(array_filter($wordsB, fn($w) => isset($wordsA[$w])));
        $totalWords = max(count($wordsA), count(array_flip($wordsB)));

        return $totalWords > 0 ? $commonWords / $totalWords : 0.0;
    }
}
```

### Prompt Artırma

```php
<?php

namespace App\Services\RAG;

use Illuminate\Support\Collection;

class PromptAugmentationService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a helpful assistant with access to a knowledge base. Answer questions based ONLY on the provided context.

Rules:
1. Only use information from the provided context to answer.
2. If the context doesn't contain enough information to answer, say "I don't have enough information in my knowledge base to answer this question."
3. Always cite your sources using [Source N] notation in your answer.
4. Be concise and accurate.
5. Do not make up facts or add information not in the context.
PROMPT;

    /**
     * Əldə edilmiş chunk-lardan artırılmış prompt qur.
     *
     * @param string $query İstifadəçinin sualı
     * @param Collection $retrievedChunks RetrievalService-dən chunk-lar
     * @param array $conversationHistory Çox turlu söhbət üçün əvvəlki mesajlar
     */
    public function buildPrompt(
        string $query,
        Collection $retrievedChunks,
        array $conversationHistory = [],
    ): array {
        $contextBlock = $this->formatContext($retrievedChunks);

        $userMessage = <<<MSG
        Context from knowledge base:

        {$contextBlock}

        ---

        Question: {$query}
        MSG;

        $messages = [];

        // Söhbət tarixçəsi əlavə et (çox turlu üçün)
        foreach ($conversationHistory as $turn) {
            $messages[] = [
                'role' => $turn['role'],
                'content' => $turn['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return [
            'system' => self::SYSTEM_PROMPT,
            'messages' => $messages,
        ];
    }

    /**
     * Əldə edilmiş chunk-ları nömrəli mənbələr kimi formatla.
     */
    private function formatContext(Collection $chunks): string
    {
        return $chunks->map(function ($chunk, $index) {
            $sourceNum = $index + 1;
            $source = $chunk['metadata']['document_title'] ?? 'Naməlum Mənbə';
            $page = isset($chunk['metadata']['page']) ? ", Səhifə {$chunk['metadata']['page']}" : '';

            return "[Source {$sourceNum}] ({$source}{$page})\n{$chunk['content']}";
        })->implode("\n\n");
    }

    /**
     * Yaradılmış cavabdan istinad referanslarını çıxar.
     * [Source N] nümunələrini tapır və faktiki chunk-larla əlaqələndirir.
     */
    public function extractCitations(string $answer, Collection $chunks): array
    {
        preg_match_all('/\[Source (\d+)\]/', $answer, $matches);
        $citedIndices = array_unique(array_map('intval', $matches[1]));

        return collect($citedIndices)
            ->filter(fn($i) => $i >= 1 && $i <= $chunks->count())
            ->map(fn($i) => [
                'source_number' => $i,
                'chunk_id' => $chunks[$i - 1]['id'],
                'document_id' => $chunks[$i - 1]['document_id'],
                'title' => $chunks[$i - 1]['metadata']['document_title'] ?? 'Naməlum',
                'content_preview' => substr($chunks[$i - 1]['content'], 0, 150) . '...',
            ])
            ->values()
            ->all();
    }
}
```

### Claude ilə Yaratma + İstinad İzlənməsi

```php
<?php

namespace App\Services\RAG;

use App\Models\RagQuery;
use Anthropic\Client as AnthropicClient;
use Illuminate\Support\Facades\Log;

class RAGGenerationService
{
    public function __construct(
        private RetrievalService $retrievalService,
        private PromptAugmentationService $augmentationService,
        private AnthropicClient $anthropic,
    ) {}

    /**
     * Tam RAG sorğusu: al → artır → yarat → izlə.
     *
     * @param string $query İstifadəçinin sualı
     * @param string|null $sessionId Söhbət tarixçəsi izlənməsi üçün
     * @param array $options topK, threshold, filters
     */
    public function query(
        string $query,
        ?string $sessionId = null,
        array $options = [],
    ): array {
        $startTime = microtime(true);

        // 1. Uyğun chunk-ları əldə et
        $chunks = $this->retrievalService->retrieve(
            query: $query,
            topK: $options['top_k'] ?? 5,
            threshold: $options['threshold'] ?? 0.60,
            filters: $options['filters'] ?? [],
        );

        if ($chunks->isEmpty()) {
            return $this->noContextResponse($query, $sessionId);
        }

        // 2. Çox turlu üçün söhbət tarixçəsini qur
        $history = $sessionId
            ? $this->getConversationHistory($sessionId)
            : [];

        // 3. Promptu kontekstlə artır
        $prompt = $this->augmentationService->buildPrompt($query, $chunks, $history);

        // 4. Claude ilə yarat
        $response = $this->anthropic->messages()->create([
            'model' => 'claude-opus-4-5',
            'max_tokens' => 1024,
            'system' => $prompt['system'],
            'messages' => $prompt['messages'],
        ]);

        $answer = $response->content[0]->text;

        // 5. İstinadları çıxar
        $citations = $this->augmentationService->extractCitations($answer, $chunks);

        $latencyMs = (int)((microtime(true) - $startTime) * 1000);

        // 6. Analitika və qiymətləndirmə üçün sorğunu saxla
        $ragQuery = RagQuery::create([
            'session_id' => $sessionId,
            'query' => $query,
            'answer' => $answer,
            'retrieved_chunks' => $chunks->toArray(),
            'citations' => $citations,
            'latency_ms' => $latencyMs,
        ]);

        return [
            'query_id' => $ragQuery->id,
            'answer' => $answer,
            'citations' => $citations,
            'sources' => $chunks->map(fn($c) => [
                'title' => $c['metadata']['document_title'] ?? 'Naməlum',
                'similarity' => $c['similarity'],
            ])->unique('title')->values()->all(),
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * Real vaxt UX üçün axın versiyası.
     */
    public function queryStream(
        string $query,
        ?string $sessionId = null,
        array $options = [],
    ): \Generator {
        $chunks = $this->retrievalService->retrieve(
            query: $query,
            topK: $options['top_k'] ?? 5,
            threshold: $options['threshold'] ?? 0.60,
        );

        if ($chunks->isEmpty()) {
            yield ['type' => 'no_context', 'content' => 'Bilik bazasında uyğun məlumat tapılmadı.'];
            return;
        }

        $prompt = $this->augmentationService->buildPrompt($query, $chunks);

        // Əldə edilmiş mənbələri əvvəlcə göstər (UI üçün)
        yield [
            'type' => 'sources',
            'sources' => $chunks->map(fn($c) => $c['metadata']['document_title'] ?? 'Naməlum')->unique()->values()->all(),
        ];

        // Yaradılmış cavabı axınla göndər
        $stream = $this->anthropic->messages()->stream([
            'model' => 'claude-opus-4-5',
            'max_tokens' => 1024,
            'system' => $prompt['system'],
            'messages' => $prompt['messages'],
        ]);

        foreach ($stream as $event) {
            if ($event->type === 'content_block_delta') {
                yield ['type' => 'delta', 'content' => $event->delta->text];
            }
        }

        yield ['type' => 'done'];
    }

    private function noContextResponse(string $query, ?string $sessionId): array
    {
        $answer = "Bilik bazamda bu sualı cavablandırmaq üçün uyğun məlumat yoxdur. Xahiş edirik sualı yenidən ifadə edin və ya fərqli mövzu haqqında soruşun.";

        RagQuery::create([
            'session_id' => $sessionId,
            'query' => $query,
            'answer' => $answer,
            'retrieved_chunks' => [],
            'citations' => [],
        ]);

        return [
            'answer' => $answer,
            'citations' => [],
            'sources' => [],
        ];
    }

    private function getConversationHistory(string $sessionId): array
    {
        return RagQuery::where('session_id', $sessionId)
            ->orderBy('created_at')
            ->take(10) // Son 10 tur
            ->get()
            ->flatMap(fn($q) => [
                ['role' => 'user', 'content' => $q->query],
                ['role' => 'assistant', 'content' => $q->answer],
            ])
            ->all();
    }
}
```

### HTTP Controller

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\RAG\DocumentIngestionPipeline;
use App\Services\RAG\RAGGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RAGController extends Controller
{
    public function __construct(
        private RAGGenerationService $ragService,
        private DocumentIngestionPipeline $ingestionPipeline,
    ) {}

    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:3|max:1000',
            'session_id' => 'nullable|string|max:64',
            'top_k' => 'nullable|integer|min:1|max:20',
            'threshold' => 'nullable|numeric|min:0|max:1',
        ]);

        $sessionId = $validated['session_id'] ?? Str::uuid()->toString();

        $result = $this->ragService->query(
            query: $validated['query'],
            sessionId: $sessionId,
            options: [
                'top_k' => $validated['top_k'] ?? 5,
                'threshold' => $validated['threshold'] ?? 0.60,
            ],
        );

        return response()->json(array_merge($result, ['session_id' => $sessionId]));
    }

    public function queryStream(Request $request): StreamedResponse
    {
        $query = $request->input('query');
        $sessionId = $request->input('session_id', Str::uuid()->toString());

        return response()->stream(function () use ($query, $sessionId) {
            foreach ($this->ragService->queryStream($query, $sessionId) as $event) {
                echo 'data: ' . json_encode($event) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'content' => 'required|string',
            'source_type' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $document = $this->ingestionPipeline->ingest(
            title: $validated['title'],
            content: $validated['content'],
            sourceType: $validated['source_type'] ?? 'text',
            metadata: $validated['metadata'] ?? [],
        );

        return response()->json([
            'document_id' => $document->id,
            'status' => $document->status,
        ], 201);
    }
}
```

---

## Memarlar üçün Fikirlər

### Kontekst Pəncərəsi İdarəetməsi

Əldə edilmiş kontekst sistem promptu, söhbət tarixçəsi və yaradılmış cavabla birlikdə modelin kontekst pəncərəsinə sığmalıdır. Büdcə planlaması:

```
Ümumi kontekst: 200,000 token (Claude)
Cavab üçün ayrılan: ~2,000 token
Sistem promptu: ~500 token
Söhbət tarixçəsi: ~3,000 token
Kontekst üçün mövcud: ~194,500 token

~300 token/chunk-da, bu nəzəri olaraq ~600 chunk icazə verir.
Praktikada, keyfiyyət və xərc səbəbindən 5-20 chunk istifadə et.
```

### Yaratmadan Əvvəl Retrieval Qiymətləndirmə

Retrieval-ı təcrid etmədən tam RAG pipeline-ını qiymətləndirmək ümumi bir səhvdir. Kontekst tam əhatəsi 40%-dirsə (uyğun məlumatın 60%-ni itiririksə), heç bir prompt mühəndisliyi cavabları düzəldə bilməz. Hər addımı müstəqil şəkildə profilləşdir.

### Chunk Üst-üstə Düşmə və Valideyn-Uşaq Retrieval

Güclü bir nümunə: dəqiq retrieval üçün kiçik chunk-ları indeksə əlavə et, amma yaratma konteksti üçün daha böyük valideyn chunk-ı qaytar. Bu retrieval dəqiqliyi + yaratma kontekstini verir.

```
Sənəd → 2048-tokenli valideyn chunk-larına böl
Hər valideyn → 512-tokenli uşaq chunk-larına böl
İndeks: yalnız uşaq chunk-ları
Al: uşaq chunk-ları (dəqiq uyğunluq)
LLM-ə qaytar: valideyn chunk məzmunu (tam kontekst)
```

### Asinxron Qəbul Arxitekturası

İstehsalda, heç vaxt veb sorğusunda sinxron şəkildə qəbul etmə:

```php
// Növbəyə göndər
IngestDocumentJob::dispatch($documentId)->onQueue('indexing');

// İş: mətn çıxarma, chunking, embedding, saxlama ilə məşğul olur
// Çoxlu işçilər paralel emal edə bilər
// Laravel növbələrə qurulmuş sürət limiti və yenidən cəhd məntiqi
```

### Çox-kiracılıq Mülahizələri

SaaS sistemlərinde hər kiracının izolyasiya edilmiş chunk-ları olmalıdır. Bütün sorğulara kiracı əhatəsi əlavə et:

```php
// RetrievalService-də
$queryBuilder->where('knowledge_chunks.metadata->>"tenant_id"', $tenantId);
```

Yaxud kiracı başına ayrı pgvector cədvəlləri istifadə et (daha yaxşı izolyasiya, silinmə daha asan, bir qədər daha mürəkkəb).
