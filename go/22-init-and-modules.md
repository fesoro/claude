# Init funksiyası və Go Modules (Middle)

## İcmal

Go-da `init()` funksiyası proqram başlayarkən `main()`-dən əvvəl avtomatik işləyir — paket səviyyəsindəki dəyişkənləri hazırlamaq, bağlantı yoxlamaq, registrasiya əməliyyatları üçün nəzərdə tutulub. Go Modules isə asılılıq idarəetmə sistemidir: `go.mod` faylı modulu, `go.sum` isə kriptqrafik hash-ləri saxlayır. PHP-nin Composer-i ilə eyni rol oynayır, lakin module versioning daha ciddi tətbiq edilir.

## Niyə Vacibdir

Böyük layihələrdə paket inisializasiyası, database pool qurulması, konfiqürasiya yüklənməsi — bunların hamısı `main()`-dən əvvəl olmalıdır. Go Modules olmadan third-party kitabxana istifadəsi mümkün deyil; dependency versioning, `vendor` modu, `go.sum` integrity yoxlaması production deploymentda kritik əhəmiyyət daşıyır.

## Əsas Anlayışlar

- **`init()` xüsusiyyətləri**: parametr almır, dəyər qaytarmır, bir faylda bir neçəsi ola bilər
- **İcra sırası**: import edilmiş paketlərin `init()` → paket dəyişkənləri → `init()` → `main()`
- **Import sırası**: A, B-ni import edirsə, əvvəl B-nin `init()` işləyir
- **`_` import**: `import _ "pkg"` — yalnız `init()`-i işlətmək üçün, paket özü istifadə edilmədən
- **`go.mod`**: modul adı, Go versiyası, `require` asılılıqları
- **`go.sum`**: hər asılılığın kriptqrafik hash-i — tamper-proof
- **`go mod tidy`**: lazımsız asılılıqları silir, çatışanları əlavə edir
- **`go mod vendor`**: asılılıqları `vendor/` qovluğuna kopyalayır — offline build üçün
- **Export qaydası**: böyük hərf — `Public`, kiçik hərf — `private` (paket daxili)
- **Semantic versioning**: `v1.2.3` — major.minor.patch; `v2+` major versiyada import yolu dəyişir

## Praktik Baxış

**Real istifadə ssenariləri:**
- `init()` ilə database driver qeydiyyatı (`_ "github.com/lib/pq"`)
- `init()` ilə konfiqürasiya fayl oxuma və validation
- `init()` ilə global logger qurmaq
- Go Modules ilə team-in eyni versiyanı istifadə etməsini təmin etmək

**Trade-off-lar:**
- `init()` çox istifadə edilməməlidir — test etmək çətindir; əvəzinə explicit initialization function yazın
- Global state init-də qurulmağa çalışılır — dependency injection daha yaxşı alternativdir
- `vendor/` modu — reproducible build təmin edir, lakin repo ölçüsünü artırır
- `go.sum` Git-ə commit edilməlidir — security üçün vacibdir

**Ümumi səhvlər:**
- `init()` içindəki `panic` proqramı çökdürür — xəta idarəetməsi etmək olmur
- Çox sayda `init()` call sırası — anlaşılmaz initialization flow
- `go.sum`-u `.gitignore`-a əlavə etmək — security riski
- Major versiya `v2+` import yolunu dəyişdiyini bilməmək (`github.com/foo/bar/v2`)

**PHP ilə fərqi:**

| PHP (Composer) | Go (Modules) |
|----------------|--------------|
| `composer.json` | `go.mod` |
| `composer.lock` | `go.sum` |
| `composer install` | `go mod download` |
| `composer require pkg` | `go get pkg` |
| `composer dump-autoload` | — (Go-da avtomatik) |
| `vendor/` qovluğu | `vendor/` qovluğu (opsional) |
| `__construct()` | `init()` + explicit init funksiyası |

## Nümunələr

### Nümunə 1: init() — icra sırası

```go
package main

import "fmt"

// Paket səviyyəsi dəyişkən — init()-dən əvvəl işləyir
var appVersion = "1.0.0"

var db string // init()-də hazırlanacaq

// Birinci init()
func init() {
    db = "postgresql://localhost:5432/myapp"
    fmt.Println("init() #1: DB bağlantısı hazırlandı")
}

// İkinci init() — eyni faylda bir neçəsi mümkündür
func init() {
    fmt.Println("init() #2: Cache quruldu")
    fmt.Println("DB:", db) // artıq hazırdır
}

func main() {
    fmt.Println("main() işlədi")
    fmt.Println("Versiya:", appVersion)
    fmt.Println("DB:", db)
}

// Çıxış sırası:
// init() #1: DB bağlantısı hazırlandı
// init() #2: Cache quruldu
// DB: postgresql://localhost:5432/myapp
// main() işlədi
```

### Nümunə 2: `_` import — yalnız init() üçün

```go
package main

import (
    "database/sql"
    "fmt"
    _ "github.com/lib/pq" // PostgreSQL driver qeydiyyatı üçün
    // _ ilə import — paket istifadə edilmir, lakin init() işləyir
    // pq.init() sql.Register("postgres", &pq.Driver{}) çağırır
)

func main() {
    // driver artıq qeydiyyatdadır
    db, err := sql.Open("postgres", "postgresql://localhost/myapp")
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    defer db.Close()
    fmt.Println("DB bağlantısı açıldı:", db != nil)
}
```

### Nümunə 3: init() vs explicit initialization funksiyası

