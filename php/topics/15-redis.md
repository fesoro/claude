# Redis - Tam Hərtərəfli Bələdçi

## 1. Redis Nədir?

**Redis** (Remote Dictionary Server) — açıq mənbəli, in-memory data structure store-dur. Əsasən cache, message broker, session store və real-time analytics üçün istifadə olunur. Redis bütün məlumatları RAM-da saxlayır, bu səbəbdən çox sürətlidir (saniyədə yüz minlərlə əməliyyat).

### Redis-in Əsas Xüsusiyyətləri

- **In-memory storage** — bütün data RAM-da saxlanılır
- **Single-threaded** — bir thread ilə işləyir (I/O multiplexing ilə), race condition yoxdur
- **Persistence** — disk-ə yazma dəstəyi (RDB, AOF)
- **Replication** — master-replica arxitekturası
- **High Availability** — Redis Sentinel
- **Horizontal Scaling** — Redis Cluster
- **Rich data structures** — sadəcə key-value deyil, müxtəlif data strukturları

### Redis Necə İşləyir?

```
Client -> TCP Connection (port 6379) -> Redis Server (Single Thread + Event Loop)
                                              |
                                        RAM (Data Store)
                                              |
                                        Disk (RDB/AOF - optional persistence)
```

Redis **event-driven**, **non-blocking I/O** modeli istifadə edir. `epoll` (Linux) və ya `kqueue` (macOS) vasitəsilə minlərlə client-i eyni anda idarə edə bilir.

---

## 2. Redis Data Structures

### 2.1 String

Ən sadə data tipi. Mətn, rəqəm, serialized JSON, binary data saxlaya bilər. Maksimum 512 MB.

*Ən sadə data tipi. Mətn, rəqəm, serialized JSON, binary data saxlaya b üçün kod nümunəsi:*
```bash
# Sadə string əməliyyatları
SET user:1:name "Orxan"
GET user:1:name              # "Orxan"

# Expire ilə
SET session:abc123 "user_data" EX 3600    # 1 saat TTL
SETEX session:abc123 3600 "user_data"     # Eyni nəticə

# NX - yalnız mövcud deyilsə set et (distributed lock üçün)
SET lock:order:123 "worker1" NX EX 30

# Atomic increment/decrement
SET counter 0
INCR counter          # 1
INCRBY counter 5      # 6
DECR counter          # 5
DECRBY counter 3      # 2

# Float increment
SET price 10.50
INCRBYFLOAT price 1.25    # "11.75"

# Multiple key əməliyyatları
MSET user:1:name "Orxan" user:1:age "30" user:1:city "Baku"
MGET user:1:name user:1:age user:1:city

# String manipulation
APPEND user:1:name " Mammadov"    # "Orxan Mammadov"
STRLEN user:1:name                 # 15
GETRANGE user:1:name 0 4          # "Orxan"
```

### 2.2 List

Doubly-linked list. Sıralı elementlər saxlayır. Stack və ya Queue kimi istifadə oluna bilər.

*Doubly-linked list. Sıralı elementlər saxlayır. Stack və ya Queue kimi üçün kod nümunəsi:*
```bash
# Sola/sağa əlavə
LPUSH notifications:user1 "Yeni mesaj" "Sifariş təsdiqləndi"
RPUSH notifications:user1 "Ödəniş uğurlu"

# Oxumaq
LRANGE notifications:user1 0 -1    # Hamısını göstər
LRANGE notifications:user1 0 2     # İlk 3 element
LINDEX notifications:user1 0       # İlk element
LLEN notifications:user1           # Uzunluq

# Pop əməliyyatları
LPOP notifications:user1           # Soldan çıxar
RPOP notifications:user1           # Sağdan çıxar
BLPOP queue:emails 30              # Blocking pop (30 saniyə gözlə)
BRPOP queue:emails 30

# Queue pattern (FIFO)
RPUSH queue:emails "email1"        # Sağdan əlavə et
LPOP queue:emails                  # Soldan çıxar

# Stack pattern (LIFO)
LPUSH stack:undo "action1"         # Soldan əlavə et
LPOP stack:undo                    # Soldan çıxar

# Trim - yalnız son N elementi saxla
LTRIM notifications:user1 0 99    # Son 100 bildirişi saxla
```

### 2.3 Set

Unikal, sırasız elementlər toplusu. Kəsişmə, birləşmə əməliyyatları mövcuddur.

*Unikal, sırasız elementlər toplusu. Kəsişmə, birləşmə əməliyyatları mö üçün kod nümunəsi:*
```bash
SADD tags:post:1 "php" "laravel" "redis"
SADD tags:post:2 "php" "mysql" "docker"

SMEMBERS tags:post:1              # Bütün üzvlər
SCARD tags:post:1                 # Üzv sayı (3)
SISMEMBER tags:post:1 "php"      # Üzvdür? (1 = bəli)

# Kəsişmə - hər iki postda olan tag-lar
SINTER tags:post:1 tags:post:2   # {"php"}

# Birləşmə - bütün unikal tag-lar
SUNION tags:post:1 tags:post:2   # {"php", "laravel", "redis", "mysql", "docker"}

# Fərq
SDIFF tags:post:1 tags:post:2    # {"laravel", "redis"}

# Random element
SRANDMEMBER tags:post:1 2        # 2 random element

# Pop random
SPOP tags:post:1                  # Random element çıxar

# Online users tracking
SADD online:users "user:1" "user:2" "user:3"
SREM online:users "user:2"        # İstifadəçi çıxdı
SCARD online:users                 # Neçə nəfər online
```

### 2.4 Sorted Set (ZSet)

Hər elementin score-u olan sıralanmış set. Leaderboard, ranking, priority queue üçün ideal.

*Hər elementin score-u olan sıralanmış set. Leaderboard, ranking, prior üçün kod nümunəsi:*
```bash
# Leaderboard
ZADD leaderboard 1500 "player1"
ZADD leaderboard 2300 "player2"
ZADD leaderboard 1800 "player3"
ZADD leaderboard 3100 "player4"

# Sıralama (aşağıdan yuxarıya)
ZRANGE leaderboard 0 -1 WITHSCORES
# player1: 1500, player3: 1800, player2: 2300, player4: 3100

# Ən yüksək score (yuxarıdan aşağıya)
ZREVRANGE leaderboard 0 2 WITHSCORES    # Top 3
# player4: 3100, player2: 2300, player3: 1800

# Score aralığında
ZRANGEBYSCORE leaderboard 1500 2000 WITHSCORES

# Rank (sıra nömrəsi)
ZRANK leaderboard "player2"       # 2 (0-dan başlayır, aşağıdan)
ZREVRANK leaderboard "player2"    # 1 (yuxarıdan)

# Score artır
ZINCRBY leaderboard 500 "player1"  # 1500 + 500 = 2000

# Sayı
ZCARD leaderboard                  # 4
ZCOUNT leaderboard 1500 2500      # Score aralığındakı say

# Silmə
ZREM leaderboard "player1"
ZREMRANGEBYSCORE leaderboard 0 1000    # Score < 1000 olanları sil
ZREMRANGEBYRANK leaderboard 0 1        # İlk 2-ni sil
```

