# Redis & Caching Strategies

## Redis nedir?

In-memory key-value data store. Database, cache, message broker, ve queue kimi istifade olunur.

**Suretle:** Butun data RAM-dadir. Disk I/O yoxdur. Tek-threaded (context switch yoxdur).

---

## Redis Data Tipleri

### String

```redis
SET user:1:name "John"
GET user:1:name          -- "John"
SET counter 0
INCR counter             -- 1 (atomic!)
INCRBY counter 5         -- 6
SETEX session:abc 3600 "data"  -- 1 saat TTL ile
SETNX lock:order:1 "locked"   -- Yalniz movcud deyilse set et (distributed lock)
```

### Hash

```redis
HSET user:1 name "John" email "john@mail.com" age 30
HGET user:1 name         -- "John"
HGETALL user:1           -- name, John, email, john@mail.com, age, 30
HINCRBY user:1 age 1     -- 31
```

### List

```redis
LPUSH queue:emails "email1" "email2"  -- Sola elave et
RPOP queue:emails                      -- Sagdan cixart (queue kimi)
LRANGE queue:emails 0 -1              -- Hamisi
BRPOP queue:emails 30                 -- Blocking pop (30 saniye gozle)
```

### Set

```redis
SADD tags:post:1 "php" "laravel" "redis"
SMEMBERS tags:post:1     -- php, laravel, redis
SISMEMBER tags:post:1 "php"  -- 1 (true)
SINTER tags:post:1 tags:post:2  -- Ortaq taglar (intersection)
```

### Sorted Set

```redis
ZADD leaderboard 100 "user:1" 250 "user:2" 50 "user:3"
ZRANGE leaderboard 0 -1 WITHSCORES  -- Score-a gore siralama
ZREVRANGE leaderboard 0 2           -- Top 3 (yuxaridan)
ZRANK leaderboard "user:2"          -- 2 (0-based rank)
ZINCRBY leaderboard 10 "user:1"     -- Score-u 10 artir
```

---

## Caching Strategies

### 1. Cache-Aside (Lazy Loading)

En genis yayilmis strategiya. Application cache-i idare edir.

```php
function getUser(int $id): User
{
    $cacheKey = "user:{$id}";
    
    // 1. Cache-den oxu
    $cached = Cache::get($cacheKey);
    if ($cached !== null) {
        return $cached; // Cache HIT
    }
    
    // 2. Cache MISS - DB-den oxu
    $user = User::findOrFail($id);
    
    // 3. Cache-e yaz
    Cache::put($cacheKey, $user, now()->addHour());
    
    return $user;
}

// Laravel-de qisa yol:
$user = Cache::remember("user:{$id}", 3600, function () use ($id) {
    return User::findOrFail($id);
});
```

**Ustunlukleri:** Sade, yalniz lazimi data cache-lenir
**Cetinliyi:** Ilk request yavas (cache miss), stale data mumkundur

### 2. Write-Through

Her write zamani hem DB-ye hem cache-e yazilir.

```php
function updateUser(int $id, array $data): User
{
    // DB-ye yaz
    $user = User::findOrFail($id);
    $user->update($data);
    
    // Cache-e yaz
    Cache::put("user:{$id}", $user, now()->addHour());
    
    return $user;
}
```

**Ustunlukleri:** Cache hemishe fresh
**Cetinliyi:** Her write 2 yere yazilir (yavas), istifade olunmayan data da cache-lenir

### 3. Write-Behind (Write-Back)

Evvelce cache-e yazilir, sonra async DB-ye yazilir.

```php
function updateUser(int $id, array $data): void
{
    // Yalniz cache-e yaz (suretli!)
    Cache::put("user:{$id}", $data, now()->addHour());
    
    // Async DB-ye yaz (queue ile)
    dispatch(new SyncUserToDatabase($id, $data));
}
```

**Ustunlukleri:** Suretli write, DB yuku azalir
**Cetinliyi:** Data itkisi riski (cache crash olsa), complexity

### 4. Read-Through

Cache-Aside kimidir, amma cache library ozu DB-den oxuyur.

### 5. Cache Invalidation Strategiyalari

```php
// 1. Time-based (TTL)
Cache::put('key', $value, now()->addMinutes(30));

// 2. Event-based (data deyisdikde cache sil)
class UserObserver
{
    public function updated(User $user)
    {
        Cache::forget("user:{$user->id}");
        Cache::forget("user_list"); // Related cache-leri de sil
    }
}

// 3. Tag-based (Laravel)
Cache::tags(['users'])->put("user:{$id}", $user, 3600);
Cache::tags(['users'])->put("user_list", $users, 3600);
// Butun user cache-lerini sil:
Cache::tags(['users'])->flush();
```

---

## Cache Problemleri

### Cache Stampede (Thundering Herd)

Populyar cache expire olur, 1000 request eyni anda DB-ye gedir.

```php
// Problem:
Cache::remember('popular_products', 60, function () {
    return Product::popular()->get(); // 1000 request eyni anda bunu cagirir!
});

// Hell 1: Atomic lock
$products = Cache::lock('lock:popular_products', 10)->block(5, function () {
    return Cache::remember('popular_products', 60, function () {
        return Product::popular()->get();
    });
});

// Hell 2: Early refresh (TTL bitmeyi gozleme)
// 60 saniye TTL, amma 50 saniyede artiq yenile
$cached = Cache::get('popular_products');
$ttl = Cache::getStore()->ttl('popular_products'); // Redis TTL command
if ($ttl < 10) {
    dispatch(new RefreshPopularProductsCache());
}
```

