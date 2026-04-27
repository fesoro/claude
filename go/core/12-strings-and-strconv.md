# Strings və strconv Paketləri (Junior)

## İcmal

Go-da `string` immutable (dəyişdirilməz) UTF-8 bayt ardıcıllığıdır. String əməliyyatları üçün `strings` paketi, tip çevrilmələri üçün `strconv` paketi istifadə olunur. `strings.Builder` — çoxlu string birləşdirməni effektiv etmək üçün; `fmt.Sprintf` — formatlaşdırılmış string yaratmaq üçün. String-in içindəki simvolu dəyişdirmək üçün `[]byte`-a çevirmək lazımdır.

## Niyə Vacibdir

Backend developer hər gün string-lərlə işləyir: URL parsing, JSON keys, log mesajları, validation, CSV parse. Bu paketlərin funksiyalarını bilmək birinci gündən lazım olacaq. Bundan əlavə, Azərbaycan hərflərinin (`ə`, `ş`, `ğ`) düzgün emalı üçün `rune` konseptini başa düşmək vacibdir.

## Əsas Anlayışlar

- **`strings.Contains`** — alt-string yoxlama
- **`strings.HasPrefix/HasSuffix`** — başlanğıc/son yoxlama
- **`strings.Split`** — ayırma
- **`strings.Join`** — birləşdirmə
- **`strings.Replace/ReplaceAll`** — əvəzetmə
- **`strings.ToUpper/ToLower`** — böyük/kiçik hərf çevirməsi
- **`strings.TrimSpace`** — baş/son boşluqları sil
- **`strings.Builder`** — effektiv string birləşdirmə; `+` operatoru loop içindən qaçın
- **`strconv.Atoi`** — string → int; xəta qaytarır
- **`strconv.Itoa`** — int → string
- **`strconv.ParseFloat`** — string → float64; xəta qaytarır
- **`fmt.Sprintf`** — formatlaşdırılmış string yaratmaq

## Praktik Baxış

**Real layihədə istifadə:**
- Email normalizasiya: `strings.ToLower(strings.TrimSpace(email))`
- URL path parse: `strings.Split(path, "/")`
- Query string build: `strings.Builder` ilə
- Config dəyərini int-ə çevirmə: `strconv.Atoi(os.Getenv("PORT"))`
- Log formatı: `fmt.Sprintf("[%s] %s: %s", level, timestamp, message)`

**Trade-off-lar:**
- `+` ilə string birləşdirmə — azdırsa (2-3 dəfə) qəbul edilə bilər; çoxsa `strings.Builder` istifadə edin
- `fmt.Sprintf` — rahat, amma reflection istifadə edir; performance-a diqqətli olun
- `strconv` vs `fmt.Sscanf` — `strconv` daha sürətlidir

**Common mistakes:**
- `strings.Replace(s, old, new, -1)` əvəzinə `strings.ReplaceAll` istifadə edin (daha oxunaqlı)
- `len(str)` simvol sayı deyil, bayt sayıdır — Azərbaycan hərfləri üçün `len([]rune(str))`
- `strconv.Atoi` xətasını ignore etmək — `_, _ := strconv.Atoi(...)` → 0 qaytarır, xəta itirilir

## Nümunələr

### Nümunə 1: strings paketi əsas funksiyalar

