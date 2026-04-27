# Logging (Middle)

## İcmal

Go-da logging üçün bir neçə yanaşma mövcuddur: standart `log` paketi, Go 1.21 ilə gələn müasir `log/slog` paketi və üçüncü tərəf kitabxanalar (zap, zerolog). `slog` get-gedə production standartına çevrilir.

## Niyə Vacibdir

Production tətbiqlərdə log keyfiyyəti debuggingin çətinliyini birbaşa müəyyən edir. Structured logging (JSON formatında) log aggregation sistemləri (ELK, Datadog, Grafana Loki) ilə inteqrasiyanı asanlaşdırır. Düzgün log səviyyələri isə production-da yalnız lazımlı məlumatın görünməsini təmin edir.

## Əsas Anlayışlar

- **log paketi** — Go standart kitabxanasının sadə, unstructured logger-i
- **slog** — Go 1.21+ ilə gələn structured, leveled, handler-based logger
- **Handler** — slog-un çıxış formatını müəyyən edən interfeys (TextHandler, JSONHandler)
- **Level** — Debug / Info / Warn / Error
- **Structured logging** — hər log entry-nin key-value cütlərindən ibarət olması
- **Log rotation** — böyük faylların avtomatik idarə edilməsi (lumberjack kimi kitabxanalarla)

## Praktik Baxış

**Nə vaxt hansını seçmək:**

| Paket     | Nə vaxt              | Sürət        |
|-----------|----------------------|--------------|
| `log`     | Sadə script/tool     | Orta          |
| `slog`    | Production app       | Yüksək        |
| `zap`     | Çox yüksək throughput| Çox yüksək    |
| `zerolog` | Zero allocation      | Ən yüksək     |

**Trade-off-lar:**
- `slog` kifayət qədər sürətlidir — çox hallarda zap/zerolog artıq optimizasiyadır
- `log` paketi structured logging dəstəkləmir — production-da istifadə etməyin
- Sensitive məlumatları (token, şifrə) heç vaxt log-a yazmayın

**Common mistakes:**
- `log.Fatal` — `os.Exit(1)` çağırır, `defer`-lər işləmir
- `log.Panic` — panic verir, error handling-i korlayır
- JSON format olmadan log aggregation sistemlərinə göndərmək

## Nümunələr

### Nümunə 1: Standart log paketi

```go
package main

import (
    "log"
    "os"
)

func main() {
    // Default logger — stderr-ə yazır, tarix+vaxt prefiksi var
    log.Println("Proqram başladı")
    log.Printf("İstifadəçi %s daxil oldu, yaş: %d", "Orkhan", 25)

    // Prefix əlavə etmək
    log.SetPrefix("[APP] ")
    log.Println("Prefix ilə log")

    // Formatı dəyişmək
    log.SetFlags(log.Ldate | log.Ltime | log.Lshortfile)
    log.Println("Tarix + vaxt + fayl görünür")

    // log.Flags dəyərləri:
    // log.Ldate        — 2024/03/15
    // log.Ltime        — 14:30:00
    // log.Lmicroseconds — mikrosaniyə dəqiqliyi
    // log.Llongfile    — tam fayl yolu və sətir nömrəsi
    // log.Lshortfile   — qısa fayl adı və sətir nömrəsi
    // log.LUTC         — UTC vaxt
    // log.Lmsgprefix   — prefix mesajdan əvvəl

    // Fayla log yazmaq
    faylLog, err := os.OpenFile("app.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
    if err != nil {
        log.Fatal("Log faylı açılamadı:", err)
    }
    defer faylLog.Close()

    faylLogger := log.New(faylLog, "[FAYL] ", log.Ldate|log.Ltime)
    faylLogger.Println("Bu mesaj fayla yazıldı")

    // log.Fatal → log yazıb os.Exit(1) çağırır (defer işləmir!)
    // log.Panic → log yazıb panic verir
}
```

### Nümunə 2: slog — Text formatı (development)

```go
package main

import (
    "log/slog"
    "os"
)

func main() {
    // Default slog (text format, Info+ level)
    slog.Info("Proqram başladı")
    slog.Warn("Disk sahəsi azalır", "qalan_gb", 5)
    slog.Error("Bağlantı qırıldı", "server", "db-01", "səbəb", "timeout")

    // Key-value cütləri ilə
    slog.Info("İstifadəçi daxil oldu",
        "user_id", 42,
        "ad", "Orkhan",
        "ip", "192.168.1.1",
    )

    // Text handler — development üçün
    textHandler := slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{
        Level: slog.LevelDebug, // debug-dan başlat
    })
    logger := slog.New(textHandler)
    logger.Debug("Bu yalnız debug modda görünür")
    logger.Info("Text formatda log", "key", "value")
}
```

### Nümunə 3: slog — JSON formatı (production)

