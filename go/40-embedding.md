# Embedding — Struct, Interface, go:embed (Senior)

## İcmal

Go-da iki fərqli "embedding" anlayışı var: **Struct/Interface Embedding** — Go-nun inheritance-ə alternativ mexanizmi, composition vasitəsilə bir tipin metodlarını digərinə ötürür; **`go:embed` direktivi** — faylları (HTML, CSS, SQL, şəkil) compile zamanı binary-yə daxil edir, deploy zamani ayrı fayl lazım olmur.

## Niyə Vacibdir

**Struct Embedding:**
- Go-da inheritance yoxdur — composition onun yerinə keçir
- Kod təkrarını azaltmağın əsas üsuludur
- Interface-ləri implement edərkən partial implementation vermək üçün istifadə olunur
- `http.Handler`-i extend etmək, `sync.Mutex`-i struct-a gömmək kimi real ssenarilər

**`go:embed`:**
- HTML şablonları, statik fayllar, SQL migration-lar binary-yə daxildir
- Deploy: bir fayl, asılılıq yoxdur
- Docker image-lər daha kiçik olur — ayrı static/ qovluğuna ehtiyac yoxdur
- Compile-time error — göstərilən fayl yoxdursa build uğursuz olur

## Əsas Anlayışlar

**Struct Embedding — necə işləyir:**

```go
type Animal struct {
    Ad string
}

func (a Animal) Danish() string {
    return a.Ad + " danışır"
}

type Dog struct {
    Animal  // embed — field adı yoxdur, tip adı sahə adıdır
    Cins string
}

d := Dog{Animal: Animal{Ad: "Buddy"}, Cins: "Labrador"}
fmt.Println(d.Danish()) // "Buddy danışır" — Dog-un öz metodu yoxdur, Animal-dədir
fmt.Println(d.Ad)       // "Buddy" — promoted field
```

**Method promotion** — embed olunan tipin metodları "qalxır", çağıran struct-dan birbaşa çağırıla bilər.

**Interface Embedding:**

```go
type Reader interface { Read(p []byte) (n int, err error) }
type Writer interface { Write(p []byte) (n int, err error) }

type ReadWriter interface {
    Reader  // embed
    Writer  // embed
}
// ReadWriter = Reader + Writer
```

**`go:embed` direktivi:**

```go
//go:embed fayl.txt          // string
var content string

//go:embed fayl.bin          // byte slice
var data []byte

//go:embed templates/*       // embed.FS — qovluq
var templates embed.FS
```

## Praktik Baxış

**`go:embed` üstünlükləri vs fayl sistemi:**

| | `go:embed` | Fayl sistemi |
|---|---|---|
| Deploy | Tək binary | Ayrı fayllar lazım |
| Compile check | Fayl yoxdursa build uğursuz | Runtime panic |
| Performans | Yaddaşdan oxu | Disk I/O |
| Dəyişiklik | Rebuild lazım | Fayl dəyişsə dərhal |

**Anti-pattern-lər:**

- Çox dərin embedding — zəncir uzanırsa oxunaqlıq azalır
- Struct-ı həm embed həm xarici field kimi istifadə — ad konflikti
- `go:embed` ilə böyük binary faylları — binary şişir, RAM istifadəsi artır
- Embedding-i inheritance kimi düşünmək — "is-a" yoxdur, "has-a" + promotion var

**Trade-off-lar:**

- Struct embedding — sadədir, amma interfeysi tam implement etmirəmsə compile error var
- `go:embed` vs external config — koda qatılmış şablon dəyişdiriləndə rebuild lazımdır; xarici faylda isə yenidən deploy lazım deyil

## Nümunələr

### Nümunə 1: Struct Embedding — Əsaslar

```go
package main

import "fmt"

type Timestamp struct {
    CreatedAt string
    UpdatedAt string
}

func (t Timestamp) Info() string {
    return fmt.Sprintf("yaradıldı: %s, yeniləndi: %s", t.CreatedAt, t.UpdatedAt)
}

type User struct {
    Timestamp             // embed — field adı "Timestamp"
    ID    int
    Ad    string
    Email string
}

type Post struct {
    Timestamp             // eyni embed başqa struct-da
    ID      int
    Baslik  string
    UserID  int
}

func main() {
    u := User{
        Timestamp: Timestamp{CreatedAt: "2025-01-01", UpdatedAt: "2025-01-10"},
        ID:    1,
        Ad:    "Orkhan",
        Email: "o@test.az",
    }

    fmt.Println(u.Info())        // Timestamp.Info() promoted — u.Info() işləyir
    fmt.Println(u.CreatedAt)     // promoted field — u.Timestamp.CreatedAt ilə eyni
    fmt.Println(u.Timestamp.Info()) // explicit olaraq da çağıra bilərsən

    p := Post{
        Timestamp: Timestamp{CreatedAt: "2025-02-01", UpdatedAt: "2025-02-05"},
        ID:     10,
        Baslik: "Go Öyrənmək",
    }
    fmt.Println(p.Info()) // Post da Timestamp.Info() istifadə edir
}
```

