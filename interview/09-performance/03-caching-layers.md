# Multi-Level Caching (Senior ⭐⭐⭐)

## İcmal

Multi-level caching — tətbiqin müxtəlif qatlarında fərqli sürət/həcm xüsusiyyətlərinə malik cache mexanizmlərinin birlikdə işlədiyi arxitektura patternidir. Hər qat öz latency-si və TTL strategiyası ilə fərqlənir. L1 (in-process), L2 (shared distributed), L3 (CDN/edge) — bu qatların düzgün idarə olunması tətbiqin miqyaslanma qabiliyyətini əsaslı şəkildə artırır.

## Niyə Vacibdir

Modern backend sistemlərinin böyük əksəriyyəti DB-yə birbaşa müraciəti azaltmaq üçün cache-ə etibar edir. Məsələn, 10K RPS-lik bir sistem bütün istəkləri DB-yə göndərsə, DB saniyədə 10K query alır. Cache hit rate 90%-ə çatdıqda DB yükü 1K-ya düşür. Bu 10x azalma infrastruktur xərclərini birbaşa azaldır. Bundan əlavə, cache olmadan müəyyən read-heavy workload-lar tamamilə qeyri-mümkündür.

## Əsas Anlayışlar

- **Cache qatları (PHP kontekstində):**
  - **L1 — Request-scoped (in-process):** PHP prosesinin RAM-ı, yalnız cari request üçün yaşayır. `Repository::remember()`, static property.
  - **L2 — Shared in-memory (Redis/Memcached):** Bütün server/worker-lər paylaşır, TTL ilə idarə olunur.
  - **L3 — HTTP Cache (Varnish/Nginx/CDN):** Edge-dən cavab, DB/PHP-yə sorğu getmir.
  - **L4 — Browser Cache:** Client tərəfindəki cache.

- **Cache strategiyaları:**
  - **Cache-aside (Lazy loading):** Tətbiq cache-ə baxır, yoxdursa DB-dən alır, cache-ə yazır.
  - **Write-through:** Yazı eyni anda cache + DB-ə gedir.
  - **Write-behind (Write-back):** Yazı yalnız cache-ə gedir, async DB-ə yazılır.
  - **Read-through:** Cache layer özü DB-dən oxuyur (application şəffaf).
  - **Refresh-ahead:** TTL bitməzdən əvvəl proaktiv yeniləmə.

- **Cache invalidation (ən çətin məsələ):**
  - **TTL-based:** Vaxt keçdikdə avtomatik silinir.
  - **Event-based:** Model dəyişdikdə manual silmə (Observer).
  - **Tag-based:** Redis tags ilə qrup invalidation (Laravel Cache tags).
  - **Version-based:** Cache key-ə version əlavə (URL versioning).

- **Cache problemləri:**
  - **Cache stampede / dogpile:** TTL eyni vaxtda bitdikdə, yüzlərlə request eyni anda DB-yə gedir. Həll: mutex lock, probabilistic early refresh.
  - **Thundering herd:** Yeni deploy sonrası sıfırdan dolan cache. Həll: warm-up.
  - **Cache penetration:** Key yoxdur, hər sorğu DB-yə çatır. Həll: null caching, Bloom filter.
  - **Cache avalanche:** Çox key eyni vaxtda expire. Həll: jitter (random TTL).
  - **Stale data:** Cache köhnə, DB yeni. Həll: short TTL, event invalidation.

- **Redis data strukturları:**
  - **String:** Sadə value, counter
  - **Hash:** Object caching (field-level access)
  - **List:** Queue, timeline
  - **Set:** Unique member-lər, tag sistemi
  - **Sorted Set:** Leaderboard, range query
  - **HyperLogLog:** Unique visitor count (approximate)

## Praktik Baxış

**Cache-aside pattern (Laravel):**

```php
// Ən geniş yayılmış pattern
$data = Cache::remember("user:{$userId}:profile", 3600, function () use ($userId) {
    return User::with('profile', 'settings')->find($userId);
});
```

**Cache stampede qarşısını almaq:**

```php
// Yanaşma 1: Lock ilə
$data = Cache::lock("rebuild:user:{$id}", 10)->get(function () use ($id) {
    return Cache::remember("user:{$id}", 3600, fn() => $this->buildFromDB($id));
});

// Yanaşma 2: Probabilistic early expiration
// TTL bitməzdən 10% qalmış, random olaraq 5% ehtimalla yeniləyin
public function getWithEarlyExpiry(string $key, int $ttl, Closure $callback): mixed
{
    $item = Cache::get($key . ':meta'); // ['value' => ..., 'expires_at' => ...]

    if (!$item) {
        return $this->refreshCache($key, $ttl, $callback);
    }

    $remainingTtl = $item['expires_at'] - time();
    $earlyRefreshThreshold = $ttl * 0.1; // son 10% zamanında

    if ($remainingTtl < $earlyRefreshThreshold && mt_rand(1, 10) === 1) {
        // 10% ehtimalla yenilə (stampede-dən qaçır)
        dispatch(new RefreshCacheJob($key, $ttl, $callback));
    }

    return $item['value'];
}
```

**Tag-based invalidation:**

```php
// Yazarkən tag-la
Cache::tags(['users', "user:{$userId}"])->put(
    "user:{$userId}:orders",
    $orders,
    ttl: 1800
);

// Useri update etdikdə hamısını sil
Cache::tags(["user:{$userId}"])->flush();

// Observer ilə avtomatik:
class UserObserver
{
    public function updated(User $user): void
    {
        Cache::tags(["user:{$user->id}"])->flush();
    }
}
```

**Multi-layer cache:**

