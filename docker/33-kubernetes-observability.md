# Kubernetes Observability (Prometheus, Grafana, Loki, Tempo, OpenTelemetry)

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

**Observability** — sistemin daxili vəziyyətini xarici sinal-lardan (metrics, logs, traces) anlamaq qabiliyyətidir. "Three Pillars of Observability":

1. **Metrics** — rəqəmsal zaman seriyası (CPU, RPS, error rate)
2. **Logs** — event-lərin strukturlu qeydi
3. **Traces** — request-in mikroservislərdən keçdiyi yol

K8s-də observability stack-i adətən:
- **PLG**: Prometheus + Loki + Grafana
- **EFK**: Elasticsearch + Fluentd + Kibana
- **Tracing**: Tempo, Jaeger, Zipkin
- **OpenTelemetry**: vendor-neutral standard

## Əsas Konseptlər

### 1. RED və USE Method

**RED (Request-oriented)**:
- **R**ate — request/saniyə
- **E**rrors — error sayı/faiz
- **D**uration — response vaxtı

**USE (Resource-oriented)**:
- **U**tilization — nə qədər istifadə olunur
- **S**aturation — növbə uzunluğu
- **E**rrors — error sayı

### 2. Metrics Pipeline

```
┌─ App/Node/Pod ──────────┐
│  /metrics endpoint       │
│  (Prometheus format)     │
└────────┬─────────────────┘
         │ scrape
         ▼
┌─ Prometheus Server ─────┐
│  - Scraping              │
│  - Storage (TSDB)        │
│  - PromQL queries        │
│  - Alert rules           │
└────────┬─────────────────┘
         │ query
         ▼
┌─ Grafana ───────────────┐
│  - Dashboards            │
│  - Visualization         │
└──────────────────────────┘
```

### 3. Observability Stack Müqayisəsi

| Stack | Metrics | Logs | Tracing | Resource |
|-------|---------|------|---------|----------|
| PLG + Tempo | Prometheus | Loki | Tempo | Az |
| EFK | Prometheus | Elasticsearch | Jaeger | Çox |
| Datadog | Datadog | Datadog | Datadog | SaaS ($$$) |
| New Relic | SaaS | SaaS | SaaS | SaaS |

## Prometheus

### 1. kube-prometheus-stack Install

```bash
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm install kube-prometheus-stack prometheus-community/kube-prometheus-stack \
    --namespace monitoring \
    --create-namespace \
    --set prometheus.prometheusSpec.retention=15d \
    --set prometheus.prometheusSpec.storageSpec.volumeClaimTemplate.spec.resources.requests.storage=50Gi
```

Daxil olan komponentlər:
- **Prometheus** — metric server
- **Alertmanager** — alert routing
- **Grafana** — dashboard
- **node-exporter** — node metric
- **kube-state-metrics** — K8s object metric
- **Prometheus Operator** — CRD idarəsi

### 2. ServiceMonitor (Laravel app)

```yaml
# Laravel Service
apiVersion: v1
kind: Service
metadata:
  name: laravel-metrics
  namespace: production
  labels:
    app: laravel
spec:
  selector:
    app: laravel
  ports:
    - name: metrics
      port: 9400
      targetPort: 9400
---
# ServiceMonitor — Prometheus-a scrape et deyir
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: laravel
  namespace: production
  labels:
    release: kube-prometheus-stack   # Prometheus bu label-ə uyğun ServiceMonitor-ları götürür
spec:
  selector:
    matchLabels:
      app: laravel
  endpoints:
    - port: metrics
      interval: 15s
      path: /metrics
      scheme: http
      scrapeTimeout: 10s
```

### 3. PodMonitor

Service olmayan Pod-lar üçün (məs. DaemonSet):

```yaml
apiVersion: monitoring.coreos.com/v1
kind: PodMonitor
metadata:
  name: node-exporter
  namespace: monitoring
spec:
  selector:
    matchLabels:
      app: node-exporter
  podMetricsEndpoints:
    - port: metrics
      interval: 30s
      path: /metrics
```

### 4. PrometheusRule (Alerts)

