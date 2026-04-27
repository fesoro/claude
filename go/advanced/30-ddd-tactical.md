# DDD Tactical Patterns (Architect)

## İcmal

Domain-Driven Design (DDD) — iş məntiqi mərkəzdə olan mürəkkəb sistemlər üçün proqramlaşdırma yanaşması. **Tactical patterns** — konkret implementasiya blokları: Entity, Value Object, Aggregate, Repository, Domain Event, Domain Service, Application Service. Go-da bu pattern-lər idiomatic şəkildə ifadə olunur — struct, interface, method receiver vasitəsilə. Əsas qanun: **domain layer heç bir external paketi import etmirmi.**

## Niyə Vacibdir

Mürəkkəb e-commerce, fintech, SaaS sistemlərini qururkən iş məntiqi tez-tez DB layer-ına, HTTP handler-ına yayılır. Xüsusilə Eloquent-dən istifadə edən Laravel developerlər üçün bu problem tanışdır: `User::find(1)->orders()->where(...)->get()` — business logic ORM query-nin içindədir. DDD bu ayrılığı struktural şəkildə tətbiq edir. Nəticə: domain test edilə bilir, business rule-lar aydın görünür, komanda "ubiquitous language" ilə danışır.

## Əsas Anlayışlar

- **Entity** — unikal identifikasiyası var, mutable. `Order`, `User`, `Product`.
- **Value Object** — identifikasiyası yoxdur, immutable, dəyər ilə müqayisə edilir. `Money`, `Address`, `Email`.
- **Aggregate** — birlikdə dəyişən entity-lər qrupu; aggregate root konsistentliyi təmin edir.
- **Aggregate Root** — aggregate-in entry point-i; xaricdən yalnız root-a müraciət edilir.
- **Repository** — bir aggregate üçün bir repository; domain layer-da interface, infra layer-da implementation.
- **Domain Event** — baş vermiş bir fakt. `OrderPlaced`, `PaymentFailed`. Immutable.
- **Domain Service** — bir entity-ə aid olmayan business logic. `PricingService`, `ShippingCalculator`.
- **Application Service** — orchestration; domain objects-ı koordinasiya edir, özü business logic saxlamır.
- **Ubiquitous Language** — domain experti və developer eyni terminologiya ilə danışır; kod bu dili əks etdirir.
- **Invariant** — aggregate-in həmişə doğru olmalı olan business qaydası.

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Fintech: `Account` aggregate, `Money` value object, `TransactionRecorded` domain event
- E-commerce: `Order` aggregate, `OrderItem` entity, `OrderPlaced` event → warehouse sistemi tetiklənir
- SaaS: `Subscription` aggregate, `Plan` value object, `SubscriptionUpgraded` event

**Trade-off-lar:**
- Daha çox kod: hər şey üçün struct, interface, event
- Öyrənmə əyrisi var: komanda "aggregate boundary" anlayışını başa düşməlidir
- Mürəkkəb query-lər üçün read model (CQRS) lazım olur — domain model query üçün yaxşı deyil

**Nə vaxt istifadə etməmək lazımdır:**
- Sadə CRUD: user yaratmaq, silmək, listelemek — DDD overkill
- Reporting ağır sistemlər — DDD write side üçündür, read side CQRS/projections istər
- Kiçik komanda + sıx deadline — tactical pattern-ləri tədricən tətbiq edin

**Ümumi səhvlər:**
- Domain entity-lərinə GORM tag qoymaq: `gorm:"column:..."` — domain xarici paketdən asılı olmamalıdır
- Anemic domain model: yalnız getter/setter, heç bir behavior yoxdur — bu sadəcə data struct-dır
- Application service-də business logic: `if order.Status == "placed" && time.Now().After(...)` — bu domain-ə aiddir
- Aggregate-i çox böyük etmək: aggregate transaction boundary-dir, kiçik saxlayın
- Repository-də bütün aggregate graph-ı yükləmək: lazy load yoxdur, nə lazımdır onu yükləyin

## Nümunələr

### Ümumi Nümunə

Order domain-in tam qurulması:

