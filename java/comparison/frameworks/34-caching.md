# Caching (Keşləmə)

> **Seviyye:** Intermediate ⭐⭐

## Giris

Keşləmə (caching) muasir veb tetbiqlerde performansi artirmaq ucun en vacib texnikalardan biridir. Verilenleri her defe verilener bazasindan ve ya xarici servislerden oxumaq evezine, onlari muveqqeti yaddashda saxlamaq tetbiqin suretini birdeferlik artira bilir. Hem Spring, hem de Laravel guclu keshleme mexanizmleri teqdim edir, lakin yanasmalar ferqlidir.

Spring annotation-based yanasmadan istifade edir -- `@Cacheable`, `@CacheEvict`, `@CachePut` kimi annotasiyalarla metodlarin neticelerini avtomatik keshliyir. Laravel ise facade-based yanasmadan istifade edir -- `Cache` facade-i vasitesile aciq sekilde keshleme emeliyyatlari aparir.

## Spring-de istifadesi

### Keshlemenin aktivleshdirilmesi

Spring-de keshleme istifade etmek ucun evvelce onu aktiv etmek lazimdir:

```java
@Configuration
@EnableCaching
public class CacheConfig {
    // Default cache manager istifade olunacaq
}
```

### @Cacheable -- Neticeni keshle

`@Cacheable` annotasiyasi metod neticesini keshleyir. Eyni parametrlerle novbeti cagirish zamani metod icra olunmur, keshden netice qaytarilir:

```java
@Service
public class ProductService {

    @Autowired
    private ProductRepository productRepository;

    @Cacheable(value = "products", key = "#id")
    public Product findById(Long id) {
        // Bu metod yalniz ilk defe cagirilacaq.
        // Sonraki cagirishlarda netice keshden gelib gelecek.
        log.info("Verilener bazasindan mehsul oxunur: {}", id);
        return productRepository.findById(id)
                .orElseThrow(() -> new ProductNotFoundException(id));
    }

    @Cacheable(value = "productsByCategory", key = "#category")
    public List<Product> findByCategory(String category) {
        return productRepository.findByCategory(category);
    }

    // Shertli keshleme -- yalniz qiymeti 100-den boyuk olanlari keshle
    @Cacheable(value = "expensiveProducts", key = "#id",
               condition = "#result != null && #result.price > 100")
    public Product findExpensiveProduct(Long id) {
        return productRepository.findById(id).orElse(null);
    }

    // unless -- neticeni keshlememe sherti
    @Cacheable(value = "products", key = "#id",
               unless = "#result == null")
    public Product findProductOrNull(Long id) {
        return productRepository.findById(id).orElse(null);
    }
}
```

### @CacheEvict -- Keshi temizle

Verilener deyishdikde keshi temizlemek lazimdir:

```java
@Service
public class ProductService {

    @CacheEvict(value = "products", key = "#id")
    public void deleteProduct(Long id) {
        productRepository.deleteById(id);
        // Metod icra olunduqdan sonra "products" keshinden
        // bu ID-ye uygun giris silinecek
    }

    // Butun keshi temizle
    @CacheEvict(value = "products", allEntries = true)
    public void clearAllProductCache() {
        log.info("Butun mehsul keshi temizlendi");
    }

    // Birden cox keshi temizle
    @Caching(evict = {
        @CacheEvict(value = "products", key = "#product.id"),
        @CacheEvict(value = "productsByCategory",
                    key = "#product.category")
    })
    public void updateProduct(Product product) {
        productRepository.save(product);
    }
}
```

### @CachePut -- Keshi yenile

`@CachePut` metodu her zaman icra edir, lakin neticeni keshe yazir:

```java
@Service
public class ProductService {

    @CachePut(value = "products", key = "#product.id")
    public Product updateProduct(Product product) {
        // Metod HER ZAMAN icra olunur (keshden oxumur)
        // Lakin netice keshe yazilir
        return productRepository.save(product);
    }

    @CachePut(value = "products", key = "#result.id")
    public Product createProduct(ProductCreateDto dto) {
        Product product = new Product();
        product.setName(dto.getName());
        product.setPrice(dto.getPrice());
        return productRepository.save(product);
    }
}
```

