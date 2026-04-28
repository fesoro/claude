# DDD Aggregates (Senior ⭐⭐⭐)

## İcmal

Aggregate — birlikdə dəyişən entity-lər və value object-lər qrupudur. Bir vahid kimi davranır: ya hamısı dəyişir, ya heç biri. Aggregate Root — qrupun yeganə giriş nöqtəsidir; bütün xarici müraciətlər root vasitəsilə keçir.

```
Order Aggregate:
┌─────────────────────────────────────────────┐
│  Order (Aggregate Root)                     │
│  ├── id: OrderId                            │
│  ├── status: OrderStatus                    │
│  ├── customerId: CustomerId (ref)           │
│  ├── items: OrderItem[]                     │
│  │   ├── OrderItem                          │
│  │   │   ├── productId: ProductId (ref)     │
│  │   │   ├── quantity: Quantity             │
│  │   │   └── price: Money                  │
│  │   └── OrderItem                         │
│  ├── shippingAddress: Address               │
│  └── total: Money                           │
└─────────────────────────────────────────────┘

OrderItem-ə yalnız Order vasitəsilə daxil ola bilərsən!
```

## Niyə Vacibdir

Aggregate olmadan business rule-lar dağınıq qalır. `$order->items[0]->price = 50` kimi birbaşa dəyişiklik aggregate-in invariant-larını pozur — `order.total` artıq doğru deyil. Aggregate Root bütün dəyişikliklərin keçdiyi mərkəz nöqtədir, invariant-lar daxilindən qorunur.

**Consistency Boundary:**
```
Aggregate = Transaction boundary

Bir aggregate-dəki dəyişikliklər bir transactional unit-dir.
Fərqli aggregate-lər fərqli transactions-da dəyişdirilir.

Order.addItem() → Order consistency qorunur (atomik)
Order + Inventory → fərqli aggregates → fərqli transactions
                  → eventual consistency (Saga/Domain Events)
```

## Əsas Anlayışlar

**Aggregate Root qaydaları:**
1. Aggregate-ə yalnız root vasitəsilə daxil olunur
2. Root-un ID-si aggregate-in kimliyi
3. Root consistency invariant-ları qoruyur
4. External reference yalnız root ID-sinə ola bilər

**Düzgün vs Yanlış:**
```
Düzgün:                         Yanlış:

  ┌──────────┐                    ┌──────────┐
  │  Order   │◄── repository      │  Order   │
  │  (root)  │                    │  (root)  │
  └────┬─────┘                    └──────────┘

  ┌────▼─────┐                    ┌──────────┐
  │OrderItem │← Order vasitəsilə  │OrderItem │◄── repository ❌
  └──────────┘                    └──────────┘
```

**Aggregate Dizayn Heuristics:**
```
1. Consistency invariant-ları müəyyən et
   "Order-ın toplam dəyəri item-lərin cəminə bərabər olmalıdır"
   → Bu invariant aggregate boundary-ni müəyyən edir

2. Aggregate-i kiçik saxla
   ❌ Customer + Orders + Payments + Addresses (çox böyük!)
   ✅ Customer, Order, Payment (ayrı aggregates)

3. ID ilə referans
   Order-da: customerId: CustomerId  ← ID ✅
   Order-da: customer: Customer      ← ❌ Object referansı

4. Eventual consistency üçün Domain Events
   Order → PaymentRequired (event) → Payment Aggregate

5. Business logic aggregate-də qalsın
   ✅ order.addItem(item)
   ✅ order.confirm()
   ❌ orderService.addItemToOrder(order, item) (anemic)
```

## Praktik Baxış

**Real istifadə:**
- **Order Aggregate**: OrderItem-lər root-un altında; item əlavə/silmə yalnız root vasitəsilə; total invariant qorunur
- **BankAccount Aggregate**: Transaction-lar aggregate-in içindədir; balance = transactions sum; overdraft invariant-ı
- **ShoppingCart**: CartItem-lər, coupon, max item count invariant-ı

**Trade-off-lar:**
- Böyük aggregate: daha güclü consistency, amma daha geniş lock, yüksək contention
- Kiçik aggregate: yüksək throughput, eventual consistency tələb edir, daha çox event-based koordinasiya

