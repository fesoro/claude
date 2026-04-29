# Graceful Shutdown (Senior)

## İcmal

Graceful shutdown — tətbiqin dayandırılma siqnalı aldıqda anında öldürülmək əvəzinə mövcud sorğuları tamamlayıb, resursları sərbəst buraxıb, düzgün şəkildə bağlanmasıdır. Kubernetes, Docker, systemd — hamısı SIGTERM → gözlə → SIGKILL ardıcıllığını izləyir. Bu pattern tətbiq edilmədikdə: yarımçıq yazılan database qeydləri, müştəriyə `connection reset` xətası, data itkisi.

## Niyə Vacibdir

- **Kubernetes:** Rolling update zamanı köhnə pod-a SIGTERM göndərir, 30s sonra SIGKILL
- **Docker:** `docker stop` → SIGTERM (10s) → SIGKILL
- **systemd:** `systemctl stop` → SIGTERM (TimeoutStopSec) → SIGKILL
- **Data integrity:** Yarımçıq DB transaction-lar
- **User experience:** HTTP 502 əvəzinə sorğu tamamlanır

## Əsas Anlayışlar

### Graceful Shutdown Addımları

```
SIGTERM alındı
    ↓
1. Yeni bağlantı qəbulunu dayandır (health check → 503)
    ↓
2. Mövcud HTTP sorğuları tamamla (server.Shutdown)
    ↓
3. Background worker-ləri dayandır (stopCh)
    ↓
4. Database bağlantılarını bağla (db.Close)
    ↓
5. Log fayllarını flush et
    ↓
6. Exit(0)
```

### http.Server.Shutdown

Go standart kitabxanasının əsas funksiyası:

```go
ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
defer cancel()
server.Shutdown(ctx) // yeni bağlantı qəbul etmir, mövcudları gözləyir
```

- `ListenAndServe` artıq `http.ErrServerClosed` qaytarır
- Timeout keçsə context ləğv olur, `Shutdown` xəta qaytarır
- `context.Background()` — parent context olmadan

### sync.WaitGroup — Worker-ləri İzləmək

```go
var wg sync.WaitGroup

wg.Add(1)
go func() {
    defer wg.Done()
    // worker işi
}()

wg.Wait() // bütün worker-lər bitənə qədər gözlə
```

### stopCh Pattern — Worker-ləri Dayandırmaq

```go
stopCh := make(chan struct{})

// Worker
go func() {
    for {
        select {
        case <-stopCh:
            return // dayandır
        default:
            doWork()
        }
    }
}()

// Shutdown zamanı
close(stopCh) // bütün worker-lərə signal gedir
```

### Kubernetes terminationGracePeriodSeconds

```yaml
spec:
  terminationGracePeriodSeconds: 30  # default 30
```

Go tətbiqinin shutdown timeout-u bu dəyərdən **kiçik** olmalıdır:

```
Kubernetes: 30s
Go shutdown: 25s (5s ehtiyat)
```

Əks halda SIGKILL tətbiq shutdown bitməzdən əvvəl gəlir.

### Health Check + Readiness

Kubernetes-də:
- **Readiness probe** — tətbiq SIGTERM aldıqdan sonra dərhal 503 qaytarmalıdır
- **Liveness probe** — tətbiqin canlı olduğunu yoxlayır

```go
var isShuttingDown atomic.Bool

http.HandleFunc("/health/ready", func(w http.ResponseWriter, r *http.Request) {
    if isShuttingDown.Load() {
        http.Error(w, "shutting down", http.StatusServiceUnavailable)
        return
    }
    w.WriteHeader(http.StatusOK)
})
```

## Praktik Baxış

### Layihə Strukturunda Yerləşdirmə

```
cmd/api/main.go        ← signal handling + shutdown orchestration
internal/server/       ← http.Server wrapping
internal/worker/       ← background goroutine-lər
internal/database/     ← DB connection lifecycle
```

### Shutdown Hook Pattern

```go
type App struct {
    shutdown []func(ctx context.Context) error
}

func (a *App) OnShutdown(fn func(ctx context.Context) error) {
    a.shutdown = append(a.shutdown, fn)
}

func (a *App) Shutdown(ctx context.Context) error {
    for _, fn := range a.shutdown {
        if err := fn(ctx); err != nil {
            return err
        }
    }
    return nil
}
```

### Trade-off-lar

