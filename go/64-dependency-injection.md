# Dependency Injection — manual DI, Wire, Fx, interface-based design (Lead)

## İcmal

Dependency Injection (DI) — bir komponentin asılı olduğu digər komponentləri xaricdən almasıdır. Go-da DI heç bir framework olmadan da mükəmməl işləyir: interface + constructor funksiya + manual wiring. Laravel-in `app()->make()` service container-indən fərqli olaraq Go-da compile-time type safety var.

Bu mövzuda manual DI, Google Wire (compile-time DI), Uber Fx (runtime DI) müqayisəsi, interface-based dizayn, testability, mock strategiyası öyrəniləcək.

## Niyə Vacibdir

- DI olmadan unit test yazmaq demək olar ki mümkün deyil
- Interface əsasında dizayn — swap implementasiya asanlığı (DB → in-memory, real logger → noop)
- Layered arxitektura: Handler → Service → Repository → DB — hər qat yalnız öz birbaşa asılılığını bilir
- Wire/Fx — böyük layihələrdə manual wiring-i idarə etmək çətindir

## Əsas Anlayışlar

### DI növləri

```
Constructor Injection  — ən geniş yayılmış Go idiomu
Interface Injection    — nadirən istifadə olunur
Property/Field         — test-lərdə bəzən; production-da çətin idarə olunur
```

### Wire vs Fx

| | Wire | Fx |
|---|---------|-----|
| Tip | Compile-time codegen | Runtime DI |
| Xəta | Kompilyasiya zamanı | Runtime |
| Öyrənmə | Az | Orta |
| Magic | Az (generated kod oxunur) | Çox |
| Uyğun | Sadə→orta layihə | Böyük, plugin-like |

### Interface minimal saxla

```go
// YANLIŞ: çox geniş interface
type UserService interface {
    Create(u User) error
    Update(u User) error
    Delete(id int) error
    FindByID(id int) (*User, error)
    FindByEmail(email string) (*User, error)
    // ... 20 metod
}

// DOĞRU: istifadə nöqtəsinə görə kiçik interface
type UserFinder interface {
    FindByID(id int) (*User, error)
}
type UserCreator interface {
    Create(u User) error
}
```

## Praktik Baxış

### Manual DI — `main.go`-da wiring

```go
// Böyük layihədə main.go-da:
db := database.Connect(cfg.DSN)
repo := postgres.NewUserRepo(db)
cache := redis.NewCache(cfg.Redis)
logger := slog.New(...)
service := user.NewService(repo, cache, logger)
handler := http.NewHandler(service)
```

### Trade-off-lar

- Manual DI: şəffaf, debugging asan, amma böyük layihədə əllə idarə çətindir
- Wire: generated kod oxunur, amma `wire.go` faylı ayrıca saxlanmalıdır
- Fx: çox "magic", stack trace-də DI çərçivəsi görünür, debug çətindir
- Container pattern (Laravel-ə bənzər): Go-da ümumiyyətlə tövsiyə edilmir — type safety itirilir

### Anti-pattern-lər

```go
// YANLIŞ: global variable (hidden dependency)
var db *sql.DB // hardcoded dependency

func GetUser(id int) User {
    return db.Query(...) // görünməz asılılıq
}

// DOĞRU: explicit injection
type UserRepo struct{ db *sql.DB }
func NewUserRepo(db *sql.DB) *UserRepo { return &UserRepo{db: db} }
func (r *UserRepo) FindByID(id int) (*User, error) { ... }
```

```go
// YANLIŞ: interface çox geniş
func NewService(db *postgres.DB) *Service { ... } // konkret tiplə

// DOĞRU: interfeyslə
func NewService(repo UserRepository, log Logger) *Service { ... }
```

## Nümunələr

### Nümunə 1: Tam layered arxitektura ilə DI