```go
package main

import (
    "log/slog"
    "os"
)

func main() {
    // JSON handler — production üçün (ELK, Datadog ilə uyğun)
    jsonHandler := slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
        Level: slog.LevelInfo,
    })
    logger := slog.New(jsonHandler)

    // Çıxış: {"time":"...","level":"INFO","msg":"Sifariş yaradıldı","order_id":"ORD-123",...}
    logger.Info("Sifariş yaradıldı",
        "order_id", "ORD-123",
        "məbləğ", 99.99,
        "valyuta", "AZN",
    )

    // Group ilə əlaqəli sahələri qruplaşdırmaq
    logger.Info("Server başladı",
        slog.Group("server",
            slog.String("host", "localhost"),
            slog.Int("port", 8080),
        ),
        slog.Group("tls",
            slog.Bool("aktiv", true),
        ),
    )

    // slog-u qlobal default kimi təyin etmək
    slog.SetDefault(logger) // bundan sonra slog.Info() JSON yazır
    slog.Info("Bu indi JSON formatındadır")
}
```

### Nümunə 4: slog.With — sabit sahələr ilə logger

```go
package main

import (
    "log/slog"
    "os"
)

func main() {
    jsonHandler := slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
        Level: slog.LevelDebug,
    })
    baseLogger := slog.New(jsonHandler)

    // Request-scoped logger — hər log-da request_id və method olacaq
    reqLogger := baseLogger.With(
        "request_id", "abc-123",
        "method", "POST",
        "path", "/api/orders",
    )

    reqLogger.Info("Sorğu alındı")
    reqLogger.Info("Cavab göndərildi", "status", 201, "ms", 45)

    // Service-level logger
    dbLogger := baseLogger.With("component", "database")
    dbLogger.Info("Sorğu icra edildi", "query", "SELECT * FROM users", "rows", 10)
    dbLogger.Error("Bağlantı xətası", "host", "db-01")
}
```

### Nümunə 5: Xüsusi slog Handler (filtrasiya nümunəsi)

```go
package main

import (
    "context"
    "log/slog"
    "os"
    "strings"
)

// SensitiveFilter — şifrə, token kimi sahələri gizlədən handler
type SensitiveFilter struct {
    handler   slog.Handler
    sensitiveKeys []string
}

func NewSensitiveFilter(h slog.Handler, keys ...string) *SensitiveFilter {
    return &SensitiveFilter{handler: h, sensitiveKeys: keys}
}

func (f *SensitiveFilter) Enabled(ctx context.Context, level slog.Level) bool {
    return f.handler.Enabled(ctx, level)
}

func (f *SensitiveFilter) Handle(ctx context.Context, r slog.Record) error {
    filtered := slog.NewRecord(r.Time, r.Level, r.Message, r.PC)
    r.Attrs(func(a slog.Attr) bool {
        for _, key := range f.sensitiveKeys {
            if strings.EqualFold(a.Key, key) {
                filtered.AddAttrs(slog.String(a.Key, "***REDACTED***"))
                return true
            }
        }
        filtered.AddAttrs(a)
        return true
    })
    return f.handler.Handle(ctx, filtered)
}

func (f *SensitiveFilter) WithAttrs(attrs []slog.Attr) slog.Handler {
    return &SensitiveFilter{handler: f.handler.WithAttrs(attrs), sensitiveKeys: f.sensitiveKeys}
}

func (f *SensitiveFilter) WithGroup(name string) slog.Handler {
    return &SensitiveFilter{handler: f.handler.WithGroup(name), sensitiveKeys: f.sensitiveKeys}
}

func main() {
    base := slog.NewJSONHandler(os.Stdout, nil)
    safeHandler := NewSensitiveFilter(base, "password", "token", "secret")
    logger := slog.New(safeHandler)

    // "password" sahəsi ***REDACTED*** kimi çıxacaq
    logger.Info("İstifadəçi qeydiyyatı",
        "email", "user@example.com",
        "password", "mysecret123", // gizlənəcək
        "token", "abc.def.ghi",    // gizlənəcək
    )
}
```

### Nümunə 6: zap vs zerolog müqayisəsi

```go
// zap — Uber tərəfindən, iki mode: sugared (rahat) və core (sürətli)
// go get go.uber.org/zap

// Sugared (rahat istifadə):
// logger, _ := zap.NewProduction()
// sugar := logger.Sugar()
// sugar.Infow("Sifariş", "id", 123, "məbləğ", 99.99)

// Core (maksimum sürət, allocation yoxdur):
// logger, _ := zap.NewProduction()
// logger.Info("Sifariş",
//     zap.Int("id", 123),
//     zap.Float64("məbləğ", 99.99),
// )

// zerolog — allocation-free, chain API:
// go get github.com/rs/zerolog

// log := zerolog.New(os.Stdout).With().Timestamp().Logger()
// log.Info().Str("order_id", "ORD-123").Float64("amount", 99.99).Msg("Sifariş")

// TÖVSIYƏ: Standart layihələrdə slog kifayət edir.
// zap/zerolog yalnız çox yüksək throughput tələb edən sistemlər üçündür (>100k req/s).
```

## Praktik Tapşırıqlar

**Tapşırıq 1: HTTP middleware logger**

