# Multi-Tenancy Patterns (Lead)

## İcmal

Multi-tenancy — bir tətbiqin bir neçə müştərini (tenant) eyni anda, izolasiya edilmiş şəkildə xidmət etməsi modelidir. Hər tenant öz datasını görür, başqasının datasına çatmır. SaaS məhsullar üçün fundamental arxitektura qərarıdır.

## Niyə Vacibdir

Laravel-də `multi-tenancy` üçün `stancl/tenancy` paketi var — lakin arxitektura seçimi həmişə əvvəlcə gəlir. Go-da hazır paket yoxdur — pattern-i özün qurmaq lazımdır.

Yanlış seçim:
- **Database per tenant** seçib 10,000 tenant çatanda connection pool bitmə problemi
- **Row-level isolation** seçib bir JOIN-da `WHERE tenant_id = ?` unutmaq — data leak
- Sonradan migration demək olar ki, mümkün deyil

## Əsas Anlayışlar

### 3 əsas yanaşma

| Yanaşma | İzolasiya | Mürəkkəblik | Miqyas |
|---------|-----------|-------------|-------|
| Database per tenant | Maksimum | Yüksək | Az tenant (~1000) |
| Schema per tenant | Orta | Orta | Orta tenant (~10k) |
| Row-level isolation | Minimum | Aşağı | Çox tenant (~1M+) |

### Tenant identifikasiyası

Tenant kim olduğunu sisteme necə bildirir:
- **Subdomain**: `acme.myapp.com` → tenant = `acme`
- **JWT claim**: token içində `tenant_id: "acme"`
- **Header**: `X-Tenant-ID: acme`
- **Path prefix**: `/tenants/acme/api/...`

## Praktik Baxış

### 1. Database per tenant

```go
// tenantdb/manager.go
package tenantdb

import (
    "database/sql"
    "fmt"
    "sync"

    _ "github.com/lib/pq"
)

type Manager struct {
    mu   sync.RWMutex
    pool map[string]*sql.DB // tenant_id → *sql.DB
    dsn  func(tenantID string) string
}

func NewManager(dsn func(tenantID string) string) *Manager {
    return &Manager{
        pool: make(map[string]*sql.DB),
        dsn:  dsn,
    }
}

// DB — tenant üçün connection qaytarır, yoxdursa yaradır
func (m *Manager) DB(tenantID string) (*sql.DB, error) {
    m.mu.RLock()
    db, ok := m.pool[tenantID]
    m.mu.RUnlock()

    if ok {
        return db, nil
    }

    m.mu.Lock()
    defer m.mu.Unlock()

    // Double-check locking
    if db, ok = m.pool[tenantID]; ok {
        return db, nil
    }

    dsn := m.dsn(tenantID)
    db, err := sql.Open("postgres", dsn)
    if err != nil {
        return nil, fmt.Errorf("open db for tenant %s: %w", tenantID, err)
    }

    // Connection pool limiti — per-tenant
    db.SetMaxOpenConns(5)
    db.SetMaxIdleConns(2)

    if err := db.Ping(); err != nil {
        return nil, fmt.Errorf("ping db for tenant %s: %w", tenantID, err)
    }

    m.pool[tenantID] = db
    return db, nil
}

// DSN factory
func TenantDSN(tenantID string) string {
    return fmt.Sprintf(
        "host=localhost port=5432 dbname=tenant_%s user=app password=secret sslmode=require",
        tenantID,
    )
}
```

**Problem**: 1000 tenant = 1000 connection pool = memory və connection sayı batar. Yalnız az sayda tenant (~100-500) üçün uyğundur.

### 2. Schema per tenant

```go
// middleware/tenant_schema.go
package middleware

import (
    "context"
    "database/sql"
    "fmt"
    "net/http"
)

type contextKey string

const tenantKey contextKey = "tenant_id"

// SetSearchPath — PostgreSQL search_path-i tenant schema-sına qurur
func SetSearchPath(db *sql.DB, tenantID string) (*sql.Conn, error) {
    conn, err := db.Conn(context.Background())
    if err != nil {
        return nil, err
    }

    schema := fmt.Sprintf("tenant_%s", tenantID)
    _, err = conn.ExecContext(context.Background(),
        fmt.Sprintf("SET search_path = %s, public", schema),
    )
    if err != nil {
        conn.Close()
        return nil, fmt.Errorf("set search_path for %s: %w", tenantID, err)
    }

    return conn, nil
}

// TenantMiddleware — hər request üçün tenant-specific connection hazırlayır
func TenantMiddleware(db *sql.DB) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            tenantID := r.Header.Get("X-Tenant-ID")
            if tenantID == "" {
                http.Error(w, "X-Tenant-ID header required", http.StatusBadRequest)
                return
            }

            // Tenant-specific connection al
            conn, err := SetSearchPath(db, tenantID)
            if err != nil {
                http.Error(w, "tenant not found", http.StatusNotFound)
                return
            }
            defer conn.Close()

            // Context-ə hem tenant_id hem conn qoy
            ctx := context.WithValue(r.Context(), tenantKey, tenantID)
            ctx = context.WithValue(ctx, connKey, conn)

            next.ServeHTTP(w, r.WithContext(ctx))
        })
    }
}
```

