# Ecommerce Go — Laravel ↔ Spring ↔ Go Müqayisəsi

Bu layihə eyni DDD e-commerce tətbiqinin **Go (Gin + GORM)** versiyasıdır. Məqsəd — üç texnologiya stack-ini birlikdə öyrənmək.

## Layihə quruluşu

```
golang/
├── cmd/
│   ├── api/main.go          ← HTTP server entry (Spring main class əvəzi)
│   └── cli/main.go          ← Artisan / Spring Shell əvəzi (cobra)
├── configs/config.yaml      ← config/*.php / application.yml əvəzi
├── docker/                  ← 6 servis
├── migrations/{user,product,order,payment}/
│                             ← 21 SQL fayl (up.sql + down.sql), hər DB-də
├── internal/                ← Go konvensiyası: private code
│   ├── config/              ← viper YAML loader
│   ├── server/server.go     ← composition root (manual DI)
│   ├── middleware/          ← 7 Gin middleware + JWT auth
│   ├── seed/                ← test data seeders
│   ├── shared/
│   │   ├── domain/          ← AggregateRoot, DomainEvent, Specification, errors
│   │   ├── application/
│   │   │   ├── bus/         ← CommandBus, QueryBus (generic, reflection)
│   │   │   └── middleware/  ← 5 pipeline MW: Logging→Idempotency→Validation→Tx→Retry
│   │   └── infrastructure/
│   │       ├── api/         ← ApiResponse, ErrorHandler
│   │       ├── audit/       ← AuditLog entity + Service
│   │       ├── auth/        ← JWT + 2FA (pquerna/otp)
│   │       ├── cache/       ← TaggedCache (Redis SET)
│   │       ├── database/    ← 4 GORM instance + migrations
│   │       ├── featureflags/
│   │       ├── locking/     ← DistributedLock (Redsync)
│   │       ├── messaging/   ← RabbitMQ Publisher, Outbox, Inbox, DLQ, IdempotentConsumer
│   │       └── webhook/     ← HMAC-SHA256 signing + log
│   ├── user/
│   │   ├── domain/          ← User aggregate, Email/Password/UserID VO, events
│   │   ├── application/     ← RegisterUser, GetUser handlers
│   │   └── infrastructure/
│   │       ├── persistence/ ← GORM UserModel + Repository impl
│   │       └── web/         ← AuthController, JWT issuance
│   ├── product/             ← Product + Money/Stock VO + CachedRepository (decorator)
│   ├── order/               ← Order aggregate (state machine) + Saga + OrderItems
│   ├── payment/             ← Payment + Strategy (3 gateway) + ACL + Circuit Breaker
│   ├── notification/        ← 4 listener + 4 email template
│   ├── webhook/             ← Webhook CRUD
│   ├── health/              ← K8s liveness/readiness
│   ├── search/              ← Global search
│   └── admin/               ← DLQ admin endpoint
├── test/
│   ├── unit/                ← JUnit əvəzi: testify
│   ├── integration/         ← Testcontainers
│   └── feature/             ← MockMvc əvəzi: httptest
├── templates/emails/        ← 5 HTML email şablonu (Go text/template sintaksisi)
│   │                          Laravel: resources/views/emails/*.blade.php
│   │                          Spring:  src/main/resources/templates/*.html
│   ├── order-confirmation.html
│   ├── payment-receipt.html
│   ├── payment-failed.html
│   ├── low-stock-alert.html
│   └── password-reset.html
├── go.mod / go.sum
├── Dockerfile (multi-stage)
├── docker-compose.yml
└── README.md
```

## Üç-texnologiyalı Müqayisə Cədvəli

