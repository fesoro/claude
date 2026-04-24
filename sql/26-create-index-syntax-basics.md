# CREATE INDEX Syntax Basics

> **Seviyye:** Intermediate ⭐⭐

Index yaratma syntax-ı — **necə yaradılır**, algoritm və strategy üçün `27-indexing.md`.

## Basic CREATE INDEX

```sql
-- Single column
CREATE INDEX idx_users_email ON users(email);

-- Descending
CREATE INDEX idx_orders_created ON orders(created_at DESC);

-- IF NOT EXISTS (PostgreSQL, MySQL 8+)
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- DROP
DROP INDEX idx_users_email;                        -- PostgreSQL
DROP INDEX idx_users_email ON users;               -- MySQL
```

## Composite Index (Çox Sütunlu)

```sql
-- İki (və ya daha çox) sütun birlikdə
CREATE INDEX idx_orders_user_created 
ON orders(user_id, created_at DESC);

-- İstifadə halları:
-- WHERE user_id = 1                                -- OK (prefix)
-- WHERE user_id = 1 AND created_at > '...'         -- OK
-- WHERE created_at > '...'                          -- BAD (prefix yoxdur)
```

**Qayda (left-prefix):** Composite index yalnız sütunlar **sol-tərəfli prefix** olaraq istifadə olunduqda işləyir.

### Sıra əhəmiyyətlidir

```sql
-- Fərqli index:
CREATE INDEX idx_a ON orders(user_id, created_at);    -- user-i filter edir, sonra sort
CREATE INDEX idx_b ON orders(created_at, user_id);    -- created_at-i filter edir, sonra user

-- Hansı yaxşıdır? WHERE-dəki sütuna görə:
-- WHERE user_id = 1 AND created_at > '...' → idx_a
-- WHERE created_at > '...' AND user_id = 1 → idx_b

-- Selectivity (nə qədər unique dəyər var) da əhəmiyyətlidir:
-- user_id - 10M unique dəyər
-- status - 5 unique dəyər (pending, paid, ...)
-- → user_id əvvəl (daha selective)
```

## UNIQUE Index

```sql
-- Unique constraint ilə index (eyni şey)
CREATE UNIQUE INDEX idx_users_email ON users(email);

-- Composite unique
CREATE UNIQUE INDEX idx_user_role ON user_roles(user_id, role_id);

-- CONSTRAINT versiyası - funksional olaraq eyni
ALTER TABLE users ADD CONSTRAINT uk_email UNIQUE (email);
```

## Partial Index (PostgreSQL)

Yalnız şərtə uyğun row-ları index-ə daxil et. **Hərşə kiçik, sürətli**.

```sql
-- Yalnız active user-lər üçün index
CREATE INDEX idx_users_email_active 
ON users(email)
WHERE deleted_at IS NULL;

-- Pending order-lər üçün
CREATE INDEX idx_orders_pending 
ON orders(created_at)
WHERE status = 'pending';

-- Unique partial (email aktiv user-lər arasında unique)
CREATE UNIQUE INDEX idx_email_active 
ON users(email) 
WHERE deleted_at IS NULL;
```

**MySQL-də partial index yoxdur** (amma filtered replication var).

## Functional / Expression Index

Funksiya nəticəsini index-lə.

```sql
-- PostgreSQL
CREATE INDEX idx_email_lower ON users(LOWER(email));
-- Indi WHERE LOWER(email) = 'ali@x.com' fast olur

-- MySQL 8+
CREATE INDEX idx_email_lower ON users((LOWER(email)));

-- JSON extract index (MySQL)
CREATE INDEX idx_attrs_brand ON products((JSON_EXTRACT(attributes, '$.brand')));

-- JSON (PostgreSQL)
CREATE INDEX idx_attrs_brand ON products((attributes->>'brand'));
```

**Qayda:** Query WHERE-də məhz eyni expression istifadə etməlidir. Məs, `UPPER(email)` fərqli index olsun.

## Index-ə Sütun Daxil Et (INCLUDE) — Covering Index

Index-ə "taşımaq" üçün sütunlar əlavə et (WHERE-də olmayan, amma SELECT-də lazım olan).

