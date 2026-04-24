# Laravel-də Tam RAG Tətbiqi Qurulması

"Sənədlərinizlə söhbət edin" — hibrid axtarış, istinad izləmə və təmiz UI ilə istehsal səviyyəli Retrieval-Augmented Generation sistemi.

---

## Arxitektura Baxışı

```
Sənəd Yükləmə
      │
      ▼
DocumentProcessingPipeline (Tapşırıq Zənciri)
  ├── ExtractTextJob      (PDF→mətn, DOCX→mətn, TXT keçid)
  ├── ChunkDocumentJob    (rekursiv simvol bölünməsi)
  └── EmbedChunksJob      (OpenAI/Claude vasitəsilə toplu embedding)
             │
             ▼ pgvector + tsvector-də saxlanılır
          PostgreSQL
             │
      ┌──────┴──────┐
      │             │
   BM25          Vector
   Axtarış       Axtarış
      │             │
      └──────┬──────┘
           Yenidən Sıralama
             │
             ▼
    RAG Sorğu Kanalı
      ├── Metadata ilə əldə edilmiş parçalar
      ├── İstinad qurucusu
      └── [1][2] istinadları ilə Claude cavabı
```

**Niyə hibrid axtarış?** Saf vektorial axtarış dəqiq açar söz uyğunluqlarını (məhsul kodları, adlar, hüquqi terminlər) əldən verir. Saf BM25 semantik oxşarlığı əldən verir. RRF (Qarşılıqlı Sıra Birləşməsi) ilə hər ikisini birləşdirmək əksər meyarlar üzrə hər ikisini 15-30% geridə buraxır.

---

## Verilənlər Bazası Miqrasiyaları

```php
// database/migrations/2024_01_01_000001_create_rag_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector genişlənməsini aktivləşdir
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm'); // Daha yaxşı mətn axtarışı üçün

        Schema::create('document_collections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('embedding_model')->default('text-embedding-3-small');
            $table->unsignedSmallInteger('embedding_dimensions')->default(1536);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('collection_id')->constrained('document_collections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('page_count')->nullable();
            $table->string('status')->default('pending'); // pending, processing, ready, failed
            $table->string('error_message')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->json('metadata')->nullable(); // Çıxarılmış metadata (müəllif, yaradılma tarixi, və s.)
            $table->timestamps();

            $table->index(['collection_id', 'status']);
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('document_collections')->cascadeOnDelete();
            $table->unsignedSmallInteger('chunk_index'); // Sənəd daxilindəki mövqe
            $table->text('content');
            $table->unsignedInteger('token_count');
            $table->unsignedInteger('page_number')->nullable();
            $table->json('metadata')->nullable(); // başlıq, bölmə, və s.
            $table->timestamps();

            $table->index(['collection_id', 'document_id']);
        });

        // Vektor sütununu ayrıca əlavə et (pgvector sintaksisi)
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1536)');

        // BM25-stil tam mətn axtarışı üçün tsvector sütunu əlavə et
        DB::statement("ALTER TABLE document_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', content)) STORED");

        // Vektor indeksi yarat (HNSW sorğular üçün daha sürətlidir, IVFFlat qurmaq üçün daha sürətlidir)
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');

        // Tam mətn axtarışı indeksi yarat
        DB::statement('CREATE INDEX document_chunks_search_idx ON document_chunks USING gin (search_vector)');

        // Analitika üçün RAG sorğu tarixçəsi
        Schema::create('rag_queries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('document_collections')->cascadeOnDelete();
            $table->text('query');
            $table->text('answer')->nullable();
            $table->json('cited_chunk_ids')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->float('retrieval_time_ms')->nullable();
            $table->float('generation_time_ms')->nullable();
            $table->tinyInteger('user_rating')->nullable(); // 1-5 bəyəndim/bəyənmədim
            $table->timestamps();
        });
    }
};
```

---

## Modellər

```php
// app/Models/DocumentCollection.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentCollection extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'tenant_id', 'name', 'description',
        'embedding_model', 'embedding_dimensions', 'settings',
    ];

    protected $casts = ['settings' => 'array'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->ulid ??= Str::ulid());
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Document::class, 'collection_id');
    }

    public function chunks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentChunk::class, 'collection_id');
    }

    public function getRouteKeyName(): string { return 'ulid'; }
}
```

```php
// app/Models/Document.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Document extends Model
{
    protected $fillable = [
        'ulid', 'collection_id', 'user_id', 'name', 'file_path',
        'mime_type', 'file_size', 'page_count', 'status', 'error_message',
        'chunk_count', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->ulid ??= Str::ulid());
    }

    public function collection(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentCollection::class, 'collection_id');
    }

    public function chunks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function isReady(): bool { return $this->status === 'ready'; }
    public function isFailed(): bool { return $this->status === 'failed'; }
    public function getRouteKeyName(): string { return 'ulid'; }
}
```