**İstifadə etməmək:**
- Read-only sorğular üçün aggregate yükləmək — Query side üçün sadə Eloquent kifayətdir
- Çox sadə entity-lər üçün (yalnız bir cədvəl, heç bir invariant) — plain Eloquent model işlər

**Common mistakes:**
- Aggregate root-u bypass etmək: `$order->items[0]->price = 50`
- Aggregate-ə digər aggregate-i object reference ilə daxil etmək
- Bütün domain-i bir aggregate-ə sıxışdırmaq
- Repository-dən child entity yükləmək (OrderItemRepository)

**Anti-Pattern Nə Zaman Olur?**

- **Giant aggregate — lock contention** — Order aggregate içinə Customer, Product, Inventory, ShippingHistory, PaymentHistory hamısını yerləşdirmək. Hər Order əməliyyatı customer record-u da lock edir. Yüksək concurrency altında timeout-lar başlayır. Aggregate yalnız birlikdə dəyişən, consistency invariantı olan entity-ləri içərməlidir.
- **Aggregate root-u bypass etmək** — `$order->items()->where('product_id', $id)->update(['quantity' => 5])` — Eloquent vasitəsilə birbaşa DB yazması aggregate-in invariant-larını bilmir, total yenilənmir, event atılmır. Bütün dəyişikliklər aggregate metodları vasitəsilə edilməlidir.
- **Object reference ilə aggregate-lər arası bağlantı** — `$order->customer` (Customer object) tutmaq. Customer dəyişdikdə Order da yenilənməlidir; transaction boundary-lər üst-üstə düşür. Yalnız ID saxlayın: `$order->customerId`.
- **Invariant-ları ignore etmək** — aggregate-in main məqsədi invariant-ları qorumaq. `Order::addItem()` total limitini yoxlamadan item əlavə etsə, aggregate mənasını itirdi. Business rule-lar aggregate metodları içinə yazılmalıdır.

## Nümunələr

### Ümumi Nümunə

E-commerce sifariş prosesini düşünün. `OrderItem` sifarişin hissəsidir — müstəqil mövcud olmur. `Order` root-dur: item əlavə etmək, silmək, sifarişi tamamlamaq — hamısı `Order` metodları vasitəsiylə. `Customer` ayrı aggregate-dir, yalnız `customerId` saxlanılır.

### PHP/Laravel Nümunəsi

