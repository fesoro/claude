# Functional Interfaces və Method Reference (Java vs PHP)

## Giriş

Funksional proqramlaşdırma fərqli bir "paradigma" deyil, daha çox bir stildir: **funksiya birinci sinif obyektdir** — dəyişənə yaz, arqument kimi ver, nəticə kimi qaytar. Bu stil modern Java və PHP-də geniş yayılıb.

**Java** 1.0-dan OOP dili olaraq başladı — funksiya "obyekt" deyildi. Java 8 `@FunctionalInterface` və lambda gətirdi — indi `Function<T, R>`, `Predicate<T>`, `Consumer<T>` kimi generic interface-lər standart aləvhə gəldi. **Method reference** (`String::length`) isə lambda-nı daha qısa etdi.

**PHP**-də funksiyalar daima "first-class citizen" deyildi. Amma PHP 8.1-də **first-class callable syntax** (`strlen(...)`) gəldi — bu Java-nın method reference-inə oxşardı. **Closure** da güclü bir tipdir — `bind`, `call`, `fromCallable` kimi metodlarla context dəyişdirmək olar.

Bu fayl həm iki dilin funksional xüsusiyyətlərini dərindən müqayisə edir.

---

## Java-da istifadəsi

### 1) `@FunctionalInterface` annotation

```java
@FunctionalInterface
public interface Calculator {
    int apply(int a, int b);
    // Yalnız bir abstract metod — SAM (Single Abstract Method)
    // default və static metodlara icazə var
}

Calculator add = (a, b) -> a + b;
Calculator mul = (a, b) -> a * b;

int result = add.apply(2, 3);    // 5
```

`@FunctionalInterface` annotation məcburi deyil, amma compiler check verir: "bu interface funksional qalmalıdır" — ikinci abstract metod əlavə etsən, compile error.

### 2) Standart funksional interfacelər

Java `java.util.function` paketi əsas tiplər verir:

```java
import java.util.function.*;

// Function<T, R> — T alır, R qaytarır
Function<String, Integer> length = s -> s.length();
Integer n = length.apply("salam");            // 5

// BiFunction<T, U, R> — iki giriş
BiFunction<Integer, Integer, Integer> sum = (a, b) -> a + b;
Integer s = sum.apply(2, 3);                  // 5

// Predicate<T> — boolean qaytarır (filter üçün)
Predicate<String> isEmpty = String::isEmpty;
boolean b = isEmpty.test("");                 // true

// Consumer<T> — input alır, void
Consumer<String> printer = System.out::println;
printer.accept("salam");

// Supplier<T> — giriş yoxdur, T qaytarır
Supplier<LocalDateTime> now = LocalDateTime::now;
LocalDateTime t = now.get();

// UnaryOperator<T> — Function<T, T> xüsusi halı
UnaryOperator<String> upper = String::toUpperCase;
String S = upper.apply("salam");              // "SALAM"

// BinaryOperator<T> — BiFunction<T, T, T>
BinaryOperator<Integer> max = Math::max;
Integer m = max.apply(3, 5);                  // 5
```

### 3) Primitive specializations — boxing qaçın

```java
// Boxing qaçmaq üçün primitive varyant:
IntFunction<String> intToStr = i -> "n=" + i;
ToIntFunction<String> strToInt = String::length;
IntPredicate isPositive = i -> i > 0;
IntUnaryOperator square = i -> i * i;
IntBinaryOperator plus = Integer::sum;

// Performans fərqi böyük ola bilər:
IntStream.range(0, 1_000_000)
    .map(n -> n * n)              // primitive — sürətli
    .sum();

// vs
Stream.iterate(0, n -> n + 1).limit(1_000_000)
    .map(n -> n * n)              // Integer boxing — yavaş
    .mapToInt(Integer::intValue)
    .sum();
```

Həmçinin `LongFunction`, `DoubleFunction`, `ToLongFunction`, `ToDoubleFunction` var — long və double üçün.

### 4) Method Reference — 4 növ

