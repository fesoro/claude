# Context (Middle)

## İcmal

`context` paketi Go-da goroutine-lər arası ləğvetmə (cancellation), deadline/timeout idarəsi və request-scoped dəyərlərin ötürülməsi üçün istifadə olunur. HTTP handler-dən database sorğusuna qədər bütün zənciri idarə etmək üçün kritikdir.

## Niyə Vacibdir

Real layihədə HTTP sorğusu gələndə bir çox asinxron əməliyyat başlana bilər: database sorğusu, external API call, cache yoxlaması. Əgər client sorğunu ləğv etsə (browser-i bağlasa), bütün bu əməliyyatlar da dayandırılmalıdır. Context olmadan bu mümkün deyil — server resursları boşa xərclənər. Go HTTP serveri hər sorğu üçün avtomatik context yaradır və client bağlandıqda onu ləğv edir.

## Əsas Anlayışlar

- **context.Background()** — kök context; yalnız main, init, test-lərdə istifadə olunur
- **context.TODO()** — hənuz hansı context-in istifadə ediləcəyi bilinmədikdə placeholder
- **context.WithCancel()** — manual ləğvetmə; `cancel()` funksiyası qaytarır
- **context.WithTimeout()** — müəyyən müddətdən sonra avtomatik ləğv
- **context.WithDeadline()** — müəyyən ana qədər (absolute time) ləğv
- **context.WithValue()** — context vasitəsilə dəyər ötürmək (az istifadə edin)
- **ctx.Done()** — ləğv edildikdə bağlanan kanal
- **ctx.Err()** — `context.Canceled` və ya `context.DeadlineExceeded`

## Praktik Baxış

**Context zənciri:**

```
context.Background()
    └─ WithCancel()           ← əl ilə ləğv
        └─ WithTimeout(30s)   ← 30s sonra ləğv
            └─ WithValue(user_id, 42)  ← dəyər
                └─ handler → service → repository (hər birinin parametri)
```

**Qeydlər:**
- Context struct-da saxlamayın — həmişə parametr kimi ötürün
- Birinci parametr: `func DoSomething(ctx context.Context, ...)` — bu Go konvensiyasıdır
- `cancel()` həmişə `defer` ilə çağırın — resurs sızmasının qarşısını alır
- `context.WithValue` yalnız request-scoped metadata üçün: request_id, user_id, trace_id

**Trade-off-lar:**
- Çox dərin context zənciri debugging-i çətinləşdirir
- `ctx.Value()` type-safe deyil — xüsusi key tipi istifadə edin (string yox)
- Çox uzun timeout — resursları blokda saxlayır; çox qısa — düzgün sorğular fail olur

**Common mistakes:**
- `cancel()` çağırmamaq — goroutine leak
- `context.Background()` birbaşa handler-ə ötürmək əvəzinə `r.Context()` istifadə etməmək
- String key ilə `context.WithValue` — collision riski

## Nümunələr

### Nümunə 1: context.WithTimeout — API çağırışı

```go
package main

import (
    "context"
    "fmt"
    "time"
)

// External API çağırışını simulasiya edir
func externalAPICall(ctx context.Context) (string, error) {
    select {
    case <-time.After(2 * time.Second): // API 2 saniyə gec cavab verir
        return "API cavabı", nil
    case <-ctx.Done():
        return "", ctx.Err() // context.DeadlineExceeded
    }
}

func main() {
    ctx := context.Background()

    // 1 saniyə timeout — API 2 saniyə lazım edir → timeout
    ctx1, cancel1 := context.WithTimeout(ctx, 1*time.Second)
    defer cancel1()

    _, err := externalAPICall(ctx1)
    if err != nil {
        fmt.Println("Timeout:", err) // context deadline exceeded
    }

    // 3 saniyə timeout — API 2 saniyə → uğurlu
    ctx2, cancel2 := context.WithTimeout(ctx, 3*time.Second)
    defer cancel2()

    result, err := externalAPICall(ctx2)
    if err != nil {
        fmt.Println("Xəta:", err)
    } else {
        fmt.Println("Nəticə:", result)
    }
}
```

### Nümunə 2: context.WithCancel — əl ilə ləğvetmə

```go
package main

import (
    "context"
    "fmt"
    "time"
)

func arxa plan işi(ctx context.Context, ad string) {
    for {
        select {
        case <-ctx.Done():
            fmt.Printf("%s dayandırıldı: %v\n", ad, ctx.Err())
            return
        case <-time.After(300 * time.Millisecond):
            fmt.Printf("%s işləyir...\n", ad)
        }
    }
}

func main() {
    ctx, cancel := context.WithCancel(context.Background())

    go arxa plan işi(ctx, "İşçi-1")
    go arxa plan işi(ctx, "İşçi-2")

    time.Sleep(1 * time.Second)
    cancel() // hər iki goroutine-i ləğv et

    time.Sleep(200 * time.Millisecond) // dayandıqlarından əmin ol
    fmt.Println("Bütün işçilər dayandırıldı")
}
```

