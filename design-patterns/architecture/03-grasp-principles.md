# GRASP Prinsipləri (Senior)

## İcmal

GRASP — General Responsibility Assignment Software Patterns. Craig Larman-ın "Applying UML and Patterns" (2004) kitabında təqdim edilib. Əsas sual: **məsuliyyəti hansı class-a vermək lazımdır?** GRASP bu sualı 9 pattern ilə cavablandırır. SOLID necə dizayn etməyi deyirsə, GRASP kimin nə etməli olduğunu müəyyən edir.

## Niyə Vacibdir

Senior developer kimi refactoring zamanı "bu kodu hara köçürəm?" sualına sistemli cavab vermək lazımdır. GRASP bu qərar prosesini strukturlaşdırır. Information Expert yanlış tətbiq edilsə, domain logic service-lərə yayılır, entity-lər anemic olur. Low Coupling yanlış başa düşülsə, gereksiz interface-lər yaranır. GRASP-ı bilmək code review-da düzgün əsaslandırma verməyə imkan tanıyır.

## Əsas Anlayışlar

- **Information Expert**: məsuliyyəti, onu yerinə yetirmək üçün lazımi məlumatı olan class-a ver
- **Creator**: B-ni yaratmaq üçün `A, B-ni saxlayırsa`, `A, B üçün lazımi məlumatı bilirsə`, `A, B-ni yığırsa` — A yaratmalıdır
- **Controller**: UI-dan gələn system event-ləri qəbul edən class; Facade Controller (hamısı bir yerdə) vs Use Case Controller (hər use case ayrı)
- **Low Coupling**: class-lar arasındakı asılılığı azalt; dəyişiklik yayılmasını azaldır
- **High Cohesion**: class yalnız bir işi yaxşı etsin; Low cohesion = God class
- **Polymorphism**: tip-ə görə fərqli davranış üçün `if/else` əvəzinə polymorphism istifadə et
- **Pure Fabrication**: domain-də olmayan amma lazımi olan class — Repository, Service, Mapper; domain model-i temiz saxlamaq üçün
- **Indirection**: iki class arasına vasitəçi qoy (decoupling üçün); Controller → Service → Repository
- **Protected Variations**: dəyişə biləcək yerlərə interface/abstract qoy; SOLID-in OCP-si ilə eynidir

## Praktik Baxış

- **Real istifadə**: code review-da "bu məntiq burada olmalıdırmı?" sualını cavablandırmaq; refactoring qərarlarını əsaslandırmaq; layihə arxitekturasını planlamaq
- **Trade-off-lar**: Information Expert düzgün tətbiq edilsə domain logic entity-lərdə qalır (rich domain model); Pure Fabrication isə zəruri hallarda domain-i xaricə çıxarır (infrastructure separation)
- **Hansı hallarda istifadə etməmək**: 2-3 class-lı kiçik script-lər üçün GRASP overhead yaradır; principi öyrənmək üçün nümunə düşünmək, production-da hər metod üçün GRASP-ı düşünmək gərəkli deyil
- **Common mistakes**: Information Expert-i God Object kimi istifadə etmək (bütün məlumatı bilirsə, hər şeyi etsin — SRP pozulur); Low Coupling-i "sıfır asılılıq" kimi anlamaq (bir-biriylə əlaqəsi olan class-ların asılılığı tamamilə yox ola bilməz)

### Anti-Pattern Nə Zaman Olur?

**GRASP anlamamaq**: "Expert olduğu üçün etsin" — `Order` class-ı çox şeyi bilirsə, ona payment, shipping, notification məsuliyyəti də vermək. Bu Information Expert-i God Object-ə çevirir. Expert prinsipi "məlumatı olan class-a ver" deməkdir — məlumatı olmayan məsuliyyəti vermə.

**Creator-ı yanlış başa düşmək**: Factory pattern olmadan, məlumatı olmayan class-ın object yaratması — `Controller::new Order(...)` — Controller-in Order üçün lazımi məlumatı olmaya bilər, bu Creator prinsipini pozur. Əvəzinə Factory ya da Application Service-dən istifadə et.

## Nümunələr

### Ümumi Nümunə

GRASP 9 pattern birlikdə bir cümləyə sığır: "Məsuliyyəti bilən versin (Expert), doğurub-böyüdən yaransın (Creator), girişi nəzarət etsin (Controller), asılılığı azalt (Low Coupling), fokuslu qal (High Cohesion), tipə görə davranışı dəyiş (Polymorphism), domain-i temiz saxlamaq üçün süni class yarat (Pure Fabrication), vasitəçilə ayır (Indirection), dəyişəni interface-lə qoru (Protected Variations)."

### PHP/Laravel Nümunəsi

**1. Information Expert**

```php
<?php

// Information Expert: Order öz items-larını bilir → total hesablamaq onun məsuliyyətidir
class Order
{
    private array $items; // Order bu məlumatı bilir

    public function calculateTotal(): Money
    {
        // ✅ Doğru: total hesablamaq Order-in məsuliyyətidir — məlumat burada
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $item) => $carry->add($item->subtotal()),
            Money::zero('AZN'),
        );
    }
}

// ❌ Yanlış — Information Expert pozulur:
// class OrderTotalService {
//     public function calculate(Order $order): Money { ... }
// }
// Niyə yanlış: məlumat Order-dədir, amma məsuliyyət başqa class-a verilir → Anemic Domain

// Laravel nümunəsi: Eloquent model accessor-ları Information Expert-dir
class Product extends Model
{
    // Product öz price və tax rate-ini bilir → final price onun hesablamasıdır
    public function getFinalPriceAttribute(): float
    {
        return $this->price * (1 + $this->tax_rate);
    }
}
```

**2. Creator**

