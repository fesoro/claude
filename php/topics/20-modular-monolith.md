# Modulyar Monolit Arxitektura

## Mündəricat
1. [Modulyar Monolit nədir](#modulyar-monolit-nədir)
2. [Monolit vs Modulyar Monolit vs Microservices](#monolit-vs-modulyar-monolit-vs-microservices)
3. [Module Boundaries (Modul Sərhədləri)](#module-boundaries-modul-sərhədləri)
4. [Module Communication (Modullar Arası Əlaqə)](#module-communication-modullar-arası-əlaqə)
5. [Shared Kernel](#shared-kernel)
6. [Module Independence (Modul Müstəqilliyi)](#module-independence-modul-müstəqilliyi)
7. [Laravel-də Modulyar Monolit](#laravel-də-modulyar-monolit)
8. [nwidart/laravel-modules Paketi](#nwidartlaravel-modules-paketi)
9. [Database per Module vs Shared Database](#database-per-module-vs-shared-database)
10. [Module Testing](#module-testing)
11. [Module Dependency Management](#module-dependency-management)
12. [Modulyar Monolit-dən Microservices-ə Keçid](#modulyar-monolit-dən-microservices-ə-keçid)
13. [Real-world Nümunə: E-commerce Sistemi](#real-world-nümunə-e-commerce-sistemi)
14. [Best Practices](#best-practices)
15. [İntervyu Sualları və Cavabları](#intervyu-sualları-və-cavabları)

---

## Modulyar Monolit nədir

Modulyar Monolit (Modular Monolith) - tətbiqin **bir deploy vahidi** olaraq qaldığı, lakin daxildə **aydın sərhədlərlə ayrılmış müstəqil modullara** bölündüyü arxitektura yanaşmasıdır. Klassik monolitin sadəliyini və microservices-in təşkilati üstünlüklərini birləşdirir.

**Əsas prinsiplər:**
- **Bir deploy vahidi** - bütün modullar eyni prosesdə işləyir, eyni anda deploy olunur
- **Aydın modul sərhədləri** - hər modulun öz bounded context-i var
- **Yüksək koheziya (High Cohesion)** - bir moduldakı kodlar bir-biri ilə sıx bağlıdır
- **Aşağı əlaqələndirmə (Low Coupling)** - modullar bir-birindən minimum asılıdır
- **Müstəqil inkişaf** - komandalar müxtəlif modullar üzərində paralel işləyə bilir

**Sadə analogiya:** Böyük bir şirkəti düşünün. Klassik monolit - hər kəsin bir otaqda oturduğu şirkətdir; hamı hər şeyi bilir, lakin xaos yaranır. Microservices - hər şöbənin ayrıca binada olduğu şirkətdir; müstəqillik var, lakin əlaqə çətindir. Modulyar Monolit isə - hər şöbənin öz otağı olan, lakin eyni binada yerləşən şirkətdir; həm müstəqillik, həm asan əlaqə var.

```
Klassik Monolit:
┌─────────────────────────────────┐
│  Hər şey bir yerdə, sərhəd yox  │
│  Controller → Service → Model    │
│  Spaghetti code riski yüksək    │
└─────────────────────────────────┘

Modulyar Monolit:
┌─────────────────────────────────────────┐
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ │
│  │  Order   │ │ Payment  │ │Inventory │ │
│  │  Module  │ │  Module  │ │  Module  │ │
│  │          │ │          │ │          │ │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ │
│       │    Events   │   Events   │       │
│       └─────────────┴────────────┘       │
│            Bir deploy vahidi             │
└─────────────────────────────────────────┘

Microservices:
┌──────────┐   ┌──────────┐   ┌──────────┐
│  Order   │   │ Payment  │   │Inventory │
│ Service  │──▶│ Service  │──▶│ Service  │
│(Ayrı DB) │   │(Ayrı DB) │   │(Ayrı DB) │
└──────────┘   └──────────┘   └──────────┘
  Ayrı deploy   Ayrı deploy   Ayrı deploy
```

---

## Monolit vs Modulyar Monolit vs Microservices

### Klassik Monolit

```
Üstünlükləri:
✅ Sadə inkişaf və deploy
✅ Sadə debugging
✅ Sadə transaction idarəetməsi
✅ Bir verilənlər bazası
✅ Aşağı operational complexity

Mənfi cəhətləri:
❌ Big Ball of Mud riski
❌ Scaling çətinliyi
❌ Texnologiya lock-in
❌ Böyük komandalar üçün çətin
❌ Bir dəyişiklik hər şeyi təsir edə bilər
❌ Uzun build/deploy vaxtları
```

### Modulyar Monolit

```
Üstünlükləri:
✅ Monolitin sadəliyi (bir deploy, bir DB)
✅ Microservices-in təşkilati üstünlükləri (modul sərhədləri)
✅ Sadə transaction idarəetməsi
✅ Refactoring asanlığı
✅ Microservices-ə keçid üçün hazır fundament
✅ Network latency yoxdur (in-process calls)
✅ Distributed systems problemləri yoxdur
✅ Komandalar arası paralel inkişaf

Mənfi cəhətləri:
❌ Bir deploy vahidi (bir modulun update-i hamını təsir edir)
❌ Modul sərhədlərini qorumaq üçün intizam lazımdır
❌ Müstəqil scaling çətindir
❌ Texnologiya hər modul üçün eyni olmalıdır
```

### Microservices

```
Üstünlükləri:
✅ Müstəqil deploy
✅ Müstəqil scaling
✅ Texnologiya diversity
✅ Fault isolation
✅ Kiçik, anlaşılan servisler

Mənfi cəhətləri:
❌ Distributed systems complexity
❌ Network latency
❌ Data consistency çətinliyi
❌ Operational overhead (monitoring, logging, tracing)
❌ Service discovery, circuit breaker, retry lazımdır
❌ Debugging çətinliyi
❌ İlkin inkişaf vaxtı çox
```

### Müqayisə Cədvəli

```
┌─────────────────┬───────────┬──────────────────┬───────────────┐
│   Xüsusiyyət    │  Monolit  │ Modulyar Monolit │ Microservices │
├─────────────────┼───────────┼──────────────────┼───────────────┤
│ Deploy           │ Bir vahid │ Bir vahid        │ Müstəqil      │
│ Scaling          │ Bütöv     │ Bütöv            │ Müstəqil      │
│ DB               │ Bir       │ Bir/Ayrı schema  │ Ayrı DB       │
│ Communication    │ Direct    │ Interface/Event  │ Network/API   │
│ Transaction      │ ACID      │ ACID             │ Eventual      │
│ Komanda ölçüsü   │ Kiçik     │ Orta             │ Böyük         │
│ Complexity       │ Aşağı     │ Orta             │ Yüksək        │
│ Startup vaxtı   │ 1-3 ay    │ 2-4 ay           │ 6-12 ay       │
│ Operational cost │ Aşağı     │ Aşağı            │ Yüksək        │
│ Tech diversity   │ Yox       │ Yox              │ Var           │
│ Refactoring      │ Çətin     │ Asan             │ Çox çətin     │
└─────────────────┴───────────┴──────────────────┴───────────────┘
```

### Hansını seçməli?

*Hansını seçməli? üçün kod nümunəsi:*
```php
// Seçim qaydaları:

// 1. Klassik Monolit - kiçik layihə, kiçik komanda (1-5 nəfər)
if ($teamSize <= 5 && $projectComplexity === 'low') {
    return 'Klassik Monolit';
}

// 2. Modulyar Monolit - orta/böyük layihə, orta komanda (5-30 nəfər)
if ($teamSize <= 30 && $projectComplexity === 'medium-high') {
    return 'Modulyar Monolit';
}

// 3. Microservices - çox böyük layihə, böyük komanda (30+ nəfər)
if ($teamSize > 30 && $projectComplexity === 'very-high' && $hasDevOpsExpertise) {
    return 'Microservices';
}

// Vacib: Əksər hallarda Modulyar Monolit ilə başlamaq ən düzgün qərardır.
// Microservices-ə ehtiyac yarandıqda asanlıqla keçid etmək mümkündür.
```

---

## Module Boundaries (Modul Sərhədləri)

Modul sərhədlərinin düzgün müəyyən olunması Modulyar Monolit-in ən vacib aspektidir. Səhv sərhədlər bütün arxitekturanın pozulmasına gətirib çıxarır.

### Bounded Context əsasında Sərhədlər

DDD-nin (Domain-Driven Design) Bounded Context konsepti modul sərhədlərini müəyyən etmək üçün ən yaxşı yanaşmadır:

*DDD-nin (Domain-Driven Design) Bounded Context konsepti modul sərhədlə üçün kod nümunəsi:*
```php
// Hər modul öz Bounded Context-ini əks etdirir

// Order Module - sifariş prosesi ilə bağlı hər şey
// Bu modulda "Müştəri" = "Sifarişçi" (yalnız ad, ünvan, telefon lazımdır)
namespace Modules\Order;

// User Module - istifadəçi idarəetməsi ilə bağlı hər şey
// Bu modulda "Müştəri" = "User" (email, parol, rol, permission lazımdır)
namespace Modules\User;

// Payment Module - ödəniş prosesi ilə bağlı hər şey
// Bu modulda "Müştəri" = "Ödəyici" (kart məlumatları, ödəniş tarixi lazımdır)
namespace Modules\Payment;
```

### Sərhəd Qaydaları

*Sərhəd Qaydaları üçün kod nümunəsi:*
```php
// QAYDA 1: Modul yalnız öz public interface-i vasitəsilə əlaqə qurmalıdır
// ✅ Düzgün
$orderService = app(OrderServiceInterface::class);
$order = $orderService->getOrderById($orderId);

// ❌ Səhv - başqa modulun daxili sinfinə birbaşa müraciət
use Modules\Order\Models\Order;
$order = Order::find($orderId);

// QAYDA 2: Modul başqa modulun database cədvəlinə birbaşa müraciət etməməlidir
// ✅ Düzgün - interface vasitəsilə
$userName = app(UserServiceInterface::class)->getUserName($userId);

// ❌ Səhv - başqa modulun cədvəlinə birbaşa SQL
DB::table('users')->where('id', $userId)->value('name');

// QAYDA 3: Hər modulun öz modeli olmalıdır (eyni cədvələ aid olsa belə)
// Order modulunda User-in sadələşdirilmiş versiyası
namespace Modules\Order\Models;

class OrderCustomer
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}

// User modulunda User-in tam versiyası
namespace Modules\User\Models;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role', /* ... */];
}
```

### Fiziki Sərhədlər (Enforcement)

*Fiziki Sərhədlər (Enforcement) üçün kod nümunəsi:*
```php
// ArchUnit-stilində testlər ilə sərhədlərin pozulmasını yoxlamaq
// (phpat/phpat paketi ilə)

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ModuleBoundaryTest
{
    /**
     * Order module yalnız öz Contract-ləri vasitəsilə əlaqə qurmalıdır.
     * Başqa modulların daxili siniflərinə birbaşa müraciət qadağandır.
     */
    public function test_order_module_does_not_depend_on_payment_internals(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Modules\\Order'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Modules\\Payment\\Models'),
                Selector::inNamespace('Modules\\Payment\\Services'),
                Selector::inNamespace('Modules\\Payment\\Repositories'),
            );
    }

    /**
     * Bütün modullar yalnız digər modulların Contracts namespace-inə müraciət edə bilər.
     */
    public function test_modules_only_use_contracts(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Modules\\Order'))
            ->canOnlyDependOn()
            ->classes(
                Selector::inNamespace('Modules\\Order'),              // öz kodu
                Selector::inNamespace('Modules\\Payment\\Contracts'),  // contract-lər
                Selector::inNamespace('Modules\\User\\Contracts'),     // contract-lər
                Selector::inNamespace('Illuminate'),                   // Laravel
                Selector::inNamespace('Shared'),                       // shared kernel
            );
    }
}
```

---

## Module Communication (Modullar Arası Əlaqə)

Modullar arası əlaqə iki əsas yolla qurulur: **Sinxron** və **Asinxron**.

### 1. Sinxron Əlaqə (Interface/Contract vasitəsilə)

Modul başqa modula "indi cavab lazımdır" deyə müraciət edəndə sinxron əlaqə istifadə olunur. Bu, PHP interface-ləri (contracts) vasitəsilə həyata keçirilir.

*Modul başqa modula "indi cavab lazımdır" deyə müraciət edəndə sinxron  üçün kod nümunəsi:*
```php
// ==========================================
// Payment modulunun public contract-i
// ==========================================
namespace Modules\Payment\Contracts;

interface PaymentServiceInterface
{
    /**
     * Ödəniş prosesini başlatmaq.
     *
     * @throws PaymentFailedException
     */
    public function processPayment(PaymentRequest $request): PaymentResult;

    /**
     * Ödənişin statusunu yoxlamaq.
     */
    public function getPaymentStatus(string $transactionId): PaymentStatus;

    /**
     * Geri ödəniş (refund) etmək.
     *
     * @throws RefundFailedException
     */
    public function refund(string $transactionId, int $amountInCents): RefundResult;
}

// ==========================================
// Payment Request DTO (Contract-in bir hissəsi)
// ==========================================
namespace Modules\Payment\Contracts\DTOs;

final readonly class PaymentRequest
{
    public function __construct(
        public int $orderId,
        public int $amountInCents,
        public string $currency,
        public string $paymentMethod,
        public array $metadata = [],
    ) {}
}

// ==========================================
// Payment Result DTO
// ==========================================
namespace Modules\Payment\Contracts\DTOs;

final readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $transactionId,
        public PaymentStatus $status,
        public ?string $errorMessage = null,
    ) {}
}

// ==========================================
// Payment modulunun daxili implementasiyası
// ==========================================
namespace Modules\Payment\Services;

use Modules\Payment\Contracts\PaymentServiceInterface;
use Modules\Payment\Contracts\DTOs\PaymentRequest;
use Modules\Payment\Contracts\DTOs\PaymentResult;

final class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentRepository $repository,
    ) {}

    public function processPayment(PaymentRequest $request): PaymentResult
    {
        $payment = $this->repository->create([
            'order_id' => $request->orderId,
            'amount' => $request->amountInCents,
            'currency' => $request->currency,
            'method' => $request->paymentMethod,
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $gatewayResult = $this->gateway->charge(
                amount: $request->amountInCents,
                currency: $request->currency,
                method: $request->paymentMethod,
                metadata: $request->metadata,
            );

            $payment->update([
                'transaction_id' => $gatewayResult->transactionId,
                'status' => PaymentStatus::Completed,
            ]);

            return new PaymentResult(
                success: true,
                transactionId: $gatewayResult->transactionId,
                status: PaymentStatus::Completed,
            );
        } catch (GatewayException $e) {
            $payment->update(['status' => PaymentStatus::Failed]);

            return new PaymentResult(
                success: false,
                transactionId: '',
                status: PaymentStatus::Failed,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        $payment = $this->repository->findByTransactionId($transactionId);
        return $payment?->status ?? PaymentStatus::NotFound;
    }

    public function refund(string $transactionId, int $amountInCents): RefundResult
    {
        // Refund logikası...
    }
}

// ==========================================
// Order modulundan Payment moduluna sinxron müraciət
// ==========================================
namespace Modules\Order\Services;

use Modules\Payment\Contracts\PaymentServiceInterface;
use Modules\Payment\Contracts\DTOs\PaymentRequest;

final class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly PaymentServiceInterface $paymentService, // Interface inject olunur
    ) {}

    public function placeOrder(PlaceOrderCommand $command): Order
    {
        $order = $this->orderRepository->create($command);

        // Sinxron əlaqə - Payment moduluna interface vasitəsilə müraciət
        $paymentResult = $this->paymentService->processPayment(
            new PaymentRequest(
                orderId: $order->id,
                amountInCents: $order->total_in_cents,
                currency: 'USD',
                paymentMethod: $command->paymentMethod,
            )
        );

        if (!$paymentResult->success) {
            $order->update(['status' => OrderStatus::PaymentFailed]);
            throw new OrderPaymentFailedException($paymentResult->errorMessage);
        }

        $order->update([
            'status' => OrderStatus::Paid,
            'transaction_id' => $paymentResult->transactionId,
        ]);

        return $order;
    }
}
```

### 2. Asinxron Əlaqə (Events vasitəsilə)

Modul bir hadisə (event) yayımlayır, digər modullar bu hadisəyə qulaq asır. Publisher listener-ləri tanımır.

*Modul bir hadisə (event) yayımlayır, digər modullar bu hadisəyə qulaq  üçün kod nümunəsi:*
```php
// ==========================================
// Shared kernel-də event base class
// ==========================================
namespace Shared\Events;

abstract class DomainEvent
{
    public readonly string $eventId;
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->eventId = (string) \Illuminate\Support\Str::uuid();
        $this->occurredAt = new \DateTimeImmutable();
    }
}

// ==========================================
// Order modulunun yayımladığı event-lər
// ==========================================
namespace Modules\Order\Events;

use Shared\Events\DomainEvent;

final class OrderPlaced extends DomainEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly int $totalInCents,
        public readonly array $items,
    ) {
        parent::__construct();
    }
}

final class OrderCancelled extends DomainEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $reason,
        public readonly string $transactionId,
    ) {
        parent::__construct();
    }
}

// ==========================================
// Payment modulu OrderPlaced event-inə qulaq asır
// ==========================================
namespace Modules\Payment\Listeners;

use Modules\Order\Events\OrderPlaced;

final class InitiatePaymentOnOrderPlaced
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $this->paymentService->processPayment(
            new PaymentRequest(
                orderId: $event->orderId,
                amountInCents: $event->totalInCents,
                currency: 'USD',
                paymentMethod: 'default',
            )
        );
    }
}

// ==========================================
// Inventory modulu OrderPlaced event-inə qulaq asır
// ==========================================
namespace Modules\Inventory\Listeners;

use Modules\Order\Events\OrderPlaced;

final class ReserveStockOnOrderPlaced
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        foreach ($event->items as $item) {
            $this->stockService->reserve(
                productId: $item['product_id'],
                quantity: $item['quantity'],
                orderId: $event->orderId,
            );
        }
    }
}

// ==========================================
// Notification modulu OrderPlaced event-inə qulaq asır
// ==========================================
namespace Modules\Notification\Listeners;

use Modules\Order\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendOrderConfirmationEmail implements ShouldQueue
{
    public $queue = 'notifications';

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $this->notificationService->sendOrderConfirmation(
            userId: $event->userId,
            orderId: $event->orderId,
        );
    }
}

// ==========================================
// Event-in yayımlanması
// ==========================================
namespace Modules\Order\Services;

final class OrderService
{
    public function placeOrder(PlaceOrderCommand $command): Order
    {
        $order = $this->orderRepository->create($command);

        // Asinxron əlaqə - event yayımlanır, kim qulaq asır, bilmirik
        event(new OrderPlaced(
            orderId: $order->id,
            userId: $command->userId,
            totalInCents: $order->total_in_cents,
            items: $order->items->toArray(),
        ));

        return $order;
    }
}
```

### Sinxron vs Asinxron Seçim Qaydaları

*Sinxron vs Asinxron Seçim Qaydaları üçün kod nümunəsi:*
```php
// Sinxron istifadə et - cavab lazım olanda:
// - Ödəniş nəticəsini gözləyəndə
// - Stok yoxlaması edəndə
// - İstifadəçi məlumatını əldə edəndə
$paymentResult = $paymentService->processPayment($request); // Cavab lazımdır!

// Asinxron istifadə et - cavab lazım olmayanda:
// - Email göndərəndə
// - Log yazanda
// - Statistika yeniləyəndə
// - Cache invalidation edəndə
event(new OrderPlaced($order)); // Nəticəni gözləmirəm
```

---

## Shared Kernel

Shared Kernel - bütün modulların ortaq istifadə etdiyi kod bazasıdır. Bu, modullar arasında təkrarlanan kodu azaltmaq və ortaq standartları təmin etmək üçün istifadə olunur.

*Shared Kernel - bütün modulların ortaq istifadə etdiyi kod bazasıdır.  üçün kod nümunəsi:*
```php
// ==========================================
// Shared Kernel strukturu
// ==========================================
// Shared/
// ├── Events/
// │   └── DomainEvent.php
// ├── ValueObjects/
// │   ├── Money.php
// │   ├── Email.php
// │   └── Address.php
// ├── DTOs/
// │   └── PaginatedResult.php
// ├── Exceptions/
// │   ├── DomainException.php
// │   └── NotFoundException.php
// ├── Contracts/
// │   ├── HasUuid.php
// │   └── Auditable.php
// ├── Traits/
// │   ├── HasUuidTrait.php
// │   └── AuditableTrait.php
// └── Enums/
//     ├── Currency.php
//     └── Country.php

// ==========================================
// Money Value Object - bütün modullar istifadə edir
// ==========================================
namespace Shared\ValueObjects;

final readonly class Money
{
    public function __construct(
        private int $amountInCents,
        private Currency $currency = Currency::USD,
    ) {
        if ($amountInCents < 0) {
            throw new \InvalidArgumentException('Məbləğ mənfi ola bilməz');
        }
    }

    public static function fromDollars(float $dollars, Currency $currency = Currency::USD): self
    {
        return new self((int) round($dollars * 100), $currency);
    }

    public function amountInCents(): int
    {
        return $this->amountInCents;
    }

    public function amountInDollars(): float
    {
        return $this->amountInCents / 100;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    public function multiply(int $multiplier): self
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
        $this->ensureSameCurrency($other);
        return $this->amountInCents > $other->amountInCents;
    }

    public function format(): string
    {
        return number_format($this->amountInDollars(), 2) . ' ' . $this->currency->value;
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Müxtəlif valyutalarla əməliyyat mümkün deyil: {$this->currency->value} vs {$other->currency->value}"
            );
        }
    }
}

// ==========================================
// Shared Enum
// ==========================================
namespace Shared\Enums;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case AZN = 'AZN';
    case GBP = 'GBP';
    case TRY = 'TRY';
}

// ==========================================
// Base Domain Event
// ==========================================
namespace Shared\Events;

abstract class DomainEvent
{
    public readonly string $eventId;
    public readonly \DateTimeImmutable $occurredAt;
    public readonly string $eventType;

    public function __construct()
    {
        $this->eventId = (string) \Illuminate\Support\Str::uuid();
        $this->occurredAt = new \DateTimeImmutable();
        $this->eventType = static::class;
    }

    /**
     * Event-i array-ə çevir (serialization üçün).
     */
    abstract public function toArray(): array;
}

// ==========================================
// Shared Exception
// ==========================================
namespace Shared\Exceptions;

abstract class DomainException extends \DomainException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

// ==========================================
// Paginated Result DTO
// ==========================================
namespace Shared\DTOs;

/**
 * @template T
 */
final readonly class PaginatedResult
{
    /**
     * @param array<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public int $lastPage,
    ) {}

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }
}
```

**Shared Kernel qaydaları:**
- Shared Kernel-ə **yalnız həqiqətən ortaq olan** kodlar əlavə olunmalıdır
- Shared Kernel **minimal** olmalıdır - şübhə varsa, modula aid edin
- Shared Kernel-in dəyişdirilməsi **bütün modulları** təsir edəcəyi üçün çox ehtiyatlı olunmalıdır
- Shared Kernel-ə **business logic** əlavə edilməməlidir
- Yalnız Value Objects, DTOs, base siniflər, enums və utility-lər olmalıdır

---

## Module Independence (Modul Müstəqilliyi)

Hər modul mümkün qədər müstəqil olmalıdır. Bu müstəqillik bir neçə səviyyədə təmin olunur:

### 1. Data Ownership (Məlumat Mülkiyyəti)

*1. Data Ownership (Məlumat Mülkiyyəti) üçün kod nümunəsi:*
```php
// Hər modul öz məlumatlarının sahibidir.
// Başqa modul bu məlumatları yalnız contract vasitəsilə əldə edə bilər.

// ❌ Səhv - Order modulu User cədvəlinə birbaşa müraciət edir
namespace Modules\Order\Services;

class OrderService
{
    public function getOrderWithUser(int $orderId): array
    {
        $order = Order::with('user')->find($orderId); // User modelinə birbaşa müraciət!
        return [
            'order' => $order,
            'user_email' => $order->user->email, // User modulunun daxili strukturuna asılılıq!
        ];
    }
}

// ✅ Düzgün - Order modulu User contract vasitəsilə məlumat alır
namespace Modules\Order\Services;

use Modules\User\Contracts\UserServiceInterface;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserServiceInterface $userService,
    ) {}

    public function getOrderWithUser(int $orderId): OrderWithCustomerDTO
    {
        $order = $this->orderRepository->find($orderId);
        $customer = $this->userService->getBasicInfo($order->user_id);

        return new OrderWithCustomerDTO(
            order: $order,
            customerName: $customer->name,
            customerEmail: $customer->email,
        );
    }
}
```

### 2. Code Ownership (Kod Mülkiyyəti)

*2. Code Ownership (Kod Mülkiyyəti) üçün kod nümunəsi:*
```php
// Hər modulun öz:
// - Models, Migrations, Seeders
// - Controllers, Requests, Resources
// - Services, Repositories
// - Events, Listeners, Jobs
// - Tests
// - Config
// - Routes
// faylları var

// Modul daxilində tam azadlıq - istədiyin pattern-i istifadə et
// Bir modul Repository Pattern, digəri Action Pattern istifadə edə bilər

// Order Module - Repository Pattern istifadə edir
namespace Modules\Order\Repositories;

final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function find(int $id): ?Order
    {
        return Order::find($id);
    }
}

// Payment Module - Action Pattern istifadə edir
namespace Modules\Payment\Actions;

final class ProcessPaymentAction
{
    public function execute(PaymentRequest $request): PaymentResult
    {
        // ...
    }
}
```

### 3. Lifecycle Independence (Lifecycle Müstəqilliyi)

*3. Lifecycle Independence (Lifecycle Müstəqilliyi) üçün kod nümunəsi:*
```php
// Hər modulun öz Service Provider-i var
// Modul deaktiv edildikdə, digər modullar işləməyə davam etməlidir

namespace Modules\Payment\Providers;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Payment modulu öz binding-lərini qeydiyyatdan keçirir
        $this->app->bind(
            PaymentServiceInterface::class,
            PaymentService::class,
        );

        $this->app->bind(
            PaymentGateway::class,
            fn () => new StripePaymentGateway(
                config('modules.payment.stripe_key'),
                config('modules.payment.stripe_secret'),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->mergeConfigFrom(__DIR__ . '/../Config/payment.php', 'modules.payment');

        // Event listener qeydiyyatı
        Event::listen(OrderPlaced::class, InitiatePaymentOnOrderPlaced::class);
        Event::listen(OrderCancelled::class, RefundPaymentOnOrderCancelled::class);
    }
}
```

---

## Laravel-də Modulyar Monolit

### Folder Structure (Qovluq Strukturu)

```
app/
├── Shared/                          # Shared Kernel
│   ├── Events/
│   │   └── DomainEvent.php
│   ├── ValueObjects/
│   │   ├── Money.php
│   │   └── Email.php
│   ├── DTOs/
│   │   └── PaginatedResult.php
│   ├── Exceptions/
│   │   └── DomainException.php
│   ├── Enums/
│   │   └── Currency.php
│   └── Traits/
│       └── HasUuidTrait.php
│
modules/                             # Bütün modullar burada
├── Order/
│   ├── Contracts/                   # Public API (digər modullar bunu görür)
│   │   ├── OrderServiceInterface.php
│   │   └── DTOs/
│   │       ├── OrderDTO.php
│   │       └── CreateOrderRequest.php
│   ├── Config/
│   │   └── order.php
│   ├── Database/
│   │   ├── Migrations/
│   │   │   ├── 2024_01_01_000001_create_orders_table.php
│   │   │   └── 2024_01_01_000002_create_order_items_table.php
│   │   ├── Seeders/
│   │   │   └── OrderSeeder.php
│   │   └── Factories/
│   │       └── OrderFactory.php
│   ├── Events/
│   │   ├── OrderPlaced.php
│   │   ├── OrderCancelled.php
│   │   └── OrderShipped.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── OrderController.php
│   │   ├── Requests/
│   │   │   ├── StoreOrderRequest.php
│   │   │   └── UpdateOrderRequest.php
│   │   └── Resources/
│   │       ├── OrderResource.php
│   │       └── OrderCollection.php
│   ├── Listeners/
│   │   └── UpdateOrderStatusOnPaymentCompleted.php
│   ├── Models/
│   │   ├── Order.php
│   │   └── OrderItem.php
│   ├── Repositories/
│   │   ├── OrderRepositoryInterface.php
│   │   └── EloquentOrderRepository.php
│   ├── Services/
│   │   └── OrderService.php
│   ├── Providers/
│   │   └── OrderServiceProvider.php
│   ├── Routes/
│   │   ├── api.php
│   │   └── web.php
│   └── Tests/
│       ├── Unit/
│       │   └── OrderServiceTest.php
│       └── Feature/
│           └── OrderApiTest.php
│
├── Payment/
│   ├── Contracts/
│   │   ├── PaymentServiceInterface.php
│   │   └── DTOs/
│   │       ├── PaymentRequest.php
│   │       └── PaymentResult.php
│   ├── Config/
│   │   └── payment.php
│   ├── Database/
│   │   └── Migrations/
│   │       └── 2024_01_01_000001_create_payments_table.php
│   ├── Events/
│   │   ├── PaymentCompleted.php
│   │   └── PaymentFailed.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── PaymentWebhookController.php
│   │   └── Requests/
│   ├── Listeners/
│   │   ├── InitiatePaymentOnOrderPlaced.php
│   │   └── RefundPaymentOnOrderCancelled.php
│   ├── Models/
│   │   └── Payment.php
│   ├── Services/
│   │   ├── PaymentService.php
│   │   └── Gateways/
│   │       ├── PaymentGateway.php
│   │       ├── StripeGateway.php
│   │       └── PayPalGateway.php
│   ├── Providers/
│   │   └── PaymentServiceProvider.php
│   ├── Routes/
│   │   └── api.php
│   └── Tests/
│
├── Inventory/
│   ├── Contracts/
│   ├── Config/
│   ├── Database/
│   ├── Events/
│   ├── Http/
│   ├── Listeners/
│   ├── Models/
│   ├── Services/
│   ├── Providers/
│   ├── Routes/
│   └── Tests/
│
├── User/
│   ├── Contracts/
│   ├── Config/
│   ├── Database/
│   ├── Events/
│   ├── Http/
│   ├── Models/
│   ├── Services/
│   ├── Providers/
│   ├── Routes/
│   └── Tests/
│
└── Notification/
    ├── Contracts/
    ├── Config/
    ├── Database/
    ├── Events/
    ├── Http/
    ├── Models/
    ├── Services/
    ├── Providers/
    ├── Routes/
    └── Tests/
```

### composer.json - Autoloading

*composer.json - Autoloading üçün kod nümunəsi:*
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Shared\\": "app/Shared/",
            "Modules\\Order\\": "modules/Order/",
            "Modules\\Payment\\": "modules/Payment/",
            "Modules\\Inventory\\": "modules/Inventory/",
            "Modules\\User\\": "modules/User/",
            "Modules\\Notification\\": "modules/Notification/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/",
            "Modules\\Order\\Tests\\": "modules/Order/Tests/",
            "Modules\\Payment\\Tests\\": "modules/Payment/Tests/",
            "Modules\\Inventory\\Tests\\": "modules/Inventory/Tests/",
            "Modules\\User\\Tests\\": "modules/User/Tests/",
            "Modules\\Notification\\Tests\\": "modules/Notification/Tests/"
        }
    }
}
```

### Module Service Providers

*Module Service Providers üçün kod nümunəsi:*
```php
// ==========================================
// Əsas AppServiceProvider-da modulların qeydiyyatı
// ==========================================
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Bütün modul provider-lərinin siyahısı.
     * Modul deaktiv etmək üçün sadəcə burada comment edin.
     */
    private array $moduleProviders = [
        \Modules\User\Providers\UserServiceProvider::class,
        \Modules\Order\Providers\OrderServiceProvider::class,
        \Modules\Payment\Providers\PaymentServiceProvider::class,
        \Modules\Inventory\Providers\InventoryServiceProvider::class,
        \Modules\Notification\Providers\NotificationServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->moduleProviders as $provider) {
            $this->app->register($provider);
        }
    }
}

// ==========================================
// Daha yaxşı yanaşma: config/modules.php ilə idarəetmə
// ==========================================
// config/modules.php
return [
    'enabled' => [
        'User' => [
            'provider' => \Modules\User\Providers\UserServiceProvider::class,
            'enabled' => true,
        ],
        'Order' => [
            'provider' => \Modules\Order\Providers\OrderServiceProvider::class,
            'enabled' => true,
        ],
        'Payment' => [
            'provider' => \Modules\Payment\Providers\PaymentServiceProvider::class,
            'enabled' => true,
        ],
        'Inventory' => [
            'provider' => \Modules\Inventory\Providers\InventoryServiceProvider::class,
            'enabled' => true,
        ],
        'Notification' => [
            'provider' => \Modules\Notification\Providers\NotificationServiceProvider::class,
            'enabled' => env('MODULE_NOTIFICATION_ENABLED', true),
        ],
    ],
];

// AppServiceProvider
namespace App\Providers;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $modules = config('modules.enabled', []);

        foreach ($modules as $name => $module) {
            if ($module['enabled'] ?? false) {
                $this->app->register($module['provider']);
            }
        }
    }
}

// ==========================================
// Order Module Service Provider (tam nümunə)
// ==========================================
namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Order\Contracts\OrderServiceInterface;
use Modules\Order\Models\Order;
use Modules\Order\Policies\OrderPolicy;
use Modules\Order\Repositories\EloquentOrderRepository;
use Modules\Order\Repositories\OrderRepositoryInterface;
use Modules\Order\Services\OrderService;
use Modules\Payment\Events\PaymentCompleted;
use Modules\Order\Listeners\UpdateOrderStatusOnPaymentCompleted;

final class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Interface binding-lər
        $this->app->bind(OrderServiceInterface::class, OrderService::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);

        // Config merge
        $this->mergeConfigFrom(__DIR__ . '/../Config/order.php', 'modules.order');
    }

    public function boot(): void
    {
        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        // Views (əgər lazımsa)
        // $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'order');

        // Event Listeners - digər modulların event-lərinə qulaq as
        Event::listen(PaymentCompleted::class, UpdateOrderStatusOnPaymentCompleted::class);

        // Policies
        Gate::policy(Order::class, OrderPolicy::class);

        // Artisan commands (əgər varsa)
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Order\Console\PruneOldOrdersCommand::class,
            ]);
        }
    }
}
```

### Module Routing

*Module Routing üçün kod nümunəsi:*
```php
// ==========================================
// modules/Order/Routes/api.php
// ==========================================
use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\OrderController;

Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {

        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/{order}', [OrderController::class, 'show']);
            Route::put('/{order}', [OrderController::class, 'update']);
            Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
            Route::get('/{order}/status', [OrderController::class, 'status']);
        });
    });

