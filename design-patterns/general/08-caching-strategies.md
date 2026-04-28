# Caching Strategiyaları (Senior) ⭐⭐⭐

## İcmal

Cache sadəcə `Cache::remember()` deyil. Cache topology (local vs distributed vs CDN), eviction policy-ləri (LRU, LFU, TTL), cache stampede, tag-based invalidation, HTTP cache — bunların hamsı birlikdə sistemin performance-ini formalaşdırır. Bu fayl caching-in dərin baxışını verir.

## Niyə Vacibdir

Cache yanlış qurulubsa: stampede-dən DB çökər, stale data user-i çaşdırar, Redis crash olduqda bütün sistem dayanar. Cache düzgün qurulubsa: DB yükü 90% azalar, latency ms-ə düşər, horizontal scale asanlaşar. Senior developer cache-i "performance trick" kimi yox, sistemin bir layer-i kimi düşünür.

## Əsas Anlayışlar

### Cache Topology

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Local (In-Process) Cache                                 │
│    PHP process-in memory-sində                              │
│    Pros: Sıfır network latency, ən sürətli                  │
│    Cons: PHP-FPM hər request-də sıfırlanır, shared yoxdur  │
│    Laravel: array driver, ya da APCu extension              │
│                                                             │
│ 2. Distributed Cache (Redis / Memcached)                    │
│    Ayrı server-da, bütün PHP worker-ləri paylaşır           │
│    Pros: Shared state, persistence (Redis), TTL, pub/sub    │
│    Cons: Network latency (~1ms), əlavə infra                │
│    Laravel: redis driver (defolt production seçimi)         │
│                                                             │
│ 3. CDN Cache (Cloudflare, Fastly, CloudFront)               │
│    Edge node-larda, user-ə ən yaxın məkanda                 │
│    Pros: Global dağıtılmış, statik asset-lər üçün ideal     │
│    Cons: Yalnız public content, invalidation API lazımdır   │
│    Laravel: HTTP response header-ları ilə idarə olunur      │
└─────────────────────────────────────────────────────────────┘
```

### Eviction Policy-lər

```
LRU (Least Recently Used):
  Ən son istifadə edilməyən silinir.
  Nümunə: 5 slot — [A, B, C, D, E], F gəlir:
  A ən köhnə → silinir → [B, C, D, E, F]
  Geniş istifadə: Redis default

LFU (Least Frequently Used):
  Ən az istifadə edilən silinir (sayac tutur).
  Hot data → çox istifadə → qalır
  Cold data → az istifadə → silinir
  Redis 4.0+: maxmemory-policy allkeys-lfu

TTL (Time-To-Live):
  Vaxt bitəndə avtomatik silinir.
  LRU/LFU-dan müstəqil işləyir.
  Hər Redis key-in özünün TTL-i ola bilər.

FIFO (First In, First Out):
  İlk yazılan ilk silinir.
  Cache-ə aid ədalətsizdir — hot data da silinir.
  Nadir istifadə.
```

### Cache Stampede (Thundering Herd)

```
Problem:
  10,000 user eyni vaxtda cavab gözləyir.
  TTL bitdi → hamısı eyni vaxtda DB-yə gedir.
  DB 10,000 request altında çöküb.

  T=0    [User1, User2, ..., User10000] cache miss!
  T=0.1  10,000 DB query başladı
  T=2    DB overloaded, timeout

Həll 1: Lock-based (yalnız bir request DB-yə getsin)
  T=0    User1 cache miss, lock alır, DB-yə gedir
  T=0    User2-10000 lock gözləyir
  T=1    User1 data gəldi, cache-ə yazdı, lock buraxdı
  T=1    User2-10000 cache-dən alır

Həll 2: Probabilistic Early Expiration (PER)
  TTL bitmədən tədricən yenilər.
  "TTL 10 saniyə qalmışsa, 10% ehtimalla yenilə"
  → Cache heç vaxt tamamilə boş olmur
```

## Praktik Baxış

### Redis Cache Tag-ları

```php
// Laravel-də tag-based invalidation
// Qeyd: yalnız Redis driver-da işləyir (file/array driver-da yox)

