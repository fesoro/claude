# 07 — Distributed Tracing: Micrometer Tracing + OpenTelemetry — Geniş İzah

> **Seviyye:** Expert ⭐⭐⭐⭐

> **Qeyd:** Spring Cloud Sleuth Spring Boot 3.x-də deprecated edilib. Yerinə Micrometer Tracing (Brave backend) yaxud OpenTelemetry SDK istifadə olunur.


## Mündəricat
1. [Distributed Tracing nədir?](#distributed-tracing-nədir)
2. [Micrometer Tracing quraşdırması](#micrometer-tracing-quraşdırması)
3. [Trace konteksti propagasiyası](#trace-konteksti-propagasiyası)
4. [Custom spans](#custom-spans)
5. [Zipkin UI](#zipkin-ui)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Distributed Tracing nədir?

**Distributed Tracing** — microservice mühitdə bir request-in bütün service-lər arasındakı yolunu izləmək. Hər request `traceId` alır, hər service çağırışı `spanId` alır.

```
Client → Gateway (traceId: abc123, spanId: 1)
           ↓
    Order Service (traceId: abc123, spanId: 2)
           ↓
    Inventory Service (traceId: abc123, spanId: 3)
           ↓
    Payment Service (traceId: abc123, spanId: 4)

Zipkin UI-də görürsən:
abc123: Gateway(50ms) → Order(200ms) → Inventory(100ms) → Payment(300ms) = 650ms total
```

---

## Micrometer Tracing quraşdırması

Spring Boot 3.x-də Sleuth yerinə **Micrometer Tracing** + **Zipkin Reporter** istifadə olunur:

```xml
<dependency>
    <groupId>io.micrometer</groupId>
    <artifactId>micrometer-tracing-bridge-brave</artifactId>
</dependency>
<dependency>
    <groupId>io.zipkin.reporter2</groupId>
    <artifactId>zipkin-reporter-brave</artifactId>
</dependency>
<dependency>
    <groupId>io.github.openfeign</groupId>
    <artifactId>feign-micrometer</artifactId>
</dependency>
```

```yaml
# application.yml
management:
  tracing:
    sampling:
      probability: 1.0   # 100% sampling (dev), 0.1 = 10% (prod)
  zipkin:
    tracing:
      endpoint: http://localhost:9411/api/v2/spans

spring:
  application:
    name: order-service   # Zipkin-də service adı
  sleuth:
    sampler:
      probability: 1.0
```

```java
// Log-da trace ID avtomatik görünür
// Format: [order-service,traceId,spanId]
// 2026-04-10 10:30:00 INFO [order-service,abc123,def456] OrderService - Order yaradıldı

// logback-spring.xml konfiqurasiyası
/*
<pattern>%d{yyyy-MM-dd HH:mm:ss} %-5level [%X{traceId},%X{spanId}] %logger{36} - %msg%n</pattern>
*/
```

---

## Trace konteksti propagasiyası

```java
// HTTP header-lar vasitəsilə avtomatik propagasiya
// RestTemplate, WebClient, OpenFeign — avtomatik

// RestTemplate istifadə edərkən:
@Configuration
public class RestTemplateConfig {

    @Bean
    public RestTemplate restTemplate(RestTemplateBuilder builder) {
        // Tracing interceptor avtomatik əlavə olunur (Micrometer)
        return builder.build();
    }
}

// WebClient:
@Bean
public WebClient webClient(WebClient.Builder builder) {
    return builder
        .baseUrl("http://inventory-service")
        .build(); // Tracing context avtomatik propagate olunur
}

// OpenFeign:
@FeignClient(name = "inventory-service")
public interface InventoryClient {
    @GetMapping("/api/inventory/{productId}")
    Inventory getInventory(@PathVariable Long productId);
    // traceId/spanId header-ları avtomatik göndərilir
}
```

**Header-lar:**
```
Zipkin/Brave (B3 format):
  X-B3-TraceId: abc123
  X-B3-SpanId: def456
  X-B3-ParentSpanId: 789abc
  X-B3-Sampled: 1

W3C Trace Context (modern):
  traceparent: 00-abc123-def456-01
```

---

## Custom spans

```java
@Service
public class OrderService {

    private final Tracer tracer;

    // Custom span — metodun özü span-a sarınır
    @NewSpan("processOrder")
    public Order processOrder(OrderRequest request) {
        // Span avtomatik başlayır və bitir
        return createOrder(request);
    }

    // Manual span
    @Transactional
    public Order createOrderManual(OrderRequest request) {
        Span span = tracer.nextSpan().name("order.creation");

        try (Tracer.SpanInScope ws = tracer.withSpan(span.start())) {
            // Tag əlavə et
            span.tag("order.type", request.getType());
            span.tag("user.id", request.getUserId().toString());
            span.tag("item.count", String.valueOf(request.getItems().size()));

            // Event əlavə et
            span.event("validation.started");
            validateOrder(request);
            span.event("validation.completed");

            Order order = orderRepository.save(buildOrder(request));
            span.tag("order.id", order.getId().toString());

            return order;
        } catch (Exception e) {
            span.error(e);
            throw e;
        } finally {
            span.end();
        }
    }

    // @SpanTag — parametri tag kimi əlavə et
    @NewSpan
    public Order getOrder(@SpanTag("order.id") Long orderId) {
        return orderRepository.findById(orderId).orElseThrow();
    }
}

// Tracer Baggage — span-lar arasında məlumat ötürmə
@Service
public class CorrelationService {

    private final BaggageField userIdField = BaggageField.create("userId");
    private final BaggageField tenantIdField = BaggageField.create("tenantId");

    public void setContextForRequest(String userId, String tenantId) {
        userIdField.updateValue(userId);
        tenantIdField.updateValue(tenantId);
        // Bu dəyərlər bütün child span-lara propagate olur
    }

    public String getUserIdFromContext() {
        return userIdField.getValue();
    }
}
```

---

## Zipkin UI

```yaml
# Docker ilə Zipkin başlatmaq
# docker run -d -p 9411:9411 openzipkin/zipkin

# Zipkin UI: http://localhost:9411
```

**Zipkin UI-də nə görürsən:**
```
Service graph → service-lər arasındakı asılılıqlar
Traces → konkret request izləri, latency breakdown
Dependencies → hansı service hansına çağırır

Trace detail:
  traceId: abc123
  Duration: 650ms
  Spans:
    gateway        [0ms - 50ms]   50ms
    order-service  [50ms - 250ms] 200ms
    inventory-svc  [250ms - 350ms] 100ms
    payment-svc    [350ms - 650ms] 300ms  ← ən uzun
```

```java
// Prometheus + Grafana Tempo ilə inteqrasiya
management:
  zipkin:
    tracing:
      endpoint: http://tempo:9411/api/v2/spans  # Grafana Tempo
  tracing:
    sampling:
      probability: 0.1  # Production-da 10%
```

---

## OpenTelemetry (OTEL) Backend

Micrometer Tracing — vendor-neutral façade. Brave (Zipkin) əvəzinə OTEL SDK backend-ini seçmək mümkündür:

```xml
<!-- Brave əvəzinə OTEL SDK -->
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
# application.yml — OTEL Collector-a göndər
management:
  tracing:
    sampling:
      probability: 0.1    # Production-da 10%
  otlp:
    tracing:
      endpoint: http://otel-collector:4318/v1/traces
```

```
OTEL Architecture:
  Spring Boot → OTEL SDK → OTEL Collector → Jaeger / Grafana Tempo
                                          ↘ Datadog / Dynatrace
                                          ↘ AWS X-Ray

Üstünlükləri:
  → Vendor-neutral: collector config dəyişib, app code dəyişmir
  → Metrics + Traces + Logs eyni pipeline-da
  → Sampling OTEL Collector səviyyəsində konfiqurasiya olur
```

### OTEL Collector konfiqurasiyası

```yaml
# otel-collector.yaml
receivers:
  otlp:
    protocols:
      http:
        endpoint: 0.0.0.0:4318
      grpc:
        endpoint: 0.0.0.0:4317

processors:
  batch:
    timeout: 1s
    send_batch_size: 1024

  # Tail-based sampling — xətalı trace-ləri tam saxla
  tail_sampling:
    decision_wait: 10s
    policies:
      - name: errors-policy
        type: status_code
        status_code: {status_codes: [ERROR]}
      - name: slow-policy
        type: latency
        latency: {threshold_ms: 1000}       # 1s-dən uzun
      - name: probabilistic-policy
        type: probabilistic
        probabilistic: {sampling_percentage: 5}  # Qalanın 5%-i

exporters:
  jaeger:
    endpoint: jaeger:14250
  otlp/tempo:
    endpoint: tempo:4317

service:
  pipelines:
    traces:
      receivers: [otlp]
      processors: [batch, tail_sampling]
      exporters: [jaeger, otlp/tempo]
```

### Head-based vs Tail-based Sampling

```
Head-based sampling (Brave default):
  → Request başlayanda qərar verilir (random %)
  → Sadə, aşağı overhead
  → Problem: xətalı trace-lər şansa görə atıla bilər

Tail-based sampling (OTEL Collector):
  → Bütün trace tamamlandıqdan sonra qərar verilir
  → Xətalı / yavaş trace-lər həmişə saxlanır
  → Normal trace-lərin yalnız X%-i saxlanır
  → Overhead yüksəkdir (collector-da buffer lazımdır)

Tövsiyə:
  Development: 100% sampling
  Staging: 10-20% head-based
  Production: tail-based (xəta + yavaş: 100%, normal: 1-5%)
```

---

## İntervyu Sualları

### 1. TraceId vs SpanId fərqi nədir?
**Cavab:** `TraceId` — bütün request zənciri üçün unikal ID (başdan sona). `SpanId` — hər ayrı service call-u yaxud əməliyyat üçün unikal ID. Bir trace bir neçə span ehtiva edir. Parent-child hierarxiya: A service B-ni çağırırsa, B-nin spanId-si A-nın spanId-sinə bağlıdır (parentSpanId).

### 2. Sampling probability nədir?
**Cavab:** Tracing data-sının nə qədər hissəsinin Zipkin-ə göndəriləcəyi. `1.0` = 100% (bütün sorğular), `0.1` = 10% (hər 10 sorğudan 1-i). Production-da 100% sampling çox data yaradır, performans düşür. Adətən 1-10% yetərlidir. Head-based (random) yaxud tail-based (xətalı olanlar) sampling strategiyaları var.

### 3. Micrometer Tracing (Spring Boot 3) ilə Sleuth (Spring Boot 2) fərqi?
**Cavab:** Spring Boot 3-dən etibarən Sleuth deprecated-dir. Yerinə Micrometer Tracing inteqrasiyası gəldi. Micrometer vendor-neutral façade-dir — Brave (Zipkin), OpenTelemetry kimi backend-lərə switch edə bilərsiniz. Konfigurasiya `management.tracing.*` altında.

### 4. Custom span nə zaman lazımdır?
**Cavab:** Metod çağırışlarından əlavə ayrıca izlənməli addımlar olduqda — məsələn, 3rd party API call, uzun hesablama, validation mərhələsi. `@NewSpan` ilə avtomatik, yaxud `Tracer.nextSpan()` ilə manual. Tag-lar (`span.tag()`) axtarış üçün metadata əlavə edir.

### 5. B3 vs W3C Trace Context fərqi nədir?
**Cavab:** `B3` (Binary/JSON) — Zipkin-in orijinal header formatı (`X-B3-TraceId`). `W3C Trace Context` — IETF standartı (`traceparent` header) — daha modern, cross-vendor uyğunluğu. Spring Boot Micrometer hər ikisini dəstəkləyir. Yeni sistemlər W3C formatını tövsiyə edir.

*Son yenilənmə: 2026-04-10*
