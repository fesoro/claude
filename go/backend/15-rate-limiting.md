# Rate Limiting (Senior)

## İcmal

Rate limiting — API sorğu sayını, resursi müəyyən müddətdə limitləmək üçün istifadə olunan texnikadır. DDoS qorunması, API kvota tətbiqi, resurs idarəsi, fair usage policy — real production sistemlərinin vacib hissəsidir. Go-da `time.Ticker`, `golang.org/x/time/rate` (Token Bucket), custom Sliding Window implementasiyası mövcuddur.

## Niyə Vacibdir

- Bütün public API-lar rate limiting tətbiq edir (GitHub, Stripe, Twilio)
- DDoS-dan qorunma — həm server, həm müştəri tərəfindən
- Resource fairness — bir müştəri bütün resursları tükətməsin
- Cost control — cloud API-larında artıq sorğu büdcəni artırır

## Əsas Anlayışlar

### Əsas Alqoritmlər

| Alqoritm | Məntiq | Burst? | Memory |
|----------|--------|--------|--------|
| Fixed Window | Hər N saniyədə X sorğu | Bəli (window başında) | Az |
| Sliding Window | Rolling N saniyədə X sorğu | Hamar | Orta |
| Token Bucket | Token toplanır, hər sorğu 1 token | Bəli (max bucket) | Az |
| Leaky Bucket | Sabit sürətlə çıxış | Xeyr | Az |

### Token Bucket Alqoritmi

```
Bucket: [●●●●●] (max 5 token)

Hər sorğu: 1 token götür
Hər 500ms: 1 token əlavə et (max-a qədər)

Burst: başlanğıcda 5 sorğu anında keçir
Steady state: 2 sorğu/saniyə
```

`golang.org/x/time/rate` — Token Bucket-in standart implementasiyası:

```go
r := rate.NewLimiter(rate.Limit(10), 20) // 10 sorğu/saniyə, burst 20
r.Allow()      // bool — token varsa true, yoxdursa false (gözləmmir)
r.Wait(ctx)    // token gözlə (bloklar)
r.Reserve()    // reservation al — nə vaxt davam etmək olar?
```

### Sliding Window

Son N saniyəni "sürüşən pəncərə" kimi izlə:

```
Now: 13:00:05
Window: [13:00:04, 13:00:05] (1 saniyəlik)
   Köhnə sorğular: 13:00:03.5 → kənar → sil
   Yeni sorğu: 13:00:05.1 → əlavə et
```

Daha hamar limitləmə, amma daha çox memory (hər sorğunun vaxtı saxlanır).

### time.Tick vs time.Ticker

```go
// time.Tick — goroutine leak riski (dayandırmaq olmur)
limiter := time.Tick(200 * time.Millisecond)

// Düzgün: time.NewTicker (dayandırılabilir)
ticker := time.NewTicker(200 * time.Millisecond)
defer ticker.Stop()
<-ticker.C
```

### Per-IP vs Global Limiter

- **Global:** Bütün istifadəçilər birlikdə sayılır
- **Per-IP:** Hər IP ayrıca limitlənir — daha ədalətli
- **Per-User:** Auth sonrası user ID-yə görə — planlı istifadə

## Praktik Baxış

### Distributed Rate Limiting

Bir neçə server instance-ı varsa — Redis-də mərkəzli limiter:

```
Client → Server 1 →
Client → Server 2 → Redis (INCR + EXPIRE)
Client → Server 3 →
```

`go-redis` + Lua script ilə atomic increment+check.

### HTTP Response Header-ləri

Standart rate limit header-ləri:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 87
X-RateLimit-Reset: 1609459200
Retry-After: 30
```

### Trade-off-lar

| Alqoritm | Hamar? | Burst? | Redis lazım? | Mürəkkəblik |
|----------|--------|--------|--------------|-------------|
| Fixed Window | Xeyr | Bəli | Xeyr | Sadə |
| Sliding Window | Bəli | Hamar | Redis-lə | Orta |
| Token Bucket | Bəli | Bəli | Xeyr | Orta |
| Leaky Bucket | Çox hamar | Xeyr | Xeyr | Sadə |

### Anti-pattern-lər

```go
// Anti-pattern 1: time.Tick — goroutine leak
limiter := time.Tick(time.Second) // leak! Stop edilmir

