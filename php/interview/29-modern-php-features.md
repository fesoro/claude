# Modern PHP 8.x Features (Senior)

## Mündəricat
1. PHP 8.0 features
2. PHP 8.1 features
3. PHP 8.2 features
4. PHP 8.3 features
5. PHP 8.4 features
6. Sual-cavab seti

---

## 1. PHP 8.0 (2020)

**S: PHP 8.0 ilə gələn ən vacib feature-lər nələrdir?**
C: 
- Named arguments
- Constructor property promotion
- Match expression
- Nullsafe operator (`?->`)
- Union types
- Attributes (annotation əvəzinə)
- JIT compiler (FPM-də fayda az)
- Throw expression-də

```php
// Constructor promotion
class User {
    public function __construct(
        private string $name,
        private string $email,
        private int $age = 18,
    ) {}
}

// Match
$result = match($status) {
    'active', 'pending' => 'OK',
    'suspended' => 'Blocked',
    default => 'Unknown',
};

// Nullsafe
$city = $user?->address?->city ?? 'Unknown';

// Named arguments
sendEmail(to: 'a@b.com', subject: 'Hi', body: '...');
```

---

## 2. PHP 8.1 (2021)

**S: Enum-lar nə üçün əlavə edildi?**
C: Type-safe sabit dəyər toplusu üçün. Əvvəl `class { const X = 'x' }` simulyasiyası vardı, indi native dil dəstəyi.

**S: Backed Enum və Pure Enum fərqi?**
C: Pure enum yalnız case adına malikdir. Backed enum (`: string` və ya `: int`) dəyərə bağlıdır — DB və JSON-da istifadə olunur.

**S: Readonly property nə vaxt istifadə olunur?**
C: Value Object, DTO, immutable obyektlər üçün. Constructor-dan sonra dəyər dəyişdirilməz.

**S: First-class callable syntax nədir?**
C: `[$obj, 'method'](...args)` əvəzinə `$obj->method(...)` ilə closure yaratmaq.

```php
// First-class callable
$users->map($transformer->transform(...));

// Never return type
function abort(): never {
    throw new Exception();
}

// Intersection types
function bind(Countable&Stringable $obj): void { /* ... */ }
```

---

## 3. PHP 8.2 (2022)

**S: Readonly class nə deməkdir?**
C: Class-ın bütün property-ləri readonly olur. DTO/VO yazmaq daha qısa.

**S: DNF (Disjunctive Normal Form) types nədir?**
C: Union və intersection birgə: `(A&B)|null`.

**S: PHP 8.2-də nə deprecated oldu?**
C: Dynamic property (class-da declare edilməmiş `$obj->newProp = 1`). `#[\AllowDynamicProperties]` attribute ilə icazə vermək olar.

```php
readonly class UserDto {
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}

// DNF types
function process((Iterator&Countable)|array $data): void { }
```

---

## 4. PHP 8.3 (2023)

**S: Typed class constants nə üçündür?**
C: Const-ların tipi yoxlanır, child class override edəndə uyğunluq tələb olunur.

**S: `#[\Override]` attribute nə edir?**
C: Compile-time check — method parent class-da MƏCBURI olmalıdır. Refactoring-də typo tutar.

**S: `json_validate()` `json_decode()`-dan necə fərqlənir?**
C: Memory az istifadə edir — yalnız "valid JSON-dur?" yoxlayır, parse etmir.

```php
class Config {
    const string VERSION = '1.0';
    const int MAX_USERS = 100;
}

class MyController extends BaseController {
    #[\Override]
    public function index(): Response { /* ... */ }
}

if (json_validate($input)) {
    $data = json_decode($input, true);
}
```

---

## 5. PHP 8.4 (2024)

**S: Property hooks (8.4) nədir? `__get`/`__set`-dan necə fərqlənir?**
C: Per-property get/set logic. `__get` bütün missing property-yə işləyir (slow), property hook explicit və faster.

**S: Asymmetric visibility nədir?**
C: `public private(set)` — kənardan oxunur, daxildən yazılır. `readonly`-dan fərqi: daxili method istənilən vaxt dəyişdirə bilər.

