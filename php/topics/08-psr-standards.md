# PSR (PHP Standard Recommendations) Standartları (Middle)

## PSR Nədir?

PSR (PHP Standard Recommendations) — PHP-FIG (PHP Framework Interoperability Group) tərəfindən hazırlanan standartlar toplusudur. Bu standartlar PHP ekosistemində müxtəlif framework və kitabxanaların bir-biri ilə uyğun işləməsini təmin edir.

**PHP-FIG nədir?**
- 2009-cu ildə yaradılmışdır
- Laravel, Symfony, Zend, Drupal, Joomla kimi böyük layihələrin iştirakçılarından ibarətdir
- Standartları voting (səsvermə) yolu ilə qəbul edir
- Hər PSR müəyyən bir problemi həll edir

**PSR-lərin statusları:**
- **Accepted** — qəbul edilmiş, istifadəyə tövsiyə olunur
- **Draft** — hazırlanır
- **Deprecated** — köhnəlmiş, yeni versiya ilə əvəz olunmuşdur
- **Abandoned** — tərk edilmişdir

---

## PSR-1: Basic Coding Standard

PSR-1 PHP faylları üçün ən əsas kodlaşdırma qaydalarını müəyyən edir.

### Əsas qaydalar:

**1. Fayl encoding və line endings:**
- Fayllar yalnız `<?php` və ya `<?=` tag-ları ilə başlamalıdır
- Fayllar UTF-8 encoding-də olmalıdır (BOM olmadan)
- Fayllar ya namespace/class elan etməlidir, ya da side-effects icra etməlidir — hər ikisini eyni zamanda etməməlidir

**2. Namespace və Class adları:**
- Namespace-lər mütləq bir üst-level "vendor" namespace-i izləməlidir
- Class adları `StudlyCaps` (PascalCase) formatında olmalıdır
- Class constants `UPPER_SNAKE_CASE` formatında olmalıdır
- Method adları `camelCase` formatında olmalıdır

*- Method adları `camelCase` formatında olmalıdır üçün kod nümunəsi:*
```php
<?php

namespace Vendor\Package;

use FooInterface;
use BarClass as Bar;

class Foo extends Bar implements FooInterface
{
    const VERSION = '1.0';
    const DATE_APPROVED = '2012-06-01';

    public function sampleMethod($a, $b = null)
    {
        if ($a === $b) {
            // ...
        }
    }
}
```

**Side-effect nümunəsi (YANLIŞ):**

```php
<?php
// YANLIŞ: class elan etmək + side-effect eyni faylda
ini_set('error_reporting', E_ALL); // side-effect

class Foo
{
    // ...
}

include 'another_file.php'; // side-effect
```

**Düzgün ayrılmış fayllar:**

```php
<?php
// config.php — yalnız side-effects
ini_set('error_reporting', E_ALL);
include 'functions.php';
```

*include 'functions.php'; üçün kod nümunəsi:*
```php
<?php
// Foo.php — yalnız class definition
namespace Vendor\Package;

class Foo
{
    // ...
}
```

---

## PSR-2: Coding Style Guide (Deprecated)

PSR-2 PSR-1 üzərində qurulmuş kod stil qaydaları idi. **PSR-12 ilə əvəz olunmuşdur.** Əsas qaydaları:

- 4 space indentation (tab yox)
- Sətir uzunluğu 80 simvol (soft limit), 120 simvol (hard limit)
- Namespace-dən sonra boş sətir
- `use` statement-lər ayrı-ayrı olmalıdır
- Açılan `{` brace — class/function üçün ayrı sətirdə, control structures üçün eyni sətirdə

---

## PSR-4: Autoloading Standard

PSR-4 namespace-lərin directory strukturuna necə map olunacağını müəyyən edir. Bu, `require`/`include` olmadan class-ların avtomatik yüklənməsini təmin edir.

### Mapping qaydası:

```
\<NamespaceName>(\<SubNamespaceNames>)*\<ClassName>
```

- Namespace prefix bir və ya bir neçə "base directory"-ə map olunur
- Namespace prefix-i çıxarıldıqdan sonra qalan hissə directory path-a çevrilir
- `\` — `/` ilə əvəz olunur
- Son `ClassName` — `.php` əlavə edilir

### Nümunə:

*Nümunə: üçün kod nümunəsi:*
```json
// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\": "database/",
            "Tests\\": "tests/"
        }
    }
}
```

```
App\Http\Controllers\UserController
→ app/Http/Controllers/UserController.php

App\Models\User
→ app/Models/User.php

Database\Factories\UserFactory
→ database/Factories/UserFactory.php
```

### Composer Autoloading növləri:

*Composer Autoloading növləri: üçün kod nümunəsi:*
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        },
        "psr-0": {
            "Vendor_Package_": "src/"
        },
        "classmap": [
            "src/legacy/",
            "src/helpers/HelperClass.php"
        ],
        "files": [
            "src/helpers.php",
            "src/constants.php"
        ]
    }
}
```

