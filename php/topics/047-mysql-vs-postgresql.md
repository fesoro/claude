# MySQL və PostgreSQL Fərqləri (Middle)

## Mündəricat
1. [Tarixi və Fəlsəfəsi](#tarixi-və-fəlsəfəsi)
2. [Data Types Fərqləri](#data-types-fərqləri)
3. [MVCC - Multi-Version Concurrency Control](#mvcc---multi-version-concurrency-control)
4. [Indexing Fərqləri](#indexing-fərqləri)
5. [Partitioning](#partitioning)
6. [Replication](#replication)
7. [Performance Fərqləri](#performance-fərqləri)
8. [ACID Compliance](#acid-compliance)
9. [Locking Mechanisms](#locking-mechanisms)
10. [Stored Procedures, Functions, Triggers](#stored-procedures-functions-triggers)
11. [Views və Materialized Views](#views-və-materialized-views)
12. [CTE və Window Functions](#cte-və-window-functions)
13. [Full-Text Search Fərqləri](#full-text-search-fərqləri)
14. [EXPLAIN ANALYZE](#explain-analyze)
15. [Connection Pooling](#connection-pooling)
16. [PostgreSQL-ə Xas Xüsusiyyətlər](#postgresql-ə-xas-xüsusiyyətlər)
17. [MySQL-ə Xas Xüsusiyyətlər](#mysql-ə-xas-xüsusiyyətlər)
18. [Laravel-də İstifadə](#laravel-də-istifadə)
19. [Hansı Halda Hansını Seçməli](#hansı-halda-hansını-seçməli)
20. [Deadlock və Həlli](#deadlock-və-həlli)
21. [Query Optimization Techniques](#query-optimization-techniques)
22. [İntervyu Sualları](#intervyu-sualları-və-cavabları)

---

## Tarixi və Fəlsəfəsi

### MySQL
MySQL 1995-ci ildə Michael Widenius və David Axmark tərəfindən yaradılıb. İlk olaraq sürət və sadəliyə fokuslanıb. 2008-ci ildə Sun Microsystems tərəfindən, 2010-cu ildə isə Oracle Corporation tərəfindən alınıb. MySQL-in fəlsəfəsi **"sürətli oxuma əməliyyatları"** üzərində qurulub. Web tətbiqlərində ən çox istifadə edilən RDBMS-dir (LAMP stack - Linux, Apache, MySQL, PHP).

**Əsas fork-lar:**
- **MariaDB** - MySQL-in orijinal yaradıcısı tərəfindən, Oracle-ın sahibliyinə etiraz olaraq yaradılıb
- **Percona Server** - performans optimizasiyaları ilə

### PostgreSQL
PostgreSQL 1986-cı ildə UC Berkeley-də POSTGRES layihəsi olaraq başlayıb, 1996-cı ildən PostgreSQL adını alıb. Fəlsəfəsi **"standartlara uyğunluq və genişlənə bilmə"** üzərində qurulub. "Dünyanın ən inkişaf etmiş açıq mənbəli relational database-i" olaraq tanınır.

**Əsas xüsusiyyət:** PostgreSQL tam olaraq SQL standartlarına uyğundur və extensibility (genişlənə bilmə) üzərində fokuslanır. Öz data type-larınızı, operator-larınızı, index type-larınızı yarada bilərsiniz.

```
MySQL Fəlsəfəsi:  Sürət > Standartlara uyğunluq
PostgreSQL Fəlsəfəsi: Standartlara uyğunluq > Sürət (amma artıq sürət fərqi çox azdır)
```

---

## Data Types Fərqləri

### JSON və JSONB

**MySQL-də JSON:**
```sql
-- MySQL JSON type (5.7+)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    attributes JSON
);

INSERT INTO products (name, attributes) VALUES
('Laptop', '{"brand": "Dell", "ram": 16, "colors": ["black", "silver"]}');

-- JSON funksiyaları
SELECT 
    name,
    JSON_EXTRACT(attributes, '$.brand') AS brand,
    attributes->>'$.ram' AS ram,          -- shorthand (MySQL 8.0+)
    JSON_CONTAINS(attributes, '"black"', '$.colors') AS has_black
FROM products;

-- JSON dəyəri yeniləmə
UPDATE products 
SET attributes = JSON_SET(attributes, '$.price', 999.99)
WHERE id = 1;
```

**PostgreSQL-də JSON və JSONB:**
```sql
-- PostgreSQL-də 2 JSON tipi var: json və jsonb
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    attributes JSONB  -- JSONB: binary format, index dəstəyi, daha sürətli query
);

INSERT INTO products (name, attributes) VALUES
('Laptop', '{"brand": "Dell", "ram": 16, "colors": ["black", "silver"]}');

-- JSONB operatorları (PostgreSQL çox zəngindir)
SELECT 
    name,
    attributes->>'brand' AS brand,           -- text olaraq çıxar
    attributes->'ram' AS ram,                 -- JSON olaraq çıxar
    attributes @> '{"brand": "Dell"}' AS is_dell,  -- contains operator
    attributes ? 'brand' AS has_brand_key,    -- key mövcuddur?
    attributes ?| array['brand','model'] AS has_any, -- hər hansı key?
    attributes ?& array['brand','ram'] AS has_all    -- bütün key-lər?
FROM products;

-- JSONB üçün GIN index (MySQL-də yoxdur!)
CREATE INDEX idx_products_attributes ON products USING GIN (attributes);

-- JSONB path query (PostgreSQL 12+)
SELECT * FROM products 
WHERE attributes @? '$.colors[*] ? (@ == "black")';

-- JSONB aggregation
SELECT jsonb_agg(attributes->>'brand') FROM products;

-- JSONB merge
UPDATE products 
SET attributes = attributes || '{"warranty": "2 years"}'::jsonb
WHERE id = 1;
```

> **Vacib fərq:** PostgreSQL-in JSONB tipi binary formatda saxlanılır, bu da query-ləri çox sürətli edir və GIN index ilə indekslənə bilir. MySQL-in JSON tipi isə hər query-də parse edilir.

### Array Type (Yalnız PostgreSQL)

*Array Type (Yalnız PostgreSQL) üçün kod nümunəsi:*
```sql
-- PostgreSQL Array type
CREATE TABLE articles (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255),
    tags TEXT[],               -- text array
    scores INTEGER[]           -- integer array
);

INSERT INTO articles (title, tags, scores) VALUES
('Laravel Tips', ARRAY['php', 'laravel', 'backend'], ARRAY[95, 87, 92]);

-- Array query-ləri
SELECT * FROM articles WHERE 'laravel' = ANY(tags);
SELECT * FROM articles WHERE tags @> ARRAY['php', 'laravel'];  -- hamısını ehtiva edir
SELECT * FROM articles WHERE tags && ARRAY['vue', 'react'];     -- hər hansı birini

-- Array funksiyaları
SELECT 
    title,
    array_length(tags, 1) AS tag_count,
    unnest(tags) AS individual_tag,  -- array-i sətrlərə açır
    array_to_string(tags, ', ') AS tags_string
FROM articles;

-- GIN index array üçün
CREATE INDEX idx_articles_tags ON articles USING GIN (tags);
```

### HSTORE (Yalnız PostgreSQL)

*HSTORE (Yalnız PostgreSQL) üçün kod nümunəsi:*
```sql
-- Key-value store kimi istifadə edilir
CREATE EXTENSION IF NOT EXISTS hstore;

CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    user_id INT,
    preferences HSTORE
);

INSERT INTO settings (user_id, preferences) VALUES
(1, 'theme => dark, language => az, notifications => true');

SELECT preferences->'theme' FROM settings WHERE user_id = 1;
SELECT * FROM settings WHERE preferences ? 'theme';
SELECT * FROM settings WHERE preferences @> 'theme => dark';
```

### UUID

*UUID üçün kod nümunəsi:*
```sql
-- PostgreSQL (native UUID type)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

CREATE TABLE users (
    id UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
    name VARCHAR(255)
);

-- PostgreSQL 13+ gen_random_uuid()
CREATE TABLE orders (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    total DECIMAL(10,2)
);

-- MySQL (UUID function var, amma native type yoxdur, CHAR(36) istifadə olunur)
CREATE TABLE users (
    id CHAR(36) DEFAULT (UUID()) PRIMARY KEY,  -- MySQL 8.0+
    name VARCHAR(255)
);
-- MySQL-də UUID CHAR(36) olaraq saxlanılır, bu performans üçün pis ola bilər.
-- Binary(16) istifadə etmək daha yaxşıdır:
CREATE TABLE users_optimized (
    id BINARY(16) DEFAULT (UUID_TO_BIN(UUID(), 1)) PRIMARY KEY,
    name VARCHAR(255)
);
```

### ENUM

*ENUM üçün kod nümunəsi:*
```sql
-- MySQL ENUM (column-level)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled')
);

-- PostgreSQL ENUM (type-level - daha çevik)
CREATE TYPE order_status AS ENUM ('pending', 'processing', 'shipped', 'delivered', 'cancelled');

CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    status order_status DEFAULT 'pending'
);

-- PostgreSQL-də ENUM-a dəyər əlavə etmək:
ALTER TYPE order_status ADD VALUE 'refunded' AFTER 'cancelled';

-- MySQL-də ENUM dəyişmək üçün bütün column-u ALTER etmək lazımdır
ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','shipped','delivered','cancelled','refunded');
```

---

## MVCC - Multi-Version Concurrency Control

MVCC reader-lərin writer-ları, writer-ların da reader-ləri blok etməməsini təmin edən mexanizmdir. Hər iki database MVCC istifadə edir, amma fərqli şəkildə.

### PostgreSQL MVCC

PostgreSQL hər row-un birdən çox versiyasını **eyni cədvəldə** saxlayır:

```
Row: (xmin=100, xmax=∞)   -- Transaction 100 tərəfindən yaradılıb, hələ silinməyib
UPDATE sonrası:
Row: (xmin=100, xmax=105)  -- Köhnə versiya, Transaction 105 tərəfindən "silinib"
Row: (xmin=105, xmax=∞)    -- Yeni versiya, Transaction 105 tərəfindən yaradılıb
```

- `xmin`: Row-u yaradan transaction ID
- `xmax`: Row-u silən/yeniləyən transaction ID
- Hər transaction öz "snapshot"-ını görür
- **VACUUM** prosesi köhnə versiyaları təmizləyir (autovacuum)

*- **VACUUM** prosesi köhnə versiyaları təmizləyir (autovacuum) üçün kod nümunəsi:*
```sql
-- PostgreSQL-də dead tuple-ları görmək
SELECT relname, n_dead_tup, n_live_tup, last_vacuum, last_autovacuum 
FROM pg_stat_user_tables;

-- Manual vacuum
VACUUM ANALYZE products;

-- Aggressive vacuum (ID wraparound-ı önləmək üçün)
VACUUM FULL products;  -- Cədvəli tamamilə yenidən yazır, lock edir!
```

### MySQL (InnoDB) MVCC

MySQL/InnoDB köhnə versiyaları **undo log**-da saxlayır:

```
Cədvəldəki row: Ən son versiya (current)
Undo log: Əvvəlki versiyalar (rollback segment-də)

Row -> Undo record 1 -> Undo record 2 -> ... (versiya zənciri)
```

- Hər row-da gizli sütunlar: `DB_TRX_ID`, `DB_ROLL_PTR`, `DB_ROW_ID`
- Read View: Transaction başlayanda aktiv transaction-ların siyahısı
- **Purge thread** köhnə undo log-ları təmizləyir

*- **Purge thread** köhnə undo log-ları təmizləyir üçün kod nümunəsi:*
```sql
-- InnoDB status-da undo log məlumatı
SHOW ENGINE INNODB STATUS;

-- Transaction isolation level
SET TRANSACTION ISOLATION LEVEL REPEATABLE READ; -- MySQL default
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;  -- PostgreSQL default
```

**Əsas fərq:**
| Xüsusiyyət | PostgreSQL | MySQL (InnoDB) |
|---|---|---|
| Köhnə versiyalar | Eyni cədvəldə | Undo log-da |
| Təmizləmə | VACUUM | Purge thread |
| Bloat riski | Bəli (vacuum lazımdır) | Xeyr (amma uzun tx undo-nu böyüdür) |
| Default Isolation | READ COMMITTED | REPEATABLE READ |

---

## Indexing Fərqləri

### B-tree Index (Hər ikisində)

*B-tree Index (Hər ikisində) üçün kod nümunəsi:*
```sql
-- Hər ikisində default index type
CREATE INDEX idx_users_email ON users (email);

-- Composite index
CREATE INDEX idx_orders_user_date ON orders (user_id, created_at);
```

### Hash Index

*Hash Index üçün kod nümunəsi:*
```sql
-- PostgreSQL: Hash index (tam bərabərlik sorğuları üçün)
CREATE INDEX idx_users_email_hash ON users USING HASH (email);
-- Yalnız = operatoru dəstəkləyir, range sorğuları üçün yararsız
-- PostgreSQL 10-dan əvvəl WAL-a yazılmırdı, crash-safe deyildi

-- MySQL (InnoDB): Hash index yalnız MEMORY engine-də
-- InnoDB adaptive hash index-i avtomatik yaradır (dəyişə bilməzsiniz)
```

### GIN Index (Yalnız PostgreSQL)

**Generalized Inverted Index** - array, JSONB, full-text search üçün ideal:

***Generalized Inverted Index** - array, JSONB, full-text search üçün i üçün kod nümunəsi:*
```sql
-- JSONB üçün GIN
CREATE INDEX idx_products_attrs ON products USING GIN (attributes);

-- Full-text search üçün GIN
CREATE INDEX idx_articles_search ON articles USING GIN (to_tsvector('english', title || ' ' || body));

-- Array üçün GIN
CREATE INDEX idx_articles_tags ON articles USING GIN (tags);

-- Trigram axtarış üçün (LIKE '%söz%' sürətləndirmək)
CREATE EXTENSION pg_trgm;
CREATE INDEX idx_users_name_trgm ON users USING GIN (name gin_trgm_ops);
```

### GiST Index (Yalnız PostgreSQL)

**Generalized Search Tree** - geometrik, range, full-text üçün:

***Generalized Search Tree** - geometrik, range, full-text üçün üçün kod nümunəsi:*
```sql
-- Range type üçün
CREATE TABLE events (
    id SERIAL PRIMARY KEY,
    name TEXT,
    duration TSRANGE  -- timestamp range
);

CREATE INDEX idx_events_duration ON events USING GiST (duration);

-- Overlap query
SELECT * FROM events WHERE duration && '[2024-01-01, 2024-01-31]'::tsrange;

-- Exclusion constraint (GiST ilə - yalnız PostgreSQL!)
ALTER TABLE events 
ADD CONSTRAINT no_overlap EXCLUDE USING GiST (duration WITH &&);

-- PostGIS (coğrafi data) üçün
CREATE INDEX idx_locations_point ON locations USING GiST (coordinates);
```

### BRIN Index (Yalnız PostgreSQL)

**Block Range Index** - çox böyük, naturally ordered cədvəllər üçün:

***Block Range Index** - çox böyük, naturally ordered cədvəllər üçün üçün kod nümunəsi:*
```sql
-- Tarixə görə sıralanmış log cədvəli (milyonlarla row)
CREATE TABLE logs (
    id SERIAL,
    created_at TIMESTAMP DEFAULT NOW(),
    message TEXT
);

-- BRIN index (B-tree-dən 100x kiçik ola bilər!)
CREATE INDEX idx_logs_created ON logs USING BRIN (created_at);

-- BRIN yalnız data fiziki olaraq sıralanmışdırsa effektivdir
-- Yəni INSERT ardıcıl gəlirsə (time-series data üçün ideal)
```

### Full-Text Index

*Full-Text Index üçün kod nümunəsi:*
```sql
-- MySQL Full-Text Index
CREATE FULLTEXT INDEX idx_articles_ft ON articles (title, body);
-- Yalnız InnoDB və MyISAM engine-lərdə

-- PostgreSQL Full-Text Index (GIN ilə)
CREATE INDEX idx_articles_fts ON articles 
    USING GIN (to_tsvector('english', title || ' ' || body));
```

### MySQL-ə Xas Index Xüsusiyyətləri

*MySQL-ə Xas Index Xüsusiyyətləri üçün kod nümunəsi:*
```sql
-- Prefix index (MySQL)
CREATE INDEX idx_name_prefix ON users (name(10));  -- İlk 10 simvol

-- Descending index (MySQL 8.0+)
CREATE INDEX idx_date_desc ON orders (created_at DESC);

-- Invisible index (MySQL 8.0+ - test üçün əla)
CREATE INDEX idx_test ON users (phone) INVISIBLE;
ALTER INDEX idx_test VISIBLE;
```

### Index Müqayisə Cədvəli

| Index Tipi | MySQL | PostgreSQL | İstifadə |
|---|---|---|---|
| B-tree | Bəli (default) | Bəli (default) | Ümumi məqsədli |
| Hash | MEMORY only | Bəli | Yalnız = sorğuları |
| GIN | Xeyr | Bəli | JSONB, Array, FTS |
| GiST | Xeyr | Bəli | Geometrik, Range |
| BRIN | Xeyr | Bəli | Böyük ordered data |
| R-tree | MyISAM (köhnə) | GiST vasitəsilə | Spatial data |
| Full-text | Bəli | GIN/GiST ilə | Mətn axtarışı |
| Partial | Xeyr | Bəli | Şərtli index |
| Expression | Xeyr (8.0 functional) | Bəli | Hesablanmış dəyərlər |

*həll yanaşmasını üçün kod nümunəsi:*
```sql
-- PostgreSQL Partial Index (MySQL-də yoxdur)
CREATE INDEX idx_active_users ON users (email) WHERE is_active = true;

-- PostgreSQL Expression Index
CREATE INDEX idx_lower_email ON users (LOWER(email));

-- MySQL Functional Index (8.0+)
CREATE INDEX idx_lower_email ON users ((LOWER(email)));  -- əlavə mötərizə lazımdır
```

---

## Partitioning

### Range Partitioning

*Range Partitioning üçün kod nümunəsi:*
```sql
-- PostgreSQL Range Partitioning (Declarative, 10+)
CREATE TABLE orders (
    id SERIAL,
    user_id INT,
    total DECIMAL(10,2),
    created_at TIMESTAMP
) PARTITION BY RANGE (created_at);

CREATE TABLE orders_2023 PARTITION OF orders
    FOR VALUES FROM ('2023-01-01') TO ('2024-01-01');
CREATE TABLE orders_2024 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');
CREATE TABLE orders_2025 PARTITION OF orders
    FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');

-- MySQL Range Partitioning
CREATE TABLE orders (
    id INT AUTO_INCREMENT,
    user_id INT,
    total DECIMAL(10,2),
    created_at DATETIME,
    PRIMARY KEY (id, created_at)  -- Partition key primary key-də olmalıdır!
) PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### List Partitioning

*List Partitioning üçün kod nümunəsi:*
```sql
-- PostgreSQL
CREATE TABLE orders (
    id SERIAL,
    region TEXT,
    total DECIMAL(10,2)
) PARTITION BY LIST (region);

CREATE TABLE orders_europe PARTITION OF orders FOR VALUES IN ('AZ', 'TR', 'DE', 'FR');
CREATE TABLE orders_asia PARTITION OF orders FOR VALUES IN ('CN', 'JP', 'KR');

-- MySQL
CREATE TABLE orders (
    id INT AUTO_INCREMENT,
    region VARCHAR(2),
    total DECIMAL(10,2),
    PRIMARY KEY (id, region)
) PARTITION BY LIST COLUMNS (region) (
    PARTITION p_europe VALUES IN ('AZ', 'TR', 'DE', 'FR'),
    PARTITION p_asia VALUES IN ('CN', 'JP', 'KR')
);
```

### Hash Partitioning

*Hash Partitioning üçün kod nümunəsi:*
```sql
-- PostgreSQL
CREATE TABLE sessions (
    id SERIAL,
    user_id INT,
    data JSONB
) PARTITION BY HASH (user_id);

CREATE TABLE sessions_0 PARTITION OF sessions FOR VALUES WITH (MODULUS 4, REMAINDER 0);
CREATE TABLE sessions_1 PARTITION OF sessions FOR VALUES WITH (MODULUS 4, REMAINDER 1);
CREATE TABLE sessions_2 PARTITION OF sessions FOR VALUES WITH (MODULUS 4, REMAINDER 2);
CREATE TABLE sessions_3 PARTITION OF sessions FOR VALUES WITH (MODULUS 4, REMAINDER 3);

-- MySQL
CREATE TABLE sessions (
    id INT AUTO_INCREMENT,
    user_id INT,
    data JSON,
    PRIMARY KEY (id, user_id)
) PARTITION BY HASH (user_id) PARTITIONS 4;
```

---

## Replication

### MySQL Replication

*MySQL Replication üçün kod nümunəsi:*
```sql
-- MySQL Master-Slave (Source-Replica) Replication
-- Binary Log əsaslıdır

-- Master (Source) konfiqurasiyası (my.cnf):
-- [mysqld]
-- server-id=1
-- log-bin=mysql-bin
-- binlog-format=ROW

-- Slave (Replica) konfiqurasiyası:
CHANGE REPLICATION SOURCE TO
    SOURCE_HOST='master_host',
    SOURCE_USER='repl_user',
    SOURCE_PASSWORD='password',
    SOURCE_LOG_FILE='mysql-bin.000001',
    SOURCE_LOG_POS=154;
START REPLICA;

-- MySQL Group Replication (Master-Master)
-- Birdən çox node yazma qəbul edə bilər
-- Conflict resolution avtomatikdir

-- MySQL InnoDB Cluster
-- Group Replication + MySQL Router + MySQL Shell
```

### PostgreSQL Replication

*PostgreSQL Replication üçün kod nümunəsi:*
```sql
-- 1. Streaming Replication (Physical - byte-by-byte kopyalama)
-- WAL (Write-Ahead Log) əsaslıdır
-- postgresql.conf (primary):
-- wal_level = replica
-- max_wal_senders = 10

-- Standby yaratma:
-- pg_basebackup -h primary_host -D /var/lib/postgresql/data -U repl -P --wal-method=stream

-- 2. Logical Replication (PostgreSQL 10+)
-- Cədvəl səviyyəsində, seçici replikasiya
CREATE PUBLICATION my_pub FOR TABLE orders, products;

-- Subscriber-da:
CREATE SUBSCRIPTION my_sub
    CONNECTION 'host=primary dbname=mydb'
    PUBLICATION my_pub;

-- 3. Logical Replication fərqi: Fərqli versiyalar arasında, fərqli cədvəl strukturları, fərqli index-lər
```

**Replication müqayisəsi:**

| Xüsusiyyət | MySQL | PostgreSQL |
|---|---|---|
| Default metod | Binary Log | WAL Streaming |
| Logical Replication | Bəli (row-based) | Bəli (10+, publication/subscription) |
| Multi-Master | Group Replication | BDR (3rd party), Citus |
| Cascade | Bəli | Bəli |
| Delay monitoring | `SHOW REPLICA STATUS` | `pg_stat_replication` |

---

## Performance Fərqləri

```
Simple SELECT-lər: MySQL bir az sürətli ola bilər (sadə optimizer)
Complex JOIN-lər: PostgreSQL daha yaxşı (daha ağıllı query planner)
Complex subquery-lər: PostgreSQL daha yaxşı
JSON əməliyyatları: PostgreSQL çox daha sürətli (JSONB + GIN index)
Write-heavy workload: MySQL InnoDB bir az daha yaxşı ola bilər
Read-heavy workload: Oxşar performans
Full-text search: PostgreSQL daha güclü
Analytical queries: PostgreSQL daha yaxşı (window functions, CTE optimization)
```

---

## ACID Compliance

Hər iki database tam ACID-uyğundur (MySQL yalnız InnoDB engine-də):

| ACID | MySQL (InnoDB) | PostgreSQL |
|---|---|---|
| **Atomicity** | Undo log | MVCC xmin/xmax |
| **Consistency** | Foreign keys, constraints | Foreign keys, constraints, CHECK, EXCLUDE |
| **Isolation** | 4 level dəstək | 4 level dəstək (SSI ilə true serializable) |
| **Durability** | Redo log (WAL) | WAL |

*həll yanaşmasını üçün kod nümunəsi:*
```sql
-- Isolation Levels
-- READ UNCOMMITTED - dirty reads (PostgreSQL-də READ COMMITTED kimi işləyir)
-- READ COMMITTED - PostgreSQL default
-- REPEATABLE READ - MySQL default
-- SERIALIZABLE

-- PostgreSQL Serializable Snapshot Isolation (SSI)
-- True serializability - phantom read-lər tamamilə bloklanır
BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE;
SELECT * FROM accounts WHERE balance > 100;
UPDATE accounts SET balance = balance - 50 WHERE id = 1;
COMMIT; -- Conflict varsa, avtomatik rollback, yenidən cəhd lazımdır
```

---

## Locking Mechanisms

### Row-Level Locking

*Row-Level Locking üçün kod nümunəsi:*
```sql
-- Hər ikisində: SELECT FOR UPDATE
BEGIN;
SELECT * FROM accounts WHERE id = 1 FOR UPDATE;  -- Row lock
UPDATE accounts SET balance = balance - 100 WHERE id = 1;
COMMIT;

-- PostgreSQL: SELECT FOR UPDATE SKIP LOCKED (queue pattern!)
-- Job queue implementasiyası üçün əla
SELECT * FROM jobs 
WHERE status = 'pending' 
ORDER BY created_at 
LIMIT 1 
FOR UPDATE SKIP LOCKED;

-- MySQL: NOWAIT (8.0+)
SELECT * FROM accounts WHERE id = 1 FOR UPDATE NOWAIT;

-- PostgreSQL: NOWAIT
SELECT * FROM accounts WHERE id = 1 FOR UPDATE NOWAIT;
```

### Advisory Locks (Yalnız PostgreSQL)

*Advisory Locks (Yalnız PostgreSQL) üçün kod nümunəsi:*
```sql
-- Application-level lock (database row-larını lock etmədən)
-- Distributed locking üçün istifadə edilə bilər

-- Session-level advisory lock
SELECT pg_advisory_lock(12345);  -- Lock əldə et
-- ... iş gör ...
SELECT pg_advisory_unlock(12345);  -- Lock burax

-- Transaction-level advisory lock (transaction bitdikdə avtomatik buraxılır)
SELECT pg_advisory_xact_lock(12345);

-- Try lock (blok etmədən)
SELECT pg_try_advisory_lock(12345);  -- true/false qaytarır
```

Laravel-də advisory lock istifadəsi:

*Laravel-də advisory lock istifadəsi üçün kod nümunəsi:*
```php
// PostgreSQL Advisory Lock Laravel-də
class ProcessPayment
{
    public function handle(Order $order): void
    {
        $lockId = crc32("order_{$order->id}");
        
        // Advisory lock əldə et
        $acquired = DB::select("SELECT pg_try_advisory_lock(?) as locked", [$lockId]);
        
        if (!$acquired[0]->locked) {
            throw new \Exception("Order artıq işlənir");
        }
        
        try {
            // Ödəniş əməliyyatı
            $order->update(['status' => 'processing']);
            // ...payment logic...
            $order->update(['status' => 'completed']);
        } finally {
            DB::select("SELECT pg_advisory_unlock(?)", [$lockId]);
        }
    }
}
```

### Table-Level Locking

*Table-Level Locking üçün kod nümunəsi:*
```sql
-- MySQL
LOCK TABLES users WRITE;      -- Exclusive lock
LOCK TABLES users READ;       -- Shared lock
UNLOCK TABLES;

-- PostgreSQL (daha dəqiq lock mode-lar)
LOCK TABLE users IN ACCESS SHARE MODE;           -- SELECT
LOCK TABLE users IN ROW SHARE MODE;              -- SELECT FOR UPDATE/SHARE
LOCK TABLE users IN ROW EXCLUSIVE MODE;          -- UPDATE, DELETE, INSERT
LOCK TABLE users IN SHARE MODE;                  -- CREATE INDEX (concurrent olmayan)
LOCK TABLE users IN ACCESS EXCLUSIVE MODE;       -- ALTER TABLE, DROP TABLE, VACUUM FULL
```

---

## Stored Procedures, Functions, Triggers

### Stored Procedures

*Stored Procedures üçün kod nümunəsi:*
```sql
-- MySQL Stored Procedure
DELIMITER //
CREATE PROCEDURE transfer_money(
    IN from_account INT,
    IN to_account INT,
    IN amount DECIMAL(10,2)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    UPDATE accounts SET balance = balance - amount WHERE id = from_account;
    UPDATE accounts SET balance = balance + amount WHERE id = to_account;
    
    COMMIT;
END //
DELIMITER ;

CALL transfer_money(1, 2, 100.00);

-- PostgreSQL Stored Procedure (11+)
CREATE OR REPLACE PROCEDURE transfer_money(
    from_account INT,
    to_account INT,
    amount DECIMAL(10,2)
)
LANGUAGE plpgsql
AS $$
BEGIN
    UPDATE accounts SET balance = balance - amount WHERE id = from_account;
    UPDATE accounts SET balance = balance + amount WHERE id = to_account;
    -- Procedure-da COMMIT/ROLLBACK istifadə etmək olar (function-da olmaz)
    COMMIT;
END;
$$;

CALL transfer_money(1, 2, 100.00);
```

### Functions

*Functions üçün kod nümunəsi:*
```sql
-- PostgreSQL Function (daha güclü - müxtəlif dillər dəstəklənir)
CREATE OR REPLACE FUNCTION calculate_discount(
    price DECIMAL,
    category TEXT
) RETURNS DECIMAL
LANGUAGE plpgsql
IMMUTABLE  -- eyni input = eyni output (optimizer üçün hint)
AS $$
BEGIN
    RETURN CASE category
        WHEN 'electronics' THEN price * 0.10
        WHEN 'clothing' THEN price * 0.20
        WHEN 'food' THEN price * 0.05
        ELSE price * 0.0
    END;
END;
$$;

SELECT name, price, calculate_discount(price, category) AS discount FROM products;

-- PostgreSQL: Set-returning function
CREATE OR REPLACE FUNCTION get_top_customers(min_orders INT)
RETURNS TABLE(customer_name TEXT, order_count BIGINT)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT u.name, COUNT(o.id)
    FROM users u
    JOIN orders o ON o.user_id = u.id
    GROUP BY u.name
    HAVING COUNT(o.id) >= min_orders
    ORDER BY COUNT(o.id) DESC;
END;
$$;

SELECT * FROM get_top_customers(5);
```

### Triggers

*Triggers üçün kod nümunəsi:*
```sql
-- PostgreSQL Trigger
CREATE OR REPLACE FUNCTION update_modified_timestamp()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER set_updated_at
    BEFORE UPDATE ON products
    FOR EACH ROW
    EXECUTE FUNCTION update_modified_timestamp();

-- Audit log trigger
CREATE OR REPLACE FUNCTION audit_log()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO audit_logs (table_name, record_id, action, old_data, new_data, changed_at)
    VALUES (
        TG_TABLE_NAME,
        COALESCE(NEW.id, OLD.id),
        TG_OP,
        row_to_json(OLD),
        row_to_json(NEW),
        NOW()
    );
    RETURN NEW;
END;
$$;

CREATE TRIGGER orders_audit
    AFTER INSERT OR UPDATE OR DELETE ON orders
    FOR EACH ROW
    EXECUTE FUNCTION audit_log();

-- MySQL Trigger
CREATE TRIGGER before_order_update
    BEFORE UPDATE ON orders
    FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
    INSERT INTO audit_logs (table_name, record_id, action, changed_at)
    VALUES ('orders', NEW.id, 'UPDATE', NOW());
END;
```

---

## Views və Materialized Views

### Regular Views

*Regular Views üçün kod nümunəsi:*
```sql
-- Hər ikisində eynidir
CREATE VIEW active_premium_users AS
SELECT u.id, u.name, u.email, COUNT(o.id) AS order_count, SUM(o.total) AS total_spent
FROM users u
JOIN orders o ON o.user_id = u.id
WHERE u.is_active = true
GROUP BY u.id, u.name, u.email
HAVING SUM(o.total) > 1000;
```

### Materialized Views (Yalnız PostgreSQL)

*Materialized Views (Yalnız PostgreSQL) üçün kod nümunəsi:*
```sql
-- PostgreSQL Materialized View - nəticəni disk-də saxlayır
CREATE MATERIALIZED VIEW monthly_sales AS
SELECT 
    DATE_TRUNC('month', created_at) AS month,
    category,
    COUNT(*) AS total_orders,
    SUM(total) AS revenue,
    AVG(total) AS avg_order_value
FROM orders o
JOIN products p ON p.id = o.product_id
GROUP BY DATE_TRUNC('month', created_at), category
WITH DATA;  -- Dərhal data ilə doldur

-- Index yaratmaq olar (regular view-da olmaz!)
CREATE UNIQUE INDEX idx_monthly_sales ON monthly_sales (month, category);

-- Yeniləmə
REFRESH MATERIALIZED VIEW monthly_sales;

-- CONCURRENTLY: Lock etmədən yeniləmə (UNIQUE index lazımdır)
REFRESH MATERIALIZED VIEW CONCURRENTLY monthly_sales;
```

MySQL-də Materialized View yoxdur, amma workaround:

*MySQL-də Materialized View yoxdur, amma workaround üçün kod nümunəsi:*
```sql
-- MySQL workaround: Cədvəl + Event
CREATE TABLE monthly_sales_cache AS
SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total) AS revenue
FROM orders GROUP BY month;

-- Scheduled event ilə yeniləmə
CREATE EVENT refresh_monthly_sales
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    TRUNCATE TABLE monthly_sales_cache;
    INSERT INTO monthly_sales_cache
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total) AS revenue
    FROM orders GROUP BY month;
END;
```

---

## CTE və Window Functions

### CTE (Common Table Expressions)

*CTE (Common Table Expressions) üçün kod nümunəsi:*
```sql
-- Hər ikisində CTE dəstəklənir
-- Recursive CTE - kateqoriya ağacı
WITH RECURSIVE category_tree AS (
    -- Base case: root kateqoriyalar
    SELECT id, name, parent_id, 0 AS depth, name::TEXT AS path
    FROM categories
    WHERE parent_id IS NULL
    
    UNION ALL
    
    -- Recursive case
    SELECT c.id, c.name, c.parent_id, ct.depth + 1,
           ct.path || ' > ' || c.name
    FROM categories c
    JOIN category_tree ct ON c.parent_id = ct.id
)
SELECT * FROM category_tree ORDER BY path;

-- PostgreSQL: CTE materialization control (12+)
WITH active_users AS MATERIALIZED (  -- Nəticəni cache-lə
    SELECT * FROM users WHERE is_active = true
)
SELECT * FROM active_users WHERE created_at > '2024-01-01';

WITH active_users AS NOT MATERIALIZED (  -- Inline et (subquery kimi)
    SELECT * FROM users WHERE is_active = true
)
SELECT * FROM active_users WHERE created_at > '2024-01-01';
-- MySQL 8.0-da CTE həmişə materialized olur
```

### Window Functions

*Window Functions üçün kod nümunəsi:*
```sql
-- Hər ikisində dəstəklənir (MySQL 8.0+, PostgreSQL çoxdan)
SELECT 
    name,
    department,
    salary,
    -- Sıralama
    ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS rank_in_dept,
    RANK() OVER (ORDER BY salary DESC) AS overall_rank,
    DENSE_RANK() OVER (ORDER BY salary DESC) AS dense_rank,
    
    -- Aggregate window functions
    SUM(salary) OVER (PARTITION BY department) AS dept_total,
    AVG(salary) OVER (PARTITION BY department) AS dept_avg,
    COUNT(*) OVER (PARTITION BY department) AS dept_count,
    
    -- Running total
    SUM(salary) OVER (ORDER BY hire_date ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running_total,
    
    -- Moving average
    AVG(salary) OVER (ORDER BY hire_date ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS moving_avg_3,
    
    -- Lead/Lag
    LAG(salary, 1) OVER (ORDER BY hire_date) AS prev_salary,
    LEAD(salary, 1) OVER (ORDER BY hire_date) AS next_salary,
    
    -- First/Last in partition
    FIRST_VALUE(name) OVER (PARTITION BY department ORDER BY salary DESC) AS highest_paid,
    LAST_VALUE(name) OVER (
        PARTITION BY department ORDER BY salary DESC
        ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
    ) AS lowest_paid,
    
    -- Percent
    PERCENT_RANK() OVER (ORDER BY salary) AS percentile,
    NTILE(4) OVER (ORDER BY salary) AS quartile
FROM employees;

-- PostgreSQL-ə xas: FILTER clause
SELECT 
    department,
    COUNT(*) AS total,
    COUNT(*) FILTER (WHERE salary > 50000) AS high_earners,
    AVG(salary) FILTER (WHERE hire_date > '2023-01-01') AS new_hire_avg
FROM employees
GROUP BY department;
-- MySQL-də FILTER yoxdur, CASE WHEN ilə etmək lazımdır
```

---

## Full-Text Search Fərqləri

*Full-Text Search Fərqləri üçün kod nümunəsi:*
```sql
-- MySQL Full-Text Search
ALTER TABLE articles ADD FULLTEXT INDEX ft_idx (title, body);

SELECT *, MATCH(title, body) AGAINST('laravel php' IN NATURAL LANGUAGE MODE) AS relevance
FROM articles
WHERE MATCH(title, body) AGAINST('laravel php' IN NATURAL LANGUAGE MODE);

-- Boolean mode
SELECT * FROM articles
WHERE MATCH(title, body) AGAINST('+laravel -wordpress +php' IN BOOLEAN MODE);
-- + mütləq olmalı, - olmamalı, heç biri olmadan optional

-- PostgreSQL Full-Text Search (daha güclü)
-- tsvector: sənədi axtarışa hazır formata çevirir
-- tsquery: axtarış sorğusu

SELECT *,
    ts_rank(to_tsvector('english', title || ' ' || body), plainto_tsquery('english', 'laravel php')) AS rank
FROM articles
WHERE to_tsvector('english', title || ' ' || body) @@ plainto_tsquery('english', 'laravel php')
ORDER BY rank DESC;

-- Fərqli query tipləri
SELECT * FROM articles WHERE 
    to_tsvector('english', body) @@ to_tsquery('english', 'laravel & php');         -- AND
SELECT * FROM articles WHERE 
    to_tsvector('english', body) @@ to_tsquery('english', 'laravel | wordpress');    -- OR  
SELECT * FROM articles WHERE 
    to_tsvector('english', body) @@ to_tsquery('english', '!wordpress');             -- NOT
SELECT * FROM articles WHERE 
    to_tsvector('english', body) @@ to_tsquery('english', 'web <-> development');    -- phrase (ardıcıl)

-- Headline (snippet yaratma)
SELECT ts_headline('english', body, plainto_tsquery('english', 'laravel'), 
    'StartSel=<b>, StopSel=</b>, MaxWords=35, MinWords=15') 
FROM articles;

-- Stored generated column + GIN index (ən yaxşı performans)
ALTER TABLE articles ADD COLUMN search_vector tsvector
    GENERATED ALWAYS AS (
        setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
        setweight(to_tsvector('english', coalesce(body, '')), 'B')
    ) STORED;
CREATE INDEX idx_articles_search ON articles USING GIN (search_vector);
```

---

## EXPLAIN ANALYZE

*EXPLAIN ANALYZE üçün kod nümunəsi:*
```sql
-- MySQL EXPLAIN
EXPLAIN SELECT * FROM orders WHERE user_id = 5;
-- type: ALL (full scan), index, range, ref, eq_ref, const
-- key: istifadə olunan index
-- rows: scan edilən təxmini row sayı

EXPLAIN ANALYZE SELECT * FROM orders WHERE user_id = 5;  -- MySQL 8.0.18+
-- Faktiki icra məlumatı: actual time, rows, loops

EXPLAIN FORMAT=JSON SELECT * FROM orders WHERE user_id = 5;
EXPLAIN FORMAT=TREE SELECT * FROM orders WHERE user_id = 5;  -- 8.0.16+

-- PostgreSQL EXPLAIN
EXPLAIN SELECT * FROM orders WHERE user_id = 5;
-- Seq Scan, Index Scan, Index Only Scan, Bitmap Index Scan
-- cost: startup_cost..total_cost
-- rows: təxmini row sayı

EXPLAIN ANALYZE SELECT * FROM orders WHERE user_id = 5;
-- actual time, actual rows, loops, planning time, execution time

EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) SELECT * FROM orders WHERE user_id = 5;
-- shared_hit (cache), shared_read (disk), temp_read/write

-- PostgreSQL auto_explain extension
-- Yavaş query-ləri avtomatik log-layır
LOAD 'auto_explain';
SET auto_explain.log_min_duration = '100ms';
SET auto_explain.log_analyze = true;
```

---

## Connection Pooling

### PgBouncer (PostgreSQL)

*PgBouncer (PostgreSQL) üçün kod nümunəsi:*
```ini
; pgbouncer.ini
[databases]
myapp = host=127.0.0.1 port=5432 dbname=myapp

[pgbouncer]
listen_addr = 0.0.0.0
listen_port = 6432
auth_type = md5

; Pool modes:
; session   - connection session boyunca saxlanılır (default)
; transaction - hər transaction sonrası connection qaytarılır (ən çox istifadə)
; statement - hər statement sonrası qaytarılır (prepared statements işləmir)
pool_mode = transaction

default_pool_size = 20
max_client_conn = 1000
min_pool_size = 5
reserve_pool_size = 5
reserve_pool_timeout = 3
```

### MySQL Connection Pooling

*MySQL Connection Pooling üçün kod nümunəsi:*
```ini
# MySQL ProxySQL konfiqurasiyası
# MySQL-in öz connection pooling-i yoxdur, ProxySQL istifadə olunur

# Və ya PHP PDO persistent connections:
# PDO::ATTR_PERSISTENT => true

# Laravel-də:
# .env
# DB_CONNECTION=mysql
# MySQL 8.0 thread pool plugin (Enterprise)
```

Laravel-də connection pooling:

*Laravel-də connection pooling üçün kod nümunəsi:*
```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('PGBOUNCER_PORT', '6432'), // PgBouncer portu
    'database' => env('DB_DATABASE', 'myapp'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'search_path' => 'public',
    'sslmode' => 'prefer',
    // PgBouncer transaction mode istifadə edəndə
    // prepared statements deaktiv edilməlidir
    'options' => [
        \PDO::ATTR_EMULATE_PREPARES => true,
    ],
],
```

---

## PostgreSQL-ə Xas Xüsusiyyətlər

### LISTEN/NOTIFY (Real-time events)

*LISTEN/NOTIFY (Real-time events) üçün kod nümunəsi:*
```sql
-- Session 1: LISTEN
LISTEN order_created;

-- Session 2: NOTIFY
NOTIFY order_created, '{"order_id": 123, "total": 99.99}';

-- Session 1 notification alır:
-- Asynchronous notification "order_created" with payload "{"order_id": 123}" received.
```

Laravel-də istifadə:

*Laravel-də istifadə üçün kod nümunəsi:*
```php
// PostgreSQL LISTEN/NOTIFY Laravel-də
use Illuminate\Support\Facades\DB;

class OrderNotificationListener
{
    public function listen(): void
    {
        $pdo = DB::connection('pgsql')->getPdo();
        $pdo->exec("LISTEN order_created");
        
        while (true) {
            // Notification-ı gözlə
            $result = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 10000); // 10 saniyə timeout
            
            if ($result) {
                $payload = json_decode($result['payload'], true);
                $this->processOrder($payload['order_id']);
            }
        }
    }
}

// Notification göndərmə
class OrderService
{
    public function createOrder(array $data): Order
    {
        $order = Order::create($data);
        
        DB::statement("NOTIFY order_created, ?", [
            json_encode(['order_id' => $order->id, 'total' => $order->total])
        ]);
        
        return $order;
    }
}
```

### LATERAL JOIN

*LATERAL JOIN üçün kod nümunəsi:*
```sql
-- Hər istifadəçinin son 3 sifarişini gətir (subquery-dən correlated)
SELECT u.name, recent_orders.*
FROM users u
CROSS JOIN LATERAL (
    SELECT o.id, o.total, o.created_at
    FROM orders o
    WHERE o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 3
) AS recent_orders;

-- MySQL-də eyni nəticə üçün window function lazımdır (8.0+)
-- və ya daha mürəkkəb subquery
SELECT name, id AS order_id, total, created_at FROM (
    SELECT u.name, o.id, o.total, o.created_at,
        ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY o.created_at DESC) AS rn
    FROM users u
    JOIN orders o ON o.user_id = u.id
) sub WHERE rn <= 3;
-- MySQL 8.0.14+ LATERAL JOIN dəstəkləyir
```

### UPSERT (INSERT ... ON CONFLICT)

*UPSERT (INSERT ... ON CONFLICT) üçün kod nümunəsi:*
```sql
-- PostgreSQL UPSERT
INSERT INTO products (sku, name, price, stock)
VALUES ('ABC123', 'Widget', 29.99, 100)
ON CONFLICT (sku) DO UPDATE SET
    price = EXCLUDED.price,
    stock = products.stock + EXCLUDED.stock,
    updated_at = NOW();

-- ON CONFLICT DO NOTHING
INSERT INTO user_preferences (user_id, key, value)
VALUES (1, 'theme', 'dark')
ON CONFLICT (user_id, key) DO NOTHING;

-- MySQL equivalent
INSERT INTO products (sku, name, price, stock)
VALUES ('ABC123', 'Widget', 29.99, 100)
ON DUPLICATE KEY UPDATE
    price = VALUES(price),
    stock = stock + VALUES(stock),
    updated_at = NOW();
```

### EXCLUDE Constraint

*EXCLUDE Constraint üçün kod nümunəsi:*
```sql
-- Room booking: eyni otaqda üst-üstə düşən rezervasiya olmasın
CREATE TABLE bookings (
    id SERIAL PRIMARY KEY,
    room_id INT,
    during TSRANGE,
    EXCLUDE USING GiST (room_id WITH =, during WITH &&)
);

-- Bu constraint avtomatik olaraq overlap-ı rədd edəcək:
INSERT INTO bookings (room_id, during) VALUES (1, '[2024-01-10, 2024-01-15)');
INSERT INTO bookings (room_id, during) VALUES (1, '[2024-01-13, 2024-01-18)'); -- ERROR!
-- MySQL-də bu cür constraint yoxdur, trigger və ya application logic lazımdır
```

---

## MySQL-ə Xas Xüsusiyyətlər

### GROUP_CONCAT

*GROUP_CONCAT üçün kod nümunəsi:*
```sql
-- MySQL GROUP_CONCAT
SELECT 
    u.name,
    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS tags
FROM users u
JOIN user_tags ut ON ut.user_id = u.id
JOIN tags t ON t.id = ut.tag_id
GROUP BY u.id;

-- PostgreSQL equivalent: STRING_AGG və ya ARRAY_AGG
SELECT 
    u.name,
    STRING_AGG(DISTINCT t.name, ', ' ORDER BY t.name) AS tags,
    ARRAY_AGG(DISTINCT t.name ORDER BY t.name) AS tags_array
FROM users u
JOIN user_tags ut ON ut.user_id = u.id
JOIN tags t ON t.id = ut.tag_id
GROUP BY u.id;
```

### Storage Engines

*Storage Engines üçün kod nümunəsi:*
```sql
-- MySQL Storage Engines
-- InnoDB (default, 2010+): ACID, row-level locking, foreign keys, MVCC
CREATE TABLE orders (id INT PRIMARY KEY) ENGINE=InnoDB;

-- MyISAM (köhnə default): table-level locking, full-text (köhnə), transaction yox
CREATE TABLE logs (id INT PRIMARY KEY) ENGINE=MyISAM;

-- MEMORY: RAM-da, server restart-da itir, temp data üçün
CREATE TABLE sessions_tmp (id INT PRIMARY KEY) ENGINE=MEMORY;

-- ARCHIVE: Yalnız INSERT və SELECT, yüksək sıxılma, log/arxiv üçün
CREATE TABLE access_logs (id INT PRIMARY KEY) ENGINE=ARCHIVE;

-- PostgreSQL-də storage engine konsepti yoxdur, hər şey bir engine-dir
-- Amma table access method var (PostgreSQL 12+):
-- heap (default), columnar (Citus extension ilə)
```

---

## Laravel-də İstifadə

### Migration Fərqləri

*Migration Fərqləri üçün kod nümunəsi:*
```php
// Laravel migration - PostgreSQL xas xüsusiyyətlər

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // UUID primary key (hər ikisində işləyir, amma PostgreSQL-də native)
            $table->uuid('id')->primary();
            
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            
            // JSONB (PostgreSQL) / JSON (MySQL)
            $table->jsonb('attributes')->nullable(); // PostgreSQL-də jsonb, MySQL-də json
            
            $table->timestamps();
        });

        // PostgreSQL-ə xas index-lər (raw statement lazımdır)
        if (DB::getDriverName() === 'pgsql') {
            // GIN index for JSONB
            DB::statement('CREATE INDEX idx_products_attributes ON products USING GIN (attributes)');
            
            // Partial index
            DB::statement('CREATE INDEX idx_active_products ON products (name) WHERE deleted_at IS NULL');
            
            // Expression index
            DB::statement('CREATE INDEX idx_products_name_lower ON products (LOWER(name))');
            
            // Full-text search vector column
            DB::statement("ALTER TABLE products ADD COLUMN search_vector tsvector 
                GENERATED ALWAYS AS (
                    setweight(to_tsvector('english', coalesce(name, '')), 'A') ||
                    setweight(to_tsvector('english', coalesce(description, '')), 'B')
                ) STORED");
            DB::statement('CREATE INDEX idx_products_search ON products USING GIN (search_vector)');
        }
    }
}

// PostgreSQL Array type migration
class CreateArticlesTable extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
        
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE articles ADD COLUMN tags TEXT[]');
            DB::statement('CREATE INDEX idx_articles_tags ON articles USING GIN (tags)');
        } else {
            // MySQL fallback: ayrı cədvəl və ya JSON
            Schema::table('articles', function (Blueprint $table) {
                $table->json('tags')->nullable();
            });
        }
    }
}

// Partitioned table migration (PostgreSQL)
class CreateOrdersPartitioned extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                CREATE TABLE orders (
                    id BIGSERIAL,
                    user_id BIGINT NOT NULL,
                    total DECIMAL(10,2),
                    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                    PRIMARY KEY (id, created_at)
                ) PARTITION BY RANGE (created_at)
            ");
            
            // Partition-lar yarat
            for ($year = 2023; $year <= 2026; $year++) {
                DB::statement("
                    CREATE TABLE orders_{$year} PARTITION OF orders
                    FOR VALUES FROM ('{$year}-01-01') TO ('" . ($year + 1) . "-01-01')
                ");
            }
        } else {
            // MySQL partitioning
            DB::statement("
                CREATE TABLE orders (
                    id BIGINT AUTO_INCREMENT,
                    user_id BIGINT NOT NULL,
                    total DECIMAL(10,2),
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id, created_at)
                ) PARTITION BY RANGE (YEAR(created_at)) (
                    PARTITION p2023 VALUES LESS THAN (2024),
                    PARTITION p2024 VALUES LESS THAN (2025),
                    PARTITION p2025 VALUES LESS THAN (2026),
                    PARTITION p_future VALUES LESS THAN MAXVALUE
                )
            ");
        }
    }
}
```

### Model Fərqləri

*Model Fərqləri üçün kod nümunəsi:*
```php
// Laravel Model - PostgreSQL array casting

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Product extends Model
{
    protected $keyType = 'string'; // UUID üçün
    public $incrementing = false;  // UUID üçün
    
    protected $casts = [
        'attributes' => 'array',  // JSON/JSONB avtomatik array-ə cast olur
    ];
    
    // PostgreSQL JSONB query scope-ları
    public function scopeWithAttribute($query, string $key, mixed $value)
    {
        if (config('database.default') === 'pgsql') {
            return $query->whereRaw("attributes @> ?", [json_encode([$key => $value])]);
        }
        
        return $query->where("attributes->{$key}", $value);
    }
    
    // PostgreSQL array query
    public function scopeWithTag($query, string $tag)
    {
        if (config('database.default') === 'pgsql') {
            return $query->whereRaw("? = ANY(tags)", [$tag]);
        }
        
        return $query->whereJsonContains('tags', $tag);
    }
    
    // Full-text search scope
    public function scopeSearch($query, string $term)
    {
        if (config('database.default') === 'pgsql') {
            return $query
                ->whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$term])
                ->orderByRaw("ts_rank(search_vector, plainto_tsquery('english', ?)) DESC", [$term]);
        }
        
        return $query->whereRaw(
            "MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)", [$term]
        );
    }
}

// PostgreSQL-ə xas: UPSERT Laravel-də
// Laravel 8.0+ upsert() metodu hər ikisini dəstəkləyir
Product::upsert(
    [
        ['sku' => 'ABC', 'name' => 'Widget', 'price' => 29.99],
        ['sku' => 'DEF', 'name' => 'Gadget', 'price' => 49.99],
    ],
    uniqueBy: ['sku'],           // conflict column
    update: ['name', 'price']    // yenilənəcək column-lar
);

// Advisory Lock Laravel-də
use Illuminate\Support\Facades\Cache;

// Laravel 10+ PostgreSQL advisory lock driver
Cache::lock('process-order-123', 10)->get(function () {
    // Exclusive iş
});
```

### Query Builder Fərqləri

*Query Builder Fərqləri üçün kod nümunəsi:*
```php
// CTE - Laravel 10.x+ (hər ikisi üçün)
$topCustomers = DB::table('orders')
    ->select('user_id', DB::raw('SUM(total) as total_spent'))
    ->groupBy('user_id')
    ->having('total_spent', '>', 1000);

$results = DB::query()
    ->withExpression('top_customers', $topCustomers)
    ->from('top_customers')
    ->join('users', 'users.id', '=', 'top_customers.user_id')
    ->get();

// Window Functions (raw query lazımdır)
$results = DB::table('employees')
    ->select([
        'name',
        'department',
        'salary',
        DB::raw('RANK() OVER (PARTITION BY department ORDER BY salary DESC) as dept_rank'),
        DB::raw('SUM(salary) OVER (PARTITION BY department) as dept_total'),
    ])
    ->get();

// PostgreSQL LATERAL JOIN (raw)
$results = DB::select("
    SELECT u.name, ro.id as order_id, ro.total, ro.created_at
    FROM users u
    CROSS JOIN LATERAL (
        SELECT o.id, o.total, o.created_at
        FROM orders o
        WHERE o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 3
    ) ro
");

// EXPLAIN ANALYZE Laravel-də
DB::enableQueryLog();
$products = Product::where('price', '>', 100)->get();
$queries = DB::getQueryLog();

// Və ya birbaşa EXPLAIN
$explain = DB::select('EXPLAIN ANALYZE ' . 
    Product::where('price', '>', 100)->toSql(), 
    [100]
);
```

---

## Hansı Halda Hansını Seçməli

### MySQL-i Seç Əgər:

1. **Sadə CRUD əməliyyatları** - Blog, CMS, sadə e-commerce
2. **Read-heavy workload** - Çox oxuma, az yazma
3. **Mövcud komanda MySQL bilir** - Öyrənmə əyrisi azdır
4. **Shared hosting** - Demək olar ki, hər yerdə MySQL var
5. **WordPress, Magento** və s. PHP CMS-lər - MySQL tələb edir
6. **Simple replication** lazımdırsa

### PostgreSQL-i Seç Əgər:

1. **Mürəkkəb sorğular** - Çox JOIN, subquery, CTE, window functions
2. **JSON data** intensiv istifadə - JSONB + GIN index
3. **Geospatial data** - PostGIS dünyanın ən yaxşı spatial extension-ıdır
4. **Full-text search** - Built-in, güclü FTS (Elasticsearch-siz)
5. **Data integrity** kritikdir - Daha güclü constraint-lər
6. **Custom types** lazımdır - Array, Range, Composite types
7. **Concurrency** vacibdir - SSI, advisory locks
8. **Time-series data** - BRIN index, partitioning
9. **Analytical workload** - Window functions, materialized views
10. **Standard uyğunluq** - SQL standartlarına tam uyğun

```
Praktik qərar cədvəli:
┌─────────────────────────────┬──────────┬────────────┐
│ Ssenari                     │ MySQL    │ PostgreSQL │
├─────────────────────────────┼──────────┼────────────┤
│ Blog/CMS                    │ ★★★★★   │ ★★★★       │
│ E-commerce (sadə)           │ ★★★★    │ ★★★★★      │
│ E-commerce (mürəkkəb)       │ ★★★     │ ★★★★★      │
│ Financial system             │ ★★★     │ ★★★★★      │
│ GIS/Maps                    │ ★★      │ ★★★★★      │
│ Data warehouse               │ ★★      │ ★★★★★      │
│ Real-time analytics          │ ★★★     │ ★★★★★      │
│ IoT/Time-series              │ ★★      │ ★★★★★      │
│ SAAS multi-tenant            │ ★★★     │ ★★★★★      │
│ Microservices                │ ★★★★    │ ★★★★       │
│ High-traffic read cache      │ ★★★★★   │ ★★★★       │
└─────────────────────────────┴──────────┴────────────┘
```

---

## Deadlock və Həlli

### Deadlock Nədir?

İki və ya daha çox transaction bir-birinin lock etdiyi resursu gözləyir və heç biri irəliləyə bilmir.

```
Transaction A: Lock Row 1 → Gözlə Row 2 (Transaction B lock edib)
Transaction B: Lock Row 2 → Gözlə Row 1 (Transaction A lock edib)
= DEADLOCK!
```

### MySQL-də Deadlock

*MySQL-də Deadlock üçün kod nümunəsi:*
```sql
-- MySQL deadlock nümunəsi
-- Session 1:
START TRANSACTION;
UPDATE accounts SET balance = balance - 100 WHERE id = 1;  -- Row 1 lock

-- Session 2:
START TRANSACTION;
UPDATE accounts SET balance = balance - 50 WHERE id = 2;   -- Row 2 lock

-- Session 1:
UPDATE accounts SET balance = balance + 100 WHERE id = 2;  -- Gözləyir (Row 2)

-- Session 2:
UPDATE accounts SET balance = balance + 50 WHERE id = 1;   -- DEADLOCK!
-- MySQL avtomatik bir transaction-ı rollback edir (victim seçir)

-- Deadlock məlumatını görmək
SHOW ENGINE INNODB STATUS;  -- LATEST DETECTED DEADLOCK bölməsi

-- Deadlock monitoring
SELECT * FROM performance_schema.data_lock_waits;           -- MySQL 8.0+
SELECT * FROM information_schema.innodb_lock_waits;         -- Köhnə versiyalar
```

### PostgreSQL-də Deadlock

*PostgreSQL-də Deadlock üçün kod nümunəsi:*
```sql
-- PostgreSQL deadlock detection
-- deadlock_timeout parameter (default 1s)
SHOW deadlock_timeout;  -- '1s'

-- Deadlock log-da görünür:
-- ERROR: deadlock detected
-- DETAIL: Process 1234 waits for ShareLock on transaction 5678; blocked by process 9012.
-- HINT: See server log for query details.

-- Aktiv lock-ları görmək
SELECT 
    pid,
    pg_blocking_pids(pid) AS blocked_by,
    query,
    wait_event_type,
    wait_event,
    state
FROM pg_stat_activity
WHERE state != 'idle';

-- Lock monitoring
SELECT 
    l.locktype,
    l.relation::regclass,
    l.mode,
    l.granted,
    a.pid,
    a.query
FROM pg_locks l
JOIN pg_stat_activity a ON a.pid = l.pid
WHERE NOT l.granted;
```

### Deadlock Həll Strategiyaları

*Deadlock Həll Strategiyaları üçün kod nümunəsi:*
```php
// 1. Eyni sırada lock etmək (ən vacib!)
class TransferService
{
    public function transfer(int $fromId, int $toId, float $amount): void
    {
        // Həmişə kiçik ID-ni əvvəl lock et
        $ids = [$fromId, $toId];
        sort($ids);
        
        DB::transaction(function () use ($ids, $fromId, $toId, $amount) {
            // Sıralanmış şəkildə lock et - deadlock riski aradan qalxır
            $accounts = Account::whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            
            $accounts[$fromId]->decrement('balance', $amount);
            $accounts[$toId]->increment('balance', $amount);
        });
    }
}

// 2. Retry mexanizmi
class DeadlockRetryService
{
    public function executeWithRetry(callable $callback, int $maxRetries = 3): mixed
    {
        $attempts = 0;
        
        while (true) {
            try {
                return DB::transaction($callback);
            } catch (\Illuminate\Database\DeadlockException $e) {
                $attempts++;
                
                if ($attempts >= $maxRetries) {
                    throw $e;
                }
                
                // Exponential backoff
                usleep(100000 * pow(2, $attempts)); // 200ms, 400ms, 800ms
                
                Log::warning("Deadlock detected, retry #{$attempts}", [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}

// İstifadə:
$service = new DeadlockRetryService();
$service->executeWithRetry(function () {
    $order = Order::lockForUpdate()->find(1);
    $order->update(['status' => 'processing']);
    // ...
});

// 3. NOWAIT və ya SKIP LOCKED istifadə etmək
$order = Order::where('status', 'pending')
    ->orderBy('created_at')
    ->limit(1)
    ->lockForUpdate()      // PostgreSQL/MySQL: FOR UPDATE
    // ->lockForUpdateSkipLocked() // Laravel: skip locked rows (queue pattern)
    ->first();

// 4. Transaction-u qısa tutmaq
// PİS:
DB::transaction(function () {
    $order = Order::find(1);
    
    // Uzun API çağırışı - bu vaxt lock saxlanılır!
    $paymentResult = PaymentGateway::charge($order->total);
    
    $order->update(['status' => 'paid']);
});

// YAXŞI:
$order = Order::find(1);
$paymentResult = PaymentGateway::charge($order->total); // Lock olmadan

DB::transaction(function () use ($order, $paymentResult) {
    $order = Order::lockForUpdate()->find($order->id); // Qısa lock
    $order->update([
        'status' => 'paid',
        'payment_id' => $paymentResult->id,
    ]);
});
```

---

## Query Optimization Techniques

### Index Optimization

*Index Optimization üçün kod nümunəsi:*
```php
// 1. Composite Index sırası vacibdir (Leftmost Prefix Rule)
// INDEX (a, b, c) bu sorğuları dəstəkləyir:
// WHERE a = ?              ✓
// WHERE a = ? AND b = ?    ✓
// WHERE a = ? AND b = ? AND c = ?  ✓
// WHERE b = ?              ✗ (a olmadan işləmir)
// WHERE b = ? AND c = ?    ✗

Schema::table('orders', function (Blueprint $table) {
    // Bu index: WHERE user_id = ? AND status = ? ORDER BY created_at
    $table->index(['user_id', 'status', 'created_at']);
});

// 2. Covering Index (Index-only scan)
// Bütün lazım olan column-lar index-dədir, cədvələ getmək lazım deyil
// PostgreSQL: INCLUDE
DB::statement('CREATE INDEX idx_orders_covering ON orders (user_id, status) INCLUDE (total, created_at)');

// MySQL: composite index-in özü covering index kimi işləyir

// 3. Slow Query tapma
// MySQL
DB::select("SELECT * FROM sys.statements_with_full_table_scans LIMIT 10");

// PostgreSQL  
DB::select("
    SELECT query, calls, mean_exec_time, total_exec_time 
    FROM pg_stat_statements 
    ORDER BY total_exec_time DESC 
    LIMIT 10
");
```

### Query Optimization

*Query Optimization üçün kod nümunəsi:*
```php
// 1. SELECT * əvəzinə lazımi column-lar
// PİS:
$users = User::all();

// YAXŞI:
$users = User::select(['id', 'name', 'email'])->get();

// 2. N+1 problemi
// PİS:
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->user->name; // Hər iteration-da query
}

// YAXŞI:
$orders = Order::with('user')->get(); // Eager loading
$orders = Order::with('user:id,name')->get(); // Seçici eager loading

// 3. Chunk processing (böyük data üçün)
Order::where('status', 'pending')
    ->chunk(1000, function ($orders) {
        foreach ($orders as $order) {
            // Process...
        }
    });

// Daha yaxşı: lazy loading (memory efficient)
Order::where('status', 'pending')
    ->lazy(1000)
    ->each(function ($order) {
        // Process...
    });

// 4. Subquery optimization
// PİS: N+1 aggregate
$users = User::all()->map(function ($user) {
    $user->order_count = $user->orders()->count();
    return $user;
});

// YAXŞI: Subquery select
$users = User::withCount('orders')->get();

// Və ya manual subquery
$users = User::select('users.*')
    ->selectSub(
        Order::selectRaw('COUNT(*)')->whereColumn('orders.user_id', 'users.id'),
        'order_count'
    )
    ->get();

// 5. Batch operations
// PİS:
foreach ($items as $item) {
    Product::create($item);  // N query
}

// YAXŞI:
Product::insert($items);  // 1 query
// Və ya timestamps lazımdırsa:
Product::upsert($items, ['sku'], ['name', 'price']);

// 6. Raw query üçün index hint (MySQL)
$results = DB::select("
    SELECT /*+ INDEX(orders idx_user_status) */ *
    FROM orders 
    WHERE user_id = ? AND status = ?
", [1, 'pending']);

// PostgreSQL: planner hint (pg_hint_plan extension)
// SET pg_hint_plan.enable_hint = on;
// SELECT /*+ IndexScan(orders idx_user_status) */ * FROM orders WHERE ...
```

### Caching Strategies

*Caching Strategies üçün kod nümunəsi:*
```php
// Database query cache
$products = Cache::remember('products.featured', 3600, function () {
    return Product::where('is_featured', true)
        ->with('category')
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();
});

// Model event ilə cache invalidation
class Product extends Model
{
    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('products.featured');
        });
        
        static::deleted(function () {
            Cache::forget('products.featured');
        });
    }
}
```

---

## İntervyu Sualları və Cavabları

### S1: MySQL və PostgreSQL arasındakı əsas fərqlər nələrdir?

**Cavab:** MySQL sürət və sadəliyə fokuslanır, web tətbiqləri üçün populyardır. PostgreSQL standartlara uyğunluq və genişlənə bilmə üzərində fokuslanır. PostgreSQL JSONB, Array, Range kimi zəngin data type-lar, GIN/GiST/BRIN index-ləri, Materialized Views, Advisory Locks, LISTEN/NOTIFY, EXCLUDE constraint kimi xüsusiyyətlər təklif edir. MySQL isə storage engine seçimi (InnoDB, MyISAM), GROUP_CONCAT, daha sadə replication setup təklif edir.

### S2: MVCC nədir və MySQL ilə PostgreSQL-də necə fərqlənir?

**Cavab:** MVCC reader-lərin writer-ları blok etməməsini təmin edir. PostgreSQL köhnə row versiyalarını eyni cədvəldə saxlayır (xmin/xmax ilə) və VACUUM ilə təmizləyir. MySQL/InnoDB isə köhnə versiyaları undo log-da saxlayır və purge thread ilə təmizləyir. PostgreSQL-in yanaşması "table bloat" riskini artırır (VACUUM lazımdır), MySQL-in yanaşması isə uzun transaction-larda undo log-un böyüməsinə səbəb ola bilər.

### S3: JSONB nədir və niyə JSON-dan yaxşıdır?

**Cavab:** JSONB (PostgreSQL) JSON data-nı binary formatda saxlayır. Əsas üstünlükləri: (1) GIN index ilə indekslənə bilər - sorğular çox sürətli olur, (2) duplicate key-lər avtomatik silinir, (3) key sırası saxlanılmır amma sorğular sürətlidir, (4) `@>`, `?`, `?|`, `?&` kimi güclü operatorlar. Regular JSON isə text olaraq saxlanılır, hər sorğuda parse edilir, indekslənə bilmir.

### S4: PostgreSQL-in GIN index-i nədir və nə üçün istifadə olunur?

**Cavab:** GIN (Generalized Inverted Index) composite dəyərləri indeksləmək üçün istifadə olunur. JSONB sütunlarında key/value axtarışı, Array sütunlarında element axtarışı, Full-text search (tsvector), Trigram axtarış (pg_trgm) üçün idealdır. Hər bir dəyər üçün onu ehtiva edən row-ların siyahısını saxlayır (inverted index prinsipi).

### S5: Deadlock nədir və necə qarşısını almaq olar?

**Cavab:** İki transaction bir-birinin lock etdiyi resursu gözlədikdə yaranır. Qarşısının alınması: (1) Resursları həmişə eyni sırada lock et (ID-yə görə sort et), (2) Transaction-ları qısa tut, (3) NOWAIT/SKIP LOCKED istifadə et, (4) Retry mexanizmi qur (exponential backoff ilə), (5) İndex-lər əlavə edərək lock müddətini azalt.

### S6: MySQL-in storage engine-ləri arasındakı fərq nədir?

**Cavab:** InnoDB (default) ACID uyğundur, row-level locking, foreign key, MVCC dəstəkləyir - əksər hallarda istifadə olunmalıdır. MyISAM table-level locking edir, transaction dəstəkləmir, amma bəzi read-heavy ssenarilərdə sürətli ola bilər (artıq tövsiyə edilmir). MEMORY bütün data-nı RAM-da saxlayır, temporary data üçündür. ARCHIVE yalnız INSERT/SELECT dəstəkləyir, log/arxiv üçündür.

### S7: Materialized View nədir?

**Cavab:** PostgreSQL-ə xas xüsusiyyətdir. Regular view hər sorğuda yenidən hesablanır, amma Materialized View nəticəni disk-də saxlayır. Üstünlüyü: mürəkkəb sorğular çox sürətli olur, index yaratmaq olar. Mənfi tərəfi: data köhnələ bilər, manual REFRESH lazımdır. REFRESH MATERIALIZED VIEW CONCURRENTLY ilə lock etmədən yeniləmək mümkündür (UNIQUE index tələb edir). Reporting, dashboard-lar, analytical queries üçün idealdır.

### S8: Laravel-də PostgreSQL-ə xas xüsusiyyətləri necə istifadə edərsiniz?

**Cavab:** DB::statement() ilə raw SQL istifadə ederek GIN/GiST index yaratmaq, JSONB operatorları üçün whereRaw() istifadə etmək, Array type üçün raw DDL, LISTEN/NOTIFY üçün PDO-nun pgsqlGetNotify metodu, Advisory Lock üçün pg_advisory_lock funksiyaları, Materialized View üçün raw statement, Full-text search üçün tsvector/tsquery ilə search scope yaratmaq. Laravel 10+ uuid column type, jsonb column type natively dəstəkləyir.

### S9: Connection Pooling nədir və niyə vacibdir?

**Cavab:** PostgreSQL hər connection üçün ayrı process yaradır (~10MB RAM). 100 connection = 1GB RAM. PgBouncer connection pool yaradır: tətbiq PgBouncer-a 1000 connection aça bilər, PgBouncer isə PostgreSQL-ə cəmi 20 connection açır. Transaction mode ən populyardır - transaction bitdikdə connection pool-a qaytarılır. MySQL thread-based olduğu üçün daha az resursa ehtiyac duyur, amma ProxySQL ilə connection pooling istifadə edilə bilər.

### S10: EXPLAIN ANALYZE çıxışını necə oxuyarsınız?

**Cavab:** PostgreSQL-də: Seq Scan (tam cədvəl skanı - pis), Index Scan (yaxşı), Index Only Scan (ən yaxşı - covering index), Bitmap Index Scan (çox row qaytaranda). cost=startup..total (arbitrary vahid), actual time (ms), rows (faktiki row sayı), loops (neçə dəfə icra olunub). BUFFERS ilə shared_hit (cache) vs shared_read (disk) - disk read çox olarsa index lazımdır. MySQL-də: type sütunu: ALL (pis) → index → range → ref → eq_ref → const (yaxşı). rows sütunu scan edilən row sayıdır, Extra sütununda "Using index" (covering), "Using filesort" (sort lazımdır), "Using temporary" (temp cədvəl) görünür.

### S11: Generated Columns nədir? MySQL ilə PostgreSQL-də fərqi?

**Cavab:** Generated Column — digər sütunlardan avtomatik hesablanan sütundur. İki növü var: **VIRTUAL** (hər oxunuşda hesablanır, disk tutmur) və **STORED/PERSISTED** (disk-ə yazılır, indekslənə bilər). İstifadə: tam ad (`first_name || ' ' || last_name`), JSON path extract, hesablanmış dəyərlər. MySQL 5.7+, PostgreSQL 12+ dəstəkləyir. PostgreSQL-də `GENERATED ALWAYS AS (expr) STORED`. Fərq: MySQL həm VIRTUAL həm STORED, PostgreSQL yalnız STORED. Generated column-lara index yaratmaq mümkündür — bu, `WHERE full_name = ?` sorğularını sürətləndirir.
***Cavab:** Generated Column — digər sütunlardan avtomatik hesablanan s üçün kod nümunəsi:*
```sql
-- MySQL nümunəsi
ALTER TABLE users
ADD COLUMN full_name VARCHAR(255)
GENERATED ALWAYS AS (CONCAT(first_name, ' ', last_name)) STORED;
CREATE INDEX idx_full_name ON users(full_name);
```

### S12: MySQL 8.0-ın əhəmiyyətli yeni xüsusiyyətləri hansılardır?

**Cavab:** MySQL 8.0 çoxlu mühüm xüsusiyyət gətirdi: (1) **Window Functions** — `ROW_NUMBER()`, `RANK()`, `LAG()`, `LEAD()` — analitik sorğularda çox güclü (2) **Common Table Expressions (CTE)** — `WITH` syntax, recursive CTE-lər (3) **Invisible Indexes** — index-i silmədən deaktiv etmək (test üçün) (4) **Hash Join** — böyük cədvəl birləşmələrini sürətləndirir (5) **Descending Indexes** — `ORDER BY col DESC` üçün ayrıca index (6) **JSON improvements** — JSON_TABLE(), multi-valued index (7) **Roles** — istifadəçi idarəçiliyi (8) **utf8mb4 default** — artıq charset problemləri yoxdur. Laravel 9+ minimum MySQL 8.0 tələb edir.

---

## Xülasə Cədvəli

| Xüsusiyyət | MySQL | PostgreSQL |
|---|---|---|
| Default Isolation | REPEATABLE READ | READ COMMITTED |
| MVCC | Undo log | In-place versioning |
| JSON | JSON | JSON + JSONB |
| Array type | Xeyr | Bəli |
| Materialized Views | Xeyr | Bəli |
| Partial Index | Xeyr (8.0 limited) | Bəli |
| GIN/GiST/BRIN | Xeyr | Bəli |
| Advisory Locks | GET_LOCK() (limited) | Tam dəstək |
| LISTEN/NOTIFY | Xeyr | Bəli |
| EXCLUDE Constraint | Xeyr | Bəli |
| LATERAL JOIN | 8.0.14+ | Bəli |
| Stored Procedures | Bəli | Bəli (çox dilli) |
| Storage Engines | Bəli (InnoDB, MyISAM...) | Xeyr (tək engine) |
| GROUP_CONCAT | Bəli | STRING_AGG |
| Connection Model | Thread-per-connection | Process-per-connection |
| Replication | Binary Log | WAL |
| Extensibility | Limited | Çox yüksək |
| SQL Standard | Qismən | Tam uyğun |

---

## Anti-patternlər

**1. Texnolojiyi Tələblər Əvəzinə Populyarlığa Görə Seçmək**
"Hamı MySQL istifadə edir" deyib seçim etmək — JSON sorğuları, full-text search, PostGIS kimi xüsusiyyətlər lazım olduqda işləvsizlik yaranır. Layihənin data növlərini, sorğu patternlərini və xüsusi ehtiyaclarını əvvəlcə analiz edin, sonra DB seçin.

**2. MySQL-də MyISAM Cədvəlləri İstifadə Etmək**
Transaction, foreign key dəstəyi lazım olan yerdə MyISAM engine seçmək — ACID zəmanəti yoxdur, data bütövlüyü pozula bilər, crash zamanı data itirilə bilər. Həmişə InnoDB istifadə edin; MyISAM yalnız çox spesifik full-text search legacy ssenarilər üçündür.

**3. PostgreSQL-də Çoxsaylı Qısa Əlaqə Açmaq**
Hər sorğu üçün yeni connection açmaq — PostgreSQL process-per-connection modeli ilə hər əlaqə ayrı proses yaradır, yaddaş sərfiyyatı artır, performans aşağı düşür. PgBouncer kimi connection pooler mütləq istifadə edilməlidir.

**4. Verilənlər Bazası Xüsusiyyətlərini Gizlətmək**
PostgreSQL-ə keçidin mümkün olması üçün MySQL-in JSONB, array, CTE kimi xüsusiyyətlərindən qaçmaq — bu xüsusiyyətlər çox böyük performans üstünlüyü verə bilər. Köçürmə planı olmadıqca seçilmiş DB-nin gücündən tam istifadə edin.

**5. Charset/Collation-u Düzgün Seçməmək**
MySQL-də `latin1` və ya `utf8` (3-byte) istifadə etmək — emoji və bəzi Unicode simvolları `utf8mb4` tələb edir, yanlış charset seçimi data itkisinə gətirir. MySQL-də həmişə `utf8mb4_unicode_ci`, PostgreSQL-də `UTF8` istifadə edin.

**6. EXPLAIN/EXPLAIN ANALYZE-siz Sorğu Optimallaşdırması**
Yavaş sorğuları hiss ilə optimallaşdırmağa çalışmaq — gerçəkdə hansı indeksin istifadə edildiyi, hansı əməliyyatın ən çox vaxt apardığı bilinmir. Hər optimallaşdırma cəhdindən əvvəl `EXPLAIN ANALYZE` çalışdırın; qərarlara məlumat bazasında dayanın.
