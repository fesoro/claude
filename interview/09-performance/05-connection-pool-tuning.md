# Connection Pooling Optimization (Senior ⭐⭐⭐)

## İcmal

Connection pooling — öncədən açılmış DB connection-larının pool-da saxlanması və tələb olunanda tətbiqə verilməsi mexanizmidir. Hər request üçün yeni TCP connection qurmaq (3-way handshake, TLS, auth) 5-50ms aparır. Pool bu dəyəri ortadan qaldırır. PHP-nin stateless, short-lived process modeli connection pooling-i xüsusilə önəmli edir.

## Niyə Vacibdir

PHP-FPM-in hər worker prosesi öz connection-ını açır. 200 worker × 3 DB connection = 600 aktiv connection. PostgreSQL max_connections default 100-dür. Yük altında "too many connections" xətası tamamilə real bir produksiya problemidir. Bunu həll etmək üçün pgBouncer kimi connection pooler-lər istifadə olunur. Bu arxitektura qərarını anlamaq senior developer üçün vacibdir.

## Əsas Anlayışlar

- **Connection lifecycle (pool olmadan):**
  ```
  TCP connect → TLS handshake → Auth → Query → Disconnect
  ← 5-50ms overhead →
  ```

- **Connection pooling növləri:**
  - **Persistent connections** (`PDO::ATTR_PERSISTENT`) — PHP prosesi ölənə qədər saxla
  - **External pooler** — pgBouncer, ProxySQL (ayrı proses)
  - **Built-in pooler** — RDS Proxy, PgBouncer Managed

- **pgBouncer pool mode-ları:**
  - **Session mode:** Bir client bir connection-a bağlıdır (tam uyğunluq)
  - **Transaction mode:** Yalnız transaction zamanı connection alır (tövsiyə)
  - **Statement mode:** Hər statement üçün (prepared statements işləmir)

- **ProxySQL (MySQL):**
  - Read/write ayrımı
  - Failover
  - Query rewrite
  - Connection pooling

- **Key metrics:**
  - **Pool size** — eyni anda saxlanan max connection sayı
  - **Pool utilization** — istifadə olunan / ümumi
  - **Wait queue** — connection gözləyən sorğu sayı
  - **Checkout timeout** — neçə ms gözləyib xəta versin
  - **Connection lifetime** — nə qədər köhnə connection yenilənsin

- **PHP-FPM + DB connection:**
  - Hər FPM worker = 1+ DB connection
  - FPM `pm.max_children` = max DB connection say
  - `pm.max_children × instances = total connections`

- **Connection starvation:**
  - Connection pool dolur, yeni sorğular gözləyir
  - Deadlock-a bənzər vəziyyət
  - Həll: pool size artır, slow query-ləri optimallaşdır

## Praktik Baxış

**pgBouncer quraşdırma:**

```ini
# /etc/pgbouncer/pgbouncer.ini

[databases]
myapp = host=postgres port=5432 dbname=myapp

[pgbouncer]
pool_mode = transaction
max_client_conn = 1000     # client-dən gələn max connection
default_pool_size = 25     # DB-yə real connection sayı
min_pool_size = 5          # minimum hazır connection
reserve_pool_size = 5      # emergency əlavə
reserve_pool_timeout = 3   # reserve-dən neçə saniyə sonra istifadə
max_db_connections = 100   # DB-yə max connection (global)
server_idle_timeout = 600  # boş connection neçə san. saxlanır
```

**Laravel DB connection konfiqurasiyası:**

```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),

    // Connection pool (Octane / long-running processes üçün vacib)
    'options' => [
        PDO::ATTR_PERSISTENT => false, // FPM-də persistent = problem
    ],

    // Reconnect on connection loss
    'sticky' => false, // read-your-writes üçün true
    'charset' => 'utf8',
    'schema' => 'public',
    'sslmode' => 'prefer',
],
```

**Laravel Octane ilə connection pool:**

```php
// Octane: uzun ömürlü proseslər — connection bağlanmır
// AppServiceProvider:
public function boot(): void
{
    // Octane worker başladıqda DB connection hazırla
    Octane::start(function () {
        DB::reconnect(); // fresh connection
    });

    // Hər request sonrası state-i sıfırla
    Octane::flush([
        // Redis, DB connections managed olunur
    ]);
}
```

**Connection timeout idarəetmə:**

```php
// Laravel: connection lost-da retry
// config/database.php
'mysql' => [
    ...
    'options' => [
        PDO::ATTR_TIMEOUT => 5,           // 5 saniyə timeout
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],
],

// Retry logic (Tenancy / microservice)
DB::whenQueryingForLongerThan(5000, function (Connection $connection) {
    // 5 saniyədən uzun sorğu var
    Log::warning('Long-running query', [
        'connection' => $connection->getName(),
    ]);
});
```

**RDS Proxy (AWS):**

