# Generics (Middle)

## İcmal

Go 1.18 (2022) ilə gələn Generics — eyni kodu müxtəlif tiplər üçün ayrı-ayrı yazmaq problemini həll edir. Kompilyasiya zamanı tip yoxlaması ilə type-safe, reusable funksiyalar və data strukturları yazmağa imkan verir.

## Niyə Vacibdir

Generic olmadan ya `interface{}` / `any` istifadə edirsiniz (type safety itirir), ya da hər tip üçün eyni funksiyanı kopyalayırsınız. Go 1.18+ ilə type-safe, reusable data strukturları və utility funksiyaları yazmaq mümkündür. Standart kitabxanadakı `slices`, `maps` paketləri artıq generics ilə yazılmışdır.

## Əsas Anlayışlar

- **Type parameter** — `[T any]` formatında — funksiya/struct üçün növ parametri
- **Constraint** — type parameter-in hansı tiplər ola biləcəyini məhdudlaşdırır
- **`any`** — istənilən tip (`interface{}` ilə ekvivalent)
- **`comparable`** — `==` və `!=` ilə müqayisə oluna bilən tiplər
- **`constraints.Ordered`** — `<`, `>` ilə müqayisə oluna bilən tiplər (golang.org/x/exp/constraints)
- **Union constraint** — `~int | ~float64` — bu tiplər daxil olan constraint
- **`~`** (tilde) — bu tipin özü VƏ ondan törəmiş bütün tiplər

## Praktik Baxış

**Ne vaxt generics, ne vaxt interface:**

| Ssenari | Seçim |
|---------|-------|
| Kolleksiya funksiyaları (Map, Filter) | Generics |
| Data strukturları (Stack, Queue) | Generics |
| Plug-in davranış (sort strategy) | Interface |
| Runtime tip seçimi | Interface |
| Mövcud kod sadədir | Generics lazım deyil |

**Trade-off-lar:**
- Generics kodu oxumağı bir qədər çətinləşdirə bilər — yalnız real faydası olduqda istifadə edin
- Kompilyasiya müddəti bir qədər uzuna çəkir (əhəmiyyətsiz fərq)
- `reflect` əvəzinə generics — həm sürətli, həm type-safe

**Common mistakes:**
- Hər yerdə generics istifadə etmək — "generic generics" — əgər 2-3 tiplə işlənmirsə, sadə funksiya daha yaxşıdır
- `any` ilə işləyərkən type assertion lazım olur — bu generic-in məqsədini aradan qaldırır

## Nümunələr

### Nümunə 1: Generic funksiya — `[T any]`

```go
package main

import "fmt"

// İstənilən tip üçün çap edir
func Çap[T any](dəyərlər []T) {
    for _, v := range dəyərlər {
        fmt.Print(v, " ")
    }
    fmt.Println()
}

// İlk elementi qaytarır
func First[T any](items []T) (T, bool) {
    var sıfır T
    if len(items) == 0 {
        return sıfır, false // zero value qaytarır
    }
    return items[0], true
}

// Son elementi qaytarır
func Last[T any](items []T) (T, bool) {
    var sıfır T
    if len(items) == 0 {
        return sıfır, false
    }
    return items[len(items)-1], true
}

func main() {
    Çap([]int{1, 2, 3, 4, 5})
    Çap([]string{"salam", "dünya"})
    Çap([]float64{1.1, 2.2, 3.3})

    v, ok := First([]string{"a", "b", "c"})
    fmt.Println(v, ok) // a true

    _, ok = First([]int{})
    fmt.Println(ok) // false
}
```

### Nümunə 2: Numeric constraint — `~int | ~float64`

