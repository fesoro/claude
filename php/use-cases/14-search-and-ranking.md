# Search and Ranking Sistemi Dizaynı

## Problem Statement

E-commerce platformunda məhsul axtarışı var. "laptop" axtaranda 5000 nəticə gəlir — hansı birinci görünəcək? Sadə text match kifayət deyil. Populyar məhsullar, istifadəçinin keçmişi, qiymət, stok, click-through rate — bunların hamısı rankingə təsir etməlidir. Üstəlik A/B test ilə ranking algoritmini sınaqdan keçirmək lazımdır.

---

## Ranking Faktorları

| Faktor | Ağırlıq | İzah |
|---|---|---|
| Text relevance | Yüksək | Axtarış sözünün məhsul adı/təsvirindəki uyğunluğu |
| CTR (Click-through rate) | Orta-Yüksək | Axtarışda göstərilən/klik edilən nisbəti |
| Purchase rate | Yüksək | Bu məhsul bu sorğudan neçə dəfə alınıb |
| Popularity | Orta | Ümumi satış sayı, baxış sayı |
| Recency | Aşağı-Orta | Yeni məhsulları bir qədər boost et |
| Stock | Yüksək | Stokda olmayan məhsulu aşağı düşür |
| User personalization | Orta | User-in keçmiş baxış/alışlarına görə |
| Price competitiveness | Aşağı | Aşağı qiymətli məhsulları bir qədər üstünlük ver |

---

## 1. Elasticsearch Mapping

*Bu kod məhsul axtarışı üçün synonym analyzer, text və keyword sahələri ilə Elasticsearch mapping-ini göstərir:*

```json
PUT /products
{
  "mappings": {
    "properties": {
      "id":          { "type": "integer" },
      "name":        { "type": "text", "analyzer": "standard",
                       "fields": { "keyword": { "type": "keyword" } } },
      "description": { "type": "text", "analyzer": "standard" },
      "category":    { "type": "keyword" },
      "brand":       { "type": "keyword" },
      "price":       { "type": "float" },
      "stock":       { "type": "integer" },
      "status":      { "type": "keyword" },
      "created_at":  { "type": "date" },
      "popularity_score":  { "type": "float" },
      "ctr_score":         { "type": "float" },
      "purchase_rate":     { "type": "float" },
      "tags":        { "type": "keyword" },
      "synonyms":    { "type": "text", "analyzer": "synonym_analyzer" }
    }
  },
  "settings": {
    "analysis": {
      "analyzer": {
        "synonym_analyzer": {
          "tokenizer": "standard",
          "filter": ["lowercase", "product_synonyms"]
        }
      },
      "filter": {
        "product_synonyms": {
          "type": "synonym",
          "synonyms": [
            "laptop, notebook, computer",
            "telefon, mobil, smartfon",
            "tv, televizor, ekran"
          ]
        }
      }
    }
  }
}
```

---

## 2. SearchService — Ranking Pipeline

*Bu kod spell check, synonym genişlənməsi, A/B test və analytics loglama ilə tam axtarış pipeline-ni göstərir:*

