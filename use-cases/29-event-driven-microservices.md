# Event-driven Microservices (Lead)

## Ssenari

3 mikroservis arasında order placement flow: Order Service, Payment Service, Inventory Service. Choreography-based saga, event-driven kommunikasiya, compensation.

---

## Arxitektura

```
                    RabbitMQ (Event Bus)
                         │
         ┌───────────────┼───────────────┐
         │               │               │
┌────────▼─────┐  ┌──────▼──────┐  ┌────▼─────────┐
│Order Service │  │Payment Svc  │  │Inventory Svc │
│              │  │             │  │              │
│ orders DB    │  │payments DB  │  │inventory DB  │
└──────────────┘  └─────────────┘  └──────────────┘

Events flow (happy path):
  1. OrderPlaced       → Payment Service (charge)
  2. PaymentProcessed  → Inventory Service (reserve)
  3. StockReserved     → Order Service (confirm)
  4. OrderConfirmed    → Email/Notification Service

Compensation flow (failure):
  Inventory uğursuz:
  4. StockReservationFailed → Payment Service (refund)
  5. PaymentRefunded        → Order Service (cancel)
  6. OrderCancelled         → Email Service (apology)
```

---

## Events

*Bu kod bütün servislərin paylaşdığı domain event-lərinin əsas sinifini və Order/Payment/Inventory event-lərini göstərir:*

```php
// Shared event contracts (shared library / separate package)

abstract class DomainEvent
{
    public readonly string $eventId;
    public readonly string $occurredAt;
    public readonly int    $version;
    
    public function __construct()
    {
        $this->eventId    = Str::uuid()->toString();
        $this->occurredAt = now()->toIso8601String();
        $this->version    = 1;
    }
    
    abstract public function eventType(): string;
}

// Order Service events
class OrderPlaced extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly array  $items,
        public readonly int    $totalAmount,
        public readonly array  $paymentInfo,
    ) { parent::__construct(); }
    
    public function eventType(): string { return 'order.placed'; }
}

class OrderConfirmed extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
    ) { parent::__construct(); }
    
    public function eventType(): string { return 'order.confirmed'; }
}

class OrderCancelled extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
    ) { parent::__construct(); }
    
    public function eventType(): string { return 'order.cancelled'; }
}

// Payment Service events
class PaymentProcessed extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $paymentId,
        public readonly int    $amount,
    ) { parent::__construct(); }
    
    public function eventType(): string { return 'payment.processed'; }
}

class PaymentFailed extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
    ) { parent::__construct(); }
    
    public function eventType(): string { return 'payment.failed'; }
}

class PaymentRefunded extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $paymentId,
    ) { parent::__construct(); }
    
    public function eventType(): string { return 'payment.refunded'; }
}

// Inventory Service events
class StockReserved extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reservationId,
    ) { parent::__construct(); }
    
    public function eventType(): string { return 'stock.reserved'; }
}

class StockReservationFailed extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
    ) { parent::__construct(); }
    
    public function eventType(): string { return 'stock.reservation_failed'; }
}
```

---

## Order Service

*Bu kod sifarişi DB-yə yazıb event publish edən, gələn event-lərə idempotent reaksiya verən Order servisini göstərir:*

