# Raft, Paxos, ZAB — Consensus Deep Dive

> Distributed sistemlərdə N replikanın bir dəyər (və ya ardıcıl log)
> üzərində razılığa gəlməsi üçün alqoritmlər. File 25 ümumi baxış verir;
> bu fayl Raft, Paxos, ZAB-ın daxili mexanizmlərinə dərindən baxır.

---

## Konsensus nəyi həll edir? (What consensus solves)

**Problem:** N node (replika) var. Hər biri müstəqil işləyir, bəziləri
crash ola bilər, şəbəkə partition ola bilər. Hamısı eyni dəyərə (və ya
eyni sıra ilə commit olunan log-a) razılaşmalıdır.

**Nümunələr:**
- Leader seçimi — hansı node master-dir?
- Distributed lock — kim lock-u aldı?
- Config dəyişikliyi — yeni config nədir?
- Replicated state machine — hamı eyni əmrləri eyni sıra ilə icra etsin

**Tələblər:**
1. **Safety** — səhv dəyər commit olunmasın (bir dəfə commit olunan
   dəyər dəyişməsin)
2. **Liveness** — sistem nə vaxtsa qərara gəlsin (progress etsin)
3. **Fault tolerance** — N/2-dən az node crash olarsa, sistem işləməyə
   davam etsin

---

## FLP impossibility teoremi (FLP impossibility)

**Fischer, Lynch, Paterson (1985):**
> Asinxron şəbəkədə (mesaj delay-i sonsuz ola bilər) bir node belə crash
> olarsa, **deterministic** konsensus **mümkün deyil**.

**Nə deməkdir?**
Asinxron mühitdə crash olan node-u yavaş node-dan ayıra bilmirsən.
Deterministic alqoritm ya safety pozacaq, ya da liveness itirəcək.

**Praktiki həll — partial synchrony (Dwork et al. 1988):**
- Şəbəkə çox vaxt sinxron işləyir (mesaj delay məhdud)
- Arabir "bad period" olur (partition, overload)
- Alqoritm safety-ni həmişə saxlayır, liveness-i yalnız stabil
  dövrlərdə garantee edir

Raft, Paxos, ZAB — hamısı partial synchrony modelində işləyir.

---

## Paxos (Leslie Lamport, 1990)

### Rollar (Roles)

- **Proposer** — dəyər təklif edir
- **Acceptor** — təklifə səs verir (quorum — majority)
- **Learner** — qəbul olunan dəyəri öyrənir

### İki fazalı protokol (Two-phase protocol)

```
Phase 1 — PREPARE:
  Proposer --> Acceptors: prepare(n)    # n = proposal number (monotonic)
  Acceptor --> Proposer: promise(n, highest_accepted)
                         # söz verir: n-dən kiçik təklifi qəbul etməyəcək

Phase 2 — ACCEPT:
  Proposer --> Acceptors: accept(n, value)
                          # value = ya yeni, ya da ən yüksək accepted
  Acceptor --> Proposer: accepted(n, value)

  Majority accept edərsə --> value chosen (commit)
```

### Safety və Liveness

- **Safety:** yalnız bir dəyər seçilə bilər (two proposer conflict etsə,
  yüksək `n` olan qalib gəlir)
- **Liveness:** iki proposer rəqabət edərsə, "dueling proposers" olur —
  hər biri digərinin promise-ini invalidate edir. Həll — stabil leader
  seç.

### Multi-Paxos

Hər dəyər üçün Phase 1-i təkrarlamaq baha. **Multi-Paxos:**
1. Leader (distinguished proposer) seç
2. Leader bütün gələcək təkliflər üçün Phase 1-i bir dəfə icra edir
3. Hər yeni dəyər üçün yalnız Phase 2 lazımdır (1 RTT)

### Nə üçün Paxos çətindir?

Lamport-un orijinal paper-i ("The Part-Time Parliament") alleqoriyalı
və qarışıq idi. Sonra "Paxos Made Simple" yazdı, amma yenə də:
- Multi-Paxos rəsmi spesifikasiyası tam deyil
- Log-da "hole" (boşluq) ola bilər
- Leader election Paxos-un özünə qarışıq
- Membership change üçün ayrı protokol lazım

Nəticə — hər şirkət Paxos-u fərqli implement edir.

---

## Raft (Ongaro & Ousterhout, 2014)

**Devizi:** "Understandability" — Paxos qədər güclü, amma daha anlaşıqlı.

