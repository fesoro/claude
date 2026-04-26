# 99 — Observability Architecture — Production Monitoring

> **Seviyye:** Lead ⭐⭐⭐⭐

## Mündəricat
1. [Observability üç sütunu](#observability-üç-sütunu)
2. [Structured Logging](#structured-logging)
3. [MDC — Correlation ID](#mdc--correlation-id)
4. [Micrometer Metrics](#micrometer-metrics)
5. [OpenTelemetry Tracing](#opentelemetry-tracing)
6. [Spring Boot Actuator](#spring-boot-actuator)
7. [Production Stack](#production-stack)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Observability üç sütunu

```
Observability = Logs + Metrics + Traces

Logs:   "Nə baş verdi?"     → Event-lər, error-lar, audit
Metrics:"Nə qədər baş verdi?" → Sayğaclar, histogramlar, gauges
Traces: "Harada baş verdi?"  → Request-in yolu service-dən service-ə
```

```
Problem: "Database cavab vermir"

Logs:    ERROR o.h.engine.jdbc — Connection timeout (nə baş verdi)
Metrics: db.pool.pending=48, db.pool.size=10 (nə qədər kötüdür)
Traces:  UserService→OrderService→DB (harada darboğazdır)
```

---

## Structured Logging

Plain text log oxumaq çətin olur. Structured JSON log — query, filter, alert üçün əlverişlidir.

### Logback JSON konfiqurasiya:

```xml
<!-- pom.xml -->
<dependency>
    <groupId>net.logstash.logback</groupId>
    <artifactId>logstash-logback-encoder</artifactId>
    <version>7.4</version>
</dependency>
```

```xml
<!-- src/main/resources/logback-spring.xml -->
<configuration>
    <!-- Dev: plain text -->
    <springProfile name="!prod">
        <appender name="CONSOLE" class="ch.qos.logback.core.ConsoleAppender">
            <encoder>
                <pattern>%d{HH:mm:ss} [%thread] %-5level %logger{36} - %msg%n</pattern>
            </encoder>
        </appender>
        <root level="INFO"><appender-ref ref="CONSOLE"/></root>
    </springProfile>

    <!-- Prod: JSON -->
    <springProfile name="prod">
        <appender name="JSON_CONSOLE" class="ch.qos.logback.core.ConsoleAppender">
            <encoder class="net.logstash.logback.encoder.LogstashEncoder">
                <includeMdc>true</includeMdc>
                <customFields>{"app":"my-service","env":"prod"}</customFields>
            </encoder>
        </appender>
        <root level="INFO"><appender-ref ref="JSON_CONSOLE"/></root>
    </springProfile>
</configuration>
```

```json
// JSON log output:
{
  "timestamp": "2024-01-15T10:30:00.123Z",
  "level": "INFO",
  "logger": "com.example.OrderService",
  "message": "Order created",
  "thread": "virtual-thread-42",
  "app": "my-service",
  "env": "prod",
  "traceId": "a1b2c3d4e5f6",
  "spanId": "f1e2d3c4",
  "userId": "user-123",
  "orderId": "order-456",
  "duration_ms": 45
}
```

### Logging best practices:

```java
@Slf4j
@Service
@RequiredArgsConstructor
public class OrderService {

    public Order createOrder(CreateOrderRequest req) {
        // Structured log — key-value pairs:
        log.info("Creating order userId={} itemCount={}", req.userId(), req.items().size());

        Order order = orderRepo.save(buildOrder(req));

        // Timing məlumatı:
        log.info("Order created orderId={} userId={} totalAmount={}",
            order.getId(), req.userId(), order.getTotalAmount());

        return order;
    }

    // Exception logging:
    public void processPayment(Long orderId) {
        try {
            paymentGateway.charge(orderId);
        } catch (PaymentException e) {
            // Exception message + stack trace: birlikdə
            log.error("Payment failed orderId={} errorCode={}",
                orderId, e.getErrorCode(), e); // ← son argument exception
        }
    }
}
```

**Qaydalar:**
- `log.debug()` — development, verbose
- `log.info()` — business events (order created, user registered)
- `log.warn()` — qeyri-adi amma kritik olmayan (retry, slow query)
- `log.error()` — xəta (həmişə exception-u da əlavə et)
- Personal data log etmə (password, card number, token)

---

## MDC — Correlation ID

MDC (Mapped Diagnostic Context) — hər request üçün kontekst məlumatı. Log-larda bir request-in bütün sətrlərini bir yerdə görməyə imkan verir.

```java
// Filter ilə MDC set etmək:
@Component
@Order(Ordered.HIGHEST_PRECEDENCE)
public class CorrelationFilter implements Filter {

    @Override
    public void doFilter(ServletRequest req, ServletResponse res, FilterChain chain)
            throws IOException, ServletException {

        HttpServletRequest httpReq = (HttpServletRequest) req;

        // Upstream-dən gəlibsə saxla, yoxdursa yarat:
        String traceId = Optional.ofNullable(httpReq.getHeader("X-Trace-Id"))
            .orElse(UUID.randomUUID().toString());

        // MDC-yə əlavə et — bütün log sətrlərında görünəcək:
        MDC.put("traceId", traceId);
        MDC.put("userId", extractUserId(httpReq));
        MDC.put("path", httpReq.getRequestURI());

        // Response header-ına da əlavə et:
        ((HttpServletResponse) res).setHeader("X-Trace-Id", traceId);

        try {
            chain.doFilter(req, res);
        } finally {
            MDC.clear(); // Thread pool reuse → temizle
        }
    }
}

// Log output — hər sətirdə traceId:
// {"traceId":"abc123","userId":"user-42","path":"/api/orders","message":"Creating order"}
// {"traceId":"abc123","userId":"user-42","path":"/api/orders","message":"Order saved orderId=789"}
// {"traceId":"abc123","userId":"user-42","path":"/api/orders","message":"Payment initiated"}
```

### Virtual Threads ilə MDC:

```java
// Virtual threads thread-local inherits etmir, amma Spring 6 bunu həll edir.
// spring.threads.virtual.enabled=true → Spring MDC-ni propagate edir.

// Manual propagate (lazım olduqda):
Map<String, String> mdcContext = MDC.getCopyOfContextMap();
CompletableFuture.runAsync(() -> {
    if (mdcContext != null) MDC.setContextMap(mdcContext);
    try {
        asyncOperation();
    } finally {
        MDC.clear();
    }
}, virtualThreadExecutor);
```

---

## Micrometer Metrics

Spring Boot Actuator + Micrometer — metrics toplama abstraction layer-i.

```java
@Service
@RequiredArgsConstructor
public class OrderService {

    private final MeterRegistry meterRegistry;
    private final OrderRepository orderRepo;

    // Counter — toplam say:
    public Order createOrder(CreateOrderRequest req) {
        Order order = orderRepo.save(buildOrder(req));

        meterRegistry.counter("orders.created",
            "status", "success",
            "userId", req.userId().toString()
        ).increment();

        return order;
    }

    // Timer — əməliyyat vaxtı:
    public void processPayment(Long orderId) {
        Timer.Sample sample = Timer.start(meterRegistry);
        try {
            paymentGateway.charge(orderId);
            sample.stop(meterRegistry.timer("payment.processing",
                "result", "success"));
        } catch (Exception e) {
            sample.stop(meterRegistry.timer("payment.processing",
                "result", "failure"));
            throw e;
        }
    }

    // Gauge — current state:
    @PostConstruct
    public void registerGauge() {
        Gauge.builder("orders.pending.count", orderRepo, repo -> repo.countByStatus("PENDING"))
             .register(meterRegistry);
    }
}

// @Timed annotation (daha qısa):
@Timed(value = "user.creation", description = "Time to create a user")
public UserDto createUser(CreateUserRequest req) { ... }
```

### Custom metrics endpoint:

```yaml
# application.yml
management:
  endpoints:
    web:
      exposure:
        include: health,metrics,prometheus
  metrics:
    export:
      prometheus:
        enabled: true
```

```bash
# Prometheus format:
GET /actuator/prometheus

# orders_created_total{status="success"} 1234.0
# payment_processing_seconds_count{result="success"} 5678.0
# payment_processing_seconds_sum{result="success"} 234.567
# orders_pending_count 42.0
```

---

## OpenTelemetry Tracing

Distributed tracing — microservice-lər arası request-in izlənməsi.

```xml
<!-- pom.xml -->
<dependency>
    <groupId>io.micrometer</groupId>
    <artifactId>micrometer-tracing-bridge-otel</artifactId>
</dependency>
<dependency>
    <groupId>io.opentelemetry</groupId>
    <artifactId>opentelemetry-exporter-otlp</artifactId>
</dependency>
```

```yaml
# application.yml
management:
  tracing:
    sampling:
      probability: 1.0  # prod-da 0.1 (10%)
  otlp:
    tracing:
      endpoint: http://otel-collector:4318/v1/traces
```

```java
// Span-lar avtomatik yaradılır: HTTP request, DB query, async method
// Manual span:
@Service
@RequiredArgsConstructor
public class OrderService {

    private final Tracer tracer;

    public Order createOrder(CreateOrderRequest req) {
        Span span = tracer.nextSpan().name("order.create").start();
        try (Tracer.SpanInScope scope = tracer.withSpan(span)) {
            span.tag("userId", req.userId().toString());
            span.tag("itemCount", String.valueOf(req.items().size()));

            Order order = orderRepo.save(buildOrder(req));
            span.tag("orderId", order.getId().toString());
            return order;
        } catch (Exception e) {
            span.error(e);
            throw e;
        } finally {
            span.end();
        }
    }
}
```

```
Trace görünüşü (Jaeger/Zipkin):
  Trace abc123 — 245ms
  ├── OrderController.createOrder — 240ms
  │   ├── InventoryService.check — 15ms
  │   │   └── DB: SELECT inventory — 12ms
  │   ├── OrderRepository.save — 25ms
  │   │   └── DB: INSERT orders — 22ms
  │   └── PaymentService.charge — 180ms [External HTTP]
  └── NotificationService.send — 5ms [Async]
```

---

## Spring Boot Actuator

```yaml
# application.yml
management:
  endpoints:
    web:
      exposure:
        include: health,info,metrics,prometheus,loggers,threaddump,heapdump
  endpoint:
    health:
      show-details: when-authorized  # prod-da sensitive məlumat gizlət
      probes:
        enabled: true  # K8s liveness/readiness
  info:
    git:
      enabled: true    # git commit info
    build:
      enabled: true
```

```java
// Custom health indicator:
@Component
public class PaymentGatewayHealthIndicator implements HealthIndicator {

    @Override
    public Health health() {
        try {
            boolean isUp = paymentGateway.ping();
            return isUp
                ? Health.up().withDetail("gateway", "Responsive").build()
                : Health.down().withDetail("gateway", "Not responding").build();
        } catch (Exception e) {
            return Health.down(e).build();
        }
    }
}

// GET /actuator/health response:
// {
//   "status": "UP",
//   "components": {
//     "db": {"status": "UP"},
//     "redis": {"status": "UP"},
//     "paymentGateway": {"status": "UP", "details": {"gateway": "Responsive"}},
//     "diskSpace": {"status": "UP"}
//   }
// }
```

---

## Production Stack

```
Spring Boot App
    ↓
[OpenTelemetry Collector]
    ↓           ↓           ↓
[Jaeger]    [Prometheus]  [Loki/ELK]
(Traces)    (Metrics)     (Logs)
    ↓           ↓           ↓
          [Grafana Dashboard]
               ↓
         [AlertManager]
               ↓
      [PagerDuty / Slack]
```

### Docker Compose local setup:

```yaml
services:
  app:
    environment:
      MANAGEMENT_OTLP_TRACING_ENDPOINT: http://otel:4318/v1/traces
      MANAGEMENT_TRACING_SAMPLING_PROBABILITY: "1.0"

  otel-collector:
    image: otel/opentelemetry-collector:latest
    # Traces → Jaeger, Metrics → Prometheus, Logs → Loki

  jaeger:
    image: jaegertracing/all-in-one:latest
    ports: ["16686:16686"]  # UI

  prometheus:
    image: prom/prometheus:latest
    ports: ["9090:9090"]

  grafana:
    image: grafana/grafana:latest
    ports: ["3000:3000"]
```

---

## İntervyu Sualları

**S: Observability-nin üç sütunu nədir, fərqləri?**
C: Logs — nə baş verdi (event-lər, error-lar); Metrics — nə qədər baş verdi (sayğaclar, response time); Traces — harada baş verdi (service-dən service-ə request yolu). Hamısı birlikdə production problemi debug etməyə imkan verir.

**S: MDC nədir, niyə lazımdır?**
C: Mapped Diagnostic Context — thread-local key-value store. Correlation ID-ni (traceId, userId) bir dəfə set edib, log sətrlərinin hamısında avtomatik görünməsini təmin edir. Bir request-in bütün log-larını log sistemdə filter etmək mümkün olur.

**S: Micrometer Prometheus ilə nə əlaqəsi var?**
C: Micrometer — metrics abstraction layer. Prometheus, Datadog, CloudWatch kimi sistemlərə export edə bilir. Prometheus endpoint: `/actuator/prometheus`. Grafana Prometheus-u query edir, dashboard göstərir.

**S: Distributed tracing nə üçündür?**
C: Microservice-lər arası request-in hər addımını izləmək üçün. "Bu endpoint niyə yavaşdır?" sorusuna cavab: ServiceA 5ms, ServiceB 200ms, DB query 180ms — darboğaz birbaşa görünür. Jaeger/Zipkin/Grafana Tempo bu trace-ləri saxlayır və vizuallaşdırır.

**S: Production-da sampling probability neçə olmalıdır?**
C: 100% (1.0) dev/test-də. Prod-da 10% (0.1) — çox traffic olduqda bütün trace-ləri saxlamaq bahalıdır. Yüksək latency-li request-lər üçün adaptive sampling (threshold-based) da mövcuddur.
