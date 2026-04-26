# Event-Driven Architecture (Middle)

## İcmal

Event-Driven Architecture (EDA) sistemdəki komponentlərin event-lər vasitəsilə
əlaqə saxladığı arxitektura yanaşmasıdır. Bir komponent event yaradır (publish),
digər komponentlər bu event-ə reaksiya verir (subscribe). Bu, loose coupling
təmin edir - producer consumer-in kim olduğunu bilmir.

Sadə dillə: qəzet nəşriyyatı kimi düşünün - qəzet çap olunur (event), abunəçilər
(subscribers) onu alıb oxuyur. Nəşriyyat hər abunəçini tanımır.

```
┌──────────┐   Event    ┌───────────┐   Event    ┌──────────┐
│ Producer │ ────────── │ Event Bus │ ────────── │ Consumer │
│          │            │           │            │          │
│ OrderSvc │ ────────── │  Kafka /  │ ────────── │ EmailSvc │
│          │  "order    │  RabbitMQ │  "order    │          │
└──────────┘  created"  └───────────┘  created"  └──────────┘
                              │
                              │         ┌──────────┐
                              └──────── │ InvSvc   │
                               "order   │          │
                               created" └──────────┘
```


## Niyə Vacibdir

Request/response modeli tight coupling yaradır; EDA producer-consumer arasında decoupling təmin edir. Event sourcing audit log-u pulsuz əldə edir; CQRS read/write modellərini ayrı optimize etməyə imkan verir. Laravel Events, Horizon, Kafka — real layihələrdə bu prinsiplər üzərindədir.

## Əsas Anlayışlar

### Event Types

**1. Domain Events:**
Business logic-də baş verən hadisələr.
```php
class OrderPlaced
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $userId,
        public readonly float $total,
        public readonly array $items,
        public readonly \DateTimeImmutable $occurredAt
    ) {}
}
```

**2. Integration Events:**
Service-lər arası communication üçün events.
```php
class OrderPlacedIntegrationEvent
{
    public string $eventId;
    public string $eventType = 'order.placed';
    public string $source = 'order-service';
    public array $data;
    public string $timestamp;
}
```

**3. Notification Events:**
Sadəcə "nəsə baş verdi" xəbərdarlığı, minimal data ilə.
```php
class OrderStatusChanged
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $newStatus
    ) {}
}
```

### Event Sourcing

Sistem vəziyyətini (state) event-lərin ardıcıllığı kimi saxlamaq. Current state =
bütün event-lərin replay edilməsi.

```
Traditional:                    Event Sourcing:
┌─────────────────┐            ┌─────────────────────────────┐
│ accounts table  │            │ events table                │
│ id | balance    │            │ id | type        | data     │
│ 1  | 850        │            │ 1  | created     | bal:1000 │
│                 │            │ 2  | withdrawn   | amt:200  │
│ (only current   │            │ 3  | deposited   | amt:50   │
│  state)         │            │ (full history)              │
└─────────────────┘            └─────────────────────────────┘
                               Current: 1000 - 200 + 50 = 850
```

```php
// Event Store
class EventStore
{
    public function append(string $aggregateId, DomainEvent $event): void
    {
        DB::table('event_store')->insert([
            'aggregate_id' => $aggregateId,
            'event_type' => get_class($event),
            'payload' => json_encode($event->toArray()),
            'version' => $this->getNextVersion($aggregateId),
            'created_at' => now(),
        ]);
    }

    public function getEvents(string $aggregateId): Collection
    {
        return DB::table('event_store')
            ->where('aggregate_id', $aggregateId)
            ->orderBy('version')
            ->get()
            ->map(fn ($row) => $this->deserialize($row));
    }
}

// Aggregate rebuilt from events
class BankAccount
{
    private float $balance = 0;
    private array $uncommittedEvents = [];

    public static function reconstruct(array $events): self
    {
        $account = new self();
        foreach ($events as $event) {
            $account->apply($event);
        }
        return $account;
    }

    public function withdraw(float $amount): void
    {
        if ($amount > $this->balance) {
            throw new InsufficientFundsException();
        }
        $this->recordEvent(new MoneyWithdrawn($amount));
    }

    private function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof AccountCreated => $this->balance = $event->initialBalance,
            $event instanceof MoneyDeposited => $this->balance += $event->amount,
            $event instanceof MoneyWithdrawn => $this->balance -= $event->amount,
        };
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->apply($event);
        $this->uncommittedEvents[] = $event;
    }
}
```

### CQRS (Command Query Responsibility Segregation)

Read və write əməliyyatlarını fərqli model-lər ilə idarə etmək.

