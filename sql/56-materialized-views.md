# Materialized Views (Middle)

## View vs Materialized View

**Adi VIEW** - virtual table. Her query-de original table-dan oxuyur (saxlanmir).

**MATERIALIZED VIEW** - fiziki olaraq saxlanir (cache kimi). REFRESH edilene kimi koxhne data qalir.

```sql
-- Adi VIEW
CREATE VIEW active_users AS
SELECT * FROM users WHERE deleted_at IS NULL;

SELECT * FROM active_users; 
-- Daxilde: SELECT * FROM users WHERE deleted_at IS NULL; (her defe)

-- MATERIALIZED VIEW (PostgreSQL)
CREATE MATERIALIZED VIEW active_users AS
SELECT * FROM users WHERE deleted_at IS NULL;

SELECT * FROM active_users;
-- Daxilde: SELECT * FROM active_users; (cache-den oxuyur)
```

| Xususiyyet | VIEW | MATERIALIZED VIEW |
|------------|------|-------------------|
| Storage | Yox (yalniz query saxlanir) | Beli (data cache-de) |
| Read performansi | Original query-den asili | Adi table kimi suretli |
| Data freshness | Hemise canli | REFRESH-e kimi koxhne |
| Index destek | Yox | Beli (CREATE INDEX uzerinde) |
| Disk usage | 0 | Original kimi ola biler |
| Write maliyet | 0 | REFRESH bahalidir |

---

## PostgreSQL: CREATE MATERIALIZED VIEW

```sql
CREATE MATERIALIZED VIEW order_summary AS
SELECT 
    user_id,
    COUNT(*) AS order_count,
    SUM(total) AS lifetime_value,
    MAX(created_at) AS last_order_at
FROM orders
WHERE status = 'completed'
GROUP BY user_id
WITH DATA;  -- yaranan kimi populate et (default)
-- WITH NO DATA - bos yarat, sonra REFRESH-le doldur
```

**Index elave et (cox vacib!):**

```sql
-- Unique index REFRESH CONCURRENTLY ucun mecburi
CREATE UNIQUE INDEX ON order_summary (user_id);

-- Read pattern-e gore elave index
CREATE INDEX ON order_summary (lifetime_value DESC);
```

---

## REFRESH MATERIALIZED VIEW

### Adi REFRESH (FULL LOCK)

```sql
REFRESH MATERIALIZED VIEW order_summary;
```

**Ne olur:**
- ACCESS EXCLUSIVE lock alinir (oxuma da bloklanir!)
- View tamamile silinir, query yeniden icra olunur
- Boyuk view-da dakika-larla davam ede biler
- Bu zaman heç kim view-dan oxuya bilmez

### REFRESH ... CONCURRENTLY (lock-suz)

```sql
REFRESH MATERIALIZED VIEW CONCURRENTLY order_summary;
```

**Ne olur:**
- Yalniz `EXCLUSIVE` lock (SELECT bloklanmir)
- Yeni snapshot temp table-da yaradir, sonra `INSERT/UPDATE/DELETE` ile mevcudu update edir
- Production ucun mecburi
- **TELEB:** UNIQUE INDEX olmalidir (yoxsa error)
- 2-3x yavasdir adi REFRESH-den

```sql
-- Setup
CREATE UNIQUE INDEX idx_order_summary_user ON order_summary (user_id);
REFRESH MATERIALIZED VIEW CONCURRENTLY order_summary;
```

---

## Refresh Strategiyalari

### 1. Cron / Laravel Scheduler

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(fn() => 
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY order_summary')
    )->hourly();
    
    $schedule->call(fn() =>
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY daily_revenue')
    )->dailyAt('02:00');
}
```

**Ne vaxt:** Analytics dashboard, gunluk hesabat - 1 saat freshness kifayet edir.

### 2. Trigger-based (real-time)

```sql
CREATE OR REPLACE FUNCTION refresh_summary() RETURNS TRIGGER AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY order_summary;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER orders_change_refresh
AFTER INSERT OR UPDATE OR DELETE ON orders
FOR EACH STATEMENT
EXECUTE FUNCTION refresh_summary();
```

> **Diqqet:** Her statement-de REFRESH cox bahalidir. Yalniz az traffic table-da uygundur.

### 3. Queue Job (debounced)

```php
class RefreshOrderSummary implements ShouldQueue
{
    public $uniqueFor = 60; // 1 dakika boyu eyni job dispatch olunmaz
    
