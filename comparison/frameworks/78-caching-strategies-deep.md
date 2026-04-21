# Caching Strategies — Dərin Müqayisə

## Giriş

Cache — DB və ya xarici API-dən gələn cavabı yaddaşda saxlayıb sonrakı oxumalarda sürətli qaytaran layer-dir. Düzgün istifadə olunanda latency 100x-1000x düşür, DB yükü azalır, user experience hiss olunacaq dərəcədə yaxşılaşır. Səhv istifadə olunanda isə — stale data (köhnə məlumat), stampede (eyni anda çoxlu miss), hot key (tək açara həddən artıq yük) kimi problemlər yaradır.

Spring-də əsas alət **`@Cacheable`**, **`@CachePut`**, **`@CacheEvict`**, **`@Caching`** annotasiyalarıdır — `CacheManager` abstraksiyası altında Caffeine, Redis, EhCache, Hazelcast kimi provider-lar dayanır. Açar generasiyası SpEL ifadələri (`#id`, `#user.id`) ilə idarə olunur; `condition` və `unless` ilə şərtli cache qurula bilər. Multi-level cache (local Caffeine + distributed Redis) hazır deyil — custom `CacheManager` ilə qurulur.

Laravel-də əsas API **`Cache::remember`**, **`Cache::rememberForever`**, **`Cache::tags()`**, **`Cache::lock`**-dır. Driver-lər: file, database, redis, memcached, dynamodb, array, octane. `Cache::lock()` stampede-dən qorunmaq üçün ideal. Tag-based eviction yalnız Redis/Memcached-də işləyir. Octane ilə in-memory per-worker cache mümkündür — super sürətli, amma worker-lər arasında paylaşılmır.

Bu sənəd 13-caching.md-dən daha dərin: cache patterns (cache-aside, read-through, write-through, write-behind), stampede qorunması, invalidation strategies, multi-level cache, hot key problemi — real hot-product ssenarisi ilə.

---

## Spring-də istifadəsi

### 1) `@EnableCaching` + provider seçimi

```java
@Configuration
@EnableCaching
public class CacheConfig {
    @Bean
    public CacheManager cacheManager(CaffeineCacheManagerBuilderCustomizer ... ) {
        CaffeineCacheManager mgr = new CaffeineCacheManager();
        mgr.setCaffeine(Caffeine.newBuilder()
            .maximumSize(10_000)
            .expireAfterWrite(Duration.ofMinutes(10))
            .recordStats());
        return mgr;
    }
}
```

Dependency (Caffeine — ən sürətli local):

```xml
<dependency>
    <groupId>com.github.ben-manes.caffeine</groupId>
    <artifactId>caffeine</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-cache</artifactId>
</dependency>
```

### 2) `@Cacheable`, `@CachePut`, `@CacheEvict`, `@Caching`

```java
@Service
public class ProductService {

    // Oxu — cache-də yoxdursa, metodu işə sal, nəticəni saxla
    @Cacheable(cacheNames = "products", key = "#id")
    public Product findById(Long id) {
        return productRepo.findById(id).orElseThrow();
    }

    // Həmişə işə sal + nəticəni cache-ə yaz (update ssenarisi)
    @CachePut(cacheNames = "products", key = "#product.id")
    public Product update(Product product) {
        return productRepo.save(product);
    }

    // Cache-dən sil (delete ssenarisi)
    @CacheEvict(cacheNames = "products", key = "#id")
    public void delete(Long id) {
        productRepo.deleteById(id);
    }

    // Bütün cache-i təmizlə
    @CacheEvict(cacheNames = "products", allEntries = true)
    public void refreshAll() { }

    // Kombinə — bir metod həm cache invalidate, həm yeni qoy
    @Caching(
        evict = { @CacheEvict(cacheNames = "products", key = "#product.id") },
        put   = { @CachePut(cacheNames = "products-by-slug", key = "#product.slug") }
    )
    public Product saveAndReindex(Product product) {
        return productRepo.save(product);
    }
}
```

### 3) SpEL ilə açar generasiyası

