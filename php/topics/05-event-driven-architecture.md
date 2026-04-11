# Event Driven Architecture (EDA)

## Mündəricat
1. [Event Driven Architecture nədir](#event-driven-architecture-nədir)
2. [Event vs Command vs Query fərqi](#event-vs-command-vs-query-fərqi)
3. [Event Types](#event-types)
4. [Event Sourcing](#event-sourcing)
5. [Event Store](#event-store)
6. [Eventual Consistency](#eventual-consistency)
7. [Saga Pattern](#saga-pattern)
8. [Choreography vs Orchestration](#choreography-vs-orchestration)
9. [Laravel-də Event Sistemi](#laravel-də-event-sistemi)
10. [Event Broadcasting](#event-broadcasting)
11. [Async Event Handling](#async-event-handling)
12. [Event-Driven Microservices](#event-driven-microservices)
13. [Real-world Laravel Nümunələri](#real-world-laravel-nümunələri)
14. [Event Replay](#event-replay)
15. [Dead Letter Queue](#dead-letter-queue)
16. [Idempotency in Event Handling](#idempotency-in-event-handling)
17. [Event Versioning](#event-versioning)
18. [Üstünlükləri və Mənfi Cəhətləri](#üstünlükləri-və-mənfi-cəhətləri)
19. [İntervyu Sualları və Cavabları](#intervyu-sualları-və-cavabları)

---

## Event Driven Architecture nədir

Event Driven Architecture (EDA) - sistemin komponentlərinin bir-biri ilə **hadisələr (events)** vasitəsilə əlaqə qurduğu arxitektura yanaşmasıdır. Ənənəvi sinxron çağırışlar əvəzinə, bir komponent hadisə yaradır (publish), digər komponentlər isə həmin hadisəyə reaksiya verir (subscribe).

**Əsas prinsiplər:**
- **Loose Coupling** - komponentlər bir-birindən asılı deyil, yalnız hadisələr vasitəsilə əlaqə qurur
- **Asynchronous Communication** - hadisələr asinxron emal olunur
- **Event-first thinking** - sistem dizaynında ilk olaraq hadisələr düşünülür

**Sadə analogiya:** Restoranda sifariş verəndə siz ofisiantdan yeməyi birbaşa hazırlamağı xahiş etmirsiniz. Sifariş yazılır (Event), aşpaz həmin sifarişi görür və hazırlayır (Listener), bar həmin sifarişə əsasən içki hazırlayır (başqa Listener). Heç biri bir-birindən birbaşa asılı deyil.

```
Ənənəvi yanaşma (Direct Call):
OrderService -> InventoryService.updateStock()
OrderService -> EmailService.sendConfirmation()
OrderService -> AnalyticsService.track()

Event Driven yanaşma:
OrderService -> publish(OrderPlaced)
    InventoryListener -> updateStock()
    EmailListener -> sendConfirmation()
    AnalyticsListener -> track()
```

---

## Event vs Command vs Query fərqi

Bu üç konseptin fərqini anlamaq EDA üçün çox vacibdir:

### Event (Hadisə)
- **Keçmişdə baş vermiş** bir şeyi bildirən mesajdır
- Past tense ilə adlandırılır: `OrderPlaced`, `UserRegistered`, `PaymentReceived`
- **Immutable-dir** - dəyişdirilə bilməz
- Bir event-in **bir və ya bir neçə listener-i** ola bilər
- Event publisher listener-ləri tanımır

*- Event publisher listener-ləri tanımır üçün kod nümunəsi:*
```php
// Event - keçmişdə baş vermiş hadisə
class OrderPlaced
{
    public function __construct(
        public readonly Order $order,
        public readonly Carbon $occurredAt,
    ) {}
}
```

### Command (Əmr)
- **Gələcəkdə edilməli olan** bir əməliyyatı bildirən mesajdır
- Imperative mood ilə adlandırılır: `PlaceOrder`, `RegisterUser`, `ProcessPayment`
- **Bir command-in yalnız bir handler-i** olur
- Nəticə qaytara bilər (success/failure)

*- Nəticə qaytara bilər (success/failure) üçün kod nümunəsi:*
```php
// Command - gələcəkdə edilməli əməliyyat
class PlaceOrder
{
    public function __construct(
        public readonly int $userId,
        public readonly array $items,
        public readonly string $shippingAddress,
    ) {}
}

class PlaceOrderHandler
{
    public function handle(PlaceOrder $command): Order
    {
        // Sifarişi yarat
        $order = Order::create([
            'user_id' => $command->userId,
            'shipping_address' => $command->shippingAddress,
        ]);

        foreach ($command->items as $item) {
            $order->items()->create($item);
        }

        // Event yayımla
        event(new OrderPlaced($order, now()));

        return $order;
    }
}
```

### Query (Sorğu)
- **Məlumat tələb edən** mesajdır
- Heç bir side effect yaratmır (CQS prinsipi)
- Adlandırma: `GetOrderDetails`, `FindUserByEmail`, `ListActiveProducts`

*- Adlandırma: `GetOrderDetails`, `FindUserByEmail`, `ListActiveProduct üçün kod nümunəsi:*
```php
// Query - məlumat tələb edən sorğu
class GetOrderDetails
{
    public function __construct(
        public readonly int $orderId,
    ) {}
}

class GetOrderDetailsHandler
{
    public function handle(GetOrderDetails $query): OrderDetailsDTO
    {
        $order = Order::with(['items', 'user', 'payment'])
            ->findOrFail($query->orderId);

        return OrderDetailsDTO::fromModel($order);
    }
}
```

### Müqayisə cədvəli

| Xüsusiyyət | Event | Command | Query |
|---|---|---|---|
| Zaman | Keçmiş | Gələcək | İndiki |
| Handler sayı | 0..N | 1 | 1 |
| Side effect | Yox (özü side effect-dir) | Var | Yox |
| Qaytarış | Yox | Ola bilər | Mütləq var |
| Adlandırma | Past tense | Imperative | Sual forması |

---

## Event Types

### 1. Domain Events

Domain Event-lər **domain daxilində** baş verən hadisələrdir. Bounded Context daxilində istifadə olunur və domain modelinin bir hissəsidir.

*Domain Event-lər **domain daxilində** baş verən hadisələrdir. Bounded  üçün kod nümunəsi:*
```php
// Bu kod domain event-in strukturunu göstərir
namespace App\Domain\Order\Events;

class OrderPlaced implements DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly Money $totalAmount,
        public readonly array $orderItems,
        public readonly DateTimeImmutable $occurredOn,
    ) {}

    public function aggregateId(): string
    {
        return $this->orderId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}

class OrderItemAdded implements DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $productId,
        public readonly int $quantity,
        public readonly Money $unitPrice,
        public readonly DateTimeImmutable $occurredOn,
    ) {}
}

class OrderCancelled implements DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
        public readonly string $cancelledBy,
        public readonly DateTimeImmutable $occurredOn,
    ) {}
}
```

**Domain Event-lərin xüsusiyyətləri:**
- Bounded Context daxilində qalır
- Domain dilində adlandırılır
- Aggregate Root tərəfindən yaradılır
- Domain logic tetikləyir

### 2. Integration Events

Integration Event-lər **fərqli bounded context-lər** və ya **fərqli microservice-lər** arasında əlaqə üçün istifadə olunur. Serializable olmalıdır çünki network üzərindən göndərilir.

*Integration Event-lər **fərqli bounded context-lər** və ya **fərqli mi üçün kod nümunəsi:*
```php
// Bu kod bounded context-lər arasında istifadə olunan integration event-i göstərir
namespace App\IntegrationEvents;

class OrderPlacedIntegrationEvent implements IntegrationEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly float $totalAmount,
        public readonly string $currency,
        public readonly array $items,
        public readonly string $occurredOn,
        public readonly int $version = 1,
    ) {}

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => 'order.placed',
            'version' => $this->version,
            'occurred_on' => $this->occurredOn,
            'data' => [
                'order_id' => $this->orderId,
                'customer_id' => $this->customerId,
                'total_amount' => $this->totalAmount,
                'currency' => $this->currency,
                'items' => $this->items,
            ],
        ];
    }

    public static function fromDomainEvent(OrderPlaced $domainEvent): self
    {
        return new self(
            eventId: (string) Str::uuid(),
            orderId: $domainEvent->orderId,
            customerId: $domainEvent->customerId,
            totalAmount: $domainEvent->totalAmount->getAmount(),
            currency: $domainEvent->totalAmount->getCurrency(),
            items: array_map(fn($item) => $item->toArray(), $domainEvent->orderItems),
            occurredOn: $domainEvent->occurredOn->format('c'),
        );
    }
}
```

**Integration Event-in xüsusiyyətləri:**
- Bounded Context-lər arasında gedir
- Serializable olmalıdır (JSON, Avro, Protobuf)
- Version-lanmalıdır (backward compatibility)
- Event ID olmalıdır (idempotency üçün)
- Message broker vasitəsilə göndərilir (RabbitMQ, Kafka, Redis)

### 3. Notification Events

Notification Event-lər minimal məlumat daşıyır - yalnız "nəsə baş verdi" bildirir, ətraflı məlumat üçün consumer-in özü sorğu göndərməlidir.

*Notification Event-lər minimal məlumat daşıyır - yalnız "nəsə baş verd üçün kod nümunəsi:*
```php
// Notification Event - minimal məlumat
class OrderStatusChanged implements NotificationEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $occurredOn,
    ) {}
    // Ətraflı məlumat yoxdur - consumer özü soruşmalıdır
}

// Consumer tərəfində
class OrderStatusChangedHandler
{
    public function __construct(
        private readonly OrderServiceClient $orderService,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        // Ətraflı məlumatı API-dən alır
        $orderDetails = $this->orderService->getOrder($event->orderId);

        // İndi ətraflı məlumatla işləyir
        $this->processOrderStatusChange($orderDetails);
    }
}
```

**Notification Event vs Fat Event müqayisəsi:**

```php
// Fat Event - bütün məlumat event-in içindədir
class OrderPlacedFat
{
    public string $orderId;
    public string $customerName;
    public string $customerEmail;
    public string $shippingAddress;
    public array $items; // bütün item detalları
    public float $totalAmount;
    public float $taxAmount;
    public float $shippingCost;
    // ... daha çox field
}

// Thin/Notification Event - minimum məlumat
class OrderPlacedThin
{
    public string $orderId;
    public string $occurredOn;
    // Consumer lazım olan məlumatı özü çəkir
}
```

---

## Event Sourcing

Event Sourcing - sistemin vəziyyətini (state) **hadisələr ardıcıllığı** kimi saxlayan arxitektura pattern-idir. Cari state verilənlər bazasında saxlanmır, əvəzinə bütün dəyişikliklər event kimi saxlanır və cari state bu event-lərin replay edilməsi ilə yaradılır.

**Klassik yanaşma vs Event Sourcing:**

```
Klassik (CRUD):
┌─────────────────────────────────┐
│ orders table                     │
│ id: 1                            │
│ status: "shipped"                │
│ total: 150.00                    │
│ updated_at: 2024-01-15           │
└─────────────────────────────────┘
Əvvəlki vəziyyətlər itib!

Event Sourcing:
┌─────────────────────────────────┐
│ 1. OrderCreated      (Jan 10)    │
│ 2. ItemAdded         (Jan 10)    │
│ 3. ItemAdded         (Jan 10)    │
│ 4. OrderConfirmed    (Jan 11)    │
│ 5. PaymentReceived   (Jan 12)    │
│ 6. OrderShipped      (Jan 15)    │
└─────────────────────────────────┘
Hər bir dəyişiklik qorunur!
```

### Laravel-də Event Sourcing implementasiyası

*Laravel-də Event Sourcing implementasiyası üçün kod nümunəsi:*
```php
// Aggregate Root
namespace App\Domain\Order;

class OrderAggregate
{
    private string $orderId;
    private string $status;
    private array $items = [];
    private float $totalAmount = 0;
    private array $recordedEvents = [];

    public static function create(string $orderId, string $customerId): self
    {
        $order = new self();
        $order->recordEvent(new OrderCreated(
            orderId: $orderId,
            customerId: $customerId,
            occurredOn: new DateTimeImmutable(),
        ));

        return $order;
    }

    public function addItem(string $productId, int $quantity, float $unitPrice): void
    {
        // Business rule validation
        if ($this->status !== 'draft') {
            throw new DomainException('Yalnız draft statusunda item əlavə edilə bilər.');
        }

        if ($quantity <= 0) {
            throw new DomainException('Miqdar 0-dan böyük olmalıdır.');
        }

        $this->recordEvent(new OrderItemAdded(
            orderId: $this->orderId,
            productId: $productId,
            quantity: $quantity,
            unitPrice: $unitPrice,
            occurredOn: new DateTimeImmutable(),
        ));
    }

    public function confirm(): void
    {
        if ($this->status !== 'draft') {
            throw new DomainException('Yalnız draft sifariş təsdiqlənə bilər.');
        }

        if (empty($this->items)) {
            throw new DomainException('Boş sifariş təsdiqlənə bilməz.');
        }

        $this->recordEvent(new OrderConfirmed(
            orderId: $this->orderId,
            totalAmount: $this->totalAmount,
            occurredOn: new DateTimeImmutable(),
        ));
    }

    public function cancel(string $reason): void
    {
        if (in_array($this->status, ['shipped', 'delivered', 'cancelled'])) {
            throw new DomainException("Bu statusda sifariş ləğv edilə bilməz: {$this->status}");
        }

        $this->recordEvent(new OrderCancelled(
            orderId: $this->orderId,
            reason: $reason,
            occurredOn: new DateTimeImmutable(),
        ));
    }

    // Event-ləri apply edən metodlar (state dəyişikliyi)
    protected function applyOrderCreated(OrderCreated $event): void
    {
        $this->orderId = $event->orderId;
        $this->status = 'draft';
    }

    protected function applyOrderItemAdded(OrderItemAdded $event): void
    {
        $this->items[] = [
            'product_id' => $event->productId,
            'quantity' => $event->quantity,
            'unit_price' => $event->unitPrice,
        ];
        $this->totalAmount += $event->quantity * $event->unitPrice;
    }

    protected function applyOrderConfirmed(OrderConfirmed $event): void
    {
        $this->status = 'confirmed';
    }

    protected function applyOrderCancelled(OrderCancelled $event): void
    {
        $this->status = 'cancelled';
    }

    // Event-ləri reconstitute etmə (rebuild from events)
    public static function reconstituteFromEvents(array $events): self
    {
        $order = new self();
        foreach ($events as $event) {
            $order->apply($event);
        }
        return $order;
    }

    private function apply(object $event): void
    {
        $method = 'apply' . class_basename($event);
        if (method_exists($this, $method)) {
            $this->$method($event);
        }
    }

    private function recordEvent(object $event): void
    {
        $this->apply($event);
        $this->recordedEvents[] = $event;
    }

    public function pullRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }
}
```

### Spatie Event Sourcing paketi ilə

*Spatie Event Sourcing paketi ilə üçün kod nümunəsi:*
```php
// composer require spatie/laravel-event-sourcing

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class OrderAggregateRoot extends AggregateRoot
{
    private string $status = '';
    private array $items = [];
    private float $totalAmount = 0;

    public function createOrder(string $customerId): self
    {
        $this->recordThat(new OrderCreated(
            customerId: $customerId,
        ));

        return $this;
    }

    public function addItem(string $productId, int $quantity, float $price): self
    {
        if ($this->status !== 'draft') {
            throw new DomainException('Yalnız draft sifarişə item əlavə edilə bilər.');
        }

        $this->recordThat(new OrderItemAdded(
            productId: $productId,
            quantity: $quantity,
            unitPrice: $price,
        ));

        return $this;
    }

    public function confirm(): self
    {
        if (empty($this->items)) {
            throw new DomainException('Boş sifariş təsdiqlənə bilməz.');
        }

        $this->recordThat(new OrderConfirmed(
            totalAmount: $this->totalAmount,
        ));

        return $this;
    }

    // Apply metodları
    public function applyOrderCreated(OrderCreated $event): void
    {
        $this->status = 'draft';
    }

    public function applyOrderItemAdded(OrderItemAdded $event): void
    {
        $this->items[] = [
            'product_id' => $event->productId,
            'quantity' => $event->quantity,
        ];
        $this->totalAmount += $event->quantity * $event->unitPrice;
    }

    public function applyOrderConfirmed(OrderConfirmed $event): void
    {
        $this->status = 'confirmed';
    }
}

// İstifadə
OrderAggregateRoot::retrieve($orderId)
    ->createOrder($customerId)
    ->addItem('PROD-001', 2, 29.99)
    ->addItem('PROD-002', 1, 49.99)
    ->confirm()
    ->persist();
```

---

## Event Store

Event Store - event-lərin saxlandığı verilənlər bazasıdır. Bu, append-only bir log-dur, yəni event-lər yalnız əlavə edilir, heç vaxt silinmir və ya dəyişdirilmir.

### Event Store cədvəli

*Event Store cədvəli üçün kod nümunəsi:*
```php
// Migration
Schema::create('stored_events', function (Blueprint $table) {
    $table->id();
    $table->uuid('event_id')->unique();
    $table->string('aggregate_uuid')->index();
    $table->string('aggregate_type');
    $table->unsignedInteger('aggregate_version');
    $table->string('event_type');
    $table->json('event_data');
    $table->json('metadata')->nullable();
    $table->timestamp('occurred_on');
    $table->timestamp('created_at');

    // Eyni aggregate üçün version unique olmalıdır (optimistic locking)
    $table->unique(['aggregate_uuid', 'aggregate_version']);
});
```

### Event Store implementasiyası

*Event Store implementasiyası üçün kod nümunəsi:*
```php
// Bu kod event store-un database implementasiyasını göstərir
namespace App\Infrastructure\EventStore;

class EloquentEventStore implements EventStoreInterface
{
    public function append(string $aggregateId, array $events, int $expectedVersion): void
    {
        DB::transaction(function () use ($aggregateId, $events, $expectedVersion) {
            // Optimistic Concurrency Check
            $currentVersion = StoredEvent::where('aggregate_uuid', $aggregateId)
                ->max('aggregate_version') ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw new ConcurrencyException(
                    "Gözlənilən version: {$expectedVersion}, cari version: {$currentVersion}"
                );
            }

            $version = $expectedVersion;

            foreach ($events as $event) {
                $version++;
                StoredEvent::create([
                    'event_id' => (string) Str::uuid(),
                    'aggregate_uuid' => $aggregateId,
                    'aggregate_type' => get_class($event->getAggregate()),
                    'aggregate_version' => $version,
                    'event_type' => get_class($event),
                    'event_data' => json_encode($event->toArray()),
                    'metadata' => json_encode([
                        'user_id' => auth()->id(),
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'correlation_id' => CorrelationId::current(),
                    ]),
                    'occurred_on' => $event->occurredOn(),
                ]);
            }
        });
    }

    public function getEventsForAggregate(string $aggregateId): Collection
    {
        return StoredEvent::where('aggregate_uuid', $aggregateId)
            ->orderBy('aggregate_version')
            ->get()
            ->map(fn (StoredEvent $stored) => $this->deserialize($stored));
    }

    public function getEventsAfterVersion(string $aggregateId, int $afterVersion): Collection
    {
        return StoredEvent::where('aggregate_uuid', $aggregateId)
            ->where('aggregate_version', '>', $afterVersion)
            ->orderBy('aggregate_version')
            ->get()
            ->map(fn (StoredEvent $stored) => $this->deserialize($stored));
    }

    public function getAllEventsSince(int $lastProcessedId): Collection
    {
        return StoredEvent::where('id', '>', $lastProcessedId)
            ->orderBy('id')
            ->limit(1000)
            ->get()
            ->map(fn (StoredEvent $stored) => $this->deserialize($stored));
    }

    private function deserialize(StoredEvent $stored): object
    {
        $eventClass = $stored->event_type;
        $data = json_decode($stored->event_data, true);

        return $eventClass::fromArray($data);
    }
}
```

### Snapshots (Performans optimizasiyası)

Çox sayda event olan aggregate-lər üçün hər dəfə bütün event-ləri replay etmək yavaş ola bilər. Snapshot-lar bu problemi həll edir.

*Çox sayda event olan aggregate-lər üçün hər dəfə bütün event-ləri repl üçün kod nümunəsi:*
```php
// Bu kod event-sourcing-də snapshot saxlama mexanizmini göstərir
class SnapshotStore
{
    public function save(string $aggregateId, object $state, int $version): void
    {
        Snapshot::updateOrCreate(
            ['aggregate_uuid' => $aggregateId],
            [
                'state' => serialize($state),
                'version' => $version,
            ]
        );
    }

    public function get(string $aggregateId): ?SnapshotData
    {
        $snapshot = Snapshot::where('aggregate_uuid', $aggregateId)->first();

        if (!$snapshot) {
            return null;
        }

        return new SnapshotData(
            state: unserialize($snapshot->state),
            version: $snapshot->version,
        );
    }
}

// Snapshot ilə aggregate yüklənməsi
class EventSourcedRepository
{
    public function __construct(
        private EventStoreInterface $eventStore,
        private SnapshotStore $snapshotStore,
    ) {}

    public function load(string $aggregateId): OrderAggregate
    {
        $snapshot = $this->snapshotStore->get($aggregateId);

        if ($snapshot) {
            $aggregate = $snapshot->state;
            // Snapshot-dan sonrakı event-ləri al
            $events = $this->eventStore->getEventsAfterVersion(
                $aggregateId,
                $snapshot->version
            );
        } else {
            $aggregate = new OrderAggregate();
            $events = $this->eventStore->getEventsForAggregate($aggregateId);
        }

        foreach ($events as $event) {
            $aggregate->apply($event);
        }

        return $aggregate;
    }

    public function save(OrderAggregate $aggregate): void
    {
        $events = $aggregate->pullRecordedEvents();
        $this->eventStore->append(
            $aggregate->getId(),
            $events,
            $aggregate->getVersion() - count($events)
        );

        // Hər 50 event-dən bir snapshot yarat
        if ($aggregate->getVersion() % 50 === 0) {
            $this->snapshotStore->save(
                $aggregate->getId(),
                $aggregate,
                $aggregate->getVersion()
            );
        }
    }
}
```

---

## Eventual Consistency

Eventual Consistency - distributed sistemlərdə bütün node-ların **nəhayət** (eventually) eyni vəziyyətə gələcəyini təmin edən konsistentlik modelidir. CAP teoreminə görə, distributed sistemdə Consistency, Availability və Partition Tolerance-in hamısını eyni anda təmin etmək mümkün deyil.

**Strong Consistency vs Eventual Consistency:**

```
Strong Consistency:
User -> Write -> [Bütün node-lar yenilənir] -> Read (həmişə yeni data)
Yavaş, amma tutarlı.

Eventual Consistency:
User -> Write -> [Primary node yenilənir] -> Read (köhnə data ola bilər)
                  [Digər node-lar tədricən yenilənir]
Sürətli, amma müvəqqəti inconsistency mümkündür.
```

### Laravel-də Eventual Consistency nümunəsi

*Laravel-də Eventual Consistency nümunəsi üçün kod nümunəsi:*
```php
// Order yaradıldıqda, Inventory ayrı bounded context-dir
// və eventual consistent olaraq yenilənir.

// Order Service (Write tərəfi)
class PlaceOrderHandler
{
    public function handle(PlaceOrder $command): void
    {
        $order = Order::create([
            'user_id' => $command->userId,
            'status' => 'placed',
            'total' => $command->total,
        ]);

        // Event yayımla - Inventory SERVICE asinxron olaraq yenilənəcək
        event(new OrderPlaced($order));
    }
}

// Inventory Service (asinxron Listener - ayrı bounded context)
class UpdateInventoryOnOrderPlaced implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        foreach ($event->order->items as $item) {
            $inventory = Inventory::where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if ($inventory->quantity < $item->quantity) {
                // Stok kifayət deyil - kompensasiya event-i göndər
                event(new InsufficientStock(
                    orderId: $event->order->id,
                    productId: $item->product_id,
                    requested: $item->quantity,
                    available: $inventory->quantity,
                ));
                return;
            }

            $inventory->decrement('quantity', $item->quantity);
        }

        event(new InventoryReserved(orderId: $event->order->id));
    }
}
```

---

## Saga Pattern

Saga Pattern - distributed transaction-ları idarə etmək üçün istifadə olunan pattern-dir. Uzun müddətli business process-ləri bir sıra lokal transaction-lar və kompensasiya əməliyyatları kimi idarə edir.

### Saga nümunəsi: Sifariş prosesi

*Saga nümunəsi: Sifariş prosesi üçün kod nümunəsi:*
```php
// Bu kod Saga pattern ilə sifariş prosesinin idarə edilməsini göstərir
namespace App\Sagas;

class OrderSaga
{
    private array $completedSteps = [];

    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
        private readonly InventoryService $inventoryService,
        private readonly ShippingService $shippingService,
    ) {}

    public function execute(PlaceOrderCommand $command): void
    {
        try {
            // Addım 1: Sifariş yarat
            $order = $this->orderService->createOrder($command);
            $this->completedSteps[] = 'order_created';

            // Addım 2: Stoku rezerv et
            $this->inventoryService->reserveStock($order);
            $this->completedSteps[] = 'stock_reserved';

            // Addım 3: Ödənişi emal et
            $this->paymentService->processPayment($order);
            $this->completedSteps[] = 'payment_processed';

            // Addım 4: Göndəriş yarat
            $this->shippingService->createShipment($order);
            $this->completedSteps[] = 'shipment_created';

        } catch (\Exception $e) {
            // Kompensasiya - əks sıra ilə geri al
            $this->compensate($order ?? null, $e);
            throw $e;
        }
    }

    private function compensate(?Order $order, \Exception $error): void
    {
        Log::error('Saga kompensasiya başladı', [
            'order_id' => $order?->id,
            'failed_at' => end($this->completedSteps),
            'error' => $error->getMessage(),
        ]);

        $compensations = array_reverse($this->completedSteps);

        foreach ($compensations as $step) {
            try {
                match ($step) {
                    'shipment_created' => $this->shippingService->cancelShipment($order),
                    'payment_processed' => $this->paymentService->refundPayment($order),
                    'stock_reserved' => $this->inventoryService->releaseStock($order),
                    'order_created' => $this->orderService->cancelOrder($order, $error->getMessage()),
                };
            } catch (\Exception $compensationError) {
                Log::critical('Saga kompensasiya uğursuz oldu', [
                    'step' => $step,
                    'error' => $compensationError->getMessage(),
                ]);
                // Dead letter queue-ya göndər
                dispatch(new FailedCompensationJob($order, $step, $compensationError));
            }
        }
    }
}
```

### Laravel-də Event-based Saga (state machine ilə)

*Laravel-də Event-based Saga (state machine ilə) üçün kod nümunəsi:*
```php
// Bu kod state machine ilə event-based Saga implementasiyasını göstərir
namespace App\Sagas;

class OrderProcessSaga
{
    const STATUS_STARTED = 'started';
    const STATUS_STOCK_RESERVED = 'stock_reserved';
    const STATUS_PAYMENT_PROCESSED = 'payment_processed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Saga state-i DB-də saxlanır
    public static function start(Order $order): void
    {
        SagaState::create([
            'saga_id' => (string) Str::uuid(),
            'saga_type' => 'order_process',
            'aggregate_id' => $order->id,
            'status' => self::STATUS_STARTED,
            'data' => ['order_id' => $order->id],
        ]);

        // İlk addımı başlat
        event(new ReserveStockRequested($order));
    }

    // Listener: Stok rezerv olundu
    public function onStockReserved(StockReserved $event): void
    {
        $saga = $this->findSaga($event->orderId);
        $saga->update(['status' => self::STATUS_STOCK_RESERVED]);

        // Növbəti addım: Ödəniş
        event(new ProcessPaymentRequested(
            orderId: $event->orderId,
        ));
    }

    // Listener: Stok kifayət deyil
    public function onInsufficientStock(InsufficientStock $event): void
    {
        $saga = $this->findSaga($event->orderId);
        $saga->update(['status' => self::STATUS_FAILED]);

        // Kompensasiya
        event(new CancelOrderRequested(
            orderId: $event->orderId,
            reason: 'Stok kifayət deyil',
        ));
    }

    // Listener: Ödəniş uğurlu
    public function onPaymentProcessed(PaymentProcessed $event): void
    {
        $saga = $this->findSaga($event->orderId);
        $saga->update(['status' => self::STATUS_PAYMENT_PROCESSED]);

        event(new CreateShipmentRequested(orderId: $event->orderId));
    }

    // Listener: Ödəniş uğursuz
    public function onPaymentFailed(PaymentFailed $event): void
    {
        $saga = $this->findSaga($event->orderId);
        $saga->update(['status' => self::STATUS_FAILED]);

        // Kompensasiya: Stoku geri qaytar
        event(new ReleaseStockRequested(orderId: $event->orderId));
        event(new CancelOrderRequested(
            orderId: $event->orderId,
            reason: 'Ödəniş uğursuz oldu',
        ));
    }

    private function findSaga(int $orderId): SagaState
    {
        return SagaState::where('saga_type', 'order_process')
            ->where('aggregate_id', $orderId)
            ->firstOrFail();
    }
}
```

---

## Choreography vs Orchestration

### Choreography (Xoreografiya)

Choreography-də heç bir mərkəzi koordinator yoxdur. Hər service öz işini görür və event yayımlayır, digər service-lər bu event-lərə reaksiya verir.

```
OrderService -> OrderPlaced
    InventoryService (dinləyir) -> StockReserved
        PaymentService (dinləyir) -> PaymentProcessed
            ShippingService (dinləyir) -> ShipmentCreated
                NotificationService (dinləyir) -> NotificationSent
```

*NotificationService (dinləyir) -> NotificationSent üçün kod nümunəsi:*
```php
// Choreography nümunəsi - hər service müstəqildir

// OrderService
class OrderController extends Controller
{
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::create($request->validated());

        // Sadəcə event yayımlayır, nə baş verəcəyini bilmir
        event(new OrderPlaced($order));

        return response()->json($order, 201);
    }
}

// InventoryService - OrderPlaced dinləyir
class ReserveStockListener implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $reserved = $this->reserveStock($event->order);

        if ($reserved) {
            event(new StockReserved($event->order));
        } else {
            event(new StockReservationFailed($event->order));
        }
    }
}

