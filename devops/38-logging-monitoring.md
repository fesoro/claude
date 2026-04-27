# Logging və Monitoring (Middle)

## Mündəricat
1. Logging nədir, niyə kritikdir
2. Log Levels RFC 5424
3. Structured Logging vs Plain Text
4. Correlation ID / Request ID
5. Laravel Logging (Monolog əsaslı)
6. Custom Channel (Elasticsearch)
7. Log Context və Sensitive Data Masking
8. ELK Stack
9. Centralized Logging: Datadog, Logtail
10. Monitoring vs Logging
11. RED Method, USE Method
12. Health Check Endpoint
13. Alerting
14. Laravel Telescope
15. Laravel Pulse
16. Laravel Horizon Monitoring
17. İntervyu Sualları

---

## 1. Logging Nədir, Production-da Niyə Kritikdir

Logging — tətbiqin icra zamanı baş verən hadisələri qeyd etməsi prosesidir. Bu, debug-dan monitorinqə qədər geniş spektrdə istifadə edilir.

### Niyə production-da vacibdir

Production mühitdə developer-in local debug imkanı yoxdur. Xəta baş verdikdə yeganə məlumat mənbəyi log-lardır. Bundan əlavə:

- **Incident postmortem**: Nə baş verdi, nə vaxt, hansı sırada
- **Performance bottleneck**: Hansı sorğular yavaşdır
- **Security audit**: Kim, nə vaxt, nəyə daxil olub
- **Business analytics**: Hansı feature-lar istifadə edilir
- **SLA monitoring**: Servisin availability-si neçə faizdir
- **Capacity planning**: Resurs istehlakı trendi

*Bu ifadə logging-in biznes dəyərini ümumiləşdirir:*
```
Yaxşı logging = Sürətli problem həlli = Daha az downtime = Biznes itkisinin azalması
```

### Production logging prinsipləri

```
1. Structured olsun (JSON) — machine-readable
2. Context zəngin olsun — hər log özü ilə kifayət qədər məlumat daşısın
3. Sensitive data olmasın — GDPR, PCI DSS tələbləri
4. Performansa təsir etməsin — async yazılsın
5. Centralized olsun — hamısı bir yerdə toplanılsın
6. Alert olsun — kritik xətalarda dərhal xəbər verilsin
```

---

## 2. Log Levels RFC 5424

RFC 5424 (Syslog Protocol) 8 log səviyyəsi müəyyən edir. Hər biri müəyyən situasiya üçündür:

```
Severity | Level | Nümunə situasiya
─────────┼───────┼──────────────────────────────────────────────────────────
0        | EMERG | Sistem tamamilə işləmir, bütün servisler çöküb
1        | ALERT | Dərhal insan müdaxiləsi lazımdır (DB cluster down)
2        | CRIT  | Kritik komponent uğursuz (primary DB əlçatmazdır)
3        | ERROR | Xəta baş verdi, əməliyyat tamamlanmadı
4        | WARN  | Potensial problem var, hələ critical deyil
5        | NOTICE| Normal, amma əhəmiyyətli hadisə (config dəyişikliyi)
6        | INFO  | Normal informasiya (istifadəçi login etdi)
7        | DEBUG | Detallı debug məlumatı (yalnız dev-də)
```

### Laravel-də hər level nə vaxt

*Laravel-də hər level nə vaxt üçün kod nümunəsi:*
```php
// Bu kod Laravel-də bütün log səviyyələrinin istifadəsini göstərir
<?php

use Illuminate\Support\Facades\Log;

// EMERGENCY — tətbiq tamamilə istifadə edilə bilməz
// Praktikada nadirdir, adətən monitoring tool-lar yaradır
Log::emergency("Database cluster tamamilə çöküb. Bütün əməliyyatlar dayanıb.", [
    'cluster'   => 'primary',
    'timestamp' => now()->toIso8601String(),
]);

// ALERT — dərhal diqqət tələb edir, kimsə oyandırılmalıdır
Log::alert("Disk dolulluğu 95% keçdi", [
    'server'     => gethostname(),
    'disk_usage' => disk_free_space('/'),
    'threshold'  => 95,
]);

// CRITICAL — kritik komponent uğursuz oldu, amma sistem qismən işləyir
Log::critical("Payment gateway əlçatmazdır", [
    'gateway'         => 'stripe',
    'last_successful' => Cache::get('stripe:last_success'),
    'failed_count'    => Cache::get('stripe:failed_count'),
]);

// ERROR — konkret əməliyyat uğursuz oldu
Log::error("Order yaradıla bilmədi", [
    'user_id'   => $userId,
    'cart_data' => $cartData,
    'exception' => $e->getMessage(),
    'trace'     => $e->getTraceAsString(),
]);

// WARNING — problem olmaya bilər, amma izlənilməlidir
Log::warning("API rate limit-ə yaxınlaşılır", [
    'endpoint'        => '/api/users',
    'current_rate'    => $currentRate,
    'limit'           => $rateLimit,
    'remaining'       => $rateLimit - $currentRate,
]);

// NOTICE — əhəmiyyətli sistem hadisəsi, normal, amma qeyd edilməlidir
Log::notice("İstifadəçi şifrəsini dəyişdi", [
    'user_id'  => $user->id,
    'ip'       => request()->ip(),
    'platform' => request()->userAgent(),
]);

// INFO — normal iş axışı haqqında məlumat
Log::info("Sifariş uğurla tamamlandı", [
    'order_id' => $order->id,
    'user_id'  => $order->user_id,
    'total'    => $order->total,
    'duration' => $processingTime,
]);

// DEBUG — development zamanı detallı izləmə (production-da disable olmalıdır)
Log::debug("SQL sorğusu icra edildi", [
    'sql'      => $query,
    'bindings' => $bindings,
    'time'     => $executionTime,
]);
```

