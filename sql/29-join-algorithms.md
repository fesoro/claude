# JOIN Types & Algorithms

## JOIN Tipleri

### INNER JOIN

Yalniz **her iki table-da** uygun row olan netice qaytarir.

```sql
-- User-lerin sifarisleri (sifarisi olmayan user-ler GORUNMEZ)
SELECT u.name, o.order_number, o.total
FROM users u
INNER JOIN orders o ON u.id = o.user_id;

-- users:  [1-Ali, 2-Veli, 3-Orkhan]
-- orders: [user_id=1, user_id=1, user_id=3]
-- Netice: Ali(2 row), Orkhan(1 row). Veli YOXDUR (sifarisi yox)
```

### LEFT JOIN (LEFT OUTER JOIN)

Sol table-in **butun** row-larini qaytarir. Sag table-da uygun yoxdursa NULL.

```sql
-- Butun user-ler + sifarisleri (sifarisi olmayan da gorunur)
SELECT u.name, o.order_number, o.total
FROM users u
LEFT JOIN orders o ON u.id = o.user_id;

-- Netice: Ali(2 row), Veli(NULL,NULL), Orkhan(1 row)

-- Sifarisi OLMAYAN user-leri tap
SELECT u.name
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
WHERE o.id IS NULL;
```

### RIGHT JOIN (RIGHT OUTER JOIN)

Sag table-in butun row-larini qaytarir. LEFT JOIN-in tersidi.

```sql
SELECT u.name, o.order_number
FROM users u
RIGHT JOIN orders o ON u.id = o.user_id;
-- Nadir istifade olunur - LEFT JOIN ile eyni neticeni elde etmek mumkundur
```

### FULL OUTER JOIN

Her iki table-in butun row-larini qaytarir.

```sql
-- PostgreSQL
SELECT u.name, o.order_number
FROM users u
FULL OUTER JOIN orders o ON u.id = o.user_id;

-- MySQL-de FULL OUTER JOIN yoxdur, UNION ile emulyasiya:
SELECT u.name, o.order_number FROM users u LEFT JOIN orders o ON u.id = o.user_id
UNION
SELECT u.name, o.order_number FROM users u RIGHT JOIN orders o ON u.id = o.user_id;
```

### CROSS JOIN (Cartesian Product)

Her row her row ile birlesdirirlir. N * M netice qaytarir.

```sql
-- 3 user * 4 product = 12 row
SELECT u.name, p.name AS product
FROM users u
CROSS JOIN products p;

-- Istifade: Butun kombinasiyalari yaratmaq (sizes x colors)
SELECT s.size, c.color
FROM sizes s
CROSS JOIN colors c;
```

### SELF JOIN

Table ozune join olunur.

```sql
-- Isci ve menecerini tap
SELECT e.name AS employee, m.name AS manager
FROM employees e
LEFT JOIN employees m ON e.manager_id = m.id;

-- Eyni seherde olan muxtelif user-ler
SELECT a.name, b.name, a.city
FROM users a
JOIN users b ON a.city = b.city AND a.id < b.id;
```

### NATURAL JOIN

Eyni adli column-lara gore avtomatik join edir. **Istifade etmeyin** - tehlikelidir!

```sql
-- Her iki table-da "id", "name" varsa, ikisine de join eder
-- Gozlenilmeyen netice vere biler
SELECT * FROM users NATURAL JOIN orders;  -- TEHLIKELI
```

## JOIN Alqoritmleri

Database engine JOIN-i icra ederken ferqli alqoritmler istifade edir. `EXPLAIN` ile hansi alqoritmin secildiyini gore bilersiz.

### 1. Nested Loop Join

En sadesi. Xarici table-in her row-u ucun daxili table-i tamamen scan edir.

```
Alqoritm:
FOR each row in outer_table:        -- N row
    FOR each row in inner_table:    -- M row
        IF join_condition matches:
            output combined row

Complexity: O(N × M)
```

```sql
-- EXPLAIN output-da "Nested loop" gorsenirse
EXPLAIN ANALYZE
SELECT u.name, o.total
FROM users u                    -- 1000 row (outer - kicik table)
INNER JOIN orders o             -- 100000 row (inner)
ON u.id = o.user_id;

-- Index varsa: O(N × log M) olur (daxili table-da index lookup)
-- Index yoxdursa: O(N × M) - cox yavasdir!
```

**Ne vaxt secilir?**
- Kicik table boyuk table ile join olunur
- Inner table-da index var
- Bir nece row qaytarilan query-ler

### 2. Hash Join

Kicik table-dan **hash table** qurur, boyuk table-i scan ederek hash-de axtarir.

