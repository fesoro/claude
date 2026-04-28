# Product Recommendation Engine (Lead)

## Problem
- E-commerce: 100k product, 1M user, 10M order history
- "You may also like" widget hər product page-də
- "Recommended for you" homepage
- < 100ms latency
- Personalized + cold start üçün fallback

---

## Həll: Hybrid (collaborative + content + popularity)

```
Strategy:
  1. Item-based collaborative filtering (precomputed daily)
     "Bu məhsulu alanlar X-i də aldı"
  2. Content-based (real-time)
     Eyni kateqoriya, oxşar feature
  3. Popularity (cold start fallback)
     "Trending today"
  4. User personalization
     "Sənin like history-nə uyğun"

Pipeline:
  Daily batch (Spark/Airflow) → similarity matrix → Redis
  Real-time API → fetch from Redis (< 5ms)
```

---

## 1. Item-based collaborative filtering (offline)

```python
# Spark/Python ilə daily compute (PHP-də edə bilərsiniz, amma slow)
# Output: product_similarity table

# Algorithm: Cosine similarity over co-purchase matrix
# Matrix M: rows=users, cols=products, M[u][p] = bought count

# Similarity: cos(p1, p2) = M[:][p1] · M[:][p2] / (|M[:][p1]| × |M[:][p2]|)
```

```sql
-- Result table (daily refreshed)
CREATE TABLE product_similarity (
    product_id BIGINT,
    similar_product_id BIGINT,
    similarity FLOAT,            -- 0-1
    co_purchase_count INT,
    PRIMARY KEY (product_id, similar_product_id),
    INDEX (product_id, similarity DESC)
);
```

```php
<?php
// PHP-də compute (small dataset üçün)
class ItemSimilarityComputer
{
    public function compute(): void
    {
        $products = Product::all()->keyBy('id');
        
        // Co-purchase matrix
        $coPurchase = DB::table('order_items')
            ->select(['oi1.product_id as p1', 'oi2.product_id as p2', DB::raw('COUNT(*) as co_count')])
            ->from('order_items as oi1')
            ->join('order_items as oi2', 'oi1.order_id', '=', 'oi2.order_id')
            ->whereColumn('oi1.product_id', '<', 'oi2.product_id')
            ->groupBy(['oi1.product_id', 'oi2.product_id'])
            ->having('co_count', '>=', 5)   // min support
            ->get();
        
        // Save
        DB::table('product_similarity')->truncate();
        
        foreach ($coPurchase->chunk(1000) as $chunk) {
            $rows = $chunk->flatMap(function ($row) {
                $sim = $this->jaccardSimilarity($row->p1, $row->p2);
                return [
                    ['product_id' => $row->p1, 'similar_product_id' => $row->p2,
                     'similarity' => $sim, 'co_purchase_count' => $row->co_count],
                    ['product_id' => $row->p2, 'similar_product_id' => $row->p1,
                     'similarity' => $sim, 'co_purchase_count' => $row->co_count],
                ];
            })->toArray();
            
            DB::table('product_similarity')->insert($rows);
        }
        
        // Top 20 similar per product → Redis cache
        Product::chunk(1000, function ($products) {
            foreach ($products as $product) {
                $similar = DB::table('product_similarity')
                    ->where('product_id', $product->id)
                    ->orderByDesc('similarity')
                    ->limit(20)
                    ->pluck('similar_product_id')
                    ->toArray();
                
                Redis::setex(
                    "product:similar:{$product->id}",
                    86400,
                    json_encode($similar)
                );
            }
        });
    }
    
    private function jaccardSimilarity(int $p1, int $p2): float
    {
        $u1 = $this->buyersOf($p1);
        $u2 = $this->buyersOf($p2);
        
        $intersection = count(array_intersect($u1, $u2));
        $union = count(array_unique(array_merge($u1, $u2)));
        
        return $union > 0 ? $intersection / $union : 0;
    }
    
    private function buyersOf(int $productId): array
    {
        return Cache::remember(
            "product:buyers:$productId",
            3600,
            fn() => DB::table('order_items')
                ->where('product_id', $productId)
                ->pluck('user_id')
                ->toArray()
        );
    }
}

// Schedule
Schedule::command('recommendations:compute')->dailyAt('03:00');
```

