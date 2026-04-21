# 91. Distributed Tracing Deep Dive — OpenTelemetry, Sampling, Context Propagation

Microservices arasında bir user request 20+ servisdən keçə bilər. Logs fragmentlidir, metrics aggregated-dir — yalnız **trace** bir request-in full journey-ini göstərir. File 16 logging/monitoring ümumi baxışı verir; bu fayl distributed tracing-in daxili işinə (W3C Trace Context, sampling, cardinality, storage) dərindən baxır.

## Trace model

```
Trace (ID = 32-hex, 128-bit)
 ├── Span "GET /checkout"       (root, 500ms)
 │    ├── Span "validate-cart"   (5ms)
 │    ├── Span "charge-payment"  (300ms)
 │    │    ├── Span "stripe-api" (250ms)
 │    │    └── Span "db.insert"  (10ms)
 │    └── Span "ship-notification" (10ms)
 └──
```

**Span** — atomic unit of work:
- span_id (16-hex, 64-bit)
- trace_id (parent identifier)
- parent_span_id (if not root)
- name (operation name)
- start_time, end_time
- attributes (key-value tags)
- events (timestamped notes)
- status (OK / ERROR)

## W3C Trace Context

Standart HTTP header-lər (2021-dən sonra universal):

```
traceparent: 00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01
             ^^ ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ ^^^^^^^^^^^^^^^^ ^^
             |    trace-id (32 hex)              span-id (16 hex)  flags
             version

tracestate: vendor1=value1,vendor2=value2
```

**Sampling flags:**
- `01` — sampled (store this trace)
- `00` — not sampled (drop)

### Köhnə formatlar (hələ də yayğın)

**B3 (Zipkin):**
```
X-B3-TraceId: 0af7651916cd43dd8448eb211c80319c
X-B3-SpanId:  b7ad6b7169203331
X-B3-Sampled: 1
```

**Jaeger:**
```
uber-trace-id: 0af7...:b7ad...:0:1
```

OpenTelemetry SDK bütün format-ları dəstəkləyir (propagator pluggable).

## Context propagation

### HTTP

```php
// Laravel middleware — incoming request
$traceparent = $request->header('traceparent');
$context = Propagator::extract($traceparent);

$span = $tracer->spanBuilder('http.request')
    ->setParent($context)
    ->startSpan();

try {
    return $next($request);
} finally {
    $span->end();
}
```

### Outgoing HTTP (downstream call)

```php
// Guzzle middleware
$stack->push(function (callable $handler) {
    return function ($request, $options) use ($handler) {
        $span = $tracer->spanBuilder('http.client')->startSpan();
        $headers = [];
        Propagator::inject(Context::current(), $headers);
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, $v);
        }
        return $handler($request, $options)->then(
            function ($response) use ($span) {
                $span->setAttribute('http.status_code', $response->getStatusCode());
                $span->end();
                return $response;
            }
        );
    };
});
```

### gRPC

```
Interceptor → inject traceparent into metadata
Server-side interceptor → extract
```

Native support OpenTelemetry gRPC instrumentation.

### Messaging (Kafka, RabbitMQ, SQS)

```
Producer: context injected into message headers
Consumer: context extracted, new span "consumer.process" linked to parent
```

**Span links** (vs parent-child):
- **Parent-child:** syncron causal
- **Link:** async, one-to-many (batch consume 100 messages → 1 span with 100 links)

## OpenTelemetry architecture

```
Application
    │
    ├── OTel SDK (in-process)
    │    ├── Tracer
    │    ├── BatchSpanProcessor (async buffer)
    │    └── OTLP Exporter (gRPC)
    │
    ↓ network
    
OTel Collector (sidecar or cluster)
    ├── Receivers (OTLP, Jaeger, Zipkin, Prometheus, ...)
    ├── Processors (batch, sampling, attributes, resource detection)
    └── Exporters (Jaeger, Tempo, Datadog, CloudWatch, ...)
```

### SDK responsibilities