```
                    ┌──────────────────┐
                    │     Client       │
                    └────┬────────┬────┘
                         │        │
                  Command│        │Query
                         │        │
                    ┌────┴──┐ ┌───┴────┐
                    │ Write │ │  Read  │
                    │ Model │ │  Model │
                    └───┬───┘ └───┬────┘
                        │         │
                    ┌───┴───┐ ┌───┴────┐
                    │ Write │ │  Read  │
                    │  DB   │ │   DB   │
                    │(MySQL)│ │(Elastic│
                    └───┬───┘ │  /Redis)│
                        │     └────────┘
                        │         ▲
                        └─────────┘
                     Event Sync (async)
```

```php
// Command Side
class PlaceOrderCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly array $items,
        public readonly string $shippingAddress
    ) {}
}

class PlaceOrderHandler
{
    public function handle(PlaceOrderCommand $command): string
    {
        $order = Order::create([
            'user_id' => $command->userId,
            'items' => $command->items,
            'status' => 'pending',
        ]);

        event(new OrderPlaced($order));

        return $order->id;
    }
}

// Query Side
class OrderQueryService
{
    public function __construct(private \Redis $redis) {}

    public function getOrderSummary(string $orderId): array
    {
        $cached = $this->redis->get("order:summary:{$orderId}");
        if ($cached) {
            return json_decode($cached, true);
        }

        // Denormalized read model-dən oxu
        return DB::connection('read_replica')
            ->table('order_summaries')
            ->where('order_id', $orderId)
            ->first();
    }
}

// Projector - Write DB-dən Read DB-yə sync
class OrderSummaryProjector
{
    public function onOrderPlaced(OrderPlaced $event): void
    {
        DB::connection('read')->table('order_summaries')->insert([
            'order_id' => $event->orderId,
            'user_name' => $event->userName,
            'total' => $event->total,
            'item_count' => count($event->items),
            'status' => 'pending',
        ]);
    }

    public function onOrderShipped(OrderShipped $event): void
    {
        DB::connection('read')->table('order_summaries')
            ->where('order_id', $event->orderId)
            ->update(['status' => 'shipped']);
    }
}
```

### Eventual Consistency

Distributed sistemdə bütün node-ların nəhayət eyni state-ə gəlməsi:

```
Time 0: Order Service creates order
Time 1: Event published to message bus
Time 2: Payment Service receives event, processes payment
Time 3: Inventory Service receives event, reserves stock
Time 4: All services are consistent (eventually)

Latency: milliseconds to seconds
```

## Arxitektura

### Full Event-Driven System

```
┌─────────┐    ┌─────────┐    ┌──────────┐
│  Web    │    │ Mobile  │    │  Admin   │
│  App    │    │  App    │    │  Panel   │
└────┬────┘    └────┬────┘    └────┬─────┘
     │              │              │
     └──────────────┼──────────────┘
                    │
            ┌───────┴────────┐
            │   API Gateway  │
            └───────┬────────┘
                    │
     ┌──────────────┼──────────────┐
     │              │              │
┌────┴────┐   ┌────┴────┐   ┌────┴────┐
│ Command │   │  Query  │   │  Event  │
│ Service │   │ Service │   │ Store   │
└────┬────┘   └────┬────┘   └─────────┘
     │              │
┌────┴────┐   ┌────┴────┐
│ Write DB│   │ Read DB │
└────┬────┘   └─────────┘
     │              ▲
     │    ┌─────────┴──────────┐
     └────┤   Event Bus        │
          │   (Kafka/RabbitMQ) │
          └────────┬───────────┘
                   │
     ┌─────────────┼─────────────┐
     │             │             │
┌────┴────┐  ┌────┴────┐  ┌────┴────┐
│ Email   │  │Analytics│  │ Search  │
│ Worker  │  │ Worker  │  │ Indexer │
└─────────┘  └─────────┘  └─────────┘
```

## Nümunələr

### Laravel Events və Listeners

```php
// app/Events/OrderPlaced.php
class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("user.{$this->order->user_id}");
    }
}

// app/Listeners/SendOrderConfirmation.php
class SendOrderConfirmation implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(OrderPlaced $event): void
    {
        $event->order->user->notify(
            new OrderConfirmationNotification($event->order)
        );
    }
}

// app/Listeners/ReserveInventory.php
class ReserveInventory implements ShouldQueue
{
    public string $queue = 'inventory';
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(OrderPlaced $event): void
    {
        foreach ($event->order->items as $item) {
            $product = Product::lockForUpdate()->find($item->product_id);
            $product->decrement('stock', $item->quantity);
        }
    }

    public function failed(OrderPlaced $event, \Throwable $e): void
    {
        Log::error("Failed to reserve inventory for order {$event->order->id}", [
            'error' => $e->getMessage(),
        ]);
        // Trigger compensating action
        event(new InventoryReservationFailed($event->order));
    }
}

// app/Listeners/UpdateAnalytics.php
class UpdateAnalytics implements ShouldQueue
{
    public string $queue = 'analytics';

    public function handle(OrderPlaced $event): void
    {
        OrderAnalytics::create([
            'order_id' => $event->order->id,
            'amount' => $event->order->total,
            'category' => $event->order->primary_category,
            'region' => $event->order->shipping_region,
        ]);
    }
}

// app/Providers/EventServiceProvider.php
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPlaced::class => [
            SendOrderConfirmation::class,
            ReserveInventory::class,
            UpdateAnalytics::class,
            UpdateSearchIndex::class,
        ],
        PaymentReceived::class => [
            UpdateOrderStatus::class,
            SendPaymentReceipt::class,
        ],
        OrderShipped::class => [
            SendShippingNotification::class,
            UpdateDeliveryTracking::class,
        ],
    ];
}
```