```php
// app/Services/Search/SearchService.php
<?php

namespace App\Services\Search;

use App\Services\Search\Ranking\RankingPipeline;
use App\Services\Search\Analytics\SearchAnalyticsLogger;
use App\Services\Search\ABTest\SearchABTestResolver;
use Illuminate\Support\Facades\Log;

class SearchService
{
    public function __construct(
        private ElasticsearchClient     $elasticsearch,
        private RankingPipeline         $rankingPipeline,
        private SearchAnalyticsLogger   $analytics,
        private SearchABTestResolver    $abTestResolver,
        private SpellChecker            $spellChecker,
        private SynonymExpander         $synonymExpander
    ) {}

    public function search(SearchRequest $request): SearchResult
    {
        $startTime = microtime(true);

        // A/B test hansı ranking variant-ı istifadə edəcəyini müəyyən et
        $rankingVariant = $this->abTestResolver->resolveVariant($request->userId);

        // Sorğunu normalize et
        $normalizedQuery = $this->normalizeQuery($request->query);

        // Spell correction
        $correctedQuery = $this->spellChecker->correct($normalizedQuery);
        $didYouMean = $correctedQuery !== $normalizedQuery ? $correctedQuery : null;

        // Synonym expansion
        $expandedQuery = $this->synonymExpander->expand($normalizedQuery);

        // Elasticsearch query qur
        $esQuery = $this->rankingPipeline->buildQuery(
            query:   $expandedQuery,
            filters: $request->filters,
            userId:  $request->userId,
            variant: $rankingVariant
        );

        $rawResults = $this->elasticsearch->search($esQuery);

        // Zero results handling
        if ($rawResults->totalHits === 0 && $didYouMean) {
            $request = $request->withQuery($correctedQuery);
            return $this->search($request); // Düzəldilmiş sorğu ilə yenidən axtar
        }

        $duration = (microtime(true) - $startTime) * 1000;

        // Search analytics-ə yaz
        $searchLogId = $this->analytics->logSearch(
            query:    $request->query,
            userId:   $request->userId,
            variant:  $rankingVariant,
            results:  $rawResults->hits,
            duration: $duration
        );

        return new SearchResult(
            hits:        $rawResults->hits,
            total:       $rawResults->totalHits,
            facets:      $rawResults->aggregations,
            didYouMean:  $didYouMean,
            searchLogId: $searchLogId,
            variant:     $rankingVariant,
            durationMs:  $duration
        );
    }

    private function normalizeQuery(string $query): string
    {
        $query = mb_strtolower(trim($query));
        $query = preg_replace('/\s+/', ' ', $query);
        return $query;
    }
}
```

---

## 3. RankingPipeline — function_score Query

*Bu kod popularlik, CTR, purchase rate, recency, stok və user personalization faktorlarını birləşdirən function_score ranking query-sini göstərir:*

