# Policy / Handler Pattern (Senior ⭐⭐⭐)

## İcmal

DDD-nin event-driven iş axınında iki vacib konsept: **Policy** — "X baş verəndə Y et" kimi ifadə olunan business rule-dur, domain event-ə reaksiya verərək command yaradır. **Handler** — command-i icra edən sinifdir, application layer-dədir, use case-i orkestrasiya edir. Axın: `Domain Event → Policy → Command → Handler → Domain changes`.

## Niyə Vacibdir

Bu pattern event-driven sistemlərdə domain-in reaktif davranışını explicit edir. Hər "When X then Y" qaydası öz adı ilə bir Policy sinfi olduğunda, sistemi başa düşmək, test etmək, genişləndirmək asanlaşır. Sadə event listener-dan fərqi: Policy ubiquitous language-i kod səviyyəsindədir.

## Əsas Anlayışlar

- **Policy**: domain event dinləyir; "When [Event] then [Command]" məntiqini ifadə edir; command yaradır, özü icra etmir
- **Handler**: bir Command-a bir Handler; application layer-dadır; repository-dən yükləyir, domain service çağırır, persist edir
- **Command**: handler-ə nə etmək lazım olduğunu bildirən immutable data object
- **Command Bus**: command-i düzgün handler-ə çatdıran dispatcher; middleware əlavə etmək imkanı verir
- **Ubiquitous Language**: `WhenOrderPlacedThenReserveInventory` — adı oxumaqla nə etdiyini başa düşmək

## Praktik Baxış

- **Real istifadə**: order placement → inventory reservation, payment received → subscription activation, user registered → welcome workflow, invoice overdue → account suspension
- **Trade-off-lar**: kod çox explicit olur — hər qayda öz class-ında; debug mürəkkəbləşir (event → policy → command → handler zənciri); over-architecture riski var
- **İstifadə etməmək**: sadə CRUD operasiyalarda; bir listener-də bir metod çağırış kifayət edirsə; team DDD-ni bilmirsə

- **Common mistakes**:
  1. Policy-nin içinə domain logic yazmaq — Policy yalnız yönləndirir, işi Handler edir
  2. Handler-dən başqa Handler çağırmaq — event/command vasitəsilə əvəz et
  3. Command-ı mutable etmək — command immutable data object olmalıdır

### Anti-Pattern Nə Zaman Olur?

**Authorization Policy-ni Business Logic Policy ilə qarışdırmaq:**
```php
// BAD — Laravel Policy (authorization) + DDD Policy-ni qarışdırmaq
// Laravel-in Policy-si (app/Policies/OrderPolicy.php) authorization üçündür:
class OrderPolicy
{
    public function update(User $user, Order $order): bool
    {
        // Bu authorization-dır — "kim nə edə bilər"
        return $user->id === $order->user_id;
    }

    // BU YANLIŞ — business logic authorization policy-sinin içinə girməməlidir
    public function update(User $user, Order $order): bool
    {
        // Authorization + business logic qarışığı
        return $user->id === $order->user_id
            && $order->status !== 'shipped'        // business rule
            && $order->total < 10000               // business rule
            && !$order->hasDigitalItems();          // business rule
    }
}

// GOOD — authorization sadə, business rule domain-də
class OrderPolicy
{
    public function update(User $user, Order $order): bool
    {
        return $user->id === $order->user_id; // Yalnız authorization
    }
}

class Order // Domain entity
{
    public function update(array $data): void
    {
        if ($this->status === 'shipped') {
            throw new \DomainException("Shipped order cannot be updated");
        }
        // Business rule domain-də
    }
}
```

**Policy işi özü edir (handler-ə vermir):**
```php
// BAD — Policy command yaratmır, birbaşa işi edir
class WhenOrderPlacedThenReserveInventory
{
    public function __invoke(OrderPlacedEvent $event): void
    {
        // Policy-nin işi yox! Bu handler-in işidir
        $order = Order::find($event->orderId);
        foreach ($order->items as $item) {
            $stock = Stock::find($item->product_id);
            $stock->reserve($item->quantity);
            $stock->save();
        }
    }
}

// GOOD — Policy yalnız yönləndirir
class WhenOrderPlacedThenReserveInventory
{
    public function __construct(private CommandBusInterface $commandBus) {}

    public function __invoke(OrderPlacedEvent $event): void
    {
        // Policy: event → command; yönləndirir, özü etmir
        $this->commandBus->dispatch(
            new ReserveInventoryCommand(
                orderId: $event->orderId,
                items:   $event->items,
            )
        );
    }
}
```

## Nümunələr

### Ümumi Nümunə

E-commerce-də sifariş verilir. Domain event `OrderPlacedEvent` fire olur. `WhenOrderPlacedThenReserveInventory` Policy bu event-i eşidir, `ReserveInventoryCommand` yaradır və bus-a göndərir. `ReserveInventoryHandler` command-i alır, inventory-ni yükləyir, reserve edir, InventoryReserved event fire edir. Hər addım müstəqil, test edilə bilən.

### PHP/Laravel Nümunəsi

```php
<?php

// Domain Event — nə baş verdi
namespace App\Domain\Order\Event;

class OrderPlacedEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly array  $items,       // [['product_id' => '...', 'quantity' => 2], ...]
    ) {}
}
```

