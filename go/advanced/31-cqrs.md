# CQRS (Architect)

## İcmal

CQRS — Command Query Responsibility Segregation. **Write (command) side** ilə **read (query) side**-ı tam ayırır. Command: state dəyişdirir, yalnız error qaytarır. Query: data oxuyur, heç bir side effect yoxdur. Go-da generics (1.18+) ilə type-safe CommandBus və QueryBus yaratmaq mümkündür. CQRS-i Event Sourcing, DDD ilə birlikdə tətbiq etmək ən güclü kombinasiyadır.

## Niyə Vacibdir

Mürəkkəb sistemlərdə write və read load-ları fərqli olur: yazma az, oxuma çox. CRUD modelində eyni domain model həm write həm read üçün istifadə olunur — bu tez-tez performance problemlərinə, mürəkkəb query-lərə, N+1 problemlərinə gətirir. CQRS ilə read side tamamilə ayrı (denormalized, cache-lənmiş) model istifadə edir; write side isə domain invariantlarını qoruyur. Xüsusilə e-commerce, fintech, SaaS sistemlərində kritikdir.

## Əsas Anlayışlar

- **Command** — state dəyişdirmək üçün intent. `CreateOrderCommand`, `CancelOrderCommand`. Yalnız `error` qaytarır.
- **Query** — data almaq üçün request. `GetOrderQuery`, `ListOrdersQuery`. Yalnız data qaytarır, heç bir side effect yoxdur.
- **CommandHandler** — bir command-i işlədən component. Domain logic-i çağırır.
- **QueryHandler** — bir query-i işlədən component. Read model-dən data qaytarır.
- **CommandBus** — command-i doğru handler-ə yönləndirir.
- **QueryBus** — query-i doğru handler-ə yönləndirir.
- **Read Model (Projection)** — query üçün optimallaşdırılmış ayrı data strukturu; denormalized.
- **Write Model** — domain aggregate-lər, invariantları qoruyur.
- **Eventual Consistency** — write tamamlandıqdan sonra read model sinxronlaşır (kiçik gecikmə mümkün).

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- E-commerce: sifarişi əlavə etmək (command) vs. sifarişlər siyahısı göstərmək (query — ayrı optimized table)
- Fintech: hesabdan pul çıxarmaq (command) vs. balans göstərmək (query — cached read model)
- SaaS: subscription yaratmaq (command) vs. dashboard məlumatları (query — pre-aggregated view)

**Trade-off-lar:**
- İki ayrı data model saxlamaq: read model sinxronlaşması (eventual consistency)
- Daha mürəkkəb infra: command bus, query bus, projections
- Debugging çətinləşə bilər: bir state dəyişikliyi iki fərqli yerdə əks olunur
- Read replica ilə eyni DB istifadə etmək sadə CQRS üçün kifayət edir

**Nə vaxt istifadə etməmək lazımdır:**
- Sadə CRUD app: user yaratmaq, listelemek — CQRS overkill
- Kiçik komanda: pattern-i bilməyənlər üçün overhead artırır
- Güclü consistency tələbi: eyni anda yazılan data dərhal oxunmalıdırsa, eventual consistency problem yaradır

**Ümumi səhvlər:**
- CommandHandler-dən data qaytarmaq — Command yalnız `error` qaytarmalıdır; ID lazımdırsa command-ə daxil edin
- QueryHandler-də state dəyişdirmək — Query side effect-siz olmalıdır
- Hər endpoint üçün ayrı command/query yaratmaq — bəzən sadə CRUD kifayət edir
- Read model-i real-time sinxronlaşdırmaq istəmək — eventual consistency qəbul edin

## Nümunələr

### Ümumi Nümunə

```
Write Side (Command):
  CreateOrderCommand
       │
       ▼
  CreateOrderCommandHandler
       │ domain aggregate-ı istifadə edir
       ▼
  OrderAggregate.Place()
       │ save to write DB
       ▼
  OrderRepository.Save()
       │ domain event emit
       ▼
  EventBus → ProjectionUpdater
                    │
                    ▼
              Read Model (orders_view table)

Read Side (Query):
  GetOrderQuery
       │
       ▼
  GetOrderQueryHandler
       │ read model-dən oxuyur (no domain logic)
       ▼
  SELECT * FROM orders_view WHERE id = ?
       │
       ▼
  OrderReadModel (DTO) → HTTP Response
```

