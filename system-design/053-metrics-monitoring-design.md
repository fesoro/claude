# Metrics & Monitoring System Design (Senior)

## İcmal

Metrics & Monitoring System minlərlə service-dən ədədi ölçülər (CPU, latency, request
count) toplayıb, time-series database-də saxlayıb, dashboard və alert-lər üçün query
imkanı verən sistemdir. Prometheus, VictoriaMetrics, Datadog, InfluxDB bu sinifdəndir.

Sadə dillə: hər 15 saniyədə `cpu_usage=72`, `http_requests_total=18350` kimi rəqəmləri
yığır, sıxılmış şəkildə disk-də saxlayır, sonra `rate(http_requests_total[5m])` tipli
query-lərə cavab verir.

Bu fayl **sistemi necə qurmaq** haqqındadır. Daha ümumi observability konsepti üçün
bax: [16-logging-monitoring.md](16-logging-monitoring.md) (logs + metrics + traces birgə).

```
  Services         Collection        Storage (TSDB)       Consumption
 ┌────────┐       ┌──────────┐      ┌──────────────┐    ┌──────────┐
 │ /metrics│─────▶│ Scraper  │─────▶│ Head block   │───▶│ Grafana  │
 │ exporter│      │ (pull)   │      │ (RAM, 2h)    │    │ (dash)   │
 └────────┘       └──────────┘      │      ↓       │    └──────────┘
 ┌────────┐       ┌──────────┐      │ Compacted    │    ┌──────────┐
 │ push   │─────▶│ Gateway  │─────▶│ blocks       │───▶│ Alert    │
 │ client │      │ (StatsD) │      │ (disk, 2h→)  │    │ manager  │
 └────────┘       └──────────┘      └──────────────┘    └──────────┘
```


## Niyə Vacibdir

Prometheus-un TSDB arxitekturası yüksək cardinality problemindən əziyyət çəkir. Custom metrics sistemi dizaynı — ingestion, storage, query, alerting — observability pillar-larından birini başa düşdürür. Datadog, VictoriaMetrics — real tool-ların daxili işini anlamaq üçün vacibdir.

## Tələblər

1. **Metric types toplamaq** — counter (monoton artır), gauge (yuxarı/aşağı), histogram
   (bucket-lara bölünmüş distribution), summary (client-side quantile).
2. **Minlərlə service** — hər node bir neçə saniyədən bir yüzlərlə metric yayımlayır.
3. **Retention** — son N gün (adətən 15d raw, 1y downsampled) saxlamaq.
4. **Query engine** — dashboard və alert-lər üçün `sum by (endpoint) (rate(...))`
   tipli aggregation.
5. **Alerting** — rule pozulanda Slack/PagerDuty-ə bildiriş.
6. **Dashboarding** — Grafana-yə oxşar real-time vizualizasiya.

## Non-Functional Requirements

- **Write throughput:** 1M+ data points/sec (Netflix, Uber səviyyəsi).
- **Query latency:** p99 < 1s instant query; range query < 5s.
- **Durability:** WAL + replikasiya; bir node ölsə də data itməsin.
- **Horizontal scale:** shard by metric name / tenant.
- **Cost** — disk sıxılması effektiv olmalıdır (byte/sample < 2).
- **Availability** — monitoring özü monitored system-dən etibarlı olmalıdır
  ("who watches the watcher").

## Capacity Estimation (Back-of-Envelope)

```
10,000 service instances
× 200 metrics per instance (avg series)
= 2,000,000 active time series

Scrape interval: 15s
Samples/sec    = 2M / 15 = ~133K samples/sec
Samples/day    = 133K × 86400 = 11.5 billion samples/day

Raw sample  = 16 bytes (timestamp + float64)
Compressed  = ~1.3 bytes/sample (Gorilla encoding)

Storage/day = 11.5B × 1.3B = ~15 GB/day
15 days     = ~225 GB
1 year raw  = ~5.5 TB (needs downsampling)
```

## Push vs Pull Collection

