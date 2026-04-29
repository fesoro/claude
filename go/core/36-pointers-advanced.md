# Pointer — İrəliləmiş (Senior)

## İcmal

Go-da pointer-lər sadə address saxlamaqdan daha mürəkkəb rol oynayır. Pointer receiver vs value receiver seçimi method set-i müəyyən edir, interface implementasiyanı təsir edir, performansa birbaşa təsir edir. Bu mövzu Go developer-in ən çox çaşqınlıq yaşadığı sahələrdən biridir — Go-da açıq seçim tələb olunur.

## Niyə Vacibdir

- **Method set** — interface implementasiyası üçün pointer vs value receiver-in düzgün seçilməsi
- **Performance** — böyük struct-ları kopyalamaqdan qaçmaq
- **Nil pointer** — production-da `panic` törədən ən tez-tez rast gəlinən xəta
- **Double pointer** — funksiya daxilindən pointer-in özünü dəyişmək lazım gəldikdə
- **Semantik məna** — Go-da pointer açıq göstərilməlidir

## Əsas Anlayışlar

### Value Receiver vs Pointer Receiver

```go
type Counter struct {
    count int
}

// Value receiver — struct-ın KOPYASINI alır
func (c Counter) Value() int {
    return c.count
}

// Pointer receiver — ORIJINALI dəyişə bilər
func (c *Counter) Increment() {
    c.count++
}
```

**Pointer receiver nə vaxt istifadə edilməlidir:**
1. Method struct-ın state-ini dəyişirsə
2. Struct böyükdürsə (kopyalamaq baha olursa)
3. Struct `sync.Mutex` saxlayırsa (copy edilməməlidir)

**Value receiver nə vaxt istifadə edilməlidir:**
1. Method yalnız oxuyursa və struct kiçikdirsə
2. Primitive tip üçün (int, float64, string wrapper-ları)
3. Immutability açıq göstərilmək istənildikdə

### Method Set Qaydası

Bu Go-da ən çox səhv anlaşılan qaydadır:

| Tip | Əlçatan Method-lar |
|-----|-------------------|
| `T` (value) | Yalnız value receiver method-lar |
| `*T` (pointer) | Həm value, həm pointer receiver method-lar |

Bu qaydanın praktik nəticəsi: pointer receiver method-u olan struct, o interface-i ancaq pointer kimi implement edir:

```go
type Stringer interface {
    String() string
}

type MyType struct{ val string }

func (m *MyType) String() string { return m.val } // pointer receiver

var s Stringer = &MyType{"salam"} // OK
var s2 Stringer = MyType{"salam"} // COMPILE XƏTASİ!
```

### Nil Pointer

Go-da nil pointer dereference ən tez-tez `panic` səbəbidir:

```go
var p *int
fmt.Println(*p) // PANIC: nil pointer dereference
```

**Nil-safe method-lar:** Pointer receiver method-lar nil pointer-ə çağırıla bilər, daxilindən yoxlanılırsa:

```go
type Node struct {
    Val  int
    Next *Node
}

func (n *Node) String() string {
    if n == nil {
        return "nil"
    }
    return fmt.Sprintf("%d -> %s", n.Val, n.Next.String())
}
```

### Double Pointer (`**T`)

Funksiya daxilindən pointer-in özünü (address-ini) dəyişmək lazım gəldikdə:

```go
func initialize(pp **int) {
    val := 42
    *pp = &val // pointer-in göstərdiyi yeri dəyiş
}

var p *int
initialize(&p)
fmt.Println(*p) // 42
```

Real use-case: linked list head-i dəyişmək, DI container-də lazy initialization.

### Pointer to Interface Anti-pattern

Go-da `*interface{}` demək olar heç vaxt istifadə edilməməlidir:

```go
// YANLIŞ — pointer to interface
func process(r *io.Reader) { ... }

// DÜZGÜN — interface birbaşa
func process(r io.Reader) { ... }
```

Interface özü artıq pointer semantikası daşıyır (type + value pointer).

### Pointer Müqayisəsi

```go
a := 10
b := 10

pa := &a
pb := &b
pa2 := &a

fmt.Println(pa == pa2) // true — eyni address
fmt.Println(pa == pb)  // false — fərqli address (dəyərlər eyni olsa belə)
fmt.Println(*pa == *pb) // true — dəyərləri müqayisə
```

