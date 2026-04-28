# DDD Domain Events (Senior ⭐⭐⭐)

## İcmal

Domain Event — domain-da baş vermiş vacib bir şeyin immutable qeydidir. Keçmiş zaman (past tense) ilə adlandırılır: `OrderPlaced`, `PaymentFailed`, `ItemShipped`. Aggregate-lərarası loose coupling-i təmin edir — Order domain-ı Inventory haqqında heç nə bilmədən `OrderConfirmed` event yayımlayır, Inventory handler həmin event-ə subscribe olur.

```
Order.confirm() → OrderConfirmed event

OrderConfirmed event-i:
  → Inventory-ə: stoku rezerv et
  → Email service-ə: confirmation email göndər
  → Analytics-ə: satış statistikasını yenilə

Bunların hamısı decoupled:
  Order domain-ı bu servislər haqqında heç nə bilmir!
```

## Niyə Vacibdir

Domain Event olmadan aggregate-lərarası side effect-lər Application Service-ə yazılır: `$inventoryService->reserve()`, `$emailService->sendConfirmation()` — Order service-i Inventory-ə və Email-ə birbaşa asılı olur. Domain Event bu coupling-i aradan qaldırır: Order "nə baş verdi" bildirir, kim reaksiya verəcəyi bilmir.

**Domain Events vs Integration Events:**
```
Domain Events:
  → Bounded Context daxilində
  → In-process (eyni process)
  → Synchronous dispatch (adətən)
  → Rollback olunabilər (TX-da)

Integration Events:
  → Bounded Context-lər arasında
  → Out-of-process (message broker)
  → Asynchronous
  → Public contract (versioning vacibdir)
  → Rollback edilə bilməz (at-least-once)
```

## Əsas Anlayışlar

**Event Dispatch Strategiyaları:**
```
1. Immediate Dispatch (Transaction daxilində)
   Pros: Sadə, synchronous
   Cons: Handler xətası TX-ı rollback edər

2. Post-Transaction Dispatch
   Pros: TX başarılı olarsa dispatch
   Cons: Crash olsa events itirilə bilər

3. Outbox Pattern (Transactional Outbox)
   Pros: At-least-once delivery, reliable
   Cons: Eventual consistency, polling overhead
```

**Naming Convention:**
- Keçmiş zaman: `OrderPlaced`, `OrderConfirmed`, `PaymentFailed`
- Present tense (`ConfirmOrder`) — bu command-dır, event deyil
- Past tense ifadə edir: "bu artıq baş verdi, geri alınmaz"

## Praktik Baxış

**Real istifadə:**
- `OrderPlaced` → Inventory reserve, email göndər, analytics
- `PaymentFailed` → Sifarişi cancel et, notification göndər
- `UserRegistered` → Welcome email, onboarding flow
- `SubscriptionExpired` → Access revoke, renewal email

**Trade-off-lar:**
- Loose coupling, decoupled side effects — güclü tərəf
- Lakin: handler sırası qeyri-müəyyən, debug çətinləşir, eventual consistency

**İstifadə etməmək:**
- Query/read əməliyyatları üçün event
- Hər property dəyişikliyi üçün event — event flood
- Infrastructure events üçün (DB connection lost) — bu domain event deyil

**Common mistakes:**
- Event-i transaction daxilindən dispatch etmək — handler xətası TX rollback edir
- Domain Event-i Integration Event kimi birbaşa broker-ə göndərmək — daxili detallar public olur
- Handler-ları idempotent yazmamaq — at-least-once delivery, duplicate handling lazımdır
- Past tense əvəzinə present/future tense adlar

**Anti-Pattern Nə Zaman Olur?**

- **Hər DB dəyişikliyi üçün event publish etmək** — `UserNameUpdated`, `OrderNoteAdded`, `ProductDescriptionChanged` kimi trivial dəyişikliklər üçün event flood yaranır. Handler-lar artıq əhəmiyyətsiz event-lərə subscribe olur, sistem noise-la dolur. Domain Event yalnız domain üçün mühüm olan, side effect tələb edən dəyişikliklər üçündür.
- **Transaction daxilindən event dispatch** — `$order->confirm()` çağrılır, dərhal event handler-lar çalışır, handler xəta verir, transaction rollback olur — amma event artıq xarici sistemə göndərilib. Event-ləri transaction commit-dən sonra dispatch edin.
- **Domain Event-i Integration Event ilə qarışdırmaq** — BC daxili `OrderItemAdded` event-ini birbaşa message broker-ə publish etmək. Domain Event → handler → Integration Event axını qurun: daxili event dəyişə bilər, xarici contract sabit qalır.
- **Handler-ları idempotent yazmamaq** — broker at-least-once delivery verir. Eyni event iki dəfə gəlsə iki dəfə email gedər, iki dəfə inventar azalar. Inbox Pattern tətbiq edin.

## Nümunələr

### Ümumi Nümunə

