# Aggregate Functions, GROUP BY & HAVING

> **Seviyye:** Beginner ⭐

## Aggregate Functions

Birdən çox row-u bir deyərə çevirir.

| Function | Nə edir | NULL davranışı |
|----------|---------|----------------|
| `COUNT(*)` | bütün row-ları sayır | NULL daxildir |
| `COUNT(col)` | col NOT NULL olan row-ları sayır | NULL sayılmır |
| `COUNT(DISTINCT col)` | unique deyər sayı | NULL sayılmır |
| `SUM(col)` | cəm | NULL ignorlanır |
| `AVG(col)` | orta | NULL ignorlanır |
| `MIN(col)` | minimum | NULL ignorlanır |
| `MAX(col)` | maksimum | NULL ignorlanır |

```sql
-- Nümunələr
SELECT 
    COUNT(*) AS total_orders,
    COUNT(discount_code) AS orders_with_discount,    -- NULL sayılmır
    COUNT(DISTINCT user_id) AS unique_buyers,
    SUM(total) AS revenue,
    AVG(total) AS avg_order_value,
    MIN(created_at) AS first_order,
    MAX(created_at) AS last_order
FROM orders;
```

### COUNT(*) vs COUNT(col) vs COUNT(1)

```sql
-- COUNT(*) - ALL row-lar (NULL-lər də daxil)
SELECT COUNT(*) FROM users;

-- COUNT(email) - yalniz email NOT NULL olan row-lar
SELECT COUNT(email) FROM users;

-- COUNT(1) - COUNT(*) ilə eyni (sabit 1)
SELECT COUNT(1) FROM users;

-- Performans: COUNT(*), COUNT(1), COUNT(pk_column) eyni-sureti (muasir DB-lerde optimized)
-- Tovsiye: COUNT(*) en oxunaqli
```

## GROUP BY

Row-lari qrupa bölüb hər qrupa aggregate tətbiq edir.

```sql
-- Hər country üçün user sayı
SELECT country, COUNT(*) AS user_count
FROM users
GROUP BY country;

-- Çoxlu sutun ilə qruplaşdırma
SELECT country, role, COUNT(*) AS cnt
FROM users
GROUP BY country, role;

-- Aggregate + raw sutun = pitfall
SELECT country, name, COUNT(*) FROM users GROUP BY country;
-- ERROR! `name` GROUP BY-da deyil va aggregate icinde deyil
-- (MySQL default-da bunu icraya başladı - amma təhlükəli - any() row alır)
```

### GROUP BY qaydası

**Qayda:** `SELECT` list-indəki hər sütun ya `GROUP BY`-da olmalıdır, ya da aggregate içində olmalıdır.

```sql
-- DUZGUN
SELECT country, role, COUNT(*), AVG(age)
FROM users
GROUP BY country, role;

-- YANLIS (standart SQL-də)
SELECT country, name, COUNT(*)
FROM users
GROUP BY country;
-- `name` ya GROUP BY-da olmalıdır, ya da MIN(name)/MAX(name) kimi
```

**MySQL `ONLY_FULL_GROUP_BY`:** MySQL 5.7+-də default ON-dir, standart davranish tetbiq olunur.

## HAVING - Group-ları Filter Et

```
WHERE  → row-u filter edir (GROUP BY-dan EVVEL)
HAVING → group-u filter edir (GROUP BY-dan SONRA)
```

```sql
-- Ən azı 5 sifarişi olan user-lər
SELECT user_id, COUNT(*) AS order_count
FROM orders
GROUP BY user_id
HAVING COUNT(*) >= 5;

-- WHERE vs HAVING
SELECT user_id, COUNT(*) AS paid_orders
FROM orders
WHERE status = 'paid'           -- row-u filter: yalniz paid-lər
GROUP BY user_id
HAVING COUNT(*) >= 3;           -- group-u filter: 3+ paid sifaris
```

### WHERE-də aggregate YAZMA!

```sql
-- YANLIS - WHERE aggregate dəstəkləmir
SELECT user_id, COUNT(*) 
FROM orders 
WHERE COUNT(*) > 5               -- ERROR!
GROUP BY user_id;

-- DOGRU - HAVING istifade et
SELECT user_id, COUNT(*) 
FROM orders 
GROUP BY user_id
HAVING COUNT(*) > 5;
```

### HAVING alias-ları dəstəkləyir (bəzi DB-lərdə)

