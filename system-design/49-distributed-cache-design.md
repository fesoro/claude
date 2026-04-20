# Distributed Cache Design

## Nədir? (What is it?)

**Distributed Cache** — bir neçə node-a yayılmış in-memory key-value storage. Tək Redis/Memcached
instansı müəyyən həddə qədər işləyir, amma bir node limitləri var: RAM (məsələn 256GB), CPU core
sayı, NIC bandwidth (10-25 Gbps), connection limiti. Yüksək trafikli sistemlərdə (Facebook, Twitter,
Netflix) milyardlarla key, saniyədə milyonlarla əməliyyat lazımdır — horizontal scale və high
availability üçün distributed cache dizayn edilməlidir.

Bu fayl file 03 (caching strategies) ilə fərqli məsələyə baxır: strategiya yox, **cache sistemini
sıfırdan necə qurmaq** (sharding, replication, failover, hot key).

## Tələblər (Requirements)

**Functional:**
- `GET key`, `SET key value [TTL]`, `DEL key`
- Atomic operations: `INCR`, `CAS` (compare-and-swap)
- Bulk: `MGET`, `MSET`
- Data types (Redis): string, hash, list, set, sorted set, stream

**Non-functional:**
- **Latency:** p99 < 1 ms intra-DC
- **Throughput:** 100k+ ops/sec per node (Redis single-thread ~80-100k, pipeline ilə 500k+)
- **Scale:** milyardlarla key, petabayt RAM cluster səviyyəsində
- **Availability:** 99.99%+, node failure zamanı read/write davam etsin
- **Durability:** optional (cache kimi, amma AOF/RDB mövcuddur)
- **Consistency:** eventual (write-master, replica-read ola bilər)

## High-Level Design

```
         +----------+   +----------+   +----------+
Clients  |  App 1   |   |  App 2   |   |  App N   |
         +----------+   +----------+   +----------+
              |              |              |
              +------+-------+------+-------+
                     |              |
               Client-side       Proxy
               hashing           (Twemproxy/Envoy)
                     |              |
         +-----------+-------+-----------+
         v           v       v           v
    +--------+  +--------+  +--------+  +--------+
    | Shard0 |  | Shard1 |  | Shard2 |  | ShardN |
    |Master  |  |Master  |  |Master  |  |Master  |
    |  +Rep  |  |  +Rep  |  |  +Rep  |  |  +Rep  |
    +--------+  +--------+  +--------+  +--------+
         \         |           |          /
          \       Gossip / Sentinel      /
           +----> Failure detection <---+
```

## Komponentlər (Components)

### 1. Node Internals — Hash Table + LRU

Hər node-un əsası: **hash table (O(1) lookup) + doubly linked list (LRU order)**. Entry həm
hash bucket-dadır, həm LRU list-də. `GET` zamanı entry head-ə daşınır (MRU). Memory full
olduqda tail-dən evict olur.

```
Hash Table:                  LRU Doubly Linked List:
bucket[0] -> entry_a         head <-> A <-> B <-> C <-> D <-> tail
bucket[1] -> entry_c
bucket[2] -> entry_b         Most-recent          Least-recent
```

Hər əməliyyat O(1): lookup hash ilə, list-dən çıxarmaq/əlavə etmək pointer swap.

### 2. Network Layer

- TCP persistent connection + binary protocol (RESP Redis, ASCII/binary Memcached)
- Connection pooling, pipelining (batch), non-blocking I/O event loop

### 3. Storage Engine

- **Redis:** hər data type üçün xüsusi struct (dict, ziplist, quicklist, skiplist), **jemalloc**
  allocator — fragmentation azdır.
- **Memcached:** **slab allocator** — fixed-size chunk-lar, fragmentation yoxdur, multi-threaded.

## Eviction Policies

RAM dolduqda hansı key silinir? Redis `maxmemory-policy` variantları:

1. **noeviction** — yazma rədd edilir (error qaytarır)
2. **allkeys-lru** / **volatile-lru** — bütün / yalnız TTL-li key-lərdən LRU
3. **allkeys-lfu** / **volatile-lfu** — LFU (Redis 4.0+)
4. **allkeys-random** — təsadüfi
5. **volatile-ttl** — ən qısa TTL-li silinir

**LRU vs LFU:** LRU recency, LFU frequency. Səhifələnmiş scan LRU-nu pozur (yeni görünən
köhnə populyar key-i evict edir). LFU daha yaxşı hit rate verir amma bahalıdır.

**TinyLFU (Caffeine):** Count-Min Sketch + admission policy. W-TinyLFU scan-resistant.
**ARC (IBM):** LRU + LFU-nu dinamik balanslayır (T1/T2 + ghost B1/B2).
**Redis approximate LRU:** 5 sample götürür (tam LRU list bahadır) — `maxmemory-samples`.