**PSR-0 (köhnə, deprecated):**
- PSR-4-dən əvvəlki standart
- Underscore (`_`) namespace separator kimi işlədilirdi
- `Vendor_Package_ClassName` → `Vendor/Package/ClassName.php`

**classmap:**
- Composer bütün `.php` fayllarını skan edir və class→file map-ı yaradır
- Hər dəfə yeni class əlavə edildikdə `composer dump-autoload` lazımdır
- Legacy kod üçün faydalıdır

**files:**
- Hər request-də avtomatik yüklənən fayllar
- Global functions, constants üçün istifadə olunur

*- Global functions, constants üçün istifadə olunur üçün kod nümunəsi:*
```php
<?php
// src/helpers.php
if (!function_exists('format_money')) {
    function format_money(int $amount, string $currency = 'AZN'): string
    {
        return number_format($amount / 100, 2) . ' ' . $currency;
    }
}
```

### PSR-4 autoloader tətbiqi (manual):

*PSR-4 autoloader tətbiqi (manual): üçün kod nümunəsi:*
```php
<?php

spl_autoload_register(function (string $class): void {
    // Namespace prefix
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    // Class prefix ilə başlayırmı?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Prefix-dən sonrakı hissəni götür
    $relativeClass = substr($class, $len);

    // Namespace separator-u directory separator-a çevir
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
```

### Composer autoload optimize etmək:

*Composer autoload optimize etmək: üçün kod nümunəsi:*
```bash
# Development
composer dump-autoload

# Production (classmap yaradır, daha sürətli)
composer dump-autoload --optimize
# və ya
composer install --optimize-autoloader

# Authoritative classmap (hətta miss-ları da cache-ləyir)
composer dump-autoload --classmap-authoritative
```

---

## PSR-7: HTTP Message Interface

PSR-7 HTTP mesajlarını (request, response) temsil etmek üçün interface-lər toplusudur. Bu interface-lər immutable (dəyişməz) dizayn prinsipinə əsaslanır.

### Əsas interface-lər:

```
Psr\Http\Message\MessageInterface
├── Psr\Http\Message\RequestInterface
│   └── Psr\Http\Message\ServerRequestInterface
└── Psr\Http\Message\ResponseInterface

Psr\Http\Message\UriInterface
Psr\Http\Message\StreamInterface
Psr\Http\Message\UploadedFileInterface
```

### MessageInterface:

*MessageInterface: üçün kod nümunəsi:*
```php
<?php

namespace Psr\Http\Message;

interface MessageInterface
{
    public function getProtocolVersion(): string;
    public function withProtocolVersion(string $version): static;

    public function getHeaders(): array;
    public function hasHeader(string $name): bool;
    public function getHeader(string $name): array;
    public function getHeaderLine(string $name): string;
    public function withHeader(string $name, $value): static;
    public function withAddedHeader(string $name, $value): static;
    public function withoutHeader(string $name): static;

    public function getBody(): StreamInterface;
    public function withBody(StreamInterface $body): static;
}
```

### ServerRequestInterface (geniş versiya):

*ServerRequestInterface (geniş versiya): üçün kod nümunəsi:*
```php
<?php

namespace Psr\Http\Message;

interface ServerRequestInterface extends RequestInterface
{
    public function getServerParams(): array;
    public function getCookieParams(): array;
    public function withCookieParams(array $cookies): static;
    public function getQueryParams(): array;
    public function withQueryParams(array $query): static;
    public function getUploadedFiles(): array;
    public function withUploadedFiles(array $uploadedFiles): static;
    public function getParsedBody();
    public function withParsedBody($data): static;
    public function getAttributes(): array;
    public function getAttribute(string $name, $default = null);
    public function withAttribute(string $name, $value): static;
    public function withoutAttribute(string $name): static;
}
```

### Immutability nümunəsi:

PSR-7 obyektləri immutable-dır — `with*` metodları orijinalı dəyişdirmir, yeni instance qaytarır:

*PSR-7 obyektləri immutable-dır — `with*` metodları orijinalı dəyişdirm üçün kod nümunəsi:*
```php
<?php

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;

$request = new Request('GET', 'https://api.example.com/users');

// Yeni header əlavə edilir — orijinal dəyişmir
$requestWithAuth = $request->withHeader(
    'Authorization',
    'Bearer ' . $token
);

// Yeni URI ilə yeni instance
$requestWithQuery = $requestWithAuth->withUri(
    new Uri('https://api.example.com/users?page=2')
);

// Orijinal request dəyişməyib
var_dump($request->hasHeader('Authorization')); // false
var_dump($requestWithAuth->hasHeader('Authorization')); // true
```

