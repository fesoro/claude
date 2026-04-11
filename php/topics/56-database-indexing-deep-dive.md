# Database Indexing — Dərin Analiz

Senior PHP Developer müsahibə hazırlığı üçün hərtərəfli bələdçi.

---

## 1. Index-lər Daxilən Necə İşləyir — B-Tree Strukturu

### B-Tree (Balanced Tree) nədir?

B-Tree, verilənlər bazasında ən geniş istifadə olunan index strukturudur. Hər node (qovşaq) müəyyən sayda açar (key) saxlayır və uşaq node-lara göstərici (pointer) daşıyır. Ağac həmişə balanslaşdırılmış qalır — yəni kök node-dan hər bir yarpaq (leaf) node-a eyni məsafə var.

### B-Tree ASCII Diaqramı

```
                        [Root Node]
                     [30 | 60 | 90]
                    /    |    |    \
                   /     |    |     \
          [10|20] [40|50] [70|80] [95|100]
           / \     / \     / \      / \
          /   \   /   \   /   \    /   \
      [Leaf] [Leaf] [Leaf] [Leaf] [Leaf] [Leaf]
       10,20  40,50  70,80  95,100
       (row   (row   (row   (row
       ptrs)  ptrs)  ptrs)  ptrs)
```

**Daha ətraflı B+Tree leaf node zənciri:**

```
Leaf Node-lar arasında ikitərəfli bağlı siyahı (doubly linked list):

[Leaf 1: 1,2,3] <-> [Leaf 2: 4,5,6] <-> [Leaf 3: 7,8,9]
   |  |  |                |  |  |            |  |  |
  row row row            row row row        row row row
  ptr ptr ptr            ptr ptr ptr        ptr ptr ptr

- Hər leaf node: key dəyəri + row pointer (heap ya da PK) saxlayır
- Range scan zamanı leaf node-lar üzrə sürətli keçid mümkündür
```

### B-Tree Axtarış Mürəkkəbliyi

| Əməliyyat | Mürəkkəblik |
|-----------|-------------|
| Axtarış   | O(log n)    |
| Daxiletmə | O(log n)    |
| Silmə     | O(log n)    |
| Range scan| O(log n + k)|

- `n` — cədvəldəki sətir sayı
- `k` — tapılan sətirlərin sayı

### Node Strukturu (InnoDB)

```
InnoDB default page ölçüsü: 16 KB

[Page Header | Key1 | Ptr1 | Key2 | Ptr2 | ... | KeyN | PtrN | Page Footer]

- Internal node: yalnız key və child pointer-lər
- Leaf node: key + actual data (clustered) və ya key + PK (secondary)
```

---

## 2. Primary vs Secondary Index-lər

### Primary Index

Primary index cədvəlin əsas identifikatoruna (PRIMARY KEY) qurulmuş index-dir.

*Primary index cədvəlin əsas identifikatoruna (PRIMARY KEY) qurulmuş in üçün kod nümunəsi:*
```sql
CREATE TABLE users (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email     VARCHAR(255) NOT NULL,
    name      VARCHAR(100),
    PRIMARY KEY (id)          -- Bu primary index-dir
);
```

**Xüsusiyyətlər:**
- Hər cədvəldə yalnız bir primary index ola bilər
- NULL dəyər qəbul etmir
- InnoDB-də clustered index kimi işləyir (məlumat fiziki olaraq bu index üzrə sıralanır)
- Unikal olmaq məcburidir

### Secondary Index (İkinci dərəcəli index)

Primary key-dən başqa hər hansı sütun üzərindəki index-lərdir.

*Primary key-dən başqa hər hansı sütun üzərindəki index-lərdir üçün kod nümunəsi:*
```sql
CREATE TABLE users (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email     VARCHAR(255) NOT NULL,
    name      VARCHAR(100),
    created_at DATETIME,
    PRIMARY KEY (id),
    INDEX idx_email (email),          -- Secondary index
    INDEX idx_created_at (created_at) -- Secondary index
);
```

**Xüsusiyyətlər:**
- Bir cədvəldə çox sayda secondary index ola bilər
- InnoDB-də hər secondary index leaf node-da primary key dəyərini saxlayır
- Secondary index-dən istifadə zamanı əlavə bir "lookup" lazımdır (primary index-ə gediş)

### Fərq Cədvəli

| Xüsusiyyət          | Primary Index       | Secondary Index         |
|---------------------|---------------------|-------------------------|
| Say                 | Cədvəldə bir        | Çox ola bilər           |
| NULL                | Yox                 | Bəzən (UNIQUE deyilsə)  |
| InnoDB data yeri    | Leaf node-da data   | Leaf node-da PK pointer |
| Yaradılma           | Avtomatik (PK ilə)  | Manual                  |
| Clustered           | Bəli (InnoDB)       | Xeyr                    |

---

## 3. Clustered vs Non-clustered Index-lər (MySQL InnoDB)

### Clustered Index

Fiziki məlumatın sıralandığı index-dir. InnoDB-də hər cədvəlin mütləq bir clustered index-i var.

```
Clustered Index B-Tree leaf node-ları:

[PK=1 | name="Ali"   | email="ali@test.az"   | ...]
[PK=2 | name="Veli"  | email="veli@test.az"  | ...]
[PK=3 | name="Nihad" | email="nihad@test.az" | ...]
        ^
        Actual row data leaf node-da birbaşa saxlanılır
```

**InnoDB Clustered Index Seçimi Qaydası:**
1. Əgər `PRIMARY KEY` var isə — o clustered index olur
2. Əgər yoxdursa — ilk `UNIQUE NOT NULL` sütun götürülür
3. Heç biri yoxdursa — InnoDB gizli 6-byte `DB_ROW_ID` yaradır

*3. Heç biri yoxdursa — InnoDB gizli 6-byte `DB_ROW_ID` yaradır üçün kod nümunəsi:*
```sql
-- Pis praktika: UUID primary key (random insert, B-Tree fragmentation)
CREATE TABLE orders (
    id   CHAR(36) PRIMARY KEY,  -- UUID: sırasız daxiletmə → B-Tree split-lər
    ...
);

-- Yaxşı praktika: ardıcıl (monotonic) PK
CREATE TABLE orders (
    id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ...
);
```

### Non-clustered Index

Ayrı bir B-Tree strukturu saxlayır, leaf node-larda isə əsl data yox, clustered index key (PK) saxlanılır.

```
Secondary (Non-clustered) Index:

Index B-Tree leaf node-ları:
[email="ali@test.az"   | PK=1]
[email="nihad@test.az" | PK=3]
[email="veli@test.az"  | PK=2]
                          ^
                          Clustered index-ə pointer (PK dəyəri)

Sorğu icra axını:
1. Secondary index-də email axtarılır → PK tapılır
2. Clustered index-də PK ilə tam sətir tapılır (bu "double lookup"-dur)
```

### Double Lookup Problem

*Double Lookup Problem üçün kod nümunəsi:*
```sql
-- Bu sorğu double lookup edir:
SELECT name, email FROM users WHERE email = 'ali@test.az';

-- 1. idx_email-də "ali@test.az" tapılır → PK=1 alınır
-- 2. Clustered index-də PK=1 ilə tam sətir oxunur

-- Covering index ilə bu problem həll olunur (bax: Bölmə 5)
```

### PostgreSQL Fərqi

