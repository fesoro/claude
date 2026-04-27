# Database Query Optimization in Practice (Senior ⭐⭐⭐)

## İcmal

Query optimization — tətbiqin DB ilə danışıq dilini effektivləşdirmək prosesidir. Hər yavaş tətbiqin arxasında ya artıq sorğu, ya da pis yazılmış sorğu var. Senior developer üçün bu bacarıq vacibdir: sadəcə "index əlavə et" demək deyil, query execution plan oxumaq, join order anlamaq, statistika eskizlərini tanımaq.

## Niyə Vacibdir

Production-da 90% performance problemi DB-dən qaynaqlanır. Laravel ilə yazılan kod görünüşcə sadə ola bilər, amma arxa planda yüzlərlə query göndərilə bilər. Bir e-commerce saytda `Order::all()` yazmaq bütün sifariş tarixçəsini RAM-a yükləyər. Real layihələrdə query optimization birbaşa infrastructure cost-u azaldır — daha az DB CPU = daha az server = daha az xərc.

## Əsas Anlayışlar

- **EXPLAIN / EXPLAIN ANALYZE:**
  - `EXPLAIN` — execution plan-ı göstərir (actual data olmadan)
  - `EXPLAIN ANALYZE` — plan-ı icra edir, real vaxtı göstərir (PostgreSQL)
  - `EXPLAIN FORMAT=JSON` — machine-readable output

- **Query execution plan oxumaq:**
  - **Seq Scan** — bütün cədvəli oxu (index yoxdur / selectivity aşağıdır)
  - **Index Scan** — index üzərindən get
  - **Index Only Scan** — yalnız index-dən cavab (covering index)
  - **Nested Loop / Hash Join / Merge Join** — join alqoritmləri
  - **cost=X..Y** — başlanğıc / tam icra xərci (planner üçün)
  - **rows=N** — gözlənilən sətir sayı (statistika)
  - **actual time** — real icra vaxtı (ANALYZE ilə)

- **N+1 Problem:**
  - Hər əsas record üçün ayrıca query göndərilmə
  - Laravel: `with()` (eager loading) ilə həll
  - Aggregat üçün: `withCount()`, `withSum()`, `withAvg()`

- **Slow query növləri:**
  - **Full table scan** — böyük cədvəldə index yoxdur
  - **Function on indexed column** — `WHERE YEAR(created_at) = 2024` → index işləmir
  - **LIKE '%text%'** — leading wildcard → Full Text Search lazım
  - **SELECT \*** — lazımsız sütunlar network-a gəlir
  - **Missing covering index** — sort/filter sütunları index-də yoxdur
  - **Implicit type cast** — `WHERE id = '123'` (string vs int)

- **Optimization texnikaları:**
  - **Projection** — yalnız lazım olan sütunları seç (`select('id', 'name')`)
  - **Covering index** — WHERE + ORDER + SELECT sütunları index-də
  - **Query rewrite** — subquery-ni JOIN ilə əvəz et
  - **Denormalization** — read performance üçün məlumatı artıq saxlamaq
  - **Materialized view** — hesablanmış nəticəni cədvəl kimi saxlamaq
  - **Partitioning** — böyük cədvəli date/range ilə böl

## Praktik Baxış

**Optimization iş axını:**

```
1. Slow query tap (APM / slow log / Telescope)
2. EXPLAIN ANALYZE çək
3. Seq Scan / high rows estimate gör
4. Index varmı? Statistika köhnəlibmi?
5. Query rewrite cəhd et
6. Index yarat (CONCURRENTLY — prod üçün)
7. EXPLAIN ANALYZE yenidən çək
8. Actual vs estimated rows fərqi varsa ANALYZE çalışdır
```

**EXPLAIN ANALYZE nümunəsi (PostgreSQL):**

```sql
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT o.id, o.total, u.name
FROM orders o
JOIN users u ON u.id = o.user_id
WHERE o.status = 'pending'
  AND o.created_at >= NOW() - INTERVAL '30 days'
ORDER BY o.created_at DESC
LIMIT 50;

-- Baxılacaqlar:
-- "Seq Scan on orders" → index lazımdır
-- "rows=50000 actual rows=48" → selectivity problem
-- "Sort Method: external merge Disk: 1024kB" → work_mem artır
-- "Buffers: shared hit=0 read=5000" → cache miss çox
```

**Laravel N+1 fix:**

```php
// ❌ Pis: N+1
$orders = Order::where('status', 'pending')->get();
foreach ($orders as $order) {
    echo $order->user->name;         // +1 query
    echo $order->items->count();     // +1 query
}

// ✅ Yaxşı: Eager loading
$orders = Order::with(['user', 'items'])
    ->where('status', 'pending')
    ->get();

// ✅ Aggregate üçün:
$orders = Order::withCount('items')
    ->withSum('items', 'price')
    ->where('status', 'pending')
    ->get();
// Hər order-da: $order->items_count, $order->items_sum_price
```

**Covering index nümunəsi:**

```sql
-- Query:
SELECT id, status, created_at
FROM orders
WHERE user_id = 123
  AND status = 'pending'
ORDER BY created_at DESC;

-- Covering index (bütün sütunları əhatə edir):
CREATE INDEX idx_orders_covering
ON orders (user_id, status, created_at DESC)
INCLUDE (id);  -- PostgreSQL 11+

-- Nəticə: Index Only Scan → disk I/O yoxdur
```

**Function-based index (PHP yanaşması):**