- **Tracer** — span yaradır
- **Sampler** — trace saxlanılmalıdırmı?
- **SpanProcessor** — span bitəndə ne olur (batch, export)
- **Exporter** — backend-ə göndərir (OTLP, Jaeger format, ...)
- **Propagator** — context header-lərini serialize/deserialize edir

### Collector

Uygulamadan ayrı — application-ın telemetry overhead-ini minimal tutur.

```yaml
receivers:
  otlp:
    protocols:
      grpc: { endpoint: 0.0.0.0:4317 }
      http: { endpoint: 0.0.0.0:4318 }

processors:
  batch:
    timeout: 10s
    send_batch_size: 10000

  tail_sampling:
    decision_wait: 10s
    policies:
      - name: errors
        type: status_code
        status_code: { status_codes: [ERROR] }
      - name: slow
        type: latency
        latency: { threshold_ms: 500 }
      - name: random
        type: probabilistic
        probabilistic: { sampling_percentage: 1 }

exporters:
  otlp/tempo:
    endpoint: tempo:4317
    tls: { insecure: true }

service:
  pipelines:
    traces:
      receivers: [otlp]
      processors: [batch, tail_sampling]
      exporters: [otlp/tempo]
```

## Sampling strategies

### Niyə sampling lazımdır?

100k QPS × avg 20 spans/trace × 1 KB/span = **2 GB/saniyə** = 172 TB/gün.

Cost: $1M+/ay sadəcə trace storage. Həll — sampling.

### Head-based sampling

Root span-da qərar ver — saxla ya drop. Bütün downstream span-lar eyni qərara tabe.

**Fixed rate:**
```
1% sampling: sample if trace_id % 100 == 0
```

Sadə, client-only decision, bütün servislər eyni qərar verir (deterministic by trace_id).

**Trade-off:**
- **+** Low overhead, predictable cost
- **−** Nadir error-lar məxluqda itə bilər
- **−** Interesting trace-lər statistik sezmir

### Tail-based sampling

Bütün trace toplandıqdan sonra qərar ver. Collector-da implement olunur.

```
1. Bütün span-ları trace_id üzrə buffer et (10s window)
2. Trace tam (və ya deadline) → rule-based decide:
   - ERROR status → always keep
   - latency > P99 → always keep
   - random 0.1% → keep
   - else drop
3. Keep qərarı verilsə → export
```

**Trade-off:**
- **+** Interesting trace-lər (error, slow) 100% saxlanır
- **−** Buffer memory yüksək (10s × 100k QPS = 1M trace buffer)
- **−** Distributed state — collector-lar arası coordination (eyni trace-in bütün span-ları eyni collector-a getməlidir → consistent hashing by trace_id)

### Adaptive sampling

Sistem load-una görə dinamik:

```
error_rate = errors / total
sample_rate = base_rate × (1 + error_multiplier × error_rate)
```

Normal trafikdə 1%, error-lar artanda 100%-ə qədər.

### Rate limiting

```
Per-service: 10 sample/second max
→ viral endpoint tək başına sistemi batırmaz
```

## Span attributes & semantic conventions

OpenTelemetry semantic conventions — standardize tag names:

```
http.method = "GET"
http.url = "https://..."
http.status_code = 200
http.user_agent = "..."

db.system = "postgresql"
db.statement = "SELECT * FROM users WHERE id = $1"
db.operation = "SELECT"

messaging.system = "kafka"
messaging.destination = "orders"
messaging.operation = "process"

rpc.system = "grpc"
rpc.service = "CheckoutService"
rpc.method = "PlaceOrder"
```

**Why conventions matter?** Backend (Tempo, Jaeger) UI-lar bu attribute-lara əsasən built-in filtrlər təklif edir.

## Baggage

Context-də səyahət edən key-value, **bütün downstream span-ların** attributes-ına göstəricilər:

```
baggage: user_id=42,tenant=acme,feature_flag.new_ui=true
```

**İstifadə:**
- User-specific trace filtering (hansı user-i etkilədi?)
- A/B experiment tagging
- Tenant isolation analytics

**Diqqət:** hər span-a əlavə olunur → storage cost, PII risk. Həssas məlumat saxlama.