### Cache Manager konfiqurasiyasi

#### EhCache konfiqurasiyasi

```java
@Configuration
@EnableCaching
public class EhCacheConfig {

    @Bean
    public CacheManager cacheManager() {
        return new EhCacheCacheManager(ehCacheManager());
    }

    @Bean
    public net.sf.ehcache.CacheManager ehCacheManager() {
        CacheConfiguration productCache = new CacheConfiguration();
        productCache.setName("products");
        productCache.setMaxEntriesLocalHeap(1000);
        productCache.setTimeToLiveSeconds(3600); // 1 saat

        CacheConfiguration categoryCache = new CacheConfiguration();
        categoryCache.setName("productsByCategory");
        categoryCache.setMaxEntriesLocalHeap(100);
        categoryCache.setTimeToLiveSeconds(1800); // 30 deqiqe

        net.sf.ehcache.config.Configuration config =
            new net.sf.ehcache.config.Configuration();
        config.addCache(productCache);
        config.addCache(categoryCache);

        return net.sf.ehcache.CacheManager.newInstance(config);
    }
}
```

#### Redis konfiqurasiyasi

```java
@Configuration
@EnableCaching
public class RedisCacheConfig {

    @Bean
    public RedisCacheManager cacheManager(
            RedisConnectionFactory connectionFactory) {

        RedisCacheConfiguration defaultConfig =
            RedisCacheConfiguration.defaultCacheConfig()
                .entryTtl(Duration.ofHours(1))
                .serializeKeysWith(
                    RedisSerializationContext.SerializationPair
                        .fromSerializer(new StringRedisSerializer()))
                .serializeValuesWith(
                    RedisSerializationContext.SerializationPair
                        .fromSerializer(
                            new GenericJackson2JsonRedisSerializer()));

        // Ferqli keshler ucun ferqli TTL
        Map<String, RedisCacheConfiguration> cacheConfigs =
            new HashMap<>();
        cacheConfigs.put("products",
            defaultConfig.entryTtl(Duration.ofMinutes(30)));
        cacheConfigs.put("productsByCategory",
            defaultConfig.entryTtl(Duration.ofMinutes(10)));

        return RedisCacheManager.builder(connectionFactory)
                .cacheDefaults(defaultConfig)
                .withInitialCacheConfigurations(cacheConfigs)
                .build();
    }
}
```

**application.yml:**

```yaml
spring:
  redis:
    host: localhost
    port: 6379
    password: mypassword
  cache:
    type: redis
```

### Birden cox Cache Manager

```java
@Configuration
@EnableCaching
public class MultipleCacheConfig {

    @Bean
    @Primary
    public CacheManager redisCacheManager(
            RedisConnectionFactory connectionFactory) {
        return RedisCacheManager.builder(connectionFactory)
                .cacheDefaults(RedisCacheConfiguration.defaultCacheConfig()
                    .entryTtl(Duration.ofHours(1)))
                .build();
    }

    @Bean
    public CacheManager localCacheManager() {
        CaffeineCacheManager manager = new CaffeineCacheManager();
        manager.setCaffeine(Caffeine.newBuilder()
                .maximumSize(500)
                .expireAfterWrite(Duration.ofMinutes(5)));
        return manager;
    }
}

@Service
public class ProductService {

    // Default (Redis) cache manager istifade olunur
    @Cacheable("products")
    public Product findById(Long id) { ... }

    // Lokal cache manager istifade olunur
    @Cacheable(value = "configs",
               cacheManager = "localCacheManager")
    public AppConfig getConfig(String key) { ... }
}
```

## Laravel-de istifadesi

### Esasi istifade

Laravel-de `Cache` facade-i vasitesile kesh emeliyyatlari aparilir:

