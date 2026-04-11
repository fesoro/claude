# PHP Attributes (Annotasiyalar)

## Mündəricat
1. [Attributes nədir?](#attributes-nədir)
2. [Built-in Attributes](#built-in-attributes)
3. [Custom Attribute Yaratmaq](#custom-attribute-yaratmaq)
4. [Reflection ilə Oxuma](#reflection-ilə-oxuma)
5. [Doctrine/Symfony ilə](#doctrinesymfony-ilə)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Attributes nədir?

```
PHP 8.0-da əlavə edildi. DocBlock annotation-ların yerini aldı.

DocBlock (köhnə):
  /**
   * @Route("/users", methods={"GET"})
   * @IsGranted("ROLE_ADMIN")
   */
  public function index() {}

Attribute (yeni):
  #[Route('/users', methods: ['GET'])]
  #[IsGranted('ROLE_ADMIN')]
  public function index() {}

Fərqlər:
  DocBlock → Runtime-da parse edilir (string parsing, yavaş)
  Attribute → PHP parser tərəfindən anlanır (native, sürətli)
  Attribute → Type-safe (class instance-dır)
  Attribute → IDE autocomplete ✅
  Attribute → Static analysis ✅
```

---

## Built-in Attributes

```php
// #[Attribute] — sinfin attribute olduğunu bildirir
#[Attribute]
class MyAttribute {}

// #[Deprecated] — PHP 8.4
#[Deprecated('Use newMethod() instead')]
function oldMethod(): void {}

// #[Override] — PHP 8.3 — parent method override-ı explicit edir
class Child extends Parent {
    #[Override]
    public function method(): void {} // Parent-da yoxdursa compile error!
}

// #[SensitiveParameter] — PHP 8.2 — stack trace-dən gizlər
function login(string $user, #[SensitiveParameter] string $password): void {}
// Stack trace-də: login('ali', <redacted>)

// #[AllowDynamicProperties] — PHP 8.2
#[AllowDynamicProperties]
class LegacyClass {}
```

---

## Custom Attribute Yaratmaq

```php
<?php
// Attribute target-ları:
// Attribute::TARGET_CLASS
// Attribute::TARGET_METHOD
// Attribute::TARGET_PROPERTY
// Attribute::TARGET_PARAMETER
// Attribute::TARGET_ALL

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public readonly string $path,
        public readonly array $methods = ['GET'],
        public readonly ?string $name = null,
    ) {}
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Middleware
{
    public function __construct(
        public readonly string $middleware,
    ) {}
}

// İstifadə
#[Middleware('auth')]
class UserController
{
    #[Route('/users', methods: ['GET'], name: 'users.index')]
    public function index(): Response {}

    #[Route('/users/{id}', methods: ['GET', 'HEAD'])]
    #[Middleware('cache:60')]
    public function show(int $id): Response {}
}
```

---

## Reflection ilə Oxuma

```php
<?php
// Attribute-ları runtime-da oxumaq
function getRoutes(string $controllerClass): array
{
    $reflection = new ReflectionClass($controllerClass);
    $routes = [];

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $routeAttrs = $method->getAttributes(Route::class);

        foreach ($routeAttrs as $attr) {
            $route = $attr->newInstance(); // Route object yaradır
            $routes[] = [
                'path'    => $route->path,
                'methods' => $route->methods,
                'handler' => [$controllerClass, $method->getName()],
            ];
        }
    }

    return $routes;
}

// Router registration
$routes = getRoutes(UserController::class);
foreach ($routes as $route) {
    $router->add($route['path'], $route['methods'], $route['handler']);
}
```

---

## Doctrine/Symfony ilə

```php
<?php
// Doctrine ORM Entity (Attribute-based)
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_users_email')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class, cascade: ['persist'])]
    private Collection $orders;
}

// Symfony Validator
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDto
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 100)]
    public string $password;
}
```

---

## PHP İmplementasiyası

```php
<?php
// Validation attribute sistemi (custom)
#[Attribute(Attribute::TARGET_PROPERTY)]
class Validate
{
    public function __construct(
        public readonly string $rule,
        public readonly mixed $value = null,
        public readonly string $message = '',
    ) {}
}

class CreateOrderRequest
{
    #[Validate('required')]
    #[Validate('integer')]
    public int $customerId;

    #[Validate('required')]
    #[Validate('array', message: 'Items array olmalıdır')]
    public array $items;

    #[Validate('min', 1)]
    public int $quantity = 1;
}

// Validator engine
class AttributeValidator
{
    public function validate(object $dto): array
    {
        $errors = [];
        $reflection = new ReflectionClass($dto);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($dto);

            foreach ($property->getAttributes(Validate::class) as $attr) {
                $validate = $attr->newInstance();
                $error = $this->check($validate->rule, $value, $validate->value);

                if ($error) {
                    $errors[$property->getName()][] = $validate->message ?: $error;
                }
            }
        }

        return $errors;
    }

    private function check(string $rule, mixed $value, mixed $param): ?string
    {
        return match($rule) {
            'required' => empty($value) ? 'Bu sahə tələb olunur' : null,
            'integer'  => !is_int($value) ? 'Integer olmalıdır' : null,
            'min'      => $value < $param ? "Minimum {$param} olmalıdır" : null,
            default    => null,
        };
    }
}
```

---

## İntervyu Sualları

- PHP Attribute-ları DocBlock annotation-lardan nəylə fərqlənir?
- `Attribute::TARGET_METHOD` vs `TARGET_CLASS` nə məna daşıyır?
- Attribute-ları runtime-da oxumaq üçün nə istifadə edilir?
- `#[Override]` attribute-u niyə faydalıdır?
- `#[SensitiveParameter]` nə işə yarayır?
- Attribute vs Interface — metadata ifadəsi üçün hansını nə vaxt seçərdiniz?
