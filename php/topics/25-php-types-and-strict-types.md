# PHP Types və strict_types

## PHP Type System Tarixi

PHP zəif tipli (weakly typed) dil olaraq başladı. PHP 5-də type hints əlavə edildi, PHP 7-də isə böyük irəliləyiş oldu.

```
PHP 5.0  → Class/Interface type hints
PHP 7.0  → Scalar type hints, Return types, declare(strict_types=1)
PHP 7.1  → Nullable types (?string), void return type
PHP 7.2  → object type
PHP 7.4  → Typed properties
PHP 8.0  → Union types, named arguments, match, nullsafe operator, constructor promotion
PHP 8.1  → Enums, readonly properties, never type, intersection types, fibers
PHP 8.2  → readonly classes, DNF types
PHP 8.3  → Typed class constants
```

---

## declare(strict_types=1)

### Fərq nədir?

`strict_types=0` (default) — PHP type coercion edir (məsələn, `"5"` → `5`).
`strict_types=1` — yalnız dəqiq tip qəbul edir, əks halda `TypeError` atar.

*`strict_types=1` — yalnız dəqiq tip qəbul edir, əks halda `TypeError`  üçün kod nümunəsi:*
```php
<?php
// strict_types=0 (default)
function add(int $a, int $b): int {
    return $a + $b;
}

add("5", "3");  // ✅ işləyir, "5" → 5 coerce edilir, nəticə: 8
add("5abc", 3); // ✅ işləyir, "5abc" → 5 coerce edilir (warning ilə)

// --------------------------------------------------

<?php
declare(strict_types=1);

function add(int $a, int $b): int {
    return $a + $b;
}

add("5", "3");  // ❌ TypeError: add(): Argument #1 must be of type int, string given
add(5, 3);      // ✅ işləyir
```

### strict_types yalnız çağıran fayla aiddir

*strict_types yalnız çağıran fayla aiddir üçün kod nümunəsi:*
```php
// math.php (strict_types YOX)
function multiply(int $a, int $b): int {
    return $a * $b;
}

// caller.php
<?php
declare(strict_types=1);
require 'math.php';

multiply("5", "3"); // ❌ TypeError — çünki caller.php-də strict_types=1
```

### Laravel-də strict_types

*Laravel-də strict_types üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\ValueObjects\Money;

