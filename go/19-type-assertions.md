# Type Assertions və Type Conversions (Middle)

## İcmal

Go-da iki fərqli tip transformasiya mexanizmi mövcuddur: **type assertion** interface-dən konkret tipə çatmaq üçün, **type conversion** isə bir konkret tipi başqa konkret tipə çevirmək üçündür. Go-da bu iki əməliyyat aydın şəkildə ayrılmışdır və compiler ciddi tip nəzarəti aparır — avtomatik tip çevirmə yoxdur.

## Niyə Vacibdir

Interface-lərlə işləyəndə (xüsusən `any`/`interface{}` parametrli funksiyalarda, JSON parsing-də, reflection-da) tip yoxlaması qaçınılmazdır. Type assertion düzgün istifadə olunmasa panic verir. JSON decode zamanı rəqəmlərin `float64` kimi gəlməsi, `[]interface{}` vs `[]string` fərqi — bunlar real layihələrdə ən çox rast gəlinən tuzaqlardır.

## Əsas Anlayışlar

- **Type assertion** — `v := iface.(ConcreteType)` — interface → konkret tip; yanlış tipdə panic
- **Comma-ok pattern** — `v, ok := iface.(ConcreteType)` — panic vermədən yoxlama; `ok` false olur
- **Type switch** — `switch v := x.(type) { case int: ... }` — çox tipin yoxlanması
- **Type conversion** — `float64(myInt)` — konkret tip → başqa konkret tip; yeni dəyər yaranır
- **`any`** — `interface{}` üçün alias (Go 1.18+); istənilən tipi saxlayır
- **Overflow** — `int32(bigInt64)` zamanı overflow baş verə bilər, Go yoxlamır
- **`[]byte` ↔ `string`** — sürətli konversiya, amma yaddaş kopyalanır
- **`[]rune` ↔ `string`** — Unicode simvollar üçün; `len(str)` bayt sayıdır, `len([]rune(str))` simvol sayı
- **Named types** — `type Manat float64` → `Manat` və `float64` arasında explicit conversion lazımdır
- **Nil interface** — nil interface assertion həmişə `ok=false` qaytarır, panic vermir

## Praktik Baxış

**Real istifadə ssenariləri:**
- JSON decode zamanı `interface{}` tipindən dəyərləri çıxarmaq
- Plugin/handler sistemlərində konkret tip yoxlaması
- `any` parametr qəbul edən generic utility funksiyaları
- `int` → `int64` kimi database sütunu tip uyğunlaşdırması

**Trade-off-lar:**
- Type assertion hər yerdə — kod çirklənir; generics (Go 1.18+) daha yaxşı alternativdir
- `interface{}` əvəzinə konkret tip istifadəsi — type safety saxlanılır
- Float → int konversiyası yuvarlama etmir, kəsir

**Ümumi səhvlər:**
- `ok` yoxlamadan birbaşa assertion — JSON rəqəmlərini `int` ilə assert etmək (həmişə `float64` gəlir)
- `[]interface{}` ilə `[]string` eyni deyil — avtomatik çevirmə yoxdur, dövrə lazımdır
- Float64 → int zamanı yuvarlama gözləmək — `int(3.99) == 3`, `4` deyil
- int64 → int32 overflow-u yoxlamamaq

## Nümunələr

### Nümunə 1: Type Assertion — panic-li və panic-siz

```go
package main

import "fmt"

type Heyvan interface {
    Ses() string
}

type It struct{ Ad string }
func (i It) Ses() string   { return "Hav hav!" }
func (i It) Gez() string   { return i.Ad + " gəzir" }

type Pisik struct{ Ad string }
func (p Pisik) Ses() string     { return "Miyav!" }
func (p Pisik) Miyavla() string { return p.Ad + " miyavlayır" }

func main() {
    var h Heyvan = It{Ad: "Rex"}

    // PANIC-Lİ assertion — yanlış tipdə panic verir
    it := h.(It)
    fmt.Println(it.Gez()) // Rex gəzir

    // PANIC-SİZ assertion — comma-ok pattern
    pisik, ok := h.(Pisik)
    if ok {
        fmt.Println("Pişikdir:", pisik.Ad)
    } else {
        fmt.Println("Pişik deyil!") // bu çap olunur
    }

    it2, ok := h.(It)
    if ok {
        fmt.Println("İtdir:", it2.Ad) // Rex
    }

    // Nil interface — panic yoxdur
    var nilIface Heyvan
    _, ok = nilIface.(It)
    fmt.Println("nil assertion ok:", ok) // false
}
```