// ==========================================
// modules/Payment/Routes/api.php
// ==========================================
use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentWebhookController;

Route::prefix('api/v1')
    ->middleware(['api'])
    ->group(function () {

        // Webhook - auth lazım deyil, signature ilə təsdiq olunur
        Route::post('/payments/webhook/stripe', [PaymentWebhookController::class, 'handleStripe'])
            ->middleware('verify.stripe.signature');

        // Authenticated routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/payments/{transactionId}', [PaymentWebhookController::class, 'show']);
            Route::post('/payments/{transactionId}/refund', [PaymentWebhookController::class, 'refund']);
        });
    });

// ==========================================
// Order Controller (tam nümunə)
// ==========================================
namespace Modules\Order\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Order\Contracts\OrderServiceInterface;
use Modules\Order\Http\Requests\StoreOrderRequest;
use Modules\Order\Http\Resources\OrderResource;

final class OrderController
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $orders = $this->orderService->getUserOrders(
            userId: auth()->id(),
            page: request()->integer('page', 1),
            perPage: request()->integer('per_page', 15),
        );

        return OrderResource::collection($orders->items)
            ->additional([
                'meta' => [
                    'total' => $orders->total,
                    'per_page' => $orders->perPage,
                    'current_page' => $orders->currentPage,
                    'last_page' => $orders->lastPage,
                ],
            ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->placeOrder(
            new PlaceOrderCommand(
                userId: auth()->id(),
                items: $request->validated('items'),
                shippingAddress: $request->validated('shipping_address'),
                paymentMethod: $request->validated('payment_method'),
            )
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $order): OrderResource
    {
        $orderData = $this->orderService->getOrderById($order);

        return new OrderResource($orderData);
    }

    public function cancel(int $order): JsonResponse
    {
        $this->orderService->cancelOrder(
            orderId: $order,
            userId: auth()->id(),
            reason: request('reason', 'Müştəri tərəfindən ləğv edildi'),
        );

        return response()->json(['message' => 'Sifariş ləğv edildi']);
    }
}
```

### Module Migrations

*Module Migrations üçün kod nümunəsi:*
```php
// ==========================================
// modules/Order/Database/Migrations/2024_01_01_000001_create_orders_table.php
// ==========================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order modulu öz cədvəllərini prefix ilə yarada bilər.
     * Bu, cədvəllərin hansı modula aid olduğunu aydın edir.
     */
    public function up(): void
    {
        Schema::create('order_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status')->default('pending');
            $table->integer('subtotal_cents');
            $table->integer('tax_cents')->default(0);
            $table->integer('shipping_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('total_cents');
            $table->string('currency', 3)->default('USD');
            $table->json('shipping_address');
            $table->string('payment_method');
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key yox! User modulu ilə yalnız contract vasitəsilə əlaqə.
            // user_id sadəcə integer olaraq saxlanılır.
            // $table->foreign('user_id')->references('id')->on('users'); // BUNU ETMƏ!
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('order_orders')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('product_id'); // Inventory modulunun product-i
            $table->string('product_name');            // Snapshot - adı burada saxla
            $table->integer('unit_price_cents');        // Snapshot - qiyməti burada saxla
            $table->integer('quantity');
            $table->integer('total_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_orders');
    }
};

// ==========================================
// modules/Payment/Database/Migrations/2024_01_01_000001_create_payments_table.php
// ==========================================
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('order_id')->index(); // FK yox, sadəcə ID
            $table->string('transaction_id')->unique()->nullable();
            $table->string('gateway'); // stripe, paypal, etc.
            $table->string('method');  // card, bank_transfer, etc.
            $table->integer('amount_cents');
            $table->string('currency', 3);
            $table->string('status'); // pending, completed, failed, refunded
            $table->json('gateway_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->integer('refunded_amount_cents')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')
                ->constrained('payment_payments');
            $table->string('refund_transaction_id')->unique();
            $table->integer('amount_cents');
            $table->string('reason');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_payments');
    }
};
```

### Module Configs

*Module Configs üçün kod nümunəsi:*
```php
// ==========================================
// modules/Payment/Config/payment.php
// ==========================================
return [
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'stripe'),

    'gateways' => [
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'currency' => env('STRIPE_CURRENCY', 'usd'),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
        ],
    ],

    'retry' => [
        'max_attempts' => 3,
        'delay_seconds' => 5,
    ],

    'refund' => [
        'max_days' => 30,         // Refund üçün maksimum gün sayı
        'auto_approve_under' => 5000, // 50$ altı avtomatik təsdiq
    ],
];

