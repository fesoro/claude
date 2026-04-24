# Query Optimization & EXPLAIN

> **Seviyye:** Intermediate ⭐⭐

## EXPLAIN - Query Analizi

EXPLAIN her query-nin nece icra olundugunu gosterir. Optimizasiya ucun **en vacib** aletdir.

```sql
EXPLAIN SELECT * FROM orders WHERE status = 'pending' AND created_at > '2024-01-01';
```

### EXPLAIN Output (MySQL)

| id | select_type | table | type | possible_keys | key | rows | Extra |
|----|------------|-------|------|--------------|-----|------|-------|
| 1 | SIMPLE | orders | range | idx_status_date | idx_status_date | 1250 | Using index condition |

### Muhum sutunlar:

**type** (en muhum!) - En yaxsidan en pise:

| Type | Menasi | Performance |
|------|--------|-------------|
| `system` | 1 row-lu table | En yaxsi |
| `const` | PRIMARY KEY ve ya UNIQUE ile 1 row | Eladir |
| `eq_ref` | JOIN-da her row ucun 1 match (PK/UNIQUE) | Eladir |
| `ref` | Index ile bir nece row | Yaxsi |
| `range` | Index range scan (BETWEEN, >, <) | Yaxsi |
| `index` | Full index scan (butun index oxunur) | Orta |
| `ALL` | Full table scan | **EN PIS!** |

```sql
-- const (PK ile)
EXPLAIN SELECT * FROM users WHERE id = 1;

-- ref (adi index ile)
EXPLAIN SELECT * FROM orders WHERE user_id = 5;

-- range
EXPLAIN SELECT * FROM orders WHERE created_at BETWEEN '2024-01-01' AND '2024-06-01';

-- ALL (index yoxdur - FIX ET!)
EXPLAIN SELECT * FROM orders WHERE YEAR(created_at) = 2024;
```

**Extra** sutunundaki muhum deyerler:

| Deyer | Menasi |
|-------|--------|
| `Using index` | Covering index - table-a baxmir, eladir |
| `Using where` | Filtrleme server terefde bas verir |
| `Using temporary` | Temporary table yaradilir - yavas ola biler |
| `Using filesort` | Sortlama ucun elave emeliyyat - yavas ola biler |
| `Using index condition` | Index condition pushdown (ICP) |

### EXPLAIN ANALYZE (MySQL 8.0+ / PostgreSQL)

Actual icra zamani ve row sayini gosterir:

```sql
-- MySQL 8.0+
EXPLAIN ANALYZE SELECT * FROM orders WHERE status = 'pending';

-- Neticede:
-- -> Filter: (orders.status = 'pending')  (cost=2.50 rows=5) 
--    (actual time=0.030..0.150 rows=5 loops=1)
```

```sql
-- PostgreSQL
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) 
SELECT * FROM orders WHERE status = 'pending';

-- Output:
-- Seq Scan on orders (cost=0.00..25.88 rows=6 width=40) (actual time=0.009..0.012 rows=5 loops=1)
--   Filter: (status = 'pending')
--   Rows Removed by Filter: 995
--   Buffers: shared hit=5
-- Planning Time: 0.100 ms
-- Execution Time: 0.030 ms
```

---

## Yavas Query-lerin Tapilmasi

### Slow Query Log (MySQL)

```ini
# my.cnf
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1    # 1 saniyeden uzun suren query-ler
log_queries_not_using_indexes = 1  # Index istifade etmeyen query-ler
```

### PostgreSQL

```ini
# postgresql.conf
log_min_duration_statement = 1000  # 1000ms-den uzun query-ler
```

### Laravel Query Log

```php
// Butun query-leri log et
DB::listen(function ($query) {
    if ($query->time > 1000) { // 1 saniyeden uzun
        Log::warning('Slow query', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
        ]);
    }
});

// Debug ucun
DB::enableQueryLog();
// ... query-ler ...
dd(DB::getQueryLog());
```

---

## Optimization Texnikalari

### 1. SELECT * istifade etme

```sql
-- YANLIS
SELECT * FROM orders WHERE status = 'pending';

-- DOGRU (lazim olan sutunlari sec)
SELECT id, user_id, total_amount, created_at 
FROM orders WHERE status = 'pending';

-- Sebebler:
-- 1. Daha az data transfer olunur
-- 2. Covering index istifade oluna biler
-- 3. Schema deyisse, application qirilmaz
```

### 2. Subquery yerine JOIN istifade et

```sql
-- YAVAS (correlated subquery - her row ucun icra olunur)
SELECT *, (
    SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id
) AS order_count 
FROM users;

-- SURETLI (JOIN - bir defe icra olunur)
SELECT u.*, COUNT(o.id) AS order_count
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
GROUP BY u.id;
```

### 3. LIMIT ile pagination

```sql
-- Sade OFFSET (boyuk offset-lerde YAVAS)
SELECT * FROM orders ORDER BY id DESC LIMIT 20 OFFSET 100000;
-- 100000 row skip etmeli, sonra 20 row qaytarmali

-- Cursor-based pagination (SURETLI)
SELECT * FROM orders WHERE id < 50000 ORDER BY id DESC LIMIT 20;
-- Index istifade edir, offset yoxdur
```

