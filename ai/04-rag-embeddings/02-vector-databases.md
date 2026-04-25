# Vector Verilənlər Bazaları: Arxitektura və Seçim Bələdçisi (Middle)

## Niyə İxtisaslaşmış Vector Saxlama Önəm Kəsb Edir

Standart relasional verilənlər bazaları oxşarlıq axtarışı üçün nəzərdə tutulmayıb. Sadə bir `SELECT * FROM documents ORDER BY cosine_similarity(embedding, query)` sorğusu tam cədvəl taraması aparır — hər vektorda O(n) xətti axtarış. 1 milyon sənəddə bu sorğu başına 1M dot product deməkdir.

Vector verilənlər bazaları (və pgvector kimi genişlənmələr) bunu **Approximate Nearest Neighbor (ANN)** indeksləmə vasitəsilə həll edir, kiçik bir dəqiqliyi sürfəq verib dəfələrlə daha sürətli axtarış əldə edir.

### Vector Verilənlər Bazası Əslində Nə Edir

1. **İndeksləmə**: Xəttinin altı axtarışa imkan verən bir məlumat strukturunda vektorları təşkil edir
2. **ANN Axtarışı**: O(log n) və ya daha yaxşı zamanda təxmini k ən yaxın qonşuları qaytarır
3. **Filtrləmə**: Vector axtarışını metadata/atribut filtrləri ilə birləşdirir
4. **CRUD**: Metadatası olan vektorları yarat, oxu, yenilə, sil
5. **Miqyaslama**: Sharding, replikasiya, paylanmış axtarış

---

## İndeks Alqoritmləri Dərin Araşdırma

### HNSW (Hierarchical Navigable Small World)

Yüksək performanslı ANN axtarışı üçün dominant alqoritm. Çox qatlı qraf qurur:

- **Qat 0**: Bütün node-lar yaxın qonşularla bağlıdır
- **Yuxarı qatlar**: Uzun məsafəli keçid üçün tədricən seyrəkləşən "magistral" qatlar
- **Axtarış**: Yuxarı qatdan başla, sorğu vektoruna xəsis şəkildə naviqasiya et, qatları en

**Parametrlər**:
- `M` (node başına maksimum bağlantı): yüksək = daha yaxşı recall, daha çox yaddaş. Standart: 16
- `ef_construction` (qurma zamanı axtarış eni): yüksək = daha yaxşı qraf keyfiyyəti, daha yavaş qurma. Standart: 64
- `ef_search` (sorğu zamanı axtarış eni): runtime tənzimlənə bilər, yüksək = daha yaxşı recall, daha yavaş. Standart: 10-64

**Performans**: O(log n) axtarış, tənzimlənmiş parametrlərlə ~95-99% recall əldə edilə bilər.

**Yaddaş**: ~100 bayt × M × n vektor. M=16-da 1M vektor ≈ 1.6 GB yük.

### IVFFlat (Inverted File + Flat)

Vektor fəzasını `lists` klasterə bölür (k-means vasitəsilə). Axtarış yalnız `probes` ən yaxın kластерI tarayır.

**Parametrlər**:
- `lists`: bölmə sayı. Baş qayda: balanslaşdırılmış klasterlər üçün `sqrt(n)`
- `probes`: sorğu zamanı axtarılacaq klasterlər. Yüksək = daha yaxşı recall, daha yavaş

**Kompromis**: Kobud gücden 10-50x daha sürətli, amma HNSW-dan ~5-20% aşağı recall.
**Yaddaş**: HNSW-dan aşağı yaddaş. HNSW-nun praktik olmadığı çox böyük datasetlər üçün daha yaxşı.

**Qayda**: < 10M vektor və yüksək recall tələbləri üçün HNSW; 10M+ vektor üçün IVFFlat.

---

## Vector Verilənlər Bazası Müqayisəsi

### pgvector (PostgreSQL Genişlənməsi)

**Arxitektura**: Doğma PostgreSQL genişlənməsi. Vektorlar sütun kimi saxlanılır. HNSW və IVFFlat indeksləri. Standart SQL sorğuları.

