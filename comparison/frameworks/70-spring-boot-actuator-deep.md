# Spring Boot Actuator vs Laravel Health/Observability — Dərin Müqayisə

## Giriş

Spring Boot Actuator — production tətbiqini "içəridən görmək" üçün hazırlanmış rəsmi paketdir. Health, metrics, env, beans, httpexchanges, thread dump, heap dump, loggers, Flyway, Liquibase, Quartz, scheduledtasks, shutdown — onlarla endpoint. Dependency əlavə edən kimi `/actuator/health` və `/actuator/info` aktiv olur. Qalanını `management.endpoints.web.exposure.include` ilə açırsan.

Laravel-də birinci tərəf (first-party) analoqu yoxdur. Laravel 11 `/up` endpoint gətirdi — bu yalnız "app boot-olur" deyir, amma DB/Redis yoxlamır. Real health check üçün **`spatie/laravel-health`** paketi və ya manual route yazılır. Metrics üçün **Laravel Pulse**, dev debugging üçün **Telescope**, app info üçün **`php artisan about`**, queue üçün **Horizon** API. Hər biri ayrıca paketdir.

---

## Spring-də istifadəsi

### 1) Asılılıq və ilkin quraşdırma

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-actuator</artifactId>
</dependency>
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
        include: health,info,metrics,prometheus,loggers,env,beans,httpexchanges,threaddump,heapdump,scheduledtasks,flyway,mappings,caches
      base-path: /actuator
  endpoint:
    health:
      show-details: when-authorized
      show-components: always
      probes:
        enabled: true
      group:
        liveness:
          include: livenessState
        readiness:
          include: readinessState,db,redis,kafka
    shutdown:
      enabled: true
    env:
      show-values: when-authorized
  server:
    port: 9090                      # ayrı management port
    base-path: /
  info:
    git:
      mode: full
    env:
      enabled: true
    build:
      enabled: true
  metrics:
    tags:
      application: ${spring.application.name}
      environment: ${ENV:production}
    distribution:
      percentiles-histogram:
        http.server.requests: true
      sla:
        http.server.requests: 100ms,500ms,1s
```

### 2) Health endpoint — built-in və custom

Built-in `HealthIndicator`-lar: `DataSourceHealthIndicator`, `DiskSpaceHealthIndicator`, `RedisHealthIndicator`, `MongoHealthIndicator`, `KafkaHealthIndicator`, `RabbitHealthIndicator`, `ElasticsearchHealthIndicator`.

```bash
curl http://localhost:9090/actuator/health
```

```json
{
  "status": "UP",
  "components": {
    "db": { "status": "UP", "details": {"database": "PostgreSQL", "validationQuery": "isValid()"} },
    "diskSpace": { "status": "UP", "details": {"total": 499963174912, "free": 102547431424, "threshold": 10485760} },
    "redis": { "status": "UP", "details": {"version": "7.2.4"} },
    "kafka": { "status": "UP" },
    "ping": { "status": "UP" }
  }
}
```

Custom `HealthIndicator`:

```java
@Component("paymentGateway")
@RequiredArgsConstructor
public class PaymentGatewayHealth implements HealthIndicator {

    private final RestClient restClient;

    @Override
    public Health health() {
        try {
            HttpStatusCode status = restClient.get()
                .uri("https://api.stripe.com/v1/charges?limit=1")
                .retrieve()
                .toBodilessEntity()
                .getStatusCode();

            if (status.is2xxSuccessful()) {
                return Health.up().withDetail("provider", "stripe").build();
            }
            return Health.down().withDetail("statusCode", status.value()).build();
        } catch (Exception e) {
            return Health.down(e).withDetail("provider", "stripe").build();
        }
    }
}
```

### 3) Liveness vs Readiness (Kubernetes)

Spring Boot Actuator Kubernetes probe-ları birinci tərəfdən dəstəkləyir:

```yaml
management:
  endpoint:
    health:
      probes:
        enabled: true
      group:
        readiness:
          include: readinessState,db,redis
