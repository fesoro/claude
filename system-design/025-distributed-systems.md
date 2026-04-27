# Distributed Systems (Senior)

## İcmal

Distributed systems - bir çox müstəqil kompüterin (node) network üzərindən əlaqə quraraq vahid sistem kimi işləməsidir. İstifadəçi üçün bu sistem tək bir kompüter kimi görünür, amma arxa planda onlarla, yüzlərlə server birlikdə iş görür.

Distributed sistemlərin əsas problemləri:
- **Network unreliability** - Şəbəkə paketləri itə bilər
- **Partial failures** - Bəzi nodlar fail ola bilər
- **Consistency** - Məlumatın bütün nodlarda sinxron qalması
- **Consensus** - Nodların ortaq qərara gəlməsi
- **Time synchronization** - Saatların sinxronizasiyası


## Niyə Vacibdir

Distributed sistemlər partial failure, network partition, clock skew kimi unikal problemlər gətirir. Bu fundamentalları bilmədən Kafka, Redis Cluster, PostgreSQL replication-ı düzgün konfiqurasiya etmək mümkün deyil. CAP, FLP, two generals problem — distributed engineering-in özəyi bunlardır.

## Əsas Anlayışlar

### 1. CAP Theorem
- **Consistency** - Bütün nodlar eyni məlumatı görür
- **Availability** - Hər sorğu cavab alır
- **Partition Tolerance** - Network bölünsə belə sistem işləyir

CAP-a görə bir anda yalnız 2 xüsusiyyəti seçə bilərsiniz: CP (MongoDB, HBase) və ya AP (Cassandra, DynamoDB).

### 2. Consensus Algorithms

**Raft Algorithm:**
Raft - anlaşılması asan olsun deyə hazırlanmış consensus algoritmidir. Üç rol var:
- **Leader** - bütün yazıları qəbul edir
- **Follower** - leaderi dinləyir
- **Candidate** - leader seçki prosesində

Raft prosesi:
1. Bütün nodlar Follower rolunda başlayır
2. Leader heartbeat göndərməsə, Follower Candidate olur
3. Candidate səs toplayır, majority qazanırsa Leader olur
4. Leader log entries göndərir, Follower-lər apply edir

**Paxos Algorithm:**
Paxos - ən qədim və istifadə olunan consensus protokolu. Rolları:
- **Proposer** - dəyəri təklif edir
- **Acceptor** - təklifi qəbul edir/rədd edir
- **Learner** - qəbul edilmiş dəyəri öyrənir

Paxos iki fazalıdır: Prepare (promise) və Accept. Raft daha sadədir, Paxos daha çevikdir.

### 3. Leader Election

Leader election - distributed sistemdə bir node-un lider seçilməsi prosesidir. Alqoritmlər:
- **Bully Algorithm** - ən yüksək ID-li node leader olur
- **Ring Algorithm** - node-lar halqa şəklində yerləşir
- **Raft Election** - timeout və voting əsaslı

### 4. Distributed Locks

Distributed mühitdə bir neçə serverdə eyni resurs üzərində eksklüziv kontrol üçün istifadə olunur.

**Redis Redlock:**
Redlock - Redis üzərində distributed lock mexanizmidir. Alqoritm:
1. Client cari vaxtı millisaniyə ilə götürür
2. N Redis instance-da ardıcıl eyni key üçün lock almağa çalışır
3. Client majority-dən (N/2+1) lock alırsa və ümumi vaxt TTL-dən azdırsa, lock alınıb sayılır
4. Lock azad edilərkən bütün instance-larda silinir

### 5. Clock Synchronization

**Lamport Clocks (Logical Clocks):**
- Hər hadisə üçün monotonic counter
- Event a ilə b arasında "happens-before" əlaqəsi
- Sadə, amma causality-ni tam tutmur

**Vector Clocks:**
- Hər node üçün ayrı counter
- `[A:2, B:1, C:3]` formatında
- Concurrent hadisələri aşkarlaya bilir
- DynamoDB, Riak istifadə edir

### 6. Split Brain

Split brain - network partition nəticəsində clusterin iki hissəyə bölünməsi və hər hissənin özünü həqiqi cluster hesab etməsidir. Çox təhlükəlidir çünki data corruption baş verir.

Həlli:
- **Quorum** - majority tələb et (N/2+1)
- **Fencing tokens** - unique increasing token
- **STONITH** - "Shoot The Other Node In The Head"

## Arxitektura

```
Client → Load Balancer → [Node1 (Leader), Node2 (Follower), Node3 (Follower)]
                                    ↓
                              Replicated State
                                    ↓
                          Consensus Protocol (Raft)
```

Tipik Raft cluster:
- 3 və ya 5 node (odd number)
- Quorum = N/2 + 1
- Leader hər şeyi koordinasiya edir
- Election timeout 150-300ms
- Heartbeat interval 50ms

## Nümunələr

