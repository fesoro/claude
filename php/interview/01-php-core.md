# PHP Core (Middle)

## 1. PHP 8.x-de hansı yeni xüsusiyyətlər var?

**PHP 8.0:**
- **Named Arguments:** Funksiyanı çağıranda parametr adı ilə dəyər ötürmək.
```php
function createUser(string $name, int $age, string $city = 'Baku') {}
createUser(name: 'Orxan', age: 30);
```
- **Union Types:** Bir parametr bir neçə tip qəbul edə bilər.
```php
function process(int|string $value): bool|int {}
```
- **Match expression:** switch-in daha güclü versiyası, strict comparison edir.
```php
$result = match($status) {
    'active' => 'Aktiv',
    'inactive' => 'Deaktiv',
    default => 'Naməlum',
};
```
- **Nullsafe operator:** `?->` — null check zənciri.
```php
$city = $user?->getAddress()?->getCity();
```
- **Constructor Property Promotion:**
```php
class User {
    public function __construct(
        private string $name,
        private int $age,
    ) {}
}
```
- **Attributes (Annotations):**
```php
#[Route('/api/users', methods: ['GET'])]
public function index() {}
```
- **JIT Compiler:** Performansı artıran Just-In-Time compilation.

**PHP 8.1:**
- **Enums:**
```php
enum Status: string {
    case Active = 'active';
    case Inactive = 'inactive';
}
```
- **Fibers:** Asinxron proqramlaşdırma üçün əsas.
- **Readonly properties:**
```php
class User {
    public function __construct(
        public readonly string $name,
    ) {}
}
```
- **Intersection Types:** `function process(Countable&Iterator $value) {}`
- **First-class callable syntax:** `$fn = strlen(...);`

**PHP 8.2:**
- **Readonly classes:** Bütün property-lər avtomatik readonly olur.
- **Disjunctive Normal Form (DNF) Types:** `(A&B)|C`
- **`true`, `false`, `null` standalone types**
- **Constants in traits**

**PHP 8.3:**
- **Typed class constants:** `const string NAME = 'test';`
- **`json_validate()` funksiyası**
- **`#[\Override]` attribute**
- **Dynamic class constant fetch:** `$class::{$constName}`

---

## 2. PHP-də type juggling və strict comparison nədir?

PHP **weakly typed** dildir. Type juggling — PHP-nin avtomatik tip çevrilməsidir.

```php
// Type juggling (loose comparison ==)
0 == "foo"    // PHP 7: true, PHP 8: false (dəyişdi!)
"" == null    // true
"0" == false  // true
0 == null     // true

// Strict comparison (===) — tip və dəyər eyni olmalıdır
0 === false   // false
"1" === 1     // false
```

**Best practice:** Həmişə `===` və `!==` istifadə edin. `declare(strict_types=1);` faylın əvvəlinə yazın.

```php
declare(strict_types=1);

function add(int $a, int $b): int {
    return $a + $b;
}

add("2", "3"); // TypeError atır
```

---

## 3. PHP-də reference vs value nədir?

```php
// By value (default) — kopya yaranır
$a = 5;
$b = $a;
$b = 10;
echo $a; // 5

// By reference — eyni yaddaş sahəsinə işarə
$a = 5;
$b = &$a;
$b = 10;
echo $a; // 10

// Funksiyada
function increment(&$value) {
    $value++;
}
$x = 5;
increment($x);
echo $x; // 6
```

**Obyektlər:** PHP-də obyektlər həmişə **object identifier** ilə ötürülür (reference kimi davranır, amma tam reference deyil).

```php
$a = new stdClass();
$a->name = 'Orxan';
$b = $a;           // eyni obyektə işarə
$b->name = 'Ali';
echo $a->name;     // 'Ali'

$c = clone $a;     // yeni kopya yaranır
$c->name = 'Veli';
echo $a->name;     // 'Ali' (dəyişmədi)
```

---

## 4. PHP-də Generators nədir və nə üçün istifadə olunur?

Generator — yaddaşı qənaət edərək böyük data setləri üzərində iterasiya etmək üçündür. `yield` keyword istifadə edir.

```php
// Adi yanaşma — bütün array yaddaşda saxlanır
function getNumbers(): array {
    $numbers = [];
    for ($i = 0; $i < 1000000; $i++) {
        $numbers[] = $i;
    }
    return $numbers; // ~32MB yaddaş
}

// Generator — hər dəfə yalnız 1 element yaddaşda olur
function getNumbers(): Generator {
    for ($i = 0; $i < 1000000; $i++) {
        yield $i;
    }
}

foreach (getNumbers() as $number) {
    echo $number;
}
```

**Real-world misal:** Böyük CSV faylı oxumaq:
```php
function readCsv(string $path): Generator {
    $handle = fopen($path, 'r');
    while (($row = fgetcsv($handle)) !== false) {
        yield $row;
    }
    fclose($handle);
}

foreach (readCsv('big_file.csv') as $row) {
    // Hər sətir ayrıca işlənir, bütün fayl yaddaşa yüklənmir
}
```

---

## 5. Late Static Binding nədir?

`static::` keyword vasitəsilə çağırılan sinifə istinad etmək. `self::` ilə fərqi:

```php
class ParentClass {
    protected static string $type = 'parent';

    public static function create(): static {
        return new static(); // çağırılan sinifin instance-ı
    }

    public function getType(): string {
        return static::$type; // late static binding
    }

    public function getSelfType(): string {
        return self::$type; // həmişə ParentClass-ın $type-ı
    }
}

class ChildClass extends ParentClass {
    protected static string $type = 'child';
}

$child = ChildClass::create(); // ChildClass instance-ı qaytarır
echo $child->getType();        // 'child'
echo $child->getSelfType();    // 'parent'
```