**Laravel-de:**

```php
// YAVAS (boyuk table-larda)
Order::orderBy('id', 'desc')->paginate(20); // page=5000 olsa yavas

// SURETLI (cursor pagination)
Order::orderBy('id', 'desc')->cursorPaginate(20);
```

### 4. Bulk Operations

```sql
-- YAVAS (10000 ayri INSERT)
INSERT INTO logs (message) VALUES ('log1');
INSERT INTO logs (message) VALUES ('log2');
-- ... x10000

-- SURETLI (batch INSERT)
INSERT INTO logs (message) VALUES ('log1'), ('log2'), ('log3'), ... ;
```

```php
// Laravel
// YAVAS
foreach ($items as $item) {
    Order::create($item);
}

// SURETLI
Order::insert($items); // Tek query

// Upsert (INSERT ve ya UPDATE)
Order::upsert($items, ['id'], ['status', 'total_amount']);
```

### 5. WHERE-de Function istifade etme

```sql
-- YANLIS (index istifade olunmur)
SELECT * FROM users WHERE LOWER(email) = 'john@mail.com';
SELECT * FROM orders WHERE DATE(created_at) = '2024-01-01';
SELECT * FROM orders WHERE amount + tax > 100;

-- DOGRU
SELECT * FROM users WHERE email = 'john@mail.com'; -- case-insensitive collation istifade et
SELECT * FROM orders WHERE created_at >= '2024-01-01' AND created_at < '2024-01-02';
SELECT * FROM orders WHERE amount > 100 - tax; -- sabiti sagda saxla (eger mumkunse)

-- PostgreSQL: Expression index
CREATE INDEX idx_lower_email ON users (LOWER(email));
-- Indi LOWER(email) = ? query-si index istifade ede biler
```

### 6. OR yerine UNION ve ya IN

```sql
-- YAVAS (OR bezen index istifade etmir)
SELECT * FROM orders WHERE status = 'pending' OR status = 'processing';

-- SURETLI
SELECT * FROM orders WHERE status IN ('pending', 'processing');

-- Ve ya UNION (ferqli index-ler istifade oluna biler)
SELECT * FROM orders WHERE status = 'pending'
UNION ALL
SELECT * FROM orders WHERE payment_method = 'crypto';
```

### 7. EXISTS vs IN

```sql
-- Boyuk subquery neticesi olduqda EXISTS daha yaxsidir
-- IN: butun subquery-ni evvelce icra edir, neticeni memory-de saxlayir
SELECT * FROM users WHERE id IN (SELECT user_id FROM orders); -- orders boyukdurse yavas

-- EXISTS: her user ucun tapan kimi dayanir (short-circuit)
SELECT * FROM users u WHERE EXISTS (
    SELECT 1 FROM orders o WHERE o.user_id = u.id
);
```

### 8. COUNT Optimization

```sql
-- YAVAS (butun row-lari sayir)
SELECT COUNT(*) FROM orders WHERE status = 'pending';

-- Approximate count (boyuk table-lar ucun, deqiq olmaya biler)
-- MySQL
SELECT TABLE_ROWS FROM information_schema.TABLES 
WHERE TABLE_NAME = 'orders';

-- PostgreSQL
SELECT reltuples FROM pg_class WHERE relname = 'orders';
```

---

## Query Profiling

```sql
-- MySQL
SET profiling = 1;
SELECT * FROM orders WHERE status = 'pending';
SHOW PROFILE FOR QUERY 1;

-- Neticede her merhelenin zamani gorsenur:
-- starting              0.000050
-- checking permissions   0.000005
-- Opening tables        0.000015
-- init                  0.000020
-- System lock           0.000010
-- optimizing            0.000008
-- executing             0.125000  <-- en uzun hisse
-- Sending data          0.003000
-- end                   0.000005
```

---

## Interview suallari

**Q: EXPLAIN-de type=ALL gorsen ne edersin?**
A: Full table scan demekdir. 1) WHERE sutununa index elave et. 2) WHERE-de function istifade olunursa, silib range query-ye cevir. 3) EXPLAIN yeniden yoxla.

**Q: Niye eyni query bezen suretli, bezen yavas olur?**
A: 1) Query cache (MySQL 5.7) - eyni query cache-den qaytarila biler. 2) Buffer pool - data memory-dedir ve ya diskden oxunmalidir. 3) Table statistics kohnelib - optimizer yanlis plan secir (`ANALYZE TABLE` ile yenile). 4) Lock contention - diger transaction-lar lock saxlayir.

**Q: Pagination ucun OFFSET/LIMIT niye yavasdir?**
A: OFFSET=100000 olduqda, database 100000 row-u oxuyub atlayir. Cursor-based pagination-da ise index-le birbaşa lazimi noqteye gedir: `WHERE id < ?` seklinde.
