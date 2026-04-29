# Goroutine-lər və Kanallar (Middle)

## İcmal

Goroutine-lər Go-nun ən güclü xüsusiyyətidir. Go-nun concurrency modeli **CSP (Communicating Sequential Processes)** prinsipinə əsaslanır: `"Do not communicate by sharing memory; instead, share memory by communicating."` Goroutine-lər son dərəcə yüngüldür — bir goroutine cəmi ~2KB stack yaddaşı istifadə edir.

## Niyə Vacibdir

Real layihələrdə eyni anda minlərlə müştəriyə xidmət etmək, background job-lar işlətmək, paralel API çağırışları etmək lazım olur. Goroutine-lər bu problemi OS thread-lərindən çox daha ucuz şəkildə həll edir. Go runtime özü N goroutine-i M OS thread-ə (M:N scheduling) distribute edir.

## Əsas Anlayışlar

- **goroutine** — Go runtime tərəfindən idarə olunan yüngül execution unit; `go` açar sözü ilə başlanır
- **channel** — goroutine-lər arası type-safe məlumat ötürmə kanalı; `make(chan T)` ilə yaradılır
- **buffered channel** — müəyyən sayda element bufferlaşdıra bilən kanal: `make(chan T, n)`
- **WaitGroup** — bir qrup goroutine-in bitməsini gözləmək üçün; `sync.WaitGroup`
- **Mutex** — paylaşılan yaddaşa thread-safe giriş üçün; `sync.Mutex`
- **select** — bir neçə kanalı eyni anda dinləmək üçün `switch` bənzəri konstruksiya
- **deadlock** — goroutine-lərin bir-birini əbədi gözləməsi vəziyyəti
- **race condition** — iki goroutine-in eyni yaddaş sahəsinə eyni anda yazması

## Praktik Baxış

**Goroutine vs Thread:**

| Xüsusiyyət | OS Thread | Goroutine |
|-----------|-----------|-----------|
| Stack ölçüsü | ~1-8 MB | ~2 KB (dinamik böyüyür) |
| Yaratma xərci | Yüksək | Çox aşağı |
| Context switching | Yavaş (kernel) | Sürətli (user space) |
| Eyni anda sayı | Yüzlər | Yüz minlər |
| Scheduling | OS | Go runtime |

**Trade-off-lar:**
- Channel-lar kodu daha anlaşıqlı edir, amma mutex-dən yavaşdır
- Buffered channel-lar backpressure yaradır — bunu bilərəkdən istifadə edin
- `go func()` ilə başladılan goroutine-ni gözləmək lazımdır — əks halda main bitdikdə hamısı öldürülür

**Common mistakes:**
- WaitGroup olmadan goroutine-ləri başlatmaq (data race)
- Channel-ı bağlamadan `range` istifadəsi — deadlock
- Goroutine leak — ləğv etmə mexanizmi olmadan goroutine başlatmaq
- `go race detector`-u bilməmək: `go run -race main.go`

## Nümunələr

### Nümunə 1: Goroutine yaratmaq

```go
package main

import (
    "fmt"
    "time"
)

func salam(ad string) {
    for i := 0; i < 3; i++ {
        fmt.Printf("Salam %s (i=%d)\n", ad, i)
        time.Sleep(100 * time.Millisecond)
    }
}

func main() {
    go salam("Əli")   // yeni goroutine-də işləyir
    go salam("Vəli")  // başqa goroutine-də işləyir
    salam("Orkhan")   // main goroutine-də işləyir

    // QEYD: main bitərsə bütün goroutine-lər öldürülür!
    // Goroutine-ləri gözləmək üçün WaitGroup istifadə edin
}
```

### Nümunə 2: WaitGroup ilə goroutine-ləri gözləmək

