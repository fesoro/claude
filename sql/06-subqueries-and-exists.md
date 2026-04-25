# Subqueries, EXISTS, ANY, ALL (Junior)

## Subquery Nədir?

`SELECT` icinde yazilan başqa bir `SELECT`. **Nested query** də adlanır.

```sql
-- En yuksek qiymetli məhsul
SELECT * FROM products 
WHERE price = (SELECT MAX(price) FROM products);
```

## Subquery Növləri

### 1. Scalar Subquery — 1 row, 1 sütun

Tək bir dəyər qaytarır. İfadə kimi istifadə olunur.

```sql
-- SELECT-də
SELECT 
    name, 
    salary,
    (SELECT AVG(salary) FROM employees) AS company_avg
FROM employees;

-- WHERE-də müqayisə
SELECT * FROM products 
WHERE price > (SELECT AVG(price) FROM products);
```

**Diqqət:** Scalar subquery > 1 row qaytarırsa, **error**.

### 2. Row Subquery — 1 row, çox sütun

```sql
-- Bir row-un bütün sütunlarını müqayisə et
SELECT * FROM employees
WHERE (department, salary) = (
    SELECT department, MAX(salary) 
    FROM employees WHERE id = 10
);
```

### 3. Column/Table Subquery — çox row

```sql
-- IN ilə
SELECT * FROM users 
WHERE id IN (SELECT user_id FROM orders WHERE total > 1000);

-- FROM-da (derived table)
SELECT avg_country.country, avg_country.avg_age
FROM (
    SELECT country, AVG(age) AS avg_age
    FROM users
    GROUP BY country
) AS avg_country
WHERE avg_country.avg_age > 30;
```

## Correlated Subquery

Outer query-nin sütunlarına **istinad** edir. Hər outer row üçün execute olunur.

```sql
-- Öz department-inin orta maaşından çox alan işçilər
SELECT e.name, e.salary, e.department
FROM employees e
WHERE e.salary > (
    SELECT AVG(salary) 
    FROM employees 
    WHERE department = e.department   -- OUTER query-yə istinad!
);
```

**Performans riski:** Hər outer row üçün inner query işləyir. Çox row varsa yavaş olur. **Alternativ:** JOIN + window function:

```sql
-- Daha sürətli
SELECT name, salary, department
FROM (
    SELECT name, salary, department,
           AVG(salary) OVER (PARTITION BY department) AS dept_avg
    FROM employees
) t
WHERE salary > dept_avg;
```

## EXISTS / NOT EXISTS

Subquery **ən azı 1 row qaytarırmı?** Boolean nəticə.

```sql
-- Ən azı 1 sifarişi olan user-lər
SELECT * FROM users u
WHERE EXISTS (
    SELECT 1 FROM orders o WHERE o.user_id = u.id
);

-- Sifarişi OLMAYAN user-lər
SELECT * FROM users u
WHERE NOT EXISTS (
    SELECT 1 FROM orders o WHERE o.user_id = u.id
);
```

### `SELECT 1` niyə?

`EXISTS` yalnız row olub-olmadığını yoxlayır, dəyərləri vecinə deyil. `SELECT 1`, `SELECT *`, `SELECT col` — hamısı eyni sürətdədir (DB optimizer konvert edir), amma `SELECT 1` konvensional.

### EXISTS vs IN

```sql
-- IN versiyasi
SELECT * FROM users 
WHERE id IN (SELECT user_id FROM orders);

-- EXISTS versiyasi
SELECT * FROM users u
WHERE EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id);
```

Muasir DB-lərdə ikisi də eyni plana çevrilir. **Amma NULL olan halda fərqli:**

```sql
-- Niye NOT IN TEHLUKELI:
SELECT * FROM users WHERE id NOT IN (1, 2, NULL);
-- Netice: bosh set (NULL 3-valued logic)

-- NOT EXISTS bu problemi yashamir:
SELECT * FROM users u 
WHERE NOT EXISTS (SELECT 1 FROM excluded WHERE excluded.user_id = u.id);
-- Ishleyir (NULL-leri ignorlayir)
```

**Qayda:** `NOT` lazımdırsa, `NOT EXISTS` istifadə et (NOT IN yox).

## ANY / SOME

```sql
-- Hər hansı manager-dən çox qazanan işçi
SELECT * FROM employees
WHERE salary > ANY (
    SELECT salary FROM employees WHERE role = 'manager'
);
-- Minimum manager maaşından çoxu - işləyir

-- SOME ANY-nin sinonimidir (SQL standard)
```

## ALL

