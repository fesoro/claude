# CAP Theorem in Practice (Lead ⭐⭐⭐⭐)

## İcmal
CAP Theorem, distributed sistemin eyni zamanda üç xüsusiyyəti — Consistency, Availability, Partition Tolerance — tam olaraq təmin edə bilmədiyini ifadə edir. Bu nəzəriyyə Eric Brewer tərəfindən 2000-ci ildə irəli sürülüb. Interview-larda CAP-ı "CP vs AP" kimi formalist yox, real database seçimi, trade-off qərarı kontekstinde müzakirə etmək namizədi fərqləndirir.

## Niyə Vacibdir
CAP theorem bilmək hər distributed system interview-unda vacibdir. Lakin əsl Senior/Lead bacarığı — "Bu sistem CP mi AP mi olmalıdır?" sualına kontekstdən asılı olaraq cavab vermək, PACELC extension-ı, tunable consistency (Cassandra), BASE vs ACID fərqini izah etmək. Bu mövzunu həm nəzəri, həm praktik bilmək lazımdır.

## Əsas Anlayışlar

### 1. CAP Hərflərinin Mənası

**C — Consistency (Ardıcıllıq)**
Hər oxuma son yazmanı qaytarır (ya da error qaytarır).
```
Node A-ya yaz: x = 5
Node B-dən oxu: x = ? 
Consistent sistem: x = 5 qaytarır
Inconsistent sistem: köhnə qiymət (x = 3) qaytara bilər
```

**A — Availability (Əlçatanlıq)**
Hər sorğu (xəta olmayan node-lara) cavab alır (error deyil).
- Cavab köhnə ola bilər
- Amma CAVAB alınır (timeout deyil)

**P — Partition Tolerance (Bölünmə Tolerantlığı)**
Sistem, node-lar arasında şəbəkə bölünməsi (network partition) olduqda işləməyə davam edir.
```
Node A ←→ Network Split ←→ Node B
Node A işləyir, Node B işləyir
Amma bir-birləri ilə danışa bilmirlər
```

### 2. Niyə 3-dən yalnız 2-si?
```
Network partition realdır — "P" always assumed.
Real choice: CP or AP

CP (Consistency + Partition Tolerance):
  Network split olduqda → availability qurban ver
  Node A daha xəbər ala bilmir → sorğulara error qaytarır
  Consistent, amma unavailable

AP (Availability + Partition Tolerance):
  Network split olduqda → consistency qurban ver
  Node A köhnə data ilə cavab verir
  Available, amma potentially stale
```

**CA olmur** (real distributed sistemdə):
- CA seçmək = "Network partition heç vaxt olmaz" demək
- Distributed sistemdə realist deyil
- Single-node RDBMS "CA" hesab olunur (distributed deyil ki)

### 3. Real Database Seçimləri

**CP Sistemlər:**
- **HBase**: Strong consistency, ZooKeeper-based coordination
- **Zookeeper**: CP by design (leader election, coordination)
- **MongoDB** (default write concern majority)
- **Redis Cluster**: Partition zamanı some nodes unavailable
- **Etcd, Consul**: Raft consensus → CP

**AP Sistemlər:**
- **Cassandra**: Tunable consistency, default AP
- **CouchDB**: "Eventual consistency" design
- **Amazon DynamoDB** (eventual consistency mode)
- **Riak**: AP by design

**CA Sistemlər (single-node ya da tightly-coupled):**
- **PostgreSQL** (single node)
- **MySQL** (single node)
- **Traditional RDBMS** (not distributed)

### 4. PACELC Extension (Daniel Abadi, 2012)
CAP-ı tamamlayır:
```
P artdıqda (network partition):
  A ya da C seçirik (CAP)
  
E əgər Else (partition yoxdursa, normal operation):
  L (Latency) ya da C (Consistency) seçirik
  
PACELC = PA/EL (Cassandra) ya da PC/EC (HBase)
```

