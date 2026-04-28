# Cache-Aside Pattern (Middle ⭐⭐)

## İcmal

Cache-Aside (Lazy Loading Cache) — tətbiqin özünün cache-i idarə etdiyi ən geniş yayılmış caching pattern-idir. Məntiq sadədir: əvvəlcə cache-ə bax, yoxsa DB-dən al, sonra cache-ə yaz.

## Niyə Vacibdir

Caching olmadan hər request DB-ə gedər. Caching yanlış tətbiq olunarsa — stale data, cache stampede, ya da data itkisi baş verər. Cache-Aside bu balansı ən transparent şəkildə saxlayan pattern-dir. Laravel-in `Cache::remember()` funksiyası birbaşa bu pattern-i implementasiya edir.

## Əsas Anlayışlar

### 4 Əsas Caching Pattern

```
┌─────────────────────────────────────────────────────────┐
│ 1. Cache-Aside (Lazy Loading)                           │
│    App → Cache? → Hit: return                           │
│                → Miss: DB → Cache → return              │
│    Cache özü data bilmir — app idarə edir               │
├─────────────────────────────────────────────────────────┤
│ 2. Read-Through                                         │
│    App → Cache → Hit: return                            │
│               → Miss: Cache özü DB-dən alır → return    │
│    App cache-in "miss" olduğunu bilmir                  │
├─────────────────────────────────────────────────────────┤
│ 3. Write-Through                                        │
│    App → Cache + DB (sinxron, hər ikisi birlikdə)       │
│    Write əməliyyatı bitir = hər ikisi yenilənib         │
├─────────────────────────────────────────────────────────┤
│ 4. Write-Behind (Write-Back)                            │
│    App → Cache (sürətli return)                         │
│         Cache → DB (async, batch)                       │
│    Write sürəti maksimum, amma data itkisi riski var    │
└─────────────────────────────────────────────────────────┘
```

### Cache-Aside vs Read-Through Müqayisəsi

| Xüsusiyyət | Cache-Aside | Read-Through |
|---|---|---|
| Kim idarə edir? | Tətbiq (app) | Cache layer özü |
| Cache miss məntiqi | App code-da | Cache provider-da |
| Flexibility | Yüksək | Aşağı |
| Cache failure handing | App idarə edir | Transparent |
| Laravel dəstəyi | `Cache::remember()` | Xüsusi library lazımdır |
| Stale data riski | TTL ilə idarə | TTL ilə idarə |
| Cold start | Tədricən dolar | Tədricən dolar |

### "There are only two hard things in CS"

> "There are only two hard things in Computer Science: cache invalidation and naming things."
> — Phil Karlton

Bu zarafat deyil. Cache invalidation — "nə vaxt cache-i silim?" sualıdır. Cavab həmişə düz deyil:
- **Çox erkən silsən**: DB-ə lazımsız yük
- **Çox gec silsən**: User köhnə data görür (stale)
- **Unudub silməsən**: Data uyğunsuzluğu — ən pisi

## Praktik Baxış

### Cache-Aside — Əsas Axış

```
T1: App → Cache: "products:1" var mı?
T2: Cache → App: Miss (yoxdur)
T3: App → DB: SELECT * FROM products WHERE id = 1
T4: DB → App: {id: 1, name: "Laptop", price: 999}
T5: App → Cache: SET "products:1" = {...} TTL=3600
T6: App → User: data

T7: App → Cache: "products:1" var mı?  (ikinci request)
T8: Cache → App: Hit! {id: 1, name: "Laptop", ...}
T9: App → User: data  (DB-ə getmədən)
```

### Write-Through — Sinxron Yazma

```
T1: User → App: product yenilə
T2: App → DB: UPDATE products SET price = 899 WHERE id = 1
T3: App → Cache: SET "products:1" = {...yeni data...} TTL=3600
T4: App → User: OK
```

Üstünlük: Cache həmişə fresh. Çatışmazlıq: Hər write = 2 əməliyyat.

### Write-Behind — Asinxron Yazma

```
T1: User → App: product yenilə
T2: App → Cache: SET "products:1" = {...yeni data...}  ← sürətli return
T3: App → User: OK
...async...
T4: Worker → Cache: oxu
T5: Worker → DB: UPDATE (batch ilə)
```