// Yazma — tag-larla
Cache::tags(['products', 'category:1'])->put(
    "product:{$id}",
    $product,
    ttl: 3600,
);

Cache::tags(['products'])->remember(
    'products:all',
    3600,
    fn () => Product::all(),
);

// Invalidation — bütün "products" tag-lı key-ləri sil
Cache::tags(['products'])->flush();
// Yalnız category:1-ə aid olanları sil
Cache::tags(['category:1'])->flush();

// Real nümunə — category yeniləndi, bütün əlaqəli cache sil
class CategoryService
{
    public function update(int $id, array $data): Category
    {
        $category = Category::findOrFail($id);
        $category->update($data);

        // Bu category-yə aid bütün cache-i sil
        Cache::tags(["category:{$id}", 'products'])->flush();

        return $category->fresh();
    }
}
```

### Cache Stampede — Lock-based Həll

```php
class ProductRepository
{
    public function findWithStampedeProtection(int $id): ?Product
    {
        $cacheKey = "product:{$id}";
        $lockKey  = "product:{$id}:lock";

        // Cache-də var mı?
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Lock al — yalnız bir process DB-yə getsin
        $lock = Cache::lock($lockKey, seconds: 10);

        if ($lock->get()) {
            // Mən lock aldım — DB-dən alıb cache-ə yazım
            try {
                $product = Product::with('category')->find($id);
                Cache::put($cacheKey, $product, ttl: 3600);
                return $product;
            } finally {
                $lock->release();
            }
        }

        // Lock ala bilmədim — başqası artıq DB-dən alır
        // Bir az gözlə, cache-dən yenidən yoxla
        usleep(100_000); // 100ms
        return Cache::get($cacheKey) ?? Product::find($id);
    }
}
```

### Probabilistic Early Expiration (PER)

```php
class CacheWithEarlyExpiration
{
    /**
     * XFetch alqoritmi — Facebook tərəfindən dərc edilib.
     * TTL bitməzdən əvvəl tədricən yeniləyir.
     *
     * @param float $beta Aggressiveness (1.0 = standard, >1 = daha erkən yeniləyir)
     */
    public function rememberWithPER(
        string $key,
        int $ttl,
        callable $callback,
        float $beta = 1.0,
    ): mixed {
        $cached = Cache::get($key . ':data');
        $expiry = Cache::get($key . ':expiry');

        if ($cached !== null && $expiry !== null) {
            // Hələ vaxt var — amma yeniləmə lazımdır mı?
            $timeLeft = $expiry - time();
            $delta    = microtime(true) - ($expiry - $ttl);

            // Probabilistic check: vaxt azaldıqca yeniləmə ehtimalı artır
            if ($timeLeft > 0 && (-$delta * $beta * log(random_int(1, PHP_INT_MAX) / PHP_INT_MAX)) < $timeLeft) {
                return $cached; // Hələ lazım deyil
            }
        }

        // Cache miss ya da yenilənmə vaxtı — DB-dən al
        $start = microtime(true);
        $value = $callback();
        $computeTime = microtime(true) - $start;

        // Data + expiry saxla
        $actualTtl = $ttl + (int)($computeTime * 1000);
        Cache::put($key . ':data',   $value,       $actualTtl);
        Cache::put($key . ':expiry', time() + $ttl, $actualTtl);

        return $value;
    }
}
```

### HTTP Caching: ETag, Last-Modified, Cache-Control

```php
// Laravel Middleware — HTTP cache header-ları
class HttpCacheMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Yalnız GET request-lər üçün
        if (!$request->isMethod('GET')) {
            return $response;
        }

        // Cache-Control header-ları
        $response->headers->set(
            'Cache-Control',
            'public, max-age=300, stale-while-revalidate=60',
        );

        // ETag — response content-inin hash-i
        $etag = md5($response->getContent());
        $response->headers->set('ETag', "\"{$etag}\"");

        // If-None-Match: client göndərir, server yoxlayır
        if ($request->header('If-None-Match') === "\"{$etag}\"") {
            return response('', 304); // Not Modified — body göndərmirik
        }

        return $response;
    }
}
```

```php
// Controller-da Last-Modified ilə HTTP cache
class ProductController extends Controller
{
    public function show(int $id): Response
    {
        $product = Product::findOrFail($id);

        // Last-Modified: məhsulun son yenilənmə tarixi
        $lastModified = $product->updated_at;

        // If-Modified-Since yoxlayır
        if ($request->hasHeader('If-Modified-Since')) {
            $ifModifiedSince = Carbon::parse($request->header('If-Modified-Since'));

            if ($lastModified->lte($ifModifiedSince)) {
                return response('', 304);
            }
        }

        return response()
            ->json(ProductResource::make($product))
            ->header('Last-Modified', $lastModified->toRfc7231String())
            ->header('Cache-Control', 'public, max-age=60');
    }
}
```

### Database Query Cache vs Application Cache

```php
// Database query cache — Laravel-in query result-larını cache-lər
// NOT: MySQL query cache deprecated, application cache daha yaxşıdır

