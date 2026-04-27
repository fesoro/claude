# Slice Advanced — Daxili Mexanizm və Performans (Senior)

## İcmal

Go slice-ı sadə bir array wrapper deyil — backing array, uzunluq (`len`) və tutum (`cap`) üçlüyündən ibarət bir header-dir. Bu daxili quruluşu anlamadan yazdığın kod həm düzgün işləməyə bilər, həm de lazımsız memory allocation baş verə bilər. `append` edge cases-ləri, `copy` davranışı, slice-ların bir-birini "gördüyü" hallar — bütün bunlar Go-da performans açısından kritik bilikdir.

## Niyə Vacibdir

- `append` lazımsız istifadəsi N kvadrat mürəkkəblik yaradır (her append-də kopyalama)
- Slice-ları funksiyalara ötürəndə "in-place" dəyişiklik backing array-ı dəyişir — gözlənilməz davranış
- `cap` pre-allocation ilə allocation sayını kəskin azaltmaq olar — benchmark fərqi 10x ola bilər
- `copy` istifadə etmədən "kopyalandığını" düşündüyün slice əslində orijinalı paylaşır

## Əsas Anlayışlar

**Slice header — 3 sahə:**

```
┌─────────┬─────┬─────┐
│ pointer │ len │ cap │
└─────────┴─────┴─────┘
     │
     ↓
┌────┬────┬────┬────┬────┐
│ 1  │ 2  │ 3  │ 4  │ 5  │  ← backing array
└────┴────┴────┴────┴────┘
```

**`len`** — slice-in görünən element sayı.

**`cap`** — backing array-ın slice-in başından sonuna qədər tutumu.

**`append` davranışı:**

- `cap` əksilsə, yeni (daha böyük) backing array ayrılır
- Əks halda, eyni backing array-a yazılır — digər slice-ları da təsir edə bilər

**Growth factor:** Go 1.18+ kiçik slice-lar üçün 2x, böyük slice-lar üçün 1.25x artım istifadə edir.

**`slices` paketi (Go 1.21+):**

```go
slices.Equal, slices.Sort, slices.SortFunc
slices.Reverse, slices.Compact
slices.Contains, slices.Index, slices.Min, slices.Max
slices.Collect // iterator-dan slice yarat (Go 1.23+)
```

## Praktik Baxış

**Pre-allocation — ne vaxt lazımdır:**

```go
// Pis — hər append yeni allocation ola bilər
var result []int
for i := 0; i < 10000; i++ {
    result = append(result, i)
}

// Yaxşı — son ölçü məlumsa
result := make([]int, 0, 10000)
for i := 0; i < 10000; i++ {
    result = append(result, i)
}
```

**Funksiyaya ötürmə — paylaşılan backing array:**

```go
func double(s []int) {
    for i := range s {
        s[i] *= 2 // orijinal array dəyişir!
    }
}

original := []int{1, 2, 3}
double(original)
fmt.Println(original) // [2 4 6] — dəyişdi!
```

**Tam copy üçün:**

```go
clone := make([]int, len(original))
copy(clone, original)
```

**Trade-off-lar:**

- `append` kiçik ölçülü chunklar üçün qəbul olunan; böyük veri setiləri üçün `make([]T, 0, n)` prefer et
- `copy` tam izolyasiya verir amma allocation + copy vaxtı lazımdır
- `slices.Compact` ardıcıl dublikatları silir amma sort lazımdır — `map` ilə unique daha ümumi

**Anti-pattern-lər:**

- `== nil` slice yoxlaması yerinə `len(s) == 0` istifadə et — `nil` slice də `len = 0` olur, amma `nil` deyil initialized boş slice
- `a = append(a[:i], a[i+1:]...)` ilə silmə — sıra saxlanılırsa O(n), sıra lazım deyilsə swap-and-pop O(1)
- Böyük array-in kiçik bir slice-ını saxlamaq — orijinal array GC-dən qorunur (memory leak)
- `reflect.DeepEqual` slice müqayisəsi — `slices.Equal` daha sürətlidir

## Nümunələr

### Nümunə 1: Backing Array — Paylaşma Mexanizmi

