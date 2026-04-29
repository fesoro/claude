# Project Structure (Senior)

## İcmal

Go layihə strukturu `cmd/`, `internal/`, `pkg/` — ən geniş yayılmış strukturdur, lakin layihənin ölçüsünə görə dəyişir. Yanlış struktur seçimi — böyük layihədə refactoring çətinliyi, kiçik layihədə artıq mürəkkəblik.

## Niyə Vacibdir

- **`internal/`** — Go kompilyatoru tərəfindən enforce olunur: xarici paketlər import edə bilməz
- **`cmd/`** — bir layihədə bir neçə binary (`api`, `worker`, `cli`) mümkündür
- **Paket dizaynı** — import cycle, testability, reusability-ə birbaşa təsir edir
- **Layered architecture** — handler → service → repository bölgüsü boilerplate azaldır

## Əsas Anlayışlar

### Qovluq Rolları

| Qovluq | Rolu | İmport? |
|--------|------|---------|
| `cmd/` | Binary giriş nöqtəsi, `main` paketi | Bəli (əl ilə) |
| `internal/` | Layihəyə xas kod | **Yalnız bu layihə** |
| `pkg/` | Reusable utility-lər | İstənilən paket |
| `api/` | OpenAPI/proto sxemləri | Referans |
| `migrations/` | SQL migration faylları | DB tool-ları |
| `config/` | Konfiqurasiya faylları | Deployment |

### `internal/` Paketi

Go 1.4+ — Go kompilyatoru `internal/` altındakı paketlərə yalnız parent moduldan import icazəsi verir:

```
myapp/
├── internal/
│   └── auth/   ← yalnız myapp qəbul edə bilər
└── pkg/
    └── validator/ ← istənilən modul import edə bilər
```

```go
// DÜZGÜN — eyni modul
import "myapp/internal/auth"

// XƏTA — başqa modul
import "github.com/other/project/internal/auth" // compile xətası
```

### Layered Architecture

```
HTTP Request → Handler → Service → Repository → Database
                  ↓           ↓           ↓
               DTO         Domain       Query
             validation    logic        result
```

**Handler:** HTTP-yə xas logic (parse, validate, respond)
**Service:** Biznes qaydaları (business logic)
**Repository:** Data access (SQL, cache)

Bu ayrım test etməyi asanlaşdırır: service test edilərkən repository mock-lanır.

### Dependency Injection — `cmd/main.go`-da

```go
// cmd/api/main.go — "composition root"
db := setupDatabase(cfg)
userRepo := postgres.NewUserRepo(db)
emailSvc := smtp.NewEmailService(cfg)
userSvc := service.NewUserService(userRepo, emailSvc)
userHandler := handler.NewUserHandler(userSvc)
```

Bütün asılıqlıqlar ən yuxarıda yaranır. Paketlər bir-birini birbaşa create etmir.

### Paket Adlandırma Qaydaları

```go
// Düzgün
package user      // tək, sadə
package handler   // nə etdiyini bildirən
package postgres  // konkret implementasiya

// Yanlış
package users        // çoxluq
package userHandler  // CamelCase
package util         // çox geniş, mənasız
package helpers      // anti-pattern
```

### Interface — Domain-də Müəyyənlənir

```go
// internal/domain/user.go — Interface burada
type UserRepository interface {
    FindByID(ctx context.Context, id int) (*User, error)
    Save(ctx context.Context, user *User) error
}

// internal/adapter/repository/postgres/user.go — İmplementasiya burada
type userRepository struct { db *sql.DB }

func (r *userRepository) FindByID(ctx context.Context, id int) (*User, error) { ... }
```

Bu Clean Architecture-ın əsas prinsipidir: domain heç bir xarici paketi import etmir.

## Praktik Baxış

### Ne vaxt hansı strukturu seçmək?

| Layihə | Tövsiyə |
|--------|---------|
| Prototip / CLI tool | Flat structure (hamı `main` paketdə) |
| Kiçik API (< 5 endpoint) | `main.go` + `handler.go` + `storage.go` |
| Orta API | `cmd/`, `internal/{handler,service,repository}` |
| Böyük layihə | Clean/Hexagonal Architecture |

### Trade-off-lar

| Müfəssəl struktur | Çatışmazlıq |
|-----------------|-------------|
| Test edilə bilən | Çox boilerplate (kiçik layihə üçün) |
| Aydın ayrım | Çox fayl, çox interface |
| Yeni feature əlavəsi asan | İlk başlanğıc yavaş |

### Anti-pattern-lər

