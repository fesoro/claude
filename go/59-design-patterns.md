# Design Patterns — Go-ya xas idiomatic pattern-lər (Lead)

## İcmal

Go dizayn pattern-ləri PHP/Java-dan fərqlidir. Go-da miras yoxdur, interface implicit implement olunur, struct embedding komposisiya yaradır. Bu fərqlər klassik GoF pattern-lərinin bir hissəsini lazımsız edir, digər hissəsini isə köklü şəkildə dəyişdirir.

Bu mövzuda Go-ya xas pattern-lər: Functional Options, Singleton (sync.Once ilə), Factory (interface ilə), Observer (channel ilə), Middleware chain, Repository, Service Layer öyrəniləcək. PHP-dəki `__construct` injection ilə Go-nun constructor funksiyaları arasındakı oxşarlıqları da müzakirə edəcəyik.

## Niyə Vacibdir

- Go-da həddən artıq mürəkkəb pattern tətbiqi — **over-engineering** sayılır
- Interface minimal olmalıdır — bir metod kifayətdir (`io.Reader`, `error` interfeysi)
- Kodun testability-si pattern seçimini müəyyənləşdirir
- Functional Options — Laravel `config()` array-indən daha güclü, type-safe alternativ
- Middleware pattern — Go HTTP stack-inin özəyidir

## Əsas Anlayışlar

### PHP vs Go — pattern fərqləri

| Pattern | PHP yanaşması | Go yanaşması |
|---------|--------------|--------------|
| Singleton | `static $instance` | `sync.Once` |
| Factory | `abstract class`, `if/switch` | `interface` + switch |
| Dependency Injection | Constructor injection, container | Constructor function + interface |
| Observer | Interface implement | function type / channel |
| Builder | Fluent interface chain | Functional Options |
| Decorator | Class extend/implement | Function wrapping |

### Interface implicit implementation

```go
// PHP-də explicit:
// class Dog implements Animal { ... }

// Go-da implicit:
type Animal interface {
    Sound() string
}

type Dog struct{}
func (d Dog) Sound() string { return "Hav" }
// Dog avtomatik Animal-dır — heç bir bəyanat lazım deyil
```

### Komposisiya vs miras

```go
// PHP-də miras:
// class AdminUser extends User { }

// Go-da embedding (komposisiya):
type User struct { Name string }
type AdminUser struct {
    User        // User-in bütün metodları AdminUser-də mövcuddur
    Permissions []string
}
```

## Praktik Baxış

### Nə vaxt hansı pattern?

```
Çox parametrli constructor  → Functional Options
Bir dəfə yaranmalı          → sync.Once Singleton
Bir neçə implementasiya     → Factory + Interface
Aralıq funksionallıq        → Middleware
Hadisə yayımı               → Observer (function type)
```

### Trade-off-lar

- **Functional Options**: API-ni genişlətmək asandır, amma option-ların sırasının əhəmiyyəti olduqda mürəkkəbləşir
- **Interface qranularlığı**: kiçik interface daha çevik, amma çox kiçik olduqda anlaşılmazlıq yaranır
- **Singleton**: test zamanı state paylaşılır → parallel testlər çirklənir; `t.Cleanup()` ilə sıfırlamaq lazımdır

### Anti-pattern-lər

```go
// YANLIŞ: boş interface parametr (type-safety itirilir)
func Process(data interface{}) { ... }

// DOĞRU: generics və ya konkret tip
func Process[T Stringer](data T) { ... }

// YANLIŞ: God struct — hər şey bir yerdə
type App struct {
    DB      *sql.DB
    Redis   *redis.Client
    Logger  *log.Logger
    Config  *Config
    // ...50 field
}

// DOĞRU: ayrı servis-lər, dependency injection
type UserService struct { repo UserRepo; log Logger }
type OrderService struct { repo OrderRepo; events EventBus }
```

## Nümunələr

### Nümunə 1: Functional Options — production-grade server konfigurasyonu