```java
// 1) Static method reference
Function<String, Integer> parse = Integer::parseInt;
// eşit: s -> Integer.parseInt(s)

// 2) Instance method of a particular object
String prefix = "user_";
Function<String, String> addPrefix = prefix::concat;
// eşit: s -> prefix.concat(s)

// 3) Instance method of an arbitrary object of particular type
Function<String, Integer> len = String::length;
// eşit: s -> s.length()

BiFunction<String, String, Boolean> startsWith = String::startsWith;
// eşit: (s, prefix) -> s.startsWith(prefix)

// 4) Constructor reference
Supplier<ArrayList<String>> newList = ArrayList::new;
Function<Integer, ArrayList<String>> newListWithCap = ArrayList::new;

record User(String name) {}
Function<String, User> userFactory = User::new;
```

### 5) `andThen`, `compose` — function composition

```java
Function<Integer, Integer> plus2 = x -> x + 2;
Function<Integer, Integer> times3 = x -> x * 3;

// andThen — sıra ilə: plus2 → times3
Function<Integer, Integer> combined1 = plus2.andThen(times3);
combined1.apply(5);             // (5+2)*3 = 21

// compose — əks sıra: times3 → plus2
Function<Integer, Integer> combined2 = plus2.compose(times3);
combined2.apply(5);             // (5*3)+2 = 17

// Real istifadə — pipeline
Function<Request, User> parse = req -> parseUser(req);
Function<User, User> validate = u -> validateUser(u);
Function<User, User> save = u -> userRepo.save(u);

Function<Request, User> pipeline = parse.andThen(validate).andThen(save);
User result = pipeline.apply(request);
```

### 6) `Predicate.and`, `or`, `negate`

```java
Predicate<User> isActive = User::isActive;
Predicate<User> isAdmin = u -> u.getRole() == Role.ADMIN;
Predicate<User> hasEmail = u -> u.getEmail() != null;

// Composition
Predicate<User> activeAdmin = isActive.and(isAdmin);
Predicate<User> activeOrAdmin = isActive.or(isAdmin);
Predicate<User> notAdmin = isAdmin.negate();

List<User> targets = users.stream()
    .filter(isActive.and(hasEmail).and(isAdmin.negate()))
    .toList();

// Static metodlar
Predicate<String> nonEmpty = Predicate.not(String::isEmpty);
Predicate<Integer> isZero = Predicate.isEqual(0);
```

### 7) Checked exception problemi

Lambda-da checked exception yazıla bilmir:

```java
// XƏTA
Function<String, String> read = path -> Files.readString(Paths.get(path));
// IOException checked-dir — compile error

// Həll 1: try/catch içində
Function<String, String> read1 = path -> {
    try {
        return Files.readString(Paths.get(path));
    } catch (IOException e) {
        throw new RuntimeException(e);
    }
};

// Həll 2: custom interface
@FunctionalInterface
interface ThrowingFunction<T, R, E extends Exception> {
    R apply(T t) throws E;
}

static <T, R, E extends Exception> Function<T, R> unchecked(ThrowingFunction<T, R, E> f) {
    return t -> {
        try {
            return f.apply(t);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    };
}

Function<String, String> read2 = unchecked(p -> Files.readString(Paths.get(p)));

// Həll 3: Vavr library
import io.vavr.control.Try;

List<String> contents = paths.stream()
    .map(p -> Try.of(() -> Files.readString(Paths.get(p))))
    .filter(Try::isSuccess)
    .map(Try::get)
    .toList();
```

### 8) Currying və partial application

```java
// Native currying Java-da yoxdur — Function<A, Function<B, R>> yazmaq olar
Function<Integer, Function<Integer, Integer>> adder = a -> b -> a + b;

Function<Integer, Integer> add5 = adder.apply(5);
Integer n = add5.apply(3);    // 8

// Partial application
BiFunction<Integer, Integer, Integer> multiply = (a, b) -> a * b;

// "b" parametrini fix et
Function<Integer, Integer> doubleIt = a -> multiply.apply(a, 2);

// Generic partial applier
static <A, B, R> Function<B, R> partial(BiFunction<A, B, R> fn, A a) {
    return b -> fn.apply(a, b);
}
Function<Integer, Integer> times3 = partial(multiply, 3);
times3.apply(5);    // 15
```

