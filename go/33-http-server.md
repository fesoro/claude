# HTTP Server (Senior)

## İcmal

Go-da HTTP server yaratmaq üçün standart `net/http` paketi istifadə olunur — xarici framework lazım deyil. `http.Handler` interfeysi, `ServeMux` router-i, graceful shutdown mexanizmi və production-a hazır server konfiqurasiyası bu mövzunun əsasını təşkil edir.

PHP/Laravel-də `php-fpm` + `nginx` birlikdə işləyirdi. Go-da isə bütün bu funksionallıq tək bir binary daxilindədir — öz HTTP server-ini özün yazırsan.

## Niyə Vacibdir

- Go xidmətlərinin böyük əksəriyyəti `net/http` üzərində qurulur
- Standart kitabxana kifayətdir — çox hallarda Gin/Echo/Chi kimi framework əlavə yük yaradır
- Handler, middleware, mux anlayışları bütün Go HTTP framework-lərinin təməlidir
- Graceful shutdown production-da məcburi bilikdir — serverin düzgün bağlanmaması data itkisinə səbəb ola bilər

## Əsas Anlayışlar

**`http.Handler` interfeysi**

```go
type Handler interface {
    ServeHTTP(ResponseWriter, *Request)
}
```

Go-da hər şey bu interfeysi implement edən bir dəyərdir.

**`http.HandlerFunc`** — funksiyaları handler-ə çevirən adapter type-dır.

**`http.ResponseWriter`** — HTTP cavabını yazır: status kodu, header-lər, body.

**`*http.Request`** — gələn sorğunu təsvir edir: method, URL, header, body.

**`http.ServeMux`** — URL pattern-lərini handler-lərə map edir (router). Go 1.22-dən method filteri (`GET /users`) və path parametrləri (`/users/{id}`) dəstəklənir.

**`http.Server`** — server konfiqurasiyasını saxlayan struct. Timeout-lar burada təyin olunur.

## Praktik Baxış

**Production-da mütləq edilməli olanlar:**

1. `ReadTimeout`, `WriteTimeout`, `IdleTimeout` — timeout-suz server açıq qalır, resource leak
2. `Graceful shutdown` — gələn sorğular tamamlanmadan server bağlanmamalıdır
3. `Error log` — server xətaları stderr-ə yazılmalıdır
4. `TLS` — production-da HTTPS məcburidir

**Trade-off-lar:**

| Standart `net/http` | Chi / Gin / Echo |
|---------------------|-----------------|
| Sıfır dependency | Əlavə kitabxana |
| Go 1.22+ ilə güclü routing | Daha zəngin middleware ekosistemi |
| Daha az magic | Daha az boilerplate |
| Kiçik servislərdə ideal | Böyük REST API-lərdə sürət qazandırır |

**Anti-pattern-lər:**

- `http.ListenAndServe` birbaşa çağırmaq — graceful shutdown imkansız olur
- Default `http.DefaultServeMux` istifadəsi — bütün paketlər ona route qeydiyyat edə bilər, gizli endpoint-lər açılır
- Handler daxilindən `log.Fatal` çağırmaq — server-i öldürür
- Body-ni oxumamaq — memory leak
- `WriteHeader` sonradan header set etmək cəhdi — header artıq göndərilib

## Nümunələr

### Nümunə 1: Minimal HTTP Server

```go
package main

import (
    "fmt"
    "log"
    "net/http"
)

func main() {
    mux := http.NewServeMux()

    mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
        fmt.Fprintln(w, "Salam, Go HTTP Server!")
    })

    mux.HandleFunc("GET /health", func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("Content-Type", "application/json")
        fmt.Fprintln(w, `{"status": "ok"}`)
    })

    server := &http.Server{
        Addr:    ":8080",
        Handler: mux,
    }

    log.Println("Server :8080 portunda başladı")
    if err := server.ListenAndServe(); err != nil {
        log.Fatal(err)
    }
}
```

### Nümunə 2: JSON REST Handler — Struct + Helper

```go
package main

import (
    "encoding/json"
    "net/http"
    "log"
)

type Kitab struct {
    ID      int     `json:"id"`
    Ad      string  `json:"ad"`
    Qiymet  float64 `json:"qiymet"`
}

var kitablar = []Kitab{
    {ID: 1, Ad: "Go in Action", Qiymet: 29.99},
    {ID: 2, Ad: "The Go Programming Language", Qiymet: 34.99},
}

// JSON cavab göndərmək üçün helper
func jsonYaz(w http.ResponseWriter, status int, data any) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(status)
    if err := json.NewEncoder(w).Encode(data); err != nil {
        log.Printf("JSON encode xətası: %v", err)
    }
}

// JSON xəta cavabı
func jsonXeta(w http.ResponseWriter, status int, mesaj string) {
    jsonYaz(w, status, map[string]string{"xeta": mesaj})
}

func main() {
    mux := http.NewServeMux()

    mux.HandleFunc("GET /kitablar", func(w http.ResponseWriter, r *http.Request) {
        jsonYaz(w, http.StatusOK, kitablar)
    })

    mux.HandleFunc("POST /kitablar", func(w http.ResponseWriter, r *http.Request) {
        var kitab Kitab
        if err := json.NewDecoder(r.Body).Decode(&kitab); err != nil {
            jsonXeta(w, http.StatusBadRequest, "yanlış JSON formatı")
            return
        }
        if kitab.Ad == "" {
            jsonXeta(w, http.StatusBadRequest, "ad sahəsi məcburidir")
            return
        }
        kitab.ID = len(kitablar) + 1
        kitablar = append(kitablar, kitab)
        jsonYaz(w, http.StatusCreated, kitab)
    })

    mux.HandleFunc("GET /kitablar/{id}", func(w http.ResponseWriter, r *http.Request) {
        id := r.PathValue("id") // Go 1.22+
        for _, k := range kitablar {
            if fmt.Sprintf("%d", k.ID) == id {
                jsonYaz(w, http.StatusOK, k)
                return
            }
        }
        jsonXeta(w, http.StatusNotFound, "kitab tapılmadı")
    })

    http.ListenAndServe(":8080", mux)
}
```

