# Database Scaling Strategies (Senior)

## Scaling Nedir?

Database-in artan yuku (trafik, data volume, query complexity) dasiya bilmesi. Iki esas yol var:

```
Vertical Scaling (Scale Up):   Daha guclu server al
Horizontal Scaling (Scale Out): Daha cox server elave et
```

## Vertical Scaling (Scale Up)

Movcud serverin hardware-ini guclendir.

```
Before:  4 CPU, 16GB RAM, 500GB SSD
After:   32 CPU, 128GB RAM, 2TB NVMe

Ustunlukler:
✅ Sadedir - kod deyismez
✅ Transaction/JOIN tam isleyir
✅ Management asandir

Dezavantajlar:
❌ Limit var (en guclu server de yetmez)
❌ Bahalidir (2x CPU ≠ 2x performance)
❌ Single point of failure
❌ Downtime lazim ola biler (hardware deyisiklik)
```

## Horizontal Scaling (Scale Out)

Birden cox server istifade et. Yuku bolusdur.

```
Before:  1 server (butun yuk)
After:   1 primary + 3 replica (read yuku bolunur)
         ve ya 4 shard (data bolunur)
```

## Scaling Strategiyalari (Merheleli)

Production-da scaling adim-adim edilir. Her merhele evvelkinin limitine catanda baslanir.

### Merhele 1: Query Optimization

**Hec bir infrastructure deyisikliyi olmadan** performance-i artirir.

```sql
-- 1. Lazimi index-leri elave et
CREATE INDEX idx_orders_user_status ON orders (user_id, status);

-- 2. EXPLAIN ile yavas query-leri tap ve duzelt
EXPLAIN ANALYZE SELECT ...;

-- 3. N+1 problemi hell et
-- YANLIS: 100 user ucun 101 query
SELECT * FROM users;
SELECT * FROM orders WHERE user_id = 1;  -- x100

-- DOGRU: 2 query
SELECT * FROM users;
SELECT * FROM orders WHERE user_id IN (1,2,...,100);

-- 4. Lazim olmayan data-ni yukle
SELECT id, name, price FROM products;        -- DOGRU
-- SELECT * FROM products;                    -- YANLIS (BLOB, TEXT yukler)
```

### Merhele 2: Caching

Database-e gelen query sayini azaltmaq.

```php
// Application-level cache (Redis)
$products = Cache::remember('products:featured', 3600, function () {
    return Product::where('is_featured', true)
        ->with('category')
        ->get();
});

// Query result cache
$stats = Cache::remember("user:{$userId}:order_stats", 600, function () use ($userId) {
    return Order::where('user_id', $userId)
        ->selectRaw('COUNT(*) as count, SUM(total) as total')
        ->first();
});
```

```
Caching strategiyasi:
Request → Redis Cache → HIT? → Cavab qaytar
                      → MISS? → Database → Cache-e yaz → Cavab qaytar

Database yuku: %80+ azala biler
```

### Merhele 3: Read Replicas

Read yuku birden cox servere paylasdirilir.

```
                 ┌── Replica 1 (reads)
Primary ────────├── Replica 2 (reads)
(writes)        └── Replica 3 (reads)
```

```php
// Laravel - Read/Write Splitting
// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            '192.168.1.2',  // Replica 1
            '192.168.1.3',  // Replica 2
            '192.168.1.4',  // Replica 3
        ],
    ],
    'write' => [
        'host' => '192.168.1.1',  // Primary
    ],
    'sticky' => true,  // Write-dan sonra eyni request-de primary-den oxu
],

// Avtomatik - SELECT replica-ya, INSERT/UPDATE/DELETE primary-ya gedir
$users = User::all();                    // Replica-dan oxuyur
$user = User::create(['name' => '...']); // Primary-ya yazir
$user->orders;                           // sticky=true: Primary-dan oxuyur (yeni yaradildi)

// Manuel control
$users = DB::connection('mysql::read')->table('users')->get();
```

**Replication Lag problemi:**
```php
// User yaratdi → dərhal profile sehifesine redirect → User gorsenmir!
// Sebebi: Replica hele sync olmayib

// Helli 1: sticky sessions (yuxarida)
// Helli 2: Critical read-leri primary-den oxu
$user = User::on('mysql::write')->find($userId);

// Helli 3: Write-dan sonra qisa gecikme
return redirect()->route('profile')->with('flash', 'Profil yaradildi');
// Redirect zamani replica sync olur
```

### Merhele 4: Connection Pooling

Database connection-larini effektiv idare et.

```
Problem:
100 PHP worker × 1 connection = 100 connection
1000 PHP worker = 1000 connection → Database cokur!

Helli: Connection Pooler
1000 PHP worker → PgBouncer (50 conn) → PostgreSQL
```

```ini
# PgBouncer config
[databases]
mydb = host=127.0.0.1 port=5432 dbname=mydb

[pgbouncer]
pool_mode = transaction          # Transaction bitende connection geri qaytarilir
max_client_conn = 1000           # Client-lerden max 1000
default_pool_size = 50           # DB-ye max 50 connection
reserve_pool_size = 10           # Extra 10 (peak zamani)
```

### Merhele 5: Partitioning

Boyuk table-lari kicik hisselere bol (eyni server-de).

```sql
-- Tarixe gore partition
CREATE TABLE orders (
    id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    total DECIMAL(12,2),
    created_at DATE NOT NULL,
    PRIMARY KEY (id, created_at)
) PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2022 VALUES LESS THAN (2023),
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION pmax  VALUES LESS THAN MAXVALUE
);

-- Query yalniz lazim olan partition-i scan edir (partition pruning)
SELECT * FROM orders WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31';
-- Yalniz p2024 partition-a baxir, diger iller ignore olunur
```

### Merhele 6: Sharding

