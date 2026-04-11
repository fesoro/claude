# Distributed Cache Invalidation

## Problem

Production mühitində bir neçə server eyni vaxtda işləyir:

```
[Server 1] [Server 2] [Server 3]
    ↓           ↓           ↓
[Local Cache] [Local Cache] [Local Cache]
         ↓           ↓           ↓
              [Shared Redis]
```

**Ssenari**: İstifadəçi `Server 1`-ə sorğu göndərib məhsul qiymətini dəyişdirir. Server 1 öz cache-ini silir. Lakin Server 2 və Server 3 hələ köhnə qiyməti keş etmişdir. Növbəti istifadəçi Server 2-yə gəlir — köhnə qiyməti görür.

Bu **stale cache** problemidir və distributed sistemlərin ən çətin məsələlərindən biridir.

> "There are only two hard things in Computer Science: cache invalidation and naming things." — Phil Karlton

---

## Cache Invalidation Challenges

1. **Race Condition**: Eyni vaxtda iki server eyni key-i yeniləyir, birinin dəyəri itir
2. **Thundering Herd**: Cache invalidate olunduqda yüzlərlə server eyni anda DB-yə sorğu göndərir
3. **Partial Invalidation**: Əlaqəli cache-lər sinxron silinmir — inconsistent state
4. **Network Partition**: Invalidation mesajı çatmır, köhnə data qalır
5. **Hot Key**: Çox tez-tez invalidate olunan key — Redis-ə yük

---

## Invalidation Strategiyaları

### 1. TTL-Based (Time-To-Live)

Ən sadə yanaşma: cache müəyyən müddətdən sonra özü köhnəlir.

*Bu kod TTL əsaslı sadə cache yazma nümunəsini göstərir:*

```php
Cache::put('product:123', $product, ttl: 300); // 5 dəqiqə
```

**Müsbət**: Heç bir əlavə infrastruktur lazım deyil, sadədir
**Mənfi**: Data 5 dəqiqəyə qədər köhnə qala bilər (eventual consistency)
**Use case**: Çox tez-tez dəyişməyən data — kateqoriya siyahısı, konfiqurasiya

### 2. Event-Driven Invalidation (Pub/Sub)

Model dəyişdikdə event fire olur, subscriber-lər cache-i silir.

```
Model updated → Event fired → Redis Pub/Sub → All servers → Cache deleted
```

**Müsbət**: Real-time invalidation, güclü consistency
**Mənfi**: Infrastructure mürəkkəbliyi, single point of failure (Redis)

### 3. Tag-Based Invalidation

Cache key-lərini tag-larla qruplaşdır, tag-a görə toplu sil.

*Bu kod tag-based cache yazma və toplu silmə əməliyyatını göstərir:*

```php
Cache::tags(['products', 'category:5'])->put('product:123', $data, 3600);
// Sonra:
Cache::tags(['products'])->flush(); // Bütün product cache-lərini sil
```

**Müsbət**: Dəqiq toplu silmə, əlaqəli cache-lər birlikdə silinir
**Mənfi**: Redis-də əlavə memory, tag tracking overhead

### 4. Cache Versioning / Key Namespacing

Key-ə version nömrəsi əlavə et. Dəyişiklik zamanı version-ı artır — köhnə key-lər avtomatik "invalidate" olur (sadəcə unreach olur).

*Bu kod version artırmaqla köhnə cache key-lərini əlçatmaz edən versioning strategiyasını göstərir:*

```php
$version = Cache::get('product_cache_version', 1);
$key = "products:v{$version}:category:5";

// Invalidation zamanı:
Cache::increment('product_cache_version');
// Köhnə key-lər keçilməz olur, TTL ilə özü silinir
```

**Müsbət**: Atomik invalidation, race condition yoxdur
**Mənfi**: Köhnə key-lər TTL-ə qədər memory tutur

### 5. Write-Through Invalidation

Hər yazma əməliyyatında cache dərhal yenilənir (silinmir, update edilir).

*Bu kod DB-yə yazarkən eyni anda cache-i də yeniləyən write-through strategiyasını göstərir:*

