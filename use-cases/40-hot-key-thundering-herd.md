# Hot Key & Thundering Herd (Senior)

## Problem necə yaranır?

### Thundering Herd (Cache Stampede)

Populyar bir məhsulun cache TTL-i bitir. Həmin millisecond-da 500 concurrent request gəlir. Hamısı cache miss alır. Hamısı DB-yə sorğu göndərir. DB bu yükü daşıya bilmir → timeout → daha çox retry → DB çökür.

```
Cache TTL bitdi:
  500 request → cache miss → 500 parallel DB query
  DB overload → timeout → retry → 1000 sorğu → cascade failure
```

Bu xüsusilə Black Friday, yeni məhsul elanı, viral post kimi yüksək traffic anlarda kritikdir.

**Cache Stampede vs Cache Avalanche fərqi:**
- **Stampede:** Bir key-in TTL-i bitir, çox request eyni anda DB-yə gedir.
- **Avalanche:** Çox key eyni anda expire olur (məsələn, deploy zamanı cache flush) → DB-yə massive yük. Həll: TTL-ə jitter əlavə etmək — `ttl + random(0, 300)`.

### Hot Key

Redis single-threaded command processor istifadə edir. Redis Cluster-da hər key bir node-dadır. `product:1` key-i saniyədə 900,000 sorğu alsa həmin node bottleneck olur. Həmin node-dakı bütün digər key-lər də yavaşlayır.

---

## Həll 1: Mutex Lock (Stampede üçün)

Yalnız bir process DB-dən yükləyir, digərləri gözləyir:

*Bu kod cache stampede-ni önləmək üçün mutex lock istifadə edən `remember` metodunu göstərir:*

```php
class CacheService
{
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = Cache::get($key);
        if ($value !== null) return $value;

        // Atomic lock: yalnız bir process "rebuild" edəcək
        $lock = Cache::lock("lock:{$key}", 10);

        if ($lock->get()) {
            try {
                $value = $callback();     // DB sorğusu
                Cache::put($key, $value, $ttl);
                return $value;
            } finally {
                $lock->release();
            }
        }

        // Biz lock ala bilmədik — başqa process rebuild edir
        // 50ms gözlə, sonra cache-dən oxu
        usleep(50000);
        return Cache::get($key) ?? $callback(); // son fallback
    }
}
```

**Problem:** Lock-un özü bottleneck ola bilər — çox iş eyni key üçün lock gözləyir.

---

## Həll 2: Probabilistic Early Refresh (XFetch)

Cache expire olmadan əvvəl, TTL azaldıqca artan ehtimalla yeniləyir. Yük dağılır, stampede olmur:

*Bu kod cache expire olmadan əvvəl ehtimala əsasən erkən yeniləyən XFetch (probabilistic early refresh) alqoritmini göstərir:*

```php
class XFetchCache
{
    // TTL azaldıqca artan ehtimalla refresh — expire-dan əvvəl yenilənir
    public function get(string $key, int $ttl, callable $recompute, float $beta = 1.0): mixed
    {
        $data = Cache::get($key);

        if ($data === null) {
            $value = $recompute();
            Cache::put($key, ['value' => $value, 'expires_at' => time() + $ttl], $ttl);
            return $value;
        }

        $remaining = $data['expires_at'] - time();

        // Formul: log(rand) * beta * recompute_time > remaining
        // TTL az qaldıqda ehtimal artır — bəzi request-lər erkən refresh edir
        if (-log(random_int(1, 1000) / 1000) * $beta > $remaining) {
            $value = $recompute();
            Cache::put($key, ['value' => $value, 'expires_at' => time() + $ttl], $ttl);
            return $value;
        }

        return $data['value'];
    }
}
```

---

## Həll 3: L1/L2 Cache (Hot Key üçün)

PHP process-in öz yaddaşında ultra-qısa TTL-li cache saxla — Redis-ə sorğu sayını drastik azaldır:

*Bu kod Redis yükünü azaltmaq üçün PHP prosesinin yaddaşında ultra-qısa TTL-li L1 cache saxlayan iki səviyyəli cache sinifini göstərir:*

