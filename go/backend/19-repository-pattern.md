# Repository Pattern + Service Layer (Senior)

## İcmal

Repository pattern — data access logic-i biznes logic-dən ayıran arxitektura pattern-idir. Go-da interface vasitəsilə tətbiq olunur: `UserRepository` interface müəyyən edilir, `PostgresUserRepository` onu implement edir. Test zamanı in-memory implementation istifadə olunur.

## Niyə Vacibdir

- **Testability:** Service layer-i real DB olmadan, in-memory repo ilə test etmək
- **Swap:** PostgreSQL → MySQL → MongoDB — Service layer-i dəyişmədən
- **Separation of concerns:** SQL handler-də yox, repository-də
- **Qatların ayrılması** — handler, service, repository hər biri ayrıca test oluna bilər

## Əsas Anlayışlar

### Repository Pattern Strukturu

```
Handler  →  Service  →  Repository Interface  →  DB Implementation
              ↕                ↕
           EmailSender     In-memory (test üçün)
```

**Domain (interface tərəfi):**
```go
type UserRepository interface {
    FindByID(ctx context.Context, id int) (*User, error)
    FindAll(ctx context.Context, filter UserFilter) ([]*User, error)
    Save(ctx context.Context, user *User) error
    Update(ctx context.Context, user *User) error
    Delete(ctx context.Context, id int) error
}
```

**Adapter (implementasiya tərəfi):**
```go
type postgresUserRepository struct {
    db *sql.DB
}

func NewPostgresUserRepository(db *sql.DB) UserRepository {
    return &postgresUserRepository{db: db}
}
```

**Qeyd:** Constructor `UserRepository` interface qaytarır, `*postgresUserRepository` yox — bu dependency inversion-un tətbiqidir.

### Service Layer

Service — biznes logic-i saxlayır. Database bilmir, interface vasitəsilə danışır:

```go
type UserService struct {
    repo  UserRepository   // interface
    email EmailSender      // interface
    cache CacheService     // interface
}

func (s *UserService) Register(ctx context.Context, input RegisterInput) (*User, error) {
    // 1. Validate
    // 2. Mövcudluq yoxla
    // 3. Hash password
    // 4. DB-ə yaz (repo vasitəsilə)
    // 5. Email göndər
    // 6. Return
}
```

### Filter Pattern

Repository-yə mürəkkəb sorğu göndərmək üçün:

```go
type UserFilter struct {
    Name      string
    Email     string
    IsActive  *bool
    CreatedFrom *time.Time
    CreatedTo   *time.Time
    Limit     int
    Offset    int
    OrderBy   string
}
```

## Praktik Baxış

### Real Layihədə Repository Qatı

```
internal/
├── domain/
│   └── user.go          ← User struct + UserRepository interface
├── service/
│   └── user_service.go  ← biznes logic
└── repository/
    ├── postgres/
    │   └── user.go      ← SQL implementasiya
    ├── memory/
    │   └── user.go      ← test üçün in-memory
    └── redis/
        └── user_cache.go ← cache layer
```

### Context İstifadəsi

Bütün repository method-ları `context.Context` qəbul etməlidir:

```go
FindByID(ctx context.Context, id int) (*User, error)
```

- HTTP request ləğv olsa → SQL sorğusu da ləğv olur
- Timeout — `context.WithTimeout(ctx, 5*time.Second)`
- Tracing — `ctx` trace məlumatı saxlaya bilər

### Error Wrapping

Repository xətaları wrap edilməlidir:

```go
func (r *postgresUserRepository) FindByID(ctx context.Context, id int) (*User, error) {
    ...
    if err == sql.ErrNoRows {
        return nil, domain.ErrUserNotFound // domain xətası
    }
    if err != nil {
        return nil, fmt.Errorf("UserRepository.FindByID: %w", err)
    }
    ...
}
```

Service layer domain xətasını tanıyır (`errors.Is(err, domain.ErrUserNotFound)`), SQL xətasını tanımır.

### Trade-off-lar

| Repository Pattern | Çatışmazlıqlar |
|-------------------|----------------|
| Tam test izolasiyası | Daha çox kod (interface, struct, constructor) |
| DB dəyişikliyi asan | ORM-in qüvvətli xüsusiyyətlərindən məhrum ola bilər |
| Aydın qat ayrımı | Kiçik layihə üçün over-engineering |
| Dependency inversion | In-memory mock-u sync saxlamaq lazımdır |

**Ne vaxt Repository Pattern lazım deyil:**
- Kiçik CRUD tool, prototip
- 1-2 developer-li layihə, production test yoxdur
- ORM (GORM) birbaşa handler-da — tez, amma test çətin

