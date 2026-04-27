# Map — Lüğət Strukturu (Junior)

## İcmal

Go-dakı `map` — açar-dəyər (key-value) cütlərini saxlayan hash table strukturudur. Go-da map-in əsas xüsusiyyəti: açarın mövcudluğunu yoxlamaq üçün iki dəyərli oxuma (`val, ok := m[key]`) istifadə olunur. Map reference tipidir — kopyalandıqda eyni yaddaşa istinad edilir.

## Niyə Vacibdir

Backend kodunda map-lər hər yerdədir: JSON object parse etmək, HTTP header-lər, cache saxlamaq, group-by əməliyyatları, frequency counter, konfiqurasiya. Map-in reference tipi olduğunu bilmək vacibdir — map kopyalandıqda eyni yaddaşa istinad edilir, bu davranışı bilməmək gizli buqlara yol aça bilər.

## Əsas Anlayışlar

- **Map yaratma** — `map[KeyTip]DegerTip{}` literal ilə, ya `make(map[KeyTip]DegerTip)` ilə
- **Oxuma** — `val := m[key]` — açar yoxdursa zero value qaytarır (xəta yox!)
- **Mövcudluq yoxlama** — `val, ok := m[key]` — `ok` bool: `true` varsa, `false` yoxdursa
- **Əlavə/yeniləmə** — `m[key] = value` — açar varsa yeniləyir, yoxdursa əlavə edir
- **Silmə** — `delete(m, key)` — mövcud olmayan açarı silmək xəta vermir
- **Uzunluq** — `len(m)` — element sayı
- **Iteration** — `for k, v := range m` — sıra qarantiya deyil! Hər dəfə fərqli ola bilər
- **Reference tipi** — `m2 := m1` hər ikisi eyni yaddaşa baxır
- **Nil map** — elan edilib, `nil`-dir; oxumaq işləyir (zero value qaytarır), amma yazmaq panic verir

## Praktik Baxış

**Real layihədə istifadə:**
- `map[string]interface{}` — JSON parsing üçün (dinamik strukturda)
- `map[string]string` — HTTP header-lər, konfiqurasiya
- `map[int]User` — ID üzrə cache/lookup
- Frequency counter — `map[string]int` ilə sözlər, kateqoriyalar sayılır
- Group-by — `map[string][]User` — istifadəçiləri rola görə qruplaşdırmaq

**Trade-off-lar:**
- Sıra lazımdırsa — açarları slice-a götür, sort et, sonra map-dən oxu
- Concurrent access — `sync.Map` istifadə edin, adi map thread-safe deyil
- Memory — böyük map-lərə diqqət; `delete` yaddaşı dərhal azaltmır

**Common mistakes:**
- Nil map-ə yazmaq: `var m map[string]int; m["key"] = 1` → panic; həmişə `make` edin
- İterasiya sırasına güvənmək — map-də sıra yoxdur
- `m2 := m1` ilə kopya aldığını düşünmək — reference-dır, dəyişsə hər ikisini dəyişir

## Nümunələr

### Nümunə 1: Map yaratma və əsas əməliyyatlar

```go
package main

import "fmt"

func main() {
    // Literal ilə yaratma
    yaslar := map[string]int{
        "Eli":   25,
        "Veli":  30,
        "Orxan": 22,
    }

    // make ilə yaratma (boş map)
    qiymetler := make(map[string]float64)
    qiymetler["çay"] = 1.50
    qiymetler["kofe"] = 3.00
    qiymetler["su"] = 0.50

    // Oxuma
    fmt.Println("Eli-nin yaşı:", yaslar["Eli"]) // 25
    fmt.Println("Mövcud olmayan:", yaslar["Aydin"]) // 0 (zero value, xəta yox)

    // Mövcudluq yoxlama
    yas, varMi := yaslar["Eli"]
    if varMi {
        fmt.Println("Tapıldı:", yas)
    }

    _, varMi2 := yaslar["Aydin"]
    if !varMi2 {
        fmt.Println("Aydin tapılmadı")
    }

    // Əlavə/yeniləmə
    yaslar["Aydin"] = 28 // yeni
    yaslar["Eli"] = 26   // yeniləmə

    // Silmə
    delete(yaslar, "Veli")
    fmt.Println("Silindikdən sonra:", yaslar)

    // Uzunluq
    fmt.Println("Element sayı:", len(yaslar))
}
```

### Nümunə 2: İterasiya və reference xüsusiyyəti

