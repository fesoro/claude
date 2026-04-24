# ORDER BY, LIMIT & OFFSET

> **Seviyye:** Beginner ⭐

## ORDER BY - Sıralama

Netice set-ini belli sutuna gore sıralayır.

```sql
-- Ascending (default)
SELECT * FROM users ORDER BY name;
SELECT * FROM users ORDER BY name ASC;    -- eynidir

-- Descending
SELECT * FROM users ORDER BY created_at DESC;

-- Multi-column
SELECT * FROM users ORDER BY country ASC, created_at DESC;
-- Once country uzre, sonra her country icinde created_at uzre
```

### Expression ile sıralama

```sql
-- Hesablanmis deyer uzre
SELECT * FROM products ORDER BY price * quantity DESC;

-- Function
SELECT * FROM users ORDER BY LOWER(name);

-- Alias-la (bezi DB-lerde)
SELECT id, name, price * 1.18 AS total FROM products ORDER BY total DESC;

-- Pozisiya uzre (zəhmət olmasa istifadə etmə - schema deyişəndə sınır)
SELECT id, name, price FROM products ORDER BY 3 DESC;    -- 3-cu sutun = price
```

## NULL-lərin sıralanması

NULL-ler defaultda DB-den DB-ye ferqlidir.

```sql
-- PostgreSQL: NULL DEFAULT LAST (ASC), FIRST (DESC)
SELECT * FROM users ORDER BY last_login;           -- NULL sonda
SELECT * FROM users ORDER BY last_login DESC;      -- NULL evvelde

-- MySQL: NULL DEFAULT FIRST (ASC), LAST (DESC)
SELECT * FROM users ORDER BY last_login;           -- NULL evvelde (!)

-- Explicit kontrol (PostgreSQL + MySQL 8+)
SELECT * FROM users ORDER BY last_login ASC NULLS LAST;
SELECT * FROM users ORDER BY last_login DESC NULLS FIRST;
```

**Qayda:** Portable kod üçün həmişə `NULLS FIRST` və ya `NULLS LAST` explicit yaz.

### NULL-ləri sona aparmaq (MySQL trick)

MySQL `NULLS LAST` standart dəstəkləmir (8.0.27-dən əvvəl):

```sql
-- Trick: CASE WHEN
ORDER BY CASE WHEN last_login IS NULL THEN 1 ELSE 0 END, last_login;

-- Va ya bunu daha qisa:
ORDER BY last_login IS NULL, last_login;     -- IS NULL true=1, false=0
```

## LIMIT - Sahənin azaldılması

En çox neçə row qaytarmaq.

```sql
-- Ilk 10 row
SELECT * FROM products ORDER BY created_at DESC LIMIT 10;

-- MySQL / PostgreSQL (her ikisi)
SELECT * FROM products LIMIT 10;

-- SQL Server (TOP)
SELECT TOP 10 * FROM products;

-- Oracle (FETCH FIRST)
SELECT * FROM products FETCH FIRST 10 ROWS ONLY;
```

### LIMIT ORDER BY olmadan TƏHLUKƏLİdir

```sql
-- BAD: hansi 10 row gelecek - QARANTİYA YOXDUR!
SELECT * FROM products LIMIT 10;

-- Row yaradilma sirasi implementation detail-idir
-- Insert sirasi uzre gelmesi qarantiya deyil
-- Query planner fərqli plan secərsə fərqli row gələ bilər

-- GOOD: her zaman ORDER BY ilə
SELECT * FROM products ORDER BY id LIMIT 10;
```

**Qayda:** `LIMIT` varsa, `ORDER BY` mutləq olmalıdır.

## OFFSET - Row-ları atla

Başdan N row-u atla.

```sql
-- 11-20 row-lari (sehife 2, 10-luq)
SELECT * FROM products ORDER BY id LIMIT 10 OFFSET 10;

-- PostgreSQL ayri syntax-ı da dəstəkləyir:
SELECT * FROM products ORDER BY id LIMIT 10 OFFSET 10;
-- və ya:
SELECT * FROM products ORDER BY id OFFSET 10 LIMIT 10;

-- MySQL qisa syntax:
SELECT * FROM products ORDER BY id LIMIT 10, 10;    -- OFFSET, LIMIT (!)

-- Standard SQL (PostgreSQL):
SELECT * FROM products ORDER BY id 
OFFSET 10 ROWS FETCH NEXT 10 ROWS ONLY;
```

