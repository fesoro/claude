# DDD Tactical Patterns (Lead ⭐⭐⭐⭐)

## İcmal

Domain-Driven Design (DDD) tactical pattern-ləri — Entity, Value Object, Aggregate, Domain Service, Domain Event, Repository — domain məntiqini kodda aydın modelləşdirməyə xidmət edir. Bu pattern-lər Laravel layihəsinin framework ilə dolu code-unu real business qaydasını əks etdirən domain modelə dönüşdürür.

## Niyə Vacibdir

Laravel-in Eloquent modeli güclüdür, lakin tez-tez anemic domain model-ə səbəb olur: model yalnız getters/setters, bütün logic controller-lərdə və ya service-lərdə. Bu, business rule-ların dağınıq olmasına, bir rule-un bir neçə yerdə təkrarlanmasına, dəyişikliklərin gözlənilməz yerləri pozmasına yol açır. DDD tactical pattern-ləri business logic-i doğru yerə — domain-ə — çəkir.

## Əsas Anlayışlar

- **Entity**: unikal identifikator (ID) ilə fərqləndirilir; mutable ola bilər; eyni attribute-lu iki entity fərqlidir (iki eyni adlı istifadəçi fərqli insandır)
- **Value Object**: dəyərlərinin məcmusu ilə fərqləndirilir; identity-si yoxdur; immutable-dır; `Money(100, 'USD')` = `Money(100, 'USD')` eynidir
- **Aggregate**: consistency boundary — bir neçə entity/VO-dan ibarət bütöv qrup; yalnız Aggregate Root vasitəsilə əldə edilir; invariant-lar daxilindən qorunur
- **Aggregate Root**: aggregate-in xarici dünyaya açılan tək giriş nöqtəsi; bütün dəyişikliklər root üzərindən keçir
- **Domain Service**: nə entity-yə, nə VO-ya aid olmayan business logic (birdən çox aggregate ilə işləyən); stateless-dir
- **Domain Event**: domain-də baş vermiş bir fakt; immutable; keçmiş zaman ilə adlandırılır (`OrderPlaced`, `PaymentReceived`, `UserRegistered`)
- **Repository**: aggregate-i persist etmək/əldə etmək üçün interface; data access layer deyil — domain abstraction-dır

**Pattern-lər arasında əlaqə:**
```
Application Service
    → Repository (entity yüklə)
    → Domain Service (business qayda yoxla)
    → Entity/Aggregate (davranış icra et)
    → Repository (save et)
    → Event Bus (domain events dispatch et)
```

## Praktik Baxış

**Real istifadə:** e-commerce order management, subscription billing, inventory tracking, financial transactions — business rule-ların mürəkkəb olduğu hər yer

**Trade-off-lar:**
- Business rule-lar bir yerdə, test olunabilir, change-e izole
- Lakin: daha çox class, daha uzun setup, tez dəyişən startup domain-ləri üçün ağırdır

**İstifadə etməmək:**
- Sadə CRUD (admin panelləri, settings); domain logic çox azdırsa; kiçik team üçün cognitive overhead çoxdur; exploration mərhələsindəki MVP-lər

**Common mistakes:**
1. **Anemic domain model**: entity-lər yalnız getter/setter, bütün logic service-lərdə — DDD-nin ən çox görülən anti-pattern-i
2. **Value Object-ı mutable etmək**: `$money->amount = 200` — VO immutable olmalıdır; yeni instance qaytarın
3. **Aggregate-i çox böyük etmək**: Order + OrderItems + Customer + Product hamısını bir aggregate-ə qoymaq — transaction bottleneck yaranır
4. **Domain-ə Laravel import etmək**: `use Illuminate\Database\Eloquent\Model` domain entity-sinin içindəsə, domain artıq pure deyil

**Anti-Pattern Nə Zaman Olur?**

- **Tactical patterns strategic context olmadan** — Aggregate, Repository, Domain Event yazırsınız, lakin Bounded Context, Ubiquitous Language, Context Map yoxdur. "DDD without the D" — design olmadan sadəcə pattern-lər. Strategic DDD olmadan tactical tools kontekstsiz qalır; eyni termin fərqli yerlər fərqli mənada işlənir, confusion artır.
- **Domain Service-ə repository inject etmək** — `PricingService`-in daxilindən `ProductRepository` çağırmaq. Domain Service infrastructure bilməməlidir — stateless, pure domain logic. Repository-i Application Service inject etməlidir.
- **Hər şeyi Domain Service etmək** — `Order::calculateTotal()` entity-nin öz metodudur. `OrderTotalCalculator` adlı service yaratmaq anemic domain model-dir. Entity öz məntiqini özü daşımalıdır.
- **Application Service-ə business logic qoymaq** — `if ($order->total > 10000) throw ...` Application Service içindədir. Bu business rule domain-ə aiddir, aggregate içindədir.