| Timeout çox qısa | Timeout çox uzun |
|-----------------|-----------------|
| Sorğular yarımçıq bitir | Kubernetes SIGKILL gönderir |
| Data itkisi riski | Rolling update yavaşlayır |

**Tövsiyə:** HTTP timeout ≤ 20s, shutdown timeout ≤ 25s (K8s 30s üçün).

### Anti-pattern-lər

```go
// Anti-pattern 1: os.Exit istifadəsi — defer-lar işləmir
sigCh <- signal.Notify(...)
<-sigCh
os.Exit(0) // DB close(), file flush() — heç biri işləmir!

// Anti-pattern 2: Shutdown timeout olmadan
server.Shutdown(context.Background()) // əbədi gözləyər əgər sorğu bitməsə

// Anti-pattern 3: Worker goroutine-i düzgün gözləməmək
close(stopCh)
// worker-lər bitmədən davam etmək — data race, resource leak

// Düzgün:
close(stopCh)
wg.Wait() // bitməyi gözlə

// Anti-pattern 4: SIGKILL-i tutmağa çalışmaq
signal.Notify(sigCh, syscall.SIGKILL) // işləmir! SIGKILL tutula bilməz

// Anti-pattern 5: Database-i birinci bağlamaq
db.Close()           // YANLIŞ — əvvəlcə server, worker, sonra DB
server.Shutdown(ctx) // artıq DB yoxdur — panic!

// Düzgün sıralama:
server.Shutdown(ctx) // HTTP
close(stopCh)        // workers
wg.Wait()
db.Close()           // DB — ən axırda
```

## Nümunələr

### Nümunə 1: Sadə HTTP Server Graceful Shutdown

```go
package main

import (
    "context"
    "fmt"
    "log"
    "net/http"
    "os"
    "os/signal"
    "syscall"
    "time"
)

func main() {
    mux := http.NewServeMux()

    mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
        // "Yavaş" sorğu simulyasiyası — shutdown-u test etmək üçün
        time.Sleep(3 * time.Second)
        fmt.Fprint(w, "OK")
    })

    mux.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
        w.WriteHeader(http.StatusOK)
        fmt.Fprint(w, "OK")
    })

    server := &http.Server{
        Addr:         ":8080",
        Handler:      mux,
        ReadTimeout:  15 * time.Second,
        WriteTimeout: 15 * time.Second,
        IdleTimeout:  60 * time.Second,
    }

    // Server-i ayrı goroutine-də başlat
    serverErr := make(chan error, 1)
    go func() {
        log.Println("Server :8080 başladı")
        if err := server.ListenAndServe(); err != http.ErrServerClosed {
            serverErr <- err
        }
        close(serverErr)
    }()

    // Signal gözlə
    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)

    select {
    case err := <-serverErr:
        log.Fatalf("Server xətası: %v", err)
    case sig := <-quit:
        log.Printf("Signal alındı: %v. Shutdown başlayır...", sig)
    }

    // 25 saniyə vaxt ver (Kubernetes 30s üçün ehtiyat)
    ctx, cancel := context.WithTimeout(context.Background(), 25*time.Second)
    defer cancel()

    if err := server.Shutdown(ctx); err != nil {
        log.Printf("Server shutdown xətası: %v", err)
        os.Exit(1)
    }

    log.Println("Server düzgün bağlandı")
}
```

### Nümunə 2: Production-ready Application

