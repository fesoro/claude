# Policy / Handler Pattern (DDD) (Senior)

## Mündəricat
1. [Pattern nədir?](#pattern-nədir)
2. [Policy (Siyasət)](#policy-siyasət)
3. [Handler](#handler)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Pattern nədir?

```
DDD-də event-driven iş axınında iki vacib konsept:

Policy (Siyasət):
  "X baş verəndə Y et" — biznes qaydası
  Domain event-ə reaksiya verən qayda
  Adı: "When [Event] then [Command]"

Handler:
  Command-ı icra edən sinif
  Application layer-dadır
  Use case-i orkestrasiya edir

Axın:
  Domain Event → Policy → Command → Handler → Domain changes

Nümunə:
  OrderPlacedEvent
      ↓ (Policy: "Sifariş verildikdə stoku rezerv et")
  ReserveInventoryCommand
      ↓ (Handler: ReserveInventoryHandler)
  Inventory.reserve()
```

---

## Policy (Siyasət)

```
Policy xüsusiyyətləri:
  ✓ Domain Event dinləyir
  ✓ "When X then Y" məntiqini ifadə edir
  ✓ Command yaradır (özü icra etmir)
  ✓ Domain language ilə adlandırılır
  ✗ Biznes məntiqi saxlamır (yalnız yönləndirir)

Nümunələr:
  WhenOrderPlacedThenReserveInventory
  WhenPaymentReceivedThenActivateSubscription
  WhenUserRegisteredThenSendWelcomeEmail
  WhenInvoiceOverdueThenSuspendAccount

Policy niyə ayrı?
  Event handler-ın içinə command publish etmək mümkündür.
  Amma Policy ayrı sinif olduqda:
    → Adlandırma (ubiquitous language)
    → Test edilə bilər
    → Oxunur: "Bu event-ə nə baş verir?"
```

---

## Handler

```
Command Handler xüsusiyyətləri:
  ✓ Bir Command-a bir Handler
  ✓ Application layer-da yaşayır
  ✓ Repository-dən yükləyir
  ✓ Domain service çağırır
  ✓ Persist edir
  ✗ Birbaşa digər Handler çağırmır

Command Bus pattern:
  CommandBus.dispatch(command) → düzgün Handler tapır

  Handler-ları əl ilə çağırmaq yerinə Bus istifadə et:
    + Middleware (logging, transaction, authorization)
    + Decoupling
    + Test edilə bilər
```

---

## PHP İmplementasiyası

```php
<?php
// Domain Event
namespace App\Domain\Order\Event;

class OrderPlacedEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly array  $items,
    ) {}
}
```

```php
<?php
// Policy — "When OrderPlaced Then ReserveInventory"
namespace App\Application\Policy;

use App\Domain\Order\Event\OrderPlacedEvent;
use App\Application\Inventory\ReserveInventoryCommand;
use Symfony\Component\Messenger\MessageBusInterface;

class WhenOrderPlacedThenReserveInventory
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    public function __invoke(OrderPlacedEvent $event): void
    {
        // Policy: yalnız Command yaradır və yönləndirir
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
// Command
namespace App\Application\Inventory;

class ReserveInventoryCommand
{
    public function __construct(
        public readonly string $orderId,
        public readonly array  $items,
    ) {}
}

// Handler — use case orkestrasiya
class ReserveInventoryHandler
{
    public function __construct(
        private InventoryRepository  $inventory,
        private OrderRepository      $orders,
        private EventBusInterface    $eventBus,
    ) {}

    public function __invoke(ReserveInventoryCommand $cmd): void
    {
        $order = $this->orders->findById($cmd->orderId)
            ?? throw new OrderNotFoundException($cmd->orderId);

        foreach ($cmd->items as $item) {
            $stock = $this->inventory->findByProductId($item['product_id'])
                ?? throw new ProductNotFoundException($item['product_id']);

            $stock->reserve($item['quantity']); // Domain logic entity-də
            $this->inventory->save($stock);
        }

        $this->eventBus->publish(
            new InventoryReservedEvent($cmd->orderId)
        );
    }
}
```

```php
<?php
// Symfony Messenger konfiqurasiyası (messenger.yaml):
// framework:
//   messenger:
//     routing:
//       App\Application\Inventory\ReserveInventoryCommand: async
//
// Event → Policy → Command → Handler axını:
// EventBus: OrderPlacedEvent → WhenOrderPlacedThenReserveInventory (Policy)
// CommandBus: ReserveInventoryCommand → ReserveInventoryHandler
```

---

## İntervyu Sualları

- Policy pattern DDD-də nəyi ifadə edir?
- Policy-nin Handler-dan fərqi nədir?
- "When X then Y" adlandırması niyə vacibdir?
- Command Bus niyə Handler-ı birbaşa çağırmaqdan yaxşıdır?
- Policy test etmək üçün necə yanaşarsınız?
- Bir event-ə birdən çox Policy reaksiya verərsə nə baş verir?
