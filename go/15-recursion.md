# Rekursiya — Recursion (Junior)

## İcmal

Rekursiya — funksiyayın özünü çağırmasıdır. Hər rekursiv funksiyada mütləq **dayandırma şərti** (base case) olmalıdır — əks halda sonsuz rekursiya → stack overflow. Go-da stack dinamik olaraq böyüyür (başlanğıcda ~8KB, lazım olduqda 1GB-a qədər), bu PHP-dəkindən fərqlidir. Rekursiya mürəkkəb ağac-bazalı strukturları (fayl sistemi, JSON, DOM) sadə kodla emal etmək üçün güclü vasitədir.

## Niyə Vacibdir

Backend developer üçün rekursiya bilavasitə lazım olduğu ssenarilər var: fayl sistemi gəzmək (qovluq içindəki bütün faylları tapmaq), JSON/XML ağaclarını parse etmək, kateqoriya ağaclarını render etmək (categories with subcategories), binary search. Memoization isə rekursiyanı practical edir — Fibonacci-nin naiv rekursiyası `fib(40)` üçün milyard əməliyyat edir; memoization ilə anında həll edir.

## Əsas Anlayışlar

- **Base case (dayandırma şərti)** — rekursiyanın bitdiyi nöqtə; mütləq olmalıdır
- **Recursive case** — özünü çağıran hissə; hər dəfə problemin ölçüsünü azaltmalıdır
- **Call stack** — hər rekursiv çağırış yeni stack frame əlavə edir; çox dərin rekursiya overflow verir
- **Memoization** — artıq hesablanmış nəticələri cache-ləmək; rekursiyanın effektivliyini dramatik artırır
- **Tail recursion** — son əməliyyat özünü çağırmaq; Go-da tail call optimization yoxdur, amma pattern bilinməlidir
- **Divide and Conquer** — rekursiyanın əsas strategiyası: problemi kiçik hissələrə böl, həll et, birləşdir
- **Binary Search** — sıralanmış massivdə rekursiv axtarış; hər addımda axtarış sahəsini yarıya endirir

## Praktik Baxış

**Real layihədə istifadə:**
- Fayl sistemi gəzmək: `filepath.Walk` daxilən rekursivdir
- Kateqoriya ağacı: DB-dən `parent_id` ilə çəkilmiş siyahıdan ağac qurmaq
- JSON ağac parse: `interface{}` üzərindən rekursiv gəzmək
- Fibonacci memoization: `sync.Map` ilə concurrent-safe memo
- Directory tree: terminal-da `tree` komutu çıxışı kimi

**PHP ilə fərqi:**
- PHP: `function fib($n) { return $n <= 1 ? $n : fib($n-1) + fib($n-2); }` — eyni syntax demək olar
- PHP max recursion `xdebug.max_nesting_level` ilə məhdudlaşır (default 256); Go-da dinamik böyüyür
- PHP-də memoization üçün `static $memo = []` istifadə olunur; Go-da `map` parametr kimi ötürülür
- Go-da tail call optimization yoxdur; dərin rekursiya lazımsa iterativ versiyaya çevirin

**Trade-off-lar:**
- Rekursiya vs İterasiya: rekursiya oxunaqlı, amma overhead-li; iterasiya effektiv, amma mürəkkəb
- Memoization: yaddaş istifadəsi artır, amma sürət dramatik yaxşılaşır
- Çox dərin rekursiya (10000+ çağırış) üçün iterativ çevirmə düşünün

**Common mistakes:**
- Dayandırma şərtini unutmaq — sonsuz rekursiya → stack overflow (panics runtime: goroutine stack exceeds)
- Hər rekursiv çağırışda problemi kiçiltməmək — sonsuz döngü
- Memoizasiyasız Fibonacci-ni böyük N üçün çağırmaq — eksponensial vaxt

## Nümunələr

### Nümunə 1: Klassik rekursiya nümunələri

```go
package main

import "fmt"

// Faktorial: 5! = 5 * 4 * 3 * 2 * 1 = 120
func faktorial(n int) int {
    if n <= 1 {         // base case — dayandırma şərti
        return 1
    }
    return n * faktorial(n-1) // recursive case
}

// Güvvət: base^exp
func guvvet(base, exp int) int {
    if exp == 0 {
        return 1
    }
    return base * guvvet(base, exp-1)
}

// String tərsinə çevirmək
func tersine(s string) string {
    runes := []rune(s) // Azərbaycan hərfləri üçün
    if len(runes) <= 1 {
        return s
    }
    return tersine(string(runes[1:])) + string(runes[0])
}

// Slice cəmi
func sliceCem(ededler []int) int {
    if len(ededler) == 0 {
        return 0
    }
    return ededler[0] + sliceCem(ededler[1:])
}

func main() {
    fmt.Println("5! =", faktorial(5))   // 120
    fmt.Println("10! =", faktorial(10)) // 3628800

    fmt.Println("2^10 =", guvvet(2, 10)) // 1024

    fmt.Println("'salam' tərsi:", tersine("salam")) // malas

    fmt.Println("Cəm:", sliceCem([]int{1, 2, 3, 4, 5})) // 15
}
```

### Nümunə 2: Fibonacci — naiv vs memoization