### Anti-pattern-lər

```go
// Anti-pattern 1: Repository-də biznes logic
func (r *postgresUserRepo) RegisterUser(name, email, password string) (*User, error) {
    // Password hash burada olmamalıdır!
    hashed := bcrypt.Hash(password)
    // Bu service-in işidir
}

// Anti-pattern 2: Service-də SQL
func (s *UserService) GetByEmail(email string) (*User, error) {
    var user User
    s.db.Query("SELECT * FROM users WHERE email = ?", email) // YANLIŞ — service SQL bilmir
}

// Anti-pattern 3: Konkret tip qaytarır
func NewUserRepo(db *sql.DB) *postgresUserRepo { // interface qaytarmalı
    return &postgresUserRepo{db: db}
}
// Düzgün:
func NewUserRepo(db *sql.DB) UserRepository { // interface
    return &postgresUserRepo{db: db}
}

// Anti-pattern 4: Test-də real DB istifadəsi
func TestCreateUser(t *testing.T) {
    db, _ := sql.Open("postgres", "postgresql://localhost/test") // yavaş, flaky
    repo := NewUserRepo(db)
    ...
}
// Düzgün: in-memory repository

// Anti-pattern 5: Context-siz method
func (r *repo) FindByID(id int) (*User, error) // context yox — cancel, timeout yox
```

## Nümunələr

### Nümunə 1: Tam İmplementasiya — UserRepository

