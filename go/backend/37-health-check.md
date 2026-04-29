# Health Check Endpoints (Middle)

## İcmal

Health check endpoint-ləri orchestration sistemləri (Kubernetes, Docker Swarm, load balancer) üçün tətbiqin sağlamlığını bildirən HTTP endpointlərdir. Go-da standart bir paket yoxdur, lakin sadə implementasiya kifayətdir. İki növ check vacibdir: **liveness** (proses yaşayırmı) və **readiness** (sorğu qəbul edə bilərmi).

## Niyə Vacibdir

- Kubernetes liveness/readiness probe-ları olmadan pod yenidən başlamaz və ya yanlış pod-lara traffic gedir
- Dependency (DB, Redis, external API) down olduqda readiness check traffic dayandırır
- Deployment zamanı "rolling update" sağlam pod-lara keçidi təmin edir
- Production-da debugging: `/health` endpoint-i dependency vəziyyətini bir baxışda göstərir

## Əsas Anlayışlar

- **Liveness probe** — proses sonsuz loopa düşüb, deadlock, panic recover edildi — yenidən başlatmaq lazımdır
- **Readiness probe** — DB bağlantısı yoxdur, cache warm-up tamamlanmayıb — traffic göndərmə
- **`200 OK`** — sağlam; **`503 Service Unavailable`** — sağlam deyil
- **Startup probe** — tətbiq ilk başlayanda (Kubernetes 1.16+); readiness ilk başlanğıcda çox fail ola bilər
- **Dependency check timeout** — check özü çox gec qaytarmamalıdır (Kubernetes default 1s)

## Praktik Baxış

**Best practices:**

| Tövsiyə | Səbəb |
|---------|-------|
| Liveness-i sadə saxla | Yanlış restart olmaması üçün |
| Readiness-ə DB/Redis yoxla | Traffic düzgün yönlənsin |
| Check timeout ≤ 500ms | Probe timeout-dan az olmalıdır |
| `/metrics` ilə `/health`-i ayır | Health check yüngül olsun |
| Cache check nəticəsini | Hər probe-da DB sorğusu etmə |

**Trade-off-lar:**
- Çox dependency check: hər biri fail olla bilər → false positive; yalnız kritik dependency-ləri yoxla
- Liveness-ə DB check əlavə etmə — DB down olanda bütün pod-lar restart olar
- Health response-u sensitive data içərməsin (internal IP, version, dependency URL)

## Nümunələr

### Nümunə 1: Sadə liveness + readiness

```go
package main

import (
    "context"
    "database/sql"
    "encoding/json"
    "net/http"
    "time"
)

type HealthChecker struct {
    db    *sql.DB
    redis interface{ Ping(context.Context) error }
}

type HealthStatus struct {
    Status     string            `json:"status"`
    Checks     map[string]string `json:"checks,omitempty"`
    Duration   string            `json:"duration,omitempty"`
}

// Liveness — proses yaşayırmı (sadə olsun)
func (h *HealthChecker) Liveness(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(http.StatusOK)
    json.NewEncoder(w).Encode(HealthStatus{Status: "ok"})
}

// Readiness — bağlantılar hazırdırmı
func (h *HealthChecker) Readiness(w http.ResponseWriter, r *http.Request) {
    start := time.Now()
    ctx, cancel := context.WithTimeout(r.Context(), 500*time.Millisecond)
    defer cancel()

    checks := make(map[string]string)
    healthy := true

    // DB yoxla
    if err := h.db.PingContext(ctx); err != nil {
        checks["database"] = "unhealthy: " + err.Error()
        healthy = false
    } else {
        checks["database"] = "ok"
    }

    status := HealthStatus{
        Checks:   checks,
        Duration: time.Since(start).String(),
    }

    if healthy {
        status.Status = "ok"
        w.WriteHeader(http.StatusOK)
    } else {
        status.Status = "degraded"
        w.WriteHeader(http.StatusServiceUnavailable)
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(status)
}

func main() {
    db, _ := sql.Open("postgres", "postgres://...")
    checker := &HealthChecker{db: db}

    mux := http.NewServeMux()
    mux.HandleFunc("/health/live", checker.Liveness)
    mux.HandleFunc("/health/ready", checker.Readiness)
    mux.HandleFunc("/", appHandler)

    http.ListenAndServe(":8080", mux)
}
```

### Nümunə 2: Çox dependency check

