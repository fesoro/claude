# Search System — DB Design (Senior ⭐⭐⭐)

## İcmal

Full-text search hər ciddi tətbiqin vacib komponentidir. PostgreSQL `tsvector` kiçik miqyas üçün kifayət edir, lakin mürəkkəb search (facets, relevance tuning, geo, autocomplete) Elasticsearch/OpenSearch tələb edir. Bu fayl search sisteminin DB arxitekturasını, Elasticsearch schema dizaynını, indexing pipeline-ı və real-dünya nümunələrini əhatə edir.

---

## Tövsiyə olunan DB Stack

```
Primary data:   PostgreSQL / MySQL    (source of truth)
Search engine:  Elasticsearch         (search index)
Sync:           Kafka + CDC           (real-time index update)
Cache:          Redis                 (popular queries, autocomplete)
Analytics:      ClickHouse            (search analytics, click-through)
```

---

## Elasticsearch vs PostgreSQL FTS

```
PostgreSQL tsvector:
  ✓ Kiçik/orta data (< 1M documents)
  ✓ Sadə full-text search
  ✓ Ayrı infra lazım deyil
  ✗ Faceted search çətin
  ✗ Relevance tuning məhdud
  ✗ Geo + text combo zəif
  ✗ Large scale (100M+ docs) yavaş

Elasticsearch:
  ✓ 100M+ documents
  ✓ Faceted search (aggregations)
  ✓ Relevance scoring (BM25, custom)
  ✓ Geo + text + filters combined
  ✓ Autocomplete (edge ngram)
  ✓ Fuzzy search (typo tolerance)
  ✓ Synonyms, stopwords
  ✗ Ayrı infra + ops
  ✗ Schema migration əl işi
  ✗ Near real-time (1 saniyə gecikmə)

Qərar:
  Blog / CMS       → PostgreSQL tsvector
  E-commerce       → Elasticsearch (facets lazım)
  Job board        → Elasticsearch (geo + salary filter)
  Any app, 1M+ doc → Elasticsearch
```

---

## Elasticsearch Index Schema

### E-commerce Product Search

```json
PUT /products
{
  "settings": {
    "number_of_shards": 5,
    "number_of_replicas": 1,
    "analysis": {
      "analyzer": {
        "az_analyzer": {
          "type": "custom",
          "tokenizer": "standard",
          "filter": ["lowercase", "asciifolding", "stop"]
        },
        "autocomplete_analyzer": {
          "type": "custom",
          "tokenizer": "standard",
          "filter": ["lowercase", "edge_ngram_filter"]
        },
        "autocomplete_search": {
          "type": "custom",
          "tokenizer": "standard",
          "filter": ["lowercase"]
        }
      },
      "filter": {
        "edge_ngram_filter": {
          "type": "edge_ngram",
          "min_gram": 2,
          "max_gram": 20
        }
      }
    }
  },
  "mappings": {
    "properties": {
      "id":          {"type": "long"},
      "name": {
        "type": "text",
        "analyzer": "az_analyzer",
        "fields": {
          "autocomplete": {
            "type": "text",
            "analyzer": "autocomplete_analyzer",
            "search_analyzer": "autocomplete_search"
          },
          "keyword": {"type": "keyword"}
        }
      },
      "description": {"type": "text", "analyzer": "az_analyzer"},
      "brand":       {"type": "keyword"},
      "category":    {"type": "keyword"},
      "tags":        {"type": "keyword"},
      "price":       {"type": "float"},
      "discount_pct":{"type": "float"},
      "stock":       {"type": "integer"},
      "rating":      {"type": "half_float"},
      "review_count":{"type": "integer"},
      "is_active":   {"type": "boolean"},
      "location": {
        "type": "geo_point"
      },
      "created_at":  {"type": "date"}
    }
  }
}
```

---

## Search Query Dizaynı

### Sadə mətn axtarışı

```json
GET /products/_search
{
  "query": {
    "multi_match": {
      "query": "nike running shoes",
      "fields": ["name^3", "description", "brand^2", "tags"],
      "type": "best_fields",
      "fuzziness": "AUTO"
    }
  }
}
```

### Faceted search (e-commerce)

```json
GET /products/_search
{
  "query": {
    "bool": {
      "must": [
        {"multi_match": {
          "query": "laptop",
          "fields": ["name^3", "description"]
        }}
      ],
      "filter": [
        {"term":  {"is_active": true}},
        {"range": {"price": {"gte": 500, "lte": 2000}}},
        {"terms": {"brand": ["Apple", "Dell", "Lenovo"]}},
        {"range": {"rating": {"gte": 4.0}}}
      ]
    }
  },
  "aggs": {
    "brands": {
      "terms": {"field": "brand", "size": 20}
    },
    "price_ranges": {
      "range": {
        "field": "price",
        "ranges": [
          {"to": 500},
          {"from": 500, "to": 1000},
          {"from": 1000, "to": 2000},
          {"from": 2000}
        ]
      }
    },
    "avg_rating": {
      "avg": {"field": "rating"}
    }
  },
  "sort": [
    {"_score": "desc"},
    {"rating": "desc"},
    {"review_count": "desc"}
  ],
  "from": 0,
  "size": 24
}
```

