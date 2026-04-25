# Advanced Concurrency 2 — errgroup, semaphore, worker pool, pipeline (Lead)

## İcmal

Bu mövzu production Go servisinin concurrency arxitekturasını əhatə edir: `errgroup` ilə xəta idarəetmə, semaphore pattern ilə resurs məhdudlaşdırma, worker pool ilə iş növbəsi, stateful goroutine ilə shared state-in alternativ idarəetməsi. PHP-də bu pattern-lər demək olar ki yoxdur çünki hər request izolə olunmuş proses kimi işləyir.

## Niyə Vacibdir

- Paralel HTTP çağırışlarda bir xəta olduqda digərlərini ləğv etmək — `errgroup`
- Eyni anda maksimum N goroutine işləməsini məhdudlaşdırmaq — semaphore
- CPU/IO bounded işlər üçün sabit sayda worker-ə iş paylamaq — worker pool
- Goroutine leak-dən qorunmaq — hər goroutine exit yolu olmalıdır
- Goroutine sayını `runtime.NumGoroutine()` ilə monitoring etmək

## Əsas Anlayışlar

### Goroutine vs OS Thread

| Xüsusiyyət | Goroutine | OS Thread |
|------------|-----------|-----------|
| İlkin yaddaş | ~2–8 KB | ~1–8 MB |
| Yaranma sürəti | mikrosan. | millisan. |
| Planlayıcı | Go runtime (M:N) | OS kernel |
| Limit | yüz minlər | minlər |

Go M:N modelini işlədir: M goroutine N OS thread üzərindən planlanır. `GOMAXPROCS` (default: CPU sayı) nə qədər thread paralel işlədiyini müəyyənləşdirir.

### errgroup

`golang.org/x/sync/errgroup` paketi — paralel goroutine-ləri başlatmaq, birinin xəta verməsi halında digərlərini context vasitəsilə ləğv etmək üçün. `sync.WaitGroup` + error propagation kombinasiyasıdır.

### Semaphore pattern

Buffered channel ilə iş görülür: `make(chan struct{}, N)` — eyni anda maksimum N goroutine aktiv ola bilər. Database connection pool, external API rate limit, CPU-intensive işlər üçün vacibdir.

### Worker Pool

Sabit sayda worker goroutine daimi işləyir, iş kanalından tapşırıqlar alır. Job/worker ayrılığı sayəsində goroutine sayı proqnozlaşdırıla bilər.

## Praktik Baxış

### Nə vaxt errgroup, nə vaxt WaitGroup?

```
Xəta propagation lazımdır       → errgroup
Context ləğvi lazımdır          → errgroup.WithContext
Sadəcə hamısını gözlə           → sync.WaitGroup
```

### Semaphore vs Worker Pool

```
Ani burst var, işlər qısa       → semaphore (goroutine per job, throttled)
Uzun müddətli, sabit yük        → worker pool (sabit goroutine sayı)
```

### Anti-pattern-lər

```go
// YANLIŞ: goroutine leak — channel heç vaxt yazılmayacaq
func fetch(url string) {
    ch := make(chan string)
    go func() { ch <- httpGet(url) }() // receiver yoxdursa leak!
    // ch-dən heç vaxt oxunmur
}

// DOĞRU: buffered channel və ya timeout
func fetch(url string) string {
    ch := make(chan string, 1) // buffered
    go func() { ch <- httpGet(url) }()
    select {
    case v := <-ch:
        return v
    case <-time.After(5 * time.Second):
        return ""
    }
}
```

```go
// YANLIŞ: goroutine-ə pointer ilə loop variable ötürmək
for i := 0; i < 10; i++ {
    go func() { fmt.Println(i) }() // hər zaman 10 çap olunur!
}

// DOĞRU: kopyasını ötür
for i := 0; i < 10; i++ {
    go func(n int) { fmt.Println(n) }(i)
}
```

### Production hazırlıq

- Goroutine sayını əvvəlcədən məhdudlaşdırın — metrikanı `runtime.NumGoroutine()` ilə izləyin
- Worker pool-da `context.Done()` nəzərə alın — graceful shutdown üçün
- `errgroup.WithContext` istifadə edərkən, context ləğvindən sonra cleanup icrası etməyi unutmayın

## Nümunələr

### Nümunə 1: errgroup ilə paralel API çağırışları