```

Endpoint-lər:
- `/actuator/health/liveness` — JVM sağdırmı? (restart qərarı)
- `/actuator/health/readiness` — trafik qəbul etməyə hazırdırmı? (service endpoint-ə əlavə qərarı)

Kodda `AvailabilityState` dəyişdirmək:

```java
@Component
@RequiredArgsConstructor
public class StartupConfig {

    private final ApplicationEventPublisher publisher;

    @EventListener(ApplicationReadyEvent.class)
    public void onReady() {
        // Warmup caches, first DB ping
        publisher.publishEvent(new AvailabilityChangeEvent<>(this, ReadinessState.ACCEPTING_TRAFFIC));
    }

    public void enterMaintenance() {
        publisher.publishEvent(new AvailabilityChangeEvent<>(this, ReadinessState.REFUSING_TRAFFIC));
    }
}
```

Kubernetes deployment:

```yaml
livenessProbe:
  httpGet:
    path: /actuator/health/liveness
    port: 9090
  initialDelaySeconds: 30
  periodSeconds: 10
readinessProbe:
  httpGet:
    path: /actuator/health/readiness
    port: 9090
  periodSeconds: 5
  failureThreshold: 3
```

### 4) Info endpoint — build + git info

```xml
<plugin>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-maven-plugin</artifactId>
    <executions>
        <execution><goals><goal>build-info</goal></goals></execution>
    </executions>
</plugin>

<plugin>
    <groupId>io.github.git-commit-id</groupId>
    <artifactId>git-commit-id-maven-plugin</artifactId>
</plugin>
```

```bash
curl http://localhost:9090/actuator/info
```

```json
{
  "app": { "name": "order-service", "version": "1.24.0" },
  "build": { "artifact": "order-service", "version": "1.24.0", "time": "2026-04-20T08:14:22Z" },
  "git": { "branch": "main", "commit": { "id": "a1b2c3d", "time": "2026-04-19T18:00:00Z" } }
}
```

Custom `InfoContributor`:

```java
@Component
public class FeatureFlagsInfoContributor implements InfoContributor {

    private final FeatureFlagService ff;

    public FeatureFlagsInfoContributor(FeatureFlagService ff) { this.ff = ff; }

    @Override
    public void contribute(Info.Builder builder) {
        builder.withDetail("features", ff.activeFlags());
    }
}
```

### 5) Custom endpoint — `@Endpoint`

```java
@Component
@Endpoint(id = "cacheStats")
public class CacheStatsEndpoint {

    private final CacheManager cacheManager;

    public CacheStatsEndpoint(CacheManager cm) { this.cacheManager = cm; }

    @ReadOperation
    public Map<String, Object> stats() {
        return cacheManager.getCacheNames().stream()
            .collect(Collectors.toMap(name -> name, name -> cacheSize(name)));
    }

    @ReadOperation
    public Object cache(@Selector String name) {
        Cache cache = cacheManager.getCache(name);
        return cache == null ? Map.of("error", "not found") : Map.of("name", name, "size", cacheSize(name));
    }

    @WriteOperation
    public void evict(@Selector String name) {
        Optional.ofNullable(cacheManager.getCache(name)).ifPresent(Cache::clear);
    }

    @DeleteOperation
    public void evictAll() {
        cacheManager.getCacheNames().forEach(n -> cacheManager.getCache(n).clear());
    }

    private Object cacheSize(String name) {
        Cache cache = cacheManager.getCache(name);
        if (cache == null) return 0;
        if (cache.getNativeCache() instanceof com.github.benmanes.caffeine.cache.Cache<?,?> c) {
            return c.estimatedSize();
        }
        return "unknown";
    }
}
```

Variantlar:
- `@WebEndpoint` — yalnız HTTP
- `@JmxEndpoint` — yalnız JMX
- `@ControllerEndpoint` — Spring MVC annotations istifadə edir

### 6) Metrics və Prometheus

Micrometer Actuator ilə birləşib — `/actuator/prometheus` Prometheus scrape formatında çıxış verir:

```
# HELP http_server_requests_seconds  
# TYPE http_server_requests_seconds summary
http_server_requests_seconds_count{application="order-service",method="GET",status="200",uri="/orders/{id}"} 14523
http_server_requests_seconds_sum{application="order-service",method="GET",status="200",uri="/orders/{id}"} 1842.56
```

Custom metric:

```java
@Service
@RequiredArgsConstructor
public class OrderService {

