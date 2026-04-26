# Clean Code, Code Smells və Refactoring (Senior)

## 1. Code Smells — pis kodun əlamətləri

### God Class / God Method
```php
// Pis — bir sinif hər şeyi edir
class OrderManager {
    public function createOrder() {}
    public function processPayment() {}
    public function sendEmail() {}
    public function generatePdf() {}
    public function updateInventory() {}
    public function calculateShipping() {}
    public function applyDiscount() {}
    // 2000+ sətir...
}

// Yaxşı — hər sinifin bir məsuliyyəti
class OrderService { public function create() {} }
class PaymentService { public function process() {} }
class OrderMailer { public function sendConfirmation() {} }
class InvoiceGenerator { public function generate() {} }
```

### Long Parameter List
```php
// Pis
function createUser(string $name, string $email, string $phone, 
    string $city, string $country, string $zip, int $age, string $role) {}

// Yaxşı — DTO istifadə et
function createUser(CreateUserDTO $dto) {}
```

### Feature Envy
```php
// Pis — başqa sinifin datasına çox müraciət edir
class InvoiceCalculator {
    public function calculate(Order $order): float {
        $subtotal = 0;
        foreach ($order->getItems() as $item) {
            $subtotal += $item->getPrice() * $item->getQuantity();
        }
        $tax = $subtotal * $order->getTaxRate();
        $discount = $subtotal * $order->getDiscountRate();
        return $subtotal + $tax - $discount;
    }
}

// Yaxşı — hesablama Order-in öz metodu olsun
class Order {
    public function getTotal(): float {
        return $this->getSubtotal() + $this->getTax() - $this->getDiscount();
    }
}
```

### Primitive Obsession
```php
// Pis — hər yerdə string
function sendMoney(string $fromEmail, string $toEmail, float $amount, string $currency) {}

// Yaxşı — Value Object istifadə et
class Email {
    public function __construct(private string $value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: $value");
        }
    }
    public function __toString(): string { return $this->value; }
}

class Money {
    public function __construct(
        private float $amount,
        private Currency $currency,
    ) {}

    public function add(Money $other): self {
        if (!$this->currency->equals($other->currency)) {
            throw new CurrencyMismatchException();
        }
        return new self($this->amount + $other->amount, $this->currency);
    }
}

function sendMoney(Email $from, Email $to, Money $amount) {}
```

---

## 2. Refactoring Texnikaları

### Extract Method
```php
// Əvvəl
public function processOrder(Order $order): void {
    // Validate stock
    foreach ($order->items as $item) {
        $product = Product::find($item->product_id);
        if ($product->stock < $item->quantity) {
            throw new InsufficientStockException($product);
        }
    }
    
    // Calculate total
    $total = 0;
    foreach ($order->items as $item) {
        $total += $item->price * $item->quantity;
    }
    $total *= (1 + $order->tax_rate);
    
    // Process payment
    // ...30 more lines
}

// Sonra
public function processOrder(Order $order): void {
    $this->validateStock($order);
    $total = $this->calculateTotal($order);
    $this->processPayment($order, $total);
}

private function validateStock(Order $order): void { /* ... */ }
private function calculateTotal(Order $order): float { /* ... */ }
private function processPayment(Order $order, float $total): void { /* ... */ }
```

### Replace Conditional with Polymorphism
```php
// Əvvəl
class NotificationSender {
    public function send(string $type, string $message, User $user): void {
        switch ($type) {
            case 'email':
                Mail::to($user->email)->send(new GenericMail($message));
                break;
            case 'sms':
                $this->smsClient->send($user->phone, $message);
                break;
            case 'push':
                $this->pushService->notify($user->device_token, $message);
                break;
        }
    }
}

// Sonra
interface NotificationChannel {
    public function send(string $message, User $user): void;
}

class EmailChannel implements NotificationChannel {
    public function send(string $message, User $user): void {
        Mail::to($user->email)->send(new GenericMail($message));
    }
}

class SmsChannel implements NotificationChannel {
    public function send(string $message, User $user): void {
        $this->smsClient->send($user->phone, $message);
    }
}

// Factory ilə seçim
class NotificationChannelFactory {
    public function make(string $type): NotificationChannel {
        return match($type) {
            'email' => app(EmailChannel::class),
            'sms' => app(SmsChannel::class),
            'push' => app(PushChannel::class),
            default => throw new InvalidArgumentException("Unknown channel: $type"),
        };
    }
}
```

