# Time-Series Databases (Lead ⭐⭐⭐⭐)

## İcmal
Time-series database — zamana görə sıralanmış ölçüm məlumatlarını saxlamaq üçün optimallaşdırılmış xüsusi database növüdür. Monitoring metrics, IoT sensor data, financial tick data, server logs — bunlar üçün PostgreSQL-in adi yanaşması scale olmur. Bu mövzu Lead interview-larda sistem dizaynında ixtisaslaşmış storage biliyinizi yoxlayır.

## Niyə Vacibdir
Metrics, logs, events — hər ciddi backend sistemi bunları toplayır. İnterviewer bu sualla sizin "PostgreSQL-ə hər şeyi qoy" yanaşmasının limitlərini bildiyinizi, InfluxDB/TimescaleDB/Prometheus kimi alətləri tanıdığınızı, retention policy, downsampling, cardinality kimi time-series-specific konseptləri bildiyinizi yoxlayır.

## Əsas Anlayışlar

- **Time-Series Data:** Timestamp + value + tags — zamanla dəyişən ölçümlər. Nümunə: CPU=72.5%, host=server1, time=2025-01-15T10:30:00Z
- **Append-Only:** Yeni data həmişə əlavə olunur, köhnə nadir güncəllənir. Insert-heavy, delete nadir, update demək olar ki, yoxdur
- **Immutability:** Tarixi ölçüm dəyişdirilmir — keçmiş real-dir. Audit, financial records, compliance üçün vacib
- **Cardinality:** Fərqli tag kombinasiyaları sayı. Hər `host+service+metric` kombinasiyası bir cardinality. InfluxDB-də yüksək cardinality (milyon unikal tag kombinasiyası) performansı ciddi aşağı salır
- **Retention Policy:** Müəyyən müddətdən köhnə data avtomatik silinir. Disk idarəsi üçün kritik — 15 gün raw, 90 gün saatlıq aggregate, 2 il günlük aggregate
- **Downsampling (Rollup):** Dəqiqəlik → saatlıq → günlük aggregate. Köhnə raw data silinir, summary saxlanır. Disk qənaəti + query sürəti
- **Time Bucketing:** `time_bucket('1h', time)` ilə zaman aralıqlarında group-by — "hər saatın ortalaması"
- **InfluxDB:** Ən məşhur dedicated time-series DB. InfluxQL (SQL-like) ya da Flux sorğu dili. Line protocol ilə yazma: `cpu,host=server1 value=72.5 1705316600000000000`
- **TimescaleDB:** PostgreSQL extension — eyni SQL, hypertable, continuous aggregate, native compression. Mövcud PostgreSQL stack-ına ən az disruptive seçim
- **Prometheus:** Monitoring-specific time-series. Pull model (server metriki özü çəkir), PromQL sorğu dili, 15s default scrape interval, short retention (15 gün), AlertManager inteqrasiyası
- **Grafana:** Visualization layer — Prometheus, InfluxDB, TimescaleDB, ClickHouse-a bağlanır. Production monitoring-in defacto standartı
- **VictoriaMetrics:** Prometheus-compatible, yüksək compression, daha az RAM, drop-in replacement
- **OpenTSDB:** HBase üzərindən time-series. Yüksək cardinality dəstəkli, amma çox mürəkkəb
- **ClickHouse:** OLAP database — time-series kimi də istifadə olunur, özellikle yüksək throughput log analytics üçün
- **Compression:** Time-series datası çox compressible-dir. Delta encoding (fərqləri saxla), timestamp compression, delta-of-delta, XOR compression (Gorilla — Prometheus-un istifadə etdiyi)
- **Hypertable (TimescaleDB):** PostgreSQL table-ını time-based partitioned chunks-a böler — hər chunk ayrı fayl. Köhnə chunk-lar compress edilir, retention policy tətbiq olunur
- **Continuous Aggregate:** Background-da avtomatik yenilənən materialized view. TimescaleDB-nin downsampling mexanizmi

## Praktik Baxış

**Interview-da yanaşma:**
- "Metrics sistemi dizayn edin" sualında time-series DB-ni önər — "PostgreSQL timestamp column kifayət deyil, scale etmər"
- Retention + downsampling policy-ni mütləq qeyd edin — "raw data sonsuza saxlamaq olmaz"
- "Niyə normal SQL kifayət deyil?" sualına hazır ol: insert rate, retention, compression, time-range queries

