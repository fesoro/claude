# Java Pattern Matching — Geniş İzah

## Mündəricat
1. [Pattern Matching nədir?](#pattern-matching-nədir)
2. [instanceof Pattern Matching](#instanceof-pattern-matching)
3. [Switch Pattern Matching](#switch-pattern-matching)
4. [Guarded Patterns](#guarded-patterns)
5. [Record Patterns](#record-patterns)
6. [Nested Patterns](#nested-patterns)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Pattern Matching nədir?

**Pattern Matching** — tip yoxlama + cast + dəyişkən tanımlama əməliyyatlarını birləşdirən Java xüsusiyyəti.

```java
// ƏVVƏL (Java 16-dan qabaq):
Object obj = getObject();

if (obj instanceof String) {
    String str = (String) obj; // Əl ilə cast
    System.out.println(str.toUpperCase());
}

// SONRA (Java 16+ instanceof pattern):
Object obj = getObject();

if (obj instanceof String str) { // Avtomatik cast + dəyişkən
    System.out.println(str.toUpperCase());
}

// Daha sıxışdırılmış:
if (getObject() instanceof String str && str.length() > 3) {
    System.out.println(str);
}
```

---

## instanceof Pattern Matching

```java
// Java 16 — JEP 394

class PatternMatchingExamples {

    // ─── Əsas pattern ─────────────────────────────────
    void processShape(Object shape) {
        if (shape instanceof Circle c) {
            System.out.println("Dairə, radius: " + c.radius());
        } else if (shape instanceof Rectangle r) {
            System.out.println("Düzbucaq, " + r.width() + "x" + r.height());
        } else if (shape instanceof Triangle t) {
            System.out.println("Üçbucaq, sahə: " + t.area());
        }
    }

    // ─── Şərtlə birlikdə ──────────────────────────────
    void processObject(Object obj) {
        if (obj instanceof String s && s.length() > 5) {
            // s yalnız bu blokda mövcuddur
            System.out.println("Uzun string: " + s.toUpperCase());
        }

        if (obj instanceof Integer i && i > 0) {
            System.out.println("Müsbət tam ədəd: " + i);
        }
    }

    // ─── Negasiya ─────────────────────────────────────
    void negationPattern(Object obj) {
        if (!(obj instanceof String s)) {
            // s bu blokda əlçatmazdır
            throw new IllegalArgumentException("String gözlənilir");
        }
        // s burada mövcuddur (flow typing)
        System.out.println(s.toUpperCase());
    }

    // ─── Equals ilə istifadə ──────────────────────────
    @Override
    public boolean equals(Object obj) {
        return obj instanceof Order other
            && Objects.equals(this.id, other.id)
            && Objects.equals(this.customerId, other.customerId);
    }

    // ─── YANLIŞ istifadə ──────────────────────────────
    void wrongUsage(Object obj) {
        if (obj instanceof String s || s.isEmpty()) {
            // COMPILE ERROR: s ikinci şərtdə mövcud olmaya bilər
        }
    }
}
```

---

## Switch Pattern Matching

```java
// Java 21 — JEP 441 (final)

class SwitchPatternExamples {

    // ─── switch expression ilə ────────────────────────
    double calculateArea(Shape shape) {
        return switch (shape) {
            case Circle c -> Math.PI * c.radius() * c.radius();
            case Rectangle r -> r.width() * r.height();
            case Triangle t -> 0.5 * t.base() * t.height();
            // sealed class → exhaustive → default lazım deyil
        };
    }

    // ─── Object üzərində switch ───────────────────────
    String formatValue(Object obj) {
        return switch (obj) {
            case Integer i -> "Integer: " + i;
            case Long l -> "Long: " + l + "L";
            case Double d -> "Double: " + d;
            case String s -> "String: \"" + s + "\"";
            case null -> "null";
            default -> "Naməlum: " + obj.getClass().getSimpleName();
        };
    }

    // ─── null handling ────────────────────────────────
    void handleNull(String str) {
        switch (str) {
            case null -> System.out.println("null dəyər");
            case "hello" -> System.out.println("Salam!");
            default -> System.out.println("Digər: " + str);
        }
    }

    // ─── switch statement vs expression ───────────────
    void switchStatement(Object obj) {
        switch (obj) {
            case Integer i -> System.out.println("Int: " + i);
            case String s -> System.out.println("String: " + s);
            default -> System.out.println("Digər");
        }
    }

    String switchExpression(Object obj) {
        return switch (obj) {
            case Integer i -> "Int: " + i;
            case String s -> "String: " + s;
            default -> "Digər";
        };
    }

    // ─── Enum ilə switch ──────────────────────────────
    String describeStatus(OrderStatus status) {
        return switch (status) {
            case PENDING -> "Gözləmədə";
            case CONFIRMED -> "Təsdiqləndi";
            case SHIPPED -> "Göndərildi";
            case DELIVERED -> "Çatdırıldı";
            case CANCELLED -> "Ləğv edildi";
        };
    }
}
```

---

## Guarded Patterns

```java
// Java 21 — `when` guard clause

class GuardedPatternExamples {

    // ─── when şərti ───────────────────────────────────
    String classifyOrder(Order order) {
        return switch (order) {
            case Order o when o.getTotalAmount().compareTo(new BigDecimal("1000")) > 0 ->
                "Premium sifariş";
            case Order o when o.getTotalAmount().compareTo(new BigDecimal("100")) > 0 ->
                "Orta sifariş";
            case Order o ->
                "Kiçik sifariş";
        };
    }

    // ─── Shape + guard ────────────────────────────────
    String describeShape(Shape shape) {
        return switch (shape) {
            case Circle c when c.radius() > 100 -> "Böyük dairə (r=" + c.radius() + ")";
            case Circle c when c.radius() > 10 -> "Orta dairə (r=" + c.radius() + ")";
            case Circle c -> "Kiçik dairə (r=" + c.radius() + ")";
            case Rectangle r when r.width() == r.height() -> "Kvadrat (" + r.width() + "x" + r.height() + ")";
            case Rectangle r -> "Düzbucaq (" + r.width() + "x" + r.height() + ")";
            case Triangle t -> "Üçbucaq";
        };
    }

    // ─── String guard ─────────────────────────────────
    void processInput(Object input) {
        switch (input) {
            case String s when s.isBlank() ->
                throw new IllegalArgumentException("Boş string qəbul edilmir");
            case String s when s.length() > 100 ->
                log.warn("Uzun string: {} simvol", s.length());
            case String s ->
                processString(s);
            case Integer i when i < 0 ->
                throw new IllegalArgumentException("Mənfi rəqəm qəbul edilmir");
            case Integer i ->
                processInteger(i);
            case null ->
                throw new NullPointerException("null qəbul edilmir");
            default ->
                throw new UnsupportedOperationException("Dəstəklənmir: " + input.getClass());
        }
    }
}
```

---

## Record Patterns

```java
// Java 21 — JEP 440

// ─── Record destructuring ─────────────────────────────
record Point(int x, int y) {}
record Line(Point start, Point end) {}

void processPoint(Object obj) {
    if (obj instanceof Point(int x, int y)) {
        System.out.println("Point at (" + x + ", " + y + ")");
    }
}

// ─── switch ilə record pattern ────────────────────────
double length(Object shape) {
    return switch (shape) {
        case Point(int x, int y) -> Math.sqrt(x * x + y * y); // Origin-dən məsafə
        case Line(Point(int x1, int y1), Point(int x2, int y2)) -> {
            double dx = x2 - x1;
            double dy = y2 - y1;
            yield Math.sqrt(dx * dx + dy * dy);
        }
        default -> throw new IllegalArgumentException("Unknown shape");
    };
}

// ─── Domain record patterns ───────────────────────────
sealed interface OrderEvent permits OrderCreated, OrderConfirmed, OrderCancelled {}

record OrderCreated(String orderId, String customerId, BigDecimal amount) implements OrderEvent {}
record OrderConfirmed(String orderId, Instant confirmedAt) implements OrderEvent {}
record OrderCancelled(String orderId, String reason) implements OrderEvent {}

void handleEvent(OrderEvent event) {
    switch (event) {
        case OrderCreated(String id, String customerId, BigDecimal amount) -> {
            log.info("Yeni sifariş: {} (müştəri: {}, məbləğ: {})", id, customerId, amount);
            notifyCustomer(customerId, "Sifarişiniz yaradıldı");
        }
        case OrderConfirmed(String id, Instant confirmedAt) -> {
            log.info("Sifariş təsdiqləndi: {} at {}", id, confirmedAt);
        }
        case OrderCancelled(String id, String reason) -> {
            log.warn("Sifariş ləğv edildi: {} - {}", id, reason);
            processRefund(id);
        }
    }
}

// ─── Generic record pattern ───────────────────────────
record Pair<A, B>(A first, B second) {}

void processPair(Object obj) {
    if (obj instanceof Pair(String name, Integer age)) {
        System.out.println(name + " is " + age + " years old");
    }

    if (obj instanceof Pair(Integer a, Integer b)) {
        System.out.println("Sum: " + (a + b));
    }
}
```

---

## Nested Patterns

```java
// ─── İç içə pattern matching ──────────────────────────
sealed interface Expr permits Num, Add, Mul, Neg {}
record Num(int value) implements Expr {}
record Add(Expr left, Expr right) implements Expr {}
record Mul(Expr left, Expr right) implements Expr {}
record Neg(Expr expr) implements Expr {}

int evaluate(Expr expr) {
    return switch (expr) {
        case Num(int n) -> n;
        case Add(Expr l, Expr r) -> evaluate(l) + evaluate(r);
        case Mul(Expr l, Expr r) -> evaluate(l) * evaluate(r);
        case Neg(Expr e) -> -evaluate(e);
    };
}

// ─── HTTP Response processing ─────────────────────────
sealed interface HttpResult permits HttpResult.Ok, HttpResult.Error {}

record Ok(int statusCode, String body) implements HttpResult {}
record Error(int statusCode, String errorMessage, Throwable cause) implements HttpResult {}

// İstifadə:
String processResult(HttpResult result) {
    return switch (result) {
        case Ok(int code, String body) when code == 200 -> body;
        case Ok(int code, String body) when code == 201 -> "Created: " + body;
        case Ok(int code, String body) -> "Status " + code + ": " + body;
        case Error(int code, String msg, Throwable cause)
            when code == 404 -> throw new NotFoundException(msg);
        case Error(int code, String msg, Throwable cause)
            when code >= 500 -> throw new ServiceUnavailableException(msg, cause);
        case Error(int code, String msg, Throwable ignored) ->
            throw new ClientException(code, msg);
    };
}

// ─── Order processing pipeline ────────────────────────
sealed interface ProcessingStep
        permits ValidateStep, PriceStep, InventoryStep, PaymentStep {}

record ValidateStep(OrderRequest request) implements ProcessingStep {}
record PriceStep(Order order, BigDecimal basePrice) implements ProcessingStep {}
record InventoryStep(Order order, BigDecimal finalPrice) implements ProcessingStep {}
record PaymentStep(Order order, BigDecimal amount, String paymentMethod) implements ProcessingStep {}

ProcessingResult process(ProcessingStep step) {
    return switch (step) {
        case ValidateStep(OrderRequest req) when req.customerId() == null ->
            ProcessingResult.error("customerId boş ola bilməz");
        case ValidateStep(OrderRequest req) when req.items().isEmpty() ->
            ProcessingResult.error("Items boş ola bilməz");
        case ValidateStep(OrderRequest req) ->
            ProcessingResult.next(new PriceStep(req.toOrder(), calculateBasePrice(req)));
        case PriceStep(Order order, BigDecimal price) ->
            ProcessingResult.next(new InventoryStep(order, applyDiscounts(order, price)));
        case InventoryStep(Order order, BigDecimal price) ->
            checkInventory(order).isAvailable()
                ? ProcessingResult.next(new PaymentStep(order, price, "CARD"))
                : ProcessingResult.error("Stokda yoxdur");
        case PaymentStep(Order order, BigDecimal amount, String method) ->
            processPayment(order, amount, method);
    };
}
```

---

## İntervyu Sualları

### 1. Java-da Pattern Matching nədir?
**Cavab:** Tip yoxlama, cast və dəyişkən bağlamağı birləşdirən xüsusiyyət. `instanceof` pattern (Java 16) — `if (obj instanceof String s)` əl ilə cast lazım deyil. Switch pattern (Java 21) — switch-də tip testləri. Record destructuring (Java 21) — record-un field-lərini birbaşa açmaq. Sealed class ilə birlikdə `exhaustive` switch.

### 2. Guarded pattern (`when`) nədir?
**Cavab:** Switch pattern case-inə əlavə şərt qoymaq üçün `when` açar sözü. `case Circle c when c.radius() > 100` — həm tip yoxlama, həm şərt. Sıra vacibdir — daha spesifik case-lər əvvəl gəlməlidir (compiler xəbərdarlıq verir). JEP 441 ilə Java 21-də final oldu.

### 3. Record pattern nədir?
**Cavab:** Java 21 (JEP 440). Record-un komponentlərini `switch`/`instanceof`-da birbaşa açmaq: `case OrderCreated(String id, String customerId, BigDecimal amount)` — ayrıca `.orderId()`, `.customerId()` çağırmağa ehtiyac yoxdur. Nested record patterns da mümkündür: `case Line(Point(int x1, int y1), Point(int x2, int y2))`.

### 4. Pattern Matching-in switch ilə əvvəlki `if-else instanceof` chain-dən fərqi?
**Cavab:** Sealed class ilə `exhaustive` — bütün tiplər əhatə edilmişsə `default` lazım deyil, yeni tip əlavə edildikdə compiler xəta verir. Daha oxunaqlı. `when` guard clause ilə tip yoxlama + şərt eyni anda. Expression kimi — dəyər qaytarır. Null handling — `case null` ilə ayrıca hallanır.

### 5. Flow-sensitive typing nədir?
**Cavab:** `if (obj instanceof String s)` — `if` blokunun içərisində `s` avtomatik `String` tipindədir, cast lazım deyil. `if (!(obj instanceof String s)) return;` — `return`-dən sonra `s` mövcuddur çünki kod ora çatdıqda `obj` mütləq `String`-dir. Compiler kod axınını analiz edir — buna flow-sensitive ya da flow typing deyilir. `&&` ilə şərtdə sol tərəf doğruysa, sağ tərəfdə `s` mövcuddur.

*Son yenilənmə: 2026-04-10*
