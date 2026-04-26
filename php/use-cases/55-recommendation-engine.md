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
