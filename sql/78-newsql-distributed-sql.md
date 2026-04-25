# NewSQL & Distributed SQL (CockroachDB, Spanner, TiDB, YugabyteDB) (Lead)

## NewSQL Nedir?

**NewSQL** = SQL interfeysi + ACID transactions + horizontal scale. NoSQL kimi miqyaslanir, amma SQL semantikasini saxlayir.

```
SQL (MySQL, Postgres):
  + ACID, JOIN, deqiq
  - Yalniz vertical scale (boyuk single-node)

NoSQL (Mongo, Cassandra):
  + Horizontal scale (yuzlerle node)
  - Eventual consistency, JOIN yox, schema deyisikliyi cetin

NewSQL (Spanner, Cockroach, TiDB):
  + ACID + JOIN + SQL
  + Horizontal scale (1 → 1000 node)
  + Geo-distributed
  - Yuksek latency (cross-region consensus)
  - Bahalidir
```

## NoSQL vs NewSQL Ferqi

| Cehet | NoSQL | NewSQL |
|-------|-------|--------|
| Query | Proprietary API | Standart SQL |
| Schema | Schema-less | Strict schema |
| Transaction | Eventual / single-doc | Full ACID, multi-row |
| JOIN | Yoxdur / yavas | Native |
| Horizontal scale | Beli | Beli |
| Migration | App layer | DDL |
| Best fit | Flexible data, write-heavy | OLTP at scale, global app |

---

## Google Spanner — Distributed SQL Pioneeri

Google-un global database-i. AdWords, Gmail metadata, Google Photos burada qalir.

### TrueTime API

Spanner-in sehri **TrueTime**-dir. GPS + atomic clock kombinasiyasi ile butun datacenter-lerde **vaxt aralığı** (TT.now() = [earliest, latest]) verir.

```
Adi NTP:    "Vaxt 12:00:05.123" (yanlis ola biler ±100ms)
TrueTime:   "Vaxt 12:00:05.120-12:00:05.126 arasındadır" (zəmanət)
```

Bu zaman aralığı sayesinde Spanner **external consistency** verir — global olaraq eyni vaxtda baş verən transaction-lar butun region-lar üçün eyni siralanır.

### Spanner Xüsusiyyətləri

- **Synchronously replicated** — yazılış 5 region-a eyni anda
- **Paxos consensus** — quorum (3/5) tesdiq lazimdir
- **Horizontal split** — table-lar avtomatik split olunur (region-larda)
- **Interleaved tables** — parent-child eyni split-de saxlanir (locality)

```sql
-- Spanner DDL — interleaved table
CREATE TABLE Customers (
    CustomerId STRING(36) NOT NULL,
    Name STRING(MAX),
    Email STRING(255),
) PRIMARY KEY (CustomerId);

CREATE TABLE Orders (
    CustomerId STRING(36) NOT NULL,
    OrderId STRING(36) NOT NULL,
    Total NUMERIC,
    OrderDate TIMESTAMP,
) PRIMARY KEY (CustomerId, OrderId),
INTERLEAVE IN PARENT Customers ON DELETE CASCADE;
-- Customer-in butun Order-leri eyni node-da → JOIN suretli
```

### Spanner Qiymetlendirme

- ~$0.90 / node / saat (3 node minimum) ≈ $1900/ay baza
- Storage: $0.30 / GB / ay
- Backup: $0.10 / GB / ay
- Network egress: standart GCP

> **Real:** Kicik startup ucun cox bahadir. Yalniz **global scale** (10+ region, milyardlar row) layihə üçün məntiqi var.

---

## CockroachDB — Open-Source Spanner

Spanner-in açıq-mənbə alternatividir. Eski Google muhendis-leri yaradib. PostgreSQL wire protocol ile uyğundur.

### Memarliq

```
Layer:
  SQL Layer       (Postgres-uyumlu parser)
  Distributed KV  (key range → range)
  Replication     (Raft consensus, 3 replica)
  Storage         (Pebble, RocksDB-fork)
```

- **Range** = 512 MB key-range. Cox boyuyende avtomatik **split** olunur.
- **Raft** = her range-in 3 replica arasinda lider seçimi
- **MVCC** = HLC (Hybrid Logical Clock) + timestamp ile snapshot isolation

### CockroachDB Misalı