```yaml
apiVersion: monitoring.coreos.com/v1
kind: PrometheusRule
metadata:
  name: laravel-alerts
  namespace: production
  labels:
    release: kube-prometheus-stack
spec:
  groups:
    - name: laravel
      interval: 30s
      rules:
        # High error rate
        - alert: LaravelHighErrorRate
          expr: |
            sum(rate(http_requests_total{app="laravel",status=~"5.."}[5m]))
            / sum(rate(http_requests_total{app="laravel"}[5m]))
            > 0.05
          for: 5m
          labels:
            severity: warning
            team: backend
          annotations:
            summary: "Laravel 5xx error rate > 5% (current: {{ $value | humanizePercentage }})"
            runbook_url: "https://wiki.example.com/runbooks/laravel-5xx"

        # High p99 latency
        - alert: LaravelHighLatency
          expr: |
            histogram_quantile(0.99,
              sum(rate(http_request_duration_seconds_bucket{app="laravel"}[5m]))
              by (le)
            ) > 2
          for: 10m
          labels:
            severity: warning
          annotations:
            summary: "Laravel p99 latency > 2s"

        # Pod crashloop
        - alert: LaravelPodCrashLooping
          expr: rate(kube_pod_container_status_restarts_total{namespace="production",pod=~"laravel-.*"}[5m]) > 0
          for: 5m
          labels:
            severity: critical
          annotations:
            summary: "Pod {{ $labels.pod }} is crash looping"
```

### 5. PromQL Basics

```promql
# CPU istifadəsi per pod
sum(rate(container_cpu_usage_seconds_total{namespace="production"}[5m])) by (pod)

# Memory usage
container_memory_working_set_bytes{namespace="production"} / (1024 * 1024 * 1024)

# RPS
sum(rate(http_requests_total{app="laravel"}[1m]))

# Error rate
sum(rate(http_requests_total{status=~"5.."}[5m]))
/ sum(rate(http_requests_total[5m]))

# p95 latency
histogram_quantile(0.95, sum(rate(http_request_duration_seconds_bucket[5m])) by (le))

# Pod restart
increase(kube_pod_container_status_restarts_total[1h]) > 0
```

## Grafana

### 1. Dashboard as Code

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-dashboard
  namespace: monitoring
  labels:
    grafana_dashboard: "1"    # Grafana auto-discovery
data:
  laravel.json: |
    {
      "title": "Laravel RED Metrics",
      "panels": [
        {
          "title": "Request Rate",
          "targets": [
            {"expr": "sum(rate(http_requests_total{app=\"laravel\"}[1m]))"}
          ]
        },
        {
          "title": "Error Rate",
          "targets": [
            {"expr": "sum(rate(http_requests_total{app=\"laravel\",status=~\"5..\"}[5m]))"}
          ]
        },
        {
          "title": "p95 Latency",
          "targets": [
            {"expr": "histogram_quantile(0.95, sum(rate(http_request_duration_seconds_bucket{app=\"laravel\"}[5m])) by (le))"}
          ]
        }
      ]
    }
```

### 2. Populyar Dashboard-lar

Grafana.com-dan import:
- **Node Exporter Full** (1860)
- **Kubernetes / Compute Resources / Namespace (Pods)** (15758)
- **PHP-FPM** (4158)
- **MySQL Overview** (7362)
- **Redis** (11835)

## Loki (Logs)

### 1. Architecture

```
App log → Promtail (agent) → Loki → Grafana
```

Loki Prometheus-a bənzəyir amma log-lar üçün. Log body-ni indexləmir, yalnız label-ları — bu səbəbdən ucuzdur.

### 2. Install

```bash
helm install loki grafana/loki-stack \
    --namespace monitoring \
    --set promtail.enabled=true \
    --set grafana.enabled=false   # grafana artıq var
