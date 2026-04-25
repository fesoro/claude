# Struct — İrəliləmiş (Senior)

## İcmal

Go-da struct sadə data container-dən çox daha güclüdür. Function field-lar, embedded struct-lar, method promotion, memory layout optimallaşdırması — bunlar Senior Go developer-in gündəlik işlərinin bir hissəsidir. Bu mövzu struct-ların real layihələrdə düzgün istifadəsini əhatə edir.

## Niyə Vacibdir

- **Function field-lar** callback pattern-lər üçün, pluggable behavior üçün istifadə olunur
- **Embedded struct** Go-da composition-un əsas mexanizmidir (inheritance yoxdur)
- **Method promotion** kod təkrarını azaldır, interface implementasiyanı asanlaşdırır
- **Struct tags** JSON serialization, DB mapping, validation üçün vacibdir
- **Memory layout** yüksək yüklü sistemlərdə performansa birbaşa təsir edir

## Əsas Anlayışlar

### Function Field (Funksiya sahəsi)

Struct-ın field-i funksiya tipi ola bilər. Bu sayədə struct-ın davranışını runtime-da dəyişmək mümkündür:

```go
type Button struct {
    Label   string
    OnClick func()
    OnHover func(x, y int)
    Validate func(string) bool
}
```

`nil` yoxlaması məcburidir — nil function call `panic` törədir.

### Struct Tags

Struct field-lərinin metadata-sını müəyyən edir. Reflection vasitəsilə oxunur:

```go
type User struct {
    ID    int    `json:"id" db:"id" validate:"required"`
    Name  string `json:"name" db:"name" validate:"required,min=2"`
    Email string `json:"email" db:"email" validate:"required,email"`
    Pass  string `json:"-" db:"password_hash"` // JSON-a yazılmır
}
```

**Tez-tez istifadə olunan tag-lar:**
- `json:"field_name,omitempty"` — JSON encode/decode
- `db:"column_name"` — sqlx, gorm üçün DB column mapping
- `validate:"required,min=2,email"` — go-playground/validator
- `yaml:"field"` — YAML konfiqurasiya faylları üçün
- `xml:"element"` — XML marshal/unmarshal

### Embedded Struct (Composition)

PHP-dən fərqli olaraq Go-da inheritance yoxdur. Bunun əvəzinə composition istifadə edilir:

```go
type Animal struct {
    Name string
    Age  int
}

func (a Animal) Sound() string {
    return a.Name + " makes a sound"
}

type Dog struct {
    Animal        // embedded — promotion baş verir
    Breed string
}
```

`Dog` struct-ı `Animal`-ın bütün field-lərini və method-larını "promote" edir. `dog.Name`, `dog.Age`, `dog.Sound()` birbaşa istifadə oluna bilər.

### Method Promotion Qaydaları

1. Embedded struct-ın method-ları outer struct tərəfindən promote olunur
2. Outer struct eyni adlı method yaratsa, promoted method override olunur
3. Konflikt zamanı tam yol istifadə edilməlidir: `dog.Animal.Sound()`
4. Multiple embedding zamanı eyni adlı field/method conflict yaradır — compile xətası

### Memory Layout

Go struct-ları CPU alignment tələblərinə görə field-lərin arasına padding əlavə edə bilər. Bu, struct-ın faktiki ölçüsünə təsir edir:

```go
// Pis: 24 byte (padding var)
type Bad struct {
    A bool   // 1 byte + 7 byte padding
    B float64 // 8 byte
    C bool    // 1 byte + 7 byte padding
}

// Yaxşı: 10 byte (16-a yuvarlaqlaşdırılır, amma daha az padding)
type Good struct {
    B float64 // 8 byte
    A bool    // 1 byte
    C bool    // 1 byte + 6 byte padding
}
```

Böyük ölçülü struct-larda bu optimallaşdırma vacibdir.

## Praktik Baxış

### Real Layihələrdə İstifadə

**Callback pattern (HTTP middleware, event handler):**
```go
type Router struct {
    NotFound    http.HandlerFunc
    ErrorHandler func(w http.ResponseWriter, r *http.Request, err error)
}
```

