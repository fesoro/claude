# Middleware and Routing (Senior)

## İcmal

Go 1.22-dən standart `http.ServeMux` method filteri (`GET /users`) və path parametrləri (`/users/{id}`) dəstəkləyir. Middleware isə `func(http.Handler) http.Handler` imzasına sahib olan funksiyalardır — handler-ləri zərflərərək əvvəl/sonra logic icra edirlər. Chi router isə standart kitabxanadan daha zəngin middleware ekosistemi, route qrupları, named parametrlər və s. verir.

## Niyə Vacibdir

- Logging, auth, CORS, rate limiting, recovery — hər HTTP handler-ə ayrıca yazılsa kod duplicate olur, middleware isə bunu mərkəzləşdirir
- Middleware chain-in sırası vacibdir: authentication-dan əvvəl logging gəlmélıdir, recovery isə həmişə ən xaricdə olmalıdır
- Go 1.22-dən `r.PathValue("id")` — xarici router olmadan path parametri almaq mümkündür
- Chi vs standart kitabxana seçimi layihənin ölçüsünə görə edilməlidir

## Əsas Anlayışlar

**`http.Handler` interfeysi**

```go
type Handler interface {
    ServeHTTP(ResponseWriter, *Request)
}
```

**`http.HandlerFunc`** — funksiyaları `http.Handler`-ə çevirən adapter.

**Middleware tipi** — Go-da standartlaşmış imza:
```go
type Middleware func(http.Handler) http.Handler
```

**Middleware chain** — Bir handler-in ətrafına bir neçə middleware sarılması. İcra sırası qeydiyyat sırasının əksidir (onion model).

**`http.ServeMux` (Go 1.22+)**

```go
mux.HandleFunc("GET /users/{id}", handler)
// Method filtri + path parametri
```

**`chi.Router`** — `net/http`-uyğun, hafif router kitabxanası. `github.com/go-chi/chi/v5`.

**`r.Context()`** — Middleware-lər context vasitəsilə məlumat ötürür (user ID, request ID və s.).

## Praktik Baxış

**Ne zaman standart `ServeMux` kifayətdir:**

- Kiçik servislər, az sayda endpoint
- Go 1.22+ — method filter + path params kifayətdir
- Xarici dependency əlavə etmək istəmirsən

**Ne zaman Chi (və ya başqa router) seçmək lazımdır:**

- Mürəkkəb nested route qrupları (`/api/v1/users/{id}/posts`)
- Middleware-i yalnız bəzi route qruplarına tətbiq etmək
- `404` / `405` handler-lərini özelləşdirmək
- Wildcard parametrlər (`/files/*path`)
- Zəngin middleware ekosistemi (chi/middleware-də hazır: Logger, Recoverer, Compress, RealIP, RequestID...)

**Middleware yazma qaydaları:**

- `next.ServeHTTP(w, r)` çağırılmasa zəncir qırılır — authentication reddedildikdə istifadə et
- Body yalnız bir dəfə oxuna bilər — middleware-də body oxusan handler artıq oxuya bilmir
- `context.WithValue` ilə məlumat ötür — amma yalnız request-scoped məlumat üçün
- Middleware özü goroutine başlatmasın — deadlock riski

**Anti-pattern-lər:**

- Global state-ə yazmaq middleware daxilindən — race condition
- Middleware-dən sonra `w.WriteHeader` çağırmaq cəhdi — header artıq göndərilib
- Middleware order-ini yanlış qurmaq: recovery ən xaricdə olmalıdır
- `context.WithValue` ilə string key istifadəsi — package-level type key istifadə et

## Nümunələr

### Nümunə 1: Middleware Fundamentals

```go
package main

import (
    "context"
    "log"
    "net/http"
    "time"
)

// Middleware tipi
type Middleware func(http.Handler) http.Handler

// Middleware-ləri zəncirləmək — sağdan sola sarılır
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
        log.Printf("→ [%s] %s", r.Method, r.URL.Path)
        next.ServeHTTP(w, r) // növbəti handler-i çağır
        log.Printf("← [%s] %s %s", r.Method, r.URL.Path, time.Since(start))
    })
}

// Recovery middleware — panic-i tutur, 500 qaytarır
func Recovery(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        defer func() {
            if err := recover(); err != nil {
                log.Printf("PANIC: %v", err)
                http.Error(w, "Daxili server xətası", 500)
            }
        }()
        next.ServeHTTP(w, r)
    })
}

// CORS middleware
func CORS(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("Access-Control-Allow-Origin", "*")
        w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
        w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")

        // Preflight sorğusunu burada bitir
        if r.Method == http.MethodOptions {
            w.WriteHeader(http.StatusNoContent)
            return
        }
        next.ServeHTTP(w, r)
    })
}

func main() {
    mux := http.NewServeMux()
    mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
        w.Write([]byte("Salam!"))
    })

    // Zəncir: Recovery(CORS(Logger(mux)))
    // İcra sırası: Recovery → CORS → Logger → mux → Logger → CORS → Recovery
    handler := Chain(mux, Recovery, CORS, Logger)
    http.ListenAndServe(":8080", handler)
}
```

