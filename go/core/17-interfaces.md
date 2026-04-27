# Interfaces (Middle)

## İcmal

Go-da interface bir növ müqavilədir: hansı metodların olması lazım olduğunu müəyyən edir. Go-da interface-i implement etmək üçün heç nə yazmaq lazım deyil — struct həmin metodlara sahib olduqda **avtomatik olaraq** interface-i implement etmiş sayılır. Bu "implicit implementation" adlanır. Kiçik, konkret interface-lər Go-nun ən güclü dizayn prinsiplərindən biridir.

## Niyə Vacibdir

Interface-lər dependency injection, testability və loosely coupled kod üçün əsasdır. Məsələn, database, cache, email servisini interface arxasında gizlətmək — real implementasiyanı dəyişmədən mock istifadə etməyə imkan verir. HTTP handler-lər, middleware, plugin sistemləri — hamısı interface-ə əsaslanır. Go standart kitabxanasının özü də interface-lərlə qurulub: `io.Reader`, `io.Writer`, `error`, `fmt.Stringer`.

## Əsas Anlayışlar

- **Implicit implementation** — `implements` açar sözü yoxdur; metodlar uyğun gəlsə avtomatikdir
- **Kiçik interface** — Go-da ideal interface 1-2 metoddan ibarətdir (`io.Reader`: tək `Read` metodu)
- **Interface composition** — interface-lər bir-birini daxilə ala bilər (`io.ReadWriter`)
- **Empty interface** — `interface{}` və ya `any` — istənilən tipi qəbul edir; type safety yoxdur
- **Type assertion** — `v.(ConcreteType)` — interface-dən konkret tipə çatmaq
- **Comma-ok pattern** — `v, ok := iface.(Type)` — panic vermədən yoxlama
- **Type switch** — `switch v := x.(type)` — çox tipin eyni anda yoxlanması
- **`fmt.Stringer`** — `String() string` metodu olan struct `fmt.Println`-də avtomatik formatlanır
- **`error` interface** — `Error() string` metoduna sahib istənilən tip error-dır
- **Nil interface** — interface-in özü nil ola bilər; nil pointer saxlayan interface nil deyil (klassik tuzaq)

## Praktik Baxış

**Real istifadə ssenariləri:**
- Repository pattern: `UserRepository` interface → real DB və mock implementasiya
- Notifier interface → Email, SMS, Push bildiriş implementasiyaları
- Cache interface → Redis, in-memory, no-op (test üçün) implementasiyaları
- Logger interface → structured, plain text, no-op implementasiyaları

**Trade-off-lar:**
- Interface çox erkən yaratmaq — anti-pattern. "Accept interfaces, return structs" qaydası: funksiya parametri interface qəbul etsin, nəticə struct qaytarsın
- Çox böyük interface — testability azalır; kiçik interface-lərə bölün
- Empty interface (`any`) istifadəsi — type safety itirilir; generics (Go 1.18+) daha yaxşı alternativdir

**Ümumi səhvlər:**
- Nil pointer saxlayan interface-i nil ilə müqayisə etmək — daima `false` olur
- Pointer receiver-li metodlar olan struct-ı value kimi interface-ə vermək — compile xətası
- Interface-i test etmədən yaratmaq — real ehtiyac olmadan interface yaratmayın

## Nümunələr

### Nümunə 1: Implicit implementation

```go
package main

import "fmt"

type Sekil interface {
    Sahe() float64
    Cevre() float64
}

type Duzbucaqli struct {
    En, Boy float64
}

// Bu iki metod var → Duzbucaqli avtomatik Sekil interface-ini implement edir
func (d Duzbucaqli) Sahe() float64  { return d.En * d.Boy }
func (d Duzbucaqli) Cevre() float64 { return 2 * (d.En + d.Boy) }

type Daire struct {
    Radius float64
}

func (da Daire) Sahe() float64  { return 3.14159 * da.Radius * da.Radius }
func (da Daire) Cevre() float64 { return 2 * 3.14159 * da.Radius }

// Funksiya Sekil interface qəbul edir — Duzbucaqli də, Daire də keçə bilər
func sekilInfo(s Sekil) {
    fmt.Printf("Tip: %T | Sahə: %.2f | Çevrə: %.2f\n", s, s.Sahe(), s.Cevre())
}

func main() {
    sekilInfo(Duzbucaqli{En: 5, Boy: 3})
    sekilInfo(Daire{Radius: 7})

    // Slice of interface — müxtəlif tiplər eyni slice-da
    sekiller := []Sekil{
        Duzbucaqli{En: 4, Boy: 6},
        Daire{Radius: 3},
    }
    toplam := 0.0
    for _, s := range sekiller {
        toplam += s.Sahe()
    }
    fmt.Printf("Cəm sahə: %.2f\n", toplam)
}
```

