# ORM and sqlx (Senior)

## İcmal

Go-da iki populyar yüksək səviyyəli database kitabxanası var: `sqlx` — standart `database/sql`-un genişləndirilmiş versiyası, SQL özün yazırsan amma struct mapping avtomatikdir; `GORM` — tam ORM, SQL yazmadan CRUD əməliyyatları mümkündür. İkisinin arasındakı seçim layihənin tələbindən asılıdır.

## Niyə Vacibdir

- Raw `database/sql` ilə `rows.Scan(&u.ID, &u.Ad, &u.Email, ...)` — sütun sayı artdıqca çox yorucu olur
- `sqlx` struct tag-lərinə görə avtomatik scan edir — kod daha oxunaqlı, daha az xəta
- GORM association-ları (HasMany, BelongsTo) ORM olmadan əl ilə yazmaq çox kod tələb edir
- N+1 problem hər iki kitabxanada baş verə bilər — `Preload` vs `JOIN` seçimi kritikdir
- Migration idarəsi: `AutoMigrate` vs `golang-migrate` — komanda mühitində fərqli tövsiyə

## Əsas Anlayışlar

**sqlx əsas funksiyaları:**

- `db.Get(&u, query, args...)` — tək sətiri struct-a oxu
- `db.Select(&users, query, args...)` — bir neçə sətiri slice-a oxu
- `db.NamedExec(query, struct/map)` — named parametrlər ilə sorğu
- `db.In(query, slice)` — WHERE IN üçün helper
- `db.Rebind(query)` — placeholder-ləri driver üçün uyğunlaşdır

**GORM əsas konseptlər:**

- `gorm.Model` — `ID`, `CreatedAt`, `UpdatedAt`, `DeletedAt` əlavə edir (soft delete)
- `AutoMigrate` — struct-a görə cədvəl yarat/yenilə
- `Preload` — associated data-nı ayrıca sorğu ilə yüklə (N+1 riski)
- `Joins` — JOIN ilə birlikdə yüklə (daha effektiv)
- `hooks` — `BeforeCreate`, `AfterCreate`, `BeforeSave`...
- `Scopes` — yenidən istifadə olunan sorğu parçaları

**N+1 problem:**

```go
// N+1 — hər user üçün ayrıca sorğu
for _, u := range users {
    db.Find(&u.Posts) // N sorğu!
}

// Düzgün — Preload ilə 2 sorğu
db.Preload("Posts").Find(&users)

// Daha yaxşı — JOIN ilə 1 sorğu
db.Joins("Posts").Find(&users)
```

## Praktik Baxış

**Ne vaxt sqlx seçmək:**

- SQL biliyini istifadə etmək istəyirsən
- Mürəkkəb JOIN-lar, window function-lar, CTE-lər lazımdır
- Performance kritikdir — GORM-un yaradan SQL-i həmişə optimal deyil
- Köhnə database schema-sına uyğunlaşmaq lazımdır

**Ne vaxt GORM seçmək:**

- Sürətli prototip, admin panel
- Sadə CRUD üstünlük təşkil edir
- Association-lar (HasMany, ManyToMany) aktiv istifadə olunur
- Soft delete, hooks, callbacks lazımdır
- Komanda SQL yazmağa alışmayıbsa

**Anti-pattern-lər:**

- GORM `Updates(struct{})` — sıfır dəyərlər (0, "", false) yenilənmir, map istifadə et
- `AutoMigrate` production-da — sütun silmir, tip dəyişmir, `golang-migrate` istifadə et
- `db.Raw` + format string — SQL injection
- GORM-da `Find` əvzinə `Where("id = ?", id).First` — `First` ORDER BY id əlavə edir

**Trade-off-lar:**

| | sqlx | GORM |
|---|---|---|
| SQL kontrolü | Tam | Məhdud |
| Boilerplate | Az (Get/Select) | Çox az |
| Performance | Yüksək | Orta (tunable) |
| Association | Əl ilə JOIN | Preload/Joins |
| Migration | golang-migrate | AutoMigrate |
| Debug | SQL aydın | GORM logunu aç |