```php
// DB-yə yaz
$product = Product::find(123);
$product->update(['price' => 99.99]);

// Cache-i də dərhal yenilə
Cache::put("product:123", $product->fresh(), 3600);
```

**Müsbət**: Cache həmişə fresh, DB-dən oxuma azalır
**Mənfi**: Write latency artır, cache/DB sync race condition

---

## Cache Consistency Models

| Model | Açıqlama | Latency | Consistency |
|-------|----------|---------|-------------|
| **Strong** | Oxuma həmişə ən son yazmadan sonra | Yüksək | Tam |
| **Read-Your-Writes** | Özün yazdığını oxuya bilirsən | Orta | Qismən |
| **Monotonic Reads** | Köhnəyə geri dönmürsən | Orta | Qismən |
| **Eventual** | Nəhayət hamı sinxronlaşır | Aşağı | Zəif |

---

## Redis Pub/Sub ilə Broadcast Invalidation

```
Server 1 (yazır) → Redis Channel: 'cache-invalidation' → Server 2, Server 3
                                                      → hər server öz cache-ini silir
```

*Bu kod Pub/Sub üçün ayrı Redis connection-un konfiqurasiyasını göstərir:*

```php
// config/database.php — Redis konfiqurasiyası
'redis' => [
    'client' => 'phpredis',

    'default' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => 6379,
        'database' => 0,
    ],

    // Pub/Sub üçün ayrı connection (blocking operation)
    'pubsub' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => 6379,
        'database' => 1,
    ],
],
```

---

## CacheInvalidator Service

*Bu kod tək key, batch və tag əsaslı invalidation-ı Redis Pub/Sub ilə bütün serverlara yayan servis sinfini göstərir:*

```php
<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CacheInvalidator
{
    private const INVALIDATION_CHANNEL = 'cache:invalidation';
    private const LOCAL_SERVER_ID_KEY  = 'server_id';

    private string $serverId;

    public function __construct()
    {
        // Hər serverin unikal ID-si (başlanğıcda set edilir)
        $this->serverId = config('app.server_id', gethostname());
    }

    /**
     * Tək key invalidate et — yalnız local
     */
    public function invalidate(string $key): void
    {
        Cache::forget($key);
        Log::debug("Cache invalidated locally: {$key}");
    }

    /**
     * Tək key-i bütün serverlarda invalidate et
     */
    public function invalidateGlobally(string $key): void
    {
        // Local-da sil
        Cache::forget($key);

        // Digər server-lara pub/sub ilə xəbər ver
        $this->publishInvalidation([
            'type'      => 'key',
            'key'       => $key,
            'server_id' => $this->serverId,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Tag-ə görə bütün serverlarda invalidate et
     */
    public function invalidateByTagGlobally(string $tag): void
    {
        Cache::tags([$tag])->flush();

        $this->publishInvalidation([
            'type'      => 'tag',
            'tag'       => $tag,
            'server_id' => $this->serverId,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Çoxlu key-i batch olaraq invalidate et (tranzaksiya daxilində)
     */
    public function invalidateBatch(array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $this->publishInvalidation([
            'type'      => 'batch',
            'keys'      => $keys,
            'server_id' => $this->serverId,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Redis channel-a invalidation mesajı göndər
     */
    private function publishInvalidation(array $payload): void
    {
        try {
            Redis::publish(
                self::INVALIDATION_CHANNEL,
                json_encode($payload)
            );
        } catch (\Exception $e) {
            // Pub/Sub fail olsa log et, lakin əsas əməliyyatı bloklama
            Log::error('Cache invalidation publish failed', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    /**
     * Gələn invalidation mesajını işlə
     */
    public function handleInvalidationMessage(string $message): void
    {
        $payload = json_decode($message, true);

        // Öz mesajımıza cavab vermə (loop qarşısını al)
        if ($payload['server_id'] === $this->serverId) {
            return;
        }

        match ($payload['type']) {
            'key'   => Cache::forget($payload['key']),
            'tag'   => Cache::tags([$payload['tag']])->flush(),
            'batch' => array_map(fn($k) => Cache::forget($k), $payload['keys']),
            default => Log::warning("Unknown invalidation type: {$payload['type']}"),
        };

        Log::debug("Cache invalidated from remote server: {$payload['server_id']}", $payload);
    }
}
```