```php
// app/Services/Search/Ranking/RankingPipeline.php
<?php

namespace App\Services\Search\Ranking;

class RankingPipeline
{
    public function __construct(
        private UserBehaviorScoreProvider $behaviorProvider,
        private PersonalizationProvider  $personalizationProvider
    ) {}

    public function buildQuery(
        string  $query,
        array   $filters,
        ?int    $userId,
        string  $variant = 'default'
    ): array {
        $weights = $this->getWeights($variant);

        // User personalization score-larını əvvəlcədən yüklə
        $userBoosts = $userId
            ? $this->personalizationProvider->getCategoryBoosts($userId)
            : [];

        return [
            'query' => [
                'function_score' => [
                    'query' => [
                        'bool' => [
                            'must'   => $this->buildTextQuery($query),
                            'filter' => $this->buildFilters($filters),
                        ],
                    ],
                    'functions' => array_filter([
                        // 1. Popularlik
                        [
                            'field_value_factor' => [
                                'field'    => 'popularity_score',
                                'factor'   => $weights['popularity'],
                                'modifier' => 'log1p',
                                'missing'  => 0,
                            ],
                        ],
                        // 2. CTR signal
                        [
                            'field_value_factor' => [
                                'field'    => 'ctr_score',
                                'factor'   => $weights['ctr'],
                                'modifier' => 'sqrt',
                                'missing'  => 0,
                            ],
                        ],
                        // 3. Purchase rate
                        [
                            'field_value_factor' => [
                                'field'    => 'purchase_rate',
                                'factor'   => $weights['purchase_rate'],
                                'modifier' => 'sqrt',
                                'missing'  => 0,
                            ],
                        ],
                        // 4. Recency boost — yeni məhsullar bir qədər üstündür
                        [
                            'gauss' => [
                                'created_at' => [
                                    'origin' => 'now',
                                    'scale'  => '30d',  // 30 günlük yarım-ömür
                                    'decay'  => 0.5,
                                ],
                            ],
                            'weight' => $weights['recency'],
                        ],
                        // 5. Stok penalty — az stok varsa aşağı düşür
                        [
                            'script_score' => [
                                'script' => [
                                    'source' => "
                                        if (doc['stock'].value == 0) return 0.1;
                                        if (doc['stock'].value < 5) return 0.7;
                                        return 1.0;
                                    ",
                                ],
                            ],
                            'weight' => $weights['stock'],
                        ],
                        // 6. User personalization — kateqoriya boost
                        $userId && !empty($userBoosts)
                            ? $this->buildPersonalizationFunction($userBoosts, $weights['personalization'])
                            : null,
                    ]),
                    'score_mode'  => 'multiply',
                    'boost_mode'  => 'multiply',
                    'min_score'   => 0.1,
                ],
            ],
            'aggs'    => $this->buildFacetAggregations($filters),
            'from'    => ($filters['page'] ?? 1 - 1) * ($filters['per_page'] ?? 20),
            'size'    => $filters['per_page'] ?? 20,
            'highlight' => [
                'fields' => [
                    'name'        => ['number_of_fragments' => 0],
                    'description' => ['number_of_fragments' => 1, 'fragment_size' => 150],
                ],
            ],
        ];
    }

    private function buildTextQuery(string $query): array
    {
        return [
            'multi_match' => [
                'query'  => $query,
                'fields' => [
                    'name^4',         // Ad 4x çox ağırlıq
                    'name.keyword^3', // Exact match
                    'brand^2',
                    'tags^2',
                    'description',
                ],
                'type'      => 'best_fields',
                'fuzziness' => 'AUTO',  // Typo tolerance
                'minimum_should_match' => '70%',
            ],
        ];
    }

    private function buildFilters(array $filters): array
    {
        $clauses = [['term' => ['status' => 'active']]];

        if (!empty($filters['category'])) {
            $clauses[] = ['term' => ['category' => $filters['category']]];
        }

        if (!empty($filters['min_price']) || !empty($filters['max_price'])) {
            $range = [];
            if (!empty($filters['min_price'])) $range['gte'] = $filters['min_price'];
            if (!empty($filters['max_price'])) $range['lte'] = $filters['max_price'];
            $clauses[] = ['range' => ['price' => $range]];
        }

        if (!empty($filters['brands'])) {
            $clauses[] = ['terms' => ['brand' => $filters['brands']]];
        }

        if ($filters['in_stock'] ?? false) {
            $clauses[] = ['range' => ['stock' => ['gt' => 0]]];
        }

        return $clauses;
    }

    private function buildPersonalizationFunction(array $categoryBoosts, float $weight): array
    {
        $categoryFilters = array_map(fn($cat, $boost) => [
            'filter' => ['term' => ['category' => $cat]],
            'weight' => $boost,
        ], array_keys($categoryBoosts), array_values($categoryBoosts));

        return [
            'script_score' => [
                'script' => ['source' => '1.0'],
            ],
            'filter' => ['match_all' => new \stdClass()],
            'weight' => $weight,
        ];
    }

    private function buildFacetAggregations(array $filters): array
    {
        return [
            'categories' => ['terms' => ['field' => 'category', 'size' => 20]],
            'brands'     => ['terms' => ['field' => 'brand', 'size' => 30]],
            'price_ranges' => [
                'range' => [
                    'field'  => 'price',
                    'ranges' => [
                        ['to' => 50],
                        ['from' => 50, 'to' => 200],
                        ['from' => 200, 'to' => 500],
                        ['from' => 500],
                    ],
                ],
            ],
        ];
    }

    private function getWeights(string $variant): array
    {
        return match ($variant) {
            'popularity_heavy' => [
                'popularity'      => 2.0,
                'ctr'             => 1.0,
                'purchase_rate'   => 1.5,
                'recency'         => 0.3,
                'stock'           => 1.0,
                'personalization' => 1.0,
            ],
            'personalization_heavy' => [
                'popularity'      => 1.0,
                'ctr'             => 1.0,
                'purchase_rate'   => 1.5,
                'recency'         => 0.5,
                'stock'           => 1.0,
                'personalization' => 3.0,
            ],
            default => [
                'popularity'      => 1.2,
                'ctr'             => 1.3,
                'purchase_rate'   => 1.5,
                'recency'         => 0.5,
                'stock'           => 1.0,
                'personalization' => 1.5,
            ],
        };
    }
}
```

---

## 4. UserBehaviorTracker — Clicks, Views, Purchases