**Follow-up suallar:**
- "TimescaleDB ilə InfluxDB fərqi nədir?" — TimescaleDB: SQL + PostgreSQL ecosystem; InfluxDB: native time-series, Flux dili, öz API-si
- "High cardinality problemi nədir?" — Çox unikal tag kombinasiyası → InfluxDB-də index şişir, yazma yavaşlayır
- "Retention policy olmasa nə baş verər?" — Disk dolur, query-lər yavaşlayır, system crash edər
- "Prometheus niyə long-term storage üçün uyğun deyil?" — Local storage, 15 gün default, remote_write lazımdır
- "Pull model vs push model fərqi?" — Pull: server hər endpoint-dən çəkir (Prometheus); Push: client server-ə göndərir (InfluxDB, OpenTelemetry)

**Ümumi səhvlər:**
- "PostgreSQL-ə timestamp column əlavə et, kifayətdir" demək — 10M+ row/gün üçün scale etmir
- Cardinality problemini bilməmək — InfluxDB-yə yüksək cardinality yazanda crash
- Downsampling-i qeyd etməmək — "2 il raw data" = TiB-lərlə disk
- Prometheus-u long-term storage kimi dizayn etmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- TimescaleDB-nin PostgreSQL extension olduğunu bilmək — mövcud SQL bacarıqları işə gəlir, migration minimal
- Prometheus pull model + remote_write arxitekturasını izah etmək
- "Biz Prometheus (short-term) + TimescaleDB (long-term) stack-i qurduq" demək — real deployment nümunəsi
- Gorilla compression-ın time-series üçün niyə effektiv olduğunu izah etmək

## Nümunələr

### Tipik Interview Sualı
"Aplikasiyanızın CPU, memory, request latency metrics-lərini saxlamaq üçün sistem dizayn edin. Günlük 10 milyon data nöqtəsi, 2 il retention tələbi var."

### Güclü Cavab
Bu metrics pipeline dizayn sualıdır. Tələbi analiz edək: 10M/gün = ~115/saniyə — normal write throughput. 2 il retention = 7.3 milyard data nöqtəsi — raw format saxlamaq qeyri-mümkün.

**Stack:**

**Collection:** Prometheus — application-dan 15 saniyədə bir scrape edir. PHP/Laravel app-dan `/metrics` endpoint-ı expose edirik.

**Short-term storage:** Prometheus-un özü (15 gün) — son 15 gün üçün Grafana dashboard-ları real-time göstərir.

**Long-term storage:** TimescaleDB — PostgreSQL extension, mövcud skillimizlə işlədə bilərik, SQL biliyimiz işə gəlir. Prometheus `remote_write` ilə TimescaleDB-yə (Promscale adapter vasitəsilə) yazır.

**Retention + Downsampling strategy:**
- 0-15 gün: Raw data (15s interval) → Prometheus
- 15 gün - 90 gün: Saatlıq aggregate → TimescaleDB continuous aggregate
- 90 gün - 2 il: Günlük aggregate → TimescaleDB continuous aggregate
- Raw data > 15 gün: Silinir

**Visualization:** Grafana — Prometheus + TimescaleDB-yə eyni anda bağlanır.

**Scale:** 115 write/saniyə TimescaleDB üçün trivial — 100K+/saniyə dəstəkləyir.

### Kod Nümunəsi
```sql
-- TimescaleDB: Hypertable yaratma
CREATE EXTENSION IF NOT EXISTS timescaledb;

CREATE TABLE metrics (
    time          TIMESTAMPTZ NOT NULL,
    host          TEXT        NOT NULL,
    service       TEXT        NOT NULL,
    metric_name   TEXT        NOT NULL,
    value         DOUBLE PRECISION NOT NULL,
    tags          JSONB,
    unit          TEXT
);

-- Hypertable: 7 günlük chunks — hər chunk ayrı fayl
SELECT create_hypertable(
    'metrics',
    'time',
    chunk_time_interval => INTERVAL '7 days'
);

-- Mövcud index (host + service + metric üçün)
CREATE INDEX idx_metrics_host_service
ON metrics (host, service, metric_name, time DESC);

-- Compress policy: 14 gündən köhnə chunk-ları sıxışdır
ALTER TABLE metrics SET (
    timescaledb.compress,
    timescaledb.compress_segmentby = 'host, service, metric_name',
    timescaledb.compress_orderby   = 'time DESC'
);
SELECT add_compression_policy('metrics', INTERVAL '14 days');

-- Retention policy: 90 gündən köhnə raw data sil
SELECT add_retention_policy('metrics', INTERVAL '90 days');

-- Continuous Aggregate: Saatlıq rollup (downsampling)
CREATE MATERIALIZED VIEW metrics_hourly
WITH (timescaledb.continuous) AS
SELECT
    time_bucket('1 hour', time) AS hour,
    host,
    service,
    metric_name,
    AVG(value)  AS avg_value,
    MAX(value)  AS max_value,
    MIN(value)  AS min_value,
    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY value) AS p95,
    PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY value) AS p99,
    COUNT(*)    AS sample_count
FROM metrics
GROUP BY hour, host, service, metric_name
WITH NO DATA;

-- Saatlıq rollup üçün refresh policy
SELECT add_continuous_aggregate_policy('metrics_hourly',
    start_offset      => INTERVAL '3 hours',
    end_offset        => INTERVAL '1 hour',
    schedule_interval => INTERVAL '1 hour'
);

-- Günlük aggregate (daha uzunmüddətli)
CREATE MATERIALIZED VIEW metrics_daily
WITH (timescaledb.continuous) AS
SELECT
    time_bucket('1 day', time) AS day,
    host, service, metric_name,
    AVG(avg_value) AS avg_value,
    MAX(max_value) AS max_value,
    MIN(min_value) AS min_value,
    SUM(sample_count) AS total_samples
FROM metrics_hourly
GROUP BY day, host, service, metric_name
WITH NO DATA;

SELECT add_continuous_aggregate_policy('metrics_daily',
    start_offset      => INTERVAL '3 days',
    end_offset        => INTERVAL '1 day',
    schedule_interval => INTERVAL '1 day'
);

-- Retention for aggregates
SELECT add_retention_policy('metrics_hourly', INTERVAL '1 year');
SELECT add_retention_policy('metrics_daily',  INTERVAL '5 years');
```

