# Domain-Driven Design (DDD) (Senior)

## Mündəricat
1. [DDD nədir?](#ddd-nədir)
2. [Strategic Design](#strategic-design)
3. [Tactical Design](#tactical-design)
4. [Layered Architecture](#layered-architecture)
5. [Entity](#entity)
6. [Value Object](#value-object)
7. [Aggregate və Aggregate Root](#aggregate-və-aggregate-root)
8. [Domain Events](#domain-events)
9. [Repository Pattern](#repository-pattern)
10. [Domain Service vs Application Service](#domain-service-vs-application-service)
11. [Factory Pattern](#factory-pattern)
12. [Specification Pattern](#specification-pattern)
13. [Anti-corruption Layer](#anti-corruption-layer)
14. [Laravel-də DDD Tətbiqi](#laraveldə-ddd-tətbiqi)
15. [Real-World E-commerce Nümunəsi](#real-world-e-commerce-nümunəsi)
16. [DDD və CQRS](#ddd-və-cqrs)
17. [Üstünlüklər və Mənfi Cəhətlər](#üstünlüklər-və-mənfi-cəhətlər)
18. [İntervyu Sualları](#intervyu-sualları)

---

## DDD nədir?

Domain-Driven Design (DDD) — Eric Evans tərəfindən 2003-cü ildə təqdim edilmiş software design yanaşmasıdır. DDD-nin əsas ideyası odur ki, **proqram təminatının strukturu business domain-i əks etdirməlidir**. Texniki qərarlar domain biliyinə əsaslanmalıdır.

**DDD-nin əsas prinsipləri:**
1. **Domain-ə fokuslanma** — texnologiya deyil, business logic əsasdır
2. **Ubiquitous Language** — developer və business eyni dildə danışmalıdır
3. **Model-Driven Design** — domain modeli kodun əsasıdır
4. **Bounded Context** — mürəkkəb domain-i kiçik hissələrə bölmək

**Nə vaxt DDD istifadə etməli?**
- Mürəkkəb business logic olduqda
- Domain ekspertləri ilə sıx əməkdaşlıq olduqda
- Uzunmüddətli layihələrdə
- Business qaydaları tez-tez dəyişdikdə

**Nə vaxt DDD lazım deyil?**
- Sadə CRUD tətbiqlərində
- Prototiplərdə
- Kiçik layihələrdə
- Business logic az olduqda

---

## Strategic Design

### Bounded Context

Bounded Context — domain-in müəyyən bir kontekstdə sərhədlənmiş modelidir. Eyni termin fərqli kontekstlərdə fərqli mənaya malik ola bilər.

```
E-commerce domain-i üçün Bounded Context-lər:

┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│   Catalog BC     │  │   Ordering BC   │  │   Shipping BC   │
│                  │  │                 │  │                 │
│ Product (ad,     │  │ Order           │  │ Shipment        │
│   qiymət, şəkil)│  │ OrderItem       │  │ Package         │
│ Category         │  │ Customer        │  │ Address         │
│ Brand            │  │ Payment         │  │ TrackingNumber  │
│ Review           │  │ Discount        │  │ Carrier         │
└─────────────────┘  └─────────────────┘  └─────────────────┘

┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│  Inventory BC    │  │   Payment BC    │  │  Identity BC    │
│                  │  │                 │  │                 │
│ StockItem        │  │ Transaction     │  │ User            │
│ Warehouse        │  │ PaymentMethod   │  │ Role            │
│ StockMovement    │  │ Refund          │  │ Permission      │
│ Reservation      │  │ Invoice         │  │ Authentication  │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

**"Product" hər kontekstdə fərqlidir:**
- **Catalog BC**: ad, təsvir, şəkillər, qiymət, reviews
- **Ordering BC**: product_id, ad, qiymət (snapshot), quantity
- **Inventory BC**: SKU, stock miqdarı, warehouse yeri
- **Shipping BC**: çəki, ölçülər, fragile/not

### Ubiquitous Language

Domain ekspertləri və developerlər arasında ortaq dil. Kodda da, ünsiyyətdə də eyni terminlər istifadə olunur.

*Domain ekspertləri və developerlər arasında ortaq dil. Kodda da, ünsiy üçün kod nümunəsi:*
```php
// YANLIŞ - texniki dil, business anlamı yoxdur
class OrderManager
{
    public function processItem(array $data): void
    {
        $record = DB::table('orders')->insert($data);
        $this->updateStatus($record, 2); // 2 nə deməkdir?
    }
}

// DOĞRU - Ubiquitous Language istifadə
class Order
{
    public function place(Customer $customer, array $items): void
    {
        // "Order place etmək" - business termini
    }

    public function confirm(): void
    {
        // "Order təsdiqləmək" - business termini
    }

    public function ship(TrackingNumber $trackingNumber): void
    {
        // "Order göndərmək" - business termini
    }

    public function cancel(CancellationReason $reason): void
    {
        // "Order ləğv etmək" - business termini
    }

    public function refund(Money $amount): void
    {
        // "Pulu qaytarmaq" - business termini
    }
}
```

### Context Mapping

Bounded Context-lər arasındakı əlaqələrin xəritəsi.

```
Əlaqə növləri:
- Shared Kernel: İki BC ortaq model paylaşır
- Customer/Supplier: Bir BC digərinə data təmin edir
- Conformist: Bir BC tamamilə digərinə uyğunlaşır
- Anti-corruption Layer: Bir BC digərinin modelini öz modelinə çevirir
- Published Language: Standart format ilə ünsiyyət (API, Events)

Ordering BC ──[Customer/Supplier]──> Inventory BC
     │                                    │
     └──[Anti-corruption Layer]──> Payment BC
     │
     └──[Published Language (Events)]──> Shipping BC
     │
     └──[Conformist]──> Identity BC
```

---

## Tactical Design

Tactical Design — domain modelini kodda necə implementasiya etməyin konkret pattern-ləridir.

### Əsas building block-lar:
1. **Entity** — identity-si olan obyekt
2. **Value Object** — identity-si olmayan, dəyərinə görə müəyyən olunan obyekt
3. **Aggregate** — bir-birinə bağlı obyektlər qrupu
4. **Aggregate Root** — aggregate-in giriş nöqtəsi
5. **Domain Event** — domain-də baş vermiş hadisə
6. **Repository** — aggregate-ləri saxlama/oxuma
7. **Factory** — mürəkkəb obyektlərin yaradılması
8. **Domain Service** — heç bir entity-yə aid olmayan domain logic
9. **Specification** — business qaydalarını təmsil edən pattern

---

## Layered Architecture

```
┌─────────────────────────────────────────────┐
│           Presentation Layer                 │
│    (Controllers, API Resources, Views)       │
│    Request-ləri qəbul edir, Response verir   │
├─────────────────────────────────────────────┤
│           Application Layer                  │
│    (Application Services, Commands,          │
│     Queries, DTOs, Event Handlers)           │
│    Use case-ləri orkestrasiya edir           │
├─────────────────────────────────────────────┤
│             Domain Layer                     │
│    (Entities, Value Objects, Aggregates,     │
│     Domain Events, Repository Interfaces,    │
│     Domain Services, Specifications)         │
│    Business logic burada yaşayır             │
├─────────────────────────────────────────────┤
│          Infrastructure Layer                │
│    (Eloquent Repositories, External APIs,    │
│     Queue, Mail, Cache, File Storage)        │
│    Texniki implementasiyalar                 │
└─────────────────────────────────────────────┘

Asılılıq istiqaməti: Yuxarıdan aşağıya
Domain Layer heç nəyə asılı DEYİL (framework-dən belə)
```

*Domain Layer heç nəyə asılı DEYİL (framework-dən belə) üçün kod nümunəsi:*
```php
// Laravel-də folder strukturu

src/
├── Domain/                          # Domain Layer
│   ├── Order/
│   │   ├── Models/
│   │   │   ├── Order.php           # Aggregate Root (Entity)
│   │   │   ├── OrderItem.php       # Entity
│   │   │   └── OrderStatus.php     # Enum
│   │   ├── ValueObjects/
│   │   │   ├── OrderNumber.php
│   │   │   └── ShippingInfo.php
│   │   ├── Events/
│   │   │   ├── OrderPlaced.php
│   │   │   ├── OrderPaid.php
│   │   │   └── OrderCancelled.php
│   │   ├── Repositories/
│   │   │   └── OrderRepositoryInterface.php
│   │   ├── Services/
│   │   │   └── OrderPricingService.php
│   │   ├── Specifications/
│   │   │   ├── CancellableOrderSpec.php
│   │   │   └── RefundableOrderSpec.php
│   │   ├── Factories/
│   │   │   └── OrderFactory.php
│   │   └── Exceptions/
│   │       ├── OrderCannotBeCancelledException.php
│   │       └── InsufficientStockException.php
│   │
│   ├── Product/
│   │   ├── Models/
│   │   │   └── Product.php
│   │   ├── ValueObjects/
│   │   │   ├── Sku.php
│   │   │   └── ProductDimensions.php
│   │   └── Repositories/
│   │       └── ProductRepositoryInterface.php
│   │
│   └── Shared/
│       └── ValueObjects/
│           ├── Money.php
│           ├── Email.php
│           └── Address.php
│
├── Application/                     # Application Layer
│   ├── Order/
│   │   ├── Commands/
│   │   │   ├── PlaceOrderCommand.php
│   │   │   └── CancelOrderCommand.php
│   │   ├── Handlers/
│   │   │   ├── PlaceOrderHandler.php
│   │   │   └── CancelOrderHandler.php
│   │   ├── Queries/
│   │   │   ├── GetOrderQuery.php
│   │   │   └── ListOrdersQuery.php
│   │   ├── DTOs/
│   │   │   ├── PlaceOrderDTO.php
│   │   │   └── OrderDTO.php
│   │   └── EventHandlers/
│   │       ├── SendOrderConfirmationEmail.php
│   │       └── UpdateInventoryOnOrderPlaced.php
│   └── Product/
│       └── ...
│
├── Infrastructure/                  # Infrastructure Layer
│   ├── Persistence/
│   │   ├── EloquentOrderRepository.php
│   │   └── EloquentProductRepository.php
│   ├── Payment/
│   │   ├── StripePaymentGateway.php
│   │   └── PayPalPaymentGateway.php
│   ├── Mail/
│   │   └── OrderConfirmationMail.php
│   └── Queue/
│       └── AsyncEventDispatcher.php
│
└── Presentation/                    # Presentation Layer
    ├── Http/
    │   ├── Controllers/
    │   │   ├── OrderController.php
    │   │   └── ProductController.php
    │   ├── Requests/
    │   │   └── PlaceOrderRequest.php
    │   └── Resources/
    │       └── OrderResource.php
    └── Console/
        └── Commands/
            └── ProcessExpiredOrders.php
```

---

## Entity

Entity — unikal identity-si olan domain obyektidir. Eyni xüsusiyyətlərə malik iki entity fərqli ola bilər (çünki fərqli ID-ləri var).

*Entity — unikal identity-si olan domain obyektidir. Eyni xüsusiyyətlər üçün kod nümunəsi:*
```php
// Domain/Order/Models/Order.php
class Order
{
    private array $items = [];
    private array $domainEvents = [];

    public function __construct(
        private readonly OrderId $id,
        private readonly CustomerId $customerId,
        private OrderStatus $status,
        private Money $subtotal,
        private Money $taxAmount,
        private Money $discountAmount,
        private Money $total,
        private Address $shippingAddress,
        private ?string $notes,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $paidAt = null,
        private ?DateTimeImmutable $shippedAt = null,
        private ?DateTimeImmutable $cancelledAt = null,
    ) {}

    public function id(): OrderId
    {
        return $this->id;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function total(): Money
    {
        return $this->total;
    }

    // Business method-lar
    public function addItem(Product $product, int $quantity): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new DomainException('Yalnız draft sifarişə məhsul əlavə edilə bilər.');
        }

        $existingItem = $this->findItemByProduct($product->id());

        if ($existingItem) {
            $existingItem->increaseQuantity($quantity);
        } else {
            $this->items[] = new OrderItem(
                id: OrderItemId::generate(),
                orderId: $this->id,
                productId: $product->id(),
                productName: $product->name(),
                unitPrice: $product->price(),
                quantity: $quantity,
            );
        }

        $this->recalculateTotals();
    }

    public function removeItem(OrderItemId $itemId): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new DomainException('Yalnız draft sifarişdən məhsul silinə bilər.');
        }

        $this->items = array_filter(
            $this->items,
            fn (OrderItem $item) => !$item->id()->equals($itemId),
        );

        $this->recalculateTotals();
    }

    public function place(): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new DomainException('Yalnız draft sifariş yerləşdirilə bilər.');
        }

        if (empty($this->items)) {
            throw new DomainException('Boş sifariş yerləşdirilə bilməz.');
        }

        $this->status = OrderStatus::Placed;
        $this->recordEvent(new OrderPlaced(
            orderId: $this->id,
            customerId: $this->customerId,
            total: $this->total,
            itemCount: count($this->items),
            placedAt: new DateTimeImmutable(),
        ));
    }

    public function markAsPaid(string $transactionId): void
    {
        if ($this->status !== OrderStatus::Placed) {
            throw new DomainException('Yalnız yerləşdirilmiş sifariş ödənilə bilər.');
        }

        $this->status = OrderStatus::Paid;
        $this->paidAt = new DateTimeImmutable();

        $this->recordEvent(new OrderPaid(
            orderId: $this->id,
            transactionId: $transactionId,
            amount: $this->total,
            paidAt: $this->paidAt,
        ));
    }

    public function ship(TrackingNumber $trackingNumber): void
    {
        if ($this->status !== OrderStatus::Paid) {
            throw new DomainException('Yalnız ödənilmiş sifariş göndərilə bilər.');
        }

        $this->status = OrderStatus::Shipped;
        $this->shippedAt = new DateTimeImmutable();

        $this->recordEvent(new OrderShipped(
            orderId: $this->id,
            trackingNumber: $trackingNumber,
            shippedAt: $this->shippedAt,
        ));
    }

    public function cancel(CancellationReason $reason): void
    {
        $cancellableStatuses = [OrderStatus::Draft, OrderStatus::Placed, OrderStatus::Paid];

        if (!in_array($this->status, $cancellableStatuses)) {
            throw new OrderCannotBeCancelledException(
                "Status '{$this->status->value}' olan sifariş ləğv edilə bilməz."
            );
        }

        $previousStatus = $this->status;
        $this->status = OrderStatus::Cancelled;
        $this->cancelledAt = new DateTimeImmutable();

        $this->recordEvent(new OrderCancelled(
            orderId: $this->id,
            reason: $reason,
            previousStatus: $previousStatus,
            cancelledAt: $this->cancelledAt,
            requiresRefund: $previousStatus === OrderStatus::Paid,
        ));
    }

    // Aggregate daxili hesablama
    private function recalculateTotals(): void
    {
        $this->subtotal = Money::zero($this->total->currency);

        foreach ($this->items as $item) {
            $this->subtotal = $this->subtotal->add($item->lineTotal());
        }

        // Vergi hesabla (18% ƏDV)
        $this->taxAmount = $this->subtotal->percentage(18);
        $this->total = $this->subtotal->add($this->taxAmount)->subtract($this->discountAmount);
    }

    private function findItemByProduct(ProductId $productId): ?OrderItem
    {
        foreach ($this->items as $item) {
            if ($item->productId()->equals($productId)) {
                return $item;
            }
        }
        return null;
    }

    // Domain Events
    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // Identity equality
    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    public function items(): array
    {
        return $this->items;
    }
}

// OrderItem Entity
class OrderItem
{
    public function __construct(
        private readonly OrderItemId $id,
        private readonly OrderId $orderId,
        private readonly ProductId $productId,
        private readonly string $productName,
        private readonly Money $unitPrice,
        private int $quantity,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Miqdar ən azı 1 olmalıdır.');
        }
    }

    public function id(): OrderItemId { return $this->id; }
    public function productId(): ProductId { return $this->productId; }
    public function unitPrice(): Money { return $this->unitPrice; }
    public function quantity(): int { return $this->quantity; }

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function increaseQuantity(int $amount): void
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Artırma miqdarı ən azı 1 olmalıdır.');
        }
        $this->quantity += $amount;
    }

    public function decreaseQuantity(int $amount): void
    {
        if ($amount >= $this->quantity) {
            throw new InvalidArgumentException('Azaltma miqdarı cari miqdardan az olmalıdır.');
        }
        $this->quantity -= $amount;
    }
}
```

---

## Value Object

DDD kontekstində Value Object haqqında ətraflı məlumat `02-value-objects.md` faylında verilmişdir. Burada DDD-yə xas nümunələrə baxaq:

*DDD kontekstində Value Object haqqında ətraflı məlumat `02-value-objec üçün kod nümunəsi:*
```php
// Domain/Order/ValueObjects/OrderNumber.php
readonly class OrderNumber
{
    public readonly string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^ORD-\d{4}-\d{6}$/', $value)) {
            throw new InvalidArgumentException("Yanlış sifariş nömrəsi formatı: {$value}");
        }

        $this->value = $value;
    }

    public static function generate(): self
    {
        $year = date('Y');
        $sequence = str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        return new self("ORD-{$year}-{$sequence}");
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

// Domain/Shared/ValueObjects/OrderId.php
readonly class OrderId
{
    public function __construct(
        public string $value,
    ) {
        if (empty($value)) {
            throw new InvalidArgumentException('Order ID boş ola bilməz.');
        }
    }

    public static function generate(): self
    {
        return new self((string) Str::uuid());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

// Domain/Order/ValueObjects/TrackingNumber.php
readonly class TrackingNumber
{
    public function __construct(
        public string $value,
        public string $carrier,
    ) {
        if (empty($value)) {
            throw new InvalidArgumentException('Tracking nömrəsi boş ola bilməz.');
        }
    }

    public function trackingUrl(): string
    {
        return match($this->carrier) {
            'dhl' => "https://www.dhl.com/tracking/{$this->value}",
            'fedex' => "https://www.fedex.com/tracking/{$this->value}",
            'ups' => "https://www.ups.com/track?tracknum={$this->value}",
            default => '',
        };
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value && $this->carrier === $other->carrier;
    }
}
```

---

## Aggregate və Aggregate Root

**Aggregate** — bir-birinə bağlı entity və value object-lərin qrupudur. **Aggregate Root** — aggregate-in giriş nöqtəsidir. Bütün dəyişikliklər aggregate root vasitəsilə edilir.

**Aggregate qaydaları:**
1. Aggregate root-dan kənar entity-lərə birbaşa müraciət etmə
2. Aggregate daxilində consistency qorunmalıdır
3. Aggregate-lər arası istinad yalnız ID ilə olmalıdır
4. Bir transaction bir aggregate-i dəyişdirməlidir

*4. Bir transaction bir aggregate-i dəyişdirməlidir üçün kod nümunəsi:*
```php
// Order aggregate - Root: Order, Children: OrderItem

// YANLIŞ - birbaşa OrderItem-ə müraciət
$orderItem = OrderItem::find(5);
$orderItem->quantity = 10; // Aggregate root-u bypass edir!
$orderItem->save();        // Total yenilənməyəcək!

// DOĞRU - Aggregate Root vasitəsilə
$order = $orderRepository->findById($orderId);
$order->addItem($product, 10);       // Root vasitəsilə
$order->removeItem($orderItemId);     // Root vasitəsilə
$orderRepository->save($order);       // Bütün aggregate birlikdə save olunur

// Başqa bir aggregate nümunəsi: ShoppingCart
class ShoppingCart // Aggregate Root
{
    /** @var CartItem[] */
    private array $items = [];
    private array $domainEvents = [];

    public function __construct(
        private readonly CartId $id,
        private readonly CustomerId $customerId,
        private DateTimeImmutable $updatedAt,
    ) {}

    public function addItem(ProductId $productId, string $name, Money $price, int $quantity = 1): void
    {
        $existingItem = $this->findItem($productId);

        if ($existingItem) {
            $existingItem->updateQuantity($existingItem->quantity() + $quantity);
        } else {
            $this->items[] = new CartItem(
                cartItemId: CartItemId::generate(),
                productId: $productId,
                productName: $name,
                unitPrice: $price,
                quantity: $quantity,
            );
        }

        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new ItemAddedToCart(
            cartId: $this->id,
            productId: $productId,
            quantity: $quantity,
        ));
    }

    public function removeItem(ProductId $productId): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            fn (CartItem $item) => !$item->productId()->equals($productId),
        ));

        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateItemQuantity(ProductId $productId, int $newQuantity): void
    {
        if ($newQuantity < 1) {
            $this->removeItem($productId);
            return;
        }

        $item = $this->findItem($productId);

        if (!$item) {
            throw new DomainException('Məhsul səbətdə tapılmadı.');
        }

        $item->updateQuantity($newQuantity);
        $this->updatedAt = new DateTimeImmutable();
    }

    public function clear(): void
    {
        $this->items = [];
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new CartCleared($this->id));
    }

    public function subtotal(): Money
    {
        return array_reduce(
            $this->items,
            fn (Money $carry, CartItem $item) => $carry->add($item->lineTotal()),
            Money::zero(Currency::AZN()),
        );
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (CartItem $item) => $item->quantity(), $this->items));
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    private function findItem(ProductId $productId): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->productId()->equals($productId)) {
                return $item;
            }
        }
        return null;
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
```

---

## Domain Events

Domain Event — domain-də baş vermiş mühüm hadisəni təmsil edir. Past tense (keçmiş zaman) ilə adlandırılır.

*Domain Event — domain-də baş vermiş mühüm hadisəni təmsil edir. Past t üçün kod nümunəsi:*
```php
// Domain/Order/Events/OrderPlaced.php
readonly class OrderPlaced implements DomainEvent
{
    public function __construct(
        public OrderId $orderId,
        public CustomerId $customerId,
        public Money $total,
        public int $itemCount,
        public DateTimeImmutable $placedAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->placedAt;
    }
}

readonly class OrderPaid implements DomainEvent
{
    public function __construct(
        public OrderId $orderId,
        public string $transactionId,
        public Money $amount,
        public DateTimeImmutable $paidAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->paidAt;
    }
}

readonly class OrderCancelled implements DomainEvent
{
    public function __construct(
        public OrderId $orderId,
        public CancellationReason $reason,
        public OrderStatus $previousStatus,
        public DateTimeImmutable $cancelledAt,
        public bool $requiresRefund,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->cancelledAt;
    }
}

// Domain Event Interface
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}

// Application Layer - Event Handlers
// Application/Order/EventHandlers/SendOrderConfirmationEmail.php
class SendOrderConfirmationEmail
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly CustomerRepositoryInterface $customerRepo,
        private readonly MailerInterface $mailer,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $this->orderRepo->findById($event->orderId);
        $customer = $this->customerRepo->findById($event->customerId);

        $this->mailer->send(
            to: $customer->email(),
            template: 'order-confirmation',
            data: [
                'customer_name' => $customer->name(),
                'order_number' => $order->orderNumber(),
                'total' => $order->total()->format(),
                'items_count' => $event->itemCount,
            ],
        );
    }
}

// Application/Order/EventHandlers/ReserveInventory.php
class ReserveInventory
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly InventoryServiceInterface $inventoryService,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $this->orderRepo->findById($event->orderId);

        foreach ($order->items() as $item) {
            $this->inventoryService->reserve(
                productId: $item->productId(),
                quantity: $item->quantity(),
                orderId: $event->orderId,
            );
        }
    }
}

// Application/Order/EventHandlers/ProcessRefundOnCancellation.php
class ProcessRefundOnCancellation
{
    public function __construct(
        private readonly PaymentServiceInterface $paymentService,
        private readonly OrderRepositoryInterface $orderRepo,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        if (!$event->requiresRefund) {
            return;
        }

        $order = $this->orderRepo->findById($event->orderId);
        $this->paymentService->refund($order->transactionId(), $order->total());
    }
}

// Laravel-də Event Handler qeydiyyatı
// EventServiceProvider.php
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPlaced::class => [
            SendOrderConfirmationEmail::class,
            ReserveInventory::class,
            NotifyWarehouse::class,
        ],
        OrderPaid::class => [
            GenerateInvoice::class,
            UpdateSalesReport::class,
        ],
        OrderCancelled::class => [
            ProcessRefundOnCancellation::class,
            ReleaseInventoryReservation::class,
            NotifyCustomerOfCancellation::class,
        ],
    ];
}
```

---

## Repository Pattern

Repository — aggregate-ləri persistence-dən abstrakt etmək üçün istifadə olunur. Domain layer-də interface, infrastructure layer-də implementasiya.

*Repository — aggregate-ləri persistence-dən abstrakt etmək üçün istifa üçün kod nümunəsi:*
```php
// Domain/Order/Repositories/OrderRepositoryInterface.php (Domain Layer)
interface OrderRepositoryInterface
{
    public function findById(OrderId $id): Order;
    public function findByCustomerId(CustomerId $customerId): array;
    public function save(Order $order): void;
    public function delete(OrderId $id): void;
    public function nextId(): OrderId;
}

// Infrastructure/Persistence/EloquentOrderRepository.php (Infrastructure Layer)
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(OrderId $id): Order
    {
        $model = OrderModel::with('items')->findOrFail($id->value);

        return $this->toDomainEntity($model);
    }

    public function findByCustomerId(CustomerId $customerId): array
    {
        $models = OrderModel::with('items')
            ->where('customer_id', $customerId->value)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn ($model) => $this->toDomainEntity($model))->all();
    }

    public function save(Order $order): void
    {
        $model = OrderModel::updateOrCreate(
            ['id' => $order->id()->value],
            [
                'customer_id' => $order->customerId()->value,
                'status' => $order->status()->value,
                'subtotal' => $order->subtotal()->amount,
                'tax_amount' => $order->taxAmount()->amount,
                'discount_amount' => $order->discountAmount()->amount,
                'total' => $order->total()->amount,
                'currency' => $order->total()->currency->code,
                'shipping_address' => json_encode($order->shippingAddress()->toArray()),
                'notes' => $order->notes(),
                'paid_at' => $order->paidAt(),
                'shipped_at' => $order->shippedAt(),
                'cancelled_at' => $order->cancelledAt(),
            ],
        );

        // Items-ləri sync et
        $model->items()->delete();

        foreach ($order->items() as $item) {
            $model->items()->create([
                'id' => $item->id()->value,
                'product_id' => $item->productId()->value,
                'product_name' => $item->productName(),
                'unit_price' => $item->unitPrice()->amount,
                'quantity' => $item->quantity(),
            ]);
        }

        // Domain events-ləri dispatch et
        $events = $order->pullDomainEvents();
        foreach ($events as $event) {
            event($event);
        }
    }

    public function delete(OrderId $id): void
    {
        OrderModel::where('id', $id->value)->delete();
    }

    public function nextId(): OrderId
    {
        return OrderId::generate();
    }

    // Eloquent Model -> Domain Entity mapping
    private function toDomainEntity(OrderModel $model): Order
    {
        $currency = new Currency($model->currency);

        $order = new Order(
            id: new OrderId($model->id),
            customerId: new CustomerId($model->customer_id),
            status: OrderStatus::from($model->status),
            subtotal: new Money($model->subtotal, $currency),
            taxAmount: new Money($model->tax_amount, $currency),
            discountAmount: new Money($model->discount_amount, $currency),
            total: new Money($model->total, $currency),
            shippingAddress: Address::fromArray(json_decode($model->shipping_address, true)),
            notes: $model->notes,
            createdAt: new DateTimeImmutable($model->created_at),
            paidAt: $model->paid_at ? new DateTimeImmutable($model->paid_at) : null,
            shippedAt: $model->shipped_at ? new DateTimeImmutable($model->shipped_at) : null,
            cancelledAt: $model->cancelled_at ? new DateTimeImmutable($model->cancelled_at) : null,
        );

        // Items-ləri əlavə et
        foreach ($model->items as $itemModel) {
            $order->loadItem(new OrderItem(
                id: new OrderItemId($itemModel->id),
                orderId: new OrderId($model->id),
                productId: new ProductId($itemModel->product_id),
                productName: $itemModel->product_name,
                unitPrice: new Money($itemModel->unit_price, $currency),
                quantity: $itemModel->quantity,
            ));
        }

        return $order;
    }
}

// Service Provider-da binding
$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
```

---

## Domain Service vs Application Service

**Domain Service** — entity-lərə aid olmayan domain logic üçün. Domain layer-dədir.
**Application Service** — use case orkestrasiya üçün. Application layer-dədir.

***Application Service** — use case orkestrasiya üçün. Application laye üçün kod nümunəsi:*
```php
// Domain Service - domain logic (framework-dən asılı deyil)
class OrderPricingService
{
    public function calculateDiscount(Order $order, ?Coupon $coupon): Money
    {
        $discount = Money::zero($order->total()->currency);

        // Müştəri loyallıq endirimi
        if ($order->customerType() === CustomerType::VIP) {
            $discount = $discount->add($order->subtotal()->percentage(10));
        }

        // Kupon endirimi
        if ($coupon && $coupon->isValid()) {
            $couponDiscount = match($coupon->type()) {
                CouponType::Percentage => $order->subtotal()->percentage($coupon->value()),
                CouponType::FixedAmount => new Money($coupon->value(), $order->total()->currency),
            };
            $discount = $discount->add($couponDiscount);
        }

        // Həcm endirimi
        if ($order->itemCount() >= 10) {
            $discount = $discount->add($order->subtotal()->percentage(5));
        }

        // Endirim subtotal-dan çox ola bilməz
        if ($discount->greaterThan($order->subtotal())) {
            return $order->subtotal();
        }

        return $discount;
    }
}

// Application Service - use case orkestrasiya (framework ilə əlaqəli ola bilər)
class PlaceOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly ProductRepositoryInterface $productRepo,
        private readonly CouponRepositoryInterface $couponRepo,
        private readonly OrderPricingService $pricingService,
        private readonly OrderFactory $orderFactory,
    ) {}

    public function handle(PlaceOrderCommand $command): OrderId
    {
        // 1. Products-ları yükle
        $products = [];
        foreach ($command->items as $item) {
            $product = $this->productRepo->findById(new ProductId($item->productId));
            if (!$product) {
                throw new ProductNotFoundException($item->productId);
            }
            if ($product->stock() < $item->quantity) {
                throw new InsufficientStockException($product->name(), $product->stock(), $item->quantity);
            }
            $products[] = ['product' => $product, 'quantity' => $item->quantity];
        }

        // 2. Kupon yoxla
        $coupon = null;
        if ($command->couponCode) {
            $coupon = $this->couponRepo->findByCode($command->couponCode);
        }

        // 3. Order yarat (Factory istifadə edərək)
        $order = $this->orderFactory->create(
            customerId: new CustomerId($command->customerId),
            shippingAddress: $command->shippingAddress->toValueObject(),
            notes: $command->notes,
        );

        // 4. Items əlavə et
        foreach ($products as $entry) {
            $order->addItem($entry['product'], $entry['quantity']);
        }

        // 5. Discount hesabla (Domain Service)
        $discount = $this->pricingService->calculateDiscount($order, $coupon);
        $order->applyDiscount($discount);

        // 6. Sifarişi yerləşdir
        $order->place();

        // 7. Saxla (repository events-ləri dispatch edəcək)
        $this->orderRepo->save($order);

        return $order->id();
    }
}
```

---

## Factory Pattern

Factory — mürəkkəb domain obyektlərinin yaradılması üçün istifadə olunur.

*Factory — mürəkkəb domain obyektlərinin yaradılması üçün istifadə olun üçün kod nümunəsi:*
```php
// Domain/Order/Factories/OrderFactory.php
class OrderFactory
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
    ) {}

    public function create(
        CustomerId $customerId,
        Address $shippingAddress,
        ?string $notes = null,
    ): Order {
        $orderId = $this->orderRepo->nextId();
        $currency = Currency::AZN();

        return new Order(
            id: $orderId,
            customerId: $customerId,
            status: OrderStatus::Draft,
            subtotal: Money::zero($currency),
            taxAmount: Money::zero($currency),
            discountAmount: Money::zero($currency),
            total: Money::zero($currency),
            shippingAddress: $shippingAddress,
            notes: $notes,
            createdAt: new DateTimeImmutable(),
        );
    }

    // Mövcud datadan yaratmaq üçün (DB-dən oxuyanda)
    public function reconstitute(
        OrderId $id,
        CustomerId $customerId,
        OrderStatus $status,
        Money $subtotal,
        Money $taxAmount,
        Money $discountAmount,
        Money $total,
        Address $shippingAddress,
        ?string $notes,
        DateTimeImmutable $createdAt,
        array $items = [],
    ): Order {
        $order = new Order(
            id: $id,
            customerId: $customerId,
            status: $status,
            subtotal: $subtotal,
            taxAmount: $taxAmount,
            discountAmount: $discountAmount,
            total: $total,
            shippingAddress: $shippingAddress,
            notes: $notes,
            createdAt: $createdAt,
        );

        foreach ($items as $item) {
            $order->loadItem($item);
        }

        return $order;
    }
}
```

---

## Specification Pattern

Specification — business qaydalarını ayrıca class-da təmsil etmək üçün istifadə olunur. Təkrar istifadə və test etmə asanlığı təmin edir.

*Specification — business qaydalarını ayrıca class-da təmsil etmək üçün üçün kod nümunəsi:*
```php
// Domain/Shared/Specifications/Specification.php
interface Specification
{
    public function isSatisfiedBy(mixed $candidate): bool;
    public function and(Specification $other): Specification;
    public function or(Specification $other): Specification;
    public function not(): Specification;
}

abstract class AbstractSpecification implements Specification
{
    public function and(Specification $other): Specification
    {
        return new AndSpecification($this, $other);
    }

    public function or(Specification $other): Specification
    {
        return new OrSpecification($this, $other);
    }

    public function not(): Specification
    {
        return new NotSpecification($this);
    }
}

class AndSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate)
            && $this->right->isSatisfiedBy($candidate);
    }
}

class OrSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate)
            || $this->right->isSatisfiedBy($candidate);
    }
}

class NotSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $spec,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return !$this->spec->isSatisfiedBy($candidate);
    }
}

// Konkret specification-lar
class CancellableOrderSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (!$candidate instanceof Order) {
            return false;
        }

        $cancellableStatuses = [OrderStatus::Draft, OrderStatus::Placed, OrderStatus::Paid];
        return in_array($candidate->status(), $cancellableStatuses);
    }
}

class RefundableOrderSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (!$candidate instanceof Order) {
            return false;
        }

        return $candidate->status() === OrderStatus::Paid
            || $candidate->status() === OrderStatus::Cancelled;
    }
}

class HighValueOrderSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Money $threshold,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (!$candidate instanceof Order) {
            return false;
        }

        return $candidate->total()->greaterThanOrEqual($this->threshold);
    }
}

class OlderThanSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly int $days,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (!$candidate instanceof Order) {
            return false;
        }

        $threshold = new DateTimeImmutable("-{$this->days} days");
        return $candidate->createdAt() < $threshold;
    }
}

// İstifadə nümunəsi
$cancellable = new CancellableOrderSpecification();
$highValue = new HighValueOrderSpecification(Money::AZN(500));
$oldOrder = new OlderThanSpecification(30);

// Combine: ləğv edilə bilən VƏ yüksək dəyərli VƏ 30 gündən köhnə
$complexSpec = $cancellable->and($highValue)->and($oldOrder);

$order = $orderRepository->findById($orderId);

if ($complexSpec->isSatisfiedBy($order)) {
    // Bu sifariş üçün xüsusi proses
}

// Filtrasiya
$allOrders = $orderRepository->findAll();
$eligibleOrders = array_filter(
    $allOrders,
    fn (Order $order) => $complexSpec->isSatisfiedBy($order),
);
```

---

## Anti-corruption Layer

Anti-corruption Layer (ACL) — xarici sistemlərin modelini öz domain modelimizə çevirən təbəqədir. Xarici sistem dəyişəndə yalnız ACL dəyişir.

*Anti-corruption Layer (ACL) — xarici sistemlərin modelini öz domain mo üçün kod nümunəsi:*
```php
// Xarici ödəniş sistemi (Stripe) üçün Anti-corruption Layer

// Bizim domain interface
interface PaymentGatewayInterface
{
    public function charge(Money $amount, PaymentMethodToken $token): PaymentResult;
    public function refund(TransactionId $transactionId, Money $amount): RefundResult;
}

// ACL - Stripe-ı öz domain modelimizə çevirir
class StripePaymentAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {}

    public function charge(Money $amount, PaymentMethodToken $token): PaymentResult
    {
        try {
            // Stripe-ın öz formatına çevir
            $stripeResponse = $this->stripe->paymentIntents->create([
                'amount' => $amount->amount, // cent
                'currency' => strtolower($amount->currency->code),
                'payment_method' => $token->value,
                'confirm' => true,
            ]);

            // Stripe-ın cavabını öz domain modelimizə çevir
            return $this->toPaymentResult($stripeResponse);
        } catch (\Stripe\Exception\CardException $e) {
            return PaymentResult::failed(
                "Kart xətası: {$e->getMessage()}"
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return PaymentResult::failed(
                "Ödəniş sistemi xətası: {$e->getMessage()}"
            );
        }
    }

    public function refund(TransactionId $transactionId, Money $amount): RefundResult
    {
        try {
            $stripeRefund = $this->stripe->refunds->create([
                'payment_intent' => $transactionId->value,
                'amount' => $amount->amount,
            ]);

            return $this->toRefundResult($stripeRefund);
        } catch (\Exception $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    // Stripe response -> Domain model mapping
    private function toPaymentResult(\Stripe\PaymentIntent $intent): PaymentResult
    {
        return match($intent->status) {
            'succeeded' => PaymentResult::successful(
                transactionId: new TransactionId($intent->id),
                amount: new Money($intent->amount, new Currency(strtoupper($intent->currency))),
            ),
            'requires_action' => PaymentResult::requiresAction(
                redirectUrl: $intent->next_action->redirect_to_url->url ?? '',
            ),
            default => PaymentResult::failed("Gözlənilməz status: {$intent->status}"),
        };
    }

    private function toRefundResult(\Stripe\Refund $refund): RefundResult
    {
        return match($refund->status) {
            'succeeded' => RefundResult::successful(new TransactionId($refund->id)),
            'pending' => RefundResult::pending(new TransactionId($refund->id)),
            default => RefundResult::failed("Refund status: {$refund->status}"),
        };
    }
}
```

---

## DDD və CQRS

CQRS (Command Query Responsibility Segregation) — oxuma (query) və yazma (command) əməliyyatlarını ayırma prinsipidir. DDD ilə çox yaxşı uyğun gəlir.

*CQRS (Command Query Responsibility Segregation) — oxuma (query) və yaz üçün kod nümunəsi:*
```php
// Command - yazma əməliyyatı
readonly class PlaceOrderCommand
{
    /**
     * @param OrderItemInput[] $items
     */
    public function __construct(
        public int $customerId,
        public array $items,
        public AddressDTO $shippingAddress,
        public PaymentMethod $paymentMethod,
        public ?string $couponCode = null,
        public ?string $notes = null,
    ) {}
}

readonly class OrderItemInput
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}

readonly class CancelOrderCommand
{
    public function __construct(
        public string $orderId,
        public string $reason,
    ) {}
}

// Command Handler
class PlaceOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly ProductRepositoryInterface $productRepo,
        private readonly OrderPricingService $pricingService,
        private readonly OrderFactory $orderFactory,
    ) {}

    public function handle(PlaceOrderCommand $command): string
    {
        // Domain logic...
        $order = $this->orderFactory->create(
            customerId: new CustomerId($command->customerId),
            shippingAddress: $command->shippingAddress->toValueObject(),
            notes: $command->notes,
        );

        foreach ($command->items as $item) {
            $product = $this->productRepo->findById(new ProductId($item->productId));
            $order->addItem($product, $item->quantity);
        }

        $order->place();
        $this->orderRepo->save($order);

        return $order->id()->value;
    }
}

class CancelOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
    ) {}

    public function handle(CancelOrderCommand $command): void
    {
        $order = $this->orderRepo->findById(new OrderId($command->orderId));
        $order->cancel(new CancellationReason($command->reason));
        $this->orderRepo->save($order);
    }
}

// Query - oxuma əməliyyatı (domain model-dən keçmir, birbaşa DB-dən oxuyur)
readonly class GetOrderQuery
{
    public function __construct(
        public string $orderId,
    ) {}
}

readonly class ListOrdersQuery
{
    public function __construct(
        public ?int $customerId = null,
        public ?string $status = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}

// Query Handler - optimize edilmiş oxuma (Eloquent/raw query)
class GetOrderQueryHandler
{
    public function handle(GetOrderQuery $query): OrderDTO
    {
        $order = OrderModel::with(['items', 'customer'])
            ->findOrFail($query->orderId);

        return OrderDTO::fromModel($order);
    }
}

class ListOrdersQueryHandler
{
    public function handle(ListOrdersQuery $query): LengthAwarePaginator
    {
        $builder = OrderModel::with(['items', 'customer']);

        if ($query->customerId) {
            $builder->where('customer_id', $query->customerId);
        }

        if ($query->status) {
            $builder->where('status', $query->status);
        }

        if ($query->dateFrom) {
            $builder->where('created_at', '>=', $query->dateFrom);
        }

        if ($query->dateTo) {
            $builder->where('created_at', '<=', $query->dateTo);
        }

        return $builder
            ->orderBy('created_at', 'desc')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}

// Command Bus / Query Bus (Laravel-də sadə implementasiya)
class CommandBus
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function dispatch(object $command): mixed
    {
        $handlerClass = $this->resolveHandler($command);
        $handler = $this->container->make($handlerClass);

        return $handler->handle($command);
    }

    private function resolveHandler(object $command): string
    {
        $commandClass = get_class($command);
        $handlerClass = str_replace('Command', 'Handler', $commandClass);

        if (!class_exists($handlerClass)) {
            throw new RuntimeException("Handler tapılmadı: {$handlerClass}");
        }

        return $handlerClass;
    }
}

// Controller-da istifadə
class OrderController extends Controller
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly ListOrdersQueryHandler $listOrdersHandler,
        private readonly GetOrderQueryHandler $getOrderHandler,
    ) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $command = new PlaceOrderCommand(
            customerId: $request->user()->id,
            items: array_map(
                fn ($item) => new OrderItemInput($item['product_id'], $item['quantity']),
                $request->validated('items'),
            ),
            shippingAddress: AddressDTO::fromArray($request->validated('shipping_address')),
            paymentMethod: PaymentMethod::from($request->validated('payment_method')),
            couponCode: $request->validated('coupon_code'),
            notes: $request->validated('notes'),
        );

        $orderId = $this->commandBus->dispatch($command);

        return response()->json(['order_id' => $orderId], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = new ListOrdersQuery(
            customerId: $request->user()->id,
            status: $request->query('status'),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 20),
        );

        $orders = $this->listOrdersHandler->handle($query);

        return response()->json($orders);
    }

    public function show(string $orderId): JsonResponse
    {
        $order = $this->getOrderHandler->handle(new GetOrderQuery($orderId));

        return response()->json($order);
    }

    public function cancel(string $orderId, CancelOrderRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new CancelOrderCommand(
            orderId: $orderId,
            reason: $request->validated('reason'),
        ));

        return response()->json(['message' => 'Sifariş ləğv edildi.']);
    }
}
```

---

## Laravel-də DDD Tətbiqi

### Service Provider qeydiyyatı

*Service Provider qeydiyyatı üçün kod nümunəsi:*
```php
// app/Providers/DomainServiceProvider.php
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(
            OrderRepositoryInterface::class,
            EloquentOrderRepository::class,
        );
        $this->app->bind(
            ProductRepositoryInterface::class,
            EloquentProductRepository::class,
        );
        $this->app->bind(
            CustomerRepositoryInterface::class,
            EloquentCustomerRepository::class,
        );

        // Payment gateway (ACL)
        $this->app->bind(PaymentGatewayInterface::class, function () {
            return match(config('payment.gateway')) {
                'stripe' => new StripePaymentAdapter(
                    new StripeClient(config('payment.stripe.secret')),
                ),
                'paypal' => new PayPalPaymentAdapter(
                    config('payment.paypal.client_id'),
                    config('payment.paypal.secret'),
                ),
                default => throw new RuntimeException('Bilinməyən ödəniş gateway-i'),
            };
        });

        // Domain Services
        $this->app->singleton(OrderPricingService::class);

        // Command Bus
        $this->app->singleton(CommandBus::class);
    }
}
```

### Composer autoload konfiqurasiyası

*Composer autoload konfiqurasiyası üçün kod nümunəsi:*
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Domain\\": "src/Domain/",
            "Application\\": "src/Application/",
            "Infrastructure\\": "src/Infrastructure/"
        }
    }
}
```

---

## Real-World E-commerce Nümunəsi

Tam e-commerce axışı - müştəri sifariş verir:

*Tam e-commerce axışı - müştəri sifariş verir üçün kod nümunəsi:*
```php
// 1. HTTP Request gəlir
// POST /api/orders
// {
//   "items": [
//     {"product_id": 1, "quantity": 2},
//     {"product_id": 3, "quantity": 1}
//   ],
//   "shipping_address": {
//     "street": "Neftçilər prospekti 45",
//     "city": "Bakı",
//     "state": "Bakı",
//     "zip_code": "AZ1000",
//     "country": "Azərbaycan"
//   },
//   "payment_method": "card",
//   "card_token": "tok_xxx",
//   "coupon_code": "SUMMER20"
// }

// 2. Controller - Presentation Layer
class OrderController extends Controller
{
    public function store(PlaceOrderRequest $request, CommandBus $bus): JsonResponse
    {
        $orderId = $bus->dispatch(PlaceOrderCommand::fromRequest($request));

        return response()->json([
            'message' => 'Sifariş uğurla yaradıldı.',
            'order_id' => $orderId,
        ], 201);
    }
}

// 3. Command Handler - Application Layer
class PlaceOrderHandler
{
    public function handle(PlaceOrderCommand $command): string
    {
        return DB::transaction(function () use ($command) {
            // Order yaratma (Factory)
            $order = $this->orderFactory->create(
                customerId: new CustomerId($command->customerId),
                shippingAddress: $command->shippingAddress->toValueObject(),
            );

            // Hər item üçün: stok yoxla, əlavə et
            foreach ($command->items as $item) {
                $product = $this->productRepo->findById(new ProductId($item->productId));

                if ($product->stock() < $item->quantity) {
                    throw new InsufficientStockException(
                        $product->name(),
                        $product->stock(),
                        $item->quantity,
                    );
                }

                $order->addItem($product, $item->quantity);
            }

            // Kupon tətbiq et
            if ($command->couponCode) {
                $coupon = $this->couponRepo->findByCode($command->couponCode);
                $discount = $this->pricingService->calculateDiscount($order, $coupon);
                $order->applyDiscount($discount);
            }

            // Sifarişi yerləşdir
            $order->place(); // OrderPlaced event qeyd olunur

            // Saxla (events dispatch olunur)
            $this->orderRepo->save($order);

            return $order->id()->value;
        });
    }
}

// 4. Domain Events tetiklenir:

// OrderPlaced -> SendOrderConfirmationEmail (email göndərilir)
// OrderPlaced -> ReserveInventory (stokdan reservation yaradılır)
// OrderPlaced -> NotifyWarehouse (anbar bildirişi)

// 5. Ödəniş prosesi (ayrıca endpoint)
// POST /api/orders/{id}/pay

class PaymentController extends Controller
{
    public function pay(string $orderId, PayOrderRequest $request, CommandBus $bus): JsonResponse
    {
        $result = $bus->dispatch(new PayOrderCommand(
            orderId: $orderId,
            cardToken: $request->validated('card_token'),
        ));

        if ($result->requiresRedirect) {
            return response()->json([
                'redirect_url' => $result->redirectUrl,
            ]);
        }

        return response()->json([
            'message' => 'Ödəniş uğurlu oldu.',
            'transaction_id' => $result->transactionId,
        ]);
    }
}

// 6. OrderPaid event tetiklenir:
// OrderPaid -> GenerateInvoice
// OrderPaid -> UpdateSalesReport
// OrderPaid -> NotifyShipping
```

---

## Üstünlüklər və Mənfi Cəhətlər

### Üstünlüklər:
1. **Business logic aydınlığı** — kod domain-i əks etdirir
2. **Testability** — domain logic framework-dən asılı deyil, unit test asandır
3. **Maintainability** — dəyişikliklər lokaldır, bir yeri dəyişmək digərinə təsir etmir
4. **Ubiquitous Language** — developer və business eyni dildə danışır
5. **Modularity** — bounded context-lər müstəqil development və deploy imkanı verir
6. **Complexity management** — mürəkkəb domain-i idarə etmək asanlaşır

### Mənfi cəhətlər:
1. **Boilerplate code** — çox class, interface, mapping lazımdır
2. **Öyrənmə əyrisi** — pattern-ləri düzgün tətbiq etmək vaxt tələb edir
3. **Overengineering riski** — sadə CRUD üçün çox mürəkkəbdir
4. **Performance overhead** — mapping-lər əlavə yük yaradır
5. **Team alignment** — bütün komanda DDD-ni bilməlidir
6. **Initial development time** — ilk development daha çox vaxt alır

---

## İntervyu Sualları

### 1. DDD nədir? Nə vaxt istifadə etməli?
**Cavab**: DDD business domain-i kodda modelləşdirən software design yanaşmasıdır. Mürəkkəb business logic, domain ekspertləri ilə sıx əməkdaşlıq, uzunmüddətli layihələr üçün istifadə olunur. Sadə CRUD tətbiqləri üçün lazım deyil - overengineering olar.

### 2. Bounded Context nədir? Nümunə verin.
**Cavab**: Domain-in müəyyən kontekstdə sərhədlənmiş modelidir. E-commerce-də "Product" Catalog BC-də ad və şəkildir, Inventory BC-də SKU və stok sayısıdır, Shipping BC-də çəki və ölçüdür. Hər BC-nin öz modeli, öz dili, öz sərhədləri var.

### 3. Aggregate və Aggregate Root nədir?
**Cavab**: Aggregate — bir-birinə bağlı entity/VO qrupudur, consistency sərhədi təyin edir. Aggregate Root — aggregate-in yeganə giriş nöqtəsidir. Bütün dəyişikliklər root vasitəsilə edilir. Məsələn, Order (root) + OrderItem (child). Birbaşa OrderItem-ə müraciət yanlışdır.

### 4. Entity və Value Object arasında fərq nədir?
**Cavab**: Entity-nin unikal identity-si var, ID-yə görə müqayisə olunur, mutable-dır (User, Order). Value Object-in identity-si yoxdur, dəyərinə görə müqayisə olunur, immutable-dır (Money, Email, Address).

### 5. Domain Event nədir? Nə üçün istifadə olunur?
**Cavab**: Domain-də baş vermiş mühüm hadisəni təmsil edən obyektdir. Keçmiş zamanda adlandırılır (OrderPlaced, PaymentReceived). Aggregate-lər arası ünsiyyət, side effect-lərin ayrılması, audit trail üçün istifadə olunur. Loose coupling təmin edir.

### 6. Repository pattern nədir? Niyə interface istifadə olunur?
**Cavab**: Repository — aggregate-ləri persistence-dən abstrakt edən pattern-dir. Interface domain layer-dədir, implementasiya infrastructure-dadır. Bu DIP-ə uyğundur — domain concrete DB-dən asılı deyil. Test zamanı in-memory repository istifadə edilə bilər.

### 7. Domain Service və Application Service arasında fərq nədir?
**Cavab**: Domain Service — heç bir entity-yə aid olmayan domain logic üçündür (qiymət hesablama, vergi hesablama). Framework-dən asılı deyil. Application Service — use case-ləri orkestrasiya edir (transaction idarə, event dispatch, xarici servis çağırışı). Framework-ə asılı ola bilər.

### 8. Anti-corruption Layer nədir?
**Cavab**: Xarici sistemin modelini öz domain modelimizə çevirən adapter təbəqəsidir. Xarici API dəyişəndə yalnız ACL dəyişir, domain model təsirləntmir. Stripe API-ni öz PaymentResult domain modelimizə çevirən adapter ACL nümunəsidir.

### 9. CQRS nədir? DDD ilə necə əlaqəlidir?
**Cavab**: CQRS — oxuma (Query) və yazma (Command) əməliyyatlarını ayırma prinsipidir. DDD ilə yaxşı uyğun gəlir: Command-lar domain model vasitəsilə keçir (business rules qorunur), Query-lər birbaşa DB-dən oxuyur (performans). Mürəkkəb domain-lərdə oxuma və yazma model-ləri fərqli ola bilər.

### 10. Laravel-də DDD tətbiq edərkən ən böyük çətinlik nədir?
**Cavab**: Laravel Active Record pattern (Eloquent) istifadə edir, DDD isə Data Mapper pattern-ə daha uyğundur. Domain entity-ləri Eloquent Model-dən asılı olmamalıdır — bu, mapping layer lazım olduğunu bildirir. Həmçinin, Laravel-in convention-over-configuration yanaşması DDD-nin folder strukturu ilə uyğun gəlmir, custom autoloading lazımdır.

### 11. Specification pattern nədir?
**Cavab**: Business qaydalarını ayrıca class-da təmsil edən pattern-dir. isSatisfiedBy() metodu ilə obyektin qaydaya uyğun olub-olmadığını yoxlayır. AND, OR, NOT operatorları ilə combine edilə bilər. Təkrar istifadə, test asanlığı və business qaydalarının aydınlığı üçündür.

### 12. DDD-ni hər layihədə istifadə etməli?
**Cavab**: Xeyr. DDD mürəkkəb domain-lər üçündür. Sadə CRUD, kiçik layihələr, prototiplər üçün lazım deyil — əlavə mürəkkəblik yaradır. DDD-nin dəyəri business logic mürəkkəbliyi ilə düz mütənasibdir. "Start simple, evolve when needed" yanaşması daha sağlamdır.

### 13. Context Map-in əsas münasibət tipləri hansılardır?
**Cavab**: DDD-nin Strategic Design hissəsindən olan Context Map bounded context-lər arasındakı münasibəti göstərir. Əsas tiplər:
- **Shared Kernel**: İki context eyni kod hissəsini paylaşır. Dəyişiklik razılıq tələb edir. Az istifadə olunmalıdır.
- **Customer-Supplier**: Bir context (Supplier) digərinin (Customer) ehtiyaclarına cavab verir. Supplier-ın API-si Customer üçün planlaşdırılır.
- **Conformist**: Customer Supplier-ın modelinə tam uyğunlaşır — ACL yazmağa güc yoxdursa. Vendor API-lərinə uyğunlaşmaq kimi.
- **Anti-Corruption Layer (ACL)**: Customer Supplier-ın modelini öz domain dilinə çevirir — ən güclü izolyasiya.
- **Open Host Service / Published Language**: Bir context public protokol/API yayımlayır, istənilən context istifadə edə bilər (REST API, event schema).
- **Separate Ways**: Kontekstlər heç bir inteqrasiya olmadan müstəqil işləyir.

### 14. Domain Event ilə Integration Event arasındakı fərq nədir?
**Cavab**: **Domain Event** — bir Bounded Context daxilindədir, sync dispatch oluna bilər, Laravel Event sistemi ilə işləyir, PHP class-ı kimi serialization olmaya bilər. Nümunə: `OrderPlaced` event-i Order BC-nin daxilindəki Inventory, Notification listener-ları üçündür. **Integration Event** — Bounded Context-lər arasında və ya microservice-lər arasındadır, message broker (RabbitMQ, Kafka) vasitəsilə göndərilir, serializable formatda (JSON, Avro) olmalıdır, versiyalanmalıdır. Bir Domain Event Integration Event-ə çevrilə bilər (application service-də).

---

## Anti-patternlər

**1. Anemic Domain Model**
Eloquent model-ləri yalnız getter/setter-dən ibarət tutub bütün business logic-i Service class-lara köçürmək — domain qaydaları dağılır, bir entity-yə aid məntiqi tapmaq üçün onlarca fayl axtarılır. Business logic-i aid olduğu Aggregate/Entity daxilinə yerləşdirin.

**2. Bounded Context Sərhədlərini Pozumaq**
Bir bounded context-in entity-lərini başqasına birbaşa import etmək — modullar bir-birinə bağlanır, bir tərəfdəki dəyişiklik digərini sındırır. Context-lər arasında yalnız Domain Event-lər və ya Anti-Corruption Layer vasitəsilə əlaqə qurun.

**3. Hər Layihəyə DDD Tətbiq Etmək**
Sadə CRUD tətbiqlərə tam DDD infrastrukturunun (Aggregate, Repository, Domain Event, ACL) tətbiqi — minimal fayda verən artıq mürəkkəblik yaranır, inkişaf tempi yavaşlayır. DDD-ni yalnız mürəkkəb domain-lərdə tətbiq edin; sadə layihələr üçün daha yüngül yanaşmalar seçin.

**4. Ubiquitous Language-i Kodda İstifadə Etməmək**
Domain ekspertlərinin dili ilə kod arasında fərq olması — `processItem()` kodu nə iş gördüyünü bildirmir, domain mütəxəssisi kodu oxuya bilmir. Kod, test adları, variable adları domain dilindən istifadə etməlidir: `submitOrder()`, `approveRefund()`.

**5. Application Service ilə Domain Service-i Qarışdırmaq**
Xarici sistem çağırışlarını, email göndərməyi domain service-ə yerləşdirmək — domain paketi xarici infrastruktura bağlanır, test etmək çətinləşir. Domain Service yalnız domain logic saxlamalı; xarici əlaqələr Application Service qatına aiddir.

**6. Aggregate Kökünü Bypass Etmək**
`OrderItem`-ə birbaşa müraciət edib `Order` Aggregate Root-unu keçməmək — invariant-lar qorunmur, Order-ın ümumi məbləği sinxronizasiyadan çıxır. Aggregate daxilindəki entity-lərə həmişə Root vasitəsilə müraciət edin.
