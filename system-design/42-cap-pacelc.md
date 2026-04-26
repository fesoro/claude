# CAP & PACELC Theorem (Senior)

## İcmal

**CAP Theorem** (Eric Brewer, 2000) — distributed sistemdə eyni anda yalnız **2 xüsusiyyət** təmin oluna bilər: **Consistency**, **Availability**, **Partition tolerance**. Network partition baş verdikdə sistem C və A arasında seçim etməyə məcbur olur.

**PACELC Theorem** (Daniel Abadi, 2012) — CAP-ı genişləndirir: əgər **P**artition varsa **C** vs **A** seçirik, **E**lse (normal iş rejimində) **L**atency vs **C**onsistency arasında seçim edirik. Yəni partition olmasa belə, sistem yenə də trade-off-la üzləşir.

### Bir cümlədə fərq

- **CAP**: "Network bölünəndə availability-ni mi, consistency-ni mi saxlayım?"
- **PACELC**: "Normal işləyəndə belə, sürət üçün consistency-dən nə qədər güzəştə getməliyəm?"


## Niyə Vacibdir

'Consistency yoxsa Availability?' — bu sualı cavablamadan verilənlər bazası seçimi mümkün deyil. CAP teoremi interview-da mütləq soruşulur; PACELC onu normal iş rejiminə genişləndirir. Cassandra (AP), HBase (CP), PostgreSQL (CA partition olmadan) — real DB-ləri bu çərçivədə oxumaq lazımdır.

## Əsas Anlayışlar

### 1. CAP-ın 3 komponenti

| Hərf | Ad | İzahı |
|------|----|----|
| **C** | Consistency | Bütün node-lar eyni anda eyni data-nı qaytarır (linearizability) |
| **A** | Availability | Hər sorğu cavab alır (error deyil), data stale ola bilər |
| **P** | Partition Tolerance | Network node-lar arasında koppo olsa da sistem işləyir |

### 2. Niyə yalnız 2-ni seçmək olar?

Network partition baş verdikdə (məs. datacenter arası link qopur):

```
  Client                         Client
    │                              │
    ▼                              ▼
┌────────┐    X (partition)    ┌────────┐
│ Node A │ ────────────────── │ Node B │
│ val=5  │                    │ val=5  │
└────────┘                    └────────┘
    ▲                              ▲
    │ write val=10                 │ read val=?
```

Sistem 2 seçimdən birini etməlidir:
- **CP**: Node B cavab vermir (error qaytarır) — availability itirilir, amma stale data qaytarılmır
- **AP**: Node B köhnə val=5 qaytarır — available, amma inconsistent

**Partition tolerance seçilməyə bilər?** Real dünyada yox. Network həmişə qopur (GC pause, packet loss, slow link). Ona görə CAP praktiki olaraq **CP vs AP** seçiminə çevrilir.

### 3. CA sistemləri mövcuddurmu?

**Texniki olaraq xeyr** — distributed sistemdə partition qaçınılmazdır. Amma **single-node** database-lər (PostgreSQL on one machine, traditional RDBMS) CA adlandırıla bilər: partition yoxdur, C və A-nın hər ikisi var.

### 4. PACELC — partition olmasa nə olur?

CAP partition zamanı seçim haqqındadır. Amma **99% vaxt sistemlər normal işləyir**. O zaman nə trade-off var?

```
Partition varsa (P):    C (consistency) ⟷ A (availability)
Else (normal):          L (latency)     ⟷ C (consistency)
```

**Nümunə:** Synchronous replication strong consistency verir, amma hər yazı bütün replica-ları gözləyir (yüksək latency). Asynchronous replication isə aşağı latency verir, lakin replica-lar stale ola bilər.

## PACELC Təsnifatı

### Sistemlərin PACELC sinifləri

| Sistem | Sinif | İzahı |
|--------|-------|-------|
| **HBase**, **VoltDB**, **Megastore** | PC/EC | Həmişə consistency (partition-da da, normalda da) |
| **MongoDB** (default) | PC/EL | Partition-da C seçir, normalda L (secondary-dən oxu) |
| **Cassandra**, **DynamoDB**, **Riak** | PA/EL | Həmişə availability və latency (eventual) |
| **PostgreSQL sync replication** | PC/EC | Strong consistency həm partition-da, həm normalda |
| **MySQL async replication** | PA/EL | Primary ölsə available, replica lag mümkündür |
| **etcd**, **Zookeeper**, **Consul** | PC/EC | Consensus (Raft/Paxos) — həmişə consistency |
| **DynamoDB strong read** | PC/EC | Strong read seçilərsə |
| **DynamoDB eventual read** | PA/EL | Default rejim |