### Nümunə 2: Type switch — çox tipli idarəetmə

```go
package main

import "fmt"

type It struct{ Ad string }
func (i It) Ses() string { return "Hav hav!" }

type Pisik struct{ Ad string }
func (p Pisik) Ses() string { return "Miyav!" }

type Heyvan interface{ Ses() string }

func heyvanInfo(h Heyvan) {
    switch v := h.(type) {
    case It:
        fmt.Printf("İt: %s, %s\n", v.Ad, v.Ad+" gəzir")
    case Pisik:
        fmt.Printf("Pişik: %s, %s\n", v.Ad, v.Ad+" miyavlayır")
    default:
        fmt.Printf("Naməlum heyvan: %T\n", v)
    }
}

func tipYoxla(d any) {
    switch v := d.(type) {
    case int:
        fmt.Printf("int: %d\n", v)
    case string:
        fmt.Printf("string: %q\n", v)
    case bool:
        fmt.Printf("bool: %t\n", v)
    case float64:
        fmt.Printf("float64: %.2f\n", v)
    case []int:
        fmt.Printf("[]int: %v\n", v)
    case nil:
        fmt.Println("nil dəyər")
    default:
        fmt.Printf("naməlum tip: %T = %v\n", v, v)
    }
}

func main() {
    heyvanlar := []Heyvan{
        It{Ad: "Rex"},
        Pisik{Ad: "Mişmiş"},
    }
    for _, h := range heyvanlar {
        heyvanInfo(h)
    }

    // any (interface{}) ilə type switch
    deyerler := []any{42, "salam", true, 3.14, []int{1, 2, 3}, nil}
    for _, d := range deyerler {
        tipYoxla(d)
    }
}
```

### Nümunə 3: Type Conversion — konkret tiplər arası

```go
package main

import (
    "fmt"
    "math"
)

func main() {
    // int → float64
    var tam int = 42
    var onluq float64 = float64(tam)
    fmt.Println("int → float64:", onluq) // 42.0

    // float64 → int (KƏSIR, YUVARLAMA YOX!)
    var pi float64 = 3.99
    var tamPi int = int(pi)
    fmt.Println("float64 → int:", tamPi) // 3, deyil 4!

    // int32 → int64 (genişləmə — təhlükəsiz)
    var kicik int32 = 100
    var boyuk int64 = int64(kicik)
    fmt.Println("int32 → int64:", boyuk)

    // int64 → int32 (daralma — OVERFLOW mümkün!)
    var cox int64 = math.MaxInt32 + 1
    var azaldilmis int32 = int32(cox)
    fmt.Println("Overflow nümunəsi:", azaldilmis) // mənfi rəqəm!

    // Named type konversiya — eyni underlying tip olsa belə explicit lazımdır
    type Manat float64
    type Dollar float64

    var qiymet Manat = 100
    // var usd Dollar = qiymet  // COMPILE XƏTASI! fərqli named types
    var usd Dollar = Dollar(qiymet) // explicit conversion
    fmt.Println("Manat → Dollar:", usd)
}
```

### Nümunə 4: String ↔ []byte, []rune

```go
package main

import "fmt"

func main() {
    // string → []byte
    s := "Salam"
    b := []byte(s)
    fmt.Println("[]byte:", b)  // [83 97 108 97 109]
    b[0] = 'H'
    fmt.Println("Dəyişdirilmiş []byte:", b) // [72 97 108 97 109]
    fmt.Println("Orijinal string:", s)       // Salam — dəyişmədi! (kopya yarandı)

    // []byte → string
    s2 := string(b)
    fmt.Println("string:", s2) // Halam

    // Unicode — string → []rune
    az := "Şəhər"
    r := []rune(az)
    fmt.Printf("Bayt sayı: %d, Simvol sayı: %d\n", len(az), len(r))
    // Bayt sayı: 8 (UTF-8), Simvol sayı: 5

    // Simvola birbaşa çatmaq
    fmt.Println("3-cü simvol:", string(r[2])) // h

    // []rune → string
    s3 := string(r)
    fmt.Println("string:", s3) // Şəhər

    // int → string (Unicode code point!)
    fmt.Println(string(rune(65)))  // A
    fmt.Println(string(rune(399))) // Ə
    // NOT: string(65) == "A", string("65") compile olmur
}
```