### Nümunə 2: Repository pattern — real layihə nümunəsi

```go
package main

import (
    "errors"
    "fmt"
)

type User struct {
    ID    int
    Ad    string
    Email string
}

// Interface — konkret implementasiyadan asılı deyil
type UserRepository interface {
    Tap(id int) (*User, error)
    Saxla(u *User) error
    Sil(id int) error
}

// Real database implementasiyası
type PostgresUserRepo struct {
    // db *sql.DB
}

func (r *PostgresUserRepo) Tap(id int) (*User, error) {
    // SQL sorğusu...
    return &User{ID: id, Ad: "Orkhan", Email: "o@mail.az"}, nil
}

func (r *PostgresUserRepo) Saxla(u *User) error {
    fmt.Println("DB-yə yazıldı:", u.Ad)
    return nil
}

func (r *PostgresUserRepo) Sil(id int) error {
    fmt.Println("DB-dən silindi:", id)
    return nil
}

// Test üçün mock implementasiya
type MockUserRepo struct {
    users map[int]*User
}

func (m *MockUserRepo) Tap(id int) (*User, error) {
    if u, ok := m.users[id]; ok {
        return u, nil
    }
    return nil, errors.New("tapılmadı")
}

func (m *MockUserRepo) Saxla(u *User) error {
    m.users[u.ID] = u
    return nil
}

func (m *MockUserRepo) Sil(id int) error {
    delete(m.users, id)
    return nil
}

// Service — interface ilə işləyir, konkret tipə baxmır
type UserService struct {
    repo UserRepository // interface field
}

func (s *UserService) ProfilGoster(id int) {
    user, err := s.repo.Tap(id)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("İstifadəçi: %s (%s)\n", user.Ad, user.Email)
}

func main() {
    // Production: real DB
    prodService := &UserService{repo: &PostgresUserRepo{}}
    prodService.ProfilGoster(1)

    // Test: mock
    mock := &MockUserRepo{users: map[int]*User{
        42: {ID: 42, Ad: "Test User", Email: "test@test.com"},
    }}
    testService := &UserService{repo: mock}
    testService.ProfilGoster(42)
    testService.ProfilGoster(99) // tapılmadı
}
```

### Nümunə 3: Stringer, error interface və interface composition

```go
package main

import "fmt"

// fmt.Stringer interface — String() string metodunu tələb edir
type Oyuncu struct {
    Ad  string
    Xal int
}

func (o Oyuncu) String() string {
    return fmt.Sprintf("%s (%d xal)", o.Ad, o.Xal)
}

// error interface — Error() string metodunu tələb edir
type ValidationError struct {
    Field   string
    Message string
}

func (e *ValidationError) Error() string {
    return fmt.Sprintf("validasiya xətası: %s — %s", e.Field, e.Message)
}

// Interface composition
type Reader interface {
    Read(p []byte) (n int, err error)
}

type Writer interface {
    Write(p []byte) (n int, err error)
}

type ReadWriter interface {
    Reader // Reader interface-ni daxilə alır
    Writer // Writer interface-ni daxilə alır
}

func main() {
    // Stringer — fmt.Println avtomatik String() çağırır
    oyuncu := Oyuncu{Ad: "Orkhan", Xal: 100}
    fmt.Println(oyuncu) // Orkhan (100 xal)

    // Custom error
    var err error = &ValidationError{Field: "email", Message: "boş ola bilməz"}
    fmt.Println(err) // validasiya xətası: email — boş ola bilməz

    // Type assertion — interface-dən konkret tipə
    var s interface{} = Daire{Radius: 5}

    // Panic-siz yoxlama (comma-ok pattern)
    if d, ok := s.(Daire); ok {
        fmt.Println("Dairədir, radius:", d.Radius)
    }

    // Type switch — çox tip
    deyerler := []interface{}{42, "salam", true, 3.14}
    for _, d := range deyerler {
        switch v := d.(type) {
        case int:
            fmt.Printf("int: %d\n", v)
        case string:
            fmt.Printf("string: %q\n", v)
        case bool:
            fmt.Printf("bool: %t\n", v)
        default:
            fmt.Printf("digər tip: %T\n", v)
        }
    }
}

type Daire struct{ Radius float64 }
func (da Daire) Sahe() float64  { return 3.14159 * da.Radius * da.Radius }
func (da Daire) Cevre() float64 { return 2 * 3.14159 * da.Radius }
```

