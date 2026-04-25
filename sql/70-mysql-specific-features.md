# MySQL Specific Features (Senior)

## İcmal

MySQL 8.0+, əksər PHP/Laravel layihəsinin default database seçimidir. Bu faylda PostgreSQL analoquna bənzər şəkildə MySQL-in backend developer üçün vacib olan xüsusi feature-ları, davranış fərqləri və praktik tətbiqləri izah olunur.

---

## Niyə Vacibdir

- Laravel-in default `mysql` driver-i InnoDB üzərindədir
- MySQL 8.0 ilə bir çox PostgreSQL feature-ı (window functions, CTEs, JSON) əlavə olundu
- MySQL-in bəzi davranışları PostgreSQL-dən əsaslı fərqlənir — bilməsən silent data corruption baş verə bilər
- Performance tuning PostgreSQL-dən fərqli prinsiplərə əsaslanır

---

## InnoDB Storage Engine

MySQL-in default engine-i. `MyISAM`-dan fərqli olaraq:

| Xüsusiyyət | InnoDB | MyISAM |
|------------|--------|--------|
| ACID | ✅ | ❌ |
| Foreign Keys | ✅ | ❌ |
| Row-level Locking | ✅ | Table-level |
| Crash Recovery | Redo log | Yox |
| Full-text Search | MySQL 5.6+ | ✅ (köhnə) |

**Praktik qeyd:** 2024-ci ildə MyISAM-ı heç bir production layihəsində istifadə etmə. Legacy kodda görsən mütləq InnoDB-yə keçir.

### InnoDB Buffer Pool

```sql
-- Buffer pool ölçüsünü göstər (ideal: RAM-ın 70-80%)
SHOW VARIABLES LIKE 'innodb_buffer_pool_size';

-- Buffer pool hit rate-ni yoxla (>99% olmalıdır)
SHOW STATUS LIKE 'Innodb_buffer_pool_reads';
SHOW STATUS LIKE 'Innodb_buffer_pool_read_requests';

-- my.cnf konfiqurasiyası
-- innodb_buffer_pool_size = 4G  -- 8GB RAM olan server üçün
-- innodb_buffer_pool_instances = 4
```

### Redo Log & Doublewrite Buffer

MySQL 8.0.30+ redo log-u dinamik resize edə bilir:

```sql
-- Redo log ölçüsü (MySQL 8.0.30+)
SET GLOBAL innodb_redo_log_capacity = 8589934592;  -- 8GB

-- Doublewrite buffer (data corruption-a qarşı)
SHOW VARIABLES LIKE 'innodb_doublewrite';
```

---

## MySQL 8.0 Yeni Xüsusiyyətlər

### Roles (İstifadəçi Rolları)

```sql
-- Rol yarat
CREATE ROLE 'app_reader', 'app_writer';

-- İcazə ver
GRANT SELECT ON myapp.* TO 'app_reader';
GRANT INSERT, UPDATE, DELETE ON myapp.* TO 'app_writer';

-- İstifadəçiyə rol ver
GRANT 'app_reader' TO 'john'@'%';
GRANT 'app_reader', 'app_writer' TO 'backend_service'@'10.0.0.%';

-- Aktiv et
SET DEFAULT ROLE ALL TO 'john'@'%';
```

### Invisible Indexes

Zero-downtime index evaluation üçün:

```sql
-- Index-i görünməz et (query planner istifadə etmir, amma mövcuddur)
ALTER TABLE orders ALTER INDEX idx_status INVISIBLE;

-- Test et, slow query-lər artıbsa geri qaytar
ALTER TABLE orders ALTER INDEX idx_status VISIBLE;

-- Zero-downtime index drop workflow:
-- 1. INVISIBLE et → 2. Monitor et (1 həftə) → 3. DROP et
```

### Generated Columns

```sql
-- Virtual column (disk-də saxlanmır, real-time hesablanır)
ALTER TABLE orders
  ADD COLUMN total_with_tax DECIMAL(10,2)
    AS (subtotal * 1.18) VIRTUAL;

-- Stored column (disk-də saxlanır, daha sürətli query)
ALTER TABLE users
  ADD COLUMN full_name VARCHAR(200)
    AS (CONCAT(first_name, ' ', last_name)) STORED;

-- Stored column-a index qoy
CREATE INDEX idx_full_name ON users(full_name);

-- Laravel: virtualAs / storedAs
Schema::table('users', function (Blueprint $table) {
    $table->string('full_name', 200)->storedAs("CONCAT(first_name, ' ', last_name)");
    $table->index('full_name');
});
```