```
PULL (Prometheus model)                PUSH (StatsD, OTel model)
                                       
  ┌────────┐  scrape    ┌─────────┐     ┌────────┐  push   ┌─────────┐
  │ server │──────────▶│/metrics │      │ client │────────▶│collector│
  │scraper │  HTTP GET  │endpoint │     │        │  UDP/   │         │
  └────────┘            └─────────┘     └────────┘  gRPC   └─────────┘
  server knows          targets          client knows
  the targets           are passive      the collector
```

**Pull üstünlükləri:** scrape = health check (`up` metric); client sadədir; debug
asan (`curl /metrics`). **Çətinlikləri:** service discovery lazımdır (Consul, K8s API);
ephemeral job-lar scrape olunmadan bitə bilər → **Pushgateway** lazım.

**Push üstünlükləri:** qısa-ömürlü job-lar (cron, batch); serverless/FaaS uyğundur;
client firewall arxasında ola bilər. **Çətinlikləri:** client fail olsa səssizlik;
authentication hər push-da lazımdır; collector throttle çətindir.

**Praktiki seçim:** uzun-ömürlü service-lər üçün **pull**, ephemeral və browser RUM
üçün **push**. OpenTelemetry Collector hər ikisini qəbul edir.

## Time-Series Data Model

Metric = **name** + **labels (tags)** + **timestamp** + **value (float64)**.

```
http_requests_total{method="GET", endpoint="/api/orders", status="200", pod="web-7"} 
  @ 1713441600 = 18350
  @ 1713441615 = 18392
  @ 1713441630 = 18421

 └─ metric name ─┘└──────────── labels ────────────┘ └─ ts ─┘ └─value┘
```

Hər unikal **(name, labels)** kombinasiyası ayrı **time series**-dir. Seriya bir
"chunk" içində ardıcıl timestamp-value cütlüklərini saxlayır.

## Cardinality Problemi (High Cardinality)

```
METRIC                              CARDINALITY
http_requests{status, method}       5 statuses × 5 methods = 25 (ok)
http_requests{...,endpoint}         + 50 endpoints         = 1,250 (still ok)
http_requests{...,user_id}          × 1M users  = 1.25B series (BOOM!)
http_requests{...,request_id}       × ∞ → memory OOM
```

**Qayda:** label dəyərlərinin sayı **bounded** olmalıdır. `user_id`, `session_id`,
`trace_id`, `email` LABEL OLMAMALIDIR — onlar log və trace-lərə aiddir.

**Cardinality control texnikaları:**
- **allowlist** — scrape config-də yalnız icazəli label-ləri saxla.
- **relabel_configs** — `drop`, `replace`, `labeldrop` qaydaları.
- **metric_relabel_configs** — scrape-dən sonra filtrasiya.
- **aggregation recording rules** — high-cardinality metrici əvvəlcədən aggregate et.

## Storage: Chunk-Based TSDB

Ardıcıl sample-lər bənzər olduğundan (timestamp +15s fərq, value yaxın) güclü
sıxılır. Prometheus Facebook-un **Gorilla encoding**-dən istifadə edir:

```
TIMESTAMP (delta-of-delta):
  raw:     1000, 1015, 1030, 1045, 1060
  delta:   1000,  +15,  +15,  +15,  +15
  delta-2: 1000,   0,    0,    0,    0   ← 0-lar 1 bit-lə yazılır

VALUE (XOR): v_n XOR v_(n-1) → yaxın float-larda çox sıfır;
yalnız "meaningful bits" saxlanılır.

Nəticə: 16 byte/sample → ~1.3 byte/sample (10x+ kompresiya)
```

## TSDB Architecture (Prometheus Daxili)

```
       WRITE PATH                                READ PATH
       ──────────                                ─────────
  scrape                                    PromQL query
    │                                            │
    ▼                                            ▼
  ┌────────────┐      ┌──────────────┐      ┌────────────┐
  │ WAL        │─────▶│ Head Block   │◀─────│ Query      │
  │ (append    │      │ (in-memory,  │      │ Engine     │
  │  log, fsyc)│      │  last 2h)    │      └────────────┘
  └────────────┘      └──────┬───────┘             ▲
                             │ compact             │
                             ▼                     │
                      ┌──────────────┐             │
                      │ Block 1 (2h) │◀────────────┤
                      ├──────────────┤             │
                      │ Block 2 (2h) │◀────────────┤
                      ├──────────────┤             │
                      │ ...          │             │
                      └──────────────┘             │
                             │                     │
                      ┌──────┴───────┐             │
                      │ Index        │─────────────┘
                      │ (postings)   │
                      └──────────────┘
```

