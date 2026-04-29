# Eventual Consistency (Architect ⭐⭐⭐⭐⭐)

## İcmal
Eventual consistency, distributed sistemdə bütün node-ların bir müəyyən vaxtdan sonra eyni dəyərə gəlməsini təmin edən consistency modelidir. Strong consistency-dən fərqli olaraq, hər write-dan dərhal sonra bütün node-larda eyni dəyər görünmür. Bu trade-off availability və partition tolerance üçün ödənilən qiymətdir. Architect-level mühəndislər yalnız "eventual consistency istifadə edirik" deməklə kifayətlənmir — nə qədər eventual, nə zaman problematik, hansı mitigation pattern-ları lazımdır.

## Niyə Vacibdir
Amazon, DynamoDB, Cassandra, CockroachDB, DNS — milyardlarla insanın istifadə etdiyi sistemlər eventual consistency üzərindədir. Strong consistency-nin mümkün olmadığı (network partitions real-da baş verir) ya da çox bahalı olduğu mühitlərdə eventual consistency yeganə praktik seçimdir. Architect bu modeli seçdikdə, onun business implicationslarını (istifadəçi köhnə data görür), texniki complexity-ni (conflict resolution, anti-entropy) və mitigation strategiyalarını (read-your-writes, monotonic reads) dərindən bilməlidir.

## Əsas Anlayışlar

### 1. Consistency Modelləri Spektri
```
Strongest                                          Weakest
    │                                                  │
    ▼                                                  ▼
Linearizability  →  Sequential  →  Causal  →  Eventual
    │
    (Every read sees the most recent write)

Linearizability:
  Write A → sonra Read görən hər kəs A-nı görür
  Google Spanner, Zookeeper, etcd
  Cost: High latency (consensus required)

Sequential Consistency:
  Bütün node-lar eyni order-da əməliyyatları görür
  Amma "most recent" guarantee yoxdur
  Cost: Medium latency

Causal Consistency:
  Causally related əməliyyatlar order-da görünür
  A causes B → hər kəs A-nı B-dən əvvəl görür
  Unrelated writes: Fərqli order ola bilər
  MongoDB causal sessions, CockroachDB

Eventual Consistency:
  Yeni write olmazsa, nəticədə hamı eyni dəyəri görür
  "Nəticədə" — milliseconds-dan minutes-ə qədər ola bilər
  DynamoDB default, Cassandra tunable, DNS
```

### 2. BASE vs ACID
```
ACID (Strong Consistency):
  Atomicity:   Transaction ya tam, ya heç
  Consistency: DB hər zaman valid state-də
  Isolation:   Concurrent transactions bir-birini görmür
  Durability:  Committed data qalır

BASE (Eventual Consistency):
  Basically Available:  Sistem hər zaman cavab verir
                        (bəzən köhnə data ilə)
  Soft State:           State zaman keçdikcə dəyişə bilər
                        (sync olmadan)
  Eventually Consistent: Müəyyən vaxtdan sonra consistent

BASE seçim məntiqi:
  User feed görmək: 1 saniyə köhnə post OK → BASE
  Bank balansı: $0 görmək, amma $100 var → ACID
  DNS record: Yeni IP 60 saniyə propagate → BASE
  İnventory: Overselling riski → ACID (ya da saga)
```

### 3. Replication Lag
```
Asynchronous replication:
  Primary: Write qəbul edir → ACK → Replica-ya async göndərir
  Replica: Bir az geridə qalır (replication lag)

  Write → Primary (committed)
  Milliseconds later → Replica 1 (synced)
  Seconds later → Replica 2 (slower network)

  Replication lag ölçmək:
  MySQL: SHOW REPLICA STATUS → Seconds_Behind_Source
  PostgreSQL: pg_stat_replication → replay_lag
  MongoDB: rs.printSecondaryReplicationInfo()

Lag problems:
  1. Read-after-write: User öz write-ını görə bilmir
  2. Monotonic read violation: İki read-də fərqli dəyər
  3. Stale read: Critical decision köhnə data ilə

Lag spike triggerləri:
  Heavy write burst → Replica yetişmir
  Network issue → Replica geridə qalır
  Replica CPU spike → Apply slower
  Schema change → DDL lock, lag artır
```