**S: `array_find` nə üçündür?**
C: İlk match olan element. Əvvəl `array_filter()->first()` yazılırdı.

```php
class Product {
    public string $sku;
    
    public string $skuFormatted {
        get => strtoupper($this->sku);
    }
    
    public string $name {
        set (string $value) {
            $this->name = trim($value);
        }
    }
}

class Account {
    public private(set) float $balance = 0;
}

$first = array_find($items, fn($i) => $i->active);
$any = array_any($items, fn($i) => $i->active);
$all = array_all($items, fn($i) => $i->active);
```

---

## 6. Sual-cavab seti (Yenilik-fokus)

**S: PHP 8 match-in switch-dən üstünlüyü nədir?**
C: Strict comparison, expression (assign edilə bilər), no fallthrough, exhaustive (PHPStan səviyyə 5+).

**S: Named arguments nə vaxt yaxşıdır?**
C: 5+ optional parametrli function-lar üçün. Boolean parametr-lər üçün xüsusilə oxunaqlı.

**S: Constructor promotion-un mənfi tərəfi varmı?**
C: Visibility eyni olmalıdır (bütün public ya bütün private). Property doc-block çətinləşir.

**S: Readonly class miras qoymağa imkan verirmi?**
C: Yalnız readonly class extend edə bilir. Non-readonly child class olmaz.

**S: PHP 8 attribute-ları annotation-dan necə fərqlənir?**
C: Native dil dəstəyi (parsing yox), reflection ilə oxunur, type-safe, IDE dəstəyi.

**S: JIT PHP-də web-da niyə az fayda verir?**
C: Web request I/O-bound (DB, network), JIT yalnız CPU-bound kod sürətləndirir. CLI batch processing-də fayda var.

**S: `never` return type harada istifadə olunur?**
C: Function həmişə exception throw edir və ya exit() çağırır. Static analyzer dead code aşkarlamağa kömək edir.

**S: Property hook-lar performance-a necə təsir edir?**
C: `__get`/`__set` magic-dən sürətli, normal property-dən bir az yavaş. Validation/transformation lazım olmadıqda istifadə etmə.

**S: `readonly` property-də array necə davranır?**
C: Array reference deyil, value-dur. Amma içindəki obyekt mutable qala bilər.

**S: PHP 8.4 lazy objects nə üçün lazımdır?**
C: Heavy obyekt yaradılmasını ilk istifadəyə qədər təxir et. Doctrine proxy əvəzinə native dəstək.

**S: Typed const inheritance-də necə çalışır?**
C: Override edilərsə eyni tipdə olmalıdır (variance qaydası). String → int dəyişmək olmaz.

**S: PHP 8 attribute-ları runtime-da necə oxunur?**
C: `(new ReflectionClass($obj))->getAttributes()` → `getAttributes()->newInstance()`.

**S: PHP 8.0-dan sonra `null` parametr default niyə deprecated oldu?**
C: `function f(string $s = null)` istifadəçini çaşdırır. Açıq `?string` yazmaq lazımdır.

**S: `enum` interface implement edə bilirmi?**
C: Bəli. Polymorphic davranış üçün ideal — hər case fərqli implementation versə də.

**S: Strict types-də `int` parametr-ə `float` qəbul olunurmu?**
C: Xeyr. Strict mode-da type coercion söndürülür.

**S: Match `default` arm yazmasaq nə olur?**
C: `UnhandledMatchError` runtime-da. PHPStan compile-time tutar.

**S: `readonly` ilə `final` arasında fərq?**
C: `readonly` property — dəyər. `final` class — extend olunmaz, `final` method — override olunmaz.

**S: `#[Attribute(Attribute::TARGET_METHOD)]` niyə vacibdir?**
C: Attribute hara tətbiq oluna bilər (class, method, property, parameter). Səhv yerdə istifadə → reflection error.

**S: PHP 8.1-də fibers nə fayda verdi?**
C: Cooperative multitasking. Async kod (ReactPHP, Amphp, Swoole) sync kimi yazılır, blocking olmadan.

**S: `array_is_list()` nə vaxt true qaytarır?**
C: Array sıfırdan başlayan sıralı integer key-lərdir (associative deyil).
