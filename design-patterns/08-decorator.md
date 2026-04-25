# Decorator (Middle ⭐⭐)

## İcmal
Decorator pattern runtime-da bir obyektə əlavə davranış qoşmağa imkan verir — inheritance olmadan. Decorator, əsas obyektlə eyni interface-i implement edir, içəridə həmin obyekti saxlayır, metodları çağırmadan əvvəl və ya sonra əlavə iş görür. "Wrapper" da deyilir.

## Niyə Vacibdir
Bir cache service-ə logging əlavə etmək lazımdır. Sonra rate limiting lazım olur. Sonra retry məntiqini. Bunları inheritance ilə həll etsən, hər kombinasiya üçün yeni subclass lazım olur. Decorator-lər bu qatları (layers) dinamik birləşdirir: `new RetryDecorator(new LoggingDecorator(new RateLimitDecorator(new CacheService())))` — hər qat öz işini görür.

## Əsas Anlayışlar
- **Component interface**: Əsas obyekt və bütün Decorator-ların implement etdiyi interface
- **Concrete Component**: Əsl iş görən obyekt — "əsas" davranış
- **Decorator (abstract)**: Component interface-i implement edir; içəridə başqa bir Component saxlayır
- **Concrete Decorator**: Concrete Component-in metoduna `before/after` əlavə davranış qoşur
- **Stacking**: Decorator-ları iç-içə sarmalmaq — əlavə edilmə sırası vacibdir
- **Fərq Proxy-dən**: Decorator funksionallıq əlavə edir, Proxy isə access kontrol edir (müştəriyə eyni interface, amma girişi məhdudlaşdırır/yönləndirir)

## Praktik Baxış
- **Real istifadə**: Caching decorator (logging cache), API client-ə retry + timeout + logging qatları, HTTP middleware (Decorator ideyasının HTTP versiyası), notification-a tracking əlavə etmək
- **Trade-off-lar**: Çox qatlı Decorator-larda debugging çətin olur — hansı qatda səhv var? Stack trace uzanır; Decorator-ların sırası məsələdir (log-dan əvvəl mi, sonra mı retry?); config-dən idarə etmək çətin olur
- **İstifadə etməmək**: Statik (compile-time) genişlənmə lazımdırsa inheritance daha sadədir; Decorator sayı 3-4-dən çox olduqda pipeline pattern daha idarəli ola bilər; çox sadə əlavə davranış üçün (sadəcə bir `if` bloğu kifayət edəndə)
- **Common mistakes**: Decorator-da `$this->wrapped->method()` çağırmağı unutmaq — əsas davranış itirilir; Decorator-a biznes məntiqini qoymaq — Decorator yalnız cross-cutting concerns (log, cache, retry) üçündür

## Nümunələr

### Ümumi Nümunə
Cache service-in üstünə log yazmaq lazımdır. `LoggingCache` wrapper class eyni `CacheInterface`-i implement edir, içəridə real cache saxlayır. `get()` çağırıldıqda əvvəl log yazır, sonra real cache-i çağırır, nəticəni yenidən log edir. Client kodu heç bir dəyişiklik olmadan `LoggingCache` istifadə edə bilər.

### PHP/Laravel Nümunəsi