```go
package main

import (
    "fmt"
    "sync"
    "time"
)

func main() {
    var wg sync.WaitGroup

    for i := 1; i <= 5; i++ {
        wg.Add(1)        // goroutine sayını artır
        go func(num int) {
            defer wg.Done() // bitəndə sayını azalt
            fmt.Printf("Worker %d işləyir\n", num)
            time.Sleep(100 * time.Millisecond)
            fmt.Printf("Worker %d bitdi\n", num)
        }(i) // i-ni parametr kimi ötür — closure bug-ından qaç
    }

    wg.Wait() // bütün goroutine-lər bitənə qədər gözlə
    fmt.Println("Bütün işçilər bitdi")

    // DIQQƏT: i-ni closure içində birbaşa istifadə etməyin:
    // go func() { fmt.Println(i) }()
    // — bu loop bitəndə i-nin son dəyərini götürür (race condition)
}
```

### Nümunə 3: Channel əsasları

```go
package main

import "fmt"

func kvadratHesabla(n int, ch chan int) {
    ch <- n * n // kanala yaz
}

func main() {
    // a) Buffersiz kanal (sinxron — göndərici qəbul edici gözləyir)
    ch := make(chan string)

    go func() {
        ch <- "Salam kanaldan!" // blok olur qəbul edilənə qədər
    }()

    mesaj := <-ch // blok olur göndərilənə qədər
    fmt.Println(mesaj)

    // b) Bufferli kanal (asinxron — buffer dolana qədər blok etmir)
    bufCh := make(chan int, 3) // 3 elementlik buffer
    bufCh <- 10
    bufCh <- 20
    bufCh <- 30
    // bufCh <- 40  // BLOK! buffer dolu

    fmt.Println(<-bufCh) // 10
    fmt.Println(<-bufCh) // 20
    fmt.Println(<-bufCh) // 30

    // c) Yönlü kanallar (directional)
    kvadratCh := make(chan int, 3)
    go kvadratHesabla(4, kvadratCh) // chan int — hər ikisi
    go kvadratHesabla(5, kvadratCh)
    go kvadratHesabla(6, kvadratCh)

    fmt.Println("4² =", <-kvadratCh)
    fmt.Println("5² =", <-kvadratCh)
    fmt.Println("6² =", <-kvadratCh)
}
```

### Nümunə 4: Kanal bağlamaq və range

```go
package main

import "fmt"

func ədədlərGöndər(ch chan<- int, limit int) {
    defer close(ch) // kanal bağlanana qədər range işləmir
    for i := 1; i <= limit; i++ {
        ch <- i
    }
}

func main() {
    ch := make(chan int)

    go ədədlərGöndər(ch, 5)

    // Kanal bağlanana qədər oxu
    for n := range ch {
        fmt.Println("Oxundu:", n)
    }

    // v, ok := <-ch
    // ok == false — kanal bağlıdır və boşdur
    fmt.Println("Bütün ədədlər oxundu")
}
```

### Nümunə 5: select — bir neçə kanalı dinləmək

```go
package main

import (
    "fmt"
    "time"
)

func main() {
    ch1 := make(chan string)
    ch2 := make(chan string)

    go func() {
        time.Sleep(100 * time.Millisecond)
        ch1 <- "birinci"
    }()

    go func() {
        time.Sleep(50 * time.Millisecond)
        ch2 <- "ikinci" // daha tez gəlir
    }()

    // Hansı kanal əvvəl hazır olsa onu oxu
    for i := 0; i < 2; i++ {
        select {
        case msg1 := <-ch1:
            fmt.Println("ch1:", msg1)
        case msg2 := <-ch2:
            fmt.Println("ch2:", msg2)
        }
    }

    // select ilə timeout
    slowCh := make(chan string)
    go func() {
        time.Sleep(2 * time.Second) // çox gec cavab verir
        slowCh <- "gec cavab"
    }()

    select {
    case msg := <-slowCh:
        fmt.Println("Cavab:", msg)
    case <-time.After(500 * time.Millisecond):
        fmt.Println("Timeout! Cavab gəlmədi")
    }

    // Non-blocking select (default ilə)
    dataCh := make(chan int, 1)
    select {
    case v := <-dataCh:
        fmt.Println("Alındı:", v)
    default:
        fmt.Println("Kanal boşdur, blok olmadı")
    }
}
```

