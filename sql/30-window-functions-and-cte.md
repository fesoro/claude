# Window Functions & CTEs (Common Table Expressions) (Middle)

## Window Functions nedir?

Window function - row-lar uzerinde **hesablama** edir, amma `GROUP BY` kimi row-lari **birlesdirmir**. Her row oz neticesini saxlayir + window-dan elave melumat alir.

```sql
-- Adi GROUP BY: 1 department -> 1 row
SELECT department, AVG(salary) FROM employees GROUP BY department;

-- Window function: her employee row qalir + department avg-i de gorunur
SELECT 
    name,
    department,
    salary,
    AVG(salary) OVER (PARTITION BY department) AS dept_avg
FROM employees;
```

**Sintaksis:**

```sql
function() OVER (
    [PARTITION BY column]    -- window-u qruplara bol
    [ORDER BY column]         -- window icinde sirala
    [frame_clause]            -- window-un sehesini mueyyenlesdir
)
```

---

## ROW_NUMBER, RANK, DENSE_RANK

Uc ranking funksiyasi - amma davranisi ferqlidir.

```sql
SELECT 
    name,
    score,
    ROW_NUMBER() OVER (ORDER BY score DESC) AS row_num,
    RANK()       OVER (ORDER BY score DESC) AS rnk,
    DENSE_RANK() OVER (ORDER BY score DESC) AS dense
FROM students;
```

| name  | score | row_num | rank | dense_rank |
|-------|-------|---------|------|------------|
| Anna  | 100   | 1       | 1    | 1          |
| Bob   | 95    | 2       | 2    | 2          |
| Carol | 95    | 3       | 2    | 2          |
| David | 90    | 4       | 4    | 3          |

- `ROW_NUMBER`: hemise unique - 1, 2, 3, 4
- `RANK`: tie-de eyni rank, sonra **atlayir** - 1, 2, 2, 4
- `DENSE_RANK`: tie-de eyni rank, **atlamir** - 1, 2, 2, 3

---

## Top-N per Group (cox istifade olunan pattern)

Her departamentde en yuksek maasli 3 employee:

```sql
WITH ranked AS (
    SELECT 
        name, department, salary,
        ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS rn
    FROM employees
)
SELECT name, department, salary 
FROM ranked
WHERE rn <= 3;
```

**Laravel-de:**

```php
$top3PerDept = DB::table(DB::raw('(
    SELECT name, department, salary,
        ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS rn
    FROM employees
) AS ranked'))
->where('rn', '<=', 3)
->get();
```

---

## LAG ve LEAD - Onceki/Sonraki Row

```sql
-- Her gun ile onceki gun arasindaki satis ferqi
SELECT 
    sale_date,
    amount,
    LAG(amount, 1) OVER (ORDER BY sale_date) AS prev_amount,
    amount - LAG(amount, 1) OVER (ORDER BY sale_date) AS diff
FROM daily_sales;
```

**LAG(column, offset, default)** - onceki row-dan deyer alir.
**LEAD(column, offset, default)** - sonraki row-dan deyer alir.

Real istifade: user-in onceki login-i ile arasindaki vaxt:

```sql
SELECT 
    user_id,
    login_at,
    LAG(login_at) OVER (PARTITION BY user_id ORDER BY login_at) AS prev_login,
    EXTRACT(EPOCH FROM (login_at - LAG(login_at) OVER (PARTITION BY user_id ORDER BY login_at))) AS seconds_between
FROM user_logins;
```

---

## FIRST_VALUE, LAST_VALUE, NTILE

```sql
-- Her departmanda en yuksek ve en asagi maas
SELECT 
    name,
    department,
    salary,
    FIRST_VALUE(salary) OVER (PARTITION BY department ORDER BY salary DESC) AS top_salary,
    LAST_VALUE(salary) OVER (
        PARTITION BY department 
        ORDER BY salary DESC
        ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
    ) AS lowest_salary
FROM employees;
```

> **Tələ:** `LAST_VALUE` default frame yalniz current row-a qeder oxuyur. Duzgun deyer almaq ucun `ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING` lazimdir.

