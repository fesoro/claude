# Dəyişkənlər — Variables (Junior)

## İcmal

Go-da dəyişkən elan etməyin bir neçə üsulu var: `var` açar sözü, qısa elan `:=` operatoru və sabitlər üçün `const`. Go statik tipli dildir — dəyişkenin tipi elan zamanında müəyyənləşir və dəyişdirilə bilməz. Bununla belə, `type inference` sayəsində tipi açıq yazmağa həmişə ehtiyac yoxdur — Go tipi özü təyin edir.

## Niyə Vacibdir

Go-da dəyişkən tipləri compile-time-da yoxlanılır. Bu o deməkdir ki, tip xətaları production-da deyil, build zamanında aşkar olunur. Bundan əlavə, Go-nun `zero value` konsepti — elan edilib dəyər verilməmiş dəyişkənlərin avtomatik başlanğıc dəyəri alması — `undefined` xətalarını aradan qaldırır.

## Əsas Anlayışlar

- **`var` açar sözü** — explicit tip ilə və ya type inference ilə dəyişkən elan edir
- **`:=` operatoru** — qısa elan; yalnız funksiya daxilində işləyir; ən çox istifadə olunan üsul
- **`const`** — sabiti elan edir; dəyəri dəyişdirilə bilməz; compile-time-da hesablanır
- **`iota`** — `const` blokunda avtomatik artan sayıcı; enum yaratmaq üçün istifadə olunur
- **Zero values** — `int` → `0`, `float64` → `0.0`, `string` → `""`, `bool` → `false`, pointer/slice/map → `nil`
- **Unused variable** — Go-da elan edilib istifadə edilməyən dəyişkən compile error verir
- **`_` (blank identifier)** — dəyəri ignore etmək üçün

## Praktik Baxış

**Real layihədə istifadə:**
- Konfiqurasiya dəyərləri üçün `const` — port nömrəsi, timeout, max retry sayı
- HTTP handler-lərdə cavab almaq üçün `:=` — ən məşhur istifadə forması
- Paket səviyyəsində `var` — global state (nadir, amma zəruri hallarda)

**Trade-off-lar:**
- `var` — açıq, oxunaqlı, amma verbose
- `:=` — qısa, amma yalnız funksiya daxilində; paket səviyyəsində işləmir
- `const` — compile-time, daha sürətli, amma yalnız sadə tiplər

**Common mistakes:**
- `:=` ilə artıq elan edilmiş dəyişkəni yenidən elan etmək cəhdi — compile error verir (bəzən `=` istifadə edin)
- Paket səviyyəsində `:=` istifadə etmək — işləmir, `var` lazımdır
- `const` blokunda `iota`-nın `0`-dan başladığını unutmaq

## Nümunələr

### Nümunə 1: Dəyişkən elan etmə üsulları

```go
package main

import "fmt"

func main() {
    // 1. Tam formu — var açar sözü ilə
    var ad string = "Orkhan"
    var yas int = 25
    var boy float64 = 1.75
    var aktiv bool = true

    // 2. Type inference — tip yazmadan
    var sheher = "Baku"   // Go string olduğunu bilir
    var nomre = 42        // int

    // 3. Qısa elan — ən çox istifadə olunan
    mesaj := "Salam Go!"
    faiz := 3.14

    // 4. Bir neçə dəyişkən birdən
    a, b, c := 1, 2, 3

    fmt.Println(ad, yas, boy, aktiv)
    fmt.Println(sheher, nomre)
    fmt.Println(mesaj, faiz)
    fmt.Println(a, b, c)
}
```

### Nümunə 2: Zero values və var bloku

```go
package main

import "fmt"

func main() {
    // Zero values — dəyər vermədən elan
    var s string  // ""
    var i int     // 0
    var f float64 // 0.0
    var b bool    // false

    fmt.Printf("string: %q\n", s)  // ""
    fmt.Printf("int: %d\n", i)     // 0
    fmt.Printf("float: %f\n", f)   // 0.0
    fmt.Printf("bool: %t\n", b)    // false

    // var bloku — oxunaqlı qruplar üçün
    var (
        olke    string = "Azərbaycan"
        paytaxt string = "Bakı"
        ehali   int    = 10_000_000 // _ ayırıcısı oxunaqlıq üçün
    )
    fmt.Println(olke, paytaxt, ehali)
}
```

### Nümunə 3: Sabitlər və iota

```go
package main

import "fmt"

// HTTP status kodları üçün sabitlər
const (
    StatusOK       = 200
    StatusNotFound = 404
    StatusError    = 500
)

// iota ilə enum yaratmaq
type LogSeviyyesi int

const (
    DEBUG LogSeviyyesi = iota // 0
    INFO                       // 1
    WARNING                    // 2
    ERROR                      // 3
)

// Bit flag-ları üçün iota
const (
    OxuHuququ    = 1 << iota // 1 (001)
    YazmaHuququ               // 2 (010)
    IcraHuququ                // 4 (100)
)

func main() {
    fmt.Println("OK:", StatusOK)
    fmt.Println("DEBUG:", DEBUG)
    fmt.Println("ERROR:", ERROR)

    icaze := OxuHuququ | YazmaHuququ
    fmt.Println("İcazə:", icaze) // 3 (011)
}
```

## Praktik Tapşırıqlar

1. HTTP client üçün konfiqurasiya sabitleri yaz: `BaseURL`, `Timeout`, `MaxRetry`, `DefaultPageSize` — `const` bloku istifadə et. Sonra bu dəyərləri `fmt.Printf` ilə formatlaşdırılmış şəkildə çap et.

2. İstifadəçi məlumatlarını dəyişkənlərlə modelləşdir: `ad`, `email`, `yas`, `aktiv`, `balans` — hər biri üçün uyğun tip seç. Zero value-dan başla, sonra real dəyərlər ver. Hər dəyişkenin `%T` tipi ilə çap et.

3. Haftanın günləri üçün `iota` ilə enum yaz. Cümə, Şənbə, Bazar üçün `isIstirahet` funksiyası yaz — `switch` istifadə et.

4. Aşağıdakı PHP kodunu Go-ya çevir:
   ```php
   $name = "Ali";
   $age = 30;
   $isAdmin = true;
   echo "User: $name, age: $age, admin: " . ($isAdmin ? "yes" : "no");
   ```

## PHP ilə Müqayisə

- PHP: `$ad = "Orkhan";` — tip yoxdur, istənilən zaman dəyişdirilə bilər
- Go: `ad := "Orkhan"` — tipi string-dir, başqa tipə dəyişdirilə bilməz
- PHP-də elan edilməmiş dəyişkən `null` qaytarır; Go-da compile error verir
- PHP-də `define('MAX', 100)` → Go-da `const Max = 100`
- PHP-də `$_` — Go-da `_` (blank identifier) eyni məqsəd daşıyır
- Go-da elan edilib istifadə edilməyən dəyişkən compile error verir; PHP-də mümkündür

## Əlaqəli Mövzular

- [01-introduction.md](01-introduction.md) — Go-ya giriş
- [03-data-types.md](03-data-types.md) — məlumat tipləri
- [04-operators.md](04-operators.md) — operatorlar
- [07-functions.md](07-functions.md) — funksiyalarda dəyişkənlər