### OFFSET PROBLEMI (çox vacibdir!)

```sql
-- Sehife 1000 (her sehife 20 row)
SELECT * FROM orders ORDER BY created_at DESC LIMIT 20 OFFSET 19980;
```

**Problem:** DB 19,980 + 20 = 20,000 row oxuyur, sonra ilk 19,980-i **atır**. Cox **slow**-dur.

**Həll:** Keyset/cursor pagination istifade et. Ətraflı: `37-pagination-patterns.md`.

```sql
-- Keyset pagination (seek method)
SELECT * FROM orders 
WHERE created_at < '2026-04-24 10:30:00'    -- əvvəlki səhifənin son `created_at`-i
ORDER BY created_at DESC 
LIMIT 20;
```

## Deterministic Sıralama

```sql
-- BAD: eyni created_at olan row-lar rastgele sıra ilə gelir
SELECT * FROM orders ORDER BY created_at DESC LIMIT 10;

-- GOOD: tie-breaker elave et (primary key)
SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 10;
```

Bu xüsusilə **pagination** üçün vacibdir - eyni `created_at`-li iki row arasında deterministik sıra lazımdır.

## ORDER BY və Index

```sql
-- Index varsa: fast (index scan)
CREATE INDEX idx_orders_created ON orders(created_at DESC);
SELECT * FROM orders ORDER BY created_at DESC LIMIT 10;

-- Index yoxdur: DB butun table-i memorya alıb sıralayır (slow)
SELECT * FROM orders ORDER BY total_amount DESC LIMIT 10;
-- B-Tree index `total_amount` üçün olmalıdır

-- Multi-column ORDER BY üçün composite index:
CREATE INDEX idx_orders_user_created ON orders(user_id, created_at DESC);
SELECT * FROM orders 
WHERE user_id = 123 
ORDER BY created_at DESC LIMIT 10;
```

## Laravel Nümunəsi

```php
// ORDER BY
User::orderBy('created_at', 'desc')->get();
User::orderBy('country')->orderBy('name')->get();

// latest() və oldest() - sürətli shorthand
User::latest()->get();                        // ORDER BY created_at DESC
User::latest('updated_at')->get();            // ORDER BY updated_at DESC
User::oldest()->get();                        // ORDER BY created_at ASC

// LIMIT və OFFSET
User::limit(10)->offset(20)->get();
User::take(10)->skip(20)->get();             // alias

// Pagination - avtomatik LIMIT + OFFSET + COUNT
$users = User::latest()->paginate(20);

// Cursor pagination (keyset) - böyük table-lar üçün
$users = User::cursorPaginate(20);

// Raw ORDER BY (CASE və ya funksiya ilə)
User::orderByRaw("FIELD(status, 'active', 'pending', 'banned')")->get();
```

## Interview Sualları

**Q: `LIMIT 10 OFFSET 1000000` niye slow-dur?**
A: DB 1,000,010 row oxuyur və ilk 1,000,000-i atır. Həll: keyset pagination - WHERE ilə fikrin yerini xatırlamaq və yalnız son row-dan sonrakıları oxumaq.

**Q: ORDER BY hansı index strategy istifade edir?**
A: B-Tree index sort-unu hazır saxlayır, ORA BY DB-yə memorya sort etmə ehtiyacını aradan qaldırır. Əgər `ORDER BY` sütununda index yoxdursa, DB external sort edir (disk-ə yaza bilər).

**Q: `LIMIT` `ORDER BY` olmadan qanuni qaytarılan nəticəyə qanuni-dir?**
A: **Xeyir**. Row sırası SQL standard-ında təyin olunmur. ORDER BY olmadan 2 ardıcıl query fərqli nəticə qaytara bilər.

**Q: NULL deyerleri `ORDER BY`-da harada görünür?**
A: Default DB-dən asılıdır (PostgreSQL: ASC-də sonda, MySQL: əvvəldə). Portable olmaq üçün `NULLS FIRST` və ya `NULLS LAST` explicit yaz.