```php
class OrderService
{
    public function __construct(
        private EventBus $eventBus,
        private OrderRepository $orders,
    ) {}
    
    // User order yerləşdirir
    public function placeOrder(PlaceOrderCommand $cmd): Order
    {
        $order = Order::create([
            'id'          => Str::uuid(),
            'customer_id' => $cmd->customerId,
            'items'       => $cmd->items,
            'total'       => $cmd->calculateTotal(),
            'status'      => OrderStatus::PENDING,
        ]);
        
        $this->orders->save($order);
        
        // Event publish et — Payment Service dinləyir
        $this->eventBus->publish(new OrderPlaced(
            orderId:     $order->id,
            customerId:  $order->customer_id,
            items:       $order->items,
            totalAmount: $order->total,
            paymentInfo: $cmd->paymentInfo,
        ));
        
        return $order;
    }
    
    // Payment uğurlu, Inventory de uğurlu
    public function handleStockReserved(StockReserved $event): void
    {
        $this->withIdempotency($event->eventId, function () use ($event) {
            $order = $this->orders->findById($event->orderId);
            $order->confirm();
            $this->orders->save($order);
            
            $this->eventBus->publish(new OrderConfirmed($event->orderId));
        });
    }
    
    // Payment uğursuz
    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $this->withIdempotency($event->eventId, function () use ($event) {
            $order = $this->orders->findById($event->orderId);
            $order->cancel("Payment failed: {$event->reason}");
            $this->orders->save($order);
            
            $this->eventBus->publish(new OrderCancelled(
                $event->orderId,
                "Payment uğursuz: {$event->reason}"
            ));
        });
    }
    
    // Inventory uğursuz (payment artıq alınıb → Payment refund etdi)
    public function handlePaymentRefunded(PaymentRefunded $event): void
    {
        $this->withIdempotency($event->eventId, function () use ($event) {
            $order = $this->orders->findById($event->orderId);
            $order->cancel('Stock mövcud deyil, ödəniş geri qaytarıldı');
            $this->orders->save($order);
            
            $this->eventBus->publish(new OrderCancelled(
                $event->orderId,
                'Stock mövcud deyil'
            ));
        });
    }
    
    private function withIdempotency(string $eventId, callable $fn): void
    {
        if (ProcessedEvent::where('event_id', $eventId)->exists()) {
            return;  // Artıq işlənib
        }
        
        DB::transaction(function () use ($eventId, $fn) {
            $fn();
            ProcessedEvent::create(['event_id' => $eventId]);
        });
    }
}
```

---

## Payment Service

*Bu kod OrderPlaced event-ini alıb ödəniş edən, uğursuzluqda PaymentFailed, stok bitmişdə kompensasiya edən Payment servisini göstərir:*

```php
class PaymentConsumer
{
    public function __construct(
        private PaymentGateway $gateway,
        private EventBus $eventBus,
    ) {}
    
    // OrderPlaced → charge
    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $this->withIdempotency($event->eventId, function () use ($event) {
            try {
                $payment = $this->gateway->charge(
                    amount:      $event->totalAmount,
                    paymentInfo: $event->paymentInfo,
                    idempotencyKey: "order-{$event->orderId}",
                );
                
                $this->eventBus->publish(new PaymentProcessed(
                    $event->orderId,
                    $payment->id,
                    $event->totalAmount,
                ));
            } catch (PaymentException $e) {
                $this->eventBus->publish(new PaymentFailed(
                    $event->orderId,
                    $e->getMessage(),
                ));
            }
        });
    }
    
    // StockReservationFailed → refund (compensation)
    public function handleStockReservationFailed(StockReservationFailed $event): void
    {
        $this->withIdempotency($event->eventId, function () use ($event) {
            $payment = Payment::where('order_id', $event->orderId)->firstOrFail();
            
            $this->gateway->refund($payment->gateway_id);
            
            $this->eventBus->publish(new PaymentRefunded(
                $event->orderId,
                $payment->id,
            ));
        });
    }
}
```

---

## Inventory Service

*Bu kod PaymentProcessed event-ini alıb stok rezerv edən, yetmədikdə StockReservationFailed publish edən Inventory servisini göstərir:*

```php
class InventoryConsumer
{
    public function __construct(
        private InventoryRepository $inventory,
        private EventBus $eventBus,
    ) {}
    
    // PaymentProcessed → reserve stock
    public function handlePaymentProcessed(PaymentProcessed $event): void
    {
        $this->withIdempotency($event->eventId, function () use ($event) {
            $order = $this->fetchOrderItems($event->orderId);
            
            try {
                DB::transaction(function () use ($event, $order) {
                    $reservationId = Str::uuid();
                    
                    foreach ($order['items'] as $item) {
                        $stock = Inventory::where('product_id', $item['product_id'])
                            ->lockForUpdate()
                            ->first();
                        
                        if (!$stock || $stock->available < $item['quantity']) {
                            throw new OutOfStockException(
                                "Product {$item['product_id']} stokda yoxdur"
                            );
                        }
                        
                        $stock->decrement('available', $item['quantity']);
                        
                        StockReservation::create([
                            'id'         => $reservationId,
                            'order_id'   => $event->orderId,
                            'product_id' => $item['product_id'],
                            'quantity'   => $item['quantity'],
                        ]);
                    }
                    
                    $this->eventBus->publish(new StockReserved(
                        $event->orderId,
                        $reservationId,
                    ));
                });
            } catch (OutOfStockException $e) {
                $this->eventBus->publish(new StockReservationFailed(
                    $event->orderId,
                    $e->getMessage(),
                ));
            }
        });
    }
    
    private function fetchOrderItems(string $orderId): array
    {
        // Order Service-dən REST ilə al
        return Http::timeout(5)
            ->get(config('services.order.url') . "/internal/orders/$orderId")
            ->throw()
            ->json();
    }
}
```

