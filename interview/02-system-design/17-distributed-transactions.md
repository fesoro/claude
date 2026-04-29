# Distributed Transactions (Architect ⭐⭐⭐⭐⭐)

## İcmal
Distributed transactions, bir neçə müstəqil service və ya database üzərindəki əməliyyatların atomik şəkildə tamamlanmasını təmin etmək problemidir. Monolitik sistemdəki `BEGIN/COMMIT/ROLLBACK` distributed mühitdə işləmir. 2PC, Saga, TCC — hər birinin fərqli consistency guarantee, latency, complexity trade-off-u var. Bu mövzu Architect-level mühəndislərin ən dərindən bilməli olduğu patternlərdəndir.

## Niyə Vacibdir
Amazon, Uber, Airbnb — hər e-commerce, ride-sharing, booking sistemi cross-service transactional consistency problemi yaşayır. "Order yaradıldı, amma inventory azalmadı" ya da "Payment alındı, amma order silindi" — bunlar production-da real baş verən problemlərdir. Architect bu problemləri distributed transaction pattern-ları ilə həll edir.

## Əsas Anlayışlar

### 1. Distributed Transaction Problemi
```
Monolith (asan):
  BEGIN;
    UPDATE inventory SET stock = stock - 1 WHERE id = :item;
    INSERT INTO orders (item_id, user_id) VALUES (...);
    UPDATE accounts SET balance = balance - :price WHERE id = :user;
  COMMIT;

Microservices (çətin):
  Inventory Service  → ayrı database
  Order Service      → ayrı database
  Payment Service    → ayrı database

  Əgər payment fail olsa → order-i və inventory-ni geri al
  Əgər inventory fail olsa → payment-i geri al
  Şəbəkə kəsilərsə ne olur?
```

### 2. Two-Phase Commit (2PC)
```
Coordinator → Phase 1 (Prepare):
  → Inventory: "1 item azaltmağa hazır mısın?"
  → Payment: "100$ almağa hazır mısın?"
  → Order: "Sifariş yaratmağa hazır mısın?"

Hamısı "OK" → Phase 2 (Commit):
  → Inventory: "Commit!"
  → Payment: "Commit!"
  → Order: "Commit!"

Biri "FAIL" → Phase 2 (Rollback):
  → Inventory: "Rollback!"
  → Payment: "Rollback!"
  → Order: "Rollback!"
```

**2PC Problemləri:**
```
Blocking protocol:
  Coordinator crash → participants locked (waiting for phase 2)
  → Dead lock until coordinator recovers

Performance:
  2 round-trips + lock holding = high latency
  Bütün service-lər birlikdə lock olur

Availability:
  Hər participant available olmalıdır
  1 participant unreachable → whole transaction stalls

Use case:
  Same organization, low latency, high consistency needed
  NOT for internet-scale, NOT for cross-company
  Example: XA protocol in enterprise systems
```

### 3. Saga Pattern (Preferred for Microservices)
```
Saga = Distributed transaction = sequence of local transactions
Hər step uğurlu → növbəti step
Hər step fail → compensating transactions (reverse previous steps)

Order saga:
  Step 1: Create order       (compensate: cancel order)
  Step 2: Reserve inventory  (compensate: release inventory)
  Step 3: Charge payment     (compensate: refund payment)
  Step 4: Ship notification  (compensate: cancel shipping)

Compensating transaction:
  Semantik geri qaytarma — fiziki rollback deyil
  "100$ refund" = yeni transaction, nəinki DB rollback
```

### 4. Choreography-based Saga
```
Hər service event publish edir, digəri subscribe edir:

Order Service:
  1. Order yaradır
  2. "order.created" event publish edir

Inventory Service:
  "order.created" dinləyir
  3. Inventory azaldır
  4. "inventory.reserved" ya "inventory.failed" publish edir

Payment Service:
  "inventory.reserved" dinləyir
  5. Ödəniş alır
  6. "payment.completed" ya "payment.failed" publish edir

Shipping Service:
  "payment.completed" dinləyir
  7. Shipping schedule edir
  8. "order.shipped" publish edir

Compensating events:
  "payment.failed" → Inventory Service listens:
  9. Inventory-i release edir → "inventory.released"
  "inventory.released" → Order Service listens:
  10. Order-i cancel edir → "order.cancelled"
```

**Choreography pros/cons:**
```
Pros:
  - Loose coupling
  - No coordinator SPOF
  - Simple individual services

Cons:
  - Hard to track overall saga state
  - Event chain debugging çətin
  - Business logic services arasında yayılır
  - Cyclic dependencies riski
```

