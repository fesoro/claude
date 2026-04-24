# Query Hints & Planner Control

> **Seviyye:** Advanced ⭐⭐⭐

## Niyə lazımdır?

Cost-based optimizer (CBO) adətən **doğru query plan-ı seçir**, amma **hər zaman yox**. Səbəblər:

1. **Stale statistics** — `ANALYZE` edilməyib, cardinality yanlış hesablanır
2. **Correlated columns** — CBO sütunları müstəqil hesab edir, real dünyada əlaqəlidir (`city = 'Baku' AND country = 'Azerbaijan'`)
3. **Data skew** — bir dəyər çox yayılıb, digərləri nadirdir (histogram çatışmır)
4. **Complex queries** — join order N! kombinasiyası, search space çox böyük
5. **Parameter sniffing** — ilk prepared statement call-u plan-ı cache edir, sonrakı parametrlər üçün pis

Senior müəllimin edə biləcəyi ən pis hərəkət — **hər query-yə hint yapışdırmaq**. Hint bir nagil əlacdır, real problem `ANALYZE` + schema dizaynı + düzgün index-dir. Amma bəzən **qaçılmazdır**.

---

## MySQL Query Hints

### Index hints

```sql
-- USE INDEX — optimizer-ə yalnız bu index-ləri təklif et (məcbur etmir)
SELECT * FROM orders 
USE INDEX (idx_status_date) 
WHERE status = 'pending' AND created_at > '2024-01-01';

-- FORCE INDEX — məcbur et (table scan-dan bahalı olsa belə)
SELECT * FROM orders 
FORCE INDEX (idx_status_date)
WHERE status = 'pending';

-- IGNORE INDEX — bu index-i istifadə etmə
SELECT * FROM orders 
IGNORE INDEX (idx_created_at)
WHERE created_at > '2024-01-01';

-- Spesifik əməliyyat üçün:
-- USE INDEX FOR JOIN (...)
-- USE INDEX FOR ORDER BY (...)
-- USE INDEX FOR GROUP BY (...)
SELECT /* ... */ FROM orders 
USE INDEX FOR JOIN (idx_user) 
USE INDEX FOR ORDER BY (idx_created_at)
WHERE ...;
```

### Optimizer hints (MySQL 5.7.7+) — komment sintaksisi

```sql
SELECT /*+ MAX_EXECUTION_TIME(1000) */ * FROM orders;
-- Query 1 saniyə keçsə terminate et

SELECT /*+ BKA(t1) NO_BKA(t2) */ * FROM t1 JOIN t2 ON ...;
-- Batched Key Access optimizer strateji

SELECT /*+ SET_VAR(optimizer_switch='batched_key_access=on') */ * FROM ...;
-- Session-level switch

SELECT /*+ NO_RANGE_OPTIMIZATION(idx_a) */ * FROM t WHERE a BETWEEN 1 AND 100;

-- JOIN order
SELECT /*+ JOIN_ORDER(t1, t2, t3) */ * FROM t1, t2, t3 WHERE ...;

-- Subquery strategy
SELECT /*+ SEMIJOIN(MATERIALIZATION) */ * FROM t WHERE id IN (SELECT ...);

-- Hash join (MySQL 8.0.18+)
SELECT /*+ HASH_JOIN(t1 t2) */ * FROM t1 JOIN t2 ON ...;
```

### `STRAIGHT_JOIN` — join order məcbur et

```sql
-- MySQL ardıcıl olaraq `t1` sonra `t2` sonra `t3` ilə JOIN etsin
SELECT STRAIGHT_JOIN t1.*, t2.*, t3.*
FROM t1
JOIN t2 ON t1.id = t2.t1_id
JOIN t3 ON t2.id = t3.t2_id
WHERE ...;
```

---

## PostgreSQL — nativ hint YOXDUR

PostgreSQL dizayn fəlsəfəsi ilə **query hint dəstəkləmir**. Səbəb: "optimizer daimi yaxşılaşdırılır, hint-lər köhnəlir və regression yaradır".

Amma **sərt dolayı nəzarət mexanizmləri** var.

### 1. `SET` — planner parametrləri