**Komponentlər:**
- **WAL** — hər yazı disk-ə append; crash-dan sonra head replay olunur.
- **Head block** — son ~2 saat RAM-da, mmapped chunk-lar.
- **Compacted blocks** — head flush olunanda immutable 2-saatlıq block (chunks +
  index + meta.json).
- **Index (postings list)** — hər label dəyəri üçün seriya ID-ləri:
  `method="GET"` → `[12, 45, 99, ...]`. Query iki list-in intersection-ı (Lucene-ə bənzər).
- **Retention** — `meta.json`-dakı `maxTime` köhnədirsə, block silinir.

## Horizontal Scaling

Tək Prometheus ~1-10M active series həll edir. Daha böyük miqyas üçün:

```
  ┌─────────┐ ┌─────────┐ ┌─────────┐
  │Prom #1  │ │Prom #2  │ │Prom #3  │  ← shard (by job/region/team)
  │shard A  │ │shard B  │ │shard C  │
  └────┬────┘ └────┬────┘ └────┬────┘
       └───────────┼───────────┘
                   ▼
      Thanos Sidecar / Remote Write
                   ▼
      Object Store (S3)  +  Store Gateway  +  Compactor (downsampling)
                   ▼
      Thanos Query (global view, dedup) → Grafana
```

- **Thanos** — sidecar S3-ə upload edir; Query komponent global PromQL.
- **Cortex / Mimir** — push-based; chunks S3-də, index DynamoDB/BigTable-da.
- **VictoriaMetrics** — vmagent + vmstorage cluster; yüksək kompresiya.

## PromQL (Query Language)

İki vektor növü:

```
Instant vector:  http_requests_total{status="500"}        ← bir zaman nöqtəsi
Range vector:    http_requests_total{status="500"}[5m]    ← son 5 dəqiqə slice
```

Counter tez-tez artdığı üçün `rate()` lazımdır (per-second dərəcəsi):

```promql
# 5xx error RPS per endpoint
sum by (endpoint) (
  rate(http_requests_total{status=~"5.."}[5m])
)

# p99 latency (histogram üçün)
histogram_quantile(0.99,
  sum by (le) (rate(http_request_duration_seconds_bucket[5m]))
)

# Error ratio
sum(rate(http_requests_total{status=~"5.."}[5m]))
  /
sum(rate(http_requests_total[5m]))
```

`rate()` counter reset-lərini (restart) avtomatik görür və düzəldir.

## Downsampling (Long-Term Retention)

Raw 15s sample-ları 1 il saxlamaq bahalıdır. Downsampling pillələri:

```
Raw (15s resolution)  → 15 gün saxla
5m aggregates         → 90 gün  (min, max, avg, count, sum hər 5m)
1h aggregates         → 2 il
```

Thanos Compactor və VictoriaMetrics-in bu prosesi avtomatikdir. Query gələndə sistem
ən uyğun resolution-u seçir (1 həftə üçün 5m, 1 il üçün 1h).

## Alerting

```
  Prometheus             Alertmanager
  ┌────────────┐         ┌──────────────┐
  │ Evaluate   │ firing  │ Dedupe       │
  │ alert      │────────▶│ Group        │──┬──▶ PagerDuty
  │ rules 30s  │ alerts  │ Route        │  ├──▶ Slack
  │            │         │ Silence      │  └──▶ Email
  └────────────┘         └──────────────┘
```

**Alert rule misalı:**

```yaml
groups:
  - name: api_alerts
    interval: 30s
    rules:
      - alert: HighErrorRate
        expr: |
          sum(rate(http_requests_total{status=~"5.."}[5m]))
          / sum(rate(http_requests_total[5m])) > 0.05
        for: 10m     # 10m boyu true olmalıdır (flapping qorunsun)
        labels:
          severity: critical
        annotations:
          summary: "5xx error rate > 5% for 10m"
```

**Alertmanager məsuliyyətləri:** **dedupe** (3 HA Prometheus → 1 bildiriş), **grouping**
(50 pod → 1 notification), **silences** (maintenance window), **inhibition**
(cluster-down aktivdirsə pod-down alert-lərini boğ).

