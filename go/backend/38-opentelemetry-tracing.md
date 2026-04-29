# OpenTelemetry Tracing (Senior)

## İcmal

**OpenTelemetry (OTel)** — distributed tracing, metrics, logs üçün vendor-neutral standartdır. Go-da `go.opentelemetry.io/otel` paketi ilə HTTP request-i bir servis-dən digərinə izləmək, performance bottleneck tapmaq, xəta propagation-ı görmək mümkündür. Backend olaraq Jaeger, Tempo, Datadog, Honeycomb işləyir.

## Niyə Vacibdir

- Mikroservis mühitdə request 5-10 servisə keçir — hansında yavaşladı?
- `slog`/`log` trace context olmadan əlaqəsiz log satırları verir
- OTel trace ID ilə bütün log-ları bir request-ə bağlamaq olur
- Zero-code instrumentation: Chi, GORM, Redis, gRPC üçün hazır middleware var

## Əsas Anlayışlar

- **Trace** — bir istifadəçi sorğusunun bütün servislər boyunca tam izi
- **Span** — bir əməliyyatın başlanğıc-son vaxtı (DB sorğusu, HTTP call, cache)
- **SpanContext** — trace ID + span ID; HTTP header-lərlə servislər arasında keçir
- **Propagator** — SpanContext-i header-a yaz/oxu (W3C TraceContext standart)
- **OTLP** — OpenTelemetry Protocol; Jaeger/Tempo OTLP endpoint qəbul edir
- **Attributes** — span-a əlavə edilən key-value metadata (user ID, query, status)
- **Sampler** — hər trace-i yığma; `TraceIDRatioBased(0.1)` = 10% nümunə

## Praktik Baxış

**Tipik OTel stack:**

```
Go App (OTel SDK) → OTLP exporter → Collector (otelcol) → Jaeger/Tempo
```

**Ne vaxt nə istifadə et:**

| Ssenari | Yanaşma |
|---------|---------|
| Local debug | Jaeger all-in-one (docker) |
| Production scale | Grafana Tempo + OTel Collector |
| SaaS | Datadog / Honeycomb / New Relic |
| Logs + Traces birlikdə | Grafana Loki + Tempo |

**Trade-off-lar:**
- OTel overhead: SDK 1-5ms əlavə edir; sampler ilə azaldılır
- Context propagation: hər funksiyaya `ctx` ötürmək lazımdır — bu Go-da artıq normadır
- Collector vs direct export: production-da Collector tövsiyə olunur (retry, batching)

## Nümunələr

### Nümunə 1: Provider qurma

```go
package telemetry

import (
    "context"
    "go.opentelemetry.io/otel"
    "go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc"
    "go.opentelemetry.io/otel/propagation"
    "go.opentelemetry.io/otel/sdk/resource"
    sdktrace "go.opentelemetry.io/otel/sdk/trace"
    semconv "go.opentelemetry.io/otel/semconv/v1.21.0"
)

func InitTracer(ctx context.Context, serviceName, endpoint string) (func(context.Context) error, error) {
    // OTLP exporter — Jaeger/Tempo/Collector-ə gönder
    exporter, err := otlptracegrpc.New(ctx,
        otlptracegrpc.WithEndpoint(endpoint),
        otlptracegrpc.WithInsecure(), // production-da TLS istifadə et
    )
    if err != nil {
        return nil, err
    }

    // Resource — servis haqqında metadata
    res := resource.NewWithAttributes(
        semconv.SchemaURL,
        semconv.ServiceName(serviceName),
        semconv.ServiceVersion("1.0.0"),
    )

    // TracerProvider
    tp := sdktrace.NewTracerProvider(
        sdktrace.WithBatcher(exporter),
        sdktrace.WithResource(res),
        sdktrace.WithSampler(sdktrace.TraceIDRatioBased(0.1)), // 10% sample
    )

    otel.SetTracerProvider(tp)
    otel.SetTextMapPropagator(propagation.NewCompositeTextMapPropagator(
        propagation.TraceContext{},
        propagation.Baggage{},
    ))

    return tp.Shutdown, nil
}
```

### Nümunə 2: HTTP server middleware

```go
import (
    "go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp"
)

func main() {
    shutdown, _ := telemetry.InitTracer(ctx, "order-service", "localhost:4317")
    defer shutdown(ctx)

    mux := http.NewServeMux()
    mux.HandleFunc("/orders", ordersHandler)

    // otelhttp middleware — hər request üçün avtomatik span yaradır
    handler := otelhttp.NewHandler(mux, "order-service",
        otelhttp.WithMessageEvents(otelhttp.ReadEvents, otelhttp.WriteEvents),
    )

    http.ListenAndServe(":8080", handler)
}

func ordersHandler(w http.ResponseWriter, r *http.Request) {
    ctx := r.Context() // span context-i artıq burada var

    // Manual span
    tracer := otel.Tracer("order-service")
    ctx, span := tracer.Start(ctx, "fetchOrders")
    defer span.End()

    orders, err := fetchOrdersFromDB(ctx)
    if err != nil {
        span.RecordError(err)
        span.SetStatus(codes.Error, err.Error())
        http.Error(w, err.Error(), 500)
        return
    }

    span.SetAttributes(
        attribute.Int("orders.count", len(orders)),
    )

    json.NewEncoder(w).Encode(orders)
}
```