### Laravel-də PSR-7 istifadəsi:

Laravel öz `Illuminate\Http\Request` class-ını istifadə edir, lakin PSR-7 dəstəyi də mövcuddur:

*Laravel öz `Illuminate\Http\Request` class-ını istifadə edir, lakin PS üçün kod nümunəsi:*
```bash
composer require symfony/psr-http-message-bridge
composer require nyholm/psr7
```

*composer require nyholm/psr7 üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ApiController extends Controller
{
    // Laravel PSR-7 request-i avtomatik inject edir
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = $queryParams['page'] ?? 1;

        $users = User::paginate(15, ['*'], 'page', $page);

        return response()->json($users);
    }
}
```

*return response()->json($users); üçün kod nümunəsi:*
```php
<?php
// Route-da PSR-7 response qaytarmaq
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;

Route::get('/psr7', function (ServerRequestInterface $request) {
    $body = Stream::create(json_encode(['status' => 'ok']));

    return new Response(
        status: 200,
        headers: ['Content-Type' => 'application/json'],
        body: $body
    );
});
```

---

## PSR-11: Container Interface

PSR-11 Dependency Injection Container-lər üçün standart interface müəyyən edir. Bu sayədə müxtəlif container implementasiyaları birbirlə əvəz oluna bilər.

### Interface:

*Interface: üçün kod nümunəsi:*
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
```

### PSR-11-in əhəmiyyəti:

Container-dən asılı olmayan kod yaza bilmək üçün:

*Container-dən asılı olmayan kod yaza bilmək üçün üçün kod nümunəsi:*
```php
<?php

// PSR-11 olmadan — spesifik container-ə bağlıdır
use Illuminate\Container\Container;

function createService(Container $container): MyService
{
    return $container->make(MyService::class);
}

// PSR-11 ilə — istənilən container işləyir
use Psr\Container\ContainerInterface;

function createService(ContainerInterface $container): MyService
{
    return $container->get(MyService::class);
}
```

### Laravel Container PSR-11 uyğunluğu:

`Illuminate\Container\Container` `Psr\Container\ContainerInterface`-i implement edir:

*`Illuminate\Container\Container` `Psr\Container\ContainerInterface`-i  üçün kod nümunəsi:*
```php
<?php

use Illuminate\Container\Container;
use Psr\Container\ContainerInterface;

$container = Container::getInstance();

// PSR-11 metodları işləyir
var_dump($container->has(UserRepository::class)); // bool

$repo = $container->get(UserRepository::class); // instance qaytarır
```

### Öz Container implementasiyanızı yazmaq:

*Öz Container implementasiyanızı yazmaq: üçün kod nümunəsi:*
```php
<?php

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class SimpleContainer implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = function () use ($abstract, $factory) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = $factory($this);
            }
            return $this->instances[$abstract];
        };
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class("No entry found for '$id'") extends \RuntimeException
                implements NotFoundExceptionInterface {};
        }

        return ($this->bindings[$id])($this);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }
}

// İstifadəsi:
$container = new SimpleContainer();
$container->singleton(
    DatabaseConnection::class,
    fn($c) => new DatabaseConnection(config('database'))
);

$db = $container->get(DatabaseConnection::class);
```

---

## PSR-12: Extended Coding Style

PSR-12 PSR-2-nin yerinə keçib (PSR-2 deprecated-dir). PHP 7+ xüsusiyyətlərini əhatə edir.

### Əsas qaydalar:

*Əsas qaydalar: üçün kod nümunəsi:*
```php
<?php

declare(strict_types=1);

namespace Vendor\Package;

use Vendor\Package\{ClassA, ClassB, ClassC as C};
use Vendor\Package2\ClassD as D;

use function Vendor\Package\{functionA, functionB, functionC};
use const Vendor\Package\{CONSTANT_A, CONSTANT_B};

class Foo extends Bar implements
    FooInterface,
    BarInterface
{
    use SomeTrait;

    public const SAMPLE_CONSTANT = 'value';

    private int $property = 0;

    public function sampleFunction(
        int $arg1,
        string $arg2,
        ?string $arg3 = null,
    ): string {
        if ($arg1 === $arg2) {
            // ...
        } elseif ($arg1 > $arg2) {
            // ...
        } else {
            // ...
        }

        return match(true) {
            $arg1 > 10  => 'large',
            $arg1 > 5   => 'medium',
            default     => 'small',
        };
    }

    public function anotherFunction(
        string $arg1,
        int $arg2,
        int $arg3 = 0,
    ): void {
        // ...
    }
}
```

### Anonymous class, closure qaydaları:

