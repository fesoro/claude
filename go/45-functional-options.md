# Functional Options Pattern (Senior)

## İcmal

Functional Options — Go-da konfiqurasiya üçün ən populyar pattern-dir. `WithTimeout(5*time.Second)`, `WithMaxRetries(3)` kimi funksiyalar vasitəsilə struct-ı konfiqurasiya etmək imkanı verir. `grpc-go`, `zap`, `gorm` kimi məşhur kitabxanalar bu pattern-i istifadə edir.

## Niyə Vacibdir

- Go-da constructor-a optional parametr vermək mümkün deyil
- Çoxlu constructor overload-lar mümkün deyil
- Yeni seçim əlavə etmək mövcud API-ni pozmur (backward compatible)
- Test zamanı yalnız lazım olan seçimləri override etmək asan olur
- `New(host, port, timeout, retries, logger, tls, ...)` kimi uzun signature-dan qaçmaq

## Əsas Anlayışlar

### Problem — Uzun Constructor

```go
// PROBLEM — çox parametrli constructor
func NewServer(host string, port int, timeout time.Duration,
    maxConns int, tls bool, logger *log.Logger) *Server {
    ...
}

// İstifadəsi: bütün parametrləri bilmək lazımdır
server := NewServer("localhost", 8080, 30*time.Second, 100, false, nil)
// Hansı nil-dir? timeout-mu? logger-mi? — oxumaq çətindir
```

### Config Struct Yanaşması (Aralıq Həll)

```go
type Config struct {
    Host    string
    Port    int
    Timeout time.Duration
}

func NewServer(cfg Config) *Server { ... }

// Daha yaxşı, amma default value-ları idarə etmək çətindir
server := NewServer(Config{Host: "localhost", Port: 8080})
```

### Functional Options — İdiomatic Həll

```go
type Option func(*Server)

func WithTimeout(d time.Duration) Option {
    return func(s *Server) {
        s.timeout = d
    }
}

func WithPort(port int) Option {
    return func(s *Server) {
        s.port = port
    }
}

func NewServer(host string, opts ...Option) *Server {
    s := &Server{
        host:    host,
        port:    8080,          // default
        timeout: 30 * time.Second, // default
    }
    for _, opt := range opts {
        opt(s)
    }
    return s
}

// İstifadə — oxunaqlı, seçici
server := NewServer("localhost",
    WithPort(9090),
    WithTimeout(60*time.Second),
)
```

### Validation ilə Options

Option-lar error qaytara bilər:

```go
type Option func(*Server) error

func WithPort(port int) Option {
    return func(s *Server) error {
        if port < 1 || port > 65535 {
            return fmt.Errorf("invalid port: %d", port)
        }
        s.port = port
        return nil
    }
}

func NewServer(host string, opts ...Option) (*Server, error) {
    s := &Server{host: host, port: 8080}
    for _, opt := range opts {
        if err := opt(s); err != nil {
            return nil, err
        }
    }
    return s, nil
}
```

## Praktik Baxış

### Real Layihələrdə İstifadə

**HTTP Client konfiqurasiyası:**
```go
client := NewHTTPClient(
    WithBaseURL("https://api.example.com"),
    WithTimeout(10*time.Second),
    WithMaxRetries(3),
    WithBearerToken(os.Getenv("API_TOKEN")),
)
```

**Database connection pool:**
```go
db := NewDB(dsn,
    WithMaxOpenConns(25),
    WithMaxIdleConns(5),
    WithConnMaxLifetime(5*time.Minute),
)
```

**Logger:**
```go
// zap kitabxanasından real nümunə
logger, _ := zap.NewProduction(
    zap.WithCaller(true),
    zap.Fields(zap.String("service", "payment")),
)
```

### Trade-off-lar

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| Functional Options | Backward compatible, oxunaqlı, default-lar asan | Hər option üçün funksiya yazılır |
| Config Struct | Sadə, type-safe | Yeni field əlavəsi API-ni sındıra bilər |
| Builder Pattern | Fluent API | Go idiomatik deyil, əlavə struct |
| Variadic Params | Sadə | Type-safe deyil |

