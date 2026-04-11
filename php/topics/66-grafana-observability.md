# Grafana Observability — Metrics, Logs, Traces

## Mündəricat
1. [Observability nədir?](#observability-nədir)
2. [Prometheus — Metrics](#prometheus--metrics)
3. [Grafana — Dashboards](#grafana--dashboards)
4. [Loki — Logs](#loki--logs)
5. [Tempo — Distributed Tracing](#tempo--distributed-tracing)
6. [RED Method](#red-method)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [Alerting](#alerting)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Observability nədir?

```
// Bu kod observability-nin üç sütununu (metrics, logs, traces) izah edir
3 Pillar:

┌─────────────────────────────────────────────────────┐
│                   Observability                     │
│                                                     │
│  ┌──────────┐   ┌──────────┐   ┌────────────────┐  │
│  │ Metrics  │   │  Logs    │   │    Traces      │  │
│  │          │   │          │   │                │  │
│  │ "Nə baş  │   │ "Nə baş  │   │ "Sorğu haradan │  │
│  │  verdi?" │   │  verdi?" │   │  keçdi?"       │  │
│  │ (ədədi)  │   │(detailed)│   │  (end-to-end)  │  │
│  └──────────┘   └──────────┘   └────────────────┘  │
│  Prometheus      Loki/ELK       Jaeger/Tempo         │
│  Grafana         Grafana Loki   Grafana Tempo         │
└─────────────────────────────────────────────────────┘

Monitoring vs Observability:
  Monitoring: "Nə baş verdi?" — öncədən bilinen suallar
  Observability: "Niyə baş verdi?" — naməlum suallar
```

---

## Prometheus — Metrics

```
// Bu kod Prometheus pull model arxitekturasını, metric növlərini və PromQL sorğularını göstərir
Pull model: Prometheus öz özünə scrape edir

┌────────────────┐          ┌──────────────────┐
│  PHP App       │          │   Prometheus     │
│  /metrics      │◄─scrape──│  (her 15s)       │
│  endpoint      │          │                  │
└────────────────┘          └────────┬─────────┘
                                     │
                             ┌───────▼────────┐
                             │    Grafana     │
                             │  (dashboard)   │
                             └────────────────┘

Metric növləri:
  Counter:   Yalnız artır (requests_total, errors_total)
  Gauge:     Artıb-azala bilər (memory_usage, active_connections)
  Histogram: Dəyər paylanması (request_duration_seconds)
  Summary:   Quantile hesablamaları (p50, p95, p99)

PromQL (Prometheus Query Language):
  # Request rate (son 5 dəqiqə)
  rate(http_requests_total[5m])
  
  # Error rate
  rate(http_requests_total{status=~"5.."}[5m]) / rate(http_requests_total[5m])
  
  # P95 latency
  histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))
  
  # Memory usage per service
  process_resident_memory_bytes{job="php-app"}
```

---

## Grafana — Dashboards

```
// Bu kod Grafana-nın data source-ları, panel növlərini və dashboard best practice-lərini göstərir
Grafana data source-lardan data çəkib vizuallaşdırır:

Data Sources:
  Prometheus (metrics)
  Loki (logs)
  Tempo (traces)
  MySQL/PostgreSQL (business metrics)
  Elasticsearch
  CloudWatch, Datadog, ...

Panel növləri:
  Time series: zaman üzrə xətt qrafiki
  Gauge: tək dəyər (CPU usage %)
  Stat: big number (total requests)
  Table: cədvəl
  Heatmap: latency distribution
  Logs: log stream

Dashboard best practices:
  USE Method (Resources): Utilization, Saturation, Errors
  RED Method (Services): Rate, Errors, Duration
  
  Row 1: Overview (health, uptime)
  Row 2: Traffic (requests/s, error rate)
  Row 3: Performance (latency p50/p95/p99)
  Row 4: Infrastructure (CPU, Memory, Disk)
  Row 5: Business metrics (orders/s, revenue)
```

---

## Loki — Logs

```
// Bu kod Loki-nin log aggregation arxitekturasını və LogQL sorğu nümunələrini göstərir
Log aggregation:
  Promtail (log collector) → Loki → Grafana

Loki fərqi (Elasticsearch-dən):
  Elasticsearch: log məzmununu index edir (baha)
  Loki: yalnız label-ları index edir, məzmun compressed
  Daha ucuz, amma full-text search zəifdir

Labels:
  {app="php-app", env="production", level="error"}

LogQL:
  # Error log-lar
  {app="php-app"} |= "ERROR"
  
  # JSON parse + filter
  {app="php-app"} | json | level="error" | response_time > 1000
  
  # Log rate
  rate({app="php-app", level="error"}[5m])
  
  # Grep + metrics
  sum(rate({app="php-app"} |= "OrderPlaced" [5m])) by (env)
```

---

## Tempo — Distributed Tracing

```
// Bu kod Tempo-nun distributed trace span strukturunu və Metrics ilə əlaqəsini göstərir
Distributed trace:
  Hər sorğuya unique Trace ID
  Hər addım Span

  GET /api/orders/123
  │
  ├── Span: HTTP Request [0ms - 145ms]
  │   ├── Span: Auth middleware [0ms - 5ms]
  │   ├── Span: Order::find() [5ms - 45ms]
  │   │   └── Span: MySQL SELECT [6ms - 44ms]
  │   ├── Span: Inventory check [45ms - 95ms]
  │   │   └── Span: HTTP inventory-service [46ms - 94ms]
  │   └── Span: Response serialize [95ms - 145ms]

Exemplars (Metrics ↔ Traces link):
  Grafana histogram-da slow request-ə tıkla
  → Trace ID görürsən
  → Tempo-da tam trace görürsən
  → Hansı span yavaşdır? → Loki-də o span-ın log-ları
  
OpenTelemetry:
  Vendor-neutral standard
  PHP: open-telemetry/opentelemetry-php
```

---

## RED Method

```
// Bu kod RED metodunu (Rate, Errors, Duration) və SLO/SLI/SLA anlayışlarını izah edir
Services üçün 3 əsas metric:

R — Rate:     Neçə sorğu/saniyə
E — Errors:   Neçə sorğu xəta ilə bitir (%)
D — Duration: Sorğuların nə qədər çəkir (p50, p95, p99)

Grafana dashboard:
  Rate:     rate(http_requests_total[5m])
  Errors:   rate(http_requests_total{code=~"5.."}[5m])
            / rate(http_requests_total[5m]) * 100
  Duration: histogram_quantile(0.99, 
              rate(http_request_duration_seconds_bucket[5m]))

USE Method (Infrastructure):
  U — Utilization: CPU/Memory/Disk istifadəsi (%)
  S — Saturation:  Queue uzunluğu, wait time
  E — Errors:      Error rate

SLO/SLI/SLA:
  SLI (Indicator): P99 latency = 250ms
  SLO (Objective): P99 < 300ms, 99.9% vaxt
  SLA (Agreement): SLO pozularsa penalty
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod PHP-də Prometheus metric collector xidməti və middleware ilə request metrikalarını göstərir
// Prometheus metrics — promphp/prometheus_client_php

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use Prometheus\RenderTextFormat;

class MetricsService
{
    private CollectorRegistry $registry;
    
    public function __construct()
    {
        $this->registry = new CollectorRegistry(new Redis([
            'host' => config('redis.default.host'),
        ]));
    }
    
    public function incrementHttpRequests(string $method, string $path, int $status): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'php_app',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'path', 'status']
        );
        $counter->inc([$method, $path, (string) $status]);
    }
    
    public function recordRequestDuration(string $path, float $durationSeconds): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            'php_app',
            'http_request_duration_seconds',
            'HTTP request duration',
            ['path'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5]
        );
        $histogram->observe($durationSeconds, [$path]);
    }
    
    public function setActiveConnections(int $count): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            'php_app',
            'active_connections',
            'Current active connections'
        );
        $gauge->set($count);
    }
    
    public function renderMetrics(): string
    {
        $renderer = new RenderTextFormat();
        return $renderer->render($this->registry->getMetricFamilySamples());
    }
}

// Laravel Middleware — otomatik request metrics
class PrometheusMiddleware
{
    public function __construct(private MetricsService $metrics) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $start;
        $path = $this->normalizePath($request->path());
        
        $this->metrics->incrementHttpRequests(
            $request->method(),
            $path,
            $response->getStatusCode()
        );
        
        $this->metrics->recordRequestDuration($path, $durationSeconds: $duration);
        
        return $response;
    }
    
    private function normalizePath(string $path): string
    {
        // /api/orders/123 → /api/orders/{id}
        return preg_replace('/\/\d+/', '/{id}', "/$path");
    }
}

// /metrics endpoint
Route::get('/metrics', function (MetricsService $metrics) {
    return response($metrics->renderMetrics(), 200, [
        'Content-Type' => RenderTextFormat::MIME_TYPE,
    ]);
})->middleware('auth.metrics');  // Prometheus scrape auth
```

---

## Alerting

*Alerting üçün kod nümunəsi:*
```yaml
# Bu kod yüksək error rate, latency və queue backlog üçün Prometheus alerting qaydalarını göstərir
# prometheus/alerts.yml
groups:
  - name: php-app
    rules:
      - alert: HighErrorRate
        expr: |
          rate(http_requests_total{status=~"5.."}[5m])
          / rate(http_requests_total[5m]) > 0.05
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "High error rate: {{ $value | humanizePercentage }}"
          
      - alert: HighLatency
        expr: |
          histogram_quantile(0.99,
            rate(http_request_duration_seconds_bucket[5m])) > 1
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "P99 latency > 1s: {{ $value }}s"
          
      - alert: QueueBacklog
        expr: redis_list_length{key="queue:default"} > 10000
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Queue backlog: {{ $value }} jobs"
```

---

## İntervyu Sualları

**1. Observability-nin 3 pilları nədir?**
Metrics (Prometheus): ədədi, aggregated — "neçə sorğu xəta verdi?". Logs (Loki): detailed events — "hansı sorğu, hansı error?". Traces (Tempo): end-to-end request flow — "sorğu hansı servislərdən keçdi, hansı yavaş idi?".

**2. RED method nədir?**
Services üçün əsas 3 metric: Rate (sorğu/saniyə), Errors (xəta faizi), Duration (latency p50/p95/p99). Bu üçü normal olduqda servis sağlamdır. Grafana dashboardunun əsasını təşkil edir.

**3. Prometheus pull model nədir?**
Prometheus özü aplikasiyanın `/metrics` endpoint-ini scrape edir (hər 15s). Push model (app Prometheus-a göndərir) əvəzinə. Üstünlük: centralized config, scrape failure monitoring, app sadədir. Məhdudiyyət: short-lived jobs üçün Pushgateway lazımdır.

**4. Loki niyə Elasticsearch-dən ucuzdur?**
Elasticsearch log məzmununu tamamilə index edir — storage baha. Loki yalnız label-ları index edir, məzmun compressed blob-da saxlanılır. Amma full-text search Elasticsearch-dən zəifdir. Structured logging + label-based search üçün Loki yetərlidir.

**5. SLI, SLO, SLA fərqi nədir?**
SLI (Service Level Indicator): ölçülən metric (P99 latency = 200ms). SLO (Service Level Objective): hədəf (P99 < 300ms, 99.9% vaxt). SLA (Service Level Agreement): müqavilə — SLO pozularsa customer-a compensation. SLO daxili hədəf, SLA xarici öhdəlikdir.

**6. Error budget nədir?**
SLO-nun icazə verdiyi "pis" vaxt miqdarı. Məsələn SLO = 99.9% → ayda 43.8 dəq downtime icazəsi. Yeni feature deploy etmək bu budgeti azaldır. Error budget tükənibsə deployment dondurulur, reliability üzərindən iş görülür. SRE (Site Reliability Engineering) mərkəzi konsepti.

**7. Cardinality problemi nədir?**
Prometheus metric label-larının dəyər sayı. `http_requests{user_id="12345"}` — hər user üçün ayrı time series yaranır → milyonlarla series → Prometheus memory-si partlayır. High-cardinality label-lar (user_id, order_id) metric-ə əlavə etmə; bunları trace/log-da saxla.

**8. OpenTelemetry nədir?**
Vendor-neutral observability standartı — metrics, logs, traces üçün bir SDK. PHP: `open-telemetry/opentelemetry-php`. Bir dəfə instrument et, sonra Jaeger/Zipkin/Tempo/Datadog-a export et. Auto-instrumentation: Laravel-da HTTP, DB, Redis sorğuları avtomatik trace edilir.

---

## Anti-patternlər

**1. Hər şeyi alert etmək**
Hər kiçik metrik üçün alert qurmaq — alert fatigue yaranır, komanda alert-ləri ignore etməyə başlayır, kritik problem bildirişi kütləyə qarışır. Yalnız istifadəçiyə birbaşa təsir edən hadisələri alert edin: RED metrics (error rate, latency p99, saturation); qalan-lar dashboard-da görünür olsun.

**2. Yalnız average latency ölçmək**
`AVG(response_time)` metrikini izləmək — average yaxşı görünür, lakin p99 istifadəçilərin 1%-i üçün dəhşətli ola bilər. Percentile-lar üzərindən izləyin: p50, p95, p99; "tail latency" problem əlamətini average gizlədir.

**3. Log-ları structured olmayan free-text kimi yazmaq**
`Log::info("User 123 logged in at 14:32")` — Loki-də label-la filter etmək mümkün deyil, full-text axtarış lazım olur. Structured logging istifadə edin: `Log::info("user.login", ['user_id' => 123, 'ip' => ...])` — label-lara çevrilir, Loki-də effektiv filter mümkün olur.

**4. Distributed trace olmadan mikroservis debug etmək**
Servislərin log-larına ayrı-ayrı baxmaq — hansı servisin nə qədər vaxt aldığını, xətanın harada yarandığını anlamaq çox çətin olur. Trace ID-ni bütün servislərdə HTTP header ilə ötürün (`X-Trace-Id`); Tempo ilə end-to-end request flow-u vizuallaşdırın.

**5. Monitoring-i deploy-dan sonra qurmaq**
Sistem production-a çıxır, monitoring sonraya saxlanılır — ilk problem anında nə baş verdiyini anlamaq üçün heç bir data yoxdur. Observability pipeline-ı (Prometheus scrape, Loki agent, Tempo) deployment ilə birlikdə qurun; "Day 1" monitoring olmadan production-a çıxmayın.

**6. SLO-suz dashboard qurmaq**
Onlarca metric izlənilir, lakin "normal" nədir bilinmir — anomaliya göründükdə bunun problem olub olmadığını müəyyən etmək çətindir. Əvvəlcə SLO-ları müəyyən edin (p99 < 300ms, error rate < 0.1%); dashboard-lar bu hədəflərə nisbətən vizuallaşdırılsın, error budget izlənilsin.