*Bu kod axtarış click/impression/purchase siqnallarını Redis-də real-time izləyən, CTR score hesablayan service-i göstərir:*

```php
// app/Services/Search/Analytics/UserBehaviorTracker.php
<?php

namespace App\Services\Search\Analytics;

use App\Models\SearchBehaviorEvent;
use Illuminate\Support\Facades\Redis;

class UserBehaviorTracker
{
    private const WINDOW_DAYS = 30;

    /**
     * Məhsula klik qeyd et
     */
    public function trackClick(int $userId, int $productId, string $searchLogId, int $position): void
    {
        // Async — queue-ya göndər, request-i gecikdirmə
        dispatch(new \App\Jobs\TrackBehaviorEvent([
            'type'          => 'click',
            'user_id'       => $userId,
            'product_id'    => $productId,
            'search_log_id' => $searchLogId,
            'position'      => $position,
            'occurred_at'   => now(),
        ]));

        // Redis-də real-time CTR counter
        $key = "product_ctr:{$productId}";
        Redis::hincrby($key, 'clicks', 1);
        Redis::expire($key, 86400 * 7); // 7 günlük TTL
    }

    /**
     * Məhsulun axtarışda göründüyünü qeyd et (impression)
     */
    public function trackImpression(int $productId, string $searchLogId): void
    {
        $key = "product_ctr:{$productId}";
        Redis::hincrby($key, 'impressions', 1);
        Redis::expire($key, 86400 * 7);
    }

    /**
     * Alış qeyd et — ən güclü signal
     */
    public function trackPurchase(int $userId, int $productId, ?string $searchLogId = null): void
    {
        dispatch(new \App\Jobs\TrackBehaviorEvent([
            'type'          => 'purchase',
            'user_id'       => $userId,
            'product_id'    => $productId,
            'search_log_id' => $searchLogId,
            'occurred_at'   => now(),
        ]));
    }

    /**
     * CTR score-unu hesabla — Elasticsearch-ə yazılacaq
     */
    public function getCtrScore(int $productId): float
    {
        $data = Redis::hgetall("product_ctr:{$productId}");

        if (empty($data) || ($data['impressions'] ?? 0) === 0) {
            // DB-dən yüklə
            return $this->calculateCtrFromDb($productId);
        }

        $impressions = (int) ($data['impressions'] ?? 0);
        $clicks      = (int) ($data['clicks'] ?? 0);

        // Laplace smoothing — az impression-lu məhsullar üçün
        return ($clicks + 1) / ($impressions + 10);
    }

    /**
     * User-in son 30 gündə klik etdiyi kateqoriyalara görə boost
     */
    public function getUserCategoryAffinities(int $userId): array
    {
        $cacheKey = "user_affinities:{$userId}";

        return \Cache::remember($cacheKey, 3600, function () use ($userId) {
            $events = SearchBehaviorEvent::where('user_id', $userId)
                ->where('type', 'in', ['click', 'purchase'])
                ->where('occurred_at', '>=', now()->subDays(self::WINDOW_DAYS))
                ->with('product:id,category_id')
                ->get();

            $categoryScores = [];
            foreach ($events as $event) {
                $category = $event->product?->category_id;
                if (!$category) continue;

                $weight = $event->type === 'purchase' ? 3.0 : 1.0;
                $categoryScores[$category] = ($categoryScores[$category] ?? 0) + $weight;
            }

            // Normalize — max score = 2.0
            if (!empty($categoryScores)) {
                $max = max($categoryScores);
                $categoryScores = array_map(fn($s) => ($s / $max) * 2.0, $categoryScores);
            }

            arsort($categoryScores);
            return array_slice($categoryScores, 0, 10, true); // Top 10
        });
    }

    private function calculateCtrFromDb(int $productId): float
    {
        $data = SearchBehaviorEvent::where('product_id', $productId)
            ->where('occurred_at', '>=', now()->subDays(7))
            ->selectRaw("
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks
            ")
            ->first();

        if (!$data || $data->impressions == 0) return 0.1;

        return ($data->clicks + 1) / ($data->impressions + 10);
    }
}
```