### Builder Pattern ilə Müqayisə

```go
// Builder (Go-da az istifadə olunur)
server := NewServerBuilder().
    WithHost("localhost").
    WithPort(9090).
    Build()

// Functional Options (idiomatic Go)
server := NewServer("localhost", WithPort(9090))
```

Go cəmiyyəti functional options-ı üstün tutur çünki daha az kod, daha az type-lar.

### Anti-pattern-lər

```go
// Anti-pattern 1: Option-ların ardıcıllığı mühüm olmamalıdır
// Əgər WithTLS() ilk çağırılmasa xəta verəcəksə — design problemi var

// Anti-pattern 2: Çox mürəkkəb option-lar
func WithComplexSetup(a, b, c int, d string, e bool) Option {
    // Bu artıq functional option deyil, config struct lazımdır
}

// Anti-pattern 3: Option-ların state saxlaması
count := 0
WithCounter := func() Option {
    return func(s *Server) {
        count++ // closure-da state — bu race condition-a səbəb ola bilər
    }
}

// Anti-pattern 4: Nil option qəbul etməmək
// Bəzən nil-ları filter etmək faydalıdır
for _, opt := range opts {
    if opt != nil { // nil-safe
        opt(s)
    }
}
```

## Nümunələr

### Nümunə 1: HTTP Server — Tam İmplementasiya

```go
package main

import (
    "fmt"
    "log"
    "net/http"
    "time"
)

type Server struct {
    host         string
    port         int
    readTimeout  time.Duration
    writeTimeout time.Duration
    maxConns     int
    logger       *log.Logger
    tlsEnabled   bool
    certFile     string
    keyFile      string
}

type Option func(*Server)

func WithPort(port int) Option {
    return func(s *Server) { s.port = port }
}

func WithReadTimeout(d time.Duration) Option {
    return func(s *Server) { s.readTimeout = d }
}

func WithWriteTimeout(d time.Duration) Option {
    return func(s *Server) { s.writeTimeout = d }
}

func WithMaxConns(n int) Option {
    return func(s *Server) { s.maxConns = n }
}

func WithLogger(l *log.Logger) Option {
    return func(s *Server) { s.logger = l }
}

func WithTLS(certFile, keyFile string) Option {
    return func(s *Server) {
        s.tlsEnabled = true
        s.certFile = certFile
        s.keyFile = keyFile
    }
}

func NewServer(host string, opts ...Option) *Server {
    s := &Server{
        host:         host,
        port:         8080,
        readTimeout:  15 * time.Second,
        writeTimeout: 15 * time.Second,
        maxConns:     100,
        logger:       log.Default(),
    }
    for _, opt := range opts {
        opt(s)
    }
    return s
}

func (s *Server) Addr() string {
    return fmt.Sprintf("%s:%d", s.host, s.port)
}

func (s *Server) HTTPServer() *http.Server {
    return &http.Server{
        Addr:         s.Addr(),
        ReadTimeout:  s.readTimeout,
        WriteTimeout: s.writeTimeout,
    }
}

func main() {
    // Development
    devServer := NewServer("localhost",
        WithPort(9090),
        WithReadTimeout(5*time.Second),
    )
    fmt.Println("Dev server:", devServer.Addr())

    // Production
    prodServer := NewServer("0.0.0.0",
        WithPort(443),
        WithTLS("/etc/ssl/cert.pem", "/etc/ssl/key.pem"),
        WithMaxConns(1000),
        WithReadTimeout(30*time.Second),
        WithWriteTimeout(30*time.Second),
    )
    fmt.Println("Prod server:", prodServer.Addr())
    fmt.Println("TLS enabled:", prodServer.tlsEnabled)
}
```

### Nümunə 2: HTTP Client — Real Layihə Nümunəsi

