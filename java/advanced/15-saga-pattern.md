# 15 — Saga Pattern — Java ilə Geniş İzah

> **Seviyye:** Expert ⭐⭐⭐⭐


## Mündəricat
1. [Saga Pattern nədir?](#saga-pattern-nədir)
2. [Choreography Saga](#choreography-saga)
3. [Orchestration Saga](#orchestration-saga)
4. [Compensating Transaction](#compensating-transaction)
5. [Spring ilə Orchestration Saga](#spring-ilə-orchestration-saga)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Saga Pattern nədir?

**Saga Pattern** — mikroservice mühitdə distributed transaction-ları idarə etmək üçün pattern. 2PC (Two-Phase Commit) əvəzinə eventual consistency istifadə edir.

```
Problem:
  Sifariş ver:
    1. Order Service — sifariş yarat
    2. Inventory Service — məhsulları ayır
    3. Payment Service — ödəniş al
    4. Notification Service — bildiriş göndər
  
  Distributed transaction — 4 microservice, ayrı DB-lər
  2PC problemi: performans düşür, deadlock riski, distributed lock

Saga həll:
  Hər step lokal transaction. Uğursuz olduqda compensating transactions.
  Step 3 fail → Step 2 undo (inventory-ni geri qaytarır), Step 1 undo (order cancel)
```

---

## Choreography Saga

Event-driven — service-lər bir-birinə event vasitəsilə danışır, mərkəzi koordinator yoxdur.

```java
// 1. Order Service — sifarişi yarat, event göndər
@Service
public class OrderService {

    private final OrderRepository orderRepository;
    private final KafkaTemplate<String, Object> kafkaTemplate;

    @Transactional
    public Order createOrder(OrderRequest request) {
        Order order = Order.create(request.customerId(), request.items());
        Order saved = orderRepository.save(order);

        // Event göndər
        kafkaTemplate.send("order-created",
            saved.getId().toString(),
            new OrderCreatedEvent(saved.getId(), saved.getItems(), saved.getTotalAmount()));

        return saved;
    }

    // Inventory uğursuz olduqda — sifarişi cancel et
    @KafkaListener(topics = "inventory-reservation-failed")
    @Transactional
    public void onInventoryFailed(InventoryReservationFailedEvent event) {
        orderRepository.findById(event.orderId()).ifPresent(order -> {
            order.cancel("Stok çatışmır");
            orderRepository.save(order);

            // OrderCancelled event göndər
            kafkaTemplate.send("order-cancelled",
                order.getId().toString(),
                new OrderCancelledEvent(order.getId(), "Stok çatışmır"));
        });
    }

    // Payment uğursuz olduqda — sifarişi cancel et
    @KafkaListener(topics = "payment-failed")
    @Transactional
    public void onPaymentFailed(PaymentFailedEvent event) {
        orderRepository.findById(event.orderId()).ifPresent(order -> {
            order.cancel("Ödəniş uğursuz");
            orderRepository.save(order);
        });
    }
}

// 2. Inventory Service
@Service
public class InventoryService {

    @KafkaListener(topics = "order-created")
    @Transactional
    public void onOrderCreated(OrderCreatedEvent event) {
        try {
            reserveItems(event.orderId(), event.items());

            kafkaTemplate.send("inventory-reserved",
                event.orderId().toString(),
                new InventoryReservedEvent(event.orderId()));

        } catch (InsufficientStockException e) {
            kafkaTemplate.send("inventory-reservation-failed",
                event.orderId().toString(),
                new InventoryReservationFailedEvent(event.orderId(), e.getMessage()));
        }
    }

    // Compensating — sifarişi cancel olduqda stoku geri ver
    @KafkaListener(topics = "order-cancelled")
    @Transactional
    public void onOrderCancelled(OrderCancelledEvent event) {
        releaseReservation(event.orderId()); // Stoku geri ver
    }
}

// 3. Payment Service
@Service
public class PaymentService {

    @KafkaListener(topics = "inventory-reserved")
    @Transactional
    public void onInventoryReserved(InventoryReservedEvent event) {
        try {
            processPayment(event.orderId());

            kafkaTemplate.send("payment-completed",
                event.orderId().toString(),
                new PaymentCompletedEvent(event.orderId()));

        } catch (PaymentException e) {
            kafkaTemplate.send("payment-failed",
                event.orderId().toString(),
                new PaymentFailedEvent(event.orderId(), e.getMessage()));
        }
    }
}
```

**Choreography Saga diaqramı:**
```
OrderService: OrderCreated →
  InventoryService: (reserve) → InventoryReserved →
    PaymentService: (pay) → PaymentCompleted →
      NotificationService: (notify)

Fail case:
PaymentService: (pay fails) → PaymentFailed →
  InventoryService: (release) → InventoryReleased →
    OrderService: (cancel order)
```

---

## Orchestration Saga

Mərkəzi Saga Orchestrator bütün addımları koordinasiya edir.

```java
// Saga state
@Entity
@Table(name = "order_saga")
public class OrderSagaState {
    @Id
    private String sagaId;
    private String orderId;
    private String currentStep;
    private String status; // STARTED, COMPENSATING, COMPLETED, FAILED

    @ElementCollection
    private List<String> completedSteps = new ArrayList<>();

    @Column(columnDefinition = "TEXT")
    private String sagaData; // JSON
}

// Saga Orchestrator
@Service
public class OrderSagaOrchestrator {

    private final OrderSagaRepository sagaRepository;
    private final OrderServiceClient orderClient;
    private final InventoryServiceClient inventoryClient;
    private final PaymentServiceClient paymentClient;
    private final NotificationServiceClient notificationClient;

    @Transactional
    public void startSaga(String orderId) {
        OrderSagaState saga = new OrderSagaState();
        saga.setSagaId(UUID.randomUUID().toString());
        saga.setOrderId(orderId);
        saga.setStatus("STARTED");
        saga.setCurrentStep("RESERVE_INVENTORY");
        sagaRepository.save(saga);

        // İlk addım
        executeStep(saga);
    }

    private void executeStep(OrderSagaState saga) {
        switch (saga.getCurrentStep()) {
            case "RESERVE_INVENTORY" -> {
                try {
                    inventoryClient.reserve(saga.getOrderId());
                    saga.getCompletedSteps().add("RESERVE_INVENTORY");
                    saga.setCurrentStep("PROCESS_PAYMENT");
                    sagaRepository.save(saga);
                    executeStep(saga); // Növbəti addım
                } catch (Exception e) {
                    startCompensation(saga, "RESERVE_INVENTORY");
                }
            }
            case "PROCESS_PAYMENT" -> {
                try {
                    paymentClient.process(saga.getOrderId());
                    saga.getCompletedSteps().add("PROCESS_PAYMENT");
                    saga.setCurrentStep("SEND_NOTIFICATION");
                    sagaRepository.save(saga);
                    executeStep(saga);
                } catch (Exception e) {
                    startCompensation(saga, "PROCESS_PAYMENT");
                }
            }
            case "SEND_NOTIFICATION" -> {
                notificationClient.sendConfirmation(saga.getOrderId());
                saga.setStatus("COMPLETED");
                saga.setCurrentStep(null);
                sagaRepository.save(saga);
                log.info("Saga tamamlandı: {}", saga.getSagaId());
            }
        }
    }

    private void startCompensation(OrderSagaState saga, String failedStep) {
        saga.setStatus("COMPENSATING");
        saga.setCurrentStep("COMPENSATE_" + failedStep);
        sagaRepository.save(saga);
        executeCompensation(saga);
    }

    private void executeCompensation(OrderSagaState saga) {
        List<String> completed = new ArrayList<>(saga.getCompletedSteps());
        Collections.reverse(completed); // Ters sırada

        for (String step : completed) {
            switch (step) {
                case "PROCESS_PAYMENT" -> paymentClient.refund(saga.getOrderId());
                case "RESERVE_INVENTORY" -> inventoryClient.release(saga.getOrderId());
            }
        }

        // Sifarişi cancel et
        orderClient.cancel(saga.getOrderId(), "Saga uğursuz oldu");

        saga.setStatus("FAILED");
        sagaRepository.save(saga);
    }
}
```

---

## Compensating Transaction

```java
// Hər step-in compensating (geri qaytarma) əməliyyatı olmalıdır

@Service
public class InventoryService {

    // Forward transaction — stok ayır
    @Transactional
    public void reserveItems(OrderId orderId, List<OrderItem> items) {
        items.forEach(item -> {
            Product product = productRepository.findById(item.getProductId()).orElseThrow();
            if (product.getAvailableStock() < item.getQuantity()) {
                throw new InsufficientStockException(item.getProductId());
            }
            product.reserve(item.getQuantity());
            productRepository.save(product);
        });
        // Reservation record saxla (compensation üçün)
        reservationRepository.save(new Reservation(orderId, items));
    }

    // Compensating transaction — stoku geri ver
    @Transactional
    public void releaseItems(OrderId orderId) {
        Reservation reservation = reservationRepository.findByOrderId(orderId)
            .orElseThrow(() -> new ReservationNotFoundException(orderId));

        reservation.getItems().forEach(item -> {
            Product product = productRepository.findById(item.getProductId()).orElseThrow();
            product.release(item.getQuantity()); // Ayırmanı geri ver
            productRepository.save(product);
        });

        reservation.markAsReleased();
        reservationRepository.save(reservation);
    }
}
```

---

## Spring ilə Orchestration Saga

```java
// Eventuate Tram Saga Framework (populyar)
// yaxud manual implementation

// Async saga (event-driven orchestration)
@Saga
public class OrderSaga implements SimpleSaga<OrderSagaData> {

    @Autowired
    private SagaDefinition<OrderSagaData> sagaDefinition;

    @Override
    public SagaDefinition<OrderSagaData> getSagaDefinition() {
        return sagaDefinition;
    }

    @Bean
    public SagaDefinition<OrderSagaData> sagaDef() {
        return step()
            .invokeLocal(this::createOrder)
            .withCompensation(this::rejectOrder)
            .step()
            .invokeParticipant(this::reserveCredit)
            .onReply(ReserveCreditReply.class, this::handleReserveCreditReply)
            .withCompensation(this::releaseCredit)
            .step()
            .invokeLocal(this::approveOrder)
            .build();
    }
}
```

---

## İntervyu Sualları

### 1. Saga niyə 2PC-dən daha yaxşıdır?
**Cavab:** 2PC distributed lock tələb edir — bütün participant-lar hazır olana qədər resurslar kilidlənir, bu performance probleminə və deadlock-a yol açır. Saga lokal transaction-lardan ibarətdir, eventual consistency istifadə edir, lock yoxdur. Daha yaxşı availability və scalability.

### 2. Choreography vs Orchestration fərqi?
**Cavab:** `Choreography` — service-lər event vasitəsilə bir-birinə danışır, mərkəzi koordinator yoxdur. Loose coupling, amma debug çətin. `Orchestration` — mərkəzi Saga Orchestrator bütün addımları idarə edir. Daha aydın flow, amma single point of complexity. Kompleks saga-lar üçün orchestration tövsiyə olunur.

### 3. Compensating transaction nədir?
**Cavab:** Uğursuz saga-da əvvəlki addımları geri qaytarmaq üçün icra olunan əməliyyat. Hər forward transaction üçün compensating olmalıdır. Məsələn: `reserveInventory` → `releaseInventory`. Compensating transaction-lar idempotent olmalıdır (bir neçə dəfə çağırıla bilər).

### 4. Saga-da idempotency niyə vacibdir?
**Cavab:** Mesaj delivery at-least-once — event bir neçə dəfə gələ bilər. Compensating transaction network xətası səbəbiylə retry edilə bilər. Idempotency sayəsində eyni əməliyyat bir neçə dəfə çağırılsa belə nəticə dəyişmir. Unique idempotency key saxlamaq köməkçi olur.

### 5. Saga-da izolasiya problemi (dirty reads) necə idarə olunur?
**Cavab:** Saga lokal transaction-ları izolə edir, amma ara vəziyyətlər görünə bilər (inventory rezerv edilib, ödəniş hələ tam deyil). Semantic lock — pending state-də entity-ni `PROCESSING` kimi işarələ. Pessimistic view — UI-da "pending" göstər. Bayesian inference — risk qiymətləndir. Bu Saga-nın qəbul edilmiş məhdudiyyətidir.

*Son yenilənmə: 2026-04-10*
