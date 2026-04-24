# Storage Internals (WAL, Buffer Pool, Pages)

> **Seviyye:** Advanced ⭐⭐⭐

## Storage Layer Niye Vacibdir?

Senior developer bilmelidir - DB **disk + memory** ile nece islir. Bu olmasa:
- "Niye INSERT yavasdir?" sualina cavab vermek olmur
- Index, fillfactor, vacuum kimi konsepleri anlamamaq
- Crash recovery, replication, backup mexanizmlerini anlamamaq

---

## Page Layout (Fiziki Saxlanma)

DB data **page** (block) seviyyesinde saxlayir - row-larla yox.

| DB | Page size | Configurable? |
|----|-----------|---------------|
| InnoDB (MySQL) | 16 KB | Compile-time |
| PostgreSQL | 8 KB | Compile-time (--with-blocksize) |
| SQL Server | 8 KB | Yox |
| Oracle | 2/4/8/16/32 KB | Yox (DB create vaxti) |

**Page strukturu (sadelesdirilmis):**

```
+-----------------------------------+
| Page Header (24-100 bytes)        |  -> checksum, LSN, free space
+-----------------------------------+
| Row pointers (slot array)         |  -> her row-a offset
+-----------------------------------+
| ...free space...                  |
+-----------------------------------+
| Row N data                        |
| Row N-1 data                      |
| ...                               |
| Row 0 data                        |
+-----------------------------------+
| Page Trailer (checksum)           |
+-----------------------------------+
```

**Niye 8/16 KB?** OS page (4 KB) ile uygun, atomic write, random I/O ile balans.

---

## Buffer Pool (RAM Cache)

DB hot data-ni RAM-da saxlayir - disk read minimum olur.

**MySQL InnoDB:**
```sql
-- Buffer pool size
SHOW VARIABLES LIKE 'innodb_buffer_pool_size';
-- Defalt 128MB - production-da kicik!

-- my.cnf
[mysqld]
innodb_buffer_pool_size = 12G  # RAM-in 60-80%-i
innodb_buffer_pool_instances = 8  # parallel access

-- Hit ratio yoxla
SHOW STATUS LIKE 'Innodb_buffer_pool_read%';
-- hit_ratio = 1 - (reads / read_requests)
-- 99%+ olmalidir saglam sistemde
```

**PostgreSQL:**
```sql
-- shared_buffers (RAM-in 25%-i adi tovsiye)
SHOW shared_buffers;  -- default 128MB

-- postgresql.conf
shared_buffers = 8GB
effective_cache_size = 24GB  -- OS cache + shared (planner ucun)
```

**Eviction policy:** LRU (Least Recently Used) variantlari.
- InnoDB: LRU + young/old sublist (cold scan-leri qorumaq ucun)
- PostgreSQL: clock-sweep (LRU yaxinligi)

**Buffer pool-un isi:**
1. Query gelir, page lazim
2. Buffer pool-da var? -> hit, RAM-dan oxu
3. Yoxdur? -> miss, disk-den oxu, buffer pool-a yukle
4. Yer yoxdur? -> en kohne page-i evict et

---

## Page Split, Fillfactor, Fragmentation

**B-tree index-de page split:**

```
Page X (full): [10, 20, 30, 40, 50]
INSERT 25 -> page yer yoxdur

Page split:
Page X: [10, 20, 25]
Page Y: [30, 40, 50]
Parent index update.
```

**Page split ekibetleri:**
- I/O artir (2 page yenilenir)
- Sequential read fragment olur
- WAL log boyuyur

**Fillfactor** (page-i 100% yox, 80%-90% doldur):

```sql
-- PostgreSQL
CREATE TABLE orders (...) WITH (fillfactor = 80);
ALTER INDEX idx_orders_user SET (fillfactor = 70);

-- MySQL InnoDB - manual fillfactor yoxdur, amma:
-- innodb_fill_factor = 100 (avtomatik leaf doldurmaq)
```

| Fillfactor | Ne vaxt |
|------------|---------|
| 100% | Read-heavy, append-only data (logs) |
| 80-90% | OLTP normal (UPDATE-ler var) |
| 70% | UPDATE-heavy table (HOT update yer ucun) |

**Fragmentation tipleri:**
- **Internal**: page icinde free space cox
- **External**: page-ler disk-de seperated