```

### 3. Promtail Config

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: promtail-config
data:
  promtail.yaml: |
    server:
      http_listen_port: 9080

    clients:
      - url: http://loki:3100/loki/api/v1/push

    scrape_configs:
      - job_name: kubernetes-pods
        kubernetes_sd_configs:
          - role: pod
        relabel_configs:
          - source_labels: [__meta_kubernetes_namespace]
            target_label: namespace
          - source_labels: [__meta_kubernetes_pod_name]
            target_label: pod
          - source_labels: [__meta_kubernetes_pod_label_app]
            target_label: app
        pipeline_stages:
          - cri: {}   # containerd log format
          - json:
              expressions:
                level: level
                message: message
          - labels:
              level:
```

### 4. LogQL

Prometheus-a bənzər sorğu dili:

```logql
# Bütün Laravel log-ları
{app="laravel"}

# Yalnız error-lar
{app="laravel"} |= "ERROR"

# JSON parse + filter
{app="laravel"} | json | level="error"

# Pattern extract
{app="laravel"} | pattern `<ip> - - [<timestamp>] "<method> <path>" <status>` | status >= 500

# Rate
sum(rate({app="laravel", level="error"}[5m])) by (namespace)

# Per pod log volume
sum(rate({namespace="production"}[5m])) by (pod)
```

### 5. Loki vs Elasticsearch

| | Loki | Elasticsearch |
|-|------|---------------|
| Index | Yalnız metadata | Full text |
| Storage | S3 + Bolt | Local + S3 |
| Query | LogQL | DSL / KQL |
| Cost | Aşağı | Yüksək |
| Speed | Orta | Yüksək (indexed) |

Yüksək həcmdə log-da Loki çox daha ucuzdur.

## Tempo və Jaeger (Tracing)

### 1. Distributed Tracing Nə Verir

Mikroservislər arası request yolunu göstərir:

```
Request → laravel-web (50ms)
         └─ laravel-auth (10ms)
         └─ laravel-users (100ms)
             └─ mysql (80ms)
         └─ laravel-orders (200ms)  ← bottleneck
             └─ redis (5ms)
             └─ external-api (180ms)
```

### 2. Tempo Install

```bash
helm install tempo grafana/tempo \
    --namespace monitoring \
    --set tempo.storage.trace.backend=s3 \
    --set tempo.storage.trace.s3.bucket=traces
```

### 3. OpenTelemetry Collector

Trace-ləri toplayıb Tempo-ya göndərir:

```yaml
apiVersion: opentelemetry.io/v1beta1
kind: OpenTelemetryCollector
metadata:
  name: otel-collector
  namespace: monitoring
spec:
  mode: deployment
  config:
    receivers:
      otlp:
        protocols:
          grpc:
            endpoint: 0.0.0.0:4317
          http:
            endpoint: 0.0.0.0:4318

    processors:
      batch:
        timeout: 10s
      memory_limiter:
        check_interval: 1s
        limit_mib: 1000

    exporters:
      otlp/tempo:
        endpoint: tempo:4317
        tls:
          insecure: true
      prometheus:
        endpoint: 0.0.0.0:9090
      loki:
        endpoint: http://loki:3100/loki/api/v1/push

    service:
      pipelines:
        traces:
          receivers: [otlp]
          processors: [memory_limiter, batch]
          exporters: [otlp/tempo]
        metrics:
          receivers: [otlp]
          exporters: [prometheus]
        logs:
          receivers: [otlp]
          exporters: [loki]
```

### 4. Laravel OpenTelemetry SDK

```bash
composer require open-telemetry/opentelemetry-auto-laravel
```

```php
// config/otel.php
return [
    'enabled' => env('OTEL_ENABLED', true),
    'service_name' => env('OTEL_SERVICE_NAME', 'laravel'),
    'exporter' => env('OTEL_EXPORTER', 'otlp'),
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318'),
];
```

Pod env:
```yaml
env:
  - name: OTEL_SERVICE_NAME
    value: laravel-api
  - name: OTEL_EXPORTER_OTLP_ENDPOINT
    value: http://otel-collector.monitoring:4318
  - name: OTEL_TRACES_SAMPLER
    value: parentbased_traceidratio
  - name: OTEL_TRACES_SAMPLER_ARG
    value: "0.1"    # 10% sampling
```

### 5. Jaeger (Alternativ)

