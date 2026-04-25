# Pointer-lər (Junior)

## İcmal

Pointer — dəyişkenin yaddaş ünvanını saxlayan dəyişkendir. `&` operatoru dəyişkenin ünvanını alır, `*` operatoru isə həmin ünvandakı dəyəri oxuyur/dəyişdirir. PHP developer üçün pointer əvvəlcə anlaşılmaz görünə bilər — PHP-də pointer yoxdur, obyektlər avtomatik reference kimi ötürülür. Go-da isə struct-lar default dəyər kimi ötürülür — dəyişdirmək üçün pointer lazımdır.

## Niyə Vacibdir

Pointer-ləri başa düşmək olmadan Go-da struct metodlarını düzgün yazmaq mümkün deyil. Pointer receiver vs value receiver fərqi, `nil` pointer-lərin idarəsi, böyük struct-ları kopyalamaqdan qaçınmaq — bunların hamısı real backend kodunda rast gəlinən məsələlərdir. Bundan əlavə, Go-da nullable dəyərlər üçün pointer istifadə olunur: `*int` nil ola bilər, `int` isə yox.

## Əsas Anlayışlar

- **`&`** — ünvan alma operatoru; `p := &x` → `p` dəyişkenin ünvanını saxlayır
- **`*`** — dereference operatoru; `*p` → `p`-nin göstərdiyi dəyər
- **Pointer tipi** — `*int`, `*string`, `*User` — int-ə, string-ə, User-ə pointer
- **`nil` pointer** — heç yerə göstərmir; dereference etmək panic verir
- **`new(T)`** — `T` tipində zero value yaradan, `*T` qaytaran funksiya; `&T{}` ilə eyni
- **Struct pointer** — `p.Field` Go tərəfindən avtomatik `(*p).Field`-ə çevrilir
- **Pointer receiver** — `func (u *User) Method()` — struct-u dəyişdirir
- **Value receiver** — `func (u User) Method()` — kopya üzərindədir, dəyişmir
- **Slice, map, channel** — artıq reference tipidir; pointer lazım deyil
- **Pointer arifmetikası** — Go-da yoxdur (C-dən fərqli)

## Praktik Baxış

**Real layihədə istifadə:**
- Struct metodlarında dəyişiklik: `func (u *User) UpdateEmail(email string)` — pointer receiver
- Nullable dəyərlər: `Name *string` — JSON-da `null` ola bilər
- Böyük struct-ları kopyalamaqdan qaçmaq: `func process(u *User)` — kopya yaratmır
- Constructor return: `func NewService(...) *Service` — pointer qaytarır

**PHP ilə fərqi:**
- PHP obyektlər həmişə reference kimi ötürülür: `function f(User $u) { $u->name = "..."; }` — orijinalı dəyişdirir
- Go-da struct value kimi ötürülür: `func f(u User)` — kopya; dəyişdirmək üçün `func f(u *User)` lazımdır
- PHP-də `null` hər tip üçün mümkündür; Go-da yalnız pointer, slice, map, interface `nil` ola bilər
- PHP-də pointer yoxdur; Go-da explicit `&` və `*` istifadə olunur

**Trade-off-lar:**
- Pointer: orijinalı dəyişdirmək üçün; amma `nil` yoxlaması lazım olur
- Value: daha təhlükəsiz (nil panic yoxdur), amma böyük struct-lar üçün yavaş (kopya)
- Ümumilikdə: struct metodları üçün pointer receiver; kiçik primitiv tiplər üçün value

**Common mistakes:**
- Nil pointer dereference: `var u *User; fmt.Println(u.Name)` → panic; həmişə nil yoxla
- `&` unutmaq: `func f(u User)` — kopya; dəyişdirmək istəyirsiniz, amma işləmir
- Map/slice üçün pointer istifadə: lazımsızdır, bunlar artıq reference tipidir

## Nümunələr

### Nümunə 1: Pointer əsasları

```go
package main

import "fmt"

func main() {
    x := 42

    // & ilə ünvan al
    p := &x
    fmt.Println("x dəyəri:", x)    // 42
    fmt.Println("x ünvanı:", p)    // 0xc000... (yaddaş adresi)
    fmt.Println("*p dəyəri:", *p)  // 42

    // * ilə dəyər dəyiş
    *p = 100
    fmt.Println("x indi:", x) // 100 — pointer vasitəsilə dəyişdi!

    // Pointer tipi
    var a int = 10
    var ptr *int = &a
    fmt.Printf("Tip: %T, Dəyər: %v\n", ptr, *ptr)

    // nil pointer
    var nilPtr *int
    fmt.Println("Nil pointer:", nilPtr) // <nil>
    if nilPtr != nil {
        fmt.Println(*nilPtr) // bu icra olmaz
    }
    // *nilPtr  // PANIC! nil pointer dereference
}
```

