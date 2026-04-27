# Database Migrations (Senior)

## İcmal

Database migration — sxem dəyişikliklərini versiyonlamaq, tətbiq etmək və geri qaytarmaq sistemidir. Go-da iki əsas alət: **goose** (sadə, SQL-first) və **golang-migrate** (daha geniş driver dəstəyi).

## Niyə Vacibdir

- Manual SQL skriptləri — izlənilmir, komanda üçün sinxronizasiya çətindir
- Migration historiyası olmadan prod DB-ni development ilə sinxronlaşdırmaq mümkün deyil
- Rollback imkanı — xətalı dəyişikliyi geri qaytarmaq
- CI/CD pipeline-da avtomatik tətbiq: `goose up` server başlamazdan əvvəl işlər

## Əsas Anlayışlar

**Migration faylı:** hər dəyişiklik üçün ayrı fayl — `001_create_users.sql`, `002_add_index.sql`

**Up/Down:**
- `Up` — dəyişikliyi tətbiq et (yeni cədvəl, sütun əlavə, index)
- `Down` — dəyişikliyi geri qaytar (cədvəl sil, sütun düşür)

**Migration cədvəli:** alət öz cədvəlini (`goose_db_version` və ya `schema_migrations`) database-ə yaradır — hansı migration-ların tətbiq olunduğunu izləyir

**Goose vs golang-migrate:**
```
goose:
  + SQL və Go migration-ları dəstəklər
  + go:embed ilə binary-ə daxil etmək asan
  + daha az konfiqurasiya
  - yalnız PostgreSQL, MySQL, SQLite, CockroachDB

golang-migrate:
  + çox geniş driver siyahısı (MongoDB, Cassandra, ...)
  + GitHub Actions-da hazır action var
  - Go migration-ı yoxdur (yalnız SQL)
```

## Praktik Baxış

**Nə vaxt migration istifadə et:**
- İstənilən production database-i olan layihə
- Komanda işi — hər developer öz local DB-ni `goose up` ilə sinxronlaşdırır

**Production qaydaları:**
- Migration-ı heç vaxt silmə — yalnız yeni down migration yaz
- Böyük cədvəl üçün migration-ı `CONCURRENTLY` index ilə yaz (PostgreSQL)
- Data migration ilə sxem migration-nı ayır — ayrı fayllar

**Common mistakes:**
- Up + Down yazmamaq — rollback mümkün olmur
- Sxem dəyişikliyi ilə data dəyişikliyini bir migration-da birləşdirmək
- Production-da test edilməmiş migration tətbiq etmək

## Nümunələr

### Nümunə 1: Goose — quraşdırma və ilk migration

```bash
# Quraşdırma
go get github.com/pressly/goose/v3

# CLI tool
go install github.com/pressly/goose/v3/cmd/goose@latest

# Yeni migration yarat
goose -dir migrations create create_users sql
# migrations/20240415120000_create_users.sql faylı yaranır
```

```sql
-- migrations/20240415120000_create_users.sql

-- +goose Up
CREATE TABLE users (
    id         BIGSERIAL    PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(255) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_active ON users(active) WHERE deleted_at IS NULL;

-- +goose Down
DROP TABLE IF EXISTS users;
```

```sql
-- migrations/20240415130000_add_role_to_users.sql

-- +goose Up
ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user';
ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('admin', 'user', 'guest'));

-- +goose Down
ALTER TABLE users DROP COLUMN role;
```

### Nümunə 2: go:embed ilə binary-ə daxil etmək

```go
// migrations/migrations.go
package migrations

import "embed"

//go:embed *.sql
var FS embed.FS
```

```go
// internal/database/migrate.go
package database

import (
    "database/sql"
    "fmt"

    "github.com/pressly/goose/v3"
    "myapp/migrations"
)

func RunMigrations(db *sql.DB) error {
    goose.SetBaseFS(migrations.FS)

    if err := goose.SetDialect("postgres"); err != nil {
        return fmt.Errorf("dialect: %w", err)
    }

    if err := goose.Up(db, "."); err != nil {
        return fmt.Errorf("migrations: %w", err)
    }

    return nil
}
```

```go
// cmd/api/main.go
func main() {
    db, err := sql.Open("postgres", os.Getenv("DATABASE_URL"))
    // ...

    // Server başlamazdan əvvəl migration-ları tətbiq et
    if err := database.RunMigrations(db); err != nil {
        log.Fatal("Migration xətası:", err)
    }

    // HTTP server başlat...
}
```

### Nümunə 3: CLI əmrləri

```bash
# Bütün migration-ları tətbiq et
goose -dir migrations postgres "$DATABASE_URL" up

# Yalnız bir migration irəli
goose -dir migrations postgres "$DATABASE_URL" up-by-one

# Son migration-ı geri qaytar
goose -dir migrations postgres "$DATABASE_URL" down

# Cari status
goose -dir migrations postgres "$DATABASE_URL" status

# Konkret versiyaya get
goose -dir migrations postgres "$DATABASE_URL" goto 20240415130000

# Migration tarixçəsi
goose -dir migrations postgres "$DATABASE_URL" version
```