---

## 5. A/B Testing on Ranking

*Bu kod user ID-yə görə deterministik variant təyin edən, mövcud assignment-i yoxlayan A/B test resolver-i göstərir:*

```php
// app/Services/Search/ABTest/SearchABTestResolver.php
<?php

namespace App\Services\Search\ABTest;

use App\Models\ABTestAssignment;

class SearchABTestResolver
{
    private array $activeTests = [
        'ranking_v2' => [
            'variants'    => ['default', 'popularity_heavy', 'personalization_heavy'],
            'weights'     => [60, 20, 20],   // %60 default, %20 hər yeni variant
            'start_date'  => '2024-01-01',
            'end_date'    => '2024-02-01',
        ],
    ];

    public function resolveVariant(?int $userId): string
    {
        if (!$userId) return 'default';

        $test = $this->activeTests['ranking_v2'] ?? null;
        if (!$test) return 'default';

        // Mövcud assignment varmı?
        $assignment = ABTestAssignment::where('user_id', $userId)
            ->where('test_name', 'ranking_v2')
            ->first();

        if ($assignment) {
            return $assignment->variant;
        }

        // Yeni assignment yarat — user ID-yə görə deterministik
        $variant = $this->assignVariant($userId, $test);

        ABTestAssignment::create([
            'user_id'   => $userId,
            'test_name' => 'ranking_v2',
            'variant'   => $variant,
        ]);

        return $variant;
    }

    private function assignVariant(int $userId, array $test): string
    {
        // User ID-yə görə hash — eyni user həmişə eyni varianta düşər
        $hash      = crc32("ranking_v2_{$userId}") % 100;
        $cumulative = 0;

        foreach ($test['variants'] as $i => $variant) {
            $cumulative += $test['weights'][$i];
            if ($hash < $cumulative) {
                return $variant;
            }
        }

        return $test['variants'][0];
    }
}
```

*return $test['variants'][0]; üçün kod nümunəsi:*
```php
// A/B test nəticələrini analiz et
// app/Services/Search/ABTest/ABTestAnalyzer.php
<?php

namespace App\Services\Search\ABTest;

class ABTestAnalyzer
{
    public function analyzeTest(string $testName, string $metric = 'ctr'): array
    {
        $results = \DB::table('search_logs as sl')
            ->join('ab_test_assignments as ata', function ($join) use ($testName) {
                $join->on('ata.user_id', '=', 'sl.user_id')
                     ->where('ata.test_name', $testName);
            })
            ->leftJoin('search_behavior_events as sbe', function ($join) {
                $join->on('sbe.search_log_id', '=', 'sl.id')
                     ->where('sbe.type', 'click');
            })
            ->select(
                'ata.variant',
                \DB::raw('COUNT(DISTINCT sl.id) as searches'),
                \DB::raw('COUNT(sbe.id) as clicks'),
                \DB::raw('COUNT(sbe.id) / COUNT(DISTINCT sl.id) as ctr')
            )
            ->groupBy('ata.variant')
            ->get();

        return [
            'test_name' => $testName,
            'metric'    => $metric,
            'results'   => $results,
            'winner'    => $results->sortByDesc('ctr')->first()?->variant,
        ];
    }
}
```

---

## 6. Spell Correction / "Did You Mean?"

*6. Spell Correction / "Did You Mean?" üçün kod nümunəsi:*
```php
// app/Services/Search/SpellChecker.php
<?php

namespace App\Services\Search;

class SpellChecker
{
    public function __construct(
        private ElasticsearchClient $elasticsearch
    ) {}

    public function correct(string $query): string
    {
        $response = $this->elasticsearch->suggest([
            'query_correction' => [
                'text'   => $query,
                'phrase' => [
                    'field'          => 'name',
                    'size'           => 1,
                    'gram_size'      => 3,
                    'direct_generator' => [[
                        'field'          => 'name',
                        'suggest_mode'   => 'missing',
                        'min_word_length' => 3,
                    ]],
                    'highlight' => [
                        'pre_tag'  => '<em>',
                        'post_tag' => '</em>',
                    ],
                ],
            ],
        ]);

        $suggestions = $response['query_correction'][0]['options'] ?? [];

        if (empty($suggestions)) {
            return $query;
        }

        return $suggestions[0]['text'];
    }
}
```