### Nümunə 3: context.WithDeadline — mütləq vaxt

```go
package main

import (
    "context"
    "fmt"
    "time"
)

func main() {
    // Sabah saat 00:00-a qədər
    deadline := time.Now().Add(500 * time.Millisecond)
    ctx, cancel := context.WithDeadline(context.Background(), deadline)
    defer cancel()

    fmt.Println("Deadline:", ctx.Deadline())

    select {
    case <-time.After(1 * time.Second):
        fmt.Println("Bitdi")
    case <-ctx.Done():
        fmt.Println("Deadline keçdi:", ctx.Err())
    }

    // WithTimeout = WithDeadline(ctx, time.Now().Add(d))
    // İkisi eyni şeydir, WithTimeout daha rahatdır
}
```

### Nümunə 4: context.WithValue — request-scoped dəyərlər

```go
package main

import (
    "context"
    "fmt"
)

// QAYDA: String key istifadə etməyin — xüsusi tip yaradın
type contextKey string

const (
    requestIDKey contextKey = "request_id"
    userIDKey    contextKey = "user_id"
    roleKey      contextKey = "role"
)

func handler(ctx context.Context) {
    requestID := ctx.Value(requestIDKey).(string)
    userID    := ctx.Value(userIDKey).(int)
    role      := ctx.Value(roleKey).(string)

    fmt.Printf("Request ID: %s, User: %d (%s)\n", requestID, userID, role)
    
    // Alt funksiyaya eyni context-i ötür
    serviceCall(ctx)
}

func serviceCall(ctx context.Context) {
    // Context zəncirinə bütün dəyərləri əlçatan olur
    if rid, ok := ctx.Value(requestIDKey).(string); ok {
        fmt.Println("Service layer, request:", rid)
    }
}

func main() {
    ctx := context.Background()
    ctx = context.WithValue(ctx, requestIDKey, "req-abc-123")
    ctx = context.WithValue(ctx, userIDKey, 42)
    ctx = context.WithValue(ctx, roleKey, "admin")

    handler(ctx)
}
```

### Nümunə 5: Real HTTP handler ssenarisi

```go
package main

import (
    "context"
    "database/sql"
    "encoding/json"
    "fmt"
    "log/slog"
    "net/http"
    "time"
)

type contextKey string
const userIDKey contextKey = "user_id"

// Middleware — request_id əlavə et, timeout qur
func timeoutMiddleware(timeout time.Duration, next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        ctx, cancel := context.WithTimeout(r.Context(), timeout)
        defer cancel()
        next.ServeHTTP(w, r.WithContext(ctx))
    })
}

// Auth middleware — user_id-ni context-ə əlavə et
func authMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        userID := 42 // JWT-dən alınmış dəyər simulasiyası
        ctx := context.WithValue(r.Context(), userIDKey, userID)
        next.ServeHTTP(w, r.WithContext(ctx))
    })
}

// Repository — context-i database sorğusuna ötür
func getUserFromDB(ctx context.Context, db *sql.DB, userID int) (map[string]any, error) {
    // db.QueryContext context ləğv edildikdə sorğunu dayandırır
    rows, err := db.QueryContext(ctx, "SELECT id, name FROM users WHERE id = $1", userID)
    if err != nil {
        return nil, fmt.Errorf("database sorğusu: %w", err)
    }
    defer rows.Close()
    return map[string]any{"id": userID, "name": "Orkhan"}, nil
}

// Handler
func userHandler(db *sql.DB) http.HandlerFunc {
    return func(w http.ResponseWriter, r *http.Request) {
        ctx := r.Context()
        
        // Context-dən user_id al
        userID, ok := ctx.Value(userIDKey).(int)
        if !ok {
            http.Error(w, "Unauthorized", http.StatusUnauthorized)
            return
        }

        user, err := getUserFromDB(ctx, db, userID)
        if err != nil {
            if ctx.Err() != nil {
                // Client bağlantını kəsdi — log yaz, amma error response göndərmə
                slog.Warn("Request ləğv edildi", "user_id", userID, "err", ctx.Err())
                return
            }
            http.Error(w, "Internal Server Error", http.StatusInternalServerError)
            return
        }

        w.Header().Set("Content-Type", "application/json")
        json.NewEncoder(w).Encode(user)
    }
}

func main() {
    // var db *sql.DB // real DB bağlantısı
    mux := http.NewServeMux()
    // mux.Handle("/user", authMiddleware(userHandler(db)))
    
    handler := timeoutMiddleware(5*time.Second, mux)
    http.ListenAndServe(":8080", handler)
}
```

### Nümunə 6: Context zənciri — service/repo arxitekturası

