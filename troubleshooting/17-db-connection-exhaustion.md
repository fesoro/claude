# DB Connection Exhaustion

## Problem (nəyə baxırsan)
Verilənlər bazası yeni connection-ları qəbul etmir. Tətbiq xətaları:

- MySQL: `SQLSTATE[08004] [1040] Too many connections`
- Postgres: `FATAL: remaining connection slots are reserved` / `sorry, too many clients already`
- SQL Server: `The connection pool has been exhausted`

Simptomlar:
- Trafik artımından sonra qəfil 500 xətaları
- Queue worker-lər hamısı connection xətası ilə uğursuz olur
- `mysql> SHOW STATUS LIKE 'Threads_connected'` `max_connections` limitinə yaxındır
- Postgres: `pg_stat_activity` `max_connections` limitinə yaxındır

## Sürətli triage (ilk 5 dəqiqə)

### Sayı təsdiqlə

MySQL:
```sql
SHOW GLOBAL STATUS LIKE 'Threads_connected';
SHOW VARIABLES LIKE 'max_connections';
SHOW PROCESSLIST;
```

Postgres:
```sql
SELECT count(*) FROM pg_stat_activity;
SHOW max_connections;
SELECT * FROM pg_stat_activity WHERE state = 'idle in transaction';
```

### Connection-ları kim tutub?

MySQL:
```sql
SELECT user, host, db, state, COUNT(*) as cnt
FROM information_schema.processlist
GROUP BY user, host, db, state
ORDER BY cnt DESC;
```

Postgres:
```sql
SELECT application_name, state, count(*)
FROM pg_stat_activity
GROUP BY application_name, state
ORDER BY count DESC;
```

Bunlara bax:
- Sleep connection-lar (boş müştərilər slot tutur)
- "idle in transaction" (Postgres) — tətbiq transaction açıb və bağlamağı unudub
- Qeyri-bərabər mənbə (bir tətbiq connection-ların 80%-ni tutur)

### Sürətli bleed

Aydın şəkildə ilişib qalmış idle-in-transaction sessiyalarını kill et:

Postgres:
```sql
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE state = 'idle in transaction'
  AND state_change < now() - interval '5 minutes';
```

MySQL:
```sql
-- Build KILL statements for long sleepers
SELECT CONCAT('KILL ', id, ';')
FROM information_schema.processlist
WHERE command = 'Sleep' AND time > 600;
```

## Diaqnoz

### Riyaziyyat: max vs effektiv tutum

- `max_connections = 500` 500 paralel istifadəçi kimi səslənir
- Reallıq: hər PHP-FPM worker bir connection tuta bilər. 200 FPM worker × 1 DB = 200 slot baseline.
- Queue worker-ləri əlavə et: 50 Horizon worker × 1 = 50 slot.
- Admin cron, migration-lar, DBA console əlavə et = 10-20 slot.
- Burst: əgər `persistent connections` aktivdirsə, idle connection-lar açıq qalır.
- Serverless (Lambda, Vercel) çoxaldır: hər cold-start invocation yeni connection aça bilər.

### Cold-start amplifikasiyası

Pis pattern:
```
500 Lambda invocations → 500 new MySQL connections → max_connections exceeded → cascade failure
```

### Laravel persistent connections

`config/database.php`:
```php
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,   // CAUTION
    ],
],
```

Üstünlüklər: az connection churn.
Mənfi cəhətlər: əgər connection ölsə (server restart, replica swap), növbəti istifadə exception atır. Fork-lar/worker-lər persistent connection-ları təmiz izləyə bilmir. Connection-lar request-lər arasında pool-a qaytarılmır.

Ümumiyyətlə: **Laravel-də `PDO::ATTR_PERSISTENT` istifadə etmə** — əgər ölçülmüş faydası yoxdursa. Bunun əvəzinə real pooler istifadə et.

## Fix (bleeding-i dayandır)

### Variant 1: Idle / idle-in-transaction kill et

Yuxarıdakı kimi — dərhal rahatlıq, amma əsas səbəb düzəldilməsə təkrarlanacaq.

### Variant 2: max_connections-u qaldır (müvəqqəti)

MySQL (restart olmadan):
```sql
SET GLOBAL max_connections = 1000;
```

`SHOW VARIABLES LIKE 'max_connections';` ilə yoxla. Reboot-dan sağ çıxması üçün `my.cnf`-də saxla.

Postgres (restart tələb edir):
```
# postgresql.conf
max_connections = 1000
```

Qeyd: hər connection yaddaş tələb edir. Çox yüksəltmək DB-ni OOM edə bilər. Tipik limit: orta hardware-də Postgres ~300-500 per instance; MySQL ~500-1000.

### Variant 3: Connection pooler əlavə et (real fix)

Postgres üçün: **PgBouncer**
```ini
# pgbouncer.ini
[databases]
mydb = host=pg-primary port=5432 dbname=mydb

[pgbouncer]
listen_port = 6432
pool_mode = transaction     # transaction mode = best
max_client_conn = 2000
default_pool_size = 50      # per-user-per-db
```

Müştərilər PgBouncer-ə qoşulur, o isə kiçik real Postgres connection pool saxlayır. 2000 müştəri slotu, yalnız 50 real slot — böyük amplifikasiya faktoru.

MySQL üçün: **ProxySQL**
```sql
-- ProxySQL admin interface
INSERT INTO mysql_servers(hostgroup_id, hostname, port) VALUES(0, 'mysql-primary', 3306);
LOAD MYSQL SERVERS TO RUNTIME;
```