---

## 7. Search Analytics Logger

*7. Search Analytics Logger üçün kod nümunəsi:*
```php
// app/Services/Search/Analytics/SearchAnalyticsLogger.php
<?php

namespace App\Services\Search\Analytics;

use App\Models\SearchLog;

class SearchAnalyticsLogger
{
    public function logSearch(
        string  $query,
        ?int    $userId,
        string  $variant,
        array   $results,
        float   $duration
    ): int {
        $log = SearchLog::create([
            'query'        => $query,
            'user_id'      => $userId,
            'ab_variant'   => $variant,
            'result_count' => count($results),
            'top_result_ids' => array_slice(array_column($results, 'id'), 0, 5),
            'duration_ms'  => round($duration, 2),
            'is_zero_result' => empty($results),
        ]);

        // Zero results — analiz üçün vacibdir
        if (empty($results)) {
            \Log::channel('search')->info('Zero result query', [
                'query'   => $query,
                'user_id' => $userId,
            ]);
        }

        return $log->id;
    }

    /**
     * Ən çox axtarılan sorğular
     */
    public function getTopQueries(int $days = 7, int $limit = 50): array
    {
        return SearchLog::where('created_at', '>=', now()->subDays($days))
            ->select('query', \DB::raw('COUNT(*) as count'))
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Nəticəsiz axtarışlar — bunlar üçün content ya synonym lazımdır
     */
    public function getZeroResultQueries(int $days = 7): array
    {
        return SearchLog::where('is_zero_result', true)
            ->where('created_at', '>=', now()->subDays($days))
            ->select('query', \DB::raw('COUNT(*) as count'))
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit(100)
            ->get()
            ->toArray();
    }
}
```

---

## 8. Elasticsearch Score-larını Yeniləmək

*8. Elasticsearch Score-larını Yeniləmək üçün kod nümunəsi:*
```php
// app/Console/Commands/UpdateProductScoresCommand.php
// Scheduled: hər gecə işlədilir

<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Search\Analytics\UserBehaviorTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class UpdateProductScoresCommand extends Command
{
    protected $signature   = 'search:update-scores';
    protected $description = 'Elasticsearch-dakı məhsul score-larını yenilə';

    public function handle(UserBehaviorTracker $tracker): void
    {
        $this->info('Məhsul score-ları yenilənir...');

        Product::chunk(500, function ($products) use ($tracker) {
            $batch = Bus::batch(
                $products->map(fn($p) => new \App\Jobs\UpdateProductScoreJob($p, $tracker))
                         ->all()
            )->allowFailures()->dispatch();

            $this->info("Batch dispatched: {$batch->id}");
        });

        $this->info('Bütün batch-lər dispatch edildi.');
    }
}
```

*$this->info('Bütün batch-lər dispatch edildi.'); üçün kod nümunəsi:*
```php
// app/Jobs/UpdateProductScoreJob.php
<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Search\Analytics\UserBehaviorTracker;
use App\Services\Search\ElasticsearchClient;

class UpdateProductScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product
    ) {}

    public function handle(UserBehaviorTracker $tracker, ElasticsearchClient $es): void
    {
        $ctrScore      = $tracker->getCtrScore($this->product->id);
        $popularityScore = $this->calculatePopularityScore($this->product);

        $es->update('products', $this->product->id, [
            'doc' => [
                'ctr_score'        => $ctrScore,
                'popularity_score' => $popularityScore,
                'stock'            => $this->product->stock,  // Stoku da yenilə
            ],
        ]);
    }

    private function calculatePopularityScore(Product $product): float
    {
        // Son 30 gündə satış sayına görə
        $salesCount = \DB::table('order_items')
            ->where('product_id', $product->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return log10($salesCount + 1); // Logaritmik scale
    }
}
```

---

## 9. Controller — Search Endpoint