## Nümunələr

### Ümumi Nümunə

Bir sifariş (Order) düşünün. OrderItem-lər sifarişin hissəsidir, müstəqil mövcud olmur — aggregate-dir. Order Aggregate Root-dur: item əlavə etmək, ləğv etmək yalnız Order üzərindən edilir. Qiymət `Money` value object-dir — 100 AZN immutable məbləğdir, 100 USD ilə fərqlidir. "Sifarişi place et" business məntiqi `order.place()` metodundadır — service-də deyil.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Domain\Order;

// ─────────────────────────────────────────────
// VALUE OBJECTS — immutable, equality by value
// ─────────────────────────────────────────────

final class Money
{
    public function __construct(
        private readonly int    $amount,   // cents
        private readonly string $currency, // ISO 4217
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException("Amount cannot be negative");
        }
    }

    public function getAmount(): int    { return $this->amount; }
    public function getCurrency(): string { return $this->currency; }

    public function add(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return number_format($this->amount / 100, 2) . ' ' . $this->currency;
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \DomainException("Currency mismatch: {$this->currency} vs {$other->currency}");
        }
    }
}

final class OrderId
{
    public function __construct(private readonly string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException("OrderId cannot be empty");
        }
    }

    public static function generate(): self
    {
        return new self((string) \Ramsey\Uuid\Uuid::uuid4());
    }

    public function equals(self $other): bool { return $this->value === $other->value; }
    public function __toString(): string { return $this->value; }
}

// ─────────────────────────────────────────────
// ENTITIES within Aggregate
// ─────────────────────────────────────────────

class OrderItem
{
    public function __construct(
        private readonly string $productId,
        private readonly string $productName, // snapshot — product dəyişsə order dəyişməsin
        private readonly Money  $unitPrice,
        private int             $quantity,
    ) {
        if ($quantity <= 0) {
            throw new \DomainException("Quantity must be positive");
        }
    }

    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function getProductId(): string { return $this->productId; }
    public function getQuantity(): int { return $this->quantity; }
}

// ─────────────────────────────────────────────
// AGGREGATE ROOT — consistency boundary
// ─────────────────────────────────────────────

class Order
{
    private array $items        = [];
    private array $domainEvents = [];
    private \DateTimeImmutable $createdAt;

    private function __construct(
        private readonly OrderId $id,
        private readonly string  $customerId,
        private OrderStatus      $status,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(string $customerId): self
    {
        $order = new self(OrderId::generate(), $customerId, OrderStatus::Draft);
        $order->recordEvent(new OrderDraftCreated($order->id, $customerId));
        return $order;
    }

    public function addItem(string $productId, string $productName, Money $unitPrice, int $quantity): void
    {
        $this->ensureStatus(OrderStatus::Draft, 'Cannot add items to non-draft order');
        $this->items[] = new OrderItem($productId, $productName, $unitPrice, $quantity);
    }

    public function place(): void
    {
        $this->ensureStatus(OrderStatus::Draft, 'Only draft orders can be placed');

        if (empty($this->items)) {
            throw new \DomainException("Cannot place empty order");
        }

        $this->status = OrderStatus::Pending;
        $this->recordEvent(new OrderPlaced($this->id, $this->customerId, $this->calculateTotal()));
    }

    public function cancel(string $reason): void
    {
        if (!in_array($this->status, [OrderStatus::Draft, OrderStatus::Pending], true)) {
            throw new \DomainException("Order cannot be cancelled in status: {$this->status->value}");
        }
        $this->status = OrderStatus::Cancelled;
        $this->recordEvent(new OrderCancelled($this->id, $reason));
    }

    public function calculateTotal(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $item) => $carry->add($item->subtotal()),
            new Money(0, 'AZN'),
        );
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function ensureStatus(OrderStatus $required, string $message): void
    {
        if ($this->status !== $required) {
            throw new \DomainException($message);
        }
    }

