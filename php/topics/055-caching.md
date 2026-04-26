# Caching - Tam Hərtərəfli Bələdçi (Middle)

## 1. Caching Nədir?

**Caching** — tez-tez istifadə olunan məlumatların daha sürətli əldə edilə biləcək bir yerdə (adətən RAM-da) saxlanması prosesidir. Məqsəd response time-ı azaltmaq, database yükünü yüngülləşdirmək və ümumi performansı artırmaqdır.

### Niyə Cache Lazımdır?

```
Cache olmadan:
Client -> Web Server -> Application -> Database (100ms query)
                                    -> External API (300ms)
                                    = ~400ms total

Cache ilə:
Client -> Web Server -> Application -> Cache (1-5ms)
                                    = ~5ms total
```

**Əsas faydaları:**
- **Sürət**: Database query-lər əvəzinə RAM-dan oxuma (100x+ sürətli)
- **Yük azaltma**: Database-ə edilən sorğu sayı azalır
- **Xərc qənaəti**: Daha az server resursu lazımdır
- **Scalability**: Daha çox istifadəçini idarə edə bilirsiniz
- **Availability**: Database çöksə belə, cached data xidmət edə bilər

---

## 2. Cache Növləri

### 2.1 Application Cache (Server-side)

Application səviyyəsində data cache-ləmə. Redis, Memcached kimi in-memory store-lar istifadə olunur.

*Application səviyyəsində data cache-ləmə. Redis, Memcached kimi in-mem üçün kod nümunəsi:*
```php
// Laravel Application Cache
$products = Cache::remember('products:featured', 3600, function () {
    return Product::where('featured', true)
        ->with('category', 'images')
        ->get();
});
```

### 2.2 Database Cache (Query Cache)

Database səviyyəsində query nəticələrinin cache-lənməsi.

*Database səviyyəsində query nəticələrinin cache-lənməsi üçün kod nümunəsi:*
```php
// MySQL Query Cache (MySQL 8.0-da silindi, amma konsept əhəmiyyətlidir)
// Laravel-də manual query caching
$users = Cache::remember('users:active:page:1', 600, function () {
    return DB::table('users')
        ->where('active', true)
        ->orderBy('created_at', 'desc')
        ->paginate(20);
});
```

### 2.3 HTTP Cache

Browser və proxy seviyəsində response caching.

*Browser və proxy seviyəsində response caching üçün kod nümunəsi:*
```php
// Cache-Control headers
return response($content)
    ->header('Cache-Control', 'public, max-age=3600')         // 1 saat cache
    ->header('Cache-Control', 'private, max-age=600')          // Yalnız browser cache
    ->header('Cache-Control', 'no-cache')                      // Hər dəfə validate et
    ->header('Cache-Control', 'no-store')                      // Heç cache etmə
    ->header('Cache-Control', 'public, max-age=31536000, immutable'); // 1 il, dəyişməz

// ETag
$etag = md5($content);
return response($content)
    ->header('ETag', '"' . $etag . '"')
    ->header('Cache-Control', 'public, max-age=0, must-revalidate');

// Last-Modified
return response($content)
    ->header('Last-Modified', $lastModified->toRfc7231String())
    ->header('Cache-Control', 'public, max-age=0, must-revalidate');
```

### 2.4 CDN Cache

Content Delivery Network — statik (və bəzən dinamik) content-i dünya üzrə edge server-lərdə cache edir.

```
İstifadəçi (Bakı) -> CDN Edge (İstanbul) -> Origin Server (Frankfurt)
                     |
                     Cache HIT -> Birbaşa cavab (10ms)
                     Cache MISS -> Origin-dən al, cache-lə, cavab ver
```

### 2.5 Browser Cache

Client tərəfində resursların (CSS, JS, images) cache-lənməsi.

*Client tərəfində resursların (CSS, JS, images) cache-lənməsi üçün kod nümunəsi:*
```php
// Laravel Mix / Vite - versioning ilə cache busting
// vite.config.js əsasında avtomatik hash əlavə edir
// /build/assets/app-BdG7k3Ye.js

// Manual cache busting
<link rel="stylesheet" href="/css/app.css?v={{ filemtime(public_path('css/app.css')) }}">
```

### 2.6 Opcode Cache (OPcache)

PHP script-lərinin compiled bytecode-unu cache edir. Hər request-də yenidən parse/compile etməyə ehtiyac qalmır.

*PHP script-lərinin compiled bytecode-unu cache edir. Hər request-də ye üçün kod nümunəsi:*
```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0        ; Production-da 0 (deploy zamanı restart)
opcache.revalidate_freq=0
opcache.interned_strings_buffer=16
opcache.jit=1255                     ; PHP 8.0+ JIT
opcache.jit_buffer_size=100M
```

*opcache.jit_buffer_size=100M üçün kod nümunəsi:*
```php
// OPcache statusu yoxla
$status = opcache_get_status();
echo "Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'];
echo "Memory used: " . $status['memory_usage']['used_memory'];
echo "Hit rate: " . $status['opcache_statistics']['opcache_hit_rate'] . '%';

// Cache-i təmizlə (deploy zamanı)
opcache_reset();
```

---

## 3. Caching Strategies

### 3.1 Cache-Aside (Lazy Loading)

Ən çox istifadə olunan strategiya. Application cache-i yoxlayır, yoxdursa database-dən oxuyur və cache-ə yazır.

*Ən çox istifadə olunan strategiya. Application cache-i yoxlayır, yoxdu üçün kod nümunəsi:*
```php
class ProductService
{
    public function getProduct(int $id): ?Product
    {
        $cacheKey = "product:{$id}";

        // 1. Cache-dən yoxla
        $product = Cache::get($cacheKey);

        if ($product !== null) {
            return $product; // Cache HIT
        }

        // 2. Cache MISS - Database-dən oxu
        $product = Product::with('category', 'images', 'reviews')->find($id);

        if ($product) {
            // 3. Cache-ə yaz
            Cache::put($cacheKey, $product, now()->addHours(2));
        }

        return $product;
    }

    public function updateProduct(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);

        // Cache-i invalidate et
        Cache::forget("product:{$id}");

        return $product;
    }
}

// Daha sadə: Cache::remember
$product = Cache::remember("product:{$id}", 7200, function () use ($id) {
    return Product::with('category', 'images', 'reviews')->find($id);
});
```

**Üstünlükləri:**
- Sadə implementasiya
- Yalnız lazım olan data cache-lənir
- Cache çöksə, application işləyir (database-dən oxuyur)

**Mənfi cəhətləri:**
- İlk request yavaş olur (cache miss)
- Stale data riski (database dəyişdi, cache köhnədir)

### 3.2 Read-Through

