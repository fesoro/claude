# Domain-Driven Design (DDD) (Lead ⭐⭐⭐⭐)

## İcmal
Domain-Driven Design (DDD) Eric Evans-ın 2003-cü ildə "Domain-Driven Design: Tackling Complexity in the Heart of Software" kitabında təqdim etdiyi, mürəkkəb biznes domenini kodda düzgün modelləşdirməyə yönəlmiş yanaşmadır. Interview-da bu mövzu Lead/Architect səviyyəli mövqelər üçün çıxır — çünki DDD yalnız pattern deyil, düşüncə tərzidir. Kandidatın domain complexity ilə necə başa çıxdığını, Bounded Context-ləri necə müəyyən etdiyini ölçür.

## Niyə Vacibdir
DDD bilmək senior mühəndisin sadəcə kod yazdığını yox, biznes problemlərini həll etdiyini göstərir. Microservices arxitekturasında service boundary-ləri DDD Bounded Context-lərinə əsaslanır. Böyük sistemlərdə domain complexity-ni idarə etmədən codebase "big ball of mud"-a çevrilir — hər şey hər şeylə əlaqəli olur, dəyişiklik etmək getdikcə çətinləşir. DDD-ni başa düşən mühəndis product owner ilə eyni dildə danışa bilir, bu isə team üretkenliyi artırır.

## Əsas Anlayışlar

- **Ubiquitous Language**: Domain expert-ləri və developerların eyni terminologiyadan istifadə etməsi. "Order" sözü hamı üçün eyni şeyi bildirir. Kod business domain-dəki terminləri əks etdirir — `customer.placeOrder()` çağrısı business prosesin özünü ifadə edir.

- **Bounded Context**: Müəyyən bir domain modelinin etibarlı olduğu kontekst sərhədi. Eyni "Customer" sözü Billing context-ində ödəmə məlumatları daşıyır, Shipping context-ində çatdırılma ünvanı daşıyır, CRM context-ində satış tarixi daşıyır. Fərqli kontekstlər — fərqli modellər.

- **Domain Model**: Biznes qaydalarını əks etdirən obyekt modeli. Persistence, UI, network — bunlardan asılı olmayaraq domain-in özünü ifadə edir.

- **Entity**: Unikal identifikatorla tanınan domain obyekti. Order, User, Product — eyni atributlarla iki fərqli entity ola bilər (ID-ləri fərqlidir). Entity-nin dəyəri vaxtla dəyişə bilər, amma identitor dəyişmir.

- **Value Object**: Identifikatoru olmayan, yalnız dəyəri ilə tanınan obyekt. Money, Address, Email, DateRange — immutable olmalıdır, equality dəyəri üzərindən müəyyən edilir. `new Money(100, 'USD')` əvəzinə plain int istifadə etmək type safety-ni itirir.

- **Aggregate**: Birlikdə dəyişən entity-lər qrupu. Bir root entity (Aggregate Root) vasitəsilə idarə olunur. Invariant-ları qoruyur — "Order-un cəmi mənfi ola bilməz" kimi qaydalar Aggregate Root tərəfindən tətbiq edilir.

- **Aggregate Root**: Aggregate-ə yeganə giriş nöqtəsi. Xaricdən yalnız root-a müraciət olunur. `orderItem.setPrice()` əvəzinə `order.updateItemPrice(itemId, newPrice)` — bu şəkildə invariant-lar qorunur.

- **Aggregate size qərarı**: Kiçik aggregate-lər daha az lock contention, daha az memory istifadəsi. Böyük aggregate-lər daha az eventual consistency, daha az complexity. Qayda: Aggregate boundary = transaction boundary. Eyni transactionda dəyişən şeylər eyni aggregate-də olmalıdır.

- **Domain Event**: Domain-də baş verən vacib hadisə. `OrderPlaced`, `PaymentFailed`, `UserRegistered`. Event-lar immutable-dır, keçmişdə baş vermiş faktı ifadə edir. Aggregate-lər arası kommunikasiya — bir aggregate event publish edir, digəri subscribe olur.