```bash
kubectl apply -f https://github.com/jaegertracing/jaeger-operator/releases/latest/download/jaeger-operator.yaml
```

```yaml
apiVersion: jaegertracing.io/v1
kind: Jaeger
metadata:
  name: jaeger
  namespace: monitoring
spec:
  strategy: production
  storage:
    type: elasticsearch
    options:
      es:
        server-urls: http://elasticsearch:9200
```

## OpenTelemetry Operator

### 1. Auto-Instrumentation

OpenTelemetry Operator pod-a avto SDK inject edə bilər — kod dəyişməz:

```yaml
apiVersion: opentelemetry.io/v1alpha1
kind: Instrumentation
metadata:
  name: my-instrumentation
  namespace: production
spec:
  exporter:
    endpoint: http://otel-collector:4318
  propagators:
    - tracecontext
    - baggage
  sampler:
    type: parentbased_traceidratio
    argument: "0.1"
  java:
    image: otel/autoinstrumentation-java:latest
  python:
    image: otel/autoinstrumentation-python:latest
  nodejs:
    image: otel/autoinstrumentation-nodejs:latest
```

Pod-a annotation əlavə et:

```yaml
metadata:
  annotations:
    instrumentation.opentelemetry.io/inject-java: "true"
```

## kube-state-metrics və node-exporter

### 1. kube-state-metrics

K8s obyektlərinin halını metric kimi export edir:

```promql
# Pod sayı per namespace
count(kube_pod_info) by (namespace)

# Desired vs actual replicas
kube_deployment_spec_replicas{deployment="laravel"}
kube_deployment_status_replicas_available{deployment="laravel"}

# Pod restart sayı
kube_pod_container_status_restarts_total

# PVC usage
kubelet_volume_stats_used_bytes / kubelet_volume_stats_capacity_bytes
```

### 2. node-exporter

Node-un hardware və OS metric-ləri:

```promql
# CPU per node
100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) by (instance) * 100)

# Memory usage
(node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes) / node_memory_MemTotal_bytes * 100

# Disk usage
(node_filesystem_size_bytes - node_filesystem_avail_bytes) / node_filesystem_size_bytes * 100

# Network
rate(node_network_receive_bytes_total[5m])
```

### 3. cAdvisor

Container-level metric (kubelet-ə built-in):

```promql
# Container CPU
container_cpu_usage_seconds_total

# Container memory
container_memory_working_set_bytes

# Container network
container_network_receive_bytes_total
```

## SLO və Error Budget

### 1. SLO Nə Deməkdir

**SLO (Service Level Objective)**: 99.9% uptime, p95 < 500ms.

**Error budget**: 100% - SLO = 0.1% — ayda ~43 dəqiqə downtime icazəli.

### 2. Burn Rate Alert

Budget-in nə qədər sürətlə yeyildiyini izləyir:

```yaml
- alert: LaravelErrorBudgetBurn
  expr: |
    (
      sum(rate(http_requests_total{app="laravel",status=~"5.."}[1h]))
      / sum(rate(http_requests_total{app="laravel"}[1h]))
    ) > (14.4 * 0.001)   # 14.4× 0.1% error rate
  for: 5m
  labels:
    severity: critical
  annotations:
    summary: "Error budget burning at 14.4x rate — 30-day budget will exhaust in 2 days"
```

Multi-window multi-burn-rate (Google SRE book):

| Window | Burn rate | Action |
|--------|-----------|--------|
| 1h + 5m | 14.4× | Page (critical) |
| 6h + 30m | 6× | Page (high) |
| 1d + 2h | 3× | Ticket |
| 3d + 6h | 1× | Slack |

## PLG vs EFK

| | PLG (Prometheus+Loki+Grafana) | EFK (Elasticsearch+Fluentd+Kibana) |
|-|-------------------------------|------------------------------------|
| Index | Label-based | Full text |
| Cost | Aşağı | Yüksək (Elastic) |
| Learning curve | Orta (PromQL + LogQL) | Orta (KQL) |
| Search power | Limitli | Yüksək |
| K8s ecosystem | Native | Legacy |
| Resource | Az | Çox |