// ==========================================
// modules/Order/Config/order.php
// ==========================================
return [
    'statuses' => [
        'pending' => 'Gözlənilir',
        'paid' => 'Ödənildi',
        'processing' => 'Hazırlanır',
        'shipped' => 'Göndərildi',
        'delivered' => 'Çatdırıldı',
        'cancelled' => 'Ləğv edildi',
    ],

    'auto_cancel_after_hours' => 24,  // Ödənilməmiş sifarişlər 24 saat sonra ləğv
    'max_items_per_order' => 50,
    'min_order_amount_cents' => 100,   // Minimum 1$

    'notifications' => [
        'send_confirmation_email' => true,
        'send_shipped_sms' => true,
    ],
];

// Config-ə müraciət:
$maxDays = config('modules.payment.refund.max_days'); // 30
$gateway = config('modules.payment.default_gateway'); // stripe
```

### Module Events

*Module Events üçün kod nümunəsi:*
```php
// ==========================================
// modules/Order/Events/OrderPlaced.php
// ==========================================
namespace Modules\Order\Events;

use Shared\Events\DomainEvent;

final class OrderPlaced extends DomainEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderUuid,
        public readonly int $userId,
        public readonly int $totalInCents,
        public readonly string $currency,
        public readonly string $paymentMethod,
        public readonly array $items, // [{product_id, quantity, unit_price_cents}]
        public readonly array $shippingAddress,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_uuid' => $this->orderUuid,
            'user_id' => $this->userId,
            'total_in_cents' => $this->totalInCents,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod,
            'items' => $this->items,
            'shipping_address' => $this->shippingAddress,
        ];
    }
}

// ==========================================
// modules/Payment/Events/PaymentCompleted.php
// ==========================================
namespace Modules\Payment\Events;

use Shared\Events\DomainEvent;

final class PaymentCompleted extends DomainEvent
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly string $transactionId,
        public readonly int $amountInCents,
        public readonly string $currency,
        public readonly string $gateway,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'transaction_id' => $this->transactionId,
            'amount_in_cents' => $this->amountInCents,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
        ];
    }
}

// ==========================================
// modules/Payment/Events/PaymentFailed.php
// ==========================================
namespace Modules\Payment\Events;

use Shared\Events\DomainEvent;

final class PaymentFailed extends DomainEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $reason,
        public readonly string $gateway,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'reason' => $this->reason,
            'gateway' => $this->gateway,
            'error_code' => $this->errorCode,
        ];
    }
}

// ==========================================
// Inventory modulu stok dəyişikliyini bildirir
// ==========================================
namespace Modules\Inventory\Events;

use Shared\Events\DomainEvent;

final class StockDepleted extends DomainEvent
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $remainingQuantity,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'remaining_quantity' => $this->remainingQuantity,
        ];
    }
}
```

### Inter-module Communication Patterns

*Inter-module Communication Patterns üçün kod nümunəsi:*
```php
// ==========================================
// PATTERN 1: Mediator Pattern - Event Bus vasitəsilə
// ==========================================
// Modullar bir-birini tanımır, yalnız event bus vasitəsilə əlaqə qururlar

namespace App\Shared\EventBus;

interface EventBusInterface
{
    public function publish(DomainEvent $event): void;
    public function subscribe(string $eventClass, string $listenerClass): void;
}

final class LaravelEventBus implements EventBusInterface
{
    public function publish(DomainEvent $event): void
    {
        event($event);
    }

    public function subscribe(string $eventClass, string $listenerClass): void
    {
        Event::listen($eventClass, $listenerClass);
    }
}

// İstifadəsi:
final class OrderService
{
    public function __construct(
        private readonly EventBusInterface $eventBus,
    ) {}

    public function placeOrder(PlaceOrderCommand $command): Order
    {
        $order = $this->createOrder($command);

        $this->eventBus->publish(new OrderPlaced(
            orderId: $order->id,
            orderUuid: $order->uuid,
            userId: $command->userId,
            totalInCents: $order->total_cents,
            currency: $order->currency,
            paymentMethod: $command->paymentMethod,
            items: $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
            ])->toArray(),
            shippingAddress: $order->shipping_address,
        ));

        return $order;
    }
}

// ==========================================
// PATTERN 2: Facade/Gateway Pattern - Sinxron əlaqə
// ==========================================
// Hər modul digər modullar üçün facade təqdim edir

namespace Modules\User\Contracts;

/**
 * User modulunun public API-si.
 * Digər modullar yalnız bu interface vasitəsilə User moduluna müraciət edə bilər.
 */
interface UserServiceInterface
{
    public function getBasicInfo(int $userId): UserBasicInfoDTO;
    public function getUserEmail(int $userId): string;
    public function userExists(int $userId): bool;
    public function getUsersByIds(array $userIds): array;
}

namespace Modules\User\Contracts\DTOs;

final readonly class UserBasicInfoDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,
    ) {}
}

// User modulunun implementasiyası
namespace Modules\User\Services;

use Modules\User\Contracts\UserServiceInterface;
use Modules\User\Contracts\DTOs\UserBasicInfoDTO;
use Modules\User\Models\User;

final class UserService implements UserServiceInterface
{
    public function getBasicInfo(int $userId): UserBasicInfoDTO
    {
        $user = User::findOrFail($userId);

        return new UserBasicInfoDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
        );
    }

    public function getUserEmail(int $userId): string
    {
        return User::where('id', $userId)->value('email')
            ?? throw new UserNotFoundException($userId);
    }

    public function userExists(int $userId): bool
    {
        return User::where('id', $userId)->exists();
    }

    public function getUsersByIds(array $userIds): array
    {
        return User::whereIn('id', $userIds)
            ->get()
            ->map(fn (User $user) => new UserBasicInfoDTO(
                id: $user->id,
                name: $user->name,
                email: $user->email,
                phone: $user->phone,
            ))
            ->all();
    }
}

// Order modulunda istifadə:
namespace Modules\Order\Services;

use Modules\User\Contracts\UserServiceInterface;

final class OrderService
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    ) {}

    public function placeOrder(PlaceOrderCommand $command): Order
    {
        // Əvvəlcə istifadəçinin mövcudluğunu yoxla
        if (!$this->userService->userExists($command->userId)) {
            throw new \DomainException('İstifadəçi tapılmadı');
        }

        // Sifarişi yarat...
    }
}

// ==========================================
// PATTERN 3: Anti-Corruption Layer
// ==========================================
// Xarici modulun məlumatlarını öz modulunun dilinə çevirir

namespace Modules\Order\AntiCorruption;

use Modules\User\Contracts\UserServiceInterface;
use Modules\Order\Models\OrderCustomer;

/**
 * User modulunun məlumatını Order modulunun dilinə çevir.
 * Order modulu "User" yox, "Customer" anlayışını istifadə edir.
 */
final class CustomerTranslator
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    ) {}

    public function getCustomer(int $userId): OrderCustomer
    {
        $userInfo = $this->userService->getBasicInfo($userId);

        // User kontekstindən Order kontekstinə çevrilmə
        return new OrderCustomer(
            id: $userInfo->id,
            name: $userInfo->name,
            email: $userInfo->email,
        );
    }
}
```

### Module Contracts/Interfaces

*Module Contracts/Interfaces üçün kod nümunəsi:*
```php
// ==========================================
// Hər modulun Contracts qovluğu - digər modullar YALNIZ bunu görür
// ==========================================

// modules/Inventory/Contracts/InventoryServiceInterface.php
namespace Modules\Inventory\Contracts;

interface InventoryServiceInterface
{
    /**
     * Məhsulun stokda olub-olmadığını yoxlamaq.
     */
    public function isInStock(int $productId, int $quantity): bool;

    /**
     * Məhsulları sifariş üçün rezerv etmək.
     *
     * @param array<array{product_id: int, quantity: int}> $items
     * @throws InsufficientStockException
     */
    public function reserveStock(array $items, int $orderId): StockReservationResult;

    /**
     * Rezervasiyanı ləğv etmək (sifariş ləğv edildikdə).
     */
    public function releaseReservation(int $orderId): void;

    /**
     * Rezervasiyanı təsdiq etmək (ödəniş tamamlandıqda).
     */
    public function confirmReservation(int $orderId): void;

    /**
     * Məhsulun mövcud stok sayını əldə etmək.
     */
    public function getAvailableStock(int $productId): int;

    /**
     * Bir neçə məhsulun stok məlumatını əldə etmək.
     *
     * @param int[] $productIds
     * @return array<int, ProductStockDTO>
     */
    public function getStockForProducts(array $productIds): array;
}

// modules/Inventory/Contracts/DTOs/StockReservationResult.php
namespace Modules\Inventory\Contracts\DTOs;

final readonly class StockReservationResult
{
    public function __construct(
        public bool $success,
        public string $reservationId,
        public array $reservedItems,
        public array $failedItems = [],
    ) {}
}

// modules/Inventory/Contracts/DTOs/ProductStockDTO.php
namespace Modules\Inventory\Contracts\DTOs;

