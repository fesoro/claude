# Distributed Tracing (Senior)

## Nədir? (What is it?)

Distributed tracing – microservice arxitekturada bir istifadəçi sorğusunun bütün service-lər arasındakı yolunu izləmə texnikasıdır. Hər servis öz işini "span" kimi qeyd edir, bütün span-lar eyni trace_id altında toplanır və waterfall diaqramı əmələ gətirir. Bu sayədə "niyə bu request 2 saniyə çəkir?" sualına cavab verilir – hansı service-də, hansı DB query-də, hansı HTTP çağırışında vaxt sərf olunduğu görünür. Google Dapper (2010) məqaləsindən ilham alıb; Jaeger, Zipkin, Tempo kimi alətlər mövcuddur.

## Əsas Konseptlər (Key Concepts)

### Trace və Span

```
Trace = bir request-in bütün həyatı
Span = bu request daxilində bir əməliyyat

Trace ID: 4bf92f3577b34da6a3ce929d0e0e4736 (128-bit)
   └─ Span 1: "GET /api/orders" [gateway] 250ms
       ├─ Span 2: "auth.validate" [auth-svc] 15ms
       ├─ Span 3: "orders.list" [order-svc] 180ms
       │   ├─ Span 4: "SELECT orders" [postgres] 120ms
       │   └─ Span 5: "cache.get" [redis] 3ms
       └─ Span 6: "products.batch" [product-svc] 45ms

Hər Span-da:
   - name
   - trace_id, span_id, parent_span_id
   - start_time, end_time
   - attributes (key-value)
   - events (timestamp + attrs)
   - status (OK/ERROR)
   - links (başqa trace-lərə)
```

### Jaeger vs Tempo vs Zipkin

```
JAEGER (Uber → CNCF graduated):
   - Storage: Cassandra, Elasticsearch, Badger
   - UI: zəngin, service dependency graph
   - Query language: öz query UI-si
   - Indexing: bütün atributları indeksləyə bilər (storage baha)
   - Native OpenTelemetry dəstəyi

TEMPO (Grafana Labs):
   - Storage: obyekt storage (S3, GCS, Azure Blob) – ucuz!
   - UI: Grafana-ya inteqrasiya
   - Query: TraceQL (SQL-ə bənzər)
   - Indexing: minimal – "trace_id bilən tap" yanaşması
   - Logs/metrics ilə korrelyasiya (eyni Grafana-da)
   - Ucuz, şkaləedilə bilən

ZIPKIN (Twitter, 2012 – pioneer):
   - Storage: MySQL, Cassandra, Elasticsearch
   - UI: sadə
   - Protocol: B3 (köhnə), OTLP da dəstəklənir
   - Köhnə, lakin hələ işlənir

SEÇİM:
   - Kiçik/orta: Jaeger (UI yaxşıdır)
   - Böyük miqyas, Grafana stack: Tempo
   - Legacy: Zipkin
```

### Context Propagation

```
Problem: service A → B → C çağırdıqda, hər service öz span-ını yaradır.
A-dan B-yə "bu trace-in davamısan" necə deyəsən?

HƏLLİ: HTTP header-lər (və ya message header-lər queue üçün)

W3C TRACE CONTEXT (standart, müasir):
   traceparent: 00-{trace_id}-{parent_span_id}-{flags}
   tracestate: vendor-specific data

B3 (Zipkin format, köhnə):
   X-B3-TraceId
   X-B3-SpanId
   X-B3-ParentSpanId
   X-B3-Sampled

JAEGER FORMAT (köhnə):
   uber-trace-id: {trace-id}:{span-id}:{parent-span-id}:{flags}
```

### Baggage

```
Baggage = request boyunca bütün service-lərə yayılan metadata
   (trace-dən fərqli olaraq biznes context saxlayır)

baggage: user.id=12345,tenant=acme,region=eu

Use case-lər:
   - User ID bütün log-larda
   - Feature flag decision-ı service-lər arasında
   - Tenant routing

Diqqət: baggage header-də gedir, çox qoyma (bandwidth)
```

### Sampling Strategies