### Introduce Null Object
```php
// Əvvəl — hər yerdə null check
$discount = $coupon ? $coupon->getDiscount($total) : 0;
$label = $coupon ? $coupon->getLabel() : 'No discount';

// Sonra
class NullCoupon implements CouponInterface {
    public function getDiscount(float $total): float { return 0; }
    public function getLabel(): string { return 'No discount'; }
}

$coupon = $this->findCoupon($code) ?? new NullCoupon();
$discount = $coupon->getDiscount($total); // null check lazım deyil
```

---

## 3. Guard Clauses (Early Return)

```php
// Pis — nested if-lər
function processPayment(Order $order): PaymentResult {
    if ($order->isPaid()) {
        return PaymentResult::alreadyPaid();
    } else {
        if ($order->total > 0) {
            if ($order->user->hasPaymentMethod()) {
                // 20 sətir nested kod...
                return PaymentResult::success();
            } else {
                return PaymentResult::noPaymentMethod();
            }
        } else {
            return PaymentResult::invalidAmount();
        }
    }
}

// Yaxşı — guard clauses
function processPayment(Order $order): PaymentResult {
    if ($order->isPaid()) {
        return PaymentResult::alreadyPaid();
    }

    if ($order->total <= 0) {
        return PaymentResult::invalidAmount();
    }

    if (!$order->user->hasPaymentMethod()) {
        return PaymentResult::noPaymentMethod();
    }

    // Əsas məntiq — flat, oxunaqlı
    return $this->chargePayment($order);
}
```

---

## 4. Value Objects

```php
class Money {
    public function __construct(
        private readonly int $amount,       // cents-lə saxla (float deyil!)
        private readonly Currency $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }

    public function add(Money $other): self {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self {
        $this->ensureSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $factor): self {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function equals(Money $other): bool {
        return $this->amount === $other->amount 
            && $this->currency === $other->currency;
    }

    public function format(): string {
        return number_format($this->amount / 100, 2) . ' ' . $this->currency->value;
    }

    private function ensureSameCurrency(Money $other): void {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException();
        }
    }
}

enum Currency: string {
    case AZN = 'AZN';
    case USD = 'USD';
    case EUR = 'EUR';
}

// İstifadə
$price = new Money(2999, Currency::USD);  // $29.99
$tax = $price->multiply(0.18);            // $5.40
$total = $price->add($tax);              // $35.39
echo $total->format();                    // "35.39 USD"
```

---

## 5. Immutability — niyə vacibdir?

```php
// Mutable — state dəyişir, debug çətin
class Cart {
    private array $items = [];
    
    public function addItem(Item $item): void {
        $this->items[] = $item; // State dəyişdi
    }
}

// Immutable — hər dəyişiklik yeni instance qaytarır
class Cart {
    public function __construct(
        private readonly array $items = [],
    ) {}

    public function addItem(Item $item): self {
        return new self([...$this->items, $item]); // Yeni instance
    }

    public function removeItem(int $index): self {
        $items = $this->items;
        unset($items[$index]);
        return new self(array_values($items));
    }
}

// PHP 8.2 readonly class
readonly class OrderSummary {
    public function __construct(
        public int $orderId,
        public float $total,
        public string $status,
        public Carbon $createdAt,
    ) {}
}
```

---

## 6. Tell, Don't Ask prinsipi

```php
// Pis — ask (data istə, özün qərar ver)
if ($user->getBalance() >= $amount) {
    $user->setBalance($user->getBalance() - $amount);
    $order->setStatus('paid');
}

// Yaxşı — tell (nə etməli de)
$user->charge($amount); // User özü yoxlayır və dəyişir

class User {
    public function charge(Money $amount): void {
        if ($this->balance->lessThan($amount)) {
            throw new InsufficientBalanceException($this->balance, $amount);
        }
        $this->balance = $this->balance->subtract($amount);
    }
}
```