final readonly class ProductStockDTO
{
    public function __construct(
        public int $productId,
        public string $productName,
        public int $totalQuantity,
        public int $reservedQuantity,
        public int $availableQuantity,
        public bool $isInStock,
    ) {}
}

// modules/Inventory/Contracts/Exceptions/InsufficientStockException.php
namespace Modules\Inventory\Contracts\Exceptions;

use Shared\Exceptions\DomainException;

final class InsufficientStockException extends DomainException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
    ) {
        parent::__construct(
            message: "Kifayət qədər stok yoxdur. Məhsul: {$productId}, Tələb: {$requestedQuantity}, Mövcud: {$availableQuantity}",
            errorCode: 'INSUFFICIENT_STOCK',
            context: [
                'product_id' => $productId,
                'requested' => $requestedQuantity,
                'available' => $availableQuantity,
            ],
        );
    }
}

// ==========================================
// Notification Module Contract
// ==========================================
namespace Modules\Notification\Contracts;

interface NotificationServiceInterface
{
    public function sendOrderConfirmation(int $userId, int $orderId): void;
    public function sendPaymentReceipt(int $userId, int $paymentId): void;
    public function sendShippingUpdate(int $userId, int $orderId, string $trackingNumber): void;
    public function sendLowStockAlert(int $productId, int $remainingQuantity): void;
}
```

---

## nwidart/laravel-modules Paketi

`nwidart/laravel-modules` Laravel-də modulyar arxitektura qurmaq üçün ən populyar paketdir. Modul yaratma, idarəetmə və autoloading prosesini avtomatlaşdırır.

### Quraşdırma və Konfiqurasiya

*Quraşdırma və Konfiqurasiya üçün kod nümunəsi:*
```bash
# Paketi quraşdır
composer require nwidart/laravel-modules

# Config faylını publish et
php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"

# Yeni modul yarat
php artisan module:make Order
php artisan module:make Payment
php artisan module:make Inventory
php artisan module:make User
php artisan module:make Notification
```

### Yaradılan Modul Strukturu

```
Modules/
└── Order/
    ├── app/
    │   ├── Http/
    │   │   ├── Controllers/
    │   │   │   └── OrderController.php
    │   │   ├── Middleware/
    │   │   └── Requests/
    │   ├── Models/
    │   ├── Providers/
    │   │   ├── OrderServiceProvider.php
    │   │   └── RouteServiceProvider.php
    │   └── Policies/
    ├── config/
    │   └── config.php
    ├── database/
    │   ├── factories/
    │   ├── migrations/
    │   └── seeders/
    ├── resources/
    │   ├── assets/
    │   └── views/
    ├── routes/
    │   ├── api.php
    │   └── web.php
    ├── tests/
    │   ├── Feature/
    │   └── Unit/
    ├── composer.json
    ├── module.json
    └── vite.config.js
```

### module.json

*module.json üçün kod nümunəsi:*
```json
{
    "name": "Order",
    "alias": "order",
    "description": "Sifariş idarəetmə modulu",
    "keywords": ["order", "e-commerce"],
    "priority": 0,
    "providers": [
        "Modules\\Order\\Providers\\OrderServiceProvider",
        "Modules\\Order\\Providers\\RouteServiceProvider"
    ],
    "files": [],
    "requires": ["User"]
}
```

### Artisan Əmrləri

*Artisan Əmrləri üçün kod nümunəsi:*
```bash
# Modul əmrləri
php artisan module:list                    # Bütün modulları siyahıla
php artisan module:enable Order            # Modulu aktiv et
php artisan module:disable Notification    # Modulu deaktiv et
php artisan module:delete Payment          # Modulu sil

# Modul daxilində resurslar yarat
php artisan module:make-controller OrderController Order
php artisan module:make-model Order Order
php artisan module:make-migration create_orders_table Order
php artisan module:make-seeder OrderSeeder Order
php artisan module:make-factory OrderFactory Order
php artisan module:make-request StoreOrderRequest Order
php artisan module:make-resource OrderResource Order
php artisan module:make-event OrderPlaced Order
php artisan module:make-listener HandleOrderPlaced Order
php artisan module:make-job ProcessOrder Order
php artisan module:make-middleware CheckOrderOwner Order
php artisan module:make-policy OrderPolicy Order
php artisan module:make-command PruneOrders Order
php artisan module:make-test OrderServiceTest Order

# Migration əmrləri
php artisan module:migrate Order           # Order modulunun migration-larını işlət
php artisan module:migrate-rollback Order  # Rollback et
php artisan module:seed Order              # Seeder işlət
```

### Config

*Config üçün kod nümunəsi:*
```php
// config/modules.php (nwidart paketi üçün)
return [
    'namespace' => 'Modules',
    'stubs' => [
        'enabled' => false,
        'path' => base_path('vendor/nwidart/laravel-modules/src/Commands/stubs'),
    ],
    'paths' => [
        'modules' => base_path('Modules'),
        'assets' => public_path('modules'),
        'migration' => base_path('database/migrations'),
        'generator' => [
            'config' => ['path' => 'config', 'generate' => true],
            'controller' => ['path' => 'app/Http/Controllers', 'generate' => true],
            'model' => ['path' => 'app/Models', 'generate' => true],
            'repository' => ['path' => 'app/Repositories', 'generate' => true],
            'service' => ['path' => 'app/Services', 'generate' => true],
            'event' => ['path' => 'app/Events', 'generate' => true],
            'listener' => ['path' => 'app/Listeners', 'generate' => true],
            'migration' => ['path' => 'database/migrations', 'generate' => true],
            'seeder' => ['path' => 'database/seeders', 'generate' => true],
            'factory' => ['path' => 'database/factories', 'generate' => true],
            'routes' => ['path' => 'routes', 'generate' => true],
            'test' => ['path' => 'tests', 'generate' => true],
        ],
    ],
    'activators' => [
        'file' => [
            'class' => \Nwidart\Modules\Activators\FileActivator::class,
            'statuses-file' => base_path('modules_statuses.json'),
        ],
    ],
];
```

### nwidart ilə Custom Modul Strukturu

*nwidart ilə Custom Modul Strukturu üçün kod nümunəsi:*
```php
// Öz strukturunuzu yaratmaq üçün stubs-ları dəyişdirin
// və ya modulda Contracts qovluğunu əl ilə əlavə edin:

// Modules/Order/Contracts/OrderServiceInterface.php
namespace Modules\Order\Contracts;

interface OrderServiceInterface
{
    public function placeOrder(array $data): OrderDTO;
    public function getOrderById(int $id): OrderDTO;
}

// Modules/Order/app/Providers/OrderServiceProvider.php
namespace Modules\Order\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Order\Contracts\OrderServiceInterface;
use Modules\Order\app\Services\OrderService;

class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderServiceInterface::class, OrderService::class);
    }
}
```

---

## Database per Module vs Shared Database

### Strategiya 1: Shared Database, Ayrı Schemas/Prefix

*Strategiya 1: Shared Database, Ayrı Schemas/Prefix üçün kod nümunəsi:*
```php
// Bütün modullar eyni verilənlər bazasını istifadə edir,
// lakin cədvəl adları prefix ilə ayrılır.

// Order modulu: order_orders, order_items
// Payment modulu: payment_payments, payment_refunds
// Inventory modulu: inventory_products, inventory_stock_movements
// User modulu: user_users, user_roles, user_permissions

// Model-də cədvəl adını təyin et:
namespace Modules\Order\Models;

class Order extends Model
{
    protected $table = 'order_orders';
}

namespace Modules\Payment\Models;

class Payment extends Model
{
    protected $table = 'payment_payments';
}

// Üstünlükləri:
// ✅ Sadə əməliyyat - bir DB idarə etmək asandır
// ✅ ACID transactions modullar arası mümkündür
// ✅ JOIN əməliyyatları mümkündür (amma tövsiyə olunmur!)
// ✅ Backup/restore sadədir

// Mənfi cəhətləri:
// ❌ Modullar DB səviyyəsində tam müstəqil deyil
// ❌ Bir modulun migration-u digərinə təsir edə bilər
// ❌ Microservices-ə keçid zamanı DB-ni ayırmaq lazımdır
```

### Strategiya 2: Schema per Module (PostgreSQL)

*Strategiya 2: Schema per Module (PostgreSQL) üçün kod nümunəsi:*
```php
// PostgreSQL-də hər modul üçün ayrı schema istifadə et
// Bu, Shared DB ilə Separate DB arasında yaxşı kompromisdir

// Migration-da schema yarat:
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS order_schema');

        Schema::connection('pgsql')->create('order_schema.orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // ...
        });
    }
};

// Model-də connection və cədvəl:
namespace Modules\Order\Models;

class Order extends Model
{
    protected $table = 'order_schema.orders';
}

// Üstünlükləri:
// ✅ Daha yaxşı izolyasiya
// ✅ Schema-level permissions
// ✅ Cross-schema queries hələ də mümkündür
// ✅ Microservices-ə keçid asandır

// Mənfi cəhətləri:
// ❌ Yalnız PostgreSQL dəstəkləyir
// ❌ Əlavə konfiqurasiya lazımdır
```

### Strategiya 3: Separate Database per Module

*Strategiya 3: Separate Database per Module üçün kod nümunəsi:*
```php
// Hər modul üçün ayrı verilənlər bazası
// config/database.php
return [
    'connections' => [
        'order' => [
            'driver' => 'mysql',
            'host' => env('DB_ORDER_HOST', '127.0.0.1'),
            'database' => env('DB_ORDER_DATABASE', 'ecommerce_orders'),
            'username' => env('DB_ORDER_USERNAME', 'root'),
            'password' => env('DB_ORDER_PASSWORD', ''),
        ],
        'payment' => [
            'driver' => 'mysql',
            'host' => env('DB_PAYMENT_HOST', '127.0.0.1'),
            'database' => env('DB_PAYMENT_DATABASE', 'ecommerce_payments'),
            'username' => env('DB_PAYMENT_USERNAME', 'root'),
            'password' => env('DB_PAYMENT_PASSWORD', ''),
        ],
        'inventory' => [
            'driver' => 'mysql',
            'host' => env('DB_INVENTORY_HOST', '127.0.0.1'),
            'database' => env('DB_INVENTORY_DATABASE', 'ecommerce_inventory'),
            'username' => env('DB_INVENTORY_USERNAME', 'root'),
            'password' => env('DB_INVENTORY_PASSWORD', ''),
        ],
    ],
];

// Model-də connection:
namespace Modules\Order\Models;

class Order extends Model
{
    protected $connection = 'order';
    protected $table = 'orders';
}

namespace Modules\Payment\Models;

class Payment extends Model
{
    protected $connection = 'payment';
    protected $table = 'payments';
}

// Migration-da connection:
return new class extends Migration
{
    protected $connection = 'order';

    public function up(): void
    {
        Schema::connection('order')->create('orders', function (Blueprint $table) {
            $table->id();
            // ...
        });
    }
};

// Üstünlükləri:
// ✅ Tam izolyasiya
// ✅ Müstəqil backup/restore
// ✅ Müstəqil scaling
// ✅ Microservices-ə keçid çox asan

// Mənfi cəhətləri:
// ❌ Cross-database JOIN mümkün deyil
// ❌ Cross-database transaction çətin (2PC lazımdır)
// ❌ Operational complexity artır
// ❌ Daha çox resurs lazımdır
```

### Hansını seçməli?

```
Tövsiyə olunan yol:

1. Başlanğıcda → Shared Database + Table Prefix
   (Sadədir, ACID transaction-lar var)

2. Böyüdükcə → Schema per Module (PostgreSQL)
   (Daha yaxşı izolyasiya, amma hələ sadədir)

3. Microservices-ə keçid zamanı → Separate Database
   (Tam müstəqillik lazımdır)
```

---

## Module Testing

### Unit Tests

*Unit Tests üçün kod nümunəsi:*
```php
// ==========================================
// modules/Order/Tests/Unit/OrderServiceTest.php
// ==========================================
namespace Modules\Order\Tests\Unit;

use Modules\Inventory\Contracts\InventoryServiceInterface;
use Modules\Inventory\Contracts\DTOs\StockReservationResult;
use Modules\Order\Services\OrderService;
use Modules\Order\Repositories\OrderRepositoryInterface;
use Modules\Payment\Contracts\PaymentServiceInterface;
use Modules\User\Contracts\UserServiceInterface;
use PHPUnit\Framework\TestCase;

final class OrderServiceTest extends TestCase
{
    private OrderService $orderService;
    private OrderRepositoryInterface $orderRepository;
    private PaymentServiceInterface $paymentService;
    private InventoryServiceInterface $inventoryService;
    private UserServiceInterface $userService;

    protected function setUp(): void
    {
        parent::setUp();

        // Bütün xarici asılılıqlar mock-lanır
        // Bu, modulun müstəqil test oluna biləcəyini göstərir
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->paymentService = $this->createMock(PaymentServiceInterface::class);
        $this->inventoryService = $this->createMock(InventoryServiceInterface::class);
        $this->userService = $this->createMock(UserServiceInterface::class);

        $this->orderService = new OrderService(
            orderRepository: $this->orderRepository,
            paymentService: $this->paymentService,
            inventoryService: $this->inventoryService,
            userService: $this->userService,
        );
    }

    public function test_order_is_placed_successfully(): void
    {
        // Arrange
        $command = new PlaceOrderCommand(
            userId: 1,
            items: [
                ['product_id' => 101, 'quantity' => 2, 'unit_price_cents' => 1500],
                ['product_id' => 102, 'quantity' => 1, 'unit_price_cents' => 3000],
            ],
            shippingAddress: ['city' => 'Bakı', 'street' => 'Nizami küçəsi 10'],
            paymentMethod: 'card',
        );

        $this->userService
            ->expects($this->once())
            ->method('userExists')
            ->with(1)
            ->willReturn(true);

        $this->inventoryService
            ->expects($this->once())
            ->method('reserveStock')
            ->willReturn(new StockReservationResult(
                success: true,
                reservationId: 'res-123',
                reservedItems: [101, 102],
            ));

        $expectedOrder = $this->createOrderStub(
            id: 1,
            userId: 1,
            totalCents: 6000,
            status: 'pending',
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn($expectedOrder);

        // Act
        $order = $this->orderService->placeOrder($command);

        // Assert
        $this->assertEquals(1, $order->id);
        $this->assertEquals(6000, $order->total_cents);
        $this->assertEquals('pending', $order->status);
    }

    public function test_order_fails_when_stock_insufficient(): void
    {
        // Arrange
        $command = new PlaceOrderCommand(
            userId: 1,
            items: [['product_id' => 101, 'quantity' => 100, 'unit_price_cents' => 1500]],
            shippingAddress: ['city' => 'Bakı'],
            paymentMethod: 'card',
        );

        $this->userService
            ->method('userExists')
            ->willReturn(true);

        $this->inventoryService
            ->method('reserveStock')
            ->willThrowException(
                new InsufficientStockException(
                    productId: 101,
                    requestedQuantity: 100,
                    availableQuantity: 5,
                )
            );

        // Act & Assert
        $this->expectException(InsufficientStockException::class);
        $this->orderService->placeOrder($command);
    }

    public function test_order_fails_when_user_not_found(): void
    {
        $command = new PlaceOrderCommand(
            userId: 999,
            items: [],
            shippingAddress: [],
            paymentMethod: 'card',
        );

        $this->userService
            ->method('userExists')
            ->with(999)
            ->willReturn(false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('İstifadəçi tapılmadı');

        $this->orderService->placeOrder($command);
    }

    private function createOrderStub(int $id, int $userId, int $totalCents, string $status): object
    {
        return new class($id, $userId, $totalCents, $status) {
            public function __construct(
                public readonly int $id,
                public readonly int $user_id,
                public readonly int $total_cents,
                public readonly string $status,
            ) {}
        };
    }
}
```

### Feature / Integration Tests

*Feature / Integration Tests üçün kod nümunəsi:*
```php
// ==========================================
// modules/Order/Tests/Feature/OrderApiTest.php
// ==========================================
namespace Modules\Order\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Contracts\InventoryServiceInterface;
use Modules\Inventory\Contracts\DTOs\StockReservationResult;
use Modules\User\Models\User;
use Tests\TestCase;

final class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Xarici modul asılılıqlarını mock-la
        $this->mock(InventoryServiceInterface::class, function ($mock) {
            $mock->shouldReceive('reserveStock')
                ->andReturn(new StockReservationResult(
                    success: true,
                    reservationId: 'res-test',
                    reservedItems: [],
                ));

            $mock->shouldReceive('isInStock')->andReturn(true);
        });
    }