```sql
-- Bütün manager-dən çox qazanan işçi
SELECT * FROM employees
WHERE salary > ALL (
    SELECT salary FROM employees WHERE role = 'manager'
);
-- Maksimum manager maaşından çoxu
```

### ANY / ALL vs Aggregate

```sql
-- Daha oxunaqli yazim:
WHERE salary > ANY (SELECT salary FROM managers);
-- = WHERE salary > (SELECT MIN(salary) FROM managers);

WHERE salary > ALL (SELECT salary FROM managers);
-- = WHERE salary > (SELECT MAX(salary) FROM managers);
```

## Derived Table (FROM-da Subquery)

```sql
-- Her country-də ilk 3 məhsul
SELECT *
FROM (
    SELECT 
        product_id, country, price,
        ROW_NUMBER() OVER (PARTITION BY country ORDER BY price DESC) AS rn
    FROM product_sales
) t
WHERE rn <= 3;
```

Derived table-da alias **mutləqdir**.

## CTE (Common Table Expression) — daha oxunaqli subquery

```sql
-- Derived table əvəzinə CTE
WITH ranked_products AS (
    SELECT 
        product_id, country, price,
        ROW_NUMBER() OVER (PARTITION BY country ORDER BY price DESC) AS rn
    FROM product_sales
)
SELECT * FROM ranked_products WHERE rn <= 3;
```

Ətraflı: `30-window-functions-and-cte.md`.

## Praktik İstifadə Nümunələri

### 1. Sifarişi olmayan user-lər

```sql
-- NOT EXISTS
SELECT * FROM users u
WHERE NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id);

-- LEFT JOIN trick
SELECT u.* FROM users u
LEFT JOIN orders o ON o.user_id = u.id
WHERE o.id IS NULL;
```

### 2. Ən son sifariş hər user üçün

```sql
-- Correlated subquery
SELECT u.name, (
    SELECT MAX(created_at) FROM orders o WHERE o.user_id = u.id
) AS last_order
FROM users u;

-- Window function (daha sürətli)
SELECT name, last_order
FROM (
    SELECT u.name, o.created_at AS last_order,
           ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY o.created_at DESC) AS rn
    FROM users u JOIN orders o ON o.user_id = u.id
) t WHERE rn = 1;
```

### 3. Duplicate Row-ları Tap

```sql
-- Eyni email ilə bir neçə user
SELECT * FROM users
WHERE email IN (
    SELECT email FROM users GROUP BY email HAVING COUNT(*) > 1
);
```

## Laravel Nümunəsi

```php
// Scalar subquery
$aboveAvg = DB::table('products')
    ->where('price', '>', DB::table('products')->avgRaw('price'))
    ->get();

// WHERE IN (subquery)
User::whereIn('id', function ($q) {
    $q->select('user_id')->from('orders')->where('total', '>', 1000);
})->get();

// EXISTS
User::whereExists(function ($q) {
    $q->select(DB::raw(1))
      ->from('orders')
      ->whereColumn('orders.user_id', 'users.id');
})->get();

// NOT EXISTS
User::whereNotExists(function ($q) {
    $q->select(DB::raw(1))
      ->from('orders')
      ->whereColumn('orders.user_id', 'users.id');
})->get();

// doesntHave (Eloquent əlaqə ilə eyni effekt)
User::doesntHave('orders')->get();
User::has('orders', '>=', 5)->get();
```

## Interview Sualları

**Q: `IN` və `EXISTS` nə vaxt fərqli olur?**
A: Nəticə etibarı ilə muasir DB-lərdə eynidir (optimizer convert edir). Amma NULL-lərin olması `NOT IN`-i çökdürür, `NOT EXISTS` isə düzgün işləyir.

**Q: Correlated subquery `N × M` vaxt tələb edir — nə vaxt istifadə etməli?**
A: Kiçik data-set üçün OK. Böyük üçün JOIN + window function və ya CTE istifadə et. EXPLAIN ilə plan-ı yoxla.

**Q: `FROM`-da subquery (derived table) ilə CTE fərqi?**
A: Sintaktik olaraq fərqli, lakin performance fərqi adətən yoxdur (PostgreSQL-də CTE materialize oluna bilir — inline ilə fərq yaranır). CTE oxunaqlı və recursive query üçün lazımdır.

**Q: `EXISTS (SELECT 1 FROM ...)` və `EXISTS (SELECT * FROM ...)` performans fərqi?**
A: Yoxdur. DB optimizer `SELECT` list-indəki sütunları ignorlayır. Konvensional `SELECT 1`.
