# Object Pool (Middle ⭐⭐)

## İcmal
Object Pool pattern əvvəlcədən initialize edilmiş obyektlər dəstini (pool) saxlayır. Client obyektə ehtiyac duyduqda yenisini yaratmaq əvəzinə pool-dan alır, işi bitdikdə isə geri qaytarır. Pool obyekti məhv etmir — növbəti istifadə üçün saxlayır.

Klassik nümunə: database connection pool. Hər request üçün yeni TCP connection açmaq 50-200ms çəkir; pool-da hazır connection varsa, bu gecikməni sıfırlayır.

## Niyə Vacibdir
Laravel-in `DB` facade-i connection pool istifadə edir — bunu bilmək olmasa da. Lakin öz layihəndə bahalı resurslar (HTTP client, external API connection, Redis client, PDF renderer) varsa, Object Pool bu resursları paylaşdırmaq üçün açıq dizayn qərarı olur. Octane/Swoole ilə uzunömürlü PHP proseslərində pool əhəmiyyəti daha da artır.

## Əsas Anlayışlar
- **Pool**: Hazır obyektlər kolleksiyası (adətən `SplQueue` və ya array)
- **Acquire (götür)**: Pool-dan boş obyekt al; pool boşdursa ya gözlə, ya yeni yarat, ya exception at
- **Release (qaytar)**: İşi bitmiş obyekti pool-a geri qoy — məhv etmə
- **Pool size**: Minimum (başlanğıcda yaradılan) + Maximum (eyni anda mövcud ola bilən) hədd
- **Object reset**: Obyekt pool-a qayıdarkən əvvəlki state-i təmizlənir ki növbəti client "kirli" obyekt almasın
- **Leak**: Client aldığı obyekti qaytarmırsa pool tükənir — bu ən çox görülən problemdir

## Praktik Baxış
- **Real istifadə**: Database connection pool (PDO, pgsql), Redis connection pool (Predis/PhpRedis), HTTP client pool (Guzzle instances), PDF generator (wkhtmltopdf process), external API SDK instance-ları, thread worker pool (Swoole/RoadRunner)
- **Trade-off-lar**: Kod mürəkkəbliyi artır — acquire/release məntiqi lazımdır; pool dolu olduqda waiting strategy seçmək lazımdır (blok, exception, yeni yarat); pool-dakı ölü connection-ları aşkar etmək üçün health check lazımdır; thread-safe implementation Swoole-da ayrıca diqqət tələb edir
- **İstifadə etməmək**: Yaratmaq ucuz olan obyektlər üçün (sadə DTO, value object — `new` daha sürətlidir); stateless, immutable obyektlər üçün (paylaşmağa ehtiyac yoxdur); az sayda istifadə halında (overhead pool-un faydanı üstələyir)
- **Common mistakes**: Release-i unutmaq — pool tükənir; release-dən əvvəl reset etməmək — state sızıntısı; pool size-ı sistemin yükünə görə ayarlamamaq (çox kiçik → darboğaz, çox böyük → resurs israfı)

### Anti-Pattern Nə Zaman Olur?

**1. Ucuz obyektlər üçün pool — overhead faydanı üstələyir:**
```php
// Pis: Money value object üçün pool yaratmaq
class MoneyPool
{
    private array $pool = [];

    public function acquire(int $amount, string $currency): Money
    {
        // Money yaratmaq < 1 microsecond çəkir
        // Pool idarəetməsi isə daha çox çəkir — mənasız overhead
        return array_pop($this->pool) ?? new Money($amount, $currency);
    }
}
// Həll: sadəcə new Money($amount, $currency)
```

**2. Pool-dan alınan obyekti reset etməmək — state sızıntısı:**
```php
class HttpClientPool
{
    public function release(GuzzleClient $client): void
    {
        // YANLIŞ: əvvəlki request-in header-ları qalır
        $this->available[] = $client;

        // DÜZGÜN: client-i fresh vəziyyətə gətir
        $freshClient = new GuzzleClient($this->defaultConfig);
        $this->available[] = $freshClient;
        // ya da client reset() metodunu çağır
    }
}
```