Data birden cox database server-e paylanir.

```
Shard 1 (users 1-1M)      → Server A
Shard 2 (users 1M-2M)     → Server B
Shard 3 (users 2M-3M)     → Server C

-- Her shard mustaqil primary + replica ola biler
Shard 1: Primary A + Replica A1, A2
Shard 2: Primary B + Replica B1, B2
```

```php
// Laravel - Manual Sharding
class ShardManager
{
    public function getConnectionForUser(int $userId): string
    {
        $shardId = $userId % 3;  // 3 shard

        return match ($shardId) {
            0 => 'shard_0',
            1 => 'shard_1',
            2 => 'shard_2',
        };
    }
}

// Istifade
$connection = $shardManager->getConnectionForUser($userId);
$orders = Order::on($connection)
    ->where('user_id', $userId)
    ->get();
```

### Merhele 7: CQRS (Command Query Responsibility Segregation)

Read ve Write ucun **ferqli database/model** istifade et.

```
Write Model (Primary):
PostgreSQL → Normalized (3NF) → ACID transactions

Read Model (Optimized):
Elasticsearch → Denormalized → Suretli search
Redis → Aggregated data → Dashboard/stats
MongoDB → Document → API responses

Sync: CDC (Debezium) ve ya Event-driven
```

```php
// Write (Command)
class CreateOrderHandler
{
    public function handle(CreateOrderCommand $command): void
    {
        DB::transaction(function () use ($command) {
            $order = Order::create([...]);
            OrderItem::insert([...]);
            Product::decrement('stock', $command->quantity);
        });
        // Event publish → Read model yenilenir
    }
}

// Read (Query) - ayri, optimize edilmis source-dan
class OrderSearchHandler
{
    public function handle(SearchOrdersQuery $query): array
    {
        // Elasticsearch-den oxu (denormalized, suretli)
        return $this->elasticsearch->search([
            'index' => 'orders',
            'body' => [
                'query' => ['match' => ['status' => $query->status]],
                'sort' => [['created_at' => 'desc']],
            ],
        ]);
    }
}
```

## Scaling Decision Tree

```
Database yavasdir?
├── Query yavasdir?
│   ├── Index elave et (Merhele 1)
│   ├── Query optimize et (Merhele 1)
│   └── EXPLAIN analiz et
│
├── Read yuku coxdur?
│   ├── Cache elave et (Merhele 2)
│   ├── Read replica elave et (Merhele 3)
│   └── CQRS (Merhele 7)
│
├── Write yuku coxdur?
│   ├── Batch operations (Merhele 1)
│   ├── Async processing (queue)
│   └── Sharding (Merhele 6)
│
├── Connection limiti?
│   └── Connection pooling (Merhele 4)
│
├── Table cox boyukdur?
│   ├── Partitioning (Merhele 5)
│   ├── Arxivleme (kohne data-ni kocur)
│   └── Sharding (Merhele 6)
│
└── Single server limiti?
    ├── Vertical scaling (daha guclu server)
    └── Horizontal scaling (Merhele 3-7)
```

## Scaling Anti-Patterns

### 1. Premature Optimization

```
YANLIS: "Gelecekde 1M user olacaq, indi shard edek"
DOGRU: "Hazirda 10K user var, index ve cache kifayet edir"

Shard etmek ucun minimum:
- Tek server-in CPU/RAM/Disk limiti
- 100M+ row olan table-lar
- Write throughput tek serveri asir
```

### 2. Sharding Too Early

```
Sharding-in qiymeti:
- Cross-shard JOIN yazmaq olmur
- Distributed transactions cetindir
- Resharding agrilidır
- Operational complexity artir

Evvence bunlari et:
1. Query optimization
2. Caching
3. Read replicas
4. Vertical scaling
5. Partitioning
6. EN SONUNDA: Sharding
```

### 3. Ignoring Connection Limits

```php
// YANLIS: Her request yeni connection
// 1000 concurrent request = 1000 connection = database olur

// DOGRU: Connection pooling
// 1000 concurrent request → PgBouncer → 50 connection
```

## Real-World Scaling Numuneleri

| Miqyas | User | Strategy |
|--------|------|----------|
| **Startup** | < 10K | Tek server, query optimization, basic cache |
| **Growing** | 10K - 100K | Read replicas, Redis cache, CDN |
| **Scale** | 100K - 1M | Connection pooling, partitioning, CQRS |
| **Large** | 1M - 10M | Sharding, multi-region, dedicated search (ES) |
| **Massive** | 10M+ | Multi-database (SQL + NoSQL), custom solutions |

## Interview Suallari

1. **Vertical vs Horizontal scaling ferqi?**
   - Vertical: Daha guclu server (asan, limitli). Horizontal: Daha cox server (cetindir, limitsiz).

2. **Scaling-de ilk adim ne olmalidir?**
   - Query optimization! Index, EXPLAIN, N+1 fix. Sonra cache. Infrastructure en sonda deyisdirmek lazimdir.

3. **Read replica-nin limitasyonlari?**
   - Replication lag (eventual consistency), write-lari scale etmir, cross-region lag daha boyuk.

4. **Ne vaxt sharding lazimdir?**
   - Tek serverin CPU/RAM/disk limiti, table coxlu billion row, write throughput bir serveri asir. Amma evvelce butun diger option-lari tuket.

5. **CQRS niye scaling ucun yaxsidir?**
   - Read ve write muxtelif requirment-lere malikdir. Read: suret, denormalization. Write: consistency, ACID. Ayirmaqla her birini mustaqil optimize etmek olur.

6. **Sticky sessions nedir ve niye lazimdir?**
   - Write-dan sonra eyni request-de read replica yerine primary-den oxumaq. Replication lag sebebile user oz deyisikliklerini gormeyebiler.