### Kod Nümunəsi

**Command və Query marker interface-ləri:**

```go
// internal/cqrs/cqrs.go
package cqrs

import "context"

// Command — write side marker
type Command interface {
    commandMarker()
}

// Query — read side marker
type Query interface {
    queryMarker()
}

// CommandHandler — generic, type-safe
type CommandHandler[C Command] interface {
    Handle(ctx context.Context, cmd C) error
}

// QueryHandler — generic, type-safe
type QueryHandler[Q Query, R any] interface {
    Handle(ctx context.Context, query Q) (R, error)
}
```

**CommandBus — in-memory implementation:**

```go
// internal/cqrs/command_bus.go
package cqrs

import (
    "context"
    "fmt"
    "reflect"
)

type commandHandlerFunc func(ctx context.Context, cmd Command) error

// CommandBus — command-i handler-ə yönləndirir
type CommandBus struct {
    handlers map[reflect.Type]commandHandlerFunc
}

func NewCommandBus() *CommandBus {
    return &CommandBus{handlers: make(map[reflect.Type]commandHandlerFunc)}
}

// Register — generic helper ilə type-safe registration
func Register[C Command](bus *CommandBus, handler CommandHandler[C]) {
    var zero C
    t := reflect.TypeOf(zero)
    bus.handlers[t] = func(ctx context.Context, cmd Command) error {
        return handler.Handle(ctx, cmd.(C))
    }
}

func (b *CommandBus) Dispatch(ctx context.Context, cmd Command) error {
    t := reflect.TypeOf(cmd)
    handler, ok := b.handlers[t]
    if !ok {
        return fmt.Errorf("no handler registered for %s", t.Name())
    }
    return handler(ctx, cmd)
}
```

**QueryBus — in-memory implementation:**

```go
// internal/cqrs/query_bus.go
package cqrs

import (
    "context"
    "fmt"
    "reflect"
)

type queryHandlerFunc func(ctx context.Context, query Query) (any, error)

type QueryBus struct {
    handlers map[reflect.Type]queryHandlerFunc
}

func NewQueryBus() *QueryBus {
    return &QueryBus{handlers: make(map[reflect.Type]queryHandlerFunc)}
}

func RegisterQuery[Q Query, R any](bus *QueryBus, handler QueryHandler[Q, R]) {
    var zero Q
    t := reflect.TypeOf(zero)
    bus.handlers[t] = func(ctx context.Context, query Query) (any, error) {
        return handler.Handle(ctx, query.(Q))
    }
}

func Dispatch[R any](bus *QueryBus, ctx context.Context, query Query) (R, error) {
    t := reflect.TypeOf(query)
    handler, ok := bus.handlers[t]
    if !ok {
        var zero R
        return zero, fmt.Errorf("no handler registered for %s", t.Name())
    }
    result, err := handler(ctx, query)
    if err != nil {
        var zero R
        return zero, err
    }
    return result.(R), nil
}
```

**Order domain — Commands:**

```go
// internal/order/commands.go
package order

import "github.com/yourorg/app/internal/cqrs"

// CreateOrderCommand
type CreateOrderCommand struct {
    OrderID    string
    CustomerID string
    Items      []OrderItemInput
}

type OrderItemInput struct {
    ProductID string
    Name      string
    UnitPrice int64
    Quantity  int
}

func (c CreateOrderCommand) commandMarker() {}

// CancelOrderCommand
type CancelOrderCommand struct {
    OrderID string
    Reason  string
}

func (c CancelOrderCommand) commandMarker() {}

// Compile-time check — interface implementation yoxlanır
var _ cqrs.Command = CreateOrderCommand{}
var _ cqrs.Command = CancelOrderCommand{}
```

**Order domain — Queries:**