```go
package main

import (
    "fmt"
    "net/http"
    "time"
)

type HTTPClient struct {
    baseURL    string
    timeout    time.Duration
    maxRetries int
    headers    map[string]string
    httpClient *http.Client
}

type ClientOption func(*HTTPClient)

func WithBaseURL(url string) ClientOption {
    return func(c *HTTPClient) { c.baseURL = url }
}

func WithTimeout(d time.Duration) ClientOption {
    return func(c *HTTPClient) { c.timeout = d }
}

func WithMaxRetries(n int) ClientOption {
    return func(c *HTTPClient) { c.maxRetries = n }
}

func WithHeader(key, value string) ClientOption {
    return func(c *HTTPClient) { c.headers[key] = value }
}

func WithBearerToken(token string) ClientOption {
    return WithHeader("Authorization", "Bearer "+token)
}

func NewHTTPClient(opts ...ClientOption) *HTTPClient {
    c := &HTTPClient{
        timeout:    30 * time.Second,
        maxRetries: 3,
        headers:    make(map[string]string),
    }
    c.headers["Content-Type"] = "application/json"
    c.headers["Accept"] = "application/json"

    for _, opt := range opts {
        opt(c)
    }

    c.httpClient = &http.Client{Timeout: c.timeout}
    return c
}

func (c *HTTPClient) Get(path string) (*http.Response, error) {
    req, err := http.NewRequest("GET", c.baseURL+path, nil)
    if err != nil {
        return nil, err
    }
    for k, v := range c.headers {
        req.Header.Set(k, v)
    }
    return c.httpClient.Do(req)
}

func main() {
    client := NewHTTPClient(
        WithBaseURL("https://api.example.com"),
        WithTimeout(10*time.Second),
        WithMaxRetries(5),
        WithBearerToken("my-secret-token"),
        WithHeader("X-Request-ID", "abc123"),
    )

    fmt.Println("Base URL:", client.baseURL)
    fmt.Println("Timeout:", client.timeout)
    fmt.Println("Max Retries:", client.maxRetries)
    fmt.Println("Auth:", client.headers["Authorization"])
}
```

### Nümunə 3: Validation ilə Error-returning Options

```go
package main

import (
    "errors"
    "fmt"
    "time"
)

type DBConfig struct {
    dsn             string
    maxOpenConns    int
    maxIdleConns    int
    connMaxLifetime time.Duration
    retryAttempts   int
}

type DBOption func(*DBConfig) error

func WithMaxOpenConns(n int) DBOption {
    return func(c *DBConfig) error {
        if n < 1 || n > 1000 {
            return fmt.Errorf("maxOpenConns must be between 1 and 1000, got %d", n)
        }
        c.maxOpenConns = n
        return nil
    }
}

func WithMaxIdleConns(n int) DBOption {
    return func(c *DBConfig) error {
        c.maxIdleConns = n
        return nil
    }
}

func WithConnMaxLifetime(d time.Duration) DBOption {
    return func(c *DBConfig) error {
        if d < time.Second {
            return errors.New("connMaxLifetime must be at least 1 second")
        }
        c.connMaxLifetime = d
        return nil
    }
}

func NewDBConfig(dsn string, opts ...DBOption) (*DBConfig, error) {
    cfg := &DBConfig{
        dsn:             dsn,
        maxOpenConns:    25,
        maxIdleConns:    5,
        connMaxLifetime: 5 * time.Minute,
    }

    for _, opt := range opts {
        if err := opt(cfg); err != nil {
            return nil, fmt.Errorf("db config error: %w", err)
        }
    }

    return cfg, nil
}

func main() {
    cfg, err := NewDBConfig("postgres://localhost/mydb",
        WithMaxOpenConns(50),
        WithMaxIdleConns(10),
        WithConnMaxLifetime(10*time.Minute),
    )
    if err != nil {
        fmt.Println("Config xətası:", err)
        return
    }
    fmt.Printf("DB Config: max=%d, idle=%d, lifetime=%v\n",
        cfg.maxOpenConns, cfg.maxIdleConns, cfg.connMaxLifetime)

    // Validation testi
    _, err = NewDBConfig("postgres://localhost/mydb",
        WithMaxOpenConns(9999), // limit aşılır
    )
    fmt.Println("Gözlənilən xəta:", err)
}
```