class OrderService
{
    public function calculateTotal(array $items): Money
    {
        $total = array_reduce($items, function (int $carry, array $item): int {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);

        return new Money($total, 'AZN');
    }
}
```

---

## Scalar Types

*Scalar Types üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

function greet(string $name): string {
    return "Salam, $name!";
}

function divide(float $a, float $b): float {
    if ($b === 0.0) {
        throw new \InvalidArgumentException("Sıfıra bölmək olmaz");
    }
    return $a / $b;
}

function isAdult(bool $isAdult): string {
    return $isAdult ? "Yetkin" : "Yetkin deyil";
}

function getAge(int $userId): int {
    // ...
    return 25;
}
```

---

## Return Types

*Return Types üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

// void - heç nə return etmir
function logMessage(string $message): void {
    error_log($message);
    // return; // boş return olar, amma return 5; olmaz
}

// never - heç vaxt return etmir (exception atır və ya die/exit)
function throwError(string $message): never {
    throw new \RuntimeException($message);
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

// static - late static binding üçün
class Builder
{
    protected array $data = [];

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }
}

class UserBuilder extends Builder
{
    public function build(): array
    {
        return $this->data;
    }
}

$user = (new UserBuilder())->set('name', 'Orkhan')->set('age', 25)->build();
```

---

## Nullable Types

*Nullable Types üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

// ?Type = Type|null
function findUser(?int $id): ?string
{
    if ($id === null) {
        return null;
    }
    return "User #$id";
}

findUser(null);  // ✅ null qaytarır
findUser(5);     // ✅ "User #5" qaytarır
findUser("5");   // ❌ strict_types=1 ilə TypeError
```

---

## Union Types (PHP 8.0)

*Union Types (PHP 8.0) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

function processInput(int|string $input): string
{
    if (is_int($input)) {
        return "Ədəd: $input";
    }
    return "Mətn: $input";
}

processInput(42);      // ✅ "Ədəd: 42"
processInput("hello"); // ✅ "Mətn: hello"
processInput(3.14);    // ❌ TypeError

// Null ilə birlikdə
function findById(int|string|null $id): mixed
{
    if ($id === null) return null;
    // ...
}

// Laravel-də real nümunə
class UserRepository
{
    public function find(int|string $identifier): ?User
    {
        if (is_int($identifier)) {
            return User::find($identifier);
        }
        return User::where('email', $identifier)->first();
    }
}
```

---

## Intersection Types (PHP 8.1)

*Intersection Types (PHP 8.1) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

interface Printable
{
    public function print(): void;
}

interface Saveable
{
    public function save(): void;
}

// Hər iki interface-i implement edən tip tələb edir
function processDocument(Printable&Saveable $document): void
{
    $document->save();
    $document->print();
}

class Invoice implements Printable, Saveable
{
    public function print(): void { echo "Invoice printed"; }
    public function save(): void { echo "Invoice saved"; }
}

processDocument(new Invoice()); // ✅
```

---

## Mixed Type

*Mixed Type üçün kod nümunəsi:*
```php
<?php
// mixed = string|int|float|bool|array|object|null|resource
function process(mixed $value): mixed
{
    return $value;
}

// mixed istifadəsi - type bilinmədikdə
class Collection
{
    private array $items = [];

    public function add(mixed $item): void
    {
        $this->items[] = $item;
    }

    public function get(int $index): mixed
    {
        return $this->items[$index] ?? null;
    }
}
```

---

## Typed Properties (PHP 7.4)

*Typed Properties (PHP 7.4) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

class User
{
    public int $id;
    public string $name;
    public ?string $email = null;
    protected float $balance = 0.0;
    private bool $isActive = true;