*9. Controller — Search Endpoint üçün kod nümunəsi:*
```php
// app/Http/Controllers/SearchController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest as SearchHttpRequest;
use App\Services\Search\SearchService;
use App\Services\Search\Analytics\UserBehaviorTracker;
use App\Services\Search\SearchRequestDTO;

class SearchController extends Controller
{
    public function __construct(
        private SearchService        $searchService,
        private UserBehaviorTracker  $behaviorTracker
    ) {}

    public function index(SearchHttpRequest $request)
    {
        $searchRequest = SearchRequestDTO::fromRequest($request);

        $result = $this->searchService->search($searchRequest);

        // Impression-ları track et (async)
        if ($result->searchLogId && !empty($result->hits)) {
            foreach ($result->hits as $hit) {
                $this->behaviorTracker->trackImpression($hit['id'], $result->searchLogId);
            }
        }

        return response()->json([
            'data'         => $result->hits,
            'total'        => $result->total,
            'facets'       => $result->facets,
            'did_you_mean' => $result->didYouMean,
            'search_log_id' => $result->searchLogId,
            'meta' => [
                'duration_ms' => $result->durationMs,
                'variant'     => app()->environment('production') ? null : $result->variant,
            ],
        ]);
    }

    /**
     * Klik event-i qeyd et
     */
    public function trackClick(SearchHttpRequest $request)
    {
        $request->validate([
            'product_id'    => 'required|integer',
            'search_log_id' => 'required|string',
            'position'      => 'required|integer',
        ]);

        $this->behaviorTracker->trackClick(
            userId:      $request->user()?->id,
            productId:   $request->product_id,
            searchLogId: $request->search_log_id,
            position:    $request->position
        );

        return response()->json(['status' => 'tracked']);
    }
}
```

---

## 10. Migration

*10. Migration üçün kod nümunəsi:*
```php
Schema::create('search_logs', function (Blueprint $table) {
    $table->id();
    $table->string('query', 500);
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('ab_variant', 50)->nullable();
    $table->integer('result_count')->default(0);
    $table->json('top_result_ids')->nullable();
    $table->float('duration_ms')->nullable();
    $table->boolean('is_zero_result')->default(false);
    $table->timestamps();

    $table->index(['query', 'created_at']);
    $table->index(['user_id', 'created_at']);
    $table->index('is_zero_result');
});

Schema::create('search_behavior_events', function (Blueprint $table) {
    $table->id();
    $table->string('type'); // click, impression, purchase
    $table->unsignedBigInteger('user_id')->nullable();
    $table->unsignedBigInteger('product_id');
    $table->string('search_log_id')->nullable();
    $table->integer('position')->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();

    $table->index(['product_id', 'type', 'occurred_at']);
    $table->index(['user_id', 'type', 'occurred_at']);
});

Schema::create('ab_test_assignments', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('test_name');
    $table->string('variant');
    $table->timestamps();

    $table->unique(['user_id', 'test_name']);
});
```

---

## İntervyu Sualları

**S: Learning to Rank (LTR) nədir?**
C: ML əsaslı ranking metodu. Training data: hər (query, product) cütü üçün relevance label (clicked=1, purchased=2, ignored=0). Model bu labellərə görə öyrənir. Elasticsearch-in LTR plugin-i var. Manual function_score-dan daha dəqiq, amma daha mürəkkəb. Yeterli data olmadan işləmir.

**S: CTR-ı ranking siqnalı kimi istifadə etməyin problemi nədir?**
C: Position bias — ilk nəticələr həmişə daha çox klik alır, çünki yuxarıdadır. Həll: position-adjusted CTR (hər position üçün ayrıca baseline). Popularity bias — populyar məhsullar daha çox göründüyü üçün daha çox klik alır, yeni məhsullar şans almır.

**S: Elasticsearch vs PostgreSQL full-text search fərqi?**
C: PostgreSQL-in tsvector/tsquery mövcuddur, kiçik dataset üçün kifayətdir. Elasticsearch: distributed, horizontal scaling, advanced analysis (synonym, stemming), relevance scoring, faceted search, aggregations daha güclüdür. >1M document-dən sonra ES üstündür.

