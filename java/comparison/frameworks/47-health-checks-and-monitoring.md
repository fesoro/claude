# Sağlamlıq Yoxlamaları və Monitorinq (Health Checks & Monitoring)

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Production mühitində tətbiqin sağlam işlədiyini bilmək vacibdir — verilənlər bazası bağlantısı, disk yaddaşı, xarici servislər, queue-ların vəziyyəti. Spring Boot Actuator bu sahədə çox güclüdür — onlarla daxili endpoint, Prometheus/Grafana inteqrasiyası, xüsusi metriklər. Laravel-də isə Telescope (debugging), Pulse (monitoring) və xüsusi health endpoint-ləri istifadə olunur.

---

## Spring-də istifadəsi

### Spring Boot Actuator quraşdırması

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-actuator</artifactId>
</dependency>
```

```yaml
# application.yml
management:
  endpoints:
    web:
      exposure:
        include: health, info, metrics, env, loggers, caches, scheduledtasks
      base-path: /actuator
  endpoint:
    health:
      show-details: when-authorized   # Detallar yalnız autentifikasiya ilə
      show-components: always
  info:
    env:
      enabled: true
    git:
      mode: full

# Tətbiq məlumatları
info:
  app:
    name: E-Ticarət API
    version: 2.1.0
    description: Əsas API servisi
    environment: ${spring.profiles.active:default}
```

Bu konfiqurasiyadan sonra mövcud endpoint-lər:

```
GET /actuator                  → Bütün endpoint-lərin siyahısı
GET /actuator/health           → Sağlamlıq statusu
GET /actuator/info             → Tətbiq məlumatları
GET /actuator/metrics          → Metriklərin siyahısı
GET /actuator/metrics/{name}   → Xüsusi metrik
GET /actuator/env              → Environment dəyişənləri
GET /actuator/loggers          → Logger-lər və səviyyələri
GET /actuator/caches           → Cache məlumatları
```

### Health endpoint cavabı

```
GET /actuator/health
```

```json
{
  "status": "UP",
  "components": {
    "db": {
      "status": "UP",
      "details": {
        "database": "PostgreSQL",
        "validationQuery": "isValid()"
      }
    },
    "diskSpace": {
      "status": "UP",
      "details": {
        "total": 107374182400,
        "free": 85899345920,
        "threshold": 10485760,
        "path": "/app/.",
        "exists": true
      }
    },
    "redis": {
      "status": "UP",
      "details": {
        "version": "7.2.4"
      }
    },
    "mail": {
      "status": "UP",
      "details": {
        "location": "smtp.gmail.com:587"
      }
    }
  }
}
```

### Xüsusi Health Indicator yaratmaq

```java
@Component
public class PaymentGatewayHealthIndicator implements HealthIndicator {

    private final PaymentGatewayClient paymentClient;

    public PaymentGatewayHealthIndicator(PaymentGatewayClient paymentClient) {
        this.paymentClient = paymentClient;
    }

    @Override
    public Health health() {
        try {
            long startTime = System.currentTimeMillis();
            boolean isAvailable = paymentClient.ping();
            long responseTime = System.currentTimeMillis() - startTime;

            if (isAvailable) {
                return Health.up()
                    .withDetail("provider", "Stripe")
                    .withDetail("responseTime", responseTime + "ms")
                    .withDetail("lastChecked", LocalDateTime.now().toString())
                    .build();
            } else {
                return Health.down()
                    .withDetail("provider", "Stripe")
                    .withDetail("error", "Ping uğursuz oldu")
                    .build();
            }
        } catch (Exception e) {
            return Health.down()
                .withDetail("provider", "Stripe")
                .withDetail("error", e.getMessage())
                .withException(e)
                .build();
        }
    }
}

@Component
public class QueueHealthIndicator implements HealthIndicator {

    private final StringRedisTemplate redisTemplate;

    public QueueHealthIndicator(StringRedisTemplate redisTemplate) {
        this.redisTemplate = redisTemplate;
    }