### CAP klassifikasiyası (praktiki)

| Sistem | CAP | Niyə |
|--------|-----|------|
| **PostgreSQL** (single node) | CA | Partition yoxdur |
| **PostgreSQL** (primary + replicas) | CP | Primary ölsə failover-a qədər yazı yoxdur |
| **MongoDB** | CP | Primary partition-da oxu/yazı blokdur (majority side-də işləyir) |
| **Cassandra** | AP | Hər node yazı qəbul edir, eventual sync |
| **DynamoDB** | AP (default) | Eventual reads default |
| **Riak** | AP | Dynamo-style, konflikt həlli client tərəfdə |
| **Redis** (master-replica) | AP | Async replication, master ölsə data itə bilər |
| **Redis Cluster** | AP | Minority partition-da yazı qəbul edilir, data itki mümkün |
| **etcd / Zookeeper** | CP | Raft/Zab consensus — quorum olmasa yazı yoxdur |
| **HBase** | CP | Region server ölsə partition-da oxu/yazı yoxdur |

## Nümunələr

### CP System — Bank Transaction

```php
<?php
// Laravel bank transfer — strong consistency tələb edir
use Illuminate\Support\Facades\DB;

class BankTransferService
{
    public function transfer(int $fromId, int $toId, int $amount): void
    {
        DB::transaction(function () use ($fromId, $toId, $amount) {
            // Row-level lock — partition-da transaction fail
            $from = Account::lockForUpdate()->findOrFail($fromId);
            $to = Account::lockForUpdate()->findOrFail($toId);

            if ($from->balance < $amount) {
                throw new InsufficientFundsException();
            }

            $from->decrement('balance', $amount);
            $to->increment('balance', $amount);
        });
        // Partition zamanı transaction abort — CP davranışı
    }
}
```

### AP System — Shopping Cart

```php
<?php
// DynamoDB-based shopping cart — availability priority
class ShoppingCartService
{
    public function addItem(string $userId, array $item): void
    {
        // Eventual consistency — user cart-ına item əlavə edə bilməlidir
        // hətta partition zamanı da
        $this->dynamodb->putItem([
            'TableName' => 'carts',
            'Item' => [
                'user_id' => ['S' => $userId],
                'item_id' => ['S' => $item['id']],
                'added_at' => ['N' => (string) time()],
                'version' => ['N' => (string) $this->nextVersion($userId)],
            ],
        ]);
    }

    public function getCart(string $userId): array
    {
        // Konflikt həlli: eyni item müxtəlif node-larda — birləşdir
        $items = $this->dynamodb->query([
            'TableName' => 'carts',
            'KeyConditionExpression' => 'user_id = :uid',
            'ExpressionAttributeValues' => [':uid' => ['S' => $userId]],
            'ConsistentRead' => false,  // eventual read — aşağı latency
        ]);

        return $this->mergeConflicts($items['Items']);
    }

    private function mergeConflicts(array $items): array
    {
        // Last-write-wins və ya union (cart item üçün union daha yaxşıdır)
        $merged = [];
        foreach ($items as $item) {
            $id = $item['item_id']['S'];
            $merged[$id] = $merged[$id] ?? $item;
        }
        return array_values($merged);
    }
}
```

### Laravel Read Replicas — PACELC Trade-off

```php
<?php
// config/database.php
'mysql' => [
    'write' => [
        'host' => env('DB_PRIMARY', 'primary.db'),
    ],
    'read' => [
        'host' => [
            env('DB_REPLICA_1', 'replica1.db'),
            env('DB_REPLICA_2', 'replica2.db'),
        ],
    ],
    'sticky' => true,  // read-your-writes guarantee (session üçün)
    'driver' => 'mysql',
],

// Service istifadəsi
class OrderService
{
    public function createOrder(array $data): Order
    {
        // Yazı → primary (strong consistency)
        return Order::create($data);
    }

    public function listOrders(int $userId): Collection
    {
        // Oxu → replica (eventual, aşağı latency)
        // PACELC: E (normal) rejimində L seçdik — stale data qəbul edirik
        return Order::where('user_id', $userId)->get();
    }

    public function getOrderForPayment(int $orderId): Order
    {
        // Kritik oxu → primary-dən zorla (strong consistency)
        // PACELC: E rejimində C seçdik — latency artır
        return Order::on('mysql')  // explicitly primary
            ->where('id', $orderId)
            ->firstOrFail();
    }
}
```

