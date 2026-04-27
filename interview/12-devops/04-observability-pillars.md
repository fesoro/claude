# Observability: Metrics, Logs, Traces (Senior ⭐⭐⭐)

## İcmal
Observability — sistemin daxili vəziyyətini xarici çıxışları (metrics, logs, traces) əsasında başa düşmə qabiliyyətidir. "Monitoring" reaktivdir — bəlirli hadisəni gözləyir. "Observability" proaktivdir — əvvəlcə görülməmiş problemləri araşdırmağa imkan verir. Müasir production sistemlərini idarə etmək üçün bu üç sütunu birlikdə başa düşmək lazımdır.

## Niyə Vacibdir
"Sistem yavaşdır" şikayəti gəldikdə — haradan başlayırsınız? Log-lar hər şeyi söyləmir; alert yalnız "nəsə pisdir" deyir; trace olmadan microservice-lərdə bottleneck tapmaq mümkün deyil. Observability tripod-unu başa düşən developer production incident-ı daha sürətli həll edir. Senior/Lead müsahibələrindən "on-call keçidinizdə nə edirsiniz?" sualına bu anlayış olmadan cavab vermək çətindir.

## Əsas Anlayışlar

### Üç Sütun:

---

#### 1. Metrics (Metrikalar)

Sayısal, time-series məlumat. "Nə baş verdi?" sualına yüksək səviyyəli cavab.

**Metric növləri:**
- **Counter**: Yalnız artan (request sayı, error sayı)
- **Gauge**: Artıb-azalan (CPU %, active connections, queue depth)
- **Histogram**: Dəyər paylanması (response time buckets)
- **Summary**: Percentile hesablamaları (p50, p95, p99)

**USE Method (Resource-centric monitoring):**
- **U**tilization: Resurs nə qədər məşğuldur? (CPU 80%)
- **S**aturation: Kuyrukda nə qədər iş gözləyir? (queue depth)
- **E**rrors: Xəta sayı/faizi

**RED Method (Service-centric monitoring):**
- **R**ate: Saniyədəki request sayı
- **E**rrors: Xətalı request faizi
- **D**uration: Response time (latency)

**Prometheus + Grafana stack:**
```yaml
# docker-compose.yml — lokal observability stack
services:
  prometheus:
    image: prom/prometheus:latest
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    ports:
      - "9090:9090"

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3000:3000"
    environment:
      GF_SECURITY_ADMIN_PASSWORD: admin
```

```php
// Laravel + Prometheus metrics
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class MetricsService
{
    public function recordHttpRequest(string $method, string $path, int $status, float $duration): void
    {
        $registry = app(CollectorRegistry::class);

        // Counter
        $requestsCounter = $registry->getOrRegisterCounter(
            'app', 'http_requests_total',
            'Total HTTP requests',
            ['method', 'path', 'status']
        );
        $requestsCounter->inc([$method, $path, (string) $status]);

        // Histogram
        $durationHistogram = $registry->getOrRegisterHistogram(
            'app', 'http_request_duration_seconds',
            'HTTP request duration',
            ['method', 'path'],
            [0.01, 0.05, 0.1, 0.5, 1, 2, 5]
        );
        $durationHistogram->observe($duration, [$method, $path]);
    }
}
```

**Əsas alertlər:**
- Error rate > 1% → PagerDuty
- p99 latency > 2s → Slack notification
- CPU > 85% (5 dəqiqə) → Auto-scale trigger

---

#### 2. Logs (Jurnallar)

Hadisənin tam konteksti. "Nə baş verdi?" sualına ətraflı cavab.

**Structured Logging:**
```php
// Pis: plain text log
Log::error("User login failed for email: " . $email);

// Yaxşı: structured JSON log
Log::error('User login failed', [
    'email'      => $email,
    'ip'         => request()->ip(),
    'user_agent' => request()->userAgent(),
    'attempt_no' => $attemptCount,
    'trace_id'   => request()->header('X-Trace-Id'),
]);
```

**Log Levels (istifadə qaydası):**
| Level | Nə zaman |
|-------|----------|
| DEBUG | Development, verbose context |
| INFO | Normal business events (user registered, order created) |
| WARNING | Anomalous amma system işləyir (deprecated API, slow query) |
| ERROR | Bir operation uğursuz oldu (payment failed, DB error) |
| CRITICAL | System kritik vəziyyətdədir |

**Centralized Logging Stack:**
- **ELK Stack**: Elasticsearch + Logstash + Kibana
- **EFK Stack**: Elasticsearch + Fluentd + Kibana
- **Grafana Loki**: Prometheus-a bənzər, label-based
- **Cloud native**: AWS CloudWatch, GCP Cloud Logging, Azure Monitor

**Laravel Logging konfiqurasiyası:**
```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'stderr'],
    ],
    'stderr' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'formatter' => JsonFormatter::class,  // Structured JSON
        'with' => [
            'stream' => 'php://stderr',
        ],
    ],
],
```

---

#### 3. Distributed Tracing (İzlər)

