# Embeddings və Vector Search: Dərin Araşdırma (Junior)

## Embedding Riyazi Olaraq Nədir?

Embedding — diskret obyektləri (sözlər, cümlələr, sənədlər, şəkillər, kod) davamlı **R^n** vektor fəzasına proyeksiya edən bir funksiyadır. Əsas xüsusiyyət odur ki, vektor fəzasının geometriyası orijinal məlumatın semantikasını əks etdirir.

Formal olaraq: V korpus lüğəti verildikdə, E embedding modeli hər hansı x girişini sıx vektora çevirir:

```
E(x) = v ∈ R^d
```

burada d ölçülülükdür (məsələn, `text-embedding-3-small` üçün 1536, böyük modellər üçün 4096).

### Niyə Sıx Vektorlar?

Sinir şəbəkəsinə əsaslanan embeddinglərdən əvvəl mətn **seyrek vektorlarla** (bag-of-words, TF-IDF) təmsil edilirdi:

- Lüğətin həcmi: 100,000 söz → 100,000-ölçülü vektor
- Hər sənəd: əsasən sıfırlardan ibarət (seyrek)
- Problem: "car" və "automobile" bu fəzada ortogonaldır — sıfır oxşarlıq

Sıx embeddinglər bunu sıxılmış, semantik təmsil öyrənərək həll edir:

- Lüğətin həcmindən asılı olmayaraq sabit ölçülülük
- Hər ölçü məna daşıyır (baxmayaraq ki, interpretasiya edilmir)
- Semantik cəhətdən oxşar girişlər fəzada bir arada qruplaşır

### Embeddinglər Necə Yaradılır

Müasir mətn embeddingləri böyük korpuslarda əyidilmiş **transformer-əsaslı modellərdən** gəlir. Proses:

1. **Tokenizasiya**: Mətn → token ID-ləri (BPE/WordPiece vasitəsilə alt-söz vahidləri)
2. **Token embeddingləri**: Hər token öyrənilmiş bir vektor alır (embedding cədvəli)
3. **Mövqe kodlaması**: Token vektorlarına mövqe məlumatı əlavə edilir
4. **Transformer qatları**: Özünüdiqqət mexanizmi kontekst-xəbərdar vektorlar yaratmaq üçün token təmsilləri arasında dəfələrlə qarışdırır
5. **Pooling**: Çox sayda token vektoru → tək sənəd vektoru
   - CLS token pooling (BERT üslubu)
   - Orta pooling (bütün token vektorlarının ortalaması)
   - Çəkili orta pooling (diqqətlə çəkiləndirilmiş)

Model **kontrastiv öyrənmə** vasitəsilə əyidilir: oxşar mətn cütləri embedding fəzasında bir-birinə yaxınlaşdırılır, fərqli cütlər isə uzaqlaşdırılır. İtki funksiyası (InfoNCE / çoxlu neqativ sıralama itkisi) bu geometriyaya uyğunlaşdırılır.

### Semantik Fəzanın Geometriyası

Əyitmə nəticəsində yaranan əsas xüsusiyyətlər:

- **Klasterləşmə**: Oxşar mövzulardakı sənədlər klasterlər əmələ gətirir
- **İstiqamətlilik**: Vektorun istiqaməti semantik mənanı kodlayır
- **Xətti əlaqələr**: Anoloji mühakimə: king - man + woman ≈ queen
- **İzotropiya**: Yaxşı əyidilmiş embedding fəzaları vektorları vahid şəkildə payladır, alt-fəzalara çökmənin qarşısını alır

Bu geometriya cosine similarity-nin işləməsinin səbəbidir — oxşar sənədlər başlanğıcdan eyni istiqamətdə işarə edir.

---

## Oxşarlıq Metrikalari: Hansını Nə Zaman İstifadə Etməli

### Cosine Similarity

```
cos(A, B) = (A · B) / (|A| × |B|)
```

Aralıq: [-1, 1]. Vektorlar arasındakı **bucağı** ölçür, miqnasiyı nəzərə almır.

**Nə zaman istifadə edilir**: Miqnas mənasız olan mətn embeddingləri üçün (əksər embedding modellər çıxışları vahid uzunluğa normallaşdırır, bu da onu dot product-a ekvivalent edir).

