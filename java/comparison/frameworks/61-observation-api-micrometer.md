# Observation API + Micrometer — Dərin Müqayisə

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Müasir production tətbiqləri üçün **observability** (müşahidə qabiliyyəti) üç sütun üzərində qurulur: **metrics** (sayılar), **traces** (sorğunun servislər arası yolu), **logs** (hadisə mətnləri). Spring Boot 3 bu üçünü **Observation API** altında birləşdirir — tək abstraksiya, çoxlu backend. **Micrometer** metric facade rolunu oynayır (Prometheus, Datadog, New Relic, CloudWatch-a export), **Micrometer Tracing** Spring Cloud Sleuth-u əvəz edir və W3C Trace Context, B3 propagasiya dəstəkləyir.

Laravel-də rəsmi lightweight həll **Laravel Pulse**-dır (Laravel 11+ üçün). Əlavə olaraq **Telescope** (dev), **Horizon** (queue dashboard), **Scout** (search) var. Prometheus üçün `promphp/prometheus_client_php`, distributed tracing üçün **OpenTelemetry PHP SDK** auto-instrumentation ilə işləyir.

---

## Spring-də istifadəsi

### 1) Starter asılılıqları

```xml
<dependencies>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-actuator</artifactId>
    </dependency>
    <dependency>
        <groupId>io.micrometer</groupId>
        <artifactId>micrometer-registry-prometheus</artifactId>
    </dependency>
    <dependency>
        <groupId>io.micrometer</groupId>
        <artifactId>micrometer-tracing-bridge-brave</artifactId>
    </dependency>
    <dependency>
        <groupId>io.zipkin.reporter2</groupId>
        <artifactId>zipkin-reporter-brave</artifactId>
    </dependency>
    <!-- və ya OpenTelemetry bridge -->
    <!--
    <dependency>
        <groupId>io.micrometer</groupId>
        <artifactId>micrometer-tracing-bridge-otel</artifactId>
    </dependency>
    <dependency>
        <groupId>io.opentelemetry</groupId>
        <artifactId>opentelemetry-exporter-otlp</artifactId>
    </dependency>
    -->
</dependencies>
```

### 2) `application.yml`

```yaml
spring:
  application:
    name: orders-api

management:
  endpoints:
    web:
      exposure:
        include: health,info,metrics,prometheus,observations
  endpoint:
    health:
      show-details: always
  metrics:
    distribution:
      percentiles-histogram:
        http.server.requests: true
      percentiles:
        http.server.requests: 0.5, 0.9, 0.95, 0.99
      slo:
        http.server.requests: 100ms, 200ms, 500ms
    tags:
      application: ${spring.application.name}
      region: ${REGION:eu-central}
  tracing:
    sampling:
      probability: 1.0            # dev-də 100%, prod-da 0.1 (10%)
    propagation:
      type: w3c,b3
  zipkin:
    tracing:
      endpoint: http://zipkin:9411/api/v2/spans

logging:
  pattern:
    level: "%5p [${spring.application.name:},%X{traceId:-},%X{spanId:-}]"
```

### 3) Auto-instrumented metrikləri

Aktivləşdirmədən `actuator/prometheus` endpoint-i verəcək:

- `http.server.requests` — HTTP endpoint latency, status code, URI
- `http.client.requests` — xaricə sorğular
- `jvm.memory.used`, `jvm.gc.pause`, `jvm.threads.live`
- `process.cpu.usage`, `system.cpu.usage`
- `tomcat.sessions.active.current`, `tomcat.threads.busy`
- `hikaricp.connections.active`, `hikaricp.connections.usage`
- `spring.data.repository.invocations`
- `kafka.consumer.*`, `kafka.producer.*`

### 4) Custom metrics — Counter, Timer, Gauge

```java
@Service
public class OrdersService {
    private final MeterRegistry registry;
    private final Counter ordersCreated;
    private final Timer paymentTimer;
    private final AtomicInteger pendingOrders = new AtomicInteger();

    public OrdersService(MeterRegistry registry) {
        this.registry = registry;
        this.ordersCreated = Counter.builder("orders.created")
            .description("Total orders created")
            .tag("channel", "web")
            .register(registry);
        this.paymentTimer = Timer.builder("payment.processing")
            .description("Payment processing duration")
            .publishPercentiles(0.5, 0.95, 0.99)
            .publishPercentileHistogram()
            .register(registry);
        Gauge.builder("orders.pending", pendingOrders, AtomicInteger::get)
            .description("Pending orders queue depth")
            .register(registry);
    }

    public Order placeOrder(OrderRequest req) {
        pendingOrders.incrementAndGet();
        try {
            Order order = paymentTimer.record(() -> processPayment(req));
            ordersCreated.increment();
            return order;
        } finally {
            pendingOrders.decrementAndGet();
        }
    }

    public void orderShipped(Order o) {
        DistributionSummary.builder("order.weight")
            .baseUnit("kg")
            .register(registry)
            .record(o.weightKg());
    }
}
```