### Production-da log level konfiqurasiyası

*Production-da log level konfiqurasiyası üçün kod nümunəsi:*
```php
// .env
// LOG_LEVEL=warning  — production-da yalnız warning+ yazılır
// LOG_LEVEL=debug    — yalnız development-də

// config/logging.php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/laravel.log'),
        'level'  => env('LOG_LEVEL', 'debug'),
        'days'   => 14,
    ],
],
```

---

## 3. Structured Logging vs Plain Text

### Plain text logging (pis praktika)

```
[2025-01-15 14:23:01] production.ERROR: User 42 could not complete order 789 because payment failed
```

Problemlər:
- Regex ilə parse etmək çətindir
- Sahələri ayrı-ayrı filter etmək olmur
- Kibana/Datadog-da field-based search işləmir

### Structured (JSON) logging (yaxşı praktika)

*Structured (JSON) logging (yaxşı praktika) üçün kod nümunəsi:*
```json
{
  "timestamp": "2025-01-15T14:23:01.456Z",
  "level": "error",
  "message": "Order tamamlana bilmədi",
  "context": {
    "user_id": 42,
    "order_id": 789,
    "reason": "payment_failed",
    "payment_gateway": "stripe",
    "error_code": "card_declined",
    "request_id": "req_abc123",
    "duration_ms": 1250
  },
  "channel": "orders",
  "env": "production",
  "host": "web-01"
}
```

Üstünlüklər:
- Kibana-da `context.user_id:42` ilə dəqiq axtarış
- Datadog-da metric-lərə çevirmək mümkün
- Alerting üçün sahə dəyərlərinə əsasən şərt yazmaq olar
- Log aggregation toolları asanlıqla parse edir

### Laravel-də JSON logging

*Laravel-də JSON logging üçün kod nümunəsi:*
```php
// Bu kod Laravel-də JSON formatında structured logging konfiqurasiyasını göstərir
<?php
// config/logging.php
'channels' => [
    'json' => [
        'driver'    => 'single',
        'path'      => storage_path('logs/laravel-json.log'),
        'level'     => 'debug',
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],

    'stack' => [
        'driver'   => 'stack',
        'channels' => ['json', 'slack'],
    ],
],

// Custom JSON formatter ilə əlavə sahələr
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class EnrichedJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $record = $record->with(extra: array_merge($record->extra, [
            'app_version' => config('app.version'),
            'environment' => config('app.env'),
            'host'        => gethostname(),
            'pid'         => getmypid(),
        ]));

        return parent::format($record);
    }
}
```

---

## 4. Correlation ID / Request ID

Mikroservis arxitekturasında bir istifadəçi sorğusu bir neçə servisə keçə bilər. Correlation ID — bu sorğuları bağlamaq üçün istifadə edilir.

```
Browser → API Gateway → User Service → Order Service → Payment Service
           (req_abc)     (req_abc)       (req_abc)        (req_abc)
```

Hər servis eyni `correlation_id` ilə log yazır. Beləliklə, Kibana-da `correlation_id:req_abc` axtararkən bütün servislərin log-larını görürük.

### Laravel Middleware implementasiyası

*Laravel Middleware implementasiyası üçün kod nümunəsi:*
```php
// Bu kod hər sorğuya unikal Correlation ID əlavə edən middleware-i göstərir
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\{Request, Response};
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Mövcud correlation ID-ni götür (ya da yenisini yarat)
        $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        // Request ID bu sorğuya özəldir
        $requestId = (string) Str::uuid();

        // Yeni context əlavə et — bu context sonrakı bütün Log:: çağrılarına qoşulacaq
        Log::withContext([
            'correlation_id' => $correlationId,
            'request_id'     => $requestId,
            'method'         => $request->method(),
            'url'            => $request->fullUrl(),
            'ip'             => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        // Request-ə context yaz (controller-lərdən oxumaq üçün)
        $request->attributes->set('correlation_id', $correlationId);
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        // Response header-lərinə əlavə et — client-in izləməsi üçün
        return $response
            ->header('X-Correlation-ID', $correlationId)
            ->header('X-Request-ID', $requestId);
    }
}

// Kernel-ə əlavə etmək
// app/Http/Kernel.php
protected $middleware = [
    \App\Http\Middleware\CorrelationIdMiddleware::class,
    // ...
];
```

### Queue job-larında Correlation ID ötürmək

*Queue job-larında Correlation ID ötürmək üçün kod nümunəsi:*
```php
// Bu kod Queue job-larında Correlation ID-nin ötürülməsini göstərir
<?php

class ProcessOrderJob implements ShouldQueue
{
    public function __construct(
        private readonly Order $order,
        private readonly string $correlationId, // ötürülür
    ) {}

    public function handle(): void
    {
        // Job-da da eyni correlation_id ilə log yaz
        Log::withContext([
            'correlation_id' => $this->correlationId,
            'job'            => static::class,
            'order_id'       => $this->order->id,
        ]);

        Log::info("Order processing başladı");
        // işlər...
    }
}

// Dispatcher-da
ProcessOrderJob::dispatch(
    order: $order,
    correlationId: request()->attributes->get('correlation_id', (string) Str::uuid()),
);
```

---

## 5. Laravel Logging (Monolog əsaslı)

Laravel altında Monolog kitabxanası işləyir. Laravel channels-ları Monolog handler-larının wrapper-ıdır.

### Built-in channels

