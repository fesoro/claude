# Query Optimization Techniques (Senior ⭐⭐⭐)

## İcmal
Query optimization — yavaş sorğuları analiz edib sürətləndirmə prosesidir. Senior interview-larda bu mövzu demək olar ki, mütləq çıxır: EXPLAIN ANALYZE nəticəsini oxumaq, N+1 problem, missing index, yanlış JOIN strategiyası. Real production sorğusunu optimize etmək bacardığınızı göstərməyiniz gözlənilir.

## Niyə Vacibdir
Hər sistemin vaxtında gəldiyi nöqtə: "Bu sorğu niyə bu qədər yavaşdır?" İnterviewer sizin bu problemi sistematik şəkildə həll edib-etmədiyinizi yoxlayır. "Index əlavə etdim" kifayət deyil — niyə həmin index, niyə həmin column, alternativlər nə idi? Bu sual sizin production təcrübənizi ortaya qoyur.

## Əsas Anlayışlar

- **Query Planner/Optimizer**: Database-in sorğunu necə icra edəcəyinə qərar verən komponent. Cost-based model — statistikalardan istifadə edir, ən ucuz plan seçilir.
- **EXPLAIN**: Execution plan-ı göstərir (estimated cost). Real icra olmur, sadəcə plan.
- **EXPLAIN ANALYZE**: Real icra edir, actual time + actual rows göstərir — plan vs real müqayisəsi. Yavaş sorğu üçün `EXPLAIN (ANALYZE, BUFFERS)` daha çox məlumat verir.
- **Seq Scan (Sequential Scan)**: Table-ı başdan sona skan edir. Kiçik tablolar üçün OK. Böyük tablolarda `rows=100000` Seq Scan ciddi problemdir.
- **Index Scan**: Index B-tree-sini traverse edir, sonra table-a pointer ilə gedir. Index-də tapılmayan hissə table-dan oxunur.
- **Index Only Scan**: Bütün lazım olan data index-dədir — table heap-ə müraciət yoxdur. Ən sürətli scan növü.
- **Bitmap Heap Scan**: Bir neçə index nəticəsini AND/OR ilə birləşdirir. Selective predicate-lar üçün.
- **Nested Loop Join**: Kiçik dataset-lər, indexed join column üçün optimal. Outer row hər biri üçün inner-i axtarır. Large dataset-lərdə exponential.
- **Hash Join**: Böyük dataset, equality join. Bir tərəfi hash table-a yükləyir, digərini scan edir. Memory tələb edir.
- **Merge Join**: İki sorted dataset — linear scan. Pre-sorted data ya sort edilmiş index üçün optimal.
- **Statistics (pg_statistics)**: Planner column distribution-ı buradan alır. Köhnəlsə yanlış plan seçilir.
- **ANALYZE**: Statistics-i yeniləyir. Auto-analyze default aktiv, amma böyük bulk insert sonrası manual lazım ola bilər.
- **Cardinality Estimate**: Planner-in neçə row gəlacağını düşündüyü. Actual vs Estimated fərqi böyükdürsə yanlış plan seçilir.
- **Cost units**: `cost=0.00..45.20` — startup cost + total cost. Disk page read (seq_page_cost=1.0) + CPU work.
- **Correlated Subquery**: Hər row üçün ayrıca subquery icra olunur — SQL-in N+1-i. JOIN-a çevir.
- **CTE (WITH clause)**: Sorğunu hissələrə ayırır. PostgreSQL 12-dən default olaraq inline edilir (optimizasiya olunur). `WITH ... AS MATERIALIZED` forced materialization.
- **Partition Pruning**: Partitioned table-da planner yalnız lazım olan partition-ları skanlar. Range partition ilə date filter sorğuları çox sürətlənir.
- **Covering Index**: SELECT-dəki bütün column-lar index-dədir → table I/O yoxdur. `Index Only Scan` əldə olunur.
- **Partial Index**: Şərtə uyğun row-lar üçün index. `CREATE INDEX ON orders(status) WHERE status = 'pending'` — yalnız pending order-lər index-dədir.
- **Expression Index**: Function nəticəsi üzərindən index. `CREATE INDEX ON users(LOWER(email))` — case-insensitive search üçün.

## Praktik Baxış

