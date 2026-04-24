# Search Engines: Meilisearch, Algolia, Typesense, Sphinx

> **Seviyye:** Advanced ⭐⭐⭐

## Search Engine Nedir?

Full-text search ucun optimize olunmus xususi database. Inverted index ile isleyir - adi database-den qat-qat suretli text axtarisi verir.

**Niye database-in oz search-i yetmir?**
```sql
-- MySQL LIKE - full table scan, yavas
SELECT * FROM products WHERE name LIKE '%iphone%';

-- MySQL FULLTEXT - daha yaxsi amma limitli
SELECT * FROM products WHERE MATCH(name) AGAINST('iphone' IN BOOLEAN MODE);

-- Problemler:
-- Typo tolerance yoxdur ("iphne" → "iphone" tapmaz)
-- Relevance scoring primitiv
-- Faceted search yoxdur
-- Instant search (as-you-type) cetin
-- Boyuk data-da yavas
```

---

## Meilisearch

**Aciq-menbe, self-hosted, developer-friendly.** Rust ile yazilmis. Setup 5 deqiqe cekir.

### Xususiyyetler

- **Typo tolerance** - "iphne" yazsan "iphone" tapir
- **Instant search** - her keystroke-da netice (< 50ms)
- **Faceted search** - filter by category, price range
- **Geo search** - yaxinliqdaki mekanlar
- **Multi-language** - 30+ dil destegi
- **Simple API** - REST, SDK-lar (PHP, JS, Python)

### Qurasdirilma

```bash
# Docker ile
docker run -d --name meilisearch \
  -p 7700:7700 \
  -e MEILI_MASTER_KEY='masterKey123' \
  -v $(pwd)/meili_data:/meili_data \
  getmeili/meilisearch:v1.6

# curl ile test
curl http://localhost:7700/health
```

### Meilisearch CRUD

```bash
# Index yarat ve document elave et
curl -X POST 'http://localhost:7700/indexes/products/documents' \
  -H 'Authorization: Bearer masterKey123' \
  -H 'Content-Type: application/json' \
  --data '[
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "description": "Apple smartfonu",
      "category": "electronics",
      "brand": "Apple",
      "price": 1199,
      "rating": 4.8,
      "in_stock": true
    },
    {
      "id": 2,
      "name": "Samsung Galaxy S24",
      "description": "Samsung smartfonu",
      "category": "electronics",
      "brand": "Samsung",
      "price": 899,
      "rating": 4.6,
      "in_stock": true
    }
  ]'

# Search
curl 'http://localhost:7700/indexes/products/search' \
  -H 'Authorization: Bearer masterKey123' \
  --data '{ "q": "iphne pro", "limit": 10 }'
# "iphne" yazilsa da "iPhone 15 Pro" tapir (typo tolerance)

# Filterable attributes set et
curl -X PATCH 'http://localhost:7700/indexes/products/settings' \
  --data '{
    "filterableAttributes": ["category", "brand", "price", "in_stock"],
    "sortableAttributes": ["price", "rating"],
    "searchableAttributes": ["name", "description", "brand"]
  }'

# Faceted search
curl 'http://localhost:7700/indexes/products/search' \
  --data '{
    "q": "phone",
    "filter": "category = electronics AND price < 1000 AND in_stock = true",
    "sort": ["price:asc"],
    "facets": ["category", "brand"],
    "limit": 20
  }'
```

### Laravel ile Meilisearch (Scout)

```php
// composer require laravel/scout
// composer require meilisearch/meilisearch-php

// .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=masterKey123

// config/scout.php
'meilisearch' => [
    'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
    'key' => env('MEILISEARCH_KEY'),
],

// Model
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'brand' => $this->brand,
            'price' => (float) $this->price,
            'rating' => (float) $this->rating,
            'in_stock' => $this->stock > 0,
        ];
    }
}

// Axtaris
$results = Product::search('iphone pro')
    ->where('category', 'electronics')
    ->where('in_stock', true)
    ->paginate(20);

// Index settings (AppServiceProvider)
use MeiliSearch\Client;

public function boot()
{
    $client = app(Client::class);
    $client->index('products')->updateSettings([
        'filterableAttributes' => ['category', 'brand', 'price', 'in_stock'],
        'sortableAttributes' => ['price', 'rating', 'created_at'],
        'rankingRules' => [
            'words', 'typo', 'proximity', 'attribute', 'sort', 'exactness',
        ],
    ]);
}

// Data import
// php artisan scout:import "App\Models\Product"
```

