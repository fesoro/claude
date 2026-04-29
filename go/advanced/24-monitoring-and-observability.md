# Monitoring and Observability (Architect)

## İcmal

Monitoring — sistemin nə qədər sağlam olduğunu ölçmək; Observability — niyə səhv olduğunu anlamaq deməkdir. Observability-nin 3 sütunu var: **Metrics** (nə qədər?), **Logs** (nə baş verdi?), **Traces** (haradan haraya?).

Go ekosistemi bu 3 sütun üçün güclü standart alətlər təqdim edir: `prometheus/client_golang`, `log/slog` (Go 1.21+), `go.opentelemetry.io/otel`. Architect səviyyəsində yalnız alətləri bilmək yetərli deyil — SLI/SLO müəyyən etmək, alert strategiyası qurmaq, distributed tracing arxitekturası dizayn etmək lazımdır.

## Niyə Vacibdir

- Production-da problem yalnız müştəri şikayət etdikdə bilinir — bu qəbulolunmazdır
- Metrics olmadan capacity planning etmək mümkün deyil
- Distributed system-də log axtarmaq — trace ID olmadan iynə dənizdə axtarmağa bənzəyir
- SLO pozulması → oncall alert → düzgün qurulmuş dashboard → sürətli RCA (Root Cause Analysis)
- Kubernetes liveness/readiness probe-lar health endpoint-dən asılıdır

## Əsas Anlayışlar

**Prometheus metrik tipləri:**
- `Counter`: yalnız artan (request sayı, xəta sayı) — `Rate()` ilə istifadə et
- `Gauge`: artıb-azalan (aktiv bağlantı, yaddaş istifadəsi)
- `Histogram`: dəyərlərin paylanması (latency) — bucket-lara görə
- `Summary`: Histogram-a oxşar, client-side quantile hesablayır

**SLI / SLO / SLA:**
- **SLI** (Service Level Indicator): ölçülən metrik (error rate, latency p99)
- **SLO** (Service Level Objective): hədəf dəyər (99.9% availability, p99 < 200ms)
- **SLA** (Service Level Agreement): müştəri ilə müqavilə (SLO-dan aşağı olur)

**OpenTelemetry:**
- Vendor-neutral observability standard
- Trace = bir request-in bütün sistemi keçməsi
- Span = trace daxilindəki bir əməliyyat (DB sorgusu, API call)
- Context propagation = trace ID-nin servislər arası ötürülməsi

**Structured logging:**
- JSON format: `{"time":"...","level":"INFO","msg":"...","key":"value"}`
- Sade log əvəzinə: key-value pairs — axtarma, filter edilə bilən
- `slog` (Go 1.21+): standart kitabxanada structured logging

## Praktik Baxış

**Nə vaxt alert etməli:**
- Error rate > 1% (son 5 dəqiqədə)
- Latency p99 > 500ms (son 5 dəqiqədə)
- CPU > 85% (son 15 dəqiqədə)
- Memory > 90% container limitinin

**SLO qurarkən:**
- Availability SLO: `1 - (error_count / total_count)`
- Latency SLO: `histogram_quantile(0.99, rate(...))`
- Error budget: `100% - SLO%` — komanda bu budget-i idarə edir

**Trade-off-lar:**
- Çox metrik → yüksək kardinallik → Prometheus yavaşlayır (label-ları diqqətli seçin)
- Hər request üçün trace = yüksək yük → sampling istifadə edin (1%, 10%)
- Log verbosity: DEBUG development-da, INFO/ERROR production-da
- Histogram bucket-ları: öncədən təyin edin — dəyişdirə bilmərsiniz

**Common mistakes:**
- User ID-ni label kimi istifadə etmək — sonsuz kardinallik!
- Hər xəta üçün ayrı alert — alert fatigue (yorğunluq)
- Log-da həssas məlumat (parol, token) yazmaq
- pprof endpoint-i public internete açmaq

## Nümunələr

### Nümunə 1: Prometheus metrics — Counter, Gauge, Histogram