```go
package main

import "fmt"

// Öz constraint-imizi yaradaq
type Ədəd interface {
    ~int | ~int8 | ~int16 | ~int32 | ~int64 |
        ~float32 | ~float64
}

func Cəm[T Ədəd](ədədlər []T) T {
    var toplam T
    for _, n := range ədədlər {
        toplam += n
    }
    return toplam
}

func Orta[T Ədəd](ədədlər []T) float64 {
    if len(ədədlər) == 0 {
        return 0
    }
    return float64(Cəm(ədədlər)) / float64(len(ədədlər))
}

// ~int — int özü VƏ ondan törəmiş tiplər
type Yaş int

func main() {
    fmt.Println("Int cəm:", Cəm([]int{1, 2, 3, 4, 5}))        // 15
    fmt.Println("Float cəm:", Cəm([]float64{1.5, 2.5, 3.0}))  // 7

    fmt.Println("Orta:", Orta([]int{10, 20, 30})) // 20

    // ~int sayəsində custom type də işləyir
    yaşlar := []Yaş{20, 25, 30}
    fmt.Println("Yaş cəmi:", Cəm(yaşlar)) // 75
}
```

### Nümunə 3: constraints.Ordered — müqayisə əməliyyatları

```go
package main

import (
    "fmt"

    "golang.org/x/exp/constraints"
)

func EnBöyük[T constraints.Ordered](ədədlər []T) T {
    max := ədədlər[0]
    for _, v := range ədədlər[1:] {
        if v > max {
            max = v
        }
    }
    return max
}

func EnKiçik[T constraints.Ordered](ədədlər []T) T {
    min := ədədlər[0]
    for _, v := range ədədlər[1:] {
        if v < min {
            min = v
        }
    }
    return min
}

func Sırala[T constraints.Ordered](items []T) []T {
    result := make([]T, len(items))
    copy(result, items)
    // Sadə bubble sort (nümunə üçün)
    for i := 0; i < len(result)-1; i++ {
        for j := 0; j < len(result)-i-1; j++ {
            if result[j] > result[j+1] {
                result[j], result[j+1] = result[j+1], result[j]
            }
        }
    }
    return result
}

func main() {
    fmt.Println("Ən böyük int:", EnBöyük([]int{3, 1, 4, 1, 5, 9}))         // 9
    fmt.Println("Ən böyük string:", EnBöyük([]string{"banan", "alma"}))    // banan

    fmt.Println("Ən kiçik:", EnKiçik([]float64{3.14, 1.41, 2.71})) // 1.41

    fmt.Println("Sıralı:", Sırala([]int{5, 2, 8, 1, 9})) // [1 2 5 8 9]
    fmt.Println("Sıralı:", Sırala([]string{"c", "a", "b"})) // [a b c]
}
```

### Nümunə 4: comparable — Set data strukturu

```go
package main

import "fmt"

// comparable — == ilə müqayisə oluna bilən tiplər
func Ehtiva[T comparable](slice []T, hədəf T) bool {
    for _, v := range slice {
        if v == hədəf {
            return true
        }
    }
    return false
}

// Generic Set
type Set[T comparable] struct {
    elementlər map[T]struct{}
}

func NewSet[T comparable]() *Set[T] {
    return &Set[T]{elementlər: make(map[T]struct{})}
}

func (s *Set[T]) Əlavə(v T) {
    s.elementlər[v] = struct{}{}
}

func (s *Set[T]) Ehtiva(v T) bool {
    _, ok := s.elementlər[v]
    return ok
}

func (s *Set[T]) Sil(v T) {
    delete(s.elementlər, v)
}

func (s *Set[T]) Ölçü() int {
    return len(s.elementlər)
}

func (s *Set[T]) Siyahı() []T {
    result := make([]T, 0, len(s.elementlər))
    for k := range s.elementlər {
        result = append(result, k)
    }
    return result
}

func main() {
    fmt.Println("5 var?", Ehtiva([]int{1, 2, 3, 4, 5}, 5))        // true
    fmt.Println("Go var?", Ehtiva([]string{"Go", "Rust"}, "Go"))   // true

    // Generic Set
    intSet := NewSet[int]()
    intSet.Əlavə(1)
    intSet.Əlavə(2)
    intSet.Əlavə(2) // dublikat — təsir etmir
    intSet.Əlavə(3)

    fmt.Println("Ölçü:", intSet.Ölçü()) // 3
    fmt.Println("2 var?", intSet.Ehtiva(2)) // true

    strSet := NewSet[string]()
    strSet.Əlavə("go")
    strSet.Əlavə("rust")
    fmt.Println("Siyahı:", strSet.Siyahı())
}
```