```php
use Illuminate\Support\Facades\Cache;

class ProductService
{
    // Keshden oxu, yoxdursa null qaytar
    public function findById(int $id): ?Product
    {
        return Cache::get("product:{$id}");
    }

    // Keshden oxu, yoxdursa default deyer qaytar
    public function findByIdOrDefault(int $id): Product
    {
        return Cache::get("product:{$id}", function () {
            return Product::defaultProduct();
        });
    }

    // Keshe yaz
    public function cacheProduct(Product $product): void
    {
        // 3600 saniye (1 saat) saxla
        Cache::put("product:{$product->id}", $product, 3600);

        // Carbon istifade et
        Cache::put(
            "product:{$product->id}",
            $product,
            now()->addHours(1)
        );
    }

    // Sonsuza qeder saxla
    public function cacheForever(Product $product): void
    {
        Cache::forever("product:{$product->id}", $product);
    }
}
```

### remember() ve rememberForever()

`remember()` metodu en cox istifade olunan keshleme uslubudur. Keshde varsa qaytarir, yoxdursa closure-u icra edib neticeni keshleyir:

```php
class ProductService
{
    public function findById(int $id): Product
    {
        return Cache::remember(
            "product:{$id}",
            now()->addHours(1),
            function () use ($id) {
                // Yalniz keshde olmadiqda icra olunur
                return Product::findOrFail($id);
            }
        );
    }

    public function findByCategory(string $category): Collection
    {
        return Cache::remember(
            "products:category:{$category}",
            now()->addMinutes(30),
            fn () => Product::where('category', $category)->get()
        );
    }

    // Sonsuza qeder keshle
    public function getSettings(): array
    {
        return Cache::rememberForever('app_settings', function () {
            return Setting::all()->pluck('value', 'key')->toArray();
        });
    }
}
```

### Keshi silme ve temizleme

```php
class ProductService
{
    public function updateProduct(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);

        // Keshi sil
        Cache::forget("product:{$id}");
        Cache::forget("products:category:{$product->category}");

        return $product;
    }

    // Butun keshi temizle
    public function clearAllCache(): void
    {
        Cache::flush();
    }
}
```

### Cache Tags

Cache tags yalniz `redis` ve `memcached` driver-lerinde ishleyir. Elageli kesh qruplarini birge idare etmeye imkan verir:

```php
class ProductService
{
    public function findById(int $id): Product
    {
        return Cache::tags(['products', 'catalog'])
            ->remember(
                "product:{$id}",
                now()->addHours(1),
                fn () => Product::findOrFail($id)
            );
    }

    public function findByCategory(string $category): Collection
    {
        return Cache::tags(['products', 'categories'])
            ->remember(
                "products:category:{$category}",
                now()->addMinutes(30),
                fn () => Product::where('category', $category)->get()
            );
    }

    // Yalniz 'products' tag-li butun keshleri temizle
    public function clearProductCache(): void
    {
        Cache::tags('products')->flush();
        // Bu hem findById, hem de findByCategory keshlerini silir
    }

    // Yalniz 'categories' tag-li keshleri temizle
    public function clearCategoryCache(): void
    {
        Cache::tags('categories')->flush();
        // Bu yalniz findByCategory keshlerini silir
    }
}
```

### Cache Driver-leri

**config/cache.php:**

```php
return [
    'default' => env('CACHE_DRIVER', 'redis'),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],
];
```

### Ferqli driver-lerden istifade

```php
class ProductService
{
    public function getCachedData(): mixed
    {
        // Default driver (Redis)
        $data = Cache::get('key');

        // Museyyen driver secmek
        $data = Cache::store('file')->get('key');
        $data = Cache::store('memcached')->get('key');
        $data = Cache::store('database')->get('key');

        return $data;
    }
}
```

### Atomic Locks (Cache vasitesile)

