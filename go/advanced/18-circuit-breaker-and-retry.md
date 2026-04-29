# Circuit Breaker və Retry Patterns (Lead)

## İcmal

Distributed sistemlərdə xarici servis çağırışları uğursuz ola bilər: network timeout, servis yavaşlaması, müvəqqəti xəta. **Retry** — müvəqqəti xətaları düzəltmək üçün yenidən cəhd edir. **Circuit Breaker** — davamlı xətalarda sistemi qorumaq üçün çağırışları bloklayır.

Bu iki pattern bir-birini tamamlayır: Retry kiçik, müvəqqəti xətalar üçün, Circuit Breaker isə kütləvi uğursuzluğu (cascading failure) dayandırmaq üçündür.

Go-da geniş istifadə olunanlar:
- **sony/gobreaker** — Circuit Breaker
- **cenkalti/backoff** — Exponential backoff retry

## Niyə Vacibdir

- **Cascading failure:** Bir servis yavaşladığında yüzlərlə goroutine onu gözləyər, connection pool tükənər, əsas servis də çöküşə keçər
- **Retry storm:** Retry-lar düzgün yazılmamışsa, uğursuz servisi daha da yükləyər (thundering herd)
- **Fast fail:** Circuit Breaker açıqdısa, request database-ə çatmadan dərhal xəta qaytarır → latency azalır
- **Self-healing:** Half-open vəziyyətdə servis özünü bərpa edibsə, circuit təkrar qapanır

## Əsas Anlayışlar

### Retry

**Nə vaxt retry:**
- Network timeout (müvəqqəti)
- HTTP 500, 502, 503, 504
- DB connection error

**Nə vaxt retry etmə:**
- HTTP 400, 422 (client xətası — retry kömək etməz)
- HTTP 401, 403 (auth xətası)
- Business logic xətası

### Exponential Backoff

```
Cəhd 1: 100ms gözlə
Cəhd 2: 200ms gözlə
Cəhd 3: 400ms gözlə
Cəhd 4: 800ms gözlə
Max: cap (məs: 30s)
```

**Jitter** — eyni vaxtda çox client retry etsə thundering herd yaranır. Random vaxt əlavəsi bunu həll edir:

```
Backoff with full jitter: rand(0, min(cap, base * 2^attempt))
```

### Circuit Breaker Vəziyyətləri

```
                   uğursuzluq sayı threshold-u keçdi
    [CLOSED] ─────────────────────────────────────────► [OPEN]
       ▲                                                    │
       │                                                    │ cooldown müddəti keçdi
       │  probe request uğurlu oldu                        ▼
       └─────────────────────────────────────── [HALF-OPEN]
                                                    │
                                                    │ probe request uğursuz
                                                    ▼
                                                 [OPEN]
```

- **Closed** — normal vəziyyət, bütün request-lər keçir
- **Open** — bütün request-lər anında xəta ilə bloklanır (servisə çatmır)
- **Half-open** — bir neçə probe request buraxılır; uğurlu olsa Closed-a keçər

## Nümunələr

### Nümunə 1: Sadə exponential backoff retry

```go
package main

import (
    "context"
    "fmt"
    "math/rand"
    "time"
)

func retryWithBackoff(ctx context.Context, maxAttempts int, fn func() error) error {
    delay := 100 * time.Millisecond
    for attempt := 0; attempt < maxAttempts; attempt++ {
        if err := fn(); err == nil {
            return nil
        } else if attempt == maxAttempts-1 {
            return fmt.Errorf("max cəhd (%d) bitdi: %w", maxAttempts, err)
        }
        // Full jitter
        jitter := time.Duration(rand.Int63n(int64(delay)))
        select {
        case <-ctx.Done():
            return ctx.Err()
        case <-time.After(jitter):
        }
        delay = min(delay*2, 30*time.Second)
    }
    return nil
}
```

### Nümunə 2: Circuit breaker vəziyyət simulyasiyası

```go
package main

import (
    "errors"
    "fmt"
    "sync/atomic"
    "time"
)

type State int32

const (
    Closed   State = 0
    Open     State = 1
    HalfOpen State = 2
)

type SimpleBreaker struct {
    state       atomic.Int32
    failures    atomic.Int32
    threshold   int32
    openUntil   atomic.Int64
    cooldown    time.Duration
}

func NewBreaker(threshold int, cooldown time.Duration) *SimpleBreaker {
    return &SimpleBreaker{threshold: int32(threshold), cooldown: cooldown}
}

var ErrOpen = errors.New("circuit breaker açıqdır")

func (b *SimpleBreaker) Execute(fn func() error) error {
    switch State(b.state.Load()) {
    case Open:
        if time.Now().UnixNano() > b.openUntil.Load() {
            b.state.CompareAndSwap(int32(Open), int32(HalfOpen))
        } else {
            return ErrOpen
        }
    }

    err := fn()
    if err != nil {
        n := b.failures.Add(1)
        if n >= b.threshold {
            b.openUntil.Store(time.Now().Add(b.cooldown).UnixNano())
            b.state.Store(int32(Open))
            fmt.Println("circuit OPEN")
        }
        return err
    }

    b.failures.Store(0)
    b.state.Store(int32(Closed))
    return nil
}
```

