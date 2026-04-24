# Observability və OpenTelemetry

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Müasir distributed sistemlərdə "problem haradadır?" sualına cavab vermək üçün üç əsas siqnal lazımdır: **metriklər** (nə qədər?), **log-lar** (nə oldu?) və **trace-lər** (hansı servis nə qədər vaxt aldı?). OpenTelemetry (OTel) bu üç siqnalı standartlaşdıran CNCF layihəsidir — hər dil üçün SDK, OTLP (OpenTelemetry Protocol) exporter və auto-instrumentation imkanları ilə.

Spring Boot 3.x Micrometer Tracing + Actuator ilə OTel-ə dərin inteqrasiya verir — endpoint-lər, DB sorğuları, HTTP client-lər avtomatik instrument olunur. Laravel-də isə OpenTelemetry PHP SDK + auto-instrumentation paketləri (Symfony, Laravel, Guzzle, PDO üçün) və daxili Pulse/Telescope vasitələri istifadə olunur.

---

## Spring-də istifadəsi

### Dependency-lər (Spring Boot 3.x)

```xml
<dependencies>
    <!-- Actuator — metrics və health üçün -->
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-actuator</artifactId>
    </dependency>

    <!-- Micrometer Tracing + OpenTelemetry bridge -->
    <dependency>
        <groupId>io.micrometer</groupId>
        <artifactId>micrometer-tracing-bridge-otel</artifactId>
    </dependency>

    <!-- OTLP exporter — Collector-a göndərmək üçün -->
    <dependency>
        <groupId>io.opentelemetry</groupId>
        <artifactId>opentelemetry-exporter-otlp</artifactId>
    </dependency>

    <!-- Prometheus format metriklər -->
    <dependency>
        <groupId>io.micrometer</groupId>
        <artifactId>micrometer-registry-prometheus</artifactId>
    </dependency>

    <!-- Loglarda traceId/spanId avtomatik görünsün deyə -->
    <dependency>
        <groupId>net.logstash.logback</groupId>
        <artifactId>logstash-logback-encoder</artifactId>
        <version>7.4</version>
    </dependency>
</dependencies>
```

### Konfiqurasiya

```yaml
# application.yml
spring:
  application:
    name: order-service

management:
  endpoints:
    web:
      exposure:
        include: health, info, metrics, prometheus
  tracing:
    sampling:
      probability: 1.0      # 100% — dev, prod-da 0.1 (10%)
    baggage:
      remote-fields: user-id, tenant-id
      correlation:
        fields: user-id, tenant-id
  otlp:
    tracing:
      endpoint: http://otel-collector:4318/v1/traces
    metrics:
      export:
        url: http://otel-collector:4318/v1/metrics
        step: 10s

logging:
  pattern:
    level: "%5p [${spring.application.name:},%X{traceId:-},%X{spanId:-}]"
```

Bu konfiqurasiyadan sonra hər log-da `traceId` və `spanId` avtomatik görünür:

```
2026-04-20 10:23:45.123 INFO [order-service,a3f1b5c2d4e5,b7c8d9e0f1a2] --- Sifariş yaradıldı: ORD-42
```

### Auto-instrumented komponentlər

Spring Boot avtomatik olaraq bunları izləyir (heç kod yazmadan):
- REST endpoints (`@RestController`)
- DB sorğuları (JDBC, JPA, R2DBC)
- `RestTemplate`, `WebClient` HTTP client-ləri
- Kafka producer/consumer
- Redis əməliyyatları
- Scheduled tapşırıqlar

### Xüsusi span yaratmaq

```java
@Service
public class OrderService {

    private final Tracer tracer;                    // Micrometer Tracer
    private final ObservationRegistry observationRegistry;
    private final PaymentClient paymentClient;

    public OrderService(Tracer tracer,
                        ObservationRegistry observationRegistry,
                        PaymentClient paymentClient) {
        this.tracer = tracer;
        this.observationRegistry = observationRegistry;
        this.paymentClient = paymentClient;
    }

    // 1) Observation API (tövsiyə olunur) — metrik + trace + log tək dəfəyə
    public Order createOrder(CreateOrderRequest request) {
        return Observation.createNotStarted("order.create", observationRegistry)
            .lowCardinalityKeyValue("payment.method", request.paymentMethod())
            .highCardinalityKeyValue("order.id", request.orderId())
            .observe(() -> {
                Order order = persistOrder(request);
                chargePayment(order);
                return order;
            });
    }

    // 2) Trace API — span-ı əl ilə idarə etmək
    private void chargePayment(Order order) {
        Span span = tracer.nextSpan()
            .name("payment.charge")
            .tag("order.id", order.getId())
            .tag("amount", String.valueOf(order.getTotal()))
            .start();

        try (Tracer.SpanInScope ws = tracer.withSpan(span)) {
            paymentClient.charge(order.getId(), order.getTotal());
            span.event("payment.success");
        } catch (Exception e) {
            span.error(e);
            throw e;
        } finally {
            span.end();
        }
    }
}
```