## High Availability

```
 ┌─────────────┐   ┌─────────────┐     ← iki eyni skraper, eyni target-lər
 │ Prom-A      │   │ Prom-B      │
 └──────┬──────┘   └──────┬──────┘
        │                 │
        ▼                 ▼
 ┌──────────────────────────────┐
 │ Thanos Query (deduplication) │     ← eyni seriya iki dəfə; external_labels
 └──────────────────────────────┘       ilə fərqləndirib birləşdirir

 ┌──────────────────────────────┐
 │ Alertmanager cluster (3 node)│     ← gossip protocol; dedupe paylaşılır
 └──────────────────────────────┘
```

## Laravel Misalı: Metrics Exposition

```bash
composer require promphp/prometheus_client_php
```

```php
// app/Http/Middleware/TrackHttpMetrics.php
public function handle(Request $request, Closure $next)
{
    $start = microtime(true);
    $response = $next($request);

    $counter = $this->registry->getOrRegisterCounter(
        'app', 'http_requests_total', 'Total HTTP requests',
        ['method', 'route', 'status']   // DIQQƏT: user_id YOX (cardinality)
    );
    $counter->inc([
        $request->method(),
        $request->route()?->getName() ?? 'unknown',
        (string) $response->status(),
    ]);

    $histogram = $this->registry->getOrRegisterHistogram(
        'app', 'http_request_duration_seconds', 'Request duration',
        ['route'],
        [0.005, 0.01, 0.05, 0.1, 0.5, 1, 2, 5]   // buckets
    );
    $histogram->observe(microtime(true) - $start, [
        $request->route()?->getName() ?? 'unknown',
    ]);
    return $response;
}
```

```php
// routes/web.php — Prometheus-un scrape edəcəyi endpoint
Route::get('/metrics', function (CollectorRegistry $registry) {
    $renderer = new \Prometheus\RenderTextFormat();
    return response(
        $renderer->render($registry->getMetricFamilySamples()),
        200, ['Content-Type' => RenderTextFormat::MIME_TYPE]
    );
});

// Custom domain counter
$orderCounter = $registry->getOrRegisterCounter(
    'shop', 'orders_placed_total', 'Orders successfully placed',
    ['payment_method', 'currency']
);
$orderCounter->inc(['stripe', 'USD']);
```

```yaml
# prometheus.yml scrape config
scrape_configs:
  - job_name: 'laravel-api'
    metrics_path: /metrics
    scrape_interval: 15s
    kubernetes_sd_configs:
      - role: pod
    relabel_configs:
      - source_labels: [__meta_kubernetes_pod_label_app]
        regex: laravel-api
        action: keep
```

**Nginx exporter** — `nginx-prometheus-exporter` sidecar container kimi qoşulur,
`stub_status` oxuyub `/metrics` kimi yayır.

## Metrics vs Traces vs Logs (Three Pillars)

```
METRICS                 TRACES                   LOGS
─────────               ──────                   ────
aggregates (counts,     causal chain of          discrete events with
percentiles)            one request              context
                        
low cardinality         high cardinality         high cardinality
                        (trace_id, span_id)      (any field)
                        
"how many?"             "where is the            "what happened at
"how fast?"             slowness?"               exactly 10:23:45?"
                        
Prometheus,             Jaeger, Tempo,           Loki, Elasticsearch
VictoriaMetrics         Zipkin, OTel             (ELK stack)
                        
cheap (bytes/sample)    expensive (sample        expensive (full-text
                        1-5%)                    index)
```

Praktikada: dashboard metrics üzərində, incident debug-ı trace + log ilə aparılır.
Exemplars ilə metric-dən trace-ə linkləmək olur (Grafana exemplars feature).

## Praktik Tapşırıqlar

**Q1: Nəyə görə pull model push-dan daha çox istifadə olunur uzun-ömürlü service-lər üçün?**
Pull model "target up/down" bilgisini pulsuz verir (scrape fail = `up=0`). Service
discovery mərkəzidir, hər client-də push endpoint konfiqurasiyası olmur. Debug asan:
`curl service:9090/metrics` nəticəni göstərir. Lakin ephemeral job-lar üçün pull
yaramır; orada Pushgateway və ya OpenTelemetry push protokolu daha yaxşıdır.

