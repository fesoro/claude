# Shared Kernel (Lead ⭐⭐⭐⭐)

## İcmal

Shared Kernel — DDD Bounded Context inteqrasiya pattern-lərindən biridir. İki (və ya daha çox) BC, müəyyən kod bazasını birgə paylaşır və birgə idarə edir. Paylaşılan bu hissə — shared kernel — hər iki tərəfin razılaşması olmadan dəyişdirilə bilməz.

```
Bounded Context A ──┐
                    ├──► Shared Kernel (paylaşılan kod)
Bounded Context B ──┘

Shared Kernel məzmunu (tipik):
  - Paylaşılan Value Objects (Money, CustomerId, Email)
  - Paylaşılan Domain Events kontrakt-ları
  - Paylaşılan Interface-lər
  - Utility class-lar (nadir hallarda)

Nümunə:
  OrderingContext:  Order, OrderItem, CustomerId (paylaşır)
  BillingContext:   Invoice, Payment, CustomerId (paylaşır)

  CustomerId hər iki Context-də var → Shared Kernel-a çıxar.
```

## Niyə Vacibdir

BC-lər arası ən sadə paylaşma mexanizmidir. Ayrı library, versioning, API contract gerekmир — kod sadəcə paylaşılır. Lakin bu sadəlik eyni zamanda onun ən böyük riskidir: bir tərəfin dəyişikliyi digərini blokla bilər.

## Əsas Anlayışlar

**Uyğun ssenarilər:**
- İki BC həqiqətən eyni konsepti paylaşır (məs: `CustomerId` hər ikisinə lazımdır)
- Hər iki team birgə dəyişikliyi idarə etməyə razıdır
- Paylaşılan kod stabil, az dəyişir
- Monorepo strukturu; ayrı deploy yoxdur

**Uyğun olmayan ssenarilər:**
- Servislar ayrı deployment-dadır — versioning problemi yaranır
- Team-lər müstəqil işləmək istəyir
- Paylaşılan kod tez-tez dəyişir
- "Shared Kernel" lazımsız coupling yaradır

**Əsas şərt:** SK dəyişdirilməzdən əvvəl hər iki team razılaşmalıdır.

**Alternativlər:**
- **Customer/Supplier**: BC A (downstream) → BC B (upstream) API-sından istifadə edir. Paylaşılan kod yoxdur.
- **Conformist**: BC A, BC B-nin modelini olduğu kimi qəbul edir. Adapter yazılmır.
- **ACL**: BC A, BC B-nin modelini öz dilinə translasiya edir. Güclü izolyasiya.
- **Published Language**: Hər iki BC razılaşdırılmış format istifadə edir (JSON schema, Protobuf). Heç bir kod paylaşılmır, yalnız kontrakt.

## Praktik Baxış

**Real istifadə:**
- Monorepo-da `shared-kernel` Composer package: `Money`, `CustomerId`, `Email`, `DomainEvent` base class
- Bir komanda idarə etdiyi iki BC (Order + Billing) arasında paylaşılan value objects

**Trade-off-lar:**
- Kod dublikatı yoxdur; hər iki tərəf eyni validasyonu işlədir
- Lakin: bir tərəfin dəyişikliyi digərini bloklayır; versioning mürəkkəbdir; ownership qeyri-müəyyən

**İstifadə etməmək:**
- Müxtəlif komandalar idarə etdikdə (coordination overhead yüksəkdir)
- Microservice-lər ayrı deploy olduqda (library versioning problemi)
- SK tez-tez dəyişəndə (hər dəyişiklik hər iki tərəfi test etməyi tələb edir)

**Common mistakes:**
- SK-a tədricən hər şeyi əlavə etmək — böyüyür, effective monolit olur
- Ownership-i müəyyən etməmək — "bu kodu kim dəyişə bilər?" sualına cavab yoxdur
- Versioning olmadan microservice-lərə SK library dağıtmaq

**Anti-Pattern Nə Zaman Olur?**

- **SK-nı team-lərin dump ground-una çevirmək** — "bu kod hər yerə lazımdır, SK-ya əlavə edək" mentallığı. Zamanla SK hər şeyi içərir: utility-lər, helper-lər, business logic-lər. Artıq paylaşılan "kernel" deyil, paylaşılan "everything" — bu monolit deyil, daha da pisdir. SK yalnız həqiqətən hər iki BC-nin domain dili olan konseptləri içərməlidir: `Money`, `CustomerId`, `Email` — bunlar domain primitiv-ləridir. `OrderUtils`, `CustomerHelper` bunlar SK-ya girmər.
- **SK dəyişikliyinin hər iki tərəfi bloklayması** — Order team `Money`-ə yeni metod əlavə etmək istəyir, lakin Billing team-in test suite-i bitməyib, deploy edilə bilmir. Hər kiçik SK dəyişikliyi iki komandanın koordinasiyasını tələb edir — bu bottleneck-dir. SK-nı kiçik, stabil saxlayın; tez dəyişən kod SK-da olmamalıdır.
- **Microservice-lərdə versionsuz SK library** — Service A `shared-kernel:1.2`, Service B `shared-kernel:1.1` — uyğunsuzluq. Microservice-lərdə SK library semantic versioning ilə dağıdılmalı, hər BC öz versiyasına upgrade qərarı verməlidir.
- **SK-dan Published Language əvəzinə istifadə** — SK kod paylaşmasıdır. Böyük ekosistem üçün JSON Schema, Protobuf, AsyncAPI — yalnız kontrakt paylaşmaq daha yaxşıdır. SK yalnız çox sıx əlaqəli, eyni komanda tərəfindən idarə olunan BC-lər üçün.