    public function test_user_can_create_order(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'product_id' => 1,
                        'quantity' => 2,
                        'unit_price_cents' => 2500,
                    ],
                ],
                'shipping_address' => [
                    'city' => 'Bakı',
                    'street' => 'Nizami 10',
                    'zip' => 'AZ1000',
                ],
                'payment_method' => 'card',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'uuid',
                    'status',
                    'total_cents',
                    'items',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('order_orders', [
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_can_view_own_orders(): void
    {
        // 3 sifariş yarat
        OrderFactory::new()->count(3)->create(['user_id' => $this->user->id]);
        // Başqa istifadəçinin sifarişi
        OrderFactory::new()->create(['user_id' => 999]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // Yalnız öz sifarişlərini görür
    }

    public function test_user_can_cancel_order(): void
    {
        $order = OrderFactory::new()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$order->id}/cancel", [
                'reason' => 'Fikrimi dəyişdim',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('order_orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_unauthorized_user_cannot_access_orders(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertStatus(401);
    }
}
```

### Architecture Tests

*Architecture Tests üçün kod nümunəsi:*
```php
// ==========================================
// tests/Architecture/ModuleBoundaryTest.php
// ==========================================
namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Bu testlər modul sərhədlərinin pozulmasını avtomatik aşkarlayır.
 * CI/CD pipeline-da işlədilməlidir.
 */
final class ModuleBoundaryTest
{
    /**
     * Order modulu Payment modulunun internal koduna müraciət etməməlidir.
     */
    public function test_order_does_not_access_payment_internals(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Modules\\Order'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Modules\\Payment\\Models'),
                Selector::inNamespace('Modules\\Payment\\Services'),
                Selector::inNamespace('Modules\\Payment\\Repositories'),
            )
            ->because('Order modulu Payment modulunun yalnız Contracts-ına müraciət etməlidir');
    }

    /**
     * Heç bir modul başqa modulun Models namespace-inə birbaşa müraciət etməməlidir.
     */
    public function test_no_cross_module_model_access(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Modules\\Order\\Models'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Modules\\Payment\\Models'),
                Selector::inNamespace('Modules\\Inventory\\Models'),
                Selector::inNamespace('Modules\\User\\Models'),
                Selector::inNamespace('Modules\\Notification\\Models'),
            )
            ->because('Modullar digər modulların modellərinə birbaşa müraciət etməməlidir');
    }

    /**
     * Contracts namespace-ində yalnız interface, DTO və exception olmalıdır.
     */
    public function test_contracts_are_clean(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Modules\\Order\\Contracts'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Illuminate\\Database'), // Eloquent olmamalı
                Selector::inNamespace('Illuminate\\Http'),     // HTTP olmamalı
            )
            ->because('Contracts framework-dən asılı olmamalıdır');
    }
}
```

### Modul Üçün Test Isolation

*Modul Üçün Test Isolation üçün kod nümunəsi:*
```php
// ==========================================
// Hər modul öz testlərini müstəqil işlədə bilər
// ==========================================

// phpunit.xml-ə modul test suite əlavə et:
// <testsuites>
//     <testsuite name="Order">
//         <directory>modules/Order/Tests</directory>
//     </testsuite>
//     <testsuite name="Payment">
//         <directory>modules/Payment/Tests</directory>
//     </testsuite>
//     <testsuite name="Inventory">
//         <directory>modules/Inventory/Tests</directory>
//     </testsuite>
// </testsuites>

// Yalnız bir modulun testlərini işlət:
// php artisan test --testsuite=Order
// php artisan test --testsuite=Payment
```

---

## Module Dependency Management

### Asılılıq Qrafikası

```
Doğru asılılıq qrafikası (DAG - Directed Acyclic Graph):

                    ┌──────────────┐
                    │  Notification │   ← Heç kimə asılı deyil (events qəbul edir)
                    └──────┬───────┘
                           │ listens
    ┌───────────┐   ┌──────┴──────┐   ┌────────────┐
    │   User    │   │    Order    │   │  Inventory  │
    │  Module   │   │   Module    │   │   Module    │
    └─────┬─────┘   └──┬─────┬───┘   └──────┬──────┘
          │             │     │              │
          │      uses   │     │ uses         │ listens
          │  contract   │     │ contract     │
          │             │     │              │
          │             │  ┌──┴──────┐       │
          │             │  │ Payment │       │
          │             │  │ Module  │       │
          └─────────────┘  └─────────┘       │
                                             │
                    Shared Kernel             │
              (Bütün modullar istifadə edir)  │

❌ SƏHV: Dairəvi asılılıq (Circular Dependency)
Order → Payment → Order  (BU OLMAMALIDIR!)

✅ DÜZGÜN: Bir istiqamətli asılılıq
Order → Payment Contract (sinxron)
Payment → Order Events (asinxron, event ilə)
```

### Asılılıq Qaydaları

*Asılılıq Qaydaları üçün kod nümunəsi:*
```php
// ==========================================
// QAYDA 1: Dairəvi asılılıq QADAĞANDIR
// ==========================================

// ❌ Səhv:
// OrderService depends on PaymentService
// PaymentService depends on OrderService
// Bu, sonsuz dövrə yaradır!

// ✅ Düzgün:
// OrderService depends on PaymentServiceInterface (sinxron, contract)
// PaymentModule listens to OrderPlaced event (asinxron, event)
// Asinxron əlaqə dairəvi asılılığı qırır!

// ==========================================
// QAYDA 2: Asılılıq yalnız Contract-lara olmalıdır
// ==========================================

// Order modulunun ServiceProvider-i
namespace Modules\Order\Providers;

final class OrderServiceProvider extends ServiceProvider
{
    /**
     * Bu modulun asılı olduğu contract-lar.
     * Əgər bunlar mövcud deyilsə, modul işləməyəcək.
     */
    public function register(): void
    {
        // Xarici asılılıqların mövcudluğunu yoxla
        $this->ensureDependenciesExist();

        $this->app->bind(OrderServiceInterface::class, OrderService::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
    }

    private function ensureDependenciesExist(): void
    {
        $requiredContracts = [
            \Modules\User\Contracts\UserServiceInterface::class,
            \Modules\Payment\Contracts\PaymentServiceInterface::class,
            \Modules\Inventory\Contracts\InventoryServiceInterface::class,
        ];

        foreach ($requiredContracts as $contract) {
            if (!$this->app->bound($contract)) {
                throw new \RuntimeException(
                    "Order modulu '{$contract}' contract-ına asılıdır, lakin o qeydiyyatdan keçməyib. " .
                    "Əlaqəli modulun aktiv olduğundan əmin olun."
                );
            }
        }
    }
}

// ==========================================
// QAYDA 3: Graceful Degradation - modul olmadıqda işləmək
// ==========================================

namespace Modules\Order\Services;

final class OrderService implements OrderServiceInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ?PaymentServiceInterface $paymentService = null,
        private readonly ?InventoryServiceInterface $inventoryService = null,
    ) {}

    public function placeOrder(PlaceOrderCommand $command): Order
    {
        $order = $this->orderRepository->create($command);

        // Inventory modulu aktiv deyilsə, stok yoxlamasını atla
        if ($this->inventoryService !== null) {
            $this->inventoryService->reserveStock($command->items, $order->id);
        }

        // Payment modulu aktiv deyilsə, ödənişi atla
        if ($this->paymentService !== null) {
            $this->paymentService->processPayment(
                new PaymentRequest(
                    orderId: $order->id,
                    amountInCents: $order->total_cents,
                    currency: $order->currency,
                    paymentMethod: $command->paymentMethod,
                )
            );
        }

        return $order;
    }
}

// ServiceProvider-da optional binding:
$this->app->when(OrderService::class)
    ->needs(PaymentServiceInterface::class)
    ->give(function () {
        return $this->app->bound(PaymentServiceInterface::class)
            ? $this->app->make(PaymentServiceInterface::class)
            : null;  // Modul yoxdursa null ver
    });

// ==========================================
// QAYDA 4: Null Object Pattern ilə default davranış
// ==========================================

namespace Modules\Order\Fallbacks;

use Modules\Notification\Contracts\NotificationServiceInterface;

/**
 * Notification modulu aktiv olmadıqda istifadə olunan null implementation.
 * Heç nə etmir, lakin xəta yaratmır.
 */
final class NullNotificationService implements NotificationServiceInterface
{
    public function sendOrderConfirmation(int $userId, int $orderId): void
    {
        // Notification modulu aktiv deyil, heç nə etmə
        logger()->info('Notification modulu aktiv deyil, email göndərilmir', [
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);
    }

    public function sendPaymentReceipt(int $userId, int $paymentId): void {}
    public function sendShippingUpdate(int $userId, int $orderId, string $trackingNumber): void {}
    public function sendLowStockAlert(int $productId, int $remainingQuantity): void {}
}

// ServiceProvider:
if ($this->app->bound(NotificationServiceInterface::class)) {
    // Modul aktivdir, real implementasiya istifadə olunur
} else {
    $this->app->bind(NotificationServiceInterface::class, NullNotificationService::class);
}
```

---

## Modulyar Monolit-dən Microservices-ə Keçid

### Keçid Strategiyası

```
Addım 1: Modulyar Monolit qur (düzgün sərhədlərlə)
    ↓
Addım 2: Modulları tam müstəqil et (ayrı DB, event-based communication)
    ↓
Addım 3: Ən müstəqil modulu ayır (məs: Notification)
    ↓
Addım 4: API Gateway əlavə et
    ↓
Addım 5: Daha çox modulu tədricən ayır
    ↓
Addım 6: Tam microservices arxitekturasına keçid

Vacib: Birdəfəlik "big bang" keçid ETMƏYİN!
Tədricən, bir-bir modul ayırın (Strangler Fig Pattern).
```

### Addım 1: In-process Communication-dan HTTP/Message Queue-ya keçid

*Addım 1: In-process Communication-dan HTTP/Message Queue-ya keçid üçün kod nümunəsi:*
```php
// ==========================================
// ƏVVƏL: Sinxron in-process call (Modulyar Monolit)
// ==========================================
namespace Modules\Order\Services;

use Modules\Payment\Contracts\PaymentServiceInterface;

final class OrderService
{
    public function __construct(
        private readonly PaymentServiceInterface $paymentService,
    ) {}

    public function placeOrder(PlaceOrderCommand $command): Order
    {
        // In-process call - eyni prosesdə
        $result = $this->paymentService->processPayment($request);
        // ...
    }
}

// ==========================================
// SONRA: HTTP call (Microservice olaraq ayrıldıqdan sonra)
// ==========================================
namespace Modules\Payment\Clients;

use Modules\Payment\Contracts\PaymentServiceInterface;
use Illuminate\Support\Facades\Http;

/**
 * Payment modulu artıq ayrı servisdir.
 * Eyni interface-i implement edir, lakin HTTP call vasitəsilə.
 * Order modulu heç bir dəyişiklik etmir - yalnız binding dəyişir!
 */
final class PaymentServiceHttpClient implements PaymentServiceInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    public function processPayment(PaymentRequest $request): PaymentResult
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept' => 'application/json',
        ])
        ->timeout(10)
        ->retry(3, 100)
        ->post("{$this->baseUrl}/api/v1/payments", [
            'order_id' => $request->orderId,
            'amount_cents' => $request->amountInCents,
            'currency' => $request->currency,
            'method' => $request->paymentMethod,
        ]);

        if ($response->failed()) {
            return new PaymentResult(
                success: false,
                transactionId: '',
                status: PaymentStatus::Failed,
                errorMessage: $response->json('error', 'Payment service xətası'),
            );
        }

        $data = $response->json();

        return new PaymentResult(
            success: true,
            transactionId: $data['transaction_id'],
            status: PaymentStatus::from($data['status']),
        );
    }

    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->get("{$this->baseUrl}/api/v1/payments/{$transactionId}");

        return PaymentStatus::from($response->json('status'));
    }

    public function refund(string $transactionId, int $amountInCents): RefundResult
    {
        // HTTP call...
    }
}

// ==========================================
// ServiceProvider-da binding dəyişikliyi - bu YEGANƏ dəyişiklikdir!
// ==========================================
namespace Modules\Payment\Providers;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('modules.payment.mode') === 'remote') {
            // Payment artıq ayrı servisdir - HTTP client istifadə et
            $this->app->bind(PaymentServiceInterface::class, function () {
                return new PaymentServiceHttpClient(
                    baseUrl: config('modules.payment.remote_url'),
                    apiKey: config('modules.payment.api_key'),
                );
            });
        } else {
            // Hələ monolit daxilindədir - birbaşa implementasiya
            $this->app->bind(PaymentServiceInterface::class, PaymentService::class);
        }
    }
}
```

### Addım 2: Event-ləri Message Queue-ya keçir

*Addım 2: Event-ləri Message Queue-ya keçir üçün kod nümunəsi:*
```php
// ==========================================
// ƏVVƏL: Laravel Event (in-process)
// ==========================================
event(new OrderPlaced($order)); // Eyni prosesdə dispatch olunur

// ==========================================
// SONRA: RabbitMQ/Kafka ilə (ayrı servislər arası)
// ==========================================

// Event publisher (Order servisində)
namespace Modules\Order\Events\Publishers;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMQEventPublisher
{
    public function publish(DomainEvent $event): void
    {
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
        );

        $channel = $connection->channel();
        $channel->exchange_declare('domain_events', 'topic', false, true, false);

        $message = new AMQPMessage(
            json_encode([
                'event_id' => $event->eventId,
                'event_type' => $event->eventType,
                'occurred_at' => $event->occurredAt->format('c'),
                'payload' => $event->toArray(),
            ]),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );

        $routingKey = 'order.' . class_basename($event);
        $channel->basic_publish($message, 'domain_events', $routingKey);

        $channel->close();
        $connection->close();
    }
}

// Keçid dövründə hər iki yolu dəstəklə:
final class HybridEventPublisher
{
    public function __construct(
        private readonly RabbitMQEventPublisher $rabbitMQ,
    ) {}

    public function publish(DomainEvent $event): void
    {
        // Laravel event (hələ monolit daxilindəki modullar üçün)
        event($event);

        // RabbitMQ (artıq ayrılmış servislər üçün)
        $this->rabbitMQ->publish($event);
    }
}
```

---

## Real-world Nümunə: E-commerce Sistemi

Tam bir e-commerce sisteminin modulyar monolit arxitekturası. Beş əsas modul: **User**, **Order**, **Payment**, **Inventory**, **Notification**.

### User Module - Tam Kod

*User Module - Tam Kod üçün kod nümunəsi:*
```php
// ==========================================
// modules/User/Contracts/UserServiceInterface.php
// ==========================================
namespace Modules\User\Contracts;

use Modules\User\Contracts\DTOs\UserBasicInfoDTO;
use Modules\User\Contracts\DTOs\CreateUserDTO;
use Shared\DTOs\PaginatedResult;

interface UserServiceInterface
{
    public function getBasicInfo(int $userId): UserBasicInfoDTO;
    public function getUserEmail(int $userId): string;
    public function userExists(int $userId): bool;
    public function getUsersByIds(array $userIds): array;
    public function createUser(CreateUserDTO $dto): UserBasicInfoDTO;
}

// ==========================================
// modules/User/Contracts/DTOs/UserBasicInfoDTO.php
// ==========================================
namespace Modules\User\Contracts\DTOs;

final readonly class UserBasicInfoDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?string $avatarUrl = null,
    ) {}
}

// ==========================================
// modules/User/Contracts/DTOs/CreateUserDTO.php
// ==========================================
namespace Modules\User\Contracts\DTOs;

final readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phone = null,
    ) {}
}

// ==========================================
// modules/User/Models/User.php
// ==========================================
namespace Modules\User\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'user_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_url',
        'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

// ==========================================
// modules/User/Services/UserService.php
// ==========================================
namespace Modules\User\Services;

