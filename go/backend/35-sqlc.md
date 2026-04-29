# sqlc — Type-safe SQL Code Generation (Middle)

## İcmal

**sqlc** — SQL sorgularından Go kodu generasiya edən alətdir. SQL yazırsın, sqlc Go struct-ları + interface-ləri yaradır. Nə ORM overhead-i, nə reflection, nə string query — compile-time type safety ilə raw SQL sürəti.

Workflow: SQL query annotasiyası → `sqlc generate` → type-safe Go funksiyaları.

## Niyə Vacibdir

- ORM-siz type-safe database layer
- Yanlış SQL compile vaxtında tutulur (generate zamanı)
- `SELECT` nəticəsi artıq `map[string]interface{}` deyil — struct
- Refactoring asanlaşır: column adı dəyişsə kod compile olmur
- Performance: GORM kimi N+1 risk yoxdur

## Əsas Anlayışlar

- **`sqlc.yaml`** — konfiqurasiya: database tipi, query faylları, output paketi
- **`-- name: QueryName :one/:many/:exec`** — query annotasiyası
- **`:one`** — tək row qaytarır (`sql.ErrNoRows` mümkündür)
- **`:many`** — çox row qaytarır (slice)
- **`:exec`** — nəticə qaytarmır (INSERT/UPDATE/DELETE)
- **`:execresult`** — `sql.Result` qaytarır (LastInsertId, RowsAffected)
- **`sqlc.arg()`** — named parameter; `sqlc.narg()` — nullable named param
- **`db.DBTX`** — generasiya olunan interface; `*sql.DB` VƏ `*sql.Tx` qəbul edir

## Praktik Baxış

**Ne vaxt sqlc:**

| Ssenari | Uyğunluq |
|---------|---------|
| Mürəkkəb SQL, performans kritik | ✓ Mükəmməl |
| Statik schema, tez-tez dəyişmir | ✓ Yaxşı |
| CRUD-heavy, minimal custom SQL | △ GORM da işlər |
| Dynamic query (çox filter) | ✗ Çətin — sqlc statik |
| Rapid prototyping | △ Generate overhead |

**Trade-off-lar:**
- Dynamic query (WHERE şərtlər dəyişkən) çətindir — ya conditional query, ya da raw SQL
- Schema dəyişdikdə generate yenidən lazımdır
- Migration tool-u ayrı lazımdır (golang-migrate ilə istifadə olunur)

## Nümunələr

### Nümunə 1: Konfiqurasiya və schema

```yaml
# sqlc.yaml
version: "2"
sql:
  - engine: "postgresql"
    queries: "db/queries/"
    schema: "db/migrations/"
    gen:
      go:
        package: "db"
        out: "internal/db"
        emit_json_tags: true
        emit_prepared_queries: false
        emit_interface: true
```

```sql
-- db/migrations/001_create_users.sql
CREATE TABLE users (
    id         SERIAL PRIMARY KEY,
    email      VARCHAR(255) UNIQUE NOT NULL,
    name       VARCHAR(100) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

CREATE TABLE posts (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id),
    title      VARCHAR(255) NOT NULL,
    body       TEXT NOT NULL,
    published  BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

### Nümunə 2: Sorğu annotasiyaları

```sql
-- db/queries/users.sql

-- name: GetUser :one
SELECT id, email, name, created_at
FROM users
WHERE id = $1 AND deleted_at IS NULL;

-- name: ListUsers :many
SELECT id, email, name, created_at
FROM users
WHERE deleted_at IS NULL
ORDER BY created_at DESC
LIMIT $1 OFFSET $2;

-- name: CreateUser :one
INSERT INTO users (email, name)
VALUES ($1, $2)
RETURNING *;

-- name: UpdateUserName :one
UPDATE users
SET name = $2
WHERE id = $1 AND deleted_at IS NULL
RETURNING *;

-- name: SoftDeleteUser :exec
UPDATE users
SET deleted_at = NOW()
WHERE id = $1;

-- name: GetUserByEmail :one
SELECT id, email, name, created_at
FROM users
WHERE email = $1 AND deleted_at IS NULL;
```

```sql
-- db/queries/posts.sql

-- name: CreatePost :one
INSERT INTO posts (user_id, title, body)
VALUES (@user_id, @title, @body)
RETURNING *;

-- name: ListPublishedPostsByUser :many
SELECT p.id, p.title, p.body, p.created_at, u.name AS author_name
FROM posts p
JOIN users u ON u.id = p.user_id
WHERE p.user_id = $1 AND p.published = TRUE
ORDER BY p.created_at DESC;
```

### Nümunə 3: Generasiya olunan kod (nümunə)

```bash
# Kod generasiya et
sqlc generate
```

```go
// internal/db/models.go (avtomatik generasiya)
type User struct {
    ID        int32          `json:"id"`
    Email     string         `json:"email"`
    Name      string         `json:"name"`
    CreatedAt time.Time      `json:"created_at"`
    DeletedAt sql.NullTime   `json:"deleted_at"`
}

