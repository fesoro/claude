# SQL vs NoSQL Selection

## Nədir? (What is it?)

**SQL (Relational)** və **NoSQL (Non-Relational)** database-lər fərqli use case-lər üçün optimallaşdırılmış məlumat saxlama sistemləridir. Doğru seçim sistemin **performans**ına, **scalability**-sinə, **konsistensiyasına** və **geliştirmə sürətinə** birbaşa təsir edir.

- **SQL**: Structured data, ACID transactions, complex joins, schema-on-write.
- **NoSQL**: Flexible schema, horizontal scaling, eventual consistency, high throughput.

### Əsas fərq - bir cümlədə

- **SQL**: "Mənə konsistent, struktur, transaksiyalı sistem lazımdır" (bank, ERP).
- **NoSQL**: "Mənə böyük miqyaslı, elastik, sürətli oxuma/yazma lazımdır" (feed, analytics, cache).

## Əsas Konseptlər (Key Concepts)

### SQL xüsusiyyətləri

| Xüsusiyyət | İzahı |
|------------|-------|
| **ACID** | Atomicity, Consistency, Isolation, Durability |
| **Schema** | Strict (schema-on-write), migration-lar |
| **Joins** | Multi-table queries optimize edilmiş |
| **Scaling** | Vertical (güclü server) + read replicas |
| **Query Language** | SQL - standart, deklarativ |

### NoSQL Tipləri

#### 1. Document (MongoDB, CouchDB)

- JSON/BSON sənədlər.
- Flexible schema.
- Use case: CMS, e-commerce catalog, user profiles.

#### 2. Key-Value (Redis, DynamoDB, Memcached)

- Sadə açar-dəyər cütləri.
- Çox sürətli (O(1) access).
- Use case: Cache, session storage, leaderboard.

#### 3. Column-Family / Wide-Column (Cassandra, HBase, ScyllaDB)

- Sütunlar qruplarla saxlanır.
- Write-heavy workload üçün optimal.
- Use case: Time-series, IoT, messaging (Instagram, Netflix).

#### 4. Graph (Neo4j, Amazon Neptune, ArangoDB)

- Node-lər və edge-lər.
- Relationship traversal sürətli.
- Use case: Social network, recommendation, fraud detection.

### CAP Theorem

- **SQL (traditional)**: CP or CA (single node strong consistency).
- **MongoDB**: CP (default, replica set).
- **Cassandra**: AP (eventual consistency, tunable).
- **DynamoDB**: AP (configurable strong/eventual).

## Arxitektura (Architecture)

### Decision Tree

1. **Strong ACID + Transactions lazımdır?** → SQL (PostgreSQL, MySQL).
2. **Schema tez dəyişir, unstructured?** → Document (MongoDB).
3. **Relationships mürəkkəbdir (3+ hop)?** → Graph (Neo4j).
4. **Milyonlarla TPS write, time-series?** → Column-family (Cassandra).
5. **Sadə key-by-id lookup, cache?** → Key-Value (Redis, DynamoDB).
6. **Hybrid tələb?** → Polyglot persistence.

### Müqayisə Cədvəli

| Kriteriya | SQL | Document | Key-Value | Column | Graph |
|-----------|-----|----------|-----------|--------|-------|
| **ACID** | Full | Document-level | Limited | Row-level | Full |
| **Schema** | Strict | Flexible | None | Flexible | Flexible |
| **Joins** | Native | Manual/Aggregation | None | Limited | Native (traversal) |
| **Scaling** | Vertical | Horizontal | Horizontal | Horizontal | Cluster |
| **Consistency** | Strong | Configurable | Eventual | Eventual | Strong |
| **Write speed** | Orta | Yüksək | Ən yüksək | Ən yüksək | Orta |
| **Example** | MySQL, PostgreSQL | MongoDB | Redis, DynamoDB | Cassandra | Neo4j |

## PHP/Laravel ilə Tətbiq

### 1. SQL (Eloquent + MySQL) - Financial Transaction

