# Memory Management (Architect)

## İcmal

Go-da yaddaş idarəetməsi avtomatikdir — Garbage Collector (GC) lazımsız obyektləri silir. Lakin "avtomatik" "nəzarətsiz" demək deyil. Architect səviyyəsində GC-nin necə işlədiyini, heap/stack ayrımını, escape analysis-i və yaddaş optimallaşdırma texnikalarını dərin başa düşmək production performansını əhəmiyyətli dərəcədə artırır.

## Niyə Vacibdir

- Memory leak Go-da mümkündür — GC hər şeyi sehrlə silmir
- Yanlış yaddaş strukturları GC pause-larını artırır və latency spike-lar yaradır
- Struct field sıralaması tək dəyişikliklə 25-33% yaddaş qənaəti verə bilər
- Escape analysis başa düşülmədən yazılan kod gərəksiz heap allocation-lara gətirir
- `GOGC` və `GOMEMLIMIT` tuning ilə GC davranışını production-a uyğunlaşdırmaq olar

## Əsas Anlayışlar

**Stack vs Heap:**
- **Stack**: Sürətli, funksiya çağırışı ilə avtomatik ayrılır/azad edilir, ölçüsü məhduddur (goroutine stack 2KB-dən başlayır, avtomatik böyüyür)
- **Heap**: GC tərəfindən idarə olunur, ölçüsü böyükdür, nisbətən yavaş

**Escape Analysis:**
- Kompilyator hər dəyişkən üçün qərar verir: stack mi, heap mi?
- Pointer qaytarılan dəyişkən heap-ə "qaçır" (escapes)
- Interface-ə atama qaçışa səbəb olur (boxing)
- Closure xarici dəyişkənə istinad — heap-ə qaçış

**GC (Garbage Collector):**
- Go tri-color mark-and-sweep GC istifadə edir
- GC pause-ları minimumdur (concurrent GC)
- `GOGC=100` (default) — heap ikiqat böyüyəndə GC işləyir
- `GOMEMLIMIT` (Go 1.19+) — maksimal yaddaş limiti

**Yaddaş strukturu anlayışları:**
- **Alignment**: CPU sözləri hizalı ünvandan oxuyur — struct padding buna görədir
- **Cache line**: 64 byte, eyni cache line-da olan məlumat birlikdə CPU-ya gəlir
- **False sharing**: müxtəlif goroutine-lər eyni cache line-dakı fərqli dəyişkənləri dəyişdirsə

## Praktik Baxış

**Nə vaxt yaddaş optimallaşdırması vacibdir:**
- Yüz minlərlə eyni tipli struct yaradılırsa (məs: event streaming)
- GC pause-ları latency SLO-nu pozursa
- Container limiti aşılırsa (OOMKilled)
- Memory profil zamanı allocation hot spot aşkarlanırsa

**Nə vaxt lazım deyil:**
- CRUD API-lar üçün adi hallarda
- Throughput yüksəkdirsə amma latency qəbuledilərsə
- "Görünür ki yaddaş çoxdur" — əvvəl profile et

**Trade-off-lar:**
- `GOGC=200` → daha az GC, çox yaddaş; `GOGC=50` → tez-tez GC, az yaddaş, çox CPU
- `sync.Pool` → allocation azalır, amma pool-dan çıxan obyektin təmiz olduğuna əmin olun
- Struct field sıralaması → yaddaş azalır, amma readability azalır (comment yazın)
- `[]byte` → `string` konversiyasından qaçın — hər dəfə kopyalama olur

**Common mistakes:**
- Böyük slice-dan kiçik dilim almaq — hər ikisi eyni backing array-ə istinad edir
- Goroutine leak — goroutine-lər dayandırılmasa yaddaşda qalır
- Global slice-a append etmək — GC silə bilmir
- Timer/Ticker-i `Stop()` etməmək — goroutine leak

## Nümunələr

### Nümunə 1: Stack vs Heap — escape analysis

```go
package main

import (
    "fmt"
    "runtime"
)

// Stack-də qalan — pointer qaytarmır
func stackDeQal() int {
    x := 42 // stack-də
    return x // dəyər kopyalanır, x stack-dən çıxanda silinir
}

// Heap-ə qaçan — pointer qaytarır
func heapeQac() *int {
    x := 42
    return &x // x heap-ə "qaçır" — funksiya bitəndən sonra da lazımdır
}

// Interface-ə atama — heap-ə qaçış
func interfaceEscape() {
    x := 42
    var i interface{} = x // x heap-ə qaçır (boxing)
    fmt.Println(i)        // fmt.Println da escape edir
}

// Escape analysis-i yoxlamaq:
// go build -gcflags="-m" main.go
// go build -gcflags="-m -m" main.go   (ətraflı)

// Çıxış nümunəsi:
// ./main.go:15:9: &x escapes to heap
// ./main.go:21:14: x escapes to heap

func main() {
    v1 := stackDeQal()
    v2 := heapeQac()
    fmt.Println(v1, *v2)

    var m runtime.MemStats
    runtime.ReadMemStats(&m)
    fmt.Printf("Alloc: %d KB\n", m.Alloc/1024)
    fmt.Printf("NumGC: %d\n", m.NumGC)
}
```

