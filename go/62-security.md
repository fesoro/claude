# Security — Go-da təhlükəsiz proqram yazma (Lead)

## İcmal

Təhlükəsizlik sonradan əlavə edilə bilməz — arxitekturadan başlamalıdır. Bu mövzuda Go-da ən tez-tez rast gəlinən zəifliklər və onlardan qorunma üsulları: SQL injection, XSS, CSRF, bcrypt, rate limiting, input validation, TLS, `crypto/rand`, HMAC, AES-GCM əhatə olunur.

## Niyə Vacibdir

- OWASP Top 10-un 90%-i düzgün biliklə qarşısı alına bilər
- Go runtime-ı buffer overflow-u qarşısını alır, amma business logic bug-ları qalır
- `govulncheck` ilə asılılıqlardakı CVE-ləri tapın — CI/CD-də məcburi
- Secret management pis işlənərsə, kodunuz açıq olsa belə bütün istifadəçilər risk altındadır

## Əsas Anlayışlar

### Kriptoqrafiya primitiv-ləri

| Məqsəd | Düzgün seçim | Yanlış seçim |
|--------|-------------|-------------|
| Şifrə hash | bcrypt, argon2 | MD5, SHA-1, SHA-256 |
| Məlumat şifrələmə | AES-GCM | AES-ECB, DES |
| Mesaj autentifikası | HMAC-SHA256 | sadə hash |
| Təsadüfi dəyər | `crypto/rand` | `math/rand` |
| Açar mübadiləsi | ECDH, X25519 | RSA < 2048 |

### SQL Injection

```go
// YANLIŞ — istifadəçi inputunu birbaşa birləşdir
query := "SELECT * FROM users WHERE name = '" + userName + "'"

// DOĞRU — parameterized query
db.QueryRowContext(ctx, "SELECT * FROM users WHERE name = $1", userName)
```

### XSS

```go
// YANLIŞ: text/template — escape etmir
import "text/template"
t.Execute(w, userInput) // XSS!

// DOĞRU: html/template — avtomatik escape edir
import "html/template"
t.Execute(w, userInput) // Təhlükəsiz
```

## Praktik Baxış

### Defence in depth

```
Layer 1: Input validation (giriş nöqtəsindən rədd et)
Layer 2: Parameterized queries (DB-də injection yoxdur)
Layer 3: Output encoding (XSS yoxdur)
Layer 4: Authentication (kim girə bilər)
Layer 5: Authorization (nə edə bilər)
Layer 6: Audit logging (nə etdi)
Layer 7: Rate limiting (brute force-u dayan)
```

### Trade-off-lar

- bcrypt cost: 10 (default) — ~100ms, server üçün qəbul edilə bilər; daha yüksək cost = daha yavaş login
- `crypto/rand` math/rand-dan yavaşdır, amma kriptoqrafik cəhətdən güvənlidir
- TLS 1.3 — bəzi köhnə clientlərlə uyğunsuzluq, amma əhəmiyyətli performans faydası

### Anti-pattern-lər

```go
// YANLIŞ: parolu kodda saxlamaq
const dbPassword = "mySecretPass123" // git historysında qalır

// YANLIŞ: zəif hash
h := md5.Sum([]byte(password)) // 1 saniyədə milyardlarla deneme

// YANLIŞ: timing attack
if token == expected { ... } // strings.Compare vaxtı fərqlənə bilər

// DOĞRU: constant-time müqayisə
hmac.Equal([]byte(token), []byte(expected)) // timing-safe
```

## Nümunələr

### Nümunə 1: Şifrə hashing — bcrypt + argon2

