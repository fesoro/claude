# Set Operations & Advanced GROUP BY

> **Seviyye:** Intermediate ⭐⭐

## Set Operations Nedir?

Iki ve ya daha cox SELECT-in neticelerini birlestiren operatorlar:

| Operator | Davranis | Dedupe |
|----------|----------|--------|
| `UNION` | A + B birlikde | Beli (DISTINCT) |
| `UNION ALL` | A + B birlikde | Yox (sade concat) |
| `INTERSECT` | A ve B-de olan | Beli |
| `INTERSECT ALL` | Multiset intersect | Yox (PG) |
| `EXCEPT` (PG) / `MINUS` (Oracle) | A-da var, B-de yox | Beli |
| `EXCEPT ALL` | Multiset minus | Yox (PG) |

**Qaydalar:**
1. Hər SELECT eyni **column sayina** sahib olmalidir
2. Column tip-leri **uyumlu** olmalidir (auto cast cox vaxt)
3. Result-un column adlari **birinci** SELECT-den gelir
4. `ORDER BY` yalniz **sonda** mumkundur (umumi result-a)

---

## UNION vs UNION ALL

```sql
-- UNION (dedupe)
SELECT email FROM newsletter_subscribers
UNION
SELECT email FROM users;
-- Eyni email 1 defe qalir, sort/hash etmek lazim - YAVAS

-- UNION ALL (no dedupe)
SELECT email FROM newsletter_subscribers
UNION ALL
SELECT email FROM users;
-- Hamisini birlestirir, dedupe yoxdur - SURETLI
```

**Performance ferqi:**
- UNION = UNION ALL + DISTINCT (sort/hash agregat)
- 10M row: UNION 30s, UNION ALL 3s

**Qizil qayda:** UNIQUE oldugunu bilirsense, **hemise UNION ALL** istifade et.

```sql
-- PIS (gereksiz dedupe)
SELECT id, 'order' AS type FROM orders WHERE created_at > NOW() - INTERVAL '1 day'
UNION
SELECT id, 'payment' AS type FROM payments WHERE created_at > NOW() - INTERVAL '1 day';

-- YAXSI (id+type unique - dedupe lazim deyil)
SELECT id, 'order' AS type FROM orders WHERE created_at > NOW() - INTERVAL '1 day'
UNION ALL
SELECT id, 'payment' AS type FROM payments WHERE created_at > NOW() - INTERVAL '1 day';
```

---

## INTERSECT - Iki Result-da Olanlar

```sql
-- Hem newsletter, hem user kimi qeydiyyat olanlar
SELECT email FROM newsletter_subscribers
INTERSECT
SELECT email FROM users;
```

**MySQL destek:** 8.0.31+ (yenidir!). Onceki versiyalarda emulasiya:

```sql
-- INTERSECT alternativ (MySQL <8.0.31)
SELECT DISTINCT n.email
FROM newsletter_subscribers n
WHERE EXISTS (SELECT 1 FROM users u WHERE u.email = n.email);

-- ya
SELECT DISTINCT n.email
FROM newsletter_subscribers n
INNER JOIN users u ON u.email = n.email;
```

---

## EXCEPT / MINUS - Ferq

```sql
-- PostgreSQL/SQL Standard
SELECT email FROM newsletter_subscribers
EXCEPT
SELECT email FROM users;
-- Newsletter-de var, user kimi qeydiyyatda YOX

-- Oracle
SELECT email FROM newsletter_subscribers
MINUS
SELECT email FROM users;
```

**MySQL 8.0.31+** EXCEPT destek edir. Onceki versiyalarda:

```sql
SELECT DISTINCT n.email
FROM newsletter_subscribers n
WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.email = n.email);

-- ya LEFT JOIN ile
SELECT DISTINCT n.email
FROM newsletter_subscribers n
LEFT JOIN users u ON u.email = n.email
WHERE u.email IS NULL;
```

**Real istifade:** Marketing - newsletter abune, amma platform user-i deyil -> "register" kampaniyasi gonder.

---

## ORDER BY in Set Operations