### Descending Indexes

```sql
-- MySQL 8.0+: DESC index real-dir (əvvəl sadəcə ASC index tərsinə oxunurdu)
CREATE INDEX idx_created_desc ON posts(created_at DESC);

-- Composite descending — ORDER BY col1 ASC, col2 DESC üçün ideal
CREATE INDEX idx_composite ON orders(status ASC, created_at DESC);
```

---

## JSON Data Type

```sql
-- JSON kolumn
CREATE TABLE configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    settings JSON NOT NULL
);

-- Insert
INSERT INTO configs (user_id, settings) VALUES (
    1,
    '{"theme": "dark", "notifications": {"email": true, "sms": false}}'
);

-- JSON path ilə select
SELECT
    user_id,
    settings->>'$.theme' AS theme,
    settings->>'$.notifications.email' AS email_notif
FROM configs;

-- JSON update (bir field-i dəyiş, hamısını yox)
UPDATE configs
SET settings = JSON_SET(settings, '$.theme', 'light')
WHERE user_id = 1;

-- JSON array
UPDATE configs
SET settings = JSON_ARRAY_APPEND(settings, '$.tags', 'beta')
WHERE user_id = 1;

-- JSON-a index (generated column vasitəsilə)
ALTER TABLE configs
  ADD COLUMN theme VARCHAR(50) AS (settings->>'$.theme') VIRTUAL;
CREATE INDEX idx_theme ON configs(theme);

-- Laravel JSON where
User::where('settings->theme', 'dark')->get();
User::whereJsonContains('settings->tags', 'beta')->get();
```

---

## MySQL-Specific Funksiyalar

### String Funksiyaları

```sql
-- FIND_IN_SET (CSV sütunları ilə — anti-pattern, amma legacy kodda çox var)
SELECT * FROM posts WHERE FIND_IN_SET('laravel', tags) > 0;
-- tags = 'php,laravel,backend'

-- GROUP_CONCAT — qruplaşdırılmış dəyərləri birləşdir
SELECT
    category_id,
    GROUP_CONCAT(title ORDER BY id SEPARATOR ', ') AS post_titles,
    GROUP_CONCAT(DISTINCT status) AS statuses
FROM posts
GROUP BY category_id;

-- GROUP_CONCAT limit artır (default: 1024 byte)
SET SESSION group_concat_max_len = 1000000;

-- REGEXP_REPLACE (MySQL 8.0+)
UPDATE users
SET phone = REGEXP_REPLACE(phone, '[^0-9]', '');

-- ELT / FIELD (lookup tables üçün)
SELECT ELT(status, 'pending', 'active', 'cancelled') AS status_label
FROM orders;
-- status=1 → 'pending', status=2 → 'active'

SELECT FIELD('active', 'pending', 'active', 'cancelled') AS pos;
-- Nəticə: 2 (index)
```

### Date/Time Funksiyaları

```sql
-- MySQL-in xüsusi date funksiyaları
SELECT
    DATE_FORMAT(created_at, '%Y-%m') AS month,
    COUNT(*) AS orders
FROM orders
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY month
ORDER BY month;

-- TIMESTAMPDIFF
SELECT TIMESTAMPDIFF(DAY, created_at, shipped_at) AS days_to_ship
FROM orders;

-- PERIOD_DIFF (YYYYMM formatı ilə)
SELECT PERIOD_DIFF(202412, 202406);  -- Nəticə: 6

-- STR_TO_DATE
SELECT STR_TO_DATE('25-04-2026', '%d-%m-%Y');

-- Last day of month
SELECT LAST_DAY('2026-02-01');  -- 2026-02-28

-- Working days hesabı (weekend-ləri skip et)
-- MySQL-də built-in yoxdur, stored function lazımdır
```

### Ədəd Funksiyaları