```sql
-- Postgres-uyumlu DDL
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email STRING UNIQUE NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Multi-region (cross-region replication)
ALTER DATABASE myapp PRIMARY REGION "europe-west1";
ALTER DATABASE myapp ADD REGION "us-east1";
ALTER DATABASE myapp ADD REGION "asia-northeast1";

-- Table-niz region-lara baglilig sec
ALTER TABLE users SET LOCALITY REGIONAL BY ROW;
ALTER TABLE config SET LOCALITY GLOBAL;            -- her region-da read replica
ALTER TABLE products SET LOCALITY REGIONAL BY TABLE IN "europe-west1";

-- Transaction (Postgres ile eyni)
BEGIN;
UPDATE accounts SET balance = balance - 100 WHERE id = 1;
UPDATE accounts SET balance = balance + 100 WHERE id = 2;
COMMIT;

-- Bezi ferqler:
-- 1. SERIAL deyil, UUID istifade et (sequence sharding problemi)
-- 2. Long transaction (>5 minute) tovsiye olunmur (contention)
-- 3. SELECT FOR UPDATE var, amma serializable default deyil
```

### Laravel + CockroachDB

```php
// .env
DB_CONNECTION=pgsql
DB_HOST=cockroach.mycluster.com
DB_PORT=26257
DB_DATABASE=myapp
DB_USERNAME=admin

// config/database.php — PostgreSQL driver isleyir
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT', '26257'),  // Default 26257
    'sslmode' => 'verify-full',
    'sslrootcert' => env('DB_SSL_CA'),
    'options' => [
        // Retry transaction-larda lazimdir (40001 kod)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],
],

// Retry pattern (CockroachDB-ye xas)
DB::transaction(function () {
    Account::where('id', 1)->decrement('balance', 100);
    Account::where('id', 2)->increment('balance', 100);
}, 5);  // 5 defe yenideın çalış (40001 retryable error)
```

> **Tələ:** CockroachDB serializable default-dur. Postgres-de RC isleyen kod burada `40001` retryable xeta verir. Application-da retry loop lazim.

---

## TiDB — MySQL Compatible Distributed SQL

PingCAP terefinden, MySQL wire protocol ile uyğundur. **HTAP** (Hybrid Transactional/Analytical) — eyni sistemde OLTP + OLAP.

### Memarliq

```
TiDB (SQL layer, stateless) ──> TiKV (transactional KV)
                            \─> TiFlash (columnar, analytics)
                                  ↑ Raft Learner
                                  └ TiKV-den asinxron sync
```

- **TiKV** — RocksDB esasli, Raft replication, transaction
- **TiFlash** — column store, analytics query-leri suretlendirir
- **PD (Placement Driver)** — metadata, leader election, scheduling

### TiDB Spesifik

```sql
-- AUTO_RANDOM (sequence ucun monotonik PK problemini hell edir)
CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTO_RANDOM,
    user_id BIGINT,
    total DECIMAL(10,2)
);

-- Real-time analytics ucun TiFlash replica
ALTER TABLE orders SET TIFLASH REPLICA 1;

-- Eyni query — TiDB optimizer TiKV ya da TiFlash secir
SELECT user_id, SUM(total) FROM orders WHERE created_at > '2026-01-01' GROUP BY user_id;
-- ^ Buyuk aggregation → TiFlash (columnar)

SELECT * FROM orders WHERE id = 12345;
-- ^ Point lookup → TiKV (row store)
```

### Laravel + TiDB

```php
// MySQL driver-i isleyir, .env-de port 4000
DB_CONNECTION=mysql
DB_HOST=tidb-cluster.example.com
DB_PORT=4000
DB_DATABASE=myapp

// Migration teyin etmek lazim deyil — MySQL kimi
Schema::create('orders', function (Blueprint $table) {
    $table->id();  // BIGINT AUTO_INCREMENT, amma scale problemi var
    // Daha yaxsi: AUTO_RANDOM
    // $table->bigInteger('id', true)->primary();  + raw ALTER
    $table->foreignId('user_id');
    $table->decimal('total', 10, 2);
    $table->timestamps();
});
```

---

## YugabyteDB — PostgreSQL Compatible

Yugabyte 2 layer-den ibaretdir:
- **YSQL** — PostgreSQL wire protocol (Cassandra fork-undan dəyişdirilib)
- **YCQL** — Cassandra wire protocol
- **DocDB** — distributed storage (Raft, RocksDB)

```sql
-- 100% Postgres uyumlu (extension-lar daxil)
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    price NUMERIC(10, 2),
    tags TEXT[]
) SPLIT INTO 12 TABLETS;  -- explicit sharding

-- Tablespace ile region pinning
CREATE TABLESPACE eu_west_zone WITH (
  replica_placement='{"num_replicas":3,
                      "placement_blocks":[
                        {"cloud":"aws","region":"eu-west-1","zone":"a","min_num_replicas":1},
                        {"cloud":"aws","region":"eu-west-1","zone":"b","min_num_replicas":1},
                        {"cloud":"aws","region":"eu-west-1","zone":"c","min_num_replicas":1}
                      ]}'
);
```