// PaymentService - StockReserved dinləyir
class ProcessPaymentListener implements ShouldQueue
{
    public function handle(StockReserved $event): void
    {
        $result = $this->processPayment($event->order);

        if ($result->isSuccessful()) {
            event(new PaymentProcessed($event->order));
        } else {
            event(new PaymentFailed($event->order));
        }
    }
}

// ShippingService - PaymentProcessed dinləyir
class CreateShipmentListener implements ShouldQueue
{
    public function handle(PaymentProcessed $event): void
    {
        $shipment = $this->createShipment($event->order);
        event(new ShipmentCreated($event->order, $shipment));
    }
}
```

### Orchestration (Orkestrasiyanı)

Orchestration-da bir **mərkəzi koordinator (Orchestrator/Saga)** bütün prosesi idarə edir.

```
                    OrderOrchestrator
                    /       |        \
        InventoryService  PaymentService  ShippingService
```

*InventoryService  PaymentService  ShippingService üçün kod nümunəsi:*
```php
// Orchestration nümunəsi - mərkəzi idarəetmə

class OrderOrchestrator
{
    public function __construct(
        private InventoryService $inventory,
        private PaymentService $payment,
        private ShippingService $shipping,
        private NotificationService $notification,
    ) {}

    public function processOrder(Order $order): OrderResult
    {
        // Addım 1: Stoku yoxla və rezerv et
        $stockResult = $this->inventory->reserveStock($order);
        if (!$stockResult->isSuccessful()) {
            return OrderResult::failed('Stok kifayət deyil');
        }

        // Addım 2: Ödənişi emal et
        $paymentResult = $this->payment->processPayment($order);
        if (!$paymentResult->isSuccessful()) {
            // Kompensasiya
            $this->inventory->releaseStock($order);
            return OrderResult::failed('Ödəniş uğursuz');
        }

        // Addım 3: Göndəriş yarat
        $shipmentResult = $this->shipping->createShipment($order);
        if (!$shipmentResult->isSuccessful()) {
            // Kompensasiya
            $this->payment->refundPayment($order);
            $this->inventory->releaseStock($order);
            return OrderResult::failed('Göndəriş yaradıla bilmədi');
        }

        // Addım 4: Bildiriş göndər
        $this->notification->sendOrderConfirmation($order);

        return OrderResult::success($order);
    }
}
```

### Müqayisə

| Xüsusiyyət | Choreography | Orchestration |
|---|---|---|
| Coupling | Çox aşağı | Orta |
| Mürəkkəblik | Sadə flow üçün asan | Mürəkkəb flow üçün asan |
| Visibility | Prosesi görmək çətin | Proses aydın görünür |
| Single point of failure | Yox | Orchestrator |
| Debugging | Çətin | Asan |
| Ölçülənlik | Yüksək | Orta |

---

## Laravel-də Event Sistemi

### Event yaratma

*Event yaratma üçün kod nümunəsi:*
```bash
php artisan make:event OrderPlaced
php artisan make:listener SendOrderConfirmation --event=OrderPlaced
php artisan make:listener UpdateInventory --event=OrderPlaced
php artisan event:generate  # EventServiceProvider-dəki bütün event/listener-ləri yaradır
php artisan event:list      # Bütün event-ləri və listener-ləri siyahılayır
```

### Event class

*Event class üçün kod nümunəsi:*
```php
// Bu kod Laravel event class strukturunu göstərir
namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    // Broadcasting üçün kanal
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.' . $this->order->user_id),
        ];
    }

    // Broadcasting-də göndəriləcək data
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'total' => $this->order->total,
        ];
    }

    // Broadcast event adı
    public function broadcastAs(): string
    {
        return 'order.placed';
    }
}
```

### Listener class

*Listener class üçün kod nümunəsi:*
```php
// Bu kod Laravel listener class strukturunu göstərir
namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    // Queue adı
    public string $queue = 'emails';

    // Queue connection
    public string $connection = 'redis';

    // Retry sayı
    public int $tries = 3;

    // Retry arasında gözləmə (saniyə)
    public array $backoff = [10, 30, 60];

    // Timeout
    public int $timeout = 60;

    // Event handle olunmazdan əvvəl yoxlama
    public function shouldQueue(OrderPlaced $event): bool
    {
        return $event->order->total > 0;
    }

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        Mail::to($order->user->email)
            ->send(new OrderConfirmationMail($order));

        Log::info('Sifariş təsdiq email-i göndərildi', [
            'order_id' => $order->id,
            'email' => $order->user->email,
        ]);
    }

    // Bütün retry-lar uğursuz olduqda
    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        Log::critical('Sifariş təsdiq email-i göndərilə bilmədi', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);

        // Admin-ə bildiriş göndər
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new FailedEmailNotification($event->order, $exception));
    }
}
```

### Event qeydiyyatı

*Event qeydiyyatı üçün kod nümunəsi:*
```php
// EventServiceProvider
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPlaced::class => [
            SendOrderConfirmation::class,
            UpdateInventory::class,
            NotifyWarehouse::class,
            TrackOrderAnalytics::class,
            CreateOrderAuditLog::class,
        ],
        UserRegistered::class => [
            SendWelcomeEmail::class,
            CreateDefaultSettings::class,
            TrackRegistrationAnalytics::class,
            AssignDefaultRole::class,
        ],
        PaymentReceived::class => [
            UpdateOrderStatus::class,
            GenerateInvoice::class,
            NotifyUser::class,
            RecordRevenue::class,
        ],
    ];

    // Auto-discovery: listener-lərdə handle metodunun type-hint-inə görə
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
```

### Event Subscriber

Subscriber - bir class daxilində bir neçə event-i dinləyən class-dır.

*Subscriber - bir class daxilində bir neçə event-i dinləyən class-dır üçün kod nümunəsi:*
```php
// Bu kod bir neçə event-i dinləyən event subscriber class-ını göstərir
namespace App\Listeners;