```go
type Check func(ctx context.Context) error

type HealthService struct {
    checks map[string]Check
}

func NewHealthService() *HealthService {
    return &HealthService{checks: make(map[string]Check)}
}

func (h *HealthService) Register(name string, check Check) {
    h.checks[name] = check
}

func (h *HealthService) Check(ctx context.Context) (bool, map[string]string) {
    results := make(map[string]string)
    healthy := true

    for name, check := range h.checks {
        if err := check(ctx); err != nil {
            results[name] = "unhealthy: " + err.Error()
            healthy = false
        } else {
            results[name] = "ok"
        }
    }
    return healthy, results
}

func (h *HealthService) Handler() http.HandlerFunc {
    return func(w http.ResponseWriter, r *http.Request) {
        ctx, cancel := context.WithTimeout(r.Context(), 500*time.Millisecond)
        defer cancel()

        healthy, checks := h.Check(ctx)
        status := map[string]any{"checks": checks}

        w.Header().Set("Content-Type", "application/json")
        if healthy {
            status["status"] = "ok"
            w.WriteHeader(http.StatusOK)
        } else {
            status["status"] = "degraded"
            w.WriteHeader(http.StatusServiceUnavailable)
        }
        json.NewEncoder(w).Encode(status)
    }
}

// İstifadə
func main() {
    db, _ := sql.Open("postgres", "...")
    rdb := redis.NewClient(&redis.Options{Addr: "localhost:6379"})

    hs := NewHealthService()

    hs.Register("database", func(ctx context.Context) error {
        return db.PingContext(ctx)
    })

    hs.Register("redis", func(ctx context.Context) error {
        return rdb.Ping(ctx).Err()
    })

    hs.Register("disk", func(ctx context.Context) error {
        // disk space yoxla
        var stat syscall.Statfs_t
        if err := syscall.Statfs("/", &stat); err != nil {
            return err
        }
        freePercent := float64(stat.Bfree) / float64(stat.Blocks) * 100
        if freePercent < 10 {
            return fmt.Errorf("disk boş yer azdır: %.1f%%", freePercent)
        }
        return nil
    })

    mux := http.NewServeMux()
    mux.Handle("/health/ready", hs.Handler())
    mux.HandleFunc("/health/live", func(w http.ResponseWriter, r *http.Request) {
        w.WriteHeader(http.StatusOK)
        w.Write([]byte(`{"status":"ok"}`))
    })

    http.ListenAndServe(":8080", mux)
}
```

### Nümunə 3: Kubernetes probe konfiqurasiyası

```yaml
# kubernetes deployment.yaml
apiVersion: apps/v1
kind: Deployment
spec:
  template:
    spec:
      containers:
        - name: api
          image: myapp:latest
          ports:
            - containerPort: 8080

          livenessProbe:
            httpGet:
              path: /health/live
              port: 8080
            initialDelaySeconds: 10
            periodSeconds: 10
            failureThreshold: 3
            timeoutSeconds: 2

          readinessProbe:
            httpGet:
              path: /health/ready
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 5
            failureThreshold: 3
            timeoutSeconds: 2

          startupProbe:
            httpGet:
              path: /health/live
              port: 8080
            failureThreshold: 30   # 30 * 10s = 5 dəqiqə startup vaxtı
            periodSeconds: 10
```

### Nümunə 4: Version + build info endpoint

```go
var (
    Version   = "dev"
    BuildTime = "unknown"
    GitCommit = "unknown"
)

func infoHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(map[string]string{
        "version":    Version,
        "build_time": BuildTime,
        "git_commit": GitCommit,
    })
}

// Build zamanı inject: go build -ldflags "-X main.Version=1.2.3"
```

## Praktik Tapşırıqlar

1. **Basic health:** Liveness + readiness endpoint yaz; DB ping ilə readiness test et
2. **Graceful start:** Server başlayanda readiness-i `false` qoy, DB ready olduqda `true` et
3. **Custom checks:** Memory usage, goroutine sayı, son DB migration versiyasını yoxlayan check
4. **Load test:** k6 ilə `/health/ready`-nin yüksək RPM altında davranışını test et

## PHP ilə Müqayisə

```
PHP/Laravel                    →  Go
────────────────────────────────────────
/up route (Laravel 10+)        →  /health/live
custom health check service    →  HealthService struct
DB::connection()->getPdo()     →  db.PingContext(ctx)
```

Laravel 10+ `/up` endpoint-i var lakin sadədir. Kubernetes-dən istifadə edəndə readiness probe üçün ayrıca endpoint lazım olur.

## Əlaqəli Mövzular

- [01-http-server](01-http-server.md) — HTTP server qurma
- [05-database](05-database.md) — db.PingContext
- [17-graceful-shutdown](17-graceful-shutdown.md) — shutdown zamanı readiness false et
- [../core/28-context](../core/28-context.md) — check timeout
- [../advanced/24-monitoring-and-observability](../advanced/24-monitoring-and-observability.md) — Prometheus metrics ilə birlikdə