---

## Event Bus

*Bu kod domain event-i persistent RabbitMQ mesajına çevirıb exchange-ə publish edən event bus implementasiyasını göstərir:*

```php
class RabbitMQEventBus implements EventBus
{
    public function publish(DomainEvent $event): void
    {
        $message = new AMQPMessage(
            json_encode([
                'event_id'    => $event->eventId,
                'event_type'  => $event->eventType(),
                'occurred_at' => $event->occurredAt,
                'version'     => $event->version,
                'payload'     => $event,
            ]),
            [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id'    => $event->eventId,
                'type'          => $event->eventType(),
            ]
        );
        
        $this->channel->basic_publish(
            $message,
            'order-events',     // Exchange
            $event->eventType() // Routing key
        );
    }
}
```

---

## İntervyu Sualları

**1. Choreography-based saga-da kim nəyi orchestrate edir?**
Heç kim — servislər event-lərə reaksiya verir. Order Service `OrderPlaced` publish edir. Payment Service onu dinləyir, charge edir, `PaymentProcessed` publish edir. Inventory Service onu dinləyir. Mərkəzi koordinator yoxdur. Loose coupling, amma flow-u izləmək çətindir.

**2. Compensation event-ləri necə işləyir?**
Hər addımın əksi var: PaymentProcessed → StockReservationFailed → PaymentRefunded (compensation). Fail olduqda compensation event publish edilir. Hər servis öz compensation-ını edir. Order Service ən son OrderCancelled publish edir.

**3. Idempotency niyə kritikdir?**
At-least-once delivery: eyni event bir neçə dəfə gələ bilər. Hər handler işlədikdə `processed_events` cədvəlinə event_id yazır. İkinci dəfə eyni event_id gəldikdə skip edilir. DB transaction-ı daxilindədir — atomic.

**4. Servislərarası data paylaşımı (order items) necə həll edilir?**
Inventory Service order items-ə ehtiyac duyur. Variant 1: Event payload-ına items daxil et (Event-carried State Transfer). Variant 2: Order Service-dən REST ilə al (synchronous). Variant 3: Shared DB (anti-pattern). Variant 1 ən loose coupled, amma event böyük ola bilər.

---

## Outbox Pattern — Reliable Event Publishing

*Bu kod DB commit ilə event publish-i atomik etmək üçün outbox cədvəlinə yazan və onu polling ilə RabbitMQ-ya göndərən Outbox pattern-ni göstərir:*

```php
// Problem: DB commit + event publish eyni anda atomic deyil.
// DB commit → crash → event publish olmadı → servis event-i heç vaxt görmür.

// Həll: Outbox pattern — event-i eyni transaction-da DB-yə yaz.
// Ayrı worker outbox-dan oxuyur və publish edir.

class OrderService
{
    public function placeOrder(PlaceOrderCommand $cmd): Order
    {
        return DB::transaction(function () use ($cmd) {
            $order = Order::create([...]);

            // Event-i outbox-a yaz — eyni transaction-da
            OutboxEvent::create([
                'event_type' => 'order.placed',
                'payload'    => json_encode(new OrderPlaced(
                    orderId:     $order->id,
                    customerId:  $cmd->customerId,
                    items:       $cmd->items,
                    totalAmount: $cmd->calculateTotal(),
                    paymentInfo: $cmd->paymentInfo,
                )),
                'status'     => 'pending',
            ]);

            return $order;
            // DB commit → outbox yazıldı zəmanəti var
        });
    }
}

// OutboxPublisher — polling ilə outbox-dan oxuyur, RabbitMQ-ya publish edir
class OutboxPublisherJob implements ShouldQueue
{
    public function handle(RabbitMQEventBus $bus): void
    {
        OutboxEvent::where('status', 'pending')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (OutboxEvent $event) use ($bus) {
                try {
                    $bus->publishRaw($event->event_type, $event->payload);
                    $event->update(['status' => 'published', 'published_at' => now()]);
                } catch (\Exception $e) {
                    $event->increment('attempts');
                    if ($event->attempts >= 5) {
                        $event->update(['status' => 'dead_lettered']);
                    }
                }
            });
    }
}
```