### Nümunə 2: Method Override — Promoted Metodun Üzərinə Yazmaq

```go
package main

import "fmt"

type Logger struct{}

func (l Logger) Log(msg string) {
    fmt.Println("[BASE]", msg)
}

type ProductionLogger struct {
    Logger              // embed
}

// Override — eyni imza ilə öz metodunu yaz
func (pl ProductionLogger) Log(msg string) {
    // İstəsən base metodu çağır
    // pl.Logger.Log(msg) // → "[BASE] ..."
    fmt.Println("[PROD]", msg) // öz implementasiyası
}

func main() {
    base := Logger{}
    base.Log("test")            // [BASE] test

    prod := ProductionLogger{}
    prod.Log("test")            // [PROD] test — override işləyir
    prod.Logger.Log("test")     // [BASE] test — base-i explicit çağır
}
```

### Nümunə 3: Interface Embedding

```go
package main

import (
    "fmt"
    "strings"
)

// Kiçik interfeyslər — Go-nun idiomu
type Stringer interface {
    String() string
}

type Validator interface {
    Validate() error
}

// Embed edilmiş interface — birləşmə
type Model interface {
    Stringer
    Validator
}

// Struct model-i implement edir
type User struct {
    Ad    string
    Email string
}

func (u User) String() string {
    return fmt.Sprintf("User{%s, %s}", u.Ad, u.Email)
}

func (u User) Validate() error {
    if u.Ad == "" {
        return fmt.Errorf("ad boş ola bilməz")
    }
    if !strings.Contains(u.Email, "@") {
        return fmt.Errorf("yanlış email: %s", u.Email)
    }
    return nil
}

// Model interface-ini qəbul edən funksiya
func save(m Model) error {
    if err := m.Validate(); err != nil {
        return err
    }
    fmt.Println("Saxlanıldı:", m.String())
    return nil
}

func main() {
    u := User{Ad: "Orkhan", Email: "orkhan@test.az"}
    save(u) // User həm Stringer həm Validator implement edir → Model-dir

    invalid := User{Ad: "", Email: "nope"}
    if err := save(invalid); err != nil {
        fmt.Println("Xəta:", err)
    }
}
```

### Nümunə 4: sync.Mutex Embedding — Real Pattern

```go
package main

import (
    "fmt"
    "sync"
)

// Mutex-i embed etmək — Go-da çox istifadə olunan pattern
type SafeCounter struct {
    sync.Mutex          // embed — Lock(), Unlock() promoted olur
    count int
}

func (c *SafeCounter) Increment() {
    c.Lock()           // c.Mutex.Lock() ilə eyni — promoted
    defer c.Unlock()
    c.count++
}

func (c *SafeCounter) Value() int {
    c.Lock()
    defer c.Unlock()
    return c.count
}

// HTTP handler-i embed etmək nümunəsi
type LoggingHandler struct {
    http.Handler        // embed — ServeHTTP promoted olur
}

func (h LoggingHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
    fmt.Printf("[LOG] %s %s\n", r.Method, r.URL.Path)
    h.Handler.ServeHTTP(w, r) // base-i çağır
}

func main() {
    c := &SafeCounter{}
    var wg sync.WaitGroup
    for i := 0; i < 1000; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            c.Increment()
        }()
    }
    wg.Wait()
    fmt.Println("Nəticə:", c.Value()) // 1000
}
```

### Nümunə 5: go:embed — String, Bytes, FS

```go
package main

import (
    _ "embed"
    "embed"
    "fmt"
    "io/fs"
    "net/http"
)

// Tək fayl — string kimi
//go:embed templates/welcome.html
var welcomeHTML string

// Tək fayl — byte slice kimi
//go:embed static/logo.png
var logoBytes []byte

// Qovluq — embed.FS kimi
//go:embed templates
var templateFS embed.FS

// SQL migration-lar
//go:embed migrations
var migrationsFS embed.FS

func main() {
    // String birbaşa istifadə
    fmt.Println("Şablon uzunluğu:", len(welcomeHTML))

    // Byte slice — şəkil, fayl göndərmə
    fmt.Printf("Logo: %d byte\n", len(logoBytes))

    // FS-dən oxumaq
    content, err := templateFS.ReadFile("templates/welcome.html")
    if err != nil {
        fmt.Println("Xəta:", err)
    }
    fmt.Println("FS-dən:", len(content), "byte")

    // Qovluq gəzmə
    fs.WalkDir(templateFS, ".", func(path string, d fs.DirEntry, err error) error {
        if !d.IsDir() {
            fmt.Println("Fayl:", path)
        }
        return nil
    })

    // HTTP server — static faylları binary-dən serve et
    mux := http.NewServeMux()

    // templates qovluğunu sub-FS kimi aç
    templSub, _ := fs.Sub(templateFS, "templates")
    mux.Handle("/static/", http.StripPrefix("/static/",
        http.FileServer(http.FS(templSub)),
    ))

    mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("Content-Type", "text/html")
        w.Write([]byte(welcomeHTML))
    })

    http.ListenAndServe(":8080", mux)
}
```

