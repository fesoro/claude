# Connection Pooling

## Problem

Her database connection yaratmaq bahadir:
1. TCP connection qurulur
2. Authentication olur (SSL handshake de ola biler)
3. Database session yaradilir
4. Memory ayrilir

Bu proses **20-100ms** ceke biler. Her HTTP request-de yeni connection = boyuk israf.

```
Request 1: Connect (50ms) -> Query (5ms) -> Disconnect
Request 2: Connect (50ms) -> Query (3ms) -> Disconnect
Request 3: Connect (50ms) -> Query (8ms) -> Disconnect
-- Connection overhead query vaxtindan 10x coxdur!
```

---

## Connection Pooling nedir?

Evvelceden yaradilmis connection-lar "pool"-da saxlanilir. Ehtiyac olduqda pool-dan goturursen, bitirdikde geri qaytarirsan.

```
                    [Connection Pool]
                   /    |    |    \
Request 1 -----> [C1]  [C2] [C3]  [C4]  <------ Evvelceden acilmis connection-lar
Request 2 -----> [C2]  (serbest olandan sonra)
Request 3 -----> [C3]
```

---

## PHP-de Connection Pooling

PHP traditional (request-per-process) model-de her request bitdikde butun memory temizlenir - connection-lar da. Buna gore PHP-de pooling **xarici tool** ile edilir.

### 1. PDO Persistent Connections

En sade usul. Connection request bitdikde baglanmir, novbeti request ucun saxlanilir.

```php
$pdo = new PDO(
    'mysql:host=localhost;dbname=myapp',
    'user',
    'password',
    [
        PDO::ATTR_PERSISTENT => true,  // Persistent connection
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);
```

**Laravel-de:**

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
],
```

**Xeberdarliqlar:**
- Connection state temizlenmir (temp tables, session variables qala biler)
- max_connections limitine catmaq asan ola biler (her php-fpm worker 1 persistent connection saxlayir)
- Transaction yarida qalsa, novbeti request-de problem yaradir

### 2. PgBouncer (PostgreSQL ucun)

En populyar xarici connection pooler.

```
[PHP App] ---> [PgBouncer (port 6432)] ---> [PostgreSQL (port 5432)]
```

**PgBouncer config (pgbouncer.ini):**

```ini
[databases]
myapp = host=127.0.0.1 port=5432 dbname=myapp

[pgbouncer]
listen_port = 6432
listen_addr = 0.0.0.0

# Pool mode
pool_mode = transaction    # En cox istifade olunan

# Pool size
default_pool_size = 20     # Database-e max 20 connection
max_client_conn = 200      # Client-lerden max 200 connection qebul et
min_pool_size = 5           # Minimum 5 connection aciq saxla

# Timeouts
server_idle_timeout = 600  # 10 deqiqe istifade olunmayan connection-i bagla
```

**Pool Mode-lar:**

| Mode | Izah | Performance |
|------|------|-------------|
| `session` | Connection session boyu saxlanilir | En yavas (persistent connection kimi) |
| `transaction` | Transaction bitdikde connection pool-a qaytarilir | **Tovsiye olunan** |
| `statement` | Her statement-den sonra qaytarilir | En suretli, amma multi-statement transaction islemir |

**Laravel-de PgBouncer-e qosulma:**

```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => 6432,  // PgBouncer portu
    'database' => 'myapp',
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
],
```

### 3. ProxySQL (MySQL ucun)

MySQL ucun en populyar proxy/pooler. Hemcinin query routing, caching, ve load balancing edir.

```
[PHP App] ---> [ProxySQL (port 6033)] ---> [MySQL Master (port 3306)]
                                      ---> [MySQL Replica1]
                                      ---> [MySQL Replica2]
```

**ProxySQL-de read/write splitting:**

```sql
-- ProxySQL admin interface (port 6032)
-- Write query-leri master-e (hostgroup 0)
INSERT INTO mysql_query_rules (rule_id, match_pattern, destination_hostgroup)
VALUES (1, '^SELECT.*FOR UPDATE', 0);

