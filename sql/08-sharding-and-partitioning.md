# Sharding & Partitioning

## Partitioning (Tek server daxilinde)

Boyuk table-i kicik hisselere (partition) bolmek. Data eyni server-dedir, amma fiziki olaraq ayri saxlanilir.

### Partition Novleri

#### 1. Range Partitioning

```sql
-- MySQL
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT,
    created_at DATE NOT NULL,
    total_amount DECIMAL(10,2),
    status VARCHAR(20),
    PRIMARY KEY (id, created_at)  -- Partition key PK-da olmalidir!
) PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2022 VALUES LESS THAN (2023),
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

**Query zamani partition pruning:**

```sql
-- Yalniz p2024 partition-una baxir (diger partition-lar skip olunur)
SELECT * FROM orders WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31';

-- EXPLAIN ile yoxla:
EXPLAIN SELECT * FROM orders WHERE created_at >= '2024-06-01';
-- partitions: p2024,p2025,p_future (yalniz lazimi partition-lar)
```

**Kohne data silmek cox suretlidir:**

```sql
-- YAVAS: milyon row-u silmek
DELETE FROM orders WHERE created_at < '2022-01-01';

-- SURETLI: partition DROP etmek (aninda!)
ALTER TABLE orders DROP PARTITION p2022;
```

#### 2. List Partitioning

```sql
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT,
    country_code VARCHAR(2) NOT NULL,
    total_amount DECIMAL(10,2),
    PRIMARY KEY (id, country_code)
) PARTITION BY LIST COLUMNS (country_code) (
    PARTITION p_europe VALUES IN ('DE', 'FR', 'GB', 'NL'),
    PARTITION p_asia VALUES IN ('JP', 'CN', 'IN', 'KR'),
    PARTITION p_americas VALUES IN ('US', 'CA', 'BR', 'MX'),
    PARTITION p_other VALUES IN ('AU', 'AZ', 'TR')
);
```

#### 3. Hash Partitioning

Data-ni beraber bolusdurur.

```sql
CREATE TABLE sessions (
    id BIGINT AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    data TEXT,
    PRIMARY KEY (id, user_id)
) PARTITION BY HASH (user_id)
PARTITIONS 8;

-- user_id % 8 = partition nomresi
-- Data beraber paylanir
```

#### 4. Key Partitioning (MySQL)

MySQL-in oz hash function-u ile.

```sql
CREATE TABLE logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    message TEXT
) PARTITION BY KEY ()
PARTITIONS 4;
-- PRIMARY KEY-e gore hash edir
```

### PostgreSQL Partitioning

PostgreSQL 10+ native partitioning destekleyir:

```sql
-- Parent table
CREATE TABLE orders (
    id BIGSERIAL,
    created_at DATE NOT NULL,
    total_amount DECIMAL(10,2)
) PARTITION BY RANGE (created_at);

-- Child table-lar (partition-lar)
CREATE TABLE orders_2024 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');

CREATE TABLE orders_2025 PARTITION OF orders
    FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');

-- Avtomatik partition yaratma (pg_partman extension)
```

---

## Sharding (Bir nece server arasinda)

Data-ni ferqli database server-lerine bolmek. Her server data-nin bir hissesini saxlayir.

```
                    [Application]
                    /     |     \
            [Shard 1]  [Shard 2]  [Shard 3]
            Users A-H   Users I-P  Users Q-Z
```

### Sharding Strategiyalari

#### 1. Range-Based Sharding

```
Shard 1: user_id 1 - 1,000,000
Shard 2: user_id 1,000,001 - 2,000,000
Shard 3: user_id 2,000,001 - 3,000,000
```

**Problem:** Hotspot - yeni user-ler hemishe son shard-a dusur.

#### 2. Hash-Based Sharding

```php
function getShard(int $userId): string
{
    $shardId = crc32((string) $userId) % 3;
    
    return match ($shardId) {
        0 => 'shard_1',
        1 => 'shard_2',
        2 => 'shard_3',
    };
}

// Istifade
$shardName = getShard($userId);
$user = DB::connection($shardName)->table('users')->find($userId);
```

**Problem:** Shard elave etdikde butun data-ni yeniden paylamaq lazimdir (resharding).

#### 3. Consistent Hashing

Resharding zamani minimum data kocurulmesini temin edir.

```
Hash ring:
0 --------- Shard1 --------- Shard2 --------- Shard3 --------- 0
     [user data]       [user data]       [user data]

Yeni shard elave olunanda:
0 --- Shard1 --- Shard4 --- Shard2 --- Shard3 --- 0
                  ^-- yalniz yaxin shard-lardan data kocur
