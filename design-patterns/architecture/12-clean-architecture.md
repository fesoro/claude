# Clean Architecture (Lead)

## İcmal

Clean Architecture — Robert C. Martin (Uncle Bob) tərəfindən 2012-ci ildə populyarlaşdırılmış arxitektura yanaşmasıdır. Mərkəzi ideya: **Dependency Rule** — source code asılılıqları yalnız içəriyə (daha abstrakt qatlara) işarə edə bilər. 4 konsantrik ring var: Entities (ən iç), Use Cases, Interface Adapters, Frameworks & Drivers (ən kənar). Framework, DB, UI — bunlar hamısı kənar detal sayılır; domain logic-i onlardan tamamilə müstəqil olmalıdır.

## Niyə Vacibdir

Laravel developer üçün Clean Architecture-ın dəyəri: Eloquent-i dəyişmək istədikdə yalnız Infrastructure qatı dəyişir; test üçün real DB lazım olmur; Use Case-ləri CLI, HTTP, Queue — hər üçündən eyni şəkildə çağırmaq olur; yeni developer domain logic-i sırf PHP class-ları oxuyaraq başa düşür. Trade-off real: kiçik CRUD app üçün bu mürəkkəblik artıqdır. Lead/Architect səviyyəsi bu qərarı əsaslandıra bilməlidir.

## Əsas Anlayışlar

- **Dependency Rule**: source code asılılığı heç vaxt xaricə işarə edə bilməz; daxili qat xarici qatı bilmir
- **Entities**: enterprise-wide business rule-lar; ən az dəyişən; framework asılılığı yoxdur; plain PHP class-lar
- **Use Cases**: application-specific business rule-lar; entities-i orkestr edir; xarici dünyadan (DB, UI) müstəqil
- **Interface Adapters**: data formatını Use Cases ↔ Framework arasında çevirir; Controller, Presenter, Gateway burada
- **Frameworks & Drivers**: Laravel, Eloquent, MySQL, RabbitMQ — kənar detal; ən kənardadır; tez-tez dəyişə bilər
- **Screaming Architecture**: folder strukturuna baxanda domain "qışqırmalıdır" — `App\Domain\Order`, `App\Application\Payment`; `App\Http\Controllers` deyil
- **Interactor / Use Case Handler**: Use Case-i implement edən class; input boundary-dən daxil olur, output boundary-dən çıxır
- **Input/Output Boundary**: Use Case-in kənara ilə kommunikasiya interface-ləri; dependency rule-u tətbiq etmək üçün

## Praktik Baxış

- **Real istifadə**: böyük komanda (10+ developer), uzun müddətli layihə (3+ il), mürəkkəb domain qaydaları, çoxlu delivery mechanism (HTTP + CLI + Queue + WebSocket)
- **Trade-off-lar**: testability yüksəlir (domain test-lər ms-lərdə), framework dəyişimi mümkün olur, onboarding daha asan (domain oxuyub başa düşmək); lakin boilerplate çox (hər feature üçün 5-8 class), öyrənmə əyrisi yüksəkdir, kiçik team üçün over-engineering
- **Hansı hallarda istifadə etməmək**: startup MVP, sadə admin panel, 1-3 nəfərlik team, domain logic minimaldırsa, layihə 6 aydan az ömürlüdürsə
- **Common mistakes**: Use Case-ə framework import etmək (`use Illuminate\...`); Entities-ə DB logic yazmaq; Interface Adapters-ı atlamaq (Controller birbaşa Domain-i çağırır)

### Anti-Pattern Nə Zaman Olur?

**Simple CRUD app-a Clean Architecture**: 5 CRUD endpoint-li admin panel üçün `PlaceOrderUseCase`, `OrderRepositoryInterface`, `EloquentOrderAdapter`, `OrderPresenter`, `OrderResponseModel` yaratmaq — 6 class bir basit əməliyyat üçün. Developer-lər hər yeni feature üçün boilerplate yazmaqdan usanır, qaydaları pozmağa başlayır. Nəticə: yarıtmaz Clean Architecture = adi monolitdən pis.

**Use Case-ə framework import etmək**: `use Illuminate\Support\Facades\Cache;` Use Case class-ının içindədir — Use Case artıq Laravel bilir, framework-dən asılıdır. Test üçün real Laravel laravel lazım olur. Use Case-lər yalnız Domain interface-lərini bilməlidir; Laravel spesifik şeylər yalnız Infrastructure-da ola bilər.

