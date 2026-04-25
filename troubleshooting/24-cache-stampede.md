# Cache Stampede (Senior)

## Problem (nə görürsən)
Populyar cache dəyəri vaxtı keçir. Növbəti request miss görür və database-ə gedir. Cache-i doldurmazdan əvvəl, 100 digər request də miss verir. İndi 100 eyni DB query eyni vaxtda işləyir. Database əzilir. Response time-lar yuxarı qalxır. Daha çox istifadəçi retry edir, bu da vəziyyəti pisləşdirir. Bu cache stampede-dir (həmçinin "thundering herd" və ya "dogpile" adlanır).

Simptomlar:
- Bir neçə dəqiqədən bir qəfil DB CPU spike (TTL ilə uyğundur)
- Cache TTL-ə uyğun p99 latency saw-tooth pattern-i
- Redis normal yük göstərir, MySQL/Postgres spike-lər göstərir
- Dashboard: cache hit rate qısa müddətə 0%-ə düşür, sonra bərpa olur
- Korrelyasiya: "deploy-dan sonra dəqiq 60s-də başlayır" (çünki deploy zamanı isindirilmiş hər şey birlikdə bitir)

## Sürətli triage (ilk 5 dəqiqə)

### Sadəcə cache invalidation deyil, stampede olduğunu təsdiqlə

Zaman ərzində cache hit rate-i yoxla:
```bash
# Redis INFO stats
redis-cli INFO stats | grep keyspace

# Or from app metrics (if exposed)
cache_hits_total / (cache_hits_total + cache_misses_total)
```

Stampede imzası: hit rate qısa müddətə 0%-ə düşür sonra tez bərpa olur və bu DB yük spike-ləri ilə uyğun gəlir.

### Hansı açar

İnstrumentasiya olunubsa:
```php
// middleware or service
Cache::macro('rememberWithMetrics', function (string $key, $ttl, Closure $callback) {
    $hit = Cache::has($key);
    Metrics::counter('cache_'.($hit ? 'hit' : 'miss'))->inc(['key' => $key]);
    return Cache::remember($key, $ttl, $callback);
});
```

Və ya miss-də logla:
```php
if (!Cache::has($key)) {
    Log::info('Cache miss', ['key' => $key]);
}
```

Hansı açarların ən çox miss etdiyini görmək üçün log-ları aqreqasiya et.

## Diaqnoz

### Klassik yarış

```php
// 100 concurrent requests
$value = Cache::remember('expensive_thing', 60, function () {
    // Expensive DB query — all 100 requests hit DB simultaneously
    return DB::select('SELECT ... complex ...');
});
```

`Cache::remember` atomik DEYİL. Bütün miss-lər yarışır.

### Variantlar

**Tip 1: Expiration stampede** — tək açar vaxtı keçir, çox request eyni anda miss verir.

**Tip 2: Deployment stampede** — deploy-dan sonra OPcache və ya distributed cache soyuqdur, hər şey eyni anda miss verir.

**Tip 3: Sliding window stampede** — hər girişdə TTL yenilənir, amma bütün giriş eyni vaxtda baş verir (cron + cache = fəlakət).

## Fix (qanaxmanı dayandır)

### Strategiya 1: Lock / mutex (ən təsirli)

Yalnız bir request regenerate edir; digərləri gözləyir.

```php
use Illuminate\Support\Facades\Cache;

public function getExpensiveThing(): mixed
{
    $cached = Cache::get('expensive_thing');
    if ($cached !== null) {
        return $cached;
    }
    
    // Try to acquire lock
    $lock = Cache::lock('expensive_thing:lock', 10); // 10s lock
    
    if ($lock->get()) {
        try {
            // We won the race — regenerate
            $value = $this->computeExpensive();
            Cache::put('expensive_thing', $value, 60);
            return $value;
        } finally {
            $lock->release();
        }
    }
    
    // Someone else is regenerating — wait briefly and retry
    sleep(0.1);
    return Cache::get('expensive_thing') ?? $this->fallbackValue();
}
```

Və ya Laravel-in `Cache::lock()->block()` ilə qısaca:
```php
Cache::lock('regen', 10)->block(5, function () use ($key, $ttl, $callback) {
    return Cache::remember($key, $ttl, $callback);
});
```

### Strategiya 2: Ehtimal əsaslı erkən bitmə (XFetch alqoritmi)

Həqiqi bitmədən ƏVVƏL ehtimal əsaslı regenerate:

```php
// Store value + computed-at timestamp + expected compute time
Cache::put($key, [
    'value' => $value,
    'computed_at' => microtime(true),
    'delta' => $computeTimeSeconds, // how long compute took
    'expiry' => microtime(true) + $ttl,
], $ttl + 10);

// On read
$entry = Cache::get($key);
if ($entry === null) {
    return regenerate(); // miss, regenerate with lock
}

// Probabilistically regenerate before expiry
$now = microtime(true);
$xfetch = $entry['delta'] * 0.5 * log(1 - mt_rand() / mt_getrandmax());
if ($now - $xfetch >= $entry['expiry']) {
    return regenerate(); // early, with lock
}

return $entry['value'];
```

Trick: bitməyə yaxınlaşdıqca erkən regen ehtimalı 1-ə yaxınlaşır. Az request regenerate edir; çoxu təzə dəyərdən istifadə edir.

### Strategiya 3: Stale-while-revalidate

Background-da regenerate edərkən köhnə məzmunu ver:

