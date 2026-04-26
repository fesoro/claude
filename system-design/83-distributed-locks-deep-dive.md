# Distributed Locks Deep Dive (Lead)

Distributed lock — bir neçə node/instance arasında **yalnız birinin** hansısa kritik əməliyyatı icra etməsini təmin edən mexanizm. Single-machine mutex-dən fərqli olaraq, network, clock drift və failure-ları nəzərə almalıdır. File 25 ümumi distributed systems mövzusunda locks-a qısa toxunur — bu fayl dərin baxışdır: Redlock tənqidi, ZK/etcd, fencing tokens.


## Niyə Vacibdir

'Redis SETNX ilə lock' görünüşcə sadədir; Redlock-un Martin Kleppmann tənqidi isə distributed lock-ların nə qədər çətin olduğunu göstərir. Fencing token olmadan process pause lock-ı invalidate edə bilər. Distributed lock hər koordinasiya probleminin arxasında durur.

## Niyə lazımdır? (Why need distributed lock)

Tək bir node-un icra etməli olduğu işlər:

- **Leader election** — cluster-də yalnız bir leader cron job işlədir, digər replica standby
- **Scheduled job dedup** — 10 app server var, `SendDailyInvoices` job-u yalnız biri işlətməlidir
- **Resource allocation** — shard-based worker-lar, hər shard üçün bir consumer
- **Idempotency enforcement** — eyni `order_id` üzrə paralel handler işə düşməsin
- **Critical section** — shared state-ə (file, DB row, external API) tək access

```
Without lock:                       With lock:
Worker-1 ---> |                     Worker-1 -->[lock]--> WORK
Worker-2 ---> | CHARGE CARD         Worker-2 -->[blocked]
Worker-3 ---> |  (3x charged!)      Worker-3 -->[blocked]
```

## Lock properties (Tələb olunan xüsusiyyətlər)

1. **Mutual exclusion** — eyni anda yalnız bir client sahib olur
2. **Deadlock-free** — client crash edərsə, lock avtomatik açılır (TTL, session)
3. **Fault-tolerant** — lock servisinin bir node-u ölsə, sistem işləyir
4. **Fencing** — gecikmiş (stale) client shared state-i korlaya bilməsin
5. **Liveness** — sonunda kimsə lock-u alır (starvation yoxdur)

## Single-node Redis lock (Sadə yanaşma)

### SETNX + TTL pattern

```bash
# Atomic acquire: set if not exists, with TTL
SET lock:order:42 "owner-uuid-xyz" NX PX 30000
# NX = only if Not eXists
# PX 30000 = expire in 30 seconds
```

**Niyə TTL vacib?** Client crash edərsə, lock sonsuza qədər qalmasın.

**Niyə unique value?** Release zamanı "bu lock mənimdir?" yoxlamaq üçün.

### Release with Lua (atomic check-and-delete)

```lua
-- release.lua
if redis.call("GET", KEYS[1]) == ARGV[1] then
    return redis.call("DEL", KEYS[1])
else
    return 0
end
```

Niyə Lua lazımdır? `GET` + `DEL` iki addım olsa, TTL-dən sonra başqa birinin lock-unu yanlışlıqla silmək olar. Race condition:

```
T=0   Client-A: GET lock  -> "A"
T=1   Client-A: GC pause 30s
T=31  Lock TTL expired
T=32  Client-B: SET lock "B" NX PX 30000  -> ok (B owns)
T=33  Client-A: DEL lock  -> DELETED B'S LOCK!
```

### Problem: Redis failover

Master-replica setup-da:

```
T=0  Client-A -> SET lock ON MASTER  -> ok
T=1  MASTER crashes BEFORE replicating to replica
T=2  Replica promoted to master (lock not there!)
T=3  Client-B -> SET lock ON NEW MASTER -> ok
     (A və B eyni anda lock-u bilir -> mutex pozuldu)
```

## Redlock algoritmi (Antirez)

Solution: **N** müstəqil Redis master (şərti 5). Client `N/2+1` (majority) üzərində acquire edir.

### Addımlar

1. Client hazırkı vaxtı götürür: `T_start`
2. Bütün N master-ə eyni `key` və unique `value` ilə `SET NX PX ttl` göndərir (qısa timeout)
3. Neçə master-də uğurlu oldu? Əgər `>= N/2+1` VƏ `elapsed < ttl` -> lock aldı
4. Əks halda: bütün master-lərdə `DEL` edib buraxır
5. Effective lock time = `ttl - elapsed - clock_drift_margin`

```
   Redis-1   Redis-2   Redis-3   Redis-4   Redis-5
      |         |         |         |         |
  A->OK    A->OK    A->FAIL   A->OK    A->OK    (4/5 majority)
                                                  A holds lock
```