### Nümunə 5: Generic Stack

```go
package main

import "fmt"

type Yığın[T any] struct {
    elementlər []T
}

func (y *Yığın[T]) Əlavə(dəyər T) {
    y.elementlər = append(y.elementlər, dəyər)
}

func (y *Yığın[T]) Çıxar() (T, bool) {
    var sıfır T
    if len(y.elementlər) == 0 {
        return sıfır, false
    }
    son := y.elementlər[len(y.elementlər)-1]
    y.elementlər = y.elementlər[:len(y.elementlər)-1]
    return son, true
}

func (y *Yığın[T]) Üst() (T, bool) {
    var sıfır T
    if len(y.elementlər) == 0 {
        return sıfır, false
    }
    return y.elementlər[len(y.elementlər)-1], true
}

func (y *Yığın[T]) Boş() bool { return len(y.elementlər) == 0 }
func (y *Yığın[T]) Ölçü() int { return len(y.elementlər) }

func main() {
    // int yığını
    intYığın := &Yığın[int]{}
    intYığın.Əlavə(10)
    intYığın.Əlavə(20)
    intYığın.Əlavə(30)
    fmt.Println("Ölçü:", intYığın.Ölçü()) // 3

    v, _ := intYığın.Çıxar()
    fmt.Println("Çıxarıldı:", v) // 30

    // string yığını
    strYığın := &Yığın[string]{}
    strYığın.Əlavə("birinci")
    strYığın.Əlavə("ikinci")

    top, _ := strYığın.Üst()
    fmt.Println("Üst:", top) // ikinci (çıxarılmır)
    fmt.Println("Ölçü:", strYığın.Ölçü()) // hələ 2
}
```

### Nümunə 6: Funksional Map, Filter, Reduce

```go
package main

import "fmt"

// Map — hər elementi transformasiya edir
func Map[T any, U any](slice []T, f func(T) U) []U {
    nəticə := make([]U, len(slice))
    for i, v := range slice {
        nəticə[i] = f(v)
    }
    return nəticə
}

// Filter — şərtə uyğun elementləri saxlayır
func Filter[T any](slice []T, f func(T) bool) []T {
    var nəticə []T
    for _, v := range slice {
        if f(v) {
            nəticə = append(nəticə, v)
        }
    }
    return nəticə
}

// Reduce — bütün elementləri bir dəyərə endirir
func Reduce[T any, U any](slice []T, başlanğıc U, f func(U, T) U) U {
    nəticə := başlanğıc
    for _, v := range slice {
        nəticə = f(nəticə, v)
    }
    return nəticə
}

func main() {
    ədədlər := []int{1, 2, 3, 4, 5}

    // Map: ikiqat et
    ikiqat := Map(ədədlər, func(n int) int { return n * 2 })
    fmt.Println("İkiqat:", ikiqat) // [2 4 6 8 10]

    // Map: int → string
    stringlər := Map(ədədlər, func(n int) string {
        return fmt.Sprintf("ədəd_%d", n)
    })
    fmt.Println("Stringlər:", stringlər)

    // Filter: yalnız cüt ədədlər
    cütlər := Filter(ədədlər, func(n int) bool { return n%2 == 0 })
    fmt.Println("Cütlər:", cütlər) // [2 4]

    // Reduce: cəm
    cəm := Reduce(ədədlər, 0, func(acc, n int) int { return acc + n })
    fmt.Println("Cəm:", cəm) // 15

    // Reduce: ən böyük
    max := Reduce(ədədlər[1:], ədədlər[0], func(acc, n int) int {
        if n > acc {
            return n
        }
        return acc
    })
    fmt.Println("Maksimum:", max) // 5
}
```

### Nümunə 7: Generic Result tipi