### `@Observed` annotasiyası ilə AOP

```java
@Service
public class InventoryService {

    @Observed(
        name = "inventory.reserve",
        contextualName = "reserve-stock",
        lowCardinalityKeyValues = {"service", "inventory"}
    )
    public void reserveStock(String sku, int quantity) {
        // Avtomatik span + metrik + log yaradılır
    }
}
```

### Baggage — Context-i servislərarası ötürmək

Baggage, bir trace daxilində bütün servislərə paylanan key-value məlumatdır (misal: `userId`, `tenantId`):

```java
@Service
public class UserContextService {

    private final Tracer tracer;

    public void processWithUser(String userId) {
        try (BaggageInScope baggage = tracer.createBaggageInScope("user-id", userId)) {
            // Bu blok daxilində edilən bütün HTTP sorğularında
            // `baggage: user-id=123` header avtomatik əlavə olunur
            callDownstreamService();
        }
    }
}
```

### Sampling strategiyaları

```yaml
management:
  tracing:
    sampling:
      probability: 0.1    # 10% — sadə head-based sampling
```

Proqramatik, dinamik sampling:

```java
@Bean
public Sampler customSampler() {
    return Sampler.parentBasedBuilder(
            Sampler.traceIdRatioBased(0.1))    // default 10%
        .setRemoteParentSampled(Sampler.alwaysOn())     // parent var, izlə
        .setLocalParentSampled(Sampler.alwaysOn())
        .build();
}

// Xətalı sorğuları həmişə saxla (tail-based)
@Component
public class ErrorBasedSampler implements SpanProcessor {
    @Override
    public void onEnd(ReadableSpan span) {
        if (span.getStatus().getStatusCode() == StatusCode.ERROR) {
            // Collector-a "force sample" işarəsi göndər
        }
    }
}
```

### Xüsusi metriklər (Micrometer + OTel)

```java
@Service
public class OrderMetrics {

    private final Counter ordersCreated;
    private final Timer orderDuration;

    public OrderMetrics(MeterRegistry registry) {
        this.ordersCreated = Counter.builder("orders.created")
            .description("Yaradılmış sifarişlər")
            .tag("service", "order")
            .register(registry);

        this.orderDuration = Timer.builder("orders.duration")
            .description("Sifariş emalı müddəti")
            .publishPercentiles(0.5, 0.95, 0.99)
            .register(registry);
    }

    public void recordOrderCreated(String status, Duration duration) {
        ordersCreated.increment();
        orderDuration.record(duration);
    }
}
```

Bu metriklər həm `/actuator/prometheus` endpoint-ində, həm də OTLP vasitəsilə Collector-a göndərilir.

---

## Laravel-də istifadəsi

### Quraşdırma

```bash
composer require open-telemetry/sdk \
                open-telemetry/exporter-otlp \
                open-telemetry/opentelemetry-auto-laravel \
                open-telemetry/opentelemetry-auto-pdo \
                open-telemetry/opentelemetry-auto-guzzle
```

Auto-instrumentation PHP 8.0+ extension tələb edir:

```bash
pecl install opentelemetry
echo "extension=opentelemetry.so" >> /etc/php/8.3/cli/php.ini
```

### Konfiqurasiya

```env
# .env
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=order-service
OTEL_TRACES_EXPORTER=otlp
OTEL_METRICS_EXPORTER=otlp
OTEL_LOGS_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_PROPAGATORS=tracecontext,baggage
OTEL_TRACES_SAMPLER=parentbased_traceidratio
OTEL_TRACES_SAMPLER_ARG=0.1
OTEL_RESOURCE_ATTRIBUTES=service.version=1.2.0,deployment.environment=production
```