### 2.5 Hash

Key-value cütlərindən ibarət obyekt. Verilənlər bazası sətrinə bənzəyir.

*Key-value cütlərindən ibarət obyekt. Verilənlər bazası sətrinə bənzəyi üçün kod nümunəsi:*
```bash
HSET user:1 name "Orxan" age 30 city "Baku" email "orxan@test.com"

HGET user:1 name               # "Orxan"
HGETALL user:1                 # Bütün field-lər və dəyərlər
HMGET user:1 name email        # Bir neçə field

HEXISTS user:1 phone           # 0 (yoxdur)
HKEYS user:1                   # Bütün key-lər
HVALS user:1                   # Bütün dəyərlər
HLEN user:1                    # Field sayı

HDEL user:1 city               # Field sil
HINCRBY user:1 age 1           # Age-i 1 artır (31)

# NX - yalnız mövcud deyilsə
HSETNX user:1 phone "+994501234567"
```

### 2.6 Stream

Append-only log data structure. Kafka-ya bənzər message streaming.

*Append-only log data structure. Kafka-ya bənzər message streaming üçün kod nümunəsi:*
```bash
# Stream-ə mesaj əlavə et
XADD orders * product "laptop" quantity 1 price 2500
XADD orders * product "phone" quantity 2 price 800
# Nəticə: "1680000000000-0" (auto-generated ID)

# Oxumaq
XRANGE orders - +                  # Hamısını oxu
XRANGE orders - + COUNT 10        # İlk 10
XLEN orders                        # Mesaj sayı

# Consumer Group
XGROUP CREATE orders order-processors $ MKSTREAM
XREADGROUP GROUP order-processors worker1 COUNT 1 BLOCK 5000 STREAMS orders >
XACK orders order-processors "1680000000000-0"    # Acknowledge

# Pending messages
XPENDING orders order-processors
```

### 2.7 Bitmap

Bit-level əməliyyatlar. Çox yaddaş-effektiv. Gündəlik aktiv istifadəçilər, feature flag-lar üçün ideal.

*Bit-level əməliyyatlar. Çox yaddaş-effektiv. Gündəlik aktiv istifadəçi üçün kod nümunəsi:*
```bash
# İstifadəçi #1000 bu gün aktiv
SETBIT daily:active:2024-01-15 1000 1
SETBIT daily:active:2024-01-15 1001 1
SETBIT daily:active:2024-01-15 1002 1

# İstifadəçi aktiv idi?
GETBIT daily:active:2024-01-15 1000    # 1

# Aktiv istifadəçi sayı
BITCOUNT daily:active:2024-01-15       # 3

# Son 7 gündə hər gün aktiv olanlar (AND)
BITOP AND weekly:active daily:active:2024-01-09 daily:active:2024-01-10 ... daily:active:2024-01-15

# Son 7 gündə ən azı 1 dəfə aktiv olanlar (OR)
BITOP OR weekly:any:active daily:active:2024-01-09 ... daily:active:2024-01-15
```

### 2.8 HyperLogLog

Probabilistic data structure. Unikal elementlərin sayını təxmini hesablayır (0.81% xəta). Çox az yaddaş istifadə edir (12 KB).

*Probabilistic data structure. Unikal elementlərin sayını təxmini hesab üçün kod nümunəsi:*
```bash
# Unikal ziyarətçilər
PFADD visitors:2024-01-15 "user1" "user2" "user3" "user1"    # user1 təkrarlanır
PFCOUNT visitors:2024-01-15     # 3 (təxmini)

# Birləşdirmək
PFMERGE visitors:jan visitors:2024-01-01 visitors:2024-01-02 ... visitors:2024-01-31
PFCOUNT visitors:jan            # Yanvar ayının unikal ziyarətçi sayı
```

### 2.9 Geospatial

Coğrafi koordinatlar və məsafə hesablamaları.

*Coğrafi koordinatlar və məsafə hesablamaları üçün kod nümunəsi:*
```bash
# Məkan əlavə et
GEOADD restaurants 49.8671 40.4093 "restaurant1"
GEOADD restaurants 49.8520 40.3780 "restaurant2"
GEOADD restaurants 49.8400 40.4200 "restaurant3"

# İki nöqtə arası məsafə
GEODIST restaurants "restaurant1" "restaurant2" km

# Radius-da axtarış
GEOSEARCH restaurants FROMLONLAT 49.8600 40.4000 BYRADIUS 5 km ASC COUNT 10

# Koordinatları al
GEOPOS restaurants "restaurant1"

# Geohash
GEOHASH restaurants "restaurant1"
```

---

## 3. Redis Key Əməliyyatları və TTL

*3. Redis Key Əməliyyatları və TTL üçün kod nümunəsi:*
```bash
# Key mövcuddur?
EXISTS user:1                  # 1 (bəli)

# Key tipi
TYPE user:1                    # hash

# TTL təyin et
EXPIRE user:1 3600             # 1 saat (saniyə)
PEXPIRE user:1 3600000         # 1 saat (millisaniyə)
EXPIREAT user:1 1700000000     # Unix timestamp

# TTL yoxla
TTL user:1                     # Qalan saniyə (-1: expire yoxdur, -2: key yoxdur)
PTTL user:1                    # Qalan millisaniyə

# TTL sil
PERSIST user:1                 # Expire-ı sil

# Key-ləri axtar (PRODUCTION-da istifadə etmə!)
KEYS user:*                    # Pattern ilə (blocking, yavaş)
SCAN 0 MATCH user:* COUNT 100 # Cursor-based (non-blocking, təhlükəsiz)

# Key sil
DEL user:1                     # Sinxron silmə
UNLINK user:1                  # Asinxron silmə (böyük key-lər üçün)

# Key-i yenidən adlandır
RENAME user:1 customer:1
RENAMENX user:1 customer:1    # Yalnız customer:1 mövcud deyilsə
```

---

## 4. Persistence: RDB vs AOF

### 4.1 RDB (Redis Database Backup)

Point-in-time snapshot. Müəyyən intervallarla bütün data-nın snapshot-ını disk-ə yazır.

```
# redis.conf
save 900 1        # 900 saniyədə ən azı 1 dəyişiklik varsa
save 300 10       # 300 saniyədə ən azı 10 dəyişiklik varsa
save 60 10000     # 60 saniyədə ən azı 10000 dəyişiklik varsa

dbfilename dump.rdb
dir /var/lib/redis
```

**Üstünlükləri:**
- Kompakt fayl formatı
- Sürətli restart
- Fork ilə child process yazır, parent-ə təsir etmir

**Mənfi cəhətləri:**
- Snapshot arası data itkisi mümkündür
- Fork əməliyyatı böyük dataset-lərdə yavaş ola bilər

### 4.2 AOF (Append Only File)

Hər write əməliyyatını log faylına yazır.