```php
<?php
namespace App\Domain\Order;

// Value Objects
final class OrderId
{
    public function __construct(public readonly string $value)
    {
        if (empty($value)) throw new \InvalidArgumentException('OrderId boş ola bilməz');
    }

    public static function generate(): self
    {
        return new self(Str::uuid()->toString());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

final class Money
{
    public function __construct(
        public readonly int $amount,     // sentavarda (100 = $1.00)
        public readonly string $currency
    ) {
        if ($amount < 0) throw new \InvalidArgumentException('Mənfi məbləğ olmaz');
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \DomainException('Fərqli valyutaları cəmləmək olmaz');
        }
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->amount * $quantity, $this->currency);
    }

    public function greaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}

// Aggregate Entity (not root) — yalnız Order vasitəsilə daxil olunur
class OrderItem
{
    private function __construct(
        private readonly string $productId,
        private int             $quantity,
        private readonly Money  $unitPrice,
    ) {}

    public static function create(string $productId, int $quantity, Money $unitPrice): self
    {
        if ($quantity <= 0) {
            throw new \DomainException('Miqdar müsbət olmalıdır');
        }
        return new self($productId, $quantity, $unitPrice);
    }

    public function changeQuantity(int $newQuantity): void
    {
        if ($newQuantity <= 0) {
            throw new \DomainException('Miqdar müsbət olmalıdır');
        }
        $this->quantity = $newQuantity;
    }

    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function productId(): string { return $this->productId; }
    public function quantity(): int { return $this->quantity; }
    public function unitPrice(): Money { return $this->unitPrice; }
}

// Aggregate Root
class Order
{
    private array  $items        = [];
    private array  $domainEvents = [];
    private Money  $total;
    private Money  $maxAllowedTotal;
    private OrderStatus $status;

    private function __construct(
        private readonly OrderId $id,
        private readonly string  $customerId, // ID referansı, Object yox ✅
    ) {
        $this->status         = OrderStatus::DRAFT;
        $this->total          = new Money(0, 'AZN');
        $this->maxAllowedTotal = new Money(1_000_000, 'AZN'); // 10,000 AZN
    }

    public static function create(string $customerId): self
    {
        $order = new self(OrderId::generate(), $customerId);
        $order->recordEvent(new OrderCreated($order->id->value, $customerId));
        return $order;
    }

    // Reconstitution — DB-dən yükləndikdə; event yaratmır
    public static function reconstitute(
        OrderId $id,
        string $customerId,
        OrderStatus $status,
        Money $total,
        array $items,
    ): self {
        $order = new self($id, $customerId);
        $order->status = $status;
        $order->total  = $total;
        $order->items  = $items;
        return $order;
    }

    public function addItem(string $productId, int $quantity, Money $unitPrice): void
    {
        $this->assertDraft();

        // Mövcud item-i yenilə
        foreach ($this->items as $item) {
            if ($item->productId() === $productId) {
                $item->changeQuantity($item->quantity() + $quantity);
                $this->recalculateTotal();
                return;
            }
        }

        $newItem     = OrderItem::create($productId, $quantity, $unitPrice);
        $newTotal    = $this->total->add($newItem->subtotal());

        // Invariant: max limit yoxla
        if ($newTotal->greaterThan($this->maxAllowedTotal)) {
            throw new OrderLimitExceededException("Sifariş limiti aşıldı");
        }

        $this->items[] = $newItem;
        $this->recalculateTotal();

        $this->recordEvent(new ItemAddedToOrder($this->id->value, $productId, $quantity));
    }

    public function removeItem(string $productId): void
    {
        $this->assertDraft();
        $this->items = array_values(
            array_filter($this->items, fn(OrderItem $i) => $i->productId() !== $productId)
        );
        $this->recalculateTotal();
    }

    public function confirm(): void
    {
        if ($this->status !== OrderStatus::DRAFT) {
            throw new \DomainException('Yalnız draft order təsdiqlənə bilər');
        }
        if (empty($this->items)) {
            throw new \DomainException('Boş order təsdiqlənə bilməz');
        }

        $this->status = OrderStatus::CONFIRMED;
        $this->recordEvent(new OrderConfirmed(
            $this->id->value,
            $this->customerId,
            $this->total->amount
        ));
    }

    public function cancel(string $reason): void
    {
        if ($this->status === OrderStatus::SHIPPED) {
            throw new \DomainException('Göndərilmiş order ləğv edilə bilməz');
        }
        $this->status = OrderStatus::CANCELLED;
        $this->recordEvent(new OrderCancelled($this->id->value, $reason));
    }

    // Consistency invariant
    private function recalculateTotal(): void
    {
        $total = new Money(0, 'AZN');
        foreach ($this->items as $item) {
            $total = $total->add($item->subtotal());
        }
        $this->total = $total;
    }

    private function assertDraft(): void
    {
        if ($this->status !== OrderStatus::DRAFT) {
            throw new \DomainException('Təsdiqlənmiş order dəyişdirilə bilməz');
        }
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

    public function id(): OrderId { return $this->id; }
    public function status(): OrderStatus { return $this->status; }
    public function total(): Money { return $this->total; }
    public function items(): array { return $this->items; }
    public function customerId(): string { return $this->customerId; }
}

enum OrderStatus: string
{
    case DRAFT     = 'draft';
    case CONFIRMED = 'confirmed';
    case SHIPPED   = 'shipped';
    case CANCELLED = 'cancelled';
}
```

**Aggregate-lərarası münasibət — yalnız ID:**

