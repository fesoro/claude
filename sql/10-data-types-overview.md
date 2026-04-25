# Data Types Overview (Junior)

Düzgün data type seçimi performans, storage və data integrity üçün vacibdir. **Schema-nın əsasıdır**.

## Numeric Types

### Integer Types

| Type | MySQL | PostgreSQL | Range | Storage |
|------|-------|------------|-------|---------|
| tinyint | `TINYINT` | — | -128 to 127 | 1B |
| smallint | `SMALLINT` | `SMALLINT` | -32K to 32K | 2B |
| int | `INT` / `INTEGER` | `INTEGER` / `INT` | -2.1B to 2.1B | 4B |
| bigint | `BIGINT` | `BIGINT` | -9.2×10^18 to 9.2×10^18 | 8B |

```sql
-- Primary key-lər üçün BIGINT tövsiyə olunur (INT-in limiti 2.1B)
CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,          -- MySQL
    -- id BIGSERIAL PRIMARY KEY,                   -- PostgreSQL
    quantity INT,
    priority SMALLINT
);
```

**Qayda:** Primary key üçün `BIGINT` (INT limitə çata bilər). Status, priority kimi kiçik sayılar üçün `SMALLINT` və ya `TINYINT`.

### UNSIGNED (MySQL only)

```sql
-- MySQL: müsbət dəyərlər üçün diapazon artır
CREATE TABLE t (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  -- 0 to 18.4×10^18
    quantity INT UNSIGNED                          -- 0 to 4.2B
);
```

**Qeyd:** PostgreSQL `UNSIGNED` dəstəkləmir. Mənfi dəyər qadağan etmək üçün `CHECK (col >= 0)`.

### Decimal / Numeric (dəqiq)

```sql
-- Pul, dəqiq hesablama üçün
CREATE TABLE orders (
    total DECIMAL(10, 2),           -- 10 rəqəm cəmi, 2-si kəsr
    -- max: 99999999.99
    tax_rate NUMERIC(5, 4)          -- 5 rəqəm, 4 kəsr (0.0000 to 9.9999)
);

-- DECIMAL vs NUMERIC - sinonimdir (SQL standard)
```

### Float / Double (inexact)

```sql
CREATE TABLE measurements (
    temperature FLOAT,              -- 4B, ~7 dəqiq rəqəm
    distance DOUBLE,                -- 8B, ~15 dəqiq rəqəm (MySQL)
    distance DOUBLE PRECISION       -- PostgreSQL sintaksisi
);
```

**Qayda:** **Pul üçün DECIMAL istifadə et**, FLOAT/DOUBLE yox! Floating point yuvarlaqlaşdırma səhvləri var (`0.1 + 0.2 != 0.3`).

## String Types

### VARCHAR vs TEXT

```sql
-- VARCHAR - təyin olunmuş max uzunluq
name VARCHAR(255)                  -- 255 simvola qədər

-- TEXT - praktiki olaraq limitsiz
description TEXT                   -- PostgreSQL: limitsiz, MySQL: 65KB

-- MySQL-də TEXT variantları
TINYTEXT       -- 255 bayt
TEXT           -- 65KB
MEDIUMTEXT     -- 16MB
LONGTEXT       -- 4GB
```

**Fərq (PostgreSQL):** `VARCHAR(n)`, `VARCHAR`, `TEXT` — hər üçü eyni performansdadır. **PostgreSQL `TEXT` istifadə et**.

**Fərq (MySQL):**
- `VARCHAR(255)` — inline saxlanır (max 255 bayt)
- `TEXT` — ayrı blob bölməsində saxlanır (index tamamlanmış ola bilməz)

**Qayda:** 
- Qısa string (ad, email): `VARCHAR(255)`
- Uzun mətn (blog post): PG `TEXT`, MySQL `TEXT`/`MEDIUMTEXT`

### CHAR — Fixed Length

```sql
-- Həmişə N simvol, qısa olsa bile pad olunur
country_code CHAR(2)               -- həmişə 2 simvol ('AZ', 'TR')
```

**İstifadə:** Fixed-length identifier-lər (ISO country code, currency code). Adətən `VARCHAR` daha yaxşıdır.

### Character Set / Collation

**Həmişə utf8mb4 istifadə et (MySQL):**

