# NoSQL Databases (Senior)

## NoSQL Nedir?

"Not Only SQL" - relational model-den ferqli olaraq, flexible schema, horizontal scaling ve yuksek performance ucun dizayn olunmus database-lerdir.

**Ne vaxt lazimdir?**
- Schema tez-tez deyisir
- Boyuk data volume (TB/PB)
- Yuksek write throughput
- Distributed system lazimdir
- Data relational deyil (document, graph, key-value)

## NoSQL Kateqoriyalari

| Kateqoriya | Misallar | Data Modeli | Use Case |
|------------|---------|-------------|----------|
| **Document** | MongoDB, CouchDB | JSON/BSON documents | CMS, user profiles, catalog |
| **Key-Value** | Redis, DynamoDB, Memcached | Key → Value | Cache, session, config |
| **Wide-Column** | Cassandra, HBase, ScyllaDB | Row key → column families | IoT, time-series, logs |
| **Graph** | Neo4j, ArangoDB | Nodes + Edges | Social networks, recommendations |
| **Search Engine** | Elasticsearch, Meilisearch | Inverted index documents | Full-text search, logs |

---

## MongoDB

En populyar document database. JSON-a oxsar BSON formatinda data saxlayir.

### Esas Konseptler

| SQL Termin | MongoDB Termin |
|-----------|---------------|
| Database | Database |
| Table | Collection |
| Row | Document |
| Column | Field |
| JOIN | $lookup (embed etmek daha yaxsidir) |
| INDEX | Index (B-Tree) |
| PRIMARY KEY | _id (avtomatik) |

### CRUD Emeliyyatlari

```javascript
// Database ve collection yarat (avtomatik yaranir)
use ecommerce

// INSERT
db.products.insertOne({
    name: "iPhone 15",
    price: 999.99,
    category: "electronics",
    specs: {
        color: "black",
        storage: "256GB",
        ram: "8GB"
    },
    tags: ["smartphone", "apple", "5g"],
    stock: 150,
    createdAt: new Date()
});

// Bulk insert
db.products.insertMany([
    { name: "Samsung S24", price: 899, category: "electronics" },
    { name: "MacBook Pro", price: 2499, category: "electronics" },
    { name: "AirPods Pro", price: 249, category: "accessories" }
]);

// FIND (SELECT)
db.products.find({ category: "electronics" });                    // WHERE
db.products.find({ price: { $gt: 500 } });                       // WHERE price > 500
db.products.find({ price: { $gte: 100, $lte: 1000 } });          // BETWEEN
db.products.find({ tags: "smartphone" });                         // Array contains
db.products.find({ "specs.color": "black" });                     // Nested field
db.products.find({ name: /iphone/i });                            // LIKE (regex)
db.products.find({}, { name: 1, price: 1, _id: 0 });             // SELECT name, price
db.products.find({ category: "electronics" }).sort({ price: -1 }).limit(10);  // ORDER BY + LIMIT

// $or, $and, $in
db.products.find({
    $or: [
        { category: "electronics" },
        { price: { $lt: 100 } }
    ]
});
db.products.find({ category: { $in: ["electronics", "accessories"] } });

// UPDATE
db.products.updateOne(
    { name: "iPhone 15" },
    { $set: { price: 949.99 }, $inc: { stock: -1 } }
);

// Nested field update
db.products.updateOne(
    { name: "iPhone 15" },
    { $set: { "specs.color": "blue" } }
);

// Array-a element elave et
db.products.updateOne(
    { name: "iPhone 15" },
    { $push: { tags: "bestseller" } }
);

// Bulk update
db.products.updateMany(
    { category: "electronics" },
    { $set: { onSale: true } }
);

// DELETE
db.products.deleteOne({ name: "iPhone 15" });
db.products.deleteMany({ stock: 0 });
```

### Aggregation Pipeline (SQL GROUP BY, HAVING, JOIN)