```sql
-- PostgreSQL: bloat check
SELECT schemaname, tablename, 
       pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- VACUUM FULL (lock alir!) ya da pg_repack (online)
VACUUM FULL orders;

-- MySQL: OPTIMIZE TABLE (lock alir InnoDB-de de)
OPTIMIZE TABLE orders;
-- ya da pt-online-schema-change
```

---

## WAL (Write-Ahead Log)

**Qizil qayda:** Data file-a yazmadan **evvel**, change log-a yaz.

**Niye?** Crash recovery + durability.

```
Transaction: UPDATE accounts SET balance = 100 WHERE id = 1

1. WAL: "T1: page 42, offset 100, old=50, new=100"
2. WAL fsync (DISK-e gedir)
3. COMMIT - client OK alir
4. (Sonra, lazy) - data page-i diske yaz
```

**Bu niye saglamdir?**
- Power failure step 3-den sonra: WAL replay olur, page yenilenir
- Power failure step 2-den evvel: transaction rollback
- Power failure step 4-den evvel: WAL var, replay olur

**InnoDB-de:**
- **Redo log** (ib_logfile0, ib_logfile1) - WAL ekvivalenti
- **Undo log** - eski qiymet (rollback + MVCC ucun)

```sql
-- InnoDB redo log size
innodb_log_file_size = 1G       -- her file
innodb_log_files_in_group = 2   -- toplam 2GB
-- Boyuk = az checkpoint, amma uzun recovery
```

**PostgreSQL WAL:**

```sql
-- postgresql.conf
wal_level = replica       -- minimal | replica | logical
wal_segment_size = 16MB   -- her WAL file 16MB
max_wal_size = 4GB        -- checkpoint trigger
min_wal_size = 1GB
```

**wal_level acislari:**

| Level | Use case | Disk |
|-------|----------|------|
| minimal | Yalniz crash recovery, replication YOX | En az |
| replica | Streaming replication, PITR | Orta |
| logical | Logical decoding (CDC, Debezium) | En cox |

---

## Checkpoint Mexanizmi

Buffer pool-dakı dirty page-ler periodik olaraq diske yazilir = **checkpoint**.

```
Time -----[CP1]----[T1 commit]----[T2 commit]----[CP2]----[CRASH]
                                                 ^                ^
                                  Recovery: WAL[CP2 -> CRASH] replay
```

**Niye lazimdir?**
- Recovery vaxtini mehdudlasdirir (CP-den sonraki WAL-i replay)
- WAL file-larini azad edir (kohne WAL silinir CP sonra)

**Checkpoint trigger-leri:**
- Vaxt: PG `checkpoint_timeout = 5min`, MySQL `innodb_checkpoint_age`
- Size: WAL boyukluyu (`max_wal_size`)
- Manual: `CHECKPOINT;` (PG)

**Checkpoint problem:** I/O burst yaranir.

```sql
-- PostgreSQL: smooth checkpoint
checkpoint_completion_target = 0.9  -- CP I/O 90% interval-de yayilsin
```

---

## fsync Semantics

**fsync** = OS-e de "diske INDI yaz" emrini ver.

```
write(fd, data) -> OS page cache-e gedir (RAM)
fsync(fd) -> diske sıxılır
```

| Setting | Davranis | Risk |
|---------|----------|------|
| `fsync = on` (PG) / `innodb_flush_log_at_trx_commit = 1` | Her commit-de fsync | En guvenli, yavas |
| `synchronous_commit = off` (PG) | fsync background-da | 1-3s data itki commit-den sonra |
| `innodb_flush_log_at_trx_commit = 2` | OS cache-e yaz (fsync hər saniye) | OS crash-da itki, MySQL crash-da OK |
| `innodb_flush_log_at_trx_commit = 0` | Hec birinden hec biri | Cox suretli, cox riskli |

**Production tovsiye:** `=1` (financial data) ve ya `=2` (orta sistem).

---

## Group Commit

Per-transaction fsync cox baha (fsync 1-10 ms). Multiple transaction birlikde fsync edilir:

```
T1 commit -> WAL buffer-e yazilir, group-a qosulur
T2 commit -> WAL buffer-e yazilir
T3 commit -> WAL buffer-e yazilir
... 10ms gozle ya 100 transaction yiqilsin ...
1 fsync -> hamisi commit
```

**Throughput:** 100 TPS -> 10,000 TPS (group commit ile).

```sql
-- MySQL
binlog_group_commit_sync_delay = 100  -- microseconds
binlog_group_commit_sync_no_delay_count = 10
```

---

## Double-Write Buffer (InnoDB)

