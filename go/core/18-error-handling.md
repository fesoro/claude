# Error Handling (Middle)

## İcmal

Go-da xəta idarəetməsi fərqli bir yanaşmaya əsaslanır: Go-da xətalar sadəcə dəyərlərdir — funksiyalar xətanı son qaytarma dəyəri kimi qaytarır, çağıran isə dərhal yoxlayır. Bu yanaşma explicit (açıq) nəzarəti təmin edir: xəta heç vaxt "gizli" qalmır. Go 1.13-dən etibarən `%w` ilə xəta sarma (wrapping), `errors.Is` və `errors.As` ilə dərinlikli yoxlama mümkündür.

## Niyə Vacibdir

Düzgün xəta idarəetməsi production-da sistemin sağlamlığını müəyyən edir. Hansı xəta nədən qaynaqlandı, hansı kontekstdə baş verdi — bu məlumatlar olmadan debugging imkansız olur. Go-nun explicit error handling yanaşması developer-i hər xəta haqqında düşünməyə məcbur edir, nəticədə daha etibarlı kod yazılır.

## Əsas Anlayışlar

- **`error` interface** — `Error() string` metodlu istənilən tip error-dır
- **`errors.New`** — sadə error mesajı yaradır
- **`fmt.Errorf`** — formatlanmış error mesajı; `%w` ilə wrapping
- **`%w` verb** — xətanı sarar; `errors.Is` / `errors.As` ilə açmaq mümkündür
- **`errors.Is(err, target)`** — xəta zəncirini keçərək target ilə müqayisə edir
- **`errors.As(err, &target)`** — xəta zəncirini keçərək konkret tipə cast etməyə çalışır
- **Sentinel errors** — paket səviyyəsindəki məlum xəta dəyişkənləri (`io.EOF`, `sql.ErrNoRows`)
- **Custom error types** — `Error()` metodu olan struct; əlavə kontekst saxlaya bilər
- **`panic`** — ciddi proqram xətası; normal axın üçün `panic` istifadə etməyin
- **`recover`** — `defer` içindəki `recover()` panic-i tutur

## Praktik Baxış

**Real istifadə ssenariləri:**
- HTTP handler-də validation xətasını 400, DB xətasını 500 kimi ayırd etmək
- Servis qatında xətanı kontekstlə sararaq yuxarıya ötürmək
- `errors.As` ilə konkret xəta tipini tapıb müvafiq qərara gəlmək
- Sentinel error ilə "tapılmadı" vəziyyətini fərqləndirmək

**Trade-off-lar:**
- `if err != nil` blokları kodu şaquli istiqamətdə artırır; ancaq bu explicit nəzarətin qiymətidir
- Xətanı çox dərindən sarmaq — debugging-i asanlaşdırır, lakin çox qat xəta mesajı konfetə bənzəyir
- Panic yalnız proqram ümumiyyətlə davam edə bilməyəndə istifadə olunur (məsələn init zamanında məcburi konfiqurasiya yoxdursa)

**Ümumi səhvlər:**
- Xətanı yoxlamadan `_` ilə atmaq — ən pis anti-pattern
- `fmt.Errorf("xəta: %v", err)` — `%v` wrap etmir; `errors.Is/As` işləmír; `%w` istifadə edin
- Hər qatda eyni xətanı loglamaq — logging yalnız ən yuxarı qatda olsun, aşağıda yalnız wrap edin
- Custom error type üçün pointer receiver əvəzinə value receiver — `errors.As` işləmir

## Nümunələr

### Nümunə 1: Əsas pattern — return value ilə xəta

```go
package main

import (
    "errors"
    "fmt"
)

// Go-da xəta son qaytarma dəyəridir
func bol(a, b float64) (float64, error) {
    if b == 0 {
        return 0, errors.New("sıfıra bölmək mümkün deyil")
    }
    return a / b, nil // nil = xəta yoxdur
}

func yasYoxla(yas int) error {
    if yas < 0 {
        return fmt.Errorf("yaş mənfi ola bilməz: %d", yas)
    }
    if yas > 150 {
        return fmt.Errorf("yaş real deyil: %d", yas)
    }
    return nil
}

func main() {
    netice, err := bol(10, 0)
    if err != nil {
        fmt.Println("Xəta:", err)
    } else {
        fmt.Printf("Nəticə: %.2f\n", netice)
    }

    netice, err = bol(10, 3)
    if err != nil {
        fmt.Println("Xəta:", err)
    } else {
        fmt.Printf("Nəticə: %.2f\n", netice) // 3.33
    }

    if err := yasYoxla(-5); err != nil {
        fmt.Println("Xəta:", err) // yaş mənfi ola bilməz: -5
    }
}
```

