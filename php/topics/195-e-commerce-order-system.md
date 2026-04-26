# System Design: E-Commerce Order System (Lead)

## Mündəricat
1. [Tələblər](#tələblər)
2. [Order Lifecycle](#order-lifecycle)
3. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  Sifariş yerləşdirmək
  Ödəniş emal etmək
  Stok idarəsi
  Çatdırılma izlənməsi
  Geri qaytarma (refund)
  Bildiriş (email, SMS, push)

Qeyri-funksional:
  Consistency: double charge yoxdur
  Availability: 99.99% (Black Friday)
  Scale: 100K order/dəqiqə (peak)
  Idempotency: eyni sifarişin iki dəfə işlənməməsi
  Audit: hər state dəyişikliyi qeyd edilir

Hesablamalar:
  Normal: 1K order/dəqiqə
  Peak (BF): 100K order/dəqiqə = 1666/saniyə
  Order size: ~10KB (items, address, payment)
  Günlük: ~1M order → 10GB
```

---

## Order Lifecycle

```
DRAFT → PLACED → PAYMENT_PENDING → PAYMENT_CONFIRMED
     → PROCESSING → SHIPPED → DELIVERED → COMPLETED
     
Ləğv yolları:
  PLACED → CANCELLED (ödəmədən əvvəl)
  PAYMENT_CONFIRMED → CANCELLATION_REQUESTED → REFUNDING → REFUNDED

State machine:
  ┌─────────┐ place   ┌──────────┐ initPay  ┌─────────────────┐
  │  DRAFT  │────────►│  PLACED  │─────────►│ PAYMENT_PENDING │
  └─────────┘         └──────────┘          └────────┬────────┘
                           │                          │ success
                           │ cancel              ┌────▼──────────────┐
                           │                     │PAYMENT_CONFIRMED  │
                      ┌────▼───┐                 └────────┬──────────┘
                      │CANCEL  │                          │ process
                      └────────┘                 ┌────────▼──────┐
                                                 │  PROCESSING   │
                                                 └────────┬──────┘
                                                          │ ship
                                                 ┌────────▼──────┐
                                                 │   SHIPPED     │
                                                 └────────┬──────┘
                                                          │ deliver
                                                 ┌────────▼──────┐
                                                 │   DELIVERED   │
                                                 └───────────────┘
```

---

## Yüksək Səviyyəli Dizayn

```
Microservices:
  Order Service:    Sifariş yaratmaq, state idarəsi
  Payment Service:  Ödəniş emal (Stripe/PayPal)
  Inventory Service: Stok rezerv/azaltma
  Shipping Service: Kargo inteqrasiyası
  Notify Service:   Email/SMS/Push

Saga Orchestration (topik 121):
  OrderSaga idarə edir:
  1. Order.place()
  2. Inventory.reserve()
  3. Payment.charge()
  4. Shipping.createLabel()
  5. Notify.orderConfirmed()
  
  Hər addım uğursuz olduqda → kompensasiya

Critical path:
  Order placed → Payment confirmed: < 3 saniyə
  Async: shipping label, notification (non-blocking)

Black Friday scale:
  Order Service: horizontal scale (stateless)
  DB: read replicas, partitioning (order_id % 10)
  Queue: Kafka partitioning (order_id key)
  Cache: sıx oxunan data Redis-də
```

---

## PHP İmplementasiyası

```php
<?php
// Order Aggregate — DDD
namespace App\Order\Domain;

class Order
{
    private OrderId       $id;
    private CustomerId    $customerId;
    private OrderStatus   $status;
    private array         $items;
    private Money         $total;
    private ?PaymentId    $paymentId;
    private array         $domainEvents = [];

    public static function place(PlaceOrderCommand $cmd): self
    {
        $order = new self();
        $order->id         = OrderId::generate();
        $order->customerId = $cmd->customerId;
        $order->status     = OrderStatus::PLACED;
        $order->items      = $cmd->items;
        $order->total      = $cmd->calculateTotal();

        $order->recordEvent(new OrderPlacedEvent(
            $order->id,
            $order->customerId,
            $order->items,
            $order->total,
        ));

        return $order;
    }

    public function confirmPayment(PaymentId $paymentId): void
    {
        $this->guardStatus(OrderStatus::PAYMENT_PENDING);
        $this->paymentId = $paymentId;
        $this->status    = OrderStatus::PAYMENT_CONFIRMED;
        $this->recordEvent(new PaymentConfirmedEvent($this->id, $paymentId, $this->total));
    }

    public function cancel(string $reason): void
    {
        $cancellableStatuses = [OrderStatus::PLACED, OrderStatus::PAYMENT_PENDING];
        if (!in_array($this->status, $cancellableStatuses)) {
            throw new CannotCancelOrderException("Status: {$this->status->value}");
        }
        $this->status = OrderStatus::CANCELLED;
        $this->recordEvent(new OrderCancelledEvent($this->id, $reason));
    }

    private function guardStatus(OrderStatus $expected): void
    {
        if ($this->status !== $expected) {
            throw new InvalidOrderStateException(
                "Gözlənilən: {$expected->value}, mövcud: {$this->status->value}"
            );
        }
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events             = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
```

```php
<?php
// Order Saga — Orchestration
class PlaceOrderSaga
{
    public function __construct(
        private OrderRepository    $orders,
        private InventoryService   $inventory,
        private PaymentService     $payments,
        private ShippingService    $shipping,
        private NotificationService $notify,
        private SagaStateRepository $sagaStates,
    ) {}

    public function execute(PlaceOrderCommand $cmd): Order
    {
        $sagaId = SagaId::generate();
        $this->sagaStates->save($sagaId, 'STARTED');

        $order = null;

        try {
            // Step 1: Order yarat
            $this->sagaStates->update($sagaId, 'PLACING_ORDER');
            $order = Order::place($cmd);
            $this->orders->save($order);

            // Step 2: Stok rezerv et
            $this->sagaStates->update($sagaId, 'RESERVING_INVENTORY');
            $reservationId = $this->inventory->reserve(
                $order->getId(),
                $order->getItems(),
            );

            // Step 3: Ödəniş al
            $this->sagaStates->update($sagaId, 'CHARGING_PAYMENT');
            $paymentResult = $this->payments->charge(
                orderId:  $order->getId(),
                amount:   $order->getTotal(),
                method:   $cmd->paymentMethod,
            );

            $order->confirmPayment($paymentResult->getPaymentId());
            $this->orders->save($order);

            // Step 4 & 5: Async (non-blocking)
            $this->shipping->createLabelAsync($order->getId());
            $this->notify->sendConfirmationAsync($order->getId());

            $this->sagaStates->update($sagaId, 'COMPLETED');
            return $order;

        } catch (InsufficientStockException $e) {
            $this->sagaStates->update($sagaId, 'COMPENSATING');
            if ($order) {
                $order->cancel('Stok mövcud deyil');
                $this->orders->save($order);
            }
            $this->sagaStates->update($sagaId, 'CANCELLED');
            throw $e;

        } catch (PaymentFailedException $e) {
            $this->sagaStates->update($sagaId, 'COMPENSATING');
            // Stok rezervini azad et
            $this->inventory->releaseReservation($order->getId());
            $order->cancel('Ödəniş uğursuz oldu');
            $this->orders->save($order);
            $this->sagaStates->update($sagaId, 'CANCELLED');
            throw $e;
        }
    }
}
```

```php
<?php
// Idempotency — eyni sifarişin iki dəfə işlənməməsi
class IdempotentOrderHandler
{
    public function __construct(
        private \Redis        $redis,
        private PlaceOrderSaga $saga,
    ) {}

    public function handle(PlaceOrderCommand $cmd): Order
    {
        $idempotencyKey = "order:idempotent:{$cmd->idempotencyKey}";

        // Artıq işlənibmi?
        $existing = $this->redis->get($idempotencyKey);
        if ($existing !== null) {
            $orderId = $existing;
            return $this->orders->findById($orderId);
        }

        // Mutex: başqa request eyni anda işləməsin
        $lockKey     = "order:lock:{$cmd->idempotencyKey}";
        $lockAcquired = $this->redis->set($lockKey, '1', ['NX', 'EX' => 30]);

        if (!$lockAcquired) {
            throw new ConcurrentOrderException("Bu sifariş artıq işlənir");
        }

        try {
            $order = $this->saga->execute($cmd);

            // Idempotency key-i saxla (24 saat)
            $this->redis->setex($idempotencyKey, 86400, $order->getId());

            return $order;
        } finally {
            $this->redis->del($lockKey);
        }
    }
}
```

---

## İntervyu Sualları

- Order sistemdə Saga pattern niyə istifadə edilir?
- Double charging problemi necə önlənir?
- Black Friday-da 100K order/dəqiqə üçün scale strategiyası?
- Order state machine-ini kod ilə necə tətbiq edərdiniz?
- Payment timeout olduqda (pending state) nə etmək lazımdır?
- Geri qaytarma (refund) prosesini dizayn edin.