### 5) `@Observed` — tracing + metrics + logs birdən

```java
@Service
public class PaymentService {

    @Observed(
        name = "payment.process",
        contextualName = "process-payment",
        lowCardinalityKeyValues = {"payment.type", "card"}
    )
    public PaymentResult process(Payment payment) {
        // metod daxilində trace span + metrics auto
        return gateway.charge(payment);
    }
}
```

Enable etmək üçün:

```java
@Configuration
public class ObservationConfig {

    @Bean
    public ObservedAspect observedAspect(ObservationRegistry observationRegistry) {
        return new ObservedAspect(observationRegistry);
    }
}
```

### 6) `ObservationRegistry` — aşağı səviyyə API

```java
@Service
public class CheckoutService {
    private final ObservationRegistry registry;

    public Order checkout(Cart cart) {
        return Observation.createNotStarted("checkout", registry)
            .contextualName("checkout-flow")
            .lowCardinalityKeyValue("tenant", cart.tenant())
            .highCardinalityKeyValue("cart.id", cart.id().toString())
            .observe(() -> {
                validateInventory(cart);
                Payment p = createPayment(cart);
                return placeOrder(cart, p);
            });
    }
}
```

### 7) Custom ObservationHandler — müəyyən hadisəni tutmaq

```java
@Component
public class AuditLogHandler implements ObservationHandler<Observation.Context> {

    @Override
    public boolean supportsContext(Observation.Context context) {
        return context.getName().startsWith("audit.");
    }

    @Override
    public void onStart(Observation.Context context) {
        log.info("audit start: {}", context.getName());
    }

    @Override
    public void onStop(Observation.Context context) {
        long ms = context.get(Long.class);
        log.info("audit stop: {} took {}ms", context.getName(), ms);
    }
}
```

### 8) Tracing — distributed context propagation

HTTP sorğu gələndə `traceparent` header-i avtomatik oxunur, daxili RestClient/WebClient call-larda isə avtomatik göndərilir.

```java
@RestController
public class OrderController {

    // Nothing special — trace id avtomatik MDC-yə qoyulur
    @GetMapping("/orders/{id}")
    public Order get(@PathVariable Long id) {
        log.info("fetching order {}", id);
        return service.findById(id);
    }
}
```

Log-da:
```
INFO [orders-api,4bf92f3577b34da6a3ce929d0e0e4736,00f067aa0ba902b7] fetching order 42
```

Kafka consumer-də də propagasiya var:

```java
@KafkaListener(topics = "orders.placed")
@Observed(name = "kafka.consume.orders")
public void consume(OrderEvent event) {
    // trace parent header-dən götürülür
}
```

### 9) Prometheus scrape + Grafana dashboard

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'spring-boot'
    metrics_path: '/actuator/prometheus'
    static_configs:
      - targets: ['orders-api:8080', 'billing-api:8080']
```

`GET /actuator/prometheus` response (fragment):
```
# TYPE http_server_requests_seconds summary
http_server_requests_seconds_count{method="GET",uri="/orders/{id}",status="200",application="orders-api"} 1523
http_server_requests_seconds_sum{method="GET",uri="/orders/{id}",status="200"} 42.318
http_server_requests_seconds{method="GET",uri="/orders/{id}",status="200",quantile="0.95"} 0.089

# TYPE orders_created_total counter
orders_created_total{channel="web"} 942
```

### 10) Testing observability

```java
@SpringBootTest
class PaymentServiceTest {
    @Autowired PaymentService svc;
    @Autowired MeterRegistry registry;