**Cassandra PACELC:**
- P olduqda: A (availability) seçir (AP)
- Normal vəziyyətdə: L (low latency) seçir (EL)
- Cassandra PA/EL sistemidir

**HBase PACELC:**
- P olduqda: C (consistency) seçir (CP)
- Normal vəziyyətdə: C (consistency) saxlayır (EC)
- HBase PC/EC sistemidir

### 5. Tunable Consistency (Cassandra nümunəsi)
```
Write Consistency Levels:
- ONE: 1 node yazır → fast, low consistency
- QUORUM: majority yazır → balanced
- ALL: bütün node-lar yazır → slow, highest consistency
- LOCAL_QUORUM: region daxilinde majority

Read Consistency Levels:
- ONE: 1 node-dan oxu → fast, possibly stale
- QUORUM: majority-dən oxu → fresher
- ALL: bütün node-lardan oxu → most consistent

Strong consistency formula:
  Write CL + Read CL > Replication Factor
  
Nümunə: RF=3
  Write QUORUM (2) + Read QUORUM (2) = 4 > 3 ✓ (strong consistency)
  Write ONE (1) + Read ONE (1) = 2 < 3 (eventual consistency)
```

### 6. ACID vs BASE
```
ACID (Traditional RDBMS):
  Atomicity   — ya hamısı, ya heç biri
  Consistency — data invariant-lar qorunur
  Isolation   — concurrent tx-lər bir-birini görmür
  Durability  — committed data itirilmir

BASE (NoSQL, distributed):
  Basically Available  — sistem adətən cavab verir
  Soft state           — sistem state dəyişə bilər zamanla
  Eventual Consistency — eventual olaraq consistent olacaq

Əksər NoSQL sistemlər BASE yanaşması izləyir.
```

### 7. Practical Consistency Patterns

**Read-your-writes consistency:**
```
Siz yazdınız → siz oxuyursunuz → öz write-ınızı görürsünüz
Digər user-lər hələ görmeyə bilər

Implementation:
- Write → primary
- Read → eyni primary (session-based routing)
- Ya da: yazma timestamp-i saxla, bu timestamp-dən sonrakı replica-ya yönləndir
```

**Monotonic reads:**
```
Bir dəfə daha yeni data gördünsə, köhnə data görməyəcəksən
T1: x=5 görürsən
T2: x=3 görürsən ← YANLISH (monotonic read violation)

Implementation: User həmişə eyni replica-ya routing
```

**Causal consistency:**
```
A yazır (cause) → B oxuyur → B yazır (effect)
B-nin yazısı A-nın yazısından sonra gəlir

Vector clocks ilə implement edilir
```

### 8. Interview-da CAP Soruşulduqda Real Cavab
```
Sual: "Ödəniş sistemi üçün CP mi AP mi seçərdiniz?"

Zəif cavab: "CP seçərdim çünki consistency vacibdir"

Güclü cavab:
"Ödəniş sistemi üçün CP seçərdim.

Consistency:
- $100 deducted from account A
- $100 added to account B
- Bu iki əməliyyat ya hər ikisi olmalı, ya heç biri

Network partition zamanı:
- Consistency qurban vermirəm
- Availability qurban verirəm: sorğu error qaytarır
- User: "Service temporarily unavailable, retry"
- Bu acceptable-dir — yanlış balance göstərməkdən yaxşıdır

PACELC:
- Normal operation-da da consistency üstündür
- Latency artsa belə strong consistency istəyirik
- PC/EC sistemdir: HBase, CockroachDB, Spanner

Əgər user experience üçün AP lazım olsa:
- Balance view: AP ok (slightly stale ok)
- Transfer operation: CP mütləq lazımdır
- Hybrid: read AP, write CP
```

