# Prometheus Monitorinq (Senior)

## Nədir? (What is it?)

Prometheus açıq mənbəli monitoring və alerting sistemidir. Pull-based model ilə işləyir - hədəf sistemlərdən metrikləri toplamaq üçün onlara HTTP request göndərir. Time-series database-dir - hər metrik zaman seriyası kimi saxlanılır. PromQL sorğu dili ilə metrikləri analiz etmək mümkündür. Cloud Native Computing Foundation (CNCF) layihəsidir.

## Əsas Konseptlər (Key Concepts)

### Prometheus Arxitekturası

```
                    ┌─────────────────┐
                    │   Alertmanager  │
                    │  (Alert routing)│
                    └────────▲────────┘
                             │ alerts
┌──────────┐  scrape  ┌─────┴──────┐  query  ┌──────────┐
│ Exporters│◄─────────│ Prometheus │◄────────│  Grafana  │
│ (targets)│          │   Server   │         │(dashboard)│
└──────────┘          └─────┬──────┘         └──────────┘
                            │
                    ┌───────┴───────┐
                    │  TSDB Storage │
                    │ (time-series) │
                    └───────────────┘
```

### Quraşdırma (Docker Compose)

```yaml
# docker-compose.yml
version: '3.8'
services:
  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.retention.time=15d'
      - '--web.enable-lifecycle'

  node-exporter:
    image: prom/node-exporter:latest
    ports:
      - "9100:9100"
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /:/rootfs:ro
    command:
      - '--path.procfs=/host/proc'
      - '--path.sysfs=/host/sys'
      - '--path.rootfs=/rootfs'

  alertmanager:
    image: prom/alertmanager:latest
    ports:
      - "9093:9093"
    volumes:
      - ./alertmanager.yml:/etc/alertmanager/alertmanager.yml

volumes:
  prometheus_data:
```

### Prometheus Konfiqurasiyası

```yaml
# prometheus.yml
global:
  scrape_interval: 15s          # Hər 15 saniyədə metrik topla
  evaluation_interval: 15s      # Hər 15 saniyədə qaydaları qiymətləndir
  scrape_timeout: 10s

# Alert qaydaları faylları
rule_files:
  - "alert_rules.yml"
  - "recording_rules.yml"

# Alertmanager konfiqurasiyası
alerting:
  alertmanagers:
    - static_configs:
        - targets: ['alertmanager:9093']

# Scrape konfiqurasiyaları (hədəflər)
scrape_configs:
  # Prometheus özünü monitor edir
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  # Node Exporter (server metrikləri)
  - job_name: 'node'
    static_configs:
      - targets:
          - 'node-exporter:9100'
          - '10.0.1.10:9100'
          - '10.0.1.11:9100'
        labels:
          env: 'production'

  # PHP-FPM
  - job_name: 'php-fpm'
    static_configs:
      - targets: ['php-fpm-exporter:9253']

  # Nginx
  - job_name: 'nginx'
    static_configs:
      - targets: ['nginx-exporter:9113']

  # MySQL
  - job_name: 'mysql'
    static_configs:
      - targets: ['mysqld-exporter:9104']

  # Redis
  - job_name: 'redis'
    static_configs:
      - targets: ['redis-exporter:9121']

  # Laravel Application
  - job_name: 'laravel'
    metrics_path: '/metrics'
    static_configs:
      - targets: ['laravel-app:8000']

  # Kubernetes Service Discovery
  - job_name: 'kubernetes-pods'
    kubernetes_sd_configs:
      - role: pod
    relabel_configs:
      - source_labels: [__meta_kubernetes_pod_annotation_prometheus_io_scrape]
        action: keep
        regex: true
```

### Metrik Tipləri

```
1. Counter (Sayğac)
   - Yalnız artır (sıfırlana bilər)
   - Nümunə: http_requests_total, errors_total
   - rate() ilə saniyədəki artım hesablanır

2. Gauge (Göstərici)
   - Artıb-azala bilər
   - Nümunə: temperature, memory_usage, active_connections
   - Anlıq dəyəri göstərir

3. Histogram
   - Dəyərləri bucket-lərə paylaşdırır
   - Nümunə: http_request_duration_seconds
   - _bucket, _sum, _count suffix-ləri var
   - Percentile hesablamaq üçün (p50, p95, p99)

4. Summary
   - Histogram-a oxşar, amma client tərəfində percentile hesablayır
   - Pre-calculated quantile-lər
   - Daha az server yükü, amma aggregation çətindir
```

### PromQL (Prometheus Query Language)

