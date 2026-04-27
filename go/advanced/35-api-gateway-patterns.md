# API Gateway Patterns (Lead)

## İcmal

API Gateway, microservice arxitekturasında bütün client sorğuları üçün vahid giriş nöqtəsidir. Client-lər fərdi servislərlə birbaşa danışmaq əvəzinə yalnız gateway ilə əlaqə saxlayır. Gateway isə sorğuları müvafiq servisə yönləndirir, auth yoxlayır, rate limit tətbiq edir və lazım gəldikdə bir neçə servis cavabını birləşdirir.

Go-da API Gateway həm özün sıfırdan yaza bilərsən, həm də Go-da yazılmış Traefik, ya da digər həllər — Kong, Nginx — istifadə edə bilərsən.

## Niyə Vacibdir

Microservice-siz dünyada (monolith) hər şey bir tətbiqdədir. Lakin microservice-lər ilə:

- Client 10 fərqli servisin adresini bilməli olur
- Hər servis ayrı-ayrılıqda auth yoxlamaq məcburiyyətindədir
- Mobile client çox sayda HTTP sorğusu göndərir (latency artır)
- Rate limiting hər servisdə ayrıca implement edilməlidir

API Gateway bu problemləri mərkəzləşdirir: **bir giriş, mərkəzləşdirilmiş cross-cutting concerns**.

## Əsas Anlayışlar

### Core Pattern-lər

**1. Request Routing**
Client sorğusunu path-a görə müvafiq servisə yönləndirir.

**2. Authentication & Authorization**
JWT token-i gateway-də yoxla, claims-i downstream servisə header kimi ötür. Hər servis auth logic yazmalı olmur.

**3. Rate Limiting**
Client IP-sinə və ya API key-ə görə request sayını məhdudlaşdır. DDoS qoruması.

**4. Request Aggregation / BFF**
Bir client sorğusuna cavab vermək üçün bir neçə servis çağırıb nəticələri birləşdir.

**5. Circuit Breaker**
Downstream servis dayanıbsa, sorğuları dərhal rədd et — timeout gözləmə.

**6. Request/Response Transformation**
Header əlavə et/sil, JSON formatını çevir, versioning idarə et.

### BFF (Backend for Frontend)

Bir gateway əvəzinə hər client tipi üçün ayrı gateway:

```
Mobile App      → Mobile BFF Gateway     → Microservices
Web App         → Web BFF Gateway        → Microservices
3rd Party API   → Public API Gateway     → Microservices
```

Mobile BFF az data qaytarır (bandwidth qənaəti), Web BFF daha çox.

## Praktik Baxış

### Nə vaxt özün yaz, nə vaxt hazır həll istifadə et

| Ssenari                          | Tövsiyə                          |
|----------------------------------|----------------------------------|
| Learning / prototip              | Özün `net/http` ilə yaz          |
| Produksiya — sadə routing        | Traefik (Go, Kubernetes-friendly)|
| Enterprise — plugin ecosystem    | Kong (Nginx-based)               |
| Yüksək mürəkkəblik — custom logic | Özün yaz + bazı middleware-lər   |

### Trade-off-lar

- **Single point of failure**: Gateway danarsa hər şey dayanır → yüksək availability lazımdır
- **Latency hop**: Hər sorğu gateway-dən keçir → əlavə ~1-5ms
- **Bottleneck**: Bütün traffic gateway-dən keçir → yatay scaling lazımdır

### Anti-pattern-lər

- Business logic-i gateway-ə qoymaq (yalnız cross-cutting concerns olmalıdır)
- Gateway-i monolith-ə çevirmək
- Downstream servislə eyni auth logic-i gateway-də yazmaq (gateway yalnız token yoxlayır, servis authorization qərarı verir)

## Nümunələr

### Ümumi Nümunə

```
Client → GET /api/orders/123
         ↓
    API Gateway
    ├── Auth middleware: JWT yoxla
    ├── Rate limiter: limit keçilməyib?
    ├── Router: /api/orders/* → order-service:8080
    └── ReverseProxy: sorğunu yönləndir
         ↓
    Order Service → 200 OK
         ↓
    API Gateway → cavabı client-ə qaytar
```

### Kod Nümunəsi

**Sadə custom API Gateway:**

