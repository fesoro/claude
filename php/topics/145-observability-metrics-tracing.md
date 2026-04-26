# Observability, Metrics və Distributed Tracing (Senior)

## Observability nədir?

Observability — bir sistemin daxili vəziyyətini xarici çıxışlarına (logs, metrics, traces) əsasən anlamaq qabiliyyətidir. Sadəcə "sistem işləyirmi?" sualına cavab vermir, "sistem niyə belə davranır?" sualına cavab verir.

**Monitoring vs Observability fərqi:**

| Monitoring | Observability |
|---|---|
| Öncədən bilinən xətaları aşkar edir | Bilinməyən xətaları da aşkar etməyə imkan verir |
| "Nə baş verdi?" sualına cavab verir | "Niyə baş verdi?" sualına cavab verir |
| Dashboard-lar və alertlər | Sorğular, explorasiya, kontekst |
| Reactive (reaktiv) | Proactive (proaktiv) |
| Metrics-focused | Logs + Metrics + Traces |

---

## 3 Pillars of Observability

### 1. Logging

Strukturlaşdırılmış hadisələrin qeydiyyatı. Hər log entry bir hadisənin snapshot-ıdır.

*Strukturlaşdırılmış hadisələrin qeydiyyatı. Hər log entry bir hadisəni üçün kod nümunəsi:*
```php
// Pis logging — struktursuz
Log::info("User 123 bought product 456 for 99.99");

// Yaxşı logging — strukturlaşdırılmış (JSON)
Log::info("order.placed", [
    'user_id'    => 123,
    'product_id' => 456,
    'amount'     => 99.99,
    'currency'   => 'USD',
    'trace_id'   => request()->header('X-Trace-Id'),
    'timestamp'  => now()->toIso8601String(),
]);
```

**Log Levels (RFC 5424):**
- `emergency` — sistem işləmir
- `alert` — dərhal müdaxilə lazımdır
- `critical` — kritik vəziyyət
- `error` — runtime xətası (exception)
- `warning` — gözlənilməz vəziyyət, lakin xəta deyil
- `notice` — normal, amma əhəmiyyətli hadisə
- `info` — maraqlı hadisələr
- `debug` — development-də ətraflı məlumat

### 2. Metrics

Vaxt seriyası ilə toplanmış rəqəmsal ölçümlər. Aggregation-a imkan verir.

### 3. Tracing

Bir request-in birdən çox servis arasında keçdiyi yolun izlənməsi. Latency, bottleneck-ləri müəyyən edir.

---

## Metrics Növləri

### Counter

Yalnız artır (reset olunmur). Ümumi sayı ölçür.

*Yalnız artır (reset olunmur). Ümumi sayı ölçür üçün kod nümunəsi:*
```php
// Prometheus PHP client
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class MetricsService
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $adapter = new Redis(['host' => config('database.redis.default.host')]);
        $this->registry = new CollectorRegistry($adapter);
    }

    public function incrementHttpRequests(string $method, string $path, int $statusCode): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            namespace: 'app',
            name: 'http_requests_total',
            help: 'Total number of HTTP requests',
            labels: ['method', 'path', 'status_code']
        );

        $counter->incBy(1, [$method, $path, (string) $statusCode]);
    }

    public function incrementOrdersPlaced(string $currency): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            namespace: 'app',
            name: 'orders_placed_total',
            help: 'Total number of orders placed',
            labels: ['currency']
        );

        $counter->inc([$currency]);
    }
}
```

### Gauge

Artıb-azala bilər. Cari vəziyyəti göstərir.

*Artıb-azala bilər. Cari vəziyyəti göstərir üçün kod nümunəsi:*
```php
// Bu kod Prometheus Gauge metriyi ilə aktiv bağlantı sayını izləməyi göstərir
public function setActiveConnections(int $count): void
{
    $gauge = $this->registry->getOrRegisterGauge(
        namespace: 'app',
        name: 'active_connections',
        help: 'Current number of active database connections',
        labels: ['pool']
    );

    $gauge->set($count, ['primary']);
}

public function setQueueDepth(string $queueName, int $depth): void
{
    $gauge = $this->registry->getOrRegisterGauge(
        namespace: 'app',
        name: 'queue_depth',
        help: 'Number of jobs waiting in queue',
        labels: ['queue']
    );

    $gauge->set($depth, [$queueName]);
}
```

### Histogram

Dəyərləri bucket-lərə bölür. Latency distribution üçün ideal.