```promql
# Əsas sorğular
up                                          # Hədəf aktiv mi?
node_cpu_seconds_total                      # CPU metrikası (raw)
http_requests_total                          # Ümumi request sayı

# Label filtering
http_requests_total{method="GET"}            # GET request-ləri
http_requests_total{status=~"5.."}           # 5xx error-lar (regex)
http_requests_total{job!="test"}             # test olmayan job-lar

# Rate - saniyədəki dəyişim (counter üçün)
rate(http_requests_total[5m])                # Son 5 dəqiqəlik rate
rate(http_requests_total{status="500"}[5m])  # 500 error rate

# irate - instant rate (son 2 nöqtə arası)
irate(http_requests_total[5m])

# Aggregation
sum(rate(http_requests_total[5m]))           # Ümumi request/sn
sum by (status)(rate(http_requests_total[5m]))  # Status-a görə
avg by (instance)(rate(node_cpu_seconds_total{mode!="idle"}[5m]))  # CPU istifadəsi

# Histogram percentile
histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))  # p95 latency
histogram_quantile(0.99, rate(http_request_duration_seconds_bucket[5m]))  # p99 latency

# Math operations
node_filesystem_avail_bytes / node_filesystem_size_bytes * 100   # Disk % boş
(node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes)
  / node_memory_MemTotal_bytes * 100                              # RAM % istifadə

# Predict
predict_linear(node_filesystem_avail_bytes[1h], 4*3600)  # 4 saat sonra disk dolacaq?

# absent - metrik yoxdursa alert
absent(up{job="laravel"})                     # Laravel down olsa

# Zaman seçimi
http_requests_total offset 1h                 # 1 saat əvvəlki dəyər
increase(http_requests_total[24h])            # Son 24 saatda artım
```

### Alerting Qaydaları

```yaml
# alert_rules.yml
groups:
  - name: server_alerts
    rules:
      # Server down
      - alert: InstanceDown
        expr: up == 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "Instance {{ $labels.instance }} down"
          description: "{{ $labels.instance }} has been down for more than 2 minutes."

      # Yüksək CPU
      - alert: HighCPU
        expr: 100 - (avg by(instance) (irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100) > 80
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High CPU on {{ $labels.instance }}"
          description: "CPU usage is {{ $value }}%"

      # Disk dolur
      - alert: DiskSpaceLow
        expr: (node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"}) * 100 < 20
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Low disk space on {{ $labels.instance }}"

      # Yüksək RAM
      - alert: HighMemory
        expr: (1 - node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes) * 100 > 90
        for: 5m
        labels:
          severity: critical

  - name: laravel_alerts
    rules:
      # Yüksək error rate
      - alert: HighErrorRate
        expr: sum(rate(http_requests_total{status=~"5.."}[5m])) / sum(rate(http_requests_total[5m])) > 0.05
        for: 3m
        labels:
          severity: critical
        annotations:
          summary: "Error rate above 5%"
          description: "{{ $value | humanizePercentage }} of requests are failing"

      # Yavaş response
      - alert: HighLatency
        expr: histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m])) > 2
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "p95 latency above 2 seconds"

      # Queue backlog
      - alert: QueueBacklog
        expr: laravel_queue_size > 1000
        for: 5m
        labels:
          severity: warning
```

### Alertmanager Konfiqurasiyası

```yaml
# alertmanager.yml
global:
  resolve_timeout: 5m
  smtp_smarthost: 'smtp.gmail.com:587'
  smtp_from: 'alerts@example.com'
  smtp_auth_username: 'alerts@example.com'
  smtp_auth_password: 'password'

route:
  group_by: ['alertname', 'severity']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 1h
  receiver: 'default'
  routes:
    - match:
        severity: critical
      receiver: 'pagerduty-critical'
    - match:
        severity: warning
      receiver: 'slack-warnings'

receivers:
  - name: 'default'
    email_configs:
      - to: 'devops@example.com'

  - name: 'slack-warnings'
    slack_configs:
      - api_url: 'https://hooks.slack.com/services/xxx'
        channel: '#alerts'
        text: "{{ range .Alerts }}{{ .Annotations.summary }}\n{{ end }}"

  - name: 'pagerduty-critical'
    pagerduty_configs:
      - service_key: 'your-service-key'

inhibit_rules:
  - source_match:
      severity: 'critical'
    target_match:
      severity: 'warning'
    equal: ['alertname', 'instance']
```

### Exporterlər