use Modules\User\Contracts\UserServiceInterface;
use Modules\User\Contracts\DTOs\UserBasicInfoDTO;
use Modules\User\Contracts\DTOs\CreateUserDTO;
use Modules\User\Events\UserRegistered;
use Modules\User\Models\User;

final class UserService implements UserServiceInterface
{
    public function getBasicInfo(int $userId): UserBasicInfoDTO
    {
        $user = User::findOrFail($userId);

        return new UserBasicInfoDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            avatarUrl: $user->avatar_url,
        );
    }

    public function getUserEmail(int $userId): string
    {
        return User::where('id', $userId)->value('email')
            ?? throw new \RuntimeException("İstifadəçi tapılmadı: {$userId}");
    }

    public function userExists(int $userId): bool
    {
        return User::where('id', $userId)->exists();
    }

    public function getUsersByIds(array $userIds): array
    {
        return User::whereIn('id', $userIds)
            ->get()
            ->map(fn (User $user) => new UserBasicInfoDTO(
                id: $user->id,
                name: $user->name,
                email: $user->email,
                phone: $user->phone,
            ))
            ->all();
    }

    public function createUser(CreateUserDTO $dto): UserBasicInfoDTO
    {
        $user = User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
            'phone' => $dto->phone,
        ]);

        event(new UserRegistered(
            userId: $user->id,
            name: $user->name,
            email: $user->email,
        ));

        return new UserBasicInfoDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
        );
    }
}

// ==========================================
// modules/User/Events/UserRegistered.php
// ==========================================
namespace Modules\User\Events;

use Shared\Events\DomainEvent;

final class UserRegistered extends DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly string $email,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}

// ==========================================
// modules/User/Providers/UserServiceProvider.php
// ==========================================
namespace Modules\User\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\User\Contracts\UserServiceInterface;
use Modules\User\Services\UserService;

final class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->mergeConfigFrom(__DIR__ . '/../Config/user.php', 'modules.user');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}

// ==========================================
// modules/User/Http/Controllers/AuthController.php
// ==========================================
namespace Modules\User\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\User\Contracts\DTOs\CreateUserDTO;
use Modules\User\Contracts\UserServiceInterface;
use Modules\User\Http\Requests\RegisterRequest;
use Modules\User\Http\Requests\LoginRequest;
use Modules\User\Models\User;

final class AuthController
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->userService->createUser(new CreateUserDTO(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            phone: $request->validated('phone'),
        ));

        $token = User::find($user->id)->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Yanlış email və ya parol'], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserBasicInfoDTO(
                id: $user->id,
                name: $user->name,
                email: $user->email,
            ),
            'token' => $token,
        ]);
    }
}
```

### Order Module - Tam Kod

*Order Module - Tam Kod üçün kod nümunəsi:*
```php
// ==========================================
// modules/Order/Contracts/OrderServiceInterface.php
// ==========================================
namespace Modules\Order\Contracts;

use Modules\Order\Contracts\DTOs\OrderDTO;
use Modules\Order\Contracts\DTOs\PlaceOrderCommand;
use Shared\DTOs\PaginatedResult;

interface OrderServiceInterface
{
    public function placeOrder(PlaceOrderCommand $command): OrderDTO;
    public function getOrderById(int $orderId): OrderDTO;
    public function getUserOrders(int $userId, int $page, int $perPage): PaginatedResult;
    public function cancelOrder(int $orderId, int $userId, string $reason): void;
    public function updateOrderStatus(int $orderId, string $status): void;
}

// ==========================================
// modules/Order/Contracts/DTOs/OrderDTO.php
// ==========================================
namespace Modules\Order\Contracts\DTOs;

final readonly class OrderDTO
{
    public function __construct(
        public int $id,
        public string $uuid,
        public int $userId,
        public string $status,
        public int $totalCents,
        public string $currency,
        public array $items,
        public ?string $transactionId,
        public string $createdAt,
    ) {}
}

// ==========================================
// modules/Order/Contracts/DTOs/PlaceOrderCommand.php
// ==========================================
namespace Modules\Order\Contracts\DTOs;

final readonly class PlaceOrderCommand
{
    public function __construct(
        public int $userId,
        public array $items,    // [{product_id, quantity, unit_price_cents}]
        public array $shippingAddress,
        public string $paymentMethod,
        public ?string $couponCode = null,
    ) {}
}

// ==========================================
// modules/Order/Models/Order.php
// ==========================================
namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'order_orders';

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'subtotal_cents',
        'tax_cents',
        'shipping_cents',
        'discount_cents',
        'total_cents',
        'currency',
        'shipping_address',
        'payment_method',
        'transaction_id',
        'notes',
        'cancellation_reason',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'paid']);
    }
}

// ==========================================
// modules/Order/Models/OrderItem.php
// ==========================================
namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'unit_price_cents',
        'quantity',
        'total_cents',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

// ==========================================
// modules/Order/Services/OrderService.php
// ==========================================
namespace Modules\Order\Services;

use Illuminate\Support\Str;
use Modules\Inventory\Contracts\InventoryServiceInterface;
use Modules\Order\Contracts\DTOs\OrderDTO;
use Modules\Order\Contracts\DTOs\PlaceOrderCommand;
use Modules\Order\Contracts\OrderServiceInterface;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Events\OrderPlaced;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\User\Contracts\UserServiceInterface;
use Shared\DTOs\PaginatedResult;

final class OrderService implements OrderServiceInterface
{
    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly InventoryServiceInterface $inventoryService,
    ) {}

    public function placeOrder(PlaceOrderCommand $command): OrderDTO
    {
        // 1. İstifadəçini yoxla
        if (!$this->userService->userExists($command->userId)) {
            throw new \DomainException('İstifadəçi tapılmadı');
        }

        // 2. Stok yoxlaması
        foreach ($command->items as $item) {
            if (!$this->inventoryService->isInStock($item['product_id'], $item['quantity'])) {
                throw new \DomainException(
                    "Məhsul #{$item['product_id']} stokda kifayət qədər yoxdur"
                );
            }
        }

        // 3. Məbləğ hesabla
        $subtotalCents = array_reduce($command->items, function ($carry, $item) {
            return $carry + ($item['unit_price_cents'] * $item['quantity']);
        }, 0);

        $taxCents = (int) round($subtotalCents * 0.18); // 18% ƏDV
        $totalCents = $subtotalCents + $taxCents;

        // 4. Sifarişi yarat
        $order = Order::create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $command->userId,
            'status' => 'pending',
            'subtotal_cents' => $subtotalCents,
            'tax_cents' => $taxCents,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => $totalCents,
            'currency' => 'USD',
            'shipping_address' => $command->shippingAddress,
            'payment_method' => $command->paymentMethod,
        ]);

        // 5. Sifariş elementlərini yarat
        foreach ($command->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'] ?? 'Məhsul #' . $item['product_id'],
                'unit_price_cents' => $item['unit_price_cents'],
                'quantity' => $item['quantity'],
                'total_cents' => $item['unit_price_cents'] * $item['quantity'],
            ]);
        }

        // 6. Stoku rezerv et
        $this->inventoryService->reserveStock($command->items, $order->id);

        // 7. Event yayımla
        event(new OrderPlaced(
            orderId: $order->id,
            orderUuid: $order->uuid,
            userId: $command->userId,
            totalInCents: $totalCents,
            currency: 'USD',
            paymentMethod: $command->paymentMethod,
            items: $command->items,
            shippingAddress: $command->shippingAddress,
        ));

        return $this->toDTO($order->load('items'));
    }

    public function getOrderById(int $orderId): OrderDTO
    {
        $order = Order::with('items')->findOrFail($orderId);
        return $this->toDTO($order);
    }

    public function getUserOrders(int $userId, int $page = 1, int $perPage = 15): PaginatedResult
    {
        $paginator = Order::with('items')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return new PaginatedResult(
            items: $paginator->map(fn ($order) => $this->toDTO($order))->all(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function cancelOrder(int $orderId, int $userId, string $reason): void
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->firstOrFail();

        if (!$order->canBeCancelled()) {
            throw new \DomainException("Bu sifariş ləğv edilə bilməz. Status: {$order->status}");
        }

        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        // Stok rezervasiyasını ləğv et
        $this->inventoryService->releaseReservation($orderId);

        // Event yayımla
        event(new OrderCancelled(
            orderId: $order->id,
            reason: $reason,
            transactionId: $order->transaction_id ?? '',
        ));
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        $order = Order::findOrFail($orderId);

        $statusTimestamps = match ($status) {
            'paid' => ['paid_at' => now()],
            'shipped' => ['shipped_at' => now()],
            'delivered' => ['delivered_at' => now()],
            default => [],
        };

        $order->update(array_merge(['status' => $status], $statusTimestamps));
    }

    private function toDTO(Order $order): OrderDTO
    {
        return new OrderDTO(
            id: $order->id,
            uuid: $order->uuid,
            userId: $order->user_id,
            status: $order->status,
            totalCents: $order->total_cents,
            currency: $order->currency,
            items: $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit_price_cents' => $item->unit_price_cents,
                'quantity' => $item->quantity,
                'total_cents' => $item->total_cents,
            ])->toArray(),
            transactionId: $order->transaction_id,
            createdAt: $order->created_at->toISOString(),
        );
    }
}

// ==========================================
// modules/Order/Listeners/UpdateOrderStatusOnPaymentCompleted.php
// ==========================================
namespace Modules\Order\Listeners;

use Modules\Payment\Events\PaymentCompleted;
use Modules\Order\Contracts\OrderServiceInterface;
use Modules\Inventory\Contracts\InventoryServiceInterface;

final class UpdateOrderStatusOnPaymentCompleted
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
        private readonly InventoryServiceInterface $inventoryService,
    ) {}

    public function handle(PaymentCompleted $event): void
    {
        // Sifariş statusunu "paid" et
        $this->orderService->updateOrderStatus($event->orderId, 'paid');

        // Stok rezervasiyasını təsdiq et
        $this->inventoryService->confirmReservation($event->orderId);
    }
}

// ==========================================
// modules/Order/Listeners/MarkOrderAsFailedOnPaymentFailed.php
// ==========================================
namespace Modules\Order\Listeners;

use Modules\Payment\Events\PaymentFailed;
use Modules\Order\Contracts\OrderServiceInterface;
use Modules\Inventory\Contracts\InventoryServiceInterface;

final class MarkOrderAsFailedOnPaymentFailed
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
        private readonly InventoryServiceInterface $inventoryService,
    ) {}

    public function handle(PaymentFailed $event): void
    {
        $this->orderService->updateOrderStatus($event->orderId, 'payment_failed');
        $this->inventoryService->releaseReservation($event->orderId);
    }
}
```

### Payment Module - Tam Kod

*Payment Module - Tam Kod üçün kod nümunəsi:*
```php
// ==========================================
// modules/Payment/Services/PaymentService.php
// ==========================================
namespace Modules\Payment\Services;

use Illuminate\Support\Str;
use Modules\Payment\Contracts\DTOs\PaymentRequest;
use Modules\Payment\Contracts\DTOs\PaymentResult;
use Modules\Payment\Contracts\DTOs\RefundResult;
use Modules\Payment\Contracts\PaymentServiceInterface;
use Modules\Payment\Events\PaymentCompleted;
use Modules\Payment\Events\PaymentFailed;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\Gateways\PaymentGateway;
use Modules\Payment\Services\Gateways\PaymentGatewayFactory;
use Shared\Enums\Currency;

final class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        private readonly PaymentGatewayFactory $gatewayFactory,
    ) {}

    public function processPayment(PaymentRequest $request): PaymentResult
    {
        $gateway = $this->gatewayFactory->create(
            config('modules.payment.default_gateway')
        );

        // 1. Ödəniş qeydini yarat
        $payment = Payment::create([
            'uuid' => Str::uuid()->toString(),
            'order_id' => $request->orderId,
            'gateway' => config('modules.payment.default_gateway'),
            'method' => $request->paymentMethod,
            'amount_cents' => $request->amountInCents,
            'currency' => $request->currency,
            'status' => 'pending',
        ]);

        try {
            // 2. Gateway vasitəsilə ödəniş et
            $gatewayResult = $gateway->charge(
                amountCents: $request->amountInCents,
                currency: $request->currency,
                paymentMethod: $request->paymentMethod,
                metadata: array_merge($request->metadata, [
                    'order_id' => $request->orderId,
                    'payment_id' => $payment->id,
                ]),
            );

            // 3. Uğurlu ödəniş
            $payment->update([
                'transaction_id' => $gatewayResult->transactionId,
                'status' => 'completed',
                'gateway_response' => $gatewayResult->rawResponse,
                'completed_at' => now(),
            ]);

            // 4. Uğur event-i yayımla
            event(new PaymentCompleted(
                paymentId: $payment->id,
                orderId: $request->orderId,
                transactionId: $gatewayResult->transactionId,
                amountInCents: $request->amountInCents,
                currency: $request->currency,
                gateway: $payment->gateway,
            ));

            return new PaymentResult(
                success: true,
                transactionId: $gatewayResult->transactionId,
                status: \Modules\Payment\Enums\PaymentStatus::Completed,
            );

        } catch (\Throwable $e) {
            // 5. Uğursuz ödəniş
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            // 6. Uğursuzluq event-i yayımla
            event(new PaymentFailed(
                orderId: $request->orderId,
                reason: $e->getMessage(),
                gateway: $payment->gateway,
                errorCode: $e->getCode() ? (string) $e->getCode() : null,
            ));

            return new PaymentResult(
                success: false,
                transactionId: '',
                status: \Modules\Payment\Enums\PaymentStatus::Failed,
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function getPaymentStatus(string $transactionId): \Modules\Payment\Enums\PaymentStatus
    {
        $payment = Payment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return \Modules\Payment\Enums\PaymentStatus::NotFound;
        }

        return \Modules\Payment\Enums\PaymentStatus::from($payment->status);
    }

    public function refund(string $transactionId, int $amountInCents): RefundResult
    {
        $payment = Payment::where('transaction_id', $transactionId)->firstOrFail();

        if ($payment->status !== 'completed') {
            throw new \DomainException('Yalnız tamamlanmış ödənişlər geri qaytarıla bilər');
        }

        $maxRefundable = $payment->amount_cents - $payment->refunded_amount_cents;
        if ($amountInCents > $maxRefundable) {
            throw new \DomainException(
                "Maksimum geri qaytarıla bilən məbləğ: {$maxRefundable} sent"
            );
        }

        $gateway = $this->gatewayFactory->create($payment->gateway);
        $refundResult = $gateway->refund($transactionId, $amountInCents);

        $payment->update([
            'refunded_amount_cents' => $payment->refunded_amount_cents + $amountInCents,
            'status' => ($payment->refunded_amount_cents + $amountInCents >= $payment->amount_cents)
                ? 'refunded'
                : 'partially_refunded',
            'refunded_at' => now(),
        ]);

        // Refund qeydini yarat
        $payment->refunds()->create([
            'refund_transaction_id' => $refundResult->refundTransactionId,
            'amount_cents' => $amountInCents,
            'reason' => 'Müştəri tələbi',
            'status' => 'completed',
        ]);

        return new RefundResult(
            success: true,
            refundTransactionId: $refundResult->refundTransactionId,
            amountRefundedCents: $amountInCents,
        );
    }
}

// ==========================================
// modules/Payment/Services/Gateways/PaymentGatewayFactory.php
// ==========================================
namespace Modules\Payment\Services\Gateways;

final class PaymentGatewayFactory
{
    public function create(string $gateway): PaymentGateway
    {
        return match ($gateway) {
            'stripe' => new StripeGateway(
                key: config('modules.payment.gateways.stripe.key'),
                secret: config('modules.payment.gateways.stripe.secret'),
            ),
            'paypal' => new PayPalGateway(
                clientId: config('modules.payment.gateways.paypal.client_id'),
                clientSecret: config('modules.payment.gateways.paypal.client_secret'),
                mode: config('modules.payment.gateways.paypal.mode'),
            ),
            default => throw new \InvalidArgumentException("Naməlum ödəniş gateway: {$gateway}"),
        };
    }
}

// ==========================================
// modules/Payment/Services/Gateways/PaymentGateway.php
// ==========================================
namespace Modules\Payment\Services\Gateways;

interface PaymentGateway
{
    public function charge(
        int $amountCents,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayChargeResult;

    public function refund(string $transactionId, int $amountCents): GatewayRefundResult;
}

// ==========================================
// modules/Payment/Services/Gateways/StripeGateway.php
// ==========================================
namespace Modules\Payment\Services\Gateways;

final class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $key,
        private readonly string $secret,
    ) {}

    public function charge(
        int $amountCents,
        string $currency,
        string $paymentMethod,
        array $metadata = [],
    ): GatewayChargeResult {
        // Real Stripe API call
        \Stripe\Stripe::setApiKey($this->secret);

        try {
            $charge = \Stripe\PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'payment_method' => $paymentMethod,
                'confirm' => true,
                'metadata' => $metadata,
            ]);

            return new GatewayChargeResult(
                transactionId: $charge->id,
                status: 'completed',
                rawResponse: $charge->toArray(),
            );
        } catch (\Stripe\Exception\CardException $e) {
            throw new PaymentGatewayException(
                "Kart xətası: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function refund(string $transactionId, int $amountCents): GatewayRefundResult
    {
        \Stripe\Stripe::setApiKey($this->secret);

        $refund = \Stripe\Refund::create([
            'payment_intent' => $transactionId,
            'amount' => $amountCents,
        ]);

        return new GatewayRefundResult(
            refundTransactionId: $refund->id,
            status: 'completed',
        );
    }
}
```

### Inventory Module - Tam Kod

*Inventory Module - Tam Kod üçün kod nümunəsi:*
```php
// ==========================================
// modules/Inventory/Contracts/InventoryServiceInterface.php
// (yuxarıda göstərilmişdi, burada implementasiya)
// ==========================================

// ==========================================
// modules/Inventory/Models/Product.php
// ==========================================
namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'inventory_products';

    protected $fillable = [
        'name',
        'sku',
        'description',
        'price_cents',
        'currency',
        'quantity',
        'reserved_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function availableQuantity(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->availableQuantity() >= $quantity;
    }
}