```java
@Cacheable(cacheNames = "users", key = "#id")
public User byId(Long id) { ... }

@Cacheable(cacheNames = "users", key = "#user.tenantId + ':' + #user.email")
public User find(UserQuery user) { ... }

@Cacheable(cacheNames = "report", key = "T(java.util.Objects).hash(#from, #to, #type)")
public Report fetch(LocalDate from, LocalDate to, String type) { ... }

// Custom KeyGenerator
@Bean public KeyGenerator keyGen() {
    return (target, method, params) -> target.getClass().getSimpleName()
        + "." + method.getName()
        + ":" + Arrays.hashCode(params);
}

@Cacheable(cacheNames = "x", keyGenerator = "keyGen")
public X fetch(long a, long b) { ... }
```

### 4) `condition` və `unless`

```java
// Cache-ə yalnız id > 10 olanda yazılsın
@Cacheable(cacheNames = "products", key = "#id", condition = "#id > 10")

// Cache-ə yazılmasın əgər nəticə null-dursa və ya boş list-dirsə
@Cacheable(cacheNames = "search", key = "#query", unless = "#result == null or #result.isEmpty()")

// Birləşmiş
@Cacheable(cacheNames = "pricing",
           key = "#sku + ':' + #region",
           condition = "#region != 'test'",
           unless = "#result.price == 0")
public Price quote(String sku, String region) { ... }
```

**Fərq:** `condition` metoddan əvvəl qiymətləndirilir (input-a baxaraq hesablamağa dəyməzsə, cache-ə heç baxmırıq); `unless` sonra qiymətləndirilir (nəticə əsaslı skip).

### 5) Cache providers — müqayisə

**ConcurrentMapCache** — default, memory-də HashMap. Prod üçün uyğun deyil (eviction yox, statistika yox).

**Caffeine** — ən sürətli local. W-TinyLFU algorithm, refresh-ahead, statistika.

```java
@Bean
public Caffeine<Object, Object> caffeineConfig() {
    return Caffeine.newBuilder()
        .maximumSize(50_000)
        .expireAfterWrite(Duration.ofMinutes(10))
        .expireAfterAccess(Duration.ofMinutes(30))
        .refreshAfterWrite(Duration.ofMinutes(5))
        .weigher((Object k, Object v) -> estimateSize(v))
        .maximumWeight(100_000_000)            // 100MB
        .recordStats();
}
```

**Redis** (Spring Data Redis):

```yaml
spring:
  cache:
    type: redis
  data:
    redis:
      host: localhost
      port: 6379
  redis:
    cache:
      time-to-live: 600000       # 10 dəqiqə
      key-prefix: "myapp:"
      use-key-prefix: true
```

```java
@Bean
public RedisCacheManager redisCacheManager(RedisConnectionFactory cf) {
    RedisCacheConfiguration cfg = RedisCacheConfiguration.defaultCacheConfig()
        .entryTtl(Duration.ofMinutes(10))
        .serializeKeysWith(RedisSerializationContext.SerializationPair.fromSerializer(new StringRedisSerializer()))
        .serializeValuesWith(RedisSerializationContext.SerializationPair.fromSerializer(new GenericJackson2JsonRedisSerializer()));
    return RedisCacheManager.builder(cf)
        .cacheDefaults(cfg)
        .withCacheConfiguration("products", cfg.entryTtl(Duration.ofHours(1)))
        .build();
}
```

**Hazelcast** / **EhCache 3** / **Infinispan** — cluster-aware distributed cache variantları.

### 6) Multi-level cache — Caffeine + Redis

```java
public class TwoLevelCache implements Cache {
    private final Cache local;       // Caffeine
    private final Cache remote;      // Redis

    @Override
    public ValueWrapper get(Object key) {
        ValueWrapper v = local.get(key);
        if (v != null) return v;
        v = remote.get(key);
        if (v != null) local.put(key, v.get());   // Warm up L1
        return v;
    }

    @Override
    public void put(Object key, Object value) {
        remote.put(key, value);
        local.put(key, value);
    }

    @Override
    public void evict(Object key) {
        local.evict(key);
        remote.evict(key);
        // Əlavə: Redis pub/sub ilə bütün node-lara evict hadisəsi göndər
    }
}

public class TwoLevelCacheManager extends AbstractCacheManager {
    private final CacheManager localMgr;
    private final CacheManager remoteMgr;

    @Override
    protected Collection<? extends Cache> loadCaches() { return List.of(); }

    @Override
    protected Cache getMissingCache(String name) {
        return new TwoLevelCache(localMgr.getCache(name), remoteMgr.getCache(name));
    }
}
```

