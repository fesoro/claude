# Fayl Əməliyyatları — File Operations (Junior)

## İcmal

Go-da fayl əməliyyatları `os` və `bufio` paketləri vasitəsilə aparılır. Kiçik fayllar üçün `os.ReadFile`/`os.WriteFile` rahat, sadədir. Böyük fayllar üçün isə `bufio.Scanner` — sətir-sətir oxumaq üçün — daha effektivdir. Go-da fayl əməliyyatlarının əsas qaydası: `defer file.Close()` — fayl açıldıqdan dərhal sonra bağlamaq üçün defer istifadə edin.

## Niyə Vacibdir

Backend developer gündəlik fayl əməliyyatları ilə üzləşir: konfiqurasiya faylları oxumaq, log fayllarına yazmaq, CSV/JSON import etmək, temporary fayl yaratmaq. `defer file.Close()` pattern-i faylı bağlamağı unutmağa qarşı mükəmməl müdafiədir. `os.IsNotExist(err)` fayl yoxlama pattern-i isə daha güvənli yanaşmadır.

## Əsas Anlayışlar

- **`os.WriteFile`** — bütöv faylı birdən yaz
- **`os.ReadFile`** — bütöv faylı birdən oxu
- **`os.Create`** — yeni fayl yarat (varsa silir, yenidən yaradır); `*os.File` qaytarır
- **`os.Open`** — faylı yalnız oxumaq üçün aç; yoxsa xəta qaytarır
- **`os.OpenFile`** — flag-larla (O_RDWR, O_APPEND, O_CREATE) aç
- **`defer file.Close()`** — funksiya bitəndə faylı bağla; həmişə yazın
- **`bufio.Scanner`** — sətir-sətir oxumaq üçün; böyük fayllar üçün effektiv
- **`file.WriteString`** — string yaz; `file.Write([]byte)` bayt yaz
- **`os.Stat`** — fayl haqqında məlumat; mövcudluq yoxlamaq üçün
- **`os.IsNotExist(err)`** — faylın mövcud olmadığını yoxla
- **`os.Mkdir/MkdirAll`** — qovluq yarat; `MkdirAll` — iç-içə qovluqlar
- **`os.ReadDir`** — qovluq məzmununu oxu
- **`os.Remove/RemoveAll`** — fayl/qovluq sil

## Praktik Baxış

**Real layihədə istifadə:**
- Konfiqurasiya: `os.ReadFile("config.json")` → JSON parse
- Log: `os.OpenFile("app.log", os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)`
- CSV import: `bufio.Scanner` ilə sətir-sətir oxu, parse et, DB-ə yaz
- Fayl upload: multi-part form-dan faylı `os.Create` ilə saxla
- Static file serve: faylın mövcudluğunu yoxla, uyğun content-type ilə göndər

**Trade-off-lar:**
- `os.ReadFile` — sadə, amma böyük faylı birdən yaddaşa oxuyur; GB-lıq fayl üçün istifadə etməyin
- `bufio.Scanner` — sətir-sətir oxuyur; böyük fayllar üçün əla, amma oxunmuş sətri dərhal emal etmək lazımdır
- `os.Create` — varsa üzərinə yazır; diqqətli olun; `os.OpenFile` ilə flag-ları idarə edin

**Common mistakes:**
- `defer file.Close()` yazmamaq — fayl açıq qalır, OS limit-inə çatıla bilər
- `os.Create` ilə append istəmək — `Create` faylı sıfırlayır; `os.OpenFile` + `O_APPEND` istifadə edin
- Fayl mövcudluğunu `os.Open` cəhdi ilə yoxlamaq — bu düzdür, amma `os.Stat` daha açıqdır
- `[]byte` ↔ `string` çevrilməsini unutmaq: `os.WriteFile(path, []byte(metn), 0644)`

## Nümunələr

### Nümunə 1: Əsas fayl əməliyyatları

```go
package main

import (
    "fmt"
    "os"
)

func main() {
    // 1. Fayla yaz — os.WriteFile
    metn := []byte("Salam Dünya!\nBu Go-dan yazıldı.\n")
    err := os.WriteFile("test.txt", metn, 0644)
    if err != nil {
        fmt.Println("Yazma xətası:", err)
        return
    }
    fmt.Println("Fayl yazıldı")

    // 2. Fayldan oxu — os.ReadFile
    data, err := os.ReadFile("test.txt")
    if err != nil {
        fmt.Println("Oxuma xətası:", err)
        return
    }
    fmt.Println("Məzmun:")
    fmt.Print(string(data))

    // 3. Fayl mövcudluğunu yoxla
    _, err = os.Stat("test.txt")
    if os.IsNotExist(err) {
        fmt.Println("Fayl mövcud deyil")
    } else if err != nil {
        fmt.Println("Stat xətası:", err)
    } else {
        fmt.Println("Fayl mövcuddur")
    }

    // Sonda təmizlə
    os.Remove("test.txt")
}
```

### Nümunə 2: Fayl yaratma, append, sətir-sətir oxuma

