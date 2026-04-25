# HTTP Client (Senior)

## İcmal

Go-da xarici API-lərə sorğu göndərmək üçün standart `net/http` paketinin `http.Client` tipi istifadə olunur. Default client-dən fərqli olaraq, production-da öz client-ini yaratmalısan: timeout, retry, connection pooling, custom transport — bunların hamısı `http.Client` və `http.Transport` vasitəsilə konfiqurasiya olunur.

PHP/Laravel-də `Http::get()` — Guzzle HTTP client-i sarmalayan bir facade idi. Go-da birbaşa standart kitabxana ilə işləyirsan, amma sən özün retry, timeout, error handling logic-i yazmalısan.

## Niyə Vacibdir

- Hər microservice xarici API-lərə sorğu göndərir — bu unavoidable-dır
- Default `http.Client` timeout-suzdur: server cavab vermirsə, goroutine sonsuz gözləyir
- Connection pooling düzgün konfiqurasiya edilməzsə, port exhaustion baş verir
- Retry + exponential backoff olmadan transient xətalar servisin aşmasına səbəb ola bilər
- Düzgün qurulan client-lər onlarla sorğu göndərərkən socket-ləri yenidən istifadə edir

## Əsas Anlayışlar

**`http.Client`** — sorğu göndərən əsas struct. `Timeout`, `Transport`, `CheckRedirect`, `Jar` sahələri var.

**`http.Transport`** — TCP bağlantılarını idarə edir: connection pool, TLS, proxy. `http.Client.Transport` sahəsinə set edilir.

**`http.Request`** — sorğunun tam təsviri: method, URL, header, body, context.

**`http.Response`** — cavab: status kodu, header-lər, body. Body mütləq bağlanmalıdır.

**`io.Reader` interface** — body həm request, həm response üçün `io.Reader` kimi gəlir. Bu stream-based-dir, yəni böyük body-ləri yaddaşa tam yükləmək şərt deyil.

**Idempotent sorğular** — `GET`, `HEAD`, `OPTIONS` idempotent-dir. `POST` isə deyil. Retry-da bu fərq vacibdir.

## Praktik Baxış

**Production checklist:**

1. Hər zaman öz `http.Client` instansiyasını yarat — global package-level dəyişkən kimi saxla
2. `Timeout` set et — minimum 5-10 saniyə, amma use-case-ə görə
3. `Body.Close()` — hər `defer resp.Body.Close()` — unutsan leak
4. Status kodu yoxla — `2xx` deyilsə error
5. Context ilə timeout idarə et — `context.WithTimeout` + `req.WithContext`
6. Retry yalnız idempotent sorğularda — POST-da retry data duplikasiyasına səbəb ola bilər

**Connection Pool (Transport) parametrləri:**

| Parametr | Default | Tövsiyə |
|---|---|---|
| `MaxIdleConns` | 100 | 100+ |
| `MaxIdleConnsPerHost` | 2 | 10-20 |
| `IdleConnTimeout` | 90s | 90s |
| `DisableKeepAlives` | false | false saxla |

**Anti-pattern-lər:**

- `http.Get()`, `http.Post()` default client-dən istifadə edir — timeout yox, production-da xətalıdır
- Hər sorğu üçün yeni `http.Client` yaratmaq — connection pool-u məhv edir
- `ioutil.ReadAll(resp.Body)` böyük cavablar üçün — yaddaşa tam yükləyir
- Timeout olmadan loop içindəki sorğular — goroutine leak
- Error-u ignore etmək: `resp, _ := client.Do(req)` — network xətaları gizlənir

**Trade-off-lar:**

- `http.Client` vs `resty`/`go-resty` — standart kitabxana dependency-siz, amma retry/logging əl ilə yazılır
- Manual retry vs circuit breaker (`sony/gobreaker`) — yüksək yüklü sistemlərdə circuit breaker daha yaxşıdır
- `context.WithTimeout` vs `client.Timeout` — context daha dəqiq idarə verir, amma ikisini birlikdə istifadə edərkən daha kiçik dəyər qazanır

## Nümunələr

### Nümunə 1: Production-Ready HTTP Client

