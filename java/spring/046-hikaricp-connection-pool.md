# 046 — HikariCP Connection Pool — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [Connection Pool nədir?](#connection-pool-nədir)
2. [HikariCP konfiqurasiyası](#hikaricp-konfiqurasiyası)
3. [Kritik parametrlər](#kritik-parametrlər)
4. [Pool ölçüsü hesablaması](#pool-ölçüsü-hesablaması)
5. [Monitoring və diagnostika](#monitoring-və-diagnostika)
6. [Ümumi problemlər](#ümumi-problemlər)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Connection Pool nədir?

**Connection Pool** — əvvəlcədən açılmış DB bağlantıları toplusu. Hər sorğu üçün yeni bağlantı açmaq yerinə pool-dan hazır bağlantı götürülür.

```
Connection Pool olmadan:
  Request → Yeni TCP bağlantısı (50-200ms)
           → Authentication (10-50ms)
           → SQL icra (1-10ms)
           → Bağlantı bağla (10-50ms)
  
  Hər request: 70-310ms overhead!

Connection Pool ilə:
  Request → Pool-dan bağlantı götür (<1ms)
           → SQL icra (1-10ms)
           → Bağlantını pool-a qaytar (<1ms)
  
  Overhead: ~2ms → 100x sürətli!

HikariCP — Java üçün ən sürətli connection pool (Spring Boot default)
```

---

## HikariCP konfiqurasiyası

```yaml
# application.yml — əsas konfiqurasiya
spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/orderdb
    username: postgres
    password: secret
    driver-class-name: org.postgresql.Driver

    # HikariCP xüsusi konfiqurasiya
    hikari:
      # Pool ölçüsü
      minimum-idle: 5
      maximum-pool-size: 20

      # Timeout-lar (millisaniyə)
      connection-timeout: 30000        # Bağlantı gözləmə: 30s
      idle-timeout: 600000             # İdeal bağlantı vaxtı: 10 dəq
      max-lifetime: 1800000            # Maksimum bağlantı ömrü: 30 dəq
      keepalive-time: 60000            # Bağlantını canlı saxlama: 60s
      validation-timeout: 5000         # Bağlantı sağlamlıq yoxlama: 5s

      # Bağlantı sağlamlıq yoxlama
      connection-test-query: SELECT 1   # PostgreSQL-də lazım deyil (isSocketConnected)

      # Pool adı (monitoring üçün)
      pool-name: OrderServicePool

      # İzolasiya
      auto-commit: true
      read-only: false
      transaction-isolation: TRANSACTION_READ_COMMITTED

      # Connection init
      connection-init-sql: "SET timezone='Asia/Baku'"
```

```java
// ─── Proqramatik konfigurasiya ────────────────────────
@Configuration
public class DataSourceConfig {

    @Bean
    @Primary
    public DataSource primaryDataSource() {
        HikariConfig config = new HikariConfig();

        config.setJdbcUrl("jdbc:postgresql://localhost:5432/orderdb");
        config.setUsername("postgres");
        config.setPassword("secret");
        config.setDriverClassName("org.postgresql.Driver");

        // Pool parametrləri
        config.setMinimumIdle(5);
        config.setMaximumPoolSize(20);
        config.setConnectionTimeout(30_000);
        config.setIdleTimeout(600_000);
        config.setMaxLifetime(1_800_000);
        config.setKeepaliveTime(60_000);

        config.setPoolName("OrderServicePool");
        config.setAutoCommit(true);

        // PostgreSQL-specific optimizasiya
        config.addDataSourceProperty("cachePrepStmts", "true");
        config.addDataSourceProperty("prepStmtCacheSize", "250");
        config.addDataSourceProperty("prepStmtCacheSqlLimit", "2048");
        config.addDataSourceProperty("useServerPrepStmts", "true");

        return new HikariDataSource(config);
    }

    // Read replica üçün ayrı pool
    @Bean
    @Qualifier("readOnlyDataSource")
    public DataSource readOnlyDataSource() {
        HikariConfig config = new HikariConfig();
        config.setJdbcUrl("jdbc:postgresql://replica.example.com:5432/orderdb");
        config.setUsername("readonly_user");
        config.setPassword("readonly_pass");
        config.setReadOnly(true);
        config.setMaximumPoolSize(30); // Read replika — daha böyük pool
        config.setPoolName("ReadReplicaPool");

        return new HikariDataSource(config);
    }
}
```

---

## Kritik parametrlər

```
─────────────────────────────────────────────────────────────────────
Parametr              Default     Tövsiyə      Açıqlama
─────────────────────────────────────────────────────────────────────
maximumPoolSize       10          10-50        Maksimum bağlantı sayı
minimumIdle           = max       5-10         Minimum idle bağlantı
connectionTimeout     30000ms     30000ms      Pool-dan bağlantı gözləmə
idleTimeout           600000ms    600000ms     Idle-ı pool-dan çıxarma
maxLifetime           1800000ms   1800000ms    Bağlantının maksimum ömrü
keepaliveTime         0 (off)     60000ms      Canlı saxlama interval
validationTimeout     5000ms      5000ms       Sağlamlıq yoxlama timeout
─────────────────────────────────────────────────────────────────────
```

```java
// ─── Timeout-ların rolu ───────────────────────────────
class TimeoutExplanations {

    /**
     * connectionTimeout — pool doldu, bağlantı gözlənilir
     * 30s-dan çox gözləyirsə → SQLTimeoutException
     * Çox az = user-ı tez rədd edirik (timeout çox qısa)
     * Çox uzun = resource tükənir, tıxanır (timeout çox uzun)
     */
    void connectionTimeoutExample() throws SQLException {
        // Pool dolu olduqda bu 30s gözləyir, sonra exception
        try (var conn = dataSource.getConnection()) {
            // İş gör
        }
        // ConnectionPool tükəndikdə:
        // HikariPool-1 - Connection is not available, request timed out after 30000ms
    }

    /**
     * maxLifetime — bağlantının maksimum ömrü
     * DB server connection timeout-undan az olmalıdır!
     * PostgreSQL default: wait_timeout yoxdur, amma
     * MySQL default idle timeout: 8 saat (28800s)
     * Tövsiyə: MySQL wait_timeout-dan 30s az
     */
    void maxLifetimeBestPractice() {
        // MySQL wait_timeout = 3600s (1 saat) olarsa:
        // maxLifetime = 3600000 - 30000 = 3570000ms (59.5 dəq)
        // Bunun əvəzinə:
        // maxLifetime = 1800000 (30 dəq) — daha güvənli
    }

    /**
     * keepaliveTime — idle bağlantını canlı saxlama
     * Firewall/LB bağlantını idle timeout-dan sonra bağlaya bilər
     * keepalive = firewall timeout-undan az
     * idleTimeout > keepaliveTime olmalıdır
     */
    void keepaliveUsage() {
        // AWS ALB idle timeout default: 60s
        // keepaliveTime: 30000 (30s) — ALB-dan əvvəl "ping" göndərir
        // idleTimeout: 600000 (10 dəq) — 10 dəqiqə idle olarsa pool-dan çıxar
    }
}
```

---

## Pool ölçüsü hesablaması

```
─── HikariCP-nin tövsiyəsi ──────────────────────────────
Pool size = (core_count × 2) + effective_spindle_count

CPU: 4 core, SSD (spindle = 1):
  Pool size = (4 × 2) + 1 = 9 ≈ 10

CPU: 8 core, SSD:
  Pool size = (8 × 2) + 1 = 17 ≈ 20

─── Niyə böyük pool daha yaxşı deyil? ─────────────────
10 thread, 10 bağlantı:
  Her thread sorğu edir → 10ms
  Total: 10 × 10ms = 100ms (bir turdən) → throughput yüksəkdir

100 thread, 100 bağlantı:
  DB 100 sorğunu paralel işləyir
  CPU context switching artır
  I/O bottleneck
  → Hər sorğu daha uzun çəkir!

Yaxşı pool: kiçik, sürətli.
Böyük pool: illüziya — throughput artmır, latency artır.

─── Praktik qayda ───────────────────────────────────────
maximum-pool-size = thread_pool_size (Tomcat default: 200)
YANLIŞ! Tomcat-ın bütün thread-ləri eyni anda DB-yə girmir.

Doğru hesab:
  1. Profiling ilə DB aktiv bağlantı sayını ölç
  2. SELECT pg_stat_activity WHERE state = 'active'
  3. Adətən: max 20-50 bağlantı yetərlidir

Kubernetes:
  3 pod × 20 connection = 60 DB connection
  PostgreSQL max_connections = 100 → problem!
  Pgbouncer əlavə edin: pod → Pgbouncer → PostgreSQL
```

---

## Monitoring və diagnostika

```java
// ─── Actuator ilə monitoring ──────────────────────────
// application.yml:
// management:
//   endpoints:
//     web:
//       exposure:
//         include: health, metrics, prometheus
//   health:
//     db:
//       enabled: true

// GET /actuator/health
// {
//   "status": "UP",
//   "components": {
//     "db": {
//       "status": "UP",
//       "details": {
//         "database": "PostgreSQL",
//         "validationQuery": "isValid()"
//       }
//     }
//   }
// }

// ─── Prometheus metriklər ─────────────────────────────
// hikaricp.connections.active     → Aktiv bağlantılar
// hikaricp.connections.idle       → Idle bağlantılar
// hikaricp.connections.pending    → Gözləyən sorğular
// hikaricp.connections.timeout    → Timeout sayı
// hikaricp.connections.max        → Pool ölçüsü
// hikaricp.connections.min        → Minimum idle
// hikaricp.connections.acquire    → Bağlantı alma vaxtı (histogram)
// hikaricp.connections.usage      → Bağlantı istifadə vaxtı (histogram)

// ─── Proqramatik monitoring ───────────────────────────
@Component
public class HikariPoolMonitor {

    private final HikariDataSource dataSource;

    @Scheduled(fixedDelay = 60_000) // Hər dəqiqə
    public void logPoolStats() {
        HikariPoolMXBean poolProxy = dataSource.getHikariPoolMXBean();

        log.info("HikariCP Pool Stats [{}]:" +
            " Active={}, Idle={}, Waiting={}, Total={}",
            dataSource.getPoolName(),
            poolProxy.getActiveConnections(),
            poolProxy.getIdleConnections(),
            poolProxy.getThreadsAwaitingConnection(),
            poolProxy.getTotalConnections()
        );
    }

    // Alert: Pool doluğu 80%-dən çox olduqda
    @Scheduled(fixedDelay = 10_000)
    public void checkPoolHealth() {
        HikariPoolMXBean pool = dataSource.getHikariPoolMXBean();
        int max = dataSource.getMaximumPoolSize();
        int active = pool.getActiveConnections();

        double utilization = (double) active / max;
        if (utilization > 0.8) {
            log.warn("Pool utilization high: {}/{}  ({}%)",
                active, max, String.format("%.0f", utilization * 100));
            alertService.send("DB Pool almost full!");
        }
    }
}
```

---

## Ümumi problemlər

```java
// ─── Problem 1: Connection Leak ───────────────────────
// Bağlantı pool-a qaytarılmır → pool tükənir

// YANLIŞ — connection sızdırır
public void processOrder(Long orderId) throws SQLException {
    Connection conn = dataSource.getConnection();
    // conn.close() çağırılmadı → LEAK!
    Statement stmt = conn.createStatement();
    stmt.execute("UPDATE orders SET status='PROCESSED' WHERE id=" + orderId);
}

// DOĞRU — try-with-resources
public void processOrder(Long orderId) throws SQLException {
    try (Connection conn = dataSource.getConnection();
         PreparedStatement ps = conn.prepareStatement(
             "UPDATE orders SET status='PROCESSED' WHERE id=?")) {
        ps.setLong(1, orderId);
        ps.executeUpdate();
    } // Avtomatik close → pool-a qaytar
}

// HikariCP leak detection:
// hikari:
//   leak-detection-threshold: 2000  # 2s-dən uzun bağlantı → warning
// Log: HikariPool-1 - Connection leak detection triggered...

// ─── Problem 2: Pool Exhaustion ───────────────────────
// Çox uzun transaction-lar pool-u tutur

// YANLIŞ — uzun transaction
@Transactional
public void processAllOrders() {
    List<Order> orders = orderRepository.findAll(); // 10,000 order
    for (Order order : orders) {
        processOrder(order); // Hər biri 100ms → 1000s transaction!
    }
    // Bu müddətdə bir DB bağlantısı tutulur
}

// DOĞRU — batch processing
@Transactional
public void processAllOrders() {
    Page<Order> page;
    int pageNum = 0;
    do {
        page = orderRepository.findAll(PageRequest.of(pageNum++, 100));
        page.forEach(this::processOrder);
    } while (page.hasNext());
}

// ─── Problem 3: N+1 Problem ───────────────────────────
// Hər row üçün ayrı sorğu → pool tıxanır

// YANLIŞ
List<Order> orders = orderRepository.findAll();
for (Order order : orders) {
    order.getCustomer().getName(); // Hər order üçün ayrı SELECT!
}

// DOĞRU — JOIN FETCH
@Query("SELECT o FROM Order o JOIN FETCH o.customer")
List<Order> findAllWithCustomer();

// ─── Problem 4: Long-running queries ──────────────────
// Slow query bağlantını uzun tutur → pool tıxanır

// PostgreSQL-də slow query monitoring:
// log_min_duration_statement = 1000  # 1s-dən uzun sorğuları log et

// Application tərəfdən statement timeout:
// hikari:
//   connection-init-sql: "SET statement_timeout='30s'"
```

---

## İntervyu Sualları

### 1. Connection Pool nədir?
**Cavab:** Əvvəlcədən açılmış DB bağlantıları toplusu. Hər sorğu üçün yeni bağlantı açmaq (TCP handshake, authentication, vb.) 50-200ms overhead yaradır. Pool-dan hazır bağlantı götürmək <1ms-dir. HikariCP Java-da ən sürətli pool kitabxanasıdır — Spring Boot default-dur.

### 2. maximumPoolSize necə seçilir?
**Cavab:** HikariCP tövsiyəsi: `(CPU_core × 2) + effective_spindle_count`. SSD ilə 4 core → ~10. Mühüm: böyük pool = yaxşı deyil. DB-nin CPU/I/O kapasitesi məhduddur — 100 parallel sorğu 10 parallel sorğudan yavaş ola bilər (context switching, I/O contention). Profiling ilə real aktiv bağlantı sayı ölçülməlidir — `SELECT pg_stat_activity WHERE state='active'`.

### 3. maxLifetime niyə vacibdir?
**Cavab:** DB server (MySQL, AWS RDS) idle bağlantıları müəyyən vaxtdan sonra bağlayır. `maxLifetime` bu limitdən az olmalıdır — pool bağlantını öz iradəsiylə yeniləyir, server bağlamadan əvvəl. MySQL `wait_timeout` (default 8 saat) olarsa `maxLifetime = 1800000ms (30 dəq)` güvənlidir. Əks halda "Communications link failure" xətaları yaranır.

### 4. Connection Leak nədir?
**Cavab:** Bağlantı pool-dan götürülür amma pool-a qaytarılmır (`close()` çağırılmır). Zamanla pool tükənir — yeni sorğular `connectionTimeout` sonrası exception alır. `leak-detection-threshold: 2000` ilə HikariCP uzun tutulan bağlantıları log edir. `try-with-resources` ilə həmişə avtomatik close — leak-dən qorunma.

### 5. Kubernetes-də HikariCP istifadəsinin xüsusiyyəti?
**Cavab:** 10 pod × 20 bağlantı = 200 DB bağlantısı. PostgreSQL `max_connections` default 100 — problem! Həll: (1) `maximumPoolSize`-ı azalt (pod sayına görə böl); (2) **PgBouncer** əlavə et — connection multiplexer, 100 DB bağlantısı ilə 1000 application bağlantısına xidmət edir; (3) AWS RDS Proxy, Google Cloud SQL Proxy. Pod avtoskalası olduqda dinamik pool ölçüsü kritikdir.

*Son yenilənmə: 2026-04-10*