// Anti-pattern 2: Map-i lock olmadan istifadə
var visitors = map[string]*Limiter{} // data race!
visitors[ip] = newLimiter()          // concurrent yazma

// Düzgün:
var mu sync.RWMutex
mu.Lock()
visitors[ip] = newLimiter()
mu.Unlock()

// Anti-pattern 3: Hər requst-da yeni limiter
func handler(w http.ResponseWriter, r *http.Request) {
    limiter := rate.NewLimiter(10, 1) // hər dəfə yeni — limitləmə işləmir!
    limiter.Allow()
}

// Anti-pattern 4: Memory leak — köhnə visitor-ları silməmək
var visitors = map[string]*Visitor{}
// Visitors heç silinmir — uzun müddətdə memory dolur
// Cleanup goroutine əlavə edin
```

## Nümunələr

### Nümunə 1: golang.org/x/time/rate ilə Token Bucket

```go
package main

import (
    "context"
    "fmt"
    "net/http"
    "time"

    "golang.org/x/time/rate"
)

// Global limiter — bütün sorğular üçün
var globalLimiter = rate.NewLimiter(rate.Limit(10), 20)
// 10 sorğu/saniyə, burst 20

func handler(w http.ResponseWriter, r *http.Request) {
    // Allow — token varsa dərhal, yoxdursa false (gözləmir)
    if !globalLimiter.Allow() {
        w.Header().Set("Retry-After", "1")
        http.Error(w, `{"error":"rate_limit_exceeded"}`, http.StatusTooManyRequests)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    fmt.Fprintf(w, `{"status":"ok","time":"%s"}`, time.Now().Format(time.RFC3339))
}

// Wait variantı — sorğuyu gözlədirmək (qəbul etmək amma gecikdirmək)
func handlerWithWait(w http.ResponseWriter, r *http.Request) {
    ctx, cancel := context.WithTimeout(r.Context(), 5*time.Second)
    defer cancel()

    if err := globalLimiter.Wait(ctx); err != nil {
        http.Error(w, "timeout", http.StatusServiceUnavailable)
        return
    }

    fmt.Fprint(w, "OK")
}

func main() {
    http.HandleFunc("/api", handler)
    http.HandleFunc("/api/wait", handlerWithWait)

    fmt.Println("Server :8080")
    http.ListenAndServe(":8080", nil)
}
```

### Nümunə 2: Per-IP Rate Limiter

```go
package main

import (
    "fmt"
    "net"
    "net/http"
    "sync"
    "time"

    "golang.org/x/time/rate"
)

type visitor struct {
    limiter  *rate.Limiter
    lastSeen time.Time
}

type IPRateLimiter struct {
    mu       sync.RWMutex
    visitors map[string]*visitor
    limit    rate.Limit
    burst    int
    cleanup  time.Duration
}

func NewIPRateLimiter(r rate.Limit, burst int) *IPRateLimiter {
    rl := &IPRateLimiter{
        visitors: make(map[string]*visitor),
        limit:    r,
        burst:    burst,
        cleanup:  3 * time.Minute,
    }

    // Köhnə visitor-ları periodik sil
    go rl.cleanupLoop()
    return rl
}

func (rl *IPRateLimiter) getVisitor(ip string) *rate.Limiter {
    rl.mu.Lock()
    defer rl.mu.Unlock()

    v, ok := rl.visitors[ip]
    if !ok {
        limiter := rate.NewLimiter(rl.limit, rl.burst)
        rl.visitors[ip] = &visitor{limiter: limiter, lastSeen: time.Now()}
        return limiter
    }

    v.lastSeen = time.Now()
    return v.limiter
}

func (rl *IPRateLimiter) cleanupLoop() {
    ticker := time.NewTicker(rl.cleanup)
    defer ticker.Stop()

    for range ticker.C {
        rl.mu.Lock()
        for ip, v := range rl.visitors {
            if time.Since(v.lastSeen) > rl.cleanup {
                delete(rl.visitors, ip)
            }
        }
        rl.mu.Unlock()
    }
}

func (rl *IPRateLimiter) Middleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        // X-Forwarded-For başlığından gerçek IP
        ip := r.Header.Get("X-Forwarded-For")
        if ip == "" {
            ip, _, _ = net.SplitHostPort(r.RemoteAddr)
        }

        limiter := rl.getVisitor(ip)
        if !limiter.Allow() {
            w.Header().Set("X-RateLimit-Limit", fmt.Sprintf("%.0f", float64(rl.limit)))
            w.Header().Set("Retry-After", "1")
            http.Error(w, "429 Too Many Requests", http.StatusTooManyRequests)
            return
        }

        // Qalan token-i header-ə əlavə et
        remaining := limiter.Tokens()
        w.Header().Set("X-RateLimit-Remaining", fmt.Sprintf("%.0f", remaining))

        next.ServeHTTP(w, r)
    })
}