// YANLIŞ yanaşma: DB query cache-ə güvənmək
$users = DB::select('SELECT * FROM users'); // MySQL cache-ə güvənir
// MySQL 8.0+ query cache tamamilə silinib!

// DOĞRU yanaşma: Application-level cache
class UserRepository
{
    public function getActiveUsers(): Collection
    {
        return Cache::remember(
            'users:active',
            300, // 5 dəqiqə
            fn () => User::active()->with('roles')->get(),
        );
    }

    public function getCount(): int
    {
        // Sadə say üçün ayrı, qısa TTL
        return Cache::remember(
            'users:count',
            60, // 1 dəqiqə
            fn () => User::count(),
        );
    }
}
```

### Cache-aside + Circuit Breaker (Redis down olduqda fallback)

```php
use Illuminate\Support\Facades\Cache;

class ResilientProductRepository
{
    private bool $cacheAvailable = true;

    public function find(int $id): ?Product
    {
        // Redis down olduqda birbaşa DB-yə get
        if (!$this->cacheAvailable) {
            return Product::find($id);
        }

        try {
            return Cache::remember(
                "product:{$id}",
                3600,
                fn () => Product::with('category')->find($id),
            );
        } catch (\RedisException $e) {
            // Redis əlçatmazdır — circuit breaker activ et
            $this->cacheAvailable = false;

            // Bir müddətdən sonra Redis-i yenidən yoxla
            Cache::put('cache:circuit_breaker', true, ttl: 30);

            report($e); // Sentry/log

            // Fallback: birbaşa DB
            return Product::find($id);
        }
    }

    private function isCacheAvailable(): bool
    {
        // Circuit breaker aktiv deyilsə cache istifadə et
        return !Cache::get('cache:circuit_breaker', false);
    }
}
```

### Cache Partitioning / Sharding

```php
// Böyük data set-ləri üçün cache partition
// Hər shard ayrı Redis instance-da ola bilər

class ShardedCacheService
{
    private const SHARD_COUNT = 4;

    private function getShardKey(int $id): string
    {
        $shard = $id % self::SHARD_COUNT;
        return "shard_{$shard}"; // Redis connection adı
    }

    public function getProduct(int $id): ?array
    {
        $connection = $this->getShardKey($id);

        return Cache::store($connection)->remember(
            "product:{$id}",
            3600,
            fn () => Product::find($id)?->toArray(),
        );
    }
}

// config/cache.php — ayrı Redis instance-lar
'stores' => [
    'shard_0' => ['driver' => 'redis', 'connection' => 'cache_0'],
    'shard_1' => ['driver' => 'redis', 'connection' => 'cache_1'],
    'shard_2' => ['driver' => 'redis', 'connection' => 'cache_2'],
    'shard_3' => ['driver' => 'redis', 'connection' => 'cache_3'],
],
```

## Real Nümunə: E-Commerce Product Catalog Caching Strategy

```php
// E-commerce üçün tam caching strategiyası
class ProductCatalogCachingStrategy
{
    // 1. Product detail — uzun TTL, dəyişiklikdə invalidate
    public function getProduct(int $id): ?Product
    {
        return Cache::tags(['products', "product:{$id}"])
            ->remember("product:{$id}", 7200, fn () =>
                Product::with(['category', 'images', 'variants'])
                    ->findOrFail($id)
            );
    }