*Built-in channels üçün kod nümunəsi:*
```php
// Bu kod Laravel-in built-in logging channel-larının konfiqurasiyasını göstərir
<?php
// config/logging.php

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [

        // stack — bir neçə channel-ı birləşdirir
        'stack' => [
            'driver'            => 'stack',
            'channels'          => ['daily', 'slack'],
            'ignore_exceptions' => false,
        ],

        // single — bir fayla yazır
        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => 'debug',
        ],

        // daily — hər gün yeni fayl (rotation)
        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => 14, // 14 gün saxla
        ],

        // slack — Slack webhook-a göndər
        'slack' => [
            'driver'   => 'slack',
            'url'      => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Logger',
            'emoji'    => ':boom:',
            'level'    => 'critical', // yalnız critical+
        ],

        // syslog — sistem syslog-una yaz (Linux)
        'syslog' => [
            'driver'   => 'syslog',
            'level'    => 'debug',
            'facility' => LOG_USER,
        ],

        // papertrail — cloud log service
        'papertrail' => [
            'driver'       => 'monolog',
            'level'        => 'debug',
            'handler'      => \Monolog\Handler\SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        // null — log-ları at (testing üçün)
        'null' => [
            'driver'  => 'monolog',
            'handler' => \Monolog\Handler\NullHandler::class,
        ],

        // stderr — container mühitləri üçün (Docker/K8s logs)
        'stderr' => [
            'driver'    => 'monolog',
            'level'     => 'debug',
            'handler'   => \Monolog\Handler\StreamHandler::class,
            'formatter' => \Monolog\Formatter\JsonFormatter::class,
            'with'      => ['stream' => 'php://stderr'],
        ],
    ],
];
```

---

## 6. Custom Channel: Elasticsearch

*6. Custom Channel: Elasticsearch üçün kod nümunəsi:*
```php
// Bu kod Elasticsearch-ə log yazan custom Monolog handler-i göstərir
<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Elastic\Elasticsearch\Client;

class ElasticsearchHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly Client $client,
        private readonly string $indexPrefix = 'laravel-logs',
        Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $indexName = $this->indexPrefix . '-' . date('Y.m.d');

        $document = [
            '@timestamp'   => $record->datetime->format('c'),
            'level'        => $record->level->name,
            'level_number' => $record->level->value,
            'message'      => $record->message,
            'channel'      => $record->channel,
            'context'      => $record->context,
            'extra'        => $record->extra,
        ];

        try {
            $this->client->index([
                'index' => $indexName,
                'body'  => $document,
            ]);
        } catch (\Throwable $e) {
            // Elasticsearch əlçatmazdırsa, log system-i crash etməsin
            // Fallback: stderr-ə yaz
            fwrite(STDERR, "Elasticsearch log uğursuz: " . $e->getMessage() . PHP_EOL);
        }
    }
}

// Channel factory
namespace App\Logging;

class ElasticsearchChannelFactory
{
    public function __invoke(array $config): \Monolog\Logger
    {
        $client = \Elastic\Elasticsearch\ClientBuilder::create()
            ->setHosts($config['hosts'])
            ->build();

        $handler = new ElasticsearchHandler(
            client: $client,
            indexPrefix: $config['index'] ?? 'laravel-logs',
        );

        return new \Monolog\Logger('elasticsearch', [$handler]);
    }
}

// config/logging.php-yə əlavə etmək
'elasticsearch' => [
    'driver'  => 'custom',
    'via'     => \App\Logging\ElasticsearchChannelFactory::class,
    'hosts'   => [env('ELASTICSEARCH_HOST', 'localhost:9200')],
    'index'   => env('ELASTICSEARCH_LOG_INDEX', 'laravel-logs'),
    'level'   => env('LOG_LEVEL', 'info'),
],
```

---

## 7. Log Context və Sensitive Data Masking

### Log::withContext() və Log::shareContext()

*Log::withContext() və Log::shareContext() üçün kod nümunəsi:*
```php
// Bu kod Log::withContext() ilə bütün log-lara avtomatik context əlavə etməyi göstərir
<?php

// withContext — yalnız cari request üçün context
// (Middleware-də çağırılır)
Log::withContext([
    'request_id' => $requestId,
    'user_id'    => auth()->id(),
    'tenant_id'  => tenant()->id,
]);

// Bu context ilə bütün log-lar əlavə field alır:
Log::info("Sifariş yaradıldı"); 
// Output: {"message":"Sifariş yaradıldı","context":{},"extra":{"request_id":"...","user_id":42}}

// shareContext — tətbiqin bütün request-lərində paylaşılan context
// (ServiceProvider-da çağırılır)
Log::shareContext([
    'app_version' => config('app.version'),
    'environment' => config('app.env'),
    'server'      => gethostname(),
]);
```

### Sensitive Data Masking

*Sensitive Data Masking üçün kod nümunəsi:*
```php
// Bu kod log-larda həssas məlumatları (parol, kart nömrəsi) maskalamağı göstərir
<?php

namespace App\Logging;

use Monolog\Processor\ProcessorInterface;
use Monolog\LogRecord;

class SensitiveDataMaskingProcessor implements ProcessorInterface
{
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'credit_card',
        'card_number',
        'cvv',
        'pin',
        'secret',
        'token',
        'api_key',
        'authorization',
    ];

    private array $partialMaskKeys = [
        'email',        // user@example.com → us***@example.com
        'phone',        // +994501234567 → +994*****567
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->maskArray($record->context);
        $extra   = $this->maskArray($record->extra);

        return $record->with(context: $context, extra: $extra);
    }

    private function maskArray(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, $this->sensitiveKeys)) {
                $masked[$key] = '***MASKED***';
            } elseif (in_array($lowerKey, $this->partialMaskKeys)) {
                $masked[$key] = $this->partialMask($value);
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskArray($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    private function partialMask(mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 4) {
            return '***';
        }

        $visible = 2;
        return substr($value, 0, $visible) . str_repeat('*', strlen($value) - $visible - 2) . substr($value, -2);
    }
}

// Monolog processor-a əlavə etmək (AppServiceProvider)
public function boot(): void
{
    $this->app->make('log')->pushProcessor(new SensitiveDataMaskingProcessor());
}
```

