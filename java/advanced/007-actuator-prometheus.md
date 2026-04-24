# 007 — Spring Actuator + Prometheus — Geniş İzah
**Səviyyə:** Ekspert


## Mündəricat
1. [Spring Actuator nədir?](#spring-actuator-nədir)
2. [Actuator endpoint-ləri](#actuator-endpoint-ləri)
3. [Micrometer + Prometheus](#micrometer--prometheus)
4. [Custom metrics](#custom-metrics)
5. [Grafana dashboard](#grafana-dashboard)
6. [Health checks](#health-checks)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Spring Actuator nədir?

**Spring Actuator** — production-ready feature-lar: health check, metrics, info, env, thread dump, log level dəyişmə. `/actuator/*` endpoint-ləri vasitəsilə əlçatandır.

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

---

## Actuator endpoint-ləri

```yaml
management:
  endpoints:
    web:
      exposure:
        include: health,info,metrics,prometheus,loggers,env,threaddump,heapdump
        # include: "*"  # Bütün endpoint-lər (production-da diqqətli ol!)
      base-path: /actuator
  endpoint:
    health:
      show-details: when_authorized  # always/when_authorized/never
      show-components: always
    info:
      enabled: true
  info:
    env:
      enabled: true
    git:
      enabled: true
      mode: full
```

```
GET /actuator/health         → {status: "UP"}
GET /actuator/info           → app version, build info
GET /actuator/metrics        → metric adları siyahısı
GET /actuator/metrics/jvm.memory.used → xüsusi metric
GET /actuator/prometheus     → Prometheus format metrics
GET /actuator/loggers        → log level-lər
POST /actuator/loggers/{logger} → log level dəyişdir (runtime)
GET /actuator/env            → environment properties
GET /actuator/threaddump     → thread dump
GET /actuator/heapdump       → heap dump (JVM)
POST /actuator/shutdown      → app-ı dayandır (default deaktiv)
```

```yaml
# application.yml
info:
  app:
    name: Order Service
    version: "@project.version@"
    description: "@project.description@"
  build:
    time: "@maven.build.timestamp@"
```

---

## Micrometer + Prometheus

```yaml
management:
  metrics:
    distribution:
      percentiles-histogram:
        http.server.requests: true  # Latency histogram
      percentiles:
        http.server.requests: 0.5, 0.9, 0.95, 0.99
    tags:
      application: ${spring.application.name}
      environment: ${spring.profiles.active:default}
```

**Prometheus konfiqurasiyası** (`prometheus.yml`):
```yaml
scrape_configs:
  - job_name: 'order-service'
    scrape_interval: 15s
    metrics_path: '/actuator/prometheus'
    static_configs:
      - targets: ['order-service:8080']
    # Kubernetes-də service discovery:
    # kubernetes_sd_configs:
    #   - role: pod
```

---

## Custom metrics

```java
@Service
public class OrderMetricsService {

    private final MeterRegistry meterRegistry;

    // Counter — sadə sayğac
    private final Counter orderCreatedCounter;
    private final Counter orderFailedCounter;

    // Timer — müddəti ölçmək
    private final Timer orderProcessingTimer;

    // Gauge — cari dəyər
    private AtomicInteger activeOrdersGauge = new AtomicInteger(0);

    public OrderMetricsService(MeterRegistry meterRegistry) {
        this.meterRegistry = meterRegistry;

        this.orderCreatedCounter = Counter.builder("orders.created")
            .description("Yaradılan sifarişlər sayı")
            .tag("environment", "production")
            .register(meterRegistry);

        this.orderFailedCounter = Counter.builder("orders.failed")
            .description("Uğursuz sifarişlər sayı")
            .register(meterRegistry);

        this.orderProcessingTimer = Timer.builder("orders.processing.time")
            .description("Sifariş emal müddəti")
            .publishPercentiles(0.5, 0.95, 0.99)
            .publishPercentileHistogram()
            .register(meterRegistry);

        Gauge.builder("orders.active", activeOrdersGauge, AtomicInteger::get)
            .description("Aktiv sifarişlər sayı")
            .register(meterRegistry);
    }

    public Order processOrder(OrderRequest request) {
        // Timer ilə ölçmə
        return orderProcessingTimer.record(() -> {
            try {
                activeOrdersGauge.incrementAndGet();
                Order order = doProcessOrder(request);
                orderCreatedCounter.increment();
                return order;
            } catch (Exception e) {
                orderFailedCounter.increment();
                throw e;
            } finally {
                activeOrdersGauge.decrementAndGet();
            }
        });
    }

    // Tag ilə daha ətraflı metric
    public void recordOrderByStatus(OrderStatus status) {
        Counter.builder("orders.by.status")
            .tag("status", status.name())
            .tag("service", "order-service")
            .register(meterRegistry)
            .increment();
    }
}

// @Timed annotasiyası ilə sadə timer
@Component
public class OrderController {

    @Timed(value = "api.orders.get",
           description = "Order API sorğu müddəti",
           percentiles = {0.5, 0.95, 0.99})
    @GetMapping("/api/orders/{id}")
    public Order getOrder(@PathVariable Long id) {
        return orderService.findById(id);
    }
}

// Distribution summary — ölçü paylanması
@Bean
public DistributionSummary orderValueSummary(MeterRegistry registry) {
    return DistributionSummary.builder("orders.value")
        .description("Sifariş məbləği paylanması")
        .baseUnit("AZN")
        .publishPercentiles(0.5, 0.75, 0.95)
        .register(registry);
}
```

---

## Grafana dashboard

```
Prometheus → collect → Grafana → visualize

PromQL sorğu nümunələri:

# HTTP request rate (son 5 dəqiqə)
rate(http_server_requests_seconds_count[5m])

# Ortalama latency
rate(http_server_requests_seconds_sum[5m])
/ rate(http_server_requests_seconds_count[5m])

# 95-ci persentil latency
histogram_quantile(0.95,
  rate(http_server_requests_seconds_bucket[5m]))

# Xəta faizi
rate(http_server_requests_seconds_count{status=~"5.."}[5m])
/ rate(http_server_requests_seconds_count[5m]) * 100

# JVM heap usage
jvm_memory_used_bytes{area="heap"}
/ jvm_memory_max_bytes{area="heap"} * 100

# Active threads
jvm_threads_live_threads

# Order created rate
rate(orders_created_total[5m])
```

---

## Health checks

```java
// Custom health indicator
@Component
public class ExternalApiHealthIndicator implements HealthIndicator {

    private final ExternalApiClient apiClient;

    @Override
    public Health health() {
        try {
            boolean available = apiClient.ping();
            if (available) {
                return Health.up()
                    .withDetail("url", apiClient.getBaseUrl())
                    .withDetail("responseTime", "< 100ms")
                    .build();
            }
            return Health.down()
                .withDetail("url", apiClient.getBaseUrl())
                .withDetail("reason", "ping failed")
                .build();
        } catch (Exception e) {
            return Health.down()
                .withException(e)
                .build();
        }
    }
}

// Composite health
// GET /actuator/health
// {
//   "status": "UP",
//   "components": {
//     "db": {"status": "UP", "details": {...}},
//     "diskSpace": {"status": "UP", "details": {...}},
//     "externalApi": {"status": "UP", "details": {...}},
//     "redis": {"status": "UP", "details": {...}}
//   }
// }
```

**Kubernetes liveness/readiness:**
```yaml
management:
  endpoint:
    health:
      probes:
        enabled: true
  health:
    livenessstate:
      enabled: true
    readinessstate:
      enabled: true

# Kubernetes-də:
# livenessProbe:
#   httpGet:
#     path: /actuator/health/liveness
#     port: 8080
# readinessProbe:
#   httpGet:
#     path: /actuator/health/readiness
#     port: 8080
```

---

## İntervyu Sualları

### 1. Liveness vs Readiness probe fərqi nədir?
**Cavab:** `Liveness` — app sağlam işləyirmi? Yox isə Kubernetes pod-u restart edir. `Readiness` — app traffic qəbul etməyə hazırdırmı? Yox isə Kubernetes həmin pod-a traffic göndərmir (amma restart etmir). DB migrasiyası zamanı readiness DOWN, app restart lazım olduqda liveness DOWN edilir.

### 2. Micrometer nədir?
**Cavab:** JVM-based application-lar üçün metrics façade-i. Vendor-neutral API — Prometheus, Datadog, InfluxDB, CloudWatch kimi fərqli backend-lərə eyni kod ilə metric göndərmək mümkündür. Spring Boot auto-configuration vasitəsilə avtomatik konfiqurasiya olunur.

### 3. Counter vs Timer vs Gauge fərqi nədir?
**Cavab:** `Counter` — artırılan sayğac (sifariş sayı, xəta sayı). `Timer` — müddəti ölçür, count + sum + histogram. `Gauge` — cari dəyər (aktiv connection sayı, queue ölçüsü). `DistributionSummary` — ölçü paylanması (HTTP response ölçüsü, ödəniş məbləği).

### 4. Production-da niyə bütün actuator endpoint-ləri açmaq olmaz?
**Cavab:** `/actuator/env` — gizli konfiqurasiya dəyərləri (DB password, API key) sızdıra bilər. `/actuator/heapdump` — həssas data yaddaşda ola bilər. `/actuator/shutdown` — app-ı dayandırır. Həll: Spring Security ilə actuator endpoint-lərini qoru, yalnız lazımlı endpoint-ləri expose et.

### 5. PromQL-də `rate()` nə edir?
**Cavab:** Monotonic artım sayğacının (counter) saniyəlik artım sürətini hesablayır. `rate(http_requests_total[5m])` — son 5 dəqiqədəki saniyəlik request sayı. Counter restart olduqda (app restart) sıfırlanır — `rate()` bunu idarə edir. Latency üçün `histogram_quantile()` ilə percentile hesablanır.

*Son yenilənmə: 2026-04-10*