```sql
-- ORDER BY YALNIZ SONDA
SELECT id, name FROM customers
UNION ALL
SELECT id, name FROM suppliers
ORDER BY name;
-- ORDER BY butun result-a, name birinci SELECT-den gelir

-- Type column elave et (hansi table-dan?)
SELECT id, name, 'customer' AS type FROM customers
UNION ALL
SELECT id, name, 'supplier' AS type FROM suppliers
ORDER BY type, name;

-- ALT-SELECT-de ORDER lazimdirsa LIMIT ile
(SELECT id FROM orders ORDER BY total DESC LIMIT 10)
UNION ALL
(SELECT id FROM orders ORDER BY created_at DESC LIMIT 10);
```

---

## GROUP BY Recap

```sql
SELECT department, COUNT(*) AS emp_count, AVG(salary) AS avg_salary
FROM employees
GROUP BY department
HAVING AVG(salary) > 50000
ORDER BY avg_salary DESC;
```

**Execution order:**
```
FROM -> WHERE -> GROUP BY -> HAVING -> SELECT -> ORDER BY -> LIMIT
```

**WHERE vs HAVING:**

| | WHERE | HAVING |
|---|-------|--------|
| Ne vaxt | GROUP BY-dan **evvel** | GROUP BY-dan **sonra** |
| Aggregate? | Yox | Beli |
| Index? | Beli (faydali) | Yox (ada hesablanmis) |

```sql
-- PIS: HAVING-de aggregate olmayan sert
SELECT department, COUNT(*)
FROM employees
GROUP BY department
HAVING department != 'HR';  -- WHERE-de olmali!

-- YAXSI
SELECT department, COUNT(*)
FROM employees
WHERE department != 'HR'    -- evvel filter
GROUP BY department;
```

---

## ROLLUP - Subtotals + Grand Total

```sql
-- Department + position uzre, plus subtotal her department, plus grand total
SELECT 
    department,
    position,
    SUM(salary) AS total_salary
FROM employees
GROUP BY ROLLUP(department, position);
```

Netice:
```
department | position | total_salary
-----------+----------+-------------
Eng        | Senior   | 300000      <- per dept+position
Eng        | Junior   | 100000
Eng        | NULL     | 400000      <- Eng subtotal
Sales      | Manager  | 150000
Sales      | NULL     | 150000      <- Sales subtotal
NULL       | NULL     | 550000      <- GRAND TOTAL
```

**Real BI use:** Sales dashboard - region uzre, region+product uzre, ve total.

```sql
-- MySQL ROLLUP sintaksisi (kohne stil)
SELECT department, SUM(salary)
FROM employees
GROUP BY department WITH ROLLUP;
```

---

## CUBE - Butun Kombinasiyalar

```sql
SELECT 
    region,
    product,
    SUM(amount)
FROM sales
GROUP BY CUBE(region, product);
```

Netice (8 dimensions kombinasiyasi):
```
region | product | sum
-------+---------+----
EU     | Phone   | 100   -- (region, product)
EU     | Laptop  | 200
US     | Phone   | 150
US     | Laptop  | 300
EU     | NULL    | 300   -- EU total (her product)
US     | NULL    | 450
NULL   | Phone   | 250   -- Phone total (her region)
NULL   | Laptop  | 500
NULL   | NULL    | 750   -- GRAND TOTAL
```

**ROLLUP vs CUBE:**
- ROLLUP: hierarchical (a, ab, abc) - left-to-right rollup
- CUBE: butun 2^n kombinasiyalar

| Dimension | ROLLUP rows | CUBE rows |
|-----------|-------------|-----------|
| 2 (a, b) | 3 | 4 |
| 3 (a, b, c) | 4 | 8 |
| 4 | 5 | 16 |

---

## GROUPING SETS - Custom Groups

```sql
-- Yalniz spesifik kombinasiyalar
SELECT 
    region,
    product,
    quarter,
    SUM(amount)
FROM sales
GROUP BY GROUPING SETS (
    (region, product),       -- region+product combo
    (region, quarter),       -- region+quarter combo
    (region),                -- region total
    ()                       -- grand total
);
```

CUBE/ROLLUP-dan daha **flexible** - lazim olan kombinasiyalar secilir.

---

## GROUPING() Function

