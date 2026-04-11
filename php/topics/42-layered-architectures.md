# Software Arxitekturaları — Layered, Hexagonal, Clean, Onion

## Mündəricat
1. [Ənənəvi Layered Architecture](#ənənəvi-layered-architecture)
2. [Hexagonal Architecture (Ports & Adapters)](#hexagonal-architecture-ports--adapters)
3. [Clean Architecture](#clean-architecture)
4. [Onion Architecture](#onion-architecture)
5. [Müqayisə Cədvəli](#müqayisə-cədvəli)
6. [Nə Vaxt Hansını Seçməli](#nə-vaxt-hansını-seçməli)
7. [DDD ilə Uyğunluq](#ddd-ilə-uyğunluq)
8. [Laravel-də İmplementasiya](#laraveldə-implementasiya)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Ənənəvi Layered Architecture

```
┌─────────────────────────────────┐
│      Presentation Layer         │  HTTP, CLI, Controllers
│   (UI, Controllers, Views)      │
├─────────────────────────────────┤
│       Business Layer            │  Business Logic, Services
│   (Services, Domain Logic)      │
├─────────────────────────────────┤
│      Persistence Layer          │  DB, ORM, Repositories
│   (Repositories, ORM)           │
├─────────────────────────────────┤
│      Database Layer             │  MySQL, PostgreSQL
└─────────────────────────────────┘

Asılılıq istiqaməti: YUXARIDAN AŞAĞIYA ↓
```

**Problemi:**

```php
// Business layer birbaşa DB-dən asılıdır
class OrderService
{
    public function __construct(
        private PDO $db  // ← Infrastructure-a birbaşa asılılıq!
    ) {}
    
    public function createOrder(array $data): void
    {
        // Business logic + DB sorğusu qarışıq
        $this->db->exec("INSERT INTO orders...");
    }
}
```

**Problemlər:**
- Test etmək çətin (real DB lazımdır)
- DB-ni dəyişmək çətindir (hər yerdə PDO istifadəsi)
- Tight coupling

---

## Hexagonal Architecture (Ports & Adapters)

Alistair Cockburn tərəfindən 2005-ci ildə təqdim edildi. Əsas ideya: **tətbiqin nüvəsi kənardakı dünyadan tamamilə izolə edilməlidir**.

```
                    ┌─────────────────────────────────────┐
                    │          Hexagonal Core              │
  HTTP Request ──►  │                                     │ ──► Database
  CLI Input    ──►  │   Primary Port  │  Secondary Port   │ ──► Email
  Test         ──►  │   (inbound)     │  (outbound)       │ ──► Message Queue
                    │                 │                   │
                    └─────────────────────────────────────┘
                          ▲                  │
                    Primary Adapter    Secondary Adapter
                    (Controller,       (Repository Impl,
                     CLI Runner)        Email Sender)
```

**Port vs Adapter:**
- **Port** — interface (müqavilə), nüvədə tərif edilir
- **Adapter** — port-un konkret implementasiyası, kənarda yerləşir

*- **Adapter** — port-un konkret implementasiyası, kənarda yerləşir üçün kod nümunəsi:*
```php
// Secondary Port (outbound) — nüvədə interface
interface OrderRepository  // Port
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
}

// Secondary Adapter — kənarda implementasiya
class EloquentOrderRepository implements OrderRepository  // Adapter
{
    public function save(Order $order): void
    {
        OrderModel::updateOrCreate(
            ['id' => $order->id()->value()],
            $order->toArray()
        );
    }
    
    public function findById(OrderId $id): ?Order
    {
        $model = OrderModel::find($id->value());
        return $model ? Order::fromModel($model) : null;
    }
}

// Primary Port (inbound) — use case interface
interface PlaceOrderUseCase  // Port
{
    public function execute(PlaceOrderCommand $command): OrderId;
}

// Primary Adapter — Controller
class OrderController extends Controller  // Adapter
{
    public function store(
        PlaceOrderRequest $request,
        PlaceOrderUseCase $useCase  // Port istifadə edir
    ): JsonResponse {
        $orderId = $useCase->execute(
            new PlaceOrderCommand($request->validated())
        );
        return response()->json(['id' => $orderId->value()]);
    }
}
```

---

## Clean Architecture

Robert C. Martin (Uncle Bob) tərəfindən populyarlaşdırıldı.

```
           ┌─────────────────────────────────────────┐
           │          Frameworks & Drivers            │
           │  ┌───────────────────────────────────┐  │
           │  │      Interface Adapters            │  │
           │  │  ┌─────────────────────────────┐  │  │
           │  │  │      Application             │  │  │
           │  │  │   Business Rules             │  │  │
           │  │  │  ┌─────────────────────┐    │  │  │
           │  │  │  │      Entities       │    │  │  │
           │  │  │  │  Enterprise Rules   │    │  │  │
           │  │  │  └─────────────────────┘    │  │  │
           │  │  └─────────────────────────────┘  │  │
           │  └───────────────────────────────────┘  │
           └─────────────────────────────────────────┘

Dependency Rule: Asılılıqlar YALNIZ içəriyə işarə edir!
```

**4 qat:**
1. **Entities** — Enterprise business rules, ən az dəyişən
2. **Use Cases** — Application business rules, entities-i orkestr edir
3. **Interface Adapters** — Controllers, Presenters, Gateways (çeviricilər)
4. **Frameworks & Drivers** — Laravel, MySQL, RabbitMQ — kənar detal

*4. **Frameworks & Drivers** — Laravel, MySQL, RabbitMQ — kənar detal üçün kod nümunəsi:*
```php
// Entity (ən iç — heç bir asılılıq yoxdur)
class Order
{
    private OrderStatus $status;
    private Money $total;
    private array $items = [];
    
    public function addItem(OrderItem $item): void
    {
        $this->items[] = $item;
        $this->total = $this->total->add($item->price());
    }
    
    public function confirm(): void
    {
        if ($this->items === []) {
            throw new \DomainException('Boş sifariş təsdiqlənə bilməz');
        }
        $this->status = OrderStatus::CONFIRMED;
    }
}

// Use Case
class PlaceOrderUseCase
{
    public function __construct(
        private OrderRepository $repository,  // Interface — içəri
        private PaymentGateway $payment       // Interface — içəri
    ) {}
    
    public function execute(PlaceOrderCommand $cmd): OrderId
    {
        $order = Order::create($cmd->customerId(), $cmd->items());
        $this->payment->charge($cmd->paymentInfo(), $order->total());
        $order->confirm();
        $this->repository->save($order);
        return $order->id();
    }
}

// Interface Adapter (Controller)
class OrderController
{
    public function store(Request $request): JsonResponse
    {
        // Request DTO-ya çevir
        $command = PlaceOrderCommandMapper::fromRequest($request);
        $orderId = $this->useCase->execute($command);
        // Entity-ni Response-a çevir
        return response()->json(['id' => $orderId->value()], 201);
    }
}
```

---

## Onion Architecture

Jeffrey Palermo tərəfindən 2008-ci ildə təqdim edildi. DDD ilə çox uyğundur.

```
         ┌─────────────────────────────────────┐
         │          Infrastructure             │
         │   ┌─────────────────────────────┐   │
         │   │    Application Services     │   │
         │   │  ┌───────────────────────┐  │   │
         │   │  │   Domain Services     │  │   │
         │   │  │  ┌─────────────────┐  │  │   │
         │   │  │  │  Domain Model   │  │  │   │
         │   │  │  │ (Entities, VOs, │  │  │   │
         │   │  │  │  Domain Events) │  │  │   │
         │   │  │  └─────────────────┘  │  │   │
         │   │  └───────────────────────┘  │   │
         │   └─────────────────────────────┘   │
         └─────────────────────────────────────┘

Asılılıq: Həmişə içəriyə ↓
```

**4 qat:**
1. **Domain Model** — Entity, Value Object, Domain Event — xarici asılılıq yoxdur
2. **Domain Services** — Bir neçə entity ilə iş görən domain məntiqi
3. **Application Services** — Use case-lər, xarici dünya ilə koordinasiya
4. **Infrastructure** — DB, API, Email — konkret implementasiyalar

*4. **Infrastructure** — DB, API, Email — konkret implementasiyalar üçün kod nümunəsi:*
```php
// Domain Model (ən iç — heç bir asılılıq yoxdur)
namespace App\Domain\Order;

class Order  // Entity
{
    private OrderId $id;
    private CustomerId $customerId;
    private OrderStatus $status;
    private array $domainEvents = [];
    
    public function place(): void
    {
        $this->status = OrderStatus::PLACED;
        $this->domainEvents[] = new OrderPlaced($this->id, $this->customerId);
    }
    
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}

// Domain Service
namespace App\Domain\Order;

class OrderPricingService  // Domain Service
{
    public function calculateTotal(Order $order, PriceList $prices): Money
    {
        // Bir neçə domain obyekti ilə iş
        return array_reduce(
            $order->items(),
            fn(Money $acc, OrderItem $item) => $acc->add(
                $prices->priceFor($item->productId())->multiply($item->quantity())
            ),
            Money::zero()
        );
    }
}

// Application Service
namespace App\Application\Order;

class PlaceOrderService  // Application Service
{
    public function __construct(
        private OrderRepository $orderRepo,         // Domain interface
        private CustomerRepository $customerRepo,   // Domain interface
        private OrderPricingService $pricingService,
        private EventDispatcher $dispatcher
    ) {}
    
    public function handle(PlaceOrderCommand $cmd): void
    {
        $customer = $this->customerRepo->findById($cmd->customerId());
        $order = Order::create($customer, $cmd->items());
        
        $total = $this->pricingService->calculateTotal($order, $cmd->priceList());
        $order->setTotal($total);
        $order->place();
        
        $this->orderRepo->save($order);
        
        foreach ($order->pullDomainEvents() as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}

// Infrastructure
namespace App\Infrastructure\Order;

class EloquentOrderRepository implements OrderRepository  // Infrastructure
{
    public function save(Order $order): void
    {
        // Domain Entity → Eloquent Model çevirməsi
        OrderEloquentModel::updateOrCreate(
            ['id' => $order->id()->value()],
            OrderMapper::toArray($order)
        );
    }
}
```

---

## Müqayisə Cədvəli

```
┌──────────────────────┬───────────┬────────────┬────────────┬──────────────┐
│                      │ Layered   │ Hexagonal  │   Clean    │    Onion     │
├──────────────────────┼───────────┼────────────┼────────────┼──────────────┤
│ İl                   │ 1990s     │ 2005       │ 2012       │ 2008         │
│ Müəllif              │ -         │ Cockburn   │ Uncle Bob  │ Palermo      │
│ Nüvə                 │ Yoxdur    │ App Core   │ Entities   │ Domain Model │
│ Asılılıq istiqaməti  │ Aşağı ↓   │ Içəriyə ↓  │ Içəriyə ↓  │ Içəriyə ↓   │
│ DB-dən müstəqillik   │ ❌        │ ✅          │ ✅          │ ✅           │
│ Test edilə bilərlik  │ Orta      │ Yüksək     │ Yüksək     │ Yüksək       │
│ DDD uyğunluğu        │ Aşağı     │ Orta       │ Orta       │ Yüksək       │
│ Öyrənmə çətinliyi   │ Aşağı     │ Orta       │ Orta-Yüksək│ Orta-Yüksək │
│ Boilerplate          │ Az        │ Orta       │ Çox        │ Orta         │
│ Qat sayı             │ 3-4       │ 2 (in/out) │ 4          │ 4            │
└──────────────────────┴───────────┴────────────┴────────────┴──────────────┘
```

**Oxşarlıqlar (Hexagonal, Clean, Onion):**
- Hamısında asılılıqlar içəriyə işarə edir
- Infrastructure-un domain-dən asılı olması (tərsinə yox)
- Interface/Port-larla loose coupling
- Domain/Business logic-in framework-dən müstəqilliyi

**Fərqlər:**
- Hexagonal: port/adapter terminologiyası, ikili istiqamət (in/out)
- Clean: 4 xüsusi qat, Entities/Use Cases ayrılığı
- Onion: DDD terminologiyası, Domain Model mərkəzdə

---

## Nə Vaxt Hansını Seçməli

```
Layered Architecture:
  ✅ Kiçik/orta CRUD tətbiqləri
  ✅ Komanda arxitektura ilə tanış deyil
  ✅ Sürətli prototip
  ❌ Mürəkkəb domain
  ❌ Çoxlu integration (DB, queue, external API)

Hexagonal:
  ✅ Çoxlu adapter lazımdır (REST + CLI + Queue)
  ✅ Driver/driven ayrılığı aydın
  ✅ Integration testlər vacibdir
  ❌ Çox kiçik layihələr

Clean Architecture:
  ✅ Böyük komanda, uzun müddətli layihə
  ✅ Framework dəyişmə ehtimalı var
  ❌ Kiçik CRUD tətbiqlər
  ❌ Çox boilerplate qəbul edilə bilmir

Onion Architecture:
  ✅ DDD tətbiq edilir
  ✅ Mürəkkəb domain logic
  ✅ Long-term, enterprise tətbiq
  ❌ Domain zəifdirsə
```

---

## DDD ilə Uyğunluq

```
DDD Elementi          Onion qatı            Clean qatı
─────────────────────────────────────────────────────────
Entity                Domain Model          Entities
Value Object          Domain Model          Entities
Domain Event          Domain Model          Entities
Domain Service        Domain Services       Use Cases (qismən)
Aggregate             Domain Model          Entities
Repository (iface)    Domain Services       Use Cases
Use Case              Application Services  Use Cases
Repository (impl)     Infrastructure        Gateways/Adapters
```

---

## Laravel-də İmplementasiya

**Tövsiyə olunan folder strukturu (Onion/Clean):**

```
app/
├── Domain/
│   └── Order/
│       ├── Entities/
│       │   └── Order.php
│       ├── ValueObjects/
│       │   ├── OrderId.php
│       │   └── Money.php
│       ├── Events/
│       │   └── OrderPlaced.php
│       ├── Services/
│       │   └── OrderPricingService.php
│       └── Repositories/
│           └── OrderRepository.php  ← interface
│
├── Application/
│   └── Order/
│       ├── Commands/
│       │   └── PlaceOrderCommand.php
│       └── Services/
│           └── PlaceOrderService.php
│
└── Infrastructure/
    └── Order/
        ├── Repositories/
        │   └── EloquentOrderRepository.php  ← implementation
        └── Models/
            └── OrderModel.php
```

---

## Dependency Inversion Principle (DIP) — Arxitekturanın Əsası

Bu arxitekturaların hamısı DIP-ə söykənir:

```
❌ DIP olmadan (Layered):
  BusinessService → PDO (Infrastructure)
  BusinessService → MySQL (Infrastructure)

✅ DIP ilə (Hexagonal/Clean/Onion):
  BusinessService → OrderRepository (Interface, Domain-da)
                          ↑ implements
               EloquentOrderRepository (Infrastructure)

Yüksək səviyyəli modul (Business) aşağı səviyyəliyə (DB) asılı olmur.
Hər ikisi abstraksiyana (interface) asılıdır.
```

**Laravel Service Container-də binding:**
```php
// AppServiceProvider
$this->app->bind(OrderRepository::class, EloquentOrderRepository::class);
// Artıq OrderRepository-ni inject edən hər yer EloquentOrderRepository alır
// Test-də InMemoryOrderRepository bind etmək kifayətdir — heç bir kod dəyişmir
```

---

## İntervyu Sualları

**1. Hexagonal, Clean, Onion arxitekturalarının ortaq prinsipləri nəlardır?**
Hamısında asılılıq qaydasına (Dependency Rule) riayət edilir: asılılıqlar yalnız içəriyə işarə edir. Infrastructure domain-dən asılıdır, tərsinə yox. Interface-lar vasitəsilə loose coupling. Bu sayədə domain/business logic framework-dən, DB-dən müstəqildir.

**2. Port nədir, Adapter nədir?**
Hexagonal arxitekturada Port — tətbiqin kənarla kommunikasiya nöqtəsi (interface). Adapter — port-un konkret implementasiyası. Primary port-lar (inbound): controller tərəfindən çağırılır. Secondary port-lar (outbound): DB, email kimi xidmətlərə çağırış.

**3. Clean Architecture-da Dependency Rule nədir?**
Source code asılılıqları yalnız içəriyə (daha abstrakt qatlara) işarə edə bilər. Entities-dən heç bir şey xaricə asılı ola bilməz. Use Cases yalnız Entities-dən asılı ola bilər. Framework/DB Use Cases-dən asılıdır, tərsinə yox.

**4. Onion Architecture DDD ilə niyə yaxşı uyğunlaşır?**
Onion-un ən iç qatı Domain Model — DDD-nin Entities, Value Objects, Domain Events-lərinə uyğun gəlir. Domain Model heç bir xarici asılılıq saxlamır — bu DDD-nin "domain should be pure" prinsipinə uyğundur. Application Services DDD-nin Application Layer-inə uyğundur.

**5. Layered Architecture-nın əsas problemi nədir?**
Business layer infrastructure-a (DB) birbaşa asılı olur. Test etmək çətin (real DB lazımdır), DB dəyişmək çətin, tight coupling. Həll: Dependency Inversion — interfaces vasitəsilə asılılıqları çevirmək.

**6. "Screaming Architecture" nədir?**
Uncle Bob-un termini: folder/namespace strukturuna baxanda domain-i "qışqırmalıdır" — `App\Domain\Order`, `App\Domain\Payment`. Framework screaming olmamalıdır (`App\Http\Controllers`). Laravel-in default strukturu framework-screaming-dir, DDD layihələrində yenidən strukturlaşdırmaq lazımdır.

**7. Hexagonal arxitekturada primary adapter ilə secondary adapter nümunəsi verin.**
Primary adapter (inbound/driving): `OrderController` — xaricdən tətbiqi çağırır. Secondary adapter (outbound/driven): `EloquentOrderRepository` — tətbiq infrastruktur xidmətini çağırır. Primary adapter primary port-u (use case interface) çağırır; secondary adapter secondary port-u (repository interface) implement edir.

---

## Anti-patternlər

**1. Business logic-i Controller-də yazmaq**
Validation, hesablama, iş qaydaları birbaşa controller metodunda — controller şişir, eyni logic başqa controller-lərdə kopyalanır, unit test mümkünsüzləşir. Business logic-i Service ya da Domain layer-a köçür, controller yalnız HTTP request/response idarə etsin.

**2. Presentation layer-ı birbaşa Database layer-a bağlamaq**
Controller-dən birbaşa `DB::table('orders')->where(...)` çağırmaq — qatlar arası ayrılıq yox olur, DB dəyişdikdə bütün controller-lər dəyişməlidir. Repository pattern ilə DB əməliyyatlarını soyutla, controller yalnız service metodlarını çağırsın.

**3. Layer-ları keçməklə "shortcut" etmək**
View template-dən birbaşa model sorğusu çağırmaq (`User::where(...)` blade faylında) — template-lər DB sxeması ilə sıx bağlanır, logic test etmək mümkünsüzdür. Hər layer öz məsuliyyətini yerinə yetirsin, aşağı layer-a məlumat üst layer vasitəsilə çatsın.

**4. Anemic Domain Model yaratmaq**
Bütün business logic-i service-lərdə yazıb entity-ləri yalnız getter/setter-li data container-ə çevirmək — domain modeli iş qaydalarını ifadə etmir, service-lər şişir. Entity-lərə domain behavior-ları əlavə et (`$order->cancel()`, `$invoice->markAsPaid()`).

**5. Infrastruktur detallarını Domain layer-a sızdırmaq**
Domain entity-lərinə Eloquent-i extend etdirmək, domain service-lərdə `Cache::remember()` çağırmaq — domain framework-ə bağlanır, test etmək üçün real framework lazım olur. Domain layer yalnız saf PHP class-lardan ibarət olsun, infrastruktur asılılıqları interface-lar vasitəsilə inject edilsin.

**6. Transaction boundary-lərini yanlış qata yerləşdirmək**
Repository-nin hər metodunda `DB::beginTransaction()` açmaq — çoxlu repository əməliyyatları fərqli transaction-larda icra edilir, atomicity pozulur. Transaction boundary-lərini Application/Service layer-da müəyyən et, use case tamamlandıqda commit, uğursuzluq halında rollback et.