```php
<?php
use Illuminate\Support\Facades\DB;

class TransferService {
    public function transfer(int $fromId, int $toId, float $amount): void {
        // ACID transaction - SQL-in güclü tərəfi
        DB::transaction(function () use ($fromId, $toId, $amount) {
            $from = Account::lockForUpdate()->findOrFail($fromId);
            $to = Account::lockForUpdate()->findOrFail($toId);
            if ($from->balance < $amount) throw new \RuntimeException("Insufficient");
            $from->decrement('balance', $amount);
            $to->increment('balance', $amount);
            Transaction::create(['from_id'=>$fromId,'to_id'=>$toId,'amount'=>$amount,'status'=>'completed']);
        }, attempts: 3);
    }
}
```

**Niyə SQL?** Atomicity kritikdir, hesab balansı heç vaxt yanlış ola bilməz.

### 2. Document DB (MongoDB) - Product Catalog

```php
<?php
use MongoDB\Client;

class ProductCatalog {
    private $collection;
    public function __construct() {
        $this->collection = (new Client(env('MONGO_URI')))->shop->products;
    }
    public function addProduct(array $data): void {
        // Flexible schema - hər məhsulun fərqli atributları
        $this->collection->insertOne([
            'sku' => $data['sku'], 'name' => $data['name'], 'price' => $data['price'],
            'attributes' => $data['attributes'], // {color, size, weight...}
            'images' => $data['images'], 'reviews' => [],
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
        ]);
    }
    public function searchByAttribute(string $attr, $value): array {
        return iterator_to_array($this->collection->find(["attributes.$attr" => $value]));
    }
}
```

**Niyə Document?** Hər məhsul kateqoriyasının fərqli atributları (kitab: ISBN; köynək: size).

### 3. Key-Value (Redis) - Leaderboard & Session

```php
<?php
use Illuminate\Support\Facades\Redis;

class GameLeaderboard {
    public function recordScore(int $userId, int $score): void {
        // Sorted set - O(log n) insert, O(log n) range query
        Redis::zadd('leaderboard:global', $score, $userId);
    }
    public function top10(): array { return Redis::zrevrange('leaderboard:global', 0, 9, 'WITHSCORES'); }
    public function userRank(int $userId): int { return Redis::zrevrank('leaderboard:global', $userId) + 1; }
}

class SessionStore {
    public function set(string $sid, array $data, int $ttl = 3600): void {
        Redis::setex("sess:$sid", $ttl, json_encode($data));
    }
}
```

**Niyə Key-Value?** Millisaniyə latency, milyonlarla QPS, complex query yox.

### 4. Column-Family (Cassandra) - Time-Series

```php
<?php
class SensorDataStore {
    private $session;
    public function __construct() {
        $cluster = \Cassandra::cluster()->withContactPoints('cassandra1','cassandra2')->build();
        $this->session = $cluster->connect('iot');
    }
    public function recordMetric(string $deviceId, float $value): void {
        // Schema: PRIMARY KEY ((device_id), timestamp) - write-optimized
        $stmt = $this->session->prepare('INSERT INTO sensor_data (device_id, timestamp, value) VALUES (?, ?, ?)');
        $this->session->execute($stmt, ['arguments'=>[$deviceId, new \Cassandra\Timestamp(), $value]]);
    }
    public function getLastHour(string $deviceId): array {
        $hourAgo = new \Cassandra\Timestamp(time() - 3600, 0);
        $stmt = $this->session->prepare('SELECT * FROM sensor_data WHERE device_id = ? AND timestamp >= ?');
        return $this->session->execute($stmt, ['arguments'=>[$deviceId, $hourAgo]])->rows();
    }
}
```

**Niyə Column-family?** Günə milyonlarla sensor data, linear write scalability.

### 5. Graph (Neo4j) - Recommendation

```php
<?php
use Laudis\Neo4j\ClientBuilder;

class FriendRecommender {
    private $client;
    public function __construct() {
        $this->client = ClientBuilder::create()->withDriver('bolt', env('NEO4J_URL'))->build();
    }
    public function friendsOfFriends(int $userId): array {
        // "Friend of friend" - SQL-də JOIN cəhənnəmi, graph-da natural
        $result = $this->client->run(
            'MATCH (u:User {id: $id})-[:FRIEND]->(f)-[:FRIEND]->(fof)
             WHERE fof.id <> $id AND NOT (u)-[:FRIEND]->(fof)
             RETURN fof.id, fof.name, count(*) as mutual
             ORDER BY mutual DESC LIMIT 10', ['id' => $userId]);
        return $result->toArray();
    }
}
```