```sql
-- Session-level parametrlər
SET enable_seqscan = OFF;        -- Sequential scan-ı söndür (FORCE index kimi)
SET enable_nestloop = OFF;       -- Nested loop join söndür
SET enable_hashjoin = OFF;
SET enable_mergejoin = OFF;
SET enable_bitmapscan = OFF;
SET enable_indexscan = OFF;
SET enable_indexonlyscan = OFF;

-- Query-specific
BEGIN;
SET LOCAL enable_seqscan = OFF;
EXPLAIN SELECT * FROM orders WHERE ...;
COMMIT;

-- Konkret query üçün cost parametrləri
SET random_page_cost = 1.1;       -- SSD üçün default 4-dən aşağı
SET cpu_tuple_cost = 0.01;
SET work_mem = '256MB';            -- Sort/hash-ın memory-də qalması
```

### 2. `pg_hint_plan` extension

**Ən yaxşı vasitə**: Oracle-vari komment hints PG-ə əlavə edir.

```sql
CREATE EXTENSION pg_hint_plan;

-- Scan method
/*+ SeqScan(orders) */
SELECT * FROM orders WHERE status = 'pending';

/*+ IndexScan(orders idx_status) */
SELECT * FROM orders WHERE status = 'pending';

/*+ IndexOnlyScan(orders idx_status_date) */
SELECT id FROM orders WHERE status = 'pending';

-- Join method
/*+ HashJoin(a b) */
SELECT * FROM a JOIN b ON a.id = b.a_id;

/*+ NestLoop(a b) */
/*+ MergeJoin(a b) */

-- Join order (Leading hint)
/*+ Leading(a b c) */
SELECT * FROM a, b, c WHERE ...;

/*+ Leading(((a b) c)) */  -- a-b-ni öncə join et, sonra c
SELECT * FROM a, b, c;

-- Row count hint (cardinality düzəlt)
/*+ Rows(a #1000) */        -- Fiksə edir
/*+ Rows(a *10) */          -- Ümid olunandan 10x çox
/*+ Rows(a +500) */          -- +500
```

### 3. CTE ilə "optimization fence" (köhnə davranış)

```sql
-- PG 12-dən əvvəl: CTE-lər "optimization fence" idi
-- Planner onları ayrı icra edirdi
WITH recent AS (
    SELECT * FROM orders WHERE created_at > NOW() - INTERVAL '7 days'
)
SELECT * FROM recent WHERE status = 'pending';
-- Əvvəllər: orders full scan, sonra filter

-- PG 12+ default: inline (planner CTE-ni main query ilə birləşdirir)
-- Köhnə davranış lazımdırsa:
WITH recent AS MATERIALIZED (
    SELECT * FROM orders WHERE created_at > NOW() - INTERVAL '7 days'
)
SELECT * FROM recent WHERE status = 'pending';
```

### 4. Subquery ilə plan variasiyası

```sql
-- OFFSET 0 köhnə optimization fence trick-i
SELECT * FROM (
    SELECT * FROM orders WHERE status = 'pending' OFFSET 0
) sub WHERE created_at > NOW();
-- PG subquery-ni ayrı optimize edir
```

---

## Statistics — planner-in məlumat mənbəyi

Hint-dən **daha əvvəl** statistikaları yoxla.

### PostgreSQL

```sql
-- Table statistics yenilə
ANALYZE orders;
VACUUM ANALYZE orders;           -- Vacuum + analyze

-- Sütun-level histogram-ları gör
SELECT schemaname, tablename, attname, 
       n_distinct, most_common_vals, most_common_freqs, correlation
FROM pg_stats 
WHERE tablename = 'orders';

-- Row count estimate
SELECT reltuples::bigint AS estimate FROM pg_class WHERE relname = 'orders';

-- Statistics target-i artır (daha dəqiq histogram)
ALTER TABLE orders ALTER COLUMN status SET STATISTICS 1000; -- default 100
ANALYZE orders;

-- Multi-column statistics (PG 10+)
CREATE STATISTICS orders_status_date ON status, created_at FROM orders;
ANALYZE orders;
-- Planner korelyasiyanı öyrənir: 'pending' status-lu order-lərin çoxu son 30 gündədir.
```

### MySQL

