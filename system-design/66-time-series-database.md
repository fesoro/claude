# Time-Series Database Design

## Nədir? (What is it?)

Time-Series Database (TSDB) zaman üzrə sıralanmış ölçülərin (metric, sensor
reading, stock tick) yüksək tezlikli yazılması, sıxılması və zaman pəncərəsinə
görə sürətli query-si üçün ixtisaslaşmış bazadır. Prometheus TSDB, InfluxDB,
TimescaleDB, VictoriaMetrics, M3DB, Facebook Gorilla nümunələridir.

Adi DB-də row-lar random UUID ilə yazılır; TSDB-də yazılar **timestamp ardıcıllığı
ilə append-only** gəlir, ona görə tamamilə fərqli storage layout və kompresiya
taktikası lazımdır.

Bu fayl TSDB **daxili mexanikası** haqqındadır — WAL, chunk, inverted index,
Gorilla, downsampling. Toplama/alerting pipeline üçün bax:
[53-metrics-monitoring-design.md](53-metrics-monitoring-design.md).

```
  INGEST           MEMORY        COMMIT          LONG-TERM
 ┌────────┐      ┌─────────┐    ┌──────────┐    ┌──────────┐
 │ write  │─WAL─▶│ head    │──▶ │ block N  │──▶ │S3/parquet│
 │ batch  │      │(mutable │    │(2h imm.) │    │downsample│
 │ (1k/s) │      │ chunks) │    │index+chnk│    │ 5m / 1h  │
 └────────┘      └─────────┘    └──────────┘    └──────────┘
      ▲               │               ▲
      └─ WAL replay ──┘               │
```

## Time-Series Özəllikləri (Characteristics)

- **Append-only mostly** — update nadir, delete TTL; insert ~99% workload.
- **Yüksək ingestion** — 100K-10M sample/sec tipik scale.
- **Recent data hot** — son 1 saat query-lərin 95%-i; tarixi data soyuq.
- **Time-range queries dominate** — `rate(x[5m])`, `last 1h`, `between t1,t2`.
- **Window aggregation** — sum, avg, p99, histogram zaman pəncərəsində.

## Time-Series Data Model (Data Model)

```
( metric_name , labels{tags}              , timestamp , value )
  http_requests {method=GET,status=200}    1713441600   18350
  series_id = hash(metric_name + sorted(labels))
```

**Series** = metric + unikal label set (yeni label dəyəri = yeni series).
**Sample** = (timestamp, value) cütlüyü. **Value** `float64`; string yox.
`# HELP` və `# TYPE` annotasiya metric semantikası verir (counter / gauge /
histogram / summary). `rate()` yalnız counter üçün; gauge üçün `delta()`.

## Cardinality — Əsas Düşmən (Cardinality)

Cardinality = **unikal series sayı** = RAM + index + query latency.

```
{method, status, endpoint}     = 5 × 7 × 80   = 2,800 series   (OK)
+ user_id                      × 10M users    = 28B series     (OOM)
+ trace_id                     × ∞            = dies in minutes
```

**Qaydalar:** `user_id`, `request_id`, `session_id`, `email`, `IP` label
OLMAMALIDIR; high-cardinality siqnal **log / trace**-ə (Loki, Tempo); label
dəyərləri `< 1000`, total `< 10M` per tenant; `relabel_configs` ilə scrape
əvvəli filtrasiya; route template (`/orders/{id}`), raw path YOX.

## Ingestion Pipeline (Yazı yolu)

```
client ─batch(1k)─▶ receiver ─▶ WAL (fsync ~100ms)
                                    │
                                    ▼
                              head block (RAM)
                               ├─ series index (id → chunk*)
                               ├─ active chunks (skip list)
                               └─ postings (label=val → series_id[])
                                    │  every 2h / full
                                    ▼
                              immutable block on disk
```

Addımlar: batch gəlir (HTTP remote-write, Kafka, OTLP) → WAL append (crash
safety) → head RAM skip list-ə sample → periodic fsync (100-500ms) → 2h-da head
"cut" → immutable block → compactor kiçikləri birləşdirir (2h → 8h → 24h) →
retention köhnə block silir.

LSM-tree pattern: **in-memory skip list → immutable sorted block → compact**.

## Storage Optimization — Sıxılma (Compression)

### Timestamp Delta-of-delta

