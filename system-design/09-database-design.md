# Database Design (Junior)

## İcmal

Database design data-nın necə saxlanacağını, əlaqələndiriləcəyini və sorğulanacağını
planlaşdırmaqdır. Yaxşı database design performanslı, scalable və maintainable sistem
yaratmağın əsasıdır. Pis schema dizaynı sonradan düzəltmək çox çətindir.


## Niyə Vacibdir

Pis proyektləşdirilmiş DB şeması production-da düzəltmək üçün çox baha başa gəlir. İndeks seçimi sorğu performansını 100x artıra bilər; şema miqrasiyası downtime riski daşıyır. Real layihələrdə DB dizaynı sistemin uzunmüddətli sürətini müəyyən edir.

## Əsas Anlayışlar

### Normalization vs Denormalization

**Normalization** - Data redundancy-ni azaltmaq üçün table-ları bölmək.

```
1NF: Hər column atomic value saxlayır (list/array yox)
2NF: 1NF + partial dependency yoxdur
3NF: 2NF + transitive dependency yoxdur

Normalized (3NF):
users: id, name, email
orders: id, user_id, total
order_items: id, order_id, product_id, quantity, price
products: id, name, category_id
categories: id, name
```

**Denormalization** - Performance üçün qəsdən redundant data saxlamaq.

```
Denormalized:
orders: id, user_id, user_name, user_email, total, item_count
  (user_name, user_email JOIN əvəzinə birbaşa order-da)

order_items: id, order_id, product_id, product_name, price
  (product_name JOIN əvəzinə birbaşa item-da)
```

Normalization: Write-heavy, data integrity vacib
Denormalization: Read-heavy, performance vacib

### Indexing Strategies

**B-Tree Index (default)**
```sql
CREATE INDEX idx_users_email ON users(email);
-- Equality və range query-lər üçün yaxşıdır
-- WHERE email = 'test@mail.com'
-- WHERE created_at > '2025-01-01'
```

**Composite Index**
```sql
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
-- Soldan sağa istifadə olunur (leftmost prefix)
-- WHERE user_id = 1 AND status = 'active'  (hər iki column istifadə olunur)
-- WHERE user_id = 1                        (yalnız user_id istifadə olunur)
-- WHERE status = 'active'                  (index istifadə OLUNMUR!)
```

**Covering Index**
```sql
CREATE INDEX idx_cover ON orders(user_id, status, total);
-- SELECT status, total FROM orders WHERE user_id = 1
-- Table-a getmədən index-dən cavab verir (index-only scan)
```

**Full-Text Index**
```sql
CREATE FULLTEXT INDEX idx_ft ON products(name, description);
-- WHERE MATCH(name, description) AGAINST('laptop')
```

**Partial Index (PostgreSQL)**
```sql
CREATE INDEX idx_active_orders ON orders(user_id)
WHERE status = 'active';
-- Yalnız active order-lar index olunur, kiçik index
```

### Read Replicas

```
Application
  |
  ├── Write queries -> [Primary/Master]
  |                        |
  |                   Replication (async)
  |                    /        \
  └── Read queries -> [Replica 1] [Replica 2]

Replication lag: Primary-da yazılan data replica-da
bir müddət sonra görünür (milliseconds - seconds)
```

### Sharding (Horizontal Partitioning)

Data-nı bir neçə database-ə bölmək.

```
Shard Key: user_id

Shard 1: user_id 1-1000000
Shard 2: user_id 1000001-2000000
Shard 3: user_id 2000001-3000000

Hash-based: shard = hash(user_id) % num_shards
Range-based: shard = user_id range
```

### CAP Theorem

Distributed system-də eyni anda yalnız ikisini əldə edə bilərsən:

```
C - Consistency: Hər read ən son write-ı görür
A - Availability: Hər request cavab alır (error olmadan)
P - Partition Tolerance: Network bölünməsinə dözür

CP: MongoDB, Redis Cluster - consistency seçir, partition zamanı unavailable ola bilər
AP: Cassandra, DynamoDB - availability seçir, stale data qaytara bilər
CA: Praktikada mümkün deyil (network partition always possible)
```

