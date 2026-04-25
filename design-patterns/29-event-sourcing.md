# Event Sourcing (Architect ⭐⭐⭐⭐⭐)

## İcmal
Event Sourcing, tətbiqin current state-ini birbaşa saxlamaq əvəzinə, state-ə gətirib çıxaran bütün hadisələrin (events) ardıcıl siyahısını saxlayır. Cari state, bu event-ləri başdan axıra replay etməklə əldə edilir. Database cədvəli "indi nədir" deyil, "nə baş verdi" sualını cavablayır.

## Niyə Vacibdir
Maliyyə sistemləri, e-commerce, hüquqi sənədləşdirmə kimi sahələrdə "bu state necə yarandı?" sualı kritikdir. Ənənəvi DB-də silinmiş ya update olunmuş data geri gəlmir. Event Sourcing ilə: tam audit trail avtomatik, istənilən nöqtəyə time travel, bug replay (bug-a aparan event-ləri yenidən oxumaq), parallel projection-lar (eyni event-lərdən müxtəlif view-lar). Laravel ekosistemindəki `spatie/laravel-event-sourcing` package bu pattern-i production-ready edir.

## Əsas Anlayışlar
- **Domain Event**: baş vermiş bir fakt; immutable; past tense ilə adlandırılır (`OrderPlaced`, `PaymentReceived`, `ItemShipped`); dəyişdirilə bilməz — yalnız əlavə olunur
- **Event Store**: append-only event log; stream ID (aggregate ID) ilə qruplaşdırılır; versioning saxlanılır
- **Event Stream**: bir aggregate-ə aid bütün event-lər ardıcıllığı
- **Aggregate Reconstitution**: event stream-i başdan replay edərək aggregate-in cari state-ini qurmaq
- **Projection**: event-lərdən müəyyən məqsəd üçün read model yaratmaq (dashboard statistikaları, son sifarişlər siyahısı)
- **Snapshot**: performance optimizasiyası; sıx replay-in qarşısını almaq üçün müəyyən nöqtədə state-i saxlamaq; sonra yalnız snapshot-dan sonrakı event-lər replay olunur
- **Event Upcasting**: köhnə event format-ını yeni format-a migrate etmək (schema evolution)

## Praktik Baxış
- **Real istifadə**: bank hesab hərəkətləri, e-commerce order lifecycle, subscription billing, inventory tracking, collaborative editing (Google Docs), audit-critical ERP sistemləri
- **Trade-off-lar**: tam audit trail pulsuz; time travel queries; event replay ilə bug reproduksiyası; projection-ları müstəqil rebuild etmək; **lakin** schema evolution çətin (köhnə event-lər format dəyişsə); eventual consistency (projection-lar sync deyil); query mürəkkəbliyi (cari state üçün replay lazım); steep learning curve
- **İstifadə etməmək**: sadə CRUD admin panelləri; history lazım olmayan data; tez dəyişən schema ilə MVP-lər; event-lərin semantic mənası yoxdursa (sadə DB log fərqlənir)
- **Common mistakes**:
  1. Event-ləri command kimi adlandırmaq: `CreateOrder` (command) → `OrderCreated` (event)
  2. Event-lərdə external data saxlamaq (email text, product description) — event-lər yalnız ID + key data saxlamalıdır; projection lazım olanı öz state-ə çəkər
  3. Hər state dəyişikliyini event etmək — business-meaningful fact-lar event-dir
  4. Event-ləri mutable etmək — event baş vermişdir; dəyişdirilə bilməz

## Nümunələr

### Ümumi Nümunə
Bank hesabı düşünün: `accounts` cədvəlindəki `balance=1500` "bir şey baş verdi" demirsə, tarix yoxdur. Event Sourcing ilə: `AccountOpened(0)` → `MoneyDeposited(2000)` → `MoneyWithdrawn(500)` → balance=1500. İstənilən vaxt "12 gün əvvəl balansım nə idi?" sualına cavab vermək mümkündür. Suspicious transaction kəşf etsəniz, o anın event stream-ini replay edib debug edə bilərsiniz.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\EventSourcing;

// ─────────────────────────────────────────────
// DOMAIN EVENTS — immutable facts
// ─────────────────────────────────────────────

abstract class DomainEvent
{
    public readonly string             $eventId;
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->eventId    = (string) \Ramsey\Uuid\Uuid::uuid4();
        $this->occurredAt = new \DateTimeImmutable();
    }
}