### Nümunə 2: Custom error type — əlavə kontekst

```go
package main

import (
    "errors"
    "fmt"
)

// Xüsusi xəta tipi — əlavə məlumat saxlayır
type ValidationError struct {
    Field   string
    Message string
    Code    int
}

// Error() metodu — error interface-ni implement edir
func (e *ValidationError) Error() string {
    return fmt.Sprintf("[%d] %s: %s", e.Code, e.Field, e.Message)
}

func emailYoxla(email string) error {
    if len(email) == 0 {
        return &ValidationError{
            Field:   "email",
            Message: "boş ola bilməz",
            Code:    1001,
        }
    }
    if len(email) > 255 {
        return &ValidationError{
            Field:   "email",
            Message: "255 simvoldan uzun ola bilməz",
            Code:    1002,
        }
    }
    return nil
}

func main() {
    err := emailYoxla("")
    if err != nil {
        fmt.Println("Xəta:", err)

        // errors.As — konkret tipi çıxarır
        var valErr *ValidationError
        if errors.As(err, &valErr) {
            fmt.Printf("Sahə: %s, Kod: %d\n", valErr.Field, valErr.Code)
        }
    }
}
```

### Nümunə 3: Error wrapping — `%w`, `errors.Is`, `errors.As`

```go
package main

import (
    "errors"
    "fmt"
    "os"
)

// Sentinel error — məlum xəta dəyəri
var ErrTapilmadi = errors.New("tapılmadı")

func istifadeciTap(id int) (string, error) {
    if id <= 0 {
        return "", ErrTapilmadi
    }
    return "Orkhan", nil
}

func istifadeciProfilet(id int) (string, error) {
    ad, err := istifadeciTap(id)
    if err != nil {
        // %w ilə wrap et — errors.Is/As işləyəcək
        return "", fmt.Errorf("profil alma xətası (id=%d): %w", id, err)
    }
    return "Profil: " + ad, nil
}

func faylOxu(ad string) error {
    _, err := os.Open(ad)
    if err != nil {
        return fmt.Errorf("fayl oxunarkən xəta [%s]: %w", ad, err)
    }
    return nil
}

func main() {
    // errors.Is — zəncirdə ErrTapilmadi-ni axtarır
    _, err := istifadeciProfilet(0)
    if errors.Is(err, ErrTapilmadi) {
        fmt.Println("İstifadəçi tapılmadı") // bu çap olunur
    }
    fmt.Println("Tam xəta:", err)
    // profil alma xətası (id=0): tapılmadı

    // errors.As — zəncirdə konkret tip axtarır
    err2 := faylOxu("yoxdur.txt")
    if err2 != nil {
        var pathErr *os.PathError
        if errors.As(err2, &pathErr) {
            fmt.Println("Fayl yolu:", pathErr.Path)
            fmt.Println("Əməliyyat:", pathErr.Op)
        }
    }
}
```

### Nümunə 4: Sentinel errors — HTTP handler nümunəsi

```go
package main

import (
    "errors"
    "fmt"
)

// Paket səviyyəsindəki sentinel errors
var (
    ErrNotFound   = errors.New("tapılmadı")
    ErrForbidden  = errors.New("icazə yoxdur")
    ErrConflict   = errors.New("artıq mövcuddur")
)

type User struct {
    ID   int
    Ad   string
    Role string
}

func userGetir(id int) (*User, error) {
    switch id {
    case 0:
        return nil, ErrNotFound
    case 999:
        return nil, ErrForbidden
    case 1:
        return &User{ID: 1, Ad: "Orkhan", Role: "admin"}, nil
    }
    return nil, ErrNotFound
}

// HTTP handler-ə bənzər funksiya — xətanı tip üzrə idarə edir
func handleGetUser(id int) {
    user, err := userGetir(id)
    if err != nil {
        switch {
        case errors.Is(err, ErrNotFound):
            fmt.Printf("HTTP 404: istifadəçi %d tapılmadı\n", id)
        case errors.Is(err, ErrForbidden):
            fmt.Printf("HTTP 403: id=%d üçün icazə yoxdur\n", id)
        default:
            fmt.Printf("HTTP 500: daxili xəta: %v\n", err)
        }
        return
    }
    fmt.Printf("HTTP 200: %+v\n", user)
}

func main() {
    handleGetUser(1)   // HTTP 200
    handleGetUser(0)   // HTTP 404
    handleGetUser(999) // HTTP 403
    handleGetUser(42)  // HTTP 404
}
```

