# OpenTelemetry

## Nədir? (What is it?)

OpenTelemetry (OTel) – telemetriya (traces, metrics, logs) toplamaq üçün CNCF-in açıq standart və alətlər yığınıdır. 2019-da OpenTracing və OpenCensus birləşməsindən yaranıb. Məqsəd: vendor-neutral, unified observability. Developer kod yazır, OTel SDK telemetry data yaradır, OTLP protokolu ilə istənilən backend-ə (Jaeger, Tempo, Prometheus, Datadog, New Relic) göndərir. Artıq vendor-specific agent lazım deyil – bir dəfə instrument edirsən, backend-i istənilən vaxt dəyişə bilərsən.

## Əsas Konseptlər (Key Concepts)

### Üç Sütun (Three Pillars)

```
1. TRACES (izlər)
   Distributed request-in bütün yolu
   Span-lardan ibarətdir (operation, müddət, atribut)
   Həll edir: "bu request-in bottleneck-i haradadır?"

2. METRICS (ölçülər)
   Ədədi ölçülər, aggregated (sum, avg, histogram)
   Time series formatında
   Həll edir: "sistemin sağlamlıq vəziyyəti necədir?"

3. LOGS (jurnallar)
   Structured və unstructured event-lər
   Konkret hadisələr haqqında məlumat
   Həll edir: "konkret bu hadisədə nə baş verdi?"

OTel bütün üçünü trace context ilə bağlayır
(trace_id log-da və metric-də də olur)
```

### OpenTelemetry Arxitekturası

```
[Application]
     ↓
[OTel SDK]  ← manual/auto instrumentation
     ↓ (batch, retry, enrichment)
[OTLP Exporter] ← gRPC/HTTP
     ↓
[OTel Collector] (optional, recommended)
     ├─→ Jaeger (traces)
     ├─→ Prometheus (metrics)
     ├─→ Loki (logs)
     └─→ Vendor backend

OTel Collector nə edir:
- Receive (OTLP, Jaeger, Zipkin, Prometheus)
- Process (filter, batch, enrich, sample)
- Export (istənilən backend-ə)
```

### OTLP Protokolu

```
OTLP (OpenTelemetry Protocol):
   - gRPC (port 4317) və HTTP/protobuf (port 4318)
   - Binary, yüksək performans
   - Streaming dəstəklənir
   - Retry və backoff built-in

Niyə önəmlidir:
   - Vendor lock-in yoxdur
   - Collector-lar OTLP qəbul edir
   - Bütün əsas backend-lər dəstəkləyir
```

### Signal Komponentləri

```
TRACE komponentləri:
   TracerProvider → Tracer → Span
   Span: name, start/end time, attributes, events, status
   Context propagation: traceparent header (W3C)

METRIC komponentləri:
   MeterProvider → Meter → Instrument
   Instrument növləri:
   - Counter (yalnız artır)
   - UpDownCounter (artır/azalır)
   - Histogram (distribution)
   - Gauge (anlıq dəyər)

LOG komponentləri:
   LoggerProvider → Logger → LogRecord
   (hələ də bəzi dillərdə developing)
```

### Auto-instrumentation vs Manual

```
AUTO-INSTRUMENTATION:
   Agent kod dəyişmədən instrument edir
   Java: -javaagent:opentelemetry-javaagent.jar
   Python: opentelemetry-instrument python app.py
   Node: --require @opentelemetry/auto-instrumentations-node
   PHP: ext-opentelemetry (auto-instrumentation laravel ilə)
   
   + Tez başlamaq
   - Özəl biznes metrika azdır

MANUAL INSTRUMENTATION:
   Kodda özün span və metric yaradırsan
   
   + Dəqiq biznes konteksti
   - Daha çox kod yazırsan

REAL PATTERN: İkisi birlikdə
```

### Sampling

```
Problem: hər trace-i saxlasaq, storage partlayır

HEAD SAMPLING (sadə):
   Başlanğıcda qərar: saxlayaq, ya yox
   Probability: 10% → hər 10 trace-dən 1-i
   
TAIL SAMPLING (ağıllı, Collector-da):
   Request bitdikdən sonra qərar
   Xətalı və slow trace-ləri saxla
   "Error olanları 100%, normalları 1%"
```