    @Override
    public Health health() {
        try {
            Long queueSize = redisTemplate.opsForList().size("order-queue");
            Long failedSize = redisTemplate.opsForList().size("failed-queue");

            Health.Builder builder = (queueSize != null && queueSize < 10000)
                ? Health.up()
                : Health.status("WARNING");

            return builder
                .withDetail("pendingJobs", queueSize)
                .withDetail("failedJobs", failedSize)
                .withDetail("maxThreshold", 10000)
                .build();
        } catch (Exception e) {
            return Health.down()
                .withDetail("error", "Redis əlçatmaz: " + e.getMessage())
                .build();
        }
    }
}
```

Nəticə:

```json
{
  "status": "UP",
  "components": {
    "db": { "status": "UP" },
    "paymentGateway": {
      "status": "UP",
      "details": {
        "provider": "Stripe",
        "responseTime": "45ms"
      }
    },
    "queue": {
      "status": "UP",
      "details": {
        "pendingJobs": 23,
        "failedJobs": 0,
        "maxThreshold": 10000
      }
    }
  }
}
```

### Micrometer — Metriklər sistemi

Micrometer, Spring Boot-un metrik fasadıdır — Prometheus, DataDog, CloudWatch və digər sistemlərə metrik göndərir:

```xml
<dependency>
    <groupId>io.micrometer</groupId>
    <artifactId>micrometer-registry-prometheus</artifactId>
</dependency>
```

```yaml
management:
  endpoints:
    web:
      exposure:
        include: health, info, metrics, prometheus
  prometheus:
    metrics:
      export:
        enabled: true
```

```
GET /actuator/prometheus
```

```
# HELP jvm_memory_used_bytes Used JVM memory
jvm_memory_used_bytes{area="heap",id="G1 Eden Space"} 2.5165824E7
jvm_memory_used_bytes{area="heap",id="G1 Old Gen"} 1.8874368E7

# HELP http_server_requests_seconds HTTP request duration
http_server_requests_seconds_count{method="GET",uri="/api/products",status="200"} 1523
http_server_requests_seconds_sum{method="GET",uri="/api/products",status="200"} 12.456

# HELP process_cpu_usage CPU usage
process_cpu_usage 0.0234
```

### Xüsusi metriklər yaratmaq

```java
@Service
public class OrderService {

    private final Counter orderCounter;
    private final Counter orderFailureCounter;
    private final Timer orderProcessingTimer;
    private final AtomicInteger activeOrders;

    public OrderService(MeterRegistry meterRegistry, OrderRepository orderRepository) {
        // Counter — sayğac (yalnız artır)
        this.orderCounter = Counter.builder("orders.created")
            .description("Yaradılmış sifarişlərin sayı")
            .tag("service", "order")
            .register(meterRegistry);

        this.orderFailureCounter = Counter.builder("orders.failed")
            .description("Uğursuz sifarişlərin sayı")
            .tag("service", "order")
            .register(meterRegistry);

        // Timer — əməliyyat müddəti
        this.orderProcessingTimer = Timer.builder("orders.processing.duration")
            .description("Sifariş emal müddəti")
            .publishPercentiles(0.5, 0.95, 0.99)  // Median, P95, P99
            .register(meterRegistry);

        // Gauge — cari dəyər (artıb-azala bilər)
        this.activeOrders = new AtomicInteger(0);
        Gauge.builder("orders.active", activeOrders, AtomicInteger::get)
            .description("Hazırda emal olunan sifarişlər")
            .register(meterRegistry);
    }

    public OrderDto createOrder(CreateOrderRequest request) {
        return orderProcessingTimer.record(() -> {
            activeOrders.incrementAndGet();
            try {
                // Sifariş məntiqı
                Order order = processOrder(request);
                orderCounter.increment();
                return order.toDto();
            } catch (Exception e) {
                orderFailureCounter.increment();
                throw e;
            } finally {
                activeOrders.decrementAndGet();
            }
        });
    }
}
```

### Prometheus + Grafana inteqrasiyası

```yaml
# docker-compose.yml
services:
  app:
    build: .
    ports:
      - "8080:8080"

  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml

  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
```

```yaml
# prometheus.yml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'spring-app'
    metrics_path: '/actuator/prometheus'
    static_configs:
      - targets: ['app:8080']
```

### Logger səviyyəsini runtime-da dəyişmək

```
# Cari səviyyəni görmək
GET /actuator/loggers/com.example.orderservice

{
  "configuredLevel": null,
  "effectiveLevel": "INFO"
}

# Runtime-da DEBUG-a dəyişmək
POST /actuator/loggers/com.example.orderservice
Content-Type: application/json