### Redis Redlock Implementation

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedLock
{
    private array $servers;
    private int $retryCount = 3;
    private int $retryDelay = 200; // ms
    private float $clockDriftFactor = 0.01;

    public function __construct(array $servers)
    {
        $this->servers = $servers;
    }

    public function lock(string $resource, int $ttl): array|false
    {
        $token = bin2hex(random_bytes(20));

        for ($retry = 0; $retry < $this->retryCount; $retry++) {
            $startTime = microtime(true) * 1000;
            $acquired = 0;

            foreach ($this->servers as $server) {
                if ($this->lockInstance($server, $resource, $token, $ttl)) {
                    $acquired++;
                }
            }

            $drift = ($ttl * $this->clockDriftFactor) + 2;
            $validityTime = $ttl - (microtime(true) * 1000 - $startTime) - $drift;

            if ($acquired >= (count($this->servers) / 2 + 1) && $validityTime > 0) {
                return [
                    'validity' => $validityTime,
                    'resource' => $resource,
                    'token' => $token,
                ];
            }

            // Lock alına bilmədi, alınanları azad et
            foreach ($this->servers as $server) {
                $this->unlockInstance($server, $resource, $token);
            }

            usleep(random_int(0, $this->retryDelay) * 1000);
        }

        return false;
    }

    public function unlock(array $lock): void
    {
        foreach ($this->servers as $server) {
            $this->unlockInstance($server, $lock['resource'], $lock['token']);
        }
    }

    private function lockInstance(string $server, string $resource, string $token, int $ttl): bool
    {
        $redis = Redis::connection($server);
        return (bool) $redis->set($resource, $token, 'PX', $ttl, 'NX');
    }

    private function unlockInstance(string $server, string $resource, string $token): void
    {
        $script = "
            if redis.call('GET', KEYS[1]) == ARGV[1] then
                return redis.call('DEL', KEYS[1])
            else
                return 0
            end
        ";
        Redis::connection($server)->eval($script, 1, $resource, $token);
    }
}
```

### Istifadə nümunəsi

```php
<?php
namespace App\Http\Controllers;

use App\Services\RedLock;

class OrderController extends Controller
{
    public function processOrder(int $orderId, RedLock $redlock)
    {
        $lock = $redlock->lock("order:{$orderId}", 10000); // 10s TTL

        if (!$lock) {
            return response()->json(['error' => 'Could not acquire lock'], 423);
        }

        try {
            // Critical section
            $order = Order::find($orderId);
            $order->process();
            return response()->json(['status' => 'processed']);
        } finally {
            $redlock->unlock($lock);
        }
    }
}
```

### Vector Clock Implementation

```php
<?php
namespace App\Services;

class VectorClock
{
    private array $clock = [];
    private string $nodeId;

    public function __construct(string $nodeId)
    {
        $this->nodeId = $nodeId;
        $this->clock[$nodeId] = 0;
    }

    public function increment(): void
    {
        $this->clock[$this->nodeId]++;
    }

    public function update(array $remoteClock): void
    {
        foreach ($remoteClock as $node => $value) {
            $this->clock[$node] = max($this->clock[$node] ?? 0, $value);
        }
        $this->increment();
    }

    public function compare(array $other): string
    {
        $isLess = false;
        $isGreater = false;

        $allNodes = array_unique(array_merge(array_keys($this->clock), array_keys($other)));

        foreach ($allNodes as $node) {
            $a = $this->clock[$node] ?? 0;
            $b = $other[$node] ?? 0;
            if ($a < $b) $isLess = true;
            if ($a > $b) $isGreater = true;
        }

        if ($isLess && !$isGreater) return 'before';
        if ($isGreater && !$isLess) return 'after';
        if (!$isLess && !$isGreater) return 'equal';
        return 'concurrent';
    }

    public function toArray(): array
    {
        return $this->clock;
    }
}
```

### Leader Election with Redis

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class LeaderElection
{
    private string $nodeId;
    private string $lockKey = 'cluster:leader';
    private int $ttl = 5000; // 5s

    public function __construct()
    {
        $this->nodeId = gethostname() . '-' . getmypid();
    }

    public function tryBecomeLeader(): bool
    {
        $result = Redis::set($this->lockKey, $this->nodeId, 'PX', $this->ttl, 'NX');
        return (bool) $result;
    }

    public function isLeader(): bool
    {
        return Redis::get($this->lockKey) === $this->nodeId;
    }

    public function renewLeadership(): bool
    {
        $script = "
            if redis.call('GET', KEYS[1]) == ARGV[1] then
                return redis.call('PEXPIRE', KEYS[1], ARGV[2])
            else
                return 0
            end
        ";
        return (bool) Redis::eval($script, 1, $this->lockKey, $this->nodeId, $this->ttl);
    }

    public function resign(): void
    {
        if ($this->isLeader()) {
            Redis::del($this->lockKey);
        }
    }
}
```

## Real-World Nümunələr

- **etcd** - Kubernetes-in metadata store-u, Raft istifadə edir
- **Apache ZooKeeper** - ZAB protokol (Paxos variantı), Kafka üçün
- **Consul** - Service discovery, Raft consensus
- **Google Spanner** - TrueTime API ilə global consistency
- **Amazon DynamoDB** - Vector clocks (köhnə versiyalarda), eventual consistency
- **CockroachDB** - Raft əsaslı SQL database
- **Redis Sentinel** - Master-slave failover üçün