```
HEAD-BASED SAMPLING:
   Trace başlanğıcında qərar: sampled=true/false
   Sampling flag bütün service-lərə yayılır
   
   Növlər:
   a) Probabilistic: hər trace-in 10% ehtimalla sampled
   b) Rate-limiting: saniyədə 100 trace sample et
   c) Constant: həmişə sample et (1.0)

TAIL-BASED SAMPLING:
   Trace bitdikdən sonra qərar
   Bütün span-lar collector-da buffer edilir
   
   Üstünlük: "error-ları hamısını saxla, uğurluları 1%"
   Mənfi: memory/latency (gözləməli olur)

HYBRID:
   Application: head sampling (90% at)
   Collector: tail sampling (slow/error-ları seç)
```

## Praktiki Nümunələr (Practical Examples)

### Jaeger All-in-One Quraşdırma

```yaml
# docker-compose.yml
services:
  jaeger:
    image: jaegertracing/all-in-one:1.55
    environment:
      - COLLECTOR_OTLP_ENABLED=true
    ports:
      - "16686:16686"   # UI
      - "4317:4317"     # OTLP gRPC
      - "4318:4318"     # OTLP HTTP
      - "14268:14268"   # Jaeger collector HTTP
```

### Production Jaeger (Elasticsearch)

```yaml
# values.yaml (helm chart)
collector:
  replicaCount: 3
  service:
    otlp:
      grpc: { port: 4317 }
query:
  replicaCount: 2
storage:
  type: elasticsearch
  elasticsearch:
    host: elasticsearch
    port: 9200
    indexPrefix: jaeger
    anonymous: false
spark:
  enabled: true    # service dependency graph generation
```

### Tempo Konfiqurasiyası

```yaml
# tempo.yaml
server:
  http_listen_port: 3200

distributor:
  receivers:
    otlp:
      protocols:
        grpc: { endpoint: 0.0.0.0:4317 }

ingester:
  max_block_duration: 5m

compactor:
  compaction:
    block_retention: 336h   # 14 gün

storage:
  trace:
    backend: s3
    s3:
      bucket: tempo-traces
      endpoint: s3.amazonaws.com
    wal:
      path: /var/tempo/wal
    pool:
      max_workers: 100
      queue_depth: 10000

querier:
  frontend_worker:
    frontend_address: tempo-query-frontend:9095
```

### TraceQL Nümunələri

```sql
-- Error-u olan trace-lər
{ status = error }

-- Slow trace-lər (>1s)
{ duration > 1s }

-- Service və operation birləşməsi
{ resource.service.name = "payment" && name = "charge" }

-- DB query 500ms-dən uzun
{ db.system = "mysql" && duration > 500ms }

-- Span count ilə (mürəkkəb trace)
{ } | count() > 50

-- Error olan order service trace-lər
{ resource.service.name = "order" && status = error }
| select(name, duration)
```

### Sampling Konfiqurasiyası (Jaeger client)

```json
{
  "service_strategies": [
    {
      "service": "payment",
      "type": "probabilistic",
      "param": 1.0
    },
    {
      "service": "frontend",
      "type": "probabilistic",
      "param": 0.1
    }
  ],
  "default_strategy": {
    "type": "probabilistic",
    "param": 0.05
  }
}
```

### OTel Collector Tail Sampling

```yaml
processors:
  tail_sampling:
    decision_wait: 30s
    num_traces: 100000
    policies:
      # Error-lar 100%
      - name: errors
        type: status_code
        status_code: { status_codes: [ERROR] }
      
      # Slow trace-lər 100%
      - name: slow
        type: latency
        latency: { threshold_ms: 1000 }
      
      # Konkret service-dən hamısı
      - name: critical-service
        type: string_attribute
        string_attribute:
          key: service.name
          values: [payment, checkout]
      
      # Defaultun 1%
      - name: random
        type: probabilistic
        probabilistic: { sampling_percentage: 1 }
```

## PHP/Laravel ilə İstifadə

### Trace Propagation (HTTP Client)

```php
<?php
// app/Services/HttpClient.php

use Illuminate\Support\Facades\Http;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Context\Context;

class HttpClient
{
    public function get(string $url): array
    {
        // Cari trace context-i götür
        $context = Context::getCurrent();
        $headers = [];

        // traceparent header-i inject et
        Globals::propagator()->inject($headers, null, $context);

        $response = Http::withHeaders($headers)->get($url);

        return $response->json();
    }
}
```

### Queue Job Context Propagation