```go
package main

import (
    "crypto/tls"
    "fmt"
    "net/http"
    "time"
)

type Server struct {
    host         string
    port         int
    readTimeout  time.Duration
    writeTimeout time.Duration
    maxConns     int
    tlsConfig    *tls.Config
    middleware   []func(http.Handler) http.Handler
}

type Option func(*Server) error

// WithHost — host konfiqurasiyası ilə validasiya
func WithHost(host string) Option {
    return func(s *Server) error {
        if host == "" {
            return fmt.Errorf("host boş ola bilməz")
        }
        s.host = host
        return nil
    }
}

func WithPort(port int) Option {
    return func(s *Server) error {
        if port < 1 || port > 65535 {
            return fmt.Errorf("yanlış port: %d", port)
        }
        s.port = port
        return nil
    }
}

func WithTimeout(read, write time.Duration) Option {
    return func(s *Server) error {
        s.readTimeout = read
        s.writeTimeout = write
        return nil
    }
}

func WithTLS(certFile, keyFile string) Option {
    return func(s *Server) error {
        cert, err := tls.LoadX509KeyPair(certFile, keyFile)
        if err != nil {
            return fmt.Errorf("TLS sertifikat: %w", err)
        }
        s.tlsConfig = &tls.Config{
            Certificates: []tls.Certificate{cert},
            MinVersion:   tls.VersionTLS13,
        }
        return nil
    }
}

func WithMiddleware(mw ...func(http.Handler) http.Handler) Option {
    return func(s *Server) error {
        s.middleware = append(s.middleware, mw...)
        return nil
    }
}

// NewServer — default dəyərlər + validation
func NewServer(opts ...Option) (*Server, error) {
    s := &Server{
        host:         "0.0.0.0",
        port:         8080,
        readTimeout:  15 * time.Second,
        writeTimeout: 15 * time.Second,
        maxConns:     1000,
    }
    for _, opt := range opts {
        if err := opt(s); err != nil {
            return nil, fmt.Errorf("server konfiqurasiyası: %w", err)
        }
    }
    return s, nil
}

func (s *Server) Addr() string {
    return fmt.Sprintf("%s:%d", s.host, s.port)
}

func main() {
    s, err := NewServer(
        WithHost("0.0.0.0"),
        WithPort(9090),
        WithTimeout(30*time.Second, 30*time.Second),
    )
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Println("Server:", s.Addr())

    // Yanlış konfiqurasiya — erkən xəta
    _, err = NewServer(WithPort(99999))
    if err != nil {
        fmt.Println("Gözlənilən xəta:", err)
    }
}
```

### Nümunə 2: Factory pattern — notification sistemi

```go
package main

import (
    "fmt"
    "strings"
)

// Notification interface — minimal, bir metod
type Notifier interface {
    Send(to, subject, body string) error
}

// EmailNotifier
type EmailNotifier struct {
    SMTPHost string
    SMTPPort int
    From     string
}

func (e *EmailNotifier) Send(to, subject, body string) error {
    fmt.Printf("[EMAIL] %s → %s: %s\n", e.From, to, subject)
    return nil
}

// SMSNotifier
type SMSNotifier struct {
    APIKey  string
    Sender  string
}

func (s *SMSNotifier) Send(to, subject, body string) error {
    fmt.Printf("[SMS] %s → %s\n", s.Sender, to)
    return nil
}

// SlackNotifier
type SlackNotifier struct {
    WebhookURL string
    Channel    string
}

func (s *SlackNotifier) Send(to, subject, body string) error {
    fmt.Printf("[SLACK] #%s: %s\n", s.Channel, subject)
    return nil
}

// MultiNotifier — bir neçə channel eyni anda
type MultiNotifier struct {
    notifiers []Notifier
}

func (m *MultiNotifier) Send(to, subject, body string) error {
    var errs []string
    for _, n := range m.notifiers {
        if err := n.Send(to, subject, body); err != nil {
            errs = append(errs, err.Error())
        }
    }
    if len(errs) > 0 {
        return fmt.Errorf("bəzi bildirişlər göndərilmədi: %s", strings.Join(errs, "; "))
    }
    return nil
}

// Config-dən factory
type NotifierConfig struct {
    Type       string
    SMTPHost   string
    SMTPPort   int
    APIKey     string
    WebhookURL string
    Channel    string
}

func NewNotifier(cfg NotifierConfig) (Notifier, error) {
    switch cfg.Type {
    case "email":
        return &EmailNotifier{SMTPHost: cfg.SMTPHost, SMTPPort: cfg.SMTPPort}, nil
    case "sms":
        return &SMSNotifier{APIKey: cfg.APIKey}, nil
    case "slack":
        return &SlackNotifier{WebhookURL: cfg.WebhookURL, Channel: cfg.Channel}, nil
    default:
        return nil, fmt.Errorf("bilinməyən notifier tipi: %s", cfg.Type)
    }
}

func main() {
    // Factory istifadəsi
    email, _ := NewNotifier(NotifierConfig{Type: "email", SMTPHost: "smtp.gmail.com", SMTPPort: 587})
    slack, _ := NewNotifier(NotifierConfig{Type: "slack", Channel: "alerts"})

    // Multi-notifier
    multi := &MultiNotifier{notifiers: []Notifier{email, slack}}
    multi.Send("admin@company.com", "Server xətası", "DB əlaqəsi kəsildi")

    // Bilinməyən tip — xəta
    _, err := NewNotifier(NotifierConfig{Type: "telegram"})
    fmt.Println("Xəta:", err)
}
```

