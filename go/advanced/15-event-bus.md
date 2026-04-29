# Event Bus: Domain Events (Lead)

## İcmal

Event Bus — komponentlər arasında loose coupling yaratmaq üçün publish-subscribe pattern-in tətbiqidir. Domain Event-lər business hadisələrini əks etdirir: `UserRegistered`, `OrderPlaced`, `PaymentFailed`.

## Niyə Vacibdir

- Usecase-lər bir-birindən asılı olmur: `OrderUsecase` `EmailService`-i birbaşa çağırmır — event yayır
- Email göndərmə xəta versə sifariş silinmir — hadisə ayrılır
- Yeni funksiya əlavə etmək üçün mövcud koda toxunmaq lazım deyil — yeni subscriber əlavə et
- Audit log, analytics, notification — hamısı eyni event-ə abunə ola bilər

## Əsas Anlayışlar

**Növlər:**

**Sinxron Event Bus:**
- Handler-lər eyni goroutine-də çalışır
- Sadə, test asan
- Bir handler yavaşlarsa hamısı yavaşlayır

**Asinxron Event Bus:**
- Handler-lər ayrı goroutine-lərdə çalışır
- Publisher bloklanmır
- Xəta idarəetməsi mürəkkəbdir (panic recovery, retry)

**Domain Event vs Integration Event:**
- Domain Event: bir servis daxilindəki hadisə (in-process)
- Integration Event: servislər arası hadisə (Kafka, RabbitMQ)

## Praktik Baxış

**Nə vaxt Event Bus:**
- Bir əməliyyat tamamlananda N yan iş görülməlidir
- Yan işlər əsas iş üçün kritik deyil (email fail → sifariş qeyd edilir)
- Kod decoupled olmalıdır

**Nə vaxt birbaşa çağırış:**
- Yan iş kritikdirsə (payment uğursuz → inventory geri al) — saga istifadə et
- Test yazmaq çətin olacaqsa — sadə DI daha yaxşıdır
- Çox kiçik layihə — over-engineering

**Common mistakes:**
- Asinxron event-də panic — scheduler durur
- Handler-in database-ə bağlı olması — tranzaksiya bağlandıqdan sonra event
- Event-ə çox böyük data qoymaq — sadəcə ID + minimal məlumat qoy

## Nümunələr

### Nümunə 1: Sadə sinxron event bus

```go
package eventbus

import (
    "context"
    "fmt"
    "sync"
)

// Event — bütün eventlər bu interface-i implement etməlidir
type Event interface {
    EventName() string
}

// Handler — event-i qəbul edib işləyir
type Handler func(ctx context.Context, event Event) error

// EventBus — publish-subscribe
type EventBus struct {
    mu       sync.RWMutex
    handlers map[string][]Handler
}

func New() *EventBus {
    return &EventBus{
        handlers: make(map[string][]Handler),
    }
}

// Subscribe — event növünə abunə ol
func (b *EventBus) Subscribe(eventName string, handler Handler) {
    b.mu.Lock()
    defer b.mu.Unlock()
    b.handlers[eventName] = append(b.handlers[eventName], handler)
}

// Publish — event yay, bütün handler-ləri çağır
func (b *EventBus) Publish(ctx context.Context, event Event) error {
    b.mu.RLock()
    handlers := b.handlers[event.EventName()]
    b.mu.RUnlock()

    for _, h := range handlers {
        if err := h(ctx, event); err != nil {
            return fmt.Errorf("handler xətası [%s]: %w", event.EventName(), err)
        }
    }
    return nil
}
```

### Nümunə 2: Domain event-lər

```go
package domain

import "time"

// UserRegistered — qeydiyyat tamamlandı
type UserRegistered struct {
    UserID    int64
    Email     string
    Name      string
    OccurredAt time.Time
}

func (e UserRegistered) EventName() string { return "user.registered" }

// OrderPlaced — sifariş verildi
type OrderPlaced struct {
    OrderID    int64
    UserID     int64
    TotalAmount float64
    Items       []OrderItem
    OccurredAt  time.Time
}

func (e OrderPlaced) EventName() string { return "order.placed" }

// PaymentFailed — ödəniş uğursuz
type PaymentFailed struct {
    OrderID   int64
    UserID    int64
    Reason    string
    OccurredAt time.Time
}

func (e PaymentFailed) EventName() string { return "payment.failed" }
```