**Composition ilə base functionality:**
```go
type BaseModel struct {
    ID        int       `db:"id"`
    CreatedAt time.Time `db:"created_at"`
    UpdatedAt time.Time `db:"updated_at"`
}

type User struct {
    BaseModel
    Name  string `db:"name"`
    Email string `db:"email"`
}

type Product struct {
    BaseModel
    Title string `db:"title"`
    Price float64 `db:"price"`
}
```

### Trade-off-lar

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| Embedded struct | Kod təkrarı azalır, method promotion | Gizli dependency, conflict riski |
| Function field | Runtime davranış dəyişikliyi | nil check lazım, test mürəkkəbdir |
| Interface field | Tam abstraksiya | Overhead, nil dereference riski |

### Anti-pattern-lər

```go
// Anti-pattern 1: Çox embedded struct — "diamond problem" riski
type C struct {
    A
    B
    // Əgər A və B-nin eyni adlı field-i varsa — COMPILE XƏTASİ
}

// Anti-pattern 2: nil yoxlaması olmadan function field çağırmaq
btn.OnClick() // PANIC əgər OnClick nil-dirsə

// Düzgün:
if btn.OnClick != nil {
    btn.OnClick()
}

// Anti-pattern 3: Export olunan struct-da private embedded struct
type Handler struct {
    baseHandler // private — xarici paketlər promote olunmuş method-ları görə bilər
                // amma bu davranış qarışıq ola bilər
}
```

### Embedded Interface

Struct interface embed edə bilər — bu partial implementation üçün istifadə olunur:

```go
type MyWriter struct {
    io.Writer // interface embedded
}
// Buna ümumiyyətlə test double-lar yaratmaqda istifadə olunur
```

## Nümunələr

### Nümunə 1: Struct Tags ilə User modeli

```go
package main

import (
    "encoding/json"
    "fmt"
    "time"
)

type User struct {
    ID        int       `json:"id" db:"id"`
    Name      string    `json:"name" db:"name"`
    Email     string    `json:"email" db:"email"`
    Password  string    `json:"-" db:"password_hash"` // JSON-a yazılmır
    IsAdmin   bool      `json:"is_admin,omitempty" db:"is_admin"`
    CreatedAt time.Time `json:"created_at" db:"created_at"`
}

func main() {
    user := User{
        ID:        1,
        Name:      "Orkhan",
        Email:     "orkhan@example.com",
        Password:  "secret123",
        IsAdmin:   false,
        CreatedAt: time.Now(),
    }

    data, _ := json.MarshalIndent(user, "", "  ")
    fmt.Println(string(data))
    // Password görünmür, IsAdmin omitempty (false) olduğu üçün yazılmır
}
```

### Nümunə 2: Composition — BaseModel pattern

```go
package main

import (
    "fmt"
    "time"
)

type BaseModel struct {
    ID        int       `json:"id" db:"id"`
    CreatedAt time.Time `json:"created_at" db:"created_at"`
    UpdatedAt time.Time `json:"updated_at" db:"updated_at"`
}

func (b *BaseModel) Touch() {
    b.UpdatedAt = time.Now()
}

func (b BaseModel) IsNew() bool {
    return b.ID == 0
}

type User struct {
    BaseModel
    Name  string `json:"name" db:"name"`
    Email string `json:"email" db:"email"`
}

type Product struct {
    BaseModel
    Title string  `json:"title" db:"title"`
    Price float64 `json:"price" db:"price"`
}

func main() {
    user := User{
        BaseModel: BaseModel{ID: 1, CreatedAt: time.Now(), UpdatedAt: time.Now()},
        Name:      "Orkhan",
        Email:     "orkhan@example.com",
    }

    fmt.Println("Yeni user?", user.IsNew()) // false
    user.Touch()                             // BaseModel.Touch() promoted
    fmt.Println("UpdatedAt:", user.UpdatedAt.Format(time.RFC3339))

    product := Product{
        Title: "Kitab",
        Price: 25.99,
    }
    fmt.Println("Yeni product?", product.IsNew()) // true
}
```

