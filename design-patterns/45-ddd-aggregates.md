# DDD Aggregates (Senior)

## Mündəricat
1. [Aggregate nədir?](#aggregate-nədir)
2. [Aggregate Root](#aggregate-root)
3. [Consistency Boundaries](#consistency-boundaries)
4. [Aggregate Dizayn Qaydaları](#aggregate-dizayn-qaydaları)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [Aggregate-lərarası Münasibətlər](#aggregate-lərarası-münasibətlər)
7. [Persistence](#persistence)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Aggregate nədir?

```
Aggregate — birlikdə dəyişən obyektlər qrupu.
Bir vahid kimi davranır: ya hamısı dəyişir, ya heç biri.

E-commerce nümunəsi:

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
  OrderItem-i birbaşa dəyişdirmək olmaz.
```

---

## Aggregate Root

**Qaydalar:**
1. Aggregate-ə yalnız root vasitəsilə daxil olunur
2. Root-un ID-si aggregate-in kimliyi
3. Root consistency invariant-ları qoruyur
4. External reference yalnız root ID-sinə ola bilər

```
Düzgün:                         Yanlış:
                                
  ┌──────────┐                    ┌──────────┐
  │  Order   │◄── repository      │  Order   │
  │  (root)  │                    │  (root)  │
  └────┬─────┘                    └──────────┘
       │                          
  ┌────▼─────┐                    ┌──────────┐
  │OrderItem │← Order vasitəsilə  │OrderItem │◄── repository ❌
  └──────────┘                    └──────────┘
```

---

## Consistency Boundaries

```
Aggregate = Transaction boundary

Bir aggregate-dəki dəyişiklikler bir transactional unit-dir.
Fərqli aggregate-lər fərqli transactions-da dəyişdirilir.

Order.addItem() → Order consistency qorunur (atomik)
Order + Inventory → fərqli aggregates → fərqli transactions
                  → eventual consistency (Saga/Domain Events)

Niyə vacibdir?
  Böyük aggregate = geniş lock = az performans
  Kiçik aggregate = dar lock = yüksək throughput
```

---

## Aggregate Dizayn Qaydaları

```
1. Consistency invariant-ları müəyyən et
   "Order-ın toplam dəyəri item-lərin cəminə bərabər olmalıdır"
   → Bu invariant aggregate boundary-ni müəyyən edir

2. Aggregate-i kiçik saxla
   ❌ Customer + Orders + Payments + Addresses (çox böyük!)
   ✅ Customer, Order, Payment (ayrı aggregates)

3. ID ilə referans
   Order-da: customerId: CustomerId  ← ID
   Order-da: customer: Customer      ← ❌ Object referansı
   
4. Eventual consistency üçün Domain Events
   Order → PaymentRequired (event) → Payment Aggregate

5. Business logic aggregate-də qalsın
   ✅ order.addItem(item)
   ✅ order.confirm()
   ❌ orderService.addItemToOrder(order, item) (anemic)
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
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
    
    public function equals(OrderId $other): bool
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
    
    public function add(Money $other): self
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
    
    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}

final class Quantity
{
    public function __construct(public readonly int $value)
    {
        if ($value <= 0) throw new \InvalidArgumentException('Miqdar 0-dan böyük olmalıdır');
    }
}

// Aggregate Entity (not root)
class OrderItem
{
    private function __construct(
        private readonly string $productId,
        private Quantity $quantity,
        private readonly Money $unitPrice,
    ) {}
    
    public static function create(string $productId, Quantity $quantity, Money $unitPrice): self
    {
        return new self($productId, $quantity, $unitPrice);
    }
    
    public function changeQuantity(Quantity $newQuantity): void
    {
        $this->quantity = $newQuantity;
    }
    
    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity->value);
    }
    
    public function productId(): string { return $this->productId; }
    public function quantity(): Quantity { return $this->quantity; }
    public function unitPrice(): Money { return $this->unitPrice; }
}

// Aggregate Root
class Order
{
    private array $items = [];
    private array $domainEvents = [];
    private OrderStatus $status;
    private Money $total;
    
    private function __construct(
        private readonly OrderId $id,
        private readonly string $customerId,  // ID referansı, Object yox
    ) {
        $this->status = OrderStatus::DRAFT;
        $this->total  = new Money(0, 'USD');
    }
    
    // Factory method — invariantları enforce edir
    public static function create(string $customerId): self
    {
        $order = new self(OrderId::generate(), $customerId);
        $order->recordEvent(new OrderCreated($order->id->value, $customerId));
        return $order;
    }
    
    public function addItem(string $productId, Quantity $quantity, Money $unitPrice): void
    {
        $this->assertNotConfirmed();
        
        // Mövcud item-i yenilə
        foreach ($this->items as $item) {
            if ($item->productId() === $productId) {
                $newQty = new Quantity($item->quantity()->value + $quantity->value);
                $item->changeQuantity($newQty);
                $this->recalculateTotal();
                return;
            }
        }
        
        // Yeni item əlavə et
        $this->items[] = OrderItem::create($productId, $quantity, $unitPrice);
        $this->recalculateTotal();
        
        $this->recordEvent(new ItemAddedToOrder(
            $this->id->value, $productId, $quantity->value
        ));
    }
    
    public function removeItem(string $productId): void
    {
        $this->assertNotConfirmed();
        
        $this->items = array_filter(
            $this->items,
            fn(OrderItem $item) => $item->productId() !== $productId
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
        $total = new Money(0, 'USD');
        foreach ($this->items as $item) {
            $total = $total->add($item->subtotal());
        }
        $this->total = $total;
    }
    
    private function assertNotConfirmed(): void
    {
        if ($this->status !== OrderStatus::DRAFT) {
            throw new \DomainException('Təsdiqlənmiş order dəyişdirilə bilməz');
        }
    }
    
    // Domain Events
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
    
    // Getters
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

---

## Aggregate-lərarası Münasibətlər

*Aggregate-lərarası Münasibətlər üçün kod nümunəsi:*
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
    private string $customerId;            // ✅ ID only
    private array $items;                  // ✅ OrderItem (child entity, not aggregate)
    // items[n]->productId  ← ✅ ID only
}

// Fərqli aggregate-ləri dəyişdirmək → Domain Events + Saga
class OrderService
{
    public function confirmOrder(string $orderId): void
    {
        $order = $this->orderRepo->findById(new OrderId($orderId));
        $order->confirm();
        
        // Domain events dispatch et
        foreach ($order->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
        
        $this->orderRepo->save($order);
        // OrderConfirmed event → InventoryService (ayrı transaction-da!)
    }
}
```

---

## Persistence

*Persistence üçün kod nümunəsi:*
```php
// Repository Pattern
interface OrderRepository
{
    public function findById(OrderId $id): ?Order;
    public function save(Order $order): void;
    public function findByCustomerId(string $customerId): array;
}

// Eloquent implementation
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
            $record = OrderRecord::updateOrCreate(
                ['id' => $order->id()->value],
                [
                    'customer_id' => $order->customerId(),
                    'status'      => $order->status()->value,
                    'total'       => $order->total()->amount,
                ]
            );
            
            // Items sync
            $record->items()->delete();
            foreach ($order->items() as $item) {
                $record->items()->create([
                    'product_id' => $item->productId(),
                    'quantity'   => $item->quantity()->value,
                    'unit_price' => $item->unitPrice()->amount,
                ]);
            }
        });
        
        // Domain events dispatch
        foreach ($order->pullDomainEvents() as $event) {
            event($event);
        }
    }
    
    private function toDomain(OrderRecord $record): Order
    {
        // Reflection ilə private constructor-u çağır (reconstitution)
        return Order::reconstitute(
            new OrderId($record->id),
            $record->customer_id,
            OrderStatus::from($record->status),
            new Money($record->total, 'USD'),
            $record->items->map(fn($i) => OrderItem::create(
                $i->product_id,
                new Quantity($i->quantity),
                new Money($i->unit_price, 'USD')
            ))->all()
        );
    }
}
```

---

## İntervyu Sualları

**1. Aggregate nədir, nəyi boundary müəyyən edir?**
Bir consistency unit olan entity-lər qrupu. Birlikdə atomik olaraq dəyişdirilir. Consistency invariantları (business rules) aggregate-i müəyyən edir: "Order total, item-lərin cəminə bərabər olmalıdır" — bu invariant Order aggregate-inin sərhədini müəyyən edir.

**2. Aggregate root nədir, niyə vacibdir?**
Aggregate-in giriş nöqtəsi. External world yalnız root vasitəsilə aggregate-ə daxil ola bilər. Root consistency-ni enforce edir. Repository yalnız root-u persist edir. External referanslar yalnız root-un ID-sinə ola bilər.

**3. Aggregate-ləri kiçik saxlamağın niyə önəmi var?**
Böyük aggregate = geniş transaction lock = az concurrency. Kiçik aggregate = dar lock = yüksək throughput. Sadəlik: böyük aggregate-lər daha çox invariant → daha çox complexity. Eventual consistency ilə fərqli aggregate-lər asınxron sinxronlaşdırıla bilər.

**4. Fərqli aggregate-lər arasında münasibət necə idarə edilir?**
ID referansı ilə (object referansı yox). Dəyişikliklər eventual consistency ilə: Domain Events → Event Bus → digər aggregate-in handler-i. Kritik workflow-lar üçün Saga Pattern.

**5. Anemic Domain Model ilə Rich Domain Model fərqi nədir?**
Anemic: sadəcə data container (getter/setter), logic service-lərdə. Rich: business logic entity-dədir (order.confirm(), order.addItem()). DDD Rich Domain Model-i tövsiyə edir — business invariantları entity-nin özü qoruyur.

**6. Aggregate-i neçə böyük olmalıdır sualına DDD cavabı nədir?**
"Mümkün qədər kiçik" — yalnız consistency invariantı tələb edən entity-lər birgə olsun. Eric Evans: "Aggregate bir transaction-da dəyişdirilən en kiçik vahid olmalıdır." Böyük aggregate-lər yüksək contention yaradır, throughput-u aşağı salır. Şübhə etdikdə ayrı aggregate-lər + eventual consistency seç.

**7. `reconstitute()` factory method nədir?**
Domain entity-lərinin iki yaradılma yolu var: `create()` — yeni entity yaradır, domain event record edir. `reconstitute()` — DB-dən yüklənmiş data-dan entity-ni yenidən qurur, event yaratmır. Bu ayrım vacibdir: persistence-dan yükləmə yeni entity "yaratmır", sadəcə state-i bərpa edir.

---

## Anti-patternlər

**1. Aggregate-ləri çox böyük dizayn etmək**
Order aggregate-inə Customer, Product, Inventory-ni daxil etmək — hər order əməliyyatında böyük transaction lock götürülür, concurrency aşağı düşür. Aggregate-ləri consistency invariantına görə kəsin: yalnız birlikdə dəyişməli olan entity-lər birgə olsun.

**2. Aggregate root-u bypass edərək child entity-lərə birbaşa giriş**
`$order->items[0]->price = 50` şəklində root vasitəsi olmadan dəyişiklik — invariant yoxlanmır, business rule pozulur. Yalnız aggregate root üzərindən dəyişiklik edin: `$order->updateItemPrice(...)`, root bütün invariantları enforce etsin.

**3. Anemic domain model — bütün logic service-lərdə**
Entity-lər yalnız getter/setter, bütün iş məntiqi `OrderService`-də — domain knowledge dağınıq, entity-nin vəziyyəti istənilən yerdən pozula bilər. Business logic-i entity-nin özünə köçürün: `order->confirm()`, `order->addItem()` — invariantlar entity-nin içindən qorunsun.

**4. Aggregate-lər arasında object referansı ilə bağlantı**
`$order->customer` — Order aggregate-inin Customer aggregate-inin tam obyektini tutması — aggregate-lər arasında sıx əlaqə, transaction boundary-lər üst-üstə düşür. Yalnız ID saxlayın: `$order->customerId` — lazy load lazımdırsa Repository vasitəsilə alın.

**5. Repository-dən aggregate root olmadan child entity yükləmək**
`OrderItemRepository::find($id)` ilə birbaşa item yükləmək — aggregate-in bütünlüyü pozulur, root-un xəbəri olmur. Repository yalnız aggregate root-u yükləsin; child entity-lərə root vasitəsilə daxil olun.

**6. Hər dəyişiklikdə bütün aggregate-i persist etmək (event-siz)**
State dəyişiklikləri log edilmir, audit trail yoxdur, replay mümkün deyil. Domain event-lər yaradın: `OrderItemAdded`, `OrderConfirmed` — hər əhəmiyyətli dəyişiklik event olaraq qeyd edilsin.