func main() {
    // IP başına saniyədə 5 sorğu, burst 10
    limiter := NewIPRateLimiter(5, 10)

    mux := http.NewServeMux()
    mux.HandleFunc("/api/data", func(w http.ResponseWriter, r *http.Request) {
        fmt.Fprintf(w, `{"data":"ok","ip":"%s"}`, r.RemoteAddr)
    })

    handler := limiter.Middleware(mux)

    fmt.Println("Server :8080 — IP başına 5 req/s, burst 10")
    http.ListenAndServe(":8080", handler)
}
```

### Nümunə 3: Sliding Window Rate Limiter

```go
package main

import (
    "fmt"
    "sync"
    "time"
)

type SlidingWindowLimiter struct {
    mu       sync.Mutex
    requests []time.Time
    limit    int
    window   time.Duration
}

func NewSlidingWindowLimiter(limit int, window time.Duration) *SlidingWindowLimiter {
    return &SlidingWindowLimiter{
        requests: make([]time.Time, 0, limit),
        limit:    limit,
        window:   window,
    }
}

func (swl *SlidingWindowLimiter) Allow() bool {
    swl.mu.Lock()
    defer swl.mu.Unlock()

    now := time.Now()
    cutoff := now.Add(-swl.window)

    // Köhnə sorğuları filtrə et
    var valid []time.Time
    for _, t := range swl.requests {
        if t.After(cutoff) {
            valid = append(valid, t)
        }
    }
    swl.requests = valid

    if len(swl.requests) >= swl.limit {
        return false
    }

    swl.requests = append(swl.requests, now)
    return true
}

func (swl *SlidingWindowLimiter) Remaining() int {
    swl.mu.Lock()
    defer swl.mu.Unlock()

    cutoff := time.Now().Add(-swl.window)
    count := 0
    for _, t := range swl.requests {
        if t.After(cutoff) {
            count++
        }
    }

    if swl.limit-count < 0 {
        return 0
    }
    return swl.limit - count
}

func main() {
    // 5 saniyədə 3 sorğu
    limiter := NewSlidingWindowLimiter(3, 5*time.Second)

    fmt.Println("Sliding Window Rate Limiter Testi")
    fmt.Println("Limit: 3 sorğu / 5 saniyə\n")

    for i := 1; i <= 8; i++ {
        allowed := limiter.Allow()
        remaining := limiter.Remaining()
        status := "✓ KEÇDİ"
        if !allowed {
            status = "✗ RƏDD"
        }
        fmt.Printf("[Sorğu %d] %s (qalan: %d)\n", i, status, remaining)
        time.Sleep(600 * time.Millisecond)
    }
}
```

### Nümunə 4: Rate Limit Middleware ilə HTTP Header-ləri

```go
package main

import (
    "fmt"
    "net/http"
    "strconv"
    "time"

    "golang.org/x/time/rate"
)

type RateLimitMiddleware struct {
    limiter  *rate.Limiter
    limit    int
    window   time.Duration
    resetAt  time.Time
}

func NewRateLimitMiddleware(limit int, window time.Duration) *RateLimitMiddleware {
    r := rate.Every(window / time.Duration(limit))
    return &RateLimitMiddleware{
        limiter: rate.NewLimiter(r, limit),
        limit:   limit,
        window:  window,
        resetAt: time.Now().Add(window),
    }
}