---

## Algolia

**SaaS (hosted) search engine.** En populyar commercial search. Setup cox asandir amma bahalidir.

### Xususiyyetler

- **Hosted** - infrastructure idare etmek lazim deyil
- **< 1ms response time** - global CDN ile
- **AI-powered** - dynamic re-ranking, personalization
- **Analytics** - search analytics dashboard
- **A/B testing** - search relevance testleri
- **Recommend** - oxsar mehsul tovsiyesi

### Qiymeti

```
Free:    10K search/ay, 10K record
Essentials: $1/1K search requests
Pro:     Custom pricing
Enterprise: Custom

DİQQET: Boyuk scale-de bahalidir! 1M search/ay = ~$1000+
```

### Laravel ile Algolia

```php
// composer require laravel/scout
// composer require algolia/algoliasearch-client-php

// .env
SCOUT_DRIVER=algolia
ALGOLIA_APP_ID=your-app-id
ALGOLIA_SECRET=your-api-key

// Model (Scout ile - Meilisearch ile eyni interface!)
class Product extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price' => (float) $this->price,
            '_geoloc' => [  // Geo search
                'lat' => $this->latitude,
                'lng' => $this->longitude,
            ],
        ];
    }
}

// Axtaris - EYNI KOD, driver deyisir
$results = Product::search('iphone')->paginate(20);

// Algolia-ya xas: Faceted search
$results = Product::search('phone', function ($algolia, $query, $options) {
    $options['facets'] = ['category', 'brand'];
    $options['filters'] = 'price < 1000 AND in_stock:true';
    $options['hitsPerPage'] = 20;
    return $algolia->search($query, $options);
})->get();
```

### Algolia vs Self-Hosted ferqi

```
Algolia:
✅ Setup: 5 deqiqe, infrastructure yoxdur
✅ Global CDN, < 1ms response
✅ Analytics, A/B testing, AI ranking
❌ Baha (high volume-da)
❌ Data 3rd party-de saxlanilir
❌ Vendor lock-in

Self-Hosted (Meilisearch, ES):
✅ Pulusuz (open-source)
✅ Data oz serverinde
✅ Full control
❌ Infrastructure idare etmek lazim
❌ Scaling ozun etmelisen
```

---

## Typesense

**Aciq-menbe Algolia alternativi.** C++ ile yazilmis, cox suretlidir. Algolia-nin xususiyyetlerini pulsuz verir.

### Niye Typesense?

- Meilisearch-den daha yetkin (clustering, HA)
- Algolia-dan ucuz (self-hosted pulsuz, cloud da ucuz)
- Elasticsearch-den sadə (setup asandir)

### Qurasdirilma

```bash
# Docker
docker run -d --name typesense \
  -p 8108:8108 \
  -v $(pwd)/typesense-data:/data \
  typesense/typesense:27.1 \
  --data-dir=/data \
  --api-key=xyz123 \
  --enable-cors

# Cluster mode (production)
docker run typesense/typesense:27.1 \
  --nodes="/etc/typesense/nodes" \
  --api-key=xyz123
```

### Typesense Istifade

```bash
# Collection yarat (schema required - Meilisearch-den ferqli)
curl -X POST 'http://localhost:8108/collections' \
  -H 'X-TYPESENSE-API-KEY: xyz123' \
  --data '{
    "name": "products",
    "fields": [
      {"name": "name", "type": "string"},
      {"name": "description", "type": "string"},
      {"name": "category", "type": "string", "facet": true},
      {"name": "brand", "type": "string", "facet": true},
      {"name": "price", "type": "float"},
      {"name": "rating", "type": "float"},
      {"name": "in_stock", "type": "bool", "facet": true}
    ],
    "default_sorting_field": "rating"
  }'

# Document elave et
curl -X POST 'http://localhost:8108/collections/products/documents' \
  -H 'X-TYPESENSE-API-KEY: xyz123' \
  --data '{
    "id": "1",
    "name": "iPhone 15 Pro",
    "description": "Apple smartfonu",
    "category": "electronics",
    "brand": "Apple",
    "price": 1199,
    "rating": 4.8,
    "in_stock": true
  }'

# Search (multi-field, typo tolerant)
curl 'http://localhost:8108/collections/products/documents/search' \
  -H 'X-TYPESENSE-API-KEY: xyz123' \
  --data-urlencode 'q=iphne' \
  --data-urlencode 'query_by=name,description,brand' \
  --data-urlencode 'filter_by=category:=electronics && price:<1000' \
  --data-urlencode 'sort_by=price:asc' \
  --data-urlencode 'facet_by=category,brand' \
  --data-urlencode 'per_page=20'
```