### Nümunə 3: Production-Ready Server — Timeout + Graceful Shutdown

```go
package main

import (
    "context"
    "log"
    "net/http"
    "os"
    "os/signal"
    "syscall"
    "time"
)

func newServer(addr string, handler http.Handler) *http.Server {
    return &http.Server{
        Addr:    addr,
        Handler: handler,

        // Timeout-lar — production-da məcburidir
        ReadTimeout:       5 * time.Second,  // header + body oxuma
        ReadHeaderTimeout: 2 * time.Second,  // yalnız header oxuma
        WriteTimeout:      10 * time.Second, // cavab yazma
        IdleTimeout:       120 * time.Second, // keep-alive bağlantı
    }
}

func main() {
    mux := http.NewServeMux()
    mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
        w.Write([]byte("OK"))
    })

    srv := newServer(":8080", mux)

    // Server-i ayrı goroutine-də başlat
    go func() {
        log.Println("Server başladı: :8080")
        if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
            log.Fatalf("Server xətası: %v", err)
        }
    }()

    // OS siqnalını gözlə (Ctrl+C, SIGTERM — Docker/K8s bağlama siqnalı)
    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
    <-quit

    log.Println("Server bağlanır...")

    // 30 saniyə ərzində aktiv sorğuların tamamlanmasını gözlə
    ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
    defer cancel()

    if err := srv.Shutdown(ctx); err != nil {
        log.Fatalf("Məcburi bağlama: %v", err)
    }

    log.Println("Server düzgün bağlandı")
}
```

### Nümunə 4: Middleware Chain

```go
package main

import (
    "log"
    "net/http"
    "time"
)

type Middleware func(http.Handler) http.Handler

// Middleware-ləri zəncirləmək
func Chain(h http.Handler, middlewares ...Middleware) http.Handler {
    for i := len(middlewares) - 1; i >= 0; i-- {
        h = middlewares[i](h)
    }
    return h
}

// Logging middleware
func Logger(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        start := time.Now()
        next.ServeHTTP(w, r)
        log.Printf("[%s] %s %s", r.Method, r.URL.Path, time.Since(start))
    })
}

// Recovery middleware — panic-ləri tutur
func Recovery(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        defer func() {
            if err := recover(); err != nil {
                log.Printf("PANIC: %v", err)
                http.Error(w, "Daxili server xətası", http.StatusInternalServerError)
            }
        }()
        next.ServeHTTP(w, r)
    })
}

func main() {
    mux := http.NewServeMux()
    mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
        w.Write([]byte("Salam!"))
    })

    // İcra sırası: Recovery → Logger → mux
    handler := Chain(mux, Recovery, Logger)

    http.ListenAndServe(":8080", handler)
}
```

### Nümunə 5: Static File Server

```go
// Statik faylları serve etmək
mux.Handle("/static/", http.StripPrefix("/static/",
    http.FileServer(http.Dir("./public")),
))

// Embed olunmuş fayllarla (go:embed)
//go:embed public/*
var publicFS embed.FS

sub, _ := fs.Sub(publicFS, "public")
mux.Handle("/static/", http.StripPrefix("/static/", http.FileServer(http.FS(sub))))
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Kitab API (CRUD)**

Kitablar üçün tam REST API yaz:
- `GET /kitablar` — hamısını qaytar
- `GET /kitablar/{id}` — birini qaytar
- `POST /kitablar` — yeni əlavə et (JSON validation ilə)
- `PUT /kitablar/{id}` — yenilə
- `DELETE /kitablar/{id}` — sil
- In-memory `map[int]Kitab` istifadə et

**Tapşırıq 2 — Middleware Stack**

Aşağıdakı middleware-ləri yaz və chain et:
- `RequestID` — hər sorğuya UUID əlavə et, response header-ə yaz
- `CORS` — `Access-Control-*` header-ləri qoy
- `Recover` — panic-i tut, 500 qaytar
- `Logger` — method, path, duration, status log yaz

**Tapşırıq 3 — Graceful Shutdown Test**

`curl -X POST /ağır-iş &` ilə uzun sürən sorğu göndər, dərhal `Ctrl+C` vur. Server 30 saniyə gözləməlidir. `SIGTERM` siqnalı ilə test et.

**Tapşırıq 4 — Health Check Endpoint**

```
GET /health → {"status":"ok","uptime":"2h30m"}
GET /ready → DB ping et, DB down isə 503 qaytar
```

## Əlaqəli Mövzular

- [34-http-client](34-http-client.md) — xarici API-lərə sorğu göndərmək
- [35-middleware-and-routing](35-middleware-and-routing.md) — Chi router ilə routing, middleware pattern
- [36-httptest](36-httptest.md) — HTTP handler-lərin test edilməsi
- [53-graceful-shutdown](53-graceful-shutdown.md) — Graceful shutdown dərindən
- [28-context](28-context.md) — Request context, timeout, cancellation
- [25-logging](25-logging.md) — Structured logging
