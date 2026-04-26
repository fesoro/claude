# GRASP Principles (Senior)

## Mündəricat
1. [GRASP nədir?](#grasp-nədir)
2. [Əsas Prinsiplər](#əsas-prinsiplər)
3. [SOLID ilə Müqayisə](#solid-ilə-müqayisə)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## GRASP nədir?

```
GRASP — General Responsibility Assignment Software Patterns
Craig Larman — "Applying UML and Patterns" (2004)

Sual: "Məsuliyyəti hansı sinifə vermək lazımdır?"
GRASP bu sualı cavablandırmaq üçün 9 pattern verir.

SOLID vs GRASP:
  SOLID: siniflər necə dizayn edilməlidir?
  GRASP: məsuliyyət kimin olmalıdır?

9 GRASP Pattern:
  1. Information Expert
  2. Creator
  3. Controller
  4. Low Coupling
  5. High Cohesion
  6. Polymorphism
  7. Pure Fabrication
  8. Indirection
  9. Protected Variations
```

---

## Əsas Prinsiplər

```
1. Information Expert:
  Məsuliyyəti onu yerinə yetirmək üçün lazımi məlumatı
  olan sinifə verin.
  
  Order total hesablamaq → Order sinfi OrderItem-ləri bilir
  → Order.calculateTotal() ✓
  NOT: ayrı TotalCalculator sinfi ✗ (Anemic Domain)

2. Creator:
  B-ni aşağıdakı halda A yaradır:
  - A, B-ni saxlayır (contains)
  - A, B-ni yığır (aggregates)
  - A, B-ni başlatmaq üçün lazımi məlumatı bilir
  
  Order → OrderItem yaradır ✓
  OrderFactory (Pure Fabrication) mürəkkəb yaratma üçün ✓

3. Controller:
  UI layer-dan gələn system event-ləri qəbul edən sinif.
  Seçim:
  - Facade Controller: bütün system event-ləri bir sinif
  - Use Case Controller: hər use case bir sinif (daha yaxşı)
  
  PlaceOrderController ✓ (bir use case)
  God Controller ✗ (hər şey bir controller-da)

4. Low Coupling:
  Siniflər arasındakı asılılığı azaldın.
  Dəyişiklik yayılmasını azaldır.
  Dependency Inversion + Interface → low coupling

5. High Cohesion:
  Sinif yalnız bir işi yaxşı etsin.
  Low cohesion: bir sinifdə çox müxtəlif məsuliyyət
  God class ✗

6. Polymorphism:
  Tip-ə görə fərqli davranış → if/else əvəzinə polymorphism
  PaymentProcessor.process() → Stripe/PayPal/Cash

7. Pure Fabrication:
  Domain-də olmayan amma lazımi olan sinif.
  Repository, Service, Mapper → Pure Fabrication
  Domain model-i temiz saxlamaq üçün

8. Indirection:
  İki sinif arasına vasitəçi qoy (decoupling üçün).
  Controller → Service → Repository (birbaşa yox)

9. Protected Variations:
  Dəyişə biləcək yerlərə interface/abstract qoy.
  "Open/Closed" SOLID-dəki kimidir.
```

---

## SOLID ilə Müqayisə

```
┌──────────────────────┬────────────────────────────────────────┐
│ GRASP                │ SOLID                                  │
├──────────────────────┼────────────────────────────────────────┤
│ Information Expert   │ SRP (məsuliyyətin doğru yeri)          │
│ High Cohesion        │ SRP (bir məsuliyyət)                   │
│ Low Coupling         │ DIP (interfeysdən asılı)               │
│ Polymorphism         │ OCP (genişlənmə üçün açıq)             │
│ Protected Variations │ OCP (dəyişikliyə qapalı)               │
│ Pure Fabrication     │ ISP (spesifik interface-lər)           │
└──────────────────────┴────────────────────────────────────────┘

GRASP daha "niyə?" sualına cavab verir.
SOLID daha "necə?" sualına cavab verir.
```

---

## PHP İmplementasiyası

```php
<?php
// 1. Information Expert
class Order
{
    /** @var OrderItem[] */
    private array $items;

    // Information Expert: Order öz items-larını bilir
    // Total hesablamaq Order-in məsuliyyətidir
    public function calculateTotal(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $item) => $carry->add($item->subtotal()),
            Money::zero('USD'),
        );
    }

    // Yanlış dizayn — Information Expert pozulur:
    // class OrderTotalService { calculate(Order $order) }
    // Bu, məntiqi Order-dən kənara çıxarır
}
```

```php
<?php
// 2. Creator — Order, OrderItem yaradır
class Order
{
    private array $items = [];

    // Creator: Order, OrderItem-ləri saxlayır → o yaratmalıdır
    public function addItem(ProductId $productId, int $qty, Money $price): void
    {
        // Order, OrderItem-i özü yaradır
        $this->items[] = new OrderItem($productId, $qty, $price);
    }
}

// Pure Fabrication — domain-də olmayan amma lazımi
// OrderRepository domain konsepti deyil, amma lazımdır
class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void { /* ... */ }
    public function findById(string $id): ?Order { /* ... */ }
}
```

```php
<?php
// 3. Polymorphism — if/else əvəzinə
interface DiscountStrategy
{
    public function apply(Money $total, Customer $customer): Money;
}

class PremiumDiscount implements DiscountStrategy
{
    public function apply(Money $total, Customer $customer): Money
    {
        return $total->multiply(0.9); // 10% endirim
    }
}

class StandardDiscount implements DiscountStrategy
{
    public function apply(Money $total, Customer $customer): Money
    {
        return $total; // Endirim yoxdur
    }
}

// Protected Variations: yeni discount tipi əlavə etmək
// mövcud kodu dəyişdirmir
class PricingService
{
    public function calculate(Money $total, Customer $customer, DiscountStrategy $strategy): Money
    {
        return $strategy->apply($total, $customer);
    }
}
```

```php
<?php
// 4. Low Coupling + Indirection
// Birbaşa asılılıq (high coupling):
class OrderService {
    public function __construct(
        private MySQLOrderRepository $repo, // Concrete class!
    ) {}
}

// Interface ilə (low coupling):
class OrderService {
    public function __construct(
        private OrderRepositoryInterface $repo, // Interface
    ) {}
}

// 5. High Cohesion — bir məsuliyyət
// Yanlış (low cohesion):
class OrderManager {
    public function createOrder() {}
    public function sendEmail() {}      // Email məsuliyyəti burada?
    public function generatePdf() {}   // PDF məsuliyyəti burada?
    public function processPayment() {} // Payment məsuliyyəti burada?
}

// Düzgün (high cohesion):
class OrderService   { public function create() {} }
class EmailNotifier { public function sendConfirmation() {} }
class InvoicePdfGen { public function generate() {} }
class PaymentService { public function charge() {} }
```

---

## İntervyu Sualları

- GRASP nədir? SOLID-dən fərqi nədir?
- Information Expert prinsipi nəyi müəyyən edir?
- Pure Fabrication nədir? Real nümunə verin.
- Creator patternin şərtləri nədir?
- Low Coupling necə ölçülür?
- Protected Variations, SOLID-in hansı prinsipi ilə üst-üstə düşür?