*Anonymous class, closure qaydaları: üçün kod nümunəsi:*
```php
<?php

// Closure — açılan { eyni sətirdə
$closure = function (string $arg1, string $arg2) use ($var1): string {
    return $arg1 . $arg2 . $var1;
};

// Arrow function
$arrowFn = fn(int $x): int => $x * 2;

// Anonymous class
$object = new class (10) extends Foo implements FooInterface {
    public function __construct(private int $value) {}
};
```

### Attributes (PHP 8+):

*Attributes (PHP 8+): üçün kod nümunəsi:*
```php
<?php

#[Route('/api/users', methods: ['GET'])]
#[Middleware(AuthMiddleware::class)]
class UserController
{
    #[Get('/')]
    #[Cache(ttl: 300)]
    public function index(
        #[QueryParam] int $page = 1,
        #[QueryParam] int $perPage = 15,
    ): JsonResponse {
        // ...
    }
}
```

---

## PSR-14: Event Dispatcher Interface

PSR-14 event dispatching sistemi üçün standart interface-lər müəyyən edir.

### Interface-lər:

*Interface-lər: üçün kod nümunəsi:*
```php
<?php

namespace Psr\EventDispatcher;

// Event
interface StoppableEventInterface
{
    public function isPropagationStopped(): bool;
}

// Listener Provider — listener-ləri qaytarır
interface ListenerProviderInterface
{
    public function getListenersForEvent(object $event): iterable;
}

// Dispatcher — event-i dispatch edir
interface EventDispatcherInterface
{
    public function dispatch(object $event): object;
}
```

### Öz implementasiya nümunəsi:

*Öz implementasiya nümunəsi: üçün kod nümunəsi:*
```php
<?php

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private ListenerProviderInterface $listenerProvider
    ) {}

    public function dispatch(object $event): object
    {
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface
                && $event->isPropagationStopped()
            ) {
                break;
            }

            $listener($event);
        }

        return $event;
    }
}

class ListenerProvider implements ListenerProviderInterface
{
    private array $listeners = [];

    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function getListenersForEvent(object $event): iterable
    {
        $eventClass = get_class($event);
        return $this->listeners[$eventClass] ?? [];
    }
}

// Event class
class UserRegistered implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly int $userId,
        public readonly string $email
    ) {}

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}

// İstifadəsi:
$provider = new ListenerProvider();
$dispatcher = new EventDispatcher($provider);

$provider->addListener(UserRegistered::class, function (UserRegistered $event): void {
    echo "Welcome email sent to: {$event->email}\n";
});

$provider->addListener(UserRegistered::class, function (UserRegistered $event): void {
    echo "User analytics tracked for: {$event->userId}\n";
});

$dispatcher->dispatch(new UserRegistered(1, 'user@example.com'));
```

### Laravel-də PSR-14:

Laravel öz event sisteminə malikdir, lakin `psr/event-dispatcher` package-ini import edib PSR-14 uyğun işləyir:

*Laravel öz event sisteminə malikdir, lakin `psr/event-dispatcher` pack üçün kod nümunəsi:*
```php
<?php

namespace App\Events;

class OrderPlaced
{
    public function __construct(
        public readonly Order $order,
        public readonly User $user,
    ) {}
}
```

*public readonly User $user, üçün kod nümunəsi:*
```php
<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderConfirmation implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->user->email)->send(
            new OrderConfirmationMail($event->order)
        );
    }
}
```

---

## PSR-15: HTTP Server Request Handlers (Middleware)

PSR-15 HTTP middleware və request handler-lər üçün standart interface-lər müəyyən edir.

### Interface-lər:

*Interface-lər: üçün kod nümunəsi:*
```php
<?php

namespace Psr\Http\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Request Handler — final handler
interface RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}

// Middleware — araya girən component
interface MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface;
}
```

### Middleware pipeline nümunəsi:

*Middleware pipeline nümunəsi: üçün kod nümunəsi:*
```php
<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = $request->getHeaderLine('Authorization');

        if (empty($token) || !$this->isValidToken($token)) {
            return new Response(401, [], json_encode([
                'error' => 'Unauthorized'
            ]));
        }

        // Token-dan user məlumatını request-ə əlavə et
        $request = $request->withAttribute('user', $this->getUser($token));

        // Növbəti middleware/handler-ə ötür
        return $handler->handle($request);
    }

    private function isValidToken(string $token): bool
    {
        return str_starts_with($token, 'Bearer ') && strlen($token) > 10;
    }

    private function getUser(string $token): array
    {
        return ['id' => 1, 'name' => 'Test User'];
    }
}

class LoggingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $start = microtime(true);

        $response = $handler->handle($request);

        $duration = microtime(true) - $start;
        error_log(sprintf(
            '%s %s → %d (%.3fs)',
            $request->getMethod(),
            $request->getUri()->getPath(),
            $response->getStatusCode(),
            $duration
        ));

        return $response;
    }
}

// Middleware Pipeline
class Pipeline implements RequestHandlerInterface
{
    private array $middleware;
    private RequestHandlerInterface $finalHandler;
    private int $index = 0;

    public function __construct(
        array $middleware,
        RequestHandlerInterface $finalHandler
    ) {
        $this->middleware = $middleware;
        $this->finalHandler = $finalHandler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middleware[$this->index])) {
            return $this->finalHandler->handle($request);
        }

        $middleware = $this->middleware[$this->index];
        $next = clone $this;
        $next->index++;

        return $middleware->process($request, $next);
    }
}
```