## Praktiki Nümunələr (Practical Examples)

### OTel Collector Konfiqurasiyası

```yaml
# otel-collector-config.yaml
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
    send_batch_size: 1024
  memory_limiter:
    check_interval: 1s
    limit_mib: 512
  resource:
    attributes:
      - key: environment
        value: production
        action: insert
  tail_sampling:
    decision_wait: 30s
    policies:
      - name: errors
        type: status_code
        status_code: { status_codes: [ERROR] }
      - name: slow
        type: latency
        latency: { threshold_ms: 1000 }
      - name: default
        type: probabilistic
        probabilistic: { sampling_percentage: 5 }

exporters:
  otlp/tempo:
    endpoint: tempo:4317
    tls: { insecure: true }
  prometheusremotewrite:
    endpoint: http://mimir:9090/api/v1/push
  loki:
    endpoint: http://loki:3100/loki/api/v1/push

service:
  pipelines:
    traces:
      receivers: [otlp]
      processors: [memory_limiter, tail_sampling, batch]
      exporters: [otlp/tempo]
    metrics:
      receivers: [otlp]
      processors: [memory_limiter, batch]
      exporters: [prometheusremotewrite]
    logs:
      receivers: [otlp]
      processors: [memory_limiter, batch]
      exporters: [loki]
```

### Kubernetes-də Collector (DaemonSet)

```yaml
apiVersion: apps/v1
kind: DaemonSet
metadata:
  name: otel-collector
  namespace: observability
spec:
  selector:
    matchLabels: { app: otel-collector }
  template:
    metadata:
      labels: { app: otel-collector }
    spec:
      containers:
        - name: otelcol
          image: otel/opentelemetry-collector-contrib:0.95.0
          args: ["--config=/conf/config.yaml"]
          ports:
            - name: otlp-grpc
              containerPort: 4317
            - name: otlp-http
              containerPort: 4318
          volumeMounts:
            - name: config
              mountPath: /conf
      volumes:
        - name: config
          configMap: { name: otel-collector-config }
```

### W3C Trace Context

```http
# HTTP request header-lərində yayılır
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
             │  │                                │                │
             │  │                                │                └─ flags (sampled)
             │  │                                └─ parent span id
             │  └─ trace id
             └─ version

tracestate: vendor1=value1,vendor2=value2
```

## PHP/Laravel ilə İstifadə

### Quraşdırma

```bash
# PHP OpenTelemetry SDK
composer require open-telemetry/sdk
composer require open-telemetry/exporter-otlp
composer require open-telemetry/opentelemetry-auto-laravel
composer require open-telemetry/opentelemetry-auto-psr18   # Guzzle
composer require open-telemetry/opentelemetry-auto-pdo

# Əgər auto-instrumentation üçün ext lazımdırsa:
pecl install opentelemetry
echo "extension=opentelemetry" >> /etc/php/8.2/cli/php.ini
```

### Service Provider Qurulması

```php
// app/Providers/OpenTelemetryServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!config('otel.enabled', false)) {
            return;
        }

        $resource = ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => config('app.name'),
                ResourceAttributes::SERVICE_VERSION => config('app.version', '1.0.0'),
                ResourceAttributes::DEPLOYMENT_ENVIRONMENT => config('app.env'),
            ]))
        );

        $transport = (new OtlpHttpTransportFactory())->create(
            config('otel.endpoint', 'http://otel-collector:4318') . '/v1/traces',
            'application/x-protobuf'
        );

        $exporter = new SpanExporter($transport);

        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new BatchSpanProcessor($exporter, Clock::getDefault()))
            ->setResource($resource)
            ->build();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setPropagator(
                \OpenTelemetry\API\Trace\Propagation\TraceContextPropagator::getInstance()
            )
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }
}
```

### Manual Span