## TTL Handling (Expiration)

- **Lazy expiration** — `GET key` vaxtı expired olubsa silinir və miss qaytarılır. CPU yüngül,
  amma heç oxunmayan expired key-lər RAM tutur.
- **Active expiration** — background thread/cycle periodik olaraq sample alıb expired-ləri silir.
  Redis saniyədə 10 dəfə 20 key sample alır, >25% expired varsa təkrarlayır.

Hər ikisi birlikdə işləyir.

## Consistent Hashing (Hash Ring)

Sadəcə `hash(key) % N` istifadə etsək, node əlavə/silinəndə **bütün** key-lərin 1/N-i yox,
demək olar hamısı yerini dəyişir (re-hash). Consistent hashing bunu həll edir.

### Hash Ring

```
Hash space: [0, 2^32)
                  0 / 2^32
              N3    |    N1
                \   |   /
        270° ----+--+--+---- 90°
                /   |   \
              N2    |    N4
                   180°

Node-lər ring-də hash(node_id)-ə görə yerləşir.
Key clockwise ən yaxın node-a gedir.
```

N1 getdikdə yalnız N1-in range-i digər node-lara paylanır — digər key-lər toxunulmaz qalır.

### Virtual Nodes (vnodes)

Bir fiziki node üçün 100-200 virtual node ring-də — uniform distribution verir (əks halda random
yerləşmədə bəzi node-lar çox key alır). Node silindikdə yük bütün qalan node-lara paylanır.

### Jump Consistent Hash (Google, Lamping & Veach 2014)

`jump_hash(key, num_buckets)` — vnode array saxlamadan O(log N), memory-siz. Sürətli, amma
arbitrary remove yoxdur (yalnız append). Vitess, Sparkey istifadə edir.

### Memcached (ketama) vs Redis Cluster (hash slots)

**Memcached** — server tərəfində cluster-awareness yoxdur. Client (libmemcached ketama, phpredis)
consistent hash ilə shard seçir. Sadə, amma client-lər eyni ring-i saxlamalıdır.

**Redis Cluster** — 16384 fixed hash slot. `slot = CRC16(key) mod 16384`. Hər slot bir master-ə
təyin olunur. Node əlavə edildikdə slot-lar manual/auto migrate olunur (`CLUSTER RESHARD`).
Client MOVED/ASK redirect alır, slot→node map-i cache edir.

```
Slots 0..5460     -> Master A (+ Replica A')
Slots 5461..10922 -> Master B (+ Replica B')
Slots 10923..16383-> Master C (+ Replica C')
```

## Replication

### Master-Replica (Redis)

Hər shard üçün 1 master + 1-2 replica. Async replication — master write-dən sonra replica-ya
stream göndərir. Read replica-dan, write master-ə. Gecikmə varsa stale read mümkündür.

### Quorum (Dynamo-style, Riak, Cassandra)

`N` replica, `W` write quorum, `R` read quorum. `W + R > N` olduqda strong consistency. Redis
Enterprise aktiv-aktiv CRDT istifadə edir.

### Replication Topology

```
      Shard A                  Shard B
   +-----------+            +-----------+
   | Master A  |--async---> | Replica A'|   (A' is in different rack/AZ)
   +-----------+            +-----------+
        |
        +----- client writes
```

## Failure Handling

### Redis Sentinel (non-cluster HA)

3+ Sentinel prosesi master-i ping edir. Quorum master-i down elan edərsə replica-nı yeni master
etmək üçün seçki keçirir (Raft-a bənzər). Client yeni master-i Sentinel-dən soruşur.

### Redis Cluster Gossip

Hər node digərləri ilə gossip (PING/PONG) edir. `cluster-node-timeout` müddətində cavab yoxdursa
`PFAIL`, çoxluq razı olsa `FAIL`. Replica avtomatik failover — yeni master olur, slot-ları alır.

### Split-brain Prevention

Minimum master quorum — əgər node öz master quorum-unu görmürsə write-ı rədd edir
(`cluster-require-full-coverage yes`).

## Client-side vs Proxy-based Sharding

**Client-side** (phpredis cluster, Jedis, lettuce): client slot map saxlayır, birbaşa node-a
yazır. Latency minimal, amma hər dildə SDK lazımdır, upgrade çətin.

**Proxy-based** (Twemproxy, Envoy Redis filter, Codis): client proxy-yə yazır, proxy shard seçir.
Multi-language asan, amma əlavə 0.1-0.3 ms + proxy SPOF (LB arxasında N proxy lazımdır).

