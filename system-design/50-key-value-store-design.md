# Distributed Key-Value Store Design (Dynamo-style)

## Nədir? (What is it?)

Distributed key-value store — sadə `get(key)` və `put(key, value)` API-si təqdim edən, yüzlərlə node arasında horizontal scale olunan paylanmış storage sistemidir. Amazon-un 2007-ci ildəki **Dynamo paper**-i bu dizaynın əsasını qoydu. Real sistemlər: **DynamoDB** (AWS managed), **Apache Cassandra** (open source), **Riak** (Erlang ilə), **Voldemort** (LinkedIn).

Əsas fərq relational DB-dən: **no joins, no complex queries, no ACID transactions across keys** — bunun əvəzinə **massive scale, high availability, predictable latency** (p99 < 10ms) verir.

```
Relational DB              Key-Value Store (Dynamo)
-------------              -----------------------
SELECT * FROM ...          GET key
complex queries            simple get/put
vertical scale             horizontal scale (thousands of nodes)
strong consistency         tunable consistency (AP by default)
single master              masterless, peer-to-peer
```

## Requirementlər (Requirements)

**Functional:**
- `put(key, value)` — value yaz (opsional: context/version)
- `get(key)` — value oxu (opsional: bir neçə version qaytara bilər)
- `delete(key)` — key sil (tombstone marker)

**Non-functional (Dynamo priority order):**
1. **High availability** — "always writeable" — shopping cart heç vaxt itməməlidir, hətta network partition zamanı
2. **Low latency** — p99 < 10-20ms (SSD + local storage)
3. **Incremental scalability** — 10 → 1000 node-a ağrısız keçid
4. **Tunable consistency** — per-operation trade-off (fast vs consistent)
5. **Decentralized** — no single point of failure, peer-to-peer

**CAP theorem:** Dynamo **AP** seçir — partition zamanı availability-ni consistency-yə qurban vermir. Eventual consistency default-dur.

## Arxitektura (Architecture)

```
                   ┌──────────────────────────────────┐
                   │         Smart Client             │
                   │   (partition-aware, caches ring) │
                   └────────────┬─────────────────────┘
                                │
                     ┌──────────┴──────────┐
                     │                     │
                     ▼                     ▼
              ┌─────────────┐       ┌─────────────┐
              │ Coordinator │  ◄──► │ Coordinator │
              │   Node A    │ gossip│   Node B    │
              └──────┬──────┘       └──────┬──────┘
                     │                     │
        ┌────────────┼────────────┬────────┼────────┐
        ▼            ▼            ▼        ▼        ▼
   ┌────────┐  ┌────────┐   ┌────────┐ ┌────────┐ ┌────────┐
   │ Node 1 │  │ Node 2 │   │ Node 3 │ │ Node 4 │ │ Node N │
   │ vnodes │  │ vnodes │   │ vnodes │ │ vnodes │ │ vnodes │
   │ LSM    │  │ LSM    │   │ LSM    │ │ LSM    │ │ LSM    │
   └────────┘  └────────┘   └────────┘ └────────┘ └────────┘
```

Hər node **eyni rolu oynayır** — master yoxdur. Hər biri həm coordinator, həm storage, həm membership manager ola bilər.

## Əsas Komponentlər (Core Components)

### 1. Consistent Hashing Ring

Key-ləri node-lara map etmək üçün. Klassik `hash(key) % N` pisdir — node əlavə edəndə/silinəndə **bütün key-lər yenidən paylanır**. Consistent hashing-də yalnız **1/N key** hərəkət edir.

```
                    Hash Ring (0 to 2^128)
                           ▲
                           │  key_A → hash → position 45
                     ┌─────┴─────┐
                     │   Node1   │  (positions 0-90)
                     │  (vnodes) │
                     │           │
                   Node4       Node2
                   (270-360)   (90-180)
                     │           │
                     │   Node3   │
                     │ (180-270) │
                     └───────────┘

Key "user_42" → hash = 123 → falls in Node2's range
```

