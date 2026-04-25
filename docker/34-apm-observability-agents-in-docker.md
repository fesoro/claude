# APM və Observability Agent-ləri Docker-də (Sentry, Datadog, New Relic, OTel)

> **Səviyyə (Level):** ⭐⭐⭐ Senior
> **Oxu müddəti:** ~20-25 dəqiqə
> **Kateqoriya:** Docker / Observability

## Nədir? (What is it?)

Container-dəki Laravel app-dan **telemetry data** (metrics, logs, traces, errors) xaricə necə çatır? VPS dünyasında `tail -f laravel.log` kifayət idi. Container dünyasında bir neçə sual çıxır:

- Error-lar **Sentry**-yə necə göndərilir? DSN image-də yoxsa env-də?
- **Datadog** agent harada — hər pod-un yanında (sidecar) yoxsa node-da (DaemonSet)?
- **New Relic** PHP extension image-ə necə bundle olunur?
- **OpenTelemetry** (vendor-neutral) — collector harada deploy olunur?
- **Log-lar** stdout-a yazılsa Datadog/Loki-yə necə çatır?
- **Metrics** üçün `/metrics` endpoint FPM-də necə expose olunur?

Bu sənəd Laravel + Docker kontekstində bu 5 sualı cavablandırır: **Sentry, Datadog, New Relic, OpenTelemetry, Prometheus + Logs**.

## The 4 Observability Pillars

```
┌──────────────────────────────────────────────────────┐
│  Laravel App (in container)                           │
├──────────────────────────────────────────────────────┤
│  1. Logs      → JSON to stdout → shipper → backend   │
│  2. Metrics   → /metrics endpoint → Prometheus       │
│  3. Traces    → OTel SDK → Collector → Jaeger/Tempo  │
│  4. Errors    → Sentry SDK → sentry.io HTTPS         │
└──────────────────────────────────────────────────────┘
```

Hər vendor bu 4 sütunu ya tam (Datadog, New Relic) ya qismən (Sentry — errors+traces, Prometheus — metrics) əhatə edir.

## 1. Sentry — Error & Performance Monitoring

### SDK İnstallı

```bash
composer require sentry/sentry-laravel
```

```php
// config/sentry.php — app/Exceptions/Handler.php-də
public function register(): void
{
    $this->reportable(function (Throwable $e) {
        if (app()->bound('sentry')) {
            app('sentry')->captureException($e);
        }
    });
}
```

### Environment Variables (NEVER bake into image)

```env
# .env.production
SENTRY_LARAVEL_DSN=https://abc123@o12345.ingest.sentry.io/789
SENTRY_TRACES_SAMPLE_RATE=0.1         # 10% transaction sample
SENTRY_PROFILES_SAMPLE_RATE=0.1       # 10% profile sample
SENTRY_SEND_DEFAULT_PII=false
SENTRY_RELEASE=${GITHUB_SHA}           # Image SHA
SENTRY_ENVIRONMENT=production
```

K8s deployment:

```yaml
# k8s/deployment.yaml
spec:
  template:
    spec:
      containers:
        - name: app
          image: myapp:v1.2.3
          env:
            - name: SENTRY_LARAVEL_DSN
              valueFrom:
                secretKeyRef:
                  name: sentry-secret
                  key: dsn
            - name: SENTRY_RELEASE
              value: "v1.2.3"                     # Image tag
            - name: SENTRY_ENVIRONMENT
              value: "production"
```

**Qayda: DSN image-ə bake ETMƏ** — ekspozə olsa, hər kəsin sənin Sentry project-inə error göndərə bilər. Runtime-da env-dən gətir.

### Release & Source Maps (CI-da)

Sentry release-ə görə deploy regression detect edir. CI-da tag et:

```yaml
# .github/workflows/deploy.yml
- name: Create Sentry release
  uses: getsentry/action-release@v1
  env:
    SENTRY_AUTH_TOKEN: ${{ secrets.SENTRY_AUTH_TOKEN }}
    SENTRY_ORG: mycompany
    SENTRY_PROJECT: laravel-api
  with:
    environment: production
    version: ${{ github.sha }}
    # PHP üçün source map yoxdur, amma stack-trace üçün:
    sourcemaps: ./public/js/*.map           # Frontend üçün

- name: Associate commits with release
  run: |
    sentry-cli releases set-commits \
      --auto ${{ github.sha }}
```

