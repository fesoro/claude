# Enums (Middle)

## İcmal

Go-da `enum` açar sözü yoxdur. Əvəzinə `iota` sabitləri, xüsusi tip (named type) və metodların kombinasiyası ilə enum emulasiya olunur. Bu yanaşma daha çevikdir: enum-lara metodlar əlavə etmək, JSON serialize/deserialize, bit-flag kombinasiyalar, string-based enum-lar — hamısı eyni pattern-lər çərçivəsindədir. PHP 8.1-in `enum` açar sözündən fərqli olaraq, Go-da enum daha çox "convention" üzərindədir.

## Niyə Vacibdir

Sifariş statusu, istifadəçi rolu, ödəniş növü, bildiriş tipi — real layihələrdə sabit dəyər toplumları daima lazımdır. Go-da enum-ların JSON-la işləmə, string göstərimi, keçərsiz dəyərlərin bloklanması düzgün qurulmazsa data corruption baş verə bilər. `iota` + custom type + `String()` metodu Go-nun idiomatik enum pattern-idir.

## Əsas Anlayışlar

- **`iota`** — `const` blokunun içindəki sıra rəqəmi (0-dan başlayır, hər sətirdə artır)
- **Named type** — `type Status int` — yeni tip yaranır; `int` ilə eyni deyil, compiler fərqləndirir
- **`String()` metodu** — `fmt.Stringer` interface-ni implement edir; `fmt.Println` avtomatik çağırır
- **Sentinel enum** — sıfır dəyər mənalı olmamalıdırsa `iota + 1` ilə başlayın
- **String enum** — `type Reng string; const Qirmizi Reng = "qirmizi"` — value-based
- **Bit flags** — `1 << iota` — bir anda çox dəyər ifadə etmək üçün (UNIX permission kimi)
- **`MarshalJSON`/`UnmarshalJSON`** — JSON-da rəqəm əvəzinə mətn istifadəsi üçün
- **Exhaustive switch** — Go compiler `switch` üzərindəki bütün case-ləri yoxlamır; `default` yazın

## Praktik Baxış

**Real istifadə ssenariləri:**
- Sifariş statusu: `Pending → Processing → Shipped → Delivered → Cancelled`
- İstifadəçi rolu: `Guest | User | Admin | SuperAdmin`
- Ödəniş metodu: `"cash" | "card" | "transfer"` (string enum)
- File permission: `Read | Write | Execute` (bit flags)

**Trade-off-lar:**
- `iota` enum — rəqəm dəyəri; DB-yə rəqəm yazılır; sıra dəyişsə köhnə datalara uyğunsuzluq
- String enum — daha oxunaqlı, DB-yə mətn yazılır; migration problemi yoxdur
- Bit flags — kompakt, lakin debugging çətinləşir; kiçik permission sistemi üçün yaxşıdır
- PHP 8.1 enum-dan fərqli olaraq Go-da keçərsiz dəyər qarşısı compile-time alına bilmir

**Ümumi səhvlər:**
- `iota` sıralamasını dəyişmək — mövcud DB datalara uyğunsuzluq
- `String()` metodu olmadan `%d` əvəzinə `%s` ilə çap etmək — rəqəm çıxır
- `0` dəyərli enum-u mənalı dəyər kimi istifadə etmək — sıfır dəyər default olduğu üçün anlamı dəyişir
- JSON unmarshal zamanı keçərsiz dəyər yoxlamamaq

**PHP ilə fərqi:**

| PHP | Go |
|-----|-----|
| `enum Status: string { case Active = 'active'; }` | `type Status string; const StatusActive Status = "active"` |
| `Status::Active->value` | `string(StatusActive)` |
| `Status::from('active')` — built-in | Əl ilə map ilə lookup |
| `Status::cases()` — bütün case-lər | Əl ilə slice |
| Compiler keçərsiz case bloklar | Runtime yoxlama lazımdır |

## Nümunələr

### Nümunə 1: İota ilə sadə enum — String() metodu

```go
package main

import "fmt"

type OrderStatus int

const (
    StatusPending   OrderStatus = iota + 1 // 1 — 0-ı "yoxdur" üçün saxlayırıq
    StatusProcessing                         // 2
    StatusShipped                            // 3
    StatusDelivered                          // 4
    StatusCancelled                          // 5
)

func (s OrderStatus) String() string {
    switch s {
    case StatusPending:
        return "gözlənilir"
    case StatusProcessing:
        return "emal edilir"
    case StatusShipped:
        return "yola salınıb"
    case StatusDelivered:
        return "çatdırılıb"
    case StatusCancelled:
        return "ləğv edilib"
    default:
        return fmt.Sprintf("naməlum(%d)", int(s))
    }
}

func (s OrderStatus) IsTerminal() bool {
    return s == StatusDelivered || s == StatusCancelled
}

func main() {
    status := StatusProcessing
    fmt.Println("Status:", status)        // emal edilir
    fmt.Println("Dəyər:", int(status))    // 2
    fmt.Println("Terminal?", status.IsTerminal()) // false

    status2 := StatusDelivered
    fmt.Println("Terminal?", status2.IsTerminal()) // true

    // Switch ilə istifadə
    switch status {
    case StatusPending, StatusProcessing:
        fmt.Println("Aktiv sifariş")
    case StatusDelivered:
        fmt.Println("Tamamlandı")
    case StatusCancelled:
        fmt.Println("Ləğv edildi")
    }
}
```