**Xüsusiyyətlər**:
- Miqyasdançıxarlılıq: iki dəfə təkrarlanan sənəd orijinalla eyni cosine similarity-ə malikdir
- Semantik oxşarlıq üçün ən intuitiv
- Sənəd uzunluğundakı fərqlərə qarşı davamlı

### Dot Product

```
A · B = Σ(Aᵢ × Bᵢ)
```

Vahid vektorlar üçün (||A|| = ||B|| = 1): dot product = cosine similarity.

**Nə zaman istifadə edilir**: Vektorlar normallaşdırıldıqda (OpenAI embeddingləri vahid normallaşdırılmışdır) dot product hesab baxımından daha ucuz və cosine-ə ekvivalentdir. Miqnasın uyğunluğu kodladığı hallarda da faydalıdır (məsələn, populyarlığın önəmli olduğu tövsiyə sistemlərinde).

**Qeyd**: OpenAI embeddingləri üçün cosine similarity tövsiyə edir, lakin çıxışlar normallaşdırıldığından dot product eyni sıralamaya verir.

### Evklid Məsafəsi (L2)

```
d(A, B) = √(Σ(Aᵢ - Bᵢ)²)
```

Aralıq: [0, ∞). Nöqtələr arasındakı düz xətt məsafəsini ölçür.

**Nə zaman istifadə edilir**:
- Fəzada mütləq mövqe önəm kəsb etdikdə, yalnız istiqamət deyil
- Lokal sıxlığa əsaslanan metodlar (k-NN klasterləşmə)
- L2 məqsədi ilə əyidilmiş bəzi ixtisaslaşdırılmış modellər

**Cosine ilə əlaqə**: Vahid vektorlar üçün L2 məsafəsi ilə cosine məsafəsi monoton əlaqədədir: `L2² = 2(1 - cos)`. Beləliklə, normallaşdırılmış vektorlar üçün L2-yə görə sıralamaq ≡ cosine-ə görə sıralamaq.

### Xülasə Cədvəli

| Metrika | Formula | Aralıq | Ən Uyğun |
|--------|---------|-------|----------|
| Cosine | A·B / (|A||B|) | [-1, 1] | Mətn semantik oxşarlığı (standart seçim) |
| Dot Product | A·B | (-∞, ∞) | Normallaşdırılmış vektorlar, tövsiyə |
| Evklid | √Σ(Aᵢ-Bᵢ)² | [0, ∞) | Məkan klasterləşməsi, normallaşdırılmamış vektorlar |
| Manhattan | Σ|Aᵢ-Bᵢ| | [0, ∞) | Seyrek yüksək ölçülü (embeddinglər üçün nadir) |

### İç Hasıl Fəzası və ANN

Milyonlarla vektoru olan istehsal sistemlərində **approximate nearest neighbor (ANN)** alqoritmləri istifadə edilir:

- **HNSW** (Hierarchical Navigable Small World): qrafa əsaslanan, O(log n) axtarış, yüksək recall
- **IVF** (Inverted File): klasterləşməyə əsaslanan, sürətli amma aşağı recall
- **PQ** (Product Quantization): vektorları sıxır, dəqiqliyini yaddaş üçün qurban verir

pgvector həm dəqiq, həm də HNSW/IVF indekslərini dəstəkləyir.

---

## Embedding Modellərinin Müqayisəsi

### OpenAI text-embedding-3-small / text-embedding-3-large

- **Ölçülər**: 1536 (small), 3072 (large) — Matryoshka əyitməsi vasitəsilə azaldıla bilər
- **Kontekst pəncərəsi**: 8191 token
- **Performans**: İngiliscədə güclü, çoxdilli orta səviyyədə
- **Qiymət**: Milyonda $0.02 / $0.13 token (small/large)
- **Üstünlüklər**: Asan API, ardıcıl keyfiyyət, Matryoshka yenidən əyitmədən ölçü azaltmağa imkan verir
- **Çatışmazlıqlar**: Qapalı model, məlumat məxfiliyi narahatlıqları, gecikmə

### Cohere embed-v3

- **Ölçülər**: 1024
- **Kontekst pəncərəsi**: 512 token (qısa!) — chunking strategiyası üçün vacibdir
- **Giriş növləri**: `search_document`, `search_query`, `classification`, `clustering` (asimmetrik embeddinglər)
- **Üstünlüklər**: Retrieval üçün ən yüksək sinif, çoxdilli, giriş növü optimizasiyası
- **Çatışmazlıqlar**: Daha qısa kontekst, ödənişli API