### 9) Real istifadə — Strategy pattern

```java
public enum PricingStrategy {
    REGULAR(price -> price),
    DISCOUNT_10(price -> price * 0.9),
    DISCOUNT_25(price -> price * 0.75),
    VIP(price -> price * 0.5);

    private final UnaryOperator<Double> calculator;

    PricingStrategy(UnaryOperator<Double> calculator) {
        this.calculator = calculator;
    }

    public double apply(double price) {
        return calculator.apply(price);
    }
}

double finalPrice = PricingStrategy.VIP.apply(1000.0);    // 500.0
```

Köhnə tərzdə hər strategy üçün ayrı class yaratmaq lazım idi, indi lambda ilə sadə.

### 10) Stream + functional chain

```java
record Order(int id, int userId, double amount, LocalDate date) {}

List<Order> orders = ...;

// Fluent pipeline
double total = orders.stream()
    .filter(o -> o.userId() == 42)
    .filter(o -> o.date().isAfter(LocalDate.of(2026, 1, 1)))
    .mapToDouble(Order::amount)
    .sum();

// Grouping
Map<Integer, Double> byUser = orders.stream()
    .collect(Collectors.groupingBy(
        Order::userId,
        Collectors.summingDouble(Order::amount)
    ));

// Sorting
List<Order> sorted = orders.stream()
    .sorted(Comparator.comparing(Order::date).reversed())
    .limit(10)
    .toList();

// Multiple sort keys
Comparator<Order> byUserThenDate = Comparator
    .comparing(Order::userId)
    .thenComparing(Order::date);
```

### 11) Vavr — Scala-style functional library

```java
import io.vavr.collection.List;
import io.vavr.control.Option;
import io.vavr.control.Try;

// Immutable list
List<Integer> l = List.of(1, 2, 3).map(n -> n * 2);    // List(2, 4, 6)

// Option (Java Optional-dən genişdir)
Option<String> name = Option.of(user.getName());
name.map(String::toUpperCase)
    .filter(n -> n.length() > 3)
    .getOrElse("anonymous");

// Try — checked exception wrapping
Try<String> content = Try.of(() -> Files.readString(Paths.get("/etc/passwd")));
content.onFailure(ex -> log.error("oxunmadı", ex))
       .recover(IOException.class, "default")
       .get();
```

---

## PHP-də istifadəsi

### 1) Anonymous function — tarixi

PHP 5.3 `function () {}` anonim funksiyanı gətirdi. Bu `Closure` tipi-dir:

```php
$add = function (int $a, int $b): int {
    return $a + $b;
};

echo $add(2, 3);        // 5
echo $add instanceof \Closure;    // true (1)
```

### 2) Arrow function — PHP 7.4

Arrow function qısa sintaksisdir — outer scope-u avtomatik capture edir:

```php
// Ənənəvi closure
$multiplier = 3;
$multiply = function ($x) use ($multiplier) {
    return $x * $multiplier;
};

// Arrow function (PHP 7.4+)
$multiplier = 3;
$multiply = fn($x) => $x * $multiplier;    // $multiplier avtomatik capture

$multiply(5);    // 15

// Tək expression, tək sətir — amma çox istifadə olunur
$users
    ->map(fn($u) => $u->name)
    ->filter(fn($n) => strlen($n) > 3);
```

Diqqət: Arrow function yalnız **tək expression** ola bilər — blok yoxdur.

### 3) First-class callable syntax — PHP 8.1

PHP 8.1-də `...` ilə funksiya reference yaratmaq olur — bu Java method reference-inə çox oxşayır:

```php
// Named function → Closure
$strlen = strlen(...);       // Closure
$strlen('salam');            // 5

// Static method
$parse = DateTime::createFromFormat(...);

// Instance method — specific object
$logger = new Logger();
$log = $logger->info(...);
$log('message');

// Class method — generic (first arg becomes $this)
$toUpper = UnicodeString::toUpperCase(...);  // müəyyən sintaksis fərqli

// Constructor — closure-i wrapper kimi
$userFactory = fn(...$args) => new User(...$args);
```

### 4) Callable-i kitabxanalara ver

```php
// array_map callable alır
$names = array_map(strlen(...), ['salam', 'dünya']);
// [5, 5]

$upper = array_map(strtoupper(...), ['salam', 'dünya']);
// ['SALAM', 'DÜNYA']

// array_filter
$emails = ['a@x.com', 'not-email', 'b@y.com'];
$valid = array_filter(
    $emails,
    fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false
);

// array_reduce
$total = array_reduce([1, 2, 3, 4], fn($carry, $n) => $carry + $n, 0);
// 10

// Sorting
$users = [...];
usort($users, fn($a, $b) => $a->age <=> $b->age);
```

### 5) Closure::bind — $this dəyişmək

```php
class Container
{
    private string $secret = 'hidden';
}

$container = new Container();

// Closure-dan private-ə çat
$getSecret = Closure::bind(
    fn() => $this->secret,
    $container,
    Container::class
);

echo $getSecret();    // 'hidden'

// Object metoduna bind
$fn = function () {
    return $this->x + $this->y;
};

$point = new class {
    public int $x = 10;
    public int $y = 20;
};

$bound = Closure::bind($fn, $point, $point::class);
echo $bound();    // 30

// Shortcut — $closure->call($object)
$closure = function () {
    return $this->secret;
};
// bindTo + invoke-u bir addımda
echo $closure->call($container);    // 'hidden'
```

### 6) Closure::fromCallable

```php
// String callable → Closure
$c1 = Closure::fromCallable('strlen');
$c1('salam');        // 5

// Array callable → Closure
$c2 = Closure::fromCallable([$object, 'method']);
$c2('arg');

// Closure::fromCallable PHP 7.1-dən var
// First-class callable syntax (PHP 8.1) bunu əvəz etdi
```

### 7) Higher-order function composition

```php
// andThen analoqu — manual yaz
function pipe(callable ...$fns): callable
{
    return function ($input) use ($fns) {
        foreach ($fns as $fn) {
            $input = $fn($input);
        }
        return $input;
    };
}

$pipeline = pipe(
    fn($s) => trim($s),
    fn($s) => strtolower($s),
    fn($s) => str_replace(' ', '-', $s),
);

echo $pipeline('  Salam Dünya  ');    // 'salam-dünya'

// compose (əks sıra)
function compose(callable ...$fns): callable
{
    return pipe(...array_reverse($fns));
}
```

### 8) Laravel Collection higher-order methods

Laravel Collection method chaining + higher-order proxy verir:

```php
use Illuminate\Support\Collection;

$users = collect([
    ['name' => 'Ali', 'age' => 30, 'active' => true],
    ['name' => 'Veli', 'age' => 25, 'active' => false],
    ['name' => 'Sara', 'age' => 35, 'active' => true],
]);

// Standart chain
$activeNames = $users
    ->filter(fn($u) => $u['active'])
    ->map(fn($u) => $u['name'])
    ->values();

// Higher-order message — ->each->method(), ->map->property
$users->each->sendWelcomeEmail();          // hər user üçün sendWelcomeEmail()
$ages = $users->map->age;                   // ['age' sütunu']
$total = $users->sum('age');                // string kısayol
$sorted = $users->sortBy->age;               // sort by age field

// pluck
$names = $users->pluck('name');              // ['Ali', 'Veli', 'Sara']
```

### 9) Laravel Pipeline