**Vacib:** L1 (local) node-lar arası inconsistent ola bilər — Redis pub/sub ilə `evict` hadisələri yayım et, yaxud qısa TTL qoy (10 saniyə).

### 7) Cache patterns

**Cache-aside (ən geniş):**

```java
Product p = cache.get(id);
if (p == null) {
    p = db.find(id);
    if (p != null) cache.put(id, p);
}
return p;
```

`@Cacheable` bu pattern-i avtomatik icra edir.

**Read-through:** Cache-in özü DB-dən oxumağı bilir.

```java
// Caffeine LoadingCache
LoadingCache<Long, Product> cache = Caffeine.newBuilder()
    .maximumSize(10_000)
    .expireAfterWrite(Duration.ofMinutes(10))
    .build(id -> productRepo.findById(id).orElse(null));

Product p = cache.get(id);   // Avtomatik load
```

**Write-through:** Yazı eyni anda həm cache, həm DB-yə gedir.

```java
public Product update(Product p) {
    productRepo.save(p);
    cache.put(p.getId(), p);
    return p;
}
```

**Write-behind (async):** Cache-ə yaz, DB-yə async flush et (Redis streams / Kafka ilə).

```java
public void recordView(Long productId) {
    cache.incr("views:" + productId);
    // Hər 30 saniyədə batch DB-yə
}
```

Riskli — crash olsa, flush olmamış data itir.

### 8) Cache stampede (thundering herd)

Məsələ: TTL bitir, 1000 request eyni anda cache miss olur, 1000-i də DB-yə gedir → DB ölür.

**Həll 1 — Caffeine single-flight:**

```java
// get(key, callable) yalnız BİR thread-ə metodu işlətdirir, qalanlar nəticəni gözləyir
Product p = caffeineCache.get(id, this::loadFromDb);
```

Spring `@Cacheable(sync = true)`:

```java
@Cacheable(cacheNames = "products", key = "#id", sync = true)
public Product find(Long id) { return productRepo.findById(id).orElseThrow(); }
```

Yalnız tək thread DB-yə gedir, qalanlar gözləyir.

**Həll 2 — Probabilistic early expiration (XFetch):**

TTL bitmədən əvvəl təsadüfi şəkildə refresh et:

```java
if (ttlRemaining < random() * beta * computeTime) {
    // Erkən refresh
}
```

**Həll 3 — Redis-də distributed lock:**

```java
String lockKey = "lock:product:" + id;
Boolean acquired = redis.setIfAbsent(lockKey, "1", Duration.ofSeconds(5));
if (acquired) {
    try { return loadAndCache(id); }
    finally { redis.delete(lockKey); }
} else {
    Thread.sleep(50);
    return findFromCache(id);   // Digərinin cache-ə yazmağını gözlə
}
```

### 9) Invalidation strategies

- **TTL-based** — ən sadə, amma stale risk var (TTL bitənə qədər köhnə data göstərilir).
- **Event-based** — DB update → cache evict. `@CacheEvict` ilə avtomatik.
- **Cache versioning** — açara versiya əlavə et: `product:v5:123`. Yeni versiya → avtomatik miss → köhnə keys expire olur.
- **CDC-based** — Debezium Postgres WAL-ı oxuyur, Kafka-ya yazır, consumer cache-i invalidate edir. `system-design/51-cdc-outbox.md`-də ətraflı.

### 10) Hot key problemi

Tək açar (misal: `product:123`) dəqiqədə 100k sorğu alır — Redis tək node bu yükü çəkə bilmir.

**Həllər:**
1. **Local cache ön plana** — Caffeine-də saxla, Redis-ə az gedirsən.
2. **Açarı şardla** — `product:123:shard0`, `shard1`, ..., `shard9` (yazanda random shard-a yazarsan, oxuyanda random oxuyursan).
3. **Read replicas** — Redis Cluster read replica-larına route et.
4. **Client-side caching** — Redis 6+ `CLIENT TRACKING` ilə client yaddaşında saxla.

### 11) Real ssenari — hot product + stampede protection (Spring)

