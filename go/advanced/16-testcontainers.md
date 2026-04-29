# Testcontainers: Real İnteqrasiya Testləri (Lead)

## İcmal

`testcontainers-go` — test zamanı Docker konteynerləri proqramatik olaraq başlatmaq üçün kitabxanadır. PostgreSQL, Redis, Kafka, Elasticsearch — real servislərlə test et, mock-ları unut. Production-a uyğun real servislər istifadə edilir: constraint, index, transaction davranışı real mühitdə yoxlanır.

## Niyə Vacibdir

- Mock repository ilə unit test — "kod çalışır" deyir, amma SQL səhv ola bilər
- Real PostgreSQL ilə test — constraint, index, transaction davranışı test edilir
- "Works on my machine" problemi aradan qalxır — CI/CD-də eyni konteyner
- PostgreSQL-specific feature-lar (triggers, stored proc, advisory locks) düzgün test edilir

## Əsas Anlayışlar

**Necə işləyir:**
1. Test başlayanda konteyner başladır (10-30 saniyə)
2. DB connection string alınır
3. Migration çalışdır
4. Test icra edilir
5. Test bitdikdə konteyner silinir

**`Reuse` modu:**
- Eyni konfiqurasiya → mövcud konteyneri yenidən istifadə et
- Test dəstəsi çox sürətli olur (hər test ayrı konteyner başlatmır)

**Parallel testlər:**
- Hər test öz konteynerini başladır → tam izolasiya
- Shared konteyner → əvvəlki testin datası qarışa bilər

## Praktik Baxış

**Nə vaxt testcontainers:**
- Repository layer-i test edəndə (DB sorğuları)
- Migration doğruluğunu test edəndə
- Database-specific feature-lar (triggers, stored proc, constraint)
- CI/CD pipeline-da integration test

**Nə vaxt mock:**
- Usecase unit testləri — DB-nin necə cavab verəcəyini bilmək yetərlidirsə
- Sürətli TDD dövrəsi — container başlatmaq yavaşdır
- Third-party API-lər — real çağırış bahalıdır

**Trade-off-lar:**
- Container startup: 10-30 saniyə — `TestMain` ilə bir dəfə başlat
- Docker tələb edir — CI-da Docker daemon aktiv olmalıdır
- Parallel testlər: çox container → çox yaddaş

## Nümunələr

### Nümunə 1: PostgreSQL konteyner — əsas quraşdırma

```go
package postgres_test

import (
    "context"
    "database/sql"
    "testing"

    "github.com/pressly/goose/v3"
    "github.com/testcontainers/testcontainers-go"
    "github.com/testcontainers/testcontainers-go/modules/postgres"
    "github.com/testcontainers/testcontainers-go/wait"

    _ "github.com/lib/pq"
)

// go get github.com/testcontainers/testcontainers-go
// go get github.com/testcontainers/testcontainers-go/modules/postgres

func setupPostgres(t *testing.T) *sql.DB {
    t.Helper()
    ctx := context.Background()

    container, err := postgres.RunContainer(ctx,
        testcontainers.WithImage("postgres:16-alpine"),
        postgres.WithDatabase("testdb"),
        postgres.WithUsername("testuser"),
        postgres.WithPassword("testpass"),
        testcontainers.WithWaitStrategy(
            wait.ForLog("database system is ready to accept connections").
                WithOccurrence(2),
        ),
    )
    if err != nil {
        t.Fatalf("container başladılmadı: %v", err)
    }

    t.Cleanup(func() {
        container.Terminate(ctx)
    })

    connStr, err := container.ConnectionString(ctx, "sslmode=disable")
    if err != nil {
        t.Fatalf("connection string alınmadı: %v", err)
    }

    db, err := sql.Open("postgres", connStr)
    if err != nil {
        t.Fatalf("db açılmadı: %v", err)
    }

    if err := db.PingContext(ctx); err != nil {
        t.Fatalf("db ping uğursuz: %v", err)
    }

    // Migration-ları çalışdır
    if err := runMigrations(db); err != nil {
        t.Fatalf("migration xətası: %v", err)
    }

    return db
}

func runMigrations(db *sql.DB) error {
    return goose.Up(db, "./migrations")
}
```

### Nümunə 2: TestMain ilə paylaşılan konteyner — sürətli testlər

