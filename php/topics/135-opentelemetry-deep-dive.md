# OpenTelemetry Deep Dive

## Mündəricat
1. [OTel nədir?](#otel-nədir)
2. [Üç Sütun: Traces, Metrics, Logs](#üç-sütun-traces-metrics-logs)
3. [Instrumentation](#instrumentation)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## OTel nədir?

```
OpenTelemetry — observability üçün açıq standart.
CNCF layihəsi (Cloud Native Computing Foundation).
OpenTracing + OpenCensus birləşməsindən yarandı.

Nə deyil:
  ✗ Backend deyil (data saxlamır)
  ✗ Visualization deyil
  ✗ Alerting deyil

Nədir:
  ✓ Telemetry data yaratmaq üçün SDK/API
  ✓ Vendor-agnostic (Jaeger, Zipkin, Datadog, Grafana)
  ✓ Traces, Metrics, Logs üçün unified format

Axın:
  PHP App (OTel SDK)
      → OTel Collector (aggregate, batch, export)
          → Jaeger (traces)
          → Prometheus (metrics)
          → Elasticsearch (logs)
```

---

## Üç Sütun: Traces, Metrics, Logs

```
Distributed Tracing:
  Bir sorğunun bütün servislərdə izlənməsi
  
  Trace: bir sorğunun tam yolu
  Span: bir işin bir servisdə icra edilməsi
  
  HTTP Request → [Span: API Gateway]
                      → [Span: OrderService]
                          → [Span: DB Query]
                          → [Span: Cache Get]
                      → [Span: PaymentService]
                          → [Span: External API]

Metrics:
  Sayısal ölçümler: latency, throughput, error rate
  Counter, Gauge, Histogram, Summary
  
  http_requests_total{method="POST",status="200"} 1234
  http_request_duration_seconds{quantile="0.99"} 0.45

Logs:
  Strukturlu log event-ləri
  Trace ID ilə korelasiya (logs ↔ traces)
  
  {"level":"error","trace_id":"abc123","span_id":"def456",
   "message":"Payment failed","order_id":"789"}
```

---

## Instrumentation

```
Auto Instrumentation:
  OTel agent avtomatik olaraq instrumentasiya edir
  HTTP clients, DB queries, queues
  Zero code change
  
  PHP: opentelemetry-auto-* paketlər
  - opentelemetry-auto-psr18 (HTTP clients)
  - opentelemetry-auto-doctrine (DB)
  - opentelemetry-auto-symfony

Manual Instrumentation:
  Custom business logic üçün
  Span-lara attribute əlavə etmək
  Exception qeyd etmək

Propagation:
  Trace context-i servislar arasında ötürmək
  W3C Trace Context standard:
    traceparent: 00-{traceId}-{spanId}-{flags}
    tracestate: vendor-specific data

Sampling:
  Hər sorğunu trace etmək çox data yaradır
  Head sampling: başlanğıcda qərar
  Tail sampling: OTel Collector-da qərar (error-ları hamısını)
  
  AlwaysOn: 100% (development)
  Probabilistic: 1%-10% (production)
  Rate limiting: saniyədə N trace
```

---

## PHP İmplementasiyası

```php
<?php
// composer require open-telemetry/sdk
// composer require open-telemetry/exporter-otlp

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;

// Bootstrap (application entry point)
$tracerProvider = (new TracerProviderFactory())->create();
Globals::registerInitializer(function (Configurator $configurator) use ($tracerProvider) {
    return $configurator->withTracerProvider($tracerProvider);
});

$tracer = $tracerProvider->getTracer('app', '1.0.0');
```

```php
<?php
// Manual span yaratmaq
class OrderService
{
    public function __construct(
        private \OpenTelemetry\API\Trace\TracerInterface $tracer,
        private OrderRepository $repository,
    ) {}

    public function placeOrder(PlaceOrderCommand $cmd): Order
    {
        // Span başlat
        $span = $this->tracer->spanBuilder('OrderService::placeOrder')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $scope = $span->activate(); // Context-ə set et

        try {
            // Business attribute-lar əlavə et
            $span->setAttribute('order.customer_id', $cmd->customerId);
            $span->setAttribute('order.item_count', count($cmd->items));

            $order = Order::place($cmd);
            $this->repository->save($order);

            $span->setAttribute('order.id', $order->getId());
            $span->setStatus(StatusCode::STATUS_OK);

            return $order;

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

```php
<?php
// HTTP Propagation — trace context ötürmək
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Context;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class InstrumentedHttpClient
{
    public function __construct(
        private Client $http,
        private \OpenTelemetry\API\Trace\TracerInterface $tracer,
    ) {}

    public function post(string $url, array $data): array
    {
        $span = $this->tracer->spanBuilder("HTTP POST {$url}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        // W3C traceparent header inject et
        $headers = [];
        $carrier  = new ArrayCarrier($headers);
        TraceContextPropagator::getInstance()->inject($carrier, null, Context::getCurrent());

        try {
            $response = $this->http->post($url, [
                'json'    => $data,
                'headers' => $carrier->toArray(),
            ]);

            $span->setAttribute('http.status_code', $response->getStatusCode());
            return json_decode($response->getBody(), true);

        } catch (\Throwable $e) {
            $span->recordException($e);
            throw $e;
        } finally {
            $span->end();
        }
    }
}
```

```yaml
# OTel Collector config (otel-collector.yaml)
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
      http:
        endpoint: 0.0.0.0:4318

processors:
  batch:
    timeout: 1s
  # Error-ları 100% sample et
  tail_sampling:
    policies:
      - name: errors
        type: status_code
        status_code: {status_codes: [ERROR]}
      - name: slow
        type: latency
        latency: {threshold_ms: 1000}
      - name: probabilistic
        type: probabilistic
        probabilistic: {sampling_percentage: 10}

exporters:
  jaeger:
    endpoint: jaeger:14250
  prometheus:
    endpoint: "0.0.0.0:8889"

service:
  pipelines:
    traces:
      receivers: [otlp]
      processors: [batch, tail_sampling]
      exporters: [jaeger]
    metrics:
      receivers: [otlp]
      processors: [batch]
      exporters: [prometheus]
```

---

## İntervyu Sualları

- OpenTelemetry nədir? Backend-dən nə ilə fərqlənir?
- Trace, Span, TraceId, SpanId — izah edin.
- W3C Trace Context nədir? Niyə vacibdir?
- Head sampling ilə tail sampling fərqi nədir?
- OTel Collector-un rolu nədir? Birbaşa backend-ə göndərməkdən üstünlüyü?
- Logs-Traces korelasiyası necə işləyir?