```
RAW:        1713441600, 1713441615, 1713441630, 1713441645
DELTA:             +15, +15, +15
DELTA-OF-D:          0,   0,   0   ← 0-lar 1 bit-ə yazılır
```

Regular interval-da (1s, 15s) sample-lərin 96%-i delta-of-delta sıfırdır →
varint ~1 byte/timestamp (8× raw).

### Gorilla XOR Float Compression

```
CURRENT  XOR PREVIOUS  = çox zero bit (yaxın qiymət)
Store (leading zeros count, meaningful bits only)
```

Facebook VLDB 2015: 12× kompresiya; 16 byte/sample → ~1.3 byte/sample; 26
saatlıq data 16 GB → 1.3 GB.

### Dictionary Compression (Labels)

Label dəyərləri symbol table-da dedupe olunur:
`{0:"method", 1:"GET", 2:"status", 3:"200"}` → series int-index saxlayır, string
yox.

## Block Layout (Chunk daxili)

```
┌─ Block (2h, immutable, self-contained) ────────┐
│ meta.json  — minTime, maxTime, stats, checksum │
│ chunks/    — series_N Gorilla-encoded          │
│ index/     — symbol table + postings + series  │
│ tombstones — deleted series markers            │
└────────────────────────────────────────────────┘
```

Self-contained block S3-ə atıla və müstəqil read oluna bilər (Thanos dizaynı).

## Inverted Index (Label Lookup)

PromQL `{job="api", env="prod"}` → iki postings kəsişməsi:

```
postings["job=api"]  = [101, 105, 203, 301, 402, 508, 612]
postings["env=prod"] = [101, 150, 203, 402, 612, 701]
INTERSECT            = [101, 203, 402, 612]   ← matching series
```

Sorted postings + SIMD intersection (Lucene-ə oxşar). Regex (`=~"5.."`) bütün
value-ları iterate edir — regex label-lər az olmalı.

## Query Execution (Oxu yolu)

Addımlar: **1)** series selection (label match → postings intersect) →
**2)** chunk selection (timestamp range filter) → **3)** Gorilla decompression →
**4)** aggregation (`rate`, `sum`, `histogram_quantile`) → **5)** result
(instant/range vector). Optimizasiyalar: chunk prefetch paralel, result cache
(Grafana refresh), block pruning (`maxTime < query.start` skip).

## Retention və Compaction

```
TIME →  now-2h  now-8h  now-24h  now-7d   now-30d  now-1y
        head    2h      8h       24h       7d       delete
        RAM     disk    disk     disk      S3
                └compact┴compact─┴compact──┘
```

TTL-i keçmiş block silinir; compaction kiçik block-ları birləşdirir — daha az
index lookup, daha yaxşı kompresiya.

## Downsampling (Long-term storage)

`RAW 15s/30d → 5m/90d → 1h/2y`. Hər səviyyədə min/max/sum/count saxlanır
(`histogram_quantile` yenidən hesablansın). Thanos Compactor, Mimir, M3 bunu
edir. Trade-off: precision itkisi (15s spike 5m-də yumşalır), 50× az disk.

## Horizontal Scaling (Miqyaslandırma)

```
1. SHARDING — ingester ─hash(series_id)%N─▶ store1 | store2 | store3
              (Cortex, Mimir, VictoriaMetrics)

2. SINGLE NODE + S3 — Prom (2h head) ─sidecar─▶ S3 (all history)
                      Store Gateway ◀── Thanos Query

3. ACTIVE-ACTIVE HA — Prom A + Prom B → Thanos Query dedup by replica= label
```

Cortex/Mimir — push-based, multi-tenant, chunks S3. Thanos — sadə, tək cluster,
S3 əlavəsi. VictoriaMetrics — vmagent + vmstorage cluster, yüksək kompresiya.

## Pull vs Push Ingestion

```
PULL (Prometheus)             PUSH (InfluxDB, OTel, StatsD)
  server scrapes /metrics      client sends batch
  SD lazım, up=health          auth per push, serverless uyğun
  ephemeral → Pushgateway      client buffer on failure
```

OpenTelemetry OTLP **hər ikisini** bir pipeline-də dəstəkləyir.

## TSDB Engines (Mühərriklər)