**Üstünlüklər**:
- PostgreSQL artıq istifadə edilirsə sıfır infrastruktur yükü
- Tam ACID tranzaksiyaları — embeddinglər + metadata atomik şəkildə ardıcıl
- Relasional məlumatlarla doğruldan birləşdirmə (JOIN documents ON id = chunk.document_id)
- Bütün PostgreSQL xüsusiyyətləri: JSONB metadata, tam mətn axtarışı, bölmə
- Yetkin yedəkləmə, replikasiya və əməliyyat alətləri
- pgvector 0.7+ paralel HNSW indeks qurmağı dəstəkləyir

**Çatışmazlıqlar**:
- Standart olaraq tək maşın (oxuma replikaları miqyasda kömək edir, amma üfüqi yazma sharding yoxdur)
- Xalis vector iş yükləri üçün qurulmayıb — ümumi məqsədli yük
- Çox böyük datasetlər üçün indeks qurması yavaşdır (100M+ vektor)
- Daxili embedding yaratma yoxdur

**Performans (benchmarklar, 1M vektor, 1536 ölçü)**:
- HNSW: sorğu başına ~2-5ms, ~98% recall
- IVFFlat: sorğu başına ~1-3ms, ~90-95% recall
- Kobud güc: sorğu başına ~50-200ms

**Ən uyğundur**: Mövcud Laravel/PostgreSQL tətbiqlər. < 10M vektor. Güclü ardıcıllıq tələbləri. Büdcə məhdudiyyəti olan.

---

### Pinecone

**Arxitektura**: İdarə olunan bulud doğma vector verilənlər bazası. Xüsusi arxitektura. Serversiz və pod-əsaslı kataloqlar.

**Üstünlüklər**:
- Tam idarə olunan: infrastruktur yoxdur, avtomatik miqyaslama
- 100M+ vektorda ardıcıl 10ms-dən aşağı p99
- Daxili seyrek + sıx hibrid axtarış
- Çox-kiracılıq üçün ad fəzaları
- Sadə API

**Çatışmazlıqlar**:
- Miqyasda əhəmiyyətli xərc ($0.08/saat hər p1 pod üçün = minimal ayda ~$58; serverless oxuma/yazma vahidi başına ödənir)
- Son ardıcıllıq (yazma ilə axtarıla bilmə arasında kiçik gecikmə)
- SQL yoxdur — relasional məlumatlarla birləşdirilə bilmir
- Məlumatlar Pinecone-da kilidlənib (miqrasiya çətindir)
- Öz-hosting seçimi yoxdur

**Qiymətlər (2025)**:
- Serverless: 1M oxuma başına $0.04, 1M yazma başına $2, ~aylıq $0.33/GB saxlama
- Pod s1.x1: $0.0966/saat (~aylıq $70), 1536 ölçülü 1M vektor

**Ən uyğundur**: Yüksək miqyaslı istehsal sistemləri. Sıfır əməliyyat istəyən komandalar. Retrieval gecikmə biznes kritik olduqda.

---

### Qdrant

**Arxitektura**: Rust-da yazılmış məqsəd üçün qurulmuş vector verilənlər bazası. Açıq mənbə (Apache 2.0) + idarə olunan bulud. Payload (metadata) vektorlarla yanaşı saxlanılır.

**Üstünlüklər**:
- Əksər benchmarklarda ən sürətli sorğu performansı (Rust, optimallaşdırılmış HNSW)
- Zəngin filtrləmə: payload sahələrindəki mürəkkəb şərtlər, vector axtarışı ilə birləşdirilir
- Kvantizasiya: yaddaş azaltma üçün skalar, product, ikili kvantizasiya (4-32x)
- Kolleksiyalar + payload filtrləmə vasitəsilə çox-kiracılıq
- Öz-hostinq + idarə olunan bulud (Qdrant Cloud)
- gRPC və REST API-ləri
- Adlandırılmış vektorlar: nöqtə başına çoxlu embeddingləri saxla (sıx + seyrek)

**Çatışmazlıqlar**:
- SQL interfeysi yoxdur
- Öz-hostinqdə idarə ediləcək ayrı infrastruktur
- PHP/Laravel üçün pgvector-dan daha kiçik ekosistem

