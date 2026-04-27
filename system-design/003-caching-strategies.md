# Caching Strategies (Junior)

## İcmal

Caching tez-tez istifadə olunan data-nı sürətli yaddaşda saxlamaqdır. Database-ə hər dəfə
müraciət etmək əvəzinə, cavabı cache-dən oxumaq 10-100x daha sürətlidir. Caching performansı
artırır, database yükünü azaldır və istifadəçi təcrübəsini yaxşılaşdırır.

```
Latency müqayisə:
L1 Cache:       ~1 ns
L2 Cache:       ~4 ns
RAM:            ~100 ns
Redis (network): ~0.5 ms
SSD:            ~0.1 ms
HDD:            ~10 ms
Database query:  ~5-50 ms
API call:       ~50-500 ms
```


## Niyə Vacibdir

Database-ə hər sorğu 10–100ms latency deməkdir; cache hit isə 1ms-dən azdır. Read-heavy sistemlərdə caching olmadan horizontal scale praktik cəhətdən mümkün olmur. Yanlış cache invalidation isə stale data yaradır — bu trade-off-u bilmək kritikdir.

## Əsas Anlayışlar

### Caching Strategiyaları

**1. Cache-Aside (Lazy Loading)**
Application özü cache-i idarə edir. Əvvəl cache-ə baxır, yoxdursa DB-dən oxuyub cache-ə yazır.

```
Read:
1. App -> Cache-dən oxu (GET key)
2. Cache HIT -> Data qaytarılır
   Cache MISS -> DB-dən oxu -> Cache-ə yaz -> Data qaytarılır

Write:
1. DB-yə yaz
2. Cache-i invalidate et (DELETE key)
```

Üstünlük: Yalnız lazım olan data cache olunur, cache down olsa app işləyir
Mənfi: İlk request həmişə yavaş (cold start), data stale ola bilər

**2. Read-Through**
Cache özü DB-dən oxuyur. App yalnız cache ilə danışır.

```
1. App -> Cache-dən oxu
2. Cache MISS -> Cache özü DB-dən oxuyur -> Saxlayır -> App-a qaytarır
```

Üstünlük: App kodu sadələşir
Mənfi: Cache library/provider bunu dəstəkləməlidir

**3. Write-Through**
Hər yazma əməliyyatı əvvəl cache-ə, sonra DB-yə yazılır.

```
1. App -> Cache-ə yaz
2. Cache -> DB-yə yaz
3. Hər ikisi uğurlu olduqda confirm
```

Üstünlük: Cache həmişə fresh, data loss riski az
Mənfi: Yazma latency artır (2 yazma), heç oxunmayacaq data da cache olunur

**4. Write-Behind (Write-Back)**
Cache-ə yazılır, DB-yə asinxron yazılır.

```
1. App -> Cache-ə yaz (dərhal cavab)
2. Cache -> async/batch DB-yə yaz (bir müddət sonra)
```

Üstünlük: Yazma çox sürətli, batch write ilə DB yükü azalır
Mənfi: Cache crash olsa data itirilə bilər

**5. Write-Around**
Birbaşa DB-yə yazılır, cache yenilənmir. Oxunduqda cache-aside ilə cache dolur.

```
Write: App -> DB-yə yaz (cache-ə toxunmur)
Read: Cache MISS -> DB-dən oxu -> Cache-ə yaz
```

### Cache Invalidation

Ən çətin problem: cache-dəki köhnə data-nı nə vaxt silmək?

**1. TTL (Time To Live)**
```
SET user:123 "{name: Orkhan}" EX 3600  # 1 saat sonra silinir
```

**2. Event-based Invalidation**
```
User updated -> DELETE cache key "user:123"
```

**3. Version-based**
```
GET user:123:v5  # version dəyişdikdə köhnə key oxunmur
```

### Eviction Policies

Cache dolduqda hansı data silinməlidir?

**LRU (Least Recently Used):** Ən uzun müddət oxunmamış data silinir. Ən populyar.
**LFU (Least Frequently Used):** Ən az oxunan data silinir.
**FIFO (First In First Out):** Ən əvvəl yazılan data silinir.
**Random:** Təsadüfi seçim.
**TTL-based:** Expire vaxtı yaxın olan əvvəl silinir.

### Redis vs Memcached