---

## Nümunələr

### Ümumi Nümunə

Dependency Rule-u bildirmək üçün bir analogiya: xarici (şəhər) daxili (ev) haqqında bilik saxlaya bilər, lakin ev şəhəri bilmir. Laravel (xarici) domain-i (ev) bilir, domain Laravel-i bilmir. Bütün asılılıqlar ev tərəfinə işarə edir.

### Kod Nümunəsi

**Clean Architecture folder strukturu — Laravel:**

```
app/
├── Domain/                     # Entities qatı — pure PHP, heç bir asılılıq yoxdur
│   ├── Order/
│   │   ├── Order.php           # Entity
│   │   ├── OrderItem.php       # Entity
│   │   ├── OrderId.php         # Value Object
│   │   ├── Money.php           # Value Object
│   │   ├── OrderStatus.php     # Value Object / Enum
│   │   └── OrderRepository.php # Repository Interface (Domain-da!)
│   └── Customer/
│       ├── Customer.php
│       └── CustomerRepository.php
│
├── Application/                # Use Cases qatı — entities orchestrate edir
│   ├── Order/
│   │   ├── PlaceOrderUseCase.php
│   │   ├── PlaceOrderRequest.php  # Input boundary DTO
│   │   ├── PlaceOrderResponse.php # Output boundary DTO
│   │   ├── CancelOrderUseCase.php
│   │   └── GetOrderUseCase.php
│   └── Customer/
│       └── RegisterCustomerUseCase.php
│
├── Infrastructure/             # Frameworks & Drivers + Interface Adapters
│   ├── Persistence/
│   │   ├── EloquentOrderRepository.php   # Repository Implementation
│   │   ├── Models/
│   │   │   └── OrderModel.php            # Eloquent Model (yalnız burada)
│   │   └── Mappers/
│   │       └── OrderMapper.php
│   ├── Mail/
│   │   └── LaravelMailOrderNotifier.php
│   └── ServiceProvider/
│       └── OrderServiceProvider.php      # DI wiring
│
└── Presentation/               # Interface Adapters — HTTP/CLI/Queue
    ├── Http/
    │   ├── Controllers/
    │   │   └── OrderController.php       # Primary Adapter
    │   ├── Requests/
    │   │   └── PlaceOrderHttpRequest.php
    │   └── Resources/
    │       └── OrderResource.php         # Response formatter
    └── Console/
        └── PlaceOrderCommand.php         # CLI Primary Adapter
```

**Entities qatı — pure PHP:**