use Illuminate\Events\Dispatcher;

class OrderEventSubscriber
{
    public function handleOrderPlaced(OrderPlaced $event): void
    {
        Log::info('Sifariş yaradıldı', ['order_id' => $event->order->id]);
        // ...
    }

    public function handleOrderShipped(OrderShipped $event): void
    {
        Log::info('Sifariş göndərildi', ['order_id' => $event->order->id]);
        // ...
    }

    public function handleOrderDelivered(OrderDelivered $event): void
    {
        Log::info('Sifariş çatdırıldı', ['order_id' => $event->order->id]);
        // ...
    }

    public function handleOrderCancelled(OrderCancelled $event): void
    {
        Log::info('Sifariş ləğv edildi', ['order_id' => $event->order->id]);
        // ...
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class => 'handleOrderPlaced',
            OrderShipped::class => 'handleOrderShipped',
            OrderDelivered::class => 'handleOrderDelivered',
            OrderCancelled::class => 'handleOrderCancelled',
        ];
    }
}

// EventServiceProvider-da qeydiyyat
protected $subscribe = [
    OrderEventSubscriber::class,
];
```

### Event yayımlama yolları

*Event yayımlama yolları üçün kod nümunəsi:*
```php
// 1. event() helper funksiyası
event(new OrderPlaced($order));