Order.confirm() çağrıldıqda Ordering BC, Inventory-nin stok miqdarını birbaşa bilmir. Sadəcə `OrderConfirmed` event yayımlayır. Inventory BC həmin event-ə subscribe olur, öz transaksiyasında stoku azaldır. Əgər Inventory service down olsa, event queue-da gözləyir — Ordering bloklanmır.

### PHP/Laravel Nümunəsi

**Base Domain Event + Konkret event-lər:**

```php
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

class OrderConfirmed extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly int    $totalAmount,
        public readonly string $currency,
    ) {
        parent::__construct();
    }

    public function eventType(): string { return 'order.confirmed'; }

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
```

**Aggregate-də event toplama (trait):**

```php
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
```

**Post-transaction dispatch — düzgün üsul:**

```php
class OrderApplicationService
{
    public function __construct(
        private OrderRepository        $orders,
        private DomainEventDispatcher  $dispatcher,
    ) {}

    public function confirmOrder(string $orderId): void
    {
        $events = [];

        DB::transaction(function () use ($orderId, &$events) {
            $order = $this->orders->findById(new OrderId($orderId));
            $order->confirm();
            $this->orders->save($order);

            // TX daxilindən event-ləri çıxar, amma dispatch ETMƏ
            $events = $order->pullDomainEvents();
        });

        // TX uğurlu olandan sonra dispatch et
        $this->dispatcher->dispatchAll($events);
    }
}
```

**Event Handlers:**

```php
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
        private EmailService       $emailService,
        private CustomerRepository $customers,
    ) {}

    public function __invoke(OrderConfirmed $event): void
    {
        $customer = $this->customers->findById($event->customerId);
        $this->emailService->sendOrderConfirmation($customer->email, $event->orderId);
    }
}
```

**Outbox Pattern — reliable delivery:**

```php
// Outbox-a yaz (caller-in TX-ı daxilindədir!)
class OutboxDomainEventPublisher
{
    public function dispatch(DomainEvent $event): void
    {
        OutboxMessage::create([
            'id'             => $event->eventId,
            'aggregate_type' => 'Order',
            'event_type'     => $event->eventType(),
            'payload'        => json_encode($event->toPayload()),
            'status'         => 'pending',
        ]);
    }
}

// Repository-də istifadə — TX daxilindədir!
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
        // Ayrı relay prosesi outbox-u poll edir, broker-ə göndərir
    }
}

// Idempotent handler — inbox pattern
class IdempotentEventHandler
{
    public function handle(DomainEvent $event, callable $handler): void
    {
        if ($this->processedEvents->hasBeenProcessed($event->eventId)) {
            return; // Artıq işlənib, ötür
        }

        $handler($event);

        $this->processedEvents->markAsProcessed($event->eventId);
    }
}
```

**Domain Event → Integration Event axını:**

```php
// Domain Event handler-ı Integration Event yayımlayır
class PublishIntegrationEventOnOrderConfirmed
{
    public function __construct(private MessageBus $messageBus) {}

    public function __invoke(OrderConfirmed $event): void
    {
        // Daxili domain event → xarici integration event
        // Xarici consumer-lar daxili detalları bilmir
        $this->messageBus->publish(new OrderConfirmedIntegrationEventV1(
            orderId: $event->orderId,
            customerId: $event->customerId,
            total: $event->totalAmount,
            currency: $event->currency,
        ));
    }
}
```

## Praktik Tapşırıqlar

1. **Post-TX dispatch** — mövcud layihənizdə `event()` helper-ini `DB::transaction` daxilindən çıxarın; `pullDomainEvents()` pattern-ini tətbiq edin; dispatch-i TX-dan sonraya keçirin.
2. **Outbox Pattern** — `outbox_messages` cədvəli yaradın; repository-nin `save()` metodunda eyni TX-da event-ləri outbox-a yazın; `OutboxRelayCommand` (Artisan) yazın — pending message-ləri broker-ə göndərsin.
3. **Idempotent handler** — `processed_events` cədvəli yaradın; hər handler-da event ID yoxlayın; duplikat gəlsə skip edin; test üçün eyni event iki dəfə dispatch edin.
4. **Domain → Integration Event** — `OrderConfirmed` domain event-dən `OrderConfirmedV1` integration event-ə adapter handler yazın; versioning strategiyasını planlaşdırın.

## Əlaqəli Mövzular

- [DDD Overview](01-ddd.md) — domain event-in DDD-dəki rolu
- [Aggregates](04-ddd-aggregates.md) — event-lər aggregate-dən çıxır
- [Bounded Context](06-ddd-bounded-context.md) — domain vs integration events
- [CQRS](../integration/01-cqrs.md) — event-driven read model yeniləmə
- [Event Sourcing](../integration/02-event-sourcing.md) — events-i primary storage kimi
- [Outbox Pattern](../integration/04-outbox-pattern.md) — reliable event delivery
- [Saga Pattern](../integration/03-saga-pattern.md) — multi-aggregate coordination
- [Event Listener](../laravel/05-event-listener.md) — Laravel event-listener sistemi
- [Choreography vs Orchestration](../integration/11-choreography-vs-orchestration.md) — event-based coordination