### Nümunə 3: Observer — channel ilə event bus

```go
package main

import (
    "fmt"
    "sync"
)

type Event struct {
    Topic string
    Data  interface{}
}

type EventBus struct {
    mu          sync.RWMutex
    subscribers map[string][]chan Event
}

func NewEventBus() *EventBus {
    return &EventBus{
        subscribers: make(map[string][]chan Event),
    }
}

// Subscribe — mövzu üçün kanal qaytarır
func (eb *EventBus) Subscribe(topic string, bufSize int) <-chan Event {
    ch := make(chan Event, bufSize)
    eb.mu.Lock()
    eb.subscribers[topic] = append(eb.subscribers[topic], ch)
    eb.mu.Unlock()
    return ch
}

// Unsubscribe — kanalı sil
func (eb *EventBus) Unsubscribe(topic string, ch <-chan Event) {
    eb.mu.Lock()
    defer eb.mu.Unlock()
    subs := eb.subscribers[topic]
    for i, sub := range subs {
        if sub == ch {
            eb.subscribers[topic] = append(subs[:i], subs[i+1:]...)
            close(sub)
            return
        }
    }
}

// Publish — mövzuya abunəçilərə göndər
func (eb *EventBus) Publish(topic string, data interface{}) {
    event := Event{Topic: topic, Data: data}
    eb.mu.RLock()
    defer eb.mu.RUnlock()
    for _, ch := range eb.subscribers[topic] {
        select {
        case ch <- event:
        default:
            // Kanal dolu — göndərmə (drop)
        }
    }
}

type OrderEvent struct {
    OrderID int
    Amount  float64
    UserID  int
}

func main() {
    bus := NewEventBus()

    // Abunəçilər
    emailCh := bus.Subscribe("order.created", 10)
    inventoryCh := bus.Subscribe("order.created", 10)
    analyticsCh := bus.Subscribe("order.created", 10)

    var wg sync.WaitGroup

    // Email handler
    wg.Add(1)
    go func() {
        defer wg.Done()
        for e := range emailCh {
            order := e.Data.(OrderEvent)
            fmt.Printf("[Email] Sifariş #%d üçün email göndərildi\n", order.OrderID)
        }
    }()

    // Inventory handler
    wg.Add(1)
    go func() {
        defer wg.Done()
        for e := range inventoryCh {
            order := e.Data.(OrderEvent)
            fmt.Printf("[Inventory] Sifariş #%d üçün ehtiyat azaldıldı\n", order.OrderID)
        }
    }()

    // Analytics handler
    wg.Add(1)
    go func() {
        defer wg.Done()
        for e := range analyticsCh {
            order := e.Data.(OrderEvent)
            fmt.Printf("[Analytics] Sifariş #%d — $%.2f\n", order.OrderID, order.Amount)
        }
    }()

    // Hadisə yayımı
    for i := 1; i <= 3; i++ {
        bus.Publish("order.created", OrderEvent{
            OrderID: 1000 + i,
            Amount:  float64(i * 50),
            UserID:  i,
        })
    }

    // Abunəliyi ləğv et
    bus.Unsubscribe("order.created", emailCh)
    bus.Unsubscribe("order.created", inventoryCh)
    bus.Unsubscribe("order.created", analyticsCh)

    wg.Wait()
}
```

### Nümunə 4: Middleware chain

```go
package main

import (
    "fmt"
    "net/http"
    "time"
)

type Middleware func(http.Handler) http.Handler

// Chain — middleware-ləri sıra ilə tətbiq edir
func Chain(h http.Handler, middlewares ...Middleware) http.Handler {
    // Tərsinə tətbiq — sıra qorunur
    for i := len(middlewares) - 1; i >= 0; i-- {
        h = middlewares[i](h)
    }
    return h
}

// Logger middleware
func Logger(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        start := time.Now()
        next.ServeHTTP(w, r)
        fmt.Printf("[%s] %s %s %v\n", time.Now().Format("15:04:05"), r.Method, r.URL.Path, time.Since(start))
    })
}

// Auth middleware
func Auth(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        token := r.Header.Get("Authorization")
        if token == "" {
            http.Error(w, "icazəsiz", http.StatusUnauthorized)
            return
        }
        next.ServeHTTP(w, r)
    })
}

// RateLimit middleware (sadə)
func RateLimit(rps int) Middleware {
    ticker := time.NewTicker(time.Second / time.Duration(rps))
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            select {
            case <-ticker.C:
                next.ServeHTTP(w, r)
            default:
                http.Error(w, "çox sorğu", http.StatusTooManyRequests)
            }
        })
    }
}

func main() {
    handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        fmt.Fprintln(w, "OK")
    })

    // Middleware zənciri: Logger → RateLimit → Auth → Handler
    chained := Chain(handler, Logger, RateLimit(100), Auth)

    http.Handle("/api/", chained)
    fmt.Println("Server başladı: :8080")
    // http.ListenAndServe(":8080", nil)
}
```