```php
<?php

// Policy — "When OrderPlaced Then ReserveInventory"
// Adı qayda kimi oxunur — ubiquitous language
namespace App\Application\Policy;

use App\Domain\Order\Event\OrderPlacedEvent;
use App\Application\Inventory\ReserveInventoryCommand;

class WhenOrderPlacedThenReserveInventory
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {}

    // Policy yalnız command yaradır və bus-a verir — heç bir iş etmir
    public function __invoke(OrderPlacedEvent $event): void
    {
        $this->commandBus->dispatch(
            new ReserveInventoryCommand(
                orderId: $event->orderId,
                items:   $event->items,
            )
        );
    }
}
```

```php
<?php

// Command — handler-ə nə etmək lazım olduğunu bildirir; immutable
namespace App\Application\Inventory;

readonly class ReserveInventoryCommand
{
    public function __construct(
        public string $orderId,
        public array  $items,
    ) {}
}
```

```php
<?php

// Handler — use case orkestrasiya edir; business logic etmır, düzenler
class ReserveInventoryHandler
{
    public function __construct(
        private InventoryRepository $inventory,
        private OrderRepository     $orders,
        private EventBusInterface   $eventBus,
    ) {}

    public function __invoke(ReserveInventoryCommand $cmd): void
    {
        $order = $this->orders->findById($cmd->orderId)
            ?? throw new OrderNotFoundException($cmd->orderId);

        foreach ($cmd->items as $item) {
            $stock = $this->inventory->findByProductId($item['product_id'])
                ?? throw new ProductNotFoundException($item['product_id']);

            // Domain logic entity-də — Handler çağırır, özü etmir
            $stock->reserve($item['quantity']);
            $this->inventory->save($stock);
        }

        // Sonrakı axın üçün event — başqa Policy eşidər
        $this->eventBus->publish(
            new InventoryReservedEvent($cmd->orderId)
        );
    }
}
```

**Laravel-də wiring:**

```php
// EventServiceProvider — event → Policy mapping
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPlacedEvent::class => [
            WhenOrderPlacedThenReserveInventory::class,
            WhenOrderPlacedThenSendConfirmation::class,
            WhenOrderPlacedThenNotifyWarehouse::class,
        ],

        // Zəncir davam edir
        InventoryReservedEvent::class => [
            WhenInventoryReservedThenScheduleShipment::class,
        ],
    ];
}
```

**Policy test etmək:**

```php
class WhenOrderPlacedThenReserveInventoryTest extends TestCase
{
    public function test_dispatches_reserve_inventory_command(): void
    {
        $commandBus = $this->createMock(CommandBusInterface::class);

        // Command bus-a doğru command gəlməlidi
        $commandBus->expects($this->once())
                   ->method('dispatch')
                   ->with($this->callback(function ($cmd) {
                       return $cmd instanceof ReserveInventoryCommand
                           && $cmd->orderId === 'order-123';
                   }));

        $policy = new WhenOrderPlacedThenReserveInventory($commandBus);
        $policy(new OrderPlacedEvent(
            orderId:    'order-123',
            customerId: 'cust-456',
            items:      [['product_id' => 'prod-1', 'quantity' => 2]],
        ));
    }
}
```

**Handler test etmək:**

```php
class ReserveInventoryHandlerTest extends TestCase
{
    public function test_reserves_each_item(): void
    {
        $stock = $this->createMock(Stock::class);
        $stock->expects($this->once())->method('reserve')->with(2);

        $inventory = $this->createMock(InventoryRepository::class);
        $inventory->method('findByProductId')->willReturn($stock);
        $inventory->expects($this->once())->method('save');

        $orders = $this->createMock(OrderRepository::class);
        $orders->method('findById')->willReturn(new Order('order-123'));

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects($this->once())
                 ->method('publish')
                 ->with($this->isInstanceOf(InventoryReservedEvent::class));

        $handler = new ReserveInventoryHandler($inventory, $orders, $eventBus);
        $handler(new ReserveInventoryCommand('order-123', [
            ['product_id' => 'prod-1', 'quantity' => 2],
        ]));
    }
}
```

## Praktik Tapşırıqlar

1. `WhenPaymentReceivedThenActivateSubscription` Policy + `ActivateSubscriptionHandler` yazın; test edin
2. Mövcud bir event listener-ı Policy+Handler pattern-inə çevirin; müqayisəni müşahidə edin
3. `WhenInvoiceOverdueThenSuspendAccount` policy-si üçün tam axını qurun (event → policy → command → handler → event)

## Əlaqəli Mövzular

- [05-event-listener.md](05-event-listener.md) — Policy əslində event listener-dır; fərqi explicit naming
- [08-command-query-bus.md](08-command-query-bus.md) — Policy command bus-a göndərir
- [../ddd/05-ddd-domain-events.md](../ddd/05-ddd-domain-events.md) — Domain events Policy-nin trigger-ıdır
- [../ddd/08-domain-service-vs-app-service.md](../ddd/08-domain-service-vs-app-service.md) — Handler application service rolundadır
- [../integration/01-cqrs.md](../integration/01-cqrs.md) — Command/Query separation; Policy command yazır
- [../behavioral/08-mediator.md](../behavioral/08-mediator.md) — Command bus Mediator pattern-in tətbiqidir
- [../behavioral/03-command.md](../behavioral/03-command.md) — Command pattern əsasları