```go
// internal/order/queries.go
package order

import "github.com/yourorg/app/internal/cqrs"

// GetOrderQuery
type GetOrderQuery struct {
    OrderID string
}

func (q GetOrderQuery) queryMarker() {}

// ListOrdersQuery
type ListOrdersQuery struct {
    CustomerID string
    Page       int
    PageSize   int
}

func (q ListOrdersQuery) queryMarker() {}

// Read Model — query üçün optimallaşdırılmış DTO
type OrderReadModel struct {
    ID           string
    CustomerID   string
    Status       string
    TotalAmount  int64
    Currency     string
    ItemCount    int
    CreatedAt    string
}

type OrderListReadModel struct {
    Orders []OrderReadModel
    Total  int
    Page   int
}

// Compile-time check
var _ cqrs.Query = GetOrderQuery{}
var _ cqrs.Query = ListOrdersQuery{}
```

**CommandHandler implementasiyası:**

```go
// internal/order/create_order_handler.go
package order

import (
    "context"
    "fmt"

    "github.com/yourorg/app/internal/domain"
)

// WriteRepository — write side üçün
type WriteRepository interface {
    Save(ctx context.Context, order *domain.Order) error
    FindByID(ctx context.Context, id domain.OrderID) (*domain.Order, error)
}

// CreateOrderCommandHandler — domain aggregate-i işlədir
type CreateOrderCommandHandler struct {
    repo WriteRepository
}

func NewCreateOrderCommandHandler(repo WriteRepository) *CreateOrderCommandHandler {
    return &CreateOrderCommandHandler{repo: repo}
}

// Handle — yalnız error qaytarır
func (h *CreateOrderCommandHandler) Handle(ctx context.Context, cmd CreateOrderCommand) error {
    order, err := domain.NewOrder(domain.OrderID(cmd.OrderID), cmd.CustomerID)
    if err != nil {
        return fmt.Errorf("creating order: %w", err)
    }

    for _, item := range cmd.Items {
        price, err := domain.NewMoney(item.UnitPrice, "USD")
        if err != nil {
            return fmt.Errorf("invalid price: %w", err)
        }

        orderItem, err := domain.NewOrderItem(
            domain.OrderItemID(item.ProductID+"-item"),
            item.ProductID,
            item.Name,
            price,
            item.Quantity,
        )
        if err != nil {
            return fmt.Errorf("creating item: %w", err)
        }

        if err := order.AddItem(orderItem); err != nil {
            return fmt.Errorf("adding item: %w", err)
        }
    }

    if err := order.Place(); err != nil {
        return fmt.Errorf("placing order: %w", err)
    }

    return h.repo.Save(ctx, order)
}
```

**QueryHandler implementasiyası — ayrı read model:**

```go
// internal/order/get_order_handler.go
package order

import (
    "context"
    "database/sql"
    "fmt"
)

// ReadRepository — read side üçün; sadə SQL, no domain model
type ReadRepository interface {
    FindOrderByID(ctx context.Context, id string) (OrderReadModel, error)
    ListOrdersByCustomer(ctx context.Context, customerID string, page, size int) (OrderListReadModel, error)
}

// GetOrderQueryHandler
type GetOrderQueryHandler struct {
    readRepo ReadRepository
}

func NewGetOrderQueryHandler(readRepo ReadRepository) *GetOrderQueryHandler {
    return &GetOrderQueryHandler{readRepo: readRepo}
}

// Handle — yalnız data qaytarır, heç bir side effect
func (h *GetOrderQueryHandler) Handle(
    ctx context.Context,
    query GetOrderQuery,
) (OrderReadModel, error) {
    model, err := h.readRepo.FindOrderByID(ctx, query.OrderID)
    if err != nil {
        return OrderReadModel{}, fmt.Errorf("finding order: %w", err)
    }
    return model, nil
}

// PostgresReadRepository — read model-i birbaşa SELECT ilə qaytarır
type PostgresReadRepository struct {
    db *sql.DB
}

func NewPostgresReadRepository(db *sql.DB) *PostgresReadRepository {
    return &PostgresReadRepository{db: db}
}

func (r *PostgresReadRepository) FindOrderByID(
    ctx context.Context,
    id string,
) (OrderReadModel, error) {
    // Denormalized view-dan oxuyuruq — JOIN yoxdur, sürətlidir
    var m OrderReadModel
    err := r.db.QueryRowContext(ctx,
        `SELECT id, customer_id, status, total_amount, currency, item_count, created_at
         FROM orders_view WHERE id = $1`,
        id,
    ).Scan(
        &m.ID, &m.CustomerID, &m.Status,
        &m.TotalAmount, &m.Currency, &m.ItemCount, &m.CreatedAt,
    )
    if err == sql.ErrNoRows {
        return OrderReadModel{}, fmt.Errorf("order not found")
    }
    return m, err
}

func (r *PostgresReadRepository) ListOrdersByCustomer(
    ctx context.Context,
    customerID string,
    page, size int,
) (OrderListReadModel, error) {
    offset := (page - 1) * size
    rows, err := r.db.QueryContext(ctx,
        `SELECT id, customer_id, status, total_amount, currency, item_count, created_at
         FROM orders_view WHERE customer_id = $1
         ORDER BY created_at DESC LIMIT $2 OFFSET $3`,
        customerID, size, offset,
    )
    if err != nil {
        return OrderListReadModel{}, err
    }
    defer rows.Close()

    var result OrderListReadModel
    for rows.Next() {
        var m OrderReadModel
        rows.Scan(&m.ID, &m.CustomerID, &m.Status, &m.TotalAmount, &m.Currency, &m.ItemCount, &m.CreatedAt)
        result.Orders = append(result.Orders, m)
    }
    result.Page = page
    return result, nil
}
```