---

## 2. Content-based (real-time)

```php
<?php
class ContentBasedRecommender
{
    public function similar(Product $product, int $limit = 10): Collection
    {
        return Cache::remember(
            "content:similar:{$product->id}",
            3600,
            function () use ($product, $limit) {
                return Product::where('id', '!=', $product->id)
                    ->where('category_id', $product->category_id)
                    ->where('brand_id', $product->brand_id)
                    ->where(DB::raw('ABS(price - ?)', [$product->price]), '<=', $product->price * 0.3)
                    ->orderByDesc('rating')
                    ->limit($limit)
                    ->get();
            }
        );
    }
    
    // Tag/feature overlap
    public function similarByTags(Product $product, int $limit = 10): Collection
    {
        $tags = $product->tags()->pluck('id')->toArray();
        if (empty($tags)) return collect();
        
        return Product::where('id', '!=', $product->id)
            ->whereHas('tags', fn($q) => $q->whereIn('tags.id', $tags), '>=', 2)
            ->withCount(['tags as matching_tags' => fn($q) => $q->whereIn('tags.id', $tags)])
            ->orderByDesc('matching_tags')
            ->orderByDesc('rating')
            ->limit($limit)
            ->get();
    }
}
```

---

## 3. Personalized (user history)

```php
<?php
class PersonalizedRecommender
{
    public function forUser(User $user, int $limit = 20): Collection
    {
        // 1. User-in son baxdığı/aldığı product-lar
        $recentProducts = $user->orderItems()
            ->orderByDesc('created_at')
            ->limit(20)
            ->pluck('product_id')
            ->toArray();
        
        if (empty($recentProducts)) {
            return $this->fallbackPopular($limit);
        }
        
        // 2. Hər biri üçün similar product-lar yığ
        $candidates = [];
        foreach ($recentProducts as $pid) {
            $similar = json_decode(Redis::get("product:similar:$pid") ?? '[]', true);
            foreach ($similar as $simId) {
                $candidates[$simId] = ($candidates[$simId] ?? 0) + 1;
            }
        }
        
        // 3. Already bought-ları çıxart
        foreach ($recentProducts as $pid) {
            unset($candidates[$pid]);
        }
        
        // 4. Top N by score
        arsort($candidates);
        $topIds = array_slice(array_keys($candidates), 0, $limit);
        
        return Product::whereIn('id', $topIds)
            ->orderByRaw("FIELD(id, " . implode(',', $topIds) . ")")
            ->get();
    }
    
    private function fallbackPopular(int $limit): Collection
    {
        return Cache::remember('popular:today', 600, function () use ($limit) {
            return Product::orderByDesc('order_count_24h')
                ->limit($limit)
                ->get();
        });
    }
}
```

---

## 4. Controller endpoint

```php
<?php
class RecommendationController
{
    public function similar(Product $product, ItemSimilarityRecommender $rec): JsonResponse
    {
        $ids = json_decode(Redis::get("product:similar:{$product->id}") ?? '[]', true);
        
        if (empty($ids)) {
            // Fallback: content-based
            $similar = app(ContentBasedRecommender::class)->similar($product, 10);
        } else {
            $similar = Product::whereIn('id', $ids)
                ->orderByRaw("FIELD(id, " . implode(',', $ids) . ")")
                ->limit(10)
                ->get();
        }
        
        return response()->json($similar);
    }
    
    public function forYou(Request $req, PersonalizedRecommender $rec): JsonResponse
    {
        $user = $req->user();
        if (!$user) {
            $popular = Cache::remember('popular:today', 600, fn() => 
                Product::orderByDesc('order_count_24h')->limit(20)->get()
            );
            return response()->json($popular);
        }
        
        $recommended = Cache::remember(
            "user:recommendations:{$user->id}",
            1800,
            fn() => $rec->forUser($user, 20)
        );
        
        return response()->json($recommended);
    }
}
```

