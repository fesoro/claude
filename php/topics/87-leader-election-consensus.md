# Leader Election & Consensus

## Mündəricat
1. Leader election niyə lazımdır
2. Raft consensus alqoritmi (sadələşdirilmiş)
3. Split-brain problemi
4. PHP İmplementasiyası
5. İntervyu Sualları

---

## Leader Election Niyə Lazımdır

Distributed sistemdə eyni işi birdən çox node icra edə bilər — bu duplicate processing, data korrupsiyası yaradır:

```
Problem: Cron job bütün server-lərdə işləyir

Server A: run_monthly_billing()  ←┐
Server B: run_monthly_billing()  ←┤ eyni anda!
Server C: run_monthly_billing()  ←┘

Nəticə: müştəri 3 dəfə ödəniş alır
```

**Həll: Yalnız 1 "leader" node həssas işləri icra edir**

```
Server A (Leader) → run_monthly_billing() ✅
Server B (Follower) → gözlər
Server C (Follower) → gözlər

Leader çökdükdə:
→ Yeni seçki keçirilir
→ Server B leader olur
→ İşə davam edir
```

**Leader election lazım olan hallar:**
- Singleton cron jobs / scheduled tasks
- Primary DB yazıları (write amplification önləmək)
- Shard coordinator
- Rate limiter state
- Distributed scheduler

---

## Raft Consensus Alqoritmi (Sadələşdirilmiş)

Raft 2014-cü ildə Diego Ongaro tərəfindən işlənib. Paxos-dan daha asan anlaşılan consensus alqoritmidir.

### Node Vəziyyətləri

```
         timeout          vote granted
Follower ────────→ Candidate ──────────→ Leader
    ↑                  │
    └──────────────────┘
       higher term seen
```

- **Follower:** Passiv, leader-dən heartbeat gözləyir
- **Candidate:** Seçki başlatdı, lider olmağa çalışır
- **Leader:** Log yazır, digərlərinə replikasiya edir

### Seçki Prosesi

```
Term 1:
  Node A (Leader) ─heartbeat→ Node B, Node C

Node A çökdü:
  Node B: 150ms timeout → "Candidate oluram"
  Node B → "Vote for me (Term 2)" → Node C, Node D
  Node C, D: "Term 2-ni görməmişəm, sən üçün səs verirəm"
  Node B: 2 səs aldı (quorum: 2/3) → "Mən Liderim (Term 2)"
```

### Log Replikasiyası

```
Client → Leader: write(x=5)
Leader: log-a əlavə et (uncommitted)
Leader → Follower 1: AppendEntries(x=5)
Leader → Follower 2: AppendEntries(x=5)
Follower 1 → OK
Follower 2 → OK
Leader: Quorum var (2/3) → commit
Leader → Client: OK
Leader → Followers: commit(x=5)
```

### Term konsepsi

```
Term = seçki dövrü. Hər seçki yeni term başladır.

Term 1: A leader
Term 2: A çökdü, B leader
Term 3: Network partition, C özünü leader seçdi

Node term-ə baxaraq stale leader-i tanıyır:
  A restart oldu, Term 1 ilə gəlir
  B: "Sənin term-in (1) mənimkindən (2) kiçikdir → sən follower-sən"
```

---

## Split-Brain Problemi

Network partition zamanı hər iki tərəf özünü leader hesab edə bilər:

```
Normal:
  [A(L)] ←→ [B(F)] ←→ [C(F)]

Network partition:
  [A(L)] ✗   [B] → seçki → [B(L)]
              ↕
             [C(F)]

İndi iki leader var: A və B
Hər ikisi yazı qəbul edir → data split!
```

**Həll yolları:**

```
1. Quorum (Raft yanaşması):
   5 node-dan 3-ü lazımdır
   Partition: {A,B} vs {C,D,E}
   {A,B}: quorum yoxdur (2 < 3) → yazı reject
   {C,D,E}: quorum var (3 ≥ 3) → leader seçilir

2. Fencing (STONITH — Shoot The Other Node In The Head):
   Köhnə leader-i zorla öldür/izolə et

3. Lease-based:
   Leader lease alır (TTL)
   Lease bitənə qədər özünü leader sayır
   Lease başqasına verilmədən köhnə leader yazı etmir
```

---

## PHP İmplementasiyası

