# Switch Expressions və Record Patterns (Java Pattern Matching vs PHP match)

## Giriş

Uzun müddət `switch` həm Java-da, həm PHP-də köhnə, təhlükəli statement idi: fallthrough, `break` unutmaq, qayıtma dəyəri olmaması. Hər iki dil bu problemi həll etməyə başladı — amma müxtəlif yollarla.

**Java** tədrici inkişaf etdi:
- **Java 14** — Switch Expressions (arrow syntax, `yield`, fallthrough yoxdur)
- **Java 16** — Pattern matching for `instanceof`
- **Java 21** — Pattern matching for `switch` (type patterns, guarded patterns, null handling)
- **Java 21** — Record patterns (deconstruction, nested patterns)
- **Java 22+** — Unnamed patterns `_`

**PHP** sadə bir addım atdı:
- **PHP 8.0** — `match` expression (strict comparison, no fallthrough, returns value)

PHP-də obyekt destructuring yoxdur — yalnız array-lər üçün `[$a, $b] = $arr`. Bu Java-nın record patterns-indən çox fərqlidir.

---

## Java-da istifadəsi

### 1) Klassik switch (köhnə, təhlükəli)

```java
String day;
switch (dayOfWeek) {
    case 1:
        day = "Bazar ertəsi";
        break;                              // break unutsan — fallthrough
    case 2:
        day = "Çərşənbə axşamı";
        break;
    default:
        day = "Naməlum";
}
```

Problem: `break` unutmaq asandır, fallthrough səhv. Qayıtma dəyəri yoxdur — `day` dəyişəni xaricdə yazılıb daxildə doldurulur.

### 2) Switch expression (Java 14 stable)

```java
String day = switch (dayOfWeek) {
    case 1 -> "Bazar ertəsi";
    case 2 -> "Çərşənbə axşamı";
    case 3 -> "Çərşənbə";
    case 4, 5 -> "İstirahət yaxın";         // bir neçə dəyər
    default -> "Naməlum";
};

// `yield` — blok yaratmaq lazım olanda
int code = switch (dayOfWeek) {
    case 1, 7 -> 100;
    case 2, 3, 4, 5, 6 -> {
        log.debug("İş günü: " + dayOfWeek);
        yield 200;                          // bloklu case-də return əvəzi
    }
    default -> throw new IllegalStateException();
};
```

Fərqlər:
- Arrow syntax `->` — fallthrough yoxdur
- Qayıtma dəyəri var
- Bloklu case üçün `yield` açar sözü
- Exhaustiveness yoxlaması (enum və sealed üçün)

### 3) Pattern matching for `instanceof` (Java 16)

```java
// Köhnə yol
if (obj instanceof String) {
    String s = (String) obj;                // manual cast
    System.out.println(s.length());
}

// Yeni yol
if (obj instanceof String s) {              // avtomatik bind
    System.out.println(s.length());
}

// Condition-da işləyir
if (obj instanceof String s && s.length() > 5) {
    System.out.println("Uzun string: " + s);
}

// Negation
if (!(obj instanceof String s)) {
    return;
}
System.out.println(s.length());             // s-dən istifadə edə bilərik — scope genişlənir
```

### 4) Pattern matching for switch (Java 21 stable)

```java
Object obj = getSomething();

String result = switch (obj) {
    case Integer i    -> "Tam ədəd: " + i;
    case Long l       -> "Uzun tam: " + l;
    case Double d     -> "Ondalık: " + d;
    case String s     -> "Mətn: " + s.toUpperCase();
    case int[] arr    -> "Massiv, uzunluğu: " + arr.length;
    case null         -> "null dəyər";          // null-u da case kimi işlət
    default           -> "Bilinməyən tip";
};
```

Java 21-dən əvvəl `switch` yalnız primitiv tiplər, String və enum üçün işləyirdi. İndi istənilən tip üçün pattern matching var.

### 5) Guarded patterns — `when` açar sözü

```java
String describe = switch (obj) {
    case Integer i when i < 0      -> "Mənfi tam ədəd";
    case Integer i when i == 0     -> "Sıfır";
    case Integer i when i > 100    -> "Böyük tam ədəd: " + i;
    case Integer i                 -> "Normal tam ədəd: " + i;
    case String s when s.isEmpty() -> "Boş string";
    case String s                  -> "String: " + s;
    default                        -> "Digər";
};
```

`when` keyword guard condition-u əlavə edir. Order vacibdir — yuxarıdan aşağıya yoxlanılır.