    private final MeterRegistry meter;
    private final OrderRepository orders;

    public Order place(PlaceOrderRequest req) {
        Timer.Sample sample = Timer.start(meter);
        try {
            Order o = orders.save(toEntity(req));
            meter.counter("orders.placed", "country", req.country()).increment();
            return o;
        } finally {
            sample.stop(meter.timer("orders.place.duration", "country", req.country()));
        }
    }
}
```

### 7) Security + ayrı port

```java
@Configuration
@EnableWebSecurity
public class ActuatorSecurityConfig {

    @Bean
    @Order(1)
    public SecurityFilterChain actuator(HttpSecurity http) throws Exception {
        http
            .securityMatcher(EndpointRequest.toAnyEndpoint())
            .authorizeHttpRequests(a -> a
                .requestMatchers(EndpointRequest.to("health", "info")).permitAll()
                .anyRequest().hasRole("ACTUATOR_ADMIN"))
            .httpBasic(Customizer.withDefaults());
        return http.build();
    }
}
```

Production-da Actuator **ayrıca port**-da (9090) açılır, həmin port ingress-dən public olmur — yalnız Prometheus və K8s probe-lar üçün internal network-də görünür.

### 8) Loggers endpoint — runtime log level dəyişmək

```bash
curl -X POST http://localhost:9090/actuator/loggers/com.example.orders \
    -H 'Content-Type: application/json' \
    -d '{"configuredLevel":"DEBUG"}'
```

Restart olmadan log səviyyəsi dəyişir — incident vaxtı tətbiqi kəsmədən debug log aç, sonra bağla.

### 9) Thread dump + heap dump

```bash
curl http://localhost:9090/actuator/threaddump
curl http://localhost:9090/actuator/heapdump -o heap.hprof
```

Istifadə: hang olmuş app-da thread dump götürüb "hansı thread-lər nə gözləyir?" anlamaq.

---

## Laravel-də istifadəsi

### 1) `/up` — Laravel 11+ built-in

```php
// bootstrap/app.php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Sadə "app boot-olur" cavabı verir — **DB/Redis/Kafka yoxlamır**.

### 2) `spatie/laravel-health` — actuator analoqu

```bash
composer require spatie/laravel-health
php artisan vendor:publish --provider="Spatie\Health\HealthServiceProvider"
php artisan health:table
```

```php
// app/Providers/AppServiceProvider.php
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\{
    DatabaseCheck,
    RedisCheck,
    CacheCheck,
    UsedDiskSpaceCheck,
    QueueCheck,
    ScheduleCheck,
    HorizonCheck,
    EnvironmentCheck,
    DebugModeCheck,
    PingCheck
};

public function boot(): void
{
    Health::checks([
        EnvironmentCheck::new(),
        DebugModeCheck::new(),
        DatabaseCheck::new()->connectionName('pgsql'),
        RedisCheck::new()->connectionName('default'),
        CacheCheck::new()->driver('redis'),
        UsedDiskSpaceCheck::new()->warnWhenUsedSpaceIsAbovePercentage(70)->failWhenUsedSpaceIsAbovePercentage(90),
        QueueCheck::new(),
        ScheduleCheck::new()->heartbeatMaxAgeInMinutes(5),
        HorizonCheck::new(),
        PingCheck::new()->url('https://api.stripe.com/v1')->name('stripe-gateway'),
    ]);
}
```

```php
// routes/web.php
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;
use Spatie\Health\Http\Controllers\SimpleHealthCheckController;

Route::get('/health', HealthCheckJsonResultsController::class);
Route::get('/up', SimpleHealthCheckController::class);
```

Cavab:

