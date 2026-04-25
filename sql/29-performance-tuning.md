# Performance Tuning (Middle)

## MySQL Performance Tuning

### InnoDB Buffer Pool

En muhum MySQL parametri. Data ve index-leri RAM-da cache-leyir.

```ini
# my.cnf
[mysqld]
# Total RAM-in 70-80%-i (dedicated server-de)
# 16GB RAM = 12GB buffer pool
innodb_buffer_pool_size = 12G

# Buffer pool instances (multi-core ucun)
innodb_buffer_pool_instances = 8  # buffer_pool_size / instance >= 1GB olmali

# Buffer pool-un dolulugunu yoxla
```

```sql
SHOW STATUS LIKE 'Innodb_buffer_pool_read%';

-- Hit ratio hesabla:
-- Hit ratio = 1 - (Innodb_buffer_pool_reads / Innodb_buffer_pool_read_requests)
-- 99%+ olmalidir
```

### Query Cache (MySQL 5.7, 8.0-da silindi)

```ini
# MySQL 5.7 (8.0-da yoxdur!)
query_cache_type = 1
query_cache_size = 128M
```

MySQL 8.0+ ucun: application-level cache istifade et (Redis/Memcached).

### Thread Pool / Connections

```ini
max_connections = 500          # Max connection sayi
thread_cache_size = 50         # Thread reuse ucun cache
wait_timeout = 300             # Idle connection timeout (saniye)
interactive_timeout = 300

# Connection sayini yoxla
```

```sql
SHOW STATUS LIKE 'Threads%';
SHOW STATUS LIKE 'Max_used_connections';  -- Peak connection sayi
```

### Slow Query Log

```ini
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1  # 1 saniyeden yavas query-ler
log_queries_not_using_indexes = 1
```

```bash
# Slow log analiz
mysqldumpslow -s t -t 10 /var/log/mysql/slow.log  # Top 10 yavas query
pt-query-digest /var/log/mysql/slow.log             # Percona tool (daha detalli)
```

### Diger muhum parametrler

```ini
# InnoDB Log
innodb_log_file_size = 1G          # Redo log size (boyuk = daha az flush)
innodb_log_buffer_size = 64M       # Log buffer
innodb_flush_log_at_trx_commit = 1 # 1=ACID safe, 2=1 san. data itkisi, 0=en suretli

# I/O
innodb_io_capacity = 2000          # SSD ucun yuksek
innodb_io_capacity_max = 4000
innodb_flush_method = O_DIRECT     # OS cache bypass (Linux)

# Temp table
tmp_table_size = 256M
max_heap_table_size = 256M

# Sort/Join buffer
sort_buffer_size = 4M       # Her connection ucun!
join_buffer_size = 4M       # Coxu artirma, connection * buffer = memory!
```

---

## PostgreSQL Performance Tuning

### shared_buffers

PostgreSQL-in buffer cache-i. InnoDB buffer pool-un ekvivalenti.

```ini
# postgresql.conf
shared_buffers = 4GB          # Total RAM-in 25% (OS cache-e de etibar edir)
effective_cache_size = 12GB   # Total cache (shared_buffers + OS cache)
```

### Work Memory

Sort, hash, join ucun istifade olunan memory. **Her operation** ucun ayri ayrilir!

```ini
work_mem = 64MB               # Diqqet: connection * sort_operations * work_mem
maintenance_work_mem = 1GB     # VACUUM, CREATE INDEX ucun
```

### WAL Settings

```ini
wal_buffers = 64MB
checkpoint_completion_target = 0.9
max_wal_size = 4GB
min_wal_size = 1GB
```

### VACUUM

PostgreSQL MVCC-de kohne row versiyalari (dead tuples) yigilir. VACUUM bunlari temizleyir.

```sql
-- Manual VACUUM
VACUUM VERBOSE orders;           -- Dead tuple-lari temizle
VACUUM FULL orders;              -- Table-i yeniden yaz (lock qoyur!)
VACUUM ANALYZE orders;           -- VACUUM + statistika yenile

-- Autovacuum settings
```

```ini
autovacuum = on
autovacuum_vacuum_threshold = 50       # Min dead tuples for vacuum
autovacuum_vacuum_scale_factor = 0.1   # Table-in 10%-i dead olsa vacuum et
autovacuum_analyze_threshold = 50
autovacuum_analyze_scale_factor = 0.05
```

```sql
-- Dead tuple sayini yoxla
SELECT relname, n_dead_tup, n_live_tup, 
       round(n_dead_tup::numeric / NULLIF(n_live_tup, 0) * 100, 2) AS dead_pct
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC;
```

---

## Query-Level Optimization

### 1. Table Statistics

```sql
-- MySQL: Statistikani yenile
ANALYZE TABLE orders;

-- PostgreSQL:
ANALYZE orders;
```