-- Read query-leri replica-lara (hostgroup 1)
INSERT INTO mysql_query_rules (rule_id, match_pattern, destination_hostgroup)
VALUES (2, '^SELECT', 1);

LOAD MYSQL QUERY RULES TO RUNTIME;
SAVE MYSQL QUERY RULES TO DISK;
```

### 4. Laravel Octane (Swoole/RoadRunner)

PHP long-running process kimi isleyir. Connection-lar request-ler arasinda saxlanilir.

```php
// Laravel Octane ile connection pooling avtomatik isleyir
// Cunki process request-ler arasinda olmur, connection aciq qalir

// Amma diqqet: connection timeout ve reconnect idare et
// config/octane.php
'listeners' => [
    RequestReceived::class => [
        // Connection-i yoxla, lazimsa reconnect et
    ],
],
```

---

## Connection Limits

### MySQL

```sql
SHOW VARIABLES LIKE 'max_connections';  -- Default: 151

-- Hazirki connection sayi
SHOW STATUS LIKE 'Threads_connected';

-- Peak connection
SHOW STATUS LIKE 'Max_used_connections';

-- Connection limit artirmaq
SET GLOBAL max_connections = 500;
```

### PostgreSQL

```sql
SHOW max_connections;  -- Default: 100

SELECT count(*) FROM pg_stat_activity;  -- Hazirki connection-lar
```

### Nece hesablamaq:

```
Lazim olan connection-lar:
= php-fpm worker sayi x her worker-in connection sayi
= 50 worker x 1 connection = 50

Eger PgBouncer varsa:
PHP -> PgBouncer: 200 client connection
PgBouncer -> PostgreSQL: 20 actual connection
200 PHP worker, amma yalniz 20 database connection!
```

---

## Best Practices

1. **Connection-lari tez bagla / qaytart**

```php
// YANLIS: Butun script boyu connection aciqdir
$pdo = new PDO(...);
$data = processData(); // 30 saniye surer, connection bos gozleyir
$pdo->query("INSERT ...");

// DOGRU: Yalniz lazim olduqda istifade et
$data = processData();
$pdo = new PDO(...);
$pdo->query("INSERT ...");
$pdo = null; // Bagla
```

2. **Connection leak-den qacin**

```php
// YANLIS: Exception olsa connection baglanmir
$pdo = new PDO(...);
$pdo->beginTransaction();
riskyOperation(); // Exception atarsa?
$pdo->commit();

// DOGRU: try/finally ile
$pdo = new PDO(...);
try {
    $pdo->beginTransaction();
    riskyOperation();
    $pdo->commit();
} catch (\Exception $e) {
    $pdo->rollBack();
    throw $e;
}
// Laravel DB::transaction() bunu avtomatik edir
```

3. **Monitor et**

```sql
-- MySQL: Kimler connected-dir?
SHOW PROCESSLIST;

-- MySQL: Uzun suren query-ler
SELECT * FROM information_schema.PROCESSLIST WHERE TIME > 30;

-- Kill uzun connection
KILL PROCESS_ID;
```

---

## Interview suallari

**Q: PHP-de connection pooling niye cetindir?**
A: PHP shared-nothing architecture istifade edir - her request ayri process-de isleyir, process bitdikde her sey (connection-lar da) temizlenir. Buna gore xarici pooler (PgBouncer, ProxySQL) lazimdir. Laravel Octane (Swoole) ile long-running process model-e kecmek de hel yoludur.

**Q: PgBouncer transaction mode-da ne islemir?**
A: Prepared statements (default-da), LISTEN/NOTIFY, session-level settings (`SET`), advisory locks. Cunki connection transaction-lar arasinda ferqli client-lere verilir, session state qorunmur.

**Q: max_connections-u niye coxu artirmaq olmaz?**
A: Her connection memory tutur (~5-10MB MySQL-de). 1000 connection = 5-10GB yalniz connection ucun. Hemcinin context switching artar. Daha yaxsi yol: connection pooler istifade et ve actual database connection sayini az saxla.