```sql
-- TRUNCATE vs ROUND
SELECT TRUNCATE(3.789, 2);  -- 3.78 (kəsir, yuvarlaqlaşdırmır)
SELECT ROUND(3.789, 2);     -- 3.79

-- DIV (integer division)
SELECT 17 DIV 5;  -- 3 (float yox)

-- BIT_AND, BIT_OR, BIT_XOR aggregate
SELECT BIT_OR(permissions) AS combined_permissions
FROM user_roles
WHERE user_id = 1;
```

---

## Full-Text Search

```sql
-- Full-text index (InnoDB, MySQL 5.6+)
ALTER TABLE articles ADD FULLTEXT INDEX ft_content (title, body);

-- Natural language mode (default)
SELECT *, MATCH(title, body) AGAINST ('laravel queues') AS relevance
FROM articles
WHERE MATCH(title, body) AGAINST ('laravel queues')
ORDER BY relevance DESC;

-- Boolean mode (operator dəstəyi)
SELECT title
FROM articles
WHERE MATCH(title, body) AGAINST (
    '+laravel +queue -redis' IN BOOLEAN MODE
);
-- + = mütləq olmalı, - = olmamalı, * = wildcard

-- Query expansion mode
SELECT title
FROM articles
WHERE MATCH(title, body) AGAINST (
    'database' WITH QUERY EXPANSION
);
-- İlk pass-da tapılanlardan yeni terminlər çıxarır, ikinci pass-da genişləndirir

-- ft_min_word_len (default: 4) — qısa sözlər axtarılmır
-- my.cnf: ft_min_word_len = 2
-- Dəyişiklikdən sonra: REPAIR TABLE articles QUICK;
```

---

## MySQL Replication Xüsusiyyətləri

### Binary Log (Binlog)

```sql
-- Binlog status
SHOW MASTER STATUS;
SHOW BINARY LOGS;

-- Binlog format (Laravel üçün ROW tövsiyə olunur)
SHOW VARIABLES LIKE 'binlog_format';
-- STATEMENT: SQL əmrlər log-lanır (non-deterministic funksiyalarda problem)
-- ROW: dəyişən row-lar log-lanır (daha etibarlı)
-- MIXED: avtomatik seçir

-- my.cnf
-- binlog_format = ROW
-- binlog_row_image = MINIMAL  -- yalnız dəyişən kolonlar

-- Binlog-u oxu
mysqlbinlog --start-datetime="2026-04-25 10:00:00" \
            --stop-datetime="2026-04-25 11:00:00" \
            /var/lib/mysql/mysql-bin.000001
```

### GTID Replication

```sql
-- GTID-nin aktiv olduğunu yoxla
SHOW VARIABLES LIKE 'gtid_mode';
SHOW VARIABLES LIKE 'enforce_gtid_consistency';

-- Replica statusu
SHOW REPLICA STATUS\G
-- Seconds_Behind_Source: 0 = sync-dir

-- GTID ilə replication lag izlə
SELECT
    GTID_SUBTRACT(
        @@GLOBAL.gtid_executed,
        (SELECT received_transaction_set FROM performance_schema.replication_connection_status)
    ) AS missing_transactions;
```

---

## Performance Schema & Diagnostics

```sql
-- Ən yavaş sorğular
SELECT
    DIGEST_TEXT,
    COUNT_STAR,
    AVG_TIMER_WAIT / 1e12 AS avg_seconds,
    SUM_ROWS_EXAMINED / COUNT_STAR AS avg_rows_examined
FROM performance_schema.events_statements_summary_by_digest
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 10;

-- Hansı cədvəllər lock-lanır
SELECT * FROM performance_schema.data_lock_waits\G

-- Index istifadə edilməyən sorğular
SELECT OBJECT_SCHEMA, OBJECT_NAME, INDEX_NAME
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE INDEX_NAME IS NOT NULL
  AND COUNT_STAR = 0
  AND OBJECT_SCHEMA NOT IN ('mysql', 'performance_schema')
ORDER BY OBJECT_SCHEMA, OBJECT_NAME;

-- sys schema (human-readable)
SELECT * FROM sys.statements_with_full_table_scans LIMIT 10;
SELECT * FROM sys.schema_unused_indexes;
SELECT * FROM sys.schema_index_statistics ORDER BY rows_selected DESC LIMIT 20;
```