### Nümunə 4: Test Zamanı İstifadə

```go
package main

import (
    "fmt"
    "time"
)

// Production
func createProductionClient() *HTTPClient {
    return NewHTTPClient(
        WithBaseURL("https://api.production.com"),
        WithTimeout(30*time.Second),
        WithMaxRetries(3),
        WithBearerToken("prod-token"),
    )
}

// Test — yalnız fərqli olan hissəni override et
func createTestClient(baseURL string) *HTTPClient {
    return NewHTTPClient(
        WithBaseURL(baseURL), // test server URL-i
        WithTimeout(1*time.Second), // test-də daha qısa timeout
        // MaxRetries default (3) — dəyişmir
    )
}

func main() {
    prod := createProductionClient()
    fmt.Println("Prod:", prod.baseURL, prod.timeout)

    test := createTestClient("http://localhost:8081")
    fmt.Println("Test:", test.baseURL, test.timeout)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Logger konfiqurasiyası:**
`Logger` struct-ı yazın: `level`, `output io.Writer`, `prefix string`, `timestamps bool` field-ləri olsun. `WithLevel`, `WithOutput`, `WithPrefix`, `WithTimestamps` option-larını yazın.

**Tapşırıq 2 — Redis Client:**
`RedisClient` üçün functional options yazın: `WithAddr`, `WithDB`, `WithPassword`, `WithPoolSize`, `WithDialTimeout`. Validation əlavə edin (port range, pool size > 0).

**Tapşırıq 3 — Conditional Options:**
`WithTLSIf(enabled bool, cert, key string) Option` — şərtlə aktiv olan option yazın. `enabled` false olsa no-op etsin.

**Tapşırıq 4 — Options Composition:**
```go
func DevelopmentMode() Option { ... }  // bir neçə option birləşdirir
func ProductionMode() Option { ... }   // production defaults
```
Bu "preset" option-ları yazın.

**Tapşırıq 5 — Real Test:**
`NewHTTPClient` üçün test yazın. `httptest.NewServer` ilə fake server yaradın. Default timeout-ların düzgün işlədiyini yoxlayın.

## PHP ilə Müqayisə

PHP-də constructor-a default parametrlər vermək birbaşa mümkündür. Go-da bu mexanizm yoxdur — functional options bu boşluğu doldurmaq üçün istifadə olunur:

```php
// PHP — named parameters (8.0+) və ya constructor promotion
class Server {
    public function __construct(
        private string $host,
        private int $port = 8080,
        private int $timeout = 30,
        private ?Logger $logger = null,
    ) {}
}

$server = new Server(host: 'localhost', port: 9090);
// timeout default (30) qalır, logger null-dır
```

```go
// Go — functional options (PHP named params əvəzi)
server := NewServer("localhost",
    WithPort(9090),
    // timeout default olaraq qalır — 30s
)
```

**Fərq:** PHP-də default value birbaşa constructor signature-da verilir. Go-da default-lar struct initialization-da verilir, options yalnız dəyişiklik edir. Yeni parametr əlavəsi PHP-də constructor signature-ı dəyişdirir (breaking change ola bilər), Go-da yeni `WithXxx` funksiyası əlavə etmək API-ni pozmur.

## Əlaqəli Mövzular

- [07-functions](07-functions.md) — Funksiya əsasları, variadic parametrlər
- [17-interfaces](17-interfaces.md) — Interface pattern-ləri
- [42-struct-advanced](42-struct-advanced.md) — Struct-ların dərin analizi
- [43-pointers-advanced](43-pointers-advanced.md) — Pointer receiver
- [54-project-structure](54-project-structure.md) — Layihə strukturunda options pattern
- [55-repository-pattern](55-repository-pattern.md) — Options pattern repository-də
- [64-dependency-injection](64-dependency-injection.md) — DI ilə birlikdə istifadə
