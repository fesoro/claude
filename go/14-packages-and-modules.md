# Paketlər və Modullar — Packages and Modules (Junior)

## İcmal

Go-da kod **paket** (package) adlanan vahidlərə bölünür. Hər Go faylı bir paketə aiddir. **Modul** isə bir neçə paketin toplandığı layihədir — `go.mod` faylı ilə idarə olunur. Go-nun standart kitabxanası zengindir: `fmt`, `strings`, `strconv`, `math`, `sort`, `time`, `os`, `encoding/json` — bunların hamısı xarici dependency olmadan işləyir.

## Niyə Vacibdir

Go modullar sistemi (Go Modules, `go mod`) 2019-dan bəri standart paket idarəetmə mexanizmidir. Xarici dependency əlavə etmək, versiyaları idarə etmək, `go.sum` ilə cryptographic verification — bunlar production-grade layihənin əsasıdır. Standart kitabxanı yaxşı bilmək isə gereksiz xarici dependency-dən qaçınmağa imkan verir: `sort`, `time`, `encoding/json` — bunlar üçün ayrı paket lazım deyil.

## Əsas Anlayışlar

- **`package main`** — proqramın başladığı paket; `main()` funksiyasını ehtiva edir
- **`package xyz`** — kitabxana paketi; `main` paketi deyil, birbaşa işlədilmir
- **Export qaydası** — Böyük hərflə başlayan identifikator (`User`, `GetUser`) xarici paketlərə görünür; kiçik hərflə (`user`, `getUser`) paketi daxilindən görünür
- **`go mod init`** — yeni modul yarat; `go.mod` faylı yaranır
- **`go get`** — xarici paket yüklə; `go.mod` + `go.sum` yenilənir
- **`go.mod`** — modul adı + Go versiyası + dependency siyahısı
- **`go.sum`** — dependency-lərin cryptographic hash-i; `git`-ə commit edin
- **`import`** — paket import; unused import compile error verir
- **Blank import** — `import _ "paket"` — yan effekt üçün (driver qeydiyyat)
- **Alias** — `import alias "paket"` — ad konflikti olduqda

## Standart Kitabxana — Vacib Paketlər

| Paket | Məqsəd |
|-------|---------|
| `fmt` | Formatlaşdırma, çap |
| `strings` | String əməliyyatları |
| `strconv` | Tip çevrilmələri |
| `math` | Riyazi funksiyalar |
| `sort` | Sıralama |
| `time` | Vaxt əməliyyatları |
| `os` | Əməliyyat sistemi |
| `encoding/json` | JSON |
| `math/rand` | Təsadüfi ədəd |
| `net/http` | HTTP server/client |

## Praktik Baxış

**Real layihədə istifadə:**
- `math/rand` → `crypto/rand` ilə əvəzlə security-sensitive kontekstdə
- `encoding/json` — API response serialize/deserialize
- `sort.Slice` — xüsusi sıralama məntiqi ilə
- `time.Now`, `time.Format` — log, timestamp, deadline hesablamaları

**Trade-off-lar:**
- Standart kitabxana vs xarici paket: standart kitabxana daimi, uyğun, amma bəzən verbose
- `go get` ilə əlavə olunan paketlər `go.sum`-da hash-lənir — dəyişiklik aşkarlanır
- Vendor mode (`go mod vendor`) — oflayn build, Docker-da cache üçün faydalı

**Common mistakes:**
- Import edilib istifadə edilməyən paket — compile error
- `go.sum` faylını `.gitignore`-a əlavə etmək — bu yanlışdır; `go.sum` commit edilməlidir
- Paket adını qovluq adından fərqli yazmaq — konvensiya pozuntusu

## Nümunələr

### Nümunə 1: Standart kitabxana — əsas paketlər

```go
package main

import (
    "encoding/json"
    "fmt"
    "math"
    "math/rand"
    "sort"
    "strings"
    "time"
)

func main() {
    // fmt — formatlaşdırma
    ad := "Orkhan"
    fmt.Printf("Salam, %s! Tip: %T\n", ad, ad)
    s := fmt.Sprintf("Mesaj: %s", ad)
    fmt.Println(s)

    // strings — string əməliyyatları
    metn := "Go dilini öyrənirik"
    fmt.Println(strings.Contains(metn, "Go"))
    fmt.Println(strings.ToUpper(metn))
    fmt.Println(strings.Split(metn, " "))

    // math — riyaziyyat
    fmt.Printf("Abs: %.1f\n", math.Abs(-5.5))
    fmt.Printf("Sqrt: %.2f\n", math.Sqrt(16))
    fmt.Printf("Pi: %.5f\n", math.Pi)
    fmt.Printf("Pow: %.0f\n", math.Pow(2, 10))

    // sort — sıralama
    ededler := []int{5, 2, 8, 1, 9, 3}
    sort.Ints(ededler)
    fmt.Println("Sıralanmış:", ededler)

    sozler := []string{"banan", "alma", "çiyələk"}
    sort.Strings(sozler)
    fmt.Println("Sıralanmış:", sozler)

    // Xüsusi sıralama
    sort.Slice(ededler, func(i, j int) bool {
        return ededler[i] > ededler[j] // azalan
    })
    fmt.Println("Azalan:", ededler)

    // time
    indi := time.Now()
    fmt.Println("İndi:", indi.Format("2006-01-02 15:04:05"))
    sonra := indi.Add(2 * time.Hour)
    fmt.Println("2 saat sonra:", sonra.Format("15:04"))
    fmt.Println("Fərq:", sonra.Sub(indi))

    // rand — təsadüfi
    fmt.Println("Təsadüfi 0-99:", rand.Intn(100))
}
```

