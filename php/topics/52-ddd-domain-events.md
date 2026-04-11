# DDD Domain Events

## Mündəricat
1. [Domain Events nədir?](#domain-events-nədir)
2. [Domain Events vs Integration Events](#domain-events-vs-integration-events)
3. [Event Dispatch Strategiyaları](#event-dispatch-strategiyaları)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [Outbox Pattern ilə inteqrasiya](#outbox-pattern-ilə-inteqrasiya)
6. [Event Sourcing ilə əlaqə](#event-sourcing-ilə-əlaqə)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Domain Events nədir?

```
Domain Event — domain-da baş vermiş vacib bir şeyin qeydi.
Keçmiş zaman (past tense): OrderPlaced, PaymentFailed, ItemShipped

✅ İstifadə:
  - Domain-da vacib state dəyişiklikləri
  - Aggregate-lərarası kommunikasiya
  - Audit trail
  - Side effects-i decoupled şəkildə trigger etmək

❌ İstifadə etmə:
  - Hər property dəyişikliyi üçün
  - Query/read əməliyyatları üçün
  - Infrastructure events üçün (DB connection lost)
```

**Nümunə:**

```
Order.confirm() → OrderConfirmed event

OrderConfirmed event-i:
  → Inventory-ə: stoku rezerv et
  → Email service-ə: confirmation email göndər
  → Analytics-ə: satış statistikasını yenilə

Bunların hamısı decoupled:
  Order domain-ı bu servislər haqqında heç nə bilmir!
```

---

## Domain Events vs Integration Events

```
Domain Events:
  → Bounded Context daxilində
  → In-process (eyni process)
  → Synchronous dispatch (adətən)
  → Technical details ola bilər
  → Rollback olunabilər (TX-da)

Integration Events:
  → Bounded Context-lər arasında
  → Out-of-process (message broker)
  → Asynchronous
  → Public contract (versioning vacibdir)
  → Rollback edilə bilməz (at-least-once)

┌──────────────────────────────────────────────┐
│           Order Bounded Context              │
│                                              │
│  Order.confirm()                             │
│       ↓                                      │
│  [Domain Event: OrderConfirmed]              │
│       ↓                                      │
│  [Handler: UpdateOrderProjection]  ← in-BC  │
│  [Handler: PublishIntegrationEvent] ←        │
│                   ↓                          │
└───────────────────┼──────────────────────────┘
                    │ Message Broker
          ┌─────────▼──────────┐
          │ Integration Event: │
          │ OrderConfirmedV1   │
          └─────────┬──────────┘
                    │
      ┌─────────────┼─────────────┐
      ▼             ▼             ▼
  Inventory    Email Service   Analytics
```

---

## Event Dispatch Strategiyaları

```
1. Immediate Dispatch (Transaction daxilində)
   Pros: Sadə, synchronous
   Cons: Handler xətası TX-ı rollback edə bilər

2. Post-Transaction Dispatch
   Pros: TX başarılı olarsa dispatch
   Cons: Crash olsa events itirilə bilər

3. Outbox Pattern (Transactional Outbox)
   Pros: At-least-once delivery, reliable
   Cons: Eventual consistency, polling overhead
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Base Domain Event
abstract class DomainEvent
{
    public readonly string $eventId;
    public readonly \DateTimeImmutable $occurredAt;
    
    public function __construct()
    {
        $this->eventId    = Str::uuid()->toString();
        $this->occurredAt = new \DateTimeImmutable();
    }
    
    abstract public function eventType(): string;
    abstract public function toPayload(): array;
}

// Konkret event-lər
class OrderConfirmed extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly int $totalAmount,
        public readonly string $currency,
    ) {
        parent::__construct();
    }
    
    public function eventType(): string
    {
        return 'order.confirmed';
    }
    
    public function toPayload(): array
    {
        return [
            'order_id'     => $this->orderId,
            'customer_id'  => $this->customerId,
            'total_amount' => $this->totalAmount,
            'currency'     => $this->currency,
            'occurred_at'  => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

class OrderCancelled extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
    ) {
        parent::__construct();
    }
    
    public function eventType(): string { return 'order.cancelled'; }
    
    public function toPayload(): array
    {
        return [
            'order_id'    => $this->orderId,
            'reason'      => $this->reason,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

// Aggregate-də event toplama
trait RecordsDomainEvents
{
    private array $domainEvents = [];
    
    protected function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
    
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
    
    public function peekDomainEvents(): array
    {
        return $this->domainEvents;
    }
}

class Order
{
    use RecordsDomainEvents;
    
    public function confirm(): void
    {
        // ... validation ...
        $this->status = OrderStatus::CONFIRMED;
        
        $this->recordEvent(new OrderConfirmed(
            $this->id->value,
            $this->customerId,
            $this->total->amount,
            $this->total->currency,
        ));
    }
}

// Domain Event Dispatcher
interface DomainEventDispatcher
{
    public function dispatch(DomainEvent $event): void;
    public function dispatchAll(array $events): void;
}

class SynchronousDomainEventDispatcher implements DomainEventDispatcher
{
    private array $handlers = [];
    
    public function register(string $eventClass, callable $handler): void
    {
        $this->handlers[$eventClass][] = $handler;
    }
    
    public function dispatch(DomainEvent $event): void
    {
        $eventClass = get_class($event);
        
        foreach ($this->handlers[$eventClass] ?? [] as $handler) {
            $handler($event);
        }
    }
    
    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}

// Event Handlers
class InventoryReservationHandler
{
    public function __construct(private InventoryService $inventory) {}
    
    public function __invoke(OrderConfirmed $event): void
    {
        $this->inventory->reserveForOrder($event->orderId);
    }
}

class OrderConfirmationEmailHandler
{
    public function __construct(
        private EmailService $emailService,
        private CustomerRepository $customers,
    ) {}
    
    public function __invoke(OrderConfirmed $event): void
    {
        $customer = $this->customers->findById($event->customerId);
        $this->emailService->sendOrderConfirmation($customer->email, $event->orderId);
    }
}

// Application Service — post-transaction dispatch
class OrderApplicationService
{
    public function __construct(
        private OrderRepository $orders,
        private DomainEventDispatcher $dispatcher,
    ) {}
    
    public function confirmOrder(string $orderId): void
    {
        $events = [];
        
        DB::transaction(function () use ($orderId, &$events) {
            $order = $this->orders->findById(new OrderId($orderId));
            $order->confirm();
            $this->orders->save($order);
            
            // TX daxilindən event-ləri çıxar, amma dispatch etmə
            $events = $order->pullDomainEvents();
        });
        
        // TX uğurlu olandan sonra dispatch et
        $this->dispatcher->dispatchAll($events);
    }
}
```

---

## Outbox Pattern ilə inteqrasiya

*Outbox Pattern ilə inteqrasiya üçün kod nümunəsi:*
```php
// Reliable event dispatch üçün — Outbox Pattern kombinasiyası

class OutboxDomainEventPublisher implements DomainEventDispatcher
{
    public function dispatch(DomainEvent $event): void
    {
        // Outbox-a yaz (caller-in TX-ı daxilindədir!)
        OutboxMessage::create([
            'id'             => $event->eventId,
            'aggregate_type' => 'Order',
            'event_type'     => $event->eventType(),
            'payload'        => json_encode($event->toPayload()),
            'status'         => 'pending',
        ]);
    }
    
    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}

// Repository-də istifadə (TX daxilindədir)
class EloquentOrderRepository implements OrderRepository
{
    public function __construct(
        private OutboxDomainEventPublisher $publisher
    ) {}
    
    public function save(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // 1. Order persist et
            $this->persistOrder($order);
            
            // 2. Events-i outbox-a yaz (eyni TX-da!)
            $this->publisher->dispatchAll($order->pullDomainEvents());
        });
    }
}
```

---

## Event Sourcing ilə əlaqə

```
Event Sourcing:
  Aggregate state-i event log-dan rebuild et
  
Normal DDD:                     Event Sourcing DDD:
  Order state DB-dədir            Yalnız events DB-dədir
  Events side effect-dir          Events primary storage-dır
  
  Order{                          events = [
    id: 123,                        OrderCreated{...},
    status: CONFIRMED,              ItemAdded{...},
    items: [...]                    ItemAdded{...},
  }                                 OrderConfirmed{...}
                                  ]
                                  
                                  Order = events.reduce()

Event Sourcing nə zaman:
  ✅ Audit trail kritikdir (banking, medical)
  ✅ Temporal queries (keçmişdəki state-i gör)
  ✅ Event replay (bug fix sonrası yenidən işlə)
  ❌ Simple CRUD
  ❌ Team Event Sourcing-i bilmirsə
```

---

## İntervyu Sualları

**1. Domain Event nədir, niyə istifadə edilir?**
Domain-da baş vermiş vacib bir şeyin immutable qeydi (past tense). Aggregate-lərarası loose coupling-i təmin edir. Order.confirm() çağrılır, OrderConfirmed event publish olur, Inventory handler onu dinləyir. Order domain-ı Inventory haqqında heç nə bilmir.

**2. Domain Events vs Integration Events fərqi nədir?**
Domain Events: BC daxilindədir, in-process, sync, rollback edilə bilər. Integration Events: BC-lər arası, message broker üzərindən, async, public contract, rollback edilə bilməz. Adətən Domain Event → handler → Integration Event publish.

**3. Event-ləri nə vaxt dispatch etmək lazımdır?**
Transaction daxilindən kənarda — TX commit-dən sonra. Əks halda handler xətası TX-ı rollback edər. Reliable delivery üçün Outbox Pattern: events eyni TX-da DB-yə yazılır, sonra ayrı relay prosesi broker-ə göndərir.

**4. Domain Event-lər idempotent olmalıdırmı?**
Handler-lər idempotent olmalıdır, çünki at-least-once delivery. Eyni event bir neçə dəfə gələ bilər. Handler: əvvəlcə işlənibmi yoxla (Inbox Pattern), sonra iş gör.

**5. Event Sourcing nədir, Domain Events-dən nə fərqi var?**
Domain Events-də state DB-dədir, events side effect. Event Sourcing-də state yalnız events-dən rebuild edilir. Event Sourcing nə zaman: audit trail kritik, temporal queries lazım, event replay lazım. Əksinə: sadə CRUD-da overhead çoxdur.

**6. Domain Event versioning necə idarə edilir?**
Integration Event kimi xarici sistemlərə göndərilən event-lər versioning tələb edir. Additive changes (yeni sahə əlavə etmək) backward compatible-dır. Breaking changes üçün yeni versiya: `OrderConfirmedV2`. Consumer-lar köhnə versiyaları handle etməyə davam etməlidir. Event schema registry (Confluent Schema Registry) istifadə edilə bilər.

**7. Laravel-də Domain Event-i `event()` helper ilə göndərmək doğrudurmu?**
`event(new OrderConfirmed(...))` — Laravel event-ini synchronous dispatch edir, handler-lar anında çalışır. Domain Event üçün bu yanlışdır: handler xətası transaction-ı rollback edər. Düzgün yol: events-i toplayıb (`pullDomainEvents()`) transaction commit-dən sonra dispatch etmək. Ya da Outbox Pattern ilə tam etibarlı delivery.

---

## Anti-patternlər

**1. Domain event-ləri transaction daxilində dispatch etmək**
`$order->confirm()` çağrılır, dərhal event handler-lar çalışır, handler xəta verir, transaction rollback olur — amma event artıq xarici sistemə göndərilib. Event-ləri transaction commit-dən sonra dispatch edin; etibarlı çatdırılma üçün Outbox Pattern tətbiq edin.

**2. Domain Events-i Integration Events ilə qarışdırmaq**
Bounded context daxili `OrderItemAdded` event-ini birbaşa message broker-ə publish etmək — daxili implementation detalları public contract olur. Domain Event → handler → Integration Event axını qurun: daxili event dəyişə bilər, xarici contract sabit qalır.

**3. Event handler-ları idempotent yazmamaq**
Broker at-least-once delivery verir — eyni event iki dəfə gəlir, handler iki dəfə email göndərir, iki dəfə inventar azaldır. Hər handler-a Inbox Pattern tətbiq edin: event id-si unikal olaraq DB-də saxlanılsın, işlənibsə skip edilsin.

**4. Past tense əvəzinə present/future tense event adları**
`ConfirmOrder`, `SendEmail` kimi event adları — event komanda deyil, baş vermiş faktdır. `OrderConfirmed`, `EmailSent` kimi past tense istifadə edin; bu, event-in immutable, keçmiş fakt olduğunu ifadə edir.

**5. Event payload-ına həddən artıq data daxil etmək**
Bütün aggregate state-ini event-ə sıxışdırmaq — event schema dəyişdikdə bütün consumer-lar pozulur, PII data broker-də qalır. Event-ə yalnız ID və dəyişən sahələri daxil edin; consumer əlavə data lazımdırsa API-dan sorgulsun (Event-Carried State Transfer-i şüurlu seçin).

**6. Domain event-ləri log etməmək**
Event-lər fire-and-forget göndərilir — hansı event-in dispatch edildiyi, hansı handler-ın işlədiyi bilinmir, debug çətinləşir. Bütün domain event-ləri structured log-layın: event adı, aggregate id, timestamp, handler nəticəsi izlənilsin.