```go
// Anti-pattern 1: Hər şeyi "util" paketinə qoymaq
package util // mənasız, test çətin

// Anti-pattern 2: Circular import
// internal/user imports internal/order
// internal/order imports internal/user → COMPILE XƏTA

// Anti-pattern 3: Global mutable state
var db *sql.DB // global — test izolasiyası yoxdur

// Düzgün: dependency injection
func NewHandler(db *sql.DB) *Handler { ... }

// Anti-pattern 4: interface-i implementasiyada müəyyənləşdirmək
// adapter/repository/postgres/user.go-da:
type UserRepository interface { ... } // YANLIŞ — domain-də olmalıdır

// Anti-pattern 5: `pkg/` içinə internal logic yerləşdirmək
pkg/
└── auth/ ← bu layihəyə xasdırsa internal/ olmalıdır
```

## Nümunələr

### Nümunə 1: Sadə Layihə Strukturu

```
myapi/
├── go.mod
├── go.sum
├── main.go          ← main paketi
├── handler.go       ← HTTP handler-lər
├── service.go       ← biznes logic
├── model.go         ← struct-lar
├── storage.go       ← DB operations
├── middleware.go    ← middleware-lər
└── Makefile
```

```go
// main.go
package main

import (
    "database/sql"
    "log"
    "net/http"
    "os"
)

func main() {
    db, err := sql.Open("postgres", os.Getenv("DATABASE_URL"))
    if err != nil {
        log.Fatal(err)
    }
    defer db.Close()

    storage := NewStorage(db)
    service := NewService(storage)
    handler := NewHandler(service)

    mux := http.NewServeMux()
    mux.HandleFunc("GET /api/users", handler.ListUsers)
    mux.HandleFunc("POST /api/users", handler.CreateUser)
    mux.HandleFunc("GET /api/users/{id}", handler.GetUser)

    log.Println("Server :8080")
    log.Fatal(http.ListenAndServe(":8080", mux))
}
```

### Nümunə 2: Orta Ölçülü Layihə

```
myapp/
├── go.mod
├── go.sum
├── Makefile
├── Dockerfile
│
├── cmd/
│   ├── api/
│   │   └── main.go      ← HTTP API server
│   └── worker/
│       └── main.go      ← Background worker
│
├── internal/
│   ├── config/
│   │   └── config.go    ← env, yaml parsing
│   │
│   ├── domain/          ← Entity + Interface-lər
│   │   ├── user.go
│   │   ├── product.go
│   │   └── errors.go
│   │
│   ├── handler/         ← HTTP handler-lər
│   │   ├── user.go
│   │   ├── product.go
│   │   └── middleware.go
│   │
│   ├── service/         ← Biznes logic
│   │   ├── user.go
│   │   └── product.go
│   │
│   └── repository/      ← Data access
│       ├── postgres/
│       │   ├── user.go
│       │   └── product.go
│       └── redis/
│           └── cache.go
│
├── pkg/                 ← Reusable utilities
│   ├── pagination/
│   ├── validator/
│   └── logger/
│
├── migrations/
│   ├── 001_users.sql
│   └── 002_products.sql
│
└── config/
    ├── config.yaml
    └── config.example.yaml
```

```go
// internal/domain/user.go
package domain

import (
    "context"
    "time"
)

type User struct {
    ID        int
    Name      string
    Email     string
    CreatedAt time.Time
}

// Interface-lər domain-də müəyyənlənir
type UserRepository interface {
    FindByID(ctx context.Context, id int) (*User, error)
    FindByEmail(ctx context.Context, email string) (*User, error)
    Save(ctx context.Context, user *User) error
    Delete(ctx context.Context, id int) error
}

type EmailSender interface {
    SendWelcome(to, name string) error
}
```

```go
// internal/service/user.go
package service

import (
    "context"
    "fmt"
    "myapp/internal/domain"
)

type UserService struct {
    repo  domain.UserRepository // interface-ə asılı
    email domain.EmailSender
}

func NewUserService(repo domain.UserRepository, email domain.EmailSender) *UserService {
    return &UserService{repo: repo, email: email}
}

func (s *UserService) Create(ctx context.Context, name, email string) (*domain.User, error) {
    // Mövcudluq yoxla
    existing, _ := s.repo.FindByEmail(ctx, email)
    if existing != nil {
        return nil, fmt.Errorf("email artıq mövcuddur: %s", email)
    }

    user := &domain.User{Name: name, Email: email}
    if err := s.repo.Save(ctx, user); err != nil {
        return nil, fmt.Errorf("user yaratma: %w", err)
    }

    // Email göndər — xəta kritik deyil
    if err := s.email.SendWelcome(email, name); err != nil {
        // log et, amma davam et
        _ = err
    }

    return user, nil
}
```

