package main

import (
	"fmt"
)

// ===============================================
// MONITORING VE OBSERVABILITY
// ===============================================

// Production sistemlerde neyin bas verdiyini bilmek ucun
// monitoring (nezaret) ve observability (musahide olunabilirlik) lazimdir.
// 3 esas sutun: Metrics, Logs, Traces

func main() {
	fmt.Println("=== MONITORING VE OBSERVABILITY ===")

	// -------------------------------------------
	// 1. Prometheus metrics: Counter, Gauge, Histogram, Summary
	// -------------------------------------------
	fmt.Println("\n--- 1. Prometheus Metrics ---")
	fmt.Println("Prometheus - metrik toplama ve saxlama sistemidir.")
	fmt.Println("4 esas metrik tipi var:")
	fmt.Println("  Counter   - yalniz artan deyer (request sayi, xeta sayi)")
	fmt.Println("  Gauge     - artan/azalan deyer (CPU istifadesi, aktiv baglanti)")
	fmt.Println("  Histogram - deyerlerin paylama statistikasi (cavab muddeti)")
	fmt.Println("  Summary   - Histogram-a oxsar, quantile hesablayir")

	fmt.Println(`
  // go get github.com/prometheus/client_golang/prometheus
  // go get github.com/prometheus/client_golang/prometheus/promhttp

  import (
      "github.com/prometheus/client_golang/prometheus"
      "github.com/prometheus/client_golang/prometheus/promauto"
      "github.com/prometheus/client_golang/prometheus/promhttp"
  )

  // Counter - request sayini izlemek
  var httpRequestlari = promauto.NewCounterVec(
      prometheus.CounterOpts{
          Name: "http_requestlari_toplam",
          Help: "HTTP request-lerin umumi sayi",
      },
      []string{"method", "endpoint", "status"},
  )

  // Gauge - aktiv baglantilari izlemek
  var aktivBaglantilar = promauto.NewGauge(
      prometheus.GaugeOpts{
          Name: "aktiv_baglantilar",
          Help: "Hal-hazirda aktiv olan baglantilar",
      },
  )

  // Histogram - cavab muddetini izlemek
  var cavabMuddeti = promauto.NewHistogramVec(
      prometheus.HistogramOpts{
          Name:    "http_cavab_muddeti_saniye",
          Help:    "HTTP cavab muddeti saniye ile",
          Buckets: prometheus.DefBuckets, // 0.005, 0.01, 0.025, ...
      },
      []string{"endpoint"},
  )

  // Summary - quantile ile olcme
  var islemeMuddeti = promauto.NewSummary(
      prometheus.SummaryOpts{
          Name:       "isleme_muddeti_saniye",
          Help:       "Request isleme muddeti",
          Objectives: map[float64]float64{0.5: 0.05, 0.9: 0.01, 0.99: 0.001},
      },
  )

  // Handler-de istifade
  func metrikliHandler(w http.ResponseWriter, r *http.Request) {
      baslangic := time.Now()
      aktivBaglantilar.Inc() // gauge artir
      defer aktivBaglantilar.Dec() // handler bitende azalt

      // ... esas is ...

      muddet := time.Since(baslangic).Seconds()
      cavabMuddeti.WithLabelValues("/api/users").Observe(muddet)
      httpRequestlari.WithLabelValues("GET", "/api/users", "200").Inc()
  }`)

	// -------------------------------------------
	// 2. /metrics endpoint
	// -------------------------------------------
	fmt.Println("\n--- 2. /metrics endpoint ---")
	fmt.Println(`
  import "github.com/prometheus/client_golang/prometheus/promhttp"

  func main() {
      // Esas router
      mux := http.NewServeMux()
      mux.HandleFunc("/api/users", istifadeciHandler)

      // Prometheus metrics endpoint
      mux.Handle("/metrics", promhttp.Handler())

      // Server basla
      log.Println("Server :8080 portunda isleyir")
      log.Fatal(http.ListenAndServe(":8080", mux))
  }

  // curl localhost:8080/metrics ile metrikleri gore bilersiniz
  // Prometheus server bu endpoint-i mueyyen araliqlarda scrape edir`)

	// -------------------------------------------
	// 3. OpenTelemetry: traces, spans, context propagation
	// -------------------------------------------
	fmt.Println("\n--- 3. OpenTelemetry (OTel) ---")
	fmt.Println("OpenTelemetry - vendor-neutral observability framework-dur.")
	fmt.Println("Trace = bir request-in butun sistemi kecmesi")
	fmt.Println("Span  = trace icinde tek bir emeliyyat (DB sorgusu, API call)")
	fmt.Println(`
  // go get go.opentelemetry.io/otel
  // go get go.opentelemetry.io/otel/sdk/trace
  // go get go.opentelemetry.io/otel/exporters/otlp/otlptrace

  import (
      "go.opentelemetry.io/otel"
      "go.opentelemetry.io/otel/attribute"
      "go.opentelemetry.io/otel/sdk/resource"
      sdktrace "go.opentelemetry.io/otel/sdk/trace"
      "go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc"
  )

  // Tracer provider qurmaq
  func tracerQur(ctx context.Context) (*sdktrace.TracerProvider, error) {
      exporter, err := otlptracegrpc.New(ctx,
          otlptracegrpc.WithEndpoint("localhost:4317"),
          otlptracegrpc.WithInsecure(),
      )
      if err != nil {
          return nil, err
      }

      tp := sdktrace.NewTracerProvider(
          sdktrace.WithBatcher(exporter),
          sdktrace.WithResource(resource.NewWithAttributes(
              "menim-servisim",
              attribute.String("environment", "production"),
          )),
      )
      otel.SetTracerProvider(tp)
      return tp, nil
  }

  // Span yaratmaq
  func istifadeciAl(ctx context.Context, id int) (*User, error) {
      tracer := otel.Tracer("istifadeci-servisi")
      ctx, span := tracer.Start(ctx, "istifadeciAl")
      defer span.End()

      // Attribute elave et
      span.SetAttributes(
          attribute.Int("istifadeci.id", id),
      )

      // Alt span - database sorgusu
      ctx, dbSpan := tracer.Start(ctx, "db.sorgu")
      defer dbSpan.End()

      // ... database sorgusu ...
      return &User{}, nil
  }

  // Context propagation - servisler arasi trace davamiyyeti
  // HTTP header-ler vasitesile trace ID oturulur
  // otelhttp.NewHandler() avtomatik propagation edir`)

	// -------------------------------------------
	// 4. Structured logging with slog (Go 1.21+)
	// -------------------------------------------
	fmt.Println("\n--- 4. Structured Logging (slog) ---")
	fmt.Println("slog - Go 1.21-den standart kitabxanada strukturlu log paketidir.")
	fmt.Println(`
  import "log/slog"

  func main() {
      // JSON handler - production ucun
      logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
          Level: slog.LevelInfo,
      }))
      slog.SetDefault(logger)

      // Sade istifade
      slog.Info("Server basladi", "port", 8080)
      // {"time":"...","level":"INFO","msg":"Server basladi","port":8080}

      slog.Error("Baglanti ugursuz",
          "host", "db.example.com",
          "port", 5432,
          "err", err,
      )
      // {"time":"...","level":"ERROR","msg":"Baglanti ugursuz","host":"db.example.com","port":5432,"err":"..."}

      // Qrup ile strukturlasma
      slog.Info("Request islendi",
          slog.Group("request",
              slog.String("method", "GET"),
              slog.String("path", "/api/users"),
          ),
          slog.Group("cavab",
              slog.Int("status", 200),
              slog.Duration("muddet", 45*time.Millisecond),
          ),
      )

      // Logger ile context
      logger = logger.With("servis", "user-api", "versiya", "1.2.0")
      logger.Info("Hazir") // her mesajda servis ve versiya olacaq

      // Slog seviyyeleri: Debug, Info, Warn, Error
      // Custom seviyye de yaratmaq olar
  }`)

	// -------------------------------------------
	// 5. Health check endpoints
	// -------------------------------------------
	fmt.Println("\n--- 5. Health Check Endpoints ---")
	fmt.Println("/health  - servis isleyirmi? (liveness)")
	fmt.Println("/ready   - servis request qebul ede bilermi? (readiness)")
	fmt.Println(`
  import (
      "database/sql"
      "encoding/json"
      "net/http"
  )

  type SaglamliqCavabi struct {
      Status     string            ` + "`json:\"status\"`" + `
      Xidmetler  map[string]string ` + "`json:\"xidmetler,omitempty\"`" + `
  }

  // Liveness - servis isleyir
  func saglamliqHandler(w http.ResponseWriter, r *http.Request) {
      cavab := SaglamliqCavabi{Status: "saglamdir"}
      w.Header().Set("Content-Type", "application/json")
      json.NewEncoder(w).Encode(cavab)
  }

  // Readiness - butun asililiqlar hazirdir
  func hazirHandler(db *sql.DB) http.HandlerFunc {
      return func(w http.ResponseWriter, r *http.Request) {
          xidmetler := make(map[string]string)

          // Database yoxla
          if err := db.PingContext(r.Context()); err != nil {
              xidmetler["database"] = "ugursuz: " + err.Error()
              cavab := SaglamliqCavabi{Status: "hazir_deyil", Xidmetler: xidmetler}
              w.WriteHeader(http.StatusServiceUnavailable)
              json.NewEncoder(w).Encode(cavab)
              return
          }
          xidmetler["database"] = "saglamdir"

          // Redis yoxla, Kafka yoxla, ve s.

          cavab := SaglamliqCavabi{Status: "hazirdir", Xidmetler: xidmetler}
          json.NewEncoder(w).Encode(cavab)
      }
  }

  // Kubernetes bu endpoint-leri istifade edir:
  // livenessProbe:  /health - ugursuz olarsa pod yeniden basladilir
  // readinessProbe: /ready  - ugursuz olarsa trafik yonlendirilmir`)

	// -------------------------------------------
	// 6. pprof integration
	// -------------------------------------------
	fmt.Println("\n--- 6. pprof (Performance Profiling) ---")
	fmt.Println("pprof - Go proqraminin CPU, memory, goroutine profilini cixarir.")
	fmt.Println(`
  import (
      "net/http"
      _ "net/http/pprof" // avtomatik /debug/pprof/ endpoint-leri elave edir
  )

  func main() {
      // Ayri portda pprof server
      go func() {
          // localhost:6060/debug/pprof/ ile daxil olun
          http.ListenAndServe(":6060", nil)
      }()

      // Esas server ...
  }

  // Istifade:
  // go tool pprof http://localhost:6060/debug/pprof/heap     - memory profili
  // go tool pprof http://localhost:6060/debug/pprof/profile  - CPU profili (30s)
  // go tool pprof http://localhost:6060/debug/pprof/goroutine - goroutine dump

  // Proqrammatik profil:
  import "runtime/pprof"

  func cpuProfiliYaz(fayl string) {
      f, _ := os.Create(fayl)
      defer f.Close()
      pprof.StartCPUProfile(f)
      defer pprof.StopCPUProfile()
      // ... test olunan kod ...
  }`)

	// -------------------------------------------
	// 7. Distributed tracing concepts
	// -------------------------------------------
	fmt.Println("\n--- 7. Distributed Tracing ---")
	fmt.Println("Paylanmis sistemlerde bir request-in yolunu izlemek:")
	fmt.Println(`
  Trace ID: butun request-i temsil edir (A -> B -> C servislerini kecir)
  Span ID:  tek bir emeliyyat (meselen B servisinde DB sorgusu)
  Parent Span ID: hansi span-in alt-span-i oldugunu gosterir

  Numune axin:
  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
  │ API Gateway │ --> │ User Service│ --> │  Database   │
  │  Span A     │     │  Span B     │     │  Span C     │
  │  TraceID: x │     │  TraceID: x │     │  TraceID: x │
  │  Parent: -  │     │  Parent: A  │     │  Parent: B  │
  └─────────────┘     └─────────────┘     └─────────────┘

  Populyar aletler:
  - Jaeger (aciq qaynaq, CNCF)
  - Zipkin
  - Grafana Tempo
  - Datadog APM
  - AWS X-Ray

  // HTTP vasitesile trace propagation (W3C TraceContext standardi)
  // Header: traceparent: 00-<trace-id>-<span-id>-<flags>

  // gRPC vasitesile propagation
  // Metadata-da trace melumat oturulur

  // OpenTelemetry her ikisini avtomatik edir:
  import "go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp"

  // HTTP client-i wrap et
  client := &http.Client{
      Transport: otelhttp.NewTransport(http.DefaultTransport),
  }

  // HTTP server-i wrap et
  handler := otelhttp.NewHandler(mux, "menim-servisim")
  http.ListenAndServe(":8080", handler)`)

	fmt.Println("\n=== XULASE ===")
	fmt.Println("Metrics    - ne qeder? (sayi, muddet, olcu)")
	fmt.Println("Logs       - ne bas verdi? (hadiselerin detalari)")
	fmt.Println("Traces     - haradan haraya? (request yolu)")
	fmt.Println("Health     - servis islekdirmi?")
	fmt.Println("pprof      - performans problemleri harada?")
	fmt.Println("Bu 3 sutun birlikde tam observability yaradir.")
}