**3. Exception-da release etməyi unutmaq — pool leak:**
```php
// Pis: exception baş verərsə release çağırılmır
public function process(): void
{
    $conn = $this->pool->acquire();
    $conn->query('SELECT ...'); // exception ata bilər
    $this->pool->release($conn); // exception baş verərsə buraya çatmır — LEAK
}

// Düzgün: try/finally ilə zəmanətli release
public function process(): void
{
    $conn = $this->pool->acquire();
    try {
        $conn->query('SELECT ...');
    } finally {
        $this->pool->release($conn); // həmişə işləyir
    }
}
```

**4. Singleton pool + test isolation:** Pool-u singleton kimi bind etsən, testlər arasında connection-lar paylaşılır. Test-lərdə pool-u fake/stub ilə əvəz et.

## Nümunələr

### Ümumi Nümunə
PDF generasiya xidmətini düşün: hər request-də yeni `wkhtmltopdf` prosesi başlatmaq 300-500ms çəkir. Pool-da 5 hazır proses saxlanılsa, hər request sadəcə idle prosesi götürür, işi bitdikdə geri qaytarır. 100 eyni anda gələn request-dən 5-i dərhal işlənir, qalanları növbəyə girir — amma process start overhead yoxdur.

### PHP/Laravel Nümunəsi

```php
// ===== Generic Object Pool =====

interface PoolableInterface
{
    // Pool-a qayıdarkən state-i sıfırla
    public function reset(): void;

    // Connection sağlığını yoxla (ölü connection-ı pool-a qaytarma)
    public function isAlive(): bool;
}

class ObjectPool
{
    private \SplQueue $available;
    private int $currentSize = 0;

    public function __construct(
        // Factory: yeni obyekt yaradan callable
        private readonly \Closure $factory,
        private readonly int $minSize = 2,
        private readonly int $maxSize = 10,
    ) {
        $this->available = new \SplQueue();
        $this->warmUp(); // minimum sayda obyekti əvvəlcədən yarat
    }

    private function warmUp(): void
    {
        // Pool başlayarkən minimum sayda obyekt hazırla — ilk request-lər gecikməsin
        for ($i = 0; $i < $this->minSize; $i++) {
            $this->available->enqueue(($this->factory)());
            $this->currentSize++;
        }
    }

    public function acquire(): PoolableInterface
    {
        // Sağlam bir obyekt tapana qədər pool-u yoxla
        while (!$this->available->isEmpty()) {
            $obj = $this->available->dequeue();

            if ($obj->isAlive()) {
                return $obj; // sağlam — ver
            }

            // Ölü connection — nəzərə alma, sayı azalt
            $this->currentSize--;
        }

        // Pool boşdur — yeni yaratmaq mümkündürsə yarat
        if ($this->currentSize < $this->maxSize) {
            $this->currentSize++;
            return ($this->factory)();
        }

        // Maksimum həddə çatdıq — gözlə (real sistemdə timeout/backoff istifadə et)
        throw new \RuntimeException('Object pool exhausted. Try again later.');
    }

    public function release(PoolableInterface $obj): void
    {
        $obj->reset(); // State-i təmizlə — növbəti client "kirli" obyekt almasın

        if ($obj->isAlive()) {
            $this->available->enqueue($obj); // Sağlam — geri qoy
        } else {
            $this->currentSize--; // Ölmüş — pool-a qoyma, sayı azalt
        }
    }

    public function size(): int
    {
        return $this->currentSize;
    }

    public function availableCount(): int
    {
        return $this->available->count();
    }
}


// ===== Laravel Redis Connection Pool nümunəsi =====

class PoolableRedisConnection implements PoolableInterface
{
    private ?\Redis $redis = null;
    private bool $inUse = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $database = 0,
    ) {
        $this->connect();
    }

    private function connect(): void
    {
        $this->redis = new \Redis();
        // persistent connection — reconnect overhead-ını azaldır
        $this->redis->pconnect($this->host, $this->port, 2.5);
        $this->redis->select($this->database);
        $this->inUse = true;
    }

    public function get(string $key): mixed
    {
        return $this->redis->get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return $ttl > 0
            ? $this->redis->setex($key, $ttl, $value)
            : $this->redis->set($key, $value);
    }

    public function reset(): void
    {
        // DB-ni default-a qaytar; pipeline varsa flush et
        $this->redis->select($this->database);
        $this->inUse = false;
    }

    public function isAlive(): bool
    {
        try {
            // PING yoxlaması — əgər connection qırılıbsa false qaytarır
            return $this->redis->ping() === '+PONG' || $this->redis->ping() === true;
        } catch (\RedisException) {
            return false;
        }
    }
}


// ===== Service Provider-də Pool qurulumu =====

class RedisPoolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pool-u singleton kimi qeydiyyat et — bütün application bölüşür
        $this->app->singleton('redis.pool', function () {
            return new ObjectPool(
                factory: fn() => new PoolableRedisConnection(
                    host:     config('database.redis.default.host'),
                    port:     config('database.redis.default.port'),
                    database: config('database.redis.default.database'),
                ),
                minSize: 2,  // başlanğıcda 2 connection hazır
                maxSize: 10, // eyni anda maksimum 10 connection
            );
        });
    }
}


// ===== Cache Service — pool-dan istifadə =====

class PooledCacheService
{
    public function __construct(
        private readonly ObjectPool $pool
    ) {}

    public function remember(string $key, int $ttl, \Closure $callback): mixed
    {
        $conn = $this->pool->acquire(); // pool-dan al
        try {
            $cached = $conn->get($key);

            if ($cached !== false) {
                return unserialize($cached); // cache hit
            }

            // Cache miss — hesabla və yaz
            $value = $callback();
            $conn->set($key, serialize($value), $ttl);

            return $value;
        } finally {
            // Exception olsa belə pool-a qaytar — LEAK olmaz
            $this->pool->release($conn);
        }
    }
}


// ===== Controller-də istifadə =====

class ProductController extends Controller
{
    public function __construct(
        private readonly PooledCacheService $cache
    ) {}

    public function index(): JsonResponse
    {
        $products = $this->cache->remember(
            key: 'products:all',
            ttl: 300,
            callback: fn() => Product::active()->with('category')->get()->toArray()
        );

        return response()->json($products);
    }
}


// ===== Laravel Octane ilə Worker Pool (Swoole context) =====
// Octane-də hər worker özünün pool-una sahib olmalıdır,
// çünki Swoole coroutine-ləri paralel işləyir

// octane.php config:
// 'workers' => 4, — hər worker ayrı pool idarə edir

// Octane RequestReceived event-ında pool-u reset et:
class OctaneServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Hər request başlamadan əvvəl pool state-ini yoxla
        $this->app->booted(function () {
            if (class_exists(\Laravel\Octane\Facades\Octane::class)) {
                \Laravel\Octane\Facades\Octane::tick('pool-health', function () {
                    // Hər 60 saniyədə ölü connection-ları rədd et
                    // Pool öz acquire() metodunda bunu edir,
                    // amma proaktiv yoxlama daha yaxşıdır
                })->seconds(60);
            }
        });
    }
}
```