### Nümunə 5: panic və recover — yalnız kritik hallarda

```go
package main

import "fmt"

// recover — yalnız defer içində işləyir
func guvenliCalisdir(f func()) (err error) {
    defer func() {
        if r := recover(); r != nil {
            err = fmt.Errorf("panic tutuldu: %v", r)
        }
    }()
    f()
    return nil
}

func main() {
    err := guvenliCalisdir(func() {
        panic("bir şey pis getdi!")
    })
    if err != nil {
        fmt.Println("Xəta:", err) // panic tutuldu: bir şey pis getdi!
    }

    fmt.Println("Proqram davam edir")

    // QEYD: panic yalnız bu hallarda istifadə olunur:
    // 1. init() zamanında məcburi konfiqurasiya yoxdursa
    // 2. regexp.MustCompile kimi "bu xəta baş versə mənasız olar" yerlərdə
    // 3. Proqramçı xətası (nil pointer dereference kimi)
    // Normal axın üçün HƏMIŞƏ error qaytarın
}
```

## Praktik Tapşırıqlar

1. **HTTP error middleware:** `AppError` struct-ı yarat (HTTP status kodu, mesaj, orijinal xəta). Hər HTTP handler xətasını bu tipə çevirsin. Middleware yuxarıda `errors.As` ilə tipi tanıyıb müvafiq HTTP cavabı qaytarsın.

2. **Multi-error collector:** Bir anda bir neçə validasiya xətasını yığan `ValidationErrors` tipi yaz (`[]ValidationError`). `Add(field, msg string)` metodu, `HasErrors() bool` metodu, `Error() string` metodu olsun.

3. **Retry ilə xəta idarəetməsi:** `ErrTemporary` (müvəqqəti xəta) sentinel error yarat. `WithRetry(fn func() error, maxAttempts int)` funksiyası yaz — `errors.Is(err, ErrTemporary)` doğrudursa yenidən cəhd etsin.

4. **Error chain analizi:** Xəta zəncirini tam açan `UnwrapAll(err error) []error` funksiyası yaz. `errors.Unwrap` ilə iterasiya et, hər xəta qatını slice-a əlavə et.

5. **Database error mapping:** `*pq.Error` (PostgreSQL) tipindən `AppError`-a çevirmə funksiyası yaz. Unique violation → `ErrConflict`, foreign key → `ErrNotFound`, digər → `ErrInternal`.

## PHP ilə Müqayisə

| PHP | Go |
|-----|-----|
| `try { ... } catch (Exception $e)` | `result, err := func(); if err != nil { ... }` |
| Exception avtomatik "bubble up" edir | Xəta əl ilə qaytarılır və yoxlanır |
| `throw new ValidationException(...)` | `return nil, &ValidationError{...}` |
| `$e->getMessage()` | `err.Error()` |
| `catch (\Illuminate\Validation\ValidationException $e)` | `errors.As(err, &validationErr)` |
| Stack trace avtomatik | Əl ilə `debug.Stack()` çağırılmalı |

## Əlaqəli Mövzular

- [17-interfaces.md](17-interfaces.md) — `error` bir interface-dir; custom error tipi yaratmaq
- [19-type-assertions.md](19-type-assertions.md) — `errors.As`-ın daxilindəki mexanizm
- [24-testing.md](24-testing.md) — xəta hallarını test etmək
- [01-http-server.md](../backend/01-http-server.md) — HTTP handler-də xəta idarəetməsi
- [05-database.md](../backend/05-database.md) — database xətalarını idarə etmək
- [17-graceful-shutdown.md](../backend/17-graceful-shutdown.md) — xəta ilə bağlı graceful shutdown