```go
package main

import (
    "fmt"
    "strings"
)

func main() {
    metn := "Salam, Go dilini öyrənirik!"

    // Yoxlama funksiyaları
    fmt.Println(strings.Contains(metn, "Go"))       // true
    fmt.Println(strings.Contains(metn, "Java"))     // false
    fmt.Println(strings.HasPrefix(metn, "Salam"))   // true
    fmt.Println(strings.HasSuffix(metn, "!"))       // true
    fmt.Println(strings.Count(metn, "i"))            // neçə dəfə

    // Dəyişdirmə
    fmt.Println(strings.ToUpper(metn))
    fmt.Println(strings.ToLower(metn))
    fmt.Println(strings.TrimSpace("  salam  "))
    fmt.Println(strings.Trim("***salam***", "*"))
    fmt.Println(strings.Replace("aaa bbb aaa", "aaa", "ccc", 1))  // birinci
    fmt.Println(strings.ReplaceAll("aaa bbb aaa", "aaa", "ccc"))   // hamısı

    // Bölmə/birləşdirmə
    hisseler := strings.Split("alma,armud,nar", ",")
    fmt.Println(hisseler)    // [alma armud nar]
    fmt.Println(hisseler[0]) // alma

    birleshdirilmish := strings.Join(hisseler, " | ")
    fmt.Println(birleshdirilmish) // alma | armud | nar

    // Axtarış
    fmt.Println(strings.Index("salam dunya", "dunya"))    // 6
    fmt.Println(strings.Index("salam dunya", "mars"))     // -1
    fmt.Println(strings.LastIndex("go go go", "go"))      // 6
}
```

### Nümunə 2: strings.Builder — effektiv birləşdirmə

```go
package main

import (
    "fmt"
    "strings"
)

func main() {
    // PİS — hər iterasiyada yeni string yaranır (yavaş)
    result := ""
    for i := 0; i < 5; i++ {
        result += fmt.Sprintf("element_%d ", i)
    }
    fmt.Println("Pis:", result)

    // YAXŞI — strings.Builder (sürətli)
    var sb strings.Builder
    for i := 0; i < 5; i++ {
        sb.WriteString(fmt.Sprintf("element_%d ", i))
    }
    fmt.Println("Yaxşı:", sb.String())

    // Builder metodları
    var b strings.Builder
    b.WriteString("SELECT * FROM users")
    b.WriteString(" WHERE ")
    b.WriteString("active = 1")
    b.WriteString(" LIMIT ")
    fmt.Fprintf(&b, "%d", 10) // fmt.Fprintf ilə də yazılır
    fmt.Println(b.String())   // SELECT * FROM users WHERE active = 1 LIMIT 10
    fmt.Println("Uzunluq:", b.Len())
    b.Reset() // sıfırla

    // CSV yaratma nümunəsi
    headers := []string{"id", "name", "email"}
    var csv strings.Builder
    csv.WriteString(strings.Join(headers, ","))
    csv.WriteByte('\n')
    rows := [][]string{
        {"1", "Eli", "eli@example.com"},
        {"2", "Aysel", "aysel@example.com"},
    }
    for _, row := range rows {
        csv.WriteString(strings.Join(row, ","))
        csv.WriteByte('\n')
    }
    fmt.Println(csv.String())
}
```

### Nümunə 3: strconv — tip çevrilmələri

```go
package main

import (
    "fmt"
    "strconv"
)

func main() {
    // Atoi — string → int (xəta ilə)
    n, err := strconv.Atoi("42")
    if err != nil {
        fmt.Println("Xəta:", err)
    } else {
        fmt.Println("String → int:", n)
    }

    // Yanlış format
    _, err = strconv.Atoi("abc")
    if err != nil {
        fmt.Println("Yanlış format:", err)
    }

    // Itoa — int → string
    s := strconv.Itoa(123)
    fmt.Printf("int → string: %q\n", s) // "123"

    // ParseFloat — string → float64
    f, err := strconv.ParseFloat("3.14", 64)
    if err == nil {
        fmt.Println("ParseFloat:", f)
    }

    // ParseBool
    b, _ := strconv.ParseBool("true")
    fmt.Println("ParseBool:", b)

    // FormatFloat — float64 → string
    fmt.Println(strconv.FormatFloat(3.14159, 'f', 2, 64))  // 3.14
    fmt.Println(strconv.FormatFloat(3.14159, 'e', 4, 64))  // 3.1416e+00

    // FormatInt — müxtəlif bazalarda
    fmt.Println("10 binary:", strconv.FormatInt(10, 2))   // 1010
    fmt.Println("255 hex:", strconv.FormatInt(255, 16))   // ff

    // fmt.Sprintf — formatlaşdırılmış string
    ad, yas := "Orkhan", 25
    mesaj := fmt.Sprintf("Ad: %s, Yaş: %d, API: %.2f", ad, yas, 3.14)
    fmt.Println(mesaj)

    // Format verifiers
    fmt.Printf("%05d\n", 42)      // 00042
    fmt.Printf("%-10s|\n", "sol") // "sol       |"
    fmt.Printf("%q\n", "salam")   // "salam"
    fmt.Printf("%b\n", 42)        // 101010 (binary)
    fmt.Printf("%x\n", 255)       // ff (hex)
}
```

