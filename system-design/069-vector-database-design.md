# Vector Database Design (Lead)

## İcmal

Vector database embedding-ləri (yüksək ölçülü float array-ləri) saxlayan və
similarity search edə bilən ixtisaslaşmış storage sistemidir. LLM və image
model-lər mətn, şəkil, audio-nu 384/768/1536 ölçülü vektorlara çevirir;
cosine və ya L2 məsafə semantic oxşarlığı ifadə edir.

Sadə dillə: relational DB `WHERE email = 'x'` equality axtarır; vector DB
`WHERE vector ≈ query_vector` edir — "bu mətnə MƏNACA oxşar 10 sənəd tap".
Use-case: RAG, semantic search, recommendation, dedup, face match.

```
Text "laravel queue fails"
        │
        ▼  (embedding model: OpenAI / BGE / E5)
  [0.12, -0.44, 0.81, ..., 0.03]   (768 float)
        │
        ▼
  Vector DB ── ANN index ──▶ Top-K oxşar vektor → metadata (doc id, url)
```


## Niyə Vacibdir

Generative AI dövrü semantic search tələbini kəskin artırdı. HNSW, IVF indekslər ANN (Approximate Nearest Neighbor) sorğusunu millisaniyədə cavablandırır; RAG (Retrieval Augmented Generation) bu olmadan mümkün deyil. pgvector, Pinecone, Weaviate — real AI produktlarının əsasıdır.

## Tələblər

### Functional

```
- Upsert: (id, vector, metadata) insert / update
- kNN search: query vector → top-k nearest neighbors
- Filtered search: metadata predicate + vector similarity
- Delete by id
- Multiple indexes / collections (per tenant, per model)
- Hybrid search: keyword (BM25) + vector birgə
```

### Non-Functional

```
Vectors:       10M-1B nöqtə
Dim:           384-1536
Recall@10:     >= 95% (ANN üçün qəbul edilən)
Query p99:     < 50 ms (real-time RAG)
Ingest:        10k vector/s yazı
Availability:  99.9%+
Cost:          vector başına storage + embedding API cost
```

## Distance Metrics (Məsafə Metrikləri)

```
Cosine similarity  cos(a,b) = (a·b) / (|a||b|)       → 1 = eyni, 0 = ortogonal
L2 (Euclidean)     sqrt(Σ(ai - bi)^2)                → 0 = eyni
Inner product      a·b                               → böyük = oxşar (not normalized)
Hamming            fərqli bit sayı (binary vectors)  → 0 = eyni
```

Normalized vektorda cosine == inner product. LLM embedding-ləri adətən cosine
istifadə edir. Binary hash (128/256 bit) hamming ilə sürətli bucket match.

## Exact vs Approximate kNN

```
Exact (brute force):   O(N * d) hər query
  100k, 768 dim        → ~50 ms        OK
  10M                  → ~5 s          Yavaş
  1B                   → saatlar       Qeyri-mümkün

ANN (approximate):     O(log N), 95-99% recall, 100-1000x sürətli
```

Kiçik corpus (< 100k) üçün brute force pgvector kifayətdir; 1M+ və
latency-sensitive üçün ANN index lazımdır.

## ANN Indekslər (Index Types)

### 1. HNSW (Hierarchical Navigable Small World)

Layered graph. Üst layer-lərdə az node, uzun edge; aşağı layer-də hamısı.
Query entry point-dən başlayır, hər layer-də greedy yaxınlaşır.

```
Layer 2:     A ─────────── E
             │             │
Layer 1:     A ── C ─────── E ── G
             │    │         │    │
Layer 0:  A─B─C─D─E─F─G─H─I─J─K─L   (hamısı burada)

Query q:
  1) entry = A (top layer)
  2) Layer 2: A → E (E daha yaxın)
  3) Layer 1: E-nin qonşuları {C, G} → G
  4) Layer 0: G ətrafında ef_search candidate → top-k
```

**Parametrlər**:
- `M` — hər node-un maksimum qonşusu (16-64 tipik). Böyük M = böyük recall,
  böyük memory.
- `ef_construction` — build zamanı candidate list (100-400). Böyük = yaxşı
  graph, yavaş insert.
- `ef_search` — query zamanı list (50-500). Böyük = yaxşı recall, yavaş query.

Üstünlük: ən yaxşı recall/latency trade-off, incremental insert.
Çatışmaz: memory-heavy (graph RAM-da), build bahadır.

