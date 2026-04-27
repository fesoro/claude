# APM Tools and Observability (Senior ⭐⭐⭐)

## İcmal

APM (Application Performance Monitoring) — tətbiqin production-dakı sağlamlığını, performansını və xəta vəziyyətini real vaxtda izləmə metodologiyası və alətlər toplusudur. Sadə log faylından fərqli olaraq APM distributed traces, dependency maps, anomaly detection, alert sistemi birlikdə təqdim edir. "Observability" isə daha geniş anlayışdır: sistemin daxili vəziyyətini xarici çıxışlar (metrics, logs, traces) vasitəsilə anlamaq qabiliyyəti.

## Niyə Vacibdir

"Sayt yavaşdır" şikayəti gəldikdə necə davranmaq lazımdır? Log-a baxmaq yetərli deyil — log-da "200 OK" yazılmış ola bilər, amma 4 saniyə çəkmiş ola bilər. APM olmadan production problemi "bəlkə server resursları tükənib" sözüylə keçişdirilir. APM ilə isə konkret: hansı endpoint, hansı user, hansı DB query, hansı external API call — hamısı görünür. Bu fərq production dəstəyi üçün kritikdir.

## Əsas Anlayışlar

- **Observability sütunları (three pillars):**
  - **Metrics** — ölçülən ədədi dəyərlər (latency, error rate, throughput)
  - **Logs** — strukturlaşdırılmış hadisə qeydləri
  - **Traces** — request-in sistem boyunca izlənməsi

- **Key metrics:**
  - **Latency** — P50, P95, P99 (ortalama yanıltır!)
  - **Throughput** — RPS (requests per second)
  - **Error rate** — 5xx / total requests
  - **Apdex score** — user satisfaction metric (frustrated/tolerating/satisfied)
  - **Saturation** — CPU, memory, queue depth

- **Distributed tracing:**
  - `trace_id` — bütün span-ları birləşdirən unique ID
  - `span` — 1 əməliyyat (DB query, HTTP call, queue job)
  - `parent_span_id` — call hierarchy
  - OpenTelemetry — vendor-neutral standard

- **APM alətləri:**
  - **Datadog APM** — enterprise, tracing + metrics + logs birlikdə
  - **New Relic** — qədim, güclü PHP agent
  - **Elastic APM** — open source stack, self-hosted
  - **Sentry** — error tracking + performance
  - **Blackfire** — PHP-focused, code profiling
  - **Laravel Telescope** — development, local debug
  - **Laravel Pulse** — production, lightweight

- **Alerting:**
  - **Threshold alert** — metric X-i keçdikdə
  - **Anomaly detection** — baseline-dan sapma
  - **Synthetic monitoring** — external endpoint-ləri test et

- **SLA / SLO / SLI:**
  - **SLI** (Service Level Indicator) — ölçülən metric (p99 latency)
  - **SLO** (Service Level Objective) — hədəf (p99 < 200ms)
  - **SLA** (Service Level Agreement) — müqavilə (99.9% uptime)
  - **Error budget** — SLO-dan qalan "iş payı"

## Praktik Baxış

**Laravel Telescope quraşdırma:**

```php
// Yalnız development
composer require laravel/telescope --dev

// AppServiceProvider
public function register(): void
{
    if ($this->app->isLocal()) {
        $this->app->register(TelescopeServiceProvider::class);
    }
}

// TelescopeServiceProvider - filtering
Telescope::filter(function (IncomingEntry $entry) {
    // Yalnız 200ms-dən yavaşları saxla
    if ($entry->type === EntryType::REQUEST) {
        return $entry->content['duration'] > 200;
    }
    // Exception-ları həmişə saxla
    if ($entry->type === EntryType::EXCEPTION) {
        return true;
    }
    return $entry->isReportableException() || $entry->isSlowQuery();
});
```

**Laravel Pulse (production-safe):**

