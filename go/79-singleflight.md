# Singleflight: Request Deduplication (Lead)

## İcmal

`golang.org/x/sync/singleflight` — eyni key üçün eyni anda çoxlu gözləyən goroutine-i bir dəfə işlədib nəticəni hamısına paylaşır. **Thundering herd** (ildırım sürüsü) probleminə — cache miss zamanı hamı eyni anda DB-ni döyəcləyir — birbaşa həll.

PHP-də bu problem queue ilə aradan qaldırılır. Go-da `singleflight` eyni prosesin içində, sıfır latency overhead ilə işləyir.

## Niyə Vacibdir

- Cache expire olur → 1000 goroutine eyni anda DB-yə sorğu edir
- `singleflight` ilə → 1 sorğu edir, nəticəni 1000-ə paylaşır
- Redis/DB yükü dramatik azalır
- Hot key problemi: viral post, trending məhsul — hər millisekund onlarla sorğu
- Production-da cache stampede → DB-ni öldürür → site düşür

## Əsas Anlayışlar

**`group.Do(key, fn)`:**
- Eyni `key` üçün çağırılırsa → yalnız birinci `fn`-i işlədir
- Digərləri birincinin nəticəsini gözləyir
- `fn` tamamlanan kimi hamısı eyni nəticəni alır

**`group.DoChan(key, fn)`:**
- Channel qaytarır — bloklamadan gözləmək üçün

**`group.Forget(key)`:**
- `key`-ni qrupdan çıxar — növbəti `Do` çağırışı yeni sorğu başladacaq

**Shared = true vs false:**
- `Result.Shared`: `true` isə nəticə bir neçə gözləyəni arasında paylaşılıb

## Praktik Baxış

**Nə vaxt istifadə et:**
- Redis cache miss → DB sorğusu (ən çox istifadə halı)
- Expensive hesablamalar (aggregation, report)
- External API sorğuları (rate limit var)

**Nə vaxt istifadə etmə:**
- Hər sorğu fərqli parametrlərlə (key unikal olmalıdır, eyni key olmaz)
- Write əməliyyatları — eyni write-ı iki dəfə etmə problemi yaranar
- Çox nadir cache miss-lər — overhead dəyməz

**Trade-off-lar:**
- Bir sorğu uzun sürərsə hamı gözləyir — timeout lazımdır
- Xəta halında hamı eyni xətanı alır — partial retry mümkün deyil
- Key seçimi vacibdir: çox geniş key → fərqli məlumatlar qarışar

## Nümunələr

### Nümunə 1: Cache-aside pattern ilə

```go
package main

import (
    "context"
    "encoding/json"
    "fmt"
    "time"

    "golang.org/x/sync/singleflight"
)

// go get golang.org/x/sync

type User struct {
    ID   int64  `json:"id"`
    Name string `json:"name"`
}

type UserCache struct {
    group  singleflight.Group
    cache  map[string]*User  // real layihədə: Redis
    mu     sync.RWMutex
}

func (c *UserCache) GetUser(ctx context.Context, id int64) (*User, error) {
    key := fmt.Sprintf("user:%d", id)

    // Cache-dən oxu
    c.mu.RLock()
    if user, ok := c.cache[key]; ok {
        c.mu.RUnlock()
        return user, nil
    }
    c.mu.RUnlock()

    // Cache miss — singleflight ilə DB sorğusu
    // Eyni key üçün 100 goroutine gəlsə → 1 DB sorğusu
    result, err, shared := c.group.Do(key, func() (interface{}, error) {
        // Yalnız bir dəfə çalışır
        user, err := fetchUserFromDB(ctx, id)
        if err != nil {
            return nil, err
        }

        // Cache-ə yaz
        c.mu.Lock()
        c.cache[key] = user
        c.mu.Unlock()

        return user, nil
    })

    if err != nil {
        return nil, err
    }

    _ = shared // true isə nəticə paylaşıldı
    return result.(*User), nil
}

func fetchUserFromDB(ctx context.Context, id int64) (*User, error) {
    // Real DB sorğusu simulyasiyası
    time.Sleep(100 * time.Millisecond)
    return &User{ID: id, Name: fmt.Sprintf("User-%d", id)}, nil
}
```

### Nümunə 2: Redis ilə real implementasiya