```go
// cmd/api/main.go — Composition Root
package main

import (
    "log"
    "myapp/internal/config"
    "myapp/internal/handler"
    "myapp/internal/repository/postgres"
    "myapp/internal/service"
    "myapp/pkg/logger"
    "net/http"
    "os"
)

func main() {
    cfg := config.Load(os.Getenv("CONFIG_PATH"))
    log := logger.New(cfg.LogLevel)

    // Infrastructure
    db := postgres.Connect(cfg.DatabaseURL)
    defer db.Close()

    // Repository layer
    userRepo := postgres.NewUserRepository(db)

    // Service layer
    emailSvc := smtp.NewEmailService(cfg.SMTP)
    userSvc := service.NewUserService(userRepo, emailSvc)

    // Handler layer
    userHandler := handler.NewUserHandler(userSvc)

    // Router
    mux := http.NewServeMux()
    mux.HandleFunc("GET /api/users/{id}", userHandler.GetByID)
    mux.HandleFunc("POST /api/users", userHandler.Create)

    log.Info("Server başladı", "addr", cfg.Addr)
    http.ListenAndServe(cfg.Addr, mux)
}
```

### Nümunə 3: Clean Architecture (Böyük Layihə)

```
myapp/
├── cmd/api/main.go
│
└── internal/
    ├── domain/              ← HEÇ BİR XARICI PAKET IMPORT ETMİR
    │   ├── user/
    │   │   ├── entity.go   ← User struct
    │   │   ├── repository.go ← UserRepository interface
    │   │   └── service.go  ← UserService interface (optional)
    │   └── product/
    │
    ├── usecase/             ← Domain interface-lərini istifadə edir
    │   ├── user/
    │   │   ├── create.go
    │   │   ├── create_test.go
    │   │   └── get.go
    │   └── product/
    │
    └── adapter/             ← Xarici dünya ilə əlaqə
        ├── handler/         ← HTTP, gRPC
        │   ├── http/
        │   │   └── user.go
        │   └── grpc/
        │       └── user.go
        ├── repository/      ← DB implementasiya
        │   ├── postgres/
        │   │   └── user.go  ← UserRepository implement edir
        │   └── redis/
        └── email/           ← Email implementasiya
            └── smtp.go
```

### Nümunə 4: Makefile

```makefile
.PHONY: build run test lint clean migrate

build:
	go build -o bin/api ./cmd/api
	go build -o bin/worker ./cmd/worker

run:
	go run ./cmd/api

run-worker:
	go run ./cmd/worker

test:
	go test ./... -v -race -cover -coverprofile=coverage.out

test-unit:
	go test ./internal/... -v -race

bench:
	go test ./... -bench=. -benchmem

lint:
	golangci-lint run ./...

migrate-up:
	migrate -path migrations -database "$(DATABASE_URL)" up

migrate-down:
	migrate -path migrations -database "$(DATABASE_URL)" down 1

generate:
	go generate ./...

clean:
	rm -rf bin/
	rm -f coverage.out

docker-build:
	docker build -t myapp:latest .

docker-run:
	docker run -p 8080:8080 --env-file .env myapp:latest
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Blog API:**
`cmd/api/main.go` + `internal/{handler,service,repository,domain}` strukturu ilə sadə blog API yazın. Post CRUD + Comment əlavəsi. Repository interface mock ilə test edin.

**Tapşırıq 2 — Multi-binary:**
`cmd/api/` + `cmd/migration-tool/` + `cmd/seed/` — üç binary olan layihə yazın. Hamısı eyni `internal/` paketini istifadə etsin.

**Tapşırıq 3 — Refactoring:**
Flat struktur (`main.go` + `handler.go` + `storage.go`) olan layihəni `internal/` strukturuna migrate edin. Import cycle olmadan edin.

**Tapşırıq 4 — Package Analysis:**
`go list ./...` ilə bütün paketləri siyahılayın. `go vet ./...` keçirin. `golangci-lint` quraşdırın.

## PHP ilə Müqayisə

```
Laravel                          Go (orta layihə)
─────────────────────────────    ──────────────────────────────
app/Http/Controllers/           internal/handler/
app/Models/                     internal/domain/ + internal/model/
app/Services/                   internal/service/
app/Repositories/               internal/repository/
routes/api.php                  internal/handler/router.go
config/                         config/ + internal/config/
database/migrations/            migrations/
composer.json                   go.mod
app/Providers/                  cmd/api/main.go (composition root)
```

**Əsas fərqlər:**
1. Go-da auto-discovery yoxdur — hər şey açıq import edilir
2. Laravel service container — Go-da manual DI (composition root pattern)
3. Laravel convention-based → Go explicit architecture
4. Go-da `internal/` kompilyator tərəfindən enforce olunur — Laravel-də analog yoxdur

## Əlaqəli Mövzular

- [14-packages-and-modules](../core/14-packages-and-modules.md) — Paket əsasları
- [22-init-and-modules](../core/22-init-and-modules.md) — Go modules
- [17-graceful-shutdown](17-graceful-shutdown.md) — main.go-da shutdown
- [19-repository-pattern](19-repository-pattern.md) — Repository pattern
- [64-dependency-injection](../advanced/09-dependency-injection.md) — DI patterns
- [74-clean-architecture](../advanced/27-clean-architecture.md) — Clean Architecture dərin
