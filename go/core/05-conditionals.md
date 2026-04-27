# Şərt Operatorları — Conditionals (Junior)

## İcmal

Go-da şərt idarəetməsi üçün `if/else if/else` və `switch` konstruksiyaları var. İki əsas sintaksis xüsusiyyəti: şərt ətrafında mötərizə `()` yoxdur, açılan `{` mötərizə həmişə eyni sətirdə olmalıdır. `switch` isə güclüdür: `break` yazmaq lazım deyil, expressionless switch `if/else` zəncirinin yerini tuta bilər, tip yoxlama (`type switch`) daxili olaraq dəstəklənir.

## Niyə Vacibdir

Real backend kodunda şərt yoxlamaları hər yerdədir: request validation, error handling, business logic. Go-nun `if init; condition` sintaksisi (qısa elan şərti daxilində) kodu daha təmiz tutur — dəyişkən yalnız şərt bloku daxilindədir, xaricdə görünmür. Bu xüsusilə xəta yoxlama patterns-ında çox istifadə olunur.

## Əsas Anlayışlar

- **`if` sintaksisi** — şərt ətrafında `()` yoxdur; `{` eyni sətirdə məcburidir
- **`if init; condition`** — şərtdən əvvəl qısa elan; dəyişkən yalnız blok daxilindədir
- **`switch`** — Go-da `break` avtomatikdır; hər case-dən sonra saxlanır
- **`fallthrough`** — növbəti case-ə davam etmək üçün (nadir istifadə)
- **Expressionless switch** — `switch {}` forması; `if/else` zəncirinin daha oxunaqlı alternatividir
- **Type switch** — `switch v := x.(type)` — interface dəyərinin tipini yoxlayır
- **`case` bir neçə dəyər** — `case "Şənbə", "Bazar":` — vergüllə bir neçə dəyər

## Praktik Baxış

**Real layihədə istifadə:**
- Error yoxlama: `if err != nil { return err }` — Go-nun ən çox yazılan pattern-i
- HTTP status yoxlama: `if resp.StatusCode != 200 { ... }`
- Tip yoxlama JSON parsing zamanı: `type switch` ilə `interface{}` dəyərini yoxlamaq
- Konfiqurasiya yoxlama: expressionless switch ilə çoxlu şərtləri oxunaqlı formada yazmaq

**Trade-off-lar:**
- `if/else` — sadə, amma uzun zəncirlər çirkin görünür
- `switch` — uzun zəncirlər üçün daha oxunaqlı
- Expressionless switch — 3+ şərt üçün `if/else if/else`-dən üstün
- `fallthrough` — nadir istifadə edin; gözlənilməz davranışa yol aça bilər

**Common mistakes:**
- `{` mötərizəni yeni sətirə qoymaq — compile error
- `switch`-də `break` yazmağa çalışmaq — gereksizdir, amma xəta deyil
- `fallthrough`-un şərt yoxlamadan növbəti case-ə keçdiyini unutmaq

## Nümunələr

### Nümunə 1: if/else if/else və qısa elan

```go
package main

import "fmt"

func main() {
    yas := 20

    if yas >= 18 {
        fmt.Println("Böyükdür")
    } else if yas >= 15 {
        fmt.Println("Yeniyetmədir")
    } else {
        fmt.Println("Uşaqdır")
    }

    // if init; condition — qısa elan forması
    // n yalnız bu blok daxilindədir
    if n := 42; n > 40 {
        fmt.Println(n, "40-dan böyükdür")
    }

    // Error handling pattern — Go-nun ən çox istifadə olunan forması
    // if err := doSomething(); err != nil {
    //     return fmt.Errorf("xəta baş verdi: %w", err)
    // }
}
```

### Nümunə 2: switch — klassik və expressionless

