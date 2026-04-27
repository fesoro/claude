# Saga Pattern (Architect)

## İcmal

Saga Pattern, paylanmış sistemlərdə (distributed systems) çoxlu microservice-lər arasında data consistency-ni təmin etmək üçün istifadə olunan bir design pattern-dir. Ənənəvi ACID transaction-larını bütün microservice-lər üzərindən keçirmək mümkün olmadığı halda, Saga Pattern hər microservice-in öz lokal transaction-ını yerinə yetirməsini və uğursuzluq zamanı compensation (kompensasiya) əməliyyatı icra etməsini təmin edir.

Sadə desək: əgər bir addım uğursuz olarsa, əvvəlki uğurlu addımlar geri qaytarılır — lakin standart database rollback ilə yox, xüsusi "undo" əməliyyatları vasitəsilə.

## Niyə Vacibdir

Microservice arxitekturasında hər servis öz database-inə sahibdir (Database per Service pattern). Bu o deməkdir ki:

- **2PC (Two-Phase Commit)** işləmir — hər servis fərqli database texnologiyasından istifadə edə bilər
- **Distributed locks** performansı məhv edir
- **Network failures** istənilən zaman baş verə bilər

Real nümunə: E-commerce sifariş prosesi:
1. Order Service → sifariş yarat
2. Inventory Service → məhsulu rezerv et
3. Payment Service → ödəniş al
4. Notification Service → təsdiq göndər

Əgər Payment uğursuz olarsa, Inventory-də rezerv edilmiş məhsul azad edilməlidir. Bu "geri qaytarma" prosesi Saga-nın işidir.

## Əsas Anlayışlar

### Saga Növləri

**1. Choreography-based Saga**
Mərkəzi koordinator yoxdur. Hər servis event publish edir, digərləri subscribe olur.

```
OrderService        PaymentService       InventoryService
     |                    |                    |
     |-- OrderCreated -->  |                    |
     |                    |-- PaymentProcessed->|
     |                    |                    |-- InventoryReserved -->
     |                    |                    |
     |  (Uğursuzluq halında)
     |                    |-- PaymentFailed --> |
     |                    |                    |-- InventoryReleased -->
     |<-- OrderCancelled--|                    |
```

**2. Orchestration-based Saga**
Mərkəzi Saga Orchestrator hər addımı idarə edir. O, servislərə command göndərir və cavabları izləyir.

```
                    SagaOrchestrator
                         |
           +-------------+-------------+
           |             |             |
    ReserveInventory  ProcessPayment  ConfirmOrder
           |             |             |
     InventoryService PaymentService OrderService
```

### Compensation Transaction

Hər forward step üçün bir compensation step olmalıdır:

| Forward Step       | Compensation Step     |
|--------------------|-----------------------|
| ReserveInventory   | ReleaseInventory      |
| ProcessPayment     | RefundPayment         |
| ConfirmOrder       | CancelOrder           |

### Idempotency

Hər step — həm forward, həm compensation — **idempotent** olmalıdır. Yəni eyni əməliyyat dəfələrlə çağırılsa belə eyni nəticə verməlidir. Bu network retry-lar zamanı kritikdir.

## Praktik Baxış

### Choreography vs Orchestration

| Xüsusiyyət          | Choreography            | Orchestration            |
|---------------------|-------------------------|--------------------------|
| Coupling            | Loose                   | Central dependency       |
| Visibility          | Çətin izləmək           | Asan izləmək             |
| Testing             | Kompleks                | Daha sadə                |
| Failure handling    | Distributed             | Mərkəzləşdirilmiş        |
| Scalability         | Yaxşı                   | Orchestrator bottleneck  |

### Nə zaman istifadə etməli

- Microservice-lər arası multi-step business transaction lazım olanda
- 2PC mümkün olmadıqda (fərqli DB texnologiyaları)
- Eventual consistency qəbul edilə biləndə

### Anti-pattern-lər

- Saga-nı monolith-dəki eyni DB üzərindən çağırmaq (sadəcə regular transaction istifadə et)
- Compensation-sız step yaratmaq
- Non-idempotent step-lər
- Saga state-ini yalnız yaddaşda saxlamaq (restart-dan sonra itirilir)

## Nümunələr

### Ümumi Nümunə

CreateOrderSaga aşağıdakı addımları icra edir:

```
Step 1: ReserveInventory
  → Uğurlu: Step 2-yə keç
  → Uğursuz: Saga bitir (heç nə kompensasiya edilmir)

Step 2: ProcessPayment
  → Uğurlu: Step 3-ə keç
  → Uğursuz: Compensate Step 1 (ReleaseInventory)

Step 3: ConfirmOrder
  → Uğurlu: Saga tamamlandı
  → Uğursuz: Compensate Step 2 (RefundPayment), Step 1 (ReleaseInventory)
```

### Kod Nümunəsi

**Orchestration-based Saga — Go implementation:**