---

## Model Observer → Cache Invalidation Event

*Bu kod məhsul yadda saxlanıldıqda və ya silindikdə cache invalidation event-ini fire edən observer-i göstərir:*

```php
<?php

namespace App\Observers;

use App\Events\ModelCacheInvalidationRequired;
use App\Models\Product;

class ProductObserver
{
    public function saved(Product $product): void
    {
        // Event fire et — listener cache-i silər
        event(new ModelCacheInvalidationRequired(
            modelClass: Product::class,
            modelId: $product->id,
            tags: ['products', "category:{$product->category_id}"],
            keys: [
                "product:{$product->id}",
                "product:{$product->id}:details",
                "product:{$product->id}:reviews_summary",
            ]
        ));
    }

    public function deleted(Product $product): void
    {
        event(new ModelCacheInvalidationRequired(
            modelClass: Product::class,
            modelId: $product->id,
            tags: ['products', "category:{$product->category_id}", 'featured_products'],
            keys: ["product:{$product->id}"]
        ));
    }
}
```

*Bu kod cache invalidation üçün lazımi məlumatları daşıyan event value object-ini göstərir:*

```php
<?php

namespace App\Events;

class ModelCacheInvalidationRequired
{
    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
        public readonly array $tags = [],
        public readonly array $keys = [],
    ) {}
}
```

*Bu kod invalidation event-ini alıb key-ləri və tag-ları batch şəkildə sildirən listener-i göstərir:*

```php
<?php

namespace App\Listeners;

use App\Events\ModelCacheInvalidationRequired;
use App\Services\Cache\CacheInvalidator;

class InvalidateModelCache
{
    public function __construct(
        private readonly CacheInvalidator $invalidator
    ) {}

    public function handle(ModelCacheInvalidationRequired $event): void
    {
        // Bütün key-ləri batch invalidate et
        if (!empty($event->keys)) {
            $this->invalidator->invalidateBatch($event->keys);
        }

        // Bütün tag-ları invalidate et
        foreach ($event->tags as $tag) {
            $this->invalidator->invalidateByTagGlobally($tag);
        }
    }
}
```

---

## Redis Pub/Sub Listener — Background Worker

Bu artisan command hər serverdə background process kimi işləyir.

*Bu kod Redis Pub/Sub kanalını dinləyib gələn invalidation mesajlarını emal edən background worker command-ını göstərir:*

```php
<?php

namespace App\Console\Commands;

use App\Services\Cache\CacheInvalidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CacheInvalidationListenerCommand extends Command
{
    protected $signature   = 'cache:listen-invalidation';
    protected $description = 'Redis Pub/Sub-dan cache invalidation mesajlarını dinlə';

    public function __construct(
        private readonly CacheInvalidator $invalidator
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Cache invalidation listener başladı. Kanal: cache:invalidation');

        // Bu blocking call-dır — worker daima işləyir
        Redis::connection('pubsub')->subscribe(
            ['cache:invalidation'],
            function (string $message, string $channel) {
                $this->info("Mesaj alındı [{$channel}]: " . substr($message, 0, 100));

                try {
                    $this->invalidator->handleInvalidationMessage($message);
                } catch (\Exception $e) {
                    $this->error("Xəta: {$e->getMessage()}");
                }
            }
        );
    }
}
```

Supervisor konfiqurasiyası (hər serverdə):

*Bu kod hər serverdə listener process-ini avtomatik başladan Supervisor konfiqurasiyasını göstərir:*

```ini
[program:cache-invalidation-listener]
command=php /var/www/artisan cache:listen-invalidation
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/cache-invalidation.log
```

---

## Tag-Based Cache ilə Redis

*Bu kod məhsulları tag-larla cache-ə alan və yeniləmə zamanı əlaqəli tag-ları flush edən repository-ni göstərir:*

