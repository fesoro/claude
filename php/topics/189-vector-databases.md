# Vector Databases (Lead)

## Mündəricat
1. [Vector Database nədir?](#vector-database-nədir)
2. [Similarity Search](#similarity-search)
3. [Əsas Vector DB-lər](#əsas-vector-db-lər)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Vector Database nədir?

```
Vector (embedding) — mətn, şəkil, səsin rəqəmsal təsviri.
Yüksək ölçülü nöqtə (128-4096 ölçü).

LLM (embedding model):
  "iPhone 15 Pro"  → [0.23, -0.51, 0.87, ..., 0.14] (1536 ölçü)
  "Samsung Galaxy" → [0.21, -0.49, 0.83, ..., 0.11] (oxşar!)
  "Alma ağacı"     → [-0.45, 0.89, -0.23, ..., 0.67] (fərqli)

Vector DB:
  Bu vektorları saxlayır
  Ən oxşar vektorları tapır (ANN — Approximate Nearest Neighbor)
  Keyword yox, məna əsaslı axtarış

İstifadə halları:
  Semantic search (məna ilə axtarış)
  Recommendation systems
  RAG (Retrieval Augmented Generation) — LLM-ə context vermək
  Image similarity search
  Anomaly detection
  Duplicate detection
```

---

## Similarity Search

```
Məsafə ölçüləri:

Cosine Similarity:
  İki vektorun bucağını ölçür
  Uzunluq əhəmiyyətsiz, yalnız istiqamət
  Mətn üçün ən uyğun
  Dəyər: -1 (tam əks) ... +1 (tam eyni)

Euclidean Distance (L2):
  İki nöqtə arasındakı məsafə
  Kompüter vizyon üçün uyğun

Dot Product:
  Cosine × magnitude
  Vektorlar normalize edilmişsə cosine ilə eyni

ANN Alqoritmlər:
  HNSW (Hierarchical Navigable Small World):
    Qraf əsaslı, ən sürətli
    Yüksək memory istifadəsi
    
  IVF (Inverted File Index):
    Cluster-əsaslı
    Daha az memory
    Bir az daha az dəqiq
    
  LSH (Locality Sensitive Hashing):
    Sadə, hash-based
    Keyfiyyət az

ANN vs Exact:
  Exact NN: 100% dəqiq, O(n) — yavaş
  ANN:      ~95% dəqiq, O(log n) — sürətli
  Real-world-da ANN kifayət edir
```

---

## Əsas Vector DB-lər

```
Pinecone:
  Fully managed SaaS
  Sadə API
  Production-ready
  PHP SDK mövcud

Weaviate:
  Open source + cloud
  GraphQL API
  Built-in LLM inteqrasiyası
  Hybrid search (vector + keyword)

Qdrant:
  Rust ilə yazılmış, sürətli
  REST + gRPC API
  PHP client mövcud
  Self-hosted

Milvus:
  Open source, production-grade
  Horizontal scaling
  Kubernetes native

pgvector (PostgreSQL extension):
  PostgreSQL-ə vector qabiliyyəti əlavə edir
  SQL ilə vector search
  Mövcud PostgreSQL stack-inə inteqrasiya

Chroma:
  Development üçün sadə
  Python-first amma REST API var
  Local embedding model

Seçim:
  Mövcud PostgreSQL stack → pgvector
  Managed/SaaS → Pinecone
  Self-hosted open source → Qdrant / Weaviate
  Large scale → Milvus
```

---

## PHP İmplementasiyası

```php
<?php
// pgvector ilə PHP (composer require pgvector/pgvector)
// PostgreSQL extension: CREATE EXTENSION vector;

class ProductSemanticSearch
{
    public function __construct(private \PDO $db) {}

    public function setupTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS products (
                id          SERIAL PRIMARY KEY,
                name        TEXT NOT NULL,
                description TEXT,
                embedding   vector(1536)  -- OpenAI ada-002 ölçüsü
            )
        ");

        // HNSW indeks — sürətli ANN
        $this->db->exec("
            CREATE INDEX IF NOT EXISTS products_embedding_idx
            ON products USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ");
    }

    public function insert(string $name, string $desc, array $embedding): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products (name, description, embedding) VALUES (?, ?, ?) RETURNING id'
        );
        $embeddingStr = '[' . implode(',', $embedding) . ']';
        $stmt->execute([$name, $desc, $embeddingStr]);
        return $stmt->fetchColumn();
    }

    public function searchSimilar(array $queryEmbedding, int $limit = 5): array
    {
        $embeddingStr = '[' . implode(',', $queryEmbedding) . ']';

        $stmt = $this->db->prepare(
            "SELECT id, name, description,
                    1 - (embedding <=> ?) AS similarity
             FROM products
             ORDER BY embedding <=> ?
             LIMIT ?"
        );
        $stmt->execute([$embeddingStr, $embeddingStr, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

```php
<?php
// Qdrant PHP client
// composer require qdrant/client

use Qdrant\Qdrant;
use Qdrant\Models\Request\CreateCollection;
use Qdrant\Models\Request\PointsUpsert;
use Qdrant\Models\Request\SearchRequest;
use Qdrant\Models\VectorsConfig;
use Qdrant\Models\VectorParams;

class QdrantProductSearch
{
    private Qdrant $client;
    private string $collection = 'products';

    public function __construct(string $host = 'http://localhost:6333')
    {
        $this->client = new Qdrant(new \Qdrant\Http\Builder($host));
    }

    public function createCollection(): void
    {
        $this->client->collections($this->collection)->create(
            new CreateCollection(
                new VectorsConfig(new VectorParams(1536, 'Cosine'))
            )
        );
    }

    public function upsertProduct(int $id, array $embedding, array $payload): void
    {
        $this->client->collections($this->collection)->points()->upsert(
            new PointsUpsert([
                [
                    'id'      => $id,
                    'vector'  => $embedding,
                    'payload' => $payload,
                ]
            ])
        );
    }

    public function search(array $queryEmbedding, int $limit = 5): array
    {
        $response = $this->client->collections($this->collection)->points()->search(
            new SearchRequest($queryEmbedding, $limit)
        );

        return array_map(
            fn($result) => [
                'id'         => $result->getId(),
                'score'      => $result->getScore(),
                'name'       => $result->getPayload()['name'] ?? '',
            ],
            $response->getResult()
        );
    }
}
```

```php
<?php
// RAG (Retrieval Augmented Generation) pattern
class RagService
{
    public function __construct(
        private QdrantProductSearch $vectorDb,
        private OpenAiClient        $openai,
    ) {}

    public function answer(string $userQuestion): string
    {
        // 1. Sualı embedding-ə çevir
        $queryEmbedding = $this->openai->embed($userQuestion);

        // 2. Ən oxşar sənədləri tap
        $relevantDocs = $this->vectorDb->search($queryEmbedding, limit: 3);

        // 3. LLM-ə context ver
        $context = implode("\n\n", array_column($relevantDocs, 'description'));

        $prompt = <<<PROMPT
        Aşağıdakı kontekstə əsasən sualı cavablandır:
        
        Kontekst:
        {$context}
        
        Sual: {$userQuestion}
        PROMPT;

        return $this->openai->complete($prompt);
    }
}
```

---

## İntervyu Sualları

- Vector database adi relational DB-dən nəyi fərqli edir?
- Cosine similarity ilə Euclidean distance fərqi nədir?
- ANN (Approximate Nearest Neighbor) nədir? Dəqiq search-dən üstünlüyü?
- HNSW alqoritmi necə işləyir?
- RAG pattern nədir? Vector DB-nin rolu nədir?
- pgvector nə zaman tam Vector DB-dən daha yaxşı seçimdir?