### nomic-embed-text-v1.5

- **Ölçülər**: 768
- **Kontekst pəncərəsi**: 8192 token
- **Açıq mənbə**: Apache 2.0 lisenziyası, öz-hostinqdə işlədilə bilər
- **Üstünlüklər**: Açıq mənbə, güclü MTEB performansı, uzun kontekst, Matryoshka dəstəyi
- **Qiymət**: Ollama vasitəsilə öz-hostinqdə pulsuz

### Sentence Transformers (all-MiniLM-L6-v2, BGE, E5)

- **all-MiniLM-L6-v2**: 384 ölçü, kiçik və sürətli, yerli istifadə üçün yaxşı
- **BGE-M3**: Çoxdilli, 1024 ölçü, hibrid seyrek+sıx, MTEB-də SOTA
- **E5-mistral-7b**: 7B parametrli model, ən yüksək keyfiyyət, resurs tutumlu

### MTEB Benchmark (2025-ci ilə qədər)

| Model | MTEB Balı | Ölçülər | Kontekst | Açıq |
|-------|-----------|------|---------|------|
| text-embedding-3-large | ~64.6 | 3072 | 8191 | Xeyr |
| Cohere embed-v3 | ~64.5 | 1024 | 512 | Xeyr |
| nomic-embed-text-v1.5 | ~62.3 | 768 | 8192 | Bəli |
| BGE-M3 | ~63.0 | 1024 | 8192 | Bəli |
| all-MiniLM-L6-v2 | ~56.3 | 384 | 256 | Bəli |

---

## Laravel Tətbiqi