Page write atomic deyil (16KB page > 4KB OS page). Crash yarim-yazilmis page yarada biler ("torn page").

**Hell:** Double-write buffer.
1. Page-i evvelce **doublewrite buffer**-e yaz (sequential, kicik file)
2. fsync
3. Page-i original yere yaz
4. Crash recovery: doublewrite-dan recovery edir

```sql
SHOW VARIABLES LIKE 'innodb_doublewrite';  -- ON (default)
```

**Cost:** 2x write I/O - amma RAID/SSD-de qebul edilir.

---

## Torn Page Protection (PostgreSQL)

PG `full_page_writes = on` (default):

```
Checkpoint-den sonra ilk page deyisikliyi -> butun page WAL-a yazilir
Sonra: yalniz delta (kicik)
```

Crash-da: ilk page-i WAL-dan tam restore edir, sonra delta-lari tetbiq edir.

**Cost:** WAL boyukluyu artir checkpoint sonra.

---

## LSM-Tree vs B-tree

| Aspekt | B-tree (InnoDB, PG) | LSM-tree (RocksDB, Cassandra, ScyllaDB) |
|--------|---------------------|-----------------------------------------|
| Write | In-place, page split | Sequential append (memtable -> SSTable) |
| Read | 1-3 page read | Multiple SSTable check (Bloom filter ile sürət) |
| Compaction | Yox (vacuum/optimize) | Background compaction |
| Write amplification | Orta | Yuksek (compaction) |
| Range scan | Cox suretli | Yavas |
| Use case | OLTP, mixed | Write-heavy, time-series |

**LSM nece islir:**

```
WRITE: Memtable (RAM, sorted) -> dolanda flush SSTable (disk, immutable)
READ: Memtable -> SSTable_1 -> SSTable_2 -> ...
COMPACTION: SSTable-leri merge et, deleted/old version sil
```

**RocksDB istifade edenler:** MyRocks (MySQL fork), CockroachDB, TiDB, Kafka Streams.

---

## Sequential vs Random I/O

| I/O type | HDD | SSD | NVMe |
|----------|-----|-----|------|
| Sequential read | 100-200 MB/s | 500 MB/s | 3-7 GB/s |
| Random 4KB read | 0.5-1 MB/s (~100 IOPS) | 50-100 MB/s | 500-1000 MB/s |

**DB design implications:**
- Index scan = random I/O (slow on HDD)
- Full table scan = sequential I/O (fast)
- WAL = sequential append (very fast)
- Checkpoint = random write (slow)

```sql
-- PostgreSQL planner
random_page_cost = 1.1   -- SSD ucun
random_page_cost = 4     -- HDD ucun (default)
seq_page_cost = 1
```

---

## SSD vs HDD vs Cloud Storage

| Storage | IOPS | Latency | Use case |
|---------|------|---------|----------|
| HDD | 100 | 10ms | Archive, backup |
| Consumer SSD | 50,000 | 0.1ms | Dev, small DB |
| Enterprise NVMe | 500,000+ | 50us | Production OLTP |
| AWS EBS gp3 | 3,000-16,000 | 1-2ms | AWS RDS |
| AWS io2 | 256,000 | <1ms | High-perf RDS |
| AWS Local NVMe | 1M+ | 50us | i3/i4 instances |

**Cloud nuances:**
- EBS network-attached -> latency var
- Local NVMe ephemeral (instance ole biler -> data itir)
- Provisioned IOPS bahalidir, amma predictable

```bash
# Ext4 mount options DB ucun
mount -o noatime,nodiratime,data=writeback /dev/nvme0n1 /var/lib/mysql
```

---

## Read-Ahead

DB sequential read gorende OS-e "qabaqcadan oxu" deyir.

**MySQL InnoDB:**
- **Linear read-ahead** - extent (64 page) icinde N page sequentially read olunsa, qalan extent-i prefetch et
- **Random read-ahead** - extent-de cox page buffer pool-da var? prefetch et

```sql
innodb_read_ahead_threshold = 56  -- 64 page-den 56-i lazim oldu -> prefetch
innodb_random_read_ahead = OFF    -- default off
```

---

## Backup + WAL Archive (PITR)

**Point-in-Time Recovery** - ixtiyari ana qayitmaq.

**Strategy:**
1. **Base backup** (full, gunde 1 defe)
2. **WAL archive** (continuous, real-time)