```bash
# Node Exporter - Linux server metrikləri (CPU, RAM, Disk, Network)
# Port: 9100

# MySQL Exporter
# Port: 9104
docker run -d --name mysqld-exporter \
  -e DATA_SOURCE_NAME="exporter:password@(mysql:3306)/" \
  prom/mysqld-exporter

# Redis Exporter
# Port: 9121
docker run -d --name redis-exporter \
  -e REDIS_ADDR=redis://redis:6379 \
  oliver006/redis_exporter

# Nginx Exporter (stub_status lazımdır)
# Port: 9113

# PHP-FPM Exporter
# Port: 9253

# Blackbox Exporter - HTTP/TCP/ICMP probe
# Port: 9115
# Endpoint availability check
```

## PHP/Laravel ilə İstifadə

### Laravel Prometheus Metrikləri

```php
<?php
// composer require promphp/prometheus_client_php

// app/Http/Middleware/PrometheusMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class PrometheusMiddleware
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $adapter = new Redis(['host' => config('database.redis.default.host')]);
        $this->registry = new CollectorRegistry($adapter);
    }

    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;

        // Request counter
        $counter = $this->registry->getOrRegisterCounter(
            'laravel', 'http_requests_total', 'Total HTTP requests',
            ['method', 'endpoint', 'status']
        );
        $counter->inc([
            $request->method(),
            $request->route()?->uri() ?? 'unknown',
            $response->getStatusCode(),
        ]);

        // Request duration histogram
        $histogram = $this->registry->getOrRegisterHistogram(
            'laravel', 'http_request_duration_seconds', 'Request duration',
            ['method', 'endpoint'],
            [0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
        );
        $histogram->observe($duration, [
            $request->method(),
            $request->route()?->uri() ?? 'unknown',
        ]);

        return $response;
    }
}

// routes/web.php - Metrics endpoint
Route::get('/metrics', function () {
    $adapter = new \Prometheus\Storage\Redis([
        'host' => config('database.redis.default.host'),
    ]);
    $registry = new \Prometheus\CollectorRegistry($adapter);
    $renderer = new \Prometheus\RenderTextFormat();

    return response($renderer->render($registry->getMetricFamilySamples()))
        ->header('Content-Type', \Prometheus\RenderTextFormat::MIME_TYPE);
});
```

### Custom Business Metrikləri

```php
<?php
// app/Services/MetricsService.php
namespace App\Services;

use Prometheus\CollectorRegistry;

class MetricsService
{
    public function __construct(private CollectorRegistry $registry) {}

    public function orderCreated(float $amount): void
    {
        $this->registry->getOrRegisterCounter(
            'business', 'orders_total', 'Total orders'
        )->inc();

        $this->registry->getOrRegisterCounter(
            'business', 'revenue_total', 'Total revenue'
        )->incBy($amount);
    }

    public function queueSize(string $queue, int $size): void
    {
        $this->registry->getOrRegisterGauge(
            'laravel', 'queue_size', 'Queue job count', ['queue']
        )->set($size, [$queue]);
    }

    public function cacheHit(bool $hit): void
    {
        $label = $hit ? 'hit' : 'miss';
        $this->registry->getOrRegisterCounter(
            'laravel', 'cache_operations_total', 'Cache operations', ['result']
        )->inc([$label]);
    }
}
```

## Interview Sualları

### S1: Prometheus pull-based model istifadə edir, bu nə deməkdir?
**C:** Pull-based: Prometheus hədəf sistemlərə HTTP request göndərərək metrikləri toplayır (scrape). Push-based modeldə isə hədəf sistemlər metrikləri monitoring serverə göndərir. Pull-in üstünlükləri: 1) Prometheus hansı hədəflərin aktiv olduğunu bilir (up metric), 2) Konfiqurasiya mərkəzləşdirilib, 3) Hədəf yükü azalır. Mənfi: firewall arxasındakı hədəflər çətindir, bunun üçün Pushgateway istifadə olunur.

### S2: Counter və Gauge arasında fərq nədir?
**C:** Counter yalnız artır (və restart-da sıfırlanır). Request sayı, error sayı kimi monoton artan metriklər üçün istifadə olunur. rate() funksiyası ilə saniyədəki dəyişim hesablanır. Gauge artıb-azala bilər. Temperatur, yaddaş istifadəsi, aktiv bağlantı sayı kimi metriklər üçündür. Anlıq dəyəri göstərir, rate()-ə ehtiyac yoxdur.

### S3: PromQL-də rate() və irate() fərqi nədir?
**C:** `rate()` verilmiş zaman aralığında orta saniyəlik artımı hesablayır - daha düzgün, grafiklar üçün uyğun. `irate()` yalnız son iki data nöqtəsi arasındakı ani rate-i hesablayır - daha həssas, spike-ları görmək üçün yaxşıdır amma qeyri-sabit. Alert qaydalarında rate(), debug üçün irate() istifadə olunur.

