# Domain-Driven Design (DDD) (Senior ⭐⭐⭐)

## İcmal

Domain-Driven Design (DDD) — Eric Evans tərəfindən 2003-cü ildə "Domain-Driven Design: Tackling Complexity in the Heart of Software" kitabında təqdim edilmiş software dizayn yanaşmasıdır. Əsas ideyası: **proqramın strukturu business domain-i əks etdirməlidir**. Texniki qərarlar domain biliyinə əsaslanır, texnologiya seçiminə deyil.

DDD iki hissəyə bölünür:
- **Strategic DDD** — Bounded Context, Shared Kernel, Context Mapping (böyük miqyas, komanda/sistem səviyyəsi)
- **Tactical DDD** — Aggregate, Entity, Value Object, Domain Event, Repository (implementasiya səviyyəsi)

## Niyə Vacibdir

Mürəkkəb business domain-lərdə kod tez-tez texniki strukturu (DB cədvəlləri, HTTP endpoint-lər) əks etdirir, business-i deyil. Nəticə: business rule-lar onlarca service-ə dağılır, dəyişiklik əhatəsini anlamaq çətinləşir, developer ilə domain ekspert arasında "dil fərqi" yaranır. DDD bu problemi həll edir:

- **Ubiquitous Language** — developer və domain ekspert eyni dildə danışır
- **Model-Driven Design** — domain modeli kodun əsasıdır
- **Bounded Context** — mürəkkəb domain-i idarə olunan hissələrə bölür
- **Rich Domain Model** — business logic doğru yerdə — domain entity-lərindədir

## Əsas Anlayışlar

**Strategic Design:**
- **Bounded Context** — domain-in müəyyən kontekstdə sərhədlənmiş modeli; eyni termin fərqli kontekstlərdə fərqli mənaya malik ola bilər
- **Ubiquitous Language** — BC daxilindəki ortaq dil; kod, test adları, danışıq hamısı bu dili istifadə edir
- **Context Map** — BC-lər arasındakı əlaqələrin xəritəsi (Shared Kernel, Customer/Supplier, ACL, Conformist, OHS)

**Tactical Design — Building Blocks:**
- **Entity** — unikal identity-si olan domain obyekti (User, Order, Product); ID-yə görə müqayisə olunur
- **Value Object** — identity-si olmayan, dəyərinə görə müəyyən olunan immutable obyekt (Money, Email, Address)
- **Aggregate** — bir-birinə bağlı entity/VO-ların consistency boundary-si; yalnız root vasitəsilə daxil olunur
- **Aggregate Root** — aggregate-in yeganə giriş nöqtəsi; bütün dəyişikliklər buradan keçir
- **Domain Event** — domain-də baş vermiş mühüm hadisə (past tense: OrderPlaced, PaymentReceived)
- **Repository** — aggregate-ləri persistence-dən abstrakt edən interface
- **Domain Service** — heç bir entity-yə aid olmayan domain logic (stateless)
- **Application Service** — use case-ləri orkestrasiya edir (transaction, event dispatch)
- **Factory** — mürəkkəb domain obyektlərinin yaradılması

**Layered Architecture:**
```
┌─────────────────────────────────────────────┐
│           Presentation Layer                 │
│    (Controllers, API Resources, Views)       │
├─────────────────────────────────────────────┤
│           Application Layer                  │
│    (Application Services, Commands, Queries) │
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
└─────────────────────────────────────────────┘
Asılılıq istiqaməti: Yuxarıdan aşağıya.
Domain Layer heç nəyə asılı DEYİL (framework-dən belə).
```

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- E-commerce (Order, Payment, Inventory, Shipping — hər biri ayrı BC)
- Banking (Transfer, Loan, Account — mürəkkəb business rule-lar)
- Insurance (Policy, Claim, Premium — domain ekspertləri ilə sıx əməkdaşlıq)
- SaaS platformalar (Subscription, Billing, User Management — multiple BC)

**Trade-off-lar:**
- Business rule-lar domain-də mərkəzləşir, test olunur, dəyişiklik lokaldır
- Lakin: çox boilerplate (class, interface, mapping), öyrənmə əyrisi, team alignment tələb edir
- Eloquent Active Record ilə DDD-nin Data Mapper yanaşması çatışmır — mapping layer lazım olur

**İstifadə etməmək:**
- Sadə CRUD (admin paneli, settings, content management)
- Kiçik layihələr və prototiplər
- Business logic minimum olan sistemlər
- Tez dəyişən/exploration mərhələsindəki MVP-lər

