# DI Container Comparison — Laravel, Symfony, PHP-DI, Pimple

## Mündəricat
1. [DI container nədir?](#di-container-nədir)
2. [Service locator vs DI](#service-locator-vs-di)
3. [Laravel container](#laravel-container)
4. [Symfony container](#symfony-container)
5. [PHP-DI](#php-di)
6. [Pimple](#pimple)
7. [PSR-11 ContainerInterface](#psr-11-containerinterface)
8. [Auto-wiring deep](#auto-wiring-deep)
9. [Compiled vs runtime container](#compiled-vs-runtime-container)
10. [Tagged services](#tagged-services)
11. [İntervyu Sualları](#intervyu-sualları)

---

## DI container nədir?

```
DI (Dependency Injection) — obyektlərə dependency-lər kənardan verilir.
DI Container — dependency-ləri idarə edən, instance-ları construct edən sistem.

Manual DI:
  $logger = new Logger();
  $cache = new Cache();
  $service = new UserService($logger, $cache, new Db(...), new Config(...));
  // Manual wiring — kompleks app-də əzabverici

Container:
  $service = $container->get(UserService::class);
  // Container avtomatik dependency tree resolve edir

Container fayda:
  ✓ Dependency tree avtomatik
  ✓ Lifecycle (singleton, scoped, transient)
  ✓ Interface → implementation binding
  ✓ Configuration injection
  ✓ Decoration / interception
  ✓ Tagged services (event listeners, middleware)
  ✓ Auto-wiring (type-hint əsasında)
```

---

## Service locator vs DI

```php
<?php
// SERVICE LOCATOR (anti-pattern adətən)
class UserController
{
    public function show(int $id): Response
    {
        $service = app(UserService::class);   // container-i birbaşa çağır
        $user = $service->find($id);
        return response()->json($user);
    }
}
// Problem:
//  - Hidden dependency (controller içində service locator çağrısı)
//  - Test-də mock çətin (container-ı mock etməlisən)
//  - SOLID inversion of control pozulur

// DEPENDENCY INJECTION (good)
class UserController
{
    public function __construct(private UserService $service) {}
    
    public function show(int $id): Response
    {
        $user = $this->service->find($id);
        return response()->json($user);
    }
}
// Container: __construct-dakı tip əsasında auto-wire edir
```

---

## Laravel container

```php
<?php
// Bind binding
app()->bind(UserRepository::class, EloquentUserRepository::class);

// Singleton (one instance per request lifecycle)
app()->singleton(LoggerService::class, function ($app) {
    return new LoggerService(config('logging.channel'));
});

// Scoped (Laravel 9+) — per-request scope (Octane üçün vacib)
app()->scoped(RequestContext::class);

// Instance (existing object register)
app()->instance(SomeClass::class, $instance);

// When ... give (contextual binding)
app()->when(VideoController::class)
    ->needs(Filesystem::class)
    ->give(fn() => Storage::disk('s3'));

app()->when(PhotoController::class)
    ->needs(Filesystem::class)
    ->give(fn() => Storage::disk('local'));

// Tag
app()->tag([UserService::class, OrderService::class], 'services');
$services = app()->tagged('services');

// Resolve
$user = app(User::class);   // ya da app()->make(User::class)
$user = resolve(User::class);
```

```php
<?php
// Auto-wiring nümunəsi
class OrderService
{
    public function __construct(
        private DatabaseManager $db,   // Container resolve edir (auto)
        private LoggerInterface $log,   // Bind olunmuş interface
        private string $apiKey,         // Primitive — explicit binding lazım
    ) {}
}

// Primitive binding
app()->when(OrderService::class)
    ->needs('$apiKey')
    ->give(config('services.api.key'));

// Method injection
class HomeController
{
    public function index(UserRepository $users)   // method-da type-hint
    {
        return view('home', ['users' => $users->all()]);
    }
}
```

---

## Symfony container

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true              # auto-wire bütün service-ləri
        autoconfigure: true         # auto-tag (subscriber, command, etc.)
        public: false               # default: private (DI only)
    
    # Avtomatik service registration src/-dən
    App\:
        resource: '../src/'
        exclude:
            - '../src/{DependencyInjection,Entity,Migrations,Tests,Kernel.php}'
    
    # Specific binding
    App\Service\Mailer:
        arguments:
            $apiKey: '%env(MAILGUN_KEY)%'
            $defaultFrom: '%app.from_email%'
    
    # Interface → implementation
    App\Repository\UserRepositoryInterface:
        class: App\Repository\DoctrineUserRepository
    
    # Factory
    App\Service\Cache:
        factory: ['App\Factory\CacheFactory', 'create']
        arguments: ['%cache.driver%']
    
    # Tagged
    App\EventSubscriber\:
        resource: '../src/EventSubscriber/'
        tags: [kernel.event_subscriber]

# Parameters
parameters:
    app.from_email: 'noreply@example.com'
    app.timeout: 30
```

```php
<?php
// Service registration via PHP attributes (Symfony 6+)
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTaggedItem('app.notification_handler')]
class EmailHandler implements NotificationHandlerInterface
{
    public function __construct(
        #[Autowire('%env(SMTP_HOST)%')]
        private string $smtpHost,
    ) {}
}

// Tagged iterator inject
class NotificationDispatcher
{
    public function __construct(
        #[TaggedIterator('app.notification_handler')]
        private iterable $handlers,
    ) {}
}
```

---

## PHP-DI

```bash
composer require php-di/php-di
```

```php
<?php
use DI\Container;
use DI\ContainerBuilder;
use function DI\autowire;
use function DI\create;
use function DI\factory;
use function DI\get;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    // Auto-wire (default)
    UserService::class => autowire(),
    
    // Specific class
    LoggerInterface::class => autowire(MonologLogger::class),
    
    // Constructor parameters
    Mailer::class => autowire()
        ->constructorParameter('apiKey', getenv('MAILGUN_KEY')),
    
    // Factory
    Cache::class => factory(function (Container $c) {
        return new RedisCache($c->get('redis.client'));
    }),
    
    // Reference
    'mailer' => get(Mailer::class),
    
    // Value
    'app.timeout' => 30,
]);

// Compilation (production)
$builder->enableCompilation('/var/cache');
$builder->writeProxiesToFile(true, '/var/cache/proxies');

$container = $builder->build();
$service = $container->get(UserService::class);
```

```
PHP-DI üstünlükləri:
  ✓ PHP-də konfiqurasiya (annotation əvəzinə type-hint)
  ✓ Compilable (production performance)
  ✓ Auto-wiring güclü
  ✓ PSR-11 uyumlu
  ✓ Symfony və Laravel-dən kənar mühitlərdə əla
```

---

## Pimple

```bash
composer require pimple/pimple
```

```php
<?php
// Pimple — Silex (Symfony micro framework) container-i
// Sadə, lightweight (~500 sətir kod)

use Pimple\Container;

$container = new Container();

// Service (singleton by default)
$container['logger'] = function ($c) {
    return new Logger('app');
};

// Factory (yeni instance hər dəfə)
$container['session'] = $container->factory(function ($c) {
    return new Session();
});

// Parameter
$container['app.timeout'] = 30;

// Read
$logger = $container['logger'];
$logger2 = $container['logger'];
$logger === $logger2;       // true (singleton)

$s1 = $container['session'];
$s2 = $container['session'];
$s1 === $s2;                // false (factory)

// Extend (decorator)
$container->extend('logger', function ($logger, $c) {
    return new BufferedLogger($logger);
});
```

```
Pimple xüsusiyyətləri:
  ✓ Çox yüngül (no auto-wiring, no compile)
  ✓ Sadə API
  ✗ Manual definition hər service üçün
  ✗ Auto-wiring yoxdur (modern app üçün ağır)
  
  Use case: legacy code, micro-app, learning
```

---

## PSR-11 ContainerInterface

```php
<?php
namespace Psr\Container;

interface ContainerInterface
{
    public function get(string $id): mixed;
    public function has(string $id): bool;
}

interface NotFoundExceptionInterface extends ContainerExceptionInterface {}
interface ContainerExceptionInterface extends \Throwable {}

// Bütün modern container-lar PSR-11 implement edir.
// Library yazırsansa — PSR-11 inject et, container-specific koddan qaç:

class MyService
{
    public function __construct(private ContainerInterface $container) {}
    
    public function doStuff(): void
    {
        $repo = $this->container->get('user.repository');
    }
}
// İstənilən PSR-11 container işləyəcək (Symfony, Laravel, PHP-DI, ...)
```

---

## Auto-wiring deep

```
Auto-wiring — container constructor parameter type-hint-lərini oxuyub
            avtomatik dependency resolve edir.

Algorithm:
  1. ReflectionClass(TargetClass)
  2. Constructor parameter-lərini oxu
  3. Hər parameter:
     - Type-hint var → recursively resolve($type)
     - Default dəyər var → istifadə et
     - Optional → null
     - Heç biri yoxdur → ContainerException
  4. new TargetClass(...$resolvedParams)

Edge cases:
  - Interface type-hint → bind olunmuş implementation lazım
  - Primitive (int, string) → bind explicit lazım və ya default
  - Variadic (...$args) → empty array (Laravel)
  - Union types → ilk supportlanan (PHP 8+)
  - Self-referencing → infinite loop, exception

Performance:
  Reflection işi yavaşdır (mikro-saniyələr).
  Production-da:
    - Symfony: container compile (PHP file-a generate)
    - Laravel: cache (config:cache, route:cache)
    - PHP-DI: enableCompilation()
```

---

## Compiled vs runtime container

```
RUNTIME container (Laravel default):
  Hər request:
    1. ReflectionClass çağırılır
    2. Constructor parameter analizi
    3. Resolve loop
  
  ✓ Sadə, dynamic
  ✗ Performance overhead (mikrosaniyələr per service)

COMPILED container (Symfony default, PHP-DI optional):
  Build vaxtı:
    1. Bütün service definition-lar oxunur
    2. PHP class generate olunur (CompiledContainer.php)
    3. Hər get() method explicit yazılmış kodu icra edir
  
  Runtime:
    public function getUserService() {
        return new UserService(
            $this->getDatabaseService(),
            $this->getLoggerService()
        );
    }
  
  ✓ Çox sürətli (~5x runtime-dan)
  ✓ Type-safe (compile-time error)
  ✗ Build addımı lazım
  ✗ Runtime-da dynamic registration çətin

Symfony-da compile:
  bin/console cache:clear
  // var/cache/prod/Container*/...PHP class-lar
```

---

## Tagged services

```php
<?php
// Symfony — tagged + iterator injection
// services.yaml
// App\Notification\:
//     resource: '../src/Notification/'
//     tags: [app.notification_channel]

// İnject all tagged
class NotificationDispatcher
{
    public function __construct(
        #[TaggedIterator('app.notification_channel')]
        private iterable $channels,
    ) {}
    
    public function send(Notification $n): void
    {
        foreach ($this->channels as $channel) {
            $channel->send($n);
        }
    }
}

// Use case: plugin architecture
//   Email channel, SMS channel, Push channel — her biri tag ilə register
//   Yeni channel = yeni class + tag, dispatcher dəyişmir
```

---

## İntervyu Sualları

- DI container ilə service locator arasındakı fərq?
- Auto-wiring necə işləyir? Hansı reflection function istifadə olunur?
- Singleton, scoped, transient binding fərqi?
- PSR-11 niyə vacibdir library yazanda?
- Compiled container niyə daha sürətlidir?
- Symfony və Laravel container müqayisəsi?
- PHP-DI nə vaxt seçilir Symfony/Laravel əvəzinə?
- Tagged services nəyə xidmət edir?
- Contextual binding nədir? Nümunə verin.
- Circular dependency container-də necə həll olunur?
- Primitive parameter inject necə edilir?
- "Service locator anti-pattern" nə vaxt qaçılmazdır?
