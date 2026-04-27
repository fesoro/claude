# Vector Databases (pgvector, Pinecone, Weaviate, Qdrant, Milvus) (Lead)

## Niye Vector Database Lazim Oldu?

LLM (ChatGPT, Claude) ve semantic search-in artmasi ile birlikde **vector database**-ler yarandi. Klassik database-de "iPhone" sozunu axtaranda yalniz "iPhone" tapilir; vector DB-de **menaca yaxin** olanlar tapilir: "smartphone", "Apple telefonu", "iOS device".

**Esas use case-ler:**
- **RAG (Retrieval-Augmented Generation)** — LLM-e oz datanizi qosmaq
- **Semantic search** — soz deyil, mena axtarisi
- **Recommendation** — oxsar mehsul/film/maqale tapma
- **Image / audio similarity** — sekil/ses uzre oxsar tapma
- **Anomaly detection** — adi olmayan vector-lari tapma
- **Duplicate detection** — eyni menali kontent

## Embeddings Nedir?

**Embedding** — text/sekil/sesi cox-olcu (high-dimensional) **float vector**-e cevirmek. Mena ne qeder yaxindirsa, vector-lar o qeder yaxin yerlesir.

```
"sui ic" → [0.23, -0.45, 0.81, ..., 0.12]  (1536 sayda float)
"meşrubat al" → [0.21, -0.42, 0.79, ..., 0.15]  (cox yaxin!)
"kod yaz" → [-0.67, 0.14, -0.22, ..., 0.91]  (uzaq)
```

**Populyar embedding model-leri:**

| Model | Provider | Dimension | Qiymet |
|-------|----------|-----------|--------|
| `text-embedding-3-small` | OpenAI | 1536 (ve ya 512) | $0.02 / 1M token |
| `text-embedding-3-large` | OpenAI | 3072 | $0.13 / 1M token |
| `voyage-3` | Voyage AI | 1024 | $0.06 / 1M token |
| `cohere-embed-v3` | Cohere | 1024 | $0.10 / 1M token |
| `all-MiniLM-L6-v2` | Sentence-Transformers (local) | 384 | Pulsuz |
| `bge-large-en` | BAAI (open-source) | 1024 | Pulsuz |

> **Qayda:** Eyni dataset icinde **eyni model** istifade et. Ferqli model-lerin vector-lari muqayise olunmur.

---

## Similarity Metric-leri

Iki vector ne qeder yaxindirsa, mena o qeder yaxindir. Uc esas olcu:

| Metric | Formula | Hudud | Ne vaxt |
|--------|---------|-------|---------|
| **Cosine similarity** | `a·b / (\|a\| × \|b\|)` | -1..+1 | Text embedding (en cox) |
| **Dot product** | `Σ aᵢ × bᵢ` | -∞..+∞ | Vector normalize edilibse |
| **Euclidean (L2)** | `√Σ(aᵢ - bᵢ)²` | 0..∞ | Sekil, geo data |
| **Manhattan (L1)** | `Σ\|aᵢ - bᵢ\|` | 0..∞ | Sparse data |

```sql
-- pgvector operatorları
SELECT id FROM items ORDER BY embedding <=> '[0.1, 0.2, ...]' LIMIT 10;  -- Cosine distance
SELECT id FROM items ORDER BY embedding <-> '[0.1, 0.2, ...]' LIMIT 10;  -- L2 distance
SELECT id FROM items ORDER BY embedding <#> '[0.1, 0.2, ...]' LIMIT 10;  -- Negative dot product
```

> **Qeyd:** `<=>` cosine **distance**-dir (1 - similarity). 0 = eyni, 2 = tam eks.

---

## ANN Algorithms (Approximate Nearest Neighbor)

Milyonlarla vector arasinda exact (KNN) axtaris cox baha basa gelir. **ANN** — bir az dəqiqliyi qurban verib **100x-1000x daha suretli** islemek.

### HNSW (Hierarchical Navigable Small World)

En populyar. Multi-layer graph qurur. Recall yuksek (>95%), latency asagi.

```
Layer 2:  A ──── F (uzaq qonsular)
          │      │
Layer 1:  A ── B ── E ── F
          │   │   │   │
Layer 0:  A B C D E F G  (butun node-lar)
```