```
Client -> Envoy -> Redis shard   (proxy)
Client --------> Redis shard     (client-side)
```

## Thundering Herd / Cache Stampede

Populyar key expire olanda 1000 request eyni anda miss edir, hamısı DB-yə gedir, DB çökür.

**Həllər:**

1. **Mutex / Single-flight** — yalnız bir request DB-ni sorğulayır, digərləri gözləyir.
   Laravel `Cache::lock()` və ya `Redis::set(key, 1, 'NX', 'EX', 10)`.
2. **Probabilistic Early Expiration (XFetch)** — TTL yaxınlaşanda probabilistically bir request
   yeniləyir, digərləri köhnə dəyəri alır.
   `if (rand() < exp(-delta * beta * log(rand()))) refresh();`
3. **Stale-While-Revalidate** — TTL keçəndən sonra qısa müddət stale qaytar, arxa planda yenilə.
4. **Request Coalescing** — eyni key üçün gələn parallel request-lər bir promise-a bağlanır.

## Hot Key Problem

Bir key (məs `product:iphone16`) saniyədə 1M sorğu alır — bir shard yanır.

**Həllər:**

1. **Read replica scaling** — hot shard-a 5+ replica əlavə et, read spread
2. **Local L1 cache** — app server APCu / Caffeine 1-5 sn saxlayır, 95% trafik Redis-ə getmir
3. **Key splitting** — `product:iphone16:v1..v10` 10 copy, random oxu
4. **Probabilistic dedup** — eyni key üçün in-flight request-ləri birləşdir

## Cache Penetration & Avalanche

**Penetration** — mövcud olmayan key-lər üçün sorğular (attacker behavior). DB-yə hər dəfə gedir.
Həll: **Bloom filter** app səviyyəsində — key DB-də yoxsa Bloom false deyir, request DB-yə
getmir.

**Avalanche** — eyni anda çoxlu key expire olur (məs bütün session-lar eyni vaxt set edildi).
Həll: **randomized TTL** — `TTL = base + rand(0, jitter)`.

**Breakdown** — tək, populyar key expire. Həll: thundering herd texnikaları.

## Multi-tier Cache

```
+-------+     +---------+     +----------------+     +----------+
|Browser|-->  |CDN Edge |-->  |L1: App APCu    |-->  |L2: Redis |-->DB
|       |     |(static) |     |(10ms lifetime) |     |Cluster   |
+-------+     +---------+     +----------------+     +----------+
```

- **L1 (APCu, Caffeine)** — process-local, sub-microsecond, amma inconsistent (hər worker ayrı)
- **L2 (Redis cluster)** — shared, milliseconds, consistent
- **L3 (DB with query cache)** — source of truth

L1 write zamanı invalidation çətindir — qısa TTL (2-10 sn) ən sadə həll, və ya pub/sub ilə bütün
app node-lara invalidate mesajı.

## Laravel Misalı

### Redis Cluster config

```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'clusters' => [
        'default' => [
            [
                'host' => 'redis-node-1',
                'port' => 6379,
                'database' => 0,
            ],
            ['host' => 'redis-node-2', 'port' => 6379],
            ['host' => 'redis-node-3', 'port' => 6379],
        ],
    ],
    'options' => [
        'cluster' => 'redis', // native Redis cluster protocol
        'prefix'  => env('REDIS_PREFIX', 'app:'),
    ],
],
```

### Client-side Consistent Hashing (Memcached style)

```php
<?php

class ConsistentHashRing
{
    private array $ring = [];      // position => node
    private array $sorted = [];
    private int $replicas = 150;   // vnodes per physical node

    public function addNode(string $node): void
    {
        for ($i = 0; $i < $this->replicas; $i++) {
            $pos = crc32($node . '#' . $i);
            $this->ring[$pos] = $node;
        }
        ksort($this->ring);
        $this->sorted = array_keys($this->ring);
    }

    public function getNode(string $key): string
    {
        $hash = crc32($key);
        $lo = 0; $hi = count($this->sorted) - 1;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($this->sorted[$mid] < $hash) { $lo = $mid + 1; }
            else { $hi = $mid - 1; }
        }
        $idx = $lo % count($this->sorted); // wrap around
        return $this->ring[$this->sorted[$idx]];
    }
}

$ring = new ConsistentHashRing();
$ring->addNode('cache-1:11211');
$ring->addNode('cache-2:11211');
$ring->addNode('cache-3:11211');
$node = $ring->getNode('user:42'); // -> cache-2:11211
```

### Cache Stampede Protection