```yaml
# Terraform: RDS Proxy
resource "aws_db_proxy" "main" {
  name                   = "myapp-proxy"
  debug_logging          = false
  engine_family          = "POSTGRESQL"
  idle_client_timeout    = 1800
  require_tls            = true
  role_arn               = aws_iam_role.rds_proxy.arn
  vpc_security_group_ids = [aws_security_group.rds_proxy.id]
  vpc_subnet_ids         = aws_subnet.private[*].id

  auth {
    auth_scheme = "SECRETS"
    secret_arn  = aws_secretsmanager_secret.db_password.arn
    iam_auth    = "DISABLED"
  }
}

# Connection limits:
# Lambda: 1000 concurrent invocations × 1 connection = 1000
# RDS Proxy: 1000 → 25 actual DB connections
```

**ProxySQL (MySQL Read/Write split):**

```sql
-- ProxySQL admin-ə bağlan
-- Read/Write ayrımı
INSERT INTO mysql_query_rules (rule_id, active, match_digest, destination_hostgroup)
VALUES
  (1, 1, '^SELECT', 2),    -- SELECT → replica (hostgroup 2)
  (2, 1, '.*', 1);          -- Qalanlar → primary (hostgroup 1)

LOAD MYSQL QUERY RULES TO RUNTIME;
SAVE MYSQL QUERY RULES TO DISK;
```

**Monitoring (pgBouncer):**

```bash
# pgBouncer admin konsolu
psql -h 127.0.0.1 -p 6432 -U pgbouncer pgbouncer

# Pool statistikası
SHOW POOLS;
# server_active, server_idle, cl_waiting → bu 3-ü izlə

# Client statistikası
SHOW CLIENTS;

# Real-time stats
SHOW STATS;
# total_requests, avg_query, avg_wait
```

**Trade-offs:**
- pgBouncer transaction mode → prepared statements işləmir (`:name` binding)
- Session mode → connection effektivliyi azdır
- RDS Proxy → latency +1-2ms (az da olsa)
- Persistent PDO connection → PHP crash-da connection leak riski
- Çox connection → DB CPU artır (context switching)

**Common mistakes:**
- PHP-FPM workers sayına uyğun pool size hesablamemaq
- pgBouncer transaction mode-da `SET` / `BEGIN` gözlənilməz davranışı
- Laravel Octane + persistent PDO = stale connection problemi
- Pool timeout-u çox uzun saxlamaq (cascade failure riski)
- Max connection artırmaq yerinə slow query-ləri düzəltməmək

## Nümunələr

### Real Ssenari: Production "too many connections"

```
Şikayət: Traffic artdıqda "FATAL: sorry, too many clients already" xətası.

Analiz:
- 5 server × 40 FPM worker × 3 connection = 600
- PostgreSQL max_connections = 100
- Xəta qaçılmazdır

Həll:
1. pgBouncer transaction mode aktivləşdirildi
2. max_client_conn = 600 (FPM worker sayı)
3. default_pool_size = 20 (DB-yə real connection)
4. DB max_connections = 30 (köhnə: 100, indi proxy ilə 30)

Nəticə:
- DB artıq 600 deyil, 20 connection idarə edir
- Latency p50: 2ms artdı (pgBouncer overhead)
- "Too many connections" xətası: sıfıra düşdü
```

### Kod Nümunəsi

```php
<?php

// Health check: connection pool status
class ConnectionPoolHealthCheck
{
    public function check(): array
    {
        try {
            $start = hrtime(true);
            DB::select('SELECT 1');
            $latency = (hrtime(true) - $start) / 1e6;

            $connections = DB::select('
                SELECT count(*) as total,
                       sum(case when state = \'active\' then 1 else 0 end) as active,
                       sum(case when state = \'idle\' then 1 else 0 end) as idle
                FROM pg_stat_activity
                WHERE datname = current_database()
            ');

            return [
                'status' => 'healthy',
                'latency_ms' => round($latency, 2),
                'connections' => (array) $connections[0],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}

// config/database.php — environment-based tuning
'pgsql' => [
    ...
    // pgBouncer ilə işlədikdə prepared statements söndür
    'options' => array_filter([
        PDO::ATTR_EMULATE_PREPARES => env('DB_USE_BOUNCER', false) ? true : null,
    ]),
],
```

## Praktik Tapşırıqlar

1. **pgBouncer qur:** Docker ilə pgBouncer + PostgreSQL başlat, transaction mode konfiqurasiyası yaz, Laravel-i pgBouncer-ə qoş.

2. **Connection count monitor:** `pg_stat_activity` sorğusu ilə aktiv connection-ları izləyən bir artisan command yaz.

3. **Load test:** k6 ilə 200 concurrent user simulyasiya et, pgBouncer olmadan vs pgBouncer ilə — max connection sayını müqayisə et.

4. **Octane connection:** Laravel Octane qur, worker restart olmadan connection lost ssenarisi simulyasiya et, reconnect idarəetməsini yoxla.

5. **FPM tuning:** php-fpm.conf-da `pm.max_children` değişdirərək DB connection sayına necə təsir etdiyini Grafana/PgAdmin ilə izlə.

## Əlaqəli Mövzular

- `02-query-optimization.md` — Slow query connection pool-u bloklamasın
- `11-apm-tools.md` — Connection pool metrikalarını APM-də izlə
- `09-async-batch-processing.md` — Batch jobs connection idarəetməsi
- `12-load-testing.md` — Pool limitlərini load test ilə müəyyənləşdir
- `03-caching-layers.md` — Redis connection pool (Predis vs phpredis)