## Praktik Tapşırıqlar
1. `HttpClientPool` yaz: Guzzle `Client` obyektlərini pool-da saxla, hər `release()`-də default header-ları sıfırla. `maxSize: 5` — 6-cı acquire-də exception at. `try/finally` ilə test et ki exception-da da release işləyir.
2. Pool-un performans fərqini ölç: 100 dəfə `new \Redis()` ilə connect vs pool-dan acquire — `microtime(true)` ilə fərqi qeyd et; pool-un overhead-ını da ölç (kiçik pool-un faydası nə vaxt başlayır?).
3. Laravel Octane ilə pool sınağı: `php artisan octane:start --workers=4` ilə server başlat; pool-u singleton kimi bind et; eyni anda 20 request göndər (`ab -n 20 -c 20`) — pool-un dolduğunda nə baş verir? `maxSize`-ı artır, nəticəni müşahidə et.

## Əlaqəli Mövzular
- [Prototype](05-prototype.md) — Prototype Registry pool-a bənzər obyekt idarəsi edir
- [Singleton](01-singleton.md) — Pool özü adətən singleton kimi bind edilir
- [Flyweight](../structural/06-flyweight.md) — Flyweight da paylaşılan obyektlər istifadə edir, lakin immutable-dır; Pool isə mutable, dövriyyəlidir
- [Proxy](../structural/04-proxy.md) — Lazy connection pool-u üçün Proxy pattern tez-tez birlikdə istifadə olunur