```go
package main

import (
    "net/http"
    "time"

    "github.com/prometheus/client_golang/prometheus"
    "github.com/prometheus/client_golang/prometheus/promauto"
    "github.com/prometheus/client_golang/prometheus/promhttp"
)

// go get github.com/prometheus/client_golang/prometheus
// go get github.com/prometheus/client_golang/prometheus/promhttp

// Counter — yalnız artan dəyər
var (
    httpRequestsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Name: "http_requests_total",
            Help: "HTTP request-lərin ümumi sayı",
        },
        []string{"method", "endpoint", "status_code"},
        // DİQQƏT: user_id label kimi ƏLAVƏ ETMƏYİN — sonsuz kardinallik!
    )

    httpErrorsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Name: "http_errors_total",
            Help: "HTTP xətalarının sayı",
        },
        []string{"endpoint", "error_type"},
    )
)

// Gauge — artıb-azalan dəyər
var (
    activeConnections = promauto.NewGauge(
        prometheus.GaugeOpts{
            Name: "active_connections",
            Help: "Hal-hazırda aktiv bağlantılar",
        },
    )

    dbPoolSize = promauto.NewGaugeVec(
        prometheus.GaugeOpts{
            Name: "db_pool_size",
            Help: "Database connection pool statistikası",
        },
        []string{"state"}, // "idle", "active", "total"
    )
)

// Histogram — latency ölçmək üçün ideal
var (
    httpRequestDuration = promauto.NewHistogramVec(
        prometheus.HistogramOpts{
            Name: "http_request_duration_seconds",
            Help: "HTTP request müddəti saniyə ilə",
            // Custom bucket-lar — SLO-ya uyğun seçin
            Buckets: []float64{0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5},
        },
        []string{"method", "endpoint"},
    )

    dbQueryDuration = promauto.NewHistogramVec(
        prometheus.HistogramOpts{
            Name:    "db_query_duration_seconds",
            Help:    "Database sorğu müddəti",
            Buckets: prometheus.DefBuckets,
        },
        []string{"query_type"}, // "select", "insert", "update", "delete"
    )
)

// Metrics middleware
func metricsMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        start := time.Now()
        activeConnections.Inc()
        defer activeConnections.Dec()

        // Response writer wrapper — status code-u tutmaq üçün
        rw := &responseWriter{ResponseWriter: w, statusCode: http.StatusOK}

        next.ServeHTTP(rw, r)

        duration := time.Since(start).Seconds()
        statusStr := http.StatusText(rw.statusCode)

        httpRequestsTotal.WithLabelValues(r.Method, r.URL.Path, statusStr).Inc()
        httpRequestDuration.WithLabelValues(r.Method, r.URL.Path).Observe(duration)

        if rw.statusCode >= 400 {
            errorType := "client_error"
            if rw.statusCode >= 500 {
                errorType = "server_error"
            }
            httpErrorsTotal.WithLabelValues(r.URL.Path, errorType).Inc()
        }
    })
}

type responseWriter struct {
    http.ResponseWriter
    statusCode int
}

func (rw *responseWriter) WriteHeader(code int) {
    rw.statusCode = code
    rw.ResponseWriter.WriteHeader(code)
}

func main() {
    mux := http.NewServeMux()

    // Tətbiq endpoint-ləri
    mux.HandleFunc("/api/users", usersHandler)

    // Prometheus metrics endpoint
    mux.Handle("/metrics", promhttp.Handler())

    // Health endpoint-lər
    mux.HandleFunc("/health", healthHandler)
    mux.HandleFunc("/ready", readyHandler)

    // Middleware tətbiq et
    handler := metricsMiddleware(mux)

    http.ListenAndServe(":8080", handler)
}

func usersHandler(w http.ResponseWriter, r *http.Request) {
    w.Write([]byte(`{"users":[]}`))
}

func healthHandler(w http.ResponseWriter, r *http.Request) {
    w.WriteHeader(http.StatusOK)
    w.Write([]byte(`{"status":"alive"}`))
}

func readyHandler(w http.ResponseWriter, r *http.Request) {
    // Bütün asılılıqları yoxla
    w.WriteHeader(http.StatusOK)
    w.Write([]byte(`{"status":"ready"}`))
}
```

### Nümunə 2: OpenTelemetry tracing

