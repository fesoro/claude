# SELECT & Projection Basics (Junior)

## SELECT nedir?

`SELECT` - SQL-in en cox istifade olunan komandasidir. Table-dan row-lari **oxuyur** ve qaytarir. **Projection** demek - hansi sutunlarin qaytarilacagini secmek.

```sql
-- Butun sutunlar (production-da istifade etme!)
SELECT * FROM users;

-- Yalniz lazim olan sutunlar (Best Practice)
SELECT id, name, email FROM users;
```

### Niye `SELECT *` pisdir?

```
1. Shebeke trafiki artir (lazimsiz data gelir)
2. Index-only scan itir (her sutun lazim olduqca table-a getmeli olur)
3. Schema deyisdikde kod sinir (yeni sutun elave olunanda app crash ede biler)
4. ORM hydration bahasi artir (lazimsiz property-ler doldurulur)
```

**Qayda:** Production query-lerde her zaman **lazim olan sutunlari** yaz.

## Column Aliases (AS)

Sutun adlarini rename edir (netice set-i ucun, table dayismir).

```sql
-- AS acar sozu
SELECT 
    name AS full_name,
    email AS contact_email,
    created_at AS registered_on
FROM users;

-- AS olmadan (optional-dir, amma oxunmagi azaldir)
SELECT name full_name, email contact_email FROM users;

-- Bosluqlu alias (double-quote PostgreSQL, backtick MySQL)
SELECT name AS "Full Name" FROM users;          -- PostgreSQL
SELECT name AS `Full Name` FROM users;          -- MySQL
```

**Niye lazim?**
- JOIN-lerde eyni adli sutunlari ayird etmek ucun
- Hesablanmis sutunlara mena vermek (`price * 1.18 AS price_with_tax`)
- ORM / app tarafinda property adlari ile uyumlashdirmaq

## Column Expressions

Sutunlarda **hesablama** etmek olar. Netice set-i ucun virtual sutun yaradir.

```sql
-- Arithmetic
SELECT 
    product_name,
    price,
    price * 1.18 AS price_with_vat,
    price * quantity AS total
FROM products;

-- String concat (PostgreSQL - || operator)
SELECT first_name || ' ' || last_name AS full_name FROM users;

-- String concat (MySQL - CONCAT funksiyasi)
SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users;

-- Boolean hesablama
SELECT 
    id,
    age,
    age >= 18 AS is_adult          -- PostgreSQL (boolean)
FROM users;
```

## DISTINCT - Tekrari Sil

Netice set-inde **duplicate row-lari** silir.

```sql
-- Unique email-ler
SELECT DISTINCT email FROM users;

-- Unique (department, role) combination-lari
SELECT DISTINCT department, role FROM employees;

-- DISTINCT butun SELECT listine tetbiq olunur, tek sutuna yox!
SELECT DISTINCT name, email FROM users;
-- (name='Ali', email='ali@x.com') ve (name='Ali', email='ali@y.com') HER IKISI gorunur
```

### DISTINCT ON (PostgreSQL)

PostgreSQL-de xususi syntax - yalniz bir sutun uzre tekrari silmek.

```sql
-- Her user-in en son order-i
SELECT DISTINCT ON (user_id) 
    user_id, order_id, created_at
FROM orders
ORDER BY user_id, created_at DESC;

-- MySQL-de bu yoxdur, window function ile etmek lazimdir
```

### DISTINCT vs GROUP BY

```sql
-- Bu iki query eyni neticeni verir:
SELECT DISTINCT department FROM employees;
SELECT department FROM employees GROUP BY department;

-- Query planner ikisini de eyni cur optimize edir (muasir DB-lerde)
-- DISTINCT daha oxunaqli-dir ne zaman yalniz unique deyer lazimdir
```

## Subqueries SELECT icinde (Scalar)

Scalar subquery - 1 row 1 sutun qaytarir, deyer kimi istifade olunur.

```sql
SELECT 
    u.name,
    u.email,
    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
    (SELECT MAX(created_at) FROM orders o WHERE o.user_id = u.id) AS last_order_at
FROM users u;
```

**Diqqet:** Hor row ucun subquery ishleyir - N+1 problemi! JOIN ile yaz:

```sql
-- Daha yaxsi:
SELECT 
    u.name,
    u.email,
    COUNT(o.id) AS order_count,
    MAX(o.created_at) AS last_order_at
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
GROUP BY u.id, u.name, u.email;
```

## CASE ifadesi SELECT icinde

Shart-e gore deyer qaytarir (if-else-else if ekvivalenti).

```sql
SELECT 
    name,
    age,
    CASE
        WHEN age < 18 THEN 'minor'
        WHEN age < 65 THEN 'adult'
        ELSE 'senior'
    END AS age_group
FROM users;
```

Daha ətraflı `08-case-expressions.md`.

## Literal ve Constant Deyerler

```sql
-- Sabit deyerler
SELECT 
    id,
    name,
    'active' AS status,          -- Hor row ucun 'active'
    1 AS version,
    NOW() AS queried_at          -- Current timestamp
FROM users;

-- Expression-lardan row olusdur (table-siz)
SELECT 1 + 1 AS result;
SELECT NOW() AS current_time;
SELECT 'hello' AS greeting;
```

## Laravel ile PHP Backend Nümunəsi

```php
// Raw SELECT - yalniz lazim olan sutunlar
$users = DB::select('SELECT id, name, email FROM users WHERE active = 1');

// Query Builder - select() metodu
$users = DB::table('users')
    ->select('id', 'name', 'email')
    ->where('active', 1)
    ->get();

// Eloquent - select() metodu
$users = User::select('id', 'name', 'email')->active()->get();

// Alias ile
$users = DB::table('users')
    ->select('id', 'name AS full_name', DB::raw('price * 1.18 AS price_with_vat'))
    ->get();

// DISTINCT
$departments = DB::table('employees')->distinct()->pluck('department');
```

## Interview Sualları

**Q: `SELECT *` production-da niye pisdir?**
A: 
1. Lazimsiz sutunlar shebekeden kecir (bandwidth)
2. Covering index itir - query planner her sutun ucun table-a getmeli olur
3. Schema evolution zamani kod sinir (`ALTER TABLE ADD COLUMN` sonrasi ORM hydration)
4. ORM-de lazimsiz field-ler hydrate olunur (RAM + CPU)

**Q: DISTINCT vs GROUP BY fərqi nədir?**
A: Funksional olaraq eynidir (unique deyerler qaytarir). Muasir DB-lerde eyni plan yaranir. Oxunma nøqteyinden DISTINCT unique deyerler ucun, GROUP BY aqreqasiya ilə birlikdə istifade olunur.

**Q: Column alias-da `AS` keyword-u mecburidir?**
A: Xeyir, əksər DB-lerde optional-dir: `SELECT name full_name FROM users` işləyir. Amma `AS` yazilmasi oxunma ucun tovsiye olunur.