```java
@Service
public class ProductCacheService {

    private final ProductRepository repo;
    private final Cache local;
    private final StringRedisTemplate redis;

    @Cacheable(cacheNames = "products", key = "#id", sync = true)
    public Product findById(Long id) {
        String lockKey = "lock:product:" + id;
        Boolean got = redis.opsForValue().setIfAbsent(lockKey, "1", Duration.ofSeconds(5));
        if (Boolean.TRUE.equals(got)) {
            try {
                return repo.findById(id).orElseThrow();
            } finally {
                redis.delete(lockKey);
            }
        }
        // Başqa thread lock tutdu — qısa gözlə, sonra cache-dən yenə oxu
        try { Thread.sleep(50); } catch (InterruptedException ignored) { Thread.currentThread().interrupt(); }
        return repo.findById(id).orElseThrow();
    }

    @CacheEvict(cacheNames = "products", key = "#product.id")
    public Product update(Product product) {
        return repo.save(product);
    }
}
```

---

## Laravel-də istifadəsi

### 1) Driver konfiqurasiyası

```php
// config/cache.php
'default' => env('CACHE_STORE', 'redis'),

'stores' => [
    'file'     => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],
    'database' => ['driver' => 'database', 'table' => 'cache'],
    'redis'    => ['driver' => 'redis', 'connection' => 'cache', 'lock_connection' => 'default'],
    'memcached'=> ['driver' => 'memcached', 'servers' => [...]],
    'dynamodb' => ['driver' => 'dynamodb', 'key' => env('AWS_ACCESS_KEY_ID'), ...],
    'array'    => ['driver' => 'array', 'serialize' => false],
    'octane'   => ['driver' => 'octane'],    // Laravel Octane worker yaddaşı
],
```

### 2) `Cache::remember` — cache-aside pattern

```php
$product = Cache::remember("product:{$id}", now()->addMinutes(10), function () use ($id) {
    return Product::findOrFail($id);
});
```

Nə edir: `product:123` açarını yoxlayır, varsa qaytarır; yoxdursa closure-u işlədib nəticəni TTL ilə saxlayır.

```php
// Forever — TTL yoxdur, manual forget lazımdır
$settings = Cache::rememberForever('settings', fn () => Setting::all());

// Sadə put/get/forget
Cache::put('key', 'value', now()->addHour());
$v = Cache::get('key', 'default');
Cache::forget('key');
Cache::increment('views:123');
Cache::pull('once-key');   // get + forget
```

### 3) `Cache::lock` — stampede protection

```php
$lock = Cache::lock("product-load:{$id}", 10);   // 10 saniyə

try {
    $lock->block(5, function () use ($id) {      // Max 5 sn gözlə, sonra execute
        // Bu blokda yalnız BİR request DB-yə gedir
        Cache::put("product:{$id}", Product::find($id), 600);
    });
} catch (LockTimeoutException $e) {
    // Lock alına bilmədi — yəqin digər request işi bitirdi
}

return Cache::get("product:{$id}");
```

Və ya qısa:

```php
$product = Cache::lock("load:{$id}", 10)->get(function () use ($id) {
    return Cache::remember("product:{$id}", 600, fn () => Product::find($id));
});
```

### 4) Cache tags — qrup invalidation (Redis/Memcached)

```php
Cache::tags(['products', 'category:5'])->remember("product:{$id}", 600,
    fn () => Product::find($id));

// Bütün kateqoriya cache-ini flush
Cache::tags(['category:5'])->flush();

// products tag-ı olan bütün keys
Cache::tags(['products'])->flush();
```

**Diqqət:** Tags yalnız Redis və Memcached driver-lərində işləyir. File/DB driver-lərində xəta atır.

### 5) Cache events — invalidate on model update

```php
class Product extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (Product $p) => Cache::forget("product:{$p->id}"));
        static::deleted(fn (Product $p) => Cache::forget("product:{$p->id}"));

        // Tags istifadəsi
        static::saved(function (Product $p) {
            Cache::tags(["product:{$p->id}", "category:{$p->category_id}"])->flush();
        });
    }
}
```

Observer sinifi ilə:

```php
class ProductObserver
{
    public function saved(Product $p): void {
        Cache::forget("product:{$p->id}");
        Cache::forget("category:{$p->category_id}:products");
    }
}

// AppServiceProvider boot()
Product::observe(ProductObserver::class);
```

### 6) Octane-level memory cache