**Wiring — hamısını birlikdə istifadə:**

```go
// cmd/api/main.go (simplified)
package main

import (
    "context"
    "log"

    "github.com/yourorg/app/internal/cqrs"
    "github.com/yourorg/app/internal/order"
)

func main() {
    db := connectDB()

    writeRepo := order.NewPostgresWriteRepository(db)
    readRepo := order.NewPostgresReadRepository(db)

    // Command bus setup
    cmdBus := cqrs.NewCommandBus()
    cqrs.Register(cmdBus, order.NewCreateOrderCommandHandler(writeRepo))
    cqrs.Register(cmdBus, order.NewCancelOrderCommandHandler(writeRepo))

    // Query bus setup
    queryBus := cqrs.NewQueryBus()
    cqrs.RegisterQuery(queryBus, order.NewGetOrderQueryHandler(readRepo))

    ctx := context.Background()

    // Command dispatch
    err := cmdBus.Dispatch(ctx, order.CreateOrderCommand{
        OrderID:    "order-001",
        CustomerID: "cust-123",
        Items: []order.OrderItemInput{
            {ProductID: "prod-1", Name: "Laptop", UnitPrice: 150000, Quantity: 1},
        },
    })
    if err != nil {
        log.Fatal(err)
    }

    // Query dispatch
    result, err := cqrs.Dispatch[order.OrderReadModel](queryBus, ctx, order.GetOrderQuery{
        OrderID: "order-001",
    })
    if err != nil {
        log.Fatal(err)
    }
    log.Printf("Order: %+v", result)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Command yaratmaq:**
`UpdateOrderItemQuantityCommand` yazın. Handler-i implement edin: aggregate-i yükləyin, quantity-ni dəyişin, save edin.

**Tapşırıq 2 — Read model:**
`orders_view` cədvəlini SQL-də yaradın (denormalized: order + item count + total). Projection updater yazın: `OrderPlaced` event-i gəldikdə view-u update etsin.

**Tapşırıq 3 — Middleware:**
CommandBus-a middleware əlavə edin: hər command-i log etsin (command name + execution time). `Dispatch()` çağrısına toxunmadan işləsin.

**Tapşırıq 4 — Test:**
`CreateOrderCommandHandler` üçün unit test yazın. In-memory write repository istifadə edin. Uğurlu sifariş + boş items xətasını test edin.

**Tapşırıq 5 — Separation doğrulaması:**
QueryHandler-dən state dəyişdirməyə cəhd edin (write repo-ya call). Sonra bunu aradan qaldırın. Bu ayırmanın niyə vacib olduğunu şərh edin.

## Əlaqəli Mövzular

- `30-ddd-tactical.md` — CQRS-in write side DDD aggregate-lərdən istifadə edir
- `32-event-sourcing.md` — CQRS + Event Sourcing güclü kombinasiya
- `15-event-bus.md` — Command/Query bus-a oxşar pattern
- `31-cqrs.md` — bu fayl
- `26-microservices.md` — microservice-lərdə CQRS tətbiqi