**NTILE(N)** - row-lari N beraber qrupa boler (quartile, decile):

```sql
-- Maaslari 4 quartile-a bol
SELECT name, salary, NTILE(4) OVER (ORDER BY salary) AS quartile
FROM employees;
```

---

## Frame Clauses (ROWS / RANGE BETWEEN)

Window-un **sehesini** mueyyenlesdir. Default: `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`.

```sql
-- Running total (her gunden evvelki butun satislari topla)
SELECT 
    sale_date,
    amount,
    SUM(amount) OVER (ORDER BY sale_date 
        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running_total
FROM daily_sales;

-- Moving average (son 7 gunun ortalamasi)
SELECT 
    sale_date,
    amount,
    AVG(amount) OVER (ORDER BY sale_date 
        ROWS BETWEEN 6 PRECEDING AND CURRENT ROW) AS ma_7day
FROM daily_sales;
```

| Frame | Menasi |
|-------|--------|
| `UNBOUNDED PRECEDING` | window-un evvelinden |
| `N PRECEDING` | N row geri |
| `CURRENT ROW` | indiki row |
| `N FOLLOWING` | N row irəli |
| `UNBOUNDED FOLLOWING` | window-un sonuna qeder |

**ROWS vs RANGE:**
- `ROWS` - fiziki row sayi
- `RANGE` - logical (deyere gore, eyni deyerleri birlikde sayar)

---

## CTE (Common Table Expression) - WITH Clause

CTE - query daxilinde adlandirilmis temporary result set. Kodu daha oxunaqli edir.

```sql
WITH active_users AS (
    SELECT * FROM users WHERE status = 'active'
),
recent_orders AS (
    SELECT * FROM orders WHERE created_at > NOW() - INTERVAL '30 days'
)
SELECT u.name, COUNT(o.id) AS order_count
FROM active_users u
LEFT JOIN recent_orders o ON o.user_id = u.id
GROUP BY u.name;
```

**CTE-nin ustunlukleri:**
- Oxunaqli (subquery-den daha yaxsi)
- Bir CTE bir nece defe istifade oluna biler
- Recursive query mumkundur

---

## CTE vs Subquery vs Derived Table

| Pattern | Misal | Ne vaxt |
|---------|-------|---------|
| **Subquery** | `WHERE id IN (SELECT ...)` | Kicik, sade filter |
| **Derived Table** | `FROM (SELECT ...) AS t` | Bir defe istifade |
| **CTE** | `WITH t AS (...) SELECT ...` | Oxunaqli, yenidən istifade, recursive |
| **Materialized CTE** | PostgreSQL 12+ `WITH t AS MATERIALIZED (...)` | Bir defe hesabla, cache et |

```sql
-- PostgreSQL 12+: CTE-ni materialize et (cache et)
WITH expensive_calc AS MATERIALIZED (
    SELECT user_id, COUNT(*) AS cnt FROM events GROUP BY user_id
)
SELECT * FROM expensive_calc WHERE cnt > 100
UNION ALL
SELECT * FROM expensive_calc WHERE cnt < 10;
-- Yalniz 1 defe icra olunur

-- NOT MATERIALIZED: optimizer inline edir (default)
WITH cheap_calc AS NOT MATERIALIZED (
    SELECT * FROM users WHERE active = true
)
SELECT * FROM cheap_calc WHERE created_at > NOW() - INTERVAL '7 days';
```

> **Diqqet:** PostgreSQL 11-de CTE hemise materialize olunurdu (optimization barrier). 12+-de inline default-dur.

---

## WITH RECURSIVE - Hierarchical Data

En guclu feature - tree/graph kimi data ile islemek.

### Org Chart (employee -> manager hierarchy)

```sql
WITH RECURSIVE org_tree AS (
    -- Anchor: CEO (manager_id NULL)
    SELECT id, name, manager_id, 1 AS level, name::TEXT AS path
    FROM employees WHERE manager_id IS NULL
    
    UNION ALL
    
    -- Recursive: her seviyye CEO-dan asagi
    SELECT e.id, e.name, e.manager_id, ot.level + 1, ot.path || ' > ' || e.name
    FROM employees e
    JOIN org_tree ot ON e.manager_id = ot.id
)
SELECT * FROM org_tree ORDER BY level, name;
```

