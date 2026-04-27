# Connection Pooling (Middle ⭐⭐)

## İcmal
Database connection açmaq baha başa gəlir: TCP handshake, authentication, session initialization — bunlar hər sorğu üçün tekrarlanırsa gecikme toplanır. Connection pool — öncədən açılmış connection-ları saxlayıb yenidən istifadə edir. Bu mövzu interview-da həm "necə işləyir", həm "tuning" bucağından soruşulur.

## Niyə Vacibdir
Yanlış pool konfiqurasiyası ya connection exhaustion-a (çox az), ya da database overload-a (çox çox) səbəb olur. İnterviewer bu sualla sizin production-da database scaling problemlərini yaşayıb-yaşamadığınızı, pool size hesablamasını bildiyinizi, PgBouncer kimi external pool-erları tanıyıb-tanımadığınızı yoxlayır.

## Əsas Anlayışlar

- **Connection Overhead**: Yeni PostgreSQL connection yaratmaq ≈ 1-10ms + 5-10MB memory. 100 connection = 500MB-1GB. Hər HTTP request-i üçün yeni connection açmaq ölümcüldür.
- **Connection Pool**: Min/max connection-lardan ibarət pool — request gəldikdə hazır connection verilir, iş bitdikdə pool-a qaytarılır.
- **Pool Size Formula**: `N = Tc / Tq` — N: pool size, Tc: connection count, Tq: query time. Ümumi qayda: `CPU core count × 2 + 1` — PostgreSQL arxitekturasına görə. Amma yalnız başlanğıc nöqtəsidir, benchmark lazımdır.
- **Min Pool Size (minimum idle)**: Her zaman açıq saxlanan minimum connection sayı. İlk request-dən sürətli cavab üçün.
- **Max Pool Size**: Database-in dayana biləcəyi max connection sayı. PostgreSQL default `max_connections = 100`. Artırsa memory tükənir.
- **Connection Timeout**: Pool-da boş connection yoxdursa nə qədər gözlənilir — timeout aşıldıqda exception.
- **Idle Timeout**: Uzun müddət istifadə edilməyən connection bağlanır — server-side firewall idle connection-ı kəsdikdə connection leak əngəllənir.
- **Max Lifetime**: Connection-ın maksimum ömrü — server-side timeout, network change ilə uyğunlaşma üçün.
- **Connection Leak**: Açılan connection-ın bağlanmaması — exception zamanı `finally` blokunun çalışmaması. Pool tükənir, sistem donur.
- **PgBouncer**: PostgreSQL üçün dedicated external connection pooler. Transaction mode-da ən effektiv. PHP-FPM + PgBouncer klassik arxitekturadır.
- **Transaction Mode**: Hər transaction bitdikdə connection pool-a qaytarılır. 1000 PHP worker → 20 database connection. Prepared statement-lar işləmir (session state yoxdur).
- **Session Mode**: Bir session boyunca eyni connection — application transparentdir, prepared statement işləyir, lakin az effektiv. Transaction mode-un üstünlüklərini azaldır.
- **Statement Mode**: Hər statement bitdikdə connection qaytarılır. Ən effektiv, lakin transaction semantic-i dəstəkləmir — real application üçün uyğun deyil.
- **HikariCP (Java)**: En performanslı Java connection pool — light-weight, çox az overhead.
- **PHP + PgBouncer fərqi**: PHP-FPM-nin built-in connection pool-u yoxdur. Hər worker-i ayrıca connection açır. PgBouncer çox worker-i az database connection ilə daşıyır.
- **Read Replica Pool**: Read sorğuları replica pool-a yönləndirilir — primary pool-un yükü azalır.
- **Connection multiplexing**: PgBouncer-in fundamental mexanizmi — N client connection-ını M database connection üzərindən daşımaq (N >> M).
- **Prepared Statements + PgBouncer**: Transaction mode-da problem — server session state yoxdur. `PDO::ATTR_EMULATE_PREPARES = true` PHP-də bu problemi həll edir.

## Praktik Baxış