---

## 8. ELK Stack

ELK = **E**lasticsearch + **L**ogstash + **K**ibana. Production-da ən populyar centralized logging həllidir.

```
Tətbiq Serverləri
    ↓ (log faylları / beats agent)
Filebeat / Fluentd       ← log-ları oxuyub Logstash-a göndərir
    ↓
Logstash                 ← parse, transform, enrich edir
    ↓
Elasticsearch            ← index edib saxlayır (distributed search engine)
    ↓
Kibana                   ← visualization, search, alerting UI
```

### Logstash pipeline nümunəsi

*Logstash pipeline nümunəsi üçün kod nümunəsi:*
```ruby
# /etc/logstash/conf.d/laravel.conf

input {
  beats {
    port => 5044
  }
}

filter {
  # Laravel JSON log-larını parse et
  if [fields][app] == "laravel" {
    json {
      source  => "message"
      target  => "laravel"
      remove_field => ["message"]
    }

    # Timestamp-i düzgün format-a çevir
    date {
      match    => ["[laravel][timestamp]", "ISO8601"]
      target   => "@timestamp"
      timezone => "UTC"
    }

    # Geoip — IP-dən location məlumatı
    if [laravel][context][ip] {
      geoip {
        source => "[laravel][context][ip]"
        target => "[laravel][geo]"
      }
    }

    # User agent parse
    useragent {
      source => "[laravel][context][user_agent]"
      target => "[laravel][ua]"
    }
  }
}

output {
  elasticsearch {
    hosts         => ["elasticsearch:9200"]
    index         => "laravel-logs-%{+YYYY.MM.dd}"
    document_type => "_doc"
  }
}
```

---

## 9. Centralized Logging: Datadog, Logtail

### Datadog Laravel integration

*Datadog Laravel integration üçün kod nümunəsi:*
```php
// composer require datadog/dd-trace

// config/logging.php
'datadog' => [
    'driver'       => 'monolog',
    'handler'      => \Monolog\Handler\SocketHandler::class,
    'handler_with' => [
        'connectionString' => 'udp://localhost:10518',
    ],
    'formatter'    => \Monolog\Formatter\JsonFormatter::class,
    'level'        => 'debug',
],

// DD_SERVICE, DD_ENV, DD_VERSION — .env-də set edilir
// Datadog agent localhost-da çalışır və log-ları qəbul edir
```

### Logtail (Better Stack)

*Logtail (Better Stack) üçün kod nümunəsi:*
```php
// composer require logtail/monolog-logtail

namespace App\Logging;

use Logtail\Monolog\LogtailHandler;

class LogtailChannelFactory
{
    public function __invoke(array $config): \Monolog\Logger
    {
        $handler = new LogtailHandler(
            sourceToken: $config['source_token'],
            level: $config['level'] ?? 'debug',
        );

        return new \Monolog\Logger('logtail', [$handler]);
    }
}

// config/logging.php
'logtail' => [
    'driver'       => 'custom',
    'via'          => \App\Logging\LogtailChannelFactory::class,
    'source_token' => env('LOGTAIL_SOURCE_TOKEN'),
    'level'        => env('LOG_LEVEL', 'info'),
],
```

---

## 10. Monitoring vs Logging Fərqi

```
                LOGGING                         MONITORING
────────────────────────────────────────────────────────────────
Nədir?      Hadisələri qeyd edir           Metrikleri real-time ölçür
Sual?       "Nə baş verdi?"                "Sistem sağlam işləyirmi?"
Format      Text/JSON log entries           Ədədi metriklər (sayğac, histogram)
Saxlama     Uzun müddət (90 gün+)          Qısa müddət (15-30 gün)
İstifadə    Debug, audit, postmortem        Alerting, dashboard, capacity
Nümunə      "User 42 login etdi 14:23-da"  "Login/saniyə = 150 RPS"
Alətlər     ELK, Logtail, Papertrail        Prometheus, Datadog, Grafana
```

Hər ikisi bir-birini tamamlayır. Monitoring "problem var" deyir, logging "problem nədir" cavabını verir.

---

## 11. RED Method və USE Method

### RED Method (Microservice-lər üçün)

Hər servis üçün 3 əsas metrik:

```
R — Rate     : Saniyədə neçə sorğu işlənir (RPS/TPS)
E — Errors   : Xəta faizi (Error Rate %)
D — Duration : Sorğuların işlənmə müddəti (Latency, p50/p95/p99)
```

*D — Duration : Sorğuların işlənmə müddəti (Latency, p50/p95/p99) üçün kod nümunəsi:*
```php
// Bu kod RED metodunu (Rate, Errors, Duration) Laravel middleware-də tətbiq etməyi göstərir
<?php

// Laravel Middleware ilə RED metrikləri toplamaq
class RedMetricsMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $startTime = microtime(true);

        try {
            $response = $next($request);

            $duration = (microtime(true) - $startTime) * 1000; // ms

            $this->recordMetrics(
                route: $request->route()?->getName() ?? 'unknown',
                method: $request->method(),
                statusCode: $response->getStatusCode(),
                duration: $duration,
                isError: $response->getStatusCode() >= 400,
            );

            return $response;

        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->recordMetrics(
                route: $request->route()?->getName() ?? 'unknown',
                method: $request->method(),
                statusCode: 500,
                duration: $duration,
                isError: true,
            );

            throw $e;
        }
    }

    private function recordMetrics(
        string $route,
        string $method,
        int $statusCode,
        float $duration,
        bool $isError,
    ): void {
        $labels = [
            'route'  => $route,
            'method' => $method,
            'status' => $statusCode,
        ];

        // Prometheus counter (Rate)
        $this->prometheus->counter('http_requests_total', $labels)->increment();

        // Error counter
        if ($isError) {
            $this->prometheus->counter('http_errors_total', $labels)->increment();
        }

        // Histogram (Duration — p50, p95, p99)
        $this->prometheus->histogram(
            'http_request_duration_milliseconds',
            $labels,
            buckets: [10, 50, 100, 250, 500, 1000, 2500, 5000]
        )->observe($duration);
    }
}
```

