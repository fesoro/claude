# Time-Series Databases

## Time-Series Data Nedir?

Zamana gore siralanmis data noquteleri. Her noqute **timestamp + value(s)** ibaretdir.

**Misallar:**
- Server CPU/RAM metrikleri (her 10 saniye)
- IoT sensor datalari (temperatur, nem)
- Maliyye melumat (sehm qiymetleri her saniye)
- Application logs / request metrics
- E-commerce analytics (page views, conversions)

**Niye adi SQL database yetmir?**

```sql
-- PostgreSQL-de metrics table
CREATE TABLE server_metrics (
    id BIGSERIAL PRIMARY KEY,
    server_id INT,
    cpu_usage FLOAT,
    memory_usage FLOAT,
    disk_io FLOAT,
    recorded_at TIMESTAMP
);

-- Problemler:
-- 1. Insert yuku: 100 server × 6 metric × 1/saniye = 600 insert/saniye
--    1000 server = 6000 insert/saniye → Boyuk yuk!
-- 2. Table olcusu: 6000/san × 86400 san/gun = 518M row/gun!
-- 3. Aggregation yavas: AVG(cpu) son 24 saat ucun 518M row scan edir
-- 4. Kohne data silmek cetin: DELETE FROM ... WHERE recorded_at < '...' → yavas, lock
```

## Time-Series Database Novleri

| Database | Tip | Dil | Best For |
|----------|-----|-----|----------|
| **TimescaleDB** | PostgreSQL extension | C | SQL isteyenler, hybrid workload |
| **InfluxDB** | Purpose-built | Go | Monitoring, IoT, metrics |
| **QuestDB** | Purpose-built | Java/C++ | Ultra-high ingest, fintech |
| **Prometheus** | Pull-based | Go | Kubernetes monitoring |
| **ClickHouse** | OLAP/Columnar | C++ | Analytics (ayri movzu - 34) |
| **VictoriaMetrics** | Prometheus-compatible | Go | Long-term metric storage |

---

## TimescaleDB

PostgreSQL **extension-u**. PostgreSQL-in butun xususiyyetlerini saxlayir + time-series optimization elave edir.

### Niye TimescaleDB?

```
PostgreSQL + TimescaleDB = En yaxsi iki dunyanin birlesmesi

✅ Adi SQL istifade et (yeni dil oyrenmek lazim deyil)
✅ JOINs, triggers, views, functions - her sey isleyir
✅ Movcud PostgreSQL-e extension kimi elave olunur
✅ Hypertable - avtomatik partitioning
✅ Compression - 90%+ disk qenayeti
✅ Continuous aggregates - materialized view kimi amma avtomatik
✅ Data retention - kohne data avtomatik silinir
```

### Qurasdirilma

```bash
# Docker
docker run -d --name timescaledb \
  -p 5432:5432 \
  -e POSTGRES_PASSWORD=secret \
  timescale/timescaledb:latest-pg16

# Movcud PostgreSQL-e extension elave et
CREATE EXTENSION IF NOT EXISTS timescaledb;
```

### Hypertable (Time-Series Table)

```sql
-- Adi table yarat
CREATE TABLE server_metrics (
    time        TIMESTAMPTZ NOT NULL,
    server_id   INT NOT NULL,
    cpu_usage   DOUBLE PRECISION,
    memory_usage DOUBLE PRECISION,
    disk_io     DOUBLE PRECISION,
    network_in  BIGINT,
    network_out BIGINT
);

-- Hypertable-a cevir (avtomatik time-based partitioning)
SELECT create_hypertable('server_metrics', 'time');
-- Indi "server_metrics" zahiren tek table-dir amma daxilde
-- avtomatik olaraq time-based chunk-lara bolunur (default: 7 gun)

-- Chunk intervalini deyis
SELECT set_chunk_time_interval('server_metrics', INTERVAL '1 day');

-- Insert - adi SQL kimi!
INSERT INTO server_metrics (time, server_id, cpu_usage, memory_usage, disk_io)
VALUES
    (NOW(), 1, 75.5, 60.2, 120),
    (NOW(), 2, 45.3, 80.1, 95),
    (NOW(), 3, 92.1, 55.8, 200);

-- Index (time-series ucun optimize olunmus)
CREATE INDEX idx_metrics_server_time ON server_metrics (server_id, time DESC);
```