```
Feature          | Redis              | Memcached
-----------------+--------------------+------------------
Data structures  | String,List,Set,   | Yalnız string
                 | Hash,Sorted Set    |
Persistence      | RDB, AOF           | Yox
Replication      | Master-Replica     | Yox (client-side)
Cluster          | Redis Cluster      | Client-side sharding
Pub/Sub          | Var                | Yox
Lua scripting    | Var                | Yox
Max value size   | 512MB              | 1MB
Multithreaded    | Xeyr (single)      | Bəli
```

## Arxitektura

### Multi-Layer Caching

```
Browser Cache (localStorage, Service Worker)
       |
CDN Cache (CloudFront, Cloudflare)
       |
API Gateway Cache
       |
Application Cache (Redis/Memcached)
       |
Database Query Cache
       |
Database Buffer Pool
```

### Redis Cluster Arxitekturası

```
[Redis Master 1] --- [Redis Replica 1a]
   (slot 0-5460)     [Redis Replica 1b]

[Redis Master 2] --- [Redis Replica 2a]
   (slot 5461-10922)

[Redis Master 3] --- [Redis Replica 3a]
   (slot 10923-16383)

16384 hash slot, hər master bir hissəni idarə edir.
Key -> CRC16(key) % 16384 = slot number -> Master node
```

## Nümunələr

### Laravel Cache Facade

```php
use Illuminate\Support\Facades\Cache;

// Sadə cache əməliyyatları
Cache::put('user:123', $user, now()->addHours(1));
$user = Cache::get('user:123');
Cache::forget('user:123');

// Cache-aside pattern (remember)
$user = Cache::remember('user:123', 3600, function () {
    return User::with('profile', 'roles')->find(123);
});

// Forever cache (manual invalidation lazım)
Cache::forever('settings:app', $settings);

// Atomic operations
Cache::increment('page:views:homepage');
Cache::decrement('stock:product:456');

// Has / Missing
if (Cache::has('user:123')) {
    // ...
}

// Multiple keys
$values = Cache::many(['user:1', 'user:2', 'user:3']);
Cache::putMany([
    'user:1' => $user1,
    'user:2' => $user2,
], 3600);
```

### Tagged Caching

```php
// Tag ilə cache - qrup halında invalidate etmək üçün
Cache::tags(['users', 'admin'])->put('user:1', $admin, 3600);
Cache::tags(['users', 'regular'])->put('user:2', $user, 3600);

// Bütün user cache-lərini sil
Cache::tags(['users'])->flush();

// Yalnız admin cache-lərini sil
Cache::tags(['admin'])->flush();
```

### Model Caching Pattern

```php
// app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    protected static function booted(): void
    {
        // Auto-invalidate on update/delete
        static::updated(function (Product $product) {
            Cache::forget("product:{$product->id}");
            Cache::tags(['products'])->flush();
        });

        static::deleted(function (Product $product) {
            Cache::forget("product:{$product->id}");
            Cache::tags(['products'])->flush();
        });
    }

    public static function findCached(int $id): ?self
    {
        return Cache::remember("product:{$id}", 3600, function () use ($id) {
            return static::with('category', 'images')->find($id);
        });
    }

    public static function getPopularCached(int $limit = 10)
    {
        return Cache::tags(['products'])->remember(
            "products:popular:{$limit}",
            1800,
            fn() => static::orderBy('views', 'desc')->limit($limit)->get()
        );
    }
}
```

### Cache Driver Configuration

```php
// config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
    'memcached' => [
        'driver' => 'memcached',
        'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
        'servers' => [
            ['host' => '10.0.1.1', 'port' => 11211, 'weight' => 100],
            ['host' => '10.0.1.2', 'port' => 11211, 'weight' => 100],
        ],
    ],
    'array' => [
        'driver' => 'array',
        'serialize' => false, // test üçün
    ],
],

// config/database.php
'redis' => [
    'cache' => [
        'host' => env('REDIS_CACHE_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 2,
        'prefix' => 'cache:',
    ],
],
```

### Cache Lock (Distributed Locking)