**Intended use:** müstəqil Redis master-lər arasında "mostly correct" lock.

## Martin Kleppmann'ın Redlock tənqidi

Kleppmann ("How to do distributed locking") göstərdi ki, Redlock **safety-critical** işlər üçün təhlükəsiz deyil.

### 1) Clock drift problemi

Redlock fərz edir ki, bütün node-lardakı saat "bounded drift" ilə axır. Lakin:

- NTP servis saat-ı birdən 5s irəli/geri çəkə bilər
- VM migration, host-un CPU steal-i pauses yaradır
- Saat korlanarsa, TTL yanlış hesablanır -> iki client eyni anda sahib olur

### 2) GC pause / process freeze

Ən təhlükəli ssenari:

```
T=0   Client-A: acquire lock, TTL=30s
T=1   Client-A: start writing to shared storage
T=2   Client-A: STOP-THE-WORLD GC pause (JVM) / OS freeze (40s)
T=32  Lock TTL expired in Redis
T=33  Client-B: acquire lock, starts writing
T=42  Client-A: wakes up, THINKS it still holds lock, writes
      -> CORRUPTED STATE (A və B eyni anda yazdı)
```

Redlock (və ümumi TTL-based locks) bunu həll edə bilmir. **Həll: fencing tokens.**

## Fencing tokens (Qoruyucu nömrələr)

Hər lock acquisition **monotonic increasing ID** alır. Client bu token-i shared storage-a göndərir. Storage qeyd edir "ən son görünən token" və ondan **kiçik** token-li yazıları rədd edir.

### ASCII ssenarisi

```
Lock service issues tokens: 1, 2, 3, ...

T=0   Client-A: acquire -> token=33
T=1   Client-A: GC pause
T=32  Client-A's lock expired
T=33  Client-B: acquire -> token=34
T=34  Client-B: write(data, token=34) -> storage stores seen=34 OK
T=40  Client-A: wakes up, tries write(data, token=33)
      Storage: 33 < 34 -> REJECT
      Safe!
```

```
Client-A (token=33) ----GC pause----....---write(33) -> REJECT
                    ^                        ^
                    acquired                 stale token

Client-B (token=34)            -------write(34) -> ACCEPT
```

**Şərt:** Storage fencing token-ı anlamalıdır. Çox sistem bunu built-in dəstəkləmir — app səviyyəsində qeyd etməlisən.

## ZooKeeper-based locks

ZooKeeper **CP** sistem (Raft-oxşar ZAB consensus). Locks üçün standart resept:

1. Client `ephemeral sequential` znode yaradır: `/locks/resource/lock-0000000042`
2. Children siyahısını alır; əgər onun sequence ən kiçikdirsə -> **lock onun**
3. Əks halda ondan əvvəlki znode-a `watch` qoyur, silinəndə yenidən yoxlayır
4. Client disconnect olsa, session ölür -> ephemeral znode silinir -> lock avtomatik release

### Üstünlüklər

- **Sequence number = natural fencing token** (monotonic, ZK-da unique)
- **No TTL needed** — session heartbeat ilə idarə olunur
- **CP guarantees** — ZAB quorum writes

```
/locks/invoice-job/
  ├── lock-0000000100  <- Client-A (HOLDS LOCK, smallest)
  ├── lock-0000000101  <- Client-B (watches 100)
  └── lock-0000000102  <- Client-C (watches 101)
```

## etcd locks

etcd **Raft** əsaslı CP key-value store.

- **Lease** mexanizmi: client TTL ilə lease yaradır, `KeepAlive` ilə yeniləyir
- Lock key lease-ə bağlanır — lease bitsə, key avtomatik silinir
- `etcdctl lock <name>` — built-in lock komandası
- **Revision number** = fencing token (monotonic per-key)

```bash
# CLI nümunəsi
etcdctl lock /locks/cron-daily-invoices -- ./run_daily_job.sh
```

PHP-də etcd client (`aternos/etcd-php`, `friendsofphp/etcd`) revision-u oxuyub fencing kimi istifadə edə bilərik.

## Consul və Chubby

- **Consul** — Raft + session TTL, `session` acquire/release. ZK-ya oxşar.
- **Google Chubby** — paper-ında təsvir olunan coarse-grained lock service; ZK/etcd-nin inspirasiyası. "Sequencer" = fencing token.

## Comparison: Redlock vs ZK/etcd

| Xüsusiyyət | Redlock | ZooKeeper / etcd |
|---|---|---|
| Consistency | AP-leaning | CP (Raft/ZAB) |
| Safety | Clock assumptions | Strong (quorum-based) |
| Fencing tokens | Built-in yoxdur | Var (sequence/revision) |
| Performance | Sürətli | Daha yavaş (consensus) |
| Complexity | Orta (5 Redis) | Daha yüksək |
| Use case | Cache, non-critical | Financial, critical |