## Trace storage backends

### Jaeger (CNCF)

```
Components:
  jaeger-agent (UDP receiver)
  jaeger-collector
  jaeger-query
  
Storage backends:
  Cassandra  — historical default, write-heavy suitable
  Elasticsearch — rich search, expensive
  Badger — local dev only
  Kafka (async buffer before persistence)
```

### Tempo (Grafana Labs)

Object storage (S3/GCS) based — no index, cost ultra-low.

```
Write: trace_id → blob in S3 (one blob per trace)
Read by trace_id only (fast)
Search: requires metrics-generator (separate), not full-text
```

**Trade-off:**
- **+** 10-100x cheaper than Jaeger/ES
- **+** Unlimited retention
- **−** Weak search (trace_id lookup optimized, other queries slow)
- **−** Requires Grafana ecosystem

### Zipkin

Twitter-in orijinal trace system-i. Cassandra/ES storage. Format sadə (JSON), amma ekosistemi kiçikdir. OpenTelemetry Zipkin format dəstəkləyir backward compat üçün.

### Commercial — Datadog APM, New Relic, Honeycomb, AWS X-Ray, Dynatrace, Splunk

- **Datadog** — tight integration metric-lərlə; expensive.
- **Honeycomb** — wide events model, powerful query, SamProof sampling.
- **X-Ray** — AWS native, service map awesome, limited query.

## Span events & exceptions

```php
$span->addEvent('cache.miss', ['cache.key' => $key]);

try {
    // ...
} catch (\Exception $e) {
    $span->recordException($e);
    $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
    throw $e;
}
```

Event-lər span içində timeline-da göstərilir — debug-a kömək edir.

## Cardinality problem

**Kardinalıq** = unique tag value sayı. Çoxluq = storage blow-up.

```
Pis:
  http.url = "/users/42/profile"  ← unique per user (high cardinality)
  
Yaxşı:
  http.route = "/users/:id/profile"  ← templated (low cardinality)
  http.url_path = "..."               ← separate attribute if needed
```

High cardinality tags:
- request_id (unique per req)
- user_id (for very large user bases, keep in baggage separately)
- timestamp

Low cardinality (good for indexing):
- service.name
- http.method, http.status_code
- endpoint/route

## Exemplars

Metric + trace köprüsü. Histogram bucket-də bir neçə trace_id saxla:

```
http_request_duration_seconds_bucket{le="0.1"} 123 
  # exemplar: {trace_id="abc123"} 0.095 2024-01-01T12:00:00
```

Grafana-da histogram-dakı spike-a klik → o bucket-dan bir trace aç, niyə yavaş olduğunu görün.

## Instrumentation approaches

### Manual

```php
$span = $tracer->spanBuilder('custom.operation')->startSpan();
$scope = $span->activate();
try {
    // business logic
    $span->setAttribute('order.id', $orderId);
} finally {
    $scope->detach();
    $span->end();
}
```

### Auto-instrumentation

- **Java / .NET:** agent (bytecode injection) — Laravel+PHP üçün: OpenTelemetry PHP auto-instrumentation (ext-opentelemetry + zend observer API).
- **Python:** `opentelemetry-instrument python app.py`
- **Node.js:** `--require @opentelemetry/auto-instrumentations-node`

Laravel-de auto: HTTP kernel, DB queries, Redis, queue — hepsi standart.

## PHP/Laravel tracing nümunəsi

```php
// config/opentelemetry.php
return [
    'service_name' => env('OTEL_SERVICE_NAME', 'laravel-app'),
    'exporter' => [
        'protocol' => 'otlp-grpc',
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4317'),
    ],
    'sampler' => [
        'type' => 'parent_based_traceid_ratio',
        'ratio' => env('OTEL_SAMPLING_RATIO', 0.1),
    ],
];
```