**Q2: High-cardinality problemi nədir və necə qarşısını alırsan?**
Hər unikal label kombinasiyası ayrı time series-dir. `user_id` və ya `request_id`-ni
label etsək, milyonlarla seriya yaranır → RAM OOM, query yavaş. Həlli: label set-i
əvvəlcədən planla, `relabel_configs` ilə problematik label-ləri `action: labeldrop`
ilə at, yüksək dəyər user-id məlumatını traces/logs-a at (metric-də yox).

**Q3: Counter reset-i necə həll olunur?**
Counter monotondur, amma pod restart olanda 0-a qayıdır. PromQL-də `rate()` funksiyası
sample-lər arasında azalma görəndə "reset" aşkarlayır və həmin nöqtəni atıb növbəti
sample-dan davam edir. Bu sayılmayan nöqtə bir scrape interval-dən kiçikdir, nəticə
praktiki olaraq düzgün qalır.

**Q4: Uzun-müddətli saxlama (1 il+) üçün hansı memarlıq?**
Tək Prometheus instance lokal disk-də 15 gün saxlayır. Uzun-müddət üçün Thanos və ya
Mimir: sidecar 2-saatlıq blok-ları S3-ə upload edir, Compactor komponent downsample
edir (1m, 1h resolution). Query layer raw + downsampled-i birləşdirir. Disk yerinə
obyekt storage istifadə olunduğu üçün qiymət kəskin azalır.

**Q5: Alertmanager niyə Prometheus-dan ayrıdır?**
Separation of concerns: Prometheus ancaq query engine-dir; alert routing, grouping,
silencing, dedupe başqa məsələlərdir. Bu həmçinin HA verir — 2-3 Prometheus eyni
rule-ları qiymətləndirir, Alertmanager cluster gossip ilə dedupe edir ki, eyni alert
bir dəfə Slack-ə gedsin.

**Q6: Histogram vs summary fərqi nədir?**
**Histogram** bucket-lara sayğac yazır (`le=0.1, le=0.5, le=1, ...`); server tərəfdə
`histogram_quantile()` ilə p99 hesablanır. Aggregation friendly — müxtəlif
instance-lərdən toplana bilər. **Summary** client tərəfdə presampled quantile saxlayır;
dəqiqdir, amma aggregate oluna bilmir (p99-ların p99-u düzgün deyil). Modern system-lər
histogram üstünlük verir, xüsusən Prometheus-da.

**Q7: 10M active series-li bir sistemdə latency necə aşağı saxlanır?**
(a) Index postings inverted list-dir, intersection O(min(list_a, list_b)). (b) Head
block RAM-da, son 2 saat query üçün instant. (c) Block-based format query vaxtı
yalnız uyğun time range-i oxuyur. (d) Chunk cache, postings cache. (e) Horizontal
shard: metric name və ya tenant üzrə fərqli Prometheus. (f) Recording rules ilə
bahalı aggregation-ları əvvəlcədən hesabla.

**Q8: Metric sistemi özü monitored sistemdən ayrı olmalıdır, niyə?**
Bir cluster çökərsə, həmin cluster-dəki Prometheus da çökür və incident-i görə
bilmirsən. Buna görə "meta-monitoring" aparılır: ayrı bir Prometheus (başqa region
və ya provider) əsas Prometheus-un `up` və `scrape_duration` metriklərini skrape
edir. Dead man's switch alert — məlum "həmişə firing" alert-i hər dəqiqə gəlməsə,
monitoring stack özü down deməkdir.


## Əlaqəli Mövzular

- [SLA/SLO/SLI](44-sla-slo-sli.md) — SLI-ın metrics sistemi üzərindən ölçülməsi
- [Logging & Monitoring](16-logging-monitoring.md) — üç pillar birlikdə
- [Distributed Tracing](91-distributed-tracing-deep-dive.md) — üçüncü observability pillar
- [Time-Series DB](66-time-series-database.md) — metrics storage arxitekturası
- [Chaos Engineering](56-chaos-engineering.md) — monitoring ilə chaos sınaqları