```php
// config/pulse.php
return [
    'recorders' => [
        Recorders\Requests::class => [
            'threshold' => 1000, // 1s-dən yavaş request-ləri yaz
        ],
        Recorders\SlowQueries::class => [
            'threshold' => 500,  // 500ms-dən yavaş query
        ],
        Recorders\Queues::class,
        Recorders\Exceptions::class,
        Recorders\Cache::class => [
            'hits' => false, // yalnız miss-ləri izlə
        ],
    ],
];
```

**Datadog APM ilə distributed tracing:**

```php
// composer require datadog/dd-trace

// config/app.php - no code needed: agent auto-instruments Laravel

// Custom span
use DDTrace\GlobalTracer;

class OrderService
{
    public function processOrder(int $orderId): void
    {
        $tracer = GlobalTracer::get();
        $span = $tracer->startActiveSpan('order.process');

        $span->getActiveSpan()->setTag('order.id', $orderId);
        $span->getActiveSpan()->setTag('resource.name', 'order-service');

        try {
            // Iş məntiqi
            $this->validateOrder($orderId);
            $this->chargePayment($orderId);
            $this->sendConfirmation($orderId);
        } catch (\Throwable $e) {
            $span->getActiveSpan()->setError($e);
            throw $e;
        } finally {
            $span->close();
        }
    }
}
```

**OpenTelemetry (vendor-neutral):**

```php
// composer require open-telemetry/sdk

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;

class PaymentGateway
{
    public function charge(float $amount, string $currency): array
    {
        $tracer = Globals::tracerProvider()->getTracer('payment-gateway');

        $span = $tracer->spanBuilder('payment.charge')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $scope = $span->activate();

        try {
            $span->setAttribute('payment.amount', $amount);
            $span->setAttribute('payment.currency', $currency);

            $result = $this->httpClient->post('/charge', [
                'amount' => $amount,
                'currency' => $currency,
            ]);

            $span->setAttribute('payment.transaction_id', $result['transaction_id']);
            return $result;

        } catch (\Exception $e) {
            $span->recordException($e);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

**Custom metrics (Prometheus ilə):**

```php
// composer require promphp/prometheus_client_php

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class MetricsService
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $this->registry = new CollectorRegistry(new Redis([
            'host' => config('cache.stores.redis.host'),
        ]));
    }

    public function recordRequest(string $method, string $path, int $status, float $durationMs): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            'app',
            'http_request_duration_ms',
            'HTTP request duration',
            ['method', 'path', 'status'],
            [10, 50, 100, 200, 500, 1000, 2000]
        );

        $histogram->observe($durationMs, [$method, $path, (string) $status]);
    }

    public function incrementQueueJobFailed(string $jobClass): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'app',
            'queue_job_failed_total',
            'Failed queue jobs',
            ['job_class']
        );

        $counter->increment([$jobClass]);
    }
}
```

**Structured logging (laravel-logger):**

```php
// config/logging.php - JSON structured log
'channels' => [
    'json' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.json'),
        'level' => 'info',
        'formatter' => Monolog\Formatter\JsonFormatter::class,
    ],
],

// İstifadə (context daima qoyulmalıdır):
Log::info('order.placed', [
    'order_id' => $order->id,
    'user_id' => $order->user_id,
    'total' => $order->total,
    'duration_ms' => $duration,
    'trace_id' => request()->header('X-Trace-Id'),
]);
```

**Sentry xəta tracking:**

```php
// composer require sentry/sentry-laravel

// .env
SENTRY_LARAVEL_DSN=https://...

// AppExceptionHandler
public function register(): void
{
    $this->reportable(function (Throwable $e) {
        if (app()->bound('sentry')) {
            app('sentry')->captureException($e);
        }
    });
}