```php
// app/Http/Middleware/TracingMiddleware.php
public function handle($request, Closure $next)
{
    $tracer = Globals::tracerProvider()->getTracer('laravel');
    $carrier = $request->headers->all();
    $context = TraceContextPropagator::getInstance()
        ->extract($carrier);

    $span = $tracer->spanBuilder('http ' . $request->method() . ' ' . $request->route()?->uri())
        ->setParent($context)
        ->setSpanKind(SpanKind::KIND_SERVER)
        ->setAttribute('http.method', $request->method())
        ->setAttribute('http.route', $request->route()?->uri())
        ->startSpan();

    $scope = $span->activate();

    try {
        $response = $next($request);
        $span->setAttribute('http.status_code', $response->status());
        if ($response->status() >= 500) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        }
        return $response;
    } catch (\Throwable $e) {
        $span->recordException($e);
        $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        throw $e;
    } finally {
        $scope->detach();
        $span->end();
    }
}
```

## Database instrumentation

```php
DB::listen(function (QueryExecuted $query) {
    $tracer = Globals::tracerProvider()->getTracer('db');
    $span = $tracer->spanBuilder('db.query')
        ->setAttribute('db.system', 'postgresql')
        ->setAttribute('db.statement', $query->sql)
        ->setAttribute('db.operation', explode(' ', $query->sql)[0])
        ->setStartTimestamp((int) (microtime(true) - $query->time / 1000))
        ->startSpan();
    $span->end();
});
```

**Diqqət:** `db.statement` PII ola bilər (`WHERE email = 'user@...'`). Parametrize et və ya scrub filter əlavə et.

## Cost & performance

### Overhead

- CPU: ~1-3% with BatchSpanProcessor
- Memory: buffer (default 2048 span, ~5-20 MB)
- Network: compressed OTLP gRPC, minimal

### Storage

```
100k QPS × 20 spans/trace × 1 KB avg = 2 GB/sec
Sample 1%: 20 MB/sec = 1.7 TB/day
Retention 7 days: 12 TB
Cost (S3 Intelligent-Tiering): ~$300/month
```

Jaeger/ES üçün 10-20x daha bahalı (indexed storage).

## Best practices

1. **Propagate everywhere** — tək servis müstəqnayı bütün chain-i qırır
2. **Low-cardinality attributes index edilir, high-cardinality saxlanır**
3. **Tail sampling errors + slow + random** — ən yaxşı sinqal
4. **Don't trace health checks** — filter at collector
5. **Scrub PII in collector processor**
6. **Link spans async processing-də** parent-child əvəzinə
7. **Use semantic conventions** — backend UI optimal olur
8. **Service name stable** — version-lar arası dəyişmə
9. **Sample rate — start aggressive** (5%), traffic öyrəndikdən sonra optimize

## Anti-patterns

1. **Tracing all the things** — bill shock; 1% sample kafi
2. **Non-standard attribute names** — `user_id` / `userId` / `user.id` qarışıq
3. **Missing root span** — orphan span-lar zibil yaradır
4. **Span per SQL query** without batching → N+1 trace explosion
5. **Long-lived spans** (> 1 hour) — connection state problemləri
6. **Logging inside span attributes** — stack trace 10 KB → cost blow up
7. **No error status** — trace saxlanır amma "başarılı" kimi görünür

## Real-world

### Uber Jaeger
- 1 trillion+ span/ay
- Adaptive sampling per service
- Hot-warm Cassandra tiered storage

### Shopify
- OpenTelemetry migration 2022-dən
- Tempo backend, Grafana frontend
- 99.99% ingestion SLA

### Meta Canopy
- Custom internal; Kraken/ODS integration
- Full request trace for every interaction (sub-sampling only for hot paths)

## Yekun

Distributed tracing microservices debug-ı üçün vazgeçilməzdir. W3C Trace Context həqiqi standart olub vendor lock-in-i sındırır. OpenTelemetry SDK + Collector model-i — vendor-neutral, future-proof. Ən böyük əməliyyat problemləri: **sampling strategy seçimi** (head vs tail), **cardinality control** və **cost**. Bir trace-in dəyəri yalnız komanda onu istifadə etməyi bilirsə var — dashboard və alertlə inteqrasiyası olmadan trace-lər sadəcə disk tutur.