```php
use Illuminate\Support\Facades\Cache;

public function getProduct(int $id): Product
{
    return Cache::remember("product:$id", 600, function () use ($id) {
        // Only one request executes this thanks to atomic lock
        return Cache::lock("lock:product:$id", 10)->block(5, function () use ($id) {
            return Product::findOrFail($id);
        });
    });
}
```

### Horizon + Redlock

Horizon worker-lər uzun job üçün Redis distributed lock (Redlock algoritmi 5 master quorum ilə):

```php
$lock = Cache::lock('process-invoice:'.$invoiceId, 60);
if ($lock->get()) {
    try { $this->process($invoiceId); }
    finally { $lock->release(); }
}
```

### phpredis vs Predis

- **phpredis** — C extension, 2-5x sürətli, connection persistence, cluster native
- **Predis** — pure PHP, install asan, debug/development üçün yaxşı, production-da phpredis tövsiyə

## Interview Sualları (Q&A)

**S1: Nə üçün `hash(key) % N` pisdir?**
C: Node əlavə/silinəndə N dəyişir, demək olar bütün key-lər yenidən map olur — cache kütləvi
invalidate olur. Consistent hashing yalnız 1/N key-i remap edir.

**S2: Redis Cluster 16384 slot niyə seçdi?**
C: 16384 = 2KB bitmap per node (cluster bus-da node-lar öz slot-larını bildirir). 65536 olsaydı
8KB, bandwidth israf olardı. 16384 bandwidth və balance optimumudur.

**S3: Sentinel vs Cluster fərqi nədir?**
C: Sentinel tək-master setup-da HA (failover), sharding yoxdur. Cluster həm sharding, həm
replication, həm failover. Multi-key əməliyyatı (`MGET`, transaction) Cluster-də yalnız eyni
slot-da olan key-lərdə işləyir (hash tag `{user123}:profile`).

**S4: Cache stampede-ni necə həll edərsiz?**
C: 1) `Cache::lock` single-flight — bir request DB-yə gedir, digərləri gözləyir. 2) Probabilistic
early expiration (XFetch). 3) Stale-while-revalidate — expired-dən sonra 10 sn stale qaytar,
background-da refresh.

**S5: Hot key problemini necə aşkar edirsiniz?**
C: `redis-cli --hotkeys` (LFU tələb olunur), application metrics (Prometheus), `MONITOR`
(production-da ehtiyatla). Həll: L1 local cache, read replica, key splitting (N copy), CDN.

**S6: Memcached vs Redis hansı halda seçilir?**
C: **Memcached** — sadə string cache, multi-threaded throughput, fragmentation az. **Redis** —
rich data types, persistence, pub/sub, Lua, transaction, cluster mode. Modern sistemlər üçün
çox vaxt Redis.

**S7: Write-heavy workload-da cache ilə consistency-ni necə saxlayarsız?**
C: Write-through + cache invalidation on write (write-behind yox). Versioned cache key
(`user:42:v17`) — yeni versiya yazılır, köhnə TTL ilə ölür. CDC (Debezium) ilə DB→cache
streaming də mümkündür.

**S8: Redis cluster node down olsa nə baş verir?**
C: Gossip `cluster-node-timeout` (default 15s) müddətində FAIL markalayır. Replica-lar arasında
config epoch ən böyük olan qalib gəlir, yeni master olunur, slot-ları götürür. Client MOVED
redirect alır, map yeniləyir. Replica yoxdursa və `cluster-require-full-coverage yes` isə yazma
fail olur.

## Best Practices

- **Hər key-ə TTL qoy** (jitter ilə randomize et, avalanche-ın qarşısını al)
- **Prefix/namespace** (`app:users:42`) — multi-tenant ya env ayrılığı
- **Hash tag** (`{user:42}:sessions`) multi-key əməliyyatları eyni slot-a qoy
- **Connection pooling** — persistent connection, hər request-də re-connect etmə
- **Pipelining** — batch əməliyyatlar üçün RTT-ni azalt
- **Monitoring** — hit ratio (>90%), latency p99, memory, evicted keys, key count
- **Capacity** — 75% RAM-da qal, `maxmemory` + `maxmemory-policy` konfiqurasiyası
- **Security** — AUTH, TLS, VPC isolation, ACL (Redis 6+)
- **L1 + L2 hybrid** — kritik hot path-larda APCu + Redis
- **Bloom filter penetration üçün** — böyük açıq API-lər üçün DB-ni qoru
- **phpredis native cluster** — proxy overhead-i azalt
- **Graceful degrade** — cache down olsa app DB-yə keçsin, circuit breaker ilə qoru
- **Key size limit** — value <100 KB, böyük obyekt üçün S3, cache-də yalnız pointer