```go
package main

import "fmt"

func main() {
    // Orijinal array
    a := []int{1, 2, 3, 4, 5}
    fmt.Printf("a: %v, len=%d, cap=%d\n", a, len(a), cap(a))

    // b — eyni backing array, [1,3] aralığı
    b := a[1:3]
    fmt.Printf("b: %v, len=%d, cap=%d\n", b, len(b), cap(b))
    // b = [2 3], len=2, cap=4 (a[1]-dən sona qədər)

    // b-yə yazma — a-nı da dəyişir!
    b[0] = 99
    fmt.Println("a sonra:", a) // [1 99 3 4 5] — a da dəyişdi!

    // append — cap-ı aşırsa yeni array ayrılır
    c := append(b, 100)
    fmt.Printf("c: %v, len=%d, cap=%d\n", c, len(c), cap(c))
    // c, b-nin backing array-ına yazıb — a da dəyişə bilər!
    fmt.Println("a append-dən sonra:", a) // [1 99 3 100 5]

    // İzolyasiya üçün full slice expression — yalnız lazım olan cap
    d := a[1:3:3] // [low:high:max] — cap = max - low = 2
    fmt.Printf("d: %v, cap=%d\n", d, cap(d))
    // append artıq yeni array ayırır — a-yı dəyişmir
    e := append(d, 999)
    fmt.Println("a izolyasiyadan sonra:", a) // dəyişməyib
    fmt.Println("e:", e)
}
```

### Nümunə 2: append Edge Cases

```go
package main

import "fmt"

func main() {
    // nil slice — append işləyir
    var s []int
    fmt.Println("nil:", s == nil, len(s), cap(s)) // true, 0, 0
    s = append(s, 1, 2, 3)
    fmt.Println("sonra:", s) // [1 2 3]

    // Artım pattern-i izlə
    var a []int
    prevCap := 0
    for i := 0; i < 20; i++ {
        a = append(a, i)
        if cap(a) != prevCap {
            fmt.Printf("len=%d, cap=%d (yeni allocation)\n", len(a), cap(a))
            prevCap = cap(a)
        }
    }
    // Go growth: 0→1→2→4→8→16→...

    // Slice-ları birləşdirmək
    x := []int{1, 2, 3}
    y := []int{4, 5, 6}
    z := append(x, y...) // ... ilə slice-ı açarsan
    fmt.Println("birləşmiş:", z)

    // Pre-allocation — performans
    const N = 100_000
    result := make([]int, 0, N) // allocation bir dəfə
    for i := 0; i < N; i++ {
        result = append(result, i)
    }
    fmt.Println("son len:", len(result), "cap:", cap(result))
}
```

### Nümunə 3: copy — Tam Kopyalama

```go
package main

import "fmt"

func main() {
    src := []int{1, 2, 3, 4, 5}

    // Tam kopyalama
    dst := make([]int, len(src))
    n := copy(dst, src)
    fmt.Printf("kopyalandı: %d element\n", n)
    fmt.Println("dst:", dst)

    dst[0] = 99
    fmt.Println("src dəyişmədi:", src) // [1 2 3 4 5]

    // Qismən kopyalama — minimum len qədər
    short := make([]int, 3)
    copy(short, src) // yalnız 3 element
    fmt.Println("qismən:", short) // [1 2 3]

    // Byte kopyalama — string → []byte
    str := "salam"
    b := make([]byte, len(str))
    copy(b, str)
    fmt.Println("byte:", b)

    // copy ilə element silmə — sıranı qoruyaraq
    s := []int{1, 2, 3, 4, 5}
    i := 2 // 3-ü sil
    copy(s[i:], s[i+1:]) // [i+1:] → [i:] köçür
    s = s[:len(s)-1]      // son elementi kes
    fmt.Println("silinmiş:", s) // [1 2 4 5]
}
```

### Nümunə 4: Performans — Benchmark Nümunəsi