---

## 5. Cold start (new user/product)

```php
<?php
class ColdStartHandler
{
    public function newUser(): array
    {
        // Heç bir history yox → trending product
        return Cache::remember('trending:today', 600, function () {
            return DB::table('order_items')
                ->select('product_id', DB::raw('COUNT(*) as cnt'))
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('product_id')
                ->orderByDesc('cnt')
                ->limit(20)
                ->pluck('product_id')
                ->toArray();
        });
    }
    
    public function newProduct(Product $product): array
    {
        // Yeni product, similarity yox → content-based
        return app(ContentBasedRecommender::class)
            ->similar($product, 10)
            ->pluck('id')
            ->toArray();
    }
}
```

---

## 6. A/B test framework

```php
<?php
// İki algorithm-i müqayisə
class RecommendationABTest
{
    public function getRecommendations(User $user, int $limit = 20): array
    {
        $variant = Feature::for($user)->value('reco-algorithm');
        
        $results = match ($variant) {
            'collaborative' => app(PersonalizedRecommender::class)->forUser($user, $limit),
            'neural'        => app(NeuralRecommender::class)->forUser($user, $limit),
            'hybrid'        => app(HybridRecommender::class)->forUser($user, $limit),
            default         => app(ColdStartHandler::class)->newUser(),
        };
        
        // Track impression
        Analytics::track('recommendation.shown', [
            'user_id' => $user->id,
            'variant' => $variant,
            'product_ids' => collect($results)->pluck('id')->toArray(),
        ]);
        
        return $results;
    }
}

// Click tracking → conversion rate per variant
class TrackClickController
{
    public function track(Request $req): JsonResponse
    {
        $variant = Feature::for($req->user())->value('reco-algorithm');
        
        Analytics::track('recommendation.clicked', [
            'user_id'    => $req->user()->id,
            'product_id' => $req->product_id,
            'variant'    => $variant,
            'position'   => $req->position,
        ]);
        
        return response()->noContent();
    }
}

// SQL: variant CTR
// SELECT variant,
//        SUM(clicks) / SUM(impressions) AS ctr
// FROM events GROUP BY variant
```

---

## 7. Pitfalls

```
❌ Real-time matrix factorization → çox CPU
   ✓ Offline batch (daily) + Redis serve

❌ Cold-start ignore → yeni user fail
   ✓ Multi-strategy fallback chain

❌ Recommendation echo chamber (eyni kateqoriyadan)
   ✓ Diversity penalty (already-shown bonus negative)

❌ Stale similarity (köhnə data)
   ✓ Daily refresh (recent N month data)

❌ Privacy (user history bütün target-lərdə görünür)
   ✓ Anonymized aggregation, GDPR compliant

❌ Sequential bias (UI sırası — top item çox click alır)
   ✓ Position weight in CTR analysis

❌ Filter bubble — yalnız oxşar göstər
   ✓ Random "explore" slot (10%)
```

---

## 8. Performance

```
Latency:
  Similar (Redis cache):       2ms
  Personalized (cached):       3ms
  Content-based fallback:      15ms (DB query)
  
Storage (Redis):
  100k product × 20 similar IDs × 8 bytes = 16 MB
  1M user × 20 reco × 8 bytes = 160 MB
  
Daily compute (offline):
  10M order × similarity = ~30 min on Spark cluster
  PHP single-node: 4-8 saat (small dataset üçün OK)
```

---

## Problem niyə yaranır?

"Ən populyar məhsulları göstər" yanaşması ilk baxışda sadə görünür, amma real mühitdə işləmir. Birinci problem odur ki, bütün istifadəçilərə eyni siyahı göstərilir — aktiv alıcıya da, yeni qeydiyyatdan keçmiş ziyarətçiyə də. Bu məhsullar ümumi populyarlığa əsaslanır, istifadəçinin real maraqlarını əks etdirmir. Nəticədə click-through rate (CTR) aşağı düşür, conversion rate isə əhəmiyyətli dərəcədə azalır. Amazon-un tədqiqatlarına görə, personalized recommendation-lar ümumi populyarlıq siyahısına nisbətən 20-35% daha yüksək konversiya göstərir.