    public function uniqueId(): string
    {
        return 'refresh-order-summary';
    }
    
    public function handle(): void
    {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY order_summary');
    }
}

// Order observer-de
class OrderObserver {
    public function created(Order $order): void
    {
        RefreshOrderSummary::dispatch()->delay(now()->addSeconds(30));
    }
}
```

`ShouldBeUnique` interface ile 30 sn icinde 100 dispatch -> 1 REFRESH.

### 4. Debezium / CDC (gercek vaxt streaming)

Postgres WAL-i Kafka-ya yazib, downstream consumer materialized table-i update edir. Daha murekkeb, amma sub-second freshness.

---

## Incremental Materialized Views

Adi `REFRESH` butun query-ni yeniden icra edir. Boyuk view-da bu 30+ dakika cekibilir.

**pg_ivm (Incremental View Maintenance) extension:**

```sql
CREATE EXTENSION pg_ivm;

CREATE INCREMENTAL MATERIALIZED VIEW order_summary AS
SELECT user_id, COUNT(*) AS cnt, SUM(total) AS sum
FROM orders GROUP BY user_id;

-- Artiq REFRESH lazim deyil! Trigger-le yalniz deyisen row-lari update edir.
INSERT INTO orders (user_id, total) VALUES (1, 100);
-- order_summary avtomatik incremental update olunur
```

**Citus / TimescaleDB - continuous aggregates:**

```sql
-- TimescaleDB
CREATE MATERIALIZED VIEW hourly_revenue
WITH (timescaledb.continuous) AS
SELECT 
    time_bucket('1 hour', created_at) AS hour,
    SUM(total) AS revenue
FROM orders GROUP BY hour;

SELECT add_continuous_aggregate_policy('hourly_revenue',
    start_offset => INTERVAL '1 day',
    end_offset => INTERVAL '1 hour',
    schedule_interval => INTERVAL '15 minutes');
