# NULL Handling, COALESCE, NULLIF (Junior)

NULL — SQL-in ən **təhlükəli** hissələrindən biridir. Yanlış başa düşülməsi səhv nəticələrə və tapılması çətin bug-lara səbəb olur.

## NULL NƏDIR?

NULL = **yoxluq, naməlum**. "Boş" və ya "0" deyil!

```sql
-- Bu üç dəyər FƏRQLİdir:
INSERT INTO users (name, age) VALUES 
    ('Ali', NULL),       -- age bilinmir
    ('Veli', 0),         -- age = 0 (bu məlumdur)
    ('Orkhan', '');      -- age boş string (invalid - tip səhvi)
```

## Three-Valued Logic (3VL)

Boolean nəticə 3 ola bilər: `TRUE`, `FALSE`, **`UNKNOWN`** (NULL).

| Şərt | Nəticə |
|------|--------|
| `5 = 5` | TRUE |
| `5 = 6` | FALSE |
| `5 = NULL` | UNKNOWN (!) |
| `NULL = NULL` | UNKNOWN (!) |
| `NULL IS NULL` | TRUE |

**Kritik:** `NULL = NULL` **FALSE DEYİL**, **UNKNOWN**-dir!

### AND / OR cədvəli (NULL ilə)

```
AND:
  TRUE  AND NULL = NULL
  FALSE AND NULL = FALSE
  NULL  AND NULL = NULL

OR:
  TRUE  OR NULL = TRUE
  FALSE OR NULL = NULL
  NULL  OR NULL = NULL

NOT:
  NOT NULL = NULL
```

### WHERE-in NULL ilə davranışı

WHERE yalnız `TRUE` olan row-ları qaytarır. `FALSE` və `UNKNOWN` row-ları **ignorlanır**.

```sql
-- Bu query heç vaxt heç nə qaytarmaz:
SELECT * FROM users WHERE email = NULL;
-- email = NULL → UNKNOWN → row filtered out

-- DOĞRU:
SELECT * FROM users WHERE email IS NULL;
SELECT * FROM users WHERE email IS NOT NULL;
```

## NULL ilə Müqayisə

```sql
-- YANLIŞ
WHERE col = NULL            -- həmişə UNKNOWN
WHERE col != NULL           -- həmişə UNKNOWN
WHERE col <> NULL           -- həmişə UNKNOWN

-- DOĞRU
WHERE col IS NULL
WHERE col IS NOT NULL

-- İki sütunu müqayisə: hər ikisi NULL olsa da...
WHERE col_a = col_b         -- hər ikisi NULL-dırsa UNKNOWN
```

## `IS [NOT] DISTINCT FROM` — NULL-Safe Müqayisə

```sql
-- PostgreSQL: NULL-ləri bərabər sayır
WHERE col_a IS NOT DISTINCT FROM col_b
-- (NULL, NULL)  → TRUE
-- (1, 1)        → TRUE
-- (1, NULL)     → FALSE
-- (1, 2)        → FALSE

-- MySQL: <=> NULL-safe operator
WHERE col_a <=> col_b
```

## COALESCE — İlk NOT NULL

```sql
-- Birinci NOT NULL dəyəri qaytarır
SELECT COALESCE(phone, email, 'N/A') FROM contacts;

-- NULL olmayanda default ver
SELECT COALESCE(last_login, '2000-01-01') FROM users;

-- Hesablamaya NULL daxil olmasın
SELECT price + COALESCE(discount, 0) FROM products;
-- discount NULL olsa da, cəm NULL olmayacaq
```

### COALESCE vs ISNULL vs NVL

| DB | Funksiya |
|----|----------|
| Standard SQL | `COALESCE(a, b, c)` |
| MySQL | `COALESCE()` və ya `IFNULL(a, b)` (2 arg) |
| PostgreSQL | `COALESCE()` |
| SQL Server | `COALESCE()` və ya `ISNULL(a, b)` (2 arg) |
| Oracle | `COALESCE()` və ya `NVL(a, b)` (2 arg) |

**Qayda:** `COALESCE` portable-dir, onu istifadə et.

## NULLIF — Bərabərdirsə NULL

```sql
-- a == b → NULL, əks halda a
SELECT NULLIF(a, b) FROM t;

-- Klassik istifadə: zero-division qarşısını al
SELECT total / NULLIF(quantity, 0) FROM orders;
-- quantity=0 olanda NULL qaytarir (error əvəzinə)
```

## NULL ve Aggregate

```sql
-- NULL ignorlanir (COUNT(*) xaric)
SELECT 
    COUNT(*),            -- bütün row-lar (NULL daxil)
    COUNT(email),        -- email IS NOT NULL olan row-lar
    SUM(discount),       -- NULL ignorlanir
    AVG(discount)        -- NULL ignorlanir: SUM/COUNT
FROM orders;

-- Bütün dəyərlər NULL olsa:
SELECT SUM(col) FROM t WHERE col IS NULL;
-- Nəticə: NULL (0 yox!)

-- NULL-u 0 kimi sayaq:
SELECT COALESCE(SUM(col), 0) FROM t;
```

## NULL ve GROUP BY

```sql
-- NULL-lar bir qrupda yığılır
SELECT status, COUNT(*) FROM orders GROUP BY status;
-- status: 'paid', 'pending', 'cancelled', NULL (ayrı qrup)
```

## NULL ve ORDER BY

