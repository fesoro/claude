# Caching Strategies (Senior)

## Mündəricat
1. [Cache Strategiyaları](#cache-strategiyaları)
2. [Cache-aside](#cache-aside)
3. [Write-through](#write-through)
4. [Write-behind (Write-back)](#write-behind-write-back)
5. [Read-through](#read-through)
6. [Cache Stampede (Thundering Herd)](#cache-stampede-thundering-herd)
7. [Cache Invalidation](#cache-invalidation)
8. [PHP İmplementasiyası](#php-implementasiyası)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Cache Strategiyaları

```
// Bu kod cache strategiyalarını read/write xüsusiyyətləri və consistency baxımından müqayisə edir
Strategiya seçimi:
  Read-heavy:  Cache-aside, Read-through
  Write-heavy: Write-behind
  Consistency: Write-through
  
  ┌──────────────────┬───────────┬────────────┬──────────────┐
  │                  │ Read Hit  │ Write      │ Consistency  │
  ├──────────────────┼───────────┼────────────┼──────────────┤
  │ Cache-aside      │ Fast      │ DB only    │ Eventual     │
  │ Read-through     │ Fast      │ DB only    │ Eventual     │
  │ Write-through    │ Fast      │ Cache + DB │ Strong       │
  │ Write-behind     │ Fast      │ Cache only │ Eventual     │
  └──────────────────┴───────────┴────────────┴──────────────┘
```

---

## Cache-aside

```
// Bu kod cache-aside (lazy loading) pattern-inin read/write axınını göstərir
(Lazy Loading)

Read:
  1. Cache-ə bax
  2. Hit → qaytar
  3. Miss → DB-dən al, cache-ə yaz, qaytar

  App ──GET──► Cache
              │ Miss
              ▼
              DB ──► App ──SET──► Cache
                         
Write:
  1. DB-ə yaz
  2. Cache-i inval et (sil) ← SET etmə!

  App ──WRITE──► DB
       └──DEL──► Cache

✅ Yalnız istifadə edilən data cache-lənir
✅ Cache crash-ı data itkisi deyil
✅ En geniş yayılmış strategiya
❌ Cache miss zamanı 3 addım (latency)
❌ Stale data mümkündür (invalidation problemsiz deyil)
```

---

## Write-through

```
// Bu kod write-through pattern-inin cache və DB-ni eyni anda yeniləməsini göstərir
Write: Cache + DB eyni anda yenilənir

  App ──WRITE──► Cache ──WRITE──► DB

Read:
  App ──GET──► Cache ──Hit──► Data
                    └─Miss──► (nadirdir, data həmişə sync)

✅ Cache həmişə fresh data saxlayır
✅ Read-after-write consistency
❌ Write latency artır (cache + db)
❌ Az oxunan data da cache-ə düşür (space waste)

Nümunə: User profile (tez-tez oxunan, consistency vacib)
```

---

## Write-behind (Write-back)

```
// Bu kod write-behind pattern-inin aşağı latency ilə async DB yazmasını göstərir
Write: Yalnız cache-ə yaz, sonra async DB-ə yaz

  App ──WRITE──► Cache
                  │ (async)
                  ▼
                  DB (batch write, gecikmə ilə)

✅ Write latency çox aşağıdır
✅ DB-ə batch write → throughput yüksək
❌ Cache crash olsa data itirilir
❌ Race condition: cache evict olunarsa DB update olmaz
❌ Complex implementation

Nümunə: View counter, like count (az critical, high write)
```

---

## Read-through

```
// Bu kod read-through pattern-inin cache-in özünün DB-dən data almasını göstərir
Cache data source gibi davranır — app yalnız cache ilə danışır

  App ──GET──► Cache
                │ Miss
                ▼
                Cache özü DB-dən alır → app-a qaytar

Write-through ilə kombinasiya:
  App ──WRITE──► Cache ──(sync)──► DB
  App ──READ───► Cache ──(miss)──► Cache DB-dən alır

✅ App kodu sadədir (cache miss logic cache provider-dədir)
❌ Cache provider DB-dən data almağı bilməlidir
Nümunə: CDN (origin-dən pull), Varnish reverse proxy cache
```

---

## Cache Stampede (Thundering Herd)

```
// Bu kod cache stampede (thundering herd) problemini və müxtəlif həll yollarını izah edir
Problem:
  Popular cache key expire olur
  Eyni anda 1000 sorğu gəlir
  Hamısı cache miss → hamısı DB-yə gedir
  DB overload!

    ┌──────────────────────────────────┐
    │  Cache key expires at t=100      │
    │                                  │
    │  t=100: 1000 requests arrive     │
    │  All miss cache                  │
    │  All hit DB simultaneously! 💥   │
    └──────────────────────────────────┘

Həll 1: Mutex/Lock
  Yalnız 1 proses DB-dən alır, digərləri gözləyir

Həll 2: Probabilistic Early Expiration (XFetch)
  Expire olmadan əvvəl random olaraq yenilə
  
Həll 3: Stale-while-revalidate
  Köhnə cache-i qaytar, arxada yenilə

Həll 4: Pre-warming
  Deploy-dan əvvəl cache-i doldur
```

---

## Cache Invalidation

```
// Bu kod cache invalidation strategiyalarını (TTL, event-driven, versioning) izah edir
"There are only two hard things in Computer Science:
 cache invalidation and naming things." — Phil Karlton

Strategiyalar:

1. TTL-based (Time-to-Live):
   Key expire olur, yenilənir
   ✅ Sadə, ❌ Stale data TTL müddətincə

2. Event-driven invalidation:
   Data dəyişdikdə cache DEL
   ✅ Daha fresh, ❌ Race condition

3. Write-through:
   Cache + DB sync write
   ✅ Həmişə fresh, ❌ Write yavaş

4. Cache versioning / tagging:
   user:1:v3 → version artır (sil əvəzinə)
   products:tag:electronics → tag-a görə invalidate

Race condition:
   1. App DB-dən okuyur (eski data)
   2. Başqası DB-ni yeniləyir + cache-i sil
   3. İlk app eski datanı cache-ə yazır!
   → Stale data cache-ə düşdü!
   
Həll: Cache-aside-da SET əvəzinə DEL,
       Write-through, versioning
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod cache-aside, stampede lock, XFetch, tagging və write-through pattern-lərini göstərir
// Cache-aside pattern
class ProductRepository
{
    public function find(int $id): ?Product
    {
        return Cache::remember("product:$id", 3600, function () use ($id) {
            return Product::find($id);
        });
    }
    
    public function update(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);
        
        // Cache invalidate (SET yox!)
        Cache::forget("product:$id");
        Cache::tags(['products'])->flush();  // Əlaqəli cache-lər
        
        return $product;
    }
}

// Cache Stampede — Mutex Lock
class StampedeProtectedCache
{
    public function get(string $key, int $ttl, callable $callback): mixed
    {
        $value = Cache::get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        // Lock: yalnız 1 proses DB-yə müraciət edir
        $lockKey = "lock:$key";
        $lock    = Cache::lock($lockKey, 10);  // 10s lock
        
        try {
            if ($lock->get()) {
                // Double-check (biri lock alarkən digəri doldurmuş ola bilər)
                $value = Cache::get($key);
                if ($value !== null) {
                    return $value;
                }
                
                $value = $callback();
                Cache::put($key, $value, $ttl);
                return $value;
            }
        } finally {
            $lock->release();
        }
        
        // Lock ala bilmədik, qısa gözlə və yenidən cəhd et
        usleep(50 * 1000);
        return Cache::get($key) ?? $callback();
    }
}

// Probabilistic Early Expiration (XFetch)
class XFetchCache
{
    public function get(string $key, int $ttl, callable $callback, float $beta = 1.0): mixed
    {
        $cached = Cache::get($key . ':data');
        $expiry = Cache::get($key . ':expiry');
        
        if ($cached === null || $expiry === null) {
            return $this->refresh($key, $ttl, $callback);
        }
        
        // Early recompute probability
        $now   = microtime(true);
        $delta = microtime(true);  // Compute time estimate
        
        if ($now - $beta * $delta * log(mt_rand() / mt_getrandmax()) >= $expiry) {
            // Probabiistically refresh
            return $this->refresh($key, $ttl, $callback);
        }
        
        return $cached;
    }
    
    private function refresh(string $key, int $ttl, callable $callback): mixed
    {
        $value = $callback();
        Cache::put($key . ':data', $value, $ttl + 60);
        Cache::put($key . ':expiry', microtime(true) + $ttl, $ttl + 60);
        return $value;
    }
}

// Cache Tagging (invalidation qrupları)
class CategoryCache
{
    public function getProducts(int $categoryId): array
    {
        return Cache::tags(["category:$categoryId", 'products'])
            ->remember("category:$categoryId:products", 3600, function () use ($categoryId) {
                return Product::where('category_id', $categoryId)->get()->toArray();
            });
    }
    
    public function invalidateCategory(int $categoryId): void
    {
        // Bütün category-related cache-ləri sil
        Cache::tags(["category:$categoryId"])->flush();
    }
    
    public function invalidateAllProducts(): void
    {
        Cache::tags(['products'])->flush();
    }
}

// Write-through pattern
class UserProfileCache
{
    public function update(int $userId, array $data): User
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = User::findOrFail($userId);
            $user->update($data);
            
            // Cache-i də yenilə (write-through)
            Cache::put("user:$userId", $user->toArray(), 86400);
            
            return $user;
        });
    }
    
    public function get(int $userId): ?array
    {
        return Cache::remember("user:$userId", 86400, function () use ($userId) {
            return User::find($userId)?->toArray();
        });
    }
}
```

---

## İntervyu Sualları

**1. Cache-aside vs Write-through fərqi nədir?**
Cache-aside (lazy): Read-da miss olduqda DB-dən al, cache-ə yaz. Write-da cache-i sil. Yalnız istifadə olunan data cache-lənir. Write-through: Write-da həm cache, həm DB yenilənir. Cache həmişə fresh, amma write yavaş.

**2. Cache stampede nədir, necə həll edilir?**
Popular cache key expire olanda eyni anda çox sorğu miss edir, hamı DB-yə gedir. Həll: Mutex lock (yalnız 1 proses DB-dən alır), Probabilistic Early Expiration (expire-dan əvvəl random yenilə), Stale-while-revalidate (köhnəni qaytar, arxada yenilə).

**3. Cache invalidation-ın çətin tərəfi nədir?**
Write + cache invalidation arasında race condition. App köhnə datanı DB-dən alıb cache-ə yazarkən başqası DB-ni yeniləyib cache-i silə bilər → stale data. Həll: DEL (SET yox), versioning, write-through.

**4. Cache tagging nədir?**
Əlaqəli cache key-ləri qruplama. `Cache::tags(['products'])` ilə `products` tağlı bütün cache-ləri bir dəfəyə flush etmək. Category silinəndə o category-ə aid bütün product cache-ləri silinir. Redis-ə ehtiyac var (file driver dəstəkləmir).

**5. Write-behind-ın riski nədir?**
Cache crash olduqda DB-ə yazılmamış data itirilir. Cache eviction zamanı data DB-ə çatmadan silinə bilər. Race condition: iki write eyni key-ə gələrsə sıra pozula bilər. Yalnız durability kritik olmayan data üçün (view count, analytics).

**6. Cache poisoning nədir?**
Cache-ə yanlış/zərərli data yazılması. Həll: cache key-lərini user input-dan yaratmaqdan çəkinin; signed/hashed key-lər istifadə edin; DB-dən gələn data-nı cache-ə yazmazdan əvvəl validate edin.

**7. CDN caching REST API üçün necə işləyir?**
`Cache-Control: public, max-age=300` header-ı ilə GET sorğuları CDN-də 5 dəq cache-lənir. `ETag` / `Last-Modified` ilə conditional request: client ETag göndərir, dəyişməyibsə 304 Not Modified. API versioning-i (v1, v2) cache busting üçün istifadə edilir.

**8. Laravel-də `Cache::remember` vs `Cache::rememberForever` nə zaman seçilir?**
`remember($key, $ttl, $callback)`: TTL verilir, expire olduqda callback çalışır. `rememberForever`: TTL yoxdur, yalnız manual `forget` ilə silinir. `rememberForever` yalnız nadir dəyişən static data üçün (config, enum-lar); dinamik data üçün həmişə TTL verin.

---

## Anti-patternlər

**1. Cache invalidation olmadan write-aside istifadə etmək**
DB yazılır, cache key silinmir — növbəti oxumada köhnə data qaytarılır, istifadəçi öz update-ini görə bilmir. Write-dan sonra həmişə müvafiq cache key-i silin (`Cache::forget()`); `SET` deyil, `DEL` istifadə edin ki, race condition riski azalsın.

**2. Cache stampede üçün heç bir qoruma tətbiq etməmək**
Populyar key expire olur, eyni anda yüzlərlə request miss alır, hamısı DB-yə gedir — DB həddindən artıq yüklənir, cavab müddəti uzanır. Mutex lock tətbiq edin: yalnız bir proses DB-dən alıb cache-ə yazsın, digərləri gözləsin; ya da Probabilistic Early Expiration ilə expire-dan əvvəl yeniləyin.

**3. Cache key-lərini versiyalanmadan deploy zamanı uyuşmazlıq**
Yeni kod deploy olunur, köhnə struktura sahib cache key-lər hənüz xaricdə durur — yeni kod köhnə formatı parse edərkən xəta atır. Cache key-lərini versiyalandırın (`product:v2:{id}`) ya da deploy zamanı cache flush prosedurunuzu hazırlayın.

**4. Bütün datanı eyni TTL ilə cache etmək**
User profili, product kataloqu, ödəniş statusu hamısı 60 dəqiqə TTL — ödəniş statusu 60 dəqiqə köhnə qalır. Hər data növü üçün ayrı TTL müəyyən edin: tez dəyişən data qısa TTL (30s), nadir dəyişən data uzun TTL (24s); kritik real-time data cache edilməsin.

**5. Cache tag-larını file driver ilə istifadə etmək**
`Cache::tags(['products'])->put(...)` — file driver cache tag-larını dəstəkləmir, `BadMethodCallException` atır. Cache tag-ları üçün Redis ya da Memcached driver lazımdır; `.env`-dəki `CACHE_DRIVER=redis` olmadan production-da tag-lardan istifadə etməyin.

**6. Cache hit/miss nisbətini izləməmək**
Cache quruludur, amma cache hit rate-i ölçülmür — hit rate 10%-dirsə cache demək olar işləmir, DB-yə hər sorğu gedir. `cache:hit` / `cache:miss` metric-lərini Prometheus-a export edin; hit rate-i 80%+ hədəf götürün, aşağı olduqda TTL və ya cache strategy-ni nəzərdən keçirin.
