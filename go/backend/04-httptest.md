# HTTP Handler Testing — net/http/httptest (Senior)

## İcmal

`net/http/httptest` paketi Go standart kitabxanasının bir hissəsidir. Həqiqi TCP port açmadan HTTP handler-ləri test etməyə imkan verir. İki əsas tipi var: `httptest.NewRecorder()` — handler-i yaddaşda icra edir; `httptest.NewServer()` — həqiqi TCP server başladır, xarici HTTP client-ləri test üçün istifadə olunur.

## Niyə Vacibdir

- HTTP handler-ləri real TCP bağlantısı olmadan test edilə bilər — sürətli, izolyasiyalı
- Middleware chain-ni test etmək üçün ideal — yalnız bir middleware-i test edə bilərsən
- External dependency-ləri (database, xarici API) `httptest.NewServer()` ilə mock edə bilərsən
- JSON API cavablarının status kodu, body, header-lərini dəqiq yoxlamaq mümkündür
- CI/CD-də real port açmadan paralel testlər işləyir

## Əsas Anlayışlar

**`httptest.NewRecorder()`**

`http.ResponseWriter`-i implement edən `*httptest.ResponseRecorder` qaytar. Handler-in yazdığı hər şeyi yaddaşda saxlayır:
- `rr.Code` — status kodu (default 200)
- `rr.Body` — `*bytes.Buffer` — yazılan body
- `rr.Header()` — set edilmiş header-lər
- `rr.Result()` — tam `*http.Response` obyekti

**`httptest.NewServer(handler)`**

Həqiqi HTTP server başladır. `ts.URL` — `http://127.0.0.1:<random_port>`. Test bitdikdə `ts.Close()` çağırılmalıdır.

`httptest.NewTLSServer(handler)` — HTTPS server.

**`http.NewRequest` vs `httptest.NewRequest`**

```go
// http.NewRequest — error qaytar bilir, production kodunda
r, err := http.NewRequest("GET", "/path", nil)

// httptest.NewRequest — panic edir əgər yanlış, test-lərdə daha az boilerplate
r := httptest.NewRequest("GET", "/path", nil)
```

**Unit test vs Integration test:**

- `NewRecorder()` → unit test: yalnız handler logic-i test edilir
- `NewServer()` → integration test: real HTTP client, middleware, TLS — hamısı iştirak edir

## Praktik Baxış

**Trade-off-lar:**

- `NewRecorder` sürətli, amma middleware chain-i bypass edə bilər əgər diqqət edilməzsə — handler-i test edirsən, bütün stack-i yox
- `NewServer` bütün stack-i test edir, amma test hər dəfə port açır — paralel testlərdə konflikt ola bilər (random port istifadəsi bunu həll edir)
- Table-driven testlər (`t.Run`) + `httptest` — Go-da ən yayılmış pattern

**Anti-pattern-lər:**

- `rr.Result()` istifadə etmək lazım olmayan hallarda `rr.Code` + `rr.Body` daha sadədir
- Body-ni `json.Unmarshal` etmədən string müqayisəsi — whitespace fərqliliyinə həssas
- `NewServer` test bitdikdə `Close()` çağırılmasa port açıq qalar — `defer ts.Close()` məcburidir
- Testdə global state istifadəsi — paralel testlərdə race condition

## Nümunələr

### Nümunə 1: Sadə Handler Unit Test

```go
package main

import (
    "encoding/json"
    "net/http"
    "net/http/httptest"
    "testing"
)

// Test ediləcək handler
func helloHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(http.StatusOK)
    json.NewEncoder(w).Encode(map[string]string{"mesaj": "Salam!"})
}

func TestHelloHandler(t *testing.T) {
    // 1. Request yarat
    req := httptest.NewRequest(http.MethodGet, "/", nil)

    // 2. ResponseRecorder yarat
    rr := httptest.NewRecorder()

    // 3. Handler-i çağır
    helloHandler(rr, req)

    // 4. Status kodu yoxla
    if rr.Code != http.StatusOK {
        t.Errorf("gözlənilən 200, alınan: %d", rr.Code)
    }

    // 5. Content-Type yoxla
    ct := rr.Header().Get("Content-Type")
    if ct != "application/json" {
        t.Errorf("yanlış Content-Type: %s", ct)
    }

    // 6. Body-ni parse et və yoxla
    var result map[string]string
    if err := json.Unmarshal(rr.Body.Bytes(), &result); err != nil {
        t.Fatalf("JSON parse xətası: %v", err)
    }
    if result["mesaj"] != "Salam!" {
        t.Errorf("gözlənilən 'Salam!', alınan: %s", result["mesaj"])
    }
}
```