```

#### 4. Directory-Based Sharding

Lookup table ile hansi data hansi shard-da oldugunu saxla.

```sql
-- Shard directory table (ayri database)
CREATE TABLE shard_directory (
    user_id BIGINT PRIMARY KEY,
    shard_id INT NOT NULL
);
```

```php
function getShard(int $userId): string
{
    $shardId = DB::connection('directory')
        ->table('shard_directory')
        ->where('user_id', $userId)
        ->value('shard_id');
    
    return "shard_{$shardId}";
}
```

### Laravel-de Sharding

```php
// config/database.php
'connections' => [
    'shard_1' => [
        'driver' => 'mysql',
        'host' => 'shard1.db.com',
        'database' => 'myapp',
    ],
    'shard_2' => [
        'driver' => 'mysql',
        'host' => 'shard2.db.com',
        'database' => 'myapp',
    ],
],

// Model-de
class User extends Model
{
    public function getConnectionName()
    {
        return 'shard_' . ($this->id % 2 + 1);
    }
}

// Query zamani
$user = DB::connection(getShard($userId))->table('users')->find($userId);
```

---

## Sharding Problemleri

### 1. Cross-Shard Queries

```php
// Problem: Butun user-lerin sayini tapmaq
// Her shard-dan ayri-ayri saymaq lazimdir
$total = 0;
foreach (['shard_1', 'shard_2', 'shard_3'] as $shard) {
    $total += DB::connection($shard)->table('users')->count();
}
```

### 2. Cross-Shard Joins

```sql
-- Bu islemir! User shard_1-de, order shard_2-de ola biler
SELECT u.*, o.* FROM users u JOIN orders o ON o.user_id = u.id;

-- Hell: Eyni entity-nin related data-sini eyni shard-da saxla
-- User ve onun orders-lari eyni shard-da olmalidir
```

### 3. Cross-Shard Transactions

Distributed transaction lazimdir (2PC, Saga pattern). Cox complex ve yavasdir.

### 4. Resharding

Yeni shard elave etmek ve ya data-ni yeniden paylamaq **en cetin** mesele.

---

## Sharding Key secimi

**En muhum qerar!** Shard key nece secilir:

| Meyyar | Izah |
|--------|------|
| Even distribution | Data beraber paylanmalidir |
| Query pattern | En cox istifade olunan query shard key-i ehate etmeli |
| Locality | Related data eyni shard-da olmali |
| Growth | Yeni data beraber paylanmali (hotspot olmamali) |

**Misal:**

```
E-commerce app:
- Shard key: user_id ✅
  - User ve onun orders, payments-i eyni shard-da
  - Cogu query user_id ile filtrlenir
  
- Shard key: order_id ❌
  - Bir user-in orders-lari ferqli shard-larda olacaq
  - User-in butun orders-larini almaq ucun butun shard-lari scan etmek lazim
```

---

## Partitioning vs Sharding

| Xususiyyet | Partitioning | Sharding |
|------------|-------------|----------|
| Server sayi | Tek server | Bir nece server |
| Murekkeblk | Asagi | Yuksek |
| Cross-partition query | Asandir (eyni server) | Cetindir (ferqli server) |
| Scaling | Vertical (daha guclu server) | Horizontal (daha cox server) |
| Transaction | Adi transaction | Distributed transaction |
| Ne vaxt | Table boyuk, amma tek server kifayet edir | Tek server artiq kifayet etmir |

---

## Interview suallari

**Q: Ne vaxt sharding lazimdir?**
A: Tek server-in resurslari (CPU, RAM, disk I/O, storage) kifayet etmeyende. Evvelce baxilmali seylər: indexing, query optimization, caching, read replica, vertical scaling. Butun bunlar kifayet etmirse, sharding lazimdir.

**Q: Sharding key nece secilir?**
A: 1) En cox isleyen query-lerin WHERE sertine bax. 2) Related data eyni shard-da olmalidir. 3) Data beraber paylanmalidir (hotspot yoxdur). 4) E-commerce-de adeten user_id, multi-tenant app-da tenant_id yaxsi secimdir.

**Q: Resharding nece edilir?**
A: 1) Yeni shard-lari hazirla. 2) Data-ni kopyala (dual-write ve ya background migration). 3) Application-u yeni shard config ile deyis. 4) Kohne shard-lardan data-ni sil. Bu proses downtime olmadan etmek cox cetindir - buna gore evvelceden yeterli shard yaratmaq (over-shard) tovsiye olunur.