---

## Vitess + PlanetScale — MySQL Sharding

Vitess YouTube-da yaranib. Trillions of rows MySQL ile manage etmek ucun. **PlanetScale** = managed Vitess.

```
App ──> VTGate (proxy, query router) ──> VTTablet ──> MySQL shard 1
                                     ──> VTTablet ──> MySQL shard 2
                                     ──> VTTablet ──> MySQL shard 3
```

**PlanetScale features:**
- **Branching** — git kimi schema branch
- **Online schema change** — zero-downtime DDL (gh-ost esasli)
- **Connection pooling** — daxili
- **Read replica routing** — coğrafi

```php
// PlanetScale = MySQL drop-in
DB_CONNECTION=mysql
DB_HOST=aws.connect.psdb.cloud
DB_PORT=3306
DB_USERNAME=xxx
DB_PASSWORD=pscale_xxx
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

// Tələ: PlanetScale-de FOREIGN KEY default qadagandir!
// (Vitess sharding-de FK destekleyir, amma manual unbridge lazim)
```

---

## Distributed SQL Müqayisə

| Cehet | Spanner | CockroachDB | TiDB | YugabyteDB | Vitess/PS |
|-------|---------|-------------|------|------------|-----------|
| **Wire protocol** | gRPC + JDBC | PostgreSQL | MySQL | Postgres + Cassandra | MySQL |
| **Consensus** | Paxos | Raft | Raft | Raft | Async (MySQL repl) |
| **Geo-distrib.** | Native, TrueTime | Native, HLC | TiKV regional | Multi-region | Manual cell |
| **Open source** | Yox (PostgreSQL Spanner emulator var) | Beli (BSL) | Beli (Apache 2) | Beli (Apache 2) | Beli (Apache 2) |
| **Managed** | GCP only | Cockroach Cloud | TiDB Cloud | Yugabyte Cloud | PlanetScale |
| **Strong cons.** | External consistency | Serializable | Snapshot iso | Serializable | Read-your-write |
| **HTAP** | Yox | Yox | Beli (TiFlash) | Hisselit | Yox |
| **Foreign Key** | Beli | Beli | Beli (8.0) | Beli | Sharded-de yox |

---

## Raft / Paxos Qisaca

**Consensus** — N node arasinda eyni dəyər üzərində razılaşmaq.

**Raft (sade, anlayişli):**
1. Leader election — bir node lider seçilir
2. Log replication — lider bütün entry-leri follower-lara yazir
3. Quorum (N/2 + 1) tesdiq edirse → committed

**Paxos (Spanner istifade edir):**
- Multi-Paxos daha murekkebdir, amma daha cevikdir
- Split-brain yoxdur

> 5-node cluster: 2 node düşse, sistem işlemeyə davam edir (3/5 quorum). 3 node düşse → unavailable.

---

## Ne vaxt Distributed SQL? Ne vaxt Yox?

### Lazimdir (məsuliyyətli secim)

- **Global app** — Avropa + ABS + Asiya istifadeci, hər region <100ms latency
- **Compliance / data residency** — GDPR (EU data EU-da qalmalidir), CCPA
- **Massive write throughput** — 50K+ TPS sustained, vertical scale yetmir
- **High availability** — multi-region failover, RPO=0, RTO seconds
- **Horizontal scale roadmap** — bu il 100GB, 3 il sonra 100TB

### Lazim deyil (ovverkill)

- Yegane region-da işləyən app
- 1-10K TPS, kicik data (<1TB)
- Komandanin Postgres/MySQL bilik deryasi var
- Bahaliliq tolerans yoxdur (Spanner $2k/ay-dan baslayir)
- Cross-region latency qebuledilməzdir (write-heavy)

### Cross-region Latency Reality

```
Single-region commit:          1-5 ms
Multi-region (5 region) commit: 50-150 ms (Paxos round-trip)
Single-row read (local):       1-3 ms
Multi-region read (stale OK):  1-3 ms
Multi-region read (consistent): 50-150 ms
```

> Distributed SQL **read-your-own-write** ucun tradeoff teleb edir. Eger app cox latency-sensitive-dirse, single-region deployment + read replica daha praktikdir.

---

## Transaction Limitləri (Real Real Real)