### Nümunə 5: Repository + Service — Go-da layered arxitektura

```go
package main

import (
    "context"
    "fmt"
)

// Domain
type Product struct {
    ID    int
    Name  string
    Price float64
    Stock int
}

// Repository interface — minimal
type ProductRepository interface {
    FindByID(ctx context.Context, id int) (*Product, error)
    Save(ctx context.Context, p *Product) error
    FindAll(ctx context.Context) ([]*Product, error)
}

// In-memory implementasiya (test üçün)
type memProductRepo struct {
    data map[int]*Product
}

func NewMemProductRepo() ProductRepository {
    return &memProductRepo{data: make(map[int]*Product)}
}

func (r *memProductRepo) FindByID(_ context.Context, id int) (*Product, error) {
    p, ok := r.data[id]
    if !ok {
        return nil, fmt.Errorf("məhsul tapılmadı: %d", id)
    }
    return p, nil
}

func (r *memProductRepo) Save(_ context.Context, p *Product) error {
    r.data[p.ID] = p
    return nil
}

func (r *memProductRepo) FindAll(_ context.Context) ([]*Product, error) {
    result := make([]*Product, 0, len(r.data))
    for _, p := range r.data {
        result = append(result, p)
    }
    return result, nil
}

// Service — biznes məntiqi
type ProductService struct {
    repo   ProductRepository
    events EventPublisher
}

type EventPublisher interface {
    Publish(topic string, data interface{})
}

func NewProductService(repo ProductRepository, events EventPublisher) *ProductService {
    return &ProductService{repo: repo, events: events}
}

func (s *ProductService) Purchase(ctx context.Context, id, qty int) error {
    p, err := s.repo.FindByID(ctx, id)
    if err != nil {
        return fmt.Errorf("məhsul tapılmadı: %w", err)
    }
    if p.Stock < qty {
        return fmt.Errorf("stok kifayət deyil: %d < %d", p.Stock, qty)
    }
    p.Stock -= qty
    if err := s.repo.Save(ctx, p); err != nil {
        return fmt.Errorf("saxlama xətası: %w", err)
    }
    s.events.Publish("product.purchased", map[string]interface{}{
        "product_id": id,
        "quantity":   qty,
    })
    return nil
}

// Stub event publisher (test üçün)
type noopPublisher struct{}
func (n *noopPublisher) Publish(_ string, _ interface{}) {}

func main() {
    ctx := context.Background()
    repo := NewMemProductRepo()

    // Seed
    repo.Save(ctx, &Product{ID: 1, Name: "Laptop", Price: 1200, Stock: 5})

    svc := NewProductService(repo, &noopPublisher{})

    if err := svc.Purchase(ctx, 1, 2); err != nil {
        fmt.Println("Xəta:", err)
    } else {
        fmt.Println("Alış uğurludur")
    }

    p, _ := repo.FindByID(ctx, 1)
    fmt.Printf("Qalan stok: %d\n", p.Stock) // 3
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Functional Options genişləndirilməsi:**
`NewServer` üçün `WithHealthCheck(path string)`, `WithMetrics(endpoint string)`, `WithGracefulShutdown(timeout time.Duration)` option-ları əlavə edin. Hər option validasiya etsin.

**Tapşırıq 2 — Event sourcing:**
`EventBus` üçün wildcard abunəlik əlavə edin: `bus.Subscribe("order.*")` — `order.created`, `order.cancelled`, `order.shipped` mövzularının hamısını tutsun.

**Tapşırıq 3 — Middleware test:**
Logger middleware üçün `httptest` istifadə edərək test yazın. Log məzmununu yoxlayın.

**Tapşırıq 4 — Repository mock:**
`ProductRepository` interfeysi üçün mock yaradın. `ProductService.Purchase` üçün test yazın: stok kifayətsiz halı, DB xətası halı.

## Əlaqəli Mövzular

- [17-interfaces](17-interfaces.md) — interface əsasları
- [45-functional-options](45-functional-options.md) — functional options ətraflı
- [55-repository-pattern](55-repository-pattern.md) — repository pattern
- [64-dependency-injection](64-dependency-injection.md) — DI və wire
- [35-middleware-and-routing](35-middleware-and-routing.md) — HTTP middleware