```php
use Laravel\Octane\Facades\Octane;

// Yalnız bu worker proses üçün (super sürətli, ~nanosaniyə)
Octane::memory()->put('global-config', $config);
$cfg = Octane::memory()->get('global-config');

// Table-based shared memory (Swoole)
Octane::table('users')->set($id, ['name' => $name]);
```

**Fayda:** per-worker yaddaşdan oxumaq Redis-dən min qat sürətlidir.
**Risk:** worker-lər arasında inconsistent — config dəyişəndə bütün worker-ləri restart etmək lazımdır (`php artisan octane:reload`).

### 7) Multi-level cache — custom helper

```php
class TieredCache
{
    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        // L1: Octane memory
        $local = Octane::memory()->get($key);
        if ($local !== null) return $local;

        // L2: Redis
        $value = Cache::store('redis')->remember($key, $seconds, $callback);

        // L1 warm up (qısa TTL)
        Octane::memory()->put($key, $value, 30);
        return $value;
    }

    public function forget(string $key): void
    {
        Cache::store('redis')->forget($key);
        Octane::memory()->forget($key);
        // Redis pub/sub ilə bütün worker-lərə evict göndər
        Redis::publish('cache-evict', $key);
    }
}
```

Pub/sub listener:

```php
Redis::subscribe(['cache-evict'], function ($key) {
    Octane::memory()->forget($key);
});
```

### 8) Spatie Response Cache — tam HTTP response cache

```bash
composer require spatie/laravel-responsecache
```

```php
// routes/web.php
Route::middleware(['cacheResponse:300'])->group(function () {
    Route::get('/products/{id}', [ProductController::class, 'show']);
});
```

Bütün response (HTML/JSON) cache-də saxlanır. Update olanda:

```php
Artisan::call('responsecache:clear');
// Və ya programmatik
app(\Spatie\ResponseCache\ResponseCache::class)->clear();
```

### 9) PSR-6 / PSR-16 inteqrasiya

Laravel Cache `Psr\SimpleCache\CacheInterface` interface-ni implement edir:

```php
use Psr\SimpleCache\CacheInterface;

class ExternalLibrary
{
    public function __construct(private CacheInterface $cache) {}

    public function fetch(string $key): mixed {
        if ($this->cache->has($key)) return $this->cache->get($key);
        $v = $this->loadFromApi();
        $this->cache->set($key, $v, 300);
        return $v;
    }
}

// Service provider
$this->app->bind(CacheInterface::class, fn () => Cache::store('redis'));
```

### 10) Real ssenari — hot product + stampede protection (Laravel)

```php
class ProductCacheService
{
    public function findById(int $id): Product
    {
        $key = "product:{$id}";

        // L1: Octane memory (per-worker)
        $local = Octane::memory()->get($key);
        if ($local) return $local;

        // L2: Redis + stampede lock
        $product = Cache::lock("load:product:{$id}", 10)
            ->block(5, function () use ($id, $key) {
                return Cache::remember($key, now()->addMinutes(10), function () use ($id) {
                    return Product::findOrFail($id);
                });
            });

        // L1 warm up
        Octane::memory()->put($key, $product, 30);
        return $product;
    }

    public function invalidate(int $id): void
    {
        Cache::forget("product:{$id}");
        Octane::memory()->forget("product:{$id}");
        Redis::publish('cache-evict', "product:{$id}");
    }
}
```

Observer ilə:

```php
class ProductObserver
{
    public function __construct(private ProductCacheService $cache) {}

    public function saved(Product $p): void { $this->cache->invalidate($p->id); }
    public function deleted(Product $p): void { $this->cache->invalidate($p->id); }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Deklarativ | `@Cacheable` / `@CachePut` / `@CacheEvict` annotasiya | Manual `Cache::remember()` closure |
| Açar generasiyası | SpEL (`#id`, `#user.email`) | String interpolation (`"key:{$id}"`) |
| Custom key | `KeyGenerator` interface | Manual string |
| Condition/unless | `condition`, `unless` SpEL | `if` blok manual |
| Stampede protection | `@Cacheable(sync = true)` | `Cache::lock()->block()` |
| Provider switching | `CacheManager` bean dəyişdir | `config/cache.php` `default` |
| Local cache | Caffeine (W-TinyLFU) | File/array (zəif) / Octane memory |
| Distributed | Redis, Hazelcast, EhCache cluster, Infinispan | Redis, Memcached, DynamoDB |
| Tag-based flush | Manual (prefix key, then `KEYS` scan) | `Cache::tags()->flush()` (Redis/Memcached) |
| Multi-level | Custom `CacheManager` | Manual helper class |
| TTL control | RedisCacheConfiguration per-cache | `now()->addMinutes(x)` per-key |
| Statistika | Caffeine `.recordStats()`, Actuator | Redis INFO + custom |
| HTTP response cache | Manual | Spatie Response Cache |
| Per-worker memory | JVM yaddaşı (bütün thread-lər paylaşır) | Octane memory (per-worker, izolə) |
| Write-behind | Manual | Manual |
| Framework-a daxilliyə | Ayrıca starter | Built-in |