```
# redis.conf
appendonly yes
appendfilename "appendonly.aof"

# Sync strategiyası
appendfsync always      # Hər write-da (ən yavaş, ən təhlükəsiz)
appendfsync everysec    # Hər saniyə (tövsiyə olunan)
appendfsync no          # OS-ə burax (ən sürətli, riskli)

# AOF rewrite (faylı kiçilt)
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb
```

**Üstünlükləri:**
- Minimal data itkisi (ən çox 1 saniyə)
- Oxuna bilən format
- Background rewrite

**Mənfi cəhətləri:**
- RDB-dən böyük fayl
- RDB-dən yavaş restart

### 4.3 Hibrid Yanaşma (Redis 4.0+)

```
# redis.conf
aof-use-rdb-preamble yes    # AOF faylının əvvəlində RDB snapshot, sonra AOF
```

**Tövsiyə:** Production-da hər ikisini aktiv edin: RDB + AOF.

---

## 5. Redis Cluster

Redis Cluster data-nı avtomatik olaraq bir neçə node arasında paylaşır (sharding).

### Necə İşləyir?

- 16384 hash slot var
- Hər key CRC16 ilə hash olunur və 16384-ə bölünür
- Hər node müəyyən slot aralığını idarə edir

```
Node A: Slots 0-5460
Node B: Slots 5461-10922
Node C: Slots 10923-16383
```

### Cluster Qurulması

*Cluster Qurulması üçün kod nümunəsi:*
```bash
# 6 node (3 master + 3 replica)
redis-cli --cluster create \
  192.168.1.1:6379 192.168.1.2:6379 192.168.1.3:6379 \
  192.168.1.4:6379 192.168.1.5:6379 192.168.1.6:6379 \
  --cluster-replicas 1
```

### Hash Tags

Eyni slot-a düşməsini istəyirsinizsə:

*Eyni slot-a düşməsini istəyirsinizsə üçün kod nümunəsi:*
```bash
SET {user:1}:profile "data"
SET {user:1}:settings "data"
# {user:1} hash tag - hər ikisi eyni slot-dadır
```

### Məhdudiyyətlər

- Multi-key əməliyyatlar yalnız eyni slot-dakı key-lərə işləyir
- Lua script-lər eyni slot key-ləri tələb edir
- Database seçimi yoxdur (yalnız DB 0)

---

## 6. Redis Sentinel (High Availability)

Sentinel Redis master-replica setup-unu monitor edir və avtomatik failover edir.

### Sentinel Funksiyaları

1. **Monitoring** — Master və replica-ların sağlamlığını yoxlayır
2. **Notification** — Problem zamanı xəbərdarlıq göndərir
3. **Automatic Failover** — Master çöksə, replica-nı master edir
4. **Configuration Provider** — Client-lərə cari master-in ünvanını verir

```
# sentinel.conf
sentinel monitor mymaster 192.168.1.1 6379 2      # 2 sentinel razılaşmalıdır
sentinel down-after-milliseconds mymaster 5000      # 5 saniyə cavab verməsə
sentinel failover-timeout mymaster 60000             # Failover timeout
sentinel parallel-syncs mymaster 1                   # Eyni anda neçə replica sync olsun
```

### Laravel ilə Sentinel

*Laravel ilə Sentinel üçün kod nümunəsi:*
```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'default' => [
        // Sentinel konfiqurasiyası
        'tcp://sentinel1:26379',
        'tcp://sentinel2:26379',
        'tcp://sentinel3:26379',
        'options' => [
            'replication' => 'sentinel',
            'service'     => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'parameters'  => [
                'password' => env('REDIS_PASSWORD'),
                'database' => 0,
            ],
        ],
    ],
],
```

---

## 7. Redis Pub/Sub

Publisher-Subscriber messaging pattern. Real-time mesajlaşma üçün.

*Publisher-Subscriber messaging pattern. Real-time mesajlaşma üçün üçün kod nümunəsi:*
```bash
# Terminal 1 - Subscriber
SUBSCRIBE news:tech news:sports

# Terminal 2 - Publisher
PUBLISH news:tech "Redis 8.0 released!"
PUBLISH news:sports "Match result: 2-1"

# Pattern subscribe
PSUBSCRIBE news:*            # news: ilə başlayan bütün kanallar
```

### Laravel ilə Pub/Sub

*Laravel ilə Pub/Sub üçün kod nümunəsi:*
```php
// Broadcasting Event
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderShipped implements ShouldBroadcast
{
    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
            new PrivateChannel('orders.' . $this->order->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.shipped';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status'   => $this->order->status,
            'shipped_at' => now()->toISOString(),
        ];
    }
}

// Manuel Pub/Sub
use Illuminate\Support\Facades\Redis;

// Publish
Redis::publish('chat:room:1', json_encode([
    'user' => 'Orxan',
    'message' => 'Salam!',
    'timestamp' => now()->toISOString(),
]));

// Subscribe (uzun müddətli proses - artisan command-da istifadə edin)
Redis::subscribe(['chat:room:1'], function (string $message, string $channel) {
    $data = json_decode($message, true);
    echo "[$channel] {$data['user']}: {$data['message']}\n";
});

// Pattern subscribe
Redis::psubscribe(['chat:room:*'], function (string $message, string $channel) {
    // Bütün chat room-lardan mesajlar
});
```

### Pub/Sub Məhdudiyyətləri

- Fire-and-forget: Offline subscriber mesajı almaz
- Mesaj persistence yoxdur
- Acknowledgment yoxdur
- Bu səbəbdən ciddi queue ehtiyacları üçün **Redis Streams** və ya **RabbitMQ** istifadə edin

---

## 8. Redis Streams (Dərin)

Kafka-ya bənzər, persistent, consumer group dəstəkli mesaj streaming.

*Kafka-ya bənzər, persistent, consumer group dəstəkli mesaj streaming üçün kod nümunəsi:*
```bash
# Mesaj göndər
XADD events * type "user.registered" user_id 123 email "test@test.com"
XADD events * type "order.created" order_id 456 amount 150.00
# Nəticə: "1680000001234-0"

# Müəyyən ID ilə
XADD events 1680000001234-0 type "custom" data "value"

# Consumer Group yarat
XGROUP CREATE events event-processors 0 MKSTREAM

# Consumer Group ilə oxu
XREADGROUP GROUP event-processors consumer1 COUNT 5 BLOCK 2000 STREAMS events >

# Acknowledge
XACK events event-processors "1680000001234-0"

# Pending mesajlar (acknowledge olunmamış)
XPENDING events event-processors - + 10

# Claim - başqa consumer-in pending mesajını al
XCLAIM events event-processors consumer2 3600000 "1680000001234-0"

# Auto-claim (Redis 6.2+)
XAUTOCLAIM events event-processors consumer2 3600000 0-0 COUNT 10

# Stream uzunluğunu məhdudla
XADD events MAXLEN ~ 10000 * type "event" data "value"
# ~ işarəsi təxmini trim deməkdir (daha effektiv)

# Stream info
XINFO STREAM events
XINFO GROUPS events
XINFO CONSUMERS events event-processors
```

---

## 9. Redis Transactions (MULTI/EXEC)