**Niyə Graph?** Multi-hop traversal, SQL-də 3+ JOIN qarşısını alır.

### 6. Hybrid Approach (Polyglot Persistence)

```php
<?php
class EcommerceSystem {
    public function checkout(int $userId, array $cart): Order {
        $order = DB::transaction(fn() => Order::create([...]));           // SQL - ACID
        Redis::del("cart:$userId");                                        // Redis - cache
        app(ProductSearchService::class)->updateStock($cart);              // MongoDB - catalog
        app(OrderIndexer::class)->index($order);                           // Elasticsearch - search
        app(EventLog::class)->record('order.created', $order);             // Cassandra - events
        return $order;
    }
}
```

## Real-World Nümunələr

### Facebook: Why MySQL?

- **Milyardlarla user** - Lakin Facebook hələ də **MySQL** istifadə edir (sharded).
- **Səbəb**: Consistency critical (friend request state), mövcud ecosystem, MyRocks storage engine ilə optimize.
- **Technique**: **Application-level sharding** (TAO cache layer MySQL-in üstündə), `UserID % N` ilə shard-lara paylanır.

### Instagram: PostgreSQL + Cassandra

- **User data**: PostgreSQL (sharded) - profile, followers.
- **Feed/Messages**: Cassandra - write-heavy, eventual consistency OK.
- **Key lesson**: "ONE size fits none" - hər feature üçün fərqli DB.

### Uber: MySQL → Schemaless → NewSQL

- **2011**: MySQL ilə başladı.
- **2014**: **Schemaless** (custom, MySQL üzərində document layer).
- **2020**: **Docstore** (MySQL-based, proprietary), **CockroachDB** (NewSQL) bəzi servislər üçün.
- **Lesson**: Scale artdıqca SQL semantikasını qoruyub NoSQL scalability-sini almaq üçün **NewSQL** (Spanner, CockroachDB, TiDB) seçilir.

### Netflix: Cassandra at scale

- **2.5 trillion requests/day** Cassandra-ya.
- **Səbəb**: Multi-region replication, AP focus (1-2 saniyə gecikmə OK), linear write scalability.
- **Playback events**, **recommendation features** - hamısı Cassandra-da.

### Twitter: MySQL + Manhattan (custom)

- **Tweets**: Əvvəllər MySQL, sonra **Manhattan** (custom key-value).
- **Timeline**: **Redis** (sorted set)
- **Search**: **Elasticsearch**

### Airbnb: MySQL → Vitess

- Single MySQL instance-dan başladı.
- Scale-dən sonra **Vitess** (YouTube-da yaradılan MySQL sharding layer).
- Tam SQL saxladı, amma horizontal scaling əldə etdi.

### Discord: MongoDB → Cassandra → ScyllaDB

- Başlanğıc: **MongoDB** (messages).
- **Cassandra**-ya keçid (120M messages/day scale).
- **2022-2023**: **ScyllaDB** (Cassandra-compatible, C++-lə yazılıb, 10x throughput).

### LinkedIn: Espresso (distributed document DB)

- Özlərinin **Espresso** document store-nu yaratdılar (MySQL üzərində).
- Səbəb: Strong consistency + document semantics + horizontal scale.

## Interview Sualları (Q&A)

### 1. ACID və BASE arasındakı fərq nədir?

**Cavab:**
- **ACID** (SQL): **A**tomicity, **C**onsistency, **I**solation, **D**urability - transaction zəmanətləri.
- **BASE** (NoSQL): **B**asically **A**vailable, **S**oft state, **E**ventually consistent.
- SQL "doğru cavab verməkçün yavaş olar", NoSQL "cavab verməkçün bəzən köhnə data qaytarar".
- Trade-off: **Financial** sistemlər ACID tələb edir, **social feed** BASE ilə işləyə bilər.

