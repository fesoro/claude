# Database Table Partitioning (Senior)

## Mündəricat
1. [Partitioning nədir?](#partitioning-nədir)
2. [Partitioning vs Sharding](#partitioning-vs-sharding)
3. [Partitioning növləri](#partitioning-növləri)
4. [MySQL Partitioning](#mysql-partitioning)
5. [PostgreSQL Partitioning](#postgresql-partitioning)
6. [Partition Pruning](#partition-pruning)
7. [Partitioning Strategiyaları](#partitioning-strategiyaları)
8. [Maintenance əməliyyatları](#maintenance-əməliyyatları)
9. [Laravel ilə Partitioning](#laravel-ilə-partitioning)
10. [Performance Benchmarks](#performance-benchmarks)
11. [Anti-patterns](#anti-patterns)
12. [PHP İmplementasiyası](#php-implementasiyası)
13. [İntervyu Sualları](#intervyu-sualları)

---

## Partitioning nədir?

Table partitioning — böyük bir table-ı fiziki olaraq kiçik hissələrə (partition-lara) bölmə texnikasıdır. Verilənlər bazası mühərriki bir table-ı daxildə bir neçə fiziki fayla ayırır, lakin tətbiq səviyyəsində bu, hələ də vahid table kimi görünür.

```
Partitioning olmadan:
+-----------------------------------------------+
|                  orders table                  |
|  50 milyon sətir, 1 fayl, hər query full scan  |
+-----------------------------------------------+

Partitioning ilə:
+-----------------------------------------------+
|                  orders table                  |
|         (məntiqi olaraq vahid table)           |
+-----------------------------------------------+
         |           |           |           |
    +---------+ +---------+ +---------+ +---------+
    | 2023-Q1 | | 2023-Q2 | | 2023-Q3 | | 2023-Q4 |
    | 3M sətir| | 4M sətir| | 5M sətir| | 3M sətir|
    +---------+ +---------+ +---------+ +---------+
       fayl_1     fayl_2     fayl_3     fayl_4

Query: WHERE created_at = '2023-08-15'
  → Yalnız 2023-Q3 partition oxunur (Partition Pruning)
  → 50M əvəzinə 5M sətir scan edilir
  → ~10x performans artımı
```

Partitioning-in əsas məqsədləri:

1. **Query performansı** — partition pruning sayəsində yalnız lazımi partition-lar oxunur
2. **Maintenance asanlığı** — köhnə data-nı silmək üçün DROP PARTITION (DELETE-dən çox sürətli)
3. **I/O paylanması** — partition-lar fərqli disk-lərə yerləşdirilə bilər
4. **Paralel əməliyyatlar** — hər partition müstəqil index-lənə, backup-lana bilər

---

## Partitioning vs Sharding

Partitioning və Sharding çox qarışdırılan konseptlərdir. Əsas fərq — partitioning **bir server daxilində**, sharding isə **bir neçə server arasında** bölünmədir.

```
PARTITIONING (Bir Server):
+--------------------------------------------------+
|              Server (192.168.1.10)                |
|  +--------------------------------------------+  |
|  |              orders table                   |  |
|  +--------------------------------------------+  |
|  | partition_2024_01 | partition_2024_02 | ... |  |
|  | partition_2024_03 | partition_2024_04 | ... |  |
|  +--------------------------------------------+  |
|                                                   |
|  Hamısı eyni serverdə, eyni database-də.          |
|  Tətbiq bir connection istifadə edir.             |
|  SQL query dəyişmir.                              |
+--------------------------------------------------+

SHARDING (Bir neçə Server):
+------------------+  +------------------+  +------------------+
|  Shard 1         |  |  Shard 2         |  |  Shard 3         |
|  Server A        |  |  Server B        |  |  Server C        |
|  192.168.1.10    |  |  192.168.1.11    |  |  192.168.1.12    |
|                  |  |                  |  |                  |
|  users A-H       |  |  users I-P       |  |  users Q-Z       |
|  orders (A-H)    |  |  orders (I-P)    |  |  orders (Q-Z)    |
+------------------+  +------------------+  +------------------+
        ^                     ^                     ^
        |                     |                     |
  +--------------------------------------------------+
  |        Application (Routing Logic)               |
  |  "Bu user hansı shard-dadır?" — əlavə məntiqi    |
  +--------------------------------------------------+

Müqayisə:
+---------------------+------------------+-------------------+
|                     | Partitioning     | Sharding          |
+---------------------+------------------+-------------------+
| Məkan               | 1 server         | N server          |
| Şəffaflıq           | SQL dəyişmir     | Routing lazımdır  |
| Ölçəkləndirmə       | Vertikal limit   | Horizontal growth |
| Mürəkkəblik         | Aşağı            | Yüksək            |
| Tranzaksiya          | ACID tam dəstək  | Distributed TX    |
| JOIN əməliyyatları   | Hər partition-da | Cross-shard çətin |
| Failover             | Bir server risk  | Node müstəqil     |
+---------------------+------------------+-------------------+
```

**Nə vaxt Partitioning?**
- Table 10M+ sətir
- Zaman əsaslı query-lər çox (logs, orders, events)
- Köhnə data-nı tez-tez silmək lazımdır
- Bir server resursları kifayətdir

**Nə vaxt Sharding?** (Mövzu 55-ə baxın)
- Bir server write yükünü daşıya bilmir
- Data həcmi bir disk-ə sığmır
- Coğrafi paylama lazımdır

---

## Partitioning növləri

### 1. Range Partitioning

Verilənlər ardıcıl dəyər aralığına görə bölünür. Tarix əsaslı data üçün ən populyar.

```
Range Partitioning (tarix üzrə):

orders table:
+------------------------------------------------------------------+
| id | customer_id | amount | created_at                           |
+------------------------------------------------------------------+

Partition açarı: created_at

partition_2024_q1: created_at >= '2024-01-01' AND created_at < '2024-04-01'
partition_2024_q2: created_at >= '2024-04-01' AND created_at < '2024-07-01'
partition_2024_q3: created_at >= '2024-07-01' AND created_at < '2024-10-01'
partition_2024_q4: created_at >= '2024-10-01' AND created_at < '2025-01-01'

Dəyərin düşdüyü aralıq:
  '2024-05-15' → partition_2024_q2
  '2024-11-30' → partition_2024_q4
  '2025-01-05' → partition_2025_q1 (yaradılmayıbsa ERROR!)

Üstünlükləri:
  + Tarix əsaslı query-lər çox effektiv
  + Partition pruning tez işləyir (range comparison)
  + Köhnə partition-u DROP etmək asan

Məhdudiyyətlər:
  - Balansız partition-lar ola bilər (bəzi aylar çox data)
  - Yeni partition-lar əvvəlcədən yaradılmalıdır
```

### 2. List Partitioning

Verilənlər diskret dəyərlər siyahısına görə bölünür. Status, region, category kimi sabit dəyərlər üçün.

```
List Partitioning (status üzrə):

orders table:
+------------------------------------------------------------------+
| id | customer_id | amount | status                               |
+------------------------------------------------------------------+

Partition açarı: status

partition_active:    status IN ('pending', 'processing', 'shipped')
partition_completed: status IN ('delivered', 'completed')
partition_cancelled: status IN ('cancelled', 'refunded', 'disputed')

Dəyərin düşdüyü partition:
  'pending'    → partition_active
  'delivered'  → partition_completed
  'refunded'   → partition_cancelled

Üstünlükləri:
  + Müəyyən status-lar üçün query çox sürətli
  + Məntiqli qruplaşdırma
  + Aktiv data kiçik partition-da — performans artır

Məhdudiyyətlər:
  - Yeni status əlavə olunanda partition dəyişdirilməlidir
  - List-ə daxil olmayan dəyər ERROR verir
```

### 3. Hash Partitioning

Verilənlər hash funksiyası ilə bərabər şəkildə paylanır. Balans vacib olanda istifadə olunur.

```
Hash Partitioning (customer_id üzrə, 4 partition):

Hash funksiyası: customer_id MOD 4

customer_id = 101  → 101 MOD 4 = 1 → partition_1
customer_id = 200  → 200 MOD 4 = 0 → partition_0
customer_id = 333  → 333 MOD 4 = 1 → partition_1
customer_id = 5678 → 5678 MOD 4 = 2 → partition_2

Nəticə bölgüsü:
  partition_0: customer_id MOD 4 = 0  (~25% data)
  partition_1: customer_id MOD 4 = 1  (~25% data)
  partition_2: customer_id MOD 4 = 2  (~25% data)
  partition_3: customer_id MOD 4 = 3  (~25% data)

Üstünlükləri:
  + Bərabər paylama
  + Hotspot problemi yoxdur
  + Partition sayını artırmaq asan (MySQL-də)

Məhdudiyyətlər:
  - Range query effektiv deyil (bütün partition-lar oxunur)
  - Partition pruning yalnız = operatoru ilə işləyir
  - Sıralama yoxdur (range scan mümkün deyil)
```

### 4. Key Partitioning

MySQL-ə xas partition növü. Hash partitioning-ə oxşayır, amma MySQL öz daxili hash funksiyasını istifadə edir (MD5 əsaslı).

```
Key Partitioning:

  Hash Partitioning:   user MOD N
  Key Partitioning:    MySQL_INTERNAL_HASH(user) MOD N

Fərq:
  - Hash → istifadəçi hash funksiyasını bilir
  - Key  → MySQL öz hash-ini istifadə edir
  - Key PRIMARY KEY istifadə edə bilər (default)

İstifadə halı:
  - Primary key integer olmayanda (UUID, composite key)
  - Hash funksiyasını MySQL-ə həvalə etmək istəyəndə
```

### 5. Composite (Sub-partitioning)

Bir partition növünün içində başqa partition növü. İki ölçülü bölünmə lazım olanda.

```
Composite Partitioning:

Range + Hash birləşməsi:
  İlk səviyyə: Range (tarix üzrə) → 4 partition
  İkinci səviyyə: Hash (customer_id üzrə) → hər partition-da 4 sub-partition

                        orders table
                            |
         +----------+----------+----------+----------+
         | 2024-Q1  | 2024-Q2  | 2024-Q3  | 2024-Q4  |   ← Range
         +----------+----------+----------+----------+
         |          |          |          |
     +---+---+  +---+---+  +---+---+  +---+---+
     |h0|h1|h2|h3| |h0|h1|  |h0|h1|  |h0|h1|           ← Hash
     +--+--+--+--+ +--+--+  +--+--+  +--+--+

  Cəmi: 4 x 4 = 16 sub-partition

  Query: WHERE created_at = '2024-08-15' AND customer_id = 500
    → Range pruning: 2024-Q3
    → Hash pruning: 500 MOD 4 = 0
    → Yalnız 1 sub-partition oxunur (16-dan 1-i)
```

---

## MySQL Partitioning

### Range Partitioning

```sql
-- Tarix əsaslı Range Partitioning
CREATE TABLE orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id, created_at),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status)
)
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p2024_01 VALUES LESS THAN (202402),
    PARTITION p2024_02 VALUES LESS THAN (202403),
    PARTITION p2024_03 VALUES LESS THAN (202404),
    PARTITION p2024_04 VALUES LESS THAN (202405),
    PARTITION p2024_05 VALUES LESS THAN (202406),
    PARTITION p2024_06 VALUES LESS THAN (202407),
    PARTITION p2024_07 VALUES LESS THAN (202408),
    PARTITION p2024_08 VALUES LESS THAN (202409),
    PARTITION p2024_09 VALUES LESS THAN (202410),
    PARTITION p2024_10 VALUES LESS THAN (202411),
    PARTITION p2024_11 VALUES LESS THAN (202412),
    PARTITION p2024_12 VALUES LESS THAN (202501),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

```
MySQL Range Partitioning qaydaları:

  1. Partition açarı PRIMARY KEY-in bir hissəsi olmalıdır
     → PRIMARY KEY (id, created_at) — created_at əlavə edildi

  2. LESS THAN — üst həd (exclusive)
     → p2024_01: created_at < '2024-02-01'

  3. MAXVALUE — son partition, bilinməyən gələcək dəyərlər üçün
     → p_future: '2025-01-01' və sonrası

  4. Expression istifadəsi
     → YEAR(created_at) * 100 + MONTH(created_at) = 202408
     → Bu integer expression partition pruning-ə imkan verir

  Diqqət: RANGE COLUMNS daha sadə sintaksis təklif edir:
```

```sql
-- RANGE COLUMNS — expression lazım deyil, birbaşa sütun istifadə edir
CREATE TABLE events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL,
    payload JSON,
    occurred_at DATE NOT NULL,
    PRIMARY KEY (id, occurred_at),
    INDEX idx_event_type (event_type)
)
PARTITION BY RANGE COLUMNS (occurred_at) (
    PARTITION p2024_q1 VALUES LESS THAN ('2024-04-01'),
    PARTITION p2024_q2 VALUES LESS THAN ('2024-07-01'),
    PARTITION p2024_q3 VALUES LESS THAN ('2024-10-01'),
    PARTITION p2024_q4 VALUES LESS THAN ('2025-01-01'),
    PARTITION p2025_q1 VALUES LESS THAN ('2025-04-01'),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### List Partitioning

```sql
-- Status əsaslı List Partitioning
CREATE TABLE tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, status),
    INDEX idx_user (user_id),
    INDEX idx_priority (priority)
)
PARTITION BY LIST COLUMNS (status) (
    PARTITION p_active VALUES IN ('open', 'in_progress', 'waiting'),
    PARTITION p_resolved VALUES IN ('resolved', 'closed'),
    PARTITION p_special VALUES IN ('escalated', 'on_hold', 'blocked')
);
```

```
List Partitioning istifadə qaydaları:

  1. Hər dəyər yalnız bir partition-da olmalıdır
     → 'open' həm p_active, həm p_resolved-da ola bilməz

  2. Siyahıda olmayan dəyər INSERT zamanı ERROR verir
     → INSERT ... status='unknown' → ERROR 1526
     → Həll: DEFAULT partition (MySQL 8.0.13+):
        PARTITION p_other VALUES IN (DEFAULT)

  3. ENUM tip uyğunluğu
     → LIST COLUMNS string dəyərlərlə işləyir
     → LIST (ədi) integer lazımdır
```

### Hash Partitioning

```sql
-- Customer əsaslı Hash Partitioning (8 partition)
CREATE TABLE customer_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    ordered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, customer_id),
    INDEX idx_product (product_id),
    INDEX idx_ordered (ordered_at)
)
PARTITION BY HASH (customer_id)
PARTITIONS 8;
```

```sql
-- Key Partitioning (UUID primary key ilə)
CREATE TABLE audit_logs (
    id CHAR(36) NOT NULL,  -- UUID
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(20) NOT NULL,
    old_values JSON,
    new_values JSON,
    performed_by INT UNSIGNED,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
)
PARTITION BY KEY (id)
PARTITIONS 16;
```

### Composite (Sub-partitioning)

```sql
-- Range + Hash Composite Partitioning
CREATE TABLE transaction_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT UNSIGNED NOT NULL,
    transaction_type VARCHAR(20) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at DATE NOT NULL,
    PRIMARY KEY (id, created_at, account_id),
    INDEX idx_account (account_id),
    INDEX idx_type (transaction_type)
)
PARTITION BY RANGE COLUMNS (created_at)
SUBPARTITION BY HASH (account_id)
SUBPARTITIONS 4 (
    PARTITION p2024_q1 VALUES LESS THAN ('2024-04-01'),
    PARTITION p2024_q2 VALUES LESS THAN ('2024-07-01'),
    PARTITION p2024_q3 VALUES LESS THAN ('2024-10-01'),
    PARTITION p2024_q4 VALUES LESS THAN ('2025-01-01'),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
-- Nəticə: 5 partition x 4 sub-partition = 20 sub-partition
```

### MySQL Partitioning Məhdudiyyətləri

```
MySQL Partitioning Qaydaları və Məhdudiyyətləri:

  1. PRIMARY KEY və UNIQUE KEY qaydası:
     Hər unique index partition açarını ƏHATƏ ETMƏLİDİR.
     → PRIMARY KEY (id) + PARTITION BY RANGE(created_at) → ERROR
     → PRIMARY KEY (id, created_at) + PARTITION BY RANGE(created_at) → OK

  2. Foreign key dəstəyi YOXDUR:
     Partitioned table-da FOREIGN KEY istifadə edilə bilməz.
     → Tətbiq səviyyəsində referential integrity təmin edilməlidir.

  3. FULLTEXT index dəstəyi YOXDUR (MySQL 8.0-a qədər):
     MySQL 8.0+ FULLTEXT index-ə icazə verir, lakin pruning işləmir.

  4. Maksimum partition sayı:
     MySQL 8.0: 8192 partition (sub-partition daxil)

  5. Partition açarı NULL:
     → RANGE: NULL ən kiçik dəyər hesab edilir (ilk partition)
     → LIST: NULL üçün ayrıca VALUES IN (NULL) lazımdır
     → HASH/KEY: NULL = 0 hesab edilir

  6. Temporary table partition edilə bilməz.

  7. InnoDB tələbi:
     MySQL 8.0+ yalnız InnoDB partition-u dəstəkləyir.
```

---

## PostgreSQL Partitioning

PostgreSQL 10+ versiyasından etibarən "Declarative Partitioning" dəstəkləyir ki, bu MySQL-dən daha çevik yanaşma təmin edir.

### Declarative Partitioning

```sql
-- Ana (parent) table yaratmaq
CREATE TABLE orders (
    id BIGSERIAL,
    customer_id INTEGER NOT NULL,
    amount NUMERIC(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Partition-lar yaratmaq
CREATE TABLE orders_2024_q1 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2024-04-01');

CREATE TABLE orders_2024_q2 PARTITION OF orders
    FOR VALUES FROM ('2024-04-01') TO ('2024-07-01');

CREATE TABLE orders_2024_q3 PARTITION OF orders
    FOR VALUES FROM ('2024-07-01') TO ('2024-10-01');

CREATE TABLE orders_2024_q4 PARTITION OF orders
    FOR VALUES FROM ('2024-10-01') TO ('2025-01-01');

-- Default partition — aralığa düşməyən dəyərlər üçün
CREATE TABLE orders_default PARTITION OF orders DEFAULT;

-- Hər partition-da ayrıca index yaratmaq
CREATE INDEX idx_orders_2024_q1_customer ON orders_2024_q1 (customer_id);
CREATE INDEX idx_orders_2024_q2_customer ON orders_2024_q2 (customer_id);
CREATE INDEX idx_orders_2024_q3_customer ON orders_2024_q3 (customer_id);
CREATE INDEX idx_orders_2024_q4_customer ON orders_2024_q4 (customer_id);

-- PostgreSQL 11+: Parent table-da index yaratmaq,
-- avtomatik bütün partition-lara yayılır:
CREATE INDEX idx_orders_customer ON orders (customer_id);
CREATE INDEX idx_orders_status ON orders (status);
```

```
PostgreSQL vs MySQL Partitioning fərqləri:

  +---------------------------+-------------------+---------------------+
  |                           | MySQL             | PostgreSQL          |
  +---------------------------+-------------------+---------------------+
  | Partition = table?        | Xeyr, daxili      | Bəli, ayrı table    |
  | FK dəstəyi               | Xeyr              | Bəli (PG 12+)       |
  | Unique index qaydası      | PK-da olmalı      | PK-da olmalı        |
  | Default partition         | MAXVALUE/DEFAULT  | DEFAULT keyword      |
  | Sub-partitioning          | Bəli              | Bəli (nested)       |
  | Index miras               | Avtomatik         | PG 11+ avtomatik    |
  | Partition detach          | Yoxdur            | DETACH PARTITION     |
  | Concurrent attach         | Yoxdur            | CONCURRENTLY (PG14) |
  +---------------------------+-------------------+---------------------+
```

### PostgreSQL List Partitioning

```sql
-- Region əsaslı List Partitioning
CREATE TABLE user_profiles (
    id BIGSERIAL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    region VARCHAR(10) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY LIST (region);

CREATE TABLE user_profiles_eu PARTITION OF user_profiles
    FOR VALUES IN ('de', 'fr', 'it', 'es', 'nl', 'pl');

CREATE TABLE user_profiles_na PARTITION OF user_profiles
    FOR VALUES IN ('us', 'ca', 'mx');

CREATE TABLE user_profiles_asia PARTITION OF user_profiles
    FOR VALUES IN ('jp', 'kr', 'cn', 'in', 'sg');

CREATE TABLE user_profiles_other PARTITION OF user_profiles DEFAULT;
```

### PostgreSQL Hash Partitioning

```sql
-- Hash Partitioning (PG 11+)
CREATE TABLE sensor_data (
    id BIGSERIAL,
    sensor_id INTEGER NOT NULL,
    reading NUMERIC(10,4) NOT NULL,
    recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY HASH (sensor_id);

CREATE TABLE sensor_data_0 PARTITION OF sensor_data
    FOR VALUES WITH (MODULUS 4, REMAINDER 0);
CREATE TABLE sensor_data_1 PARTITION OF sensor_data
    FOR VALUES WITH (MODULUS 4, REMAINDER 1);
CREATE TABLE sensor_data_2 PARTITION OF sensor_data
    FOR VALUES WITH (MODULUS 4, REMAINDER 2);
CREATE TABLE sensor_data_3 PARTITION OF sensor_data
    FOR VALUES WITH (MODULUS 4, REMAINDER 3);
```

### pg_partman ilə Avtomatik İdarəetmə

`pg_partman` — PostgreSQL üçün populyar partition idarəetmə extension-udur. Yeni partition-ları avtomatik yaradır və köhnələrini idarə edir.

```sql
-- pg_partman extension quraşdırma
CREATE EXTENSION pg_partman;

-- Ana table yaratmaq
CREATE TABLE api_logs (
    id BIGSERIAL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code INTEGER NOT NULL,
    response_time_ms INTEGER,
    request_body JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- pg_partman ilə konfiqurasiya
SELECT partman.create_parent(
    p_parent_table := 'public.api_logs',
    p_control := 'created_at',
    p_type := 'native',
    p_interval := 'daily',
    p_premake := 7,           -- 7 gün irəli partition yarat
    p_start_partition := '2024-01-01'
);

-- Nəticə: avtomatik yaradılan partition-lar
-- api_logs_p2024_01_01
-- api_logs_p2024_01_02
-- ...
-- api_logs_p2024_01_08 (bugün + 7 gün)
```

```sql
-- pg_partman maintenance (cron job ilə çalışdırılır)
-- Yeni partition-lar yaradır, köhnələri idarə edir
SELECT partman.run_maintenance();

-- Köhnə partition-ları silmək üçün retention ayarı
UPDATE partman.part_config
SET retention = '90 days',
    retention_keep_table = false  -- partition-u DROP edir
WHERE parent_table = 'public.api_logs';

-- pg_partman cron job (hər saat)
-- pg_cron istifadə edərək:
SELECT cron.schedule(
    'partition-maintenance',
    '0 * * * *',
    $$SELECT partman.run_maintenance()$$
);
```

```
pg_partman iş axını:

  +-------------------+
  |   cron job        |  Hər saat işləyir
  |   (pg_cron)       |
  +--------+----------+
           |
           v
  +-------------------+
  | run_maintenance() |
  +--------+----------+
           |
     +-----+------+
     |            |
     v            v
  +--------+  +----------+
  | Yeni   |  | Köhnə    |
  | part.  |  | part.    |
  | yarat  |  | sil/arxiv|
  +--------+  +----------+
     |            |
     v            v
  p2024_01_15  p2023_10_15
  (premake)    (retention)
```

---

## Partition Pruning

Partition pruning — verilənlər bazası mühərrikinin query şərtlərinə əsasən lazımsız partition-ları atlama mexanizmidir. Bu, partitioning-in performans üstünlüyünün əsas mənbəyidir.

### MySQL-də Partition Pruning

```sql
-- Partition pruning-in aktivləşdiyini yoxlamaq
SET GLOBAL optimizer_switch='partition_pruning=on';  -- default ON

-- EXPLAIN ilə pruning-i görmək
EXPLAIN SELECT * FROM orders
WHERE created_at BETWEEN '2024-07-01' AND '2024-09-30';
```

```
EXPLAIN nəticəsi (pruning İŞLƏYİR):

+----+-------------+--------+------------+------+------+-------+
| id | select_type | table  | partitions | type | rows | Extra |
+----+-------------+--------+------------+------+------+-------+
|  1 | SIMPLE      | orders | p2024_q3   | ALL  | 5M   | ...   |
+----+-------------+--------+------------+------+------+-------+

  partitions: p2024_q3    ← Yalnız 1 partition oxunur!
  Əgər pruning olmasaydı: p2024_q1,p2024_q2,p2024_q3,p2024_q4,p_future

EXPLAIN nəticəsi (pruning İŞLƏMİR):

+----+-------------+--------+------------------------------------------+
| id | select_type | table  | partitions                               |
+----+-------------+--------+------------------------------------------+
|  1 | SIMPLE      | orders | p2024_q1,p2024_q2,p2024_q3,p2024_q4,... |
+----+-------------+--------+------------------------------------------+

  Bütün partition-lar oxunur → pruning işləmədi!
```

```sql
-- Pruning İŞLƏYƏN query-lər:
-- 1. Partition açarı WHERE-dədir
SELECT * FROM orders WHERE created_at = '2024-08-15';

-- 2. Range şərti
SELECT * FROM orders WHERE created_at >= '2024-07-01'
                       AND created_at < '2024-10-01';

-- 3. IN operatoru
SELECT * FROM orders WHERE created_at IN ('2024-03-15', '2024-08-20');


-- Pruning İŞLƏMƏYƏN query-lər:
-- 1. Partition açarında funksiya
SELECT * FROM orders WHERE YEAR(created_at) = 2024;
-- ↑ Funksiya partition açarını gizlədir!
-- Həll: WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01'

-- 2. Partition açarı olmadan query
SELECT * FROM orders WHERE customer_id = 42;
-- ↑ customer_id partition açarı deyil, bütün partition-lar oxunur

-- 3. OR ilə fərqli sütun
SELECT * FROM orders WHERE created_at = '2024-08-15' OR status = 'pending';
-- ↑ OR status üçün bütün partition-ları oxumağa məcbur edir
```

### PostgreSQL-də Partition Pruning

```sql
-- PostgreSQL-də pruning (default ON, PG 11+)
SET enable_partition_pruning = on;

-- EXPLAIN ANALYZE ilə real pruning
EXPLAIN ANALYZE
SELECT * FROM orders WHERE created_at = '2024-08-15';
```

```
PostgreSQL EXPLAIN ANALYZE nəticəsi:

Append  (cost=0.00..25.00 rows=6 width=52)
        (actual time=0.015..0.022 rows=3 loops=1)
  Subplans Removed: 3                          ← 3 partition keçildi!
  ->  Seq Scan on orders_2024_q3 orders_1
        (cost=0.00..25.00 rows=6 width=52)
        (actual time=0.015..0.022 rows=3 loops=1)
        Filter: (created_at = '2024-08-15')
Planning Time: 0.5 ms
Execution Time: 0.1 ms

  "Subplans Removed: 3" — 3 partition pruning edildi,
  yalnız orders_2024_q3 oxundu.

PostgreSQL 12+ JIT Pruning:
  PG 12 icra zamanı (runtime) pruning dəstəkləyir:

  EXPLAIN ANALYZE
  SELECT * FROM orders WHERE created_at = $1;  -- prepared statement

  Nəticədə:
    Subplans Removed: 3   ← runtime-da pruning (parametrli query)
```

```
Partition Pruning effektivliyi:

  Partition sayı:   12 (aylıq)
  Table ölçüsü:     50M sətir
  Hər partition:    ~4.2M sətir

  Query: WHERE created_at = '2024-08-15'

  Pruning olmadan:   50M sətir scan → 2.5 saniyə
  Pruning ilə:       4.2M sətir scan → 0.2 saniyə
                     ~12x sürətləndirmə

  Query: WHERE created_at BETWEEN '2024-07-01' AND '2024-09-30'

  Pruning olmadan:   50M sətir scan → 2.5 saniyə
  Pruning ilə:       12.6M sətir scan (3 partition) → 0.6 saniyə
                     ~4x sürətləndirmə

  Əlavə index + pruning:
    Partition index scan: 0.001 saniyə
    Full table scan:      2.5 saniyə
    → 2500x sürətləndirmə!
```

---

## Partitioning Strategiyaları

### 1. Zaman əsaslı (Time-based) Partitioning

Ən populyar strategiya. Logs, events, orders, transactions kimi zamanla artan data üçün.

```
Zaman əsaslı partition strategiyası seçimi:

  Data yaşı / Həcm           Tövsiyə olunan interval
  ──────────────────────────  ──────────────────────────
  < 1 milyon sətir/gün       Aylıq partition
  1-10 milyon sətir/gün      Həftəlik partition
  10-100 milyon sətir/gün    Gündəlik partition
  > 100 milyon sətir/gün     Saatlıq partition

  Qayda: Hər partition 1-50 milyon sətir arası optimal.
  Çox kiçik partition → overhead artır.
  Çox böyük partition → pruning az fayda verir.

  Retention strategiyası:
  +--------+--------+--------+--------+--------+--------+
  | Jan    | Feb    | Mar    | Apr    | May    | Jun    |
  | Arxiv  | Arxiv  | Arxiv  | Aktiv  | Aktiv  | Aktiv  |
  | DETACH | DETACH | DETACH | KEEP   | KEEP   | KEEP   |
  +--------+--------+--------+--------+--------+--------+
      ↓
  +-------------------+
  | Cold Storage      |
  | (S3 / Archive DB) |
  +-------------------+
```

```sql
-- PostgreSQL: Zaman əsaslı strategiya nümunəsi
CREATE TABLE application_logs (
    id BIGSERIAL,
    level VARCHAR(10) NOT NULL,      -- error, warning, info, debug
    channel VARCHAR(50) NOT NULL,    -- app, queue, scheduler
    message TEXT NOT NULL,
    context JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Gündəlik partition-lar (pg_partman ilə)
SELECT partman.create_parent(
    p_parent_table := 'public.application_logs',
    p_control := 'created_at',
    p_type := 'native',
    p_interval := 'daily',
    p_premake := 7,
    p_start_partition := CURRENT_DATE::TEXT
);

-- 30 gündən köhnə partition-ları sil
UPDATE partman.part_config
SET retention = '30 days',
    retention_keep_table = false
WHERE parent_table = 'public.application_logs';
```

### 2. Tenant əsaslı Partitioning

Multi-tenant SaaS tətbiqlərində hər tenant və ya tenant qrupu üçün ayrı partition.

```sql
-- MySQL: Tenant əsaslı Hash Partitioning
CREATE TABLE tenant_data (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    data_key VARCHAR(100) NOT NULL,
    data_value JSON,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, tenant_id),
    INDEX idx_key (data_key)
)
PARTITION BY HASH (tenant_id)
PARTITIONS 32;

-- PostgreSQL: Böyük tenant-lar üçün List Partitioning
CREATE TABLE tenant_orders (
    id BIGSERIAL,
    tenant_id INTEGER NOT NULL,
    order_data JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY LIST (tenant_id);

-- Böyük tenant-lar öz partition-unu alır
CREATE TABLE tenant_orders_t1 PARTITION OF tenant_orders
    FOR VALUES IN (1);     -- Ən böyük tenant

CREATE TABLE tenant_orders_t2 PARTITION OF tenant_orders
    FOR VALUES IN (2);     -- İkinci böyük tenant

-- Kiçik tenant-lar qruplaşdırılır
CREATE TABLE tenant_orders_small PARTITION OF tenant_orders
    FOR VALUES IN (3, 4, 5, 6, 7, 8, 9, 10);

CREATE TABLE tenant_orders_default PARTITION OF tenant_orders DEFAULT;
```

```
Tenant əsaslı partitioning diqqət məqamları:

  Üstünlüklər:
    + Bir tenant-ın data-sı izolyasiya olunur
    + Tenant silmə → DROP PARTITION (anında)
    + Böyük tenant-lar üçün ayrıca performans tuning
    + Data locality — bir tenant-ın data-sı eyni disk sahəsində

  Risk-lər:
    - Tenant sayı artdıqca partition sayı artır
    - Cross-tenant query-lər bütün partition-ları oxuyur
    - Bəzi tenant-lar çox data, bəziləri az → disbalans

  Tövsiyə:
    Hash partitioning (sabit N partition) + tenant_id
    və ya
    List partitioning (böyük tenant-lar ayrı, kiçiklər qrup)
```

### 3. Status əsaslı Partitioning

Aktiv və arxiv data-nı ayırmaq üçün. Hot/cold data pattern.

```sql
-- MySQL: Status əsaslı List Partitioning
CREATE TABLE support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT,
    status VARCHAR(20) NOT NULL,
    priority TINYINT NOT NULL DEFAULT 3,
    created_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id, status),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
)
PARTITION BY LIST COLUMNS (status) (
    PARTITION p_active VALUES IN ('open', 'in_progress', 'escalated'),
    PARTITION p_waiting VALUES IN ('waiting_customer', 'waiting_vendor'),
    PARTITION p_closed VALUES IN ('resolved', 'closed', 'cancelled')
);
```

```
Status əsaslı partitioning iş prinsipi:

  Tipik senaryo: Aktiv ticket-lər 5%, qalanı arxiv.

  +-------------------+    +-------------------+    +-------------------+
  | p_active          |    | p_waiting         |    | p_closed          |
  | 50K sətir (5%)    |    | 20K sətir (2%)    |    | 930K sətir (93%)  |
  |                   |    |                   |    |                   |
  | HOT data          |    | WARM data         |    | COLD data         |
  | SSD-də saxla      |    | SSD-də saxla      |    | HDD-də saxla      |
  | Index: RAM-da     |    | Index: RAM-da     |    | Index: disk-də    |
  +-------------------+    +-------------------+    +-------------------+

  Query: WHERE status = 'open'
    → Yalnız p_active oxunur (50K sətir)
    → 930K sətirlik arxiv toxunulmur

  Tablespace fərqləndirmə (PostgreSQL):
    CREATE TABLESPACE fast_ssd LOCATION '/mnt/ssd/pgdata';
    CREATE TABLESPACE archive_hdd LOCATION '/mnt/hdd/pgdata';

    ALTER TABLE tickets_active SET TABLESPACE fast_ssd;
    ALTER TABLE tickets_closed SET TABLESPACE archive_hdd;
```

---

## Maintenance əməliyyatları

### Partition əlavə etmək

```sql
-- MySQL: Yeni partition əlavə etmək
-- MAXVALUE partition-u split etmək lazımdır
ALTER TABLE orders REORGANIZE PARTITION p_future INTO (
    PARTITION p2025_01 VALUES LESS THAN (202502),
    PARTITION p2025_02 VALUES LESS THAN (202503),
    PARTITION p2025_03 VALUES LESS THAN (202504),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- PostgreSQL: Yeni partition əlavə etmək (daha sadə)
CREATE TABLE orders_2025_q1 PARTITION OF orders
    FOR VALUES FROM ('2025-01-01') TO ('2025-04-01');

-- PostgreSQL: Mövcud table-ı partition kimi əlavə etmək
CREATE TABLE orders_2025_q2 (LIKE orders INCLUDING ALL);
-- Data yükləmək...
ALTER TABLE orders ATTACH PARTITION orders_2025_q2
    FOR VALUES FROM ('2025-04-01') TO ('2025-07-01');
```

### Partition silmək

```sql
-- MySQL: Partition silmək (data ilə birlikdə)
-- DAHA SÜRƏTLİ: anında, DELETE-dən 100-1000x sürətli
ALTER TABLE orders DROP PARTITION p2023_01;

-- Çoxlu partition silmək
ALTER TABLE orders DROP PARTITION p2023_01, p2023_02, p2023_03;

-- PostgreSQL: Partition silmək
DROP TABLE orders_2023_q1;

-- PostgreSQL: Partition ayırmaq (data qalır, ayrı table olur)
ALTER TABLE orders DETACH PARTITION orders_2023_q1;
-- orders_2023_q1 hələ də mövcuddur, amma orders-in hissəsi deyil
-- Arxivləmək və ya başqa database-ə köçürmək olar

-- PostgreSQL 14+: CONCURRENTLY (kilidləmədən)
ALTER TABLE orders DETACH PARTITION orders_2023_q1 CONCURRENTLY;
```

```
DROP PARTITION vs DELETE performansı:

  Table: orders (50M sətir, 12 aylıq partition)
  Silmək: 2023-Q1 data-sı (~4M sətir)

  DELETE FROM orders WHERE created_at < '2024-04-01';
    Vaxt: ~45 saniyə
    WAL log: ~2GB
    Vacuum lazımdır
    Table kilidlənir (row lock)
    Index yenilənir

  ALTER TABLE orders DROP PARTITION p2023_q1;   (MySQL)
  DROP TABLE orders_2023_q1;                    (PostgreSQL)
    Vaxt: < 0.1 saniyə
    WAL log: minimal
    Vacuum lazım deyil
    Metadata dəyişikliyi (anında)
    Index avtomatik silinir

  Nəticə: Köhnə data silmək üçün HƏMIŞƏ partition istifadə edin.
```

### Partition birləşdirmək (Merge)

```sql
-- MySQL: İki partition-u birləşdirmək
ALTER TABLE orders REORGANIZE PARTITION p2024_01, p2024_02, p2024_03 INTO (
    PARTITION p2024_q1 VALUES LESS THAN (202404)
);
-- Diqqət: data fiziki olaraq köçürülür, böyük partition-larda yavaş ola bilər

-- PostgreSQL: Birləşdirmə üçün manual proses
-- 1. Yeni partition yaratmaq
CREATE TABLE orders_2024_h1 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2024-07-01');
-- ERROR: overlapping range! Əvvəl köhnələri ayırmaq lazımdır.

-- Düzgün yol:
BEGIN;
ALTER TABLE orders DETACH PARTITION orders_2024_q1;
ALTER TABLE orders DETACH PARTITION orders_2024_q2;
CREATE TABLE orders_2024_h1 (LIKE orders INCLUDING ALL);
INSERT INTO orders_2024_h1 SELECT * FROM orders_2024_q1;
INSERT INTO orders_2024_h1 SELECT * FROM orders_2024_q2;
ALTER TABLE orders ATTACH PARTITION orders_2024_h1
    FOR VALUES FROM ('2024-01-01') TO ('2024-07-01');
DROP TABLE orders_2024_q1;
DROP TABLE orders_2024_q2;
COMMIT;
```

### Reindexing

```sql
-- MySQL: Partition-da index yenidən qurmaq
ALTER TABLE orders REBUILD PARTITION p2024_q3;

-- Bütün partition-ları rebuild
ALTER TABLE orders REBUILD PARTITION ALL;

-- Partition statistikalarını yeniləmək
ANALYZE TABLE orders;

-- PostgreSQL: Partition index-ini yenidən qurmaq
REINDEX TABLE orders_2024_q3;

-- Bütün partition-ların index-ini yeniləmək
REINDEX TABLE orders;

-- PostgreSQL: CONCURRENTLY (kilidləmədən)
REINDEX TABLE CONCURRENTLY orders_2024_q3;

-- Statistikaları yeniləmək
ANALYZE orders;
ANALYZE orders_2024_q3;  -- yalnız bir partition
```

---

## Laravel ilə Partitioning

Laravel-in standart migration sistemi partitioning-i birbaşa dəstəkləmir. Lakin raw SQL ilə tam nəzarət mümkündür.

### Migration-lar

```php
<?php
// database/migrations/2024_01_01_create_orders_partitioned_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: Range Partitioned table
        DB::statement("
            CREATE TABLE orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                customer_id INT UNSIGNED NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id, created_at),
                INDEX idx_customer (customer_id),
                INDEX idx_status (status)
            )
            PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
                PARTITION p2024_01 VALUES LESS THAN (202402),
                PARTITION p2024_02 VALUES LESS THAN (202403),
                PARTITION p2024_03 VALUES LESS THAN (202404),
                PARTITION p2024_04 VALUES LESS THAN (202405),
                PARTITION p2024_05 VALUES LESS THAN (202406),
                PARTITION p2024_06 VALUES LESS THAN (202407),
                PARTITION p2024_07 VALUES LESS THAN (202408),
                PARTITION p2024_08 VALUES LESS THAN (202409),
                PARTITION p2024_09 VALUES LESS THAN (202410),
                PARTITION p2024_10 VALUES LESS THAN (202411),
                PARTITION p2024_11 VALUES LESS THAN (202412),
                PARTITION p2024_12 VALUES LESS THAN (202501),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS orders');
    }
};
```

```php
<?php
// database/migrations/2024_01_02_create_orders_partitioned_postgresql.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: Declarative Partitioning
        DB::statement("
            CREATE TABLE orders (
                id BIGSERIAL,
                customer_id INTEGER NOT NULL,
                amount NUMERIC(10,2) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                notes TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ
            ) PARTITION BY RANGE (created_at)
        ");

        // Partition-lar yaratmaq
        $year = 2024;
        for ($month = 1; $month <= 12; $month++) {
            $partName = sprintf('orders_%d_%02d', $year, $month);
            $start = sprintf('%d-%02d-01', $year, $month);

            $nextMonth = $month + 1;
            $nextYear = $year;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            $end = sprintf('%d-%02d-01', $nextYear, $nextMonth);

            DB::statement("
                CREATE TABLE {$partName} PARTITION OF orders
                FOR VALUES FROM ('{$start}') TO ('{$end}')
            ");
        }

        // Default partition
        DB::statement("
            CREATE TABLE orders_default PARTITION OF orders DEFAULT
        ");

        // Index-lər (bütün partition-lara yayılır)
        DB::statement('CREATE INDEX idx_orders_customer ON orders (customer_id)');
        DB::statement('CREATE INDEX idx_orders_status ON orders (status)');
        DB::statement('CREATE INDEX idx_orders_created ON orders (created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS orders CASCADE');
    }
};
```

### Yeni partition-lar əlavə etmək üçün migration

```php
<?php
// database/migrations/2025_01_01_add_2025_partitions_to_orders.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->addMySQLPartitions();
        } elseif ($driver === 'pgsql') {
            $this->addPostgreSQLPartitions();
        }
    }

    private function addMySQLPartitions(): void
    {
        // MAXVALUE partition-u split etmək
        $partitions = [];
        for ($month = 1; $month <= 12; $month++) {
            $partName = sprintf('p2025_%02d', $month);
            $nextMonth = $month + 1;
            $nextYear = 2025;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear = 2026;
            }
            $bound = $nextYear * 100 + $nextMonth;
            $partitions[] = "PARTITION {$partName} VALUES LESS THAN ({$bound})";
        }

        $partitions[] = "PARTITION p_future VALUES LESS THAN MAXVALUE";
        $partitionsSql = implode(",\n", $partitions);

        DB::statement("
            ALTER TABLE orders REORGANIZE PARTITION p_future INTO (
                {$partitionsSql}
            )
        ");
    }

    private function addPostgreSQLPartitions(): void
    {
        $year = 2025;
        for ($month = 1; $month <= 12; $month++) {
            $partName = sprintf('orders_%d_%02d', $year, $month);
            $start = sprintf('%d-%02d-01', $year, $month);

            $nextMonth = $month + 1;
            $nextYear = $year;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            $end = sprintf('%d-%02d-01', $nextYear, $nextMonth);

            DB::statement("
                CREATE TABLE IF NOT EXISTS {$partName} PARTITION OF orders
                FOR VALUES FROM ('{$start}') TO ('{$end}')
            ");
        }
    }

    public function down(): void
    {
        // Partition-ları geri qaytarmaq risklidir, diqqətli olun
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            for ($month = 1; $month <= 12; $month++) {
                $partName = sprintf('orders_2025_%02d', $month);
                DB::statement("DROP TABLE IF EXISTS {$partName}");
            }
        }
    }
};
```

### Eloquent ilə işləmək

```php
<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'customer_id',
        'amount',
        'status',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * VACIB: Partition pruning üçün query-lərdə həmişə created_at şərtini əlavə edin.
     * Bu scope aktiv dövrü filtrləyir.
     */
    public function scopeInPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Bu ay-ın sifarişləri (yalnız 1 partition oxunur).
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth())
                     ->where('created_at', '<', now()->addMonth()->startOfMonth());
    }

    /**
     * Son N ay-ın sifarişləri.
     */
    public function scopeLastMonths(Builder $query, int $months): Builder
    {
        return $query->where('created_at', '>=', now()->subMonths($months)->startOfMonth());
    }

    /**
     * DIQQƏT: Bu query bütün partition-ları oxuyur (pruning yoxdur).
     * Yalnız lazım olanda istifadə edin.
     */
    public function scopeGlobal(Builder $query): Builder
    {
        // Partition açarı olmadan — bütün data
        return $query;
    }
}
```

```php
<?php
// Eloquent istifadə nümunələri

// Pruning İŞLƏYİR — yalnız müvafiq partition oxunur
$orders = Order::thisMonth()
    ->where('status', 'pending')
    ->get();

// Pruning İŞLƏYİR — 2 partition
$orders = Order::inPeriod('2024-07-01', '2024-08-31')
    ->where('customer_id', 42)
    ->sum('amount');

// Pruning İŞLƏMİR — partition açarı yoxdur!
$orders = Order::where('customer_id', 42)->get();
// ↑ Bütün partition-lar oxunur. Əlavə where('created_at', ...) lazımdır.

// Performans fərqi:
// 50M sətirli table, 12 partition:
//   Order::thisMonth()->count()       → 0.05s (1 partition)
//   Order::where('status', 'x')->count() → 2.3s (12 partition, full scan)
```

### Eloquent və Partitioned Table diqqət məqamları

```php
<?php
// PROBLEM 1: Auto-increment ID
// Partitioned table-da PRIMARY KEY (id, partition_key) olmalıdır.
// Laravel default olaraq `id` istifadə edir.
// find() işləyir, amma full scan ola bilər.

// Həll: Scope ilə partition açarını əlavə edin
$order = Order::where('id', 123)
    ->where('created_at', '>=', '2024-08-01')
    ->where('created_at', '<', '2024-09-01')
    ->first();

// PROBLEM 2: SoftDeletes
// Partitioned table ilə SoftDeletes istifadə etmək olar,
// amma silmə əvəzinə DROP PARTITION daha effektivdir.

// PROBLEM 3: Timestamps
// Eloquent created_at/updated_at avtomatik set edir.
// Partition açarı created_at isə — problem yoxdur.
// Amma updated_at dəyişəndə partition dəyişmir (MySQL yalnız insert zamanı).

// PROBLEM 4: Eager Loading (N+1)
// Partitioned table ilə eager loading işləyir,
// amma əlaqəli query-lərdə partition açarı olmaya bilər.

// Nümunə:
// Customer::with('orders')->find(42);
// ↑ orders query: WHERE customer_id = 42
//   → Partition pruning İŞLƏMİR (created_at yoxdur)

// Həll:
Customer::with(['orders' => function ($query) {
    $query->where('created_at', '>=', now()->subMonths(3));
}])->find(42);
// ↑ Partition pruning İŞLƏYİR (son 3 ay-ın partition-ları)
```

---

## Performance Benchmarks

Aşağıdakı benchmark-lar real dünya ssenarilərinə əsaslanır. Nəticələr hardware-ə görə dəyişə bilər, amma nisbətlər oxşar olacaq.

```
Test mühiti:
  MySQL 8.0.35
  CPU: 8 core
  RAM: 32GB
  Disk: NVMe SSD
  Table: orders (50 milyon sətir, 12 aylıq partition)

+-------------------------------------------+----------------+----------------+----------+
| Query                                     | Partitioned    | Non-Partitioned| Fərq     |
+-------------------------------------------+----------------+----------------+----------+
| SELECT COUNT(*) FROM orders               |                |                |          |
| WHERE created_at = '2024-08-15'           | 0.04s          | 1.8s           | 45x      |
+-------------------------------------------+----------------+----------------+----------+
| SELECT * FROM orders                      |                |                |          |
| WHERE created_at BETWEEN                  |                |                |          |
|   '2024-07-01' AND '2024-09-30'           | 0.9s           | 3.2s           | 3.5x     |
+-------------------------------------------+----------------+----------------+----------+
| SELECT * FROM orders                      |                |                |          |
| WHERE customer_id = 42                    | 2.1s           | 1.9s           | 0.9x(!)  |
| (partition açarı yoxdur)                  |                |                |          |
+-------------------------------------------+----------------+----------------+----------+
| DELETE FROM orders                        |                |                |          |
| WHERE created_at < '2024-04-01'           | 45s + VACUUM   | -              |          |
| vs DROP PARTITION                         | 0.05s          | -              | 900x     |
+-------------------------------------------+----------------+----------------+----------+
| INSERT INTO orders (tek sətir)            | 0.3ms          | 0.3ms          | ~1x      |
+-------------------------------------------+----------------+----------------+----------+
| Bulk INSERT (100K sətir)                  | 4.5s           | 4.2s           | ~1x      |
+-------------------------------------------+----------------+----------------+----------+
| SELECT SUM(amount) FROM orders            |                |                |          |
| WHERE created_at >= '2024-01-01'          |                |                |          |
| AND created_at < '2024-04-01'             | 1.2s           | 4.8s           | 4x       |
| GROUP BY customer_id                      |                |                |          |
+-------------------------------------------+----------------+----------------+----------+
| FULL TABLE SCAN (bütün data)              | 12.5s          | 11.8s          | 0.94x(!) |
+-------------------------------------------+----------------+----------------+----------+

Nəticə:
  + Partition açarı olan query-lər 3-45x sürətli
  + DROP PARTITION 900x sürətli (DELETE-dən)
  + INSERT performansı eynidir
  - Partition açarı olmayan query-lər cüzi YAVAŞ ola bilər
  - Full table scan cüzi YAVAŞ ola bilər (partition overhead)
```

```
PostgreSQL Benchmark (oxşar mühit, 50M sətir):

+-------------------------------------------+----------------+----------------+----------+
| Əməliyyat                                 | Partitioned    | Non-Partitioned| Fərq     |
+-------------------------------------------+----------------+----------------+----------+
| Point query (1 partition)                 | 0.03s          | 1.5s           | 50x      |
| Range query (3 partition)                 | 0.7s           | 2.8s           | 4x       |
| VACUUM (köhnə data)                      | 2s (1 part.)   | 45s (table)    | 22x      |
| pg_dump backup (1 partition)              | 3s             | 120s (table)   | 40x      |
| Index rebuild (1 partition)               | 5s             | 180s (table)   | 36x      |
| Partition açarısız query                  | 2.3s           | 2.0s           | 0.87x    |
+-------------------------------------------+----------------+----------------+----------+

Qeyd: Partition açarısız query-lər yavaşlayır çünki:
  1. Query planner hər partition-u ayrıca planlamalıdır
  2. Hər partition-da ayrıca index scan olur
  3. Nəticələr merge edilməlidir

  Bu overhead partition sayı artdıqca artır:
    12 partition:  +5-15% overhead
    100 partition: +20-40% overhead
    1000 partition: +100-300% overhead (ciddi problem!)
```

---

## Anti-patterns

### 1. Over-partitioning (Həddindən artıq partition)

```
YANLIŞ: Gündəlik partition, 5 il data = 1825 partition

  CREATE TABLE logs ...
  PARTITION BY RANGE (TO_DAYS(created_at)) (
      PARTITION p20200101 VALUES LESS THAN (TO_DAYS('2020-01-02')),
      PARTITION p20200102 VALUES LESS THAN (TO_DAYS('2020-01-03')),
      ... (1825 partition!)
  );

  Problem-lər:
  - Query planner hər query üçün 1825 partition-u qiymətləndirməlidir
  - SHOW CREATE TABLE çıxışı nəhəng olur
  - DDL əməliyyatları yavaşlayır
  - File descriptor limiti aşıla bilər (hər partition = 1+ fayl)
  - MySQL 8.0 limiti: 8192 partition

  DOĞRU: Aylıq partition, 5 il = 60 partition
  və ya: Həftəlik partition, retention 1 il = 52 partition

  Qayda: Partition sayı 50-200 arası optimal.
  500+ ciddi sual doğurmalıdır.
```

### 2. Yanlış partition açarı

```
YANLIŞ: Partition açarı query pattern-ə uyğun deyil

  -- Table customer_id üzrə partition edilib
  PARTITION BY HASH (customer_id) PARTITIONS 16;

  -- Amma bütün query-lər tarix üzrə filter edir:
  SELECT * FROM orders WHERE created_at > '2024-08-01';
  → 16 partition hamısı oxunur! Pruning İŞLƏMİR.

  DOĞRU: Əvvəl query pattern-ləri analiz edin.
  -- Ən çox istifadə olunan WHERE şərti nədir?
  -- created_at → Range partition by date
  -- customer_id → Hash partition by customer_id
  -- status → List partition by status

  Query pattern analizi:
  +------------------------------+-----------------------------------+
  | Ən çox istifadə olunan query | Tövsiyə olunan partition açarı    |
  +------------------------------+-----------------------------------+
  | WHERE created_at = ?         | RANGE (created_at)                |
  | WHERE tenant_id = ?          | HASH (tenant_id)                  |
  | WHERE status = ?             | LIST (status)                     |
  | WHERE region = ? AND date=?  | Composite: LIST(region)+RANGE(dt) |
  +------------------------------+-----------------------------------+
```

### 3. Partition açarında funksiya istifadəsi

```sql
-- YANLIŞ: Query-də funksiya partition pruning-i pozur
SELECT * FROM orders WHERE YEAR(created_at) = 2024;
SELECT * FROM orders WHERE DATE(created_at) = '2024-08-15';
SELECT * FROM orders WHERE created_at + INTERVAL 1 DAY > NOW();

-- DOĞRU: Sütunu olduğu kimi istifadə edin
SELECT * FROM orders
WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01';

SELECT * FROM orders
WHERE created_at >= '2024-08-15' AND created_at < '2024-08-16';

SELECT * FROM orders
WHERE created_at > NOW() - INTERVAL 1 DAY;
```

### 4. Partitioned table-da Foreign Key

```
YANLIŞ (MySQL):
  CREATE TABLE order_items (
      id BIGINT PRIMARY KEY,
      order_id BIGINT,
      FOREIGN KEY (order_id) REFERENCES orders(id)  -- ERROR!
  );
  -- MySQL partitioned table-da FK dəstəkləmir.

YANLIŞ (PostgreSQL 11 və əvvəl):
  -- Oxşar məhdudiyyət var idi.

DOĞRU:
  1. Application-level referential integrity
  2. Trigger-lər ilə yoxlama
  3. PostgreSQL 12+ FK dəstəkləyir (partition-lu parent üçün)
```

### 5. Mövcud table-ı partitioned etmək (canlı sistemdə)

```
YANLIŞ: ALTER TABLE ilə birbaşa partition əlavə etmək
  -- MySQL-də mövcud table-ı partition etmək mümkündür, amma:
  ALTER TABLE orders PARTITION BY RANGE ...
  -- ↑ TABLE LOCK! 50M sətir üçün saatlarla davam edə bilər!

DOĞRU: Online migration strategiyası:

  1. Yeni partitioned table yaratmaq
     CREATE TABLE orders_new ... PARTITION BY RANGE ...

  2. Dual-write tətbiq etmək
     INSERT → orders + orders_new

  3. Köhnə data-nı batch ilə köçürmək
     INSERT INTO orders_new SELECT * FROM orders
     WHERE id BETWEEN ? AND ? (batch 10K)

  4. Yoxlamaq (count, checksum)

  5. Atomic swap:
     RENAME TABLE orders TO orders_old, orders_new TO orders;
     (MySQL — çox qısa kilidləmə)

     PostgreSQL:
     BEGIN;
     ALTER TABLE orders RENAME TO orders_old;
     ALTER TABLE orders_new RENAME TO orders;
     COMMIT;

  6. Köhnə table-ı silmək (bir müddət saxlamaq yaxşıdır)
```

---

## PHP İmplementasiyası

### Partition Management Service

```php
<?php
// app/Services/PartitionManager.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use RuntimeException;

class PartitionManager
{
    /**
     * Mövcud partition-ları siyahılamaq.
     *
     * @return array<array{name: string, rows: int, data_length: int}>
     */
    public function listPartitions(string $table): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return $this->listMySQLPartitions($table);
        }

        if ($driver === 'pgsql') {
            return $this->listPostgreSQLPartitions($table);
        }

        throw new RuntimeException("Unsupported driver: {$driver}");
    }

    private function listMySQLPartitions(string $table): array
    {
        $database = DB::getDatabaseName();

        $partitions = DB::select("
            SELECT
                PARTITION_NAME as name,
                TABLE_ROWS as `rows`,
                DATA_LENGTH as data_length,
                INDEX_LENGTH as index_length,
                PARTITION_EXPRESSION as expression,
                PARTITION_DESCRIPTION as description,
                PARTITION_METHOD as method
            FROM INFORMATION_SCHEMA.PARTITIONS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND PARTITION_NAME IS NOT NULL
            ORDER BY PARTITION_ORDINAL_POSITION
        ", [$database, $table]);

        return array_map(fn ($p) => (array) $p, $partitions);
    }

    private function listPostgreSQLPartitions(string $table): array
    {
        $partitions = DB::select("
            SELECT
                child.relname as name,
                pg_relation_size(child.oid) as data_length,
                pg_indexes_size(child.oid) as index_length,
                (SELECT reltuples FROM pg_class WHERE oid = child.oid)::BIGINT as rows,
                pg_get_expr(child.relpartbound, child.oid) as bound_expr
            FROM pg_inherits
            JOIN pg_class parent ON pg_inherits.inhparent = parent.oid
            JOIN pg_class child ON pg_inherits.inhrelid = child.oid
            WHERE parent.relname = ?
            ORDER BY child.relname
        ", [$table]);

        return array_map(fn ($p) => (array) $p, $partitions);
    }

    /**
     * MySQL: Gələcək partition-lar yaratmaq (MAXVALUE split).
     */
    public function createFuturePartitions(
        string $table,
        int $monthsAhead = 3,
        string $partitionExpression = "YEAR(created_at) * 100 + MONTH(created_at)"
    ): array {
        $driver = DB::getDriverName();
        $created = [];

        if ($driver === 'mysql') {
            $created = $this->createMySQLFuturePartitions(
                $table,
                $monthsAhead,
                $partitionExpression
            );
        } elseif ($driver === 'pgsql') {
            $created = $this->createPostgreSQLFuturePartitions($table, $monthsAhead);
        }

        return $created;
    }

    private function createMySQLFuturePartitions(
        string $table,
        int $monthsAhead,
        string $expression
    ): array {
        // Mövcud partition-ları yoxla
        $existing = collect($this->listMySQLPartitions($table))
            ->pluck('name')
            ->toArray();

        $newPartitions = [];
        $now = Carbon::now();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $date = $now->copy()->addMonths($i);
            $partName = sprintf('p%d_%02d', $date->year, $date->month);

            if (in_array($partName, $existing)) {
                continue;
            }

            $nextDate = $date->copy()->addMonth();
            $bound = $nextDate->year * 100 + $nextDate->month;
            $newPartitions[] = "PARTITION {$partName} VALUES LESS THAN ({$bound})";
        }

        if (empty($newPartitions)) {
            Log::info("PartitionManager: {$table} üçün yeni partition lazım deyil.");
            return [];
        }

        // p_future-u MAXVALUE ilə yenidən əlavə et
        $newPartitions[] = "PARTITION p_future VALUES LESS THAN MAXVALUE";
        $partitionsSql = implode(",\n", $newPartitions);

        DB::statement("
            ALTER TABLE {$table} REORGANIZE PARTITION p_future INTO (
                {$partitionsSql}
            )
        ");

        Log::info("PartitionManager: {$table} üçün partition-lar yaradıldı.", [
            'partitions' => $newPartitions,
        ]);

        return $newPartitions;
    }

    private function createPostgreSQLFuturePartitions(
        string $table,
        int $monthsAhead
    ): array {
        $existing = collect($this->listPostgreSQLPartitions($table))
            ->pluck('name')
            ->toArray();

        $created = [];
        $now = Carbon::now();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $date = $now->copy()->addMonths($i);
            $partName = sprintf('%s_%d_%02d', $table, $date->year, $date->month);

            if (in_array($partName, $existing)) {
                continue;
            }

            $start = $date->startOfMonth()->toDateString();
            $end = $date->copy()->addMonth()->startOfMonth()->toDateString();

            DB::statement("
                CREATE TABLE IF NOT EXISTS {$partName}
                PARTITION OF {$table}
                FOR VALUES FROM ('{$start}') TO ('{$end}')
            ");

            $created[] = $partName;
        }

        Log::info("PartitionManager: {$table} üçün PG partition-lar yaradıldı.", [
            'partitions' => $created,
        ]);

        return $created;
    }

    /**
     * Köhnə partition-ları silmək.
     */
    public function dropOldPartitions(
        string $table,
        int $retentionMonths
    ): array {
        $driver = DB::getDriverName();
        $cutoff = Carbon::now()->subMonths($retentionMonths)->startOfMonth();
        $dropped = [];

        if ($driver === 'mysql') {
            $dropped = $this->dropMySQLOldPartitions($table, $cutoff);
        } elseif ($driver === 'pgsql') {
            $dropped = $this->dropPostgreSQLOldPartitions($table, $cutoff);
        }

        return $dropped;
    }

    private function dropMySQLOldPartitions(string $table, Carbon $cutoff): array
    {
        $partitions = $this->listMySQLPartitions($table);
        $dropped = [];

        foreach ($partitions as $partition) {
            $name = $partition['name'];

            // p_future və ya tanınmayan partition-ları keç
            if ($name === 'p_future' || !preg_match('/^p(\d{4})_(\d{2})$/', $name, $m)) {
                continue;
            }

            $partDate = Carbon::createFromDate((int) $m[1], (int) $m[2], 1);

            if ($partDate->lt($cutoff)) {
                DB::statement("ALTER TABLE {$table} DROP PARTITION {$name}");
                $dropped[] = $name;
                Log::info("PartitionManager: {$table}.{$name} silindi.");
            }
        }

        return $dropped;
    }

    private function dropPostgreSQLOldPartitions(string $table, Carbon $cutoff): array
    {
        $partitions = $this->listPostgreSQLPartitions($table);
        $dropped = [];

        foreach ($partitions as $partition) {
            $name = $partition['name'];

            // Partition adından tarixi çıxar
            $pattern = '/^' . preg_quote($table, '/') . '_(\d{4})_(\d{2})$/';
            if (!preg_match($pattern, $name, $m)) {
                continue;
            }

            $partDate = Carbon::createFromDate((int) $m[1], (int) $m[2], 1);

            if ($partDate->lt($cutoff)) {
                // Əvvəl detach, sonra drop (daha təhlükəsiz)
                DB::statement("ALTER TABLE {$table} DETACH PARTITION {$name}");
                DB::statement("DROP TABLE {$name}");
                $dropped[] = $name;
                Log::info("PartitionManager: {$name} detach+drop edildi.");
            }
        }

        return $dropped;
    }

    /**
     * Partition statistikalarını almaq.
     */
    public function getPartitionStats(string $table): array
    {
        $partitions = $this->listPartitions($table);

        $totalRows = 0;
        $totalDataSize = 0;
        $totalIndexSize = 0;

        foreach ($partitions as &$p) {
            $totalRows += $p['rows'] ?? 0;
            $totalDataSize += $p['data_length'] ?? 0;
            $totalIndexSize += $p['index_length'] ?? 0;
            $p['data_size_human'] = $this->formatBytes($p['data_length'] ?? 0);
            $p['index_size_human'] = $this->formatBytes($p['index_length'] ?? 0);
        }

        return [
            'table' => $table,
            'partition_count' => count($partitions),
            'total_rows' => $totalRows,
            'total_data_size' => $this->formatBytes($totalDataSize),
            'total_index_size' => $this->formatBytes($totalIndexSize),
            'partitions' => $partitions,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}
```

### Artisan Command (Cron Job üçün)

```php
<?php
// app/Console/Commands/ManagePartitionsCommand.php

namespace App\Console\Commands;

use App\Services\PartitionManager;
use Illuminate\Console\Command;

class ManagePartitionsCommand extends Command
{
    protected $signature = 'partitions:manage
        {--table=orders : Table adı}
        {--create-ahead=3 : Neçə ay irəli partition yarat}
        {--retention=12 : Neçə aylıq retention}
        {--stats : Yalnız statistika göstər}
        {--dry-run : Heç nə dəyişmə, yalnız planı göstər}';

    protected $description = 'Table partition-larını idarə et (yarat/sil)';

    public function handle(PartitionManager $manager): int
    {
        $table = $this->option('table');

        // Statistika rejimi
        if ($this->option('stats')) {
            $this->showStats($manager, $table);
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');

        // Yeni partition-lar yarat
        $ahead = (int) $this->option('create-ahead');
        $this->info("Yeni partition-lar yaradılır ({$ahead} ay irəli)...");

        if (!$dryRun) {
            $created = $manager->createFuturePartitions($table, $ahead);
            foreach ($created as $name) {
                $this->line("  + Yaradıldı: {$name}");
            }
            if (empty($created)) {
                $this->line("  Yeni partition lazım deyil.");
            }
        } else {
            $this->warn("  [DRY-RUN] Partition-lar yaradılacaq idi.");
        }

        // Köhnə partition-ları sil
        $retention = (int) $this->option('retention');
        $this->info("Köhnə partition-lar silinir ({$retention} ay retention)...");

        if (!$dryRun) {
            $dropped = $manager->dropOldPartitions($table, $retention);
            foreach ($dropped as $name) {
                $this->line("  - Silindi: {$name}");
            }
            if (empty($dropped)) {
                $this->line("  Silinəcək köhnə partition yoxdur.");
            }
        } else {
            $this->warn("  [DRY-RUN] Köhnə partition-lar silinəcək idi.");
        }

        // Statistika göstər
        if (!$dryRun) {
            $this->showStats($manager, $table);
        }

        return self::SUCCESS;
    }

    private function showStats(PartitionManager $manager, string $table): void
    {
        $stats = $manager->getPartitionStats($table);

        $this->info("\nPartition statistikaları: {$table}");
        $this->table(
            ['Partition', 'Sətir sayı', 'Data ölçüsü', 'Index ölçüsü'],
            array_map(fn ($p) => [
                $p['name'],
                number_format($p['rows'] ?? 0),
                $p['data_size_human'],
                $p['index_size_human'],
            ], $stats['partitions'])
        );

        $this->info("Cəmi: {$stats['partition_count']} partition, " .
            number_format($stats['total_rows']) . " sətir, " .
            "Data: {$stats['total_data_size']}, Index: {$stats['total_index_size']}");
    }
}
```

```php
<?php
// app/Console/Kernel.php (və ya routes/console.php Laravel 11+)

use Illuminate\Support\Facades\Schedule;

// Hər gün gecə 2:00-da partition maintenance
Schedule::command('partitions:manage --table=orders --create-ahead=3 --retention=12')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/partition-maintenance.log'));

// Logs table üçün daha qısa retention
Schedule::command('partitions:manage --table=application_logs --create-ahead=1 --retention=3')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
```

### Partition Health Check

```php
<?php
// app/Services/PartitionHealthCheck.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PartitionHealthCheck
{
    public function __construct(
        private PartitionManager $manager,
    ) {}

    /**
     * Partition sağlamlıq yoxlaması.
     *
     * @return array{healthy: bool, issues: string[]}
     */
    public function check(string $table): array
    {
        $issues = [];
        $partitions = $this->manager->listPartitions($table);

        // 1. Partition sayı yoxlaması
        $count = count($partitions);
        if ($count === 0) {
            $issues[] = "CRITICAL: {$table} table-da heç bir partition yoxdur!";
        } elseif ($count > 500) {
            $issues[] = "WARNING: {$table} table-da {$count} partition var (>500, over-partitioning riski).";
        }

        // 2. Gələcək partition yoxlaması
        $hasUpcoming = $this->hasUpcomingPartitions($table, $partitions);
        if (!$hasUpcoming) {
            $issues[] = "CRITICAL: {$table} table-da gələcək ay üçün partition yoxdur! " .
                        "INSERT-lər default partition-a və ya ERROR-a düşəcək.";
        }

        // 3. Boş partition yoxlaması
        $emptyCount = 0;
        foreach ($partitions as $p) {
            if (($p['rows'] ?? 0) === 0) {
                $emptyCount++;
            }
        }
        if ($emptyCount > $count * 0.5 && $count > 4) {
            $issues[] = "WARNING: {$table} table-da {$emptyCount}/{$count} partition boşdur. " .
                        "Partition strategiyasını nəzərdən keçirin.";
        }

        // 4. Çox böyük partition yoxlaması (disbalans)
        if ($count > 1) {
            $rows = array_column($partitions, 'rows');
            $maxRows = max($rows);
            $avgRows = array_sum($rows) / count($rows);

            if ($avgRows > 0 && $maxRows > $avgRows * 5) {
                $bigPartition = '';
                foreach ($partitions as $p) {
                    if (($p['rows'] ?? 0) === $maxRows) {
                        $bigPartition = $p['name'];
                        break;
                    }
                }
                $issues[] = "WARNING: {$bigPartition} partition ortalamadan 5x böyükdür " .
                            "(rows: " . number_format($maxRows) . ", avg: " . number_format($avgRows) . "). " .
                            "Split etməyi düşünün.";
            }
        }

        return [
            'healthy' => empty($issues),
            'partition_count' => $count,
            'issues' => $issues,
        ];
    }

    private function hasUpcomingPartitions(string $table, array $partitions): bool
    {
        $nextMonth = Carbon::now()->addMonth();
        $expectedPattern = sprintf('%d_%02d', $nextMonth->year, $nextMonth->month);

        foreach ($partitions as $p) {
            $name = $p['name'];
            if (str_contains($name, $expectedPattern) || $name === 'p_future' || str_contains($name, 'default')) {
                return true;
            }
        }

        return false;
    }
}
```

---

## İntervyu Sualları

### Sual 1: Table partitioning nədir və nə vaxt istifadə olunmalıdır?

**Cavab:**

Table partitioning — böyük bir table-ı verilənlər bazası daxilində fiziki olaraq kiçik hissələrə bölmə texnikasıdır. Tətbiq səviyyəsində table hələ də vahid table kimi görünür, amma mühərrik daxildə ayrı fiziki fayllarla işləyir.

İstifadə olunmalıdır:
- Table 10 milyondan çox sətir olduqda
- Zaman əsaslı query-lər dominant olduqda (logs, orders, events)
- Köhnə data-nı tez-tez silmək lazım olduqda (DROP PARTITION, DELETE-dən 100-1000x sürətli)
- Maintenance əməliyyatlarını (VACUUM, REINDEX) kiçik hissələrdə etmək istədikdə

İstifadə olunmamalıdır:
- Kiçik table-larda (overhead fayda vermir)
- Query-lər partition açarını istifadə etmədikdə
- Bütün data-ya eyni vaxtda müraciət lazım olduqda

---

### Sual 2: Partitioning ilə Sharding arasında fərq nədir?

**Cavab:**

Partitioning bir server daxilində table-ın fiziki bölünməsidir. Tətbiq dəyişikliyi tələb etmir, SQL query-lər eyni qalır, ACID tranzaksiyalar tam işləyir. Vertikal ölçəkləndirmə limitinə tabedir.

Sharding isə data-nın bir neçə müstəqil server arasında paylanmasıdır. Tətbiq routing məntiqi əlavə etməlidir, cross-shard JOIN və tranzaksiya çətindir, amma horizontal ölçəkləndirmə imkanı verir.

Qısa desək: partitioning — bir evin otaqlarını bölmək, sharding — bir neçə evə köçürmək.

---

### Sual 3: Partition pruning nədir və niyə vacibdir?

**Cavab:**

Partition pruning — query optimizer-in WHERE şərtinə əsasən lazımsız partition-ları avtomatik atlama mexanizmidir. Məsələn, 12 aylıq partition-lu table-da `WHERE created_at = '2024-08-15'` query-si yalnız avqust partition-unu oxuyur, qalan 11-ini atlayır.

Vacibdir çünki partitioning-in performans üstünlüyünün əsas mənbəyidir. Pruning olmadan partitioning heç bir fayda vermir, əksinə overhead əlavə edir.

Pruning-in işləməsi üçün: WHERE-də partition açarını birbaşa istifadə edin, funksiya tətbiq etməyin (`YEAR(created_at)` yerinə range comparison yazın).

---

### Sual 4: MySQL-də partitioned table yaradarkən PRIMARY KEY qaydası nədir?

**Cavab:**

MySQL tələb edir ki, hər unique index (PRIMARY KEY daxil) partition açarını əhatə etsin. Yəni partition açarı PRIMARY KEY-in bir hissəsi olmalıdır.

Məsələn: `PARTITION BY RANGE(created_at)` istifadə edirsinizsə, PRIMARY KEY `(id)` yox, `(id, created_at)` olmalıdır. Bu məhdudiyyət MySQL-in hər partition-da unique-liyi müstəqil yoxlamasından irəli gəlir.

PostgreSQL-də oxşar qayda var — partition açarı primary key-in bir hissəsi olmalıdır. Bu, unikal identifikasiya üçün əlavə düşüncə tələb edir.

---

### Sual 5: Mövcud böyük table-ı canlı sistemdə necə partitioned etmək olar?

**Cavab:**

Birbaşa `ALTER TABLE ... PARTITION BY` uzun müddət table-ı kilidləyir. Canlı sistemdə təhlükəsiz yol:

1. Yeni partitioned table yaradın (eyni sxem, partition-larla)
2. Dual-write tətbiq edin (hər INSERT hər iki table-a yazılsın)
3. Köhnə data-nı batch-lərlə köçürün (10K-100K sətir, sleep arasında)
4. Data bütövlüyünü yoxlayın (count, checksum)
5. Atomic rename ilə swap edin (`RENAME TABLE orders TO orders_old, orders_new TO orders`)
6. Köhnə table-ı bir müddət saxlayın, sonra silin

Bu proses downtime olmadan keçirilə bilər, amma diqqətli planlaşdırma tələb edir.

---

### Sual 6: Laravel-də partitioned table ilə Eloquent istifadə edərkən hansı diqqət məqamları var?

**Cavab:**

Əsas diqqət məqamları:

1. **Partition pruning**: Query-lərdə həmişə partition açarını (məs. `created_at`) WHERE şərtinə əlavə edin. Scope-lar istifadə etmək yaxşı praktikadır (`scopeThisMonth`, `scopeInPeriod`).

2. **find() problemi**: `Order::find(123)` bütün partition-ları oxuya bilər, çünki yalnız `id` ilə axtarır. Əlavə partition açarı şərti lazımdır.

3. **Eager Loading**: `with('orders')` partition açarı olmadan bütün partition-ları scan edir. Constrained eager loading istifadə edin.

4. **Migration**: Laravel Schema builder partitioning-i birbaşa dəstəkləmir. `DB::statement()` ilə raw SQL istifadə edin.

5. **Cron maintenance**: Artisan command ilə yeni partition-ları avtomatik yaradın və köhnələrini silin. `pg_partman` PostgreSQL-də bu işi asanlaşdırır.
