# Clean Architecture (Architect)

## İcmal

Clean Architecture — Robert C. Martin (Uncle Bob) tərəfindən irəli sürülmüş arxitektura yanaşmasıdır. Əsas fikir: **business logic xarici asılılıqlara (DB, HTTP, framework) bağlı olmamalıdır**. Hexagonal Architecture (Ports & Adapters) eyni fikri fərqli terminologiya ilə ifadə edir.

Go-da bu yanaşma xüsusilə uyğundur: interface-lər vasitəsilə asılılığı tərsinə çevirmək (Dependency Inversion) dil tərəfindən dəstəklənir, "implicit interface" isə kodu daha az verbose edir.

## Niyə Vacibdir

- Business logic-i müxtəlif delivery mexanizmləri ilə istifadə etmək: HTTP API, CLI, gRPC, message consumer — hamısı eyni usecase-i çağırır
- PostgreSQL-dən MongoDB-yə keçmək üçün yalnız repository implementasiyası dəyişir
- HTTP-dən gRPC-yə keçmək üçün yalnız handler dəyişir
- Unit test: mock repository ilə usecase test edilir — real DB lazım deyil
- Komanda büyüyəndə hər lay müstəqil inkişaf etdirilə bilər

## Əsas Anlayışlar

**Asılılıq qaydasının istiqaməti (Dependency Rule):**
- Xaricdən daxilə → Handler asılıdır Usecase-ə, Usecase asılıdır Domain-ə
- Heç vaxt tərsinə → Domain HTTP-ni bilmir, Usecase DB-ni bilmir

**Hexagonal Architecture (Ports & Adapters):**
- **Port**: interface — sistemin daxili tərifi (UserRepository, EmailSender)
- **Primary Adapter**: HTTP handler, gRPC server, CLI command (sisteme giriş)
- **Secondary Adapter**: PostgreSQL repo, Redis cache, SMTP sender (sistemdən çıxış)

**Laylar:**
1. **Domain**: entity, value object, domain xətaları — heç bir asılılıq yoxdur
2. **Usecase/Service**: business logic — yalnız Domain-ə asılıdır
3. **Repository Interface**: Usecase qatlında tərif edilir (port)
4. **Repository Implementation**: xarici qatlarda (adapter) — PostgreSQL, Redis, etc.
5. **Delivery/Handler**: HTTP, gRPC, CLI — Usecase-i çağırır

## Praktik Baxış

**Nə vaxt Clean Architecture tətbiq et:**
- Orta və böyük layihələr (10K+ sətir kod)
- Uzunmüddətli layihə (2+ il)
- Çoxlu developer komandası
- Testability prioritetdirsə
- DB-ni dəyişdirməyin ehtimalı varsa

**Nə vaxt lazım deyil:**
- Kiçik tool, script
- Prototip, MVP (əvvəl işlədir, sonra refaktor et)
- 1-2 nəfərlik komanda, tez çatdırılma lazımdır

**Trade-off-lar:**
- Daha çox fayl, daha çox kod — amma hər lay müstəqildir
- Yeni feature əlavə etmək: 4 fayl (domain + usecase + repo + handler) — daha çox struktur, daha az coupling
- Test: usecase-i mock ilə test etmək çox asandır
- Onboarding: yeni developer üçün struktur anlaşılması vaxt aparır

**Common mistakes:**
- Domain-də framework import etmək (gin, echo, sqlx — domain bilməməlidir)
- Usecase-də HTTP status code qaytarmaq — bu handler məsuliyyətidir
- Repository interface-ini xarici qatda (adapter-də) tərif etmək — domain/usecase qatında olmalıdır
- Hər şeyi interface etmək — sadə utility funksiyaları üçün lazım deyil

## Nümunələr

### Nümunə 1: Domain layer — Entity, Value Object, xətalar

