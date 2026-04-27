# Request Coalescing / Singleflight Pattern (Lead)

## İcmal

**Singleflight** (request coalescing, request deduplication) — eyni key üçün paralel icra olunan çoxlu sorğuları bir sorğuya birləşdirən pattern-dir. Yalnız ilk thread sorğunu icra edir; digər thread-lər gözləyir və eyni nəticəni alır.

Go-da `singleflight` package-i var. Java-da eyni effekti `ConcurrentHashMap + CompletableFuture` kombinasiyası ilə əldə etmək olar.

---

## Niyə Vacibdir

**Thundering Herd problemi:** Cache miss zamanı eyni key üçün 1000 concurrent request DB-yə eyni anda vurur. Bu:
- DB-ni həddindən artıq yükləyir
- Response time-ı artırır
- Bəzən DB crash-a gətirib çıxarır

Singleflight ilə 1000 request-dən yalnız 1-i DB-yə gedir. Digər 999 thread eyni CompletableFuture-u gözləyir.

**Real dünya ssenariləri:**
- Populyar məhsulun (trending product) detalları
- Homepage üçün əsas kateqoriyalar
- Exchange rate API çağırışı
- Expensive DB aggregation sorğusu

---

## Əsas Anlayışlar

**Singleflight vs Cache:**

| Xüsusiyyət | Cache | Singleflight |
|---|---|---|
| Məqsəd | Nəticəni saxlamaq, növbəti sorğuda istifadə etmək | Concurrent sorğuları birləşdirmək |
| Effekt müddəti | TTL bitənə qədər | İlk sorğu bitənə qədər |
| Cache miss zamanı | Thundering herd yarana bilər | Thundering herd-ə qarşı qoruyur |
| Kombinasiya | Cache + Singleflight birlikdə işlədilir | — |

**Əməliyyat ardıcıllığı:**
```
Request 1 (product:42) → inFlight-da yoxdur → CompletableFuture yarat → DB sorğusu başlat
Request 2 (product:42) → inFlight-da var! → Request 1-in Future-unu gözlə
Request 3 (product:42) → inFlight-da var! → Request 1-in Future-unu gözlə
...
DB sorğusu bitir → Future complete → Bütün (1, 2, 3...) eyni nəticəni alır
Future → inFlight-dan çıxar
```

---

## Praktik Baxış

**Ne vaxt istifadə et:**
- Cache miss zamanı DB sorğusu çox bahadırsa (> 100ms)
- Eyni key üçün yüksək concurrent traffic gözlənilirsə
- Xarici API çağırışı rate limit-ə sahib olduqda

**Ne vaxt lazım deyil:**
- Hər request fərqli key üçündürsə (duplicate az)
- Sorğu çox sürətlidirsə (< 5ms) — overhead yararsız olur
- Write operasiyalarında — yalnız read üçün uyğundur

**Error propagation:**
- İlk thread exception atarsa, bütün gözləyən thread-lər eyni exception alır
- Bu istənilən davranışdır — lakin bunu bilmək vacibdir

---

## Nümunələr

### Ümumi Nümunə

Flash sale zamanı eyni məhsula 10,000 concurrent request gəlir. Cache miss halında singleflight olmadan 10,000 DB sorğusu olacaq. Singleflight ilə yalnız 1 sorğu olur — digər 9,999 nəticəni paylaşır.

### Kod Nümunəsi

#### 1. DIY Singleflight — ConcurrentHashMap + CompletableFuture

```java
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ConcurrentHashMap;
import java.util.function.Supplier;

/**
 * Generic singleflight implementation.
 * Eyni key üçün concurrent sorğuları bir sorğuya birləşdirir.
 */
public class Singleflight<K, V> {

    // Key → in-flight CompletableFuture
    private final ConcurrentHashMap<K, CompletableFuture<V>> inFlight =
        new ConcurrentHashMap<>();

    /**
     * key üçün dəyər əldə et.
     * Əgər eyni key üçün sorğu artıq icra olunursa, nəticəni gözlə.
     *
     * @param key      cache key
     * @param supplier real sorğunu icra edən funksiya (DB, API, etc.)
     * @return CompletableFuture<V> — bütün concurrent sorğular eyni future-u paylaşır
     */
    public CompletableFuture<V> execute(K key, Supplier<V> supplier) {
        // computeIfAbsent — thread-safe: yalnız bir thread üçün future yaradır
        CompletableFuture<V> existingFuture = inFlight.get(key);
        if (existingFuture != null) {
            return existingFuture; // Artıq icra olunur — gözlə
        }

        CompletableFuture<V> newFuture = new CompletableFuture<>();
        CompletableFuture<V> previous = inFlight.putIfAbsent(key, newFuture);

        if (previous != null) {
            // Race condition — başqa thread əvvəl daxil oldu
            return previous;
        }

        // Bu thread "winner" — sorğunu icra edir
        CompletableFuture.supplyAsync(supplier)
            .whenComplete((result, throwable) -> {
                // Map-dən çıxar (başqa sorğular artıq yeni lookup edə bilər)
                inFlight.remove(key, newFuture);

                if (throwable != null) {
                    newFuture.completeExceptionally(throwable);
                } else {
                    newFuture.complete(result);
                }
            });

        return newFuture;
    }
}
```