**Performans**: Ekvivalent recall-da pgvector-dan adətən 2-3x daha sürətli.

**Ən uyğundur**: Yüksək performans tələbləri. Öz-hosting üstünlüyü. Böyük miqyaslı yerləşdirmələr. pgvector çox yavaş olduqda.

---

### Weaviate

**Arxitektura**: Qrafa yönəlmiş vector verilənlər bazası. Zəngin sxema ilə saxlanılan obyektlər. Daxili vektorizatorlar (daxiletmədə avtomatik olaraq embedding API-lərini çağırır).

**Üstünlüklər**:
- GraphQL API ilə sxema-birinci dizayn
- Daxili vektorizator modulları (daxiletmədə avtomatik olaraq OpenAI/Cohere çağırır)
- Doğruldan hibrid axtarış (BM25 + vector)
- Çarpaz referanslar: obyektlər arasında qraf əlaqələri
- Çox-modal (mətn + şəkil embeddingləri)

**Çatışmazlıqlar**:
- Öz-hostinq mürəkkəbdir (ağır Java/Go yığını)
- GraphQL API öyrənmə əyrisi tələb edir
- Pinecone/Qdrant-dan daha az yetkin
- Daha yüksək resurs istifadəsi

**Ən uyğundur**: Bilik qrafı istifadə halları. Daxiletmədə avtomatik vektorizasiya tələb edən sistemlər. Çox-modal axtarış.

---

### Chroma

**Arxitektura**: Açıq mənbə, daxili yerləşdirilmiş və ya müştəri-server. Python/Go-da yazılmış. Sadəlik üçün nəzərdə tutulmuş.

**Üstünlüklər**:
- Başlamaq son dərəcə asandır (pip install, 3 sətir kod)
- Yaxşı Python ekosistemi inteqrasiyası
- SQLite/DuckDB arxa ucu ilə davamlı saxlama

**Çatışmazlıqlar**:
- Python-doğma (PHP müştərisi icmaya aiddir/qeyri-rəsmidir)
- Miqyasda istehsala hazır deyil (məhdud benchmarklar, korporativ dəstək yoxdur)
- Məhdud filtrləmə imkanları
- Performans Qdrant/Pinecone-dan geri qalır

**Ən uyğundur**: Prototipləmə. Python-doğma ML iş axınları. İstehsal Laravel tətbiqləri üçün heç vaxt.

---

### Müqayisə Matrisi

| Xüsusiyyət | pgvector | Pinecone | Qdrant | Weaviate | Chroma |
|---------|---------|---------|--------|---------|--------|
| Öz-hostinq | Bəli | Xeyr | Bəli | Bəli | Bəli |
| İdarə olunan bulud | Supabase vasitəsilə | Bəli | Bəli | Bəli | Xeyr |
| SQL/birləşmələr | Bəli | Xeyr | Xeyr | Xeyr | Xeyr |
| ACID tranzaksiyaları | Bəli | Xeyr | Qismən | Xeyr | Xeyr |
| Maksimum vektor (praktik) | ~50M | 100M+ | 100M+ | 50M+ | ~1M |
| Hibrid axtarış | Manual | Bəli | Bəli | Bəli | Xeyr |
| Çox-kiracılıq | Sxema/tenant_id | Ad fəzaları | Kolleksiyalar | Kiracılar | Kolleksiyalar |
| PHP müştərisi | Rəsmi (laravel-pg) | Rəsmi | Rəsmi | İcma | Xeyr |
| Qiymət | Pulsuz (infra xərci) | $$$ | Pulsuz/$ | Pulsuz/$ | Pulsuz |

---

## Laravel + pgvector: Tam Tətbiq