---

## PSR-16: Simple Cache Interface

PSR-16 sadə key-value cache üçün standart interface-dir. PSR-6-dan (Cache Interface) daha sadədir.

### Interface:

*Interface: üçün kod nümunəsi:*
```php
<?php

namespace Psr\SimpleCache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function getMultiple(iterable $keys, mixed $default = null): iterable;
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool;
    public function deleteMultiple(iterable $keys): bool;
    public function has(string $key): bool;
}
```

### File-based implementasiya:

*File-based implementasiya: üçün kod nümunəsi:*
```php
<?php

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class FileCache implements CacheInterface
{
    public function __construct(
        private string $cacheDir,
        private string $prefix = 'cache_'
    ) {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getPath(string $key): string
    {
        return $this->cacheDir . '/' . $this->prefix . md5($key) . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $data = unserialize(file_get_contents($path));

        if ($data['expires'] !== null && $data['expires'] < time()) {
            unlink($path);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $expires = null;

        if ($ttl instanceof \DateInterval) {
            $expires = (new \DateTime())->add($ttl)->getTimestamp();
        } elseif (is_int($ttl)) {
            $expires = time() + $ttl;
        }

        $data = serialize(['value' => $value, 'expires' => $expires]);

        return file_put_contents($this->getPath($key), $data) !== false;
    }

    public function delete(string $key): bool
    {
        $path = $this->getPath($key);
        return !file_exists($path) || unlink($path);
    }

    public function clear(): bool
    {
        foreach (glob($this->cacheDir . '/' . $this->prefix . '*.cache') as $file) {
            unlink($file);
        }
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }
}
```

### Laravel Cache PSR-16 uyğunluğu:

*Laravel Cache PSR-16 uyğunluğu: üçün kod nümunəsi:*
```php
<?php

use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\CacheInterface;

// Laravel Cache PSR-16 CacheInterface-ini implement edir
$cache = app(CacheInterface::class);

$cache->set('user:1', ['name' => 'Orkhan', 'email' => 'test@test.com'], 3600);
$user = $cache->get('user:1');

// Və ya birbaşa PSR-16 uyğun istifadə:
$cache->setMultiple([
    'config:app'  => config('app'),
    'config:mail' => config('mail'),
], ttl: 86400);
```

---

## PSR-17: HTTP Factories

PSR-17 PSR-7 obyektlərini yaratmaq üçün factory interface-lər təmin edir.

### Interface-lər:

*Interface-lər: üçün kod nümunəsi:*
```php
<?php

namespace Psr\Http\Message;

interface RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface;
}

interface ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface;
}

interface ServerRequestFactoryInterface
{
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface;
}

interface StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface;
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface;
    public function createStreamFromResource($resource): StreamInterface;
}

interface UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface;
}

interface UploadedFileFactoryInterface
{
    public function createUploadedFile(
        StreamInterface $stream,
        int $size = null,
        int $error = \UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ): UploadedFileInterface;
}
```

### Nyholm PSR-17 implementasiyası ilə nümunə:

*Nyholm PSR-17 implementasiyası ilə nümunə: üçün kod nümunəsi:*
```php
<?php

use Nyholm\Psr7\Factory\Psr17Factory;

$factory = new Psr17Factory();

// Request yaratmaq
$request = $factory->createRequest('GET', 'https://api.example.com/users');
$request = $request->withHeader('Accept', 'application/json');

// Response yaratmaq
$body = $factory->createStream(json_encode(['users' => []]));
$response = $factory->createResponse(200)
    ->withHeader('Content-Type', 'application/json')
    ->withBody($body);

// URI yaratmaq
$uri = $factory->createUri('https://example.com')
    ->withPath('/api/v1/users')
    ->withQuery('page=1&per_page=15');
```

---

## PSR-18: HTTP Client

PSR-18 HTTP client-lər üçün standart interface-dir. Bu sayədə Guzzle, Symfony HttpClient və s. bir-birini əvəz edə bilər.

### Interface:

*Interface: üçün kod nümunəsi:*
```php
<?php

namespace Psr\Http\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * @throws ClientExceptionInterface
     * @throws NetworkExceptionInterface
     * @throws RequestExceptionInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
```