```php
$cached = Cache::get($key);
$staleCached = Cache::get($key.':stale');

if ($cached) {
    return $cached; // fresh, return
}

if ($staleCached) {
    // Fresh expired but stale exists — dispatch job, return stale
    dispatch(new RegenerateCacheJob($key));
    return $staleCached;
}

// No cache at all — must compute synchronously
$value = compute();
Cache::put($key, $value, 60);
Cache::put($key.':stale', $value, 3600); // stale cache 1h
return $value;
```

İstifadəçilər bitmədən sonra ən çoxu 1 saniyə köhnə data görür. Job background-da təzə cache yaradır.

### Strategiya 4: Warm-up job-ları

İsti açarları bitmədən əvvəl yeniləmək üçün cron planla:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->job(new WarmExpensiveCache())->everyMinute();
}

class WarmExpensiveCache implements ShouldQueue
{
    public function handle(): void
    {
        // Recompute and store. TTL 65s so this cron refreshes before expiry.
        Cache::put('expensive_thing', $this->compute(), 65);
    }
}
```

### Strategiya 5: Jittered TTL-lər

Bütün açarların 60s-də bitməsi əvəzinə təsadüfiləşdir:
```php
$ttl = 60 + random_int(0, 30); // 60-90s
Cache::put($key, $value, $ttl);
```

Xüsusilə çox açarlar eyni anda doldurulduqda faydalıdır (deploy-dan sonra).

## Əsas səbəbin analizi

Incident sonrası:
- Hansı açar(lar) stampede-ə səbəb oldu?
- Neçə concurrent miss DB-yə çatdı?
- Konkret TTL sərhədi idi, yoxsa təsadüfi?
- Əsas DB query özü optimize oluna bilərdi?

## Qarşısının alınması

- İstənilən bahalı cache regeneration üçün lock istifadə et
- Əlaqəli açarlar arasında TTL-ləri jitter et
- Monitor et: cache hit rate dashboard, alert kimi miss-rate spike-ləri
- Yeni cache pattern-ləri concurrent request-lərlə load test et
- İstənilən yüksək trafikli cache-də stampede riskini sənədləşdir

## PHP/Laravel üçün qeydlər

### Laravel Cache::lock

```php
$lock = Cache::lock('mylock', 10);

// Try without waiting
if ($lock->get()) {
    try { /* work */ } finally { $lock->release(); }
}

// Block up to 5s waiting for lock
try {
    $lock->block(5);
    /* work */
} catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
    // Failed to acquire
} finally {
    optional($lock)->release();
}

// Callback form (auto-release)
Cache::lock('mylock', 10)->block(5, function () {
    // work
});
```

Lock-lar lock-qabil driver tələb edir: Redis, Memcached, DynamoDB, database.

### Redis-spesifik

Dəqiq nəzarət lazımdırsa `SET ... NX EX` semantikasını birbaşa istifadə et:
```php
$acquired = Redis::set("lock:$key", 'held', 'EX', 10, 'NX');
if ($acquired) {
    try { /* work */ } finally { Redis::del("lock:$key"); }
}
```

### İsti açarlar üçün `Cache::remember`-dən qaç

`Cache::remember` built-in lock-a malik deyil. İsti açarlar üçün açıq lock-a sar. Helper düşün:

```php
Cache::macro('rememberLocked', function ($key, $ttl, Closure $cb, $lockTtl = 10, $waitFor = 5) {
    $cached = Cache::get($key);
    if ($cached !== null) return $cached;
    
    return Cache::lock($key.':lock', $lockTtl)->block($waitFor, function () use ($key, $ttl, $cb) {
        // Double-check inside the lock (another worker may have populated)
        return Cache::remember($key, $ttl, $cb);
    });
});
```

## Yadda saxlanacaq komandalar

```bash
# Redis cache hit rate
redis-cli INFO stats | grep -E "keyspace_hits|keyspace_misses"

# Recent slow DB queries (if stampede is hitting DB)
mysql -e "SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10"

# Find hot keys in Redis
redis-cli --hotkeys

# Monitor Redis commands live (development only — heavy)
redis-cli MONITOR | head -100
```

```php
// Quick metrics dump
Cache::get('expensive_thing:metrics');
DB::enableQueryLog();
```

## Interview sualı

"Cache stampede haqqında və necə qarşısını almaq olar, danış."

Güclü cavab:
- "Stampede odur ki, cache dəyəri bitir və çox concurrent request eyni anda miss verir və DB-yə çatır."
- "Klassik səbəb: `Cache::remember` atomik deyil. Əgər 100 request eyni anda miss verirsə, 100 regeneration paralel işləyir."
- "Fix variantları prioritetə görə: lock əsaslı regeneration (bir worker regenerate edir, digərləri gözləyir), stale-while-revalidate (async refresh zamanı köhnə ver), ehtimal əsaslı erkən bitmə və planlı warm-up job-ları."
- "İsti açarlar üçün default pattern kimi `Cache::lock()->block()` istifadə edirəm."
- "Qabaqlayıcı: jittered TTL-lər, cache miss rate-i monitor et, concurrency ilə load test et."

Bonus: "Bir şirkətdə homepage cache-i düz 5 dəqiqədən bir bitirdi. DB CPU 10 saniyə 100%-ə spike edirdi, sonra düşürdü. Lock əsaslı regeneration əlavə etdik — problem yox oldu. Sonra stale-while-revalidate əlavə etdik ki, lock failure-ları belə DB-ni heç vaxt vurmur."
