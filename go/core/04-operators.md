# Operatorlar — Operators (Junior)

## İcmal

Go-dakı operatorlar çoxu dillərlə oxşardır: riyazi (`+`, `-`, `*`, `/`, `%`), müqayisə (`==`, `!=`, `>`, `<`), məntiqi (`&&`, `||`, `!`), təyinat (`=`, `+=`, `-=`) operatorları. Mühüm xüsusiyyət: artırım/azaltma (`++`, `--`) yalnız statement-dir, ifadə deyil — dəyər qaytarmır. `$i++` və `++$i` arasındakı ayrım Go-da mövcud deyil.

## Niyə Vacibdir

Operatorlar bütün proqramlarda istifadə olunur: hesablama, şərt yoxlama, bit manipulyasiyası. Go-da bir neçə operator başqa dillərdən fərqli davranır. Xüsusilə tam ədəd bölməsi (`a/b` hər zaman tam ədəd qaytarır) və modulus operatoru real layihələrdə tez-tez istifadə olunur.

## Əsas Anlayışlar

- **Riyazi operatorlar** — `+`, `-`, `*`, `/`, `%`; `int/int` tam ədəd qaytarır (kəsir atılır)
- **Müqayisə operatorları** — `==`, `!=`, `>`, `<`, `>=`, `<=`; həmişə `bool` qaytarır
- **Məntiqi operatorlar** — `&&` (VƏ), `||` (VƏ YA), `!` (DEYİL); short-circuit evaluation var
- **Təyinat operatorları** — `=`, `+=`, `-=`, `*=`, `/=`, `%=`
- **Artırım/azaltma** — `sayi++`, `sayi--`; yalnız statement, dəyər qaytarmır; `++sayi` yoxdur
- **Bit operatorları** — `&` (AND), `|` (OR), `^` (XOR), `<<` (sol sürüşmə), `>>` (sağ sürüşmə)
- **Short-circuit** — `&&`-da sol tərəf `false`-dursa, sağ tərəf hesablanmır; `||`-da sol `true`-dursa, sağ hesablanmır

## Praktik Baxış

**Real layihədə istifadə:**
- `%` (modulus) — pagination: `(page - 1) * perPage`; cüt/tək yoxlama: `n % 2 == 0`
- Bit operatorları — permission flag-ları: `UserPerm & ReadFlag != 0`
- `+=` — counter artırma, toplama akkumulyatoru
- `&&`, `||` — input validasiya şərtlərini birləşdirmə

**Trade-off-lar:**
- Tam ədəd bölməsini float üçün istəyirsinizsə, əvvəlcə çevirməlisiniz: `float64(a) / float64(b)`
- Bit operatorları microoptimization üçün yaxşıdır, amma oxunaqlığı azaldır — şərh yazın

**Common mistakes:**
- `10 / 3` nəticəsinin `3.333` olmasını gözləmək — `3` qaytarır
- `x++` dəyər kimi istifadə etmək — Go-da compile error
- Müqayisədə tip uyğunsuzluğu: `int32` ilə `int64`-ü birbaşa müqayisə etmək — compile error

## Nümunələr

### Nümunə 1: Riyazi operatorlar

```go
package main

import "fmt"

func main() {
    a, b := 10, 3

    fmt.Println("Toplama:", a+b)  // 13
    fmt.Println("Çıxma:", a-b)    // 7
    fmt.Println("Vurma:", a*b)    // 30
    fmt.Println("Bölmə:", a/b)    // 3  ← tam ədəd bölməsi!
    fmt.Println("Qalıq:", a%b)    // 1

    // Float bölməsi istəyirsinizsə:
    x, y := 10.0, 3.0
    fmt.Println("Float bölmə:", x/y) // 3.3333...

    // Və ya açıq çevirmə:
    fmt.Println("Açıq çevirmə:", float64(a)/float64(b)) // 3.3333...

    // Praktik: pagination
    page, perPage := 3, 10
    offset := (page - 1) * perPage
    fmt.Println("Offset:", offset) // 20
}
```

### Nümunə 2: Müqayisə və məntiqi operatorlar