### Nümunə 2: Struct padding və field sıralaması

```go
package main

import (
    "fmt"
    "unsafe"
)

// Yanlış sıralama — çox yaddaş (24 byte)
type PisStruct struct {
    a bool    // 1 byte + 7 byte padding (float64 üçün hizalanma)
    b float64 // 8 byte
    c bool    // 1 byte + 3 byte padding (int32 üçün hizalanma)
    d int32   // 4 byte
    // Cəm: 1+7+8+1+3+4 = 24 byte
}

// Düzgün sıralama — az yaddaş (16 byte)
// Qayda: böyükdən kiçiyə, sonra bool-lar
type YaxsiStruct struct {
    b float64 // 8 byte
    d int32   // 4 byte
    a bool    // 1 byte
    c bool    // 1 byte + 2 byte padding (hizalanma üçün)
    // Cəm: 8+4+1+1+2 = 16 byte
}

// Real nümunə — HTTP request struct
type HttpRequestPis struct {
    Method  string    // 16 byte (pointer+len)
    IsHTTPS bool      // 1 byte + 7 padding
    URL     string    // 16 byte
    Body    []byte    // 24 byte
    Port    int16     // 2 byte + 6 padding
}

type HttpRequestYaxsi struct {
    Method  string // 16 byte
    URL     string // 16 byte
    Body    []byte // 24 byte
    Port    int16  // 2 byte
    IsHTTPS bool   // 1 byte + 5 padding
}

func main() {
    fmt.Printf("PisStruct:         %d byte\n", unsafe.Sizeof(PisStruct{}))   // 24
    fmt.Printf("YaxsiStruct:       %d byte\n", unsafe.Sizeof(YaxsiStruct{})) // 16
    fmt.Printf("HttpRequestPis:    %d byte\n", unsafe.Sizeof(HttpRequestPis{}))
    fmt.Printf("HttpRequestYaxsi:  %d byte\n", unsafe.Sizeof(HttpRequestYaxsi{}))

    // Tip ölçüləri
    fmt.Println("\n--- Tip Ölçüləri ---")
    fmt.Printf("bool:    %d byte\n", unsafe.Sizeof(bool(false)))
    fmt.Printf("int8:    %d byte\n", unsafe.Sizeof(int8(0)))
    fmt.Printf("int32:   %d byte\n", unsafe.Sizeof(int32(0)))
    fmt.Printf("int64:   %d byte\n", unsafe.Sizeof(int64(0)))
    fmt.Printf("float64: %d byte\n", unsafe.Sizeof(float64(0)))
    fmt.Printf("string:  %d byte\n", unsafe.Sizeof(""))      // 16 (pointer + len)
    fmt.Printf("slice:   %d byte\n", unsafe.Sizeof([]int{})) // 24 (pointer + len + cap)
    fmt.Printf("pointer: %d byte\n", unsafe.Sizeof((*int)(nil)))
}
```

### Nümunə 3: Slice yaddaş tələləri

```go
package main

import "fmt"

func main() {
    // TELE 1: Böyük slice-dan kiçik dilim
    boyukSlice := make([]byte, 1_000_000) // 1MB ayrıldı
    kicikDilim := boyukSlice[:10]          // yalnız 10 byte lazımdır

    // AMA: kicikDilim hala 1MB-lıq array-ə istinad edir!
    // GC 1MB-ni silə bilməz — kicikDilim var olduğu müddətcə
    boyukSlice = nil // boyukSlice-ı sıfırlamaq KÖMƏK ETMİR
    _ = kicikDilim  // çünki hala istinad var

    // HƏLL: kopyalayın
    kopya := make([]byte, 10)
    copy(kopya, boyukSlice[:10]) // yalnız 10 byte kopyalandı
    boyukSlice = nil             // indi 1MB azad edilə bilər
    _ = kopya

    // TELE 2: Slice-dan çox element çıxartdıqda capacity qalır
    s := make([]int, 0, 1000) // 1000 tutum ayrıldı
    for i := 0; i < 1000; i++ {
        s = append(s, i)
    }
    s = s[:10] // uzunluq 10, amma hala 1000 capacity tutulur!

    // HƏLL: Yeni slice yaradın
    yeniS := make([]int, 10)
    copy(yeniS, s[:10])
    s = yeniS // köhnə backing array GC-yə verildi

    // TELE 3: Closure-da slice-ı tutmaq
    funcs := make([]func(), 10)
    data := make([]int, 10)
    for i := range data {
        i := i // hər closure üçün yeni dəyişkən — loop variable capture!
        funcs[i] = func() { fmt.Println(data[i]) }
    }
    _ = funcs
}
```