### Nümunə 2: Context ilə Məlumat Ötürmə

```go
package main

import (
    "context"
    "fmt"
    "net/http"
)

// Type-safe context key — string key-dən daha yaxşıdır
type contextKey string

const (
    keyRequestID contextKey = "requestID"
    keyUserID    contextKey = "userID"
)

// RequestID middleware — hər sorğuya ID əlavə et
func RequestID(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        id := generateID() // UUID yarat
        ctx := context.WithValue(r.Context(), keyRequestID, id)
        w.Header().Set("X-Request-ID", id)
        next.ServeHTTP(w, r.WithContext(ctx)) // context-i yenilənmiş request ilə ötür
    })
}

// Auth middleware — token yoxla, user ID-ni context-ə yaz
func Auth(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        token := r.Header.Get("Authorization")
        if token == "" {
            http.Error(w, `{"xeta":"token lazımdır"}`, http.StatusUnauthorized)
            return // next çağırılmır — zəncir burada qırılır
        }

        userID, err := validateToken(token)
        if err != nil {
            http.Error(w, `{"xeta":"etibarsız token"}`, http.StatusForbidden)
            return
        }

        // User ID-ni context-ə yaz
        ctx := context.WithValue(r.Context(), keyUserID, userID)
        next.ServeHTTP(w, r.WithContext(ctx))
    })
}

// Handler-dən context dəyərini almaq
func profileHandler(w http.ResponseWriter, r *http.Request) {
    userID := r.Context().Value(keyUserID).(string)
    reqID := r.Context().Value(keyRequestID).(string)
    fmt.Fprintf(w, "User: %s, ReqID: %s", userID, reqID)
}

func generateID() string    { return "uuid-xxx" }
func validateToken(t string) (string, error) { return "user-1", nil }
```

### Nümunə 3: Go 1.22+ Standart Routing

```go
package main

import (
    "encoding/json"
    "net/http"
)

func main() {
    mux := http.NewServeMux()

    // Method filtri + path pattern
    mux.HandleFunc("GET /users", listUsers)
    mux.HandleFunc("POST /users", createUser)
    mux.HandleFunc("GET /users/{id}", getUser)      // path parametri
    mux.HandleFunc("PUT /users/{id}", updateUser)
    mux.HandleFunc("DELETE /users/{id}", deleteUser)

    // Wildcard
    mux.HandleFunc("GET /files/{path...}", serveFile) // /files/a/b/c

    // GET /users/{id} — path parametrini almaq
    http.ListenAndServe(":8080", mux)
}

func getUser(w http.ResponseWriter, r *http.Request) {
    id := r.PathValue("id") // Go 1.22+
    // id == "42" əgər sorğu GET /users/42 idi
    json.NewEncoder(w).Encode(map[string]string{"id": id})
}

func listUsers(w http.ResponseWriter, r *http.Request)  {}
func createUser(w http.ResponseWriter, r *http.Request) {}
func updateUser(w http.ResponseWriter, r *http.Request) {}
func deleteUser(w http.ResponseWriter, r *http.Request) {}
func serveFile(w http.ResponseWriter, r *http.Request)  {}
```

### Nümunə 4: Chi Router ilə Route Qrupları