### 6) Record patterns (Java 21) — deconstruction

```java
record Point(int x, int y) {}
record Line(Point start, Point end) {}
record Circle(Point center, double radius) {}

Object shape = getShape();

String info = switch (shape) {
    case Point(int x, int y) ->
        "Nöqtə (" + x + ", " + y + ")";

    case Line(Point(var x1, var y1), Point(var x2, var y2)) ->
        "Xətt (" + x1 + "," + y1 + ") → (" + x2 + "," + y2 + ")";

    case Circle(Point(var cx, var cy), double r) ->
        "Dairə, mərkəz (" + cx + "," + cy + "), radius " + r;

    default -> "Bilinməyən forma";
};
```

Nested deconstruction — `Line` içindəki `Point`-ı da deconstruct edə bilərik.

### 7) Unnamed patterns `_` (Java 22 preview, 24 stable)

```java
record Pair<A, B>(A first, B second) {}

// Bəzən bir dəyəri istəmirik
Pair<String, Integer> p = new Pair<>("salam", 42);

if (p instanceof Pair(String s, _)) {       // second lazım deyil
    System.out.println("Birinci: " + s);
}

// Switch-də
String result = switch (p) {
    case Pair(String s, _) when s.length() > 3 -> "Uzun birinci: " + s;
    case Pair(_, Integer n) when n > 0         -> "Müsbət ikinci: " + n;
    default                                    -> "Başqa";
};
```

### 8) Null handling switch-də

```java
// Java 21-dən əvvəl null `NullPointerException` atırdı
String result = switch (obj) {
    case null            -> "null idi";
    case Integer i       -> "int: " + i;
    case String s        -> "string: " + s;
    default              -> "digər";
};

// null + digər birləşmə
String result2 = switch (obj) {
    case null, "default" -> "null və ya default";
    case String s        -> "digər string: " + s;
    default              -> "digər";
};
```

### 9) Real dünya: AST walking

```java
public sealed interface Expr {
    record Num(int value) implements Expr {}
    record Add(Expr left, Expr right) implements Expr {}
    record Mul(Expr left, Expr right) implements Expr {}
    record Neg(Expr inner) implements Expr {}
}

public static int evaluate(Expr expr) {
    return switch (expr) {
        case Expr.Num(int v)          -> v;
        case Expr.Add(Expr l, Expr r) -> evaluate(l) + evaluate(r);
        case Expr.Mul(Expr l, Expr r) -> evaluate(l) * evaluate(r);
        case Expr.Neg(Expr inner)     -> -evaluate(inner);
    };
}

// (1 + 2) * (3 + (-4))
Expr ast = new Expr.Mul(
    new Expr.Add(new Expr.Num(1), new Expr.Num(2)),
    new Expr.Add(new Expr.Num(3), new Expr.Neg(new Expr.Num(4)))
);

System.out.println(evaluate(ast));          // 3 * -1 = -3
```

Bu Java-da funksional-stil interpreter. Sealed + record + pattern matching birləşməsi.

### 10) State machine Pattern Matching ilə

```java
public sealed interface Order {
    record Pending(Instant createdAt) implements Order {}
    record Paid(Instant paidAt, String txId) implements Order {}
    record Shipped(Instant shippedAt, String trackingCode) implements Order {}
    record Cancelled(Instant cancelledAt, String reason) implements Order {}
}

public String render(Order order) {
    return switch (order) {
        case Order.Pending(Instant t) when Duration.between(t, Instant.now()).toHours() > 24
            -> "Ödəniş gecikib — xatırlat";
        case Order.Pending(Instant t)
            -> "Ödəniş gözlənilir (yaradılıb: " + t + ")";
        case Order.Paid(Instant paid, String tx)
            -> "Ödənildi. Transaction: " + tx;
        case Order.Shipped(Instant shipped, String track)
            -> "Göndərildi. Tracking: " + track;
        case Order.Cancelled(Instant cancelled, String reason)
            -> "Ləğv edildi. Səbəb: " + reason;
    };
}
```

### 11) Spring Boot: Controller-də pattern matching