Bir request-in bütün microservice-lər boyunca gedişatını izləmək. "Haradan yavaşlayır?" sualına cavab.

**Trace Konseptləri:**
- **Trace**: Bir user request-inin tam ömrü
- **Span**: Trace daxilindəki bir əməliyyat (DB call, HTTP call, function)
- **Trace ID**: Bütün servislərdəki eyni request-i birləşdirən ID
- **Span ID**: Hər span üçün unikal ID

```
User Request → API Gateway (span 1)
                 └── Auth Service (span 2)
                       └── DB query (span 2.1)
                 └── Order Service (span 3)
                       └── DB query (span 3.1)
                       └── Payment Service (span 3.2)
                             └── Stripe API (span 3.2.1)
```

**OpenTelemetry — standart:**
```php
// OpenTelemetry PHP SDK
use OpenTelemetry\API\Trace\TracerInterface;

class OrderService
{
    public function __construct(
        private readonly TracerInterface $tracer,
        private readonly OrderRepository $repository,
    ) {}

    public function createOrder(array $data): Order
    {
        $span = $this->tracer->spanBuilder('order.create')
            ->startSpan();

        try {
            $span->setAttribute('user.id', $data['user_id']);
            $span->setAttribute('order.amount', $data['amount']);

            $order = $this->repository->save(new Order($data));

            $span->setAttribute('order.id', $order->id);
            return $order;
        } catch (\Exception $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR);
            throw $e;
        } finally {
            $span->end();
        }
    }
}
```

**Trace Backends:**
- Jaeger (open source)
- Zipkin (Twitter, open source)
- Tempo (Grafana stack)
- AWS X-Ray, GCP Cloud Trace

---

### Üç Sütunu Birləşdirmək:

Real incident araşdırması:
```
1. ALERT: Error rate spike → Metrics dashboard-da
2. LOGS: Hansı endpoint? Hansı error? → Kibana-da trace_id tap
3. TRACE: Bu request hansı servisdə yavaşladı? → Jaeger-də trace_id ilə axtarış
4. ROOT CAUSE: Span detail-ında bottleneck aşkar
```

---

### SLI/SLO Əlaqəsi:

```
SLI (metric): p99 latency = 450ms
SLO (hədəf): p99 latency < 500ms

Prometheus alert:
histogram_quantile(0.99, http_request_duration_seconds) > 0.5
```

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Observability-ni necə qurursunuz?" sualına "Grafana istifadə edirik" demə. Metrics/Logs/Traces üçlüyünü izah et, hər birinin nə üçün olduğunu söylə, real incident ssenariosunda necə istifadə etdiğini göstər. "Trace ID-ni bütün servislərdə propagate edirik" — bu kaliber praktika interviewer-ı razı salır.

**Follow-up suallar:**
- "RED method nədir?"
- "Distributed tracing olmadan microservice performance problemi necə tapılır?"
- "Log aggregation nə üçün lazımdır?"

**Ümumi səhvlər:**
- Plain text logging (structured logging əvəzinə)
- Log-larda sensitive məlumat (passwords, tokens, PII)
- Metrics olmadan yalnız log-lara güvənmək
- Trace ID-ni servislərdən keçirməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Grafana, Prometheus, Jaeger bilirik" vs "Incident zamanı metrics ilə başlayıb, log-da trace ID tapıb, Jaeger-də root cause aşkar etdik — bu workflow-u tətbiq edirik."

## Nümunələr

### Tipik Interview Sualı
"Production-da yavaşlıq problemi var. Haradan başlayırsınız?"

### Güclü Cavab
"Əvvəlcə metrics dashboard-a baxaram: RED metodunu izləyirəm — rate, error, duration. Hansı endpoint-lərin p99 latency-si artıb? Sonra həmin endpoint üçün log-lara baxıram — structured log-larımızda trace_id var. Bu trace_id-i Jaeger-də axtarıram: hansı span ən çox vaxt aparır? Microservice çağırışı, DB query, ya da external API? Span detail-ında konkret bottleneck görünür. Son bir incidentimizda Stripe API-yə gedən span 8 saniyə qalmışdı — timeout konfigurasiyası səhv idi."

## Praktik Tapşırıqlar
- Laravel-ə Prometheus PHP client inteqrasiya et, RED metrikalar əlavə et
- ELK Stack ya da Grafana Loki lokal qur, structured JSON log göndər
- OpenTelemetry Laravel SDK ilə span-lar yarat
- Grafana dashboard-da SLO widget qur

## Əlaqəli Mövzular
- [05-sla-slo-sli.md](05-sla-slo-sli.md) — SLO metrikalarla necə ölçülür
- [07-incident-response.md](07-incident-response.md) — Observability incident-ı necə sürətləndirir
- [06-oncall-best-practices.md](06-oncall-best-practices.md) — On-call zamanı ilk başvuru: metrics
- [08-capacity-planning.md](08-capacity-planning.md) — Metrics-dən capacity qərarı çıxarmaq