```sql
-- ❌ Index işləmir:
WHERE LOWER(email) = 'test@example.com'

-- ✅ Həll 1: Functional index
CREATE INDEX idx_users_email_lower ON users (LOWER(email));

-- ✅ Həll 2: Application layer (daha yaxşı)
// Laravel mutator ilə email həmişə lowercase saxla
protected function setEmailAttribute(string $value): void
{
    $this->attributes['email'] = strtolower($value);
}
```

**Subquery vs JOIN:**

```sql
-- ❌ Yavaş: Correlated subquery (hər row üçün icra)
SELECT * FROM users u
WHERE (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) > 5;

-- ✅ Sürətli: JOIN + GROUP BY
SELECT u.*
FROM users u
INNER JOIN (
    SELECT user_id, COUNT(*) as cnt
    FROM orders
    GROUP BY user_id
    HAVING COUNT(*) > 5
) top ON top.user_id = u.id;
```

**Laravel query builder optimization:**

```php
// ❌ SELECT * + bütün modeli RAM-a yüklə
$users = User::all();

// ✅ Yalnız lazım olan
$users = User::select('id', 'name', 'email')
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->limit(100)
    ->get();

// ✅ Böyük dataset üçün chunk
User::where('active', true)->chunk(500, function ($users) {
    foreach ($users as $user) {
        // process
    }
});

// ✅ Cursor (memory-efficient, server-side cursor)
foreach (User::where('active', true)->cursor() as $user) {
    // yalnız 1 model eyni anda RAM-da
}
```

**Trade-offs:**
- Index artıqlığı → INSERT/UPDATE/DELETE yavaşlayır
- Denormalization → data consistency riski
- Covering index → disk space artır
- Materialized view → stale data riski (refresh interval)

**Common mistakes:**
- `SELECT *` istifadə etmək
- WHERE clause-da indexed sütunu function ilə wrap etmək
- Large dataset-i `get()` ilə RAM-a yükləmək (`chunk` lazım)
- Production-da EXPLAIN olmadan index yaratmaq
- Composite index sırasını bilməmək (selectivity order)

## Nümunələr

### Real Ssenari: Dashboard 8 saniyə çəkir

```
Şikayət: Admin dashboard "Revenue" kartı 8s çəkir.

Debug prosesi:
1. Telescope → 1 query, 7.9s
2. EXPLAIN ANALYZE çəkdim

SQL:
SELECT SUM(total), DATE(created_at) as day
FROM orders
WHERE status = 'completed'
  AND created_at >= '2024-01-01'
GROUP BY DATE(created_at);

Problem: DATE(created_at) → function call, index işləmir. 2M row full scan.

Həll 1 (index): CREATE INDEX ON orders (status, created_at);
Həll 2 (daha yaxşı): Precomputed daily_revenue cədvəli, cron ilə güncəllənir.
Nəticə: 7.9s → 0.04s
```

### Kod Nümunəsi

```php
<?php

// Service: Query optimization helper
class OrderQueryService
{
    /**
     * Optimized revenue query - date range + status
     * Index: (status, created_at) composite
     */
    public function getDailyRevenue(string $from, string $to): Collection
    {
        return DB::table('orders')
            ->select([
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as order_count'),
            ])
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get();
    }

    /**
     * Efficient order listing with all relations
     * Covering index: (user_id, status, created_at)
     */
    public function getPendingOrdersForUser(int $userId): LengthAwarePaginator
    {
        return Order::select('id', 'status', 'total', 'created_at', 'user_id')
            ->with([
                'items:id,order_id,product_id,quantity,price',
                'items.product:id,name,sku',
            ])
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * Batch update - single query instead of N updates
     */
    public function markOrdersAsProcessed(array $orderIds): int
    {
        return Order::whereIn('id', $orderIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'processed_at' => now(),
            ]);
        // 1 query — NOT N queries in a loop
    }
}
```

```php
// Migration: Optimal index yaratmaq
Schema::table('orders', function (Blueprint $table) {
    // Composite index — WHERE status + ORDER BY created_at
    $table->index(['status', 'created_at'], 'idx_orders_status_created');

    // Covering index — user orders list
    $table->index(['user_id', 'status', 'created_at'], 'idx_orders_user_status');
});

// PostgreSQL CONCURRENTLY (production, lock olmadan)
DB::statement('CREATE INDEX CONCURRENTLY idx_orders_status_created
               ON orders (status, created_at DESC)');
```

## Praktik Tapşırıqlar

1. **Slow query tap:** MySQL slow query log aktivləşdir (`long_query_time=0.1`), Laravel Seederi ilə 100K order yarat, dashboard endpointini yükle, ilk 3 slow query-ni analiz et.

2. **EXPLAIN ANALYZE oxu:** pgAdmin və ya `\x` (psql) ilə bir neçə query-nin execution plan-ını oxu, Seq Scan olan birini tapıb index ilə düzəlt.

3. **N+1 audit:** Mövcud bir controller götür, Debugbar aktivləşdir, query count-u say, `with()` ilə optimallaşdır, əvvəl/sonra müqayisə et.

4. **Covering index test:** `orders` cədvəli üçün bir covering index yarat, EXPLAIN ANALYZE ilə "Index Only Scan" əldə et.

5. **Denormalization:** `order_items` cədvəlindən `orders.total` hesablayan trigger əvəzinə, tətbiq səviyyəsində saxlamağı düşün — hansı daha yaxşıdır? Trade-off-ları yaz.

## Əlaqəli Mövzular

- `01-performance-profiling.md` — Profiling ilə query bottleneck tapmaq
- `15-indexing-strategy.md` — Index növləri və strategiyası
- `08-pagination-strategies.md` — Böyük dataset-ləri səhifələmək
- `03-caching-layers.md` — Query nəticəsini cache-ləmək
- `05-connection-pool-tuning.md` — DB connection idarəetmə
