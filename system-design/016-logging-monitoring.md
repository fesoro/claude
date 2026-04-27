# Logging & Monitoring (Middle)

## İcmal

Logging və monitoring sistemləri tətbiqin sağlamlığını, performansını və davranışını
izləmək üçün istifadə olunur. Logging - hadisələri qeydə almaq, Monitoring - metriklər
toplayıb vizuallaşdırmaq, Alerting - problemlər haqqında xəbərdarlıq etmək.

Sadə dillə: logging = gündəlik yazmaq, monitoring = sağlamlıq yoxlaması, alerting = həyəcan siqnalı.

```
Application → Logs → Centralized Storage → Search & Analysis
           → Metrics → Time-Series DB → Dashboards
           → Traces → Trace Storage → Request Flow Visualization
           → Alerts → Notification Channels
```


## Niyə Vacibdir

Production-da problem aşkar etmək üçün observability olmadan 'blind fly' etmiş olursunuz. Centralized logging, metrics, distributed tracing — üç pillar olmadan MTTR (mean time to repair) yüksək olur. ELK stack, Prometheus, Grafana — real şirkətlərin standart observability tool-larıdır.

## Əsas Anlayışlar

### Three Pillars of Observability

```
1. LOGS (Events)          2. METRICS (Numbers)       3. TRACES (Flows)
   What happened?            How is it performing?      Where did it go?

   [2024-01-15 10:23:45]    CPU: 75%                  Service A → Service B
   ERROR: Payment failed    Memory: 4.2GB/8GB                ↓
   order_id: 123            Requests/sec: 1500        Service C → Database
   user_id: 456             Error rate: 0.5%                 ↓
   amount: 99.99            Latency p99: 250ms        Response: 230ms total
```

### Log Levels

```
EMERGENCY → System is unusable
ALERT     → Action must be taken immediately
CRITICAL  → Critical conditions (database down)
ERROR     → Error conditions (payment failed)
WARNING   → Warning conditions (disk 80% full)
NOTICE    → Normal but significant condition
INFO      → Informational messages (user logged in)
DEBUG     → Debug-level messages (SQL queries)
```

### Structured Logging

```json
// Bad: Plain text
"[2024-01-15 10:23:45] ERROR: Payment failed for order 123"

// Good: Structured JSON
{
  "timestamp": "2024-01-15T10:23:45.123Z",
  "level": "error",
  "message": "Payment failed",
  "context": {
    "order_id": 123,
    "user_id": 456,
    "amount": 99.99,
    "payment_method": "credit_card",
    "error_code": "INSUFFICIENT_FUNDS"
  },
  "service": "payment-service",
  "trace_id": "abc-123-def-456",
  "host": "web-server-03"
}
```

### ELK Stack

```
┌───────────┐     ┌──────────────┐     ┌───────────────┐
│ App Logs  │────▶│ Logstash     │────▶│ Elasticsearch │
│           │     │ (Process &   │     │ (Store &      │
│ Filebeat  │     │  Transform)  │     │  Index)       │
│ (Ship)    │     └──────────────┘     └───────┬───────┘
└───────────┘                                  │
                                        ┌──────┴───────┐
                                        │   Kibana     │
                                        │ (Visualize & │
                                        │  Search)     │
                                        └──────────────┘
```

### Metrics Types

```
Counter:   Total requests = 150,000 (only goes up)
Gauge:     Current memory = 4.2GB (goes up and down)
Histogram: Request latency distribution
           p50 = 50ms, p90 = 120ms, p95 = 200ms, p99 = 500ms
Summary:   Similar to histogram, calculated client-side
```

### Prometheus + Grafana

```
┌──────────────────────────────────────────────┐
│                Applications                   │
│  ┌────────┐  ┌────────┐  ┌────────┐        │
│  │ App 1  │  │ App 2  │  │ App 3  │        │
│  │/metrics│  │/metrics│  │/metrics│        │
│  └───┬────┘  └───┬────┘  └───┬────┘        │
└──────┼───────────┼───────────┼──────────────┘
       │           │           │
       └───────────┼───────────┘
                   │ scrape (pull)
            ┌──────┴──────┐
            │ Prometheus  │
            │ (TSDB)      │──────┐
            └──────┬──────┘      │
                   │          ┌──┴──────────┐
            ┌──────┴──────┐   │ AlertManager│
            │  Grafana    │   │             │
            │ (Dashboard) │   │ → Slack     │
            └─────────────┘   │ → PagerDuty │
                              │ → Email     │
                              └─────────────┘
```

### Distributed Tracing