// Əl ilə context əlavə et
\Sentry\configureScope(function (\Sentry\State\Scope $scope) {
    $scope->setUser([
        'id' => auth()->id(),
        'email' => auth()->user()?->email,
    ]);
});
```

**Alerting konfiqurasiyası (Datadog):**

```yaml
# Datadog monitor YAML
name: "High P99 Latency"
type: metric alert
query: "p99(last_5m):avg:trace.laravel.request.duration{service:api} > 1000"
message: |
  P99 latency {{value}}ms keçdi.
  Service: {{service}}
  @slack-alerts
thresholds:
  critical: 1000   # 1 saniyə
  warning: 500     # 500ms
```

**Trade-offs:**
- APM agent overhead — 1-5% CPU (qəbuledilən)
- Trace sampling — 100% trace = storage problem → 10% sample
- Log volume — structured log daha çox disk, amma parse olunur
- Self-hosted (Elastic) vs Managed (Datadog) — cost vs ops
- Too many metrics → dashboard mürəkkəbliyi → alert fatigue

**Common mistakes:**
- Yalnız error log baxmaq (performance görünmür)
- P50 (ortalama) izləmək, P99 izləməmək
- Alert threshold-u çox aşağı/yuxarı qoymaq
- Production-da Telescope açmaq (DB-yə yük)
- Trace-id-siz log (request izlənmir)

## Nümunələr

### Real Ssenari: Gizli latency artışı

```
Simptom: User şikayəti yoxdur, amma weekly review-da P99 artıb.
Öncə: P99 = 180ms
İndi: P99 = 650ms

APM analiz (Datadog):
1. Latency spike: son deploy sonrası başlayıb
2. Ən yavaş endpoint: /api/feed
3. Trace breakdown: DB 520ms, PHP 80ms, network 50ms
4. DB span: 3 query → 1 slow: "SELECT * FROM posts JOIN likes..."
5. EXPLAIN: missing index on likes.user_id

Həll: CREATE INDEX CONCURRENTLY on likes(user_id)
Nəticə: P99 650ms → 190ms (deploy olmadan, online index)
```

### Kod Nümunəsi

```php
<?php

// Middleware: automatic tracing + metrics
class ObservabilityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header('X-Trace-Id', Str::uuid()->toString());
        $start = hrtime(true);

        // Request-ə trace-id attach et
        $request->headers->set('X-Trace-Id', $traceId);

        // Log context-ə əlavə et (bütün log-larda görünsün)
        Log::shareContext(['trace_id' => $traceId]);

        $response = $next($request);

        $duration = (hrtime(true) - $start) / 1e6;
        $status = $response->getStatusCode();

        // Metrics
        app(MetricsService::class)->recordRequest(
            $request->method(),
            $request->route()?->getName() ?? $request->path(),
            $status,
            $duration
        );

        // Response header-ə trace-id
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
```

## Praktik Tapşırıqlar

1. **Telescope qur:** Local Laravel proyektdə Telescope aktivləşdir, slow query threshold 100ms-ə qoy, bir N+1 endpoint-i çağır, Telescope-da nə görürsən qeyd et.

2. **Pulse produksiya test:** Laravel Pulse dashboard-u qur, 5 dəqiqə load test çalışdır, en çox yüklü endpoint-i tap.

3. **Custom metric:** Prometheus counter ilə failed login attempt-ləri ölçən bir metric yaz, `/metrics` endpoint-ini açıq qoy.

4. **Structured log:** Monolog JsonFormatter ilə bütün request-ları log et, `trace_id` əlavə et, log-u `jq` ilə filter et.

5. **Alert yaz:** Datadog/Grafana-da "Error rate > 1% in 5 min" alert konfiqurasiya et, test üçün error endpoint yarat.

## Əlaqəli Mövzular

- `01-performance-profiling.md` — APM vs profiler fərqi
- `12-load-testing.md` — Load test metrikalarını APM-də izlə
- `13-flame-graphs.md` — CPU profiling APM-də
- `06-memory-leak-detection.md` — Memory metrikları APM-də
- `09-async-batch-processing.md` — Queue metrikalarını APM ilə izlə