### Nümunə 2: String enum — daha oxunaqlı

```go
package main

import (
    "fmt"
)

type PaymentMethod string

const (
    PaymentCash     PaymentMethod = "cash"
    PaymentCard     PaymentMethod = "card"
    PaymentTransfer PaymentMethod = "transfer"
    PaymentCrypto   PaymentMethod = "crypto"
)

func (p PaymentMethod) IsValid() bool {
    switch p {
    case PaymentCash, PaymentCard, PaymentTransfer, PaymentCrypto:
        return true
    }
    return false
}

func (p PaymentMethod) Label() string {
    switch p {
    case PaymentCash:
        return "Nağd pul"
    case PaymentCard:
        return "Bank kartı"
    case PaymentTransfer:
        return "Bank köçürməsi"
    case PaymentCrypto:
        return "Kriptovalyuta"
    default:
        return "Naməlum"
    }
}

func main() {
    metod := PaymentCard
    fmt.Println("Metod:", metod)           // card
    fmt.Println("Etiket:", metod.Label())  // Bank kartı
    fmt.Println("Keçərli?", metod.IsValid()) // true

    yanlis := PaymentMethod("bitcoin") // string-dən yaratmaq olar
    fmt.Println("Keçərli?", yanlis.IsValid()) // false
}
```

### Nümunə 3: Bit flag enum — kombinasiya icazələr

```go
package main

import (
    "fmt"
    "strings"
)

type Permission int

const (
    PermRead    Permission = 1 << iota // 1  (001)
    PermWrite                           // 2  (010)
    PermDelete                          // 4  (100)
    PermAdmin   = PermRead | PermWrite | PermDelete // 7 (111)
)

func (p Permission) String() string {
    var parts []string
    if p&PermRead != 0 {
        parts = append(parts, "oxu")
    }
    if p&PermWrite != 0 {
        parts = append(parts, "yaz")
    }
    if p&PermDelete != 0 {
        parts = append(parts, "sil")
    }
    if len(parts) == 0 {
        return "icazə yoxdur"
    }
    return strings.Join(parts, "|")
}

func (p Permission) Has(perm Permission) bool {
    return p&perm != 0
}

func main() {
    // İstifadəçi icazəsi: oxu + yaz
    userPerm := PermRead | PermWrite
    fmt.Println("İcazələr:", userPerm)          // oxu|yaz
    fmt.Println("Oxu var?", userPerm.Has(PermRead))   // true
    fmt.Println("Sil var?", userPerm.Has(PermDelete))  // false

    // İcazə əlavə et
    userPerm |= PermDelete
    fmt.Println("Sonra:", userPerm)             // oxu|yaz|sil

    // İcazə sil
    userPerm &^= PermWrite
    fmt.Println("Write silindi:", userPerm)     // oxu|sil

    // Admin bütün icazələrə sahib
    adminPerm := PermAdmin
    fmt.Println("Admin:", adminPerm)            // oxu|yaz|sil
    fmt.Println("Admin oxu?", adminPerm.Has(PermRead)) // true
}
```

### Nümunə 4: JSON ilə işləyən enum

```go
package main

import (
    "encoding/json"
    "fmt"
)

type UserRole int

const (
    RoleGuest UserRole = iota
    RoleUser
    RoleAdmin
    RoleSuperAdmin
)

var roleToStr = map[UserRole]string{
    RoleGuest:      "guest",
    RoleUser:       "user",
    RoleAdmin:      "admin",
    RoleSuperAdmin: "super_admin",
}

var strToRole = map[string]UserRole{
    "guest":       RoleGuest,
    "user":        RoleUser,
    "admin":       RoleAdmin,
    "super_admin": RoleSuperAdmin,
}

func (r UserRole) MarshalJSON() ([]byte, error) {
    s, ok := roleToStr[r]
    if !ok {
        return nil, fmt.Errorf("naməlum rol: %d", r)
    }
    return json.Marshal(s)
}

func (r *UserRole) UnmarshalJSON(data []byte) error {
    var s string
    if err := json.Unmarshal(data, &s); err != nil {
        return err
    }
    val, ok := strToRole[s]
    if !ok {
        return fmt.Errorf("naməlum rol: %q", s)
    }
    *r = val
    return nil
}

func (r UserRole) String() string {
    if s, ok := roleToStr[r]; ok {
        return s
    }
    return "naməlum"
}

type User struct {
    Ad  string   `json:"ad"`
    Rol UserRole `json:"rol"`
}

func main() {
    user := User{Ad: "Orkhan", Rol: RoleAdmin}
    data, _ := json.MarshalIndent(user, "", "  ")
    fmt.Println(string(data))
    // {"ad":"Orkhan","rol":"admin"} — rəqəm deyil, mətn!

    var u2 User
    json.Unmarshal([]byte(`{"ad":"Aysel","rol":"super_admin"}`), &u2)
    fmt.Printf("Ad: %s, Rol: %s (%d)\n", u2.Ad, u2.Rol, int(u2.Rol))
    // Ad: Aysel, Rol: super_admin (3)

    // Keçərsiz dəyər yoxlaması
    var u3 User
    err := json.Unmarshal([]byte(`{"ad":"Test","rol":"hacker"}`), &u3)
    if err != nil {
        fmt.Println("Xəta:", err) // naməlum rol: "hacker"
    }
}
```