Üstünlük: Write çox sürətli. Çatışmazlıq: Worker çöksə data itirilir.

## Nümunələr

### Cache-Aside: `Cache::remember()`

```php
// Laravel-in Cache::remember() = Cache-Aside pattern
class ProductRepository
{
    public function find(int $id): ?Product
    {
        return Cache::remember(
            key: "product:{$id}",
            ttl: 3600,
            callback: fn () => Product::with('category')->find($id)
        );
        // Əgər cache-də varsa: DB-yə getmir
        // Yoxdursa: callback icra edilir, nəticə cache-ə yazılır
    }

    public function findAll(): Collection
    {
        return Cache::remember(
            key: 'products:all',
            ttl: 1800,
            callback: fn () => Product::with('category')->active()->get()
        );
    }
}
```

### Cache Invalidation on Update

```php
class ProductService
{
    public function __construct(
        private readonly ProductRepository $repo,
    ) {}

    public function update(int $id, UpdateProductDTO $dto): Product
    {
        $product = Product::findOrFail($id);
        $product->update($dto->toArray());

        // Cache-i invalidate et — köhnə data görünməsin
        Cache::forget("product:{$id}");
        Cache::forget('products:all');   // list cache-i də sil

        return $product->fresh();
    }

    public function delete(int $id): void
    {
        Product::findOrFail($id)->delete();

        Cache::forget("product:{$id}");
        Cache::forget('products:all');
    }
}
```

### Write-Through: Observer + Cache

```php
// Observer ilə Write-Through pattern
class ProductObserver
{
    public function updated(Product $product): void
    {
        // DB update-dan sonra avtomatik cache yenilənir
        Cache::put(
            key: "product:{$product->id}",
            value: $product->load('category'),
            ttl: 3600,
        );

        // List cache-i invalidate et (tamamilə sil — refresh lazımdır)
        Cache::forget('products:all');
        Cache::tags(['products'])->flush(); // Tag-based varsa
    }

    public function deleted(Product $product): void
    {
        Cache::forget("product:{$product->id}");
        Cache::forget('products:all');
    }
}

// AppServiceProvider-da register et
Product::observe(ProductObserver::class);
```

### Write-Behind: Queue + Cache → Batch DB Write

```php
// Write-Behind: sürətli write, async DB sync
class ProductCacheWriteBehindService
{
    private const PENDING_KEY = 'products:pending_writes';

    public function updatePrice(int $productId, float $newPrice): void
    {
        // 1. Cache-i dərhal yenilə (sürətli)
        $product = Cache::get("product:{$productId}");
        if ($product) {
            $product['price'] = $newPrice;
            Cache::put("product:{$productId}", $product, 3600);
        }

        // 2. Pending writes set-inə əlavə et
        Cache::put(
            "product:pending:{$productId}",
            ['price' => $newPrice, 'updated_at' => now()->toISOString()],
            ttl: 300, // 5 dəq ərzində worker götürsün
        );

        // 3. Batch DB sync job-unu dispatch et
        SyncProductPricesToDatabase::dispatch()->delay(30);
    }
}

// Job: pending write-ları DB-yə batch commit et
class SyncProductPricesToDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $pattern = 'product:pending:*';
        $keys = Redis::keys($pattern);

        if (empty($keys)) return;

        DB::transaction(function () use ($keys) {
            foreach ($keys as $key) {
                $data = Cache::get($key);
                if (!$data) continue;

                $productId = (int) last(explode(':', $key));
                Product::where('id', $productId)->update($data);
                Cache::forget($key);
            }
        });
    }
}
```

### Cache Warming — Cold Start Problemi

```php
// Cold start: ilk deploy-dan sonra cache boşdur
// Bütün request-lər DB-ə gedər — spike!
// Həll: cache warming (əvvəlcədən doldur)

class CacheWarmupCommand extends Command
{
    protected $signature   = 'cache:warmup';
    protected $description = 'Pre-populate cache before traffic hits';

    public function handle(): void
    {
        $this->info('Cache warming başlayır...');

        // Ən çox istifadə olunan məhsulları cache-ə yüklə
        Product::with('category')
            ->active()
            ->orderByDesc('view_count')
            ->limit(1000)
            ->chunk(100, function (Collection $products) {
                foreach ($products as $product) {
                    Cache::put(
                        "product:{$product->id}",
                        $product,
                        ttl: 3600,
                    );
                }
                $this->info("100 məhsul cache-ə yazıldı.");
            });

        // List cache-i doldur
        Cache::remember('products:featured', 3600, function () {
            return Product::featured()->with('category')->get();
        });

        $this->info('Cache warming tamamlandı.');
    }
}
```