```go
package main

import (
    "context"
    "database/sql"
    "errors"
    "fmt"
    "sync"
    "time"
)

// === DOMAIN ===

// Sentinel xətalar — errors.Is() ilə yoxlanılır
var (
    ErrUserNotFound   = errors.New("user tapılmadı")
    ErrEmailDuplicate = errors.New("email artıq mövcuddur")
)

type User struct {
    ID        int
    Name      string
    Email     string
    Active    bool
    CreatedAt time.Time
    UpdatedAt time.Time
}

type UserFilter struct {
    Email    string
    Active   *bool
    Limit    int
    Offset   int
}

// Repository interface — domain-də müəyyənlənir
type UserRepository interface {
    FindByID(ctx context.Context, id int) (*User, error)
    FindByEmail(ctx context.Context, email string) (*User, error)
    FindAll(ctx context.Context, filter UserFilter) ([]*User, error)
    Save(ctx context.Context, user *User) (*User, error)
    Update(ctx context.Context, user *User) error
    Delete(ctx context.Context, id int) error
}

// === POSTGRES IMPLEMENTASIYA ===

type postgresUserRepository struct {
    db *sql.DB
}

func NewPostgresUserRepository(db *sql.DB) UserRepository {
    return &postgresUserRepository{db: db}
}

func (r *postgresUserRepository) FindByID(ctx context.Context, id int) (*User, error) {
    query := `SELECT id, name, email, active, created_at, updated_at 
              FROM users WHERE id = $1 AND deleted_at IS NULL`

    var user User
    err := r.db.QueryRowContext(ctx, query, id).Scan(
        &user.ID, &user.Name, &user.Email,
        &user.Active, &user.CreatedAt, &user.UpdatedAt,
    )

    if errors.Is(err, sql.ErrNoRows) {
        return nil, fmt.Errorf("FindByID %d: %w", id, ErrUserNotFound)
    }
    if err != nil {
        return nil, fmt.Errorf("UserRepository.FindByID: %w", err)
    }

    return &user, nil
}

func (r *postgresUserRepository) FindByEmail(ctx context.Context, email string) (*User, error) {
    query := `SELECT id, name, email, active, created_at, updated_at 
              FROM users WHERE email = $1 AND deleted_at IS NULL`

    var user User
    err := r.db.QueryRowContext(ctx, query, email).Scan(
        &user.ID, &user.Name, &user.Email,
        &user.Active, &user.CreatedAt, &user.UpdatedAt,
    )

    if errors.Is(err, sql.ErrNoRows) {
        return nil, nil // tapılmadı — xəta yox
    }
    if err != nil {
        return nil, fmt.Errorf("UserRepository.FindByEmail: %w", err)
    }

    return &user, nil
}

func (r *postgresUserRepository) FindAll(ctx context.Context, filter UserFilter) ([]*User, error) {
    query := `SELECT id, name, email, active, created_at, updated_at 
              FROM users WHERE deleted_at IS NULL`
    args := []interface{}{}
    argIdx := 1

    if filter.Email != "" {
        query += fmt.Sprintf(" AND email ILIKE $%d", argIdx)
        args = append(args, "%"+filter.Email+"%")
        argIdx++
    }

    if filter.Active != nil {
        query += fmt.Sprintf(" AND active = $%d", argIdx)
        args = append(args, *filter.Active)
        argIdx++
    }

    query += " ORDER BY created_at DESC"

    if filter.Limit > 0 {
        query += fmt.Sprintf(" LIMIT $%d", argIdx)
        args = append(args, filter.Limit)
        argIdx++
    }
    if filter.Offset > 0 {
        query += fmt.Sprintf(" OFFSET $%d", argIdx)
        args = append(args, filter.Offset)
    }

    rows, err := r.db.QueryContext(ctx, query, args...)
    if err != nil {
        return nil, fmt.Errorf("UserRepository.FindAll: %w", err)
    }
    defer rows.Close()

    var users []*User
    for rows.Next() {
        var u User
        if err := rows.Scan(&u.ID, &u.Name, &u.Email, &u.Active, &u.CreatedAt, &u.UpdatedAt); err != nil {
            return nil, fmt.Errorf("UserRepository.FindAll scan: %w", err)
        }
        users = append(users, &u)
    }

    return users, rows.Err()
}

func (r *postgresUserRepository) Save(ctx context.Context, user *User) (*User, error) {
    query := `INSERT INTO users (name, email, active, created_at, updated_at)
              VALUES ($1, $2, $3, NOW(), NOW())
              RETURNING id, created_at, updated_at`

    err := r.db.QueryRowContext(ctx, query, user.Name, user.Email, user.Active).
        Scan(&user.ID, &user.CreatedAt, &user.UpdatedAt)
    if err != nil {
        return nil, fmt.Errorf("UserRepository.Save: %w", err)
    }

    return user, nil
}

func (r *postgresUserRepository) Update(ctx context.Context, user *User) error {
    query := `UPDATE users SET name=$1, email=$2, active=$3, updated_at=NOW()
              WHERE id=$4 AND deleted_at IS NULL`

    result, err := r.db.ExecContext(ctx, query, user.Name, user.Email, user.Active, user.ID)
    if err != nil {
        return fmt.Errorf("UserRepository.Update: %w", err)
    }

    rows, _ := result.RowsAffected()
    if rows == 0 {
        return fmt.Errorf("Update %d: %w", user.ID, ErrUserNotFound)
    }

    return nil
}

func (r *postgresUserRepository) Delete(ctx context.Context, id int) error {
    query := `UPDATE users SET deleted_at=NOW() WHERE id=$1 AND deleted_at IS NULL`
    result, err := r.db.ExecContext(ctx, query, id)
    if err != nil {
        return fmt.Errorf("UserRepository.Delete: %w", err)
    }

    rows, _ := result.RowsAffected()
    if rows == 0 {
        return fmt.Errorf("Delete %d: %w", id, ErrUserNotFound)
    }

    return nil
}

// === IN-MEMORY IMPLEMENTASIYA (test üçün) ===

type inMemoryUserRepository struct {
    mu     sync.RWMutex
    users  map[int]*User
    nextID int
}

func NewInMemoryUserRepository() UserRepository {
    return &inMemoryUserRepository{
        users:  make(map[int]*User),
        nextID: 1,
    }
}

func (r *inMemoryUserRepository) FindByID(ctx context.Context, id int) (*User, error) {
    r.mu.RLock()
    defer r.mu.RUnlock()

    user, ok := r.users[id]
    if !ok {
        return nil, fmt.Errorf("FindByID %d: %w", id, ErrUserNotFound)
    }
    copied := *user
    return &copied, nil
}

func (r *inMemoryUserRepository) FindByEmail(ctx context.Context, email string) (*User, error) {
    r.mu.RLock()
    defer r.mu.RUnlock()

    for _, u := range r.users {
        if u.Email == email {
            copied := *u
            return &copied, nil
        }
    }
    return nil, nil
}

func (r *inMemoryUserRepository) FindAll(ctx context.Context, filter UserFilter) ([]*User, error) {
    r.mu.RLock()
    defer r.mu.RUnlock()

    var result []*User
    for _, u := range r.users {
        if filter.Active != nil && u.Active != *filter.Active {
            continue
        }
        copied := *u
        result = append(result, &copied)
    }
    return result, nil
}

func (r *inMemoryUserRepository) Save(ctx context.Context, user *User) (*User, error) {
    r.mu.Lock()
    defer r.mu.Unlock()

    for _, u := range r.users {
        if u.Email == user.Email {
            return nil, ErrEmailDuplicate
        }
    }

    user.ID = r.nextID
    user.CreatedAt = time.Now()
    user.UpdatedAt = time.Now()
    r.nextID++

    copied := *user
    r.users[user.ID] = &copied
    return user, nil
}

func (r *inMemoryUserRepository) Update(ctx context.Context, user *User) error {
    r.mu.Lock()
    defer r.mu.Unlock()

    if _, ok := r.users[user.ID]; !ok {
        return fmt.Errorf("Update %d: %w", user.ID, ErrUserNotFound)
    }

    user.UpdatedAt = time.Now()
    copied := *user
    r.users[user.ID] = &copied
    return nil
}

func (r *inMemoryUserRepository) Delete(ctx context.Context, id int) error {
    r.mu.Lock()
    defer r.mu.Unlock()

    if _, ok := r.users[id]; !ok {
        return fmt.Errorf("Delete %d: %w", id, ErrUserNotFound)
    }
    delete(r.users, id)
    return nil
}
```