```go
// internal/domain/user.go
package domain

import (
    "errors"
    "strings"
    "time"
)

// Domain xətaları — HTTP status code yoxdur, yalnız business xəta
var (
    ErrUserNotFound     = errors.New("istifadəçi tapılmadı")
    ErrEmailExists      = errors.New("bu email artıq qeydiyyatdadır")
    ErrInvalidEmail     = errors.New("email formatı yanlışdır")
    ErrInvalidPassword  = errors.New("parol minimum 8 simvol olmalıdır")
    ErrAccountDisabled  = errors.New("hesab deaktivdir")
)

// Entity — ID-si var, zaman içində dəyişir
type User struct {
    ID        int64
    Name      string
    Email     Email  // Value Object
    Password  string // hashlenmiş
    Active    bool
    CreatedAt time.Time
    UpdatedAt time.Time
}

// Domain validation — business qaydaları
func (u *User) Validate() error {
    if strings.TrimSpace(u.Name) == "" {
        return errors.New("ad boş ola bilməz")
    }
    if len(u.Name) < 2 || len(u.Name) > 100 {
        return errors.New("ad 2-100 simvol arasında olmalıdır")
    }
    return nil
}

// Value Object — ID-si yoxdur, dəyəri ilə müəyyən edilir
type Email struct {
    value string
}

func NewEmail(email string) (Email, error) {
    email = strings.ToLower(strings.TrimSpace(email))
    if !strings.Contains(email, "@") || !strings.Contains(email, ".") {
        return Email{}, ErrInvalidEmail
    }
    if len(email) < 5 || len(email) > 255 {
        return Email{}, ErrInvalidEmail
    }
    return Email{value: email}, nil
}

func (e Email) String() string { return e.value }
func (e Email) IsZero() bool   { return e.value == "" }
```

### Nümunə 2: Repository interface (port) — domain qatında

```go
// internal/domain/repository.go
package domain

import "context"

// Bu interface-dir — implementasiya yoxdur!
// Usecase bu interface-ə asılıdır, konkret DB-yə deyil.
// "Port" — sisteminizin daxili tərifi

type UserRepository interface {
    FindByID(ctx context.Context, id int64) (*User, error)
    FindByEmail(ctx context.Context, email string) (*User, error)
    Create(ctx context.Context, user *User) error
    Update(ctx context.Context, user *User) error
    Delete(ctx context.Context, id int64) error
    List(ctx context.Context, limit, offset int) ([]*User, int64, error)
}

// Digər port-lar
type PasswordHasher interface {
    Hash(password string) (string, error)
    Verify(hash, password string) bool
}

type EmailSender interface {
    SendWelcome(ctx context.Context, to Email, name string) error
    SendPasswordReset(ctx context.Context, to Email, token string) error
}

type CacheStore interface {
    Get(ctx context.Context, key string, dest interface{}) error
    Set(ctx context.Context, key string, value interface{}, ttl int) error
    Delete(ctx context.Context, key string) error
}
```

### Nümunə 3: Usecase — business logic

