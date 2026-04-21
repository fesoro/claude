# Sealed Classes və Pattern Matching (Java Sealed Types vs PHP Workarounds)

## Giriş

Sealed class — bu, inheritance-i məhdudlaşdıran class-dır. Adi `abstract class` hər kəs tərəfindən extend edilə bilər; `final` class heç kim tərəfindən. Sealed isə arada durur — yalnız müəyyən class-lar extend edə bilər. Bu, **Algebraic Data Types (ADT)** modelləmək üçün əsas alətdir: "bu tipin yalnız 3 halı var və hamısını bilirəm".

**Java 17**-də `sealed` stable oldu (preview Java 15-də gəldi). Açar sözlər: `sealed`, `permits`, `non-sealed`, `final`. Sealed + Record kombinasiyası Java-da funksional-stil domain modelləməni mümkün etdi.

**PHP-də `sealed` yoxdur.** PHP konvensiya və workaround-larla işləyir: `final` class-lar, `abstract` + discriminator property, enum + interface, və ya attribute-based yoxlama. Ən yaxın ekvivalent — PHP 8.1-in **backed enum**-u və **interface + final** kombinasiyasıdır.

---

## Java-da istifadəsi

### 1) Sealed class və ya interface — əsas sintaksis

```java
public sealed interface Shape permits Circle, Square, Triangle {}

public record Circle(double radius) implements Shape {}
public record Square(double side) implements Shape {}
public record Triangle(double base, double height) implements Shape {}

// permits siyahısında olmayan class implement edə bilməz
// class Hexagon implements Shape {}       // Compile error
```

`permits` açar sözü icazə verilən alt class-ları göstərir. Siyahıdan kənar class extend etsə, compiler xəta verir.

### 2) Üç seçim: `final`, `sealed`, `non-sealed`

```java
public sealed interface Vehicle permits Car, Truck, Bike {}

public final class Car implements Vehicle {}                  // genişlənə bilməz
public sealed class Truck implements Vehicle permits Pickup, Semi {}   // hierarchy davam edir
public non-sealed class Bike implements Vehicle {}            // hər kəs extend edə bilər

public final class Pickup extends Truck {}
public final class Semi extends Truck {}

// Əvvəlcədən bilmədiyin class-lar da Bike-dan extend oluna bilər
public class ElectricBike extends Bike {}                     // OK, Bike non-sealed
```

Hər sealed hierarchy-də hər alt tip bu üçündən biri olmalıdır: `final`, `sealed`, `non-sealed`.

### 3) Sealed + Record = Algebraic Data Type

```java
public sealed interface Result<T, E> {
    record Success<T, E>(T value) implements Result<T, E> {}
    record Failure<T, E>(E error) implements Result<T, E> {}
}

// İstifadə
Result<User, String> result = userService.findById(42);

String message = switch (result) {
    case Result.Success<User, String>(User u)  -> "Tapıldı: " + u.email();
    case Result.Failure<User, String>(String e) -> "Xəta: " + e;
};
```

Bu pattern — **sum type** (və ya **tagged union**) — Rust-da `enum`, Kotlin-də `sealed class`, Haskell-də `data` açar sözü ilə var.

### 4) Exhaustive pattern matching

```java
public sealed interface PaymentMethod
    permits CreditCard, BankTransfer, PayPal, Crypto {}

public record CreditCard(String number, String cvv) implements PaymentMethod {}
public record BankTransfer(String iban) implements PaymentMethod {}
public record PayPal(String email) implements PaymentMethod {}
public record Crypto(String wallet, String network) implements PaymentMethod {}

public BigDecimal calculateFee(PaymentMethod method, BigDecimal amount) {
    return switch (method) {
        case CreditCard cc  -> amount.multiply(new BigDecimal("0.029"));
        case BankTransfer b -> new BigDecimal("0.50");
        case PayPal p       -> amount.multiply(new BigDecimal("0.034"));
        case Crypto c       -> amount.multiply(new BigDecimal("0.01"));
        // default lazım DEYİL — compiler bütün halları bildiyi üçün yoxlayır
    };
}
```

Bu kodun gücü: yeni `PaymentMethod` əlavə etsən (məsələn `ApplePay`), bu metod compile xətası verəcək — səhv tuta bilməzsən. `default` qoysaydın, bu yoxlama itərdi.

### 5) Nested sealed hierarchy — state machine