Auto-instrumentation aktivləşdikdən sonra bunlar avtomatik izlənir:
- HTTP sorğuları (Laravel route-ları)
- Eloquent/PDO DB sorğuları
- Guzzle HTTP client çağırışları
- Redis, cache, queue əməliyyatları
- Artisan command-ları

### Xüsusi span yaratmaq

```php
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

class OrderService
{
    public function createOrder(CreateOrderRequest $request): Order
    {
        $tracer = Globals::tracerProvider()->getTracer('app.order');

        $span = $tracer->spanBuilder('order.create')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('payment.method', $request->paymentMethod)
            ->setAttribute('order.total', $request->total)
            ->startSpan();

        $scope = $span->activate();

        try {
            $order = Order::create($request->toArray());
            $this->chargePayment($order);
            $span->addEvent('order.persisted', ['order.id' => $order->id]);

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

    private function chargePayment(Order $order): void
    {
        $tracer = Globals::tracerProvider()->getTracer('app.payment');

        $span = $tracer->spanBuilder('payment.charge')
            ->setAttribute('order.id', $order->id)
            ->setAttribute('amount', $order->total)
            ->startSpan();

        $scope = $span->activate();

        try {
            Http::post(config('services.payment.url') . '/charge', [
                'order_id' => $order->id,
                'amount' => $order->total,
            ])->throw();

            $span->addEvent('payment.success');
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR);
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

### Helper wrapper — Laravel-ə uyğun

```php
// app/Observability/Trace.php
class Trace
{
    public static function span(string $name, callable $callback, array $attributes = []): mixed
    {
        $tracer = Globals::tracerProvider()->getTracer('app');

        $spanBuilder = $tracer->spanBuilder($name);
        foreach ($attributes as $key => $value) {
            $spanBuilder->setAttribute($key, $value);
        }

        $span = $spanBuilder->startSpan();
        $scope = $span->activate();

        try {
            return $callback($span);
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

// İstifadə — təmiz və qısa
$order = Trace::span('order.create', function ($span) use ($request) {
    $order = Order::create($request->validated());
    $span->setAttribute('order.id', $order->id);
    return $order;
}, ['payment.method' => $request->paymentMethod]);
```

### Log correlation — trace ID-ni loga yazmaq

```php
// app/Logging/TraceIdProcessor.php
use OpenTelemetry\API\Trace\Span;

class TraceIdProcessor
{
    public function __invoke(array $record): array
    {
        $span = Span::getCurrent();
        $context = $span->getContext();

        if ($context->isValid()) {
            $record['extra']['trace_id'] = $context->getTraceId();
            $record['extra']['span_id'] = $context->getSpanId();
        }

        return $record;
    }
}
```

```php
// config/logging.php
'stack' => [
    'driver' => 'stack',
    'channels' => ['otlp', 'stderr'],
    'tap' => [App\Logging\AddTraceIdTap::class],
],
```

### Baggage istifadəsi

```php
use OpenTelemetry\API\Baggage\Baggage;

// Middleware-də user-id baggage-ə qoy
class AddUserBaggage
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            $baggage = Baggage::getCurrent()->toBuilder()
                ->set('user.id', (string) $user->id)
                ->set('tenant.id', (string) $user->tenant_id)
                ->build();

            $scope = $baggage->activate();

            try {
                return $next($request);
            } finally {
                $scope->detach();
            }
        }

        return $next($request);
    }
}
```

### Pulse + OTel birləşməsi

Pulse daxili dashboard verir, OTel isə məlumatları xarici sistemlərə (Grafana, Honeycomb, DataDog) göndərir. İkisi birgə işləyə bilər:

```php
// config/pulse.php — daxili monitoring
// .env — OTLP exporter xarici üçün
// Heç bir konflikt yoxdur, ikisi paralel işləyir
```

### OTel Collector konfiqurasiyası (ikisi üçün ortaq)

```yaml
# otel-collector-config.yaml
receivers:
  otlp:
    protocols:
      http:
        endpoint: 0.0.0.0:4318
      grpc:
        endpoint: 0.0.0.0:4317

