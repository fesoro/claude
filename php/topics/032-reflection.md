# Reflection API və onun Laravel-də İstifadəsi (Middle)

## Mündəricat
1. [Reflection API nədir?](#reflection-api-nədir)
2. [ReflectionClass](#reflectionclass)
3. [ReflectionMethod](#reflectionmethod)
4. [ReflectionProperty](#reflectionproperty)
5. [ReflectionParameter](#reflectionparameter)
6. [ReflectionFunction](#reflectionfunction)
7. [Reflection ilə Class Metadata Oxuma](#reflection-ilə-class-metadata-oxuma)
8. [Private/Protected Property-lərə Daxil Olma](#privateprotected-propertylərə-daxil-olma)
9. [Method Parametrlərini Analiz Etmə](#method-parametrlərini-analiz-etmə)
10. [PHP Attributes (PHP 8.0+) və Reflection](#php-attributes-php-80-və-reflection)
11. [Laravel-də Reflection İstifadəsi](#laraveldə-reflection-istifadəsi)
12. [Laravel Service Container Internals](#laravel-service-container-internals)
13. [Auto-Wiring Necə İşləyir](#auto-wiring-necə-işləyir)
14. [Custom Attribute Yaratma](#custom-attribute-yaratma)
15. [Reflection Performance Impact](#reflection-performance-impact)
16. [Real-World Nümunələr](#real-world-nümunələr)
17. [İntervyu Sualları](#intervyu-sualları)

---

## Reflection API nədir?

Reflection API PHP-nin built-in funksionallığıdır və runtime zamanı class-lar, interface-lər, function-lar, method-lar, property-lər və parametrlər haqqında məlumat almağa imkan verir. Başqa sözlə, kodunuz öz strukturunu "güzgüdə" görə bilir — buna **introspection** deyilir.

Reflection API-nin əsas məqsədləri:
- Class-ın hansı method-ları, property-ləri olduğunu öyrənmək
- Method-ların parametrlərini, type hint-lərini analiz etmək
- Private/protected üzvlərə runtime zamanı daxil olmaq
- Attribute-ları (annotation-ları) oxumaq
- Dependency Injection container-ləri üçün auto-wiring həyata keçirmək

*- Dependency Injection container-ləri üçün auto-wiring həyata keçirmək üçün kod nümunəsi:*
```php
// Bu kod Reflection API-nin əsas istifadəsini göstərir
<?php

// Sadə bir class nümunəsi
class User
{
    private string $name;
    protected int $age;
    public string $email;

    public function __construct(string $name, int $age, string $email)
    {
        $this->name = $name;
        $this->age = $age;
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function formatName(): string
    {
        return strtoupper($this->name);
    }
}

// Reflection ilə bu class haqqında məlumat alaq
$reflection = new ReflectionClass(User::class);

echo $reflection->getName();           // "User"
echo $reflection->getFileName();       // Faylın yolu
echo $reflection->getStartLine();      // Class-ın başladığı sətir
echo $reflection->getEndLine();        // Class-ın bitdiyi sətir
```

**Niyə Reflection lazımdır?**

1. **Framework-lar** — Laravel, Symfony kimi framework-lar Reflection ilə dependency-ləri avtomatik resolve edir
2. **Testing** — Private method-ları test etmək üçün (baxmayaraq ki, bu mübahisəlidir)
3. **Code generation** — Avtomatik kod generasiya etmək üçün
4. **Documentation** — Avtomatik sənədləşdirmə alətləri
5. **Serialization** — Object-ləri serialize/deserialize etmək

---

## ReflectionClass

`ReflectionClass` bir class haqqında tam məlumat verir — method-lar, property-lər, constant-lar, interface-lər, parent class və s.

*`ReflectionClass` bir class haqqında tam məlumat verir — method-lar, p üçün kod nümunəsi:*
```php
// Bu kod ReflectionClass ilə class haqqında tam məlumat almağı göstərir
<?php

interface Notifiable
{
    public function notify(string $message): void;
}

abstract class BaseModel
{
    protected string $table;

    abstract public function save(): bool;
}

class Product extends BaseModel implements Notifiable
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    private int $id;
    protected string $name;
    public float $price;
    private static int $instanceCount = 0;

    public function __construct(int $id, string $name, float $price)
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        self::$instanceCount++;
    }

    public function save(): bool
    {
        return true;
    }

    public function notify(string $message): void
    {
        echo $message;
    }

    private function calculateDiscount(float $percentage): float
    {
        return $this->price * ($percentage / 100);
    }

    public static function getInstanceCount(): int
    {
        return self::$instanceCount;
    }
}

// === ReflectionClass istifadəsi ===

$ref = new ReflectionClass(Product::class);

// Əsas məlumatlar
echo $ref->getName();              // "Product"
echo $ref->getShortName();         // "Product" (namespace olmadan)
echo $ref->getNamespaceName();     // "" (namespace varsa göstərər)
echo $ref->getFileName();          // Faylın tam yolu
echo $ref->isAbstract();           // false
echo $ref->isFinal();              // false
echo $ref->isInstantiable();       // true
echo $ref->isInterface();          // false
echo $ref->isInternal();           // false (built-in deyil)
echo $ref->isUserDefined();        // true

// Parent class
$parent = $ref->getParentClass();
echo $parent->getName();           // "BaseModel"

// Interface-lər
$interfaces = $ref->getInterfaces();
foreach ($interfaces as $interface) {
    echo $interface->getName();    // "Notifiable"
}

// İmplements interface yoxlama
echo $ref->implementsInterface(Notifiable::class); // true

// Constant-lar
$constants = $ref->getConstants();
// ['STATUS_ACTIVE' => 'active', 'STATUS_INACTIVE' => 'inactive']

$refConstant = $ref->getReflectionConstant('STATUS_ACTIVE');
echo $refConstant->getValue();     // 'active'
echo $refConstant->isPublic();     // true

// Method-lar
$methods = $ref->getMethods();
foreach ($methods as $method) {
    echo $method->getName() . ' - ' . ($method->isPublic() ? 'public' : 'non-public');
}

// Yalnız public method-lar
$publicMethods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

// Property-lər
$properties = $ref->getProperties();
foreach ($properties as $prop) {
    echo $prop->getName() . ' - ' . $prop->getType()->getName();
}

// Object yaratma (constructor istifadə etmədən)
$product = $ref->newInstanceWithoutConstructor();
// Constructor çağırılmadan boş Product object yaradılır

// Constructor ilə yaratma
$product = $ref->newInstanceArgs([1, 'Laptop', 999.99]);

// Constructor ilə yaratma (named arguments deyil, sıralı)
$product = $ref->newInstance(1, 'Laptop', 999.99);
```

### Class Hierarchy Analizi

*Class Hierarchy Analizi üçün kod nümunəsi:*
```php
// Bu kod Reflection ilə class irsiyyət iyerarxiyasını analiz etməyi göstərir
<?php

// Bütün parent class-ları tapmaq
function getAllParentClasses(string $className): array
{
    $parents = [];
    $ref = new ReflectionClass($className);

    while ($parent = $ref->getParentClass()) {
        $parents[] = $parent->getName();
        $ref = $parent;
    }

    return $parents;
}

// Bütün trait-ləri tapmaq (parent class-lar daxil)
function getAllTraits(string $className): array
{
    $traits = [];
    $ref = new ReflectionClass($className);

    // Öz trait-ləri
    foreach ($ref->getTraits() as $trait) {
        $traits[] = $trait->getName();
    }

    // Parent class-ların trait-ləri
    while ($parent = $ref->getParentClass()) {
        foreach ($parent->getTraits() as $trait) {
            $traits[] = $trait->getName();
        }
        $ref = $parent;
    }

    return array_unique($traits);
}
```

---

## ReflectionMethod

`ReflectionMethod` bir class method-u haqqında ətraflı məlumat verir.

*`ReflectionMethod` bir class method-u haqqında ətraflı məlumat verir üçün kod nümunəsi:*
```php
// Bu kod ReflectionMethod ilə metodları analiz etməyi göstərir
<?php

class OrderService
{
    public function createOrder(
        User $user,
        array $items,
        ?string $couponCode = null,
        float $discount = 0.0
    ): Order {
        // ...
        return new Order();
    }

    protected function validateItems(array $items): bool
    {
        return count($items) > 0;
    }

    private static function generateOrderNumber(): string
    {
        return 'ORD-' . uniqid();
    }

    public function __toString(): string
    {
        return 'OrderService';
    }
}

// === ReflectionMethod istifadəsi ===

$method = new ReflectionMethod(OrderService::class, 'createOrder');

// Əsas məlumatlar
echo $method->getName();                // "createOrder"
echo $method->getDeclaringClass()->getName(); // "OrderService"
echo $method->isPublic();               // true
echo $method->isProtected();            // false
echo $method->isPrivate();              // false
echo $method->isStatic();               // false
echo $method->isAbstract();             // false
echo $method->isFinal();                // false
echo $method->isConstructor();          // false
echo $method->isDestructor();           // false
echo $method->getNumberOfParameters();  // 4
echo $method->getNumberOfRequiredParameters(); // 2

// Return type
$returnType = $method->getReturnType();
echo $returnType->getName();            // "Order"
echo $returnType->allowsNull();         // false

// Parametrlər
$params = $method->getParameters();
foreach ($params as $param) {
    echo $param->getName();             // "user", "items", "couponCode", "discount"
    echo $param->getPosition();         // 0, 1, 2, 3
    echo $param->hasType();             // true
    if ($param->hasType()) {
        echo $param->getType()->getName(); // "User", "array", "string", "float"
    }
    echo $param->isOptional();          // false, false, true, true
    if ($param->isDefaultValueAvailable()) {
        echo $param->getDefaultValue(); // null, 0.0
    }
}

// DocBlock oxuma
echo $method->getDocComment();

// Method-u çağırma
$service = new OrderService();
$result = $method->invoke($service, $user, $items, null, 10.0);

// invoke ilə associative array (argument unpacking)
$result = $method->invokeArgs($service, [$user, $items, null, 10.0]);

// Private/protected method-u çağırma
$privateMethod = new ReflectionMethod(OrderService::class, 'generateOrderNumber');
$privateMethod->setAccessible(true); // PHP 8.1-dən əvvəl lazımdır
$orderNumber = $privateMethod->invoke(null); // static olduğu üçün null
```

### Closure-ları Reflection ilə Analiz Etmə

*Closure-ları Reflection ilə Analiz Etmə üçün kod nümunəsi:*
```php
// Bu kod ReflectionFunction ilə closure-ları analiz etməyi göstərir
<?php

$closure = function (int $x, int $y): int {
    return $x + $y;
};

$ref = new ReflectionFunction($closure);
echo $ref->getNumberOfParameters();     // 2
echo $ref->getReturnType()->getName();  // "int"

// Closure-un bağlı olduğu scope
echo $ref->getClosureScopeClass()?->getName();
```

---

## ReflectionProperty

`ReflectionProperty` class property-ləri haqqında ətraflı məlumat verir və onlara daxil olmağa imkan yaradır.

*`ReflectionProperty` class property-ləri haqqında ətraflı məlumat veri üçün kod nümunəsi:*
```php
// Bu kod ReflectionProperty ilə class property-lərini analiz etməyi göstərir
<?php

class Config
{
    public string $appName = 'MyApp';
    protected array $settings = [];
    private static string $secretKey = 'abc123';
    public readonly string $version;

    // PHP 8.1+ readonly property
    public function __construct(
        private readonly string $environment = 'production'
    ) {
        $this->version = '1.0.0';
    }
}

$ref = new ReflectionClass(Config::class);

// Bütün property-lər
foreach ($ref->getProperties() as $prop) {
    echo sprintf(
        "%s: %s %s%s%s\n",
        $prop->getName(),
        $prop->isPublic() ? 'public' : ($prop->isProtected() ? 'protected' : 'private'),
        $prop->isStatic() ? 'static ' : '',
        $prop->isReadOnly() ? 'readonly ' : '',
        $prop->hasType() ? $prop->getType()->getName() : 'mixed'
    );
}

// Property-nin dəyərini oxuma
$config = new Config('staging');

$prop = $ref->getProperty('appName');
echo $prop->getValue($config); // "MyApp"

// Private property-ni oxuma
$envProp = $ref->getProperty('environment');
$envProp->setAccessible(true); // PHP 8.1-dən əvvəl
echo $envProp->getValue($config); // "staging"

// Static property
$secretProp = $ref->getProperty('secretKey');
$secretProp->setAccessible(true);
echo $secretProp->getValue(); // "abc123" (static üçün object lazım deyil)

// Property dəyərini dəyişmə
$prop = $ref->getProperty('appName');
$prop->setValue($config, 'NewAppName');
echo $config->appName; // "NewAppName"

// Default value
$prop = $ref->getProperty('appName');
echo $prop->getDefaultValue(); // "MyApp"
echo $prop->hasDefaultValue(); // true

// Promoted property yoxlama (PHP 8.0+)
$envProp = $ref->getProperty('environment');
echo $envProp->isPromoted(); // true (constructor promotion)
```

---

## ReflectionParameter

`ReflectionParameter` function/method parametrləri haqqında dərin məlumat verir. Bu, Dependency Injection container-ləri üçün çox vacibdir.

*`ReflectionParameter` function/method parametrləri haqqında dərin məlu üçün kod nümunəsi:*
```php
// Bu kod ReflectionParameter ilə method parametrlərini analiz etməyi göstərir
<?php

class PaymentService
{
    public function processPayment(
        PaymentGateway $gateway,
        Order $order,
        float $amount,
        string $currency = 'USD',
        ?array $metadata = null,
        bool ...$flags
    ): PaymentResult {
        // ...
    }
}

$method = new ReflectionMethod(PaymentService::class, 'processPayment');

foreach ($method->getParameters() as $param) {
    $info = [
        'name' => $param->getName(),
        'position' => $param->getPosition(),
        'hasType' => $param->hasType(),
        'type' => $param->hasType() ? $param->getType()->getName() : null,
        'isOptional' => $param->isOptional(),
        'isNullable' => $param->allowsNull(),
        'isVariadic' => $param->isVariadic(),
        'hasDefault' => $param->isDefaultValueAvailable(),
        'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : 'N/A',
        'isPromoted' => $param->isPromoted(),
        'canBePassedByValue' => $param->canBePassedByValue(),
    ];

    // Type hint class-dırmı yoxsa primitive type-dırmı?
    if ($param->hasType()) {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            echo $param->getName() . ' -> Class type: ' . $type->getName();
            // Bu, DI container üçün çox vacibdir!
            // Container bu class-ı avtomatik resolve edə bilər
        }

        if ($type instanceof ReflectionUnionType) {
            // PHP 8.0+ union types: int|string
            foreach ($type->getTypes() as $t) {
                echo $t->getName();
            }
        }

        if ($type instanceof ReflectionIntersectionType) {
            // PHP 8.1+ intersection types: Countable&Iterator
            foreach ($type->getTypes() as $t) {
                echo $t->getName();
            }
        }
    }
}

// === Parametr type-larına görə dependency resolve etmə (DI Container prinsipi) ===

function resolveDependencies(ReflectionMethod $method): array
{
    $dependencies = [];

    foreach ($method->getParameters() as $param) {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            // Class dependency — container-dən resolve etmək lazımdır
            $className = $type->getName();
            $dependencies[] = new $className(); // Sadələşdirilmiş versiya
        } elseif ($param->isDefaultValueAvailable()) {
            $dependencies[] = $param->getDefaultValue();
        } elseif ($param->allowsNull()) {
            $dependencies[] = null;
        } else {
            throw new RuntimeException(
                "'{$param->getName()}' parametrini resolve etmək mümkün deyil"
            );
        }
    }

    return $dependencies;
}
```

---

## ReflectionFunction

`ReflectionFunction` standalone function-lar (class method-ları deyil) haqqında məlumat verir.

*`ReflectionFunction` standalone function-lar (class method-ları deyil) üçün kod nümunəsi:*
```php
// Bu kod ReflectionFunction ilə müstəqil funksiyaları analiz etməyi göstərir
<?php

function calculateTax(float $amount, float $rate = 0.18): float
{
    return $amount * $rate;
}

$ref = new ReflectionFunction('calculateTax');

echo $ref->getName();                  // "calculateTax"
echo $ref->getFileName();             // Faylın yolu
echo $ref->getStartLine();            // Başlama sətiri
echo $ref->getEndLine();              // Bitmə sətiri
echo $ref->getNumberOfParameters();   // 2
echo $ref->getNumberOfRequiredParameters(); // 1
echo $ref->getReturnType()->getName(); // "float"

// Parametrləri analiz etmə
foreach ($ref->getParameters() as $param) {
    echo $param->getName();
    echo $param->getType()->getName();
}

// Function-u invoke etmə
$result = $ref->invoke(100, 0.20);    // 20.0
$result = $ref->invokeArgs([100, 0.20]); // 20.0

// === Built-in function-lar ===
$ref = new ReflectionFunction('array_map');
echo $ref->isInternal();              // true
echo $ref->isUserDefined();           // false

// === Closure-lar ===
$multiply = fn(int $a, int $b): int => $a * $b;
$ref = new ReflectionFunction($multiply);
echo $ref->isClosure();               // true
echo $ref->getNumberOfParameters();   // 2

// Closure binding
class Calculator
{
    private int $base = 10;
}

$closure = Closure::bind(function () {
    return $this->base;
}, new Calculator(), Calculator::class);

$ref = new ReflectionFunction($closure);
echo $ref->getClosureScopeClass()->getName(); // "Calculator"
echo $ref->getClosureThis()::class;           // "Calculator"
```

---

## Reflection ilə Class Metadata Oxuma

Reflection ilə class-ın tam "röntgen şəklini" çıxara bilərik.

*Reflection ilə class-ın tam "röntgen şəklini" çıxara bilərik üçün kod nümunəsi:*
```php
// Bu kod Reflection ilə class metadata-nı tam oxumağı göstərir
<?php

trait Timestampable
{
    public \DateTime $createdAt;
    public \DateTime $updatedAt;
}

interface Searchable
{
    public function toSearchArray(): array;
}

interface Cacheable
{
    public function getCacheKey(): string;
    public function getCacheTTL(): int;
}

#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        public string $table,
        public string $primaryKey = 'id'
    ) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public string $name,
        public string $type = 'string',
        public bool $nullable = false
    ) {}
}

#[Entity(table: 'articles', primaryKey: 'id')]
class Article implements Searchable, Cacheable
{
    use Timestampable;

    #[Column(name: 'id', type: 'integer')]
    private int $id;

    #[Column(name: 'title', type: 'string')]
    public string $title;

    #[Column(name: 'body', type: 'text', nullable: true)]
    public ?string $body;

    #[Column(name: 'status', type: 'string')]
    public string $status = 'draft';

    public function __construct(int $id, string $title, ?string $body = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->body = $body;
    }

    public function toSearchArray(): array
    {
        return ['title' => $this->title, 'body' => $this->body];
    }

    public function getCacheKey(): string
    {
        return "article:{$this->id}";
    }

    public function getCacheTTL(): int
    {
        return 3600;
    }

    public function publish(): void
    {
        $this->status = 'published';
    }
}

// === Tam metadata oxuma ===

function analyzeClass(string $className): array
{
    $ref = new ReflectionClass($className);
    $metadata = [];

    // Əsas məlumatlar
    $metadata['name'] = $ref->getName();
    $metadata['shortName'] = $ref->getShortName();
    $metadata['namespace'] = $ref->getNamespaceName();
    $metadata['isAbstract'] = $ref->isAbstract();
    $metadata['isFinal'] = $ref->isFinal();
    $metadata['isReadOnly'] = method_exists($ref, 'isReadOnly') ? $ref->isReadOnly() : false;

    // Parent class
    $metadata['parent'] = $ref->getParentClass() ? $ref->getParentClass()->getName() : null;

    // Interface-lər
    $metadata['interfaces'] = array_keys($ref->getInterfaces());

    // Trait-lər
    $metadata['traits'] = array_keys($ref->getTraits());

    // Constant-lar
    $metadata['constants'] = [];
    foreach ($ref->getReflectionConstants() as $constant) {
        $metadata['constants'][] = [
            'name' => $constant->getName(),
            'value' => $constant->getValue(),
            'visibility' => $constant->isPublic() ? 'public' :
                          ($constant->isProtected() ? 'protected' : 'private'),
        ];
    }

    // Property-lər
    $metadata['properties'] = [];
    foreach ($ref->getProperties() as $prop) {
        $metadata['properties'][] = [
            'name' => $prop->getName(),
            'type' => $prop->hasType() ? $prop->getType()->getName() : 'mixed',
            'visibility' => $prop->isPublic() ? 'public' :
                          ($prop->isProtected() ? 'protected' : 'private'),
            'isStatic' => $prop->isStatic(),
            'isReadOnly' => $prop->isReadOnly(),
            'hasDefault' => $prop->hasDefaultValue(),
            'default' => $prop->hasDefaultValue() ? $prop->getDefaultValue() : null,
            'isPromoted' => $prop->isPromoted(),
            'attributes' => getAttributesInfo($prop),
        ];
    }

    // Method-lar
    $metadata['methods'] = [];
    foreach ($ref->getMethods() as $method) {
        $params = [];
        foreach ($method->getParameters() as $param) {
            $params[] = [
                'name' => $param->getName(),
                'type' => $param->hasType() ? (string) $param->getType() : 'mixed',
                'isOptional' => $param->isOptional(),
                'isNullable' => $param->allowsNull(),
                'hasDefault' => $param->isDefaultValueAvailable(),
                'isVariadic' => $param->isVariadic(),
            ];
        }

        $metadata['methods'][] = [
            'name' => $method->getName(),
            'visibility' => $method->isPublic() ? 'public' :
                          ($method->isProtected() ? 'protected' : 'private'),
            'isStatic' => $method->isStatic(),
            'isAbstract' => $method->isAbstract(),
            'isFinal' => $method->isFinal(),
            'returnType' => $method->hasReturnType() ? (string) $method->getReturnType() : 'mixed',
            'parameters' => $params,
            'declaringClass' => $method->getDeclaringClass()->getName(),
        ];
    }

    // Class Attributes (PHP 8.0+)
    $metadata['attributes'] = getAttributesInfo($ref);

    return $metadata;
}

function getAttributesInfo(ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter $ref): array
{
    $attrs = [];
    foreach ($ref->getAttributes() as $attr) {
        $attrs[] = [
            'name' => $attr->getName(),
            'arguments' => $attr->getArguments(),
            'target' => $attr->getTarget(),
        ];
    }
    return $attrs;
}

// İstifadə
$metadata = analyzeClass(Article::class);
print_r($metadata);
```

---

## Private/Protected Property-lərə Daxil Olma

Bu texnika əsasən test zamanı və framework internals-da istifadə olunur.

*Bu texnika əsasən test zamanı və framework internals-da istifadə olunu üçün kod nümunəsi:*
```php
// Bu kod Reflection ilə private/protected property-lərə daxil olmağı göstərir
<?php

class BankAccount
{
    private float $balance;
    private array $transactions = [];
    protected string $accountNumber;
    private static float $interestRate = 0.05;

    public function __construct(string $accountNumber, float $initialBalance)
    {
        $this->accountNumber = $accountNumber;
        $this->balance = $initialBalance;
    }

    private function addTransaction(string $type, float $amount): void
    {
        $this->transactions[] = [
            'type' => $type,
            'amount' => $amount,
            'date' => date('Y-m-d H:i:s'),
        ];
    }

    public function getBalance(): float
    {
        return $this->balance;
    }
}

$account = new BankAccount('AZ12345', 1000.00);

// === Private property oxuma ===
$ref = new ReflectionClass($account);

$balanceProp = $ref->getProperty('balance');
$balanceProp->setAccessible(true); // PHP 8.1-dən əvvəl mütləqdir
echo $balanceProp->getValue($account); // 1000.00

// PHP 8.1+ - setAccessible artıq lazım deyil
// (reflection avtomatik private/protected-ə daxil ola bilir)

// === Private property dəyişmə ===
$balanceProp->setValue($account, 5000.00);
echo $account->getBalance(); // 5000.00

// === Private method çağırma ===
$method = $ref->getMethod('addTransaction');
$method->setAccessible(true);
$method->invoke($account, 'deposit', 500.00);

// Transaction-ları yoxlayaq
$transProp = $ref->getProperty('transactions');
$transProp->setAccessible(true);
$transactions = $transProp->getValue($account);
print_r($transactions);
// [['type' => 'deposit', 'amount' => 500.00, 'date' => '...']]

// === Static private property ===
$rateProp = $ref->getProperty('interestRate');
$rateProp->setAccessible(true);
echo $rateProp->getValue(); // 0.05
$rateProp->setValue(null, 0.07); // static üçün null
echo $rateProp->getValue(); // 0.07

// === Daha təmiz helper function ===
function getPrivateProperty(object $object, string $propertyName): mixed
{
    $ref = new ReflectionProperty($object, $propertyName);
    $ref->setAccessible(true);
    return $ref->getValue($object);
}

function setPrivateProperty(object $object, string $propertyName, mixed $value): void
{
    $ref = new ReflectionProperty($object, $propertyName);
    $ref->setAccessible(true);
    $ref->setValue($object, $value);
}

function callPrivateMethod(object $object, string $methodName, mixed ...$args): mixed
{
    $ref = new ReflectionMethod($object, $methodName);
    $ref->setAccessible(true);
    return $ref->invoke($object, ...$args);
}

// İstifadə
$balance = getPrivateProperty($account, 'balance');
setPrivateProperty($account, 'balance', 2000.00);
callPrivateMethod($account, 'addTransaction', 'withdrawal', 100.00);
```

### Test-lərdə İstifadə

*Test-lərdə İstifadə üçün kod nümunəsi:*
```php
// Bu kod testlərdə private metodlara Reflection ilə daxil olmağı göstərir
<?php

use PHPUnit\Framework\TestCase;

class BankAccountTest extends TestCase
{
    public function test_initial_balance_is_set(): void
    {
        $account = new BankAccount('AZ12345', 500.00);

        // Private property-yə reflection ilə daxil olma
        $ref = new ReflectionProperty(BankAccount::class, 'balance');
        $ref->setAccessible(true);

        $this->assertEquals(500.00, $ref->getValue($account));
    }

    public function test_add_transaction_records_correctly(): void
    {
        $account = new BankAccount('AZ12345', 1000.00);

        // Private method-u çağırma
        $method = new ReflectionMethod(BankAccount::class, 'addTransaction');
        $method->setAccessible(true);
        $method->invoke($account, 'deposit', 200.00);

        // Transactions yoxlama
        $prop = new ReflectionProperty(BankAccount::class, 'transactions');
        $prop->setAccessible(true);
        $transactions = $prop->getValue($account);

        $this->assertCount(1, $transactions);
        $this->assertEquals('deposit', $transactions[0]['type']);
        $this->assertEquals(200.00, $transactions[0]['amount']);
    }
}
```

> **Qeyd:** PHP 8.1-dən etibarən `setAccessible(true)` çağırışı artıq lazım deyil — Reflection avtomatik bütün visibility-lərə daxil ola bilir.

---

## Method Parametrlərini Analiz Etmə

Bu, Dependency Injection-ın əsasını təşkil edir.

*Bu, Dependency Injection-ın əsasını təşkil edir üçün kod nümunəsi:*
```php
// Bu kod Reflection ilə constructor parametrlərini analiz edərək DI-ni göstərir
<?php

interface LoggerInterface
{
    public function log(string $message): void;
}

class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        file_put_contents('app.log', $message . "\n", FILE_APPEND);
    }
}

class UserController
{
    public function store(
        Request $request,
        UserRepository $repository,
        LoggerInterface $logger,
        int $maxAttempts = 3,
        ?string $redirectUrl = null
    ): Response {
        // ...
    }
}

// === Parametr analizi ===

function analyzeMethodParameters(string $class, string $method): array
{
    $ref = new ReflectionMethod($class, $method);
    $analysis = [];

    foreach ($ref->getParameters() as $param) {
        $type = $param->getType();
        $paramInfo = [
            'name' => $param->getName(),
            'position' => $param->getPosition(),
            'required' => !$param->isOptional(),
        ];

        if ($type === null) {
            $paramInfo['category'] = 'untyped';
            $paramInfo['resolvable'] = false;
        } elseif ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                $paramInfo['category'] = 'primitive';
                $paramInfo['typeName'] = $type->getName();
                $paramInfo['resolvable'] = $param->isDefaultValueAvailable() || $param->allowsNull();
            } else {
                $paramInfo['category'] = 'class_or_interface';
                $paramInfo['typeName'] = $type->getName();
                $paramInfo['resolvable'] = true;

                // Class mövcuddurmu?
                $paramInfo['classExists'] = class_exists($type->getName())
                    || interface_exists($type->getName());

                // Interface-dirsə, concrete class lazımdır
                if (interface_exists($type->getName())) {
                    $paramInfo['isInterface'] = true;
                    $paramInfo['needsBinding'] = true;
                }
            }
        } elseif ($type instanceof ReflectionUnionType) {
            $paramInfo['category'] = 'union';
            $paramInfo['types'] = array_map(
                fn(ReflectionNamedType $t) => $t->getName(),
                $type->getTypes()
            );
        } elseif ($type instanceof ReflectionIntersectionType) {
            $paramInfo['category'] = 'intersection';
            $paramInfo['types'] = array_map(
                fn(ReflectionNamedType $t) => $t->getName(),
                $type->getTypes()
            );
        }

        if ($param->isDefaultValueAvailable()) {
            $paramInfo['defaultValue'] = $param->getDefaultValue();
        }

        $paramInfo['nullable'] = $param->allowsNull();
        $paramInfo['variadic'] = $param->isVariadic();

        $analysis[] = $paramInfo;
    }

    return $analysis;
}

$result = analyzeMethodParameters(UserController::class, 'store');
print_r($result);

/*
Nəticə:
[
    [
        'name' => 'request',
        'position' => 0,
        'required' => true,
        'category' => 'class_or_interface',
        'typeName' => 'Request',
        'resolvable' => true,
        'classExists' => true,
        'nullable' => false,
    ],
    [
        'name' => 'repository',
        ...
        'category' => 'class_or_interface',
    ],
    [
        'name' => 'logger',
        ...
        'category' => 'class_or_interface',
        'isInterface' => true,
        'needsBinding' => true,
    ],
    [
        'name' => 'maxAttempts',
        ...
        'category' => 'primitive',
        'typeName' => 'int',
        'defaultValue' => 3,
    ],
    [
        'name' => 'redirectUrl',
        ...
        'category' => 'primitive',
        'typeName' => 'string',
        'nullable' => true,
    ],
]
*/
```

---

## PHP Attributes (PHP 8.0+) və Reflection

PHP 8.0 ilə gələn Attributes (əvvəlki versiyalarda docblock annotation-lar istifadə olunurdu) structured metadata əlavə etmək üçün istifadə olunur.

*PHP 8.0 ilə gələn Attributes (əvvəlki versiyalarda docblock annotation üçün kod nümunəsi:*
```php
// Bu kod PHP 8 Attributes-lərin yaradılmasını və Reflection ilə oxunmasını göstərir
<?php

// === Attribute yaratma ===

#[\Attribute(\Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(
        public string $prefix = '',
        public array $middleware = []
    ) {}
}

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class HttpMethod
{
    public function __construct(
        public string $method,
        public string $path,
        public ?string $name = null
    ) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Get extends HttpMethod
{
    public function __construct(string $path, ?string $name = null)
    {
        parent::__construct('GET', $path, $name);
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Post extends HttpMethod
{
    public function __construct(string $path, ?string $name = null)
    {
        parent::__construct('POST', $path, $name);
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Middleware
{
    public function __construct(
        public string|array $middleware
    ) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Validate
{
    public function __construct(
        public array $rules
    ) {}
}

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class FromQuery
{
    public function __construct(
        public ?string $key = null
    ) {}
}

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class FromBody
{
    public function __construct(
        public ?string $key = null
    ) {}
}

// === Attribute-ları istifadə etmə ===

#[Route(prefix: '/api/users', middleware: ['auth', 'throttle'])]
class UserController
{
    #[Get(path: '/', name: 'users.index')]
    #[Middleware('cache')]
    public function index(
        #[FromQuery(key: 'page')] int $page = 1,
        #[FromQuery(key: 'per_page')] int $perPage = 15
    ): array {
        return [];
    }

    #[Get(path: '/{id}', name: 'users.show')]
    public function show(int $id): array
    {
        return [];
    }

    #[Post(path: '/', name: 'users.store')]
    #[Middleware(['auth', 'admin'])]
    public function store(
        #[FromBody] string $name,
        #[FromBody] string $email
    ): array {
        return [];
    }
}

// === Attribute-ları Reflection ilə oxuma ===

function discoverRoutes(string $controllerClass): array
{
    $ref = new ReflectionClass($controllerClass);
    $routes = [];

    // Class-level Route attribute
    $routeAttrs = $ref->getAttributes(Route::class);
    $prefix = '';
    $classMiddleware = [];

    if (!empty($routeAttrs)) {
        $route = $routeAttrs[0]->newInstance();
        $prefix = $route->prefix;
        $classMiddleware = $route->middleware;
    }

    // Method-level attribute-lar
    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        // HttpMethod attribute-larını tap
        $httpAttrs = $method->getAttributes(HttpMethod::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($httpAttrs as $httpAttr) {
            $http = $httpAttr->newInstance();

            // Middleware attribute-larını tap
            $middlewareAttrs = $method->getAttributes(Middleware::class);
            $methodMiddleware = [];
            foreach ($middlewareAttrs as $mAttr) {
                $m = $mAttr->newInstance();
                $methodMiddleware = array_merge(
                    $methodMiddleware,
                    (array) $m->middleware
                );
            }

            // Parameter attribute-larını tap
            $parameterBindings = [];
            foreach ($method->getParameters() as $param) {
                $fromQuery = $param->getAttributes(FromQuery::class);
                $fromBody = $param->getAttributes(FromBody::class);

                if (!empty($fromQuery)) {
                    $fq = $fromQuery[0]->newInstance();
                    $parameterBindings[] = [
                        'source' => 'query',
                        'key' => $fq->key ?? $param->getName(),
                        'parameter' => $param->getName(),
                    ];
                }

                if (!empty($fromBody)) {
                    $fb = $fromBody[0]->newInstance();
                    $parameterBindings[] = [
                        'source' => 'body',
                        'key' => $fb->key ?? $param->getName(),
                        'parameter' => $param->getName(),
                    ];
                }
            }

            $routes[] = [
                'method' => $http->method,
                'path' => $prefix . $http->path,
                'name' => $http->name,
                'controller' => $controllerClass,
                'action' => $method->getName(),
                'middleware' => array_merge($classMiddleware, $methodMiddleware),
                'parameters' => $parameterBindings,
            ];
        }
    }

    return $routes;
}

$routes = discoverRoutes(UserController::class);
print_r($routes);

/*
[
    [
        'method' => 'GET',
        'path' => '/api/users/',
        'name' => 'users.index',
        'controller' => 'UserController',
        'action' => 'index',
        'middleware' => ['auth', 'throttle', 'cache'],
        'parameters' => [
            ['source' => 'query', 'key' => 'page', 'parameter' => 'page'],
            ['source' => 'query', 'key' => 'per_page', 'parameter' => 'perPage'],
        ],
    ],
    ...
]
*/
```

---

## Laravel-də Reflection İstifadəsi

Laravel framework-u Reflection API-ni çox geniş istifadə edir. Əsas istifadə sahələrinə baxaq:

### 1. Service Container (Dependency Injection)

Laravel-in ən əsas komponenti olan Service Container, class dependency-lərini avtomatik resolve etmək üçün Reflection istifadə edir.

*Laravel-in ən əsas komponenti olan Service Container, class dependency üçün kod nümunəsi:*
```php
// Bu kod Laravel Service Container-in Reflection ilə işləməsini göstərir
<?php

// Laravel Service Container-in sadələşdirilmiş versiyası
class SimpleContainer
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => true,
        ];
    }

    public function make(string $abstract): object
    {
        // Əgər singleton instance mövcuddursa, onu qaytar
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Binding varsa, onu istifadə et
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];

            if (is_array($binding) && ($binding['shared'] ?? false)) {
                $concrete = $binding['concrete'];
                $object = $this->resolve($concrete);
                $this->instances[$abstract] = $object;
                return $object;
            }

            return $this->resolve($binding);
        }

        // Binding yoxdursa, auto-wiring ilə resolve et
        return $this->build($abstract);
    }

    private function resolve(Closure|string $concrete): object
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        return $this->build($concrete);
    }

    /**
     * BU, REFLECTION-IN ƏN VACIB İSTİFADƏSİDİR!
     * Class-ın constructor parametrlərini oxuyub,
     * dependency-ləri avtomatik resolve edir.
     */
    private function build(string $className): object
    {
        $reflector = new ReflectionClass($className);

        // Class instantiate oluna bilirmi?
        if (!$reflector->isInstantiable()) {
            throw new RuntimeException(
                "[$className] is not instantiable (interface or abstract class)"
            );
        }

        // Constructor varmı?
        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            // Constructor yoxdur, sadəcə yarat
            return new $className();
        }

        // Constructor parametrlərini analiz et
        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        // Class-ı dependency-lərlə yarat
        return $reflector->newInstanceArgs($dependencies);
    }

    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                // Class/interface dependency - recursive olaraq resolve et
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                // Default dəyəri var
                $dependencies[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                // Nullable
                $dependencies[] = null;
            } else {
                throw new RuntimeException(
                    "Unresolvable dependency: [{$parameter->getName()}] in class [{$parameter->getDeclaringClass()->getName()}]"
                );
            }
        }

        return $dependencies;
    }
}

// === İstifadə ===

interface PaymentGatewayInterface
{
    public function charge(float $amount): bool;
}

class StripeGateway implements PaymentGatewayInterface
{
    public function charge(float $amount): bool
    {
        echo "Charging $amount via Stripe\n";
        return true;
    }
}

class OrderRepository
{
    public function __construct(
        private readonly DatabaseConnection $db
    ) {}

    public function save(array $order): int
    {
        return 1;
    }
}

class DatabaseConnection
{
    // Constructor parametri yoxdur
}

class OrderService
{
    public function __construct(
        private readonly OrderRepository $repository,
        private readonly PaymentGatewayInterface $gateway
    ) {}

    public function createOrder(float $amount): int
    {
        $this->gateway->charge($amount);
        return $this->repository->save(['amount' => $amount]);
    }
}

$container = new SimpleContainer();

// Interface-dən concrete class-a binding
$container->bind(PaymentGatewayInterface::class, StripeGateway::class);

// Auto-wiring ilə bütün dependency chain resolve olunur:
// OrderService -> OrderRepository -> DatabaseConnection
//              -> PaymentGatewayInterface (-> StripeGateway)
$orderService = $container->make(OrderService::class);
$orderService->createOrder(99.99);
```

### 2. Route Model Binding

Laravel route parametrlərini avtomatik model instance-larına çevirir.

*Laravel route parametrlərini avtomatik model instance-larına çevirir üçün kod nümunəsi:*
```php
// Bu kod Laravel Route Model Binding-in Reflection ilə işləməsini göstərir
<?php

// routes/web.php
Route::get('/users/{user}', [UserController::class, 'show']);

// Laravel Reflection ilə nə edir:
// 1. UserController::show method-unun parametrlərini oxuyur
// 2. $user parametrinin type hint-inin User (Eloquent model) olduğunu görür
// 3. Route parametrindəki {user} dəyərini (ID) ilə User::findOrFail() çağırır

class UserController
{
    // Laravel reflection ilə $user parametrinin
    // User model olduğunu müəyyən edir
    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }
}

// Arxada belə bir şey baş verir (sadələşdirilmiş):
function resolveRouteBinding(string $controllerClass, string $method, array $routeParams): array
{
    $ref = new ReflectionMethod($controllerClass, $method);
    $resolved = [];

    foreach ($ref->getParameters() as $param) {
        $type = $param->getType();
        $paramName = $param->getName();

        if ($type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && isset($routeParams[$paramName])
        ) {
            $className = $type->getName();

            // Eloquent model-dirsə, implicit binding et
            if (is_subclass_of($className, \Illuminate\Database\Eloquent\Model::class)) {
                $resolved[] = $className::findOrFail($routeParams[$paramName]);
            } else {
                $resolved[] = app()->make($className);
            }
        } elseif (isset($routeParams[$paramName])) {
            $resolved[] = $routeParams[$paramName];
        }
    }

    return $resolved;
}
```

### 3. Controller Method Injection

*3. Controller Method Injection üçün kod nümunəsi:*
```php
// Bu kod controller metodlarına Reflection ilə dependency injection-u göstərir
<?php

class PostController
{
    // Laravel hər parametri reflection ilə analiz edir:
    // - Request -> Service Container-dən resolve olunur
    // - PostService -> Service Container-dən resolve olunur
    // - Post $post -> Route Model Binding
    public function update(
        Request $request,
        PostService $postService,
        Post $post
    ): RedirectResponse {
        $postService->update($post, $request->validated());
        return redirect()->route('posts.show', $post);
    }
}

// Bu, invoke etmə zamanı belə işləyir:
// Container::call([$controller, 'update'], $routeParameters)
// ki, burada call() metodu reflection istifadə edir
```

### 4. Event Discovery

*4. Event Discovery üçün kod nümunəsi:*
```php
// Bu kod event-lərin Reflection ilə avtomatik kəşf edilməsini göstərir
<?php

// Laravel event:discover əmri listener class-ları skan edərək
// hadisələri avtomatik tapır

use Illuminate\Support\Facades\Event;

class EventDiscoverer
{
    public function discover(string $listenerPath): array
    {
        $events = [];

        // Listener class-larını fayllardan tap
        $files = glob($listenerPath . '/*.php');

        foreach ($files as $file) {
            require_once $file;
            $className = $this->getClassNameFromFile($file);

            if (!$className || !class_exists($className)) {
                continue;
            }

            $ref = new ReflectionClass($className);

            // handle() method-unu yoxla
            if (!$ref->hasMethod('handle')) {
                continue;
            }

            $handleMethod = $ref->getMethod('handle');
            $params = $handleMethod->getParameters();

            if (empty($params)) {
                continue;
            }

            // İlk parametrin type hint-ini oxu — bu event class-ıdır
            $firstParam = $params[0];
            $type = $firstParam->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $eventClass = $type->getName();
                $events[$eventClass][] = $className;
            }
        }

        return $events;
    }

    private function getClassNameFromFile(string $file): ?string
    {
        // Reflection olmadan class adını tapmaq üçün token parse
        $tokens = token_get_all(file_get_contents($file));
        $namespace = '';
        $className = '';

        // ... token parsing logic
        return $namespace ? $namespace . '\\' . $className : $className;
    }
}

// Nəticə:
// [
//     'App\Events\OrderCreated' => ['App\Listeners\SendOrderConfirmation'],
//     'App\Events\UserRegistered' => ['App\Listeners\SendWelcomeEmail', 'App\Listeners\CreateDefaultSettings'],
// ]
```

### 5. Artisan Command Discovery

*5. Artisan Command Discovery üçün kod nümunəsi:*
```php
// Bu kod Artisan command-larının Reflection ilə avtomatik tapılmasını göstərir
<?php

// Laravel Artisan command-ları avtomatik tapır
// app/Console/Commands/ qovluğundakı bütün class-ları skan edir

class CommandDiscoverer
{
    public function discover(string $path): array
    {
        $commands = [];

        foreach (glob($path . '/*.php') as $file) {
            $className = $this->resolveClassName($file);

            if (!$className || !class_exists($className)) {
                continue;
            }

            $ref = new ReflectionClass($className);

            // Abstract deyilsə və Artisan Command-ın subclass-ıdırsa
            if (!$ref->isAbstract()
                && $ref->isSubclassOf(\Illuminate\Console\Command::class)
                && $ref->isInstantiable()
            ) {
                $commands[] = $className;
            }
        }

        return $commands;
    }
}
```

### 6. Validation Rule Discovery

*6. Validation Rule Discovery üçün kod nümunəsi:*
```php
// Bu kod FormRequest qaydalarını Reflection ilə oxumağı göstərir
<?php

// FormRequest-dən rules-ları reflection ilə oxuma
class FormRequestResolver
{
    public function resolveRules(string $formRequestClass): array
    {
        $ref = new ReflectionClass($formRequestClass);

        if ($ref->hasMethod('rules')) {
            $method = $ref->getMethod('rules');

            // rules() public method olmalıdır
            if ($method->isPublic() && !$method->isStatic()) {
                $instance = $ref->newInstance();
                return $method->invoke($instance);
            }
        }

        return [];
    }
}
```

---

## Laravel Service Container Internals

Laravel-in Service Container-inin əsl kodu necə işləyir, dərindən baxaq:

*Laravel-in Service Container-inin əsl kodu necə işləyir, dərindən baxa üçün kod nümunəsi:*
```php
// Bu kod Illuminate Container class-ının daxili işləməsini göstərir
<?php

// Bu, Illuminate\Container\Container class-ının sadələşdirilmiş versiyasıdır
// Laravel-in real kodu buna bənzəyir

namespace Illuminate\Container;

class Container
{
    protected array $bindings = [];
    protected array $instances = [];
    protected array $aliases = [];
    protected array $resolved = [];
    protected array $buildStack = [];
    protected array $with = [];
    protected array $contextual = [];

    /**
     * Əsas resolve metodu
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->resolve($abstract, $parameters);
    }

    protected function resolve(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        // Singleton instance mövcuddursa
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $this->with[] = $parameters;

        $concrete = $this->getConcrete($abstract);

        // Build et
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete);
        } else {
            $object = $this->make($concrete);
        }

        // Singleton-dursa, instance-ı saxla
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        $this->resolved[$abstract] = true;

        array_pop($this->with);

        return $object;
    }

    /**
     * ƏSAS REFLECTION MƏNTIQ — Class-ı build etmə
     */
    public function build(Closure|string $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $this->getLastParameterOverride());
        }

        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new BindingResolutionException(
                "Target class [$concrete] does not exist.", 0, $e
            );
        }

        if (!$reflector->isInstantiable()) {
            $this->notInstantiable($concrete);
        }

        $this->buildStack[] = $concrete;

        $constructor = $reflector->getConstructor();

        // Constructor yoxdursa
        if (is_null($constructor)) {
            array_pop($this->buildStack);
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        try {
            $instances = $this->resolveDependencies($dependencies);
        } catch (BindingResolutionException $e) {
            array_pop($this->buildStack);
            throw $e;
        }

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Constructor parametrlərini resolve etmə
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            // Override parameters yoxla
            if ($this->hasParameterOverride($dependency)) {
                $results[] = $this->getParameterOverride($dependency);
                continue;
            }

            $type = $dependency->getType();

            // Primitive type (int, string, etc.) və ya type hint yoxdursa
            if (is_null($type) || ($type instanceof \ReflectionNamedType && $type->isBuiltin())) {
                $results[] = $this->resolvePrimitive($dependency);
                continue;
            }

            // Class/Interface dependency
            try {
                $results[] = $this->resolveClass($dependency);
            } catch (BindingResolutionException $e) {
                if ($dependency->isDefaultValueAvailable()) {
                    $results[] = $dependency->getDefaultValue();
                } elseif ($dependency->allowsNull()) {
                    $results[] = null;
                } else {
                    throw $e;
                }
            }
        }

        return $results;
    }

    protected function resolveClass(\ReflectionParameter $parameter): mixed
    {
        try {
            // Contextual binding yoxla
            if (!is_null($contextual = $this->getContextualConcrete(
                $parameter->getType()->getName()
            ))) {
                return $this->resolve($contextual);
            }

            // Recursive olaraq resolve et
            return $this->make($parameter->getType()->getName());
        } catch (BindingResolutionException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->isVariadic()) {
                return [];
            }

            throw $e;
        }
    }

    protected function resolvePrimitive(\ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new BindingResolutionException(
            "Unresolvable dependency [{$parameter}] in class {$parameter->getDeclaringClass()->getName()}"
        );
    }
}
```

### Container::call() — Method Injection

*Container::call() — Method Injection üçün kod nümunəsi:*
```php
// Bu kod Container::call() metodunun dependency injection ilə işləməsini göstərir
<?php

// Container::call() metodu method-ları dependency injection ilə çağırmaq üçündür
class Container
{
    /**
     * Call a method with dependency injection
     */
    public function call(callable|string $callback, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback);
            $callback = [$this->make($class), $method];
        }

        if (is_array($callback)) {
            $reflector = new ReflectionMethod($callback[0], $callback[1]);
        } elseif ($callback instanceof Closure) {
            $reflector = new ReflectionFunction($callback);
        } else {
            $reflector = new ReflectionFunction($callback);
        }

        $dependencies = [];
        foreach ($reflector->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
            } else {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $dependencies[] = $this->make($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw new RuntimeException("Cannot resolve parameter: $name");
                }
            }
        }

        return call_user_func_array($callback, $dependencies);
    }
}

// İstifadə nümunəsi (Laravel-də):
// app()->call([new UserController, 'show'], ['id' => 1]);
// app()->call('App\Services\OrderService@processOrder');
// app()->call(function (UserRepository $repo, int $id) { ... }, ['id' => 5]);
```

---

## Auto-Wiring Necə İşləyir

Auto-wiring — constructor parametrlərinin type hint-lərinə baxaraq dependency-ləri avtomatik resolve etmə mexanizmidir.

*Auto-wiring — constructor parametrlərinin type hint-lərinə baxaraq dep üçün kod nümunəsi:*
```php
// Bu kod auto-wiring ilə mürəkkəb dependency chain-inin resolve edilməsini göstərir
<?php

// Mürəkkəb dependency chain nümunəsi:

// Layer 1: Infrastructure
class DatabaseConnection
{
    public function __construct(
        private readonly string $dsn = 'mysql:host=localhost;dbname=test'
    ) {}
}

class RedisConnection
{
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 6379
    ) {}
}

// Layer 2: Repository
class UserRepository
{
    public function __construct(
        private readonly DatabaseConnection $db
    ) {}

    public function findById(int $id): ?array
    {
        return ['id' => $id, 'name' => 'John'];
    }
}

// Layer 3: Cache
class CacheService
{
    public function __construct(
        private readonly RedisConnection $redis
    ) {}

    public function get(string $key): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
    }
}

// Layer 4: Service
class UserService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly CacheService $cache
    ) {}

    public function getUser(int $id): ?array
    {
        $cacheKey = "user:$id";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $user = $this->repository->findById($id);

        if ($user !== null) {
            $this->cache->set($cacheKey, $user);
        }

        return $user;
    }
}

// Layer 5: Controller
class UserController
{
    public function __construct(
        private readonly UserService $service
    ) {}

    public function show(int $id): array
    {
        return $this->service->getUser($id) ?? [];
    }
}

// === Auto-wiring tam prosesi ===

/*
app()->make(UserController::class) çağırıldıqda:

1. ReflectionClass(UserController) yaradılır
2. Constructor: __construct(UserService $service)
3. UserService resolve olunmalıdır:
   a. ReflectionClass(UserService) yaradılır
   b. Constructor: __construct(UserRepository $repository, CacheService $cache)
   c. UserRepository resolve olunmalıdır:
      i. ReflectionClass(UserRepository) yaradılır
      ii. Constructor: __construct(DatabaseConnection $db)
      iii. DatabaseConnection resolve olunmalıdır:
           - ReflectionClass(DatabaseConnection) yaradılır
           - Constructor: __construct(string $dsn = '...')
           - $dsn default dəyəri var, istifadə olunur
           - DatabaseConnection yaradılır
      iv. UserRepository(new DatabaseConnection()) yaradılır
   d. CacheService resolve olunmalıdır:
      i. ReflectionClass(CacheService) yaradılır
      ii. Constructor: __construct(RedisConnection $redis)
      iii. RedisConnection resolve olunmalıdır:
           - ReflectionClass(RedisConnection) yaradılır
           - Constructor: __construct(string $host = 'localhost', int $port = 6379)
           - Default dəyərlər istifadə olunur
           - RedisConnection yaradılır
      iv. CacheService(new RedisConnection()) yaradılır
   e. UserService(new UserRepository(...), new CacheService(...)) yaradılır
4. UserController(new UserService(...)) yaradılır

Nəticə: Bütün dependency ağacı avtomatik resolve olunur!
*/

// Laravel-də:
$controller = app()->make(UserController::class);
$controller->show(1);
```

### Circular Dependency Problemi

*Circular Dependency Problemi üçün kod nümunəsi:*
```php
// Bu kod circular dependency problemini və həllini göstərir
<?php

// BU, PROBLEM YARADIR!
class ServiceA
{
    public function __construct(private ServiceB $b) {}
}

class ServiceB
{
    public function __construct(private ServiceA $a) {}
}

// app()->make(ServiceA::class);
// ServiceA -> ServiceB lazımdır
// ServiceB -> ServiceA lazımdır
// ServiceA -> ServiceB lazımdır
// ... sonsuz dövrə!

// Laravel bunu aşkar edir və BindingResolutionException atır:
// "Circular dependency detected while resolving [ServiceA]"

// HƏLL: Interface istifadə et və ya lazy loading tətbiq et
class ServiceA
{
    public function __construct(private Container $container) {}

    public function getServiceB(): ServiceB
    {
        return $this->container->make(ServiceB::class);
    }
}
```

---

## Custom Attribute Yaratma və Reflection ilə Oxuma

*Custom Attribute Yaratma və Reflection ilə Oxuma üçün kod nümunəsi:*
```php
// Bu kod custom PHP attribute yaradılmasını və Reflection ilə oxunmasını göstərir
<?php

// === Custom Validation Attribute-ları ===

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Required
{
    public function __construct(
        public string $message = 'Bu sahə mütləqdir'
    ) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MaxLength
{
    public function __construct(
        public int $max,
        public string $message = ''
    ) {
        if ($this->message === '') {
            $this->message = "Maksimum $max simvol ola bilər";
        }
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MinLength
{
    public function __construct(
        public int $min,
        public string $message = ''
    ) {
        if ($this->message === '') {
            $this->message = "Minimum $min simvol olmalıdır";
        }
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Email
{
    public function __construct(
        public string $message = 'Düzgün email ünvanı daxil edin'
    ) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Range
{
    public function __construct(
        public int|float $min,
        public int|float $max,
        public string $message = ''
    ) {
        if ($this->message === '') {
            $this->message = "Dəyər $min ilə $max arasında olmalıdır";
        }
    }
}

// === DTO class-ı Attribute-larla ===

class CreateUserDTO
{
    public function __construct(
        #[Required]
        #[MinLength(2)]
        #[MaxLength(100)]
        public string $name = '',

        #[Required]
        #[Email]
        #[MaxLength(255)]
        public string $email = '',

        #[Required]
        #[MinLength(8)]
        #[MaxLength(64)]
        public string $password = '',

        #[Range(min: 18, max: 120)]
        public ?int $age = null,
    ) {}
}

// === Attribute-based Validator ===

class AttributeValidator
{
    public function validate(object $object): array
    {
        $errors = [];
        $ref = new ReflectionClass($object);

        foreach ($ref->getProperties() as $property) {
            $propertyName = $property->getName();
            $value = $property->getValue($object);

            foreach ($property->getAttributes() as $attribute) {
                $attrInstance = $attribute->newInstance();
                $error = $this->validateAttribute($attrInstance, $value, $propertyName);

                if ($error !== null) {
                    $errors[$propertyName][] = $error;
                }
            }
        }

        return $errors;
    }

    private function validateAttribute(object $attribute, mixed $value, string $field): ?string
    {
        return match (true) {
            $attribute instanceof Required && empty($value)
                => $attribute->message,

            $attribute instanceof MinLength && is_string($value) && mb_strlen($value) < $attribute->min
                => $attribute->message,

            $attribute instanceof MaxLength && is_string($value) && mb_strlen($value) > $attribute->max
                => $attribute->message,

            $attribute instanceof Email && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)
                => $attribute->message,

            $attribute instanceof Range && $value !== null && ($value < $attribute->min || $value > $attribute->max)
                => $attribute->message,

            default => null,
        };
    }
}

// === İstifadə ===

$dto = new CreateUserDTO(
    name: 'O',
    email: 'invalid-email',
    password: '123',
    age: 15
);

$validator = new AttributeValidator();
$errors = $validator->validate($dto);

print_r($errors);
/*
[
    'name' => ['Minimum 2 simvol olmalıdır'],
    'email' => ['Düzgün email ünvanı daxil edin'],
    'password' => ['Minimum 8 simvol olmalıdır'],
    'age' => ['Dəyər 18 ilə 120 arasında olmalıdır'],
]
*/

// === Laravel ilə inteqrasiya ===

// Custom validation rule olaraq Attribute-ları Laravel-ə əlavə etmək:
class AttributeValidationRule implements \Illuminate\Contracts\Validation\Rule
{
    public function __construct(
        private readonly string $dtoClass,
        private readonly string $property
    ) {}

    public function passes($attribute, $value): bool
    {
        $ref = new ReflectionProperty($this->dtoClass, $this->property);

        foreach ($ref->getAttributes() as $attr) {
            $instance = $attr->newInstance();
            // Validate logic...
        }

        return true;
    }

    public function message(): string
    {
        return 'Validation failed';
    }
}
```

---

## Reflection Performance Impact

Reflection əməliyyatları normal PHP koduna nisbətən daha yavaşdır. Performance impact-ı başa düşmək vacibdir.

*Reflection əməliyyatları normal PHP koduna nisbətən daha yavaşdır. Per üçün kod nümunəsi:*
```php
// Bu kod Reflection-ın performans təsirini benchmark ilə göstərir
<?php

// === Benchmark: Reflection vs Direct ===

class TestClass
{
    public function __construct(
        private string $name = 'test',
        private int $value = 42
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}

// Test 1: Direct instantiation vs Reflection
$iterations = 100000;

// Direct
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $obj = new TestClass('test', 42);
}
$directTime = microtime(true) - $start;

// Reflection (hər dəfə yeni ReflectionClass)
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $ref = new ReflectionClass(TestClass::class);
    $obj = $ref->newInstanceArgs(['test', 42]);
}
$reflectionTime = microtime(true) - $start;

// Reflection (cached ReflectionClass)
$ref = new ReflectionClass(TestClass::class);
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $obj = $ref->newInstanceArgs(['test', 42]);
}
$cachedReflectionTime = microtime(true) - $start;

echo "Direct: {$directTime}s\n";
echo "Reflection (no cache): {$reflectionTime}s\n";
echo "Reflection (cached): {$cachedReflectionTime}s\n";

/*
Tipik nəticələr:
Direct: 0.015s
Reflection (no cache): 0.120s
Reflection (cached): 0.045s
*/

// === Caching strategiyası ===

class ReflectionCache
{
    private static array $classCache = [];
    private static array $methodCache = [];
    private static array $propertyCache = [];
    private static array $parameterCache = [];

    public static function getClass(string $className): ReflectionClass
    {
        if (!isset(self::$classCache[$className])) {
            self::$classCache[$className] = new ReflectionClass($className);
        }
        return self::$classCache[$className];
    }

    public static function getMethod(string $className, string $methodName): ReflectionMethod
    {
        $key = "$className::$methodName";
        if (!isset(self::$methodCache[$key])) {
            self::$methodCache[$key] = new ReflectionMethod($className, $methodName);
        }
        return self::$methodCache[$key];
    }

    public static function getConstructorParameters(string $className): array
    {
        if (!isset(self::$parameterCache[$className])) {
            $ref = self::getClass($className);
            $constructor = $ref->getConstructor();
            self::$parameterCache[$className] = $constructor
                ? $constructor->getParameters()
                : [];
        }
        return self::$parameterCache[$className];
    }

    public static function clear(): void
    {
        self::$classCache = [];
        self::$methodCache = [];
        self::$propertyCache = [];
        self::$parameterCache = [];
    }
}

// Laravel Service Container-in özü də reflection nəticələrini cache edir!
// Həm resolved instances, həm də reflection data cache olunur.

// === OPcache və Preloading ===

// PHP 7.4+ preloading ilə reflection overhead azaldıla bilər:
// opcache.preload=preload.php
// Bu, class-ları əvvəlcədən yükləyir və reflection daha sürətli işləyir

// Laravel-in php artisan optimize əmri
// config:cache, route:cache, event:cache — bunlar reflection nəticələrini cache edir
// Beləliklə production-da reflection overhead minimuma enir
```

### Laravel-in Cache Mexanizmləri

*Laravel-in Cache Mexanizmləri üçün kod nümunəsi:*
```php
// Bu kod Laravel-in Reflection nəticələrini cache etməsini göstərir
<?php

// Laravel route:cache reflection nəticələrini cache edir:
// php artisan route:cache
// Bu əmr bütün route-ları resolve edib serialized formada saxlayır
// Beləliklə hər request-də reflection lazım olmur

// event:cache
// php artisan event:cache
// Event-listener mapping-ləri cache olunur

// config:cache
// php artisan config:cache
// Bütün config faylları bir fayla birləşdirilir

// php artisan optimize
// Yuxarıdakıların hamısını bir dəfəyə edir
```

---

## Real-World Nümunələr

### 1. Avtomatik DTO Mapper

*1. Avtomatik DTO Mapper üçün kod nümunəsi:*
```php
// Bu kod Reflection ilə avtomatik DTO mapper yaratmağı göstərir
<?php

class DTOMapper
{
    /**
     * Request/array məlumatlarını avtomatik DTO-ya map edir
     */
    public static function map(string $dtoClass, array $data): object
    {
        $ref = new ReflectionClass($dtoClass);
        $constructor = $ref->getConstructor();

        if (!$constructor) {
            return new $dtoClass();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if (array_key_exists($name, $data)) {
                $value = $data[$name];

                // Type casting
                if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                    $value = match ($type->getName()) {
                        'int' => (int) $value,
                        'float' => (float) $value,
                        'bool' => (bool) $value,
                        'string' => (string) $value,
                        'array' => (array) $value,
                        default => $value,
                    };
                }
                // Nested DTO
                elseif ($type instanceof ReflectionNamedType
                    && !$type->isBuiltin()
                    && is_array($value)
                    && class_exists($type->getName())
                ) {
                    $value = self::map($type->getName(), $value);
                }

                $args[] = $value;
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                throw new \InvalidArgumentException(
                    "Missing required field: $name for $dtoClass"
                );
            }
        }

        return $ref->newInstanceArgs($args);
    }
}

class AddressDTO
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country = 'Azerbaijan'
    ) {}
}

class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly int $age,
        public readonly ?AddressDTO $address = null
    ) {}
}

// İstifadə
$data = [
    'name' => 'Orxan',
    'email' => 'orxan@test.com',
    'age' => '25', // String gəlir, int-ə cast olunacaq
    'address' => [
        'street' => 'Nizami küçəsi 10',
        'city' => 'Bakı',
    ],
];

$dto = DTOMapper::map(CreateUserDTO::class, $data);
// CreateUserDTO {
//   name: "Orxan",
//   email: "orxan@test.com",
//   age: 25,
//   address: AddressDTO { street: "Nizami küçəsi 10", city: "Bakı", country: "Azerbaijan" }
// }
```

### 2. Plugin System

*2. Plugin System üçün kod nümunəsi:*
```php
// Bu kod Reflection və Attribute ilə plugin sistem yaratmağı göstərir
<?php

#[\Attribute(\Attribute::TARGET_CLASS)]
class Plugin
{
    public function __construct(
        public string $name,
        public string $version = '1.0.0',
        public array $dependencies = []
    ) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Hook
{
    public function __construct(
        public string $event,
        public int $priority = 10
    ) {}
}

interface PluginInterface
{
    public function activate(): void;
    public function deactivate(): void;
}

#[Plugin(name: 'SEO Plugin', version: '2.1.0')]
class SeoPlugin implements PluginInterface
{
    #[Hook(event: 'page.render', priority: 5)]
    public function addMetaTags(Page $page): void
    {
        // Meta taglar əlavə et
    }

    #[Hook(event: 'page.render', priority: 10)]
    public function addStructuredData(Page $page): void
    {
        // Structured data əlavə et
    }

    #[Hook(event: 'sitemap.generate')]
    public function addToSitemap(Sitemap $sitemap): void
    {
        // Sitemap-ə əlavə et
    }

    public function activate(): void { }
    public function deactivate(): void { }
}

class PluginManager
{
    private array $plugins = [];
    private array $hooks = [];

    public function register(string $pluginClass): void
    {
        $ref = new ReflectionClass($pluginClass);

        // Plugin attribute-unu oxu
        $pluginAttrs = $ref->getAttributes(Plugin::class);
        if (empty($pluginAttrs)) {
            throw new \RuntimeException("$pluginClass does not have #[Plugin] attribute");
        }

        $pluginMeta = $pluginAttrs[0]->newInstance();

        // Plugin interface-ini implement edir?
        if (!$ref->implementsInterface(PluginInterface::class)) {
            throw new \RuntimeException("$pluginClass must implement PluginInterface");
        }

        $plugin = $ref->newInstance();
        $this->plugins[$pluginMeta->name] = [
            'instance' => $plugin,
            'meta' => $pluginMeta,
        ];

        // Hook-ları tap
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $hookAttrs = $method->getAttributes(Hook::class);

            foreach ($hookAttrs as $hookAttr) {
                $hook = $hookAttr->newInstance();
                $this->hooks[$hook->event][] = [
                    'plugin' => $pluginMeta->name,
                    'method' => $method->getName(),
                    'priority' => $hook->priority,
                    'callable' => [$plugin, $method->getName()],
                ];
            }
        }

        // Hook-ları priority-yə görə sırala
        foreach ($this->hooks as &$eventHooks) {
            usort($eventHooks, fn($a, $b) => $a['priority'] <=> $b['priority']);
        }

        $plugin->activate();
    }

    public function trigger(string $event, mixed ...$args): void
    {
        if (!isset($this->hooks[$event])) {
            return;
        }

        foreach ($this->hooks[$event] as $hook) {
            call_user_func($hook['callable'], ...$args);
        }
    }
}

// İstifadə
$manager = new PluginManager();
$manager->register(SeoPlugin::class);
$manager->trigger('page.render', $page);
```

### 3. Micro Framework Router

*3. Micro Framework Router üçün kod nümunəsi:*
```php
// Bu kod Reflection ilə attribute-based router yaratmağı göstərir
<?php

#[\Attribute(\Attribute::TARGET_CLASS)]
class Controller
{
    public function __construct(
        public string $prefix = ''
    ) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public string $method,
        public string $path,
        public array $middleware = []
    ) {}
}

class Router
{
    private array $routes = [];

    public function registerController(string $controllerClass): void
    {
        $ref = new ReflectionClass($controllerClass);

        $prefix = '';
        $controllerAttrs = $ref->getAttributes(Controller::class);
        if (!empty($controllerAttrs)) {
            $prefix = $controllerAttrs[0]->newInstance()->prefix;
        }

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttrs = $method->getAttributes(Route::class);

            foreach ($routeAttrs as $routeAttr) {
                $route = $routeAttr->newInstance();
                $fullPath = rtrim($prefix, '/') . '/' . ltrim($route->path, '/');

                $this->routes[] = [
                    'httpMethod' => $route->method,
                    'path' => $fullPath,
                    'controller' => $controllerClass,
                    'action' => $method->getName(),
                    'middleware' => $route->middleware,
                ];
            }
        }
    }

    public function dispatch(string $httpMethod, string $uri): mixed
    {
        foreach ($this->routes as $route) {
            if ($route['httpMethod'] === $httpMethod && $this->matchPath($route['path'], $uri)) {
                $controller = new $route['controller']();
                $method = new ReflectionMethod($controller, $route['action']);

                // Auto-inject dependencies
                $args = [];
                foreach ($method->getParameters() as $param) {
                    $type = $param->getType();
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $args[] = app()->make($type->getName());
                    }
                }

                return $method->invokeArgs($controller, $args);
            }
        }

        throw new \RuntimeException("Route not found: $httpMethod $uri");
    }

    private function matchPath(string $pattern, string $uri): bool
    {
        // Sadələşdirilmiş path matching
        return $pattern === $uri;
    }
}
```

---

## İntervyu Sualları

### Sual 1: Reflection API nədir və nə üçün istifadə olunur?
**Cavab:** Reflection API PHP-nin built-in funksionallığıdır, runtime zamanı class-lar, method-lar, property-lər, function-lar və parametrlər haqqında metadata əldə etməyə imkan verir. Əsas istifadə sahələri: Dependency Injection container-ləri (constructor parametrlərini analiz etmək), framework-larda auto-discovery (route, event, command tapma), testing (private üzvlərə daxil olma), code generation və PHP Attributes oxuma. Laravel-in Service Container-i Reflection-dan geniş istifadə edir — class-ın constructor parametrlərinin type hint-lərinə baxaraq dependency-ləri avtomatik resolve edir (auto-wiring).

### Sual 2: Laravel Service Container Reflection-ı necə istifadə edir?
**Cavab:** `app()->make(SomeClass::class)` çağırıldıqda Container belə işləyir:
1. `ReflectionClass` yaradır
2. Constructor-un olub-olmadığını yoxlayır
3. Constructor parametrlərini `ReflectionParameter` ilə analiz edir
4. Hər parametrin type hint-inə baxır
5. Əgər type hint class/interface-dirsə, onu rekursiv olaraq resolve edir (auto-wiring)
6. Əgər primitive type-dırsa və default dəyəri varsa, onu istifadə edir
7. `newInstanceArgs()` ilə class-ı yaradır

### Sual 3: Auto-wiring nədir? Hansı hallarda işləmir?
**Cavab:** Auto-wiring — container-in constructor type hint-lərinə baxaraq dependency-ləri avtomatik tapıb inject etmə qabiliyyətidir. İşləmir: 1) Primitive type parametrlər default dəyər olmadan (int $count), 2) Interface type hint olub binding qeydə alınmadıqda, 3) Circular dependency olduqda (A->B->A), 4) Type hint olmayan parametrlər.

### Sual 4: setAccessible(true) nə edir? PHP 8.1-dən sonra nə dəyişdi?
**Cavab:** `setAccessible(true)` private/protected üzvlərə Reflection ilə daxil olmaq üçün icazə verir. PHP 8.1-dən əvvəl bu çağırış olmadan private property/method-a daxil olmaq `ReflectionException` atırdı. PHP 8.1-dən etibarən Reflection avtomatik bütün visibility-lərə daxil ola bilir, ona görə `setAccessible(true)` çağırışı artıq lazım deyil (geriyə uyğunluq üçün mövcuddur, amma heç bir effekti yoxdur).

### Sual 5: PHP Attributes ilə DocBlock Annotations arasındakı fərq nədir?
**Cavab:** DocBlock Annotations string-based idi, parse etmək üçün xüsusi kitabxana lazım idi (Doctrine Annotations), yanlış yazılanda xəta vermirdi, IDE dəstəyi zəif idi. PHP 8.0 Attributes isə native dil funksionallığıdır, compile time-da yoxlanılır, tam IDE/static analysis dəstəyi var, `ReflectionAttribute` ilə oxunur, autoload ilə işləyir, tip təhlükəsizdir.

### Sual 6: Reflection-ın performance impact-ı nədir?
**Cavab:** Reflection əməliyyatları direct koddan 3-8x yavaşdır. Lakin: 1) ReflectionClass instance-ını cache etmək overhead-i azaldır, 2) Laravel resolved instance-ları cache edir, 3) Production-da `php artisan optimize` (route:cache, event:cache, config:cache) reflection nəticələrini cache edir, 4) OPcache preloading reflection sürətini artırır. Real dünyada reflection overhead əhəmiyyətsizdir, çünki I/O (database, network) əməliyyatları minlərlə dəfə daha yavaşdır.

### Sual 7: ReflectionClass ilə class_exists, is_subclass_of, instanceof arasındakı fərq nədir?
**Cavab:** `class_exists()`, `is_subclass_of()`, `instanceof` — sadə boolean yoxlamalar edir, çox sürətlidir. `ReflectionClass` isə tam metadata verir — method-lar, property-lər, parametrlər, attributes, visibility, abstract/final statusu və s. Sadə yoxlama üçün reflection istifadə etmək lazım deyil, amma dərin introspection lazımdırsa (məsələn, DI container, framework internals), Reflection yeganə seçimdir.

### Sual 8: Contextual Binding nədir və Reflection ilə necə əlaqəlidir?
**Cavab:** Contextual binding — eyni interface üçün fərqli class-larda fərqli implementation inject etmə. Məsələn:
***Cavab:** Contextual binding — eyni interface üçün fərqli class-larda üçün kod nümunəsi:*
```php
// Bu kod contextual binding ilə eyni interface üçün fərqli implementasiyalar inject etməyi göstərir
$this->app->when(PhotoController::class)
          ->needs(Filesystem::class)
          ->give(function () { return Storage::disk('local'); });

$this->app->when(VideoController::class)
          ->needs(Filesystem::class)
          ->give(function () { return Storage::disk('s3'); });
```
Laravel, resolve zamanı Reflection ilə hansı class üçün resolve edildiğini (buildStack) və parametrin type hint-ini analiz edir, sonra contextual binding-ə uyğun concrete class verir.

### Sual 9: Niyə private method-ları reflection ilə test etmək pis praktikadır?
**Cavab:** Private method-lar implementation detail-dir, public API-nin bir hissəsi deyil. Onları birbaşa test etmək tight coupling yaradır — refactoring zamanı testlər sınır, baxmayaraq ki, davranış dəyişməyib. Əvəzində, private method-ları dolayı yolla — public method-lar vasitəsilə test etmək lazımdır. Əgər private method çox mürəkkəbdirsə, bu, onu ayrı class-a çıxarmaq lazım olduğunun əlamətidir. Bununla belə, bəzi nadir hallarda (legacy kod) reflection ilə private method test etmək qaçılmaz ola bilər.

### Sual 10: ReflectionNamedType, ReflectionUnionType və ReflectionIntersectionType nədir?
**Cavab:**
- `ReflectionNamedType` — tək tip: `string`, `int`, `User`, `?string`
- `ReflectionUnionType` (PHP 8.0+) — birləşmə tip: `int|string`, `User|null`
- `ReflectionIntersectionType` (PHP 8.1+) — kəsişmə tip: `Countable&Iterator`
- `ReflectionUnionType` və `ReflectionIntersectionType` `getTypes()` metodu ilə daxili tiplərə ayrılır
- DNF types (PHP 8.2+) — `(Countable&Iterator)|null` kimi mürəkkəb tiplər

Container bu tipləri analiz edərək doğru dependency-ni seçir. Union type olduqda container ilk resolve edilə bilən tipi seçir, intersection type olduqda isə hər iki interface-i implement edən class-ı tapır.

### Sual 11: PHP Attributes (Annotasiyalar) real layihədə nə üçün istifadə olunur?

**Cavab:** PHP 8.0 native Attributes-lər (eskidən DocBlock annotations idi) bir çox real istifadə halına malikdir:
- **Route Attributes** (`#[Route('/api/users', methods: ['GET'])]`) — PHP 8-dən Laravel 10+ dəstəkləyir.
- **Validation Attributes** (`#[Required]`, `#[Email]`, `#[Min(1)]`) — spatie/laravel-data, Symfony Validator.
- **Serialization Attributes** (`#[JsonProperty('user_name')]`) — property adının serialization-dakı fərqli adını təyin edir.
- **Cache Attributes** (`#[Cached(ttl: 3600)]`) — method-a AOP-style caching əlavə etmək.
- **OpenAPI/Swagger Attributes** — API dokumentasiyası üçün.
- **ORM Mapping** (`#[Column(type: 'string')]`) — Doctrine ORM üçün.
Reflection ilə runtime-da oxunur: `$attr = $reflMethod->getAttributes(Cached::class)[0]?->newInstance()`.

### Sual 12: ReflectionClass-ı cache etmək niyə vacibdir?

**Cavab:** `new ReflectionClass($className)` hər çağırışda PHP-nin class metadata-sını parse edir — bu, sadə method call-dan 3-8x yavaşdır. Yüksək trafik layihələrindəki DI container-lər bu məlumatları cache-ləyir. Laravel Service Container resolved class-ların constructor metadata-sını `$this->buildStack` array-ında saxlayır. Manual istifadə üçün: statik array-da `ReflectionClass` instance-ları saxlayın, ya da APCu ilə serialize olunmuş metadata cache-ləyin. `php artisan optimize` route/event cache kimi Reflection nəticələrini PHP array-ına yazır — bu da runtime Reflection-ı aradan qaldırır.

---

## Anti-patternlər

**1. Hot Path-da Reflection İstifadəsi**
Hər request-də çağırılan kod yolunda `ReflectionClass` ilə metadata oxumaq — Reflection əməliyyatları nisbətən yavaşdır, yüksək trafik zamanı performans problemi yaranır. Reflection nəticəcərini cache-ləyin (APCu, opcache); boot zamanı bir dəfə işlənib nəticə saxlanılsın.

**2. Private Method-ları Reflection ilə Test Etmək**
`setAccessible(true)` ilə private metodlara test daxilindən müraciət etmək — implementation detail-ə bağlı testlər yaranır, refactoring zamanı sınır. Private metodları public interface vasitəsilə test edin; mürəkkəb private metod varsa, onu ayrı class-a çıxarın.

**3. Reflection ilə Production Kodunda Kapsulyanı Pozmaq**
Runtime-da başqa class-ın private property-sini Reflection ilə oxumaq/yazmaq — OOP encapsulation prinsipləri pozulur, gizli asılılıqlar yaranır, dəyişiklik zamanı gözlənilməz problemlər baş verir. Əgər xarici müraciət lazımdırsa, public API və ya getter/setter əlavə edin.

**4. `class_exists` Əvəzinə Reflection ilə Yoxlama**
Class-ın mövcudluğunu `ReflectionClass` exception-ı ilə yoxlamaq — try/catch əlavə overhead yaradır. `class_exists()` funksiyası daha sürətli və oxunaqlıdır; Reflection yalnız metadata lazım olduqda istifadə edin.

**5. Dinamik Class Yaratma üçün Reflection-u Sui-istifadə**
Sadə factory pattern əvəzinə `ReflectionClass::newInstanceArgs()` ilə dinamik obyekt yaratmaq — kod anlaşılmazlaşır, IDE dəstəyi itirilir, mürəkkəblik artır. Service Container, Factory ya da Strategy pattern bu işi daha aydın şəkildə həll edir.

**6. Annotation/Attribute Oxumağı Cache Etməmək**
Hər request-də method-ların `#[Attribute]`-larını `ReflectionMethod` ilə oxumaq — mürəkkəb class hierarxiyasında bu əməliyyat yavaşlayır. Attribute oxuma nəticələrini application cache-inə (Redis, file cache) yazın; deployment zamanı cache sıfırlansın.
