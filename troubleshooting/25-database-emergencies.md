# Database Emergencies (Senior)

## Problem (nəyə baxırsan)
Verilənlər bazası hər production stack-in ən qorxulu hissəsidir. Problem olduqda çox vaxt sadəcə "restart edib təkrar cəhd et" deyə bilmirsən — data risk altındadır. Bu playbook replikasiya lag, lock contention, uzun işləyən query-lər və replica promotion-u əhatə edir.

Alt-kateqoriya üzrə simptomlar:
- Replikasiya lag: köhnə read-lər, "İndicə save etdim, görünmür"
- Lock contention: write-lər asılı qalır, `Lock wait timeout exceeded`
- Uzun query-lər: müəyyən endpoint-lər yavaşdır, DB CPU yüksəkdir
- Replica uğursuzluğu: read-lər xəta verir, write-lər OK-dır

## Sürətli triage (ilk 5 dəqiqə)

### Əslində nə səhvdir?

```sql
-- MySQL: is the server healthy?
SHOW GLOBAL STATUS LIKE 'Uptime';
SHOW GLOBAL STATUS LIKE 'Threads_connected';
SHOW GLOBAL STATUS LIKE 'Threads_running';
SHOW ENGINE INNODB STATUS\G

-- Postgres
SELECT now() - pg_postmaster_start_time() AS uptime;
SELECT * FROM pg_stat_activity;
```

`Threads_running` yüksəkdirsə = query-lər aktiv işləyir (load). `Threads_connected` yüksək, running isə aşağıdırsa = connection leak. Bax: [db-connection-exhaustion.md](db-connection-exhaustion.md).

## Diaqnoz

### Replikasiya lag

MySQL replica:
```sql
SHOW REPLICA STATUS\G
-- look at:
-- Seconds_Behind_Source: 0 is good, > 30 is concerning
-- Replica_IO_Running / Replica_SQL_Running: both "Yes"
-- Last_IO_Error / Last_SQL_Error: should be empty
```

Postgres replica:
```sql
SELECT now() - pg_last_xact_replay_timestamp() AS replication_lag;
SELECT * FROM pg_stat_replication;   -- on primary
```

Lag səbəbləri:
- Primary-də uzun işləyən transaction replica apply-i bloklayır
- Böyük DDL (ALTER TABLE) yayılır
- Replica-da disk IO saturated olub
- Şəbəkə partition
- Replica zəif gücdədir (MySQL 8.0-dan əvvəlki versiyalarda tək SQL thread, əsasən serial)

### Lock contention

MySQL:
```sql
SHOW ENGINE INNODB STATUS\G
-- scroll to LATEST DETECTED DEADLOCK and TRANSACTIONS sections

-- InnoDB lock waits
SELECT * FROM performance_schema.data_lock_waits;

-- Who is blocking whom (MySQL 8)
SELECT
  r.trx_id waiting_trx_id, r.trx_mysql_thread_id waiting_thread,
  r.trx_query waiting_query,
  b.trx_id blocking_trx_id, b.trx_mysql_thread_id blocking_thread,
  b.trx_query blocking_query
FROM performance_schema.data_lock_waits w
JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_engine_transaction_id
JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_engine_transaction_id;
```

Postgres:
```sql
-- Blocking queries
SELECT pid, usename, pg_blocking_pids(pid) AS blocked_by, query
FROM pg_stat_activity
WHERE cardinality(pg_blocking_pids(pid)) > 0;

-- All locks
SELECT * FROM pg_locks WHERE NOT granted;
```

### Uzun transaction-lar

MySQL:
```sql
SELECT trx_id, trx_started, trx_mysql_thread_id, trx_query
FROM information_schema.innodb_trx
ORDER BY trx_started ASC;
```

Postgres:
```sql
SELECT pid, now() - xact_start AS duration, state, query
FROM pg_stat_activity
WHERE xact_start IS NOT NULL
ORDER BY xact_start ASC;
```

10 dəqiqədən köhnə = şübhəli. 1 saatdan köhnə = demək olar ki, qırılıb.

### Query-ləri kill etmək

MySQL:
```sql
KILL 12345;         -- kills connection and its query
KILL QUERY 12345;   -- kills just the query, keeps connection
```

Postgres:
```sql
SELECT pg_cancel_backend(12345);    -- polite, sends cancellation
SELECT pg_terminate_backend(12345); -- forceful, drops connection
```

Əvvəl `pg_cancel_backend` üstün tut, sonra terminate-ə keç.

## Fix (bleeding-i dayandır)

### Replikasiya lag

- Primary-də apply-i bloklayan uzun işləyən transaction-ı kill et
- Əgər replica catch up edə bilmirsə, müvəqqəti olaraq primary-dən oxumağı nəzərdən keçir (app səviyyəsində failover flag)
- Bərpadan sonra: replica-nı tune et (paralel replikasiya, daha böyük `innodb_buffer_pool_size`, daha sürətli disk)

### Lock contention

- Bloklayan transaction-ı kill et (stale/asılı qaldığını təsdiqlədikdən sonra)
- App-də lock scope-u azalt (qısa transaction-lar, dar sətir erişimi)
- UPDATE-də full-table lock-dan qaçmaq üçün index əlavə et (seq scan hər şeyi lock edə bilər)
- Transaction tutan anti-pattern-ləri yoxla (məsələn, `DB::transaction` daxilində xarici API çağırışı)

### Uzun query-lər

- Kill et
- Əgər `EXPLAIN` full scan göstərirsə, index əlavə et
- Query-ni daha ucuz şəkildə yenidən yaz
- Bahalı read query-ləri replica-ya köçür