```go
// internal/usecase/user_usecase.go
package usecase

import (
    "context"
    "fmt"
    "myapp/internal/domain"
    "time"
)

// UserUsecase — business logic burada, HTTP/DB yoxdur
type UserUsecase struct {
    repo     domain.UserRepository  // interface — konkret implementasiya deyil
    hasher   domain.PasswordHasher
    emailer  domain.EmailSender
    cache    domain.CacheStore
}

func NewUserUsecase(
    repo domain.UserRepository,
    hasher domain.PasswordHasher,
    emailer domain.EmailSender,
    cache domain.CacheStore,
) *UserUsecase {
    return &UserUsecase{
        repo:    repo,
        hasher:  hasher,
        emailer: emailer,
        cache:   cache,
    }
}

// Register — qeydiyyat business logic
func (uc *UserUsecase) Register(ctx context.Context, name, emailStr, password string) (*domain.User, error) {
    // 1. Email formatını yoxla (domain validation)
    email, err := domain.NewEmail(emailStr)
    if err != nil {
        return nil, err // domain.ErrInvalidEmail
    }

    // 2. Email mövcuddurmu yoxla
    existing, err := uc.repo.FindByEmail(ctx, email.String())
    if err != nil && err != domain.ErrUserNotFound {
        return nil, fmt.Errorf("email yoxlama xətası: %w", err)
    }
    if existing != nil {
        return nil, domain.ErrEmailExists
    }

    // 3. Parolu hashle
    if len(password) < 8 {
        return nil, domain.ErrInvalidPassword
    }
    hash, err := uc.hasher.Hash(password)
    if err != nil {
        return nil, fmt.Errorf("parol hashleme xətası: %w", err)
    }

    // 4. Entity yarat
    user := &domain.User{
        Name:      name,
        Email:     email,
        Password:  hash,
        Active:    true,
        CreatedAt: time.Now(),
        UpdatedAt: time.Now(),
    }

    // 5. Domain validation
    if err := user.Validate(); err != nil {
        return nil, err
    }

    // 6. Database-ə yaz
    if err := uc.repo.Create(ctx, user); err != nil {
        return nil, fmt.Errorf("istifadəçi yaratma xətası: %w", err)
    }

    // 7. Xoş gəldin emaili göndər (xəta kritik deyil)
    go func() {
        if err := uc.emailer.SendWelcome(context.Background(), user.Email, user.Name); err != nil {
            // log et, amma register-i xəta etmə
            _ = err
        }
    }()

    return user, nil
}

// GetUser — ID ilə istifadəçi al (cache-dən əvvəl, sonra DB)
func (uc *UserUsecase) GetUser(ctx context.Context, id int64) (*domain.User, error) {
    // Cache-dən oxu
    var user domain.User
    cacheKey := fmt.Sprintf("user:%d", id)
    if err := uc.cache.Get(ctx, cacheKey, &user); err == nil {
        return &user, nil
    }

    // DB-dən oxu
    u, err := uc.repo.FindByID(ctx, id)
    if err != nil {
        return nil, err // domain.ErrUserNotFound
    }

    // Cache-ə yaz (300 saniyə TTL)
    uc.cache.Set(ctx, cacheKey, u, 300)

    return u, nil
}
```

### Nümunə 4: Repository implementation (adapter) — PostgreSQL