func (m *RateLimitMiddleware) Wrap(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        // Reset vaxtı keçibsə yenilə
        if time.Now().After(m.resetAt) {
            m.resetAt = time.Now().Add(m.window)
        }

        remaining := int(m.limiter.Tokens())
        resetUnix := m.resetAt.Unix()

        // Rate limit header-ləri
        w.Header().Set("X-RateLimit-Limit", strconv.Itoa(m.limit))
        w.Header().Set("X-RateLimit-Remaining", strconv.Itoa(remaining))
        w.Header().Set("X-RateLimit-Reset", strconv.FormatInt(resetUnix, 10))

        if !m.limiter.Allow() {
            retryAfter := time.Until(m.resetAt).Seconds()
            w.Header().Set("Retry-After", fmt.Sprintf("%.0f", retryAfter))
            http.Error(w, `{"error":"rate_limit_exceeded","retry_after":`+
                fmt.Sprintf("%.0f", retryAfter)+`}`,
                http.StatusTooManyRequests)
            return
        }

        next.ServeHTTP(w, r)
    })
}

func main() {
    mw := NewRateLimitMiddleware(10, time.Minute) // dəqiqədə 10

    handler := mw.Wrap(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("Content-Type", "application/json")
        fmt.Fprintf(w, `{"message":"success","time":"%s"}`, time.Now().Format(time.RFC3339))
    }))

    http.Handle("/api/", handler)
    fmt.Println("Server :8080 — dəqiqədə 10 sorğu limiti")
    http.ListenAndServe(":8080", nil)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Tier-based Rate Limiting:**
Free/Pro/Enterprise plan üçün fərqli limitlər tətbiq edin. JWT token-dən plan məlumatını alıb müvafiq limiter seçin.

**Tapşırıq 2 — Redis-based Distributed Limiter:**
`go-redis` ilə Lua script istifadə edərək atomic INCR+EXPIRE ilə distributed rate limiter yazın. Bir neçə server instance-ı eyni limiti paylaşsın.

**Tapşırıq 3 — Client-side Rate Limiting:**
Xarici API-a sorğu göndərən client yazın. API-nın limitini (`X-RateLimit-Remaining`) oxuyub, limit aşılanda `Retry-After` vaxtı qədər gözləsin.

**Tapşırıq 4 — Circuit Breaker ilə Rate Limiter:**
Rate limit + circuit breaker kombinasiyası: ardıcıl 5 xəta olsa circuit açılsın, 30 saniyə sonra yenidən cəhd etsin.

## PHP ilə Müqayisə

Laravel `throttle` middleware arxasında Token Bucket / Fixed Window alqoritmləri var. Go-da eyni alqoritmi özün seçib yazırsan.

```php
// Laravel — ThrottleRequests middleware
Route::middleware('throttle:60,1') // 60 req/dəqiqə
    ->group(function () {
        Route::get('/api/users', ...);
    });

// Manual:
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

```go
// Go — middleware ilə
func RateLimitMiddleware(limit rate.Limit, burst int) func(http.Handler) http.Handler {
    limiter := rate.NewLimiter(limit, burst)
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            if !limiter.Allow() {
                http.Error(w, "429 Too Many Requests", http.StatusTooManyRequests)
                return
            }
            next.ServeHTTP(w, r)
        })
    }
}
```

**Əsas fərq:** Laravel middleware konfiqurasiyası ilə işləyir; Go-da alqoritm seçimi, burst dəyəri, distributed vs in-memory qərarları özün verirsən.

## Əlaqəli Mövzular

- [27-goroutines-and-channels](../core/27-goroutines-and-channels.md) — Goroutine ilə limiter
- [28-context](../core/28-context.md) — Context ilə timeout
- [01-http-server](01-http-server.md) — HTTP middleware
- [03-middleware-and-routing](03-middleware-and-routing.md) — Middleware zənciri
- [63-caching](../advanced/08-caching.md) — Redis ilə distributed limiting
- [62-security](../advanced/07-security.md) — DDoS qorunması