```json
{
  "checkResults": [
    {"name":"DatabaseCheck","label":"Database","status":"ok"},
    {"name":"RedisCheck","label":"Redis","status":"ok"},
    {"name":"UsedDiskSpaceCheck","label":"UsedDiskSpace","status":"ok","meta":{"used_disk_space_percentage":42}},
    {"name":"PingCheck","label":"stripe-gateway","status":"ok"}
  ]
}
```

### 3) Custom check

```php
namespace App\HealthChecks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class PaymentGatewayCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        try {
            $response = Http::timeout(3)->get('https://api.stripe.com/v1/charges?limit=1');
            if ($response->successful()) {
                return $result->ok()->meta(['status' => $response->status()]);
            }
            return $result->failed('Stripe returned '.$response->status());
        } catch (\Throwable $e) {
            return $result->failed($e->getMessage());
        }
    }
}

// servise register
Health::checks([
    ...
    \App\HealthChecks\PaymentGatewayCheck::new(),
]);
```

### 4) Liveness və Readiness — Kubernetes üçün

Laravel-də birinci tərəf "probe" anlayışı yoxdur. Manual ayrıca route:

```php
// routes/web.php
Route::get('/health/live', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/health/ready', function () {
    $db = DB::connection()->getPdo() !== null;
    $redis = Redis::connection()->ping() === true;

    $ok = $db && $redis;
    return response()->json([
        'status' => $ok ? 'ok' : 'fail',
        'checks' => ['db' => $db, 'redis' => $redis],
    ], $ok ? 200 : 503);
});
```

```yaml
# deployment.yaml
livenessProbe:
  httpGet: { path: /health/live, port: 8080 }
  initialDelaySeconds: 15
  periodSeconds: 10
readinessProbe:
  httpGet: { path: /health/ready, port: 8080 }
  periodSeconds: 5
  failureThreshold: 3
```

### 5) Laravel Pulse — metrics dashboard

```bash
composer require laravel/pulse
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
php artisan migrate
```

```php
// config/pulse.php
'recorders' => [
    \Laravel\Pulse\Recorders\Servers::class => [
        'server_name' => env('PULSE_SERVER_NAME', gethostname()),
        'directories' => ['/'],
    ],
    \Laravel\Pulse\Recorders\SlowRequests::class => ['threshold' => 1000],
    \Laravel\Pulse\Recorders\SlowQueries::class => ['threshold' => 1000],
    \Laravel\Pulse\Recorders\SlowJobs::class => ['threshold' => 1000],
    \Laravel\Pulse\Recorders\Exceptions::class => [],
    \Laravel\Pulse\Recorders\UserRequests::class => [],
    \Laravel\Pulse\Recorders\CacheInteractions::class => [],
],
```

```php
// routes/web.php
Route::get('/pulse', function () {
    return app(\Laravel\Pulse\Http\Controllers\DashboardController::class)();
})->middleware(['auth', 'can:viewPulse']);
```

Custom metric:

```php
use Laravel\Pulse\Facades\Pulse;

class OrderService
{
    public function place(array $data): Order
    {
        $start = microtime(true);
        $order = Order::create($data);
        $ms = (microtime(true) - $start) * 1000;

        Pulse::record('orders_placed', $data['country'] ?? 'unknown')
            ->sum(1)
            ->avg($ms);

        return $order;
    }
}
```

### 6) Telescope — dev debugger

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Telescope production-da deyil, dev mühiti üçün. Actuator-un `httpexchanges` endpoint-inə yaxın, amma daha geniş — request, query, job, event, mail, cache, notification hamısı audit trail-də toplanır.

### 7) `php artisan about` + config inspection

```bash
php artisan about
```

```
  Environment ..............................................
  Application Name ................................. Laravel
  Laravel Version .................................. 11.32.0
  PHP Version ...................................... 8.3.10
  Composer Version ................................. 2.7.7
  Environment ................................... production
  Debug Mode .......................................... OFF

  Cache ...................................................
  Config ........................................... CACHED
  Events ......................................... NOT CACHED
  Routes ........................................... CACHED
  Views ......................................... NOT CACHED

  Drivers ................................................
  Broadcasting .................................... reverb
  Cache ............................................ redis
  Database .......................................... pgsql
  Logs ................. stack / single / stderr / slack
  Mail ................................................ ses
  Queue ............................................ redis
  Session ........................................... redis
```