    // Initialized olmamış typed property-yə daxil olmaq Error atır
    public function getId(): int
    {
        return $this->id; // $id initialized olmayıbsa → Error
    }
}

$user = new User();
$user->id = 1;
$user->name = "Orkhan";
$user->email = null; // ✅ nullable olduğu üçün
$user->email = 123;  // ❌ TypeError
```

---

## Readonly Properties (PHP 8.1)

*Readonly Properties (PHP 8.1) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}
}

$money = new Money(1000, 'AZN');
echo $money->amount;   // ✅ 1000
$money->amount = 2000; // ❌ Error: Cannot modify readonly property

// Readonly class (PHP 8.2) - bütün properties readonly olur
readonly class Point
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z,
    ) {}
}

// Laravel-də Value Object kimi istifadə
readonly class UserId
{
    public function __construct(
        public readonly int $value,
    ) {
        if ($value <= 0) {
            throw new \InvalidArgumentException('UserId müsbət olmalıdır');
        }
    }
}
```

---

## Enums (PHP 8.1)

### Pure Enum

*Pure Enum üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

enum Status
{
    case Pending;
    case Active;
    case Inactive;
    case Banned;
}

// İstifadəsi
$status = Status::Active;
echo $status->name; // "Active"

function activate(Status $status): void
{
    if ($status !== Status::Inactive) {
        throw new \LogicException("Yalnız Inactive status aktiv edilə bilər");
    }
}
```

### Backed Enum (int/string value ilə)

*Backed Enum (int/string value ilə) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

enum PaymentStatus: string
{
    case Pending   = 'pending';
    case Captured  = 'captured';
    case Refunded  = 'refunded';
    case Failed    = 'failed';

    public function label(): string
    {
        return match($this) {
            PaymentStatus::Pending  => 'Gözləmədə',
            PaymentStatus::Captured => 'Ödənildi',
            PaymentStatus::Refunded => 'Qaytarıldı',
            PaymentStatus::Failed   => 'Uğursuz',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Captured, self::Refunded, self::Failed]);
    }

    public function canTransitionTo(self $new): bool
    {
        return match($this) {
            self::Pending  => in_array($new, [self::Captured, self::Failed]),
            self::Captured => $new === self::Refunded,
            default        => false,
        };
    }
}

// Database-dən oxuma
$status = PaymentStatus::from('pending');    // PaymentStatus::Pending
$status = PaymentStatus::tryFrom('unknown'); // null (exception atmır)

// Laravel-də Enum casting
class Payment extends Model
{
    protected $casts = [
        'status' => PaymentStatus::class,
    ];
}

$payment = Payment::find(1);
echo $payment->status->label(); // "Gözləmədə"
echo $payment->status->value;   // "pending"
```

### Enum Interface ilə

*Enum Interface ilə üçün kod nümunəsi:*
```php
<?php
interface HasColor
{
    public function color(): string;
}

enum Suit: string implements HasColor
{
    case Hearts   = 'H';
    case Diamonds = 'D';
    case Clubs    = 'C';
    case Spades   = 'S';

    public function color(): string
    {
        return match($this) {
            self::Hearts, self::Diamonds => 'red',
            self::Clubs, self::Spades   => 'black',
        };
    }
}
```

---

## Named Arguments (PHP 8.0)

*Named Arguments (PHP 8.0) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

function createUser(
    string $name,
    int $age,
    string $email,
    bool $isAdmin = false,
    ?string $phone = null,
): array {
    return compact('name', 'age', 'email', 'isAdmin', 'phone');
}

// Ənənəvi çağırış (mövqeyə görə)
createUser('Orkhan', 25, 'orkhan@example.com', false, null);

// Named arguments ilə (mövqe vacib deyil)
createUser(
    name: 'Orkhan',
    email: 'orkhan@example.com',
    age: 25,
    isAdmin: true,
);

// Laravel-də faydalı nümunə
$users = User::query()
    ->where(column: 'status', operator: 'active')
    ->orderBy(column: 'created_at', direction: 'desc')
    ->paginate(perPage: 15, page: 2);
```

---

## Match Expression (PHP 8.0)

*Match Expression (PHP 8.0) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

// Switch ilə müqayisə
$status = 'active';

// Köhnə yol (switch)
switch ($status) {
    case 'active':
        $label = 'Aktiv';
        break;
    case 'inactive':
        $label = 'Deaktiv';
        break;
    default:
        $label = 'Naməlum';
}

// Yeni yol (match) - strict comparison, expression qaytarır
$label = match($status) {
    'active'   => 'Aktiv',
    'inactive' => 'Deaktiv',
    'banned'   => 'Bloklanmış',
    default    => 'Naməlum',
};

// Çoxlu case
$httpMethod = 'GET';
$type = match($httpMethod) {
    'GET', 'HEAD'    => 'read',
    'POST', 'PUT', 'PATCH' => 'write',
    'DELETE'         => 'delete',
    default          => throw new \InvalidArgumentException("Unknown method"),
};

// Complex conditions
$age = 25;
$category = match(true) {
    $age < 18  => 'uşaq',
    $age < 30  => 'gənc',
    $age < 60  => 'orta yaşlı',
    default    => 'yaşlı',
};
```

---

## Nullsafe Operator (?->) — PHP 8.0

*Nullsafe Operator (?->) — PHP 8.0 üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

// Köhnə yol
$city = null;
if ($user !== null) {
    if ($user->address !== null) {
        if ($user->address->city !== null) {
            $city = $user->address->city->name;
        }
    }
}

// Nullsafe operator ilə
$city = $user?->address?->city?->name;

// Method chainlə
$zipCode = $order?->getShippingAddress()?->getZipCode();

// Laravel-də
$avatar = auth()->user()?->profile?->avatar_url ?? '/default-avatar.png';
```

---

## Constructor Promotion (PHP 8.0)

*Constructor Promotion (PHP 8.0) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

// Köhnə yol
class UserDTO
{
    public string $name;
    public string $email;
    public int $age;

    public function __construct(string $name, string $email, int $age)
    {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
    }
}

// Constructor promotion ilə (eyni şey, daha az kod)
class UserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly int $age,
    ) {}
}

// Default values ilə
class PaginationDTO
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDir = 'desc',
    ) {}
}
```

---

## First Class Callables (PHP 8.1)

*First Class Callables (PHP 8.1) üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

// Köhnə yol
$fn = function (int $n): int { return $n * 2; };
$arr = array_map(fn($n) => $n * 2, [1, 2, 3]);

// First class callable syntax
function double(int $n): int { return $n * 2; }

$fn = double(...);         // Closure yaradır
$arr = array_map(double(...), [1, 2, 3]); // [2, 4, 6]

// Method-larla
class MathHelper
{
    public static function square(int $n): int { return $n ** 2; }
    public function cube(int $n): int { return $n ** 3; }
}

$squareFn = MathHelper::square(...);
$helper = new MathHelper();
$cubeFn = $helper->cube(...);

// Laravel Collection-da
$users = User::all();
$names = $users->map(fn(User $u) => $u->name);
// vs
$getNameFn = fn(User $u) => $u->name;
$names = $users->map($getNameFn);
```

---

## Type Juggling / Coercion Nümunələri

*Type Juggling / Coercion Nümunələri üçün kod nümunəsi:*
```php
<?php
// strict_types=0 (default) — PHP coercion edir
var_dump((int)"42abc");    // int(42)
var_dump((int)"abc");      // int(0)
var_dump((bool)"");        // bool(false)
var_dump((bool)"0");       // bool(false)
var_dump((bool)"false");   // bool(true) — diqqət!
var_dump((float)"3.14");   // float(3.14)
var_dump((string)true);    // string(1) "1"
var_dump((string)false);   // string(0) ""
var_dump((string)null);    // string(0) ""
var_dump((array)null);     // array(0) {}
var_dump((array)"hello");  // array(1) { [0] => string(5) "hello" }

// Loose vs Strict comparison
var_dump(0 == "a");        // bool(true)  — PHP 7 (PHP 8-də false!)
var_dump(0 === "a");       // bool(false)
var_dump("1" == "01");     // bool(true)
var_dump("10" == "1e1");   // bool(true)
var_dump(100 == "1e2");    // bool(true)
```

---

## Static Analysis: PHPStan və Psalm

### PHPStan

*PHPStan üçün kod nümunəsi:*
```bash
composer require --dev phpstan/phpstan
composer require --dev phpstan/extension-installer
composer require --dev nunomaduro/larastan  # Laravel üçün
```

*composer require --dev nunomaduro/larastan  # Laravel üçün üçün kod nümunəsi:*
```yaml
# phpstan.neon
includes:
    - ./vendor/nunomaduro/larastan/extension.neon

parameters:
    level: 8  # 0-9, 9 ən strict
    paths:
        - app
    checkMissingIterableValueType: false
    ignoreErrors:
        - '#Unsafe usage of new static#'
```

*- '#Unsafe usage of new static#' üçün kod nümunəsi:*
```bash
./vendor/bin/phpstan analyse
```

### Psalm

*Psalm üçün kod nümunəsi:*
```bash
composer require --dev vimeo/psalm
./vendor/bin/psalm --init
```

*./vendor/bin/psalm --init üçün kod nümunəsi:*
```xml
<!-- psalm.xml -->
<?xml version="1.0"?>
<psalm
  errorLevel="3"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xmlns="https://getpsalm.org/schema/config"
>
    <projectFiles>
        <directory name="app" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
```

---

## Laravel-də strict_types Faydaları

*Laravel-də strict_types Faydaları üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

namespace App\Services;

class CartService
{
    // strict_types olmadan bu metod "10" string qəbul edərdi
    public function addItem(int $productId, int $quantity, float $price): void
    {
        // type-safe processing
    }

    // Return type garantiyası
    public function getTotal(): float
    {
        return array_sum(array_map(
            fn(array $item) => $item['price'] * $item['quantity'],
            $this->items
        ));
    }
}

// Controller-də
class CartController extends Controller
{
    public function add(Request $request, CartService $cartService): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'quantity'   => 'required|integer|min:1',
            'price'      => 'required|numeric',
        ]);

        // $validated['product_id'] string-dir, (int) cast lazımdır
        $cartService->addItem(
            productId: (int) $validated['product_id'],
            quantity:  (int) $validated['quantity'],
            price:     (float) $validated['price'],
        );

        return response()->json(['message' => 'Əlavə edildi']);
    }
}
```

---

## Fibers (PHP 8.1)

*Fibers (PHP 8.1) üçün kod nümunəsi:*
```php
<?php
// Fiber - kooperativ multitasking (coroutine)
$fiber = new Fiber(function(): void {
    $value = Fiber::suspend('first');
    echo "Fiber dəyər aldı: $value\n";
    Fiber::suspend('second');
    echo "Fiber bitdi\n";
});

$value1 = $fiber->start();
echo "Ana kod: $value1\n";  // "first"

$value2 = $fiber->resume('hello');
echo "Ana kod: $value2\n";  // "second"

$fiber->resume();

// Laravel Octane Fibers-dan istifadə edir
// Async HTTP requests
use Laravel\Octane\Facades\Octane;

[$users, $orders] = Octane::concurrently([
    fn() => User::all(),
    fn() => Order::pending()->get(),
]);
```

---

## İntervyu Sualları və Cavabları

**S: strict_types=1 ilə strict_types=0 fərqi nədir?**
C: strict_types=0-da PHP type coercion edir (məsələn `"5"` → `int(5)`). strict_types=1-də isə yalnız dəqiq tip qəbul edilir, əks halda `TypeError` atılır. Bu, bugs-ları erkən aşkar etməyə kömək edir.

**S: Union types nə vaxt istifadə edilir?**
C: Bir parametr/return bir neçə fərqli tipdə ola biləndə. Məsələn `int|string` — id həm integer (database id), həm də string (UUID) ola bilər.

**S: Readonly property-nin mənfi cəhəti varmı?**
C: Clone zamanı modifikasiya mümkün deyil (PHP 8.3-də dəyişdi). Test-lərdə mock etmək çətinləşə bilər.

**S: Enum nə vaxt string, nə vaxt int backed istifadə etməli?**
C: Database-də məna daşıyan string-lər (`'pending'`, `'active'`) üçün string backed; bit flags və ya performance critical yerlər üçün int backed.

**S: `match` ilə `switch` arasındakı fərqlər?**
C: 1) `match` strict comparison (`===`) istifadə edir, `switch` loose (`==`). 2) `match` expression-dır, dəyər qaytarır. 3) `match`-də `break` lazım deyil. 4) `match` exhaustive olmalıdır (default olmasa, unmatched case üçün `UnhandledMatchError` atır).

