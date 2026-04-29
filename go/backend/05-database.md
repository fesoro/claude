# Database — database/sql (Senior)

## İcmal

Go-da `database/sql` paketi bütün relational database-lər üçün universal interface-dir. Driver-lər (PostgreSQL, MySQL, SQLite) bu interface-i implement edir. Əsas konseptlər: `sql.DB` (connection pool), `sql.Rows` (nəticə seti), `sql.Tx` (transaction), `sql.Stmt` (prepared statement) və NULL dəyərlərin idarəsi.

## Niyə Vacibdir

- Go web servislərin böyük əksəriyyəti relational DB ilə işləyir
- `database/sql` düzgün istifadə edilməzsə — connection leak, SQL injection, N+1 problemlər baş verir
- Connection pool parametrləri yanlış seçilərsə production-da port exhaustion olur
- `sql.Tx` və `defer Rollback()` pattern-ini bilmək transactional bütövlüyü təmin edir
- NULL dəyərlər Go-da xüsusi davranış tələb edir — `sql.NullString` və s.

## Əsas Anlayışlar

**`sql.Open`** — connection pool yaradır, amma faktiki bağlantı açmır. `db.Ping()` ilə yoxlamaq lazımdır.

**`sql.DB`** — thread-safe connection pool. Bir dəfə yaradılır, bütün aplikasiya boyunca istifadə olunur.

**`db.Query`** — çoxsaylı sətir qaytaran sorğular (`SELECT`).

**`db.QueryRow`** — tək sətir. `sql.ErrNoRows` xərasini xüsusi yoxla.

**`db.Exec`** — nəticə qaytarmayan sorğular (`INSERT`, `UPDATE`, `DELETE`).

**`rows.Scan`** — hər sütun dəyərini pointer-lərə oxuyur.

**`sql.Tx`** — transaction. Commit yoxsa Rollback mütləq çağırılmalıdır.

**Placeholder sintaksisi:**
- PostgreSQL: `$1`, `$2`, `$3`
- MySQL: `?`, `?`, `?`
- SQLite: `?` yaxud `$1`

**NULL dəyərləri üçün tiplər:**

| Go tipi | SQL NULL dəyəri |
|---|---|
| `sql.NullString` | VARCHAR NULL |
| `sql.NullInt64` | INT NULL |
| `sql.NullFloat64` | FLOAT NULL |
| `sql.NullBool` | BOOL NULL |
| `sql.NullTime` | TIMESTAMP NULL |

## Praktik Baxış

**Connection Pool tövsiyələri:**

```go
db.SetMaxOpenConns(25)           // maksimum açıq bağlantı
db.SetMaxIdleConns(10)           // bos bağlantı pool-da gözlər
db.SetConnMaxLifetime(5 * time.Minute) // bağlantı maksimum ömrü
db.SetConnMaxIdleTime(2 * time.Minute) // idle bağlantı max vaxtı
```

Ümumi tövsiyə: `MaxOpenConns = CPU * 4` başlanğıc nöqtəsi, sonra load test ilə tənzimləmək.

**Trade-off-lar:**

- `QueryRow` + `ErrNoRows` vs `Query` + `rows.Next()` — tək sətir üçün `QueryRow` daha yığcam
- Prepared statement (`db.Prepare`) vs birbaşa `db.Query` — eyni sorğu tez-tez icra olunursa prepared daha sürətli, amma connection-a bağlıdır
- `Exec` vs `QueryRow` — INSERT RETURNING id üçün `QueryRow` + `Scan` istifadə et

**Anti-pattern-lər:**

- `defer rows.Close()` unutmaq — connection buraxılmır, pool tükənir
- String concatenation ilə SQL: `"WHERE id = " + id` — SQL injection
- `rows.Err()` yoxlamamaq — partial oxuma xətası gizlənir
- Transaction-da `db.Query` deyil `tx.Query` unutmaq — transaction-dan kənarda icra olunur
- `db.Open` hər sorğuda çağırmaq — connection pool işləmir

**Xəta idarəsi:**

```go
// Tək sətir tapılmadıqda
if err == sql.ErrNoRows {
    // bu xəta deyil — sadəcə boş nəticədir
}

// rows döngüsündən sonra
if err := rows.Err(); err != nil {
    // partial read zamanı baş vermiş xəta
}
```

## Nümunələr

### Nümunə 1: Bağlantı + Pool Konfiqurasiyası

```go
package main

import (
    "database/sql"
    "fmt"
    "log"
    "time"

    _ "github.com/lib/pq" // PostgreSQL driver — side-effect import
)

func newDB(dsn string) (*sql.DB, error) {
    db, err := sql.Open("postgres", dsn)
    if err != nil {
        return nil, fmt.Errorf("DB açma xətası: %w", err)
    }

    // Production pool parametrləri
    db.SetMaxOpenConns(25)
    db.SetMaxIdleConns(10)
    db.SetConnMaxLifetime(5 * time.Minute)
    db.SetConnMaxIdleTime(2 * time.Minute)

    // Bağlantını yoxla
    if err := db.Ping(); err != nil {
        return nil, fmt.Errorf("DB ping xətası: %w", err)
    }

    return db, nil
}

func main() {
    dsn := "host=localhost port=5432 user=postgres password=1234 dbname=testdb sslmode=disable"
    db, err := newDB(dsn)
    if err != nil {
        log.Fatal(err)
    }
    defer db.Close()

    fmt.Println("DB bağlantısı uğurludur")
}
```