---

## 6. PHP-də closures və anonymous functions necə işləyir?

```php
// Anonymous function
$greet = function(string $name): string {
    return "Salam, $name!";
};
echo $greet('Orxan');

// Closure — xarici dəyişəni capture edir
$prefix = 'Mr.';
$greet = function(string $name) use ($prefix): string {
    return "Salam, $prefix $name!";
};

// use by reference
$counter = 0;
$increment = function() use (&$counter) {
    $counter++;
};
$increment();
echo $counter; // 1

// Arrow function (PHP 7.4+) — avtomatik capture edir
$multiplier = 3;
$multiply = fn(int $x): int => $x * $multiplier;
echo $multiply(5); // 15

// Closure::bind — closure-un $this-ini dəyişmək
class Wallet {
    private int $balance = 100;
}

$getBalance = Closure::bind(
    function() { return $this->balance; },
    new Wallet(),
    Wallet::class
);
echo $getBalance(); // 100
```

---

## 7. PHP-də Error Handling necə işləyir?

**PHP 7+ Exception hierarchy:**
```
Throwable
├── Error (engine-level)
│   ├── TypeError
│   ├── ValueError
│   ├── ArithmeticError
│   └── ...
└── Exception (application-level)
    ├── RuntimeException
    ├── LogicException
    ├── InvalidArgumentException
    └── ...
```

```php
// Try-catch-finally
try {
    riskyOperation();
} catch (SpecificException $e) {
    // spesifik exception
} catch (AnotherException | YetAnotherException $e) {
    // multi-catch (PHP 8+)
} catch (Exception $e) {
    // ümumi exception
} finally {
    // həmişə icra olunur
}

// Custom exception
class InsufficientBalanceException extends RuntimeException {
    public function __construct(
        private float $balance,
        private float $amount,
    ) {
        parent::__construct("Balans kifayət deyil: {$balance} < {$amount}");
    }

    public function getBalance(): float { return $this->balance; }
    public function getAmount(): float { return $this->amount; }
}
```

---

## 8. PHP-də Interfaces, Abstract Classes və Traits arasındakı fərq nədir?

```php
// Interface — kontrakt (nə etməli)
interface Cacheable {
    public function getCacheKey(): string;
    public function getCacheTTL(): int;
}

// Abstract class — qismən implementasiya (bir hissəsi hazır)
abstract class BaseRepository {
    abstract protected function getModel(): string;

    public function find(int $id): ?Model {
        return ($this->getModel())::find($id);
    }
}

// Trait — kod paylaşımı (horizontal reuse)
trait HasUuid {
    public static function bootHasUuid(): void {
        static::creating(function ($model) {
            $model->uuid = Str::uuid();
        });
    }
}

// Bir yerdə istifadə
class UserRepository extends BaseRepository implements Cacheable {
    use HasUuid;

    protected function getModel(): string { return User::class; }
    public function getCacheKey(): string { return 'users'; }
    public function getCacheTTL(): int { return 3600; }
}
```

**Əsas fərqlər:**
| | Interface | Abstract Class | Trait |
|---|---|---|---|
| Instance yaratmaq | Yox | Yox | Yox |
| Method body | PHP 8+ default methods | Bəli | Bəli |
| Properties | Yox (PHP 8.4+ hooks) | Bəli | Bəli |
| Multiple inheritance | Bəli | Yox | Bəli |
| Constructor | Yox | Bəli | Yox (amma ola bilər) |

---

## 9. PHP-də Dependency Injection nədir?

Sinifin asılılıqlarını xaricdən almaq, daxildə yaratmamaq.

```php
// Pis - tight coupling
class OrderService {
    public function process(): void {
        $mailer = new SmtpMailer(); // birbaşa asılılıq
        $mailer->send('order confirmed');
    }
}

// Yaxşı - DI ilə
interface MailerInterface {
    public function send(string $message): void;
}

class OrderService {
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function process(): void {
        $this->mailer->send('order confirmed');
    }
}

// İndi test-də mock edə bilərik
$mock = $this->createMock(MailerInterface::class);
$service = new OrderService($mock);
```

**DI növləri:**
1. **Constructor Injection** (ən çox istifadə olunan)
2. **Method Injection** — bir metoda lazım olanda
3. **Property Injection** — setter vasitəsilə

---

## 10. PHP-də magic methods hansılardır?

```php
class MagicDemo {
    private array $data = [];

    public function __construct() {}        // Yaradılanda
    public function __destruct() {}         // Silinəndə
    public function __get($name) {}         // Olmayan property oxunanda
    public function __set($name, $value) {} // Olmayan property-ə yazanda
    public function __isset($name) {}       // isset() çağırılanda
    public function __unset($name) {}       // unset() çağırılanda
    public function __call($name, $args) {} // Olmayan metod çağırılanda
    public static function __callStatic($name, $args) {} // Static versiya
    public function __toString(): string {} // String-ə çevriləndə
    public function __invoke() {}           // Obyekt funksiya kimi çağırılanda
    public function __clone() {}            // clone olunanda
    public function __serialize(): array {} // serialize() çağırılanda
    public function __unserialize(array $data): void {} // unserialize()
    public function __debugInfo(): array {} // var_dump() çağırılanda
}
```