**S: Named arguments-ın faydası nədir?**
C: 1) Parametr sırasına bağlılığı aradan qaldırır. 2) Kodun oxunaqlığını artırır. 3) Default parametrləri atlamağa imkan verir.

**S: PHPStan level neçə olmalıdır?**
C: Mövcud proyektlər üçün 5-6, yeni proyektlər üçün 8 tövsiyə olunur. Level 9 ən strict-dir.

**S: `never` return type nə vaxt istifadə edilir?**
C: Metod heç vaxt normal return etmədikdə — ya exception atır, ya da `exit()`/`die()` çağırır. Static analysis toolları üçün faydalıdır.

**S: Constructor promotion nədir, hər yerdə istifadə etmək lazımdırmı?**
C: Constructor promotion boilerplate kodu azaldır, amma mürəkkəb validation logic olan constructor-larda ənənəvi yol daha oxunaqlı ola bilər.

**S: Intersection types ilə Union types fərqi?**
C: Union types (`A|B`) parametrin A ya da B olduğunu bildirir. Intersection types (`A&B`) isə parametrin həm A həm də B olduğunu — yəni hər iki interface-i implement etdiyini bildirir.

**S: DNF (Disjunctive Normal Form) types nədir? (PHP 8.2)**
C: Union ve Intersection type-ların kombinasiyasıdır: `(Traversable&Countable)|null`. Parentez içindəki Intersection type-lar OR ilə birləşdirilir. Nümunə: `function process((Stringable&Countable)|string $input)` — ya hər iki interface-i implement edən obyekt, ya da adi string.