### Nümunə 4: GC ayarı — GOGC və GOMEMLIMIT

```go
package main

import (
    "fmt"
    "runtime"
    "runtime/debug"
)

func main() {
    // Default: GOGC=100
    // Heap son GC-dən 2x böyüyəndə yeni GC işlər

    // Proqrammatik ayar
    // debug.SetGCPercent(100)  // default
    // debug.SetGCPercent(50)   // tez-tez GC (az yaddaş, çox CPU)
    // debug.SetGCPercent(200)  // nadir GC (çox yaddaş, az CPU)
    // debug.SetGCPercent(-1)   // GC-ni söndür (xüsusi hallarda)

    // Go 1.19+: GOMEMLIMIT — yaddaş limiti
    // debug.SetMemoryLimit(1 << 30) // 1GB limit
    // Kubernetes container limiti ilə əlaqələndirmək üçün faydalıdır

    // Manual GC çağırmaq (nadir hallarda)
    runtime.GC()

    var m runtime.MemStats
    runtime.ReadMemStats(&m)

    fmt.Printf("Alloc:      %d KB\n", m.Alloc/1024)
    fmt.Printf("TotalAlloc: %d KB\n", m.TotalAlloc/1024)
    fmt.Printf("Sys:        %d KB\n", m.Sys/1024)
    fmt.Printf("NumGC:      %d\n", m.NumGC)
    fmt.Printf("GCCPUFraction: %.4f\n", m.GCCPUFraction) // GC-nin CPU payı

    // Pause statistikaları
    fmt.Printf("LastGCPause: %d ns\n", m.PauseNs[(m.NumGC+255)%256])
    fmt.Printf("PauseTotalNs: %d ns\n", m.PauseTotalNs)

    // Build stats
    bs := debug.ReadBuildInfo
    _ = bs

    // Production tövsiyəsi:
    // GOGC=100 GOMEMLIMIT=<container_limit * 0.9> ./myapp
    // Bu kombinasiya GC-ni container limitinə uyğunlaşdırır
    // container limitinin 90%-nə çatanda agresiv GC işlər
}
```

### Nümunə 5: strings.Builder ilə yaddaş optimallaşdırması

```go
package main

import (
    "strings"
    "testing"
)

// YANLIŞ: + ilə birləşdirmə
// Hər dəfə yeni string yaranır, köhnəsi GC-yə verilir
func birlesdirmePis(n int) string {
    s := ""
    for i := 0; i < n; i++ {
        s += "x" // O(n²) yaddaş ayrılması!
    }
    return s
}

// DÜZGÜN: strings.Builder
func birlesdirmeYaxsi(n int) string {
    var sb strings.Builder
    sb.Grow(n) // əvvəlcədən yer ayır — reallocation yoxdur
    for i := 0; i < n; i++ {
        sb.WriteByte('x')
    }
    return sb.String()
}

func BenchmarkStringConcat(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        _ = birlesdirmePis(100)
    }
}

func BenchmarkStringBuilder(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        _ = birlesdirmeYaxsi(100)
    }
}

// Nəticə nümunəsi:
// BenchmarkStringConcat-8    100000   15234 ns/op   5424 B/op   99 allocs/op
// BenchmarkStringBuilder-8  1000000    1043 ns/op    128 B/op    2 allocs/op
```

### Nümunə 6: sync.Pool ilə yaddaş reuse