### Nümunə 2: Table-Driven Test — JSON API Handler

```go
package main

import (
    "bytes"
    "encoding/json"
    "net/http"
    "net/http/httptest"
    "testing"
)

type User struct {
    ID    int    `json:"id"`
    Ad    string `json:"ad"`
    Email string `json:"email"`
}

var users = map[int]User{
    1: {ID: 1, Ad: "Orkhan", Email: "orkhan@test.az"},
    2: {ID: 2, Ad: "Eli", Email: "eli@test.az"},
}

func getUserHandler(w http.ResponseWriter, r *http.Request) {
    idStr := r.PathValue("id")
    var id int
    if _, err := fmt.Sscan(idStr, &id); err != nil {
        http.Error(w, `{"xeta":"yanlış ID"}`, http.StatusBadRequest)
        return
    }
    u, ok := users[id]
    if !ok {
        http.Error(w, `{"xeta":"tapılmadı"}`, http.StatusNotFound)
        return
    }
    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(u)
}

func TestGetUserHandler(t *testing.T) {
    tests := []struct {
        name       string
        id         string
        wantStatus int
        wantUser   *User
    }{
        {
            name:       "mövcud istifadəçi",
            id:         "1",
            wantStatus: http.StatusOK,
            wantUser:   &User{ID: 1, Ad: "Orkhan", Email: "orkhan@test.az"},
        },
        {
            name:       "tapılmayan istifadəçi",
            id:         "999",
            wantStatus: http.StatusNotFound,
            wantUser:   nil,
        },
        {
            name:       "yanlış ID formatı",
            id:         "abc",
            wantStatus: http.StatusBadRequest,
            wantUser:   nil,
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            req := httptest.NewRequest(http.MethodGet, "/users/"+tt.id, nil)

            // Go 1.22+ path parametrini test-də set etmək
            req.SetPathValue("id", tt.id)

            rr := httptest.NewRecorder()
            getUserHandler(rr, req)

            if rr.Code != tt.wantStatus {
                t.Errorf("status: istənilən %d, alınan %d", tt.wantStatus, rr.Code)
            }

            if tt.wantUser != nil {
                var got User
                if err := json.Unmarshal(rr.Body.Bytes(), &got); err != nil {
                    t.Fatalf("JSON parse: %v", err)
                }
                if got.Ad != tt.wantUser.Ad {
                    t.Errorf("ad: istənilən %s, alınan %s", tt.wantUser.Ad, got.Ad)
                }
            }
        })
    }
}
```

### Nümunə 3: POST Handler — Request Body ilə Test

```go
package main

import (
    "bytes"
    "encoding/json"
    "net/http"
    "net/http/httptest"
    "testing"
)

type CreateUserRequest struct {
    Ad    string `json:"ad"`
    Email string `json:"email"`
}

func createUserHandler(w http.ResponseWriter, r *http.Request) {
    var req CreateUserRequest
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        http.Error(w, `{"xeta":"yanlış JSON"}`, http.StatusBadRequest)
        return
    }
    if req.Ad == "" || req.Email == "" {
        http.Error(w, `{"xeta":"ad və email məcburidir"}`, http.StatusBadRequest)
        return
    }
    newUser := User{ID: 100, Ad: req.Ad, Email: req.Email}
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(http.StatusCreated)
    json.NewEncoder(w).Encode(newUser)
}

func TestCreateUserHandler(t *testing.T) {
    body := CreateUserRequest{Ad: "Veli", Email: "veli@test.az"}
    bodyBytes, _ := json.Marshal(body)

    req := httptest.NewRequest(http.MethodPost, "/users", bytes.NewReader(bodyBytes))
    req.Header.Set("Content-Type", "application/json")

    rr := httptest.NewRecorder()
    createUserHandler(rr, req)

    if rr.Code != http.StatusCreated {
        t.Errorf("gözlənilən 201, alınan: %d\nbody: %s", rr.Code, rr.Body.String())
    }

    var created User
    json.Unmarshal(rr.Body.Bytes(), &created)
    if created.Ad != body.Ad {
        t.Errorf("ad uyğun deyil: %s != %s", created.Ad, body.Ad)
    }
}

// Boş body ilə test
func TestCreateUserHandler_EmptyBody(t *testing.T) {
    req := httptest.NewRequest(http.MethodPost, "/users", bytes.NewReader([]byte("{}")))
    req.Header.Set("Content-Type", "application/json")
    rr := httptest.NewRecorder()
    createUserHandler(rr, req)

    if rr.Code != http.StatusBadRequest {
        t.Errorf("gözlənilən 400, alınan: %d", rr.Code)
    }
}
```