```php
// app/Models/DocumentChunk.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id', 'collection_id', 'chunk_index', 'content',
        'token_count', 'page_number', 'metadata', 'embedding',
    ];

    protected $casts = ['metadata' => 'array'];

    public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // Embedding-i saxlama üçün formatla: "[0.1,0.2,...]"
    public function setEmbeddingAttribute(array $vector): void
    {
        $this->attributes['embedding'] = '[' . implode(',', $vector) . ']';
    }
}
```

---

## Mətn Çıxarma Xidməti

```php
// app/Services/Rag/TextExtractor.php
<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class TextExtractor
{
    /**
     * Saxlanılmış fayldan mətn çıxar.
     * ['text' => string, 'pages' => int, 'metadata' => array] qaytarır
     */
    public function extract(string $filePath, string $mimeType): array
    {
        $localPath = Storage::path($filePath);

        return match (true) {
            $mimeType === 'application/pdf' => $this->extractPdf($localPath),
            in_array($mimeType, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']) => $this->extractDocx($localPath),
            str_starts_with($mimeType, 'text/') => $this->extractText($localPath),
            default => throw new \InvalidArgumentException("Dəstəklənməyən növ: {$mimeType}"),
        };
    }

    private function extractPdf(string $path): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);
        $pages = $pdf->getPages();

        $textByPage = [];
        foreach ($pages as $i => $page) {
            $textByPage[$i + 1] = $page->getText();
        }

        $details = $pdf->getDetails();

        return [
            'text' => implode("\n\n", $textByPage),
            'text_by_page' => $textByPage,
            'pages' => count($pages),
            'metadata' => [
                'author' => $details['Author'] ?? null,
                'title' => $details['Title'] ?? null,
                'created' => $details['CreationDate'] ?? null,
            ],
        ];
    }

    private function extractDocx(string $path): array
    {
        // DOCX-i analiz etmək üçün PhpWord istifadə et
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
        $text = '';
        $paragraphCount = 0;

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $lineText = '';
                    foreach ($element->getElements() as $child) {
                        if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                            $lineText .= $child->getText();
                        }
                    }
                    $text .= $lineText . "\n";
                    $paragraphCount++;
                }
            }
        }

        return [
            'text' => $text,
            'text_by_page' => null,
            'pages' => null,
            'metadata' => ['paragraphs' => $paragraphCount],
        ];
    }

    private function extractText(string $path): array
    {
        $text = file_get_contents($path);
        return [
            'text' => $text,
            'text_by_page' => null,
            'pages' => null,
            'metadata' => [],
        ];
    }
}
```

---

## Sənəd Parçalayıcı

```php
// app/Services/Rag/DocumentChunker.php
<?php

namespace App\Services\Rag;

/**
 * Rekursiv simvol mətn bölücüsü.
 *
 * Strategiya:
 * 1. Əvvəlcə abzas boşluqlarında böl (\n\n)
 * 2. Parçalar hələ çox böyükdürsə, tək yeni sətirlərdə böl
 * 3. Hələ çox böyükdürsə, cümlələrdə böl
 * 4. Sınırlar boyunca konteksti qorumaq üçün parçalar arasında üst-üstə düşmə əlavə et
 */
class DocumentChunker
{
    private int $chunkSize;
    private int $chunkOverlap;

    // İngilis mətni üçün tokenlər/simvol təxmini
    private const CHARS_PER_TOKEN = 4;

    public function __construct(
        int $chunkTokens = 512,    // Hər parça üçün hədəf ~512 token
        int $overlapTokens = 64,   // Parçalar arasında 64 token üst-üstə düşmə
    ) {
        $this->chunkSize = $chunkTokens * self::CHARS_PER_TOKEN;
        $this->chunkOverlap = $overlapTokens * self::CHARS_PER_TOKEN;
    }

    /**
     * Mətni metadata ilə parçalara böl.
     *
     * @return array<array{content: string, chunk_index: int, token_count: int, page_number: ?int, metadata: array}>
     */
    public function chunk(string $text, ?array $textByPage = null): array
    {
        // Səhifə başına mətn varsa səhifə xəritəsi qur
        $pageMap = $textByPage ? $this->buildPageMap($textByPage) : null;

        // Xam parçalara böl
        $rawChunks = $this->splitRecursively($text, ["\n\n", "\n", ". ", " ", ""]);

        $chunks = [];
        $position = 0; // Səhifə xəritələməsi üçün orijinal mətndəki mövqeyi izlə

        foreach ($rawChunks as $index => $chunkText) {
            $chunkText = trim($chunkText);
            if (empty($chunkText)) continue;

            $tokenCount = (int) ceil(mb_strlen($chunkText) / self::CHARS_PER_TOKEN);

            // Parçanı orijinal mətndə tapıb səhifə nömrəsini müəyyən et
            $pageNumber = null;
            if ($pageMap) {
                $offset = mb_strpos($text, $chunkText, $position);
                if ($offset !== false) {
                    $pageNumber = $this->getPageForOffset($pageMap, $offset);
                    $position = $offset;
                }
            }

            $chunks[] = [
                'content' => $chunkText,
                'chunk_index' => count($chunks),
                'token_count' => $tokenCount,
                'page_number' => $pageNumber,
                'metadata' => [],
            ];
        }

        return $chunks;
    }

    private function splitRecursively(string $text, array $separators): array
    {
        if (mb_strlen($text) <= $this->chunkSize) {
            return [$text];
        }

        if (empty($separators)) {
            // Simvol səviyyəsində zorla böl
            $parts = [];
            $len = mb_strlen($text);
            for ($i = 0; $i < $len; $i += $this->chunkSize - $this->chunkOverlap) {
                $parts[] = mb_substr($text, $i, $this->chunkSize);
            }
            return $parts;
        }

        $separator = array_shift($separators);
        $splits = $separator ? explode($separator, $text) : mb_str_split($text, 1);

        $chunks = [];
        $currentChunk = '';

        foreach ($splits as $split) {
            $candidate = $currentChunk ? $currentChunk . $separator . $split : $split;

            if (mb_strlen($candidate) <= $this->chunkSize) {
                $currentChunk = $candidate;
            } else {
                if ($currentChunk) {
                    // Hələ çox böyükdürsə rekursiv böl
                    if (mb_strlen($currentChunk) > $this->chunkSize) {
                        $subChunks = $this->splitRecursively($currentChunk, $separators);
                        $chunks = array_merge($chunks, $subChunks);
                    } else {
                        $chunks[] = $currentChunk;
                    }

                    // Üst-üstə düşmə ilə yeni parça başlat
                    $overlap = mb_substr($currentChunk, -$this->chunkOverlap);
                    $currentChunk = $overlap . ($overlap ? $separator : '') . $split;
                } else {
                    $currentChunk = $split;
                }
            }
        }

        if ($currentChunk) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    private function buildPageMap(array $textByPage): array
    {
        $map = [];
        $offset = 0;
        foreach ($textByPage as $page => $pageText) {
            $map[] = ['page' => $page, 'start' => $offset, 'end' => $offset + mb_strlen($pageText)];
            $offset += mb_strlen($pageText) + 2; // \n\n ayırıcısı üçün +2
        }
        return $map;
    }

    private function getPageForOffset(array $pageMap, int $offset): ?int
    {
        foreach ($pageMap as $entry) {
            if ($offset >= $entry['start'] && $offset < $entry['end']) {
                return $entry['page'];
            }
        }
        return null;
    }
}
```