**Parametrler:**
- `m` — her node-un baglilig sayi (default 16). Daha boyuk = yaxsi recall, cox memory
- `ef_construction` — qurma vaxti axtaris derinliyi (default 64-200)
- `ef_search` — query vaxti axtaris derinliyi (default 40-100)

### IVF (Inverted File Index)

Vector-lari **cluster**-lere bolur (k-means). Query yalniz oxsar cluster-de axtarir.

- `lists` — cluster sayi (~ √N tovsiye olunur)
- `probes` — query-de baxilan cluster sayi (cox = yavas amma deqiq)

### PQ (Product Quantization)

Vector-i **kicik qisimlere** bolur ve her birini ID ile evez edir. Memory 10-100x azaldir, amma deqiqlik dusur.

| Algorithm | Recall | Latency | Memory | Build time |
|-----------|--------|---------|--------|------------|
| Brute-force (KNN) | 100% | Yavas | Az | Sürətli |
| **HNSW** | 95-99% | En suretli | Cox | Yavas |
| **IVF** | 90-95% | Suretli | Orta | Orta |
| **IVF + PQ** | 80-90% | Cox suretli | Cox az | Orta |

---

## pgvector — PostgreSQL Extension

Mevcud Postgres-e vector dəstəyi elave edir. Kicik-orta layihe ucun ideal.

```sql
-- Extension elave et
CREATE EXTENSION vector;

-- Cedval
CREATE TABLE documents (
    id BIGSERIAL PRIMARY KEY,
    content TEXT NOT NULL,
    embedding vector(1536),  -- OpenAI ada-002 / 3-small
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Insert
INSERT INTO documents (content, embedding, metadata)
VALUES ('Laravel framework PHP-de yazilir', '[0.012, -0.034, ...]', '{"category": "framework"}');

-- KNN axtaris (exact, kicik dataset)
SELECT id, content, embedding <=> '[0.01, 0.02, ...]' AS distance
FROM documents
ORDER BY embedding <=> '[0.01, 0.02, ...]'
LIMIT 5;

-- HNSW index (oxuma cox suretli)
CREATE INDEX ON documents
USING hnsw (embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);

-- IVFFlat index (yaratma daha suretli)
CREATE INDEX ON documents
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);

-- Query vaxti tuning
SET hnsw.ef_search = 100;
SET ivfflat.probes = 10;

-- Hybrid search (vector + metadata filter)
SELECT id, content
FROM documents
WHERE metadata->>'category' = 'framework'
  AND created_at > NOW() - INTERVAL '30 days'
ORDER BY embedding <=> '[0.01, 0.02, ...]'
LIMIT 10;
```

> **Tələ:** `WHERE` filter-ini index-den evvel tetbiq edirse, ANN index istifade olunmaya biler. Buna `pre-filtering vs post-filtering` deyilir. Cox seçici filter ucun **partial index** yarat.

---

## Vector DB Muqayise Cedveli

| DB | Tip | Indeks | Hosted | Filter | En guclu cehet |
|----|-----|--------|--------|--------|----------------|
| **pgvector** | Postgres extension | HNSW, IVFFlat | Self / Neon / Supabase | SQL WHERE | Mevcud Postgres ile birge |
| **Pinecone** | Managed SaaS | Proprietary | Yalniz cloud | Metadata filter | Sade API, auto-scale |
| **Weaviate** | Standalone | HNSW | Self / cloud | GraphQL filter | Multi-tenant, hybrid search |
| **Qdrant** | Standalone (Rust) | HNSW | Self / cloud | Rich payload | Suret + filter performance |
| **Milvus** | Distributed | HNSW, IVF, DiskANN | Self / Zilliz | Complex | Milyardlarla vector |
| **Chroma** | Embedded / standalone | HNSW | Self | Simple metadata | Prototip, kicik dataset |
| **Redis (RediSearch)** | KV + vector | HNSW, FLAT | Self / Cloud | Tag, numeric | Asagi latency cache |
| **Elasticsearch** | Search + vector | HNSW | Self / Cloud | Full-text + vector | Hibrid keyword + semantic |

### Hansini secmeli?