### Nümunə 5: JSON rəqəmləri ilə klassik tuzaq

```go
package main

import (
    "encoding/json"
    "fmt"
    "strings"
)

func main() {
    // JSON-dan gələn rəqəmlər defolt olaraq float64 olur!
    jsonStr := `{"id": 42, "qiymet": 19.99}`

    var data map[string]interface{}
    json.Unmarshal([]byte(jsonStr), &data)

    // YANLIŞ — panic verir!
    // id := data["id"].(int) // PANIC: float64, not int

    // DUZGUN — əvvəl float64, sonra int
    idFloat := data["id"].(float64)
    id := int(idFloat)
    fmt.Println("ID:", id) // 42

    // Tehlukesiz yol — ok pattern
    if q, ok := data["qiymet"].(float64); ok {
        fmt.Println("Qiymət:", q) // 19.99
    }

    // json.Number — böyük integer-lər üçün
    dec := json.NewDecoder(strings.NewReader(`{"big_id": 9007199254740993}`))
    dec.UseNumber()
    var data2 map[string]interface{}
    dec.Decode(&data2)

    num := data2["big_id"].(json.Number)
    bigID, _ := num.Int64()
    fmt.Println("Böyük ID:", bigID) // 9007199254740993 (dəqiq)

    // []interface{} ↔ []string — avtomatik çevirmə yoxdur
    strs := []string{"a", "b", "c"}
    // var ifaces []interface{} = strs // COMPILE XƏTASI!

    // Dövrə ilə çevirmə lazımdır
    ifaces := make([]interface{}, len(strs))
    for i, s := range strs {
        ifaces[i] = s
    }
    fmt.Println("Çevirilmiş:", ifaces)
}
```

## Praktik Tapşırıqlar

1. **Safe type extractor:** `GetString(data map[string]interface{}, key string) (string, error)`, `GetInt(...)`, `GetBool(...)` kimi utility funksiyaları yaz. JSON map-dən type-safe dəyər çıxarsın, tapılmasa və ya yanlış tipdirsə xəta qaytarsın.

2. **JSON number handling:** Böyük ID-ləri (int64 range) olan API cavabını `json.Decoder` + `UseNumber()` ilə parse edən funksiya yaz. ID-ni `int64`, qiymətləri `float64` kimi çıxarsın.

3. **Unicode string processer:** Azərbaycan əlifbasındakı simvolları (Əə, İi, Ğğ, Şş, Çç, Üü, Öö) sayıb statistika qaytaran funksiya yaz. `[]rune` istifadə et.

4. **Type-safe event system:** `Event interface { Type() string }` yarat. `UserCreated`, `OrderPlaced`, `PaymentFailed` tipləri implement etsin. `Dispatcher` hər event tipini type switch ilə handle etsin.

5. **Overflow yoxlaması:** `SafeInt32(v int64) (int32, error)` funksiyası yaz — `math.MinInt32` və `math.MaxInt32` həddindən kənardırsa xəta qaytarsın.

## PHP ilə Müqayisə

| PHP | Go |
|-----|-----|
| `(int)$val` — implicit, zəif yoxlama | `int(val)` — explicit, compile-time yoxlama |
| `$obj instanceof Foo` | `_, ok := iface.(Foo)` |
| `intval("42abc")` → `42` | `strconv.Atoi("42abc")` → xəta qaytarır |
| Massiv → string çevirmə "Array" verir | `[]byte(str)` düzgün çevirmə |
| `settype($var, "float")` | `float64(intVar)` |
| Float → int: `intval()` yuvarlama etmir | `int(3.99) == 3` — eyni davranış |

## Əlaqəli Mövzular

- [17-interfaces.md](17-interfaces.md) — interface əsasları; type assertion bunun üzərindədir
- [18-error-handling.md](18-error-handling.md) — `errors.As` daxilində type assertion mexanizmi
- [20-json-encoding.md](20-json-encoding.md) — JSON decode zamanı float64 tuzağı
- [12-strings-and-strconv.md](12-strings-and-strconv.md) — string/int konversiyaları `strconv` ilə
- [29-generics.md](29-generics.md) — type assertion-ın generics ilə alternativ yolu
- [60-reflection.md](60-reflection.md) — runtime-da tip məlumatı əldə etmək