```php
// ===== Component Interface =====
interface CacheInterface
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int $ttl): bool;
    public function forget(string $key): bool;
    public function has(string $key): bool;
}


// ===== Concrete Component =====
class RedisCache implements CacheInterface
{
    public function __construct(private readonly \Redis $redis) {}

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : null;
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return (bool) $this->redis->setex($key, $ttl, serialize($value));
    }

    public function forget(string $key): bool
    {
        return (bool) $this->redis->del($key);
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }
}


// ===== Abstract Decorator =====
abstract class CacheDecorator implements CacheInterface
{
    public function __construct(protected readonly CacheInterface $wrapped) {}

    // Default — wrapped-ə ötür, subclass lazım olanı override edir
    public function get(string $key): mixed
    {
        return $this->wrapped->get($key);
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->wrapped->put($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->wrapped->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->wrapped->has($key);
    }
}


// ===== Concrete Decorator 1: Logging =====
class LoggingCacheDecorator extends CacheDecorator
{
    public function get(string $key): mixed
    {
        $value = $this->wrapped->get($key);

        Log::debug('Cache ' . ($value !== null ? 'HIT' : 'MISS'), ['key' => $key]);

        return $value;
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        Log::debug('Cache PUT', ['key' => $key, 'ttl' => $ttl]);
        return $this->wrapped->put($key, $value, $ttl);
    }
}


// ===== Concrete Decorator 2: Statistics =====
class StatisticsCacheDecorator extends CacheDecorator
{
    private int $hits   = 0;
    private int $misses = 0;

    public function get(string $key): mixed
    {
        $value = $this->wrapped->get($key);

        $value !== null ? $this->hits++ : $this->misses++;

        return $value;
    }

    public function getHitRate(): float
    {
        $total = $this->hits + $this->misses;
        return $total > 0 ? ($this->hits / $total) * 100 : 0;
    }

    public function getStats(): array
    {
        return [
            'hits'     => $this->hits,
            'misses'   => $this->misses,
            'hit_rate' => round($this->getHitRate(), 2) . '%',
        ];
    }
}


// ===== Concrete Decorator 3: Prefix namespace =====
class NamespacedCacheDecorator extends CacheDecorator
{
    public function __construct(
        CacheInterface $wrapped,
        private readonly string $prefix
    ) {
        parent::__construct($wrapped);
    }

    private function prefixedKey(string $key): string
    {
        return "{$this->prefix}:{$key}";
    }

    public function get(string $key): mixed
    {
        return $this->wrapped->get($this->prefixedKey($key));
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->wrapped->put($this->prefixedKey($key), $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->wrapped->forget($this->prefixedKey($key));
    }

    public function has(string $key): bool
    {
        return $this->wrapped->has($this->prefixedKey($key));
    }
}


// ===== ServiceProvider-də qatları yığ =====
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheInterface::class, function () {
            $redis = new RedisCache(new \Redis());

            // Qatları iç-içə sar — içdən xaricə:
            // 1. Redis (əsl iş)
            // 2. Namespace (açarı prefix-lə)
            // 3. Logging (hər əməliyyatı log et)
            // 4. Statistics (hit/miss izlə)
            return new StatisticsCacheDecorator(
                new LoggingCacheDecorator(
                    new NamespacedCacheDecorator($redis, config('app.name'))
                )
            );
        });
    }
}


// ===== Client kodu — sadəcə CacheInterface bilir =====
class ProductRepository
{
    public function __construct(private readonly CacheInterface $cache) {}

    public function find(int $id): ?array
    {
        $key = "product:{$id}";

        if ($cached = $this->cache->get($key)) {
            return $cached;
        }

        $product = DB::table('products')->find($id);
        $this->cache->put($key, $product, ttl: 3600);

        return $product;
    }
}


// ===== Laravel Middleware — Decorator ideyasının HTTP versiyası =====
// Hər middleware əvvəlki/sonrakı-nı "wrap" edir
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // before
        if ($this->isRateLimited($request)) {
            return response()->json(['error' => 'Too many requests'], 429);
        }

        $response = $next($request); // wrapped handler-i çağır

        // after
        $response->headers->set('X-RateLimit-Remaining', $this->remaining($request));

        return $response;
    }
}
```

## Praktik Tapşırıqlar
1. `ApiClientDecorator` yaz: `HttpClientInterface` (`get`, `post`, `put`, `delete`) — `RetryDecorator` (3 dəfə retry on failure), `TimingDecorator` (response vaxtını log et), `AuthDecorator` (hər sorğuya Bearer token əlavə et) — üçünü üst-üstə sar
2. Mövcud bir Service class-ına Decorator ilə caching əlavə et — Service-i dəyişmədən: `CachedUserService` wraps `UserService`, eyni interface, `get` metodunda cache kontrol edir
3. Decorator sırası məsələsini sübut et: `LoggingDecorator(RetryDecorator(service))` vs `RetryDecorator(LoggingDecorator(service))` — hər retry-da log yazılırmı? Fərqi müəyyən et

## Əlaqəli Mövzular
- [17-proxy.md](17-proxy.md) — Proxy access kontrol edir, Decorator funksionallıq əlavə edir
- [21-pipeline.md](21-pipeline.md) — Pipeline çox Decorator-u sıralı idarə edir
- [09-observer.md](09-observer.md) — Observer reaktiv, Decorator proaktiv davranış qoşur