### Əsas fikirlər

1. **Strong leader** — yalnız leader client write qəbul edir
2. **Leader election** — explicit state machine
3. **Log replication** — leader log-u follower-lərə göndərir
4. **Terms** — monotonic integer, hər yeni election +1

### Raft state machine (ASCII)

```
                  timeout,
                  start election
    +--------+  ---------------->  +-----------+
    |        |                     |           |
    |Follower|                     | Candidate |
    |        |  <----------------  |           |
    +--------+   discovers leader  +-----------+
        ^        or higher term          |
        |                                | receives majority
        | discovers                      v votes
        | higher term            +----------+
        |  <-------------------  |          |
        +----------------------- |  Leader  |
                                 |          |
                                 +----------+
```

**Keçidlər:**
- **Follower --> Candidate:** election timeout (adətən 150-300ms random)
- **Candidate --> Leader:** majority vote aldı
- **Candidate --> Follower:** başqa leader tapdı və ya yüksək term gördü
- **Leader --> Follower:** yüksək term gördü

### Leader election

```
Follower:
  - heartbeat gözləyir (AppendEntries from leader)
  - timeout olduqda --> Candidate olur

Candidate:
  - currentTerm++
  - özünə səs verir
  - RequestVote RPC göndərir
  - Əgər majority "yes" --> Leader
  - Əgər AppendEntries gəldi (leader var) --> Follower
  - Əgər yeni timeout --> növbəti term-də yenidən
```

**Split vote:** iki candidate eyni vaxtda eyni term-də səs istəyə bilər.
Random timeout (150-300ms) bunu minimuma endirir.

### Log replication

```
Client --> Leader: SET x=5
Leader:
  1. Log-a append: [term=3, index=7, cmd="SET x=5"]
  2. AppendEntries RPC follower-lərə
  3. Majority ack --> commit (commitIndex = 7)
  4. State machine-ə tətbiq et
  5. Client-ə cavab

Follower:
  - AppendEntries gələndə log-a append et
  - commitIndex yenilənəndə state machine-ə tətbiq et
```

### Log structure (ASCII)

```
Index:   1   2   3   4   5   6   7
       +---+---+---+---+---+---+---+
Leader | 1 | 1 | 1 | 2 | 3 | 3 | 3 |  <- term numbers
       | x | y | z | a | b | c | d |  <- commands
       +---+---+---+---+---+---+---+
                        ^
                        commitIndex

       +---+---+---+---+---+---+
Fol.1  | 1 | 1 | 1 | 2 | 3 | 3 |    <- index 7 yoxdur
       +---+---+---+---+---+---+

       +---+---+---+---+
Fol.2  | 1 | 1 | 1 | 2 |            <- geri qalıb
       +---+---+---+---+
```

### Log matching property

> Əgər iki log eyni index və eyni term-də entry-ə malikdirsə, həmin
> index-ə qədər log-lar identikdir.

Necə işləyir — leader AppendEntries göndərəndə:
- `prevLogIndex` və `prevLogTerm` əlavə edir
- Follower yoxlayır: mənim log-um bu index-də bu term-ə malikdir?
- Yoxdursa — reject. Leader `nextIndex` azaldır və yenidən cəhd edir.

### Leader completeness

> Commit olunmuş entry gələcək leader-lərin log-unda **hökmən** olacaq.

Raft bunu təmin edir: candidate yalnız o halda leader ola bilər ki,
onun log-u voter-lərin log-undan "up-to-date" olsun (RequestVote
yoxlayır).

---

## Heartbeat və leadership saxlama

Leader boş `AppendEntries` (heartbeat) göndərir — adətən hər 50ms.

```
Leader  --heartbeat-->  Follower (timer reset)
Leader  --heartbeat-->  Follower (timer reset)
Leader  --heartbeat-->  Follower (timer reset)

Əgər leader crash olsa:
Follower heartbeat gözləyir... timeout (150-300ms)...
--> yeni election başlayır
```

---

## Log compaction (snapshot)

Log sonsuz böyüyə bilməz. Həll — **snapshot:**

```
Log: [1][2][3][4][5][6][7][8][9][10]
                  |
                  v  snapshot up to index 5
Snapshot: {state: {...}, lastIndex: 5, lastTerm: 2}
Log: [6][7][8][9][10]
```

Leader yavaş follower-ə `InstallSnapshot` RPC göndərir.