### 4. Conflict Resolution
```
Multi-master / multi-region yazı:
  Region A: user.name = "Alice" yazdı
  Region B: eyni anda user.name = "Bob" yazdı
  → Conflict! Hansı doğrudur?

Last Write Wins (LWW):
  Timestamp-ə görə: daha yeni timestamp qalib
  Problem: Clock skew (NTP ±100ms) → yanlış qaliblər
  DynamoDB default: LWW with vector clock assist
  Amazon S3: LWW

  Fix: Hybrid Logical Clocks (HLC)
    Physical time + logical counter
    CockroachDB, YugabyteDB istifadə edir

CRDT (Conflict-free Replicated Data Type):
  Mathematically merge-able data structures
  Conflict olmadan otomatik merge

  G-Counter (Grow-only Counter):
    Shard A: counter = 5
    Shard B: counter = 3
    Merge: max(5, 3) = 5  ← always correct

  LWW-Element-Set:
    Add timestamp ilə, Remove timestamp ilə
    Merge: En yeni timestamp qalib

  OR-Set (Observed-Remove Set):
    "Add qalib" strategy
    Redis CRDT (Active-Active Geo-replication) istifadə edir

Application-level resolution:
  E-commerce: Inventory oversell → saga + compensation
  Social feed: Duplicate post → idempotency key ile deduplicate
  Document edit: Google Docs OT (Operational Transformation)
```

### 5. Read-Your-Writes Consistency
```
Problem:
  User profil şəkli yeniləyir (write → primary)
  Dərhal öz profilini yoxlayır (read → replica)
  Replica hələ sync olmayıb → Köhnə şəkil görür
  → User "save işləmədi" düşünür

Həllər:

1. Sticky sessions (read-your-writes):
   User öz write-larını həmişə primary-dən oxusun
   Başqaları üçün replica OK
   
   Implementation:
   - Session-a "last_write_at" timestamp saxla
   - Read time < last_write_at + lag_threshold → Primary-dən oxu
   - Read time > threshold → Replica OK

2. Routing by modification state:
   Recently modified resource → Primary read
   Old resource → Replica read

3. Write confirmation token:
   Write → Token (version/timestamp)
   Client token-ı hər request-ə əlavə edir
   Replica: Token-dəki versiyanı görüb-görmədiyini yoxlayır
   Görməyibsə → Primary-ə forward et

4. Wait for replication:
   Write: sync replication (ağır, yalnız kritik write-lar)
   DynamoDB ConsistentRead=true option
```

### 6. Monotonic Reads
```
Problem:
  User feed-i yeniləyir (read → Replica 1: lag 100ms)
  Refresh edir (read → Replica 2: lag 500ms)
  → Birinci oxumada gördüyü post ikincisində yoxdur!
  → "Postlar yox oldu" effect

Həll: Session-based replica binding
  User session → Hər zaman eyni replica
  Session expires → Reassign

  Load balancer sticky sessions:
  User 1 → Always Replica 1
  User 2 → Always Replica 2

  Problem: Replica 1 down → User 1 üçün failover lazımdır
  Failover: Replica 3-ə keç (ən az lagged)

Monotonic read token:
  Hər read-dən sonra "I have seen up to version X" token
  Sonrakı read: "Version X-dən aşağısını göstərmə"
  → Replica token-i qarşılaya bilmirsə, başqa replica-ya keç
```

### 7. Anti-Entropy Protocol
```
Anti-entropy: Node-lar arası data sync mexanizmi
İki node arasındakı fərqləri tapıb düzəldir

Merkle Tree-based Anti-Entropy (Cassandra, Dynamo):
  1. Hər node: data-nın Merkle tree-sini hesabla
     Leaf: Hər data item-ın hash-i
     Parent: Children hash-lərin kombinasiyası
     Root: Bütün data-nın finger-print

  2. Node A ↔ Node B: Root hash müqayisə
     Eyni → Sync tamamdır
     Fərqli → Subtree-ləri müqayisə et (binary search)

  3. Fərqli leaf tapıldı → Daha yeni versiya qalib
     (LWW ya da vector clock)

  4. Missing data → Kopyala

  Efficiency:
    N items, K fərqlidir → O(K log N) message
    Bütün data göndərməyə ehtiyac yoxdur

Read Repair:
  Read zamanı coordinator bütün replica-lara soruşur
  Köhnə replica-ya daha yeni versiyonu göndərir
  → Passive anti-entropy (read-driven)
  Cassandra: read_repair_chance konfiqurasiya olunur

Hinted Handoff:
  Write zamanı replica down:
  Coordinator: "Hint" saxlayır (hansi replica, nə data)
  Replica qayıtdıqda: Coordinator hint-i göndərir
  → Write hiss etmədən geç-dırılır
```