```php
use Illuminate\Pipeline\Pipeline;

$result = app(Pipeline::class)
    ->send($request)
    ->through([
        AuthMiddleware::class,
        RateLimitMiddleware::class,
        LogMiddleware::class,
        fn($req, $next) => $next($req),   // inline middleware
    ])
    ->then(fn($req) => $controller->handle($req));

// Bu andThen-composition-un framework səviyyəsində versiyasıdır
```

### 10) Currying — Laravel və Lua style

```php
// Manual currying
function curry(callable $fn, int $arity): callable
{
    return function (...$args) use ($fn, $arity, &$curry) {
        if (count($args) >= $arity) {
            return $fn(...array_slice($args, 0, $arity));
        }
        return fn(...$more) => $fn(...$args, ...$more);
    };
}

$add = curry(fn($a, $b, $c) => $a + $b + $c, 3);

$add5 = $add(5);
$add5And10 = $add5(10);
echo $add5And10(15);    // 30

// və ya birdən
echo $add(5)(10)(15);

// Kitabxanalar: beberlei/assert, laraveldoctrine/orm və bəzi functional utility paketlər
```

### 11) Callable type hint

```php
function apply(callable $fn, mixed $input): mixed
{
    return $fn($input);
}

// Bu formalar callable-dir:
apply('strlen', 'salam');                              // string
apply([$obj, 'method'], 'arg');                        // array
apply(fn($x) => $x * 2, 5);                            // closure
apply(strlen(...), 'salam');                           // first-class callable
apply(UnicodeString::createFromString(...), 'salam');  // static method ref

// Strict typing
function mapAll(callable $fn, array $items): array
{
    return array_map($fn, $items);
}

// PHP 8.4 — Closure parameter type
function apply(\Closure $fn, mixed $input): mixed
{
    return $fn($input);
}
```

### 12) Real istifadə — Strategy pattern PHP-də

```php
enum PricingStrategy: string
{
    case REGULAR      = 'regular';
    case DISCOUNT_10  = 'discount_10';
    case DISCOUNT_25  = 'discount_25';
    case VIP          = 'vip';

    public function apply(float $price): float
    {
        return match ($this) {
            self::REGULAR     => $price,
            self::DISCOUNT_10 => $price * 0.9,
            self::DISCOUNT_25 => $price * 0.75,
            self::VIP         => $price * 0.5,
        };
    }
}

$finalPrice = PricingStrategy::VIP->apply(1000.0);    // 500.0

// Və ya callable map
$strategies = [
    'regular'     => fn($p) => $p,
    'discount_10' => fn($p) => $p * 0.9,
    'discount_25' => fn($p) => $p * 0.75,
    'vip'         => fn($p) => $p * 0.5,
];

$finalPrice = $strategies['vip'](1000.0);
```

### 13) Eloquent Collection və `->map->`

```php
use App\Models\Order;

$orders = Order::where('user_id', 42)->get();

// Higher-order message
$amounts = $orders->sum->amount;               // amount sütunu cəmi
$orders->each->markAsProcessed();               // hər Order üçün metod
$newest = $orders->sortByDesc->created_at->first();

// Standart
$totalAmount = $orders->reduce(
    fn($sum, $o) => $sum + $o->amount,
    0
);
```

### 14) Invokable class — callable class