```php
<?php

namespace App\Domain\Order;

// Value Object
final class OrderId
{
    public function __construct(private readonly string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('OrderId cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(\Ramsey\Uuid\Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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

// Value Object
final class Money
{
    private function __construct(
        private readonly int    $amount,   // Sentdə — floating point problem-dən qaçınmaq üçün
        private readonly string $currency  // ISO 4217: AZN, USD, EUR
    ) {}

    public static function of(int $amount, string $currency): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Mənfi məbləğ qəbul edilmir');
        }
        return new self($amount, strtoupper($currency));
    }

    public static function zero(string $currency = 'AZN'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Fərqli valyutalar toplanmaz: {$this->currency} + {$other->currency}"
            );
        }
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function amount(): int    { return $this->amount; }
    public function currency(): string { return $this->currency; }
    public function formatted(): string { return number_format($this->amount / 100, 2) . ' ' . $this->currency; }
}

// Entity — enterprise business rule-lar
final class Order
{
    private array $items         = [];
    private array $domainEvents  = [];
    private Money $total;

    private function __construct(
        private readonly OrderId    $id,
        private readonly string     $customerId,
        private OrderStatus         $status,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->total = Money::zero();
    }

    public static function place(string $customerId): self
    {
        if (empty($customerId)) {
            throw new \DomainException('Customer ID required');
        }

        $order = new self(
            id:        OrderId::generate(),
            customerId: $customerId,
            status:    OrderStatus::DRAFT,
            createdAt: new \DateTimeImmutable(),
        );

        $order->domainEvents[] = new OrderPlaced($order->id, $customerId);
        return $order;
    }

    /**
     * Domain-dan yenidən qurma — repository istifadə edir
     * static factory — private constructor əvəzinə
     */
    public static function reconstitute(
        OrderId    $id,
        string     $customerId,
        OrderStatus $status,
        Money       $total,
        array       $items,
        \DateTimeImmutable $createdAt
    ): self {
        $order = new self($id, $customerId, $status, $createdAt);
        $order->total = $total;
        $order->items = $items;
        return $order;
    }

    // Enterprise business rule-lar burada
    public function addItem(OrderItem $item): void
    {
        if ($this->status !== OrderStatus::DRAFT) {
            throw new \DomainException('Yalnız draft sifarişə məhsul əlavə edilə bilər');
        }

        $this->items[]  = $item;
        $this->total    = $this->total->add($item->subtotal());
    }

    public function confirm(): void
    {
        if ($this->status !== OrderStatus::DRAFT) {
            throw new \DomainException('Yalnız draft sifariş təsdiqlənə bilər');
        }
        if (empty($this->items)) {
            throw new \DomainException('Boş sifariş təsdiqlənə bilməz');
        }
        if ($this->total->isGreaterThan(Money::of(1_000_000_00, 'AZN'))) {
            throw new \DomainException('Sifariş limiti aşılıb');
        }

        $this->status         = OrderStatus::CONFIRMED;
        $this->domainEvents[] = new OrderConfirmed($this->id, $this->total);
    }

    public function cancel(string $reason): void
    {
        if (in_array($this->status, [OrderStatus::SHIPPED, OrderStatus::DELIVERED], true)) {
            throw new \DomainException('Göndərilmiş sifariş ləğv edilə bilməz');
        }

        $this->status         = OrderStatus::CANCELLED;
        $this->domainEvents[] = new OrderCancelled($this->id, $reason);
    }

    public function pullDomainEvents(): array
    {
        $events             = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // Getters
    public function id(): OrderId            { return $this->id; }
    public function customerId(): string     { return $this->customerId; }
    public function status(): OrderStatus    { return $this->status; }
    public function total(): Money           { return $this->total; }
    public function items(): array           { return $this->items; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
}

// Repository Interface — Domain layer-da tərif edilir
interface OrderRepository
{
    public function findById(OrderId $id): ?Order;
    public function findByCustomer(string $customerId): array;
    public function save(Order $order): void;
    public function nextIdentity(): OrderId;
}
```

**Use Cases qatı:**

```php
<?php

namespace App\Application\Order;

use App\Domain\Order\{Order, OrderRepository, OrderItem, Money};

// Input DTO — Use Case-in giriş interfeysi
final class PlaceOrderRequest
{
    public function __construct(
        public readonly string $customerId,
        public readonly array  $items,   // [['product_id', 'quantity', 'unit_price_cents'], ...]
    ) {}
}

// Output DTO — Use Case-in çıxış interfeysi
final class PlaceOrderResponse
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $status,
        public readonly int    $totalAmountCents,
        public readonly string $currency,
    ) {}
}

// PlaceOrderUseCase — application-specific business rule
final class PlaceOrderUseCase
{
    public function __construct(
        private readonly OrderRepository       $orders,
        private readonly CustomerRepository    $customers,
        private readonly OrderNotifier         $notifier,  // Domain interface
    ) {}

    public function execute(PlaceOrderRequest $request): PlaceOrderResponse
    {
        // 1. Customer mövcuddurmu?
        $customer = $this->customers->findById($request->customerId);
        if (!$customer) {
            throw new CustomerNotFoundException($request->customerId);
        }

        // 2. Domain entity yarat
        $order = Order::place($request->customerId);

        // 3. Items əlavə et
        foreach ($request->items as $itemData) {
            $item = new OrderItem(
                productId: $itemData['product_id'],
                quantity:  $itemData['quantity'],
                unitPrice: Money::of($itemData['unit_price_cents'], 'AZN'),
            );
            $order->addItem($item);
        }

        // 4. Sifariş təsdiqlə — domain rule-ları tətbiq olunur
        $order->confirm();

        // 5. Persist et
        $this->orders->save($order);

        // 6. Side effects
        $this->notifier->orderPlaced($order);

        // 7. Output DTO qaytar — domain entity-ni birbaşa qaytarma!
        return new PlaceOrderResponse(
            orderId:          (string) $order->id(),
            status:           $order->status()->value,
            totalAmountCents: $order->total()->amount(),
            currency:         $order->total()->currency(),
        );
    }
}
```

**Infrastructure qatı:**