```go
package main

import "fmt"

func main() {
    // Klassik switch
    gun := "Çərşənbə"
    switch gun {
    case "Bazar ertəsi":
        fmt.Println("Həftənin 1-ci günü")
    case "Çərşənbə axşamı":
        fmt.Println("Həftənin 2-ci günü")
    case "Çərşənbə":
        fmt.Println("Həftənin 3-cü günü")
    case "Şənbə", "Bazar": // bir neçə dəyər
        fmt.Println("İstirahət günü!")
    default:
        fmt.Println("Belə gün yoxdur")
    }

    // Expressionless switch — if/else alternativi
    bal := 85
    switch {
    case bal >= 90:
        fmt.Println("Əla")
    case bal >= 80:
        fmt.Println("Yaxşı")
    case bal >= 70:
        fmt.Println("Kafi")
    default:
        fmt.Println("Qeyri-kafi")
    }

    // HTTP status yoxlama
    statusCode := 404
    switch statusCode {
    case 200:
        fmt.Println("OK")
    case 400:
        fmt.Println("Bad Request")
    case 401, 403:
        fmt.Println("Auth xətası")
    case 404:
        fmt.Println("Tapılmadı")
    case 500:
        fmt.Println("Server xətası")
    default:
        fmt.Printf("Naməlum status: %d\n", statusCode)
    }
}
```

### Nümunə 3: Type switch və fallthrough

```go
package main

import "fmt"

// JSON parsing-də interface{} dəyərlərini yoxlamaq
func tipYoxla(deyer interface{}) {
    switch v := deyer.(type) {
    case int:
        fmt.Printf("Tam ədəd: %d\n", v)
    case float64:
        fmt.Printf("Onluq: %.2f\n", v)
    case string:
        fmt.Printf("Mətn: %s (uzunluq: %d)\n", v, len(v))
    case bool:
        fmt.Printf("Məntiqi: %t\n", v)
    case []interface{}:
        fmt.Printf("Array: %d element\n", len(v))
    case nil:
        fmt.Println("Nil dəyər")
    default:
        fmt.Printf("Naməlum tip: %T\n", v)
    }
}

func main() {
    tipYoxla(42)
    tipYoxla(3.14)
    tipYoxla("salam")
    tipYoxla(true)
    tipYoxla(nil)

    // fallthrough — növbəti case-ə davam et (nadir)
    reqem := 1
    switch reqem {
    case 1:
        fmt.Println("Bir")
        fallthrough
    case 2:
        fmt.Println("İki (fallthrough ilə çatdı)")
    case 3:
        fmt.Println("Üç")
    }
}
```

## Praktik Tapşırıqlar

1. HTTP middleware funksiyası yaz: `statusCode int` parametri alır, `"success"`, `"client_error"`, `"server_error"`, `"redirect"` qaytarır. `switch` istifadə et, 4xx üçün bir `case`, 5xx üçün başqa `case`.

2. Input validation funksiyası: `validateAge(age int) error` — yaşı yoxlayır: mənfi olmamalı, 150-dən böyük olmamalı; `if init; condition` formasını istifadə et.

3. JSON parser: `interface{}` dəyərini qəbul edən, tip switch ilə `string`, `float64`, `bool`, `[]interface{}`, `map[string]interface{}` tiplərini ayırd edən funksiya yaz.

4. Güzəştli qiymət hesablayıcı: `switch` + `expressionless switch` istifadə edərək, müştəri səviyyəsinə (`"silver"`, `"gold"`, `"platinum"`) görə endirim faizini qaytaran funksiya yaz.

## PHP ilə Müqayisə

- PHP: `if ($a > 0) {` → Go: `if a > 0 {` (mötərizə yoxdur)
- PHP: `switch ($x) { case 1: echo "bir"; break; }` → Go-da `break` lazım deyil
- PHP: `switch` yalnız bərabərlik yoxlayır; Go-dakı expressionless switch hər şərti yoxlaya bilər
- Go-da `else if` iki sözlə yazılır (PHP-dəki `elseif` kompakt forması yoxdur)
- Go-da `type switch` — PHP-dəki `instanceof` + `gettype()` kombinasiyasının daha güclü versiyası
- PHP-də `switch` case-lər arasında `break` olmadan düşür (fall-through default); Go-da əksinə — explicit `fallthrough` lazımdır

## Əlaqəli Mövzular

- [04-operators.md](04-operators.md) — operatorlar
- [06-loops.md](06-loops.md) — döngülər
- [07-functions.md](07-functions.md) — funksiyalar
- [17-interfaces.md](17-interfaces.md) — interfeyslər (type switch üçün)
- [18-error-handling.md](18-error-handling.md) — xəta idarəetməsi