```sql
-- Table statistics yenilə
ANALYZE TABLE orders;

-- Statistics görüntü
SELECT * FROM mysql.innodb_table_stats WHERE table_name = 'orders';
SELECT * FROM mysql.innodb_index_stats WHERE table_name = 'orders';

-- Persistent statistics
ALTER TABLE orders STATS_PERSISTENT=1, STATS_AUTO_RECALC=1, STATS_SAMPLE_PAGES=100;

-- Histogram (MySQL 8.0+)
ANALYZE TABLE orders UPDATE HISTOGRAM ON status, priority WITH 100 BUCKETS;

SELECT * FROM information_schema.column_statistics 
WHERE table_name = 'orders';
```

---

## Parameter Sniffing problemi

Prepared statement-lər ilk parametrlə plan cache edir. Sonrakı fərqli parametrlər üçün pis plan ola bilər.

```sql
-- Problem:
PREPARE q AS SELECT * FROM orders WHERE status = $1;

-- İlk çağırış 'pending' ilə (1% row) → index scan seçir
EXECUTE q('pending');

-- İkinci çağırış 'delivered' ilə (80% row) → hələ də index scan! Pisdir.
EXECUTE q('delivered');
```

### Həllər

**PostgreSQL:**
```sql
-- PG 12+: plan_cache_mode
SET plan_cache_mode = 'force_custom_plan';   -- Hər dəfə yeni plan
SET plan_cache_mode = 'force_generic_plan';  -- Daimi plan
SET plan_cache_mode = 'auto';                -- Default (5 call-dan sonra generic)
```

**MySQL:**
```sql
-- Sorğunu hər dəfə re-prepare et:
DEALLOCATE PREPARE q;
PREPARE q AS SELECT * FROM orders WHERE status = ?;

-- Və ya parametr inline ilə:
SELECT * FROM orders WHERE status = 'pending'; -- cache-də ayrı plan
```

---

## Laravel-də Query Hints

```php
// Raw expression ilə
$orders = DB::table('orders')
    ->from(DB::raw('orders USE INDEX (idx_status_date)'))
    ->where('status', 'pending')
    ->get();

// Və ya fromRaw
$orders = DB::table('orders')
    ->fromRaw('orders FORCE INDEX (idx_status_date)')
    ->where('status', 'pending')
    ->get();

// Optimizer hints (MySQL komment)
$orders = DB::select("
    SELECT /*+ MAX_EXECUTION_TIME(5000) NO_BKA(o) */ *
    FROM orders o
    WHERE status = ?
", ['pending']);

// pg_hint_plan ilə (PostgreSQL)
$orders = DB::select("
    /*+ IndexScan(orders idx_orders_status) */
    SELECT * FROM orders WHERE status = ?
", ['pending']);
```

---

## Timeout-lar (critical production control)

### PostgreSQL

```sql
-- Statement timeout (query max müddəti)
SET statement_timeout = '5s';
SELECT * FROM orders WHERE ...; -- 5s keçsə cancel olunur

-- Session-level
ALTER DATABASE myapp SET statement_timeout = '30s';

-- User-level
ALTER ROLE api_user SET statement_timeout = '5s';
ALTER ROLE reporting_user SET statement_timeout = '5min';

-- Transaction idle timeout
SET idle_in_transaction_session_timeout = '30s';

-- Lock timeout
SET lock_timeout = '2s';
```

### MySQL

```sql
-- Query timeout (MySQL 5.7.4+)
SET SESSION MAX_EXECUTION_TIME = 5000;  -- millisekundlar

-- Və ya query-specific
SELECT /*+ MAX_EXECUTION_TIME(5000) */ * FROM orders;

-- Lock wait timeout
SET SESSION innodb_lock_wait_timeout = 10;
```

### Laravel-də timeout

```php
// Query timeout-u PDO səviyyəsində
DB::statement("SET statement_timeout = '5s'");   // PostgreSQL
DB::statement("SET SESSION MAX_EXECUTION_TIME = 5000"); // MySQL

// Connection-level in config/database.php
'mysql' => [
    'options' => [
        PDO::ATTR_TIMEOUT => 10,
    ],
],
```

---

## Debugging recepti