```php
class PaymentService
{
    public function processPayment(int $orderId): bool
    {
        // Eyni sifarishi eyni anda iki defe emal etmenin
        // qarsisini almaq ucun lock
        $lock = Cache::lock("order-lock:{$orderId}", 10);

        if ($lock->get()) {
            try {
                // Odemeni emal et
                $order = Order::findOrFail($orderId);
                $order->markAsPaid();
                return true;
            } finally {
                $lock->release();
            }
        }

        return false; // Lock alinmadi, basqa proses isleyir
    }
}
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Yanaşma** | Annotation-based (`@Cacheable`) | Facade-based (`Cache::get()`) |
| **Konfiqurasiya** | Java Config / XML | PHP array konfiq |
| **Kesh acari** | Avtomatik (metod parametrlerinden) | Manuel (ozun yazirsan) |
| **Kesh silme** | `@CacheEvict` annotasiyasi | `Cache::forget()` metodu |
| **Kesh yenileme** | `@CachePut` annotasiyasi | `Cache::put()` metodu |
| **Cache Tags** | Standart olaraq yoxdur | Redis/Memcached ile var |
| **remember()** | Yoxdur (annotasiya ozundeki mexanizm) | `Cache::remember()` |
| **Atomic Lock** | RedisTemplate ile | `Cache::lock()` |
| **Birden cox manager** | `@Qualifier` ile | `Cache::store()` ile |
| **Shertli keshleme** | `condition`, `unless` parametrleri | Manuel if/else |

## Niye bele ferqler var?

**Spring-in annotation-based yanasmasi:**
Spring AOP (Aspect-Oriented Programming) uzerine quruludur. `@Cacheable` annotasiyasi bir proxy yaradir ve metod cagirishi zamanlarinda araya girir. Bu, keshleme mentigini biznes mentiqinden ayirmaga imkan verir -- metod icerisinde kesh haqqinda hec ne yazilmir. Bu yanasma "Separation of Concerns" prinsipine uygundir ve kodu daha temiz saxlayir.

**Laravel-in facade-based yanasmasi:**
Laravel sadlik ve aciqliqi on plana cixarir. `Cache::remember()` yazanda tam olaraq ne bash verdiyini gorursen -- hansil acarin istifade olundugunu, TTL-in ne qeder oldugunu, kesh olmadiqda ne icra olunacagini. Bu yanaşma daha explicit-dir ve debugging zamani rahatdir.

**Cache Tags:**
Laravel-in cache tag sistemi cox guclu bir xususiyyetdir. Spring-de buna benzer bir mexanizm standart olaraq yoxdur -- onu manual olaraq implement etmek lazimdir. Tags vasitesile elageli keshleri qruplashdirib birge silmek mumkundur, bu xususen e-commerce kimi murekkeb tetbiqlerde faydalidir.

**Kesh acari (key):**
Spring-de kesh acari avtomatik olaraq metod parametrlerinden yaranir (SpEL ifadeleri ile customize etmek de olar). Laravel-de ise acari ozun yazirsan. Spring-in yanasmasi rahatdir amma bezen gozlenilmez naticalara sebeb ola biler. Laravel-in yanasmasi daha explicit-dir.

## Hansi framework-de var, hansinda yoxdur?

**Yalniz Spring-de:**
- `@Cacheable`, `@CacheEvict`, `@CachePut` annotasiyalari -- deklarativ keshleme
- `condition` ve `unless` ile shertli keshleme annotasiya seviyyesinde
- `@Caching` ile birden cox kesh emeliyyatini bir annotasiyada birlashdirmek
- SpEL (Spring Expression Language) ile kesh acari ifadeleri

**Yalniz Laravel-de:**
- `Cache::remember()` ve `Cache::rememberForever()` -- get-or-set bir metodda
- Cache Tags -- elageli keshleri qruplashdirmaq ve birge silmek
- `Cache::lock()` -- atomic lock mexanizmi birbasah Cache API-den
- `Cache::forever()` -- TTL-siz keshleme
- `Cache::store()` ile runtime-da driver deyishdirmek
- `cache()` helper funksiyasi -- facade olmadan istifade