### Tunable Consistency — Cassandra R+W>N

```php
<?php
// Cassandra PHP driver ilə quorum ayarlaması
$cluster = Cassandra::cluster()
    ->withContactPoints('node1,node2,node3')
    ->build();
$session = $cluster->connect('app_keyspace');

// N = 3 (replication factor)
// W = 2, R = 2 → W + R > N → strong consistency
$statement = new Cassandra\SimpleStatement(
    'INSERT INTO users (id, email) VALUES (?, ?)'
);
$options = new Cassandra\ExecutionOptions([
    'arguments' => [$userId, $email],
    'consistency' => Cassandra::CONSISTENCY_QUORUM,  // W=2 of 3
]);
$session->execute($statement, $options);

// Oxu da QUORUM — R=2, W+R=4 > N=3, strong guarantee
$readStmt = new Cassandra\SimpleStatement('SELECT * FROM users WHERE id = ?');
$readOpts = new Cassandra\ExecutionOptions([
    'arguments' => [$userId],
    'consistency' => Cassandra::CONSISTENCY_QUORUM,
]);
$result = $session->execute($readStmt, $readOpts);

// Trade-off:
// - ONE: aşağı latency, eventual consistency
// - QUORUM: orta latency, strong consistency (əksər case-lər)
// - ALL: yüksək latency, strongest, lakin bir node ölsə unavailable
```

### DynamoDB Strong vs Eventual Read

```php
<?php
// Eventual read — default, daha ucuz (0.5 RCU)
$result = $dynamodb->getItem([
    'TableName' => 'orders',
    'Key' => ['id' => ['S' => $orderId]],
    'ConsistentRead' => false,  // ~ms latency
]);

// Strong read — 2x bahalı (1 RCU), latency daha yüksək
$result = $dynamodb->getItem([
    'TableName' => 'orders',
    'Key' => ['id' => ['S' => $orderId]],
    'ConsistentRead' => true,  // all replicas-dan təsdiq
]);
// Use case: payment status yoxlayan read
```

## Yanlış Təsəvvürlər (Misconceptions)

### 1. "CAP-dən 2-sini seç" çox sadələşdirilmişdir

Real həyatda seçim **binary deyil** — system **partition rejiminə** girəndə seçir. Normal vaxt hər üçü əldə edilə bilər. PACELC məhz bu boşluğu doldurur.

### 2. Partition binary deyil

"Partition var ya yox" deyil. **Partial partition-lar** olur: bəzi node-lar yavaşlayır (GC pause), packet loss 5%-dir, latency spike var. Sistem bu fuzzy vəziyyətlərdə necə davranır — əsas sualdır.

### 3. Consistency bir spektrdir

"Strong vs eventual" sadələşdirməsi var. Real həyatda:

```
Linearizable → Sequential → Causal → Read-your-writes → Monotonic → Eventual
```

Hər səviyyə fərqli latency/complexity verir.

### 4. "Cassandra AP-dir, MongoDB CP-dir" tam doğru deyil

Hər ikisi **tunable**-dir:
- Cassandra CONSISTENCY_ALL ilə CP kimi davranır
- MongoDB `w: 1, readPreference: secondary` ilə AP kimi davranır

## Real-World Nümunələr

### DNS — AP sistem

- TTL ilə caching → eventual consistency (24 saat propagation)
- Network partition-da DNS server-lər fərqli cavab verə bilər
- Niyə AP? Dünyanın hər yerində həmişə işləməlidir; stale data qəbul edilir

### Bank Core Banking — CP sistem

- Hesab balansı linearizable olmalıdır (ikiqat xərcləmə qadağandır)
- Partition-da transaction abort olur (better safe than sorry)
- Availability 99.9% kifayətdir; consistency 100% lazımdır

### Shopping Cart (Amazon) — AP with conflict resolution

