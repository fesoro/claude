# Channel Patterns — Fan-out/Fan-in, Pipeline, Tee, Bridge (Lead)

## İcmal

Go channel-ləri sadə növbə deyil — onlar ilə mürəkkəb data flow arxitekturası qurula bilər. Bu mövzu real layihələrdə işlədilən channel pattern-lərini əhatə edir: pipeline (mərhələ zənciri), fan-out/fan-in (iş paylaşması), done channel (ləğv etmə), tee (kopyalama), bridge (kanal kanalı). Bu pattern-lər Go-nun "CSP" (Communicating Sequential Processes) paradiqmasının praktik tətbiqidir.

## Niyə Vacibdir

- **Pipeline** — ETL, image processing, log processing üçün modular, testable zəncir
- **Fan-out** — bir məlumat mənbəsini N worker arasında paralel emal üçün paylamaq
- **Fan-in** — N mənbədən gələn nəticəni bir kanala toplamaq
- **Done channel** — goroutine ömrünü idarə etmək, leak-dən qorunmaq
- **Tee** — eyni məlumatı iki yerə (log + process) göndərmək
- **Semaphore** — resurs limiti ilə paralelliyə nəzarət

## Əsas Anlayışlar

### Buffered vs Unbuffered channel

```
Unbuffered: göndərən alıcı olmadan bloklanır — sinxronizasiya primitivi
Buffered:   N elementə kimi göndərən bloklanmır — növbə kimi işləyir
```

Pipeline-larda adətən `unbuffered` kanal istifadə olunur — bu back-pressure yaradır: sürətli producer yavaş consumer-dən irəli keçə bilməz.

### Channel direction (kanal istiqaməti)

```go
chan int      // oxuma + yazma
<-chan int    // yalnız oxuma (receive-only)
chan<- int    // yalnız yazma (send-only)
```

Funksiyaların imzasında `<-chan` / `chan<-` istifadə etmək — niyyəti aydınlaşdırır, bug-ları kompilyasiya zamanı tutur.

### close() semantikası

```go
close(ch)         // yazıcı tərəfindən çağırılır
v, ok := <-ch     // ok=false isə kanal bağlıdır və boşdur
for v := range ch // kanal bağlanana kimi oxuyur
```

**Qayda:** Yalnız yazıcı `close()` çağırır. Alıcı heç vaxt close etməməlidir.

## Praktik Baxış

### Trade-off-lar

- Pipeline-da buffered kanal istifadə etmək mərhələlər arasında decoupling verir, amma debug-ı çətinləşdirir
- Fan-out — worker sayı CPU sayından çox olduqda context switch overhead artır
- Tee kanal — hər ikisi alana kimi bloklanır; biri yavaş olduqda back-pressure yaranır
- Done channel — `context.Context` ilə müqayisədə daha aşağı səviyyəlidir; production-da context tövsiyə olunur

### Anti-pattern-lər

```go
// YANLIŞ: goroutine-dən kanal bağlamaq
go func() {
    for v := range inputCh {
        outputCh <- process(v)
    }
    close(outputCh) // YaxşI deyil — birdən çox goroutine bağlamağa cəhd edə bilər
}()

// YANLIŞ: nil kanaldan oxumaq (əbədi bloklanır)
var ch chan int
<-ch // deadlock!

// YANLIŞ: bağlanmış kanala yazmaq
close(ch)
ch <- 1 // panic!
```

### Production hazırlıq

- Hər pipeline mərhələsi `done <-chan struct{}` qəbul etməlidir — ləğv üçün
- Goroutine-lər `select { case <-done: return }` ilə çıxmalıdır
- Fan-in üçün `sync.WaitGroup` istifadə edin — bütün mənbələr bitdikdə çıxış kanalını bağlamaq üçün
- `errgroup.WithContext` — pipeline xəta idarəetməsi üçün ən yaxşı seçimdir

## Nümunələr

### Nümunə 1: Production-grade Pipeline