```java
public sealed interface OrderState {
    record Pending(Instant createdAt) implements OrderState {}
    record Paid(Instant paidAt, String transactionId) implements OrderState {}
    record Shipped(Instant shippedAt, String trackingCode) implements OrderState {}
    record Delivered(Instant deliveredAt) implements OrderState {}
    record Cancelled(Instant cancelledAt, String reason) implements OrderState {}
}

public OrderState transition(OrderState current, OrderEvent event) {
    return switch (current) {
        case OrderState.Pending p -> switch (event) {
            case OrderEvent.Pay(var txId)   -> new OrderState.Paid(Instant.now(), txId);
            case OrderEvent.Cancel(var r)   -> new OrderState.Cancelled(Instant.now(), r);
            default -> throw new IllegalStateException("Pending-dən icazəsiz keçid");
        };
        case OrderState.Paid p -> switch (event) {
            case OrderEvent.Ship(var code)  -> new OrderState.Shipped(Instant.now(), code);
            case OrderEvent.Cancel(var r)   -> new OrderState.Cancelled(Instant.now(), r);
            default -> throw new IllegalStateException("Paid-dən icazəsiz keçid");
        };
        case OrderState.Shipped s -> switch (event) {
            case OrderEvent.Deliver d       -> new OrderState.Delivered(Instant.now());
            default -> throw new IllegalStateException("Shipped-dən icazəsiz keçid");
        };
        case OrderState.Delivered d, OrderState.Cancelled c ->
            throw new IllegalStateException("Final vəziyyət");
    };
}
```

Bu — tam tipli state machine. Hər keçid compiler tərəfindən yoxlanılır.

### 6) API response modelləməsi

```java
public sealed interface ApiResponse<T>
    permits ApiResponse.Ok, ApiResponse.Error, ApiResponse.ValidationFailed {

    record Ok<T>(T data, int statusCode) implements ApiResponse<T> {}
    record Error<T>(String message, int statusCode) implements ApiResponse<T> {}
    record ValidationFailed<T>(Map<String, String> errors) implements ApiResponse<T> {}
}

@RestController
@RequestMapping("/api/users")
public class UserController {

    @GetMapping("/{id}")
    public ResponseEntity<?> getUser(@PathVariable Long id) {
        ApiResponse<User> response = service.findById(id);

        return switch (response) {
            case ApiResponse.Ok<User>(User user, int code)
                -> ResponseEntity.status(code).body(user);
            case ApiResponse.Error<User>(String msg, int code)
                -> ResponseEntity.status(code).body(Map.of("error", msg));
            case ApiResponse.ValidationFailed<User>(Map<String, String> errs)
                -> ResponseEntity.badRequest().body(errs);
        };
    }
}
```

### 7) Sealed + Visitor pattern (alternativ)

Sealed-dən əvvəl Visitor pattern istifadə olunurdu. Sealed + switch bu boilerplate-i aradan qaldırır:

```java
// Visitor — köhnə yol
public interface ShapeVisitor<R> {
    R visitCircle(Circle c);
    R visitSquare(Square s);
    R visitTriangle(Triangle t);
}

public interface Shape {
    <R> R accept(ShapeVisitor<R> visitor);
}

// Sealed — yeni yol
public sealed interface Shape permits Circle, Square, Triangle {}

double area(Shape s) {
    return switch (s) {
        case Circle(double r)            -> Math.PI * r * r;
        case Square(double side)         -> side * side;
        case Triangle(double b, double h) -> 0.5 * b * h;
    };
}
```

### 8) Sealed ilə error handling — Result type

```java
public sealed interface Result<T, E> {
    record Ok<T, E>(T value) implements Result<T, E> {}
    record Err<T, E>(E error) implements Result<T, E> {}

    default <U> Result<U, E> map(Function<T, U> fn) {
        return switch (this) {
            case Ok<T, E>(T v)  -> new Ok<>(fn.apply(v));
            case Err<T, E> err  -> new Err<>(err.error());
        };
    }

    default <U> Result<U, E> flatMap(Function<T, Result<U, E>> fn) {
        return switch (this) {
            case Ok<T, E>(T v)  -> fn.apply(v);
            case Err<T, E> err  -> new Err<>(err.error());
        };
    }
}

// İstifadə
Result<User, String> user = findUser(42);
Result<String, String> email = user
    .map(u -> u.email())
    .flatMap(e -> validateEmail(e));

switch (email) {
    case Result.Ok<String, String>(String v)  -> System.out.println("Email OK: " + v);
    case Result.Err<String, String>(String e) -> System.out.println("Xəta: " + e);
}
```