---

## Embedding Xidməti

```php
// app/Services/Rag/EmbeddingService.php
<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class EmbeddingService
{
    // OpenAI text-embedding-3-small: 1536 ölçü, $0.02/1M token
    // OpenAI text-embedding-3-large: 3072 ölçü, $0.13/1M token
    private string $model;
    private int $batchSize = 100; // OpenAI sorğu başına 2048 girişə qədər icazə verir

    public function __construct(
        string $model = 'text-embedding-3-small',
    ) {
        $this->model = $model;
    }

    /**
     * Tək mətni embed et. Lazımsız API çağırışlarının qarşısını almaq üçün 24 saat keşlənir.
     */
    public function embed(string $text): array
    {
        $cacheKey = 'embed:' . md5($this->model . $text);

        return Cache::remember($cacheKey, 86400, function () use ($text) {
            return $this->embedBatch([$text])[0];
        });
    }

    /**
     * Bir neçə mətni toplu şəkildə səmərəli embed et.
     *
     * @param string[] $texts
     * @return float[][] Embedding vektorları massivi
     */
    public function embedBatch(array $texts): array
    {
        $results = [];
        $batches = array_chunk($texts, $this->batchSize);

        foreach ($batches as $batch) {
            $response = Http::withToken(config('services.openai.api_key'))
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $batch,
                    'encoding_format' => 'float',
                ])
                ->throw()
                ->json();

            // Sıranı qorumaq üçün indeksə görə sırala (OpenAI sıradan çıxa bilər)
            $data = collect($response['data'])->sortBy('index');
            foreach ($data as $item) {
                $results[] = $item['embedding'];
            }
        }

        return $results;
    }
}
```

---

## Emal Tapşırıqları

