# Funksiyalar — Functions (Junior)

## İcmal

Go-dakı funksiyalar bir neçə güclü xüsusiyyətə malikdir: **birden çox dəyər qaytarmaq** (multiple return values), **adlı qaytarma** (named return), **variadic parametrlər** (`...`), **first-class funksiyalar** (funksiyalar dəyər kimi) və **`defer`** mexanizmi. Bu xüsusiyyətlər xüsusilə Go-nun idiomatik error handling pattern-ini formalaşdırır: `result, err := someFunc()`.

## Niyə Vacibdir

Go-da funksiyalar `(result, error)` cütü qaytarır. Bu pattern Go-da hər yerdədir: verilənlər bazası sorğuları, HTTP istəkləri, fayl əməliyyatları. Bu pattern-i başa düşmək Go kodunu oxumağın əsasıdır. Bundan əlavə, `defer` resursları düzgün bağlamaq üçün (fayl, database connection) çox vacibdir.

## Əsas Anlayışlar

- **Funksiya sintaksisi** — `func ad(parametrlər) qaytarma_tipi { ... }`
- **Multiple return** — `func bölmə(a, b int) (int, int)` — Go-nun ən güclü xüsusiyyətlərindən biri
- **Named return** — `func hesabla(a, b int) (cem, ferq int)` — adlı qaytarma dəyişkənləri
- **Naked return** — adlı qaytarmada `return` yalnız yazılır — dəyərləri addan götürür
- **Variadic** — `func topla(eded ...int)` — istənilən sayda parametr
- **First-class function** — funksiyalar dəyər kimi ötürülə, dəyişkənə saxlanıla bilər
- **Anonymous function** — `func() { ... }()` — lambda/closure
- **Closure** — daxili funksiya xarici scope-a çatır; `use` yazmadan işləyir
- **`defer`** — funksiya bitəndə icra edilir; LIFO (son daxil olan birinci çıxır) sırası

## Praktik Baxış

**Real layihədə istifadə:**
- `result, err := db.Query(...)` — multiple return ilə error handling
- `defer file.Close()` — faylı unutmadan bağlamaq
- `defer db.Close()` — database connection-ı təmizləmək
- Anonymous funksiya + closure — middleware, handler wrapper-lər
- Variadic — `log.Printf(format, args...)` kimi logging funksiyaları

**Trade-off-lar:**
- Named return — oxunaqlıdır, amma `return` birdən çox yerdə olarsa qarışıqlıq yarada bilər
- `defer` — resursları bağlamaq üçün mükəmməl, amma loop içindəki `defer` funksiya bitənə saxlanır, loop-da yox
- Closure — güclüdür, amma qalıq (goroutine leak) riskinə diqqət edin

**Common mistakes:**
- `defer` loop içindədir — hər iterasiyada defer stack-ə əlavə olunur; `func()` ilə wrap edin
- Named return istifadə edib `return val` yazıb adlı dəyərləri override etmək
- `...` ilə slice-ı açmağı unutmaq: `func(slice...)` — `...` olmasa compile error

## Nümunələr

### Nümunə 1: Əsas funksiya formaları

```go
package main

import (
    "fmt"
    "math"
)

// Sadə funksiya
func salam(ad string) {
    fmt.Println("Salam,", ad)
}

// Dəyər qaytaran
func kvadrat(n int) int {
    return n * n
}

// Eyni tipli parametrlər qısa yazılış
func topla(a, b int) int {
    return a + b
}

// Multiple return — Go-nun əsas pattern-i
func bölmə(a, b int) (int, error) {
    if b == 0 {
        return 0, fmt.Errorf("sıfıra bölmək olmaz")
    }
    return a / b, nil
}

func main() {
    salam("Orkhan")
    fmt.Println("9-un kvadratı:", kvadrat(9))
    fmt.Println("3 + 5 =", topla(3, 5))

    // Error handling pattern — Go-da hər yerdə
    netice, err := bölmə(10, 0)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Println("Nəticə:", netice)

    // Dairə sahəsi
    r := 5.0
    sahe := math.Pi * r * r
    fmt.Printf("Sahə: %.2f\n", sahe)
}
```

### Nümunə 2: Variadic, anonymous funksiya, closure

