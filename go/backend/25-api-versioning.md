# API Versioning (Senior)

## İcmal

API versioning — breaking change-ləri köhnə clientlərə görünməz etmək strategiyasıdır. Go-nun standart `net/http` paketi versioning üçün bütün alətlərə malikdir.

## Niyə Vacibdir

- API endpoint-i istifadəçilərə açıqsa, onu birdən dəyişmək olmaz — client-lər pozulur
- Mobile app-lar yenilənmir — köhnə versiyalar aylarca, illərcə istifadə edilir
- Breaking change olmadan tətbiq tez inkişaf edir, amma müqavilə pozulmur

## Əsas Anlayışlar

**Üç əsas yanaşma:**

**1. URL Path versioning** `/v1/users`, `/v2/users`
- Ən geniş yayılmış, ən sadə
- Cache-ə uyğundur (URL fərqlidir)
- Swagger/OpenAPI üçün asan
- Minus: URL-i çirkləndirir, köhnə versiyalar həmişə var

**2. Header versioning** `Accept: application/vnd.myapi.v2+json`
- URL temiz qalır
- Cache çətin (Vary header lazımdır)
- Client-lər üçün əlavə header — daha mürəkkəb

**3. Query parameter** `/users?version=2`
- Asan test edilir (browser-dən)
- Production üçün tövsiyə edilmir

**Deprecation strategiyası:**
- `Sunset` header — köhnə versiya nə vaxt çıxacaq
- `Deprecation` header — köhnə versiyadan istifadə bildirişi
- Log köhnə versiya çağırışlarını — kim hələ istifadə edir?

## Praktik Baxış

**Ən yaxşı seçim:** URL Path versioning — ən geniş dəstək, asan debug

**Nə vaxt versiya artır:**
- Breaking change: sahə silmək, tip dəyişmək, endpoint silmək
- Non-breaking: yeni sahə əlavə etmək, yeni endpoint — versiya lazım deyil

**Ne vaxt versiya lazım deyil:**
- Daxili API (backend-backend) — tez refaktor etmək mümkündür
- Versiya ilə yanaşı kod saxlamaq pahalıdır — az versiya, çox müddət dəstək

**Common mistakes:**
- Hər kiçik dəyişiklik üçün versiya artırmaq
- Köhnə versiyaları heç silməmək — 5 versiya paralel çalışır
- Versiyalar arasında kod paylaşmamaq — hər versiya duplicate

## Nümunələr

### Nümunə 1: URL path versioning — Go 1.22 ServeMux

```go
package main

import (
    "encoding/json"
    "net/http"
)

// V1 User — köhnə format
type UserV1 struct {
    ID   int64  `json:"id"`
    Name string `json:"name"` // "name" sahəsi
}

// V2 User — yeni format (name → first_name + last_name)
type UserV2 struct {
    ID        int64  `json:"id"`
    FirstName string `json:"first_name"`
    LastName  string `json:"last_name"`
    Email     string `json:"email"` // yeni sahə
}

func setupRouter() *http.ServeMux {
    mux := http.NewServeMux()

    // V1 endpoints
    mux.HandleFunc("GET /v1/users/{id}", getUserV1)
    mux.HandleFunc("POST /v1/users", createUserV1)

    // V2 endpoints — breaking change: name → first_name/last_name
    mux.HandleFunc("GET /v2/users/{id}", getUserV2)
    mux.HandleFunc("POST /v2/users", createUserV2)

    // Versiyasız → default versiyaya yönləndir
    mux.HandleFunc("GET /users/{id}", func(w http.ResponseWriter, r *http.Request) {
        http.Redirect(w, r, "/v2/users/"+r.PathValue("id"), http.StatusMovedPermanently)
    })

    return mux
}

func getUserV1(w http.ResponseWriter, r *http.Request) {
    addDeprecationHeaders(w, "2025-12-31")

    user := UserV1{ID: 1, Name: "Orxan Şükürlü"}
    json.NewEncoder(w).Encode(user)
}

func getUserV2(w http.ResponseWriter, r *http.Request) {
    user := UserV2{
        ID:        1,
        FirstName: "Orxan",
        LastName:  "Şükürlü",
        Email:     "orxan@example.com",
    }
    json.NewEncoder(w).Encode(user)
}

func createUserV1(w http.ResponseWriter, r *http.Request) {}
func createUserV2(w http.ResponseWriter, r *http.Request) {}

// Deprecation header-ları əlavə et
func addDeprecationHeaders(w http.ResponseWriter, sunsetDate string) {
    w.Header().Set("Deprecation", "true")
    w.Header().Set("Sunset", sunsetDate)
    w.Header().Set("Link", `</v2/users>; rel="successor-version"`)
}
```