## Praktik Baxış

### Performance Baxımından

- `int`, `bool`, `float64` kimi kiçik type-lar üçün value receiver daha sürətlidir (pointer indirection yoxdur)
- 64 byte-dan böyük struct-lar üçün pointer receiver daha sürətlidir
- Slice, map, channel — artıq reference type-dır, pointer-ə ehtiyac yoxdur

### Slice ilə Pointer

```go
// Slice özü reference type-dır — pointer vermək lazım deyil
func addItem(s []int, v int) []int {
    return append(s, v)
} // yeni slice qaytarır

// AMMa slice-ın özünü dəyişmək lazımdırsa:
func reset(s *[]int) {
    *s = (*s)[:0]
}
```

### Trade-off-lar

| Seçim | Üstünlük | Çatışmazlıq |
|-------|----------|-------------|
| Value receiver | Kopyalama müdafiəsi, thread-safe | Böyük struct-larda yavaş |
| Pointer receiver | State dəyişikliyi, performans | nil yoxlaması lazım, race condition riski |
| `[]*Struct` | Böyük struct-lar üçün sürətli | GC pressure artır |
| `[]Struct` | Cache-friendly, GC az | Böyük struct copy edilir |

### Anti-pattern-lər

```go
// Anti-pattern 1: Gereksiz pointer
type SmallStruct struct{ x, y int }

func (s *SmallStruct) GetX() int { return s.x } // value receiver kifayət edər

// Anti-pattern 2: Pointer to interface
func Handle(r *io.Reader) { ... } // YANLIŞ — interface pointer olmur

// Anti-pattern 3: nil yoxlaması olmadan
func process(user *User) string {
    return user.Name // PANIC əgər user nil-dirsə
}

// Düzgün:
func process(user *User) string {
    if user == nil {
        return ""
    }
    return user.Name
}

// Anti-pattern 4: Ümumilikdə unnecessary return pointer
// Kiçik struct-ları pointer kimi qaytarmaq şərt deyil
func newPoint() *Point { return &Point{1, 2} } // həmişə heap-ə allocate edilir
func newPoint() Point  { return Point{1, 2} }  // stack allocation mümkün
```

## Nümunələr

### Nümunə 1: Method Set və Interface

```go
package main

import "fmt"

type Animal interface {
    Sound() string
    Move()
}

type Dog struct {
    Name string
    Pos  int
}

// Value receiver — read-only
func (d Dog) Sound() string {
    return "Hav!"
}

// Pointer receiver — state dəyişir
func (d *Dog) Move() {
    d.Pos++
}

func makeAnimalSound(a Animal) {
    fmt.Println(a.Sound())
    a.Move()
}

func main() {
    dog := &Dog{Name: "Boncuk"} // pointer — hər iki method əlçatandır
    makeAnimalSound(dog)

    // dog2 := Dog{Name: "Rex"}
    // makeAnimalSound(dog2) // COMPILE XƏTASİ — Move() pointer receiver tələb edir
}
```

### Nümunə 2: Nil-safe Linked List

```go
package main

import "fmt"

type Node struct {
    Val  int
    Next *Node
}

// nil pointer-ə çağırıla bilən method
func (n *Node) String() string {
    if n == nil {
        return "nil"
    }
    return fmt.Sprintf("%d -> %s", n.Val, n.Next.String())
}

func (n *Node) Sum() int {
    if n == nil {
        return 0
    }
    return n.Val + n.Next.Sum()
}

func main() {
    list := &Node{1, &Node{2, &Node{3, nil}}}
    fmt.Println(list.String()) // 1 -> 2 -> 3 -> nil
    fmt.Println("Cəm:", list.Sum()) // 6

    var empty *Node
    fmt.Println(empty.String()) // nil — panic yox!
    fmt.Println("Boş cəm:", empty.Sum()) // 0
}
```

### Nümunə 3: Double Pointer — Builder Pattern

```go
package main

import "fmt"

type Config struct {
    Host string
    Port int
}

// Pointer-in özünü dəyişir
func initConfig(cfg **Config, defaultHost string) {
    if *cfg == nil {
        *cfg = &Config{
            Host: defaultHost,
            Port: 8080,
        }
    }
}

func main() {
    var cfg *Config
    fmt.Println("Əvvəl:", cfg) // nil

    initConfig(&cfg, "localhost")
    fmt.Println("Sonra:", cfg.Host, cfg.Port) // localhost 8080

    // Artıq nil deyil — initConfig keçmir
    initConfig(&cfg, "production.server.com")
    fmt.Println("Dəyişmyib:", cfg.Host) // localhost
}
```