## Praktik Baxış

### Sadə Retry (cenkalti/backoff)

```go
import "github.com/cenkalti/backoff/v4"

func callExternalAPI(ctx context.Context, url string) (*Response, error) {
    var result *Response

    operation := func() error {
        resp, err := http.Get(url)
        if err != nil {
            return err // retry
        }
        defer resp.Body.Close()

        if resp.StatusCode >= 500 {
            return fmt.Errorf("server error: %d", resp.StatusCode) // retry
        }
        if resp.StatusCode >= 400 {
            return backoff.Permanent(fmt.Errorf("client error: %d", resp.StatusCode)) // retry etmə
        }

        result = parseResponse(resp.Body)
        return nil
    }

    b := backoff.WithContext(
        backoff.WithMaxRetries(
            backoff.NewExponentialBackOff(),
            3,
        ),
        ctx,
    )

    if err := backoff.Retry(operation, b); err != nil {
        return nil, err
    }
    return result, nil
}
```

### Custom Retry — Jitter ilə

```go
type RetryConfig struct {
    MaxAttempts int
    BaseDelay   time.Duration
    MaxDelay    time.Duration
    Multiplier  float64
}

func WithRetry[T any](ctx context.Context, cfg RetryConfig, fn func() (T, error)) (T, error) {
    var zero T
    delay := cfg.BaseDelay

    for attempt := 0; attempt < cfg.MaxAttempts; attempt++ {
        result, err := fn()
        if err == nil {
            return result, nil
        }

        // Permanent error-ları retry etmə
        var permErr *PermanentError
        if errors.As(err, &permErr) {
            return zero, err
        }

        if attempt == cfg.MaxAttempts-1 {
            return zero, fmt.Errorf("max retries (%d) exhausted: %w", cfg.MaxAttempts, err)
        }

        // Full jitter
        jitter := time.Duration(rand.Int63n(int64(delay)))
        sleepDur := jitter
        if sleepDur > cfg.MaxDelay {
            sleepDur = cfg.MaxDelay
        }

        select {
        case <-ctx.Done():
            return zero, ctx.Err()
        case <-time.After(sleepDur):
        }

        delay = time.Duration(float64(delay) * cfg.Multiplier)
        if delay > cfg.MaxDelay {
            delay = cfg.MaxDelay
        }
    }
    return zero, errors.New("retry failed")
}
```

### Circuit Breaker (sony/gobreaker)

```go
import "github.com/sony/gobreaker/v2"

var cb *gobreaker.CircuitBreaker[*http.Response]

func init() {
    settings := gobreaker.Settings{
        Name:        "payment-service",
        MaxRequests: 3,    // Half-open-da max probe request
        Interval:    10 * time.Second, // Closed vəziyyətdə sayğacın sıfırlanma müddəti
        Timeout:     30 * time.Second, // Open-dan Half-open-a keçiş müddəti
        ReadyToTrip: func(counts gobreaker.Counts) bool {
            // 5 uğursuzluqdan sonra ve ya 60% failure rate-də açıl
            failureRatio := float64(counts.TotalFailures) / float64(counts.Requests)
            return counts.Requests >= 5 && failureRatio >= 0.6
        },
        OnStateChange: func(name string, from, to gobreaker.State) {
            slog.Warn("circuit breaker state change",
                "name", name,
                "from", from.String(),
                "to", to.String(),
            )
        },
    }
    cb = gobreaker.NewCircuitBreaker[*http.Response](settings)
}

func callPaymentService(ctx context.Context, req PaymentRequest) (*PaymentResponse, error) {
    resp, err := cb.Execute(func() (*http.Response, error) {
        return httpClient.Do(buildRequest(ctx, req))
    })

    if errors.Is(err, gobreaker.ErrOpenState) {
        // Circuit açıqdır — fallback yürüt
        return &PaymentResponse{Status: "pending", Retry: true}, nil
    }
    if errors.Is(err, gobreaker.ErrTooManyRequests) {
        // Half-open-da limit aşıldı
        return nil, ErrServiceUnavailable
    }

    return parsePaymentResponse(resp)
}
```

### Retry + Circuit Breaker Kombohası

