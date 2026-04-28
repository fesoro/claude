# Choreography vs Orchestration (Lead)

Distributed workflow-ları koordinasiya etməyin iki fərqli yanaşması.
İkisi də Saga pattern-in implementasiya üsullarıdır.

**Orchestration — Mərkəzi koordinator:**
- Bir "saga orchestrator" servis hər addımı birbaşa çağırır
- Orchestrator workflow-un gedişatını bilir
- Əgər bir addım uğursuz olarsa, compensating transactions çağırır
- Debugging asandır: workflow bir yerdədir

**Choreography — Paylanmış koordinasiya:**
- Hər servis event publish edir, digərləri subscribe edir
- Heç bir servis "böyük mənzərəni" görmür
- Workflow event zənciri vasitəsilə irəliləyir
- Loose coupling: servislər bir-birini tanımır

---

## Orchestration (Laravel)

```
project/
├── order-service/                             # Orchestrator lives here
│   ├── app/
│   │   ├── Sagas/
│   │   │   ├── OrderFulfillmentSaga.php       # Bütün workflow buradadır
│   │   │   │   // Step 1: Reserve inventory
│   │   │   │   // Step 2: Process payment
│   │   │   │   // Step 3: Notify shipping
│   │   │   │   // On failure: compensate
│   │   │   └── OrderFulfillmentSagaState.php  # Workflow state persistence
│   │   │
│   │   ├── SagaSteps/
│   │   │   ├── ReserveInventoryStep.php       # Calls inventory-service
│   │   │   ├── ProcessPaymentStep.php         # Calls payment-service
│   │   │   ├── NotifyShippingStep.php         # Calls shipping-service
│   │   │   └── Compensations/
│   │   │       ├── ReleaseInventoryCompensation.php
│   │   │       └── RefundPaymentCompensation.php
│   │   │
│   │   └── Clients/
│   │       ├── InventoryServiceClient.php     # Sync call to inventory
│   │       ├── PaymentServiceClient.php       # Sync call to payment
│   │       └── ShippingServiceClient.php      # Sync call to shipping
│   │
│   └── database/
│       └── migrations/
│           └── create_saga_states_table.php   # Workflow state persistence
│
├── inventory-service/                         # Simple: just handles commands
│   └── app/Http/Controllers/InventoryController.php
│       # POST /reserve → ReserveInventoryCommand
│       # POST /release → ReleaseInventoryCommand
│
└── payment-service/                           # Simple: just handles commands
    └── app/Http/Controllers/PaymentController.php
        # POST /charge → ProcessPaymentCommand
        # POST /refund → RefundPaymentCommand
```

---

## Choreography (Laravel)

```
project/
│
├── order-service/                             # Just places order + publishes event
│   ├── app/
│   │   ├── Services/OrderService.php
│   │   │   // Places order → publishes OrderPlaced event
│   │   │   // No direct calls to other services!
│   │   └── Events/OrderPlaced.php
│   └── (does NOT know about inventory, payment, shipping)
│
├── inventory-service/                         # Reacts to OrderPlaced
│   ├── app/
│   │   ├── Listeners/HandleOrderPlaced.php   # Subscribes to OrderPlaced
│   │   │   // Reserves stock → publishes InventoryReserved
│   │   │   // On failure → publishes InventoryReservationFailed
│   │   └── Events/
│   │       ├── InventoryReserved.php
│   │       └── InventoryReservationFailed.php
│   └── (does NOT know about payment, shipping)
│
├── payment-service/                           # Reacts to InventoryReserved
│   ├── app/
│   │   ├── Listeners/HandleInventoryReserved.php
│   │   │   // Charges card → publishes PaymentCompleted
│   │   │   // On failure → publishes PaymentFailed
│   │   └── Events/
│   │       ├── PaymentCompleted.php
│   │       └── PaymentFailed.php
│   └── (does NOT know about order structure, shipping)
│
├── shipping-service/                          # Reacts to PaymentCompleted
│   ├── app/
│   │   ├── Listeners/HandlePaymentCompleted.php
│   │   │   // Creates shipment → publishes ShipmentCreated
│   │   └── Events/
│   │       └── ShipmentCreated.php
│   └── (does NOT know about payment details)
│
└── notification-service/                      # Reacts to everything
    └── app/Listeners/
        ├── HandleOrderPlaced.php              # Send order confirmation
        ├── HandlePaymentFailed.php            # Send payment failure email
        └── HandleShipmentCreated.php          # Send tracking info
```

---

## Spring Boot Orchestration (Saga Orchestrator)

```
src/main/java/com/example/order/
├── saga/
│   ├── OrderFulfillmentSaga.java              # Orchestrator
│   │   // @SagaOrchestrator (Axon / custom)
│   │   // Manages: inventory → payment → shipping steps
│   ├── SagaState.java
│   │   // PENDING → INVENTORY_RESERVED → PAYMENT_PROCESSED → SHIPPED
│   │   // INVENTORY_FAILED → CANCELLED
│   │   // PAYMENT_FAILED → INVENTORY_RELEASED → CANCELLED
│   └── step/
│       ├── ReserveInventoryStep.java
│       │   // Calls inventory-service via Feign
│       │   // On success: advance state
│       │   // On fail: trigger compensation
│       ├── ProcessPaymentStep.java
│       └── NotifyShippingStep.java
│
├── compensation/
│   ├── ReleaseInventoryCompensation.java
│   └── RefundPaymentCompensation.java
│
└── repository/
    └── SagaStateRepository.java               # Persist saga state in DB
```

---

## Golang Choreography (Event-Driven)

```
order-service/
├── internal/
│   ├── service/order_service.go
│   │   // PlaceOrder → save → publish OrderPlaced event
│   └── event/
│       └── publisher.go

inventory-service/
├── internal/
│   ├── subscriber/
│   │   └── order_placed_handler.go            # Listens to OrderPlaced
│   │       // Reserve stock
│   │       // Publish InventoryReserved OR InventoryFailed
│   └── publisher/
│       └── inventory_events.go

payment-service/
├── internal/
│   ├── subscriber/
│   │   └── inventory_reserved_handler.go      # Listens to InventoryReserved
│   │       // Charge → Publish PaymentCompleted OR PaymentFailed
│   └── publisher/
│       └── payment_events.go
```

---

## Müqayisə Cədvəli

```
                        ORCHESTRATION           CHOREOGRAPHY
─────────────────────────────────────────────────────────────
Coordinator             Mərkəzi (Saga)          Yoxdur
Coupling                Higher (knows others)   Lower (event-driven)
Debugging               Asandır (1 yer)         Çətindir (event trail)
Workflow visibility     Yüksək                  Aşağı
Cyclic dependency risk  Aşağı                   Yüksək
Bottleneck risk         Yüksək (orchestrator)   Aşağı
Best for                Complex workflows       Simple reactions
                        Rollback logic          Decoupled systems
                        Audit requirements      Highly scalable

Ne vaxt Orchestration:
  ✓ 3+ service coordination
  ✓ Compensating transactions lazımdır
  ✓ Workflow state visibility vacibdir (audit log)
  ✓ Human approval steps var

Ne vaxt Choreography:
  ✓ Simple event reactions (A baş verir → B react edir)
  ✓ Services must be independently deployable
  ✓ Adding new services without changing others
  ✓ High throughput, loose coupling prioritet
```