```go
package postgres_test

import (
    "context"
    "database/sql"
    "os"
    "testing"

    "github.com/testcontainers/testcontainers-go/modules/postgres"
    "github.com/testcontainers/testcontainers-go/wait"
)

var testDB *sql.DB

func TestMain(m *testing.M) {
    ctx := context.Background()

    container, err := postgres.RunContainer(ctx,
        testcontainers.WithImage("postgres:16-alpine"),
        postgres.WithDatabase("testdb"),
        postgres.WithUsername("user"),
        postgres.WithPassword("pass"),
        testcontainers.WithWaitStrategy(
            wait.ForLog("ready to accept connections").WithOccurrence(2),
        ),
    )
    if err != nil {
        panic("postgres başladılmadı: " + err.Error())
    }

    connStr, _ := container.ConnectionString(ctx, "sslmode=disable")
    testDB, _ = sql.Open("postgres", connStr)
    runMigrations(testDB)

    // Testlər çalışır
    code := m.Run()

    container.Terminate(ctx)
    os.Exit(code)
}

// Hər test DB-ni təmizləyir
func cleanDB(t *testing.T) {
    t.Helper()
    testDB.Exec("TRUNCATE users, orders CASCADE")
}

func TestUserRepository_Create(t *testing.T) {
    cleanDB(t)

    repo := NewUserRepository(testDB)
    user := &User{Name: "Orxan", Email: "orxan@test.com"}

    err := repo.Create(context.Background(), user)
    if err != nil {
        t.Fatalf("create xətası: %v", err)
    }
    if user.ID == 0 {
        t.Error("ID 0-dır")
    }
}
```

### Nümunə 3: Redis + PostgreSQL — tam inteqrasiya

```go
package integration_test

import (
    "context"
    "testing"

    "github.com/redis/go-redis/v9"
    "github.com/testcontainers/testcontainers-go/modules/redis"
    tcpostgres "github.com/testcontainers/testcontainers-go/modules/postgres"
)

type testEnv struct {
    db    *sql.DB
    redis *redis.Client
}

func setupTestEnv(t *testing.T) *testEnv {
    t.Helper()
    ctx := context.Background()

    // PostgreSQL
    pgContainer, _ := tcpostgres.RunContainer(ctx,
        testcontainers.WithImage("postgres:16-alpine"),
        tcpostgres.WithDatabase("test"),
        tcpostgres.WithUsername("user"),
        tcpostgres.WithPassword("pass"),
        testcontainers.WithWaitStrategy(wait.ForLog("ready").WithOccurrence(2)),
    )

    // Redis
    redisContainer, _ := redis.RunContainer(ctx,
        testcontainers.WithImage("redis:7-alpine"),
    )

    t.Cleanup(func() {
        pgContainer.Terminate(ctx)
        redisContainer.Terminate(ctx)
    })

    pgConn, _ := pgContainer.ConnectionString(ctx, "sslmode=disable")
    db, _ := sql.Open("postgres", pgConn)

    redisEndpoint, _ := redisContainer.Endpoint(ctx, "")
    rdb := redis.NewClient(&redis.Options{Addr: redisEndpoint})

    return &testEnv{db: db, redis: rdb}
}

func TestUserService_GetWithCache(t *testing.T) {
    env := setupTestEnv(t)

    repo := NewUserRepository(env.db)
    cache := NewRedisCache(env.redis)
    svc := NewUserService(repo, cache)

    // DB-yə istifadəçi əlavə et
    user := &User{ID: 1, Name: "Orxan"}
    repo.Create(context.Background(), user)

    // İlk sorğu — DB-dən
    result, err := svc.GetUser(context.Background(), 1)
    if err != nil {
        t.Fatal(err)
    }
    if result.Name != "Orxan" {
        t.Errorf("gözlənilən: Orxan, alınan: %s", result.Name)
    }

    // İkinci sorğu — Redis-dən (DB-ə sorğu getməməlidir)
    result2, err := svc.GetUser(context.Background(), 1)
    if err != nil {
        t.Fatal(err)
    }
    if result2.Name != "Orxan" {
        t.Error("cache-dən düzgün oxunmadı")
    }
}
```

### Nümunə 4: Repository inteqrasiya testləri