```go
package main

import (
    "fmt"
    "testing"
)

// Pis — hər addımda allocation ola bilər
func buildSliceSlow(n int) []int {
    var result []int
    for i := 0; i < n; i++ {
        result = append(result, i*i)
    }
    return result
}

// Yaxşı — bir dəfə allocation
func buildSliceFast(n int) []int {
    result := make([]int, 0, n)
    for i := 0; i < n; i++ {
        result = append(result, i*i)
    }
    return result
}

// Daha sürətli — birbaşa index ilə
func buildSliceFastest(n int) []int {
    result := make([]int, n)
    for i := 0; i < n; i++ {
        result[i] = i * i
    }
    return result
}

// Benchmark üçün (go test -bench=. -benchmem):
// func BenchmarkSlow(b *testing.B) {
//     for b.Loop() { buildSliceSlow(10000) }
// }
// func BenchmarkFast(b *testing.B) {
//     for b.Loop() { buildSliceFast(10000) }
// }

func main() {
    fmt.Println(buildSliceFastest(5)) // [0 1 4 9 16]
}
```

### Nümunə 5: slices paketi (Go 1.21+)

```go
package main

import (
    "fmt"
    "slices"
)

type Person struct {
    Ad  string
    Yas int
}

func main() {
    // Equal
    a := []int{1, 2, 3}
    b := []int{1, 2, 3}
    fmt.Println("Equal:", slices.Equal(a, b)) // true

    // Sort — sadə tiplər
    nums := []int{5, 2, 8, 1, 9}
    slices.Sort(nums)
    fmt.Println("Sort:", nums) // [1 2 5 8 9]

    // SortFunc — struct
    people := []Person{
        {"Veli", 30}, {"Eli", 25}, {"Orkhan", 28},
    }
    slices.SortFunc(people, func(a, b Person) int {
        return a.Yas - b.Yas // ascending
    })
    fmt.Println("Yasa görə:", people)

    // IsSortedFunc
    fmt.Println("Sıralanıb:", slices.IsSortedFunc(people, func(a, b Person) int {
        return a.Yas - b.Yas
    }))

    // Reverse
    slices.Reverse(nums)
    fmt.Println("Ters:", nums) // [9 8 5 2 1]

    // Contains, Index
    fmt.Println("Contains 8:", slices.Contains(nums, 8))  // true
    fmt.Println("Index of 5:", slices.Index(nums, 5))     // 2

    // Min, Max
    fmt.Println("Min:", slices.Min(nums)) // 1
    fmt.Println("Max:", slices.Max(nums)) // 9

    // Compact — ardıcıl dublikatları sil
    dups := []int{1, 1, 2, 2, 2, 3}
    unique := slices.Compact(dups)
    fmt.Println("Compact:", unique) // [1 2 3]

    // Compare
    x := []int{1, 2, 3}
    y := []int{1, 2, 4}
    fmt.Println("Compare:", slices.Compare(x, y)) // -1 (x < y)
}
```

### Nümunə 6: Praktik Əməliyyatlar — Chunk, Filter, Map

```go
package main

import "fmt"

// Slice-ı N ölçülü hissələrə böl
func Chunk[T any](s []T, size int) [][]T {
    if size <= 0 {
        return nil
    }
    chunks := make([][]T, 0, (len(s)+size-1)/size)
    for i := 0; i < len(s); i += size {
        end := i + size
        if end > len(s) {
            end = len(s)
        }
        chunks = append(chunks, s[i:end])
    }
    return chunks
}

// Şərtə uyğun elementləri filtrlə
func Filter[T any](s []T, fn func(T) bool) []T {
    result := make([]T, 0, len(s))
    for _, v := range s {
        if fn(v) {
            result = append(result, v)
        }
    }
    return result
}

// Hər elementi çevir
func Map[T, U any](s []T, fn func(T) U) []U {
    result := make([]U, len(s))
    for i, v := range s {
        result[i] = fn(v)
    }
    return result
}

// Unique — sırasız, map ilə
func Unique[T comparable](s []T) []T {
    seen := make(map[T]struct{}, len(s))
    result := make([]T, 0, len(s))
    for _, v := range s {
        if _, ok := seen[v]; !ok {
            seen[v] = struct{}{}
            result = append(result, v)
        }
    }
    return result
}

func main() {
    nums := []int{1, 2, 3, 4, 5, 6, 7, 8, 9}

    fmt.Println("Chunk(3):", Chunk(nums, 3))
    // [[1 2 3] [4 5 6] [7 8 9]]

    evens := Filter(nums, func(n int) bool { return n%2 == 0 })
    fmt.Println("Evens:", evens) // [2 4 6 8]

    squares := Map(nums, func(n int) int { return n * n })
    fmt.Println("Squares:", squares) // [1 4 9 16 25 36 49 64 81]

    mixed := []int{1, 2, 2, 3, 3, 3, 4}
    fmt.Println("Unique:", Unique(mixed)) // [1 2 3 4] — sıra ola bilər fərqli
}
```