```
Kicik (<1M vector) + mevcud Postgres var → pgvector
Orta-boyuk + DevOps istemir            → Pinecone (managed)
Open-source + multi-tenant lazim        → Weaviate
Rust seviyye performans + filter        → Qdrant
Milyardlar vector + GPU                 → Milvus
RAG prototype, sade lokal               → Chroma
```

---

## Hybrid Search (Vector + Keyword)

Yalniz semantic axtaris bezi hallarda zəyifdir (acronym, exact name). **Hybrid** = vector + BM25/full-text.

```sql
-- pgvector + tsvector hybrid (Reciprocal Rank Fusion)
WITH semantic AS (
    SELECT id, ROW_NUMBER() OVER (ORDER BY embedding <=> :qvec) AS rank
    FROM documents ORDER BY embedding <=> :qvec LIMIT 50
),
keyword AS (
    SELECT id, ROW_NUMBER() OVER (ORDER BY ts_rank(tsv, plainto_tsquery(:qtext)) DESC) AS rank
    FROM documents
    WHERE tsv @@ plainto_tsquery(:qtext)
    LIMIT 50
)
SELECT d.*, (1.0/(60+s.rank) + 1.0/(60+k.rank)) AS score
FROM documents d
LEFT JOIN semantic s ON s.id = d.id
LEFT JOIN keyword k ON k.id = d.id
WHERE s.id IS NOT NULL OR k.id IS NOT NULL
ORDER BY score DESC LIMIT 10;
```

**RRF (Reciprocal Rank Fusion)** — sade ve guclu hybrid scoring. Skor: `Σ 1 / (k + rank_i)` (k = 60 default).

---

## RAG Pattern

```
1. Document → Chunk-lara bol (200-500 token)
2. Her chunk-i embedding-e cevir
3. Vector DB-ye yaz (chunk text + metadata + embedding)
4. User soruşur → query-ni embedding-e cevir
5. Vector DB-de top-K (5-20) en yaxin chunk tap
6. Reranker ile yeniden sirala (optional)
7. Top-K chunk-lari + user sualını LLM-e prompt et
8. LLM cavab uretir
```

### Chunking Strategiyalari

| Strategiya | Necə | Ne vaxt |
|------------|------|---------|
| **Fixed-size** | Her N token | Sade, sürətli, ümumi |
| **Recursive split** | Paragraf → cumle → soz | Default tovsiye |
| **Semantic chunking** | Mena dəyişəndə kesir | Yuksek keyfiyyet |
| **Document-aware** | Markdown header / code block | Texniki sənəd |
| **Sliding window** | Overlap (50-100 token) | Kontekst itirməmək |

---

## Laravel + OpenAI + pgvector Misalı

```bash
composer require openai-php/client pgvector/pgvector
```

```php
// app/Services/EmbeddingService.php
namespace App\Services;

use OpenAI;

class EmbeddingService
{
    private $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.key'));
    }

    public function embed(string $text): array
    {
        $response = $this->client->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    public function embedBatch(array $texts): array
    {
        $response = $this->client->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $texts,
        ]);

        return array_map(fn($e) => $e->embedding, $response->embeddings);
    }
}
```

```php
// Migration
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->text('content');
    $table->jsonb('metadata')->nullable();
    $table->timestamps();
});

DB::statement('ALTER TABLE documents ADD COLUMN embedding vector(1536)');
DB::statement('CREATE INDEX documents_embedding_idx ON documents
               USING hnsw (embedding vector_cosine_ops)');
```

```php
// Document yazma
class DocumentService
{
    public function __construct(private EmbeddingService $embedder) {}

    public function ingest(string $content, array $metadata = []): int
    {
        $embedding = $this->embedder->embed($content);
        $vector = '[' . implode(',', $embedding) . ']';

        return DB::table('documents')->insertGetId([
            'content' => $content,
            'metadata' => json_encode($metadata),
            'embedding' => DB::raw("'$vector'::vector"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function search(string $query, int $topK = 5, ?string $category = null): array
    {
        $embedding = $this->embedder->embed($query);
        $vector = '[' . implode(',', $embedding) . ']';

        $q = DB::table('documents')
            ->select('id', 'content', 'metadata')
            ->selectRaw("embedding <=> '$vector'::vector AS distance")
            ->orderByRaw("embedding <=> '$vector'::vector")
            ->limit($topK);

        if ($category) {
            $q->whereRaw("metadata->>'category' = ?", [$category]);
        }

        return $q->get()->toArray();
    }
}

// Controller — RAG endpoint
public function ask(Request $request, DocumentService $docs, OpenAI\Client $llm)
{
    $question = $request->input('question');
    $context = $docs->search($question, 5);

    $prompt = "Answer based on context only:\n\n";
    foreach ($context as $i => $doc) {
        $prompt .= "[" . ($i + 1) . "] " . $doc->content . "\n\n";
    }
    $prompt .= "Question: $question";

    $resp = $llm->chat()->create([
        'model' => 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ]);

    return response()->json([
        'answer' => $resp->choices[0]->message->content,
        'sources' => $context,
    ]);
}
```