### Performance Tracing

```php
// Performance sample
Route::get('/orders', function () {
    $transaction = \Sentry\SentrySdk::getCurrentHub()->getTransaction();

    $span = $transaction?->startChild(
        (new \Sentry\Tracing\SpanContext())
            ->setOp('db.query')
            ->setDescription('Orders index')
    );

    $orders = Order::with('customer')->get();

    $span?->finish();

    return $orders;
});
```

Laravel package otomatik: DB query-lər, HTTP client, queue job-lar, cache trace olunur.

### Dockerfile — Sentry nothing special

```dockerfile
FROM php:8.3-fpm-alpine

# ... app kodu ...
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Sentry SDK pure PHP — heç bir extension lazım deyil
# DSN env-dən gəlir

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
```

## 2. Datadog — Full-Stack Observability

Datadog-un iki əsas komponenti var:
- **dd-agent** — metrics/logs collector (host və ya sidecar)
- **dd-trace-php** — APM trace extension (app image-də)

### Pattern A: Agent DaemonSet + Statsd/DogStatsD

K8s-də dd-agent hər node-da bir pod kimi işləyir (DaemonSet):

```yaml
# Helm ilə
helm repo add datadog https://helm.datadoghq.com
helm install datadog datadog/datadog \
  --set datadog.apiKey=$DD_API_KEY \
  --set datadog.apm.enabled=true \
  --set datadog.logs.enabled=true \
  --set datadog.logs.containerCollectAll=true \
  --set datadog.processAgent.enabled=true
```

App-dan agent-ə:
- **Metrics:** statsd UDP `localhost:8125` (hostPort)
- **Traces:** APM TCP `localhost:8126`
- **Logs:** container stdout → agent scrape edir

```php
// Metrics — composer require datadog/php-datadogstatsd
use DataDog\DogStatsd;

$statsd = new DogStatsd([
    'host' => getenv('DD_AGENT_HOST') ?: 'localhost',
    'port' => 8125,
]);

$statsd->increment('checkout.completed', 1, ['currency' => 'USD']);
$statsd->timing('db.query.time', 142.3, ['query' => 'orders.index']);
```

K8s pod env:
```yaml
env:
  - name: DD_AGENT_HOST
    valueFrom:
      fieldRef:
        fieldPath: status.hostIP         # Node IP — DaemonSet orada
```

### Pattern B: APM Tracer (dd-trace-php Extension)

Extension-ı image-ə əlavə et:

```dockerfile
FROM php:8.3-fpm-alpine

# dd-trace-php yükləyici
RUN apk add --no-cache curl libgcc \
    && curl -LO https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php \
    && php datadog-setup.php --php-bin=all \
    && rm datadog-setup.php

# Extension avtomatik php.ini-də activate olur
# Amma config-i verək
COPY docker/php/99-datadog.ini /usr/local/etc/php/conf.d/

ENV DD_SERVICE="laravel-api"
ENV DD_ENV="production"
# DD_VERSION runtime-da dəyişməlidir

COPY . /var/www/html
```

```ini
; docker/php/99-datadog.ini
[datadog]
datadog.trace.enabled = 1
datadog.trace.debug = 0
datadog.trace.analytics_enabled = 1
datadog.trace.agent_url = "http://${DD_AGENT_HOST}:8126"
datadog.trace.sample_rate = 0.1
datadog.trace.report_hostname = 1

; Laravel integrasiyası default aktivdir
datadog.trace.laravel_enabled = 1
```

K8s deployment:
```yaml
env:
  - name: DD_VERSION
    value: "v1.2.3"                    # Image tag ilə match
  - name: DD_ENV
    value: "production"
  - name: DD_AGENT_HOST
    valueFrom:
      fieldRef:
        fieldPath: status.hostIP
  - name: DD_TRACE_SAMPLE_RATE
    value: "0.1"
```

### Pattern C: Admission Webhook Auto-Injection

Datadog Operator-un feature-i — siz deployment yaratanda webhook avtomatik tracer-i inject edir:

```yaml
metadata:
  labels:
    tags.datadoghq.com/env: production
    tags.datadoghq.com/service: laravel-api
    tags.datadoghq.com/version: v1.2.3
  annotations:
    admission.datadoghq.com/php-lib.version: v0.102.0    # Auto-inject
```

Kod dəyişikliyi yoxdur — webhook init container əlavə edir, PHP extension hazır deploy-da.