```
Domain Layer (xarici dependency yoxdur):
  order.go          → Order aggregate root (Entity)
  order_item.go     → OrderItem entity
  money.go          → Money value object
  address.go        → Address value object
  events.go         → Domain events
  order_repo.go     → OrderRepository interface
  pricing_service.go → Domain service

Application Layer:
  order_app_service.go → Application service (orchestration)

Infrastructure Layer:
  postgres_order_repo.go → Repository implementation
```

### Kod Nümunəsi

**Value Object — immutable, dəyər ilə müqayisə:**

```go
// internal/domain/money.go
package domain

import (
    "errors"
    "fmt"
)

// Money — Value Object. Pointer receiver yoxdur: immutable.
type Money struct {
    amount   int64  // sentlərlə saxlanır (float point problemi yoxdur)
    currency string
}

func NewMoney(amount int64, currency string) (Money, error) {
    if amount < 0 {
        return Money{}, errors.New("money amount cannot be negative")
    }
    if currency == "" {
        return Money{}, errors.New("currency is required")
    }
    return Money{amount: amount, currency: currency}, nil
}

func (m Money) Amount() int64      { return m.amount }
func (m Money) Currency() string   { return m.currency }
func (m Money) String() string     { return fmt.Sprintf("%d %s", m.amount, m.currency) }

// Value Object müqayisəsi — dəyər ilə
func (m Money) Equals(other Money) bool {
    return m.amount == other.amount && m.currency == other.currency
}

func (m Money) Add(other Money) (Money, error) {
    if m.currency != other.currency {
        return Money{}, fmt.Errorf("cannot add %s and %s", m.currency, other.currency)
    }
    return Money{amount: m.amount + other.amount, currency: m.currency}, nil
}

func (m Money) Multiply(factor int64) Money {
    return Money{amount: m.amount * factor, currency: m.currency}
}
```

**Domain Events:**

```go
// internal/domain/events.go
package domain

import "time"

// DomainEvent — bütün event-lərin base interface-i
type DomainEvent interface {
    EventName() string
    OccurredAt() time.Time
}

// OrderPlaced event
type OrderPlaced struct {
    OrderID     OrderID
    CustomerID  string
    TotalAmount Money
    occurredAt  time.Time
}

func NewOrderPlaced(orderID OrderID, customerID string, total Money) OrderPlaced {
    return OrderPlaced{
        OrderID:     orderID,
        CustomerID:  customerID,
        TotalAmount: total,
        occurredAt:  time.Now(),
    }
}

func (e OrderPlaced) EventName() string    { return "order.placed" }
func (e OrderPlaced) OccurredAt() time.Time { return e.occurredAt }

// OrderCancelled event
type OrderCancelled struct {
    OrderID    OrderID
    Reason     string
    occurredAt time.Time
}

func NewOrderCancelled(orderID OrderID, reason string) OrderCancelled {
    return OrderCancelled{
        OrderID:    orderID,
        Reason:     reason,
        occurredAt: time.Now(),
    }
}

func (e OrderCancelled) EventName() string    { return "order.cancelled" }
func (e OrderCancelled) OccurredAt() time.Time { return e.occurredAt }
```

**Entity — OrderItem:**

```go
// internal/domain/order_item.go
package domain

type OrderItemID string

type OrderItem struct {
    ID        OrderItemID
    ProductID string
    Name      string
    UnitPrice Money
    Quantity  int
}

func NewOrderItem(id OrderItemID, productID, name string, price Money, qty int) (*OrderItem, error) {
    if qty <= 0 {
        return nil, errorf("quantity must be positive, got %d", qty)
    }
    return &OrderItem{
        ID:        id,
        ProductID: productID,
        Name:      name,
        UnitPrice: price,
        Quantity:  qty,
    }, nil
}

func (i *OrderItem) Subtotal() Money {
    return i.UnitPrice.Multiply(int64(i.Quantity))
}

func errorf(format string, args ...any) error {
    return fmt.Errorf(format, args...)
}
```

**Aggregate Root — Order:**