Cache layer avtomatik olaraq database-dən oxuyur. Application yalnız cache ilə danışır.

*Cache layer avtomatik olaraq database-dən oxuyur. Application yalnız c üçün kod nümunəsi:*
```php
class ReadThroughCache
{
    public function __construct(
        private CacheInterface $cache,
        private ProductRepository $repository
    ) {}

    public function get(int $id): ?Product
    {
        return $this->cache->remember("product:{$id}", 3600, function () use ($id) {
            return $this->repository->find($id);
        });
    }
}

// İstifadə (application cache layer-dən xəbərsizdir)
$product = $readThroughCache->get(123);
```

### 3.3 Write-Through

Data yazılanda həm cache-ə, həm database-ə eyni anda yazılır. Data consistency təmin edir.

*Data yazılanda həm cache-ə, həm database-ə eyni anda yazılır. Data con üçün kod nümunəsi:*
```php
class WriteThroughCache
{
    public function __construct(
        private CacheInterface $cache,
        private ProductRepository $repository
    ) {}

    public function save(Product $product): Product
    {
        // 1. Database-ə yaz
        $saved = $this->repository->save($product);

        // 2. Cache-ə yaz (eyni anda)
        $this->cache->put("product:{$saved->id}", $saved, now()->addHours(2));

        return $saved;
    }

    public function update(int $id, array $data): Product
    {
        // 1. Database-i yenilə
        $product = $this->repository->update($id, $data);

        // 2. Cache-i yenilə (invalidate deyil, yenilə)
        $this->cache->put("product:{$id}", $product, now()->addHours(2));

        return $product;
    }
}
```

**Üstünlüyü:** Cache həmişə aktual data saxlayır
**Mənfi cəhəti:** Yavaş write (hər write üçün 2 əməliyyat)

### 3.4 Write-Behind (Write-Back)

Data əvvəlcə cache-ə yazılır, sonra asinxron olaraq database-ə yazılır. Çox sürətli write.

*Data əvvəlcə cache-ə yazılır, sonra asinxron olaraq database-ə yazılır üçün kod nümunəsi:*
```php
class WriteBehindCache
{
    public function save(Product $product): void
    {
        // 1. Cache-ə yaz (sürətli)
        Cache::put("product:{$product->id}", $product, now()->addHours(2));

        // 2. Database-ə asinxron yaz (queue ilə)
        dispatch(new SyncProductToDatabase($product));
    }
}

class SyncProductToDatabase implements ShouldQueue
{
    public function __construct(private Product $product) {}

    public function handle(): void
    {
        // Database-ə yaz
        DB::table('products')->updateOrInsert(
            ['id' => $this->product->id],
            $this->product->toArray()
        );
    }
}
```

**Üstünlüyü:** Çox sürətli write
**Mənfi cəhəti:** Cache çöksə data itə bilər, eventual consistency

### 3.5 Refresh-Ahead

Cache expire olmadan əvvəl background-da yenilənir. İstifadəçi heç vaxt cache miss yaşamır.

*Cache expire olmadan əvvəl background-da yenilənir. İstifadəçi heç vax üçün kod nümunəsi:*
```php
class RefreshAheadCache
{
    private float $refreshThreshold = 0.8; // TTL-in 80%-i keçdikdə yenilə

    public function get(string $key, int $ttl, Closure $callback): mixed
    {
        $data = Cache::get($key);
        $remaining = Cache::getStore()->getRedis()->ttl(config('cache.prefix') . $key);

        if ($data !== null) {
            // TTL-in 80%-i keçibsə, background-da yenilə
            if ($remaining < $ttl * (1 - $this->refreshThreshold)) {
                dispatch(function () use ($key, $ttl, $callback) {
                    $freshData = $callback();
                    Cache::put($key, $freshData, $ttl);
                })->afterResponse();
            }
            return $data;
        }

        // Cache MISS
        $data = $callback();
        Cache::put($key, $data, $ttl);
        return $data;
    }
}

// Laravel 11+ Cache::flexible (stale-while-revalidate built-in)
$products = Cache::flexible('products:featured', [300, 600], function () {
    return Product::featured()->get();
});
// 0-300 saniyə: Fresh data
// 300-600 saniyə: Stale data serve olunur, background-da yenilənir
// 600+ saniyə: Full cache miss
```

---

## 4. Cache Invalidation Strategies

> "There are only two hard things in Computer Science: cache invalidation and naming things." — Phil Karlton

### 4.1 TTL (Time To Live)

Ən sadə strategiya. Müəyyən müddət sonra avtomatik expire olur.

*Ən sadə strategiya. Müəyyən müddət sonra avtomatik expire olur üçün kod nümunəsi:*
```php
// Fərqli TTL strategiyaları
Cache::put('product:1', $product, now()->addHours(2));      // Dəyişə biləcək data
Cache::put('countries', $countries, now()->addDays(30));     // Nadir dəyişən data
Cache::put('exchange:rates', $rates, now()->addMinutes(5));  // Tez dəyişən data
Cache::forever('app:settings', $settings);                    // Əl ilə invalidate olunacaq
```

### 4.2 Event-based Invalidation

Data dəyişdikdə cache-i avtomatik invalidate et.

*Data dəyişdikdə cache-i avtomatik invalidate et üçün kod nümunəsi:*
```php
// Model Observer
class ProductObserver
{
    public function updated(Product $product): void
    {
        Cache::forget("product:{$product->id}");
        Cache::forget("products:category:{$product->category_id}");
        Cache::tags(['products'])->flush();
    }

    public function deleted(Product $product): void
    {
        Cache::forget("product:{$product->id}");
        Cache::tags(['products'])->flush();
    }
}

// Event Listener
class InvalidateProductCache
{
    public function handle(ProductUpdated $event): void
    {
        $product = $event->product;

        Cache::forget("product:{$product->id}");

        // Əlaqəli cache-ləri də sil
        Cache::forget("products:featured");
        Cache::forget("products:category:{$product->category_id}");
        Cache::forget("products:search:results:*"); // Pattern-based
    }
}

// Laravel Model Events
class Product extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Product $product) {
            Cache::forget("product:{$product->id}");
        });

        static::deleted(function (Product $product) {
            Cache::forget("product:{$product->id}");
        });
    }
}
```

### 4.3 Tag-based Invalidation

Əlaqəli cache-ləri qrup şəklində invalidate et.

