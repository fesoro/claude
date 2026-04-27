# Profiling and Benchmarking (Architect)

## İcmal

Profiling — proqramın harada yavaşladığını, nə qədər yaddaş istifadə etdiyini aşkar etmək prosesidir. Benchmarking isə kodun performansını ölçmək və müqayisə etmək üsuludur. Go dili bu sahədə standart kitabxanada güclü alətlər təqdim edir: `runtime/pprof`, `net/http/pprof` və `testing` paketi.

Architect səviyyəsində performans mühəndisliyi yalnız "bu daha sürətli işləyir" demək deyil — ölçmə metodologiyası, bottleneck-lərin düzgün identifikasiyası, trade-off qərarları və production sistemlərinin proaktiv monitorinqi deməkdir.

## Niyə Vacibdir

- Production-da gizli qalan bottleneck-lər yalnız real yük altında üzə çıxır
- Düzgün profilsiz edilən optimizasiya adətən yanlış yerə edilir ("premature optimization" tuzağı)
- Benchmark olmadan refaktor etmək reqressiya riski yaradır
- Go-nun yerleşik `pprof` aləti xarici tool olmadan CPU, memory, goroutine, mutex profilləri çıxarır
- Flame graph vasitəsilə call stack vizualizasiyası minutes əvəzinə seconds içində bottleneck tapır

## Əsas Anlayışlar

**Profiling növləri:**
- **CPU profiling** — hansı funksiyalar ən çox CPU vaxtı alır
- **Memory (heap) profiling** — hansı funksiyalar ən çox heap allocation edir
- **Goroutine profiling** — neçə goroutine var, harda bloklanıb
- **Mutex/block profiling** — lock contention haradandır
- **Trace** — ətraflı execution timeline (runtime/trace)

**Benchmarking anlayışları:**
- `b.N` — testing framework-ün optimal dəfə sayını avtomatik müəyyən etməsi
- `b.ReportAllocs()` — hər benchmark iteration-da allocation statistikası
- `b.RunParallel()` — paralel benchmark (concurrency bottleneck-ləri üçün)
- Sub-benchmark — eyni funksiyaya müxtəlif parametrlərlə test

**Flame Graph:**
- Horizontal eni: funksiyada keçirilən vaxtın nisbəti
- Vertikal: call stack dərinliyi
- Ən geniş "plato"lar — əsl bottleneck-lər

## Praktik Baxış

**Nə vaxt profiling etməli:**
- Proqram gözlənilmədən yavaşlayanda
- Memory istifadəsi saatdan-saata artanda (memory leak şüphəsi)
- Load test zamanı latency spike-lar müşahidə edəndə
- Refaktordan əvvəl baseline almaq üçün

**Nə vaxt etməmək:**
- Hər dəfə kod dəyişdikdə — benchmark suite CI-da saxla
- Gözümüzlə "bu yavaş görünür" deyəndə — əvvəlcə ölç, sonra optimize et
- Micro-optimization üçün — macro bottleneck-ləri əvvəl həll et

**Trade-off-lar:**
- HTTP pprof endpoint production-da aktiv olsa — minimal overhead, amma security riski (internal port-da açın)
- `GOGC=off` ilə GC dayandırmaq benchmark nəticəsini dəyişdirir — real scenario-nu ölçün
- `b.ResetTimer()` — setUp kodunu benchmark-dan xaric etmək üçün

**Common mistakes:**
- Benchmark-ı yalnız bir dəfə çalışdırmaq — `count=5` istifadə edin
- Compiler optimization-ın nəticəni "optimize etməsini" qoymaq — nəticəni `_ =` ilə istifadə edin
- Wall clock time ilə ölçmək əvəzinə `time.Since()` — benchmark framework daha dəqiqdir
- Production binary-da `pprof` import etməyi unutmaq

## Nümunələr

### Nümunə 1: Runtime məlumatları və proqrammatik CPU profiling