### Guzzle ilə PSR-18 nümunəsi:

*Guzzle ilə PSR-18 nümunəsi: üçün kod nümunəsi:*
```php
<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class UserApiClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private string $baseUrl,
    ) {}

    public function getUser(int $id): array
    {
        $request = $this->requestFactory
            ->createRequest('GET', "{$this->baseUrl}/users/{$id}")
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->getToken());

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                "API error: {$response->getStatusCode()}"
            );
        }

        return json_decode((string) $response->getBody(), true);
    }

    private function getToken(): string
    {
        return config('services.api.token');
    }
}

// Laravel Service Provider-da bind etmək:
$this->app->bind(ClientInterface::class, fn() => new Client([
    'timeout' => 30,
    'connect_timeout' => 5,
]));
```

---

## PSR-20: Clock Interface

PSR-20 mövcud vaxtı əldə etmək üçün sadə interface-dir. Testing zamanı vaxtı mock etmək üçün çox faydalıdır.

### Interface:

*Interface: üçün kod nümunəsi:*
```php
<?php

namespace Psr\Clock;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
```

### Nümunələr:

*Nümunələr: üçün kod nümunəsi:*
```php
<?php

use Psr\Clock\ClockInterface;

// Sistematik saat (production)
class SystemClock implements ClockInterface
{
    public function __construct(
        private \DateTimeZone $timezone = new \DateTimeZone('UTC')
    ) {}

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->timezone);
    }
}

// Test üçün frozen clock
class FrozenClock implements ClockInterface
{
    private \DateTimeImmutable $frozenAt;

    public function __construct(string|\DateTimeImmutable $time = 'now')
    {
        $this->frozenAt = is_string($time)
            ? new \DateTimeImmutable($time)
            : $time;
    }

    public function now(): \DateTimeImmutable
    {
        return $this->frozenAt;
    }

    public function advance(\DateInterval $interval): void
    {
        $this->frozenAt = $this->frozenAt->add($interval);
    }
}

// Servis - ClockInterface istifadə edir
class SubscriptionService
{
    public function __construct(
        private ClockInterface $clock,
        private SubscriptionRepository $repo,
    ) {}

    public function isExpired(Subscription $subscription): bool
    {
        return $subscription->expiresAt < $this->clock->now();
    }

    public function extendByMonth(Subscription $subscription): Subscription
    {
        $now = $this->clock->now();
        return $subscription->withExpiresAt(
            $now->add(new \DateInterval('P1M'))
        );
    }
}

// Test:
class SubscriptionServiceTest extends TestCase
{
    public function test_subscription_is_expired(): void
    {
        $clock = new FrozenClock('2025-01-15 12:00:00');
        $service = new SubscriptionService($clock, $this->mockRepo());

        $expiredSubscription = new Subscription(
            expiresAt: new \DateTimeImmutable('2025-01-10')
        );

        $this->assertTrue($service->isExpired($expiredSubscription));
    }
}
```

---

## Laravel-in PSR Uyğunluğu

Laravel bir çox PSR standartını dəstəkləyir:

| PSR    | Status | Qeyd |
|--------|--------|------|
| PSR-1  | Tam    | Laravel kodu PSR-1 qaydalarına uyğundur |
| PSR-2  | Köhnə  | PSR-12 istifadə olunur |
| PSR-3  | Tam    | `Psr\Log\LoggerInterface` — Laravel Log PSR-3 uyğundur |
| PSR-4  | Tam    | Composer autoloading |
| PSR-6  | Tam    | Cache pool (illuminate/cache) |
| PSR-7  | Qismən | symfony/psr-http-message-bridge ilə |
| PSR-11 | Tam    | `Illuminate\Container\Container` |
| PSR-12 | Tam    | Laravel kodu PSR-12 qaydalarına uyğundur |
| PSR-14 | Qismən | Laravel Event system PSR-14 ilhamlıdır |
| PSR-16 | Tam    | `Illuminate\Cache\Repository` |
| PSR-18 | Qismən | Guzzle ilə PSR-18 client istifadə olunur |

---

## PHP_CodeSniffer ilə PSR Enforcement

PHP_CodeSniffer (phpcs) kod stilini avtomatik yoxlayır.

### Quraşdırma:

*Quraşdırma: üçün kod nümunəsi:*
```bash
composer require --dev squizlabs/php_codesniffer

# Mövcud standard-ları görmək:
./vendor/bin/phpcs -i
# Çıxış: The installed coding standards are: MySource, PEAR, PSR1, PSR2, PSR12, Squiz, Zend
```

### İstifadəsi:

*İstifadəsi: üçün kod nümunəsi:*
```bash
# PSR-12 ilə yoxlamaq:
./vendor/bin/phpcs --standard=PSR12 app/

# Xüsusi faylları yoxlamaq:
./vendor/bin/phpcs --standard=PSR12 app/Http/Controllers/UserController.php

# Avtomatik düzəltmək (phpcbf):
./vendor/bin/phpcbf --standard=PSR12 app/

# Report formatı:
./vendor/bin/phpcs --standard=PSR12 --report=summary app/
./vendor/bin/phpcs --standard=PSR12 --report=json app/ > phpcs-report.json
```

### phpcs.xml konfiqurasiya:

*phpcs.xml konfiqurasiya: üçün kod nümunəsi:*
```xml
<?xml version="1.0"?>
<ruleset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         name="Project Coding Standard"
         xsi:noNamespaceSchemaLocation="./vendor/squizlabs/php_codesniffer/phpcs.xsd">

    <description>PSR-12 based coding standard</description>

    <!-- PSR-12 standard-ı əsas götür -->
    <rule ref="PSR12"/>

    <!-- Yoxlanılacaq fayllar -->
    <file>app</file>
    <file>tests</file>

    <!-- İstisna qovluqlar -->
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/storage/*</exclude-pattern>
    <exclude-pattern>database/migrations/*</exclude-pattern>

    <!-- Xüsusi qaydalar -->
    <rule ref="Generic.Files.LineLength">
        <properties>
            <property name="lineLimit" value="120"/>
            <property name="absoluteLineLimit" value="0"/>
        </properties>
    </rule>

    <!-- Xüsusi qayda söndürmək -->
    <rule ref="PSR1.Files.SideEffects.FoundWithSymbols">
        <exclude-pattern>bootstrap/*</exclude-pattern>
    </rule>
</ruleset>
```

---

## PHP-CS-Fixer ilə PSR Enforcement

PHP-CS-Fixer daha güclü bir alətdir — yalnız xəbərdarlıq vermir, həm də düzəldir.

### Quraşdırma:

*Quraşdırma: üçün kod nümunəsi:*
```bash
composer require --dev friendsofphp/php-cs-fixer
```

### .php-cs-fixer.php konfiqurasiya:

*.php-cs-fixer.php konfiqurasiya: üçün kod nümunəsi:*
```php
<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/tests',
        __DIR__ . '/database/factories',
        __DIR__ . '/database/seeders',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,

        // Əlavə qaydalar
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'single_trait_insert_per_statement' => true,
    ])
    ->setFinder($finder)
    ->setLineEnding("\n");
```

### İstifadəsi:

*İstifadəsi: üçün kod nümunəsi:*
```bash
# Yoxlamaq (dəyişiklik etmədən):
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Düzəltmək:
./vendor/bin/php-cs-fixer fix

# Xüsusi fayl:
./vendor/bin/php-cs-fixer fix app/Http/Controllers/UserController.php

# Verbose:
./vendor/bin/php-cs-fixer fix --verbose
```

### CI/CD pipeline inteqrasiyası (GitHub Actions):