- Cart item əlavə edə bilməyən user checkout-a getməz → $$$ itkisi
- 2 node-da eyni item əlavə edilərsə? **Union** (hər ikisi saxlanılır)
- İtem silinib-yox? **Add wins** (silinən item geri qayıda bilər — kiçik UX problemi)

### Google Spanner — paradox

TrueTime API (atom saat + GPS) ilə **həm CP həm low-latency** iddia edir. Gerçəkdə PC/EC-dir amma latency çox optimallaşdırılıb (~10ms cross-region). Bu da göstərir — CAP absolut deyil, mühəndislik sərhədlərini genişləndirmək olar.

## Interview-da Necə Seçim Etmək

### Decision Framework

1. **Data itkisi qəbul edilirmi?** Yox → CP (bank, inventory, auth)
2. **Downtime qəbul edilirmi?** Yox → AP (feed, cart, analytics)
3. **Stale data qəbul edilirmi?** Neçə saniyə? → tunable consistency seç
4. **Latency budget?** <10ms → eventual mütləqdir
5. **Geographic distribution?** Multi-region → AP və ya Spanner-style

### Trade-off Cədvəli

| Use Case | Seçim | Niyə |
|----------|-------|------|
| Bank transfer | CP | Data correctness > availability |
| Social feed | AP | Stale post qəbul edilir |
| Inventory (e-commerce) | CP | Oversell qadağandır |
| Product views counter | AP | +/- 100 view fərq etməz |
| User session | AP (sticky) | Session loss pis UX |
| Auth tokens | CP | Revoke dərhal olmalıdır |
| Chat messages | AP + causal | Mesaj sırası önəmli |
| Leaderboard | AP | Real-time approximation kifayətdir |

## Praktik Tapşırıqlar

### Q: CAP teoremində "2-ni seç" niyə yanlış sadələşdirmədir?

**A:** CAP yalnız **partition zamanı** seçimi təsvir edir. Normal işdə 3 xüsusiyyət eyni anda ola bilər (məs. PostgreSQL single node-da). Həm də P praktiki olaraq məcburidir (network həmişə qopa bilər), ona görə real seçim **CP vs AP**-dir. PACELC bunu tamamlayır — normal rejimdə də Latency vs Consistency trade-off var.

### Q: PACELC MongoDB default-unu PC/EL kimi təsnif edir. İzah et.

**A:** **PC** — partition baş verdikdə majority side-də primary qalır, minority isə oxu/yazı qəbul etmir (consistency üçün availability qurban verilir). **EL** — normalda read preference `secondary` və ya `nearest` qoyulsa, replica-lardan oxu gedir (aşağı latency), amma replica lag səbəbindən stale data gələ bilər (consistency-dən güzəşt). `readConcern: "majority"` ilə MongoDB PC/EC-yə çevrilə bilər.

### Q: Cassandra "AP" deyilir. Amma strong consistency əldə edə bilərikmi?

**A:** Bəli — **tunable consistency** ilə. Əgər `W + R > N` şərti ödənirsə (məs. N=3, W=2, R=2), quorum overlap zəmanət verir ki, oxu ən son yazını görəcək. Amma bu halda latency və availability azalır (bir node ölsə QUORUM fail). Cassandra "AP" default-dur, consistency opt-in-dir.

### Q: Laravel-də sticky connection PACELC-də hansı trade-off-dur?

**A:** `sticky => true` **read-your-writes** consistency verir: request daxilində write-dan sonra read primary-dən gəlir. Bu **E** rejimində **C** seçmək deməkdir — latency bir az artır (replica əvəzinə primary-dən oxu), amma öz yazdığını dərhal görürsən. Alternativ: bütün read-ləri replica-ya göndər (EL) — daha sürətli, amma user öz update-ini dərhal görməyə bilər.

### Q: Shopping cart üçün AP seçərsən. Partition zamanı user 2 fərqli node-da item əlavə etsə nə olur?

**A:** **Vector clock** və ya **CRDT** ilə hər node ayrı versiya saxlayır. Partition bitəndə 2 versiya birləşdirilir:
- **Union strategy** — hər iki cart-dakı item saxlanır (əlavə edildi wins)
- **CRDT (OR-Set)** — add/remove əməliyyatları commutative olur
- **Client-side merge** — Riak-da necə işləyir (sibling-lər client-ə göndərilir)