**S: PHP-də `(bool)"false"` nə qaytarır və niyə?**
C: `true` qaytarır. Boş olmayan hər string `true`-dur — `"false"` stringi boş deyil, odur ki `true` olur. Yalnız `""` (boş string) və `"0"` falsy string-lərdir. Bu coercion qaydası çox yanlış anlaşılır.

**S: Typed class constants (PHP 8.3) nədir?**
C: PHP 8.3-dən class constant-ların tipi müəyyən edilə bilir: `const string VERSION = '1.0';`. Əvvəl constant-ların tipi yox idi, istənilən dəyər atanırdı. Typed constants interface-lərdə də işləyir — implement edən class o tipi saxlamalıdır.

**S: `array_is_list()` funksiyası nə üçündür? (PHP 8.1)**
C: Array-in `[0, 1, 2, ...]` kimi sıralı integer key-lərə sahib olub olmadığını yoxlayır. `[0=>'a', 1=>'b']` list-dir, `['a'=>0, 'b'=>1]` deyil. JSON serialization-da array vs object fərqi üçün vacibdir: list `[]`, digər array `{}` kimi serialize olunur.

**S: `readonly` class ilə `readonly` property fərqi nədir?**
C: `readonly` property yalnız o property-ni yazılmaz edir. `readonly class` (PHP 8.2) isə bütün declared property-ləri avtomatik `readonly` edir, həmçinin class-a non-readonly dynamic property əlavə etməyi qadağan edir. `readonly class` üçün bütün property-lərin tipi olmalıdır.