### 1. pgvector üçün Verilənlər Bazası Miqrasiyası

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector genişlənməsini aktiv et
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('model')->default('text-embedding-3-small');
            $table->integer('chunk_index');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        // Vektor sütunu ayrıca əlavə edilir (pgvector sintaksisi)
        // text-embedding-3-small üçün 1536 ölçü
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1536)');

        // Approximate nearest neighbor axtarışı üçün HNSW indeksi
        // m: qat başına maksimum bağlantı, ef_construction: qurma zamanı dəqiqlik
        DB::statement('
            CREATE INDEX document_chunks_embedding_hnsw_idx
            ON document_chunks
            USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
```

### 2. OpenAI API vasitəsilə Embedding Yaratmaq

```php
<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

readonly class EmbeddingService
{
    private const CACHE_TTL = 86400 * 7; // 7 gün
    private const MAX_TOKENS_PER_REQUEST = 8191;
    private const BATCH_SIZE = 100; // OpenAI sorğu başına 2048 giriş icazə verir

    public function __construct(
        private string $provider = 'openai',
        private string $model = 'text-embedding-3-small',
        private int $dimensions = 1536,
    ) {}

    /**
     * Tək mətn üçün embedding yarat.
     * Float massivi (sıx vektor) qaytarır.
     */
    public function embed(string $text): array
    {
        $cacheKey = "embedding:{$this->model}:" . md5($text);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($text) {
            return $this->fetchEmbedding($text);
        });
    }

    /**
     * Tək API sorğusunda bir neçə mətn üçün embedding yarat.
     * Döngüdə embed() çağırmaqdan daha effektivdir.
     */
    public function embedBatch(array $texts): array
    {
        $chunks = array_chunk($texts, self::BATCH_SIZE);
        $embeddings = [];

        foreach ($chunks as $chunk) {
            $batchEmbeddings = $this->fetchBatchEmbeddings($chunk);
            $embeddings = array_merge($embeddings, $batchEmbeddings);
        }

        return $embeddings;
    }

    private function fetchEmbedding(string $text): array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/embeddings', [
                'input' => $this->prepareText($text),
                'model' => $this->model,
                'dimensions' => $this->dimensions, // Matryoshka: ölçüləri azalt
                'encoding_format' => 'float',
            ]);

        if ($response->failed()) {
            Log::error('OpenAI embedding uğursuz oldu', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);
            throw new \RuntimeException('Embedding yaratma uğursuz oldu: ' . $response->json('error.message'));
        }

        return $response->json('data.0.embedding');
    }

    private function fetchBatchEmbeddings(array $texts): array
    {
        $preparedTexts = array_map([$this, 'prepareText'], $texts);

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/embeddings', [
                'input' => $preparedTexts,
                'model' => $this->model,
                'dimensions' => $this->dimensions,
                'encoding_format' => 'float',
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Toplu embedding uğursuz oldu: ' . $response->json('error.message'));
        }

        // Sıranı qorumaq üçün indeksə görə sırala
        $data = $response->json('data');
        usort($data, fn($a, $b) => $a['index'] <=> $b['index']);

        return array_column($data, 'embedding');
    }

    /**
     * Embedding üçün mətni normallaşdır və kes.
     * Əksər modellər təmiz, kəsilmiş girişlə daha yaxşı işləyir.
     */
    private function prepareText(string $text): string
    {
        // Boşluqları normallaşdır
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Kobud token təxmini: token başına ~4 simvol
        $maxChars = self::MAX_TOKENS_PER_REQUEST * 4;
        if (strlen($text) > $maxChars) {
            $text = substr($text, 0, $maxChars);
        }

        return $text;
    }

    /**
     * İki vektor arasında cosine similarity.
     * Verilənlər bazası olmadan debug/test üçün faydalıdır.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $val) {
            $dot += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
```

### 3. Cohere Embedding Xidməti

```php
<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

readonly class CohereEmbeddingService
{
    // Cohere asimmetrik embeddinglər istifadə edir:
    // Sənədlər 'search_document' növü, sorğular 'search_query' növü alır
    // Bu, retrieval keyfiyyətini əhəmiyyətli dərəcədə yaxşılaşdırır
    private const INPUT_TYPES = [
        'document' => 'search_document',
        'query' => 'search_query',
        'classification' => 'classification',
        'clustering' => 'clustering',
    ];

    public function __construct(
        private string $model = 'embed-english-v3.0',
    ) {}

    public function embedDocuments(array $texts): array
    {
        return $this->fetchEmbeddings($texts, 'search_document');
    }

    public function embedQuery(string $query): array
    {
        $result = $this->fetchEmbeddings([$query], 'search_query');
        return $result[0];
    }

    private function fetchEmbeddings(array $texts, string $inputType): array
    {
        $response = Http::withToken(config('services.cohere.key'))
            ->post('https://api.cohere.ai/v1/embed', [
                'texts' => $texts,
                'model' => $this->model,
                'input_type' => $inputType,
                'embedding_types' => ['float'],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Cohere embedding uğursuz oldu: ' . $response->body());
        }

        return $response->json('embeddings.float');
    }
}
```

### 4. Vektor Sütunu olan Eloquent Modeli

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'content',
        'model',
        'chunk_index',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'chunk_index' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Embedding vektoru saxla.
     * pgvector '[0.1, 0.2, ...]' formatını gözləyir
     */
    public function setEmbedding(array $vector): void
    {
        $vectorString = '[' . implode(',', $vector) . ']';
        DB::statement(
            'UPDATE document_chunks SET embedding = ? WHERE id = ?',
            [$vectorString, $this->id]
        );
    }

    /**
     * Cosine similarity axtarışı.
     * Chunk-ları sorğu vektoruna oxşarlığa görə sıralı qaytarır.
     *
     * @param array $queryVector Sorğu embeddingi
     * @param int $limit Nəticə sayı
     * @param float $threshold Minimum oxşarlıq balı (0-1)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function similaritySearch(
        array $queryVector,
        int $limit = 10,
        float $threshold = 0.7,
    ): \Illuminate\Database\Eloquent\Collection {
        $vectorString = '[' . implode(',', $queryVector) . ']';

        return static::query()
            ->selectRaw('*, 1 - (embedding <=> ?) as similarity', [$vectorString])
            ->whereRaw('1 - (embedding <=> ?) >= ?', [$vectorString, $threshold])
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?', [$vectorString])
            ->limit($limit)
            ->with('document')
            ->get();
    }

    /**
     * Dot product axtarışı (normallaşdırılmış vektorlar üçün daha sürətli).
     * Mənfi daxili hasıl üçün <#> operatorundan istifadə et (pgvector minimizasiya etməklə maksimizasiya edir).
     */
    public static function dotProductSearch(
        array $queryVector,
        int $limit = 10,
    ): \Illuminate\Database\Eloquent\Collection {
        $vectorString = '[' . implode(',', $queryVector) . ']';

        return static::query()
            ->selectRaw('*, (embedding <#> ?) * -1 as score', [$vectorString])
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <#> ?', [$vectorString])
            ->limit($limit)
            ->get();
    }
}
```

### 5. Tam Oxşarlıq Axtarışı Pipeline-ı

```php
<?php