```php
// app/Jobs/ProcessDocument.php
<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Rag\TextExtractor;
use App\Services\Rag\DocumentChunker;
use App\Services\Rag\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;  // Maksimum 5 dəqiqə
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private readonly int $documentId) {}

    public function handle(
        TextExtractor $extractor,
        DocumentChunker $chunker,
        EmbeddingService $embedder,
    ): void {
        $document = Document::find($this->documentId);
        if (!$document) return;

        $document->update(['status' => 'processing']);

        try {
            // Addım 1: Mətn çıxar
            Log::info("{$document->id} sənədindən mətn çıxarılır");
            $extracted = $extractor->extract($document->file_path, $document->mime_type);

            $document->update([
                'page_count' => $extracted['pages'],
                'metadata' => array_merge($document->metadata ?? [], $extracted['metadata']),
            ]);

            // Addım 2: Mətni parçala
            Log::info("{$document->id} sənədi parçalanır");
            $chunks = $chunker->chunk($extracted['text'], $extracted['text_by_page']);

            if (empty($chunks)) {
                throw new \RuntimeException('Heç bir parça yaradılmadı — sənəd boş və ya oxunmaz ola bilər');
            }

            // Addım 3: Bütün parçaları toplu embed et
            Log::info("{$document->id} sənədi üçün " . count($chunks) . " parça embed edilir");
            $texts = array_column($chunks, 'content');
            $embeddings = $embedder->embedBatch($texts);

            // Addım 4: Parçaları + embedding-ləri atomik olaraq saxla
            DB::transaction(function () use ($document, $chunks, $embeddings) {
                // Mövcud parçaları sil (yenidən emal zamanı)
                $document->chunks()->delete();

                // Parçaları toplu daxil et (əvvəlcə embedding-siz)
                $chunkRecords = [];
                foreach ($chunks as $i => $chunk) {
                    $chunkRecords[] = [
                        'document_id' => $document->id,
                        'collection_id' => $document->collection_id,
                        'chunk_index' => $chunk['chunk_index'],
                        'content' => $chunk['content'],
                        'token_count' => $chunk['token_count'],
                        'page_number' => $chunk['page_number'],
                        'metadata' => json_encode($chunk['metadata']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Yaddaş problemlərinin qarşısını almaq üçün toplu daxil et
                foreach (array_chunk($chunkRecords, 50) as $batch) {
                    \App\Models\DocumentChunk::insert($batch);
                }

                // İndi hər parçanın embedding-ini yenilə
                // pgvector INSERT vektor literalına ehtiyac duyduğu üçün bunu bir-bir etməliyik
                $chunkIds = $document->chunks()
                    ->orderBy('chunk_index')
                    ->pluck('id')
                    ->toArray();

                foreach ($chunkIds as $i => $chunkId) {
                    if (!isset($embeddings[$i])) continue;
                    $vector = '[' . implode(',', $embeddings[$i]) . ']';
                    DB::statement(
                        'UPDATE document_chunks SET embedding = ? WHERE id = ?',
                        [$vector, $chunkId]
                    );
                }

                $document->update([
                    'status' => 'ready',
                    'chunk_count' => count($chunks),
                ]);
            });

            Log::info("{$document->id} sənədi uğurla emal edildi");

        } catch (\Exception $e) {
            Log::error("{$document->id} sənədinin emalı uğursuz oldu", ['error' => $e->getMessage()]);
            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

---

## Hibrid Axtarış Xidməti

```php
// app/Services/Rag/HybridSearchService.php
<?php

namespace App\Services\Rag;