### 2. Nə zaman MongoDB seçərsən, nə zaman PostgreSQL?

**Cavab:**
- **MongoDB**: Schema tez dəyişir (startup, CMS), nested document-lər (product attributes), horizontal scale vacibdir.
- **PostgreSQL**: Relational data (finance, inventory), complex JOIN-lar, ACID kritikdir, mature ecosystem.
- **Qeyd**: PostgreSQL artıq **JSONB** ilə NoSQL kimi işləyə bilər. Əgər şübhə edirsənsə, **PostgreSQL-lə başla** - scale problemi olarsa migrate edərsən.

### 3. Cassandra-nı Redis-dən nə ayırır, ikisi də key-value deyilmi?

**Cavab:**
- **Redis**: **In-memory** (RAM), kiçik dataset (<1TB), cache/session/pubsub.
- **Cassandra**: **Disk-based** (petabyte scale), column-family model, multi-region replication.
- Redis tək-node-luq daha yaxşıdır (cluster var, amma complex). Cassandra distributed-by-default.
- Redis-in **data structures** var (list, set, sorted set), Cassandra-nın yalnız table-ları.

### 4. Facebook niyə NoSQL-ə tam keçmir, hələ də MySQL istifadə edir?

**Cavab:**
- **Maturity**: MySQL 30+ il optimallaşdırılıb. Facebook MySQL-in eksperlərini işə götürür.
- **Tooling**: Replication, backup, monitoring üçün geniş alətlər.
- **Consistency**: Social graph operations (friend, like) strong consistency tələb edir.
- **MyRocks engine**: Facebook özü yaradıb, storage 50% azalıb.
- **TAO cache**: MySQL-i cache layer ilə effektiv istifadə edirlər - MySQL faktiki olaraq "slow path"dir.

### 5. Schema-on-write və schema-on-read nə deməkdir?

**Cavab:**
- **Schema-on-write** (SQL): Data yazılmazdan əvvəl schema müəyyən olunur. Upside: Validation, type safety. Downside: Migration ağrılı.
- **Schema-on-read** (NoSQL): Data saxlandığı formatda saxlanır, oxuyan tərəf interpret edir. Upside: Flexibility, schema evolution asan. Downside: Bad data yazıla bilər, read logic mürəkkəbləşir.
- **Hybrid**: PostgreSQL `JSONB` column - sütunlar strict, JSONB flexible.

### 6. Eventual consistency "qəbul edilə bilən" nə deməkdir?

**Cavab:**
- Data müəyyən müddətə (saniyələr, bəzən dəqiqələr) konsistent olmaya bilər. Nəticədə isə bütün node-lar eyni dəyəri görəcək.
- **Qəbul edilə bilər**: Sosial media post counter (10 ms fərq əhəmiyyətli deyil), product view counter.
- **Qəbul edilə bilməz**: Bank balansı, biletlərin satılması, inventory check.
- **Read-your-writes consistency** - user öz yazdığını dərhal oxuya bilər (session stickiness).
- **Monotonic reads** - geri getmə (new → old oxuma).

### 7. Uber niyə MySQL-dən NewSQL (CockroachDB, Schemaless)-ə keçdi?

**Cavab:**
- **Scale**: Şəhər başına milyonlarla ride, MySQL single-shard limitinə çatdı.
- **Multi-region**: Datacenter arası replication MySQL-də mürəkkəbdir, NewSQL native dəstəkləyir.
- **SQL saxlamaq**: Developer produktivliyi üçün SQL interface lazımdır (JOIN, transactions).
- **Schemaless**: MySQL üzərində document layer - schema evolution asan.
- **Lesson**: NoSQL-ə "qaç" əvəzinə SQL semantikasını saxlayaraq scale et.

### 8. Graph database nə vaxt SQL-dən daha yaxşıdır?