### Nümunə 2: Middleware ilə versioning — header əsaslı

```go
package main

import (
    "net/http"
    "strings"
)

const (
    DefaultAPIVersion = "v2"
)

type versionedHandler struct {
    handlers map[string]http.Handler
}

func (vh *versionedHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
    version := extractVersion(r)

    handler, ok := vh.handlers[version]
    if !ok {
        http.Error(w, "Dəstəklənməyən versiya: "+version, http.StatusNotAcceptable)
        return
    }

    // Aktiv versiyani response header-ə əlavə et
    w.Header().Set("API-Version", version)
    handler.ServeHTTP(w, r)
}

func extractVersion(r *http.Request) string {
    // 1. URL path-dan: /v1/... → "v1"
    parts := strings.Split(r.URL.Path, "/")
    if len(parts) > 1 && strings.HasPrefix(parts[1], "v") {
        return parts[1]
    }

    // 2. Accept header-dan: application/vnd.myapi.v2+json
    accept := r.Header.Get("Accept")
    if strings.Contains(accept, "vnd.myapi.") {
        // "application/vnd.myapi.v2+json" → "v2"
        start := strings.Index(accept, "vnd.myapi.") + len("vnd.myapi.")
        end := strings.Index(accept[start:], "+")
        if end > 0 {
            return accept[start : start+end]
        }
    }

    // 3. X-API-Version header
    if v := r.Header.Get("X-API-Version"); v != "" {
        return v
    }

    // 4. Default versiya
    return DefaultAPIVersion
}

func NewVersionedHandler(handlers map[string]http.Handler) http.Handler {
    return &versionedHandler{handlers: handlers}
}
```

### Nümunə 3: Versiya qatı — shared logic

```go
package main

import (
    "encoding/json"
    "net/http"
)

// Domain model — versiyadan asılı deyil
type User struct {
    ID        int64
    FirstName string
    LastName  string
    Email     string
}

// Shared business logic
type UserService interface {
    GetUser(id int64) (*User, error)
}

// V1 Handler — köhnə format
type UserHandlerV1 struct {
    svc UserService
}

func (h *UserHandlerV1) GetUser(w http.ResponseWriter, r *http.Request) {
    user, err := h.svc.GetUser(1)
    if err != nil {
        http.Error(w, err.Error(), http.StatusNotFound)
        return
    }

    // V1 formatına çevir
    resp := map[string]interface{}{
        "id":   user.ID,
        "name": user.FirstName + " " + user.LastName, // köhnə format
    }

    w.Header().Set("Deprecation", "true")
    json.NewEncoder(w).Encode(resp)
}

// V2 Handler — yeni format
type UserHandlerV2 struct {
    svc UserService
}

func (h *UserHandlerV2) GetUser(w http.ResponseWriter, r *http.Request) {
    user, err := h.svc.GetUser(1)
    if err != nil {
        http.Error(w, err.Error(), http.StatusNotFound)
        return
    }

    // V2 formatına çevir
    resp := map[string]interface{}{
        "id":         user.ID,
        "first_name": user.FirstName,
        "last_name":  user.LastName,
        "email":      user.Email,
    }

    json.NewEncoder(w).Encode(resp)
}

// Eyni service — fərqli presentasiya
// Business logic duplicate deyil!
```

