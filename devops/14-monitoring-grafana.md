# Grafana Monitoring Dashboards

## Nədir? (What is it?)

Grafana açıq mənbəli data vizualizasiya və monitoring platformasıdır. Prometheus, Elasticsearch, MySQL, PostgreSQL, InfluxDB kimi müxtəlif data source-lardan data toplayıb gözəl dashboard-lar yaradır. Alerting, annotation, variable dəstəyi var. DevOps komandaları üçün əsas monitoring interfeysidir.

## Əsas Konseptlər (Key Concepts)

### Quraşdırma

```yaml
# docker-compose.yml
services:
  grafana:
    image: grafana/grafana:latest
    ports:
      - "3000:3000"
    volumes:
      - grafana_data:/var/lib/grafana
      - ./grafana/provisioning:/etc/grafana/provisioning
      - ./grafana/dashboards:/var/lib/grafana/dashboards
    environment:
      GF_SECURITY_ADMIN_USER: admin
      GF_SECURITY_ADMIN_PASSWORD: secret
      GF_USERS_ALLOW_SIGN_UP: "false"
      GF_SERVER_ROOT_URL: "https://grafana.example.com"
      GF_SMTP_ENABLED: "true"
      GF_SMTP_HOST: "smtp.gmail.com:587"
      GF_SMTP_USER: "alerts@example.com"
      GF_SMTP_PASSWORD: "password"

volumes:
  grafana_data:
```

### Data Sources

```yaml
# grafana/provisioning/datasources/datasources.yml
apiVersion: 1
datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    url: http://prometheus:9090
    isDefault: true
    jsonData:
      timeInterval: '15s'

  - name: Elasticsearch
    type: elasticsearch
    access: proxy
    url: http://elasticsearch:9200
    database: 'laravel-*'
    jsonData:
      timeField: '@timestamp'
      esVersion: '8.0.0'

  - name: MySQL
    type: mysql
    url: mysql:3306
    database: laravel
    user: grafana
    secureJsonData:
      password: grafana_password

  - name: Loki
    type: loki
    access: proxy
    url: http://loki:3100
```

### Dashboard Provisioning

```yaml
# grafana/provisioning/dashboards/dashboards.yml
apiVersion: 1
providers:
  - name: 'Default'
    orgId: 1
    folder: 'DevOps'
    type: file
    disableDeletion: false
    updateIntervalSeconds: 30
    options:
      path: /var/lib/grafana/dashboards
      foldersFromFilesStructure: true
```

### Dashboard JSON Model

```json
{
  "dashboard": {
    "title": "Laravel Application Dashboard",
    "tags": ["laravel", "production"],
    "timezone": "browser",
    "refresh": "30s",
    "time": {
      "from": "now-6h",
      "to": "now"
    },
    "panels": [
      {
        "title": "Request Rate",
        "type": "timeseries",
        "gridPos": { "h": 8, "w": 12, "x": 0, "y": 0 },
        "targets": [
          {
            "expr": "sum(rate(http_requests_total{job=\"laravel\"}[5m]))",
            "legendFormat": "Total RPS"
          }
        ]
      },
      {
        "title": "Error Rate",
        "type": "stat",
        "gridPos": { "h": 4, "w": 6, "x": 12, "y": 0 },
        "targets": [
          {
            "expr": "sum(rate(http_requests_total{status=~\"5..\"}[5m])) / sum(rate(http_requests_total[5m])) * 100"
          }
        ],
        "fieldConfig": {
          "defaults": {
            "unit": "percent",
            "thresholds": {
              "steps": [
                { "color": "green", "value": null },
                { "color": "yellow", "value": 1 },
                { "color": "red", "value": 5 }
              ]
            }
          }
        }
      }
    ]
  }
}
```

### Panel Tipləri

```
1. Time Series - Zaman qrafiki (əsas panel tip)
   - Request rate, CPU usage, memory, latency
   - Bir neçə sorğu üst-üstə

2. Stat - Tək rəqəm göstəricisi
   - Uptime, error rate, active users
   - Threshold rəngləri ilə

3. Gauge - Dairəvi göstərici
   - CPU%, Disk%, Memory%
   - Min/max dəyərləri ilə

4. Bar Chart - Sütun qrafik
   - Top endpoints, error distribution

5. Table - Cədvəl
   - Server list, alert history

6. Heatmap - İstilik xəritəsi
   - Request latency distribution
   - Time-based pattern analizi

7. Logs - Log panel
   - Loki/Elasticsearch-dən loglar
   - Real-time log streaming

8. Alert List - Alert siyahısı
   - Aktiv alert-lər

9. Text - Markdown mətn
   - Dashboard açıqlamaları, linkler

10. Pie Chart - Dairəvi qrafik
    - Traffic paylanması, error tipləri
```

### Variables (Template Variables)