| Konsept | Laravel (PHP) | Spring (Java) | Go (bu layihə) |
|---------|---------------|---------------|---------------|
| **Web framework** | Laravel | Spring Web MVC | **Gin** (78k ⭐) |
| **ORM** | Eloquent | Spring Data JPA / Hibernate | **GORM** (36k ⭐) |
| **Migration** | Laravel Migrations | Flyway | **golang-migrate** |
| **DI** | Service Container | Spring IoC (auto) | **Manual wiring** (no magic) |
| **Config** | config/*.php | application.yml + `@ConfigurationProperties` | **viper** + YAML |
| **Validation** | Form Request rules | Bean Validation annotations | **validator/v10** struct tags |
| **Logging** | StructuredLogger | SLF4J + Logback | **log/slog** stdlib |
| **Cache** | Cache::tags(...) | Spring Cache + Redis | **go-redis + custom tagged cache** |
| **Distributed Lock** | Redis SETNX | Redisson `RLock` | **redsync** |
| **JWT** | Sanctum | `jjwt` + Spring Security | **golang-jwt** |
| **2FA TOTP** | pragmarx/google2fa | googleauth | **pquerna/otp** |
| **Queue/Messaging** | php-amqplib | spring-rabbit | **amqp091-go** |
| **CQRS framework** | custom | Axon (opsional) | **Watermill** (opsional) |
| **Circuit Breaker** | custom | Resilience4j `@CircuitBreaker` | **sony/gobreaker** |
| **Rate Limiting** | `RateLimiter::for(...)` | Bucket4j | **golang.org/x/time/rate** |
| **Test framework** | PHPUnit | JUnit 5 + Mockito | **testify** |
| **Integration test** | DatabaseRefresh trait | Testcontainers | **testcontainers-go** |
| **HTTP test** | $this->json(...) | MockMvc | **httptest** |
| **Email** | Mail::to()->send() | JavaMailSender + Thymeleaf | **wneessen/go-mail** + text/template |
| **CLI/Artisan** | Artisan command | Spring Shell `@ShellComponent` | **spf13/cobra** |
| **Scheduler** | Kernel $schedule | `@Scheduled` | **time.Ticker + goroutine** |
| **Docker** | PHP-FPM image | JDK image | **Alpine + static binary** (~15MB) |

## Konseptual Paralellər

### 1. Aggregate Root
```php
// Laravel
class Order extends AggregateRoot {
    public function confirm() { $this->recordEvent(new OrderConfirmedEvent(...)); }
}
```
```java
// Spring
public class Order extends AggregateRoot {
    public void confirm() { this.recordEvent(new OrderConfirmedEvent(...)); }
}
```
```go
// Go — composition (embedding)
type Order struct {
    domain.AggregateRoot   // ← embed
    id OrderID
    // ...
}
func (o *Order) Confirm() error {
    o.RecordEvent(OrderConfirmedEvent{OrderID: o.id})
    return nil
}
```

### 2. Value Object
```php
// Laravel — PHP immutable class
class Money {
    public function __construct(public readonly int $amount, public readonly Currency $currency) {}
    public function add(Money $other): Money { ... }
}
```
```java
// Spring — Java record + compact constructor
public record Money(long amount, Currency currency) {
    public Money {
        if (amount < 0) throw new DomainException(...);
    }
    public Money add(Money other) { ... }
}
```
```go
// Go — struct with value receiver (immutable by convention)
type Money struct {
    amount   int64
    currency Currency
}
func NewMoney(amount int64, c Currency) (Money, error) {
    if amount < 0 { return Money{}, domain.NewDomainError(...) }
    return Money{amount, c}, nil
}
func (m Money) Add(other Money) (Money, error) { ... }
```

### 3. CQRS Command + Handler
```php
// Laravel
class CreateOrderCommand { public function __construct(public Uuid $userId, ...) {} }
class CreateOrderHandler {
    public function handle(CreateOrderCommand $cmd): Uuid { ... }
}
```
```java
// Spring
public record CreateOrderCommand(UUID userId, ...) implements Command<UUID> {}
@Service
public class CreateOrderHandler implements CommandHandler<CreateOrderCommand, UUID> {
    public UUID handle(CreateOrderCommand cmd) { ... }
}
```
```go
// Go
type CreateOrderCommand struct {
    UserID uuid.UUID `validate:"required"`
}
type CreateOrderHandler struct {
    repo orderDomain.Repository
}
func (h *CreateOrderHandler) Handle(ctx context.Context, cmd CreateOrderCommand) (uuid.UUID, error) { ... }

// Register:
bus.Register[CreateOrderCommand, uuid.UUID](cmdBus, handler)
// Dispatch:
id, err := bus.Dispatch[CreateOrderCommand, uuid.UUID](ctx, cmdBus, cmd)
```

### 4. Middleware Pipeline (CommandBus)
3 versiyada eyni sıra:
```
Logging (10) → Idempotency (20) → Validation (30) → Transaction (40) → RetryOnConcurrency (50) → Handler
```

### 5. Strategy Pattern (Payment)
- **Laravel**: `PaymentGatewayInterface` + 3 impl, `PaymentStrategyResolver` (array-də)
- **Spring**: interface + 3 `@Component`, DI avtomatik map-ə yığır
- **Go**: `Gateway` interface + 3 struct, `NewStrategyResolver(gws...)` variadic

### 6. Repository Pattern
- **Laravel**: `EloquentOrderRepository implements OrderRepositoryInterface`
- **Spring**: `OrderRepositoryImpl implements OrderRepository` (adapter) + `JpaOrderRepository` (Spring Data)
- **Go**: `Repository` struct implements `domain.Repository` interface — eyni pattern, implicit interface

### 7. Dependency Injection fəlsəfəsi

| Framework | DI üslubu |
|-----------|-----------|
| Laravel | Service Container (auto, reflection) |
| Spring | @Component scan + auto-wiring (magic) |
| **Go** | **Manual wiring in main.go / server.go** — hər şey **görünür**, debug asan |

Go-nun dəyəri: "no magic" — hər dependency əl ilə qoşulur, amma oxumaq asandır.

## Endpoint-lər (Laravel ilə eyni 43 ədəd)

Bütün route-lar `/api/` prefix-i ilə:
- **Auth**: register, login, logout, me, 2fa/{enable,confirm,disable,verify}, forgot/reset-password
- **Users**: GET /users/{id}
- **Products**: GET (list/show, public), POST/PATCH (auth)
- **Orders**: POST, GET, list-by-user, cancel, update-status (hamısı auth)
- **Payments**: process, show
- **Webhooks**: CRUD
- **Health**: /, /live, /ready
- **Admin**: /admin/failed-jobs

## İşə salma

```bash
cd app/golang

# 1. Docker (tövsiyə olunur)
docker compose up --build

# 2. Lokal (Go 1.23 + MySQL/Redis/RabbitMQ lazımdır)
docker compose up -d mysql redis rabbitmq mailpit
go mod tidy
go run ./cmd/api

# 3. Test
go test ./test/unit/...                            # unit testlər (domain VO-lar)
go test ./test/feature/...                         # HTTP layer testləri (in-memory)
go test -tags=integration ./test/integration/...   # Testcontainers (Docker lazımdır)

# 4. CLI
go run ./cmd/cli outbox:publish
go run ./cmd/cli projection:rebuild
```

## Vacib fərqlər (Laravel/Spring-dən)

1. **Domain layer saf POJO-dır** (3 versiyada da eyni prinsip), amma Go-da GORM tag ayrı struct-dadır (UserModel).
2. **Go generics**: CommandBus generic interface-lə, amma runtime-da reflection istifadə edir.
3. **Context propagation**: hər handler `context.Context` qəbul edir — Laravel/Spring-də implicit (ThreadLocal), Go-da explicit.
4. **Error handling**: `error` interface — exception yoxdur. `errors.As/Is` ilə tip yoxlaması.
5. **No annotations**: `@Component`, `@Service`, `@Transactional` əvəzinə explicit constructor + manual wiring.
6. **Struct embedding**: inheritance əvəzinə composition.
7. **Implicit interface**: Java `implements` açıq yazılır, Go-da struct hər interface-i avtomatik qarşılayır əgər method set uyğundursa.
8. **Go module path**: `github.com/orkhan/ecommerce` — bunu öz GitHub username-inizə dəyişin.

## Multi-DB Transactional Outbox — **per-context həll**

Hər context-in öz `outbox_messages` cədvəli var (4 DB × 1 outbox). `EventDispatcher`
routing key prefix-ə görə uyğun outbox-u seçir:
- `user.registered`     → `user_db.outbox_messages`
- `product.stock.low`   → `product_db.outbox_messages`
- `order.created`       → `order_db.outbox_messages`
- `payment.completed`   → `payment_db.outbox_messages`

`server.go`-da 4 ayrı `OutboxPublisher` goroutine işə salınır — hər biri öz DB-dən
oxuyub RabbitMQ-yə publish edir. Bu multi-DB 2PC problemini ƏLLƏ LAZIM DEYİL həll edir.

## Distributed Lock

`ProcessPaymentHandler` artıq Redsync-based distributed lock ilə wrap olunub:
```go
h.locker.ExecuteLocked(ctx, "payment:"+orderID, 30*time.Second, func() error {
    return h.process(ctx, cmd)
})
```
Eyni `orderID` üçün 2 paralel payment request gəlsə, ikincisi lock ala bilmir və xəta alır.

## Performans müqayisəsi (təxmini)

| Metric | Laravel | Spring | Go |
|--------|---------|--------|-----|
| Cold start | ~500ms | ~3-5s | **~50ms** |
| Memory | ~50MB per worker | ~300MB | **~15MB** |
| Docker image | ~200MB | ~250MB | **~15MB** |
| Concurrency | PHP-FPM (process) | Threads + virtual threads | **goroutines** (100k+) |

Go production-da container sıxlığı ilə qənaət edir — **eyni trafikə 5-10x az resurs**.

## Öyrənmə yolu

Bu layihədə hər Go faylının başında **Laravel + Spring müqayisəsi** şərhlərdə verilib. Tövsiyə:
1. Kiçikdən başlayın: `internal/shared/domain/` (primitive-lər)
2. Sonra 1 context: `internal/user/` (command → handler → controller)
3. Pattern-lər: `internal/payment/` (Strategy + Circuit Breaker)
4. Ən kompleks: `internal/order/` (state machine + saga + event sourcing)