```go
// Düzgün sıralama: Retry → Circuit Breaker → Timeout → Actual call
// Retry xarici qatdadır — circuit breaker xəta saydığı üçün retry-dan əvvəl olmalıdır

func robustCall(ctx context.Context, fn func() error) error {
    b := backoff.WithContext(
        backoff.WithMaxRetries(backoff.NewExponentialBackOff(), 3),
        ctx,
    )

    return backoff.Retry(func() error {
        _, err := cb.Execute(func() (struct{}, error) {
            return struct{}{}, fn()
        })

        // Circuit açıqdırsa retry etmə
        if errors.Is(err, gobreaker.ErrOpenState) {
            return backoff.Permanent(err)
        }

        return err
    }, b)
}
```

### Timeout ilə birlikdə

```go
func callWithTimeout(ctx context.Context, timeout time.Duration, fn func(context.Context) error) error {
    ctx, cancel := context.WithTimeout(ctx, timeout)
    defer cancel()
    return fn(ctx)
}

// İstifadəsi:
err := callWithTimeout(ctx, 5*time.Second, func(ctx context.Context) error {
    return robustCall(ctx, func() error {
        return paymentSvc.Process(ctx, req)
    })
})
```

### Fallback Pattern

```go
func getProductPrice(ctx context.Context, id int) (Price, error) {
    price, err := cb.Execute(func() (Price, error) {
        return pricingService.Get(ctx, id)
    })

    if err != nil {
        // Circuit açıqdır → cache-dən al
        if cached, ok := priceCache.Get(id); ok {
            slog.Warn("using cached price", "id", id)
            return cached, nil
        }
        // Cache-də yoxdur → static fallback
        return defaultPrice, nil
    }

    priceCache.Set(id, price, 5*time.Minute)
    return price, nil
}
```

### Monitoring

```go
// Prometheus metrics ilə circuit state izlə
func recordCircuitState(name string, state gobreaker.State) {
    circuitStateGauge.WithLabelValues(name, state.String()).Set(1)
}

// Settings-ə əlavə et:
settings.OnStateChange = func(name string, from, to gobreaker.State) {
    circuitStateChanges.WithLabelValues(name, from.String(), to.String()).Inc()
    recordCircuitState(name, to)
}
```

## Trade-off-lar

| | Retry | Circuit Breaker |
|--|-------|----------------|
| Müvəqqəti xətalara | Kömək edir | Fərqli məqsəd |
| Yük altında | Artırır (storm riski) | Qoruyur (fast fail) |
| Latency | Artırır (delay) | Azaldır (open state) |
| Complexity | Az | Orta |
| Nə zaman | Network jitter | Servis çökmüşdür |

**Anti-pattern:** Yüksək timeout + çox retry → əsas servisə uzun müddət yük. Qısa timeout + az retry + circuit breaker — düzgün kombinasiya.

## Praktik Tapşırıqlar

1. **Payment gateway:** 3 retry (500ms, 1s, 2s backoff) + circuit breaker (5 xəta → 30s open)
2. **State monitoring:** `OnStateChange` ilə log yaz, Prometheus counter artır
3. **Fallback:** Circuit açıqdıqda cache-dən qaytar, cache yoxdursa default dəyər
4. **Test:** httptest serveri yaz, 60% uğursuzluq qaytarsın, circuit-in açıldığını assert et
5. **Bulkhead:** Hər xarici servis üçün ayrı circuit breaker instance — bir servisin çöküşü digərini etkiləməsin

## PHP ilə Müqayisə

```
PHP                              Go
────────────────────────────────────────────────────────────────────
Guzzle retry middleware      →   backoff.Retry
predis/circuit-breaker        →   gobreaker (in-process)
Laravel Horizon retry()       →   asynq MaxRetry
sleep(2)                      →   time.Sleep / backoff delay
```

**Fərqlər:**
- PHP-də circuit breaker state-i Redis/Memcached-də saxlamaq lazımdır (stateless proseslər), Go-da in-process state mümkündür
- PHP-də retry genellikle queue job-larında — Go-da HTTP çağırışları da sync retry edir
- gobreaker thread-safe (goroutine-safe), PHP-də hər request ayrı prosesdə

## Əlaqəli Mövzular

- [../backend/02-http-client.md](../backend/02-http-client.md) — HTTP client, connection pool, timeout
- [../core/28-context.md](../core/28-context.md) — timeout və cancellation
- [51-rate-limiting.md](../backend/15-rate-limiting.md) — gülən sorğu sayını məhdudlaşdır
- [26-microservices.md](26-microservices.md) — service resilience patterns
- [71-monitoring-and-observability.md](24-monitoring-and-observability.md) — circuit state monitoring
- [79-singleflight.md](13-singleflight.md) — thundering herd üçün digər yanaşma