### 9) Sealed + Records + Jackson

```java
@JsonTypeInfo(use = JsonTypeInfo.Id.DEDUCTION)
@JsonSubTypes({
    @JsonSubTypes.Type(Shape.Circle.class),
    @JsonSubTypes.Type(Shape.Square.class),
})
public sealed interface Shape {
    record Circle(double radius) implements Shape {}
    record Square(double side) implements Shape {}
}

// JSON-dan polymorphic deserialization
// {"radius": 5} → Circle
// {"side": 10}  → Square
```

### 10) Sealed Hierarchy limitləri

- Sealed class və permitted sub-class-ları **eyni module**-da olmalıdır.
- Eyni package-də olmalıdır (əgər unnamed module-dadırsa).
- Record-lar implicitly final-dir, amma sealed interface-in subtype-ı ola bilərlər.
- Generic sealed interface üçün `permits` deklarasiyası tam tip olmalıdır.

---

## PHP-də istifadəsi

### 1) PHP-də `sealed` yoxdur

PHP dil səviyyəsində sealed class dəstəkləmir. RFC təklifləri olub, amma indiyə qədər qəbul edilməyib. Workaround-lar var.

### 2) Abstract class + final subclass (yaxın emulasiya)

```php
<?php
abstract class Shape
{
    abstract public function area(): float;
}

final class Circle extends Shape
{
    public function __construct(public readonly float $radius) {}

    public function area(): float
    {
        return M_PI * $this->radius ** 2;
    }
}

final class Square extends Shape
{
    public function __construct(public readonly float $side) {}

    public function area(): float
    {
        return $this->side ** 2;
    }
}
```

Problem: developer öz `Shape` subclass-ını əlavə edə bilər. Compile-time yoxlama yoxdur.

### 3) Interface + final class

```php
<?php
interface Shape
{
    public function area(): float;
}

final class Circle implements Shape
{
    public function __construct(public readonly float $radius) {}

    public function area(): float
    {
        return M_PI * $this->radius ** 2;
    }
}

final class Square implements Shape
{
    public function __construct(public readonly float $side) {}

    public function area(): float
    {
        return $this->side ** 2;
    }
}

// Match istifadəsi
function describe(Shape $shape): string
{
    return match (true) {
        $shape instanceof Circle => "Dairə, radius: {$shape->radius}",
        $shape instanceof Square => "Kvadrat, yan: {$shape->side}",
    };
}
```

Amma hələ də yeni class yazsaq, `match` sonunda `default` qoymasa, fallback MATCH ERROR atacaq — runtime, compile-time yox.

### 4) Enum + interface (ən yaxın ADT)

```php
<?php
enum PaymentType: string
{
    case CREDIT_CARD   = 'credit_card';
    case BANK_TRANSFER = 'bank_transfer';
    case PAYPAL        = 'paypal';
    case CRYPTO        = 'crypto';
}

interface PaymentMethod
{
    public function type(): PaymentType;
}

final class CreditCard implements PaymentMethod
{
    public function __construct(
        public readonly string $number,
        public readonly string $cvv,
    ) {}

    public function type(): PaymentType { return PaymentType::CREDIT_CARD; }
}

final class BankTransfer implements PaymentMethod
{
    public function __construct(public readonly string $iban) {}

    public function type(): PaymentType { return PaymentType::BANK_TRANSFER; }
}

function calculateFee(PaymentMethod $method, string $amount): string
{
    return match ($method->type()) {
        PaymentType::CREDIT_CARD   => bcmul($amount, '0.029', 2),
        PaymentType::BANK_TRANSFER => '0.50',
        PaymentType::PAYPAL        => bcmul($amount, '0.034', 2),
        PaymentType::CRYPTO        => bcmul($amount, '0.01', 2),
    };
}
```

Bu variant ən güclüsüdür — match enum-u exhaustive yoxlayır (PHP 8.1+). Amma kod daha uzundur.

### 5) Enum-un özü ADT kimi (sadə hallarda)