*Əlaqəli cache-ləri qrup şəklində invalidate et üçün kod nümunəsi:*
```php
// Cache yazarkən tag-lar əlavə et
Cache::tags(['products', 'category:electronics'])->put(
    'products:electronics:page:1',
    $products,
    3600
);

Cache::tags(['products', 'category:electronics'])->put(
    'products:electronics:count',
    $count,
    3600
);

Cache::tags(['products', 'featured'])->put(
    'products:featured',
    $featuredProducts,
    3600
);

// Bütün electronics cache-ini sil
Cache::tags(['category:electronics'])->flush();

// Bütün product cache-ini sil
Cache::tags(['products'])->flush();

// Tag-lı cache oxumaq
$products = Cache::tags(['products', 'category:electronics'])->get('products:electronics:page:1');

// Tag-lı remember
$products = Cache::tags(['products'])->remember('products:all', 3600, function () {
    return Product::all();
});
```

> **Qeyd:** Cache tags yalnız `redis` və `memcached` driver-ləri ilə işləyir. `file` və `database` driver-ləri tag dəstəkləmir.

### 4.4 Version-based Invalidation

Cache key-ə versiya nömrəsi əlavə et. Versiya dəyişdikdə köhnə cache avtomatik istifadəsiz qalır.

*Cache key-ə versiya nömrəsi əlavə et. Versiya dəyişdikdə köhnə cache a üçün kod nümunəsi:*
```php
class VersionedCache
{
    public function getVersion(string $entity): int
    {
        return (int) Cache::get("version:{$entity}", 1);
    }

    public function incrementVersion(string $entity): void
    {
        Cache::increment("version:{$entity}");
    }

    public function remember(string $entity, string $key, int $ttl, Closure $callback): mixed
    {
        $version = $this->getVersion($entity);
        $versionedKey = "{$key}:v{$version}";

        return Cache::remember($versionedKey, $ttl, $callback);
    }
}

// İstifadə
$versionedCache = app(VersionedCache::class);

// Oxumaq
$products = $versionedCache->remember('products', 'products:featured', 3600, function () {
    return Product::featured()->get();
});

// Invalidate (versiya artır, köhnə key istifadəsiz qalır)
$versionedCache->incrementVersion('products');
// Növbəti oxumada "products:featured:v2" axtarılacaq - cache miss, yeni data yüklənir
```

---

## 5. Cache Problemləri və Həlləri

### 5.1 Cache Stampede / Thundering Herd

**Problem:** Populyar cache key expire olduqda, yüzlərlə request eyni anda database-ə gedir.

```
Cache expire -> 100 request eyni anda -> 100 database query -> Database overload
```

**Həll 1: Mutex Lock**

```php
class StampedeProtectedCache
{
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        $value = Cache::get($key);

        if ($value !== null) {
            return $value;
        }

        // Lock al
        $lock = Cache::lock("lock:{$key}", 10);

        if ($lock->get()) {
            try {
                // Yenidən yoxla (başqa thread artıq yazmış ola bilər)
                $value = Cache::get($key);
                if ($value !== null) {
                    return $value;
                }

                $value = $callback();
                Cache::put($key, $value, $ttl);

                return $value;
            } finally {
                $lock->release();
            }
        }

        // Lock alına bilmədi - qısa gözlə və yenidən yoxla
        usleep(100000); // 100ms
        return Cache::get($key) ?? $callback();
    }
}
```

**Həll 2: Stale-While-Revalidate (Cache::flexible)**

```php
// Laravel 11+
$products = Cache::flexible('products:featured', [300, 600], function () {
    return Product::featured()->get();
});
// 300s fresh, 300-600s arası stale serve edir, background yeniləyir
```

**Həll 3: Probabilistic Early Expiration**

```php
class ProbabilisticCache
{
    public function remember(string $key, int $ttl, Closure $callback, float $beta = 1.0): mixed
    {
        $data = Cache::get($key);

        if ($data !== null) {
            $expiry = Cache::get("{$key}:expiry");
            $delta = Cache::get("{$key}:delta");

            if ($expiry && $delta) {
                // Probabilistic early recompute
                $now = microtime(true);
                $random = -$delta * $beta * log(random_int(1, PHP_INT_MAX) / PHP_INT_MAX);

                if ($now - $random >= $expiry) {
                    // Erkən yenidən hesabla
                    $start = microtime(true);
                    $data = $callback();
                    $newDelta = microtime(true) - $start;

                    Cache::put($key, $data, $ttl);
                    Cache::put("{$key}:expiry", microtime(true) + $ttl, $ttl);
                    Cache::put("{$key}:delta", $newDelta, $ttl);
                }
            }

            return $data;
        }

        // Cache MISS
        $start = microtime(true);
        $data = $callback();
        $delta = microtime(true) - $start;

        Cache::put($key, $data, $ttl);
        Cache::put("{$key}:expiry", microtime(true) + $ttl, $ttl);
        Cache::put("{$key}:delta", $delta, $ttl);

        return $data;
    }
}
```

### 5.2 Cache Penetration

**Problem:** Mövcud olmayan key-lər üçün davamlı olaraq database-ə gedilir. Cache-dən heç vaxt HIT alınmır.

```
GET product:999999 -> Cache MISS -> Database (yoxdur) -> null
GET product:999999 -> Cache MISS -> Database (yoxdur) -> null  (hər dəfə təkrar)
```

**Həll 1: Null/Empty dəyəri cache-lə**

```php
public function getProduct(int $id): ?Product
{
    $cacheKey = "product:{$id}";

    // has() ilə yoxla (null dəyər cache-lənmiş ola bilər)
    if (Cache::has($cacheKey)) {
        return Cache::get($cacheKey); // null ola bilər
    }

    $product = Product::find($id);

    // Null olsa belə cache-lə (qısa TTL ilə)
    Cache::put($cacheKey, $product, $product ? 3600 : 300);

    return $product;
}
```

**Həll 2: Bloom Filter**

```php
// Mövcud product ID-lərinin Bloom Filter-i
class ProductBloomFilter
{
    private string $key = 'bloom:products';

    public function add(int $productId): void
    {
        // Redis Bloom Filter (RedisBloom module)
        Redis::rawCommand('BF.ADD', $this->key, $productId);
    }

    public function mightExist(int $productId): bool
    {
        return (bool) Redis::rawCommand('BF.EXISTS', $this->key, $productId);
    }
}

// İstifadə
public function getProduct(int $id): ?Product
{
    // Bloom filter ilə yoxla - əgər yoxdursa, mütləq yoxdur
    if (!$this->bloomFilter->mightExist($id)) {
        return null; // Database-ə getmədən null
    }

    return Cache::remember("product:{$id}", 3600, function () use ($id) {
        return Product::find($id);
    });
}
```

### 5.3 Cache Breakdown

**Problem:** Bir "hot key" (çox populyar) expire olduqda yüksək yüklə database-ə gedilir.

**Həll:** Mutex lock + stale-while-revalidate (yuxarıdakı stampede həlləri)