```go
package main

import (
    "encoding/json"
    "net/http"

    "github.com/go-chi/chi/v5"
    chimiddleware "github.com/go-chi/chi/v5/middleware"
)

func main() {
    r := chi.NewRouter()

    // Qlobal middleware-lər
    r.Use(chimiddleware.Logger)
    r.Use(chimiddleware.Recoverer)
    r.Use(chimiddleware.RequestID)
    r.Use(chimiddleware.RealIP)

    // Public route-lar
    r.Get("/", func(w http.ResponseWriter, r *http.Request) {
        w.Write([]byte("API v1"))
    })
    r.Post("/auth/login", loginHandler)

    // Yalnız autentifikasiyalı route-lar
    r.Group(func(r chi.Router) {
        r.Use(authMiddleware) // Bu qrupa aid middleware

        r.Get("/profile", getProfile)
        r.Put("/profile", updateProfile)

        // Nested qrup — yalnız adminlər
        r.Group(func(r chi.Router) {
            r.Use(adminMiddleware)
            r.Get("/admin/users", listAllUsers)
            r.Delete("/admin/users/{id}", deleteAnyUser)
        })
    })

    // API versiyalaşdırma
    r.Route("/api/v1", func(r chi.Router) {
        r.Use(chimiddleware.Compress(5)) // Compression bu qrupa

        r.Route("/posts", func(r chi.Router) {
            r.Get("/", listPosts)
            r.Post("/", createPost)
            r.Get("/{id}", getPost)
            r.Put("/{id}", updatePost)
            r.Delete("/{id}", deletePost)

            // Nested resource
            r.Get("/{postID}/comments", listComments)
        })
    })

    http.ListenAndServe(":8080", r)
}

// Chi path parametrini almaq
func getPost(w http.ResponseWriter, r *http.Request) {
    id := chi.URLParam(r, "id")
    json.NewEncoder(w).Encode(map[string]string{"id": id})
}

func loginHandler(w http.ResponseWriter, r *http.Request) {}
func authMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        next.ServeHTTP(w, r)
    })
}
func adminMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        next.ServeHTTP(w, r)
    })
}
func getProfile(w http.ResponseWriter, r *http.Request)   {}
func updateProfile(w http.ResponseWriter, r *http.Request) {}
func listAllUsers(w http.ResponseWriter, r *http.Request) {}
func deleteAnyUser(w http.ResponseWriter, r *http.Request) {}
func listPosts(w http.ResponseWriter, r *http.Request)    {}
func createPost(w http.ResponseWriter, r *http.Request)   {}
func updatePost(w http.ResponseWriter, r *http.Request)   {}
func deletePost(w http.ResponseWriter, r *http.Request)   {}
func listComments(w http.ResponseWriter, r *http.Request) {}
```

### Nümunə 5: ResponseWriter Wraper — Status Kodu Yazmaq

```go
package main

import (
    "log"
    "net/http"
    "time"
)

// ResponseWriter-i sarmal — status kodu qeyd etmək üçün
type responseWriter struct {
    http.ResponseWriter
    statusCode int
    written    bool
}

func newResponseWriter(w http.ResponseWriter) *responseWriter {
    return &responseWriter{ResponseWriter: w, statusCode: http.StatusOK}
}

func (rw *responseWriter) WriteHeader(code int) {
    if !rw.written {
        rw.statusCode = code
        rw.written = true
        rw.ResponseWriter.WriteHeader(code)
    }
}

// Status kodunu da log-a yazan middleware
func LoggerWithStatus(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        start := time.Now()
        rw := newResponseWriter(w)
        next.ServeHTTP(rw, r)
        log.Printf("[%d] %s %s %s", rw.statusCode, r.Method, r.URL.Path, time.Since(start))
    })
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Middleware Stack**

Aşağıdakı middleware-ləri yaz və test et:

- `RateLimit(rps int)` — IP-ə görə saniyədə `rps` sorğu, artıqda 429
- `BasicAuth(user, pass string)` — `Authorization: Basic ...` header-i yoxla
- `Timeout(d time.Duration)` — request context-inə timeout qoy
- Hamısını chain et: `Recovery → Logger → CORS → Timeout → RateLimit → Auth → mux`

**Tapşırıq 2 — Chi Router ilə Blog API**

```
POST   /auth/login         → JWT qaytar
GET    /posts              → siyahı (public)
GET    /posts/{id}         → tək yazı (public)
POST   /posts              → yeni yazı (auth lazım)
PUT    /posts/{id}         → yenilə (auth + owner yoxla)
DELETE /posts/{id}         → sil (admin)
GET    /posts/{id}/comments → şərhlər (public)
POST   /posts/{id}/comments → şərh əlavə et (auth)
```

**Tapşırıq 3 — Request ID Tracing**

- Hər sorğuya UUID request ID əlavə et (həm header-dən oxu, həm yenisini yarat)
- Bütün log mesajlarında request ID göstər
- Response header-ə `X-Request-ID` yaz

## PHP ilə Müqayisə

PHP/Laravel-də middleware `app/Http/Middleware` sinfləri idi, route-lar `routes/web.php`-də qeydiyyatdan keçirdi. Go-da həmin anlayışlar eynidir — fərq yalnız sintaksisdədir.

## Əlaqəli Mövzular

- [01-http-server](01-http-server.md) — HTTP server əsasları
- [02-http-client](02-http-client.md) — HTTP client
- [04-httptest](04-httptest.md) — Handler və middleware testləri
- [28-context](../core/28-context.md) — Context vasitəsilə məlumat ötürmə
- [65-jwt-and-auth](../advanced/10-jwt-and-auth.md) — JWT ilə authentication middleware
- [51-rate-limiting](15-rate-limiting.md) — Rate limiting implementasiyası