```go
package main

import (
    "context"
    "fmt"
    "log/slog"
    "os"
    "time"
)

// ==================== Domain ====================

type User struct {
    ID        int
    Name      string
    Email     string
    CreatedAt time.Time
}

// ==================== Repository Layer ====================

type UserRepository interface {
    FindByID(ctx context.Context, id int) (*User, error)
    FindByEmail(ctx context.Context, email string) (*User, error)
    Save(ctx context.Context, u *User) error
    Delete(ctx context.Context, id int) error
}

// In-memory implementasiya (test + development)
type memUserRepo struct {
    data   map[int]*User
    byEmail map[string]*User
    nextID int
}

func NewMemUserRepo() UserRepository {
    return &memUserRepo{
        data:    make(map[int]*User),
        byEmail: make(map[string]*User),
        nextID:  1,
    }
}

func (r *memUserRepo) FindByID(_ context.Context, id int) (*User, error) {
    u, ok := r.data[id]
    if !ok {
        return nil, fmt.Errorf("istifadəçi tapılmadı: %d", id)
    }
    return u, nil
}

func (r *memUserRepo) FindByEmail(_ context.Context, email string) (*User, error) {
    u, ok := r.byEmail[email]
    if !ok {
        return nil, fmt.Errorf("email tapılmadı: %s", email)
    }
    return u, nil
}

func (r *memUserRepo) Save(_ context.Context, u *User) error {
    if u.ID == 0 {
        u.ID = r.nextID
        r.nextID++
    }
    u.CreatedAt = time.Now()
    r.data[u.ID] = u
    r.byEmail[u.Email] = u
    return nil
}

func (r *memUserRepo) Delete(_ context.Context, id int) error {
    u, ok := r.data[id]
    if !ok {
        return nil
    }
    delete(r.data, id)
    delete(r.byEmail, u.Email)
    return nil
}

// ==================== Cache Layer ====================

type UserCache interface {
    Get(ctx context.Context, id int) (*User, bool)
    Set(ctx context.Context, id int, u *User, ttl time.Duration)
    Invalidate(ctx context.Context, id int)
}

type noopCache struct{}
func (n *noopCache) Get(_ context.Context, _ int) (*User, bool) { return nil, false }
func (n *noopCache) Set(_ context.Context, _ int, _ *User, _ time.Duration) {}
func (n *noopCache) Invalidate(_ context.Context, _ int) {}

// ==================== Event Publisher ====================

type EventPublisher interface {
    Publish(ctx context.Context, event string, payload interface{}) error
}

type logEventPublisher struct{ log *slog.Logger }
func (p *logEventPublisher) Publish(_ context.Context, event string, payload interface{}) error {
    p.log.Info("event", "name", event, "payload", payload)
    return nil
}

// ==================== Service Layer ====================

type CreateUserRequest struct {
    Name  string
    Email string
}

type UserServiceConfig struct {
    CacheTTL time.Duration
}

type UserService struct {
    repo      UserRepository
    cache     UserCache
    events    EventPublisher
    log       *slog.Logger
    cfg       UserServiceConfig
}

func NewUserService(
    repo UserRepository,
    cache UserCache,
    events EventPublisher,
    log *slog.Logger,
    cfg UserServiceConfig,
) *UserService {
    return &UserService{repo: repo, cache: cache, events: events, log: log, cfg: cfg}
}

func (s *UserService) CreateUser(ctx context.Context, req CreateUserRequest) (*User, error) {
    // Email unikallıq yoxlaması
    if _, err := s.repo.FindByEmail(ctx, req.Email); err == nil {
        return nil, fmt.Errorf("email artıq istifadə olunur: %s", req.Email)
    }

    u := &User{Name: req.Name, Email: req.Email}
    if err := s.repo.Save(ctx, u); err != nil {
        return nil, fmt.Errorf("saxlama: %w", err)
    }

    s.cache.Set(ctx, u.ID, u, s.cfg.CacheTTL)
    s.log.Info("istifadəçi yaradıldı", "id", u.ID, "email", u.Email)
    s.events.Publish(ctx, "user.created", map[string]interface{}{"id": u.ID})

    return u, nil
}

func (s *UserService) GetUser(ctx context.Context, id int) (*User, error) {
    // Cache yoxla
    if u, ok := s.cache.Get(ctx, id); ok {
        return u, nil
    }

    u, err := s.repo.FindByID(ctx, id)
    if err != nil {
        return nil, err
    }

    s.cache.Set(ctx, id, u, s.cfg.CacheTTL)
    return u, nil
}

func (s *UserService) DeleteUser(ctx context.Context, id int) error {
    if err := s.repo.Delete(ctx, id); err != nil {
        return err
    }
    s.cache.Invalidate(ctx, id)
    s.events.Publish(ctx, "user.deleted", map[string]interface{}{"id": id})
    return nil
}

// ==================== Wiring (main.go) ====================

func wireApp() *UserService {
    logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))

    repo := NewMemUserRepo()
    cache := &noopCache{}
    events := &logEventPublisher{log: logger}

    return NewUserService(repo, cache, events, logger, UserServiceConfig{
        CacheTTL: 5 * time.Minute,
    })
}

func main() {
    ctx := context.Background()
    svc := wireApp()

    // İstifadəçi yarat
    u, err := svc.CreateUser(ctx, CreateUserRequest{Name: "Orkhan", Email: "orkhan@example.com"})
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("Yaradıldı: %+v\n", u)

    // Tapıldı
    found, _ := svc.GetUser(ctx, u.ID)
    fmt.Printf("Tapıldı: %+v\n", found)

    // Duplicate email
    _, err = svc.CreateUser(ctx, CreateUserRequest{Name: "Başqası", Email: "orkhan@example.com"})
    fmt.Println("Gözlənilən xəta:", err)
}
```