Redis transaction-lar atomikdir — ya hamısı icra olunur, ya heç biri.

*Redis transaction-lar atomikdir — ya hamısı icra olunur, ya heç biri üçün kod nümunəsi:*
```bash
MULTI                          # Transaction başla
SET user:1:balance 1000
DECRBY user:1:balance 200
INCRBY user:2:balance 200
EXEC                           # Transaction icra et

# Əgər ləğv etmək istəsəniz
MULTI
SET key1 "value1"
DISCARD                        # Transaction ləğv et
```

### WATCH ilə Optimistic Locking

*WATCH ilə Optimistic Locking üçün kod nümunəsi:*
```bash
WATCH user:1:balance           # Key-i izlə
GET user:1:balance             # 1000

MULTI
DECRBY user:1:balance 200
EXEC
# Əgər başqa client user:1:balance-ı dəyişdibsə, EXEC nil qaytarır
# Bu halda yenidən cəhd etmək lazımdır
```

### Laravel ilə Transaction

*Laravel ilə Transaction üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Redis;

// Transaction
Redis::transaction(function ($redis) {
    $redis->set('key1', 'value1');
    $redis->set('key2', 'value2');
    $redis->incr('counter');
});

// Pipeline ilə transaction
$results = Redis::pipeline(function ($pipe) {
    $pipe->set('key1', 'value1');
    $pipe->set('key2', 'value2');
    $pipe->get('key1');
    $pipe->get('key2');
});
```

---

## 10. Redis Lua Scripting

Lua script-lər Redis server-də atomik olaraq icra olunur. Complex logic-i bir atomic əməliyyatda icra etmək üçün istifadə olunur.

*Lua script-lər Redis server-də atomik olaraq icra olunur. Complex logi üçün kod nümunəsi:*
```bash
# Sadə Lua script
EVAL "return redis.call('GET', KEYS[1])" 1 user:1:name

# Rate limiter Lua script
EVAL "
    local current = redis.call('INCR', KEYS[1])
    if current == 1 then
        redis.call('EXPIRE', KEYS[1], ARGV[1])
    end
    return current
" 1 rate:user:1 60

# Atomic transfer
EVAL "
    local from_balance = tonumber(redis.call('GET', KEYS[1]))
    local to_balance = tonumber(redis.call('GET', KEYS[2]))
    local amount = tonumber(ARGV[1])
    
    if from_balance >= amount then
        redis.call('DECRBY', KEYS[1], amount)
        redis.call('INCRBY', KEYS[2], amount)
        return 1
    else
        return 0
    end
" 2 user:1:balance user:2:balance 500
```

### Laravel ilə Lua Script

*Laravel ilə Lua Script üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Redis;

// Inline Lua script
$result = Redis::eval(<<<'LUA'
    local current = redis.call('INCR', KEYS[1])
    if current == 1 then
        redis.call('EXPIRE', KEYS[1], ARGV[1])
    end
    if current > tonumber(ARGV[2]) then
        return 0
    end
    return 1
LUA, 1, 'rate_limit:user:' . $userId, 60, 100);

// Script-i əvvəlcədən yüklə (EVALSHA ilə performans)
$sha = Redis::script('load', <<<'LUA'
    local current = redis.call('INCR', KEYS[1])
    if current == 1 then
        redis.call('EXPIRE', KEYS[1], ARGV[1])
    end
    return current
LUA);

$result = Redis::evalsha($sha, 1, 'counter:key', 3600);
```

---

## 11. Redis Pipelining

Bir neçə command-ı eyni anda göndərir, network round-trip-ləri azaldır. Hər əmr üçün ayrıca response gözləmək əvəzinə, bütün əmrləri bir dəfəyə göndərir.

*Bir neçə command-ı eyni anda göndərir, network round-trip-ləri azaldır üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Redis;

// Pipeline olmadan (yavaş - hər biri ayrı round-trip)
foreach ($users as $user) {
    Redis::set("user:{$user->id}:name", $user->name);
}

// Pipeline ilə (sürətli - bir round-trip)
Redis::pipeline(function ($pipe) use ($users) {
    foreach ($users as $user) {
        $pipe->set("user:{$user->id}:name", $user->name);
        $pipe->set("user:{$user->id}:email", $user->email);
        $pipe->expire("user:{$user->id}:name", 3600);
        $pipe->expire("user:{$user->id}:email", 3600);
    }
});

// Pipeline nəticələrini almaq
$results = Redis::pipeline(function ($pipe) {
    $pipe->get('key1');
    $pipe->get('key2');
    $pipe->hgetall('user:1');
});
// $results[0] = key1 value
// $results[1] = key2 value
// $results[2] = user:1 hash
```

**Pipeline vs Transaction fərqi:**
- **Pipeline**: Batch göndərmə, atomik deyil
- **Transaction (MULTI/EXEC)**: Atomik, amma hər əmr ayrıca göndərilir
- **Pipeline + Transaction**: Hər ikisinin üstünlüyü

---

## 12. Eviction Policies (Yaddaş Dolduqda)

Redis yaddaşı dolduqda hansı key-ləri silməli olduğunu müəyyən edən siyasətlər.

```
# redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
```

| Policy | Təsvir |
|--------|--------|
| `noeviction` | Yeni write-ları rədd et (default) |
| `allkeys-lru` | Bütün key-lər arasında ən az istifadə olunanı sil (LRU) |
| `allkeys-lfu` | Bütün key-lər arasında ən az tez-tez istifadə olunanı sil (LFU) |
| `allkeys-random` | Random key sil |
| `volatile-lru` | Yalnız TTL olan key-lər arasında LRU |
| `volatile-lfu` | Yalnız TTL olan key-lər arasında LFU |
| `volatile-random` | Yalnız TTL olan key-lərdən random sil |
| `volatile-ttl` | Ən qısa TTL-i olanı sil |

**Tövsiyə:**
- Cache üçün: `allkeys-lru` və ya `allkeys-lfu`
- Persistent data + cache qarışığı üçün: `volatile-lru`

---

## 13. Memory Optimization

### 13.1 Düzgün Data Strukturu Seçimi

*13.1 Düzgün Data Strukturu Seçimi üçün kod nümunəsi:*
```php
// PIS: Hər field üçün ayrı key
Redis::set('user:1:name', 'Orxan');
Redis::set('user:1:email', 'orxan@test.com');
Redis::set('user:1:age', 30);
// 3 key = çox overhead

// YAXSI: Hash istifadə et
Redis::hset('user:1', 'name', 'Orxan', 'email', 'orxan@test.com', 'age', 30);
// 1 key = az overhead (hash-ziplist encoding istifadə edir)
```

### 13.2 Key Adlandırma

*13.2 Key Adlandırma üçün kod nümunəsi:*
```php
// PIS: Uzun key adları
Redis::set('application:production:user:profile:data:12345', $data);

// YAXSI: Qısa amma mənalı
Redis::set('u:12345:p', $data);
```

### 13.3 Yaddaş Analizi

*13.3 Yaddaş Analizi üçün kod nümunəsi:*
```bash
# Key-in yaddaş istifadəsi
MEMORY USAGE user:1