### Time-Series Sorgulamalar

```sql
-- Son 1 saatin ortalama CPU istifadesi (server bazinda)
SELECT server_id,
       time_bucket('5 minutes', time) AS bucket,  -- TimescaleDB xususi funksiya
       AVG(cpu_usage) AS avg_cpu,
       MAX(cpu_usage) AS max_cpu,
       MIN(cpu_usage) AS min_cpu
FROM server_metrics
WHERE time > NOW() - INTERVAL '1 hour'
GROUP BY server_id, bucket
ORDER BY bucket DESC;

-- Son 24 saatde en cox CPU istifade eden server-ler
SELECT server_id,
       AVG(cpu_usage) AS avg_cpu,
       MAX(cpu_usage) AS peak_cpu,
       COUNT(*) AS data_points
FROM server_metrics
WHERE time > NOW() - INTERVAL '24 hours'
GROUP BY server_id
HAVING AVG(cpu_usage) > 80
ORDER BY avg_cpu DESC;

-- First/Last value
SELECT server_id,
       first(cpu_usage, time) AS first_cpu,    -- En kohne deyer
       last(cpu_usage, time) AS last_cpu       -- En yeni deyer
FROM server_metrics
WHERE time > NOW() - INTERVAL '1 hour'
GROUP BY server_id;

-- Downsampling: Saatlik ortalamalar
SELECT time_bucket('1 hour', time) AS hour,
       server_id,
       AVG(cpu_usage) AS avg_cpu,
       percentile_cont(0.95) WITHIN GROUP (ORDER BY cpu_usage) AS p95_cpu
FROM server_metrics
WHERE time > NOW() - INTERVAL '7 days'
GROUP BY hour, server_id
ORDER BY hour DESC;
```

### Continuous Aggregates

```sql
-- Materialized view kimi amma avtomatik yenilenilir
CREATE MATERIALIZED VIEW hourly_metrics
WITH (timescaledb.continuous) AS
SELECT time_bucket('1 hour', time) AS bucket,
       server_id,
       AVG(cpu_usage) AS avg_cpu,
       MAX(cpu_usage) AS max_cpu,
       AVG(memory_usage) AS avg_memory
FROM server_metrics
GROUP BY bucket, server_id;

-- Avtomatik refresh policy
SELECT add_continuous_aggregate_policy('hourly_metrics',
    start_offset => INTERVAL '3 hours',
    end_offset => INTERVAL '1 hour',
    schedule_interval => INTERVAL '1 hour'
);

-- Sorgu (cox suretli - precomputed data)
SELECT * FROM hourly_metrics
WHERE server_id = 1
  AND bucket > NOW() - INTERVAL '7 days'
ORDER BY bucket DESC;
```

### Data Retention & Compression

```sql
-- Compression aktiv et (90%+ disk qenayeti)
ALTER TABLE server_metrics SET (
    timescaledb.compress,
    timescaledb.compress_segmentby = 'server_id',
    timescaledb.compress_orderby = 'time DESC'
);

-- 7 gunden kohne datalari avtomatik compress et
SELECT add_compression_policy('server_metrics', INTERVAL '7 days');

-- 90 gunden kohne datalari avtomatik sil
SELECT add_retention_policy('server_metrics', INTERVAL '90 days');

-- Compression neticelerini gor
SELECT chunk_name,
       pg_size_pretty(before_compression_total_bytes) AS before,
       pg_size_pretty(after_compression_total_bytes) AS after,
       round((1 - after_compression_total_bytes::numeric / before_compression_total_bytes) * 100, 1) AS compression_pct
FROM chunk_compression_stats('server_metrics');
```