```php
class TwoLevelCache
{
    private array $localCache  = [];
    private array $localExpiry = [];

    // L1 (PHP memory, 5s) → L2 (Redis, 300s) → DB
    public function get(string $key, callable $fallback, int $redisTtl = 300, int $localTtl = 5): mixed
    {
        // L1 check — memory-dən, <1ms
        if (isset($this->localCache[$key]) && $this->localExpiry[$key] > microtime(true)) {
            return $this->localCache[$key];
        }

        // L2 check — Redis-dən
        $value = Cache::get($key);

        if ($value === null) {
            $value = $fallback(); // DB
            Cache::put($key, $value, $redisTtl);
        }

        // L1-ə yaz — 5s ərzindəki sorğular Redis-ə getmir
        $this->localCache[$key]  = $value;
        $this->localExpiry[$key] = microtime(true) + $localTtl;

        return $value;
    }
}
```

Saniyədə 10,000 sorğu, L1 TTL 5s: Redis yalnız ilk sorğunu alır, qalan 9,999-u L1-dən qarşılanır.

---

## Həll 4: Key Sharding (Hot Key üçün)

Bir key-i N shard-a böl — yük N node-a dağılır:

*Bu kod hot key yükünü N Redis node-una paylamaq üçün açar parçalama (key sharding) mexanizmini göstərir:*

```php
class HotKeySharding
{
    private int $shards = 10;

    // "product:1" əvəzinə "product:1:shard:3" kimi random shard
    public function get(string $baseKey, callable $fallback): mixed
    {
        $shardKey = "{$baseKey}:shard:" . random_int(0, $this->shards - 1);
        return Cache::remember($shardKey, 60, $fallback);
    }

    // Hamısını invalidate et
    public function invalidate(string $baseKey): void
    {
        for ($i = 0; $i < $this->shards; $i++) {
            Cache::forget("{$baseKey}:shard:{$i}");
        }
    }
}
```

**Redis Cluster hash tag:** `{product:1}:shard:3` formatında yazılsa hash tag `{product:1}` əsasında slot hesablanır. Sharded key-lər fərqli slotlara yönləndirilsini istəyirsənsə hash tag-siz yazılmalıdır.

---

## Həll 5: Stale-while-revalidate

Köhnə data-nı dərhal qaytar, arxa planda refresh et. User gecikmə hiss etmir:

*Bu kod köhnə datanı dərhal qaytarıb arxa planda yeniləyən stale-while-revalidate strategiyasını göstərir:*

```php
public function getWithGracePeriod(string $key, int $ttl, int $grace, callable $recompute): mixed
{
    $data = Cache::get($key);

    if ($data === null) {
        $value = $recompute();
        Cache::put($key, ['value' => $value, 'fresh_until' => time() + $ttl], $ttl + $grace);
        return $value;
    }

    // Data köhnədir amma grace period-dadır — köhnəni qaytar, arxa planda refresh et
    if (time() > $data['fresh_until']) {
        dispatch(fn() => Cache::put($key, ['value' => $recompute(), 'fresh_until' => time() + $ttl], $ttl + $grace));
    }

    return $data['value'];
}
```

---

## Cache Avalanche — TTL Jitter

Deploy zamanı cache flush edilsə ya da çox key eyni TTL ilə yazılsa hamısı eyni vaxtda expire olur:

*Bu kod cache avalanche-ı önləmək üçün TTL-ə random jitter əlavə edilməsini göstərir:*

```php
// Bütün məhsullar 300s TTL ilə yazılır → eyni anda expire → DB spike
Cache::put("product:{$id}", $data, 300);

// Jitter əlavə et: 300 ± 60s arası random — expire zamanı dağılır
Cache::put("product:{$id}", $data, 300 + random_int(-60, 60));
```

---

## Anti-patterns

- **Tüm stampede həlllərini eyni anda tətbiq etmək:** Mutex + XFetch + L1 — over-engineering. Problem analiz et, uyğun həll seç.
- **L1 cache-i çox uzun TTL ilə saxlamaq:** Stale data user görür. 5-10s optimal.
- **Sharding-da invalidation-ı unutmaq:** Data yeniləndikdə bütün shardları sil — biri stale qalsa inconsistency.
- **Lock timeout-u çox uzun qoymaq:** 30s lock → bütün digər requestlər 30s gözləyir.

---

## İntervyu Sualları

**1. Cache stampede nədir, necə önlənir?**
Cache TTL bitdikdə çox request eyni anda DB-yə gedir. Mutex lock: yalnız biri rebuild edir. XFetch: TTL bitməzdən əvvəl probabilistic refresh. Stale-while-revalidate: köhnəni qaytar, refresh arxa planda.