### Nümunə 2: UserService — Repository istifadəsi

```go
// === SERVICE LAYER ===

type RegisterInput struct {
    Name     string
    Email    string
    Password string
}

type UserService struct {
    repo  UserRepository
    email EmailSender
}

type EmailSender interface {
    SendWelcome(to, name string) error
}

func NewUserService(repo UserRepository, email EmailSender) *UserService {
    return &UserService{repo: repo, email: email}
}

func (s *UserService) Register(ctx context.Context, input RegisterInput) (*User, error) {
    // 1. Email mövcuddur?
    existing, err := s.repo.FindByEmail(ctx, input.Email)
    if err != nil {
        return nil, fmt.Errorf("email yoxlama: %w", err)
    }
    if existing != nil {
        return nil, ErrEmailDuplicate
    }

    // 2. User yarat
    user := &User{
        Name:   input.Name,
        Email:  input.Email,
        Active: true,
    }

    // 3. Saxla
    saved, err := s.repo.Save(ctx, user)
    if err != nil {
        return nil, fmt.Errorf("user saxlama: %w", err)
    }

    // 4. Email göndər (kritik deyil)
    if s.email != nil {
        if err := s.email.SendWelcome(saved.Email, saved.Name); err != nil {
            // Log et amma xəta qaytarma
            fmt.Printf("welcome email xətası: %v\n", err)
        }
    }

    return saved, nil
}

func (s *UserService) GetByID(ctx context.Context, id int) (*User, error) {
    user, err := s.repo.FindByID(ctx, id)
    if err != nil {
        if errors.Is(err, ErrUserNotFound) {
            return nil, ErrUserNotFound // domain xətasını göndər
        }
        return nil, fmt.Errorf("user alma: %w", err)
    }
    return user, nil
}

func (s *UserService) ListActive(ctx context.Context, page, limit int) ([]*User, error) {
    active := true
    return s.repo.FindAll(ctx, UserFilter{
        Active: &active,
        Limit:  limit,
        Offset: (page - 1) * limit,
    })
}

func (s *UserService) Deactivate(ctx context.Context, id int) error {
    user, err := s.repo.FindByID(ctx, id)
    if err != nil {
        return err
    }

    user.Active = false
    return s.repo.Update(ctx, user)
}
```

### Nümunə 3: Test — In-memory Repository ilə