### Nümunə 3: Subscriber-lər — hər biri ayrı məsuliyyət

```go
package subscribers

import (
    "context"
    "log/slog"
    "myapp/domain"
    "myapp/eventbus"
)

// Email göndərən subscriber
type EmailSubscriber struct {
    emailService EmailService
}

func (s *EmailSubscriber) OnUserRegistered(ctx context.Context, event eventbus.Event) error {
    e := event.(domain.UserRegistered)

    if err := s.emailService.SendWelcomeEmail(ctx, e.Email, e.Name); err != nil {
        // Email xətası — log et, amma return nil (kritik deyil)
        slog.Error("Welcome email göndərilmədi",
            slog.String("email", e.Email),
            slog.String("error", err.Error()),
        )
        return nil // Email fail → qeydiyyat uğurludur
    }
    return nil
}

// Analytics subscriber
type AnalyticsSubscriber struct {
    analytics AnalyticsService
}

func (s *AnalyticsSubscriber) OnUserRegistered(ctx context.Context, event eventbus.Event) error {
    e := event.(domain.UserRegistered)
    s.analytics.Track("user_registered", map[string]interface{}{
        "user_id": e.UserID,
        "email":   e.Email,
    })
    return nil
}

// Audit log subscriber
type AuditSubscriber struct{}

func (s *AuditSubscriber) OnUserRegistered(ctx context.Context, event eventbus.Event) error {
    e := event.(domain.UserRegistered)
    slog.Info("Audit: istifadəçi qeydiyyatdan keçdi",
        slog.Int64("user_id", e.UserID),
        slog.String("email", e.Email),
        slog.Time("at", e.OccurredAt),
    )
    return nil
}

// Qeydiyyat — bütün subscriber-ləri event-ə bağla
func RegisterSubscribers(bus *eventbus.EventBus, email *EmailSubscriber, analytics *AnalyticsSubscriber, audit *AuditSubscriber) {
    bus.Subscribe("user.registered", email.OnUserRegistered)
    bus.Subscribe("user.registered", analytics.OnUserRegistered)
    bus.Subscribe("user.registered", audit.OnUserRegistered)
}
```

### Nümunə 4: Usecase-də event publish

```go
package usecase

import (
    "context"
    "myapp/domain"
    "myapp/eventbus"
    "time"
)

type UserUsecase struct {
    repo   domain.UserRepository
    bus    *eventbus.EventBus
    hasher domain.PasswordHasher
}

func (uc *UserUsecase) Register(ctx context.Context, name, email, password string) (*domain.User, error) {
    // Business logic...
    user := &domain.User{Name: name, Email: email}
    if err := uc.repo.Create(ctx, user); err != nil {
        return nil, err
    }

    // DB-yə yazıldı → event yay
    // Subscriber-lər bilmir necə yarandı — yalnız hadisəni bilir
    uc.bus.Publish(ctx, domain.UserRegistered{
        UserID:     user.ID,
        Email:      email,
        Name:       name,
        OccurredAt: time.Now(),
    })

    return user, nil
}
```

### Nümunə 5: Asinxron event bus