## Praktik Tapşırıqlar

**Q1: Raft və Paxos arasındakı fərq nədir?**
Raft sadəlik üçün dizayn edilib - strong leader modeli, ayrıca leader election və log replication fazaları. Paxos daha ümumi, amma başa düşməsi çətindir - Multi-Paxos, Fast Paxos kimi variantları var. Raft production sistemlərdə (etcd, Consul) daha çox istifadə olunur.

**Q2: Split brain nədir və necə qarşısı alınır?**
Split brain - network partition zamanı clusterin iki hissəyə bölünüb hər iki hissənin özünü master hesab etməsidir. Qarşısını almaq üçün quorum istifadə olunur (N/2+1 node razılaşmalıdır). Fencing tokens və STONITH da istifadə edilə bilər.

**Q3: Niyə distributed lock üçün database lock istifadə etmək pisdir?**
Database lock bir neçə problem yaradır:
- Connection pool tükənə bilər
- Lock holder crash olanda lock qalır (deadlock)
- Database single point of failure olur
- Redis Redlock daha performantdır və TTL ilə avtomatik azad olunur

**Q4: Lamport clock və Vector clock fərqi?**
Lamport clock tək counterdir, "happens-before" əlaqəsini tuta bilir amma concurrent hadisələri ayırd edə bilmir. Vector clock hər node üçün ayrı counter saxlayır və concurrent updates-i tanıya bilir. Vector clock daha çox yer tutur amma daha dəqiqdir.

**Q5: Niyə Raft cluster-də tək sayda node lazımdır?**
Tək sayda node (3, 5, 7) quorum hesablamasını optimallaşdırır. 3 node-da quorum 2, 4 node-da da quorum 3-dür - yəni 4-cü node əlavə fault tolerance vermir, amma daha çox network traffic yaradır. 3 node 1 failure-a, 5 node 2 failure-a dözə bilir.

**Q6: Redlock-un tənqid olunan cəhətləri nələrdir?**
Martin Kleppmann Redlock-u tənqid edib:
- Clock drift problemləri (GC pause, NTP adjust)
- Fencing token olmaması
- Timing assumption-lara əsaslanır
Antirez (Redis yaradıcısı) cavab verib, amma kritik sistemlərdə Zookeeper/etcd daha təhlükəsizdir.

**Q7: Byzantine Fault Tolerance nədir?**
BFT - node-ların nəinki crash olacağını, həm də zərərli (malicious) davranacağını nəzərə alan consensus modeli. PBFT, Tendermint bu sahədə işlədilir. Klassik Raft/Paxos BFT deyil - node-ların düzgün davranacağını qəbul edir. Blockchain sistemləri BFT istifadə edir.

**Q8: Two-phase commit (2PC) problemləri nələrdir?**
2PC-nin məhdudiyyətləri:
- Blocking protokol (coordinator fail olarsa participantlar gözləyir)
- Performans cəhətdən yavaş
- Network partition-da problem yaradır
- 3PC, Saga pattern alternativdir

**Q9: Eventual consistency nə deməkdir?**
Eventual consistency - update-dən sonra əgər əlavə yazılar olmasa, oxumalar nəticədə yeni dəyəri qaytaracaq. DNS, DynamoDB, Cassandra bu modeli istifadə edir. Strong consistency-dən daha yüksək availability verir amma programming model çətinləşir.

**Q10: Consensus niyə çətindir?**
FLP impossibility teoremi sübut edir ki, asinxron network-də, bir node belə fail olsa, deterministik consensus algoritmi mövcud deyil. Praktikada bunu randomization (ping timeout) və failure detectorlar ilə həll edirik. Bu isə trade-off-lara gətirib çıxarır.

## Praktik Baxış

1. **Odd number of nodes** - Consensus cluster-ləri 3, 5, 7 node ilə qur
2. **Timeout tuning** - Election timeout heartbeat-dan 10x böyük olsun
3. **Monitor consensus lag** - Follower-lərin geri qalmasını izlə
4. **Use battle-tested libraries** - Özün Raft yazma, etcd/Consul istifadə et
5. **Fencing tokens** - Distributed lock-larda monotonic token istifadə et
6. **Clock sync** - NTP və ya PTP ilə saatları sinxron saxla
7. **Idempotent operations** - Retry zamanı duplicate qorunsun
8. **Quorum writes and reads** - CP sistemdə W+R > N təmin et
9. **Network partition testing** - Chaos engineering ilə test et
10. **Graceful degradation** - Leader itirildikdə read-only mode-a keç


## Əlaqəli Mövzular

- [CAP & PACELC](42-cap-pacelc.md) — distributed sistem fundamental seçimi
- [Consistency Patterns](32-consistency-patterns.md) — consistency modellər spektri
- [Distributed Locks](83-distributed-locks-deep-dive.md) — koordinasiya primitivi
- [Raft/Paxos](84-raft-paxos-consensus.md) — leader election və consensus
- [Anti-Entropy](92-anti-entropy-merkle-trees.md) — replica divergence aşkarlanması