ROLLUP/CUBE-da NULL-i secdir: real NULL ve ya subtotal-dan?

```sql
SELECT 
    COALESCE(department, 'ALL DEPTS') AS dept,
    COALESCE(position, 'ALL POSITIONS') AS pos,
    SUM(salary) AS total,
    GROUPING(department) AS is_dept_total,
    GROUPING(position) AS is_pos_total
FROM employees
GROUP BY ROLLUP(department, position);
```

| dept | pos | total | is_dept_total | is_pos_total |
|------|-----|-------|---------------|--------------|
| Eng | Senior | 300000 | 0 | 0 |
| Eng | ALL POSITIONS | 400000 | 0 | 1 |
| ALL DEPTS | ALL POSITIONS | 550000 | 1 | 1 |

`GROUPING(col) = 1` -> bu row aggregate (subtotal/total), `0` -> normal group.

---

## FILTER Clause (PostgreSQL)

PG 9.4+ -> aggregate-de **selective** count.

```sql
SELECT 
    department,
    COUNT(*) AS total_employees,
    COUNT(*) FILTER (WHERE salary > 50000) AS high_earners,
    COUNT(*) FILTER (WHERE gender = 'F') AS female_count,
    AVG(salary) FILTER (WHERE position = 'Senior') AS avg_senior_salary
FROM employees
GROUP BY department;
```

**MySQL ekvivalenti** (CASE WHEN):

```sql
SELECT 
    department,
    COUNT(*) AS total_employees,
    SUM(CASE WHEN salary > 50000 THEN 1 ELSE 0 END) AS high_earners,
    SUM(CASE WHEN gender = 'F' THEN 1 ELSE 0 END) AS female_count,
    AVG(CASE WHEN position = 'Senior' THEN salary END) AS avg_senior_salary
FROM employees
GROUP BY department;
```

FILTER daha oxunaqlidir + bezi planner-ler optimize edir.

---

## Conditional Aggregation Pattern

Pivot ucun cox istifade olunur:

```sql
-- Order status-lerin sayini sutun-sutun goster
SELECT 
    user_id,
    COUNT(*) FILTER (WHERE status = 'pending') AS pending_count,
    COUNT(*) FILTER (WHERE status = 'completed') AS completed_count,
    COUNT(*) FILTER (WHERE status = 'cancelled') AS cancelled_count,
    SUM(total) FILTER (WHERE status = 'completed') AS revenue
FROM orders
GROUP BY user_id;
```

**Netice:**
```
user_id | pending | completed | cancelled | revenue
--------+---------+-----------+-----------+--------
1       | 2       | 10        | 1         | 1500.00
2       | 0       | 5         | 0         | 800.00
```

Bu **manual pivot**-dur. Real pivot funksiyasi yalniz Oracle, SQL Server-de var (PG-de tablefunc extension).

---

## DISTINCT in Aggregates

```sql
-- Cox unique product satildi?
SELECT COUNT(DISTINCT product_id) FROM order_items;

-- Eger 1M row-luk DISTINCT cox baha olar
-- Approximate: HyperLogLog (PG: postgresql-hll extension)
```

`COUNT(DISTINCT x)` performance-i:
- O(N) memory (hash set)
- Boyuk dataset-de spill to disk
- Approximate alternativ: HyperLogLog (~1% xeta, log(N) memory)

```sql
-- PostgreSQL APPROXIMATE
SELECT approx_count_distinct(user_id) FROM events;  -- citus
SELECT count_distinct(user_id) FROM events;          -- pg_extra_hll
```

---

## GROUP_CONCAT vs string_agg vs array_agg

| DB | String concat | Array |
|----|---------------|-------|
| MySQL | `GROUP_CONCAT(name SEPARATOR ', ')` | `JSON_ARRAYAGG(name)` |
| PostgreSQL | `STRING_AGG(name, ', ')` | `ARRAY_AGG(name)` |
| Oracle | `LISTAGG(name, ', ')` | `COLLECT(name)` |
| SQL Server | `STRING_AGG(name, ', ')` | yox |

**Misal:**