final class OrderPlaced extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly string $currency,
    ) {
        parent::__construct();
    }
}

final class ItemAdded extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $productId,
        public readonly string $productName,
        public readonly int    $quantity,
        public readonly int    $unitPriceCents,
    ) {
        parent::__construct();
    }
}

final class OrderConfirmed extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly int    $totalAmountCents,
    ) {
        parent::__construct();
    }
}

final class OrderCancelled extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
        public readonly string $cancelledBy,
    ) {
        parent::__construct();
    }
}

final class OrderShipped extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $trackingNumber,
        public readonly string $carrier,
    ) {
        parent::__construct();
    }
}
```

**Event Store Interface + Implementation:**

```php
<?php

// ─────────────────────────────────────────────
// EVENT STORE
// ─────────────────────────────────────────────

interface EventStore
{
    /**
     * Event stream-ə yeni event-lər əlavə et (append-only)
     */
    public function append(string $streamId, int $expectedVersion, DomainEvent ...$events): void;

    /**
     * Bir stream-in bütün event-lərini qaytar
     * @return DomainEvent[]
     */
    public function load(string $streamId): array;

    /**
     * Stream-in müəyyən versiyasından sonrakı event-lər
     * @return DomainEvent[]
     */
    public function loadFromVersion(string $streamId, int $fromVersion): array;
}

class DatabaseEventStore implements EventStore
{
    public function append(string $streamId, int $expectedVersion, DomainEvent ...$events): void
    {
        \DB::transaction(function () use ($streamId, $expectedVersion, $events) {
            // Optimistic concurrency: stream-in cari versiyasını yoxla
            $currentVersion = \DB::table('event_store')
                ->where('stream_id', $streamId)
                ->max('version') ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw new ConcurrencyException(
                    "Stream {$streamId}: expected version {$expectedVersion}, got {$currentVersion}"
                );
            }

            $version = $expectedVersion;
            foreach ($events as $event) {
                $version++;
                \DB::table('event_store')->insert([
                    'event_id'   => $event->eventId,
                    'stream_id'  => $streamId,
                    'event_type' => get_class($event),
                    'payload'    => json_encode($this->serialize($event)),
                    'version'    => $version,
                    'occurred_at'=> $event->occurredAt->format('Y-m-d H:i:s.u'),
                ]);
            }
        });
    }

    public function load(string $streamId): array
    {
        return $this->loadFromVersion($streamId, 0);
    }

    public function loadFromVersion(string $streamId, int $fromVersion): array
    {
        $rows = \DB::table('event_store')
            ->where('stream_id', $streamId)
            ->where('version', '>', $fromVersion)
            ->orderBy('version')
            ->get();

        return $rows->map(fn($row) => $this->deserialize($row->event_type, json_decode($row->payload, true)))->all();
    }

    private function serialize(DomainEvent $event): array
    {
        return get_object_vars($event);
    }

    private function deserialize(string $eventType, array $data): DomainEvent
    {
        // Reflection ilə reconstruct — ya da manual match
        return match ($eventType) {
            OrderPlaced::class    => new OrderPlaced($data['orderId'], $data['customerId'], $data['currency']),
            ItemAdded::class      => new ItemAdded($data['orderId'], $data['productId'], $data['productName'], $data['quantity'], $data['unitPriceCents']),
            OrderConfirmed::class => new OrderConfirmed($data['orderId'], $data['totalAmountCents']),
            OrderCancelled::class => new OrderCancelled($data['orderId'], $data['reason'], $data['cancelledBy']),
            OrderShipped::class   => new OrderShipped($data['orderId'], $data['trackingNumber'], $data['carrier']),
            default               => throw new \RuntimeException("Unknown event type: {$eventType}"),
        };
    }
}
```

**Aggregate with Event Sourcing:**

```php
<?php

// ─────────────────────────────────────────────
// AGGREGATE — event-lərlə reconstruct olunur
// ─────────────────────────────────────────────

class Order
{
    private string      $id;
    private string      $customerId;
    private OrderStatus $status;
    private array       $items          = [];
    private int         $version        = 0;
    private array       $uncommittedEvents = [];

    // Constructor private — yalnız factory method-lar
    private function __construct() {}

    // Yeni order yaratmaq — event raise edir
    public static function place(string $customerId, string $currency): self
    {
        $order = new self();
        $order->raiseEvent(new OrderPlaced(
            orderId: (string) \Ramsey\Uuid\Uuid::uuid4(),
            customerId: $customerId,
            currency: $currency,
        ));
        return $order;
    }