*CI/CD pipeline inteqrasiyası (GitHub Actions): üçün kod nümunəsi:*
```yaml
# .github/workflows/code-style.yml
name: Code Style

on: [push, pull_request]

jobs:
  php-cs-fixer:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install --no-progress --no-suggest
      - run: ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

---

## İntervyu Sualları

**S: PSR-4 ilə PSR-0 arasındakı fərq nədir?**

C: PSR-0 köhnədir və deprecated-dir. Əsas fərqlər:
- PSR-0-da underscore (`_`) class adında namespace separator kimi işləyirdi: `Vendor_Package_Class` → `Vendor/Package/Class.php`
- PSR-4-də underscore sadəcə literal simvoldur
- PSR-4-də base directory-dən namespace prefix çıxarılır, PSR-0-da çıxarılmırdı
- PSR-4 daha sadə directory strukturuna imkan verir

**S: PSR-7-nin immutable olması niyə vacibdir?**

C: Immutability bir neçə üstünlük verir:
- Middleware pipeline-da request/response-u dəyişdirmək asandır — orijinal itmir
- Thread-safe kod yazmaq asanlaşır
- Debugging zamanı hər addımda state izlənilə bilər
- Unexpected side-effect-lər azalır

**S: PSR-11 Container Interface-in faydası nədir?**

C: Framework-dən asılı olmayan kod yazmağa imkan verir. Bir servis PSR-11 `ContainerInterface`-i qəbul etsə, Laravel, Symfony, Slim, ya da öz container-inizlə işləyə bilər. Bu, kitabxana müəllifləri üçün xüsusilə vacibdir.

**S: PSR-18 niyə lazımdır, Guzzle birbaşa istifadə etmək olmazmı?**

C: Guzzle birbaşa istifadə etsəniz, kod Guzzle-a bağlı olur. PSR-18 `ClientInterface`-i inject etsəniz:
- Test zamanı mock HTTP client istifadə edə bilərsiniz
- Guzzle-ı başqa bir library ilə (məsələn, Symfony HttpClient) əvəz edə bilərsiniz
- Kodun testability-si artır

**S: PSR-20 Clock Interface olmadan testing necə çətin olur?**

C: `new \DateTime('now')` birbaşa istifadə etsəniz, testi müəyyən bir vaxta "dondurmaq" mümkün olmur. `ClockInterface` inject edəndə `FrozenClock` ilə istənilən vaxtı simulate etmək olur. Bu, subscription expiry, scheduled tasks, time-based logic testləri üçün kritikdir.

**S: composer dump-autoload --optimize nə edir?**

C: Normal PSR-4 autoloading hər dəfə file system-də axtarış edir. `--optimize` flag-i bütün class-ları əvvəlcədən taparaq classmap yaradır. Bu, production-da autoloading-i əhəmiyyətli dərəcədə sürətləndirir, lakin yeni class əlavə etdikdə yenidən çalıştırılmalıdır.

**S: PSR-6 ilə PSR-16 arasındakı fərq nədir?**

C: PSR-6 daha mürəkkəb, PSR-16 daha sadədir:
- PSR-6-da `CacheItemPoolInterface` + `CacheItemInterface` — iki ayrı konsept var
- PSR-16-da sadəcə `CacheInterface` — birbaşa key-value əməliyyatları
- PSR-16 sadə use-case-lər üçün tövsiyə olunur
- Laravel `Cache::get()`, `Cache::put()` — PSR-16-a uyğundur

**S: PHP-CS-Fixer ilə phpcs arasındakı fərq nədir?**

C:
- `phpcs` — yalnız yoxlayır, xəbərdarlıq verir
- `phpcbf` — phpcs-in düzəldici hissəsidir, avtomatik düzəldir
- `php-cs-fixer` — həm yoxlayır, həm düzəldir; daha çox qaydaya sahibdir; konfigurasa PHP faylıdır (XML yox); daha aktiv inkişaf etdirilir

**S: PSR-14 Event Dispatcher-in `StoppableEventInterface`-in məqsədi nədir?**

C: Bəzi hallarda ilk listener event-i "tutub" digər listener-ların işləməsini dayandırmalıdır. Məsələn, authorization event-ı: birinci listener "əgər user blocked-dır, stop et" deyə bilər. `isPropagationStopped()` true qaytaranda dispatcher qalan listener-ları çağırmır.

---

## Anti-patternlər

**1. PSR standartlarını tamamilə ignore etmək**
Hər developer-in öz kod stilini işlətməsi — komandada kod bazasını oxumaq çətinləşir, code review vaxtı itirilir, onboarding uzanır. PSR-1 və PSR-12-ni layihə başında qəbul et, `.php-cs-fixer.php` konfiqurasiyasını repo-ya əlavə et.

**2. PSR-4 əvəzinə manual `require`/`include` işlətmək**
`require_once '../models/User.php'` kimi yolları əl ilə yazmaq — fayl strukturu dəyişdikdə bütün `require`-lar sınır, refactoring mümkünsüzləşir. Composer autoload + PSR-4 namespace strukturu istifadə et.

**3. `interface`-ləri PSR-ə uyğun olmayan adlandırmaq**
`Logger` əvəzinə `ILogger` və ya `LoggerClass` yazmaq — PSR-3 `LoggerInterface` adlandırma konvensiyasını pozur, paketlərarası uyumsuzluq yaranır. PSR-3-ü implement edən class-lar `LoggerInterface`-i type-hint kimi alsın.

**4. PSR-7 request obyektini mutate etmək**
`$request->setAttribute('user', $user)` kimi birbaşa dəyişdirmək — PSR-7 immutable dizayn tələb edir, mutation paylaşılan state-də gözlənilməz nəticələr verir. `$newRequest = $request->withAttribute('user', $user)` istifadə et.

**5. Cache key-lərini PSR-6/16 spesifikasiyasına uyğun olmayan simvollarla yaratmaq**
`{}()/\@:` simvolları olan cache key-lər işlətmək — bu simvollar PSR-6-da qadağandır, bəzi adapter-lər exception atar. Key generasiya üçün `md5()` və ya safe string metodları istifadə et.

**6. Event listener-ləri `StoppableEventInterface`-i nəzərə almadan yazmaq**
Bütün listener-ləri hər zaman işlədib propagation statusunu yoxlamamaq — dayandırılmış event-dən sonra da işlər görülür, authorization bypass risk yaranır. Dispatcher-i implement edərkən `isPropagationStopped()` yoxlanışını mütləq əlavə et.