### Laravel Broadcasting (Real-time Events)

```php
// Broadcasting event to frontend
class OrderStatusUpdated implements ShouldBroadcast
{
    public function __construct(
        public readonly Order $order,
        public readonly string $newStatus
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->order->id}"),
            new PrivateChannel("user.{$this->order->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->newStatus,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}

// Frontend (Laravel Echo)
// Echo.private(`order.${orderId}`)
//     .listen('.order.status.updated', (e) => {
//         console.log('Order status:', e.status);
//     });
```

## Real-World Nümunələr

1. **LinkedIn** - Kafka ilə activity feed, notification events, analytics
2. **Netflix** - Event sourcing ilə user activity tracking
3. **Uber** - Real-time ride events, driver location updates
4. **Amazon** - Order lifecycle events across hundreds of services
5. **Spotify** - User listening events for recommendations

## Praktik Tapşırıqlar

**S1: Event Sourcing ilə traditional CRUD arasındakı fərq nədir?**
C: CRUD-da yalnız current state saxlanır, data dəyişdikdə köhnə state itirilir.
Event Sourcing-da bütün dəyişikliklər event kimi saxlanır, istənilən zamanda state
rebuild edilə bilər. Audit log pulsuz gəlir, time-travel debugging mümkündür.

**S2: CQRS nə vaxt istifadə edilməlidir?**
C: Read və write pattern-ləri çox fərqli olanda (məsələn, çox read, az write),
complex domain logic olanda, read model-in denormalized olması lazım olanda.
Sadə CRUD tətbiqləri üçün CQRS over-engineering ola bilər.

**S3: Eventual consistency problemi necə həll olunur?**
C: UI-da optimistic updates, background sync, conflict resolution strategiyaları
(last-write-wins, merge), saga pattern ilə compensating transactions,
idempotent consumers.

**S4: Event ordering necə təmin olunur?**
C: Kafka partition key ilə eyni entity-nin event-ləri eyni partition-a düşür
və sıra saxlanır. Sequence number və version ilə consumer tərəfdə ordering
təmin edilir.

**S5: Dead letter queue nədir?**
C: İşlənə bilməyən (failed) mesajların göndərildiyi xüsusi queue-dur. Retry
limitinə çatmış mesajlar buraya düşür, manual araşdırma və ya sonradan
reprocessing üçün saxlanır.

**S6: Outbox pattern nədir?**
C: Database dəyişikliyi və event publishing-i atomik etmək üçün pattern-dir.
Event əvvəlcə outbox table-ına yazılır (eyni transaction-da), sonra ayrı
process bu table-dan oxuyub message bus-a publish edir.

**S7: Event-driven arxitekturanın dezavantajları nədir?**
C: Debugging çətindir (distributed flow), eventual consistency complexity,
event ordering problemləri, idempotency təmin etmək lazımdır, sistem
davranışını anlamaq çətinləşir (event storm).

## Praktik Baxış

1. **Event Naming** - Past tense istifadə edin: OrderPlaced, PaymentReceived
2. **Event Schema Versioning** - Schema dəyişdikdə backward compatibility saxlayın
3. **Idempotent Consumers** - Eyni event-i iki dəfə işləmək eyni nəticə verməlidir
4. **Dead Letter Queue** - Failed events-i ayrı queue-ya göndərin
5. **Correlation ID** - Event chain-i track etmək üçün
6. **Event Size** - Event-ləri kiçik saxlayın, böyük data üçün reference istifadə edin
7. **Schema Registry** - Event schema-ları mərkəzi yerdə saxlayın
8. **Monitoring** - Event lag, consumer health, failed events track edin
9. **Outbox Pattern** - DB write və event publish atomik olsun
10. **Event Replay** - Production event-ləri test mühitində replay edə bilin


## Əlaqəli Mövzular

- [Message Queues](05-message-queues.md) — event transport qatı
- [Microservices](10-microservices.md) — EDA-nın əsas istifadə yeri
- [CDC & Outbox](46-cdc-outbox-pattern.md) — DB dəyişikliyini event-ə çevirmək
- [Stream Processing](54-stream-processing.md) — event axınını real-time analiz etmək
- [Pub/Sub](81-pubsub-system-design.md) — event fan-out dizaynı