### Nümunə 6: Worker Pool pattern

```go
package main

import (
    "fmt"
    "sync"
    "time"
)

type İş struct {
    ID    int
    Məlumat string
}

type Nəticə struct {
    İşID  int
    Cavab string
}

func worker(id int, işlər <-chan İş, nəticələr chan<- Nəticə, wg *sync.WaitGroup) {
    defer wg.Done()
    for iş := range işlər {
        fmt.Printf("Worker %d: iş %d işlənir\n", id, iş.ID)
        time.Sleep(100 * time.Millisecond) // iş simulyasiyası
        nəticələr <- Nəticə{
            İşID:  iş.ID,
            Cavab: fmt.Sprintf("İş %d tamamlandı", iş.ID),
        }
    }
}

func main() {
    const workerSayı = 3
    const işSayı = 10

    işlər    := make(chan İş, işSayı)
    nəticələr := make(chan Nəticə, işSayı)
    var wg sync.WaitGroup

    // Worker-ləri başlat
    for i := 1; i <= workerSayı; i++ {
        wg.Add(1)
        go worker(i, işlər, nəticələr, &wg)
    }

    // İşləri göndər
    for j := 1; j <= işSayı; j++ {
        işlər <- İş{ID: j, Məlumat: fmt.Sprintf("data-%d", j)}
    }
    close(işlər) // yeni iş yoxdur — worker-lər öz işlərini bitirəcək

    // Worker-lər bitdikdə nəticə kanalını bağla
    go func() {
        wg.Wait()
        close(nəticələr)
    }()

    // Bütün nəticələri yığ
    for nəticə := range nəticələr {
        fmt.Println("Nəticə:", nəticə.Cavab)
    }
}
```

### Nümunə 7: Mutex — paylaşılan yaddaşı qorumaq

```go
package main

import (
    "fmt"
    "sync"
)

type TəhlükəsizSayaç struct {
    mu    sync.Mutex
    dəyər int
}

func (s *TəhlükəsizSayaç) Artır() {
    s.mu.Lock()
    defer s.mu.Unlock()
    s.dəyər++
}

func (s *TəhlükəsizSayaç) Dəyər() int {
    s.mu.Lock()
    defer s.mu.Unlock()
    return s.dəyər
}

func main() {
    sayaç := &TəhlükəsizSayaç{}
    var wg sync.WaitGroup

    for i := 0; i < 1000; i++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            sayaç.Artır()
        }()
    }

    wg.Wait()
    fmt.Println("Sayaç:", sayaç.Dəyər()) // Hər zaman 1000

    // Mutex olmadan race condition baş verər:
    // go run -race main.go
    // — race detector xəbərdarlıq göstərər

    // sync.RWMutex — oxuma-yazma kilidləri
    // var rwmu sync.RWMutex
    // rwmu.RLock()  / rwmu.RUnlock()  — oxuma üçün (bir neçə goroutine eyni anda)
    // rwmu.Lock()   / rwmu.Unlock()   — yazma üçün (yalnız bir goroutine)
}
```

### Nümunə 8: Goroutine leak-inin qarşısını almaq