# Ümumi yaddaş statistikası
INFO memory

# Böyük key-ləri tap
redis-cli --bigkeys

# Yaddaş doctor
MEMORY DOCTOR
```

### 13.4 Redis Konfiqurasiyası

```
# Kiçik hash-lar üçün ziplist encoding (yaddaş qənaəti)
hash-max-ziplist-entries 512
hash-max-ziplist-value 64

# Kiçik list-lər üçün
list-max-ziplist-size -2

# Kiçik set-lər üçün
set-max-intset-entries 512

# Kiçik sorted set-lər üçün
zset-max-ziplist-entries 128
zset-max-ziplist-value 64
```

---

## 14. Laravel-də Redis İstifadəsi

### 14.1 Quraşdırma

*14.1 Quraşdırma üçün kod nümunəsi:*
```bash
composer require predis/predis
# və ya PHP extension: pecl install redis
```

*və ya PHP extension: pecl install redis üçün kod nümunəsi:*
```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'), // phpredis daha sürətlidir

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix'  => env('REDIS_PREFIX', 'laravel_database_'),
    ],

    'default' => [
        'url'      => env('REDIS_URL'),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port'     => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],

    'cache' => [
        'url'      => env('REDIS_URL'),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port'     => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
],
```

### 14.2 Cache Driver Olaraq

*14.2 Cache Driver Olaraq üçün kod nümunəsi:*
```php
// .env
CACHE_DRIVER=redis

// Sadə cache əməliyyatları
use Illuminate\Support\Facades\Cache;

// Yazma
Cache::put('user:1:profile', $profile, now()->addHours(2));
Cache::forever('settings:app', $settings);

// Oxuma
$profile = Cache::get('user:1:profile');
$profile = Cache::get('user:1:profile', 'default_value');

// Remember pattern (ən çox istifadə olunan)
$users = Cache::remember('users:active', 3600, function () {
    return User::where('active', true)->get();
});

// Sonsuz cache remember
$countries = Cache::rememberForever('countries', function () {
    return Country::all();
});

// Silmə
Cache::forget('user:1:profile');
Cache::flush(); // BÜTÜN cache-i sil (DİQQƏT!)

// Atomic operations
Cache::increment('page:views', 1);
Cache::decrement('stock:product:1', 5);

// Cache Tags (yalnız redis/memcached)
Cache::tags(['products', 'featured'])->put('product:1', $product, 3600);
Cache::tags(['products'])->flush(); // Yalnız products tag-lı cache-ləri sil

// Cache Lock
$lock = Cache::lock('processing:order:123', 10); // 10 saniyə lock
if ($lock->get()) {
    try {
        // Əməliyyat
    } finally {
        $lock->release();
    }
}

// Block until lock available
Cache::lock('processing:order:123', 10)->block(5, function () {
    // Lock alınanadək 5 saniyə gözlə
    // Lock alındıqda bu closure icra olunur
});
```

### 14.3 Session Driver Olaraq

*14.3 Session Driver Olaraq üçün kod nümunəsi:*
```php
// .env
SESSION_DRIVER=redis

// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'connection' => env('SESSION_CONNECTION', 'default'),
'lifetime' => env('SESSION_LIFETIME', 120),
```

### 14.4 Queue Driver Olaraq

*14.4 Queue Driver Olaraq üçün kod nümunəsi:*
```php
// .env
QUEUE_CONNECTION=redis

// config/queue.php
'redis' => [
    'driver'       => 'redis',
    'connection'   => 'default',
    'queue'        => env('REDIS_QUEUE', 'default'),
    'retry_after'  => 90,
    'block_for'    => null,
    'after_commit' => false,
],
```

### 14.5 Rate Limiting

*14.5 Rate Limiting üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\RateLimiter;

// Laravel Rate Limiter (Redis-based)
// app/Providers/RouteServiceProvider.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Route-da istifadə
Route::middleware('throttle:api')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

// Manual rate limiting with Redis
$key = 'rate_limit:' . $request->ip();
$maxAttempts = 100;
$decaySeconds = 60;

$current = Redis::incr($key);
if ($current === 1) {
    Redis::expire($key, $decaySeconds);
}

if ($current > $maxAttempts) {
    abort(429, 'Too many requests');
}

// Sliding window rate limiter
Redis::throttle('api:user:' . $userId)
    ->allow(100)
    ->every(60)
    ->then(function () {
        // İcazə verildi
        return $this->processRequest();
    }, function () {
        // Limit aşıldı
        return response()->json(['error' => 'Rate limit exceeded'], 429);
    });
```

### 14.6 Distributed Locks

*14.6 Distributed Locks üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

// Redis Lock ilə idempotent payment processing
class ProcessPaymentJob implements ShouldQueue
{
    public function handle(): void
    {
        $lock = Cache::lock('payment:' . $this->orderId, 30);

        if ($lock->get()) {
            try {
                // Ödəniş artıq emal olunub?
                if ($this->order->isPaid()) {
                    return;
                }

                // Ödənişi emal et
                $this->processPayment();
            } finally {
                $lock->release();
            }
        } else {
            // Lock alına bilmədi - başqa worker emal edir
            // Job-u geri qoy (retry)
            $this->release(10);
        }
    }
}

// Owner token ilə lock (daha təhlükəsiz)
$lock = Cache::lock('resource:1', 30);
$token = $lock->get(); // Unikal token qaytarır

if ($token) {
    // İşi gör...
    
    // Yalnız lock-un sahibisinizsə release edin
    $lock->release();
    // və ya
    Cache::restoreLock('resource:1', $token)->release();
}

// Blocking lock
try {
    $lock = Cache::lock('resource:1', 10)->block(5);
    // 5 saniyə gözlə, lock alınanadək
    // Lock alındı, işi gör
} catch (LockTimeoutException $e) {
    // 5 saniyə ərzində lock alına bilmədi
} finally {
    $lock?->release();
}
```

### 14.7 Atomic Operations

*14.7 Atomic Operations üçün kod nümunəsi:*
```php
use Illuminate\Support\Facades\Redis;

// Atomic counter
$views = Redis::incr('article:' . $articleId . ':views');

// Atomic check-and-set with Lua
$acquired = Redis::eval(<<<'LUA'
    if redis.call('exists', KEYS[1]) == 0 then
        redis.call('setex', KEYS[1], ARGV[1], ARGV[2])
        return 1
    end
    return 0
LUA, 1, 'lock:resource', 30, 'owner_id');

// Leaderboard əməliyyatları
Redis::zadd('leaderboard:weekly', $score, "user:{$userId}");
$rank = Redis::zrevrank('leaderboard:weekly', "user:{$userId}");
$topPlayers = Redis::zrevrange('leaderboard:weekly', 0, 9, 'WITHSCORES');

// Atomic inventory management
$script = <<<'LUA'
    local stock = tonumber(redis.call('get', KEYS[1]))
    local requested = tonumber(ARGV[1])
    if stock >= requested then
        redis.call('decrby', KEYS[1], requested)
        return 1
    end
    return 0
LUA;

$success = Redis::eval($script, 1, "stock:product:{$productId}", $quantity);
if ($success) {
    // Stock azaldıldı, sifarişi davam et
} else {
    // Stock kifayət deyil
}
```

### 14.8 Broadcasting (Real-time Events)

*14.8 Broadcasting (Real-time Events) üçün kod nümunəsi:*
```php
// config/broadcasting.php
'redis' => [
    'driver'     => 'redis',
    'connection' => 'default',
],

// .env
BROADCAST_DRIVER=redis

// Event class
class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.' . $this->message->room_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'      => $this->message->id,
            'user'    => $this->message->user->name,
            'text'    => $this->message->text,
            'sent_at' => $this->message->created_at->toISOString(),
        ];
    }
}