```go
package main

import "fmt"

// Variadic — istənilən sayda parametr
func cem(ededler ...int) int {
    toplam := 0
    for _, n := range ededler {
        toplam += n
    }
    return toplam
}

// Funksiya parametr kimi
func tebiq(items []int, fn func(int) int) []int {
    result := make([]int, len(items))
    for i, v := range items {
        result[i] = fn(v)
    }
    return result
}

// Closure — sayıcı yaradır
func sayiciYarat() func() int {
    sayi := 0
    return func() int {
        sayi++
        return sayi
    }
}

func main() {
    // Variadic çağırış
    fmt.Println("Cəm:", cem(1, 2, 3, 4, 5)) // 15

    // Slice-ı variadic-ə açmaq
    list := []int{10, 20, 30}
    fmt.Println("Cəm:", cem(list...)) // 60

    // Anonymous funksiya
    ikiqat := func(n int) int { return n * 2 }
    fmt.Println("5*2 =", ikiqat(5))

    // Higher-order function
    ededler := []int{1, 2, 3, 4, 5}
    kvadratlar := tebiq(ededler, func(n int) int { return n * n })
    fmt.Println("Kvadratlar:", kvadratlar)

    // Closure — hər çağırışda sayi artır
    say := sayiciYarat()
    fmt.Println(say(), say(), say()) // 1 2 3

    // İkinci sayıcı müstəqildir
    say2 := sayiciYarat()
    fmt.Println(say2()) // 1
}
```

### Nümunə 3: defer və named return

```go
package main

import "fmt"

// Named return — adlı dəyərlər
func statistika(ededler []int) (min, max, ort float64) {
    if len(ededler) == 0 {
        return // hər üçü 0 qaytarır
    }
    min = float64(ededler[0])
    max = float64(ededler[0])
    sum := 0.0
    for _, v := range ededler {
        f := float64(v)
        if f < min { min = f }
        if f > max { max = f }
        sum += f
    }
    ort = sum / float64(len(ededler))
    return // naked return — min, max, ort
}

// defer — resurs idarəetməsi
func faylEmal() {
    fmt.Println("Fayl açıldı")
    defer fmt.Println("Fayl bağlandı (defer)")  // ən sonda icra olunur

    // defer LIFO sırası ilə işləyir
    defer fmt.Println("Defer 1")
    defer fmt.Println("Defer 2")

    fmt.Println("İş görülür...")
    // Çıxış: İş görülür... → Defer 2 → Defer 1 → Fayl bağlandı
}

func main() {
    min, max, ort := statistika([]int{3, 1, 7, 2, 9, 4})
    fmt.Printf("Min: %.0f, Max: %.0f, Ortalama: %.2f\n", min, max, ort)

    faylEmal()
}
```

## Praktik Tapşırıqlar

1. `parseAge(s string) (int, error)` funksiyası yaz: string-i int-ə çevirir; boş string, mənfi dəyər, 150-dən böyük dəyər üçün xüsusi xəta mesajları qaytarır.

2. Retry funksiyası: `withRetry(fn func() error, maxAttempts int) error` — funksiyayı `maxAttempts` dəfə çağırır; uğur qazanırsa dərhal qaytarır, hamısı uğursuz olarsa son xətanı qaytarır. `defer` ilə attempt sayını log et.

3. Functional pipeline: `map`, `filter`, `reduce` funksiyaları yaz — higher-order funksiyaları istifadə et.

4. Middleware wrapper: `func withLogging(fn func(string) string) func(string) string` — orijinal funksiyaya giriş/çıxış zamanını log edən closure qaytarır. Closure-un necə işlədiyini izah et.

## PHP ilə Müqayisə

- PHP: `function f($a, $b)` — qaytarma tipi yoxdur (PHP 7.0+-da var, amma məcburi deyil); Go-da məcburidir
- PHP exception ilə error handling → Go multiple return `(result, error)` pattern-i
- PHP `...$args` → Go `args ...int`; PHP-dəki kimi `...` ilə genişləndirilir
- PHP closure-da `use ($var)` lazımdır; Go-da daxili funksiya avtomatik xarici dəyişkənə çatır
- PHP-də `defer` yoxdur; destructor (`__destruct`) və ya `finally` istifadə olunur
- Go-da funksiya birinci dərəcəli dəyərdir; PHP-də `Closure` class-ı ilə mümkündür, amma daha verbose

## Əlaqəli Mövzular

- [06-loops.md](06-loops.md) — döngülər
- [08-arrays-and-slices.md](08-arrays-and-slices.md) — slice-larla funksiyalar
- [10-structs.md](10-structs.md) — struct metodları
- [15-recursion.md](15-recursion.md) — rekursiya
- [18-error-handling.md](18-error-handling.md) — xəta idarəetməsi dərindən