```go
package main

import (
    "context"
    "fmt"
    "time"
)

// Repository layer
func (r *OrderRepository) FindByID(ctx context.Context, id int) (*Order, error) {
    select {
    case <-ctx.Done():
        return nil, fmt.Errorf("repository: %w", ctx.Err())
    case <-time.After(50 * time.Millisecond):
        return &Order{ID: id, Total: 99.99}, nil
    }
}

// Service layer — context-i ötür, öz timeout əlavə edə bilər
func (s *OrderService) GetOrder(ctx context.Context, id int) (*Order, error) {
    // Ümumi timeout varsa, alt sorğular üçün ayrıca timeout əlavə etmək olar
    dbCtx, cancel := context.WithTimeout(ctx, 200*time.Millisecond)
    defer cancel()
    
    order, err := s.repo.FindByID(dbCtx, id)
    if err != nil {
        return nil, fmt.Errorf("service: %w", err)
    }
    return order, nil
}

// HTTP Handler
func (h *Handler) GetOrder(w http.ResponseWriter, r *http.Request) {
    // r.Context() artıq timeout middleware-dən keçib
    order, err := h.service.GetOrder(r.Context(), 123)
    if err != nil {
        http.Error(w, err.Error(), 500)
        return
    }
    fmt.Fprintf(w, "Order: %+v\n", order)
}

type Order struct{ ID int; Total float64 }
type OrderRepository struct{}
type OrderService struct{ repo *OrderRepository }
type Handler struct{ service *OrderService }
```

## Praktik Tapşırıqlar

**Tapşırıq 1: Paralel sorğularla timeout**

3 fərqli service-ə eyni anda sorğu göndər. Hamısı üçün ümumi 2 saniyəlik timeout. Biri fail etsə digərlərini ləğv et.

```go
// Fikir:
// ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
// defer cancel()
// errgroup.WithContext(ctx) — golang.org/x/sync/errgroup
// g.Go(func() error { return callService1(ctx) })
// g.Go(func() error { return callService2(ctx) })
// err := g.Wait()
```

**Tapşırıq 2: Graceful shutdown**

HTTP server başladın. SIGINT gəldikdə: yeni sorğuları qəbul etməyi dayandırın, mövcud sorğuları 30 saniyə ərzində tamamlamağa icazə verin, sonra çıxın.

```go
// Fikir:
// server.Shutdown(ctx) — context.WithTimeout(ctx, 30*time.Second)
// signal.NotifyContext(ctx, os.Interrupt)
```

**Tapşırıq 3: Request tracing**

Hər HTTP sorğusuna UUID `trace_id` təyin edin. Bu `trace_id`-ni bütün log-larda, database sorğularında, external API call-larında göstərin. `slog.With("trace_id", ...)` istifadə edin.

```go
// Middleware:
// traceID := uuid.New().String()
// ctx := context.WithValue(r.Context(), traceIDKey, traceID)
// logger := slog.With("trace_id", traceID)
// ctx = context.WithValue(ctx, loggerKey, logger)
```

## Ətraflı Qeydlər

**Context propagation best practices:**

```go
// 1. Həmişə birinci parametr
func DoWork(ctx context.Context, input string) error

// 2. Struct-da saxlamayın
type Bad struct {
    ctx context.Context // YANLIŞ
}

// 3. Nil context göndərməyin
// DoWork(nil, "data") // panic
// DoWork(context.TODO(), "data") // düzgün

// 4. cancel()-i həmişə defer ilə çağırın
ctx, cancel := context.WithTimeout(parent, 5*time.Second)
defer cancel() // resurs sızmasının qarşısını alır
```

**errgroup — xəta idarəsi ilə paralel goroutine-lər:**

```go
import "golang.org/x/sync/errgroup"

g, ctx := errgroup.WithContext(context.Background())
g.Go(func() error { return fetchUser(ctx) })
g.Go(func() error { return fetchOrders(ctx) })
if err := g.Wait(); err != nil {
    // Biri fail etdikdə ctx avtomatik ləğv olunur
}
```

## PHP ilə Müqayisə

```
PHP                          →  Go
set_time_limit(30)           →  context.WithTimeout(ctx, 30*time.Second)
ignore_user_abort(false)     →  <-ctx.Done()  (client disconnected)
CancellationToken (yoxdur)   →  context.WithCancel()
Request global               →  ctx.Value(key) — ancaq request data üçün
```

PHP-də request lifetime avtomatik idarə olunur (Apache/FPM prosesini öldürür). Go-da hər sorğu üçün context əl ilə qurulur — bu daha çox nəzarət, amma daha çox məsuliyyət deməkdir.

## Əlaqəli Mövzular

- `27-goroutines-and-channels` — goroutine ləğvi üçün context.Done()
- `../backend/01-http-server` — HTTP handler-lərdə r.Context() istifadəsi
- `../backend/05-database` — db.QueryContext() ilə database timeout
- `../backend/17-graceful-shutdown` — server shutdown-da context
- `25-logging` — context vasitəsilə request-scoped logger ötürmək