**Interview-da yanaşma:**
- Sistematik: 1) EXPLAIN ANALYZE çalışdır, 2) Seq Scan-ları tap, 3) Estimated vs actual row fərqini yoxla, 4) Statistics-i yenilə, 5) index qoy, 6) query-ni yenidən yaz
- "Biz X sorğunu Y ms-dən Z ms-ə endirdik, belə etdik" formatında danış
- Query rewrite-ı index-dən əvvəl düşün — bəzən sorğunun özü problemdir

**Follow-up suallar interviewerlər soruşur:**
- "EXPLAIN output-da hansı metrics-ə baxırsınız? Actual vs estimated rows fərqini görəndə nə edirsiniz?"
- "Correlated subquery-ni necə yenidən yazarsınız?"
- "Planner yanlış plan seçirsə nə edərsiniz?" — `pg_hint_plan`, statistics update, `SET enable_seqscan = off` (debug üçün)
- "Covering index nədir, nə zaman işləyir?"
- "Partial index nə vaxt yararlıdır?"

**Ümumi candidate səhvləri:**
- "Index qoymaq həmişə kömək edir" demək — index yazma əməliyyatlarını yavaşladır, disk tutur
- EXPLAIN ilə EXPLAIN ANALYZE-i fərqləndirməmək — birincisi plan, ikincisi real icra
- Join order-in önəmini bilməmək
- `SELECT *` istifadəsi — lazımsız column-lar I/O artırır, covering index işləmir
- Function-wrapped şərt: `WHERE YEAR(created_at) = 2024` — index deaktiv olur

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- "Actual rows" vs "estimated rows" fərqini görüb statistics problemi kimi tanıya bilmək
- Query rewrite — correlated subquery → JOIN/CTE dönüşümü
- `EXPLAIN (ANALYZE, BUFFERS)` output-unu oxumaq: `Buffers: shared hit=X read=Y` — disk I/O-nu görüb index lazım olduğunu anlamaq

## Nümunələr

### Tipik Interview Sualı
"Bu sorğu 8 saniyə çalışır: `SELECT * FROM orders WHERE YEAR(created_at) = 2024`. Necə optimize edərdiniz?"

### Güclü Cavab
"Bu sorğuda iki ayrı problem var.

Birinci problem: `YEAR(created_at) = 2024` — function wrapping. `created_at` sütununda index varsa belə istifadə edilmir, çünki planner hər row üçün `YEAR()` funksiyasını çağırmalıdır — Seq Scan qaçınılmazdır. Həll: range condition — `WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01'`. Bu artıq index istifadə edə bilər.

İkinci problem: `SELECT *` — bütün column-ları çəkir. Yalnız lazım olanları seçmək həm I/O-nu azaldır, həm də covering index imkanı yaradır.

Addımlarım: 1) `EXPLAIN (ANALYZE, BUFFERS)` ilə real plan görürəm, 2) Seq Scan varsa və table böyükdürsə index əlavə edirəm, 3) Buffers-dəki `read` sayı çoxsa disk I/O problemdir — covering index köməkçi ola bilər, 4) Table çox böyükdürsə range partition `created_at` üzərindən partition pruning verir.

`pg_stat_statements`-dən bu sorğunun nə qədər tez-tez çalışdığını, ortalama vaxtını görüb prioritet verirəm."

### Kod / SQL Nümunəsi — EXPLAIN ANALYZE

```sql
-- PROBLEM: Function wrapping — index işləmir
SELECT * FROM orders WHERE YEAR(created_at) = 2024;  -- Seq Scan!

-- HƏLL: Range condition — index istifadə edir
SELECT order_id, user_id, total_amount, status
FROM orders
WHERE created_at >= '2024-01-01'
  AND created_at < '2025-01-01';

-- EXPLAIN ANALYZE ilə müqayisə
-- Before:
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT * FROM orders WHERE YEAR(created_at) = 2024;
/*
Seq Scan on orders  (cost=0.00..125000.00 rows=50000 width=256)
                    (actual time=0.1..8200.5 rows=48321 loops=1)
  Filter: (EXTRACT(year FROM created_at) = 2024)
  Rows Removed by Filter: 1651679
  Buffers: shared hit=12 read=89238
Planning Time: 0.2 ms
Execution Time: 8389.7 ms
*/

-- After (+ index):
CREATE INDEX idx_orders_created_at ON orders(created_at);

EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT order_id, user_id, total_amount, status
FROM orders
WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01';
/*
Index Scan using idx_orders_created_at on orders
  (cost=0.56..4821.33 rows=48000 width=64)
  (actual time=0.1..42.3 rows=48321 loops=1)
  Buffers: shared hit=4835 read=8
Planning Time: 0.3 ms
Execution Time: 43.1 ms
*/
-- 8389ms → 43ms : ~195x sürətlənmə
```