```go
package main

import (
    "net/http"
    "time"
)

// Tək instansiya — package-level
var httpClient = &http.Client{
    Timeout: 15 * time.Second,
    Transport: &http.Transport{
        MaxIdleConns:        100,
        MaxIdleConnsPerHost: 10,
        IdleConnTimeout:     90 * time.Second,
        TLSHandshakeTimeout: 5 * time.Second,
        // DisableKeepAlives: false (default) — connection reuse aktiv
    },
}
```

### Nümunə 2: GET — JSON cavabı struct-a parse et

```go
package main

import (
    "context"
    "encoding/json"
    "fmt"
    "io"
    "net/http"
    "time"
)

type Post struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Body  string `json:"body"`
}

var client = &http.Client{Timeout: 10 * time.Second}

func getPost(ctx context.Context, id int) (*Post, error) {
    url := fmt.Sprintf("https://jsonplaceholder.typicode.com/posts/%d", id)

    req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
    if err != nil {
        return nil, fmt.Errorf("sorğu yaratma: %w", err)
    }
    req.Header.Set("Accept", "application/json")

    resp, err := client.Do(req)
    if err != nil {
        return nil, fmt.Errorf("sorğu göndərmə: %w", err)
    }
    defer resp.Body.Close()

    // Status kodu yoxla
    if resp.StatusCode != http.StatusOK {
        body, _ := io.ReadAll(resp.Body)
        return nil, fmt.Errorf("API xətası %d: %s", resp.StatusCode, body)
    }

    var post Post
    if err := json.NewDecoder(resp.Body).Decode(&post); err != nil {
        return nil, fmt.Errorf("JSON parse: %w", err)
    }
    return &post, nil
}

func main() {
    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()

    post, err := getPost(ctx, 1)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("Post: %+v\n", post)
}
```

### Nümunə 3: POST — JSON body ilə sorğu

```go
package main

import (
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "io"
    "net/http"
    "time"
)

type YeniPost struct {
    UserID int    `json:"userId"`
    Title  string `json:"title"`
    Body   string `json:"body"`
}

var client = &http.Client{Timeout: 10 * time.Second}

func createPost(ctx context.Context, post YeniPost) (*YeniPost, error) {
    jsonData, err := json.Marshal(post)
    if err != nil {
        return nil, fmt.Errorf("JSON marshal: %w", err)
    }

    req, err := http.NewRequestWithContext(
        ctx,
        http.MethodPost,
        "https://jsonplaceholder.typicode.com/posts",
        bytes.NewBuffer(jsonData),
    )
    if err != nil {
        return nil, err
    }
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("Authorization", "Bearer "+getToken())

    resp, err := client.Do(req)
    if err != nil {
        return nil, fmt.Errorf("sorğu: %w", err)
    }
    defer resp.Body.Close()

    if resp.StatusCode != http.StatusCreated {
        body, _ := io.ReadAll(resp.Body)
        return nil, fmt.Errorf("gözlənilməz status %d: %s", resp.StatusCode, body)
    }

    var result YeniPost
    json.NewDecoder(resp.Body).Decode(&result)
    return &result, nil
}

func getToken() string { return "my-api-token" }
```

### Nümunə 4: Retry — Exponential Backoff

```go
package main

import (
    "context"
    "fmt"
    "math"
    "net/http"
    "time"
)

func doWithRetry(ctx context.Context, client *http.Client, req *http.Request, maxAttempts int) (*http.Response, error) {
    var lastErr error

    for attempt := 0; attempt < maxAttempts; attempt++ {
        if attempt > 0 {
            // Exponential backoff: 1s, 2s, 4s, 8s...
            wait := time.Duration(math.Pow(2, float64(attempt-1))) * time.Second
            select {
            case <-time.After(wait):
            case <-ctx.Done():
                return nil, ctx.Err()
            }
        }

        // Request body bir dəfədən çox oxuna bilər: bytes.Buffer istifadə et
        resp, err := client.Do(req)
        if err != nil {
            lastErr = err
            continue // network xətası — retry et
        }

        // 5xx server xətaları — retry məntiqli
        // 4xx client xətaları — retry mənasızdır
        if resp.StatusCode >= 500 {
            resp.Body.Close()
            lastErr = fmt.Errorf("server xətası: %d", resp.StatusCode)
            continue
        }

        return resp, nil // uğurlu cavab
    }

    return nil, fmt.Errorf("%d cəhddən sonra uğursuz: %w", maxAttempts, lastErr)
}
```

