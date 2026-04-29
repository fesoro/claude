# Caching Strategies (Senior ⭐⭐⭐)

## İcmal
Caching, sistemin performansını artırmaq üçün ən effektiv texnikalardan biridir. Düzgün tətbiq olunmuş cache, database load-unu 90%+ azalda, latency-ni millisekunddan mikrosekundlara endirib bilər. Interview-larda caching mövzusu həmişə "Bu sistemi necə scale edərdiniz?" sualının içindədir — çünki cache olmadan əksər sistemlər scale ola bilmir.

## Niyə Vacibdir
Netflix-in 99%+ hit ratio ilə CDN cache-i, Twitter-in Redis-də timeline cache-i, Google-un Bigtable üzərindəki Memcache layer-i — dünyənin ən böyük sistemlərinin hamısı aggressive caching istifadə edir. Cache hit ratio 1%-dən 99%-ə qaldırmaq database server sayını 100x azaltmaq deməkdir. Bu mövzunu dərindən bilən namizəd Senior statusuna uyğundur.

## Əsas Anlayışlar

### 1. Cache Növləri

**In-Process Cache (Local Cache)**
- Application memory-sində saxlanır
- Ultra-fast: L1/L2 cache səviyyəsində
- Server restart olduqda itirilir
- Problem: N server = N ayrı cache (inconsistency)
- Use case: Config, lookup tables, rarely-changed data

```php
// Laravel in-memory cache (request lifecycle)
$value = Cache::remember('key', 60, fn() => DB::query());
```

**Distributed Cache (Remote Cache)**
- Redis, Memcached, Hazelcast
- Network hop lazımdır (1-2ms)
- Bütün serverlar eyni data görür
- Horizontal scale edilə bilər (Redis Cluster)
- Use case: Session, user data, API responses

**CDN Cache**
- Edge server-lərdə static/semi-static content
- Coğrafi baxımdan user-ə yaxın
- Use case: Images, videos, CSS/JS, API responses

### 2. Cache-Aside Pattern (Lazy Loading)
```
Read:
1. Cache-dən oxu
2. Cache miss → DB-dən oxu
3. Cache-ə yaz
4. Return

Write:
1. DB-ə yaz
2. Cache-dəki dəyəri sil (invalidate)
```

**Pros:** Yalnız lazım olan data cache-ə düşür.
**Cons:** İlk request həmişə DB-ə gedir (cold start), cache stampede riski.

### 3. Write-Through Pattern
```
Write:
1. Cache-ə yaz
2. DB-ə yaz (eyni anda)
3. Return success

Read:
1. Cache-dən oxu (həmişə fresh data var)
```

**Pros:** Cache həmişə DB ilə sinxronizədir.
**Cons:** Hər write üçün iki yazma → latency artır. Cache-də heç oxunmayan data da yazılır.

### 4. Write-Behind (Write-Back) Pattern
```
Write:
1. Cache-ə yaz
2. Return success (DB-ə yazmadan!)
3. Background: batch olaraq DB-ə yaz
```

**Pros:** Write latency minimal (yalnız cache write).
**Cons:** DB yazılmadan server crash olsa data itirilir. Risky for critical data.

### 5. Read-Through Pattern
```
Read:
1. Cache-ə sor
2. Cache miss → Cache özü DB-dən oxuyur, cache-ə yazır
3. Cached data-nı return edir
```

Application, DB-ni birbaşa bilmir — hər şey cache üzərindən gedir.

### 6. Cache Invalidation Strategiyaları
Bu, caching-in ən çətin hissəsidir:

**TTL (Time-to-Live)**
- Hər key üçün expiry time
- Sadə, amma stale data risk var
- `SET key value EX 3600` (1 saat)

**Event-based Invalidation**
- Data dəyişdikdə event publish et
- Event consumer cache-i invalidate edir
- Real-time fresh data, mürəkkəb arxitektura

**Write-through Invalidation**
- Write olduqda cache-dəki versiyonu sil
- Növbəti read yeni data çəkir

**Versioned Keys**
```
cache_key = "user:{id}:v{version}"
User yeniləndikdə version artır
Köhnə key-lər TTL ilə özü silir
```

### 7. Cache Eviction Policies
Redis eviction alqoritmləri:

| Policy | Açıqlama | Use Case |
|--------|----------|----------|
| LRU (Least Recently Used) | Ən az istifadə edilən silir | General purpose |
| LFU (Least Frequently Used) | Ən az tez-tez istifadə edilən | Popularity-based |
| TTL-based | Expiry zamanı çatana silir | Time-sensitive data |
| Random | Təsadüfi silir | Sadə, heç bir zəmanət yox |
| No-eviction | Dolu olduqda error | Critical data, monitoring |

### 8. Cache Stampede (Thundering Herd)
**Problem:** Populyar cache key expire olur. Eyni anda 10,000 request DB-yə gedir.

**Həll 1: Mutex Lock**
```
Cache miss → Lock al → DB-dən oxu → Cache-ə yaz → Lock burax
Digər request-lər lock-u gözləyir, sonra cache-dən oxuyur
```

**Həll 2: Probabilistic Early Expiration**
TTL bitməzdən əvvəl, kiçik probability ilə yeniləmə başlayır.
`if random() < f(remaining_ttl): refresh cache`