### Cache Penetration

Movcud olmayan data ucun daimi DB query-si.

```php
// Problem: user:99999 yoxdur, her defe DB-ye gedir
$user = Cache::remember("user:99999", 3600, function () {
    return User::find(99999); // null qaytarir, cache-lenmez!
});

// Hell: Null deyeri de cache-le
$user = Cache::remember("user:{$id}", 3600, function () use ($id) {
    return User::find($id) ?? 'NOT_FOUND'; // null yerine marker
});
if ($user === 'NOT_FOUND') $user = null;

// Hell 2: Bloom Filter (boyuk scale ucun)
// Redis Bloom Filter ile evvelce yoxla ki, data umumen movcuddurmu
```

### Cache Avalanche

Coxlu cache eyni anda expire olur.

```php
// Problem: Butun product cache-leri eyni anda expire olur
foreach ($products as $product) {
    Cache::put("product:{$product->id}", $product, 3600); // Hamisi 1 saatdan sonra
}

// Hell: Random TTL (jitter)
foreach ($products as $product) {
    $ttl = 3600 + random_int(-300, 300); // 55-65 deqiqe arasi
    Cache::put("product:{$product->id}", $product, $ttl);
}
```

---

## Redis PHP Misallari

### Laravel ile Redis

```php
// config/database.php
'redis' => [
    'client' => 'phpredis', // ve ya 'predis'
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
    ],
    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_CACHE_DB', 1),
    ],
],
```

### Rate Limiting

```php
use Illuminate\Support\Facades\Redis;

// 1 deqiqede max 60 request
$key = "rate_limit:{$userId}";
$current = Redis::incr($key);
if ($current === 1) {
    Redis::expire($key, 60);
}
if ($current > 60) {
    abort(429, 'Too many requests');
}

// Laravel built-in
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

### Distributed Lock

```php
$lock = Cache::lock('process-payment-123', 10); // 10 saniye TTL

if ($lock->get()) {
    try {
        processPayment(123);
    } finally {
        $lock->release();
    }
} else {
    // Lock alinmadi, baskasi isleyir
}

// Blocking lock (gozle)
$lock->block(5, function () {
    processPayment(123);
}); // Max 5 saniye gozle
```

### Pub/Sub

```php
// Publisher
Redis::publish('order-channel', json_encode([
    'order_id' => 123,
    'status' => 'paid',
]));

// Subscriber (ayri process-de)
Redis::subscribe(['order-channel'], function ($message) {
    $data = json_decode($message, true);
    // Process message
});
```

### Redis Pipeline (Batch)

```php
// YAVAS: 100 ayri command = 100 network round-trip
foreach ($userIds as $id) {
    Redis::set("user:{$id}:online", true);
}

// SURETLI: Pipeline = 1 network round-trip
Redis::pipeline(function ($pipe) use ($userIds) {
    foreach ($userIds as $id) {
        $pipe->set("user:{$id}:online", true);
        $pipe->expire("user:{$id}:online", 300);
    }
});
```

### Redis Transaction (MULTI/EXEC)

```php
Redis::transaction(function ($redis) {
    $redis->incr('counter');
    $redis->set('last_update', now()->toISOString());
});
// MULTI -> INCR -> SET -> EXEC (atomic!)
```

---

## Redis Persistence

### RDB (Snapshot)
Mueyyen araliqlarla tam snapshot disk-e yazilir.

```redis
# redis.conf
save 900 1      # 900 saniyede 1+ deyisiklik olsa snapshot et
save 300 10     # 300 saniyede 10+ deyisiklik olsa
save 60 10000   # 60 saniyede 10000+ deyisiklik olsa
```

### AOF (Append Only File)
Her write emeliyyati log-a yazilir.

```redis
appendonly yes
appendfsync everysec  # Her saniye sync (data itkisi max 1 saniye)
```

### Tovsiye: RDB + AOF birlikde

---

## Interview suallari

**Q: Cache invalidation niye cetindir?**
A: "There are only two hard things in CS: cache invalidation and naming things." Nece bilersen ki cache-deki data kohnelib? TTL ile hamisini track etmek cetindir. Event-based invalidation-da related cache-leri unutmaq olar. Tag-based daha yaxsidir amma overhead var.

**Q: Redis niye tek-threaded-dir ve yene de suretlidir?**
A: 1) Butun data RAM-dadir (disk I/O yoxdur). 2) I/O multiplexing (epoll) istifade edir. 3) Lock yoxdur (tek thread = race condition yoxdur). 4) Sade data strukturlari optimize edilib. Redis 6.0+ I/O threading elave etdi, amma command execution yene tek thread-dedir.

**Q: Redis vs Memcached?**
A: Redis: data tipleri (hash, list, set, sorted set), persistence, replication, Pub/Sub, Lua scripting. Memcached: yalniz string, multi-threaded (CPU-dan daha yaxsi istifade), sade caching ucun. Cogu hallda Redis daha yaxsi secimdir.
