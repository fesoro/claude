# MongoDB with PHP (Middle)

## Mündəricat
1. [MongoDB nədir?](#mongodb-nədir)
2. [MongoDB vs SQL](#mongodb-vs-sql)
3. [PHP driver setup](#php-driver-setup)
4. [CRUD əməliyyatları](#crud-əməliyyatları)
5. [Query operators](#query-operators)
6. [Aggregation pipeline](#aggregation-pipeline)
7. [Indexing](#indexing)
8. [Schema design patterns](#schema-design-patterns)
9. [Transactions](#transactions)
10. [Laravel inteqrasiya (jenssegers/mongodb)](#laravel-inteqrasiya-jenssegersmongodb)
11. [İntervyu Sualları](#intervyu-sualları)

---

## MongoDB nədir?

```
MongoDB — document-oriented NoSQL database.
JSON-like document (BSON binary format) saxlayır.

Use case:
  - Schemaless data (varied structure)
  - Heavy reads/writes (sharding native)
  - Geospatial queries
  - Real-time analytics
  - Content management
  - IoT / sensor data
  - Catalog (e-commerce — products with variant attributes)

NƏ VAXT istifadə ETMƏ:
  - Strict ACID multi-document transactions (Postgres daha yaxşı)
  - Complex JOINs (relational)
  - Strong schema enforcement (regulated industries)

MongoDB Atlas — managed cloud service.
```

---

## MongoDB vs SQL

```
                  | MongoDB                  | SQL (Postgres)
─────────────────────────────────────────────────────────────
Storage          | BSON document (JSON-like) | Rows in tables
Schema           | Flexible (per document)   | Fixed (per table)
Joins            | Limited ($lookup)         | Native, optimized
Transactions     | Multi-doc ACID (4.0+)     | Native ACID
Scaling          | Sharding (auto)           | Read replicas, manual sharding
Index            | Single field, compound, geo | B-tree, GIN, GiST
Aggregation      | Pipeline (stages)         | SQL GROUP BY, window
Schema migration | Optional, lazy            | Required, upfront
Best for         | Flexible, distributed     | Relational, transactional
```

```js
// MongoDB document
{
  "_id": ObjectId("..."),
  "name": "Ali",
  "email": "ali@example.com",
  "addresses": [
    { "city": "Baku", "street": "..." },
    { "city": "Sumqayit", "street": "..." }
  ],
  "tags": ["vip", "early-adopter"],
  "metadata": {
    "signup_source": "mobile",
    "last_login": ISODate("2026-04-19T10:00:00Z")
  }
}

// Equivalent SQL: 4 tables (users, addresses, tags, user_tags, metadata)
```

---

## PHP driver setup

```bash
# PHP extension
sudo pecl install mongodb
echo "extension=mongodb" >> /etc/php/8.3/cli/conf.d/30-mongodb.ini

# Higher-level library
composer require mongodb/mongodb
```

```php
<?php
require 'vendor/autoload.php';

use MongoDB\Client;

$client = new Client('mongodb://user:pass@localhost:27017', [], [
    'serverSelectionTimeoutMS' => 5000,
]);

// Replica set
$client = new Client('mongodb://host1:27017,host2:27017,host3:27017/?replicaSet=rs0');

// Atlas (cloud)
$client = new Client('mongodb+srv://user:pass@cluster.mongodb.net/');

// Database & Collection
$db = $client->selectDatabase('myapp');
$users = $db->selectCollection('users');
// Ya da: $users = $client->myapp->users;
```

---

## CRUD əməliyyatları

```php
<?php
// INSERT
$result = $users->insertOne([
    'name'  => 'Ali',
    'email' => 'ali@example.com',
    'age'   => 30,
    'tags'  => ['vip'],
    'created_at' => new MongoDB\BSON\UTCDateTime(),
]);

echo $result->getInsertedId();   // ObjectId

// Insert many
$users->insertMany([
    ['name' => 'Bob', 'age' => 25],
    ['name' => 'Carol', 'age' => 28],
]);

// FIND
$user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
echo $user['name'];   // BSONDocument array access

// findOne by criteria
$user = $users->findOne(['email' => 'ali@example.com']);

// find (cursor)
$cursor = $users->find(
    ['age' => ['$gte' => 18]],
    [
        'projection' => ['name' => 1, 'email' => 1, '_id' => 0],   // SELECT
        'sort' => ['created_at' => -1],
        'limit' => 20,
        'skip'  => 0,
    ]
);

foreach ($cursor as $user) {
    echo $user['name'] . "\n";
}

// UPDATE
$users->updateOne(
    ['_id' => $id],
    ['$set' => ['age' => 31]]
);

$users->updateMany(
    ['active' => false],
    [
        '$set' => ['archived' => true],
        '$inc' => ['archive_count' => 1],
        '$push' => ['tags' => 'archived'],
    ]
);

// UPSERT
$users->updateOne(
    ['email' => 'new@example.com'],
    ['$set' => ['name' => 'New User']],
    ['upsert' => true]
);

// REPLACE
$users->replaceOne(
    ['_id' => $id],
    ['name' => 'Completely New', 'email' => 'new@x.com']
);

// DELETE
$users->deleteOne(['_id' => $id]);
$users->deleteMany(['active' => false]);

// COUNT
$count = $users->countDocuments(['age' => ['$gte' => 18]]);
$estimated = $users->estimatedDocumentCount();   // collection metadata
```

---

## Query operators

```php
<?php
// Comparison
['age' => ['$eq' => 30]]                    // age = 30
['age' => ['$ne' => 30]]                    // age != 30
['age' => ['$gt' => 18]]                    // age > 18
['age' => ['$gte' => 18, '$lt' => 65]]      // 18 <= age < 65
['age' => ['$in' => [25, 30, 35]]]          // age IN (...)
['age' => ['$nin' => [25, 30]]]             // age NOT IN

// Logical
['$and' => [['age' => ['$gte' => 18]], ['active' => true]]]
['$or'  => [['email' => 'a@b.com'], ['email' => 'c@d.com']]]
['$not' => ['age' => ['$gte' => 18]]]
['$nor' => [...]]   // none of

// Array
['tags' => 'vip']                            // array contains 'vip'
['tags' => ['$all' => ['vip', 'beta']]]      // all of
['tags' => ['$size' => 3]]                   // exactly 3 elements
['tags' => ['$elemMatch' => ['$gte' => 5]]]  // any element matches

// Existence / type
['phone' => ['$exists' => true]]
['age' => ['$type' => 'int']]

// Regex
['name' => new MongoDB\BSON\Regex('^Ali', 'i')]   // case-insensitive

// Geospatial
['location' => [
    '$near' => [
        '$geometry' => ['type' => 'Point', 'coordinates' => [49.8671, 40.4093]],
        '$maxDistance' => 10000,   // 10km
    ]
]]

// Text search
$users->createIndex(['name' => 'text']);
$users->find(['$text' => ['$search' => 'gaming laptop']]);
```

---

## Aggregation pipeline

```php
<?php
// Aggregation = SQL GROUP BY + JOIN + window function
// Stage-by-stage transformation

$result = $users->aggregate([
    // Stage 1: Filter
    ['$match' => ['age' => ['$gte' => 18]]],
    
    // Stage 2: Group by country, count
    ['$group' => [
        '_id'   => '$country',
        'count' => ['$sum' => 1],
        'avg_age' => ['$avg' => '$age'],
        'users' => ['$push' => '$name'],   // collect names
    ]],
    
    // Stage 3: Sort
    ['$sort' => ['count' => -1]],
    
    // Stage 4: Limit
    ['$limit' => 10],
    
    // Stage 5: Project (SELECT)
    ['$project' => [
        'country' => '$_id',
        'count'   => 1,
        'avg_age' => ['$round' => ['$avg_age', 1]],
        '_id' => 0,
    ]],
]);

foreach ($result as $doc) {
    echo "{$doc['country']}: {$doc['count']} users (avg age {$doc['avg_age']})\n";
}
```

```php
<?php
// $lookup — JOIN-like
$orders->aggregate([
    ['$lookup' => [
        'from'         => 'users',
        'localField'   => 'user_id',
        'foreignField' => '_id',
        'as'           => 'user',
    ]],
    ['$unwind' => '$user'],     // array → flat
    ['$project' => [
        'order_id' => '$_id',
        'amount'   => 1,
        'user.name' => 1,
    ]],
]);

// $facet — birdən çox aggregation paralel
$users->aggregate([
    ['$facet' => [
        'by_country' => [
            ['$group' => ['_id' => '$country', 'count' => ['$sum' => 1]]],
        ],
        'by_age_group' => [
            ['$bucket' => [
                'groupBy' => '$age',
                'boundaries' => [0, 18, 30, 50, 100],
                'default' => 'other',
                'output' => ['count' => ['$sum' => 1]],
            ]],
        ],
        'total' => [
            ['$count' => 'value'],
        ],
    ]],
]);
```

---

## Indexing

```php
<?php
// Single field
$users->createIndex(['email' => 1], ['unique' => true]);

// Compound (sıra vacibdir!)
$orders->createIndex(['user_id' => 1, 'created_at' => -1]);
// Query: db.orders.find({user_id: X}).sort({created_at: -1}) → index istifadə

// Text index
$products->createIndex(['name' => 'text', 'description' => 'text']);

// Geospatial 2dsphere
$places->createIndex(['location' => '2dsphere']);

// Partial index (filter qoyulmuş index)
$users->createIndex(
    ['email' => 1],
    ['partialFilterExpression' => ['active' => true]]
);

// TTL — auto-expire (sessions, logs)
$sessions->createIndex(
    ['expires_at' => 1],
    ['expireAfterSeconds' => 0]   // expires_at field-i dəyəri ilə
);

// View indexes
$users->listIndexes();

// Drop
$users->dropIndex('email_1');
```

```
Index strategiyaları (ESR rule):
  E — Equality (eq match) ilk
  S — Sort fields ortada
  R — Range (gt/lt/gte/lte) sonda
  
  Compound: { user_id: 1, created_at: -1, age: 1 }
  Query:    db.find({user_id: X, age: {$gte: 18}}).sort({created_at: -1})
                    └─ E ────────┘ └─ R ───────┘  └─ S ──────────────┘
```

---

## Schema design patterns

```js
// PATTERN 1: EMBEDDED (denormalized)
// User + addresses tək document
{
  _id: ...,
  name: "Ali",
  addresses: [
    { city: "Baku", street: "..." },
    { city: "Sumqayit", street: "..." }
  ]
}
// Pros: 1 read, no JOIN
// Cons: doc size limit (16MB), update overhead

// PATTERN 2: REFERENCED (normalized)
// users + addresses ayrı collection
{ _id: 1, name: "Ali" }
{ _id: 100, user_id: 1, city: "Baku" }
// Pros: small docs, shared addresses
// Cons: $lookup lazımdır (JOIN)

// PATTERN 3: BUCKET (time-series)
// Sensor readings — saatlıq bucket
{
  _id: ...,
  sensor_id: 42,
  hour: ISODate("2026-04-19T10:00:00Z"),
  count: 60,                   // 60 sample
  measurements: [
    { ts: 0,  value: 23.5 },   // 0s
    { ts: 1,  value: 23.6 },   // 1s
    ...
  ]
}

// PATTERN 4: COMPUTED
// Pre-calculate at write time (sum, count)
{
  _id: ...,
  user_id: 1,
  total_orders: 42,           // pre-computed (write zamanı $inc)
  total_spent: 12000.50
}

// PATTERN 5: SCHEMA VERSIONING
{
  _id: ...,
  schema_version: 2,          // her doc-da version
  ...
}
// Migration lazy — read zamanı upgrade et
```

---

## Transactions

```php
<?php
// MongoDB 4.0+ multi-document transactions (replica set/sharded cluster lazım)
$session = $client->startSession();

try {
    $session->startTransaction();
    
    $accounts->updateOne(
        ['_id' => $fromId],
        ['$inc' => ['balance' => -100]],
        ['session' => $session]
    );
    
    $accounts->updateOne(
        ['_id' => $toId],
        ['$inc' => ['balance' => 100]],
        ['session' => $session]
    );
    
    $session->commitTransaction();
} catch (\Throwable $e) {
    $session->abortTransaction();
    throw $e;
} finally {
    $session->endSession();
}

// MongoDB-də transaction-lara ehtiyac AZALIR (embedded design ilə).
// Tək document operation atomic-dir (upsert, $inc, etc.).
```

---

## Laravel inteqrasiya (jenssegers/mongodb)

```bash
composer require mongodb/laravel-mongodb
```

```php
<?php
// config/database.php
'connections' => [
    'mongodb' => [
        'driver'   => 'mongodb',
        'dsn'      => env('MONGODB_URI', 'mongodb://localhost:27017'),
        'database' => env('MONGODB_DATABASE', 'myapp'),
    ],
],

// Model
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class User extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'users';
    protected $fillable = ['name', 'email', 'age', 'tags'];
}

// İstifadə (Eloquent kimi!)
$user = User::create(['name' => 'Ali', 'email' => 'a@b.com']);
$users = User::where('age', '>', 18)->get();

// Embedded relationship
class User extends Model
{
    public function addresses()
    {
        return $this->embedsMany(Address::class);
    }
}

$user->addresses()->save(new Address(['city' => 'Baku']));

// Query Builder
DB::connection('mongodb')->collection('users')
    ->where('age', '>', 18)
    ->get();
```

---

## İntervyu Sualları

- MongoDB-i nə vaxt SQL-dən üstün seçərdiniz?
- BSON nədir, JSON-dan necə fərqlənir?
- Embedded vs Referenced design — hansı nə vaxt?
- ESR index qaydası nədir?
- `$lookup` SQL JOIN-dən nə ilə fərqlənir (performance)?
- Multi-document transaction MongoDB-də nə vaxt lazımdır?
- TTL index nəyə xidmət edir?
- Aggregation pipeline-da `$facet` nə üçündür?
- Sharding key seçimi niyə vacibdir?
- Document-də 16 MB limit-i necə aşmaq olar?
- Schema versioning pattern necə işləyir?
- Replica set və sharded cluster fərqi?