// Event-i trigger et
event(new ChatMessageSent($message));

// JavaScript (Laravel Echo + Socket.io)
// Echo.join('chat.' + roomId)
//     .listen('ChatMessageSent', (e) => {
//         console.log(e.user + ': ' + e.text);
//     });
```

---

## 15. Redis Best Practices

### 15.1 Key Naming Convention

```
# Format: object-type:id:field
user:1000:profile
order:5000:items
cache:products:featured
session:abc123def
queue:emails:high
lock:payment:order:1000
rate:api:user:500
```

### 15.2 TTL Hər Yerdə

*15.2 TTL Hər Yerdə üçün kod nümunəsi:*
```php
// Həmişə TTL təyin edin (yaddaş sızıntısının qarşısını alır)
Redis::setex('cache:key', 3600, $value); // 1 saat
Cache::put('key', $value, now()->addHours(2));

// Heç vaxt TTL-siz cache saxlamayın (əgər forever lazım deyilsə)
```

### 15.3 KEYS Əmrindən Qaçının

*15.3 KEYS Əmrindən Qaçının üçün kod nümunəsi:*
```php
// PIS (production-da istifadə etməyin - O(N), blocking)
$keys = Redis::keys('user:*');

// YAXSI (SCAN - non-blocking, cursor-based)
$cursor = 0;
$keys = [];
do {
    [$cursor, $results] = Redis::scan($cursor, ['match' => 'user:*', 'count' => 100]);
    $keys = array_merge($keys, $results);
} while ($cursor !== 0);
```

### 15.4 Big Key-lərdən Qaçının

*15.4 Big Key-lərdən Qaçının üçün kod nümunəsi:*
```php
// PIS: Bir key-də milyonlarla element
Redis::sadd('all:users', ...range(1, 1000000));

// YAXSI: Bölün
for ($i = 0; $i < 1000; $i++) {
    $bucket = $userId % 1000;
    Redis::sadd("users:bucket:{$bucket}", $userId);
}
```

### 15.5 Connection Pooling

*15.5 Connection Pooling üçün kod nümunəsi:*
```php
// config/database.php
'redis' => [
    'client' => 'phpredis',
    'default' => [
        'host'            => env('REDIS_HOST'),
        'port'            => env('REDIS_PORT'),
        'database'        => env('REDIS_DB'),
        'read_timeout'    => 60,
        'persistent'      => true, // Persistent connection
    ],
],
```

---

## 16. Redis Security

```
# redis.conf

# Password
requirepass "çox_güclü_parol_burada"

# ACL (Redis 6+)
user default off                              # Default user-i deaktiv et
user app on >app_password ~cache:* +get +set  # Məhdud icazələr
user admin on >admin_password ~* +@all        # Admin

# Binding
bind 127.0.0.1 192.168.1.100                 # Yalnız bu IP-lərdən qəbul et

# TLS
tls-port 6380
tls-cert-file /path/to/cert.pem
tls-key-file /path/to/key.pem

# Təhlükəli əmrləri deaktiv et
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command KEYS ""
rename-command CONFIG "CONFIG_SECRET_NAME"
```

---

## 17. Redis Monitoring

*17. Redis Monitoring üçün kod nümunəsi:*
```bash
# Real-time monitoring
redis-cli MONITOR            # Bütün əmrləri real-time göstər (debug üçün)

# Statistika
redis-cli INFO               # Ümumi statistika
redis-cli INFO memory        # Yaddaş
redis-cli INFO stats         # Əməliyyat statistikası
redis-cli INFO replication   # Replikasiya statusu
redis-cli INFO clients       # Client-lər

# Slow log
SLOWLOG GET 10               # Son 10 yavaş əmr
CONFIG SET slowlog-log-slower-than 10000   # 10ms-dən yavaş olanlar

# Client list
CLIENT LIST                  # Bağlı client-lər
CLIENT GETNAME               # Client adı

# Latency monitoring
redis-cli --latency          # Ortalama latency
redis-cli --latency-history  # Latency tarixi
```

### Laravel Horizon ilə Monitoring

*Laravel Horizon ilə Monitoring üçün kod nümunəsi:*
```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
    ],
],

// Horizon dashboard: /horizon
// Metrics: Jobs per minute, runtime, throughput, wait time
```

---

## 18. İntervyu Sualları və Cavabları

### S1: Redis niyə bu qədər sürətlidir?

**Cavab:**
1. **In-memory** — bütün data RAM-da saxlanılır, disk I/O yoxdur
2. **Single-threaded** — context switching yoxdur, lock yoxdur
3. **I/O Multiplexing** — epoll/kqueue ilə minlərlə bağlantı idarə edir
4. **Efficient data structures** — C-dilində optimizasiya olunmuş strukturlar
5. **Non-blocking I/O** — asinxron network əməliyyatları
6. **Zero-copy** — data kopyalanması minimaldır

### S2: Redis single-threaded-dirsə, multi-core CPU-dan necə yararlanır?

**Cavab:**
- Redis 6.0+ I/O threading dəstəkləyir (network I/O üçün multi-thread, əmr icrası hələ single-thread)
- Bir serverdə bir neçə Redis instance çalışdıra bilərsiniz
- Redis Cluster ilə yükü bir neçə node-a paylaşa bilərsiniz
- `io-threads 4` konfiqurasiyası ilə I/O thread-ləri aktiv edə bilərsiniz

### S3: RDB və AOF arasında fərq nədir? Hansını istifadə etməliyəm?

**Cavab:**
- **RDB**: Point-in-time snapshot, kompakt, sürətli restart, amma snapshot arası data itkisi mümkündür
- **AOF**: Hər write loglanır, minimal data itkisi (1 saniyə), amma daha böyük fayl
- **Tövsiyə**: Production-da hər ikisini aktiv edin. RDB backup üçün, AOF durability üçün
- Redis 4.0+ hibrid formatı (aof-use-rdb-preamble) ən yaxşı seçimdir

### S4: Cache Stampede nədir və necə həll olunur?

**Cavab:** Çox sayda request eyni anda expire olmuş cache key-ə müraciət edir və hamısı eyni anda database-ə gedir.

***Cavab:** Çox sayda request eyni anda expire olmuş cache key-ə müraci üçün kod nümunəsi:*
```php
// Həll 1: Mutex Lock
$value = Cache::get('expensive:query');
if ($value === null) {
    $lock = Cache::lock('lock:expensive:query', 10);
    if ($lock->get()) {
        try {
            $value = DB::table('products')->complexQuery();
            Cache::put('expensive:query', $value, 3600);
        } finally {
            $lock->release();
        }
    } else {
        // Stale data qaytar və ya gözlə
        sleep(1);
        $value = Cache::get('expensive:query');
    }
}

