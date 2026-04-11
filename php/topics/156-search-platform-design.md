# System Design: Search Platform

## Mündəricat
1. [Tələblər](#tələblər)
2. [Search Arxitekturası](#search-arxitekturası)
3. [Relevance & Ranking](#relevance--ranking)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional (E-Commerce axtarış):
  Full-text search: "qırmızı telefon"
  Filter: qiymət aralığı, kateqoriya, brend
  Faceted search: filter sayları ("Telefon (1234)")
  Autocomplete / Suggestions
  Typo tolerance: "teflon" → "telefon"
  Synonyms: "mobil" = "telefon" = "smartphone"

Qeyri-funksional:
  Axtarış < 100ms
  10M məhsul
  100K sorğu/saniyə (peak)
  Near real-time index: yeni məhsul 1 dəqiqədə görünür

Ölçmə:
  Index ölçüsü: 10M × 2KB = 20GB
  Query peak: 100K/saniyə
```

---

## Search Arxitekturası

```
Index Architecture:
  Inverted Index: sözdən → document ID-ləri
  "telefon" → [doc1, doc5, doc100, ...]
  "qırmızı" → [doc3, doc5, doc99, ...]
  
  "qırmızı telefon" → intersection: [doc5, ...]

Elasticsearch/OpenSearch cluster:
  Sharding: 10M doc → 5 shard × 2M doc
  Replication: 3 replica (HA + read scaling)
  
  ┌──────────────────────────────────────┐
  │         Coordinator Node             │
  │  (sorğu qəbul, shard-lara yay)       │
  └──────────────────┬───────────────────┘
                     │ scatter-gather
         ┌───────────┼────────────┐
         │           │            │
  ┌──────▼──┐  ┌─────▼──┐  ┌─────▼──┐
  │ Shard 1 │  │ Shard 2│  │ Shard 3│
  │(Primary)│  │(Primary│  │(Primary│
  │+ Replica│  │+Replica│  │+Replica│
  └─────────┘  └────────┘  └────────┘

Index Update Strategy:
  DB change → Kafka → Index Worker → Elasticsearch
  Near real-time: ~30 saniyə gecikmə
```

---

## Relevance & Ranking

```
TF-IDF (Term Frequency-Inverse Document Frequency):
  TF: sözün sənəddə neçə dəfə keçdiyi
  IDF: sözün bütün sənədlərdə nadirliysi
  
  "telefon" ümumi söz → aşağı IDF
  "Xiaomi 13T Pro" spesifik → yüksək IDF

BM25 (Elasticsearch default):
  TF-IDF-in inkişaf etmiş variantı
  Uzun sənədlərə normalızasiya

Boosting:
  title: weight 3 (məhsul adı daha vacibdir)
  description: weight 1
  brand: weight 2

Business Logic:
  Sponsored products: boost
  High stock: boost
  Low rating: penalize
  Out of stock: rank lower/hide

Learning to Rank (LTR):
  ML model: user click data ilə öyrənir
  Hansı nəticəni user seçir → signal
  Model ranking-i personallaşdırır
```

---

## PHP İmplementasiyası

```php
<?php
// Elasticsearch PHP client
// composer require elasticsearch/elasticsearch

use Elastic\Elasticsearch\ClientBuilder;

class ProductSearchService
{
    private \Elastic\Elasticsearch\Client $es;
    private string $index = 'products';

    public function __construct()
    {
        $this->es = ClientBuilder::create()
            ->setHosts(['elasticsearch:9200'])
            ->build();
    }

    public function search(SearchQuery $query): SearchResult
    {
        $esQuery = $this->buildQuery($query);

        $response = $this->es->search([
            'index' => $this->index,
            'body'  => $esQuery,
        ]);

        return $this->mapResponse($response);
    }

    private function buildQuery(SearchQuery $query): array
    {
        $must  = [];
        $filter = [];

        // Full-text search
        if ($query->getText()) {
            $must[] = [
                'multi_match' => [
                    'query'  => $query->getText(),
                    'fields' => [
                        'title^3',        // Title 3x boost
                        'brand^2',        // Brand 2x boost
                        'description',    // Normal weight
                        'category',
                    ],
                    'fuzziness' => 'AUTO', // Typo tolerance
                    'type'      => 'best_fields',
                ],
            ];
        }

        // Filter: qiymət aralığı
        if ($query->getMinPrice() || $query->getMaxPrice()) {
            $range = [];
            if ($query->getMinPrice()) $range['gte'] = $query->getMinPrice();
            if ($query->getMaxPrice()) $range['lte'] = $query->getMaxPrice();
            $filter[] = ['range' => ['price' => $range]];
        }

        // Filter: kateqoriya
        if ($query->getCategory()) {
            $filter[] = ['term' => ['category_id' => $query->getCategory()]];
        }

        // In-stock filter
        $filter[] = ['term' => ['in_stock' => true]];

        return [
            'query' => [
                'function_score' => [
                    'query' => [
                        'bool' => [
                            'must'   => $must,
                            'filter' => $filter,
                        ],
                    ],
                    // Business logic boosting
                    'functions' => [
                        // Sponsorluq boost
                        ['filter' => ['term' => ['is_sponsored' => true]], 'weight' => 2],
                        // Yüksək rating boost
                        ['field_value_factor' => ['field' => 'rating', 'factor' => 0.1]],
                    ],
                    'boost_mode' => 'multiply',
                ],
            ],
            // Facets (aggregations)
            'aggs' => [
                'categories' => ['terms' => ['field' => 'category_id', 'size' => 20]],
                'brands'     => ['terms' => ['field' => 'brand.keyword', 'size' => 20]],
                'price_range' => ['histogram' => ['field' => 'price', 'interval' => 100]],
            ],
            'from'    => ($query->getPage() - 1) * $query->getSize(),
            'size'    => $query->getSize(),
            'suggest' => [
                'did_you_mean' => [
                    'text'   => $query->getText(),
                    'phrase' => ['field' => 'title', 'size' => 1],
                ],
            ],
        ];
    }
}
```

```php
<?php
// Index Worker — DB → Elasticsearch sync
class ProductIndexWorker
{
    public function __construct(
        private ProductRepository $products,
        private \Elastic\Elasticsearch\Client $es,
    ) {}

    public function handle(ProductUpdatedEvent $event): void
    {
        $product = $this->products->findById($event->productId);

        if ($product === null) {
            // Silinib → index-dən sil
            $this->es->delete(['index' => 'products', 'id' => $event->productId]);
            return;
        }

        // Upsert
        $this->es->index([
            'index' => 'products',
            'id'    => $product->getId(),
            'body'  => [
                'title'        => $product->getTitle(),
                'description'  => $product->getDescription(),
                'brand'        => $product->getBrand(),
                'category_id'  => $product->getCategoryId(),
                'category'     => $product->getCategoryName(),
                'price'        => $product->getPrice(),
                'rating'       => $product->getRating(),
                'in_stock'     => $product->isInStock(),
                'is_sponsored' => $product->isSponsored(),
                'updated_at'   => $product->getUpdatedAt()->format(\DateTime::ATOM),
            ],
        ]);
    }

    public function bulkReindex(\DateTimeImmutable $since): void
    {
        $page = 1;

        do {
            $products = $this->products->findUpdatedSince($since, $page, 1000);

            if (empty($products)) break;

            // Bulk API — çox daha sürətli
            $body = [];
            foreach ($products as $product) {
                $body[] = ['index' => ['_index' => 'products', '_id' => $product->getId()]];
                $body[] = $this->toDocument($product);
            }

            $this->es->bulk(['body' => $body]);
            $page++;

        } while (count($products) === 1000);
    }
}
```

---

## İntervyu Sualları

- Inverted index nədir? Necə işləyir?
- Typo tolerance axtarışda necə tətbiq edilir?
- Faceted search (filter sayları) necə effektiv şəkildə hesablanır?
- DB ilə search index sinxronizasiyasında gecikmə problemi necə həll edilir?
- TF-IDF nədir? BM25-dən fərqi nədir?
- Search relevance ML-ə ehtiyac olmadan necə yaxşılaşdırıla bilər?