**Interview-da yanaşma:**
- PHP-FPM worker modeli ilə connection pool fərqini izah edin — PHP-nin connection pool mexanizmi Java ilə fərqlənir
- PgBouncer-in niyə lazım olduğunu izah edin: "100 PHP worker → 100 DB connection VS PgBouncer → 20 DB connection"
- Pool size hesablamasını göstərin

**Follow-up suallar interviewerlər soruşur:**
- "Laravel-də built-in connection pool varmı?" — PHP-FPM worker-based model, PgBouncer external
- "PgBouncer transaction mode-da prepared statement niyə işləmir?"
- "Connection exhaustion baş verdikdə nə edərdiniz?"
- "Pool size neçə olmalıdır? Formula nədir?"
- "Connection leak nədir, necə tapırsınız?"
- "Session mode vs transaction mode — nə zaman hansını seçirsiniz?"

**Ümumi candidate səhvləri:**
- "Max pool size artırmaq həmişə kömək edir" demək — database-in memory limiti var
- PHP-FPM worker modeli ilə Java thread pool-u qarışdırmaq
- Connection leak-i qeyd etməmək
- PgBouncer-in prepared statement məhdudiyyətini bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- PHP-FPM + PgBouncer arxitekturasını izah etmək: worker count, PgBouncer pool size, DB max_connections münasibəti
- Pool size-ı hesablamaq: `worker_count × avg_conn_per_worker ≤ db_max_connections`
- `pg_stat_activity` ilə connection monitoring nümunəsi

## Nümunələr

### Tipik Interview Sualı
"Aplikasiyanız trafik artdıqda 'too many connections' xətası alır. Nə edərdiniz?"

### Güclü Cavab
"Bu klassik connection exhaustion problemidir. Sistematik yanaşım:

Birinci — mövcud vəziyyəti anlayıram: `SELECT state, COUNT(*) FROM pg_stat_activity WHERE datname = 'mydb' GROUP BY state;` — neçə active, neçə idle connection var? `SHOW max_connections;` — limiti nədir?

İkinci — kökü tapıram:
- Connection leak? Idle transaction-lar çoxdursa — leak şüphəlidir
- Application layer: PHP-FPM worker count × connection per worker = total DB connection
- N+1 problem bağlantı sayını artırır

Üçüncü — həll addımları:
1. PgBouncer əlavə edərdim — transaction mode-da 200 PHP worker-i 20 DB connection ilə daşıyır. Bu ən effektiv həlldir.
2. PHP-FPM worker count-u gözden keçirirdim — `pm.max_children` çox yüksəkdirsə azaldardım
3. PostgreSQL-in `max_connections`-ını artırmaq olar, lakin hər connection ~5MB memory istifadə edir — server memory hesablanmalıdır
4. Read replica əlavə edib read sorğuları yönləndirərdim — primary pool-un yükü azalır
5. Connection leak varsa — `idle in transaction` state-dəki session-ları tapıb kod review edərdim"

### Kod Nümunəsi — PgBouncer Konfiqurasiyası

```ini
; /etc/pgbouncer/pgbouncer.ini

[databases]
; Application database-i PgBouncer üzərindən
mydb = host=127.0.0.1 port=5432 dbname=mydb

; Read replica
mydb_read = host=replica.db.internal port=5432 dbname=mydb

[pgbouncer]
listen_port = 6432
listen_addr = *

auth_type = md5
auth_file  = /etc/pgbouncer/userlist.txt

; Transaction mode — ən effektiv
; Hər transaction bitdikdə connection database-ə qaytarılır
pool_mode = transaction

; 1000 PHP worker PgBouncer-ə bağlana bilər
max_client_conn = 1000

; PostgreSQL-ə max bu qədər connection
default_pool_size = 20

; Minimum idle connection
min_pool_size = 5

; Emergency buffer
reserve_pool_size = 5
reserve_pool_timeout = 3   ; 3s sonra reserve-dən götür

; Idle server connection-ı nə vaxt bağla
server_idle_timeout = 600   ; 10 dəqiqə

; Connection-ın maksimum ömrü
server_lifetime = 3600      ; 1 saat

; Client timeout
client_idle_timeout = 0     ; Deaktiv (application idarə edir)

; Monitoring
stats_period = 60
log_connections = 0         ; Production-da performans üçün deaktiv
log_disconnections = 0
```

