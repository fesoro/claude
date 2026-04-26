# 98 — Sealed Classes — Domain Modeling

> **Seviyye:** Senior ⭐⭐⭐

## Mündəricat
1. [Sealed Classes nədir?](#sealed-classes-nədir)
2. [Pattern Matching ilə birlikdə](#pattern-matching-ilə-birlikdə)
3. [Domain Modeling use case-ləri](#domain-modeling-use-case-ləri)
4. [Result/Either pattern](#resulteither-pattern)
5. [Spring REST ilə inteqrasiya](#spring-rest-ilə-inteqrasiya)
6. [Laravel ilə müqayisə](#laravel-ilə-müqayisə)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Sealed Classes nədir?

Java 17-də stable gəldi. Hansı class-ların extend edə biləcəyini məhdudlaşdırır.

```java
// Sealed class — yalnız permits-dəkilər extend edə bilər:
public sealed class Shape
    permits Circle, Rectangle, Triangle {}

public final class Circle extends Shape {
    private final double radius;
    public Circle(double radius) { this.radius = radius; }
    public double radius() { return radius; }
}

public final class Rectangle extends Shape {
    private final double width, height;
    public Rectangle(double width, double height) {
        this.width = width;
        this.height = height;
    }
    // ...
}

public final class Triangle extends Shape {
    private final double a, b, c;
    // ...
}
```

**Niyə lazımdır?** Compiler bütün mümkün type-ları **bilir**. Switch expression `exhaustive` ola bilər — else branch lazım deyil.

```java
// Exhaustive switch — bütün mümkün type-lar verilmişdir:
double area = switch (shape) {
    case Circle c -> Math.PI * c.radius() * c.radius();
    case Rectangle r -> r.width() * r.height();
    case Triangle t -> calculateTriangleArea(t.a(), t.b(), t.c());
    // else lazım deyil — compiler hamısını bilir
};
```

Yeni `Diamond` class əlavə etsəniz → compile xətası (permits-ə əlavə etmədən).

---

## Pattern Matching ilə birlikdə

Java 21-də Pattern Matching switch stable gəldi:

```java
// Type pattern:
String describe(Shape shape) {
    return switch (shape) {
        case Circle c when c.radius() > 10 -> "Large circle: " + c.radius();
        case Circle c -> "Small circle: " + c.radius();
        case Rectangle r when r.width() == r.height() -> "Square: " + r.width();
        case Rectangle r -> "Rectangle: " + r.width() + "x" + r.height();
        case Triangle t -> "Triangle";
    };
}

// instanceof ilə (klassik yanaşma):
if (shape instanceof Circle c) {
    System.out.println("Circle radius: " + c.radius());
} else if (shape instanceof Rectangle r) {
    System.out.println("Rectangle " + r.width() + "x" + r.height());
}
```

---

## Domain Modeling use case-ləri

### Payment Status:

```java
// Sealed interface (interface də sealed ola bilər):
public sealed interface PaymentResult
    permits PaymentResult.Success, PaymentResult.Failed, PaymentResult.Pending {

    record Success(String transactionId, BigDecimal amount) implements PaymentResult {}
    record Failed(String errorCode, String message) implements PaymentResult {}
    record Pending(String referenceId, LocalDateTime estimatedAt) implements PaymentResult {}
}

// Service:
@Service
public class PaymentService {

    public PaymentResult processPayment(PaymentRequest req) {
        try {
            String txId = gateway.charge(req.amount(), req.cardToken());
            return new PaymentResult.Success(txId, req.amount());
        } catch (InsufficientFundsException e) {
            return new PaymentResult.Failed("INSUFFICIENT_FUNDS", e.getMessage());
        } catch (GatewayTimeoutException e) {
            return new PaymentResult.Pending(e.getReferenceId(), LocalDateTime.now().plusMinutes(5));
        }
    }
}

// Controller:
@PostMapping("/pay")
public ResponseEntity<?> pay(@RequestBody PaymentRequest req) {
    return switch (paymentService.processPayment(req)) {
        case PaymentResult.Success s ->
            ResponseEntity.ok(new PaymentSuccessResponse(s.transactionId(), s.amount()));
        case PaymentResult.Failed f ->
            ResponseEntity.unprocessableEntity().body(new ErrorResponse(f.errorCode(), f.message()));
        case PaymentResult.Pending p ->
            ResponseEntity.accepted().body(new PendingResponse(p.referenceId(), p.estimatedAt()));
    };
}
```

### Notification Channel:

```java
public sealed interface NotificationChannel
    permits NotificationChannel.Email, NotificationChannel.SMS, NotificationChannel.Push {

    record Email(String to, String subject, String htmlBody) implements NotificationChannel {}
    record SMS(String phoneNumber, String text) implements NotificationChannel {}
    record Push(String deviceToken, String title, String body, Map<String, String> data)
        implements NotificationChannel {}
}

@Service
public class NotificationDispatcher {

    public void dispatch(NotificationChannel channel) {
        switch (channel) {
            case NotificationChannel.Email e -> emailService.send(e.to(), e.subject(), e.htmlBody());
            case NotificationChannel.SMS s -> smsService.send(s.phoneNumber(), s.text());
            case NotificationChannel.Push p -> pushService.send(p.deviceToken(), p.title(), p.body(), p.data());
        }
    }
}
```

### Order State Machine:

```java
public sealed interface OrderState
    permits OrderState.Draft, OrderState.Pending, OrderState.Processing,
            OrderState.Shipped, OrderState.Delivered, OrderState.Cancelled {

    record Draft(LocalDateTime createdAt) implements OrderState {}
    record Pending(LocalDateTime submittedAt, String paymentRef) implements OrderState {}
    record Processing(LocalDateTime confirmedAt, String warehouseId) implements OrderState {}
    record Shipped(LocalDateTime shippedAt, String trackingNumber) implements OrderState {}
    record Delivered(LocalDateTime deliveredAt) implements OrderState {}
    record Cancelled(LocalDateTime cancelledAt, String reason) implements OrderState {}
}

// State transitions:
public OrderState transition(OrderState current, OrderEvent event) {
    return switch (current) {
        case OrderState.Draft d when event == OrderEvent.SUBMIT ->
            new OrderState.Pending(LocalDateTime.now(), generatePaymentRef());

        case OrderState.Pending p when event == OrderEvent.CONFIRM ->
            new OrderState.Processing(LocalDateTime.now(), assignWarehouse());

        case OrderState.Processing pr when event == OrderEvent.SHIP ->
            new OrderState.Shipped(LocalDateTime.now(), generateTrackingNumber());

        case OrderState.Shipped s when event == OrderEvent.DELIVER ->
            new OrderState.Delivered(LocalDateTime.now());

        case OrderState.Draft d, OrderState.Pending p, OrderState.Processing pr
            when event == OrderEvent.CANCEL ->
            new OrderState.Cancelled(LocalDateTime.now(), "User cancelled");

        default -> throw new InvalidStateTransitionException(current, event);
    };
}
```

---

## Result/Either pattern

Exception-siz error handling:

```java
// Result type:
public sealed interface Result<T>
    permits Result.Ok, Result.Err {

    record Ok<T>(T value) implements Result<T> {}
    record Err<T>(String code, String message) implements Result<T> {}

    // Static factories:
    static <T> Result<T> ok(T value) { return new Ok<>(value); }
    static <T> Result<T> err(String code, String message) { return new Err<>(code, message); }

    // Functional methods:
    default boolean isOk() { return this instanceof Ok; }
    default boolean isErr() { return this instanceof Err; }

    default <U> Result<U> map(java.util.function.Function<T, U> mapper) {
        return switch (this) {
            case Ok<T> ok -> Result.ok(mapper.apply(ok.value()));
            case Err<T> err -> Result.err(err.code(), err.message());
        };
    }
}

// Service — exception atmadan:
public Result<UserDto> createUser(CreateUserRequest req) {
    if (userRepo.existsByEmail(req.email())) {
        return Result.err("EMAIL_EXISTS", "Email already registered");
    }

    if (!passwordValidator.isStrong(req.password())) {
        return Result.err("WEAK_PASSWORD", "Password must be at least 12 chars with numbers");
    }

    User user = userRepo.save(buildUser(req));
    return Result.ok(UserDto.fromEntity(user));
}

// Controller:
@PostMapping
public ResponseEntity<?> createUser(@Valid @RequestBody CreateUserRequest req) {
    return switch (userService.createUser(req)) {
        case Result.Ok<UserDto> ok ->
            ResponseEntity.status(201).body(ok.value());
        case Result.Err<UserDto> err ->
            ResponseEntity.badRequest().body(
                Map.of("code", err.code(), "message", err.message())
            );
    };
}
```

---

## Spring REST ilə inteqrasiya

### Jackson ilə Sealed class serialize:

```java
// Jackson 2.15+ sealed type-ları dəstəkləyir:
@JsonTypeInfo(use = JsonTypeInfo.Id.NAME, property = "type")
@JsonSubTypes({
    @JsonSubTypes.Type(value = PaymentResult.Success.class, name = "success"),
    @JsonSubTypes.Type(value = PaymentResult.Failed.class, name = "failed"),
    @JsonSubTypes.Type(value = PaymentResult.Pending.class, name = "pending")
})
public sealed interface PaymentResult
    permits PaymentResult.Success, PaymentResult.Failed, PaymentResult.Pending {

    record Success(String transactionId, BigDecimal amount) implements PaymentResult {}
    record Failed(String errorCode, String message) implements PaymentResult {}
    record Pending(String referenceId, LocalDateTime estimatedAt) implements PaymentResult {}
}

// JSON output:
// {"type": "success", "transactionId": "TX123", "amount": 99.99}
// {"type": "failed", "errorCode": "DECLINED", "message": "Card declined"}
```

### Repository ilə birlikdə:

```java
// Sealed interface event sourcing üçün:
public sealed interface OrderEvent
    permits OrderEvent.Created, OrderEvent.StatusChanged, OrderEvent.Cancelled {

    record Created(Long orderId, Long userId, List<OrderItem> items) implements OrderEvent {}
    record StatusChanged(Long orderId, String from, String to) implements OrderEvent {}
    record Cancelled(Long orderId, String reason, Long cancelledBy) implements OrderEvent {}
}

@Repository
public interface OrderEventRepository extends JpaRepository<OrderEventEntity, Long> {
    List<OrderEventEntity> findByOrderIdOrderByCreatedAtAsc(Long orderId);
}
```

---

## Laravel ilə müqayisə

```php
// PHP-də Union Types (PHP 8+):
function processPayment(array $data): Success|Failed|Pending {
    // PHP Union Types — amma exhaustive switch yoxdur
}

// PHP-də match ilə:
$result = processPayment($data);
$response = match(true) {
    $result instanceof Success => response()->json(['tx' => $result->txId]),
    $result instanceof Failed => response()->json(['error' => $result->message], 422),
    $result instanceof Pending => response()->json(['ref' => $result->refId], 202),
};
```

```java
// Java Sealed Classes — compiler bütün case-ləri yoxlayır:
PaymentResult result = paymentService.process(req);
return switch (result) {
    case PaymentResult.Success s -> ResponseEntity.ok(s.transactionId());
    case PaymentResult.Failed f -> ResponseEntity.unprocessableEntity().body(f.message());
    case PaymentResult.Pending p -> ResponseEntity.accepted().body(p.referenceId());
    // Yeni type əlavə etsəniz → compile xətası! PHP-də yoxdur
};
```

**Əsas fərq:** Java sealed classes compile-time exhaustiveness — bütün hallar işlənmədikdə build fail olur. PHP-də runtime-da problem yaranır.

---

## İntervyu Sualları

**S: Sealed class nədir, niyə lazımdır?**
C: Hansı class-ların extend edə biləcəyini sınırlayan Java 17 feature. Compiler bütün alt type-ları bilir → switch exhaustive ola bilər → compile-time safety. Yeni subtype əlavə etmək bütün switch yerlərini update etməyi məcbur edir.

**S: Sealed class vs enum fərqi?**
C: Enum — hər instance eyni type, state yoxdur. Sealed class — hər subtype öz field-lərinə sahib ola bilər, record ilə birlikdə güclüdür. Enum.PENDING sadədir; `record Pending(LocalDateTime eta)` isə state saxlayır.

**S: Result pattern niyə exception-dan yaxşıdır?**
C: Exception-lar control flow üçün deyil, exceptional hallar üçündür. Result type — method signature-da error mümkün olduğunu göstərir. Caller onu işləmək **məcburiyyətindədir** (switch exhaustive). Exception-ları ignore etmək olar, Result-u isə yox.

**S: Sealed interface vs sealed abstract class?**
C: İkisi də işləyir. Interface multiple implementation imkan verir (class birini, sealed interface-i implements edə bilər). Abstract class constructor logic paylaşmaq üçün. Record-larla birlikdə `sealed interface + record permits` ən yığcam pattern-dir.