// 2. Event facade
Event::dispatch(new OrderPlaced($order));

// 3. Dispatchable trait ilə
OrderPlaced::dispatch($order);

// 4. Model events
class Order extends Model
{
    protected $dispatchesEvents = [
        'created' => OrderCreated::class,
        'updated' => OrderUpdated::class,
        'deleted' => OrderDeleted::class,
    ];
}

// 5. Observer ilə
class OrderObserver
{
    public function created(Order $order): void
    {
        event(new OrderPlaced($order));
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            event(new OrderStatusChanged($order, $order->getOriginal('status')));
        }
    }
}
```

---

## Event Broadcasting

Laravel Echo və WebSocket vasitəsilə real-time event broadcasting.

### Server tərəfi konfiqurasiya

*Server tərəfi konfiqurasiya üçün kod nümunəsi:*
```php
// config/broadcasting.php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
        ],
    ],
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST'),
            'port' => env('REVERB_PORT', 443),
            'scheme' => env('REVERB_SCHEME', 'https'),
        ],
    ],
],
```

### Broadcast Event

*Broadcast Event üçün kod nümunəsi:*
```php
// Bu kod WebSocket vasitəsilə real-time broadcast edilən event-i göstərir
class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $previousStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->order->user_id),
            new PrivateChannel('orders.' . $this->order->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'previous_status' => $this->previousStatus,
            'current_status' => $this->order->status,
            'updated_at' => $this->order->updated_at->toISOString(),
        ];
    }

    // Broadcast edilib-edilməyəcəyini müəyyən edən şərt
    public function broadcastWhen(): bool
    {
        return $this->previousStatus !== $this->order->status;
    }
}
```

### Client tərəfi (Laravel Echo)

*Client tərəfi (Laravel Echo) üçün kod nümunəsi:*
```javascript
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Private channel-a qoşulma
Echo.private(`orders.${orderId}`)
    .listen('.order.status.updated', (event) => {
        console.log('Sifariş statusu dəyişdi:', event);
        updateOrderStatusUI(event.current_status);
    });

// Presence channel (istifadəçi online/offline)
Echo.join(`chat.${roomId}`)
    .here((users) => {
        console.log('Cari istifadəçilər:', users);
    })
    .joining((user) => {
        console.log(user.name + ' qoşuldu');
    })
    .leaving((user) => {
        console.log(user.name + ' ayrıldı');
    })
    .listen('MessageSent', (event) => {
        appendMessage(event.message);
    });
```

### Channel Authorization

*Channel Authorization üçün kod nümunəsi:*
```php
// routes/channels.php
Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    $order = Order::find($orderId);
    return $order && $user->id === $order->user_id;
});

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('chat.{roomId}', function (User $user, int $roomId) {
    $room = ChatRoom::find($roomId);
    if ($room && $room->hasParticipant($user)) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});