**Qayda:** Safety-critical (para, inventory)? -> CP sistem (ZK/etcd). Best-effort mutex? -> Redis lock kifayətdir.

## Lease management (İcarə idarəsi)

Long-running işlər üçün lock TTL-i uzatmalısan:

```
acquire(TTL=30s)
loop every 10s:
    extend(TTL=30s)   <- renewal
    if extend FAILED: ABORT WORK (lock lost)
```

**Əsas nüans:** renewal uğursuz olsa, işi dərhal dayandır — başqası artıq lock götürmüş ola bilər.

## Lock granularity (Granularlıq)

- **Coarse** — "bütün orders table" lock; az contention, amma az parallelism
- **Fine** — `lock:order:{id}` per-row; çox parallel, amma overhead artır

Seçim: workload-un hot-key paylanmasına baxın. Key few-hot-many-cold olsa, fine per-key lock + outer coarse lock birləşmə.

## Common patterns

### 1) Leader election

```
Bütün instance-lər try-acquire("leader:cron")
- Uğurlu olan -> leader; cron job-ları işlədir
- Digərləri standby; lock released olsa, yenidən yarışır
```

### 2) Resource allocation (shard-per-worker)

```
10 shard, 3 worker
Worker-N -> acquire("shard:N")
Shard sahibi olduğu worker-dən başqa kimsə işləməz
```

### 3) Idempotency enforcement

```
webhook gəldi, entity_id=42
acquire("webhook:entity:42", TTL=5s)
if NOT acquired -> duplicate, skip
process
release
```

## Data Model

```
lock {
    key          string       // "shard:7"
    owner        uuid         // client instance ID
    acquired_at  timestamp
    lease_until  timestamp
    fence_token  bigint       // monotonic
    metadata     json         // host, pid, job_name
}
```

## Laravel nümunələri

### Cache::lock (Redis)

```php
use Illuminate\Support\Facades\Cache;

// Block and wait up to 5 seconds to acquire, lock held 10s
Cache::lock('send-daily-invoices', 10)->block(5, function () {
    InvoiceSender::sendAll();
});

// Non-blocking try
$lock = Cache::lock('order:42', 30);
if ($lock->get()) {
    try {
        processOrder(42);
    } finally {
        $lock->release();
    }
} else {
    Log::info('order 42 already being processed');
}
```

### Atomic release with Lua

```php
use Illuminate\Support\Facades\Redis;

$key = 'lock:order:42';
$token = Str::uuid()->toString();

// Acquire
$ok = Redis::set($key, $token, 'NX', 'PX', 30000);
if (!$ok) {
    throw new LockException('busy');
}

try {
    // critical work
    processOrder(42);
} finally {
    // Atomic check-and-delete
    $lua = <<<LUA
        if redis.call("GET", KEYS[1]) == ARGV[1] then
            return redis.call("DEL", KEYS[1])
        else
            return 0
        end
    LUA;
    Redis::eval($lua, 1, $key, $token);
}
```

### Fencing token with DB

```php
// Lock issues monotonic token via DB sequence
$token = DB::selectOne("SELECT nextval('lock_fence_seq') as t")->t;
$lock = Cache::lock("order:42", 30);
$lock->get();

// Pass token to downstream writes
DB::update(
    'UPDATE orders SET status=?, last_fence=? WHERE id=? AND last_fence < ?',
    ['paid', $token, 42, $token]
);
// Row-level check: stale token rejected
```

### etcd / ZK-based for critical

Financial işlər üçün `aternos/etcd-php` və ya ZK client (`nmred/kafka-php` ekosistemində) və revision/sequence-i fencing kimi istifadə et.

## Timeouts və circuit breaker

- **Acquisition timeout** — `block(5, ...)`, 5s-dən çox gözləmə
- **Backoff** — fail olsa, exponential + jitter retry
- **Abandon** — retry limit-i aşıldısa, job-u ölü məktub queue-ya at
- **Metric** — `lock_acquire_fail_total` alarmla bağla

## Praktik Baxış

1. Həmişə TTL qoy — client crash olsa da lock açılsın
2. Unique owner value istifadə et və release-də Lua ilə yoxla
3. Renewal lazım olsa, renewal fail -> işi dayandır
4. **Safety-critical**? Redis tək source olma — ZK/etcd istifadə et
5. Fencing token-i shared state-ə ötür (DB, storage check etsin)
6. Lock scope mümkün qədər dar (fine-grained), amma overhead-i nəzərə al
7. Acquisition timeout qoy — sonsuz block etmə
8. Monitor et: hold duration, contention rate, renewal failures
9. Long-held locks (dəqiqələr) — anti-pattern, iş həcmini böl
10. Test: chaos (Redis master-i öldür, GC simulate et) və failure paths