```go
package main

import (
    "fmt"

    "golang.org/x/crypto/bcrypt"
)

type PasswordHasher struct {
    cost int
}

func NewPasswordHasher(cost int) *PasswordHasher {
    if cost < bcrypt.MinCost {
        cost = bcrypt.DefaultCost
    }
    return &PasswordHasher{cost: cost}
}

func (h *PasswordHasher) Hash(password string) (string, error) {
    if len(password) < 8 {
        return "", fmt.Errorf("şifrə ən azı 8 simvol olmalıdır")
    }
    if len(password) > 72 {
        // bcrypt 72 byte-dan artığını kəsir — uzun şifrələri pre-hash et
        return "", fmt.Errorf("şifrə 72 simvoldan çox ola bilməz")
    }

    hash, err := bcrypt.GenerateFromPassword([]byte(password), h.cost)
    if err != nil {
        return "", fmt.Errorf("hash xətası: %w", err)
    }
    return string(hash), nil
}

func (h *PasswordHasher) Verify(password, hash string) bool {
    err := bcrypt.CompareHashAndPassword([]byte(hash), []byte(password))
    return err == nil
}

// NeedRehash — köhnə cost-la yaradılmış hash-i yenidən hashlə
func (h *PasswordHasher) NeedRehash(hash string) bool {
    cost, err := bcrypt.Cost([]byte(hash))
    if err != nil {
        return true
    }
    return cost < h.cost
}

func main() {
    hasher := NewPasswordHasher(bcrypt.DefaultCost)

    hash, err := hasher.Hash("myS3cureP@ss")
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Println("Hash:", hash[:30]+"...")

    fmt.Println("Doğrudur:", hasher.Verify("myS3cureP@ss", hash))
    fmt.Println("Yanlışdır:", hasher.Verify("wrongpass", hash))
    fmt.Println("Yenidən hash lazımdır:", hasher.NeedRehash(hash))
}
```

### Nümunə 2: AES-GCM şifrələmə servisi

```go
package main

import (
    "crypto/aes"
    "crypto/cipher"
    "crypto/rand"
    "encoding/base64"
    "fmt"
    "io"
)

// EncryptionService — authenticated encryption (AES-GCM)
type EncryptionService struct {
    key []byte // 32 byte = AES-256
}

func NewEncryptionService(key []byte) (*EncryptionService, error) {
    if len(key) != 32 {
        return nil, fmt.Errorf("açar 32 byte olmalıdır (AES-256)")
    }
    return &EncryptionService{key: key}, nil
}

func (s *EncryptionService) Encrypt(plaintext []byte) (string, error) {
    block, err := aes.NewCipher(s.key)
    if err != nil {
        return "", fmt.Errorf("cipher: %w", err)
    }

    gcm, err := cipher.NewGCM(block)
    if err != nil {
        return "", fmt.Errorf("GCM: %w", err)
    }

    // Hər şifrələmə üçün unikal nonce
    nonce := make([]byte, gcm.NonceSize())
    if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
        return "", fmt.Errorf("nonce: %w", err)
    }

    // Seal: nonce + ciphertext + authentication tag
    ciphertext := gcm.Seal(nonce, nonce, plaintext, nil)
    return base64.URLEncoding.EncodeToString(ciphertext), nil
}

func (s *EncryptionService) Decrypt(encoded string) ([]byte, error) {
    ciphertext, err := base64.URLEncoding.DecodeString(encoded)
    if err != nil {
        return nil, fmt.Errorf("base64: %w", err)
    }

    block, err := aes.NewCipher(s.key)
    if err != nil {
        return nil, fmt.Errorf("cipher: %w", err)
    }

    gcm, err := cipher.NewGCM(block)
    if err != nil {
        return nil, fmt.Errorf("GCM: %w", err)
    }

    if len(ciphertext) < gcm.NonceSize() {
        return nil, fmt.Errorf("məlumat çox qısadır")
    }

    nonce, ciphertext := ciphertext[:gcm.NonceSize()], ciphertext[gcm.NonceSize():]
    plaintext, err := gcm.Open(nil, nonce, ciphertext, nil)
    if err != nil {
        return nil, fmt.Errorf("deşifrələmə (məlumat dəyişdirilmişdir?): %w", err)
    }

    return plaintext, nil
}

// GenerateKey — kriptoqrafik cəhətdən təhlükəsiz açar
func GenerateKey() ([]byte, error) {
    key := make([]byte, 32)
    if _, err := rand.Read(key); err != nil {
        return nil, err
    }
    return key, nil
}

func main() {
    key, _ := GenerateKey()
    svc, _ := NewEncryptionService(key)

    // Məlumat şifrələ
    encrypted, err := svc.Encrypt([]byte(`{"card":"4111111111111111","cvv":"123"}`))
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Println("Şifrələnmiş:", encrypted[:30]+"...")

    // Deşifrələ
    decrypted, err := svc.Decrypt(encrypted)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Println("Deşifrələnmiş:", string(decrypted))

    // Tamper test
    _, err = svc.Decrypt(encrypted + "X")
    fmt.Println("Tamper aşkarlandı:", err != nil)
}
```