```
# Dashboard variables - dashboard-ı dinamik edir

# Query Variable
Name: server
Type: Query
Data source: Prometheus
Query: label_values(up{job="node"}, instance)
# İstifadə: $server

# Custom Variable
Name: environment
Type: Custom
Values: production, staging, development

# Interval Variable
Name: interval
Type: Interval
Values: 1m, 5m, 15m, 30m, 1h

# PromQL-də istifadə
rate(http_requests_total{instance="$server", env="$environment"}[$interval])

# Multi-value variable
# Regex: /(.*)/
# İstifadə: {instance=~"$server"} - regex match
```

### Grafana Alerting

```yaml
# Alert Rule nümunəsi (Grafana UI-dan və ya provisioning)
# grafana/provisioning/alerting/alerts.yml
apiVersion: 1
groups:
  - orgId: 1
    name: Laravel Alerts
    folder: DevOps
    interval: 1m
    rules:
      - uid: high-error-rate
        title: High Error Rate
        condition: C
        data:
          - refId: A
            datasourceUid: prometheus
            model:
              expr: sum(rate(http_requests_total{status=~"5.."}[5m]))
          - refId: B
            datasourceUid: prometheus
            model:
              expr: sum(rate(http_requests_total[5m]))
          - refId: C
            datasourceUid: "__expr__"
            model:
              type: math
              expression: "$A / $B * 100"
              conditions:
                - evaluator:
                    type: gt
                    params: [5]
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Error rate above 5%"

# Notification policies
contactPoints:
  - orgId: 1
    name: slack-devops
    receivers:
      - uid: slack-1
        type: slack
        settings:
          url: "https://hooks.slack.com/services/xxx"
          recipient: "#devops-alerts"
```

## Praktiki Nümunələr (Practical Examples)

### Laravel Monitoring Dashboard PromQL Sorğuları

```promql
# === Request Metrics ===
# Total RPS (requests per second)
sum(rate(http_requests_total{job="laravel"}[5m]))

# RPS by endpoint
sum by (endpoint)(rate(http_requests_total{job="laravel"}[5m]))

# RPS by status code
sum by (status)(rate(http_requests_total{job="laravel"}[5m]))

# Error rate %
sum(rate(http_requests_total{job="laravel", status=~"5.."}[5m]))
/ sum(rate(http_requests_total{job="laravel"}[5m])) * 100

# === Latency Metrics ===
# p50 latency
histogram_quantile(0.5, rate(http_request_duration_seconds_bucket{job="laravel"}[5m]))

# p95 latency
histogram_quantile(0.95, rate(http_request_duration_seconds_bucket{job="laravel"}[5m]))

# p99 latency
histogram_quantile(0.99, rate(http_request_duration_seconds_bucket{job="laravel"}[5m]))

# Average response time
rate(http_request_duration_seconds_sum{job="laravel"}[5m])
/ rate(http_request_duration_seconds_count{job="laravel"}[5m])

# === PHP-FPM Metrics ===
# Active processes
phpfpm_active_processes{job="php-fpm"}

# Idle processes
phpfpm_idle_processes{job="php-fpm"}

# Max children reached (capacity)
rate(phpfpm_max_children_reached_total[5m])

# === Queue Metrics ===
# Queue size
laravel_queue_size{queue="default"}

# Queue processing rate
rate(laravel_jobs_processed_total[5m])

# Failed jobs
rate(laravel_jobs_failed_total[5m])

# === Infrastructure ===
# CPU usage %
100 - (avg by (instance)(irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)

# Memory usage %
(1 - node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes) * 100

# Disk usage %
(1 - node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"}) * 100

# Network traffic
rate(node_network_receive_bytes_total{device="eth0"}[5m]) * 8  # bits/s
rate(node_network_transmit_bytes_total{device="eth0"}[5m]) * 8
```

### RED Method Dashboard (Request, Error, Duration)

```
Rate    - sum(rate(http_requests_total[5m]))
Errors  - sum(rate(http_requests_total{status=~"5.."}[5m])) / sum(rate(http_requests_total[5m]))
Duration - histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))

Bu 3 metrik hər microservice üçün minimal monitoring təmin edir.
```

### USE Method Dashboard (Utilization, Saturation, Errors)

```
# CPU
Utilization: avg(rate(node_cpu_seconds_total{mode!="idle"}[5m]))
Saturation:  avg(node_load1) / count(node_cpu_seconds_total{mode="idle"})
Errors:      rate(node_cpu_guest_seconds_total[5m])

# Memory
Utilization: 1 - node_memory_MemAvailable_bytes/node_memory_MemTotal_bytes
Saturation:  rate(node_vmstat_pgmajfault[5m])
Errors:      N/A (OOM events)

# Disk I/O
Utilization: rate(node_disk_io_time_seconds_total[5m])
Saturation:  rate(node_disk_io_time_weighted_seconds_total[5m])
Errors:      rate(node_disk_io_now[5m])

# Network
Utilization: rate(node_network_receive_bytes_total[5m])
Saturation:  rate(node_network_receive_drop_total[5m])
Errors:      rate(node_network_receive_errs_total[5m])
```