// Həll 2: Cache::flexible (Laravel 11+ stale-while-revalidate)
$value = Cache::flexible('expensive:query', [300, 600], function () {
    return DB::table('products')->complexQuery();
});
// 300 saniyə fresh, 300-600 arası stale amma serve edir və background-da yeniləyir
```

### S5: Distributed Lock necə implement olunur?

**Cavab:**

```php
// Redis SET NX EX ilə
$lockKey = 'lock:order:' . $orderId;
$lockValue = Str::uuid()->toString(); // Unikal identifier
$ttl = 30; // 30 saniyə

// Lock al
$acquired = Redis::set($lockKey, $lockValue, 'NX', 'EX', $ttl);

if ($acquired) {
    try {
        // İşi gör
    } finally {
        // Yalnız öz lock-unu sil (Lua script ilə atomic)
        Redis::eval(<<<'LUA'
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            end
            return 0
        LUA, 1, $lockKey, $lockValue);
    }
}

// Laravel Cache::lock wrapper (eyni şeyi edir)
Cache::lock('order:' . $orderId, 30)->block(5, function () use ($orderId) {
    $this->processOrder($orderId);
});
```

### S6: Redis-i Laravel-də session store olaraq istifadə etmək nə vaxt məntiqlidir?

**Cavab:**
- **Çox serverdə (load balancer arxasında)**: File session hər serverdə fərqlidir, Redis mərkəzi session store təmin edir
- **Yüksək trafik**: Redis file system-dən sürətlidir
- **Session data paylaşmaq lazımdırsa**: Microservice-lər arası

### S7: Redis Pub/Sub ilə Redis Streams arasında fərq nədir?

**Cavab:**
| Xüsusiyyət | Pub/Sub | Streams |
|------------|---------|---------|
| Persistence | Yox | Bəli |
| Consumer Groups | Yox | Bəli |
| Message replay | Yox | Bəli |
| Acknowledgment | Yox | Bəli |
| Offline consumer | Mesaj itir | Mesaj qalır |
| İstifadə sahəsi | Real-time notification | Event sourcing, reliable messaging |

### S8: Redis Cluster-da multi-key əməliyyat necə işləyir?

**Cavab:** Multi-key əməliyyatlar (MGET, MSET, etc.) yalnız eyni slot-dakı key-lərə işləyir. Hash tag `{...}` istifadə edərək key-lərin eyni slot-a düşməsini təmin edə bilərsiniz:

***Cavab:** Multi-key əməliyyatlar (MGET, MSET, etc.) yalnız eyni slot- üçün kod nümunəsi:*
```bash
# Bu işləyər (eyni hash tag)
MGET {user:1}:name {user:1}:email {user:1}:age

# Bu işləməyə bilər (fərqli slot-lar)
MGET user:1:name user:2:name user:3:name
```

### S9: Redis-i monitoring etmək üçün nə istifadə edirsiniz?

**Cavab:**
- **Redis INFO** — built-in statistika
- **Redis SLOWLOG** — yavaş query-lər
- **Laravel Horizon** — queue monitoring dashboard
- **Redis Exporter + Prometheus + Grafana** — production monitoring
- **RedisInsight** — Redis Labs-ın GUI aləti
- **redis-cli --stat** — real-time stats
- Custom health check endpoint-ləri

### S10: Eviction policy seçərkən nəyə diqqət etməliyəm?

**Cavab:**
- Əgər Redis **yalnız cache** üçündürsə: `allkeys-lru` və ya `allkeys-lfu`
  - LRU: Son istifadə vaxtına görə (klassik)
  - LFU: İstifadə tezliyinə görə (Redis 4.0+, daha ağıllı)
- Əgər Redis **cache + persistent data** üçündürsə: `volatile-lru` (yalnız TTL olan key-ləri silir)
- Əgər bütün key-lərin eyni əhəmiyyəti varsa: `allkeys-random`
- Əgər expire olunan key-lər arasında ən qısa TTL-i olanın silinməsini istəyirsinizsə: `volatile-ttl`
- **Heç vaxt** production-da `noeviction` istifadə etməyin (default!), çünki yaddaş dolduqda write-lar rədd olunacaq

### S11: Redis-də HyperLogLog nədir və nə vaxt istifadə olunur?

**Cavab:** HyperLogLog — unikal element sayını çox az yaddaşda (12KB sabit) approximate olaraq hesablayan probabilistic data structure-dur. Dəqiqlik ~0.81% xəta ilə. Milyardlarla element üçün belə yaddaş sabittir. İstifadə: unikal ziyarətçi sayı, unikal axtarış sorğusu sayı, API endpoint-ə unikal IP sayı. `PFADD` ilə element əlavə edilir, `PFCOUNT` ilə count alınır, `PFMERGE` ilə bir neçə HyperLogLog birləşdirilir. Dəqiq sayma lazımdırsa (məsələn, billing) Set istifadə edin; approximate yetərlidirsə HyperLogLog daha effektivdir.

### S12: Redis-in Redlock alqoritmi nədir?

**Cavab:** Redlock — Redis Cluster-da distributed lock üçün alqoritmdır. Tək Redis node yetərli deyil — node restart olsa lock itirilir. Redlock: N Redis node-dan (adətən 5) ən azı N/2+1-dən eyni anda lock almağa çalışır. Çoxluq (quorum) əldə olunarsa lock alınmış sayılır. Hər node müstəqil — bir node down olsa belə sistem işləyir. Laravel-in `Cache::lock()` tək node üçündür (Redlock deyil). Kritik distributed lock-lar üçün `ronnylt/laravel-redlock` paketi və ya manuel Redlock implementasiyası lazımdır.

---

## 19. Praktik Nümunə: Redis ilə Real-time Analytics

*19. Praktik Nümunə: Redis ilə Real-time Analytics üçün kod nümunəsi:*
```php
// app/Services/AnalyticsService.php
class AnalyticsService
{
    /**
     * Səhifə baxışını qeyd et
     */
    public function trackPageView(string $page, ?int $userId = null): void
    {
        $today = now()->format('Y-m-d');
        $hour = now()->format('H');

        Redis::pipeline(function ($pipe) use ($page, $today, $hour, $userId) {
            // Ümumi baxış sayı
            $pipe->incr("stats:pageviews:{$today}");
            
            // Səhifə üzrə baxış
            $pipe->hincrby("stats:pages:{$today}", $page, 1);
            
            // Saatlıq baxış
            $pipe->hincrby("stats:hourly:{$today}", $hour, 1);
            
            // Unikal ziyarətçilər (HyperLogLog)
            if ($userId) {
                $pipe->pfadd("stats:unique:{$today}", "user:{$userId}");
            }
            
            // Ən populyar səhifələr (Sorted Set)
            $pipe->zincrby("stats:popular:{$today}", 1, $page);
            
            // TTL - 30 gün sonra sil
            $pipe->expire("stats:pageviews:{$today}", 86400 * 30);
            $pipe->expire("stats:pages:{$today}", 86400 * 30);
            $pipe->expire("stats:hourly:{$today}", 86400 * 30);
            $pipe->expire("stats:unique:{$today}", 86400 * 30);
            $pipe->expire("stats:popular:{$today}", 86400 * 30);
        });
    }

