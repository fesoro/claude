# Decorator (Middle ⭐⭐)

## İcmal
Decorator pattern runtime-da bir obyektə əlavə davranış qoşmağa imkan verir — inheritance olmadan. Decorator, əsas obyektlə eyni interface-i implement edir, içəridə həmin obyekti saxlayır, metodları çağırmadan əvvəl və ya sonra əlavə iş görür. "Wrapper" da deyilir.

## Niyə Vacibdir
Bir cache service-ə logging əlavə etmək lazımdır. Sonra rate limiting lazım olur. Sonra retry məntiqini. Bunları inheritance ilə həll etsən, hər kombinasiya üçün yeni subclass lazım olur: `LoggingCache`, `LoggingRateLimitedCache`, `LoggingRateLimitedRetryingCache` — kombinasiya partlaması. Decorator-lər bu qatları (layers) dinamik birləşdirir: hər qat öz işini görür, biri digərindən xəbərsizdir.

## Əsas Anlayışlar
- **Component interface**: Əsas obyekt və bütün Decorator-ların implement etdiyi interface — birlik budur
- **Concrete Component**: Əsl iş görən obyekt — "əsas" davranış burada yaşayır
- **Decorator (abstract)**: Component interface-i implement edir; içəridə başqa bir Component saxlayır
- **Concrete Decorator**: `before/after` əlavə davranış qoşur; `wrapped`-i mütləq çağırmalıdır
- **Stacking**: Decorator-ları iç-içə sarmalmaq — əlavə edilmə sırası vacibdir; sıra nəticəni dəyişə bilər
- **Fərq Proxy-dən**: Decorator funksionallıq əlavə edir; Proxy isə access kontrol edir — struktur eyni, məqsəd fərqli

## Praktik Baxış
- **Real istifadə**: Caching decorator (repository-nin üstünə cache qatı), API client-ə retry + timeout + logging qatları, HTTP middleware (Decorator ideyasının HTTP versiyası), notification-a tracking əlavə etmək
- **Trade-off-lar**: Çox qatlı Decorator-larda debugging çətin olur — hansı qatda səhv var? Stack trace uzanır; Decorator-ların sırası məsələdir (log-dan əvvəl mi, sonra mı retry?); hər qat ayrı class — fayl sayı artır
- **İstifadə etməmək**: Statik (compile-time) genişlənmə lazımdırsa inheritance daha sadədir; Decorator sayı 3-4-dən çox olduqda pipeline pattern daha idarəli ola bilər; çox sadə əlavə davranış üçün (bir `if` bloğu kifayət edəndə)
- **Common mistakes**: Decorator-da `$this->wrapped->method()` çağırmağı unutmaq — əsas davranış itirilir; Decorator-a biznes məntiqini qoymaq — Decorator yalnız cross-cutting concerns (log, cache, retry) üçündür

### Anti-Pattern Nə Zaman Olur?
Decorator-lar çox qatlandıqda və ya yanlış məqsəd üçün istifadə edildikdə anti-pattern olur:

- **"Decorator hell"**: 7-8 Decorator üst-üstə sarındıqda — `new A(new B(new C(new D(new E(new F(new G($core)))))))` — artıq heç kim nə olduğunu anlamır. Bu halda Pipeline pattern, middleware stack, ya da birbaşa composition daha oxunaqlıdır.
- **Sıra effektlərini anlamadan stack etmək**: `RetryDecorator(LoggingDecorator(service))` vs `LoggingDecorator(RetryDecorator(service))` — birincidə hər retry üçün log yazılmır, ikincidə yazılır. Yanlış sıra, yanlış davranış — amma kod "işləyir" görünür.
- **Biznes məntiqini Decorator-a sıxmaq**: `VipUserDiscountDecorator` adlı bir Decorator yaratmaq — discount hesablamaq cross-cutting concern deyil, domain məntiqidir. Bu, Service layer-ə ya Policy-ə aiddir.
- **Decorator-u subclass kimi düşünmək**: `PremiumCacheDecorator` yaratmaq, orada `wrapped`-i çağırmamaq — bu artıq Decorator deyil, sadəcə fərqli bir implementation-dır.

## Nümunələr

### Ümumi Nümunə
Cache service-in üstünə log yazmaq lazımdır. `LoggingCache` wrapper class eyni `CacheInterface`-i implement edir, içəridə real cache saxlayır. `get()` çağırıldıqda əvvəl log yazır, sonra real cache-i çağırır, nəticəni yenidən log edir. Client kodu heç bir dəyişiklik olmadan `LoggingCache` istifadə edə bilər — DI container sadəcə fərqli instance inject edir.

### PHP/Laravel Nümunəsi