### Cost Considerations

Datadog **per-host** hesablayır (~$18-24/host/ay). Container-də 1 pod ≠ 1 host — Datadog host-u node səviyyəsində sayır. 100 pod-lu 10-node kluster = 10 host.

**APM** — per 1M spans. Log ingest GB-lə. **Sampling agressiv et** (10% default). Prod traffic-də `DD_TRACE_SAMPLE_RATE=0.05`.

## 3. New Relic

### PHP Extension

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache curl \
    && curl -L https://download.newrelic.com/php_agent/release/newrelic-php5-11.0.0.17-linux-musl.tar.gz \
       -o newrelic.tar.gz \
    && tar -xzf newrelic.tar.gz \
    && cd newrelic-php5-* \
    && NR_INSTALL_USE_CP_NOT_LN=1 \
       NR_INSTALL_SILENT=1 \
       ./newrelic-install install \
    && cd .. \
    && rm -rf newrelic-php5-* newrelic.tar.gz

COPY docker/php/newrelic.ini /usr/local/etc/php/conf.d/newrelic.ini
```

```ini
; docker/php/newrelic.ini
[newrelic]
newrelic.license = "${NEW_RELIC_LICENSE_KEY}"
newrelic.appname = "Laravel API (${APP_ENV})"
newrelic.distributed_tracing_enabled = true
newrelic.transaction_tracer.detail = 1
newrelic.transaction_tracer.threshold = "apdex_f"
newrelic.error_collector.enabled = true
newrelic.daemon.address = "newrelic-daemon:31339"   ; Sidecar
```

### Daemon Sidecar

```yaml
# k8s/deployment.yaml
spec:
  containers:
    - name: app
      image: myapp:v1.2.3
      env:
        - name: NEW_RELIC_LICENSE_KEY
          valueFrom:
            secretKeyRef:
              name: newrelic-secret
              key: license
    - name: newrelic-daemon
      image: newrelic/php-daemon:latest
      ports:
        - containerPort: 31339
      env:
        - name: NR_DAEMON_LICENSE_KEY
          valueFrom:
            secretKeyRef:
              name: newrelic-secret
              key: license
```

### Manual Instrumentation

```php
if (extension_loaded('newrelic')) {
    newrelic_name_transaction('Orders/Index');
    newrelic_add_custom_parameter('customer_tier', $user->tier);
    newrelic_notice_error('Payment failed', $exception);
}
```

## 4. OpenTelemetry — Vendor-Neutral

### Niyə OTel?

- Vendor lock-in yoxdur — eyni SDK Jaeger, Tempo, Honeycomb, Datadog, New Relic-ə göndərir
- CNCF standard
- SDK + Collector arxitekturası
- Auto-instrumentation library-lər var

### PHP SDK + Auto-Instrumentation

```dockerfile
FROM php:8.3-fpm-alpine

# OpenTelemetry extension — auto-instrumentation üçün
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry \
    && apk del $PHPIZE_DEPS

COPY composer.json composer.lock ./
# OTel + auto-instrumentation library-lər
RUN composer require \
    open-telemetry/sdk \
    open-telemetry/exporter-otlp \
    open-telemetry/opentelemetry-auto-laravel \
    open-telemetry/opentelemetry-auto-guzzle \
    open-telemetry/opentelemetry-auto-pdo

COPY docker/php/otel.ini /usr/local/etc/php/conf.d/
```

```ini
; docker/php/otel.ini
[opentelemetry]
otel.autoload.enabled=true
```

### Bootstrap (app/bootstrap.php və ya ServiceProvider)

```php
// app/Providers/OpenTelemetryServiceProvider.php
use OpenTelemetry\API\Globals;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;