### Kod Nümunəsi — Correlated Subquery → JOIN Rewrite

```sql
-- YAVAŞ: Correlated subquery — hər order üçün ayrıca subquery
SELECT order_id, (
    SELECT name FROM customers WHERE id = o.customer_id
) AS customer_name
FROM orders o;
-- orders table-da 1M row varsa → 1M subquery!

-- SÜRƏTLI: JOIN ilə — bir dəfə
SELECT o.order_id, c.name AS customer_name
FROM orders o
INNER JOIN customers c ON c.id = o.customer_id;

-- EXISTS vs IN rewrite
-- YAVAŞ: IN ilə large subquery — bütün ID-lər yaddaşa yüklənir
SELECT * FROM customers
WHERE id IN (SELECT customer_id FROM orders WHERE status = 'active');

-- SÜRƏTLI: EXISTS — ilk match-də dayanır, bütün ID-ləri yükləmir
SELECT * FROM customers c
WHERE EXISTS (
    SELECT 1 FROM orders o
    WHERE o.customer_id = c.id AND o.status = 'active'
);

-- CTE ilə oxunaqlı + sürətli
WITH recent_orders AS (
    SELECT customer_id,
           MAX(created_at) AS last_order_date,
           COUNT(*)        AS order_count,
           SUM(total_amount) AS total_spent
    FROM orders
    WHERE created_at > NOW() - INTERVAL '90 days'
    GROUP BY customer_id
)
SELECT c.name, c.email, ro.last_order_date, ro.order_count, ro.total_spent
FROM customers c
INNER JOIN recent_orders ro ON ro.customer_id = c.id
ORDER BY ro.total_spent DESC;
```

### Kod Nümunəsi — Covering Index

```sql
-- Covering index: SELECT-dəki bütün column-lar index-dədir
-- Table heap-ə getmir → Index Only Scan

-- Tez-tez işlənən sorğu
SELECT user_id, status, total_amount
FROM orders
WHERE user_id = 42 AND status = 'pending';

-- Normal index — table-a gedir
CREATE INDEX idx_orders_user ON orders(user_id);
-- Index Scan + Heap Fetch

-- Covering index — bütün lazım olan column-lar burada
CREATE INDEX idx_orders_user_covering
    ON orders(user_id, status)
    INCLUDE (total_amount);
-- Index Only Scan — table-a getmir!

-- Partial covering index — yalnız pending order-lər
CREATE INDEX idx_orders_pending_covering
    ON orders(user_id)
    INCLUDE (total_amount, created_at)
    WHERE status = 'pending';
-- Daha kiçik index, yalnız pending-lər üçün
```

### Kod Nümunəsi — Laravel Query Optimization

```php
// YAVAŞ: N+1 + SELECT *
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name; // Hər order üçün ayrıca query!
}

// SÜRƏTLI: Eager loading + specific columns
$orders = Order::select('orders.id', 'orders.status', 'orders.total_amount', 'orders.customer_id')
    ->with(['customer:id,name,email']) // Yalnız lazım olan column-lar
    ->where('status', 'pending')
    ->where('created_at', '>=', now()->subDays(7))
    ->get();

// JOIN ilə daha da sürətli (1 sorğu)
$orders = Order::select(
        'orders.id',
        'orders.status',
        'orders.total_amount',
        'customers.name as customer_name'
    )
    ->join('customers', 'customers.id', '=', 'orders.customer_id')
    ->where('orders.status', 'pending')
    ->get();

// Slow query-ləri tap
DB::enableQueryLog();
// ... queries ...
$queries = collect(DB::getQueryLog())
    ->where('time', '>', 100) // 100ms-dən yavaş
    ->sortByDesc('time');

// Query count assertion — test-lərdə N+1 regression
public function test_no_n_plus_one_on_order_list(): void
{
    Order::factory(20)->create();

    DB::enableQueryLog();
    $this->getJson('/api/orders');
    $count = count(DB::getQueryLog());

    $this->assertLessThanOrEqual(3, $count,
        "N+1 detected: {$count} queries executed"
    );
}
```