```go
package main

import (
    "bufio"
    "fmt"
    "os"
)

func main() {
    // os.Create ilə fayl yarat
    fayl, err := os.Create("log.txt")
    if err != nil {
        fmt.Println("Yaratma xətası:", err)
        return
    }
    defer fayl.Close() // funksiya bitəndə bağla

    fayl.WriteString("2024-01-01 INFO Server başladı\n")
    fayl.WriteString("2024-01-01 INFO İstifadəçi qeydiyyat\n")
    fayl.WriteString("2024-01-01 ERROR Verilənlər bazası xətası\n")
    fmt.Println("Log yazıldı")

    // Append — mövcud fayla əlavə
    logFayl, err := os.OpenFile("log.txt", os.O_APPEND|os.O_WRONLY, 0644)
    if err != nil {
        fmt.Println("Açma xətası:", err)
        return
    }
    defer logFayl.Close()
    logFayl.WriteString("2024-01-01 INFO Server dayandı\n")

    // bufio.Scanner ilə sətir-sətir oxu
    oxuFayl, err := os.Open("log.txt")
    if err != nil {
        fmt.Println("Açma xətası:", err)
        return
    }
    defer oxuFayl.Close()

    scanner := bufio.NewScanner(oxuFayl)
    setirNo := 1
    for scanner.Scan() {
        line := scanner.Text()
        fmt.Printf("Sətir %d: %s\n", setirNo, line)
        setirNo++
    }

    os.Remove("log.txt")
}
```

### Nümunə 3: Qovluq əməliyyatları və CSV parse

```go
package main

import (
    "bufio"
    "fmt"
    "os"
    "strings"
    "strconv"
)

type User struct {
    ID    int
    Name  string
    Email string
}

func parseCSV(faylAdi string) ([]User, error) {
    fayl, err := os.Open(faylAdi)
    if err != nil {
        return nil, err
    }
    defer fayl.Close()

    var users []User
    scanner := bufio.NewScanner(fayl)
    
    // Header sətirini atla
    scanner.Scan()

    for scanner.Scan() {
        sutunlar := strings.Split(scanner.Text(), ",")
        if len(sutunlar) != 3 {
            continue
        }
        id, err := strconv.Atoi(strings.TrimSpace(sutunlar[0]))
        if err != nil {
            continue
        }
        users = append(users, User{
            ID:    id,
            Name:  strings.TrimSpace(sutunlar[1]),
            Email: strings.TrimSpace(sutunlar[2]),
        })
    }
    return users, scanner.Err()
}

func main() {
    // Qovluq yarat
    err := os.MkdirAll("data/uploads", 0755)
    if err != nil {
        fmt.Println("MkdirAll xətası:", err)
        return
    }

    // CSV fayl yaz
    csv := "id,name,email\n1,Eli,eli@example.com\n2,Aysel,aysel@example.com\n"
    os.WriteFile("data/users.csv", []byte(csv), 0644)

    // CSV parse et
    users, err := parseCSV("data/users.csv")
    if err != nil {
        fmt.Println("Parse xətası:", err)
        return
    }
    for _, u := range users {
        fmt.Printf("ID: %d, Ad: %s, Email: %s\n", u.ID, u.Name, u.Email)
    }

    // Qovluq məzmununu oxu
    girisler, _ := os.ReadDir("data")
    fmt.Println("\ndata/ qovluğu:")
    for _, g := range girisler {
        tip := "FAYL"
        if g.IsDir() { tip = "DIR " }
        fmt.Printf("  [%s] %s\n", tip, g.Name())
    }

    // Təmizlə
    os.RemoveAll("data")
}
```

## Praktik Tapşırıqlar

1. **Log writer**: `Logger` struct yarat. `Info(msg string)`, `Error(msg string)` metodları. Hər mesaj `[INFO] 2024-01-01 15:04:05 message` formatında `app.log` faylına append olsun. `defer` ilə faylı bağla.

2. **Config reader**: `config.env` faylını oxu (`KEY=VALUE` formatında), map-ə yığ. Yoxsa default dəyərlər istifadə et. `bufio.Scanner` ilə sətir-sətir oxu, `strings.Split` ilə parse et.

3. **CSV importer**: İstifadəçi məlumatlarını CSV fayldan oxu, validiasiya et (email formatı yoxla, yaş > 0), etibarlıları `valid.csv`, etibarsızları `invalid.csv`-yə yaz.

4. **File watcher simulyasiyası**: `checkFile(path string)` — hər saniyə faylın ölçüsünü yoxlayır; dəyişsə log edir. 5 saniyə işlədikdən sonra dayanır. (`time.Sleep`, `os.Stat`)

## PHP ilə Müqayisə

| PHP funksiyası | Go ekvivalenti |
|----------------|----------------|
| `file_get_contents()` | `os.ReadFile()` |
| `file_put_contents()` | `os.WriteFile()` |
| `fopen($path, 'a')` | `os.OpenFile(path, os.O_APPEND\|os.O_WRONLY, 0644)` |
| `fgets($handle)` | `bufio.Scanner + scanner.Scan() + scanner.Text()` |
| `file_exists()` | `os.Stat(path); os.IsNotExist(err)` |
| `mkdir()` | `os.Mkdir()` / `os.MkdirAll()` |
| `unlink()` | `os.Remove()` |

- PHP-də `fclose` unutmaq ola bilər; Go-da `defer file.Close()` bunu avtomatikləşdirir
- Go-da `os.ReadFile` xəta qaytarır — explicit yoxlama lazımdır; PHP-də `false` qaytarır
- PHP-dəki `\n` → Go-da `\n` (Unix) — platform fərqinə diqqət; Windows-da `\r\n` ola bilər

## Əlaqəli Mövzular

- [07-functions.md](07-functions.md) — defer ilə resurs idarəsi
- [12-strings-and-strconv.md](12-strings-and-strconv.md) — string parse etmə
- [20-json-encoding.md](20-json-encoding.md) — JSON fayl əməliyyatları
- [13-files-advanced.md](../backend/13-files-advanced.md) — fayl əməliyyatları dərindən