```php
<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Order\{Order, OrderId, OrderItem, OrderRepository, OrderStatus, Money};
use App\Infrastructure\Persistence\Models\OrderModel;

class EloquentOrderRepository implements OrderRepository
{
    public function findById(OrderId $id): ?Order
    {
        $model = OrderModel::with('items')->find((string) $id);
        return $model ? $this->toDomain($model) : null;
    }

    public function findByCustomer(string $customerId): array
    {
        return OrderModel::where('customer_id', $customerId)
            ->with('items')
            ->get()
            ->map(fn($m) => $this->toDomain($m))
            ->all();
    }

    public function save(Order $order): void
    {
        $model = OrderModel::updateOrCreate(
            ['id' => (string) $order->id()],
            [
                'customer_id'  => $order->customerId(),
                'status'       => $order->status()->value,
                'total_amount' => $order->total()->amount(),
                'currency'     => $order->total()->currency(),
                'created_at'   => $order->createdAt(),
            ]
        );

        // Items sync
        $model->items()->delete();
        foreach ($order->items() as $item) {
            $model->items()->create([
                'product_id'       => $item->productId(),
                'quantity'         => $item->quantity(),
                'unit_price_cents' => $item->unitPrice()->amount(),
            ]);
        }

        // Domain events — Infrastructure dispatch edir
        foreach ($order->pullDomainEvents() as $event) {
            event($event); // Laravel event system
        }
    }

    public function nextIdentity(): OrderId
    {
        return OrderId::generate();
    }

    // Mapper — Eloquent model → Domain entity
    private function toDomain(OrderModel $model): Order
    {
        return Order::reconstitute(
            id:         OrderId::fromString($model->id),
            customerId: $model->customer_id,
            status:     OrderStatus::from($model->status),
            total:      Money::of($model->total_amount, $model->currency),
            items:      $model->items->map(fn($i) => new OrderItem(
                            productId: $i->product_id,
                            quantity:  $i->quantity,
                            unitPrice: Money::of($i->unit_price_cents, $model->currency),
                        ))->all(),
            createdAt:  new \DateTimeImmutable($model->created_at),
        );
    }
}

// Service Provider — DI wiring
namespace App\Infrastructure\ServiceProvider;

use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Interface → Implementation binding
        $this->app->bind(
            \App\Domain\Order\OrderRepository::class,
            \App\Infrastructure\Persistence\EloquentOrderRepository::class
        );

        $this->app->bind(
            \App\Domain\Order\OrderNotifier::class,
            \App\Infrastructure\Mail\LaravelMailOrderNotifier::class
        );
    }
}
```

**Presentation qatı (Interface Adapters):**

```php
<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Order\{PlaceOrderUseCase, PlaceOrderRequest};
use App\Presentation\Http\Requests\PlaceOrderHttpRequest;
use App\Presentation\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;

// Primary Adapter — HTTP üzərindən Use Case-i drive edir
class OrderController extends \Illuminate\Routing\Controller
{
    public function __construct(
        private readonly PlaceOrderUseCase $placeOrder,
    ) {}

    public function store(PlaceOrderHttpRequest $request): JsonResponse
    {
        try {
            // HTTP Request → Use Case Input DTO
            $response = $this->placeOrder->execute(new PlaceOrderRequest(
                customerId: $request->user()->id,
                items:      $request->validated('items'),
            ));

            // Use Case Output DTO → HTTP Response
            return response()->json([
                'order_id' => $response->orderId,
                'status'   => $response->status,
                'total'    => [
                    'amount'   => $response->totalAmountCents,
                    'currency' => $response->currency,
                    'formatted' => number_format($response->totalAmountCents / 100, 2)
                                   . ' ' . $response->currency,
                ],
            ], 201);

        } catch (\App\Domain\Order\CustomerNotFoundException $e) {
            return response()->json(['error' => 'Müştəri tapılmadı'], 404);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}

// CLI Primary Adapter — eyni Use Case-i CLI-dən drive edir
namespace App\Presentation\Console;

class PlaceOrderConsoleCommand extends \Illuminate\Console\Command
{
    protected $signature   = 'order:place {customerId} {--items=}';
    protected $description = 'Place an order from CLI';

    public function __construct(private PlaceOrderUseCase $placeOrder)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $items = json_decode($this->option('items'), true);

        $response = $this->placeOrder->execute(new PlaceOrderRequest(
            customerId: $this->argument('customerId'),
            items:      $items,
        ));

        $this->info("Sifariş yaradıldı: {$response->orderId}");
        $this->info("Cəmi: {$response->totalAmountCents} {$response->currency}");

        return self::SUCCESS;
    }
}
```

