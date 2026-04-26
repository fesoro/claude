# NoSQL vs SQL (Middle)

## Mündəricat
1. [SQL vs NoSQL Əsas Fərqlər](#sql-vs-nosql-əsas-fərqlər)
2. [CAP Theorem](#cap-theorem)
3. [NoSQL Növləri](#nosql-növləri)
4. [MongoDB](#mongodb)
5. [Cassandra](#cassandra)
6. [Nə Zaman Hansını Seçmək?](#nə-zaman-hansını-seçmək)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## SQL vs NoSQL Əsas Fərqlər

```
// Bu kod SQL və NoSQL verilənlər bazalarının əsas xüsusiyyətlərini müqayisəli göstərir
┌─────────────────┬────────────────────┬───────────────────────┐
│                 │       SQL          │       NoSQL           │
├─────────────────┼────────────────────┼───────────────────────┤
│ Schema          │ Fixed, strict      │ Flexible, dynamic     │
│ Relations       │ JOIN, FK           │ Embedded / denorm.    │
│ ACID            │ ✅ Full            │ Depends (often BASE)  │
│ Scale           │ Vertical (mainly)  │ Horizontal            │
│ Query language  │ SQL (standard)     │ Varies per DB         │
│ Consistency     │ Strong             │ Eventual (mostly)     │
│ Best for        │ Structured, OLTP   │ Unstructured, scale   │
└─────────────────┴────────────────────┴───────────────────────┘

BASE (NoSQL consistency model):
  BA: Basically Available
  S:  Soft state
  E:  Eventually consistent
  
  → SQL: ACID (strong)
  → NoSQL: BASE (weak, eventual)
```

---

## CAP Theorem

```
// Bu kod CAP teoremini CP və AP seçimləri ilə distributed sistemlər kontekstində izah edir
CAP Theorem: Distributed sistemdə aşağıdakı 3-dən yalnız 2-sini eyni anda təmin etmək olar:

  C — Consistency:    Hər read ən son write-ı görür
  A — Availability:   Hər sorğu cavab alır (error olmaya bilər)
  P — Partition Tolerance: Network partition-a dözümlülük

                    C
                   /|\
                  / | \
                 /  |  \
                CA  |  CP
               /    |    \
              /  ?  |  ?  \
             A──────────────P
                    AP

Real dünyada: Network partition qaçılmazdır → P seçilməlidir
Ona görə: CP vs AP seçimi

CP (Consistency + Partition Tolerance):
  Network partition olduqda availability qurban verilir
  Bəzi node-lar cavab vermir (consistency üçün)
  Nümunə: HBase, Zookeeper, MongoDB (strong mode)
  Nə zaman: Banking, financial, inventory

AP (Availability + Partition Tolerance):
  Network partition olduqda consistency qurban verilir
  Hər node cavab verir, amma stale data ola bilər
  Nümunə: Cassandra, DynamoDB, CouchDB
  Nə zaman: Social feed, shopping cart, DNS

CA (Consistency + Availability):
  Distributed deyil — single node
  Nümunə: Traditional SQL (single server)
  Partition olduqda ikisi də pozulur
```

---

## NoSQL Növləri

```
// Bu kod NoSQL verilənlər bazasının beş əsas növünü istifadə halları ilə izah edir
1. Document Store:
   JSON/BSON document-lər
   Flexible schema
   MongoDB, CouchDB, Firestore
   Nümunə: E-commerce products (müxtəlif attributes)

2. Key-Value Store:
   Sadə key → value
   Yüksək throughput
   Redis, DynamoDB, Riak
   Nümunə: Session, cache, user preferences

3. Column-family Store (Wide Column):
   Row key + column families
   Write-heavy, time-series
   Cassandra, HBase
   Nümunə: IoT metrics, event log, audit trail

4. Graph Database:
   Nodes + Edges + Properties
   Relationship-heavy queries
   Neo4j, Amazon Neptune
   Nümunə: Social network, fraud detection, recommendation

5. Time Series:
   Timestamp-indexed data
   Efficient time-range queries
   InfluxDB, TimescaleDB, Prometheus
   Nümunə: Metrics, monitoring, IoT sensor data
```

---

## MongoDB

```
// Bu kod MongoDB-nin embedded document modelini SQL multi-table strukturu ilə müqayisə edir
Document model:
{
  "_id": ObjectId("..."),
  "order_id": "ORD-001",
  "customer": {
    "id": 123,
    "name": "Ali Əliyev",
    "email": "ali@example.com"
  },
  "items": [
    {"product_id": 1, "name": "Laptop", "qty": 1, "price": 1200},
    {"product_id": 2, "name": "Mouse",  "qty": 2, "price": 25}
  ],
  "total": 1250,
  "status": "confirmed",
  "created_at": ISODate("2024-01-15")
}

SQL-də: orders + customers + order_items → 3 cədvəl, JOIN lazım
MongoDB-də: 1 document, JOIN yoxdur → read sürətli

✅ Flexible schema (product attributes fərqli ola bilər)
✅ Embedded documents → az JOIN
✅ Horizontal scale (sharding)
❌ JOIN zəifdir
❌ Multi-document transactions (4.0+ var, amma overhead)
❌ ACID yalnız single document-də (by default)

Nə zaman MongoDB:
  → Product catalog (müxtəlif attributes: elektronika vs geyim)
  → Content management (article, page, blog)
  → User activity logs
  → Real-time analytics
```

---

## Cassandra

```
// Bu kod Cassandra-nın partition key əsaslı wide-column schema dizaynını göstərir
Wide-column store, distributed, AP:

Partition Key → Row → Columns

CREATE TABLE events (
    user_id   UUID,
    event_time TIMESTAMP,
    event_type TEXT,
    data       MAP<TEXT, TEXT>,
    PRIMARY KEY (user_id, event_time)
) WITH CLUSTERING ORDER BY (event_time DESC);

-- Primary Key = (Partition Key, Clustering Key)
-- user_id: partition key → hansı node-da saxlanılır
-- event_time: clustering key → partition daxilindəki sıra

Oxuma: "user_id = X olan son 100 event" → 1 partition, sürətli!
Yazma: append-only → çox sürətli

✅ Write-heavy workload
✅ Linear horizontal scale
✅ No single point of failure
✅ Multi-datacenter replication
❌ JOIN yoxdur
❌ Aggregate queries zəifdir
❌ Schema query pattern-ə görə dizayn edilməlidir

Nə zaman Cassandra:
  → IoT sensor data (milyardlarla yazma)
  → Time-series metrics
  → Audit/activity log
  → Messaging (inbox/outbox at scale)
  → Netflix (viewing history), Instagram (DMs)
```

---

## Nə Zaman Hansını Seçmək?

```
// Bu kod müxtəlif iş yükləri üçün hansı verilənlər bazasının seçilməsini tövsiyə edir
SQL (PostgreSQL, MySQL):
  ✅ ACID transactions lazımdır (payment, banking)
  ✅ Complex queries, JOINs
  ✅ Well-defined, stable schema
  ✅ Reporting, analytics
  ✅ Referential integrity vacibdir
  Nümunə: ERP, CRM, e-commerce core

MongoDB:
  ✅ Flexible, hierarchical data
  ✅ Schema tez-tez dəyişir
  ✅ JSON API çoxdur
  ✅ Product catalog, CMS
  Nümunə: Product catalog, blog, event store

Redis:
  ✅ Cache (sub-millisecond latency)
  ✅ Session storage
  ✅ Real-time leaderboard
  ✅ Rate limiting
  Nümunə: Cache layer, distributed lock

Cassandra:
  ✅ Write-heavy, high throughput
  ✅ Time-series data
  ✅ Multi-datacenter
  Nümunə: Metrics, logs, activity feed at scale

Polyglot Persistence:
  → Bir aplikasiyada bir neçə DB
  → Orders: PostgreSQL (ACID)
  → Product catalog: MongoDB (flexible)
  → Sessions: Redis (fast)
  → Analytics: ClickHouse (columnar)
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod PHP-də MongoDB CRUD, aggregation pipeline və Laravel Eloquent inteqrasiyasını göstərir
// MongoDB — mongodb/mongodb package

$client = new MongoDB\Client(
    "mongodb://localhost:27017",
    ['username' => 'user', 'password' => 'pass']
);

$collection = $client->myapp->products;

// Insert
$collection->insertOne([
    'name'       => 'Laptop',
    'category'   => 'electronics',
    'attributes' => ['ram' => '16GB', 'storage' => '512GB'],
    'price'      => 1200,
    'variants'   => [
        ['color' => 'black', 'stock' => 10],
        ['color' => 'silver', 'stock' => 5],
    ],
]);

// Find with filter
$products = $collection->find(
    ['category' => 'electronics', 'price' => ['$lte' => 1500]],
    ['sort' => ['price' => 1], 'limit' => 10]
);

// Aggregation pipeline
$result = $collection->aggregate([
    ['$match'  => ['category' => 'electronics']],
    ['$group'  => ['_id' => '$category', 'avgPrice' => ['$avg' => '$price']]],
    ['$sort'   => ['avgPrice' => -1]],
]);

// Update
$collection->updateOne(
    ['_id' => $id],
    ['$set' => ['price' => 999], '$inc' => ['views' => 1]]
);

// Laravel-də MongoDB (jenssegers/mongodb)
use Jenssegers\Mongodb\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'products';
}

$products = Product::where('category', 'electronics')
    ->where('price', '<=', 1500)
    ->orderBy('price')
    ->get();
```

---

## İntervyu Sualları

**1. CAP theorem nədir?**
Distributed sistemdə Consistency, Availability, Partition Tolerance-dən yalnız ikisini eyni anda tam təmin etmək olar. Network partition qaçılmaz olduğu üçün CP (consistency > availability) ya AP (availability > consistency) seçilir. SQL: CP, Cassandra: AP, MongoDB: konfiqurasiyadan asılı.

**2. SQL nə zaman, NoSQL nə zaman seçilir?**
SQL: ACID transactions (payment), complex JOINs, stable schema, referential integrity. NoSQL: flexible schema (product catalog), high write throughput (Cassandra), key-value cache (Redis), hierarchical data (MongoDB). Polyglot persistence — hər data tipi üçün uyğun DB.

**3. MongoDB-nin document model üstünlüyü nədir?**
Related data embedded document-də saxlanılır. Order + customer + items = 1 document. JOIN lazım deyil → read sürətli. Flexible schema — hər product fərqli attributes ola bilər. Horizontal sharding asandır.

**4. Cassandra niyə write-heavy workload üçün uygundur?**
Append-only storage (SSTable). Write: WAL + MemTable → sürətli. Distributed, no single point of failure. Linear scale. Amma: JOIN yoxdur, aggregate queries zəifdir, schema query pattern-ə görə dizayn edilməlidir.

**5. Eventual consistency nə deməkdir?**
Write bir node-a gedir, digər node-lara asynchronous yayılır. Qısa müddətdə node-lar fərqli data görə bilər, amma sonunda hamı eyni olur. Nümunə: Social media like count, DNS propagation. Bank transaction üçün uyğun deyil (strong consistency lazımdır).

**6. PACELC theorem CAP-ı necə genişləndirir?**
CAP yalnız partition zamanı seçimi izah edir. PACELC: Partition varsa E (Else): Latency vs Consistency tradeoff. Normal işləyəndə də distributed DB-lər Latency (sürət) vs Consistency (düzgünlük) arasında seçim edir. Cassandra: PA/EL — həm partition-da availability, həm normal halda aşağı latency seçir.

**7. MongoDB-də transactions nə vaxtdan var, necə işləyir?**
4.0-dan multi-document transactions dəstəklənir. WiredTiger engine, snapshot isolation. Lakin JOIN-lər kimi ACID əməliyyatlar performance overhead yaradır — bütün document-lər eyni shard-da olmalıdır (4.2-dən cross-shard transactions da var). Çox istifadə edilərsə MongoDB seçimini sorğulamalısınız.

**8. Cassandra-da replication factor və consistency level necə işləyir?**
`REPLICATION_FACTOR=3`: hər data 3 node-a yazılır. `CONSISTENCY_LEVEL=QUORUM`: write/read üçün N/2+1 node cavab verməlidir. `RF=3, CL=QUORUM` → 2 node razılaşmalıdır — 1 node down olsa sistem çalışır. Strong consistency üçün `CL=ALL` (RF sayı qədər node cavab vermeli).

---

## Anti-patternlər

**1. NoSQL-i hər şeyə "modern" olduğu üçün seçmək**
SQL-dən NoSQL-ə keçməyin səbəbi "daha müasirdir" düşüncəsi — relational constraint-lər, JOIN-lər, ACID transaction-lar lazım olan yerlərdə NoSQL data bütünlüyünü təmin etmir. DB seçimini data modeli, sorğu pattern-ləri və consistency tələblərinə görə edin; texnologiya deyil, tələb müəyyən etsin.

**2. MongoDB-də hər şeyi embedded document kimi saxlamaq**
Böyük array-lər (sonsuz böyüyən comments) document-ə embed etmək — 16MB document limit-ə çatılır, tək comment üçün bütün document yüklənir, update-lər ağırlaşır. Böyüyə bilən kolleksiyaları ayrı collection-da saxlayın; yalnız həmişə birlikdə oxunan, sabit ölçülü datanı embed edin.

**3. Cassandra-da query pattern-ə görə deyil, entity-yə görə schema dizayn etmək**
SQL düşüncəsi ilə `users`, `orders` cədvəlləri yaratmaq, sonra JOIN-ə ehtiyac duymaq — Cassandra JOIN dəstəkləmir, sonradan schema yenidən dizayn edilməlidir. Cassandra-da əvvəlcə sorğuları müəyyən edin, hər sorğu üçün ayrı cədvəl yaradın; denormalizasiya qəsdən edilir.

**4. ACID tələb edən əməliyyatlar üçün AP NoSQL seçmək**
Ödəniş əməliyyatları üçün eventual consistency verən Cassandra istifadə etmək — qısa müddətdə iki node fərqli balans görür, double spend mümkün olur. Financial transaction-lar, inventory deduction kimi kritik əməliyyatlar üçün ACID zəmanəti verən SQL ya da CP NoSQL (MongoDB ilə transactions) seçin.

**5. Polyglot persistence-i lazımsız yerə tətbiq etmək**
Sadə CRUD tətbiqi üçün MySQL + MongoDB + Redis + Elasticsearch qurmaq — operasional yük artır, hər DB-nin backup, monitoring, migration proseduru lazımdır, komanda hər birini bilməlidir. Sadəlikdən başlayın: bir DB kifayət edərsə bir DB işlədin; yeni DB yalnız real tələb yarandıqda əlavə edilsin.

**6. Schema migration-ı NoSQL-də planlaşdırmamaq**
"NoSQL schemaless-dir, migration lazım deyil" düşüncəsi — data zamanla dəyişir, köhnə strukturda yazılmış milyonlarla sənəd yeni kod ilə uyuşmur. NoSQL-də schema migration planı hazırlayın: versioning sahəsi əlavə edin, migration skriptlərini tədricən işlədin, kod hər iki versiyanı dəstəkləsin.