### Nümunə 2: INSERT + SELECT One

```go
package main

import (
    "database/sql"
    "fmt"
    "log"
)

type User struct {
    ID    int
    Ad    string
    Email string
    Yas   int
}

// INSERT — id geri al
func createUser(db *sql.DB, u User) (int, error) {
    var id int
    err := db.QueryRow(
        `INSERT INTO users (ad, email, yas) VALUES ($1, $2, $3) RETURNING id`,
        u.Ad, u.Email, u.Yas,
    ).Scan(&id)
    if err != nil {
        return 0, fmt.Errorf("user yaratma: %w", err)
    }
    return id, nil
}

// SELECT ONE — id ilə
func getUserByID(db *sql.DB, id int) (*User, error) {
    u := &User{}
    err := db.QueryRow(
        `SELECT id, ad, email, yas FROM users WHERE id = $1`, id,
    ).Scan(&u.ID, &u.Ad, &u.Email, &u.Yas)

    if err == sql.ErrNoRows {
        return nil, nil // tapılmadı — xəta deyil
    }
    if err != nil {
        return nil, fmt.Errorf("user oxuma: %w", err)
    }
    return u, nil
}

// SELECT MANY — şərt ilə
func getUsersByAge(db *sql.DB, minAge int) ([]User, error) {
    rows, err := db.Query(
        `SELECT id, ad, email, yas FROM users WHERE yas > $1 ORDER BY ad`,
        minAge,
    )
    if err != nil {
        return nil, fmt.Errorf("query: %w", err)
    }
    defer rows.Close() // MÜTLƏQ — unutsaq connection leak

    var users []User
    for rows.Next() {
        var u User
        if err := rows.Scan(&u.ID, &u.Ad, &u.Email, &u.Yas); err != nil {
            return nil, fmt.Errorf("scan: %w", err)
        }
        users = append(users, u)
    }

    // döngüdən sonra xəta yoxla
    if err := rows.Err(); err != nil {
        return nil, fmt.Errorf("rows: %w", err)
    }

    return users, nil
}
```

### Nümunə 3: UPDATE + DELETE

```go
// UPDATE — təsir edən sətir sayını al
func updateUserAge(db *sql.DB, id, newAge int) (int64, error) {
    result, err := db.Exec(
        `UPDATE users SET yas = $1, updated_at = NOW() WHERE id = $2`,
        newAge, id,
    )
    if err != nil {
        return 0, fmt.Errorf("update: %w", err)
    }
    return result.RowsAffected()
}

// DELETE
func deleteUser(db *sql.DB, id int) error {
    result, err := db.Exec(`DELETE FROM users WHERE id = $1`, id)
    if err != nil {
        return fmt.Errorf("delete: %w", err)
    }
    affected, _ := result.RowsAffected()
    if affected == 0 {
        return fmt.Errorf("user tapılmadı: %d", id)
    }
    return nil
}
```

### Nümunə 4: Transaction — Doğru Pattern

```go
package main

import (
    "context"
    "database/sql"
    "fmt"
)

// Pul köçürməsi — atomic olmalıdır
func transfer(ctx context.Context, db *sql.DB, fromID, toID int, amount float64) error {
    tx, err := db.BeginTx(ctx, nil)
    if err != nil {
        return fmt.Errorf("tx başlatma: %w", err)
    }
    // MÜTLƏQ defer Rollback — commit olunmuş tx-da Rollback no-op-dur
    defer tx.Rollback()

    // Mənbə hesabdan çıx
    result, err := tx.ExecContext(ctx,
        `UPDATE accounts SET balance = balance - $1 WHERE id = $2 AND balance >= $1`,
        amount, fromID,
    )
    if err != nil {
        return fmt.Errorf("debit: %w", err)
    }
    affected, _ := result.RowsAffected()
    if affected == 0 {
        return fmt.Errorf("kifayətsiz balans və ya hesab tapılmadı")
    }

    // Hədəf hesaba əlavə et
    _, err = tx.ExecContext(ctx,
        `UPDATE accounts SET balance = balance + $1 WHERE id = $2`,
        amount, toID,
    )
    if err != nil {
        return fmt.Errorf("credit: %w", err)
    }

    // Audit log
    _, err = tx.ExecContext(ctx,
        `INSERT INTO transfers (from_id, to_id, amount, created_at) VALUES ($1, $2, $3, NOW())`,
        fromID, toID, amount,
    )
    if err != nil {
        return fmt.Errorf("audit log: %w", err)
    }

    // Hamısı uğurlu → commit
    if err := tx.Commit(); err != nil {
        return fmt.Errorf("commit: %w", err)
    }
    return nil
}
```