### Laravel ile TimescaleDB

```php
// Normal PostgreSQL connection istifade olunur!
// config/database.php - adi pgsql config

// Migration
Schema::create('server_metrics', function (Blueprint $table) {
    $table->timestampTz('time');
    $table->integer('server_id');
    $table->float('cpu_usage');
    $table->float('memory_usage');
    $table->float('disk_io');
    $table->index(['server_id', 'time']);
});

// Hypertable-a cevir
DB::statement("SELECT create_hypertable('server_metrics', 'time')");

// Model
class ServerMetric extends Model
{
    public $timestamps = false;
    protected $casts = ['time' => 'datetime'];

    // Son 1 saatin metrikleri
    public function scopeLastHour(Builder $query): Builder
    {
        return $query->where('time', '>', now()->subHour());
    }

    // time_bucket ile aggregation
    public static function hourlyAverage(int $serverId, int $hours = 24): Collection
    {
        return DB::select("
            SELECT time_bucket('1 hour', time) AS bucket,
                   AVG(cpu_usage) AS avg_cpu,
                   AVG(memory_usage) AS avg_memory
            FROM server_metrics
            WHERE server_id = ? AND time > NOW() - INTERVAL '{$hours} hours'
            GROUP BY bucket
            ORDER BY bucket DESC
        ", [$serverId]);
    }
}

// Insert (yuksek throughput ucun bulk)
$metrics = [];
foreach ($servers as $server) {
    $metrics[] = [
        'time' => now(),
        'server_id' => $server->id,
        'cpu_usage' => $server->getCpuUsage(),
        'memory_usage' => $server->getMemoryUsage(),
        'disk_io' => $server->getDiskIo(),
    ];
}
DB::table('server_metrics')->insert($metrics);
```

---

## InfluxDB

Purpose-built time-series database. Oz query dili (Flux / InfluxQL) var.

### Esas Konseptler

```
InfluxDB termini    SQL termini
Bucket              Database
Measurement         Table
Tag                 Indexed column (string only)
Field               Non-indexed column
Timestamp           PRIMARY KEY (avtomatik)
Point               Row
```

### InfluxDB Write & Query

```bash
# InfluxDB 2.x Line Protocol ile write
# measurement,tag1=val1,tag2=val2 field1=val1,field2=val2 timestamp

curl -X POST 'http://localhost:8086/api/v2/write?bucket=monitoring&org=myorg' \
  -H 'Authorization: Token mytoken' \
  --data-raw '
server_metrics,server_id=web-01,region=eu cpu_usage=75.5,memory_usage=60.2 1704067200000000000
server_metrics,server_id=web-02,region=eu cpu_usage=45.3,memory_usage=80.1 1704067200000000000
'
```

```flux
// Flux query dili
// Son 1 saatin CPU metrikleri
from(bucket: "monitoring")
  |> range(start: -1h)
  |> filter(fn: (r) => r._measurement == "server_metrics")
  |> filter(fn: (r) => r._field == "cpu_usage")
  |> filter(fn: (r) => r.server_id == "web-01")
  |> aggregateWindow(every: 5m, fn: mean)
  |> yield(name: "avg_cpu")

// InfluxQL (SQL-e oxsar, kohne versiya)
SELECT MEAN(cpu_usage) FROM server_metrics
WHERE server_id = 'web-01' AND time > now() - 1h
GROUP BY time(5m)
```

### PHP ile InfluxDB