- **Repository**: Aggregate-ləri yaddaşdan əldə etmək üçün abstraksiya. Collection kimi davranır — `findById`, `save`, `findAll`. SQL, MongoDB, ya da in-memory implementasiya ola bilər — domain kodunu persistence detallarından qoruyur.

- **Domain Service**: Entity-ə aid olmayan, amma domain logic-i olan əməliyyatlar. `PricingService.calculateDiscount(order, coupon)` — bu logic nə Order-ə, nə Coupon-a aid deyil, amma domain-dədir.

- **Application Service**: Use case orkestrasionu — domain logic yoxdur, yalnız koordinasiya. Repository-dən aggregate al, domain metodunu çağır, event-ləri publish et, transaction-ı idarə et.

- **Anti-corruption Layer (ACL)**: Xarici sistem ilə integration zamanı domain modelini qorumaq üçün translation layer. Legacy sistem ya da third-party API-nin terminologiyası domain modelinizə qarışmasın deyə ACL translation edir.

- **Context Map**: Bounded Context-lər arasındakı əlaqəni göstərən xəritə. Partnership, Customer-Supplier, Conformist, Anti-corruption Layer, Open Host Service, Published Language — context-lər arası münasibət növləri.

- **Strategic DDD**: Bounded Context-ləri, Context Map-i müəyyən etmək — high-level design. Team-lər arasında məsuliyyət bölgüsü. Microservice boundary-ləri burada müəyyən edilir.

- **Tactical DDD**: Entity, Value Object, Aggregate, Repository — implementation-level patterns. Strategic DDD olmadan tactical DDD patterns-ı tətbiq etmək çox vaxt "over-engineered CRUD" olur.

- **Anemic Domain Model anti-pattern**: Entity-lər yalnız getter/setter-dən ibarətdir, bütün biznes logic service-lərə keçmişdir. DDD-nin əksi — domain modelləri behavior daşımır, yalnız data daşıyır.

- **Domain Event Sourcing ilə əlaqə**: Domain event-ləri persist edib state-i event-lərdən reconstruct etmək — Event Sourcing. DDD ilə çox yaxşı uyğun gəlir — hər state dəyişikliyi bir event-dir.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
DDD mövzusunda ən güclü cavab konkret domain nümunəsi ilə verilir. "E-commerce sistemində Order Aggregate-ni necə modelləşdirərdiniz?" sualına struktur cavab verin: Aggregate Root nədir, hansı Entity-lər var, Value Object-lər hansılardır, Domain Event-lər nə vaxt fire olunur. Strategic vs Tactical DDD ayrımını bilmək əla cavabı yaxşıdan ayırır.

**Junior-dan fərqlənən senior cavabı:**
Junior: "Entity, Value Object, Repository nədir" — pattern adlarını sıralayır.
Senior: "Order Aggregate-nin boundary-sini müəyyən edərkən transaction boundary-ni nəzərə alıram — Order item-ı dəyişdirəndə Order-un da dəyişməsi lazımdır, ona görə eyni aggregate-dədirlər."
Lead: "Bounded Context-ləri event storming workshop ilə domain expert-lərlə birlikdə müəyyən edirəm. Ubiquitous Language-i yaratmaq üçün business analyst-larla həftəlik vocabulary review keçirirəm."

**Follow-up suallar:**
- "Bounded Context-ləri necə müəyyən edirsiniz?"
- "Aggregate size-ı necə qərar verirsiniz — böyük mi kiçik mi?"
- "Context-lər arasında kommunikasiya necə həyata keçirilir?"
- "DDD-nin microservices ilə əlaqəsi nədir?"
- "Anemic Domain Model nədir, niyə anti-pattern-dir?"
- "Event Storming nədir, nə üçün istifadə olunur?"