```bash
# PostgreSQL
# postgresql.conf
archive_mode = on
archive_command = 'cp %p /backup/wal/%f'

# Base backup
pg_basebackup -D /backup/base -F tar -z

# Recovery to specific time
# recovery.conf (PG <12) ya da postgresql.auto.conf
restore_command = 'cp /backup/wal/%f %p'
recovery_target_time = '2026-04-24 14:30:00'
```

**MySQL:**
```bash
# Full backup
mysqldump --all-databases --single-transaction --master-data=2 > backup.sql
# ya
xtrabackup --backup --target-dir=/backup/

# Binary logs archive
log_bin = /var/log/mysql/mysql-bin
expire_logs_days = 7
```

**RPO** (Recovery Point Objective) - maksimum data itki - WAL ne qeder tez archive olunur ondan asili.

**RTO** (Recovery Time Objective) - berpasi ne qeder uzun cekir - WAL replay suretinden asili (1 GB/min ortalama).

---

## Real Production Tuning Checklist

```sql
-- MySQL InnoDB
[mysqld]
innodb_buffer_pool_size = 12G            -- RAM 60-70%
innodb_buffer_pool_instances = 8
innodb_log_file_size = 2G                -- transaction-lar boyukdurse
innodb_flush_log_at_trx_commit = 1       -- durability
innodb_flush_method = O_DIRECT           -- double caching qarsisi
innodb_io_capacity = 2000                -- SSD
innodb_io_capacity_max = 4000

-- PostgreSQL
shared_buffers = 8GB                     -- RAM 25%
effective_cache_size = 24GB              -- RAM 75%
wal_buffers = 16MB
checkpoint_timeout = 15min
max_wal_size = 8GB
random_page_cost = 1.1                   -- SSD
effective_io_concurrency = 200           -- SSD
work_mem = 32MB                          -- per query operation
maintenance_work_mem = 2GB               -- VACUUM, CREATE INDEX
```

---

## Laravel Implications

```php
// Long transaction WAL boyudur - qisa saxla
DB::transaction(function () {
    // 100 INSERT - OK
    foreach ($items as $item) {
        OrderItem::create([...]);
    }
}, 5);

// PIS: tek transaction-da 1M insert
// Yaxsi: chunk
$items->chunk(1000)->each(function ($chunk) {
    DB::transaction(fn() => OrderItem::insert($chunk->toArray()));
});

// Bulk insert -> WAL/redo log az gerginlesir
DB::table('events')->insert($manyRows); // 1 statement, 1 WAL entry
```

---

## Interview suallari

**Q: WAL niye lazimdir? Olmasaydi ne olardi?**
A: Crash recovery + durability ucun. Olmasaydi: commit edilen transaction crash zamani itireqeb (page disk-e yazilmamis). WAL "evvelce log-a yaz, sonra data" prinsipi - crash-da log replay edilir, commit edilen transaction-lar berpa olunur, edilmemisler rollback olunur.

**Q: innodb_flush_log_at_trx_commit = 1 vs = 2 ferqi?**
A: =1 (default): her commit-de fsync diske - tam durability, amma yavas. =2: WAL OS page cache-e yazilir (fsync seconda 1 defe), MySQL crash-da OK, amma OS/server crash-da 1 saniye itki. Financial -> =1, log/analytics -> =2 ola biler.

**Q: Buffer pool hit ratio neceyse "yaxsi"-dir?**
A: 99%+ saglam OLTP sistemde. 95-99% kifayetdir cox sistemler ucun. <95% problemdir - ya buffer pool kicikdir, ya query pattern pis (random scan), ya working set RAM-dan boyukdur. SHOW STATUS LIKE 'Innodb_buffer_pool_reads' (disk read sayi) izle.

**Q: PostgreSQL VACUUM nedir, ne vaxt FULL lazimdir?**
A: VACUUM dead tuple-leri (UPDATE/DELETE-den qalan) temizleyir, space reuse ucun. FULL = table-i tam yenidən yazir (shrink), exclusive lock alir - production-da olum. Adi VACUUM (autovacuum) kifayetdir, FULL yalniz boyuk bloat-da pg_repack alternativi yoxdursa.

**Q: LSM-tree write-heavy ucun niye yaxsidir?**
A: Sequential append - random I/O yoxdur write zamani. Memtable RAM-da, dolanda flush SSTable. Write amplification compaction-da olsa da, background-dur. Time-series, log, IoT - LSM (Cassandra, RocksDB). OLTP normal -> B-tree yaxsidir (range scan, predictable read latency).
