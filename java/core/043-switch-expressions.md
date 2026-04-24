# 043 — Java Switch Expressions — Geniş İzah
**Səviyyə:** Orta


## Mündəricat
1. [Switch Expressions nədir?](#switch-expressions-nədir)
2. [Arrow (→) sintaksisi](#arrow--sintaksisi)
3. [yield ifadəsi](#yield-ifadəsi)
4. [Pattern Matching ilə Switch](#pattern-matching-ilə-switch)
5. [Guarded Patterns](#guarded-patterns)
6. [Exhaustiveness — tam əhatə](#exhaustiveness--tam-əhatə)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Switch Expressions nədir?

```
Tarix:
  Java 14 — Switch Expressions (standard feature)
  Java 16 — instanceof Pattern Matching (standard)
  Java 17 — Sealed Classes (standard)
  Java 21 — Switch Pattern Matching (standard)

Köhnə switch statement problemləri:
  → Fall-through: break unutsaq → alt case-ə düşür
  → Return deyə bilmirik birbaşa (statement-dir, expression deyil)
  → Verbose: hər case-ə break
  → null handling yoxdur (NullPointerException!)

Yeni switch expression:
  → Expression: dəyər qaytarır, dəyişkənə mənimsədilir
  → Arrow (→): fall-through yoxdur, break lazım deyil
  → yield: block içindən dəyər qaytarır
  → Exhaustive: bütün case-lər əhatə olunmalı (enum, sealed)
  → null case: explicit handling mümkün
```

---

## Arrow (→) sintaksisi

```java
// ─── Köhnə switch statement ───────────────────────────────
String oldWay(int day) {
    String result;
    switch (day) {
        case 1:
            result = "Bazar ertəsi";
            break;         // Unutsaq → fall-through!
        case 2:
            result = "Çərşənbə axşamı";
            break;
        case 6:
        case 7:
            result = "Həftəsonu";
            break;
        default:
            result = "Digər gün";
    }
    return result;
}

// ─── Yeni switch expression — arrow sintaksisi ────────────
String newWay(int day) {
    return switch (day) {
        case 1 -> "Bazar ertəsi";        // Fall-through yoxdur!
        case 2 -> "Çərşənbə axşamı";
        case 3 -> "Çərşənbə";
        case 4 -> "Cümə axşamı";
        case 5 -> "Cümə";
        case 6, 7 -> "Həftəsonu";        // Vergüllə çoxlu case
        default -> "Yanlış gün";
    };
}

// ─── Enum ilə switch expression ──────────────────────────
enum Season { SPRING, SUMMER, AUTUMN, WINTER }

String describeSeasonOld(Season season) {
    switch (season) {
        case SPRING: return "İlkbahar — çiçəklər";
        case SUMMER: return "Yaz — isti";
        case AUTUMN: return "Payız — yarpaqlar";
        case WINTER: return "Qış — soyuq";
        default: throw new IllegalArgumentException();
    }
}

// Yeni — default lazım deyil! (Enum exhaustive)
String describeSeason(Season season) {
    return switch (season) {
        case SPRING -> "İlkbahar — çiçəklər";
        case SUMMER -> "Yaz — isti";
        case AUTUMN -> "Payız — yarpaqlar";
        case WINTER -> "Qış — soyuq";
        // default lazım deyil — bütün enum dəyərləri əhatə olunub
    };
}

// ─── Switch expression dəyişkənə mənimsəmə ───────────────
int numLetters = switch (season) {
    case SPRING, SUMMER, WINTER -> 6;
    case AUTUMN -> 6;
};

// ─── Switch expression metod çağırışında ─────────────────
System.out.println(switch (season) {
    case SPRING -> "🌸";
    case SUMMER -> "☀️";
    case AUTUMN -> "🍂";
    case WINTER -> "❄️";
});

// ─── String switch ────────────────────────────────────────
int httpStatus(String method) {
    return switch (method.toUpperCase()) {
        case "GET", "HEAD"          -> 200;
        case "POST", "PUT"          -> 201;
        case "DELETE"               -> 204;
        case "OPTIONS"              -> 200;
        default                     -> 400;
    };
}
```

---

## yield ifadəsi

```java
// ─── yield — block içindən dəyər qaytarır ────────────────
// Bəzən case içində mürəkkəb məntiqlə lazım olur

String classifyNumber(int n) {
    return switch (n) {
        case 0 -> "Sıfır";
        case 1, 2, 3, 5, 7 -> "Sadə ədəd";
        default -> {
            if (n < 0) {
                yield "Mənfi ədəd";
            } else if (n % 2 == 0) {
                yield "Cüt ədəd";
            } else {
                yield "Tək ədəd";
            }
        }
    };
}

// ─── yield vs return fərqi ────────────────────────────────
String example(int x) {
    return switch (x) {
        case 1 -> "Bir";          // Arrow: implicit yield
        default -> {
            String result = "Dəyər: " + x;
            System.out.println("Hesablandı: " + result);
            yield result;         // Block: explicit yield
            // return result;     // ❌ Yanlış! return deyil, yield!
        }
    };
}

// ─── yield ilə mürəkkəb hesablama ─────────────────────────
double calculateTax(String country, double income) {
    return switch (country) {
        case "AZ" -> income * 0.14;
        case "TR" -> income * 0.20;
        case "US" -> {
            // Mürəkkəb vergi hesablaması
            double baseTax = income * 0.22;
            double stateTax = income * 0.05;
            double totalTax = baseTax + stateTax;
            System.out.printf("US vergi: %.2f%n", totalTax);
            yield totalTax;
        }
        case "UK" -> {
            double personalAllowance = 12570;
            double taxableIncome = Math.max(0, income - personalAllowance);
            yield taxableIncome * 0.20;
        }
        default -> throw new IllegalArgumentException("Naməlum ölkə: " + country);
    };
}

// ─── switch expression void (statement olaraq) ───────────
// Switch expression-ı statement kimi də istifadə etmək mümkündür
void printSeason(Season season) {
    switch (season) {                // Dəyər qaytarılmır
        case SPRING -> System.out.println("İlkbahar");
        case SUMMER -> System.out.println("Yay");
        case AUTUMN -> System.out.println("Payız");
        case WINTER -> System.out.println("Qış");
    }
}
```

---

## Pattern Matching ilə Switch

```java
// ─── Java 21 — Type Pattern in Switch ────────────────────
// instanceof yoxlamalarını switch-ə daşıdı

// Köhnə yanaşma:
String describeOld(Object obj) {
    if (obj instanceof Integer i) {
        return "Tam ədəd: " + i;
    } else if (obj instanceof String s) {
        return "Mətn: " + s.toUpperCase();
    } else if (obj instanceof Double d) {
        return "Onluq: " + d;
    } else if (obj == null) {
        return "Null dəyər";
    } else {
        return "Naməlum tip: " + obj.getClass().getSimpleName();
    }
}

// Yeni — switch pattern matching:
String describe(Object obj) {
    return switch (obj) {
        case Integer i   -> "Tam ədəd: " + i;
        case String s    -> "Mətn: " + s.toUpperCase();
        case Double d    -> "Onluq: %.2f".formatted(d);
        case int[] arr   -> "Array: uzunluq=" + arr.length;
        case null        -> "Null dəyər";          // explicit null case!
        default          -> "Naməlum: " + obj.getClass().getSimpleName();
    };
}

// ─── Sealed class ilə switch ──────────────────────────────
sealed interface Shape
    permits Circle, Rectangle, Triangle {}

record Circle(double radius) implements Shape {}
record Rectangle(double width, double height) implements Shape {}
record Triangle(double base, double height) implements Shape {}

double calculateArea(Shape shape) {
    return switch (shape) {
        case Circle c       -> Math.PI * c.radius() * c.radius();
        case Rectangle r    -> r.width() * r.height();
        case Triangle t     -> 0.5 * t.base() * t.height();
        // default lazım deyil! — Sealed class exhaustive
    };
}

// ─── Record Pattern — destructuring ──────────────────────
// Java 21 — Record-un field-lərini birbaşa pattern-də aç

record Point(int x, int y) {}
record Line(Point start, Point end) {}

String describePoint(Object obj) {
    return switch (obj) {
        case Point(int x, int y)
            when x == 0 && y == 0   -> "Orijin nöqtəsi";
        case Point(int x, int y)
            when x == 0             -> "Y oxundakı nöqtə: y=" + y;
        case Point(int x, int y)
            when y == 0             -> "X oxundakı nöqtə: x=" + x;
        case Point(int x, int y)    -> "Nöqtə (%d, %d)".formatted(x, y);
        case Line(Point s, Point e) -> "Xətt: %s → %s".formatted(s, e);
        default                     -> "Naməlum forma";
    };
}

// ─── Domain event handling ────────────────────────────────
sealed interface DomainEvent
    permits OrderCreated, OrderCancelled, PaymentProcessed {}

record OrderCreated(String orderId, String customerId, BigDecimal amount)
    implements DomainEvent {}
record OrderCancelled(String orderId, String reason)
    implements DomainEvent {}
record PaymentProcessed(String orderId, String transactionId, boolean success)
    implements DomainEvent {}

@Service
public class EventHandler {

    public void handle(DomainEvent event) {
        switch (event) {
            case OrderCreated(var id, var cId, var amount)
                -> log.info("Sifariş yaradıldı: {} müştəri={} məbləğ={}", id, cId, amount);

            case OrderCancelled(var id, var reason)
                -> log.warn("Sifariş ləğv edildi: {} səbəb={}", id, reason);

            case PaymentProcessed(var id, var txId, true)
                -> log.info("Ödəniş uğurlu: {} tx={}", id, txId);

            case PaymentProcessed(var id, var txId, false)
                -> log.error("Ödəniş uğursuz: {} tx={}", id, txId);
        }
        // default yoxdur — sealed, exhaustive!
    }
}
```

---

## Guarded Patterns

```java
// ─── when — guard condition ────────────────────────────────
// Pattern + əlavə şərt

String classifyTemperature(Object temp) {
    return switch (temp) {
        case Integer i when i < 0    -> "Dondurucu: " + i + "°C";
        case Integer i when i < 15   -> "Soyuq: " + i + "°C";
        case Integer i when i < 25   -> "Mülayim: " + i + "°C";
        case Integer i when i < 35   -> "İsti: " + i + "°C";
        case Integer i               -> "Çox isti: " + i + "°C";
        case Double d when d < 0.0   -> "Dondurucu: " + d + "°C";
        case Double d                -> "Onluq: " + d + "°C";
        case null                    -> "Temperatur bilinmir";
        default                      -> "Yanlış tip: " + temp.getClass().getSimpleName();
    };
}

// ─── Guarded pattern real nümunə ─────────────────────────
sealed interface ApiResponse<T>
    permits ApiResponse.Success, ApiResponse.Error {}

record Success<T>(T data, int statusCode) implements ApiResponse<T> {}
record Error(String message, int statusCode) implements ApiResponse<Object> {}

String handleResponse(ApiResponse<?> response) {
    return switch (response) {
        case Success<?>(var data, int code) when code == 200
            -> "OK: " + data;
        case Success<?>(var data, int code) when code == 201
            -> "Yaradıldı: " + data;
        case Success<?>(var data, int code)
            -> "Uğurlu (%d): %s".formatted(code, data);
        case Error(var msg, int code) when code >= 500
            -> "Server xətası (%d): %s".formatted(code, msg);
        case Error(var msg, int code) when code >= 400
            -> "Client xətası (%d): %s".formatted(code, msg);
        case Error(var msg, int code)
            -> "Xəta (%d): %s".formatted(code, msg);
    };
}

// ─── Dominance — case sırası vacibdir! ───────────────────
// Daha spesifik case əvvəl gəlməlidir

Object obj = 42;

// ❌ Yanlış — Integer i hər Integer-i tutur, Integer i when i > 0 heç vaxt çatmır
switch (obj) {
    case Integer i             -> System.out.println("Integer");
    case Integer i when i > 0  -> System.out.println("Müsbət"); // UNREACHABLE!
    default                    -> System.out.println("Digər");
}

// ✅ Doğru — Spesifik əvvəl
switch (obj) {
    case Integer i when i < 0  -> System.out.println("Mənfi");
    case Integer i when i == 0 -> System.out.println("Sıfır");
    case Integer i             -> System.out.println("Müsbət");  // fallback
    default                    -> System.out.println("Digər");
}
```

---

## Exhaustiveness — tam əhatə

```java
// ─── Compiler exhaustiveness yoxlaması ───────────────────

// Enum — bütün dəyərləri əhatə etməliyik (ya da default)
enum Day { MON, TUE, WED, THU, FRI, SAT, SUN }

// ❌ Compile error: "switch" expression does not cover all possible input values
// int hours = switch (day) {
//     case MON, TUE, WED, THU, FRI -> 8;
//     // SAT, SUN əhatə olunmayıb!
// };

// ✅ Tam əhatə
int workHours = switch (day) {
    case MON, TUE, WED, THU, FRI -> 8;
    case SAT, SUN                 -> 0;
};

// ─── Sealed interface — automatic exhaustiveness ──────────
sealed interface Notification
    permits EmailNotification, SmsNotification, PushNotification {}

// ✅ Bütün permitted types əhatə olunub → default lazım deyil
String sendNotification(Notification n) {
    return switch (n) {
        case EmailNotification e -> "Email göndərildi: " + e.to();
        case SmsNotification s   -> "SMS göndərildi: " + s.phone();
        case PushNotification p  -> "Push göndərildi: " + p.deviceId();
        // Yeni Notification növü əlavə etsək → compile error!
    };
}

// ─── Null handling ────────────────────────────────────────
// Köhnə switch: null gəlsə NullPointerException
// Yeni switch: null case ilə idarə etmək mümkün

String safeDescribe(Object obj) {
    return switch (obj) {
        case null    -> "Null";           // Explicit null
        case Integer i -> "Tam: " + i;
        case String s  -> "Mətn: " + s;
        default        -> obj.toString();
    };
}

// ─── Praktiki nümunə — HTTP request routing ──────────────
record HttpRequest(String method, String path, Object body) {}

ResponseEntity<?> route(HttpRequest req) {
    return switch (req) {
        case HttpRequest(String m, String p, var b)
            when m.equals("GET") && p.startsWith("/api/orders")
            -> ResponseEntity.ok(orderService.getAll());

        case HttpRequest(String m, String p, var b)
            when m.equals("POST") && p.equals("/api/orders")
            -> ResponseEntity.status(201).body(orderService.create(b));

        case HttpRequest(String m, String p, var b)
            when m.equals("DELETE")
            -> ResponseEntity.noContent().build();

        case HttpRequest(String m, String p, var b)
            -> ResponseEntity.status(405).body("Method not allowed: " + m);
    };
}
```

---

## İntervyu Sualları

### 1. Switch expression ilə switch statement fərqi nədir?
**Cavab:** **Statement** — dəyər qaytarmır, `break` tələb edir, fall-through riski var. **Expression** — dəyər qaytarır, dəyişkənə mənimsədilir, `->` sintaksisi ilə fall-through yoxdur, `yield` block içindən dəyər qaytarır. Switch expression həm `->` (arrow), həm `:` (colon, köhnə stil) ilə yazılır, amma arrow fall-through olmadığı üçün tövsiyə edilir. Java 14-dən standard feature.

### 2. yield nə üçün lazımdır, return ilə fərqi?
**Cavab:** `yield` yalnız switch expression block-u içindən dəyər qaytarmaq üçün istifadə edilir. `return` metoddan çıxır, `yield` yalnız switch block-undan çıxır. Arrow case (`->`) implicit yield edir. Block case (`-> { ... }`) explicit `yield` tələb edir. `return` istifadə edilsə compile error olur.

### 3. Pattern Matching switch Java 21-də nə verir?
**Cavab:** `instanceof` zənciri əvəzinə type pattern switch: `case Integer i ->`, `case String s ->`. Sealed class ilə birlikdə: exhaustiveness — compiler bütün permitted tiplərin əhatə olunduğunu yoxlayır, `default` lazım deyil. Record pattern: `case Point(int x, int y) ->` — destructuring. Guarded pattern: `case Integer i when i > 0 ->` — pattern + şərt. `null` case: explicit handling, NPE yoxdur.

### 4. Sealed class ilə switch niyə vacibdir?
**Cavab:** Sealed class — `permits` ilə bütün alt tiplər compiler-a məlumdur. Switch bu bilgidən yararlanır: bütün permitted tiplər case olaraq yazılırsa `default` tələb etmir. Yeni tip əlavə edilsə — bütün switch expression-lar compile error verir → "değişiklik yerləri" asanlıqla tapılır. Bu, OOP-da "exhaustive dispatch" pattern-idir: union type kimi işləyir. `if-instanceof` zəncirindən daha güvənli və oxunaqlı.

### 5. Guarded pattern (`when`) nə üçün istifadə edilir?
**Cavab:** `case Integer i when i > 0 ->` — tip yoxlaması + əlavə şərt bir yerdə. `when` olmadan bu iki ayrı if lazım idi. Sıra vacibdir: daha spesifik case (with guard) ümumi case-dən əvvəl gəlməlidir, əks halda unreachable code — compile warning. Guarded pattern domain logic-i switch-ə gətirir: status kodu 200, 201, 4xx, 5xx kimi qruplar bir switch-də idarə edilir.

*Son yenilənmə: 2026-04-10*