### Nümunə 5: NULL Dəyərləri

```go
package main

import (
    "database/sql"
    "fmt"
)

type Profile struct {
    ID       int
    UserID   int
    Bio      sql.NullString  // nullable VARCHAR
    AvatarURL sql.NullString
    Age      sql.NullInt64   // nullable INT
}

func getProfile(db *sql.DB, userID int) (*Profile, error) {
    p := &Profile{}
    err := db.QueryRow(
        `SELECT id, user_id, bio, avatar_url, age FROM profiles WHERE user_id = $1`,
        userID,
    ).Scan(&p.ID, &p.UserID, &p.Bio, &p.AvatarURL, &p.Age)

    if err == sql.ErrNoRows {
        return nil, nil
    }
    if err != nil {
        return nil, err
    }

    // NullString istifadəsi
    if p.Bio.Valid {
        fmt.Println("Bio:", p.Bio.String)
    } else {
        fmt.Println("Bio: boşdur (NULL)")
    }

    return p, nil
}

// INSERT NULL dəyər
func createProfile(db *sql.DB, userID int, bio string) error {
    var bioNull sql.NullString
    if bio != "" {
        bioNull = sql.NullString{String: bio, Valid: true}
    }
    // Valid false isə NULL yazılır

    _, err := db.Exec(
        `INSERT INTO profiles (user_id, bio) VALUES ($1, $2)`,
        userID, bioNull,
    )
    return err
}
```

### Nümunə 6: Prepared Statement

```go
package main

import (
    "database/sql"
    "fmt"
)

type UserRepo struct {
    db       *sql.DB
    getStmt  *sql.Stmt
    listStmt *sql.Stmt
}

func NewUserRepo(db *sql.DB) (*UserRepo, error) {
    getStmt, err := db.Prepare(`SELECT id, ad, email FROM users WHERE id = $1`)
    if err != nil {
        return nil, fmt.Errorf("prepare get: %w", err)
    }

    listStmt, err := db.Prepare(`SELECT id, ad, email FROM users WHERE yas > $1 ORDER BY ad LIMIT $2`)
    if err != nil {
        getStmt.Close()
        return nil, fmt.Errorf("prepare list: %w", err)
    }

    return &UserRepo{
        db:       db,
        getStmt:  getStmt,
        listStmt: listStmt,
    }, nil
}

// Close — bütün statement-ları bağla
func (r *UserRepo) Close() {
    r.getStmt.Close()
    r.listStmt.Close()
}

func (r *UserRepo) Get(id int) (*User, error) {
    u := &User{}
    err := r.getStmt.QueryRow(id).Scan(&u.ID, &u.Ad, &u.Email)
    if err == sql.ErrNoRows {
        return nil, nil
    }
    return u, err
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — User Repository**

PostgreSQL-də `users` cədvəli üçün repository yaz:
- `Create(ctx, user) (id int, err error)`
- `GetByID(ctx, id) (*User, error)` — tapılmasa nil, nil
- `GetByEmail(ctx, email) (*User, error)`
- `List(ctx, limit, offset int) ([]User, error)`
- `Update(ctx, id, fields) error`
- `Delete(ctx, id) error`

**Tapşırıq 2 — Çoxlu Cədvəl Transaction**

E-commerce sifarişi: `orders` + `order_items` + `inventory` cədvəllərinə atomic yazma. `inventory`-dən stok azalt, `orders` yarat, `order_items` əlavə et — biri alınmasa hamısı rollback.

**Tapşırıq 3 — Connection Pool Monitoring**

`db.Stats()` ilə connection pool statistikasını hər 10 saniyədə log-a yaz:
- `OpenConnections`, `InUse`, `Idle`, `WaitCount`, `WaitDuration`

**Tapşırıq 4 — Batch Insert**

1000 istifadəçini bir transaction-da `VALUES ($1,$2), ($3,$4)...` formatında insert et. Performansı tək-tək insert ilə müqayisə et.

## PHP ilə Müqayisə

PHP/Laravel-də PDO bu rolu oynayırdı, Eloquent isə üstündə ORM idi. Go-da `database/sql` PDO-nun analoqu, `sqlx` və `GORM` isə üstündəki qatlardır.

## Əlaqəli Mövzular

- [06-orm-and-sqlx](06-orm-and-sqlx.md) — GORM və sqlx ilə yüksək səviyyəli abstraction
- [28-context](../core/28-context.md) — Context ilə query timeout
- [07-environment-and-config](07-environment-and-config.md) — DB DSN-ni environment-dən almaq
- [18-project-structure](18-project-structure.md) — Repository pattern
- [19-repository-pattern](19-repository-pattern.md) — Repository pattern implementation
- [18-error-handling](../core/18-error-handling.md) — DB xətalarını wrap etmək