```java
@RestController
public class OrderController {

    private final OrderService service;

    @GetMapping("/orders/{id}")
    public ResponseEntity<?> getOrder(@PathVariable Long id) {
        Result<Order, OrderError> result = service.findById(id);

        return switch (result) {
            case Result.Ok<Order, OrderError>(Order order)
                -> ResponseEntity.ok(order);
            case Result.Err<Order, OrderError>(OrderError.NotFound nf)
                -> ResponseEntity.notFound().build();
            case Result.Err<Order, OrderError>(OrderError.Forbidden f)
                -> ResponseEntity.status(HttpStatus.FORBIDDEN).build();
            case Result.Err<Order, OrderError>(OrderError.InternalError ie)
                -> ResponseEntity.internalServerError().body(ie.message());
        };
    }
}
```

---

## PHP-də istifadəsi

### 1) Klassik switch (köhnə, təhlükəli)

```php
<?php
switch ($dayOfWeek) {
    case 1:
        $day = 'Bazar ertəsi';
        break;                                // break unutsan — fallthrough
    case 2:
        $day = 'Çərşənbə axşamı';
        break;
    default:
        $day = 'Naməlum';
}
```

Java-dakı problem eynisi. PHP-də `switch` `==` (loose) müqayisə edir — `"1" == 1` `true`-dur. Bu səhv yaradır.

### 2) `match` expression (PHP 8.0)

```php
<?php
$day = match ($dayOfWeek) {
    1       => 'Bazar ertəsi',
    2       => 'Çərşənbə axşamı',
    3       => 'Çərşənbə',
    4, 5    => 'İstirahət yaxın',            // bir neçə dəyər
    default => 'Naməlum',
};
```

Fərqlər:
- Strict comparison (`===`) — `"1"` match `1` ETMIR
- Qayıtma dəyəri var
- Fallthrough yoxdur
- Uyğunluq tapılmasa, `UnhandledMatchError` atır
- Exhaustiveness yoxdur dil səviyyəsində (PHPStan var)

### 3) Match ilə murəkkəb ifadələr

```php
<?php
$category = match (true) {
    $age < 0              => throw new InvalidArgumentException('Mənfi yaş'),
    $age < 13             => 'uşaq',
    $age < 18             => 'yeniyetmə',
    $age < 65             => 'yaşlı',
    default               => 'qoca',
};
```

`match(true)` PHP-də guard pattern-in ekvivalentidir. Hər `case` boolean ifadədir.

### 4) Type-based match (instanceof ilə)

```php
<?php
function describe(object $obj): string
{
    return match (true) {
        $obj instanceof Circle   => "Dairə, radius: {$obj->radius}",
        $obj instanceof Square   => "Kvadrat, yan: {$obj->side}",
        $obj instanceof Triangle => "Üçbucaq",
        default                  => 'Bilinməyən',
    };
}
```

Java-dakı `case Circle c ->` ekvivalenti yoxdur. `instanceof` + property access manual edilir.

### 5) Enum ilə match

```php
<?php
enum OrderStatus: string
{
    case PENDING   = 'pending';
    case PAID      = 'paid';
    case SHIPPED   = 'shipped';
    case DELIVERED = 'delivered';
}

$label = match($status) {
    OrderStatus::PENDING   => 'Gözləyir',
    OrderStatus::PAID      => 'Ödənib',
    OrderStatus::SHIPPED   => 'Göndərilib',
    OrderStatus::DELIVERED => 'Çatdırılıb',
    // default qoyma — compiler/PHPStan exhaustiveness yoxlasın
};
```

PHP 8.1 enum + match — exhaustiveness üçün ən yaxşı variant. PHPStan bütün enum case-ləri yoxlandığını yoxlayır.

### 6) Array destructuring — PHP-də olan yeganə deconstruction

```php
<?php
$user = ['name' => 'Orkhan', 'age' => 30, 'email' => 'a@b.com'];

['name' => $name, 'age' => $age] = $user;
echo $name;                                  // Orkhan

// Nested
$order = ['id' => 1, 'items' => [['name' => 'book', 'price' => 20]]];
['items' => [['name' => $firstItem]]] = $order;
echo $firstItem;                             // book

// Swap
[$a, $b] = [1, 2];
[$a, $b] = [$b, $a];                         // $a=2, $b=1
```

### 7) Obyekt destructuring yoxdur — workaround

```php
<?php
class Point
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
    ) {}

    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }
}

$p = new Point(10, 20);

// Java-dakı `case Point(int x, int y)` ekvivalenti yoxdur
// Workaround 1: property birbaşa oxu
echo $p->x;

// Workaround 2: array-a çevir, destructure et
['x' => $x, 'y' => $y] = $p->toArray();

// Workaround 3: readonly class + property
echo "({$p->x}, {$p->y})";
```