Netice:
```
id | name  | level | path
1  | Alice | 1     | Alice
2  | Bob   | 2     | Alice > Bob
3  | Carol | 2     | Alice > Carol
4  | David | 3     | Alice > Bob > David
```

### Comment Tree (nested replies)

```sql
WITH RECURSIVE comment_tree AS (
    SELECT id, parent_id, content, 0 AS depth
    FROM comments WHERE parent_id IS NULL AND post_id = 42
    
    UNION ALL
    
    SELECT c.id, c.parent_id, c.content, ct.depth + 1
    FROM comments c
    JOIN comment_tree ct ON c.parent_id = ct.id
)
SELECT * FROM comment_tree;
```

### Category Tree (e-commerce)

```sql
-- Butun alt kategoriyalari tap (Electronics > Phones > Smartphones > ...)
WITH RECURSIVE subcategories AS (
    SELECT id, name, parent_id FROM categories WHERE id = 1
    UNION ALL
    SELECT c.id, c.name, c.parent_id 
    FROM categories c
    JOIN subcategories sc ON c.parent_id = sc.id
)
SELECT * FROM subcategories;
```

**Laravel-de:**

```php
$tree = DB::select("
    WITH RECURSIVE comment_tree AS (
        SELECT id, parent_id, content, 0 AS depth
        FROM comments WHERE parent_id IS NULL AND post_id = ?
        UNION ALL
        SELECT c.id, c.parent_id, c.content, ct.depth + 1
        FROM comments c JOIN comment_tree ct ON c.parent_id = ct.id
    )
    SELECT * FROM comment_tree
", [$postId]);
```

---

## MySQL vs PostgreSQL Support

| Feature | MySQL | PostgreSQL |
|---------|-------|------------|
| Window functions | 8.0+ | 8.4+ |
| CTE (WITH) | 8.0+ | 8.4+ |
| WITH RECURSIVE | 8.0+ | 8.4+ |
| MATERIALIZED hint | Yox | 12+ |
| `LATERAL` join | 8.0.14+ | 9.3+ |
| `FILTER` clause | Yox (CASE WHEN istifade et) | 9.4+ |

```sql
-- PostgreSQL FILTER (window function-da):
SELECT 
    department,
    COUNT(*) FILTER (WHERE salary > 50000) OVER (PARTITION BY department) AS high_earners
FROM employees;

-- MySQL ekvivalenti:
SELECT 
    department,
    SUM(CASE WHEN salary > 50000 THEN 1 ELSE 0 END) OVER (PARTITION BY department) AS high_earners
FROM employees;
```

---

## Real Patterns

### 1. Running Total

```sql
SELECT 
    order_date,
    amount,
    SUM(amount) OVER (ORDER BY order_date) AS running_total
FROM orders;
```

### 2. Deduplication with ROW_NUMBER

User-in dublikat email-leri var, en koxhneni saxla, qalanlarini sil:

```sql
WITH dups AS (
    SELECT id, email,
        ROW_NUMBER() OVER (PARTITION BY email ORDER BY created_at) AS rn
    FROM users
)
DELETE FROM users WHERE id IN (SELECT id FROM dups WHERE rn > 1);
```

### 3. Gaps and Islands

Ardicil gunlerde aktiv olan istifadeciler:

```sql
WITH grouped AS (
    SELECT user_id, login_date,
        login_date - INTERVAL '1 day' * ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY login_date) AS grp
    FROM user_logins
)
SELECT user_id, MIN(login_date) AS streak_start, MAX(login_date) AS streak_end, COUNT(*) AS streak_days
FROM grouped
GROUP BY user_id, grp
HAVING COUNT(*) >= 7;  -- 7+ gun ardicil
```

### 4. Percentile

```sql
-- Maaslarin median-i (50th percentile)
SELECT DISTINCT
    department,
    PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY salary) OVER (PARTITION BY department) AS median
FROM employees;
```

---

## Laravel Practical Examples