### Nümunə 7: Memory Leak — Böyük Array Saxlamaq

```go
package main

import "fmt"

// Problem: böyük array-in kiçik slice-ını saxlamaq
func getFirstThree() []int {
    bigSlice := make([]int, 1_000_000)
    // ... doldurmaq...
    return bigSlice[:3] // 3 element gəlir, amma 1M backing array GC-dən qorunur!
}

// Həll: copy ilə tam izolyasiya
func getFirstThreeSafe() []int {
    bigSlice := make([]int, 1_000_000)
    result := make([]int, 3)
    copy(result, bigSlice[:3]) // yalnız 3 element kopyalanır
    return result              // bigSlice GC-yə buraxılır
}

// Məsləhət: funksiya böyük slice-dan kiçik seqment qaytarırsa — copy et
func main() {
    a := getFirstThree()
    b := getFirstThreeSafe()
    fmt.Println(a, b)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Generic Utility Funksiyaları**

Aşağıdakı generic funksiyaları yaz:
- `Reduce[T, U any](s []T, init U, fn func(U, T) U) U` — sum, product, concat
- `GroupBy[T any, K comparable](s []T, key func(T) K) map[K][]T` — qruplama
- `Flatten[T any](s [][]T) []T` — [[1,2],[3,4]] → [1,2,3,4]
- `Zip[T, U any](a []T, b []U) []struct{ A T; B U }` — paralel birləşdirmə

**Tapşırıq 2 — Benchmark**

```go
// 100_000 elementli slice üçün aşağıdakıları benchmark et:
// 1. Allocation olmadan (make + cap)
// 2. Hər dəfə append (cap-siz)
// 3. İndeks ilə (make([]T, n))
// go test -bench=. -benchmem -count=5
```

Nəticələri müqayisə et. Allocation sayını (`allocs/op`) ön plana çıxar.

**Tapşırıq 3 — Backing Array Paylaşma Bug-ı**

Aşağıdakı bug-ı tap və düzəlt:

```go
func splitAndProcess(data []int) ([]int, []int) {
    mid := len(data) / 2
    left := data[:mid]
    right := data[mid:]

    // "Sol" hissəyə əlavə et
    left = append(left, 999)
    // BUG: right-ın birinci elementi dəyişdi!
    return left, right
}
```

**Tapşırıq 4 — Stream Processing**

1M integer-dan ibarət slice-ı 10K-lıq chunk-lara bölüb hər chunk-ı goroutine-də paralel emal et. Nəticəni toplayıb qaytart. `sync.WaitGroup` + channel istifadə et.

## PHP ilə Müqayisə

PHP `array` yenidən atananda daima copy olunur (copy-on-write). Go slice-ı isə referans kimi davranır — iki dəyişkən eyni underlying array-ı göstərə bilər. Bu fərq bug-ların əsas mənbəyidir.

## Əlaqəli Mövzular

- [08-arrays-and-slices](08-arrays-and-slices.md) — Slice əsasları
- [29-generics](29-generics.md) — Generic utility funksiyalar
- [27-goroutines-and-channels](27-goroutines-and-channels.md) — Parallel slice emalı
- [69-memory-management](69-memory-management.md) — GC, allocation, escape analysis
- [68-profiling-and-benchmarking](68-profiling-and-benchmarking.md) — Slice performansı ölçmək
- [44-data-structures](44-data-structures.md) — Daha mürəkkəb data strukturları