public function register(): void
{
    $resource = ResourceInfoFactory::defaultResource()
        ->merge(ResourceInfoFactory::create(Attributes::create([
            'service.name'    => config('app.name'),
            'service.version' => env('APP_VERSION', 'unknown'),
            'deployment.environment' => config('app.env'),
        ])));

    $exporter = new SpanExporter(
        (new \OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory())
            ->create(env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318') . '/v1/traces', 'application/x-protobuf')
    );

    $tracerProvider = TracerProvider::builder()
        ->addSpanProcessor(new BatchSpanProcessor($exporter, \OpenTelemetry\API\Common\Time\Clock::getDefault()))
        ->setResource($resource)
        ->build();

    Globals::registerInitializer(fn() => Globals::tracerProvider($tracerProvider));
}
```

### OTel Collector (Sidecar / DaemonSet)

Collector app-dan OTLP alır, istənilən backend-ə export edir:

```yaml
# docker-compose.yml
services:
  app:
    # ...
    environment:
      OTEL_EXPORTER_OTLP_ENDPOINT: http://otel-collector:4318
      OTEL_SERVICE_NAME: laravel-api

  otel-collector:
    image: otel/opentelemetry-collector-contrib:latest
    command: ["--config=/etc/otelcol/config.yaml"]
    volumes:
      - ./docker/otel/config.yaml:/etc/otelcol/config.yaml:ro
    ports:
      - "4317:4317"   # gRPC
      - "4318:4318"   # HTTP
      - "8888:8888"   # Prometheus metrics
```

```yaml
# docker/otel/config.yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
      http:
        endpoint: 0.0.0.0:4318

processors:
  batch:
    timeout: 5s
    send_batch_size: 1024
  
  # Tail sampling: error və ya slow trace-ləri saxla
  tail_sampling:
    decision_wait: 10s
    num_traces: 100
    policies:
      - name: errors-policy
        type: status_code
        status_code: {status_codes: [ERROR]}
      - name: slow-policy
        type: latency
        latency: {threshold_ms: 500}
      - name: sample-rest
        type: probabilistic
        probabilistic: {sampling_percentage: 5}

  resource:
    attributes:
      - key: deployment.environment
        value: production
        action: upsert

exporters:
  # Jaeger (self-hosted)
  otlp/jaeger:
    endpoint: jaeger:4317
    tls: {insecure: true}
  
  # Honeycomb
  otlp/honeycomb:
    endpoint: api.honeycomb.io:443
    headers:
      x-honeycomb-team: ${HONEYCOMB_API_KEY}
  
  # Prometheus
  prometheus:
    endpoint: "0.0.0.0:8888"

service:
  pipelines:
    traces:
      receivers: [otlp]
      processors: [batch, tail_sampling, resource]
      exporters: [otlp/jaeger, otlp/honeycomb]
    metrics:
      receivers: [otlp]
      processors: [batch, resource]
      exporters: [prometheus]
```

### Head vs Tail Sampling

**Head-based (SDK-da):**
```
Span yaranır → %90 drop → %10 collector-ə göndər
```
- Sadədir
- Collector-ə yük azalır
- **Amma** — error və slow trace-lərin çoxunu itirə bilərsən

**Tail-based (Collector-də):**
```
Bütün span-lar collector-ə → tam trace gözlə → qərar ver → keep / drop
```
- Error-lu trace-ləri saxla (100%)
- Slow trace-ləri saxla (100%)
- Qalanından %5 sample
- **Amma** — collector-də bütün trace memory-də saxlanılmalıdır

**Qayda:** Head sampling SDK-da aggressive (10%), amma error-ları həmişə keep et (`AlwaysOnSampler` for errors). Sonra collector-də tail sampling refinement.

## 5. Logs — Structured JSON to Stdout

### Monolog JSON Formatter

```php
// config/logging.php
use Monolog\Formatter\JsonFormatter;

'channels' => [
    'stdout' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'formatter' => JsonFormatter::class,
        'handler_with' => [
            'stream' => 'php://stdout',
        ],
        'processors' => [
            \Monolog\Processor\WebProcessor::class,
            \Monolog\Processor\MemoryUsageProcessor::class,
            // Custom: request_id əlavə et
            \App\Logging\RequestIdProcessor::class,
        ],
    ],
],
```

```php
// app/Logging/RequestIdProcessor.php
class RequestIdProcessor
{
    public function __invoke(array $record): array
    {
        $record['extra']['request_id'] = request()?->header('X-Request-ID') 
            ?? \Illuminate\Support\Str::uuid()->toString();
        $record['extra']['trace_id'] = \OpenTelemetry\API\Trace\Span::getCurrent()
            ->getContext()->getTraceId();
        return $record;
    }
}
```

Output:
```json
{
  "message": "Order created",
  "context": {"order_id": 12345, "customer_id": 99},
  "level": 200,
  "level_name": "INFO",
  "channel": "orders",
  "datetime": "2026-04-25T12:34:56+00:00",
  "extra": {
    "request_id": "a1b2c3",
    "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736",
    "memory_usage": "12 MB"
  }
}
```

### Don't Write to Files in Containers

**YANLIŞ:**
```php
'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log')],
```

**Problem:**
- Container silinsə, log-lar itir
- Fayl böyüyür, disk dolur
- Rotation yoxdur (logrotate container-da yoxdur)
- Volume mount etsən multi-pod-da race

**DOĞRU:** stdout-a yaz, log shipper (Fluentbit, Datadog agent, Promtail) stdout-u scrape edir:

```env
LOG_CHANNEL=stdout
```

```yaml
# Kubernetes default: stdout → node-da /var/log/containers/
# Promtail / Fluent Bit oradan oxuyur və Loki/Datadog-a göndərir
```

### Loki + Promtail Example

```yaml
# docker-compose.yml
services:
  app:
    # ... log-ları stdout-a

  loki:
    image: grafana/loki:latest
    ports: ["3100:3100"]

  promtail:
    image: grafana/promtail:latest
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./docker/promtail/config.yml:/etc/promtail/config.yml
    command: -config.file=/etc/promtail/config.yml