use App\Models\DocumentCollection;
use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HybridSearchService
{
    private const VECTOR_WEIGHT = 0.7;  // Vektorial oxşarlığa nə qədər ağırlıq verilir
    private const BM25_WEIGHT = 0.3;    // Açar söz uyğunluğuna nə qədər ağırlıq verilir

    public function __construct(
        private readonly EmbeddingService $embedder,
    ) {}

    /**
     * Həm vektorial oxşarlıq, həm BM25 açar söz axtarışı istifadə edərək axtar,
     * sonra RRF ilə nəticələri birləşdir.
     *
     * @return Collection<DocumentChunk> 'rrf_score' əlavə edilmiş
     */
    public function search(
        DocumentCollection $collection,
        string $query,
        int $topK = 10,
        array $filters = [],
    ): Collection {
        $startTime = microtime(true);

        // Hər iki axtarışı "paralel" icra et
        $queryEmbedding = $this->embedder->embed($query);

        $vectorResults = $this->vectorSearch($collection, $queryEmbedding, $topK * 2, $filters);
        $bm25Results = $this->bm25Search($collection, $query, $topK * 2, $filters);

        // Qarşılıqlı Sıra Birləşməsi ilə birləşdir
        $combined = $this->reciprocalRankFusion($vectorResults, $bm25Results, $topK);

        // Monitorinq üçün axtarış vaxtını qeyd et
        $elapsed = (microtime(true) - $startTime) * 1000;
        \Log::debug("Hibrid axtarış {$elapsed}ms çəkdi, {$combined->count()} nəticə qaytardı");

        return $combined;
    }

    /**
     * Kosinus məsafəsi istifadə edərək saf vektorial oxşarlıq axtarışı.
     */
    private function vectorSearch(
        DocumentCollection $collection,
        array $queryEmbedding,
        int $limit,
        array $filters = [],
    ): Collection {
        $vectorStr = '[' . implode(',', $queryEmbedding) . ']';

        $query = DB::table('document_chunks')
            ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
            ->where('document_chunks.collection_id', $collection->id)
            ->where('documents.status', 'ready')
            ->whereNotNull('document_chunks.embedding')
            ->selectRaw("
                document_chunks.*,
                documents.name as document_name,
                1 - (document_chunks.embedding <=> ?) as vector_score
            ", [$vectorStr]);

        // İsteğe bağlı filterlər tətbiq et (məs. sənəd ID-si ilə filter)
        if (!empty($filters['document_ids'])) {
            $query->whereIn('document_chunks.document_id', $filters['document_ids']);
        }

        return $query
            ->orderByRaw("document_chunks.embedding <=> ?", [$vectorStr])
            ->limit($limit)
            ->get();
    }

    /**
     * PostgreSQL-in ts_rank_cd istifadə edərək BM25-stil tam mətn axtarışı.
     * ts_rank_cd, örtüm sıxlığını nəzərə aldığı üçün ts_rank-dan BM25-ə daha yaxındır.
     */
    private function bm25Search(
        DocumentCollection $collection,
        string $query,
        int $limit,
        array $filters = [],
    ): Collection {
        // Sorğunu tsquery formatına çevir
        $terms = collect(preg_split('/\s+/', trim($query)))
            ->filter(fn($t) => strlen($t) > 2)
            ->map(fn($t) => preg_replace('/[^a-zA-Z0-9]/', '', $t))
            ->filter()
            ->implode(' & ');

        if (empty($terms)) {
            return collect();
        }

        $dbQuery = DB::table('document_chunks')
            ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
            ->where('document_chunks.collection_id', $collection->id)
            ->where('documents.status', 'ready')
            ->selectRaw("
                document_chunks.*,
                documents.name as document_name,
                ts_rank_cd(document_chunks.search_vector, to_tsquery('english', ?)) as bm25_score
            ", [$terms])
            ->whereRaw("document_chunks.search_vector @@ to_tsquery('english', ?)", [$terms]);

        if (!empty($filters['document_ids'])) {
            $dbQuery->whereIn('document_chunks.document_id', $filters['document_ids']);
        }

        return $dbQuery
            ->orderByDesc('bm25_score')
            ->limit($limit)
            ->get();
    }

    /**
     * Qarşılıqlı Sıra Birləşməsi: hər nəticə siyahısı üçün xal = cəm(1 / (k + sıra)).
     * k=60 orijinal RRF məqaləsinin standart tövsiyəsidir.
     */
    private function reciprocalRankFusion(
        Collection $vectorResults,
        Collection $bm25Results,
        int $topK,
        int $k = 60,
    ): Collection {
        $scores = [];

        // Vektorial nəticələri xallandır
        foreach ($vectorResults as $rank => $chunk) {
            $id = $chunk->id;
            $scores[$id] = ($scores[$id] ?? 0) + (self::VECTOR_WEIGHT / ($k + $rank + 1));
            $scores[$id . ':chunk'] = $chunk;
        }

        // BM25 nəticələrini xallandır
        foreach ($bm25Results as $rank => $chunk) {
            $id = $chunk->id;
            $scores[$id] = ($scores[$id] ?? 0) + (self::BM25_WEIGHT / ($k + $rank + 1));
            if (!isset($scores[$id . ':chunk'])) {
                $scores[$id . ':chunk'] = $chunk;
            }
        }

        // RRF xalına görə sırala
        arsort($scores);

        // Son nəticələri qur
        $results = [];
        $seen = [];
        foreach ($scores as $key => $score) {
            if (str_ends_with($key, ':chunk')) continue;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $chunk = $scores[$key . ':chunk'];
            $chunk->rrf_score = $score;
            $results[] = $chunk;

            if (count($results) >= $topK) break;
        }

        return collect($results);
    }
}
```

---

## RAG Sorğu Kanalı

```php
// app/Services/Rag/RagQueryService.php
<?php

namespace App\Services\Rag;

use App\Models\DocumentCollection;
use App\Models\RagQuery;
use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Support\Collection;

class RagQueryService
{
    // Əldə edilmiş kontekst üçün istifadə ediləcək maksimum tokenlər
    private const MAX_CONTEXT_TOKENS = 80_000;
    private const CHARS_PER_TOKEN = 4;

    public function __construct(
        private readonly HybridSearchService $searchService,
    ) {}

    /**
     * Əldə edilmiş sənəd konteksti istifadə edərək sorğuya cavab ver.
     * Cavabı axın edir və istinadları izləyir.
     */
    public function query(
        DocumentCollection $collection,
        string $userQuery,
        int $userId,
    ): \Generator {
        $retrievalStart = microtime(true);

        // 1. Uyğun parçaları əldə et
        $chunks = $this->searchService->search($collection, $userQuery, topK: 8);

        $retrievalMs = (microtime(true) - $retrievalStart) * 1000;

        if ($chunks->isEmpty()) {
            yield ['type' => 'error', 'message' => 'Kolleksiyada uyğun məzmun tapılmadı.'];
            return;
        }

        // 2. İstinad işarəli kontekst qur
        [$contextText, $citations] = $this->buildContext($chunks);

        // 3. UI-nin onları göstərə bilməsi üçün istinadları ver
        yield ['type' => 'citations', 'citations' => $citations];

        // 4. Prompt qur
        $systemPrompt = <<<PROMPT
        Sən sənəd köməkçisisən. Sualları YALNIZ təqdim edilən sənəd parçalarına əsasən cavablandır.
        
        Qaydalar:
        - Yalnız təqdim edilən parçalardakı məlumatlardan istifadə et
        - Hər iddiadan sonra [1], [2] və s. ilə istinadlarını göstər
        - Cavab parçalarda yoxdursa, "Təqdim edilən sənədlərdə bu barədə məlumatım yoxdur" de
        - Qısa və birbaşa ol
        - Uyğun olduqda markdown formatlamasından istifadə et
        PROMPT;

        $userPrompt = "Sənəd parçaları:\n\n{$contextText}\n\n---\n\nSual: {$userQuery}";

        // 5. Cavabı axın et
        $generationStart = microtime(true);
        $fullAnswer = '';
        $inputTokens = 0;
        $outputTokens = 0;

        $stream = Anthropic::messages()->createStreamed([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userPrompt]],
        ]);

        foreach ($stream as $event) {
            if ($event->type === 'content_block_delta' && $event->delta->type === 'text_delta') {
                $text = $event->delta->text;
                $fullAnswer .= $text;
                yield ['type' => 'text', 'text' => $text];
            }
            if ($event->type === 'message_start') {
                $inputTokens = $event->message->usage->inputTokens ?? 0;
            }
            if ($event->type === 'message_delta') {
                $outputTokens = $event->usage->outputTokens ?? 0;
            }
        }

        $generationMs = (microtime(true) - $generationStart) * 1000;

        // 6. Cavabda hansı istinadların həqiqətən istifadə edildiyini müəyyən et
        $usedCitationNumbers = [];
        preg_match_all('/\[(\d+)\]/', $fullAnswer, $matches);
        $usedCitationNumbers = array_unique(array_map('intval', $matches[1] ?? []));

        // 7. Sorğunu tarixçəyə saxla
        RagQuery::create([
            'user_id' => $userId,
            'collection_id' => $collection->id,
            'query' => $userQuery,
            'answer' => $fullAnswer,
            'cited_chunk_ids' => $chunks->pluck('id')->toArray(),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'retrieval_time_ms' => $retrievalMs,
            'generation_time_ms' => $generationMs,
        ]);

        yield ['type' => 'done', 'used_citations' => $usedCitationNumbers];
    }

    /**
     * Nömrəli istinad işarəli kontekst sətri qur.
     * Token büdcəsinə sığmaq üçün kəsir.
     *
     * @return array{0: string, 1: array}
     */
    private function buildContext(Collection $chunks): array
    {
        $contextParts = [];
        $citations = [];
        $totalChars = 0;
        $maxChars = self::MAX_CONTEXT_TOKENS * self::CHARS_PER_TOKEN;

        foreach ($chunks as $index => $chunk) {
            $citationNumber = $index + 1;
            $pageInfo = $chunk->page_number ? " (səhifə {$chunk->page_number})" : '';
            $header = "[{$citationNumber}] Mənbə: {$chunk->document_name}{$pageInfo}";
            $excerpt = "```\n{$chunk->content}\n```";
            $block = "{$header}\n{$excerpt}";

            if ($totalChars + strlen($block) > $maxChars) {
                break; // Kontekst büdcəsi tükəndi
            }

            $contextParts[] = $block;
            $citations[] = [
                'number' => $citationNumber,
                'document_name' => $chunk->document_name,
                'chunk_id' => $chunk->id,
                'page_number' => $chunk->page_number,
                'score' => $chunk->rrf_score ?? 0,
                'excerpt' => mb_substr($chunk->content, 0, 200) . '...',
            ];

            $totalChars += strlen($block);
        }

        return [implode("\n\n---\n\n", $contextParts), $citations];
    }
}
```

---

## Kontroller

```php
// app/Http/Controllers/RagController.php
<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentCollection;
use App\Services\Rag\RagQueryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RagController extends Controller
{
    public function __construct(
        private readonly RagQueryService $ragService,
    ) {}

    public function index()
    {
        $collections = auth()->user()->documentCollections()
            ->withCount(['documents', 'documents as ready_documents_count' => function ($q) {
                $q->where('status', 'ready');
            }])
            ->latest()
            ->get();

        return view('rag.index', compact('collections'));
    }

    public function createCollection(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $collection = auth()->user()->documentCollections()->create($validated);

        return redirect()->route('rag.collection', $collection);
    }

    public function upload(Request $request, DocumentCollection $collection)
    {
        $this->authorize('update', $collection);

        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['required', 'file', 'max:51200', 'mimes:pdf,docx,txt,md'], // Maksimum 50MB
        ]);

        $documents = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store("rag-documents/{$collection->id}", 'private');

            $document = $collection->documents()->create([
                'user_id' => auth()->id(),
                'name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending',
            ]);

            // Emal tapşırığını növbəyə al
            ProcessDocument::dispatch($document->id)
                ->onQueue('document-processing');

            $documents[] = $document;
        }

        return response()->json([
            'message' => count($documents) . ' sənəd emal üçün növbəyə alındı',
            'documents' => collect($documents)->map(fn($d) => [
                'id' => $d->ulid,
                'name' => $d->name,
                'status' => $d->status,
            ]),
        ]);
    }

    public function query(Request $request, DocumentCollection $collection): StreamedResponse
    {
        $this->authorize('view', $collection);

        $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        // Kolleksiyada ən azı bir hazır sənəd olduğunu yoxla
        if (!$collection->documents()->where('status', 'ready')->exists()) {
            return response()->json(['error' => 'Hələ hazır sənəd yoxdur.'], 422);
        }

        return response()->stream(function () use ($request, $collection) {
            $generator = $this->ragService->query(
                $collection,
                $request->input('query'),
                auth()->id(),
            );

            foreach ($generator as $chunk) {
                echo 'data: ' . json_encode($chunk) . "\n\n";
                ob_flush();
                flush();
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

---

## Livewire Komponenti

```php
// app/Livewire/RagChat.php
<?php

namespace App\Livewire;

use App\Models\DocumentCollection;
use Livewire\Component;

class RagChat extends Component
{
    public DocumentCollection $collection;
    public string $query = '';
    public bool $isSearching = false;
    public string $streamingAnswer = '';
    public array $citations = [];
    public array $usedCitations = [];
    public array $history = [];
    public ?string $error = null;

    public function mount(DocumentCollection $collection): void
    {
        $this->collection = $collection;
    }

    public function search(): void
    {
        $this->validate(['query' => ['required', 'string', 'min:3', 'max:2000']]);

        $this->isSearching = true;
        $this->streamingAnswer = '';
        $this->citations = [];
        $this->error = null;

        $this->dispatch('start-rag-search', [
            'collectionId' => $this->collection->ulid,
            'query' => $this->query,
        ]);
    }

    public function setCitations(array $citations): void
    {
        $this->citations = $citations;
    }

    public function appendText(string $text): void
    {
        $this->streamingAnswer .= $text;
    }

    public function finishSearch(array $usedCitations): void
    {
        $this->isSearching = false;
        $this->usedCitations = $usedCitations;

        // Tarixçəyə əlavə et
        $this->history[] = [
            'query' => $this->query,
            'answer' => $this->streamingAnswer,
            'citations' => array_filter(
                $this->citations,
                fn($c) => in_array($c['number'], $usedCitations)
            ),
        ];

        $this->query = '';
        $this->streamingAnswer = '';
        $this->citations = [];
    }

    public function render()
    {
        return view('livewire.rag-chat');
    }
}
```

---

## Blade Görünüşü

```blade
{{-- resources/views/livewire/rag-chat.blade.php --}}
<div class="max-w-4xl mx-auto" x-data="ragStream()">

    {{-- Kolleksiya məlumatı --}}
    <div class="bg-white border rounded-xl p-4 mb-6">
        <h2 class="font-semibold text-gray-900">{{ $collection->name }}</h2>
        <p class="text-sm text-gray-500">
            {{ $collection->documents()->where('status', 'ready')->count() }} sənəd hazırdır
        </p>
    </div>

    {{-- Sorğu tarixçəsi --}}
    @foreach(array_reverse($history) as $item)
        <div class="mb-8">
            {{-- İstifadəçi sualı --}}
            <div class="flex justify-end mb-4">
                <div class="bg-blue-600 text-white rounded-xl rounded-tr-sm px-4 py-3 max-w-2xl">
                    {{ $item['query'] }}
                </div>
            </div>

            {{-- Cavab --}}
            <div class="bg-white border rounded-xl rounded-tl-sm px-5 py-4 shadow-sm max-w-3xl">
                <div class="prose prose-sm max-w-none mb-4">
                    {!! \Illuminate\Support\Str::markdown(e($item['answer'])) !!}
                </div>

                {{-- İstinadlar --}}
                @if(!empty($item['citations']))
                    <div class="border-t pt-3 mt-3">
                        <p class="text-xs font-medium text-gray-500 mb-2">Mənbələr</p>
                        <div class="space-y-2">
                            @foreach($item['citations'] as $citation)
                                <div class="bg-gray-50 rounded-lg px-3 py-2 text-xs">
                                    <span class="font-medium text-blue-700">[{{ $citation['number'] }}]</span>
                                    <span class="font-medium text-gray-700 ml-1">{{ $citation['document_name'] }}</span>
                                    @if($citation['page_number'])
                                        <span class="text-gray-400"> — səhifə {{ $citation['page_number'] }}</span>
                                    @endif
                                    <p class="text-gray-500 mt-1">{{ $citation['excerpt'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Axın cavabı --}}
    @if($isSearching)
        <div class="mb-8">
            {{-- İstinadlar cavabdan əvvəl görünür --}}
            @if(!empty($citations))
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach($citations as $citation)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-full px-3 py-1 text-xs text-yellow-800">
                            [{{ $citation['number'] }}] {{ $citation['document_name'] }}
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="bg-white border rounded-xl rounded-tl-sm px-5 py-4 shadow-sm max-w-3xl">
                <div class="prose prose-sm max-w-none"
                     x-text="$wire.streamingAnswer"
                     x-show="$wire.streamingAnswer">
                </div>
                <div x-show="!$wire.streamingAnswer" class="flex items-center gap-1">
                    <span class="text-sm text-gray-400">Sənədlər axtarılır...</span>
                    <svg class="w-4 h-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </div>
    @endif

    {{-- Xəta --}}
    @if($error)
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm mb-4">
            {{ $error }}
        </div>
    @endif

    {{-- Sorğu girişi --}}
    <div class="sticky bottom-4">
        <form wire:submit="search" class="bg-white border rounded-xl shadow-lg p-3 flex gap-3">
            <input
                wire:model="query"
                type="text"
                placeholder="Sənədləriniz haqqında sual verin..."
                :disabled="$wire.isSearching"
                class="flex-1 px-3 py-2 text-sm focus:outline-none"
                x-on:keydown.enter.prevent="$wire.search()"
            />
            <button
                type="submit"
                :disabled="$wire.isSearching"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
            >
                Soruş
            </button>
        </form>
    </div>

    <script>
    function ragStream() {
        return {
            init() {
                this.$wire.on('start-rag-search', async (data) => {
                    const { collectionId, query } = data[0];
                    await this.stream(collectionId, query);
                });
            },

            async stream(collectionId, query) {
                const response = await fetch(`/rag/${collectionId}/query`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ query }),
                });

                if (!response.ok) {
                    const error = await response.json();
                    this.$wire.set('error', error.error || 'Axtarış uğursuz oldu');
                    this.$wire.set('isSearching', false);
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop();

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        const data = line.slice(6).trim();
                        if (data === '[DONE]') return;

                        try {
                            const chunk = JSON.parse(data);
                            if (chunk.type === 'citations') {
                                await this.$wire.setCitations(chunk.citations);
                            } else if (chunk.type === 'text') {
                                await this.$wire.appendText(chunk.text);
                            } else if (chunk.type === 'done') {
                                await this.$wire.finishSearch(chunk.used_citations);
                            } else if (chunk.type === 'error') {
                                this.$wire.set('error', chunk.message);
                                this.$wire.set('isSearching', false);
                            }
                        } catch (e) {}
                    }
                }
            },
        };
    }
    </script>
</div>
```

---

## Marşrutlar

```php
// routes/web.php
use App\Http\Controllers\RagController;

Route::middleware(['auth'])->prefix('rag')->name('rag.')->group(function () {
    Route::get('/', [RagController::class, 'index'])->name('index');
    Route::post('/collections', [RagController::class, 'createCollection'])->name('collections.store');
    Route::get('/collections/{collection}', [RagController::class, 'show'])->name('collection');
    Route::post('/collections/{collection}/upload', [RagController::class, 'upload'])->name('upload');
    Route::post('/collections/{collection}/query', [RagController::class, 'query'])->name('query');
});
```

---

## İstehsal Mülahizələri

### PostgreSQL pgvector Quraşdırması

```sql
-- Məlumat dəstinizin ölçüsünə görə HNSW indeks parametrlərini tənzimləyin
-- m=16, ef_construction=64 yaxşı standartdır
-- Daha yüksək m = daha yaxşı geri çağırma, daha çox yaddaş. Daha yüksək ef_construction = daha yaxşı geri çağırma, daha yavaş quruluş
CREATE INDEX document_chunks_embedding_idx ON document_chunks
USING hnsw (embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);

-- Axtarış üçün ef_search-i ef_construction-dan yüksək qur
SET hnsw.ef_search = 100;
```

### Yenidən Embedding Strategiyası

Embedding modellərini dəyişdirirsinizsə, bütün parçaları yenidən embed etməlisiniz:

```php
// php artisan rag:reembed --collection=<id>
Artisan::command('rag:reembed {collection}', function () {
    $collection = DocumentCollection::where('ulid', $this->argument('collection'))->firstOrFail();
    foreach ($collection->documents()->where('status', 'ready')->cursor() as $document) {
        $document->update(['status' => 'pending']);
        ProcessDocument::dispatch($document->id);
    }
});
```

### Parçalama Strategiyasının Tənzimlənməsi

Düzgün parça ölçüsü sənədlərinizə bağlıdır:
- **Texniki sənədlər / kod**: Kiçik parçalar (256-512 token) — spesifiklik önəmlidir
- **Hüquqi / anlatı sənədlər**: Böyük parçalar (512-1024 token) — kontekst önəmlidir
- **FAQ / Sual-cavab məzmunu**: Sual-cavab cütlüklərinə uyğunlaşdırın

### Əldə Etmə Keyfiyyətinin Monitorinqi

İstinadların istifadə edilib-edilmədiyini izləyin və geri bildirim döngüsü qurun:

```php
// İstifadəçi cavabı qiymətləndirdikdən sonra analiz üçün qeyd et
Route::post('/rag/queries/{query}/rate', function (RagQuery $query, Request $request) {
    $query->update(['user_rating' => $request->input('rating')]); // 1 (pis) - 5 (yaxşı)
});

// Həftəlik hesabat: reytinqi 3-dən aşağı olan sorğular əldə etmə problemlərini göstərir
```