```sql
-- Sorğu nümunələri
-- Son 24 saatın CPU ortalaması (raw data)
SELECT
    time_bucket('5 minutes', time) AS bucket,
    AVG(value) AS avg_cpu
FROM metrics
WHERE metric_name = 'cpu_percent'
  AND host        = 'prod-web-01'
  AND time        > NOW() - INTERVAL '24 hours'
GROUP BY bucket
ORDER BY bucket;

-- Son 30 günün saatlıq CPU (continuous aggregate-dən — sürətli)
SELECT hour, avg_value, max_value, p99
FROM metrics_hourly
WHERE metric_name = 'cpu_percent'
  AND host        = 'prod-web-01'
  AND hour        > NOW() - INTERVAL '30 days'
ORDER BY hour;

-- Son 2 ilin günlük agregat (metrics_daily-dən — çox sürətli)
SELECT day, avg_value, max_value
FROM metrics_daily
WHERE metric_name = 'request_latency_ms'
  AND service     = 'api-gateway'
  AND day         > NOW() - INTERVAL '2 years'
ORDER BY day;

-- Anomaly detection: p99 > threshold olan saatlar
SELECT hour, host, avg_value, p99
FROM metrics_hourly
WHERE metric_name = 'http_request_duration_ms'
  AND p99         > 1000  -- 1 saniyədən çox
  AND hour        > NOW() - INTERVAL '7 days'
ORDER BY p99 DESC
LIMIT 20;
```

```python
# InfluxDB v2 — Python client
from influxdb_client import InfluxDBClient, Point, WritePrecision
from influxdb_client.client.write_api import SYNCHRONOUS, ASYNCHRONOUS
from datetime import datetime

client   = InfluxDBClient(
    url   = "http://localhost:8086",
    token = "my-token",
    org   = "my-org"
)
write_api = client.write_api(write_options=SYNCHRONOUS)

# Data yazma — Point API
point = (
    Point("server_metrics")
    .tag("host",    "prod-web-01")
    .tag("region",  "az-east")
    .tag("env",     "production")
    .field("cpu_percent",         72.5)
    .field("memory_used_mb",      4096)
    .field("request_latency_ms",  45.2)
    .field("active_connections",  1234)
    .time(datetime.utcnow(), WritePrecision.NANOSECONDS)
)
write_api.write(bucket="app-metrics", record=point)

# Batch yazma (yüksək throughput üçün)
points = [
    Point("server_metrics")
    .tag("host", f"prod-web-{i:02d}")
    .field("cpu_percent", 40.0 + i * 5)
    for i in range(20)
]
write_api.write(bucket="app-metrics", record=points)

# Flux sorğu — son 1 saatın P95 latency
query_api = client.query_api()
flux_query = '''
from(bucket: "app-metrics")
  |> range(start: -1h)
  |> filter(fn: (r) => r._measurement == "server_metrics")
  |> filter(fn: (r) => r._field == "request_latency_ms")
  |> filter(fn: (r) => r.env == "production")
  |> aggregateWindow(every: 5m, fn: mean, createEmpty: false)
  |> quantile(q: 0.95)
  |> yield(name: "p95_latency")
'''
tables = query_api.query(flux_query)
for table in tables:
    for record in table.records:
        print(f"Host: {record['host']}, P95: {record['_value']:.1f}ms")
```