### Nümunə 3: Database span-ı

```go
func fetchOrdersFromDB(ctx context.Context) ([]Order, error) {
    tracer := otel.Tracer("order-service")
    ctx, span := tracer.Start(ctx, "db.fetchOrders",
        trace.WithSpanKind(trace.SpanKindClient),
        trace.WithAttributes(
            semconv.DBSystemPostgreSQL,
            semconv.DBStatement("SELECT * FROM orders WHERE user_id = $1"),
        ),
    )
    defer span.End()

    rows, err := db.QueryContext(ctx, "SELECT id, total, status FROM orders WHERE user_id = $1", 1)
    if err != nil {
        span.RecordError(err)
        span.SetStatus(codes.Error, err.Error())
        return nil, err
    }
    defer rows.Close()

    var orders []Order
    for rows.Next() {
        var o Order
        rows.Scan(&o.ID, &o.Total, &o.Status)
        orders = append(orders, o)
    }

    span.SetAttributes(attribute.Int("db.rows_returned", len(orders)))
    return orders, nil
}
```

### Nümunə 4: HTTP client — trace propagation

```go
import (
    "go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp"
)

// OTel-aware HTTP client — trace ID-ni header-ə yazır
var httpClient = &http.Client{
    Transport: otelhttp.NewTransport(http.DefaultTransport),
}

func callPaymentService(ctx context.Context, orderID int) error {
    tracer := otel.Tracer("order-service")
    ctx, span := tracer.Start(ctx, "payment.charge")
    defer span.End()

    req, _ := http.NewRequestWithContext(ctx, "POST",
        "http://payment-service/charge",
        strings.NewReader(fmt.Sprintf(`{"order_id":%d}`, orderID)),
    )

    // otelhttp Transport avtomatik olaraq W3C TraceContext header-ını əlavə edir:
    // traceparent: 00-<trace-id>-<span-id>-01
    resp, err := httpClient.Do(req)
    if err != nil {
        span.RecordError(err)
        return err
    }
    defer resp.Body.Close()

    span.SetAttributes(attribute.Int("http.status_code", resp.StatusCode))
    return nil
}
```

### Nümunə 5: Trace ID-ni log-a bağlamak

```go
import (
    "go.opentelemetry.io/otel/trace"
    "log/slog"
)

func traceLogger(ctx context.Context, msg string, args ...any) {
    spanCtx := trace.SpanFromContext(ctx).SpanContext()
    if spanCtx.IsValid() {
        args = append(args,
            "trace_id", spanCtx.TraceID().String(),
            "span_id", spanCtx.SpanID().String(),
        )
    }
    slog.InfoContext(ctx, msg, args...)
}

// İstifadə:
// traceLogger(ctx, "user created", "user_id", 42)
// → {"msg":"user created","user_id":42,"trace_id":"abc123","span_id":"def456"}
```

## Praktik Tapşırıqlar

1. **Local Jaeger:** `docker run -p 16686:16686 -p 4317:4317 jaegertracing/all-in-one` − HTTP server qur, trace-ləri Jaeger UI-da gör
2. **DB spans:** DB sorğuları üçün span yaz; `db.statement` attribute-u əlavə et
3. **Trace propagation:** İki Go service qur; birindən digərinə HTTP call et; trace ID eyni olsun
4. **Sampler:** `TraceIDRatioBased(0.01)` ilə 1% sampling qur; yüksək trafik altında test et

## PHP ilə Müqayisə

```
PHP/Laravel              →  Go OTel
────────────────────────────────────────
Datadog APM (agent)      →  OTel SDK (no agent)
Telescope request log    →  Jaeger trace timeline
dd-trace-php (auto)      →  otelhttp middleware (auto)
manual spans yoxdur      →  tracer.Start(ctx, "name")
```

PHP-də APM agent-lər kodu avtomatik instrument edir. Go-da SDK əl ilə inteqrasiya tələb edir, amma context propagation daha açıqdır.

## Əlaqəli Mövzular

- [01-http-server](01-http-server.md) — otelhttp middleware
- [05-database](05-database.md) — DB span-ları
- [17-graceful-shutdown](17-graceful-shutdown.md) — TracerProvider.Shutdown
- [../core/28-context](../core/28-context.md) — context-first API
- [../advanced/24-monitoring-and-observability](../advanced/24-monitoring-and-observability.md) — Prometheus + OTel