```php
<?php
// app/Jobs/ProcessOrder.php

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, Queueable;

    public array $traceCarrier = [];

    public function __construct(public int $orderId)
    {
        // Job yaradıldığı anda trace context-i serialize et
        Globals::propagator()->inject($this->traceCarrier);
    }

    public function handle(): void
    {
        // Worker tərəfdə context-i restore et
        $parentContext = Globals::propagator()->extract($this->traceCarrier);

        $tracer = Globals::tracerProvider()->getTracer('queue');
        $span = $tracer->spanBuilder('process-order')
            ->setParent($parentContext)
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setAttribute('messaging.system', 'redis')
            ->setAttribute('messaging.destination', 'orders')
            ->setAttribute('order.id', $this->orderId)
            ->startSpan();

        $scope = $span->activate();

        try {
            // ... iş
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

### Nested Span Pattern

```php
<?php

class CheckoutService
{
    public function checkout(int $userId): Order
    {
        $tracer = Globals::tracerProvider()->getTracer('checkout');

        $span = $tracer->spanBuilder('checkout.process')
            ->setAttribute('user.id', $userId)
            ->startSpan();
        $scope = $span->activate();

        try {
            $cart = $this->traceStep('load-cart', fn () => $this->loadCart($userId));
            $this->traceStep('validate', fn () => $this->validator->validate($cart));
            $payment = $this->traceStep('charge', fn () => $this->payment->charge($cart));
            $order = $this->traceStep('save-order', fn () => $this->saveOrder($cart, $payment));
            $this->traceStep('notify', fn () => $this->mailer->sendConfirmation($order));
            
            return $order;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function traceStep(string $name, callable $fn): mixed
    {
        $tracer = Globals::tracerProvider()->getTracer('checkout');
        $span = $tracer->spanBuilder($name)->startSpan();
        $scope = $span->activate();

        try {
            return $fn();
        } catch (\Throwable $e) {
            $span->recordException($e);
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

### Baggage

```php
use OpenTelemetry\API\Baggage\Baggage;

// Set baggage (request başlanğıcında)
$context = Context::getCurrent();
$baggage = Baggage::getBuilder()
    ->set('user.id', (string) $userId)
    ->set('tenant', $tenant)
    ->build();

$newContext = $context->with(\OpenTelemetry\API\Baggage\BaggageContextKey::instance(), $baggage);
Context::storage()->attach($newContext);

// Digər service-də oxumaq:
$baggage = Baggage::fromContext(Context::getCurrent());
$userId = $baggage->getValue('user.id');
```

## Interview Sualları (Q&A)

**S1: Distributed tracing-də trace_id və span_id fərqi nədir?**
C: **trace_id** bütün request-in unikal identifikatorudur (128-bit) – bütün service-lərdə eyni qalır. **span_id** isə konkret operation-un identifikatorudur (64-bit) – hər operation üçün yenisi yaradılır. Parent-child əlaqəsi `parent_span_id` ilə saxlanır, beləliklə waterfall qurmaq olur.

**S2: Sampling-in məqsədi nədir, 100% sample etsək nə olar?**
C: Production-da saniyədə minlərlə trace olur – hamısını saxlamaq storage və network baxımından çox bahadır. Sampling ilə məlumatların kiçik hissəsi saxlanır, amma yenə də statistika üçün yetərli olur. 100% sample production-da nadirən mümkündür (çox kiçik sistem və ya critical path üçün). Tipik dəyərlər: frontend 1-5%, payment kimi critical servis 100%.

**S3: Head və tail sampling-dən hansı daha yaxşıdır?**
C: Müxtəlif problem həll edirlər. **Head** sadə, yaddaş az tutur, amma "error trace-lərinin hamısını saxla" mümkün deyil. **Tail** daha ağıllıdır (policy əsaslı), error və slow trace-ləri saxlamağa imkan verir, amma Collector-da memory istəyir və decision_wait lazımdır. Production-da çox vaxt hybrid: application-da yüksək head sampling (90%), Collector-da tail sampling error/slow üçün.

**S4: W3C Trace Context və B3 fərqi nədir?**
C: **B3** (Zipkin) köhnə standart – bir neçə ayrı header (X-B3-TraceId, X-B3-SpanId). **W3C Trace Context** müasir standart – iki header (traceparent, tracestate). W3C interoperability üçün hazırlanıb – fərqli vendor-lar bir-birini anlasın. Yeni sistemlərdə W3C tövsiyə olunur, B3 yalnız legacy üçün.

**S5: Tempo-nun Jaeger-dən əsas fərqi nədir?**
C: Tempo **indexless** arxitektur istifadə edir – trace-ləri yalnız trace_id ilə oxumaq olar. Bu object storage-da (S3) saxlanmağa imkan verir, **çox ucuzdur** (Jaeger-in Elasticsearch xərclərinə nisbətən 10x+ ucuz). Amma "error-lu trace-ləri tap" kimi query etmək üçün logs/metrics-dən trace_id gətirmək lazımdır (TraceQL son zamanlar bəzi querylərə imkan verir).

**S6: Span attribute və event fərqi nədir, nə vaxt hansını istifadə edək?**
C: **Attribute** span-ın bütövlükdə xassəsidir – metadata (http.method, db.statement). **Event** isə span daxilində zaman nöqtəsi hadisəsidir (retry, cache miss, state change). Attribute son state-i göstərir, event ardıcıl hadisələri. Məsələn: `http.status_code=500` attribute-dir, "exception thrown" event-dir.

**S7: Queue/Async job-ların trace-i necə propagate olunur?**
C: Job enqueue olunduqda, trace context serialize edilib message-a daxil edilir (adətən xüsusi field-də – traceparent, traceCarrier). Worker job-u dequeue edəndə context-i extract edib yeni span-ın parent-i təyin edir. Ancaq async olduğuna görə span kind CONSUMER, producer tərəfdə PRODUCER olmalıdır. Span link istifadə edərək batch-də paralel işlənmə də göstərilə bilər.

**S8: Baggage nədir, nə vaxt trace attribute əvəzinə istifadə olunur?**
C: **Baggage** request boyunca bütün service-lərə yayılan key-value data-dır. Trace attribute yalnız bir span-a aiddir, digər service-lərdə görünmür. Baggage isə hər span-da əldə oluna bilər. Məsələn, user.id baggage-da qoyulsa, bütün microservice-lərdə log-a və metric-ə əlavə oluna bilər. Lakin baggage HTTP header-də gedir – çox data qoymaq bandwidth problemidir.

**S9: Trace sampling-in bütün service-lərdə eyni olması niyə vacibdir?**
C: Əgər service A "sample et" deyib, B isə öz qərarını versə, span-lar qarışıq olar – bəzi service-lər görünər, bəzilər yox (trace "yarımçıq"). Sampling qərarı trace başlanğıcında verilir və `trace flags` ilə bütün service-lərə propagate olunur – hər service eyni qərara sadiq qalır (consistent sampling).

**S10: Trace analizindən biznes məlumatı necə çıxarmaq olar?**
C: (1) **Service dependency graph** – hansı service hansını çağırır. (2) **Latency distribution** – p50/p95/p99 span-lara görə. (3) **Error rate by operation** – hansı operation ən çox fail olur. (4) **Critical path analysis** – trace-də ən uzun alt-zəncir. (5) **Cross-service bottleneck** – A→B→C zəncirində B-nin yavaş olması. Grafana/Jaeger UI bu analizləri dəstəkləyir, advanced halda trace-ləri BigQuery/Snowflake-ə export edib ML model qurulur.

## Best Practices

1. **OpenTelemetry standart et** – vendor-specific tracing library-lərə bağlanma.
2. **Semantic convention** riayət et – `http.method`, `db.system`, `messaging.system` kimi.
3. **Trace context propagation** həm HTTP həm də queue job-larda təmin et.
4. **Sampling** strategiyası qur – 100% sample production-a yararlı deyil.
5. **Tail sampling Collector səviyyəsində** istifadə et error/slow-ları tutmaq üçün.
6. **Sensitive data** attribute kimi əlavə etmə (password, token, PII).
7. **Span naming convention** – `http.method path` və ya `db.system.operation` kimi.
8. **Span duration məhdudlaşdır** – trace-lər saatlarla uzun olmamalıdır (timeout qoy).
9. **Logs-a trace_id əlavə et** – trace-log korrelyasiyası üçün.
10. **Critical service-də sampling 100%** (payment, auth).
11. **Service dependency graph** izlə – dəyişmə gözlənilməzdirsə, təhqiq et.
12. **Alerting** – error rate və latency SLO-ları yarat.
13. **Retention policy** – trace-ləri uzun saxlama (7-14 gün kifayətdir adətən).
14. **Collector high availability** – queue/batching ilə data loss-un qarşısını al.
15. **Dashboard-lar** – service latency, error rate, top operation by duration.