```go
package main

import (
    "log"
    "net/http"
    "net/http/httputil"
    "net/url"
    "strings"
    "time"
)

// RouteConfig bir route-u təsvir edir
type RouteConfig struct {
    Prefix  string
    Target  string
}

// Gateway əsas struct
type Gateway struct {
    routes []RouteConfig
    mux    *http.ServeMux
}

func NewGateway(routes []RouteConfig) *Gateway {
    g := &Gateway{routes: routes}
    g.mux = http.NewServeMux()
    g.setupRoutes()
    return g
}

func (g *Gateway) setupRoutes() {
    for _, route := range g.routes {
        target, err := url.Parse(route.Target)
        if err != nil {
            log.Fatalf("Invalid target URL %s: %v", route.Target, err)
        }

        proxy := httputil.NewSingleHostReverseProxy(target)

        // Path prefix-ini strip edib downstream-ə yönləndir
        prefix := route.Prefix
        g.mux.HandleFunc(prefix+"/", func(w http.ResponseWriter, r *http.Request) {
            // /api/orders/123 → /123 (prefix strip)
            r.URL.Path = strings.TrimPrefix(r.URL.Path, prefix)
            if r.URL.Path == "" {
                r.URL.Path = "/"
            }

            // Downstream servisə original host-u ötür
            r.Header.Set("X-Forwarded-Host", r.Host)
            r.Header.Set("X-Origin-Host", target.Host)

            proxy.ServeHTTP(w, r)
        })
    }
}

func (g *Gateway) ServeHTTP(w http.ResponseWriter, r *http.Request) {
    g.mux.ServeHTTP(w, r)
}
```

**JWT Auth Middleware:**

```go
package middleware

import (
    "context"
    "net/http"
    "strings"

    "github.com/golang-jwt/jwt/v5"
)

type contextKey string

const ClaimsKey contextKey = "claims"

type Claims struct {
    UserID string `json:"user_id"`
    Role   string `json:"role"`
    jwt.RegisteredClaims
}

func JWTAuth(secret string) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            authHeader := r.Header.Get("Authorization")
            if authHeader == "" || !strings.HasPrefix(authHeader, "Bearer ") {
                http.Error(w, "Unauthorized", http.StatusUnauthorized)
                return
            }

            tokenStr := strings.TrimPrefix(authHeader, "Bearer ")

            claims := &Claims{}
            token, err := jwt.ParseWithClaims(tokenStr, claims, func(t *jwt.Token) (any, error) {
                return []byte(secret), nil
            })

            if err != nil || !token.Valid {
                http.Error(w, "Invalid token", http.StatusUnauthorized)
                return
            }

            // Claims-i downstream-ə header kimi ötür
            r.Header.Set("X-User-ID", claims.UserID)
            r.Header.Set("X-User-Role", claims.Role)

            // Context-ə də əlavə et (BFF aggregation üçün)
            ctx := context.WithValue(r.Context(), ClaimsKey, claims)
            next.ServeHTTP(w, r.WithContext(ctx))
        })
    }
}
```

**Rate Limiting Middleware (token bucket):**

```go
package middleware

import (
    "net/http"
    "sync"
    "time"
)

type rateLimiter struct {
    mu       sync.Mutex
    clients  map[string]*clientState
    rate     int           // sorğu sayı
    window   time.Duration // zaman pəncərəsi
}

type clientState struct {
    count    int
    resetAt  time.Time
}

func NewRateLimiter(rate int, window time.Duration) *rateLimiter {
    rl := &rateLimiter{
        clients: make(map[string]*clientState),
        rate:    rate,
        window:  window,
    }
    // Köhnə client-ləri təmizlə
    go rl.cleanup()
    return rl
}

func (rl *rateLimiter) Allow(clientIP string) bool {
    rl.mu.Lock()
    defer rl.mu.Unlock()

    now := time.Now()
    state, exists := rl.clients[clientIP]

    if !exists || now.After(state.resetAt) {
        rl.clients[clientIP] = &clientState{
            count:   1,
            resetAt: now.Add(rl.window),
        }
        return true
    }

    if state.count >= rl.rate {
        return false
    }

    state.count++
    return true
}

func (rl *rateLimiter) Middleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        clientIP := r.RemoteAddr // Production-da X-Forwarded-For istifadə et

        if !rl.Allow(clientIP) {
            w.Header().Set("Retry-After", "60")
            http.Error(w, "Too Many Requests", http.StatusTooManyRequests)
            return
        }

        next.ServeHTTP(w, r)
    })
}

func (rl *rateLimiter) cleanup() {
    ticker := time.NewTicker(5 * time.Minute)
    for range ticker.C {
        rl.mu.Lock()
        now := time.Now()
        for ip, state := range rl.clients {
            if now.After(state.resetAt) {
                delete(rl.clients, ip)
            }
        }
        rl.mu.Unlock()
    }
}
```

**BFF — Request Aggregation:**

