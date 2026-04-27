# PHP 8.3 & 8.4 (Junior)

## Mündəricat
1. [PHP 8.3 (2023 Nov)](#php-83-2023-nov)
2. [PHP 8.4 (2024 Nov)](#php-84-2024-nov)
3. [Typed class constants](#typed-class-constants-83)
4. [Readonly amendments](#readonly-amendments)
5. [Property hooks (8.4)](#property-hooks-84)
6. [Asymmetric visibility (8.4)](#asymmetric-visibility-84)
7. [new in initializer (8.4)](#new-in-initializer-84)
8. [Array functions yeni (8.4)](#array-functions-yeni-84)
9. [Deprecation siyahısı](#deprecation-siyahısı)
10. [Upgrade strategy](#upgrade-strategy)
11. [İntervyu Sualları](#intervyu-sualları)

---

## PHP 8.3 (2023 Nov)

```
Əsas yeniliklər:
  1. Typed class constants          — const NAME: string = 'foo'
  2. #[\Override] attribute          — kompilator parent method-u yoxlayır
  3. Readonly amendments             — clone + readonly property copy
  4. json_validate() function        — RAM-effektiv JSON yoxlama
  5. Dynamic class constant fetch    — ClassName::{$name}
  6. Randomizer::getBytesFromString()
  7. Command line linter improvements
  8. Deep cloning readonly-də        — __clone ilə dəyişdirmək olar
```

---

## PHP 8.4 (2024 Nov)

```
Əsas yeniliklər:
  1. Property hooks                  — get/set hook (Kotlin/C# kimi)
  2. Asymmetric visibility           — public read, private write
  3. new without parens              — new Foo()->method() birbaşa
  4. Lazy objects                    — deferred initialization
  5. array_find, array_find_key, array_all, array_any
  6. Chaining new in initializer
  7. HTMLDocument class              — yeni DOM HTML5 parser
  8. Deprecation: E_STRICT sabitləri
```

---

## Typed class constants (8.3)

```php
<?php
// ƏVVƏL (8.2)
class Config
{
    const VERSION = '1.0';   // type yox — string, int, array fərqi qaranlıq
    const MAX_USERS = 100;
    
    // Child class override edəndə yanlış tip verə bilərdi:
    // const VERSION = 1.0;  (string yerinə float — qəbul olunur!)
}

// SONRA (8.3)
class Config
{
    const string VERSION   = '1.0';
    const int    MAX_USERS = 100;
    const array  FEATURES  = ['auth', 'cache'];
    const ?string DB_HOST  = null;
}

class AppConfig extends Config
{
    // Bunu qəbul etmir — tip uyğunsuzluğu:
    // const string MAX_USERS = 'unlimited';  // TypeError
    
    // Üst class-dan tipi qorumalıdır
    const int MAX_USERS = 500;
}

// Interface-də də işləyir
interface HasVersion
{
    const string VERSION = '1.0.0';
}
```

---

## Readonly amendments

```php
<?php
// PHP 8.2: readonly property clone-da da readonly qalırdı (problem!)
class User
{
    public function __construct(
        public readonly string $name,
        public readonly array  $permissions = [],
    ) {}
}

$u1 = new User('Alice', ['read']);
$u2 = clone $u1;
// $u2->name = 'Bob';  ← ERROR (readonly)

// PHP 8.3: __clone-da readonly property dəyişdirilə bilər
class User
{
    public function __construct(
        public readonly string $name,
        public readonly array  $permissions = [],
    ) {}
    
    public function __clone(): void
    {
        // Clone-da dəyişmək olar — "deep clone" üçün vacibdir
        $this->permissions = [...$this->permissions, 'shared'];
    }
}

// With-er pattern (immutable update)
class User
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
    
    public function withEmail(string $email): self
    {
        $clone = clone $this;
        $clone->email = $email;  // 8.3-də işləyir (readonly clone içində)
        return $clone;
    }
}
```

---

## Property hooks (8.4)

```php
<?php
// Kotlin/C#-dəki computed property + validation hook
// __get/__set magic method-dan daha performantdır

class User
{
    public string $firstName = '';
    public string $lastName  = '';
    
    // GET hook — computed property
    public string $fullName {
        get => trim("{$this->firstName} {$this->lastName}");
    }
    
    // SET hook — validation
    public string $email {
        set (string $value) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new ValueError('Invalid email');
            }
            $this->email = strtolower($value);
        }
    }
    
    // Həm get, həm set
    public int $age {
        get => $this->age;
        set (int $value) {
            if ($value < 0 || $value > 150) {
                throw new ValueError('Age out of range');
            }
            $this->age = $value;
        }
    }
}

$u = new User();
$u->firstName = 'Ali';
$u->lastName  = 'Veli';
echo $u->fullName;  // "Ali Veli" (computed, heç yerdə saxlanmır)

$u->email = 'INVALID';  // ValueError
$u->email = 'Test@Example.COM';
echo $u->email;  // "test@example.com" (normalized)

// Bu __get/__set-dən niyə yaxşıdır?
//  __get/__set BÜTÜN dynamic property-lərə çalışır — slow
//  Hook yalnız həmin property-ə aid olur — fast və explicit
```

```php
<?php
// Interface-də property hook declaration
interface Person
{
    public string $fullName { get; }   // yalnız read contract
}

class Employee implements Person
{
    public function __construct(
        private string $first,
        private string $last,
    ) {}
    
    public string $fullName {
        get => "{$this->first} {$this->last}";
    }
}
```

---

## Asymmetric visibility (8.4)

```php
<?php
// public read, private write — OOP klassikası
// C#-də `public int Age { get; private set; }` kimi

class Account
{
    public private(set) float $balance = 0;
    //     ↑           ↑
    //     read        write
    
    public function deposit(float $amount): void
    {
        if ($amount <= 0) throw new ValueError('Positive only');
        $this->balance += $amount;  // daxildən OK
    }
}

$acc = new Account();
$acc->deposit(100);
echo $acc->balance;      // 100 (public read)
// $acc->balance = 999;  // ERROR — private set

// Readonly-dən fərqi:
//   readonly = yalnız constructor-da set, heç bir zaman sonra YOX
//   private(set) = daxildən istənilən method set edə bilər

// Protected set — inheritance üçün
class User
{
    public protected(set) string $role = 'guest';
}

class Admin extends User
{
    public function promote(): void
    {
        $this->role = 'admin';  // protected — extend olan class-lar OK
    }
}
```

---

## new in initializer (8.4)

```php
<?php
// 8.4-də new Foo() birbaşa method chain — mötərizə lazım deyil
// 8.3 və əvvəl:
//   (new Request())->withHeader('X', 'Y');  // mötərizə məcburi
// 8.4:
$response = new Request()->withHeader('X', 'Y');

// Method chain:
$user = new UserBuilder()
    ->withName('Ali')
    ->withEmail('a@b.com')
    ->build();
```

---

## Array functions yeni (8.4)

```php
<?php
// array_find — ilk match olan element
$users = [
    ['id' => 1, 'name' => 'Alice', 'active' => false],
    ['id' => 2, 'name' => 'Bob',   'active' => true],
    ['id' => 3, 'name' => 'Carol', 'active' => true],
];

$firstActive = array_find($users, fn($u) => $u['active']);
// ['id' => 2, 'name' => 'Bob', 'active' => true]

// array_find_key — açarı qaytarır
$key = array_find_key($users, fn($u) => $u['name'] === 'Carol');
// 2

// array_all — hamısı match?
array_all($users, fn($u) => $u['active']);  // false (Alice aktiv deyil)

// array_any — ən az biri?
array_any($users, fn($u) => $u['active']);  // true

// Əvvəl bunları necə yazırdıq?
// $firstActive = null;
// foreach ($users as $u) {
//     if ($u['active']) { $firstActive = $u; break; }
// }
// ya da:
// $firstActive = array_values(array_filter($users, fn($u) => $u['active']))[0] ?? null;
// → array_find daha oxunaqlı və performant
```

---

## Deprecation siyahısı

```
PHP 8.3 deprecations:
  - get_class() və get_parent_class() argumentsiz istifadə
  - Deprecate #[\AllowDynamicProperties] olmadan dynamic property
  - ReflectionClass::getStaticProperties() dəyişiklikləri
  - assert() ilə string expression

PHP 8.4 deprecations:
  - E_STRICT error level (tamamilə silindi)
  - mysqli implicit type conversion
  - GMP_ROUND_* sabitləri
  - DateTimeInterface::setDate() null argument
  - Implicit nullable types:
    function foo(string $x = null)   // deprecated, istifadə et:
    function foo(?string $x = null)

// Laravel 11+ PHP 8.2 minimum tələb edir
// Symfony 7+ PHP 8.2+
// PHP 8.4 — Laravel 11 / Symfony 7 dəstəyi
```

---

## Upgrade strategy

```bash
# 1. PHPStan level 9 ilə kodu skan et
vendor/bin/phpstan analyse --level=9

# 2. Rector ilə auto-upgrade
composer require --dev rector/rector
vendor/bin/rector init

# rector.php
# $rectorConfig->sets([
#     LevelSetList::UP_TO_PHP_84,
# ]);

vendor/bin/rector process app/

# 3. Test suite tam keçməlidir
vendor/bin/phpunit

# 4. Deprecation-ları log et
# php.ini
# error_reporting = E_ALL
# log_errors = On
# error_log = /var/log/php_errors.log

# 5. Production-da canary deploy
# Bir serverdə PHP 8.4 → monitor → tam rollout

# 6. Composer extensions kontrol
composer require php:^8.4
```

```json
// composer.json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.0"
    },
    "config": {
        "platform": {
            "php": "8.3.0"
        }
    }
}
```

---

## Feature-by-version xülasə

| Feature | 8.1 | 8.2 | 8.3 | 8.4 |
|---------|-----|-----|-----|-----|
| Enums | ✓ | | | |
| Readonly property | ✓ | | | |
| never return type | ✓ | | | |
| Readonly class | | ✓ | | |
| DNF types `(A&B)\|C` | | ✓ | | |
| true/false/null standalone types | | ✓ | | |
| Typed const | | | ✓ | |
| `#[\Override]` | | | ✓ | |
| json_validate() | | | ✓ | |
| Property hooks | | | | ✓ |
| Asymmetric visibility | | | | ✓ |
| array_find family | | | | ✓ |
| Lazy objects | | | | ✓ |
| new() chaining | | | | ✓ |

---

## İntervyu Sualları

- PHP 8.3-də typed class constants niyə lazımdır?
- Property hooks nədir? `__get`/`__set`-dən necə fərqlənir?
- Asymmetric visibility (`public private(set)`) readonly-dən nə ilə fərqlənir?
- PHP 8.3-də readonly + clone dəyişikliyi nə gətirdi?
- `#[\Override]` attribute nə üçündür? Niyə faydalıdır?
- `array_find` əvvəl necə yazılırdı? Performansı fərqlidirmi?
- Lazy objects (8.4) hansı pattern-i əvəzləyir?
- `json_validate()` `json_decode()`-dan nə ilə fərqlənir?
- PHP-də `never` return type hansı məqsəd üçündür?
- Readonly class (8.2) və readonly property (8.1) arasında fərq?
- DNF types nədir? Misal verin.
- PHP 8.4-ə keçid planını necə qurarsınız legacy proyektdə?