### HNSW İndeksi ilə Miqrasiya

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('embeddable_type'); // Polimorfik
            $table->unsignedBigInteger('embeddable_id');
            $table->string('model', 100)->default('text-embedding-3-small');
            $table->text('content'); // Embed edilmiş mətn
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['embeddable_type', 'embeddable_id']);
            $table->index('model');
        });

        // text-embedding-3-small üçün 1536 ölçü
        DB::statement('ALTER TABLE embeddings ADD COLUMN vector vector(1536)');

        // Cosine similarity üçün HNSW (mətn embeddingləri üçün ən yaxşı)
        DB::statement(<<<SQL
            CREATE INDEX embeddings_vector_hnsw_idx
            ON embeddings
            USING hnsw (vector vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        SQL);

        // Qismən indeks: yalnız vektoru olan sətirləri indeksə əlavə et
        // Toplu daxiletmələr zamanı indeks şişkinliyinin qarşısını alır
        DB::statement(<<<SQL
            CREATE INDEX embeddings_model_idx
            ON embeddings (model)
            WHERE vector IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};
```

### Vektor Scope-u olan Eloquent Modeli

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Embedding extends Model
{
    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'model',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Bu embedding qeydi üçün vektor saxla.
     */
    public function storeVector(array $vector): void
    {
        $vectorString = '[' . implode(',', $vector) . ']';
        DB::statement(
            'UPDATE embeddings SET vector = ?::vector WHERE id = ?',
            [$vectorString, $this->id]
        );
    }

    /**
     * Scope: verilmiş vektora oxşar embeddingləri tap.
     * pgvector-ın <=> cosine məsafə operatorunu istifadə edir.
     *
     * İstifadə:
     *   Embedding::similarTo($vector, threshold: 0.7)->limit(10)->get();
     */
    public function scopeSimilarTo(
        Builder $query,
        array $vector,
        float $threshold = 0.7,
        string $metric = 'cosine',
    ): Builder {
        $vectorString = '[' . implode(',', $vector) . ']';

        $operator = match($metric) {
            'cosine'    => '<=>',
            'dot'       => '<#>',
            'euclidean' => '<->',
            default     => '=>>'
        };

        // Cosine üçün: məsafə = 1 - oxşarlıq, threshold minimum oxşarlıq deməkdir
        $distanceThreshold = match($metric) {
            'cosine' => 1 - $threshold,
            default  => null,
        };

        $query->whereNotNull('vector')
              ->selectRaw("embeddings.*, 1 - (vector {$operator} ?) as score", [$vectorString])
              ->orderByRaw("vector {$operator} ?", [$vectorString]);

        if ($distanceThreshold !== null) {
            $query->whereRaw("vector {$operator} ? <= ?", [$vectorString, $distanceThreshold]);
        }

        return $query;
    }

    /**
     * Scope: embeddable növünə görə filtrə et.
     */
    public function scopeForModel(Builder $query, string $modelClass): Builder
    {
        return $query->where('embeddable_type', $modelClass);
    }

    /**
     * Scope: embedding modeli üzrə filtrə et.
     */
    public function scopeUsingModel(Builder $query, string $model): Builder
    {
        return $query->where('model', $model);
    }
}
```

### Modellər üçün HasEmbeddings Trait-i

```php
<?php

namespace App\Concerns;

use App\Models\Embedding;
use App\Services\AI\EmbeddingService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEmbeddings
{
    public function embeddings(): MorphMany
    {
        return $this->morphMany(Embedding::class, 'embeddable');
    }

    /**
     * Bu model üçün embedding yarat və saxla.
     * Embed ediləcəyi xüsusiləşdirmək üçün getEmbeddableText() üzərinə yaz.
     */
    public function generateEmbedding(): Embedding
    {
        $text = $this->getEmbeddableText();
        $service = app(EmbeddingService::class);
        $vector = $service->embed($text);

        // Bu model+qeyd kombinasiyası üçün mövcud embeddingi sil
        $this->embeddings()->delete();

        $embedding = $this->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'content' => $text,
            'metadata' => $this->getEmbeddingMetadata(),
        ]);

        $embedding->storeVector($vector);

        return $embedding;
    }

    /**
     * Embedding oxşarlığına əsasən oxşar modellər tap.
     *
     * @param string $query Axtarış üçün mətn sorğusu
     * @param int $limit
     * @param float $threshold
     */
    public static function semanticSearch(
        string $query,
        int $limit = 10,
        float $threshold = 0.7,
    ): \Illuminate\Support\Collection {
        $service = app(EmbeddingService::class);
        $queryVector = $service->embed($query);

        $embeddings = Embedding::similarTo($queryVector, $threshold)
            ->forModel(static::class)
            ->limit($limit)
            ->get();

        // Faktiki model nümunələrini yüklə
        $ids = $embeddings->pluck('embeddable_id');
        $models = static::whereIn('id', $ids)->get()->keyBy('id');

        return $embeddings->map(function ($embedding) use ($models) {
            $model = $models->get($embedding->embeddable_id);
            if ($model) {
                $model->similarity_score = $embedding->score;
            }
            return $model;
        })->filter();
    }

    /**
     * Embed ediləcəyi mətni müəyyənləşdirmək üçün bu metodu üzərinə yaz.
     */
    public function getEmbeddableText(): string
    {
        // Standart: bütün sətir doldurula bilən atributları birləşdir
        return collect($this->getFillable())
            ->filter(fn($attr) => is_string($this->$attr ?? null))
            ->map(fn($attr) => $this->$attr)
            ->implode(' ');
    }

    /**
     * Embeddinglərə xüsusi metadata əlavə etmək üçün üzərinə yaz.
     */
    public function getEmbeddingMetadata(): array
    {
        return [];
    }
}
```

### Performans üçün HNSW İndeksi Yaratmaq

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeVectorIndexes extends Command
{
    protected $signature = 'vector:optimize
                            {--table=embeddings : Vektor sütunu olan cədvəl}
                            {--column=vector : Vektor sütununun adı}
                            {--dims=1536 : Vektor ölçüləri}
                            {--m=16 : HNSW M parametri}
                            {--ef-construction=64 : HNSW ef_construction parametri}
                            {--rebuild : Mövcud indeksi sil və yenidən qur}';

    protected $description = 'Vektor sütununda HNSW indeksi yarat və ya yenidən qur';

    public function handle(): int
    {
        $table = $this->option('table');
        $column = $this->option('column');
        $indexName = "{$table}_{$column}_hnsw_idx";

        if ($this->option('rebuild')) {
            $this->info("Mövcud indeks silinir {$indexName}...");
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
        }

        $this->info("HNSW indeksi qurulur (böyük datasetlər üçün bir neçə dəqiqə çəkə bilər)...");

        // Daha sürətli indeks qurma üçün maintenance work mem-i artır
        DB::statement('SET maintenance_work_mem = "2GB"');

        // Paralel indeks qurmanı aktiv et (PostgreSQL 16+)
        DB::statement('SET max_parallel_maintenance_workers = 4');

        $m = $this->option('m');
        $efConstruction = $this->option('ef-construction');

        DB::statement(<<<SQL
            CREATE INDEX CONCURRENTLY IF NOT EXISTS {$indexName}
            ON {$table}
            USING hnsw ({$column} vector_cosine_ops)
            WITH (m = {$m}, ef_construction = {$efConstruction})
        SQL);

        // İndeksin yaradıldığını yoxla
        $indexExists = DB::selectOne(<<<SQL
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        SQL, [$table, $indexName]);

        if ($indexExists) {
            $this->info("İndeks uğurla yaradıldı: {$indexName}");
            $this->info("Tərif: {$indexExists->indexdef}");
        } else {
            $this->error("İndeks yaratma uğursuz olmuş ola bilər.");
            return self::FAILURE;
        }

        // İndeks ölçüsünü göstər
        $size = DB::selectOne(
            "SELECT pg_size_pretty(pg_relation_size(?)) as size",
            [$indexName]
        );
        $this->info("İndeks ölçüsü: {$size->size}");

        return self::SUCCESS;
    }
}
```

---

## Xarici Xidmət Kimi Qdrant-a Qoşulmaq

### Qdrant Müştəri Xidməti

```php
<?php

namespace App\Services\VectorDB;

use Illuminate\Support\Facades\Http;

class QdrantService
{
    private string $baseUrl;
    private array $defaultHeaders;

    public function __construct()
    {
        $this->baseUrl = config('services.qdrant.url', 'http://localhost:6333');
        $this->defaultHeaders = array_filter([
            'Content-Type' => 'application/json',
            'api-key' => config('services.qdrant.key'),
        ]);
    }

    /**
     * Kolleksiya yarat (cədvələ ekvivalent).
     */
    public function createCollection(
        string $name,
        int $vectorSize = 1536,
        string $distance = 'Cosine', // 'Cosine', 'Dot', 'Euclid'
    ): bool {
        $response = Http::withHeaders($this->defaultHeaders)
            ->put("{$this->baseUrl}/collections/{$name}", [
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => $distance,
                ],
                'hnsw_config' => [
                    'm' => 16,
                    'ef_construct' => 100,
                    'full_scan_threshold' => 10000,
                ],
                'optimizers_config' => [
                    'memmap_threshold' => 20000,
                ],
            ]);

        return $response->successful();
    }

    /**
     * Payloadlarla (metadata) vektorları daxil et.
     */
    public function upsertPoints(string $collection, array $points): bool
    {
        // Hər nöqtə: ['id' => string|int, 'vector' => float[], 'payload' => array]
        $response = Http::withHeaders($this->defaultHeaders)
            ->put("{$this->baseUrl}/collections/{$collection}/points", [
                'points' => array_map(fn($point) => [
                    'id' => $point['id'],
                    'vector' => $point['vector'],
                    'payload' => $point['payload'] ?? [],
                ], $points),
            ]);

        return $response->successful();
    }

    /**
     * İsteğe bağlı payload filtri ilə oxşar vektorlar üçün axtarış et.
     *
     * @param string $collection
     * @param array $vector Sorğu vektoru
     * @param int $limit Nəticə sayı
     * @param array $filter Qdrant filtr şərtləri
     * @param float $scoreThreshold Minimum oxşarlıq balı
     */
    public function search(
        string $collection,
        array $vector,
        int $limit = 10,
        array $filter = [],
        float $scoreThreshold = 0.7,
    ): array {
        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'score_threshold' => $scoreThreshold,
            'with_payload' => true,
            'with_vector' => false,
        ];

        if (!empty($filter)) {
            $body['filter'] = $filter;
        }

        $response = Http::withHeaders($this->defaultHeaders)
            ->post("{$this->baseUrl}/collections/{$collection}/points/search", $body);

        if ($response->failed()) {
            throw new \RuntimeException('Qdrant axtarışı uğursuz oldu: ' . $response->body());
        }

        return $response->json('result');
    }

    /**
     * ID-ə görə nöqtələri sil.
     */
    public function deletePoints(string $collection, array $ids): bool
    {
        $response = Http::withHeaders($this->defaultHeaders)
            ->post("{$this->baseUrl}/collections/{$collection}/points/delete", [
                'points' => $ids,
            ]);

        return $response->successful();
    }

    /**
     * Filtrə uyğun nöqtələri sil.
     * Misal: deleteByFilter('chunks', ['must' => [['key' => 'document_id', 'match' => ['value' => 123]]]])
     */
    public function deleteByFilter(string $collection, array $filter): bool
    {
        $response = Http::withHeaders($this->defaultHeaders)
            ->post("{$this->baseUrl}/collections/{$collection}/points/delete", [
                'filter' => $filter,
            ]);

        return $response->successful();
    }

    /**
     * Kolleksiya məlumatlarını al (vektor sayı, status və s.)
     */
    public function collectionInfo(string $collection): array
    {
        $response = Http::withHeaders($this->defaultHeaders)
            ->get("{$this->baseUrl}/collections/{$collection}");

        return $response->json('result');
    }
}
```

### Qdrant-əsaslanan RAG Xidməti

```php
<?php

