# Hexagonal Architecture (Ports & Adapters) (Senior)

## Mündəricat
1. [Ports & Adapters Konsepti](#ports--adapters-konsepti)
2. [Primary vs Secondary Adapters](#primary-vs-secondary-adapters)
3. [Onion Arxitekturası ilə Fərq](#onion-arxitekturası-ilə-fərq)
4. [Dependency Inversion](#dependency-inversion)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Ports & Adapters Konsepti

```
Alistair Cockburn (2005) tərəfindən təklif edilib.
Məqsəd: Application core-u xarici dünyadan tam izolyasiya et.

"Application bir hexagon kimidir.
 Hər tərəf bir port — bir inteqrasiya nöqtəsi."

         ┌─────────────────────────────────────────┐
         │                                         │
HTTP ────┤ Port(API) ──► Application Core          │
         │                           │             │
CLI  ────┤ Port(CLI)               Port(DB)────────┤──── PostgreSQL
         │                           │             │
Tests────┤ Port(Test)             Port(Email)───────┤──── SendGrid
         │                           │             │
         │                        Port(Queue)──────┤──── RabbitMQ
         │                                         │
         └─────────────────────────────────────────┘

Əsas prinsip:
  Application Core heç bir xarici texnologiyanı bilmir.
  DB nədir? Bilmir.
  HTTP nədir? Bilmir.
  Yalnız Port (interface) vasitəsilə danışır.
```

---

## Primary vs Secondary Adapters

```
Primary (Driving) Adapters:
  Application-ı "sürən" tərəf.
  Outside → Application Core

  Nümunələr:
    HTTP Controller
    CLI Command
    Test
    Message Consumer

Secondary (Driven) Adapters:
  Application-ın "sürdüyü" tərəf.
  Application Core → Outside

  Nümunələr:
    DB Repository (MySQL, PostgreSQL, InMemory)
    Email Sender (SMTP, SendGrid, Log)
    Cache (Redis, Array, Null)
    Queue Publisher (RabbitMQ, SQS, Sync)

┌─────────────────────────────────────────────────────┐
│                                                     │
│  Primary            Application Core  Secondary    │
│  Adapters           ┌─────────────┐   Adapters     │
│                     │             │                 │
│  HTTP Controller ──►│  Use Cases  │──► DB Adapter  │
│  CLI Command     ──►│  (Domain)   │──► Email Sender│
│  Queue Consumer  ──►│             │──► Cache       │
│  Test            ──►│             │                 │
│                     └─────────────┘                 │
│                                                     │
└─────────────────────────────────────────────────────┘

Test zamanı:
  Production:  HTTP Controller → Use Case → MySQL Adapter
  Test:        Test Driver    → Use Case → InMemory Adapter
  Eyni Use Case, fərqli adapter!
```

---

## Onion Arxitekturası ilə Fərq

```
Onion Architecture:
  ┌──────────────────────────────┐
  │      Infrastructure          │
  │  ┌──────────────────────┐   │
  │  │    Application       │   │
  │  │  ┌──────────────┐   │   │
  │  │  │    Domain    │   │   │
  │  │  └──────────────┘   │   │
  │  └──────────────────────┘   │
  └──────────────────────────────┘
  Qatlara görə təşkil edilir.
  Inside → Outside dependency direction.

Hexagonal Architecture:
  "Tərəflər" var (driver/driven).
  Qatlar yox, ports var.
  Driver ports: application girişi
  Driven ports: application çıxışı

Oxşarlıqlar:
  ✓ Hər ikisi dependency inversion tələb edir
  ✓ Hər ikisi core-u xariciliyindən ayırır
  ✓ Hər ikisi testability-ni artırır

Fərqlər:
  Hexagonal: "kim sürür, kim sürülür" fokusudur
  Onion: layer hierarchy fokusudur
  Hexagonal daha abstrakt, Onion daha konkret qat quruluşu
```

---

## Dependency Inversion

```
Hexagonal-ın özəyi: Dependency Inversion Principle (SOLID D).

"High-level modules should not depend on low-level modules.
 Both should depend on abstractions."

Yanlış (Direct dependency):
  OrderService → MySQLOrderRepository ← concrete class!
  
  OrderService MySQL bilir → test çətin, dəyişiklik çətin

Düzgün (Port/Interface):
  OrderService → OrderRepositoryInterface (port)
                       ↑
               MySQLOrderRepository (adapter) implements
               InMemoryOrderRepository (adapter) implements

  OrderService yalnız interface bilir.
  Adapter istənilən vaxt dəyişdirilə bilər.

Port (interface) application core-da saxlanır.
Adapter infrastructure-da saxlanır.
```

---

## PHP İmplementasiyası

```
Qovluq strukturu:

src/
├── Application/           ← Use Cases (Driving/Driven port-larla danışır)
│   ├── CreateOrder/
│   │   ├── CreateOrderCommand.php
│   │   └── CreateOrderHandler.php
│   └── GetOrderStatus/
│       └── GetOrderStatusHandler.php
│
├── Domain/                ← Pure business logic (heç nə bilmir)
│   ├── Order/
│   │   ├── Order.php
│   │   ├── OrderId.php
│   │   └── OrderStatus.php
│   └── Port/              ← Driven Ports (interfaces)
│       ├── OrderRepositoryInterface.php
│       ├── PaymentGatewayInterface.php
│       └── EmailSenderInterface.php
│
└── Infrastructure/        ← Adapters
    ├── Http/              ← Primary Adapters
    │   └── OrderController.php
    ├── Persistence/       ← Secondary Adapters
    │   ├── DoctrineOrderRepository.php
    │   └── InMemoryOrderRepository.php
    ├── Payment/
    │   ├── StripePaymentGateway.php
    │   └── FakePaymentGateway.php
    └── Email/
        ├── SmtpEmailSender.php
        └── LogEmailSender.php
```

```php
<?php
// Domain/Port — Driven Port (interface)
namespace App\Domain\Port;

interface OrderRepositoryInterface
{
    public function findById(OrderId $id): ?Order;
    public function save(Order $order): void;
    public function findByCustomer(CustomerId $customerId): array;
}

interface PaymentGatewayInterface
{
    public function charge(Money $amount, PaymentMethod $method): PaymentResult;
    public function refund(PaymentId $paymentId, Money $amount): void;
}
```

```php
<?php
// Application — Use Case
namespace App\Application\CreateOrder;

use App\Domain\Port\OrderRepositoryInterface;
use App\Domain\Port\PaymentGatewayInterface;

class CreateOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,    // Port — bilmir hansı adapter
        private PaymentGatewayInterface  $payments,  // Port — bilmir hansı adapter
    ) {}

    public function handle(CreateOrderCommand $command): OrderId
    {
        $order = Order::create(
            $command->customerId,
            $command->items,
        );

        $paymentResult = $this->payments->charge(
            $order->getTotal(),
            $command->paymentMethod,
        );

        $order->markAsPaid($paymentResult->getPaymentId());
        $this->orders->save($order);

        return $order->getId();
    }
}
```

```php
<?php
// Infrastructure — Secondary Adapter (Doctrine)
namespace App\Infrastructure\Persistence;

use App\Domain\Port\OrderRepositoryInterface;

class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findById(OrderId $id): ?Order
    {
        return $this->em->find(Order::class, $id->toString());
    }

    public function save(Order $order): void
    {
        $this->em->persist($order);
        $this->em->flush();
    }
}

// Infrastructure — Secondary Adapter (InMemory - test üçün)
class InMemoryOrderRepository implements OrderRepositoryInterface
{
    private array $orders = [];

    public function findById(OrderId $id): ?Order
    {
        return $this->orders[$id->toString()] ?? null;
    }

    public function save(Order $order): void
    {
        $this->orders[$order->getId()->toString()] = $order;
    }
}
```

```php
<?php
// Test — Primary Adapter (test driver)
class CreateOrderTest extends TestCase
{
    public function test_creates_order_successfully(): void
    {
        // InMemory adapters ilə test — DB lazım deyil!
        $orders   = new InMemoryOrderRepository();
        $payments = new FakePaymentGateway();

        $handler = new CreateOrderHandler($orders, $payments);
        $orderId = $handler->handle(new CreateOrderCommand(...));

        $order = $orders->findById($orderId);
        $this->assertEquals('paid', $order->getStatus()->value());
    }
}
```

---

## İntervyu Sualları

- Hexagonal Architecture-da "Port" nədir? "Adapter" nədir?
- Primary vs Secondary adapter fərqi nədir? Nümunə verin.
- Hexagonal vs Onion Architecture — əsas fərq nədir?
- Application Core-un Infrastructure-dan heç nə bilməsi niyə vacibdir?
- Test zamanı InMemory adapter-in faydası nədir?
- `OrderRepositoryInterface`-i Infrastructure yox, Domain-də saxlamağın əsas səbəbi?
- Yeni bir payment gateway əlavə etmək üçün nəyi dəyişmək lazımdır?