### Nümunə 3: CSRF token middleware

```go
package main

import (
    "crypto/rand"
    "encoding/base64"
    "fmt"
    "net/http"
    "sync"
    "time"
)

// CSRFStore — in-memory token store
type CSRFStore struct {
    mu     sync.Mutex
    tokens map[string]time.Time
    ttl    time.Duration
}

func NewCSRFStore(ttl time.Duration) *CSRFStore {
    s := &CSRFStore{
        tokens: make(map[string]time.Time),
        ttl:    ttl,
    }
    go s.cleanup()
    return s
}

func (s *CSRFStore) Generate() (string, error) {
    b := make([]byte, 32)
    if _, err := rand.Read(b); err != nil {
        return "", err
    }
    token := base64.URLEncoding.EncodeToString(b)

    s.mu.Lock()
    s.tokens[token] = time.Now().Add(s.ttl)
    s.mu.Unlock()

    return token, nil
}

func (s *CSRFStore) Validate(token string) bool {
    s.mu.Lock()
    defer s.mu.Unlock()

    exp, ok := s.tokens[token]
    if !ok {
        return false
    }
    delete(s.tokens, token) // bir dəfə istifadə
    return time.Now().Before(exp)
}

func (s *CSRFStore) cleanup() {
    ticker := time.NewTicker(time.Minute)
    for range ticker.C {
        now := time.Now()
        s.mu.Lock()
        for token, exp := range s.tokens {
            if now.After(exp) {
                delete(s.tokens, token)
            }
        }
        s.mu.Unlock()
    }
}

// CSRFMiddleware — POST/PUT/DELETE üçün token yoxlayır
func CSRFMiddleware(store *CSRFStore) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            // GET, HEAD, OPTIONS — CSRF tələb etmir
            if r.Method == http.MethodGet || r.Method == http.MethodHead || r.Method == http.MethodOptions {
                next.ServeHTTP(w, r)
                return
            }

            // Token header-dən və ya form-dan al
            token := r.Header.Get("X-CSRF-Token")
            if token == "" {
                token = r.FormValue("csrf_token")
            }

            if !store.Validate(token) {
                http.Error(w, "keçərsiz CSRF token", http.StatusForbidden)
                return
            }

            next.ServeHTTP(w, r)
        })
    }
}

func main() {
    store := NewCSRFStore(30 * time.Minute)

    mux := http.NewServeMux()

    // Form göstər
    mux.HandleFunc("/form", func(w http.ResponseWriter, r *http.Request) {
        token, err := store.Generate()
        if err != nil {
            http.Error(w, "token yaratma xətası", http.StatusInternalServerError)
            return
        }
        fmt.Fprintf(w, `<form method="POST" action="/submit">
            <input type="hidden" name="csrf_token" value="%s">
            <button type="submit">Göndər</button>
        </form>`, token)
    })

    // Form qəbul et
    mux.HandleFunc("/submit", func(w http.ResponseWriter, r *http.Request) {
        fmt.Fprintln(w, "Forma uğurla qəbul edildi!")
    })

    csrf := CSRFMiddleware(store)
    http.ListenAndServe(":8080", csrf(mux))
}
```