```php
<?php

class Order
{
    private array $items = [];

    // Creator: Order, OrderItem-ləri saxlayır → yaratmalıdır
    public function addItem(ProductId $productId, int $qty, Money $price): void
    {
        $this->items[] = new OrderItem($productId, $qty, $price);
        // ✅ Order, OrderItem-in yaradılması üçün lazımi məlumatı bilir
    }
}

// Pure Fabrication — domain konsepti deyil, amma lazımdır
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    // ✅ Repository domain modelini bilmir (Pure Fabrication)
    public function save(Order $order): void { /* ... */ }
    public function findById(OrderId $id): ?Order { /* ... */ }
}
```

**3. Controller (Use Case Controller — tövsiyə olunan)**

```php
<?php

// ✅ Use Case Controller — hər use case üçün ayrı (High Cohesion)
class PlaceOrderController extends Controller
{
    public function __construct(private readonly PlaceOrderService $service) {}

    public function __invoke(PlaceOrderRequest $request): JsonResponse
    {
        $orderId = $this->service->handle(PlaceOrderCommand::fromRequest($request));
        return response()->json(['order_id' => $orderId], 201);
    }
}

// ❌ Facade Controller — hamısı bir yerdə (Low Cohesion)
// class OrderController {
//     public function place() { ... }      // use case 1
//     public function cancel() { ... }     // use case 2
//     public function ship() { ... }       // use case 3
//     public function return() { ... }     // use case 4
//     public function report() { ... }     // use case 5 — artıq başqa domain!
// }
```

**4. Low Coupling + Indirection**

```php
<?php

// ❌ High Coupling — concrete class-a birbaşa asılılıq
class OrderService
{
    public function __construct(
        private MySQLOrderRepository $repo, // Concrete! Dəyişdirilsə OrderService dəyişməlidir
    ) {}
}

// ✅ Low Coupling — interface vasitəsilə (Indirection əlavə olunur)
class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $repo, // Interface — asılılıq azaldı
    ) {}
}

// Laravel Service Container — Indirection mexanizmi
$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
// OrderService artıq MySQL, PostgreSQL, InMemory — hansı implementasiyadan asılı deyil
```

**5. Polymorphism — if/else əvəzinə**

```php
<?php

// ❌ OCP + Polymorphism pozuntusu
class DiscountCalculator
{
    public function calculate(string $type, float $price): float
    {
        if ($type === 'regular')   { return $price * 0.95; }
        if ($type === 'premium')   { return $price * 0.85; }
        if ($type === 'corporate') { return $price * 0.70; }
        return $price; // Yeni tip? Bu metodu dəyişdirmək lazımdır!
    }
}

// ✅ Polymorphism — yeni tip mövcud kodu dəyişdirmir
interface DiscountStrategy
{
    public function apply(Money $total): Money;
}

class PremiumDiscount implements DiscountStrategy
{
    public function apply(Money $total): Money
    {
        return $total->multiply(0.85); // 15% endirim
    }
}

class CorporateDiscount implements DiscountStrategy
{
    public function apply(Money $total): Money
    {
        return $total->multiply(0.70); // 30% endirim
    }
}

// Protected Variations: yeni discount tipi mövcud kodu dəyişdirmir
class PricingService
{
    public function calculate(Money $total, DiscountStrategy $strategy): Money
    {
        return $strategy->apply($total);
    }
}
```

**6. High Cohesion vs Low Cohesion**

```php
<?php

// ❌ Low Cohesion — bir class çox müxtəlif məsuliyyət
class OrderManager
{
    public function createOrder() {}    // Order domain
    public function sendEmail() {}      // Notification domain — burada olmamalı
    public function generatePdf() {}    // PDF rendering — burada olmamalı
    public function processPayment() {} // Payment domain — burada olmamalı
    public function updateStock() {}    // Inventory domain — burada olmamalı
}

// ✅ High Cohesion — hər class yalnız bir məsuliyyət
class OrderService    { public function create(): void {} }
class EmailNotifier   { public function sendConfirmation(): void {} }
class InvoicePdfGen   { public function generate(): void {} }
class PaymentService  { public function charge(): void {} }
class InventoryService { public function reserve(): void {} }
```

## Praktik Tapşırıqlar

1. Mövcud bir Laravel `OrderService`-i götürün; hansı metodların Information Expert prinsipinə görə `Order` entity-sinə köçürülə biləcəyini müəyyən edin; köçürün
2. `ProductService::createProduct()` metodunda yeni `ProductVariant` yaratma əməliyyatı var; Creator prinsipinə görə bu məsuliyyəti `Product` entity-sinə köçürün
3. God Controller-i Use Case Controller-lərə bölün: `OrderController` → `PlaceOrderController`, `CancelOrderController`, `ShipOrderController` — hər controller yalnız bir use case üçün
4. Bir God Service-i tapın; Low Coupling tətbiq edərək interface-ə çıxarın; Service Provider-da bind edin; unit test-lərdə in-memory implementasiya inject edin

## Əlaqəli Mövzular

- [SOLID Prinsipləri](02-solid-principles.md) — GRASP SOLID-i tamamlayır; Low Coupling = DIP, Protected Variations = OCP
- [Design Patterns Ümumi Baxış](01-design-patterns-overview.md) — GRASP pattern-ləri GoF pattern-lərin əsasını formalaşdırır
- [Layered Architectures](04-layered-architectures.md) — Controller, Pure Fabrication (Repository/Service) GRASP-dan gəlir
- [DDD](../ddd/01-ddd.md) — Information Expert rich domain model-ə, Pure Fabrication infrastructure isolation-a uyğun gəlir
- [Repository Pattern](../laravel/01-repository-pattern.md) — Pure Fabrication-ın real tətbiqi