### Nümunə 4: Middleware Test

```go
package main

import (
    "net/http"
    "net/http/httptest"
    "testing"
)

// Test ediləcək auth middleware
func authMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        token := r.Header.Get("Authorization")
        if token != "Bearer valid-token" {
            http.Error(w, "Unauthorized", http.StatusUnauthorized)
            return
        }
        next.ServeHTTP(w, r)
    })
}

func TestAuthMiddleware(t *testing.T) {
    // Test ediləcək handler — yalnız auth keçsə çağırılır
    protected := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.WriteHeader(http.StatusOK)
        w.Write([]byte("protected content"))
    })

    // Middleware ilə sarmal
    handler := authMiddleware(protected)

    t.Run("token olmadan", func(t *testing.T) {
        req := httptest.NewRequest(http.MethodGet, "/protected", nil)
        rr := httptest.NewRecorder()
        handler.ServeHTTP(rr, req)

        if rr.Code != http.StatusUnauthorized {
            t.Errorf("gözlənilən 401, alınan: %d", rr.Code)
        }
    })

    t.Run("yanlış token", func(t *testing.T) {
        req := httptest.NewRequest(http.MethodGet, "/protected", nil)
        req.Header.Set("Authorization", "Bearer wrong-token")
        rr := httptest.NewRecorder()
        handler.ServeHTTP(rr, req)

        if rr.Code != http.StatusUnauthorized {
            t.Errorf("gözlənilən 401, alınan: %d", rr.Code)
        }
    })

    t.Run("düzgün token", func(t *testing.T) {
        req := httptest.NewRequest(http.MethodGet, "/protected", nil)
        req.Header.Set("Authorization", "Bearer valid-token")
        rr := httptest.NewRecorder()
        handler.ServeHTTP(rr, req)

        if rr.Code != http.StatusOK {
            t.Errorf("gözlənilən 200, alınan: %d", rr.Code)
        }
    })
}
```

### Nümunə 5: httptest.NewServer — Xarici API Mock

```go
package main

import (
    "encoding/json"
    "net/http"
    "net/http/httptest"
    "testing"
    "time"
)

// Test ediləcək funksiya — xarici API-yə sorğu göndərir
func fetchUserFromAPI(baseURL string, id int) (*User, error) {
    client := &http.Client{Timeout: 5 * time.Second}
    resp, err := client.Get(fmt.Sprintf("%s/users/%d", baseURL, id))
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()

    if resp.StatusCode != http.StatusOK {
        return nil, fmt.Errorf("API xətası: %d", resp.StatusCode)
    }
    var u User
    json.NewDecoder(resp.Body).Decode(&u)
    return &u, nil
}

func TestFetchUserFromAPI(t *testing.T) {
    // Fake API server yarat
    ts := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        // Gələn sorğunun path-ini yoxla
        if r.URL.Path == "/users/1" {
            w.Header().Set("Content-Type", "application/json")
            json.NewEncoder(w).Encode(User{ID: 1, Ad: "Test User", Email: "test@test.az"})
            return
        }
        http.NotFound(w, r)
    }))
    defer ts.Close() // Test bitdikdə server bağla

    // ts.URL = "http://127.0.0.1:<random>"
    user, err := fetchUserFromAPI(ts.URL, 1)
    if err != nil {
        t.Fatalf("xəta: %v", err)
    }
    if user.Ad != "Test User" {
        t.Errorf("gözlənilən 'Test User', alınan: %s", user.Ad)
    }

    // 404 hal
    _, err = fetchUserFromAPI(ts.URL, 999)
    if err == nil {
        t.Error("xəta gözlənilirdi, amma nil alındı")
    }
}
```

### Nümunə 6: Tam Handler Stack — Middleware Chain Test