### Autocomplete

```json
GET /products/_search
{
  "query": {
    "match": {
      "name.autocomplete": {
        "query": "nik",
        "analyzer": "autocomplete_search"
      }
    }
  },
  "_source": ["id", "name", "brand"],
  "size": 10
}
```

### Geo search (job board / local search)

```json
GET /jobs/_search
{
  "query": {
    "bool": {
      "must": [
        {"match": {"title": "backend developer"}}
      ],
      "filter": [
        {"geo_distance": {
          "distance": "50km",
          "location": {"lat": 40.4093, "lon": 49.8671}
        }},
        {"terms": {"employment_type": ["full_time", "remote"]}}
      ]
    }
  },
  "sort": [
    {"_score": "desc"},
    {"_geo_distance": {
      "location": {"lat": 40.4093, "lon": 49.8671},
      "order": "asc",
      "unit": "km"
    }}
  ]
}
```

---

## Indexing Pipeline

```
DB (source of truth) → Search Index (Elasticsearch)

3 yanaşma:

1. Application-level dual write (sadə amma problem-li):
   Save to DB → then index to ES
   Risk: DB save OK, ES index fail → inconsistent
   
2. CDC (Change Data Capture):
   DB binlog → Debezium → Kafka → ES Sink Connector
   Guaranteed: every DB change → ES update
   Latency: ~1-5 seconds
   
3. Scheduled sync (batch):
   Hər 5 dəqiqə: SELECT * FROM products WHERE updated_at > ?
   → ES bulk index
   Simple amma lag var

Tövsiyə: CDC approach (production-ready)

CDC pipeline:
  PostgreSQL WAL / MySQL binlog
  → Debezium (Kafka Connect source)
  → Kafka topic: products.changes
  → Elasticsearch Kafka Connect sink
  → ES index updated

Laravel üçün sadə approach:
  Observer (Model event) → dispatch IndexProductJob
  Job: ES client.index(product)
  Queue: async, retry on failure
```

---

## Laravel Elasticsearch Integration

```php
// config/elasticsearch.php
// elastic/elasticsearch-php package

class ProductSearchService
{
    public function __construct(
        private Client $client
    ) {}

    public function index(Product $product): void
    {
        $this->client->index([
            'index' => 'products',
            'id'    => $product->id,
            'body'  => [
                'id'           => $product->id,
                'name'         => $product->name,
                'description'  => $product->description,
                'brand'        => $product->brand,
                'category'     => $product->category->name,
                'price'        => $product->price,
                'rating'       => $product->rating,
                'is_active'    => $product->is_active,
                'created_at'   => $product->created_at->toIso8601String(),
            ],
        ]);
    }

    public function search(SearchRequest $request): array
    {
        $query = [
            'bool' => [
                'must'   => [],
                'filter' => [['term' => ['is_active' => true]]],
            ],
        ];

        if ($request->q) {
            $query['bool']['must'][] = [
                'multi_match' => [
                    'query'     => $request->q,
                    'fields'    => ['name^3', 'description', 'brand^2'],
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if ($request->min_price || $request->max_price) {
            $range = [];
            if ($request->min_price) $range['gte'] = $request->min_price;
            if ($request->max_price) $range['lte'] = $request->max_price;
            $query['bool']['filter'][] = ['range' => ['price' => $range]];
        }

        if ($request->brands) {
            $query['bool']['filter'][] = ['terms' => ['brand' => $request->brands]];
        }

        $response = $this->client->search([
            'index' => 'products',
            'body'  => [
                'query' => $query,
                'aggs'  => [
                    'brands' => ['terms' => ['field' => 'brand', 'size' => 30]],
                    'price_stats' => ['stats' => ['field' => 'price']],
                ],
                'from'  => $request->offset(),
                'size'  => $request->per_page ?? 24,
                'sort'  => $this->buildSort($request->sort),
            ],
        ]);

        return [
            'hits'       => $response['hits']['hits'],
            'total'      => $response['hits']['total']['value'],
            'facets'     => $response['aggregations'],
        ];
    }
}

// Model Observer
class ProductObserver
{
    public function saved(Product $product): void
    {
        IndexProductJob::dispatch($product->id);
    }

    public function deleted(Product $product): void
    {
        DeleteProductFromIndexJob::dispatch($product->id);
    }
}
```

---

## Autocomplete: Redis + ES Hibrid

```
Autocomplete üçün 2 yanaşma:

1. Elasticsearch edge ngram (yuxarıda göstərildi):
   "nik" → ["Nike", "Nikon", "Nikelab"]
   
2. Redis sorted set (daha sürətli, top queries):
   ZADD autocomplete:products 0 "nike shoes"
   ZADD autocomplete:products 0 "nike air max"
   
   -- Prefix search (Redis 6.2+)
   ZRANGEBYLEX autocomplete:products [nik (nij
   -- Returns: ["nike air max", "nike shoes", ...]

3. Hibrid (tövsiyə):
   Redis: top-100 popular searches (fast, in-memory)
   ES:    long-tail searches (comprehensive)
   
   Redis miss → ES query → Redis-ə cache (TTL: 1 saat)

-- Populer query tracking
ZINCRBY search:popular:daily:{date} 1 "nike shoes"
ZREVRANGE search:popular:daily:{today} 0 9   -- top 10 today
```