### Nümunə 4: Rate Limiter + Brute Force qorunması

```go
package main

import (
    "fmt"
    "net/http"
    "sync"
    "time"
)

// TokenBucket — token bucket rate limiter
type TokenBucket struct {
    mu       sync.Mutex
    tokens   float64
    maxToken float64
    refill   float64 // saniyəyə token
    lastTime time.Time
}

func NewTokenBucket(rate, burst float64) *TokenBucket {
    return &TokenBucket{
        tokens:   burst,
        maxToken: burst,
        refill:   rate,
        lastTime: time.Now(),
    }
}

func (b *TokenBucket) Allow() bool {
    b.mu.Lock()
    defer b.mu.Unlock()

    now := time.Now()
    elapsed := now.Sub(b.lastTime).Seconds()
    b.lastTime = now

    b.tokens = min(b.maxToken, b.tokens+elapsed*b.refill)

    if b.tokens >= 1 {
        b.tokens--
        return true
    }
    return false
}

func min(a, b float64) float64 {
    if a < b {
        return a
    }
    return b
}

// IPRateLimiter — hər IP üçün ayrıca limiter
type IPRateLimiter struct {
    mu       sync.RWMutex
    limiters map[string]*TokenBucket
    rate     float64
    burst    float64
}

func NewIPRateLimiter(rate, burst float64) *IPRateLimiter {
    limiter := &IPRateLimiter{
        limiters: make(map[string]*TokenBucket),
        rate:     rate,
        burst:    burst,
    }
    go limiter.cleanup()
    return limiter
}

func (l *IPRateLimiter) Allow(ip string) bool {
    l.mu.Lock()
    bucket, ok := l.limiters[ip]
    if !ok {
        bucket = NewTokenBucket(l.rate, l.burst)
        l.limiters[ip] = bucket
    }
    l.mu.Unlock()
    return bucket.Allow()
}

func (l *IPRateLimiter) cleanup() {
    // Köhnə entry-ləri periodiki sil (sadə implementasiya)
    ticker := time.NewTicker(10 * time.Minute)
    for range ticker.C {
        l.mu.Lock()
        l.limiters = make(map[string]*TokenBucket) // sadə reset
        l.mu.Unlock()
    }
}

func RateLimitMiddleware(limiter *IPRateLimiter) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            ip := r.RemoteAddr // production-da X-Forwarded-For da yoxla

            if !limiter.Allow(ip) {
                w.Header().Set("Retry-After", "1")
                http.Error(w, "çox sorğu", http.StatusTooManyRequests)
                return
            }

            next.ServeHTTP(w, r)
        })
    }
}

// LoginAttemptTracker — brute force qorunması
type LoginAttemptTracker struct {
    mu       sync.Mutex
    attempts map[string][]time.Time
    maxAttempts int
    window   time.Duration
}

func NewLoginAttemptTracker(maxAttempts int, window time.Duration) *LoginAttemptTracker {
    return &LoginAttemptTracker{
        attempts:    make(map[string][]time.Time),
        maxAttempts: maxAttempts,
        window:      window,
    }
}

func (t *LoginAttemptTracker) IsBlocked(identifier string) bool {
    t.mu.Lock()
    defer t.mu.Unlock()

    now := time.Now()
    attempts := t.attempts[identifier]

    // Köhnə cəhdləri sil
    var recent []time.Time
    for _, at := range attempts {
        if now.Sub(at) < t.window {
            recent = append(recent, at)
        }
    }
    t.attempts[identifier] = recent

    return len(recent) >= t.maxAttempts
}

func (t *LoginAttemptTracker) Record(identifier string) {
    t.mu.Lock()
    defer t.mu.Unlock()
    t.attempts[identifier] = append(t.attempts[identifier], time.Now())
}

func main() {
    // 10 sorğu/saniyə, burst 20
    limiter := NewIPRateLimiter(10, 20)
    tracker := NewLoginAttemptTracker(5, 15*time.Minute)

    mux := http.NewServeMux()

    mux.HandleFunc("/login", func(w http.ResponseWriter, r *http.Request) {
        if r.Method != http.MethodPost {
            http.Error(w, "yalnız POST", http.StatusMethodNotAllowed)
            return
        }

        email := r.FormValue("email")

        // Brute force yoxlaması
        if tracker.IsBlocked(email) {
            http.Error(w, "çox cəhd, 15 dəqiqə gözləyin", http.StatusTooManyRequests)
            return
        }

        // ... şifrə yoxlaması ...
        loginSuccess := false // simulyasiya

        if !loginSuccess {
            tracker.Record(email)
            http.Error(w, "yanlış giriş məlumatı", http.StatusUnauthorized)
            return
        }

        fmt.Fprintln(w, "Giriş uğurludur")
    })

    rl := RateLimitMiddleware(limiter)
    fmt.Println("Server başladı")
    http.ListenAndServe(":8080", rl(mux))
}
```