```go
package eventbus

import (
    "context"
    "fmt"
    "log/slog"
    "runtime/debug"
    "sync"
)

type AsyncEventBus struct {
    mu       sync.RWMutex
    handlers map[string][]Handler
    workers  int
}

func NewAsync(workers int) *AsyncEventBus {
    return &AsyncEventBus{
        handlers: make(map[string][]Handler),
        workers:  workers,
    }
}

func (b *AsyncEventBus) Subscribe(eventName string, handler Handler) {
    b.mu.Lock()
    defer b.mu.Unlock()
    b.handlers[eventName] = append(b.handlers[eventName], handler)
}

// Publish bloklamır — handler-lər ayrı goroutine-də çalışır
func (b *AsyncEventBus) Publish(ctx context.Context, event Event) {
    b.mu.RLock()
    handlers := b.handlers[event.EventName()]
    b.mu.RUnlock()

    for _, h := range handlers {
        h := h // capture
        go func() {
            defer func() {
                if r := recover(); r != nil {
                    slog.Error("Event handler panic",
                        slog.String("event", event.EventName()),
                        slog.String("panic", fmt.Sprintf("%v", r)),
                        slog.String("stack", string(debug.Stack())),
                    )
                }
            }()

            if err := h(ctx, event); err != nil {
                slog.Error("Asinxron event handler xətası",
                    slog.String("event", event.EventName()),
                    slog.String("error", err.Error()),
                )
            }
        }()
    }
}
```

### Nümunə 6: Test — event-ləri asan test et

```go
package usecase_test

import (
    "context"
    "testing"
    "myapp/domain"
    "myapp/eventbus"
    "myapp/usecase"
)

func TestRegister_PublishesEvent(t *testing.T) {
    bus := eventbus.New()
    var capturedEvent domain.UserRegistered

    // Test subscriber — event-i yaxala
    bus.Subscribe("user.registered", func(ctx context.Context, event eventbus.Event) error {
        capturedEvent = event.(domain.UserRegistered)
        return nil
    })

    repo := NewMockUserRepo()
    uc := usecase.NewUserUsecase(repo, bus, &MockHasher{})

    _, err := uc.Register(context.Background(), "Orxan", "orxan@test.com", "password")
    if err != nil {
        t.Fatal(err)
    }

    // Event yayıldımı?
    if capturedEvent.Email != "orxan@test.com" {
        t.Errorf("gözlənilən email: orxan@test.com, alınan: %s", capturedEvent.Email)
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
`EventBus` implement edin. `UserRegistered` event-i yayın. İki subscriber: email log + analytics log. Hər ikisinin çağırıldığını test edin.

**Tapşırıq 2:**
`OrderPlaced` event-i üçün inventory rezervasiya subscriber-i yazın. Inventory xəta versə order ləğv edilməsin (asinxron, non-critical).

**Tapşırıq 3:**
Asinxron EventBus-a retry məntiqi əlavə edin: handler 3 dəfə xəta versə dead-letter queue-ya düşsün.

## PHP ilə Müqayisə

Laravel `Event::dispatch()` + `Listener` sistemi eyni publish-subscribe modelini tətbiq edir. Go-da eyni konsept interface-lər ilə manual implement edilir.

```php
// Laravel — event dispatch
event(new UserRegistered($user));

// Listener — app/Providers/EventServiceProvider.php
protected $listen = [
    UserRegistered::class => [
        SendWelcomeEmail::class,
        TrackAnalytics::class,
        AuditLog::class,
    ],
];
```

```go
// Go — event bus ilə
bus.Subscribe("user.registered", emailSubscriber.OnUserRegistered)
bus.Subscribe("user.registered", analyticsSubscriber.OnUserRegistered)
bus.Subscribe("user.registered", auditSubscriber.OnUserRegistered)

bus.Publish(ctx, domain.UserRegistered{...})
```

**Əsas fərqlər:**
- Laravel: listener-lər avtomatik kəşf olunur (auto-discovery); Go: əl ilə subscribe edilir
- Laravel: `ShouldQueue` ilə listener asinxron olur; Go: `go func()` ilə
- Laravel Horizon: queue monitoring; Go-da özün metric əlavə edirsən

## Əlaqəli Mövzular

- [72-message-queues.md](25-message-queues.md) — Kafka ilə integration events
- [27-clean-architecture.md](27-clean-architecture.md) — Domain layer, usecase
- [27-goroutines-and-channels.md](../core/27-goroutines-and-channels.md) — Goroutine əsasları
- [26-microservices.md](26-microservices.md) — Servislərarası hadisələr
