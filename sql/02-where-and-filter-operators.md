# WHERE Clause & Filter Operators (Junior)

`WHERE` - row-lari **filter** edir. SELECT/UPDATE/DELETE-de işləyir. En çox səhv burada olur (index istifade olunmur, NULL pitfall-i, operator secimi).

## Muqayise Operatorlari

```sql
SELECT * FROM products WHERE price = 100;        -- berabor
SELECT * FROM products WHERE price <> 100;       -- berabor deyil (standard)
SELECT * FROM products WHERE price != 100;       -- eyni (PostgreSQL/MySQL)
SELECT * FROM products WHERE price > 100;
SELECT * FROM products WHERE price >= 100;
SELECT * FROM products WHERE price < 100;
SELECT * FROM products WHERE price <= 100;
```

**Qayda:** `<>` standard SQL, `!=` de eksər DB-lerdə işləyir - ferqi yoxdur.

## AND / OR / NOT

```sql
-- AND - her iki shert true olmalidir
SELECT * FROM users 
WHERE age >= 18 AND country = 'AZ';

-- OR - en az biri true olmalidir
SELECT * FROM users 
WHERE role = 'admin' OR role = 'owner';

-- NOT - shert-i inversia edir
SELECT * FROM users WHERE NOT active;
SELECT * FROM users WHERE NOT (age < 18);

-- Kompleks - MUTLEQ parantez istifade et!
SELECT * FROM users 
WHERE (country = 'AZ' OR country = 'TR') 
  AND age >= 18;
```

### AND/OR operator precedence

```sql
-- Parantez olmasa YANLIS netice:
SELECT * FROM users 
WHERE country = 'AZ' OR country = 'TR' AND age >= 18;
-- Aslinda su sekilde parse olunur:
-- country = 'AZ' OR (country = 'TR' AND age >= 18)
-- Azerbaycandan butun user-ler gelir (yashindan asili olmayaraq!)

-- DOGRU:
SELECT * FROM users 
WHERE (country = 'AZ' OR country = 'TR') AND age >= 18;
```

**Qayda:** Tereddudun varsa, parantez elave et.

## IN / NOT IN

`OR`-in qisa yazilmasi. **Sabit list** ucun.

```sql
-- Bunlar eynidir:
SELECT * FROM users WHERE country = 'AZ' OR country = 'TR' OR country = 'RU';
SELECT * FROM users WHERE country IN ('AZ', 'TR', 'RU');

-- NOT IN
SELECT * FROM users WHERE country NOT IN ('AZ', 'TR');

-- Subquery ile
SELECT * FROM orders 
WHERE user_id IN (SELECT id FROM users WHERE country = 'AZ');
```

### NOT IN ve NULL TƏLƏsi

```sql
-- NULL olan list-de NOT IN her zaman UNKNOWN qaytarir!
SELECT * FROM users WHERE id NOT IN (1, 2, NULL);
-- Netice: hecne qaytarmaz (NULL 3-valued logic-ine gore)

-- DOGRU: NOT EXISTS istifade et
SELECT * FROM users u
WHERE NOT EXISTS (
    SELECT 1 FROM banned_users b WHERE b.user_id = u.id
);
```

## BETWEEN

Interval filter-i. **Hem baslangic, hem son daxildir** (inclusive).

```sql
-- Qiymet 100-200 arasinda (ikisi de daxil)
SELECT * FROM products WHERE price BETWEEN 100 AND 200;

-- Ekvivalent:
SELECT * FROM products WHERE price >= 100 AND price <= 200;

-- Tarix araliqi
SELECT * FROM orders 
WHERE created_at BETWEEN '2026-01-01' AND '2026-12-31';

-- NOT BETWEEN
SELECT * FROM products WHERE price NOT BETWEEN 100 AND 200;
```

**Diqqet:** Tarix-zaman (`TIMESTAMP`) ucun BETWEEN-in gizli problemi:

```sql
-- YANLIS - 2026-12-31-in yalniz 00:00:00 hissəsini dax edir
WHERE created_at BETWEEN '2026-01-01' AND '2026-12-31';
-- 2026-12-31 14:30 = DAX DEYIL!

-- DOGRU:
WHERE created_at >= '2026-01-01' 
  AND created_at < '2027-01-01';

-- Va ya:
WHERE created_at BETWEEN '2026-01-01 00:00:00' AND '2026-12-31 23:59:59';
```

## LIKE - Pattern Matching

**Wildcard** ile axtaris. `%` = 0 və ya daha çox simvol, `_` = tek simvol.

```sql
-- "a" ilə baslayan
SELECT * FROM users WHERE name LIKE 'a%';

-- "son" ile bitеn
SELECT * FROM users WHERE name LIKE '%son';

-- "ali" icherеn
SELECT * FROM users WHERE name LIKE '%ali%';

-- Eynən 5 simvollu
SELECT * FROM users WHERE name LIKE '_____';

-- 3-cu simvolu 'a'
SELECT * FROM users WHERE name LIKE '__a%';

-- Escape - haqiqi % və ya _ axtarmaq
SELECT * FROM products WHERE name LIKE '%50\%%' ESCAPE '\';   -- "50%" string-ini axtarir
```