```go
package saga

import (
    "context"
    "fmt"
    "log"
)

// SagaStep hər bir addımın interface-i
type SagaStep interface {
    Name() string
    Execute(ctx context.Context, data *SagaData) error
    Compensate(ctx context.Context, data *SagaData) error
}

// SagaData saga boyunca paylaşılan state
type SagaData struct {
    OrderID     string
    UserID      string
    ProductID   string
    Quantity    int
    Amount      float64
    PaymentID   string // ProcessPayment zamanı doldurulur
    ReservationID string // ReserveInventory zamanı doldurulur
}

// SagaOrchestrator saga-nı idarə edir
type SagaOrchestrator struct {
    steps []SagaStep
}

func NewSagaOrchestrator(steps ...SagaStep) *SagaOrchestrator {
    return &SagaOrchestrator{steps: steps}
}

// Execute bütün addımları icra edir; uğursuzluq olduqda kompensasiya edir
func (o *SagaOrchestrator) Execute(ctx context.Context, data *SagaData) error {
    executedSteps := make([]SagaStep, 0, len(o.steps))

    for _, step := range o.steps {
        log.Printf("Saga step executing: %s", step.Name())

        if err := step.Execute(ctx, data); err != nil {
            log.Printf("Saga step failed: %s, error: %v", step.Name(), err)

            // Kompensasiya — tərsinə qaydada
            o.compensate(ctx, data, executedSteps)
            return fmt.Errorf("saga failed at step %s: %w", step.Name(), err)
        }

        executedSteps = append(executedSteps, step)
        log.Printf("Saga step completed: %s", step.Name())
    }

    log.Printf("Saga completed successfully for order: %s", data.OrderID)
    return nil
}

// compensate uğurlu addımları tərsinə qaydada kompensasiya edir
func (o *SagaOrchestrator) compensate(ctx context.Context, data *SagaData, executedSteps []SagaStep) {
    for i := len(executedSteps) - 1; i >= 0; i-- {
        step := executedSteps[i]
        log.Printf("Compensating step: %s", step.Name())

        if err := step.Compensate(ctx, data); err != nil {
            // Kompensasiya uğursuz olsa — mütləq log et, alert göndər
            log.Printf("CRITICAL: compensation failed for step %s: %v", step.Name(), err)
            // Production-da: dead letter queue, manual intervention alert
        }
    }
}

// --- Konkret addımlar ---

// ReserveInventoryStep inventarı rezerv edir
type ReserveInventoryStep struct {
    inventoryService InventoryService
}

func (s *ReserveInventoryStep) Name() string { return "ReserveInventory" }

func (s *ReserveInventoryStep) Execute(ctx context.Context, data *SagaData) error {
    reservationID, err := s.inventoryService.Reserve(ctx, data.ProductID, data.Quantity)
    if err != nil {
        return fmt.Errorf("inventory reservation failed: %w", err)
    }
    data.ReservationID = reservationID // state-i saxla
    return nil
}

func (s *ReserveInventoryStep) Compensate(ctx context.Context, data *SagaData) error {
    if data.ReservationID == "" {
        return nil // rezerv edilməyibsə heç nə etmə
    }
    return s.inventoryService.Release(ctx, data.ReservationID)
}

// ProcessPaymentStep ödənişi icra edir
type ProcessPaymentStep struct {
    paymentService PaymentService
}

func (s *ProcessPaymentStep) Name() string { return "ProcessPayment" }

func (s *ProcessPaymentStep) Execute(ctx context.Context, data *SagaData) error {
    paymentID, err := s.paymentService.Charge(ctx, data.UserID, data.Amount)
    if err != nil {
        return fmt.Errorf("payment failed: %w", err)
    }
    data.PaymentID = paymentID
    return nil
}

func (s *ProcessPaymentStep) Compensate(ctx context.Context, data *SagaData) error {
    if data.PaymentID == "" {
        return nil
    }
    return s.paymentService.Refund(ctx, data.PaymentID)
}

// ConfirmOrderStep sifarişi təsdiq edir
type ConfirmOrderStep struct {
    orderService OrderService
}

func (s *ConfirmOrderStep) Name() string { return "ConfirmOrder" }

func (s *ConfirmOrderStep) Execute(ctx context.Context, data *SagaData) error {
    return s.orderService.Confirm(ctx, data.OrderID)
}

func (s *ConfirmOrderStep) Compensate(ctx context.Context, data *SagaData) error {
    return s.orderService.Cancel(ctx, data.OrderID)
}

// --- Service interfaces ---

type InventoryService interface {
    Reserve(ctx context.Context, productID string, qty int) (reservationID string, err error)
    Release(ctx context.Context, reservationID string) error
}

type PaymentService interface {
    Charge(ctx context.Context, userID string, amount float64) (paymentID string, err error)
    Refund(ctx context.Context, paymentID string) error
}

type OrderService interface {
    Confirm(ctx context.Context, orderID string) error
    Cancel(ctx context.Context, orderID string) error
}
```