```javascript
// Kateqoriyaya gore ortalama qiymet (GROUP BY + AVG)
db.products.aggregate([
    { $match: { price: { $gt: 0 } } },                    // WHERE
    { $group: {
        _id: "$category",                                   // GROUP BY
        avgPrice: { $avg: "$price" },                       // AVG()
        totalStock: { $sum: "$stock" },                     // SUM()
        count: { $sum: 1 }                                  // COUNT()
    }},
    { $having: { count: { $gt: 5 } } },                    // HAVING (in $match)
    { $sort: { avgPrice: -1 } },                            // ORDER BY
    { $limit: 10 }                                          // LIMIT
]);

// $lookup (LEFT JOIN)
db.orders.aggregate([
    { $lookup: {
        from: "users",             // JOIN edilecek collection
        localField: "userId",       // orders.userId
        foreignField: "_id",        // users._id
        as: "user"                  // Alias
    }},
    { $unwind: "$user" },           // Array-dan object-e cevir
    { $project: {
        orderNumber: 1,
        total: 1,
        "user.name": 1,
        "user.email": 1
    }}
]);
```

### MongoDB Indexing

```javascript
// Tek field index
db.products.createIndex({ category: 1 });          // Ascending
db.products.createIndex({ price: -1 });             // Descending

// Compound index
db.products.createIndex({ category: 1, price: -1 });

// Unique index
db.users.createIndex({ email: 1 }, { unique: true });

// Text index (full-text search)
db.products.createIndex({ name: "text", description: "text" });
db.products.find({ $text: { $search: "iphone pro" } });

// TTL index (avtomatik silme)
db.sessions.createIndex({ createdAt: 1 }, { expireAfterSeconds: 3600 });

// Partial index
db.products.createIndex(
    { price: 1 },
    { partialFilterExpression: { stock: { $gt: 0 } } }
);

// EXPLAIN
db.products.find({ category: "electronics" }).explain("executionStats");
```

### Embedding vs Referencing

```javascript
// EMBEDDING (denormalization) - Data bir yerde
// Ustunluk: Tek query, suretli read
// Dezavantaj: Duplicate data, boyuk document
{
    _id: ObjectId("..."),
    name: "Orkhan",
    orders: [
        { product: "iPhone", total: 999, date: "2024-01-15" },
        { product: "AirPods", total: 249, date: "2024-02-20" }
    ]
}

// REFERENCING (normalization) - Data ayri collection-larda
// Ustunluk: Data tekrarlanmir, kicik document
// Dezavantaj: $lookup lazimdir, yavas
{
    _id: ObjectId("..."),
    name: "Orkhan",
    orderIds: [ObjectId("order1"), ObjectId("order2")]
}
```

**Ne vaxt Embed, ne vaxt Reference?**

| Embed | Reference |
|-------|-----------|
| 1:1 ve 1:Few relation | 1:Many (yuzlerle) |
| Birlikde oxunan data | Ayri-ayri oxunan data |
| Data nadir deyisir | Data tez-tez deyisir |
| Document 16MB-den kicik | Boyuk/boyuyen data |

### Laravel ile MongoDB

```php
// composer require mongodb/laravel-mongodb

// config/database.php
'mongodb' => [
    'driver' => 'mongodb',
    'host' => env('MONGO_HOST', '127.0.0.1'),
    'port' => env('MONGO_PORT', 27017),
    'database' => env('MONGO_DATABASE', 'ecommerce'),
    'username' => env('MONGO_USERNAME'),
    'password' => env('MONGO_PASSWORD'),
],

// Model
use MongoDB\Laravel\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'products';  // Table yerine collection

    protected $fillable = ['name', 'price', 'category', 'specs', 'tags'];

    // Embedded relation
    public function reviews(): EmbedsMany
    {
        return $this->embedsMany(Review::class);
    }
}

// Istifade - Eloquent kimi!
$products = Product::where('category', 'electronics')
    ->where('price', '>', 500)
    ->orderBy('price', 'desc')
    ->get();

// Nested field query
$products = Product::where('specs.color', 'black')->get();

// Array query
$products = Product::where('tags', 'smartphone')->get();

// Aggregation
$stats = Product::raw(function ($collection) {
    return $collection->aggregate([
        ['$group' => [
            '_id' => '$category',
            'avgPrice' => ['$avg' => '$price'],
            'count' => ['$sum' => 1],
        ]],
    ]);
});
```

### MongoDB Transactions (4.0+)

```php
// MongoDB 4.0+ transaction destekleyir (replica set lazimdir)
$session = DB::connection('mongodb')->getMongoClient()->startSession();
$session->startTransaction();

try {
    $order = Order::create([...], ['session' => $session]);

    Product::where('_id', $productId)
        ->decrement('stock', 1, [], ['session' => $session]);

    $session->commitTransaction();
} catch (\Exception $e) {
    $session->abortTransaction();
    throw $e;
}
```