```
PostgreSQL-də HEAP + Index ayrıdır:
- Heap: məlumat fiziki olaraq sırasız saxlanılır
- Index: ayrı B-Tree, leaf node-da heap page pointer (ctid) saxlayır
- "Clustered" index PostgreSQL-də CLUSTER əmri ilə fiziki sıralama deməkdir,
  lakin sonrakı INSERT-lər bu sıranı pozmur
```

---

## 4. Composite Index-lər — Sütun Sırası Vacibdir

### Leftmost Prefix Qaydası

Composite index yalnız soldan başlayan sütun kombinasiyaları üçün işləyir.

*Composite index yalnız soldan başlayan sütun kombinasiyaları üçün işlə üçün kod nümunəsi:*
```sql
-- Composite index yaradılması:
CREATE INDEX idx_last_first ON users (last_name, first_name, birth_year);
```

**Hansı sorğular bu index-dən istifadə edə bilər:**

```sql
-- ✅ Tam istifadə (3 sütun)
SELECT * FROM users WHERE last_name = 'Əliyev' AND first_name = 'Murad' AND birth_year = 1990;

-- ✅ İlk iki sütun
SELECT * FROM users WHERE last_name = 'Əliyev' AND first_name = 'Murad';

-- ✅ Yalnız birinci sütun
SELECT * FROM users WHERE last_name = 'Əliyev';

-- ❌ Ortadan başlamaq olmaz
SELECT * FROM users WHERE first_name = 'Murad';

-- ❌ Axırıncı sütun tək işləmir
SELECT * FROM users WHERE birth_year = 1990;

-- ⚠️ İlk sütun range condition-dırsa, sonrakılar istifadə olunmur
SELECT * FROM users WHERE last_name > 'Ə' AND first_name = 'Murad';
-- last_name range-dən sonra first_name filter üçün istifadə olunmur
```

### Sütun Sırasının Seçilməsi Strategiyası

*Sütun Sırasının Seçilməsi Strategiyası üçün kod nümunəsi:*
```sql
-- Ssenari: istifadəçiləri status və created_at üzrə süzgəcləmək

-- Pis sıra (status low cardinality-dir, əvvəlcə qoymaq yaxşı deyil):
CREATE INDEX idx_bad ON orders (status, user_id, created_at);

-- Yaxşı sıra (equality əvvəl, range axırda):
CREATE INDEX idx_good ON orders (user_id, status, created_at);

-- Qayda: Equality columns → High cardinality columns → Range columns
```

### Real Nümunə

*Real Nümunə üçün kod nümunəsi:*
```sql
-- orders cədvəli üçün optimal composite index:
CREATE TABLE orders (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    status     ENUM('pending','paid','shipped','cancelled'),
    created_at DATETIME NOT NULL,
    total      DECIMAL(10,2)
);

-- Bu sorğu üçün:
SELECT * FROM orders
WHERE user_id = 42
  AND status = 'paid'
  AND created_at >= '2024-01-01'
ORDER BY created_at DESC;

-- Optimal index:
CREATE INDEX idx_user_status_date ON orders (user_id, status, created_at);
-- user_id → equality, status → equality, created_at → range/order
```

---

## 5. Covering Index-lər — Index-Only Scan

### Covering Index nədir?

Bir sorğunun ehtiyac duyduğu bütün sütunlar index-in özündə mövcuddursa, MySQL cədvələ (clustered index-ə) müraciət etmədən nəticəni birbaşa index-dən qaytara bilər. Bu "index-only scan" adlanır.

*Bir sorğunun ehtiyac duyduğu bütün sütunlar index-in özündə mövcuddurs üçün kod nümunəsi:*
```sql
-- Cədvəl:
CREATE TABLE products (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    name        VARCHAR(200) NOT NULL,
    description TEXT
);

-- Non-covering index (table lookup lazımdır):
CREATE INDEX idx_category ON products (category_id);

SELECT id, name, price FROM products WHERE category_id = 5;
-- 1. idx_category-də category_id=5 tapılır → PK list alınır
-- 2. Hər PK üçün clustered index-dən sətir oxunur (name, price üçün)

-- Covering index (table lookup yoxdur):
CREATE INDEX idx_category_covering ON products (category_id, price, name);

SELECT id, name, price FROM products WHERE category_id = 5;
-- 1. idx_category_covering-də category_id=5 tapılır
-- 2. Leaf node-da artıq price, name var + PK (id) var
-- 3. Cədvələ müraciət etməyə ehtiyac yoxdur!
```

### EXPLAIN ilə Covering Index Yoxlanması

*EXPLAIN ilə Covering Index Yoxlanması üçün kod nümunəsi:*
```sql
EXPLAIN SELECT id, name, price FROM products WHERE category_id = 5;

-- Covering index varsa:
-- Extra: "Using index"   ← bu sətri görməlisiniz

-- Covering index yoxdursa:
-- Extra: NULL  (table lookup baş verir)
-- Extra: "Using index condition"  (ICP — Index Condition Pushdown)
```

### InnoDB-də Xüsusi Hal: PK Həmişə Covering-dədir

*InnoDB-də Xüsusi Hal: PK Həmişə Covering-dədir üçün kod nümunəsi:*
```sql
-- InnoDB secondary index leaf node-larında PK həmişə var.
-- Belə ki, PK sütunu covering index-ə əlavə etməyə ehtiyac yoxdur.

CREATE INDEX idx_cat_price ON products (category_id, price);

-- Bu sorğu covering olacaq (id — PK):
SELECT id, category_id, price FROM products WHERE category_id = 5;
-- id artıq leaf node-da var (PK kimi)
```

### Covering Index Nə Vaxt Həqiqətən Lazımdır?

*Covering Index Nə Vaxt Həqiqətən Lazımdır? üçün kod nümunəsi:*
```sql
-- Çox oxunan, performance-kritik sorğular
-- Analytics sorğuları (böyük cədvəllər üzərində aggregation)

-- Nümunə: gündəlik satış hesabatı
CREATE INDEX idx_orders_report
    ON orders (status, created_at, total, user_id);

SELECT
    DATE(created_at) AS day,
    COUNT(*) AS order_count,
    SUM(total) AS revenue
FROM orders
WHERE status = 'paid'
  AND created_at BETWEEN '2024-01-01' AND '2024-12-31'
GROUP BY DATE(created_at);
-- Bütün lazımi sütunlar (status, created_at, total) index-dədir
-- Table lookup yoxdur → Çox sürətli!
```

---

## 6. Index Selectivity və Cardinality

### Cardinality

Bir sütundakı unikal dəyərlərin sayıdır.

*Bir sütundakı unikal dəyərlərin sayıdır üçün kod nümunəsi:*
```sql
-- Cardinality-ni yoxlamaq:
SHOW INDEX FROM users;
-- Cardinality sütununa baxın

-- Daha dəqiq:
SELECT
    COUNT(DISTINCT status)     AS status_cardinality,      -- az (məs: 4)
    COUNT(DISTINCT user_id)    AS user_id_cardinality,     -- çox (məs: 100000)
    COUNT(DISTINCT email)      AS email_cardinality,       -- çox yüksək
    COUNT(*)                   AS total_rows
FROM orders;
```

### Selectivity

```
Selectivity = Cardinality / Total Rows

Yüksək selectivity (0.9 → 1.0): Hər dəyər demək olar unikaldır (email, UUID)
Aşağı selectivity (0.0 → 0.1):  Az sayda unikal dəyər (status, gender, boolean)
```

