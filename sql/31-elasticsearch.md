# Elasticsearch

## Elasticsearch Nedir?

Distributed search ve analytics engine. Full-text search, log analysis, real-time analytics ucun istifade olunur. Apache Lucene uzerinde qurulub.

**Ne vaxt lazimdir?**
- MySQL/PostgreSQL `LIKE '%search%'` yavasdir (full table scan)
- Complex text search (fuzzy, synonym, relevance scoring)
- Log aggregation ve analysis (ELK stack)
- Auto-complete / search suggestions
- Faceted search (filter by category, price range, brand)

## Esas Konseptler

| SQL Termin | Elasticsearch Termin |
|-----------|---------------------|
| Database | Index |
| Table | Type (7.x-de qaldirildi) |
| Row | Document |
| Column | Field |
| Schema | Mapping |
| SQL Query | Query DSL (JSON) |
| INDEX | Inverted Index (default) |

### Inverted Index Nece Isleyir?

```
Document 1: "Quick brown fox"
Document 2: "Quick brown dog"
Document 3: "Lazy brown fox"

Inverted Index:
"quick" → [doc1, doc2]
"brown" → [doc1, doc2, doc3]
"fox"   → [doc1, doc3]
"dog"   → [doc2]
"lazy"  → [doc3]

Search "brown fox" → "brown" ∩ "fox" = [doc1, doc3]
```

SQL-in B-Tree index-inden ferqli olaraq, inverted index **sozlere gore** axtaris edir. Bu text search ucun ideal amma range query ucun deyil.

## CRUD Emeliyyatlari

```bash
# Index yarat (mapping ile)
PUT /products
{
    "mappings": {
        "properties": {
            "name": { "type": "text", "analyzer": "standard" },
            "description": { "type": "text" },
            "category": { "type": "keyword" },     # keyword = exact match
            "price": { "type": "float" },
            "stock": { "type": "integer" },
            "tags": { "type": "keyword" },          # Array olaraq da isleyir
            "created_at": { "type": "date" },
            "specs": { "type": "object" }
        }
    },
    "settings": {
        "number_of_shards": 3,
        "number_of_replicas": 1
    }
}

# Document elave et
POST /products/_doc/1
{
    "name": "iPhone 15 Pro Max",
    "description": "Apple-in en guclu smartfonu",
    "category": "electronics",
    "price": 1199.99,
    "stock": 50,
    "tags": ["smartphone", "apple", "5g"],
    "created_at": "2024-01-15"
}

# Bulk insert (suretli)
POST /products/_bulk
{ "index": { "_id": "2" } }
{ "name": "Samsung Galaxy S24", "category": "electronics", "price": 899 }
{ "index": { "_id": "3" } }
{ "name": "MacBook Pro M3", "category": "electronics", "price": 2499 }

# Document al
GET /products/_doc/1

# Update
POST /products/_update/1
{
    "doc": { "price": 1099.99, "stock": 45 }
}

# Delete
DELETE /products/_doc/1
```

## Search Query-leri

### Full-Text Search

```bash
# match - text analysis ile axtaris (tokenize + lowercase)
GET /products/_search
{
    "query": {
        "match": {
            "name": "iphone pro"    # "iphone" OR "pro" olan her sey
        }
    }
}

# match_phrase - tam ifade axtarisi
GET /products/_search
{
    "query": {
        "match_phrase": {
            "name": "iPhone 15"    # "iPhone 15" ardicilligi olmalidir
        }
    }
}

# multi_match - birden cox field-de axtar
GET /products/_search
{
    "query": {
        "multi_match": {
            "query": "pro max",
            "fields": ["name^3", "description"],   # name 3x daha vacib
            "type": "best_fields"
        }
    }
}

# fuzzy - sehv yazilisa tolerant
GET /products/_search
{
    "query": {
        "fuzzy": {
            "name": {
                "value": "iphne",        # "iphone" tapacaq
                "fuzziness": "AUTO"       # 1-2 herf sehvi
            }
        }
    }
}

# wildcard
GET /products/_search
{
    "query": {
        "wildcard": {
            "name": "iph*"    # "iphone", "iphone 15" ve s.
        }
    }
}
```