```go
package main

import (
    "context"
    "fmt"
    "strings"
    "time"
)

// Pipeline mərhələsi — ümumi tip
type Stage[I, O any] func(ctx context.Context, in <-chan I) <-chan O

// logLines — məlumat mənbəyi
func logLines(ctx context.Context, lines []string) <-chan string {
    out := make(chan string)
    go func() {
        defer close(out)
        for _, line := range lines {
            select {
            case out <- line:
            case <-ctx.Done():
                return
            }
        }
    }()
    return out
}

// filter — şərtə uyğun olmayanları kənar edir
func filter(ctx context.Context, in <-chan string, pred func(string) bool) <-chan string {
    out := make(chan string)
    go func() {
        defer close(out)
        for line := range in {
            select {
            case <-ctx.Done():
                return
            default:
            }
            if pred(line) {
                out <- line
            }
        }
    }()
    return out
}

// transform — hər elementi çevirir
func transform(ctx context.Context, in <-chan string, fn func(string) string) <-chan string {
    out := make(chan string)
    go func() {
        defer close(out)
        for v := range in {
            select {
            case out <- fn(v):
            case <-ctx.Done():
                return
            }
        }
    }()
    return out
}

// batch — N elementdən ibarət qruplar yaradır
func batch(ctx context.Context, in <-chan string, size int) <-chan []string {
    out := make(chan []string)
    go func() {
        defer close(out)
        var buf []string
        for v := range in {
            buf = append(buf, v)
            if len(buf) == size {
                select {
                case out <- buf:
                    buf = nil
                case <-ctx.Done():
                    return
                }
            }
        }
        if len(buf) > 0 { // qalan elementlər
            out <- buf
        }
    }()
    return out
}

func main() {
    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()

    logs := []string{
        "INFO: server started",
        "DEBUG: request received",
        "ERROR: database timeout",
        "INFO: user logged in",
        "ERROR: payment failed",
        "DEBUG: cache hit",
    }

    // Pipeline: source → filter ERROR → uppercase → batch(2)
    source := logLines(ctx, logs)
    errors := filter(ctx, source, func(s string) bool {
        return strings.HasPrefix(s, "ERROR")
    })
    uppercased := transform(ctx, errors, strings.ToUpper)
    batched := batch(ctx, uppercased, 2)

    for group := range batched {
        fmt.Println("Batch:", group)
    }
}
```

### Nümunə 2: Fan-out / Fan-in — paralel iş emalı

```go
package main

import (
    "context"
    "fmt"
    "sync"
    "time"
)

type Job struct {
    ID    int
    Input string
}

type Result struct {
    JobID  int
    Output string
    Err    error
}

// worker — kanaldan iş alır, nəticə kanalına yazır
func worker(ctx context.Context, id int, jobs <-chan Job, results chan<- Result) {
    for {
        select {
        case job, ok := <-jobs:
            if !ok {
                return
            }
            // iş emalı simulyasiyası
            time.Sleep(50 * time.Millisecond)
            results <- Result{
                JobID:  job.ID,
                Output: fmt.Sprintf("worker-%d processed: %s", id, job.Input),
            }
        case <-ctx.Done():
            return
        }
    }
}

// fanOut — N worker başladır, eyni job kanalından oxuyurlar
func fanOut(ctx context.Context, jobs <-chan Job, workerCount int) <-chan Result {
    results := make(chan Result, workerCount)
    var wg sync.WaitGroup

    for i := 0; i < workerCount; i++ {
        wg.Add(1)
        go func(id int) {
            defer wg.Done()
            worker(ctx, id, jobs, results)
        }(i + 1)
    }

    go func() {
        wg.Wait()
        close(results)
    }()

    return results
}

func main() {
    ctx := context.Background()

    // Job mənbəyi
    jobs := make(chan Job, 20)
    go func() {
        defer close(jobs)
        for i := 1; i <= 20; i++ {
            jobs <- Job{ID: i, Input: fmt.Sprintf("task-%d", i)}
        }
    }()

    // 4 worker ilə fan-out
    start := time.Now()
    results := fanOut(ctx, jobs, 4)

    count := 0
    for r := range results {
        if r.Err != nil {
            fmt.Println("Xəta:", r.Err)
            continue
        }
        count++
        fmt.Println(r.Output)
    }
    fmt.Printf("\n%d iş tamamlandı, %v ərzində\n", count, time.Since(start).Round(time.Millisecond))
}
```

### Nümunə 3: Or-channel — birincisi qazanır

```go
package main

import (
    "fmt"
    "time"
)

// orChannel — istənilən bir kanal bağlandıqda nəticə kanalı bağlanır
func orChannel(channels ...<-chan struct{}) <-chan struct{} {
    switch len(channels) {
    case 0:
        return nil
    case 1:
        return channels[0]
    }

    out := make(chan struct{})
    go func() {
        defer close(out)
        switch len(channels) {
        case 2:
            select {
            case <-channels[0]:
            case <-channels[1]:
            }
        default:
            select {
            case <-channels[0]:
            case <-channels[1]:
            case <-channels[2]:
            case <-orChannel(append(channels[3:], out)...):
            }
        }
    }()
    return out
}

// after — müəyyən müddətdən sonra bağlanan kanal
func after(d time.Duration) <-chan struct{} {
    ch := make(chan struct{})
    go func() {
        defer close(ch)
        time.Sleep(d)
    }()
    return ch
}

func main() {
    start := time.Now()

    // Ən qısası qalib gəlir
    <-orChannel(
        after(3*time.Second),
        after(1*time.Second),
        after(500*time.Millisecond), // bu qazanır
        after(2*time.Second),
    )

    fmt.Printf("Bitdi: %v sonra\n", time.Since(start).Round(time.Millisecond))
    // Output: ~500ms sonra
}
```