## Praktik Tapşırıqlar

**Q1: Niyə sadəcə `SET key value NX PX 30000` kifayət deyil?**
A: Tək Redis node-u SPOF-dur. Master ölüb replica promote olsa, yenilənməmiş lock itir — iki client eyni lock-u "sahib" olar. Həmçinin GC pause / clock drift olsa, mutex pozula bilər. Release zamanı `GET+DEL` race condition yarada bilər — Lua ilə atomic et.

**Q2: Redlock nə zaman uyğundur, nə zaman yox?**
A: **Uyğun:** best-effort / non-safety-critical lock (cache invalidation coordination, non-money işlər). **Uyğun deyil:** financial transactions, inventory decrement, unique ID generation. Kleppmann-ın tənqidi: clock və pause assumption-ları safety-critical üçün yararsızdır. Fencing tokens yoxdur.

**Q3: Fencing token nədir, niyə vacibdir?**
A: Hər lock acquisition alan monotonic increasing ID. Client bu token-i shared storage-a yazanda göndərir; storage `seen_token`-ı saxlayır və daha kiçik token-li yazıları rədd edir. GC pause-dan sonra stale client yenidən yazmağa çalışsa, token kiçik olacaq və rədd ediləcək. TTL-lərin kifayət etmədiyi ssenariləri qoruyur.

**Q4: ZooKeeper lock nə üçün CP qarantiyası verir?**
A: ZK ZAB consensus-dan istifadə edir — yazı yalnız quorum-ə replicated olandan sonra commit olur. Lock = ephemeral sequential znode; ən kiçik sequence sahib olur. Session client-ə bağlıdır — disconnect -> znode silinir. Sequence monotonic -> təbii fencing. Redlock-dan fərqli olaraq, clock assumption yoxdur.

**Q5: etcd lease-based lock necə işləyir?**
A: Client `Grant(TTL)` ilə lease yaradır, bu lease-ə key bağlayır (`Put key val --lease=id`). `KeepAlive` ilə lease-i renew edir. Lease bitsə, bütün bağlı key-lər avtomatik silinir. Revision number per-key monotonic artır və fencing token rolu oynayır. Raft altında olduğu üçün quorum writes gedir — CP.

**Q6: Leader election-u necə həyata keçirərsən?**
A: ZK/etcd istifadə et: bütün instance eyni lock key-i acquire etməyə çalışır; uğurlu olan leader olur, cron/scheduler işlədir. Leader session-ı bitsə (heartbeat itkisi), başqası yeni leader olur. Redis Cache::lock-la sadə setup da olar, amma split-brain riski var. Critical üçün ZK/etcd.

**Q7: Long-running iş üçün lock-u necə idarə edirsən?**
A: Lock-u kiçik TTL (məs 30s) ilə al və periodic (10s-də bir) renewal et. Renewal fail olsa -> iş dərhal dayansın, çünki başqası lock-u götürmüş ola bilər. İdeal: iş özü də idempotent olsun və fencing token yaz. Yaxşı alternativ: işi check-pointing ilə xırda batch-lara böl, hər batch öz lock-unu götürsün.

**Q8: Anti-pattern-lər hansılardır?**
A: (1) TTL-siz lock — crash zamanı əbədi bağlı. (2) GET+DEL non-atomic — race. (3) Dəqiqələrlə lock saxlamaq — throughput öldürür. (4) Tək Redis-i financial operations üçün source of truth etmək — failover itkisi. (5) Fencing token-i app-da generasiya edib shared state-də check etməmək. (6) Renewal fail-ı ignore etmək. (7) Unlimited retry + backoff-suz spin — thundering herd.

## Laxlaşdırma (Summary)

- Single-node Redis lock sadədir, amma failover/GC pause risklidir
- Redlock Redis ekosistemində ümumiləşdirməni yaxşılaşdırır, amma safety-critical üçün yetərli deyil (Kleppmann)
- **Fencing tokens** — real safety üçün zəruri primitive
- **ZK/etcd** — CP systems, consensus-backed, doğma fencing (sequence/revision)
- Laravel `Cache::lock` Redis üzərində rahat API, lakin critical işlər üçün etcd/ZK client-ə yönəl
- Həmişə TTL, unique owner, atomic release, renewal və monitoring — altı qızıl qayda


## Əlaqəli Mövzular

- [Distributed Systems](25-distributed-systems.md) — koordinasiya fundamentalları
- [Booking System](39-booking-system.md) — resource lock tətbiqi
- [Raft/Paxos](84-raft-paxos-consensus.md) — consensus-based lock
- [Task Scheduler](21-task-scheduler-design.md) — leader election üçün lock
- [Live Auction](87-live-auction-design.md) — bid concurrency lock