```go
package main

import "fmt"

func main() {
    yas := 25
    maas := 3000.0

    // Müqayisə
    fmt.Println("yas >= 18:", yas >= 18)    // true
    fmt.Println("maas > 5000:", maas > 5000) // false

    // Məntiqi — şərtləri birləşdirmə
    kreditAla := yas >= 18 && maas >= 2000
    fmt.Println("Kredit ala bilər:", kreditAla) // true

    // Short-circuit — sol false-dursa, sağ hesablanmır
    s := ""
    if s != "" && len(s) > 5 {  // s=="" olduğu üçün len(s) çağırılmır
        fmt.Println("Uzun string")
    }

    // NOT operatoru
    aktiv := true
    fmt.Println("Deaktiv:", !aktiv) // false

    // Çoxlu şərt
    x := 42
    if x > 0 && x < 100 && x%2 == 0 {
        fmt.Println("Müsbət, 100-dən kiçik, cüt")
    }
}
```

### Nümunə 3: Bit operatorları — icazə sistemi

```go
package main

import "fmt"

const (
    OxuHuququ    = 1 << iota // 1 (001)
    YazmaHuququ               // 2 (010)
    SilmeHuququ               // 4 (100)
)

func main() {
    // İstifadəçiyə ox + yaz icazəsi ver
    icaze := OxuHuququ | YazmaHuququ // 3 (011)
    fmt.Printf("İcazə: %b\n", icaze) // 11

    // İcazəni yoxla
    fmt.Println("Oxuya bilər:", icaze&OxuHuququ != 0)  // true
    fmt.Println("Silə bilər:", icaze&SilmeHuququ != 0) // false

    // Artırım/azaltma — yalnız statement
    sayi := 5
    sayi++
    sayi--
    fmt.Println("Sayi:", sayi) // 5

    // Təyinat operatorları
    c := 10
    c += 5; fmt.Println("+=:", c)  // 15
    c -= 3; fmt.Println("-=:", c)  // 12
    c *= 2; fmt.Println("*=:", c)  // 24
    c /= 4; fmt.Println("/=:", c)  // 6
    c %= 4; fmt.Println("%=:", c)  // 2
}
```

## Praktik Tapşırıqlar

1. Saat hesablayıcı yaz: verilmiş saniyə sayını `%` operatoru ilə saat, dəqiqə, saniyəyə çevir. Məsələn: `3661` saniyə → `1s 1d 1san`.

2. RBAC permission sistemi: `Admin`, `Editor`, `Viewer` rolları üçün bit flag-ları müəyyənləşdir. İstifadəçinin rolunu yoxlayan funksiya yaz: `hasPermission(userRole, required int) bool`.

3. Müqayisə operatorları testi: iki `float64` dəyəri müqayisə et (`0.1 + 0.2 == 0.3`). Nəticəni izah et. Düzgün müqayisə üçün `math.Abs(a-b) < 1e-9` istifadə et.

4. Aşağıdakı PHP kodunu Go-ya çevir, tip xətalarına diqqət et:
   ```php
   $a = 10; $b = 3;
   echo $a / $b;    // 3.333...
   echo $a % $b;    // 1
   $a++;
   echo $a;         // 11
   ```

## PHP ilə Müqayisə

- PHP: `$i++` ifadə kimi işləyir — `$a = $i++` yazılır; Go-da `a = i++` compile error
- PHP: `"5" == 5` → true (tip çevrilməsi); Go-da `"5" == 5` → compile error
- PHP-dəki `===` Go-da yoxdur — Go-da `==` həmişə həm tip, həm dəyər yoxlayır
- PHP: `10 / 3 = 3.333...`; Go: `10 / 3 = 3` (tam ədəd bölməsi)
- Go-da `++sayi` (prefix artırım) yoxdur; PHP-də həm `$i++`, həm `++$i` işləyir

## Əlaqəli Mövzular

- [02-variables.md](02-variables.md) — dəyişkənlər
- [03-data-types.md](03-data-types.md) — tiplər və çevrilmə
- [05-conditionals.md](05-conditionals.md) — şərt operatorları
- [06-loops.md](06-loops.md) — döngülər