    // 2. Product list — qısa TTL, çox dəyişir
    public function getActiveProducts(int $page = 1): LengthAwarePaginator
    {
        return Cache::tags(['products', 'product_list'])
            ->remember("products:active:page:{$page}", 300, fn () =>
                Product::active()
                    ->with('category')
                    ->latest()
                    ->paginate(20)
            );
    }

    // 3. Featured products — orta TTL, manual invalidation
    public function getFeatured(): Collection
    {
        return Cache::tags(['products', 'featured'])
            ->remember('products:featured', 1800, fn () =>
                Product::featured()->with('category')->limit(10)->get()
            );
    }

    // 4. Search results — qısa TTL, query-specific key
    public function search(string $query, array $filters): Collection
    {
        $cacheKey = 'search:' . md5($query . serialize($filters));

        return Cache::remember($cacheKey, 120, fn () =>
            Product::search($query)
                ->filter($filters)
                ->with('category')
                ->get()
        );
    }

    // 5. Invalidation — product dəyişdikdə
    public function invalidateProduct(int $id): void
    {
        Cache::tags(["product:{$id}"])->flush();  // Bu product-a aid hamısı
        Cache::tags(['product_list', 'featured'])->flush(); // List-ləri yenilə
        // Search results TTL-lə özü silinir — invalidate etmirik
    }
}
```

## Praktik Tapşırıqlar

1. Redis stampede simulyasiyası: eyni vaxtda 100 concurrent request göndər (`ab` tool ilə), lock-based protection olmadan vs olduqda fərqi ölç.
2. Cache tag-larını implement et: category yenilənəndə `Cache::tags(['products'])->flush()` test et.
3. HTTP ETag middleware yaz: product show endpoint-ə tətbiq et, ikinci request-in 304 qaytardığını `curl -v` ilə doğrula.
4. Circuit breaker test et: Redis-i stopla, tətbiqin DB-ə fallback etdiyini yoxla.
5. Cache warming script yaz: deployment pipeline-a əlavə et.

## Anti-Pattern Nə Zaman Olur?

**1. Cache-ı primary data store kimi istifadə etmək**
Redis-ə yazıb DB-ə yazmamaq — Redis restart → data itkisi. Redis persistence (AOF/RDB) aktiv olsa belə, bu cache üçün yox, primary storage üçün nəzərdə tutulub. Həmişə source of truth DB-dir, cache DB-nin surətidir.

**2. Over-caching (hər şeyi cache-ləmək)**
Hər SQL query-nin nəticəsini cache-ləmək — cache management overhead-i, stale data riski, invalidation mürəkkəbliyi artır. Cache yalnız: (a) tez-tez oxunan, (b) nadir dəyişən, (c) hesablaması bahalı olan data üçün istifadə et.

```
Cache etmə:
  ✓ Product catalog (saatda bir dəyişir, minlərlə request)
  ✓ User session (hər request oxunur)
  ✓ Aggregated stats (həsablaması ağır)

Cache etmə:
  ✗ User balance (hər transaction dəyişir, stale olması ciddidir)
  ✗ Inventory count (real-time lazım)
  ✗ Payment status (gecikmiş məlumat maliyyə riski)
```

**3. Cache stampede-dən qorunmamaq**
Yüksək traffic-li endpoint-lərdə TTL bitdikdə lock ya da PER istifadə etməmək — peak load-da DB spike. Hər cache miss-in DB-yə nə qədər yük qoyacağını hesabla.

**4. Stale data-nı test etməmək**
"Cache 1 saatda bir yenilənir" — yaxşı, amma update etdikdə cache invalidation işləyirmi? Test mühitdə cache adətən `array` driver-dədir (hər request sıfırlanır), production-da `redis`-dir (real TTL). Cache invalidation bug-larını yalnız production-da görmək çox gec olur.

---

## Əlaqəli Mövzular

- [Cache-Aside Pattern](07-cache-aside.md) — lazy loading caching, `Cache::remember()` pattern
- [Concurrency Patterns](03-concurrency-patterns.md) — stampede-də distributed lock
- [Repository Pattern](../laravel/01-repository-pattern.md) — cache-i repository layer-də saxlamaq
- [CQRS](../integration/01-cqrs.md) — read model-i cache layer kimi düşünmək