### Nümunə 4: Pointer Slice — Böyük Struct-lar

```go
package main

import "fmt"

type HeavyStruct struct {
    Data    [1024]byte // 1KB
    ID      int
    Name    string
}

// []*HeavyStruct — pointer slice
// Yenidən sıralama zamanı struct kopyalanmır
func processAll(items []*HeavyStruct) {
    for _, item := range items {
        item.Name = "processed_" + item.Name // orijinalı dəyişir
    }
}

func main() {
    items := []*HeavyStruct{
        {ID: 1, Name: "alpha"},
        {ID: 2, Name: "beta"},
    }

    processAll(items)

    for _, item := range items {
        fmt.Println(item.ID, item.Name)
    }
    // 1 processed_alpha
    // 2 processed_beta
}
```

### Nümunə 5: Function Pointer ilə Strategy Pattern

```go
package main

import "fmt"

type SortFunc func(a, b int) bool

type Sorter struct {
    compare SortFunc
}

func NewSorter(fn SortFunc) *Sorter {
    return &Sorter{compare: fn}
}

func (s *Sorter) Sort(data []int) {
    // Bubble sort — demo üçün
    for i := 0; i < len(data); i++ {
        for j := 0; j < len(data)-i-1; j++ {
            if !s.compare(data[j], data[j+1]) {
                data[j], data[j+1] = data[j+1], data[j]
            }
        }
    }
}

func main() {
    data := []int{5, 2, 8, 1, 9, 3}

    ascending := NewSorter(func(a, b int) bool { return a < b })
    ascending.Sort(data)
    fmt.Println("Artan:", data)

    data = []int{5, 2, 8, 1, 9, 3}
    descending := NewSorter(func(a, b int) bool { return a > b })
    descending.Sort(data)
    fmt.Println("Azalan:", data)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Method Set Araşdırması:**
`Shape` interface yazın: `Area() float64`, `Perimeter() float64`. `Rectangle` struct-ı yaradın. Həm pointer, həm value receiver ilə sınayın. Hansında compile xətası alırsınız?

**Tapşırıq 2 — Safe Pointer Helper:**
`SafeDeref[T any](ptr *T, defaultVal T) T` generic funksiyası yazın. Nil olsa default qaytarsın.

**Tapşırıq 3 — Cache ilə Lazy Init:**
Double pointer istifadə edərək `lazyLoad(ptr **Database, dsn string)` funksiyası yazın. İlk çağırışda connection yaransın, sonrakılarda mövcudu qaytarsın.

**Tapşırıq 4 — Performance Benchmark:**
`testing.B` ilə value receiver vs pointer receiver-in performance-ını ölçün. 1000 byte-lıq struct üçün fərqi müşahidə edin.

## PHP ilə Müqayisə

PHP-də `&$var` optional-dır — obyektlər avtomatik reference ilə ötürülür. Go-da struct-lar default olaraq copy edilir — pointer açıq göstərilməlidir:

```php
// PHP — pass by reference optional-dır
function increment(&$val) {
    $val++;
}

$x = 5;
increment($x); // $x indi 6
```

```go
// Go — açıq pointer
func increment(val *int) {
    *val++
}

x := 5
increment(&x) // x indi 6
```

**Əsas fərq:** PHP-də object-lər avtomatik reference ilə ötürülür. Go-da struct-lar default olaraq copy edilir — pointer açıq göstərilməlidir. PHP-dəki `&$var` isteğe bağlıdır, Go-da isə `&` operatoru semantik məna daşıyır.

## Əlaqəli Mövzular

- [11-pointers](11-pointers.md) — Pointer əsasları
- [17-interfaces](17-interfaces.md) — Interface və method set
- [35-struct-advanced](35-struct-advanced.md) — Struct-ların dərin analizi
- [45-functional-options](../backend/09-functional-options.md) — Pointer receiver ilə options pattern
- [68-profiling-and-benchmarking](../advanced/21-profiling-and-benchmarking.md) — Performance ölçmə
- [69-memory-management](../advanced/22-memory-management.md) — Stack vs heap allocation