## Nümunələr

### Nümunə 1: sqlx — Bağlantı + Əsas Əməliyyatlar

```go
package main

import (
    "context"
    "fmt"
    "log"

    "github.com/jmoiron/sqlx"
    _ "github.com/lib/pq"
)

// Struct tag-lar `db:` ilə — sütun adlarına uyğun
type User struct {
    ID    int    `db:"id"`
    Ad    string `db:"ad"`
    Email string `db:"email"`
    Yas   int    `db:"yas"`
}

func main() {
    db, err := sqlx.Connect("postgres",
        "host=localhost user=postgres password=sifre dbname=testdb sslmode=disable")
    if err != nil {
        log.Fatal(err)
    }
    defer db.Close()

    // GET — tək sətir struct-a
    var u User
    err = db.Get(&u, "SELECT * FROM users WHERE id = $1", 1)
    if err != nil {
        log.Println("tapılmadı:", err)
    }
    fmt.Printf("User: %+v\n", u)

    // SELECT — çoxlu sətir slice-a
    var users []User
    err = db.Select(&users, "SELECT * FROM users WHERE yas > $1 ORDER BY ad", 20)
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println("Users:", len(users))

    // NamedExec — struct ilə INSERT
    _, err = db.NamedExec(
        `INSERT INTO users (ad, email, yas) VALUES (:ad, :email, :yas)`,
        &User{Ad: "Orkhan", Email: "orkhan@test.az", Yas: 28},
    )
    if err != nil {
        log.Fatal(err)
    }
}
```

### Nümunə 2: sqlx — IN + Transaction

```go
package main

import (
    "fmt"

    "github.com/jmoiron/sqlx"
)

// WHERE IN — slice ilə
func getUsersByIDs(db *sqlx.DB, ids []int) ([]User, error) {
    query, args, err := sqlx.In("SELECT * FROM users WHERE id IN (?)", ids)
    if err != nil {
        return nil, err
    }

    // Placeholder-ləri DB driver-ə uyğun çevir ($1, $2... vs ?)
    query = db.Rebind(query)

    var users []User
    return users, db.Select(&users, query, args...)
}

// sqlx Transaction
func transferWithSQLX(db *sqlx.DB, fromID, toID int, amount float64) error {
    tx, err := db.Beginx()
    if err != nil {
        return err
    }
    defer tx.Rollback()

    _, err = tx.Exec(
        `UPDATE accounts SET balance = balance - $1 WHERE id = $2`,
        amount, fromID,
    )
    if err != nil {
        return fmt.Errorf("debit: %w", err)
    }

    _, err = tx.Exec(
        `UPDATE accounts SET balance = balance + $1 WHERE id = $2`,
        amount, toID,
    )
    if err != nil {
        return fmt.Errorf("credit: %w", err)
    }

    return tx.Commit()
}
```

### Nümunə 3: GORM — Model + AutoMigrate

```go
package main

import (
    "time"

    "gorm.io/driver/postgres"
    "gorm.io/gorm"
    "gorm.io/gorm/logger"
)

type User struct {
    ID        uint      `gorm:"primaryKey"               json:"id"`
    Ad        string    `gorm:"size:100;not null"        json:"ad"`
    Email     string    `gorm:"uniqueIndex;size:255"     json:"email"`
    Yas       int       `gorm:"default:0"               json:"yas"`
    Aktiv     bool      `gorm:"default:true"            json:"aktiv"`
    Posts     []Post    `gorm:"foreignKey:UserID"       json:"posts,omitempty"`
    CreatedAt time.Time `json:"created_at"`
    UpdatedAt time.Time `json:"updated_at"`
    DeletedAt gorm.DeletedAt `gorm:"index"` // soft delete
}

type Post struct {
    ID     uint   `gorm:"primaryKey"`
    Baslik string `gorm:"size:200;not null"`
    Metn   string `gorm:"type:text"`
    UserID uint
    User   User   `gorm:"foreignKey:UserID"`
}

func newGORMDB(dsn string) (*gorm.DB, error) {
    return gorm.Open(postgres.Open(dsn), &gorm.Config{
        Logger: logger.Default.LogMode(logger.Info), // SQL-ləri log-a yaz
    })
}

func migrate(db *gorm.DB) error {
    return db.AutoMigrate(&User{}, &Post{})
    // DIQQƏT: production-da golang-migrate istifadə et
}
```