namespace App\Services\VectorDB;

use App\Services\AI\EmbeddingService;

class QdrantRAGService
{
    private const COLLECTION = 'rag_chunks';

    public function __construct(
        private QdrantService $qdrant,
        private EmbeddingService $embeddingService,
    ) {
        $this->ensureCollection();
    }

    public function indexChunk(
        string $id,
        string $text,
        array $metadata = [],
    ): void {
        $vector = $this->embeddingService->embed($text);

        $this->qdrant->upsertPoints(self::COLLECTION, [[
            'id' => $id,
            'vector' => $vector,
            'payload' => array_merge($metadata, ['content' => $text]),
        ]]);
    }

    public function search(string $query, int $topK = 5, array $filters = []): array
    {
        $queryVector = $this->embeddingService->embed($query);

        // Sadə açar-dəyər cütlərindən Qdrant filtri qur
        $qdrantFilter = [];
        if (!empty($filters)) {
            $qdrantFilter = [
                'must' => array_map(
                    fn($key, $value) => ['key' => $key, 'match' => ['value' => $value]],
                    array_keys($filters),
                    array_values($filters)
                ),
            ];
        }

        $results = $this->qdrant->search(
            collection: self::COLLECTION,
            vector: $queryVector,
            limit: $topK,
            filter: $qdrantFilter,
            scoreThreshold: 0.65,
        );

        return array_map(fn($result) => [
            'id' => $result['id'],
            'score' => $result['score'],
            'content' => $result['payload']['content'],
            'metadata' => $result['payload'],
        ], $results);
    }