```go
package main

import (
    "context"
    "fmt"
    "time"
)

// Düzgün — context ilə dayandırılabilir goroutine
func arxaIslem(ctx context.Context) {
    for {
        select {
        case <-ctx.Done():
            fmt.Println("Goroutine dayandırıldı:", ctx.Err())
            return
        case <-time.After(500 * time.Millisecond):
            fmt.Println("Arxa planda işləyir...")
        }
    }
}

func main() {
    ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
    defer cancel()

    go arxaIslem(ctx)

    <-ctx.Done() // 2 saniyə gözlə
    time.Sleep(100 * time.Millisecond) // goroutine-nin dayandığından əmin ol
    fmt.Println("Program bitdi")

    // QAYDA: Hər goroutine-in dayandırılma mexanizmi olmalıdır
    // — context.Done() ilə
    // — close(done) channel ilə
    // — WaitGroup ilə
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1: Paralel API sorğuları**

5 fərqli endpoint-ə eyni anda sorğu göndər, hamısının nəticəsini topla, timeout 3 saniyədir. Hər endpoint üçün ayrı goroutine, nəticələri channel vasitəsilə topla.

```go
// İstifadə ssenarisı:
// URLs := []string{"https://api1.com/data", "https://api2.com/data", ...}
// Hər biri üçün goroutine başlat
// Nəticələri []Result kanalında topla
// context.WithTimeout(ctx, 3*time.Second) ilə timeout tətbiq et
```

**Tapşırıq 2: Rate limiter**

Saniyədə maksimum 10 sorğu emal edən worker pool yaz. `time.Ticker` istifadə et. Artıq gələn sorğular növbəyə qoyulsun (buffered channel), amma növbə 100-dən artıq olsa rədd et.

```go
// Strukturu:
// rateLimiter := time.NewTicker(100 * time.Millisecond) // 10/saniyə
// queue       := make(chan Request, 100)
// worker goroutine-i ticker-i gözləyir, sonra queue-dən oxuyur
```

**Tapşırıq 3: Fan-out / Fan-in**

1 giriş kanalından məlumat al, 5 worker-ə paylaşdır (fan-out), hamısının nəticəsini 1 çıxış kanalında topla (fan-in). Sıralama önəmli deyil.

```go
// fanOut(input <-chan int, workers int) []<-chan int
// fanIn(channels ...<-chan int) <-chan int
// merge(channels...) ilə bütün kanalları birləşdir
```

## Ətraflı Qeydlər

**Go Scheduler (GOMAXPROCS):**

```go
import "runtime"

// Default: bütün CPU core-larını istifadə et
runtime.GOMAXPROCS(runtime.NumCPU())

// Yoxlamaq:
fmt.Println("CPU sayı:", runtime.NumCPU())
fmt.Println("GOMAXPROCS:", runtime.GOMAXPROCS(0)) // 0 = dəyişmə, yalnız oxu
fmt.Println("Goroutine sayı:", runtime.NumGoroutine())
```

**sync.Once — bir dəfə icra:**

```go
var once sync.Once
var db *Database

func getDB() *Database {
    once.Do(func() {
        db = connectDB() // yalnız bir dəfə çağırılır
    })
    return db
}
```

**sync.Map — concurrent-safe map:**

```go
var m sync.Map
m.Store("key", "value")          // yazma
v, ok := m.Load("key")           // oxuma
m.LoadOrStore("key", "default")  // varsa al, yoxsa saxla
m.Delete("key")                  // silmə
m.Range(func(k, v any) bool {    // iterate
    fmt.Println(k, v)
    return true // false — dayan
})
```

## PHP ilə Müqayisə

```
PHP                             →  Go
pcntl_fork()                    →  go func() {}()
ReactPHP EventLoop              →  goroutine + channel
parallel\run()                  →  go func() {}()
Swoole coroutine                →  goroutine
$channel->recv()                →  <-ch
```

PHP-də həqiqi goroutine analoqunu əldə etmək üçün `pcntl`, `ReactPHP` və ya `Swoole` kimi əlavə kitabxanalar lazımdır. Go-da concurrency dil səviyyəsindədir.

## Əlaqəli Mövzular

- `28-context` — goroutine ləğvi üçün context
- `25-logging` — goroutine-lərdə thread-safe logging
- `../advanced/01-advanced-concurrency` — atomic, sync primitives dərinliyi
- `../advanced/03-channel-patterns` — pipeline, fan-out/in, done pattern
- `../backend/15-rate-limiting` — token bucket, rate limiter implementation
- `../backend/17-graceful-shutdown` — goroutine-ləri düzgün dayandırmaq
