# Enterprise Integration Patterns (EIP) (Lead ⭐⭐⭐⭐)

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

## Anti-Pattern Nə Zaman Olur?

**Simple request/response üçün message routing over-engineering:**
2 servis arasında sadə HTTP sorğusu EIP pattern-ləri tələb etmir — message channel, content-based router, aggregator qurulsa complexity artır, debugging çətinləşir, overhead böyüyür. EIP pattern-lərini yalnız real messaging sistemi lazım olduqda tətbiq edin: async, decoupled, yüksək həcmli event axını.

**Dead Letter Queue olmadan mesajlaşma:**
İşlənə bilməyən mesajlar DLQ-ya getmədən drop edilsə — kritik event-lər itirilir, debugging mümkün deyil. DLQ mütləq konfiqurasiya edilməli, monitoring ilə izlənməli, manual retry mexanizmi olmalıdır.

**Splitter olmadan aggregator:**
Aggregator state saxlayır (hansı hissələr gəldi, hansı gəlmədi). State management olmadan: partial message qəbul edilib session bitir, aggregation yarımçıq qalır. Splitter + Aggregator cütü birlikdə planlaşdırılmalı, timeout + incomplete message siyasəti olmalıdır.

**Idempotent Consumer olmadan at-least-once delivery:**
Message broker eyni mesajı bir neçə dəfə göndərə bilər. Consumer idempotent deyilsə: ikinci email, ikinci charge, ikinci inventory azalma. Hər consumer-də idempotency key (message ID) yoxlanmalıdır.

## Praktik Tapşırıqlar

1. Content-Based Router yazın: `OrderPlacedEvent`-i ölkəyə görə 3 fərqli queue-ya yönləndir (US, EU, APAC); test: hər region düzgün queue-ya gedir
2. Splitter + Aggregator: `BulkOrderEvent` → hər item ayrı `ProcessItemCommand`; Redis-də partial state; hamısı tamamlandıqda `BulkOrderCompletedEvent` fire et
3. Symfony Messenger-i qurun: `failed` transport (DLQ); test: handler exception atır → DLQ-ya düşür; `messenger:failed:retry` ilə yenidən cəhd edin
4. Idempotent Consumer yazın: inbox table ilə; eyni message iki dəfə gəlsə bir dəfə işlənsin; PHPUnit test: 3 eyni message → 1 DB row

## Əlaqəli Mövzular

- [API Composition Pattern](10-api-composition-pattern.md) — Scatter-Gather EIP-nin bir formasıdır
- [Choreography vs Orchestration](11-choreography-vs-orchestration.md) — EIP mesajlaşma ilə orchestration əlaqəsi
- [Outbox Pattern](04-outbox-pattern.md) — reliable message delivery EIP ilə birlikdə
- [Event Listener](../laravel/05-event-listener.md) — Laravel-in EIP-yə ən yaxın mexanizmi

---

## İntervyu Sualları

- EIP nədir? Hansı kitabdan gəlir?
- Content-Based Router ilə Recipient List fərqi nədir?
- Splitter və Aggregator pattern-lərini birlikdə nə zaman istifadə edərsiniz?
- Dead Letter Queue nəyə lazımdır?
- Scatter-Gather pattern API Composition ilə necə əlaqəlidir?
- Symfony Messenger hansı EIP pattern-lərini dəstəkləyir?