```sql
-- MySQL
SELECT 
    department,
    GROUP_CONCAT(name ORDER BY name SEPARATOR ', ') AS employees
FROM employees
GROUP BY department;

-- PostgreSQL
SELECT 
    department,
    STRING_AGG(name, ', ' ORDER BY name) AS employees,
    ARRAY_AGG(name ORDER BY name) AS emp_array,
    JSON_AGG(JSON_BUILD_OBJECT('name', name, 'salary', salary)) AS emp_json
FROM employees
GROUP BY department;
```

**Caveat:** MySQL `group_concat_max_len` default 1024 - boyuk siyahilarda silinir!

```sql
SET group_concat_max_len = 1000000;  -- 1MB
```

---

## Group by Expressions

```sql
-- Ay uzre group
SELECT 
    DATE_TRUNC('month', created_at) AS month,
    COUNT(*) AS orders
FROM orders
GROUP BY DATE_TRUNC('month', created_at);

-- MySQL
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') AS month,
    COUNT(*) AS orders
FROM orders
GROUP BY DATE_FORMAT(created_at, '%Y-%m');

-- Range bucket
SELECT 
    CASE 
        WHEN age < 18 THEN '0-17'
        WHEN age < 30 THEN '18-29'
        WHEN age < 50 THEN '30-49'
        ELSE '50+'
    END AS age_group,
    COUNT(*)
FROM users
GROUP BY age_group;
```

**PostgreSQL alias-i GROUP BY-da istifade ede bilersən** (MySQL da olur). SQL standard ekspresiyasini tekrar yazmagi teleb edir.

---

## TABLESAMPLE (PostgreSQL)

Boyuk table-den random sample:

```sql
-- 1% random sample
SELECT AVG(amount) FROM orders TABLESAMPLE BERNOULLI(1);

-- Block-level sampling (sürətli, az random)
SELECT * FROM orders TABLESAMPLE SYSTEM(0.5);  -- ~0.5% page-leri
```

**Use case:** 1B row-luk table-de quick estimate, A/B test-de random user secimi.

---

## LATERAL JOIN (Brief)

LATERAL = `subquery onceki table-in column-larini istifade ede biler`.

```sql
-- Her user ucun en son 3 order
SELECT u.id, u.name, o.id AS order_id, o.total
FROM users u
LEFT JOIN LATERAL (
    SELECT id, total
    FROM orders
    WHERE user_id = u.id      -- u.id-i istifade edir!
    ORDER BY created_at DESC
    LIMIT 3
) o ON true;
```

Window function alternativi var, amma LATERAL bezen daha intuitivdir.

---

## Real BI Report Examples

### Sales Dashboard

```sql
-- Ay/region/product uzre satislar (CUBE)
SELECT 
    DATE_TRUNC('month', sale_date) AS month,
    region,
    product_category,
    SUM(amount) AS revenue,
    COUNT(DISTINCT customer_id) AS customers,
    SUM(amount) / NULLIF(COUNT(DISTINCT customer_id), 0) AS arpu
FROM sales
WHERE sale_date >= NOW() - INTERVAL '12 months'
GROUP BY CUBE(DATE_TRUNC('month', sale_date), region, product_category)
ORDER BY month, region, product_category;
```

### Funnel Analysis

```sql
-- Acquisition funnel
SELECT 
    DATE_TRUNC('week', signed_up_at) AS week,
    COUNT(*) AS signups,
    COUNT(*) FILTER (WHERE first_order_at IS NOT NULL) AS converted,
    ROUND(100.0 * COUNT(*) FILTER (WHERE first_order_at IS NOT NULL) / COUNT(*), 2) AS conversion_pct
FROM users
GROUP BY week
ORDER BY week;
```

### Cohort Analysis

```sql
WITH cohorts AS (
    SELECT 
        user_id,
        DATE_TRUNC('month', MIN(created_at)) AS cohort_month
    FROM orders
    GROUP BY user_id
),
activity AS (
    SELECT 
        c.cohort_month,
        DATE_TRUNC('month', o.created_at) AS active_month,
        COUNT(DISTINCT o.user_id) AS active_users
    FROM cohorts c
    JOIN orders o ON o.user_id = c.user_id
    GROUP BY c.cohort_month, DATE_TRUNC('month', o.created_at)
)
SELECT 
    cohort_month,
    SUM(active_users) FILTER (WHERE active_month = cohort_month) AS m0,
    SUM(active_users) FILTER (WHERE active_month = cohort_month + INTERVAL '1 month') AS m1,
    SUM(active_users) FILTER (WHERE active_month = cohort_month + INTERVAL '2 months') AS m2
FROM activity
GROUP BY cohort_month
ORDER BY cohort_month;
```