### 8. Tunable Consistency
```
Cassandra W + R > N → Strong consistency
  N = 3 (replica sayı)
  W = 2 (write quorum)
  R = 2 (read quorum)
  W + R = 4 > N=3 → Ən azı 1 node overlap → Strong

  W=1, R=1: Fastest, weakest (eventual)
  W=2, R=2: Balanced (quorum)
  W=3, R=1: Strong write, fast read
  W=1, R=3: Fast write, strong read

DynamoDB:
  ConsistentRead=false: Eventual (default, cheaper)
  ConsistentRead=true:  Strong (mais expensive)
  
  Eventually consistent read: 1 read unit per 4KB
  Strongly consistent read:   2 read units per 4KB
  → 2x cost for strong consistency

MongoDB:
  readConcern: "local"     → Eventual
  readConcern: "majority"  → Strong (committed majority-də)
  readConcern: "linearizable" → Linearizable (ən yavaş)
  
  writeConcern: 1         → Fire and forget
  writeConcern: "majority" → Wait for majority commit
```

### 9. Eventual Consistency Patterns
```
Saga + Compensation (distributed transaction):
  Hər step local, eventual consistency cross-service
  Failure → Compensating transaction

Outbox Pattern:
  DB commit + Event publish atomic (same DB transaction)
  Event relay: Eventually consistent delivery
  Idempotency: Receiver duplicate-ları ignore edir

Idempotent receiver:
  Same event multiple times gəlsə → idempotent
  "process if not already processed"
  Key: event_id + processed check

Event sourcing:
  Hər dəyişiklik event kimi yazılır
  Read model: Events-dən rebuild edilir
  Eventual: Read model populate olmaqda gecikə bilər

CQRS + eventual consistency:
  Write model: Strongly consistent
  Read model: Eventually consistent (async update)
  → Read model update lag = eventual consistency window
```

### 10. Business Impact Assessment
```
Eventual consistency window-nu ölç:
  P50 lag: 10ms → Çox az user fark edir
  P99 lag: 2s  → Bəzi user-lər köhnə data görür
  Max lag: 30s → Ciddi user experience problem

Business-a görə qərar:
  Social feed (Twitter-like):
    Lag 10s OK — user fresh posts görmür amma fark etmir
    Eventual consistency: YES

  Bank balansı:
    Lag 1ms bile OK deyil — overdraft riski
    Strong consistency: REQUIRED

  E-commerce inventory:
    "Only 2 left" göstərir, amma 0 olub
    → Overselling
    → Saga + reservation pattern + eventual consistency tolerable

  DNS TTL:
    Record dəyişdikdə 60s-5min propagate
    Acceptable — herkes bilir DNS eventual consistency-dir

CAP theorem decision:
  Network partition baş verdikdə:
    C seç → Some nodes unavailable (CP system)
    A seç → Stale data serve (AP system)
  
  Optimal: Tune per use-case
    User profile read → A (stale OK)
    Payment → C (correct required)
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Bu sistem eventual consistency-ni tolerate edə bilərmi?" sualını soru
2. Use case-ə görə consistency model seç (social feed vs financial)
3. Eventual consistency window-nu quantify et (ms? seconds?)
4. Konkret mitigation pattern-ları sırala (read-your-writes, monotonic reads)
5. Conflict resolution strategiyasını seç (LWW, CRDT, application-level)

### Follow-up Suallar
- "Eventual consistency window 30 saniyə olsa, user-lar nə görər?" → Business impact
- "Multi-region write conflict-ini necə həll edərsən?" → CRDT vs LWW vs application
- "Read-your-writes-i cassandra-da necə implement edərsən?" → Session routing, token
- "Eventual consistency yetərli olmayan yer var mı?" → Financial, inventory critical paths

### Ümumi Namizəd Səhvləri
- "Eventual consistency istifadə edirik, problem yoxdur" — heç bir mitigation qeyd etməmək
- Read-your-writes problemini görmək amma həll bilməmək
- CRDT nə olduğunu bilməmək
- Strong consistency-nin həmişə mümkün olmadığını (CAP) bilməmək
- Tunable consistency seçimini cost ilə əlaqələndirməmək

### Senior vs Architect Fərqi
**Senior**: Eventual vs strong consistency fərqini bilir, replication lag-ı izah edir, DynamoDB ConsistentRead option-ı tanıyır, basic mitigation (read-your-writes) həyata keçirir.

**Architect**: Consistency model-ini business requirement-lərə map edir (per-endpoint consistency budget), CRDT data structure-larını dizayn edir, Merkle tree anti-entropy mexanizmasını izah edir, multi-region conflict resolution strategiyasını seçir (LWW risk analizi, HLC clock), consistency model dəyişikliyinin məliyyat xərcinə (DynamoDB RCU) təsirini hesablayır, eventual consistency window-nu SLO kimi müəyyən edir ("95% of reads return data no older than 500ms").

## Nümunələr

### Tipik Interview Sualı
"Design the social feed for 500M users. Posts should appear near-real-time but the system must remain highly available during network partitions."

### Güclü Cavab
```
Social feed — eventual consistency dizayn:

Consistency analizi:
  Feed göstərmək: Lag 1-5s OK → AP system (eventual)
  Post silmək (abuse): Hər yerdə tez görünməli → CP component
  Like count: Approximate OK → CRDTs

Architecture:
  Write path:
    Post create → Primary DB (strong write)
    Async fan-out → Follower feed caches (eventual)
    Fan-out latency: P50 100ms, P99 2s

  Read path:
    Feed read → Redis cache (eventual, pre-computed)
    Cache miss → DB read (quorum read for freshness)
    Cache TTL: 60s (acceptable staleness)

Eventual consistency mitigation:
  1. Read-your-writes: 
     Author öz postunu dərhal görür
     Author session → Primary read for own posts

  2. Monotonic reads:
     User session → Same Redis cluster shard
     Shard down → Failover to next most-current

  3. Conflict resolution:
     Like count: G-Counter CRDT (always correct merge)
     Post edit: LWW with HLC (clock skew resistant)
     Post delete: OR-Set tombstone (delete wins)

  4. Anti-entropy:
     Feed cache vs DB inconsistency:
     Background job: Sample N users/minute, verify cache
     Stale → Rebuild from DB

Consistency window SLO:
  Feed freshness: 95th percentile < 3 seconds
  Post delete propagation: 100% within 30 seconds (abuse)
  Like count accuracy: ±5% acceptable

Monitoring:
  Replication lag histogram (P50, P95, P99, P100)
  Cache hit rate (low hit → more stale reads from DB)
  Fan-out latency (post → follower feed delay)
  "Missing post" user reports (support signal)
```

### Arxitektura Nümunəsi
```
User POST /posts
       │
       ▼
  ┌─────────┐   Strong Write   ┌───────────┐
  │  Write  │ ────────────────►│  Primary  │
  │ Service │                  │    DB     │
  └────┬────┘                  └─────┬─────┘
       │                             │ async replication
       │ Publish event               │
       ▼                      ┌──────▼──────┐
  ┌─────────┐                 │  Replica 1  │
  │  Kafka  │                 │  Replica 2  │
  └────┬────┘                 └─────────────┘
       │
       ▼ Fan-out workers (async, eventual)
  ┌────────────────────────────────────┐
  │   Feed Cache (Redis Cluster)       │
  │   User A feed: [post3, post1, ...] │
  │   User B feed: [post5, post2, ...] │
  │   TTL: 60s, LRU eviction           │
  └────────────────────────────────────┘
       │ Read (eventual, ~P99 2s lag)
       ▼
  ┌─────────┐
  │  Read   │  Author read → Primary (read-your-writes)
  │ Service │  Others → Redis cache
  └─────────┘
```

## Praktik Tapşırıqlar
- DynamoDB: Eventual vs strongly consistent read latency benchmark edin
- Cassandra: W=1,R=1 vs W=2,R=2 read consistency test edin
- CRDT G-Counter implement edin PHP-da: merge function yazın
- Read-your-writes: Session-based primary routing implement edin
- Anti-entropy simulation: 2 node, 10% data diff → Merkle tree reconciliation

## Əlaqəli Mövzular
- [12-cap-theorem-practice.md](12-cap-theorem-practice.md) — CAP theorem, C vs A tradeoff
- [07-database-sharding.md](07-database-sharding.md) — Replication strategies
- [17-distributed-transactions.md](17-distributed-transactions.md) — Strong consistency alternative
- [25-outbox-pattern.md](25-outbox-pattern.md) — Reliable event delivery with eventual consistency
- [24-leader-election.md](24-leader-election.md) — Consistency in leader-based systems