### Nümunə 5: Secure headers middleware

```go
package main

import "net/http"

// SecurityHeaders — OWASP tövsiyə edilən header-lər
func SecurityHeaders(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        h := w.Header()

        // Clickjacking qorunması
        h.Set("X-Frame-Options", "DENY")

        // XSS filter
        h.Set("X-XSS-Protection", "1; mode=block")

        // MIME sniffing qorunması
        h.Set("X-Content-Type-Options", "nosniff")

        // HSTS (yalnız HTTPS-də)
        h.Set("Strict-Transport-Security", "max-age=31536000; includeSubDomains")

        // Content Security Policy
        h.Set("Content-Security-Policy",
            "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'")

        // Referrer policy
        h.Set("Referrer-Policy", "strict-origin-when-cross-origin")

        // Server məlumatını gizlət
        h.Set("Server", "")

        next.ServeHTTP(w, r)
    })
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — govulncheck:**
Mövcud Go layihənizdə `govulncheck ./...` işlədin. Tapılan CVE-ləri araşdırın, düzəldin.

**Tapşırıq 2 — Secrets audit:**
`git log --all --full-history -- "*.env"` ilə git tarixçəsini yoxlayın. Heç bir şifrə/token commit edilməyib?

**Tapşırıq 3 — SQL injection test:**
`sqlmap -u "http://localhost:8080/user?id=1"` ilə öz API-nizi test edin. Parameterized query olmayan endpoint-ləri tapın.

**Tapşırıq 4 — Bcrypt benchmark:**
Cost 10, 12, 14 üçün `bcrypt.GenerateFromPassword` benchmark yazın. Hər cost nə qədər vaxt alır? Hansı cost layihəniz üçün qəbul edilə bilər?

**Tapşırıq 5 — Constant-time comparison:**
`strings.Compare`, `==`, `hmac.Equal` arasında timing fərqini `time.Now()` ilə ölçün. Niyə `hmac.Equal` vacibdir?

## PHP ilə Müqayisə

PHP/Laravel-dən fərqli olaraq Go-nun standart kitabxanası kriptoqrafiya üçün çox güclüdür — `crypto/*` paketlər professional kriptoqrafiya üçün yetərlidir, xarici kitabxana lazım deyil. `html/template` XSS-dən avtomatik qoruyur (Laravel Blade-in `{{ }}` sintaksisinin analoqu). CSRF üçün Laravel-dəki `VerifyCsrfToken` middleware özünüz yazılır — Go-da framework-dən gəlmir, bu daha çevik amma daha çox məsuliyyət tələb edir.

## Əlaqəli Mövzular

- [65-jwt-and-auth](65-jwt-and-auth.md) — JWT authentication
- [35-middleware-and-routing](35-middleware-and-routing.md) — middleware chain
- [33-http-server](33-http-server.md) — TLS server konfiqurasiyası
- [51-rate-limiting](51-rate-limiting.md) — rate limiting pattern-ləri
- [37-database](37-database.md) — parameterized queries, connection security