**Testing — domain test-lər framework olmadan:**

```php
<?php

namespace Tests\Unit\Domain\Order;

use App\Domain\Order\{Order, OrderItem, Money};
use PHPUnit\Framework\TestCase;

// ✅ Framework olmadan, ms-lərdə çalışır
class OrderTest extends TestCase
{
    public function test_can_be_placed_with_valid_customer(): void
    {
        $order = Order::place('customer-123');

        $this->assertEquals('customer-123', $order->customerId());
        $this->assertTrue($order->status()->isDraft());
    }

    public function test_calculates_total_correctly(): void
    {
        $order = Order::place('customer-123');
        $order->addItem(new OrderItem('prod-1', 2, Money::of(5000, 'AZN')));
        $order->addItem(new OrderItem('prod-2', 1, Money::of(3000, 'AZN')));

        $this->assertEquals(Money::of(13000, 'AZN'), $order->total());
    }

    public function test_cannot_confirm_empty_order(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Boş sifariş təsdiqlənə bilməz');

        $order = Order::place('customer-123');
        $order->confirm();
    }

    public function test_cannot_add_item_to_confirmed_order(): void
    {
        $this->expectException(\DomainException::class);

        $order = Order::place('customer-123');
        $order->addItem(new OrderItem('prod-1', 1, Money::of(5000, 'AZN')));
        $order->confirm();
        $order->addItem(new OrderItem('prod-2', 1, Money::of(3000, 'AZN'))); // Exception!
    }
}

// Use Case test — fake repository ilə
namespace Tests\Unit\Application\Order;

use App\Application\Order\{PlaceOrderUseCase, PlaceOrderRequest};
use App\Domain\Order\OrderRepository;
use Tests\Doubles\InMemoryOrderRepository;
use Tests\Doubles\FakeOrderNotifier;
use Tests\Doubles\FakeCustomerRepository;
use PHPUnit\Framework\TestCase;

class PlaceOrderUseCaseTest extends TestCase
{
    private InMemoryOrderRepository $orders;
    private FakeOrderNotifier       $notifier;
    private PlaceOrderUseCase       $useCase;

    protected function setUp(): void
    {
        $this->orders   = new InMemoryOrderRepository();
        $this->notifier = new FakeOrderNotifier();
        $customers      = new FakeCustomerRepository(['customer-123']); // Mövcud customer

        $this->useCase = new PlaceOrderUseCase(
            $this->orders,
            $customers,
            $this->notifier,
        );
    }

    public function test_places_order_successfully(): void
    {
        $response = $this->useCase->execute(new PlaceOrderRequest(
            customerId: 'customer-123',
            items: [
                ['product_id' => 'prod-1', 'quantity' => 2, 'unit_price_cents' => 5000],
            ]
        ));

        $this->assertNotEmpty($response->orderId);
        $this->assertEquals('confirmed', $response->status);
        $this->assertEquals(10000, $response->totalAmountCents);
        $this->assertTrue($this->notifier->wasNotified($response->orderId));
    }

    public function test_fails_for_unknown_customer(): void
    {
        $this->expectException(CustomerNotFoundException::class);

        $this->useCase->execute(new PlaceOrderRequest(
            customerId: 'unknown-customer',
            items: [['product_id' => 'prod-1', 'quantity' => 1, 'unit_price_cents' => 5000]]
        ));
    }
}
```

## Praktik Tapşırıqlar

1. **Hexagonal vs Clean vs Onion fərqi**: bu 3 arxitekturanı cədvələ kimi müqayisə edin; `PlaceOrderUseCase`-i hər birinin strukturunda haraya qoyacağınızı müəyyən edin; hansı terminologiyanı hansı arxitektura istifadə edir?

2. **Laravel modulunu Clean Architecture-a refactoring**: mövcud `OrderService` class-ını götürün; `PlaceOrderUseCase`, `PlaceOrderRequest`, `PlaceOrderResponse` yaradın; `OrderRepository` interface-ni Domain-ə köçürün; `EloquentOrderRepository`-ni Infrastructure-a yerləşdirin; `OrderController`-i adapter kimi yenidən yazın

3. **Domain test-lər**: `Order`, `Money`, `OrderItem` class-ları üçün PHPUnit test-lər yazın — Laravel boot olmadan, ms-lərdə çalışsın; `Order::confirm()` bütün edge case-ləri üçün test; `Money::add()` fərqli valyuta exception testi