## PHP/Laravel ilə İstifadə

### Laravel Health Endpoint for Grafana

```php
<?php
// routes/api.php
Route::get('/health', function () {
    $checks = [
        'database' => false,
        'redis' => false,
        'storage' => false,
        'queue' => false,
    ];

    try {
        DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Exception $e) {}

    try {
        Redis::ping();
        $checks['redis'] = true;
    } catch (\Exception $e) {}

    $checks['storage'] = is_writable(storage_path());

    try {
        $queueSize = Redis::llen('queues:default');
        $checks['queue'] = $queueSize < 10000;  // Queue backlog yoxla
    } catch (\Exception $e) {}

    $healthy = !in_array(false, $checks);

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
});
```

### Grafana Annotation from Laravel

```php
<?php
// app/Services/GrafanaService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class GrafanaService
{
    public function addAnnotation(string $text, array $tags = []): void
    {
        Http::withToken(config('services.grafana.api_key'))
            ->post(config('services.grafana.url') . '/api/annotations', [
                'text' => $text,
                'tags' => $tags,
                'time' => now()->getTimestampMs(),
            ]);
    }
}

// Deploy zamanı annotation əlavə et
app(GrafanaService::class)->addAnnotation(
    'Deployed v2.5.0 to production',
    ['deployment', 'production']
);
```

## Interview Sualları

### S1: Grafana və Prometheus arasında fərq nədir?
**C:** Prometheus metrikləri toplayan, saxlayan və sorğulayan monitoring sistemidir - backend. Grafana isə vizualizasiya platformasıdır - frontend. Prometheus data toplayır və saxlayır, Grafana həmin data-nı dashboard-larda göstərir. Grafana yalnız Prometheus ilə deyil, Elasticsearch, MySQL, CloudWatch və s. ilə də işləyə bilər. Prometheus-un öz sadə UI-ı var amma Grafana çox daha güclü vizualizasiya verir.

### S2: RED və USE monitoring metodları nədir?
**C:** RED (Tom Wilkie): Request-centric, microservice-lər üçün - Rate (saniyədə request), Errors (error nisbəti), Duration (response vaxtı). USE (Brendan Gregg): Resource-centric, infrastruktur üçün - Utilization (resurs istifadəsi %), Saturation (sıra/gözləmə), Errors (error sayı). Laravel API-si üçün RED, server monitoring üçün USE istifadə olunur.

### S3: Grafana-da template variables necə işləyir?
**C:** Template variables dashboard-ları dinamik edir. Dropdown-dan server, environment, interval seçmək olur. Query variable Prometheus-dan label dəyərlərini çəkir: `label_values(up, instance)`. PromQL-də `$variable` kimi istifadə olunur. Multi-value seçildikdə regex match `=~"$var"` istifadə olunur. Bir dashboard müxtəlif serverlər/mühitlər üçün istifadə oluna bilər.

### S4: Grafana alerting necə qurulur?
**C:** Grafana 9+ unified alerting istifadə edir. Alert rule: data source sorğusu + threshold şərti + evaluation interval + for müddəti. Contact points: Slack, email, PagerDuty, webhook. Notification policies: hansı alert hansı contact point-ə getsin, routing. Labels ilə qruplaşdırma, silencing (müvəqqəti susdurma) mövcuddur. Provisioning ilə kod kimi idarə etmək olar.

### S5: Dashboard design best practices nələrdir?
**C:** 1) Golden signals (latency, traffic, errors, saturation) yuxarıda olsun, 2) Soldan sağa: request flow ardıcıllığı, 3) Rəng kodlaması: yaşıl=yaxşı, qırmızı=pis, sarı=xəbərdarlıq, 4) Variables ilə filtrlə (server, env), 5) Annotations ilə deploy vaxtlarını göstər, 6) Panel başlıqları informativ olsun, 7) Threshold-lar visual olsun, 8) Refresh interval uyğun olsun (30s-5m).

## Best Practices

1. **Dashboard-as-Code** - JSON dashboard-ları Git-də saxlayın, provisioning istifadə edin
2. **Golden Signals** izləyin - Latency, Traffic, Errors, Saturation
3. **Variables** istifadə edin - Bir dashboard çox server/mühit üçün
4. **Annotations** əlavə edin - Deploy, incident, maintenance vaxtları
5. **Folder strukturu** qurun - Team/service-a görə qruplaşdırın
6. **RBAC** konfiqurasiya edin - Rol əsaslı giriş nəzarəti
7. **Alert fatigue** qaçının - Yalnız actionable alert-lər qurun
8. **Consistent naming** - Panel/dashboard adları standart olsun
9. **Link-lər əlavə edin** - Dashboard-lar arası drill-down
10. **Backup** edin - Grafana data-sını və dashboard JSON-larını backup edin