### Database Tipləri

```
Relational (SQL):       PostgreSQL, MySQL - structured data, ACID, JOIN
Document:               MongoDB - flexible schema, nested data, JSON
Key-Value:              Redis, DynamoDB - simple lookup, cache
Wide-Column:            Cassandra, HBase - time series, large scale writes
Graph:                  Neo4j - relationships, social networks
Search:                 Elasticsearch - full-text search, analytics
Time-Series:            InfluxDB, TimescaleDB - metrics, IoT data
```

## Arxitektura

### Database Architecture Patterns

```
Pattern 1: Single Database
  [App] -> [MySQL]
  Sadə, kiçik app-lar üçün

Pattern 2: Read Replicas
  [App] -> Write -> [Primary MySQL]
        -> Read  -> [Replica 1] [Replica 2]
  Read-heavy workload

Pattern 3: CQRS
  [Command Service] -> [Write DB (PostgreSQL)]
  [Query Service]   -> [Read DB (Elasticsearch)]
  Event bus ilə sync

Pattern 4: Polyglot Persistence
  User data    -> PostgreSQL (relational)
  Session      -> Redis (key-value)
  Product search -> Elasticsearch
  Activity log -> Cassandra (write-heavy)
  Social graph -> Neo4j
```

## Nümunələr

### Migration Best Practices

```php
// database/migrations/2025_01_01_create_orders_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 20)->unique();
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])
                  ->default('pending');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');  // Reporting queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

### Eloquent Query Optimization

```php
// N+1 Problem (PIS)
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->user->name;  // Hər order üçün ayrı query!
}
// 1 + N queries (101 query for 100 orders)

// Eager Loading (YAXŞI)
$orders = Order::with('user')->get();
foreach ($orders as $order) {
    echo $order->user->name;  // Artıq yüklənib
}
// 2 queries total

// Lazy Eager Loading
$orders = Order::all();
$orders->load('user', 'items.product');

// Nested Eager Loading
$orders = Order::with([
    'user:id,name,email',  // select specific columns
    'items' => function ($query) {
        $query->select('id', 'order_id', 'product_id', 'quantity')
              ->with('product:id,name,price');
    },
])->get();

// Prevent N+1 (development-da)
// AppServiceProvider.php
Model::preventLazyLoading(!app()->isProduction());
```

### Query Builder Optimization

```php
// Chunk - böyük data set üçün memory-efficient
User::where('active', true)
    ->chunk(1000, function ($users) {
        foreach ($users as $user) {
            // Process
        }
    });

// Lazy collection - daha da memory-efficient
User::where('active', true)->lazy(1000)->each(function ($user) {
    // Process one at a time, 1000-lik batch-larla DB-dən çəkir
});

// Select specific columns
User::select('id', 'name', 'email')->where('active', true)->get();

// Subquery
$latestOrder = Order::select('created_at')
    ->whereColumn('user_id', 'users.id')
    ->latest()
    ->limit(1);

$users = User::select('users.*')
    ->selectSub($latestOrder, 'last_order_date')
    ->get();

// Raw expressions (carefully)
$stats = Order::select([
    DB::raw('DATE(created_at) as date'),
    DB::raw('COUNT(*) as order_count'),
    DB::raw('SUM(total) as revenue'),
])
->where('created_at', '>=', now()->subDays(30))
->groupBy('date')
->orderBy('date')
->get();
```

### Database Transactions

```php
// Basic transaction
DB::transaction(function () {
    $order = Order::create([...]);

    foreach ($items as $item) {
        $order->items()->create($item);

        Product::where('id', $item['product_id'])
            ->decrement('stock', $item['quantity']);
    }
});

// Manual transaction with retry
DB::transaction(function () {
    // ... operations
}, 3); // Retry 3 times on deadlock

// Pessimistic locking
$product = Product::where('id', 1)->lockForUpdate()->first();
$product->decrement('stock', 1);

// Optimistic locking (manual)
$product = Product::find(1);
$updated = Product::where('id', 1)
    ->where('version', $product->version)
    ->update([
        'stock' => $product->stock - 1,
        'version' => $product->version + 1,
    ]);