```php
<?php

namespace App\Repositories;

use App\Models\Product;
use App\Services\Cache\CacheInvalidator;
use Illuminate\Support\Facades\Cache;

class ProductRepository
{
    public function __construct(
        private readonly CacheInvalidator $invalidator
    ) {}

    /**
     * Məhsulu tag-larla keşlə
     */
    public function find(int $id): ?Product
    {
        return Cache::tags(['products', "product:{$id}"])
            ->remember("product:{$id}", 3600, fn() => Product::find($id));
    }

    /**
     * Kateqoriyaya görə məhsullar — kateqoriya tag-ı ilə keşlə
     */
    public function findByCategory(int $categoryId): \Illuminate\Support\Collection
    {
        return Cache::tags(['products', "category:{$categoryId}"])
            ->remember(
                "products:category:{$categoryId}",
                1800,
                fn() => Product::where('category_id', $categoryId)->get()
            );
    }

    /**
     * Məhsul yeniləndikdə: yalnız həmin məhsula aid cache-ləri sil
     */
    public function update(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);

        // Yalnız bu məhsula aid tag-ı flush et
        Cache::tags(["product:{$id}"])->flush();

        // Kateqoriya cache-ini də yenilə
        Cache::tags(["category:{$product->category_id}"])->flush();

        return $product->fresh();
    }
}
```

---

## Hierarchical Cache Invalidation

Ana-uşaq əlaqəli məlumatlarda: ana element dəyişdikdə bütün uşaq cache-lər də silinməlidir.

*Bu kod ana element dəyişdikdə bütün uşaq cache key-lərini avtomatik silən iyerarxik invalidator-u göstərir:*

```php
<?php

namespace App\Services\Cache;

class HierarchicalCacheInvalidator
{
    /**
     * Cache ağac strukturu:
     * category:1
     *   └── product:101
     *   └── product:102
     *         └── product:102:reviews
     *         └── product:102:images
     */
    private array $hierarchy = [
        'category:{id}' => [
            'products:category:{id}',
            'category:{id}:featured',
            'category:{id}:count',
        ],
        'product:{id}' => [
            'product:{id}:details',
            'product:{id}:reviews',
            'product:{id}:images',
            'product:{id}:related',
        ],
        'user:{id}' => [
            'user:{id}:profile',
            'user:{id}:orders',
            'user:{id}:wishlist',
            'user:{id}:addresses',
        ],
    ];

    public function invalidate(string $pattern, string|int $id): void
    {
        $resolvedPattern = str_replace('{id}', $id, $pattern);

        // Ana key-i sil
        Cache::forget($resolvedPattern);

        // Uşaq key-ləri tap və sil
        if (isset($this->hierarchy[$pattern])) {
            foreach ($this->hierarchy[$pattern] as $childPattern) {
                $childKey = str_replace('{id}', $id, $childPattern);
                Cache::forget($childKey);
            }
        }

        // Pub/Sub ilə digər serverlara göndər
        app(CacheInvalidator::class)->publishInvalidation([
            'type'    => 'hierarchical',
            'pattern' => $pattern,
            'id'      => $id,
        ]);
    }
}
```

---

## Cache Warming After Invalidation

Cache silinəndən sonra "thundering herd" problemi: minlərlə sorğu eyni anda DB-yə gedir.

**Həll**: Cache silinən kimi dərhal yenidən doldur (warming), sorğular gözləməsin.

*Bu kod cache silinəndən sonra thundering herd problemini önləmək üçün distributed lock ilə cache-i yenidən dolduran job-u göstərir:*