```php
// Top 5 customer per region
$topCustomers = DB::select("
    WITH ranked AS (
        SELECT region, customer_id, total_spent,
            ROW_NUMBER() OVER (PARTITION BY region ORDER BY total_spent DESC) AS rn
        FROM customer_summary
    )
    SELECT * FROM ranked WHERE rn <= 5
");

// Running revenue with Eloquent
$revenue = Order::select(
    'order_date',
    'amount',
    DB::raw('SUM(amount) OVER (ORDER BY order_date) AS running_total')
)->get();

// Recursive category tree
$categories = DB::select("
    WITH RECURSIVE tree AS (
        SELECT id, name, parent_id, 0 AS depth
        FROM categories WHERE parent_id IS NULL
        UNION ALL
        SELECT c.id, c.name, c.parent_id, t.depth + 1
        FROM categories c JOIN tree t ON c.parent_id = t.id
    )
    SELECT * FROM tree ORDER BY depth, name
");
```

---

## Ne vaxt istifade et / Ne vaxt yox

**Window function istifade et:**
- Ranking, top-N per group
- Running totals, moving averages
- Onceki/sonraki row-la muqayise (LAG/LEAD)
- Deduplication

**CTE istifade et:**
- Murekkeb query-leri **oxunaqli** etmek
- Ayni subquery-ni bir nece defe istifade
- Recursive (tree, graph)

**Istifade etme:**
- Sade GROUP BY isleyirse - window function lazim deyil
- MySQL 5.7-de (destek yoxdur)
- Cox boyuk dataset-de PARTITION BY (memory limit)
- Recursive: tree cox derindirse (depth limit lazim - infinite loop riski!)

```sql
-- Recursive depth limit (infinite loop qarsisi)
WITH RECURSIVE tree AS (
    SELECT id, parent_id, 0 AS depth FROM categories WHERE id = 1
    UNION ALL
    SELECT c.id, c.parent_id, t.depth + 1
    FROM categories c JOIN tree t ON c.parent_id = t.id
    WHERE t.depth < 10  -- max derinlik
)
SELECT * FROM tree;
```

---

## Interview suallari

**Q: ROW_NUMBER, RANK, DENSE_RANK arasinda ferq nedir?**
A: ROW_NUMBER hemise unique deyerler verir (1,2,3,4). RANK tie-de eyni nomre verir, sonra atlayir (1,2,2,4). DENSE_RANK tie-de eyni nomre verir, amma atlamir (1,2,2,3). Top-N per group ucun adeten ROW_NUMBER istifade olunur.

**Q: CTE ile derived table arasinda performance ferqi varmi?**
A: PostgreSQL 12+-de demek olar yoxdur (CTE inline edilir default). Amma PostgreSQL 11-de CTE optimization barrier idi (materialize olunurdu) - subquery daha suretli olurdu. MySQL 8.0+-de optimizer her ikisini eyni ele alir. Recursive CTE ucun alternativ yoxdur - yalniz CTE-de mumkundur.

**Q: WITH RECURSIVE-de infinite loop nece qarsisini almaq olar?**
A: Uc usul: 1) Depth column elave et ve `WHERE depth < N` qoy. 2) Visited node-lari array-de saxla (PostgreSQL): `WHERE NOT (id = ANY(visited))`. 3) PostgreSQL 14+-de `CYCLE` clause var: `CYCLE id SET is_cycle USING path`.

**Q: LAG funksiyasi olmadan onceki row ile muqayise nece edilirdi?**
A: SELF JOIN ile - `JOIN sales s2 ON s2.date = s1.date - INTERVAL '1 day'`. Cox yavas idi (her row ucun JOIN). Window function bunu O(N) edir cunki data bir defe sirali oxunur.

**Q: PARTITION BY olmadan window function isleyirmi?**
A: Beli, butun result set bir window kimi qebul olunur. Misal: `ROW_NUMBER() OVER (ORDER BY id)` - butun row-lara global nomre verir. PARTITION BY-li versiya: `OVER (PARTITION BY dept ORDER BY id)` - her departamentde nomre 1-den baslayir.