```go
package main

import (
    "fmt"
    "os"
    "runtime"
    "runtime/pprof"
    "time"
)

func main() {
    // Runtime məlumatları
    var m runtime.MemStats
    runtime.ReadMemStats(&m)

    fmt.Printf("Ayrılmış yaddaş:  %d KB\n", m.Alloc/1024)
    fmt.Printf("Toplam ayrılmış:  %d KB\n", m.TotalAlloc/1024)
    fmt.Printf("Sistem yaddaşı:   %d KB\n", m.Sys/1024)
    fmt.Printf("GC sayı:          %d\n", m.NumGC)
    fmt.Printf("Goroutine sayı:   %d\n", runtime.NumGoroutine())
    fmt.Printf("CPU sayısı:       %d\n", runtime.NumCPU())
    fmt.Printf("Go versiyası:     %s\n", runtime.Version())

    // CPU profiling
    cpuFile, err := os.Create("cpu.prof")
    if err != nil {
        panic(err)
    }
    defer cpuFile.Close()

    pprof.StartCPUProfile(cpuFile)
    agirIs() // profil alınacaq kod
    pprof.StopCPUProfile()

    fmt.Println("cpu.prof yazıldı")
    fmt.Println("Analiz üçün: go tool pprof cpu.prof")

    // Memory profiling
    agirYaddashIs()

    memFile, _ := os.Create("mem.prof")
    defer memFile.Close()

    runtime.GC() // GC işlət ki dəqiq nəticə olsun
    pprof.WriteHeapProfile(memFile)

    fmt.Println("mem.prof yazıldı")

    // Manuel vaxt ölçmə
    start := time.Now()
    agirIs()
    fmt.Printf("İcra müddəti: %v\n", time.Since(start))
}

func agirIs() {
    toplam := 0
    for i := 0; i < 10_000_000; i++ {
        toplam += i
    }
    _ = toplam
}

func agirYaddashIs() {
    data := make([][]byte, 100)
    for i := range data {
        data[i] = make([]byte, 10000)
    }
    _ = data
}
```

### Nümunə 2: HTTP pprof — canlı production profiling

```go
package main

import (
    "log"
    "net/http"
    _ "net/http/pprof" // import ilə endpoint-lər avtomatik qeydiyyatdan keçir
)

func main() {
    // Production serverdə ayrı debug port
    go func() {
        log.Println("pprof :6060 portunda")
        // HEÇVAXT public internet-ə açmayın!
        log.Fatal(http.ListenAndServe("127.0.0.1:6060", nil))
    }()

    // Əsas server
    mux := http.NewServeMux()
    mux.HandleFunc("/api/users", usersHandler)
    log.Fatal(http.ListenAndServe(":8080", mux))
}

// Əlçatan endpoint-lər:
// http://localhost:6060/debug/pprof/          — profil siyahısı
// http://localhost:6060/debug/pprof/heap      — memory heap
// http://localhost:6060/debug/pprof/goroutine — goroutine dump
// http://localhost:6060/debug/pprof/profile   — 30s CPU profili

// Komandalar:
// go tool pprof http://localhost:6060/debug/pprof/heap
// go tool pprof http://localhost:6060/debug/pprof/profile?seconds=30
// go tool pprof -http=:8081 cpu.prof  # brauzerdə flame graph

func usersHandler(w http.ResponseWriter, r *http.Request) {
    w.Write([]byte(`{"users": []}`))
}
```

### Nümunə 3: Benchmark testləri — _test.go faylında