```go
package main

import (
    "context"
    "log"
    "net/http"

    "go.opentelemetry.io/otel"
    "go.opentelemetry.io/otel/attribute"
    "go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc"
    "go.opentelemetry.io/otel/sdk/resource"
    sdktrace "go.opentelemetry.io/otel/sdk/trace"
    semconv "go.opentelemetry.io/otel/semconv/v1.17.0"
    "go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp"
)

// go get go.opentelemetry.io/otel
// go get go.opentelemetry.io/otel/sdk/trace
// go get go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc
// go get go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp

// Tracer provider qurmaq
func initTracer(ctx context.Context) (*sdktrace.TracerProvider, error) {
    // Jaeger/Tempo/Grafana-ya OTLP ilə göndər
    exporter, err := otlptracegrpc.New(ctx,
        otlptracegrpc.WithEndpoint("localhost:4317"), // Jaeger/OTel collector
        otlptracegrpc.WithInsecure(),
    )
    if err != nil {
        return nil, err
    }

    tp := sdktrace.NewTracerProvider(
        sdktrace.WithBatcher(exporter),
        sdktrace.WithSampler(sdktrace.TraceIDRatioBased(0.1)), // 10% sampling
        sdktrace.WithResource(resource.NewWithAttributes(
            semconv.SchemaURL,
            semconv.ServiceName("user-api"),
            semconv.ServiceVersion("1.2.3"),
            attribute.String("environment", "production"),
        )),
    )

    otel.SetTracerProvider(tp)
    return tp, nil
}

// Span yaratmaq
func getUser(ctx context.Context, id int64) (*User, error) {
    tracer := otel.Tracer("user-service")
    ctx, span := tracer.Start(ctx, "getUser")
    defer span.End()

    // Attribute əlavə et — dashboard-da filtr edilə bilər
    span.SetAttributes(
        attribute.Int64("user.id", id),
        attribute.String("operation", "fetch"),
    )

    // Alt span — database sorğusu
    ctx, dbSpan := tracer.Start(ctx, "db.query.getUser")
    defer dbSpan.End()
    dbSpan.SetAttributes(
        attribute.String("db.system", "postgresql"),
        attribute.String("db.statement", "SELECT * FROM users WHERE id = $1"),
    )

    // Database sorğusu...
    user, err := db.GetUser(ctx, id)
    if err != nil {
        span.RecordError(err)
        return nil, err
    }

    return user, nil
}

type User struct {
    ID   int64
    Name string
}

var db *Database

type Database struct{}

func (d *Database) GetUser(ctx context.Context, id int64) (*User, error) {
    return &User{ID: id, Name: "Test User"}, nil
}

func main() {
    ctx := context.Background()

    tp, err := initTracer(ctx)
    if err != nil {
        log.Fatal(err)
    }
    defer tp.Shutdown(ctx)

    mux := http.NewServeMux()
    mux.HandleFunc("/api/users/", func(w http.ResponseWriter, r *http.Request) {
        user, _ := getUser(r.Context(), 1)
        _ = user
        w.Write([]byte(`{"id":1}`))
    })

    // otelhttp: HTTP request-ləri avtomatik trace edir
    // Context propagation — W3C TraceContext header-ləri avtomatik oxuyur/yazır
    handler := otelhttp.NewHandler(mux, "user-api")
    log.Fatal(http.ListenAndServe(":8080", handler))
}
```

### Nümunə 3: Structured logging ilə slog (Go 1.21+)

```go
package main

import (
    "context"
    "log/slog"
    "net/http"
    "os"
    "time"
)

// Logger qurma
func setupLogger(env string) *slog.Logger {
    var handler slog.Handler

    if env == "production" {
        // JSON handler — log aggregation sistemləri üçün (Loki, ELK)
        handler = slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
            Level:     slog.LevelInfo,
            AddSource: false, // performance üçün production-da söndür
        })
    } else {
        // Text handler — development üçün oxunaqlı
        handler = slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{
            Level: slog.LevelDebug,
        })
    }

    return slog.New(handler)
}

// Request ID middleware
func requestIDMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        requestID := r.Header.Get("X-Request-ID")
        if requestID == "" {
            requestID = generateID()
        }

        // Logger-ə request ID əlavə et — bütün loglar bu ID ilə axtarıla bilər
        logger := slog.Default().With(
            slog.String("request_id", requestID),
            slog.String("method", r.Method),
            slog.String("path", r.URL.Path),
        )

        ctx := context.WithValue(r.Context(), loggerKey{}, logger)
        w.Header().Set("X-Request-ID", requestID)
        next.ServeHTTP(w, r.WithContext(ctx))
    })
}

type loggerKey struct{}

func loggerFromCtx(ctx context.Context) *slog.Logger {
    if l, ok := ctx.Value(loggerKey{}).(*slog.Logger); ok {
        return l
    }
    return slog.Default()
}

// Handler-də istifadə
func userHandler(w http.ResponseWriter, r *http.Request) {
    logger := loggerFromCtx(r.Context())
    start := time.Now()

    // Structured log — machine-readable
    logger.Info("Request başladı")

    // Business logic...
    userID := 42
    logger.Debug("User axtarılır", slog.Int("user_id", userID))

    // Xəta hadisəsində
    // logger.Error("Database xətası",
    //     slog.String("error", err.Error()),
    //     slog.Int("user_id", userID),
    // )
    // {"time":"2024-...","level":"ERROR","msg":"Database xətası","request_id":"abc","error":"...","user_id":42}

    logger.Info("Request tamamlandı",
        slog.Duration("duration", time.Since(start)),
        slog.Int("status", 200),
    )

    w.Write([]byte(`{"id":42}`))
}

func generateID() string {
    return "req-" + time.Now().Format("20060102150405.000000")
}

func main() {
    logger := setupLogger(os.Getenv("APP_ENV"))
    slog.SetDefault(logger)

    // Service başlama logu
    slog.Info("Server başladı",
        slog.String("version", "1.2.3"),
        slog.Int("port", 8080),
    )

    mux := http.NewServeMux()
    mux.HandleFunc("/api/users/", userHandler)

    handler := requestIDMiddleware(mux)
    http.ListenAndServe(":8080", handler)
}
```

