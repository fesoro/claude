# Code Smells & Refactoring (Middle ⭐⭐)

## Mündəricat
1. [Code smell nədir?](#code-smell-nədir)
2. [Classic smells (Fowler catalog)](#classic-smells-fowler-catalog)
3. [PHP-specific smells](#php-specific-smells)
4. [Refactoring techniques](#refactoring-techniques)
5. [Extract method](#extract-method)
6. [Extract class / Move method](#extract-class--move-method)
7. [Replace conditional with polymorphism](#replace-conditional-with-polymorphism)
8. [Introduce parameter object](#introduce-parameter-object)
9. [Tool: PHPStan, Psalm, Rector](#tool-phpstan-psalm-rector)
10. [Legacy kod strategiyaları](#legacy-kod-strategiyaları)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Code smell nədir?

```
Code smell — koddakı "doğru-olmayan" əlamət.
Kent Beck & Martin Fowler tərəfindən populyarlaşdırıldı.

Fərq:
  Bug        — kod səhv işləyir
  Smell      — kod işləyir, amma düzgün YAZILMAYIB
  Anti-pattern — qurum səviyyəsində səhv qərar

Niyə smell-lərə diqqət?
  - Dəyişiklik etmək çətin olur
  - Bug yaranma ehtimalı artır
  - Onboarding yavaş
  - Test yazmaq çətin

"Smell" != "yenidən yaz"
  Bəzən kiçik refactoring, bəzən böyük.
  Xərclərlə müqayisə edin — dəyişiklik gətirsə ki, hə:
```

---

## Classic smells (Fowler catalog)

```
BLOATERS (irəri böyüklük):
  □ Long method            — 50+ sətir metod
  □ Large class            — 500+ sətir class
  □ Primitive obsession    — string, int hər yerdə (VO istifadə et)
  □ Long parameter list    — 5+ parametr
  □ Data clumps            — eyni parametr qrupu təkrar keçir

OBJECT-ORIENTATION ABUSERS:
  □ Switch statements      — polymorphism üçün ipucu
  □ Temporary field        — bəzi hallarda null olan property
  □ Refused bequest        — subclass parent metod-unu dərk etmir
  □ Alternative classes with different interfaces

CHANGE PREVENTERS:
  □ Divergent change       — bir class bir çox səbəblə dəyişir (SRP pozulur)
  □ Shotgun surgery        — bir dəyişiklik çox class-a toxunur

DISPENSABLES:
  □ Comments               — kod özü izah etməlidir
  □ Duplicate code         — DRY pozuntusu
  □ Lazy class             — çox az iş görür, silmək olar
  □ Data class             — yalnız getter/setter (behaviorless)
  □ Dead code              — heç yerdə çağırılmır
  □ Speculative generality — future "maybe" üçün layers

COUPLERS:
  □ Feature envy           — method başqa class-ın property-lərindən çox istifadə edir
  □ Inappropriate intimacy — class-lar bir-birinin internals-na çox girir
  □ Message chains         — $obj->a()->b()->c()->d()  (Law of Demeter)
  □ Middle man             — hər metod sadəcə proxy
```

---

## PHP-specific smells

```php
<?php
// SMELL 1: Array hər yerdə (primitive obsession)
function createUser(array $data) {
    // $data['name'], $data['email'] — typo risk, type-safety yoxdur
}

// DÜZGÜN:
function createUser(UserData $data) {
    // readonly property, validated
}
```

```php
<?php
// SMELL 2: God Service
class UserService
{
    public function create(...) {}       // 
    public function update(...) {}        //
    public function delete(...) {}        // bu ok
    public function sendEmail(...) {}     // <- email kənar
    public function generateReport() {}   // <- report kənar
    public function exportCsv() {}        // <- export kənar
    public function importExcel() {}      // <- import kənar
}

// 20 method, 1500 line class — parçalamaq lazımdır
// UserRegistration, UserMailer, UserReportExporter, UserBulkImporter
```

```php
<?php
// SMELL 3: Static helper addiction
class StringHelper
{
    public static function slug($s) { /* ... */ }
    public static function truncate($s, $n) { /* ... */ }
    public static function clean($s) { /* ... */ }
}
// Çox static call → test etmək çətin, mock etmək olmur
// Həll: instance service + dependency injection
```

```php
<?php
// SMELL 4: Magic method hər yerdə
class Model
{
    public function __get($key) { return $this->attributes[$key] ?? null; }
}
// IDE autocomplete yoxdur, typo bug-lar — @property PHPDoc lazım
```

```php
<?php
// SMELL 5: "Fat controller"
class OrderController
{
    public function store(Request $req)
    {
        // 1. Validation (30 sətir)
        // 2. User check (20 sətir)
        // 3. Inventory check (40 sətir)
        // 4. Payment processing (50 sətir)
        // 5. Email send (10 sətir)
        // 6. Response (10 sətir)
        // ~160 sətir controller method-u
    }
}

// DÜZGÜN: Command + Handler (action classes)
class OrderController
{
    public function store(
        StoreOrderRequest $req,
        CreateOrderAction $action,
    ): JsonResponse {
        $order = $action->handle($req->toData());
        return OrderResource::make($order)->response();
    }
}
```

```php
<?php
// SMELL 6: Train wreck (Law of Demeter)
$order->getCustomer()->getAddress()->getCity()->getName();

// DÜZGÜN:
$order->getCityName();   // facade method

// Və ya:
$cityName = $order->getCustomer()->getCityName();
```

```php
<?php
// SMELL 7: Eloquent N+1
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name;  // hər iterationda query!
}

// DÜZGÜN: eager loading
$orders = Order::with('customer')->get();
```

```php
<?php
// SMELL 8: if-elseif-elseif ladder
function getDiscount(string $userType): float
{
    if ($userType === 'guest') return 0;
    elseif ($userType === 'member') return 0.05;
    elseif ($userType === 'vip') return 0.15;
    elseif ($userType === 'premium') return 0.25;
    else return 0;
}

// DÜZGÜN: match
function getDiscount(UserType $type): float
{
    return match($type) {
        UserType::Guest   => 0,
        UserType::Member  => 0.05,
        UserType::Vip     => 0.15,
        UserType::Premium => 0.25,
    };
}

// Ya da polymorphism
interface DiscountStrategy { public function rate(): float; }
class VipDiscount implements DiscountStrategy { public function rate(): float { return 0.15; } }
```

---

## Refactoring techniques

```
Martin Fowler "Refactoring" kitabında 70+ texnika.
Ən geniş istifadə olunanlar:

  1.  Extract method / function
  2.  Extract class
  3.  Move method / Move field
  4.  Rename method / variable
  5.  Replace magic number with constant
  6.  Replace conditional with polymorphism
  7.  Introduce parameter object
  8.  Replace type code with subclass / enum
  9.  Decompose conditional
  10. Replace temp with query

KIÇIK ADDIM qaydası:
  Hər refactoring tək dəyişiklikdir.
  Sonra test işlədilir.
  Green olduqda commit.
  Növbəti addım.

"Refactor → Test → Commit → Refactor"  (nikaralmış addımlar)
```

---

## Extract method

```php
<?php
// ƏVVƏL
public function printInvoice(Invoice $invoice): void
{
    // 1. Print header
    echo "========================\n";
    echo "Invoice #{$invoice->id}\n";
    echo "Date: {$invoice->date}\n";
    echo "========================\n";
    
    // 2. Print items
    foreach ($invoice->items as $item) {
        echo sprintf(
            "%-30s %d x $%.2f\n",
            $item->name,
            $item->quantity,
            $item->price
        );
    }
    
    // 3. Print footer
    echo "========================\n";
    echo sprintf("Total: $%.2f\n", $invoice->total);
    echo "========================\n";
}

// SONRA — extract 3 method
public function printInvoice(Invoice $invoice): void
{
    $this->printHeader($invoice);
    $this->printItems($invoice);
    $this->printFooter($invoice);
}

private function printHeader(Invoice $invoice): void { /* ... */ }
private function printItems(Invoice $invoice): void { /* ... */ }
private function printFooter(Invoice $invoice): void { /* ... */ }
```

---

## Extract class / Move method

```php
<?php
// ƏVVƏL — User class çox məsuliyyət
class User
{
    public string $name;
    public string $email;
    public string $phone;
    public string $street;
    public string $city;
    public string $zipcode;
    public string $country;
    
    public function formatAddress(): string {
        return "{$this->street}, {$this->city} {$this->zipcode}, {$this->country}";
    }
}

// SONRA — Address extract
class Address
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $zipcode,
        public readonly string $country,
    ) {}
    
    public function format(): string
    {
        return "{$this->street}, {$this->city} {$this->zipcode}, {$this->country}";
    }
}

class User
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public Address $address,
    ) {}
}
```

---

## Replace conditional with polymorphism

```php
<?php
// ƏVVƏL
class Shape
{
    public function __construct(
        public string $type,
        public float $width,
        public float $height,
        public float $radius = 0,
    ) {}
    
    public function area(): float
    {
        return match($this->type) {
            'circle'    => M_PI * $this->radius ** 2,
            'square'    => $this->width ** 2,
            'rectangle' => $this->width * $this->height,
            'triangle'  => 0.5 * $this->width * $this->height,
        };
    }
}

// SONRA
abstract class Shape
{
    abstract public function area(): float;
}

class Circle extends Shape
{
    public function __construct(private float $radius) {}
    public function area(): float { return M_PI * $this->radius ** 2; }
}

class Rectangle extends Shape
{
    public function __construct(private float $width, private float $height) {}
    public function area(): float { return $this->width * $this->height; }
}
```

---

## Introduce parameter object

```php
<?php
// ƏVVƏL — long parameter list
function createOrder(
    int $customerId,
    array $items,
    string $currency,
    string $street,
    string $city,
    string $zipcode,
    string $country,
    string $promoCode = null,
    float $discount = 0,
    string $paymentMethod = 'card',
) {
    // 10 parametr!
}

// SONRA
readonly class OrderData
{
    public function __construct(
        public int $customerId,
        public array $items,
        public string $currency,
        public Address $shippingAddress,
        public ?string $promoCode,
        public float $discount,
        public PaymentMethod $paymentMethod,
    ) {}
}

function createOrder(OrderData $data) {
    // ...
}
```

---

## Tool: PHPStan, Psalm, Rector

```bash
# PHPStan — static analyzer
composer require --dev phpstan/phpstan
vendor/bin/phpstan analyse --level=max src/

# phpstan.neon
parameters:
    level: max
    paths:
        - src/
    excludePaths:
        - src/Legacy/
    
# Level 0-9: 0 = basit, 9 = strictest

# Psalm — alternative analyzer
composer require --dev vimeo/psalm
vendor/bin/psalm --init
vendor/bin/psalm

# Rector — automatic refactoring
composer require --dev rector/rector

# rector.php
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . '/src']);
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_83,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
    ]);
};

# Run:
vendor/bin/rector process src/

# PHP CS Fixer — style
composer require --dev friendsofphp/php-cs-fixer
vendor/bin/php-cs-fixer fix

# PHPMD — mess detector (cyclomatic complexity)
composer require --dev phpmd/phpmd
vendor/bin/phpmd src/ text cleancode,codesize,design

# PHPCPD — copy-paste detector
composer require --dev sebastian/phpcpd
vendor/bin/phpcpd src/
```

---

## Legacy kod strategiyaları

```
Michael Feathers — "Working Effectively with Legacy Code"

"Legacy code" = test-siz kod (yəni dəyişdirmək qorxuludur).

Strategiya:
  1. Karakterization test yaz (mövcud davranışı qoru)
  2. Sprout method / class (yeni funksionalliq ayrı yerdə)
  3. Wrap method — köhnə davranışı wrap et
  4. Seam (kod dəyişdirmədən injection imkanı)
  5. Extract-and-override (subclass-da method override et)

Strangler Fig pattern (Martin Fowler):
  Yeni kod köhnənin ətrafında yavaş-yavaş böyüyür
  Köhnə "strangled" olur (son nöqtədə silinir)
  Bax 47-strangler-fig-pattern.md

Prioritet:
  ✗ Bütün legacy-ni birdən yenidən yazma
  ✓ Dəyişir olan hissələrə fokuslan
  ✓ Test qapsma yüksək tutarlıqla davam edir
  ✓ PR-lərdə "Boy Scout rule" — kodu daha təmiz burax
```

---

## Anti-Pattern Nə Zaman Olur?

**1. Test olmadan refactoring etmək**
"Bu kodu təmizləyim" deyib heç bir test yazmadan refactor başlamaq — bir şey sındırsan bilmirsən, "əvvəlcə işləyirdi, indi işləmir" sindromu. Refactoring workflow: əvvəlcə characterization test yaz (mövcud davranışı qoru), sonra refactor et, test hələ keçirsə uğurlusun. Test yoxdursa, refactoring deyil, yenidən yazmaqdır.

**2. "Clean code" üçün premature optimization**
Hər yerdə design pattern tətbiq etmək, sadə bir funksiyadan Strategy + Factory + Visitor zinciri çıxarmaq — "amma bu daha SOLID-dir" deyərək. Clean code mürəkkəblik əlavə etmək deyil — dəyişiklik asanlığıdır. Əgər dəyişiklik edəcəyin yer bəlli deyilsə, abstraksiya vaxtından əvvəldir. "Make it work, make it right, make it fast" — bu sırayla.

```php
// YANLIŞ — premature abstraction
interface OrderCreationStrategy {
    public function create(OrderData $data): Order;
}

class StandardOrderCreationStrategy implements OrderCreationStrategy { ... }
class PriorityOrderCreationStrategy implements OrderCreationStrategy { ... }

class OrderCreationStrategyFactory {
    public function make(string $type): OrderCreationStrategy { ... }
}

// Hal-hazırda yalnız bir növ sifariş var. Bu 3 class lazımsızdır.

// DOĞRU — sadə başla
class OrderService {
    public function create(OrderData $data): Order {
        // Sadə, anlaşıqlı, dəyişdirmək asan
    }
}
// İkinci növ order lazım olduqda THEN refactor et.
```

---

## İntervyu Sualları

- Code smell və bug arasında fərq nədir?
- "Long method"-u necə aşkarlayırsınız? Nə qədər uzun?
- "Primitive obsession" nədir? Nümunə verin.
- "Feature envy" nədir və necə həll edilir?
- Law of Demeter nədir? Train wreck anti-pattern-ə necə bağlıdır?
- "Replace conditional with polymorphism" nə vaxt həddən artıq engineering olur?
- PHPStan level niyə "max"-a çatdırmaq lazımdır?
- Rector ilə hansı refactoring-ləri avtomatlaşdırmaq olar?
- Legacy kod üçün "characterization test" nədir?
- Strangler Fig pattern legacy rewrite-da necə istifadə olunur?
- Dead code necə aşkarlanır və silinir?
- "God class" — nə qədər xətt/metod sayılır?

---

## Əlaqəli Mövzular

- [SOLID Principles](../architecture/02-solid-principles.md) — smell-lərin çoxu SOLID pozuntusu kimi aşkarlanır
- [Technical Debt](05-technical-debt.md) — smell-lərin yığılması texniki borcun formalaşması deməkdir
- [Service Layer](../laravel/02-service-layer.md) — Fat Controller smell-ini həll edən yanaşma
- [Repository Pattern](../laravel/01-repository-pattern.md) — Eloquent-in hər yerə sıçmasını önləyir