### Kod Nümunəsi — Statistics və Slow Query Monitoring

```sql
-- pg_stat_statements ilə ən yavaş sorğuları tap
SELECT
    LEFT(query, 100)                                AS query_preview,
    calls,
    ROUND(mean_exec_time::numeric, 2)               AS avg_ms,
    ROUND(total_exec_time::numeric / 1000, 2)       AS total_sec,
    ROUND(stddev_exec_time::numeric, 2)             AS stddev_ms,
    rows / calls                                    AS avg_rows
FROM pg_stat_statements
WHERE calls > 100           -- Tez-tez çalışan
  AND mean_exec_time > 50   -- 50ms-dən yavaş
ORDER BY total_exec_time DESC
LIMIT 20;

-- Statistics yenilə — planner düzgün plan seçsin
ANALYZE orders;  -- Spesifik table
ANALYZE;         -- Bütün tablolar (avtomatik vacuum ilə gəlir)

-- Planner cardinality problemi yoxla
EXPLAIN SELECT * FROM orders WHERE status = 'pending';
-- "rows=500" gözlənilir amma real 50000 row — statistics köhnədir
-- ANALYZE sonra yenidən yoxla

-- Column statistics artır — selective column-lar üçün
ALTER TABLE orders ALTER COLUMN status SET STATISTICS 500;
ANALYZE orders;

-- Uzun çalışan sorğuları tap (real-time)
SELECT pid, now() - query_start AS duration, query, state
FROM pg_stat_activity
WHERE state != 'idle'
  AND query_start < NOW() - INTERVAL '10 seconds'
ORDER BY duration DESC;
```

### Attack/Failure Nümunəsi — Statistics Köhnədirsə

```
Ssenari: Black Friday — trafik 10x artır, sorğular yavaşladı

Tarix:
1. Tabloda 100K order var, status dağılımı: pending=5%, delivered=90%, cancelled=5%
2. Planner öyrənib: "status='pending' gətirsə ~5000 row gəlir" → Index Scan seçir
3. Black Friday: 1M order gəldi, hamısı pending! pending=90% oldu
4. Statistics köhnədir — planner hələ "5K row gəlir" düşünür → Index Scan seçir
5. Amma real: 900K row gəlir! Index Scan 900K random I/O → Seq Scan-dan yavaş!
6. Serverlər yükə tab gətirmir

Həll:
ANALYZE orders; -- Statistics yenilə
-- Planner indi 900K row görür → Seq Scan seçir (düzgündür)
-- Sorğular normallaşır

Dərs: Auto-vacuum/auto-analyze adətən kömək edir, amma
      böyük bulk insert/update sonrası manual ANALYZE etmək lazımdır.
      pg_stat_user_tables-dən n_live_tup vs last_analyze görün.
```

## Praktik Tapşırıqlar

- `pg_stat_statements` extension-ı aktiv edin, ən yavaş 10 sorğunu tapın
- Bir yavaş sorğu üçün `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` çalışdırın — `explain.depesz.com`-a yapışdırın, hər mərhələni oxuyun
- Correlated subquery olan bir sorğu yazın, JOIN-a çevirin, `EXPLAIN ANALYZE` ilə benchmark edin
- `WHERE YEAR(created_at) = 2024` kimi function-wrapped şərt tapın, düzəldin, fərqi ölçün
- Covering index yaradın, `Index Only Scan` əldə edin, `Buffers: shared read` sayının azaldığını görün
- `pg_stat_user_tables`-dən `seq_scan` sayı yüksək tablolar — bunlar index kandidatıdır
- Laravel-də query count assertion testi yazın, CI-da çalışdırın

## Əlaqəli Mövzular
- `04-index-types.md` — Hansı index növü seçmək
- `08-n-plus-1-problem.md` — Application tərəfindən sorğu optimizasiyası
- `06-transaction-isolation.md` — Long-running sorğularda isolation
- `12-mvcc.md` — Query plan ilə MVCC qarşılıqlı təsiri
- `16-database-migration-strategies.md` — Schema change ilə query impact