```go
package bff

import (
    "encoding/json"
    "net/http"
    "sync"
)

// UserDashboardResponse — mobile BFF üçün agregasiya cavabı
type UserDashboardResponse struct {
    User   *UserDTO    `json:"user"`
    Orders []*OrderDTO `json:"orders"`
    Stats  *StatsDTO   `json:"stats"`
}

type BFFHandler struct {
    userClient   *http.Client
    orderClient  *http.Client
    statsClient  *http.Client
    userService  string
    orderService string
    statsService string
}

// GetDashboard — 3 servisi paralel çağırıb birləşdirir
func (h *BFFHandler) GetDashboard(w http.ResponseWriter, r *http.Request) {
    userID := r.Header.Get("X-User-ID") // gateway-dən gəlir

    var (
        wg       sync.WaitGroup
        mu       sync.Mutex
        result   UserDashboardResponse
        firstErr error
    )

    wg.Add(3)

    // User data
    go func() {
        defer wg.Done()
        user, err := h.fetchUser(r.Context(), userID)
        mu.Lock()
        defer mu.Unlock()
        if err != nil && firstErr == nil {
            firstErr = err
            return
        }
        result.User = user
    }()

    // Orders
    go func() {
        defer wg.Done()
        orders, err := h.fetchOrders(r.Context(), userID)
        mu.Lock()
        defer mu.Unlock()
        if err != nil && firstErr == nil {
            firstErr = err
            return
        }
        result.Orders = orders
    }()

    // Stats
    go func() {
        defer wg.Done()
        stats, err := h.fetchStats(r.Context(), userID)
        mu.Lock()
        defer mu.Unlock()
        if err != nil && firstErr == nil {
            firstErr = err
            return
        }
        result.Stats = stats
    }()

    wg.Wait()

    if firstErr != nil {
        http.Error(w, "Service unavailable", http.StatusServiceUnavailable)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(result)
}
```

**Middleware chain ilə Gateway-i bir yerə yığmaq:**

```go
func main() {
    routes := []RouteConfig{
        {Prefix: "/api/orders",   Target: "http://order-service:8080"},
        {Prefix: "/api/payments", Target: "http://payment-service:8080"},
        {Prefix: "/api/users",    Target: "http://user-service:8080"},
    }

    gateway := NewGateway(routes)

    // Rate limiter: dəqiqədə 100 sorğu
    rl := NewRateLimiter(100, time.Minute)

    // Middleware chain: rate limit → auth → gateway
    handler := rl.Middleware(
        middleware.JWTAuth("your-secret")(gateway),
    )

    // /api/dashboard — BFF aggregation endpoint
    bffHandler := &bff.BFFHandler{...}
    mux := http.NewServeMux()
    mux.Handle("/api/dashboard", middleware.JWTAuth("your-secret")(
        http.HandlerFunc(bffHandler.GetDashboard),
    ))
    mux.Handle("/", handler)

    log.Println("Gateway listening on :8000")
    http.ListenAndServe(":8000", mux)
}
```

**Traefik ilə (docker-compose.yml):**

```yaml
# Traefik — Go-da yazılmış, sıfır konfiqurasiyon ilə routing
services:
  traefik:
    image: traefik:v3.0
    command:
      - "--api.insecure=true"
      - "--providers.docker=true"
    ports:
      - "80:80"
      - "8080:8080"  # Dashboard

  order-service:
    image: myapp/order-service
    labels:
      - "traefik.http.routers.orders.rule=PathPrefix(`/api/orders`)"
      - "traefik.http.services.orders.loadbalancer.server.port=8080"
      # JWT middleware
      - "traefik.http.middlewares.auth.forwardauth.address=http://auth-service/verify"
      - "traefik.http.routers.orders.middlewares=auth"

  payment-service:
    image: myapp/payment-service
    labels:
      - "traefik.http.routers.payments.rule=PathPrefix(`/api/payments`)"
      - "traefik.http.services.payments.loadbalancer.server.port=8080"
```

## Praktik Tapşırıqlar

1. **Basic Reverse Proxy**: `httputil.ReverseProxy` istifadə edərək iki servisi (order, payment) route edən sadə gateway yaz. Hər servis ayrı portda çalışır.

2. **JWT Middleware**: Gateway-ə JWT middleware əlavə et. Yalnız valid token ilə sorğular downstream-ə çatmalıdır. Invalid token halında 401 qaytar.

3. **Rate Limiter Test**: Rate limiter implement et. `wrk` və ya `ab` (Apache Benchmark) ilə yük testi keç. Limit keçdikdə 429 aldığını doğrula.

4. **BFF Aggregation**: User dashboard endpoint-i yaz. `/api/profile`, `/api/orders`, `/api/notifications` — üç servisə paralel sorğu at, cavabları birləşdir. Bir servis uğursuz olsa partial response qaytar.

5. **Traefik Setup**: Docker Compose ilə Traefik qur. Order və payment service-lərini label-larla route et. Auth middleware əlavə et.

## Əlaqəli Mövzular

- `26-microservices.md` — Microservice arxitekturasının əsasları
- `18-circuit-breaker-and-retry.md` — Downstream servis uğursuzluğunu idarə etmək
- `10-jwt-and-auth.md` — JWT authentication detalları
- `07-security.md` — Gateway-də security best practice-lər
- `24-monitoring-and-observability.md` — Gateway metrics və tracing