**2. Hot key Redis-də niyə problem yaradır?**
Redis Cluster-da hər key bir node-a məxsusdur. 900K req/s həmin node-un limitini keçir — bottleneck. Digər key-lər həmin node-dadırsa onlar da yavaşlayır. Həll: L1 in-process cache (Redis yükünü 100x azaldır), key sharding (yük N node-a dağılır).

**3. L1/L2 cache tradeoff nədir?**
L1: ultra-sürətli, lakin hər PHP worker-ın öz L1-i var (FPM 20 worker → 20 ayrı cache). Stale window: L1 TTL bitənə qədər köhnə data. L2 (Redis): paylaşılan, invalidation mümkün, lakin daha yavaş. Hot key-lər üçün L1 əvəzolunmazdır.

**4. Cache stampede vs cache avalanche fərqi nədir?**
Stampede: tək key-in TTL-i bitir, çox concurrent request DB-yə gedir. Avalanche: çox key eyni anda expire olur (deploy, flush) → massive DB yük. Stampede: mutex/XFetch. Avalanche: TTL-ə random jitter əlavə etmək.

**5. Redis `--hotkeys` flag nədir?**
`redis-cli --hotkeys` — Redis 4.0+ ilə gələn, ən çox oxunan key-ləri tapır. LFU eviction policy aktiv olmalıdır (`maxmemory-policy allkeys-lfu`). Production-da hot key identifikasiyası üçün istifadə edilir.

---

## Anti-patternlər

**1. Cache miss zamanı bütün request-lərə DB-yə getmək icazəsi vermək**
Cache TTL bitdikdə gələn hər request-in birbaşa DB sorğusu etməsinə icazə vermək — stampede: 10,000 eyni anda DB-yə çatır, DB çökür. Mutex lock və ya probabilistic early expiry (XFetch) ilə yalnız bir request rebuild etməli, qalanları gözləməlidir.

**2. Bütün hot key-lər üçün eyni sharding sayı seçmək**
Hər populyar key üçün sabit sayda shard (`hot_product:1`, `hot_product:2`) yaratmaq — bəzi key-lər daha çox trafik alır, digərləri az. Shard sayı trafikə görə dinamik seçilməli, ya da consistent hashing ilə key-ə özəl shard sayı müəyyənləşdirilməlidir.

**3. L1 cache-dən sonra stale data inconsistency-ni ignore etmək**
In-process L1 cache TTL-ni 60s kimi uzun saxlamaq — data yeniləndikdə 60s ərzində bəzi PHP worker-lar köhnə, bəziləri yeni dəyər görür. L1 TTL 5-10s olmalı, kritik data L1-dən yox, yalnız L2 Redis-dən oxunmalıdır.

**4. Thundering herd üçün yalnız rate limiting tətbiq etmək**
Stampede problemini backend-ə rate limit qoymaqla həll etməyə çalışmaq — rate limit həddini keçən request-lər 429 alır, user experience pisləşir. Düzgün həll: stampede-nin özünü önləmək (mutex/XFetch), rate limiting yalnız əlavə qoruma üçün istifadə edilməlidir.

**5. Redis Cluster-da hot key-i keyslot aware yazmamaq**
`{product}:123` kimi hash tag olmadan hot key yazmaq — Redis Cluster bütün hot key-ləri eyni slot-a, eyni node-a yönləndirir. Sharded key-lər `{product:123}:shard:1` formatında müxtəlif slotlara paylanmalıdır.

**6. Cache warming-i deployment-dən sonraya buraxmaq**
Yeni deploy sonrası cache-in öz-özünə dolmasını gözləmək — cold start zamanı bütün request-lər DB-yə çatır, spike yaranır. Deploy prosesinin bir hissəsi olaraq avtomatik cache warming skripti işlədilməli, trafik gəlməzdən əvvəl ən çox oxunan key-lər Redis-ə yüklənməlidir.

**7. Avalanche üçün jitter olmadan eyni TTL istifadə etmək**
Çox key-i eyni sabit TTL ilə yazıb cache flush-dan sonra synchronized expire-dan qorunmamaq — hamısı eyni anda expire olur, DB-yə massive spike. TTL dəyərinə ±10-20% random jitter əlavə etmək expire-ları zamana payır, avalanche önlənir.