```sql
-- PostgreSQL (11+)
CREATE INDEX idx_orders_user_include 
ON orders(user_id) 
INCLUDE (total, status);
-- Query: SELECT total, status FROM orders WHERE user_id = 1
-- Table-a getmədən yalnız index-dən cavab alır!

-- MySQL - bütün composite index sütunları "included" kimi işləyir
CREATE INDEX idx_orders_user ON orders(user_id, total, status);
```

**Covering index:** Query SELECT etdiyi bütün sütunlar index-in içində - **index-only scan**.

## Sort Direction

```sql
-- Qarışıq sort
CREATE INDEX idx_products_mixed 
ON products(category ASC, price DESC);

-- Query bu sortdan faydalanır:
SELECT * FROM products 
WHERE category = 'electronics'
ORDER BY price DESC;
```

## NULL Ordering (PostgreSQL)

```sql
-- NULL-lərin yerləşdiyi yer
CREATE INDEX idx_last_login 
ON users(last_login NULLS LAST);

-- Default: ASC → NULLS LAST, DESC → NULLS FIRST
```

## Index Types (PostgreSQL)

```sql
-- B-Tree (default) - ən çox istifadə olunan
CREATE INDEX idx_btree ON users(email);              -- USING btree implicit

-- Hash - yalnız = müqayisə (B-Tree-dən əhəmiyyətsiz fərqli)
CREATE INDEX idx_hash ON users USING HASH (email);

-- GIN (Generalized Inverted Index) - array, JSONB, full-text
CREATE INDEX idx_gin_tags ON posts USING GIN (tags);
CREATE INDEX idx_gin_jsonb ON products USING GIN (attributes);
CREATE INDEX idx_gin_text ON posts USING GIN (to_tsvector('english', content));

-- GiST (Generalized Search Tree) - geometric, full-text
CREATE INDEX idx_gist_location ON places USING GIST (location);

-- BRIN (Block Range Index) - çox böyük time-series table
CREATE INDEX idx_brin_created ON events USING BRIN (created_at);
-- Storage çox az, sıralanmış data üçün
```

## Concurrent Index Creation (PostgreSQL)

Production-da table-ı bloklamaq olmaz.

```sql
-- Adi - exclusive lock (INSERT/UPDATE/DELETE bloklanır)
CREATE INDEX idx_users_email ON users(email);       -- SLOW, BLOCKS

-- CONCURRENTLY - lock olmur
CREATE INDEX CONCURRENTLY idx_users_email ON users(email);
-- 2 dəfə table scan, amma blok yoxdur
-- Transaction-da işləmir

-- DROP də CONCURRENTLY:
DROP INDEX CONCURRENTLY idx_users_email;
```

**Qayda:** Production-da həmişə `CONCURRENTLY` istifadə et.

## Online Index (MySQL)

```sql
-- Default - ALTER TABLE edir, lock-lar tuta bilər
CREATE INDEX idx_users_email ON users(email);

-- Online DDL (MySQL 5.6+)
ALTER TABLE users ADD INDEX idx_email (email), ALGORITHM=INPLACE, LOCK=NONE;
```

## Index Adlandırma Konvensiyası

```sql
-- Yaxşı konvensiya: idx_<table>_<columns>
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_orders_user_id_status ON orders(user_id, status);
CREATE INDEX idx_products_category_price_desc ON products(category, price DESC);

-- Partial index
CREATE INDEX idx_users_email_active ON users(email) WHERE deleted_at IS NULL;

-- Unique index
CREATE UNIQUE INDEX uk_users_email ON users(email);
```

## Index Silmək

```sql
-- PostgreSQL
DROP INDEX idx_users_email;
DROP INDEX IF EXISTS idx_users_email;
DROP INDEX CONCURRENTLY idx_users_email;

-- MySQL
DROP INDEX idx_users_email ON users;
ALTER TABLE users DROP INDEX idx_email;
```

## Index-ləri Siyahı

```sql
-- PostgreSQL
\d users                                            -- psql meta-command
SELECT * FROM pg_indexes WHERE tablename = 'users';

-- MySQL
SHOW INDEX FROM users;
SHOW CREATE TABLE users;
```

## Composite Index Pitfall