```go
// internal/repository/postgres/user_repo.go
package postgres

import (
    "context"
    "database/sql"
    "fmt"
    "myapp/internal/domain"
)

// domain.UserRepository interface-ini implement edir
// "Secondary Adapter" — sistemi DB-yə bağlayır
type UserRepo struct {
    db *sql.DB
}

func NewUserRepo(db *sql.DB) *UserRepo {
    return &UserRepo{db: db}
}

// Interface-ə uyğunluğu compile zamanı yoxla
var _ domain.UserRepository = (*UserRepo)(nil)

func (r *UserRepo) FindByID(ctx context.Context, id int64) (*domain.User, error) {
    user := &domain.User{}
    var email string

    err := r.db.QueryRowContext(ctx,
        `SELECT id, name, email, password, active, created_at, updated_at
         FROM users WHERE id = $1 AND deleted_at IS NULL`,
        id,
    ).Scan(&user.ID, &user.Name, &email, &user.Password, &user.Active,
        &user.CreatedAt, &user.UpdatedAt)

    if err == sql.ErrNoRows {
        return nil, domain.ErrUserNotFound
    }
    if err != nil {
        return nil, fmt.Errorf("db sorğu xətası: %w", err)
    }

    // DB string-dən Value Object-ə çevir
    user.Email, _ = domain.NewEmail(email)
    return user, nil
}

func (r *UserRepo) FindByEmail(ctx context.Context, email string) (*domain.User, error) {
    user := &domain.User{}
    var emailStr string

    err := r.db.QueryRowContext(ctx,
        `SELECT id, name, email, password, active FROM users WHERE email = $1`,
        email,
    ).Scan(&user.ID, &user.Name, &emailStr, &user.Password, &user.Active)

    if err == sql.ErrNoRows {
        return nil, domain.ErrUserNotFound
    }
    if err != nil {
        return nil, fmt.Errorf("db sorğu xətası: %w", err)
    }

    user.Email, _ = domain.NewEmail(emailStr)
    return user, nil
}

func (r *UserRepo) Create(ctx context.Context, user *domain.User) error {
    return r.db.QueryRowContext(ctx,
        `INSERT INTO users (name, email, password, active, created_at, updated_at)
         VALUES ($1, $2, $3, $4, $5, $6) RETURNING id`,
        user.Name, user.Email.String(), user.Password, user.Active,
        user.CreatedAt, user.UpdatedAt,
    ).Scan(&user.ID)
}

func (r *UserRepo) Update(ctx context.Context, user *domain.User) error {
    _, err := r.db.ExecContext(ctx,
        `UPDATE users SET name=$1, email=$2, active=$3, updated_at=$4 WHERE id=$5`,
        user.Name, user.Email.String(), user.Active, user.UpdatedAt, user.ID,
    )
    return err
}

func (r *UserRepo) Delete(ctx context.Context, id int64) error {
    // Soft delete
    _, err := r.db.ExecContext(ctx,
        `UPDATE users SET deleted_at = NOW() WHERE id = $1`, id)
    return err
}

func (r *UserRepo) List(ctx context.Context, limit, offset int) ([]*domain.User, int64, error) {
    var total int64
    if err := r.db.QueryRowContext(ctx, `SELECT COUNT(*) FROM users WHERE deleted_at IS NULL`).Scan(&total); err != nil {
        return nil, 0, err
    }

    rows, err := r.db.QueryContext(ctx,
        `SELECT id, name, email, active FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT $1 OFFSET $2`,
        limit, offset)
    if err != nil {
        return nil, 0, err
    }
    defer rows.Close()

    var users []*domain.User
    for rows.Next() {
        user := &domain.User{}
        var email string
        if err := rows.Scan(&user.ID, &user.Name, &email, &user.Active); err != nil {
            return nil, 0, err
        }
        user.Email, _ = domain.NewEmail(email)
        users = append(users, user)
    }

    return users, total, rows.Err()
}
```

### Nümunə 5: HTTP Handler (delivery layer)