```go
package main

import (
    "bytes"
    "encoding/json"
    "net/http"
    "sync"
)

// Pool olmadan — hər request yeni buffer yaradır
// Yüksək RPS-də GC pressure yaradır

// Pool ilə — buffer-lər yenidən istifadə edilir
var bufPool = sync.Pool{
    New: func() interface{} {
        // Pool boşaldıqda yeni obyekt yaradır
        return bytes.NewBuffer(make([]byte, 0, 4096))
    },
}

type Response struct {
    Data interface{} `json:"data"`
}

func handleRequest(w http.ResponseWriter, r *http.Request) {
    // Pool-dan buffer al
    buf := bufPool.Get().(*bytes.Buffer)
    buf.Reset() // köhnə məlumatı sil
    defer bufPool.Put(buf) // işin sonunda geri qoy

    // JSON encode et
    resp := Response{Data: map[string]string{"status": "ok"}}
    if err := json.NewEncoder(buf).Encode(resp); err != nil {
        http.Error(w, err.Error(), 500)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    w.Write(buf.Bytes())
}

// Önəmli qeyd: sync.Pool-dan çıxan obyekt
// GC cycle-ları arasında silinə bilər
// Pool yalnız müvəqqəti, tez yaradılan/silinən obyektlər üçündür
// Uzun müddətli state saxlamaq üçün istifadə etməyin

// Goroutine leak — ən çox rast gəlinən memory leak
func goroutineLeak() {
    ch := make(chan int)

    go func() {
        // ch-dan heç vaxt oxunmayacaq
        // bu goroutine əbədi bloklanır — memory leak!
        val := <-ch
        _ = val
    }()

    // ch heç vaxt yazılmır...
    // HƏLL: context ilə dayandırma
}

func goroutineYaxsi(ctx interface{ Done() <-chan struct{} }) {
    ch := make(chan int)

    go func() {
        select {
        case val := <-ch:
            _ = val
        case <-ctx.Done(): // context ləğv olunanda çıx
            return
        }
    }()
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Struct optimizasiyası:**
1. Layihənizdəki ən çox istifadə olunan struct-ı tapın
2. `unsafe.Sizeof()` ilə mövcud ölçüsünü ölçün
3. Field-ları böyükdən kiçiyə sıralayın
4. Fərqi hesablayın — 1 milyonluq slice-da neçə MB fərq edir?

**Tapşırıq 2 — Escape analysis araşdırması:**
1. Bir funksiya yazın ki, local struct-ı pointer kimi qaytarsın
2. `go build -gcflags="-m" ./...` ilə escape-i görün
3. Dəyər kimi qaytarın — fərqi müqayisə edin
4. Benchmark ilə performans fərqini ölçün

**Tapşırıq 3 — Memory leak tapma:**
```go
// Bu kod-da leak var — tapın
func main() {
    var data [][]byte
    for i := 0; i < 100; i++ {
        chunk := make([]byte, 1_000_000)
        data = append(data, chunk[:10]) // problem burada
    }
    _ = data
}
```

**Tapşırıq 4 — GOGC tuning:**
1. Yaddaş intensiv bir proqram yazın
2. `GOGC=100` (default) ilə çalışdırın, GC sayını qeyd edin
3. `GOGC=50` ilə müqayisə edin
4. `GOGC=200` ilə müqayisə edin
5. CPU vs Memory trade-off-ı müşahidə edin

**Tapşırıq 5 — sync.Pool effektivliyi:**
1. HTTP handler yazın: pool olmadan vs pool ilə
2. `go test -bench=. -benchmem ./...` ilə ölçün
3. Allocation sayındakı fərqi izah edin

## Ətraflı Qeydlər

**GC tri-color mark-and-sweep:**
- **White**: hələ ziyarət edilməyib (potential garbage)
- **Gray**: ziyarət edilib, amma referans-ları yoxlanmayıb
- **Black**: ziyarət edilib, referans-ları da yoxlanıb (canlı)
- GC white qalan hər şeyi silir

**GOGC vs GOMEMLIMIT fərqi:**
- `GOGC`: GC nə tez-tez işləsin? (ratio əsaslı)
- `GOMEMLIMIT`: Maksimal yaddaş nə qədər olsun? (absolute limit)
- İkisini birlikdə istifadə etmək optimal: `GOGC=100 GOMEMLIMIT=800MiB`

**Goroutine stack büyüməsi:**
- Goroutine başlanğıcda 2KB stack alır (Go 1.14+)
- Lazım olduqca 2x böyüyür (contiguous stack)
- Maksimum default: 1GB (runtime.MAXSTACKSIZE)
- Çox goroutine → çox yaddaş → OOM riski

## PHP ilə Müqayisə

Memory management PHP/Laravel-dən gələn developer üçün ən böyük mental shift-dən biridir. PHP hər request üçün yeni proses açır — request bitdikdə hər şey avtomatik silinir, memory leak praktiki cəhətdən yoxdur (PHP-FPM restart etsə də). Go-da isə proses uzun müddət işləyir — GC var, amma memory leak mümkündür (goroutine leak, global slice, timer leak). PHP-də `unset()` dəyişkəni növbəti GC cycle-da sililəcəyi zaman serbəst buraxır; Go-da GC tri-color mark-and-sweep ilə işləyir, `GOGC` ilə tənzimlənir. Struct alignment PHP-nin dünya görünüşündə yoxdur — Go-da ciddi performans fərqi yarada bilir.

## Əlaqəli Mövzular

- [68-profiling-and-benchmarking.md](68-profiling-and-benchmarking.md) — pprof, benchmark metodologiyası
- [56-advanced-concurrency.md](56-advanced-concurrency.md) — Goroutine pool, sync primitives
- [27-goroutines-and-channels.md](27-goroutines-and-channels.md) — Goroutine əsasları
- [11-pointers.md](11-pointers.md) — Pointer əsasları