```php
class MultiLayerCache
{
    private array $localCache = []; // L1: request-scoped

    public function get(string $key, Closure $callback, int $ttl = 3600): mixed
    {
        // L1: in-process check
        if (isset($this->localCache[$key])) {
            return $this->localCache[$key];
        }

        // L2: Redis check
        $value = Cache::get($key);
        if ($value !== null) {
            $this->localCache[$key] = $value; // L1-ə də yaz
            return $value;
        }

        // L3: DB / compute
        $value = $callback();
        Cache::put($key, $value, $ttl);
        $this->localCache[$key] = $value;

        return $value;
    }
}
```

**Null caching (cache penetration qarşısı):**

```php
public function findProduct(int $id): ?Product
{
    $cacheKey = "product:{$id}";
    $cached = Cache::get($cacheKey);

    if ($cached === false) {
        return null; // null-cached — DB-yə getmə
    }

    if ($cached !== null) {
        return $cached;
    }

    $product = Product::find($id);
    // Yoxdursa da cache-lə (false ilə fərqləndir)
    Cache::put($cacheKey, $product ?? false, $product ? 3600 : 300);

    return $product;
}
```

**TTL jitter (avalanche qarşısı):**

```php
public function cacheWithJitter(string $key, mixed $value, int $baseTtl): void
{
    // Base TTL-in ±20%-i qədər random əlavə et
    $jitter = random_int(-$baseTtl * 0.2, $baseTtl * 0.2);
    Cache::put($key, $value, $baseTtl + $jitter);
}
```

**HTTP Cache headers (Laravel):**

```php
return response($data)
    ->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400')
    ->header('ETag', md5(serialize($data)))
    ->header('Last-Modified', $lastModified->toRfc7231String());
```

**Trade-offs:**
- Cache = eventual consistency; real-time data üçün uyğun deyil
- Redis cluster = network hop; local cache = stale risk
- Tag-based invalidation Redis Cluster-da işləmir (single node only)
- Write-through = yazı latency artır; write-behind = data loss riski

**Common mistakes:**
- Cache key collision (namespace istifadə etməmək)
- Böyük object cache-ləmək (serialization overhead)
- Cache-ə sensitive data yazmaq (PII, token)
- TTL-siz cache (memory dolur)
- Cache invalidation olmadan data yeniləmək

## Nümunələr

### Real Ssenari: Homepagede product list

```
Tələb: 50K DAU, homepage 200 product göstərir, DB 3ms cavab verir
Load: 50K * 10 page refresh = 500K req/gün = ~6 RPS ortalama, 50 RPS peak

Strategiya:
- Product list: Cache::remember('homepage:products', 300, ...) // 5 dəq TTL
- Produktu update etdikdə: observer ilə key-i sil
- HTTP: Cache-Control: public, max-age=60 (CDN 60 saniyə cache)

Nəticə:
- DB 500K req/gün → ~1K req/gün (cache miss-lar)
- CDN hit rate 85% → PHP-yə 15% çatır
```

### Kod Nümunəsi

```php
<?php

class ProductCacheService
{
    private const TTL_PRODUCT = 3600;       // 1 saat
    private const TTL_LISTING = 300;        // 5 dəq
    private const TTL_NULL = 300;           // null cache: 5 dəq

    public function getProduct(int $id): ?Product
    {
        return Cache::tags(['products', "product:{$id}"])
            ->remember("product:{$id}", self::TTL_PRODUCT, function () use ($id) {
                return Product::with('category', 'images')->find($id);
            });
    }

    public function getHomepageListing(int $page = 1): LengthAwarePaginator
    {
        return Cache::tags(['products', 'homepage'])
            ->remember("homepage:products:page:{$page}", self::TTL_LISTING, function () use ($page) {
                return Product::where('active', true)
                    ->with('primaryImage')
                    ->orderBy('sort_order')
                    ->paginate(20, page: $page);
            });
    }

    public function invalidateProduct(int $id): void
    {
        // Yalnız bu ürünə aid cache-ləri sil
        Cache::tags(["product:{$id}"])->flush();
    }

    public function invalidateAllListings(): void
    {
        Cache::tags(['homepage'])->flush();
    }
}

// Observer
class ProductObserver
{
    public function __construct(private ProductCacheService $cache) {}

    public function updated(Product $product): void
    {
        $this->cache->invalidateProduct($product->id);
        $this->cache->invalidateAllListings();
    }

    public function deleted(Product $product): void
    {
        $this->cache->invalidateProduct($product->id);
        $this->cache->invalidateAllListings();
    }
}
```

## Praktik Tapşırıqlar

1. **Cache stampede simulyasiyası:** Redis-i dayandır, 100 concurrent request göndər, stampede-i müşahidə et. Sonra mutex lock əlavə et, fərqi gör.

2. **Tag-based invalidation:** Observer pattern ilə model update olduqda tag flush implement et. Cache::tags() Redis-də necə saxlanır, yoxla.

3. **Cache metrics:** Cache hit/miss ratio-nu log et. Redis `INFO stats` komandası ilə `keyspace_hits` / `keyspace_misses` müqayisə et.

4. **HTTP caching:** Nginx-ə `proxy_cache` konfiqurasiyası əlavə et, eyni endpointə 2 request göndər, 2-cinin headers-ına bax (`X-Cache: HIT`).

5. **Benchmark:** `Cache::remember` vs raw `DB::select` — 1000 iteration ilə qiymətləndir, fərqi ms ilə qeyd et.

## Əlaqəli Mövzular

- `01-performance-profiling.md` — Cache hit/miss-i profiling ilə tapmaq
- `02-query-optimization.md` — Cache-ə ehtiyacı azaldan query optimization
- `09-async-batch-processing.md` — Cache warm-up job-ları
- `11-apm-tools.md` — Cache metrikalarını APM ilə izləmək
- `05-connection-pool-tuning.md` — Redis connection pooling