## Nümunələr

### Ümumi Nümunə

E-commerce platformasında `CustomerId` həm Ordering BC, həm Billing BC tərəfindən istifadə olunur. İkisi eyni format, eyni validasiya tələb edir. Kod dublikatı yerinə `shared-kernel` Composer package yaradılır — hər iki BC bu package-i import edir.

### PHP/Laravel Nümunəsi

**Shared Kernel Composer Package:**

```php
// packages/shared-kernel/src/ValueObject/CustomerId.php
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

    public function equals(self $other): bool { return $this->value === $other->value; }
    public function toString(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}

// packages/shared-kernel/src/ValueObject/Money.php
namespace Company\SharedKernel\ValueObject;

final class Money
{
    public function __construct(
        private readonly int      $amount,   // qəpik cinsindən
        private readonly Currency $currency
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Məbləğ mənfi ola bilməz');
        }
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new \DomainException('Valyutalar eyni deyil');
        }
    }

    public function getAmount(): int { return $this->amount; }
    public function getCurrency(): Currency { return $this->currency; }
}

// packages/shared-kernel/src/Event/DomainEvent.php
namespace Company\SharedKernel\Event;

abstract class DomainEvent
{
    public readonly string $eventId;
    public readonly \DateTimeImmutable $occurredOn;

    public function __construct()
    {
        $this->eventId    = uniqid('evt_', true);
        $this->occurredOn = new \DateTimeImmutable();
    }

    abstract public function eventName(): string;
}
```

**Ordering BC-də istifadə:**

```php
// OrderingBC — shared-kernel-dən import
use Company\SharedKernel\ValueObject\CustomerId;
use Company\SharedKernel\ValueObject\Money;

class Order
{
    public function __construct(
        private readonly OrderId    $id,
        private readonly CustomerId $customerId, // Shared Kernel-dan ✅
        private Money               $total,       // Shared Kernel-dan ✅
    ) {}
}
```

**Billing BC-də eyni class:**

```php
// BillingBC — eyni shared-kernel-dən import
use Company\SharedKernel\ValueObject\CustomerId;
use Company\SharedKernel\ValueObject\Money;

class Invoice
{
    public function __construct(
        private readonly InvoiceId  $id,
        private readonly CustomerId $customerId, // Eyni Shared Kernel sinfi ✅
        private Money               $amount,      // Eyni Shared Kernel sinfi ✅
    ) {}
}
```

**Composer setup:**

```json
{
    "name": "company/shared-kernel",
    "description": "Shared domain primitives for company BC-lər",
    "autoload": {
        "psr-4": {
            "Company\\SharedKernel\\": "src/"
        }
    },
    "require": {}
}
```

**Monorepo-da hər BC-nin composer.json-u:**

```json
{
    "require": {
        "company/shared-kernel": "^1.0"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../../packages/shared-kernel"
        }
    ]
}
```

**SK ölçüsünü kiçik saxlamaq — rule:**

```php
// SK-da OLMALIDI:
// ✅ Domain primitive value objects (CustomerId, Money, Email)
// ✅ Shared domain event kontrakt-ları (interface-lər)
// ✅ Abstract base class-lar (DomainEvent, AggregateRoot)

// SK-da OLMAMALIDIR:
// ❌ Business logic (PricingPolicy, DiscountCalculator)
// ❌ Specific entity-lər (Customer, Order — bunlar BC-yə aiddir)
// ❌ Infrastructure (Repository interface-lər, gateway-lər)
// ❌ Utility helper-lər (bunlar package-ə çevrilə bilər amma SK deyil)
```

**SK dəyişikliyi prosesi (governance):**

```
SK dəyişikliyi etmək istəyirsən?

1. PR aç → SK repo-da
2. Hər iki team review etməlidir (mandatory)
3. Hər iki BC-nin CI-ı keçməlidir
4. YALNIZ hər iki team razılaşdıqdan sonra merge
5. Hər iki BC-ni eyni sprint-də yenilə

Bu proses ağır görünür? Bu SK-ın xərcidir.
Əgər çox tez-tez dəyişirsə → SK yanlış yerdədir.
```

## Praktik Tapşırıqlar

1. **SK audit** — mövcud layihənizdə iki BC arasında dublikat kodu tapın; hansı BC-lərin həqiqətən eyni konsepti paylaşdığını müəyyən edin; SK candidate-ları siyahısına alın.
2. **Minimal SK package** — yalnız `CustomerId`, `Money`, `Email` VO-larından ibarət `shared-kernel` Composer package yaradın; hər ikisi onu import etsin; BC-yə spesifik heç nə daxil etməyin.
3. **SK governance** — SK dəyişikliyi üçün process yazın: PR template, mandatory review, CI requirements; team ilə razılaşın.
4. **SK vs Duplication qərarı** — mövcud 3 paylaşılan concept götürün; hər biri üçün "SK-da olmalıdırmı, yoxsa hər BC öz versiyasına sahib olmalıdırmı?" qərarını arqumentlərlə verin.

## Əlaqəli Mövzular

- [DDD Overview](01-ddd.md) — strategic DDD tam mənzərəsi
- [Bounded Context](06-ddd-bounded-context.md) — BC inteqrasiya pattern-ləri
- [Value Objects](02-value-objects.md) — SK-da ən çox paylaşılan tip
- [ACL Pattern](../integration/08-anti-corruption-layer.md) — SK alternativinə — güclü izolyasiya
- [Modular Monolith](../architecture/08-modular-monolith.md) — monorepo-da SK istifadəsi