```go
// internal/delivery/http/user_handler.go
package http

import (
    "encoding/json"
    "errors"
    "myapp/internal/domain"
    "myapp/internal/usecase"
    "net/http"
    "strconv"
)

// Handler — HTTP detallarını idarə edir, usecase-i çağırır
// Usecase-in domain xətalarını HTTP status code-lara çevirir
type UserHandler struct {
    usecase *usecase.UserUsecase
}

func NewUserHandler(uc *usecase.UserUsecase) *UserHandler {
    return &UserHandler{usecase: uc}
}

// Request/Response DTO-lar — domain entity-dən ayrı
type RegisterRequest struct {
    Name     string `json:"name"`
    Email    string `json:"email"`
    Password string `json:"password"`
}

type UserResponse struct {
    ID    int64  `json:"id"`
    Name  string `json:"name"`
    Email string `json:"email"`
}

type ErrorResponse struct {
    Error   string `json:"error"`
    Message string `json:"message"`
}

func (h *UserHandler) Register(w http.ResponseWriter, r *http.Request) {
    var req RegisterRequest
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        h.writeError(w, http.StatusBadRequest, "yanlış format", err.Error())
        return
    }

    // Usecase-i çağır
    user, err := h.usecase.Register(r.Context(), req.Name, req.Email, req.Password)
    if err != nil {
        // Domain xətalarını HTTP status code-lara çevir
        // BU HANDLER MƏSULİYYƏTİDİR — usecase bilmir HTTP nədir
        switch {
        case errors.Is(err, domain.ErrEmailExists):
            h.writeError(w, http.StatusConflict, "email_exists", err.Error())
        case errors.Is(err, domain.ErrInvalidEmail):
            h.writeError(w, http.StatusBadRequest, "invalid_email", err.Error())
        case errors.Is(err, domain.ErrInvalidPassword):
            h.writeError(w, http.StatusBadRequest, "invalid_password", err.Error())
        default:
            h.writeError(w, http.StatusInternalServerError, "internal_error", "daxili xəta")
        }
        return
    }

    // Domain entity-dən DTO-ya çevir (parol GÖNDƏRMƏ)
    resp := UserResponse{
        ID:    user.ID,
        Name:  user.Name,
        Email: user.Email.String(),
    }

    h.writeJSON(w, http.StatusCreated, resp)
}

func (h *UserHandler) GetUser(w http.ResponseWriter, r *http.Request) {
    // Go 1.22+ ilə: r.PathValue("id")
    idStr := r.PathValue("id")
    id, err := strconv.ParseInt(idStr, 10, 64)
    if err != nil {
        h.writeError(w, http.StatusBadRequest, "invalid_id", "yanlış ID formatı")
        return
    }

    user, err := h.usecase.GetUser(r.Context(), id)
    if err != nil {
        if errors.Is(err, domain.ErrUserNotFound) {
            h.writeError(w, http.StatusNotFound, "not_found", err.Error())
            return
        }
        h.writeError(w, http.StatusInternalServerError, "internal_error", "daxili xəta")
        return
    }

    h.writeJSON(w, http.StatusOK, UserResponse{
        ID:    user.ID,
        Name:  user.Name,
        Email: user.Email.String(),
    })
}

// Router
func (h *UserHandler) RegisterRoutes(mux *http.ServeMux) {
    mux.HandleFunc("POST /api/users/register", h.Register)
    mux.HandleFunc("GET /api/users/{id}", h.GetUser)
}

func (h *UserHandler) writeJSON(w http.ResponseWriter, status int, data interface{}) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(status)
    json.NewEncoder(w).Encode(data)
}

func (h *UserHandler) writeError(w http.ResponseWriter, status int, code, msg string) {
    h.writeJSON(w, status, ErrorResponse{Error: code, Message: msg})
}
```

### Nümunə 6: Dependency Injection — main.go