### USE Method (Infrastructure üçün)

```
U — Utilization : Resursun neçə faizi istifadə edilir (CPU, RAM, Disk)
S — Saturation  : Resurs dolu olduqda queue/backlog var mı
E — Errors      : Error sayı/faizi (disk errors, network errors)
```

---

## 12. Health Check Endpoint Laravel-də

Kubernetes, load balancer-lər və monitoring toolları health check endpoint-lərindən istifadə edir.

### Readiness vs Liveness

```
Liveness probe  : "Proses sağ işləyirmi?" — əgər FAIL → container restart
Readiness probe : "Sorğu qəbul etməyə hazırdırmı?" — əgər FAIL → traffic göndərilmir
```

*Readiness probe : "Sorğu qəbul etməyə hazırdırmı?" — əgər FAIL → traff üçün kod nümunəsi:*
```php
// Bu kod tətbiqin sağlamlığını yoxlayan Health Check endpoint-ini göstərir
<?php

namespace App\Http\Controllers;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Cache, DB, Redis};

class HealthCheckController extends Controller
{
    // Liveness — sadəcə proses ayaqda durur?
    // GET /health/live
    public function liveness(): JsonResponse
    {
        return response()->json(['status' => 'ok'], 200);
    }

    // Readiness — bütün dependency-lər hazırdır?
    // GET /health/ready
    public function readiness(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok', 'latency_ms' => $this->measureLatency(fn() => DB::select('SELECT 1'))];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $allHealthy = false;
        }

        // Redis check
        try {
            $latency = $this->measureLatency(fn() => Redis::ping());
            $checks['redis'] = ['status' => 'ok', 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            $checks['redis'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $allHealthy = false;
        }

        // Queue check — son 5 dəqiqədə failed job var mı?
        try {
            $failedJobs = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subMinutes(5))
                ->count();

            $checks['queue'] = [
                'status'      => $failedJobs < 10 ? 'ok' : 'degraded',
                'failed_jobs' => $failedJobs,
            ];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'unknown'];
        }

        // Disk check
        $diskFreePercent = disk_free_space('/') / disk_total_space('/') * 100;
        $checks['disk'] = [
            'status'       => $diskFreePercent > 10 ? 'ok' : 'warning',
            'free_percent' => round($diskFreePercent, 2),
        ];

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status'    => $allHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ], $statusCode);
    }

    // Detailed — internal monitoring üçün
    // GET /health/details (auth middleware ilə qorunur)
    public function details(): JsonResponse
    {
        return response()->json([
            'app' => [
                'version'     => config('app.version'),
                'environment' => config('app.env'),
                'debug'       => config('app.debug'),
                'php_version' => PHP_VERSION,
                'laravel'     => app()->version(),
            ],
            'memory' => [
                'usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb'  => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit'    => ini_get('memory_limit'),
            ],
            'server' => [
                'hostname'   => gethostname(),
                'uptime'     => $this->getServerUptime(),
                'load_avg'   => sys_getloadavg(),
            ],
        ]);
    }

    private function measureLatency(callable $fn): float
    {
        $start = microtime(true);
        $fn();
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function getServerUptime(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = (int) file_get_contents('/proc/uptime');
            return gmdate('j\d H:i:s', $uptime);
        }
        return 'N/A';
    }
}

// routes/api.php — rate limiting olmadan, auth olmadan (yalnız liveness)
Route::get('/health/live', [HealthCheckController::class, 'liveness']);
Route::get('/health/ready', [HealthCheckController::class, 'readiness']);
Route::get('/health/details', [HealthCheckController::class, 'details'])
    ->middleware('auth:sanctum');
```

---

## 13. Alerting — PagerDuty, OpsGenie

### Laravel Notification ilə alerting

*Laravel Notification ilə alerting üçün kod nümunəsi:*
```php
// Bu kod kritik xətalarda Slack-ə alert göndərən notification-ı göstərir
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class CriticalErrorAlert extends Notification
{
    public function __construct(
        private readonly \Throwable $exception,
        private readonly string $environment,
        private readonly ?string $requestId = null,
    ) {}

    public function via(object $notifiable): array
    {
        // Production-da PagerDuty, staging-də yalnız Slack
        return $this->environment === 'production'
            ? ['pagerduty', 'slack']
            : ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage())
            ->error()
            ->content("🚨 Critical Error: " . $this->exception->getMessage())
            ->attachment(function ($attachment) {
                $attachment
                    ->title(get_class($this->exception))
                    ->fields([
                        'Environment' => $this->environment,
                        'Request ID'  => $this->requestId ?? 'N/A',
                        'File'        => $this->exception->getFile() . ':' . $this->exception->getLine(),
                        'Time'        => now()->format('Y-m-d H:i:s'),
                    ]);
            });
    }
}

// Handler-də critical exception-ları alert et
$this->reportable(function (\Throwable $e) {
    if ($e instanceof CriticalException || $e->getCode() >= 500) {
        Notification::route('slack', config('services.slack.webhook_url'))
            ->notify(new CriticalErrorAlert(
                exception: $e,
                environment: config('app.env'),
                requestId: request()->header('X-Request-ID'),
            ));
    }
});
```

---

## 14. Laravel Telescope

Telescope — Laravel-in official debugging assistantıdır. Development mühitdə request, query, job, exception və s. hər şeyi izləyir.