```go
package main

import (
    "fmt"
    "sort"
)

func main() {
    qiymetler := map[string]float64{
        "çay":   1.50,
        "kofe":  3.00,
        "su":    0.50,
        "şirə":  2.00,
    }

    // İterasiya — sıra qarantiya deyil!
    for mehsul, qiymet := range qiymetler {
        fmt.Printf("%-8s: %.2f AZN\n", mehsul, qiymet)
    }

    // Sıralı çıxış istəyirsinizsə:
    keys := make([]string, 0, len(qiymetler))
    for k := range qiymetler {
        keys = append(keys, k)
    }
    sort.Strings(keys)
    fmt.Println("\nSıralı:")
    for _, k := range keys {
        fmt.Printf("%-8s: %.2f\n", k, qiymetler[k])
    }

    // Reference xüsusiyyəti
    original := map[string]int{"a": 1, "b": 2}
    kopia := original // eyni map-ə istinad!
    kopia["a"] = 999
    fmt.Println("Original:", original) // map[a:999 b:2] — dəyişdi!

    // Əsl kopya
    eslKopia := make(map[string]int)
    for k, v := range original {
        eslKopia[k] = v
    }
    eslKopia["a"] = 111
    fmt.Println("Original:", original) // dəyişmədi
}
```

### Nümunə 3: Praktik nümunələr

```go
package main

import "fmt"

// Frequency counter — söz sayıcı
func sozSay(metn string) map[string]int {
    sayac := make(map[string]int)
    soz := ""
    for _, h := range metn + " " {
        if h == ' ' {
            if soz != "" {
                sayac[soz]++
                soz = ""
            }
        } else {
            soz += string(h)
        }
    }
    return sayac
}

// Group-by — istifadəçiləri departmana görə
type User struct {
    Name       string
    Department string
}

func groupByDept(users []User) map[string][]User {
    result := make(map[string][]User)
    for _, u := range users {
        result[u.Department] = append(result[u.Department], u)
    }
    return result
}

func main() {
    // Söz sayıcı
    metn := "alma armud alma nar alma armud"
    sayac := sozSay(metn)
    fmt.Println("Söz sayı:", sayac) // map[alma:3 armud:2 nar:1]

    // Nested map
    telebler := map[string]map[string]int{
        "Eli": {"riyaziyyat": 90, "fizika": 85},
        "Veli": {"riyaziyyat": 75, "fizika": 80},
    }
    fmt.Println("Eli riyaziyyat:", telebler["Eli"]["riyaziyyat"])

    // Group-by
    users := []User{
        {"Eli", "Backend"},
        {"Aysel", "Frontend"},
        {"Veli", "Backend"},
        {"Günel", "Design"},
        {"Orxan", "Backend"},
    }
    grouped := groupByDept(users)
    for dept, deptUsers := range grouped {
        fmt.Printf("%s: %d nəfər\n", dept, len(deptUsers))
    }
}
```

## Praktik Tapşırıqlar

1. **Cache implementasiyası**: `map[int]User` ilə sadə in-memory cache yaz. `Get(id int) (User, bool)`, `Set(id int, u User)`, `Delete(id int)` metodları olsun. Nil map panic-indən qaçmaq üçün `make` ilə initialize et.

2. **HTTP Router**: `map[string]func()` istifadə edərək sadə URL router yaz. `GET /users`, `POST /users`, `GET /users/profile` path-larını qeydiyyatdan keçir, `route(method, path string)` ilə handler-i tap və çağır.

3. **Word frequency**: Bir mətndən ən çox istifadə olunan 5 sözü tap. `strings.Fields` ilə sözlərə böl, map ilə say, sort et.

4. **PHP-dən Go-ya çevir**:
   ```php
   $config = ['host' => 'localhost', 'port' => 5432, 'db' => 'myapp'];
   if (isset($config['password'])) {
       echo $config['password'];
   }
   unset($config['db']);
   ```

## PHP ilə Müqayisə

- PHP: `$arr["key"]` — açar yoxdursa `null` qaytarır + notice; Go-da zero value qaytarır, xəta yox
- PHP: `isset($arr["key"])` → Go: `_, ok := m["key"]; ok`
- PHP: `unset($arr["key"])` → Go: `delete(m, "key")`
- PHP `array` iteration sıralıdır; Go map iteration sırası naməlumdur
- PHP array kopyalananda ayrı kopya olur (copy-on-write); Go map reference-dır — eyni yaddaş
- PHP-dəki associative array həm list, həm map rolunu oynayır; Go-da bunlar ayrı tiplərdir

## Əlaqəli Mövzular

- [08-arrays-and-slices.md](08-arrays-and-slices.md) — slice-lar
- [10-structs.md](10-structs.md) — daha mürəkkəb məlumat strukturları
- [06-loops.md](06-loops.md) — for range ilə map
- [20-json-encoding.md](20-json-encoding.md) — JSON ↔ map çevrilməsi