**Ümumi səhvlər:**
- Hər class-ı Entity kimi modelləşdirmək — Value Object-i laqeyd buraxmaq. `Money`, `Email`, `Address` Value Object olmalıdır.
- Aggregate-ləri çox böyük etmək — bütün əlaqəli obyektləri bir Aggregate-ə toplamaq (performance problemi, lock contention).
- Repository-ni database abstraction kimi düşünmək — əslində collection semantics var. `getAll()` yox, `findByStatus(Status $status)` — domain language-də.
- Ubiquitous Language-i görmürdən keçmək — kod terminologiyası ilə biznes terminologiyası fərqlidirsə bu DDD deyil.
- Application Service ilə Domain Service-i qarışdırmaq. Application Service: "find order, cancel it, send event". Domain Service: "calculate discount based on loyalty program rules".
- Tactical DDD-ni strategic DDD-siz tətbiq etmək — Aggregate yaratmaq amma Bounded Context-lər düşünülməmək.

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab Strategic DDD-ni (Bounded Context, Context Map) taktiki DDD ilə (Entity, Aggregate) birlikdə izah edir. Real layihədə necə tətbiq etdiyini, hansı çətinliklərlə qarşılaşdığını danışır. "Biz event storming keçirib 5 Bounded Context müəyyən etdik, amma sonra anladıq ki, Billing və Subscription eyni context-dədir" — bu real təcrübədir.

## Nümunələr

### Tipik Interview Sualı
"E-commerce sistemini DDD ilə necə modelləşdirərdiniz? Order Aggregate-ni izah edin."

### Güclü Cavab
"Əvvəlcə Bounded Context-ləri müəyyən edərdim: Order Management, Inventory, Payment, Shipping, Notification — hər biri öz domain modelinə malikdir. Order Management context-ində Order Aggregate Root-dur. Ona OrderItem-lər bağlıdır — bunlar ayrıca yaşaya bilməz. Address, Money Value Object-dir — immutable, equality dəyəri üzərindən. Order.place() əməliyyatı çağrıldıqda OrderPlaced domain event-i fire olur. Bu event Inventory context-inə asynchronous olaraq çatdırılır. Shipping context-i ACL ilə xarici courier API-si ilə danışır — courier-in terminologiyasının domain modelimizə sızmasına icazə vermirik."

### Kod Nümunəsi