### Konfiqurasiya

*Konfiqurasiya üçün kod nümunəsi:*
```php
// Bu kod Laravel Telescope-un konfiqurasiyasını göstərir
<?php
// config/telescope.php

return [
    'enabled' => env('TELESCOPE_ENABLED', true),

    // Yalnız development-də istifadə et
    // Production-da performance problemi yarada bilər

    'watchers' => [
        // HTTP sorğuları
        Watchers\RequestWatcher::class => [
            'enabled'         => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit'      => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64), // KB
        ],

        // Eloquent queries
        Watchers\QueryWatcher::class => [
            'enabled'      => env('TELESCOPE_QUERY_WATCHER', true),
            'slow'         => 100, // 100ms-dən uzun sorğuları "slow" kimi işarələ
        ],

        // Queue jobs
        Watchers\JobWatcher::class => [
            'enabled' => env('TELESCOPE_JOB_WATCHER', true),
        ],

        // Exception-lar
        Watchers\ExceptionWatcher::class => [
            'enabled' => env('TELESCOPE_EXCEPTION_WATCHER', true),
        ],

        // Log entries
        Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level'   => 'error',
        ],

        // Mail
        Watchers\MailWatcher::class => true,

        // Notifications
        Watchers\NotificationWatcher::class => true,

        // Cache
        Watchers\CacheWatcher::class => [
            'enabled'    => env('TELESCOPE_CACHE_WATCHER', true),
            'hidden'     => [], // gizlədilən cache key-lər
        ],

        // Redis commands
        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),

        // Scheduled tasks
        Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
    ],
];

// app/Providers/TelescopeServiceProvider.php
class TelescopeServiceProvider extends \Laravel\Telescope\TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        Telescope::night(); // dark mode

        $this->hideSensitiveRequestDetails();
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token', 'password', 'credit_card']);
        Telescope::hideRequestHeaders(['Authorization', 'Cookie', 'X-CSRF-TOKEN']);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            // Yalnız admin-lər Telescope UI-ni görə bilər
            return in_array($user->email, config('telescope.allowed_emails', []));
        });
    }
}
```

### Telescope custom tag-lar

*Telescope custom tag-lar üçün kod nümunəsi:*
```php
// Bu kod Telescope-da request-lərə custom tag-lar əlavə etməyi göstərir
<?php

// Request-ə custom tag əlavə etmək (axtarış üçün)
Telescope::tag(function (IncomingEntry $entry) {
    if ($entry->type === EntryType::REQUEST) {
        $tags = [];

        if (auth()->check()) {
            $tags[] = 'user:' . auth()->id();
        }

        if ($tenantId = tenant()?->id) {
            $tags[] = 'tenant:' . $tenantId;
        }

        return $tags;
    }

    return [];
});
```

---

## 15. Laravel Pulse

Pulse — Laravel 10.2+ ilə gələn real-time performance dashboard-ıdır. Telescope-dan fərqli olaraq production mühiti üçündür.

*Pulse — Laravel 10.2+ ilə gələn real-time performance dashboard-ıdır.  üçün kod nümunəsi:*
```php
// Bu kod Laravel Pulse-un konfiqurasiyasını və əsas metriklərin izlənməsini göstərir
<?php
// composer require laravel/pulse

// config/pulse.php
return [
    // Məlumatları hansı interval-da topla (saniyə)
    'ingest' => [
        'trim' => [
            'lottery'   => [1, 1000],
            'keep'      => '7 days',
        ],
    ],

    'recorders' => [
        // Yavaş requests
        Recorders\Requests::class => [
            'enabled'   => env('PULSE_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_REQUESTS_SAMPLE_RATE', 1), // 1 = 100%
            'threshold' => env('PULSE_REQUESTS_THRESHOLD', 1000), // ms
        ],

        // Yavaş SQL queries
        Recorders\SlowQueries::class => [
            'enabled'   => env('PULSE_SLOW_QUERIES_ENABLED', true),
            'threshold' => env('PULSE_SLOW_QUERIES_THRESHOLD', 1000),
        ],

        // Yavaş jobs
        Recorders\SlowJobs::class => [
            'enabled'   => env('PULSE_SLOW_JOBS_ENABLED', true),
            'threshold' => env('PULSE_SLOW_JOBS_THRESHOLD', 1000),
        ],

        // Exceptions
        Recorders\Exceptions::class => [
            'enabled'   => env('PULSE_EXCEPTIONS_ENABLED', true),
            'ignore'    => [
                // Bu exception-ları sayma
                \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            ],
        ],

        // Cache hits/misses
        Recorders\CacheInteractions::class => [
            'enabled' => env('PULSE_CACHE_ENABLED', true),
            'groups'  => [
                '/^product\./i' => 'Products',
                '/^user\./i'    => 'Users',
            ],
        ],

        // Server metriklər (CPU, RAM, disk)
        Recorders\Servers::class => [
            'server_name' => env('PULSE_SERVER_NAME', gethostname()),
            'directories' => explode(':', env('PULSE_SERVER_DIRECTORIES', '/')),
        ],

        // Ən çox istifadə edilən istifadəçilər
        Recorders\UserRequests::class => ['enabled' => true],
        Recorders\UserJobs::class     => ['enabled' => true],
    ],
];

// Custom Pulse card yaratmaq
namespace App\Livewire;

use Laravel\Pulse\Livewire\Card;

class PaymentMetricsCard extends Card
{
    public function render(): mixed
    {
        // Pulse-un aggregate metodlarından istifadə et
        [$data, $time, $runAt] = $this->remember(function () {
            return $this->aggregate('payment_processed', ['count', 'sum'], period: '1 hour');
        });

        return view('livewire.pulse.payment-metrics', [
            'data'  => $data,
            'time'  => $time,
            'runAt' => $runAt,
        ]);
    }
}

// Custom metric əlavə etmək
Pulse::record(
    type: 'payment_processed',
    key: 'stripe',
    value: $order->total,
)->sum()->count();
```