```go
package main

import "fmt"

type Result[T any] struct {
    dəyər T
    xəta  error
}

func Ok[T any](v T) Result[T] {
    return Result[T]{dəyər: v}
}

func Err[T any](err error) Result[T] {
    return Result[T]{xəta: err}
}

func (r Result[T]) IsOk() bool         { return r.xəta == nil }
func (r Result[T]) Unwrap() T          { return r.dəyər }
func (r Result[T]) UnwrapErr() error   { return r.xəta }

func (r Result[T]) UnwrapOr(default_ T) T {
    if r.IsOk() {
        return r.dəyər
    }
    return default_
}

func divide(a, b float64) Result[float64] {
    if b == 0 {
        return Err[float64](fmt.Errorf("sıfıra bölmə")  )
    }
    return Ok(a / b)
}

func main() {
    r1 := divide(10, 2)
    fmt.Println("OK:", r1.IsOk(), r1.Unwrap()) // OK: true 5

    r2 := divide(10, 0)
    fmt.Println("OK:", r2.IsOk(), r2.UnwrapErr()) // OK: false sıfıra bölmə

    fmt.Println("Default:", r2.UnwrapOr(0)) // 0
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1: Generic cache**

Generic in-memory cache yaz: `Cache[K comparable, V any]`. Metodlar: `Set(key K, value V, ttl time.Duration)`, `Get(key K) (V, bool)`, `Delete(key K)`. TTL keçdikdə element avtomatik silinsin.

```go
// Strukturu:
// type entry[V any] struct { value V; expiresAt time.Time }
// type Cache[K comparable, V any] struct { mu sync.RWMutex; items map[K]entry[V] }
// Background goroutine köhnə elementləri təmizləsin
```

**Tapşırıq 2: slices paketi analoju**

`slices` standart paketinin analog funksiyalarını generics ilə yazın: `Contains`, `Index`, `Compact` (dublikatları sil), `Reverse`, `Chunk` (n ölçülü hissələrə böl).

```go
// Chunk[T any](slice []T, size int) [][]T
// Reverse[T any](slice []T) []T
// Index[T comparable](slice []T, v T) int — tapılmasa -1
```

**Tapşırıq 3: Tip-safe event system**

Generic event system yaz. `EventBus[T any]`, `Subscribe(handler func(T))`, `Publish(event T)`. Subscriber-lər goroutine-də çağırılsın.

```go
// type EventBus[T any] struct { handlers []func(T); mu sync.RWMutex }
// bus := NewEventBus[OrderEvent]()
// bus.Subscribe(func(e OrderEvent) { fmt.Println(e) })
// bus.Publish(OrderEvent{ID: 123})
```

## Ətraflı Qeydlər

**Go 1.21+ `slices` və `maps` paketləri:**

```go
import "slices"
import "maps"

// Artıq özünüz yazmağa ehtiyac yoxdur:
slices.Contains([]int{1,2,3}, 2) // true
slices.Max([]int{3,1,4})         // 4
slices.Reverse(slice)
maps.Keys(m)
maps.Values(m)
```

**Type inference — tipi açıq yazmaq lazım deyil:**

```go
// Go tipi çıxarır:
Map([]int{1,2,3}, func(n int) string { return fmt.Sprint(n) })
// T = int, U = string — aydındır

// Bəzən açıq yazılmalı:
First[string]([]string{}) // boş slice üçün
```

## PHP ilə Müqayisə

```php
// PHP — type safety yoxdur:
function first(array $items): mixed {
    return $items[0] ?? null; // return tipi bilinmir
}

// PHP 8+ docblock ilə (editor hint, runtime yoxlama yox):
/** @template T
 *  @param T[] $items
 *  @return T|null
 */
function first(array $items): mixed { ... }
```

```go
// Go generics — compile time type safety:
func First[T any](items []T) (T, bool) {
    if len(items) == 0 {
        var zero T
        return zero, false
    }
    return items[0], true
}
```

PHP-də `mixed` tip və ya docblock yalnız editor hint-dir; runtime-da yoxlanmır. Go generics kompilyasiya zamanı tip yoxlaması aparır — yanlış tip istifadəsi build xətası verir.

## Əlaqəli Mövzular

- `17-interfaces` — generics vs interface seçimi
- `19-type-assertions` — any ilə işləmək
- `41-slice-advanced` — slice əməliyyatları (Go 1.21 slices paketi)
- `44-data-structures` — generic tree, queue, heap
- `60-reflection` — runtime tip introspection (generics alternativi)