### Structured Search (Filter)

```bash
# bool query - complex filter
GET /products/_search
{
    "query": {
        "bool": {
            "must": [                                    # AND
                { "match": { "name": "phone" } }
            ],
            "filter": [                                  # AND (cached, suretli)
                { "term": { "category": "electronics" } },
                { "range": { "price": { "gte": 500, "lte": 1500 } } },
                { "range": { "stock": { "gt": 0 } } }
            ],
            "should": [                                  # OR (relevance artirır)
                { "term": { "tags": "5g" } },
                { "term": { "tags": "bestseller" } }
            ],
            "must_not": [                                # NOT
                { "term": { "category": "accessories" } }
            ],
            "minimum_should_match": 1
        }
    },
    "sort": [
        { "price": "asc" },
        "_score"                                         # Relevance score
    ],
    "from": 0,
    "size": 20
}
```

### Aggregations (GROUP BY)

```bash
# Kateqoriyaya gore sayi (faceted search)
GET /products/_search
{
    "size": 0,     # Document qaytarma, yalniz aggregation
    "aggs": {
        "categories": {
            "terms": {
                "field": "category",
                "size": 20
            }
        },
        "price_ranges": {
            "range": {
                "field": "price",
                "ranges": [
                    { "to": 100 },
                    { "from": 100, "to": 500 },
                    { "from": 500, "to": 1000 },
                    { "from": 1000 }
                ]
            }
        },
        "avg_price": {
            "avg": { "field": "price" }
        },
        "price_stats": {
            "stats": { "field": "price" }    # min, max, avg, sum, count
        }
    }
}
```

### Auto-Complete / Suggestions

```bash
# Completion suggester ucun mapping
PUT /products
{
    "mappings": {
        "properties": {
            "suggest": {
                "type": "completion"       # Xususi tip
            }
        }
    }
}

# Suggest query
GET /products/_search
{
    "suggest": {
        "product_suggest": {
            "prefix": "iph",
            "completion": {
                "field": "suggest",
                "size": 5,
                "fuzzy": {
                    "fuzziness": 1
                }
            }
        }
    }
}
```

## Laravel ile Elasticsearch

### Laravel Scout + Elasticsearch

```php
// composer require laravel/scout
// composer require matchish/laravel-scout-elasticsearch

// config/scout.php
'driver' => 'Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine',

// .env
ELASTICSEARCH_HOST=localhost:9200

// Model
class Product extends Model
{
    use Searchable;

    // Elasticsearch-e gonderilem data
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'tags' => $this->tags,
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    // Axtaris ucun index adi
    public function searchableAs(): string
    {
        return 'products';
    }
}

// Axtaris
$results = Product::search('iphone pro')
    ->where('category', 'electronics')
    ->where('price', ['gte' => 500])
    ->orderBy('price', 'asc')
    ->paginate(20);

// Import/Sync
// php artisan scout:import "App\Models\Product"
```

### Direct Elasticsearch Client

```php
// composer require elasticsearch/elasticsearch

use Elastic\Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()
    ->setHosts(['localhost:9200'])
    ->build();

// Search
$params = [
    'index' => 'products',
    'body' => [
        'query' => [
            'bool' => [
                'must' => [
                    ['multi_match' => [
                        'query' => $searchTerm,
                        'fields' => ['name^3', 'description', 'tags^2'],
                        'type' => 'best_fields',
                        'fuzziness' => 'AUTO',
                    ]],
                ],
                'filter' => array_filter([
                    $category ? ['term' => ['category' => $category]] : null,
                    $minPrice ? ['range' => ['price' => ['gte' => $minPrice]]] : null,
                    ['range' => ['stock' => ['gt' => 0]]],
                ]),
            ],
        ],
        'aggs' => [
            'categories' => ['terms' => ['field' => 'category']],
            'price_stats' => ['stats' => ['field' => 'price']],
        ],
        'highlight' => [
            'fields' => [
                'name' => new \stdClass(),
                'description' => ['fragment_size' => 150],
            ],
        ],
        'from' => ($page - 1) * $perPage,
        'size' => $perPage,
    ],
];

$response = $client->search($params);

// Neticeleri isle
foreach ($response['hits']['hits'] as $hit) {
    $product = $hit['_source'];
    $score = $hit['_score'];                    // Relevance score
    $highlight = $hit['highlight'] ?? [];       // Highlighted fragments
}
$totalHits = $response['hits']['total']['value'];
$categories = $response['aggregations']['categories']['buckets'];
```