*Dəyərləri bucket-lərə bölür. Latency distribution üçün ideal üçün kod nümunəsi:*
```php
// Bu kod Prometheus Histogram ilə HTTP sorğu gecikmələrinin ölçülməsini göstərir
public function observeRequestDuration(string $route, float $durationSeconds): void
{
    $histogram = $this->registry->getOrRegisterHistogram(
        namespace: 'app',
        name: 'http_request_duration_seconds',
        help: 'HTTP request duration in seconds',
        labels: ['route'],
        buckets: [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
    );

    $histogram->observe($durationSeconds, [$route]);
}

// İstifadə:
// p50, p95, p99 latency-ni hesablamağa imkan verir
// histogram_quantile(0.95, rate(app_http_request_duration_seconds_bucket[5m]))
```

### Summary

Histogram-a oxşar, amma client-side quantile hesablayır. Aggregation-a uyğun deyil.

*Histogram-a oxşar, amma client-side quantile hesablayır. Aggregation-a üçün kod nümunəsi:*
```php
// Summary — adətən histogram daha çox istifadə olunur
// Çünki summary-ni server-side aggregate etmək olmur
public function observeDbQueryDuration(float $durationSeconds): void
{
    $summary = $this->registry->getOrRegisterSummary(
        namespace: 'app',
        name: 'db_query_duration_seconds',
        help: 'Database query duration in seconds',
        labels: ['query_type'],
        maxAgeSeconds: 600,
        quantiles: [0.5, 0.9, 0.99]
    );

    $summary->observe($durationSeconds, ['select']);
}
```

---

## Prometheus + Grafana Laravel-də

### Composer paketi

*Composer paketi üçün kod nümunəsi:*
```bash
composer require promphp/prometheus_client_php
```

### Metrics Middleware

*Metrics Middleware üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis as PrometheusRedis;
use Prometheus\RenderTextFormat;

class PrometheusMetricsMiddleware
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $adapter = new PrometheusRedis([
            'host'     => config('database.redis.default.host', '127.0.0.1'),
            'port'     => config('database.redis.default.port', 6379),
            'password' => config('database.redis.default.password'),
            'timeout'  => 0.1,
            'database' => 1, // ayrı DB istifadə et
        ]);

        $this->registry = new CollectorRegistry($adapter);
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $duration   = microtime(true) - $startTime;
        $route      = $request->route()?->getName() ?? $request->path();
        $method     = $request->method();
        $statusCode = (string) $response->getStatusCode();

        $this->recordRequest($method, $route, $statusCode, $duration);

        return $response;
    }

    private function recordRequest(
        string $method,
        string $route,
        string $statusCode,
        float $duration
    ): void {
        // Counter
        $counter = $this->registry->getOrRegisterCounter(
            'laravel',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'route', 'status_code']
        );
        $counter->incBy(1, [$method, $route, $statusCode]);

        // Histogram
        $histogram = $this->registry->getOrRegisterHistogram(
            'laravel',
            'http_request_duration_seconds',
            'HTTP request duration',
            ['method', 'route'],
            [0.01, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0]
        );
        $histogram->observe($duration, [$method, $route]);

        // Error counter
        if ((int) $statusCode >= 500) {
            $errorCounter = $this->registry->getOrRegisterCounter(
                'laravel',
                'http_errors_total',
                'Total HTTP 5xx errors',
                ['method', 'route', 'status_code']
            );
            $errorCounter->incBy(1, [$method, $route, $statusCode]);
        }
    }
}
```

### Metrics Endpoint

*Metrics Endpoint üçün kod nümunəsi:*
```php
// Bu kod Prometheus-un metrics-lərini HTTP endpoint vasitəsilə əlçatan edən controller-i göstərir
<?php

namespace App\Http\Controllers;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis as PrometheusRedis;

