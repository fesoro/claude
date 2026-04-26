# Enterprise Integration Patterns (EIP) (Lead)

## Mündəricat
1. [EIP nədir?](#eip-nədir)
2. [Əsas Pattern-lər](#əsas-pattern-lər)
3. [Router Pattern-ləri](#router-pattern-ləri)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## EIP nədir?

```
Gregor Hohpe & Bobby Woolf — "Enterprise Integration Patterns" (2003)
Mesajlaşma sistemlərini dizayn etmək üçün 65+ pattern.

Əsas kateqoriyalar:
  Message Construction  — mesaj yaratmaq
  Message Routing       — mesajı yönləndirmək
  Message Transformation — mesajı çevirmək
  Message Endpoints     — mesaj qəbul/göndərmək
  Channels              — mesaj kanalları

Praktik EIP stack:
  Apache Camel, Spring Integration, MassTransit (.NET)
  PHP: Symfony Messenger (bəzi pattern-lər)
```

---

## Əsas Pattern-lər

```
1. Message Channel:
   Producer → [Channel] → Consumer
   Typed channel: hər channel bir tip mesaj daşıyır

2. Message Filter:
   [Channel] → [Filter] → yalnız uyğun mesajlar keçir
   Nümunə: yalnız amount > 1000 olan sifarişlər

3. Content-Based Router:
   Mesaj məzmununa görə fərqli kanala yönləndir
   if order.country == 'US' → us-orders
   if order.country == 'EU' → eu-orders

4. Message Splitter:
   Bir mesajı bir neçə mesaja böl
   [OrderWithItems] → [Item1, Item2, Item3]

5. Message Aggregator:
   Bir neçə mesajı bir mesaja birləşdir
   [Part1, Part2, Part3] → [CompleteOrder]
   Splitter-in tərsi

6. Dead Letter Channel (DLQ):
   Emal edilə bilməyən mesajlar bu kanala gedir
   Manual müdaxilə üçün

7. Idempotent Consumer:
   Eyni mesaj iki dəfə gəlsə yalnız bir dəfə emal et
   (topik 86 — idempotency)

8. Message Envelope (Wrapper):
   Məzmun + metadata (correlation-id, timestamp, type)
   Routing metadata-ya görə, məzmun consumer-ə görə
```

---

## Router Pattern-ləri

```
Content-Based Router:
  ┌──────────┐    ┌─────────────────┐    ┌─────────────┐
  │ Producer │───►│  CBR Router     │───►│ US Channel  │
  └──────────┘    │                 │───►│ EU Channel  │
                  │  order.country? │───►│ APAC Channel│
                  └─────────────────┘    └─────────────┘

Recipient List:
  Bir mesajı birdən çox kanala göndər
  Splitter-dən fərqi: eyni mesaj, çoxlu alıcı

Scatter-Gather:
  Recipient List + Aggregator
  Mesajı paylaştır → cavabları topla → birləşdir
  (API Composition pattern-i kimi — topik 123)

Process Manager:
  Mürəkkəb workflow-ları idarə edir
  Orchestration Saga (topik 121) ilə oxşar
```

---

## PHP İmplementasiyası

```php
<?php
// Symfony Messenger ilə EIP pattern-lər

// 1. Message Filter — Middleware
namespace App\Messaging\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class OrderAmountFilterMiddleware implements MiddlewareInterface
{
    public function __construct(
        private float $minimumAmount = 0.01,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if ($message instanceof OrderPlacedEvent) {
            if ($message->amount < $this->minimumAmount) {
                // Filter: mesajı drop et (log et)
                $this->logger->info('Order filtered — amount too low', [
                    'orderId' => $message->orderId,
                    'amount'  => $message->amount,
                ]);
                return $envelope; // İşlənmir, amma exception da yoxdur
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
```

```php
<?php
// 2. Content-Based Router
class OrderRouter
{
    public function __construct(
        private MessageBusInterface $usBus,
        private MessageBusInterface $euBus,
        private MessageBusInterface $apacBus,
    ) {}

    public function route(OrderPlacedEvent $event): void
    {
        $bus = match (true) {
            in_array($event->country, ['US', 'CA'])         => $this->usBus,
            in_array($event->country, ['DE', 'FR', 'NL'])   => $this->euBus,
            default                                          => $this->apacBus,
        };

        $bus->dispatch($event);
    }
}
```

```php
<?php
// 3. Splitter + Aggregator
class OrderItemSplitter
{
    public function split(OrderPlacedEvent $event): array
    {
        // Bir Order → çoxlu ProcessItemCommand
        return array_map(
            fn($item) => new ProcessOrderItemCommand(
                orderId:   $event->orderId,
                productId: $item['product_id'],
                quantity:  $item['quantity'],
            ),
            $event->items,
        );
    }
}

// Aggregator — Redis ilə state
class OrderItemAggregator
{
    public function __construct(private \Redis $redis) {}

    public function aggregate(ItemProcessedEvent $event): ?OrderReadyEvent
    {
        $key   = "agg:{$event->orderId}";
        $total = (int) $this->redis->get("{$key}:total");
        $done  = $this->redis->incr("{$key}:done");

        if ($done >= $total) {
            $this->redis->del($key);
            return new OrderReadyEvent($event->orderId);
        }

        return null; // Hələ tam deyil
    }
}
```

```php
<?php
// 4. Dead Letter Queue — Symfony Messenger
// messenger.yaml:
// framework:
//   messenger:
//     failure_transport: failed
//     transports:
//       failed:
//         dsn: 'doctrine://default?queue_name=failed'
//     routing:
//       App\Message\OrderPlacedEvent: async

// Manual retry:
// bin/console messenger:failed:retry
// bin/console messenger:failed:show
```

---

## İntervyu Sualları

- EIP nədir? Hansı kitabdan gəlir?
- Content-Based Router ilə Recipient List fərqi nədir?
- Splitter və Aggregator pattern-lərini birlikdə nə zaman istifadə edərsiniz?
- Dead Letter Queue nəyə lazımdır?
- Scatter-Gather pattern API Composition ilə necə əlaqəlidir?
- Symfony Messenger hansı EIP pattern-lərini dəstəkləyir?