---

## MySQL-Specific Pitfall-lar

### Silent Data Truncation (STRICT mode yoxdursa)

```sql
-- Strict mode olmadan: 'hello world' → 'hello' (5-char VARCHAR-a)
-- Strict mode ilə: ERROR!

SHOW VARIABLES LIKE 'sql_mode';
-- STRICT_TRANS_TABLES aktiv olmalıdır

-- Laravel .env
DB_STRICT=true  -- database.php-də strict: true

-- Əl ilə set et
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO';
```

### ONLY_FULL_GROUP_BY

```sql
-- MySQL 5.7+ default: ONLY_FULL_GROUP_BY aktiv
-- Bu sorğu ERROR verir:
SELECT user_id, name, COUNT(*) FROM orders GROUP BY user_id;
-- name GROUP BY-da yoxdur

-- Düzgün:
SELECT user_id, ANY_VALUE(name), COUNT(*) FROM orders GROUP BY user_id;
-- və ya:
SELECT user_id, name, COUNT(*) FROM orders GROUP BY user_id, name;
```

### Implicit Type Conversion

```sql
-- Bu index işlətmir! (string kolona integer müqayisə)
SELECT * FROM users WHERE phone = 447911123456;
-- phone VARCHAR tipindədir

-- Düzgün:
SELECT * FROM users WHERE phone = '447911123456';

-- Laravel: həmişə doğru tipdə dəyər ötür
User::where('phone', $phone)->first();  -- $phone string olmalıdır
```

### DATETIME vs TIMESTAMP

```sql
-- TIMESTAMP: UTC-də saxlanır, timezone-a görə convert edilir (range: 1970–2038!)
-- DATETIME: literal dəyər saxlanır, timezone convert yoxdur

-- Laravel migration
$table->timestamp('created_at');  -- UTC, auto-timezone
$table->dateTime('scheduled_at'); -- literal, timezone yoxdur

-- 2038 problemi: timestamp kolonları olan köhnə sistemlər!
-- Həll: DATETIME istifadə et, ya da BIGINT (Unix timestamp)
```

---

## Praktik Tapşırıqlar

### 1. MySQL 8 JSON Features

```php
// Laravel: User settings JSON kolonu
Schema::create('user_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique();
    $table->json('preferences')->default('{}');
    $table->timestamps();
});

// Virtual column + index
DB::statement("ALTER TABLE user_settings ADD COLUMN theme VARCHAR(50)
    AS (preferences->>'$.theme') VIRTUAL");
DB::statement("CREATE INDEX idx_theme ON user_settings(theme)");

// Eloquent ilə JSON query
UserSetting::where('preferences->theme', 'dark')
    ->whereJsonContains('preferences->notifications', ['email' => true])
    ->get();
```

### 2. Full-text Search Laravel Integration

```php
// Migration
Schema::table('articles', function (Blueprint $table) {
    $table->fullText(['title', 'body']);
});

// Eloquent (raw expression lazımdır)
Article::whereRaw(
    'MATCH(title, body) AGAINST(? IN BOOLEAN MODE)',
    ['+' . implode(' +', explode(' ', $query))]
)
->selectRaw('*, MATCH(title, body) AGAINST(? IN BOOLEAN MODE) as relevance', [$query])
->orderByDesc('relevance')
->get();
```

### 3. Performance Schema İzləmə

```bash
# En yavaş 5 sorğunu tap
mysql -u root -p -e "
SELECT SUBSTR(DIGEST_TEXT, 1, 100) as query,
       COUNT_STAR as count,
       ROUND(AVG_TIMER_WAIT/1e12, 3) as avg_sec
FROM performance_schema.events_statements_summary_by_digest
ORDER BY AVG_TIMER_WAIT DESC LIMIT 5;
"
```

---

## Əlaqəli Mövzular

- [MySQL vs PostgreSQL](25-mysql-vs-postgresql.md)
- [PostgreSQL Specific Features](69-postgresql-specific-features.md)
- [Indexing & Index Algorithms](27-indexing.md)
- [Query Optimization & EXPLAIN](28-query-optimization.md)
- [Replication](59-replication.md)
- [Storage Internals](67-storage-internals-wal-buffer-pool.md)