```go
package main

import (
    "net/http"
    "net/http/httptest"
    "testing"
)

// Bütün middleware stack-i test et
func TestFullStack(t *testing.T) {
    // Mux qur
    mux := http.NewServeMux()
    mux.HandleFunc("GET /api/users", func(w http.ResponseWriter, r *http.Request) {
        // Request ID middleware-dən gəlməlidir
        requestID := r.Header.Get("X-Request-ID")
        w.Header().Set("X-Request-ID", requestID) // echo back
        w.WriteHeader(http.StatusOK)
    })

    // Middleware-ləri əlavə et
    handler := Chain(mux, Recovery, RequestIDMiddleware, Logger)

    // Test server yarat
    ts := httptest.NewServer(handler)
    defer ts.Close()

    resp, err := http.Get(ts.URL + "/api/users")
    if err != nil {
        t.Fatal(err)
    }
    defer resp.Body.Close()

    if resp.StatusCode != http.StatusOK {
        t.Errorf("gözlənilən 200, alınan: %d", resp.StatusCode)
    }

    // Request ID response header-ə keçməlidirsə yoxla
    if resp.Header.Get("X-Request-ID") == "" {
        t.Error("X-Request-ID header-i yoxdur")
    }
}

// Helpers — bu testdə sadəcə interface üçün
func Chain(h http.Handler, mw ...func(http.Handler) http.Handler) http.Handler {
    for i := len(mw) - 1; i >= 0; i-- { h = mw[i](h) }
    return h
}
func Recovery(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { next.ServeHTTP(w, r) })
}
func RequestIDMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        r.Header.Set("X-Request-ID", "test-req-id")
        next.ServeHTTP(w, r)
    })
}
func Logger(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { next.ServeHTTP(w, r) })
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — CRUD Handler Testləri**

`/books` REST API üçün tam test suite yaz:
- `GET /books` → 200 + JSON array
- `GET /books/{id}` → 200 yoxsa 404
- `POST /books` → 201 + yeni kitab / 400 validation xətası
- `DELETE /books/{id}` → 204 yoxsa 404
- Hər test üçün `t.Run` istifadə et

**Tapşırıq 2 — Xarici API Mock**

Üçüncü tərəf ödəniş API-si çağıran handler var. `httptest.NewServer` ilə mock yaz:
- Uğurlu ödəniş cavabı simülyasiyası
- Timeout simülyasiyası (handler 100ms gecikdirsin, client timeout 50ms)
- 500 xəta cavabı

**Tapşırıq 3 — Benchmark**

```go
func BenchmarkHelloHandler(b *testing.B) {
    req := httptest.NewRequest("GET", "/", nil)
    for b.Loop() {
        rr := httptest.NewRecorder()
        helloHandler(rr, req)
    }
}
```

`go test -bench=. -benchmem` ilə işlət, nəticəni şərh et.

**Tapşırıq 4 — Race Detector**

`go test -race ./...` ilə testləri işlət. Handler-in global state istifadə etdiyini fərz et — race-i tap və düzəlt.

## PHP ilə Müqayisə

PHP/Laravel-də handler testləri `$this->getJson('/api/users')` şəklindəydi — Laravel özü request/response simulyasiya edirdi. Go-da isə `httptest.NewRecorder()` ilə `http.ResponseWriter`-i simulyasiya edirsən, `http.Request`-i əl ilə qurursan — daha açıq, amma daha çox boilerplate.

| Laravel | Go httptest |
|---|---|
| `$this->getJson('/users')` | `req := httptest.NewRequest("GET","/users",nil)` + `rr := httptest.NewRecorder()` + `handler(rr, req)` |
| `$response->assertStatus(200)` | `assert.Equal(t, 200, rr.Code)` |
| `$response->assertJson([...])` | `json.Unmarshal(rr.Body.Bytes(), &result)` |
| `Http::fake()` | `httptest.NewServer(...)` |

## Əlaqəli Mövzular

- [33-http-server](33-http-server.md) — HTTP handler yazma əsasları
- [35-middleware-and-routing](35-middleware-and-routing.md) — Middleware pattern-ları
- [24-testing](24-testing.md) — Go-da testing fundamentals
- [52-mocking-and-testify](52-mocking-and-testify.md) — Testify + mock-lar
- [34-http-client](34-http-client.md) — HTTP client test etmək
- [28-context](28-context.md) — Context ilə test timeout-ları