**Cold start problemi** texniki baxımdan belə özünü göstərir: yeni istifadəçinin heç bir purchase history-si, browse history-si, ya da rating-i yoxdur. Collaborative filtering tamamilə bu data üzərində işləyir — "bu məhsulu alan digər istifadəçilər X məhsulunu da aldı" prinsipi ilə. Yeni istifadəçi üçün isə bu "digər istifadəçilər" kimi müəyyən etmək mümkün deyil, çünki user hələ heç bir əməliyyat etməyib. Content-based filtering də tam işləmir — istifadəçinin hansı xüsusiyyətləri (kateqoriya, qiymət aralığı, brend) sevdiyi bilinmir. Bu boşluq cold start problem adlanır, yeni product-lar üçün də (item cold start) eyni vəziyyət yaranır.

**Real-time collaborative filtering-in niyə bahalı olduğu** isə ayrı bir texniki problemdir. Cosine similarity hesablamaq üçün bütün user-product matriksini nəzərə almaq lazımdır. 1M user və 100k product üçün bu matrix 100 milyard elementdən ibarətdir. Hər API sorğusunda bu hesablamanı real-time etmək hətta güclü server üçün də mümkün deyil — latency saniyələrə çıxar. Məhz buna görə industry standart yanaşma offline batch compute-dur: gecə saatlarında similarity matrix hesablanır, Redis-ə yüklənir, API sorğuları isə hazır nəticəni Redis-dən oxuyur. Bu arxitektura < 5ms latency ilə milyonlarla istifadəçiyə xidmət göstərməyə imkan verir.

---

## PHP Implementation — Cold Start

Yeni istifadəçi üçün popularity-based fallback son 7 günün sifariş məlumatlarına əsaslanır. Bu data Redis-də cache-ə alınır ki, hər sorğuda DB-yə müraciət olmasın.

```php
<?php

namespace App\Services\Recommendation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\User;

class ColdStartRecommendationService
{
    /**
     * Heç bir history-si olmayan yeni user üçün trending products.
     * Son 7 günün sifarişlərinə əsasən hesablanır, 1 saat cache-ə alınır.
     */
    public function getForNewUser(int $limit = 10): array
    {
        return Cache::remember("cold_start:trending:week:{$limit}", 3600, function () use ($limit) {
            return Product::query()
                ->join('order_items', 'products.id', '=', 'order_items.product_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.created_at', '>=', now()->subDays(7))
                ->where('orders.status', 'completed')
                ->where('products.is_active', true)
                ->groupBy('products.id')
                ->orderByRaw('COUNT(order_items.id) DESC')
                ->limit($limit)
                ->get([
                    'products.*',
                    DB::raw('COUNT(order_items.id) as order_count'),
                ])
                ->toArray();
        });
    }

    /**
     * Kateqoriyaya görə trending products.
     * User signup zamanı kateqoriya seçibsə — bu metod istifadə olunur.
     */
    public function getByCategory(int $categoryId, int $limit = 10): array
    {
        return Cache::remember("cold_start:category:{$categoryId}:{$limit}", 3600, function () use ($categoryId, $limit) {
            return Product::query()
                ->join('order_items', 'products.id', '=', 'order_items.product_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('products.category_id', $categoryId)
                ->where('orders.created_at', '>=', now()->subDays(14))
                ->where('orders.status', 'completed')
                ->where('products.is_active', true)
                ->groupBy('products.id')
                ->orderByRaw('COUNT(order_items.id) DESC')
                ->limit($limit)
                ->get([
                    'products.*',
                    DB::raw('COUNT(order_items.id) as order_count'),
                ])
                ->toArray();
        });
    }

    /**
     * Yüksək ratingli yeni məhsullar (item cold start).
     * Məhsulun hələ purchase history-si yoxdursa — content + rating əsas götürülür.
     */
    public function getHighRatedNewArrivals(int $limit = 10): array
    {
        return Cache::remember("cold_start:new_arrivals:{$limit}", 1800, function () use ($limit) {
            return Product::query()
                ->where('is_active', true)
                ->where('created_at', '>=', now()->subDays(30))
                ->where('rating', '>=', 4.0)
                ->orderByDesc('rating')
                ->orderByDesc('rating_count')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Tam cold start strategy chain:
     * 1. Kateqoriya seçilibsə → category trending
     * 2. Kateqoriya yoxdursa → ümumi trending
     * 3. Trending boş çıxarsa (çox az data) → high-rated new arrivals
     */
    public function resolve(User $user, int $limit = 10): array
    {
        // Istifadəçi qeydiyyat zamanı kateqoriya seçib?
        $preferredCategoryId = $user->signup_category_id;

        if ($preferredCategoryId) {
            $results = $this->getByCategory($preferredCategoryId, $limit);
            if (count($results) >= 5) {
                return $results;
            }
        }

        $trending = $this->getForNewUser($limit);
        if (count($trending) >= 5) {
            return $trending;
        }

        // Fallback: ən az data olan mühitlər üçün (test, yeni bazarlar)
        return $this->getHighRatedNewArrivals($limit);
    }

    /**
     * Cache-i məcburi yenilə (admin panel-dən və ya scheduled job-dan).
     */
    public function invalidateCache(): void
    {
        Cache::forget('cold_start:trending:week:10');
        Cache::forget('cold_start:new_arrivals:10');
        // Kateqoriya cache-lərini pattern ilə silmək üçün Redis-dən birbaşa istifadə etmək lazımdır
    }
}
```

