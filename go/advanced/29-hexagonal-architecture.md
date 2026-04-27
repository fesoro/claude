# Hexagonal Architecture (Lead)

## İcmal

Hexagonal Architecture (Ports & Adapters) — Alistair Cockburn tərəfindən təqdim edilmiş arxitektura pattern-i. Əsas fikir: **domain (iş məntiqi) mərkəzdə dayanır** və xarici dünya ilə yalnız **port-lar** (interface-lər) vasitəsilə ünsiyyət qurur. HTTP handler, PostgreSQL, Redis — bunlar hamısı **adapter**-lərdir, domain layer bunları bilmir. Go-da bu pattern təbii oturur çünki interface-lər implicit-dir.

## Niyə Vacibdir

Laravel-də business logic tez-tez Eloquent model-ləri, controller-lər, arasında yayılır. Test yazmaq çətin olur çünki HTTP, DB, framework bir-birinə bağlıdır. Hexagonal architecture bu problemləri həll edir: domain logic tamamilə izolə olunur, adapter-ləri mock edib domain-i sürətlə test etmək mümkündür. Yeni adapter əlavə etmək (məs: PostgreSQL-dən MongoDB-yə keçid) domain-ə toxunmadan mümkün olur.

## Əsas Anlayışlar

- **Domain** — xalis iş məntiqi; heç bir external dependency yoxdur (no ORM, no HTTP, no Redis)
- **Port** — domain-in xarici dünya ilə ünsiyyət üçün müəyyən etdiyi interface. İki növ:
  - **Input Port (Driving Port)** — xarici dünyadan domain-ə gələn çağrılar (use case interface-ləri)
  - **Output Port (Driven Port)** — domain-dən xarici dünyaya gedən çağrılar (repository, email, cache interface-ləri)
- **Adapter** — port-u implement edən konkret kod. İki növ:
  - **Input Adapter** — HTTP handler, gRPC handler, CLI, message consumer
  - **Output Adapter** — PostgreSQL repository, Redis cache, SMTP mailer
- **Application Service** — input port-ları implement edir, output port-lardan istifadə edir; orchestration
- **Dependency Rule** — bağımlılıq yalnız içəriyə doğru: Adapter → Application → Domain

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Microservice-lər üçün ideal: hər servisin öz hexagonal strukturu
- Test yazmaq çox asanlaşır: real DB olmadan domain-i tam test etmək mümkün
- Infrastructure dəyişiklikləri domain-ə toxunmur

**Trade-off-lar:**
- Daha çox fayl və folder: `internal/domain/`, `internal/ports/`, `internal/adapters/`
- Kiçik komandalar üçün overkill ola bilər
- Interface-lərin sayı artır — bu bəzən navigation-ı çətinləşdirir
- Clean Architecture ilə demək olar eynidir; fərq terminologiyadadır

**Nə vaxt istifadə etməmək lazımdır:**
- Sadə CRUD microservice (3-4 endpoint)
- Prototip/MVP mərhələsi
- Komanda bu pattern-ə alışmayıbsa — ləng tətbiq

**Ümumi səhvlər:**
- Domain entity-lərinə GORM tag əlavə etmək — domain xarici paketi import etməməlidir
- Application service-də business logic yazmaq — bu domain-ə aiddir
- Port interface-lərini adapter package-ə qoymaq — port-lar domain/ports package-ə aiddir
- Hər şey üçün interface yaratmaq — yalnız dəyişə bilən şeylər üçün port lazımdır

## Nümunələr

### Ümumi Nümunə