---

## Niyə belə fərqlər var?

**JVM-in yaddaş fəlsəfəsi.** Spring tətbiqi tək JVM prosesdir — bütün thread-lər eyni heap-i paylaşır. Buna görə Caffeine kimi local cache super effektivdir: hər sorğu eyni obyektə referans alır, serialize lazım deyil. Spring `@Cacheable` annotasiya-əsaslı yanaşma seçib — AOP proxy metodu intercept edir.

**PHP-nin request-per-process modeli.** PHP-FPM altında hər request ayrı prosesdir — shared yaddaş yoxdur, Cache həmişə xaricə (Redis/Memcached/disk) gedir. Laravel Octane bunu dəyişdi: worker proses uzun yaşayır, `Octane::memory()` per-worker local cache verir. Lakin worker-lər arası paylaşım hələ Redis üzərindən gedir.

**Tag-based flush.** Laravel `Cache::tags()->flush()` Redis-də arxada reference-based implementation ilə işləyir — hər tag üçün xüsusi key-in altında əlaqəli key-lər siyahısı saxlanır. Spring-də default bu yoxdur — əvəzinə `@CacheEvict(allEntries = true)` ya da manual prefix scan. Cache versioning daha effektiv alternativdir.

**Stampede protection.** Spring `@Cacheable(sync = true)` eyni JVM daxilində tək thread-ə metodu işlətdirir (memoization). Lakin distributed stampede (çoxlu node) üçün manual Redis lock lazımdır. Laravel `Cache::lock()` birbaşa distributed lock təqdim edir — PHP-də sirkulyar stampede daha ümumi problem olub, framework ilk sinif həll təklif edir.

**Provider ekosistemi.** Java enterprise dünyasında Hazelcast, EhCache, Infinispan, Coherence kimi sərt cluster-aware cache-lər var — Spring hamısı üçün `CacheManager` implementasiyası təklif edir. PHP-də Redis və Memcached dominantdır — Laravel bu ikisinə optimize olunub. DynamoDB driver AWS mühitinə görə əlavə olundu.

**Multi-level cache.** İkisində də hazır deyil — hər ikisində custom wrapper yazılır. Spring-də `CacheManager` interface-i bu üçün nəzərdə tutulub (composite). Laravel-də helper class daha sadədir. Octane mühitində Laravel tərəfi daha üstünlük verir — `Octane::memory()` + Redis ikili yardımçı.

**Response cache.** Spring-də HTTP səviyyəsində cache `Cache-Control` header-lərinə əsaslanır + CDN (Varnish/Cloudflare). Laravel-də Spatie Response Cache paketi server tərəfli tam HTML/JSON cache verir — daha sadə.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Annotasiya-əsaslı deklarativ cache (`@Cacheable`, `@CachePut`, `@CacheEvict`, `@Caching`)
- SpEL ilə güclü key ifadələri
- `condition` + `unless` inline filter
- `@Cacheable(sync = true)` — tək JVM stampede protection
- Caffeine W-TinyLFU — sənayedə ən sürətli local cache
- Hazelcast, Infinispan, EhCache cluster dəstəyi
- `KeyGenerator` interface
- Actuator `/actuator/caches` endpoint
- Native compile (GraalVM) ilə cache işləyir