```php
<?php
enum OrderStatus: string
{
    case PENDING   = 'pending';
    case PAID      = 'paid';
    case SHIPPED   = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING   => 'Gözləyir',
            self::PAID      => 'Ödənib',
            self::SHIPPED   => 'Göndərilib',
            self::DELIVERED => 'Çatdırılıb',
            self::CANCELLED => 'Ləğv edilib',
        };
    }

    public function canTransitionTo(OrderStatus $target): bool
    {
        return match([$this, $target]) {
            [self::PENDING, self::PAID],
            [self::PAID, self::SHIPPED],
            [self::SHIPPED, self::DELIVERED],
            [self::PENDING, self::CANCELLED],
            [self::PAID, self::CANCELLED]       => true,
            default                              => false,
        };
    }
}
```

Amma enum-a əlavə data (məsələn, `paidAt` timestamp) saxlamaq olmur. Bunun üçün class lazımdır.

### 6) Attribute-based workaround

```php
<?php
#[Attribute(Attribute::TARGET_CLASS)]
final class Sealed
{
    public function __construct(public array $permittedClasses) {}
}

#[Sealed(permittedClasses: [Circle::class, Square::class])]
abstract class Shape
{
    abstract public function area(): float;
}

// Static analyzer (PHPStan, Psalm) bu attribute-u oxuya bilər
// Runtime-da isə öz factory method-da yoxlaya bilərsən
```

PHPStan plugin yaza bilərsən ki, `extends Shape` olan amma `permittedClasses`-da olmayan class üçün xəbərdarlıq versin.

### 7) Laravel-də Result pattern

```php
<?php
interface Result
{
    public function isOk(): bool;
}

final readonly class Ok implements Result
{
    public function __construct(public mixed $value) {}
    public function isOk(): bool { return true; }
}

final readonly class Err implements Result
{
    public function __construct(public string $error) {}
    public function isOk(): bool { return false; }
}

// Service
class UserService
{
    public function findById(int $id): Result
    {
        $user = User::find($id);
        return $user === null
            ? new Err("İstifadəçi tapılmadı: {$id}")
            : new Ok($user);
    }
}

// Controller
$result = $service->findById($id);

return match (true) {
    $result instanceof Ok  => response()->json($result->value),
    $result instanceof Err => response()->json(['error' => $result->error], 404),
};
```

### 8) Symfony-də state machine

```php
<?php
// Symfony Workflow component — state machine üçün
# [AsMessageHandler]
class OrderStateMachine
{
    public function __construct(
        #[Target('order_state')]
        private readonly WorkflowInterface $workflow,
    ) {}

    public function transition(Order $order, string $transition): void
    {
        if (!$this->workflow->can($order, $transition)) {
            throw new InvalidStateException(
                "Keçid icazəsi yoxdur: {$transition}"
            );
        }
        $this->workflow->apply($order, $transition);
    }
}

# framework.yaml
# workflows:
#   order_state:
#     type: state_machine
#     supports:
#       - App\Entity\Order
#     places: [pending, paid, shipped, delivered, cancelled]
#     transitions:
#       pay:     { from: pending, to: paid }
#       ship:    { from: paid, to: shipped }
#       deliver: { from: shipped, to: delivered }
#       cancel:  { from: [pending, paid], to: cancelled }
```

Bu Java sealed + pattern matching-ə ekvivalent deyil, amma production-da stabil state machine-dir.

### 9) Match strict comparison + default ilə təhlükə

```php
<?php
$status = OrderStatus::PENDING;

$label = match($status) {
    OrderStatus::PENDING => 'Gözləyir',
    OrderStatus::PAID    => 'Ödənib',
    // Əgər SHIPPED, DELIVERED, CANCELLED unutsan,
    // `UnhandledMatchError` atır — runtime-də
};

// default qoysaq, bütün yeniləri gizli şəkildə handle edəcək — təhlükəli
$label = match($status) {
    OrderStatus::PENDING => 'Gözləyir',
    OrderStatus::PAID    => 'Ödənib',
    default              => 'Məlum deyil',   // yeni status qurtul unutdun = naməlum
};
```

PHPStan level 8-də exhaustive match yoxlanılır — bu, Java compile-time yoxlamasına alternativdir.

---

## Əsas fərqlər

| Xüsusiyyət | Java Sealed | PHP Workaround |
|---|---|---|
| Versiya | 17 (stable) | Dildə yoxdur |
| Açar söz | `sealed`, `permits`, `non-sealed` | Yox |
| Compile-time yoxlama | Bəli | Yalnız PHPStan/Psalm |
| Exhaustive switch | Bəli (Java 21) | `match` + enum |
| Sub-class əlavə etmək | permits-də olmalı | İstənilən class implement edə bilər |
| Pattern matching | `case TypeName(var x)` | `instanceof` + manual |
| Record patterns | Java 21-dən bəri | Yox |
| ADT modelləmə | Native | Manual (enum + interface + match) |
| Module boundary | Eyni module-də | N/A |
| Visitor əvəz etmə | Sealed + switch | `instanceof` + match(true) |