    @Test
    void incrementsCounter() {
        svc.process(Payment.sample());

        assertThat(registry.counter("orders.created", "channel", "web").count()).isEqualTo(1.0);
        assertThat(registry.timer("payment.processing").totalTime(TimeUnit.MILLISECONDS)).isPositive();
    }
}
```

---

## Laravel-də istifadəsi

### 1) Laravel Pulse — rəsmi lightweight observability

```bash
composer require laravel/pulse
php artisan pulse:install
php artisan migrate
```

```php
// config/pulse.php
return [
    'domain' => env('PULSE_DOMAIN'),
    'path' => env('PULSE_PATH', 'pulse'),
    'enabled' => env('PULSE_ENABLED', true),
    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),
        'database' => [
            'connection' => env('PULSE_DB_CONNECTION', null),
            'chunk' => 1000,
        ],
    ],
    'ingest' => [
        'driver' => env('PULSE_INGEST_DRIVER', 'storage'),
        'trim' => ['lottery' => [1, 1000], 'keep' => '7 days'],
        'redis' => [
            'connection' => env('PULSE_REDIS_CONNECTION'),
            'chunk' => 1000,
        ],
    ],
    'recorders' => [
        Recorders\CacheInteractions::class => ['enabled' => true, 'sample_rate' => 1],
        Recorders\Exceptions::class        => ['enabled' => true, 'sample_rate' => 1],
        Recorders\Queues::class            => ['enabled' => true, 'sample_rate' => 1],
        Recorders\Servers::class           => ['enabled' => true, 'directories' => ['/']],
        Recorders\SlowJobs::class          => ['enabled' => true, 'threshold' => 1000],
        Recorders\SlowQueries::class       => ['enabled' => true, 'threshold' => 1000],
        Recorders\SlowRequests::class      => ['enabled' => true, 'threshold' => 1000],
        Recorders\UserJobs::class          => ['enabled' => true],
        Recorders\UserRequests::class      => ['enabled' => true],
    ],
];
```

Dashboard `/pulse` altındadır — aktiv user-lər, slow endpoints, slow queries, failed jobs, exceptions, server load.

Custom recorder:

```php
use Laravel\Pulse\Facades\Pulse;

// bir event-də
Pulse::record(
    type: 'order_created',
    key: (string) $order->id,
    value: $order->total,
)->max()->sum();

// Laravel event listener-də
class OrderCreatedListener
{
    public function handle(OrderCreated $event): void
    {
        Pulse::set('active_orders', $event->order->id, 1);
    }
}
```

### 2) Telescope — dev diaqnostikası

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

```php
// app/Providers/TelescopeServiceProvider.php
public function register(): void
{
    Telescope::night();
    Telescope::filter(function (IncomingEntry $entry) {
        if ($this->app->environment('local')) {
            return true;
        }
        return $entry->isReportableException()
            || $entry->isFailedJob()
            || $entry->isSlowQuery()
            || $entry->hasMonitoredTag();
    });
}
```

Telescope-də görünənlər: requests, jobs, commands, queries, cache, redis, mail, notifications, events, exceptions, logs, dumps, schedule, gates.

### 3) Prometheus PHP client — custom metric

```bash
composer require promphp/prometheus_client_php
```

```php
namespace App\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis as RedisStorage;

class Metrics
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $storage = new RedisStorage([
            'host' => config('database.redis.default.host'),
            'port' => config('database.redis.default.port'),
        ]);
        $this->registry = new CollectorRegistry($storage);
    }

    public function ordersCreated(string $channel): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'app', 'orders_created_total',
            'Total orders created', ['channel']
        );
        $counter->inc([$channel]);
    }

    public function paymentDuration(float $seconds, string $gateway): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            'app', 'payment_duration_seconds',
            'Payment processing duration',
            ['gateway'],
            [0.05, 0.1, 0.25, 0.5, 1, 2, 5]
        );
        $histogram->observe($seconds, [$gateway]);
    }

    public function registry(): CollectorRegistry
    {
        return $this->registry;
    }
}
```

Prometheus export route:

```php
// routes/web.php
use Prometheus\RenderTextFormat;

Route::get('/metrics', function (Metrics $metrics) {
    $renderer = new RenderTextFormat();
    return response($renderer->render($metrics->registry()->getMetricFamilySamples()))
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
});
```

### 4) OpenTelemetry PHP SDK — auto-instrumentation

```bash
pecl install opentelemetry
composer require open-telemetry/sdk open-telemetry/exporter-otlp open-telemetry/opentelemetry-auto-laravel
```

```ini
; php.ini
extension=opentelemetry.so
```

`.env`:
```
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=orders-api
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_PROPAGATORS=tracecontext,baggage
OTEL_TRACES_SAMPLER=parentbased_traceidratio
OTEL_TRACES_SAMPLER_ARG=0.1
```

Auto-instrumentation-la:
- HTTP server request-ləri
- Guzzle / Laravel Http client
- Database queries
- Cache, Queue job-ları

Manual span yaratmaq:

```php
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;