```go
// internal/domain/order.go
package domain

import (
    "errors"
    "fmt"
    "time"
)

type OrderID string

type OrderStatus string

const (
    OrderStatusDraft     OrderStatus = "draft"
    OrderStatusPlaced    OrderStatus = "placed"
    OrderStatusShipped   OrderStatus = "shipped"
    OrderStatusCancelled OrderStatus = "cancelled"
)

// Order — Aggregate Root
type Order struct {
    id         OrderID
    customerID string
    items      []*OrderItem
    status     OrderStatus
    createdAt  time.Time

    // Uncommitted domain events — publish olunmamış event-lər
    events []DomainEvent
}

func NewOrder(id OrderID, customerID string) (*Order, error) {
    if customerID == "" {
        return nil, errors.New("customerID is required")
    }
    return &Order{
        id:         id,
        customerID: customerID,
        status:     OrderStatusDraft,
        items:      make([]*OrderItem, 0),
        events:     make([]DomainEvent, 0),
        createdAt:  time.Now(),
    }, nil
}

// Getters — field-lər private, xaricdən yalnız getter vasitəsilə
func (o *Order) ID() OrderID         { return o.id }
func (o *Order) CustomerID() string  { return o.customerID }
func (o *Order) Status() OrderStatus { return o.status }
func (o *Order) Items() []*OrderItem { return o.items }
func (o *Order) CreatedAt() time.Time { return o.createdAt }

// Domain Events
func (o *Order) Events() []DomainEvent    { return o.events }
func (o *Order) ClearEvents()             { o.events = make([]DomainEvent, 0) }
func (o *Order) record(e DomainEvent)     { o.events = append(o.events, e) }

// AddItem — Aggregate invariant tətbiq edir
func (o *Order) AddItem(item *OrderItem) error {
    if o.status != OrderStatusDraft {
        return fmt.Errorf("cannot add items to %s order", o.status)
    }

    // Eyni product varsa quantity artır
    for _, existing := range o.items {
        if existing.ProductID == item.ProductID {
            existing.Quantity += item.Quantity
            return nil
        }
    }

    o.items = append(o.items, item)
    return nil
}

// Place — status dəyişir, invariant yoxlayır, event yayır
func (o *Order) Place() error {
    if o.status != OrderStatusDraft {
        return fmt.Errorf("order is already %s", o.status)
    }
    if len(o.items) == 0 {
        return errors.New("cannot place empty order")
    }

    o.status = OrderStatusPlaced

    total, _ := o.Total()
    o.record(NewOrderPlaced(o.id, o.customerID, total))

    return nil
}

// Cancel — business rule: yalnız draft və ya placed order cancel edilə bilər
func (o *Order) Cancel(reason string) error {
    if o.status == OrderStatusShipped {
        return errors.New("shipped orders cannot be cancelled")
    }
    if o.status == OrderStatusCancelled {
        return errors.New("order is already cancelled")
    }

    o.status = OrderStatusCancelled
    o.record(NewOrderCancelled(o.id, reason))

    return nil
}

// Total — Value Object qaytarır
func (o *Order) Total() (Money, error) {
    if len(o.items) == 0 {
        return NewMoney(0, "USD")
    }

    total, err := NewMoney(0, "USD")
    if err != nil {
        return Money{}, err
    }

    for _, item := range o.items {
        subtotal := item.Subtotal()
        total, err = total.Add(subtotal)
        if err != nil {
            return Money{}, err
        }
    }
    return total, nil
}
```

**Repository Interface — domain layer-da:**

```go
// internal/domain/order_repo.go
package domain

import "context"

// OrderRepository — yalnız interface; implementation infra layer-dadır
type OrderRepository interface {
    Save(ctx context.Context, order *Order) error
    FindByID(ctx context.Context, id OrderID) (*Order, error)
    FindByCustomerID(ctx context.Context, customerID string) ([]*Order, error)
}
```

**Domain Service — bir entity-ə aid olmayan business logic:**

```go
// internal/domain/pricing_service.go
package domain

// PricingService — Domain Service
// Discount hesabı hər iki entity-yə aid olduğu üçün ayrıca service
type PricingService struct{}

func NewPricingService() *PricingService {
    return &PricingService{}
}

// ApplyLoyaltyDiscount — business rule: 10+ sifarişdə 10% endirim
func (s *PricingService) ApplyLoyaltyDiscount(order *Order, customerOrderCount int) (Money, error) {
    total, err := order.Total()
    if err != nil {
        return Money{}, err
    }

    if customerOrderCount < 10 {
        return total, nil
    }

    // 10% discount
    discountAmount := total.Amount() / 10
    discounted, err := NewMoney(total.Amount()-discountAmount, total.Currency())
    if err != nil {
        return Money{}, err
    }

    return discounted, nil
}
```