*Aşağı selectivity (0.0 → 0.1):  Az sayda unikal dəyər (status, gender, üçün kod nümunəsi:*
```sql
-- Selectivity hesablanması:
SELECT
    COUNT(DISTINCT email) / COUNT(*) AS email_selectivity,
    COUNT(DISTINCT status) / COUNT(*) AS status_selectivity
FROM users;

-- Nəticə:
-- email_selectivity:  0.9998  (çox yaxşı index candidate)
-- status_selectivity: 0.0003  (zəif index candidate)
```

### Optimizer Qərarı

*Optimizer Qərarı üçün kod nümunəsi:*
```sql
-- MySQL optimizer aşağı selectivity-li index-i ignore edə bilər:
-- Məsələn, "active" sütununun yalnız 2 unikal dəyəri varsa (0/1),
-- cədvəlin 60%-i active=1 olarsa, optimizer full table scan seçər.

-- Yoxlamaq üçün:
EXPLAIN SELECT * FROM users WHERE is_active = 1;
-- type: ALL  → full table scan (optimizer index-i ignore etdi)
-- type: ref  → index istifadə edildi
```

---

## 7. Index-lərin Zərər Verdiyi Hallar

### 7.1. Aşağı Cardinality Sütunlar

*7.1. Aşağı Cardinality Sütunlar üçün kod nümunəsi:*
```sql
-- Pis nümunə: boolean sütuna index
CREATE TABLE articles (
    id         INT PRIMARY KEY,
    title      VARCHAR(255),
    is_deleted TINYINT(1) DEFAULT 0,  -- yalnız 0 ya 1
    content    TEXT
);

-- Bu index demək olar heç vaxt istifadə olunmaz:
CREATE INDEX idx_deleted ON articles (is_deleted);

-- Çünki cədvəlin 95%-i is_deleted=0 olarsa,
-- optimizer full scan etməyi daha sürətli hesab edir.

-- İstisna: Partial index (PostgreSQL-də):
CREATE INDEX idx_deleted_only ON articles (id) WHERE is_deleted = 1;
-- Yalnız deleted sətirləri index-ləyir (az say, yüksək selectivity)
```

### 7.2. Write-Heavy Cədvəllərdə Çox Index

*7.2. Write-Heavy Cədvəllərdə Çox Index üçün kod nümunəsi:*
```sql
-- Hər INSERT/UPDATE/DELETE əməliyyatı bütün index-ləri yeniləməlidir.

CREATE TABLE events (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    event_type VARCHAR(50),
    payload    JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Problemli: 8 index olan write-heavy cədvəl
CREATE INDEX idx1 ON events (user_id);
CREATE INDEX idx2 ON events (event_type);
CREATE INDEX idx3 ON events (created_at);
CREATE INDEX idx4 ON events (user_id, event_type);
CREATE INDEX idx5 ON events (user_id, created_at);
CREATE INDEX idx6 ON events (event_type, created_at);
CREATE INDEX idx7 ON events (user_id, event_type, created_at);
CREATE INDEX idx8 ON events (created_at, event_type);

-- Hər INSERT üçün 8 B-Tree yenilənir!
-- Yüksək I/O, lock contentions, disk yazma yavaşlığı

-- Qayda: Read-heavy → daha çox index; Write-heavy → minimal index
```

### 7.3. Böyük Sütunlar üzərindəki Index

*7.3. Böyük Sütunlar üzərindəki Index üçün kod nümunəsi:*
```sql
-- Problemli: uzun string sütununa tam index
CREATE INDEX idx_desc ON products (description(500));
-- Index ölçüsü çox böyük olur, memory-də saxlamaq çətin

-- Həll: Prefix index
CREATE INDEX idx_name_prefix ON products (name(50));
-- Yalnız ilk 50 simvol index-lənir

-- Lakin: prefix index covering index ola bilməz!
```

### 7.4. Funksiya Çağırışları WHERE-də

*7.4. Funksiya Çağırışları WHERE-də üçün kod nümunəsi:*
```sql
-- ❌ Index işləmir (sütuna funksiya tətbiq edilir):
SELECT * FROM users WHERE YEAR(created_at) = 2024;
SELECT * FROM users WHERE LOWER(email) = 'ali@test.az';
SELECT * FROM orders WHERE DATE(created_at) = '2024-01-15';

-- ✅ Index işləyir (aralıq istifadə et):
SELECT * FROM users
WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01';

-- ✅ MySQL 5.7+ Function-Based Index (virtual sütun):
ALTER TABLE users ADD COLUMN email_lower VARCHAR(255)
    GENERATED ALWAYS AS (LOWER(email)) VIRTUAL;
CREATE INDEX idx_email_lower ON users (email_lower);

-- ✅ PostgreSQL Function-Based Index:
CREATE INDEX idx_email_lower ON users (LOWER(email));
SELECT * FROM users WHERE LOWER(email) = 'ali@test.az';
```

---

## 8. EXPLAIN / EXPLAIN ANALYZE — Execution Plan-ları Oxumaq

### EXPLAIN Nəticəsinin Sütunları

*EXPLAIN Nəticəsinin Sütunları üçün kod nümunəsi:*
```sql
EXPLAIN SELECT u.name, COUNT(o.id) as order_count
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
WHERE u.created_at >= '2024-01-01'
GROUP BY u.id;
```

**Nümunə nəticə:**

```
+----+-------------+-------+------------+------+--------------------+------------------+---------+----------------+-------+----------+-------------+
| id | select_type | table | partitions | type | possible_keys      | key              | key_len | ref            | rows  | filtered | Extra       |
+----+-------------+-------+------------+------+--------------------+------------------+---------+----------------+-------+----------+-------------+
|  1 | SIMPLE      | u     | NULL       | range| idx_created_at     | idx_created_at   | 5       | NULL           |  5420 |   100.00 | Using where |
|  1 | SIMPLE      | o     | NULL       | ref  | idx_user_id        | idx_user_id      | 4       | mydb.u.id      |     3 |   100.00 | NULL        |
+----+-------------+-------+------------+------+--------------------+------------------+---------+----------------+-------+----------+-------------+
```

### `type` Sütunu — Ən Vacib Göstərici

| Type   | Performans    | Açıqlama                                          |
|--------|---------------|---------------------------------------------------|
| system | Ən yaxşı      | Cədvəldə yalnız 1 sətir var                       |
| const  | Çox yaxşı     | PK və ya UNIQUE ilə tam uyğunluq                  |
| eq_ref | Çox yaxşı     | JOIN-də PK/UNIQUE istifadəsi                      |
| ref    | Yaxşı         | Non-unique index ilə uyğunluq                     |
| range  | Qəbul edilir  | Index üzərindən aralıq (>, <, BETWEEN, IN)        |
| index  | Pis           | Full index scan (table scan-dan cüzi yaxşı)       |
| ALL    | Ən pis        | Full table scan — indekslənməmiş sorğu            |

### `Extra` Sütununun Şərhi

```
Using index         → Covering index (ən yaxşı)
Using where         → WHERE filter table-level-da tətbiq edilir
Using temporary     → Geçici cədvəl yaradılır (GROUP BY, ORDER BY)
Using filesort      → Sıralama əməliyyatı (memory ya disk)
Using index condition → Index Condition Pushdown (ICP)
Using join buffer   → JOIN üçün buffer istifadəsi (index yoxdur)
```

### EXPLAIN ANALYZE (MySQL 8.0+ / PostgreSQL)

*EXPLAIN ANALYZE (MySQL 8.0+ / PostgreSQL) üçün kod nümunəsi:*
```sql
-- MySQL 8.0+:
EXPLAIN ANALYZE
SELECT * FROM orders
WHERE user_id = 42 AND status = 'paid'
ORDER BY created_at DESC
LIMIT 10;
```

**Nümunə nəticə:**

```
-> Limit: 10 row(s)  (cost=0.71 rows=10) (actual time=0.045..0.052 rows=10 loops=1)
    -> Index range scan on orders using idx_user_status_date
       over (user_id = 42 AND status = 'paid'), with index condition: ...
       (cost=0.71 rows=10) (actual time=0.042..0.048 rows=10 loops=1)
```

**PostgreSQL EXPLAIN ANALYZE:**

```sql
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT * FROM orders WHERE user_id = 42;
```

```
Index Scan using idx_user_id on orders
  (cost=0.43..8.45 rows=12 width=120)
  (actual time=0.025..0.031 rows=12 loops=1)
  Index Cond: (user_id = 42)
Buffers: shared hit=4
Planning Time: 0.156 ms
Execution Time: 0.058 ms
```

### Slow Query Aşkarlanması

*Slow Query Aşkarlanması üçün kod nümunəsi:*
```sql
-- MySQL slow query log aktivləşdirmə:
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;  -- 1 saniyədən uzun sorğuları log et
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow.log';

-- Performance Schema ilə slow sorğuları tapmaq:
SELECT
    DIGEST_TEXT,
    COUNT_STAR AS exec_count,
    AVG_TIMER_WAIT / 1e12 AS avg_seconds,
    SUM_ROWS_EXAMINED / COUNT_STAR AS avg_rows_examined
FROM performance_schema.events_statements_summary_by_digest
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 10;
```

---

## 9. Index Növləri

### 9.1. Hash Index

*9.1. Hash Index üçün kod nümunəsi:*
```sql
-- Hash index yalnız equality (=) üçün işləyir, range üçün deyil.
-- MySQL: MEMORY engine-də istifadə edilir.
-- InnoDB: Adaptive Hash Index (avtomatik, əl ilə idarə edilmir)

-- MySQL MEMORY cədvəlində açıq hash index:
CREATE TABLE cache_table (
    cache_key   VARCHAR(255) PRIMARY KEY,
    cache_value TEXT,
    expires_at  DATETIME
) ENGINE=MEMORY;

-- Hash: O(1) lookup, lakin:
-- ❌ Range queries: WHERE id > 100
-- ❌ ORDER BY
-- ❌ LIKE prefix: WHERE name LIKE 'Ali%'
-- ✅ Equality only: WHERE cache_key = 'user:42'
```

**PostgreSQL-də açıq Hash Index:**

```sql
CREATE INDEX idx_hash_email ON users USING HASH (email);
-- Yalnız = operatoru üçün, B-tree-dən daha az yer tutur
```

### 9.2. Full-Text Index

*9.2. Full-Text Index üçün kod nümunəsi:*
```sql
-- MySQL Full-Text:
CREATE TABLE articles (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    title   VARCHAR(255),
    body    TEXT,
    FULLTEXT idx_ft (title, body)
);

-- Əlavə etmə:
ALTER TABLE articles ADD FULLTEXT INDEX idx_fulltext (title, body);

-- İstifadə:
SELECT *, MATCH(title, body) AGAINST ('verilənlər bazası') AS relevance
FROM articles
WHERE MATCH(title, body) AGAINST ('verilənlər bazası' IN BOOLEAN MODE)
ORDER BY relevance DESC;

-- Boolean mode operatorları:
-- +word  → mütləq olmalı
-- -word  → olmamalı
-- *      → wildcard
-- "..."  → tam ifadə
SELECT * FROM articles
WHERE MATCH(title, body)
    AGAINST ('+PHP -JavaScript "verilənlər bazası"' IN BOOLEAN MODE);
```

**PostgreSQL Full-Text (GIN index):**

```sql
CREATE INDEX idx_fts ON articles
    USING GIN (to_tsvector('english', title || ' ' || body));

SELECT * FROM articles
WHERE to_tsvector('english', title || ' ' || body)
    @@ to_tsquery('english', 'database & indexing');
```

### 9.3. Spatial Index (GIS)

*9.3. Spatial Index (GIS) üçün kod nümunəsi:*
```sql
-- MySQL Spatial Index (R-Tree):
CREATE TABLE locations (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(100),
    position POINT NOT NULL SRID 4326,
    SPATIAL INDEX idx_position (position)
);

INSERT INTO locations (name, position)
VALUES ('Bakı', ST_GeomFromText('POINT(49.8671 40.4093)', 4326));

-- Yaxın yerləri tapmaq (10 km radius):
SELECT name,
    ST_Distance_Sphere(position, ST_GeomFromText('POINT(49.8671 40.4093)', 4326)) AS distance_m
FROM locations
WHERE ST_Distance_Sphere(position, ST_GeomFromText('POINT(49.8671 40.4093)', 4326)) <= 10000
ORDER BY distance_m;
```

### 9.4. Partial Index (PostgreSQL)

*9.4. Partial Index (PostgreSQL) üçün kod nümunəsi:*
```sql
-- Yalnız müəyyən şərti ödəyən sətirləri index-ləmək:

-- Yalnız aktiv istifadəçilər üçün:
CREATE INDEX idx_active_users ON users (email)
WHERE is_active = TRUE;

-- Yalnız ödənilməmiş sifarişlər:
CREATE INDEX idx_pending_orders ON orders (user_id, created_at)
WHERE status = 'pending';

-- Üstünlükləri:
-- - Daha kiçik index (disk, memory)
-- - Daha yüksək selectivity
-- - Sürətli yazma (bütün sətirləri deyil, yalnız şərti ödəyənləri index-ləyir)

-- MySQL-də partial index yoxdur (Generated Column workaround):
ALTER TABLE orders
    ADD COLUMN is_pending TINYINT GENERATED ALWAYS AS (status = 'pending') VIRTUAL;
CREATE INDEX idx_pending ON orders (is_pending, user_id)
WHERE is_pending = 1;  -- Bu hissə MySQL-də işləmir, yalnız PostgreSQL!
```

### 9.5. GIN və GiST Index-lər (PostgreSQL)

*9.5. GIN və GiST Index-lər (PostgreSQL) üçün kod nümunəsi:*
```sql
-- GIN (Generalized Inverted Index): Array, JSONB, Full-text
CREATE INDEX idx_tags ON posts USING GIN (tags);  -- tags: text[]
SELECT * FROM posts WHERE tags @> ARRAY['php', 'mysql'];

-- JSONB üçün GIN:
CREATE INDEX idx_meta ON products USING GIN (metadata);
SELECT * FROM products WHERE metadata @> '{"color": "red"}';

-- GiST (Generalized Search Tree): Geometric types, Range types
CREATE INDEX idx_date_range ON bookings USING GiST (date_range);
SELECT * FROM bookings WHERE date_range && '[2024-01-01, 2024-01-31]'::daterange;
```

---

## 10. Index Bloat — Səbəblər və Baxım

### Index Bloat nədir?

Çox sayda silmə (DELETE) və yeniləmə (UPDATE) əməliyyatı nəticəsində B-Tree node-larında boş yer qalır. Buna "bloat" deyilir.

```
Normal B-Tree page:         Bloated B-Tree page:
[K1|K2|K3|K4|K5|K6|K7|K8]  [K1|   |K3|   |K5|   |K7|   ]
                                  ^deleted   ^deleted
Fill Factor: 100%            Fill Factor: ~50%
```

### Bloat Səbəbləri

```
1. DELETE: Silinmiş sıralar page-dən dərhal yox olmur (dead tuples)
2. UPDATE: Köhnə dəyər saxlanır (MVCC), yenisi əlavə edilir
3. Random INSERT-lər: UUID kimi sırasız key-lər B-Tree split-lərə səbəb olur
4. Vacuum gecikmə: PostgreSQL-də autovacuum işləmədikdə dead tuples birikir
```

### MySQL-də Baxım

*MySQL-də Baxım üçün kod nümunəsi:*
```sql
-- İndex fragmentasiyasını yoxlamaq:
SELECT
    table_name,
    index_name,
    stat_value AS pages,
    ROUND(stat_value * @@innodb_page_size / 1024 / 1024, 2) AS size_mb
FROM mysql.innodb_index_stats
WHERE database_name = 'mydb'
  AND stat_name = 'size'
ORDER BY stat_value DESC;

-- Fragmentasiyanı aradan qaldırmaq (OPTIMIZE TABLE):
OPTIMIZE TABLE orders;
-- Bu əməliyyat:
-- 1. Cədvəlin tam kopyasını yaradır
-- 2. B-Tree-i yenidən qurur (defragment)
-- 3. Köhnə faylı silir
-- Diqqət: Böyük cədvəllərdə çox vaxt ala bilər, cədvəl kilidlənə bilər!

-- Online alternative (pt-online-schema-change):
pt-online-schema-change --alter "ENGINE=InnoDB" D=mydb,t=orders --execute

-- InnoDB table statistics yeniləmə:
ANALYZE TABLE orders;
-- Optimizer üçün statistikaları yeniləyir (actual defragment etmir)
```

### PostgreSQL-də Baxım

*PostgreSQL-də Baxım üçün kod nümunəsi:*
```sql
-- Bloat-ı yoxlamaq:
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) AS table_size,
    n_dead_tup AS dead_tuples,
    n_live_tup AS live_tuples,
    ROUND(n_dead_tup::numeric / NULLIF(n_live_tup + n_dead_tup, 0) * 100, 2) AS dead_pct
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC;

-- VACUUM (dead tuples-ı təmizlə, disk yerini GERİ qaytarmır):
VACUUM orders;

-- VACUUM FULL (disk yerini geri qaytarır, cədvəli kilidləyir!):
VACUUM FULL orders;

-- REINDEX (index-i yenidən qur):
REINDEX INDEX idx_user_id;
REINDEX TABLE orders;  -- bütün index-lər

-- Canlı sistemdə (PostgreSQL 12+):
REINDEX INDEX CONCURRENTLY idx_user_id;
-- Kilidləmədən index-i yenidən qurur

-- Autovacuum konfiqurasiyası:
ALTER TABLE orders SET (
    autovacuum_vacuum_scale_factor = 0.01,  -- 1% dead tuple-da vacuum başlasın
    autovacuum_analyze_scale_factor = 0.005
);
```

### Fill Factor

*Fill Factor üçün kod nümunəsi:*
```sql
-- Index-lərdə yeniləmələr çox olarsa, fill factor azaltmaq lazımdır:
-- (Hər page-in 80%-ni doldur, 20% UPDATE-lər üçün saxla)

-- PostgreSQL:
CREATE INDEX idx_orders_status ON orders (status) WITH (fillfactor = 80);

-- MySQL: innodb_fill_factor (qlobal parametr)
SET GLOBAL innodb_fill_factor = 80;
```

---

## 11. MySQL vs PostgreSQL Index Fərqləri

| Xüsusiyyət                | MySQL (InnoDB)                    | PostgreSQL                          |
|---------------------------|-----------------------------------|-------------------------------------|
| Default Index tipi        | B+Tree                            | B-Tree                              |
| Clustered Index           | Bəli (PK = clustered)             | Xeyr (heap ayrıdır)                 |
| Partial Index             | Yoxdur                            | Bəli (`WHERE` şərti ilə)            |
| Concurrent Index Build    | Bəli (Online DDL)                 | `CREATE INDEX CONCURRENTLY`         |
| Function-Based Index      | Generated Column vasitəsilə       | Birbaşa (`LOWER(email)`)            |
| Hash Index                | Yalnız MEMORY engine              | `USING HASH`                        |
| GIN/GiST                  | Yoxdur                            | Bəli (Array, JSONB, FTS üçün)       |
| BRIN Index                | Yoxdur                            | Bəli (böyük, sıralı cədvəllər üçün)|
| Index Bloat               | OPTIMIZE TABLE                    | VACUUM, REINDEX CONCURRENTLY        |
| Index Statistics          | `ANALYZE TABLE`                   | `ANALYZE tablename`                 |
| Descending Index          | MySQL 8.0+                        | Bəli                                |
| Invisible Index           | MySQL 8.0+                        | Yoxdur                              |
| Index Skip Scan           | MySQL 8.0+ (limited)              | Bəli (Index-only scan)              |

### MySQL-ə Xas Xüsusiyyətlər

*MySQL-ə Xas Xüsusiyyətlər üçün kod nümunəsi:*
```sql
-- Invisible Index (MySQL 8.0+): Test etmək üçün index-i "gizlətmək"
ALTER TABLE orders ALTER INDEX idx_status INVISIBLE;
-- Optimizer bu index-i görməz, lakin silinmir
-- Test etdikdən sonra:
ALTER TABLE orders ALTER INDEX idx_status VISIBLE;

-- Descending Index (MySQL 8.0+):
CREATE INDEX idx_created_desc ON orders (created_at DESC);
-- ORDER BY created_at DESC sorğularını filesort olmadan icra edir

-- MySQL-də index hint:
SELECT * FROM orders USE INDEX (idx_user_status) WHERE user_id = 42;
SELECT * FROM orders FORCE INDEX (idx_user_status) WHERE user_id = 42;
SELECT * FROM orders IGNORE INDEX (idx_status) WHERE status = 'paid';
```

### PostgreSQL-ə Xas Xüsusiyyətlər

*PostgreSQL-ə Xas Xüsusiyyətlər üçün kod nümunəsi:*
```sql
-- BRIN Index (Block Range INdex): Böyük, append-only cədvəllər üçün
-- Çox kiçik yer tutur, lakin yalnız sıralı/korrelyasiyalı data üçün effektivdir
CREATE INDEX idx_created_brin ON events USING BRIN (created_at)
    WITH (pages_per_range = 128);

-- Index-i müvəqqəti deaktiv etmək (session level):
SET enable_indexscan = OFF;
SET enable_bitmapscan = OFF;
-- Optimizeri məcbur etmək (yalnız test üçün!)

-- pg_stat_user_indexes ilə index istifadəsini izləmək:
SELECT
    indexrelname AS index_name,
    idx_scan AS times_used,
    idx_tup_read AS tuples_read,
    idx_tup_fetch AS tuples_fetched,
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
ORDER BY idx_scan DESC;

-- İstifadə edilməyən index-ləri tapmaq:
SELECT indexrelname, idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0
  AND indexrelname NOT LIKE 'pg_%';
```

---

## 12. Ümumi Səhvlər

### 12.1. Foreign Key-lərdə Çatışmayan Index-lər

*12.1. Foreign Key-lərdə Çatışmayan Index-lər üçün kod nümunəsi:*
```sql
-- ❌ Problemli: FK var, lakin index yoxdur

CREATE TABLE orders (
    id      INT PRIMARY KEY,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    -- user_id-də index YOX!
);

-- Bu nə deməkdir?
-- 1. DELETE FROM users WHERE id = 42; -- users cədvəlindən sil
--    → MySQL bütün orders cədvəlini scan edər (user_id = 42 olanları tapmaq üçün)
--    → Full table scan hər FK delete-də!

-- ✅ Həll: FK sütununa həmişə index qoy
CREATE INDEX idx_orders_user_id ON orders (user_id);

-- MySQL avtomatik yoxlamaq üçün:
SELECT
    kcu.table_name,
    kcu.column_name,
    kcu.constraint_name
FROM information_schema.key_column_usage kcu
LEFT JOIN information_schema.statistics s
    ON s.table_schema = kcu.table_schema
    AND s.table_name = kcu.table_name
    AND s.column_name = kcu.column_name
WHERE kcu.referenced_table_name IS NOT NULL
  AND s.index_name IS NULL
  AND kcu.table_schema = 'mydb';
-- Bu sorğu index-siz FK-ləri tapır
```

### 12.2. Həddindən Çox Index

*12.2. Həddindən Çox Index üçün kod nümunəsi:*
```sql
-- ❌ Hər sütuna ayrıca index:
CREATE TABLE products (
    id          INT PRIMARY KEY,
    category_id INT,
    brand_id    INT,
    price       DECIMAL(10,2),
    name        VARCHAR(200),
    created_at  DATETIME
);
CREATE INDEX idx_cat      ON products (category_id);
CREATE INDEX idx_brand    ON products (brand_id);
CREATE INDEX idx_price    ON products (price);
CREATE INDEX idx_name     ON products (name);
CREATE INDEX idx_created  ON products (created_at);
CREATE INDEX idx_cat_brand ON products (category_id, brand_id);
CREATE INDEX idx_brand_cat ON products (brand_id, category_id);
-- 7 index (PK ilə birlikdə 8)!

-- Hər INSERT/UPDATE → 8 B-Tree yenilənir
-- Disk 8x daha çox yer tutur

-- ✅ Real sorğulara əsasən minimal index:
-- Ən çox işlənən sorğular analiz edilib:
CREATE INDEX idx_cat_price     ON products (category_id, price);
CREATE INDEX idx_brand_created ON products (brand_id, created_at);
-- Yalnız 2 composite index (PK ilə 3)
```

### 12.3. NULL Dəyərləri olan Sütunlar

*12.3. NULL Dəyərləri olan Sütunlar üçün kod nümunəsi:*
```sql
-- NULL dəyərləri B-Tree-də saxlanılır, lakin IS NULL sorğuları
-- bəzən optimizer tərəfindən düzgün istifadə edilmir.

-- MySQL-də IS NULL index-dən istifadə edə bilər:
CREATE INDEX idx_deleted_at ON posts (deleted_at);
SELECT * FROM posts WHERE deleted_at IS NULL;  -- index istifadə edir

-- NULL olan sütunu primary key-ə və ya NOT NULL-a çevirmək
-- performansı artırır.
```

### 12.4. LIKE Wildcard Yanlış İstifadəsi

*12.4. LIKE Wildcard Yanlış İstifadəsi üçün kod nümunəsi:*
```sql
-- ❌ Prefix wildcard: index işləmir
SELECT * FROM users WHERE name LIKE '%Ali%';
SELECT * FROM users WHERE name LIKE '%Ali';

-- ✅ Suffix (yalnız sonda wildcard): index işləyir
SELECT * FROM users WHERE name LIKE 'Ali%';

-- Full-text axtarış lazımdırsa → FULLTEXT index istifadə et
-- Yaxud Elasticsearch, Meilisearch kimi xarici həllər
```

### 12.5. OR Şərtləri

*12.5. OR Şərtləri üçün kod nümunəsi:*
```sql
-- ❌ OR index istifadəsini çətinləşdirə bilər:
SELECT * FROM orders WHERE user_id = 42 OR status = 'pending';

-- ✅ UNION ilə həll:
SELECT * FROM orders WHERE user_id = 42
UNION
SELECT * FROM orders WHERE status = 'pending' AND user_id != 42;

-- MySQL-də "index merge" mümkündür, lakin çox effektiv deyil:
-- type: index_merge, Extra: Using union(idx_user_id,idx_status)
```

---

## 13. EXPLAIN Çıxışı Analizi ilə Real Ssenarilər

### Ssenari 1: N+1 Problemi və Index

*Ssenari 1: N+1 Problemi və Index üçün kod nümunəsi:*
```sql
-- Laravel-dən gələn N+1 sorğu:
-- SELECT * FROM users WHERE id = 1;
-- SELECT * FROM orders WHERE user_id = 1;
-- SELECT * FROM orders WHERE user_id = 2;
-- ...N dəfə

EXPLAIN SELECT * FROM orders WHERE user_id = 1;
-- type: ref, key: idx_user_id → Yaxşı (index var)
-- type: ALL → Pis (index yoxdur, hər user üçün full scan!)

-- Həll:
CREATE INDEX idx_orders_user_id ON orders (user_id);
-- + Eager loading: User::with('orders')->get();
```

### Ssenari 2: Slow Pagination

*Ssenari 2: Slow Pagination üçün kod nümunəsi:*
```sql
-- ❌ Offset-based pagination (böyük offset-də çox yavaş):
SELECT * FROM posts ORDER BY created_at DESC LIMIT 20 OFFSET 100000;
-- Optimizer: 100020 sətir oxuyur, 100000-ni atır!

EXPLAIN SELECT * FROM posts ORDER BY created_at DESC LIMIT 20 OFFSET 100000;
-- rows: 100020  ← çox sətir oxunur
-- Extra: Using filesort  ← əlavə sıralama

-- ✅ Keyset pagination (cursor-based):
SELECT * FROM posts
WHERE created_at < '2024-01-15 12:00:00'  -- son görülmüş dəyər
ORDER BY created_at DESC
LIMIT 20;

CREATE INDEX idx_posts_created ON posts (created_at DESC);
-- rows: 20  ← yalnız lazımi sətirləri oxuyur
```

### Ssenari 3: JOIN Optimizasiyası

*Ssenari 3: JOIN Optimizasiyası üçün kod nümunəsi:*
```sql
-- Slow JOIN sorğusu:
SELECT u.name, COUNT(o.id) as cnt
FROM users u
JOIN orders o ON o.user_id = u.id
WHERE o.status = 'paid'
  AND o.created_at >= '2024-01-01'
GROUP BY u.id;

EXPLAIN -- Nəticəsi:
-- orders cədvəli: type=ALL, rows=500000 → Problem!

-- Həll: composite index orders üzərindəki JOIN + filter sütunları üçün
CREATE INDEX idx_orders_join ON orders (user_id, status, created_at);

EXPLAIN -- Yeni nəticə:
-- orders: type=ref, key=idx_orders_join, rows=12 → Əla!
```

### Ssenari 4: Subquery vs JOIN

*Ssenari 4: Subquery vs JOIN üçün kod nümunəsi:*
```sql
-- Subquery (bəzən yavaş):
SELECT * FROM users
WHERE id IN (
    SELECT DISTINCT user_id FROM orders WHERE status = 'paid'
);

EXPLAIN -- type: ALL subquery üçün (materialized)

-- JOIN daha sürətli ola bilər:
SELECT DISTINCT u.*
FROM users u
INNER JOIN orders o ON o.user_id = u.id
WHERE o.status = 'paid';

-- Ya da EXISTS (çox vaxt ən sürətli):
SELECT * FROM users u
WHERE EXISTS (
    SELECT 1 FROM orders o
    WHERE o.user_id = u.id AND o.status = 'paid'
);
```

### Ssenari 5: Index üzərindən ORDER BY

*Ssenari 5: Index üzərindən ORDER BY üçün kod nümunəsi:*
```sql
-- ❌ filesort:
SELECT * FROM orders WHERE user_id = 42 ORDER BY created_at DESC;
-- idx_user_id var, lakin created_at üçün filesort lazımdır

-- ✅ Composite index ilə filesort aradan qalxır:
CREATE INDEX idx_user_created ON orders (user_id, created_at DESC);

EXPLAIN SELECT * FROM orders WHERE user_id = 42 ORDER BY created_at DESC;
-- Extra: NULL (nə filesort, nə temporary!)
```

---

## 14. Laravel Migration-larında Index Nümunələri

### Əsas Index Yaratma

*Əsas Index Yaratma üçün kod nümunəsi:*
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();                                    // Primary key (AUTO_INCREMENT)
            $table->foreignId('user_id')->constrained();    // FK + index avtomatik
            $table->string('status', 20)->default('pending');
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('AZN');
            $table->timestamps();

            // Composite index
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

### Mövcud Cədvələ Index Əlavə Etmək

*Mövcud Cədvələ Index Əlavə Etmək üçün kod nümunəsi:*
```php
<?php

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Sadə index
            $table->index('email');

            // Unikal index
            $table->unique('phone');

            // Composite index
            $table->index(['last_name', 'first_name'], 'idx_users_fullname');

            // Index-i sil
            // $table->dropIndex(['email']);
            // $table->dropIndex('users_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropUnique(['phone']);
            $table->dropIndex('idx_users_fullname');
        });
    }
};
```

### Full-Text Index (MySQL)

*Full-Text Index (MySQL) üçün kod nümunəsi:*
```php
<?php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();

            // Full-text index
            $table->fullText(['title', 'body'], 'idx_articles_search');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
```

### Raw SQL ilə Xüsusi Index-lər

*Raw SQL ilə Xüsusi Index-lər üçün kod nümunəsi:*
```php
<?php

return new class extends Migration
{
    public function up(): void
    {
        // Laravel Blueprint-in dəstəkləmədiyi index növləri üçün
        // raw SQL istifadə et

        // MySQL: Prefix index
        DB::statement('CREATE INDEX idx_name_prefix ON users (name(50))');

        // PostgreSQL: Partial index
        DB::statement(
            'CREATE INDEX idx_active_users ON users (email) WHERE is_active = TRUE'
        );

        // PostgreSQL: Function-based index
        DB::statement(
            'CREATE INDEX idx_email_lower ON users (LOWER(email))'
        );

        // PostgreSQL: GIN index for JSONB
        DB::statement(
            'CREATE INDEX idx_meta_gin ON products USING GIN (metadata)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_name_prefix ON users');
        DB::statement('DROP INDEX IF EXISTS idx_active_users');
        DB::statement('DROP INDEX IF EXISTS idx_email_lower');
        DB::statement('DROP INDEX IF EXISTS idx_meta_gin');
    }
};
```

### Foreign Key ilə Düzgün İstifadə

*Foreign Key ilə Düzgün İstifadə üçün kod nümunəsi:*
```php
<?php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // foreignId avtomatik index yaradır!
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('restrict');

            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            // Əgər bu kombinasiya tez-tez sorğulanırsa:
            $table->index(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
```

### Laravel-də Index İstifadəsi Yoxlanması

*Laravel-də Index İstifadəsi Yoxlanması üçün kod nümunəsi:*
```php
<?php

// Eloquent query ilə EXPLAIN:
$query = User::where('email', 'test@test.az');
$sql = $query->toSql();
$bindings = $query->getBindings();

$explain = DB::select("EXPLAIN " . $sql, $bindings);
dump($explain);

// Query log aktiv etmək:
DB::enableQueryLog();
User::where('status', 'active')->with('orders')->get();
$queries = DB::getQueryLog();
foreach ($queries as $q) {
    dump($q['query'], $q['time'] . 'ms');
}
```

---

## 15. Müsahibə Sualları

### Əsas Suallar

**S1: B-Tree index-in daxili strukturunu izah et. Axtarış necə baş verir?**

> B-Tree balanslaşdırılmış ağac strukturudur. Kök (root) node-dan başlayaraq, hər internal node-da key müqayisəsi edilir — kiçikdirsə sol, böyükdürsə sağ uşağa keçilir. Bu proses O(log n) mürəkkəbliyini təmin edir. InnoDB B+Tree istifadə edir: bütün data yalnız leaf node-lardadır, leaf node-lar isə doubly linked list ilə birləşdirilidir ki, range scan-lar sürətli olsun.

---

**S2: Clustered və non-clustered index arasındakı fərq nədir? InnoDB-də xüsusiyyətlər?**

> Clustered index-də əsl cədvəl datasının fiziki sıralanması index strukturunu izləyir — InnoDB-də bu həmişə primary key-dir. Non-clustered (secondary) index isə ayrı bir B-Tree saxlayır; leaf node-larında data yox, primary key dəyərləri var. Beləliklə, secondary index-dən istifadə zamanı əvvəl secondary B-Tree-də key tapılır, sonra həmin PK ilə clustered index-dən tam sətir oxunur (double lookup). Bu, covering index-in əhəmiyyətini artırır.

---

**S3: Composite index-də sütun sırası niyə vacibdir?**

> Leftmost prefix qaydası: MySQL yalnız index-in soldan başlayan ardıcıl sütun kombinasiyalarını istifadə edə bilər. Məsələn, `(a, b, c)` index-i: `WHERE a=1`, `WHERE a=1 AND b=2`, `WHERE a=1 AND b=2 AND c=3` sorğularını optimize edir, lakin `WHERE b=2` və ya `WHERE c=3` üçün işləmir. Sütunları düzgün sıralamaq üçün qayda: əvvəlcə equality sütunları, sonra yüksək cardinality-li sütunlar, ən axırda range sütunları.

---

**S4: Covering index nədir, niyə faydalıdır?**

> Covering index, sorğunun SELECT etdiyi bütün sütunları özündə ehtiva edən index-dir. Bu halda MySQL/PostgreSQL yalnız index B-Tree-ni oxuyur, əsas cədvələ (heap/clustered index) müraciət etmir — "index-only scan". Bu, xüsusilə böyük cədvəllərdə əhəmiyyətli performans artımı verir. EXPLAIN çıxışında `Extra: Using index` göstəricisi covering index-dən istifadəni bildirir.

---

**S5: Index selectivity nədir? Aşağı selectivity-li sütunlara index qoymaq lazımdırmı?**

> Selectivity = unikal dəyərlər / ümumi sətir sayı. Yüksək selectivity (email, UUID) index-ləri çox effektiv edir. Aşağı selectivity-li sütunlar (status, gender, boolean) üçün isə optimizer çox vaxt full table scan-ı daha sürətli hesab edir, çünki cədvəlin böyük hissəsini oxuyacaqsa, index overhead-i haqlamır. PostgreSQL-də partial index bu problemi həll edə bilər.

---

**S6: EXPLAIN çıxışında `type: ALL` görürsünüz. Bu nə deməkdir, necə həll edərsiniz?**

> `type: ALL` full table scan deməkdir — optimizer heç bir index istifadə etmir. Həll addımları:
> 1. WHERE şərtindəki sütunlara index var mı yoxla
> 2. Index varsa, niyə istifadə olunmur? (funksiya çağırışı, type mismatch, aşağı selectivity)
> 3. Composite index lazımdırsa, sütun sırasını düzgün qur
> 4. `FORCE INDEX` ilə test et, sonra qərar ver
> 5. Covering index yaratmağı düşün

---

**S7: Write-heavy cədvəldə çox index olması niyə problem yaradır?**

> Hər `INSERT`, `UPDATE`, `DELETE` əməliyyatı həmin cədvəldəki bütün index B-Tree-lərini yeniləməlidir. 10 index olan cədvəldə bir `INSERT` 10 B-Tree node əlavəsinə, potensial page split-lərə, əlavə I/O-ya səbəb olur. Bu, yazma latency-ni artırır, lock contentions yaranır, write throughput azalır. Həll: yalnız real sorğulara cavab verən minimal sayda index saxlamaq.

---

**S8: InnoDB-də foreign key varsa, əlaqəli sütuna niyə index qoymaq lazımdır?**

> InnoDB referential integrity üçün FK əməliyyatları zamanı (DELETE, UPDATE parent cədvəldə) child cədvəldə FK sütununu axtarır. Index olmadan bu axtarış full table scan-a çevrilir. Məsələn, 1 milyon sıralı `orders` cədvəlindən `users` silinəndə, hər `DELETE FROM users` üçün `orders` cədvəlinin tam skanı baş verir. `foreignId()->constrained()` Laravel-də avtomatik index yaradır.

---

**S9: Index bloat nədir, necə mübarizə aparılır?**

> Index bloat, çox sayda DELETE/UPDATE nəticəsində B-Tree page-lərinin boş yer (dead tuples) ilə dolmasıdır. Bu, index ölçüsünü artırır, cache efficiency-ni azaldır. MySQL-də `OPTIMIZE TABLE` ilə tam rebuild, PostgreSQL-də isə `VACUUM` (dead tuples-ı təmizlər) və `REINDEX CONCURRENTLY` (kilidləmədən index-i yenidən qurur) istifadə edilir. Fill factor ayarı da bloat-ı azaltmağa kömək edir.

---

**S10: Hash index nə vaxt B-Tree-dən daha yaxşıdır?**

> Hash index yalnız equality (`=`) müqayisəsi üçün O(1) lookup təmin edir — B-Tree-nin O(log n)-indən daha sürətlidir. Lakin range sorğuları (`>`, `<`, `BETWEEN`), ORDER BY, LIKE prefix üçün hash işləmir. Belə ki, hash index yalnız tam bərabərlik sorğuları üçün sadə "lookup table" kimi istifadə olunan sütunlarda (cache key, token, UUID lookup) mənalıdır.

---

**S11: PostgreSQL-in partial index-i nədir, MySQL-dəki alternativ nədir?**

> PostgreSQL partial index yalnız müəyyən şərti ödəyən sətirləri index-ləyir: `CREATE INDEX idx ON orders (user_id) WHERE status = 'pending'`. Bu, index ölçüsünü kiçildir, selectivity-ni artırır, yazma performansını yaxşılaşdırır. MySQL-də partial index yoxdur. Alternativ: Virtual/Generated Column yaradıb həmin sütunu index-ləmək, ya da application-level partitioning.

---

**S12: `EXPLAIN ANALYZE` ilə `EXPLAIN` arasındakı fərq nədir?**

> `EXPLAIN` yalnız optimizer-in planladığı execution plan-ı göstərir (estimated rows, estimated cost). `EXPLAIN ANALYZE` isə sorğunu faktiki icra edir və real statistikaları göstərir: actual time, actual rows, loops. Bu, optimizer-in yanlış estimasiyalarını (`rows: 1` idi, faktiki `rows: 50000`) aşkar etmək üçün vacibdir. PostgreSQL-də `EXPLAIN (ANALYZE, BUFFERS)` ilə disk/cache read statistikaları da əldə etmək mümkündür.

---

## Sürətli Referans

*Sürətli Referans üçün kod nümunəsi:*
```sql
-- Index yaratma:
CREATE INDEX idx_name ON table (col1, col2);
CREATE UNIQUE INDEX idx_uniq ON table (col);
CREATE FULLTEXT INDEX idx_ft ON table (col);

-- Index silmə:
DROP INDEX idx_name ON table;               -- MySQL
DROP INDEX idx_name;                        -- PostgreSQL

-- Index yoxlama:
SHOW INDEX FROM table;                      -- MySQL
\d table                                    -- PostgreSQL

-- Execution plan:
EXPLAIN SELECT ...;
EXPLAIN ANALYZE SELECT ...;                 -- MySQL 8+, PostgreSQL

-- Unused index-lər (PostgreSQL):
SELECT indexrelname, idx_scan
FROM pg_stat_user_indexes WHERE idx_scan = 0;

-- Index rebuild:
OPTIMIZE TABLE table;                       -- MySQL
REINDEX INDEX CONCURRENTLY idx_name;       -- PostgreSQL
VACUUM ANALYZE table;                       -- PostgreSQL
```

---

## Anti-patternlər

**1. Hər sütuna index əlavə etmək**
"Index çox olsun, sorğular sürətli olsun" düşüncəsi ilə hər sütuna index qoymaq — INSERT/UPDATE/DELETE yavaşlayır, disk istifadəsi artır, query planner yanlış index seçə bilər. Yalnız real sorğu pattern-lərini analiz edərək faktiki olaraq istifadə ediləcək sütunlara index əlavə edin.

**2. Composite index-in sütun sırasını düşünmədən seçmək**
`INDEX(status, created_at)` qurmaq, amma sorğular yalnız `created_at`-a görə filtrləyir — index tam istifadə edilmir. Composite index-in leftmost prefix qaydasını nəzərə alın: ən çox filtrlənən sütun solda olsun; sorğu pattern-ini EXPLAIN ilə yoxlayın.

**3. İstifadəsiz index-ləri saxlamaq**
Migration-larda əlavə edilmiş, artıq lazım olmayan index-lər silinmir — yazma əməliyyatları hər index-i yeniləməlidir, boş yük daşınır. `pg_stat_user_indexes` (PostgreSQL) ya da `sys.schema_unused_indexes` (MySQL) ilə istifadəsiz index-ləri müntəzəm müəyyənləşdirib silin.

**4. LIKE '%keyword%' sorğularını B-tree index ilə həll etməyə çalışmaq**
`WHERE name LIKE '%john%'` — B-tree index bu sorğuya kömək etmir, full table scan qaçılmazdır. Full-text search üçün `FULLTEXT` index (MySQL) ya da `tsvector` (PostgreSQL) istifadə edin; böyük həcmdə Elasticsearch-ə köçürün.

**5. Index-i N+1 sorğunun həlli kimi görmək**
Hər model üçün ayrı sorğu atan lazy loading qalır, yalnız ona index əlavə edilir — sorğu sayı azalmır, yalnız hər biri bir az sürətlənir. Əvvəlcə Eager Loading ilə sorğu sayını azaldın; index sonra əlavə optimizasiya üçün istifadə edin.

**6. Production-da index rebuild-i yüksək trafik zamanı etmək**
`REINDEX INDEX idx_name` (PostgreSQL) table lock götürür — production-da bütün yazma əməliyyatları bloklanır. PostgreSQL-də `REINDEX CONCURRENTLY`, MySQL-də `ALTER TABLE ... ALGORITHM=INPLACE` istifadə edin; rebuild-i aşağı trafik saatlarına planlayın.
