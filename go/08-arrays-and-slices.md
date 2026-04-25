# Massivlər və Slice-lar — Arrays and Slices (Junior)

## İcmal

Go-da iki ardıcıl məlumat strukturu var: **array** (massiv) — sabit ölçülü; və **slice** — dinamik ölçülü, Go-da ən çox istifadə olunan struktur. PHP-dəki `$arr = [1, 2, 3]` Go-da `slice` ilə qarşılıqlıdır. Array nadiren birbaşa istifadə olunur; həmişə demək olar ki, slice istifadə olunur. Slice-ın daxilindəki `len` (mövcud element sayı) və `cap` (ayrılmış yaddaş) arasındakı fərqi anlamaq performance-ın açarıdır.

## Niyə Vacibdir

Slice-lar Go backend kodunun əsasını təşkil edir: verilənlər bazasından siyahı sorğusu, JSON array parse etmək, HTTP handler parametrləri — hamısı slice-dır. `append`, `copy`, slicing operatorları, nil slice vs boş slice fərqi — bunları bilmək buqsuz kod yazmağın şərtidir.

## Əsas Anlayışlar

- **Array** — `[3]int{1,2,3}` — ölçü tipin bir hissəsidir; `[3]int` ≠ `[4]int`
- **Slice** — `[]int{1,2,3}` — dinamik; array-ın üzərindəki görünüş (view)
- **`len`** — mövcud element sayı
- **`cap`** — ayrılmış yaddaş həcmi; `append` lazım olanda avtomatik artır (adətən 2 dəfə)
- **`append`** — slice-a element əlavə edir; capacity dolduqda yeni array yaradır, köçürür
- **`copy`** — bir slice-dan digərinə kopyalar; dərin kopya üçün lazımdır
- **Slicing** — `s[1:4]` — index 1, 2, 3 elementləri; orijinal array-ı paylaşır (shallow!)
- **Nil slice** — elan edilib, `nil`-dir; `var s []int` → `s == nil` true
- **Boş slice** — `[]int{}` — mövcuddur, amma boş; `== nil` false-dur
- **2D slice** — `[][]int` — matris üçün; PHP-dəki nested array kimi

## Praktik Baxış

**Real layihədə istifadə:**
- DB sorğu nəticəsi — `rows` → `users := []User{}` şəklində
- Batch emal — böyük siyahını hissələrə bölmək üçün slicing
- Filter — `for range` + `append` ilə şərtə uyğun elementlər
- Stack implementasiyası — `append` ilə push, slicing ilə pop

**PHP ilə fərqi:**
- PHP `array` həm sıralanmış siyahı (slice), həm lüğət (map) rolunu oynayır; Go-da bunlar ayrıdır
- PHP `array_push($arr, $val)` → Go `slice = append(slice, val)`
- PHP `array_slice($arr, 1, 3)` → Go `slice[1:4]`
- PHP `in_array($val, $arr)` → Go-da bu built-in yoxdur; `for range` ilə yoxlayın
- Slice kopyalandığında eyni underlying array-ı paylaşır — PHP-dəki copy-on-write fərqlidir

**Trade-off-lar:**
- Slicing shallow-dır: `s2 := s1[1:3]` dəyişdirsəniz `s1`-ə də təsir edir; tam kopya üçün `copy` istifadə edin
- `append` yeni array yaradırsa (cap dolubsa), köhnə referans keçərsizləşir — buna görə həmişə `s = append(s, ...)` yazın
- Pre-allocation: `make([]T, 0, expectedSize)` ilə başlayın — çoxlu `append`-dən qaçın

**Common mistakes:**
- `s2 := s1[:]` ilə kopya aldığını düşünmək — shallow view-dur, `copy` istifadə edin
- `append`-in nəticəsini saxlamaq: `append(s, val)` — `s`-i dəyişmir, nəticəni saxlamalısınız
- Nil slice ilə `nil` yoxlaması — `len(nilSlice) == 0` üstün yoxlamadır

## Nümunələr

### Nümunə 1: Array vs Slice — fərq

```go
package main

import "fmt"

func main() {
    // Array — sabit ölçülü (nadir istifadə)
    var arr [3]int
    arr[0] = 10
    arr[1] = 20
    arr[2] = 30
    fmt.Println("Array:", arr)

    meyveler := [3]string{"alma", "armud", "nar"}
    fmt.Println("Meyvələr:", meyveler)

    // [...] — ölçünü Go hesablasın
    rengler := [...]string{"qırmızı", "yaşıl", "göy"}
    fmt.Println("Uzunluq:", len(rengler))

    // Slice — ən çox istifadə olunan
    sehirler := []string{"Bakı", "Gəncə", "Sumqayıt"}
    fmt.Println("Şəhərlər:", sehirler)
    fmt.Println("Uzunluq:", len(sehirler))

    // make ilə slice
    siyahi := make([]int, 3, 5) // len=3, cap=5
    fmt.Printf("len=%d, cap=%d\n", len(siyahi), cap(siyahi))
}
```