***Həll:** Mutex lock + stale-while-revalidate (yuxarıdakı stampede həl üçün kod nümunəsi:*
```php
// Hot key-ləri forever cache-ləyin və əl ilə yeniləyin
Cache::forever('product:bestseller', $product);

// Update zamanı
public function updateBestseller(Product $product): void
{
    $product->update($data);
    Cache::forever('product:bestseller', $product->fresh());
}
```

### 5.4 Cache Avalanche

**Problem:** Çox sayda cache key eyni anda expire olur. Birdən-birə bütün request-lər database-ə gedir.

```
T=0: 10000 key cache-ləndi (TTL=3600)
T=3600: 10000 key eyni anda expire -> 10000 database query -> Database crash
```

**Həll: Random TTL jitter**

```php
class JitteredCache
{
    /**
     * TTL-ə random jitter əlavə et ki, key-lər eyni anda expire olmasın
     */
    public function remember(string $key, int $baseTtl, Closure $callback): mixed
    {
        // Base TTL-in 10-20% random jitter əlavə et
        $jitter = random_int(0, (int) ($baseTtl * 0.2));
        $ttl = $baseTtl + $jitter;

        return Cache::remember($key, $ttl, $callback);
    }
}

// İstifadə: Bütün product-lar 3600±720 saniyə arası expire olacaq
$cache = new JitteredCache();
foreach ($products as $product) {
    $cache->remember("product:{$product->id}", 3600, fn () => $product);
}
```

**Həll 2: Multi-layer cache**

```php
// L1: Local cache (sürətli, kiçik)
// L2: Redis cache (böyük, shared)
// L3: Database

class MultiLayerCache
{
    private array $localCache = [];

    public function get(string $key, Closure $callback): mixed
    {
        // L1: Local memory cache
        if (isset($this->localCache[$key])) {
            return $this->localCache[$key];
        }

        // L2: Redis
        $value = Cache::get($key);
        if ($value !== null) {
            $this->localCache[$key] = $value;
            return $value;
        }

        // L3: Database
        $value = $callback();
        Cache::put($key, $value, 3600);
        $this->localCache[$key] = $value;

        return $value;
    }
}
```

---

## 6. Laravel Cache System (Dərin)

### 6.1 Cache Drivers

*6.1 Cache Drivers üçün kod nümunəsi:*
```php
// config/cache.php
'stores' => [
    'file' => [
        'driver' => 'file',
        'path'   => storage_path('framework/cache/data'),
    ],

    'database' => [
        'driver'     => 'database',
        'table'      => 'cache',
        'connection' => null,
        'lock_connection' => null,
    ],

    'redis' => [
        'driver'          => 'redis',
        'connection'      => 'cache',
        'lock_connection' => 'default',
    ],

    'memcached' => [
        'driver'        => 'memcached',
        'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
        'servers'       => [
            ['host' => env('MEMCACHED_HOST', '127.0.0.1'), 'port' => 11211, 'weight' => 100],
        ],
    ],

    'array' => [
        'driver'    => 'array',
        'serialize' => false,
    ],

    'null' => [
        'driver' => 'null',
    ],
],
```

| Driver | İstifadə | Üstünlük | Mənfi |
|--------|----------|----------|-------|
| `file` | Development, kiçik app | Qurulma lazım deyil | Yavaş, scalable deyil |
| `database` | Kiçik-orta app | Sadə setup | Database yükü artır |
| `redis` | Production | Çox sürətli, feature-rich | Redis server lazım |
| `memcached` | Production | Sürətli, sadə | Tag dəstəyi yoxdur (Laravel-də var) |
| `array` | Test | Request ərzində işləyir | Request bitdikdə silinir |
| `null` | Test, debug | Cache-i deaktiv edir | Heç nə cache-ləmir |

### 6.2 Əsas Cache Əməliyyatları

*6.2 Əsas Cache Əməliyyatları üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Cache;

// PUT - Cache-ə yaz
Cache::put('key', 'value', $seconds);
Cache::put('key', 'value', now()->addMinutes(30));

// GET - Cache-dən oxu
$value = Cache::get('key');
$value = Cache::get('key', 'default_value');
$value = Cache::get('key', fn () => $this->computeDefault());

// REMEMBER - Yoxla, yoxdursa hesabla və cache-lə
$value = Cache::remember('key', 3600, function () {
    return DB::table('users')->count();
});

// REMEMBER FOREVER - TTL olmadan
$value = Cache::rememberForever('key', function () {
    return Country::all();
});

// FLEXIBLE - Stale-while-revalidate (Laravel 11+)
$value = Cache::flexible('key', [300, 600], function () {
    return Product::featured()->get();
});

// FOREVER - Heç expire olmaz
Cache::forever('key', 'value');

// FORGET - Sil
Cache::forget('key');

// HAS - Mövcuddur?
if (Cache::has('key')) { /* ... */ }

// MISSING
if (Cache::missing('key')) { /* ... */ }

// PULL - Oxu və sil
$value = Cache::pull('key');

// INCREMENT / DECREMENT
Cache::increment('counter');
Cache::increment('counter', 5);
Cache::decrement('counter');
Cache::decrement('counter', 3);

// ADD - Yalnız mövcud deyilsə yaz (atomic)
$added = Cache::add('key', 'value', 3600); // true/false

// MANY - Çoxlu key əməliyyatları
Cache::putMany([
    'key1' => 'value1',
    'key2' => 'value2',
], 3600);

$values = Cache::many(['key1', 'key2', 'key3']);

// FLUSH - Bütün cache-i sil
Cache::flush(); // Diqqətli olun!

// Fərqli store istifadə et
Cache::store('file')->put('key', 'value', 600);
Cache::store('redis')->get('key');
```

### 6.3 Cache Tags

*6.3 Cache Tags üçün kod nümunəsi:*
```php
// Tag-larla cache yazmaq
Cache::tags(['users', 'admins'])->put('admin:list', $admins, 3600);
Cache::tags(['users', 'regular'])->put('user:list', $users, 3600);
Cache::tags(['products', 'electronics'])->put('products:phones', $phones, 3600);

// Tag-larla cache oxumaq
$admins = Cache::tags(['users', 'admins'])->get('admin:list');

// Müəyyən tag-ı olan bütün cache-ləri sil
Cache::tags(['users'])->flush();          // admin:list + user:list silinir
Cache::tags(['electronics'])->flush();     // products:phones silinir

// Tag-lı remember
$products = Cache::tags(['products'])->remember('all:products', 3600, function () {
    return Product::all();
});
```

### 6.4 Cache Lock

*6.4 Cache Lock üçün kod nümunəsi:*
```php
// Sadə lock
$lock = Cache::lock('processing', 10); // 10 saniyə

if ($lock->get()) {
    try {
        // Exclusive əməliyyat
    } finally {
        $lock->release();
    }
}

// Block - lock alınanadək gözlə
$lock = Cache::lock('processing', 10);
try {
    $lock->block(5); // 5 saniyə gözlə
    // Lock alındı
} catch (LockTimeoutException $e) {
    // Timeout - lock alına bilmədi
}

// Block with callback
Cache::lock('processing', 10)->block(5, function () {
    // Lock alındıqda icra olunur
});

// Owner-based lock release
$lock = Cache::lock('processing', 10);
$owner = $lock->get();

// Başqa yerdə (məsələn, queue job-da) release et
Cache::restoreLock('processing', $owner)->release();
```

### 6.5 Atomic Lock Pattern

*6.5 Atomic Lock Pattern üçün kod nümunəsi:*
```php
// Idempotent payment processing
class ProcessPaymentController extends Controller
{
    public function __invoke(Request $request, Order $order): JsonResponse
    {
        $lock = Cache::lock("payment:order:{$order->id}", 30);

        try {
            $lock->block(10);

            // Double-check
            $order->refresh();
            if ($order->isPaid()) {
                return response()->json(['message' => 'Already paid']);
            }

            DB::transaction(function () use ($order) {
                $order->markAsPaid();
                event(new OrderPaid($order));
            });

            return response()->json(['message' => 'Payment successful']);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Payment in progress'], 409);
        } finally {
            $lock?->release();
        }
    }
}
```

---

## 7. HTTP Caching

### 7.1 Cache-Control Header

*7.1 Cache-Control Header üçün kod nümunəsi:*
```php
// Laravel Middleware
class HttpCacheMiddleware
{
    public function handle(Request $request, Closure $next, int $maxAge = 3600): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $response->headers->set('Cache-Control', "public, max-age={$maxAge}");
        }

        return $response;
    }
}