### 8) Ağaç gəzintisi (AST) — PHP

```php
<?php
interface Expr {}

final readonly class NumExpr implements Expr
{
    public function __construct(public int $value) {}
}

final readonly class AddExpr implements Expr
{
    public function __construct(public Expr $left, public Expr $right) {}
}

final readonly class MulExpr implements Expr
{
    public function __construct(public Expr $left, public Expr $right) {}
}

final readonly class NegExpr implements Expr
{
    public function __construct(public Expr $inner) {}
}

function evaluate(Expr $expr): int
{
    return match (true) {
        $expr instanceof NumExpr => $expr->value,
        $expr instanceof AddExpr => evaluate($expr->left) + evaluate($expr->right),
        $expr instanceof MulExpr => evaluate($expr->left) * evaluate($expr->right),
        $expr instanceof NegExpr => -evaluate($expr->inner),
    };
}
```

Java versiyası ilə müqayisə et: PHP-də hər ifadədə `$expr->` yazmaq lazımdır, destructuring yoxdur.

### 9) Laravel Controller — match istifadəsi

```php
<?php
class OrderController extends Controller
{
    public function show(int $id, OrderService $service): JsonResponse
    {
        $result = $service->findById($id);

        return match (true) {
            $result instanceof Ok
                => response()->json($result->value),
            $result instanceof Err && $result->error === 'not_found'
                => response()->json(['error' => 'Tapılmadı'], 404),
            $result instanceof Err && $result->error === 'forbidden'
                => response()->json(['error' => 'İcazə yoxdur'], 403),
            $result instanceof Err
                => response()->json(['error' => $result->error], 500),
        };
    }
}
```

### 10) State machine — PHP

```php
<?php
enum OrderEvent: string
{
    case PAY     = 'pay';
    case SHIP    = 'ship';
    case DELIVER = 'deliver';
    case CANCEL  = 'cancel';
}

function transition(OrderStatus $current, OrderEvent $event): OrderStatus
{
    return match ([$current, $event]) {
        [OrderStatus::PENDING, OrderEvent::PAY]    => OrderStatus::PAID,
        [OrderStatus::PAID, OrderEvent::SHIP]      => OrderStatus::SHIPPED,
        [OrderStatus::SHIPPED, OrderEvent::DELIVER] => OrderStatus::DELIVERED,
        [OrderStatus::PENDING, OrderEvent::CANCEL],
        [OrderStatus::PAID, OrderEvent::CANCEL]    => OrderStatus::CANCELLED,
        default => throw new InvalidStateException(
            "İcazəsiz keçid: {$current->value} -> {$event->value}"
        ),
    };
}
```

Match içində array pattern PHP-də işləyir, amma `[OrderStatus::PAID, OrderEvent::SHIP]` `===` müqayisəsi aparır — referans bərabərliyi yox, dəyər bərabərliyi.

### 11) Symfony Messenger handler

