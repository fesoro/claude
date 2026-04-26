# Database Connection Pooling (Senior)

## Mündəricat
1. [Niyə Connection Pooling Lazımdır?](#niyə-connection-pooling-lazımdır)
2. [PgBouncer (PostgreSQL)](#pgbouncer-postgresql)
3. [ProxySQL (MySQL)](#proxysql-mysql)
4. [Pool Sizing Formulası](#pool-sizing-formulası)
5. [PHP PDO Persistent Connections](#php-pdo-persistent-connections)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə Connection Pooling Lazımdır?

```
DB connection xərci yüksəkdir:
  1. TCP handshake
  2. TLS handshake (şifrələnmiş əlaqədə)
  3. Authentication (user/password yoxlama)
  4. Session initialization (search_path, encoding, ...)
  → ~20-100ms hər connection üçün!

Problem — PHP-FPM ilə:
  100 worker × 1 connection = 100 DB connection
  (Bütün request-lər boş olsa belə, connection açıqdır)

  Hər yeni PHP-FPM restart:
  → Hər worker yeni connection açır
  → DB connection spike!

PostgreSQL max_connections:
  max_connections = 100 (default)
  Hər connection ~5-10 MB RAM
  1000 connection = 5-10 GB sadəcə connection overhead!

  PHP-FPM max_children = 50
  × 10 server
  = 500 connection → max_connections aşır!

Connection pooler həlli:
  PHP-FPM → PgBouncer → PostgreSQL
  500 PHP connection → PgBouncer → 20 DB connection
  PgBouncer paylaşır!
```

---

## PgBouncer (PostgreSQL)

```
PgBouncer — PostgreSQL üçün lightweight connection pooler.

3 pooling mode:

1. Session Pooling:
   Client bağlandıqda DB connection alır.
   Client bağlantı kəsəndə DB connection serbest buraxılır.
   
   ┌────────┐  session  ┌──────────┐  session  ┌────────┐
   │ PHP    │──────────►│PgBouncer │──────────►│  DB    │
   │ Client │           │          │           └────────┘
   └────────┘           └──────────┘
   
   PHP connection açıq = DB connection ayrılmış
   Faydası az — PHP idle olduqda da DB connection tutur.

2. Transaction Pooling:
   Yalnız aktiv transaction zamanı DB connection ayrılır.
   Transaction commit/rollback → connection pool-a qayıdır.
   
   50 PHP client → Aktiv transaction: 5 → 5 DB connection!
   Ən effektiv mode.
   
   Məhdudiyyət: SET, PREPARE, session-level features işləmir!
   Prepared statements bağımsız saxlanmalıdır.

3. Statement Pooling:
   Hər SQL statement-dən sonra connection serbest buraxılır.
   Multi-statement transaction dəstəklenmir.
   Nadir istifadə.

PgBouncer konfigurasiyası:
  [databases]
  mydb = host=pg-primary dbname=mydb

  [pgbouncer]
  pool_mode = transaction
  max_client_conn = 1000     ; PHP connection-lar
  default_pool_size = 25     ; DB connection-lar
  min_pool_size = 5
  reserve_pool_size = 5
  server_idle_timeout = 600
```

---

## ProxySQL (MySQL)

```
ProxySQL — MySQL üçün advanced proxy.
PgBouncer-dən daha çox xüsusiyyət:

Əsas imkanlar:
  Connection multiplexing (PgBouncer kimi)
  Read/Write splitting
    → Write → Primary
    → Read  → Replicas (round-robin)
  Query routing (şərh ilə)
  Query caching
  Query firewall (SQL injection)
  Connection failover

Read/Write splitting:
  SELECT → Replica
  INSERT/UPDATE/DELETE → Primary

  Annotasiya ilə:
    /* read */ SELECT * FROM orders;  → Replica
    SELECT * FROM orders;             → Primary (default)
    
  Regex ilə:
    ^SELECT.*→ replica
    ^(INSERT|UPDATE|DELETE).*→ primary

MySQL 8.0+ Connection Pool:
  MySQL-in öz connection pool-u var (thread pool plugin).
  ProxySQL daha güclü imkanlar təqdim edir.
```

---

## Pool Sizing Formulası

```
Optimal pool size — "Little's Law" əsasında:

Pool size = Thread sayı × (1 + I/O wait ratio)

Praktik yanaşma:
  pool_size = CPU core sayı * 2 + disk sayı
  
  4 core server: 4 * 2 + 1 = 9 ≈ 10

HikariCP tövsiyəsi (Java, amma prinsip universal):
  pool_size = (core_count * 2) + effective_spindle_count

Nümunə hesab:
  Sisteminiz: 8 core, SSD (1 spindle)
  Tövsiyə: 8 * 2 + 1 = 17 ≈ 20 connection

Niyə "az connection daha yaxşı" ola bilər:
  Çox connection → context switch overhead
  DB özü paralel sorğu üçün thread istifadə edir
  100 connection 20 connection-dan daha sürətli deyil!
  
  Testlər: 10-20 connection çox hallarda optimal
  Daha çox → throughput azalır (context switch)

PHP-FPM + PgBouncer:
  max_client_conn = PHP max_children * server sayı * 1.2 (buffer)
  default_pool_size = optimal_db_connections (10-30)
```

---

## PHP PDO Persistent Connections

```
PDO persistent connection:
  $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_PERSISTENT => true
  ]);
  
  Script bitəndə connection bağlanmır.
  Növbəti script eyni connection-ı istifadə edir.

PROBLEM — PHP-FPM ilə:
  Hər worker öz persistent connection-ını saxlayır.
  Worker restart olunana kimi qalır.
  
  pm.max_requests = 100 → 100 request-dən sonra worker restart
  → Persistent connection da bağlanır.

PDO persistent → external pooler (PgBouncer) problemi:
  Transaction pooling mode-da persistent connection xətalara yol açır!
  Köhnə session state qalır (SET, PREPARE, BEGIN sonrası...)

Tövsiyə:
  ❌ PDO::ATTR_PERSISTENT = true (PHP-FPM ilə)
  ✅ PgBouncer/ProxySQL external pooler
     PHP-FPM hər request-də yeni connection açır
     PgBouncer həqiqi DB connection-ı paylaşır
```

---

## PHP İmplementasiyası

```php
<?php
// PgBouncer ilə PDO konfiqurasiyası
// PgBouncer transaction mode-da prepared statement problemini həll etmək:

class DatabaseConnection
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO(
            'pgsql:host=pgbouncer;port=5432;dbname=mydb',
            config('db.user'),
            config('db.password'),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // PgBouncer transaction mode üçün persistent = false!
                PDO::ATTR_PERSISTENT         => false,
                // Prepared statement emulation (server-side olmadan)
                PDO::ATTR_EMULATE_PREPARES   => true,
            ]
        );
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
```

```php
<?php
// Connection health check + reconnect logic
class ResilientDatabaseConnection
{
    private ?PDO $pdo = null;
    private int $reconnectAttempts = 0;
    private const MAX_RECONNECT = 3;

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    public function execute(string $sql, array $params = []): \PDOStatement
    {
        try {
            return $this->tryExecute($sql, $params);
        } catch (\PDOException $e) {
            // Connection kəsildi? Reconnect cəhd et
            if ($this->isConnectionError($e)) {
                $this->pdo = null;
                $this->connect();
                return $this->tryExecute($sql, $params);
            }
            throw $e;
        }
    }

    private function connect(): void
    {
        $this->pdo = new PDO(
            dsn: 'pgsql:host=pgbouncer;port=5432;dbname=mydb',
            username: config('db.user'),
            password: config('db.password'),
        );
    }

    private function isConnectionError(\PDOException $e): bool
    {
        // PostgreSQL connection error codes
        return in_array($e->getCode(), ['08000', '08006', '57P01']);
    }
}
```

```php
<?php
// Connection pool monitoring
class ConnectionPoolMonitor
{
    public function getPgBouncerStats(): array
    {
        // PgBouncer stats sorğusu (pgbouncer virtual DB-yə qoş)
        $stats = $this->pgbouncerPdo->query(
            'SHOW POOLS;'
        )->fetchAll();

        $warnings = [];
        foreach ($stats as $pool) {
            $waitingClients = $pool['cl_waiting'];
            if ($waitingClients > 0) {
                $warnings[] = "Pool {$pool['database']}: {$waitingClients} clients waiting!";
            }

            $useRatio = $pool['sv_active'] / max($pool['sv_idle'] + $pool['sv_active'], 1);
            if ($useRatio > 0.9) {
                $warnings[] = "Pool {$pool['database']}: {$useRatio}% utilized — pool_size artır!";
            }
        }

        return ['stats' => $stats, 'warnings' => $warnings];
    }
}
```

---

## İntervyu Sualları

- PgBouncer-in 3 pooling mode-unu izah edin. Transaction mode-un məhdudiyyəti nədir?
- PHP-FPM + PDO persistent connection niyə tövsiyə edilmir?
- Pool size formulası niyə CPU core sayı × 2 + disk-dir?
- ProxySQL-in PgBouncer-dən əlavə imkanları hansılardır?
- Connection pool dolduqda nə baş verir? Müştəri nə görür?
- `cl_waiting > 0` PgBouncer-də nə deməkdir? Nə etmək lazımdır?
- K8s-dəki 50 pod hər biri 10 DB connection açırsa — necə idarə edərdiniz?