## Database + Elasticsearch Sync

```
Primary Database (PostgreSQL/MySQL) ──sync──> Elasticsearch
       │                                           │
   Source of truth                          Search/Read optimized
   (ACID, transactions)                    (full-text, aggregations)
```

### Sync Strategiyalari

```php
// 1. Model Observer ile sync (sadə, kicik scale)
class ProductObserver
{
    public function saved(Product $product): void
    {
        // Async job ile index yenile
        IndexProductJob::dispatch($product);
    }

    public function deleted(Product $product): void
    {
        DeleteProductFromIndexJob::dispatch($product->id);
    }
}

// 2. Event-driven sync
class IndexProductJob implements ShouldQueue
{
    public function handle(ElasticSearchService $es): void
    {
        $es->index([
            'index' => 'products',
            'id' => $this->product->id,
            'body' => $this->product->toSearchableArray(),
        ]);
    }
}

// 3. CDC ile sync (en etibarli - boyuk scale)
// Debezium → Kafka → Elasticsearch Sink Connector
// (22-change-data-capture.md-e bax)
```

## Elasticsearch vs Database Full-Text Search

| Xususiyyet | MySQL FULLTEXT | PG tsvector | Elasticsearch |
|------------|---------------|-------------|---------------|
| **Setup** | Asan | Orta | Cetindir |
| **Performance** | Orta | Yaxsi | En yaxsi |
| **Fuzzy search** | Xeyr | Xeyr (trgm ile) | Beli |
| **Relevance tuning** | Limitli | Limitli | Coxlu options |
| **Aggregations** | SQL ile | SQL ile | Native, suretli |
| **Auto-complete** | Xeyr | Xeyr | Native |
| **Scaling** | Vertical | Vertical | Horizontal (shards) |
| **Maintenance** | Yoxdur | VACUUM | Cluster management |
| **Ne vaxt?** | Kicik app | Orta app | Boyuk app, complex search |

## Interview Suallari

1. **Elasticsearch nece isleyir (inverted index)?**
   - Metn sozlere bolunur (tokenize), her soz ucun hansi document-lerde oldugu saxlanilir. Axtaris zamani soz inverted index-de axtarilir - O(1).

2. **text vs keyword field ferqi?**
   - `text`: Analyze olunur (tokenize, lowercase). Full-text search ucun. `keyword`: Exact match. Filter, sort, aggregation ucun.

3. **Elasticsearch ile database nece sync olunur?**
   - 1) Model observer + async job (asan). 2) CDC/Debezium (etibarli). 3) Periodic bulk reindex (asan amma yavas).

4. **must vs filter ferqi?**
   - `must`: Relevance score-a tesir edir, result caching yoxdur. `filter`: Score-a tesir etmir, neticeler cache olunur - daha suretli.

5. **Ne vaxt Elasticsearch lazimdir, ne vaxt database full-text search kifayet edir?**
   - Database: Sadə search, kicik data, elave infrastructure istemirsense. Elasticsearch: Complex search, fuzzy, suggestions, facets, boyuk data, suret vacibdirse.

6. **Elasticsearch-in dezavantajlari?**
   - ACID yoxdur, near-real-time (1 saniye gecikmə), complex aggregation-lar yavas ola biler, cluster management cetindir, RAM istifadesi yuksekdir.