| Engine           | Strong point                                   |
|------------------|------------------------------------------------|
| Prometheus TSDB  | Pull, PromQL, ecosystem (Go)                   |
| InfluxDB (TSM)   | Push, Flux, enterprise GUI (Go)                |
| TimescaleDB      | SQL, JOIN, Postgres ecosystem (C extension)    |
| VictoriaMetrics  | Compression, speed ~50× Prom (Go)              |
| ClickHouse       | Huge cardinality, analytics + TS (C++)         |
| M3DB             | Uber scale, multi-DC (Go)                      |
| Gorilla (FB)     | In-memory, paper (VLDB 2015) only              |

## Query Languages

```promql
# PromQL (Prometheus, Thanos, Mimir, VictoriaMetrics)
sum by (endpoint) (rate(http_requests_total{status=~"5.."}[5m]))
```

```sql
-- TimescaleDB (Postgres extension, time_bucket)
SELECT time_bucket('5m', ts) bucket, endpoint, count(*) FROM http_requests
WHERE ts > now() - interval '1h' AND status >= 500 GROUP BY bucket, endpoint;
```

InfluxDB Flux funksional pipeline (`from() |> range() |> filter() |>
aggregateWindow()`). PromQL industry standart oldu.

## Hot / Cold Tiered Storage

`2h RAM → 24h local SSD → 30d+ S3 parquet`. Son 1 saat = 95% dashboard traffic.
Historic data compliance + trend üçün S3-də; 5-10s latency qəbuledilən.

---

## Laravel: promphp ilə yazı (Laravel example)

`composer require promphp/prometheus_client_php`

### Counter və histogram

```php
// app/Metrics/AppMetrics.php
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis as RedisAdapter;

class AppMetrics
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $this->registry = new CollectorRegistry(
            new RedisAdapter(['host' => config('database.redis.default.host')])
        );
    }

    public function recordHttp(string $m, string $r, int $s, float $d): void
    {
        $this->registry->getOrRegisterCounter(
            'app', 'http_requests_total', 'Total', ['method','route','status']
        )->inc([$m, $r, (string)$s]);

        $this->registry->getOrRegisterHistogram(
            'app', 'http_request_duration_seconds', 'Latency', ['method','route'],
            [0.005, 0.01, 0.05, 0.1, 0.5, 1.0, 2.5, 5.0]
        )->observe($d, [$m, $r]);
    }

    public function registry(): CollectorRegistry { return $this->registry; }
}
```

### Middleware + scrape endpoint

```php
// app/Http/Middleware/TrackMetrics.php
public function handle(Request $request, Closure $next)
{
    $start = microtime(true);
    $response = $next($request);
    // ROUTE TEMPLATE — NOT raw URI (cardinality!)
    $route = optional($request->route())->uri() ?? 'unknown';
    $this->m->recordHttp($request->method(), $route,
        $response->getStatusCode(), microtime(true) - $start);
    return $response;
}

// routes/web.php — Prometheus pull endpoint
Route::get('/metrics', fn (AppMetrics $m) =>
    response((new \Prometheus\RenderTextFormat())
        ->render($m->registry()->getMetricFamilySamples()))
        ->header('Content-Type', \Prometheus\RenderTextFormat::MIME_TYPE)
);
```

Prometheus scrape config: `scrape_interval: 15s; static_configs: [targets:
['api.internal:8080']]`.

### Ephemeral batch job — Pushgateway

```php
// app/Jobs/NightlyReportJob.php — cron bitməmiş push lazım
$push = new \Prometheus\PushGateway('pushgw.internal:9091');
$c = $m->registry()->getOrRegisterCounter('batch', 'rows_total', 'Rows');
$c->incBy($this->processReports());
$push->push($m->registry(), 'nightly_report', ['instance' => gethostname()]);
```

---

## Cost Considerations (Xərc)

```
Cost = Cardinality × Retention × IngestionRate × Replication

Example: 500k series, 15s interval, 30d, ×2 replica, 1.3 B/sample Gorilla
  = 33,000 × 86400 × 30 × 1.3 × 2 ≈ 220 GB storage, ~6 GB RAM

Drayverlər: cardinality (ən təhlükəli) > retention > replication > rate.
```

---

## Trade-offs (Trade-offs)