| Limit | CockroachDB | TiDB | YugabyteDB |
|-------|-------------|------|------------|
| Max transaction size | 64MB | 100MB (defalt) | 16MB |
| Max contention retry | App-da loop | App-da loop | App-da loop |
| Long transaction | <5min tovsiye | <10min | <5min |
| Cross-region tx | Beli, amma yavas | Beli | Beli |

```php
// Tipik retry pattern
function retryableTransaction(callable $callback, int $maxRetries = 5)
{
    $attempt = 0;
    while (true) {
        try {
            return DB::transaction($callback);
        } catch (\PDOException $e) {
            $attempt++;
            // CockroachDB: 40001, TiDB: 9007, YugabyteDB: 40001
            if (!in_array($e->getCode(), ['40001', '9007']) || $attempt >= $maxRetries) {
                throw $e;
            }
            usleep(2 ** $attempt * 10_000);  // exponential backoff
        }
    }
}
```

---

## Migrations: Postgres-den CockroachDB-ye

```php
// Adi Laravel migration islayir, bezi ferqler:

// 1. SERIAL yerine UUID (monotonik PK = hot range)
$table->uuid('id')->primary();
// CockroachDB: gen_random_uuid()

// 2. Index online yaradilir (default DDL bloklamir)
Schema::table('orders', fn($t) => $t->index('user_id'));

// 3. ALTER TABLE ADD COLUMN online-dur
$table->string('new_column')->nullable();  // OK

// 4. Foreign Key on millions row → bezi versiyalarda yavas
//    Validate later istifade et
```

---

## Cost Realiteti

| Provider | Min ay | Yararlandirma |
|----------|--------|---------------|
| **CockroachDB Serverless** | $0 (free 5GB) | Kicik prod ucun real |
| **CockroachDB Dedicated** | ~$700/ay | 3 node, 2 vCPU |
| **Spanner** | ~$2000/ay | 3 node minimum |
| **TiDB Cloud Serverless** | $0 (free 25GB) | Demek olar Spanner kimi |
| **YugabyteDB Aeon** | ~$200/ay | Free tier var |
| **PlanetScale** | $0 (Hobby tier 5GB) | $39+ Scaler tier |

---

## Interview suallari

**Q: NewSQL ile NoSQL arasinda esas ferqi izah et.**
A: NewSQL ACID transaction + JOIN + standart SQL saxlayir, amma horizontal scale verir (Cockroach, Spanner, TiDB). NoSQL eventual consistency ile horizontal scale verir, amma JOIN yox, transaction yalniz single-document. NewSQL = SQL semantikasi + NoSQL miqyasi. Bahalilig və latency price tradeoff-udur.

**Q: TrueTime nedir, niye lazimdir?**
A: Google Spanner-in GPS + atomic clock kombinasiyali API-sidir. Adi NTP-de saatler arasinda 100ms+ ferq ola biler. TrueTime "vaxt mutleq [t-ε, t+ε] arasındadır" zəmanəti verir (ε ~7ms). Bu sayede butun region-lar üçün **external consistency** mümkündür — global olaraq commit-lerin sirası deqiqdir.

**Q: CockroachDB-de niye 40001 retryable xəta cox baş verir?**
A: CockroachDB serializable isolation default-dur. İki transaction eyni row-a tesir edirse, biri abort olur (40001). PostgreSQL-de Read Committed default oldugu ucun bu bas vermir. Helli — application-da retry loop ile exponential backoff, ya da SELECT FOR UPDATE ile lock al. Hot row-lara dəyişiklik ucun queue/aggregator pattern.

**Q: Distributed SQL kicik startup ucun məntiqlidirmi?**
A: Adətən yox. Single-region Postgres + read replica 95% case-i hell edir. Distributed SQL real lazim olur: (1) çoxlu coğrafi region-da yuksek availability, (2) data residency complaice (GDPR), (3) vertical scale yetmir (50K+ TPS sustained). Erkən startup-da Postgres + Aurora ile baslamaq, sonra lazim olarsa migration etmek daha praktikdir.

**Q: PlanetScale FK-ni niye qadagan etdi (sonra acdı)?**
A: Vitess sharding-de cross-shard FK enforcement çətin/baha basa gəlir. PlanetScale 2024-e qeder FK-ni umumi qadagan etmisdi — application-da check tələb olunurdu. 2024-de online FK destek elave etdiler (eyni shard daxilinde). Best practice — sharding key uzre data-ni eyni shard-a yonlendirmek (e.g., `customer_id` shard key, butun customer order-leri eyni shard-da).