### 9. Eventual Consistency Nə Zaman OK?
```
OK olan hallar:
- Social media like count (+/- 1000 fərq görünən əhəmiyyətsizdir)
- Product view counter
- Search index update (biraz gecikmə ok)
- DNS propagation (minutes/hours ok)
- Shopping cart (minor staleness ok)

OK olmayan hallar:
- Bank balance
- Inventory (sold stock cannot be oversold)
- Authentication tokens (revoked token must be invalid immediately)
- Medical records
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Bu sistem üçün hansı consistency tələb edir?" soruşun
2. Network partition-ı real scenario kimi qəbul et (P həmişə var)
3. "CP seçirəm çünki business requirement X" — əsaslandır
4. PACELC-i bilsən əlavə point qazanırsan
5. Tunable consistency (Cassandra) — hybrid yanaşma imkanı qeyd et

### Ümumi Namizəd Səhvləri
- "CA sistemi istifadə edirəm" demək (distributed sistemdə mümkün deyil)
- CAP-ı nəzəri izah etmək, real database seçimi ilə əlaqələndirməmək
- "Consistency həmişə daha yaxşıdır" düşüncəsi (availability da vacibdir)
- ACID vs BASE fərqini bilməmək
- Partition tolerance-ın niyə abandon edilmədiğini izah etməmək

### Senior vs Architect Fərqi
**Senior**: CP vs AP seçimini business context-də edir, Cassandra tunable consistency bilir, ACID vs BASE izah edir.

**Architect**: CAP-ın limitasiyalarını bilir (CAP theorem dəqiq formal deyil), PACELC ilə genişləndirir, "consistency spectrum" — strict serializability-dən eventual consistency-yə — bütün nivolar bilir (linearizability, sequential, causal, monotonic, eventual), Google Spanner external consistency-ni bilir, multi-region consistency trade-off-larını planlaşdırır.

## Nümunələr

### Tipik Interview Sualı
"You are designing a distributed inventory system for flash sales. 1M concurrent users, 1000 items available. How do you handle consistency?"

### Güclü Cavab
```
Flash sale inventory consistency problemi:

Challenge:
- 1M user, 1000 item
- Oversell problem: 2000 user eyni anda "buy" basırsa?
- AP seçsək: stale inventory → oversell
- CP seçsək: consistency amma latency/availability riski

Strategiya: Layered consistency

Layer 1: Display inventory (AP ok)
  "999 items left" göstərmək üçün Redis cache
  Slightly stale (5-10s) → ok, UI-da dəqiq sayı lazım deyil

Layer 2: Reserve inventory (CP required)
  Redis DECR + Lua script (atomic):
  ```lua
  local stock = redis.call('GET', 'item:stock')
  if tonumber(stock) > 0 then
      redis.call('DECR', 'item:stock')
      return 1  -- reserved
  end
  return 0  -- sold out
  ```
  Redis single-threaded → atomic operation
  No race condition

Layer 3: Confirm purchase (Database, CP)
  Redis reserve → DB write (saga pattern)
  If DB write fails → Redis inventory restored

Network partition:
  Redis unavailable → fail close (no oversell)
  User: "Service temporarily unavailable"
  Better than overselling 200 extra items

Alternative: Distributed lock (Redlock)
  Pessimistic: Lock item during purchase
  Simpler but lower throughput

Result: Hybrid approach
  Read: AP (cache, slightly stale ok)
  Write: CP (atomic Redis + DB)
  Failure mode: fail close (consistency over availability)
```

## Praktik Tapşırıqlar
- Cassandra-da ONE vs QUORUM consistency level latency müqayisəsi
- Redis ilə atomic inventory decrement implement edin
- Bank transfer simulasiyası: CP, network partition zamanı behavior
- CockroachDB vs Cassandra: real consistency comparison
- Vector clocks manual simulation: causal consistency nümunəsi

## Əlaqəli Mövzular
- [06-database-selection.md](06-database-selection.md) — CP/AP database seçimi
- [23-eventual-consistency.md](23-eventual-consistency.md) — Eventual consistency patterns
- [17-distributed-transactions.md](17-distributed-transactions.md) — Cross-service consistency
- [13-idempotency-design.md](13-idempotency-design.md) — Idempotency with eventual consistency
- [07-database-sharding.md](07-database-sharding.md) — Consistency in sharded systems