### Nümunə 5: Enum state machine

```go
package main

import (
    "errors"
    "fmt"
)

type InvoiceStatus int

const (
    InvoiceDraft   InvoiceStatus = iota + 1
    InvoicePending
    InvoicePaid
    InvoiceOverdue
    InvoiceCancelled
)

var validTransitions = map[InvoiceStatus][]InvoiceStatus{
    InvoiceDraft:   {InvoicePending, InvoiceCancelled},
    InvoicePending: {InvoicePaid, InvoiceOverdue, InvoiceCancelled},
    InvoiceOverdue: {InvoicePaid, InvoiceCancelled},
    // Paid və Cancelled terminal — keçid yoxdur
}

func (s InvoiceStatus) CanTransitionTo(next InvoiceStatus) bool {
    allowed := validTransitions[s]
    for _, a := range allowed {
        if a == next {
            return true
        }
    }
    return false
}

type Invoice struct {
    ID     int
    Status InvoiceStatus
}

func (inv *Invoice) SetStatus(next InvoiceStatus) error {
    if !inv.Status.CanTransitionTo(next) {
        return fmt.Errorf("keçid mümkün deyil: %d → %d", inv.Status, next)
    }
    inv.Status = next
    return nil
}

func main() {
    inv := &Invoice{ID: 1, Status: InvoiceDraft}

    if err := inv.SetStatus(InvoicePending); err != nil {
        fmt.Println("Xəta:", err)
    } else {
        fmt.Printf("Status dəyişdi: %d\n", inv.Status) // 2
    }

    if err := inv.SetStatus(InvoiceCancelled); err != nil {
        fmt.Println("Xəta:", err)
    } else {
        fmt.Printf("Status: %d\n", inv.Status)
    }

    // Yanlış keçid
    err := inv.SetStatus(InvoicePaid) // Cancelled-dən Paid-ə keçmək olmaz
    fmt.Println("Xəta:", errors.Is(err, nil)) // false
    fmt.Println("Mesaj:", err)
}
```

## Praktik Tapşırıqlar

1. **E-commerce status sistemi:** `ProductStatus` enum yarat (Draft, Active, OutOfStock, Discontinued). JSON support, `String()` metodu, `IsAvailable() bool` metodu olsun. DB-yə string kimi yazılsın.

2. **Role-based access:** `Role` bit flag enum yarat. `CanRead`, `CanWrite`, `CanDelete`, `CanManageUsers` icazələrini bit flag kimi qur. `HasPermission(p Permission) bool` metodu yaz.

3. **Enum validator:** HTTP request body-dən gələn `status` string sahəsini `OrderStatus` enum-a çevir. Keçərsiz dəyər olarsa `ValidationError` ilə 400 qaytarsın.

4. **State machine:** Sifariş üçün `OrderStatus` state machine yarat. `CanTransitionTo` metodu ilə keçid qaydaları tətbiq et. Yanlış keçiddə `ErrInvalidTransition` qaytarsın.

5. **Exhaustive switch lint:** Go-da compiler `switch` tam olmadığında xəbərdarlıq etmir. Enum üçün `Default()` metodu yaz — hər switch blokunun `default` case-i bu dəyəri qaytarsın. Bu pattern-i izah et.

## Əlaqəli Mövzular

- [20-json-encoding.md](20-json-encoding.md) — enum-ların JSON serialize edilməsi
- [17-interfaces.md](17-interfaces.md) — `fmt.Stringer` interface və `String()` metodu
- [18-error-handling.md](18-error-handling.md) — keçərsiz enum dəyəri xəta qaytarması
- [10-structs.md](10-structs.md) — enum-ların struct sahəsi kimi istifadəsi
- [37-database.md](37-database.md) — enum-ları DB-yə yazmaq/oxumaq