### Nümunə 4: Go migration faylı (data migration üçün)

```go
// migrations/20240416000000_backfill_user_roles.go
package migrations

import (
    "context"
    "database/sql"
    "fmt"

    "github.com/pressly/goose/v3"
)

func init() {
    goose.AddMigrationContext(upBackfillRoles, downBackfillRoles)
}

func upBackfillRoles(ctx context.Context, tx *sql.Tx) error {
    // Data migration — admin email-ə görə rol ver
    _, err := tx.ExecContext(ctx, `
        UPDATE users
        SET role = 'admin'
        WHERE email LIKE '%@mycompany.com'
    `)
    if err != nil {
        return fmt.Errorf("backfill admin roles: %w", err)
    }
    return nil
}

func downBackfillRoles(ctx context.Context, tx *sql.Tx) error {
    _, err := tx.ExecContext(ctx, `UPDATE users SET role = 'user'`)
    return err
}
```

### Nümunə 5: golang-migrate ilə istifadə

```bash
# Quraşdırma
go get -u github.com/golang-migrate/migrate/v4

# CLI
go install -tags 'postgres' github.com/golang-migrate/migrate/v4/cmd/migrate@latest

# Migration faylları
# 000001_create_users.up.sql
# 000001_create_users.down.sql
```

```go
package database

import (
    "database/sql"
    "errors"

    "github.com/golang-migrate/migrate/v4"
    "github.com/golang-migrate/migrate/v4/database/postgres"
    _ "github.com/golang-migrate/migrate/v4/source/file"
)

func MigrateUp(db *sql.DB) error {
    driver, err := postgres.WithInstance(db, &postgres.Config{})
    if err != nil {
        return err
    }

    m, err := migrate.NewWithDatabaseInstance(
        "file://./migrations",
        "postgres",
        driver,
    )
    if err != nil {
        return err
    }

    if err := m.Up(); err != nil && !errors.Is(err, migrate.ErrNoChange) {
        return err
    }
    return nil
}
```

### Nümunə 6: Production-da online schema change

```sql
-- Böyük cədvələ sütun əlavə etmək — LOCK vermədən
-- +goose Up

-- 1. NULL sütun əlavə et (anında tamamlanır)
ALTER TABLE orders ADD COLUMN discount_percent DECIMAL(5,2);

-- 2. Default dəyəri ayrı UPDATE ilə set et (batch-lərlə)
-- Bu migration-da deyil, ayrı script ilə:
-- UPDATE orders SET discount_percent = 0 WHERE id BETWEEN 1 AND 10000

-- 3. NOT NULL constraint sonra əlavə edilir
-- ALTER TABLE orders ALTER COLUMN discount_percent SET NOT NULL;
-- Bu da ayrı migration-da

-- +goose Down
ALTER TABLE orders DROP COLUMN discount_percent;
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
Goose ilə `users`, `products`, `orders`, `order_items` cədvəllərini yaradın. Hər cədvəl ayrı migration faylında olsun.

**Tapşırıq 2:**
`go:embed` ilə migration fayllarını binary-ə daxil edin. `main.go`-da server başlamazdan əvvəl migration tətbiq edin.

**Tapşırıq 3:**
Mövcud `users` cədvəlinə `phone` sütunu əlavə edin. `up` migration test edin, sonra `goose down` ilə geri qaytarın.

**Tapşırıq 4:**
CI/CD pipeline: GitHub Actions-da `goose up` əmrini migration addımı kimi əlavə edin. Migration xəta verərsə deploy dayanmalıdır.

## PHP ilə Müqayisə

Laravel `php artisan migrate` əmri migration-ları tətbiq edir. Go-da `goose up` eyni funksiyanı yerinə yetirir — sintaksis fərqli, konsept eyni.

```bash
# Laravel
php artisan migrate
php artisan migrate:rollback
php artisan migrate:status

# Go (goose)
goose -dir migrations postgres "$DATABASE_URL" up
goose -dir migrations postgres "$DATABASE_URL" down
goose -dir migrations postgres "$DATABASE_URL" status
```

**Əsas fərqlər:**
- Laravel migration faylları PHP-dir (`Schema::create()`); goose SQL-dir (ya da Go)
- Laravel `php artisan make:migration` skeleton yaradır; goose `goose create` SQL fayl yaradır
- `go:embed` ilə migration-ları binary-ə daxil etmək Laravel-də yoxdur

## Əlaqəli Mövzular

- [37-database.md](37-database.md) — database/sql əsasları
- [38-orm-and-sqlx.md](38-orm-and-sqlx.md) — GORM, sqlx
- [31-go-embed.md](31-go-embed.md) — go:embed
- [70-docker-and-deploy.md](70-docker-and-deploy.md) — CI/CD pipeline