Amazon Dynamo paper-da bu şəkildə işləyirdi. Trade-off: bəzən silinmiş item geri qayıda bilər (kiçik UX xətası, amma $$$ itkisindən yaxşıdır).

### Q: etcd və Zookeeper niyə CP-dir? Niyə konfiqurasiya üçün AP seçmirik?

**A:** Service discovery, leader election, distributed locks — səhv data **split-brain**-ə gətirir (2 leader, 2 lock sahibi). Bu səbəbdən CP məcburidir. Raft/Paxos consensus protocol-ları **quorum** tələb edir — partition-ın minority tərəfi yazmır (availability itir), amma data korrekt qalır. Availability 99.9% kifayətdir (partition nadir hadisədir), consistency 100% tələb olunur.

### Q: Google Spanner CAP-ı "qırır"?

**A:** Xeyr, qırmır — mühəndislik optimizasiyası edir. Spanner **TrueTime** (atom saat + GPS, <7ms time uncertainty) istifadə edərək distributed transaction-ları effektiv şəkildə linearize edir. Yenə də PC/EC-dir (partition-da və normalda consistency), amma latency cross-region 10-100ms-ə endirilib. CAP-ı qırmır, amma "CP costly olmalıdır" mifini təkzib edir.

### Q: Redis Cluster "AP" sayılır. Data itkisi riski necədir?

**A:** Redis Cluster async replication istifadə edir — master acknowledgement göndərir **replica yazmamış**. Master partition zamanı ölsə, promote olan replica son yazıları itirir. Həm də minority partition-da master hələ yazı qəbul edir (müvəqqəti, `cluster-node-timeout`-a qədər) — data split yaradır. Həll: **WAIT** komandası (sync replication simulyasiya) və ya kritik data üçün Redis istifadə etmə (PostgreSQL seç).

## Praktik Baxış

- **CAP-ı absolut qayda kimi qəbul etmə** — PACELC daha dəqiqdir, normal rejim trade-off-larını göstərir.
- **Partition tolerance məcburidir** — real sistemlərdə CA sadəcə "indi partition görməyən" sistemdir.
- **Consistency-ni spektrə qoy** — strong vs eventual ikili deyil, causal/monotonic/read-your-writes ara səviyyələri var.
- **Tunable consistency istifadə et** — Cassandra/DynamoDB/MongoDB-də per-operation seçim; ucuz read eventual, kritik read strong.
- **Write path CP, read path AP** — kritik yazıları primary-də linearize et, read-ləri replica-dan ver (sticky session ilə read-your-writes təmin et).
- **Idempotency key** — AP sistemlərdə duplicate request olur, idempotency ilə qoru.
- **Konflikt həll strategiyasını əvvəlcədən planla** — LWW, CRDT, application-level merge.
- **Monitor replication lag** — PACELC-də "E" trade-off-unu ölçmək üçün.
- **Partition simulation** — chaos engineering ilə network qopmasını test et (Netflix Chaos Monkey, Toxiproxy).
- **Quorum formulas-ı yaz** — N, W, R dəyərləri data kritikliyinə görə (audit log N=5/W=3/R=3, cache N=3/W=1/R=1).
- **Failover strategy-ni sına** — primary ölsə neçə saniyə downtime? Manual vs automatic failover.
- **Cross-region latency-ni ölç** — cross-region strong consistency 100ms+ latency verir, user-ə yaxın region istifadə et.
- **Business trade-off-u aydın et** — "1 saniyə stale data qəbul edilirmi?" product owner ilə müzakirə.
- **Vendor default-larını bilmə** — MongoDB `w: 1` default-dur (primary ack), `w: "majority"` strong; DynamoDB default eventual-dir.
- **Interview-da "asılıdır" de** — use case-i soruşmadan CP/AP seçmə; data kritikliyi, latency budget, multi-region tələbini öyrən.


## Əlaqəli Mövzular

- [Consistency Patterns](32-consistency-patterns.md) — consistency modellər spektri
- [Database Replication](43-database-replication.md) — replication consistency tipleri
- [Distributed Systems](25-distributed-systems.md) — distributed sistem fundamentalları
- [SQL vs NoSQL](41-sql-vs-nosql-selection.md) — CAP çərçivəsində DB seçimi
- [Raft/Paxos](84-raft-paxos-consensus.md) — CP sistem implementation