```go
package main

import (
    "fmt"
    "os"
)

// YANLIŞ yol — init()-də gizli initialization
var config map[string]string

func init() {
    // init içindəki xəta idarə edilə bilmir!
    config = map[string]string{
        "db_url": os.Getenv("DATABASE_URL"),
        "port":   os.Getenv("PORT"),
    }
    // Xəta baş versə panic atmalıyıq — bu pis davranışdır
}

// DUZGUN yol — explicit funksiya
type Config struct {
    DBUrl string
    Port  string
}

func NewConfig() (*Config, error) {
    dbUrl := os.Getenv("DATABASE_URL")
    if dbUrl == "" {
        return nil, fmt.Errorf("DATABASE_URL mühit dəyişkəni tələb olunur")
    }
    port := os.Getenv("PORT")
    if port == "" {
        port = "8080" // default
    }
    return &Config{DBUrl: dbUrl, Port: port}, nil
}

func main() {
    // Explicit initialization — xəta idarə edilə bilər
    cfg, err := NewConfig()
    if err != nil {
        fmt.Println("Konfiqürasiya xətası:", err)
        os.Exit(1)
    }
    fmt.Printf("Başlanır: port=%s\n", cfg.Port)
}
```

### Nümunə 4: Go Modules — əsas əmrlər

```
# 1. Yeni modul yarat
go mod init github.com/username/myapp

# Bu go.mod faylını yaradır:
# -----------------------------------
# module github.com/username/myapp
#
# go 1.21
# -----------------------------------

# 2. Paket əlavə et
go get github.com/gin-gonic/gin              # ən son versiya
go get github.com/gin-gonic/gin@v1.9.1       # konkret versiya
go get github.com/gin-gonic/gin@latest        # ən son stable

# 3. go.mod sonrası görünür:
# require (
#     github.com/gin-gonic/gin v1.9.1
#     github.com/lib/pq v1.10.9
# )

# 4. Lazımsız asılılıqları sil
go mod tidy

# 5. Paketləri yoxla
go mod verify

# 6. Vendor modu
go mod vendor                  # vendor/ qovluğuna kopyala
go build -mod=vendor           # vendor-dan build et

# 7. Asılılıq qrafiki
go mod graph
go list -m all                 # bütün asılılıqlar
go mod why github.com/foo/bar  # bu paket niyə lazımdır?
```

### Nümunə 5: Go layihə strukturu

```
myapp/
├── go.mod              # modul tərifnaməsi
├── go.sum              # kriptqrafik hash-lər (commit edin!)
├── main.go             # package main
├── cmd/
│   └── server/
│       └── main.go     # servis entry point
├── internal/           # yalnız bu modul istifadə edə bilər
│   ├── handler/
│   │   └── user.go     # package handler
│   ├── service/
│   │   └── user.go     # package service
│   └── repository/
│       └── user.go     # package repository
├── pkg/                # xarici paketlər istifadə edə bilər
│   └── utils/
│       └── helper.go   # package utils
└── vendor/             # go mod vendor ilə (opsional)
```

```go
// internal/handler/user.go
package handler

import (
    "fmt"
    "github.com/username/myapp/internal/service" // öz paket
)

type UserHandler struct {
    svc *service.UserService
}

func New(svc *service.UserService) *UserHandler {
    return &UserHandler{svc: svc}
}

// Export: böyük hərf — digər paketlər istifadə edə bilər
func (h *UserHandler) GetUser(id int) {
    user, err := h.svc.Find(id)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Println("İstifadəçi:", user)
}

// unexported: kiçik hərf — yalnız bu paketdə
func (h *UserHandler) validate(id int) bool {
    return id > 0
}
```

### Nümunə 6: Major versiya — v2+

```go
// go.mod
module github.com/username/mylib/v2

go 1.21

// import yolu dəyişir — v2 suffix əlavə olunur
import "github.com/username/mylib/v2/utils"

// v1 vs v2 eyni anda istifadə (nadirdir, lakin mümkündür):
import (
    libv1 "github.com/username/mylib/utils"
    libv2 "github.com/username/mylib/v2/utils"
)
```

## Praktik Tapşırıqlar

1. **Konfiq init pattern:** `internal/config` paketi yarat. `Config` struct-ı yarat. `init()` əvəzinə `Load() (*Config, error)` funksiyası yaz — mühit dəyişkənlərindən və `.env` faylından oxusun. `main()`-də explicit çağır.

2. **Database driver registration:** `database/sql` + `_ "github.com/lib/pq"` ilə PostgreSQL bağlantısı quran `NewDB(url string) (*sql.DB, error)` funksiyası yaz. `db.Ping()` ilə bağlantını yoxla.

3. **Modul layihəsi:** Sıfırdan Go modul layihəsi qur. `go mod init`, `go get github.com/gin-gonic/gin`, `go mod tidy` əmrlərini icra et. `internal/` və `pkg/` strukturunu qur.

4. **Init sıra testi:** A, B, C paketlərini yarat. A, B-ni; B, C-ni import etsin. Hər paketdə `init()` funksiyası `fmt.Println` ilə paket adını çap etsin. İcra sırasını müşahidə et.

5. **Vendor mode CI:** `go mod vendor` ilə vendor qovluğunu yarat. `go build -mod=vendor` ilə build et. Offline mühitdə (internet yox) build işlədiyini sübut et.

## Əlaqəli Mövzular

- [14-packages-and-modules.md](14-packages-and-modules.md) — paket əsasları, import qaydaları
- [18-error-handling.md](18-error-handling.md) — init() içindəki xəta idarəetməsi
- [39-environment-and-config.md](39-environment-and-config.md) — konfiqürasiya idarəetməsi
- [54-project-structure.md](54-project-structure.md) — Go layihə strukturu ətraflı
- [37-database.md](37-database.md) — database driver qeydiyyatı (`_ import`)