    private function ensureCollection(): void
    {
        $info = $this->qdrant->collectionInfo(self::COLLECTION);
        if (empty($info)) {
            $this->qdrant->createCollection(self::COLLECTION, 1536, 'Cosine');
        }
    }
}
```

---

## Memarlar üçün Qərar Çərçivəsi

### pgvector-dan Nə Zaman İstifadə Edilməli

pgvector istifadə et:
- Yığında artıq PostgreSQL varsa (yeni infrastruktur yoxdur)
- Dataset 5-10 milyon vektordan azdırsa
- ACID ardıcıllığına ehtiyac varsa (vektorlar + metadata eyni tranzaksiyada)
- Relasional məlumatlarla SQL JOIN-ə ehtiyac varsa
- Komanda PostgreSQL ekspertizasına malikdir amma paylanmış sistemlər deyil
- Büdcə məhduddur

### Qdrant/Pinecone-a Nə Zaman Keçilməli

Köç et:
- HNSW tənzimlənməsindən sonra pgvector sorğuları p99-da 50ms-i keçirsə
- Dataset 50M vektoru keçirsə
- Daxili hibrid axtarışa ehtiyac varsa (BM25 + vector)
- Güclü izolyasiya zəmanətləri ilə çox-kiracılığa ehtiyac varsa
- Xüsusi semantik axtarış məhsulu qurursan

### Miqrasiya Yolu

Birinci gündən köç üçün dizayn et:

```php
// Vector store-u interfeys arxasında abstrakt et
interface VectorStoreInterface
{
    public function upsert(string $id, array $vector, array $metadata): void;
    public function search(array $vector, int $limit, float $threshold): array;
    public function delete(string $id): void;
}