Bu service-i controller-də istifadə etmək:

```php
<?php

namespace App\Http\Controllers;

use App\Services\Recommendation\ColdStartRecommendationService;
use App\Services\Recommendation\PersonalizedRecommender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RecommendationController extends Controller
{
    public function __construct(
        private ColdStartRecommendationService $coldStart,
        private PersonalizedRecommender $personalized,
    ) {}

    public function forYou(Request $request): JsonResponse
    {
        $user = $request->user();

        // Guest user → cold start
        if (!$user) {
            return response()->json(
                $this->coldStart->getForNewUser(10)
            );
        }

        $orderCount = $user->orders()->count();

        // Yeni user (< 3 sifariş) → cold start strategy
        if ($orderCount < 3) {
            return response()->json(
                $this->coldStart->resolve($user, 10)
            );
        }

        // Köhnə user → full personalization
        $recommendations = Cache::remember(
            "user:recommendations:{$user->id}",
            1800,
            fn() => $this->personalized->forUser($user, 20)
        );

        return response()->json($recommendations);
    }
}
```

---

## Trade-offs

| Yanaşma | Üstünlük | Çatışmazlıq | Nə zaman |
|---------|----------|-------------|----------|
| **Item-based CF** | Məhsul sayı artsa da dəqiqlik yüksəkdir; offline compute mümkündür; < 5ms serve | Cold start (yeni product); sparsity (az alınan məhsullar üçün zəif) | 100k+ product, 1M+ user; e-commerce, streaming |
| **User-based CF** | Yeni məhsulları sürətlə öyrənir; user behavior-a real-time uyğunlaşır | User sayı artdıqca yavaşlayır (O(n²)); memory tələbi böyükdür | Kiçik user bazası (< 100k); social network-lər |
| **Content-based** | Cold start problemi yoxdur; explainable ("kateqoriya eyni olduğu üçün"); user history tələb etmir | "Filter bubble" riski — istifadəçi yeni şeylər kəşf etmir; feature engineering əmək tələb edir | Yeni product-lar; news/media; məhsul feature-ları zəngindirsə |
| **Hybrid** | Ən yüksək dəqiqlik; cold start + similarity hər ikisini həll edir; A/B test imkanı | Kompleks arxitektura; debug çətindir; daha çox infra | Production-da ciddi recommendation tələb olunanda; böyük platform-lar |