### Nümunə 2: append, copy, slicing

```go
package main

import "fmt"

func main() {
    // append
    var liste []int
    liste = append(liste, 1)
    liste = append(liste, 2, 3, 4)
    fmt.Println("Liste:", liste) // [1 2 3 4]

    // İki slice birləşdirmə
    digeri := []int{5, 6, 7}
    liste = append(liste, digeri...)
    fmt.Println("Birləşdirilmiş:", liste) // [1 2 3 4 5 6 7]

    // Slicing — s[low:high] → low daxil, high xaric
    s := []int{0, 1, 2, 3, 4, 5, 6, 7, 8, 9}
    fmt.Println("s[2:5]:", s[2:5]) // [2 3 4]
    fmt.Println("s[:3]:", s[:3])   // [0 1 2]
    fmt.Println("s[7:]:", s[7:])   // [7 8 9]

    // Shallow copy problemi
    original := []int{1, 2, 3}
    shallow := original[:]
    shallow[0] = 999
    fmt.Println("Original:", original) // [999 2 3] — dəyişdi!

    // Düzgün kopya
    dogru := make([]int, len(original))
    copy(dogru, original)
    dogru[0] = 888
    fmt.Println("Original:", original) // dəyişmədi
    fmt.Println("Kopya:", dogru)

    // Element silmə — index 2-ni sil
    siyahi2 := []string{"a", "b", "c", "d", "e"}
    siyahi2 = append(siyahi2[:2], siyahi2[3:]...)
    fmt.Println("Silinmiş:", siyahi2) // [a b d e]
}
```

### Nümunə 3: 2D slice və real istifadə

```go
package main

import "fmt"

func main() {
    // 2D slice — matris
    matris := [][]int{
        {1, 2, 3},
        {4, 5, 6},
        {7, 8, 9},
    }
    fmt.Println("Matris[1][2]:", matris[1][2]) // 6

    // Nil slice vs boş slice
    var nilSlice []int          // nil (mövcud deyil)
    bosSlice := []int{}         // boş (mövcuddur)
    makeSlice := make([]int, 0) // boş (mövcuddur)

    fmt.Println("nil:", nilSlice == nil)      // true
    fmt.Println("boş:", bosSlice == nil)      // false
    fmt.Println("make:", makeSlice == nil)    // false
    // Hamısı üçün append işləyir:
    nilSlice = append(nilSlice, 1)

    // DB nəticəsini slice-a toplamaq — real pattern
    type User struct {
        ID   int
        Name string
    }
    users := make([]User, 0, 10) // gözlənilən ölçü ilə pre-allocate
    for i := 1; i <= 3; i++ {
        users = append(users, User{ID: i, Name: fmt.Sprintf("User%d", i)})
    }
    fmt.Println("Users:", users)

    // Filter — aktiv istifadəçilər
    allIDs := []int{1, 2, 3, 4, 5, 6, 7, 8}
    aktiv := make([]int, 0)
    for _, id := range allIDs {
        if id%2 == 0 { // cüt ID-lər aktiv
            aktiv = append(aktiv, id)
        }
    }
    fmt.Println("Aktiv:", aktiv)
}
```

## Praktik Tapşırıqlar

1. **Batch processing**: 100 ədədlik slice-ı 10-luq paketlərə (`batch`) bölün. `s[i*10:(i+1)*10]` slicing-ini istifadə et. Hər paketin cəmini çap et.

2. **Stack implementasiyası**: Yalnız slice istifadə edərək `push(val)`, `pop() (val, ok)`, `peek() (val, ok)`, `isEmpty() bool` metodları olan stack yaz. Slice-ın son elementini stack top kimi istifadə et.

3. **Unique filter**: Dublikat elementləri silən funksiya yaz: `unique(items []int) []int`. Map istifadə et — `seen := map[int]bool{}`.

4. **PHP-dən Go-ya çevir**:
   ```php
   $numbers = [1, 2, 3, 4, 5];
   $doubled = array_map(fn($n) => $n * 2, $numbers);
   $evens = array_filter($doubled, fn($n) => $n % 4 === 0);
   ```

## Əlaqəli Mövzular

- [06-loops.md](06-loops.md) — for range ilə slice-lar
- [09-maps.md](09-maps.md) — map məlumat strukturu
- [07-functions.md](07-functions.md) — funksiyalarda slice parametrlər
- [41-slice-advanced.md](41-slice-advanced.md) — slice daxili mexanizmi dərindən