```php
// Value Object — immutable, equality by value
final class Money
{
    public function __construct(
        public readonly int $amountInCents,  // cents-də saxla, float yox
        public readonly string $currency     // ISO 4217: 'USD', 'EUR'
    ) {
        if ($amountInCents < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Invalid currency code');
        }
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        if ($other->amountInCents > $this->amountInCents) {
            throw new \DomainException('Cannot subtract larger amount');
        }
        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    public function multiplyBy(int $multiplier): self
    {
        return new self($this->amountInCents * $multiplier, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amountInCents === $other->amountInCents
            && $this->currency === $other->currency;
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountInCents > $other->amountInCents;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }
}

// Entity — unikal identity var, state dəyişə bilər
class OrderItem
{
    private function __construct(
        private readonly OrderItemId $id,
        private readonly ProductId $productId,
        private readonly string $productName,  // snapshot — product adı sonra dəyişsə belə qalır
        private int $quantity,
        private readonly Money $unitPrice       // snapshot — qiymət sonra dəyişsə belə qalır
    ) {}

    public static function create(
        ProductId $productId,
        string $productName,
        int $quantity,
        Money $unitPrice
    ): self {
        if ($quantity <= 0) {
            throw new \DomainException('Quantity must be positive');
        }
        if ($unitPrice->amountInCents <= 0) {
            throw new \DomainException('Unit price must be positive');
        }
        return new self(OrderItemId::generate(), $productId, $productName, $quantity, $unitPrice);
    }

    public function subtotal(): Money
    {
        return $this->unitPrice->multiplyBy($this->quantity);
    }

    public function productId(): ProductId { return $this->productId; }
    public function id(): OrderItemId { return $this->id; }
    public function quantity(): int { return $this->quantity; }
}

// Aggregate Root — invariant-ları qoruyur, domain event-lər buradan çıxır
class Order
{
    private OrderStatus $status;
    /** @var OrderItem[] */
    private array $items = [];
    private array $domainEvents = [];
    private ?Money $appliedDiscount = null;

    private function __construct(
        private readonly OrderId $id,
        private readonly CustomerId $customerId,
        private readonly \DateTimeImmutable $placedAt,
        private readonly string $currency
    ) {
        $this->status = OrderStatus::Draft;
    }

    // Factory method — konstruktor private, business intent aydındır
    public static function place(
        CustomerId $customerId,
        array $items,
        string $currency = 'USD'
    ): self {
        if (empty($items)) {
            throw new \DomainException('Order must have at least one item');
        }

        $order = new self(
            OrderId::generate(),
            $customerId,
            new \DateTimeImmutable(),
            $currency
        );

        foreach ($items as $item) {
            $order->addItem($item);
        }

        $order->status = OrderStatus::Placed;

        // Domain Event — "bir şey baş verdi" faktı
        $order->recordEvent(new OrderPlaced(
            $order->id,
            $customerId,
            $order->total(),
            $order->placedAt
        ));

        return $order;
    }

    private function addItem(OrderItem $item): void
    {
        // Invariant: eyni product-dan iki dəfə olmaz
        foreach ($this->items as $existing) {
            if ($existing->productId()->equals($item->productId())) {
                throw new \DomainException(
                    "Product {$item->productId()} already in order. Use updateQuantity instead."
                );
            }
        }
        $this->items[] = $item;
    }

    public function applyDiscount(Money $discount): void
    {
        if ($discount->isGreaterThan($this->subtotal())) {
            throw new \DomainException('Discount cannot exceed order subtotal');
        }
        if ($this->status !== OrderStatus::Placed) {
            throw new \DomainException('Cannot apply discount to non-placed order');
        }
        $this->appliedDiscount = $discount;
        $this->recordEvent(new DiscountApplied($this->id, $discount));
    }

    public function confirm(): void
    {
        if ($this->status !== OrderStatus::Placed) {
            throw new \DomainException("Cannot confirm order in status: {$this->status->value}");
        }
        $this->status = OrderStatus::Confirmed;
        $this->recordEvent(new OrderConfirmed($this->id));
    }

    public function cancel(string $reason): void
    {
        if (!$this->status->canBeCancelled()) {
            throw new \DomainException("Cannot cancel order in status: {$this->status->value}");
        }
        $this->status = OrderStatus::Cancelled;
        $this->recordEvent(new OrderCancelled($this->id, $reason));
    }

    public function subtotal(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $item) => $carry->add($item->subtotal()),
            Money::zero($this->currency)
        );
    }

    public function total(): Money
    {
        $subtotal = $this->subtotal();
        if ($this->appliedDiscount !== null) {
            return $subtotal->subtract($this->appliedDiscount);
        }
        return $subtotal;
    }

    public function id(): OrderId { return $this->id; }
    public function status(): OrderStatus { return $this->status; }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    // Application Service event-ləri pull edib publish edir
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}

// Repository — collection semantics, persistence detalları gizlənir
interface OrderRepository
{
    public function findById(OrderId $id): ?Order;
    public function save(Order $order): void;
    /** @return Order[] */
    public function findByCustomer(CustomerId $customerId): array;
    /** @return Order[] */
    public function findByStatus(OrderStatus $status, int $limit = 100): array;
}

// Application Service — use case orkestrasionu, domain logic yoxdur
class PlaceOrderUseCase
{
    public function __construct(
        private OrderRepository $orders,
        private ProductRepository $products,
        private EventBus $eventBus
    ) {}

    public function execute(PlaceOrderCommand $command): OrderId
    {
        // Domain obyektlərini qur
        $items = [];
        foreach ($command->items as $itemDto) {
            $product = $this->products->findById(ProductId::from($itemDto->productId));
            if ($product === null) {
                throw new ProductNotFoundException($itemDto->productId);
            }

            $items[] = OrderItem::create(
                $product->id(),
                $product->name(),
                $itemDto->quantity,
                $product->price()
            );
        }

        // Domain logic — Order.place() invariant-ları yoxlayır
        $order = Order::place(
            CustomerId::from($command->customerId),
            $items
        );

        // Persist
        $this->orders->save($order);

        // Domain Event-ləri publish et
        foreach ($order->pullDomainEvents() as $event) {
            $this->eventBus->publish($event);
        }

        return $order->id();
    }
}

// Domain Service — entity-ə aid olmayan domain logic
class LoyaltyDiscountService
{
    public function calculateDiscount(Order $order, Customer $customer): ?Money
    {
        // Bu logic nə Order-ə, nə Customer-ə aiddir — ayrı service-dədir
        $loyaltyPoints = $customer->loyaltyPoints();
        $orderTotal = $order->total();

        if ($loyaltyPoints >= 1000 && $orderTotal->amountInCents >= 10000) {
            // 10% discount for loyal customers on orders $100+
            return new Money(
                intval($orderTotal->amountInCents * 0.10),
                $orderTotal->currency
            );
        }

        return null;
    }
}

// Anti-Corruption Layer — xarici sistemdən domain modelini qoruyur
class ShippingACL
{
    private ExternalCourierAPI $courierApi; // xarici sistem

    public function createShipment(Order $order, Address $destination): ShipmentId
    {
        // External API-nin terminology-sini domain-ə çeviririk
        $externalRequest = [
            'parcel_weight'    => $this->calculateWeight($order),
            'delivery_address' => $this->toExternalAddress($destination),
            'reference_num'    => $order->id()->toString(), // external "reference_num" = domain "OrderId"
            'service_level'    => 'STANDARD',
        ];

        $response = $this->courierApi->createParcel($externalRequest);

        // External "parcel_id" → domain ShipmentId
        return ShipmentId::from($response['parcel_id']);
    }

    private function toExternalAddress(Address $address): array
    {
        // Domain Address → External API format
        return [
            'line1'       => $address->street(),
            'postal_code' => $address->zipCode(),
            'country'     => $address->countryCode()->value,
        ];
    }
}
```