---

## Membership changes (cluster reconfiguration)

**Problem:** 3-node cluster-i 5-node-a çevirmək istəyirsən. Sadəcə config
dəyişsən — "split brain" ola bilər (köhnə majority 2, yeni majority 3;
iki ayrı leader seçilə bilər).

**Həll 1 — Joint consensus (C_old,new):**
1. Leader `C_old,new` entry-ni commit edir (həm köhnə, həm yeni majority
   tələb olunur)
2. Sonra `C_new` entry-ni commit edir
3. Bu ikili keçid split brain-in qarşısını alır

**Həll 2 — Single-server changes:**
Hər dəfə yalnız bir node əlavə/çıxar. Köhnə və yeni majority həmişə
overlap olur (in practice — etcd bunu istifadə edir).

---

## ZAB (ZooKeeper Atomic Broadcast)

ZooKeeper-in daxili protokolu. Raft-dan əvvəl (2008) yaradılıb.

### Oxşarlıqlar (Raft ilə)

- Leader-based
- Majority quorum
- Term-ə oxşar "epoch"
- Log replication (proposal + commit)

### Fərqlər

| Aspekt | Raft | ZAB |
|--------|------|-----|
| Rəsmi adı | Raft | ZAB |
| Log commit | majority ack | majority ack |
| Leader yenidən qoşulma | log truncation | transaction resync |
| Read consistency | default linearizable | default "sync" call lazım |
| Domain | general-purpose | ZooKeeper-specific |

### ZAB fazaları

1. **Discovery** — yeni leader tapılır (highest epoch)
2. **Synchronization** — follower-lər leader-in log-una sync olur
3. **Broadcast** — normal mode; leader proposal göndərir, majority
   accept edir --> commit

---

## Performans (Performance)

### Latency

- **Commit latency:** 1 RTT leader → majority follower → leader
- **Client-a cavab:** leader commit olandan sonra
- **Read (linearizable):** ya leader-dən (leader lease), ya da ReadIndex
  (leader majority-dən təsdiq alır)

### Throughput

- **Bottleneck:** leader (bütün write onun üzərindən keçir)
- **Scale read:** follower-lərdən oxu (stale olur — eventual)
- **Batching:** leader bir RPC-də çoxlu entry göndərir

### Tipik rəqəmlər (etcd)

- 3-node cluster, SSD: ~10-20k writes/sec
- Commit latency: 5-20ms (local datacenter)
- Cross-region: 50-200ms (TrueTime olmadan)

---

## Raft vs Paxos — fərqlər (Differences)

| Aspekt | Raft | Multi-Paxos |
|--------|------|-------------|
| Leader election | Explicit state machine | Proposals-a qarışıq |
| Log | Contiguous, no holes | Holes icazəlidir |
| Understandability | Yüksək | Aşağı |
| Formal proof | Var | Var |
| Production implementations | etcd, Consul, TiKV | Chubby, Spanner |
| Membership change | Joint consensus və ya single | Ayrı protokol |
| Paper ölçüsü | ~18 səhifə | Çox paper-lar |

**Nəticə:** Yeni sistem qururusansa — Raft seç. Mövcud Paxos-based
sistem varsa — toxunma.

---

## Variantlar (Variants)

### EPaxos (Egalitarian Paxos)

- Leader yoxdur
- Hər node öz dəyərini təklif edə bilər
- Konflikt olmayan əmrlər paralel commit olunur
- Geo-distributed üçün yaxşı (leader bottleneck yox)
- Mürəkkəblik — konflikt detect etmək lazımdır

### Flexible Paxos

- Phase 1 quorum və Phase 2 quorum **fərqli** ola bilər
- Overlap kifayətdir
- Məsələn: 5 node-da Phase 1 üçün 4 quorum, Phase 2 üçün 2 quorum
- Write-heavy workload-lar üçün optimal

### Multi-leader (CRDTs, custom)

- Hər node yazır, sonra reconcile
- Konsensus əvəzinə — **convergent** data types (CRDT)
- Eventual consistency
- Riak, Redis CRDT, Yjs

---

## Real-world implementations