```go
package main

import (
    "context"
    "fmt"
    "net/http"
    "time"

    "golang.org/x/sync/errgroup"
)

type PageResult struct {
    URL    string
    Status int
}

func fetchURL(ctx context.Context, url string) (*PageResult, error) {
    req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
    if err != nil {
        return nil, fmt.Errorf("sorğu yaratma (%s): %w", url, err)
    }

    client := &http.Client{Timeout: 5 * time.Second}
    resp, err := client.Do(req)
    if err != nil {
        return nil, fmt.Errorf("sorğu (%s): %w", url, err)
    }
    defer resp.Body.Close()

    return &PageResult{URL: url, Status: resp.StatusCode}, nil
}

func fetchAll(urls []string) ([]*PageResult, error) {
    g, ctx := errgroup.WithContext(context.Background())

    results := make([]*PageResult, len(urls))

    for i, url := range urls {
        i, url := i, url // loop variable kopyası
        g.Go(func() error {
            res, err := fetchURL(ctx, url)
            if err != nil {
                return err // biri xəta versə, digərləri ləğv olunur
            }
            results[i] = res
            return nil
        })
    }

    if err := g.Wait(); err != nil {
        return nil, err
    }
    return results, nil
}

func main() {
    urls := []string{
        "https://httpbin.org/get",
        "https://httpbin.org/status/200",
        "https://httpbin.org/delay/1",
    }

    results, err := fetchAll(urls)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    for _, r := range results {
        fmt.Printf("%s → %d\n", r.URL, r.Status)
    }
}
```

### Nümunə 2: Semaphore pattern ilə throttled DB queries

```go
package main

import (
    "context"
    "fmt"
    "sync"
    "time"
)

// Semaphore — eyni anda maksimum N goroutine
type Semaphore struct {
    ch chan struct{}
}

func NewSemaphore(n int) *Semaphore {
    return &Semaphore{ch: make(chan struct{}, n)}
}

func (s *Semaphore) Acquire(ctx context.Context) error {
    select {
    case s.ch <- struct{}{}:
        return nil
    case <-ctx.Done():
        return ctx.Err()
    }
}

func (s *Semaphore) Release() {
    <-s.ch
}

// simulyasiya: DB query
func queryUser(ctx context.Context, id int) (string, error) {
    select {
    case <-time.After(50 * time.Millisecond): // DB gecikmə
        return fmt.Sprintf("user_%d", id), nil
    case <-ctx.Done():
        return "", ctx.Err()
    }
}

func loadUsers(ctx context.Context, ids []int) ([]string, error) {
    sem := NewSemaphore(5) // eyni anda max 5 DB sorğusu
    var (
        mu      sync.Mutex
        results []string
        errs    []error
        wg      sync.WaitGroup
    )

    for _, id := range ids {
        id := id
        wg.Add(1)
        go func() {
            defer wg.Done()

            if err := sem.Acquire(ctx); err != nil {
                mu.Lock()
                errs = append(errs, err)
                mu.Unlock()
                return
            }
            defer sem.Release()

            user, err := queryUser(ctx, id)
            mu.Lock()
            defer mu.Unlock()
            if err != nil {
                errs = append(errs, err)
            } else {
                results = append(results, user)
            }
        }()
    }

    wg.Wait()
    if len(errs) > 0 {
        return nil, errs[0]
    }
    return results, nil
}

func main() {
    ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
    defer cancel()

    ids := make([]int, 20)
    for i := range ids {
        ids[i] = i + 1
    }

    start := time.Now()
    users, err := loadUsers(ctx, ids)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("%d istifadəçi yükləndi, müddət: %v\n", len(users), time.Since(start).Round(time.Millisecond))
}
```

### Nümunə 3: Generic worker pool

