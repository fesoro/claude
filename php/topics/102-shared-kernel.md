# Shared Kernel (DDD) (Senior)

## Mündəricat
1. [Shared Kernel nədir?](#shared-kernel-nədir)
2. [Nə zaman istifadə edilir?](#nə-zaman-istifadə-edilir)
3. [Riskləri](#riskləri)
4. [Alternativlər](#alternativlər)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Shared Kernel nədir?

```
DDD Bounded Context inteqrasiya pattern-lərindən biri.
İki BC paylaşılan kod bazasına malikdir.

Bounded Context A ──┐
                    ├──► Shared Kernel (paylaşılan kod)
Bounded Context B ──┘

Shared Kernel məzmunu:
  - Paylaşılan Value Objects (Money, CustomerId, Email)
  - Paylaşılan Domain Events
  - Paylaşılan Interfaces
  - Utility/helper siniflər

Nümunə:
  OrderingContext:  Order, OrderItem, CustomerId (paylaşır)
  BillingContext:   Invoice, Payment, CustomerId (paylaşır)
  
  CustomerId hər iki Context-də var → Shared Kernel-a çıxar.
```

---

## Nə zaman istifadə edilir?

```
Uyğun:
  ✓ İki BC həqiqətən eyni konsepti paylaşır
  ✓ Hər iki team birgə dəyişikliyi idarə etməyə razıdır
  ✓ Paylaşılan kod stabil, az dəyişir
  ✓ Monorepo strukturu

Uyğun deyil:
  ✗ Servislar ayrı deployment-dadır (versioning problemi)
  ✗ Team-lər müstəqil işləmək istəyir
  ✗ Paylaşılan kod tez-tez dəyişir
  ✗ "Shared Kernel" lazımsız coupling yaradır

Əsas şərt:
  Shared Kernel dəyişdirilməzdən əvvəl hər iki team razılaşmalıdır.
  Biri dəyişirsə digəri sınmır.
```

---

## Riskləri

```
1. Coupling artır:
   BC A dəyişiklik istəyir → BC B-ni də təsir edir
   "Shared" olan hər şey dependency-dir

2. Ownership qeyri-müəyyənliyi:
   "Bu kodu kim dəyişə bilər?"
   "Bu dəyişiklik B-ni sındırarmı?"

3. Böyümə tendensiyası:
   "Bunu da shared edək" → shared kernel böyüyür
   → Effective monolit

4. Versioning:
   Microservice-lərdə shared library versioning problemi:
   Service A: shared-kernel v1.2
   Service B: shared-kernel v1.1
   → Incompatibility!
```

---

## Alternativlər

```
Customer/Supplier:
  BC A (downstream) → BC B (upstream) API-sından istifadə edir.
  B, A-nın ehtiyaclarını nəzərə alır.
  Paylaşılan kod yoxdur.

Conformist:
  BC A, BC B-nin modelini olduğu kimi qəbul edir.
  Adapter yazılmır.

Anti-Corruption Layer (ACL):
  BC A, BC B-nin modelini öz dilinə translasiya edir.
  Ən güclü izolyasiya (topik 76).

Published Language:
  Hər iki BC razılaşdırılmış format istifadə edir (JSON schema, Protobuf).
  Heç bir kod paylaşılmır, yalnız kontrakt.
```

---

## PHP İmplementasiyası

```php
<?php
// shared-kernel Composer package
// composer.json:
// {
//   "name": "company/shared-kernel",
//   "autoload": {"psr-4": {"Company\\SharedKernel\\": "src/"}}
// }

// SharedKernel: CustomerId Value Object
namespace Company\SharedKernel\ValueObject;

final class CustomerId
{
    private function __construct(
        private readonly string $value,
    ) {}

    public static function fromString(string $value): self
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}/i', $value)) {
            throw new \InvalidArgumentException("Invalid CustomerId: {$value}");
        }
        return new self($value);
    }

    public static function generate(): self
    {
        return new self(sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff),
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        ));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}

// SharedKernel: Paylaşılan Domain Event
namespace Company\SharedKernel\Event;

abstract class DomainEvent
{
    private readonly string $eventId;
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct() {
        $this->eventId   = uniqid('evt_', true);
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function eventId(): string { return $this->eventId; }
    public function occurredOn(): \DateTimeImmutable { return $this->occurredOn; }
    abstract public function eventName(): string;
}

// OrderingContext-də istifadə
use Company\SharedKernel\ValueObject\CustomerId;

class Order
{
    public function __construct(
        private CustomerId $customerId, // Shared Kernel-dan
        // ...
    ) {}
}

// BillingContext-də eyni CustomerId istifadəsi
class Invoice
{
    public function __construct(
        private CustomerId $customerId, // Eyni Shared Kernel sinfi
        // ...
    ) {}
}
```

---

## İntervyu Sualları

- Shared Kernel hansı DDD inteqrasiya pattern-lərdən biridir?
- Shared Kernel-ın ən böyük riski nədir?
- Microservice-lərdə Shared Kernel versioning problemini necə həll edərdiniz?
- Customer/Supplier vs Shared Kernel — fərqi nədir?
- "Shared Kernel böyüməməlidir" prinsipini necə tətbiq edərdiniz?