| Sistem | Alqoritm | Qeyd |
|--------|----------|------|
| etcd | Raft | Kubernetes-in əsas storage |
| Consul | Raft | HashiCorp service discovery |
| TiKV | Raft | TiDB-nin storage layer |
| CockroachDB | Multi-Raft | hər range-in öz Raft group-u |
| ZooKeeper | ZAB | Kafka (köhnə), HBase |
| Kafka KRaft | Raft | 2.8+ ZooKeeper-i əvəz edir |
| Google Chubby | Paxos | Google-un lock service-i |
| Google Spanner | Paxos + TrueTime | global DB |
| HashiCorp Raft (Go) | Raft | kitabxana |
| Hazelcast CP | Raft | in-memory |

---

## Client interaction

### Leader tapmaq

```
Client --> Node A: write(x=5)
Node A: "I'm follower, leader is Node B"
Client --> Node B: write(x=5)
Node B: OK (commits)
```

**Və ya:** DNS / service discovery (`etcd_leader` endpoint).

### Linearizable read

İki variant:

**1. ReadIndex:**
```
Client --> Leader: read(x)
Leader:
  - currentCommitIndex oxu
  - majority-yə heartbeat göndər (hələ də leaderəm?)
  - majority təsdiq etsə --> apply edilmiş state-dən oxu və qaytar
```

**2. Leader lease:**
```
Leader: "Mən 10 saniyə lease-im var (clock-a inanıram)"
Read gələndə lease içində olarsa --> dərhal oxu (heartbeat-siz)
```

### Stale read (eventual)

```
Client --> Follower: read(x, stale=true)
Follower: "my local value is 4" (bəlkə köhnə)
```

Sürətli, amma güncəl olmaya bilər.

---

## Failure scenarios (walk-through)

### 1. Leader crash

```
t=0: Leader (N1) commit edir, heartbeat göndərir
t=1: N1 crash
t=200ms: N2 election timeout, candidate olur
t=250ms: N2 majority alır, yeni leader
t=260ms: Client redirected
```

### 2. Network partition

```
[N1 (leader)] [N2] [N3]    partition   [N4] [N5]

Minority side (N4, N5): election başlayır, majority yoxdur, leader seçə
                         bilmir --> read/write blocked
Majority side (N1, N2, N3): normal işləyir
```

### 3. Split vote

```
N1 və N2 eyni anda candidate oldu, hər biri öz səsini aldı
--> 2-2 split (5 node cluster-də)
--> heç kim leader deyil --> timeout --> yenidən, random timeout fərqli
```

### 4. Slow follower

```
Leader: index 100-də
Follower: index 50-də (yavaş)
Leader: AppendEntries(prevIndex=99) --> reject
Leader: nextIndex-i azalt --> 98, 97, ... 50
Leader: 50-dən sonrakı entry-ləri göndər (və ya snapshot)
```

---

## Laravel / PHP client nümunəsi (Laravel client example)

PHP-də Raft/Paxos **implement etmirik**. Etcd/Consul/ZooKeeper
cluster-inə **client** kimi qoşuluruq.

### Misal — distributed lock (cron üçün)

```php
// composer require consul-php/consul
use SensioLabs\Consul\ServiceFactory;

class DistributedCron
{
    public function runCron(string $jobName, \Closure $callback): void
    {
        $consul = (new ServiceFactory(['base_uri' => 'http://consul:8500']))
            ->get('Consul\Services\Session')
            ;

        // Session yarat (Consul tərəfdə TTL ilə)
        $session = $consul->create([
            'Name' => "cron-{$jobName}",
            'TTL' => '30s',
            'Behavior' => 'release',
        ]);
        $sessionId = json_decode($session->getBody(), true)['ID'];

        // Lock götür (KV + session)
        $kv = (new ServiceFactory(['base_uri' => 'http://consul:8500']))
            ->get('Consul\Services\KV');

        $lockKey = "locks/cron/{$jobName}";
        $acquired = $kv->put($lockKey, gethostname(), [
            'acquire' => $sessionId,
        ]);

        if ($acquired->getBody() !== 'true') {
            Log::info("Another instance holds lock for {$jobName}");
            return;
        }

        try {
            $callback();
        } finally {
            $kv->put($lockKey, '', ['release' => $sessionId]);
            $consul->destroy($sessionId);
        }
    }
}
```

**Nə baş verir (arxa plan):**
1. Consul cluster-i Raft ilə replicated (məs 3 server node)
2. `session create` → Raft-a yazılır, majority commit edir
3. `KV put with acquire` → atomically yoxlayır: boşdursa session-ə bağla
4. Yalnız bir instance lock götürə bilər (Raft safety garantee)

