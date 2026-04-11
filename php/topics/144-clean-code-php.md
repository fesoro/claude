# Clean Code in PHP

## Mündəricat
1. [Adlandırma](#adlandırma)
2. [Funksiyalar](#funksiyalar)
3. [Siniflər](#siniflər)
4. [Code Smells](#code-smells)
5. [PHP Nümunələri](#php-nümunələri)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Adlandırma

```
Prinsip: Ad niyyəti ifadə etməlidir.

Yanlış:
  $d       → $daysSinceCreation
  $data    → $activeUsers
  $process → $sendOrderConfirmationEmail

Boolean adları:
  $flag   → $isEmailVerified
  $check  → $hasPermission
  $temp   → $isPremiumCustomer

Funksiya adları:
  getData()    → getActiveOrdersByCustomer()
  doStuff()    → calculateMonthlyRevenue()
  handle()     → processRefundRequest()

Sinif adları:
  Manager   → bilinməyən — OrderProcessor? OrderRepository?
  Processor → OrderFulfillmentProcessor (spesifik)
  Handler   → OK (Command/Event Handler kontekstdə)
  Utils     → pis (nə utils?) — StringFormatter, DateCalculator

Magic numbers:
  if ($status == 3)      → if ($status === OrderStatus::CANCELLED)
  if ($days > 30)        → if ($days > self::TRIAL_PERIOD_DAYS)
```

---

## Funksiyalar

```
Qayda 1 — Bir iş et:
  Funksiya bir şey etsə onu yaxşı edir, izah etmək asan.
  
  // Yanlış — iki iş:
  function validateAndSave(User $user): void {
    // validate...
    // save...
  }
  
  // Düzgün:
  function validate(User $user): void { ... }
  function save(User $user): void { ... }

Qayda 2 — Argument sayı (maksimum 3):
  0 arg: ideal
  1 arg: yaxşı
  2 arg: qəbul edilir
  3 arg: düşünün
  3+:   Data Object istifadə edin
  
  // Yanlış:
  function createOrder(string $id, string $customerId, float $total,
                        string $currency, string $status): Order
  
  // Düzgün:
  function createOrder(CreateOrderCommand $command): Order

Qayda 3 — Flag argument yoxdur:
  sendEmail($user, true)   // true nədir?
  sendEmail($user, $html)  // Hələ də qeyri-müəyyən
  
  sendHtmlEmail($user)     // Açıq-aşkar
  sendTextEmail($user)     // Açıq-aşkar

Qayda 4 — Side effects yoxdur:
  function getUser(string $id): ?User {
    $this->lastAccess = now(); // Side effect! Gözlənilmir
    return $this->repository->find($id);
  }
```

---

## Siniflər

```
Single Responsibility — bir sinif bir iş:
  Meyar: "Bu sinifi dəyişdirməyim üçün neçə səbəb var?"
  Birdən çox səbəb → SRP pozulub

Small classes:
  200 sətir üstündə sinif — diqqəti cəlb edin
  "Bu sinifi parçalaya bilərəmmi?"

Data vs Behavior:
  DTO/Value Object → data, az behavior
  Entity           → data + domain behavior
  Service          → behavior, az/heç data

Law of Demeter:
  "Yalnız bilavasitə yaxınlarınla danış"
  a.getB().getC().doSomething() → YANLIŞDIR
  a.doSomethingWithC() → Düzgün
  
  "Train wreck" — zəncir
  $customer->getAddress()->getCity()->getName() → YANLIŞDIR
  $customer->getCityName() → Düzgün (delegation)
```

---

## Code Smells

```
Long Method: 20+ sətir → parçala
God Class: 500+ sətir, hər şeyi edir → SRP pozulub
Feature Envy: sinif başqa sinifin metodlarını çox çağırır → move method
Data Clumps: eyni parametrlər hər yerdə → Value Object
Primitive Obsession: string/int əvəzinə VO (Email, Money, PhoneNumber)
Switch Statements: polymorphism ilə əvəz et
Duplicate Code: DRY — Don't Repeat Yourself
Dead Code: istifadə edilməyən kod → sil
Comments: // bunu et → niyə edilir yazmaq lazımdır, nə yox
Magic Numbers: 86400 → SECONDS_IN_DAY
```

---

## PHP Nümunələri

```php
<?php
// BEFORE — pis kod

class Order {
    public function proc($d, $s, $c, $t) {
        if ($s == 1) {
            // send email
            mail($c, 'Order', 'Your order: ' . $t);
            // update db
            $stmt = $this->db->prepare("UPDATE orders SET status=1 WHERE id=?");
            $stmt->execute([$d]);
            return true;
        } else if ($s == 2) {
            mail($c, 'Cancelled', 'Order cancelled');
            $stmt = $this->db->prepare("UPDATE orders SET status=2 WHERE id=?");
            $stmt->execute([$d]);
            return false;
        }
    }
}
```

```php
<?php
// AFTER — təmiz kod

class Order
{
    public function confirm(): void
    {
        $this->guardPending();
        $this->status = OrderStatus::CONFIRMED;
        $this->recordEvent(new OrderConfirmedEvent($this->id));
    }

    public function cancel(string $reason): void
    {
        $this->guardCancellable();
        $this->status         = OrderStatus::CANCELLED;
        $this->cancellationReason = $reason;
        $this->recordEvent(new OrderCancelledEvent($this->id, $reason));
    }

    private function guardPending(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidOrderStateException(
                "Yalnız gözləmədəki sifariş təsdiqlənə bilər"
            );
        }
    }
}

// Email göndərmək ayrı məsuliyyət:
class OrderNotificationListener
{
    public function onOrderConfirmed(OrderConfirmedEvent $event): void
    {
        $this->mailer->sendOrderConfirmation($event->orderId);
    }

    public function onOrderCancelled(OrderCancelledEvent $event): void
    {
        $this->mailer->sendCancellationNotice($event->orderId, $event->reason);
    }
}
```

```php
<?php
// Primitive Obsession → Value Object

// Yanlış:
function createUser(string $email, string $phone, float $balance): User {}

// Düzgün:
function createUser(Email $email, PhoneNumber $phone, Money $balance): User {}

final class Email
{
    private function __construct(private readonly string $value) {}

    public static function from(string $value): self
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Yanlış email: {$value}");
        }
        return new self(strtolower(trim($value)));
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
```

```php
<?php
// Law of Demeter — Train wreck aradan qaldırmaq

// Yanlış:
$city = $order->getCustomer()->getAddress()->getCity()->getName();

// Düzgün — delegation:
class Order {
    public function getCustomerCityName(): string
    {
        return $this->customer->getCityName();
    }
}

class Customer {
    public function getCityName(): string
    {
        return $this->address->getCityName();
    }
}

// İstifadə:
$city = $order->getCustomerCityName(); // Sadə, təmiz
```

---

## İntervyu Sualları

- "Code smell" nədir? 3 nümunə verin.
- Law of Demeter nədir? Niyə vacibdir?
- Flag argument niyə pis praktikadır?
- Primitive Obsession-u Value Object ilə necə aradan qaldırırsınız?
- "Clean Code" şərhlər barədə nə deyir?
- Böyük metodun refactor edilməsi üçün addımlar nədir?