**Common mistakes:**
- **Anemic Domain Model** — entity-lər yalnız getter/setter, bütün logic service-lərdə
- **Tactical DDD olmadan strategic** — aggregate yazırsan, amma BC sərhədlərini bilmirsən
- **Ubiquitous Language-ı kodda istifadə etməmək** — `processItem()`, `updateStatus(2)` kimi texniki adlar
- **Framework asılılığı domain-də** — `use Illuminate\Database\Eloquent\Model` domain entity-sinin içindəsə domain artıq pure deyil

**Anti-Pattern Nə Zaman Olur?**

- **CRUD app-a DDD tətbiq etmək** — blog post yaratmaq üçün Aggregate, Repository interface, Application Service, Domain Event, Domain Service yazmaq massive over-engineering-dir: 10x complexity, 0x benefit. DDD yalnız mürəkkəb business domain-lər üçündür — e-commerce checkout, banking transfer, insurance claim processing.
- **Anemic Domain Model** — Eloquent model-ləri yalnız getter/setter saxlayır, bütün business logic service class-lara köçürülür. Domain qaydaları dağılır, bir entity-yə aid məntiqi onlarca fayl arasında axtarmaq lazım gəlir. Business logic-i aid olduğu entity/aggregate daxilinə yerləşdirin.
- **Ubiquitous Language-ı kodda istifadə etməmək** — `processItem()`, `updateStatus(2)` kimi texniki adlar domain dilini əks etdirmir. `submitOrder()`, `approveRefund()`, `markAsShipped()` kimi domain terminlərini istifadə edin.
- **Bounded Context olmadan tactical DDD** — Aggregate yazmaq, amma BC sərhədlərini müəyyən etməmək — strategic design olmadan tactical tools kontekstsiz qalır, köhnə mürəkkəblik yeni formada davam edir.
- **DDD-ni "bütün team bilmir" şəraitdə tətbiq etmək** — DDD team alignment tələb edir. Bir developer DDD yazır, qalanları Eloquent model-ləri birbaşa dəyişirsə, domain model tez pozulur.

## Nümunələr

### Ümumi Nümunə

E-commerce sistemində "Product" fərqli BC-lərdə fərqli məna daşıyır:
- **Catalog BC**: ad, təsvir, şəkillər, qiymət, reviews
- **Ordering BC**: product_id, snapshot qiymət, quantity
- **Inventory BC**: SKU, stock miqdarı, warehouse yeri
- **Shipping BC**: çəki, ölçülər, fragile flag

Bu fərqlilik DDD-nin gücüdür: hər BC öz domain-i üçün optimal modelə malikdir.

### PHP/Laravel Nümunəsi

**Folder Strukturu (Laravel):**

```
src/
├── Domain/                          # Domain Layer
│   ├── Order/
│   │   ├── Models/
│   │   │   ├── Order.php           # Aggregate Root
│   │   │   └── OrderItem.php       # Entity
│   │   ├── ValueObjects/
│   │   │   ├── OrderNumber.php
│   │   │   └── ShippingInfo.php
│   │   ├── Events/
│   │   │   ├── OrderPlaced.php
│   │   │   └── OrderCancelled.php
│   │   ├── Repositories/
│   │   │   └── OrderRepositoryInterface.php
│   │   ├── Services/
│   │   │   └── OrderPricingService.php
│   │   └── Exceptions/
│   │       └── OrderCannotBeCancelledException.php
│   └── Shared/
│       └── ValueObjects/
│           ├── Money.php
│           ├── Email.php
│           └── Address.php
│
├── Application/                     # Application Layer
│   └── Order/
│       ├── Commands/PlaceOrderCommand.php
│       ├── Handlers/PlaceOrderHandler.php
│       └── Queries/ListOrdersQuery.php
│
├── Infrastructure/                  # Infrastructure Layer
│   └── Persistence/
│       └── EloquentOrderRepository.php
│
└── Presentation/                    # Presentation Layer
    └── Http/Controllers/OrderController.php
```

**Ubiquitous Language — Kod Nümunəsi:**

```php
// YANLIŞ — texniki dil, business anlamı yoxdur
class OrderManager
{
    public function processItem(array $data): void
    {
        $record = DB::table('orders')->insert($data);
        $this->updateStatus($record, 2); // 2 nə deməkdir?
    }
}

// DOĞRU — Ubiquitous Language istifadə
class Order
{
    public function place(): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new DomainException('Yalnız draft sifariş yerləşdirilə bilər.');
        }
        if (empty($this->items)) {
            throw new DomainException('Boş sifariş yerləşdirilə bilməz.');
        }
        $this->status = OrderStatus::Placed;
        $this->recordEvent(new OrderPlaced($this->id, $this->customerId, $this->total));
    }

    public function confirm(): void { /* ... */ }
    public function ship(TrackingNumber $trackingNumber): void { /* ... */ }
    public function cancel(CancellationReason $reason): void { /* ... */ }
}
```

