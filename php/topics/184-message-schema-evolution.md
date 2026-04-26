# Message Schema Evolution (Lead)

## Mündəricat
1. [Problem](#problem)
2. [Versioning Strategiyaları](#versioning-strategiyaları)
3. [Backward/Forward Compatibility](#backwardforward-compatibility)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Problem

```
Event-driven sistemdə mesaj formatı dəyişsə nə olur?

Producer (v1):
  { "orderId": "123", "amount": 99.99 }

Consumer (v1 gözləyir):
  OK — mesajı anlayır

Producer artıq v2 yayır:
  { "orderId": "123", "amount": 99.99, "currency": "USD", "customerId": "456" }

Consumer (hələ v1 gözləyir):
  ? — yeni sahəni ignore edir? Xəta verir?

Problem ssenariləri:
  1. Rolling deployment: producer v2, consumer hələ v1
  2. Replay: köhnə event-ləri yeni consumer emal edir
  3. Multiple consumers: fərqli versiyaları anlayan consumer-lar
```

---

## Versioning Strategiyaları

```
Strategiya 1 — Additive changes only (ən sadə):
  Yalnız yeni sahə əlavə edin, heç vaxt silin/dəyişdirin.
  Consumer yeni sahəni ignore edir.

  v1: { "orderId": "123", "amount": 99.99 }
  v2: { "orderId": "123", "amount": 99.99, "currency": "USD" }  ← əlavə
  
  v1 consumer → currency-ni ignore edir → OK

Strategiya 2 — Explicit versioning:
  { "version": 2, "orderId": "123", "amount": 99.99, "currency": "USD" }
  Consumer version-a görə fərqli handler seçir.

Strategiya 3 — Schema Registry (Confluent, AWS Glue):
  Hər schema versiyası qeydiyyatda saxlanılır.
  Producer/Consumer schema ID ilə mesaj göndərir/alır.
  Compatibility check avtomatik.

Strategiya 4 — Topic versioning:
  orders.v1 → köhnə consumer
  orders.v2 → yeni consumer
  Müddətli migration, sonra v1 retire.

Strategiya 5 — Upcasting (Event Sourcing-də):
  Köhnə event yükləndikdə → Upcaster yeni formata çevirir
  Verilənlər bazasındakı event-lər dəyişmir
```

---

## Backward/Forward Compatibility

```
Backward Compatible (köhnə consumer yeni mesajı oxuya bilir):
  Yeni sahə əlavə etmək → backward compatible
  Sahə silmək → NOT backward compatible
  Sahəni required etmək → NOT backward compatible

Forward Compatible (yeni consumer köhnə mesajı oxuya bilir):
  Köhnə mesajda olmayan sahəni optional etmək → forward compatible
  
Full compatibility (hər iki tərəf):
  Yalnız optional sahə əlavəsi
  Heç vaxt sahə silməyin
  Default dəyər verin

Protobuf/Avro üstünlüyü:
  Schema evolution qaydaları enforce edilir
  Field number/tag dəyişdirməyin — uyğunluğu pozur
  JSON-da bu qaydaları əl ilə izləmək lazımdır
```

---

## PHP İmplementasiyası

```php
<?php
// Versioned Event DTO
namespace App\Domain\Order\Event;

class OrderPlacedEvent
{
    public const CURRENT_VERSION = 2;

    public function __construct(
        public readonly string  $orderId,
        public readonly float   $amount,
        // v2-də əlavə edildi — nullable (backward compatible)
        public readonly ?string $currency   = null,
        public readonly ?string $customerId = null,
        public readonly int     $version    = self::CURRENT_VERSION,
    ) {}

    public function toArray(): array
    {
        return [
            'version'    => $this->version,
            'orderId'    => $this->orderId,
            'amount'     => $this->amount,
            'currency'   => $this->currency ?? 'USD', // default
            'customerId' => $this->customerId,
        ];
    }

    public static function fromArray(array $data): self
    {
        // Upcasting: v1 → v2
        if (($data['version'] ?? 1) === 1) {
            $data = self::upcastFromV1($data);
        }

        return new self(
            orderId:    $data['orderId'],
            amount:     $data['amount'],
            currency:   $data['currency']   ?? 'USD',
            customerId: $data['customerId'] ?? null,
            version:    $data['version'],
        );
    }

    private static function upcastFromV1(array $data): array
    {
        return array_merge($data, [
            'version'    => 2,
            'currency'   => 'USD',  // v1-də currency yox idi, default USD
            'customerId' => null,
        ]);
    }
}
```

```php
<?php
// Schema Registry ilə event consumer
class OrderEventConsumer
{
    private array $handlers = [];

    public function __construct()
    {
        // Version-a görə fərqli handler-lar
        $this->handlers[1] = new OrderPlacedV1Handler();
        $this->handlers[2] = new OrderPlacedV2Handler();
    }

    public function consume(string $payload): void
    {
        $data    = json_decode($payload, true);
        $version = $data['version'] ?? 1;

        $handler = $this->handlers[$version]
            ?? throw new UnknownEventVersionException("Version: {$version}");

        $handler->handle($data);
    }
}

// Event Sourcing Upcaster pipeline
class EventUpcasterPipeline
{
    /** @var callable[] */
    private array $upcasters = [];

    public function register(int $fromVersion, callable $upcaster): void
    {
        $this->upcasters[$fromVersion] = $upcaster;
    }

    public function upcast(array $event): array
    {
        $version = $event['version'] ?? 1;

        while (isset($this->upcasters[$version])) {
            $event   = ($this->upcasters[$version])($event);
            $version = $event['version'];
        }

        return $event;
    }
}

// İstifadə
$pipeline = new EventUpcasterPipeline();
$pipeline->register(1, fn($e) => array_merge($e, ['version' => 2, 'currency' => 'USD']));
$pipeline->register(2, fn($e) => array_merge($e, ['version' => 3, 'metadata' => []]));

$oldEvent = ['version' => 1, 'orderId' => '123', 'amount' => 99.99];
$upcastedEvent = $pipeline->upcast($oldEvent);
// → version=3, currency=USD, metadata=[]
```

---

## İntervyu Sualları

- Event schema dəyişikliyi zamanı "backward compatible" nə deməkdir?
- Schema evolution üçün hansı strategiyaları bilirsiniz?
- Event Sourcing-də köhnə event-lər schema dəyişdikdə nə olur?
- Schema Registry nə üçün istifadə edilir?
- Protobuf-da field silmək niyə problemlidir?
- Rolling deployment zamanı producer v2, consumer v1 işləyirsə nə baş verər?