---

## Laravel Examples

```php
// UNION ALL
$activities = DB::table('orders')
    ->select('id', 'created_at', DB::raw("'order' as type"))
    ->unionAll(
        DB::table('payments')
            ->select('id', 'created_at', DB::raw("'payment' as type"))
    )
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

// Conditional aggregation
$stats = DB::table('orders')
    ->select('user_id',
        DB::raw("COUNT(*) FILTER (WHERE status = 'completed') as completed"),
        DB::raw("COUNT(*) FILTER (WHERE status = 'pending') as pending"),
        DB::raw("SUM(total) FILTER (WHERE status = 'completed') as revenue")
    )
    ->groupBy('user_id')
    ->get();

// String aggregation
$result = DB::table('employees')
    ->select('department', DB::raw("STRING_AGG(name, ', ' ORDER BY name) as employees"))
    ->groupBy('department')
    ->get();

// ROLLUP (raw)
$report = DB::select("
    SELECT 
        COALESCE(region, 'ALL') AS region,
        COALESCE(product, 'ALL') AS product,
        SUM(amount) AS total
    FROM sales
    GROUP BY ROLLUP(region, product)
");
```

---

## Performance Tips

| Pattern | Tip |
|---------|-----|
| UNION | UNIQUE bilirsen -> UNION ALL |
| GROUP BY | Indexed column-larla group et (sort lazim olmasin) |
| HAVING | WHERE-e cevirmek olarsa cevir (filter erkenden) |
| COUNT(DISTINCT) | Cox baha - HLL ya da approximation dusun |
| GROUPING SETS | CUBE/ROLLUP eveziye - lazim olanlari sec |
| ORDER BY in UNION | Sonda yaz, parantez icindeki ORDER BY yalniz LIMIT-le mequldur |

---

## Interview suallari

**Q: UNION ve UNION ALL fərqi performance-de neye sebebdir?**
A: UNION = UNION ALL + DISTINCT. Distinct etmek ucun result-i sort/hash etmek lazimdir - O(N log N) ya da O(N) memory. UNION ALL sade concat - O(N). 10M row-da ferq 10x ola biler. Eger ID-ler unique-dirse UNION ALL hemise dogru secimdir.

**Q: WHERE vs HAVING ne vaxt istifade?**
A: WHERE - aggregate olmayan filter, GROUP BY-dan evvel isleyir, index istifade ede biler. HAVING - aggregate result-a filter (COUNT > 5), GROUP BY-dan sonra isleyir, index yoxdur. Mumkun olanda WHERE-e kec - daha az row aggregate olunur.

**Q: ROLLUP, CUBE, GROUPING SETS arasinda ferq?**
A: ROLLUP - hierarchical subtotal-lar (region, region+product, total). CUBE - butun kombinasiyalar (2^n grup). GROUPING SETS - custom secim (yalniz lazim olan kombinasiyalar). BI report-larinda CUBE en cox - amma boyuk dataset-de cox yavas, GROUPING SETS daha optimaldir.

**Q: COUNT(DISTINCT) niye yavasdir, alternativ?**
A: Hash set qurur, butun unique deyerleri RAM-da saxlayir. 100M unique deyer = ~5GB RAM, spill to disk lazim olur. Alternativ: HyperLogLog (Postgres extension postgresql-hll, Redis HLL) - ~1% xeta ile log(N) memory. Daily unique visitor counter ucun ideal.

**Q: FILTER clause vs CASE WHEN ferqi?**
A: Funksional eyni netice. FILTER (PG 9.4+) daha oxunaqli ve bezi planner-lerde daha optimaldir (index push-down). MySQL FILTER destek etmir - CASE WHEN istifade etmek lazim. Standardlasdirmaq isteyirsense CASE WHEN universal-dir.