### Nümunə 2: encoding/json — JSON işləmə

```go
package main

import (
    "encoding/json"
    "fmt"
)

type User struct {
    ID    int64  `json:"id"`
    Name  string `json:"name"`
    Email string `json:"email,omitempty"`
    aktiv bool   // export edilmir — JSON-a düşmür
}

func main() {
    // Struct → JSON
    u := User{ID: 1, Name: "Orkhan", Email: "orkhan@example.com"}
    data, err := json.Marshal(u)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Println("JSON:", string(data))

    // Gözəl formatlanmış JSON
    pretty, _ := json.MarshalIndent(u, "", "  ")
    fmt.Println(string(pretty))

    // JSON → Struct
    jsonStr := `{"id":2,"name":"Eli"}`
    var u2 User
    if err := json.Unmarshal([]byte(jsonStr), &u2); err != nil {
        fmt.Println("Parse xətası:", err)
        return
    }
    fmt.Printf("Unmarshalled: ID=%d, Name=%s\n", u2.ID, u2.Name)

    // JSON → map[string]interface{} (dinamik)
    var dinamik map[string]interface{}
    json.Unmarshal([]byte(`{"key":"val","num":42}`), &dinamik)
    fmt.Println("Key:", dinamik["key"])
    fmt.Println("Num:", dinamik["num"])
}
```

### Nümunə 3: Modul strukturu — paket yaratmaq

```go
// Modul strukturu nümunəsi:
//
// myapp/
//   go.mod
//   main.go
//   internal/
//     user/
//       user.go    (package user)
//     product/
//       product.go (package product)

// go.mod:
// module github.com/username/myapp
// go 1.21

// internal/user/user.go:
package main // bu nümunədə main istifadə edirik

import "fmt"

// Export — böyük hərflə; xarici paketdən görünür
type UserInfo struct {
    ID   int
    Name string
}

// Export edilmiş funksiya
func NewUserInfo(id int, name string) UserInfo {
    return UserInfo{ID: id, Name: name}
}

// private — yalnız bu paket daxilindən görünür
func validate(name string) bool {
    return name != ""
}

func main() {
    // Öz paketimizin funksiyası
    u := NewUserInfo(1, "Orkhan")
    fmt.Printf("User: %+v\n", u)

    // Xarici paket istifadəsi:
    // go get github.com/gin-gonic/gin
    // import "github.com/gin-gonic/gin"
    // r := gin.Default()

    // Blank import — driver qeydiyyat üçün
    // import _ "github.com/lib/pq"  // PostgreSQL driver

    // Alias — ad konflikti
    // import mrand "math/rand"
    // import crand "crypto/rand"
}
```

## Praktik Tapşırıqlar

1. **Layihə strukturu**: Yeni Go modulu yarat (`go mod init`). `internal/user`, `internal/product` paketlərini yarat. Hər paketdə export edilmiş struct + constructor yaz. `main.go`-da hər iki paketi import et.

2. **sort.Slice custom**: `[]Product` — `Price` sahəsinə görə artan, sonra `Name`-ə görə əlifba sırası ilə sıralayan sort yaz. `sort.Slice` + closure istifadə et.

3. **time əməliyyatları**: `time.Now()` ilə indiki vaxtı al. `time.Parse` ilə `"2024-01-15"` stringini parse et. İki tarix arasındakı günü hesabla. `time.Format` ilə müxtəlif formatlarda göstər.

4. **Mini CLI**: `os.Args` ilə command-line arqumentlər al. `command`, `--flag=value` formatını parse et. `strings.HasPrefix("--")` ilə flag-ları ayır. `fmt.Printf` ilə nəticəni formatla.

## PHP ilə Müqayisə

| PHP | Go |
|-----|-----|
| `composer.json` | `go.mod` |
| `composer.lock` | `go.sum` |
| `vendor/` | `$GOPATH/pkg/mod/` |
| `composer require vendor/package` | `go get github.com/vendor/package` |
| `use App\Models\User;` | `import "myapp/models"` (fayl yolu ilə) |
| `composer install` | `go mod download` |
| `public`/`private` (class level) | Böyük/kiçik hərflə (paket level) |

- PHP-də `public`/`private` class level; Go-da export paket level — ad böyük/kiçik hərflə müəyyənlənir
- PHP-də `use` ilə class import; Go-da `import` ilə paket import (fayl deyil, paket)
- PHP-dəki namespace → Go-da paket adı
- Go standart kitabxanası PHP-nin `composer require` etdiyi çox şeyi built-in edir

## Əlaqəli Mövzular

- [01-introduction.md](01-introduction.md) — Go-ya giriş
- [07-functions.md](07-functions.md) — funksiyalar
- [22-init-and-modules.md](22-init-and-modules.md) — `init` funksiyası və modullar dərindən
- [39-environment-and-config.md](39-environment-and-config.md) — konfiqurasiya idarəsi
- [54-project-structure.md](54-project-structure.md) — layihə strukturu