### Nümunə 4: GORM — CRUD

```go
package main

import (
    "errors"

    "gorm.io/gorm"
)

type UserRepo struct {
    db *gorm.DB
}

func (r *UserRepo) Create(u *User) error {
    return r.db.Create(u).Error
    // u.ID, u.CreatedAt avtomatik doldurulur
}

func (r *UserRepo) GetByID(id uint) (*User, error) {
    var u User
    result := r.db.First(&u, id)
    if errors.Is(result.Error, gorm.ErrRecordNotFound) {
        return nil, nil // tapılmadı — nil qaytar
    }
    return &u, result.Error
}

func (r *UserRepo) List(limit, offset int) ([]User, error) {
    var users []User
    err := r.db.
        Where("aktiv = ?", true).
        Order("created_at DESC").
        Limit(limit).
        Offset(offset).
        Find(&users).Error
    return users, err
}

func (r *UserRepo) Update(id uint, fields map[string]any) error {
    // map istifadə et — struct-da sıfır dəyərlər yenilənmir!
    return r.db.Model(&User{}).Where("id = ?", id).Updates(fields).Error
}

func (r *UserRepo) Delete(id uint) error {
    // soft delete — DeletedAt set olunur
    return r.db.Delete(&User{}, id).Error
}

func (r *UserRepo) HardDelete(id uint) error {
    // həmişəlik silmə
    return r.db.Unscoped().Delete(&User{}, id).Error
}
```

### Nümunə 5: GORM — Association + Preload vs Joins

```go
package main

import "gorm.io/gorm"

type UserRepo struct{ db *gorm.DB }

// Preload — 2 ayrı sorğu (user + posts)
// Nisbətən az post sayı üçün münasib
func (r *UserRepo) GetWithPosts_Preload(id uint) (*User, error) {
    var u User
    err := r.db.
        Preload("Posts").        // SELECT * FROM posts WHERE user_id IN (id)
        First(&u, id).Error      // SELECT * FROM users WHERE id = id
    return &u, err
}

// Joins — 1 sorğu (JOIN)
// Böyük veri setlərində daha effektiv
func (r *UserRepo) GetWithPosts_Joins(id uint) (*User, error) {
    var u User
    err := r.db.
        Joins("Posts").
        First(&u, id).Error      // SELECT users.*, posts.* FROM users LEFT JOIN posts ...
    return &u, err
}

// Conditional Preload — şərtlə yüklə
func (r *UserRepo) GetWithActivePosts(id uint) (*User, error) {
    var u User
    err := r.db.
        Preload("Posts", "published = ?", true). // yalnız published post-lar
        First(&u, id).Error
    return &u, err
}

// N+1 — YANLIŞ
func (r *UserRepo) ListWithPostsBAD() ([]User, error) {
    var users []User
    r.db.Find(&users)
    for i := range users {
        r.db.Find(&users[i].Posts) // N sorğu! 100 user = 101 sorğu
    }
    return users, nil
}
```

### Nümunə 6: GORM — Transaction + Hooks