    // Event stream-dən reconstruct — state query
    public static function reconstitute(array $events): self
    {
        $order = new self();
        foreach ($events as $event) {
            $order->apply($event);
            $order->version++;
        }
        return $order;
    }

    // Business method — event raise edir
    public function addItem(string $productId, string $productName, int $quantity, int $unitPriceCents): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new \DomainException("Cannot add items to {$this->status->value} order");
        }

        $this->raiseEvent(new ItemAdded(
            orderId: $this->id,
            productId: $productId,
            productName: $productName,
            quantity: $quantity,
            unitPriceCents: $unitPriceCents,
        ));
    }

    public function confirm(): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new \DomainException("Only pending orders can be confirmed");
        }

        if (empty($this->items)) {
            throw new \DomainException("Cannot confirm empty order");
        }

        $this->raiseEvent(new OrderConfirmed(
            orderId: $this->id,
            totalAmountCents: $this->calculateTotal(),
        ));
    }

    public function cancel(string $reason, string $cancelledBy): void
    {
        if (in_array($this->status, [OrderStatus::Shipped, OrderStatus::Cancelled])) {
            throw new \DomainException("Order cannot be cancelled");
        }

        $this->raiseEvent(new OrderCancelled($this->id, $reason, $cancelledBy));
    }

    // ─────────────────────────────────────────
    // APPLY — event state-i dəyişdirir; side effect yoxdur
    // ─────────────────────────────────────────

    private function apply(DomainEvent $event): void
    {
        match ($event::class) {
            OrderPlaced::class    => $this->onOrderPlaced($event),
            ItemAdded::class      => $this->onItemAdded($event),
            OrderConfirmed::class => $this->onOrderConfirmed($event),
            OrderCancelled::class => $this->onOrderCancelled($event),
            OrderShipped::class   => $this->onOrderShipped($event),
            default               => null,  // unknown event-lər skip edilir (forward compatibility)
        };
    }

    private function onOrderPlaced(OrderPlaced $event): void
    {
        $this->id         = $event->orderId;
        $this->customerId = $event->customerId;
        $this->status     = OrderStatus::Pending;
    }

    private function onItemAdded(ItemAdded $event): void
    {
        $this->items[$event->productId] = [
            'name'           => $event->productName,
            'quantity'       => $event->quantity,
            'unitPriceCents' => $event->unitPriceCents,
        ];
    }

    private function onOrderConfirmed(OrderConfirmed $event): void
    {
        $this->status = OrderStatus::Confirmed;
    }

    private function onOrderCancelled(OrderCancelled $event): void
    {
        $this->status = OrderStatus::Cancelled;
    }

    private function onOrderShipped(OrderShipped $event): void
    {
        $this->status = OrderStatus::Shipped;
    }

    // ─────────────────────────────────────────
    // EVENT MANAGEMENT
    // ─────────────────────────────────────────

    private function raiseEvent(DomainEvent $event): void
    {
        $this->apply($event);         // state-i dərhal güncəllə
        $this->uncommittedEvents[] = $event;
    }

    public function pullUncommittedEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];
        return $events;
    }

    public function getVersion(): int { return $this->version; }
    public function getId(): string   { return $this->id; }

    private function calculateTotal(): int
    {
        return array_reduce(
            $this->items,
            fn(int $sum, array $item) => $sum + ($item['unitPriceCents'] * $item['quantity']),
            0
        );
    }
}
```

**Projection — read model yaratmaq:**

```php
<?php

// ─────────────────────────────────────────────
// PROJECTION — event-lərdən read model
// ─────────────────────────────────────────────

class OrderSummaryProjection
{
    public function project(DomainEvent $event): void
    {
        match ($event::class) {
            OrderPlaced::class    => $this->onOrderPlaced($event),
            ItemAdded::class      => $this->onItemAdded($event),
            OrderConfirmed::class => $this->onOrderConfirmed($event),
            OrderCancelled::class => $this->onOrderCancelled($event),
            OrderShipped::class   => $this->onOrderShipped($event),
            default               => null,
        };
    }

    private function onOrderPlaced(OrderPlaced $event): void
    {
        \DB::table('order_summaries')->insert([
            'id'          => $event->orderId,
            'customer_id' => $event->customerId,
            'status'      => 'pending',
            'item_count'  => 0,
            'total_cents' => 0,
            'created_at'  => $event->occurredAt,
        ]);
    }