Pooling, read/write split, query routing, firewall qaydaları.

### Variant 4: AWS RDS Proxy

Managed xidmət. Tətbiqi → RDS Proxy → RDS yönləndir. Connection pooling, failover, IAM auth ilə məşğul olur.

Xüsusən AWS + Lambda üzərində işləyərkən istifadə et.

### Variant 5: Worker başına connection sayını azalt

- Request başına deyil, FPM worker başına bir connection
- Əgər hər worker connection tutursa və DB bottleneck-dirsə, `pm.max_children`-i azalt
- Əgər queue həddən artıq scale edilibsə, Horizon `maxProcesses`-i azalt

## Əsas səbəbin analizi

- Hansı müştəri connection-ları topladı?
- Yaxınlarda dəyişiklik oldumu: yeni microservice, yeni feature, yeni worker scale?
- Persistent connection istifadə edirik ki, söndürülməlidir?
- Leak varmı — kod connection açıb qaytarmır?
- "idle in transaction" görürükmü — app-tərəfi bug commit/rollback etmir?

## Qarşısının alınması

- DB qarşısında connection pooler (PgBouncer, ProxySQL, RDS Proxy)
- Alert: `threads_connected / max_connections > 80%`
- Alert: idle-in-transaction sayı > N
- Transaction-ları yoxla: hər BEGIN-in COMMIT və ya ROLLBACK olmalıdır
- Serverless arxitekturalar: həmişə pooler istifadə et
- Connection timeout tənzimləmələri (MySQL-də `wait_timeout`, Postgres-də `idle_in_transaction_session_timeout`)

## PHP/Laravel xüsusi qeydlər

### Laravel connection config

```php
// config/database.php
'mysql' => [
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT', 3306),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],
```

`PDO::ATTR_PERSISTENT` yox — səbəbini bilmirsənsə. Laravel lazy olaraq request başına bir connection açır, destruksiya zamanı bağlayır.

### Laravel + PgBouncer

PgBouncer `transaction` mode ciddidir: prepared statement-lər transaction-lar arasında keçmir. Laravel default-ları işləyir; amma əgər `DB::statement('PREPARE ...')` açıq şəkildə istifadə edirsənsə, `session` mode istifadə et və ya `transaction` + anonim statement-lərə sadiq qal.

### Xətada yenidən qoşulma

```php
DB::reconnect('mysql');
```

Uzun müddət işləyən worker-lərdə, connection düşəndə faydalıdır.

### `DB::transaction` leak-lərinə diqqət

```php
// Bad
DB::beginTransaction();
// exception thrown, nobody rolls back
// connection held with open transaction

// Good
DB::transaction(function () {
    // auto-commits or rolls back on exception
});
```

### Connection reset ilə Job base class

```php
abstract class BaseJob implements ShouldQueue
{
    public function handle(): void
    {
        try {
            $this->execute();
        } finally {
            DB::disconnect();   // force close on each job
        }
    }
}
```

Yalnız job-lardan connection leak görsən; əks halda keç.

## Yadda saxlanmalı real komandalar

```sql
-- MySQL
SHOW PROCESSLIST;
SHOW FULL PROCESSLIST;
SHOW GLOBAL STATUS LIKE 'Threads%';
SHOW VARIABLES LIKE '%timeout%';
SET GLOBAL max_connections = 1000;
KILL 12345;

-- Postgres
SELECT * FROM pg_stat_activity;
SELECT pg_terminate_backend(12345);
SHOW max_connections;
```

```bash
# PgBouncer stats
psql -h localhost -p 6432 pgbouncer -U pgbouncer -c "SHOW POOLS;"
psql -h localhost -p 6432 pgbouncer -U pgbouncer -c "SHOW STATS;"

# ProxySQL stats
mysql -u admin -padmin -h 127.0.0.1 -P 6032 \
  -e "SELECT * FROM stats_mysql_connection_pool;"

# AWS RDS current connections
aws cloudwatch get-metric-statistics \
  --namespace AWS/RDS --metric-name DatabaseConnections \
  --dimensions Name=DBInstanceIdentifier,Value=prod-mysql \
  --start-time $(date -u -d '1 hour ago' -Iseconds) \
  --end-time $(date -u -Iseconds) \
  --period 60 --statistics Maximum
```

## Müsahibə bucağı

"Prod DB-də too many connections. Necə həll edirsən?"

Güclü cavab:
- "Əvvəl: kim tutub. `SHOW PROCESSLIST` / `pg_stat_activity`, user/app/state üzrə qrupla."
- "Vaxt qazanmaq üçün uzun idle və idle-in-transaction-ları kill et."
- "Dərhal: headroom varsa max_connections qaldır. Risk: ifrat qaldırsan DB OOM olar."
- "Real fix: qarşısına pooler qoy. Postgres üçün PgBouncer, MySQL üçün ProxySQL və ya RDS Proxy."
- "Pool nisbəti: 2000 client-ə 50 DB connection adi haldır. Transaction-mode pooling."
- "Qarşısının alınması: 80% utilization-da alert, proaktiv idle-in-txn kill, transaction leak üçün kod audit."

Bonus: "Lambda funksiyalarımız RDS-ə birbaşa connection açırdı. Cold start-lar 400 connection-a qədər burst edirdi. RDS Proxy əlavə etdik — 'too many connections' crash-lərdən Lambda concurrency-dən asılı olmayaraq stabil 50 DB connection-a keçdik."
