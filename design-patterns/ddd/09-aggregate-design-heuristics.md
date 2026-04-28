# Aggregate Design Heuristics (Lead ⭐⭐⭐⭐)

## İcmal

Aggregate boundary-ni müəyyən etmək DDD-nin ən çətin hissəsidir. Çox böyük aggregate — lock contention, performance problemi. Çox kiçik — consistency qorunmur, business rule-lar dağılır. Bu fayl həmin qərarı vermək üçün practical heuristic-lər toplusudur.

```
DDD-də Aggregate — bir unit kimi treat edilən entity qrupu.
Aggregate Root xaricdən giriş nöqtəsidir.

Qaydalar:
  1. Xaricdən yalnız Aggregate Root-a müraciət edilir
  2. Aggregate daxili consistency-ni özü qoruyur
  3. Aggregate-lər arası istinad yalnız ID vasitəsilə
  4. Bir transaksiyada — bir Aggregate

Order Aggregate:
  ┌─────────────────────────────────┐
  │ Order (Aggregate Root)          │
  │   - orderId                     │
  │   - status                      │
  │   ┌─────────────────────┐       │
  │   │ OrderItem (Entity)  │       │
  │   │   - productId       │       │
  │   │   - quantity        │       │
  │   │   - price           │       │
  │   └─────────────────────┘       │
  │   ┌─────────────────────┐       │
  │   │ ShippingAddress (VO)│       │
  │   └─────────────────────┘       │
  └─────────────────────────────────┘
```

## Niyə Vacibdir

Aggregate design birbaşa sistem performance-ına təsir edir. Bir aggregate yükləndikdə bütün daxili entity-lər yüklənir, transaction lock götürülür. Aggregate nə qədər böyükdürsə, lock da o qədər geniş, concurrency o qədər aşağıdır. Digər tərəfdən, çox kiçik aggregate-lər consistency invariant-larını itirir.

## Əsas Anlayışlar

**Heuristic-lər:**

```
Heuristic 1 — Real invariant-ları qoruyun:
  "Order total $10,000-dən çox ola bilməz" → Order Aggregate
  Bu qaydanı qorumaq üçün OrderItem-lər bir Aggregate-də olmalıdır.

Heuristic 2 — Kiçik Aggregate-lər:
  Böyük Aggregate = performance problemi + lock contention
  Lazım olmayan entity-ləri xaricə çıxarın

  YANLIŞ:
  Customer Aggregate → [Profile, Orders[], Invoices[], Reviews[]]

  DÜZGÜN:
  Customer Aggregate → [Profile, ContactInfo]
  Order Aggregate    → [Items[], customerId (ref)]
  Invoice Aggregate  → [Lines[], customerId (ref)]

Heuristic 3 — Digər Aggregate-lərə ID ilə istinad:
  Order { customerId: CustomerId } // CustomerId, Customer deyil
  Lazy loading-i mümkün edir, coupling azaldır

Heuristic 4 — Eventual consistency qəbul edin:
  Bir transaksiyada bir Aggregate dəyişir.
  Digər Aggregate-lər event vasitəsilə sonradan yenilənir.

  Order.place() → OrderPlacedEvent
  InventoryService → event qulaq asır → stok azaldır (ayrı transaksiya)

Heuristic 5 — "Bu iki entity həmişə birlikdə dəyişirmi?" testi:
  Bəli → eyni Aggregate
  Xeyr → ayrı Aggregate + Event
```

**Ölçü problemi diaqnozu:**
```
Aggregate çox böyükdür əlamətləri:
  ✗ Yükləmək uzun çəkir
  ✗ Çox entity-ni lock edir
  ✗ Transaction timeout-ları
  ✗ Çoxlu invariant (hamısını qorumaq çətindir)
  ✗ Hər dəyişiklikdə böyük DB transaction

Aggregate çox kiçikdir əlamətləri:
  ✗ Invariant-lar qorunmur
  ✗ Consistency üçün distributed transaction lazımdır
  ✗ Business rule-lar Application Service-ə "sızdı"
  ✗ Bir use case-də 3-4 ayrı aggregate save edilir
```

## Praktik Baxış