```sql
-- MySQL: emoji + bütün Unicode üçün utf8mb4 MƏCBURİ
CREATE TABLE messages (
    content TEXT
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- MySQL utf8 = 3-byte utf8 (əksik!) - emoji saxlaya bilmir
-- utf8mb4 = real 4-byte utf8
```

Ətraflı: `35-character-encoding-and-collation.md`.

## Date/Time Types

```sql
-- PostgreSQL
DATE                        -- '2026-04-24' (4B)
TIME                        -- '14:30:00' (8B)
TIMESTAMP                   -- date + time, no timezone (8B)
TIMESTAMPTZ                 -- date + time WITH timezone (8B) - RECOMMEND
INTERVAL                    -- '3 days 2 hours' (16B)

-- MySQL
DATE                        -- '2026-04-24'
TIME                        -- '14:30:00'
DATETIME                    -- '2026-04-24 14:30:00' (no timezone)
TIMESTAMP                   -- UTC-də saxlanır, session timezone-a çevrilir
YEAR                        -- 'YYYY' (4 bayt)
```

**Qayda:**
- PostgreSQL: `TIMESTAMPTZ` istifadə et (timezone-aware)
- MySQL: `TIMESTAMP` (UTC-də saxlanır) və ya `DATETIME` + application-level timezone

```sql
-- Nümunə
CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    shipped_at TIMESTAMPTZ,
    delivery_window INTERVAL DEFAULT '3 days'
);
```

Ətraflı: `13-date-time-functions.md`.

## Boolean

```sql
-- PostgreSQL: əsl BOOLEAN
is_active BOOLEAN DEFAULT true

-- MySQL: BOOLEAN = TINYINT(1) synonymi
is_active BOOLEAN DEFAULT TRUE     -- 0 ve ya 1 olaraq saxlanir
is_active TINYINT(1) DEFAULT 1     -- eyni şey
```

## JSON / JSONB

```sql
-- PostgreSQL: JSONB (binary, indexed) - həmişə JSONB seç
CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    attributes JSONB
);
INSERT INTO products (attributes) VALUES 
    ('{"brand": "Dell", "ram": 16, "colors": ["black", "silver"]}');

-- JSONB query
SELECT * FROM products WHERE attributes->>'brand' = 'Dell';
SELECT * FROM products WHERE attributes @> '{"ram": 16}';

-- MySQL 5.7+: JSON
CREATE TABLE products (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    attributes JSON
);
SELECT * FROM products WHERE JSON_EXTRACT(attributes, '$.brand') = 'Dell';
SELECT * FROM products WHERE attributes->>'$.brand' = 'Dell';
```

Ətraflı: `33-advanced-features.md` (JSON, FTS).

## UUID

```sql
-- PostgreSQL: uuid type
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid()
);

-- MySQL: UUID() funksiyası + CHAR(36) ya da BINARY(16)
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    -- Daha kompakt: BINARY(16)
    id BINARY(16) PRIMARY KEY
);
```

UUID seçimi haqqında: `45-id-generation-strategies.md`.

## Binary / BLOB

```sql
-- File icerisini saxlamaq
CREATE TABLE files (
    id BIGINT PRIMARY KEY,
    content BYTEA                  -- PostgreSQL
    -- content BLOB                -- MySQL (65KB)
    -- content MEDIUMBLOB          -- MySQL (16MB)  
    -- content LONGBLOB            -- MySQL (4GB)
);
```

**Qayda:** Böyük file-ları DB-də saxlama — S3/MinIO kimi object storage istifadə et. DB-də URL saxla.

## ENUM

```sql
-- MySQL
status ENUM('pending', 'active', 'cancelled') NOT NULL DEFAULT 'pending'

-- PostgreSQL
CREATE TYPE order_status AS ENUM ('pending', 'active', 'cancelled');
CREATE TABLE orders (status order_status DEFAULT 'pending');
```

**Diskussiya:** ENUM əlavə etmək problematikdir (migration lazımdır). Alternativi:
```sql
-- String + CHECK constraint
status VARCHAR(20) CHECK (status IN ('pending', 'active', 'cancelled'))

-- Foreign key table
status_id INT REFERENCES order_statuses(id)
```

## Array (PostgreSQL)

```sql
-- PostgreSQL: native array
CREATE TABLE posts (
    id BIGSERIAL PRIMARY KEY,
    tags TEXT[]
);
INSERT INTO posts (tags) VALUES (ARRAY['php', 'laravel', 'sql']);

-- Query
SELECT * FROM posts WHERE 'laravel' = ANY(tags);
SELECT * FROM posts WHERE tags @> ARRAY['php', 'sql'];
```