### Nümunə 4: Health check endpoint-ləri

```go
package main

import (
    "context"
    "database/sql"
    "encoding/json"
    "net/http"
    "sync"
    "time"
)

type HealthStatus struct {
    Status   string            `json:"status"`
    Checks   map[string]string `json:"checks,omitempty"`
    Duration string            `json:"duration"`
}

type HealthChecker struct {
    db    *sql.DB
    mu    sync.RWMutex
    cache map[string]checkResult
}

type checkResult struct {
    ok      bool
    message string
    at      time.Time
}

// Liveness — proses işləyirmi?
// Kubernetes: bu xeyr olarsa pod yenidən başladılır
func (h *HealthChecker) LivenessHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(HealthStatus{
        Status:   "alive",
        Duration: "0ms",
    })
}

// Readiness — request qəbul edə bilirmi?
// Kubernetes: bu xeyr olarsa pod-a traffic göndərilmir
func (h *HealthChecker) ReadinessHandler(w http.ResponseWriter, r *http.Request) {
    ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
    defer cancel()

    start := time.Now()
    checks := make(map[string]string)
    allOk := true

    // Database yoxla
    if err := h.db.PingContext(ctx); err != nil {
        checks["database"] = "FAIL: " + err.Error()
        allOk = false
    } else {
        checks["database"] = "OK"
    }

    // Redis yoxla (nümunə)
    // if err := h.redis.Ping(ctx).Err(); err != nil {
    //     checks["redis"] = "FAIL: " + err.Error()
    //     allOk = false
    // } else {
    //     checks["redis"] = "OK"
    // }

    status := HealthStatus{
        Status:   "ready",
        Checks:   checks,
        Duration: time.Since(start).String(),
    }

    if !allOk {
        status.Status = "not_ready"
        w.WriteHeader(http.StatusServiceUnavailable)
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(status)
}

// Kubernetes probe konfiqurasiyası:
// livenessProbe:
//   httpGet:
//     path: /health
//     port: 8080
//   initialDelaySeconds: 10
//   periodSeconds: 30
//
// readinessProbe:
//   httpGet:
//     path: /ready
//     port: 8080
//   initialDelaySeconds: 5
//   periodSeconds: 10
```

### Nümunə 5: SLO monitorinq — Prometheus alert qaydaları

```yaml
# prometheus/alerts.yaml
groups:
  - name: api-slo
    rules:
      # Error rate SLO: 99.9% availability (0.1% error rate)
      - alert: HighErrorRate
        expr: |
          sum(rate(http_requests_total{status_code=~"5.."}[5m]))
          /
          sum(rate(http_requests_total[5m])) > 0.001
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "Error rate SLO pozuldu"
          description: "Error rate {{ $value | humanizePercentage }} > 0.1%"

      # Latency SLO: p99 < 500ms
      - alert: HighLatencyP99
        expr: |
          histogram_quantile(0.99,
            sum(rate(http_request_duration_seconds_bucket[5m])) by (le, endpoint)
          ) > 0.5
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Latency SLO pozuldu"
          description: "p99 latency {{ $value }}s > 500ms for {{ $labels.endpoint }}"

      # Saturation: yüksək CPU
      - alert: HighCPUUsage
        expr: rate(process_cpu_seconds_total[5m]) > 0.8
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "CPU istifadəsi yüksəkdir"

      # Goroutine leak şüphəsi
      - alert: GoRoutineLeak
        expr: go_goroutines > 10000
        for: 15m
        labels:
          severity: warning
        annotations:
          summary: "Goroutine sayı anormal yüksəkdir: {{ $value }}"
```