**Real istifadə:**
- **E-commerce Order**: Order + OrderItem-lər bir aggregate; Customer ayrı; Product ayrı
- **Banking Account**: Account + Transaction-lar (son N) bir aggregate; bütün tarix ayrı cədvəl
- **Blog Post**: Post + Comments — Comments çox böyüyə bilər, ayrı aggregate daha yaxşı

**Trade-off-lar:**
- Böyük aggregate: güclü consistency, amma yüksək contention, yavaş yükləmə
- Kiçik aggregate: yüksək throughput, lakin eventual consistency, daha çox event

**İstifadə etməmək (böyük aggregate):**
- Yüksək concurrent yazma olan entity-lər birlikdə olmamalıdır
- Böyük collection-lar (OrderItem 1000+ olacaqsa, pagination lazımdır)

**Common mistakes:**
- Aggregate-i DB schema-ya görə dizayn etmək (domain-ə görə olmalıdır)
- "Hər şeyi bir aggregate-ə qoyum, consistency asandır" düşüncəsi
- Aggregate root-u bypass etmək (birbaşa child entity-yə müraciət)
- Aggregate-ə digər aggregate object reference-i daxil etmək

**Anti-Pattern Nə Zaman Olur?**

- **Lazım olmayan consistency boundary-lər** — "Customer, Order-a sahibdir, deməli Customer aggregate-i Order-ları içərməlidir" — bu yanlışdır. Customer-ın profil məlumatı dəyişdikdə heç bir Order dəyişmir. Birlikdə dəyişmədikləri üçün eyni aggregate-də olmaq lazım deyil. Yalnız consistency invariantı bunu tələb etdikdə birlikdə olsunlar.
- **Invariant-ları ignore etmək** — aggregate-in əsas məqsədi invariant-ları qorumaqdır. `Order::addItem()` total limitini yoxlamadan item əlavə etsə, aggregate-in heç bir mənası yoxdur. Hər `addItem()`, `removeItem()`, `confirm()` metodunu invariant yoxlaması ilə başlatın.
- **Aggregate-i DB sorğu performansına görə dizayn etmək** — "bir sorğuda hər şeyi götürmək üçün böyük aggregate" — bu performans optimizasiyasıdır, domain modeli deyil. Read side üçün CQRS tətbiq edin: read model-lər (projections, views) optimizasiya edə bilər; write side aggregate-i kiçik saxlayın.
- **Aggregate root-unu bypass etmək** — `$order->items()->where(...)->update([...])` — Eloquent birbaşa DB-yə yazır; aggregate root-un yoxlanmaları keçmir. Bütün dəyişikliklər aggregate metodları vasitəsilə edilsin; reconstitute + business method + save.

## Nümunələr

### Ümumi Nümunə

E-commerce-də `Order` aggregate dizaynı: `OrderItem`-lər yalnız Order kontekstində mənalıdır, müstəqil mövcud olmamalıdır → eyni aggregate. `Customer` müstəqil mövcuddur, öz lifecycle-ı var → ayrı aggregate, yalnız `customerId` referans. `Product` müstəqil lifecycle-a malikdir → ayrı aggregate, item-də `productId` referans.

### PHP/Laravel Nümunəsi

**Düzgün aggregate boundaries:**