---

## Niyə belə fərqlər var?

**Java** statik, enterprise-yönümlü dildir. Sealed class tipi teori baxımından vacibdir: hierarchy-ni bağlayır, kompilyator exhaustiveness yoxlaya bilir. Bu Haskell, OCaml, Rust, Scala-dakı sum type konseptinin Java-ya gəlməsidir. Sealed + Record + pattern matching — Java-nı funksional dillərə yaxınlaşdırır. Brian Goetz (Java Language Architect) məqsədini açıq deyib: "Java-da Algebraic Data Types".

**PHP** daha çox pragmatik, tarix boyu scripting dilidir. Dil dizaynında "developerin hürriyyəti" prioritetdir — hər şey açıq, hər şey dəyişdirilə bilər. `final` class nisbətən yeni konvensiyadır. Sealed-i əlavə etmək PHP felsəfəsinə ziddir: "kim qoruyur?". Enum + match + interface kombinasiyası 80% hallarda kifayət edir. Qalan 20% üçün PHPStan, Psalm kimi static analyzer-lər istifadə olunur.

**Nəticə:** Java developer compile-time yoxlamaya güvənir; PHP developer test + static analyzer + runtime `UnhandledMatchError`-a. İkisi də işləyir, amma fərqli mühəndislik mədəniyyətləridir.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- `sealed`, `permits`, `non-sealed` açar sözləri
- Compile-time exhaustiveness yoxlaması
- Sealed + Record = native ADT
- Record patterns pattern matching-də
- Nested sealed hierarchy
- `DEDUCTION` ilə Jackson polymorphic JSON
- Pattern variable binding (`case Circle c`)

**Yalnız PHP-də:**
- `match(true)` və `instanceof` kombinasiyası (dynamic dispatch)
- Runtime-də class çıxarıb-yoxlamaq (`$class::getPermitted()`)
- Attribute-based konvensiya + PHPStan plugin yazmaq asanlığı

**Hər ikisində var:**
- Interface + final class hierarchy
- Exhaustive switch/match (enum-lar üçün)
- Static factory + discriminator
- Visitor pattern

---

## Best Practices

1. **Java: Domain model-ləri sealed interface + record kimi yaz.** Ən təmiz ADT.
2. **Java: `default` case-i çıxar sealed switch-lərdə** — compile-time exhaustiveness işləsin.
3. **Java: Yeni sub-type əlavə etdikdən sonra `permits` siyahısını yenilə**.
4. **PHP: Enum-u ilk seçim kimi götür** — sabit kiçik dəstlər üçün.
5. **PHP: Match-da `default` qoyma** — runtime-də `UnhandledMatchError` alınsın.
6. **PHP: PHPStan level 8 işlət** — exhaustive match yoxlaması avtomatikdir.
7. **PHP: Əlavə data olan variant-lar üçün interface + final class + match(true)**.
8. **State machine-lər üçün hər iki dildə sealed/workaround istifadə et** — if-else yerinə.
9. **Java: Result<T, E> kimi utility tiplər üçün sealed interface yaz** — Either/Result.
10. **PHP: Symfony Workflow component-dən istifadə et** murəkkəb state machine üçün.

---

## Yekun

- **Sealed class** (Java 17) — hierarchy-ni bağlayır, compiler exhaustiveness yoxlayır.
- **Sealed + Record = ADT** — Java 21-dən sonra funksional-stil domain modelling.
- **PHP-də sealed yoxdur** — enum + interface + final class ən yaxın variantdır.
- **PHP 8.1 match** — exhaustive yoxlama var, amma yalnız enum-lar üçün tam işləyir.
- **PHPStan level 8** — PHP-də exhaustive match yoxlamasını static analiz edir.
- **Result<T, E> pattern** — hər iki dildə işləyir, Java-da təmizdir.
- **State machine-lər** — sealed + switch (Java), Workflow component (PHP/Symfony).
- **Jackson `DEDUCTION`** — sealed + record-u JSON-dan avtomatik deserialize edir.
- **Visitor pattern-dən qaç** — sealed + switch daha təmizdir.
- **PHP developer üçün əsas alət:** enum methodları + match + interface hierarchy.