**S: Zero results handling necə işləyir?**
C: Birinci spell correction cəhd et. Olmasa — query-ni relaxing et (AND → OR). Olmasa — kategoriya bazında fallback göstər. Analytics-ə yaz — bu sorğular üçün content ya synonym əlavə etmək lazımdır.

**S: Synonym-ləri hardakı yönetirsiniz, Elasticsearch-dəmi, application layer-dəmi?**
C: İkisi birlikdə. ES-in synonym filter-i axtarış zamanı (search time) istifadə edilir. Application layer-da query expansion da edilə bilər. Admin panel-dən idarə olunan synonym-lər DB-də saxlanılıb ES-ə yüklənir (index reopen lazımdır).

**S: A/B test nəticəsini nə zaman significant hesab edirsiniz?**
C: Statistical significance — p-value < 0.05. Praktik əhəmiyyət — ən azı %2-3 CTR fərqi. Minimum sample size — hər variant üçün 1000+ axtarış. Chi-squared test ya da t-test istifadə edilir. Daha yaxşısı: Bayesian A/B testing — daha az data ilə qərar vermək üçün.

**S: Cold start problemi axtarışda nədir, necə həll olunur?**
C: Yeni məhsul əlavə edilib, heç CTR ya da purchase data yoxdur — ranking score-ları sıfırdır, heç göstərilmir, heç data toplana bilmir. Həll: Laplace smoothing (CTR hesabında +1/+10 əlavə et — sıfır bölməni önlər, yeni məhsula minimum score verir). Temporal boost: yeni məhsullar üçün `recency` ağırlığını artır (ilk 30 gün). Editorial boost: kateqoriya admini yeni məhsulları manual ön plana çıxara bilər. Bundan əlavə: demographic similarity — eyni kateqoriyadakı oxşar məhsulların score-larından interpolasiya et.

**S: Elasticsearch-in `function_score` query-si necə işləyir?**
C: Əsas relevance score (text match) üzərinə çarpma ya da toplama yolu ilə əlavə faktorlar tətbiq edir. `field_value_factor`: sütunun dəyərini score-a çevir. `gauss/exp/linear`: zamanla azalan score (recency). `script_score`: custom Painless script. `score_mode: multiply` — bütün funksiyaların nəticəsini vur. `boost_mode: multiply` — query score-u ilə function score-u vur. Diqqət: script_score hər sənəd üçün işlədiyi üçün yavaşdır, yalnız az sayda sənədə tətbiq et (post_filter ilə).

**S: Search-də "query understanding" nədir?**
C: İstifadəçi niyyətini (intent) müəyyən etmək. "iphone 15 qiymət" → informational intent (məhsul səhifəsinə yönləndir). "iphone 15 al" → transactional intent (checkout-a yönləndir). NLP ilə entity extraction: brand, model, attribute-ları ayır. Query rewriting: "ucuz laptop" → `price < 500 AND category = laptop`. Laravel-də: spesifik token-ləri aşkar edən rule-based sistemi ya da ML model (OpenAI embedding-ləri ilə semantic search).

---

## Anti-patterns

**1. Ranking-i hardcode etmək**
`ORDER BY price ASC` — tək kriterliyə görə sıralama relevance-ı nəzərə almır. Multi-factor scoring (text match + popularity + recency) lazımdır.

**2. İndeks olmadan full-text search**
`WHERE description LIKE '%laptop%'` — full table scan, milyonluq cədvəldə saniyələrlə gözləmə. FULLTEXT index yaxud Elasticsearch mütləqdir.

**3. Position bias-ı nəzərə almadan CTR-i siqnal kimi istifadə**
İlk nəticə həmişə daha çox klik alır — position advantage-i olmadan raw CTR yanıltıcıdır. Position-adjusted CTR hesablanmalıdır.

**4. Real-time ES sync**
Hər DB update-da synchronous ES index — write latency artır, ES bottleneck olur. Queue-based async indexing (observer → job → batch ES update) daha performanslıdır.

**5. Axtarış analitikasını toplamamaq**
Zero-result queries, click positions, abandonment rate izlənmilmirsə — search quality-ni ölçmək mümkün deyil. Bu datalar olmadan ranking tuning körə olur.