```
┌─────────────────────────────────────────────────┐
│                   ADAPTERS                      │
│                                                 │
│  ┌─────────────┐         ┌─────────────────┐   │
│  │ HTTP Handler│         │ PostgreSQL Repo  │   │
│  │ (Input)     │         │ (Output)         │   │
│  └──────┬──────┘         └────────┬────────┘   │
│         │ calls Input Port        │ implements  │
│         ▼                         │ Output Port  │
│  ┌──────────────────────────────────────────┐  │
│  │           PORTS (interfaces)             │  │
│  │  Input Port: UserUseCase                 │  │
│  │  Output Port: UserRepository             │  │
│  └──────────────┬───────────────────────────┘  │
│                 │ implements/uses               │
│         ┌───────▼──────────────────┐           │
│         │   APPLICATION SERVICE    │           │
│         │   UserApplicationService │           │
│         └───────┬──────────────────┘           │
│                 │ uses                          │
│         ┌───────▼──────────────────┐           │
│         │       DOMAIN             │           │
│         │  User entity             │           │
│         │  Domain rules            │           │
│         └──────────────────────────┘           │
└─────────────────────────────────────────────────┘
```

### Kod Nümunəsi

**Folder strukturu:**

```
internal/
  domain/
    user.go           ← Entity + domain rules
  ports/
    input/
      user_usecase.go ← Input ports (use case interfaces)
    output/
      user_repo.go    ← Output ports (repository interface)
  adapters/
    http/
      user_handler.go ← Input adapter
    postgres/
      user_repo.go    ← Output adapter
  app/
    user_service.go   ← Application service (orchestrator)
cmd/
  api/
    main.go
```

**Domain layer — sıfır external dependency:**

```go
// internal/domain/user.go
package domain

import (
    "errors"
    "time"
)

// Domain errors — framework-dən asılı deyil
var (
    ErrUserNotFound    = errors.New("user not found")
    ErrEmailTaken      = errors.New("email already taken")
    ErrInvalidEmail    = errors.New("invalid email format")
)

type UserID string

type User struct {
    ID        UserID
    Name      string
    Email     string
    Active    bool
    CreatedAt time.Time
}

// Domain business rule — entity-nin özündə
func (u *User) Deactivate() error {
    if !u.Active {
        return errors.New("user is already inactive")
    }
    u.Active = false
    return nil
}

func NewUser(id UserID, name, email string) (*User, error) {
    if email == "" || !isValidEmail(email) {
        return nil, ErrInvalidEmail
    }
    return &User{
        ID:        id,
        Name:      name,
        Email:     email,
        Active:    true,
        CreatedAt: time.Now(),
    }, nil
}

func isValidEmail(email string) bool {
    return len(email) > 3 && contains(email, "@")
}

func contains(s, sub string) bool {
    return len(s) >= len(sub) && (s == sub || len(sub) == 0 ||
        (len(s) > 0 && containsAt(s, sub)))
}

func containsAt(s, sub string) bool {
    for i := 0; i <= len(s)-len(sub); i++ {
        if s[i:i+len(sub)] == sub {
            return true
        }
    }
    return false
}
```

**Output Port — domain layer-da interface:**

```go
// internal/ports/output/user_repo.go
package output

import (
    "context"
    "github.com/yourorg/app/internal/domain"
)

type UserRepository interface {
    Save(ctx context.Context, user *domain.User) error
    FindByID(ctx context.Context, id domain.UserID) (*domain.User, error)
    FindByEmail(ctx context.Context, email string) (*domain.User, error)
    Delete(ctx context.Context, id domain.UserID) error
}
```

**Input Port — application service üçün interface:**

```go
// internal/ports/input/user_usecase.go
package input

import (
    "context"
    "github.com/yourorg/app/internal/domain"
)

type CreateUserRequest struct {
    Name  string
    Email string
}

type UserUseCase interface {
    CreateUser(ctx context.Context, req CreateUserRequest) (*domain.User, error)
    GetUser(ctx context.Context, id domain.UserID) (*domain.User, error)
    DeactivateUser(ctx context.Context, id domain.UserID) error
}
```

**Application Service — port-ları implement edir, domain-i orchestrate edir:**