Yeni layihələr üçün PLG + Tempo tövsiyə olunur.

## Pixie (eBPF Observability)

Kod dəyişmədən avto instrumentation — eBPF ilə kernel-dən bütün HTTP/gRPC/SQL trafikini capture edir:

```bash
px deploy
```

Üstünlük: app-ə library əlavə etmədən dərhal observability. Dezavantaj: Linux-only, resource overhead.

## PHP/Laravel ilə İstifadə

### Laravel Prometheus Metrics

```bash
composer require promphp/prometheus_client_php
```

```php
// app/Http/Middleware/PrometheusMiddleware.php
use Prometheus\CollectorRegistry;

class PrometheusMiddleware
{
    public function __construct(private CollectorRegistry $registry) {}

    public function handle($request, \Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;

        $counter = $this->registry->getOrRegisterCounter(
            'laravel', 'http_requests_total', 'Total HTTP requests',
            ['method', 'route', 'status']
        );
        $counter->inc([
            $request->method(),
            $request->route()?->uri() ?? 'unknown',
            (string) $response->status(),
        ]);

        $histogram = $this->registry->getOrRegisterHistogram(
            'laravel', 'http_request_duration_seconds', 'Request duration',
            ['method', 'route'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
        );
        $histogram->observe($duration, [
            $request->method(),
            $request->route()?->uri() ?? 'unknown',
        ]);

        return $response;
    }
}
```

```php
// routes/web.php
Route::get('/metrics', function (CollectorRegistry $registry) {
    $renderer = new RenderTextFormat();
    return response($renderer->render($registry->getMetricFamilySamples()))
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
});
```

### Laravel Structured Logging

```php
// config/logging.php
'channels' => [
    'stderr' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'with' => ['stream' => 'php://stderr'],
        'formatter' => JsonFormatter::class,
    ],
],
```

Loki JSON log-ları avto parse edir:

```logql
{app="laravel"} | json | level="error" | user_id="123"
```

### Complete Observability Stack Deploy

```yaml
# values.yaml for kube-prometheus-stack
grafana:
  adminPassword: admin
  defaultDashboardsEnabled: true
  additionalDataSources:
    - name: Loki
      type: loki
      url: http://loki.monitoring:3100
    - name: Tempo
      type: tempo
      url: http://tempo.monitoring:3200

prometheus:
  prometheusSpec:
    retention: 15d
    serviceMonitorSelectorNilUsesHelmValues: false
    podMonitorSelectorNilUsesHelmValues: false
    ruleSelectorNilUsesHelmValues: false
    storageSpec:
      volumeClaimTemplate:
        spec:
          storageClassName: gp3
          accessModes: ["ReadWriteOnce"]
          resources:
            requests:
              storage: 100Gi

alertmanager:
  config:
    route:
      receiver: slack
    receivers:
      - name: slack
        slack_configs:
          - api_url: https://hooks.slack.com/services/...
            channel: '#alerts'
```

## Interview Sualları

**1. Metric, log, trace fərqi?**
- Metric: aggregate rəqəm zamanla (CPU, RPS)
- Log: event qeydi (nə, nə vaxt, hansı context)
- Trace: request-in service-lərdən keçdiyi yol + hər bir span-ın vaxtı

**2. Prometheus-un pull model-i niyə seçildi?**
Push-dan (Graphite, StatsD) fərqli olaraq Prometheus target-ları özü scrape edir. Üstünlüklər: target discovery asan, target health-i bilinir, duplicate data qarşısı, rate limit sadə.

**3. ServiceMonitor və PodMonitor fərqi?**
- ServiceMonitor: Service obyekti vasitəsilə endpoint-ləri tapır
- PodMonitor: birbaşa pod-ları scrape edir (Service yoxdursa, DaemonSet və s.)

**4. Loki niyə ucuzdur Elasticsearch-dən?**
Loki log body-ni indeksləmir — yalnız label-ları. Search batch olaraq object store-da gəzir. Bu storage-ı 10-100x ucuzlaşdırır amma full-text search yoxdur.

