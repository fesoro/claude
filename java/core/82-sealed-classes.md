# 82 — Java Sealed Classes — Geniş İzah

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Sealed Classes nədir?](#sealed-classes-nədir)
2. [permits — icazəli alt siniflər](#permits--icazəli-alt-siniflər)
3. [Sealed Interfaces](#sealed-interfaces)
4. [Pattern Matching ilə birlikdə](#pattern-matching-ilə-birlikdə)
5. [Domain Modeling ilə istifadə](#domain-modeling-ilə-istifadə)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Sealed Classes nədir?

**Sealed Classes** (Java 17, JEP 409) — hansı siniflərin extend edə biləcəyini açıq müəyyən edən siniflər. `sealed` açar sözü + `permits` ilə icazəli alt siniflər siyahısı.

```
Problem (sealed olmadan):
  abstract class Shape { }
  class Circle extends Shape { }
  class Rectangle extends Shape { }
  // İstənilən xarici sinif Shape-i extend edə bilər!
  class UnknownShape extends Shape { }  // Library-nin xəbəri olmur

Sealed ilə:
  sealed class Shape permits Circle, Rectangle { }
  // Yalnız Circle və Rectangle extend edə bilər
  // Başqa cəhd → compile-time xəta
```

```java
// Java 17+
public sealed class Shape
        permits Circle, Rectangle, Triangle {

    private final String color;

    protected Shape(String color) {
        this.color = color;
    }

    public String getColor() {
        return color;
    }
}

// Alt siniflər MÜTLƏQ bu üçündən biri olmalıdır:

// 1. final — başqa extend edilə bilməz
public final class Circle extends Shape {
    private final double radius;

    public Circle(String color, double radius) {
        super(color);
        this.radius = radius;
    }

    public double area() {
        return Math.PI * radius * radius;
    }
}

// 2. sealed — öz permits siyahısı ilə davam edir
public sealed class Rectangle extends Shape
        permits Square {

    protected final double width;
    protected final double height;

    public Rectangle(String color, double width, double height) {
        super(color);
        this.width = width;
        this.height = height;
    }

    public double area() {
        return width * height;
    }
}

// Rectangle-ın sealed alt sinfi
public final class Square extends Rectangle {

    public Square(String color, double side) {
        super(color, side, side);
    }
}

// 3. non-sealed — yenidən açıq (istənilən extend edə bilər)
public non-sealed class Triangle extends Shape {

    private final double base;
    private final double height;

    public Triangle(String color, double base, double height) {
        super(color);
        this.base = base;
        this.height = height;
    }

    public double area() {
        return 0.5 * base * height;
    }
}

// Triangle non-sealed olduğu üçün:
class IsoscelesTriangle extends Triangle { } // DOĞRU
class EquilateralTriangle extends Triangle { } // DOĞRU
```

---

## permits — icazəli alt siniflər

```java
// ─── Eyni paketdə olmalıdır (adətən) ─────────────────
// sealed sinif və permits-dəkilər eyni compilation unit-da ola bilər

// ─── Qısa forma — permits siyahısı çıxarıla bilər ────
// Alt siniflər sealed ilə eyni faylda olduqda
public sealed class Result<T> {

    // permits yazılmadıqda — eyni faylda tapılır
    public record Success<T>(T value) extends Result<T> {}
    public record Failure<T>(String error) extends Result<T> {}
    public record Loading<T>() extends Result<T> {}
}

// ─── Multi-level sealed hierarchy ─────────────────────
public sealed interface Notification
        permits EmailNotification, SmsNotification, PushNotification {

    String getRecipient();
    String getMessage();
}

public sealed interface EmailNotification extends Notification
        permits HtmlEmailNotification, PlainEmailNotification {

    String getSubject();
}

public final class HtmlEmailNotification implements EmailNotification {

    private final String recipient;
    private final String subject;
    private final String htmlBody;

    // constructor, getters...
    public String getRecipient() { return recipient; }
    public String getMessage() { return htmlBody; }
    public String getSubject() { return subject; }
}

public final class PlainEmailNotification implements EmailNotification {

    private final String recipient;
    private final String subject;
    private final String textBody;

    public String getRecipient() { return recipient; }
    public String getMessage() { return textBody; }
    public String getSubject() { return subject; }
}

public final class SmsNotification implements Notification {
    private final String recipient;
    private final String message;

    public String getRecipient() { return recipient; }
    public String getMessage() { return message; }
}

public final class PushNotification implements Notification {
    private final String recipient;
    private final String message;
    private final String deviceToken;

    public String getRecipient() { return recipient; }
    public String getMessage() { return message; }
}
```

---

## Sealed Interfaces

```java
// ─── Sealed interface — tipik use case ───────────────
// Bir əməliyyatın nəticəsini modelləşdirmək

public sealed interface OrderResult
        permits OrderResult.Success, OrderResult.Failure {

    record Success(Order order) implements OrderResult {}

    record Failure(String errorCode, String message) implements OrderResult {}
}

// İstifadə:
public OrderResult createOrder(OrderRequest request) {
    try {
        Order order = doCreate(request);
        return new OrderResult.Success(order);
    } catch (ValidationException e) {
        return new OrderResult.Failure("VALIDATION_ERROR", e.getMessage());
    } catch (Exception e) {
        return new OrderResult.Failure("INTERNAL_ERROR", "Daxili xəta baş verdi");
    }
}

// Çağırışda:
OrderResult result = orderService.createOrder(request);

switch (result) {
    case OrderResult.Success(Order order) ->
        log.info("Sifariş yaradıldı: {}", order.getId());
    case OrderResult.Failure(String code, String msg) ->
        log.error("Xəta [{}]: {}", code, msg);
}

// ─── Command Pattern sealed ilə ───────────────────────
public sealed interface OrderCommand
        permits CreateOrderCommand, CancelOrderCommand,
                ConfirmOrderCommand, ShipOrderCommand {

    String getOrderId();
}

public record CreateOrderCommand(
    String customerId,
    List<OrderItem> items,
    String deliveryAddress
) implements OrderCommand {

    @Override
    public String getOrderId() {
        return null; // Yeni sifariş
    }
}

public record CancelOrderCommand(
    String orderId,
    String reason
) implements OrderCommand {

    @Override
    public String getOrderId() {
        return orderId;
    }
}

public record ConfirmOrderCommand(
    String orderId
) implements OrderCommand {

    @Override
    public String getOrderId() {
        return orderId;
    }
}

public record ShipOrderCommand(
    String orderId,
    String trackingNumber
) implements OrderCommand {

    @Override
    public String getOrderId() {
        return orderId;
    }
}
```

---

## Pattern Matching ilə birlikdə

```java
// ─── switch expression + sealed = exhaustive ──────────
// Bütün case-lər əhatə olunursa default lazım deyil

double calculateArea(Shape shape) {
    return switch (shape) {
        case Circle c -> Math.PI * c.radius() * c.radius();
        case Rectangle r -> r.width() * r.height();
        case Triangle t -> 0.5 * t.base() * t.height();
        // Square Rectangle-in alt sinfi — Rectangle case onu da tutur
        // default lazım deyil — compiler bütün halları bilir
    };
}

// ─── Notification işləmə ──────────────────────────────
NotificationResult processNotification(Notification notification) {
    return switch (notification) {
        case HtmlEmailNotification html ->
            emailService.sendHtml(html.getRecipient(), html.getSubject(), html.getMessage());
        case PlainEmailNotification plain ->
            emailService.sendPlain(plain.getRecipient(), plain.getSubject(), plain.getMessage());
        case SmsNotification sms ->
            smsService.send(sms.getRecipient(), sms.getMessage());
        case PushNotification push ->
            pushService.send(push.getDeviceToken(), push.getMessage());
    };
}

// ─── Guarded patterns ─────────────────────────────────
String describeOrder(OrderResult result) {
    return switch (result) {
        case OrderResult.Success(Order order)
            when order.getTotalAmount().compareTo(new BigDecimal("1000")) > 0 ->
            "Böyük sifariş: " + order.getId();
        case OrderResult.Success(Order order) ->
            "Adi sifariş: " + order.getId();
        case OrderResult.Failure(String code, String msg)
            when "VALIDATION_ERROR".equals(code) ->
            "Validasiya xətası: " + msg;
        case OrderResult.Failure(String code, String msg) ->
            "Sistem xətası [" + code + "]: " + msg;
    };
}

// ─── Command handler ──────────────────────────────────
@Service
public class OrderCommandHandler {

    public void handle(OrderCommand command) {
        switch (command) {
            case CreateOrderCommand create ->
                orderService.create(create.customerId(), create.items());
            case CancelOrderCommand cancel ->
                orderService.cancel(cancel.orderId(), cancel.reason());
            case ConfirmOrderCommand confirm ->
                orderService.confirm(confirm.orderId());
            case ShipOrderCommand ship ->
                orderService.ship(ship.orderId(), ship.trackingNumber());
        }
        // Yeni command tipi əlavə edildikdə compiler burada xəta göstərəcək
    }
}
```

---

## Domain Modeling ilə istifadə

```java
// ─── Payment method hierarchy ─────────────────────────
public sealed interface PaymentMethod
        permits CreditCard, BankTransfer, CryptoCurrency, WalletBalance {

    Money getAmount();
    String getDescription();
}

public record CreditCard(
    String cardNumber,
    String cardHolder,
    YearMonth expiry,
    Money amount
) implements PaymentMethod {

    public CreditCard {
        Objects.requireNonNull(cardNumber, "Card number cannot be null");
        if (cardNumber.length() < 13 || cardNumber.length() > 19) {
            throw new IllegalArgumentException("Invalid card number length");
        }
    }

    @Override
    public String getDescription() {
        return "Credit Card ending in " + cardNumber.substring(cardNumber.length() - 4);
    }
}

public record BankTransfer(
    String iban,
    String bankCode,
    Money amount
) implements PaymentMethod {

    @Override
    public String getDescription() {
        return "Bank Transfer from IBAN " + iban.substring(0, 6) + "***";
    }
}

public record CryptoCurrency(
    String walletAddress,
    String currency, // "BTC", "ETH", "USDT"
    Money amount
) implements PaymentMethod {

    @Override
    public String getDescription() {
        return currency + " from " + walletAddress.substring(0, 8) + "...";
    }
}

public record WalletBalance(
    String userId,
    Money amount
) implements PaymentMethod {

    @Override
    public String getDescription() {
        return "Wallet balance: " + amount;
    }
}

// Payment processor — exhaustive switch
@Service
public class PaymentProcessor {

    public PaymentReceipt process(PaymentMethod method) {
        return switch (method) {
            case CreditCard card -> processCreditCard(card);
            case BankTransfer transfer -> processBankTransfer(transfer);
            case CryptoCurrency crypto -> processCrypto(crypto);
            case WalletBalance wallet -> processWallet(wallet);
            // Yeni PaymentMethod əlavə etsəniz → compile error burada
        };
    }

    public BigDecimal calculateFee(PaymentMethod method) {
        return switch (method) {
            case CreditCard card -> card.amount().value().multiply(new BigDecimal("0.015")); // 1.5%
            case BankTransfer ignored -> BigDecimal.ZERO;                                     // pulsuz
            case CryptoCurrency crypto -> crypto.amount().value().multiply(new BigDecimal("0.01")); // 1%
            case WalletBalance ignored -> BigDecimal.ZERO;                                    // pulsuz
        };
    }
}

// ─── Error hierarchy ──────────────────────────────────
public sealed interface AppError
        permits ValidationError, NotFoundError, AuthError, InternalError {

    String getCode();
    String getMessage();

    default boolean isRetryable() {
        return switch (this) {
            case InternalError ignored -> true;
            default -> false;
        };
    }
}

public record ValidationError(
    String field,
    String message
) implements AppError {
    public String getCode() { return "VALIDATION_ERROR"; }
    public String getMessage() { return field + ": " + message; }
}

public record NotFoundError(
    String resourceType,
    String resourceId
) implements AppError {
    public String getCode() { return "NOT_FOUND"; }
    public String getMessage() {
        return resourceType + " tapılmadı: " + resourceId;
    }
}

public record AuthError(String message) implements AppError {
    public String getCode() { return "UNAUTHORIZED"; }
    public String getMessage() { return message; }
}

public record InternalError(
    String message,
    Throwable cause
) implements AppError {
    public String getCode() { return "INTERNAL_ERROR"; }
    public String getMessage() { return message; }
}
```

---

## İntervyu Sualları

### 1. Sealed class nədir?
**Cavab:** Java 17-də (JEP 409) gəldi. Hansı siniflərin extend edə biləcəyini `permits` siyahısı ilə məhdudlaşdırır. Alt siniflər `final`, `sealed`, ya da `non-sealed` olmalıdır. Compile-time exhaustiveness yoxlaması sağlayır — switch expression-da bütün hallar məlumdur. `abstract class`-dan fərqi: extend edə biləcəklər açıq müəyyəndir.

### 2. Sealed class-ın üstünlükləri?
**Cavab:** (1) **Exhaustiveness** — switch/pattern matching-də bütün alt tiplər bilinir, `default` lazım deyil, yeni tip əlavə edildikdə compiler xəbərdarlıq edir. (2) **Encapsulation** — üçüncü tərəf kitabxanalar sinif hierarchyni genişləndirə bilmir. (3) **Domain modeling** — `Result<T>` (Success/Failure), ADT (Algebraic Data Types) kimi funksional pattern-lər. (4) **Dokumentasiya** — kod özü icazəli sinifləri göstərir.

### 3. final, sealed, non-sealed fərqi?
**Cavab:** Sealed class-ın alt sinfi bu üç modifier-dən birini mütləq almalıdır. `final` — heç kim extend edə bilməz (hierarchy burada bitir). `sealed` — özünün `permits` siyahısı var (hierarchy davam edir, amma idarəli). `non-sealed` — hər kəs extend edə bilər (hierarchy açılır); `non-sealed` istifadəsi nadir vəziyyət — adətən third-party extension nöqtəsi üçün.

### 4. Sealed class vs enum?
**Cavab:** Enum — sabit dəyərlər toplusu, hər instance bir dəfə mövcuddur. Sealed class — hər alt tipin öz state-i ola bilər, instance sayı məhdudsuzdur (`new Circle(...)` dəfələrlə çağırıla bilər). `Result.Success(order)` hər çağırışda yeni instance yaradır — enum bu işi görə bilməz. Sealed + record birlikdə enuma oxşar amma daha güclü pattern verir.

### 5. Sealed class Pattern Matching ilə necə işləyir?
**Cavab:** Java 21-dəki switch pattern matching sealed class ilə `exhaustive` olur. Compiler sealed sinifdəki bütün `permits` alt tiplərini bilir — `switch`-də hamısı əhatə olunarsa `default` tələb olunmur. Yeni alt tip əlavə edildikdə mövcud switch-lər compile xətası verir — runtime NullPointerException əvəzinə compile-time xəbərdarlıq. Bu, type-safe algebraic data type (ADT) modelini mümkün edir.

*Son yenilənmə: 2026-04-10*
