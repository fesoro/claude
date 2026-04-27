# Search Systems (Middle)

## İcmal

Search system istifadəçilərə böyük həcmli data içindən sürətli və relevantlı nəticələr
tapmağa imkan verən sistemdir. Full-text search, filtrlər, facets, autocomplete kimi
funksiyaları əhatə edir. Elasticsearch ən populyar açıq mənbəli search engine-dir.

Sadə dillə: kitabxananın kataloqu kimi düşünün - hər sözün hansı kitabda, hansı
səhifədə olduğu əvvəlcədən indeksləşdirilib, axtarış anında sürətli tapılır.

```
User Query: "php laravel tutorial"
        │
        ▼
┌──────────────────┐
│  Search Engine   │
│  (Elasticsearch) │
│                  │
│  1. Tokenize     │  "php", "laravel", "tutorial"
│  2. Analyze      │  lowercase, stemming
│  3. Lookup Index │  inverted index scan
│  4. Score        │  TF-IDF / BM25
│  5. Rank         │  relevance scoring
│  6. Return       │  top N results
└──────────────────┘
```


## Niyə Vacibdir

LIKE sorğuları böyük cədvəllərdə yavaş işləyir; tam mətn axtarışı üçün dedicated search engine lazımdır. E-commerce, SaaS, content platform — axtarış olmadan istifadəçi məhsulu tapa bilmir. Inverted index, tokenization, relevance scoring — search-in özəyi bunlardır.

## Əsas Anlayışlar

### Inverted Index

Hər söz hansı document-lərdə olduğunu göstərən data structure:

```
Documents:
  Doc1: "PHP is a programming language"
  Doc2: "Laravel is a PHP framework"
  Doc3: "Python is a programming language"

Inverted Index:
  "php"         → [Doc1, Doc2]
  "programming" → [Doc1, Doc3]
  "language"    → [Doc1, Doc3]
  "laravel"     → [Doc2]
  "framework"   → [Doc2]
  "python"      → [Doc3]
  "is"          → [Doc1, Doc2, Doc3]  (stop word - usually filtered)
```

### Text Analysis Pipeline

```
Input: "Running quickly through the FOREST"
  │
  ├─ Character Filter:  "Running quickly through the FOREST"
  ├─ Tokenizer:         ["Running", "quickly", "through", "the", "FOREST"]
  ├─ Lowercase Filter:  ["running", "quickly", "through", "the", "forest"]
  ├─ Stop Word Filter:  ["running", "quickly", "forest"]
  └─ Stemmer:           ["run", "quick", "forest"]
```

### Relevance Scoring (BM25)

```
BM25 Score = Σ IDF(qi) * (f(qi,D) * (k1+1)) / (f(qi,D) + k1 * (1 - b + b * |D|/avgdl))

Where:
  IDF(qi) = Inverse Document Frequency (nadir sözlər daha yüksək skor)
  f(qi,D) = Term frequency in document
  |D|     = Document length
  avgdl   = Average document length
  k1, b   = Tuning parameters
```

### Elasticsearch Architecture

```
┌─────────────────────────────────────────────────┐
│              Elasticsearch Cluster               │
│                                                  │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐        │
│  │  Node 1 │  │  Node 2 │  │  Node 3 │        │
│  │ (Master)│  │ (Data)  │  │ (Data)  │        │
│  │         │  │         │  │         │        │
│  │ Shard 0 │  │ Shard 1 │  │ Shard 2 │        │
│  │ (Primary)│ │(Primary)│  │(Primary)│        │
│  │         │  │         │  │         │        │
│  │ Shard 1 │  │ Shard 2 │  │ Shard 0 │        │
│  │(Replica)│  │(Replica)│  │(Replica)│        │
│  └─────────┘  └─────────┘  └─────────┘        │
└─────────────────────────────────────────────────┘
```

### Faceted Search

Nəticələri kateqoriyalara görə qruplaşdırma:

```
Search: "laptop"

Results: 150 products found

Facets:
  Brand:     Dell (45), HP (38), Lenovo (32), Apple (35)
  Price:     $0-500 (20), $500-1000 (65), $1000+ (65)
  RAM:       8GB (50), 16GB (70), 32GB (30)
  Rating:    4+ stars (90), 3+ stars (130)
```

## Arxitektura

### Search System Architecture

```
┌─────────┐     ┌──────────┐     ┌───────────────┐
│  App    │────▶│   API    │────▶│ Elasticsearch │
│ (Write) │     │  Layer   │     │   Cluster     │
└─────────┘     └──────────┘     └───────────────┘
     │                                    ▲
     │          ┌──────────┐              │
     └─────────▶│  Queue   │──────────────┘
      Change    │ (Index   │   Async index
      Events    │  Worker) │   updates
                └──────────┘

┌─────────┐     ┌──────────┐     ┌───────────────┐
│  App    │────▶│  Search  │────▶│ Elasticsearch │
│ (Read)  │     │   API    │     │   Cluster     │
└─────────┘     └──────────┘     └───────────────┘
```