---

## Saga State — Orchestration vs Choreography

```
Choreography (mövcud implementasiya):
  Servisler öz aralarında event-lərə reaksiya verir.
  ✅ Loose coupling
  ❌ Flow-u izləmək çətin
  ❌ Debugging: hansı event nə vaxt gəldi?

Orchestration (alternativ):
  Mərkəzi Saga Orchestrator bütün servisləri idarə edir.
  ✅ Flow bir yerdə görünür
  ✅ Debugging asan
  ❌ Orchestrator single point of failure (minimal)

Choreography üçün Saga State cədvəli:
  saga_instances: saga_id, order_id, current_step, status, payload, created_at
  Her event gəldikdə saga state yenilənir — flow izlənilir.
```

*Bu kod choreography saga-da hansı addımın harada olduğunu izləmək üçün saga state-i DB-də saxlayan repository-ni göstərir:*

```php
// Saga state tracking — choreography-də opsional amma tövsiyə edilir
class SagaStateRepository
{
    public function startSaga(string $orderId): void
    {
        SagaInstance::create([
            'saga_id'      => Str::uuid(),
            'order_id'     => $orderId,
            'current_step' => 'order_placed',
            'status'       => 'in_progress',
        ]);
    }

    public function advanceSaga(string $orderId, string $step): void
    {
        SagaInstance::where('order_id', $orderId)
            ->update(['current_step' => $step, 'updated_at' => now()]);
    }

    public function failSaga(string $orderId, string $reason): void
    {
        SagaInstance::where('order_id', $orderId)
            ->update(['status' => 'compensating', 'failure_reason' => $reason]);
    }
}
```

---

## Anti-patternlər

**1. Servislərarası birbaşa DB paylaşımı**
İki mikroservisin eyni DB cədvəllərinə birbaşa müraciət etməsi — schema dəyişikliyi bütün servisləri sındırır, deploy-lar bir-birinə bağlanır, loose coupling itirilir. Hər servis yalnız öz DB-si ilə işləsin, digərinin data-sına event-lər və ya API vasitəsilə çatsın.

**2. Compensation event-lərini planlaşdırmamaq**
Distributed saga-da hər addım üçün əks əməliyyat nəzərə almadan sistemi qurmaq — ödəniş uğurlu olub anbar ehtiyatı çatışmadıqda sifarişi ləğv etmək mümkün olmur, yarımçıq vəziyyət sistemdə qalır. Hər addım üçün compensation event müəyyənləşdir (`OrderCancelled`, `PaymentRefunded`), xəta halında avtomatik tətbiq et.

**3. Event handler-ləri idempotent etməmək**
Message broker at-least-once delivery garantisi verdiyi halda eyni event-i iki dəfə emal etməyi handle etməmək — dublikat ödəniş silinməsi, stok iki dəfə azaldılması kimi ciddi xətalar baş verir. `processed_events` cədvəlindən istifadə et, eyni `event_id` gəldikdə skip et, DB transaction-ı daxilindəki.

**4. Event payload-ına çox az məlumat daxil etmək**
`OrderPlaced` event-inə yalnız `order_id` yazıb bütün detalları digər servisə API ilə çəkdirmək — asılı servis bizə sinxron bağımlı olur, bizdəki API down olarsa o da işləyə bilmir. Event-carried State Transfer tətbiq et, event payload-ına lazımi məlumatları daxil et.

**5. Choreography flow-unu sənədləşdirməmək**
Event-driven sistemdə hansı servisin hansı event-i publish/consume etdiyini heç yerdə qeyd etməmək — yeni developer sistemin axışını başa düşə bilmir, hansı servisin nəyə reaksiya verdiyini izləmək mümkün olmur. Event catalog, AsyncAPI sənədi və ya sadəcə README-də event axışı diagramı saxla.

**6. Shared DB üzərindən servislərarası join etmək**
Inventory və Order servisinin data-sını görmək üçün servislərin eyni DB-yə sorğu atması — bu servisləri fiziki olaraq bir-birinə bağlayır, ayrı deploy imkanını ləğv edir. API Composition (Gateway tərəfindən) və ya CQRS Read Model (eventlərdən build edilmiş lokal görünüş) istifadə et.