### Misal — etcd leader election

```php
// composer require lyahov/etcd-php
$etcd = new Etcd\Client('http://etcd:2379');

// Election campaign (leader olmağa çalış)
$leaseId = $etcd->lease()->grant(30)['ID']; // 30s TTL
$put = $etcd->kv()->put(
    'leader/cron-runner',
    gethostname(),
    ['lease' => $leaseId, 'ignore_value' => false]
);

// Keepalive (lease-i uzatmaq üçün arxa planda)
// Leader olduqca işini görür, crash olarsa lease expire olur,
// digər node avtomatik leader olur (Raft təmin edir)
```

**Qeyd:** Laravel-də bu pattern-ləri `Illuminate\Support\Facades\Cache`
lock (Redis) əvəzinə istifadə edirik ki, **replicated** (CP) storage-a
söykənsin. Redis CP deyil — master crash olarsa lock itə bilər.

---

## Engineering challenges

1. **Düzgün implement etmək çətindir** — Raft-da belə incə bug-lar olur
   (Jepsen test-lər tapır)
2. **Sınanmış kitabxana istifadə et:**
   - Go: `hashicorp/raft`, `etcd/raft`
   - Rust: `tikv/raft-rs`
   - Java: Apache Ratis, Atomix
3. **Network partition test-lər** — Jepsen, Chaos Monkey
4. **Clock skew** — lease-based optimization-lar clock-a güvənir
5. **Disk fsync** — crash-safe olmaq üçün log disk-ə sync olmalıdır
6. **Snapshot strategiyası** — çox tez-tez → I/O baha; nadirən → recovery
   yavaş

---

## Byzantine consensus (qısaca)

**Fərq:** Raft/Paxos **crash-stop** failure-a qarşı qoruyur. Node ya
işləyir, ya crash olub. Byzantine failure — node **yalan** danışa bilər
(malicious, corrupted).

**Alqoritmlər:**
- **PBFT (Practical BFT)** — 3f+1 node lazımdır, f malicious üçün
- **HotStuff** — Facebook Diem, linear communication
- **Tendermint** — Cosmos blockchain
- **Nakamoto consensus** (Bitcoin) — PoW, probabilistic

**Qiymət:**
- 3x daha çox node (3f+1 vs 2f+1)
- Cryptographic signatures hər mesajda
- Daha aşağı throughput

**Harada istifadə olunur:** blockchain, kritik distributed systems
(bankinq, hərbi). Adi mikroservis arxitekturasında **lazım deyil.**

---

## Interview Q&A

**Q1: Nə üçün Raft yaradıldı, Paxos kifayət deyildi?**
A: Paxos safety və liveness cəhətdən düzgündür, amma anlaşıqlı deyil —
həm öyrənmək, həm də düzgün implement etmək çətindir. Raft
"understandability" məqsədi ilə yaradılıb: explicit state machine
(Follower/Candidate/Leader), contiguous log, sadə membership change.
Performans oxşardır.

**Q2: FLP impossibility-ni necə izah edərsən?**
A: Asinxron şəbəkədə bir node crash olsa belə, deterministic konsensus
mümkün deyil — crash olan node-u yavaş node-dan ayıra bilmirsən. Praktiki
sistemlər "partial synchrony" qəbul edir: adətən şəbəkə sinxrondur,
arabir bad period olur. Safety həmişə saxlanır, liveness yalnız stabil
dövrlərdə garantee olunur.

**Q3: Raft split vote-u necə həll edir?**
A: Random election timeout (150-300ms). Hər follower fərqli timeout
seçir, ona görə eyni anda iki candidate olmaq ehtimalı aşağıdır. Split
vote baş verərsə, növbəti term-də yenidən election olur — random
timeout yenidən tətbiq olunur, tezliklə bir candidate qalib gəlir.

**Q4: Linearizable read-i Raft-da necə təmin edirsən?**
A: İki üsul: (1) **ReadIndex** — leader majority-yə heartbeat göndərir,
hələ də leader olduğunu təsdiq edir, sonra commitIndex-dən oxuyur.
(2) **Leader lease** — leader clock-a əsasən müəyyən müddətə lease alır,
o müddətdə heartbeat-siz oxuya bilər. Lease-in qiyməti az latency, amma
clock skew-a həssas.