```php
// app/Services/PaymentService.php
<?php

namespace App\Services;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

class PaymentService
{
    public function charge(int $orderId, int $amount): bool
    {
        $tracer = Globals::tracerProvider()->getTracer('payment-service');

        $span = $tracer->spanBuilder('payment.charge')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('order.id', $orderId)
            ->setAttribute('payment.amount', $amount)
            ->setAttribute('payment.currency', 'USD')
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = $this->gateway->charge($orderId, $amount);
            $span->setAttribute('payment.transaction_id', $response->transactionId);
            $span->setStatus(StatusCode::STATUS_OK);
            return true;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

### HTTP Middleware (Manual)

```php
// app/Http/Middleware/TraceRequest.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;

class TraceRequest
{
    public function handle(Request $request, Closure $next)
    {
        $tracer = Globals::tracerProvider()->getTracer('http');

        // Inbound traceparent header-ini parse et
        $parentContext = Globals::propagator()->extract(
            $request->headers->all(),
        );

        $span = $tracer->spanBuilder($request->method() . ' ' . $request->path())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setParent($parentContext)
            ->setAttribute('http.method', $request->method())
            ->setAttribute('http.url', $request->fullUrl())
            ->setAttribute('http.route', $request->route()?->uri())
            ->setAttribute('http.user_agent', $request->userAgent())
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = $next($request);
            $span->setAttribute('http.status_code', $response->status());
            
            if ($response->status() >= 500) {
                $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
            }
            
            return $response;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

### Log-Trace Korrelyasiyası

```php
// config/logging.php-də processor əlavə et
use Monolog\Processor\ProcessorInterface;
use OpenTelemetry\API\Trace\Span;

class OtelLogProcessor implements ProcessorInterface
{
    public function __invoke(array|\Monolog\LogRecord $record)
    {
        $context = Span::getCurrent()->getContext();

        if ($context->isValid()) {
            $record['extra']['trace_id'] = $context->getTraceId();
            $record['extra']['span_id'] = $context->getSpanId();
        }

        return $record;
    }
}

// config/otel.php
return [
    'enabled' => env('OTEL_ENABLED', false),
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318'),
    'service_name' => env('OTEL_SERVICE_NAME', 'laravel-app'),
];
```

### Custom Metric

```php
use OpenTelemetry\API\Globals;

$meter = Globals::meterProvider()->getMeter('orders');

$orderCounter = $meter->createCounter(
    'orders.created',
    'orders',
    'Total orders created'
);

// Order yaradıldıqda:
$orderCounter->add(1, [
    'currency' => $order->currency,
    'payment_method' => $order->payment_method,
]);

$histogram = $meter->createHistogram(
    'order.processing.duration',
    'ms',
    'Order processing duration'
);
$histogram->record($durationMs, ['plan' => $order->plan]);
```

## Interview Sualları (Q&A)

**S1: OpenTelemetry ilə Jaeger-in fərqi nədir?**
C: OpenTelemetry – **instrumentation və data toplama standart/SDK**. Jaeger – **backend** (trace storage + UI). OTel data yaradır, Jaeger data saxlayır və göstərir. OTel Jaeger-ə export edə bilər. Əvvəllər Jaeger öz client SDK-larını təklif edirdi, amma artıq deprecate edib – OTel tövsiyə olunur.

**S2: OTLP niyə gRPC və HTTP hər ikisini dəstəkləyir?**
C: gRPC daha performanslıdır (binary, streaming, multiplex), amma bəzi mühitlərdə (serverless, browser) gRPC çətin olur. HTTP/protobuf daha sadə deploy olunur, firewall-dan asan keçir. Production-da adətən gRPC, edge/serverless-də HTTP işlədilir.

**S3: Head və tail sampling-in fərqi nədir?**
C: **Head sampling** trace başlayanda random qərar qəbul edir (məs. 10%). Sadə, amma "error trace-ləri hamısını saxla" kimi qayda qoymaq olmur. **Tail sampling** trace bitdikdən sonra qərar qəbul edir – Collector-da buffer saxlanır, sonra sampling policy tətbiq olunur. Tail sampling ağıllıdır (error-ları və slow-ları saxla), amma memory istəyir.

**S4: OTel Collector niyə istifadə etməliyik, birbaşa backend-ə göndərmək olmaz?**
C: Olur, amma Collector tövsiyə olunur: (1) application kodunda backend-specific konfiq olmur, (2) retry/batch Collector-da mərkəzləşir, (3) sampling və processing Collector-da edilir (application-a yüklənmir), (4) multiple backend-ə export asan, (5) kredensiallar application-da saxlanmır.

**S5: W3C Trace Context necə işləyir?**
C: HTTP request-də `traceparent` header sabit formatda olur: `00-{trace_id}-{span_id}-{flags}`. Hər service oxuyur, öz span-ını bu trace_id ilə yaradır, parent olaraq əvvəlki span_id-ni qeyd edir. Bu sayədə bütün microservice-lərdə request "zəncir" kimi qalır. `tracestate` vendor-specific əlavə məlumat saxlayır.

**S6: Auto-instrumentation Laravel-də nəyi avtomatik izləyir?**
C: `open-telemetry/opentelemetry-auto-laravel` paketi: HTTP request (route, method, status), DB query (Eloquent, DB facade), Cache operations, Queue jobs, HTTP client çağırışları (Guzzle), Redis komandaları. Sən yalnız SDK qurursan və extension aktiv edirsən – kod dəyişməz. Özəl biznes məntiqi üçün manual span əlavə edirsən.

**S7: Trace, metric, log korrelyasiyası necə təmin olunur?**
C: Hər log record-a `trace_id` və `span_id` attribute əlavə olunur (Monolog processor vasitəsilə). Grafana-da Tempo trace-i açıb "Related logs" düyməsi ilə Loki-də eyni trace_id olan log-ları göstərir. Exemplar-lar vasitəsilə Prometheus metric-lərdən birbaşa trace-ə keçid mümkündür. Bu unified observability deyilir.

**S8: Span və Event arasında fərq nədir?**
C: **Span** vaxt aralığını təmsil edir (start + end + müddət). **Event** isə span içində anlıq nöqtə hadisəsidir (timestamp + attribute-lər). Məsələn, span "HTTP request"-dir, event isə "cache miss", "retry #1" kimi olur. Event-lərin öz müddəti yoxdur.

**S9: OpenTelemetry context propagation sync kod-a uyğundur. Async PHP (Swoole, ReactPHP) üçün nə etməli?**
C: Context coroutine-specific olmalıdır. OTel SDK-nın Context API-si manual propagation dəstəkləyir. Swoole-da hər coroutine-də kontext ötürülməlidir – `Context::setValue()` və `Context::getCurrent()` ilə. Auto-instrumentation Swoole-a hələlik tam stabil deyil, manual yanaşma daha etibarlıdır.

**S10: OpenTelemetry SDK-nı production-da yandırmaq performansa necə təsir edir?**
C: Adətən 1-5% CPU overhead, bir neçə MB RAM. Batch exporter və sampling performance-ı optimize edir. Həssas workload-lar üçün: (1) head sampling 10-20% qoy, (2) BatchSpanProcessor parametrlərini tənzimlə (queue size, schedule), (3) attribute sayını məhdudlaşdır, (4) sync exporter istifadə etmə. Düzgün konfiqurasiya ilə production-safe-dir.

## Best Practices

1. **OTel Collector işlət** application-dan birbaşa backend-ə yox.
2. **Resource attribute-ləri düzgün qoy** – service.name, service.version, deployment.environment.
3. **Auto + manual** instrumentation birləşdir.
4. **Sampling** tətbiq et – hər trace-i saxlama (tail sampling production-da ən yaxşısıdır).
5. **Semantic Conventions** riayət et – standard attribute adları işlət (`http.method`, `db.system`).
6. **Trace context propagation** hər xarici HTTP client çağırışında baş verməlidir.
7. **Sensitive data attribute kimi əlavə etmə** (password, token).
8. **Batch processor** istifadə et (performance üçün).
9. **Log-trace korrelyasiyası** qur (trace_id log-da olsun).
10. **OTLP gRPC** production-da, HTTP fallback üçün.
11. **Collector high availability** – bir neçə replica işlət (DaemonSet K8s-də).
12. **Memory limiter processor** qoy ki, OOM olmasın.
13. **OTEL_SDK_DISABLED=true** environment variable ilə tez söndürə biləsən (development/test).
14. **Version pinning** – SDK və Collector version-larını sabitləşdir.
15. **Dashboard və alert** yarat – OTel-in özünün sağlamlığı üçün.