// ==========================================
// modules/Inventory/Models/StockMovement.php
// ==========================================
namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'inventory_stock_movements';

    protected $fillable = [
        'product_id',
        'type',        // reserve, release, confirm, restock, adjustment
        'quantity',
        'order_id',
        'reference',
        'notes',
    ];
}

// ==========================================
// modules/Inventory/Models/StockReservation.php
// ==========================================
namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $table = 'inventory_stock_reservations';

    protected $fillable = [
        'reservation_id',
        'order_id',
        'product_id',
        'quantity',
        'status',       // reserved, confirmed, released
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}

// ==========================================
// modules/Inventory/Services/InventoryService.php
// ==========================================
namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Contracts\DTOs\ProductStockDTO;
use Modules\Inventory\Contracts\DTOs\StockReservationResult;
use Modules\Inventory\Contracts\Exceptions\InsufficientStockException;
use Modules\Inventory\Contracts\InventoryServiceInterface;
use Modules\Inventory\Events\StockDepleted;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockReservation;

final class InventoryService implements InventoryServiceInterface
{
    public function isInStock(int $productId, int $quantity): bool
    {
        $product = Product::find($productId);
        return $product && $product->isInStock($quantity);
    }

    public function reserveStock(array $items, int $orderId): StockReservationResult
    {
        $reservationId = 'res-' . Str::uuid()->toString();
        $reservedItems = [];
        $failedItems = [];

        DB::transaction(function () use ($items, $orderId, $reservationId, &$reservedItems, &$failedItems) {
            foreach ($items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                if (!$product || !$product->isInStock($item['quantity'])) {
                    $failedItems[] = $item['product_id'];
                    throw new InsufficientStockException(
                        productId: $item['product_id'],
                        requestedQuantity: $item['quantity'],
                        availableQuantity: $product?->availableQuantity() ?? 0,
                    );
                }

                // Stoku rezerv et
                $product->increment('reserved_quantity', $item['quantity']);

                // Rezervasiya qeydini yarat
                StockReservation::create([
                    'reservation_id' => $reservationId,
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'status' => 'reserved',
                    'expires_at' => now()->addHours(2), // 2 saat sonra bitir
                ]);

                // Stok hərəkəti qeydini yarat
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'type' => 'reserve',
                    'quantity' => -$item['quantity'],
                    'order_id' => $orderId,
                    'reference' => $reservationId,
                ]);

                $reservedItems[] = $item['product_id'];

                // Stok az qaldıqda xəbərdarlıq et
                if ($product->availableQuantity() <= 5) {
                    event(new StockDepleted(
                        productId: $product->id,
                        productName: $product->name,
                        remainingQuantity: $product->availableQuantity(),
                    ));
                }
            }
        });

        return new StockReservationResult(
            success: true,
            reservationId: $reservationId,
            reservedItems: $reservedItems,
            failedItems: $failedItems,
        );
    }

    public function releaseReservation(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $reservations = StockReservation::where('order_id', $orderId)
                ->where('status', 'reserved')
                ->get();

            foreach ($reservations as $reservation) {
                $product = Product::lockForUpdate()->find($reservation->product_id);

                if ($product) {
                    $product->decrement('reserved_quantity', $reservation->quantity);
                }

                $reservation->update(['status' => 'released']);

                StockMovement::create([
                    'product_id' => $reservation->product_id,
                    'type' => 'release',
                    'quantity' => $reservation->quantity,
                    'order_id' => $orderId,
                    'notes' => 'Rezervasiya ləğv edildi',
                ]);
            }
        });
    }

    public function confirmReservation(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $reservations = StockReservation::where('order_id', $orderId)
                ->where('status', 'reserved')
                ->get();

            foreach ($reservations as $reservation) {
                $product = Product::lockForUpdate()->find($reservation->product_id);

                if ($product) {
                    $product->decrement('quantity', $reservation->quantity);
                    $product->decrement('reserved_quantity', $reservation->quantity);
                }

                $reservation->update(['status' => 'confirmed']);

                StockMovement::create([
                    'product_id' => $reservation->product_id,
                    'type' => 'confirm',
                    'quantity' => -$reservation->quantity,
                    'order_id' => $orderId,
                    'notes' => 'Ödəniş təsdiqləndi, stok azaldıldı',
                ]);
            }
        });
    }

    public function getAvailableStock(int $productId): int
    {
        $product = Product::find($productId);
        return $product?->availableQuantity() ?? 0;
    }

    public function getStockForProducts(array $productIds): array
    {
        return Product::whereIn('id', $productIds)
            ->get()
            ->mapWithKeys(fn (Product $product) => [
                $product->id => new ProductStockDTO(
                    productId: $product->id,
                    productName: $product->name,
                    totalQuantity: $product->quantity,
                    reservedQuantity: $product->reserved_quantity,
                    availableQuantity: $product->availableQuantity(),
                    isInStock: $product->isInStock(),
                ),
            ])
            ->all();
    }
}
```

### Notification Module - Tam Kod

*Notification Module - Tam Kod üçün kod nümunəsi:*
```php
// ==========================================
// modules/Notification/Services/NotificationService.php
// ==========================================
namespace Modules\Notification\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Contracts\NotificationServiceInterface;
use Modules\Notification\Mail\OrderConfirmationMail;
use Modules\Notification\Mail\PaymentReceiptMail;
use Modules\Notification\Mail\ShippingUpdateMail;
use Modules\Notification\Mail\LowStockAlertMail;
use Modules\Notification\Models\NotificationLog;
use Modules\User\Contracts\UserServiceInterface;