    public function getId(): OrderId { return $this->id; }
    public function getStatus(): OrderStatus { return $this->status; }
    public function getItems(): array { return $this->items; }
}

enum OrderStatus: string
{
    case Draft     = 'draft';
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
```

**Repository Interface (Domain Port):**

```php
// Domain interface — Eloquent bilmir
interface OrderRepository
{
    public function findById(OrderId $id): ?Order;
    public function findByCustomer(string $customerId): array;
    public function save(Order $order): void;
    public function nextIdentity(): OrderId;
}

// Infrastructure adapter — Eloquent ilə
class EloquentOrderRepository implements OrderRepository
{
    public function save(Order $order): void
    {
        $model = OrderModel::updateOrCreate(
            ['id' => (string) $order->getId()],
            [
                'customer_id' => $order->getCustomerId(),
                'status'      => $order->getStatus()->value,
                'total'       => $order->calculateTotal()->getAmount(),
            ]
        );

        $model->items()->delete();
        foreach ($order->getItems() as $item) {
            $model->items()->create([/* ... */]);
        }

        foreach ($order->pullDomainEvents() as $event) {
            event($event);
        }
    }
}
```

**Domain Service — birdən çox aggregate ilə iş:**

```php
// Domain Service — nə Order-a, nə Customer-a aid deyil; hər ikisini bilir
// Infrastructure yoxdur — repository inject edilmir
class OrderPricingService
{
    public function calculateDiscountedTotal(Order $order, Customer $customer): Money
    {
        $total = $order->calculateTotal();

        if ($customer->isVip() && $total->getAmount() > 10000) {
            // 10% discount VIP customers üçün 100+ AZN sifarişdə
            return new Money(
                amount: (int) ($total->getAmount() * 0.9),
                currency: 'AZN'
            );
        }

        return $total;
    }
}
```

**Application Service — use case orkestrasiyası:**

```php
class PlaceOrderHandler
{
    public function __construct(
        private readonly OrderRepository    $orders,
        private readonly OrderPricingService $pricingService,
        private readonly EventBus            $eventBus,
    ) {}

    public function handle(PlaceOrderCommand $command): void
    {
        DB::transaction(function () use ($command) {
            $order = $this->orders->findById(new OrderId($command->orderId));

            // Domain service çağır (business logic)
            // Application Service özü business logic yazmır

            $order->place(); // Domain entity öz invariant-larını qoruyur

            $this->orders->save($order);

            $events = $order->pullDomainEvents();
        });

        // TX-dan sonra event dispatch
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
```

## Praktik Tapşırıqlar

1. Mövcud `Order` Eloquent model-ini götürün; Logic-i (məs: status validation, total hesablama) domain entity-yə köçürün; Eloquent model-i yalnız persistence üçün saxlayın (thin model)
2. `Money`, `Email`, `PhoneNumber` value object-ları yaradın; immutability + equality testlərini yazın; Eloquent cast-ə inteqrasiya edin
3. `Product` aggregate yaradın: `ProductId`, `Price` (VO), `StockQuantity` (VO); `reserve(int $qty)` + `release(int $qty)` metodları; invariant: stock mənfi ola bilməz; business rule test edin
4. Domain event flow-u tam qurun: `Order::place()` → `OrderPlaced` event → `ReserveInventoryListener` + `SendConfirmationListener`; persistence sonrası event dispatch edilsin

## Əlaqəli Mövzular

- [DDD Overview](01-ddd.md) — strategic + tactical DDD tam mənzərəsi
- [Value Objects](02-value-objects.md) — immutable building blocks
- [Aggregates](04-ddd-aggregates.md) — consistency boundary dərin izah
- [Domain Events](05-ddd-domain-events.md) — event flow və dispatch
- [Domain Service vs App Service](08-domain-service-vs-app-service.md) — ayrım qaydaları
- [CQRS](../integration/01-cqrs.md) — command handler DDD aggregate-ləri ilə işləyir
- [Event Sourcing](../integration/02-event-sourcing.md) — domain events-i event store-a yazır
- [Repository Pattern](../laravel/01-repository-pattern.md) — DDD repository-nin Laravel implementasiyası
- [Hexagonal Architecture](../architecture/05-hexagonal-architecture.md) — domain core, port/adapter-lərlə infrastrukturdan ayrılır
- [Specification](../laravel/04-specification.md) — business rule-ları specification kimi modelləmək