**İstifadəsi:**

```java
@Service
@Slf4j
public class ProductService {

    private final ProductRepository productRepo;
    private final CacheManager cacheManager;
    private final Singleflight<Long, Product> singleflight = new Singleflight<>();

    public CompletableFuture<Product> getProduct(Long productId) {
        // 1. Əvvəlcə cache-ə bax
        Cache cache = cacheManager.getCache("products");
        Product cached = cache.get(productId, Product.class);
        if (cached != null) {
            return CompletableFuture.completedFuture(cached);
        }

        // 2. Cache miss → singleflight ilə DB sorğusu
        return singleflight.execute(productId, () -> {
            log.info("DB query for product: {}", productId);

            Product product = productRepo.findById(productId)
                .orElseThrow(() -> new EntityNotFoundException("Product: " + productId));

            // DB-dən gəldikdən sonra cache-ə yaz
            cache.put(productId, product);
            return product;
        });
    }
}
```

---

#### 2. Caffeine AsyncLoadingCache — Tövsiyə olunan yanaşma

Caffeine library-sinin `AsyncLoadingCache`-i built-in singleflight semantikasına malikdir. Eyni key üçün loader yalnız bir dəfə çağırılır.

```xml
<dependency>
    <groupId>com.github.ben-manes.caffeine</groupId>
    <artifactId>caffeine</artifactId>
    <version>3.1.8</version>
</dependency>
```

```java
@Configuration
public class CacheConfig {

    @Bean
    public AsyncLoadingCache<Long, Product> productAsyncCache(ProductRepository repo) {
        return Caffeine.newBuilder()
            .maximumSize(10_000)
            .expireAfterWrite(Duration.ofMinutes(5))
            .recordStats()              // Metrics üçün
            // Loader — eyni key üçün yalnız bir dəfə çağırılır (singleflight!)
            .buildAsync(productId -> {
                log.info("Cache miss — loading product: {}", productId);
                return repo.findById(productId)
                    .orElseThrow(() -> new EntityNotFoundException("Product: " + productId));
            });
    }
}
```

**Service:**

```java
@Service
public class ProductServiceWithCaffeine {

    private final AsyncLoadingCache<Long, Product> productCache;

    public CompletableFuture<Product> getProduct(Long productId) {
        // Cache miss olduqda loader yalnız bir dəfə çağırılır
        // Concurrent sorğular eyni CompletableFuture-u paylaşır
        return productCache.get(productId);
    }

    // Cache invalidation
    public void invalidateProduct(Long productId) {
        productCache.synchronous().invalidate(productId);
    }
}
```

---

#### 3. Spring @Cacheable — Singleflight olmadığı üçün race condition

```java
@Service
public class ProductServiceWithoutSingleflight {

    @Cacheable(value = "products", key = "#productId")
    public Product getProduct(Long productId) {
        // PROBLEM: Concurrent cache miss halında bu metod
        // birdən çox thread tərəfindən eyni anda çağırıla bilər!
        // Spring @Cacheable singleflight zəmanəti vermir.
        log.info("DB query for product: {}", productId);  // Birdən çox dəfə çap oluna bilər!
        return productRepo.findById(productId).orElseThrow();
    }
}
```

**Həll — @Cacheable + custom singleflight:**

```java
@Service
public class ProductServiceFixed {

    private final ProductRepository productRepo;
    private final Singleflight<Long, Product> singleflight = new Singleflight<>();

    @Cacheable(value = "products", key = "#productId")
    public Product getProduct(Long productId) {
        // @Cacheable cache miss-i aşkar edir
        // Singleflight DB-yə duplicate sorğuları önləyir
        try {
            return singleflight.execute(productId,
                () -> productRepo.findById(productId).orElseThrow()
            ).get(); // Sync context üçün .get() istifadə et
        } catch (InterruptedException | ExecutionException e) {
            throw new RuntimeException("Failed to load product: " + productId, e);
        }
    }
}
```