```
Request: GET /api/orders/123

Trace ID: abc-123
├─ Span 1: API Gateway (5ms)
│  ├─ Span 2: Auth Service - validate token (15ms)
│  └─ Span 3: Order Service - get order (180ms)
│     ├─ Span 4: Database query (25ms)
│     ├─ Span 5: User Service - get user info (45ms)
│     │  └─ Span 6: User DB query (10ms)
│     └─ Span 7: Cache lookup (3ms)
│
Total: 200ms

Tools: Jaeger, Zipkin, AWS X-Ray, Datadog APM
```

## Arxitektura

### Complete Observability Stack

```
┌────────────────────────────────────────────────────┐
│                  Applications                       │
│                                                     │
│  Logs ──────┐   Metrics ────┐   Traces ─────┐     │
└─────────────┼───────────────┼───────────────┼──────┘
              │               │               │
       ┌──────┴──────┐ ┌─────┴──────┐ ┌──────┴──────┐
       │  Fluentd /  │ │ Prometheus │ │   Jaeger    │
       │  Logstash   │ │            │ │             │
       └──────┬──────┘ └─────┬──────┘ └──────┬──────┘
              │               │               │
       ┌──────┴──────┐ ┌─────┴──────┐ ┌──────┴──────┐
       │Elasticsearch│ │ Prometheus │ │   Jaeger    │
       │             │ │   TSDB    │ │   Storage   │
       └──────┬──────┘ └─────┬──────┘ └──────┬──────┘
              │               │               │
              └───────────────┼───────────────┘
                              │
                       ┌──────┴──────┐
                       │   Grafana   │
                       │ (Unified    │
                       │  Dashboard) │
                       └──────┬──────┘
                              │
                       ┌──────┴──────┐
                       │ AlertManager│
                       │ → Slack     │
                       │ → PagerDuty │
                       └─────────────┘
```

## Nümunələr

### Laravel Logging Configuration

```php
// config/logging.php
return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'elasticsearch', 'slack'],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'elasticsearch' => [
            'driver' => 'custom',
            'via' => App\Logging\ElasticsearchLogger::class,
            'level' => 'info',
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'level' => 'error',
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
        ],
    ],
];
```

### Structured Logging with Context

```php
// app/Logging/ElasticsearchLogger.php
class ElasticsearchLogger
{
    public function __invoke(array $config): Logger
    {
        $handler = new ElasticsearchHandler(
            client: app(ElasticsearchClient::class),
            options: [
                'index' => 'app-logs-' . date('Y.m.d'),
            ]
        );

        $handler->setFormatter(new ElasticsearchFormatter('app-logs', '_doc'));

        return new Logger('elasticsearch', [$handler]);
    }
}

// Structured logging in application code
class OrderService
{
    public function createOrder(array $data): Order
    {
        $startTime = microtime(true);

        Log::info('Order creation started', [
            'user_id' => auth()->id(),
            'items_count' => count($data['items']),
            'total' => $data['total'],
        ]);

        try {
            $order = DB::transaction(function () use ($data) {
                return Order::create($data);
            });

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'duration_ms' => round($duration, 2),
                'user_id' => auth()->id(),
            ]);

            return $order;
        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => Arr::except($data, ['payment_details']),
            ]);

            throw $e;
        }
    }
}
```

### Request Logging Middleware

```php
class RequestLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = (string) Str::uuid();

        // Add request ID to all logs in this request
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000;

        Log::info('HTTP Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
```

### Laravel Telescope

```php
// composer require laravel/telescope
// php artisan telescope:install

// app/Providers/TelescopeServiceProvider.php
class TelescopeServiceProvider extends \Laravel\Telescope\TelescopeServiceProvider
{
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        // Only record in specific environments
        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local')) {
                return true;
            }

            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }
}
```

### Custom Metrics with Prometheus

```php
// app/Services/MetricsService.php
use Prometheus\CollectorRegistry;

class MetricsService
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $this->registry = app(CollectorRegistry::class);
    }

    public function incrementRequestCount(string $method, string $path, int $status): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'app', 'http_requests_total', 'Total HTTP requests',
            ['method', 'path', 'status']
        );
        $counter->inc([$method, $path, (string) $status]);
    }

    public function observeRequestDuration(string $path, float $duration): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            'app', 'http_request_duration_seconds', 'Request duration',
            ['path'],
            [0.01, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
        );
        $histogram->observe($duration, [$path]);
    }

    public function setQueueSize(string $queue, int $size): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            'app', 'queue_size', 'Current queue size',
            ['queue']
        );
        $gauge->set($size, [$queue]);
    }
}

// Metrics endpoint for Prometheus scraping
Route::get('/metrics', function (MetricsService $metrics) {
    $renderer = new RenderTextFormat();
    $registry = app(CollectorRegistry::class);
    return response($renderer->render($registry->getMetricFamilySamples()))
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
});
```