```
Alqoritm:
-- Build phase:
Hash table yarat (kicik table-dan)
FOR each row in smaller_table:
    hash(join_key) → hash_table[bucket].add(row)

-- Probe phase:
FOR each row in larger_table:
    hash(join_key) → hash_table[bucket]-de axtar
    IF tapildisa:
        output combined row

Complexity: O(N + M)  -- Suretlidir!
```

```sql
-- PostgreSQL-de Hash Join (EXPLAIN output)
EXPLAIN ANALYZE
SELECT u.name, o.total
FROM users u
INNER JOIN orders o ON u.id = o.user_id;

-- Hash Join  (cost=... rows=...)
--   Hash Cond: (o.user_id = u.id)
--   -> Seq Scan on orders o
--   -> Hash
--       -> Seq Scan on users u
```

**Ne vaxt secilir?**
- Boyuk table-lar arasi join
- Join key-de index yoxdur
- Equality join (`=`) - range join (`>`, `<`) ucun islemir!
- Kicik table RAM-a sigir (hash table ucun)

**MySQL qeydi:** MySQL 8.0.18+ hash join destekleyir. Evvelki versiyalarda yalniz Nested Loop var idi.

### 3. Sort-Merge Join (Merge Join)

Her iki table-i join key-e gore **siralar**, sonra eyni zamanda merge edir.

```
Alqoritm:
-- Sort phase:
Sort table_A by join_key
Sort table_B by join_key

-- Merge phase:
pointer_a = first row of A
pointer_b = first row of B
WHILE both have rows:
    IF A.key == B.key: output, advance both
    ELIF A.key < B.key: advance A
    ELSE: advance B

Complexity: O(N log N + M log M)  -- Sort ucun
```

```sql
-- PostgreSQL EXPLAIN output
EXPLAIN ANALYZE
SELECT u.name, o.total
FROM users u
INNER JOIN orders o ON u.id = o.user_id
ORDER BY u.id;

-- Merge Join  (cost=...)
--   Merge Cond: (u.id = o.user_id)
--   -> Index Scan on users_pkey u
--   -> Sort
--       -> Seq Scan on orders o
```

**Ne vaxt secilir?**
- Her iki table artiq siralidir (index var)
- Boyuk table-lar, ORDER BY da lazimdir
- Range join-ler ucun (`BETWEEN`, `>`, `<`)
- Her iki table boyukdur (hash table RAM-a sigmayanda)

### Alqoritm Muqayise

| Xususiyyet | Nested Loop | Hash Join | Sort-Merge |
|------------|-------------|-----------|------------|
| **Complexity** | O(N × M) ve ya O(N × log M) | O(N + M) | O(N log N + M log M) |
| **Kicik data** | En yaxsi | Overhead var | Overhead var |
| **Boyuk data** | Yavas | Suretli | Suretli |
| **Index lazim?** | Inner table-da beli | Xeyr | Sort lazim (ve ya index) |
| **RAM lazim?** | Az | Hash table ucun | Sort ucun |
| **Join tipi** | Butun (=, >, <, LIKE) | Yalniz equality (=) | Equality + Range |
| **MySQL** | Default | 8.0.18+ | Yoxdur |
| **PostgreSQL** | Beli | Beli | Beli |

## JOIN Optimization

### 1. Index ile JOIN suretlendirmek

```sql
-- YANLIS: Index olmadan join
SELECT o.*, u.name
FROM orders o
JOIN users u ON u.id = o.user_id;
-- users.id artiq PK (index var)
-- amma orders.user_id-de index lazimdir!

-- Index elave et
CREATE INDEX idx_orders_user_id ON orders (user_id);

-- Covering index (extra lookup olmadan)
CREATE INDEX idx_orders_user_status ON orders (user_id, status, total);
```

### 2. JOIN Sirasi

```sql
-- Optimizer adeten en yaxsi sirani ozune secir, amma bazen komek lazimdir

-- MySQL: STRAIGHT_JOIN ile sirani mecbur et
SELECT STRAIGHT_JOIN u.name, o.total
FROM users u                    -- Bu outer table olsun
INNER JOIN orders o ON u.id = o.user_id;

-- PostgreSQL: join_collapse_limit
SET join_collapse_limit = 1;  -- Optimizer reorder etmesin
```

### 3. Lazim Olmayan JOIN-i Avoid Et

```sql
-- YANLIS: Lazim olmayan table join edilir
SELECT o.order_number, o.total
FROM orders o
JOIN users u ON u.id = o.user_id     -- User data istifade olunmur!
WHERE o.status = 'pending';

-- DOGRU: JOIN lazim deyil
SELECT order_number, total
FROM orders
WHERE status = 'pending';
```

### 4. Subquery vs JOIN