```php
<?php
#[AsMessageHandler]
class OrderEventHandler
{
    public function __invoke(OrderEvent $event): void
    {
        match (true) {
            $event instanceof OrderPlaced     => $this->handlePlaced($event),
            $event instanceof OrderPaid       => $this->handlePaid($event),
            $event instanceof OrderShipped    => $this->handleShipped($event),
            $event instanceof OrderCancelled  => $this->handleCancelled($event),
        };
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Java Switch/Pattern | PHP `match` |
|---|---|---|
| Versiya | 14/16/21 | 8.0 |
| Müqayisə | Pattern matching | Strict `===` |
| Fallthrough | Yox | Yox |
| Qayıtma dəyəri | Bəli | Bəli |
| Type pattern | `case Integer i` | `instanceof` + match(true) |
| Guarded pattern | `when` | match(true) + şərt |
| Deconstruction | Record patterns (21) | Yalnız array |
| Nested patterns | Record + record | Yalnız nested array |
| null handling | `case null` | `null => ...` (strict) |
| Unnamed pattern | `_` (Java 22+) | Yox |
| Exhaustiveness | Compile-time | Runtime `UnhandledMatchError` + PHPStan |
| Fail-fast | Default lazımdırsa | `UnhandledMatchError` |

---

## Niyə belə fərqlər var?

**Java** dili Amber layihəsi çərçivəsində tədricən funksional proqramlaşdırma xüsusiyyətlərini əlavə edir. Məqsəd — switch-i təhlükəsiz, deklarativ, tipli etmək. Pattern matching tarixi olaraq ML, Haskell, Scala, Kotlin-də var idi. Java bu feature-ları gec əlavə etdi, amma yaxşı dizaynla: hər addım stable olmadan əvvəl preview mərhələ keçir. Record patterns, sealed + switch, unnamed patterns — bunlar hər biri ayrıca JEP-lərlə gəldi.

**PHP** bir addımda `match` əlavə etdi və bununla kifayətləndi. PHP felsəfəsi minimalist yanaşmadır — `match(true)` + `instanceof` kifayət qədər güclüdür. Obyekt destructuring yoxdur, çünki PHP-də property-lər adətən public-dir — onsuz da `$obj->x` yazılır. Deep pattern matching üçün PHP developer-ləri qərar hierarchy-ni `instanceof` zənciri ilə qurur, və ya Visitor pattern-i istifadə edir.

**Nəticə:** Java pattern matching daha güclü və daha tipli; PHP match daha sadə amma daha az ifadəli. Böyük domain model-lərdə Java yanaşması aşkar üstünlük verir. Kiçik miqyasda PHP `match` kifayətdir.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- Type patterns switch-də (`case String s ->`)
- Record patterns (`case Point(int x, int y)`)
- Nested record patterns
- Guarded patterns (`when` açar sözü)
- Unnamed patterns (`_`)
- Pattern variable binding
- Compile-time exhaustiveness yoxlaması
- `yield` açar sözü bloklu case-lərdə

**Yalnız PHP-də:**
- `match(true)` ilə boolean chain — kompakt
- Array destructuring (`[$a, $b] = $arr`)
- String key ilə nested array destructuring
- Runtime `UnhandledMatchError` (fail-fast default davranış)

**Hər ikisində var:**
- Arrow syntax (`->` Java, `=>` PHP)
- Qayıtma dəyəri var
- Fallthrough yoxdur
- Multiple values one case (`case 1, 2 ->` / `1, 2 =>`)
- Exception-dan istifadə match/switch daxilində

---

## Best Practices

1. **Java: `switch` expression istifadə et, statement-dən qaç.** Return dəyəri təmizdir.
2. **Java: Sealed + record + switch = ADT** — domain model üçün ən güclü kombinasiya.
3. **Java: `default`-u çıxar** sealed type üçün — exhaustiveness işləsin.
4. **Java: Record patterns-lə deconstruct et** — `case Point(int x, int y)`.
5. **Java: `when` guard istifadə et** — complex condition-lar üçün.
6. **PHP: `match` istifadə et, `switch`-dən qaç** — yeni kod üçün default seçim.
7. **PHP: Enum + match kombinasiyası** — exhaustiveness üçün ən güclü variant.
8. **PHP: `match(true)` + `instanceof`** — type-based dispatch üçün.
9. **PHP: `default` qoyma enum match-da** — PHPStan exhaustiveness yoxlasın.
10. **Hər iki dildə: match/switch içində throw istifadə et** — impossible case-lər üçün.
11. **Java: Pattern matching state machine-lər üçün ideal** — if-else hierarchy-si yerinə.
12. **PHP: Array destructuring-i açıqla** — obyekt-lər üçün `toArray()` method yaz.

---

## Yekun

- **Switch expressions** (Java 14) — arrow syntax, `yield`, fallthrough yoxdur, qayıtma dəyəri var.
- **Pattern matching for instanceof** (Java 16) — manual cast-a son qoydu.
- **Pattern matching for switch** (Java 21) — type patterns, guarded patterns, null handling.
- **Record patterns** (Java 21) — deconstruction, nested patterns, ən güclü feature.
- **Unnamed patterns `_`** (Java 22+) — istifadə edilməyən dəyərlər üçün.
- **PHP `match`** (8.0) — strict comparison, no fallthrough, qayıtma dəyəri var.
- **PHP-də obyekt destructuring yoxdur** — yalnız array, workaround-lar istifadə olunur.
- **Exhaustiveness:** Java compile-time, PHP runtime + PHPStan.
- **`match(true)` + `instanceof`** — PHP-də type dispatch üçün standart üsul.
- **Sealed + Record + Pattern Matching** — Java-da funksional-stil domain model mümkündür.
- **AST walking, state machine, Result type** — hər iki dildə işləyir, Java-da daha təmiz.
- **Laravel/Symfony + match** — modern PHP kodda standart pattern.