```

```yaml
# docker/promtail/config.yml
server:
  http_listen_port: 9080

clients:
  - url: http://loki:3100/loki/api/v1/push

scrape_configs:
  - job_name: docker
    docker_sd_configs:
      - host: unix:///var/run/docker.sock
    relabel_configs:
      - source_labels: ['__meta_docker_container_name']
        target_label: 'container'
    pipeline_stages:
      - json:
          expressions:
            level: level_name
            trace_id: extra.trace_id
      - labels:
          level:
```

## 6. Metrics — Prometheus Exporter

### App Metrics Endpoint

```bash
composer require promphp/prometheus_client_php
```

```php
// app/Http/Controllers/MetricsController.php
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis;

class MetricsController
{
    public function __invoke()
    {
        $adapter = new Redis([
            'host' => env('REDIS_HOST'),
            'port' => env('REDIS_PORT', 6379),
        ]);
        $registry = new CollectorRegistry($adapter);

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return response($result, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
```

```php
// routes/web.php
Route::get('/metrics', MetricsController::class)
    ->middleware('throttle:60,1');
```

Application-da metric yaz:

```php
// app/Services/OrderService.php
$counter = $registry->getOrRegisterCounter(
    'app', 'orders_created_total', 'Total orders created', ['currency']
);
$counter->inc(['USD']);

$histogram = $registry->getOrRegisterHistogram(
    'app', 'checkout_duration_seconds', 'Checkout duration', ['tier'],
    [0.01, 0.05, 0.1, 0.5, 1, 2, 5]
);
$histogram->observe($duration, ['gold']);
```

### PHP-FPM Exporter (Pool Metrics)

PHP-FPM-in özünün `/status` səhifəsi var (active processes, slow requests). `hipages/php-fpm_exporter` Prometheus format-ına çevirir:

```yaml
# docker-compose.yml
services:
  app:
    image: myapp:v1
    # PHP-FPM status pool açıq olsun
    # docker/php/fpm-pool.conf-də: pm.status_path = /status

  fpm-exporter:
    image: hipages/php-fpm_exporter:latest
    environment:
      PHP_FPM_SCRAPE_URI: "tcp://app:9000/status"
      PHP_FPM_FIX_PROCESS_COUNT: "false"
    ports:
      - "9253:9253"
```

Prometheus scrape config:
```yaml
scrape_configs:
  - job_name: 'laravel-app'
    static_configs:
      - targets: ['app:8000']
        labels: {service: 'laravel-api'}
    metrics_path: /metrics
  
  - job_name: 'php-fpm'
    static_configs:
      - targets: ['fpm-exporter:9253']
```

## Tam Dockerfile — dd-trace + Sentry

```dockerfile
FROM php:8.3-fpm-alpine AS base

# PHP extensions
RUN apk add --no-cache \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        mbstring \
        opcache

# dd-trace-php extension (APM)
RUN apk add --no-cache curl libgcc \
    && curl -LO https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php \
    && php datadog-setup.php --php-bin=all --enable-appsec --enable-profiling \
    && rm datadog-setup.php

# OpenTelemetry (əgər OTel də lazımdır)
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry \
    && apk del $PHPIZE_DEPS

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Sentry + Prometheus SDK
RUN composer require \
        sentry/sentry-laravel \
        promphp/prometheus_client_php \
    --no-interaction --no-progress

# Config
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/99-datadog.ini /usr/local/etc/php/conf.d/99-datadog.ini

# App
COPY . /var/www/html
WORKDIR /var/www/html

# Env placeholders (real values at runtime)
ENV DD_SERVICE="laravel-api" \
    DD_ENV="production" \
    DD_TRACE_ENABLED="true" \
    DD_LOGS_INJECTION="true"
# DD_AGENT_HOST, SENTRY_LARAVEL_DSN, DD_VERSION — runtime

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
```

## Best Practices

1. **Secrets-ı image-ə bake ETMƏ** — DSN, license key, API key runtime env-dən.
2. **Release / version tag-lə** — image SHA-nı SENTRY_RELEASE, DD_VERSION-a ver.
3. **Sampling aggressive** — prod traffic-də 1-10% traces. Error-lar 100%.
4. **Structured JSON logs** — `JsonFormatter` stdout-a, **file yox**.
5. **Trace ID-ni log-a inject et** — `DD_LOGS_INJECTION=true` və ya manual processor.
6. **`/metrics` endpoint qoru** — internal IP, auth, və ya rate-limit.
7. **Cost-u monitor et** — Datadog host-a görə, log GB-ə görə hesablayır. Sampling + filtering.
8. **OTel vendor-neutral üstünlüyü** — provider dəyişmək rahat, SDK eyni.
9. **Tail sampling collector-də** — error və slow trace-ləri keep, qalandan 5% sample.
10. **FPM pool metrics** — `hipages/php-fpm_exporter` ilə worker saturation-u gör.
11. **Health endpoint trace-siz** — `/health`, `/metrics` trace etmə (noise).
12. **Log volume-u məhdudlaşdır** — `LOG_LEVEL=warning` prod-da, debug log-lar CloudWatch-a GB qoyur.

## Tələlər (Gotchas)

### 1. DSN image-də

**Problem:** Dockerfile-da `ENV SENTRY_LARAVEL_DSN=https://...` yazılıb, image leak olanda hər kəs sənə error göndərir.

**Həll:** Runtime-da env, K8s Secret, AWS Secrets Manager.

### 2. Release tag yoxdur

**Problem:** Sentry-də "regression" feature işləmir, versiya 1 ilə versiya 2 arasındakı fərq görünmür.

**Həll:** `SENTRY_RELEASE=${GITHUB_SHA}` və CI-da `sentry-cli releases new`.

### 3. APM extension build-də timeout

**Problem:** `pecl install opentelemetry` alpine build-də 10 dəqiqə çəkir.

**Həll:** Multi-stage build, extension layer-ı cache et. Və ya pre-built image (DataDog `datadog/dd-trace-php-base`).

### 4. Datadog agent localhost-da tapılmır

**Problem:** App pod-dan `localhost:8126`-ya qoşulmur.

**Səbəb:** `localhost` pod-un özüdür, agent başqa pod-dadır.

**Həll:** `DD_AGENT_HOST` node IP-yə set et:
```yaml
env:
  - name: DD_AGENT_HOST
    valueFrom:
      fieldRef:
        fieldPath: status.hostIP
```

Və ya agent-i sidecar kimi deploy et (DaemonSet əvəzinə).

### 5. Log duplication

**Problem:** Sentry SDK error-u öz-özünə Sentry-yə göndərir, amma Monolog da stdout-a yazır, sonra Datadog da pick up edir. Error 2 yerdə.

**Həll:** Log-da `error_level` filter — stdout-da `info+` saxla, `error+` yalnız Sentry-yə. Və ya Sentry-ni `critical+` ilə məhdudlaşdır.

### 6. Trace ID log-a çatmır

**Problem:** Log-da `trace_id` yox — Loki-də error-dan trace-ə link yaratmaq olmur.

**Həll:** Monolog processor əlavə et — `OpenTelemetry\API\Trace\Span::getCurrent()->getContext()->getTraceId()`. Və ya `DD_LOGS_INJECTION=true`.

### 7. /metrics endpoint public

**Problem:** `/metrics` endpoint açıq internet-də — rəqib bütün metrikləri oxuyur, potensial DoS.

**Həll:** Network policy ilə yalnız Prometheus pod-dan icazə ver, middleware ilə IP allow-list, və ya basic auth.

### 8. Worker trace olunmur

**Problem:** Web request-lər trace-də var, amma queue job-lar yoxdur.

**Səbəb:** APM extension FPM-də auto-start olur, CLI-da yox.

**Həll:** Worker entrypoint-də:
```bash
php -d "datadog.trace.cli_enabled=1" artisan queue:work
```
Və ya worker env-də `DD_TRACE_CLI_ENABLED=1`.

## Müsahibə Sualları

### 1. Sentry DSN-i haralarda saxlayırsınız?

**Cavab:** **Runtime env variable-da** (K8s Secret, AWS Secrets Manager, Vault). Image-ə bake ETMƏ — image leak olanda hər kəs sənə error göndərə bilər (noisy, potentially malicious). Həmçinin DSN dəyişsə image yenidən build lazım olur.

### 2. Datadog agent-i necə deploy edirsiniz K8s-də?

**Cavab:** **DaemonSet** — hər node-da bir agent pod-u. App pod-ları `status.hostIP` ilə node IP-sini tapıb agent-ə (statsd 8125, APM 8126) göndərir. Alternativ: sidecar (hər pod-un yanında agent) — resource-consuming, istisnalarda (strict isolation). Datadog Operator + admission webhook auto-inject variantı da var — kod toxunmadan.

### 3. OTel head vs tail sampling fərqi?

**Cavab:** **Head sampling** SDK-da qərar verir — span yaranan kimi drop və ya keep. Sadə, network saves. Amma error-ları və slow trace-ləri səhvən drop edə bilərsən. **Tail sampling** Collector-də — tam trace gözləyir, sonra qərar verir. Error (100%), slow (100%), qalan (%5). Daha ağıllı, amma collector-də memory istəyir. İdeal: SDK-da aggressive head (10%), Collector-də error-lara always-on.

### 4. Laravel log-ları Docker-də hara yazırsınız?

**Cavab:** **`stdout`-a JSON formatında** — fayla YOX. Container ephemeral-dır, fayl itir. Log shipper (Fluent Bit, Promtail, Datadog agent) node-dakı `/var/log/containers/*.log`-u scrape edir, backend-ə (Loki, CloudWatch, Datadog Logs) göndərir. Monolog-da `JsonFormatter`, processor-la `request_id`, `trace_id` inject edir.

### 5. `/metrics` endpoint-u Laravel-də necə qoruyursunuz?

**Cavab:** **Network-level** — K8s NetworkPolicy yalnız Prometheus pod-dan icazə verir. **App-level** — middleware ilə IP allow-list (`10.0.0.0/8` internal), və ya basic auth, və ya ayrıca port (8080) Service-də `ClusterIP` only (public Ingress-də yox). Public-ə expose olsa DoS və information disclosure.

### 6. APM extension bütün container-lərdə lazımdırmı?

**Cavab:** **Yox** — yalnız app konteynerlərində (web, worker, scheduler). DB, Redis, MinIO konteynerlərində yox — onlar Datadog-un öz integrations-ı ilə izlənir (Redis integration, Postgres integration agent-də). Amma worker-ların APM extension-u olmalıdır və `DD_TRACE_CLI_ENABLED=1` aktiv olmalıdır (default yalnız FPM-də).

### 7. Datadog cost-u necə azaldırsınız?

**Cavab:** **Sampling** — `DD_TRACE_SAMPLE_RATE=0.05` (5%). **Log level** — prod-da `warning+`, debug log-lar ancaq prodlog-lar lazım olanda. **Host optimization** — pod density artır (10 pod/node → 20 pod/node), Datadog host-a görə hesablayır. **Log filtering** — agent-də `exclude_at_match` ilə health check log-larını drop et. **Metric cardinality** — yüksək-cardinality tag-lərdən qaç (user_id, request_id metric-də tag olmasın).

### 8. Vendor-lock-in üçün OTel yoxsa native SDK?

**Cavab:** **OTel** vendor-neutral — eyni kod Jaeger, Tempo, Honeycomb, Datadog-a göndərir. Provider dəyişmək Collector config-i dəyişməklə olur. **Native SDK** (dd-trace-php, newrelic) daha dərin integrasiya — platform-specific feature-lər (Datadog Watchdog, NR Transactions). Layihənin fazasından asılıdır: early stage OTel + Honeycomb, böyüdükdə Datadog-a köçürmək rahatdır. Enterprise-də Datadog birbaşa APM instrumentation-ı OTel-dən daha dolğun verir.