```yaml
# Prometheus konfiqurasiyası
# prometheus.yml
global:
  scrape_interval:     15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'laravel-app'
    static_configs:
      - targets: ['app:9090']  # /metrics endpoint

  - job_name: 'php-fpm'
    static_configs:
      - targets: ['phpfpm-exporter:9253']

  - job_name: 'nginx'
    static_configs:
      - targets: ['nginx-exporter:9113']

  - job_name: 'postgres'
    static_configs:
      - targets: ['postgres-exporter:9187']

# Remote write — TimescaleDB-yə (Promscale)
remote_write:
  - url: "http://promscale:9201/write"
    queue_config:
      max_samples_per_send: 10000
      batch_send_deadline: 5s

remote_read:
  - url: "http://promscale:9201/read"
    read_recent: true

# Retention (Prometheus-un öz local storage-u)
# prometheus --storage.tsdb.retention.time=15d
```

```php
// PHP/Laravel application-dan Prometheus metrics expose etmək
// composer require promphp/prometheus_client_php

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis as PrometheusRedis;

// AppServiceProvider-da register et
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CollectorRegistry::class, function () {
            $adapter = new PrometheusRedis([
                'host' => config('database.redis.default.host'),
                'port' => config('database.redis.default.port'),
            ]);
            return new CollectorRegistry($adapter);
        });
    }
}

// Middleware: HTTP metrics
class PrometheusMetricsMiddleware
{
    public function __construct(
        private readonly CollectorRegistry $registry
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $start;

        // HTTP request duration histogram
        $histogram = $this->registry->getOrRegisterHistogram(
            'app',
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            ['method', 'route', 'status_code'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5]
        );
        $histogram->observe($duration, [
            $request->method(),
            $request->route()?->getName() ?? 'unknown',
            (string) $response->getStatusCode(),
        ]);

        // Request counter
        $counter = $this->registry->getOrRegisterCounter(
            'app',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'route', 'status_code']
        );
        $counter->incBy(1, [
            $request->method(),
            $request->route()?->getName() ?? 'unknown',
            (string) $response->getStatusCode(),
        ]);

        return $response;
    }
}

// /metrics endpoint (Prometheus scrape edir)
Route::get('/metrics', function (CollectorRegistry $registry) {
    $renderer = new RenderTextFormat();
    return response(
        $renderer->render($registry->getMetricFamilySamples()),
        200,
        ['Content-Type' => RenderTextFormat::MIME_TYPE]
    );
})->middleware('auth.basic'); // Basic auth ilə qoru
```

### İkinci Nümunə — VictoriaMetrics (Prometheus drop-in)

```bash
# VictoriaMetrics — Prometheus-compatible, daha az RAM, yüksək compression
# Docker Compose ilə:

# docker-compose.yml
services:
  victoriametrics:
    image: victoriametrics/victoria-metrics:latest
    ports:
      - "8428:8428"
    volumes:
      - vm-data:/storage
    command:
      - '--storageDataPath=/storage'
      - '--retentionPeriod=12'  # 12 ay retention
      - '--httpListenAddr=:8428'

  prometheus:
    image: prom/prometheus:latest
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.retention.time=1d'  # Prometheus yalnız 1 gün saxlayır
      # Remote write VictoriaMetrics-ə
    # prometheus.yml-ə əlavə et:
    # remote_write:
    #   - url: http://victoriametrics:8428/api/v1/write

# Grafana datasource: http://victoriametrics:8428 (Prometheus API compatible)
# PromQL sorğuları eyni işləyir
```

## Praktik Tapşırıqlar

- TimescaleDB ilə hypertable yaradın, 10M metrics insert edin, eyni sorğunu PostgreSQL adi table-da vs TimescaleDB-də benchmark edin
- Continuous aggregate ilə saatlıq rollup yaradın, real-time yenilənməsini `pg_stat_continuous_aggs`-dən izləyin
- PHP/Laravel applikasiyanızdan Prometheus metrics expose edin, Prometheus-u `localhost:9090`-da çalışdırın, metrics-ləri scrape edin
- Grafana-nı Prometheus-a bağlayın, CPU + memory + request latency dashboard yaradın
- Retention policy + downsampling pipeline-ı tamamilə qurun: 15 gün raw → 1 ay saatlıq → 2 il günlük
- InfluxDB-yə yüksək cardinality data yazın (1M unikal tag kombinasiyası), performans degradation-ı müşahidə edin

## Əlaqəli Mövzular
- `17-polyglot-persistence.md` — Time-series DB polyglot stack-inin bir parçasıdır
- `01-sql-vs-nosql.md` — Xüsusi SQL (TimescaleDB) vs native time-series (InfluxDB) seçimi
- `19-graph-databases.md` — Digər ixtisaslaşmış DB növü ilə müqayisə
- `10-database-replication.md` — Time-series DB-nin HA arxitekturası