**Practical qayda:** Startup mərhələsində content-based ilə başla (data lazım deyil), 10k+ sifariş toplandıqdan sonra item-based CF əlavə et, 500k+ user-dən sonra hybrid arxitekturaya keç.

---

## Anti-patternlər

**1. Real-time similarity hesablamaq**

Hər API sorğusunda cosine similarity matriksini hesablamaq — 1M user, 100k product üçün bu bilgi saniyələrə, hətta dəqiqələrə uzanır. Latency-ni < 100ms altında saxlamaq mümkün olmur. Həll: bütün similarity-lər gecə batch job ilə hesablanır, Redis-ə yazılır. API yalnız hazır nəticəni oxuyur.

**2. Cold start-ı tamamilə ignore etmək**

Yeni istifadəçiyə boş widget göstərmək və ya ümumiyyətlə recommendation section-ı gizlətmək — ilk sessiya impressiyasını məhv edir. İstifadəçi ilk açılışda personalized heç nə görməyəndə platformanı tərk edir. Həll: popularity fallback, kateqoriya seçimi (signup zamanı), coğrafi trending — bunların ən azı biri mütləq olmalıdır.

**3. A/B test etməmək**

"Collaborative filtering content-based-dən yaxşıdır" fərziyyəsini test etmədən production-a çıxarmaq. Fərqli alqoritmlər fərqli user seqmentləri üçün fərqli işləyir. Həll: feature flag vasitəsilə (Pennant, Unleash) hər yeni alqoritmi A/B test-dən keçir, CTR və conversion rate əsasında qərar ver.

**4. Hər user üçün eyni popular items göstərmək**

"Global trending" siyahısını bütün istifadəçilərə göstərmək — avtomobil almaq istəyən kişiyə qadın geyimi, uşaq alan valideynə elektronika göstərmək kimidir. Həll: ən azı kateqoriya + yaş qrupu + coğrafi mövqe əsasında segmentləşdir; "trending in your city" kimi yanaşmalar çox effektivdir.

**5. Similarity matriksini çox nadir yeniləmək**

Həftəlik və ya aylıq refresh — məhsul assortimentinin dəyişdiyi, mövsüm keçidlərinin baş verdiyi hallarda stale recommendation-lar göstərilir. Bayram öncəsi trending-i bayramdan sonra da göstərmək konversiyani aşağı salır. Həll: ən azı gündəlik batch compute; sürətli dəyişən bazarlarda (fashion, qida) 6-saatlıq refresh daha uyğundur.

**6. "Black box" ML model istifadə etmək**

Neural collaborative filtering kimi modellərin niyə X məhsulu tövsiyə etdiyini izah etmək mümkün deyil. Bu, debug-ı çətinləşdirir, müştəri şikayətlərini idarə etməyi mümkünsüz edir ("niyə bu mənə göstərildi?"), həmçinin GDPR explainability tələblərini pozur. Həll: production-da ilk mərhələdə explainable metodlar (item-based CF, content-based) istifadə et; ML əlavə edəndə "because you bought X" kimi explanation layer həmişə əlavə et.

**7. Recommendation diversity-ni nəzərə almamaq (Filter Bubble)**

Yalnız ən yüksək similarity score-lu məhsulları göstərmək istifadəçini eyni məhsul kateqoriyasına həbs edir. "Qapalı dairə" yaranır: user yalnız gördüklərini görür, kəşf baş vermir, uzun müddətdə engagement düşür. Həll: top-N recommendation-lardan 10-15%-ni intentional diversity üçün ayır — fərqli kateqoriya, yeni brend, "populyar amma sən görməmisən" kimi slotlar əlavə et.

---

## Interview Sualları və Cavablar

**S: Collaborative filtering vs content-based filtering — fərqi nədir?**

Collaborative filtering "oxşar istifadəçilər nə aldı?" sualına cavab verir — məhsulun xüsusiyyətlərini bilmir, yalnız user behavior matriksinə baxır. Content-based isə "bu məhsulun xüsusiyyətləri ilə oxşar məhsullar hansıdır?" — istifadəçi history-sindən asılı olmur. Əsas fərq: collaborative filtering user data tələb edir (cold start problemi var), content-based isə yalnız məhsul atributlarına ehtiyac duyur. Practical baxımdan collaborative filtering daha yüksək dəqiqlik verir, amma data toplanana qədər cold start problemini content-based həll edir.