```php
final class Multiplier
{
    public function __construct(
        private readonly int $factor,
    ) {}

    public function __invoke(int $x): int
    {
        return $x * $this->factor;
    }
}

$times3 = new Multiplier(3);
echo $times3(5);    // 15

// Bu da callable-dir
array_map(new Multiplier(2), [1, 2, 3]);    // [2, 4, 6]

// Laravel controller — single-action controller
final class PublishPostController
{
    public function __invoke(Request $request, Post $post): Response
    {
        // ...
    }
}
Route::post('/posts/{post}/publish', PublishPostController::class);
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Function type | `Function<T, R>`, `Predicate<T>`, ... | `Closure`, `callable` |
| Annotation/marker | `@FunctionalInterface` | Yoxdur |
| Primitive specializations | `IntFunction`, `ToIntFunction` | Yoxdur — int/float boxing problemi yoxdur |
| Method reference | `String::length` | First-class callable `strlen(...)` (PHP 8.1) |
| Static method ref | `Integer::parseInt` | `Class::method(...)` |
| Instance method ref | `obj::method`, `Type::method` | `$obj->method(...)` |
| Constructor ref | `ArrayList::new` | `fn(...$a) => new Class(...$a)` |
| `andThen` / `compose` | `Function.andThen/compose` | Manual `pipe()` / `compose()` |
| `Predicate.and/or/negate` | var | Manual |
| Checked exception | Problem — wrapping lazım | Yoxdur — exception checked deyil |
| Callable class | Lambda | `__invoke()` magic metod |
| Arrow function | Lambda `x -> x + 1` | `fn($x) => $x + 1` (PHP 7.4+) |
| Outer scope capture | Avtomatik (effectively final) | `use (...)` və ya arrow auto |
| Bind $this | Lambda `this` = enclosing | `Closure::bind`, `->call()` |
| Generic | Yes (`Function<T, R>`) | Yox — `callable` gen deyil |
| Laravel Pipeline equivalent | Reactor chain, Streams | `app(Pipeline::class)` |
| Higher-order message | Yoxdur | `$users->map->name` |

---

## Niyə belə fərqlər var?

**Java-nın tip sistemi güclüdür.** Java generic tip (`Function<T, R>`) ilə funksiyaları tipləyə bilər. Compiler yoxlayır: "bu yerdə Function<String, Integer> lazımdır, sən Function<Integer, String> verirsən" — error. PHP `callable` bir opaque tipdir — imzası static təhlil vaxtı bilinmir (amma PHPStan/Psalm generic callable (`callable(int): string`) dəstəkləyir).

**Primitive vs object.** Java-da `int`, `long`, `double` primitive-dir — `Function<Integer, Integer>` boxing edir (performans düşür). Java bu səbəbdən `IntFunction`, `IntUnaryOperator` kimi xüsusi variantlar verdi. PHP-də int/float zatən "elastikdir" — boxing concept yoxdur.

**Method reference — sintaksis məsələsi.** Java `::` operatoru xüsusi designed-dir — compiler-in hansı overload-a reference olunduğunu çıxarması lazımdır (method resolution). PHP 8.1-də `...` placeholder istifadə edilir — "argument-ları sonra təyin edəcəm". Məntiq fərqli, nəticə oxşar.

**Checked exception problemi — Java-da unique.** Java-da `IOException`, `SQLException` throws declare edilməlidir. Lambda `Function<T, R>` bunu dəstəkləmir — wrapping lazım. PHP-də bu yoxdur — hər exception runtime-dır, istəsən yaxala, istəməsən yox.

**Higher-order message — PHP-nin dinamik təbiəti.** Laravel `->map->name` yalnız dinamik dildə mümkündür — runtime `__get` magic metodu ilə higher-order proxy yaradır. Java compile-time tip yoxlanışı bu trick-ə icazə vermir.

**`use (...)` vs auto-capture.** Java lambda-da external variable "effectively final" olmalıdır (bir dəfə təyin edilmiş). PHP closure-da `use (...)` ilə açıq capture lazım idi — arrow function (7.4+) auto-capture etdi, amma by-value.

**Ecosystem fərqi.** Java-da Vavr, RxJava kimi functional library-lər var — immutable collection, Option, Try, Either ilə tam functional stili. PHP-də Laravel Collection və Pipeline populyar, amma "fully functional" kitabxanalar (Laravel-functional-php, funkcja) az istifadə olunur.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- `@FunctionalInterface` annotation
- Generic `Function<T, R>`, `Predicate<T>`, `Consumer<T>` hiyerarşiyası
- Primitive specializations (`IntFunction`, `DoubleSupplier`)
- `Function.andThen`, `compose` built-in
- `Predicate.and`, `or`, `negate`, `Predicate.not`, `Predicate.isEqual`
- Method reference 4 növ (`::`)
- Constructor reference (`ArrayList::new`)
- Stream API tam functional chain
- Vavr library — Try, Option, Either, immutable collection
- `Comparator.comparing`, `thenComparing` chain
- Bean Validation integration (`@NotNull` field-lərlə)

**Yalnız PHP-də:**
- Arrow function auto-capture (`fn($x) => $x + $outer`)
- `Closure::bind` — $this dəyişmək
- `Closure::call` — bir dəfəlik bind + invoke
- `Closure::fromCallable` — callable → Closure konvert
- Invokable class (`__invoke`)
- Higher-order message (`$users->map->name`)
- `array_map`, `array_filter`, `array_reduce` global funksiyalar
- Laravel Collection `->map->property`, `->each->method()`
- Laravel Pipeline facade
- Eloquent scope (method chaining with filters)
- `callable` pseudo-tip — string, array, Closure, invokable class hamı işləyir
- PHP 8.1 first-class callable syntax `strlen(...)`

---

## Best Practices

**Java:**
- `@FunctionalInterface` mütləq qoy — gələcək dəyişikliklər üçün mühafizə
- Generic `Function<T, R>` əvəzinə mənalı adı olan interface (`PricingStrategy`) xüsusi domain üçün
- Method reference (`String::length`) lambda-dan oxunaqlıdır (əgər bir arg varsa)
- Primitive specialization (`ToIntFunction`) böyük dataset-lərdə boxing-dən qaçmaq üçün
- Checked exception-ları `Try` (Vavr) və ya `unchecked` helper ilə yumşalt
- Lambda body 3 sətirdən uzundursa ayrı metoda çıxart
- `Comparator.comparing(User::getAge).thenComparing(User::getName)` chain oxunaqlı
- Stream chain 5-6 dən çox operator olursa dəyişənlərə böl
- Reactor `Mono.map(fn)` sırasına diqqət — reactive və imperative qarışdırma

**PHP:**
- Arrow function tək expression üçün (`fn() => ...`), blok lazımsa klassik closure
- First-class callable (`strlen(...)`) PHP 8.1+ standartdır — string callable-dən oxunaqlı
- `use (...)` ilə by-reference (`&$var`) capture diqqətli — unexpected mutation
- Laravel `->map->name` fluent-dir amma read-only — set üçün klassik loop
- Invokable class single-action controller üçün ideal
- `callable` type-hint istifadə et — amma PHPStan ilə generic callable (`callable(int): string`)
- Closure::bind yalnız testing və DSL üçün — production-da dəyişkən nəticələr verə bilər
- Laravel Pipeline middleware chain üçün — imperative transformation-dan oxunaqlı
- `array_map`, `array_filter` native-dir — Collection böyük data-da overhead olur

---

## Yekun

Java 8 functional interfaces və method references dili tam dəyişdi: `Function<T, R>`, `Predicate<T>`, lambda, `::` method reference, `Stream` API — hamısı vahid stildə. Primitive specialization (IntFunction) boxing-dən qaçır. Checked exception problemi (Vavr `Try`, wrapping) əlavə iş tələb edir. Vavr library Scala-style functional stili tam gətirir.

PHP tədricən funksional olub: 5.3 anonymous function, 7.4 arrow function, 8.1 first-class callable syntax. `Closure::bind` və `Closure::call` dinamik context dəyişikliyi verir — Java bunu edə bilmir. Laravel Collection və Pipeline framework səviyyəsində fluent functional stili standartlaşdırır — `->map->name` kimi higher-order message PHP-yə xasdır.

Seçimdə prinsip: hər iki dildə funksional stil mümkündür və produktivliyi artırır. Java-nın tip sistemi təhlükəsizlik verir (compile-time yoxlama), PHP-nin dinamik təbiəti qısa sintaksis verir. Böyük dataset və type-safe pipeline üçün Java, rapid API yazma və DSL-style code üçün PHP. Əsas qayda hər iki dildə eynidir: **funksiyalar kiçik, pure və composable olmalıdır**.