if (!$updated) {
    throw new OptimisticLockException('Concurrent modification detected');
}
```

### Eloquent Scopes & Performance

```php
// app/Models/Order.php
class Order extends Model
{
    // Global scope - həmişə tətbiq olunur
    protected static function booted(): void
    {
        static::addGlobalScope('recent', function ($query) {
            $query->where('created_at', '>', now()->subYear());
        });
    }

    // Local scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithTotalAbove($query, float $amount)
    {
        return $query->where('total', '>', $amount);
    }
}

// İstifadə
$orders = Order::active()
    ->forUser(auth()->id())
    ->withTotalAbove(100)
    ->with('items')
    ->paginate(20);
```

## Real-World Nümunələr

**Facebook:** MySQL (primary) + TAO (caching) + ZippyDB (key-value). Database sharding
user_id ilə. Milyardlarla row, minlərlə shard. Custom MySQL fork (MyRocks storage engine).

**Uber:** PostgreSQL + Redis + Cassandra + Elasticsearch. Trip data Cassandra-da (write-heavy),
User data PostgreSQL-da, Search Elasticsearch-da. Schemaless (custom sharding layer).

**Shopify:** MySQL sharding. Hər shop group bir shard-da. Online schema migration (gh-ost).
10+ PB data, 1M+ queries/saniyə. Read replicas intensive istifadə.

**Netflix:** Cassandra (primary), EVCache, Elasticsearch. Cassandra multi-region
replication. Hər region öz data copy-sinə malikdir. Eventually consistent model.

## Praktik Tapşırıqlar

**S: SQL vs NoSQL nə vaxt istifadə olunur?**
C: SQL - structured data, complex relations, ACID, reporting (e-commerce, banking).
NoSQL - flexible schema, horizontal scaling, high write throughput (social media, IoT, logs).
Çox vaxt hər ikisi istifadə olunur (polyglot persistence).

**S: Index niyə yavaşlada bilər?**
C: Index read-ı sürətləndirir amma write-ı yavaşladır. Hər INSERT/UPDATE/DELETE-də index
yenilənməlidir. Çox index = yavaş write. Index disk space tutur. Query planner yanlış
index seçə bilər. ANALYZE/EXPLAIN ilə yoxlamaq lazımdır.

**S: Database sharding-in çətinlikləri?**
C: Cross-shard JOIN mümkün deyil, distributed transactions çətin, resharding (shard
əlavə etmək) complex, application logic-də routing lazım, uneven data distribution
(hot spots). Mümkün qədər gecikdirin, əvvəl read replica + caching yetərlidir.

**S: Optimistic vs pessimistic locking?**
C: Pessimistic: Row lock edir, digər transaction gözləyir. Conflict çox olanda yaxşı.
Optimistic: Lock etmir, version check edir, conflict olsa retry. Conflict az olanda yaxşı.
E-commerce stock: pessimistic. Blog post edit: optimistic.

## Praktik Baxış

1. **İndexləri EXPLAIN ilə yoxlayın** - Hər yavaş query-ni EXPLAIN edin
2. **N+1 önləyin** - Eager loading, preventLazyLoading istifadə edin
3. **Migration-larda index əlavə edin** - Sonradan böyük table-a index əlavə etmək çətindir
4. **Soft deletes düşünün** - Data recovery üçün, amma index-ə təsir edir
5. **JSON column-lar limitlə** - Query-lərdə yavaşdır, index olmur (virtual column istisna)
6. **Connection pool istifadə edin** - Max connection limitini aşmayın
7. **Read/write split** - Read-heavy workload üçün replica istifadə edin
8. **Monitoring** - Slow query log, deadlock log, connection count izləyin


## Əlaqəli Mövzular

- [Data Partitioning](26-data-partitioning.md) — böyük cədvəlləri şardlamaq
- [Database Replication](43-database-replication.md) — read scale və HA
- [Caching](03-caching-strategies.md) — DB yükünü azaltmaq
- [SQL vs NoSQL](41-sql-vs-nosql-selection.md) — doğru DB seçimi
- [Consistency Patterns](32-consistency-patterns.md) — DB-nin consistency zəmanətləri