---

## 16. Laravel Horizon Monitoring

Horizon — Redis queue-larını real-time monitor edir.

*Horizon — Redis queue-larını real-time monitor edir üçün kod nümunəsi:*
```php
// Bu kod Laravel Horizon ilə Redis queue worker-lərinin monitorinqini göstərir
<?php
// config/horizon.php

return [
    // Horizon dashboard-a kim daxil ola bilər
    'middleware' => ['web'],

    // Worker konfiqurasiyaları
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue'      => ['high', 'default', 'low'],
                'balance'    => 'auto', // worker-ləri avtomatik paylaşdır
                'minProcesses' => 1,
                'maxProcesses' => 10,
                'balanceMaxShift'   => 1,
                'balanceCooldown'   => 3, // saniyə
                'tries'      => 3,
                'timeout'    => 60,
                'memory'     => 128, // MB — bu limitə çatanda process restart olur
            ],

            'notifications-supervisor' => [
                'connection'   => 'redis',
                'queue'        => ['notifications'],
                'balance'      => 'simple',
                'processes'    => 3,
                'tries'        => 2,
                'timeout'      => 30,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection'   => 'redis',
                'queue'        => ['high', 'default', 'low'],
                'balance'      => 'simple',
                'processes'    => 3,
                'tries'        => 3,
                'timeout'      => 60,
            ],
        ],
    ],

    // Uzun çalışan job-ları "slow" kimi işarələ (saniyə)
    'slow_job_threshold' => 300,

    // Metrik snapshot interval (saniyə)
    'snapshot_period' => 360,

    // Failed job-ları nə qədər saxla
    'trim' => [
        'recent'   => 60,    // dəqiqə
        'pending'  => 60,
        'completed' => 60,
        'recent_failed' => 10080, // 7 gün
        'failed'   => 10080,
        'monitored' => 10080,
    ],
];

// HorizonServiceProvider — Dashboard access
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return $user->hasRole('admin') || 
                   in_array($user->email, config('horizon.allowed_emails', []));
        });
    }
}

// Horizon event-ləri — job-lar haqqında notification
use Laravel\Horizon\Events\{JobFailed, LongWaitDetected};

class HorizonEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(JobFailed::class, function (JobFailed $event) {
            Log::error("Horizon: Job uğursuz oldu", [
                'queue'      => $event->queue,
                'connection' => $event->connectionName,
                'payload'    => $event->job->payload(),
            ]);
        });

        Event::listen(LongWaitDetected::class, function (LongWaitDetected $event) {
            Log::warning("Horizon: Uzun gözləmə aşkarlandı", [
                'connection' => $event->connection,
                'queue'      => $event->queue,
                'seconds'    => $event->seconds,
            ]);

            // Alert göndər
            Notification::route('slack', config('services.slack.ops_webhook'))
                ->notify(new LongQueueWaitAlert($event));
        });
    }
}
```

---

## 17. İntervyu Sualları və Cavabları

**S1: Log level-ları RFC 5424-ə görə sadalayın. Hər biri nə vaxt istifadə edilir?**

C: 8 level var: emergency (sistem tamamilə çöküb), alert (dərhal müdaxilə lazım), critical (əsas komponent uğursuz), error (əməliyyat uğursuz), warning (potensial problem), notice (əhəmiyyətli normal hadisə), info (normal axış), debug (detallı izləmə). Production-da adətən `warning` və yuxarısı log edilir, debug yalnız dev mühitindədir.

---

**S2: Structured logging niyə plain text-dən yaxşıdır?**

C: JSON log-lar machine-readable-dır: Kibana-da `context.user_id:42` kimi field-based axtarış mümkündür, Datadog-da metric-lərə çevirmək olar, alerting üçün dəqiq şərtlər yazmaq olur. Plain text-i parse etmək üçün mürəkkəb regex lazımdır, bu da xüsusilə müxtəlif format-larda log olduqda problem yaradır.

---

**S3: Correlation ID nədir, necə implement edilir?**

C: Bir istifadəçi sorğusunun bir neçə servis arasında izlənməsi üçün unikal ID-dir. Middleware-də `X-Correlation-ID` header-ini oxuyuruq (ya da yaradırıq), `Log::withContext()` ilə bütün log-lara əlavə edirik, response header-inə yazırıq. Queue job-larına da parameter kimi ötürürük. Bu sayədə Kibana-da tək correlation_id ilə bütün distributed trace-i görürük.

---

**S4: Laravel Telescope vs Laravel Pulse — fərq nədir?**

C: Telescope — development tool-udur: hər request, query, job, exception-u detallı qeyd edir. Production-da istifadə edilməməlidir (performance). Pulse isə production üçündür: aggregated metriklər toplar (sample rate ilə), real-time dashboard göstərir. Telescope "nə baş verdi" sualını, Pulse isə "sistem sağlamlığı nədir" sualını cavablandırır.

---

**S5: Health check endpoint-lərinin liveness vs readiness fərqi nədir?**

C: Liveness — "proses yaşayırmı?" Kubernetes bunu yoxlayır, FAIL olarsa container restart edir. Readiness — "servis sorğu qəbul etməyə hazırdırmı?" FAIL olarsa traffic göndərilmir, amma container restart edilmir. Məsələn: DB migration çalışarkən readiness FAIL, liveness OK olur. Bu sayədə restart əvəzinə traffic dayandırılır.

---

**S6: RED method nədir?**