```php
// ❌ YANLIŞ: Aggregate daxilindən digər aggregate-ə referans
class Order
{
    private Customer $customer;    // ❌ Object reference
    private Product[] $products;  // ❌ Array of aggregates
}

// ✅ DÜZGÜN: Yalnız ID referansı
class Order
{
    private string $customerId;  // ✅ ID only
    private array  $items;       // ✅ OrderItem (child entity, not aggregate)
}

// Fərqli aggregate-ləri dəyişdirmək → Domain Events + Saga
class OrderApplicationService
{
    public function confirmOrder(string $orderId): void
    {
        $order = $this->orderRepo->findById(new OrderId($orderId));
        $order->confirm();
        $this->orderRepo->save($order);

        // Domain events dispatch et — Inventory ayrı transaction-da işləyir
        foreach ($order->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
```

**Repository — reconstitution pattern:**

```php
interface OrderRepository
{
    public function findById(OrderId $id): ?Order;
    public function save(Order $order): void;
    public function findByCustomerId(string $customerId): array;
}

class EloquentOrderRepository implements OrderRepository
{
    public function findById(OrderId $id): ?Order
    {
        $record = OrderRecord::with('items')->find($id->value);
        if (!$record) return null;
        return $this->toDomain($record);
    }

    public function save(Order $order): void
    {
        DB::transaction(function () use ($order) {
            OrderRecord::updateOrCreate(
                ['id' => $order->id()->value],
                [
                    'customer_id' => $order->customerId(),
                    'status'      => $order->status()->value,
                    'total'       => $order->total()->amount,
                ]
            );

            $record = OrderRecord::find($order->id()->value);
            $record->items()->delete();
            foreach ($order->items() as $item) {
                $record->items()->create([
                    'product_id' => $item->productId(),
                    'quantity'   => $item->quantity(),
                    'unit_price' => $item->unitPrice()->amount,
                ]);
            }
        });

        // Domain events dispatch (TX sonrası)
        foreach ($order->pullDomainEvents() as $event) {
            event($event);
        }
    }

    private function toDomain(OrderRecord $record): Order
    {
        $items = $record->items->map(fn($i) => OrderItem::create(
            $i->product_id,
            $i->quantity,
            new Money($i->unit_price, 'AZN')
        ))->all();

        // reconstitute — DB-dən yükləmək yeni yaratmaq deyil
        return Order::reconstitute(
            new OrderId($record->id),
            $record->customer_id,
            OrderStatus::from($record->status),
            new Money($record->total, 'AZN'),
            $items
        );
    }
}
```

## Praktik Tapşırıqlar

1. **Aggregate boundary tapın** — mövcud layihənizdəki 3 entity götürün; "bu iki entity həmişə birlikdə dəyişirmi?" sualını verin; aggregate-ləri müəyyən edin.
2. **Order aggregate tam implementasiya** — `addItem()`, `removeItem()`, `confirm()`, `cancel()`; hər metodda invariant yoxlaması; unit test suite yazın.
3. **reconstitute vs create** — DB-dən yüklənəndə event yaranmamalıdır; `create()` event yaradır, `reconstitute()` yalnız state bərpa edir; test edin.
4. **Aggregate-lər arası kommunikasiya** — `Order::confirm()` → `OrderConfirmed` event → `InventoryReservationHandler` ayrı transaction-da; Saga pattern araşdırın.
5. **Lock contention testi** — böyük aggregate (Customer + Orders + Payments) yaradın; concurrency testləri aparın; kiçik aggregate-lərə bölün; fərqi ölçün.

## Əlaqəli Mövzular

- [DDD Overview](01-ddd.md) — aggregate-in DDD-dəki yeri
- [Value Objects](02-value-objects.md) — aggregate daxilindəki VO-lar
- [Domain Events](05-ddd-domain-events.md) — aggregate-lərarası kommunikasiya
- [Aggregate Design Heuristics](09-aggregate-design-heuristics.md) — boundary qərarları
- [Domain Service vs App Service](08-domain-service-vs-app-service.md) — aggregate-i kim çağırır
- [CQRS](../integration/01-cqrs.md) — command side aggregate-lər, query side read models
- [Event Sourcing](../integration/02-event-sourcing.md) — aggregate state event-lərdən rebuild
- [Saga Pattern](../integration/03-saga-pattern.md) — aggregate-lər arası long-running transaction
- [Repository Pattern](../laravel/01-repository-pattern.md) — aggregate persistence