// Route-da istifadə
Route::get('/products', [ProductController::class, 'index'])
    ->middleware('cache.headers:public;max_age=3600;etag');

// Laravel built-in middleware
Route::middleware('cache.headers:public;max_age=2628000;etag')->group(function () {
    Route::get('/api/countries', [CountryController::class, 'index']);
    Route::get('/api/currencies', [CurrencyController::class, 'index']);
});
```

### 7.2 ETag

*7.2 ETag üçün kod nümunəsi:*
```php
class ETagMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $etag = '"' . md5($response->getContent()) . '"';
            $response->headers->set('ETag', $etag);

            // Client-in ETag-ı eyni olsa - 304 Not Modified
            if ($request->headers->get('If-None-Match') === $etag) {
                $response->setStatusCode(304);
                $response->setContent('');
            }
        }

        return $response;
    }
}
```

### 7.3 Last-Modified

*7.3 Last-Modified üçün kod nümunəsi:*
```php
class LastModifiedMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $lastModified = $this->getLastModified($request);

            if ($lastModified) {
                $response->headers->set('Last-Modified', $lastModified->toRfc7231String());

                $ifModifiedSince = $request->headers->get('If-Modified-Since');
                if ($ifModifiedSince) {
                    $clientDate = Carbon::parse($ifModifiedSince);
                    if ($lastModified->lte($clientDate)) {
                        return response('', 304);
                    }
                }
            }
        }

        return $response;
    }
}
```

---

## 8. Laravel Response Caching

### Tam Səhifə Cache (spatie/laravel-responsecache paketi)

*Tam Səhifə Cache (spatie/laravel-responsecache paketi) üçün kod nümunəsi:*
```php
// composer require spatie/laravel-responsecache

// Middleware qeyd et
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \Spatie\ResponseCache\Middlewares\CacheResponse::class,
    ],
];

// Route-da
Route::get('/products', [ProductController::class, 'index'])
    ->middleware('cacheResponse:3600'); // 1 saat

// Cache-i invalidate et
use Spatie\ResponseCache\Facades\ResponseCache;

ResponseCache::clear();                           // Hamısını sil
ResponseCache::forget('/products');                // Müəyyən URL-i sil
ResponseCache::selectCachedItems()
    ->forUrls('/products', '/products/*')
    ->forget();
```

### Manual Response Cache

*Manual Response Cache üçün kod nümunəsi:*
```php
class CachedProductController extends Controller
{
    public function index(): JsonResponse
    {
        $cacheKey = 'response:products:' . md5(request()->fullUrl());

        $data = Cache::remember($cacheKey, 3600, function () {
            return Product::with('category')
                ->active()
                ->paginate(20)
                ->toArray();
        });

        return response()->json($data);
    }
}
```

---

## 9. Query Caching

*9. Query Caching üçün kod nümunəsi:*
```php
// Manual query caching
class ProductRepository
{
    public function getFeatured(): Collection
    {
        return Cache::remember('products:featured', 3600, function () {
            return Product::where('featured', true)
                ->with(['category', 'images', 'reviews' => function ($q) {
                    $q->latest()->limit(5);
                }])
                ->orderBy('sort_order')
                ->get();
        });
    }

    public function getByCategory(int $categoryId, int $page = 1): LengthAwarePaginator
    {
        $cacheKey = "products:category:{$categoryId}:page:{$page}";

        return Cache::tags(['products', "category:{$categoryId}"])->remember(
            $cacheKey,
            1800,
            function () use ($categoryId) {
                return Product::where('category_id', $categoryId)
                    ->with('images')
                    ->paginate(20);
            }
        );
    }

    public function search(string $query, array $filters = []): Collection
    {
        $cacheKey = 'products:search:' . md5($query . serialize($filters));

        return Cache::remember($cacheKey, 600, function () use ($query, $filters) {
            $builder = Product::search($query);

            foreach ($filters as $key => $value) {
                $builder->where($key, $value);
            }

            return $builder->get();
        });
    }
}
```

### Automatic Model Caching Pattern

*Automatic Model Caching Pattern üçün kod nümunəsi:*
```php
// Trait for cacheable models
trait Cacheable
{
    public static function bootCacheable(): void
    {
        static::saved(function (Model $model) {
            $model->flushCache();
        });

        static::deleted(function (Model $model) {
            $model->flushCache();
        });
    }

    public static function findCached(int $id): ?static
    {
        return Cache::remember(
            static::cacheKey($id),
            static::cacheTtl(),
            fn () => static::find($id)
        );
    }

    public static function cacheKey(int $id): string
    {
        return strtolower(class_basename(static::class)) . ":{$id}";
    }

    public static function cacheTtl(): int
    {
        return property_exists(static::class, 'cacheTtl') ? static::$cacheTtl : 3600;
    }

    public function flushCache(): void
    {
        Cache::forget(static::cacheKey($this->id));
    }
}

// Model-də istifadə
class Product extends Model
{
    use Cacheable;