**S: Cold start problemini necə həll edərdiniz?**

Üç paralel strategiya istifadə edərdim. Birincisi, **popularity-based fallback** — yeni user üçün son 7 günün trending məhsulları (kateqoriya, yaş, coğrafiyaya görə seqmentləşdirilmiş). İkincisi, **onboarding quiz** — signup zamanı "hansı kateqoriyalar sizi maraqlandırır?" sualı ilə 3-5 kateqoriya seçdirmək; bu seçimə əsasən content-based recommendation dərhal işə düşür. Üçüncüsü, **contextual signal-lar** — ilk sessiyada istifadəçinin browse etdiyi məhsulları real-time olaraq session-a əlavə edib, həmin session məlumatına əsasən anlıq tövsiyələr vermək (session-based recommendation). Bu üçünü birləşdirsən cold start 90%+ hallarda effektiv həll olunur.

**S: Netflix/Amazon recommendation-larını necə implement edir?**

Amazon item-based collaborative filtering-i 2003-cü ildə patents etdi — "customers who bought this also bought" klassik item-item CF-dir. Offline hesablanır, DynamoDB-də saxlanır. Netflix isə Matrix Factorization (ALS — Alternating Least Squares) əsaslı hybrid sistem istifadə edir: user-movie rating matriksi latent factor-lara (50-200 ölçülü) dekompose edilir, hər user və hər movie bu factor space-də təmsil olunur. Bundan əlavə deep learning (two-tower model) istifadə edirlər — bir tower user-i, digər tower content-i encode edir, dot product similarity verir. Hər ikisi də offline batch compute + online serving arxitekturası ilə işləyir; real-time yalnız context (günün vaxtı, device, location) üçün istifadə olunur.

**S: Matrix factorization nədir?**

Matrix factorization user-product rating matriksi (M) iki kiçik matriksin hasili kimi təmsil etməkdir: M ≈ U × P, burada U user latent factor-larıdır (hər user üçün k-ölçülü vektor), P isə product latent factor-larıdır. k adətən 50-200 seçilir. Bu factor-lar interpretable deyil — avtomatik öyrənilir, amma "janr" ya "qiymət həssaslığı" kimi implicit xüsusiyyətlərə uyğun gəlir. Populyar metodlar: **SVD** (Singular Value Decomposition), **ALS** (Alternating Least Squares — distributed compute üçün ideal, Spark MLlib-də mövcuddur), **NMF** (Non-negative Matrix Factorization). PHP/Laravel mühitdə bu hesablamanı Spark Python job-u ilə gecə batch-da edib nəticəni MySQL/Redis-ə yazmaq ən practical yanaşmadır.

**S: Recommendation accuracy-ni necə ölçərsiniz?**

Offline metrics (A/B testdən əvvəl): **Precision@K** — tövsiyə olunan K məhsuldan neçəsi faktiki alındı; **Recall@K** — istifadəçinin aldığı məhsulların neçəsi K tövsiyə içindəydi; **NDCG** (Normalized Discounted Cumulative Gain) — sıralamaya həssas metric, yuxarıdakı tövsiyələr aşağıdakılardan daha çox dəyər daşıyır. Online metrics (production-da əsas): **CTR** (Click-Through Rate) — tövsiyə widget-inin click sayı / göstərilmə sayı; **Conversion Rate** — tövsiyədən click edib alan user faizi; **Revenue per impression** — hər tövsiyə göstərmənin gətirdiyi gəlir; **Coverage** — neçə fərqli məhsul tövsiyə edilir (diversity); **Serendipity** — istifadəçini gözlənilmədən xoşbəxt edən tövsiyə faizi. Production-da ən vacib metric revenue per impression-dır — CTR yüksək ola bilər, amma alış baş vermirsə recommendation faydasızdır.