**Postgres-da schema migration:**

```sql
-- Yeni tenant onboarding
CREATE SCHEMA tenant_acme;
SET search_path = tenant_acme;

-- Şablondan copy (schema-level migration tool lazımdır)
CREATE TABLE users (LIKE public.users INCLUDING ALL);
CREATE TABLE orders (LIKE public.orders INCLUDING ALL);
```

**Problem**: `golang-migrate` schema per tenant üçün birbaşa dəstəkləmir — custom solution lazımdır.

### 3. Row-level isolation (ən çox istifadə olunur)

```go
// middleware/tenant_context.go
package middleware

import (
    "context"
    "fmt"
    "net/http"
    "strings"

    "github.com/golang-jwt/jwt/v5"
)

type Tenant struct {
    ID   string
    Name string
    Plan string
}

type contextKey string

const TenantCtxKey contextKey = "tenant"

// TenantFromSubdomain — acme.myapp.com → tenantID = "acme"
func TenantFromSubdomain(host string) (string, error) {
    parts := strings.Split(host, ".")
    if len(parts) < 3 {
        return "", fmt.Errorf("invalid host: %s", host)
    }
    return parts[0], nil
}

// TenantFromJWT — JWT token-dən tenant_id oxuyur
func TenantFromJWT(tokenStr string, secret []byte) (*Tenant, error) {
    token, err := jwt.Parse(tokenStr, func(t *jwt.Token) (interface{}, error) {
        if _, ok := t.Method.(*jwt.SigningMethodHMAC); !ok {
            return nil, fmt.Errorf("unexpected signing method")
        }
        return secret, nil
    })
    if err != nil {
        return nil, err
    }

    claims, ok := token.Claims.(jwt.MapClaims)
    if !ok || !token.Valid {
        return nil, fmt.Errorf("invalid token")
    }

    tenantID, ok := claims["tenant_id"].(string)
    if !ok || tenantID == "" {
        return nil, fmt.Errorf("tenant_id missing in token")
    }

    return &Tenant{
        ID:   tenantID,
        Name: claims["tenant_name"].(string),
        Plan: claims["plan"].(string),
    }, nil
}

// TenantMiddleware — request-dən tenant çıxarıb context-ə qoyur
func TenantMiddleware(jwtSecret []byte) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            var tenant *Tenant
            var err error

            // 1. JWT-dən cəhd et
            authHeader := r.Header.Get("Authorization")
            if strings.HasPrefix(authHeader, "Bearer ") {
                tokenStr := strings.TrimPrefix(authHeader, "Bearer ")
                tenant, err = TenantFromJWT(tokenStr, jwtSecret)
            }

            // 2. Header-dən cəhd et (service-to-service)
            if tenant == nil {
                tenantID := r.Header.Get("X-Tenant-ID")
                if tenantID != "" {
                    tenant = &Tenant{ID: tenantID}
                }
            }

            // 3. Subdomain-dən cəhd et
            if tenant == nil {
                tenantID, subErr := TenantFromSubdomain(r.Host)
                if subErr == nil {
                    tenant = &Tenant{ID: tenantID}
                }
            }

            if tenant == nil {
                http.Error(w, "tenant identification failed", http.StatusUnauthorized)
                return
            }

            ctx := context.WithValue(r.Context(), TenantCtxKey, tenant)
            next.ServeHTTP(w, r.WithContext(ctx))
        })
    }
}

// TenantFromCtx — context-dən tenant alır
func TenantFromCtx(ctx context.Context) (*Tenant, error) {
    tenant, ok := ctx.Value(TenantCtxKey).(*Tenant)
    if !ok || tenant == nil {
        return nil, fmt.Errorf("tenant not found in context")
    }
    return tenant, nil
}
```