**MySQL-də array yoxdur — JSON istifadə et.**

## Geometric (PostgreSQL)

```sql
-- PostgreSQL native: POINT, LINE, POLYGON
CREATE TABLE places (
    id BIGSERIAL PRIMARY KEY,
    location POINT
);

-- Daha güclü: PostGIS extension
-- MySQL: SPATIAL types
```

## Data Type Qiymətləndirmə Qaydaları

### 1. Pul
```
DECIMAL(10, 2)           -- Yox: FLOAT
```

### 2. ID (primary key)
```
BIGINT                   -- adi seq
UUID                     -- distributed
ULID / Snowflake         -- sortable, distributed
```

### 3. Boolean
```
BOOLEAN                  -- yox: VARCHAR('Y', 'N')
```

### 4. Tarix
```
DATE                     -- yalnız tarix
TIMESTAMPTZ              -- tarix + saat + timezone (PG)
TIMESTAMP                -- MySQL (UTC-də saxlanır)
```

### 5. Status/enum
```
VARCHAR + CHECK CONSTRAINT   -- dəyişkən
ENUM                         -- fixed list (amma migration problematic)
FK to lookup table          -- çox tez dəyişir
```

### 6. Uzun mətn
```
TEXT                     -- PG: həmişə
MEDIUMTEXT/LONGTEXT      -- MySQL: 64KB-dan böyük
```

### 7. JSON
```
JSONB                    -- PostgreSQL
JSON                     -- MySQL 5.7+
```

### 8. Geo
```
POINT / PostGIS          -- PostgreSQL
POINT / SPATIAL          -- MySQL
```

## Anti-Pattern: VARCHAR Hər Şey üçün

```sql
-- BAD
CREATE TABLE orders (
    id VARCHAR(255),              -- niyə?
    user_id VARCHAR(255),         -- FK olmalıdır
    total VARCHAR(255),           -- hesablama olmur
    quantity VARCHAR(255),        -- hesablama olmur
    created_at VARCHAR(255),      -- sorting/range olmur
    is_paid VARCHAR(255)          -- 'Y'/'N'/'true'/'1' qarışır
);

-- GOOD
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    total DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_paid BOOLEAN NOT NULL DEFAULT FALSE
);
```

## Laravel Nümunəsi (Migration)

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();                          // BIGINT UNSIGNED AUTO_INCREMENT PK
    $table->foreignId('user_id')->constrained();
    $table->decimal('total', 10, 2);
    $table->integer('quantity');
    $table->string('status', 20)->default('pending');
    $table->boolean('is_paid')->default(false);
    $table->json('metadata')->nullable();
    $table->timestamp('created_at');
    $table->timestamp('updated_at');
    $table->timestamp('deleted_at')->nullable();       // soft delete
});
```

## Interview Sualları

**Q: Pul saxlamaq üçün FLOAT istifadə etmək olar?**
A: **Xeyir**. Floating point yuvarlaqlaşdırma səhvləri var (`0.1 + 0.2 = 0.30000000000000004`). Həmişə `DECIMAL(n, 2)` istifadə et.

**Q: MySQL `utf8` və `utf8mb4` fərqi?**
A: MySQL `utf8` = əksik 3-byte UTF8 (emoji saxlaya bilmir). `utf8mb4` = real 4-byte UTF8. **Həmişə `utf8mb4` istifadə et**.

**Q: `VARCHAR(255)` vs `TEXT` fərqi PostgreSQL-də?**
A: PostgreSQL-də praktiki fərq yoxdur — ikisi də eyni storage mexanizmini istifadə edir. Konvensional olaraq `TEXT` istifadə et.

**Q: Primary key üçün `INT` yoxsa `BIGINT`?**
A: **BIGINT**. INT 2.1 milyard-da bitir (bəzi table-lar bir il ərzində bu limitə çatır). BIGINT 9.2 × 10^18 — praktiki olaraq bitməz.

**Q: Timestamp üçün `TIMESTAMP` vs `TIMESTAMPTZ` PostgreSQL-də?**
A: `TIMESTAMPTZ` — timezone-aware, UTC-də saxlanır, clientə session timezone-da qaytarılır. Multi-region apps üçün mütləqdir.
