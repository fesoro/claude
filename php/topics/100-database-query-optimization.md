# Database Query Optimization

## Mündəricat
1. [EXPLAIN ANALYZE oxumaq](#explain-analyze-oxumaq)
2. [Query Planner Konseptləri](#query-planner-konseptləri)
3. [Covering Index](#covering-index)
4. [Partial Index](#partial-index)
5. [Ümumi Yavaş Query Pattern-ləri](#ümumi-yavaş-query-patternləri)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## EXPLAIN ANALYZE oxumaq

```
PostgreSQL EXPLAIN ANALYZE çıxışı:

EXPLAIN ANALYZE
SELECT * FROM orders
WHERE customer_id = 42 AND status = 'pending';

Nəticə:
Index Scan using idx_orders_customer on orders
  (cost=0.43..8.45 rows=2 width=52)
  (actual time=0.028..0.031 rows=2 loops=1)
  Index Cond: (customer_id = 42)
  Filter: (status = 'pending')
  Rows Removed by Filter: 15
Planning Time: 0.5 ms
Execution Time: 0.1 ms

Oxuma qaydası:
  cost=0.43..8.45  → başlama..bitmə xərci (planner estimate)
  rows=2           → planner neçə sətir gözləyir
  actual time=...  → real vaxt (ms)
  rows=2           → real neçə sətir qaytardı
  loops=1          → neçə dəfə icra edildi (nested loop-larda artır)

Qırmızı bayraqlar:
  Seq Scan           → table scan, index yoxdur/istifadə edilmir
  Rows Removed: çox  → filter index-dən sonra — composite index lazım
  Hash Join on large  → memory hash build — work_mem artırıla bilər
  Nested Loop (çox)  → N*M əməliyyat — JOIN optimizasiya lazımdır
```

---

## Query Planner Konseptləri

```
Seq Scan (Sequential Scan):
  Bütün table-ı oxuyur.
  Kiçik table-lar üçün OK.
  Böyük table-da çox yavaş.

Index Scan:
  Index-dən əsas key oxuyur, sonra heap-ə gedir (row üçün).
  Random I/O — çox row üçün yavaşlaya bilər.

Index Only Scan:
  Yalnız index-dən oxuyur, heap-ə getmir!
  Covering index lazımdır.
  Ən sürətli oxuma üsulu.

Bitmap Heap Scan:
  Çox row qaytaracaqsa index scan-dən sürətli.
  Əvvəl bitmap (hansı page-lər lazımdır), sonra batch oxu.

Hash Join:
  İki böyük table JOIN → hash table qur → probe.
  Memory-intensive.

Nested Loop Join:
  Kiçik table → hər row üçün index scan → böyük table.
  Kiçik row sayında sürətli.

Merge Join:
  İki sorted input JOIN.
  Sorted index varsa effektivdir.

Statistics (pg_statistics):
  Planner table statistikasına baxır.
  ANALYZE → statistikaları yenilə.
  Köhnə statistika → pis plan seçimi!
  VACUUM ANALYZE tövsiyə edilir.
```

---

## Covering Index

```
Covering Index — query-nin lazım olan bütün sütunları index-dədir.
Heap-ə getmək lazım deyil → Index Only Scan.

Query:
  SELECT email, status FROM users
  WHERE created_at > '2024-01-01';

Normal index:
  CREATE INDEX idx_users_created ON users (created_at);
  → Index Scan: created_at → heap get (email, status üçün)

Covering index:
  CREATE INDEX idx_users_created_covering
    ON users (created_at)
    INCLUDE (email, status);
  → Index Only Scan: heap yoxdur!

INCLUDE vs Composite:
  (created_at, email, status) → 3 sütun ilə sort/filter mümkün
  (created_at) INCLUDE (email, status) → yalnız created_at filter üçün
    email, status filterlə istifadə edilmir, yalnız INCLUDE (data daşıma)

Nə vaxt covering index:
  ✓ Çox çağrılan, yavaş query
  ✓ SELECT sütunları məlumdur
  ✓ Index size artmasını qəbul edirsiniz (disk xərci)
```

---

## Partial Index

```
Partial Index — yalnız bəzi row-ları index-ə al.

Bütün orders-dən yalnız pending-lər işlənir:
  SELECT * FROM orders WHERE status = 'pending' AND id > :last_id;

Full index:
  CREATE INDEX idx_orders_status ON orders (status);
  → Milyonlarla completed order da index-dədir → böyük, yavaş

Partial index:
  CREATE INDEX idx_orders_pending
    ON orders (id)
    WHERE status = 'pending';
  → Yalnız pending order-lar! Çox kiçik, sürətli.

Başqa nümunə:
  Yalnız aktiv user-lar üçün:
  CREATE INDEX idx_users_active_email
    ON users (email)
    WHERE deleted_at IS NULL;

Partial index faydaları:
  ✓ Daha kiçik index → RAM-da daha çox sığır
  ✓ Write overhead azalır (yalnız şərtə uyğun row-lar üçün yenilənir)
  ✓ Daha sürətli scan

Şərt: WHERE şərti query-dəki filter ilə uyğun olmalıdır.
  Planner partial index-i yalnız uyğun query-lər üçün seçir.
```

---

## Ümumi Yavaş Query Pattern-ləri

```
1. SELECT * — lazımsız sütunlar
   ❌ SELECT * FROM users WHERE id=1;
   ✅ SELECT id, name, email FROM users WHERE id=1;

2. LIKE '%prefix%' — index istifadə etmir
   ❌ WHERE name LIKE '%john%'
   ✅ Full-text search (tsvector) və ya Elasticsearch

3. Function on indexed column
   ❌ WHERE YEAR(created_at) = 2024
      (index istifadə edilmir!)
   ✅ WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31'

4. OR with different columns
   ❌ WHERE status = 'pending' OR customer_id = 42
      (iki ayrı index birlikdə effektiv istifadə edilmir)
   ✅ UNION ALL (hər biri ayrıca index istifadə edir)

5. Implicit type conversion
   ❌ WHERE user_id = '42'  (user_id integer, '42' string)
      → Type cast → index scan yox, seq scan
   ✅ WHERE user_id = 42

6. Large OFFSET
   ❌ SELECT * FROM posts ORDER BY id LIMIT 20 OFFSET 10000
      → 10000 sətir atla (hamısı oxunur!)
   ✅ Cursor pagination:
      WHERE id > :last_id ORDER BY id LIMIT 20

7. Missing index on JOIN column
   ❌ SELECT * FROM orders JOIN customers ON orders.customer_id = customers.id
      (customers.id indexed, orders.customer_id indexed?)
   ✅ Index hər iki tərəfdə
```

---

## PHP İmplementasiyası

```php
<?php
// PDO ilə EXPLAIN ANALYZE
class QueryProfiler
{
    public function analyze(PDO $pdo, string $sql, array $params = []): array
    {
        $explainSql = 'EXPLAIN (ANALYZE, FORMAT JSON) ' . $sql;
        $stmt = $pdo->prepare($explainSql);
        $stmt->execute($params);
        $plan = $stmt->fetchColumn();

        $planData = json_decode($plan, true)[0];

        return [
            'planning_time_ms'   => $planData['Planning Time'],
            'execution_time_ms'  => $planData['Execution Time'],
            'total_cost'         => $planData['Plan']['Total Cost'],
            'rows'               => $planData['Plan']['Actual Rows'],
            'node_type'          => $planData['Plan']['Node Type'],
            'plan'               => $planData,
        ];
    }

    public function isUsingSeqScan(array $plan): bool
    {
        return $this->findNodeType($plan['plan'], 'Seq Scan');
    }

    private function findNodeType(array $node, string $type): bool
    {
        if ($node['Node Type'] === $type) return true;

        foreach ($node['Plans'] ?? [] as $child) {
            if ($this->findNodeType($child, $type)) return true;
        }

        return false;
    }
}
```

```php
<?php
// Slow query logger — development/staging üçün
class SlowQueryLogger
{
    private const THRESHOLD_MS = 100;

    public function wrap(PDO $pdo): PDO
    {
        // PDO proxy wrapper
        return new ProfilingPDO($pdo, function(string $sql, float $ms) {
            if ($ms > self::THRESHOLD_MS) {
                $this->logger->warning('Slow query detected', [
                    'sql'     => $sql,
                    'time_ms' => $ms,
                    'trace'   => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
                ]);
            }
        });
    }
}

// Covering index Laravel migration nümunəsi
// Schema::table('orders', function(Blueprint $table) {
//     $table->index(
//         ['customer_id', 'status', 'created_at'],
//         'idx_orders_customer_status_date'
//     );
// });
//
// Bu query-ni optimize edir:
// SELECT id, total FROM orders
// WHERE customer_id = ? AND status = ?
// ORDER BY created_at DESC LIMIT 10;
```

---

## İntervyu Sualları

- EXPLAIN ANALYZE-da `Seq Scan` gördünüz — nə edərdiniz?
- `actual rows` vs `rows` fərqi böyükdürsə nə deməkdir?
- Covering index nədir? Adi composite index-dən fərqi?
- `LIKE '%value%'` index-i niyə istifadə etmir?
- Partial index-in üstünlüyü nədir? Nümunə?
- N+1 problemi EXPLAIN-də necə görünür?
- `VACUUM ANALYZE` nə üçün lazımdır?