```go
package main

import (
    "fmt"
    "time"
)

// Naiv Fibonacci — eksponensial vaxt O(2^n)
func fibNaiv(n int) int {
    if n <= 0 { return 0 }
    if n == 1 { return 1 }
    return fibNaiv(n-1) + fibNaiv(n-2)
}

// Memoization ilə — lineer vaxt O(n)
func fibMemo(n int, memo map[int]int) int {
    if n <= 0 { return 0 }
    if n == 1 { return 1 }
    if val, ok := memo[n]; ok {
        return val // cache-dən qaytır
    }
    result := fibMemo(n-1, memo) + fibMemo(n-2, memo)
    memo[n] = result // cache-ə yaz
    return result
}

func main() {
    // Naiv — fib(35) belə yavaş
    start := time.Now()
    fmt.Println("fib(35) naiv:", fibNaiv(35))
    fmt.Println("Naiv vaxt:", time.Since(start))

    // Memoization ilə — fib(50) anında
    start = time.Now()
    memo := make(map[int]int)
    fmt.Println("fib(50) memo:", fibMemo(50, memo))
    fmt.Println("Memo vaxt:", time.Since(start))

    // İlk 10 Fibonacci ədədi
    memo2 := make(map[int]int)
    fmt.Print("Fibonacci: ")
    for i := 0; i < 10; i++ {
        fmt.Print(fibMemo(i, memo2), " ")
    }
    fmt.Println()
}
```

### Nümunə 3: Ağac strukturu — real istifadə

```go
package main

import "fmt"

// Kateqoriya ağacı — DB-dən gələn parent_id strukturunu əks etdirir
type Category struct {
    ID       int
    Name     string
    Children []*Category
}

// Ağacı render et — fayl sistemi tree kimi
func renderTree(cat *Category, prefix string, sonuncu bool) {
    budak := "├── "
    if sonuncu {
        budak = "└── "
    }
    fmt.Println(prefix + budak + cat.Name)

    yeniPrefix := prefix + "│   "
    if sonuncu {
        yeniPrefix = prefix + "    "
    }

    for i, child := range cat.Children {
        renderTree(child, yeniPrefix, i == len(cat.Children)-1)
    }
}

// Ağacda element tap — DFS
func tap(cat *Category, id int) *Category {
    if cat.ID == id {
        return cat
    }
    for _, child := range cat.Children {
        if found := tap(child, id); found != nil {
            return found
        }
    }
    return nil
}

// Binary Search — rekursiv
func binarySearch(arr []int, hedef, sol, sag int) int {
    if sol > sag {
        return -1
    }
    orta := (sol + sag) / 2
    if arr[orta] == hedef {
        return orta
    }
    if hedef < arr[orta] {
        return binarySearch(arr, hedef, sol, orta-1)
    }
    return binarySearch(arr, hedef, orta+1, sag)
}

func main() {
    // Kateqoriya ağacı
    root := &Category{
        ID: 1, Name: "Elektronika",
        Children: []*Category{
            {
                ID: 2, Name: "Telefonlar",
                Children: []*Category{
                    {ID: 4, Name: "Android"},
                    {ID: 5, Name: "iPhone"},
                },
            },
            {
                ID: 3, Name: "Noutbuklar",
                Children: []*Category{
                    {ID: 6, Name: "Gaming"},
                    {ID: 7, Name: "Ofis"},
                },
            },
        },
    }

    fmt.Println(root.Name)
    for i, child := range root.Children {
        renderTree(child, "", i == len(root.Children)-1)
    }

    // Tap
    found := tap(root, 5)
    if found != nil {
        fmt.Println("\nTapıldı:", found.Name)
    }

    // Binary Search
    arr := []int{1, 3, 5, 7, 9, 11, 13, 15}
    idx := binarySearch(arr, 7, 0, len(arr)-1)
    fmt.Println("\nBinary Search 7 → index:", idx) // 3
    idx2 := binarySearch(arr, 10, 0, len(arr)-1)
    fmt.Println("Binary Search 10 → index:", idx2) // -1
}
```

## Praktik Tapşırıqlar

1. **Fayl sistemi scanner**: `scanDir(path string) []string` — verilmiş qovluqdakı bütün `.go` fayllarını rekursiv tapır. `os.ReadDir` + rekursiya istifadə et. `.git` qovluğunu atla.

2. **JSON ağac printer**: `map[string]interface{}` (nested JSON) alan, ağac şəklində (──, └──) çap edən rekursiv funksiya yaz. Dərinlik artdıqca indent əlavə olunmalıdır.

3. **Hanoi qüllələri**: `hanoi(n int, menbe, hedef, komekci string)` — 3 disk üçün bütün hərəkətləri çap et, neçə hərəkət lazım olduğunu say. Formul `2^n - 1`.

4. **Memoization ilə kombinasiya**: `C(n, k) = C(n-1, k-1) + C(n-1, k)` rekursiyanı memoization ilə implement et. `C(20, 10)` dəyərini hesabla. Naiv vs memo sürətini müqayisə et.

## Əlaqəli Mövzular

- [07-functions.md](07-functions.md) — funksiyalar, closure
- [08-arrays-and-slices.md](08-arrays-and-slices.md) — slicing rekursiyada
- [09-maps.md](09-maps.md) — memoization üçün map
- [10-structs.md](10-structs.md) — ağac strukturları
- [44-data-structures.md](44-data-structures.md) — data structures dərindən