**Repository — hər sorğuda tenant_id mütləq:**

```go
// repository/order_repo.go
package repository

import (
    "context"
    "database/sql"
    "fmt"

    "myapp/middleware"
)

type Order struct {
    ID       int64
    TenantID string
    Total    float64
    Status   string
}

type OrderRepository struct {
    db *sql.DB
}

func NewOrderRepository(db *sql.DB) *OrderRepository {
    return &OrderRepository{db: db}
}

// FindByID — tenant_id olmadan query YAZILMAZ
func (r *OrderRepository) FindByID(ctx context.Context, orderID int64) (*Order, error) {
    tenant, err := middleware.TenantFromCtx(ctx)
    if err != nil {
        return nil, fmt.Errorf("unauthorized: %w", err)
    }

    var order Order
    err = r.db.QueryRowContext(ctx,
        `SELECT id, tenant_id, total, status
         FROM orders
         WHERE id = $1 AND tenant_id = $2`,  // tenant_id HƏMIŞƏ filter olunur
        orderID, tenant.ID,
    ).Scan(&order.ID, &order.TenantID, &order.Total, &order.Status)

    if err == sql.ErrNoRows {
        return nil, fmt.Errorf("order %d not found", orderID)
    }
    return &order, err
}

// ListOrders — pagination ilə, yenə tenant_id filter
func (r *OrderRepository) ListOrders(ctx context.Context, limit, offset int) ([]*Order, error) {
    tenant, err := middleware.TenantFromCtx(ctx)
    if err != nil {
        return nil, fmt.Errorf("unauthorized: %w", err)
    }

    rows, err := r.db.QueryContext(ctx,
        `SELECT id, tenant_id, total, status
         FROM orders
         WHERE tenant_id = $1
         ORDER BY id DESC
         LIMIT $2 OFFSET $3`,
        tenant.ID, limit, offset,
    )
    if err != nil {
        return nil, err
    }
    defer rows.Close()

    var orders []*Order
    for rows.Next() {
        var o Order
        if err := rows.Scan(&o.ID, &o.TenantID, &o.Total, &o.Status); err != nil {
            return nil, err
        }
        orders = append(orders, &o)
    }
    return orders, rows.Err()
}
```

### PostgreSQL Row-Level Security (RLS) — tətbiq səviyyəsindən müdafiə

```sql
-- RLS aktivləşdir
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;

-- Policy: yalnız öz tenant_id-ni gör
CREATE POLICY tenant_isolation ON orders
    USING (tenant_id = current_setting('app.current_tenant_id'));

-- Go tərəfindən:
-- SET app.current_tenant_id = 'acme';
-- Sonra normal SELECT — RLS avtomatik filter edir
```

```go
// RLS ilə connection wrapper
func (r *OrderRepository) setTenantContext(ctx context.Context, conn *sql.Conn, tenantID string) error {
    _, err := conn.ExecContext(ctx,
        "SELECT set_config('app.current_tenant_id', $1, true)",
        tenantID,
    )
    return err
}
```

### Tenant onboarding

```go
// onboarding/service.go
package onboarding

import (
    "context"
    "database/sql"
    "fmt"
)

type OnboardingService struct {
    db *sql.DB
}

// CreateTenant — yeni tenant yaradır (row-level model)
func (s *OnboardingService) CreateTenant(ctx context.Context, tenantID, name, plan string) error {
    tx, err := s.db.BeginTx(ctx, nil)
    if err != nil {
        return err
    }
    defer tx.Rollback()

    // Tenant qeydiyyatı
    _, err = tx.ExecContext(ctx,
        `INSERT INTO tenants (id, name, plan, created_at)
         VALUES ($1, $2, $3, NOW())`,
        tenantID, name, plan,
    )
    if err != nil {
        return fmt.Errorf("insert tenant: %w", err)
    }

    // Admin user yarat
    _, err = tx.ExecContext(ctx,
        `INSERT INTO users (tenant_id, email, role, created_at)
         VALUES ($1, $2, 'admin', NOW())`,
        tenantID, fmt.Sprintf("admin@%s.myapp.com", tenantID),
    )
    if err != nil {
        return fmt.Errorf("insert admin user: %w", err)
    }

    // Default settings
    _, err = tx.ExecContext(ctx,
        `INSERT INTO tenant_settings (tenant_id, settings)
         VALUES ($1, '{"timezone": "UTC", "language": "en"}')`,
        tenantID,
    )
    if err != nil {
        return fmt.Errorf("insert settings: %w", err)
    }

    return tx.Commit()
}
```