**Q5: Raft cluster-də neçə node olmalıdır?**
A: Tək say (3, 5, 7) tövsiyə olunur — split brain olmasın. 3 node 1
failure-a, 5 node 2 failure-a, 7 node 3 failure-a davam edir. Çox node
→ commit latency artır (majority daha böyük). Tipik production: 3 və ya
5.

**Q6: Leader crash olanda client nə görür?**
A: Mövcud request fail ola bilər (timeout və ya "not leader" error).
Client retry etməlidir. Yeni leader ~200-500ms-də seçilir (election
timeout + 1 RTT). Retry idempotent olmalıdır — eyni request iki dəfə
commit ola bilər (amma Raft client ID + sequence number ilə duplicate-i
detect edir).

**Q7: Raft və ZAB arasında fərq nədir?**
A: Oxşardırlar — hər ikisi leader-based, majority quorum, log replication.
Fərqlər: (1) ZAB ZooKeeper-specific, Raft general. (2) ZAB-da read
default "synced" deyil (`sync()` çağırmaq lazım), Raft-da linearizable
default. (3) Rəsmi specification — Raft daha təmizdir. ZAB tarixi
baxımdan daha qədimdir (2008).

**Q8: Nə vaxt Byzantine consensus istifadə edərsən?**
A: Yalnız **malicious** node-ların mövcud ola biləcəyi mühitdə —
blockchain, cross-organizational sistemlər, kritik infrastruktur.
Mikroservis arxitekturasında node-ları biz özümüz deploy edirik, crash-
stop failure kifayətdir. Byzantine 3x daha çox node, cryptographic
overhead tələb edir — lazımsız yerdə istifadə etmə.

---

## Best Practices

1. **Özün Raft implement etmə** — `etcd/raft`, `hashicorp/raft`,
   `tikv/raft-rs` kimi sınanmış kitabxanalardan istifadə et
2. **Cluster ölçüsü tək say** (3, 5, 7) — heç vaxt 2, 4, 6
3. **Odd-numbered, geographically aware** — cross-region üçün 5 node,
   3 region-a yayılmış
4. **Disk fsync-i söndürmə** — performans xatirinə safety itirmə
5. **Leader-ə bütün trafiki göndərmə** — read-ləri follower-dən
   (eventual consistency ilə) götür, əgər güclü consistency lazım
   deyilsə
6. **Heartbeat interval << election timeout** — 50ms heartbeat, 150-300ms
   election timeout
7. **Monitoring:** leader elections/min, commit latency, follower lag,
   log size, snapshot frequency
8. **Backup snapshot-lar** — disk corruption olarsa recovery üçün
9. **Jepsen testing** — production-a çıxmazdan əvvəl partition tolerance
   test et
10. **Client retry logic idempotent olsun** — leader crash zamanı
    duplicate mümkündür
11. **Konsensus bahalıdır** — hər write üçün istifadə etmə; config,
    metadata, leader election üçün saxla
12. **Membership change təhlükəlidir** — bir dəfəyə bir node əlavə et
    (və ya joint consensus), birdən çox node dəyişdirmə
13. **Raft-ı "sən hər zaman CP" kimi qəbul et** — AP (availability)
    lazımsa, CRDT və ya eventual consistency seç
14. **Kafka KRaft-a keç** — yeni cluster-lərdə ZooKeeper yerinə

---

## Xülasə (Summary)

- **Konsensus:** N replika bir dəyərə razılaşır, safety + liveness +
  fault tolerance
- **FLP:** asinxron mühitdə deterministic konsensus mümkün deyil;
  partial synchrony qəbul edirik
- **Paxos:** klassik, güclü, amma anlaşıqsız; Multi-Paxos leader ilə
  optimize edir
- **Raft:** Paxos-un "understandable" versiyası; explicit state machine,
  contiguous log, strong leader
- **ZAB:** ZooKeeper-in protokolu; Raft-a oxşar, leader-based
- **Byzantine:** malicious node-lara qarşı; PBFT, HotStuff — blockchain
- **Praktiki:** etcd, Consul, TiKV (Raft); Chubby, Spanner (Paxos);
  ZooKeeper (ZAB); Kafka KRaft (Raft)
- **Laravel:** Raft implement etmir; etcd/Consul client ilə leader
  election, distributed lock
- **Qaydalar:** tək say node, sınanmış kitabxana, idempotent retry,
  monitoring

File 25 konsensusun ümumi baxışıdır — bu fayl alqoritmlərin daxili
mexanizmini dərindən açıqlayır.