### Nümunə 2: Wire ilə code-generated DI

```go
// wire.go (build tag ilə kompilyasiyadan xaric edilir)
//go:build wireinject

package main

import "github.com/google/wire"

// Wire dependency qrafını anlayır və wire_gen.go yaradır

var repoSet = wire.NewSet(
    NewMemUserRepo,
    wire.Bind(new(UserRepository), new(*memUserRepo)),
)

var cacheSet = wire.NewSet(
    provideNoopCache,
    wire.Bind(new(UserCache), new(*noopCache)),
)

func provideNoopCache() *noopCache { return &noopCache{} }

func provideLogger() *slog.Logger {
    return slog.New(slog.NewJSONHandler(os.Stdout, nil))
}

func provideConfig() UserServiceConfig {
    return UserServiceConfig{CacheTTL: 5 * time.Minute}
}

func InitializeUserService() *UserService {
    wire.Build(
        repoSet,
        cacheSet,
        provideLogger,
        provideEventPublisher,
        provideConfig,
        NewUserService,
    )
    return nil // Wire əvəz edir
}

// --- wire_gen.go (avtomatik yaranır) ---
// func InitializeUserService() *UserService {
//     memUserRepo := NewMemUserRepo()
//     noopCache := provideNoopCache()
//     logger := provideLogger()
//     eventPublisher := provideEventPublisher(logger)
//     config := provideConfig()
//     return NewUserService(memUserRepo, noopCache, eventPublisher, logger, config)
// }
```

### Nümunə 3: Mock ilə unit test

```go
package main

import (
    "context"
    "errors"
    "testing"
    "log/slog"
    "io"
)

// Mock Repository
type mockUserRepo struct {
    users     map[int]*User
    saveErr   error
    findErr   error
    callCount map[string]int
}

func newMockRepo() *mockUserRepo {
    return &mockUserRepo{
        users:     make(map[int]*User),
        callCount: make(map[string]int),
    }
}

func (m *mockUserRepo) FindByID(_ context.Context, id int) (*User, error) {
    m.callCount["FindByID"]++
    if m.findErr != nil {
        return nil, m.findErr
    }
    u, ok := m.users[id]
    if !ok {
        return nil, errors.New("tapılmadı")
    }
    return u, nil
}

func (m *mockUserRepo) FindByEmail(_ context.Context, email string) (*User, error) {
    m.callCount["FindByEmail"]++
    for _, u := range m.users {
        if u.Email == email {
            return u, nil
        }
    }
    return nil, errors.New("tapılmadı")
}

func (m *mockUserRepo) Save(_ context.Context, u *User) error {
    m.callCount["Save"]++
    if m.saveErr != nil {
        return m.saveErr
    }
    u.ID = len(m.users) + 1
    m.users[u.ID] = u
    return nil
}

func (m *mockUserRepo) Delete(_ context.Context, id int) error {
    m.callCount["Delete"]++
    delete(m.users, id)
    return nil
}

// Mock Event Publisher
type mockEventPublisher struct {
    events []string
}

func (m *mockEventPublisher) Publish(_ context.Context, event string, _ interface{}) error {
    m.events = append(m.events, event)
    return nil
}

// Helper — test üçün service yarat
func newTestService(repo UserRepository) (*UserService, *mockEventPublisher) {
    events := &mockEventPublisher{}
    log := slog.New(slog.NewTextHandler(io.Discard, nil))
    svc := NewUserService(repo, &noopCache{}, events, log, UserServiceConfig{
        CacheTTL: 0, // test-də cache yox
    })
    return svc, events
}

func TestCreateUser_Success(t *testing.T) {
    repo := newMockRepo()
    svc, events := newTestService(repo)

    u, err := svc.CreateUser(context.Background(), CreateUserRequest{
        Name:  "Test User",
        Email: "test@example.com",
    })

    if err != nil {
        t.Fatalf("gözlənilməz xəta: %v", err)
    }
    if u.ID == 0 {
        t.Error("ID 0 olmamalıdır")
    }
    if len(events.events) != 1 || events.events[0] != "user.created" {
        t.Errorf("event gözlənilir, alındı: %v", events.events)
    }
    if repo.callCount["Save"] != 1 {
        t.Errorf("Save 1 dəfə çağırılmalı idi")
    }
}

func TestCreateUser_DuplicateEmail(t *testing.T) {
    repo := newMockRepo()
    repo.users[1] = &User{ID: 1, Email: "exist@example.com"}
    svc, _ := newTestService(repo)

    _, err := svc.CreateUser(context.Background(), CreateUserRequest{
        Name:  "Başqası",
        Email: "exist@example.com",
    })

    if err == nil {
        t.Error("xəta gözlənilirdi")
    }
}

func TestCreateUser_SaveError(t *testing.T) {
    repo := newMockRepo()
    repo.saveErr = errors.New("db bağlantısı kəsildi")
    svc, _ := newTestService(repo)

    _, err := svc.CreateUser(context.Background(), CreateUserRequest{
        Name:  "User",
        Email: "u@example.com",
    })

    if err == nil {
        t.Error("xəta gözlənilirdi")
    }
}
```