```sql
-- PostgreSQL: HAVING-də alias işləmir
SELECT user_id, COUNT(*) AS cnt
FROM orders
GROUP BY user_id
HAVING COUNT(*) > 5;             -- OK
HAVING cnt > 5;                  -- ERROR PostgreSQL-də!

-- MySQL: HAVING-də alias işləyir
HAVING cnt > 5;                  -- OK MySQL-də

-- Portable: aggregate-in ozunu yaz
HAVING COUNT(*) > 5;
```

## GROUP BY + ORDER BY

```sql
-- Ən çox sifaris veren ilk 10 user
SELECT user_id, COUNT(*) AS order_count
FROM orders
GROUP BY user_id
HAVING COUNT(*) > 0
ORDER BY order_count DESC
LIMIT 10;
```

## Filtered Aggregates (FILTER CLAUSE)

PostgreSQL 9.4+ güclü future - `CASE WHEN` alternativi.

```sql
-- PostgreSQL: FILTER clause
SELECT 
    COUNT(*) AS total,
    COUNT(*) FILTER (WHERE status = 'paid') AS paid_count,
    COUNT(*) FILTER (WHERE status = 'failed') AS failed_count,
    SUM(total) FILTER (WHERE status = 'paid') AS revenue
FROM orders;

-- MySQL: CASE WHEN ile əvəz olunur
SELECT 
    COUNT(*) AS total,
    COUNT(CASE WHEN status = 'paid' THEN 1 END) AS paid_count,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) AS failed_count,
    SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) AS revenue
FROM orders;
```

## Pivot (çevirmə)

`CASE WHEN` + `GROUP BY` = sutun-a-row çevirmə.

```sql
-- Hər user-in her status-unda neçə sifarişi var
SELECT 
    user_id,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
    COUNT(CASE WHEN status = 'paid' THEN 1 END) AS paid,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled
FROM orders
GROUP BY user_id;
```

## SQL Execution Order (başa düşmək vacibdir)

Yazilma sirasi:
```sql
SELECT ... FROM ... WHERE ... GROUP BY ... HAVING ... ORDER BY ... LIMIT ...
```

**İcra sırası (logic flow):**
```
1. FROM       → table secmek
2. WHERE      → row-ları filter et
3. GROUP BY   → row-ları qrupa böl
4. HAVING     → grup-ları filter et
5. SELECT     → sütunları seç (və aggregate)
6. DISTINCT   → tekrarları sil
7. ORDER BY   → sırala
8. LIMIT      → sahəni kəs
```

Niyə vacibdir? Məs:
- WHERE-də aggregate olmaz (SELECT-dən əvvəl işləyir)
- ORDER BY-da SELECT alias istifade olunur (SELECT-dən sonra)

## Laravel Nümunəsi

```php
// COUNT
User::count();
User::where('active', 1)->count();

// SUM, AVG, MIN, MAX
Order::sum('total');
Order::where('status', 'paid')->sum('total');
Order::avg('total');

// GROUP BY
$byCountry = User::select('country', DB::raw('COUNT(*) AS total'))
    ->groupBy('country')
    ->get();

// HAVING
$activeUsers = DB::table('orders')
    ->select('user_id', DB::raw('COUNT(*) AS order_count'))
    ->groupBy('user_id')
    ->having('order_count', '>=', 5)
    ->get();

// Pivot
$stats = DB::table('orders')
    ->select('user_id',
        DB::raw("COUNT(CASE WHEN status='paid' THEN 1 END) AS paid"),
        DB::raw("COUNT(CASE WHEN status='failed' THEN 1 END) AS failed"))
    ->groupBy('user_id')
    ->get();
```

## Interview Sualları

**Q: `COUNT(*)` vs `COUNT(col)` fərqi?**
A: `COUNT(*)` bütün row-ları sayır (NULL daxil). `COUNT(col)` yalnız `col IS NOT NULL` olan row-ları sayır. `COUNT(DISTINCT col)` unique dəyər sayını qaytarır.

**Q: `WHERE` və `HAVING` fərqi?**
A: `WHERE` row-ları qruplaşdırmadan əvvəl filter edir. `HAVING` qruplaşdırmadan sonra group-ları filter edir. WHERE aggregate istifadə edə bilməz, HAVING edə bilər.

**Q: `SUM(NULL + 5)` nə qaytarır?**
A: NULL + 5 = NULL. Aggregate-lər NULL-ləri ignorlayır, ona görə həmin row cəmə əlavə olunmayacaq, amma digər row-lar hesablanacaq.

**Q: `AVG()` NULL-ləri necə hesablayır?**
A: NULL-lər ignorlanır. `AVG(col) = SUM(col) / COUNT(col)` - hər ikisi NULL-dən məhrum. Əgər NULL-ləri 0 kimi saymaq istəyirsənsə: `AVG(COALESCE(col, 0))`.