```sql
-- Sual: Sifaris vermis user-lerin adlari

-- JOIN (adeten daha suretli)
SELECT DISTINCT u.name
FROM users u
INNER JOIN orders o ON u.id = o.user_id;

-- Subquery (bezen daha oxunaqli)
SELECT name FROM users
WHERE id IN (SELECT DISTINCT user_id FROM orders);

-- EXISTS (en suretli - boyuk table-lar ucun)
SELECT name FROM users u
WHERE EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id);
```

### 5. EXPLAIN ile JOIN Analizi

```sql
EXPLAIN ANALYZE
SELECT u.name, COUNT(o.id) AS order_count, SUM(o.total) AS total_spent
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
GROUP BY u.id, u.name
HAVING SUM(o.total) > 1000
ORDER BY total_spent DESC
LIMIT 10;

-- Baxilacaq seyler:
-- 1. Join type (Nested Loop, Hash, Merge)
-- 2. Seq Scan vs Index Scan (Seq Scan boyuk table-da pisdir)
-- 3. Rows estimate vs actual rows (boyuk ferq varsa, ANALYZE calisdir)
-- 4. Sort method (external sort = RAM yetmir)
```

## Practical JOIN Patterns

### Multiple Table JOIN

```sql
-- Sifaris detallari: user + order + items + products
SELECT
    u.name AS customer,
    o.order_number,
    o.created_at AS order_date,
    p.name AS product,
    oi.quantity,
    oi.price,
    (oi.quantity * oi.price) AS line_total
FROM orders o
JOIN users u ON u.id = o.user_id
JOIN order_items oi ON oi.order_id = o.id
JOIN products p ON p.id = oi.product_id
WHERE o.status = 'completed'
ORDER BY o.created_at DESC;
```

### Self-Referencing Hierarchy

```sql
-- Kateqoriya agaci (parent-child)
-- Recursive CTE ile
WITH RECURSIVE category_tree AS (
    -- Base: Root kateqoriyalar
    SELECT id, name, parent_id, 0 AS depth
    FROM categories
    WHERE parent_id IS NULL

    UNION ALL

    -- Recursive: Usaq kateqoriyalar
    SELECT c.id, c.name, c.parent_id, ct.depth + 1
    FROM categories c
    JOIN category_tree ct ON c.parent_id = ct.id
)
SELECT * FROM category_tree ORDER BY depth, name;
```

### Pivot with JOIN

```sql
-- Ayliq satislar (pivot table)
SELECT
    p.name,
    SUM(CASE WHEN MONTH(o.created_at) = 1 THEN oi.quantity ELSE 0 END) AS jan,
    SUM(CASE WHEN MONTH(o.created_at) = 2 THEN oi.quantity ELSE 0 END) AS feb,
    SUM(CASE WHEN MONTH(o.created_at) = 3 THEN oi.quantity ELSE 0 END) AS mar
FROM products p
JOIN order_items oi ON oi.product_id = p.id
JOIN orders o ON o.id = oi.order_id
WHERE o.created_at >= '2024-01-01'
GROUP BY p.id, p.name;
```

## Interview Suallari

1. **INNER JOIN ile LEFT JOIN ferqi?**
   - INNER: Yalniz her iki table-da match olan row-lar. LEFT: Sol table-in butun row-lari + sag table-dan match (ve ya NULL).

2. **Hash Join nece isleyir ve ne vaxt secilir?**
   - Kicik table-dan hash table qurulur, boyuk table scan olunur. O(N+M). Equality join ucun, index olmayanda secilir.

3. **JOIN-de index niye vacibdir?**
   - Nested Loop Join-de inner table-da index O(N×M)-i O(N×logM)-e endirir. FK column-larda index MUTLEQ olmalidir.

4. **EXISTS vs IN vs JOIN - hansi daha suretli?**
   - EXISTS: Suretli (first match-de dayanir). IN: Subquery kicik olanda yaxsi. JOIN: Aggregate lazim olanda. Boyuk data-da EXISTS adeten qalib gelir.

5. **MySQL ile PostgreSQL JOIN alqoritmleri arasinda ferq?**
   - PostgreSQL: Nested Loop, Hash Join, Sort-Merge - hersey var. MySQL: Uzun muddet yalniz Nested Loop, 8.0.18+ Hash Join elave olundu, Sort-Merge yoxdur.

6. **CROSS JOIN ne vaxt istifade olunur?**
   - Butun kombinasiyalari yaratmaq: sizes × colors. Test data yaratmaq. Tarix intervallari yaratmaq (calendar table).

7. **JOIN performance-i nece yaxsilasdirilir?**
   - 1) FK column-larda index, 2) Lazim olan column-lari sec (SELECT * deyil), 3) WHERE ile filtrleme JOIN-den evvel, 4) EXPLAIN ile analiz.