### S4: Histogram metrik tipini izah edin.
**C:** Histogram dəyərləri öncədən təyin edilmiş bucket-lərə paylaşdırır. Məsələn HTTP request müddəti: 0-0.1s, 0.1-0.5s, 0.5-1s, 1-5s. Üç suffix yaradır: `_bucket` (hər bucket-də neçə dəyər), `_sum` (bütün dəyərlərin cəmi), `_count` (ümumi say). `histogram_quantile()` ilə percentile hesablanır. p95 latency = requestlərin 95%-i bu müddətdən az sürdü.

### S5: Alerting qaydalarında "for" parametri nə edir?
**C:** `for` şərtin nə qədər müddət davam etməli olduğunu bildirir. Məsələn `for: 5m` deməkdir ki, şərt 5 dəqiqə davamlı doğru olmalıdır ki alert göndərilsin. Bu qısa müddətli spike-ların yalançı alarm yaratmasının qarşısını alır. Əvvəlcə alert "pending" statusunda olur, müddət keçdikdən sonra "firing" olur.

### S6: Recording rules nədir?
**C:** Tez-tez istifadə olunan və ya mürəkkəb PromQL sorğularının nəticələrini yeni metrik kimi saxlamaq. Performansı artırır - dashboard yüklənməsi sürətlənir. Məsələn: `record: job:http_requests:rate5m` `expr: sum(rate(http_requests_total[5m])) by (job)`. Mürəkkəb alert qaydalarını sadələşdirir.

## Best Practices

1. **Metrik adlarını standartlaşdırın** - `namespace_subsystem_name_unit` formatı
2. **Label kardinalitesini nəzarətdə saxlayın** - Çox unikal label Prometheus-u yavaşladır
3. **Rate() counter-lər üçün istifadə edin** - Raw counter dəyəri mənasızdır
4. **Recording rules** yazın - Mürəkkəb sorğuları optimize edin
5. **Alert-lər üçün for** parametri istifadə edin - Yalançı alarm azaldın
6. **Retention müddəti** təyin edin - Çox uzunmüddətli storage üçün Thanos/Cortex
7. **Alertmanager routing** düzgün qurun - Critical vs Warning fərqli kanallar
8. **Exporter-ləri ayrı container** kimi işlədin
9. **/metrics endpoint** qoruyun - basic auth və ya internal network
10. **Grafana ilə inteqrasiya** edin - PromQL sorğularını vizuallaşdırın

---

## Praktik Tapşırıqlar

1. Laravel üçün custom Prometheus metrik yaradın: `promphp/prometheus_client_php` quruyun, HTTP request sayını (method + endpoint + status) counter, response time-ı histogram kimi ölçün; `php artisan tinker`-də metrik artırın, `/metrics` endpoint-ə `curl` ilə yoxlayın
2. Alertmanager qurun: Prometheus alert qaydasını yazın (`error_rate > 5%` 5 dəqiqə davam edərsə), Alertmanager routing (critical → PagerDuty, warning → Slack); `amtool alert add` ilə test alert göndərin; Slack-da bildiriş alındığını yoxlayın
3. PromQL sorğuları yazın: son 5 dəqiqədə P99 latency (`histogram_quantile(0.99, ...)`), error rate by endpoint (`rate(http_requests_total{status=~"5.."}[5m])`), memory saturation (`node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes`); Grafana-da test edin
4. Node Exporter qurun: server-ə install edin, scrape config-ə əlavə edin; CPU throttling, disk I/O wait, network saturation metriklerini Grafana-da panel kimi vizualizasiya edin
5. Recording rules yaradın: tez-tez istifadə olunan ağır PromQL sorğusunu `record: job:http_requests:rate5m` kimi precompute edin; query time-ı recording rule ilə vs olmadan müqayisə edin
6. Prometheus retention və storage planlaşdırın: `--storage.tsdb.retention.time=30d`, `--storage.tsdb.retention.size=10GB`; mövcud metrik count-a görə disk istifadəsini hesablayın; remote write ilə Thanos/Cortex-ə uzunmüddətli storage qurun

## Əlaqəli Mövzular

- [Grafana](19-monitoring-grafana.md) — dashboard, panel, RED/USE method
- [ELK Stack](20-elk-stack.md) — log aggregation, Kibana
- [OpenTelemetry](21-opentelemetry.md) — OTel Collector Prometheus scraping
- [Logging & Monitoring](38-logging-monitoring.md) — structured logs, alerting
- [Observability](42-observability.md) — metrics/logs/traces üçlüyü
- [SLA/SLO/SLI](43-sla-slo-sli.md) — error budget, alert threshold