---

## Reranking

Vector axtarisi top-50 nameset cixarir; **reranker** (cross-encoder model) bunlari yeniden deqiq sıralayır.

```php
// Cohere reranker misalı
$rerank = Http::withToken($cohereKey)->post('https://api.cohere.ai/v1/rerank', [
    'model' => 'rerank-english-v3.0',
    'query' => $question,
    'documents' => array_column($candidates, 'content'),
    'top_n' => 5,
])->json();

// Indis-e gore yeniden sirala
$reranked = array_map(fn($r) => $candidates[$r['index']], $rerank['results']);
```

**Pipeline:** `Vector top-50 → Reranker top-5 → LLM`. 10-30% keyfiyyet artir.

---

## Dimension Tradeoff

| Dim | Storage / 1M | Latency | Keyfiyyet |
|-----|--------------|---------|-----------|
| 384 | ~1.5 GB | Suretli | Yaxsi (general) |
| 768 | ~3 GB | Orta | Cox yaxsi |
| 1536 | ~6 GB | Yavasaq | Eən yaxsi (OpenAI default) |
| 3072 | ~12 GB | Yavas | Marginal artim |

> **Trick:** OpenAI `text-embedding-3-*` model-leri **Matryoshka** dəstəkləyir — `dimensions` parametri ile 1536-ni 512-ye kiciltmek olar (10-15% keyfiyyet itkisi, 3x az storage).

---

## Interview suallari

**Q: Vector database ne ucun lazimdir, normal Postgres yetmir?**
A: Klassik B-tree index "exact match" ucun isleyir. Vector axtarisinda "menaca yaxin" lazimdir — bu, 1536-olcu fezada **nearest neighbor** problemidir. Normal index burada islemir, ANN index (HNSW, IVF) lazimdir. pgvector mehz Postgres-e bu indeksi elave edir.

**Q: HNSW ile IVFFlat arasinda secim necedir?**
A: HNSW — recall ve latency cox yuksekdir, amma memory cox tutur ve build vaxti uzundur. IVFFlat — daha az memory, build suretlidir, amma recall bir az asagi. Read-heavy production = HNSW. Tez-tez yenilenen + memory mehduddursa = IVFFlat.

**Q: RAG-da chunk size-i nece secmek lazimdir?**
A: Cox kicik (50 token) → kontekst itir, cox boyuk (2000 token) → relevance pozulur, LLM token israf olur. Default 200-500 token + 50-100 token overlap. Texniki sened ucun document-aware (header-e gore) chunking daha yaxsidir.

**Q: pgvector-de filter (category = X) ile ANN axtaris birlikde necə isleyir?**
A: Iki yanasma var: **pre-filter** (filter once, sonra ANN) — kicik hudud-da yaxsi, amma index istifade olunmur; **post-filter** (ANN once, sonra filter) — boyuk top-K lazim, az sonra filter ile azalir. pgvector 0.7+ versiyasinda iterative scan var. Cox seçici filter ucun partial index yarat.

**Q: Cosine similarity ve dot product arasinda ferq?**
A: Cosine vector-larin **istiqametine** baxir, uzunluq ehemiyyetsizdir (-1..+1). Dot product hem istiqamet, hem uzunluqdur. Eger butun vector-lar **L2-normalize** edilibse (length=1), onda cosine = dot product, amma dot product hesablanmasi daha suretlidir (boluş yoxdur). OpenAI embeddings artiq normalize olunur, ona gore dot product istifade edile biler.