### 5. Orchestration-based Saga
```
Saga Orchestrator bütün saga-nı idarə edir:

Order Orchestrator:
  1. → Inventory Service: "reserve item 123" (command)
  2. ← "reserved" (response)
  3. → Payment Service: "charge $100" (command)
  4. ← "payment_id: pay_abc" (response)
  5. → Shipping Service: "schedule delivery" (command)
  6. ← "shipped" (response)
  7. Complete order saga

Failure handling:
  Step 4 fails:
  → Payment: "charge $100" → FAIL
  Compensate:
  → Inventory: "release item 123" (compensate step 1)
  → Order: mark as FAILED
```

**Orchestration pros/cons:**
```
Pros:
  - Single place to see saga flow
  - Easier debugging/monitoring
  - Complex business logic centralized

Cons:
  - Orchestrator = potential bottleneck
  - More coupling to orchestrator
  - Orchestrator crash handling (must be idempotent)
```

### 6. Saga State Machine
```
Order Saga States:
  PENDING
    → inventory reserved → INVENTORY_RESERVED
    → inventory failed   → INVENTORY_FAILED (compensated)
  INVENTORY_RESERVED
    → payment charged    → PAYMENT_CHARGED
    → payment failed     → PAYMENT_FAILED → (compensate inventory)
  PAYMENT_CHARGED
    → shipped            → COMPLETED
    → shipping failed    → SHIPPING_FAILED → (compensate payment + inventory)
  COMPLETED / FAILED / COMPENSATING / COMPENSATED

DB schema:
  saga_id     UUID
  saga_type   VARCHAR (order, refund, transfer)
  state       VARCHAR
  payload     JSONB
  created_at  TIMESTAMP
  updated_at  TIMESTAMP

Saga log:
  saga_id, step, status, input, output, timestamp
```

### 7. TCC (Try-Confirm-Cancel)
```
2PC-ye oxşar amma blocking deyil:

Phase 1 (Try):
  Inventory: Reserve item (tentative reserve, not deducted)
  Payment: Authorize $100 (hold on card, not charged)

  Try operations:
  - Soft reserve → soft lock
  - Return: reservation_token

Phase 2A (Confirm):
  Inventory: Confirm reservation (now deducted)
  Payment: Capture authorization (now charged)

Phase 2B (Cancel):
  Inventory: Release reservation
  Payment: Void authorization

Pros:
  Non-blocking, no coordinator lock
  Fast phase 1 (just reservation)
  
Cons:
  Compensatable operations lazımdır
  Timeout handling (reservation expires)
  Implementation complexity

Use case:
  Booking systems (hotel/flight reservation)
  Financial pre-authorization
```

### 8. Outbox Pattern for Reliable Event Publishing
```
Problem in Saga:
  Local DB commit + Event publish = 2 operations
  DB commit OK, event publish fail → saga stalls

Solution: Transactional Outbox
  DB commit → outbox table-a da yaz (same transaction)
  Separate relay process outbox → Kafka publish

  (see 25-outbox-pattern.md for details)
```

### 9. Idempotency in Saga Steps
```
Saga retry (orchestrator restart):
  Step 2 tekrar çağırılırsa?
  → Inventory: "reserve item 123" (already reserved!)
  → Idempotency key lazımdır: "saga_abc:step_2"

  Each step: check if already processed
  If processed: return cached result, don't re-process

  Saga step key: "saga:{saga_id}:step:{step_number}"
```

### 10. Real-World Saga Frameworks

**Netflix Conductor:**
- JSON-based workflow definition
- Saga orchestration
- REST API for worker registration

**Apache Camel:**
- Integration framework with saga support
- Multiple transport (Kafka, JMS, HTTP)

**AWS Step Functions:**
- Serverless saga orchestration
- Visual state machine
- Automatic retries, error handling

**Temporal.io:**
- Durable execution engine
- Code-as-workflow (Go, Java, Python)
- Automatic retry, state persistence
- Netflix, Uber, Doordash istifadə edir