```go
// internal/app/user_service.go
package app

import (
    "context"
    "fmt"

    "github.com/yourorg/app/internal/domain"
    "github.com/yourorg/app/internal/ports/input"
    "github.com/yourorg/app/internal/ports/output"
    "github.com/google/uuid"
)

// Input port-u implement edir
type UserApplicationService struct {
    repo output.UserRepository // output port-a depend edir, concrete-ə deyil
}

func NewUserApplicationService(repo output.UserRepository) *UserApplicationService {
    return &UserApplicationService{repo: repo}
}

// input.UserUseCase interface-ini implement edir
func (s *UserApplicationService) CreateUser(
    ctx context.Context,
    req input.CreateUserRequest,
) (*domain.User, error) {
    // Email unikallığını yoxla
    existing, _ := s.repo.FindByEmail(ctx, req.Email)
    if existing != nil {
        return nil, domain.ErrEmailTaken
    }

    user, err := domain.NewUser(domain.UserID(uuid.NewString()), req.Name, req.Email)
    if err != nil {
        return nil, fmt.Errorf("creating user: %w", err)
    }

    if err := s.repo.Save(ctx, user); err != nil {
        return nil, fmt.Errorf("saving user: %w", err)
    }

    return user, nil
}

func (s *UserApplicationService) GetUser(
    ctx context.Context,
    id domain.UserID,
) (*domain.User, error) {
    user, err := s.repo.FindByID(ctx, id)
    if err != nil {
        return nil, fmt.Errorf("finding user: %w", err)
    }
    return user, nil
}

func (s *UserApplicationService) DeactivateUser(
    ctx context.Context,
    id domain.UserID,
) error {
    user, err := s.repo.FindByID(ctx, id)
    if err != nil {
        return fmt.Errorf("finding user: %w", err)
    }

    if err := user.Deactivate(); err != nil {
        return fmt.Errorf("deactivating user: %w", err)
    }

    return s.repo.Save(ctx, user)
}
```

**Output Adapter — PostgreSQL implementasiyası:**

```go
// internal/adapters/postgres/user_repo.go
package postgres

import (
    "context"
    "database/sql"
    "errors"

    "github.com/yourorg/app/internal/domain"
)

// output.UserRepository interface-ini implement edir
type PostgresUserRepository struct {
    db *sql.DB
}

func NewPostgresUserRepository(db *sql.DB) *PostgresUserRepository {
    return &PostgresUserRepository{db: db}
}

func (r *PostgresUserRepository) Save(ctx context.Context, user *domain.User) error {
    _, err := r.db.ExecContext(ctx,
        `INSERT INTO users (id, name, email, active, created_at)
         VALUES ($1, $2, $3, $4, $5)
         ON CONFLICT (id) DO UPDATE SET name=$2, email=$3, active=$4`,
        string(user.ID), user.Name, user.Email, user.Active, user.CreatedAt,
    )
    return err
}

func (r *PostgresUserRepository) FindByID(
    ctx context.Context,
    id domain.UserID,
) (*domain.User, error) {
    var u domain.User
    var userID string

    err := r.db.QueryRowContext(ctx,
        `SELECT id, name, email, active, created_at FROM users WHERE id = $1`,
        string(id),
    ).Scan(&userID, &u.Name, &u.Email, &u.Active, &u.CreatedAt)

    if errors.Is(err, sql.ErrNoRows) {
        return nil, domain.ErrUserNotFound
    }
    if err != nil {
        return nil, err
    }

    u.ID = domain.UserID(userID)
    return &u, nil
}

func (r *PostgresUserRepository) FindByEmail(
    ctx context.Context,
    email string,
) (*domain.User, error) {
    var u domain.User
    var userID string

    err := r.db.QueryRowContext(ctx,
        `SELECT id, name, email, active, created_at FROM users WHERE email = $1`,
        email,
    ).Scan(&userID, &u.Name, &u.Email, &u.Active, &u.CreatedAt)

    if errors.Is(err, sql.ErrNoRows) {
        return nil, nil
    }
    if err != nil {
        return nil, err
    }

    u.ID = domain.UserID(userID)
    return &u, nil
}

func (r *PostgresUserRepository) Delete(ctx context.Context, id domain.UserID) error {
    _, err := r.db.ExecContext(ctx, `DELETE FROM users WHERE id = $1`, string(id))
    return err
}
```

**Input Adapter — HTTP handler:**