### Nümunə 5: Query Parametrləri + Header-lər

```go
package main

import (
    "fmt"
    "net/http"
    "net/url"
    "time"
)

var client = &http.Client{Timeout: 10 * time.Second}

func searchUsers(query string, page, limit int) (*http.Response, error) {
    baseURL := "https://api.example.com/users"

    // URL-i query parametrlərlə düzgün yığ
    params := url.Values{}
    params.Set("q", query)
    params.Set("page", fmt.Sprintf("%d", page))
    params.Set("limit", fmt.Sprintf("%d", limit))

    fullURL := baseURL + "?" + params.Encode()
    // → https://api.example.com/users?limit=20&page=1&q=orkhan

    req, _ := http.NewRequest(http.MethodGet, fullURL, nil)

    // Müxtəlif header-lər
    req.Header.Set("Authorization", "Bearer "+getToken())
    req.Header.Set("Accept", "application/json")
    req.Header.Set("X-Request-ID", "uuid-burada")
    req.Header.Set("User-Agent", "MyApp/1.0")

    return client.Do(req)
}

func getToken() string { return "token" }
```

### Nümunə 6: Response Statusunu Düzgün Yoxlamaq

```go
// Yanlış — yalnız err yoxlamaq kifayət deyil
resp, err := client.Do(req)
if err != nil { /* ... */ }
// resp.StatusCode 404 ola bilər — bu da xətadır!

// Düzgün — status kodu da yoxla
func checkResponse(resp *http.Response) error {
    if resp.StatusCode >= 200 && resp.StatusCode < 300 {
        return nil
    }

    // Body-ni oxu — API-nin error mesajı ola bilər
    defer resp.Body.Close()
    body, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<20)) // max 1MB

    switch resp.StatusCode {
    case http.StatusUnauthorized:
        return fmt.Errorf("autentifikasiya xətası")
    case http.StatusForbidden:
        return fmt.Errorf("icazə yoxdur")
    case http.StatusNotFound:
        return fmt.Errorf("tapılmadı")
    case http.StatusTooManyRequests:
        return fmt.Errorf("rate limit aşıldı")
    default:
        return fmt.Errorf("API xətası %d: %s", resp.StatusCode, body)
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — API Client Wraper**

GitHub REST API üçün client yaz:
- `GetUser(username string) (*User, error)` — istifadəçi məlumatı
- `GetRepos(username string) ([]Repo, error)` — repo siyahısı
- Bütün sorğulara `Authorization: token` header-i əlavə et
- 404-ü xüsusi `ErrNotFound` tipinə çevir

**Tapşırıq 2 — Retry ilə Robust Client**

Aşağıdakıları implement et:
- `maxRetries = 3`, `baseDelay = 1s` exponential backoff
- 429 (`Too Many Requests`) aldıqda `Retry-After` header-ni oxu, o qədər gözlə
- 5xx-də retry, 4xx-də retry etmə
- `context.Context` dəstəyi — context cancel olunsa retry dayandır

**Tapşırıq 3 — Parallel Sorğular**

Bir siyahıdakı 10 URL-i eyni anda yüklə:
- `sync.WaitGroup` + goroutine
- Hər nəticəni channel-ə göndər
- Xəta olan URL-ləri log-a yaz, uğurluları say
- Eyni zamanda maksimum 3 sorğu — semaphore ilə

## Əlaqəli Mövzular

- [33-http-server](33-http-server.md) — HTTP server yaratmaq
- [28-context](28-context.md) — Context ilə timeout/cancellation
- [27-goroutines-and-channels](27-goroutines-and-channels.md) — Parallel HTTP sorğular
- [18-error-handling](18-error-handling.md) — HTTP xətalarını wrap etmək
- [20-json-encoding](20-json-encoding.md) — JSON marshal/unmarshal
- [36-httptest](36-httptest.md) — HTTP client-lərin test edilməsi