### Nümunə 2: Funksiyalarda pointer — value vs pointer semantikası

```go
package main

import "fmt"

// VALUE parametr — kopya ilə işləyir, orijinalı dəyişdirmir
func ikiArtirValue(n int) {
    n += 2 // yalnız KOPYAnı dəyişir
}

// POINTER parametr — orijinalı dəyişdirir
func ikiArtirPointer(n *int) {
    *n += 2 // orijinal dəyişkəni dəyişir
}

type User struct {
    Name string
    Age  int
}

// Value receiver — kopya; dəyişmir
func (u User) Info() string {
    return fmt.Sprintf("%s, %d yaş", u.Name, u.Age)
}

// Pointer receiver — orijinalı dəyişdirir
func (u *User) BirthYearSet(year int) {
    u.Age = 2024 - year
}

func main() {
    n := 10

    ikiArtirValue(n)
    fmt.Println("Value sonrası:", n) // 10 — dəyişmədi

    ikiArtirPointer(&n)
    fmt.Println("Pointer sonrası:", n) // 12 — dəyişdi!

    // Struct pointer
    u := User{Name: "Orkhan", Age: 25}
    fmt.Println(u.Info())

    u.BirthYearSet(1995) // Go avtomatik &u-ya çevirir
    fmt.Println("Yeni yaş:", u.Age)

    // & ilə struct yaratmaq
    u2 := &User{Name: "Eli", Age: 30}
    fmt.Println("Ad:", u2.Name) // Go avtomatik (*u2).Name edir
}
```

### Nümunə 3: Nullable dəyərlər və new

```go
package main

import "fmt"

// Nullable string — JSON-da null ola bilər
type UserProfile struct {
    Name     string
    Bio      *string // null ola bilər
    Age      *int    // null ola bilər
}

func printProfile(p UserProfile) {
    fmt.Println("Ad:", p.Name)
    if p.Bio != nil {
        fmt.Println("Bio:", *p.Bio)
    } else {
        fmt.Println("Bio: yoxdur")
    }
    if p.Age != nil {
        fmt.Println("Yaş:", *p.Age)
    }
}

func main() {
    // new() — zero value pointer yaradır
    num := new(int) // *int, dəyəri 0
    *num = 42
    fmt.Println("new:", *num)

    // new(struct) — nadiren istifadə olunur; &User{} daha çox
    bio := "Backend developer"
    yas := 25

    p1 := UserProfile{
        Name: "Veli",
        Bio:  &bio, // ünvan ver
        Age:  &yas,
    }
    printProfile(p1)

    p2 := UserProfile{Name: "Aysel"} // Bio, Age nil
    printProfile(p2)
}
```

## Praktik Tapşırıqlar

1. **Swap funksiyası**: `swap(a, b *int)` — pointer istifadə edərək iki dəyişkenin yerini dəyiş. `swap` sonrası dəyişkənlərin dəyərini yoxla.

2. **Optional field-lər**: API request struct-u yarat: `type UpdateUserRequest struct { Name *string; Email *string; Age *int }`. Yalnız nil olmayan sahələri "yenilə" (log et). PHP-dəki nullable type hint ilə müqayisə et.

3. **Counter struct**: `Counter` struct yarat: `count int`. Pointer receiver ilə `Increment()`, `Decrement()`, `Reset()`, `Value() int` metodları yaz. Eyni counter-i iki fərqli yerə pointer kimi ötür — hər ikisi eyni sayacı görməlidir.

4. **Nil safety pattern**: `func getUserByID(id int) *User` — istifadəçi tapılmasa `nil` qaytarır. Çağırıcı `nil` yoxlaması olmadan `user.Name` çağırarsa panic verir. Nil-safe helper: `func safeName(u *User) string` yaz.

## Əlaqəli Mövzular

- [10-structs.md](10-structs.md) — struct-lar
- [07-functions.md](07-functions.md) — funksiyalarda parametrlər
- [17-interfaces.md](17-interfaces.md) — interfeyslər
- [43-pointers-advanced.md](43-pointers-advanced.md) — pointer-lər dərinləşmə