### Health Check Endpoint

```php
class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'ok', 'latency_ms' => $this->measureLatency(fn () => DB::select('SELECT 1'))];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            Storage::disk('s3')->exists('health-check');
            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        $size = Queue::size('default');
        return [
            'status' => $size < 10000 ? 'ok' : 'warning',
            'size' => $size,
        ];
    }

    private function measureLatency(callable $fn): float
    {
        $start = microtime(true);
        $fn();
        return round((microtime(true) - $start) * 1000, 2);
    }
}
```

## Real-World Nümunələr

1. **Netflix** - Atlas (metrics), Edgar (distributed tracing), custom ELK
2. **Uber** - M3 (metrics), Jaeger (tracing, Uber-in open-source tool-u)
3. **Google** - Dapper (distributed tracing paper), Monarch (metrics)
4. **Datadog** - SaaS observability platform (logs + metrics + traces)
5. **Grafana Labs** - Grafana + Loki (logs) + Tempo (traces) + Mimir (metrics)

## Praktik Tapşırıqlar

**S1: Centralized logging niyə lazımdır?**
C: Microservices-də hər service öz log-unu yazır. Problem araşdırmaq üçün bütün
log-ları bir yerdən axtarmaq lazımdır. Correlation ID ilə request flow-nu izləmək,
aggregation, alerting mümkün olur.

**S2: Metrics vs Logs fərqi nədir?**
C: Logs - discrete events, structured text, debug üçün əla, storage intensive.
Metrics - numeric values over time, aggregation-a uyğun, dashboards, alerting.
Logs "nə baş verdi" cavab verir, metrics "necə gedir" cavab verir.

**S3: Distributed tracing nədir?**
C: Bir request-in bütün service-lər arasında yolunu izləmək. Trace ID hər
service-ə ötürülür, hər service span yaradır. Bottleneck-ləri, slow service-ləri,
error source-unu tapmaq asanlaşır.

**S4: p99 latency nədir və niyə vacibdir?**
C: 99% request-in tamamlanma müddəti. p50=50ms, p99=500ms deməkdir ki,
100 request-dən 99-u 500ms-dən az vaxtda tamamlanır, 1-i daha çox çəkir.
Average yanıltıcı ola bilər, percentile daha dəqiqdir.

**S5: Alert fatigue nədir və necə qarşısı alınır?**
C: Həddən artıq çox və ya irrelevant alert-lər göndərilməsi - komanda alert-lərə
əhəmiyyət verməyi dayandırır. Həll: severity levels, actionable alerts only,
grouping, escalation policies, on-call rotation.

**S6: Log retention strategiyası necə olmalıdır?**
C: Hot storage (Elasticsearch) - son 7-30 gün, warm storage - 3 ay, cold/archive
(S3 Glacier) - 1+ il. Compliance tələblərinə görə dəyişir. Log rotation və
lifecycle policy ilə avtomatlaşdırın.

## Praktik Baxış

1. **Structured Logging** - JSON format istifadə edin
2. **Correlation ID** - Hər request-ə unique ID verin
3. **Log Levels** - Düzgün level istifadə edin (ERROR spam yox)
4. **Sensitive Data** - Passwords, tokens log-a yazmayın
5. **Dashboards** - Key metrics üçün vizual dashboard yaradın
6. **Alerting** - Actionable alert-lər, severity levels
7. **Retention Policy** - Log-ları nə qədər saxlayacağınızı planlayın
8. **Health Checks** - /health endpoint hər service-də olsun
9. **SLI/SLO** - Service Level Indicators və Objectives təyin edin
10. **Runbooks** - Hər alert üçün həll yolu sənədləşdirin


## Əlaqəli Mövzular

- [SLA/SLO/SLI](44-sla-slo-sli.md) — observability ilə SLA-nı ölçmək
- [Metrics System Design](53-metrics-monitoring-design.md) — custom monitoring arxitekturası
- [Distributed Tracing](91-distributed-tracing-deep-dive.md) — mikroservis sorğu izləmə
- [Chaos Engineering](56-chaos-engineering.md) — monitoring ilə xaos sınaqları
- [Disaster Recovery](30-disaster-recovery.md) — incident zamanı observability