class CheckoutService
{
    public function checkout(Cart $cart): Order
    {
        $tracer = Globals::tracerProvider()->getTracer('checkout');
        $span = $tracer->spanBuilder('checkout-flow')
            ->setAttribute('tenant', $cart->tenant)
            ->setAttribute('cart.id', $cart->id)
            ->startSpan();

        $scope = $span->activate();
        try {
            $this->validateInventory($cart);
            $payment = $this->createPayment($cart);
            return $this->placeOrder($cart, $payment);
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

### 5) Events + Listeners ilə business metric

```php
// app/Events/OrderPlaced.php
class OrderPlaced
{
    public function __construct(public readonly Order $order) {}
}

// app/Listeners/RecordOrderMetrics.php
class RecordOrderMetrics
{
    public function __construct(private readonly Metrics $metrics) {}

    public function handle(OrderPlaced $event): void
    {
        $this->metrics->ordersCreated($event->order->channel);
        Pulse::record('business.order', (string) $event->order->id, $event->order->total);
    }
}
```

### 6) `composer.json`

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.0",
        "laravel/pulse": "^1.2",
        "laravel/horizon": "^5.24",
        "promphp/prometheus_client_php": "^2.11",
        "open-telemetry/sdk": "^1.0",
        "open-telemetry/exporter-otlp": "^1.0",
        "open-telemetry/opentelemetry-auto-laravel": "^1.0"
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Boot | Laravel |
|---|---|---|
| Unified API | Observation API (metric+trace+log) | Ayrı-ayrı (Pulse, Telescope, OTel) |
| Metric facade | Micrometer (13+ backend) | Prometheus PHP, Pulse storage |
| Built-in HTTP metrics | `http.server.requests` avtomatik | OTel auto-instrumentation lazımdır |
| Tracing bridge | Brave (Zipkin), OTel | OpenTelemetry PHP SDK |
| Trace context propagasiya | W3C + B3 avtomatik | OTel propagator |
| Annotasiya | `@Observed` | Yoxdur (listener əsaslı) |
| Dashboard (official) | Grafana + Prometheus | Pulse (lightweight), Horizon, Telescope |
| Slow query detect | Actuator + custom | Pulse `SlowQueries` recorder |
| Exception tracking | Actuator `/error` + external APM | Pulse + Sentry/Bugsnag |
| Low overhead production | Micrometer sampling | Pulse sample_rate + OTel sampling |
| SLA/SLO histogram | `distribution.slo` yml-da | Prometheus PHP ilə manual |
| Zero-config minimum | Actuator + one dependency | Pulse + migrate |
| Log correlation | `traceId` MDC-də | Laravel context + OTel |

---

## Niyə belə fərqlər var?

**Spring Boot-un enterprise fokusu.** Java enterprise-da observability on illərdir əsas tələbdir — JMX, Spring Batch job tracking, actuator endpoint-lər bu ənənədən gəlir. Micrometer "SLF4J-in metric dünyadakı qarşılığı" kimi yaradıldı — tətbiq kodu heç bir backend-ə bağlı deyil, sadəcə `MeterRegistry` interface-ə yazır.

**Laravel-in "lightweight by default" fəlsəfəsi.** Laravel Pulse 2024-də (Laravel 11 ilə) gəldi — əvvəl 3rd party paketlər (Spatie, Beyondcode) var idi. Pulse Laravel tərzində yaradılıb — sadə dashboard, aggregate metrikləri DB və ya Redis-də saxlayır, enterprise APM kimi qabarıq deyil.

**OpenTelemetry-nin yüksəlişi.** PHP-də auto-instrumentation mümkün olması üçün `opentelemetry` PECL extension lazımdır — Laravel mühiti bunu tədricən qəbul edir. Spring tərəfində isə micrometer-tracing-bridge-otel artıq stabildir və bir asılılıq ilə bütün request-lər instrument olunur.

**Unified Observation API.** Spring Boot 3-ün əsas yeniliyi — eyni `Observation` obyektindən həm metric (Micrometer), həm trace (Micrometer Tracing), həm log context yaradılır. Laravel-də bu üç şey ayrı-ayrıdır: Pulse metrics, OTel trace, Monolog log. İnteqrasiya custom kod tələb edir.

**SLO histogram-ları.** Micrometer `distribution.slo` yml açarı ilə endpoint üçün SLI thresholds qurub Prometheus-da `*_bucket` histogram verir. Laravel-də bu manual — Prometheus PHP-də histogram bucket siyahısı verməlisən.

**Overhead modeli.** JVM-də observation sistem daxilindədir — çox aşağı qiymətli. PHP-də hər sorğu ayrı process olduğu üçün OTel SDK initialization vaxtı əlavə edir — amma auto-instrumentation C extension vasitəsilə yerinə yetirilir, buna görə rahatdır.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Unified **Observation API** — metric + trace + log bir abstraksiyada
- `@Observed` annotasiya — deklarativ observability
- `ObservationRegistry` + custom `ObservationHandler`
- **Micrometer** 13+ backend (Prometheus, Datadog, New Relic, CloudWatch, Wavefront, SignalFx, Dynatrace, StatsD, Graphite, Elastic, JMX, Stackdriver)
- Auto-instrumentation: HTTP server/client, RestClient, WebClient, Kafka, RabbitMQ, JDBC, R2DBC, Redis
- `distribution.slo` yml açarı
- `SchedulerObservation` — `@Scheduled` method-ları üçün
- Actuator `/observations` endpoint
- MDC avtomatik trace/span ID fill
- Brave bridge + OTel bridge seçimi

**Yalnız Laravel-də:**
- **Laravel Pulse** — rəsmi lightweight real-time dashboard (aktiv user, slow query, queue depth)
- **Telescope** — dev-time request, job, query inspector
- **Horizon** — Redis queue dashboard (Pulse-a qədər də var idi)
- Pulse `Recorder` plugin sistemi (DB, cache, queue, server, exception recorder)
- Pulse `sample_rate` per-recorder konfiqurasiya
- Pulse custom dashboard card yaratma (Livewire)
- `Http::fake()` request fake-ləməsi (Telescope altında görünür)
- Auto-instrumentation opentelemetry PECL extension ilə

---

## Best Practices

1. **Tətbiqin adı və environment-i tag-da olsun** — multi-tenant observability üçün.
2. **Yüksək kardinal-ı (user ID, order ID) ayrıca qeyd et** — Micrometer-də `highCardinalityKeyValue`, metric-də istifadə etmə.
3. **Production-da trace sampling aşağı sal** — 10%, slow request-ləri isə `tail-based sampling` ilə tut.
4. **HTTP endpoint-ləri təmiz URI ilə qeyd et** — `/orders/{id}` olmalıdır, `/orders/42` yox, yoxsa kardinallıq partlayar.
5. **Laravel Pulse production-da ingest driver Redis seç** — DB-yə writes yükü salma.
6. **Prometheus Laravel endpoint-i authenticate et** — public açıq olmasın.
7. **Logs-da `traceId`/`spanId` olsun** — log ilə trace arası link üçün.
8. **SLO-ları kod deyil konfiqurasiya et** — dəyişmə ehtiyacı olanda re-deploy lazım olmasın.
9. **Slow query threshold-u həssas olma** — çox gürültü yaradar, Pulse default 1000ms yaxşıdır.
10. **Custom metric-lər üçün adlandırma konvensiyası** — `domain.entity.action` (`orders.created`, `payments.processed`).
11. **OpenTelemetry exporter OTLP üzərindən** — bir collector çoxlu backend-ə paylayar.
12. **Error rate alerting-i request latency-dən vacib tut** — `5xx / total > 1%` qaydası.

---

## Yekun

Spring Boot 3 + **Observation API** + **Micrometer** observability-ni "zero-config default" vəziyyətinə gətirib — bir starter, bir neçə yml açarı və endpoint-lər üçün HTTP metric, JVM metric, tracing avtomatik aktiv olur. `@Observed` annotasiya business method-lar üçün də eyni infrastruktura qoşulmanı sadələşdirir.

Laravel-də observability daha modulyar: **Pulse** real-time lightweight dashboard, **Telescope** dev tool, **Horizon** queue-lər, **OpenTelemetry PHP SDK** distributed tracing. Prometheus export isə `promphp/prometheus_client_php` ilə əl ilə qurulur. 2024-dən sonra Pulse + OTel kombinasiyası Laravel-in "default observability stack"-ı sayıla bilər.

Seçim qaydası: **Spring-də** Prometheus + Grafana + Zipkin/Tempo (və ya OTel collector) — zəngin enterprise stack. **Laravel-də** Pulse daxili metric-lər, OTel ilə distributed tracing, Sentry/Bugsnag error tracking — lightweight sadə stack. Hər iki tərəf də OTel semantic convention-lara keçir — uzun müddətdə backend tutarlılığı artacaq.