| Yanaşma                 | Üstünlük                  | Çatışmazlıq             |
|-------------------------|---------------------------|-------------------------|
| Prometheus single-node  | Sadə, `up` metric         | ~10M series, no HA      |
| Thanos (Prom + S3)      | Ucuz uzun retention       | S3 query latency        |
| Cortex / Mimir cluster  | Multi-tenant, scale       | Operational complexity  |
| VictoriaMetrics         | Kompresiya + sürət        | Ekosistem kiçik         |
| TimescaleDB             | SQL, JOIN, PG tooling     | Yavaş kompresiya        |
| ClickHouse as TSDB      | High cardinality OK       | No native PromQL        |

**Standart:** orta scale — Prometheus + Thanos + S3; böyük — VictoriaMetrics/Mimir.

---

## Interview Q&A (Interview Q&A)

**1. Niyə LSM-tree TSDB üçün B-tree-dən yaxşıdır?**
Yazılar append-only və timestamp sıralı gəlir. LSM-də skip list + sıralı block
ardıcıl IO (SSD seq fast). B-tree random page split hər insert-də — bahalı.
Compaction batch background.

**2. Gorilla encoding niyə 10× kompresiya verir?**
Timestamp delta-of-delta çox sıfır; float64 XOR-da yaxın qiymət çox zero bit.
16 byte/sample → ~1.3 byte/sample (Facebook VLDB 2015).

**3. Nə üçün `user_id` label kimi saxlanmamalıdır?**
Cardinality = series × label-lar. 10M user × 5 status × 80 path = milyardlarla
series → RAM partladır, query bütün postings-i iterate edir. Per-user siqnal
üçün **log / trace** (Loki, Tempo), metric yox.

**4. Prometheus və TimescaleDB nə vaxt hansı?**
Prometheus — sırf metric, PromQL, `rate()`, pull. TimescaleDB — IoT/sensor,
SQL JOIN, ACID. Monitoring üçün Prometheus; relational time-series üçün
TimescaleDB.

**5. Downsampling necə işləyir?**
Raw 15s → 5m → 1h aggregate (min/max/sum/count saxlanır). 30d raw ≈ 1y 1h
həcmində; ~50× az disk. Trade-off: precision itkisi. Thanos Compactor avtomatik.

**6. Pull vs Push fərqi?**
Pull — server scrapes; SD lazım; `up` health; ephemeral üçün Pushgateway. Push
— client sends; serverless uyğun; auth hər push; fail-də səssizlik.
Long-lived → pull; batch/browser → push.

**7. Inverted index necə işləyir?**
Hər `label=value` üçün sorted postings (series_id array). `{job="api",
env="prod"}` iki list kəsişməsi (merge-sort, SIMD). Regex bütün dəyərləri
iterate edir — regex label az olmalı.

**8. Active-active HA-da dedup?**
Fərqli `replica="A"/"B"` label-ı ilə scrape. Thanos Query fan-out sonra
`replica` drop + `deduplicate` timestamp-lərdən birini götürür. VictoriaMetrics
`-dedup.minScrapeInterval` storage level-də edir.

---

## Best Practices (Best Practices)

- **Cardinality budget** — tətbiq başına < 10M series; alert on `_head_series`
- **Label bounded saxlayın** — `user_id`, `trace_id` log/trace-ə, metric YOX
- **Route template** — `/orders/{id}`, raw path YOX
- **Histogram bucket az** — 5-10 kifayət (hər bucket yeni series)
- **Recording rules** ilə ağır query-ləri precompute
- **Retention** — raw 15-30d, 5m/90d, 1h/1-2y
- **Remote write batching** — 1000+ sample/batch + snappy
- **WAL fsync** — 100ms default; durability vs throughput
- **Scrape interval konsistent** — qarışıq `rate()` pozur
- **Replica label** HA-da hər Prometheus-a (dedup)
- **Query timeout** — 30-60s server; Grafana panel 10s
- **Tenant isolation** — Mimir/Cortex per-tenant cardinality limit
- **Cold tier S3** — 30d sonra downsample + upload (Thanos Compactor)
- **Monitor TSDB özünü** — `_head_series`, `_compactions_total`,
  `_wal_corruptions_total`

---

## Əlaqəli mövzular (Related topics)

- [53 — Metrics & Monitoring](53-metrics-monitoring-design.md)
- [16 — Logging & Monitoring](16-logging-monitoring.md)
- [26 — Data Partitioning](26-data-partitioning.md) — series hash sharding
- [54 — Stream Processing](54-stream-processing.md) — real-time pre-TSDB