```php
<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

class WarmProductCacheJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(private readonly int $productId) {}

    public function handle(): void
    {
        // Distributed lock: yalnız bir worker warming etsin
        $lock = Cache::lock("warming:product:{$this->productId}", 30);

        if (!$lock->get()) {
            // Başqa worker artıq warming edir, keç
            return;
        }

        try {
            $product = Product::with(['category', 'images', 'reviews'])->find($this->productId);

            if (!$product) {
                return;
            }

            // Əsas məhsul cache-i
            Cache::tags(['products', "product:{$this->productId}"])
                ->put("product:{$this->productId}", $product, 3600);

            // Detail cache
            Cache::tags(["product:{$this->productId}"])
                ->put("product:{$this->productId}:details", $product->toDetailArray(), 3600);

            // Review summary cache
            $reviewSummary = [
                'average'  => $product->reviews->avg('rating'),
                'count'    => $product->reviews->count(),
                'breakdown' => $product->reviews->groupBy('rating')->map->count(),
            ];

            Cache::tags(["product:{$this->productId}"])
                ->put("product:{$this->productId}:reviews_summary", $reviewSummary, 1800);

        } finally {
            $lock->release();
        }
    }
}
```

### Invalidation + Warming kombinasiyası

*Bu kod məhsul yadda saxlanıldıqda əvvəlcə cache-i silən, sonra warming job-unu dispatch edən observer-i göstərir:*

```php
<?php

namespace App\Observers;

use App\Jobs\WarmProductCacheJob;
use App\Services\Cache\CacheInvalidator;
use App\Models\Product;

class ProductCacheObserver
{
    public function __construct(
        private readonly CacheInvalidator $invalidator
    ) {}

    public function saved(Product $product): void
    {
        // 1. Əvvəlcə invalidate et
        $this->invalidator->invalidateByTagGlobally("product:{$product->id}");

        // 2. Dərhal warming job-unu queue-ya göndər
        // Cache silinən kimi yenidən doldurulsun — thundering herd qarşısını al
        WarmProductCacheJob::dispatch($product->id)
            ->onQueue('cache-warming')
            ->delay(now()->addSeconds(1)); // 1 saniyə gecikmə — invalidation yayılsın
    }
}
```

---

## Distributed Cache Lock During Warming

Thundering herd problemini tam həll etmək üçün "probabilistic early expiration":

*Bu kod cache miss zamanı yalnız bir process-in DB-yə getməsini, digərlərinin gözləməsini təmin edən distributed lock strategiyasını göstərir:*

```php
<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

class SmartCacheService
{
    /**
     * Cache miss zamanı yalnız bir process DB-yə getsin,
     * digərləri qısa gözləsin.
     */
    public function remember(
        string $key,
        int $ttl,
        callable $callback,
        int $lockTtl = 10
    ): mixed {
        // Adi cache hit-i
        $value = Cache::get($key);
        if ($value !== null) {
            return $value;
        }

        // Lock al — yalnız bir process compute etsin
        $lock = Cache::lock("lock:{$key}", $lockTtl);

        if ($lock->get()) {
            // Lock aldım — məni hesablamaq lazımdır
            try {
                // İkinci dəfə yoxla (lock gözlərkən başqası doldurmuş ola bilər)
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

        // Lock ala bilmədim — başqa process hesablayır
        // Qısa spin-wait ilə gözlə
        $attempts = 0;
        while ($attempts < 10) {
            usleep(100_000); // 100ms gözlə
            $value = Cache::get($key);
            if ($value !== null) {
                return $value;
            }
            $attempts++;
        }

        // Hələ hazır deyil — DB-dən birbaşa oxu (fallback)
        return $callback();
    }
}
```

---

## Monitoring Stale Cache Hits

*Bu kod cache hit, miss və köhnəlmiş hit-ləri izləyib Redis-ə metrika yazan monitoring servisini göstərir:*