**Aggregate Root + Domain Events:**

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
        private Money $total,
        private Address $shippingAddress,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function addItem(Product $product, int $quantity): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new DomainException('Yalnız draft sifarişə məhsul əlavə edilə bilər.');
        }

        $existing = $this->findItemByProduct($product->id());
        if ($existing) {
            $existing->increaseQuantity($quantity);
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

    public function cancel(CancellationReason $reason): void
    {
        $cancellable = [OrderStatus::Draft, OrderStatus::Placed, OrderStatus::Paid];

        if (!in_array($this->status, $cancellable)) {
            throw new OrderCannotBeCancelledException(
                "Status '{$this->status->value}' olan sifariş ləğv edilə bilməz."
            );
        }

        $previousStatus = $this->status;
        $this->status = OrderStatus::Cancelled;

        $this->recordEvent(new OrderCancelled(
            orderId: $this->id,
            reason: $reason,
            previousStatus: $previousStatus,
            requiresRefund: $previousStatus === OrderStatus::Paid,
        ));
    }

    private function recalculateTotals(): void
    {
        $this->subtotal = Money::zero($this->total->currency);
        foreach ($this->items as $item) {
            $this->subtotal = $this->subtotal->add($item->lineTotal());
        }
        $this->total = $this->subtotal->add($this->subtotal->percentage(18));
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

**Repository Pattern:**

```php
// Domain/Order/Repositories/OrderRepositoryInterface.php (Domain Layer)
interface OrderRepositoryInterface
{
    public function findById(OrderId $id): Order;
    public function findByCustomerId(CustomerId $customerId): array;
    public function save(Order $order): void;
    public function nextId(): OrderId;
}

// Infrastructure/Persistence/EloquentOrderRepository.php
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        $model = OrderModel::updateOrCreate(
            ['id' => $order->id()->value],
            [
                'customer_id' => $order->customerId()->value,
                'status'      => $order->status()->value,
                'subtotal'    => $order->subtotal()->amount,
                'total'       => $order->total()->amount,
                'currency'    => $order->total()->currency->code,
            ],
        );

        $model->items()->delete();
        foreach ($order->items() as $item) {
            $model->items()->create([
                'product_id'   => $item->productId()->value,
                'product_name' => $item->productName(),
                'unit_price'   => $item->unitPrice()->amount,
                'quantity'     => $item->quantity(),
            ]);
        }

        // Domain events-ləri dispatch et
        foreach ($order->pullDomainEvents() as $event) {
            event($event);
        }
    }
}

// Service Provider-da binding
$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
```

## Praktik Tapşırıqlar

1. **Ubiquitous Language audit** — mövcud Laravel layihənizdəki controller/service method adlarını götürün; hansıları domain dili, hansıları texniki termindir? Bir sadə aggregate refactor edin.
2. **İlk Aggregate** — sadə `Order` domain entity-si yaradın (Eloquent-siz): `place()`, `cancel()`, `addItem()` metodları; business rule-lar entity daxilindədir; unit test yazın.
3. **Repository ayrılması** — `OrderRepositoryInterface` domain layer-da yaradın; `EloquentOrderRepository` infrastructure-da implement edin; Service Provider-da bind edin.
4. **Domain Event flow** — `Order::place()` çağrıldıqda `OrderPlaced` event yaranır; repository save edəndə events dispatch olunur; `SendOrderConfirmationEmail` handler yazın.
5. **Bounded Context mapping** — mövcud sisteminizdəki 3-4 domain-i götürün; Context Map çəkin; "Product" hər kontekstdə nə deməkdir? Ubiquitous Language fərqini tapın.

## Əlaqəli Mövzular

- [Value Objects](02-value-objects.md) — domain model-in immutable building block-ları
- [DDD Patterns](03-ddd-patterns.md) — tactical pattern-lərin ətraflı izahı
- [Aggregates](04-ddd-aggregates.md) — consistency boundary-lər
- [Domain Events](05-ddd-domain-events.md) — aggregate-lərarası kommunikasiya
- [Bounded Context](06-ddd-bounded-context.md) — strategic DDD
- [Repository Pattern](../laravel/01-repository-pattern.md) — Laravel-də implementasiya
- [Service Layer](../laravel/02-service-layer.md) — application service qatı
- [CQRS](../integration/01-cqrs.md) — DDD ilə tez-tez birlikdə istifadə
- [Event Sourcing](../integration/02-event-sourcing.md) — domain events-i primary storage kimi
- [Hexagonal Architecture](../architecture/05-hexagonal-architecture.md) — domain-i framework-dən izolyasiya
- [Onion Architecture](../architecture/06-onion-architecture.md) — domain-centric layering
- [Clean Architecture](../architecture/12-clean-architecture.md) — framework-agnostic domain