## Nümunələr

### Laravel Scout with Elasticsearch

```php
// composer require laravel/scout
// composer require babenkoivan/elastic-scout-driver

// config/scout.php
return [
    'driver' => 'elastic',
    'prefix' => env('SCOUT_PREFIX', ''),
    'queue' => true,  // Async indexing
];

// app/Models/Product.php
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    public function searchableAs(): string
    {
        return 'products_index';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category->name,
            'brand' => $this->brand,
            'price' => $this->price,
            'rating' => $this->average_rating,
            'tags' => $this->tags->pluck('name')->toArray(),
            'in_stock' => $this->stock > 0,
            'created_at' => $this->created_at->timestamp,
        ];
    }

    // Index only active products
    public function shouldBeSearchable(): bool
    {
        return $this->status === 'active';
    }
}
```

### Elasticsearch Direct Integration

```php
// app/Services/SearchService.php
use Elastic\Elasticsearch\Client;

class SearchService
{
    public function __construct(private Client $client) {}

    public function search(string $query, array $filters = [], int $page = 1): array
    {
        $params = [
            'index' => 'products',
            'body' => [
                'from' => ($page - 1) * 20,
                'size' => 20,
                'query' => $this->buildQuery($query, $filters),
                'aggs' => $this->buildAggregations(),
                'highlight' => [
                    'fields' => [
                        'name' => ['number_of_fragments' => 0],
                        'description' => ['fragment_size' => 150],
                    ],
                ],
                'sort' => $this->buildSort($filters['sort'] ?? 'relevance'),
            ],
        ];

        $response = $this->client->search($params);

        return [
            'hits' => $this->formatHits($response['hits']),
            'total' => $response['hits']['total']['value'],
            'facets' => $this->formatAggregations($response['aggregations']),
        ];
    }

    private function buildQuery(string $query, array $filters): array
    {
        $must = [];
        $filter = [];

        // Full-text search
        $must[] = [
            'multi_match' => [
                'query' => $query,
                'fields' => ['name^3', 'description', 'tags^2', 'brand^2'],
                'type' => 'best_fields',
                'fuzziness' => 'AUTO',
            ],
        ];

        // Filters
        if (!empty($filters['category'])) {
            $filter[] = ['term' => ['category' => $filters['category']]];
        }
        if (!empty($filters['price_min'])) {
            $filter[] = ['range' => ['price' => ['gte' => $filters['price_min']]]];
        }
        if (!empty($filters['price_max'])) {
            $filter[] = ['range' => ['price' => ['lte' => $filters['price_max']]]];
        }
        if (!empty($filters['in_stock'])) {
            $filter[] = ['term' => ['in_stock' => true]];
        }

        return [
            'bool' => [
                'must' => $must,
                'filter' => $filter,
            ],
        ];
    }

    private function buildAggregations(): array
    {
        return [
            'categories' => [
                'terms' => ['field' => 'category', 'size' => 20],
            ],
            'brands' => [
                'terms' => ['field' => 'brand', 'size' => 20],
            ],
            'price_ranges' => [
                'range' => [
                    'field' => 'price',
                    'ranges' => [
                        ['to' => 50],
                        ['from' => 50, 'to' => 100],
                        ['from' => 100, 'to' => 500],
                        ['from' => 500],
                    ],
                ],
            ],
            'avg_rating' => [
                'avg' => ['field' => 'rating'],
            ],
        ];
    }

    private function buildSort(string $sort): array
    {
        return match ($sort) {
            'price_asc' => [['price' => 'asc']],
            'price_desc' => [['price' => 'desc']],
            'newest' => [['created_at' => 'desc']],
            'rating' => [['rating' => 'desc']],
            default => ['_score'],
        };
    }

    private function formatHits(array $hits): array
    {
        return array_map(fn ($hit) => [
            'id' => $hit['_source']['id'],
            'name' => $hit['_source']['name'],
            'score' => $hit['_score'],
            'highlight' => $hit['highlight'] ?? [],
            ...$hit['_source'],
        ], $hits['hits']);
    }
}
```

### Autocomplete / Suggest

