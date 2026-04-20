# PHP Magic Methods — Deep Dive

## Mündəricat
1. [Magic method nədir?](#magic-method-nədir)
2. [Bütün magic methods-ların siyahısı](#bütün-magic-methods-ların-siyahısı)
3. [__construct & __destruct](#__construct--__destruct)
4. [__get, __set, __isset, __unset](#__get-__set-__isset-__unset)
5. [__call & __callStatic](#__call--__callstatic)
6. [__invoke](#__invoke)
7. [__toString](#__tostring)
8. [__sleep, __wakeup, __serialize, __unserialize](#__sleep-__wakeup-__serialize-__unserialize)
9. [__clone](#__clone)
10. [__debugInfo, __set_state](#__debuginfo-__set_state)
11. [Performance təsiri](#performance-təsiri)
12. [Real-world use cases](#real-world-use-cases)
13. [Pitfalls & Best Practices](#pitfalls--best-practices)
14. [İntervyu Sualları](#intervyu-sualları)

---

## Magic method nədir?

```
Magic methods — PHP-nin xüsusi hallarda avtomatik çağırdığı methodlardır.
Hamısı `__` (iki alt xətt) ilə başlayır.

Onlar "hook" rolunu oynayır:
  - Obyekt yaradılanda (construct, destruct)
  - Dəyər oxunanda/yazılanda (get, set)
  - Method çağırılanda (call)
  - Obyekt string-ə çevriləndə (toString)
  - Obyekt callable kimi istifadə olunanda (invoke)
  - Serialize olunanda (sleep, wakeup)

Niyə var?
  - Proxy pattern
  - ORM (Eloquent dynamic property access)
  - Collection class-ları
  - Dependency injection container
  - Fluent API
```

---

## Bütün magic methods-ların siyahısı

| Method | Çağrılan | Misal |
|--------|---------|-------|
| `__construct` | `new Foo()` | Konstruktor |
| `__destruct` | obyekt silinəndə | Cleanup |
| `__get` | `$obj->undefined` | Dynamic getter |
| `__set` | `$obj->x = 1` (mövcud deyil) | Dynamic setter |
| `__isset` | `isset($obj->x)` | Dynamic isset |
| `__unset` | `unset($obj->x)` | Dynamic unset |
| `__call` | `$obj->undefined()` | Dynamic method |
| `__callStatic` | `Foo::undefined()` | Dynamic static |
| `__toString` | `(string) $obj` | String çevirmə |
| `__invoke` | `$obj()` | Callable |
| `__clone` | `clone $obj` | Copy hook |
| `__sleep` | `serialize($obj)` | Serialize qabağı |
| `__wakeup` | `unserialize($str)` | Unserialize sonrası |
| `__serialize` | PHP 7.4+ | Yeni serialize |
| `__unserialize` | PHP 7.4+ | Yeni unserialize |
| `__set_state` | `var_export()` | State bərpa |
| `__debugInfo` | `var_dump()` | Debug output |

---

## __construct & __destruct

```php
<?php
class DbConnection
{
    private \PDO $pdo;
    
    public function __construct(string $dsn, string $user, string $pass)
    {
        $this->pdo = new \PDO($dsn, $user, $pass);
        echo "Connection opened\n";
    }
    
    public function __destruct()
    {
        // Obyekt reference count 0 olanda
        // və ya script bitəndə çağırılır
        $this->pdo = null;  // close connection
        echo "Connection closed\n";
    }
}

// Scope bitəndə __destruct işləyir
function test() {
    $db = new DbConnection(...);
    // scope bitəndə $db destruct
}
test();

// GOTCHA: Exception __destruct içində problemli
class Foo {
    public function __destruct() {
        throw new \Exception('bad');  // fatal error!
    }
}

// GOTCHA: Circular reference — GC lazımdır
// $a->b = $b;
// $b->a = $a;
// reference count heç vaxt 0 olmaz — __destruct işləmir
// gc_collect_cycles() əllə çağırılmalıdır
```

---

## __get, __set, __isset, __unset

```php
<?php
// Property mövcud DEYİLSƏ çağırılır (private/protected də keçir!)
class User
{
    private array $attributes = [];
    
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }
    
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }
    
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }
    
    public function __unset(string $name): void
    {
        unset($this->attributes[$name]);
    }
}

$u = new User();
$u->name = 'Ali';        // __set('name', 'Ali')
echo $u->name;           // __get('name') → 'Ali'
isset($u->name);         // __isset('name') → true
unset($u->name);         // __unset('name')

// YADDA SAXLA:
//   __get YALNIZ property mövcud deyilsə çağırılır.
//   public $name = 'X' varsa — __get-ə getmir.
//   Private/protected property xarici scope-dan → __get çağırılır!

class Foo {
    private int $count = 10;
}
$f = new Foo();
// $f->count → "Uninitialized" xəbərdarlığı (PHP 8+) ya da __get
```

---

## __call & __callStatic

```php
<?php
// Laravel Eloquent klassikası — dynamic query method-lar
class Query
{
    private array $wheres = [];
    
    public function __call(string $method, array $args): self
    {
        // whereName, whereEmail → where('name', ...)
        if (str_starts_with($method, 'where')) {
            $field = strtolower(substr($method, 5));
            $this->wheres[] = [$field, '=', $args[0]];
            return $this;
        }
        
        throw new \BadMethodCallException("Unknown method: $method");
    }
    
    public static function __callStatic(string $method, array $args): mixed
    {
        // User::find(42) → User::query()->find(42)
        $instance = new static();
        return $instance->$method(...$args);
    }
}

$q = new Query();
$q->whereName('Ali')->whereEmail('a@b.com');
// __call('whereName', ['Ali']), __call('whereEmail', ['a@b.com'])
```

---

## __invoke

```php
<?php
// Obyekti funksiya kimi çağırmaq
class Adder
{
    public function __construct(private int $base) {}
    
    public function __invoke(int $x): int
    {
        return $this->base + $x;
    }
}

$add5 = new Adder(5);
echo $add5(10);  // 15
echo $add5(20);  // 25

// callable tip yoxlaması
is_callable($add5);        // true

// array_map ilə
array_map($add5, [1, 2, 3]);  // [6, 7, 8]

// Laravel action klassikası — "Single Action Controller"
class UpdateUserController
{
    public function __invoke(Request $req, int $id): JsonResponse
    {
        // ...
    }
}

// Route::put('/users/{id}', UpdateUserController::class);
```

---

## __toString

```php
<?php
class Money
{
    public function __construct(
        private int $cents,
        private string $currency = 'USD',
    ) {}
    
    public function __toString(): string
    {
        return sprintf('%.2f %s', $this->cents / 100, $this->currency);
    }
}

$m = new Money(1999);
echo $m;              // "19.99 USD"
$s = (string) $m;     // "19.99 USD"
$s = "Price: $m";     // "Price: 19.99 USD"

// GOTCHA — PHP 7.3 və əvvəli:
//   __toString() içində Exception throw etmək fatal error idi.
// PHP 7.4+:
//   Exception OK.

// PHP 8+ Stringable interface
interface Stringable
{
    public function __toString(): string;
}

function printLabel(Stringable|string $label): void
{
    echo $label;
}
printLabel($m);       // obyekt
printLabel('hello');  // string
```

---

## __sleep, __wakeup, __serialize, __unserialize

```php
<?php
// ƏVVƏLKİ API (__sleep / __wakeup)
class Connection
{
    private \PDO $pdo;
    private string $dsn;
    
    public function __sleep(): array
    {
        // Hansı property-lər serialize olunsun (array key-lər)
        return ['dsn'];  // $pdo serialize olunmaz (resource)
    }
    
    public function __wakeup(): void
    {
        // Unserialize olandan sonra — resource-ları yenidən qur
        $this->pdo = new \PDO($this->dsn);
    }
}

// YENİ API (PHP 7.4+) — tövsiyə olunan
class Connection
{
    public function __serialize(): array
    {
        return ['dsn' => $this->dsn];
    }
    
    public function __unserialize(array $data): void
    {
        $this->dsn = $data['dsn'];
        $this->pdo = new \PDO($this->dsn);
    }
}

// Niyə yeni API?
//   __sleep sadəcə property adları — dəyər dəyişmək olmur
//   __serialize istənilən array qaytara bilər — daha elastik
//   Constructor çağırılmır (wakeup-da da çağırılmırdı) — state tam kontrol

$c = new Connection('mysql:host=localhost');
$s = serialize($c);
$c2 = unserialize($s);  // __unserialize çağırılır
```

```
SECURITY: unserialize() istifadəçi input-u ilə TƏHLÜKƏLİDİR!
  Object injection attacks — malicious serialized string
  Həlli:
    unserialize($data, ['allowed_classes' => [User::class]]);
    ya da JSON istifadə et (safe-by-default)
```

---

## __clone

```php
<?php
// clone $obj — shallow copy yaradır.
// Obyekt property-ləri də shallow — reference paylaşılır!

class Address
{
    public function __construct(public string $city) {}
}

class User
{
    public function __construct(
        public string $name,
        public Address $address,
    ) {}
    
    public function __clone(): void
    {
        // Deep clone — nested obyekt də kopyalansın
        $this->address = clone $this->address;
    }
}

$u1 = new User('Ali', new Address('Baku'));
$u2 = clone $u1;
$u2->address->city = 'Istanbul';

echo $u1->address->city;  // __clone yoxdursa: 'Istanbul' (bug!)
                          // __clone varsa: 'Baku' (doğru)

// PHP 8.3+: __clone readonly property-yə də yaza bilər
class Event
{
    public function __construct(
        public readonly string $name,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
    
    public function __clone(): void
    {
        $this->occurredAt = new \DateTimeImmutable();  // yeni timestamp
    }
}
```

---

## __debugInfo, __set_state

```php
<?php
// __debugInfo — var_dump() outputunu fərdiləşdir
class User
{
    private string $password = 'secret-hash';
    public string  $name     = 'Ali';
    
    public function __debugInfo(): array
    {
        return [
            'name'     => $this->name,
            'password' => '***',   // sensitive data gizlət
        ];
    }
}

var_dump(new User());
// object(User)#1 (2) {
//   ["name"] => "Ali"
//   ["password"] => "***"
// }

// __set_state — var_export() tərsinə
class Point
{
    public function __construct(public int $x, public int $y) {}
    
    public static function __set_state(array $data): static
    {
        return new self($data['x'], $data['y']);
    }
}

$p = new Point(1, 2);
$code = var_export($p, true);
// Point::__set_state(array('x' => 1, 'y' => 2))

eval('$restored = ' . $code . ';');
// $restored → Point instance
```

---

## Performance təsiri

```
Magic method-lar normal method-dan YAVAŞDIR.
  Normal method call:     ~50 ns
  Magic method call:      ~150-300 ns (3-6×)

Niyə?
  - PHP hər dəfə property/method mövcudluğunu yoxlayır
  - Magic method tapılır → reflection kimi çağırılır
  - Opcache optimize edə bilməz (dynamic dispatch)

Benchmark (1M iteration):
  $obj->name (public):           10 ms
  $obj->name (via __get):        45 ms (4.5×)
  
  $obj->method() (real):         15 ms
  $obj->method() (via __call):   80 ms (5.3×)

Nə vaxt istifadə etməməli?
  - Hot path-lərdə (loop daxili, hər request-də 1000 dəfə)
  - Model property access (Eloquent bunun üçün cache edir — internal optimization)

Nə vaxt OK-dir?
  - Setup / bootstrap
  - Fluent API (ayda bir-iki dəfə çağırılan)
  - Test double / mock
```

---

## Real-world use cases

```php
<?php
// 1. Property bag (Laravel Request parameters)
class ParameterBag
{
    public function __construct(private array $params) {}
    
    public function __get(string $name): mixed
    {
        return $this->params[$name] ?? null;
    }
    
    public function __isset(string $name): bool
    {
        return isset($this->params[$name]);
    }
}

$req = new ParameterBag(['id' => 42]);
echo $req->id;  // 42

// 2. Proxy / Lazy loading
class LazyProxy
{
    private ?object $real = null;
    
    public function __construct(private \Closure $factory) {}
    
    public function __call(string $method, array $args): mixed
    {
        $this->real ??= ($this->factory)();
        return $this->real->$method(...$args);
    }
}

$lazy = new LazyProxy(fn() => new HeavyService());
// HeavyService YARANMIR — $lazy->something() çağırılana qədər
$lazy->doWork();  // indi yaranır

// 3. Function composition (callable obj)
class Pipeline
{
    private array $steps = [];
    
    public function __construct(array $steps) { $this->steps = $steps; }
    
    public function __invoke(mixed $input): mixed
    {
        return array_reduce(
            $this->steps,
            fn($carry, $step) => $step($carry),
            $input
        );
    }
}

$processor = new Pipeline([
    'trim',
    'strtolower',
    fn($s) => str_replace(' ', '-', $s),
]);

echo $processor('  HELLO WORLD  ');  // "hello-world"
```

---

## Pitfalls & Best Practices

```
❌ Pitfalls
  - __get hər "missing" property üçün işləyir — typo-ları gizlədir
  - __call method typo-larını gizlədir (method_exists istifadə et)
  - Magic method exception throw edə bilər — __get-də return type mismatch
  - __sleep array-də olmayan property serialize olunmur
  - unserialize() RCE vulnerability — allowed_classes məcburi
  - __construct return type olmur — Laravel "void" işarələyir
  - Magic method-lar autoload/opcache-də yavaş
  - IDE autocomplete işləmir — PHPDoc @property lazım

✓ Best Practices
  - PHPDoc @property və @method annotate et
  - Strict types (declare(strict_types=1))
  - __get/set əvəzinə explicit getter/setter istifadə et (mümkündürsə)
  - __toString-də heç vaxt DB sorğu etmə
  - __destruct-də exception throw etmə
  - __sleep yerinə __serialize (PHP 7.4+)
  - unserialize trust boundary yoxdur — input-a etibar etmə
```

```php
<?php
// PHPDoc annotate — IDE/static analyzer üçün
/**
 * @property string $name
 * @property-read int $id
 * @method User whereName(string $name)
 * @method static User find(int $id)
 */
class User extends Model { }
```

---

## İntervyu Sualları

- `__get` niyə yavaşdır? Hansı daxili mexanizmlə işləyir?
- `__call` və `__callStatic` arasındakı fərq nədir?
- Circular reference-də `__destruct` niyə çağırılmaya bilər?
- `__sleep` vs `__serialize` — fərq və üstünlük?
- `unserialize()` niyə təhlükəlidir? Necə təhlükəsiz edilir?
- `__clone` shallow/deep copy məsələsi nədir?
- `Stringable` interface-i PHP 8-də niyə əlavə olundu?
- `__invoke`-un tipik istifadə halları?
- Eloquent Model-də magic method-lar necə işləyir?
- Laravel Eloquent `$user->name = 'X'` arxada nə edir?
- Magic method-lar IDE autocomplete üçün niyə pisdir?
- `method_exists` və `__call` birgə nə vaxt istifadə olunur?