```go
package main

import (
    "context"
    "database/sql"
    "fmt"
    "log"
    "net/http"
    "os"
    "os/signal"
    "sync"
    "sync/atomic"
    "syscall"
    "time"
)

// Application — bütün komponentlər
type Application struct {
    server        *http.Server
    db            *sql.DB
    wg            sync.WaitGroup
    stopCh        chan struct{}
    isShuttingDown atomic.Bool
}

func NewApplication(db *sql.DB) *Application {
    app := &Application{
        db:     db,
        stopCh: make(chan struct{}),
    }

    mux := http.NewServeMux()
    mux.HandleFunc("/", app.apiHandler)
    mux.HandleFunc("/health/live", app.livenessHandler)
    mux.HandleFunc("/health/ready", app.readinessHandler)

    app.server = &http.Server{
        Addr:         ":8080",
        Handler:      mux,
        ReadTimeout:  15 * time.Second,
        WriteTimeout: 15 * time.Second,
        IdleTimeout:  60 * time.Second,
    }

    return app
}

func (app *Application) apiHandler(w http.ResponseWriter, r *http.Request) {
    // Sadə demo
    fmt.Fprintf(w, `{"status":"ok","time":"%s"}`, time.Now().Format(time.RFC3339))
}

func (app *Application) livenessHandler(w http.ResponseWriter, r *http.Request) {
    // Tətbiq sağdırsa 200
    w.WriteHeader(http.StatusOK)
    fmt.Fprint(w, `{"status":"alive"}`)
}

func (app *Application) readinessHandler(w http.ResponseWriter, r *http.Request) {
    // Shutdown başlayıbsa 503 — load balancer sorğu göndərməsin
    if app.isShuttingDown.Load() {
        http.Error(w, `{"status":"shutting_down"}`, http.StatusServiceUnavailable)
        return
    }
    // DB bağlantısı sağdır?
    if err := app.db.Ping(); err != nil {
        http.Error(w, `{"status":"db_down"}`, http.StatusServiceUnavailable)
        return
    }
    w.WriteHeader(http.StatusOK)
    fmt.Fprint(w, `{"status":"ready"}`)
}

// Background worker başlat
func (app *Application) StartWorker(name string, work func()) {
    app.wg.Add(1)
    go func() {
        defer app.wg.Done()
        log.Printf("[%s] worker başladı", name)

        ticker := time.NewTicker(5 * time.Second)
        defer ticker.Stop()

        for {
            select {
            case <-app.stopCh:
                log.Printf("[%s] worker dayandı", name)
                return
            case <-ticker.C:
                work()
            }
        }
    }()
}

// Düzgün bağlanma
func (app *Application) Shutdown(timeout time.Duration) error {
    log.Println("=== Graceful Shutdown başladı ===")

    // 1. Readiness probe-u 503-ə çevir
    app.isShuttingDown.Store(true)
    log.Println("[1] Readiness: 503")

    // Load balancer-in health check etməsini gözlə
    time.Sleep(2 * time.Second)

    ctx, cancel := context.WithTimeout(context.Background(), timeout)
    defer cancel()

    // 2. HTTP server-i bağla
    log.Println("[2] HTTP server dayandırılır...")
    if err := app.server.Shutdown(ctx); err != nil {
        return fmt.Errorf("http shutdown: %w", err)
    }
    log.Println("[2] HTTP server bağlandı")

    // 3. Worker-ləri dayandır
    log.Println("[3] Worker-lər dayandırılır...")
    close(app.stopCh)

    // Worker-lər timeout daxilində bitsin
    done := make(chan struct{})
    go func() {
        app.wg.Wait()
        close(done)
    }()

    select {
    case <-done:
        log.Println("[3] Worker-lər bağlandı")
    case <-ctx.Done():
        return fmt.Errorf("worker-lər vaxtında dayanmadı")
    }

    // 4. Database bağlantısını bağla
    log.Println("[4] Database bağlantısı bağlanır...")
    if err := app.db.Close(); err != nil {
        log.Printf("[4] DB close xətası: %v", err) // kritik deyil
    }
    log.Println("[4] Database bağlandı")

    log.Println("=== Graceful Shutdown tamamlandı ===")
    return nil
}

func (app *Application) Run() error {
    // Worker-ləri başlat
    app.StartWorker("cache-refresh", func() {
        log.Println("[cache] yenilənir...")
    })
    app.StartWorker("metrics-flush", func() {
        log.Println("[metrics] flush edilir...")
    })

    // HTTP server-i başlat
    serverErr := make(chan error, 1)
    go func() {
        log.Println("Server :8080 başladı")
        if err := app.server.ListenAndServe(); err != http.ErrServerClosed {
            serverErr <- err
        }
    }()

    // Signal gözlə
    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)

    select {
    case err := <-serverErr:
        return fmt.Errorf("server xətası: %w", err)
    case sig := <-quit:
        log.Printf("Signal alındı: %v", sig)
    }

    return app.Shutdown(25 * time.Second)
}

func main() {
    // Demo üçün fake DB
    db, _ := sql.Open("sqlite3", ":memory:")
    defer db.Close()

    app := NewApplication(db)
    if err := app.Run(); err != nil {
        log.Printf("Tətbiq xətası: %v", err)
        os.Exit(1)
    }

    log.Println("Proqram düzgün bağlandı")
}
```

