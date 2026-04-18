# Redis SPOF (Single Point of Failure)

## Problem (nəyə baxırsan)
Redis aşağıdır və ya əlçatmazdır. Bütün tətbiqin donur və ya xəta verir. Niyə? Çünki Redis səssizcə çoxlu məsuliyyəti yığmışdı: cache, session store, queue broker, lock manager, rate limiter. Düşəndə hamısını özü ilə aparır — əgər bunun üçün arxitektura qurmamışdınsa, tam outage olur.

Redis uğursuz olanda simptomlar:
- Hər şey yavaş (cache miss → DB overload)
- İstifadəçilər logout olunur (session store getdi)
- Queue job-lar işləmir (broker aşağıdır)
- Rate limiter-lər fail open VƏ YA close olur (kod-dan asılı)
- Hər yerdə `Connection refused` xətaları

## Sürətli triage (ilk 5 dəqiqə)

### Redis işləyirmi?

```bash
redis-cli PING
# PONG = alive

redis-cli INFO server | head
redis-cli INFO replication
redis-cli INFO persistence
redis-cli INFO clients
```

### Cavab verirmi?

```bash
# Latency check
redis-cli --latency

# Big keys check
redis-cli --bigkeys
```

Yavaş `PING` Redis canlıdır amma aşırı yüklənib deməkdir. Tək böyük key-in işləndiyinə bax.

### Failover vəziyyəti

```bash
# Sentinel
redis-cli -p 26379 SENTINEL masters
redis-cli -p 26379 SENTINEL slaves mymaster

# Cluster
redis-cli -p 6379 CLUSTER INFO
redis-cli -p 6379 CLUSTER NODES
```

## Diaqnoz

### Redis niyə SPOF olur

Default arxitektura:
- Laravel `CACHE_DRIVER=redis`
- Laravel `SESSION_DRIVER=redis`
- Laravel `QUEUE_CONNECTION=redis`
- `Cache::lock()` Redis istifadə edir
- Rate limiter-lər Redis istifadə edir
- `Broadcasting`, `Horizon`, websocket, pub/sub kimi feature-lər = Redis

Əgər bütün bunlar eyni Redis instansiyasına yönəlibsə, tək uğursuzluq kaskad şəklində yayılır.

### Standalone vs Sentinel vs Cluster

**Standalone**: bir Redis. Sadə. HA yox. Təmiz SPOF.

**Replica + Sentinel**: bir primary, bir və ya daha çox replica. Sentinel monitor edir və uğursuzluqda replica-nı promote edir. Client-lər Sentinel-aware olmalıdır (və ya proxy istifadə etməlidir).

**Redis Cluster**: data node-lara shard olunur. Hər shard-ın replica-sı var. Self-healing topologiya. Ən mürəkkəb, ən yüksək miqyas.

**Managed Redis** (AWS ElastiCache, GCP Memorystore, Upstash, Redis Cloud): provider failover-ı idarə edir. Tək-AZ / tək-region olsa, hələ də SPOF-dur.

### Persistence variantları

- **RDB (snapshot)**: dövri dump-lar. Sürətli restart, crash-də dəqiqələrlə data itə bilər.
- **AOF (append-only file)**: hər write log olunur. Yavaş, minimal data itkisi.
- **Hər ikisi**: maksimum təhlükəsizlik, maksimum əlavə yük.

Yalnız cache üçün RDB uyğundur. Queue broker və ya session store üçün AOF güclü tövsiyə edilir.

`redis.conf`:
```
save 900 1
save 300 10
save 60 10000
appendonly yes
appendfsync everysec
```

### Failover zamanı data itkisi

Async replication ilə (default): replica bir neçə ms lag edir. Failover-da həmin ms-lik write-lər itir.

Nümunə: job Redis queue-ya dispatch olundu, replica hələ almayıb, primary ölür. Replica promote olunur. Job: yoxdur.

Kritik iş üçün yalnız Redis queue-ya güvənmə — payment job-ları üçün durable queue (SQS, persistence-li RabbitMQ) istifadə et.

### Memory eviction

```
maxmemory 4gb
maxmemory-policy allkeys-lru
```