```php
<?php
namespace App\Domain\Order;

class Order
{
    private OrderId    $id;
    private CustomerId $customerId;    // ID referansı — Customer object deyil ✅
    private OrderStatus $status;
    /** @var OrderItem[] */
    private array  $items = [];
    private Money  $maxAllowedTotal;
    private array  $domainEvents = [];

    private function __construct(
        OrderId    $id,
        CustomerId $customerId,
        Money      $maxAllowedTotal,
    ) {
        $this->id              = $id;
        $this->customerId      = $customerId;
        $this->status          = OrderStatus::DRAFT;
        $this->maxAllowedTotal = $maxAllowedTotal;
    }

    public static function create(CustomerId $customerId): self
    {
        return new self(
            OrderId::generate(),
            $customerId,
            Money::of(1_000_000, 'AZN'), // max 10,000 AZN
        );
    }

    public function addItem(ProductId $productId, int $qty, Money $unitPrice): void
    {
        // Invariant 1: yalnız draft order dəyişdirilə bilər
        $this->guardDraft();

        $newItem  = new OrderItem($productId, $qty, $unitPrice);
        $newTotal = $this->calculateTotal()->add($newItem->subtotal());

        // Invariant 2: max $10,000 AZN limit
        if ($newTotal->greaterThan($this->maxAllowedTotal)) {
            throw new OrderLimitExceededException(
                "Sifariş limiti: {$this->maxAllowedTotal}"
            );
        }

        $this->items[] = $newItem;

        $this->recordEvent(new ItemAddedToOrder(
            $this->id,
            $productId,
            $qty,
        ));
    }

    public function place(): void
    {
        // Invariant 3: boş sifariş place edilə bilməz
        $this->guardDraft();

        if (empty($this->items)) {
            throw new EmptyOrderException("Ən az bir məhsul lazımdır");
        }

        $this->status = OrderStatus::PLACED;
        // Domain event — InventoryService bu event-ə subscribe olub stoku azaldacaq
        $this->recordEvent(new OrderPlacedEvent($this->id, $this->customerId, $this->items));
    }

    public function cancel(string $reason): void
    {
        // Invariant 4: shipped order ləğv edilə bilməz
        if ($this->status === OrderStatus::SHIPPED) {
            throw new InvalidOrderStateException("Göndərilmiş sifariş ləğv edilə bilməz");
        }
        $this->status = OrderStatus::CANCELLED;
        $this->recordEvent(new OrderCancelled($this->id, $reason));
    }

    public function calculateTotal(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $item) => $carry->add($item->subtotal()),
            Money::zero('AZN'),
        );
    }

    private function guardDraft(): void
    {
        if ($this->status !== OrderStatus::DRAFT) {
            throw new InvalidOrderStateException("Sifariş artıq yerləşdirilib");
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
    public function items(): array { return $this->items; }
    public function customerId(): CustomerId { return $this->customerId; }
}
```

**Ölçü qərarı — heuristic application:**

```php
// SUAL: Customer Order-ları bilməlidir?

// ❌ YANLIŞ dizayn — böyük aggregate
class Customer
{
    private CustomerId $id;
    private string $name;
    /** @var Order[] */
    private array $orders = []; // ← BU PROBLEM!

    // Customer yükləndikdə bütün Order-lar yüklənir
    // Customer lock olduqda bütün Order-lar lock olur
    // "Adı dəyişmək" istəsən belə bütün Order-ları yükləmək lazımdır
}

// ✅ DÜZGÜN dizayn — ayrı aggregates, ID referans
class Customer
{
    private CustomerId $id;
    private string $name;
    private Email  $email;
    private CustomerTier $tier;
    // Order-lara referans YOX — yalnız öz data-sı ✅
}

class Order
{
    private OrderId    $id;
    private CustomerId $customerId; // ID referans ✅ — Customer object deyil
    // ...
}

// Customer-ın sifarişlərini görmək lazımdırsa → Query side
// OrderRepository::findByCustomerId(CustomerId $id)
// — write side aggregate-ini dəyişdirmir
```

**Eventual consistency testi:**

```php
// Application Service — bir transaksiyada bir aggregate
class ConfirmOrderHandler
{
    public function __construct(
        private OrderRepository $orderRepo,
        private EventBus        $eventBus,
    ) {}

    public function handle(ConfirmOrderCommand $cmd): void
    {
        $events = [];

        DB::transaction(function () use ($cmd, &$events) {
            $order = $this->orderRepo->findById(new OrderId($cmd->orderId));
            $order->confirm(); // Yalnız Order aggregate dəyişir
            $this->orderRepo->save($order);
            $events = $order->pullDomainEvents();
        }); // TX bağlandı — yalnız Order dəyişdi

        // TX-dan sonra event dispatch
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }

        // OrderConfirmedEvent → InventoryHandler → ayrı TX-da stoku azaldır
        // Bu eventual consistency-dir — iki aggregate ayrı TX-da dəyişir
    }
}
```

**Lock contention simulyasiyası — aggregate ölçüsünün əhəmiyyəti:**