**Application Service — orchestration, business logic yoxdur:**

```go
// internal/app/order_app_service.go
package app

import (
    "context"
    "fmt"

    "github.com/yourorg/app/internal/domain"
    "github.com/google/uuid"
)

type CreateOrderRequest struct {
    CustomerID string
    Items      []OrderItemRequest
}

type OrderItemRequest struct {
    ProductID string
    Name      string
    UnitPrice int64
    Quantity  int
}

type OrderApplicationService struct {
    orderRepo      domain.OrderRepository // interface-ə depend edir
    pricingService *domain.PricingService
    eventPublisher EventPublisher
}

type EventPublisher interface {
    Publish(ctx context.Context, events []domain.DomainEvent) error
}

func NewOrderApplicationService(
    repo domain.OrderRepository,
    pricing *domain.PricingService,
    publisher EventPublisher,
) *OrderApplicationService {
    return &OrderApplicationService{
        orderRepo:      repo,
        pricingService: pricing,
        eventPublisher: publisher,
    }
}

func (s *OrderApplicationService) CreateOrder(
    ctx context.Context,
    req CreateOrderRequest,
) (*domain.Order, error) {
    order, err := domain.NewOrder(domain.OrderID(uuid.NewString()), req.CustomerID)
    if err != nil {
        return nil, fmt.Errorf("creating order: %w", err)
    }

    for _, itemReq := range req.Items {
        price, err := domain.NewMoney(itemReq.UnitPrice, "USD")
        if err != nil {
            return nil, fmt.Errorf("invalid price: %w", err)
        }

        item, err := domain.NewOrderItem(
            domain.OrderItemID(uuid.NewString()),
            itemReq.ProductID,
            itemReq.Name,
            price,
            itemReq.Quantity,
        )
        if err != nil {
            return nil, fmt.Errorf("creating order item: %w", err)
        }

        if err := order.AddItem(item); err != nil {
            return nil, fmt.Errorf("adding item: %w", err)
        }
    }

    if err := order.Place(); err != nil {
        return nil, fmt.Errorf("placing order: %w", err)
    }

    if err := s.orderRepo.Save(ctx, order); err != nil {
        return nil, fmt.Errorf("saving order: %w", err)
    }

    // Domain event-ləri publish et
    if err := s.eventPublisher.Publish(ctx, order.Events()); err != nil {
        return nil, fmt.Errorf("publishing events: %w", err)
    }
    order.ClearEvents()

    return order, nil
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Value Object:**
`Email`, `PhoneNumber`, `Address` value object-ları yazın. Hər biri validation içersin. `Equals()` metodu olsun. Pointer receiver istifadə etməyin (immutable).

**Tapşırıq 2 — Aggregate invariant:**
`Order`-a yeni rule əlavə edin: maksimum 10 item əlavə etmək olar. `AddItem()` 11-ci itemdə xəta qaytarsın. Unit test yazın.

**Tapşırıq 3 — Domain Event handler:**
`OrderPlaced` event-ini dinləyən `WarehouseService` yazın (in-memory). Event publish olanda warehouse-da item rezerv olunsun. Domain event-lər domain layer-da, handler infra layer-da olsun.

**Tapşırıq 4 — Anemic model refactoring:**
```go
// Bu anemic model — refactor edin
type Order struct {
    Status string
    Items  []OrderItem
}
func (o *Order) SetStatus(s string) { o.Status = s }
func (o *Order) GetStatus() string  { return o.Status }
```
Business rule-ları əlavə edin, behavior-u domain-ə köçürün.

**Tapşırıq 5 — Repository test:**
`InMemoryOrderRepository` yazın. `OrderApplicationService`-i real DB olmadan test edin. `CreateOrder` → `FindByID` → `Cancel` flow-unu test edin.

## Əlaqəli Mövzular

- `29-hexagonal-architecture.md` — Domain layer-ı hexagonal structure ilə birləşdirmək
- `31-cqrs.md` — DDD write side üçün CQRS pattern
- `32-event-sourcing.md` — Domain event-lərdən tam event sourcing-ə keçid
- `28-solid-principles.md` — DDD SOLID prinsipləri tətbiq edir
- `15-event-bus.md` — Domain event-ləri publish etmək üçün event bus