```sql
-- 1. Actual plan-ı gör
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) 
SELECT * FROM orders WHERE status = 'pending';

-- 2. Estimated vs actual row-lar bax
-- Planner-in row estimate-i real-dan 10x fərqli olarsa, statistics pis.
-- "rows=1000 .. actual rows=50000" → ANALYZE et, STATISTICS artır.

-- 3. Index istifadə olunurmu?
-- "Seq Scan" görsən, ya index yoxdur, ya da index işarəsizdir (cost-da pis).

-- 4. Alternative plan-ı sına (geçici)
SET enable_seqscan = OFF;
EXPLAIN SELECT * FROM orders WHERE ...;
RESET enable_seqscan;
-- Index scan daha yaxşı olarsa → ANALYZE et və ya pg_hint_plan istifadə et.

-- 5. Planner parametrlərini yoxla
SHOW work_mem;               -- Hash/sort bufferi
SHOW random_page_cost;       -- SSD üçün 1.1, HDD üçün 4 (default)
SHOW effective_cache_size;   -- OS cache + shared_buffers təxmini
```

---

## Hint-dən qaçının, bunları əvvəlcə sınayın

| Problem | Hint-dən ÖNCƏ bunu sına |
|---------|-------------------------|
| Wrong plan | `ANALYZE table` |
| Skewed data | `ALTER COLUMN SET STATISTICS 1000` |
| Correlated columns | `CREATE STATISTICS` (PG 10+) |
| Missing index | `EXPLAIN` oxu, müvafiq index əlavə et |
| Parameter sniffing | `SET plan_cache_mode = 'force_custom_plan'` |
| Timeout-yeyən query | Query re-write, N+1 fix, pagination |
| Specific query, bütün həll yollarını yoxladım | **O zaman hint istifadə et** |

---

## Interview sualları

**Q: PostgreSQL-də niyə query hint yoxdur?**
A: Dizayn fəlsəfəsi — hint-lər köhnəlir, regression yaradır, real problemləri maskalayır. Əvəzinə PG `ANALYZE`, `CREATE STATISTICS`, planner GUC parametrləri (`enable_seqscan`, `random_page_cost`) və `pg_hint_plan` extension təklif edir. Production-da `pg_hint_plan` istifadə edirəm, çünki legacy query-ləri bloklayıcı fix olmadan idarə etmək mümkün olur.

**Q: MySQL-də `FORCE INDEX` nə vaxt təhlükəlidir?**
A: Hər zaman. Ən çox, table böyüdükdə, `FORCE INDEX` kiçik table-da yaxşı idi, amma indi 10M row-da seçilən index selektiv deyilsə, performance 100× pisləşir. Həmçinin `FORCE INDEX` gələcəkdə silinəcək index-ə bağlanırsa, query pozulur. Optimizer-ə etibar et, amma statistikaları təzələ.

**Q: `ANALYZE` nə vaxt lazımdır?**
A: 1) Bulk insert/delete-dən sonra (10%+ row dəyişib). 2) Statistika köhnədir və EXPLAIN row estimate-lər yanılır. 3) Yeni index yaratdıqdan sonra. 4) PG-də **autovacuum** adətən bunu avtomatik edir, amma statistics target az ola bilər — `ALTER COLUMN SET STATISTICS 1000` + `ANALYZE`.

**Q: "Slow query" problemi ilə qarşılaşırsan. Addım-addım nə edirsən?**
A: 1) `EXPLAIN ANALYZE` — plan-ı gör. 2) Estimated rows vs actual rows yoxla — 10× fərqli olarsa, `ANALYZE table`. 3) Index istifadə olunurmu? Yoxdursa uyğun index əlavə et. 4) `pg_stat_statements` / slow log-dan tez-tez təkrarlanan sorğu müəyyən et. 5) Query re-write (subquery → JOIN, `OR` → `UNION ALL`, `NOT IN` → `NOT EXISTS`). 6) Yalnız bütün bunlardan sonra hint düşün.

**Q: Parameter sniffing nə vaxt problem yaradır?**
A: Yüksək-variance verilmiş column-larda. Misal: `WHERE status = ?` — status "pending" 1% row, "delivered" 80% row. İlk call "pending"-lə gələrsə, index scan seçilir və cache-lənir, sonrakı "delivered" call-ları da index scan edir (seq scan sürətli olardı). Həll: PG `force_custom_plan`, MySQL prepared-ı periodically deallocate, və ya inline parametrlər.