```go
package repository_test

import (
    "context"
    "testing"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/require"
)

func TestUserRepository_FindByEmail(t *testing.T) {
    db := setupPostgres(t)
    repo := NewUserRepository(db)
    ctx := context.Background()

    t.Run("mövcud email tapılır", func(t *testing.T) {
        user := &User{Name: "Əli", Email: "ali@test.com"}
        require.NoError(t, repo.Create(ctx, user))

        found, err := repo.FindByEmail(ctx, "ali@test.com")
        require.NoError(t, err)
        assert.Equal(t, "Əli", found.Name)
    })

    t.Run("olmayan email — ErrNotFound qaytarır", func(t *testing.T) {
        _, err := repo.FindByEmail(ctx, "yoxdur@test.com")
        assert.ErrorIs(t, err, ErrUserNotFound)
    })

    t.Run("eyni email iki dəfə — constraint xətası", func(t *testing.T) {
        err1 := repo.Create(ctx, &User{Name: "A", Email: "dupe@test.com"})
        require.NoError(t, err1)

        err2 := repo.Create(ctx, &User{Name: "B", Email: "dupe@test.com"})
        assert.Error(t, err2) // UNIQUE constraint pozulur
    })
}

func TestUserRepository_Transaction(t *testing.T) {
    db := setupPostgres(t)
    repo := NewUserRepository(db)
    ctx := context.Background()

    t.Run("rollback — partial update geri qayıdır", func(t *testing.T) {
        tx, _ := db.BeginTx(ctx, nil)
        defer tx.Rollback()

        repo.CreateTx(ctx, tx, &User{Name: "Test"})
        // Rollback → DB-də olmayacaq

        count := 0
        db.QueryRow("SELECT COUNT(*) FROM users WHERE name = 'Test'").Scan(&count)
        assert.Equal(t, 0, count)
    })
}
```

### Nümunə 5: Makefile ilə inteqrasiya test komandaları

```makefile
# Unit testlər (sürətli)
.PHONY: test
test:
    go test ./internal/usecase/... -v -short

# İnteqrasiya testlər (Docker lazımdır)
.PHONY: test-integration
test-integration:
    go test ./internal/repository/... -v -timeout 120s

# Bütün testlər
.PHONY: test-all
test-all: test test-integration

# CI/CD
.PHONY: ci
ci: test-integration
```

```go
// -short flag ilə integration testləri keç
func TestExpensiveIntegration(t *testing.T) {
    if testing.Short() {
        t.Skip("integration test, -short ilə keçilir")
    }
    // testcontainers kodu...
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
`UserRepository.Create` və `FindByEmail` üçün testcontainers ilə inteqrasiya testləri yazın. UNIQUE constraint xətasını test edin.

**Tapşırıq 2:**
`TestMain` istifadə edərək bir konteyneri bütün testlər üçün paylaşın. Hər testin əvvəlində `TRUNCATE` edin. Test müddətini ölçün.

**Tapşırıq 3:**
Redis + PostgreSQL birlikdə: `UserService.GetUser` testi — ilk sorğu DB-dən, ikinci Redis-dən gəlsin. Bunu test edin.

## PHP ilə Müqayisə

Laravel-in `RefreshDatabase` trait-i hər test üçün DB-ni sıfırlayır. SQLite in-memory ilə sürətli işləyir, amma PostgreSQL-specific feature-ları test etmir.

```php
// Laravel — RefreshDatabase + SQLite (yavaş, amma asan)
class UserRepositoryTest extends TestCase
{
    use RefreshDatabase; // Hər test üçün DB sıfırlanır

    public function test_create_user(): void
    {
        $user = User::create(['name' => 'Orxan', 'email' => 'orxan@test.com']);
        $this->assertDatabaseHas('users', ['email' => 'orxan@test.com']);
    }
}
```

```go
// Go — testcontainers ilə real PostgreSQL
func TestUserRepository_Create(t *testing.T) {
    db := setupPostgres(t) // Real PostgreSQL konteyner

    repo := NewUserRepository(db)
    err := repo.Create(context.Background(), &User{Name: "Orxan", Email: "orxan@test.com"})

    require.NoError(t, err)
    // UNIQUE constraint, trigger-lər, PostgreSQL-specific davranış — hamısı test edilir
}
```

**Əsas fərqlər:**
- Laravel `RefreshDatabase`: SQLite və ya real DB — konfiqurasiyadan asılı; testcontainers: həmişə real PostgreSQL
- Laravel: migration avtomatik çalışır; Go: `goose.Up()` əl ilə çağırılır
- Laravel SQLite limiti: `JSON_ARRAYAGG`, `uuid_generate_v4()`, advisory lock kimi PostgreSQL feature-ları işləmir
- Testcontainers: Docker daemon tələb edir; Laravel: PHP prosesi daxilindədir

## Əlaqəli Mövzular

- [24-testing.md](../core/24-testing.md) — Test əsasları
- [../backend/16-mocking-and-testify.md](../backend/16-mocking-and-testify.md) — Mock ilə unit test
- [../backend/04-httptest.md](../backend/04-httptest.md) — HTTP inteqrasiya testi
- [77-database-migrations.md](../backend/22-database-migrations.md) — Migration test zamanı
- [../backend/05-database.md](../backend/05-database.md) — database/sql