---

## Cassandra

Distributed wide-column database. **Write-heavy** workload-lar ucun dizayn olunub. Facebook terefinden yaradilib.

### Esas Xususiyyetler

- **Masterless architecture** - her node beraber, single point of failure yoxdur
- **Linear scalability** - node elave et, performance artar
- **Tunable consistency** - query bazinda consistency level sec
- **Partition key** ile data node-lar arasi bolunur
- **Write-optimized** - write cox suretlidir (LSM Tree)

### Data Modeli

```
Cluster → Keyspace → Table → Row → Column
(SQL:     Server  → Database → Table → Row → Column)
```

### CQL (Cassandra Query Language)

```sql
-- Keyspace yarat (SQL-de database)
CREATE KEYSPACE ecommerce
WITH replication = {
    'class': 'NetworkTopologyStrategy',
    'datacenter1': 3    -- 3 replica
};

USE ecommerce;

-- Table yarat
-- DİQQET: Cassandra-da schema design query-ye gore edilir, data-ya gore deyil!
CREATE TABLE orders_by_user (
    user_id UUID,
    order_date TIMESTAMP,
    order_id UUID,
    total DECIMAL,
    status TEXT,
    PRIMARY KEY ((user_id), order_date, order_id)
    -- (user_id) = partition key (data harada saxlanilir)
    -- order_date, order_id = clustering columns (siralama)
) WITH CLUSTERING ORDER BY (order_date DESC, order_id ASC);

-- INSERT
INSERT INTO orders_by_user (user_id, order_date, order_id, total, status)
VALUES (uuid(), toTimestamp(now()), uuid(), 150.00, 'pending');

-- SELECT (partition key MUTLEQ lazimdir!)
SELECT * FROM orders_by_user WHERE user_id = ?;                     -- OK
SELECT * FROM orders_by_user WHERE user_id = ? AND order_date > ?;  -- OK
-- SELECT * FROM orders_by_user WHERE status = 'pending';           -- XETA! partition key yoxdur

-- TTL ile insert (avtomatik silinir)
INSERT INTO sessions (session_id, user_id, data)
VALUES (uuid(), uuid(), 'session_data')
USING TTL 3600;  -- 1 saat sonra silinir

-- Counter table
CREATE TABLE page_views (
    page_url TEXT PRIMARY KEY,
    view_count COUNTER
);
UPDATE page_views SET view_count = view_count + 1 WHERE page_url = '/products/1';
```

### Cassandra Query-Driven Modeling

```
SQL:       Data var → Schema yarat → Query yaz
Cassandra: Query var → Schema yarat → Data yaz

-- Sual: "User-in sifarislerini tarixe gore goster"
-- Cavab: orders_by_user table (partition: user_id, clustering: order_date DESC)

-- Sual: "Son 24 saatin sifarislerini goster"
-- Cavab: AYRI table lazimdir!
CREATE TABLE recent_orders (
    order_date DATE,
    order_time TIMESTAMP,
    order_id UUID,
    user_id UUID,
    total DECIMAL,
    PRIMARY KEY ((order_date), order_time, order_id)
) WITH CLUSTERING ORDER BY (order_time DESC);
```

> **Vacib:** Cassandra-da eyni data bir nece table-da saxlanilir (denormalization). Disk ucuzdur, query performance vacibdir.

### Consistency Levels

```sql
-- Write consistency
INSERT INTO orders (...) VALUES (...)
USING CONSISTENCY QUORUM;   -- Replica-larin yarisi+1 tesdiq etmelidir

-- Read consistency
SELECT * FROM orders WHERE ...
CONSISTENCY ONE;             -- 1 replica kifayetdir (suretli, eventual consistency)
CONSISTENCY QUORUM;          -- Yarisindan coyu (strong consistency)
CONSISTENCY ALL;             -- Hamisi (en yavas, en tutarli)
```

| Level | Neticesi | Ne vaxt |
|-------|---------|---------|
| `ONE` | 1 replica | Max speed, eventual consistency OK |
| `QUORUM` | N/2 + 1 | Strong consistency lazim olanda |
| `ALL` | Butun replica | Nadir, availability asagi dusur |
| `LOCAL_QUORUM` | Local DC-de quorum | Multi-datacenter setup |

