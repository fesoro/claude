# Struct Embedding (Senior)

## İcmal

Go-da inheritance yoxdur. Bunun əvəzinə **struct embedding** var — bir struct-ı digərinə anonymous field kimi daxil etməklə metodları "promote" etmək imkanı verir. Bu composition vasitəsilə Go-nun "has-a" münasibətini qurmağın idiomatik yoludur. Interface embedding isə kiçik interfeyslər birləşdirərək daha böyük kontraktlar yaratmağa imkan verir.

## Niyə Vacibdir

- Go-da inheritance yoxdur — composition onun yerinə keçir
- Kod təkrarını azaltmağın əsas üsuludur
- Interface-ləri implement edərkən partial implementation vermək üçün istifadə olunur
- `http.Handler`-i extend etmək, `sync.Mutex`-i struct-a gömmək kimi real ssenarilər

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

## Praktik Baxış

**Embedding qaydaları:**

| Ssenari | Yanaşma |
|---------|---------|
| Shared timestamp fields | `Timestamps` struct embed et |
| Thread-safe struct | `sync.Mutex` embed et |
| HTTP handler wrap | `http.Handler` embed et |
| Interface genişləndir | Interface embedding ilə compose et |
| Inheritance simulyasiyası | Etmə — "is-a" yoxdur |

**Anti-pattern-lər:**

- Çox dərin embedding — zəncir uzanırsa oxunaqlıq azalır
- Struct-ı həm embed həm xarici field kimi istifadə — ad konflikti
- Embedding-i inheritance kimi düşünmək — "is-a" yoxdur, "has-a" + promotion var

**Trade-off-lar:**

- Embedding sadədir, amma interfeysi tam implement etmirsənsə compile error çıxır
- Promoted metodun hansı struct-dan gəldiyini görmək çətinləşə bilər — explicit `s.Embedded.Method()` daha aydındır
- Ad konflikti olduqda promoted metod gizlənir, üst struct-ın metodu qalib gəlir

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

**Tapşırıq 3 — SafeMap**

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
- [31-go-embed](31-go-embed.md) — go:embed direktivi (faylları binary-yə daxil etmək)
- [35-struct-advanced](35-struct-advanced.md) — Struct tags, comparison, zero value
- [46-text-templates](../backend/10-text-templates.md) — HTML şablonlarını embed ilə istifadə