    protected static int $cacheTtl = 7200;
}

// İstifadə
$product = Product::findCached(1);
```

---

## 10. Laravel Artisan Cache Commands

*10. Laravel Artisan Cache Commands üçün kod nümunəsi:*
```bash
# Route cache (production-da mütləq istifadə edin)
php artisan route:cache          # Route-ları cache-lə
php artisan route:clear          # Route cache sil

# Config cache (production-da mütləq istifadə edin)
php artisan config:cache         # Config-ləri cache-lə
php artisan config:clear         # Config cache sil

# View cache
php artisan view:cache           # Blade template-ləri compile et
php artisan view:clear           # Compiled view-ları sil

# Event cache (Laravel 11+)
php artisan event:cache          # Event-listener mapping cache
php artisan event:clear

# General cache
php artisan cache:clear          # Application cache-i sil
php artisan cache:forget key     # Müəyyən key-i sil

# Optimize (route + config + view cache birlikdə)
php artisan optimize             # Hamısını cache-lə
php artisan optimize:clear       # Hamısını təmizlə
```

### Deploy Script-də Cache

*Deploy Script-də Cache üçün kod nümunəsi:*
```bash
#!/bin/bash
# deploy.sh

# Maintenance mode ON
php artisan down --retry=60

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear old caches
php artisan optimize:clear

# Rebuild caches
php artisan optimize
php artisan event:cache

# Clear application cache (Redis)
php artisan cache:clear

# Restart queue workers
php artisan queue:restart

# OPcache reset
php artisan opcache:clear  # və ya curl http://localhost/opcache-reset

# Maintenance mode OFF
php artisan up
```

---

## 11. CDN Caching

### CloudFlare Cache

*CloudFlare Cache üçün kod nümunəsi:*
```php
// CloudFlare cache invalidation
class CloudFlareCacheService
{
    private string $apiToken;
    private string $zoneId;

    public function __construct()
    {
        $this->apiToken = config('services.cloudflare.api_token');
        $this->zoneId = config('services.cloudflare.zone_id');
    }

    /**
     * Müəyyən URL-lərin cache-ini sil
     */
    public function purgeUrls(array $urls): bool
    {
        $response = Http::withToken($this->apiToken)
            ->post("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/purge_cache", [
                'files' => $urls,
            ]);

        return $response->successful();
    }

    /**
     * Bütün cache-i sil
     */
    public function purgeAll(): bool
    {
        $response = Http::withToken($this->apiToken)
            ->post("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/purge_cache", [
                'purge_everything' => true,
            ]);

        return $response->successful();
    }

    /**
     * Tag əsasında sil (Enterprise plan)
     */
    public function purgeTags(array $tags): bool
    {
        $response = Http::withToken($this->apiToken)
            ->post("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/purge_cache", [
                'tags' => $tags,
            ]);

        return $response->successful();
    }
}

// Controller-da response header-ları
class ProductController extends Controller
{
    public function show(Product $product): JsonResponse
    {
        return response()->json($product)
            ->header('Cache-Control', 'public, max-age=3600, s-maxage=86400')
            ->header('CDN-Cache-Control', 'max-age=86400')      // CDN üçün fərqli TTL
            ->header('Cache-Tag', "product-{$product->id}")      // Tag-based purge üçün
            ->header('Surrogate-Key', "product-{$product->id}"); // Varnish/Fastly üçün
    }
}
```

---

## 12. Real-World Caching Strategiyası: E-Commerce

*12. Real-World Caching Strategiyası: E-Commerce üçün kod nümunəsi:*
```php
class EcommerceCacheStrategy
{
    /**
     * Səhifə tipi üzrə cache strategiyası
     */

    // 1. Ana səhifə - CDN + Application cache
    public function homepage(): array
    {
        // Featured products - 30 dəqiqə cache
        $featured = Cache::tags(['homepage', 'products'])->remember(
            'homepage:featured',
            1800,
            fn () => Product::featured()->limit(12)->get()
        );

        // Categories - 2 saat cache
        $categories = Cache::tags(['homepage', 'categories'])->remember(
            'homepage:categories',
            7200,
            fn () => Category::withCount('products')->get()
        );

        // Banners - 1 saat cache
        $banners = Cache::tags(['homepage', 'banners'])->remember(
            'homepage:banners',
            3600,
            fn () => Banner::active()->orderBy('sort')->get()
        );

        return compact('featured', 'categories', 'banners');
    }

    // 2. Product listing - Cache + Pagination
    public function productListing(int $categoryId, Request $request): LengthAwarePaginator
    {
        $cacheKey = "products:cat:{$categoryId}:" . md5($request->fullUrl());

        return Cache::tags(['products', "category:{$categoryId}"])->remember(
            $cacheKey,
            900, // 15 dəqiqə
            function () use ($categoryId, $request) {
                return Product::where('category_id', $categoryId)
                    ->filter($request->all())
                    ->with('images', 'reviews')
                    ->paginate(24);
            }
        );
    }

    // 3. Product detail - Uzun cache + event-based invalidation
    public function productDetail(int $productId): Product
    {
        return Cache::tags(['products'])->remember(
            "product:{$productId}:detail",
            7200, // 2 saat
            function () use ($productId) {
                return Product::with([
                    'category',
                    'images',
                    'attributes',
                    'reviews' => fn ($q) => $q->latest()->limit(10),
                    'relatedProducts' => fn ($q) => $q->limit(8),
                ])->findOrFail($productId);
            }
        );
    }

    // 4. Cart - Session-based, Redis, qısa TTL
    public function cart(int $userId): array
    {
        return Cache::remember("cart:{$userId}", 1800, function () use ($userId) {
            return Cart::where('user_id', $userId)
                ->with('items.product.images')
                ->first()
                ?->toArray() ?? [];
        });
    }

    // 5. Stock - Çox qısa cache (Redis atomic)
    public function getStock(int $productId): int
    {
        $key = "stock:{$productId}";

        if (!Cache::has($key)) {
            $stock = Product::find($productId)?->stock ?? 0;
            Cache::put($key, $stock, 60); // 1 dəqiqə
        }

        return (int) Cache::get($key);
    }

    // 6. Search - Orta cache + user-specific
    public function search(string $query, array $filters): Collection
    {
        $cacheKey = 'search:' . md5($query . serialize($filters));

        return Cache::remember($cacheKey, 600, function () use ($query, $filters) {
            return Product::search($query)->filter($filters)->get();
        });
    }