### Nümunə 4: Tee channel — məlumatı iki yerə

```go
package main

import (
    "fmt"
    "sync"
)

// tee — bir kanalı iki kanala kopyalayır
func tee[T any](done <-chan struct{}, in <-chan T) (<-chan T, <-chan T) {
    out1 := make(chan T)
    out2 := make(chan T)

    go func() {
        defer close(out1)
        defer close(out2)

        for val := range in {
            // Hər ikisi ala bilsin deyə lokal dəyişən
            var o1, o2 = out1, out2
            for i := 0; i < 2; i++ {
                select {
                case <-done:
                    return
                case o1 <- val:
                    o1 = nil // artıq göndərildi, nil et → bloklanmır
                case o2 <- val:
                    o2 = nil
                }
            }
        }
    }()

    return out1, out2
}

// generator — sadə kanal mənbəyi
func generate(nums ...int) <-chan int {
    out := make(chan int)
    go func() {
        defer close(out)
        for _, n := range nums {
            out <- n
        }
    }()
    return out
}

func main() {
    done := make(chan struct{})
    defer close(done)

    source := generate(10, 20, 30, 40, 50)
    ch1, ch2 := tee(done, source)

    var wg sync.WaitGroup
    wg.Add(2)

    go func() {
        defer wg.Done()
        for v := range ch1 {
            fmt.Printf("Logger aldı: %d\n", v)
        }
    }()

    go func() {
        defer wg.Done()
        for v := range ch2 {
            fmt.Printf("Processor aldı: %d\n", v)
        }
    }()

    wg.Wait()
}
```

### Nümunə 5: Pipeline-da xəta idarəetməsi

```go
package main

import (
    "fmt"
    "strconv"
)

type ResultOrErr[T any] struct {
    Value T
    Err   error
}

// parseInts — string kanalını int kanalına çevirir, xətaları saxlayır
func parseInts(in <-chan string) <-chan ResultOrErr[int] {
    out := make(chan ResultOrErr[int])
    go func() {
        defer close(out)
        for s := range in {
            n, err := strconv.Atoi(s)
            out <- ResultOrErr[int]{Value: n, Err: err}
        }
    }()
    return out
}

// doubles — xəta olmayanları ikiqat edir
func doubles(in <-chan ResultOrErr[int]) <-chan ResultOrErr[int] {
    out := make(chan ResultOrErr[int])
    go func() {
        defer close(out)
        for v := range in {
            if v.Err != nil {
                out <- v // xətanı aşağı ötür
                continue
            }
            out <- ResultOrErr[int]{Value: v.Value * 2}
        }
    }()
    return out
}

func main() {
    inputs := []string{"3", "7", "abc", "12", "xyz", "5"}

    source := make(chan string, len(inputs))
    for _, s := range inputs {
        source <- s
    }
    close(source)

    pipeline := doubles(parseInts(source))

    for r := range pipeline {
        if r.Err != nil {
            fmt.Printf("XƏTA: %v\n", r.Err)
        } else {
            fmt.Printf("Nəticə: %d\n", r.Value)
        }
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — CSV pipeline:**
CSV faylını oxuyub, hər sətri parse edib, müəyyən sütuna görə filter edib, JSON-a çevirən pipeline yazın. Hər mərhələ ayrı goroutine olsun.

**Tapşırıq 2 — Image resizer:**
Bir qovluqdakı şəkilləri 4 worker ilə paralel resize edən fan-out/fan-in sistemi yazın. `errgroup` istifadə edin.

**Tapşırıq 3 — Duplikat axını:**
Tee channel istifadə edərək: bir websocket mesaj axınını həm real-time işləyin, həm də database-ə yazın.

**Tapşırıq 4 — Throttled crawler:**
URL-ləri pipeline ilə crawl edən sistem: fetch → parse links → filter → store. Semaphore ilə eyni anda max 10 HTTP sorğu.

**Tapşırıq 5 — Pipeline benchmark:**
Buffered (N=10, N=100) və unbuffered pipeline arasında throughput fərqini ölçün. Nə vaxt buffering kömək edir?

## PHP ilə Müqayisə

PHP-də bu tip paralel data processing ya həddən artıq mürəkkəb, ya da mümkün deyil. Go-da isə bu pattern-lər idiomatic kod sayılır — pipeline, fan-out/fan-in, tee dil-səviyyəsindəki channel primitivi üzərindən qurulur.

## Əlaqəli Mövzular

- [27-goroutines-and-channels](../core/27-goroutines-and-channels.md) — channel əsasları
- [01-advanced-concurrency](01-advanced-concurrency.md) — sync.Mutex, atomic
- [57-advanced-concurrency-2](02-advanced-concurrency-2.md) — errgroup, worker pool
- [28-context](../core/28-context.md) — context ilə pipeline ləğvi
- [51-rate-limiting](../backend/15-rate-limiting.md) — rate limiter pattern-ləri