```

---

## Async Event Handling

### Queued Listeners

*Queued Listeners üçün kod nümunəsi:*
```php
// ShouldQueue implement edən listener asinxron işləyir
class ProcessOrderAnalytics implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'analytics';
    public string $connection = 'redis';
    public int $tries = 3;
    public int $maxExceptions = 2;
    public int $timeout = 120;

    // Exponential backoff
    public function backoff(): array
    {
        return [10, 30, 60]; // 10s, 30s, 60s
    }

    // Retry-ı dayandırmaq üçün
    public function retryUntil(): DateTime
    {
        return now()->addHours(2);
    }

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        // Analitika emalı - uzun müddət çəkə bilər
        $analytics = new OrderAnalyticsProcessor();
        $analytics->process($order);
    }

    // Uğursuz olduqda
    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        Log::error('Analitika emalı uğursuz', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }

    // Listener-i unique etmə (eyni event üçün təkrar çalışmağın qarşısını alır)
    public function uniqueId(): string
    {
        return 'order_analytics_' . $this->event->order->id;
    }

    // Rate limiting
    public function middleware(): array
    {
        return [
            new RateLimited('analytics'),
            new WithoutOverlapping('order_' . $this->event->order->id),
        ];
    }
}
```

### Event + Job birləşməsi

*Event + Job birləşməsi üçün kod nümunəsi:*
```php
// Mürəkkəb asinxron iş axını üçün
class ProcessOrderWorkflow implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        // Zəncir şəklində iş axını
        Bus::chain([
            new ValidateOrderJob($event->order),
            new ReserveInventoryJob($event->order),
            new ProcessPaymentJob($event->order),
            new GenerateInvoiceJob($event->order),
            new SendConfirmationEmailJob($event->order),
        ])->onQueue('orders')
          ->catch(function (\Throwable $e) use ($event) {
              Log::error('Sifariş iş axını uğursuz', [
                  'order_id' => $event->order->id,
                  'error' => $e->getMessage(),
              ]);
              event(new OrderProcessingFailed($event->order, $e->getMessage()));
          })
          ->dispatch();
    }
}

// Paralel işləmə ilə
class ProcessOrderWorkflow implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        // Paralel: eyni anda işləyə bilən job-lar
        $batch = Bus::batch([
            new SendOrderConfirmationEmail($order),
            new NotifyWarehouse($order),
            new TrackAnalytics($order),
            new UpdateDashboard($order),
        ])->then(function (Batch $batch) use ($order) {
            Log::info('Sifariş emalı tamamlandı', ['order_id' => $order->id]);
        })->catch(function (Batch $batch, \Throwable $e) use ($order) {
            Log::error('Sifariş emalında xəta', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        })->finally(function (Batch $batch) use ($order) {
            // Həmişə çalışır
            Cache::forget("order_processing_{$order->id}");
        })->onQueue('orders')
          ->dispatch();
    }
}
```

---

## Event-Driven Microservices

### Microservice-lər arası event paylaşımı

*Microservice-lər arası event paylaşımı üçün kod nümunəsi:*
```php
// RabbitMQ ilə event publishing
namespace App\Infrastructure\Messaging;

class RabbitMQEventPublisher implements EventPublisher
{
    public function __construct(
        private AMQPStreamConnection $connection,
    ) {}

    public function publish(IntegrationEvent $event): void
    {
        $channel = $this->connection->channel();

        $channel->exchange_declare(
            exchange: 'domain_events',
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false,
        );

        $message = new AMQPMessage(
            body: json_encode($event->toArray()),
            properties: [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $event->eventId,
                'timestamp' => time(),
                'type' => $event->eventType(),
                'headers' => new AMQPTable([
                    'correlation_id' => CorrelationId::current(),
                    'source_service' => config('app.name'),
                ]),
            ]
        );

        $channel->basic_publish(
            msg: $message,
            exchange: 'domain_events',
            routing_key: $event->routingKey(), // order.placed, payment.received
        );

        $channel->close();
    }
}

// Event Consumer (ayrı microservice-də)
class RabbitMQEventConsumer
{
    public function consume(string $queue, callable $handler): void
    {
        $channel = $this->connection->channel();

        $channel->queue_declare($queue, false, true, false, false);

        $channel->basic_qos(null, 10, null); // Prefetch count

        $callback = function (AMQPMessage $msg) use ($handler) {
            try {
                $eventData = json_decode($msg->body, true);
                $handler($eventData);
                $msg->ack(); // Uğurlu emal
            } catch (\Exception $e) {
                Log::error('Event emalı uğursuz', [
                    'event' => $msg->body,
                    'error' => $e->getMessage(),
                ]);

                // Reject və requeue
                if ($msg->get('redelivered')) {
                    // Artıq 1 dəfə requeue olunub - dead letter queue-ya göndər
                    $msg->nack(false, false);
                } else {
                    $msg->nack(false, true); // Requeue
                }
            }
        };

        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }
}
```

### Laravel-də Kafka ilə Event Streaming

*Laravel-də Kafka ilə Event Streaming üçün kod nümunəsi:*
```php
// config/kafka.php
return [
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP', 'order-service'),
    'topics' => [
        'order_events' => 'order-events',
        'payment_events' => 'payment-events',
        'inventory_events' => 'inventory-events',
    ],
];

// Kafka Producer
class KafkaEventProducer
{
    public function publish(string $topic, string $key, array $data): void
    {
        $producer = new RdKafkaProducer(new Conf());
        $producer->addBrokers(config('kafka.brokers'));

        $kafkaTopic = $producer->newTopic($topic);

        $kafkaTopic->produce(
            partition: RD_KAFKA_PARTITION_UA,
            msgflags: 0,
            payload: json_encode($data),
            key: $key,
        );

        $producer->flush(5000);
    }
}

// Kafka Consumer (artisan command kimi)
class ConsumeOrderEventsCommand extends Command
{
    protected $signature = 'kafka:consume-orders';

    public function handle(): void
    {
        $conf = new Conf();
        $conf->set('group.id', config('kafka.consumer_group_id'));
        $conf->set('metadata.broker.list', config('kafka.brokers'));
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'false');

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe([config('kafka.topics.order_events')]);

        while (true) {
            $message = $consumer->consume(120 * 1000); // 120 saniyə timeout

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->processMessage($message);
                    $consumer->commit($message); // Manual commit
                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;
                default:
                    Log::error('Kafka xətası', ['error' => $message->errstr()]);
                    break;
            }
        }
    }

    private function processMessage($message): void
    {
        $data = json_decode($message->payload, true);
        $eventType = $data['event_type'] ?? 'unknown';

        $handler = match ($eventType) {
            'order.placed' => app(HandleOrderPlaced::class),
            'order.cancelled' => app(HandleOrderCancelled::class),
            'order.shipped' => app(HandleOrderShipped::class),
            default => null,
        };

        $handler?->handle($data);
    }
}
```

---

## Real-world Laravel Nümunələri

### 1. OrderPlaced Event Chain

*1. OrderPlaced Event Chain üçün kod nümunəsi:*
```php
// Events
class OrderPlaced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}

// Listener 1: Təsdiq email-i göndər
class SendConfirmationEmail implements ShouldQueue
{
    public string $queue = 'emails';

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->load(['user', 'items.product']);

        Mail::to($order->user->email)
            ->send(new OrderConfirmationMail($order));
    }
}

// Listener 2: Stoku yenilə
class UpdateInventory implements ShouldQueue
{
    public string $queue = 'inventory';

    public function handle(OrderPlaced $event): void
    {
        DB::transaction(function () use ($event) {
            foreach ($event->order->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);

                if ($product->stock < $item->quantity) {
                    throw new InsufficientStockException(
                        "Məhsul {$product->name} üçün stok kifayət deyil"
                    );
                }

                $product->decrement('stock', $item->quantity);

                // Stok aşağı düşübsə xəbərdarlıq
                if ($product->stock <= $product->low_stock_threshold) {
                    event(new LowStockDetected($product));
                }
            }
        });
    }
}

// Listener 3: Anbar bildirişi
class NotifyWarehouse implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->load(['items.product', 'shippingAddress']);

        $warehouse = Warehouse::findClosestTo($order->shippingAddress);

        WarehouseNotification::create([
            'warehouse_id' => $warehouse->id,
            'order_id' => $order->id,
            'type' => 'new_order',
            'priority' => $order->isExpress() ? 'high' : 'normal',
            'data' => [
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'location' => $item->product->warehouse_location,
                ])->toArray(),
            ],
        ]);

        // Slack-a bildiriş göndər
        Notification::route('slack', $warehouse->slack_webhook)
            ->notify(new NewOrderForWarehouse($order));
    }
}
```

### 2. UserRegistered Event Chain

*2. UserRegistered Event Chain üçün kod nümunəsi:*
```php
// Bu kod istifadəçi qeydiyyatı event chain-ini göstərir
class UserRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $registrationSource = 'web',
    ) {}
}

// Listener 1: Xoş gəldin email-i
class SendWelcomeEmail implements ShouldQueue
{
    public string $queue = 'emails';
    public int $delay = 30; // 30 saniyə sonra göndər

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)
            ->send(new WelcomeEmail($event->user));
    }
}

// Listener 2: Default parametrləri yarat
class CreateDefaultSettings implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        UserSettings::create([
            'user_id' => $event->user->id,
            'notification_preferences' => [
                'email_marketing' => true,
                'email_transactional' => true,
                'push_notifications' => true,
                'sms_notifications' => false,
            ],
            'language' => app()->getLocale(),
            'timezone' => config('app.timezone'),
            'currency' => 'AZN',
            'theme' => 'light',
        ]);

        // Default profil şəkli yaratma
        $avatar = (new AvatarGenerator())->generate($event->user->name);
        $path = "avatars/{$event->user->id}/default.png";
        Storage::disk('public')->put($path, $avatar);

        $event->user->update(['avatar_path' => $path]);
    }
}