```

---

## MySQL-de Materialized View Emulation

MySQL-de native MATERIALIZED VIEW yoxdur. Emulation:

### Helli 1: Summary Table + Scheduled Event

```sql
CREATE TABLE order_summary (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    order_count INT UNSIGNED,
    lifetime_value DECIMAL(15,2),
    last_order_at TIMESTAMP,
    refreshed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- MySQL Event Scheduler
SET GLOBAL event_scheduler = ON;

CREATE EVENT refresh_order_summary
ON SCHEDULE EVERY 1 HOUR
DO
    INSERT INTO order_summary (user_id, order_count, lifetime_value, last_order_at)
    SELECT user_id, COUNT(*), SUM(total), MAX(created_at)
    FROM orders WHERE status = 'completed' GROUP BY user_id
    ON DUPLICATE KEY UPDATE
        order_count = VALUES(order_count),
        lifetime_value = VALUES(lifetime_value),
        last_order_at = VALUES(last_order_at),
        refreshed_at = CURRENT_TIMESTAMP;
```

### Helli 2: Laravel Scheduler (daha rahat)

```php
// app/Console/Kernel.php
$schedule->call(function () {
    DB::statement('
        INSERT INTO order_summary (user_id, order_count, lifetime_value, last_order_at)
        SELECT user_id, COUNT(*), SUM(total), MAX(created_at)
        FROM orders WHERE status = "completed"
        GROUP BY user_id
        ON DUPLICATE KEY UPDATE
            order_count = VALUES(order_count),
            lifetime_value = VALUES(lifetime_value),
            last_order_at = VALUES(last_order_at)
    ');
})->hourly();
```

### Helli 3: Trigger-based (real-time, ekspensiv)

```sql
DELIMITER $$
CREATE TRIGGER orders_summary_update AFTER INSERT ON orders
FOR EACH ROW BEGIN
    IF NEW.status = 'completed' THEN
        INSERT INTO order_summary (user_id, order_count, lifetime_value, last_order_at)
        VALUES (NEW.user_id, 1, NEW.total, NEW.created_at)
        ON DUPLICATE KEY UPDATE
            order_count = order_count + 1,
            lifetime_value = lifetime_value + NEW.total,
            last_order_at = GREATEST(last_order_at, NEW.created_at);
    END IF;
END$$
DELIMITER ;
```

---

## Use Cases

### 1. Analytics Dashboard

```sql
-- Cox bahali - hemise canli icra etme
SELECT 
    DATE_TRUNC('day', created_at) AS day,
    COUNT(*) AS orders,
    SUM(total) AS revenue,
    AVG(total) AS avg_order
FROM orders
JOIN users ON orders.user_id = users.id
WHERE created_at > NOW() - INTERVAL '90 days'
GROUP BY day;
-- 30 saniye!

-- Materialized View ile - <100ms
CREATE MATERIALIZED VIEW daily_revenue AS
SELECT 
    DATE_TRUNC('day', created_at) AS day,
    COUNT(*) AS orders,
    SUM(total) AS revenue,
    AVG(total) AS avg_order
FROM orders WHERE created_at > NOW() - INTERVAL '90 days'
GROUP BY day;

CREATE UNIQUE INDEX ON daily_revenue (day);
```

### 2. Expensive JOINs

```sql
-- 5 table JOIN, hesabat ucun
CREATE MATERIALIZED VIEW user_full_profile AS
SELECT 
    u.id, u.email, u.name,
    a.country, a.city,
    SUM(o.total) AS lifetime_value,
    COUNT(o.id) AS order_count,
    s.subscription_tier,
    s.expires_at
FROM users u
LEFT JOIN addresses a ON a.user_id = u.id AND a.is_default
LEFT JOIN orders o ON o.user_id = u.id AND o.status = 'completed'
LEFT JOIN subscriptions s ON s.user_id = u.id AND s.is_active
GROUP BY u.id, u.email, u.name, a.country, a.city, s.subscription_tier, s.expires_at;
```

### 3. Geocoding Cache

```sql
CREATE MATERIALIZED VIEW user_locations AS
SELECT 
    u.id,
    geocode(a.address) AS coordinates  -- Bahali API call
FROM users u JOIN addresses a ON a.user_id = u.id;
```

---

## Freshness vs Cost Tradeoff

| Strategy | Freshness | Cost | Use case |
|----------|-----------|------|----------|
| Live query | Real-time | Her query bahali | Critical real-time data |
| MV + hourly refresh | 1 saat | Saatda 1 bahali | Analytics dashboard |
| MV + 5 min refresh | 5 dak | 12x saatda | Sales monitoring |
| MV + trigger refresh | Real-time | Her INSERT bahali | Az traffic, vacib data |
| MV + queue debounce | ~30 sn | Burst-safe | Order summary, KPI |
| Incremental MV (pg_ivm) | Real-time | Her INSERT kicik | Boyuk data, sik update |

---

## MV vs Redis Cache

| Xususiyyet | Materialized View | Redis Cache |
|------------|-------------------|-------------|
| Persistence | Disk (DB) | Memory (RDB/AOF) |
| Query language | SQL (JOIN, agregat) | Key-value, simple |
| Sutun bazasinda index | Beli | Yox (key-le axtaris) |
| Refresh complexity | Tek SQL command | Application logic |
| TTL | Yox (manual) | Beli (built-in) |
| Network round-trip | DB-de yaxin | Ayri service |
| Cost | DB disk + CPU | Memory ucundur |

**Ne vaxt MV:** Murekkeb agregat, JOIN-li hesabat, SQL filter lazim.
**Ne vaxt Redis:** Sade key-le axtaris (user session, rate limit, leaderboard).

---

## Chained MVs

Bir MV digerinden asili ola biler:

```sql
-- Level 1
CREATE MATERIALIZED VIEW order_daily AS
SELECT DATE(created_at) AS day, user_id, SUM(total) AS daily_total
FROM orders GROUP BY day, user_id;

-- Level 2 (Level 1-den asili)
CREATE MATERIALIZED VIEW user_monthly AS
SELECT 
    DATE_TRUNC('month', day) AS month,
    user_id,
    SUM(daily_total) AS monthly_total
FROM order_daily GROUP BY month, user_id;

-- REFRESH order: child once, parent sonra
REFRESH MATERIALIZED VIEW CONCURRENTLY order_daily;
REFRESH MATERIALIZED VIEW CONCURRENTLY user_monthly;
```

**Laravel-de orchestration:**

```php
$schedule->command('refresh:mv order_daily')->hourly();
$schedule->command('refresh:mv user_monthly')->hourlyAt(5); // 5 dak sonra
```

---

## Storage Cost

```sql
SELECT 
    schemaname, matviewname,
    pg_size_pretty(pg_total_relation_size(matviewname::text)) AS size
FROM pg_matviews;
```

> 1M row-luq order_summary ~ 100-500 MB. Index ucun elave 50-100 MB. Boyuk MV-lar disk space-i sik yox edir.

---

## Index on Materialized View

```sql
CREATE MATERIALIZED VIEW product_stats AS
SELECT product_id, COUNT(*) AS sales_count, SUM(quantity) AS total_qty
FROM order_items GROUP BY product_id;

-- UNIQUE INDEX (CONCURRENTLY refresh ucun)
CREATE UNIQUE INDEX ON product_stats (product_id);

-- Read-pattern indexler
CREATE INDEX ON product_stats (sales_count DESC);
CREATE INDEX ON product_stats (total_qty DESC) WHERE sales_count > 100;
```

> **Diqqet:** REFRESH zamani index-ler de yenilenir (extra cost). Hedsiz index qoyma.

---

## Laravel Migration ile MV idare et

```php
// database/migrations/xxx_create_order_summary_mv.php
public function up(): void
{
    DB::statement('
        CREATE MATERIALIZED VIEW order_summary AS
        SELECT user_id, COUNT(*) AS cnt, SUM(total) AS lv
        FROM orders WHERE status = \'completed\'
        GROUP BY user_id
    ');
    
    DB::statement('CREATE UNIQUE INDEX ON order_summary (user_id)');
}

public function down(): void
{
    DB::statement('DROP MATERIALIZED VIEW IF EXISTS order_summary');
}
```

---

## Interview suallari

**Q: REFRESH MATERIALIZED VIEW vs CONCURRENTLY ferqi?**
A: Adi REFRESH ACCESS EXCLUSIVE lock alir - bu zaman SELECT da bloklanir, downtime yaranir. CONCURRENTLY yalniz EXCLUSIVE lock alir, SELECT bloklanmir. Production-da hemise CONCURRENTLY istifade et. Telebler: UNIQUE INDEX olmalidir, 2-3x daha yavasdir.

**Q: MV ne vaxt istifade etmemeli?**
A: 1) Real-time data lazimdirsa (freshness > performance). 2) Cox sik dayisen data (refresh cost > query saving). 3) Kicik dataset (live query suretlidir). 4) Az oxunan data (cache hit yox). 5) Disk space mehduddur (MV original kimi yer tutur).

**Q: MySQL-de MV emulasiyasi nece edilir?**
A: Native dest yoxdur. 1) Summary table + Event Scheduler (CREATE EVENT). 2) Laravel Scheduler ile periodic INSERT ON DUPLICATE KEY UPDATE. 3) Trigger-based real-time update (her INSERT-de summary row update). 4) Debezium ile CDC -> aggregate service.

**Q: pg_ivm nedir?**
A: PostgreSQL Incremental View Maintenance extension. Adi MV butun query-ni REFRESH-de yeniden icra edir. pg_ivm trigger qoyaraq yalniz deyisen row-larin etkisini incremental tetbiq edir. Boyuk MV-da REFRESH 30 dakika cekirse, pg_ivm millisaniyede update edir.

**Q: MV refresh hardware-i nece etkileyir?**
A: REFRESH zamani: 1) Yuksek CPU (query yeniden icra olunur). 2) Yuksek I/O (yeni snapshot disk-e yazilir). 3) WAL artisi (CONCURRENTLY-de hemcinin). 4) Lock contention (adi REFRESH-de). Heller: off-peak vaxtda planla, CONCURRENTLY istifade et, replica-da REFRESH (read replica), incremental MV (pg_ivm).