### Laravel ile Typesense

```php
// composer require typesense/laravel-scout-typesense-driver

// config/scout.php
'driver' => 'typesense',
'typesense' => [
    'api_key' => env('TYPESENSE_API_KEY', 'xyz123'),
    'nodes' => [
        [
            'host' => env('TYPESENSE_HOST', 'localhost'),
            'port' => env('TYPESENSE_PORT', '8108'),
            'protocol' => 'http',
        ],
    ],
],

// Model
class Product extends Model
{
    use Searchable;

    // Typesense schema tanimlama
    public function getCollectionSchema(): array
    {
        return [
            'name' => $this->searchableAs(),
            'fields' => [
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'description', 'type' => 'string'],
                ['name' => 'category', 'type' => 'string', 'facet' => true],
                ['name' => 'price', 'type' => 'float'],
                ['name' => 'rating', 'type' => 'float'],
            ],
            'default_sorting_field' => 'rating',
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price' => (float) $this->price,
            'rating' => (float) $this->rating,
        ];
    }
}
```

---

## Sphinx

**En kohne aciq-menbe full-text search engine.** C++ ile yazilmis. MySQL/PostgreSQL ile birbaşa inteqrasiya. Hazirda az istifade olunur amma legacy proyektlerde gorunur.

### Xususiyyetler

```
✅ MySQL/PG ile native inteqrasiya (SphinxQL - SQL kimi syntax)
✅ Real-time index destegi
✅ Cox suretli (plain text search ucun)
✅ Kicik RAM istifadesi
❌ Kohne ecosystem, az development
❌ Limitli JSON/facet destegi
❌ Community kiclilmis
```

### SphinxQL (SQL-e oxsar)

```sql
-- Sphinx-e MySQL client ile qosuluruq (port 9306)
mysql -h 127.0.0.1 -P 9306

-- Search
SELECT id, WEIGHT() AS relevance
FROM products
WHERE MATCH('iphone pro')
ORDER BY relevance DESC
LIMIT 20;

-- Filter ile
SELECT * FROM products
WHERE MATCH('phone')
  AND category_id = 5
  AND price BETWEEN 100 AND 1000
ORDER BY price ASC;
```

### PHP ile Sphinx

```php
// SphinxQL (MySQL client ile)
$pdo = new PDO('mysql:host=127.0.0.1;port=9306');

$stmt = $pdo->prepare(
    "SELECT id, WEIGHT() as relevance
     FROM products
     WHERE MATCH(?)
     ORDER BY relevance DESC
     LIMIT 20"
);
$stmt->execute(['iphone pro']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Result ID-lerini al, esas datani MySQL-den cek
$ids = array_column($results, 'id');
$products = Product::whereIn('id', $ids)
    ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')
    ->get();
```

---

## Butun Search Engine-lerin Muqayisesi

### Esas Xususiyyetler

| Xususiyyet | Elasticsearch | Meilisearch | Algolia | Typesense | Sphinx |
|------------|--------------|-------------|---------|-----------|--------|
| **Lisenziya** | Open (SSPL) | Open (MIT) | SaaS | Open (GPL3) | Open (GPL2) |
| **Dil** | Java | Rust | - | C++ | C++ |
| **Hosting** | Self / Elastic Cloud | Self / Meili Cloud | SaaS only | Self / Typesense Cloud | Self only |
| **Typo tolerance** | Plugin ile | Native | Native | Native | Xeyr |
| **Instant search** | Hecbir sey deyil | < 50ms | < 1ms | < 5ms | Orta |
| **Faceted search** | Aggregations | Native | Native | Native | Limitli |
| **Geo search** | Beli | Beli | Beli | Beli | Beli |
| **Analytics** | Kibana | Xeyr | Native | Xeyr | Xeyr |
| **Clustering/HA** | Native | Xeyr (v1.x) | Managed | Native | Xeyr |
| **Laravel Scout** | Community | Official | Official | Community | Xeyr |