```php
class AutocompleteService
{
    public function __construct(private Client $client) {}

    public function suggest(string $prefix): array
    {
        $response = $this->client->search([
            'index' => 'products',
            'body' => [
                'suggest' => [
                    'product_suggest' => [
                        'prefix' => $prefix,
                        'completion' => [
                            'field' => 'name_suggest',
                            'size' => 10,
                            'fuzzy' => ['fuzziness' => 1],
                        ],
                    ],
                ],
            ],
        ]);

        return collect($response['suggest']['product_suggest'][0]['options'])
            ->pluck('text')
            ->toArray();
    }
}

// Index mapping for autocomplete
// PUT /products
// {
//   "mappings": {
//     "properties": {
//       "name_suggest": {
//         "type": "completion"
//       }
//     }
//   }
// }
```

### Search Controller

```php
class SearchController extends Controller
{
    public function __construct(
        private SearchService $search,
        private AutocompleteService $autocomplete
    ) {}

    public function search(SearchRequest $request): JsonResponse
    {
        $results = $this->search->search(
            query: $request->validated('q'),
            filters: $request->validated('filters', []),
            page: $request->validated('page', 1)
        );

        return response()->json($results);
    }

    public function suggest(Request $request): JsonResponse
    {
        $suggestions = $this->autocomplete->suggest(
            $request->input('q', '')
        );

        return response()->json(['suggestions' => $suggestions]);
    }
}
```

## Real-World Nümunələr

1. **Google** - PageRank + inverted index, trillion+ documents
2. **Amazon** - Product search, faceted filtering, personalized ranking
3. **Spotify** - Music search, fuzzy matching for song/artist names
4. **Wikipedia** - Full-text search across millions of articles
5. **GitHub** - Code search across billions of lines of code

## Praktik Tapşırıqlar

**S1: Inverted index nədir və necə işləyir?**
C: Hər unique term-in hansı document-lərdə olduğunu göstərən data structure-dur.
Sözlər key, document ID-ləri value olur. Axtarış zamanı term-ə uyğun document
listi sürətlə tapılır. Traditional index-in əksidir (document → terms əvəzinə
term → documents).

**S2: TF-IDF vs BM25 fərqi nədir?**
C: TF-IDF sadə term frequency × inverse document frequency hesablayır. BM25
TF-IDF-in təkmilləşdirilmiş versiyasıdır - document length normalization,
term frequency saturation əlavə edir. Elasticsearch default olaraq BM25 istifadə edir.

**S3: Elasticsearch sharding nə üçün lazımdır?**
C: Data-nı bir neçə node arasında paylamaq üçün. Hər shard müstəqil Lucene index-dir.
Horizontal scaling təmin edir, parallel search mümkün edir. Primary shard write
qəbul edir, replica shard-lar read scaling və fault tolerance təmin edir.

**S4: Search relevance necə yaxşılaşdırılır?**
C: Field boosting (name^3), custom scoring functions, synonym analyzers,
stemming, fuzzy matching, user behavior signals (click-through rate),
A/B testing ilə ranking optimization.

**S5: Near real-time search nədir?**
C: Elasticsearch document index edildikdən 1 saniyə sonra axtarışda görünür.
Bu, refresh interval-a bağlıdır. True real-time deyil çünki Lucene segment
yaratmalıdır. Refresh interval azaldılsa performans düşür.

**S6: Böyük həcmli data-da search performansını necə artırarsınız?**
C: Proper shard sayı seçmək, field data type-larını optimize etmək, caching
(query cache, request cache), filter context istifadə etmək (scoring lazım
deyilsə), doc_values, routing ilə targeted search.

## Praktik Baxış

1. **Index Mapping Design** - Field type-larını düzgün seçin (keyword vs text)
2. **Analyzer Seçimi** - Language-specific analyzers istifadə edin
3. **Bulk Indexing** - Tək-tək əvəzinə batch indexing edin
4. **Alias İstifadəsi** - Zero-downtime reindexing üçün index alias
5. **Shard Sizing** - Hər shard 10-50 GB arası optimal
6. **Monitoring** - Cluster health, search latency, indexing rate track edin
7. **Async Indexing** - Queue vasitəsilə background-da index edin
8. **Filter vs Query** - Scoring lazım deyilsə filter context istifadə edin
9. **Pagination** - Deep pagination üçün search_after istifadə edin
10. **Mapping Explosion** - Dynamic mapping-dən qaçının, explicit mapping yazın


## Əlaqəli Mövzular

- [Typeahead](75-typeahead-autocomplete.md) — real-time axtarış sözündən öncə
- [Document Search](76-document-search-design.md) — Algolia kimi instant search
- [Caching](03-caching-strategies.md) — search nəticələrini cache etmək
- [Database Design](09-database-design.md) — full-text index strategiyası
- [Elasticsearch Internals](90-elasticsearch-internals.md) — dərin search engine mühərriki
