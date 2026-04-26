# Time-Series Databases (Lead)

## Mündəricat
1. [Time-Series Data nədir?](#time-series-data-nədir)
2. [Əsas TSDB-lər](#əsas-tsdb-lər)
3. [Data Model və Sorğular](#data-model-və-sorğular)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Time-Series Data nədir?

```
Zaman möhürü ilə qeyd edilmiş ölçümlər ardıcıllığı.

Nümunələr:
  Server metrics:  CPU 45% @ 14:00:01, CPU 52% @ 14:00:02
  IoT sensors:     Temperatur 22.5°C @ 10:00:00
  Business KPI:    Satış: 1250 USD @ 2026-04-10 09:00
  Application:     HTTP latency p99: 45ms @ every minute

Time-Series Data xüsusiyyətləri:
  ✓ Yazma çox, oxuma az (write-heavy)
  ✓ Son data daha çox oxunur (recent reads)
  ✓ Silmə nadir
  ✓ Aggregation lazımdır (avg, sum, p99)
  ✓ Data köhnəldikcə compression/downsampling

Niyə adi DB yox?
  PostgreSQL-də milyonlarla IoT metric: indeks şişər
  Timestamp-based range query-lər yavaşlar
  Time-series compression mümkün deyil
  Downsampling (resolution azaltma) yoxdur
```

---

## Əsas TSDB-lər

```
InfluxDB:
  SQL-like InfluxQL / Flux sorğu dili
  Tag-based indexing
  Retention policies (köhnə data avtomatik silmə)
  PHP client mövcud

TimescaleDB:
  PostgreSQL extension
  Hypertable (zaman əsaslı sharding)
  SQL tam dəstək
  Mövcud PostgreSQL inteqrasiyası ilə uyğun

Prometheus:
  Pull-based metrics collection
  PromQL sorğu dili
  Alert manager inteqrasiyası
  Uzun müddətli saxlama üçün Thanos/Cortex

ClickHouse:
  Columnar store (analytics üçün)
  Real-time aggregation
  SQL dəstəkli
  Çox geniş scale

OpenTSDB:
  HBase üzərində
  Tag-based query
  Böyük miqyas

Seçim meyarları:
  Sadə metrics monitoring → Prometheus
  IoT / sensor data → InfluxDB
  PostgreSQL stack → TimescaleDB
  Analytics / high volume → ClickHouse
```

---

## Data Model və Sorğular

```
InfluxDB Data Model:
  Measurement (cədvəl kimi): cpu_usage
  Tags (indekslənmiş): host=server1, region=eu
  Fields (ölçüm): value=45.2, user=30.1
  Timestamp: 2026-04-10T14:00:00Z

  Yazma (Line Protocol):
  cpu_usage,host=server1,region=eu value=45.2,user=30.1 1712758800000000000

Sorğu nümunələri (Flux):
  // Son 1 saatda ortalama CPU
  from(bucket: "metrics")
    |> range(start: -1h)
    |> filter(fn: (r) => r._measurement == "cpu_usage")
    |> filter(fn: (r) => r.host == "server1")
    |> aggregateWindow(every: 5m, fn: mean)

TimescaleDB (SQL):
  SELECT
    time_bucket('5 minutes', time) AS bucket,
    avg(value) AS avg_cpu
  FROM cpu_metrics
  WHERE host = 'server1'
    AND time > NOW() - INTERVAL '1 hour'
  GROUP BY bucket
  ORDER BY bucket;

Downsampling (retention):
  Raw data: 1s interval, 7 gün saxla
  Aggregated (5m): 1 ay saxla
  Aggregated (1h): 1 il saxla
  Köhnə raw data silinir, aggregated qalır
```

---

## PHP İmplementasiyası

```php
<?php
// InfluxDB PHP client
// composer require influxdata/influxdb-client-php

use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;

class MetricsCollector
{
    private \InfluxDB2\WriteApi $writeApi;
    private \InfluxDB2\QueryApi $queryApi;

    public function __construct(
        string $url   = 'http://localhost:8086',
        string $token = '',
        string $org   = 'myorg',
        string $bucket = 'metrics',
    ) {
        $client = new Client([
            'url'    => $url,
            'token'  => $token,
            'org'    => $org,
            'bucket' => $bucket,
        ]);
        $this->writeApi = $client->createWriteApi();
        $this->queryApi = $client->createQueryApi();
    }

    public function recordHttpRequest(
        string $route,
        string $method,
        int    $statusCode,
        float  $durationMs,
    ): void {
        $point = Point::measurement('http_requests')
            ->addTag('route',       $route)
            ->addTag('method',      $method)
            ->addTag('status_code', (string) $statusCode)
            ->addField('duration_ms', $durationMs)
            ->addField('count',       1)
            ->time(microtime(true) * 1e9, WritePrecision::NS);

        $this->writeApi->write($point);
    }

    public function getP99Latency(string $route, string $range = '-1h'): float
    {
        $query = <<<FLUX
        from(bucket: "metrics")
          |> range(start: {$range})
          |> filter(fn: (r) => r._measurement == "http_requests")
          |> filter(fn: (r) => r.route == "{$route}")
          |> filter(fn: (r) => r._field == "duration_ms")
          |> quantile(q: 0.99)
        FLUX;

        $tables = $this->queryApi->query($query);
        foreach ($tables as $table) {
            foreach ($table->records as $record) {
                return $record->getValue();
            }
        }

        return 0.0;
    }
}
```

```php
<?php
// TimescaleDB (PostgreSQL extension) ilə PHP PDO
class TimescaleMetrics
{
    public function __construct(private \PDO $db) {}

    public function insert(string $metric, string $host, float $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO metrics (time, metric, host, value) VALUES (NOW(), ?, ?, ?)'
        );
        $stmt->execute([$metric, $host, $value]);
    }

    public function getHourlyAverage(string $metric, string $host): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                time_bucket('1 hour', time) AS hour,
                AVG(value) AS avg_value,
                MAX(value) AS max_value,
                MIN(value) AS min_value
             FROM metrics
             WHERE metric = ?
               AND host = ?
               AND time > NOW() - INTERVAL '24 hours'
             GROUP BY hour
             ORDER BY hour"
        );
        $stmt->execute([$metric, $host]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function setupRetentionPolicy(): void
    {
        // TimescaleDB: köhnə raw data-nı compression ilə sıxışdır
        $this->db->exec(
            "SELECT add_compression_policy('metrics', INTERVAL '7 days')"
        );
        // 1 ildən köhnəni sil
        $this->db->exec(
            "SELECT add_retention_policy('metrics', INTERVAL '1 year')"
        );
    }
}
```

---

## İntervyu Sualları

- Time-series database adi relational DB-dən nə üçün daha uyğundur?
- InfluxDB-nin Tag ilə Field fərqi nədir?
- Downsampling nədir? Niyə lazımdır?
- TimescaleDB PostgreSQL-dən nə ilə fərqlənir?
- Prometheus pull-based olması ilə push-based TSDB fərqi nədir?
- IoT layihəsində hansı TSDB seçərdiniz? Niyə?
