# Məlumat Tipləri — Data Types (Junior)

## İcmal

Go statik tipli dildir: hər dəyişkenin tipi compile-time-da məlumdur. Əsas tiplər: tam ədədlər (`int`, `int8`...`int64`, `uint`...), onluq ədədlər (`float32`, `float64`), mətn (`string`), məntiqi (`bool`), simvol tipləri (`byte`, `rune`). Go-da PHP-dən fərqli olaraq avtomatik tip çevrilməsi yoxdur — bütün çevrilmələr açıq şəkildə yazılmalıdır.

## Niyə Vacibdir

PHP-də tip sistemi çox elastikdir: `"42" + 8 = 50` işləyir. Bu rahatlıq bəzən gizli buqlara yol açır. Go-da `"42" + 8` compile error verir — xəta production-a çatmadan aşkarlanır. Backend developer üçün bu xüsusilə vacibdir: pul hesablamaları, ID-lər, API parametrləri — bunların hamısı düzgün tiplərlə modelləşdirilməlidir.

## Əsas Anlayışlar

- **`int`** — platforma asılı (32-bit sistemdə 32 bit, 64-bit sistemdə 64 bit); default tam ədəd tipi
- **`int8/16/32/64`** — konkret ölçülü tam ədədlər; yaddaş optimallaşdırması üçün
- **`uint`** — yalnız müsbət tam ədəd; neqativ ola bilməyən dəyərlər üçün (yaş, say)
- **`float64`** — default onluq tip; `float32`-dən 2 dəfə dəqiq
- **`string`** — immutable (dəyişdirilməz) UTF-8 bayt ardıcıllığı; backtick `` ` `` ilə multiline
- **`bool`** — yalnız `true` və ya `false`; PHP-dəki truthy/falsy yoxdur
- **`byte`** — `uint8` ilə eynidi; ASCII simvolu saxlamaq üçün
- **`rune`** — `int32` ilə eynidi; Unicode simvolu (tam hərfi) saxlamaq üçün
- **`len(string)`** — bayt sayını qaytarır, simvol sayını yox! Azərbaycan hərfləri 2 bayt tutur

## Praktik Baxış

**Real layihədə istifadə:**
- İstifadəçi ID-si üçün `int64` — verilənlər bazasında BIGINT ilə uyğun
- Pul məbləği üçün `int64` (qəpik kimi) — `float64` ilə pul hesablamayın!
- API response field-ləri üçün `string`, `bool`, `int64`
- Flag/permission bitləri üçün `uint8` və ya `uint32`

**PHP ilə fərqi:**
- PHP: `$a = "5"; $b = 3; echo $a + $b;` → `8` (işləyir)
- Go: `a := "5"; b := 3; c := a + b` → compile error
- PHP `strlen("Şəhər")` → bayt sayı qaytara bilər; Go `len("Şəhər")` → bayt sayı qaytarır
- PHP-də `true == 1` → true; Go-da `bool` heç vaxt `int` ilə müqayisə edilə bilməz

**Trade-off-lar:**
- `float64` pul hesablamaları üçün uyğun deyil — tam saylarla (qəpik) işləyin
- `int` vs `int64`: API-dan gələn ID-ləri həmişə `int64` ilə saxlayın — 32-bit overflow riski
- `string` immutable-dır: çoxlu string birləşdirmə əvəzinə `strings.Builder` istifadə edin

**Common mistakes:**
- Azərbaycan hərflərini `string[i]` ilə götürmək — bayt qaytarır, hərfi yox; `[]rune` istifadə edin
- `float64` ilə pul hesablamaq — dəyirmiləmə xətaları olur
- `int32`-dən `int64`-ə açıq çevirmə yazmamaq

## Nümunələr

### Nümunə 1: Əsas tiplər

```go
package main

import "fmt"

