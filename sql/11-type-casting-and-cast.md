# Type Casting & CAST (Junior)

Bir data type-ı digərinə çevirmək. SQL-də **çox istifadə olunur** — amma səhv istifadəsi index-i öldürə və yanlış nəticəyə səbəb ola bilər.

## CAST Syntax

### 1. Standard SQL: CAST

```sql
-- CAST(expression AS type)
SELECT CAST('123' AS INTEGER);             -- 123
SELECT CAST(123 AS VARCHAR);               -- '123'
SELECT CAST('2026-04-24' AS DATE);         -- 2026-04-24
SELECT CAST(price AS DECIMAL(10, 2)) FROM products;
```

### 2. PostgreSQL: `::` operator

```sql
-- Shorthand
SELECT '123'::INTEGER;
SELECT '2026-04-24'::DATE;
SELECT id::TEXT FROM users;

-- Composite
SELECT (price * 1.18)::NUMERIC(10, 2) AS price_with_vat FROM products;
```

### 3. MySQL: CONVERT() 

```sql
SELECT CONVERT('123', SIGNED INTEGER);     -- 123
SELECT CONVERT('123', UNSIGNED);
SELECT CONVERT('2026-04-24', DATE);

-- CAST də işləyir
SELECT CAST('123' AS SIGNED);              -- MySQL-də INTEGER yox, SIGNED/UNSIGNED
```

## Implicit vs Explicit Cast

```sql
-- Implicit - DB avtomatik çevirir
SELECT '5' + 3;                 -- PostgreSQL: 8 (int cast), MySQL: 8
SELECT '5' = 5;                 -- TRUE (hər ikisi int-ə çevrilir)
SELECT 1 + '5abc';              -- MySQL: 6 (warning), PostgreSQL: ERROR

-- Explicit - sən deyirsən
SELECT CAST('5' AS INT) + 3;    -- həmişə dəqiq
```

**Qayda:** Implicit cast-a güvənmə — DB-dən DB-yə fərqlidir. **Explicit yaz**.

## Tez-Tez İstifadə Olunan Cast-lar

### String ↔ Integer

```sql
-- String → Int
SELECT '42'::INTEGER;                      -- PostgreSQL
SELECT CAST('42' AS SIGNED);               -- MySQL
SELECT CAST('42' AS INTEGER);              -- Standard

-- Int → String
SELECT 42::TEXT;                           -- PostgreSQL
SELECT CAST(42 AS CHAR);                   -- MySQL
```

### String → Date/Timestamp

```sql
-- PostgreSQL
SELECT '2026-04-24'::DATE;
SELECT '2026-04-24 14:30:00'::TIMESTAMP;
SELECT '2026-04-24 14:30:00 UTC'::TIMESTAMPTZ;

-- MySQL
SELECT STR_TO_DATE('2026-04-24', '%Y-%m-%d');
SELECT CAST('2026-04-24' AS DATE);

-- PostgreSQL: explicit format
SELECT TO_DATE('24/04/2026', 'DD/MM/YYYY');
SELECT TO_TIMESTAMP('24-04-2026 14:30', 'DD-MM-YYYY HH24:MI');
```

### Decimal / Float

```sql
-- PostgreSQL
SELECT 10.5::DECIMAL(10, 2);
SELECT '10.5'::NUMERIC;

-- MySQL
SELECT CAST(10.5 AS DECIMAL(10, 2));
SELECT CAST('10.5' AS DECIMAL(10, 2));
```

### Boolean

```sql
-- PostgreSQL
SELECT 'true'::BOOLEAN;
SELECT 1::BOOLEAN;                         -- true
SELECT 0::BOOLEAN;                         -- false

-- MySQL (BOOLEAN = TINYINT(1))
SELECT CAST(1 AS SIGNED);                  -- sadəcə int
-- Yox, MySQL-də birbaşa bool cast yox
```

### JSON

```sql
-- PostgreSQL: TEXT → JSONB
SELECT '{"name": "Ali"}'::JSONB;

-- MySQL: TEXT → JSON
SELECT CAST('{"name": "Ali"}' AS JSON);
```

## CAST Index-i öldürür (MƏHƏM!)

```sql
-- BAD: col-a cast tətbiq olunur - index işləmir
WHERE CAST(id AS TEXT) = '123';
WHERE LOWER(email) = 'ali@x.com';
WHERE DATE(created_at) = '2026-04-24';

-- GOOD: sabit dəyərə cast et
WHERE id = 123;
WHERE email = 'ali@x.com';        -- collation case-insensitive istifadə et
WHERE created_at >= '2026-04-24' AND created_at < '2026-04-25';
```

**Qayda:** Cast-ı **sabit dəyərə** tətbiq et, sütuna yox. Yoxsa B-Tree index istifadə olunmur.

## Functional Index — Cast Lazımdırsa

```sql
-- PostgreSQL: functional index
CREATE INDEX idx_email_lower ON users (LOWER(email));
-- Indi WHERE LOWER(email) = 'ali@x.com' fast olur

-- MySQL 8+: functional index
CREATE INDEX idx_email_lower ON users ((LOWER(email)));

-- Generated column (MySQL 5.7+, PostgreSQL 12+)
ALTER TABLE users ADD email_lower VARCHAR(255) 
    GENERATED ALWAYS AS (LOWER(email)) STORED;
CREATE INDEX idx_email_lower ON users(email_lower);
```