// Listener 3: Analitika izlə
class TrackRegistrationAnalytics implements ShouldQueue
{
    public string $queue = 'analytics';

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        Analytics::track('user_registered', [
            'user_id' => $user->id,
            'source' => $event->registrationSource,
            'referrer' => session('referrer'),
            'utm_source' => session('utm_source'),
            'utm_medium' => session('utm_medium'),
            'utm_campaign' => session('utm_campaign'),
            'device' => request()->header('User-Agent'),
            'country' => geoip(request()->ip())->iso_code ?? 'unknown',
        ]);

        // Daily registration counter
        Cache::increment('daily_registrations:' . now()->format('Y-m-d'));
    }
}
```

### 3. PaymentReceived Event Chain

*3. PaymentReceived Event Chain üçün kod nümunəsi:*
```php
// Bu kod ödəniş alındıqda işləyən event chain-ini göstərir
class PaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly Order $order,
    ) {}
}

// Listener 1: Sifariş statusunu yenilə
class UpdateOrderStatus implements ShouldQueue
{
    public function handle(PaymentReceived $event): void
    {
        $order = $event->order;

        $order->update([
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
            'payment_method' => $event->payment->method,
        ]);

        // Status tarixçəsi
        $order->statusHistory()->create([
            'from_status' => $order->getOriginal('status'),
            'to_status' => OrderStatus::PAID,
            'changed_by' => 'system',
            'note' => 'Ödəniş alındı: ' . $event->payment->transaction_id,
        ]);
    }
}

// Listener 2: Faktura yarat
class GenerateInvoice implements ShouldQueue
{
    public string $queue = 'documents';

    public function handle(PaymentReceived $event): void
    {
        $order = $event->order->load(['user', 'items.product', 'shippingAddress']);

        $invoiceNumber = Invoice::generateNumber(); // INV-2024-00001

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'subtotal' => $order->subtotal,
            'tax_amount' => $order->tax_amount,
            'total' => $order->total,
            'currency' => $order->currency,
            'payment_method' => $event->payment->method,
            'payment_transaction_id' => $event->payment->transaction_id,
            'issued_at' => now(),
        ]);

        // PDF yarat
        $pdf = Pdf::loadView('invoices.template', compact('invoice', 'order'));
        $pdfPath = "invoices/{$order->id}/{$invoiceNumber}.pdf";
        Storage::disk('private')->put($pdfPath, $pdf->output());

        $invoice->update(['pdf_path' => $pdfPath]);

        // Faktura email-ini göndər
        Mail::to($order->user->email)
            ->send(new InvoiceEmail($invoice, $pdfPath));
    }
}

// Listener 3: İstifadəçiyə bildiriş
class NotifyUserAboutPayment implements ShouldQueue
{
    public function handle(PaymentReceived $event): void
    {
        $user = $event->order->user;

        // Database notification
        $user->notify(new PaymentReceivedNotification($event->payment, $event->order));

        // Push notification
        if ($user->push_token) {
            PushNotification::send($user->push_token, [
                'title' => 'Ödəniş qəbul edildi',
                'body' => "#{$event->order->id} nömrəli sifarişiniz üçün {$event->payment->formatted_amount} ödəniş qəbul edildi.",
                'data' => ['order_id' => $event->order->id],
            ]);
        }
    }
}
```

---

## Event Replay

Event Replay - saxlanmış event-ləri yenidən emal etmə prosesidir. Bu, proyeksiyaları yenidən qurmaq, bug-ları düzəltmək və ya yeni feature üçün köhnə data-nı emal etmək üçün istifadə olunur.

*Event Replay - saxlanmış event-ləri yenidən emal etmə prosesidir. Bu,  üçün kod nümunəsi:*
```php
// Event Replay Command
class ReplayEventsCommand extends Command
{
    protected $signature = 'events:replay
        {--aggregate= : Replay specific aggregate}
        {--event-type= : Filter by event type}
        {--from= : Start date}
        {--to= : End date}
        {--dry-run : Preview without executing}';