    /**
     * Cache invalidation strategiyası
     */
    public function onProductUpdated(Product $product): void
    {
        // Direct cache silmə
        Cache::forget("product:{$product->id}:detail");
        Cache::forget("stock:{$product->id}");

        // Tag-based silmə
        Cache::tags(["category:{$product->category_id}"])->flush();
        Cache::tags(['homepage'])->flush();

        // CDN cache silmə
        app(CloudFlareCacheService::class)->purgeUrls([
            url("/products/{$product->slug}"),
            url("/api/products/{$product->id}"),
        ]);
    }
}
```

---

## 13. Multi-Layer Caching

*13. Multi-Layer Caching üçün kod nümunəsi:*
```php
class MultiLayerCacheService
{
    private array $l1 = []; // In-process memory (request ərzində)

    /**
     * L1: Process Memory (1ms) -> L2: Redis (5ms) -> L3: Database (100ms)
     */
    public function get(string $key, int $ttl, Closure $callback): mixed
    {
        // L1: Local memory
        if (isset($this->l1[$key])) {
            return $this->l1[$key];
        }

        // L2: Redis
        $value = Cache::get($key);
        if ($value !== null) {
            $this->l1[$key] = $value;
            return $value;
        }

        // L3: Database (and rebuild cache)
        $value = $callback();

        if ($value !== null) {
            Cache::put($key, $value, $ttl);
            $this->l1[$key] = $value;
        }

        return $value;
    }

    /**
     * Invalidate across all layers
     */
    public function forget(string $key): void
    {
        unset($this->l1[$key]);
        Cache::forget($key);
    }
}

// Singleton olaraq register et (request ərzində eyni instance)
// AppServiceProvider.php
$this->app->singleton(MultiLayerCacheService::class);
```

---

## 14. Caching Best Practices

### 14.1 Key Naming Convention

*14.1 Key Naming Convention üçün kod nümunəsi:*
```php
// Format: prefix:entity:identifier:variant
'products:featured'                    // Tək resurs
'products:category:5:page:2'          // Filtrli resurs
'user:123:profile'                    // İstifadəçi resursu
'search:' . md5($query)              // Dynamic query hash
'response:GET:/api/products:' . md5($params) // Response cache
```

### 14.2 Serialization

*14.2 Serialization üçün kod nümunəsi:*
```php
// Yalnız lazım olan data-nı cache-ləyin
// PIS: Bütün model-i cache-ləmək (əlaqələr, hidden attributes, etc.)
Cache::put('user:1', User::find(1), 3600);

// YAXSI: Yalnız lazım olan data
Cache::put('user:1', User::find(1)->only(['id', 'name', 'email']), 3600);

// ƏN YAXSI: Array/DTO olaraq cache-ləmək
Cache::put('user:1', [
    'id'    => $user->id,
    'name'  => $user->name,
    'email' => $user->email,
], 3600);
```

### 14.3 Cache Warm-up

*14.3 Cache Warm-up üçün kod nümunəsi:*
```php
// Artisan command ilə cache warm-up
class WarmUpCache extends Command
{
    protected $signature = 'cache:warm-up';
    protected $description = 'Warm up frequently accessed cache';

    public function handle(): void
    {
        $this->info('Warming up product cache...');
        
        Product::chunk(100, function ($products) {
            foreach ($products as $product) {
                Cache::put(
                    "product:{$product->id}",
                    $product->load('category', 'images'),
                    7200
                );
            }
            $this->output->write('.');
        });

        $this->info("\nWarming up category cache...");
        $categories = Category::withCount('products')->get();
        Cache::put('categories:all', $categories, 7200);

        $this->info('Cache warm-up completed.');
    }
}

// Scheduler-da (deploy sonrası və ya gündəlik)
// app/Console/Kernel.php
$schedule->command('cache:warm-up')->dailyAt('05:00');
```

### 14.4 Monitoring Cache Effectiveness

*14.4 Monitoring Cache Effectiveness üçün kod nümunəsi:*
```php
// Cache hit/miss tracking
class CacheMetrics
{
    public static function trackHit(string $key): void
    {
        Redis::hincrby('cache:metrics:hits', date('Y-m-d:H'), 1);
        Redis::hincrby('cache:metrics:keys:hits', $key, 1);
    }

    public static function trackMiss(string $key): void
    {
        Redis::hincrby('cache:metrics:misses', date('Y-m-d:H'), 1);
        Redis::hincrby('cache:metrics:keys:misses', $key, 1);
    }

    public static function getHitRate(string $date): float
    {
        $hits = array_sum(array_map('intval',
            Redis::hgetall("cache:metrics:hits") ?? []
        ));
        $misses = array_sum(array_map('intval',
            Redis::hgetall("cache:metrics:misses") ?? []
        ));

        $total = $hits + $misses;
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
}

// Custom Cache wrapper
class MonitoredCache
{
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        if (Cache::has($key)) {
            CacheMetrics::trackHit($key);
            return Cache::get($key);
        }

        CacheMetrics::trackMiss($key);
        $value = $callback();
        Cache::put($key, $value, $ttl);

