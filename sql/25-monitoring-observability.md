# Database Monitoring & Observability

## Niye Monitoring Vacibdir?

Production-da database problemi olduqda onu **tutur ve hell edecek** sistemin olmalidir. "Database yavasdir" deyil, "bu query 3 saniye cekir, cunki full table scan edir" bilmelisiniz.

## Key Metrics (Izlenilmeli Gostericiler)

### 1. Query Performance Metrics

```sql
-- MySQL: Yavas query-leri tap
-- Slow query log aktiv et
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;  -- 1 saniyeden uzun
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow.log';

-- Hazirda isleyen query-ler
SELECT id, user, host, db, command, time, state, info
FROM information_schema.processlist
WHERE command != 'Sleep'
ORDER BY time DESC;

-- En cox vaxt alan query-ler (Performance Schema)
SELECT digest_text, count_star, avg_timer_wait/1000000000 AS avg_ms,
       sum_rows_examined, sum_rows_sent
FROM performance_schema.events_statements_summary_by_digest
ORDER BY avg_timer_wait DESC
LIMIT 10;
```

```sql
-- PostgreSQL: Yavas query-ler (pg_stat_statements extension)
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

SELECT query,
       calls,
       round(total_exec_time::numeric, 2) AS total_ms,
       round(mean_exec_time::numeric, 2) AS avg_ms,
       rows
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;
```

### 2. Connection Metrics

```sql
-- MySQL: Connection-lar
SHOW STATUS LIKE 'Threads_connected';     -- Hazirki
SHOW STATUS LIKE 'Max_used_connections';  -- Maximumdan ne qeder istifade olunub
SHOW VARIABLES LIKE 'max_connections';    -- Limit

-- Connection utilization faizi
SELECT
    (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Threads_connected')
    /
    (SELECT VARIABLE_VALUE FROM performance_schema.global_variables WHERE VARIABLE_NAME = 'max_connections')
    * 100 AS connection_utilization_pct;
```

```sql
-- PostgreSQL: Connection-lar
SELECT count(*) AS total,
       count(*) FILTER (WHERE state = 'active') AS active,
       count(*) FILTER (WHERE state = 'idle') AS idle,
       count(*) FILTER (WHERE state = 'idle in transaction') AS idle_in_txn
FROM pg_stat_activity;

-- Idle in transaction (TEHLIKELI! - lock-lari saxlayir)
SELECT pid, now() - xact_start AS duration, query
FROM pg_stat_activity
WHERE state = 'idle in transaction'
  AND xact_start < now() - interval '5 minutes';
```

### 3. Buffer / Cache Hit Ratio

```sql
-- MySQL: InnoDB Buffer Pool hit ratio
SHOW STATUS LIKE 'Innodb_buffer_pool_read_requests';  -- Cache-den oxuma
SHOW STATUS LIKE 'Innodb_buffer_pool_reads';           -- Disk-den oxuma

-- Hit ratio hesablama (99%+ olmalidir)
SELECT
    (1 - (
        (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads')
        /
        (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests')
    )) * 100 AS buffer_pool_hit_ratio;
```

```sql
-- PostgreSQL: Cache hit ratio
SELECT
    sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read)) * 100
    AS cache_hit_ratio
FROM pg_statio_user_tables;

-- Index hit ratio
SELECT
    sum(idx_blks_hit) / (sum(idx_blks_hit) + sum(idx_blks_read)) * 100
    AS index_hit_ratio
FROM pg_statio_user_indexes;
```

### 4. Replication Lag

```sql
-- MySQL: Replica lag
SHOW SLAVE STATUS\G
-- Seconds_Behind_Master field-ine bax

-- MySQL 8.0+
SELECT * FROM performance_schema.replication_applier_status;
```

```sql
-- PostgreSQL: Replication lag
SELECT client_addr,
       state,
       sent_lsn,
       write_lsn,
       replay_lsn,
       pg_wal_lsn_diff(sent_lsn, replay_lsn) AS replay_lag_bytes
FROM pg_stat_replication;
```

### 5. Lock Monitoring

```sql
-- MySQL: Lock-lari gor
SELECT * FROM performance_schema.data_locks;

-- Lock wait-leri
SELECT
    r.trx_id AS waiting_trx,
    r.trx_mysql_thread_id AS waiting_thread,
    b.trx_id AS blocking_trx,
    b.trx_mysql_thread_id AS blocking_thread,
    b.trx_query AS blocking_query
FROM information_schema.innodb_lock_waits w
JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_trx_id;
```

```sql
-- PostgreSQL: Lock-lar
SELECT blocked.pid AS blocked_pid,
       blocked.query AS blocked_query,
       blocking.pid AS blocking_pid,
       blocking.query AS blocking_query
FROM pg_catalog.pg_locks bl
JOIN pg_stat_activity blocked ON bl.pid = blocked.pid
JOIN pg_catalog.pg_locks kl ON bl.locktype = kl.locktype
    AND bl.relation = kl.relation AND bl.pid != kl.pid
JOIN pg_stat_activity blocking ON kl.pid = blocking.pid
WHERE NOT bl.granted;
```

### 6. Table & Index Statistics