### Laravel ile Cassandra

```php
// composer require shivas/laravel-cassandra (community package)

// config/database.php
'cassandra' => [
    'driver' => 'cassandra',
    'host' => env('CASSANDRA_HOST', '127.0.0.1'),
    'port' => env('CASSANDRA_PORT', 9042),
    'keyspace' => env('CASSANDRA_KEYSPACE', 'ecommerce'),
    'consistency' => 'local_quorum',
],

// Birbasza DataStax driver ile
use Cassandra;

$cluster = Cassandra::cluster()
    ->withContactPoints('node1', 'node2', 'node3')
    ->withDefaultConsistency(Cassandra::CONSISTENCY_LOCAL_QUORUM)
    ->build();

$session = $cluster->connect('ecommerce');

// Prepared statement (SQL injection prevention + performance)
$statement = $session->prepare(
    'SELECT * FROM orders_by_user WHERE user_id = ? AND order_date > ?'
);

$result = $session->execute($statement, [
    'arguments' => [
        new Cassandra\Uuid($userId),
        new Cassandra\Timestamp(strtotime('-30 days'))
    ]
]);

foreach ($result as $row) {
    echo $row['order_id'] . ': ' . $row['total'] . "\n";
}
```

---

## DynamoDB

Amazon-un fully managed NoSQL database-i. Serverless, auto-scaling, millisecond latency.

### Esas Konseptler

```
Table → Item → Attribute
(SQL:   Table → Row → Column)

Primary Key:
- Partition Key (hash key) - data paylanmasi
- Sort Key (range key) - optional, siralama

Secondary Indexes:
- GSI (Global Secondary Index) - ferqli partition key ile query
- LSI (Local Secondary Index) - eyni partition key, ferqli sort key
```

### DynamoDB Operations

```php
// AWS SDK for PHP
use Aws\DynamoDb\DynamoDbClient;

$client = new DynamoDbClient([
    'region' => 'eu-west-1',
    'version' => 'latest',
]);

// Table yarat
$client->createTable([
    'TableName' => 'Orders',
    'KeySchema' => [
        ['AttributeName' => 'userId', 'KeyType' => 'HASH'],     // Partition key
        ['AttributeName' => 'orderDate', 'KeyType' => 'RANGE'],  // Sort key
    ],
    'AttributeDefinitions' => [
        ['AttributeName' => 'userId', 'AttributeType' => 'S'],
        ['AttributeName' => 'orderDate', 'AttributeType' => 'S'],
    ],
    'BillingMode' => 'PAY_PER_REQUEST',  // On-demand pricing
]);

// PUT Item (INSERT)
$client->putItem([
    'TableName' => 'Orders',
    'Item' => [
        'userId' => ['S' => 'user-123'],
        'orderDate' => ['S' => '2024-01-15T10:30:00Z'],
        'orderId' => ['S' => 'order-456'],
        'total' => ['N' => '150.00'],
        'status' => ['S' => 'pending'],
        'items' => ['L' => [  // List
            ['M' => [          // Map (nested object)
                'productId' => ['S' => 'prod-1'],
                'quantity' => ['N' => '2'],
            ]],
        ]],
    ],
]);

// GET Item (Primary key ile - en suretli)
$result = $client->getItem([
    'TableName' => 'Orders',
    'Key' => [
        'userId' => ['S' => 'user-123'],
        'orderDate' => ['S' => '2024-01-15T10:30:00Z'],
    ],
]);

// Query (partition key + sort key condition)
$result = $client->query([
    'TableName' => 'Orders',
    'KeyConditionExpression' => 'userId = :uid AND orderDate > :date',
    'ExpressionAttributeValues' => [
        ':uid' => ['S' => 'user-123'],
        ':date' => ['S' => '2024-01-01'],
    ],
    'ScanIndexForward' => false,  // DESC
    'Limit' => 20,
]);

// Update
$client->updateItem([
    'TableName' => 'Orders',
    'Key' => [
        'userId' => ['S' => 'user-123'],
        'orderDate' => ['S' => '2024-01-15T10:30:00Z'],
    ],
    'UpdateExpression' => 'SET #status = :status, updatedAt = :now',
    'ExpressionAttributeNames' => ['#status' => 'status'],
    'ExpressionAttributeValues' => [
        ':status' => ['S' => 'shipped'],
        ':now' => ['S' => date('c')],
    ],
]);
```