### Nümunə 3: Shutdown Hook Pattern

```go
package main

import (
    "context"
    "fmt"
    "log"
    "os"
    "os/signal"
    "syscall"
    "time"
)

type ShutdownManager struct {
    hooks []func(ctx context.Context) error
}

func (sm *ShutdownManager) Register(name string, fn func(ctx context.Context) error) {
    sm.hooks = append(sm.hooks, func(ctx context.Context) error {
        log.Printf("[shutdown] %s başlayır...", name)
        if err := fn(ctx); err != nil {
            return fmt.Errorf("%s: %w", name, err)
        }
        log.Printf("[shutdown] %s tamamlandı", name)
        return nil
    })
}

func (sm *ShutdownManager) Execute(ctx context.Context) error {
    // Əks sırada icra et (LIFO — son əlavə olan birinci bağlanır)
    for i := len(sm.hooks) - 1; i >= 0; i-- {
        if err := sm.hooks[i](ctx); err != nil {
            log.Printf("Shutdown hook xətası: %v", err)
            // Davam et — digər hook-ları icra et
        }
    }
    return nil
}

func main() {
    sm := &ShutdownManager{}

    // DB connection — əvvəl əlavə, sonuncu bağlanır (LIFO)
    sm.Register("database", func(ctx context.Context) error {
        time.Sleep(100 * time.Millisecond) // fake cleanup
        fmt.Println("DB bağlandı")
        return nil
    })

    // HTTP server — sonra əlavə, birinci bağlanır
    sm.Register("http-server", func(ctx context.Context) error {
        time.Sleep(200 * time.Millisecond)
        fmt.Println("HTTP server bağlandı")
        return nil
    })

    // Redis — sonuncu əlavə, birinci bağlanır
    sm.Register("redis", func(ctx context.Context) error {
        time.Sleep(50 * time.Millisecond)
        fmt.Println("Redis bağlandı")
        return nil
    })

    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)

    fmt.Println("Server işləyir. Ctrl+C basın...")
    <-quit

    ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
    defer cancel()

    // Bağlanma sırası: redis → http-server → database (LIFO)
    sm.Execute(ctx)
    fmt.Println("Proqram tamamlandı")
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Kubernetes Ready:**
`/health/ready` endpoint-i yazın. Shutdown başladığında 503 qaytarsın. `readinessProbe` YAML konfiqurasiyasını da yazın.

**Tapşırıq 2 — Worker Pool Shutdown:**
10 goroutine-dən ibarət worker pool yazın. Hər worker cari tapşırığını tamamlayıb sonra dayanmalıdır. Tapşırıq 10s-dən uzun sürsə force stop edin.

**Tapşırıq 3 — Multi-server:**
HTTP + gRPC server eyni tətbiqdə çalışır. Hər ikisini graceful shutdown edin. Parallel shutdown edin (daha sürətli).

**Tapşırıq 4 — Test:**
`httptest.NewServer` ilə graceful shutdown test edin. Shutdown zamanı aktiv sorğunun tamamlandığını yoxlayın.

## PHP ilə Müqayisə

PHP-FPM-də worker process-lər PHP-FPM / Supervisor / Octane tərəfindən idarə olunur. Laravel Octane SIGTERM tutub mövcud request-i tamamlayır. Go-da bu logic özün yazılır — daha çox control, daha çox məsuliyyət.

| Aspekt | PHP (FPM/Octane) | Go |
|--------|------------------|----|
| Signal handling | FPM/Supervisor edir | Özün yazırsan |
| Worker lifecycle | Process manager idarə edir | `sync.WaitGroup` ilə |
| Graceful stop | Konfiqurasiya ilə | `server.Shutdown(ctx)` |
| Timeout | `pm.process_idle_timeout` | `context.WithTimeout` |

## Əlaqəli Mövzular

- [27-goroutines-and-channels](../core/27-goroutines-and-channels.md) — Goroutine idarəsi
- [28-context](../core/28-context.md) — Context ilə timeout
- [01-http-server](01-http-server.md) — HTTP server
- [12-processes-and-signals](12-processes-and-signals.md) — OS signal-ları
- [18-project-structure](18-project-structure.md) — Layihə strukturunda shutdown
- [70-docker-and-deploy](../advanced/23-docker-and-deploy.md) — Kubernetes/Docker-da SIGTERM