### Performance

| Metric | Elasticsearch | Meilisearch | Algolia | Typesense |
|--------|--------------|-------------|---------|-----------|
| **Index speed** | Orta | Suretli | Suretli | Suretli |
| **Search latency** | 5-50ms | 1-50ms | < 1ms | 1-10ms |
| **RAM istifadesi** | Yuksek | Orta | - | Asagi |
| **Max records** | Milliardlarla | Milyon | 100M+ | Milyon |
| **Complexity** | Yuksek | Asagi | Asagi | Orta |

### Qiymet

| Engine | Pulsuz tier | Paid |
|--------|------------|------|
| **Elasticsearch** | Self-host pulsuz | Elastic Cloud: $95/ay+ |
| **Meilisearch** | Self-host pulsuz | Meili Cloud: $30/ay+ |
| **Algolia** | 10K req/ay | $1/1K search |
| **Typesense** | Self-host pulsuz | Typesense Cloud: $30/ay+ |
| **Sphinx** | Pulsuz | Yoxdur |

## Hansi Search Engine-i Ne Vaxt Secmeli?

```
Sadece axtaris lazimdir, kicik proyekt?
└── Meilisearch (asan setup, developer-friendly)

Enterprise, managed, pul vermeye hazir?
└── Algolia (en asan, en suretli, SaaS)

Algolia xususiyyetleri amma pulsuz?
└── Typesense (open-source Algolia alternativi)

Log analysis, complex aggregations, boyuk data?
└── Elasticsearch (ELK stack, analytics)

Legacy PHP proyekt, MySQL inteqrasiya?
└── Sphinx (amma yeni proyektde istifade etme)

Sadece basit text search, elave engine istemirem?
└── PostgreSQL tsvector ve ya MySQL FULLTEXT
```

### Real-World Arxitektura

```
User types "iphne pro" →
    Frontend (InstantSearch.js) →
        Search Engine (Meilisearch/Algolia) →
            Returns: product IDs + highlights + facets

User clicks product →
    Backend (Laravel) →
        Primary DB (PostgreSQL) →
            Returns: full product data, stock, pricing

Sync:
    PostgreSQL → (Model Observer + Queue) → Meilisearch
    ve ya
    PostgreSQL → (Debezium CDC) → Elasticsearch
```

## Interview Suallari

1. **Niye database-in oz full-text search-i yetmir?**
   - Typo tolerance yoxdur, relevance scoring primitiv, faceted search cetin, instant search yavas. Search engine inverted index ile bunlari optimizasiya edir.

2. **Meilisearch ile Elasticsearch ferqi?**
   - Meilisearch: Sadə, suretli setup, typo tolerance native, kicik-orta proyektler. ES: Complex, boyuk data, log analytics, aggregations, ELK stack.

3. **Algolia ne vaxt tercih olunur?**
   - Infrastructure idaresi istemirsense, budget varsa, global CDN lazimdir, analytics/A/B testing lazimdir. Dezavantaj: bahadir, vendor lock-in.

4. **Search engine ile database nece sync olunur?**
   - 1) Model observer + queue (sadə). 2) CDC/Debezium (etibarli, real-time). 3) Periodic full reindex (sadə amma yavas).

5. **text vs keyword field nece isleyir?**
   - `text`: Tokenize olunur ("iPhone 15 Pro" → ["iphone", "15", "pro"]), full-text search ucun. `keyword`: Exact match ("iPhone 15 Pro" = "iPhone 15 Pro"), filter/sort ucun.

6. **Typesense Meilisearch-den nece ferqlidir?**
   - Typesense: Schema required, native clustering/HA, daha yetkin. Meilisearch: Schema-less, sadə API, HA hele yoxdur (v1.x), developer UX daha yaxsi.