```go
package main_test

import (
    "context"
    "testing"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

// Mock Email sender
type mockEmailSender struct {
    sent []string
}

func (m *mockEmailSender) SendWelcome(to, name string) error {
    m.sent = append(m.sent, to)
    return nil
}

func TestUserService_Register(t *testing.T) {
    ctx := context.Background()

    tests := []struct {
        name      string
        input     RegisterInput
        wantErr   bool
        errTarget error
    }{
        {
            name:  "uğurlu qeydiyyat",
            input: RegisterInput{Name: "Orkhan", Email: "orkhan@example.com"},
        },
        {
            name:      "təkrar email",
            input:     RegisterInput{Name: "Başqası", Email: "orkhan@example.com"},
            wantErr:   true,
            errTarget: ErrEmailDuplicate,
        },
    }

    // Hər test üçün təzə in-memory repo
    repo := NewInMemoryUserRepository()
    emailSender := &mockEmailSender{}
    svc := NewUserService(repo, emailSender)

    // İlk test — uğurlu
    t.Run(tests[0].name, func(t *testing.T) {
        user, err := svc.Register(ctx, tests[0].input)
        require.NoError(t, err)
        assert.Equal(t, "Orkhan", user.Name)
        assert.Greater(t, user.ID, 0)
        assert.True(t, user.Active)
        assert.Len(t, emailSender.sent, 1)
    })

    // İkinci test — duplikat
    t.Run(tests[1].name, func(t *testing.T) {
        _, err := svc.Register(ctx, tests[1].input)
        require.Error(t, err)
        assert.ErrorIs(t, err, ErrEmailDuplicate)
        assert.Len(t, emailSender.sent, 1) // email göndərilmir
    })
}

func TestUserService_GetByID_NotFound(t *testing.T) {
    repo := NewInMemoryUserRepository()
    svc := NewUserService(repo, nil)

    _, err := svc.GetByID(context.Background(), 999)
    require.Error(t, err)
    assert.ErrorIs(t, err, ErrUserNotFound)
}

func TestUserService_ListActive(t *testing.T) {
    ctx := context.Background()
    repo := NewInMemoryUserRepository()
    svc := NewUserService(repo, nil)

    // 3 user yarat, biri aktiv deyil
    svc.Register(ctx, RegisterInput{Name: "A", Email: "a@example.com"})
    svc.Register(ctx, RegisterInput{Name: "B", Email: "b@example.com"})
    svc.Register(ctx, RegisterInput{Name: "C", Email: "c@example.com"})

    // C-ni deaktiv et
    svc.Deactivate(ctx, 3)

    users, err := svc.ListActive(ctx, 1, 10)
    require.NoError(t, err)
    assert.Len(t, users, 2) // yalnız A və B
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Product Repository:**
`ProductRepository` interface + PostgreSQL implementasiyası + In-memory implementasiyası yazın. `FindByCategory`, `UpdatePrice`, `FindLowStock(threshold int)` method-larını daxil edin.

**Tapşırıq 2 — Cache Decorator:**
`CachedUserRepository` — `UserRepository`-i wrap edən, Redis-də nəticəni cache edən dekorator yazın. `FindByID` → cache miss → DB → cache set pattern.

**Tapşırıq 3 — Transaction:**
`UserRepository.Save` + `WalletRepository.Create` — eyni database transaction-da icra edin. `BeginTx`, `Commit`, `Rollback` istifadə edin.

**Tapşırıq 4 — Pagination + Total Count:**
`FindAll` method-una `total int` qaytarmağı əlavə edin. Pagination header-ları: `X-Total-Count`, `X-Page`, `X-Per-Page`.

**Tapşırıq 5 — Benchmark:**
In-memory vs PostgreSQL repository performance-ını `testing.B` ilə ölçün. 10,000 FindByID sorğusu üçün fərqi müşahidə edin.

## PHP ilə Müqayisə

Laravel-də Eloquent ORM model-i birbaşa service-də istifadə etmək adi bir yanaşmadır. Go-da isə interface məcburidir — bu fərqli testability imkanları yaradır.

```php
// PHP Laravel — Eloquent aktiv birbaşa Service-də
class UserService {
    public function getActiveUsers() {
        return User::where('active', true)->get(); // ORM birbaşa
    }
}

// Interface istifadə variantı
class UserService {
    public function __construct(private UserRepositoryInterface $repo) {}
    
    public function getActiveUsers() {
        return $this->repo->findActive();
    }
}
```

```go
// Go — interface məcburidir
type UserService struct {
    repo UserRepository // interface — başqa yol yoxdur
}

func (s *UserService) GetActiveUsers(ctx context.Context) ([]*User, error) {
    return s.repo.FindAll(ctx, UserFilter{IsActive: boolPtr(true)})
}
```

**Əsas fərqlər:**
- PHP-də Eloquent model test-də real DB-yə gedir (in-memory SQLite istifadə edirlər)
- Go-da interface ilə real DB-siz test mümkündür — daha sürətli, daha etibarlı
- Laravel `UserRepository` optional (Eloquent əvəz edir); Go-da interface məcburidir

## Əlaqəli Mövzular

- [17-interfaces](../core/17-interfaces.md) — Interface əsasları
- [05-database](05-database.md) — database/sql paketi
- [06-orm-and-sqlx](06-orm-and-sqlx.md) — ORM və sqlx
- [42-struct-advanced](../core/35-struct-advanced.md) — Struct dizaynı
- [45-functional-options](09-functional-options.md) — Repository konfiqurasiyası
- [16-mocking-and-testify](16-mocking-and-testify.md) — Mock ilə test
- [18-project-structure](18-project-structure.md) — Layihə strukturunda yerləşdirmə
- [64-dependency-injection](../advanced/09-dependency-injection.md) — DI ilə birlikdə istifadə
- [74-clean-architecture](../advanced/27-clean-architecture.md) — Clean architecture kontekstdə