**Cavab:**
- **3+ hop relationship**: "Friends of friends of friends" - SQL-də 3 JOIN, graph-da tək traversal.
- **Recommendation**: "People who bought X also bought Y and are friends with Z".
- **Fraud detection**: Suspicious transaction chain-ləri (A → B → C → A dövrü).
- **Social network**: LinkedIn "2nd degree connection" sorğuları.
- **Knowledge graph**: Google search, Wikipedia article relationships.
- **SQL kifayətdir əgər**: Maksimum 2 JOIN və ya relationship az dəyişir.

### 9. Tək layihədə bir neçə database istifadə etmək tövsiyə olunurmu? (Polyglot persistence)

**Cavab:**
- **Bəli**, lakin ehtiyatla. Modern sistemlərdə adi haldır:
  - **PostgreSQL** - Transactional (users, orders).
  - **Redis** - Cache, session, rate limit.
  - **Elasticsearch** - Full-text search.
  - **S3** - Blob storage (images, videos).
  - **ClickHouse/BigQuery** - Analytics.
- **Risk**: Operational complexity, data synchronization, transaction boundary itirilir.
- **Başlanğıc**: Bir SQL database ilə başla, bottleneck yarandıqda ayrıca DB əlavə et (**YAGNI**).

### 10. SQL vs NoSQL - Instagram kimi feed sistemi üçün nə seçərsən?

**Cavab:**
- **User profile, follow relationships**: **PostgreSQL** (sharded) - consistency önəmli.
- **Posts**: **PostgreSQL** (sharded) or **Cassandra** (write-heavy).
- **Feed generation**: **Redis** (sorted set) - timeline cache.
- **Search** (hashtag, user): **Elasticsearch**.
- **Images/Videos**: **S3** + **CDN**.
- **Analytics** (likes, views): **Cassandra** or **Kafka → ClickHouse**.
- **Instagram real architecture**: PostgreSQL (sharded by user_id) + Cassandra (feed) + Redis (cache).

### 11. NewSQL nədir? Adi SQL-dən fərqi?

**Cavab:**
- **NewSQL**: SQL-in **ACID + SQL interface**-ini saxlayıb NoSQL-in **horizontal scalability**-sini verən database-lər.
- Nümunələr: **Google Spanner**, **CockroachDB**, **TiDB**, **YugabyteDB**.
- Necə işləyir: Distributed consensus (Raft/Paxos), multi-region replication, distributed transactions.
- Trade-off: Latency bir az artır (cross-region writes), amma consistency və scale birlikdə gəlir.
- **Kim istifadə edir**: Uber, Bytedance, Alibaba (mission-critical transactional systems).

## Best Practices

1. **Start with SQL** - PostgreSQL 99% layihə üçün kifayətdir. Bottleneck hiss etməyincə NoSQL-ə keçmə.
2. **YAGNI prinsipi** - "Biz bir gün milyon TPS istərik" - indi polyglot quruma.
3. **Right tool for the job** - Hər feature üçün ayrı-ayrılıqda qiymətləndir (polyglot persistence).
4. **Consistency vs Availability trade-off-unu təhlil et** - CAP theorem çərçivəsində.
5. **Schema migration planla** - NoSQL-də schema dəyişiklikləri runtime-da həll olunmalıdır.
6. **Index strategiyası** - SQL-də composite index, NoSQL-də access pattern-ə görə data modelling.
7. **Read replicas SQL-də** - Write/read split read-heavy workload üçün.
8. **Sharding key-ni diqqətlə seç** - Hot spot yaratmayacaq dağılım (user_id, hash).
9. **Backup strategy** - RTO/RPO tələblərinə görə; NoSQL-də cluster-wide backup mürəkkəbdir.
10. **Monitor everything** - Query latency, cache hit rate, replication lag.
11. **Prototype həm SQL, həm NoSQL ilə et** - Real data modelinə görə qərar ver.
12. **Vendor lock-in-dən çəkin** - DynamoDB, Firestore güclüdür, amma portability aşağıdır.
13. **Data locality** - User-ə yaxın region-da DB yerləşdir (multi-region replication).
14. **Cost modelini anla** - DynamoDB on-demand vs provisioned, MongoDB Atlas tier-ləri.
15. **Test with production-like load** - Benchmark kiçik dataset-də yanıldıcı ola bilər.
