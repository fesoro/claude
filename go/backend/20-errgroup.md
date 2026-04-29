# errgroup: Paralel Xəta İdarəetməsi (Middle)

## İcmal

`golang.org/x/sync/errgroup` — bir qrup goroutine-i birlikdə idarə etmək, xəta halında hamısını ləğv etmək üçün istifadə olunur. Standart `sync.WaitGroup`-dan fərqi: xəta qaytarma və context ilə avtomatik ləğvetmə dəstəyi.

## Niyə Vacibdir

- N müstəqil sorğunu ardıcıl yox, paralel çağırmaq → latency N dəfə deyil, ən yavaşı qədər olur
- Bir goroutine xəta versə, digərləri avtomatik ləğv edilir — xəta itkisi olmur
- `WaitGroup` + error channel kombinasiyasına görə çox sadə, boilerplate yoxdur
- Real layihə: dashboard üçün user + orders + stats — 3 DB sorğusunu paralel et

## Əsas Anlayışlar

**`errgroup.Group`:**
- `g.Go(func() error)` — yeni goroutine başlat
- `g.Wait()` — hamısı bitənə qədər gözlə, ilk xətanı qaytar

**`errgroup.WithContext`:**
- Bir goroutine xəta versə → context avtomatik ləğv edilir
- Digər goroutine-lər `ctx.Done()` yoxlayıb dayana bilər

**`sync.WaitGroup` vs `errgroup`:**
```
WaitGroup:
- xəta toplaya bilmir
- channel + mutex boilerplate lazımdır

errgroup:
- ilk xətanı avtomatik qaytarır
- context ilə ləğvetmə daxildir
```

## Praktik Baxış

**Nə vaxt istifadə et:**
- Müstəqil iş parçaları paralel çalışmalıdır
- Hər hansı biri xəta versə bütün əməliyyat uğursuz sayılır
- Fan-out: bir input, N parallel iş

**Nə vaxt istifadə etmə:**
- Goroutine-lər bir-birinin nəticəsindən asılıdırsa (pipeline istifadə et)
- Biri xəta versə digərləri davam etməlidirsə (ayrı xəta toplama strategiyası lazımdır)

**Common mistakes:**
- Loop variable-i capture etməmək: `for _, v := range items { v := v; g.Go(...)` }`
- `g.Wait()` çağırmamaq — goroutine-lər leak edir
- context olmadan istifadə — ləğvetmə işləmir

## Nümunələr

### Nümunə 1: Paralel sorğular — dashboard

```go
package main

import (
    "context"
    "fmt"
    "time"

    "golang.org/x/sync/errgroup"
)

type DashboardData struct {
    User   *User
    Orders []*Order
    Stats  *Stats
}

type User struct{ ID int64; Name string }
type Order struct{ ID int64; Total float64 }
type Stats struct{ TotalOrders int; TotalSpent float64 }

func GetDashboard(ctx context.Context, userID int64) (*DashboardData, error) {
    data := &DashboardData{}

    // errgroup + context — biri xəta versə digərləri ləğv edilir
    g, ctx := errgroup.WithContext(ctx)

    g.Go(func() error {
        user, err := fetchUser(ctx, userID)
        if err != nil {
            return fmt.Errorf("user: %w", err)
        }
        data.User = user
        return nil
    })

    g.Go(func() error {
        orders, err := fetchOrders(ctx, userID)
        if err != nil {
            return fmt.Errorf("orders: %w", err)
        }
        data.Orders = orders
        return nil
    })

    g.Go(func() error {
        stats, err := fetchStats(ctx, userID)
        if err != nil {
            return fmt.Errorf("stats: %w", err)
        }
        data.Stats = stats
        return nil
    })

    if err := g.Wait(); err != nil {
        return nil, err
    }
    return data, nil
}

// Ardıcıl yol: 3 sorğu × 100ms = 300ms
// Paralel yol: max(100ms, 80ms, 60ms) = 100ms

func fetchUser(ctx context.Context, id int64) (*User, error) {
    time.Sleep(100 * time.Millisecond)
    return &User{ID: id, Name: "Orxan"}, nil
}

func fetchOrders(ctx context.Context, id int64) ([]*Order, error) {
    time.Sleep(80 * time.Millisecond)
    return []*Order{{ID: 1, Total: 150.0}}, nil
}

func fetchStats(ctx context.Context, id int64) (*Stats, error) {
    time.Sleep(60 * time.Millisecond)
    return &Stats{TotalOrders: 5, TotalSpent: 750.0}, nil
}
```

### Nümunə 2: Context ləğvi — biri xəta versə hamısı dayanır

```go
package main