## String-də Rəqəm Sort Pitfall

```sql
-- VARCHAR sutunda rəqəm var ama ORDER BY string kimi sıralayır
INSERT INTO t (code) VALUES ('1'), ('2'), ('10'), ('20');
SELECT * FROM t ORDER BY code;
-- Nəticə: '1', '10', '2', '20' (string sort!)

-- DOĞRU: numeric-ə cast et
SELECT * FROM t ORDER BY CAST(code AS INTEGER);
-- Nəticə: '1', '2', '10', '20'
```

## Implicit Cast ve Index

```sql
-- Column VARCHAR, deyər int - implicit cast olur
SELECT * FROM users WHERE phone = 123456789;
-- Bezi DB-lərdə: phone sutununa CAST tətbiq olunur (index itir!)

-- DOĞRU
SELECT * FROM users WHERE phone = '123456789';

-- Qayda: dəyər tipi sütunun tipi ilə uyğun olsun
```

## Cast Səhvləri (Runtime Error)

```sql
-- PostgreSQL: strict cast - səhv format error verir
SELECT 'abc'::INTEGER;
-- ERROR: invalid input syntax for type integer

-- MySQL: leniency - implicit cast, warning verir amma davam edir
SELECT CAST('abc' AS SIGNED);              -- 0 qaytarir, warning verir

-- PostgreSQL: safe cast üçün
SELECT CASE 
    WHEN col ~ '^[0-9]+$' THEN col::INTEGER
    ELSE NULL
END FROM t;
```

## Numeric Dəqiqlik Itirmə

```sql
-- Double → Decimal (dəqiqlik itə bilər)
SELECT CAST(0.1 + 0.2 AS DECIMAL(10, 2));
-- Nəticə: 0.30 (yuvarlaqlaşdı)

-- Amma:
SELECT 0.1 + 0.2;
-- Bəzi DB-lərdə: 0.30000000000000004

-- Decimal → Int (kəsr itir)
SELECT CAST(10.7 AS INTEGER);              -- 10 (truncate) və ya 11 (round) DB-dən asılıdır
-- PostgreSQL: 11 (round)
-- MySQL: 11 (round)
-- Oracle: 11
-- SQL Server: 11
```

## Timezone Cast

```sql
-- PostgreSQL: timestamp → timestamptz
SELECT '2026-04-24 14:30:00'::TIMESTAMP AT TIME ZONE 'UTC';
-- 2026-04-24 14:30:00+00 (UTC olaraq yerləşdirildi)

-- Başqa timezone-a çevir
SELECT created_at AT TIME ZONE 'Asia/Baku' FROM orders;
```

## Array və JSON Cast (PostgreSQL)

```sql
-- JSON → Array
SELECT '["a","b","c"]'::JSONB -> 0;        -- "a" (JSONB element)

-- Array → JSON
SELECT array_to_json(ARRAY[1,2,3]);

-- Text → Array
SELECT '{1,2,3}'::INTEGER[];
SELECT string_to_array('a,b,c', ',');
```

## Laravel Nümunəsi

```php
// Laravel casts (Eloquent)
class Order extends Model 
{
    protected $casts = [
        'total' => 'decimal:2',
        'metadata' => 'array',            // JSON → array automatic
        'is_paid' => 'boolean',
        'created_at' => 'datetime',
        'status' => OrderStatus::class,   // Enum cast (PHP 8.1+)
    ];
}

// SQL-də cast
DB::table('users')
    ->select(DB::raw('CAST(phone AS UNSIGNED) AS phone_num'))
    ->orderByRaw('CAST(phone AS UNSIGNED)')
    ->get();
```

## Interview Sualları

**Q: `WHERE CAST(id AS TEXT) = '123'` niyə slow-dur?**
A: CAST sutununun hər row-unda tətbiq olunur, B-Tree index istifadə edilə bilmir. Həll: dəyəri cast et: `WHERE id = 123`.

**Q: MySQL-də `'5' + 3` nə qaytarır?**
A: `8`. MySQL implicit cast edir - string-i int-ə çevirir. PostgreSQL isə ERROR qaytarır (strict cast).

**Q: `CAST(0.1 + 0.2 AS DECIMAL)` dəqiq `0.3` qaytarırmı?**
A: Əvvəlcə FLOAT hesablama baş verir (`0.30000000000000004`), sonra DECIMAL-ə cast olunur (yuvarlaqlaşdırılır). Əgər dəqiqlik lazımdırsa, hesablamanı DECIMAL-də et: `CAST(0.1 AS DECIMAL) + CAST(0.2 AS DECIMAL)`.

**Q: PostgreSQL-də `::` və `CAST` fərqi?**
A: Funksional fərq yoxdur — eyni effekti verir. `::` PostgreSQL-specific shorthand-dir. Cross-DB kod üçün `CAST(x AS type)` istifadə et.

**Q: `LOWER(email)` col-a tətbiq olunursa necə index edəsən?**
A: **Functional index**: `CREATE INDEX idx_email_lower ON users (LOWER(email))`. Və ya **generated column** + normal index.