```go
// cmd/api/main.go
package main

import (
    "database/sql"
    "log"
    "log/slog"
    "net/http"
    "os"
    "os/signal"
    "syscall"
    "time"

    _ "github.com/lib/pq"

    "myapp/internal/delivery/httphandler"
    "myapp/internal/domain"
    "myapp/internal/repository/postgres"
    "myapp/internal/usecase"
)

// Bütün layları bir-birinə bağlayır (wiring)
func main() {
    logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))
    slog.SetDefault(logger)

    // ---- Infrastructure ----
    db, err := sql.Open("postgres", os.Getenv("DB_DSN"))
    if err != nil {
        log.Fatal(err)
    }
    defer db.Close()

    db.SetMaxOpenConns(25)
    db.SetMaxIdleConns(5)
    db.SetConnMaxLifetime(5 * time.Minute)

    if err := db.Ping(); err != nil {
        log.Fatal("DB bağlantısı yoxdur:", err)
    }

    // ---- Repository (Secondary Adapters) ----
    userRepo := postgres.NewUserRepo(db)

    // ---- Adapters (Mocks test üçün buraya inject edilə bilər) ----
    var hasher domain.PasswordHasher = &BcryptHasher{}
    var emailer domain.EmailSender = &SMTPEmailer{}
    var cache domain.CacheStore = &RedisCache{}

    // ---- Usecase (Business Logic) ----
    userUsecase := usecase.NewUserUsecase(userRepo, hasher, emailer, cache)

    // ---- Delivery (Primary Adapters) ----
    userHandler := httphandler.NewUserHandler(userUsecase)

    // ---- Router ----
    mux := http.NewServeMux()
    userHandler.RegisterRoutes(mux)

    // Health endpoints
    mux.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
        w.Write([]byte(`{"status":"alive"}`))
    })

    // ---- Server ----
    server := &http.Server{
        Addr:         ":" + getEnv("PORT", "8080"),
        Handler:      mux,
        ReadTimeout:  15 * time.Second,
        WriteTimeout: 15 * time.Second,
        IdleTimeout:  60 * time.Second,
    }

    // Graceful shutdown
    go func() {
        slog.Info("Server başladı", slog.String("addr", server.Addr))
        if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
            log.Fatal(err)
        }
    }()

    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGTERM, syscall.SIGINT)
    <-quit

    slog.Info("Server dayanır...")
    // server.Shutdown(ctx) çağır
}

// Placeholder implementasiyalar
type BcryptHasher struct{}
func (b *BcryptHasher) Hash(p string) (string, error) { return p, nil }
func (b *BcryptHasher) Verify(h, p string) bool       { return h == p }

type SMTPEmailer struct{}
func (s *SMTPEmailer) SendWelcome(ctx interface{ Done() <-chan struct{} }, to domain.Email, name string) error { return nil }
func (s *SMTPEmailer) SendPasswordReset(ctx interface{ Done() <-chan struct{} }, to domain.Email, token string) error { return nil }

type RedisCache struct{}
func (r *RedisCache) Get(ctx interface{}, key string, dest interface{}) error { return fmt.Errorf("miss") }
func (r *RedisCache) Set(ctx interface{}, key string, value interface{}, ttl int) error { return nil }
func (r *RedisCache) Delete(ctx interface{}, key string) error { return nil }

func getEnv(key, fallback string) string {
    if v := os.Getenv(key); v != "" {
        return v
    }
    return fallback
}

import "fmt"
```

### Nümunə 7: Layihə strukturu

```
myapp/
├── cmd/
│   └── api/
│       └── main.go              # Giriş nöqtəsi, DI, server başlama
├── internal/
│   ├── domain/                  # Ən daxili lay — heç bir asılılıq yoxdur
│   │   ├── user.go              # Entity, Value Objects
│   │   ├── order.go
│   │   ├── repository.go        # Repository interface-ləri (port-lar)
│   │   └── errors.go            # Domain xətaları
│   ├── usecase/                 # Business logic
│   │   ├── user_usecase.go
│   │   ├── user_usecase_test.go # Mock repo ilə test — real DB lazım deyil
│   │   └── order_usecase.go
│   ├── repository/              # Repository implementasiyaları (secondary adapters)
│   │   ├── postgres/
│   │   │   ├── user_repo.go
│   │   │   └── order_repo.go
│   │   ├── redis/
│   │   │   └── cache_repo.go
│   │   └── memory/              # Test üçün in-memory repo
│   │       └── user_repo.go
│   └── delivery/                # Xarici interfeys (primary adapters)
│       ├── http/
│       │   ├── user_handler.go
│       │   ├── middleware.go
│       │   └── router.go
│       ├── grpc/
│       │   └── user_server.go
│       └── consumer/            # Message Queue consumer
│           └── order_consumer.go
├── pkg/                         # Paylaşılan yardımçı paketler
│   ├── logger/
│   ├── validator/
│   └── crypto/
├── migrations/
├── config/
│   └── config.yaml
├── go.mod
└── go.sum
```

### Nümunə 8: Usecase unit test — mock ilə