**Yalnız Laravel-də:**
- `Cache::tags()->flush()` — tag-based invalidation (Redis/Memcached)
- `Cache::lock()->block(seconds)` — built-in distributed lock
- Octane per-worker memory cache
- `Cache::rememberForever()` helper
- `Cache::pull()` (get + forget)
- Spatie Response Cache — tam HTTP response cache paketi
- DynamoDB driver
- File/database driver (Redis-siz start etmək üçün)
- Laravel Horizon + queue job cache tags inteqrasiyası

---

## Best Practices

1. **Hot read üçün cache, hər şey üçün yox.** Hər DB query-ni cache etmə — yalnız ağır, yenidən-istifadə edilən, az-dəyişən data. Cache-ə yazmağın cost-u oxumağın cost-undan çox olmamalıdır.
2. **TTL düşün.** Qısa (1-5 dəq) — stale risk az. Uzun (1 saat+) — invalidation strategiyası lazımdır. Default olaraq 5-10 dəqiqə yaxşı başlanğıcdır.
3. **Stampede protection quraşdır.** Spring `sync = true`, Laravel `Cache::lock()`. TTL bitəndə 1000 request DB-yə getməsin.
4. **Invalidation-ı event-based et.** Hər update-də `@CacheEvict` / `Cache::forget()` çağır. CDC (Debezium) ilə daha güclü.
5. **Cache versioning istifadə et.** Deployment-də köhnə cache-i qalıq kimi saxlamaq istəmirsənsə — açara versiya əlavə et (`product:v${VERSION}:123`).
6. **Local + distributed qat birləşdir.** Caffeine + Redis / Octane + Redis — DB yükünü azaldır, latency düşür.
7. **Cache stat-ı izlə.** Caffeine `.recordStats()`, Redis INFO — hit-ratio 80%+ olsun. Az olsa, cache layıqincə qurulmayıb.
8. **Nəhəng obyektləri cache-ləmə.** Redis-də 1MB+ dəyərlər performansı vurur. Chunk-lara böl və ya `@Cacheable` yerinə CDN/Object Storage istifadə et.
9. **Null/empty nəticəni unless ilə atla.** Spring `unless = "#result == null"`, Laravel `if ($r !== null) Cache::put(...)`. Yoxsa negative cache trafik çoxalır.
10. **Hot key-i şardla.** Bir açara həddən artıq yük varsa — `product:123:shard${rand(0,9)}` ilə paylaşdır.
11. **Tag flush-dan ehtiyatla istifadə et.** Laravel `Cache::tags(['products'])->flush()` bir neçə min key-i silə bilər — prod-da məsuliyyətli istifadə.
12. **Multi-tenant-da tenant prefix qoy.** `tenant:${tenantId}:product:${id}` — cross-tenant cache qarışığı olmasın.
13. **Octane-də obyekt state-i cache etmə.** Worker-dən worker-a keçdikdə inconsistent olur — yalnız immutable / hesablanmış data.
14. **Write-behind diqqətli istifadə et.** Crash olsa itir — kritik məlumat üçün write-through seç.
15. **Cache warm-up deployment-də qur.** Hot data yeni node-da dərhal olsun — background job ilə öncə populyarlaşdır.

---

## Yekun

Spring cache abstraksiyası deklarativ və dərindir — `@Cacheable` annotasiyası + SpEL güclü kombinasiya verir, Caffeine ilə sənayedə ən sürətli local cache əldə edilir, Hazelcast / EhCache / Redis / Infinispan cluster-lər first-class dəstəklənir. Stampede-dən `sync = true` ilə qorunma JVM daxilində hazırdır.

Laravel cache layer-i pragmatikdir — `Cache::remember()` sadə və oxunaqlı, `Cache::tags()` qrup invalidation, `Cache::lock()` built-in distributed lock, Octane `memory()` super sürətli per-worker layer. Spatie Response Cache ilə HTTP cavab səviyyəsində cache qurmaq asandır.

Müsahibədə bu sahədə diqqət edilməli məqamlar: (1) hansı cache pattern seçirsən (cache-aside / read-through / write-through / write-behind), (2) stampede necə qarşılayırsan, (3) invalidation strategiyası nədir (TTL / event / versioning / CDC), (4) hot key problemini necə həll edirsən, (5) multi-level cache arxitekturası. Bu 5 sualı cavablandıra bilsən — cache mütəxəssisi sayılırsan. Əlavə bonus: hit-ratio, tail latency, cache warm-up kimi metrik-driven düşüncəni göstər.