// internal/db/users.sql.go (avtomatik generasiya)
const getUser = `
SELECT id, email, name, created_at FROM users WHERE id = $1 AND deleted_at IS NULL
`

func (q *Queries) GetUser(ctx context.Context, id int32) (User, error) {
    row := q.db.QueryRowContext(ctx, getUser, id)
    var i User
    err := row.Scan(&i.ID, &i.Email, &i.Name, &i.CreatedAt)
    return i, err
}

type CreateUserParams struct {
    Email string `json:"email"`
    Name  string `json:"name"`
}

func (q *Queries) CreateUser(ctx context.Context, arg CreateUserParams) (User, error) {
    // ... generasiya olunmuş kod
}
```

### Nümunə 4: Repository pattern ilə istifadə

```go
// internal/user/repository.go
package user

import (
    "context"
    "database/sql"
    "errors"
    "myapp/internal/db"
)

type Repository struct {
    queries *db.Queries
}

func NewRepository(database *sql.DB) *Repository {
    return &Repository{queries: db.New(database)}
}

func (r *Repository) FindByID(ctx context.Context, id int32) (*db.User, error) {
    user, err := r.queries.GetUser(ctx, id)
    if errors.Is(err, sql.ErrNoRows) {
        return nil, ErrNotFound
    }
    return &user, err
}

func (r *Repository) Create(ctx context.Context, email, name string) (*db.User, error) {
    user, err := r.queries.CreateUser(ctx, db.CreateUserParams{
        Email: email,
        Name:  name,
    })
    return &user, err
}

// Transaction dəstəyi — DBTX interface sayəsində
func (r *Repository) WithTx(tx *sql.Tx) *Repository {
    return &Repository{queries: db.New(tx)}
}
```

### Nümunə 5: Transaction ilə istifadə

```go
func (s *OrderService) CreateOrderWithItems(ctx context.Context, userID int32, items []Item) error {
    tx, err := s.db.BeginTx(ctx, nil)
    if err != nil {
        return err
    }
    defer tx.Rollback()

    // Generasiya olunmuş queries transaction-la işləyir
    q := db.New(tx)

    order, err := q.CreateOrder(ctx, db.CreateOrderParams{
        UserID: userID,
        Status: "pending",
    })
    if err != nil {
        return fmt.Errorf("order yaradıla bilmədi: %w", err)
    }

    for _, item := range items {
        if err := q.AddOrderItem(ctx, db.AddOrderItemParams{
            OrderID:   order.ID,
            ProductID: item.ProductID,
            Quantity:  item.Quantity,
            Price:     item.Price,
        }); err != nil {
            return fmt.Errorf("item əlavə edilə bilmədi: %w", err)
        }
    }

    return tx.Commit()
}
```

## Praktik Tapşırıqlar

1. **Todo CRUD:** `todos` cədvəli yarat, bütün CRUD sorğularını annotasiya et, generasiya et, repository yaz
2. **Join sorğusu:** User + Post join ilə `PostWithAuthor` struct generasiya et
3. **Pagination:** `LIMIT/OFFSET` parametrli sorğu + `COUNT(*)` ilə total count
4. **Transaction:** Post + PostTag-ları bir transaction-da yarat, hər hansı biri fail etsə rollback et

## PHP ilə Müqayisə

```
PHP/Laravel              →  Go sqlc
────────────────────────────────────────────
Eloquent: User::find(1)  →  q.GetUser(ctx, 1)
DB::table('u')->get()    →  q.ListUsers(ctx, ...)
$user->save()            →  q.CreateUser(ctx, params)
raw SQL + array result   →  annotated SQL + typed struct
```

Eloquent lazy loading N+1 riski var. sqlc-də query yazılır, N+1 üçün JOIN istifadə olunur — performans daha proqnozdur.

## Əlaqəli Mövzular

- [05-database](05-database.md) — database/sql əsasları; sqlc bunun üzərindədir
- [06-orm-and-sqlx](06-orm-and-sqlx.md) — GORM/sqlx ilə müqayisə
- [22-database-migrations](22-database-migrations.md) — golang-migrate ilə schema idarəsi
- [19-repository-pattern](19-repository-pattern.md) — generasiya olunan Queries-i wrap etmək
- [../core/28-context](../core/28-context.md) — hər sorğuda context lazımdır