    private function onItemAdded(ItemAdded $event): void
    {
        \DB::table('order_summaries')
            ->where('id', $event->orderId)
            ->increment('item_count', 1);

        \DB::table('order_summaries')
            ->where('id', $event->orderId)
            ->increment('total_cents', $event->quantity * $event->unitPriceCents);
    }

    private function onOrderConfirmed(OrderConfirmed $event): void
    {
        \DB::table('order_summaries')
            ->where('id', $event->orderId)
            ->update(['status' => 'confirmed']);
    }

    private function onOrderCancelled(OrderCancelled $event): void
    {
        \DB::table('order_summaries')
            ->where('id', $event->orderId)
            ->update(['status' => 'cancelled', 'cancellation_reason' => $event->reason]);
    }

    private function onOrderShipped(OrderShipped $event): void
    {
        \DB::table('order_summaries')
            ->where('id', $event->orderId)
            ->update(['status' => 'shipped', 'tracking_number' => $event->trackingNumber]);
    }
}
```

**Snapshot — performance optimization:**

```php
<?php

class OrderRepository
{
    private const SNAPSHOT_THRESHOLD = 50;  // 50 eventdən sonra snapshot al

    public function __construct(
        private EventStore          $eventStore,
        private SnapshotStore       $snapshots,
        private OrderSummaryProjection $projection,
    ) {}

    public function save(Order $order): void
    {
        $events = $order->pullUncommittedEvents();

        $this->eventStore->append($order->getId(), $order->getVersion() - count($events), ...$events);

        foreach ($events as $event) {
            $this->projection->project($event);
        }

        // Snapshot threshold-u keçibsə, snapshot al
        if ($order->getVersion() % self::SNAPSHOT_THRESHOLD === 0) {
            $this->snapshots->save($order->getId(), $order->getVersion(), $order->toSnapshot());
        }
    }

    public function findById(string $orderId): Order
    {
        $snapshot = $this->snapshots->findLatest($orderId);

        if ($snapshot) {
            // Snapshot-dan bərpa et, sonra yalnız yeni event-ləri replay et
            $order = Order::fromSnapshot($snapshot->data);
            $events = $this->eventStore->loadFromVersion($orderId, $snapshot->version);
        } else {
            $events = $this->eventStore->load($orderId);
            $order = Order::reconstitute($events);
        }

        return $order;
    }
}
```

**Spatie Laravel Event Sourcing — package ilə:**

```php
<?php

// composer require spatie/laravel-event-sourcing

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderAggregateRoot extends AggregateRoot
{
    private string $status = 'draft';

    public function placeOrder(string $customerId): self
    {
        $this->recordThat(new OrderPlaced($this->uuid(), $customerId, 'USD'));
        return $this;
    }

    public function applyOrderPlaced(OrderPlaced $event): void
    {
        $this->status = 'pending';
    }
}

// Usage
$order = OrderAggregateRoot::retrieve($uuid);
$order->placeOrder($customerId)->persist();
```

## Praktik Tapşırıqlar
1. `event_store` migration yaradın: `id`, `stream_id`, `event_type`, `payload` (JSON), `version`, `occurred_at`; `BankAccount` aggregate ilə (`AccountOpened`, `MoneyDeposited`, `MoneyWithdrawn`) test edin
2. `AccountBalanceProjection` yazın: `MoneyDeposited` → balance artır, `MoneyWithdrawn` → azalır; projection-ı başdan rebuild edin (bütün event-ləri yenidən replay); sonuç `account_balances` cədvəlindədir
3. `spatie/laravel-event-sourcing` package-ini qurun; sadə `CartAggregateRoot` yazın; `AddedToCart`, `RemovedFromCart`, `CartCheckedOut` event-ləri; `CartItemsProjector` yazın
4. Time travel sorğusu: `$balanceAt = $this->getBalanceAt($accountId, '2024-01-15')` — həmin tarixə qədər olan event-ləri replay edin; cari state deyil, tarixi state qaytarın

## Əlaqəli Mövzular
- [CQRS](25-cqrs.md) — natural pair: command-lar event store-a yazır, query-lər projection-lardan oxuyur
- [DDD Tactical Patterns](26-ddd-patterns.md) — domain event-lər DDD aggregate-lərindən raise olunur
- [Hexagonal Architecture](30-hexagonal-architecture.md) — event store infrastructure adapter-dır; domain onu interface vasitəsilə bilir
- [Observer](06-observer.md) — projection-lar event subscriber-dır; event sourcing-in read side-ı