```go
package main

import (
    "fmt"
    "gorm.io/gorm"
)

// BeforeCreate hook — yaratmadan əvvəl işlənir
func (u *User) BeforeCreate(tx *gorm.DB) error {
    if u.Email == "" {
        return fmt.Errorf("email məcburidir")
    }
    // Email-i lowercase et
    // u.Email = strings.ToLower(u.Email)
    return nil
}

// GORM Transaction
func (r *UserRepo) CreateWithProfile(user *User, bio string) error {
    return r.db.Transaction(func(tx *gorm.DB) error {
        // tx istifadə et, r.db yox!
        if err := tx.Create(user).Error; err != nil {
            return err // Rollback
        }

        profile := Profile{UserID: user.ID, Bio: bio}
        if err := tx.Create(&profile).Error; err != nil {
            return err // Rollback
        }

        return nil // Commit
    })
}

// Raw SQL — GORM-da mürəkkəb sorğular üçün
func (r *UserRepo) GetTopUsers(limit int) ([]User, error) {
    var users []User
    err := r.db.Raw(`
        SELECT u.*, COUNT(p.id) as post_count
        FROM users u
        LEFT JOIN posts p ON p.user_id = u.id
        WHERE u.aktiv = true
        GROUP BY u.id
        ORDER BY post_count DESC
        LIMIT ?
    `, limit).Scan(&users).Error
    return users, err
}
```

### Nümunə 7: golang-migrate ilə Migration

```go
package main

import (
    "log"

    "github.com/golang-migrate/migrate/v4"
    _ "github.com/golang-migrate/migrate/v4/database/postgres"
    _ "github.com/golang-migrate/migrate/v4/source/file"
)

// Migration faylları:
// migrations/000001_create_users.up.sql
// migrations/000001_create_users.down.sql

func runMigrations(dsn string) error {
    m, err := migrate.New(
        "file://migrations",
        dsn,
    )
    if err != nil {
        return err
    }
    defer m.Close()

    if err := m.Up(); err != nil && err != migrate.ErrNoChange {
        return err
    }
    log.Println("Migration tamamlandı")
    return nil
}

// migrations/000001_create_users.up.sql:
/*
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    ad VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    yas INT DEFAULT 0,
    aktiv BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);
CREATE INDEX idx_users_email ON users(email);
*/

// migrations/000001_create_users.down.sql:
/*
DROP TABLE IF EXISTS users;
*/
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — sqlx Repository**

Blog üçün `PostRepository` yaz (sqlx ilə):
- `Create(ctx, post) error`
- `GetByID(ctx, id) (*Post, error)`
- `ListByUser(ctx, userID, limit, offset) ([]Post, error)`
- `ListByTags(ctx, tags []string) ([]Post, error)` — WHERE tag IN
- `Update(ctx, id, fields map[string]any) error`

**Tapşırıq 2 — GORM E-Commerce**

`Order` + `OrderItem` + `Product` modellərini yaz:
- `Order` belongsTo `User`, hasMany `OrderItems`
- `OrderItem` belongsTo `Order` və `Product`
- `PlaceOrder(ctx, userID, items) (*Order, error)` — transaction içində stock azalt
- N+1-dən qaçmaq üçün Preload/Joins istifadə et

**Tapşırıq 3 — AutoMigrate vs golang-migrate**

Mövcud schema-ya yeni sütun əlavə et:
1. `AutoMigrate` ilə əlavə et — işləyir
2. Bir sütunu sil — AutoMigrate silmir! Niyə?
3. Eyni dəyişikliyi `golang-migrate` up/down faylı ilə yaz

## PHP ilə Müqayisə

PHP/Laravel-də Eloquent ORM aktiv record pattern-i istifadə edirdi — `User::find(1)`, `User::where('age', '>', 25)->get()`. GORM-da oxşar sintaksis var: `db.Where("age > ?", 25).Find(&users)`. Amma Go-dakı tip sistemi bu abstraksiyaları daha şəffaf edir.

## Əlaqəli Mövzular

- [05-database](05-database.md) — database/sql əsasları
- [18-project-structure](18-project-structure.md) — Layihə strukturu
- [19-repository-pattern](19-repository-pattern.md) — Repository pattern
- [28-context](../core/28-context.md) — Context ilə query timeout
- [07-environment-and-config](07-environment-and-config.md) — DSN environment-dən almaq
- [24-testing](../core/24-testing.md) — DB testləri — testcontainers