func main() {
    // Tam ədədlər
    var kicik int8  = 127               // -128..127
    var boyuk int64 = 9_223_372_036_854_775_807
    var musbet uint = 42

    // Onluq ədədlər
    var qiymet float64 = 19.99          // default; daha dəqiq
    var pi float32 = 3.14              // daha az yaddaş, daha az dəqiq

    // String
    ad := "Orkhan"
    soyad := "Şükürlü"
    tamAd := ad + " " + soyad         // birləşdirmə
    multiline := `Birinci sətir
İkinci sətir
Üçüncü sətir`

    // Bool
    aktiv := true
    fmt.Println(aktiv && false)        // false
    fmt.Println(aktiv || false)        // true

    fmt.Println(kicik, boyuk, musbet)
    fmt.Println(qiymet, pi)
    fmt.Println(tamAd)
    fmt.Println(multiline)
}
```

### Nümunə 2: byte, rune və Azərbaycan hərfləri

```go
package main

import "fmt"

func main() {
    // byte — ASCII simvolu
    var harf byte = 'A'
    fmt.Printf("byte: %c = %d\n", harf, harf) // A = 65

    // rune — Unicode simvolu
    var azHerf rune = 'ə'
    fmt.Printf("rune: %c = %d\n", azHerf, azHerf) // ə = 601

    // Vacib fərq: len(string) bayt sayı qaytarır
    s := "Şəhər"
    fmt.Println("len bayt:", len(s))              // 9 (Azərbaycan hərfləri 2 bayt)
    fmt.Println("len rune:", len([]rune(s)))       // 5 (hərflər)

    // String üzərində düzgün gəzmək
    for i, r := range s {
        fmt.Printf("index %d: %c (rune: %d)\n", i, r, r)
    }
}
```

### Nümunə 3: Tip çevrilməsi

```go
package main

import "fmt"

func main() {
    // Açıq tip çevrilməsi məcburidir
    var tamEded int = 42
    var onluq float64 = float64(tamEded)  // int → float64
    var geri int = int(onluq)             // float64 → int (kəsilir!)

    fmt.Println("int → float64:", onluq) // 42.0
    fmt.Println("float64 → int:", geri)  // 42 (3.99 → 3, kəsilir)

    // int növləri arası
    var a int32 = 100
    var b int64 = int64(a)
    fmt.Println("int32 → int64:", b)

    // Tipi öyrənmək
    x := 42
    y := 3.14
    z := "salam"
    fmt.Printf("x tipi: %T\n", x) // int
    fmt.Printf("y tipi: %T\n", y) // float64
    fmt.Printf("z tipi: %T\n", z) // string

    // String ↔ []byte çevrilməsi
    str := "Salam"
    baytlar := []byte(str)        // string → []byte
    geriStr := string(baytlar)    // []byte → string
    fmt.Println(baytlar, geriStr)
}
```

## Praktik Tapşırıqlar

1. E-ticarət məhsulu üçün tipləri müəyyənləş: `id` (`int64`), `ad` (`string`), `qiymet` (`int64` — qəpikdə), `stok` (`int`), `aktiv` (`bool`). `fmt.Printf` ilə `%T` istifadə edərək hər birinin tipini çap et.

2. Azərbaycan adları üzərində test: "Şəhriyar", "Əli", "Günel" — hər birinin bayt sayını (`len`) və simvol sayını (`len([]rune(...))`) müqayisə et. Nəticəni izah et.

3. Pul hesablaması: `100.10` AZN ilə `0.10` AZN-i `float64` ilə topla — nəticəyə bax. Sonra hər ikisini `int64` (qəpikdə: `10010` + `10`) ilə topla — fərqi müzakirə et.

4. `int8` overflow testı: `var x int8 = 127; x++` nə qaytarır? `-128`! Bunu PHP-dəki davranışla müqayisə et.

## Əlaqəli Mövzular

- [02-variables.md](02-variables.md) — dəyişkənlər
- [04-operators.md](04-operators.md) — operatorlar
- [12-strings-and-strconv.md](12-strings-and-strconv.md) — string əməliyyatları dərindən
- [09-maps.md](09-maps.md) — map tipi