**5. OpenTelemetry nədir?**
Vendor-neutral telemetry (metric, log, trace) toplama standardı. SDK + protokol + collector. Əvvəlki OpenTracing + OpenCensus-in birləşməsi. Jaeger, Tempo, Datadog hamısı OTLP qəbul edir.

**6. Trace sampling niyə lazımdır?**
Hər request-i trace etmək çox bahalıdır (storage + CPU). Tail-based sampling — yalnız error və slow request-lər. Head-based sampling — başlanğıcda 10% qərar verilir. Production 1-10% adətən yetər.

**7. SLO və SLA fərqi?**
- SLA: kontrakt — müştəriyə verilmiş söz (99.9%). Pozulsa maliyyə cəzası.
- SLO: daxili hədəf — çox vaxt SLA-dan sərt (99.95%). Buffer saxlayır.
- SLI: ölçülən metric (error rate, latency p99)

**8. Error budget burn rate nə ölçür?**
Budget-in nə qədər sürətlə yeyildiyini. 1× burn rate SLO-ya dəqiq çatır. 14× burn 30-günlük budget-i 2 günə bitirir — dərhal page. Multi-window-lu alert sistemi səs-küyü azaldır.

**9. kube-state-metrics niyə metrics-server-dən fərqlidir?**
- `metrics-server`: pod/node-un cari resource istifadəsi (CPU, memory) — HPA üçün
- `kube-state-metrics`: K8s obyektlərinin halı (replica count, pod status, deployment conditions) — monitoring üçün

**10. Distributed tracing ilə log-un fərqi?**
Log lokal event-dir. Trace cross-service context daşıyır — trace ID bütün service-lərdə eyni qalır, parent-child span ilə. Log-a trace ID əlavə etmək ("correlation ID") onları birləşdirir.

**11. eBPF observability (Pixie, Cilium Hubble) klassikdən nə üstündür?**
Kod dəyişmədən kernel-dən HTTP/SQL/gRPC-ni capture edir. Bütün dillər üçün eyni anda işləyir. Application overhead yoxdur. Linux-specific və mature deyil hələ.

## Best Practices

1. **RED metric hər app üçün** — Rate, Errors, Duration
2. **USE method node/resource üçün** — Utilization, Saturation, Errors
3. **Structured logging** (JSON) — parse asan olsun
4. **Correlation ID / Trace ID** — log və trace-i birləşdir
5. **Sampling** — production-da 1-10% trace
6. **Label cardinality aşağı** — Prometheus-da çox label performance-ı öldürür
7. **Retention planla** — Prometheus 15-30d, long-term Thanos/Mimir-ə
8. **Alert-lərə runbook** — on-call mühəndis nə etməlidir
9. **SLO-based alerting** — error budget burn rate
10. **Dashboard-lar kod kimi** — Git-də saxla, ConfigMap ilə deploy
11. **kube-prometheus-stack** — yeni layihə üçün default
12. **Grafana data source birləşdirmə** — trace-dən log-a keçid
13. **OpenTelemetry Collector** — vendor flexibility
14. **Tracing çox pilot dövrü tələb edir** — iteratif yanaşma
15. **Alert fatigue** — çox alert olanda ignore edilir, az saxla

### Alətlər Siyahısı

| Kateqoriya | Alət |
|------------|------|
| Metrics | Prometheus, Thanos, Mimir, VictoriaMetrics |
| Logs | Loki, Elasticsearch, Fluentd, Fluent Bit, Vector |
| Tracing | Tempo, Jaeger, Zipkin, SigNoz |
| Dashboard | Grafana, Kibana |
| Alerting | Alertmanager, PagerDuty, Opsgenie |
| eBPF | Pixie, Cilium Hubble, Parca |
| APM (SaaS) | Datadog, New Relic, Dynatrace, Elastic APM |
| Profiling | Pyroscope, Parca |


## Əlaqəli Mövzular

- [apm-observability-agents-in-docker.md](34-apm-observability-agents-in-docker.md) — APM agent-ları
- [docker-logging.md](14-docker-logging.md) — Logging driver-lər
- [kubernetes-autoscaling.md](31-kubernetes-autoscaling.md) — Custom metric ilə HPA
