# Correlation ID və Causation ID

## Mündəricat
1. [Nədir?](#nədir)
2. [Fərq](#fərq)
3. [Distributed Tracing ilə Əlaqə](#distributed-tracing-ilə-əlaqə)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Nədir?

```
Distributed event-driven sistemdə mesajları izləmək üçün:

Correlation ID:
  Bir iş axınının bütün mesajlarını birləşdirən unikal ID.
  "Bu mesajlar eyni sorğuya aiddir."

  HTTP Request → [correlation-id: abc-123]
    → OrderService publishes event [correlation-id: abc-123]
    → InventoryService processes [correlation-id: abc-123]
    → PaymentService processes [correlation-id: abc-123]
    → NotifyService processes [correlation-id: abc-123]

  Logda axtarış: correlation_id = "abc-123" → bütün axın

Causation ID:
  Bu mesajın nəyin nəticəsindədir?
  "Hansı mesaj bu mesajı yaratdı?"

  OrderPlacedEvent [id: evt-1, correlation: abc-123]
      → ReserveInventoryCommand [id: cmd-1, correlation: abc-123, causation: evt-1]
      → InventoryReservedEvent  [id: evt-2, correlation: abc-123, causation: cmd-1]
      → ChargePaymentCommand    [id: cmd-2, correlation: abc-123, causation: evt-2]
```

---

## Fərq

```
Correlation ID — "eyni işə aiddir"
  Axın boyu dəyişmir.
  HTTP request-dən event chain-in sonuna qədər eyni dəyər.

Causation ID — "kim yaratdı bunu?"
  Hər mesajda fərqlidir.
  Parent mesajın ID-si.
  Event graph qura bilərsiniz.

Vizual:

  [HTTP Request: abc-123]
       │
       ▼
  [OrderPlaced: evt-1] ← correlation=abc-123, causation=abc-123 (request-dən)
       │
       ▼
  [ReserveInventory: cmd-1] ← correlation=abc-123, causation=evt-1
       │
       ▼
  [InventoryReserved: evt-2] ← correlation=abc-123, causation=cmd-1
       │
       ▼
  [ChargePayment: cmd-2] ← correlation=abc-123, causation=evt-2

  Correlation ilə axtararsınız: "abc-123 — bütün axın"
  Causation ilə axtararsınız: "evt-1-dən nə yarandı?"
```

---

## Distributed Tracing ilə Əlaqə

```
OpenTelemetry / Jaeger konseptləri ilə oxşarlıq:

Trace ID ≈ Correlation ID
  Bütün span-ları birləşdirir

Parent Span ID ≈ Causation ID
  Hansı span-dan yarandı?

Fərq:
  Correlation/Causation — domain event-lərə əl ilə əlavə edilir
  OpenTelemetry — HTTP/gRPC/queue üçün avtomatik instrumentasiya

Hər ikisi birlikdə istifadə edilə bilər:
  OTel trace-ı texniki layer-ı izləyir
  Correlation/Causation biznes event-lərini izləyir
```

---

## PHP İmplementasiyası

```php
<?php
// Message envelope — hər mesajda bu metadata
namespace App\Messaging;

class MessageMetadata
{
    public function __construct(
        public readonly string  $messageId,
        public readonly string  $correlationId,
        public readonly ?string $causationId,
        public readonly string  $occurredAt,
    ) {}

    public static function forNewRequest(): self
    {
        $id = self::generateId();
        return new self(
            messageId:     $id,
            correlationId: $id,    // Yeni axın — correlation = özü
            causationId:   null,
            occurredAt:    (new \DateTimeImmutable())->format(\DateTime::ATOM),
        );
    }

    public static function fromParent(self $parent): self
    {
        return new self(
            messageId:     self::generateId(),
            correlationId: $parent->correlationId,  // Eyni correlation
            causationId:   $parent->messageId,       // Parent-in ID-si
            occurredAt:    (new \DateTimeImmutable())->format(\DateTime::ATOM),
        );
    }

    private static function generateId(): string
    {
        return sprintf('%04x%04x-%04x-4%03x-%04x-%012x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff),
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffffffffffff)
        );
    }
}
```

```php
<?php
// Middleware: HTTP request-dən correlation ID oxu/yarat
class CorrelationIdMiddleware
{
    public function process(Request $request, Handler $handler): Response
    {
        $correlationId = $request->headers->get('X-Correlation-ID')
            ?? MessageMetadata::forNewRequest()->correlationId;

        // Request scope-da saxla
        $this->container->set('correlation_id', $correlationId);

        // Log context-ə əlavə et
        $this->logger->pushProcessor(fn($record) => array_merge($record, [
            'extra' => array_merge($record['extra'] ?? [], [
                'correlation_id' => $correlationId,
            ]),
        ]));

        $response = $handler->handle($request);

        // Response header-a əlavə et
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
```

```php
<?php
// Event publishing — metadata propagate et
class OrderService
{
    public function placeOrder(PlaceOrderCommand $cmd, MessageMetadata $parent): void
    {
        $order = Order::place($cmd);
        $this->orders->save($order);

        // Parent metadata-dan yeni metadata yarat
        $eventMeta = MessageMetadata::fromParent($parent);

        $this->eventBus->publish(
            new OrderPlacedEvent($order->getId()),
            $eventMeta,
        );
    }
}

// Log çıxışı:
// {"message":"Order placed","correlation_id":"abc-123","causation_id":null,"message_id":"evt-1"}
// {"message":"Inventory reserved","correlation_id":"abc-123","causation_id":"evt-1","message_id":"evt-2"}
// {"message":"Payment charged","correlation_id":"abc-123","causation_id":"evt-2","message_id":"evt-3"}
```

---

## İntervyu Sualları

- Correlation ID ilə Causation ID arasındakı fərq nədir?
- Distributed sistemdə bir sorğunu log-da izləmək üçün nəyi istifadə edərdiniz?
- HTTP request gələndə Correlation ID yoxdursa nə etmək lazımdır?
- Event chain-ini reconstruct etmək üçün hansı sahə lazımdır?
- OpenTelemetry Trace ID ilə Correlation ID eyni şeydir?
- Causation ID olmadan debug niyə çətindir?