### 2. IVF (Inverted File)

Vektorları k-means ilə `nlist` cluster-ə böl. Query üçün yalnız ən yaxın
`nprobe` cluster-i axtar.

```
Train: k-means → 1000 centroid

    ┌───────┐   ┌───────┐   ┌───────┐
    │ C1    │   │ C2    │   │ C3    │
    │ ●●●   │   │ ●●●   │   │ ●●●   │   ... 1000 cluster
    │ ●●    │   │ ●●●●  │   │ ●●    │
    └───────┘   └───────┘   └───────┘

Query q:
  1) q ilə ən yaxın nprobe=8 centroid tap   (1000 comparison)
  2) Yalnız həmin 8 cluster-i brute force axtar
  3) Top-k qaytarır
```

**Trade-off**: `nprobe` artsa recall artır, latency artır. `nprobe=1` çox sürətli
amma ~70% recall; `nprobe=nlist` == exact search.

### 3. IVF + PQ (Product Quantization)

Vektoru `m` sub-vector-a böl, hər birini 256 centroid-dən biri ilə əvəzlə.
768 dim float32 (3072 byte) → 96 byte kimi sıxılır (32x kiçilmə).

```
Original: [f1, f2, ..., f768]  (3072 bytes)
Split m=96 sub-vectors of dim 8
Each sub-vector → nearest of 256 centroids → 1 byte code
Compressed: [c1, c2, ..., c96]  (96 bytes)
Distance: lookup table with precomputed sub-distances
```

Billion-scale məcburi: 1B × 768f32 = 3TB RAM; PQ ilə ~100GB.

### 4. IVF-HNSW Hybrid

Centroid search-də IVF yerinə HNSW — Faiss `IndexHNSWFlat` üstündə IVF.

### 5. LSH və ScaNN

LSH — random hyperplane hash, memory-efficient amma recall zəif, bu gün az.
ScaNN (Google) — anisotropic quantization + tree search, top-k-ya təsir edən
istiqamətlərdə error-u minimizə edir.

## HNSW Build Nümunəsi (PHP / pgvector)

```php
// composer: pgvector/pgvector-php, openai-php/client
use Pgvector\Laravel\Vector;
use OpenAI;

// 1. Schema migration
Schema::create('documents', function (Blueprint $t) {
    $t->id();
    $t->text('content');
    $t->jsonb('metadata');
    $t->vector('embedding', 1536); // text-embedding-3-small
    $t->timestamps();
});

// 2. HNSW index (pgvector 0.5+)
DB::statement('
    CREATE INDEX documents_embedding_hnsw
    ON documents
    USING hnsw (embedding vector_cosine_ops)
    WITH (m = 16, ef_construction = 200)
');

// Build-dən sonra query zamanı:
// SET hnsw.ef_search = 100;
```

## Data Model

```
Collection (index):
  name            string
  dim             int
  metric          cosine | l2 | ip
  index_type      hnsw | ivf | ivfpq
  index_params    { M, ef_construction, nlist, nprobe }

Point (vector):
  id              string / uuid
  vector          float[dim]
  metadata        { user_id, source, lang, created_at, tags[] }
```

Pinecone/Weaviate/Qdrant eyni modeldə; fərq serverless vs self-host, hybrid search, filter dili.

## Ingestion Pipeline (Embedding)

```
Raw text → chunk 500-1000 tok → Embedding (OpenAI/BGE/E5) → Cache (hash→vec)
        → Vector DB upsert(id, vector, metadata)
```

### Laravel — Index və RAG Query

```php
public function indexDocument(string $content, array $meta): void
{
    $vector = Cache::remember("emb:" . sha1($content), 86400*30, fn() =>
        OpenAI::client(env('OPENAI_KEY'))->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $content,
        ])->embeddings[0]->embedding
    );

    Document::create([
        'content' => $content,
        'metadata' => $meta,
        'embedding' => new Vector($vector),
    ]);
}

public function rag(string $question): string
{
    $qvec = $this->embed($question);
    // pgvector: <=> cosine distance
    $docs = DB::select('
        SELECT content, 1 - (embedding <=> ?::vector) AS score
        FROM documents
        WHERE metadata->>\'lang\' = ?
        ORDER BY embedding <=> ?::vector LIMIT 5
    ', [$qvec, 'en', $qvec]);

    $context = collect($docs)->pluck('content')->implode("\n---\n");
    return OpenAI::chat()->create([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => "Answer using context:\n$context"],
            ['role' => 'user', 'content' => $question],
        ],
    ])->choices[0]->message->content;
}
```