```sql
-- Index: (user_id, created_at, status)
CREATE INDEX idx_complex ON orders(user_id, created_at, status);

-- Query 1: OK (prefix user_id, created_at)
WHERE user_id = 1 AND created_at > '...'            -- ✅

-- Query 2: OK (prefix user_id)
WHERE user_id = 1                                   -- ✅

-- Query 3: BAD (user_id atlandı)
WHERE created_at > '...' AND status = 'paid'        -- ❌ Full scan

-- Query 4: OK amma SUB-OPTIMAL
WHERE user_id = 1 AND status = 'paid'               -- ⚠️ Skip scan (MySQL) / Bitmap (PG)
-- created_at skipped - bəzi DB-lər bunu optimize edir
```

## Index Ölçüsü və Cache

```sql
-- Index-lərin ölçüsü
-- PostgreSQL
SELECT 
    schemaname, tablename, indexname, 
    pg_size_pretty(pg_relation_size(indexname::regclass)) AS size
FROM pg_indexes
WHERE schemaname = 'public';

-- MySQL
SELECT 
    TABLE_NAME, INDEX_NAME, 
    ROUND(STAT_VALUE * @@innodb_page_size / 1024 / 1024, 2) AS size_mb
FROM information_schema.INNODB_SYS_TABLESTATS
WHERE STAT_NAME = 'size';

-- Bütün index-lərin cəmi
SELECT pg_size_pretty(SUM(pg_relation_size(indexname::regclass)))
FROM pg_indexes;
```

**Qayda:** Çox index = slow INSERT/UPDATE/DELETE. Müəyyən index effektiv istifadə olunmursa — DROP.

## Laravel Nümunəsi

```php
Schema::table('users', function (Blueprint $table) {
    $table->index('email');                         // idx_users_email
    $table->index(['user_id', 'created_at']);       // composite
    $table->unique('email');                        // unique index
    $table->unique(['user_id', 'role_id']);
    
    // Funksional index (raw)
    // DB::statement('CREATE INDEX idx_email_lower ON users (LOWER(email))');
    
    // Partial (PG)
    // DB::statement("CREATE INDEX idx_active ON users(email) WHERE deleted_at IS NULL");
    
    $table->dropIndex('idx_users_email');
    $table->dropUnique('uk_users_email');
});

// Raw
DB::statement('CREATE INDEX CONCURRENTLY idx_users_created ON users(created_at)');
```

## Best Practices

1. **WHERE / JOIN / ORDER BY-də istifadə olunan sütunlar** index olsun
2. **Selectivity yüksək sütunu əvvəl** (user_id > status)
3. **Partial index** kiçik sub-set üçün (WHERE deleted_at IS NULL)
4. **Covering index** tez-tez oxunan query-lər üçün (INCLUDE)
5. **CONCURRENTLY (PG)** production-da - block olmaz
6. **EXPLAIN ilə test et** - index istifadə olunurmu?
7. **Çox index = yavas write** - balans saxla
8. **Unused index-ləri sil** - monitoring ilə tap

## Interview Sualları

**Q: Composite index-də sütun sırası niyə əhəmiyyətlidir?**
A: B-Tree index sütunları sol-tərəfdən sıralayır. `(A, B)` index `WHERE A`, `WHERE A AND B` üçün işləyir, amma `WHERE B` üçün yox. İlk sütun ən çox WHERE-də olan və selective olan olmalıdır.

**Q: Partial index nə üçün lazımdır?**
A: Data-nın kiçik hissəsi query olunursa (məs, yalnız pending order-lər), full index köhnə/lazımsız dəyərlər saxlayır. Partial index kiçik, cache-də qalır, sürətli.

**Q: Functional index nə zaman lazımdır?**
A: WHERE-də funksiya sütuna tətbiq olunursa (`LOWER(email)`, `YEAR(date)`). Normal index işləmir, functional index lazımdır.

**Q: `CREATE INDEX CONCURRENTLY` nə üçündür?**
A: PostgreSQL-də standart CREATE INDEX exclusive lock tutur (INSERT/UPDATE/DELETE bloklanır). CONCURRENTLY bu lock-u tutmur — table işləyir. 2 dəfə scan edir (daha yavaş), amma production-da mütləqdir.

**Q: Çox index niyə pis ola bilər?**
A: Hər INSERT/UPDATE/DELETE bütün index-ləri də update edir — write performance düşür. Disk space yeyir. Cache-də qalmayanda read də slow olur. Yalnız istifadə olunanları saxla.