```go
// internal/adapters/http/user_handler.go
package http

import (
    "encoding/json"
    "errors"
    "net/http"

    "github.com/yourorg/app/internal/domain"
    "github.com/yourorg/app/internal/ports/input"
)

type UserHandler struct {
    useCase input.UserUseCase // input port-a depend edir
}

func NewUserHandler(useCase input.UserUseCase) *UserHandler {
    return &UserHandler{useCase: useCase}
}

func (h *UserHandler) CreateUser(w http.ResponseWriter, r *http.Request) {
    var body struct {
        Name  string `json:"name"`
        Email string `json:"email"`
    }

    if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
        http.Error(w, "invalid request body", http.StatusBadRequest)
        return
    }

    user, err := h.useCase.CreateUser(r.Context(), input.CreateUserRequest{
        Name:  body.Name,
        Email: body.Email,
    })

    if errors.Is(err, domain.ErrEmailTaken) {
        http.Error(w, "email already taken", http.StatusConflict)
        return
    }
    if errors.Is(err, domain.ErrInvalidEmail) {
        http.Error(w, "invalid email", http.StatusBadRequest)
        return
    }
    if err != nil {
        http.Error(w, "internal server error", http.StatusInternalServerError)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(http.StatusCreated)
    json.NewEncoder(w).Encode(user)
}

// Test üçün — domain-i mock repo ilə test etmək
type InMemoryUserRepository struct {
    users map[domain.UserID]*domain.User
}

func NewInMemoryUserRepository() *InMemoryUserRepository {
    return &InMemoryUserRepository{users: make(map[domain.UserID]*domain.User)}
}

func (r *InMemoryUserRepository) Save(_ context.Context, user *domain.User) error {
    r.users[user.ID] = user
    return nil
}

func (r *InMemoryUserRepository) FindByID(_ context.Context, id domain.UserID) (*domain.User, error) {
    if u, ok := r.users[id]; ok {
        return u, nil
    }
    return nil, domain.ErrUserNotFound
}

func (r *InMemoryUserRepository) FindByEmail(_ context.Context, email string) (*domain.User, error) {
    for _, u := range r.users {
        if u.Email == email {
            return u, nil
        }
    }
    return nil, nil
}

func (r *InMemoryUserRepository) Delete(_ context.Context, id domain.UserID) error {
    delete(r.users, id)
    return nil
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Struktur yaratmaq:**
`product-service` adlı yeni microservice üçün hexagonal struktur yaradın. `Product` domain entity, `ProductRepository` output port, `CreateProductUseCase` input port, HTTP adapter və in-memory adapter yazın.

**Tapşırıq 2 — Adapter dəyişikliyi:**
Mövcud `InMemoryUserRepository`-ni saxlayaraq PostgreSQL adapter yazın. Application service-ə toxunmadan sadəcə adapter-i dəyişin. Bu OCP + DIP-in işlədiyini göstərir.

**Tapşırıq 3 — Test:**
Application service-i real DB olmadan test edin. `InMemoryUserRepository` istifadə edərək `CreateUser`, `DeactivateUser` üçün unit test yazın.

**Tapşırıq 4 — İkinci input adapter:**
Mövcud `UserUseCase` input port-unu kullanan gRPC adapter yazın. HTTP handler-ə toxunmadan eyni business logic-i gRPC vasitəsilə expose edin.

**Tapşırıq 5 — Domain rule:**
`User` entity-ə yeni rule əlavə edin: deactivate edilmiş user-i reactivate etmək üçün admin approval lazımdır. Bu loqikanı domain-də saxlayın, HTTP handler-ə keçirməyin.

## Əlaqəli Mövzular

- `27-clean-architecture.md` — Clean Architecture ilə müqayisə (çox oxşardır)
- `28-solid-principles.md` — Hexagonal-ın əsasını SOLID prinsipləri təşkil edir
- `30-ddd-tactical.md` — Domain layer-ı DDD pattern-ləri ilə zənginləşdirmək
- `09-dependency-injection.go` — Port-ları inject etmək üçün DI container