**Həll 3: Background Refresh**
TTL bitməzdən əvvəl background job cache-i yeniləyir.
User həmişə fresh cache görür.

### 9. Cache Warm-Up
Cold start problemini həll edir:
- Deployment-dan əvvəl cache-i hot data ilə doldur
- Blue/green deployment: Yeni cluster aktiv olmadan önce warm-up
- Cron job ilə populyar data-nı pre-populate et

### 10. Cache Hit Ratio
```
Hit Ratio = Cache Hits / (Cache Hits + Cache Misses)
```

- < 80%: Problem var, caching strategiyasını yenidən bax
- 80-90%: Yaxşı
- 90-95%: Çox yaxşı
- > 95%: Əla (Netflix, Google səviyyəsi)

Hit ratio aşağı olduqda:
- TTL çox qısadır
- Cache size kiçikdir (çox eviction)
- Cache key dizaynı yanlışdır
- Hot data yanlış seçilib

### 11. Cache Partitioning (Redis Cluster)
```
Key space 0-16383 hash slot-a bölünür
Hash slot = CRC16(key) % 16384

Node A: slot 0-5460
Node B: slot 5461-10922
Node C: slot 10923-16383

Client hash slot-u hesablayır, doğru node-a yönlənir
```

### 12. Cache-ul Nə Zaman İstifadə ETMƏMƏLİ
- Hər request-də unikal data (user-specific dynamic content)
- Financial transactions (stale data = problem)
- Real-time data (stock prices, live scores)
- Cache ilə DB sync olmayan critical data
- Az istifadə olunan data (cache overhead > benefit)

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Cache əlavə edirəm" deyəndə hansı pattern-i izah et
2. Cache invalidation probleminə toxun
3. TTL seçimini əsaslandır (niyə 1 saat, niyə 5 dəq?)
4. Cache stampede-i qeyd et və həllini izah et
5. Hit ratio-nu necə monitorinq edəcəyini soruş

### Ümumi Namizəd Səhvləri
- "Cache əlavə etsək hamısı həll olar" düşüncəsi
- Cache invalidation strategiyasından danışmamaq
- Distributed cache-in network latency-ni unutmaq
- Cache-in özünün fail olduğu scenario-nu düşünməmək
- TTL seçimini əsaslandırmamaq

### Senior vs Architect Fərqi
**Senior**: Cache-aside/write-through seçir, TTL konfiqurasiya edir, cache invalidation mexanizmi qurur.

**Architect**: Multi-tier caching strategy (L1+L2+CDN), cache-un business impact-ini ölçür (cache miss cost), cache budgeti planlaşdırır, consistency guarantee-ləri sənədləşdirir, read/write ratio-ya əsasən optimal strategy müəyyən edir.

## Nümunələr

### Tipik Interview Sualı
"Design the caching layer for an e-commerce product catalog serving 100K RPS."

### Güclü Cavab
```
E-commerce catalog caching tələbləri:
- 100K RPS read
- Product count: 10M products
- Update frequency: 100 product update/sec
- Latency requirement: p99 < 50ms

Strategy:
Layer 1: CDN (Cloudflare/Fastly)
  - Product images, static assets
  - TTL: 24 hours
  - Purge on product image update

Layer 2: Redis Cluster (distributed cache)
  - Product detail pages
  - Key: "product:{id}"
  - TTL: 15 minutes
  - Pattern: Cache-aside
  - Eviction: LRU
  - Size: 10M products × 2KB = 20GB → 3-node cluster (64GB RAM)

Layer 3: Application-level cache (in-memory)
  - Category tree, configuration
  - TTL: 5 minutes
  - Size: <1MB per instance

Cache Invalidation:
  - Product update → Kafka event → Cache invalidation service
  - Service: Redis DEL product:{id}
  - CDN purge via API

Cache Stampede Prevention:
  - Redis lock: SETNX lock:product:{id} 1 EX 10
  - Only 1 request fetches from DB
  - Others wait 100ms, retry cache

Expected hit ratio:
  CDN: 95% (images rarely change)
  Redis: 90% (15min TTL, product updates rare)
  Combined: ~99%+ of requests never hit DB
```

### Redis Cache Example
```
SET product:12345 '{"id":12345,"name":"Phone","price":999}' EX 900
GET product:12345
DEL product:12345  // on update
```

## Praktik Tapşırıqlar
- Redis cache-aside implementasiya edin
- Cache hit/miss metrikasını ölçün
- Cache stampede simulyasiya edin (10K concurrent requests on cold cache)
- TTL variation-unun hit ratio-ya təsirini test edin
- Write-through vs cache-aside performance müqayisəsi

## Əlaqəli Mövzular
- [03-scalability-fundamentals.md](03-scalability-fundamentals.md) — Scalability patterns
- [06-database-selection.md](06-database-selection.md) — DB seçimi ilə cache əlaqəsi
- [09-rate-limiting.md](09-rate-limiting.md) — Rate limiting üçün Redis
- [12-cap-theorem-practice.md](12-cap-theorem-practice.md) — Consistency trade-offs
- [23-eventual-consistency.md](23-eventual-consistency.md) — Eventual consistency