### Nümunə 3: Function Field ilə Pluggable Handler

```go
package main

import (
    "fmt"
    "log"
    "net/http"
)

type Server struct {
    Addr         string
    NotFound     http.HandlerFunc
    ErrorHandler func(w http.ResponseWriter, r *http.Request, err error)
    Logger       func(format string, args ...interface{})
}

func NewServer(addr string) *Server {
    return &Server{
        Addr: addr,
        NotFound: func(w http.ResponseWriter, r *http.Request) {
            http.Error(w, "Tapılmadı", http.StatusNotFound)
        },
        ErrorHandler: func(w http.ResponseWriter, r *http.Request, err error) {
            log.Printf("Xəta: %v", err)
            http.Error(w, "Daxili xəta", http.StatusInternalServerError)
        },
        Logger: log.Printf,
    }
}

func main() {
    srv := NewServer(":8080")

    // Davranışı override et
    srv.Logger = func(format string, args ...interface{}) {
        fmt.Printf("[CUSTOM] "+format+"\n", args...)
    }

    srv.NotFound = func(w http.ResponseWriter, r *http.Request) {
        w.WriteHeader(http.StatusNotFound)
        fmt.Fprintf(w, `{"error":"not_found","path":"%s"}`, r.URL.Path)
    }

    srv.Logger("Server %s ünvanında işləyir", srv.Addr)
}
```

### Nümunə 4: Memory Layout Optimallaşdırması

```go
package main

import (
    "fmt"
    "unsafe"
)

// Pis layout — artıq padding
type UserBad struct {
    Active   bool    // 1 byte + 7 padding
    Balance  float64 // 8 byte
    Verified bool    // 1 byte + 7 padding
    // Total: 24 byte
}

// Yaxşı layout — minimum padding
type UserGood struct {
    Balance  float64 // 8 byte
    Active   bool    // 1 byte
    Verified bool    // 1 byte + 6 padding
    // Total: 16 byte
}

func main() {
    fmt.Println("UserBad ölçüsü: ", unsafe.Sizeof(UserBad{}))  // 24
    fmt.Println("UserGood ölçüsü:", unsafe.Sizeof(UserGood{})) // 16
    // Milyonlarla record zamanı bu fərq əhəmiyyətlidir
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Audit Trail:**
`BaseModel`-i genişləndirin: `CreatedBy int`, `UpdatedBy int`, `DeletedAt *time.Time` əlavə edin. Soft delete üçün `IsDeleted() bool` method-u yazın.

**Tapşırıq 2 — HTTP Client konfiqurasiyası:**
Aşağıdakı struct-ı yazın və validation tag-ları əlavə edin:
```go
type ClientConfig struct {
    BaseURL    string
    Timeout    time.Duration
    MaxRetries int
    Headers    map[string]string
}
```
`json:"..."` və `validate:"required,url"` tag-larını düzgün yerləşdirin.

**Tapşırıq 3 — Event System:**
`EventBus` struct-ı yazın. Field kimi `handlers map[string][]func(data interface{})` saxlayın. `Subscribe(event string, fn func(interface{}))` və `Emit(event string, data interface{})` method-ları əlavə edin.

**Tapşırıq 4 — Layout analizi:**
`unsafe.Sizeof` istifadə edərək 5 müxtəlif struct-ın ölçüsünü hesablayın. Field-ləri yenidən sıralayıb ölçünü kiçildin.

## Əlaqəli Mövzular

- [10-structs](10-structs.md) — Structs əsasları
- [11-pointers](11-pointers.md) — Pointer əsasları
- [17-interfaces](17-interfaces.md) — Interface ilə composition
- [43-pointers-advanced](43-pointers-advanced.md) — Pointer receiver vs value receiver
- [55-repository-pattern](55-repository-pattern.md) — Struct-ların real arxitekturada istifadəsi
- [59-design-patterns](59-design-patterns.md) — Design pattern-lər