```php
// ===== Component Interface — hər kəsin implement etdiyi contract =====
interface CacheInterface
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int $ttl): bool;
    public function forget(string $key): bool;
    public function has(string $key): bool;
}


// ===== Concrete Component — əsl iş buradadır =====
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


// ===== Abstract Decorator — default delegation =====
// Subclass yalnız lazım olan metod-u override edir, qalanlar avtomatik keçir
abstract class CacheDecorator implements CacheInterface
{
    public function __construct(protected readonly CacheInterface $wrapped) {}

    // Default behavior: wrapped-ə ötür
    // Subclass override etməsəydi, bu metod çağırılardı — zəncirlər qırılmır
    public function get(string $key): mixed    { return $this->wrapped->get($key); }
    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->wrapped->put($key, $value, $ttl);
    }
    public function forget(string $key): bool  { return $this->wrapped->forget($key); }
    public function has(string $key): bool     { return $this->wrapped->has($key); }
}


// ===== Concrete Decorator 1: Logging =====
class LoggingCacheDecorator extends CacheDecorator
{
    public function get(string $key): mixed
    {
        $value = $this->wrapped->get($key); // wrapped-i əvvəl çağır, sonra log

        // hit/miss — bu məlumat production-da çox dəyərlidir
        Log::debug('Cache ' . ($value !== null ? 'HIT' : 'MISS'), ['key' => $key]);

        return $value;
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        Log::debug('Cache PUT', ['key' => $key, 'ttl' => $ttl]);
        return $this->wrapped->put($key, $value, $ttl); // wrapped-i çağırmağı unutma!
    }
    // forget() və has() — abstract Decorator-dakı default işləyir
}


// ===== Concrete Decorator 2: Statistics — hit rate tracking =====
class StatisticsCacheDecorator extends CacheDecorator
{
    private int $hits   = 0;
    private int $misses = 0;

    public function get(string $key): mixed
    {
        $value = $this->wrapped->get($key);

        // Nəticəyə görə sayğac artır
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


// ===== Concrete Decorator 3: Namespace prefix =====
// Multi-tenant app-da tenant-a görə prefix əlavə edir
class NamespacedCacheDecorator extends CacheDecorator
{
    public function __construct(
        CacheInterface $wrapped,
        private readonly string $prefix  // məs: 'tenant_42'
    ) {
        parent::__construct($wrapped);
    }

    private function prefixedKey(string $key): string
    {
        return "{$this->prefix}:{$key}";  // 'user:1' → 'tenant_42:user:1'
    }

    // Bütün metodlar prefix əlavə edir — key collision-larını önləyir
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

            // Qatları iç-içə sar — içdən xaricə oxu:
            // 1. Redis (əsl iş)
            // 2. Namespace (açarı prefix-lə — tenant isolation)
            // 3. Logging (hər əməliyyatı log et — debug üçün)
            // 4. Statistics (hit/miss izlə — monitoring üçün)
            // Sıra vacibdir: Statistics → Logging → Namespace → Redis
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
    // CacheInterface inject olunur — 4 qatlı decorated version gəlir
    public function __construct(private readonly CacheInterface $cache) {}

    public function find(int $id): ?array
    {
        $key = "product:{$id}";

        if ($cached = $this->cache->get($key)) {
            return $cached; // Statistics: hit++; Log: HIT yazılır
        }

        $product = DB::table('products')->find($id);
        $this->cache->put($key, $product, ttl: 3600); // Log: PUT yazılır

        return $product;
    }
}


// ===== Laravel Middleware — Decorator ideyasının HTTP versiyası =====
// Hər middleware əvvəlki/sonrakı-nı "wrap" edir — Decorator-un HTTP analogu
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before: rate limit yoxla
        if ($this->isRateLimited($request)) {
            return response()->json(['error' => 'Too many requests'], 429);
        }

        $response = $next($request); // wrapped handler-i çağır

        // After: header əlavə et
        $response->headers->set('X-RateLimit-Remaining', $this->remaining($request));

        return $response;
    }

    private function isRateLimited(Request $request): bool
    {
        // Redis-dən check...
        return false;
    }

    private function remaining(Request $request): int
    {
        return 60;
    }
}
```

## Praktik Tapşırıqlar
1. `ApiClientDecorator` yaz: `HttpClientInterface` (`get`, `post`, `put`, `delete`) — `RetryDecorator` (3 dəfə retry on failure), `TimingDecorator` (response vaxtını log et), `AuthDecorator` (hər sorğuya Bearer token əlavə et) — üçünü üst-üstə sar. Sıranı düşün: retry → timing → auth → real client.
2. Mövcud bir Service class-ına Decorator ilə caching əlavə et — Service-i dəyişmədən: `CachedUserService` wraps `UserService`, eyni interface, `findById()` metodunda önce cache yoxlar, sonra real service-i çağırır, nəticəni cache-ə qoyur.
3. Decorator sırası məsələsini sübut et: `LoggingDecorator(RetryDecorator(service))` vs `RetryDecorator(LoggingDecorator(service))` — hər retry-da log yazılırmı? İki sıradan birini seç, fərqi PHPUnit test-i ilə sübut et.

## Əlaqəli Mövzular
- [04-proxy.md](04-proxy.md) — Proxy access kontrol edir, Decorator funksionallıq əlavə edir; struktur eyni, niyyət fərqli
- [01-facade.md](01-facade.md) — Facade mürəkkəbliyi gizlədər; Decorator-lar qatlanaraq həmin mürəkkəbliyi yaradır
- [02-adapter.md](02-adapter.md) — Adapter interface-i çevirir; Decorator eyni interface saxlayıb davranış qoşur
- [../laravel/03-pipeline.md](../laravel/03-pipeline.md) — Pipeline çox Decorator-u sıralı idarə edir; daha oxunaqlı alternativ
- [../laravel/02-service-layer.md](../laravel/02-service-layer.md) — Service layer üzərinə Decorator qatları əlavə etmək
- [../behavioral/01-observer.md](../behavioral/01-observer.md) — Observer reaktiv, Decorator proaktiv davranış qoşur
- [../behavioral/04-template-method.md](../behavioral/04-template-method.md) — Template Method compile-time genişlənmə; Decorator runtime genişlənmə
- [../behavioral/06-chain-of-responsibility.md](../behavioral/06-chain-of-responsibility.md) — Chain of Responsibility handler-ları Decorator-a bənzər zəncirlər, amma request/response modellidir
- [../behavioral/02-strategy.md](../behavioral/02-strategy.md) — Strategy algorithm seçir; Decorator mövcud algorithm-un üstünə davranış qoşur
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Open/Closed: mövcud class-ı dəyişmədən Decorator ilə genişlənmək