### Kod Nümunəsi — Laravel Database Konfiqurasiyası

```php
// config/database.php
'pgsql' => [
    'driver'   => 'pgsql',
    'host'     => env('DB_HOST', '127.0.0.1'),
    'port'     => env('DB_PORT', '6432'),          // PgBouncer port!
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),

    'options' => [
        // PgBouncer transaction mode-da prepared statement problemi
        // PHP tərəfindən emulate et — session state lazım olmur
        PDO::ATTR_EMULATE_PREPARES => true,

        // Connection timeout
        PDO::ATTR_TIMEOUT => 5,

        // Persistent connection — PgBouncer transaction mode ilə uyğun DEYİL
        // Session mode-da istifadə oluna bilər
        PDO::ATTR_PERSISTENT => false,
    ],

    // Read/write split
    'read' => [
        ['host' => env('DB_READ_HOST_1', '127.0.0.1'), 'port' => 6432],
        ['host' => env('DB_READ_HOST_2', '127.0.0.1'), 'port' => 6432],
    ],
    'write' => [
        'host' => env('DB_WRITE_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '6432'),
    ],
    'sticky' => true, // Write sonrası eyni request-də primary-dən oxu
],

// Connection leak — yanlış istifadə
// ❌ Manuel PDO — Laravel-dən kənar, pool idarəsi yoxdur
$pdo = new PDO('pgsql:host=...', 'user', 'pass');
// ... istifadə et ...
// $pdo = null; // Null etmək bağlamaya bərabər — amma garbage collection-a daxil olur

// ✅ Laravel DB facade — pool-u avtomatik idarə edir
DB::select('SELECT 1');
DB::transaction(function () { /* ... */ });
// Scope bitdikdə connection pool-a qaytarılır
```

### Kod Nümunəsi — HikariCP (Java)

```java
// Java projesinde HikariCP — ən performanslı connection pool
HikariConfig config = new HikariConfig();
config.setJdbcUrl("jdbc:postgresql://localhost:5432/mydb");
config.setUsername("app_user");
config.setPassword("secure_password");

// Pool size — CPU * 2 + 1 başlanğıc nöqtəsi
config.setMaximumPoolSize(20);

// Minimum idle — həmişə açıq
config.setMinimumIdle(5);

// Idle timeout — 5 dəqiqə
config.setIdleTimeout(300_000);

// Connection timeout — pool-da boş yox, 30s gözlə
config.setConnectionTimeout(30_000);

// Max lifetime — 30 dəqiqədə bir yenilə
// Server-side idle timeout-dan az olmalıdır
config.setMaxLifetime(1_800_000);

// Health check
config.setConnectionTestQuery("SELECT 1");
config.setKeepaliveTime(60_000); // 1 dəqiqədə bir ping

// Monitoring
config.setMetricRegistry(prometheusRegistry);
config.setPoolName("MainPool");

HikariDataSource dataSource = new HikariDataSource(config);

// Connection alıb istifadə et
try (Connection conn = dataSource.getConnection();
     PreparedStatement stmt = conn.prepareStatement("SELECT * FROM users WHERE id = ?")) {
    stmt.setInt(1, userId);
    ResultSet rs = stmt.executeQuery();
    // try-with-resources: conn avtomatik pool-a qaytarılır
}
```

### Kod Nümunəsi — PostgreSQL Monitoring