### LIKE case-sensitivity

```sql
-- PostgreSQL: default case-sensitive
SELECT * FROM users WHERE name LIKE 'ali%';
-- "Ali" tapmaz

-- PostgreSQL: ILIKE (case-insensitive)
SELECT * FROM users WHERE name ILIKE 'ali%';
-- "Ali", "ALI", "ali" hamisini tapir

-- MySQL: default-da collation-dan asilidir
-- utf8mb4_general_ci = case-insensitive
-- utf8mb4_bin = case-sensitive
```

### LIKE və Index

```sql
-- BAD: index ishlemir ('%ali%' prefix yoxdur)
WHERE name LIKE '%ali%';

-- GOOD: prefix index istifade edir
WHERE name LIKE 'ali%';

-- Icheri axtaris lazimdirsa:
-- PostgreSQL: GIN index with pg_trgm
-- MySQL: Full-text index (MATCH AGAINST)
```

## IS NULL / IS NOT NULL

NULL muqayise ucun **= və ya <> YOX**, **IS NULL / IS NOT NULL** istifade et.

```sql
-- YANLIS - her zaman hecne qaytarmaz
SELECT * FROM users WHERE email = NULL;        -- Boş netice!
SELECT * FROM users WHERE email <> NULL;       -- Boş netice!

-- DOGRU
SELECT * FROM users WHERE email IS NULL;
SELECT * FROM users WHERE email IS NOT NULL;
```

NULL haqqinda daha ətraflı: `09-null-handling-coalesce.md`.

## Regex Matching

```sql
-- PostgreSQL: ~ (case-sensitive), ~* (case-insensitive)
SELECT * FROM users WHERE email ~ '@gmail\.com$';
SELECT * FROM users WHERE email ~* '@GMAIL';

-- MySQL 8+: REGEXP (REGEXP_LIKE)
SELECT * FROM users WHERE email REGEXP '@gmail\\.com$';
SELECT * FROM users WHERE REGEXP_LIKE(email, '@gmail\\.com$', 'i');
```

## Hesablanmis Sutunlar (Index Pitfall)

```sql
-- BAD: funksiya sutuna tetbiq olunur, index ishlemir
WHERE YEAR(created_at) = 2026;
WHERE LOWER(email) = 'ali@x.com';
WHERE price * 1.18 > 100;

-- GOOD: range query, index ishleyir
WHERE created_at >= '2026-01-01' AND created_at < '2027-01-01';
WHERE email = 'ali@x.com'          -- case-insensitive collation istifade et
WHERE price > 100 / 1.18;

-- Alternativi: **Functional Index**
CREATE INDEX idx_users_email_lower ON users (LOWER(email));
WHERE LOWER(email) = 'ali@x.com'    -- indi index ishleyir
```

## Laravel Nümunəsi

```php
// Basic where
User::where('age', '>=', 18)->get();
User::where('country', 'AZ')->get();

// AND shert-i zingir
User::where('age', '>=', 18)
    ->where('country', 'AZ')
    ->get();

// OR
User::where('role', 'admin')
    ->orWhere('role', 'owner')
    ->get();

// IN
User::whereIn('country', ['AZ', 'TR', 'RU'])->get();
User::whereNotIn('status', ['banned', 'deleted'])->get();

// BETWEEN
Order::whereBetween('created_at', ['2026-01-01', '2026-12-31'])->get();

// LIKE
User::where('email', 'like', '%@gmail.com')->get();

// NULL
User::whereNull('deleted_at')->get();
User::whereNotNull('email_verified_at')->get();

// Grup - parantez ekvivalenti
User::where(function ($q) {
    $q->where('country', 'AZ')->orWhere('country', 'TR');
})->where('age', '>=', 18)->get();
```

## Interview Sualları

**Q: `NOT IN (1, 2, NULL)` niye boş netice qaytarir?**
A: SQL-de NULL 3-valued logic istifade edir (TRUE/FALSE/UNKNOWN). `x != NULL` her zaman UNKNOWN-dir, deməli `NOT IN` UNKNOWN-u false kimi qiymetlendirir va ROW filtered out. Həll: `NOT EXISTS` istifade et.

**Q: `WHERE YEAR(created_at) = 2026` ne ucun slow-dur?**
A: `YEAR()` funksiyasi hər row-da çağrılır, sutunun B-Tree index-i istifade edilə bilmir (functional expression-dir). Range query `created_at >= '2026-01-01' AND < '2027-01-01'` index-i istifade edir.

**Q: `WHERE name LIKE '%ali%'` optimize olunarmı?**
A: Prefix olmadigindan B-Tree index bosh istifade olunmur. Həll: PostgreSQL `pg_trgm` + GIN index və ya Elasticsearch/Meilisearch kimi full-text search engine.

**Q: `BETWEEN '2026-01-01' AND '2026-12-31'` TIMESTAMP sutunu ucun nə üçün təhlükəlidir?**
A: `'2026-12-31'` = `2026-12-31 00:00:00` olaraq cast olunur - günün qalan 23 saati itir. Həll: `>= '2026-01-01' AND < '2027-01-01'`.
