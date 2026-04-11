# Onion Architecture — Dərin İzah

## Mündəricat
1. [Onion Architecture nədir?](#onion-architecture-nədir)
2. [Dependency Rule](#dependency-rule)
3. [Layer-lər Ətraflı](#layer-lər-ətraflı)
4. [PHP Nümunəsi — Sifariş Vermə](#php-nümunəsi--sifariş-vermə)
5. [Laravel Folder Strukturu](#laravel-folder-strukturu)
6. [Hər Layer-i Test Etmək](#hər-layeri-test-etmək)
7. [Onion vs Clean Architecture](#onion-vs-clean-architecture)
8. [Ümumi Səhvlər](#ümumi-səhvlər)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Onion Architecture nədir?

Jeffrey Palermo tərəfindən 2008-ci ildə təqdim edildi. Məqsəd: **domain logic-i kənar dünyadan tamamilə izolə etmək**.

```
         ┌─────────────────────────────────────────────┐
         │              Infrastructure                 │
         │   (DB, Email, Message Queue, HTTP Client)   │
         │   ┌─────────────────────────────────────┐   │
         │   │        Application Services         │   │
         │   │    (Use Cases, Orchestration)        │   │
         │   │   ┌───────────────────────────────┐ │   │
         │   │   │       Domain Services         │ │   │
         │   │   │  (Cross-entity domain logic)  │ │   │
         │   │   │  ┌─────────────────────────┐  │ │   │
         │   │   │  │      Domain Model       │  │ │   │
         │   │   │  │  Entities, Value Objects │  │ │   │
         │   │   │  │  Domain Events, Aggr.   │  │ │   │
         │   │   │  └─────────────────────────┘  │ │   │
         │   │   └───────────────────────────────┘ │   │
         │   └─────────────────────────────────────┘   │
         └─────────────────────────────────────────────┘
                         
         Asılılıq istiqaməti: Həmişə içəriyə ↓
         
         Infrastructure → Application → Domain Services → Domain Model
```

---

## Dependency Rule

**Qızıl qayda:** Daha kənardakı qat içəridəkindən asılı ola bilər. Əksi olmaz.

```
✅ İzin verilən:
Infrastructure  →  Application Services
Application     →  Domain Services
Domain Services →  Domain Model
Infrastructure  →  Domain Model

❌ QADAĞAN:
Domain Model    →  Application Services    (XƏTA!)
Domain Model    →  Infrastructure          (XƏTA!)
Domain Services →  Infrastructure          (XƏTA!)
Application     →  Infrastructure (birbaşa) (XƏTA!)
```

**Bu qaydanı interface-larla tətbiq etmək:**

```php
// Domain layer-də interface TƏRIF edilir
namespace App\Domain\Order\Repositories;

interface OrderRepository  // ← Domain layerda
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
}

// Infrastructure layer-də implementasiya
namespace App\Infrastructure\Persistence\Order;

use App\Domain\Order\Repositories\OrderRepository;

class EloquentOrderRepository implements OrderRepository  // ← Infrastructure
{
    // ...
}

// Application layer interface-dən asılıdır (implementasiyadan yox!)
namespace App\Application\Order;

class PlaceOrderService
{
    public function __construct(
        private OrderRepository $repo  // ← Interface (Domain layer)
    ) {}
}
```

---

## Layer-lər Ətraflı

### 1. Domain Model (Ən iç)

**Heç bir xarici asılılıq yoxdur** — nə Laravel, nə Eloquent, nə PDO.

***Heç bir xarici asılılıq yoxdur** — nə Laravel, nə Eloquent, nə PDO üçün kod nümunəsi:*
```php
namespace App\Domain\Order;

// Entity
class Order
{
    private array $domainEvents = [];
    
    private function __construct(
        private readonly OrderId $id,
        private readonly CustomerId $customerId,
        private OrderStatus $status,
        private Money $total,
        private array $items = []
    ) {}
    
    public static function create(CustomerId $customerId): self
    {
        $order = new self(
            OrderId::generate(),
            $customerId,
            OrderStatus::DRAFT,
            Money::zero('AZN')
        );
        $order->domainEvents[] = new OrderCreated($order->id, $customerId);
        return $order;
    }
    
    public function addItem(Product $product, int $quantity): void
    {
        if ($this->status !== OrderStatus::DRAFT) {
            throw new \DomainException('Yalnız DRAFT sifarişə məhsul əlavə edilə bilər');
        }
        
        $item = new OrderItem($product->id(), $product->name(), $product->price(), $quantity);
        $this->items[] = $item;
        $this->total = $this->total->add($product->price()->multiply($quantity));
    }
    
    public function confirm(): void
    {
        if (empty($this->items)) {
            throw new \DomainException('Boş sifariş təsdiqlənə bilməz');
        }
        $this->status = OrderStatus::CONFIRMED;
        $this->domainEvents[] = new OrderConfirmed($this->id, $this->total);
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
}

// Value Object
class Money
{
    private function __construct(
        private readonly int $amount,   // Sentdə saxlanır (100 = 1.00 AZN)
        private readonly string $currency
    ) {}
    
    public static function of(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }
    
    public static function zero(string $currency = 'AZN'): self
    {
        return new self(0, $currency);
    }
    
    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Fərqli valyutalar toplanmaz');
        }
        return new self($this->amount + $other->amount, $this->currency);
    }
    
    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }
    
    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}

// Domain Event
class OrderConfirmed
{
    public readonly \DateTimeImmutable $occurredAt;
    
    public function __construct(
        public readonly OrderId $orderId,
        public readonly Money $total
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
```

### 2. Domain Services

Bir neçə aggregate/entity ilə iş görən domain məntiqi:

*Bir neçə aggregate/entity ilə iş görən domain məntiqi üçün kod nümunəsi:*
```php
namespace App\Domain\Order\Services;

class OrderDiscountService
{
    public function applyDiscount(Order $order, Coupon $coupon): Money
    {
        if (!$coupon->isValidFor($order)) {
            throw new \DomainException('Kupon bu sifarişə tətbiq edilə bilməz');
        }
        
        return $coupon->calculateDiscount($order->total());
    }
}

// Repository interface-ları Domain layerda tərif edilir
interface OrderRepository
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function findByCustomer(CustomerId $id): array;
    public function nextIdentity(): OrderId;
}

interface ProductRepository
{
    public function findById(ProductId $id): ?Product;
    public function findByIds(array $ids): array;
}
```

### 3. Application Services

Use case-lər, xarici dünya ilə koordinasiya:

*Use case-lər, xarici dünya ilə koordinasiya üçün kod nümunəsi:*
```php
namespace App\Application\Order;

class PlaceOrderService
{
    public function __construct(
        private OrderRepository $orderRepo,
        private ProductRepository $productRepo,
        private OrderDiscountService $discountService,
        private EventDispatcher $eventDispatcher
    ) {}
    
    public function handle(PlaceOrderCommand $command): OrderId
    {
        // 1. Domain obyektlərini yüklə
        $products = $this->productRepo->findByIds($command->productIds());
        
        // 2. Domain logic-i çağır
        $order = Order::create($command->customerId());
        
        foreach ($command->items() as $item) {
            $product = $products[$item['product_id']];
            $order->addItem($product, $item['quantity']);
        }
        
        if ($command->couponCode()) {
            $coupon = $this->couponRepo->findByCode($command->couponCode());
            $discount = $this->discountService->applyDiscount($order, $coupon);
            $order->applyDiscount($discount);
        }
        
        $order->confirm();
        
        // 3. Persist et
        $this->orderRepo->save($order);
        
        // 4. Domain event-ləri dispatch et
        foreach ($order->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
        
        return $order->id();
    }
}
```

### 4. Infrastructure (Ən kənar)

*4. Infrastructure (Ən kənar) üçün kod nümunəsi:*
```php
namespace App\Infrastructure\Persistence\Order;

use App\Domain\Order\Order;
use App\Domain\Order\OrderId;
use App\Domain\Order\Repositories\OrderRepository;

class EloquentOrderRepository implements OrderRepository
{
    public function save(Order $order): void
    {
        OrderModel::updateOrCreate(
            ['id' => $order->id()->value()],
            $this->toArray($order)
        );
        
        // Child entity-ləri də saxla
        OrderModel::find($order->id()->value())
            ->items()
            ->delete();
            
        foreach ($order->items() as $item) {
            OrderItemModel::create([
                'order_id'   => $order->id()->value(),
                'product_id' => $item->productId()->value(),
                'quantity'   => $item->quantity(),
                'price'      => $item->price()->amount(),
            ]);
        }
    }
    
    public function findById(OrderId $id): ?Order
    {
        $model = OrderModel::with('items')->find($id->value());
        return $model ? $this->toDomain($model) : null;
    }
    
    private function toDomain(OrderModel $model): Order
    {
        // Eloquent model → Domain Entity çevirməsi (Mapper pattern)
        return Order::reconstitute(
            OrderId::of($model->id),
            CustomerId::of($model->customer_id),
            OrderStatus::from($model->status),
            Money::of($model->total_amount, $model->currency),
            array_map(
                fn($item) => OrderItem::reconstitute(
                    ProductId::of($item->product_id),
                    $item->product_name,
                    Money::of($item->price, $model->currency),
                    $item->quantity
                ),
                $model->items->all()
            )
        );
    }
}
```

---

## Laravel Folder Strukturu

```
app/
├── Domain/
│   ├── Order/
│   │   ├── Entities/
│   │   │   └── Order.php
│   │   ├── ValueObjects/
│   │   │   ├── OrderId.php
│   │   │   ├── Money.php
│   │   │   └── OrderStatus.php
│   │   ├── Events/
│   │   │   ├── OrderCreated.php
│   │   │   └── OrderConfirmed.php
│   │   ├── Services/
│   │   │   └── OrderDiscountService.php
│   │   └── Repositories/          ← Interfaces burada
│   │       └── OrderRepository.php
│   └── Product/
│       └── ...
│
├── Application/
│   └── Order/
│       ├── Commands/
│       │   └── PlaceOrderCommand.php
│       └── Services/
│           └── PlaceOrderService.php
│
├── Infrastructure/
│   ├── Persistence/
│   │   └── Order/
│   │       ├── EloquentOrderRepository.php
│   │       ├── Mappers/
│   │       │   └── OrderMapper.php
│   │       └── Models/
│   │           └── OrderModel.php
│   └── Messaging/
│       └── LaravelEventDispatcher.php
│
└── Presentation/
    └── Http/
        └── Order/
            ├── OrderController.php
            └── Requests/
                └── PlaceOrderRequest.php
```

**Service Provider:**

```php
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Interface → Implementation binding
        $this->app->bind(
            OrderRepository::class,
            EloquentOrderRepository::class
        );
        
        $this->app->bind(
            EventDispatcher::class,
            LaravelEventDispatcher::class
        );
    }
}
```

---

## Hər Layer-i Test Etmək

*Hər Layer-i Test Etmək üçün kod nümunəsi:*
```php
// Domain Model testi — heç bir mock lazım deyil!
class OrderTest extends TestCase
{
    public function test_can_add_item_to_draft_order(): void
    {
        $order = Order::create(CustomerId::of('customer-1'));
        $product = new Product(ProductId::of('prod-1'), 'Laptop', Money::of(100000, 'AZN'));
        
        $order->addItem($product, 2);
        
        $this->assertEquals(Money::of(200000, 'AZN'), $order->total());
    }
    
    public function test_cannot_confirm_empty_order(): void
    {
        $this->expectException(\DomainException::class);
        
        $order = Order::create(CustomerId::of('customer-1'));
        $order->confirm();
    }
}

// Application Service testi — mock-larla
class PlaceOrderServiceTest extends TestCase
{
    public function test_places_order_successfully(): void
    {
        $orderRepo = $this->createMock(OrderRepository::class);
        $productRepo = $this->createMock(ProductRepository::class);
        $dispatcher = $this->createMock(EventDispatcher::class);
        
        $product = new Product(ProductId::of('prod-1'), 'Laptop', Money::of(100000, 'AZN'));
        $productRepo->method('findByIds')->willReturn(['prod-1' => $product]);
        $orderRepo->expects($this->once())->method('save');
        
        $service = new PlaceOrderService($orderRepo, $productRepo, $dispatcher);
        $orderId = $service->handle(new PlaceOrderCommand(...));
        
        $this->assertNotNull($orderId);
    }
}

// Infrastructure testi — real DB (integration test)
class EloquentOrderRepositoryTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_saves_and_retrieves_order(): void
    {
        $repo = new EloquentOrderRepository();
        $order = Order::create(CustomerId::of('customer-1'));
        
        $repo->save($order);
        $found = $repo->findById($order->id());
        
        $this->assertNotNull($found);
        $this->assertEquals($order->id(), $found->id());
    }
}
```

---

## Onion vs Clean Architecture

```
┌─────────────────────────┬─────────────────┬──────────────────┐
│                         │     Onion       │      Clean       │
├─────────────────────────┼─────────────────┼──────────────────┤
│ Terminologiya           │ DDD-yönümlü     │ Use Case-yönümlü │
│ Ən iç qat               │ Domain Model    │ Entities          │
│ 2-ci qat                │ Domain Services │ Use Cases         │
│ Repo interface           │ Domain layerda  │ Use Case layerda  │
│ DDD uyğunluğu           │ Yüksək          │ Orta              │
│ Use Case vurğusu        │ Orta            │ Yüksək            │
│ Presenter/ViewModel     │ Adətən yoxdur   │ Interface Adapters│
└─────────────────────────┴─────────────────┴──────────────────┘
```

---

## Ümumi Səhvlər

**1. Domain layerda infrastructure asılılığı:**

```php
// ❌ Yanlış — Domain Layerdə Eloquent
class Order extends Model  // Eloquent Model — infrastructure!
{
    // Domain logic burada — YANLIŞ!
}

// ✅ Düzgün — Domain Layer təmizdir
class Order  // Plain PHP class
{
    // Yalnız domain logic
}
```

**2. Application Service-dən birbaşa DB:**

```php
// ❌ Yanlış
class PlaceOrderService
{
    public function __construct(private PDO $db) {}  // Infrastructure!
    
    public function handle(PlaceOrderCommand $cmd): void
    {
        $this->db->exec("INSERT INTO orders...");  // Infrastructure logic!
    }
}

// ✅ Düzgün
class PlaceOrderService
{
    public function __construct(private OrderRepository $repo) {}  // Interface!
}
```

**3. Controller-dən birbaşa Domain:**

```php
// ❌ Yanlış — Controller domain logic edir
class OrderController
{
    public function store(Request $request): JsonResponse
    {
        $order = new Order();
        $order->addItem(...);
        $order->confirm();  // Bu Application Service-in işidir!
        // DB-ə yazma, event dispatch — bunlar controller-ə aid deyil
    }
}
```

---

## İntervyu Sualları

**1. Onion Architecture-un əsas prinsipini izah edin.**
Dependency Rule: bütün asılılıqlar yalnız içəriyə işarə edir. Domain Model (ən iç) heç bir xarici asılılıq saxlamır. Infrastructure (ən kənar) domain-dən asılıdır, tərsinə yox. Bu domain logic-i framework, DB, xarici API-lərdən müstəqil edir.

**2. Repository interface-ı niyə Domain layerda tərif edilir?**
Domain layer persistence-ı bilməməlidir, amma repository-yə ehtiyacı var. Interface-ı domain-də tərif etməklə, domain öz ehtiyacını müəyyən edir. Infrastructure bu interfeysi implement edir. Bu Dependency Inversion Principle-dir.

**3. Domain Model-də Eloquent istifadə etmək olarmı?**
Olmaz. Eloquent (Active Record) infrastructure-dur. Domain Model-in Eloquent-dən asılı olması, domain-i DB strukturuna bağlayır. Domain entity-ləri plain PHP class olmalıdır. Infrastructure-da Mapper pattern istifadə edərək Eloquent model ↔ Domain entity çevrilməsi edilir.

**4. Application Service ilə Domain Service fərqi nədir?**
Domain Service: saf domain logic, bir neçə entity/aggregate ilə iş görür, infrastructure asılılığı yoxdur. Application Service: use case orkestrasyonu, domain service-lərini çağırır, persistence, event dispatch idarə edir, transaction boundaries müəyyən edir.

**5. Onion Architecture-da test etmək necə asanlaşır?**
Domain Model heç bir asılılıq olmadan test edilir (sürətli unit test). Application Service-lər mock-larla test edilir (repository, event dispatcher mock-lanır). Infrastructure-un integration testləri real DB ilə aparılır. Bu test piramidini optimal edir.

**6. Transaction boundary-ləri hansı qatda olmalıdır?**
Application Services qatında. Domain Model transaction-ı bilmir — o yalnız domain qaydalarını icra edir. Repository-nin `save()` metodu transaction-ı başlatmamalıdır. Application Service use case-i tamamlayanda commit, xəta olduqda rollback edir. Bu sayədə bir use case-ə aid bütün repository əməliyyatları eyni transaction-da olur.

**7. Mapper pattern nədir, niyə lazımdır?**
Domain Entity ↔ Eloquent Model çevirməsi. Domain Entity plain PHP class-dır — Eloquent-i tanımır. Infrastructure qatındakı Mapper bu iki dünyani körpüləyir: `toDomain(OrderModel $model): Order` və `toModel(Order $order): OrderModel`. Bu sayədə DB sxeması dəyişsə yalnız Mapper dəyişir, domain toxunulmur.

---

## Anti-patternlər

**1. Domain Model-də Eloquent Model-i extend etmək**
`class User extends Model` — domain entity-si Eloquent-ə (infrastructure) bağlanır, domain logic DB sxemasını bilməlidir, framework olmadan test etmək mümkünsüzləşir. Domain entity-lərini plain PHP class kimi yaz, infrastructure-da Mapper pattern ilə Eloquent ↔ Domain çevrilməsini həll et.

**2. Infrastructure layer-ı Domain layer-dan asılı etmək əvəzinə tərsinə etmək**
Repository interface-ını Infrastructure layer-da yaradıb Domain-dən ora bağlanmaq — Dependency Rule pozulur, Domain xarici implementasiyaları bilir. Interface-ı Domain layer-da müəyyən et, Infrastructure onu implement etsin; asılılıq həmişə daxəriyə işarə etsin.

**3. Application Service-lərinə business logic yerləşdirmək**
`OrderApplicationService`-ə endirim hesablaması, stok yoxlaması kimi domain qaydalarını yazmaq — business logic domain-dən çıxır, çoxlu yerdə kopyalanır, domain model anemic olur. Application Service yalnız orkestrtor kimi işləsin, real iş qaydaları Domain Service ya da Aggregate-də qalsın.

**4. Onion arxitekturasını hər kiçik layihəyə tətbiq etmək**
10 model, 20 endpoint-li sadə CRUD tətbiqinə Onion Architecture tətbiq etmək — lazımsız mürəkkəblik yaranır, boilerplate kod çoxalır, team produktivliyi azalır. Onion/Clean Architecture-ı mürəkkəb domain qaydaları, uzun ömürlü, böyük komandalı layihələrdə tətbiq et.

**5. Domain Event-ləri Infrastructure Event-ləri ilə eyniləşdirmək**
Domain event-lərini birbaşa Laravel Event kimi yaradıb Eloquent model-i event-ə əlavə etmək — domain infrastructure-a bağlanır, event-i serialize edərkən Eloquent relation-ları lazımsız yüklənir. Domain event-lərini saf PHP class kimi yaz, infrastructure-da adapter vasitəsilə Laravel Event-ə çevir.

**6. Test-lərdə real repository implementasiyası işlətmək**
Domain və Application Service test-lərini real Eloquent repository ilə yazmaq — test-lər DB-dən asılı olur, yavaş keçir, isolation itirilir. Interface-lara in-memory repository implementasiyası yaz, unit test-lərdə onu inject et; real DB yalnız integration test-lərdə işlənsin.