        return $value;
    }
}
```

---

## 15. İntervyu Sualları və Cavabları

### S1: Cache-Aside ilə Read-Through arasında fərq nədir?

**Cavab:**
- **Cache-Aside**: Application özü cache-i yoxlayır, miss olduqda database-dən oxuyur və cache-ə yazır. Application cache logic-dən xəbərdardır.
- **Read-Through**: Cache layer özü database-dən oxuyur. Application yalnız cache ilə danışır, database-dən xəbəri yoxdur. Daha təmiz abstraction.

### S2: Cache Stampede problemi nədir və necə həll edirsiniz?

**Cavab:** Populyar bir cache key expire olduqda, yüzlərlə request eyni anda database-ə gedir, bu da database-i aşırı yükləyir.

Həllər:
1. **Mutex Lock** — yalnız bir request database-ə gedir, digərləri gözləyir
2. **Stale-While-Revalidate** (Cache::flexible) — köhnə data serve olunarkən background-da yenilənir
3. **Probabilistic Early Expiration** — expire olmadan əvvəl random yeniləmə
4. **Never expire + event-based invalidation** — hot key-lər heç expire olmaz

### S3: Cache Penetration, Breakdown və Avalanche arasında fərq nədir?

**Cavab:**
- **Penetration**: Mövcud olmayan data-ya davamlı müraciət (hər dəfə DB-yə gedir). Həll: Null dəyəri cache-ləmək, Bloom Filter.
- **Breakdown**: Bir populyar (hot) key expire olduqda yüksək yük. Həll: Mutex lock, never expire.
- **Avalanche**: Çox sayda key eyni anda expire olur. Həll: Random TTL jitter, multi-layer cache.

### S4: Laravel-də cache tag-lar necə işləyir?

**Cavab:** Cache tags yalnız Redis və Memcached driver-ləri ilə işləyir. Hər tag bir Redis Set-dir və cache key-lərin siyahısını saxlayır. `Cache::tags(['products'])->flush()` çağırıldıqda, "products" set-indəki bütün key-lər silinir. File driver tag dəstəkləmir.

### S5: HTTP caching necə işləyir? ETag nədir?

**Cavab:**
- **Cache-Control**: Browser-a və proxy-lərə cache davranışını deyir (max-age, public/private, no-cache, no-store)
- **ETag**: Response content-in hash-idir. Browser növbəti request-də `If-None-Match` header-i ilə göndərir. Əgər ETag eynidir, server 304 Not Modified qaytarır (body göndərmədən)
- **Last-Modified**: Resursun son dəyişmə tarixi. Browser `If-Modified-Since` ilə yoxlayır
- **s-maxage**: CDN/proxy üçün ayrı TTL (browser-a təsir etmir)

### S6: Cache::remember vs Cache::flexible fərqi nədir?

**Cavab:**
- `Cache::remember('key', 3600, $callback)` — 3600 saniyə cache-ləyir, expire olduqda yeni data gətirir (bu müddətdə istifadəçi gözləyir)
- `Cache::flexible('key', [300, 600], $callback)` — 300 saniyə fresh data verir, 300-600 arası stale data serve edir amma background-da yeniləyir (stale-while-revalidate). İstifadəçi heç vaxt gözləmir.

### S7: Multi-layer caching necə qurulur?

**Cavab:**
```
L1: Process Memory (APCu / static variable) — μs latency, request-scoped
L2: Redis/Memcached — ms latency, shared across servers
L3: Database — 10-100ms latency, source of truth
```
Oxuma: L1 -> L2 -> L3 (ilk tapılan qaytarılır, aşağıdakı layer-lərə getmədən)
Yazma: L3 -> L2 -> L1 (source of truth-dan başlayaraq yuxarı propagate)
Invalidation: Bütün layer-lərdə silinir

### S8: OPcache nədir və niyə vacibdir?

**Cavab:** PHP hər request-də script-ləri parse -> compile -> execute edir. OPcache compiled bytecode-u shared memory-də saxlayır, beləliklə parse/compile addımları atlanır. Production-da PHP performansını 2-3x artıra bilər.

Vacib konfiqurasiya: `validate_timestamps=0` (production-da) — fayl dəyişikliklərini yoxlamaz, deploy zamanı `opcache_reset()` çağırmalısınız.

### S9: Cache warming nədir və nə vaxt lazımdır?

**Cavab:** Deploy sonrası və ya cache flush-dan sonra bütün cache boş olur. İlk istifadəçilər yavaş response alır (cold cache). Cache warming — deploy zamanı ən çox istifadə olunan data-nı əvvəlcədən cache-ləməkdir. E-commerce saytda populyar product-lar, homepage data, category listing-lər warm-up edilə bilər.

### S10: Production Laravel application üçün cache strategiyanız nədir?

**Cavab:**
1. **Redis** — primary cache driver
2. **OPcache** — PHP bytecode cache (validate_timestamps=0)
3. **Route/Config/View cache** — `php artisan optimize`
4. **Application cache** — Cache::remember + tags + event-based invalidation
5. **HTTP cache headers** — Cache-Control, ETag (statik resurslar üçün)
6. **CDN** — CloudFlare/CloudFront (statik assets + API response)
7. **Database query optimization** — eager loading + cache + index
8. **Cache warm-up** — deploy script-də əvvəlcədən cache-ləmə
9. **Monitoring** — hit rate tracking, slow query detection
10. **Random TTL jitter** — cache avalanche-ın qarşısını almaq üçün

Bu bələdçi caching-in bütün aspektlərini əhatə edir. İntervyuda bu mövzuları dərin bilmək, performans optimization haqqında ciddi anlayış göstərəcək.

---

## Anti-patterns

**1. Mutable data-nı uzun müddət cache-ləmək**
User balansı, stock miqdarı kimi tez dəyişən data-nı 1 saatlıq cache-ləmək — stale data göstərilir. Mutable critical data ya cache-lənməməli, ya da çox qısa TTL ilə + write-through strategiyası istifadə edilməlidir.

**2. Cache avalanche (thunder of TTL)**
Çoxlu cache key-in eyni vaxtda expire olması → hamısı eyni anda DB-yə gedir → DB çöküşü. Həll: TTL-ə random jitter əlavə et (`ttl + random(0, 30)`) ki, expirasiya yayılsın.

**3. Cache stampede (thundering herd)**
Bir popular key expire olur → yüzlərlə request eyni anda DB-yə gedir. Həll: mutex lock (yalnız biri DB-yə getsin, digərləri gözləsin) yaxud XFetch (probabilistic early refresh).

**4. Cache-i bypass edib həmişə DB oxumaq**
`Cache::forget()` əvəzinə `DB::table()->get()` çağırmaq — caching-in heç bir faydası olmur. Invalidation pattern-i (forget + put) düzgün tətbiq edilməlidir.

**5. Object-ləri serialize etmədən cache-ləmək**
Laravel `Cache::put($key, $eloquentModel)` — model serialize olur, amma loaded relationships, hidden fields, casts bəzən düzgün serialize olmur. Cache-ə primitive data və ya DTO qoy.

**6. Cache-in özü fail olduqda error atmaq**
Redis down olduqda tüm application çökürsə — bu yanlışdır. Cache miss kimi davranıb DB-dən oxu. `try/catch` ilə Redis xətalara qarşı fallback strategiyası lazımdır.

**7. Cache key collision**
`Cache::put('user', ...)` — bütün user-lər üçün eyni key. Key-ə ID daxil et: `"user:{$id}"`. Multi-tenant-da tenant_id də key-ə əlavə edilməlidir.

**8. Hər şeyi cache-ləmək**
Nadir oxunan, həmişə dəyişən, yaxud çox böyük data-nı cache-ləmək — memory israfı, cache thrashing. Yalnız: tez-tez oxunan, az dəyişən, hesablanması baha olan data cache-lənməlidir.

**9. Cache-in Authorization-a Təsirini Nəzərə Almamaq**
`user:profile:{id}` cache-ləyərkən həssas məlumatları (admin flags, role-lar) cache-ə salmaq — fərqli permission-lu istifadəçilər eyni cache-dən oxuya bilər. Cache key-inə user ID-ni və ya permission context-ini daxil edin; ya da həssas məlumatları heç vaxt cache-ləməyin.

**10. Distributed Lock Olmadan Cache Refresh**
Çoxlu worker-in eyni anda eyni cache key-i rebuild etməsi — hər worker DB-yə bahalı sorğu göndərir, eyni anda eyni data yazılır. `Cache::lock()` ilə yalnız bir worker rebuild etsin, digərləri lock açılana qədər gözləsin (yaxud köhnə data qaytarsın).