```go
// performance_test.go
package main

import (
    "fmt"
    "strings"
    "testing"
)

// Sadə benchmark
func BenchmarkTopla(b *testing.B) {
    for i := 0; i < b.N; i++ {
        _ = 2 + 3
    }
}

// String birləşdirmə müqayisəsi
func BenchmarkStringConcat(b *testing.B) {
    for i := 0; i < b.N; i++ {
        s := ""
        for j := 0; j < 100; j++ {
            s += "x" // YAVAŞ — hər dəfə yeni string yaranır
        }
        _ = s
    }
}

func BenchmarkStringBuilder(b *testing.B) {
    for i := 0; i < b.N; i++ {
        var sb strings.Builder
        sb.Grow(100) // əvvəlcədən yer ayır
        for j := 0; j < 100; j++ {
            sb.WriteString("x") // SÜRƏTLI — buffer istifadə edir
        }
        _ = sb.String()
    }
}

// Yaddaş ayırmasını ölçmək
func BenchmarkSliceAppend(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        s := make([]int, 0)
        for j := 0; j < 1000; j++ {
            s = append(s, j) // çox reallocation
        }
        _ = s
    }
}

func BenchmarkSlicePrealloc(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        s := make([]int, 0, 1000) // əvvəlcədən tutum ver
        for j := 0; j < 1000; j++ {
            s = append(s, j) // reallocation yoxdur
        }
        _ = s
    }
}

// Sub-benchmark — müxtəlif ölçülər
func BenchmarkMap(b *testing.B) {
    sizes := []int{10, 100, 1000, 10000}
    for _, size := range sizes {
        b.Run(fmt.Sprintf("size=%d", size), func(b *testing.B) {
            b.ReportAllocs()
            for i := 0; i < b.N; i++ {
                m := make(map[int]int, size)
                for j := 0; j < size; j++ {
                    m[j] = j
                }
                _ = m
            }
        })
    }
}

// Paralel benchmark — concurrency testi
func BenchmarkParallelHandler(b *testing.B) {
    b.RunParallel(func(pb *testing.PB) {
        for pb.Next() {
            // HTTP handler kimi işlə
            processRequest()
        }
    })
}

func processRequest() {
    // Simulyasiya
    result := 0
    for i := 0; i < 1000; i++ {
        result += i
    }
    _ = result
}
```

### Nümunə 4: Benchmark-ı çalışdırma əmrləri

```bash
# Sadə benchmark
go test -bench=. ./...

# Yalnız müəyyən benchmark
go test -bench=BenchmarkStringBuilder ./...

# Benchmark + yaddaş statistikası
go test -bench=. -benchmem ./...

# Uzun müddətli benchmark (daha dəqiq nəticə)
go test -bench=. -benchtime=5s ./...

# Çox dəfə təkrar (statistik dəqiqlik)
go test -bench=. -count=5 ./...

# Benchmark + CPU profil çıxar
go test -bench=. -cpuprofile=cpu.prof ./...

# Benchmark + memory profil çıxar
go test -bench=. -memprofile=mem.prof ./...

# Profili analiz et — interaktiv rejim
go tool pprof cpu.prof
# Daxilə girəndən sonra:
# top          — ən çox vaxt alan funksiyalar
# top10        — ilk 10
# list funcAd  — funksiya detalları
# web          — brauzerdə qrafik (graphviz lazım)

# Brauzerdə flame graph (ən rahat üsul)
go tool pprof -http=:8081 cpu.prof

# İki benchmark nəticəsini müqayisə et (benchstat)
go install golang.org/x/perf/cmd/benchstat@latest
go test -bench=. -count=5 ./... > before.txt
# ... dəyişiklik et ...
go test -bench=. -count=5 ./... > after.txt
benchstat before.txt after.txt
```

### Nümunə 5: sync.Pool ilə allocation azaltmaq

```go
package main

import (
    "bytes"
    "sync"
    "testing"
)

// Pool olmadan — hər request yeni buffer yaradır
func withoutPool(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        buf := bytes.NewBuffer(make([]byte, 0, 512))
        buf.WriteString("response data")
        _ = buf.String()
        // buf GC-yə verilir
    }
}

// Pool ilə — buffer-lər yenidən istifadə olunur
var bufPool = sync.Pool{
    New: func() interface{} {
        return bytes.NewBuffer(make([]byte, 0, 512))
    },
}

func withPool(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        buf := bufPool.Get().(*bytes.Buffer)
        buf.Reset() // köhnə məlumatı sil
        buf.WriteString("response data")
        _ = buf.String()
        bufPool.Put(buf) // geri qoy
    }
}

// HTTP handler-də sync.Pool
type Handler struct {
    pool sync.Pool
}

func NewHandler() *Handler {
    return &Handler{
        pool: sync.Pool{
            New: func() interface{} {
                return make([]byte, 0, 4096)
            },
        },
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Baseline müəyyən et:**
1. Mövcud proqramınıza `net/http/pprof` əlavə edin (ayrı portda)
2. `wrk` və ya `hey` ilə 1 dəqiqə yük tətbiq edin: `hey -n 10000 -c 100 http://localhost:8080/api/users`
3. CPU profilini çıxarın: `go tool pprof http://localhost:6060/debug/pprof/profile?seconds=30`
4. `top10` əmri ilə ən çox CPU alan funksiyaları tapın
5. Flame graph yaradın: `go tool pprof -http=:8081 cpu.prof`