```php
// ❌ Böyük aggregate — yüksək contention
class Order
{
    /** @var OrderItem[] 500+ item */
    private array $items = [];

    // Order::confirm() çağırılanda:
    // 1. Order + bütün 500 item yüklənir
    // 2. DB transaction lock götürülür
    // 3. Eyni anda 100 user order confirm etməyə çalışırsa → timeout

    public function confirm(): void
    {
        // 500 item-in hamısı validate olunur
        foreach ($this->items as $item) {
            $this->validateItem($item); // uzun əməliyyat
        }
        $this->status = OrderStatus::CONFIRMED;
    }
}

// ✅ Kiçik aggregate — aşağı contention
class Order
{
    /** @var OrderItem[] tipik olaraq 1-10 item */
    private array $items = [];
    private Money $cachedTotal; // pre-calculated

    public function confirm(): void
    {
        if (empty($this->items)) {
            throw new EmptyOrderException();
        }
        // Sadə invariant yoxlaması — sürətli, dar lock
        $this->status = OrderStatus::CONFIRMED;
        $this->recordEvent(new OrderConfirmed($this->id, $this->cachedTotal));
    }
}
```

**Aggregate boundary sualları — qərar guide:**

```php
// 5 sual hər entity üçün soruşun:

// 1. "Bu entity müstəqil mövcud ola bilərmi?"
//    OrderItem → Xeyr (Order olmadan mənası yoxdur) → eyni aggregate
//    Product   → Bəli (Catalog-da müstəqil yaşayır) → ayrı aggregate

// 2. "Bu entity-nin öz ID-si lazımdırmı?"
//    OrderItem → Lazım deyil (Order-un içindədir) → eyni aggregate
//    Customer  → Lazımdır (müstəqil axtarılır) → ayrı aggregate

// 3. "Bu iki entity həmişə eyni transaksiyada dəyişirmi?"
//    Order + OrderItem → Bəli → eyni aggregate
//    Order + Inventory → Xeyr → ayrı aggregates + Domain Event

// 4. "Bu entity-nin consistency invariantı başqa entity ilə bağlıdırmı?"
//    OrderItem subtotal → Order total ilə bağlıdır → eyni aggregate
//    Customer address   → Order-un heç bir invariantı ilə bağlı deyil → ayrı

// 5. "Bunu ayrı aggregate etmək eventual consistency tələb edirmi?"
//    Əgər bəli: acceptable? real-time lazımdır?
//    Bəli, acceptable → ayrı; Xeyr, real-time → eyni
```

## Praktik Tapşırıqlar

1. **Boundary tapın** — mövcud layihənizdə 3 entity seçin; 5 sualla hər birini keçin; aggregate membership qərarı verin; arqumentləri yazın.
2. **Giant aggregate refactor** — bir `Customer` aggregate-ini `Customer` + `Order` + `Invoice` ayrı aggregate-lərə bölün; ID referansına keçin; repository-ləri yenidən yazın; test edin.
3. **Lock contention ölç** — 100 concurrent user eyni aggregate-i yeniləsin; timeout-ları müşahidə edin; aggregate-i kiçildin; fərqi ölçün.
4. **Eventual consistency tətbiq edin** — iki aggregate arasında domain event axını qurun; post-TX dispatch tətbiq edin; at-least-once delivery testini yazın.
5. **Invariant siyahısı** — hər aggregate üçün bütün invariant-ları siyahılayın; hər invariant üçün unit test yazın; invariant pozulduqda domain exception fırlatıldığını doğrulayın.

## Əlaqəli Mövzular

- [DDD Overview](01-ddd.md) — aggregate-in DDD-dəki mövqeyi
- [Aggregates](04-ddd-aggregates.md) — aggregate implementasiya detalları
- [Value Objects](02-value-objects.md) — aggregate daxilindəki VO-lar
- [Domain Events](05-ddd-domain-events.md) — aggregate-lər arası eventual consistency
- [Domain Service vs App Service](08-domain-service-vs-app-service.md) — aggregate-i kim çağırır
- [CQRS](../integration/01-cqrs.md) — write side aggregate-lər; read side projections
- [Event Sourcing](../integration/02-event-sourcing.md) — aggregate state event-lərdən rebuild
- [Saga Pattern](../integration/03-saga-pattern.md) — multi-aggregate workflow koordinasiyası
- [Repository Pattern](../laravel/01-repository-pattern.md) — aggregate persistence qatı