    public function handle(EventStoreInterface $eventStore): void
    {
        $query = StoredEvent::query()
            ->orderBy('id');

        if ($aggregateId = $this->option('aggregate')) {
            $query->where('aggregate_uuid', $aggregateId);
        }

        if ($eventType = $this->option('event-type')) {
            $query->where('event_type', $eventType);
        }

        if ($from = $this->option('from')) {
            $query->where('occurred_on', '>=', Carbon::parse($from));
        }

        if ($to = $this->option('to')) {
            $query->where('occurred_on', '<=', Carbon::parse($to));
        }

        $totalEvents = $query->count();
        $this->info("Replay ediləcək event sayı: {$totalEvents}");

        if ($this->option('dry-run')) {
            $this->info('Dry run - heç bir dəyişiklik edilməyəcək');
            return;
        }

        if (!$this->confirm('Davam etmək istəyirsiniz?')) {
            return;
        }

        $bar = $this->output->createProgressBar($totalEvents);

        $query->chunk(100, function ($events) use ($bar) {
            foreach ($events as $storedEvent) {
                try {
                    $event = $this->deserialize($storedEvent);

                    // Yalnız projeksiya handler-ləri çalışır
                    app(ProjectionService::class)->handle($event);

                    $bar->advance();
                } catch (\Exception $e) {
                    Log::error('Event replay xətası', [
                        'event_id' => $storedEvent->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Event replay tamamlandı.');
    }
}
```

### Projection Rebuilding

*Projection Rebuilding üçün kod nümunəsi:*
```php
// Projeksiyaları yenidən qurmaq
class RebuildOrderProjectionCommand extends Command
{
    protected $signature = 'projections:rebuild {projection}';

    public function handle(): void
    {
        $projectionName = $this->argument('projection');

        $projector = match ($projectionName) {
            'order-summary' => app(OrderSummaryProjector::class),
            'user-statistics' => app(UserStatisticsProjector::class),
            'revenue-report' => app(RevenueReportProjector::class),
            default => throw new \InvalidArgumentException("Bilinməyən projection: {$projectionName}"),
        };

        // Cari projeksiya cədvəlini təmizlə
        $projector->resetState();

        // Bütün uyğun event-ləri yenidən emal et
        StoredEvent::query()
            ->whereIn('event_type', $projector->handlesEvents())
            ->orderBy('id')
            ->chunk(500, function ($events) use ($projector) {
                foreach ($events as $storedEvent) {
                    $event = $this->deserialize($storedEvent);
                    $projector->handle($event);
                }
            });

        $this->info("'{$projectionName}' projeksiyası yenidən quruldu.");
    }
}

// Projector nümunəsi
class OrderSummaryProjector
{
    public function handlesEvents(): array
    {
        return [
            OrderCreated::class,
            OrderItemAdded::class,
            OrderConfirmed::class,
            OrderShipped::class,
            OrderDelivered::class,
            OrderCancelled::class,
        ];
    }

    public function resetState(): void
    {
        DB::table('order_summaries')->truncate();
    }

    public function handle(object $event): void
    {
        $method = 'on' . class_basename($event);
        if (method_exists($this, $method)) {
            $this->$method($event);
        }
    }

    public function onOrderCreated(OrderCreated $event): void
    {
        DB::table('order_summaries')->insert([
            'order_id' => $event->orderId,
            'customer_id' => $event->customerId,
            'status' => 'created',
            'total_amount' => 0,
            'item_count' => 0,
            'created_at' => $event->occurredOn,
        ]);
    }

    public function onOrderItemAdded(OrderItemAdded $event): void
    {
        DB::table('order_summaries')
            ->where('order_id', $event->orderId)
            ->increment('item_count');

        DB::table('order_summaries')
            ->where('order_id', $event->orderId)
            ->increment('total_amount', $event->quantity * $event->unitPrice);
    }
}
```

---

## Dead Letter Queue

Dead Letter Queue (DLQ) - emal edilə bilməyən mesajların göndərildiyi xüsusi növbədir. Bu mesajlar sonradan araşdırılıb, manual və ya avtomatik olaraq yenidən emal edilə bilər.

*Dead Letter Queue (DLQ) - emal edilə bilməyən mesajların göndərildiyi  üçün kod nümunəsi:*
```php
// Dead Letter Queue implementasiyası
Schema::create('dead_letter_queue', function (Blueprint $table) {
    $table->id();
    $table->uuid('message_id')->unique();
    $table->string('original_queue');
    $table->string('event_type');
    $table->json('payload');
    $table->json('metadata')->nullable();
    $table->text('error_message');
    $table->text('stack_trace')->nullable();
    $table->unsignedInteger('retry_count')->default(0);
    $table->unsignedInteger('max_retries')->default(5);
    $table->enum('status', ['pending', 'retrying', 'resolved', 'discarded'])->default('pending');
    $table->timestamp('failed_at');
    $table->timestamp('last_retry_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});

// DLQ Service
class DeadLetterQueueService
{
    public function store(
        string $queue,
        string $eventType,
        array $payload,
        \Throwable $exception,
        array $metadata = [],
    ): DeadLetterMessage {
        return DeadLetterMessage::create([
            'message_id' => (string) Str::uuid(),
            'original_queue' => $queue,
            'event_type' => $eventType,
            'payload' => $payload,
            'metadata' => $metadata,
            'error_message' => $exception->getMessage(),
            'stack_trace' => $exception->getTraceAsString(),
            'failed_at' => now(),
        ]);
    }

    public function retry(DeadLetterMessage $message): bool
    {
        if ($message->retry_count >= $message->max_retries) {
            $message->update(['status' => 'discarded']);
            return false;
        }

        try {
            $message->update([
                'status' => 'retrying',
                'retry_count' => $message->retry_count + 1,
                'last_retry_at' => now(),
            ]);

            // Orijinal event-i yenidən dispatch et
            $eventClass = $message->event_type;
            $event = $eventClass::fromArray($message->payload);

            dispatch(function () use ($event) {
                event($event);
            })->onQueue($message->original_queue);

            $message->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            $message->update([
                'status' => 'pending',
                'error_message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function retryAll(string $eventType = null): int
    {
        $query = DeadLetterMessage::where('status', 'pending');

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $retried = 0;
        $query->each(function (DeadLetterMessage $message) use (&$retried) {
            if ($this->retry($message)) {
                $retried++;
            }
        });

        return $retried;
    }
}

// Artisan command
class RetryDeadLetterCommand extends Command
{
    protected $signature = 'dlq:retry {--event-type=} {--id=} {--all}';

    public function handle(DeadLetterQueueService $dlq): void
    {
        if ($id = $this->option('id')) {
            $message = DeadLetterMessage::findOrFail($id);
            $result = $dlq->retry($message);
            $this->info($result ? 'Uğurla retry edildi' : 'Retry uğursuz oldu');
            return;
        }

        if ($this->option('all')) {
            $count = $dlq->retryAll($this->option('event-type'));
            $this->info("{$count} mesaj uğurla retry edildi");
            return;
        }

        // Interactive: pending mesajları göstər
        $messages = DeadLetterMessage::where('status', 'pending')
            ->latest('failed_at')
            ->limit(20)
            ->get();

        $this->table(
            ['ID', 'Event Type', 'Queue', 'Error', 'Retry Count', 'Failed At'],
            $messages->map(fn ($m) => [
                $m->id,
                class_basename($m->event_type),
                $m->original_queue,
                Str::limit($m->error_message, 50),
                $m->retry_count,
                $m->failed_at->diffForHumans(),
            ])
        );
    }
}
```

---

## Idempotency in Event Handling

Idempotency - eyni event-in bir neçə dəfə emal edilməsi halında nəticənin dəyişməməsini təmin edir. Distributed sistemlərdə event-lər duplicate ola bilər, buna görə idempotent handling çox vacibdir.

*Idempotency - eyni event-in bir neçə dəfə emal edilməsi halında nəticə üçün kod nümunəsi:*
```php
// Idempotency Middleware
class IdempotentEventMiddleware
{
    public function handle(object $event, Closure $next): mixed
    {
        $eventId = $this->getEventId($event);

        if (!$eventId) {
            return $next($event);
        }

        // Atomik yoxlama və yazma
        $inserted = DB::table('processed_events')
            ->insertOrIgnore([
                'event_id' => $eventId,
                'event_type' => get_class($event),
                'processed_at' => now(),
            ]);

        if (!$inserted) {
            Log::info('Duplicate event atlandı', [
                'event_id' => $eventId,
                'event_type' => get_class($event),
            ]);
            return null; // Artıq emal edilib
        }

        return $next($event);
    }

    private function getEventId(object $event): ?string
    {
        if (method_exists($event, 'getEventId')) {
            return $event->getEventId();
        }

        if (property_exists($event, 'eventId')) {
            return $event->eventId;
        }

        return null;
    }
}

// Idempotent Listener
class ProcessPaymentListener implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        // Idempotent yoxlama
        $alreadyProcessed = Payment::where('order_id', $event->order->id)
            ->where('status', '!=', 'failed')
            ->exists();

        if ($alreadyProcessed) {
            Log::info('Ödəniş artıq emal edilib', ['order_id' => $event->order->id]);
            return;
        }

        // Ödənişi emal et
        $payment = Payment::create([
            'order_id' => $event->order->id,
            'amount' => $event->order->total,
            'status' => 'processing',
            'idempotency_key' => "order_{$event->order->id}_payment",
        ]);

        // Payment gateway-ə idempotency key ilə göndər
        $result = $this->paymentGateway->charge([
            'amount' => $payment->amount,
            'idempotency_key' => $payment->idempotency_key,
        ]);

        $payment->update([
            'status' => $result->isSuccessful() ? 'completed' : 'failed',
            'transaction_id' => $result->transactionId,
        ]);
    }
}

// Idempotent Trait
trait IdempotentListener
{
    protected function ensureNotProcessed(string $key): bool
    {
        return Cache::lock("idempotent:{$key}", 60)
            ->get(function () use ($key) {
                if (DB::table('processed_events')->where('event_id', $key)->exists()) {
                    return false; // Artıq emal edilib
                }

                DB::table('processed_events')->insert([
                    'event_id' => $key,
                    'event_type' => static::class,
                    'processed_at' => now(),
                ]);

                return true; // Emal edilə bilər
            });
    }
}

// İstifadə
class UpdateInventoryListener implements ShouldQueue
{
    use IdempotentListener;

    public function handle(OrderPlaced $event): void
    {
        $key = "inventory_update_order_{$event->order->id}";

        if (!$this->ensureNotProcessed($key)) {
            return;
        }

        // Stoku yenilə...
    }
}
```

---

## Event Versioning

Event-lərin sxemasi zamanla dəyişə bilər. Event versioning backward compatibility-ni təmin etmək üçün vacibdir.

*Event-lərin sxemasi zamanla dəyişə bilər. Event versioning backward co üçün kod nümunəsi:*
```php
// Event Versioning strategiyaları

// 1. Event Upcasting - köhnə event-ləri yeni formata çevirən pattern
interface EventUpcaster
{
    public function canUpcast(string $eventType, int $version): bool;
    public function upcast(array $payload, int $fromVersion): array;
    public function targetVersion(): int;
}

class OrderPlacedUpcaster implements EventUpcaster
{
    public function canUpcast(string $eventType, int $version): bool
    {
        return $eventType === OrderPlaced::class && $version < $this->targetVersion();
    }

    public function targetVersion(): int
    {
        return 3;
    }

    public function upcast(array $payload, int $fromVersion): array
    {
        // V1 -> V2: currency field əlavə edildi
        if ($fromVersion < 2) {
            $payload['currency'] = 'AZN'; // Default dəyər
        }

        // V2 -> V3: shipping_address object-ə çevrildi
        if ($fromVersion < 3) {
            if (isset($payload['shipping_address']) && is_string($payload['shipping_address'])) {
                $payload['shipping_address'] = [
                    'line1' => $payload['shipping_address'],
                    'city' => 'Unknown',
                    'country' => 'AZ',
                ];
            }
        }

        return $payload;
    }
}

// Upcaster Registry
class EventUpcasterChain
{
    private array $upcasters = [];

    public function register(EventUpcaster $upcaster): void
    {
        $this->upcasters[] = $upcaster;
    }

    public function upcast(string $eventType, array $payload, int $version): array
    {
        foreach ($this->upcasters as $upcaster) {
            if ($upcaster->canUpcast($eventType, $version)) {
                $payload = $upcaster->upcast($payload, $version);
            }
        }

        return $payload;
    }
}

// 2. Versioned Event class-ları
namespace App\Events\V1;

class OrderPlaced
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly float $totalAmount,
    ) {}

    public static function version(): int
    {
        return 1;
    }
}

namespace App\Events\V2;

class OrderPlaced
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly float $totalAmount,
        public readonly string $currency, // Yeni field
    ) {}

    public static function version(): int
    {
        return 2;
    }

    public static function fromV1(V1\OrderPlaced $old): self
    {
        return new self(
            orderId: $old->orderId,
            customerId: $old->customerId,
            totalAmount: $old->totalAmount,
            currency: 'AZN', // Default
        );
    }
}

// 3. Schema Registry
class EventSchemaRegistry
{
    private array $schemas = [];

    public function register(string $eventType, int $version, array $schema): void
    {
        $this->schemas["{$eventType}_v{$version}"] = $schema;
    }

    public function validate(string $eventType, int $version, array $data): bool
    {
        $key = "{$eventType}_v{$version}";

        if (!isset($this->schemas[$key])) {
            throw new \RuntimeException("Schema tapılmadı: {$key}");
        }

        return $this->validateAgainstSchema($data, $this->schemas[$key]);
    }

    public function getLatestVersion(string $eventType): int
    {
        $versions = collect($this->schemas)
            ->keys()
            ->filter(fn ($key) => str_starts_with($key, $eventType))
            ->map(fn ($key) => (int) str_replace("{$eventType}_v", '', $key))
            ->sort()
            ->last();

        return $versions ?? 1;
    }
}

// Event Store-da versioning
class VersionedEventStore
{
    public function __construct(
        private EventStoreInterface $eventStore,
        private EventUpcasterChain $upcasterChain,
    ) {}

    public function getEvents(string $aggregateId): array
    {
        $storedEvents = $this->eventStore->getEventsForAggregate($aggregateId);

        return $storedEvents->map(function ($storedEvent) {
            $payload = json_decode($storedEvent->event_data, true);
            $version = $storedEvent->event_version ?? 1;

            // Köhnə event-ləri yeni formata çevir
            $payload = $this->upcasterChain->upcast(
                $storedEvent->event_type,
                $payload,
                $version,
            );

            return $this->deserialize($storedEvent->event_type, $payload);
        })->toArray();
    }
}
```

---

## Üstünlükləri və Mənfi Cəhətləri

### Üstünlükləri

1. **Loose Coupling** - Komponentlər bir-birindən asılı deyil, müstəqil inkişaf etdirilə bilər
2. **Scalability** - Hər listener ayrıca scale edilə bilər
3. **Flexibility** - Yeni listener əlavə etmək mövcud kodu dəyişdirmir (Open/Closed Principle)
4. **Audit Trail** - Bütün hadisələr qeyd olunur, tam tarixçə mövcuddur
5. **Temporal Decoupling** - Producer və consumer eyni anda aktiv olmaq məcburiyyətində deyil
6. **Resilience** - Bir komponentin çökməsi bütün sistemi dayandırmır
7. **Real-time Processing** - Hadisələr real-time emal oluna bilər
8. **Event Replay** - Keçmişdəki hadisələri yenidən emal etmək mümkündür

### Mənfi Cəhətləri

1. **Complexity** - Sistem mürəkkəbliyi artır, debug etmək çətindir
2. **Eventual Consistency** - Məlumat tutarlılığı dərhal təmin olunmur
3. **Event Ordering** - Event-lərin sırası garantiya edilməyə bilər
4. **Debugging Difficulty** - İş axınını izləmək çətindir (correlation ID lazımdır)
5. **Schema Evolution** - Event sxemalarının versiyalanması lazımdır
6. **Infrastructure Overhead** - Message broker, event store kimi əlavə infrastruktur lazımdır
7. **Learning Curve** - Komanda üzvlərinin öyrənməsi vaxt aparır
8. **Over-engineering riski** - Sadə sistemlər üçün lazımsız mürəkkəblik yarada bilər

### Nə vaxt istifadə etməli

```
✅ İstifadə edin:
- Microservice arxitekturası
- Real-time məlumat ehtiyacı olan sistemlər
- Mürəkkəb iş axınları (workflow)
- Audit trail lazım olan sistemlər
- Yüksək yüklü sistemlər
- Loose coupling vacib olan sistemlər

❌ İstifadə etməyin:
- Sadə CRUD əməliyyatları
- Güclü tutarlılıq (strong consistency) vacib olan sistemlər
- Kiçik, monolit tətbiqlər
- Komandanın təcrübəsi olmadıqda
- Real-time nəticə lazım olan sadə əməliyyatlar
```

---

## İntervyu Sualları və Cavabları

### Sual 1: Event Driven Architecture nədir və nə üçün istifadə olunur?

**Cavab:** Event Driven Architecture (EDA) sistemin komponentlərinin hadisələr (events) vasitəsilə əlaqə qurduğu arxitektura yanaşmasıdır. Bir komponent hadisə yaradır (publish), digər komponentlər isə həmin hadisəyə reaksiya verir (subscribe). Bu yanaşma loose coupling təmin edir, komponentlərin müstəqil scale edilməsinə imkan verir və sistemin resilient olmasını artırır. E-commerce tətbiqində sifariş yaradıldıqda email göndərən, stoku yeniləyən və anbar bildirişi göndərən listener-lərin müstəqil işləməsi buna nümunədir.

### Sual 2: Event ilə Command arasında nə fərq var?

**Cavab:** Event keçmişdə baş vermiş hadisəni bildirir (past tense: `OrderPlaced`), Command isə gələcəkdə edilməli əməliyyatı bildirir (imperative: `PlaceOrder`). Event-in bir neçə listener-i ola bilər, Command-in isə yalnız bir handler-i olur. Event immutable-dir, nəticə qaytarmır. Command nəticə qaytara bilər. Event publisher listener-i tanımır, Command göndərən handler-in cavabını gözləyir.

### Sual 3: Eventual Consistency nədir və necə idarə olunur?

**Cavab:** Eventual Consistency distributed sistemlərdə bütün node-ların nəhayət eyni vəziyyətə gələcəyini təmin edən modeldir. Strong consistency-dən fərqli olaraq, dəyişikliklər dərhal bütün node-larda əks olunmur. Bu, kompensasiya event-ləri, saga pattern, retry mexanizmləri və idempotent handler-lər vasitəsilə idarə olunur. Üstünlüyü yüksək performans və availability, mənfi cəhəti isə müvəqqəti data inconsistency-dir.

### Sual 4: Saga Pattern nədir və nə vaxt istifadə olunur?

**Cavab:** Saga Pattern distributed transaction-ları idarə etmək üçün istifadə olunan pattern-dir. Uzun müddətli business process-ləri lokal transaction-lar seriyası kimi emal edir. Hər addım uğursuz olduqda, əvvəlki addımların kompensasiya əməliyyatları çalışır. Məsələn, sifariş prosesində ödəniş uğursuz olsa, əvvəlcə rezerv edilmiş stok geri qaytarılır. İki növü var: Choreography (hər service öz event-ləri ilə prosesi idarə edir) və Orchestration (mərkəzi koordinator prosesi idarə edir).

### Sual 5: Choreography və Orchestration arasında fərq nədir?

**Cavab:** Choreography-da heç bir mərkəzi koordinator yoxdur, hər service event-lərə reaksiya verərək öz işini görür. Daha loose coupling-dir amma prosesi izləmək çətindir. Orchestration-da bir mərkəzi service (orchestrator) bütün prosesi idarə edir. Proses aydın görünür amma single point of failure yarada bilər. Sadə flow-lar üçün choreography, mürəkkəb flow-lar üçün orchestration tövsiyə olunur.

### Sual 6: Event Sourcing nədir və adi CRUD-dan nə fərqi var?

**Cavab:** Event Sourcing cari state-i birbaşa saxlamaq əvəzinə, bütün dəyişiklikləri event kimi saxlayır. CRUD-da yalnız son vəziyyət saxlanır, Event Sourcing-də isə bütün tarixçə qorunur. Üstünlükləri: tam audit trail, time-travel debugging, event replay imkanı. Mənfi cəhətləri: mürəkkəblik, performans (snapshot-lar lazım ola bilər), eventual consistency.

### Sual 7: Idempotency event handling-də niyə vacibdir?

**Cavab:** Distributed sistemlərdə network problemləri, retry-lar və ya message broker-in davranışı səbəbindən eyni event bir neçə dəfə çatdırıla bilər. Idempotent handler eyni event-i bir neçə dəfə emal etsə də nəticə dəyişmir. Bu, event ID ilə processed_events cədvəlində yoxlama, database unique constraint-ləri və ya payment gateway-lərin idempotency key-ləri vasitəsilə təmin edilir.

### Sual 8: Dead Letter Queue nədir və nə üçün lazımdır?

**Cavab:** Dead Letter Queue (DLQ) emal edilə bilməyən mesajların göndərildiyi xüsusi növbədir. Bu mesajlar sonradan araşdırılıb retry edilə bilər. DLQ olmadan uğursuz mesajlar itirilə bilər və ya sonsuz retry dövrəsinə girə bilər. DLQ monitoring, alerting və manual/avtomatik retry mexanizmləri ilə birlikdə istifadə olunur.

### Sual 9: Laravel-də Event Broadcasting necə işləyir?

**Cavab:** Laravel-də Event Broadcasting `ShouldBroadcast` interface-ini implement edən event-lər vasitəsilə işləyir. Event `broadcastOn()` metodu ilə channel-ı, `broadcastWith()` ilə data-nı müəyyən edir. Server tərəfində Pusher, Laravel Reverb və ya Redis driver-ləri istifadə olunur. Client tərəfində Laravel Echo JavaScript kitabxanası ilə WebSocket bağlantısı qurulur. Private channel-lar `channels.php` faylında authorization ilə qorunur.

### Sual 10: Event versioning nədir və niyə lazımdır?

**Cavab:** Event versioning event sxemalarının zamanla dəyişməsini idarə edir. Yeni field əlavə etmək, field silmək və ya strukturu dəyişmək lazım olduqda backward compatibility təmin etmək vacibdir. Əsas strategiyalar: Event Upcasting (köhnə event-ləri oxuyarkən yeni formata çevirmək), versioned event class-ları yaratmaq və Schema Registry istifadə etmək. Bu, xüsusilə Event Sourcing istifadə edərkən vacibdir, çünki köhnə event-lər replay edilərkən düzgün deserialize olunmalıdır.

### Sual 11: Queued Listener-lərdə xətaları necə idarə edirsiniz?

**Cavab:** Laravel-də queued listener-lərdə `tries`, `backoff`, `timeout` property-ləri ilə retry davranışı konfiqurasiya olunur. `failed()` metodu bütün retry-lar uğursuz olduqda çalışır. `shouldQueue()` metodu event-in queue-ya göndərilib-göndərilməyəcəyini müəyyən edir. Əlavə olaraq, Dead Letter Queue, monitoring (Laravel Horizon), alerting və circuit breaker pattern-ləri istifadə olunur. Mürəkkəb iş axınları üçün `Bus::chain()` və `Bus::batch()` ilə job zəncirləri yaratmaq mümkündür.

### Sual 12: Microservice-lər arasında event paylaşımı necə həyata keçirilir?

**Cavab:** Microservice-lər arasında event paylaşımı message broker (RabbitMQ, Kafka, Redis Streams) vasitəsilə həyata keçirilir. Integration Event-lər serializable olmalıdır (JSON/Avro/Protobuf). Hər event-in unique ID-si, version-u və timestamp-i olmalıdır. Kafka partition-lar vasitəsilə ordering təmin edir, RabbitMQ isə routing key ilə topic-based routing imkanı verir. Consumer group-lar ilə paralel emal mümkündür. Schema registry ilə event sxemalarının uyğunluğu yoxlanır.

---

## Anti-patternlər

**1. Event-lərdə Həddindən Artıq Data Yerləşdirmək**
Event payload-una bütün entity data-sını (bütün sahələri) yerləşdirmək — event-lər böyüyür, consumer-lar lazımsız dataya bağlanır, event sxeması dəyişdikdə bütün consumer-lar pozulur. Event yalnız identity və lazım olan minimal konteksti daşımalıdır; consumer-lar əlavə data üçün sorğu göndərsin.

**2. Sinxron Event Handler-lar ilə Uzun Əməliyyatlar**
Email göndərmə, PDF yaratma kimi ağır işi sinxron listener-da etmək — HTTP request bloklanır, timeout riski yaranır. Ağır əməliyyatlar üçün `ShouldQueue` implement edən queued listener-lar istifadə edin.

**3. Idempotent Olmayan Consumer-lar**
Eyni event iki dəfə işlənəndə iki dəfə iş görən consumer-lar yazmaq — retry mexanizmi, network duplikasiyası zamanı dublikat sifarişlər, dublikat email-lər yaranır. Hər consumer idempotent olmalıdır: event ID-sinə görə işlənib-işlənmədiyini yoxlayın.

**4. Event Sxemasını Versiyalamadan Dəyişmək**
İstehsaldakı event-in strukturunu birbaşa dəyişmək — köhnə event-ləri emal edən consumer-lar sınır, Event Sourcing-də replay mümkünsüzləşir. Event-lərə versiya əlavə edin, köhnə versiyanı müddət keçənə qədər dəstəkləyin, upcasting strategiyası tətbiq edin.

**5. Hər Şeyi Event-lə Etmək**
Sadə CRUD əməliyyatları üçün mürəkkəb event axını qurmaq — sistemin anlaşılması çətinləşir, debug əziyyətli olur, mühəndislik resursları boş yerə sərflənir. EDA mürəkkəb asinxron iş axınları, cross-service kommunikasiya, audit trail kimi zəruri hallarda tətbiq olunmalıdır.

**6. Dead Letter Queue-suz İstehsala Çıxmaq**
Uğursuz event-lər üçün DLQ konfiqurasiya etmədən sistem qurmaq — emal edilə bilməyən event-lər ya itir ya da sonsuz retry ilə sistemi yükləyir. Hər queue üçün DLQ, retry limiti, failed event monitoring mütləq konfiqurasiya edilməlidir.