class MetricsController extends Controller
{
    public function __invoke(): \Illuminate\Http\Response
    {
        $adapter  = new PrometheusRedis(['host' => config('database.redis.default.host')]);
        $registry = new CollectorRegistry($adapter, false);

        $renderer = new RenderTextFormat();
        $result   = $renderer->render($registry->getMetricFamilySamples());

        return response($result, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
```

*return response($result, 200, ['Content-Type' => RenderTextFormat::MIM üçün kod nümunəsi:*
```php
// routes/web.php
Route::get('/metrics', MetricsController::class)
    ->middleware('auth.metrics'); // IP whitelist və ya token yoxlaması
```

### Kernel-ə əlavə etmək

*Kernel-ə əlavə etmək üçün kod nümunəsi:*
```php
// app/Http/Kernel.php
protected $middleware = [
    // ...
    \App\Http\Middleware\PrometheusMetricsMiddleware::class,
];
```

---

## RED Method

**R** — Rate (requests per second)  
**E** — Errors (error rate)  
**D** — Duration (latency)

Microservice-lər üçün ideal. Hər servisin performance-ını bu 3 metrikə görə ölçmək kifayətdir.

*Microservice-lər üçün ideal. Hər servisin performance-ını bu 3 metrikə üçün kod nümunəsi:*
```promql
# Rate — requests per second (son 5 dəqiqə)
rate(laravel_http_requests_total[5m])

# Error Rate — 5xx xətalarının faizi
rate(laravel_http_errors_total[5m]) / rate(laravel_http_requests_total[5m])

# Duration — p99 latency
histogram_quantile(0.99, rate(laravel_http_request_duration_seconds_bucket[5m]))
```

---

## USE Method

**U** — Utilization (resursun nə qədər məşğul olduğu)  
**S** — Saturation (kuyrukdakı iş miqdarı)  
**E** — Errors

Infrastructure/resource-lar üçün ideal (CPU, memory, disk, network).

*Infrastructure/resource-lar üçün ideal (CPU, memory, disk, network) üçün kod nümunəsi:*
```promql
# CPU Utilization
100 - (avg by(instance)(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)

# Memory Saturation (swap istifadəsi)
node_memory_SwapUsed_bytes / node_memory_SwapTotal_bytes

# Disk Errors
rate(node_disk_io_time_seconds_total[5m])
```

---

## Distributed Tracing

### Əsas anlayışlar

**Trace** — bir request-in bütün servislərdəki izi. Unikal `trace_id` ilə identifikasiya olunur.

**Span** — bir operasiyanın başlanğıc və bitiş vaxtı. Hər trace bir və ya daha çox span-dan ibarətdir.

**Context Propagation** — trace kontekstinin (trace_id, span_id) servislərarası ötürülməsi. HTTP header-ları vasitəsilə həyata keçirilir.

```
Trace ID: abc-123
├── Span: HTTP GET /orders (0ms - 250ms)
│   ├── Span: AuthMiddleware (0ms - 5ms)
│   ├── Span: DB SELECT orders (10ms - 50ms)
│   ├── Span: HTTP POST payment-service (60ms - 200ms)
│   │   ├── Span: DB INSERT payments (70ms - 90ms)
│   │   └── Span: Stripe API call (100ms - 190ms)
│   └── Span: Redis cache SET (210ms - 215ms)
```

---

## OpenTelemetry (OTel)

OpenTelemetry — vendor-neutral observability framework. CNCF proyektidir.

*OpenTelemetry — vendor-neutral observability framework. CNCF proyektid üçün kod nümunəsi:*
```bash
composer require open-telemetry/sdk
composer require open-telemetry/exporter-otlp
composer require open-telemetry/opentelemetry-auto-laravel
```

### OTel Setup Laravel-də

*OTel Setup Laravel-də üçün kod nümunəsi:*
```php
// Bu kod OpenTelemetry-nin Laravel tətbiqinə inteqrasiyasını göstərir
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpExporter;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TracerInterface::class, function () {
            $exporter = OtlpHttpExporter::fromConnectionString(
                config('observability.otlp_endpoint', 'http://localhost:4318')
            );

            $resource = ResourceInfoFactory::defaultResource()->merge(
                \OpenTelemetry\SDK\Resource\ResourceInfo::create(Attributes::create([
                    ResourceAttributes::SERVICE_NAME    => config('app.name'),
                    ResourceAttributes::SERVICE_VERSION => config('app.version', '1.0.0'),
                    ResourceAttributes::DEPLOYMENT_ENVIRONMENT => app()->environment(),
                ]))
            );

            $tracerProvider = TracerProvider::builder()
                ->addSpanProcessor(new BatchSpanProcessor($exporter))
                ->setResource($resource)
                ->build();

            Globals::registerInitializer(function () use ($tracerProvider) {
                return \OpenTelemetry\SDK\Common\Configuration\Configuration::builder()
                    ->setTracerProvider($tracerProvider)
                    ->build();
            });

            return $tracerProvider->getTracer('laravel-app');
        });
    }
}
```

### Tracing Middleware

*Tracing Middleware üçün kod nümunəsi:*
```php
// Bu kod hər HTTP sorğusu üçün distributed trace span-ı yaradan middleware-i göstərir
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

class OpenTelemetryTracingMiddleware
{
    public function __construct(private TracerInterface $tracer) {}