### Nümunə 4: Versiya log — kim köhnə versiya istifadə edir?

```go
package main

import (
    "log/slog"
    "net/http"
    "strings"
)

func versionLoggingMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        path := r.URL.Path

        // Köhnə versiya aşkarla
        if strings.HasPrefix(path, "/v1/") {
            slog.Warn("Köhnə API versiyası istifadə edilir",
                slog.String("path", path),
                slog.String("method", r.Method),
                slog.String("user_agent", r.UserAgent()),
                slog.String("ip", r.RemoteAddr),
                // Prod-da: istifadəçi ID, tenant ID
            )
            // Metric: prometheus.Counter incr
        }

        next.ServeHTTP(w, r)
    })
}
```

### Nümunə 5: API versiyasını göstərən endpoint

```go
package main

import (
    "encoding/json"
    "net/http"
    "time"
)

type APIInfo struct {
    Version    string            `json:"version"`
    Versions   []VersionInfo     `json:"versions"`
    Deprecated []string          `json:"deprecated"`
}

type VersionInfo struct {
    Version  string    `json:"version"`
    Status   string    `json:"status"`  // active, deprecated, sunset
    SunsetAt *time.Time `json:"sunset_at,omitempty"`
}

func APIInfoHandler(w http.ResponseWriter, r *http.Request) {
    sunset := time.Date(2025, 12, 31, 0, 0, 0, 0, time.UTC)

    info := APIInfo{
        Version: "v2",
        Versions: []VersionInfo{
            {Version: "v1", Status: "deprecated", SunsetAt: &sunset},
            {Version: "v2", Status: "active"},
        },
        Deprecated: []string{"v1"},
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(info)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
V1 `/api/v1/users` (name sahəsi) və V2 `/api/v2/users` (first_name + last_name + email) endpoint-lərini yaz. V1-ə Deprecation/Sunset header əlavə et.

**Tapşırıq 2:**
`/api/versions` endpoint-i yaz: mövcud versiyaları, statuslarını, sunset tarixlərini JSON-da göstərsin.

**Tapşırıq 3:**
Versiya log middleware yaz: hər köhnə versiya sorğusu log-a yazılsın (path, user-agent, timestamp). Bu məlumatla "kim hələ v1 istifadə edir" sualına cavab ver.

## PHP ilə Müqayisə

Laravel `/api/v1/...` route grupları ilə versioning ən çox istifadə olunan yanaşmadır. Go-da eyni konsept `http.ServeMux` ilə tətbiq olunur.

```php
// Laravel — route qrupları ilə versioning
Route::prefix('v1')->group(function () {
    Route::get('/users/{id}', [UserControllerV1::class, 'show']);
});

Route::prefix('v2')->group(function () {
    Route::get('/users/{id}', [UserControllerV2::class, 'show']);
});
```

```go
// Go — ServeMux ilə
mux.HandleFunc("GET /v1/users/{id}", getUserV1)
mux.HandleFunc("GET /v2/users/{id}", getUserV2)
```

**Əsas fərqlər:**
- Laravel: route groups, middleware, controller — strukturlaşdırılmış; Go: düz handler-lər
- Shared business logic: hər ikisində service layer ilə — eyni yanaşma
- Deprecation header-ları Go-da manual; Laravel-də middleware ilə əlavə etmək daha asan

## Əlaqəli Mövzular

- [01-http-server.md](01-http-server.md) — HTTP server və routing
- [35-middleware-and-routing.md](03-middleware-and-routing.md) — Middleware chain
- [20-json-encoding.md](../core/20-json-encoding.md) — JSON format çevirmə
- [54-project-structure.md](18-project-structure.md) — Layihə strukturu