```go
package main

import (
    "context"
    "encoding/json"
    "fmt"
    "time"

    "github.com/redis/go-redis/v9"
    "golang.org/x/sync/singleflight"
)

type ProductService struct {
    db    DB               // database interface
    redis *redis.Client
    group singleflight.Group
}

func (s *ProductService) GetProduct(ctx context.Context, id int64) (*Product, error) {
    key := fmt.Sprintf("product:%d", id)

    // 1. Redis-dən yoxla
    val, err := s.redis.Get(ctx, key).Bytes()
    if err == nil {
        var p Product
        json.Unmarshal(val, &p)
        return &p, nil
    }

    // 2. Cache miss — singleflight
    result, err, _ := s.group.Do(key, func() (interface{}, error) {
        // DB sorğusu — yalnız bir dəfə
        product, err := s.db.GetProduct(ctx, id)
        if err != nil {
            return nil, err
        }

        // Redis-ə yaz — 5 dəqiqə TTL
        if data, err := json.Marshal(product); err == nil {
            s.redis.Set(ctx, key, data, 5*time.Minute)
        }

        return product, nil
    })

    if err != nil {
        return nil, err
    }

    return result.(*Product), nil
}
```

### Nümunə 3: Forget — məcburi yeniləmə

```go
package main

import (
    "context"
    "fmt"
    "time"

    "golang.org/x/sync/singleflight"
)

type ReportService struct {
    group singleflight.Group
    cache map[string][]byte
}

// Ağır hesablama — 30 saniyə çəkə bilər
func (s *ReportService) GetMonthlySalesReport(ctx context.Context, month string) ([]byte, error) {
    key := "monthly_report:" + month

    result, err, _ := s.group.Do(key, func() (interface{}, error) {
        // Çox ağır hesablama — 30 saniyə
        time.Sleep(30 * time.Second)
        return generateReport(ctx, month)
    })

    if err != nil {
        return nil, err
    }
    return result.([]byte), nil
}

// Admin cache-i sıfırlamaq istəyir
func (s *ReportService) InvalidateReport(month string) {
    // Forget — növbəti Do çağırışı yenidən hesablayacaq
    s.group.Forget("monthly_report:" + month)
    delete(s.cache, "monthly_report:"+month)
}

func generateReport(ctx context.Context, month string) ([]byte, error) {
    return []byte(fmt.Sprintf(`{"month":"%s","total":1000}`, month)), nil
}
```

### Nümunə 4: Timeout ilə birlikdə

```go
package main

import (
    "context"
    "fmt"
    "time"

    "golang.org/x/sync/singleflight"
)

type DataService struct {
    group singleflight.Group
}

func (s *DataService) FetchWithTimeout(id string, timeout time.Duration) ([]byte, error) {
    ctx, cancel := context.WithTimeout(context.Background(), timeout)
    defer cancel()

    type result struct {
        data []byte
        err  error
    }

    ch := s.group.DoChan(id, func() (interface{}, error) {
        return expensiveFetch(ctx, id)
    })

    select {
    case res := <-ch:
        if res.Err != nil {
            return nil, res.Err
        }
        return res.Val.([]byte), nil
    case <-ctx.Done():
        // Timeout — amma goroutine davam edir (digərləri üçün)
        return nil, fmt.Errorf("timeout: %w", ctx.Err())
    }
}

func expensiveFetch(ctx context.Context, id string) ([]byte, error) {
    // Uzun sürən əməliyyat
    select {
    case <-ctx.Done():
        return nil, ctx.Err()
    case <-time.After(200 * time.Millisecond):
        return []byte(`{"data":"result"}`), nil
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
Benchmark yaz: 100 goroutine eyni user ID ilə `GetUser` çağırır. `singleflight` olmadan vs ilə DB sorğusu sayını ölç.

**Tapşırıq 2:**
Redis cache + singleflight birləşdir. Cache miss olarsa singleflight DB-ni yalnız bir dəfə döysün. TTL: 5 dəqiqə.

**Tapşırıq 3:**
`group.Forget` ilə "cache invalidation" endpoint-i yaz: admin `/admin/cache/invalidate/:key` çağıranda növbəti sorğu DB-dən təzə məlumat alır.

## Əlaqəli Mövzular

- [63-caching.md](63-caching.md) — Caching pattern-lər
- [56-advanced-concurrency.md](56-advanced-concurrency.md) — sync paketi
- [75-errgroup.md](75-errgroup.md) — Paralel goroutine idarəsi
- [27-goroutines-and-channels.md](27-goroutines-and-channels.md) — Goroutine əsasları