**İstifadəsi:**

```go
func CreateOrderSaga(
    inventorySvc InventoryService,
    paymentSvc PaymentService,
    orderSvc OrderService,
) *SagaOrchestrator {
    return NewSagaOrchestrator(
        &ReserveInventoryStep{inventoryService: inventorySvc},
        &ProcessPaymentStep{paymentService: paymentSvc},
        &ConfirmOrderStep{orderService: orderSvc},
    )
}

// Handler-də istifadə
func (h *OrderHandler) PlaceOrder(ctx context.Context, req PlaceOrderRequest) error {
    data := &SagaData{
        OrderID:   generateOrderID(),
        UserID:    req.UserID,
        ProductID: req.ProductID,
        Quantity:  req.Quantity,
        Amount:    req.Amount,
    }

    saga := CreateOrderSaga(h.inventorySvc, h.paymentSvc, h.orderSvc)
    return saga.Execute(ctx, data)
}
```

**Choreography — Kafka events ilə:**

```go
// OrderService event publish edir
type OrderCreatedEvent struct {
    OrderID   string  `json:"order_id"`
    UserID    string  `json:"user_id"`
    ProductID string  `json:"product_id"`
    Quantity  int     `json:"quantity"`
    Amount    float64 `json:"amount"`
}

func (s *OrderService) CreateOrder(ctx context.Context, req CreateOrderRequest) error {
    order := &Order{ID: generateID(), Status: "pending", ...}

    if err := s.repo.Save(ctx, order); err != nil {
        return err
    }

    // Event publish et — digər servisler dinləyir
    event := OrderCreatedEvent{OrderID: order.ID, ...}
    return s.publisher.Publish(ctx, "order.created", event)
}

// PaymentService OrderCreated-i dinləyir
func (s *PaymentService) HandleOrderCreated(ctx context.Context, event OrderCreatedEvent) error {
    paymentID, err := s.processPayment(ctx, event.UserID, event.Amount)
    if err != nil {
        // Uğursuzluq — event publish et
        return s.publisher.Publish(ctx, "payment.failed", PaymentFailedEvent{
            OrderID: event.OrderID,
            Reason:  err.Error(),
        })
    }

    return s.publisher.Publish(ctx, "payment.processed", PaymentProcessedEvent{
        OrderID:   event.OrderID,
        PaymentID: paymentID,
    })
}

// InventoryService PaymentFailed-i dinləyir — kompensasiya edir
func (s *InventoryService) HandlePaymentFailed(ctx context.Context, event PaymentFailedEvent) error {
    return s.releaseReservation(ctx, event.OrderID)
}
```

**Idempotency nümunəsi:**

```go
// Hər step idempotency key ilə işləməlidir
func (s *PaymentService) Charge(ctx context.Context, userID string, amount float64) (string, error) {
    idempotencyKey := fmt.Sprintf("order-%s-payment", userID)

    // Əvvəlcə yoxla: bu ödəniş artıq icra edilib?
    existing, err := s.repo.FindByIdempotencyKey(ctx, idempotencyKey)
    if err == nil && existing != nil {
        return existing.PaymentID, nil // eyni nəticəni qaytar
    }

    // Yeni ödəniş icra et
    paymentID, err := s.gateway.Charge(ctx, userID, amount)
    if err != nil {
        return "", err
    }

    // Idempotency key ilə saxla
    s.repo.SaveWithKey(ctx, idempotencyKey, paymentID)
    return paymentID, nil
}
```

## Praktik Tapşırıqlar

1. **Basic Orchestration**: `CreateOrderSaga`-nı implement et. OrderService, PaymentService, InventoryService-in mock implementasiyasını yaz. Hər addımın uğursuzluq ssenarisini test et.

2. **Saga State Persistence**: Saga state-ini database-də saxla. Restart-dan sonra yarımçıq saga-ları bərpa et. `SagaRepository` interface-ini yaz.

3. **Choreography with Kafka**: Kafka-dan istifadə edərək choreography-based saga implement et. OrderCreated, PaymentProcessed, PaymentFailed, InventoryReserved event-lərini işlə. Hər consumer idempotent olmalıdır.

4. **Timeout Handling**: Hər saga addımına timeout əlavə et. Timeout keçdikdə kompensasiya başlat. Context-dən istifadə et.

5. **Saga Monitoring**: Hər saga-nın state-ini, hansı addımda dayandığını, compensation-ların nə vaxt çağırıldığını log et. Prometheus metrics əlavə et.

## Əlaqəli Mövzular

- `34-outbox-pattern.md` — Saga event-lərini atomik şəkildə publish etmək üçün
- `36-database-per-service.md` — Hər servisin öz database-i olduqda distributed transaction problemi
- `26-microservices.md` — Microservice arxitekturası əsasları
- `18-circuit-breaker-and-retry.md` — Servis çağırışlarında uğursuzluğu idarə etmək
- `15-event-bus.md` — Event-driven communication