// Tətbiqlər
class PgvectorStore implements VectorStoreInterface { ... }
class QdrantStore implements VectorStoreInterface { ... }
class PineconeStore implements VectorStoreInterface { ... }

// Konfiqurasiya vasitəsilə bağla
app()->bind(VectorStoreInterface::class, function () {
    return match(config('vector.driver')) {
        'pgvector' => new PgvectorStore(),
        'qdrant'   => new QdrantStore(),
        'pinecone' => new PineconeStore(),
    };
});
```

### pgvector Performans Tənzimləmə Yoxlama Siyahısı

```sql
-- 1. Sorğu zamanı dəqiqlik/sürət kompromisi üçün ef_search-i tənzimlə
SET hnsw.ef_search = 40; -- standart 10, daha yaxşı recall üçün artır

-- 2. Bağlantı hovuzu istifadə et (PgBouncer) — vektor sorğuları qısa ömürlüdür

-- 3. Sorğu planını yoxla
EXPLAIN (ANALYZE, BUFFERS) 
SELECT id, 1 - (embedding <=> '[...]'::vector) as sim
FROM knowledge_chunks
ORDER BY embedding <=> '[...]'::vector
LIMIT 10;
-- "Index Scan using knowledge_chunks_embedding_idx" göstərməlidir
-- "Seq Scan" göstərərsə, indeks istifadə edilmir

-- 4. Statistikaların güncəl olduğuna əmin ol
ANALYZE knowledge_chunks;

-- 5. 10M-dən böyük datasetlər üçün IVFFlat nəzərdən keçir
CREATE INDEX ON knowledge_chunks 
USING ivfflat (embedding vector_cosine_ops) 
WITH (lists = 1000);
SET ivfflat.probes = 10;
```