```go
// internal/usecase/user_usecase_test.go
package usecase_test

import (
    "context"
    "errors"
    "testing"

    "myapp/internal/domain"
    "myapp/internal/usecase"
)

// Mock Repository — real DB olmadan test
type MockUserRepo struct {
    users  map[string]*domain.User
    nextID int64
}

func NewMockUserRepo() *MockUserRepo {
    return &MockUserRepo{
        users:  make(map[string]*domain.User),
        nextID: 1,
    }
}

func (m *MockUserRepo) FindByID(ctx context.Context, id int64) (*domain.User, error) {
    for _, u := range m.users {
        if u.ID == id {
            return u, nil
        }
    }
    return nil, domain.ErrUserNotFound
}

func (m *MockUserRepo) FindByEmail(ctx context.Context, email string) (*domain.User, error) {
    if u, ok := m.users[email]; ok {
        return u, nil
    }
    return nil, domain.ErrUserNotFound
}

func (m *MockUserRepo) Create(ctx context.Context, user *domain.User) error {
    user.ID = m.nextID
    m.nextID++
    m.users[user.Email.String()] = user
    return nil
}

func (m *MockUserRepo) Update(ctx context.Context, user *domain.User) error { return nil }
func (m *MockUserRepo) Delete(ctx context.Context, id int64) error { return nil }
func (m *MockUserRepo) List(ctx context.Context, limit, offset int) ([]*domain.User, int64, error) {
    return nil, 0, nil
}

// Mock Hasher
type MockHasher struct{}

func (m *MockHasher) Hash(p string) (string, error) { return "hashed:" + p, nil }
func (m *MockHasher) Verify(h, p string) bool       { return h == "hashed:"+p }

// Mock EmailSender
type MockEmailSender struct {
    sent []string
}

func (m *MockEmailSender) SendWelcome(ctx context.Context, to domain.Email, name string) error {
    m.sent = append(m.sent, to.String())
    return nil
}

func (m *MockEmailSender) SendPasswordReset(ctx context.Context, to domain.Email, token string) error {
    return nil
}

// Mock Cache
type MockCache struct{}

func (m *MockCache) Get(ctx context.Context, key string, dest interface{}) error {
    return errors.New("cache miss")
}
func (m *MockCache) Set(ctx context.Context, key string, value interface{}, ttl int) error {
    return nil
}
func (m *MockCache) Delete(ctx context.Context, key string) error { return nil }

// Testlər
func TestRegister_Success(t *testing.T) {
    repo := NewMockUserRepo()
    uc := usecase.NewUserUsecase(repo, &MockHasher{}, &MockEmailSender{}, &MockCache{})

    user, err := uc.Register(context.Background(), "Orxan", "orxan@test.com", "password123")
    if err != nil {
        t.Fatalf("gözlənilməyən xəta: %v", err)
    }
    if user.ID == 0 {
        t.Error("user ID sıfır olmamalıdır")
    }
    if user.Name != "Orxan" {
        t.Errorf("gözlənilən ad: Orxan, alınan: %s", user.Name)
    }
}

func TestRegister_DuplicateEmail(t *testing.T) {
    repo := NewMockUserRepo()
    uc := usecase.NewUserUsecase(repo, &MockHasher{}, &MockEmailSender{}, &MockCache{})

    uc.Register(context.Background(), "Orxan", "orxan@test.com", "password123")

    _, err := uc.Register(context.Background(), "Əli", "orxan@test.com", "password456")
    if !errors.Is(err, domain.ErrEmailExists) {
        t.Errorf("gözlənilən: ErrEmailExists, alınan: %v", err)
    }
}

func TestRegister_InvalidEmail(t *testing.T) {
    repo := NewMockUserRepo()
    uc := usecase.NewUserUsecase(repo, &MockHasher{}, &MockEmailSender{}, &MockCache{})

    _, err := uc.Register(context.Background(), "Orxan", "yanlış-email", "password123")
    if !errors.Is(err, domain.ErrInvalidEmail) {
        t.Errorf("gözlənilən: ErrInvalidEmail, alınan: %v", err)
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Domain layer:**
1. `Product` entity yazın: ID, Name, Price (value object), Stock
2. Price value object: mənfi ola bilməz, currency saxla
3. Domain validation: `product.Validate()` funksiyası
4. Domain xətaları: `ErrProductNotFound`, `ErrInsufficientStock`

**Tapşırıq 2 — Repository interface + İki implementasiya:**
1. `ProductRepository` interface yazın
2. PostgreSQL implementasiyası yazın
3. In-memory implementasiyası yazın (test üçün)
4. Hər ikisini eyni interface ilə istifadə edin

**Tapşırıq 3 — Usecase test:**
1. `ProductUsecase.Purchase(ctx, productID, quantity int) error` yazın
2. Stok yoxlama, azaltma, log business logic
3. Mock repo ilə 5 unit test yazın:
   - Uğurlu alış
   - Stok yetərsiz
   - Məhsul tapılmadı
   - Mənfi quantity
   - Concurrent purchase (race condition)

**Tapşırıq 4 — Delivery layer:**
1. HTTP handler yazın: `POST /api/products/{id}/purchase`
2. Request validation
3. Domain xətalarını HTTP status code-lara map et
4. `httptest.NewRecorder()` ilə handler test edin

**Tapşırıq 5 — Laravel MVC-dən refaktor:**
Mövcud bir Laravel controller funksiyasını götürün (DB sorğusu olan), onu Go Clean Architecture-a köçürün:
1. Domain entity
2. Repository interface + PostgreSQL implementasiyası
3. Usecase (business logic)
4. HTTP handler

## Ətraflı Qeydlər

**Unit of Work pattern:**
Bir neçə repository əməliyyatını tək tranzaksiyada icra etmək üçün UnitOfWork interface — bax əsas .go faylındakı nümunə.

**Wire (compile-time DI):**
```bash
go install github.com/google/wire/cmd/wire@latest
# wire.go faylı yaradın, wire komandası real DI kodu generasiya edir
wire ./cmd/api
```

## PHP ilə Müqayisə

PHP/Laravel MVC ilə müqayisə: Laravel-də Controller → Model birbaşa DB-yə müraciət edir. Clean Architecture-da isə Handler → Usecase → Repository Interface → DB iyerarxiyası var. Daha çox boilerplate, amma test edilə bilən, dəyişdirilə bilən, uzunömürlü kod.

```
Laravel MVC:
Router → Controller → Model → DB
               ↓
           View/JSON