processors:
  batch:
    timeout: 10s
  tail_sampling:
    policies:
      - name: errors
        type: status_code
        status_code: { status_codes: [ERROR] }
      - name: slow
        type: latency
        latency: { threshold_ms: 500 }
      - name: random
        type: probabilistic
        probabilistic: { sampling_percentage: 10 }

exporters:
  otlp/tempo:
    endpoint: tempo:4317
    tls: { insecure: true }
  prometheus:
    endpoint: 0.0.0.0:8889

service:
  pipelines:
    traces:
      receivers: [otlp]
      processors: [tail_sampling, batch]
      exporters: [otlp/tempo]
    metrics:
      receivers: [otlp]
      processors: [batch]
      exporters: [prometheus]
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Boot | Laravel |
|---|---|---|
| OTel inteqrasiyası | Micrometer Tracing (first-class) | PHP SDK + auto-instrumentation extension |
| Auto-instrumentation | Annotation-lar, starter-lərdə daxili | `pecl install opentelemetry` + paketlər |
| Setup mürəkkəbliyi | `application.yml`-də 5-10 sətir | PHP extension + env dəyişənləri |
| Span yaratmaq | Observation API / `@Observed` / Tracer | Manual `spanBuilder()->startSpan()` |
| Log correlation | Avtomatik (`%X{traceId}` pattern) | Monolog processor əl ilə |
| Metriklər | Micrometer (zəngin API) | OpenTelemetry Meter API |
| Sampling | Head + tail (Collector), programmatic | Env-də konfiq |
| Context propagation | ThreadLocal + Reactor context | Fiber/request scope |
| Request-scoped context | Spring bean lifecycle | Request lifecycle (Octane fərqli) |
| Daxili dashboard | Actuator endpoint-ləri | Pulse + Telescope |
| Baggage dəstəyi | Micrometer + auto-propagation | Manual builder |

---

## Niyə belə fərqlər var?

**Java-nın uzun observability tarixi.** Java enterprise-da onilliklər boyu istifadə olunub, JMX, Java Flight Recorder, JFR kimi daxili alətlərə malikdir. Micrometer isə vendor-neutral fasad kimi ortaya çıxdı — istənilən backend (Prometheus, DataDog, New Relic) dəstəkləyir. Spring Boot 3.x Micrometer Tracing vasitəsilə OTel-i "default" etdi.

**PHP-nin request-per-process modeli.** Hər HTTP sorğu ayrı PHP-FPM prosesi kimi işlədiyi üçün "davamlı" metrik toplamaq çətindir. OTel SDK hər sorğunun sonunda məlumatları Collector-a push edir. Bu model overhead əlavə edir, amma Collector tərəfində aggregation edilir. Octane/Swoole/RoadRunner kimi stay-alive runtime-larda bu problem azalır.

**Context propagation mexanizmi fərqlidir.** Java-da ThreadLocal (və ya Reactor Context) trace context-i daşıyır — Virtual Thread-lərlə də işləyir. PHP-də isə Fiber və ya Request scope istifadə olunur. Async kod PHP-də hələ də nadir olduğu üçün bu mürəkkəblik daha azdır.

**Auto-instrumentation fərqli səviyyədə işləyir.** Java-da bytecode instrumentation (JVM agent) və ya aspect-based yanaşma istifadə olunur — heç bir kod dəyişikliyi lazım deyil. PHP-də isə Zend extension (`opentelemetry.so`) funksiya çağırışlarını intercept edir — həm performans cəhətdən baha, həm də extension quraşdırmaq tələb olunur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Micrometer Observation API — metrik + trace + log tək API-də
- `@Observed` annotasiyası ilə deklarativ instrumentation
- Reactor (WebFlux) context propagation OTel ilə inteqrə
- Virtual Thread-lərdə avtomatik context ötürülməsi
- `spring-cloud-sleuth` → Micrometer Tracing keçid yolu
- JVM-daxili metriklər (GC, heap, thread count) avtomatik
- BOM ilə versiya uyğunluğunun idarəsi

**Yalnız Laravel-də:**
- Pulse — zero-config daxili monitoring dashboard
- Telescope — developer-friendly request inspector
- Daxili slow-query və slow-job izləmə (OTel-dən asılı olmayaraq)
- Horizon — queue-a xüsusi dashboard (OTel-dən bağımsız)
- `Http::fake()` ilə testlərdə trace mocking asanlığı
- Artisan `about` komması ilə servis metadata görməsi