## Nümunələr

### Ümumi Nümunə

SaaS invoice tətbiqi: Acme Corp və Beta Ltd eyni tətbiqdən istifadə edir. Acme yalnız öz invoice-larını görür, Beta yalnız özününkünü. Ayrı server, ayrı DB gərəkmir — row-level isolation kifayətdir.

### Kod Nümunəsi

**Complete middleware chain:**

```go
// main.go
func main() {
    db := connectDB()
    jwtSecret := []byte(os.Getenv("JWT_SECRET"))

    orderRepo := repository.NewOrderRepository(db)

    mux := http.NewServeMux()
    mux.HandleFunc("GET /api/v1/orders/{id}", func(w http.ResponseWriter, r *http.Request) {
        id := r.PathValue("id")
        orderID, _ := strconv.ParseInt(id, 10, 64)

        order, err := orderRepo.FindByID(r.Context(), orderID)
        if err != nil {
            http.Error(w, err.Error(), http.StatusNotFound)
            return
        }
        json.NewEncoder(w).Encode(order)
    })

    // Middleware stack
    handler := middleware.TenantMiddleware(jwtSecret)(mux)
    handler = middleware.LoggingMiddleware(handler)
    handler = middleware.RecoveryMiddleware(handler)

    http.ListenAndServe(":8080", handler)
}
```

**Database schema:**

```sql
CREATE TABLE tenants (
    id         TEXT PRIMARY KEY,
    name       TEXT NOT NULL,
    plan       TEXT NOT NULL DEFAULT 'free',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE orders (
    id         BIGSERIAL PRIMARY KEY,
    tenant_id  TEXT NOT NULL REFERENCES tenants(id),
    total      DECIMAL(10, 2) NOT NULL,
    status     TEXT NOT NULL DEFAULT 'pending',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- tenant_id-yə index mütləqdir
CREATE INDEX idx_orders_tenant_id ON orders(tenant_id);
CREATE INDEX idx_orders_tenant_created ON orders(tenant_id, created_at DESC);
```

## Praktik Tapşırıqlar

**1. Tenant extraction test et:**

```go
func TestTenantFromSubdomain(t *testing.T) {
    tests := []struct {
        host     string
        expected string
        wantErr  bool
    }{
        {"acme.myapp.com", "acme", false},
        {"beta-corp.myapp.com", "beta-corp", false},
        {"localhost", "", true},
    }

    for _, tt := range tests {
        got, err := TenantFromSubdomain(tt.host)
        if (err != nil) != tt.wantErr {
            t.Errorf("host=%s: unexpected error=%v", tt.host, err)
        }
        if got != tt.expected {
            t.Errorf("host=%s: got=%s want=%s", tt.host, got, tt.expected)
        }
    }
}
```

**2. Cross-tenant leak test:**

```go
// Tenant A-nın datasına Tenant B ilə çatmağa cəhd
func TestCrossTenantIsolation(t *testing.T) {
    // Tenant A-nın order-i yarat
    ctxA := contextWithTenant("tenant-a")
    orderA, _ := repo.Create(ctxA, &Order{Total: 100})

    // Tenant B ilə həmin order-i almağa cəhd
    ctxB := contextWithTenant("tenant-b")
    _, err := repo.FindByID(ctxB, orderA.ID)

    // Xəta gəlməlidir — not found
    if err == nil {
        t.Fatal("cross-tenant data leak detected!")
    }
}
```

**3. RLS əlavə et:**

```sql
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE orders FORCE ROW LEVEL SECURITY;

CREATE POLICY tenant_isolation_policy ON orders
    AS RESTRICTIVE
    USING (tenant_id = current_setting('app.current_tenant_id', true));
```

**Common mistakes:**

- `WHERE tenant_id = ?` unutmaq — data leak
- Subquery-də tenant_id olmamaq: `WHERE id IN (SELECT id FROM sub_table WHERE ...)` — sub_table-da da tenant_id lazımdır
- Cache key-ə tenant_id qoşmamaq: Redis-də `orders:123` key tenant-specific olmalıdır: `orders:acme:123`
- Background job-da tenant context-i itirmək: worker-ə tenant_id parametr kimi ötür

## Əlaqəli Mövzular

- `10-jwt-and-auth.md` — JWT-dən tenant claim oxumaq
- `08-caching.md` — tenant-aware cache key strategiyası
- `19-repository-pattern.md` — repository-də tenant filter
- `05-database.md` — connection pool idarəsi