HTTP server üçün request logging middleware yazın. Hər request üçün: method, path, status code, latency, request ID log yazılsın. JSON format istifadə edin.

```go
package main

import (
    "context"
    "log/slog"
    "net/http"
    "os"
    "time"

    "github.com/google/uuid"
)

type contextKey string
const requestIDKey contextKey = "request_id"

func LoggingMiddleware(logger *slog.Logger, next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        start := time.Now()
        requestID := uuid.New().String()

        ctx := context.WithValue(r.Context(), requestIDKey, requestID)
        r = r.WithContext(ctx)

        // Request-scoped logger
        reqLogger := logger.With(
            "request_id", requestID,
            "method", r.Method,
            "path", r.URL.Path,
            "remote_addr", r.RemoteAddr,
        )

        // Response writer wrapper status code-u tutmaq üçün
        rw := &responseWriter{ResponseWriter: w, statusCode: http.StatusOK}

        next.ServeHTTP(rw, r)

        reqLogger.Info("HTTP request",
            "status", rw.statusCode,
            "duration_ms", time.Since(start).Milliseconds(),
        )
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
    logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
        Level: slog.LevelInfo,
    }))

    mux := http.NewServeMux()
    mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
        w.Write([]byte("OK"))
    })

    http.ListenAndServe(":8080", LoggingMiddleware(logger, mux))
}
```

**Tapşırıq 2: Multi-output logger**

Eyni anda həm stdout-a (text format), həm də fayla (JSON format) yazan logger qurun.

```go
package main

import (
    "io"
    "log/slog"
    "os"
)

func main() {
    logFile, _ := os.OpenFile("app.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
    defer logFile.Close()

    // Multi-writer: stdout + fayl
    multi := io.MultiWriter(os.Stdout, logFile)

    handler := slog.NewJSONHandler(multi, &slog.HandlerOptions{
        Level: slog.LevelDebug,
    })
    logger := slog.New(handler)
    slog.SetDefault(logger)

    slog.Info("Bu həm ekrana, həm fayla yazılır", "version", "1.0.0")
    slog.Error("Xəta baş verdi", "code", 500)
}
```

**Tapşırıq 3: Level-based log routing**

Development-də Debug, production-da yalnız Info+ göstərən konfiqurasiya qurun. `LOG_LEVEL` environment variable ilə idarə edin.

```go
package main

import (
    "log/slog"
    "os"
)

func main() {
    levelStr := os.Getenv("LOG_LEVEL")

    var level slog.Level
    switch levelStr {
    case "DEBUG":
        level = slog.LevelDebug
    case "WARN":
        level = slog.LevelWarn
    case "ERROR":
        level = slog.LevelError
    default:
        level = slog.LevelInfo
    }

    format := os.Getenv("LOG_FORMAT") // "json" və ya "text"

    var handler slog.Handler
    opts := &slog.HandlerOptions{Level: level}
    if format == "json" {
        handler = slog.NewJSONHandler(os.Stdout, opts)
    } else {
        handler = slog.NewTextHandler(os.Stdout, opts)
    }

    logger := slog.New(handler)
    slog.SetDefault(logger)

    // İstifadə:
    // LOG_LEVEL=DEBUG LOG_FORMAT=json go run main.go
    slog.Debug("Bu yalnız DEBUG modda görünür")
    slog.Info("Proqram başladı", "pid", os.Getpid())
}
```

## Ətraflı Qeydlər

**slog arxitekturası:**

`slog.Logger` → `slog.Handler` interfeysi → konkret implementasiya (JSONHandler, TextHandler və ya xüsusi). Bu dizayn sayəsində öz handler-inizi yaza bilərsiniz: məsələn, Datadog API-yə birbaşa göndərən, və ya test üçün in-memory saxlayan.

**Performance məsləhətləri:**
- `slog.LogAttrs()` — ən performant üsul (tipcasting olmadan)
- `slog.With()` — sabit sahələri bir dəfə əlavə edin, hər call-da deyil
- Debug log-larını prodda söndürün: `slog.LevelInfo` kimi konfiqurasiya edin

**lumberjack ilə log rotation:**
```go
// go get gopkg.in/natefinish/lumberjack.v2
// jack := &lumberjack.Logger{Filename: "app.log", MaxSize: 100, MaxBackups: 3, Compress: true}
// handler := slog.NewJSONHandler(jack, nil)
```

## PHP ilə Müqayisə

```
PHP Monolog           →  Go slog
$logger->info(...)    →  slog.Info(...)
$logger->error(...)   →  slog.Error(...)
Handler               →  slog.Handler interface
Formatter             →  JSONHandler / TextHandler
Channel               →  Logger.With("service", "orders")
```

## Əlaqəli Mövzular

- `24-testing` — log output-u test etmək (`slog` test handler)
- `27-goroutines-and-channels` — goroutine-lərdə thread-safe logging
- `28-context` — context ilə request-scoped logger ötürmək
- `33-http-server` — HTTP middleware-də logging
- `39-environment-and-config` — LOG_LEVEL kimi konfiqurasiya