Clean Architecture:
HTTP Router → Handler (delivery) → Usecase (business) → Repository interface
                                                              ↓
                                                  Repository implementation → DB
```

Laravel Controller-da bütün məntiq bir yerdədir:

```php
public function store(Request $request) {
    $validated = $request->validate([...]);
    $user = User::where('email', $validated['email'])->first();
    if ($user) abort(409);
    $user = User::create([..., 'password' => bcrypt(...)]);
    Mail::to($user)->send(new WelcomeMail());
    return response()->json($user, 201);
}
```

Go Clean Architecture-da ayrılmış məsuliyyət:
- Handler → validate HTTP, call usecase
- Usecase → check email exists, hash password, create, send email
- Repository → SQL query
- Domain → entity, validation rules

Yeni feature əlavə etmək Laravel-də 2 fayl (controller + model) tələb edərkən Clean Architecture-da 4 fayl (domain + usecase + repo + handler) tələb edir — amma hər fayl müstəqil test edilə bilir.

## Əlaqəli Mövzular

- [73-microservices.md](73-microservices.md) — Hər microservice Clean Arch-a əsaslanır
- [55-repository-pattern.md](55-repository-pattern.md) — Repository pattern ətraflı
- [64-dependency-injection.md](64-dependency-injection.md) — DI pattern-ləri Go-da
- [52-mocking-and-testify.md](52-mocking-and-testify.md) — Mock ilə test yazma
- [37-database.md](37-database.md) — Database ilə işləmə