```sql
-- PostgreSQL: ASC-də NULL sonda, DESC-də başda
SELECT * FROM users ORDER BY last_login;              -- NULL sonda
SELECT * FROM users ORDER BY last_login DESC;         -- NULL başda

-- MySQL: ASC-də NULL başda, DESC-də sonda
SELECT * FROM users ORDER BY last_login;              -- NULL başda!

-- Portable explicit:
ORDER BY last_login ASC NULLS LAST
ORDER BY last_login DESC NULLS FIRST
```

## NULL ve JOIN

```sql
-- LEFT JOIN-da NULL görünür
SELECT u.name, o.order_number
FROM users u
LEFT JOIN orders o ON u.id = o.user_id;
-- Sifarişi olmayan user üçün o.order_number NULL

-- NULL ilə JOIN şərti həmişə FALSE-dir
SELECT * 
FROM a JOIN b ON a.col = b.col;
-- NULL = NULL FALSE → match olmur

-- NULL-safe JOIN üçün:
-- PostgreSQL: IS NOT DISTINCT FROM
-- MySQL: <=>
SELECT * FROM a JOIN b ON a.col <=> b.col;
```

## NULL ve Unique Index

```sql
-- Standard SQL: NULL-lər unique saymır (bir neçə NULL ola bilər)
CREATE UNIQUE INDEX idx_email ON users(email);
INSERT INTO users (email) VALUES (NULL), (NULL), (NULL);  -- OK (DB-dən asılı)

-- PostgreSQL: bir neçə NULL olmasına icazə verir (NULLS DISTINCT default)
-- PostgreSQL 15+: NULLS NOT DISTINCT (NULL-ları da unique)
CREATE UNIQUE INDEX idx_email ON users(email) NULLS NOT DISTINCT;

-- MySQL: bir neçə NULL-a icazə verir (InnoDB)

-- SQL Server: YALNIZ 1 NULL-a icazə verir (!). Filtered index istifadə et:
CREATE UNIQUE INDEX idx_email ON users(email) WHERE email IS NOT NULL;
```

## NULL ve Check Constraint

```sql
-- CHECK constraint: NULL həmişə TƏQDİM OLUNUR (keçir)
ALTER TABLE users ADD CONSTRAINT age_check CHECK (age >= 0);
INSERT INTO users (age) VALUES (NULL);   -- OK! (UNKNOWN-u check keçir)

-- NULL-i qadağan etmək üçün NOT NULL + CHECK:
ALTER TABLE users ALTER COLUMN age SET NOT NULL;
ALTER TABLE users ADD CONSTRAINT age_check CHECK (age >= 0);
```

## NULL-u Necə İstifadə Edək?

### Best Practice
```sql
-- Əksər sütunları NOT NULL et - default dəyərlə
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    discount DECIMAL(10,2),                    -- NULL OK (tətbiq olunmayıbsa)
    shipped_at TIMESTAMP,                      -- NULL OK (hələ göndərilməyib)
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### NULL Semantikasi
- `discount = NULL` → "endirim tətbiq olunmayıb"
- `discount = 0` → "endirim 0%-dir" (fərqli məna!)
- `shipped_at = NULL` → "hələ göndərilməyib"
- `shipped_at = '2026-04-24'` → "24 aprel 2026-da göndərilib"

## Laravel Nümunəsi

```php
// NULL dəyər
User::where('email', null)->get();              // BAD - email = NULL
User::whereNull('email')->get();                // GOOD - email IS NULL
User::whereNotNull('email')->get();             // GOOD - email IS NOT NULL

// COALESCE
User::select('name', DB::raw('COALESCE(phone, email, "N/A") AS contact'))->get();

// NULL-safe müqayisə
// Laravel-də birbaşa yoxdur, DB::raw ilə:
DB::table('users')->whereRaw('col1 <=> col2')->get();   // MySQL
```

## Interview Sualları

**Q: `NULL = NULL` nəticəsi nədir?**
A: `UNKNOWN` (tez-tez "NULL" də deyirlər). Boolean nöqteyi-nəzərindən nə TRUE nə də FALSE. WHERE-də bu row filter olunur.

**Q: `WHERE col != 'X'` niyə NULL olan row-ları da filter edir?**
A: `NULL != 'X'` → UNKNOWN → filter olunur. Həll: `WHERE col != 'X' OR col IS NULL`.

**Q: `COUNT(*)` və `COUNT(col)` NULL-da fərqi?**
A: `COUNT(*)` bütün row-ları sayır. `COUNT(col)` yalnız `col IS NOT NULL` olan row-ları.

**Q: `SUM()` bütün dəyərlər NULL olsa nə qaytarır?**
A: NULL (0 yox). `COALESCE(SUM(col), 0)` ilə 0 almaq olar.

**Q: NULL-lər index-də saxlanır?**
A: PostgreSQL B-Tree index-də NULL saxlanır. MySQL InnoDB də. Amma bəzi DB-lərdə (Oracle) NULL index-dən kənarda - `WHERE col IS NULL` index istifadə edə bilməz.

**Q: `NOT IN (1, 2, NULL)` niyə boş set qaytarır?**
A: `x NOT IN (1, 2, NULL)` = `x != 1 AND x != 2 AND x != NULL`. Sonuncu UNKNOWN-dir, AND ilə nəticə UNKNOWN. Həll: `NOT EXISTS`.