### Müqayisə Cədvəli — DDD Konseptləri

| Konsept | Məqsəd | Nümunə |
|---------|--------|--------|
| Entity | Identity ilə tanınır, state dəyişə bilər | Order, User, Product |
| Value Object | Dəyərlə tanınır, immutable | Money, Address, Email |
| Aggregate Root | Invariant-ları qoruyur, giriş nöqtəsi | Order (→ OrderItem-ləri idarə edir) |
| Domain Event | Baş verən faktı ifadə edir | OrderPlaced, PaymentFailed |
| Repository | Collection semantics, persistence abstract | OrderRepository |
| Domain Service | Entity-ə aid olmayan domain logic | LoyaltyDiscountService |
| Application Service | Use case koordinasiyası | PlaceOrderUseCase |
| Anti-corruption Layer | Xarici sistem izolasiyası | ShippingACL |

## Praktik Tapşırıqlar

1. Öz layihənizdə Bounded Context-ləri müəyyən edin — neçə context var? Aralarındakı münasibət Customer-Supplier mı, Partnership mı?
2. Bir plain integer/string kimi saxladığınız dəyəri Value Object-ə çevirin (məs: `$price` → `Money`). Nə dəyişdi?
3. Aggregate Root-un invariant-larını sıralayın — hansı biznes qaydaları var? Bu qaydalar kim tərəfindən tətbiq edilir?
4. "Anemic Domain Model" anti-pattern-ini öz kodunuzda tapın — service-lər domain logic daşıyır, entity-lər yalnız getter/setter.
5. Event Storming workshop keçirin: domain expert ilə birlikdə sticky note-larla domain event-ləri müəyyən edin.
6. ACL yazın: xarici payment gateway-in response-unu domain Payment object-ə çevirin.
7. Bounded Context-lər arasında async communication implement edin: `OrderPlaced` event-i Inventory context-i consume etsin.
8. Domain Service vs Application Service ayrımını real kod nümunəsi ilə izah edin.

## Əlaqəli Mövzular

- `01-monolith-vs-microservices.md` — Bounded Context = Service boundary
- `05-event-sourcing.md` — Domain Event-lərlə birlikdə Event Sourcing
- `06-cqrs-architecture.md` — DDD + CQRS kombinasiyası
- `03-clean-architecture.md` — Domain layer izolasiyası
- `11-saga-pattern.md` — Context-lər arası koordinasiya