namespace App\Services\AI;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;

class VectorSearchService
{
    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Semantik cəhətdən oxşar chunk-lar üçün axtarış.
     *
     * @param string $query Təbii dildə sorğu
     * @param int $topK Qaytarılacaq nəticə sayı
     * @param float $threshold Minimum cosine similarity
     * @param array $filters Əlavə metadata filtrləri
     */
    public function search(
        string $query,
        int $topK = 5,
        float $threshold = 0.65,
        array $filters = [],
    ): Collection {
        // Sorğu embeddingini yarat
        $queryVector = $this->embeddingService->embed($query);

        $vectorString = '[' . implode(',', $queryVector) . ']';

        // İsteğe bağlı filtrlərlə sorğu qur
        $queryBuilder = DocumentChunk::query()
            ->selectRaw('
                document_chunks.*,
                1 - (embedding <=> ?) as similarity
            ', [$vectorString])
            ->whereRaw('1 - (embedding <=> ?) >= ?', [$vectorString, $threshold])
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?', [$vectorString])
            ->limit($topK)
            ->with('document');

        // Metadata filtrləri tətbiq et (JSONB sütununda saxlanılır)
        foreach ($filters as $key => $value) {
            $queryBuilder->whereRaw(
                "metadata->? = ?",
                [$key, json_encode($value)]
            );
        }

        $results = $queryBuilder->get();

        return $results->map(fn($chunk) => [
            'content' => $chunk->content,
            'similarity' => round($chunk->similarity, 4),
            'source' => $chunk->document->title ?? 'Naməlum',
            'chunk_index' => $chunk->chunk_index,
            'metadata' => $chunk->metadata,
        ]);
    }

    /**
     * Embedding yaradaraq və saxlayaraq yeni sənədi indeksə əlavə et.
     *
     * @param int $documentId
     * @param array $chunks Mətn sətirləri massivi
     * @param array $metadata Chunk başına əlavə metadata
     */
    public function indexChunks(
        int $documentId,
        array $chunks,
        array $metadata = [],
    ): void {
        // Bütün embeddingləri tək toplu API sorğusunda yarat
        $embeddings = $this->embeddingService->embedBatch($chunks);

        foreach ($chunks as $index => $chunkText) {
            $chunk = DocumentChunk::create([
                'document_id' => $documentId,
                'content' => $chunkText,
                'model' => 'text-embedding-3-small',
                'chunk_index' => $index,
                'metadata' => $metadata[$index] ?? [],
            ]);

            $chunk->setEmbedding($embeddings[$index]);
        }
    }
}
```

### 6. pgvector Operator Arayışı

```sql
-- Məsafə operatorları (aşağı = daha oxşar)
embedding <=> query_vector   -- Cosine məsafəsi: 1 - cosine_similarity
embedding <#> query_vector   -- Mənfi daxili hasıl (oxşarlıq üçün mənfilə)
embedding <-> query_vector   -- L2 (Evklid) məsafəsi

-- Oxşarlıq ballarına çevir
1 - (embedding <=> query_vector)   -- Cosine similarity [0, 1]
(embedding <#> query_vector) * -1  -- Daxili hasıl oxşarlığı

-- İndeks növləri
CREATE INDEX ON items USING hnsw (embedding vector_cosine_ops);  -- <=> üçün
CREATE INDEX ON items USING hnsw (embedding vector_ip_ops);      -- <#> üçün
CREATE INDEX ON items USING hnsw (embedding vector_l2_ops);      -- <-> üçün
CREATE INDEX ON items USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);

-- HNSW parametrləri
-- m: node başına bağlantılar (standart 16, yüksək = daha yaxşı recall, daha çox yaddaş)
-- ef_construction: qurma dəqiqliyi (standart 64, yüksək = daha yaxşı, daha yavaş qurma)
-- ef_search: sorğu dəqiqliyi (SET hnsw.ef_search = 100;)
```

---

## Memarlar üçün Mülahizələr

### Embedding Modeli Seçmə Strategiyası

1. **OpenAI text-embedding-3-small ilə başla**: Ən az sürtünmə, yaxşı keyfiyyət, ucuz
2. **Retrieval keyfiyyəti bottleneck olduqda Cohere embed-v3-ə keç** — asimmetrik sorğu/sənəd embeddingləri mənalı bir yaxşılaşmadır
3. **Öz-hostinqi (nomic/BGE) nəzərdən keçir** aşağıdakı hallarda: məlumat həssaslığı, miqyasda xərc, ya da aşağı-gecikmə tələbləri
4. **Heç vaxt modellər qarışdırılmamalıdır**: Fərqli modellərin embeddingləri müqayisə edilə bilməz — keçid zamanı indeksin yenidən qurulması tələb olunur

### Matryoshka vasitəsilə Ölçü Azaltma

OpenAI-nın text-embedding-3 modelləri **Matryoshka Representation Learning (MRL)** istifadə edir: 3072 ölçülü vektorun ilk N ölçüsü özlüyündə etibarlı (aşağı keyfiyyətli) bir embeddingdir. Bu o deməkdir ki:

- ~5% keyfiyyət itkisi ilə 1536 → 512 ölçüyə endirmək mümkündür, amma 3x yaddaş qənaəti əldə edilir
- Faydalıdır: yaddaşın/saxlancanın baha olduğu yüksək miqyaslı sistemlər
- API-də `dimensions` parametri vasitəsilə mövcuddur

### İstehsal üçün İndeks Tənzimlənməsi

```sql
-- İndeks istifadəsini yoxla
EXPLAIN (ANALYZE, BUFFERS) 
SELECT id, 1 - (embedding <=> '[...]') as score
FROM document_chunks
ORDER BY embedding <=> '[...]'
LIMIT 10;

-- Sorğu zamanı HNSW ef_search-i tənzimlə (yüksək = daha yaxşı recall, yavaş)
SET hnsw.ef_search = 100;

-- IVFFlat üçün: probes = axtarılacaq klaster sayı
SET ivfflat.probes = 10;

-- İndeks ölçüsünü izlə
SELECT pg_size_pretty(pg_relation_size('document_chunks_embedding_hnsw_idx'));
```

### Cosine Similarity-nin Yanıltdığı Hallar

Cosine similarity **istiqaməti** ölçür, uyğunluğu deyil. Uğursuzluq halları:

1. **Sahə uyğunsuzluğu**: Ümumi veb mətnlərindən əyidilmiş embeddinglər domene-spesifik semantikanı (tibbi, hüquqi, kod) tuta bilməyə bilər
2. **Qısa sorğu problemi**: Qısa sorğuların yüksək variasiyası var — "Python" və "Python proqramlaşdırma dili" əhəmiyyətli dərəcədə fərqli ola bilər
3. **İnkar körliyi**: "X xüsusiyyətini dəstəkləmir" və "X xüsusiyyətini dəstəkləyir" oxşar embeddinglərə malikdir — incəlikli hallarda reranking istifadə et
4. **Hubness**: Çox yüksək ölçülərdə bəzi vektorlar "hub" olur — başqalarının çoxuna yaxın. Cross-encoder-lərlə aradan qaldırıla bilər

### Saxlama Təxminləri

| Model | Ölçülər | Vektor başına bayt | 1M sənəd |
|-------|-----------|------------------|--------------|
| MiniLM | 384 | 1.5 KB | ~1.5 GB |
| nomic-embed | 768 | 3 KB | ~3 GB |
| text-embedding-3-small | 1536 | 6 KB | ~6 GB |
| text-embedding-3-large | 3072 | 12 KB | ~12 GB |

HNSW indeks yükü: xam vektor saxlanmasının təxminən 1.5-2 misli.

### Sürət Limiti və Xərclər İdarəetməsi

```php
// config/services.php
'openai' => [
    'key' => env('OPENAI_API_KEY'),
    'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
    // Sürət limiti: tier 1-də 3000 RPM, 5M TPM
    'embedding_rpm' => env('OPENAI_EMBEDDING_RPM', 3000),
],
```

Sürət limitləri içində qalmaq və veb sorğularını bloklamaqdan qaçmaq üçün toplu indeksləmədə növbəyə alınmış işlər istifadə et.