### Nümunə 4: Azərbaycan hərfləri ilə iş

```go
package main

import "fmt"

func main() {
    s := "Şəhər"

    // Yanlış — bayt sayı
    fmt.Println("len (bayt):", len(s)) // 9

    // Düzgün — simvol sayı
    fmt.Println("len (simvol):", len([]rune(s))) // 5

    // Yanlış gəzmə — bayt index-i
    for i := 0; i < len(s); i++ {
        fmt.Printf("bayt[%d]: %x\n", i, s[i])
    }

    // Düzgün gəzmə — rune
    for i, r := range s {
        fmt.Printf("simvol[%d]: %c\n", i, r)
    }

    // String-i dəyişdirmək — []byte
    str := "Salam"
    baytlar := []byte(str)
    baytlar[0] = 'H'
    fmt.Println(string(baytlar)) // Halam
}
```

## Praktik Tapşırıqlar

1. **Email validator**: `validateEmail(email string) (string, error)` — boşluqları trim et, kiçik hərfə çevir, `@` olmasa xəta qaytır. `strings` paketini istifadə et.

2. **Config parser**: `PORT=8080`, `DB_HOST=localhost` formatında konfiq faylı parse et. `strings.Split` ilə `=`-dan böl, key-value map-ə yığ. Port-u `strconv.Atoi` ilə int-ə çevir.

3. **Slug yaradıcı**: `slugify(title string) string` — boşluqları `-`-yə çevir, kiçik hərfə çevir, xüsusi simvolları sil. Azərbaycan hərflərini ASCII ekvivalentinə çevir.

4. **CSV → struct**: Aşağıdakı CSV-ni parse et — `strings.Split`, `strconv.Atoi`, `strconv.ParseFloat` istifadə et:
   ```
   id,name,price
   1,Laptop,1299.99
   2,Telefon,699.99
   ```

## PHP ilə Müqayisə

| PHP funksiyası | Go ekvivalenti |
|----------------|----------------|
| `str_contains()` | `strings.Contains()` |
| `str_starts_with()` | `strings.HasPrefix()` |
| `str_ends_with()` | `strings.HasSuffix()` |
| `str_replace()` | `strings.ReplaceAll()` |
| `explode()` | `strings.Split()` |
| `implode()` | `strings.Join()` |
| `strtoupper()` | `strings.ToUpper()` |
| `strtolower()` | `strings.ToLower()` |
| `trim()` | `strings.TrimSpace()` |
| `intval()` | `strconv.Atoi()` (xəta qaytarır!) |
| `floatval()` | `strconv.ParseFloat()` (xəta qaytarır!) |
| `sprintf()` | `fmt.Sprintf()` |
| `mb_strlen()` | `len([]rune(s))` |

- PHP: `intval("abc")` → 0 (səssiz); Go: `strconv.Atoi("abc")` → xəta (explicit)
- PHP: `$str[0]` simvol qaytarır; Go: `str[0]` bayt qaytarır (Azərbaycan hərfləri üçün yanlış!)
- PHP-də `"a" . "b"` → Go-da `"a" + "b"` (eyni, amma loop-da `strings.Builder` istifadə edin)
- PHP `strlen("Şəhər")` = 9 (bayt); Go `len("Şəhər")` = 9 — eyni davranış

## Əlaqəli Mövzular

- [03-data-types.md](03-data-types.md) — string tipi, byte, rune
- [07-functions.md](07-functions.md) — funksiyalar
- [13-file-operations.md](13-file-operations.md) — fayl oxuma/yazma
- [20-json-encoding.md](20-json-encoding.md) — JSON işləmə