**Virtual nodes (vnodes):** hər fiziki node ring-də **100-256 vnode** götürür. Faydası:
- Heterogeneous hardware (güclü node-a daha çox vnode)
- Node fail olanda yükü **çox node arasında** paylaşır (hamısı bir qonşuya düşmür)
- Rebalancing smooth olur

### 2. Replication Factor (N)

Hər key **N ardıcıl node-da** saxlanılır (preference list). Tipik N=3.

```
      key_X hash → falls at position P
            │
            ▼
    ┌───────────────────────────┐
    │  Preference list for X:   │
    │    Node A (position P)    │  ← primary replica (coordinator)
    │    Node B (next on ring)  │  ← replica 2
    │    Node C (next on ring)  │  ← replica 3
    └───────────────────────────┘
            │
            │  skip vnodes that belong to same physical node
            │  → ensures replicas are on DIFFERENT physical nodes
            ▼
     Cross-datacenter: preference list includes nodes
     from different racks/DCs for fault tolerance
```

### 3. Quorum (R + W > N)

Hər əməliyyat üçün R (read) və W (write) tunable-dır:

```
   N = 3  (total replicas)
   W = 2  (wait for 2 write acks)
   R = 2  (read from 2 replicas)

   R + W > N  →  2 + 2 > 3  →  strong consistency ✓
   (latest write mütləq oxunanlardan birində olacaq)
```

**Tipik konfiqlər:**
| Config          | N | W | R | Use case                    |
|-----------------|---|---|---|-----------------------------|
| Strong          | 3 | 2 | 2 | Banking, inventory          |
| Fast write      | 3 | 1 | 3 | High write throughput       |
| Fast read       | 3 | 3 | 1 | Read-heavy (rare)           |
| Eventual        | 3 | 1 | 1 | Cache, analytics            |
| W=N read repair | 3 | 3 | 1 | Guaranteed consistency      |

### 4. Vector Clocks (Versioning)

Network partition zamanı eyni key iki node-da divergent update ala bilər. Last-write-wins (LWW timestamp) sadədir amma **data itkisi** riski var (saat sync deyilsə). Dynamo **vector clocks** işlədir — causality track edir.

```
Initial state: key "cart" = {apple}, version = []

Client1 writes (via Node A):  cart = {apple, banana}
   version: [(A, 1)]

Network partition!

Client2 writes (via Node B):  cart = {apple, orange}
   version: [(A, 1), (B, 1)]   ← sees A=1, adds B=1

Client3 writes (via Node C, concurrent):
   cart = {apple, milk}
   version: [(A, 1), (C, 1)]

Partition heals → read returns 2 divergent versions:
   v1: [(A,1), (B,1)] = {apple, orange}
   v2: [(A,1), (C,1)] = {apple, milk}

Neither is ancestor of other → CONFLICT
Client must resolve (e.g., merge) → {apple, orange, milk}
   new version: [(A,1), (B,1), (C,1)]
```

Shopping cart üçün merge = union. Digər domenlərdə domain logic lazımdır.

### 5. Sloppy Quorum + Hinted Handoff

Strict quorum high availability-ni pozur — əgər N replica-dan 2-si down-dursa, W=2 write edə bilməzsən. **Sloppy quorum**: bunun əvəzinə **istənilən N sağlam node-a** yaz.

```
Normal:           Sloppy (Node C down):
A (primary) ✓     A ✓
B           ✓     B ✓
C           ✓     D ✓ ← "hint: this is for C"

Node D stores: { data, hint: "belongs to C" }
  ↓ (C gələndə)
Node D periodically tries to reach C
  ↓
Node C online → D sends data → C applies → D deletes hint
```

Bu **"always writeable"** guarantee-sini mümkün edir.

### 6. Anti-entropy with Merkle Trees

Hinted handoff itə bilər (D crash olsa). Background **anti-entropy** replica-lar arasında inconsistency tapır. Hər key-i müqayisə etmək baha — **Merkle tree** ilə sürətli diff:

```
              Root hash
             /         \
         H(L)           H(R)
        /    \         /    \
      H1     H2      H3     H4
      │      │       │      │
    keys   keys    keys   keys
   0-25%  25-50%  50-75%  75-100%

Compare Node A's root with Node B's root
  → if different, recurse into children
  → if leaf differs, sync only those keys
  → O(log n) network traffic for finding diffs
```

### 7. Membership & Failure Detection

**Gossip protocol** — hər node hər saniyə 1-3 random node-la state mübadiləsi edir (alive peers, ring positions). Tam cluster view bir neçə saniyəyə yayılır.

**Phi accrual failure detector** — binary "up/down" əvəzinə **suspicion level** (0.0 → 8.0+) verir. Network latency-ə uyğunlaşır. Threshold keçəndə node "down" elan edilir.

### 8. Local Storage Engine

- **Cassandra, DynamoDB:** LSM-tree (Log-Structured Merge) — memtable (RAM) → SSTable (disk), periodic compaction. Write-optimized.
- **BerkeleyDB, Riak-default:** B-tree — read-optimized.
- **Write-ahead log (WAL):** hər write əvvəlcə commit log-a yazılır → crash recovery üçün.

## Praktiki Nümunələr (Practical Examples)

### Capacity Estimation

```
1B keys × 1KB avg value = 1 TB raw
Replication factor 3 → 3 TB total
With overhead (indexes, bloom filters, tombstones): ~4.5 TB

100k QPS → 30k writes/s, 70k reads/s
Per node (cluster of 20): 5k QPS → feasible on SSD

Bandwidth: 100k × 1KB = 100 MB/s per DC
```

### Laravel/PHP — DynamoDB Session Storage

`config/session.php`:

```php
'driver' => 'dynamodb',
'connection' => 'aws',
'table' => 'sessions',
```

AWS SDK ilə birbaşa put/get:

```php
use Aws\DynamoDb\DynamoDbClient;

class UserCartRepository
{
    private DynamoDbClient $client;

    public function __construct()
    {
        $this->client = new DynamoDbClient([
            'region' => 'eu-central-1',
            'version' => 'latest',
        ]);
    }

    public function put(string $userId, array $cart): void
    {
        $this->client->putItem([
            'TableName' => 'carts',
            'Item' => [
                'user_id' => ['S' => $userId],
                'items' => ['S' => json_encode($cart)],
                'updated_at' => ['N' => (string) time()],
            ],
            // Consistency seçimi yoxdur — write always goes to leader
        ]);
    }

    public function get(string $userId, bool $strong = false): ?array
    {
        $result = $this->client->getItem([
            'TableName' => 'carts',
            'Key' => ['user_id' => ['S' => $userId]],
            'ConsistentRead' => $strong, // R=quorum if true, else eventual (R=1)
        ]);

        if (!isset($result['Item'])) {
            return null;
        }

        return json_decode($result['Item']['items']['S'], true);
    }
}
```

DynamoDB-də `ConsistentRead=true` → R=quorum (2 of 3), latency 2x amma strong. Default eventual (R=1) — ucuz və sürətli.

### Cassandra CQL Example

```sql
-- Keyspace with replication
CREATE KEYSPACE shop WITH replication =
  {'class': 'NetworkTopologyStrategy', 'dc1': 3};

-- Per-query tunable consistency
CONSISTENCY QUORUM;  -- R + W > N
INSERT INTO cart (user_id, item) VALUES ('u42', 'book');

CONSISTENCY ONE;     -- fast, eventual
SELECT * FROM cart WHERE user_id = 'u42';
```

## Interview Sualları (Interview Questions)

**Q1: Consistent hashing vs modulo hashing — niyə?**
Modulo-da (`hash % N`) node əlavə/silinəndə **bütün key-lər yenidən paylanır** → massive data migration. Consistent hashing-də yalnız **1/N key** hərəkət edir (yeni node öz segment-ini götürür). Virtual node-lar hardware heterogeneity və balanced rebalancing üçündür.