Siyasətlər:
- `noeviction` — yaddaş dolduqda write-lər uğursuz olur. Redis queue broker-indirsə BUNU İSTİFADƏ ET (job-ların səssizcə evict olunmasını istəmirsən!).
- `allkeys-lru` — ən az istifadə olunan. Cache üçün yaxşıdır.
- `volatile-lru` — TTL-li key-lər arasında LRU.
- `allkeys-random`, `volatile-random`, `volatile-ttl`, `allkeys-lfu`, `volatile-lfu`.

**DİQQƏT**: əgər cache və queue-nu bir Redis-də `allkeys-lru` ilə qarışdırsan, eviction queue job-ları drop edə bilər. Data ilə ciddi olduqda cache vs queue vs session üçün ayrı Redis instansiyaları.

## Fix (bleeding-i dayandır)

### Redis aşağıdır, app aşağıdır

1. Redis-in həqiqətən aşağı olduğunu təsdiqlə (app-dan şəbəkə partition deyil)
2. Əgər primary ölübsə: replica-nı promote et
3. Əgər replica yoxdursa: RDB/AOF snapshot-dan restore et (data itki pəncərəsi = son snapshot)
4. App config-i yeni endpoint-ə yenilə (və ya DNS/Sentinel-in etməsini gözlə)
5. Graceful degradation: aşağıda bax

### Graceful degradation pattern-ləri

**Cache**: miss/error-də DB read-lərə geri qayıt
```php
try {
    $value = Cache::remember($key, 60, fn() => $this->slowQuery());
} catch (\Throwable $e) {
    Log::warning('Cache unavailable, fallback to DB', ['e' => $e->getMessage()]);
    $value = $this->slowQuery();
}
```

**Sessions**: cookie-yə (stateless) və ya DB-yə keç, server-tərəfi state itkisini qəbul et
```php
// config/session.php — swap at runtime
config(['session.driver' => 'cookie']);
```

**Queue**: kritik yollar üçün synchronous dispatch-ə keç
```php
if (Redis::connection()->ping() === false) {
    SendEmailJob::dispatchSync($user);
} else {
    SendEmailJob::dispatch($user);
}
```

Həmişə praktik deyil, amma feature başına qərardır.

### Blue-green Redis swap

Redis instansiyalarını dəyişmək lazım olduqda (məs., versiya upgrade, scaling):
1. Yeni Redis qaldır
2. Replication qur: yeni köhnədən replicate edir
3. Sinxron olmasını gözlə
4. Qısa müddətlə write-ləri dayandır; yenini primary-ə promote et
5. Client-ləri yenidən yönləndir
6. Köhnəni dekommisya et

Alətlər: RedisShake, Redis-Migrate, AWS ElastiCache swap.

## Əsas səbəbin analizi

İncident-dən sonra:
- Əslində nə uğursuz oldu? (OS, hardware, şəbəkə, Redis OOM, crash-ə səbəb olan komanda)
- Promote-dan əvvəl replication catch up etdimi, yoxsa data itirdik?
- Outage nə qədər sürdü? Graceful degradation təsiri qısalda bilərdimi?
- Redis-imiz həqiqətən HA-dır, yoxsa tək instansiyaya güvənirdik?

## Qarşısının alınması

- **Prod-da Redis-i standalone işlətmə.** Minimum: primary + 1 replica, Sentinel və ya ekvivalent ilə.
- Cache (ephemeral) vs queue (durable) vs session (orta-durable) üçün **ayrı Redis instansiyaları**.
- Queue/session Redis üçün AOF aktiv.
- Monitor: memory used %, clients, blocked clients, slowlog, keyspace misses.
- Alert: `used_memory > 80%`, replication lag > 10s, master_link_status != up.
- Cache üçün graceful degradation kodlanıb; queue/session üçün məhdud degradasiya qəbul et.
- Disaster recovery üçün AOF/RDB backup-ları S3-ə.

## PHP/Laravel xüsusi qeydlər

### Laravel ayrı Redis connection-ları