### Nümunə 4: Uber Fx ilə DI (böyük layihə)

```go
package main

import (
    "context"
    "fmt"
    "net/http"

    "go.uber.org/fx"
)

// Fx — runtime DI container, lifecycle management

func NewHTTPServer(lc fx.Lifecycle, handler http.Handler) *http.Server {
    srv := &http.Server{Addr: ":8080", Handler: handler}

    lc.Append(fx.Hook{
        OnStart: func(ctx context.Context) error {
            go srv.ListenAndServe()
            fmt.Println("HTTP server başladı")
            return nil
        },
        OnStop: func(ctx context.Context) error {
            return srv.Shutdown(ctx)
        },
    })

    return srv
}

func NewRouter(svc *UserService) http.Handler {
    mux := http.NewServeMux()
    mux.HandleFunc("/users", func(w http.ResponseWriter, r *http.Request) {
        fmt.Fprintln(w, "Users endpoint")
    })
    return mux
}

func main() {
    app := fx.New(
        fx.Provide(
            NewMemUserRepo,
            func() UserCache { return &noopCache{} },
            func() EventPublisher { return &logEventPublisher{} },
            func() UserServiceConfig { return UserServiceConfig{} },
            NewUserService,
            NewRouter,
            NewHTTPServer,
        ),
        fx.Invoke(func(*http.Server) {}), // server-i başlat
    )

    app.Run()
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Postgres repo:**
`UserRepository` interfeysi üçün `postgres.UserRepo` implementasiyası yazın. `pgx/v5` işlədin. Unit test-lər `mockUserRepo` ilə keçsin.

**Tapşırıq 2 — Wire:**
Mövcud layihənizə `wire` əlavə edin. `wire.go` ilə `InitializeApp() *http.Server` funksiyası yazın. `wire` komandası ilə `wire_gen.go` yaradın.

**Tapşırıq 3 — Testcontainers:**
`TestMain` ilə PostgreSQL container başladın. `UserRepository`-nin real DB ilə integration test-lərini yazın.

**Tapşırıq 4 — Interface segregation:**
`UserService` üçün HTTP handler yazın. Handler `UserCreator` və `UserFinder` interfeyslərindən asılı olsun — tam `UserService`-dən deyil.

## Əlaqəli Mövzular

- [17-interfaces](17-interfaces.md) — interface əsasları
- [55-repository-pattern](55-repository-pattern.md) — repository pattern
- [59-design-patterns](59-design-patterns.md) — factory, observer
- [52-mocking-and-testify](52-mocking-and-testify.md) — mock yaratma
- [74-clean-architecture](74-clean-architecture.md) — clean architecture