```php
<?php

/**
 * Redis-based Leader Election with Heartbeat
 *
 * Alqoritm:
 * 1. SET leader:resource <node-id> NX EX <ttl>
 * 2. Uğurludursa → leader ol, heartbeat göndər
 * 3. Uğursuzsa → follower ol, leader key-ini izlə
 * 4. Heartbeat: hər interval-da TTL-i yenilə (sadəcə sahibi)
 */
class LeaderElection
{
    private Redis $redis;
    private string $nodeId;
    private string $resource;
    private int $ttlSeconds;
    private int $heartbeatInterval; // saniyə
    private bool $isLeader = false;

    private const EXTEND_SCRIPT = <<<'LUA'
    if redis.call("get", KEYS[1]) == ARGV[1] then
        return redis.call("expire", KEYS[1], ARGV[2])
    else
        return 0
    end
    LUA;

    public function __construct(
        Redis $redis,
        string $resource,
        int $ttlSeconds = 10,
        int $heartbeatInterval = 3
    ) {
        $this->redis              = $redis;
        $this->nodeId             = gethostname() . ':' . getmypid() . ':' . bin2hex(random_bytes(4));
        $this->resource           = $resource;
        $this->ttlSeconds         = $ttlSeconds;
        $this->heartbeatInterval  = $heartbeatInterval;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    /**
     * Leadership üçün yarış — yalnız bir node uğurlu olur
     */
    public function tryBecomeLeader(): bool
    {
        $result = $this->redis->set(
            $this->leaderKey(),
            $this->nodeId,
            ['NX', 'EX' => $this->ttlSeconds]
        );

        $this->isLeader = ($result === true);

        if ($this->isLeader) {
            $this->redis->hSet($this->metaKey(), 'elected_at', time());
            $this->redis->hSet($this->metaKey(), 'node_id', $this->nodeId);
            $this->redis->expire($this->metaKey(), $this->ttlSeconds + 5);
        }

        return $this->isLeader;
    }

    /**
     * Heartbeat: leader olduğumuzu TTL yeniləyərək bildiririk
     */
    public function sendHeartbeat(): bool
    {
        if (!$this->isLeader) {
            return false;
        }

        $result = $this->redis->eval(
            self::EXTEND_SCRIPT,
            [$this->leaderKey(), $this->nodeId, (string) $this->ttlSeconds],
            1
        );

        if ((int) $result === 0) {
            // Başqası leader olub (key yoxdur və ya dəyişib)
            $this->isLeader = false;
            return false;
        }

        $this->redis->hSet($this->metaKey(), 'last_heartbeat', time());
        return true;
    }

    /**
     * Hazırkı leader-in node ID-si
     */
    public function getCurrentLeader(): ?string
    {
        return $this->redis->get($this->leaderKey()) ?: null;
    }

    public function isCurrentNodeLeader(): bool
    {
        return $this->getCurrentLeader() === $this->nodeId;
    }

    /**
     * Leadership-i könüllü burax (shutdown zamanı)
     */
    public function resign(): void
    {
        if (!$this->isLeader) {
            return;
        }

        $script = <<<'LUA'
        if redis.call("get", KEYS[1]) == ARGV[1] then
            redis.call("del", KEYS[1])
            redis.call("del", KEYS[2])
            return 1
        end
        return 0
        LUA;

        $this->redis->eval(
            $script,
            [$this->leaderKey(), $this->metaKey(), $this->nodeId],
            2
        );

        $this->isLeader = false;
    }

    private function leaderKey(): string
    {
        return "leader:{$this->resource}";
    }

    private function metaKey(): string
    {
        return "leader:{$this->resource}:meta";
    }
}

// Singleton Worker — yalnız leader icra edir
class SingletonWorker
{
    private LeaderElection $election;
    private bool $running = true;

    public function __construct(LeaderElection $election)
    {
        $this->election = $election;

        // Graceful shutdown
        pcntl_signal(SIGTERM, function () {
            $this->running = false;
            $this->election->resign();
        });
    }

    public function run(): void
    {
        while ($this->running) {
            pcntl_signal_dispatch();

            if ($this->election->tryBecomeLeader()) {
                echo "[{$this->election->getNodeId()}] Leader oldum\n";
                $this->runAsLeader();
            } else {
                $leader = $this->election->getCurrentLeader();
                echo "[{$this->election->getNodeId()}] Follower, leader: {$leader}\n";
                sleep(2); // Gözlə, sonra yenidən cəhd et
            }
        }
    }

    private function runAsLeader(): void
    {
        $heartbeatInterval = 3;
        $lastHeartbeat     = time();

        while ($this->running) {
            pcntl_signal_dispatch();

            // Heartbeat göndər
            if (time() - $lastHeartbeat >= $heartbeatInterval) {
                if (!$this->election->sendHeartbeat()) {
                    echo "[{$this->election->getNodeId()}] Leadership itirildi!\n";
                    return; // Ana loopa qayıt, yenidən seçkiyə qoş
                }
                $lastHeartbeat = time();
            }

            // İş icra et (burada həssas singleton iş gedir)
            $this->doLeaderWork();

            usleep(100_000); // 100ms
        }
    }

    private function doLeaderWork(): void
    {
        // Scheduled job, billing, cleanup və s.
        echo "[{$this->election->getNodeId()}] İş icra edilir...\n";
    }
}

// İstifadə
$redis    = new Redis();
$redis->connect('127.0.0.1', 6379);

$election = new LeaderElection($redis, 'billing-worker', ttlSeconds: 10, heartbeatInterval: 3);
$worker   = new SingletonWorker($election);
$worker->run();
```

---

## İntervyu Sualları

- Distributed sistemdə "leader election" niyə lazımdır? Sadə cron job niyə yetərli deyil?
- Raft-da "term" nə deməkdir? Stale leader-i tanımaq üçün necə istifadə edilir?
- Split-brain nədir? Quorum bu problemi necə həll edir?
- Redis-based leader election-da node crash olsa ne baş verir? TTL bu problemi necə həll edir?
- Raft-da yeni leader seçilmək üçün neçə səs lazımdır? 5 node-lu clusterdə?
- "Brain" bölünərsə (3+2 node partition) hər iki tərəf leader seçə bilərmi?
- Heartbeat intervalı ilə TTL arasındakı nisbət niyə vacibdir?
- Etcd və Zookeeper hansı consensus alqoritmindən istifadə edir?