**Tapşırıq 2 — String benchmark müqayisəsi:**
1. `BenchmarkStringConcat` və `BenchmarkStringBuilder` yazın
2. `go test -bench=. -benchmem -count=5` ilə çalışdırın
3. `benchstat` ilə nəticəni müqayisə edin
4. Fərqi əsaslandırın (allocation sayı, ns/op)

**Tapşırıq 3 — Memory leak aşkarla:**
1. Hər dəfə `make([]byte, 1_000_000)` edən handler yazın
2. 100 request göndərin
3. Heap profilini 3 müxtəlif anda çıxarın
4. `go tool pprof -diff_base=heap1.prof heap2.prof` ilə fərqi analiz edin

**Tapşırıq 4 — sync.Pool effektivliyi:**
1. Buffer allocation-ı olan handler üçün benchmark yazın
2. `sync.Pool` əlavə edin
3. `benchmem` nəticəsini müqayisə edin — allocation sayı azalmalıdır

**Tapşırıq 5 — Production-ready profiling setup:**
```go
// Production-da şərtli pprof aktiv etmək
package main

import (
    "net/http"
    _ "net/http/pprof"
    "os"
)

func startDebugServer() {
    if os.Getenv("ENABLE_PPROF") != "true" {
        return
    }
    addr := os.Getenv("PPROF_ADDR")
    if addr == "" {
        addr = "127.0.0.1:6060"
    }
    go http.ListenAndServe(addr, nil)
}
```

## Ətraflı Qeydlər

**Benchmarking metodologiyası:**
- Həmişə `count=5` və ya daha çox — variance-ı azaltmaq üçün
- Isti sistemdə benchmark edin — CPU frequency scaling-i nəzərə alın
- `GOMAXPROCS=1` ilə single-threaded, normal şəraitdə multi-threaded test edin
- Benchmark-ı CI-da çalışdırmayın (hardware noise) — əvvəlki nəticəni `git stash`-da saxlayın

**Escape analysis anlaşması:**
```bash
# Hansı dəyişkənlər heap-ə qaçır?
go build -gcflags="-m" ./...
go build -gcflags="-m -m" ./...  # daha ətraflı
```

**Performans tövsiyələri (ölçüb sübuta endirin):**
1. `strings.Builder` istifadə edin (`+` ilə birləşdirmə deyil)
2. Slice-lara əvvəlcədən tutum verin: `make([]T, 0, n)`
3. Map-lərə əvvəlcədən tutum verin: `make(map[K]V, n)`
4. `sync.Pool` ilə müvəqqəti obyektləri yenidən istifadə edin
5. Interface-ləri hot path-da azaldın (boxing/unboxing xərci var)
6. Goroutine-ləri lazım olmadıqda yaratmayın (stack overhead)
7. `[]byte` əvəzinə `string` konversiyasını minimuma endirin

## PHP ilə Müqayisə

PHP-də profiling üçün Xdebug (`xdebug.profiler_enable=1`) və Blackfire.io kimi xarici alətlər tələb olunur — Go-da isə `net/http/pprof` standart kitabxanadır, xarici alət lazım deyil. PHP hər request-i yeni proses kimi işlədiyindən memory leak PHP-FPM restart ilə gizlənir; Go-da uzun müddətli proses olduğuna görə real memory leak-lər `pprof` ilə dəqiq aşkarlanır. Benchmarking PHP-də `microtime()` ilə əllə ölçülür; Go-da `testing.B` framework-i statistikasını özü idarə edir — `b.N` dəqiq ölçmə üçün optimal iteration sayını müəyyən edir.

## Əlaqəli Mövzular

- [69-memory-management.md](69-memory-management.md) — GC internals, heap/stack, escape analysis
- [71-monitoring-and-observability.md](71-monitoring-and-observability.md) — Prometheus metrics, OpenTelemetry
- [56-advanced-concurrency.md](56-advanced-concurrency.md) — Goroutine pool, sync primitives
- [24-testing.md](24-testing.md) — Go-da test yazma əsasları