C: Microservice monitoring metodologiyasıdır: Rate (saniyədə sorğu sayı), Errors (xəta faizi), Duration (latency). Bu 3 metrik hər servisin sağlamlığını xarakterizə edir. Prometheus ilə histogram, counter metrikləri toplayıb Grafana-da vizualizasiya edilir. P95/P99 latency — outlier-ləri görmək üçün average-dən daha informativdir.

---

**S7: USE method nədir, RED-dən fərqi?**

C: USE — infrastructure resursları üçündür: Utilization (CPU, RAM, disk neçə % istifadə edilir), Saturation (resurs dolduqda queue/backlog var mı), Errors (hardware/network xətaları). RED — servis/application səviyyəsindədir. Hər ikisi bir-birini tamamlayır: RED "servis yavaşdır" deyir, USE "niyə yavaşdır" cavabını verir (CPU bottleneck, disk I/O, memory).

---

**S8: Sensitive data-nı log-da necə qoruyursunuz?**

C: Bir neçə üsul: 1) `$dontFlash` — Laravel Handler-də session flash-dan çıxarır, 2) Custom Monolog Processor — context-i scan edib password, credit_card kimi sahələri mask edir, 3) Sentry kimi toolların built-in PII scrubbing, 4) Log-a yazmazdan əvvəl data-nı əl ilə mask etmək. GDPR tələblərinə görə user PII-sini production log-larında saxlamamaq vacibdir.

---

**S9: ELK stack-in komponentlərini izah edin.**

C: Elasticsearch — distributed search engine, log-ları index edib saxlayır. Logstash — log pipeline: inputdan oxuyur (Filebeat/syslog), parse və transform edir (geoip, date parsing), Elasticsearch-ə göndərir. Kibana — visualization UI: log axtarışı, dashboard, alerting. Filebeat/Fluentd server-lərdən log fayllarını oxuyub Logstash-a göndərir (lightweight agent). Alternativ: Elastic Agent hər ikisini əvəz edir.

---

**S10: Laravel Horizon-da `balance: auto` nə deməkdir?**

C: Horizon worker process-lərini queue yüküünə görə avtomatik paylaşdırır. `minProcesses` və `maxProcesses` arasında worker sayını dinamik artırıb azaldır. Məsələn: "high" queue-da çox job varsa, oraya daha çox worker assign edilir. `balanceCooldown` — neçə saniyədə bir rebalance edilsin, `balanceMaxShift` — hər dəfə maksimum neçə worker köçürülsün.

---

**S11: `Log::withContext()` vs `Log::shareContext()` fərqi?**

C: `withContext()` — yalnız cari request/process üçün context əlavə edir. Digər concurrent request-lərə təsir etmir. `shareContext()` — process-in ömrü boyunca bütün log çağrılarına əlavə olunur (məsələn app version, environment). Middleware-də `withContext()` istifadə edirik ki, hər request öz request_id-si ilə log yazar.

---

**S12: Production-da debug log level-i aktiv etmək niyə problem yarada bilər?**

C: Debug level-i çox məlumat yazır: hər SQL query, hər cache operation, hər method call. Bu: 1) disk I/O-nu artırır, performansı aşağı salır, 2) disk-i tez doldurur, 3) sensitive business məlumatları log-a düşə bilər, 4) log aggregation xərclərini artırır. Production-da minimum `warning` level istifadə edilməlidir. Müvəqqəti debug lazımdırsa, sampling (hər 100 request-dən 1-ni debug et) yaxşı kompromisdir.

---

## Anti-patternlər

**1. Production-da `debug` log level-i işlətmək**
Bütün mühitlər üçün `LOG_LEVEL=debug` buraxmaq — hər SQL sorğusu, hər cache operasiyası yazılır, disk sürətlə dolur, performans aşağı düşür, sensitive business data log-a düşür. Production-da minimum `warning`, ya da `error` level istifadə et.

**2. Kontekstsiz log mesajları yazmaq**
`Log::error("Something went wrong")` — kim, nə vaxt, hansı request, hansı user suallarına cavab yoxdur, log-da axtarış aparmaq mümkünsüzləşir. `Log::error("Order payment failed", ['order_id' => $id, 'user_id' => $userId, 'request_id' => $requestId])` kimi strukturlaşdırılmış context əlavə et.

**3. Log-larda şifrə, kart nömrəsi kimi sensitive data saxlamaq**
`Log::info("User login", ['password' => $password])` yazmaq — log faylları çox vaxt az qorunan yerlərdə saxlanır, audit-da compliance pozuntusu yaranır. Sensitive sahələri mask et (`***`), `$dontFlash` array-ini konfiqurasiya et.

**4. Alert-ləri hər kiçik xəta üçün göndərmək (alert fatigue)**
Hər 404 xətası üçün PagerDuty/Slack alert göndərmək — komanda alert-lərə biganə olmağa başlayır, real kritik xətalar diqqətsiz qalır. Alert threshold-larını düzgün qur, severity səviyyəsinə görə ayır, yalnız actionable alert-lər göndər.

**5. Mərkəzləşdirilmiş log idarəetməsi olmadan server-lərdən birbaşa log oxumaq**
Hər server-ə SSH ilə qoşulub `tail -f laravel.log` etmək — çox server mühitlərində qeyri-mümkündür, log rotasiyasında köhnə məlumatlar itirilir. ELK Stack, Loki, Datadog kimi mərkəzləşdirilmiş log aggregation qur.

**6. Health check endpoint-lərini yalnız HTTP 200 qaytarmaqla məhdudlaşdırmaq**
`/health` endpoint-i sadəcə `{"status":"ok"}` qaytarır, amma DB, Redis, queue bağlantıları yoxlanmır — servis "sağlam" görünür, amma əslində işlə bilmir. Liveness vs Readiness ayrımı et, dependency health-lərini (DB ping, cache ping, queue lag) yoxla.