    /**
     * Günlük statistika al
     */
    public function getDailyStats(string $date): array
    {
        return [
            'total_views'    => (int) Redis::get("stats:pageviews:{$date}"),
            'unique_visitors' => (int) Redis::pfcount("stats:unique:{$date}"),
            'top_pages'      => Redis::zrevrange("stats:popular:{$date}", 0, 9, 'WITHSCORES'),
            'hourly_views'   => Redis::hgetall("stats:hourly:{$date}"),
            'page_breakdown' => Redis::hgetall("stats:pages:{$date}"),
        ];
    }

    /**
     * Online istifadəçiləri izlə
     */
    public function trackOnlineUser(int $userId): void
    {
        // Sorted set: score = timestamp
        Redis::zadd('users:online', now()->timestamp, "user:{$userId}");
        
        // 5 dəqiqədan köhnə olanları sil
        Redis::zremrangebyscore('users:online', 0, now()->subMinutes(5)->timestamp);
    }

    public function getOnlineCount(): int
    {
        return Redis::zcard('users:online');
    }

    public function getOnlineUsers(): array
    {
        return Redis::zrevrange('users:online', 0, -1);
    }
}

// Middleware-də istifadə
class TrackPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        app(AnalyticsService::class)->trackPageView(
            $request->path(),
            $request->user()?->id
        );

        if ($request->user()) {
            app(AnalyticsService::class)->trackOnlineUser($request->user()->id);
        }

        return $response;
    }
}
```

---

## 20. Praktik Nümunə: Redis ilə Shopping Cart

*20. Praktik Nümunə: Redis ilə Shopping Cart üçün kod nümunəsi:*
```php
// app/Services/CartService.php
class CartService
{
    private string $prefix = 'cart:';
    private int $ttl = 86400 * 7; // 7 gün

    private function getKey(?int $userId, ?string $sessionId): string
    {
        return $this->prefix . ($userId ? "user:{$userId}" : "session:{$sessionId}");
    }

    public function addItem(?int $userId, ?string $sessionId, int $productId, int $quantity = 1): void
    {
        $key = $this->getKey($userId, $sessionId);
        
        Redis::hincrby($key, "product:{$productId}", $quantity);
        Redis::expire($key, $this->ttl);
    }

    public function removeItem(?int $userId, ?string $sessionId, int $productId): void
    {
        $key = $this->getKey($userId, $sessionId);
        Redis::hdel($key, "product:{$productId}");
    }

    public function updateQuantity(?int $userId, ?string $sessionId, int $productId, int $quantity): void
    {
        $key = $this->getKey($userId, $sessionId);
        
        if ($quantity <= 0) {
            Redis::hdel($key, "product:{$productId}");
        } else {
            Redis::hset($key, "product:{$productId}", $quantity);
        }
        Redis::expire($key, $this->ttl);
    }

    public function getCart(?int $userId, ?string $sessionId): array
    {
        $key = $this->getKey($userId, $sessionId);
        $items = Redis::hgetall($key);

        $cart = [];
        foreach ($items as $field => $quantity) {
            $productId = (int) str_replace('product:', '', $field);
            $cart[] = [
                'product_id' => $productId,
                'quantity'   => (int) $quantity,
            ];
        }

        return $cart;
    }

    public function clearCart(?int $userId, ?string $sessionId): void
    {
        Redis::del($this->getKey($userId, $sessionId));
    }

    /**
     * Session cart-ı user cart-a birləşdir (login zamanı)
     */
    public function mergeCarts(string $sessionId, int $userId): void
    {
        $sessionKey = $this->getKey(null, $sessionId);
        $userKey = $this->getKey($userId, null);

        $sessionItems = Redis::hgetall($sessionKey);

        if (!empty($sessionItems)) {
            Redis::pipeline(function ($pipe) use ($userKey, $sessionItems, $sessionKey) {
                foreach ($sessionItems as $field => $quantity) {
                    $pipe->hincrby($userKey, $field, (int) $quantity);
                }
                $pipe->del($sessionKey);
                $pipe->expire($userKey, $this->ttl);
            });
        }
    }
}
```

Bu bələdçi Redis-in bütün əsas konseptlərini, Laravel inteqrasiyasını və real-world istifadə nümunələrini əhatə edir. İntervyuda bu mövzuları bilmək Redis haqqında dərin anlayış göstərəcək.

---

## Anti-patterns

**1. Redis-i primary DB kimi istifadə etmək**
Redis in-memory-dir — restart olduqda RDB/AOF olmadan data itirilir. Kritik data həmişə MySQL/PostgreSQL-də, Redis-də yalnız cache/session/queue saxlanmalıdır.

**2. TTL-siz key saxlamaq**
`SET key value` — TTL yoxdur, key sonsuza qədər qalır. Memory dolur, eviction policy `noeviction`-dirsə yeni yazma uğursuz olur. Hər key-ə TTL (`EX`, `PX`) mütləqdir.

**3. `KEYS *` production-da işlətmək**
`KEYS *` bütün key-ləri scan edir — O(N). 1M key-də Redis saniyələrlə bloklanır. Bunun əvəzinə `SCAN` (cursor-based, non-blocking) istifadə et.

**4. Hot key problemi**
Bir key bütün traffic-i alır (viral məhsulun cache key-i). Tək node bottleneck olur. Həll: key sharding (`product:123:shard:{0-9}`), L1 local cache, yaxud read replica.

**5. Böyük value saxlamaq**
10MB+ JSON-u bir key-də saxlamaq — serialization yavaşdır, network bandwidth itirilir. Böyük data-nı parçala, yalnız lazımi hissəni saxla (Hash ilə field-by-field).

**6. Pub/Sub-u reliable queue kimi istifadə etmək**
Redis Pub/Sub at-most-once-dır — subscriber offline olduqda mesaj itirilir. Reliable queue üçün Redis Streams (ack mexanizmi var) və ya RabbitMQ istifadə et.

**7. Lua script-də uzun əməliyyat**
Lua Redis event loop-unu bloklayır — script işləyərkən başqa sorğular gözləyir. Script minimal olmalı, DB/HTTP çağırışı içəridə olmamalıdır.

**8. Connection pool-suz istifadə**
Hər request-də yeni Redis connection açmaq — TCP handshake overhead-i, connection limit-ə çatmaq. `phpredis` persistent connection və ya connection pool istifadə et.
