# Search Architecture (Elasticsearch) (Senior)

## Mündəricat
1. [Inverted Index](#inverted-index)
2. [Analyzers](#analyzers)
3. [Relevance Scoring (BM25)](#relevance-scoring-bm25)
4. [Index vs Query Time Analysis](#index-vs-query-time-analysis)
5. [Elasticsearch vs DB Full-Text Search](#elasticsearch-vs-db-full-text-search)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Inverted Index

```
Normal index: document → words
Inverted index: word → documents

Sənədlər:
  Doc 1: "PHP is great for web"
  Doc 2: "PHP and Python are popular"
  Doc 3: "Web development with Python"

Inverted Index:
  "php"        → [Doc1, Doc2]
  "is"         → [Doc1]
  "great"      → [Doc1]
  "web"        → [Doc1, Doc3]
  "and"        → [Doc2]
  "python"     → [Doc2, Doc3]
  "popular"    → [Doc2]
  "development"→ [Doc3]
  "with"       → [Doc3]

Query: "PHP web"
  "php" → [Doc1, Doc2]
  "web" → [Doc1, Doc3]
  Intersection + scoring → Doc1 (hər ikisi var → ən yüksək skor)

Niyə sürətli:
  SELECT * FROM docs WHERE content LIKE '%php%'
  → Full table scan: O(n * m)

  Inverted index:
  → Hash lookup: O(1)
  → Posting list merge: O(k) — k = match count
```

---

## Analyzers

```
Analyzer — mətnin token-lərə parçalanmasını idarə edir.
Index zamanı VƏ query zamanı tətbiq olunur.

Analyzer = Char Filter + Tokenizer + Token Filters

Char Filter:
  HTML remove: "<b>PHP</b>" → "PHP"
  Synonym mapping: "PHP8" → "PHP 8"

Tokenizer:
  standard: Boşluqla/durğu işarəsi ilə böl
  "PHP is great!" → ["PHP", "is", "great"]
  
  whitespace: Yalnız boşluqla böl
  ngram: "PHP" → ["P", "PH", "PHP", "H", "HP", "P"]
  edge_ngram: "PHP" → ["P", "PH", "PHP"] (prefix)

Token Filters:
  lowercase:    "PHP" → "php"
  stop words:   "is", "the", "a" → sil
  stemming:     "running" → "run", "runs" → "run"
  synonyms:     "laptop" = "notebook"
  ascii_folding: "café" → "cafe"

Default analyzer ("standard"):
  1. Tokenize (boşluq + durğu)
  2. Lowercase
  3. Stop words (opsional)

Custom analyzer nümunəsi:
  Azərbaycan mətni üçün:
  Stemmer yoxdur → custom token filter lazımdır
```

---

## Relevance Scoring (BM25)

```
BM25 (Best Match 25) — Elasticsearch default scoring.
TF-IDF-in yaxşılaşdırılmış versiyası.

TF (Term Frequency):
  Söz sənəddə neçə dəfə keçir?
  Daha çox keçirsə → daha relevant?
  Amma çox keçmə faydası azalır (saturation)

IDF (Inverse Document Frequency):
  Söz neçə sənəddə keçir?
  Nadir söz → daha informativ → yüksək IDF
  "PHP" çox sənəddə → aşağı IDF
  "Fibonacci" az sənəddə → yüksək IDF

BM25 formulası (sadələşdirilmiş):
  score = IDF × (TF × (k1+1)) / (TF + k1 × (1 - b + b × fieldLen/avgFieldLen))

  k1: TF saturation (default 1.2)
  b:  field length normalization (default 0.75)
      b=0: uzun sənədə üstünlük yoxdur
      b=1: tam length normalize

Praktik nümunə:
  Query: "PHP framework"
  Doc A: "PHP framework PHP PHP PHP" (5x PHP)
  Doc B: "PHP framework best practices"
  
  BM25: Doc B daha yüksək skor (TF saturation!)
  TF-IDF: Doc A daha yüksək (5x PHP çox sayılır)
```

---

## Index vs Query Time Analysis

```
Index time (yazarkən):
  Sənədi indexləyərkən analyzer tətbiq olunur.
  Nəticə inverted index-ə yazılır.
  
  "Running fast" → [running, fast] (stem: run, fast)
  ya da
  "Running fast" → ["run", "fast"] (stemmed)

Query time (axtararkən):
  Query string-i eyni analyzer-dən keçir.
  Sonra inverted index-dən axtarır.
  
  query: "runs" → analyzer → "run" → index-dəki "run" ilə uyğun!

Vacib qayda:
  Index və query analyzer EYNİ OLMALIDIR!
  Əgər index-də "running" → "run" saxlandısa
  query-də "running" yazılırsa → "running" axtarır → tapılmır!
  
  Query analyzer da "running" → "run" çevirərsə → tapılır!

Multi-fields:
  Eyni sahəni fərqli analyzer-lərlə saxla:
  "title": "PHP Framework Guide"
  "title.keyword": "PHP Framework Guide"  (analyzed yox, exact match)
  "title.english": "php framework guid"   (english stemmer)
```

---

## Elasticsearch vs DB Full-Text Search

```
PostgreSQL Full-Text Search:
  tsvector + tsquery
  CREATE INDEX ON docs USING GIN(to_tsvector('english', content));
  
  ✓ DB-nin içindədir — ayrı infrastruktur yoxdur
  ✓ Transactions ilə real-time
  ✓ Simple use case üçün yetərli
  
  ✗ Scoring məhduddur
  ✗ Faceted search yoxdur
  ✗ Suggestion/autocomplete yoxdur
  ✗ Böyük həcmdə yavaşlayır
  ✗ Multi-language analyzer zəifdir

Elasticsearch:
  ✓ Güclü scoring (BM25, custom)
  ✓ Facets, aggregations (filter by category, range)
  ✓ Autocomplete (edge_ngram, completion suggester)
  ✓ Fuzzy search (typo tolerance)
  ✓ Multi-language analyzer
  ✓ Horizontal scale (shard-based)
  ✓ Near real-time (1 saniyə gecikmə)
  
  ✗ Ayrı infrastruktur
  ✗ DB ilə sync (eventual consistency)
  ✗ ACID deyil
  ✗ Operational complexity

Nə vaxt Elasticsearch:
  ✓ 1M+ sənəd
  ✓ Faceted search (filter by brand, price, category)
  ✓ Autocomplete
  ✓ Relevance tuning
  ✓ Log/event search (ELK Stack)
```

---

## PHP İmplementasiyası

```php
<?php
// Elasticsearch PHP client (elastic/elasticsearch-php)
use Elastic\Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()
    ->setHosts(['localhost:9200'])
    ->build();

// Index mapping (schema) yaratmaq
$params = [
    'index' => 'products',
    'body'  => [
        'mappings' => [
            'properties' => [
                'title' => [
                    'type'   => 'text',
                    'analyzer' => 'standard',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],  // exact match
                    ],
                ],
                'description' => ['type' => 'text'],
                'price'       => ['type' => 'float'],
                'category'    => ['type' => 'keyword'],   // facets üçün
                'in_stock'    => ['type' => 'boolean'],
                'created_at'  => ['type' => 'date'],
            ],
        ],
    ],
];
$client->indices()->create($params);
```

```php
<?php
// Faceted search query
class ProductSearchService
{
    public function search(SearchQuery $query): SearchResult
    {
        $params = [
            'index' => 'products',
            'body'  => [
                'query' => [
                    'bool' => [
                        'must'   => $this->buildMust($query),
                        'filter' => $this->buildFilter($query),
                    ],
                ],
                'aggs' => [
                    'categories' => [
                        'terms' => ['field' => 'category', 'size' => 20],
                    ],
                    'price_range' => [
                        'range' => [
                            'field'  => 'price',
                            'ranges' => [
                                ['to' => 50],
                                ['from' => 50, 'to' => 200],
                                ['from' => 200],
                            ],
                        ],
                    ],
                ],
                'highlight' => [
                    'fields' => ['title' => new \stdClass(), 'description' => new \stdClass()],
                ],
                'from' => $query->offset(),
                'size' => $query->limit(),
            ],
        ];

        $response = $this->client->search($params);
        return SearchResult::fromElasticsearch($response->asArray());
    }

    private function buildMust(SearchQuery $query): array
    {
        if (!$query->hasText()) return [['match_all' => new \stdClass()]];

        return [[
            'multi_match' => [
                'query'  => $query->text(),
                'fields' => ['title^3', 'description'],  // title 3x boost
                'type'   => 'best_fields',
                'fuzziness' => 'AUTO',  // typo tolerance
            ],
        ]];
    }

    private function buildFilter(SearchQuery $query): array
    {
        $filters = [['term' => ['in_stock' => true]]];

        if ($query->hasCategory()) {
            $filters[] = ['term' => ['category' => $query->category()]];
        }

        if ($query->hasPriceRange()) {
            $filters[] = ['range' => ['price' => [
                'gte' => $query->minPrice(),
                'lte' => $query->maxPrice(),
            ]]];
        }

        return $filters;
    }
}
```

```php
<?php
// DB → Elasticsearch sync (Observer pattern)
class ProductElasticsearchObserver
{
    public function __construct(
        private Client $elasticsearch,
    ) {}

    public function saved(Product $product): void
    {
        $this->elasticsearch->index([
            'index' => 'products',
            'id'    => $product->id,
            'body'  => [
                'title'       => $product->name,
                'description' => $product->description,
                'price'       => $product->price,
                'category'    => $product->category->slug,
                'in_stock'    => $product->stock > 0,
            ],
        ]);
    }

    public function deleted(Product $product): void
    {
        $this->elasticsearch->delete([
            'index' => 'products',
            'id'    => $product->id,
        ]);
    }
}
```

---

## İntervyu Sualları

- Inverted index nədir? DB LIKE-dan niyə sürətlidir?
- Analyzer-in üç hissəsini (char filter, tokenizer, token filter) izah edin.
- Index time ilə query time analyzer-lərinin eyni olmaması nə kimi problemlərə yol açar?
- BM25 TF-IDF-dən nə ilə fərqlənir? "TF saturation" nədir?
- Faceted search (kateqoriya + qiymət filteri) Elasticsearch-də necə edilir?
- PostgreSQL full-text search-dən Elasticsearch-ə nə vaxt keçərdiniz?
- DB-Elasticsearch sync eventual consistency — necə idarə edərsiniz?