```php
// Cache stampede prevention
$value = Cache::lock('processing:order:123', 10)->block(5, function () {
    // Yalnız bir process bu kodu icra edir
    return OrderService::processOrder(123);
});

// Manual lock
$lock = Cache::lock('export:users', 120);

if ($lock->get()) {
    try {
        // Long-running operation
        ExportService::exportUsers();
    } finally {
        $lock->release();
    }
} else {
    return response()->json(['message' => 'Export already in progress'], 409);
}
```

### HTTP Cache Headers

```php
// app/Http/Controllers/ProductController.php
public function show(Product $product)
{
    $etag = md5($product->updated_at->timestamp);

    return response()->json($product)
        ->header('Cache-Control', 'public, max-age=300')
        ->header('ETag', $etag)
        ->header('Last-Modified', $product->updated_at->toRfc7231String());
}

// Middleware for cache headers
// app/Http/Middleware/CacheResponse.php
class CacheResponse
{
    public function handle($request, Closure $next, $maxAge = 60)
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $response->headers->set('Cache-Control', "public, max-age={$maxAge}");
        }

        return $response;
    }
}
```

## Real-World Nümunələr

**Facebook/Meta:** TAO - distributed cache layer MySQL qarşısında. Milyardlarla read/saniyə.
Social graph data üçün write-through cache. Cache hit rate >99%.

**Twitter:** Redis Cluster istifadə edir timeline caching üçün. Hər tweet fanout zamanı
follower-ların timeline cache-lərinə yazılır. 100TB+ in-memory cache.

**Netflix:** EVCache (Memcached üzərində) istifadə edir. Multi-region replication ilə.
Subscriber data, viewing history, recommendations cache olunur.

**Stack Overflow:** Yalnız 9 web server ilə milyonlarla request handle edir, çünki
aggressive caching strategiyası var. Redis + local in-memory cache.

## Praktik Tapşırıqlar

**S: Cache-aside vs read-through fərqi?**
C: Cache-aside-da application cache miss zamanı DB-dən oxuyub cache-ə yazır. Read-through-da
cache layer özü DB-dən oxuyur. Cache-aside daha çox kontrol verir, read-through kodu sadələşdirir.

**S: Cache stampede nədir və necə həll olunur?**
C: Populyar key expire olduqda yüzlərlə request eyni anda DB-yə gedir. Həllər:
1) Lock - yalnız bir request DB-yə getsin, digərləri gözləsin
2) Early expiration - TTL bitməzdən əvvəl background-da yeniləmək
3) Probabilistic early expiration - random factor əlavə etmək

**S: Cache invalidation niyə çətindir?**
C: "There are only two hard things in CS: cache invalidation and naming things."
Distributed sistemdə cache-i dəqiq vaxtında invalidate etmək çətindir. Race conditions,
network delays, eventual consistency problemləri var.

**S: Redis-i primary database kimi istifadə etmək olarmı?**
C: Redis persistence dəstəkləyir (RDB snapshots, AOF), amma primary DB kimi
tövsiyə olunmur. RAM-limited, complex query yoxdur. Amma session, cache, real-time
leaderboard, pub/sub kimi use case-lər üçün əladır.

## Praktik Baxış

1. **Cache hit rate izləyin** - 95%+ olmalıdır, aşağıdırsa strategiyanı yoxlayın
2. **TTL həmişə qoyun** - forever cache data staleness-ə səbəb olur
3. **Cache key convention** - `entity:id:field` formatı: `user:123:profile`
4. **Serialization formatı** - JSON oxunaqlıdır, msgpack/igbinary daha kompaktdır
5. **Cache warming** - Deploy sonrası populyar data-nı əvvəlcədən cache edin
6. **Graceful degradation** - Cache down olsa app işləməyə davam etməlidir
7. **Monitor memory** - Redis maxmemory və eviction policy düzgün konfiqurasiya edin
8. **Avoid thundering herd** - Populyar key-lərin TTL-inə jitter əlavə edin


## Əlaqəli Mövzular

- [Database Design](09-database-design.md) — cache-in arxasındakı mənbə
- [Distributed Cache Design](49-distributed-cache-design.md) — Redis cluster arxitekturası
- [Consistency Patterns](32-consistency-patterns.md) — stale cache trade-off-ları
- [Data Partitioning](26-data-partitioning.md) — cache şardlama
- [Probabilistic Data Structures](33-probabilistic-data-structures.md) — Bloom filter ilə cache miss azaltmaq