### Replica promotion (fövqəladə failover)

Əgər primary ölürsə, replica-nı promote et:

MySQL:
```sql
-- On replica that's most caught up
STOP REPLICA;
RESET REPLICA ALL;
-- Redirect application to new primary (DNS or app config)
```

Postgres:
```bash
# On replica
pg_ctl promote -D /var/lib/postgresql/data
```

Və ya orchestration istifadə et (Orchestrator, Patroni, Stolon, RDS automatic failover).

**Risk: split-brain.** Əgər köhnə primary qayıdırsa və tətbiqlər ona yönəlibsə, iki primary = data divergence. Promote etməzdən əvvəl köhnə primary-ni fence et. Cloud RDS Multi-AZ ilə bu avtomatik həll olunur.

## Əsas səbəbin analizi

İncident-dən sonrakı suallar:
- Yavaş query / lock tutan nə idi?
- Yeni deploy, yeni feature, yoxsa yeni data pattern idi?
- İstifadəçilər görməmişdən qabaq aşkar etdikmi?
- Failover dizayn edildiyi kimi işlədimi (istifadə olundusa)?
- Data itirdikmi? Bəli olsa, necə bərpa olunur?

## Qarşısının alınması

- Slow query log həmişə aktiv olsun, həftəlik yoxla
- `Seconds_Behind_Source > 30` üçün alert
- Postgres-də `idle in transaction` > 1 dəqiqə üçün alert
- Qısa transaction-lar qayda kimi (< 1s üstün)
- DB transaction daxilində xarici HTTP çağırış yoxdur
- Kritik DB-lər üçün avtomatik failover (RDS Multi-AZ, Patroni)
- Chaos test: staging-də primary-ni kill et, failover vaxtını və data integrity-ni yoxla

## PHP/Laravel xüsusi qeydlər

### Laravel transaction pattern

```php
// Good — short, auto-commits/rollbacks
DB::transaction(function () use ($order) {
    $order->update(['status' => 'paid']);
    Invoice::create(['order_id' => $order->id, ...]);
}, 3); // retry on deadlock up to 3 times

// BAD — external call inside transaction, holding locks
DB::transaction(function () use ($order) {
    $order->update(['status' => 'processing']);
    Http::post('https://slow-api.example.com/...')->throw();  // NO
    $order->update(['status' => 'done']);
});
```

HTTP çağırışı transaction-dan kənara çıxar.

### Laravel read/write split

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['replica-1', 'replica-2'],
    ],
    'write' => [
        'host' => ['primary'],
    ],
    'sticky' => true, // after write, read from primary for that request
    // ... standard config
],
```

`sticky => true` eyni request-də "İndicə yazdım, indi geri oxuya bilmirəm" problemini həll edir.

### Deadlock retry

```php
DB::transaction(function () { ... }, 3);
```

İkinci arg = deadlock-da retry sayı. Eloquent avtomatik olaraq `1213 Deadlock found` xətasında təkrar cəhd edir.

## Yadda saxlanmalı real komandalar

```sql
-- MySQL
SHOW ENGINE INNODB STATUS\G
SHOW FULL PROCESSLIST;
SHOW REPLICA STATUS\G
SHOW BINARY LOGS;
KILL 12345;
SELECT * FROM information_schema.innodb_trx ORDER BY trx_started;

-- Postgres
SELECT * FROM pg_stat_activity;
SELECT * FROM pg_stat_replication;
SELECT * FROM pg_locks WHERE NOT granted;
SELECT pg_terminate_backend(12345);
SELECT now() - pg_last_xact_replay_timestamp() AS lag;
```

```bash
# MySQL sysadmin
mysqladmin -u root -p status
mysqladmin -u root -p processlist
mysqldump --single-transaction mydb > backup.sql

# Postgres sysadmin
pg_dump mydb > backup.sql
pg_isready -h primary
psql -c "SELECT pg_is_in_recovery();"   # true = replica
```

### AWS RDS emergency

```bash
# Check Multi-AZ failover readiness
aws rds describe-db-instances --db-instance-identifier prod-mysql \
  --query 'DBInstances[0].MultiAZ'

# Initiate manual failover (Multi-AZ only)
aws rds reboot-db-instance --db-instance-identifier prod-mysql --force-failover
```

## Müsahibə bucağı

"İdarə etdiyin bir database emergency haqqında danış."

Güclü cavab strukturu:
- "Səhnəni konkret detallarla qur: replikasiya lag, lock, runaway query, və ya failover."
- "Kəşf yolunu göstər: 'Mən `SHOW PROCESSLIST` işlətdim, 50 thread `waiting for lock` gördüm...'"
- "Dərhal mitigation izah et: bloklayan txn kill etdim, uzun query kill etdim, replica promote etdim."
- "Əsas səbəb: adətən itkin index, WHERE-siz runaway `UPDATE`, və ya xarici çağırış ətrafında transaction tutan kod."
- "Uzunmüddətli fix + qarşısının alınması."

Nümunə hekayə: "Peak zamanı primary-də `DELETE FROM logs WHERE created_at < ...` işlədi. LIMIT yox idi. Bütün cədvəli 15 dəqiqə lock etdi. Çanklı delete-ə (`DELETE ... LIMIT 1000` loop-da) yenidən yazaraq və az trafik pəncərəsində işlədərək düzəltdim. Qayda əlavə etdik: bütün toplu əməliyyatlar çanklı, > 1M sətirli statement üçün açıq təsdiq."