### DynamoDB Single Table Design

```
DynamoDB-de JOIN yoxdur. Butun related data bir table-da saxlanilir.

PK              SK                  Data
USER#123        PROFILE             {name, email, ...}
USER#123        ORDER#2024-01-15    {total, status, ...}
USER#123        ORDER#2024-02-20    {total, status, ...}
USER#123        ADDRESS#home        {city, street, ...}
PRODUCT#456     INFO                {name, price, ...}
PRODUCT#456     REVIEW#user-123     {rating, comment, ...}
```

---

## Supabase

PostgreSQL uzerinde qurulmus **open-source Firebase alternativi**. Real-time subscriptions, auth, storage, edge functions verir.

### Nedir ve Ne Deyil?

- Supabase **NoSQL deyil** - altinda PostgreSQL var (relational)
- Firebase-in aciq-menbe alternatividir
- Backend-as-a-Service (BaaS) - backend yazmadan API verir
- Real-time, Auth, Storage, Edge Functions daxildir

### Supabase Xususiyyetleri

```
PostgreSQL Database    → Full SQL, RLS (Row Level Security)
PostgREST              → Avtomatik REST API
Realtime               → WebSocket ile live updates
GoTrue                 → Auth (email, OAuth, magic link)
Storage                → S3-compatible file storage
Edge Functions         → Serverless (Deno)
```

### Laravel ile Supabase

```php
// Supabase = PostgreSQL, yeni normal Laravel connection ile islenir!

// .env
DB_CONNECTION=pgsql
DB_HOST=db.xxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-password

// Adi Laravel kimi istifade
$users = User::where('is_active', true)->get();

// Supabase REST API (client-side ucun)
// composer require supabase/supabase-php (community)
$supabase = new \Supabase\SupabaseClient($url, $key);

$result = $supabase
    ->from('products')
    ->select('id, name, price')
    ->eq('category', 'electronics')
    ->order('price', ['ascending' => false])
    ->limit(10)
    ->execute();

// Real-time subscription (JavaScript client ile)
// const channel = supabase.channel('orders')
//   .on('postgres_changes', { event: 'INSERT', schema: 'public', table: 'orders' },
//     (payload) => console.log('New order:', payload.new)
//   )
//   .subscribe()
```

### Row Level Security (RLS)

```sql
-- Supabase-in en guclu xususiyyeti - database seviyyesinde auth

ALTER TABLE orders ENABLE ROW LEVEL SECURITY;

-- User yalniz oz sifarislerini gore biler
CREATE POLICY "Users can view own orders"
ON orders FOR SELECT
USING (auth.uid() = user_id);

-- User yalniz oz sifarisini yarada biler
CREATE POLICY "Users can insert own orders"
ON orders FOR INSERT
WITH CHECK (auth.uid() = user_id);

-- Admin her seyi gore biler
CREATE POLICY "Admins can view all"
ON orders FOR ALL
USING (auth.jwt() ->> 'role' = 'admin');
```

---

## CouchDB

Document database. HTTP/REST API ile isleyir. Offline-first dizayn, mobile sync ucun ideal.

```bash
# REST API ile CRUD
# Create
curl -X PUT http://localhost:5984/mydb/doc1 \
  -H "Content-Type: application/json" \
  -d '{"name": "Orkhan", "type": "user"}'

# Read
curl http://localhost:5984/mydb/doc1

# Update (_rev lazimdir!)
curl -X PUT http://localhost:5984/mydb/doc1 \
  -d '{"_rev": "1-xxx", "name": "Orkhan", "type": "user", "age": 25}'

# Delete
curl -X DELETE http://localhost:5984/mydb/doc1?rev=2-xxx
```

---

## SQL vs NoSQL Muqayise