{
  "configuredLevel": "DEBUG"
}
```

Bu, production-da restart etmədən debug rejimini aktivləşdirməyə imkan verir.

### Actuator təhlükəsizliyi

```java
@Configuration
public class ActuatorSecurityConfig {

    @Bean
    public SecurityFilterChain actuatorSecurity(HttpSecurity http) throws Exception {
        return http
            .securityMatcher("/actuator/**")
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/actuator/health").permitAll()     // Health — açıq
                .requestMatchers("/actuator/info").permitAll()       // Info — açıq
                .requestMatchers("/actuator/**").hasRole("ACTUATOR") // Qalanı — icazə lazım
            )
            .httpBasic(Customizer.withDefaults())
            .build();
    }
}
```

---

## Laravel-də istifadəsi

### Xüsusi Health Endpoint

Laravel-də daxili health endpoint sistemi yoxdur, amma yaratmaq asandır:

```php
// routes/api.php
Route::get('/health', [HealthController::class, 'check']);
Route::get('/health/detailed', [HealthController::class, 'detailed'])
    ->middleware('auth:sanctum');

// app/Http/Controllers/HealthController.php
class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $healthy = true;
        $checks = [];

        // Verilənlər bazası
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'up'];
        } catch (Throwable $e) {
            $healthy = false;
            $checks['database'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        // Redis
        try {
            Cache::store('redis')->put('health_check', true, 10);
            $checks['redis'] = ['status' => 'up'];
        } catch (Throwable $e) {
            $healthy = false;
            $checks['redis'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }

    public function detailed(): JsonResponse
    {
        $checks = [];

        // Verilənlər bazası
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            $checks['database'] = [
                'status' => 'up',
                'driver' => config('database.default'),
                'response_time' => $responseTime . 'ms',
            ];
        } catch (Throwable $e) {
            $checks['database'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        // Redis
        try {
            $info = Redis::info();
            $checks['redis'] = [
                'status' => 'up',
                'version' => $info['redis_version'] ?? 'unknown',
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 'unknown',
            ];
        } catch (Throwable $e) {
            $checks['redis'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        // Queue
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $checks['queue'] = [
                'status' => $pendingJobs < 10000 ? 'up' : 'warning',
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
            ];
        } catch (Throwable $e) {
            $checks['queue'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        // Disk
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsedPercent = round((1 - $diskFree / $diskTotal) * 100, 1);
        $checks['disk'] = [
            'status' => $diskUsedPercent < 90 ? 'up' : 'warning',
            'free' => $this->formatBytes($diskFree),
            'total' => $this->formatBytes($diskTotal),
            'used_percent' => $diskUsedPercent . '%',
        ];

        // Xarici servislər
        try {
            $response = Http::timeout(5)->get(config('services.payment.url') . '/health');
            $checks['payment_gateway'] = [
                'status' => $response->ok() ? 'up' : 'down',
                'response_time' => $response->handlerStats()['total_time'] ?? 'unknown',
            ];
        } catch (Throwable $e) {
            $checks['payment_gateway'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        $overallStatus = collect($checks)->every(fn ($c) => $c['status'] === 'up')
            ? 'healthy' : 'degraded';

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
            'app_version' => config('app.version', '1.0.0'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
        ]);
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

### Schedule əsaslı health check

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {
        $checks = app(HealthCheckService::class)->runAll();

        foreach ($checks as $name => $result) {
            if ($result['status'] !== 'up') {
                Log::critical("Health check uğursuz: {$name}", $result);

                Notification::route('slack', config('services.slack.webhook'))
                    ->notify(new HealthCheckFailedNotification($name, $result));
            }
        }
    })->everyFiveMinutes()->name('health-checks');
}
```

### Laravel Telescope — Debugging aləti

Telescope, development mühitində tətbiqin daxilini görmək üçün istifadə olunur:

```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

Telescope bu məlumatları izləyir:
- HTTP sorğuları və cavabları
- Verilənlər bazası sorğuları (yavaş sorğuları aşkarlayır)
- Cache əməliyyatları
- Queue job-ları
- Mail göndərmələr
- Bildirişlər
- Exception-lar
- Log yazıları
- Schedule tapşırıqları
- Dump-lar (`dump()` çağırışları)

```php
// config/telescope.php
return [
    'enabled' => env('TELESCOPE_ENABLED', true),
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => 'telescope',

    'watchers' => [
        Watchers\QueryWatcher::class => [
            'enabled' => true,
            'slow' => 100,   // 100ms-dən yavaş sorğuları qeyd et
        ],
        Watchers\RequestWatcher::class => [
            'enabled' => true,
            'size_limit' => 64,  // KB
        ],
        Watchers\ExceptionWatcher::class => [
            'enabled' => true,
        ],
        Watchers\JobWatcher::class => [
            'enabled' => true,
        ],
        Watchers\MailWatcher::class => [
            'enabled' => true,
        ],
        Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
        ],
    ],
];
```

```php
// Telescope-u yalnız müəyyən istifadəçilərə açmaq
// app/Providers/TelescopeServiceProvider.php
protected function gate(): void
{
    Gate::define('viewTelescope', function (User $user) {
        return in_array($user->email, [
            'admin@example.com',
            'developer@example.com',
        ]);
    });
}
```

Telescope UI: `http://app.test/telescope`

### Laravel Pulse — Real-time monitoring

Pulse, production mühitində tətbiqin performansını izləyir:

```bash
composer require laravel/pulse
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
php artisan migrate
```

```php
// config/pulse.php
return [
    'recorders' => [
        // Yavaş sorğuları izlə
        Recorders\SlowQueries::class => [
            'enabled' => true,
            'threshold' => 1000,  // 1 saniyədən yavaş
        ],

        // Yavaş HTTP sorğularını izlə
        Recorders\SlowRequests::class => [
            'enabled' => true,
            'threshold' => 1000,
        ],

        // Yavaş job-ları izlə
        Recorders\SlowJobs::class => [
            'enabled' => true,
            'threshold' => 1000,
        ],

        // İstisnaları izlə
        Recorders\Exceptions::class => [
            'enabled' => true,
        ],

        // Server resursları
        Recorders\Servers::class => [
            'enabled' => true,
        ],

        // İstifadəçi aktivliyi
        Recorders\UserRequests::class => [
            'enabled' => true,
        ],

        // Cache əməliyyatları
        Recorders\CacheInteractions::class => [
            'enabled' => true,
        ],

        // Xarici HTTP sorğuları
        Recorders\SlowOutgoingRequests::class => [
            'enabled' => true,
            'threshold' => 1000,
        ],
    ],
];
```

Pulse UI: `http://app.test/pulse`

Pulse dashboardda bunlar görünür:
- Server CPU, yaddaş, disk istifadəsi
- Ən yavaş verilənlər bazası sorğuları
- Ən yavaş HTTP endpoint-ləri
- Ən çox exception atan yerlər
- Queue job performansı
- Cache hit/miss nisbəti
- Ən aktiv istifadəçilər

### Xüsusi Pulse kart yaratmaq

```php
// app/Pulse/Recorders/OrderMetrics.php
class OrderMetrics
{
    public function register(callable $record): void
    {
        Order::created(function (Order $order) use ($record) {
            $record(
                type: 'order_created',
                key: $order->status,
                value: $order->total,
            );
        });
    }
}

// Blade komponentini yaradıb Pulse dashboarda əlavə etmək
// resources/views/vendor/pulse/dashboard.blade.php
<x-pulse>
    <livewire:pulse.servers cols="full" />

    <livewire:pulse.usage cols="4" rows="2" />
    <livewire:pulse.queues cols="4" />
    <livewire:pulse.cache cols="4" />

    <livewire:pulse.slow-queries cols="8" />
    <livewire:pulse.exceptions cols="4" />

    <livewire:pulse.slow-requests cols="6" />
    <livewire:pulse.slow-jobs cols="6" />

    <livewire:pulse.slow-outgoing-requests cols="6" />
</x-pulse>
```

### Prometheus inteqrasiyası — Laravel ilə

```php
// Xüsusi Prometheus endpoint yaratmaq
Route::get('/metrics', function () {
    $metrics = [];

    // HTTP sorğu sayı
    $requestCount = DB::table('pulse_entries')
        ->where('type', 'slow_request')
        ->where('created_at', '>', now()->subHour())
        ->count();
    $metrics[] = "# HELP http_slow_requests_total Slow HTTP requests in last hour";
    $metrics[] = "http_slow_requests_total {$requestCount}";

    // Queue metrikləri
    $pendingJobs = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();
    $metrics[] = "# HELP queue_pending_jobs Pending queue jobs";
    $metrics[] = "queue_pending_jobs {$pendingJobs}";
    $metrics[] = "# HELP queue_failed_jobs Failed queue jobs";
    $metrics[] = "queue_failed_jobs {$failedJobs}";

    // Cache
    $cacheHits = Cache::get('metrics:cache_hits', 0);
    $cacheMisses = Cache::get('metrics:cache_misses', 0);
    $metrics[] = "# HELP cache_hits_total Cache hits";
    $metrics[] = "cache_hits_total {$cacheHits}";
    $metrics[] = "cache_misses_total {$cacheMisses}";

    return response(implode("\n", $metrics), 200)
        ->header('Content-Type', 'text/plain');
});
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring (Actuator) | Laravel |
|---|---|---|
| Health endpoint | Daxili, avtomatik | Əl ilə yazılır |
| Auto-discovered checks | DB, Redis, Mail, Disk avtomatik | Yoxdur |
| Xüsusi health indicator | `HealthIndicator` interface | Əl ilə controller |
| Metriklər | Micrometer (güclü, standart) | Pulse (daxili) və ya əl ilə |
| Prometheus dəstəyi | Daxili (dependency əlavə et) | Əl ilə endpoint yaratmaq lazım |
| Logger idarəsi | Runtime-da dəyişmək olur | Restart lazım |
| Debugging | Yoxdur (IDE ilə debug) | Telescope (web UI) |
| Production monitoring | Actuator + Prometheus + Grafana | Pulse (web dashboard) |
| Environment məlumatı | `/actuator/env` endpoint | Yoxdur (təhlükəsizlik üçün) |
| Cache məlumatı | `/actuator/caches` endpoint | Pulse-da cache hit/miss |
| Scheduled task monitoring | `/actuator/scheduledtasks` | Pulse-da izlənir |

---

## Niyə belə fərqlər var?

**Spring Boot enterprise mühit üçündür.** Böyük şirkətlərdə onlarla, yüzlərlə servis işləyir. Hər servisin sağlamlığını avtomatik yoxlamaq, metriklər toplamaq və alert göndərmək vacibdir. Actuator bu tələbi ödəyir — quraşdır, konfiqurasiya et, işləsin.

**Laravel-in web developer auditoriyası** adətən bir neçə server idarə edir. Uptime monitoring üçün xarici alətlər (UptimeRobot, Better Stack) kifayətdir. Pulse, Laravel icmasının bu boşluğu doldurmaq üçün yaratdığı nisbətən yeni alətdir.

**JVM-in instrumentasiya gücü.** Java-nın JMX (Java Management Extensions) sistemi JVM-in daxilini izləməyə imkan verir — yaddaş, garbage collection, thread-lər. Micrometer bunu standart metrik formatına çevirir. PHP-nin belə dərin instrumentasiya imkanı yoxdur — hər sorğu ayrı proses olduğu üçün davamlı metriklər toplamaq çətindir.

**Telescope vs Actuator fərqli məqsədlərə xidmət edir.** Telescope development/debugging alətidir — production-da adətən söndürülür. Actuator isə production monitoring alətidir — həmişə aktiv olur. Laravel-də Pulse bu boşluğu doldurur, amma Actuator-un funksionallığına hələ çatmayıb.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Actuator ilə 20+ daxili endpoint (health, metrics, env, loggers, caches, beans...)
- `HealthIndicator` interface ilə standartlaşdırılmış sağlamlıq yoxlamaları
- DB, Redis, Mail, Disk, LDAP üçün avtomatik health check
- Micrometer ilə Counter, Timer, Gauge, Distribution Summary metriklər
- Prometheus endpoint — daxili, sıfır kod
- Runtime-da logger səviyyəsini dəyişmək (restart olmadan)
- `/actuator/beans` — bütün Spring bean-lərinin siyahısı
- `/actuator/env` — environment dəyişənlərinin görünüşü
- `/actuator/scheduledtasks` — planlanmış tapşırıqların siyahısı
- JVM metriklər (heap, GC, thread count)

**Yalnız Laravel-də:**
- Telescope — vizual debugging interface (sorğular, DB queries, jobs, mail, exceptions)
- Pulse — real-time production dashboard (server metrics, slow queries, exceptions)
- Telescope-da dump() çağırışlarının izlənməsi
- Telescope-da mail preview (göndərilən emailləri görmək)
- Pulse-da istifadəçi aktivliyi izləmə
- Pulse-da xarici HTTP sorğu performansı izləmə