---

## Anti-patternlər

**1. `declare(strict_types=1)` olmadan tip güvənməsi**
`strict_types` olmadan `function add(int $a, int $b)` yazmaq — PHP "3" stringini 3-ə çevirir, gözlənilməz tip coercion baş verir, bug-lar gizlənir. Hər PHP faylının başına `declare(strict_types=1)` əlavə et.

**2. Nullable tip əvəzinə sentinel dəyər qaytarmaq**
Tapılmayan entity üçün `null` yerinə `false`, `-1`, `""` qaytarmaq — çağıran kod bütün bu variantları bilməlidir, tip sistemi pozulur. `?User` nullable return type istifadə et, ya da `null` qaytarır.

**3. `mixed` tipini lazımsız genişliyə işlətmək**
Hər yerdə `mixed` yazıb type safety-i deaktiv etmək — static analysis toolları (PHPStan/Psalm) hiçnə yarımır, real bug-lar compile əvəzinə runtime-da çıxır. Konkret tip və ya union type (`int|string`) işlət.

**4. Enum əvəzinə string constant-lar işlətmək (PHP 8.1+)**
`const STATUS_ACTIVE = 'active'` kimi constant-lar — typo-lar runtime-da aşkar olunur, IDE completion yoxdur, exhaustive match mümkünsüzdür. PHP 8.1 `enum` istifadə et, `BackedEnum` ilə DB serialization da asanlaşır.

**5. Union type-larda tip yoxlamasını atlamaq**
`function process(int|string $id)` alıb içəridə tip yoxlamadan işlətmək — hansı tipin gəldiyini bilmədən əməliyyat etmək runtime xətasına gətirib çıxarır. `is_int($id)` ilə ayırd et, ya da ayrı overloaded metodlar yaz.

**6. PHPStan-ı yalnız level 1-2-də saxlamaq**
Statik analizi minimum səviyyədə tutmaq — dərin tip xətaları, null dereference, dead code aşkar olunmur. Yeni layihələrdə level 8+ hədəflə, mövcud layihəni tədricən artır, CI-da PHPStan məcburi et.