---

## Re-indexing Strategy

```
Problem: ES mapping dəyişdirildi (yeni field, analyzer)
Həll: Zero-downtime reindex

1. Yeni index yarat (v2):
   PUT /products_v2 {new mappings}

2. Alias sistemi:
   Mövcud: products → products_v1
   Read alias: "products_read" → products_v1
   Write alias: "products_write" → products_v1 AND products_v2

3. Bulk reindex:
   POST /_reindex
   {
     "source": {"index": "products_v1"},
     "dest":   {"index": "products_v2"}
   }
   -- Background, non-blocking

4. Yeni yazılar (dual write dövrü):
   products_v1 + products_v2 hər ikisini alır

5. Swap aliases:
   products_read → products_v2
   products_write → products_v2

6. Köhnəni sil:
   DELETE /products_v1

Heç bir downtime yoxdur!
```

---

## Search Analytics

```sql
-- ClickHouse: search analytics
CREATE TABLE search_events (
    event_time    DateTime,
    event_date    Date MATERIALIZED toDate(event_time),
    
    query         String,
    user_id       Nullable(Int64),
    session_id    String,
    
    -- Results
    result_count  UInt32,
    
    -- Click-through
    clicked_id    Nullable(Int64),  -- clicked result ID
    click_pos     Nullable(UInt8),  -- position 1-24
    
    -- Context
    filters       String,  -- JSON: {"brand": ["Nike"], "price_max": 200}
    sort          String,
    page          UInt8,
    
    platform      LowCardinality(String)  -- 'web', 'ios', 'android'
    
) ENGINE = MergeTree()
  PARTITION BY event_date
  ORDER BY (event_date, query)
  TTL event_date + INTERVAL 90 DAY;

-- "Bu həftə ən çox axtarılan terminallar"
SELECT query, count() AS searches, countIf(clicked_id IS NOT NULL) AS clicks,
       clicks / searches AS ctr
FROM search_events
WHERE event_date >= today() - 7
  AND result_count > 0
GROUP BY query
ORDER BY searches DESC
LIMIT 20;

-- "Zero result searches" (məhsul əlavə etmək lazımdır)
SELECT query, count() AS searches
FROM search_events
WHERE event_date >= today() - 7
  AND result_count = 0
GROUP BY query
ORDER BY searches DESC
LIMIT 50;
```

---

## Scale Faktları

```
Elasticsearch scale:
  Shard: index bölməsi (default: 1 primary)
  Replica: backup + read scale
  
  Rule: shard size 10-50GB arası saxla
  
  30M products × avg doc 2KB = 60GB
  → 2 shards, 1 replica = 4 copy = 240GB cluster storage
  
  Query performance:
  60M docs search: < 100ms (indexed)
  Aggregations (facets): < 200ms
  
Real-world:
  Airbnb:    Listings index → multi-shard ES cluster
  GitHub:    200TB+ code → custom Blackbird (ES-based)
  Slack:     Per-workspace ES index
  Shopify:   Product + order search
  LinkedIn:  Galene (custom Lucene-based)
```

---

## Anti-Patterns

```
✗ ES-i source of truth kimi istifadə:
  ES near real-time, eventual consistent
  DB = source of truth, ES = search cache
  
✗ Çox kiçik shard-lar (< 1GB each):
  JVM overhead per shard
  "Over-sharding" → slow queries
  
✗ Sync ES write request-də:
  User request gözləyir → ES 100ms+ latency
  Queue ilə async yaz

✗ Wildcard query-lər başlanğıcda:
  "*shoes" → full index scan
  "shoes*" → ok (forward index)
  Autocomplete üçün edge ngram istifadə et

✗ mapping dəyişmədən index update:
  "Dynamic mapping" → unexpected text→keyword
  Explicit mapping həmişə yaz

✗ Reindex etmədən analyzer dəyişmək:
  Köhnə documents köhnə analyzerla indexed
  Dəyişiklik yalnız yeni doclara təsir edir
  → Full reindex lazımdır
```

---

## Tanınmış Sistemlər

```
GitHub Code Search (Blackbird):
  45M+ public repos
  Symbol search (function/class definitions)
  Regular expression support
  Custom Lucene-based (not vanilla ES)

Airbnb:
  Geo + filters + availability combined
  Elasticsearch geo_point
  Date availability stored in ES (not separate query)

Shopify:
  Per-shop ES index (multi-tenant)
  Product + order search
  Merchant dashboard search

Slack:
  Per-workspace index
  Message search: text + from + in + date filters
  Free plan: 90 days only (messages purged from ES too)

LinkedIn (Galene):
  Built on Lucene (not ES)
  Custom ranking (PYMK, connection signals boost)
  Real-time updates via Samza stream processing
```