---

#### 4. Metrics ilə monitoring

```java
@Service
@Slf4j
public class InstrumentedProductService {

    private final ProductRepository productRepo;
    private final AsyncLoadingCache<Long, Product> cache;
    private final MeterRegistry meterRegistry;

    private final Counter dbQueriesCounter;
    private final Counter cacheHitsCounter;
    private final Timer dbQueryTimer;

    public InstrumentedProductService(ProductRepository productRepo,
                                      AsyncLoadingCache<Long, Product> cache,
                                      MeterRegistry meterRegistry) {
        this.productRepo = productRepo;
        this.cache = cache;
        this.meterRegistry = meterRegistry;

        this.dbQueriesCounter = Counter.builder("product.db.queries")
            .description("Total DB queries for products")
            .register(meterRegistry);

        this.cacheHitsCounter = Counter.builder("product.cache.hits")
            .description("Total product cache hits")
            .register(meterRegistry);

        this.dbQueryTimer = Timer.builder("product.db.query.duration")
            .description("Product DB query duration")
            .register(meterRegistry);
    }

    public CompletableFuture<Product> getProduct(Long productId) {
        CacheStats stats = cache.synchronous().stats();
        cacheHitsCounter.increment(stats.hitCount());

        return cache.get(productId); // Caffeine singleflight daxildir
    }
}
```

**Prometheus metric-ləri yoxla:**

```
product_db_queries_total: 1      # 1000 concurrent request, lakin 1 DB query
product_cache_hits_total: 999    # Qalan 999-u cache-dən
```

---

#### 5. Timeout Handling

```java
public class SingleflightWithTimeout<K, V> {

    private final ConcurrentHashMap<K, CompletableFuture<V>> inFlight = new ConcurrentHashMap<>();

    public V execute(K key, Supplier<V> supplier, Duration timeout) {
        CompletableFuture<V> future = getOrCreateFuture(key, supplier);

        try {
            return future.get(timeout.toMillis(), TimeUnit.MILLISECONDS);
        } catch (TimeoutException e) {
            // Timeout — bu thread üçün exception at
            // Digər thread-lər hələ də gözləyə bilər
            throw new ServiceTimeoutException("Timed out waiting for key: " + key, e);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            throw new RuntimeException("Interrupted", e);
        } catch (ExecutionException e) {
            // İlk thread-in exception-u bütün gözləyənlərə yayılır
            throw new RuntimeException("Execution failed", e.getCause());
        }
    }

    private CompletableFuture<V> getOrCreateFuture(K key, Supplier<V> supplier) {
        CompletableFuture<V> newFuture = new CompletableFuture<>();
        CompletableFuture<V> existing = inFlight.putIfAbsent(key, newFuture);
        if (existing != null) {
            return existing;
        }

        CompletableFuture.supplyAsync(supplier)
            .whenComplete((result, throwable) -> {
                inFlight.remove(key, newFuture);
                if (throwable != null) {
                    newFuture.completeExceptionally(throwable);
                } else {
                    newFuture.complete(result);
                }
            });

        return newFuture;
    }
}
```

---

## Praktik Tapşırıqlar

1. **Baseline benchmark:** JMH ilə 1000 concurrent thread, `productId=42`. Singleflight olmadan neçə DB sorğusu gedir? Singleflight ilə neçəyə endirir?

2. **Caffeine AsyncLoadingCache testi:** Caffeine cache `recordStats()` aktiv et. Concurrent request-lər göndər. `stats().hitCount()` vs `stats().loadCount()` müqayisə et.

3. **Error propagation testi:** Supplier-i exception atmaq üçün konfiqurasiya et. 10 concurrent sorğudan hamısı eyni exception almalıdır. Bunu `CountDownLatch` ilə test et.

4. **Timeout test:** Supplier-i 2 saniyə sleep etdir. 500ms timeout ilə singleflight çağır. `ServiceTimeoutException` almalısan. Digər gözləyən thread-lər hələ gözləyirmi?

5. **Metrics dashboard:** Grafana-da `product_db_queries_total` vs `product_cache_hits_total` qrafikini qur. Flash sale simulyasiyası — 100 concurrent request, 1 DB query olduğunu göstər.

---

## Əlaqəli Mövzular

- `java/spring/106-background-jobs-patterns.md` — @Async thread pool (singleflight-ın async varianta tətbiqi)
- `java/comparison/frameworks/` — Spring Cache abstraction
- `java/advanced/06-cloud-resilience4j.md` — Cache + Bulkhead pattern
- `java/core/100-object-pool-pattern.md` — Resource reuse pattern