| Xususiyyet | SQL (MySQL, PostgreSQL) | NoSQL (MongoDB, Cassandra) |
|------------|------------------------|---------------------------|
| **Data Model** | Table, row, column | Document, key-value, graph |
| **Schema** | Fixed, ALTER TABLE lazim | Flexible, schema-less |
| **Scaling** | Vertical (daha guclu server) | Horizontal (daha cox server) |
| **Transactions** | ACID (guclu) | BASE (eventual consistency) |
| **JOINs** | Native, suretli | Yoxdur ve ya $lookup (yavas) |
| **Consistency** | Strong consistency | Eventual consistency (tunable) |
| **Query** | SQL (standart) | Her DB-nin oz query dili |
| **Normalization** | 3NF tovsiye olunur | Denormalization tovsiye olunur |
| **Best for** | Complex queries, transactions | High volume, flexible schema |

### Ne Vaxt SQL?

- **Complex relationships** - E-commerce (orders → items → products → categories)
- **ACID transactions** - Bank, odenis, maliyye
- **Ad-hoc queries** - Reporting, analytics
- **Data integrity** - FK, CHECK, UNIQUE constraint-ler vacibdirse
- **Small-medium scale** - Tek server kifayet edirse

### Ne Vaxt NoSQL?

- **Flexible schema** - IoT sensor data (her sensorun ferqli field-leri)
- **High write throughput** - Log, event, clickstream (Cassandra)
- **Document-based data** - CMS, user profile, product catalog (MongoDB)
- **Caching / session** - Key-value store lazimdir (Redis)
- **Real-time search** - Full-text search (Elasticsearch)
- **Massive scale** - Millions QPS (DynamoDB)
- **Geo-distributed** - Multi-region deployment (Cassandra, CockroachDB)

### Hybrid Yanasma (Real Production)

Cogu production sistem **her ikisini** istifade edir:

```
PostgreSQL (primary) → Sifarisler, users, payments (ACID lazim)
MongoDB              → Product catalog, CMS content (flexible schema)
Redis                → Cache, sessions, rate limiting (speed)
Elasticsearch        → Product search, log search (full-text)
Cassandra            → Event logs, analytics (high write volume)
```

## NoSQL Database Muqayise

| Xususiyyet | MongoDB | Cassandra | DynamoDB | Redis |
|------------|---------|-----------|----------|-------|
| **Tip** | Document | Wide-Column | Key-Value/Document | Key-Value |
| **Query** | Rich queries | Limited (partition key) | Limited (PK + sort) | Commands |
| **Scaling** | Sharding | Linear | Auto | Cluster |
| **Consistency** | Tunable | Tunable | Strong/Eventual | Strong |
| **Transactions** | Beli (4.0+) | Xeyr (LWT var) | Beli | MULTI/EXEC |
| **Hosted** | Atlas | Astra | AWS native | ElastiCache |
| **Best for** | General purpose | Write-heavy, IoT | Serverless, AWS | Cache, realtime |
| **Worst for** | Heavy aggregations | Ad-hoc queries | Complex queries | Large datasets |

## Interview Suallari

1. **SQL ve NoSQL arasinda nece secim edirsiniz?**
   - Data model-e bax: relational → SQL, document/flexible → NoSQL. Transaction lazimdir → SQL. Massive scale → NoSQL. Cogu halda hybrid istifade olunur.

2. **MongoDB-de embedding ne vaxt istifade olunur?**
   - 1:1 ve 1:Few relation, birlikde oxunan data, nadir deyisen data. 16MB document limit var.

3. **Cassandra-da niye query-driven modeling lazimdir?**
   - Cassandra partition key olmadan query ede bilmir. Table dizayni "hansi suallara cavab verecem?" prinsipine goredir.

4. **DynamoDB-de single table design nedir?**
   - JOIN yoxdur, butun related data bir table-da PK/SK pattern ile saxlanilir. Access pattern-e gore dizayn olunur.

5. **Supabase ile Firebase ferqi?**
   - Supabase: PostgreSQL (SQL, RLS, full relational), aciq-menbe. Firebase: Firestore (NoSQL), Google proprietary.

6. **BASE nedir?**
   - **B**asically **A**vailable, **S**oft state, **E**ventual consistency. ACID-in NoSQL alternatividir. Data mutleq consistent olmaya biler, amma sonunda consistent olacaq.

7. **CAP theorem-e gore bu database-ler harada yerlesir?**
   - MongoDB: CP (consistency + partition tolerance). Cassandra: AP (availability + partition tolerance, tunable). DynamoDB: Tunable (CP ve ya AP).