```go
// Temporal workflow (Go)
func OrderWorkflow(ctx workflow.Context, order Order) error {
    // Step 1: Reserve inventory
    var invResult InventoryResult
    err := workflow.ExecuteActivity(ctx, ReserveInventory, order.ItemID).Get(ctx, &invResult)
    if err != nil {
        return err
    }
    
    // Step 2: Charge payment
    var payResult PaymentResult
    err = workflow.ExecuteActivity(ctx, ChargePayment, order.Amount).Get(ctx, &payResult)
    if err != nil {
        // Compensate: release inventory
        workflow.ExecuteActivity(ctx, ReleaseInventory, invResult.ReservationID)
        return err
    }
    
    return nil
}
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Problemi motivasiya et: "cross-service atomicity necə sağlanır?"
2. 2PC-ni izah et, limitations qeyd et
3. Saga pattern-ını seç (çox güman ki, doğru cavab)
4. Choreography vs Orchestration fərqini müzakirə et
5. Compensating transactions-ı izah et
6. Idempotency-i qeyd et (saga retry-larda kritik)

### Ümumi Namizəd Səhvləri
- "Distributed transaction yoxdur, sadəcə eventual consistency istifadə edirəm" — həll deyil
- Compensating transaction-ın fiziki rollback olmadığını bilməmək
- Saga idempotency-ini unutmaq
- Outbox pattern-sız saga design etmək (unreliable event publishing)
- Choreography-nin izlənilməsinin çətinliyini bilməmək

### Senior vs Architect Fərqi
**Senior**: Saga pattern anlayır, choreography vs orchestration seçimi edir, compensating transactions yazır.

**Architect**: Saga framework seçimi edir (Temporal vs Step Functions), saga state machine-i formal olaraq dizayn edir, partial failure semantics-i sənədləşdirir, business process-i saga-ya map edir, saga-nın long-running olduğu hallarda (days, weeks) human task integration edir, saga monitoring + alerting dizayn edir, saga anti-patterns (distributed monolith, chatty saga) müəyyən edir.

## Nümunələr

### Tipik Interview Sualı
"Design the transaction flow for an e-commerce order: inventory must be reserved, payment charged, and shipping initiated — all or nothing."

### Güclü Cavab
```
E-commerce order saga (Orchestration-based):

Architecture:
  Order Orchestrator: Temporal.io workflow
  Services: Inventory, Payment, Shipping (each has own DB)

Saga definition:
  OrderWorkflow(orderId, userId, itemId, amount)

  Step 1: ReserveInventory(itemId, quantity)
    - Inventory Service: tentative reserve (TCC Try phase)
    - reservation_token returned
    - Idempotency key: "order:{orderId}:reserve_inventory"

  Step 2: ChargePayment(userId, amount, orderId)
    - Payment Service: pre-authorization
    - payment_token returned
    - Idempotency key: "order:{orderId}:charge_payment"

  Step 3: ConfirmInventory(reservation_token)
    - Inventory Service: confirm TCC
    
  Step 4: CapturePayment(payment_token)
    - Payment Service: capture pre-auth

  Step 5: NotifyShipping(orderId)
    - Shipping Service: queue delivery

Compensations:
  Step 5 fail → nothing to compensate (retry)
  Step 4 fail → ReleaseInventory(reservation_token)
  Step 3 fail → VoidPayment(payment_token)
  Step 2 fail → ReleaseInventory(reservation_token)

State machine:
  PENDING → INVENTORY_RESERVED → PAYMENT_AUTHORIZED
           → INVENTORY_CONFIRMED → PAYMENT_CAPTURED
           → SHIPPING_NOTIFIED → COMPLETED
  
  Failures → COMPENSATING → COMPENSATED/FAILED

Monitoring:
  - Saga duration histogram
  - Compensation rate per step (high compensation = business problem)
  - Stuck sagas (> 5 min in one state) → alert
  - Step failure rate (payment fail > 5% → investigate)

Idempotency:
  Each step has unique idempotency key
  Temporal: automatic workflow ID deduplication
  Worker restart safe: workflow re-runs from last checkpoint
```

### Saga State Visualization
```
START
  │
  ▼
[Reserve Inventory]──fail──►[END: FAILED]
  │success
  ▼
[Charge Payment]──fail──►[Release Inventory]──►[END: FAILED]
  │success
  ▼
[Confirm Inventory]
  │
  ▼
[Capture Payment]──fail──►[Release Inventory]──►[END: FAILED]
  │success
  ▼
[Notify Shipping]
  │
  ▼
[END: COMPLETED]
```

## Praktik Tapşırıqlar
- Temporal.io ilə order saga implement edin
- Choreography-based saga: Kafka events ilə
- Partial failure: payment fail olduqda compensation simulate edin
- 2PC vs Saga latency müqayisəsi benchmark
- AWS Step Functions ilə visual saga dizayn edin

## Əlaqəli Mövzular
- [25-outbox-pattern.md](25-outbox-pattern.md) — Reliable event publishing for saga
- [08-message-queues.md](08-message-queues.md) — Kafka for choreography saga
- [13-idempotency-design.md](13-idempotency-design.md) — Saga step idempotency
- [23-eventual-consistency.md](23-eventual-consistency.md) — Consistency in distributed systems
- [18-event-driven-architecture.md](18-event-driven-architecture.md) — Event-driven saga