### Pinecone REST Nümunə

```php
$base = 'https://my-index-xxx.svc.us-east1.pinecone.io';
$h = ['Api-Key' => env('PINECONE_KEY')];

Http::withHeaders($h)->post("$base/vectors/upsert", [
    'vectors' => [['id' => 'doc-42', 'values' => $vector,
                   'metadata' => ['source' => 'blog', 'lang' => 'en']]],
]);

$hits = Http::withHeaders($h)->post("$base/query", [
    'vector' => $qvec, 'topK' => 10,
    'filter' => ['lang' => ['$eq' => 'en']],
    'includeMetadata' => true,
])->json('matches');
```

## Memory, Storage, Cost

```
1M × 768 × 4B float32 = 3 GB + HNSW overhead ~1 GB = 4 GB RAM
Quantization: float16 50% save, int8 75%, PQ 96%

Latency target:  p50 10 ms, p99 50 ms
Cost (2026):     Embedding $0.02-0.10 / 1M token
                 Pinecone ~$0.33 / GB / month serverless
```

## Sharding və Replication

```
Hash(id) % N shard           → balanced write, broadcast query
Semantic shard (by centroid) → lokal query, hot shard risk

coordinator ──▶ shard1 (top-20)
            ──▶ shard2 (top-20)   merge → top-10
            ──▶ shard3 (top-20)

Replication: async replica read scale + HA, eventual consistency 1-2 s.
```

## Filtered Search (Metadata + Vector)

```
Pre-filter:  metadata WHERE əvvəl → qalanı brute force; az pointda index dağılır
Post-filter: ANN top-k*M tap, sonra filter; sade amma selective-də az qalır
Filtered HNSW (Weaviate/Qdrant/pgvector 0.7): graph traversal-da filter; ən yaxşı
```

Rule: çox seçici filter (< 5% pass) → SQL first + brute force. Az seçici → filtered ANN.

## Hybrid Search (BM25 + Vector)

```
Query "laravel queue retry"
  ├─▶ BM25 (Elasticsearch / Postgres tsvector) top-50
  └─▶ Vector (ANN) top-50
           │
           ▼
  Reciprocal Rank Fusion (RRF):
    score(d) = Σ 1 / (k + rank_i(d))    k=60 tipik
           │
           ▼
  Final top-10
```

Nə üçün: vector semantic oxşarlığı, BM25 isə nadir keyword-ləri (versiya
nömrəsi, error code) yaxşı tapır. RAG-da birgə recall 5-15% artır.

## Freshness və Delete

```
Delta index: son yazılar üçün ayrıca kiçik HNSW; query hər ikisini merge edir
Batch:       gündə 1 dəfə yeni ANN graph build edilir
Tombstone:   delete = mark deleted; HNSW graph tam silmir, periodic rebuild lazım
```

## Sistemlərin Müqayisəsi (Systems Comparison)

| System        | Hosting       | Index      | Hybrid | Filter  | Notes                     |
|---------------|---------------|------------|--------|---------|---------------------------|
| Pinecone      | SaaS serverless | IVF/HNSW | yes    | good    | no infra, per-read billing|
| Weaviate      | OSS / Cloud   | HNSW       | yes BM25| good   | GraphQL, modules          |
| Milvus        | OSS (k8s)     | HNSW/IVF/PQ| partial| good   | billion-scale, complex    |
| Qdrant        | OSS / Cloud   | HNSW       | yes    | best   | Rust, fast, rich filter   |
| pgvector      | Postgres ext  | HNSW/IVFFlat| via FTS| SQL WHERE | RDBMS integration       |
| ES / OpenSearch| OSS          | HNSW       | yes    | good   | existing search stack     |

Başlanğıc seçimi: Postgres varsa pgvector (1M-10M OK); 100M+ və ya ayrıca
tenant-lər üçün Qdrant / Pinecone; ExSearch artıq stack-dədirsə kNN plugin.

## Praktik Baxış