**Q2: Niyə vector clocks? Timestamp bəs deyil?**
Timestamp (last-write-wins) clock skew-ə həssasdır — iki server arasında 1 saniyə fərq olsa, legitimate write "köhnə" sayılıb silinə bilər. Vector clock **causality** göstərir: v1 v2-nin ancestor-udur, yoxsa concurrent-dirlər? Concurrent conflict-ləri client (domain logic) həll edir.

**Q3: CAP-də Dynamo niyə AP seçir?**
Amazon shopping cart use case — partition zamanı "add to cart" heç vaxt fail olmamalıdır (revenue itkisi). Eventual inconsistency tolerable — sonra conflict resolve olunur. Banking üçün CP seçərdin (e.g., Spanner).

**Q4: Sloppy quorum hansı trade-off gətirir?**
**Pro:** availability — həmişə W replica tapa bilirsən. **Con:** strong consistency zəifləyir — "top N" yox, "any N" yazırsan. Hinted handoff gecikərsə və primary gələn kimi read olunarsa, stale data oxuya bilərsən. Real strong üçün strict quorum lazımdır.

**Q5: Read repair və anti-entropy fərqi nədir?**
**Read repair** — read zamanı coordinator R replica-dan fərqli version görəndə, arxa planda latest-i köhnə replica-lara göndərir (passive, hot data üçün). **Anti-entropy (Merkle trees)** — background scheduled process, bütün key range-ləri müqayisə edir (cold data və uzun partition-dan sonra). İkisi bir-birini tamamlayır.

**Q6: LSM-tree niyə write-heavy workload üçün yaxşıdır?**
Writes sequential append-a çevrilir (memtable → WAL → SSTable flush). B-tree-də hər write random disk I/O tələb edir (page split). Trade-off: read amplification (bir neçə SSTable-a baxmalısan) — bloom filter + compaction bunu azaldır.

**Q7: DynamoDB vs Cassandra — nə zaman hansını seçərsən?**
**DynamoDB** — managed (AWS), zero ops, auto-scaling, item-level ACID transactions, global tables multi-region. Bahalıdır, AWS lock-in. **Cassandra** — open source, on-prem/multi-cloud, tam control (tuning, compaction), amma operationally ağırdır. Startup → DynamoDB; high-scale enterprise with ops team → Cassandra.

**Q8: Hash ring-də virtual node sayını necə seçəsən?**
Çox az (e.g., 10) → unbalanced load. Çox çox (e.g., 10,000) → metadata overhead (ring state gossip edilməlidir). Cassandra default **256 tokens per node**. Production-da 128-512 interval-ında. Heterogeneous cluster-də güclü node-a daha çox token verilir.

## Best Practices

- **N=3 default** — fault tolerance və cost arasında balans. N=5 critical production üçün
- **W=2, R=2 strong** — lazım olanda; **W=1, R=1 fast** — cache kimi use case üçün
- **Key design** — high-cardinality partition key seç (hot partition-dan qaç); composite key (`user_id#order_id`) pattern
- **Avoid large values** — DynamoDB 400KB limit, Cassandra 2GB (amma 1MB tövsiyə)
- **Tombstones** — delete logical-dır; compaction-dan əvvəl qalırlar; massive delete-lər read perf-ni sındıra bilər
- **Monitor p99 latency, not avg** — Dynamo-nun əsas guarantee-si p99.9 SLA-dır
- **Gossip bandwidth** — 1000+ node cluster-də gossip overhead-ə diqqət, `phi_convict_threshold` tune et
- **Backup strategy** — snapshot + incremental; DynamoDB point-in-time recovery (PITR)
- **Don't use as relational DB** — JOIN-ə ehtiyac varsa, denormalize et və ya PostgreSQL işlət
- **DynamoDB transactions** — item-level `TransactWriteItems` var (25 item limit) — lazımdırsa istifadə et, amma scale cost-u bil
- **Hot partition mitigation** — write-sharding (`user_id#random(0-9)`); adaptive capacity DynamoDB-də avtomatik
- **TTL** — expired data-nı avtomatik sil (session, cache) — manual delete-dən ucuz