```sql
-- MySQL: Table olculeri
SELECT table_name,
       table_rows,
       round(data_length / 1024 / 1024, 2) AS data_mb,
       round(index_length / 1024 / 1024, 2) AS index_mb,
       round((data_length + index_length) / 1024 / 1024, 2) AS total_mb
FROM information_schema.tables
WHERE table_schema = 'mydb'
ORDER BY total_mb DESC;

-- Istifade olunmayan index-ler
SELECT s.table_name, s.index_name, s.seq_in_index,
       t.table_rows
FROM information_schema.statistics s
JOIN information_schema.tables t USING (table_schema, table_name)
LEFT JOIN performance_schema.table_io_waits_summary_by_index_usage io
    ON io.object_schema = s.table_schema
    AND io.object_name = s.table_name
    AND io.index_name = s.index_name
WHERE s.table_schema = 'mydb'
  AND (io.count_star IS NULL OR io.count_star = 0)
  AND s.index_name != 'PRIMARY';
```

```sql
-- PostgreSQL: Table ve index istifadesi
SELECT schemaname, relname, seq_scan, idx_scan,
       n_live_tup, n_dead_tup,
       round(n_dead_tup::numeric / GREATEST(n_live_tup, 1) * 100, 2) AS dead_pct
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC;

-- Istifade olunmayan index-ler
SELECT indexrelid::regclass AS index_name,
       relid::regclass AS table_name,
       idx_scan,
       pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY pg_relation_size(indexrelid) DESC;
```

## Laravel ile Database Monitoring

```php
// Query logging middleware
class QueryLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        DB::enableQueryLog();

        $response = $next($request);

        $queries = DB::getQueryLog();
        $totalTime = collect($queries)->sum('time');
        $queryCount = count($queries);

        // Slow request log
        if ($totalTime > 1000) { // 1 saniyeden cox
            Log::warning('Slow database request', [
                'url' => $request->fullUrl(),
                'total_time_ms' => $totalTime,
                'query_count' => $queryCount,
                'slow_queries' => collect($queries)
                    ->filter(fn ($q) => $q['time'] > 100)
                    ->values()
                    ->toArray(),
            ]);
        }

        return $response;
    }
}
```

```php
// Health check endpoint
class DatabaseHealthCheck
{
    public function check(): array
    {
        $checks = [];

        // Connection check
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $checks['connection'] = [
                'status' => 'ok',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            $checks['connection'] = ['status' => 'fail', 'error' => $e->getMessage()];
        }

        // Replication lag (eger replica varsa)
        try {
            $lag = DB::connection('replica')
                ->select("SHOW SLAVE STATUS")[0]->Seconds_Behind_Master ?? null;
            $checks['replication_lag'] = [
                'status' => $lag < 5 ? 'ok' : 'warning',
                'seconds' => $lag,
            ];
        } catch (\Exception $e) {
            $checks['replication_lag'] = ['status' => 'skip'];
        }

        // Active connections
        $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value;
        $maxConn = DB::select("SHOW VARIABLES LIKE 'max_connections'")[0]->Value;
        $utilization = ($connections / $maxConn) * 100;

        $checks['connections'] = [
            'status' => $utilization < 80 ? 'ok' : 'warning',
            'current' => (int) $connections,
            'max' => (int) $maxConn,
            'utilization_pct' => round($utilization, 2),
        ];

        return $checks;
    }
}
```

## Alerting (Xeberdaretme)

### Alert olunmali hallar:

| Metric | Warning | Critical |
|--------|---------|----------|
| **Connection utilization** | > 70% | > 90% |
| **Cache hit ratio** | < 95% | < 90% |
| **Replication lag** | > 5 sec | > 30 sec |
| **Slow queries/min** | > 10 | > 50 |
| **Dead tuples %** | > 20% | > 50% |
| **Disk usage** | > 75% | > 90% |
| **Long running queries** | > 30 sec | > 5 min |
| **Lock waits** | > 5 sec | > 30 sec |

## Monitoring Alatlari

| Alat | Tip | Xususiyyetler |
|------|-----|---------------|
| **Prometheus + Grafana** | Open-source | MySQL/PG exporter, dashboard-lar |
| **Datadog** | SaaS | APM, query tracing, anomaly detection |
| **New Relic** | SaaS | Database monitoring, slow query analysis |
| **pganalyze** | PG-specific | Index advisor, VACUUM monitoring |
| **Percona PMM** | Open-source | MySQL/PG/MongoDB, query analytics |
| **Laravel Telescope** | Laravel | Development ucun query/request monitoring |
| **Laravel Debugbar** | Laravel | Development ucun N+1 detection |

## Interview Suallari

1. **Production database-de performance problemi olsa, ilk olaraq neye baxarsiniz?**
   - 1) Active queries (processlist), 2) Slow query log, 3) Lock waits, 4) Connection count, 5) Buffer pool hit ratio.

2. **Cache hit ratio asagi olsa ne edersiniz?**
   - Buffer pool / shared_buffers olcusunu artirirsiniz. Eger yetmirse, working set RAM-dan boyukdur - query optimization ve ya hardware upgrade lazimdir.

3. **Replication lag-in sebebleri neolabilir?**
   - Boyuk transaction-lar, CPU/IO bottleneck replica-da, network latency, suboptimal queries replica-da.

4. **idle in transaction niye tehlekelidir?**
   - Lock-lari saxlayir, diger query-leri bloklayir, VACUUM-un islemesine mane olur (PostgreSQL-de dead tuple yigilir).

5. **Hansi metrikleri mutleq izlemelisiniz?**
   - Query latency, connection utilization, cache hit ratio, replication lag, disk usage, lock waits.
