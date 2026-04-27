# Elasticsearch (Middle)

## Mündəricat
1. [Elasticsearch nədir?](#elasticsearch-nədir)
2. [Index, mapping, analyzer](#index-mapping-analyzer)
3. [PHP client setup](#php-client-setup)
4. [CRUD əməliyyatları](#crud-əməliyyatları)
5. [Search query DSL](#search-query-dsl)
6. [Aggregations](#aggregations)
7. [Bulk operations](#bulk-operations)
8. [Scroll & search_after pagination](#scroll--search_after-pagination)
9. [Index management](#index-management)
10. [Laravel Scout inteqrasiya](#laravel-scout-inteqrasiya)
11. [Best practices](#best-practices)
12. [İntervyu Sualları](#intervyu-sualları)

---

## Elasticsearch nədir?

```
Elasticsearch — distributed search & analytics engine.
Lucene üzərində qurulub. JSON-based REST API.

Use case:
  - Full-text search (e-commerce, documents)
  - Log analytics (ELK stack: Elastic + Logstash + Kibana)
  - Metric aggregation
  - Geospatial search
  - Real-time dashboards

Versiyalar:
  Elasticsearch 7.x — type olmayan single-type
  Elasticsearch 8.x — security default ON, vector search
  OpenSearch — AWS fork (license dispute), API uyumlu

Niyə Postgres LIKE/ts_vector əvəzinə?
  - Distributed (millions of docs)
  - Fast text search (BM25 algorithm)
  - Aggregations (faceted search)
  - Custom analyzers (language-specific)
  - Real-time indexing
```

---

## Index, mapping, analyzer

```json
PUT /products
{
  "settings": {
    "number_of_shards": 3,
    "number_of_replicas": 1,
    "analysis": {
      "analyzer": {
        "az_analyzer": {
          "type": "custom",
          "tokenizer": "standard",
          "filter": ["lowercase", "asciifolding"]
        }
      }
    }
  },
  "mappings": {
    "properties": {
      "name":        { "type": "text", "analyzer": "az_analyzer" },
      "description": { "type": "text", "analyzer": "az_analyzer" },
      "category":    { "type": "keyword" },
      "price":       { "type": "double" },
      "in_stock":    { "type": "boolean" },
      "tags":        { "type": "keyword" },
      "created_at":  { "type": "date" },
      "location":    { "type": "geo_point" }
    }
  }
}
```

```
Field tipləri:
  text       — full-text search (analyzed, tokenized)
  keyword    — exact match, sort, aggregation
  double     — numeric
  integer
  boolean
  date       — ISO 8601 və ya epoch
  geo_point
  nested     — array of objects
  object     — JSON object

Analyzer:
  Tokenizer:    "Hello World" → ["Hello", "World"]
  Lowercase:    "Hello" → "hello"
  Stemmer:      "running" → "run"
  Stopwords:    "the", "a" silinir
  Synonym:      "car" ↔ "vehicle"

Index size:
  Hər shard ~10-50 GB optimal
  10M document × 5 KB = 50 GB → 5 shard ideal
```

---

## PHP client setup

```bash
composer require elasticsearch/elasticsearch
```

```php
<?php
use Elastic\Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()
    ->setHosts(['https://es-1:9200', 'https://es-2:9200'])
    ->setBasicAuthentication('elastic', 'password')
    ->setCABundle('/path/to/ca.crt')
    ->setRetries(3)
    ->build();

// PSR-18 HTTP client custom
$client = ClientBuilder::create()
    ->setHttpClient($psrHttpClient)
    ->build();

// Cluster info
$info = $client->info();
echo $info['version']['number'];   // "8.11.0"

// Health
$health = $client->cluster()->health();
echo $health['status'];   // "green", "yellow", "red"
```

---

## CRUD əməliyyatları

```php
<?php
// CREATE / INDEX (upsert)
$response = $client->index([
    'index' => 'products',
    'id'    => 42,           // optional, omit → auto-generate
    'body'  => [
        'name'        => 'Laptop',
        'description' => 'Powerful gaming laptop',
        'category'    => 'electronics',
        'price'       => 1299.99,
        'in_stock'    => true,
        'tags'        => ['gaming', 'laptop'],
        'created_at'  => date('c'),
    ],
]);
// $response['_id'] → 42, $response['_version'] → 1

// READ
$doc = $client->get([
    'index' => 'products',
    'id'    => 42,
]);
echo $doc['_source']['name'];   // "Laptop"

// EXISTS
$exists = $client->exists(['index' => 'products', 'id' => 42]);
$bool = (bool) $exists;

// UPDATE — partial
$client->update([
    'index' => 'products',
    'id'    => 42,
    'body'  => [
        'doc' => ['price' => 1199.99, 'in_stock' => false],
    ],
]);

// Update by script
$client->update([
    'index' => 'products',
    'id'    => 42,
    'body'  => [
        'script' => [
            'source' => 'ctx._source.views += params.count',
            'params' => ['count' => 1],
        ],
    ],
]);

// UPSERT
$client->update([
    'index' => 'products',
    'id'    => 42,
    'body'  => [
        'doc'           => ['price' => 999.99],
        'doc_as_upsert' => true,   // doc yoxdursa yarat
    ],
]);

// DELETE
$client->delete(['index' => 'products', 'id' => 42]);

// Delete by query
$client->deleteByQuery([
    'index' => 'products',
    'body'  => [
        'query' => ['term' => ['category' => 'discontinued']],
    ],
]);
```

---

## Search query DSL

```php
<?php
// Match (full-text)
$result = $client->search([
    'index' => 'products',
    'body'  => [
        'query' => [
            'match' => [
                'name' => 'gaming laptop',
            ],
        ],
    ],
]);

foreach ($result['hits']['hits'] as $hit) {
    echo $hit['_source']['name'] . ' (score: ' . $hit['_score'] . ")\n";
}

// Multi-field search
'query' => [
    'multi_match' => [
        'query'  => 'gaming laptop',
        'fields' => ['name^3', 'description', 'tags^2'],   // ^3 = boost
        'type'   => 'best_fields',
    ],
]

// Term (exact match — keyword/numeric/boolean)
'query' => [
    'term' => ['category' => 'electronics']
]

// Range
'query' => [
    'range' => [
        'price' => ['gte' => 100, 'lte' => 500],
        'created_at' => ['gte' => 'now-7d/d'],
    ],
]

// Bool — combine
'query' => [
    'bool' => [
        'must' => [
            ['match' => ['name' => 'laptop']],
        ],
        'filter' => [           // filter cache-lənir, score təsir etmir
            ['term' => ['in_stock' => true]],
            ['range' => ['price' => ['lte' => 2000]]],
        ],
        'should' => [           // bonus score
            ['match' => ['tags' => 'gaming']],
        ],
        'must_not' => [
            ['term' => ['category' => 'refurbished']],
        ],
        'minimum_should_match' => 1,
    ],
]

// Sort & paginate
'body' => [
    'query' => [...],
    'sort' => [
        ['_score' => 'desc'],
        ['price' => 'asc'],
    ],
    'from' => 0,    // offset
    'size' => 20,
]

// Highlight matched terms
'body' => [
    'query' => [...],
    'highlight' => [
        'fields' => [
            'name'        => new \stdClass(),
            'description' => ['number_of_fragments' => 3],
        ],
    ],
]

// Geo search
'query' => [
    'geo_distance' => [
        'distance' => '10km',
        'location' => ['lat' => 40.4093, 'lon' => 49.8671],   // Baku
    ],
]
```

---

## Aggregations

```php
<?php
// Faceted search — kateqoriyalar üzrə count
$result = $client->search([
    'index' => 'products',
    'body'  => [
        'size' => 0,    // hits lazım deyil, yalnız aggregation
        'query' => ['match_all' => new \stdClass()],
        'aggs' => [
            'by_category' => [
                'terms' => ['field' => 'category', 'size' => 10],
            ],
            'price_stats' => [
                'stats' => ['field' => 'price'],
            ],
            'price_histogram' => [
                'histogram' => ['field' => 'price', 'interval' => 100],
            ],
        ],
    ],
]);

print_r($result['aggregations']);
// 'by_category' => [
//   'buckets' => [
//     ['key' => 'electronics', 'doc_count' => 245],
//     ['key' => 'clothing',    'doc_count' => 180],
//   ]
// ]
// 'price_stats' => ['count' => 425, 'min' => 10, 'max' => 5000, 'avg' => 423]

// Nested aggregation
'aggs' => [
    'by_category' => [
        'terms' => ['field' => 'category'],
        'aggs' => [
            'avg_price' => ['avg' => ['field' => 'price']],
        ],
    ],
]

// Date histogram
'aggs' => [
    'orders_per_day' => [
        'date_histogram' => [
            'field' => 'created_at',
            'calendar_interval' => 'day',
        ],
    ],
]
```

---

## Bulk operations

```php
<?php
// Bulk index — 1000 doc bir request-də
$params = ['body' => []];

foreach ($products as $product) {
    $params['body'][] = [
        'index' => ['_index' => 'products', '_id' => $product->id],
    ];
    $params['body'][] = $product->toArray();
}

$response = $client->bulk($params);

// Errors yoxla
if ($response['errors']) {
    foreach ($response['items'] as $item) {
        if (isset($item['index']['error'])) {
            error_log("Failed: " . json_encode($item['index']['error']));
        }
    }
}

// Mixed operations
$params['body'][] = ['index' => ['_index' => 'products', '_id' => 1]];
$params['body'][] = ['name' => 'A'];
$params['body'][] = ['update' => ['_index' => 'products', '_id' => 2]];
$params['body'][] = ['doc' => ['name' => 'B']];
$params['body'][] = ['delete' => ['_index' => 'products', '_id' => 3]];

// Chunk böyük dataset-lər üçün
foreach (array_chunk($items, 1000) as $chunk) {
    $params = ['body' => []];
    foreach ($chunk as $item) {
        $params['body'][] = ['index' => ['_index' => 'products', '_id' => $item->id]];
        $params['body'][] = $item->toArray();
    }
    $client->bulk($params);
}
```

---

## Scroll & search_after pagination

```php
<?php
// Adi pagination from + size
// PROBLEM: from > 10000 → "deep pagination" yavaşlayır
// (hər node 10000 sort edib göndərir, sonra coordinator sort edir)

// SCROLL — point-in-time snapshot (data export üçün)
$response = $client->search([
    'index' => 'products',
    'scroll' => '1m',     // 1 dəq snapshot saxla
    'size'  => 100,
    'body'  => ['query' => ['match_all' => new \stdClass()]],
]);

while (count($response['hits']['hits']) > 0) {
    foreach ($response['hits']['hits'] as $hit) {
        // process $hit
    }
    
    $response = $client->scroll([
        'scroll_id' => $response['_scroll_id'],
        'scroll'    => '1m',
    ]);
}

// Cleanup
$client->clearScroll(['scroll_id' => $scrollId]);

// SEARCH_AFTER (modern, recommended)
// Sort field-ə görə cursor-based pagination
$response = $client->search([
    'index' => 'products',
    'body'  => [
        'size' => 100,
        'query' => ['match_all' => new \stdClass()],
        'sort' => [
            ['_score' => 'desc'],
            ['_id' => 'asc'],
        ],
    ],
]);

$lastSort = end($response['hits']['hits'])['sort'];

// Next page
$response = $client->search([
    'index' => 'products',
    'body'  => [
        'size' => 100,
        'search_after' => $lastSort,
        'sort' => [/* eyni sort */],
    ],
]);
```

---

## Index management

```php
<?php
// Index yaratma
$client->indices()->create([
    'index' => 'products',
    'body'  => [/* mapping */],
]);

// Index sil
$client->indices()->delete(['index' => 'products']);

// Mapping update
$client->indices()->putMapping([
    'index' => 'products',
    'body'  => [
        'properties' => [
            'new_field' => ['type' => 'keyword'],
        ],
    ],
]);

// Reindex (zero-downtime mapping change)
$client->reindex([
    'body' => [
        'source' => ['index' => 'products_v1'],
        'dest'   => ['index' => 'products_v2'],
    ],
    'wait_for_completion' => false,   // async
]);

// Alias — production zero-downtime
$client->indices()->putAlias([
    'index' => 'products_v1',
    'name'  => 'products',
]);

// Switch alias atomically
$client->indices()->updateAliases([
    'body' => [
        'actions' => [
            ['remove' => ['index' => 'products_v1', 'alias' => 'products']],
            ['add'    => ['index' => 'products_v2', 'alias' => 'products']],
        ],
    ],
]);
```

---

## Laravel Scout inteqrasiya

```bash
composer require laravel/scout
composer require babenkoivan/elastic-scout-driver
```

```php
<?php
// config/scout.php
'driver' => 'elastic',

// Model
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;
    
    public function toSearchableArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'category'    => $this->category,
            'price'       => $this->price,
        ];
    }
    
    // Custom index name
    public function searchableAs(): string
    {
        return 'products';
    }
}

// Index avtomatik (model created/updated/deleted)
$product = Product::create([...]);   // auto-index ES-də

// Manual reindex
php artisan scout:import "App\Models\Product"

// Search
$products = Product::search('gaming laptop')
    ->where('in_stock', true)
    ->take(20)
    ->get();
```

---

## Best practices

```
✓ Mapping explicit yaz (auto-mapping unpredictable)
✓ Filter context istifadə et (cache-lənir, score lazım deyilsə)
✓ Bulk index (1000 doc/request)
✓ Reindex production-da alias ilə
✓ Index aliases — version dəyişikliyi safe
✓ Refresh interval optimize (1s default → 30s ingestion-heavy üçün)
✓ Replica yalnız production-da (dev: 0)
✓ search_after > scroll > from/size (deep pagination)
✓ Aggregation cache (filter context)
✓ Query analyzer test et (_analyze API)

❌ Wildcard query başında (*foo) — full scan
❌ Deep pagination from=10000+
❌ Mapping dynamic mode default — type explosion riski
❌ Index per user/tenant (millions of indices = cluster crash)
❌ Hot/cold data eyni index-də (ILM lazımdır)
```

---

## İntervyu Sualları

- Elasticsearch ilə Postgres full-text search arasında fərq?
- `text` və `keyword` field tipləri arasındakı fərq?
- Analyzer nədir? Tokenizer + filter chain necə işləyir?
- `must` və `filter` arasındakı fərq nə vaxt vacibdir?
- Bulk insert niyə tək-tək insert-dən sürətlidir?
- Deep pagination problemi nədir?
- `scroll` və `search_after` arasında fərq?
- Reindex zero-downtime üçün alias necə istifadə olunur?
- Aggregation niyə "size: 0" ilə istifadə olunur?
- Shard və replica fərqi nədir?
- ILM (Index Lifecycle Management) nədir?
- Laravel Scout altında Elasticsearch necə işləyir?