```php
// composer require influxdata/influxdb-client-php

use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;

$client = new Client([
    'url' => 'http://localhost:8086',
    'token' => 'my-token',
    'org' => 'myorg',
    'bucket' => 'monitoring',
]);

// Write
$writeApi = $client->createWriteApi();

$point = Point::measurement('server_metrics')
    ->addTag('server_id', 'web-01')
    ->addTag('region', 'eu')
    ->addField('cpu_usage', 75.5)
    ->addField('memory_usage', 60.2)
    ->time(microtime(true) * 1000000000, WritePrecision::NS);

$writeApi->write($point);

// Batch write (suretli)
$points = [];
foreach ($servers as $server) {
    $points[] = Point::measurement('server_metrics')
        ->addTag('server_id', $server->name)
        ->addField('cpu_usage', $server->cpu)
        ->addField('memory_usage', $server->memory);
}
$writeApi->write($points);

// Query
$queryApi = $client->createQueryApi();
$result = $queryApi->query('
    from(bucket: "monitoring")
    |> range(start: -1h)
    |> filter(fn: (r) => r._measurement == "server_metrics")
    |> filter(fn: (r) => r._field == "cpu_usage")
    |> aggregateWindow(every: 5m, fn: mean)
');

foreach ($result as $table) {
    foreach ($table->records as $record) {
        echo $record->getTime() . ': ' . $record->getValue() . "\n";
    }
}
```

---

## TimescaleDB vs InfluxDB

| Xususiyyet | TimescaleDB | InfluxDB |
|------------|-------------|----------|
| **Base** | PostgreSQL extension | Purpose-built |
| **Query dili** | SQL | Flux / InfluxQL |
| **JOINs** | Beli (full SQL) | Xeyr |
| **Schema** | Relational (columns) | Schemaless (tags + fields) |
| **Compression** | 90%+ | 80%+ |
| **Clustering** | TimescaleDB enterprise | InfluxDB enterprise |
| **Learning curve** | Asagi (SQL bilirsense) | Orta (Flux oyrenmek lazim) |
| **Hybrid workload** | Beli (relational + TS) | Xeyr (yalniz TS) |
| **Ecosystem** | PostgreSQL (extensions, tools) | Telegraf, Grafana |
| **Best for** | SQL seviyorsan, movcud PG var | Pure metrics/IoT, Grafana stack |

## Ne Vaxt Time-Series DB Lazimdir?

```
Adi PostgreSQL/MySQL ile bas girir:
- < 1000 metric/saniye insert
- Data < 100M row
- Sadə aggregation-lar

Time-Series DB lazimdir:
- 10K+ metric/saniye insert
- Data > 1B row
- Complex time-based aggregation (moving average, percentile)
- Avtomatik data retention (kohne data silme)
- Compression lazimdir (disk qenayeti)
- Real-time monitoring/alerting
```

## Real-World Arxitektura

```
Metrics Collection:
  Servers/Apps → Telegraf/Agent → InfluxDB → Grafana Dashboard
  
  ve ya (PostgreSQL ekosisteminde):

  Servers/Apps → Laravel Job → TimescaleDB → Grafana Dashboard
                                    ↕
                              PostgreSQL (business data)
                              (JOINs mumkundur!)
```

## Interview Suallari

1. **Time-series database adi SQL database-den nece ferqlidir?**
   - Write-optimized (append-only), time-based partitioning, avtomatik compression, data retention, time-bucketing funksiyalari. SQL DB bu workload ucun dizayn olunmayib.

2. **TimescaleDB niye populyardir?**
   - PostgreSQL extension-dur - SQL isleyir, JOIN ede biler, movcud PG tools isleyir. Time-series optimization alirsan amma relational xususiyyetleri itirmirsən.

3. **Tag vs Field ferqi (InfluxDB)?**
   - Tag: Index olunur, string-dir, filter/group by ucun (server_id, region). Field: Index olunmur, her tip ola biler, olculen deyerlerdir (cpu_usage, memory).

4. **Continuous aggregate nedir?**
   - Avtomatik yenilenen materialized view. Meselen, saatlik ortalamalari onceden hesablayir - query zamani milyonlarla row scan etmek lazim olmur.

5. **Data retention nece isleyir?**
   - Policy ile mueyyenlenir: "90 gunden kohne chunk-lari sil". Chunk-based oldugu ucun sil emeliyyati suretlidir (DELETE deyil, DROP chunk).