```sql
-- Mövcud connection-ları yoxla
SELECT
    state,
    wait_event_type,
    COUNT(*)                                     AS count,
    MAX(EXTRACT(EPOCH FROM (NOW() - state_change))) AS max_seconds_in_state
FROM pg_stat_activity
WHERE datname = current_database()
GROUP BY state, wait_event_type
ORDER BY count DESC;

-- Nəticə nümunəsi:
-- active     | null       | 15  | 0.3
-- idle       | null       | 8   | 234.5   ← 234s idle — leak şüphəli!
-- idle in tx | null       | 2   | 45.1    ← Transaction açıq qalıb!

-- Uzun müddət idle olan connection-ları tap
SELECT
    pid,
    usename,
    application_name,
    client_addr,
    state,
    EXTRACT(EPOCH FROM (NOW() - state_change))::int AS idle_seconds,
    LEFT(query, 50) AS last_query
FROM pg_stat_activity
WHERE datname = current_database()
  AND state = 'idle'
  AND state_change < NOW() - INTERVAL '5 minutes'
ORDER BY idle_seconds DESC;

-- Max connections limitinə necə yaxın olduğumuzu gör
SELECT
    COUNT(*)                   AS active_connections,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') AS max_conn,
    ROUND(COUNT(*) * 100.0 /
        (SELECT setting::int FROM pg_settings WHERE name = 'max_connections'), 1) AS usage_pct
FROM pg_stat_activity
WHERE datname = current_database();

-- PgBouncer admin console-da (psql -h 127.0.0.1 -p 6432 -U pgbouncer pgbouncer)
SHOW POOLS;
-- database | user     | cl_active | cl_waiting | sv_active | sv_idle | sv_used
-- mydb     | app_user | 45        | 0          | 18        | 2       | 0
-- cl_waiting=0 → pool yetərli
-- cl_waiting>0 → pool geniş, connection lazımdır

SHOW STATS;
-- total_query_count, total_xact_count, avg_query_time, avg_xact_time

SHOW CLIENTS;
-- Bağlı client-lər (PHP worker-lar)
```

### Attack/Failure Nümunəsi — Pool Exhaustion

```
Ssenari: Black Friday — "Too many connections" xətası

Arxitektura:
- 50 PHP-FPM worker (pm.max_children = 50)
- PgBouncer yoxdur
- PostgreSQL max_connections = 100
- Hər worker bağlandığı anda 1 connection açır → max 50 connection

Normal trafik: OK (50 connection << 100)

Black Friday:
1. Trafik artır → PHP-FPM 50 worker tam yüklənir
2. Hər worker ortalama 2 DB connection tutur (N+1 problem!)
3. 50 × 2 = 100 connection → PostgreSQL limiti!
4. Yeni connection: "FATAL: sorry, too many clients already"
5. Laravel: "SQLSTATE[08006] Connection failure"
6. Sayt çöküb!

Diagnosis:
SELECT COUNT(*) FROM pg_stat_activity; → 100

Tez həll (emergency):
1. PHP-FPM worker-ləri azalt: pm.max_children = 30
   (xətanı azaldar amma throughput azalır)

Düzgün həll:
1. PgBouncer əlavə et:
   max_client_conn = 200  (PHP worker-lər bağlanır)
   default_pool_size = 25  (PostgreSQL-ə max 25 connection)
2. N+1 problemi düzəlt → connection per worker azalır
3. Read replica → primary-nin yükü azalır
```

## Praktik Tapşırıqlar

- PgBouncer-i Docker-da qalxın, Laravel-i bağlayın, `pg_stat_activity`-dən connection sayını PgBouncer ilə/onsuz müqayisə edin
- `php-fpm` worker count × average connections per request hesablaması ilə optimal pool size tapın
- PgBouncer-in transaction mode-da prepared statement problemini reproduce edin — `PDO::ATTR_EMULATE_PREPARES` ilə düzəldin
- Connection leak simulasiya edin: transaction açın, commit/rollback etmədən 5 dəqiqə gözləyin, `pg_stat_activity`-dən `idle in transaction` görün
- `max_connections=10` ilə PostgreSQL çalışdırın, 15 concurrent request göndərin, exhaustion xətasını görün, PgBouncer ilə həll edin
- `SHOW POOLS` ilə PgBouncer pool utilization-ı izləyin — `cl_waiting` artıb-artmadığını görün

## Əlaqəli Mövzular
- `10-database-replication.md` — Read replica pool ayrımı
- `08-n-plus-1-problem.md` — N+1 connection count-unu artırır
- `05-query-optimization.md` — Uzun sorğular connection-ı tutur, pool-u bloklayır
- `07-database-deadlocks.md` — Deadlock-lar connection-ları blokda saxlayır
