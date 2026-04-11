# Redis — Dərin Analiz

## Mündəricat
1. [Data Strukturları](#data-strukturları)
2. [Persistence: RDB vs AOF](#persistence-rdb-vs-aof)
3. [Expiry və Eviction](#expiry-və-eviction)
4. [Redis Sentinel](#redis-sentinel)
5. [Redis Cluster](#redis-cluster)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [Praktik Patterns](#praktik-patterns)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Data Strukturları

### String
```
// Bu kod Redis String data strukturunun əsas əmrlərini göstərir
SET key value [EX seconds] [NX|XX]
GET key
INCR counter     → atomic increment
GETSET key value → get old, set new

Nümunə: Cache, counter, session, rate limit
```

### Hash
```
// Bu kod Redis Hash data strukturunun field əsaslı saxlama əmrlərini göstərir
HSET user:1 name "Ali" email "ali@example.com" age 30
HGET user:1 name        → "Ali"
HGETALL user:1          → {name: Ali, email: ..., age: 30}
HINCRBY user:1 age 1    → 31

Nümunə: Object cache (User, Product), session fields
```

### List
```
// Bu kod Redis List data strukturunun queue pattern üçün LPUSH/RPOP əmrlərini göstərir
LPUSH queue task1 task2  → [task2, task1]
RPUSH queue task3        → [task2, task1, task3]
LPOP queue               → task2
BRPOP queue 30           → blocking pop (30s timeout) ← Queue pattern!
LRANGE queue 0 -1        → bütün elementlər

Nümunə: Task queue, recent activity, message history
```

### Set
```
// Bu kod Redis Set data strukturunun unikal element idarəsi əmrlərini göstərir
SADD tags php laravel redis
SISMEMBER tags php      → 1 (var)
SMEMBERS tags           → {php, laravel, redis}
SUNION tags1 tags2      → birlik
SINTER tags1 tags2      → kəsişmə
SDIFF tags1 tags2       → fərq

Nümunə: Unique visitors, tag management, friendship graph
```

### Sorted Set (ZSet) ⭐
```
// Bu kod Redis Sorted Set-in score əsaslı sıralama əmrlərini leaderboard nümunəsi ilə göstərir
ZADD leaderboard 1500 "Ali"
ZADD leaderboard 2300 "Veli"
ZADD leaderboard 1800 "Əli"

ZRANGE leaderboard 0 -1 WITHSCORES  → score-a görə sıralı
ZRANK leaderboard "Veli"            → 0 (birinci)
ZREVRANK leaderboard "Ali"          → 2 (sona ən yaxın)
ZINCRBY leaderboard 100 "Ali"       → 1600

Nümunə: Leaderboard, rate limiting (sliding window), priority queue
```

### Stream ⭐ (Redis 5.0+)
```
// Bu kod Redis Stream data strukturunun consumer group ilə event log əmrlərini göstərir
XADD events * user_id 1 action "order.placed" amount 150
XREAD COUNT 10 STREAMS events 0  → oxu
XREADGROUP GROUP consumers c1 COUNT 10 STREAMS events >  → consumer group
XACK events consumers msg_id  → acknowledge

Nümunə: Event log, audit trail, message bus, RabbitMQ alternativi
```

### Pub/Sub
```
// Bu kod Redis Pub/Sub mexanizminin subscribe/publish əmrlərini göstərir
SUBSCRIBE channel1 channel2   → subscribe
PUBLISH channel1 "message"    → publish

Nümunə: Real-time notifications, cache invalidation broadcast
```

---

## Persistence: RDB vs AOF

```
// Bu kod RDB snapshot və AOF append-only file persistence seçeneklərini müqayisə edir
RDB (Redis Database Backup):
  Snapshot — müəyyən intervalda bütün data-nı disk-ə yaz
  
  redis.conf:
    save 900 1      # 900s-də ən azı 1 dəyişiklik
    save 300 10     # 300s-də ən azı 10 dəyişiklik
    save 60 10000   # 60s-də ən azı 10000 dəyişiklik
  
  ✅ Compact, sürətli restore
  ❌ Son snapshot-dan bəri olan data itirilə bilər

AOF (Append Only File):
  Hər write əməliyyatını log faylına yaz
  
  redis.conf:
    appendonly yes
    appendfsync always    # Hər yazıda — ən safe, ən yavaş
    appendfsync everysec  # Hər saniyə — balans ✅
    appendfsync no        # OS-ə burax — ən sürətli, ən risk
  
  ✅ Az data itkisi (max 1 saniyə)
  ❌ Böyük fayl, yavaş restart

RDB + AOF:
  redis.conf:
    appendonly yes
    # RDB snapshots da aktiv
  → Best of both worlds (restart üçün RDB, AOF data safety)
```

---

## Expiry və Eviction

```
// Bu kod TTL əmrlərini və eviction siyasətlərinin konfiqurasiyasını göstərir
TTL:
  SET key value EX 3600      # 1 saat
  EXPIRE key 3600            # Mövcud key-ə TTL
  TTL key                    # Qalan vaxt
  PERSIST key                # TTL-i sil
  PEXPIRE key 1000           # millisaniyə

Eviction Policy (maxmemory-policy):
  noeviction     → Dolu olduqda error (default, cache olmayan hallar)
  allkeys-lru    → Ən az istifadə edilən key sil (ümumi cache)
  volatile-lru   → TTL-li key-lərdən LRU sil
  allkeys-lfu    → Ən az tez-tez istifadə edilən sil
  volatile-ttl   → TTL-i ən qısa olanı sil
  allkeys-random → Təsadüfi sil
  
  redis.conf:
    maxmemory 2gb
    maxmemory-policy allkeys-lru  # Cache üçün tövsiyə
```

---

## Redis Sentinel

```
// Bu kod Redis Sentinel-in master/replica monitoring və automatic failover arxitekturasını göstərir
High Availability — automatic failover:

┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  Sentinel 1 │    │  Sentinel 2 │    │  Sentinel 3 │
└──────┬──────┘    └──────┬──────┘    └──────┬──────┘
       │                  │                  │
       └──────────────────┼──────────────────┘
                          │ monitor
               ┌──────────┴──────────┐
               │                     │
        ┌──────▼──────┐    ┌──────────▼─────┐
        │   Master    │───►│    Replica     │
        └─────────────┘    └────────────────┘

Sentinel funksiyaları:
  Monitoring: master/replica sağlamlığını yoxla
  Notification: problem olduqda alert
  Automatic failover: master çöksə replica-nı master et
  Configuration provider: client-lərə master adresini ver

Quorum: N/2+1 sentinel razılaşmalıdır (split-brain önlə)
3 sentinel → 2 quorum
```

*3 sentinel → 2 quorum üçün kod nümunəsi:*
```php
// Bu kod Laravel-də Redis Sentinel konfiqurasiyasını predis client ilə göstərir
// Laravel .env
REDIS_HOST=sentinel-1
REDIS_PORT=26379
REDIS_SENTINEL=true
REDIS_SENTINEL_SERVICE=mymaster

// config/database.php
'redis' => [
    'client' => 'predis',
    'default' => [
        'tcp://sentinel-1:26379',
        'tcp://sentinel-2:26379',
        'tcp://sentinel-3:26379',
    ],
    'options' => [
        'replication' => 'sentinel',
        'service'     => 'mymaster',
    ],
],
```

---

## Redis Cluster

```
// Bu kod Redis Cluster-ın 16384 hash slot əsaslı horizontal sharding mexanizmini izah edir
Horizontal sharding — data bir neçə node-a bölünür:

16384 hash slot → nodes arasında bölünür

Node 1: slots 0-5460
Node 2: slots 5461-10922
Node 3: slots 10923-16383

Hər node-un replica-sı var:
  Node 1 (master) ↔ Node 1R (replica)
  Node 2 (master) ↔ Node 2R (replica)
  Node 3 (master) ↔ Node 3R (replica)

Key routing:
  CRC16(key) % 16384 → slot → node

Hash tags:
  {user}.session  → "user" hash edilir
  {user}.cart     → eyni node-da!
  (Multi-key ops eyni node-da olmalıdır)

Minimum: 6 node (3 master + 3 replica)
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod Laravel-də Redis əmrləri, pipeline, transaction və Lua script istifadəsini göstərir
// Laravel Redis facades
use Illuminate\Support\Facades\Redis;

// Basic operations
Redis::set('key', 'value', 'EX', 3600);
Redis::get('key');
Redis::del('key');

// Hash
Redis::hset('user:1', 'name', 'Ali');
Redis::hgetall('user:1');

// Sorted Set — Leaderboard
Redis::zadd('leaderboard', 1500, 'user:1');
Redis::zrevrange('leaderboard', 0, 9, 'WITHSCORES');  // Top 10

// Pipeline — batch commands (round-trip azalt)
$results = Redis::pipeline(function ($pipe) {
    $pipe->incr('counter');
    $pipe->expire('counter', 60);
    $pipe->get('another-key');
});

// Transaction (MULTI/EXEC)
Redis::transaction(function ($tx) {
    $tx->set('key1', 'value1');
    $tx->set('key2', 'value2');
    // Atomik — ikisi birlikdə execute olur
});

// Lua script (atomic complex operation)
$script = <<<LUA
local current = redis.call('INCR', KEYS[1])
if current == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return current
LUA;

$count = Redis::eval($script, 1, 'rate:user:1', 60);

// Pub/Sub
Redis::subscribe(['channel'], function ($message, $channel) {
    echo "Received: $message on $channel\n";
});
Redis::publish('channel', json_encode(['event' => 'test']));
```

---

## Praktik Patterns

*Praktik Patterns üçün kod nümunəsi:*
```php
// Bu kod cache-aside, distributed lock və sliding window rate limiting pattern-lərini göstərir
// 1. Cache-aside pattern
class ProductRepository
{
    public function find(int $id): ?Product
    {
        $cacheKey = "product:$id";
        
        $cached = Redis::get($cacheKey);
        if ($cached) return unserialize($cached);
        
        $product = Product::find($id);
        if ($product) {
            Redis::set($cacheKey, serialize($product), 'EX', 3600);
        }
        
        return $product;
    }
}

// 2. Distributed Lock (Redlock simplified)
class RedisLock
{
    public function acquire(string $key, int $ttlMs = 5000): ?string
    {
        $token = Str::random(32);
        $result = Redis::set($key, $token, 'PX', $ttlMs, 'NX');
        return $result ? $token : null;
    }
    
    public function release(string $key, string $token): void
    {
        $script = <<<LUA
        if redis.call('GET', KEYS[1]) == ARGV[1] then
            return redis.call('DEL', KEYS[1])
        end
        return 0
        LUA;
        Redis::eval($script, 1, $key, $token);
    }
}

// 3. Rate Limiting (sliding window)
class SlidingWindowRateLimit
{
    public function attempt(string $key, int $limit, int $window): bool
    {
        $now = microtime(true) * 1000;  // ms
        
        $script = <<<LUA
        local key = KEYS[1]
        local now = tonumber(ARGV[1])
        local window = tonumber(ARGV[2])
        local limit = tonumber(ARGV[3])
        
        redis.call('ZREMRANGEBYSCORE', key, 0, now - window)
        local count = redis.call('ZCARD', key)
        
        if count < limit then
            redis.call('ZADD', key, now, now)
            redis.call('EXPIRE', key, math.ceil(window/1000))
            return 1
        end
        return 0
        LUA;
        
        return (bool) Redis::eval($script, 1, $key, $now, $window * 1000, $limit);
    }
}
```

---

## İntervyu Sualları

**1. Redis-in əsas data strukturlarını izah et.**
String: sadə key-value, counter. Hash: object field-ləri. List: ordered elements, queue/stack. Set: unique elements. Sorted Set: score ilə sıralı, leaderboard. Stream: event log, consumer groups. Pub/Sub: real-time messaging.

**2. RDB vs AOF fərqi nədir?**
RDB: periodic snapshot, compact, sürətli restore, data loss possible. AOF: hər write log-lanır, az data loss (everysec=max 1s), böyük fayl. Production: hər ikisi birlikdə — RDB sürətli restart, AOF data safety.

**3. Redis Sentinel vs Cluster fərqi nədir?**
Sentinel: HA (high availability), automatic failover, data bir node-da. Master çöksə replica promote edilir. Cluster: horizontal sharding, 16384 slot, data bir neçə node-a bölünür. Hem HA, həm scale. Sentinel sadədir, cluster mürəkkəbdir.

**4. Eviction policy nə zaman nəyi seçmək lazımdır?**
Cache (data yenidən yüklənə bilər): allkeys-lru. Session/persistent data: noeviction (xəta vermək daha yaxşı). TTL-li cache mix: volatile-lru. Frequency-based: allkeys-lfu.

**5. Pipeline vs Transaction fərqi nədir?**
Pipeline: batch commands göndər, round-trip azalt, atomik deyil. Transaction (MULTI/EXEC): atomik, ya hamısı ya heç biri, amma optimistic — WATCH ilə. Lua script: həm batch, həm atomik — ən güclü.

**6. Redis single-threaded olmasına baxmayaraq niyə bu qədər sürətlidir?**
RAM-da işləyir (disk I/O yoxdur). Single-threaded olduğu üçün lock/mutex overhead-i yoxdur. I/O multiplexing (epoll/kqueue) ilə minlərlə connection bir thread-də idarə edilir. 6.0-dan write command-lar üçün I/O threads əlavə edilib.

**7. Redis Streams vs Pub/Sub fərqi nədir?**
Pub/Sub: fire-and-forget, subscriber online olmazsa mesaj itirilir, persist yoxdur. Streams: persistent, consumer groups, mesaj oxunduqdan sonra ACK lazımdır, gecikmə ilə oxunan mesajlar pending qalır. Reliability lazımdırsa Streams seçin.

**8. Redlock nədir, niyə sadə `SET key NX` distributed lock üçün yetərli deyil?**
Tək node Redis çöksə lock release edilə bilmir ya da stale lock yaranır. Redlock: N Redis instance-a eyni lock yazmağa cəhd edilir, N/2+1 uğurlu olarsa lock əldə edilib. Amma Martin Kleppmann Redlock-un clock drift halında unsafe olduğunu göstərib — kritik sistemlər üçün ZooKeeper/etcd lock daha etibarlıdır.

---

## Anti-patternlər

**1. Redis-i əsas (primary) veritabanı kimi istifadə etmək**
Bütün business datanı yalnız Redis-də saxlamaq — RDB/AOF olmadan restart-da bütün data itirilir, durability zəmanəti yoxdur. Redis cache, session, rate limit, leaderboard üçün istifadə edin; əsas data mütləq persistent DB-də (MySQL/PostgreSQL) saxlanılsın.

**2. TTL qoymadan key-lər yaratmaq**
`SET user:profile:123 ...` — TTL yoxdur, key heç vaxt silinmir, memory tədricən dolur, eviction siyasəti lazımsız data-nı da sila bilər. Hər cache key-inə mənalı TTL verin; eviction policy-ni `allkeys-lru` ilə konfiqurasiya edin ki, memory dolduqda ən az istifadə olunanlar silinsin.

**3. `KEYS *` əmrini production-da istifadə etmək**
`KEYS user:*` — single-threaded Redis bütün key-ləri taraması üçün bloklanır, yüz minlərlə key varsa saniyələrlə cavab vermir. `SCAN` əmrini istifadə edin: non-blocking, cursor-based iterasiya, production-u bloklamır.

**4. Distributed lock olmadan Redis-i race condition üçün istifadə etmək**
`GET balance → check → SET balance` — iki proses eyni anda oxuyub yazarsa race condition yaranır, data corrupted olur. Atomik əməliyyatlar üçün Lua script ya da `SET key value NX PX` (Redlock) istifadə edin; sadə counter üçün `INCR`/`DECR` əmrləri atomikdir.

**5. Bütün data-nı bir Redis instance-da saxlamaq**
Həm cache, həm session, həm queue data eyni instance-da — biri memory-ni doldurur, eviction bütün növ data-ya təsir edir. Data növlərinə görə ayrı Redis instance-lar ya da ayrı database-lər istifadə edin; kritik session data-sını non-evicting ayrı instance-a köçürün.

**6. Connection pool-suz hər sorğuda yeni Redis bağlantısı açmaq**
`new Redis()` hər request üçün — connection overhead artır, Redis max connection limitinə yaxınlaşıldıqda xətalar başlayır. Persistent connection ya da connection pool istifadə edin; PHP-FPM ilə `phpredis` extension-ı persistent connection dəstəkləyir.