```php
<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

class MonitoredCacheService
{
    private array $metrics = [
        'hits'       => 0,
        'misses'     => 0,
        'stale_hits' => 0,
    ];

    /**
     * Cache hit/miss-lərini monitor et
     * Stale cache-i detect etmək üçün "shadow" key saxla
     */
    public function getWithStalenessCheck(string $key, callable $freshData): mixed
    {
        $value = Cache::get($key);
        $freshness = Cache::get("freshness:{$key}");

        if ($value === null) {
            // Cache miss
            $this->recordMiss($key);
            $fresh = $freshData();
            Cache::put($key, $fresh, 3600);
            Cache::put("freshness:{$key}", now()->toIso8601String(), 3600);
            return $fresh;
        }

        if ($freshness === null) {
            // Keş var amma freshness yoxdur — köhnədir
            $this->recordStaleHit($key);
            // Background-da yenilə
            dispatch(fn() => Cache::put($key, $freshData(), 3600))->afterResponse();
        } else {
            $this->recordHit($key);
        }

        return $value;
    }

    private function recordHit(string $key): void
    {
        \Illuminate\Support\Facades\Redis::incr('cache_metrics:hits');
    }

    private function recordMiss(string $key): void
    {
        \Illuminate\Support\Facades\Redis::incr('cache_metrics:misses');
        // Tez-tez miss olan key-ləri log et
        \Illuminate\Support\Facades\Redis::zincrby('cache_metrics:miss_keys', 1, $key);
    }

    private function recordStaleHit(string $key): void
    {
        \Illuminate\Support\Facades\Redis::incr('cache_metrics:stale_hits');
        \Illuminate\Support\Facades\Log::warning("Stale cache served: {$key}");
    }

    /**
     * Cache hit ratio-nu hesabla — monitoring dashboard üçün
     */
    public static function getHitRatio(): float
    {
        $hits   = (int)\Illuminate\Support\Facades\Redis::get('cache_metrics:hits');
        $misses = (int)\Illuminate\Support\Facades\Redis::get('cache_metrics:misses');
        $total  = $hits + $misses;

        return $total > 0 ? round($hits / $total * 100, 2) : 0.0;
    }
}
```

---

## Tam İş Axışı

```
1. HTTP Request gəlir
2. ProductRepository::find(123) çağrılır
3. Cache::tags(['products', 'product:123'])->get('product:123')
   → Cache HIT: qaytar (2ms)
   → Cache MISS: DB-dən al, cache-lə, qaytar (50ms)

4. Admin məhsulu yeniləyir
5. Product::update() → Eloquent fires `saved` event
6. ProductCacheObserver::saved() çağrılır
7. CacheInvalidator::invalidateByTagGlobally('product:123')
   → Local Cache::tags(['product:123'])->flush()
   → Redis PUBLISH 'cache:invalidation' {type: 'tag', tag: 'product:123'}

8. Server 2 və Server 3-də CacheInvalidationListener mesajı alır
   → Cache::tags(['product:123'])->flush() — local cache silinir

9. WarmProductCacheJob dispatch edilir
10. Job işləyir: DB-dən al, cache-lə
11. Növbəti request gəldikdə cache hazırdır
```

---

## İntervyu Sualları

**S: Cache invalidation niyə çətindir?**

C: Birincisi, distributed sistemdə "nə vaxt" sorusu var — invalidation mesajı çatana qədər köhnə data görünür. İkincisi, race condition — iki proses eyni vaxtda yazanda hansının dəyəri qalır? Üçüncüsü, thundering herd — cache silinəndə bütün serverlər eyni anda DB-yə gedir. Dördüncüsü, partial failure — invalidation mesajı bəzi serverlara çatmır.

**S: Tag-based invalidation vs key-based invalidation fərqi nədir?**

C: Key-based-də hər dəfə dəqiq key-i bilmək lazımdır. Bir model dəyişəndə hansı key-ləri silmək lazım olduğunu izləmək çətin olur. Tag-based-də model-ə aid bütün cache-lər eyni tag-la işarələnir, bir komanda ilə hamısı silinir. Ancaq Redis-də tag tracking əlavə memory tələb edir.

**S: Thundering herd problemini necə həll edərdiniz?**

C: Distributed lock ilə — yalnız bir proses cache-i yenilər, qalanlar lock buraxılana qədər gözləyir (ya da köhnə data servis edirlər). Cache warming — invalidation baş verəndən dərhal sonra background job yeni datanı cache-ə yükləyir, sorğular gəlməzdən əvvəl. Probabilistic early expiration — TTL bitməzdən əvvəl kiçik ehtimalla cache yenilənir.