    public function handle(Request $request, Closure $next): mixed
    {
        // Incoming context-i extract et (parent span varsa)
        $propagator = TraceContextPropagator::getInstance();
        $context    = $propagator->extract($request->headers->all());

        $span = $this->tracer
            ->spanBuilder($request->method() . ' ' . $request->path())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setParent($context)
            ->startSpan();

        $span->setAttribute('http.method', $request->method());
        $span->setAttribute('http.url', $request->fullUrl());
        $span->setAttribute('http.route', $request->route()?->getName() ?? '');
        $span->setAttribute('user.id', auth()->id() ?? 'anonymous');

        $scope = $span->activate();

        try {
            $response = $next($request);

            $span->setAttribute('http.status_code', $response->getStatusCode());

            if ($response->getStatusCode() >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'HTTP ' . $response->getStatusCode());
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
}
```

### Service-də manual span

*Service-də manual span üçün kod nümunəsi:*
```php
// Bu kod service daxilində ətraflı performans izləmə üçün manual span yaratmağı göstərir
<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\SpanKind;

class OrderService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private TracerInterface $tracer
    ) {}

    public function createOrder(array $data): Order
    {
        $span = $this->tracer
            ->spanBuilder('OrderService::createOrder')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('order.user_id', $data['user_id']);
        $span->setAttribute('order.item_count', count($data['items']));

        $scope = $span->activate();

        try {
            $order = $this->orderRepository->create($data);
            $span->setAttribute('order.id', $order->id);
            return $order;
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

---

## Correlation ID Implementation

Correlation ID — bir request-in bütün servis log-larında eyni ID ilə axtarıla bilməsi üçün istifadə olunur.

*Correlation ID — bir request-in bütün servis log-larında eyni ID ilə a üçün kod nümunəsi:*
```php
// Bu kod hər sorğuya unikal Correlation ID əlavə edən tracing middleware-i göstərir
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CorrelationIdMiddleware
{
    public const HEADER_NAME = 'X-Correlation-Id';
    public const LOG_KEY     = 'correlation_id';

    public function handle(Request $request, Closure $next): mixed
    {
        // Upstream-dən gəlirsə istifadə et, yoxdursa yenisini yarat
        $correlationId = $request->header(self::HEADER_NAME)
            ?? (string) Str::uuid();

        // Request-ə əlavə et (sonrakı middleware-lər oxuya bilsin)
        $request->headers->set(self::HEADER_NAME, $correlationId);

        // Log context-ə əlavə et (bütün log-larda avtomatik görünsün)
        Log::shareContext([
            self::LOG_KEY => $correlationId,
            'request_id'  => (string) Str::uuid(), // bu request üçün unikal
        ]);

        $response = $next($request);

        // Response header-ına əlavə et (client debug edə bilsin)
        $response->headers->set(self::HEADER_NAME, $correlationId);

        return $response;
    }
}
```

### Correlation ID-ni HTTP Client-ə ötürmək

*Correlation ID-ni HTTP Client-ə ötürmək üçün kod nümunəsi:*
```php
// Bu kod Correlation ID-nin xarici HTTP sorğularına header vasitəsilə ötürülməsini göstərir
<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalApiService
{
    private function httpClient(): PendingRequest
    {
        return Http::withHeaders([
            'X-Correlation-Id' => $this->getCorrelationId(),
        ])->timeout(30);
    }

    private function getCorrelationId(): string
    {
        // Log shared context-dən oxu
        return app('log')->sharedContext()['correlation_id']
            ?? request()->header('X-Correlation-Id')
            ?? 'unknown';
    }

    public function fetchUser(int $userId): array
    {
        $response = $this->httpClient()
            ->get(config('services.user_service.url') . "/users/{$userId}");

        if ($response->failed()) {
            Log::error('user_service.fetch_failed', [
                'user_id'    => $userId,
                'status'     => $response->status(),
            ]);

            throw new \RuntimeException("Failed to fetch user {$userId}");
        }

        return $response->json();
    }
}
```

---

## W3C Trace Context Standard

W3C Trace Context — HTTP header-larla trace context-in ötürülməsi üçün standart format.

**`traceparent` header:**
```
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
             ^  ^                                ^                ^
             version  trace-id (128bit hex)     parent-span-id   flags
```

**`tracestate` header:**
```
tracestate: rojo=00f067aa0ba902b7,congo=t61rcWkgMzE
```

*tracestate: rojo=00f067aa0ba902b7,congo=t61rcWkgMzE üçün kod nümunəsi:*
```php
// Laravel-də W3C header-larını propagate etmək
Http::withHeaders([
    'traceparent' => $this->buildTraceparent(),
    'tracestate'  => $this->buildTracestate(),
])->post('http://payment-service/charge', $data);

private function buildTraceparent(): string
{
    $traceId  = bin2hex(random_bytes(16)); // 32 hex chars
    $spanId   = bin2hex(random_bytes(8));  // 16 hex chars
    $flags    = '01'; // sampled
    return "00-{$traceId}-{$spanId}-{$flags}";
}
```

---

## APM Tools

### Datadog

*Datadog üçün kod nümunəsi:*
```php
// config/datadog.php
// composer require datadog/dd-trace

// Auto-instrumentation ilə — konfiqurasiyanı .env-ə əlavə et
// DD_SERVICE=laravel-app
// DD_ENV=production
// DD_VERSION=1.2.3
// DD_AGENT_HOST=datadog-agent

// Manual span
$tracer = \DDTrace\GlobalTracer::get();
$scope  = $tracer->startActiveSpan('custom.operation');
$scope->getSpan()->setTag('custom.tag', 'value');
// ...
$scope->close();
```

### New Relic

*New Relic üçün kod nümunəsi:*
```php
// Auto-instrumentation — php.ini-yə agent əlavə et
// extension=newrelic.so

// Custom event göndərmək
newrelic_record_custom_event('OrderPlaced', [
    'order_id'   => $order->id,
    'user_id'    => $order->user_id,
    'amount'     => $order->total,
]);

// Custom metric
newrelic_custom_metric('Custom/OrderRevenue', $order->total);
```

### Elastic APM

*Elastic APM üçün kod nümunəsi:*
```bash
composer require elastic/apm-agent
```

*composer require elastic/apm-agent üçün kod nümunəsi:*
```php
// config/elastic-apm.php
return [
    'serverUrl' => env('ELASTIC_APM_SERVER_URL', 'http://localhost:8200'),
    'serviceName' => env('ELASTIC_APM_SERVICE_NAME', 'laravel-app'),
    'environment'  => env('APP_ENV', 'production'),
    'logLevel'     => 'WARNING',
];

// Custom transaction
ElasticApm::captureCurrentTransaction('order-processing', 'custom', function () use ($order) {
    ElasticApm::getCurrentTransaction()->setLabel('order_id', $order->id);
    $this->processOrder($order);
});
```

---

## Laravel Pulse

Laravel Pulse — real-time application performance monitoring. Laravel 10.x+ ilə gəlir.

*Laravel Pulse — real-time application performance monitoring. Laravel  üçün kod nümunəsi:*
```bash
composer require laravel/pulse
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
php artisan migrate
```

*php artisan migrate üçün kod nümunəsi:*
```php
// config/pulse.php
return [
    'recorders' => [
        Recorders\CacheInteractions::class => [
            'enabled' => env('PULSE_CACHE_INTERACTIONS_ENABLED', true),
            'ignore'  => ['#^laravel:pulse#'],
        ],
        Recorders\Exceptions::class => [
            'enabled' => env('PULSE_EXCEPTIONS_ENABLED', true),
            'ignore'  => [],
            'sample_rate' => 1,
        ],
        Recorders\Queues::class => [
            'enabled' => env('PULSE_QUEUES_ENABLED', true),
            'ignore'  => [],
            'sample_rate' => 1,
        ],
        Recorders\Requests::class => [
            'enabled'     => env('PULSE_REQUESTS_ENABLED', true),
            'sample_rate' => 1,
            'ignore'      => ['/health', '/metrics'],
        ],
        Recorders\SlowJobs::class => [
            'enabled'   => env('PULSE_SLOW_JOBS_ENABLED', true),
            'threshold' => env('PULSE_SLOW_JOBS_THRESHOLD', 1000), // ms
        ],
        Recorders\SlowOutgoingRequests::class => [
            'enabled'   => env('PULSE_SLOW_OUTGOING_REQUESTS_ENABLED', true),
            'threshold' => 1000,
        ],
        Recorders\SlowQueries::class => [
            'enabled'   => env('PULSE_SLOW_QUERIES_ENABLED', true),
            'threshold' => env('PULSE_SLOW_DB_THRESHOLD', 1000), // ms
            'highlight_threshold' => 2000,
        ],
        Recorders\UserRequests::class => ['enabled' => true],
        Recorders\UserJobs::class     => ['enabled' => true],
    ],
];
```

*Recorders\UserJobs::class     => ['enabled' => true], üçün kod nümunəsi:*
```php
// Custom Pulse metric
use Laravel\Pulse\Facades\Pulse;

Pulse::record('order_placed', $order->id, $order->total)
    ->count()
    ->sum('revenue');
```

---

## Laravel Telescope

Laravel Telescope — development-də debugging tool.

*Laravel Telescope — development-də debugging tool üçün kod nümunəsi:*
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

*php artisan migrate üçün kod nümunəsi:*
```php
// app/Providers/TelescopeServiceProvider.php
protected function gate(): void
{
    Gate::define('viewTelescope', function (User $user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}

// Yalnız local environment-da aktiv et
public function register(): void
{
    Telescope::night(); // dark mode

    $this->hideSensitiveRequestDetails();

    $isLocal = $this->app->environment('local');

    Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
        if ($isLocal) {
            return true;
        }
        // Production-da yalnız xətaları saxla
        return $entry->isReportableException()
            || $entry->isFailedRequest()
            || $entry->isFailedJob()
            || $entry->hasMonitoredTag();
    });
}
```

---

## Laravel Horizon

Laravel Horizon — Redis queue monitoring dashboard.

*Laravel Horizon — Redis queue monitoring dashboard üçün kod nümunəsi:*
```bash
composer require laravel/horizon
php artisan horizon:install
```

*php artisan horizon:install üçün kod nümunəsi:*
```php
// config/horizon.php
return [
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses'  => 10,
                'balanceMaxShift'   => 1,
                'balanceCooldown'   => 3,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
        ],
    ],

    'waits' => [
        'redis:default' => 60, // 60 saniyəyə qədər gözləmə alerti
    ],

    'trim' => [
        'recent'        => 60,   // dəqiqə
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080, // 1 həftə
        'failed'        => 10080,
        'monitored'     => 10080,
    ],
];
```

---

## Health Check Endpoints

*Health Check Endpoints üçün kod nümunəsi:*
```php
// Bu kod DB, Redis və digər xidmətlərin sağlamlığını yoxlayan health check endpoint-ini göstərir
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks   = [];
        $healthy  = true;
        $started  = microtime(true);

        // Database check
        try {
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $healthy = false;
        }

        // Redis check
        try {
            Redis::ping();
            $checks['redis'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['redis'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $healthy = false;
        }

        // Cache check
        try {
            $key = 'health-check-' . uniqid();
            Cache::put($key, true, 5);
            Cache::get($key);
            Cache::forget($key);
            $checks['cache'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'fail', 'error' => $e->getMessage()];
        }

        // Queue check
        try {
            $size = app('queue')->size('default');
            $checks['queue'] = [
                'status'     => $size < 1000 ? 'ok' : 'degraded',
                'queue_size' => $size,
            ];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'fail'];
        }

        // Disk space check
        $freeBytes  = disk_free_space('/');
        $totalBytes = disk_total_space('/');
        $freePercent = ($freeBytes / $totalBytes) * 100;
        $checks['disk'] = [
            'status'        => $freePercent > 10 ? 'ok' : 'critical',
            'free_percent'  => round($freePercent, 2),
        ];

        $duration = round((microtime(true) - $started) * 1000, 2);

        return response()->json([
            'status'   => $healthy ? 'ok' : 'fail',
            'checks'   => $checks,
            'duration' => $duration . 'ms',
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
```

*], $healthy ? 200 : 503); üçün kod nümunəsi:*
```php
// routes/api.php
Route::get('/health', HealthCheckController::class); // full check
Route::get('/health/live', fn() => response()->json(['status' => 'ok']));   // Kubernetes liveness
Route::get('/health/ready', ReadinessController::class);                      // Kubernetes readiness
```

---

## SLI, SLO, SLA

**SLI (Service Level Indicator)** — ölçülən metric. Məsələn: "request-lərin neçə faizi 200ms-dən az gəlir?"

**SLO (Service Level Objective)** — SLI-nin hədəfi. Məsələn: "request-lərin 99.9%-i 200ms-dən az gəlməlidir."

**SLA (Service Level Agreement)** — müştəri ilə müqavilə. SLO-dan daha az ambitioz olur. SLO pozulsa SLA da pozulur, lakin SLA üçün cəza var.

| | SLI | SLO | SLA |
|---|---|---|---|
| Nədir? | Ölçüm | Hədəf | Müqavilə |
| Kim üçün? | Engineering | Engineering + Product | Müştəri |
| Pozulsa? | Alert | Incident | Refund/Penalty |

### Error Budget

```
Error Budget = 1 - SLO

Əgər SLO = 99.9% (3 nines)
Error Budget = 0.1% = 43.8 dəqiqə/ay

Əgər bu ayda 20 dəqiqə downtime olubsa:
Qalan Error Budget = 23.8 dəqiqə

Error Budget tükənərsə → yeni feature deploy etmə, yalnız reliability işi et.
```

*Error Budget tükənərsə → yeni feature deploy etmə, yalnız reliability  üçün kod nümunəsi:*
```php
// Error budget tracking
class ErrorBudgetService
{
    public function getRemainingBudgetMinutes(float $sloPercent = 99.9): float
    {
        $totalMinutesInMonth = 30 * 24 * 60; // 43200 dəqiqə
        $budgetMinutes       = $totalMinutesInMonth * ((100 - $sloPercent) / 100);

        // Prometheus-dan actual downtime-ı al
        $usedMinutes = $this->getActualDowntimeMinutes();

        return max(0, $budgetMinutes - $usedMinutes);
    }

    private function getActualDowntimeMinutes(): float
    {
        // Prometheus query ilə real downtime hesabla
        // Bu ay 5xx error rate-in SLO-nu keçdiyi dəqiqələr
        return Cache::remember('error_budget_used', 300, function () {
            // Prometheus HTTP API sorğusu
            $response = Http::get(config('prometheus.url') . '/api/v1/query', [
                'query' => 'sum(increase(laravel_http_errors_total[30d]))',
            ]);

            return $response->json('data.result.0.value.1', 0) / 60;
        });
    }
}
```

---

## On-Call Alerting

### Alertmanager konfiqurasiyası (Prometheus ilə)

*Alertmanager konfiqurasiyası (Prometheus ilə) üçün kod nümunəsi:*
```yaml
# alertmanager.yml
global:
  slack_api_url: 'https://hooks.slack.com/services/...'

route:
  group_by: ['alertname', 'service']
  group_wait: 30s
  group_interval: 5m
  repeat_interval: 4h
  receiver: 'on-call-team'
  routes:
    - match:
        severity: critical
      receiver: 'pagerduty-critical'
    - match:
        severity: warning
      receiver: 'slack-warning'

receivers:
  - name: 'on-call-team'
    pagerduty_configs:
      - routing_key: '<PAGERDUTY_KEY>'
    slack_configs:
      - channel: '#incidents'
        title: '{{ .GroupLabels.alertname }}'
        text: '{{ range .Alerts }}{{ .Annotations.summary }}{{ end }}'

  - name: 'slack-warning'
    slack_configs:
      - channel: '#alerts'
```

*- channel: '#alerts' üçün kod nümunəsi:*
```yaml
# Alert rules (Prometheus)
groups:
  - name: laravel
    rules:
      - alert: HighErrorRate
        expr: rate(laravel_http_errors_total[5m]) / rate(laravel_http_requests_total[5m]) > 0.05
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value | humanizePercentage }}"

      - alert: SlowResponseTime
        expr: histogram_quantile(0.95, rate(laravel_http_request_duration_seconds_bucket[5m])) > 1
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Slow response time"
          description: "p95 latency is {{ $value }}s"
```

---

## İntervyu Sualları

**S: Observability ilə Monitoring-in fərqi nədir?**

C: Monitoring öncədən bilinən xətaları aşkar edir, "sistem işləyirmi?" sualına cavab verir. Observability isə sistemin daxili vəziyyətini xarici siqnallara əsasən anlamaq qabiliyyətidir — bilinməyən xətaları da araşdırmağa imkan verir. Monitoring observability-nin subset-idir.

**S: Prometheus Histogram ilə Summary fərqi nədir?**

C: Histogram bucket-ləri server-side saxlayır, quantile-ları query zamanı hesablamağa imkan verir və multiple instance-ları aggregate etmək olur. Summary isə quantile-ları client-side hesablayır, daha az CPU istifadə edir lakin aggregate edilə bilmir. Production-da adətən Histogram üstünlük verilir.

**S: RED method nədir, nə vaxt istifadə edilir?**

C: Rate, Errors, Duration — mikroservis-lərin performance-ını ölçmək üçün minimal metric set-i. Hər servis üçün bu 3 metrik kifayətdir.

**S: Correlation ID nə üçün lazımdır?**

C: Distributed sistemdə bir user request-i onlarla servis log-ı yaradır. Correlation ID olmadan bu log-ları bir-birinə bağlamaq mümkün deyil. Correlation ID hər request üçün unikal bir ID-dir, bütün servislər arasında header vasitəsilə ötürülür.

**S: SLO ilə SLA fərqi nədir?**

C: SLO daxili hədəfdir (engineering üçün), SLA isə müştəri ilə müqavilədir. SLO adətən SLA-dan daha strict olur ki, müştəriyə söz verilmədən əvvəl daxili alert yaransın.

**S: Error budget nədir?**

C: Error budget = 1 - SLO. Məsələn 99.9% SLO ilə ayda 43.8 dəqiqə downtime icazəsi var. Bu budget tükənərsə yeni feature deploy etmə dayandırılır, yalnız reliability işi aparılır.

**S: OpenTelemetry nə üçün istifadə olunur?**

C: OTel vendor-neutral observability framework-dür. Kod bir dəfə instrumentasiya olunur, sonra Jaeger, Zipkin, Datadog, New Relic kimi istənilən backend-ə göndərmək olur. Vendor lock-in-in qarşısını alır.

**S: W3C Trace Context nədir?**

C: HTTP header-larla trace context-in ötürülməsi üçün standartdır. `traceparent` header trace-id, span-id və flags saxlayır. Bu standart olmadan hər vendor öz header formatını istifadə edirdi.

**S: Laravel Pulse ilə Telescope fərqi nədir?**

C: Telescope development-də debugging üçündür — hər request, query, job-u detallı saxlayır, production-a uyğun deyil. Pulse isə production-da real-time performance monitoring üçündür — aggregated metrics saxlayır, daha az storage istifadə edir.

**S: Health check endpoint-lərin növləri?**

C: Liveness probe — process işləyirmi? (Kubernetes restart etmək üçün), Readiness probe — servis traffic qəbul etməyə hazırdırmı? (dependency-lər sağlamdırmı?), Deep health check — bütün dependency-ləri yoxlayan ətraflı endpoint (monitoring üçün).

---

## Anti-patternlər

**1. Logging-i observability-nin tamamı hesab etmək**
Yalnız log faylları ilə sistemin sağlamlığını izləmək — log-lar nə baş verdiyini göstərir, amma niyə baş verdiyini, sistemin ümumi vəziyyətini, servislər arası əlaqəni göstərmir. Metrics (Prometheus), distributed tracing (Jaeger/Zipkin), structured logging üçlüyünü bütövlükdə tətbiq et.

**2. Trace ID-ni servislərarası ötürməmək**
Hər servis öz müstəqil trace-ini yaratmaq — bir istifadəçi sorğusunun bütün sistemdəki yolunu izləmək mümkünsüzləşir, distributed debugging kabusa çevrilir. W3C `traceparent` header-ini HTTP sorğularında ötür, `X-Request-ID`-ni queue message-larına əlavə et.

**3. SLO müəyyən etmədən alert qurmaq**
Xidmət seviyyəsi hədəfləri olmadan "hər şey 200ms-dən sürətli olsun" kimi informal beklentilərə əsaslanmaq — error budget yoxdur, komanda nəyin acceptable olduğunu bilmir, alert threshold-ları keyfi seçilir. Əvvəl SLI-ları müəyyən et, SLO qur, error budget-ə əsaslanın alert-lər yaz.

**4. Hər metriqi eyni granularlıqla toplamaq**
Bütün endpoint-lər üçün hər milisaniyə metrik toplamaq — storage xərcləri sürətlə artır, cardinality explosion Prometheus-u yavaşladır. Yüksək kardinaliteli label-lardan (user_id, order_id) metriklərdə qaç, aggregated label-lar (status_code, endpoint_group) istifadə et.

**5. Telescope-u production-da aktiv saxlamaq**
Laravel Telescope-u production mühitinə deploy etmək — hər request, query, job tam detallı saxlanır, DB-yə ağır yazma yükü yaradır, sensitive data loglanır. Telescope yalnız development üçündür; production-da Pulse istifadə et.

**6. Health check-in yalnız process-in ayaqda olmasını yoxlaması**
Kubernetes liveness probe-u `return response('ok')` ilə tətbiq etmək — servis cavab verir, amma DB bağlantısı yoxdur, Redis əlçatmazdır; servis "sağlam" görünür, amma real sorğulara xidmət edə bilmir. Readiness probe-a dependency yoxlamalarını (DB ping, cache ping, queue lag) daxil et.
