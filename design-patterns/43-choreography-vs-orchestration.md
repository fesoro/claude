# Choreography vs Orchestration (Saga) (Senior)

## Mündəricat
1. [İki Yanaşma](#iki-yanaşma)
2. [Choreography (Xoreografiya)](#choreography-xoreografiya)
3. [Orchestration (Orkestr)](#orchestration-orkestr)
4. [Müqayisə](#müqayisə)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## İki Yanaşma

```
Saga pattern (topik 44) microservice distributed transaction üçündür.
Amma Saga-nı necə koordinasiya etmək? — İki üsul var.

Ssenari: Sifariş prosesi
  1. Order yaradılsın
  2. Inventory rezerv edilsin
  3. Ödəniş alınsın
  4. Bildiriş göndərilsin

Choreography:
  Hər servis öz işini bitirir → event yayır → digəri qulaq asır.
  Mərkəzi koordinator yoxdur.

Orchestration:
  Bir "dirijor" (Saga Orchestrator) bütün prosesi idarə edir.
  Hər servisə nə edəcəyini bildirir, cavab gözləyir.
```

---

## Choreography (Xoreografiya)

```
Event-driven. Hər servis hadisələrə reaksiya verir.

OrderService     → order.created event yayır
                         ↓
InventoryService ← order.created dinləyir
                 → inventory.reserved event yayır
                         ↓
PaymentService   ← inventory.reserved dinləyir
                 → payment.charged event yayır
                         ↓
NotifyService    ← payment.charged dinləyir
                 → notification.sent event yayır

Uğursuzluq (ödəniş uğursuz):
PaymentService → payment.failed event yayır
                         ↓
InventoryService ← payment.failed dinləyir → inventory release edir
                         ↓
OrderService     ← inventory.released dinləyir → order cancel edir

Faydaları:
  + Loose coupling (servislar bir-birini bilmir, yalnız events)
  + Yeni servis əlavə etmək asandır (event-ə subscribe et)
  + Mərkəzi SPOF yoxdur

Çatışmazlıqlar:
  - Prosesi izləmək çətindir ("nə mərhələdədir?")
  - Debugging çətin (event chain-i izləmək)
  - Cycle risk (A→B→A event loop)
  - Biznes məntiqi event handler-lara yayılır
```

---

## Orchestration (Orkestr)

```
Mərkəzi Saga Orchestrator hər addımı idarə edir.

SagaOrchestrator:
  1. OrderService.createOrder() → cavab gözlə
  2. InventoryService.reserve() → cavab gözlə
  3. PaymentService.charge()    → cavab gözlə
  4. NotifyService.send()       → cavab gözlə
  5. Hamısı OK → saga tamamlandı

Uğursuzluq (3. addım fail):
  SagaOrchestrator:
    PaymentService.charge() → FAIL
    InventoryService.release() → kompensasiya
    OrderService.cancel()    → kompensasiya

┌────────────────────────────────────────────────────────┐
│              Saga Orchestrator                         │
│                                                        │
│  State: STARTED → INVENTORY_RESERVED →                 │
│         PAYMENT_FAILED → COMPENSATING → CANCELLED      │
└──────────┬──────────┬──────────┬────────────────────────┘
           │          │          │
    ┌──────▼──┐  ┌────▼────┐  ┌─▼──────┐
    │ Order   │  │Inventory│  │Payment │
    │ Service │  │ Service │  │Service │
    └─────────┘  └─────────┘  └────────┘

Faydaları:
  + Proses məntiqi mərkəzdə (anlaşılır)
  + Saga state izlənir (hansı mərhələdədir?)
  + Debugging asandır
  + Biznes məntiqi orchestrator-dadır

Çatışmazlıqlar:
  - Orchestrator = SPOF (single point of failure)
  - Servislar orchestrator-a coupling
  - Yeni addım əlavə etmək orchestrator-ı dəyişdirir
```

---

## Müqayisə

```
┌──────────────────────┬─────────────────┬─────────────────┐
│                      │ Choreography    │ Orchestration   │
├──────────────────────┼─────────────────┼─────────────────┤
│ Koordinasiya         │ Event-driven    │ Mərkəzi         │
│ Coupling             │ Loose           │ Medium          │
│ Visibility           │ Çətin           │ Asan            │
│ Debugging            │ Çətin           │ Asan            │
│ Yeni addım əlavəsi   │ Asan            │ Orchestrator dəyiş│
│ SPOF riski           │ Yoxdur          │ Orchestrator    │
│ Biznes məntiqi yeri  │ Hər servisdə    │ Orchestrator-da │
│ Event loop riski     │ Var             │ Yoxdur          │
└──────────────────────┴─────────────────┴─────────────────┘

Praktik tövsiyə:
  Sadə (2-3 servis): Choreography
  Mürəkkəb (5+ addım): Orchestration
  Uzun müddətli workflow: Orchestration (state tracking lazımdır)
  
  Temporal.io, Conductor — orchestration engine-lər
```

---

## PHP İmplementasiyası

```php
<?php
// Choreography — Event-based Saga

// OrderService: order yaradır, event yayır
class OrderService
{
    public function create(CreateOrderCommand $cmd): Order
    {
        $order = Order::create($cmd);
        $this->orders->save($order);

        // Evento yay — InventoryService dinləyir
        $this->eventBus->publish(new OrderCreatedEvent(
            orderId: $order->getId(),
            items: $order->getItems(),
        ));

        return $order;
    }
}

// InventoryService: event dinləyir, öz işini görür
class InventoryEventHandler
{
    #[EventHandler(OrderCreatedEvent::class)]
    public function onOrderCreated(OrderCreatedEvent $event): void
    {
        try {
            $this->inventory->reserve($event->orderId, $event->items);
            $this->eventBus->publish(new InventoryReservedEvent($event->orderId));
        } catch (InsufficientStockException) {
            $this->eventBus->publish(new InventoryReservationFailedEvent($event->orderId));
        }
    }

    #[EventHandler(PaymentFailedEvent::class)]
    public function onPaymentFailed(PaymentFailedEvent $event): void
    {
        $this->inventory->release($event->orderId);
        $this->eventBus->publish(new InventoryReleasedEvent($event->orderId));
    }
}
```

```php
<?php
// Orchestration — Saga Orchestrator

class CreateOrderSaga
{
    private string $state = 'STARTED';

    public function __construct(
        private OrderService $orders,
        private InventoryService $inventory,
        private PaymentService $payments,
        private NotifyService $notify,
        private SagaStateRepository $stateRepo,
    ) {}

    public function execute(CreateOrderCommand $cmd): void
    {
        $sagaId = uniqid('saga_', true);

        try {
            // Step 1
            $this->transition($sagaId, 'CREATING_ORDER');
            $order = $this->orders->create($cmd);

            // Step 2
            $this->transition($sagaId, 'RESERVING_INVENTORY');
            $this->inventory->reserve($order->getId(), $order->getItems());

            // Step 3
            $this->transition($sagaId, 'CHARGING_PAYMENT');
            $this->payments->charge($order->getId(), $order->getTotal());

            // Step 4
            $this->transition($sagaId, 'SENDING_NOTIFICATION');
            $this->notify->orderConfirmed($order->getId());

            $this->transition($sagaId, 'COMPLETED');

        } catch (InsufficientStockException) {
            $this->compensate($sagaId, $order ?? null, 'INVENTORY_FAILED');
        } catch (PaymentFailedException) {
            $this->compensate($sagaId, $order ?? null, 'PAYMENT_FAILED');
        }
    }

    private function compensate(string $sagaId, ?Order $order, string $reason): void
    {
        $this->transition($sagaId, 'COMPENSATING');

        if ($order) {
            $this->inventory->release($order->getId());
            $this->orders->cancel($order->getId());
        }

        $this->transition($sagaId, 'CANCELLED');
    }

    private function transition(string $sagaId, string $newState): void
    {
        $this->state = $newState;
        $this->stateRepo->save($sagaId, $newState, now());
    }
}
```

---

## İntervyu Sualları

- Choreography vs Orchestration — əsas fərq nədir?
- Choreography-də "görünürlük" problemi niyə yaranır?
- Orchestrator SPOF riskini necə azaldarsınız?
- 10 addımlı business workflow üçün hansını seçərdiniz? Niyə?
- Choreography-də event loop (siklus) yaranmasını necə önlərsiniz?
- Temporal.io kimi orchestration engine-lərin faydası nədir?