```
- Normalize vectors client-side bir dəfə (cosine üçün), query-də təkrar yox
- Cache embeddings (hash→vector); eyni mətni 2-ci dəfə API-yə göndərmə
- Chunk 500-1000 token, overlap 50-100 (context itkisinə qarşı)
- HNSW default: M=16, ef_construction=200; ef_search ilə recall tune et
- IVF: nlist ≈ sqrt(N), nprobe ~1% of nlist
- int8 quantization: <1% recall itki, 4x storage qazanc
- RAG-da hybrid search (BM25+vector RRF) default et
- Filtered search: seçici filter → SQL first; zəif filter → filtered HNSW
- Monitor: recall@10 offline, query p50/p99, index build time
- Dim seç: 1536 dim 4x bahadır 384-dən; task kifayət edirsə kiçik seç
- Rerank: top-50 ANN → cross-encoder → top-5 — RAG keyfiyyəti artır
- Multi-tenant: 100+ tenant → shared + filter; few big → ayrı collection
- Bulk delete-dən sonra rebuild; HNSW tombstone yığılır
- Quality metrics: MRR, NDCG, recall@k offline test set ilə
```

## Praktik Tapşırıqlar

**Q1: Exact kNN nə vaxt kifayətdir, nə vaxt ANN lazımdır?**
A: Corpus < 100k və QPS < 10 olanda pgvector `ORDER BY embedding <=> q` brute
force 50 ms-də cavab verir. 1M+ vector və ya p99 < 100 ms lazımdırsa HNSW/IVF
məcburidir. Kiçikdən başla, latency problem olanda ANN-ə keç.

**Q2: HNSW vs IVF+PQ — hansını seçirsən?**
A: HNSW yüksək recall, aşağı latency, amma graph RAM-da — 10M-100M ideal. IVF+PQ
memory 10-30x kiçik (billion-scale), 2-5% recall itkisi. 1B+ vector və ya bahalı
RAM-da IVF+PQ. Orta həll: IVF-HNSW hybrid.

**Q3: Metadata filter ANN search ilə necə birləşdirilir?**
A: (1) Pre-filter — SQL ilə əvvəl filter, qalanı brute force; selective filter
üçün yaxşı. (2) Post-filter — ANN top-k*10 + filter; sade amma selective-də
boş qala bilər. (3) Filtered HNSW — graph traversal zamanı filter (Qdrant,
Weaviate, pgvector 0.7); ən yaxşı ümumi həll.

**Q4: RAG pipeline-ını necə dizayn edirsən?**
A: Ingestion — chunk → embed → store. Query — question embed → vector top-20 →
optional cross-encoder rerank top-5 → inject prompt → LLM generate. Hybrid
BM25+vector RRF recall artırır. Embedding cache API xərcini azaldır.

**Q5: Embedding model necə seçirsən — OpenAI vs open-source?**
A: OpenAI text-embedding-3-small (1536 dim, $0.02/1M token) sadə və yaxşı. BGE/E5
self-host — GPU lazım, amma per-embedding cost 0, data cloud-a getmir. 100M+
document və ya GDPR tələbində self-host qazanclıdır.

**Q6: Vector DB-də yeni yazı real-time görünməsi necə təmin olunur?**
A: HNSW incremental insert 1-2 s-də görünür. Bulk ingest-də build performance
düşür. Production pattern: canlı yazılar `delta_index` (in-memory HNSW), əsas
`base_index` gündə batch rebuild, query ikisini merge edir. Pinecone/Qdrant
bunu daxilən edir.

**Q7: 1 milyard vektor üçün storage necə planlaşdırırsan?**
A: 1B × 768 × 4 = 3 TB — tək node-da olmaz. (1) PQ quantization 3TB → ~100GB.
(2) Horizontal sharding 10-20 node hash-lə, query broadcast + merge top-k.
(3) Replication 2-3x HA üçün. (4) Hot/cold tier — seyrək nodes üçün DiskANN.

**Q8: Similarity search-də recall necə ölçülür və tune olunur?**
A: Offline test set — ground truth exact kNN top-k. ANN nəticəsi ilə intersection/k
= recall@k. HNSW-də `ef_search` artsa recall artır, latency artır; 95-99% recall
target üçün p99 latency-ni ölçə-ölçə tune et. Online: user CTR, LLM-as-judge.


## Əlaqəli Mövzular

- [Recommendation System](36-recommendation-system.md) — embedding similarity
- [Document Search](76-document-search-design.md) — hybrid BM25 + vector search
- [Feature Store](70-feature-store-design.md) — embedding feature-ləri saxlamaq
- [Search Systems](12-search-systems.md) — semantic search konteksti
- [AI Inference Serving](78-ai-inference-serving.md) — embedding generation