```bash
php artisan config:show database
php artisan config:show cache.stores
```

### 8) Horizon API — queue monitoring

```bash
composer require laravel/horizon
```

```php
// config/horizon.php
'waits' => [
    'redis:default' => 60,
    'redis:emails' => 120,
],
```

API endpoint-lər:
- `/horizon/api/stats` — JSON stats
- `/horizon/api/workload` — hər queue üçün pending/processed
- `/horizon/api/jobs/failed` — failed job list

Horizon dashboard Actuator-un queue hissəsinə bənzəyir — amma yalnız Redis üçün.

### 9) Runtime log level və Prometheus export

Actuator `/loggers` endpoint-i kimi runtime log səviyyəsi dəyişmək Laravel-də manual Cache flag + dinamik `config/logging.php` ilə işə düşür — native deyil.

Prometheus format üçün `promphp/prometheus_client_php` istifadə olunur:

```bash
composer require promphp/prometheus_client_php
```

```php
// app/Providers/AppServiceProvider.php
use Prometheus\Storage\Redis as RedisStorage;

public function register(): void
{
    $this->app->singleton(CollectorRegistry::class, function () {
        Redis::setDefaultOptions(['host' => config('database.redis.default.host'), 'port' => 6379]);
        return CollectorRegistry::getDefault();
    });
}

// istifadə
$counter = $registry->getOrRegisterCounter('app', 'orders_placed_total', 'Orders placed', ['country']);
$counter->inc(['AZ']);
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Actuator | Laravel |
|---|---|---|
| First-party paket | Var | Yalnız `/up` (11+) |
| Sağlamlıq checks | DataSource/Redis/Mongo/Kafka auto | Manual və ya `spatie/laravel-health` |
| Custom health | `HealthIndicator` bean | `Check` class (spatie) |
| Liveness/Readiness | `/health/liveness`, `/health/readiness` | Manual route |
| Metrics | Micrometer + `/prometheus` | `promphp` paket manual |
| App info | `/actuator/info` + build + git | `php artisan about` (CLI) |
| Loggers (runtime) | `POST /actuator/loggers/{name}` | Manual workaround |
| Thread/heap dump | `/threaddump`, `/heapdump` | Yoxdur (xlaug, FPM trace) |
| Env endpoint | `/actuator/env` | `php artisan config:show` |
| Beans listesi | `/actuator/beans` | Yoxdur |
| HTTP exchanges | `/actuator/httpexchanges` | Telescope (yalnız dev) |
| Ayrı management port | Var | Manual route |
| Shutdown endpoint | Var | `php artisan down` (maintenance) |
| Scheduled tasks | `/actuator/scheduledtasks` | `schedule:list` command |

---

## Niyə belə fərqlər var?

**Enterprise tələbi.** Spring Actuator 2013-cü ildən var, enterprise mühitdə "tətbiqi içəridən görmək" tələbindən doğulub. Bank, insurance, telecom şirkətlərində SREoperator-un tətbiqi runtime-da audit etməsi adi haldır.

**PHP request model.** PHP-də hər sorğu yeni proses olur — "process içində thread dump" anlayışı yoxdur. JVM-də `jstack` bir komanda ilə bütün thread-ləri göstərir; PHP-də bunun analoqu olmadığı üçün Actuator kimi endpoint sadəcə mümkün deyil.

**Micrometer vs manual.** Micrometer Spring ilə sıx inteqrasiyalıdır — HTTP request-lərin gecikməsi, JDBC connection pool, cache hit/miss, GC pauses avtomatik metrik olur. Laravel-də bunu əldə qurursan.

**Laravel-in "batteries yavaş gəlir" yanaşması.** Queue → Horizon, debug → Telescope, metrics → Pulse, health → Spatie paketi. Hər biri ayrıca, zaman-zaman əlavə olunub. Spring isə həmin kolleksiyanı bir dənədə (Actuator) birləşdirib.

**Security default.** Actuator default-da yalnız `/health` və `/info` açır — qalanını özün explicit include etməlisən. Bu ağıllı default təhlükəsizliyi təmin edir. Laravel paketlərində də benzer pattern, amma orijinal paket müəllifindən asılıdır.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `/actuator/loggers` — runtime-da log level dəyişmək.
- `/actuator/threaddump`, `/actuator/heapdump` — JVM diaqnostika.
- `/actuator/beans` — DI container ilə qeydə alınmış bütün bean-lar.
- `/actuator/httpexchanges` — son N HTTP sorğusu.
- `/actuator/scheduledtasks` — bütün `@Scheduled` metodlar.
- `/actuator/mappings` — route siyahısı HTTP API kimi.
- `/actuator/flyway`, `/actuator/liquibase` — DB migrasiya tarixi.
- `/actuator/caches` — CacheManager daxilindəki cache-lər.
- `@Endpoint` annotation ilə yeni endpoint yaratmaq.
- `AvailabilityState` API.
- Separate management port (`management.server.port`).

**Yalnız Laravel-də:**
- `php artisan about` — CLI-da app snapshot.
- Horizon dashboard — Redis queue üçün.
- Telescope — dev-time audit trail (request, query, job, cache, notification, event).
- Pulse — sadə metrics dashboard (SlowQueries, SlowJobs, Exceptions, UserRequests).
- `schedule:list` command.
- `/up` endpoint — sadə boot check.

---

## Best Practices

- **Security**: Actuator-u ayrı port-da (9090) aç, yalnız internal network-dən görünsün. `/health` və `/info` public ola bilər, qalanları auth arxasında.
- **Readiness yalnız həqiqi tələblər**: DB və kritik downstream-lərə ping. Üçüncü tərəf API (Stripe) readiness-da yox, metrik kimi izlə — yoxsa Stripe down olanda sənin pod-lar K8s-dan düşür.
- **Liveness sadə saxla**: tətbiq restart-ı həqiqətən kömək edəcəyi hal. DB down olanda restart kömək etmir — readiness-i fail et, liveness yaşasın.
- **Metrics histogram + SLA**: `http.server.requests` üçün percentile histogram və SLA thresholds (100ms, 500ms, 1s) config-də qoş.
- **Loggers endpoint protected**: istehsalda `POST /actuator/loggers` yalnız SRE role-una açıq olsun.
- **Laravel-də**: `spatie/laravel-health` + manual `/ready` route birlikdə işlət. `/up` default qalsın.
- **Pulse + Prometheus**: Pulse daxili UI üçün, Prometheus xarici Grafana üçün. İkisi birlikdə.
- **Telescope production-da qadağan**: `telescope:install` dev-dependency kimi qal. Production-da disk-yaddaş sızıntısı riski var.
- **Build info commit etmə**: `build-info.properties` faylını `.gitignore`-a qoy — CI-da yaradılır, git-dən kənar.

---

## Yekun

Spring Boot Actuator production-da tətbiqi "şüşədən baxmaq" imkanı verən bir paketdir — health, metrics, info, loggers, thread dump, heap dump, DB migrasiya tarixi hamısı bir dependency-dir. Kubernetes probes birinci tərəf dəstəklənir. Custom endpoint yazmaq `@Endpoint` annotation qədər sadədir.

Laravel-də bu funksionallıq parçalıdır: `/up` (boot), `spatie/laravel-health` (real health), Pulse (metrics), Telescope (dev audit), Horizon (queue), `php artisan about` (CLI info). Hər biri yaxşı işləyir, amma "bir endpoint-dən hər şeyi gör" modeli yoxdur.

Seçim tətbiqin mühitindən asılıdır: Kubernetes + Prometheus + enterprise monitoring — Actuator rahat seçimdir. Laravel monolit + Horizon + Sentry — mövcud paketlər 80% işi görür, qalanını manual yazırsan.