### Nümunə 4: Nil interface tuzağı

```go
package main

import "fmt"

type MyError struct{ msg string }
func (e *MyError) Error() string { return e.msg }

// BU KOD YANLIDIR — nil pointer error qaytarır, lakin nil deyil!
func yanlisFunc() error {
    var err *MyError = nil
    return err // error interface-i nil pointer saxlayır — interface özü nil deyil!
}

// DUZGUN yol
func duzgunFunc() error {
    return nil // bilavasitə nil qaytarılır
}

func main() {
    err1 := yanlisFunc()
    fmt.Println("yanlisFunc() == nil?", err1 == nil) // false! (tuzaq)

    err2 := duzgunFunc()
    fmt.Println("duzgunFunc() == nil?", err2 == nil) // true

    // Compile-time interface uyğunluq yoxlaması
    // var _ UserRepository = (*PostgresUserRepo)(nil)
    // Bu sətir compile olmazsa, PostgresUserRepo interface-i implement etmir
}
```

## Praktik Tapşırıqlar

1. **Notifier interface:** `Send(to, subject, body string) error` metodlu interface yarat. `EmailNotifier`, `SMSNotifier` və `LogNotifier` (yalnız `fmt.Println`) implementasiyaları yaz. `NotificationService` hər üçünü dövrə vurub göndərsin.

2. **Cache interface:** `Get(key string) (string, bool)` və `Set(key, value string)` metodlu interface yarat. `MemoryCache` (map ilə) implementasiyası yaz. Sonra `RedisCache` stub-u əlavə et.

3. **Stringer tətbiqi:** `Order` struct-ı (ID, müştəri adı, məbləğ, status) üçün `String()` metodu yaz. Status üçün enum (`StatusPending`, `StatusPaid`, `StatusCancelled`) istifadə et.

4. **Middleware zənciri:** HTTP middleware üçün `Handler interface { ServeHTTP(req, res) }` yarat. `LoggingMiddleware` və `AuthMiddleware` implementasiyaları yaz, onları zəncirə yığ.

5. **Plugin sistemi:** `Plugin interface { Name() string; Execute(data []byte) ([]byte, error) }` yarat. `GzipPlugin` və `JSONValidatorPlugin` implementasiyaları yaz.

## PHP ilə Müqayisə

| PHP | Go |
|-----|-----|
| `class Foo implements Bar` — açıq yazılır | Heç nə yazılmır — avtomatik |
| `interface Bar { public function method(): void; }` | `type Bar interface { Method() }` |
| `instanceof` ilə yoxlama | Type assertion: `v, ok := x.(Bar)` |
| Abstract class mövcuddur | Yoxdur — interface + struct composition |
| Interface `null` ola bilər | Interface nil ola bilər (lakin nil pointer tuzağı var) |

## Əlaqəli Mövzular

- [10-structs.md](10-structs.md) — struct əsasları, method-lar
- [18-error-handling.md](18-error-handling.md) — `error` interface istifadəsi
- [19-type-assertions.md](19-type-assertions.md) — type assertion və type switch ətraflı
- [29-generics.md](29-generics.md) — interface-lərin generics ilə birlikdə istifadəsi
- [52-mocking-and-testify.md](52-mocking-and-testify.md) — interface ilə mock yaratmaq
- [55-repository-pattern.md](55-repository-pattern.md) — real layihədə repository pattern