### Tam ProductRepository — Cache-Aside + Invalidation

```php
class ProductRepository
{
    private const TTL = 3600; // 1 saat

    public function find(int $id): ?Product
    {
        return Cache::remember(
            "product:{$id}",
            self::TTL,
            fn () => Product::with('category')->find($id),
        );
    }

    public function findActive(): Collection
    {
        return Cache::remember(
            'products:active',
            self::TTL,
            fn () => Product::with('category')->active()->orderBy('name')->get(),
        );
    }

    public function save(Product $product): Product
    {
        $product->save();
        $this->invalidate($product->id);
        return $product->fresh(['category']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
        $this->invalidate($product->id);
    }

    private function invalidate(int $productId): void
    {
        Cache::forget("product:{$productId}");
        Cache::forget('products:active');
        // Əgər başqa list key-lər varsa onları da unut
    }
}
```

## Praktik Tapşırıqlar

1. `Cache::remember()` ilə sadə Product cache yaz, TTL 1 saat. Update olduqda `Cache::forget()` ilə silmə əlavə et.
2. Observer ilə Write-Through implementasiya et: `ProductObserver::updated()` cache-i yeniləsin.
3. Cache warming artisan command yaz: deploy sonrası top-100 məhsulu əvvəlcədən cache-ə yükləsin.
4. Cache key collision ssenarisi yarat: fərqli model-lər üçün eyni key prefix istifadə et, sonra düzəlt.
5. Cache-i disable edib (`CACHE_DRIVER=array`) DB query sayını benchmark ilə müqayisə et.

## Anti-Pattern Nə Zaman Olur?

**1. Cache invalidation-sız write (stale data)**
Məhsul qiyməti DB-də 899 olur, amma user hər dəfə köhnə cache-dən 999 görür — TTL bitənə qədər. Write əməliyyatlarından sonra mütləq `Cache::forget()` çağır. "TTL-i aşağı endirərik" həll deyil — bu DB yükünü artırır.

**2. Cache key collision**
Fərqli entity-lər üçün eyni key formatı: `"order:1"` həm `Order`-i həm `OrderDraft`-ı saxlayır. Key-lərdə model adı daxil et: `"product:1"`, `"order:1"`, `"draft_order:1"`.

```php
// YANLIŞ
Cache::remember("item:{$id}", 3600, fn() => Product::find($id));
Cache::remember("item:{$id}", 3600, fn() => Order::find($id)); // Collision!

// DOĞRU
Cache::remember("product:{$id}", 3600, fn() => Product::find($id));
Cache::remember("order:{$id}",   3600, fn() => Order::find($id));
```

**3. Çox uzun TTL ilə kritik data cache-ləmək**
User balance, inventory count, ya da payment status-u 24 saat TTL ilə cache-ləmək — bu data tez-tez dəyişir, stale olması ciddi problem yaradır. Kritik financial data cache-lənməməlidir, ya da çox qısa TTL (30-60 saniyə) ilə cache-lənməlidir.

**4. Write-Behind-ı uçucu storage-da tətbiq etmək**
Write-Behind cache-i Redis-də saxlayıb Redis-i persistence olmadan işlətmək — Redis restart → pending write-lar itir → DB ilə cache uyğunsuzluğu. Write-Behind üçün ya Redis persistence (AOF) aktiv et, ya da Outbox pattern istifadə et.

---

## Əlaqəli Mövzular

- [Caching Strategies](08-caching-strategies.md) — cache topology, eviction, stampede həlləri
- [Repository Pattern](../laravel/01-repository-pattern.md) — cache-i repository layer-də saxlamaq
- [CQRS](../integration/01-cqrs.md) — read model-i cache kimi düşünmək
- [Outbox Pattern](../integration/04-outbox-pattern.md) — Write-Behind-ın distributed versiyası