Optimizer statistikaya esasen en yaxsi plani secir. Kohne statistika = pis plan.

### 2. Force Index (son care!)

```sql
-- MySQL: Optimizer yanlis index secirse
SELECT * FROM orders FORCE INDEX (idx_status_date) WHERE status = 'pending';

-- PostgreSQL: Planner hint-leri yoxdur, amma:
SET enable_seqscan = off;  -- Sequential scan-i disable et (yalniz debug ucun!)
```

### 3. Partitioning ile Performance

```sql
-- Boyuk table-lari partition et (bax: 08-sharding-and-partitioning.md)
-- 100M row-lu table-da query yerine, 10M row-lu partition-da query
```

---

## Monitoring

### MySQL

```sql
-- Umumi status
SHOW GLOBAL STATUS;

-- InnoDB engine status
SHOW ENGINE INNODB STATUS\G

-- Hazirki query-ler
SHOW PROCESSLIST;
-- Uzun suren query-leri kill et
KILL PROCESS_ID;

-- Table boyutlari
SELECT 
    table_name,
    ROUND(data_length / 1024 / 1024, 2) AS data_mb,
    ROUND(index_length / 1024 / 1024, 2) AS index_mb,
    table_rows
FROM information_schema.tables 
WHERE table_schema = 'myapp'
ORDER BY data_length DESC;

-- Istifade olunmayan index-ler
SELECT * FROM sys.schema_unused_indexes WHERE object_schema = 'myapp';

-- Redundant index-ler
SELECT * FROM sys.schema_redundant_indexes WHERE table_schema = 'myapp';
```

### PostgreSQL

```sql
-- Hazirki query-ler
SELECT pid, now() - pg_stat_activity.query_start AS duration, query, state
FROM pg_stat_activity
WHERE state != 'idle'
ORDER BY duration DESC;

-- Table I/O stats
SELECT relname, seq_scan, idx_scan, 
       ROUND(100.0 * idx_scan / NULLIF(seq_scan + idx_scan, 0), 2) AS idx_ratio
FROM pg_stat_user_tables
ORDER BY seq_scan DESC;

-- Index istifade olunma statistikasi
SELECT indexrelname, idx_scan, idx_tup_read
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
ORDER BY idx_scan;

-- Cache hit ratio
SELECT 
    sum(heap_blks_read) AS disk_reads,
    sum(heap_blks_hit) AS cache_hits,
    ROUND(sum(heap_blks_hit)::numeric / NULLIF(sum(heap_blks_hit) + sum(heap_blks_read), 0) * 100, 2) AS ratio
FROM pg_statio_user_tables;
-- 99%+ olmalidir
```

---

## PHP/Laravel Performance Tips

```php
// 1. Lazy collection (memory-efficient)
User::cursor()->each(function ($user) {
    // Her seferinde 1 row memory-de
});

// 2. Chunk (batch processing)
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        // 1000-1000 isle
    }
});

// 3. Select yalniz lazimi sutunlari
User::select('id', 'name', 'email')->get();

// 4. toBase() - Eloquent hydration skip (suretli)
$users = User::where('active', true)->toBase()->get();
// stdClass qaytarir (Model instance deyil), daha suretli

// 5. Bulk operations
// YAVAS: 1000 ayri INSERT
// SURETLI:
User::insert($arrayOf1000Users);

// 6. Database indexing (migration-da)
$table->index(['status', 'created_at']);
```

---

## Interview suallari

**Q: MySQL/PostgreSQL yavas isleyir. Ilk ne edersin?**
A: 1) Slow query log aktiv et, en yavas query-leri tap. 2) EXPLAIN ile bu query-leri analiz et. 3) Index elave et ve ya query-ni optimize et. 4) Buffer pool/shared_buffers size-ini yoxla. 5) Connection sayini ve server resource istifadesini (CPU, RAM, I/O) yoxla.

**Q: innodb_flush_log_at_trx_commit deyerlerini izah et.**
A: `1`: Her COMMIT-de disk-e flush (ACID-compliant, en etibarlı, en yavas). `2`: Her saniye flush (crash-de max 1 san. data itkisi, suretli). `0`: OS-un ozu flush edir (en suretli, amma en riskli). Production-da `1` istifade et, eger performance kritikdirsa ve kicik data itkisi qebul olunandirsa `2`.

**Q: PostgreSQL-de VACUUM niye lazimdir?**
A: MVCC sebebinden UPDATE/DELETE kohne row versiyalarini silmir, yeni versiya yaradir. Kohne versiyalar (dead tuples) yigilir, table boyuyur, query yavasir. VACUUM bu dead tuple-lari temizleyir ve yeri yeniden istifade ucun isareler. VACUUM FULL ise table-i fiziki olaraq yeniden yazir (amma table lock qoyur).