**S: Redis Pub/Sub etibarlımı, mesaj itkisi olurmu?**

C: Redis Pub/Sub "fire-and-forget"dır — subscriber offline-dırsa mesajı almır. Əgər etibarlı delivery lazımdırsa, Redis Streams (XADD/XREADGROUP) istifadə edilməlidir — mesajlar persist olunur, missed mesajlar replay edilə bilər. Alternativ: Laravel queues ilə broadcast events.

**S: Write-through vs cache-aside (lazy loading) nədir?**

C: Cache-aside-da tətbiq əvvəlcə cache-ə baxır, miss-dirsə DB-dən alır, cache-ə yazır. Write-through-da hər DB yazısı eyni zamanda cache-ə yazılır. Write-through cache həmişə fresh-dir amma write latency artır. Cache-aside sadədir amma ilk sorğu həmişə DB-yə gedir (cold start).

**S: Write-behind (write-back) cache pattern nədir, nə vaxt istifadə edilir?**
C: Tətbiq yalnız cache-ə yazır (sürətli cavab), DB-yə yazma background-da async baş verir. Üstünlük: write latency minimaldır, yüksək write throughput lazımdırsa ideal. Çatışmazlıq: cache çökərsə yazılmayan data itirilir — durability riski. Use case: analytics counter-lar, view sayğacları, like count-lar (itirilsə kritik deyil). Kritik data (sifariş, ödəniş) üçün tövsiyə edilmir.

**S: Redis Cluster-da tag-based cache istifadə etmək niyə problematikdir?**
C: Laravel Cache tags, tag tracking məlumatlarını ayrı Redis key-lərə yazır. Redis Cluster-da fərqli key-lər fərqli node-larda (slot-larda) ola bilər. Tag tracking key-i ilə data key-ləri fərqli node-larda olarsa MULTI/EXEC (pipeline) xəta verir (`CROSSSLOT`). Həll: Redis Cluster yerinə Redis Sentinel (single shard, HA) istifadə et. Cluster vacibdirsə — hash tag ilə eyni slota məcbur et: `{product:123}:cache`.

**S: Cache invalidation-ı DB transaction-dan içindən etmək düzgündürmü?**
C: Əgər `DB::transaction()` içindən `Cache::forget()` çağırılırsa — DB rollback olsa belə cache silinmiş qalır (stale data). Düzgün yanaşma: invalidation-ı `afterCommit` ilə dispatch et. Laravel-də: `dispatch(new InvalidateCacheJob(...))->afterCommit()` — transaction commit olduqda job queue-ya göndərilir. Outbox pattern daha etibarlıdır: DB-yə `cache_invalidations` cədvəlinə qeyd yaz (eyni transaction-da), ayrıca worker oxuyub invalidation edir.

---

## Anti-patterns

**1. Hər yazma əməliyyatında cache-i flush etmək**
`Cache::flush()` — bütün server-in cache-ini sil. Sonrakı bütün request-lər DB-yə gedir → thundering herd. Yalnız əlaqədar key/tag-ları sil.

**2. Cache invalidation-ı DB transaction-dan kənarda**
DB rollback olur, amma cache artıq silinib — stale data. `DB::transaction()` daxilindən invalidation dispatch et ki, transaction uğurlu olduqda cache silindi bilsin (queue + afterCommit).

**3. Redis Pub/Sub ilə kritik invalidation**
Pub/Sub mesajını cache server offline olduqda alır — subscriber miss edir. Kritik invalidation üçün Redis Streams (persistent) yaxud Outbox pattern istifadə et.

**4. TTL-ə güvənib invalidation etməmək**
"TTL 5 dəqiqədir, stale data olsun" — ödəniş, inventory kimi critical data üçün yanlışdır. Write-through + event-based invalidation lazımdır.

**5. Key pattern-i bilmədən selective invalidation**
`Cache::forget("products_page_*")` — wildcard delete Redis-də KEYS * istifadə edir (blocking). Tag-based invalidation (Laravel Cache tags) daha etibarlı yanaşmadır.