```go
package main

import (
    "context"
    "fmt"
    "sync"
    "time"
)

type Job[T, R any] struct {
    Input  T
    Result chan<- Result[R]
}

type Result[R any] struct {
    Value R
    Err   error
}

// WorkerPool — sabit sayda worker, channel vasitəsilə iş alır
type WorkerPool[T, R any] struct {
    jobs    chan Job[T, R]
    wg      sync.WaitGroup
    process func(context.Context, T) (R, error)
}

func NewWorkerPool[T, R any](ctx context.Context, workers int, fn func(context.Context, T) (R, error)) *WorkerPool[T, R] {
    p := &WorkerPool[T, R]{
        jobs:    make(chan Job[T, R], workers*2),
        process: fn,
    }

    for i := 0; i < workers; i++ {
        p.wg.Add(1)
        go func() {
            defer p.wg.Done()
            for {
                select {
                case job, ok := <-p.jobs:
                    if !ok {
                        return // kanal bağlandı
                    }
                    val, err := p.process(ctx, job.Input)
                    job.Result <- Result[R]{Value: val, Err: err}
                case <-ctx.Done():
                    return
                }
            }
        }()
    }

    return p
}

func (p *WorkerPool[T, R]) Submit(input T) <-chan Result[R] {
    ch := make(chan Result[R], 1)
    p.jobs <- Job[T, R]{Input: input, Result: ch}
    return ch
}

func (p *WorkerPool[T, R]) Close() {
    close(p.jobs)
    p.wg.Wait()
}

func main() {
    ctx := context.Background()

    // Ağır hesablama simulyasiyası
    pool := NewWorkerPool[int, int](ctx, 4, func(ctx context.Context, n int) (int, error) {
        time.Sleep(10 * time.Millisecond) // CPU iş simulyasiyası
        return n * n, nil
    })
    defer pool.Close()

    // 20 iş göndər
    futures := make([]<-chan Result[int], 20)
    for i := 0; i < 20; i++ {
        futures[i] = pool.Submit(i)
    }

    // Nəticələri topla
    for i, future := range futures {
        res := <-future
        if res.Err != nil {
            fmt.Printf("Job %d xəta: %v\n", i, res.Err)
        } else {
            fmt.Printf("%d² = %d\n", i, res.Value)
        }
    }
}
```

### Nümunə 4: Stateful goroutine — Mutex alternativ

```go
package main

import "fmt"

type readOp struct {
    key  string
    resp chan string
}

type writeOp struct {
    key   string
    value string
    resp  chan bool
}

// StateManager — yalnız bir goroutine state-ə toxunur
// Data race fiziki cəhətdən mümkün deyil
func NewStateManager() (reads chan<- readOp, writes chan<- writeOp) {
    r := make(chan readOp)
    w := make(chan writeOp)

    go func() {
        state := map[string]string{}
        for {
            select {
            case op := <-r:
                op.resp <- state[op.key]
            case op := <-w:
                state[op.key] = op.value
                op.resp <- true
            }
        }
    }()

    return r, w
}

func main() {
    reads, writes := NewStateManager()

    // Yazma
    resp := make(chan bool, 1)
    writes <- writeOp{key: "env", value: "production", resp: resp}
    <-resp

    // Oxuma
    rresp := make(chan string, 1)
    reads <- readOp{key: "env", resp: rresp}
    fmt.Println("env:", <-rresp) // production

    // QEYD: bu pattern Mutex-dən daha az yayğındır,
    // amma bəzən daha aydın semantika verir.
    // Çox say readOp/writeOp tipi olduqda çətinləşir.
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Parallel DB seed:**
Test database-ini paralel dolduran skript yazın. Eyni anda max 10 goroutine aktiv olsun. `errgroup` istifadə edin — ilk xəta bütün digərləri ləğv etsin.

**Tapşırıq 2 — Worker pool benchmark:**
1, 4, 8, 16, 32 worker-lik pool-larla 1000 iş üçün benchmark yazın (`testing.B`). CPU sayınızla optimal worker sayı arasındakı əlaqəni tapın.

**Tapşırıq 3 — Goroutine leak aşkarlaması:**
Aşağıdakı funksiyada goroutine leak var, tapın və düzəldin:
```go
func processAll(items []string) []string {
    results := make([]string, len(items))
    for i, item := range items {
        go func(i int, s string) {
            results[i] = strings.ToUpper(s)
        }(i, item)
    }
    return results // goroutine-lər bitməmiş qayıdır!
}
```

**Tapşırıq 4 — Graceful shutdown:**
Worker pool yaradın. `SIGTERM` siqnalı gəldikdə: yeni iş qəbul etməyi dayandırın, cari işlər bitsin, sonra çıxın.

## Əlaqəli Mövzular

- [28-context](28-context.md) — context.WithCancel, WithTimeout
- [53-graceful-shutdown](53-graceful-shutdown.md) — graceful shutdown pattern
- [56-advanced-concurrency](56-advanced-concurrency.md) — sync primitiv-lər
- [58-channel-patterns](58-channel-patterns.md) — fan-out/fan-in, pipeline
- [71-monitoring-and-observability](71-monitoring-and-observability.md) — goroutine count monitoring