final class NotificationService implements NotificationServiceInterface
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    ) {}

    public function sendOrderConfirmation(int $userId, int $orderId): void
    {
        try {
            $user = $this->userService->getBasicInfo($userId);

            Mail::to($user->email)->queue(new OrderConfirmationMail(
                userName: $user->name,
                orderId: $orderId,
            ));

            $this->logNotification('order_confirmation', $userId, [
                'order_id' => $orderId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Sifariş təsdiq emaili göndərilə bilmədi', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendPaymentReceipt(int $userId, int $paymentId): void
    {
        try {
            $user = $this->userService->getBasicInfo($userId);

            Mail::to($user->email)->queue(new PaymentReceiptMail(
                userName: $user->name,
                paymentId: $paymentId,
            ));

            $this->logNotification('payment_receipt', $userId, [
                'payment_id' => $paymentId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ödəniş qəbzi emaili göndərilə bilmədi', [
                'user_id' => $userId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendShippingUpdate(int $userId, int $orderId, string $trackingNumber): void
    {
        try {
            $user = $this->userService->getBasicInfo($userId);

            Mail::to($user->email)->queue(new ShippingUpdateMail(
                userName: $user->name,
                orderId: $orderId,
                trackingNumber: $trackingNumber,
            ));

            $this->logNotification('shipping_update', $userId, [
                'order_id' => $orderId,
                'tracking_number' => $trackingNumber,
            ]);
        } catch (\Throwable $e) {
            Log::error('Göndərmə yeniləməsi emaili göndərilə bilmədi', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendLowStockAlert(int $productId, int $remainingQuantity): void
    {
        try {
            $adminEmail = config('modules.notification.admin_email');

            Mail::to($adminEmail)->queue(new LowStockAlertMail(
                productId: $productId,
                remainingQuantity: $remainingQuantity,
            ));

            $this->logNotification('low_stock_alert', null, [
                'product_id' => $productId,
                'remaining_quantity' => $remainingQuantity,
            ]);
        } catch (\Throwable $e) {
            Log::error('Aşağı stok xəbərdarlığı göndərilə bilmədi', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function logNotification(string $type, ?int $userId, array $data): void
    {
        NotificationLog::create([
            'type' => $type,
            'user_id' => $userId,
            'data' => $data,
            'sent_at' => now(),
        ]);
    }
}

// ==========================================
// modules/Notification/Listeners/SendNotificationOnOrderPlaced.php
// ==========================================
namespace Modules\Notification\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Notification\Contracts\NotificationServiceInterface;
use Modules\Order\Events\OrderPlaced;

final class SendNotificationOnOrderPlaced implements ShouldQueue
{
    public $queue = 'notifications';
    public $delay = 5; // 5 saniyə gözlə

    public function __construct(
        private readonly NotificationServiceInterface $notificationService,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $this->notificationService->sendOrderConfirmation(
            userId: $event->userId,
            orderId: $event->orderId,
        );
    }

    /**
     * İş uğursuz olduqda.
     */
    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        logger()->error('Order confirmation notification failed', [
            'order_id' => $event->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}

// ==========================================
// modules/Notification/Listeners/SendNotificationOnStockDepleted.php
// ==========================================
namespace Modules\Notification\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Inventory\Events\StockDepleted;
use Modules\Notification\Contracts\NotificationServiceInterface;

final class SendNotificationOnStockDepleted implements ShouldQueue
{
    public $queue = 'notifications';

    public function __construct(
        private readonly NotificationServiceInterface $notificationService,
    ) {}

    public function handle(StockDepleted $event): void
    {
        $this->notificationService->sendLowStockAlert(
            productId: $event->productId,
            remainingQuantity: $event->remainingQuantity,
        );
    }
}

// ==========================================
// modules/Notification/Providers/NotificationServiceProvider.php
// ==========================================
namespace Modules\Notification\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Events\StockDepleted;
use Modules\Notification\Contracts\NotificationServiceInterface;
use Modules\Notification\Listeners\SendNotificationOnOrderPlaced;
use Modules\Notification\Listeners\SendNotificationOnPaymentCompleted;
use Modules\Notification\Listeners\SendNotificationOnStockDepleted;
use Modules\Notification\Services\NotificationService;
use Modules\Order\Events\OrderPlaced;
use Modules\Payment\Events\PaymentCompleted;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationServiceInterface::class, NotificationService::class);
        $this->mergeConfigFrom(__DIR__ . '/../Config/notification.php', 'modules.notification');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Bütün modulların event-lərinə qulaq as
        Event::listen(OrderPlaced::class, SendNotificationOnOrderPlaced::class);
        Event::listen(PaymentCompleted::class, SendNotificationOnPaymentCompleted::class);
        Event::listen(StockDepleted::class, SendNotificationOnStockDepleted::class);
    }
}
```

### Tam Event Flow Diaqramı

```
Müştəri sifariş verir → OrderController::store()
    │
    ▼
OrderService::placeOrder()
    │
    ├── 1. UserService::userExists() ──── [Sinxron: User Contract]
    │
    ├── 2. InventoryService::isInStock() ── [Sinxron: Inventory Contract]
    │
    ├── 3. Order yaradılır (DB)
    │
    ├── 4. InventoryService::reserveStock() ── [Sinxron: Inventory Contract]
    │
    └── 5. event(OrderPlaced) ──── [Asinxron: Event Bus]
            │
            ├──▶ Payment Module:
            │    InitiatePaymentOnOrderPlaced::handle()
            │    └── PaymentService::processPayment()
            │        ├── Uğurlu → event(PaymentCompleted)
            │        │              ├──▶ Order: UpdateOrderStatusOnPaymentCompleted
            │        │              │    └── OrderService::updateOrderStatus('paid')
            │        │              │    └── InventoryService::confirmReservation()
            │        │              └──▶ Notification: SendNotificationOnPaymentCompleted
            │        │                   └── sendPaymentReceipt()
            │        └── Uğursuz → event(PaymentFailed)
            │                       └──▶ Order: MarkOrderAsFailedOnPaymentFailed
            │                            └── OrderService::updateOrderStatus('payment_failed')
            │                            └── InventoryService::releaseReservation()
            │
            ├──▶ Notification Module:
            │    SendNotificationOnOrderPlaced::handle()
            │    └── sendOrderConfirmation()
            │
            └──▶ (Gələcəkdə başqa modullar da qoşula bilər,
                  OrderService heç bir dəyişiklik tələb etmir!)
```

---

## Best Practices

### 1. Modul Dizayn Qaydaları

*1. Modul Dizayn Qaydaları üçün kod nümunəsi:*
```php
// ✅ QAYDA 1: Hər modulun bir və yalnız bir məsuliyyəti olmalıdır
// Order modulu - sifariş prosesinin idarəsi
// Payment modulu - ödəniş prosesinin idarəsi
// ❌ OrderAndPayment modulu - iki məsuliyyət bir modulda

// ✅ QAYDA 2: Modul sərhədi = Bounded Context
// DDD-nin bounded context-i modul sərhədi ilə uyğun gəlməlidir

// ✅ QAYDA 3: Public API minimal olmalıdır
// Yalnız digər modulların ehtiyacı olan metodlar contract-da olsun
interface OrderServiceInterface
{
    // ✅ Lazım olan metodlar
    public function getOrderById(int $id): OrderDTO;
    public function placeOrder(PlaceOrderCommand $command): OrderDTO;

    // ❌ Daxili metodlar contract-da olmamalıdır
    // public function calculateTax(int $subtotal): int;
    // public function validateCoupon(string $code): bool;
}

// ✅ QAYDA 4: Data snapshot saxla, reference yox
// Sifariş yaradılanda məhsulun adını və qiymətini KOPYALA
OrderItem::create([
    'product_name' => $product->name,      // Snapshot!
    'unit_price_cents' => $product->price,  // Snapshot!
    // Məhsulun adı sonra dəyişsə belə, sifariş düzgün qalır
]);

// ✅ QAYDA 5: Modullar arası FK istifadə etmə
// order_orders cədvəlində user_id var, amma FK yoxdur
// Bu, modulların DB səviyyəsində müstəqil olmasını təmin edir
```

### 2. Naming Conventions

*2. Naming Conventions üçün kod nümunəsi:*
```php
// Cədvəl adları: {module_prefix}_{table_name}
'order_orders'
'order_items'
'payment_payments'
'payment_refunds'
'inventory_products'
'inventory_stock_movements'
'user_users'
'user_roles'
'notification_logs'

// Event adları: past tense, modul prefiksi olmadan
OrderPlaced       // ✅
PaymentCompleted  // ✅
StockDepleted     // ✅
OrderWasPlaced    // ❌ "Was" lazım deyil
PlaceOrder        // ❌ Bu command-dır, event deyil

// Contract namespace: Modules\{Module}\Contracts
Modules\Order\Contracts\OrderServiceInterface
Modules\Order\Contracts\DTOs\OrderDTO
Modules\Order\Contracts\Exceptions\OrderNotFoundException

// Internal namespace: Modules\{Module}\{Layer}
Modules\Order\Services\OrderService
Modules\Order\Models\Order
Modules\Order\Repositories\EloquentOrderRepository
```

### 3. Error Handling

*3. Error Handling üçün kod nümunəsi:*
```php
// Hər modul öz exception-larını təyin etməlidir
namespace Modules\Order\Exceptions;

use Shared\Exceptions\DomainException;

final class OrderNotFoundException extends DomainException
{
    public function __construct(int $orderId)
    {
        parent::__construct(
            message: "Sifariş tapılmadı: {$orderId}",
            errorCode: 'ORDER_NOT_FOUND',
            context: ['order_id' => $orderId],
        );
    }
}

final class OrderCannotBeCancelledException extends DomainException
{
    public function __construct(int $orderId, string $currentStatus)
    {
        parent::__construct(
            message: "Sifariş ləğv edilə bilməz. Status: {$currentStatus}",
            errorCode: 'ORDER_CANNOT_BE_CANCELLED',
            context: [
                'order_id' => $orderId,
                'current_status' => $currentStatus,
            ],
        );
    }
}

// Digər modulun exception-ını tutmaq və öz exception-ına çevirmək:
try {
    $this->inventoryService->reserveStock($items, $orderId);
} catch (InsufficientStockException $e) {
    throw new OrderCreationFailedException(
        "Stok çatışmazlığı: Məhsul #{$e->productId}",
        previous: $e,
    );
}
```

### 4. Performans İpucları

*4. Performans İpucları üçün kod nümunəsi:*
```php
// ✅ Batch əməliyyatlar istifadə et
// Bir-bir əvəzinə toplu müraciət et
$stocks = $this->inventoryService->getStockForProducts([1, 2, 3, 4, 5]);
// ❌ foreach ($productIds as $id) { $this->inventoryService->getAvailableStock($id); }

// ✅ Event listener-ləri queue-ya göndər
final class SendNotificationOnOrderPlaced implements ShouldQueue
{
    public $queue = 'notifications'; // Ayrı queue
}

// ✅ Modular caching
// Hər modul öz cache prefix-ini istifadə etsin
Cache::tags(['order'])->put("order:{$id}", $order, 3600);
Cache::tags(['inventory'])->put("stock:{$productId}", $stock, 300);

// ✅ Lazy loading əvəzinə eager loading
$orders = Order::with('items')->where('user_id', $userId)->get();
```

---

## İntervyu Sualları və Cavabları

### Sual 1: Modulyar Monolit nədir və nə üçün istifadə olunur?

**Cavab:** Modulyar Monolit - tətbiqin bir deploy vahidi olaraq qaldığı, lakin daxildə aydın sərhədlərlə ayrılmış müstəqil modullara bölündüyü arxitektura yanaşmasıdır. Klassik monolitin sadəliyini (bir deploy, bir DB, ACID transaction) microservices-in təşkilati üstünlükləri (modul izolyasiyası, paralel inkişaf, aydın sərhədlər) ilə birləşdirir. Əsasən orta ölçülü layihələr və komandalar üçün idealdır (5-30 nəfər). Həmçinin, microservices-ə keçid üçün mükəmməl hazırlıq addımıdır, çünki modul sərhədləri artıq müəyyən olunmuşdur.

---

### Sual 2: Modulyar Monolit-də modullar arası əlaqə necə qurulur?

**Cavab:** İki əsas yol var:

1. **Sinxron (Interface/Contract)** - modul digər modulun public interface-ini (contract) inject edərək birbaşa çağırır. Nəticə dərhal lazım olduqda istifadə olunur. Məsələn: `OrderService` `PaymentServiceInterface`-i inject edib `processPayment()` çağırır.

2. **Asinxron (Events)** - modul event yayımlayır, digər modullar bu event-ə listener qeydiyyat etdirir. Publisher listener-ləri tanımır. Nəticə dərhal lazım olmadığı hallarda istifadə olunur. Məsələn: `OrderPlaced` event-i yayımlanır, Notification modulu email göndərir, Inventory modulu stoku azaldır.

Modullar birbaşa bir-birinin model-lərinə, repository-lərinə və ya database cədvəllərinə müraciət etməməlidir. Yalnız Contracts namespace-indəki interface-lər, DTO-lar və event-lər vasitəsilə əlaqə qurulmalıdır.

---

### Sual 3: Shared Kernel nədir və nə olmalıdır?

**Cavab:** Shared Kernel bütün modulların ortaq istifadə etdiyi kod bazasıdır. Buraya yalnız həqiqətən ortaq olan elementlər daxildir: Value Objects (Money, Email, Address), base sinifləri (DomainEvent, DomainException), ortaq DTO-lar (PaginatedResult), enums (Currency, Country) və utility trait-lər. Shared Kernel minimal olmalıdır, business logic ehtiva etməməlidir, və dəyişdirilməsi çox ehtiyatlı aparılmalıdır - çünki hər dəyişiklik bütün modulları təsir edir.

---

### Sual 4: Modulyar Monolit-dən Microservices-ə necə keçid edilir?

**Cavab:** Keçid tədricən, "Strangler Fig Pattern" ilə edilir:
1. İlk addım artıq düzgün modul sərhədlərinin olmasıdır (Modulyar Monolit bunu təmin edir).
2. İlk olaraq ən müstəqil modul (məs: Notification) ayrılır.
3. In-process interface call-lar HTTP client-ə çevrilir. Interface eyni qalır, yalnız implementasiya dəyişir - `PaymentService` yerinə `PaymentServiceHttpClient` bind olunur. Order modulu heç bir dəyişiklik tələb etmir.
4. Laravel events RabbitMQ/Kafka-ya köçürülür.
5. Tədricən daha çox modul ayrılır.
6. Birdəfəlik "big bang" keçid ETMƏYİN - bu, yüksək risk daşıyır.

---

### Sual 5: Modullar arasında database FK (Foreign Key) istifadə etmək düzgündür?

**Cavab:** Xeyr, modullar arası FK istifadə etmək tövsiyə olunmur. Bunun səbəbi:
1. FK modulları DB səviyyəsində bir-birinə bağlayır - bu, müstəqilliyi pozur.
2. Migration sırası əhəmiyyət kəsb edir - asılılıq yaranır.
3. Microservices-ə keçid zamanı FK-ları ayırmaq çətin olur.
4. Modul deaktiv edildikdə FK xətaları yaranır.

Əvəzində, yalnız integer ID saxlanılır (məs: `user_id`), və məlumatın doğruluğu tətbiq səviyyəsində (contract vasitəsilə) yoxlanılır. Modul daxilindəki cədvəllər arasında FK istifadə etmək normaldır (məs: `order_items.order_id` → `order_orders.id`).

---

### Sual 6: Dairəvi asılılıq (Circular Dependency) problemi necə həll olunur?

**Cavab:** Dairəvi asılılıq, A modulunun B moduluna, B modulunun isə A moduluna asılı olduğu vəziyyətdir. Bu, həmişə dizayn xətasıdır. Həll yolları:

1. **Event-based communication** - bir istiqaməti sinxron (contract), digər istiqaməti asinxron (event) edin. Məs: Order → PaymentContract (sinxron), Payment → OrderPlaced event-ə qulaq asır (asinxron).

2. **Mediator/Orchestrator pattern** - üçüncü modul hər iki modulu koordinasiya edir.

3. **Modul sərhədlərini yenidən nəzərdən keçirin** - bəlkə iki modul əslində bir olmalıdır, və ya üçüncü modul çıxarılmalıdır.

---

### Sual 7: Bir modulun testi digər modullardan necə müstəqil yazılır?

**Cavab:** Xarici modul asılılıqlarının hamısı mock/stub olunur. Bu mümkündür çünki hər asılılıq interface (contract) vasitəsilə inject olunur. Unit testdə `$this->createMock(PaymentServiceInterface::class)` ilə mock yaradılır, feature testdə isə `$this->mock(InventoryServiceInterface::class)` ilə Laravel container-da mock bind olunur. Nəticədə hər modul tam müstəqil test oluna bilir - xarici modul aktivdir ya deaktivdir, fərq etməz. Architecture testləri (phpat/phpat) isə modul sərhədlərinin pozulmasını CI/CD-də avtomatik aşkarlayır.

---

### Sual 8: nwidart/laravel-modules paketi ilə öz əl ilə yazdığınız modulyar struktur arasında fərq nədir?

**Cavab:** `nwidart/laravel-modules` modul yaratma, activate/deactivate, artisan command-lar (module:make-model, module:migrate), autoloading konfiqurasiyası kimi boilerplate işləri avtomatlaşdırır. Lakin, əsas arxitektura qərarları hər iki yanaşmada eynidir: modul sərhədlərini müəyyən etmək, contract-lar yaratmaq, event-based communication qurmaq. Kiçik komandalar üçün əl ilə yazmaq daha sadə ola bilər (daha az magic, daha çox kontrol). Böyük komandalar üçün paket standartlaşma təmin edir. Əsas fərq: paket convenience (rahatlıq) verir, lakin arxitektura biliyini əvəz etmir.

---

### Sual 9: Data snapshot nə üçün vacibdir?

**Cavab:** Data snapshot, sifariş yaradılan anda məhsulun adını, qiymətini və digər məlumatlarını ORDER modulunda kopyalamaq deməkdir. Bu vacibdir çünki:
1. Məhsulun adı və ya qiyməti gələcəkdə dəyişə bilər, lakin sifariş tarixi dəyişməməlidir.
2. Inventory modulu deaktiv olsa belə, sifariş məlumatları tam qalır.
3. Modullar arası asılılığı azaldır - Order modulu göstərmək üçün Inventory moduluna müraciət etməyə ehtiyac duymur.
4. Performans - hər dəfə cross-module call etmək əvəzinə, məlumat yerli DB-dadır.

---

### Sual 10: Modulyar Monolit harada uyğun deyil?

**Cavab:** Aşağıdakı hallarda Modulyar Monolit optimal seçim deyil:
1. **Çox kiçik layihə** (1-3 developer) - əlavə struktur overhead yaradır, klassik monolit daha sadədir.
2. **Müxtəlif texnologiya tələb edən modullar** - məs: bir modul Python ML, digəri Go ilə yazılmalıdırsa, monolitdə bu mümkün deyil.
3. **Müstəqil scaling lazım olan modullar** - məs: Notification modulu çox yüksək yük altındadırsa, onu müstəqil scale etmək mümkün deyil.
4. **Çox böyük komandalar** (50+ developer) - deploy bottle-neck yaranır, hər modul müstəqil deploy olunmalıdır.
5. **Mövcud microservices ekosistemi varsa** - artıq microservices infrastrukturu (K8s, service mesh, monitoring) mövcuddursa, yenidən monolitə qayıtmaq mənasızdır.

---

### Sual 11: Event-lərin idempotent olması nə üçün vacibdir?

**Cavab:** Event listener idempotent olmalıdır, yəni eyni event iki dəfə emal olunsa da, nəticə eyni olmalıdır. Bunun səbəbi: queue retry mexanizmi event-i təkrar göndərə bilər, network problemləri iki dəfə delivery yarada bilər, və ya microservices-ə keçid zamanı at-least-once delivery qarantiyası verən message broker-lər istifadə oluna bilər. Praktik həll: event-in `eventId`-sini saxlamaq və təkrar gəldikdə ignore etmək (idempotency key pattern).

***Cavab:** Event listener idempotent olmalıdır, yəni eyni event iki də üçün kod nümunəsi:*
```php
final class InitiatePaymentOnOrderPlaced
{
    public function handle(OrderPlaced $event): void
    {
        // Eyni event artıq emal olunubsa, ignore et
        if (ProcessedEvent::where('event_id', $event->eventId)->exists()) {
            return;
        }

        ProcessedEvent::create(['event_id' => $event->eventId]);

        $this->paymentService->processPayment(/* ... */);
    }
}
```

---

### Sual 12: Modulyar Monolit-in ən böyük riski nədir?

**Cavab:** Ən böyük risk **modul sərhədlərinin tədricən pozulmasıdır** (erosion). Vaxt keçdikcə developer-lər "tez bir həll" üçün başqa modulun daxili siniflərinə birbaşa müraciət edə, cross-module JOIN yaza, və ya shared model istifadə edə bilərlər. Bu, modulyar monoliti adi Big Ball of Mud monolitinə çevirir. Buna qarşı mübarizə üçün:
1. Architecture testləri (phpat) CI/CD-də işlətmək.
2. Code review-da modul sərhədlərinə diqqət yetirmək.
3. Komandanı maarifləndirmək - nə üçün bu qaydalar var.
4. Static analysis alətləri (PHPStan, Psalm) ilə namespace izolyasiyasını yoxlamaq.

---

## Anti-patternlər

**1. Modullar Arasında Birbaşa Class İmportu**
`Billing` modulunun `Inventory` modulunun daxili sinifini `use App\Modules\Inventory\Internal\StockManager` kimi birbaşa import etməsi — modullar bir-birinə sıx bağlanır, ayrı microservice-ə çevirmək çətinləşir. Modullar yalnız digər modulun public API-si (interface, facade, event) vasitəsilə əlaqə qurmalıdır.

**2. Cross-Module Database JOIN-ları**
Bir modulun cədvəlini başqa modulun Eloquent scope-unda `join('inventory.products')` kimi birbaşa sorğulamaq — database səviyyəsindəki asılılıq ayrılmanı mümkünsüzləşdirir. Hər modul öz cədvəllərinə sahibdir; cross-module data ehtiyacı üçün event ya da public query metodu istifadə edin.

**3. Shared Eloquent Model İstifadəsi**
`User` modelini bütün modullar tərəfindən birbaşa import edib istifadə etmək — modullarda dəyişiklik User-ı dəyişdirir, User-da dəyişiklik bütün modulları pozur. Identity kimi mərkəzi konsept üçün minimal shared kernel yaradın ya da hər modul öz `UserReference` value object-ini saxlasın.

**4. Modullar Arasında Transaction-ları Paylaşmaq**
Tək database transaction içində iki modulun data-sını eyni anda dəyişmək — modullar database transaction səviyyəsindən bağlanır, ayrılmaq mümkünsüzləşir. Hər modul öz transaction-ını idarə etməlidir; modullar arası əməliyyatlar üçün Saga/Compensating Transaction pattern istifadə edin.

**5. Modul Sərhədlərini Texniki Qata Görə Deyil, Domain-ə Görə Ayırmamaq**
Modulları `Http`, `Models`, `Services` kimi texniki qatlara görə ayırmaq — domain logic hər yerə dağılır, bir feature üzərində işləmək üçün bir neçə "modul"u dəyişmək lazım gəlir. Modulları domain-ə görə ayırın: `Billing`, `Inventory`, `Shipping`; hər modulun öz Http, Models, Services qatları olsun.

**6. Architecture Testlərini Yazmamaq**
Modul izolyasiya qaydalarını yalnız code review-a güvənmək — insan nəzarəti qaçırır, tədricən pozulmalar (erosion) baş verir. `phpat` ilə architecture testləri yazın və CI/CD pipeline-a əlavə edin; modul sərhədlərini pozulmasını avtomatik aşkar edin.

**7. Shared Database-dəki Cədvəl Adlandırmasını Modul-Prefix Olmadan Etmək**
Bütün modulların cədvəllərini prefix olmadan (`orders`, `products`, `users`) adlandırmaq — cədvəlin hansı modulun məsuliyyəti olduğu bilinmir, migration-lar toqquşur, ayrılma zamanı namespace anlaşılmazlığı yaranır. Hər modul öz prefixini istifadə etsin: `order_orders`, `order_order_items`, `inventory_products`, `billing_invoices`.

**8. Module Service Provider-ında Bütün Modulun Binding-lərini Eagerly Load Etmək**
Böyük modullarda on-larla binding-i `register()` metodunda eyni anda qeydiyyatdan keçirmək — bootstrap vaxtını artırır, lazım olmayan service-lər də yüklənir. Ağır/nadir istifadə olunan service-lər üçün `DeferrableProvider` istifadə edin; yalnız həmin service resolve olunanda provider boot olsun.