```yaml
# grafana/dashboard.json (əsas panellər)
# Dashboard-da olması tövsiyə olunan panellər:
#
# 1. Request Rate (RPS):
#    sum(rate(http_requests_total[5m])) by (endpoint)
#
# 2. Error Rate:
#    sum(rate(http_requests_total{status_code=~"5.."}[5m])) / sum(rate(http_requests_total[5m]))
#
# 3. Latency p50/p95/p99:
#    histogram_quantile(0.99, sum(rate(http_request_duration_seconds_bucket[5m])) by (le))
#
# 4. Active Goroutines:
#    go_goroutines
#
# 5. Memory Usage:
#    process_resident_memory_bytes / 1024 / 1024
#
# 6. GC Pause:
#    rate(go_gc_duration_seconds_sum[5m]) / rate(go_gc_duration_seconds_count[5m])
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Prometheus metrics əlavə etmək:**
1. Prometheus client_golang quraşdırın
2. HTTP handler üçün metrics middleware yazın (counter + histogram)
3. `/metrics` endpoint əlavə edin
4. `curl localhost:8080/metrics | grep http_` ilə yoxlayın

**Tapşırıq 2 — OpenTelemetry tracing:**
1. Docker ilə Jaeger qurun: `docker run -p 16686:16686 -p 4317:4317 jaegertracing/all-in-one`
2. `otelhttp.NewHandler()` ilə HTTP server-ə tracing əlavə edin
3. İki handler arasında span ötürün (context propagation)
4. `localhost:16686`-da trace-ləri görün

**Tapşırıq 3 — Structured logging:**
1. `slog` ilə JSON logger qurun
2. Hər request üçün unique request ID əlavə edin
3. Duration, status code logla
4. `jq` ilə JSON logları filtr edin: `./server 2>&1 | jq 'select(.level=="ERROR")'`

**Tapşırıq 4 — Health check:**
1. `/health` (liveness) və `/ready` (readiness) endpoint yazın
2. Readiness-da database ping əlavə edin
3. Database olmadan test edin — 503 qaytarmalıdır
4. Kubernetes probe konfiqurasiyası yazın

**Tapşırıq 5 — SLO dashboard:**
1. Prometheus + Grafana qurun (docker-compose ilə)
2. Error rate paneli yaradın
3. p99 latency paneli yaradın
4. Alert qaydası əlavə edin (error rate > 1%)

## Ətraflı Qeydlər

**Kardinallik problem:**
- Label kombinasiyaları Prometheus-da sonsuz time series yarada bilər
- `user_id`, `session_id` kimi dəyərləri label kimi ƏLAVƏ ETMƏYİN
- Məqbul label-lar: `method`, `endpoint`, `status_code`, `region`

**Distributed tracing sampling:**
- 100% trace → yüksək yük → sampling istifadə edin
- Head-based sampling: qərar request-in əvvəlində verilir (sadə)
- Tail-based sampling: qərar request bitdikdən sonra — slow request-ləri tutmaq üçün

**Log aggregation:**
- Development: stdout JSON → `jq`
- Production: Grafana Loki, Elasticsearch, Datadog
- Log-a həssas məlumat yazma: parol, token, PII (Personal Identifiable Information)

## PHP ilə Müqayisə

PHP/Laravel-də monitoring adətən xarici servislər vasitəsilə qurulur: Laravel Telescope (development), Datadog APM, New Relic. Go-da isə `prometheus/client_golang` standart seçimdir — `/metrics` endpoint-i birbaşa Prometheus tərəfindən scrape edilir, agent yükləmə lazım deyil. `log/slog` (Go 1.21+) structured logging üçün standart kitabxanadır; Laravel-də Monolog eyni rolu oynayır. OpenTelemetry Go-da birinci sinif dəstəyə malikdir; PHP-də `open-telemetry/opentelemetry-php` aktiv inkişaf mərhələsindədir, amma Go ilə müqayisədə daha az yetişkindir.

## Əlaqəli Mövzular

- [21-profiling-and-benchmarking.md](21-profiling-and-benchmarking.md) — pprof ilə performance profiling
- [70-docker-and-deploy.md](23-docker-and-deploy.md) — Kubernetes health probe-ları
- [../backend/17-graceful-shutdown.md](../backend/17-graceful-shutdown.md) — Graceful shutdown
- [26-microservices.md](26-microservices.md) — Distributed tracing microservice-lərdə