4. **Multiple delivery mechanism**: eyni `PlaceOrderUseCase`-i 3 fərqli şəkildə çağırın — `OrderController` (HTTP), `PlaceOrderConsoleCommand` (CLI), `ProcessOrderJob` (Queue); Use Case heç dəyişmədən hər üçü işləsin

## Ətraflı Qeydlər

**Hexagonal vs Onion vs Clean Architecture fərqi:**

```
┌───────────────────┬──────────────────┬──────────────────┬──────────────────┐
│                   │  Hexagonal       │  Onion           │  Clean           │
├───────────────────┼──────────────────┼──────────────────┼──────────────────┤
│ Müəllif           │ Cockburn (2005)  │ Palermo (2008)   │ Uncle Bob (2012) │
│ Mərkəz terminol.  │ Port/Adapter     │ Domain Model     │ Entities         │
│ Use Case yerləşmə │ Application      │ App. Services    │ Use Cases ring   │
│ Repo interface    │ Secondary Port   │ Domain layer     │ Use Cases layer  │
│ DDD uyğunluğu     │ Yüksək           │ Çox yüksək       │ Orta-yüksək      │
│ Presenter konsepti│ Primary Adapter  │ Yoxdur           │ Interface Adapter│
│ Terminologiya     │ Texniki          │ DDD-yönümlü      │ Use Case-yönümlü │
│ Öyrənmə çətinliyi│ Orta             │ Orta-yüksək      │ Yüksək           │
└───────────────────┴──────────────────┴──────────────────┴──────────────────┘

Ortaq prinsiplər:
✅ Hamısında Dependency Rule — asılılıqlar içəriyə
✅ Hamısında Infrastructure domain-dən asılıdır, tərsinə yox
✅ Hamısında Domain/Business logic framework-dən müstəqil
✅ Hamısında interface-lar vasitəsilə loose coupling
```

**Dependency Rule tətbiq etmək üçün `deptrac`:**

```yaml
# deptrac.yaml
parameters:
  paths: ["app/"]
  exclude_files: []
  layers:
    - name: Domain
      collectors:
        - type: className
          regex: '^App\\Domain\\'
    - name: Application
      collectors:
        - type: className
          regex: '^App\\Application\\'
    - name: Infrastructure
      collectors:
        - type: className
          regex: '^App\\Infrastructure\\'
    - name: Presentation
      collectors:
        - type: className
          regex: '^App\\Presentation\\'

  ruleset:
    Domain:       # Domain heç bir şeydən asılı ola bilməz
      - ~
    Application:  # Application yalnız Domain-dən
      - Domain
    Infrastructure: # Infrastructure hər şeyi bilir
      - Domain
      - Application
    Presentation: # Presentation hər şeyi bilir
      - Domain
      - Application
      - Infrastructure
```

## Əlaqəli Mövzular

- [Hexagonal Architecture](05-hexagonal-architecture.md) — Ports & Adapters — Clean ilə çox oxşar prinsiplər
- [Onion Architecture](06-onion-architecture.md) — DDD-yönümlü alternativ; Domain Model mərkəzdə
- [Layered Architectures](04-layered-architectures.md) — müqayisəli baxış: ənənəvi layered → Clean
- [SOLID Prinsipləri](02-solid-principles.md) — DIP Clean Architecture-un əsasıdır
- [DDD](../ddd/01-ddd.md) — Entities qatı DDD Entity, VO, Aggregate-lərinə uyğun gəlir
- [Aggregates](../ddd/04-ddd-aggregates.md) — Domain ring-də yaşayan Aggregate root-lar
- [CQRS](../integration/01-cqrs.md) — Use Cases qatı CQRS ilə Command/Query handler-lərə bölünür
- [Event Sourcing](../integration/02-event-sourcing.md) — Domain events Entities qatında, Event Store Infrastructure-da
- [Repository Pattern](../laravel/01-repository-pattern.md) — Domain-da interface, Infrastructure-da implementasiya
- [Command/Query Bus](../laravel/08-command-query-bus.md) — Use Cases CQRS ilə Command/Query handler-lərə çevrilir
- [ADR](../general/06-architecture-decision-records.md) — Clean Architecture tətbiqi əsaslandırmaq üçün ADR yazın
- [Technical Debt](../general/05-technical-debt.md) — Clean Architecture-siz böyümüş layihənin texniki borcu