```php
// config/database.php
'redis' => [
    'client' => 'phpredis',

    'default' => [
        'host' => env('REDIS_CACHE_HOST', '127.0.0.1'),
        'password' => env('REDIS_CACHE_PASSWORD'),
        'port' => env('REDIS_CACHE_PORT', 6379),
        'database' => 0,
    ],

    'queue' => [
        'host' => env('REDIS_QUEUE_HOST', '127.0.0.1'),
        'password' => env('REDIS_QUEUE_PASSWORD'),
        'port' => env('REDIS_QUEUE_PORT', 6379),
        'database' => 1,
    ],

    'cache' => [
        'host' => env('REDIS_CACHE_HOST', '127.0.0.1'),
        'password' => env('REDIS_CACHE_PASSWORD'),
        'port' => env('REDIS_CACHE_PORT', 6379),
        'database' => 2,
    ],

    'session' => [
        'host' => env('REDIS_SESSION_HOST', '127.0.0.1'),
        'password' => env('REDIS_SESSION_PASSWORD'),
        'port' => env('REDIS_SESSION_PORT', 6379),
        'database' => 3,
    ],
],

'cache' => ['default' => env('CACHE_DRIVER', 'redis'), 'stores' => [
    'redis' => ['driver' => 'redis', 'connection' => 'cache'],
]],
```

İndi yalnız env dəyişənləri dəyişərək onları fərqli fiziki Redis server-lərdə host edə bilərsən.

### Laravel Sentinel dəstəyi

```php
'default' => [
    'host' => 'tcp://sentinel-1:26379?sentinel=mymaster',
    'options' => ['replication' => 'sentinel', 'service' => 'mymaster'],
],
```

(`predis/predis` client tələb edir.)

### Redis down-da Horizon graceful pause

Horizon Redis unreachable olduqda fail fast edir. Worker-lər restart edir; avtomatik degradasiya yoxdur. Ona uyğun plan et.

## Yadda saxlanmalı real komandalar

```bash
# Ping
redis-cli PING

# Info
redis-cli INFO server
redis-cli INFO replication
redis-cli INFO persistence
redis-cli INFO memory
redis-cli INFO stats
redis-cli INFO clients

# Find big keys
redis-cli --bigkeys
redis-cli --memkeys           # by memory

# Slowlog
redis-cli SLOWLOG GET 10
redis-cli SLOWLOG RESET

# Live command monitor (dev only!)
redis-cli MONITOR

# Sentinel
redis-cli -p 26379 SENTINEL masters
redis-cli -p 26379 SENTINEL get-master-addr-by-name mymaster

# Cluster
redis-cli CLUSTER INFO
redis-cli CLUSTER NODES

# Memory usage of a key
redis-cli MEMORY USAGE somekey

# Latency analysis
redis-cli --latency
redis-cli --latency-history
```

## Müsahibə bucağı

"Redis ümumi bir SPOF-dur. Bunu necə həll etmisən?"

Güclü cavab:
- "Əvvəl: problemi etiraf et. Default Laravel setup-ları cache, session, queue-nun hamısını bir Redis-ə yönəldir. Bu bütün app üçün single point of failure-dır."
- "Concern-lərin ayrılması: onu bir neçə Redis instansiyasına böldüm — cache (ephemeral, LRU eviction), queue (durable, noeviction, AOF), session (orta-durable, AOF). Fərqli reliability hədəfləri."
- "HA: minimum primary + replica, Sentinel ilə. Daha böyük miqyas üçün Redis Cluster. Cloud üçün ElastiCache Multi-AZ."
- "Graceful degradation: cache uğursuzluqları DB-yə geri qayıdır. Session-lar: itkini zərif qəbul et və ya kritik session-lar üçün DB-yə dual-write et. Queue-lar: kritik yollar durable alternativə (SQS, RabbitMQ) gedir."
- "Monitoring: memory, clients, replication lag, slow komandalar. Hamısı üçün alert-lər."

Bonus: "Rəhbərlik etdiyim bir migration: queue Redis-i cache ilə shared olmaqdan AOF-lu dedicated ElastiCache cluster-ə köçürdüm. Növbəti cache-Redis outage-dan sağ çıxdı — email notification-lar səhifə yüklənmələri yavaş olsa da işləməyə davam etdi. Bölmənin dəyəri budur."