import (
    "context"
    "fmt"
    "time"

    "golang.org/x/sync/errgroup"
)

func processItems(ctx context.Context, items []int) error {
    g, ctx := errgroup.WithContext(ctx)

    for _, item := range items {
        item := item // loop variable capture — kritik!
        g.Go(func() error {
            return processOne(ctx, item)
        })
    }

    return g.Wait() // ilk xəta qaytarılır, amma hamısının bitməsini gözləyir
}

func processOne(ctx context.Context, n int) error {
    select {
    case <-ctx.Done():
        // Başqa goroutine xəta verdi → context ləğv edildi
        fmt.Printf("Item %d dayandırıldı\n", n)
        return ctx.Err()
    case <-time.After(time.Duration(n*100) * time.Millisecond):
    }

    if n == 3 {
        return fmt.Errorf("item %d uğursuz", n)
    }

    fmt.Printf("Item %d tamamlandı\n", n)
    return nil
}
```

### Nümunə 3: Semaphore ilə paralellik məhdudlaşdırmaq

```go
package main

import (
    "context"
    "fmt"

    "golang.org/x/sync/errgroup"
    "golang.org/x/sync/semaphore"
)

// 1000 element, amma eyni anda maksimum 10 goroutine
func processBatch(ctx context.Context, items []string) error {
    const maxConcurrency = 10

    g, ctx := errgroup.WithContext(ctx)
    sem := semaphore.NewWeighted(maxConcurrency)

    for _, item := range items {
        item := item

        // Semaphore yer açılana qədər gözlə
        if err := sem.Acquire(ctx, 1); err != nil {
            return err // ctx ləğv edilib
        }

        g.Go(func() error {
            defer sem.Release(1)
            return processString(ctx, item)
        })
    }

    return g.Wait()
}

func processString(ctx context.Context, s string) error {
    fmt.Printf("Processing: %s\n", s)
    return nil
}
```

### Nümunə 4: Nəticə toplama — goroutine-safe

```go
package main

import (
    "context"
    "sync"

    "golang.org/x/sync/errgroup"
)

// Nəticələri goroutine-safe toplamaq üçün mutex
func fetchMultipleURLs(ctx context.Context, urls []string) (map[string]string, error) {
    var mu sync.Mutex
    results := make(map[string]string, len(urls))

    g, ctx := errgroup.WithContext(ctx)

    for _, url := range urls {
        url := url
        g.Go(func() error {
            body, err := fetch(ctx, url)
            if err != nil {
                return err
            }

            mu.Lock()
            results[url] = body
            mu.Unlock()
            return nil
        })
    }

    if err := g.Wait(); err != nil {
        return nil, err
    }
    return results, nil
}

func fetch(ctx context.Context, url string) (string, error) {
    return "response body", nil
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
JSONPlaceholder-dən üç endpoint paralel sorğula: `/users/1`, `/posts?userId=1`, `/todos?userId=1`. Nəticəni struct-a yığ. Ümumi vaxtı ölç.

**Tapşırıq 2:**
100 DB sorğusu var, amma eyni anda yalnız 5 açıq connection olsun. `errgroup` + `semaphore` ilə həll et.

**Tapşırıq 3:**
`WaitGroup` + error channel ilə yazılmış kodu `errgroup`-a köçür. Kod sətri sayını müqayisə et.

## PHP ilə Müqayisə

PHP/Laravel-də paralel əməliyyat üçün job/queue lazımdır: işlər `Queue::push()` ilə kuyruqa göndərilir, ayrı worker prosesi onları icra edir. Bu fərqli proses/request deməkdir. Go-da `errgroup` ilə eyni prosesdə, sadə kod ilə paralel işlər görülür — əlavə infrastruktur (Redis, Horizon, worker daemon) tələb etmir.

```
PHP/Laravel                          →  Go
Queue::push(new FetchUserJob)        →  g.Go(func() error { return fetchUser(ctx) })
Queue::push(new FetchOrdersJob)      →  g.Go(func() error { return fetchOrders(ctx) })
// ayrı worker prosesi icra edir     →  g.Wait() — eyni prosesdə
```

## Əlaqəli Mövzular

- [27-goroutines-and-channels.md](../core/27-goroutines-and-channels.md) — Goroutine əsasları
- [28-context.md](../core/28-context.md) — Context ilə ləğvetmə
- [56-advanced-concurrency.md](../advanced/01-advanced-concurrency.md) — sync paketi
- [58-channel-patterns.md](../advanced/03-channel-patterns.md) — Fan-in/fan-out pattern-lər