### Nümunə 6: go:embed — SQL Migration + net/http

```go
package main

import (
    "embed"
    "fmt"
    "io/fs"
)

//go:embed migrations/*.sql
var migrationFS embed.FS

// Migration fayllarını oxu və sırayla icra et
func runMigrations(db interface{ Exec(string) error }) error {
    entries, err := fs.ReadDir(migrationFS, "migrations")
    if err != nil {
        return err
    }

    for _, entry := range entries {
        if entry.IsDir() {
            continue
        }
        name := entry.Name()
        if len(name) < 4 || name[len(name)-4:] != ".sql" {
            continue
        }

        content, err := migrationFS.ReadFile("migrations/" + name)
        if err != nil {
            return fmt.Errorf("fayl oxuma %s: %w", name, err)
        }

        fmt.Printf("Migration icra edilir: %s\n", name)
        if err := db.Exec(string(content)); err != nil {
            return fmt.Errorf("migration %s: %w", name, err)
        }
    }
    return nil
}

// Layihə strukturu:
// myapp/
// ├── main.go
// ├── migrations/
// │   ├── 001_create_users.sql
// │   ├── 002_create_posts.sql
// │   └── 003_add_indexes.sql
// └── templates/
//     ├── index.html
//     └── welcome.html
//
// go build → tək binary, bütün fayllar daxildir
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Reusable Timestamp**

`Timestamps` struct yaz: `CreatedAt`, `UpdatedAt`, `DeletedAt`. `User`, `Post`, `Comment` struct-larına embed et. Hər birinin `IsDeleted()` metodu olsun (promoted).

**Tapşırıq 2 — Interface Composition**

Aşağıdakı interfeyslər:
- `Saver interface { Save() error }`
- `Loader interface { Load(id int) error }`
- `Deleter interface { Delete(id int) error }`
- `Repository interface { Saver; Loader; Deleter }`

`UserRepository struct` yaz, bütün interfeysi implement et. Funksiyalar yalnız `Saver` yoxsa `Repository` qəbul etsin — fərqi izah et.

**Tapşırıq 3 — go:embed HTML Server**

```
templates/
  index.html
  about.html
static/
  style.css
```

Bu faylları binary-yə embed et. HTTP server:
- `/` → `index.html`
- `/about` → `about.html`
- `/static/style.css` → CSS fayl

`go build` ilə tək binary yarat, `./templates/` qovluğunu sil, server yenə işləsin.

**Tapşırıq 4 — SafeMap**

```go
type SafeMap[K comparable, V any] struct {
    sync.RWMutex
    data map[K]V
}
```

`Get`, `Set`, `Delete`, `Keys` metodları yaz. Generic istifadə et. Goroutine-safe olsun.

## PHP ilə Müqayisə

OOP-dən gələn developer üçün struct embedding ən çaşdırıcı Go konseptlərindən biridir. PHP-də `extends` ilə sinif miras alınır, Go-da isə bu mexanizm yoxdur — composition istifadə olunur.

```php
// PHP
class AdminUser extends User {
    public function deleteAll() { ... }
}
$u = new AdminUser();
$u->getName(); // User-dən miras
```

```go
// Go — composition ilə eyni effekt
type User struct { Ad string }
func (u User) GetAd() string { return u.Ad }

type AdminUser struct {
    User              // embed — "inherit" deyil, promotion
    Permission string
}

u := AdminUser{User: User{Ad: "Orkhan"}, Permission: "admin"}
u.GetAd() // User.GetAd() çağırılır — promoted method
```

**Əsas fərq:** Go embedding-də "is-a" münasibəti yoxdur — `AdminUser` `User` tipi deyil. Yalnız metodlar promoted olur.

## Əlaqəli Mövzular

- [10-structs](10-structs.md) — Struct əsasları
- [17-interfaces](17-interfaces.md) — Interface-lər
- [11-pointers](11-pointers.md) — Pointer receiver-lər
- [27-goroutines-and-channels](27-goroutines-and-channels.md) — sync.Mutex embedding
- [31-go-embed](31-go-embed.md) — go:embed dərin baxış
- [33-http-server](33-http-server.md) — embed.FS ilə static fayl serve
- [46-text-templates](46-text-templates.md) — HTML şablonlarını embed ilə istifadə
