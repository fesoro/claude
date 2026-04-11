# Dependency Injection vs Service Locator

## Mündəricat
1. [Dependency Injection nədir](#dependency-injection-nədir)
2. [DI növləri](#di-növləri)
3. [Service Locator nədir](#service-locator-nədir)
4. [Anti-pattern olaraq Service Locator](#anti-pattern-olaraq-service-locator)
5. [Fərqlər: Testability, Explicitness, Coupling](#fərqlər)
6. [IoC Container nədir](#ioc-container-nədir)
7. [Laravel Service Container internals](#laravel-service-container-internals)
8. [Binding növləri](#binding-növləri)
9. [Auto-wiring və Reflection API](#auto-wiring-və-reflection-api)
10. [Method Injection](#method-injection)
11. [Contextual Binding](#contextual-binding)
12. [Tagged Bindings](#tagged-bindings)
13. [Extending Bindings](#extending-bindings)
14. [Service Locator nə vaxt məqbuldur](#service-locator-nə-vaxt-məqbuldur)
15. [Laravel Facades — Service Locator mi, DI mi?](#laravel-facades)
16. [Real-time Facades](#real-time-facades)
17. [Testing: DI vs Service Locator](#testing-di-vs-service-locator)
18. [Bad → Good code transformasiyaları](#bad--good-code-transformasiyaları)
19. [Müsahibə sualları və cavabları](#müsahibə-sualları-və-cavabları)

---

## Dependency Injection nədir

Dependency Injection (DI) — bir obyektin ehtiyac duyduğu asılılıqların (dependencies) həmin obyektin özü tərəfindən yaradılmaq əvəzinə, xaricdən ötürülməsi prinsipidir. Bu, SOLID prinsiplərindən **Dependency Inversion Principle (DIP)** nin praktik tətbiqidir.

### Əsas fikir

*Əsas fikir üçün kod nümunəsi:*
```php
// YANLŞ: sinif öz asılılığını özü yaradır (tightly coupled)
class OrderService
{
    private PaymentGateway $gateway;

    public function __construct()
    {
        // OrderService, Stripe-a birbaşa bağlıdır
        $this->gateway = new StripeGateway(config('stripe.key'));
    }
}

// DÜZGÜN: asılılıq xaricdən verilir (loosely coupled)
class OrderService
{
    public function __construct(
        private PaymentGateway $gateway // interface
    ) {}
}
```

DI-nin məqsədi:
- Siniflər arasında **loose coupling** yaratmaq
- **Testability** artırmaq (mock/stub asan yaratmaq)
- Kodu daha **explicit** etmək (sinifin nəyə ehtiyac duyduğu açıq görünür)
- **Single Responsibility** saxlamaq (sinif asılılığını özü idarə etməsin)

---

## DI növləri

### 1. Constructor Injection (Ən tövsiyə edilən)

Asılılıq sinifin constructor-unda qəbul edilir. Bu, ən çox istifadə olunan və ən tövsiyə edilən üsuldur.

*Asılılıq sinifin constructor-unda qəbul edilir. Bu, ən çox istifadə ol üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\OrderRepositoryInterface;
use App\Models\Order;
use App\DTOs\PaymentResult;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface    $orderRepository,
        private readonly PaymentGatewayInterface     $paymentGateway,
        private readonly NotificationServiceInterface $notificationService,
    ) {}

    public function processOrder(Order $order): PaymentResult
    {
        $result = $this->paymentGateway->charge(
            amount: $order->total,
            currency: $order->currency,
        );

        if ($result->successful()) {
            $this->orderRepository->markAsPaid($order);
            $this->notificationService->sendReceipt($order);
        }

        return $result;
    }
}
```

**Constructor injection-in üstünlükləri:**
- Sinif yaradılanda bütün asılılıqlar məcburi olaraq verilməlidir
- Sinif heç vaxt natamam vəziyyətdə mövcud ola bilməz
- `readonly` ilə immutable asılılıqlar yaratmaq olar
- IDE-lər type-hint-ə görə autocomplete edir

*- IDE-lər type-hint-ə görə autocomplete edir üçün kod nümunəsi:*
```php
// Service Provider-də binding
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OrderRepositoryInterface::class,
            EloquentOrderRepository::class
        );

        $this->app->bind(
            PaymentGatewayInterface::class,
            StripePaymentGateway::class
        );
    }
}
```

---

### 2. Method (Setter) Injection

Asılılıq bir method vasitəsilə sonradan ötürülür. Optional asılılıqlar üçün istifadə edilir.

*Asılılıq bir method vasitəsilə sonradan ötürülür. Optional asılılıqlar üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

use App\Contracts\LoggerInterface;
use App\Contracts\CacheInterface;
use Psr\Log\NullLogger;

class ProductService
{
    private LoggerInterface $logger;
    private ?CacheInterface $cache = null;

    public function __construct(
        private readonly ProductRepository $repository
    ) {
        // Default: heç nə log etməyən null logger
        $this->logger = new NullLogger();
    }

    // Optional asılılıq - setter injection
    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this; // fluent interface
    }

    // Optional asılılıq - setter injection
    public function setCache(CacheInterface $cache): static
    {
        $this->cache = $cache;
        return $this;
    }

    public function findProduct(int $id): ?Product
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get("product:{$id}");
            if ($cached !== null) {
                $this->logger->debug("Cache hit for product {$id}");
                return $cached;
            }
        }

        $this->logger->info("Fetching product {$id} from database");
        return $this->repository->find($id);
    }
}

// İstifadə
$service = new ProductService($repository);
$service->setLogger($logger)->setCache($cache);
```

**Nə zaman method injection istifadə edilir:**
- Asılılıq optional olduqda
- Sinif asılılıq olmadan da işləyə bildiyi halda
- Fluent interface lazım olduqda

---

### 3. Property (Field) Injection

Asılılıq birbaşa property-ə inject edilir. PHP-də birbaşa dəstəklənmir, lakin Laravel-in `#[Inject]` attribute-u və ya bəzi framework-lər annotation/attribute ilə həyata keçirir.

*Asılılıq birbaşa property-ə inject edilir. PHP-də birbaşa dəstəklənmir üçün kod nümunəsi:*
```php
<?php

// Symfony-də annotation ilə (reference üçün)
use Symfony\Contracts\Service\Attribute\Required;

class ReportGenerator
{
    #[Required]
    public LoggerInterface $logger;

    #[Required]
    public MailerInterface $mailer;
}

// Laravel-də property injection TÖVSIYƏ EDİLMİR
// Lakin bəzən görünür:
class SomeController extends Controller
{
    // Bu yanlışdır - Laravel bunu auto-inject etmir
    // public LoggerInterface $logger; // ← işləməz
}
```

**Property injection-un problemləri:**
- PHP-nin native DI sistemi bunu dəstəkləmir
- Sinif tam inisiasiya olmadan natamam qalır
- `readonly` istifadə edilə bilmir
- Unit testdə asılılığı əvəz etmək çətindir

---

## Service Locator nədir

Service Locator — bir registry/container saxlayan və istənilən yerdən `get(SomeClass::class)` çağıraraq asılılıq əldə etməyə imkan verən pattern-dir.

*Service Locator — bir registry/container saxlayan və istənilən yerdən  üçün kod nümunəsi:*
```php
<?php

// Sadə Service Locator nümunəsi
class ServiceLocator
{
    private static array $services = [];

    public static function register(string $abstract, callable $factory): void
    {
        self::$services[$abstract] = $factory;
    }

    public static function get(string $abstract): mixed
    {
        if (!isset(self::$services[$abstract])) {
            throw new \RuntimeException("Service not found: {$abstract}");
        }

        return (self::$services[$abstract])();
    }
}

// Qeydiyyat
ServiceLocator::register(PaymentGateway::class, fn() => new StripeGateway());

// İstifadə - bu yerə DI olmalıydı!
class OrderService
{
    public function processOrder(Order $order): void
    {
        // Asılılıq gizlənib - xaricdən görünmür!
        $gateway = ServiceLocator::get(PaymentGateway::class);
        $gateway->charge($order->total);
    }
}
```

---

## Anti-pattern olaraq Service Locator

Service Locator pattern-i **anti-pattern** hesab edilir, çünki:

### Problem 1: Hidden Dependencies (Gizli asılılıqlar)

*Problem 1: Hidden Dependencies (Gizli asılılıqlar) üçün kod nümunəsi:*
```php
// BAD: OrderService-in nəyə ehtiyacı olduğunu bilmirsiniz
class OrderService
{
    public function process(Order $order): void
    {
        $gateway  = app(PaymentGateway::class);   // gizli
        $mailer   = app(Mailer::class);            // gizli
        $repo     = app(OrderRepository::class);   // gizli
        $logger   = app(Logger::class);            // gizli
        $cache    = app(Cache::class);             // gizli

        // Sinifin constructor-una baxsanız, heç nə görməzsiniz
        // Bütün asılılıqlar method içindədir
    }
}

// GOOD: Asılılıqlar açıq şəkildə görünür
class OrderService
{
    public function __construct(
        private readonly PaymentGateway    $gateway,
        private readonly Mailer            $mailer,
        private readonly OrderRepository   $repo,
        private readonly LoggerInterface   $logger,
        private readonly CacheInterface    $cache,
    ) {}
}
```

### Problem 2: Testability çətinliyi

*Problem 2: Testability çətinliyi üçün kod nümunəsi:*
```php
// BAD: Test yazmaq çox çətindir
class OrderServiceTest extends TestCase
{
    public function test_process_order(): void
    {
        // Service Locator ilə mock etmək üçün
        // container-ı manipulasiya etməliyik
        $this->app->bind(PaymentGateway::class, fn() => new FakePaymentGateway());
        $this->app->bind(Mailer::class, fn() => new FakeMailer());
        // ... hər asılılıq üçün ayrı-ayrı

        $service = new OrderService(); // constructor boşdur
        $service->process($order);
    }
}

// GOOD: DI ilə test sadədir
class OrderServiceTest extends TestCase
{
    public function test_process_order(): void
    {
        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->expects($this->once())->method('charge');

        $service = new OrderService(
            gateway: $gateway,
            mailer: $this->createMock(Mailer::class),
            // ...
        );

        $service->process($order);
    }
}
```

### Problem 3: Coupling to Container

*Problem 3: Coupling to Container üçün kod nümunəsi:*
```php
// Service Locator istifadə edən sinif
// container-a (Laravel/Symfony/etc) bağlıdır
class OrderService
{
    public function process(): void
    {
        $gateway = app(PaymentGateway::class); // Laravel-ə bağlıdır!
        // Bu sinifi başqa layihədə istifadə etmək üçün
        // Laravel container-ını da aparmaq lazımdır
    }
}
```

---

## Fərqlər

### Testability müqayisəsi

| Xüsusiyyət | DI | Service Locator |
|---|---|---|
| Mock etmək | Constructor-a verin | Container-ı manipulasiya edin |
| Test izolyasiyası | Tam | Çətin |
| PHPUnit mock | Asan | Çətin |
| Parallel testlər | Problemsiz | Container state problemi |

### Explicitness (Açıqlıq)

*Explicitness (Açıqlıq) üçün kod nümunəsi:*
```php
// DI: Explicit - bir baxışda bütün asılılıqlar görünür
public function __construct(
    private readonly PaymentGateway    $gateway,     // ← açıq
    private readonly NotificationService $notifier,  // ← açıq
    private readonly OrderRepository   $orders,      // ← açıq
) {}

// Service Locator: Implicit - asılılıqlar gizlənib
public function someMethod(): void
{
    app(PaymentGateway::class);      // ← gizli
    app(NotificationService::class); // ← gizli
    app(OrderRepository::class);     // ← gizli
}
```

### Coupling

*Coupling üçün kod nümunəsi:*
```php
// Service Locator: container-a bağlıdır
class PaymentService
{
    public function pay(float $amount): void
    {
        $logger = app('logger'); // Laravel container-a coupled
    }
}

// DI: yalnız interface-ə bağlıdır
class PaymentService
{
    public function __construct(
        private readonly LoggerInterface $logger // sadece PSR-3-ə coupled
    ) {}
}
```

---

## IoC Container nədir

Inversion of Control (IoC) Container — sinifləri və onların asılılıqlarını avtomatik olaraq quran bir sistemdir. "Inversion" — əvvəlcə sinif öz asılılığını özü qururdu, indi bu məsuliyyət container-a verildi.

```
Ənənəvi axın:
  OrderService → new StripeGateway() → new HttpClient() → ...

IoC Container ilə:
  Container → OrderService yaradır
            → PaymentGateway interface-ini görür
            → StripeGateway bind olduğunu bilir
            → StripeGateway yaradır (rekursiv)
            → OrderService-ə verir
```

*→ OrderService-ə verir üçün kod nümunəsi:*
```php
<?php

// Sadə IoC Container implementasiyası (konsept üçün)
class Container
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

    public function make(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract])($this);
        }

        // Auto-wiring: Reflection ilə avtomatik resolve
        return $this->build($abstract);
    }

    private function build(string $concrete): mixed
    {
        $reflector = new \ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Cannot instantiate {$concrete}");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete;
        }

        $dependencies = array_map(
            fn(\ReflectionParameter $param) => $this->resolveDependency($param),
            $constructor->getParameters()
        );

        return $reflector->newInstanceArgs($dependencies);
    }

    private function resolveDependency(\ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new \Exception("Cannot resolve parameter: {$param->getName()}");
    }
}
```

---

## Laravel Service Container internals

Laravel-in Service Container-i `Illuminate\Container\Container` sinfindədir. Əsas mexanizmlər:

*Laravel-in Service Container-i `Illuminate\Container\Container` sinfin üçün kod nümunəsi:*
```php
<?php

// Laravel container-ının sadələşdirilmiş daxili strukturu
namespace Illuminate\Container;

class Container
{
    // Bütün binding-lər burada saxlanılır
    protected array $bindings = [];

    // Singleton instance-lar burada
    protected array $instances = [];

    // Alias-lar (string → class map)
    protected array $aliases = [];

    // Contextual binding-lər
    protected array $contextual = [];

    // Extension-lar (decorator pattern)
    protected array $extenders = [];

    // Tagged binding-lər
    protected array $tags = [];

    // Rebound callback-lər
    protected array $reboundCallbacks = [];

    // Build stack (circular dependency detection üçün)
    protected array $buildStack = [];
}
```

### Container-in resolve axını

*Container-in resolve axını üçün kod nümunəsi:*
```php
// app(PaymentGateway::class) çağırıldıqda nə baş verir?

// 1. make() çağırılır
public function make(string $abstract, array $parameters = []): mixed
{
    return $this->resolve($abstract, $parameters);
}

// 2. resolve() - alias yoxlanır, binding tapılır
protected function resolve(string $abstract, array $parameters = []): mixed
{
    $abstract = $this->getAlias($abstract); // alias varsa, real sinfi tap

    // Contextual binding yoxla
    if ($this->hasContextualBinding($abstract)) {
        // ...
    }

    // Singleton instance varsa, onu qaytar
    if (isset($this->instances[$abstract]) && empty($parameters)) {
        return $this->instances[$abstract];
    }

    // Concrete sinfi tap (binding varsa oradan, yoxsa özündən)
    $concrete = $this->getConcrete($abstract);

    // Build et
    if ($this->isBuildable($concrete, $abstract)) {
        $object = $this->build($concrete);
    } else {
        $object = $this->make($concrete); // rekursiv
    }

    // Extenders tətbiq et
    foreach ($this->getExtenders($abstract) as $extender) {
        $object = $extender($object, $this);
    }

    // Singleton ise, cache et
    if ($this->isShared($abstract)) {
        $this->instances[$abstract] = $object;
    }

    return $object;
}
```

---

## Binding növləri

### 1. `bind` — hər dəfə yeni instance

*1. `bind` — hər dəfə yeni instance üçün kod nümunəsi:*
```php
// Hər resolve-da yeni obyekt yaradılır
$this->app->bind(PaymentGateway::class, StripeGateway::class);

// Factory closure ilə
$this->app->bind(PaymentGateway::class, function (Application $app): PaymentGateway {
    return new StripeGateway(
        apiKey: config('services.stripe.key'),
        logger: $app->make(LoggerInterface::class),
    );
});

// Sübut: hər dəfə fərqli instance
$a = app(PaymentGateway::class);
$b = app(PaymentGateway::class);
var_dump($a === $b); // false
```

### 2. `singleton` — bir dəfə yaranır, sonra cache-dən

*2. `singleton` — bir dəfə yaranır, sonra cache-dən üçün kod nümunəsi:*
```php
// Tətbiq boyunca tək instance
$this->app->singleton(DatabaseConnection::class, function (Application $app) {
    return new DatabaseConnection(
        host: config('database.host'),
        port: config('database.port'),
    );
});

// Sübut: eyni instance
$a = app(DatabaseConnection::class);
$b = app(DatabaseConnection::class);
var_dump($a === $b); // true
```

### 3. `scoped` — request/scope boyunca tək instance

Laravel 8.x+ ilə gəldi. HTTP request boyunca singleton kimi davranır, növbəti request-də sıfırlanır.

*Laravel 8.x+ ilə gəldi. HTTP request boyunca singleton kimi davranır,  üçün kod nümunəsi:*
```php
// Request scope-da singleton
$this->app->scoped(RequestContext::class, function (Application $app) {
    return new RequestContext(
        requestId: Str::uuid()->toString(),
        timestamp: now(),
    );
});

// Octane ilə işləyərkən vacibdir:
// hər request yeni RequestContext alır, lakin eyni request daxilində
// eyni instance istifadə edilir
```

### 4. `instance` — mövcud obyekti container-a qeydiyyat et

*4. `instance` — mövcud obyekti container-a qeydiyyat et üçün kod nümunəsi:*
```php
// Artıq yaranmış obyekti register et
$gateway = new StripeGateway(apiKey: 'sk_test_...');
$this->app->instance(PaymentGateway::class, $gateway);

// Test-də çox istifadə edilir:
public function test_something(): void
{
    $mock = $this->createMock(PaymentGateway::class);
    $mock->method('charge')->willReturn(new PaymentResult(success: true));

    $this->app->instance(PaymentGateway::class, $mock);

    // Artıq app(PaymentGateway::class) mock qaytarır
    $result = $this->app->make(OrderService::class)->process($order);
}
```

### 5. `contextual` — kontekstə görə fərqli binding

*5. `contextual` — kontekstə görə fərqli binding üçün kod nümunəsi:*
```php
// Bax: növbəti bölmə
```

### Binding xülasəsi

*Binding xülasəsi üçün kod nümunəsi:*
```php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Hər dəfə yeni: stateless services üçün
        $this->app->bind(
            abstract: ReportGenerator::class,
            concrete: PdfReportGenerator::class,
        );

        // Tək instance: connection, config, registry üçün
        $this->app->singleton(
            abstract: EventDispatcher::class,
            concrete: SynchronousEventDispatcher::class,
        );

        // Request scope: request-specific data üçün
        $this->app->scoped(
            abstract: CurrentUser::class,
            concrete: AuthenticatedUser::class,
        );

        // Mövcud instance: third-party obyektlər üçün
        $this->app->instance(
            abstract: Carbon::class,
            concrete: Carbon::now(),
        );
    }
}
```

---

## Auto-wiring və Reflection API

Auto-wiring — container-ın type-hint-ə baxaraq asılılıqları **avtomatik** resolve etməsidir. Explicit binding olmadan işləyir.

*Auto-wiring — container-ın type-hint-ə baxaraq asılılıqları **avtomati üçün kod nümunəsi:*
```php
<?php

// Bu siniflər üçün heç bir binding yazmadıq
class HttpClient
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.example.com',
        private readonly int    $timeout = 30,
    ) {}
}

class ApiLogger
{
    public function __construct(
        private readonly \Psr\Log\LoggerInterface $logger
    ) {}
}

class ExternalApiService
{
    // Laravel bu constructor-u görür və avtomatik resolve edir
    public function __construct(
        private readonly HttpClient $client,     // ← auto-wired
        private readonly ApiLogger  $apiLogger,  // ← auto-wired (əgər LoggerInterface bind-dırsa)
    ) {}
}

// Sadəcə bu:
$service = app(ExternalApiService::class);
// Laravel:
// 1. ExternalApiService → HttpClient, ApiLogger lazımdır
// 2. HttpClient → default dəyərləri var, problemsiz yaradılır
// 3. ApiLogger → LoggerInterface lazımdır → binding-ə baxır
// 4. Hamısını yığıb ExternalApiService qaytarır
```

### Reflection API necə işləyir

*Reflection API necə işləyir üçün kod nümunəsi:*
```php
<?php

// Laravel-in daxilindəki build() metodunun sadələşdirilmiş versiyası
private function buildViaReflection(string $concrete, array $parameters): object
{
    try {
        $reflector = new \ReflectionClass($concrete);
    } catch (\ReflectionException $e) {
        throw new \Exception("Target class [{$concrete}] does not exist.");
    }

    // Interface və ya abstract sinif instantiate edilə bilməz
    if (!$reflector->isInstantiable()) {
        throw new \Exception(
            "Target [{$concrete}] is not instantiable. " .
            "Did you forget to bind it in a Service Provider?"
        );
    }

    $constructor = $reflector->getConstructor();

    // Constructor yoxdursa, sadəcə `new $concrete` et
    if (is_null($constructor)) {
        return new $concrete;
    }

    $dependencies = [];

    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();

        // Type-hint class/interface-dirsə, resolve et
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            try {
                $dependencies[] = $this->make($type->getName());
            } catch (\Exception $e) {
                if ($parameter->isOptional()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw $e;
                }
            }
        }
        // Built-in type (string, int, array, etc.)
        elseif (array_key_exists($parameter->getName(), $parameters)) {
            $dependencies[] = $parameters[$parameter->getName()];
        }
        elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }
        else {
            throw new \Exception(
                "Cannot resolve parameter [{$parameter->getName()}] " .
                "in class [{$concrete}]"
            );
        }
    }

    return $reflector->newInstanceArgs($dependencies);
}
```

### Reflection ilə method-un parametrlərini yoxlamaq

*Reflection ilə method-un parametrlərini yoxlamaq üçün kod nümunəsi:*
```php
<?php

// Laravel-in method injection üçün istifadə etdiyi mexanizm
function resolveMethodDependencies(object $instance, string $method, array $primitives = []): array
{
    $reflector = new \ReflectionMethod($instance, $method);

    $resolved = [];

    foreach ($reflector->getParameters() as $parameter) {
        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $resolved[] = app($type->getName());
        } elseif (array_key_exists($parameter->getName(), $primitives)) {
            $resolved[] = $primitives[$parameter->getName()];
        } elseif ($parameter->isDefaultValueAvailable()) {
            $resolved[] = $parameter->getDefaultValue();
        }
    }

    return $resolved;
}
```

---

## Method Injection

Laravel bir neçə yerdə **method injection** dəstəkləyir — constructor yerinə method parametrlərinə asılılıq inject edir.

### Controller Method Injection

*Controller Method Injection üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Requests\PaymentRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    // Constructor injection (shared dependencies)
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    // Method injection: hər action-a özel asılılıqlar
    public function store(
        PaymentRequest $request,                    // Form Request → auto-resolved
        PaymentGatewayInterface $gateway,           // ← method injection
        \App\Services\FraudDetectionService $fraud, // ← method injection
    ): JsonResponse {
        if ($fraud->isSuspicious($request->ip())) {
            return response()->json(['error' => 'Suspicious activity'], 403);
        }

        $result = $gateway->charge(
            amount: $request->validated('amount'),
            token: $request->validated('payment_token'),
        );

        return response()->json($result);
    }
}
```

### Artisan Command Method Injection

*Artisan Command Method Injection üçün kod nümunəsi:*
```php
<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use App\Contracts\StorageInterface;
use Illuminate\Console\Command;

class GenerateMonthlyReportCommand extends Command
{
    protected $signature   = 'reports:monthly {--month=}';
    protected $description = 'Generate monthly sales report';

    // handle() metoduna method injection
    public function handle(
        ReportService    $reportService,  // ← inject edilir
        StorageInterface $storage,        // ← inject edilir
    ): int {
        $month = $this->option('month') ?? now()->format('Y-m');

        $this->info("Generating report for {$month}...");

        $report = $reportService->generateMonthly($month);
        $path   = $storage->put("reports/{$month}.pdf", $report->render());

        $this->info("Report saved to: {$path}");

        return Command::SUCCESS;
    }
}
```

### Route Action Method Injection

*Route Action Method Injection üçün kod nümunəsi:*
```php
// routes/api.php
Route::get('/stats', function (
    \App\Services\StatisticsService $stats,  // ← inject edilir
    \Illuminate\Http\Request $request,       // ← inject edilir
): array {
    return $stats->getSummary(
        from: $request->query('from'),
        to:   $request->query('to'),
    );
});
```

---

## Contextual Binding

Eyni interface-i fərqli siniflərdə fərqli implementation ilə resolve etmək üçün.

*Eyni interface-i fərqli siniflərdə fərqli implementation ilə resolve e üçün kod nümunəsi:*
```php
<?php

// Scenario: İki müxtəlif service eyni LoggerInterface istifadə edir,
// lakin biri file-a, digəri database-ə log yazmalıdır

namespace App\Providers;

use App\Services\OrderService;
use App\Services\AuditService;
use App\Logging\FileLogger;
use App\Logging\DatabaseLogger;
use App\Contracts\LoggerInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // OrderService → FileLogger alsın
        $this->app
            ->when(OrderService::class)
            ->needs(LoggerInterface::class)
            ->give(FileLogger::class);

        // AuditService → DatabaseLogger alsın
        $this->app
            ->when(AuditService::class)
            ->needs(LoggerInterface::class)
            ->give(DatabaseLogger::class);
    }
}
```

### Primitive dəyərlərlə contextual binding

*Primitive dəyərlərlə contextual binding üçün kod nümunəsi:*
```php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ApiClient müxtəlif URL-lərlə istifadə edilir
        $this->app
            ->when(\App\Services\StripeService::class)
            ->needs('$apiUrl')
            ->give(fn() => config('services.stripe.url'));

        $this->app
            ->when(\App\Services\PayPalService::class)
            ->needs('$apiUrl')
            ->give(fn() => config('services.paypal.url'));
    }
}

// Siniflərdə
class StripeService
{
    public function __construct(
        private readonly HttpClient $client,
        private readonly string     $apiUrl,  // ← contextual binding-dən alır
    ) {}
}
```

### Array dependency ilə contextual binding

*Array dependency ilə contextual binding üçün kod nümunəsi:*
```php
$this->app
    ->when(NotificationService::class)
    ->needs('$channels')
    ->give(['email', 'sms', 'push']);

class NotificationService
{
    public function __construct(
        private readonly array $channels, // ← ['email', 'sms', 'push']
    ) {}
}
```

---

## Tagged Bindings

Eyni tag-a aid bütün binding-ləri bir yerdə resolve etmək üçün. **Composite pattern** tətbiqetmək üçün əla üsuldur.

*Eyni tag-a aid bütün binding-ləri bir yerdə resolve etmək üçün. **Comp üçün kod nümunəsi:*
```php
<?php

// Report generator-lar üçün tag-lama
class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PdfReportGenerator::class);
        $this->app->bind(CsvReportGenerator::class);
        $this->app->bind(ExcelReportGenerator::class);

        // Hamısını 'report.generators' tag-ı ilə qeydiyyat et
        $this->app->tag(
            [
                PdfReportGenerator::class,
                CsvReportGenerator::class,
                ExcelReportGenerator::class,
            ],
            'report.generators'
        );
    }
}

// Tag-a görə hamısını al
class ReportManager
{
    /** @var ReportGeneratorInterface[] */
    private array $generators;

    public function __construct(Application $app)
    {
        // 'report.generators' tag-ına aid bütün generator-lar
        $this->generators = iterator_to_array($app->tagged('report.generators'));
    }

    public function generate(string $format, ReportData $data): string
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($format)) {
                return $generator->generate($data);
            }
        }

        throw new \InvalidArgumentException("Unsupported format: {$format}");
    }
}
```

### Validation Rule-ları tag-lamaq

*Validation Rule-ları tag-lamaq üçün kod nümunəsi:*
```php
// Custom validation rule-larını tag-lamaq
class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $rules = [
            PhoneNumberRule::class,
            IbanRule::class,
            VatNumberRule::class,
        ];

        foreach ($rules as $rule) {
            $this->app->bind($rule);
        }

        $this->app->tag($rules, 'validation.custom_rules');
    }

    public function boot(): void
    {
        // Boot-da bütün custom rule-ları qeydiyyat et
        foreach ($this->app->tagged('validation.custom_rules') as $rule) {
            Validator::extend($rule::NAME, $rule);
        }
    }
}
```

---

## Extending Bindings

Mövcud binding-i dəyişdirmədən üzərinə əlavə funksionallıq qatmaq — **Decorator pattern**.

*Mövcud binding-i dəyişdirmədən üzərinə əlavə funksionallıq qatmaq — ** üçün kod nümunəsi:*
```php
<?php

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Əsas binding
        $this->app->singleton(
            CacheInterface::class,
            RedisCache::class
        );

        // Extend: hər resolve-da decorator əlavə et
        $this->app->extend(CacheInterface::class, function (
            CacheInterface $cache,
            Application    $app,
        ): CacheInterface {
            // RedisCache-i LoggingCache ilə sarın (wrap)
            return new LoggingCache(
                inner:  $cache,
                logger: $app->make(LoggerInterface::class),
            );
        });

        // İkinci extend: metriklər əlavə et
        $this->app->extend(CacheInterface::class, function (
            CacheInterface $cache,
            Application    $app,
        ): CacheInterface {
            return new MetricsCache(
                inner:   $cache,
                metrics: $app->make(MetricsCollector::class),
            );
        });
    }
}

// Nəticə: MetricsCache → LoggingCache → RedisCache
// app(CacheInterface::class) çağırıldıqda,
// MetricsCache instance-ı gəlir, lakin altında zəncir var
```

*// MetricsCache instance-ı gəlir, lakin altında zəncir var üçün kod nümunəsi:*
```php
// LoggingCache implementasiyası (Decorator)
class LoggingCache implements CacheInterface
{
    public function __construct(
        private readonly CacheInterface  $inner,
        private readonly LoggerInterface $logger,
    ) {}

    public function get(string $key): mixed
    {
        $value = $this->inner->get($key);

        $this->logger->debug("Cache {$key}: " . ($value !== null ? 'HIT' : 'MISS'));

        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->logger->debug("Cache SET {$key} (ttl={$ttl})");
        $this->inner->set($key, $value, $ttl);
    }
}
```

---

## Service Locator nə vaxt məqbuldur

Bir sıra hallarda Service Locator pattern-i məqbul hesab edilir:

### 1. Framework-in öz daxilindəki helper-lər

*1. Framework-in öz daxilindəki helper-lər üçün kod nümunəsi:*
```php
// Laravel-in helper funksiyaları — məqbuldur
// Çünki bunlar framework infra kodu üçündür

$user     = auth()->user();
$value    = config('app.name');
$path     = storage_path('logs/app.log');
$url      = route('users.show', $user);
$response = response()->json(['status' => 'ok']);
```

### 2. ServiceProvider.php içərisində

*2. ServiceProvider.php içərisində üçün kod nümunəsi:*
```php
// ServiceProvider məhz container-ı idarə etmək üçündür
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Burada app() istifadəsi məqbuldur
        $this->app->bind(PaymentGateway::class, function ($app) {
            return new StripeGateway(
                // Burada app/container istifadəsi normal
                logger: $app->make(LoggerInterface::class),
                config: $app->make('config'),
            );
        });
    }
}
```

### 3. Bootstrap / setup kodu

*3. Bootstrap / setup kodu üçün kod nümunəsi:*
```php
// public/index.php
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class); // məqbul
```

### 4. Legacy kod inteqrasiyası

*4. Legacy kod inteqrasiyası üçün kod nümunəsi:*
```php
// Köhnə kod-la inteqrasiya zamanı — müvəqqəti olaraq məqbul
class LegacyAdapter
{
    public function doSomething(): void
    {
        // Legacy sinif DI dəstəkləmir
        // Refactor etmək mümkün deyil (third-party)
        $service = app(NewService::class); // müvəqqəti kompromis
    }
}
```

---

## Laravel Facades

Laravel Facades — **statik interfeys** kimi görünür, lakin əslində arxada Service Container-dən obyekt alır. Bu onları texniki olaraq **Service Locator** edir, lakin bir çox məhdudiyyəti yoxdur.

*Laravel Facades — **statik interfeys** kimi görünür, lakin əslində arx üçün kod nümunəsi:*
```php
// Facade istifadəsi
use Illuminate\Support\Facades\Cache;

Cache::put('key', 'value', 3600);
Cache::get('key');

// Bu arxa planda belədir:
app('cache')->put('key', 'value', 3600);
app('cache')->get('key');
```

### Facade necə işləyir

*Facade necə işləyir üçün kod nümunəsi:*
```php
// Illuminate\Support\Facades\Cache
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache'; // container alias-ı
    }
}

// Baza Facade sinfi
abstract class Facade
{
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot(); // container-dan alır
        return $instance->$method(...$args);
    }

    protected static function getFacadeRoot(): mixed
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    protected static function resolveFacadeInstance(string|object $name): mixed
    {
        // Container-dən resolve edir
        return static::$app[$name];
    }
}
```

### Facade vs DI müqayisəsi

*Facade vs DI müqayisəsi üçün kod nümunəsi:*
```php
// Facade (Service Locator arxitekturası)
class OrderService
{
    public function createOrder(array $data): Order
    {
        Cache::put('order_draft', $data, 600);  // gizli asılılıq
        Log::info('Order created');              // gizli asılılıq
        Event::dispatch(new OrderCreated($data)); // gizli asılılıq

        return new Order($data);
    }
}

// DI (tövsiyə edilən)
class OrderService
{
    public function __construct(
        private readonly CacheInterface     $cache,    // açıq
        private readonly LoggerInterface    $logger,   // açıq
        private readonly EventDispatcher    $events,   // açıq
    ) {}

    public function createOrder(array $data): Order
    {
        $this->cache->put('order_draft', $data, 600);
        $this->logger->info('Order created');
        $this->events->dispatch(new OrderCreated($data));

        return new Order($data);
    }
}
```

### Facade-in üstünlükləri (niyə Laravel onu istifadə edir)

1. **Ergonomics** — az kod, daha oxunaqlı
2. **IDE support** — `@method` docblock-lar IDE-ni kömək edir
3. **Test support** — `Cache::shouldReceive()` mock əvəzi
4. **Swappable** — arxada implementation dəyişdirilə bilər

*4. **Swappable** — arxada implementation dəyişdirilə bilər üçün kod nümunəsi:*
```php
// Facade-ləri test-də mock etmək
use Illuminate\Support\Facades\Cache;

public function test_order_is_cached(): void
{
    Cache::shouldReceive('put')
        ->once()
        ->with('order_draft', Mockery::any(), 600);

    $service = new OrderService();
    $service->createOrder(['item' => 'book']);
}
```

---

## Real-time Facades

Real-time Facades — hər hansı sinifi avtomatik olaraq Facade-ə çevirmək imkanı verir. `Facades\` namespace prefiksi əlavə etmək kifayətdir.

*Real-time Facades — hər hansı sinifi avtomatik olaraq Facade-ə çevirmə üçün kod nümunəsi:*
```php
<?php

// Normal sinif — Facade DEYİL
namespace App\Services;

class PricingService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly TaxService        $taxService,
    ) {}

    public function calculatePrice(int $productId, string $country): float
    {
        $product  = $this->products->find($productId);
        $taxRate  = $this->taxService->getRateFor($country);

        return $product->basePrice * (1 + $taxRate);
    }
}
```

*return $product->basePrice * (1 + $taxRate); üçün kod nümunəsi:*
```php
// Real-time Facade: Facades\ prefiksi əlavə et
use Facades\App\Services\PricingService;

// Artıq statik kimi çağırmaq olar!
$price = PricingService::calculatePrice(productId: 42, country: 'AZ');

// Arxa planda: app(App\Services\PricingService::class)->calculatePrice(...)
```

*// Arxa planda: app(App\Services\PricingService::class)->calculatePric üçün kod nümunəsi:*
```php
// Real-time Facade-i test-də mock etmək
use Facades\App\Services\PricingService;

public function test_price_display(): void
{
    PricingService::shouldReceive('calculatePrice')
        ->with(42, 'AZ')
        ->andReturn(99.99);

    $response = $this->get('/products/42?country=AZ');
    $response->assertSee('99.99');
}
```

**Real-time Facade nə vaxt istifadə edilir:**
- Blade view-larında
- Helper funksiyalarda
- Prototipləmə zamanı

**Nə vaxt istifadə edilməməlidir:**
- Core business logic-də
- Test edilməsi vacib olan siniflərdə

---

## Testing: DI vs Service Locator

### DI ilə test — sadə və izolə edilmiş

*DI ilə test — sadə və izolə edilmiş üçün kod nümunəsi:*
```php
<?php

namespace Tests\Unit\Services;

use App\Services\OrderService;
use App\Contracts\PaymentGatewayInterface;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\OrderRepositoryInterface;
use App\Models\Order;
use App\DTOs\PaymentResult;
use PHPUnit\Framework\TestCase; // Laravel-siz pure unit test!

class OrderServiceTest extends TestCase
{
    private PaymentGatewayInterface&\PHPUnit\Framework\MockObject\MockObject $gateway;
    private NotificationServiceInterface&\PHPUnit\Framework\MockObject\MockObject $notifier;
    private OrderRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway  = $this->createMock(PaymentGatewayInterface::class);
        $this->notifier = $this->createMock(NotificationServiceInterface::class);
        $this->repo     = $this->createMock(OrderRepositoryInterface::class);
    }

    private function makeService(): OrderService
    {
        return new OrderService(
            orderRepository:     $this->repo,
            paymentGateway:      $this->gateway,
            notificationService: $this->notifier,
        );
    }

    public function test_successful_payment_marks_order_as_paid(): void
    {
        $order = new Order(['total' => 100.00, 'currency' => 'USD']);

        $this->gateway
            ->expects($this->once())
            ->method('charge')
            ->with(amount: 100.00, currency: 'USD')
            ->willReturn(new PaymentResult(successful: true));

        $this->repo
            ->expects($this->once())
            ->method('markAsPaid')
            ->with($order);

        $this->notifier
            ->expects($this->once())
            ->method('sendReceipt')
            ->with($order);

        $this->makeService()->processOrder($order);
    }

    public function test_failed_payment_does_not_mark_order(): void
    {
        $order = new Order(['total' => 50.00, 'currency' => 'USD']);

        $this->gateway
            ->method('charge')
            ->willReturn(new PaymentResult(successful: false));

        $this->repo
            ->expects($this->never())
            ->method('markAsPaid'); // ← heç vaxt çağırılmamalıdır

        $this->notifier
            ->expects($this->never())
            ->method('sendReceipt');

        $this->makeService()->processOrder($order);
    }
}
```

### Service Locator ilə test — çətin və side-effect-li

*Service Locator ilə test — çətin və side-effect-li üçün kod nümunəsi:*
```php
<?php

namespace Tests\Feature\Services;

use Tests\TestCase; // Laravel-in TestCase-i lazımdır!
use App\Services\BadOrderService; // Service Locator istifadə edir

class BadOrderServiceTest extends TestCase
{
    public function test_successful_payment(): void
    {
        // Container-ı manipulasiya etməliyik
        $mockGateway = $this->createMock(PaymentGateway::class);
        $mockGateway->method('charge')->willReturn(new PaymentResult(true));

        $this->app->instance(PaymentGateway::class, $mockGateway);

        // Notifier üçün də
        $mockNotifier = $this->createMock(NotificationService::class);
        $this->app->instance(NotificationService::class, $mockNotifier);

        // Repo üçün də
        $mockRepo = $this->createMock(OrderRepository::class);
        $this->app->instance(OrderRepository::class, $mockRepo);

        // İndi test edə bilərik, lakin:
        // 1. Laravel app lazımdır (integration test)
        // 2. Container-ın state-i dəyişir (test pollution)
        // 3. Paralel testlər problem ola bilər
        // 4. Test çox yavaşdır (framework boot lazımdır)

        $service = new BadOrderService(); // constructor-da heç nə yoxdur
        $service->processOrder($order);

        // Assert-lər...
    }
}
```

### Mockery ilə Service Locator test

*Mockery ilə Service Locator test üçün kod nümunəsi:*
```php
// Facade mock etmək (nisbətən yaxşı, amma hələ global state-dir)
public function test_with_facade_mock(): void
{
    \Illuminate\Support\Facades\Cache::shouldReceive('put')
        ->once()
        ->with('order:123', Mockery::any(), 3600)
        ->andReturn(true);

    // Test...

    // Mockery::close() çağırılmalıdır (PHPUnit tearDown)
}
```

---

## Bad → Good code transformasiyaları

### Transformasiya 1: Static calls → DI

*Transformasiya 1: Static calls → DI üçün kod nümunəsi:*
```php
// BAD: static calls hər yerdə
class InvoiceService
{
    public function generate(int $orderId): string
    {
        $order = DB::table('orders')->find($orderId); // static, test edilmir
        $user  = Auth::user();                        // static, request-ə bağlı
        $pdf   = PDF::loadView('invoice', compact('order', 'user')); // static

        Log::info("Invoice generated for order {$orderId}"); // static

        return $pdf->output();
    }
}

// GOOD: DI ilə
class InvoiceService
{
    public function __construct(
        private readonly OrderRepository   $orderRepo,
        private readonly PdfGenerator      $pdfGenerator,
        private readonly LoggerInterface   $logger,
    ) {}

    public function generate(int $orderId, User $currentUser): string
    {
        $order = $this->orderRepo->findOrFail($orderId);

        $this->logger->info("Invoice generated for order {$orderId}");

        return $this->pdfGenerator->fromView(
            view: 'invoice',
            data: ['order' => $order, 'user' => $currentUser],
        );
    }
}
```

### Transformasiya 2: new keyword → DI

*Transformasiya 2: new keyword → DI üçün kod nümunəsi:*
```php
// BAD: constructor içində new keyword
class CheckoutService
{
    private StripeGateway    $gateway;
    private EmailService     $emailService;
    private OrderRepository  $orderRepo;

    public function __construct()
    {
        // Hər şey burada yaradılır — test etmək mümkünsüzdür
        $this->gateway      = new StripeGateway(env('STRIPE_KEY'));
        $this->emailService = new EmailService(env('MAIL_HOST'), env('MAIL_PORT'));
        $this->orderRepo    = new EloquentOrderRepository();
    }
}

// GOOD: asılılıqlar inject edilir
class CheckoutService
{
    public function __construct(
        private readonly PaymentGatewayInterface   $gateway,
        private readonly EmailServiceInterface     $emailService,
        private readonly OrderRepositoryInterface  $orderRepo,
    ) {}
}

// Binding — ServiceProvider-də
class CheckoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CheckoutService::class, function ($app) {
            return new CheckoutService(
                gateway:      $app->make(PaymentGatewayInterface::class),
                emailService: $app->make(EmailServiceInterface::class),
                orderRepo:    $app->make(OrderRepositoryInterface::class),
            );
        });
    }
}
```

### Transformasiya 3: Global state → DI

*Transformasiya 3: Global state → DI üçün kod nümunəsi:*
```php
// BAD: global state, config() hər yerdə
class PaymentProcessor
{
    public function process(float $amount): bool
    {
        $maxRetries = config('payment.max_retries'); // global
        $timeout    = config('payment.timeout');     // global
        $currency   = config('app.currency');        // global

        for ($i = 0; $i < $maxRetries; $i++) {
            // ...
        }

        return true;
    }
}

// GOOD: config Value Object + DI
class PaymentConfig
{
    public function __construct(
        public readonly int    $maxRetries,
        public readonly int    $timeout,
        public readonly string $currency,
    ) {}
}

class PaymentProcessor
{
    public function __construct(
        private readonly PaymentConfig $config,
    ) {}

    public function process(float $amount): bool
    {
        for ($i = 0; $i < $this->config->maxRetries; $i++) {
            // ...
        }

        return true;
    }
}

// ServiceProvider-də
$this->app->singleton(PaymentConfig::class, fn() => new PaymentConfig(
    maxRetries: config('payment.max_retries'),
    timeout:    config('payment.timeout'),
    currency:   config('app.currency'),
));
```

### Transformasiya 4: Fat Controller → DI ilə ayrılmış service-lər

*Transformasiya 4: Fat Controller → DI ilə ayrılmış service-lər üçün kod nümunəsi:*
```php
// BAD: Fat controller, hər şey burada
class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Validation
        $request->validate(['product_id' => 'required|exists:products,id']);

        // Business logic
        $product = Product::find($request->product_id);
        $order   = Order::create([
            'user_id'    => auth()->id(),
            'product_id' => $product->id,
            'total'      => $product->price,
        ]);

        // Payment
        $stripe = new \Stripe\StripeClient(config('stripe.key'));
        $intent = $stripe->paymentIntents->create([
            'amount'   => $order->total * 100,
            'currency' => 'usd',
        ]);

        // Email
        Mail::to(auth()->user())->send(new OrderConfirmation($order));

        return response()->json($order);
    }
}

// GOOD: Controller sadədir, iş service-lərə həvalə edilir
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(
        StoreOrderRequest $request, // validation request-də
    ): OrderResource {
        $order = $this->orderService->createOrder(
            userId:    $request->user()->id,
            productId: $request->validated('product_id'),
        );

        return new OrderResource($order);
    }
}

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface  $orders,
        private readonly PaymentGatewayInterface   $payment,
        private readonly NotificationService       $notifier,
    ) {}

    public function createOrder(int $userId, int $productId): Order
    {
        $product = $this->orders->findProduct($productId);

        $order = $this->orders->create([
            'user_id'    => $userId,
            'product_id' => $product->id,
            'total'      => $product->price,
        ]);

        $this->payment->initiatePayment($order);
        $this->notifier->sendOrderConfirmation($order);

        return $order;
    }
}
```

---

## Müsahibə sualları və cavabları

### S1: Dependency Injection nədir və niyə istifadə edirik?

**Cavab:**
Dependency Injection — bir sinifin ehtiyac duyduğu asılılıqların həmin sinifin constructor-u və ya method-u vasitəsilə xaricdən verilməsidir. Sinif öz asılılıqlarını `new` ilə özü yaratmaq əvəzinə, hazır şəkildə alır.

İstifadə səbəbləri:
- **Loose coupling** — siniflər bir-birindən az asılıdır
- **Testability** — asılılıqları mock ilə əvəz etmək asandır
- **Explicitness** — sinifin nəyə ehtiyacı olduğu constructor-dan açıq görünür
- **SOLID** prinsiplərinə (xüsusilə DIP) uyğunluq

---

### S2: Service Locator niyə anti-pattern hesab edilir?

**Cavab:**
Service Locator üç əsas problemi var:

1. **Hidden dependencies** — sinifin asılılıqları constructor-da görünmür, metodların içindədir
2. **Hard to test** — test-də container-ı manipulasiya etmək lazım gəlir, pure unit test mümkün deyil
3. **Coupling to container** — sinif framework-ün container-ına bağlanır, portability azalır

*3. **Coupling to container** — sinif framework-ün container-ına bağlan üçün kod nümunəsi:*
```php
// Problem: nəyə ehtiyac olduğunu görmürük
class OrderService
{
    public function process(): void
    {
        $a = app(A::class); // gizli
        $b = app(B::class); // gizli
        $c = app(C::class); // gizli
    }
}
```

---

### S3: IoC Container ilə Service Locator arasındakı fərq nədir?

**Cavab:**
Bu çox yaxşı sualdır. **IoC Container** bir texnologiyadır, **Service Locator** isə bir pattern-dir.

- **IoC Container** — DI-ni avtomatlaşdıran bir alətdir. Framework (Laravel) onu daxilən istifadə edir.
- **Service Locator** — container-ı birbaşa business logic-dən çağırmaqdır.

Fərq **kim container-ı çağırır**dadır:
- DI: container özü sinifləri qurur, siniflər container-ı görmür
- Service Locator: siniflər özü container-ı çağırır (`app()`)

---

### S4: singleton vs scoped binding fərqi nədir?

**Cavab:**
- `singleton` — tətbiqin tam ömrü boyunca **bir** instance. Artisan command-da da, HTTP request-də də eyni.
- `scoped` — **bir request/job** boyunca tək instance. Növbəti request-də sıfırlanır.

Laravel Octane kimi long-running server istifadə edərkən `scoped` çox vacibdir — request-ə xüsusi məlumatlar (user context, request ID) növbəti request-ə keçməsin.

---

### S5: Auto-wiring nədir, necə işləyir?

**Cavab:**
Auto-wiring — container-ın sinifin constructor parametrlərini Reflection API vasitəsilə oxuyaraq asılılıqları **avtomatik** resolve etməsidir. Explicit binding olmadan işləyir.

*Auto-wiring — container-ın sinifin constructor parametrlərini Reflecti üçün kod nümunəsi:*
```php
class StripeGateway
{
    public function __construct(
        private readonly HttpClient $client,  // ← auto-wired
    ) {}
}

// Heç bir binding yazmadan:
$gateway = app(StripeGateway::class); // HttpClient avtomatik inject edilir
```

PHP-nin `ReflectionClass`, `ReflectionMethod`, `ReflectionParameter` class-ları istifadə edilir.

---

### S6: Contextual binding nə vaxt lazım olur?

**Cavab:**
Eyni interface üçün kontekstə görə fərqli implementation lazım olduqda:

*Eyni interface üçün kontekstə görə fərqli implementation lazım olduqda üçün kod nümunəsi:*
```php
// FileUploadService → LocalStorage alsın (development)
// VideoProcessingService → S3Storage alsın (production)

$this->app->when(FileUploadService::class)
    ->needs(StorageInterface::class)
    ->give(LocalStorage::class);

$this->app->when(VideoProcessingService::class)
    ->needs(StorageInterface::class)
    ->give(S3Storage::class);
```

---

### S7: Laravel Facades Service Locator midir?

**Cavab:**
Texniki olaraq **bəli** — Facades arxada `static::$app['facade-accessor']` çağırır, bu Service Locator pattern-idir.

Lakin Facades DI-nin zəifliklərini azaldır:
- **Test support**: `Cache::shouldReceive()` mock imkanı var
- **IDE support**: PHPDoc ilə autocomplete işləyir
- **Swappable**: arxada implementation dəyişdirilə bilər

Façade-lar Laravel-in "ergonomic Service Locator" həllidir. Core business logic üçün DI daha yaxşıdır, lakin helper kodu üçün Facade məqbuldur.

---

### S8: bind vs singleton nə vaxt istifadə edilir?

**Cavab:**
- `bind` → **stateless services** üçün. Hər request üçün yeni instance lazımdırsa. Məsələn: Report generator, bir dəfəlik validator.
- `singleton` → **stateful/shared services** üçün. Database connection, event dispatcher, config — bir instance kifayətdir.

Ümumi qayda: şübhə edəndə `bind` istifadə et. `singleton`-ın yanlış istifadəsi memory leak-ə, test pollution-a səbəb olur.

---

### S9: `extend` binding nə üçündür?

**Cavab:**
`extend` — mövcud binding-i dəyişdirmədən üzərinə Decorator qatmaq üçündür.

*`extend` — mövcud binding-i dəyişdirmədən üzərinə Decorator qatmaq üçü üçün kod nümunəsi:*
```php
// RedisCache-i yazan ServiceProvider-ə toxunmadan
// LoggingCache decorator-ını əlavə edirik:
$this->app->extend(CacheInterface::class, function ($cache, $app) {
    return new LoggingCache($cache, $app->make(Logger::class));
});
```

Open/Closed Principle-ə uyğundur: extension üçün açıq, modification üçün bağlı.

---

### S10: Method injection ilə constructor injection arasındakı fərq nədir?

**Cavab:**
- **Constructor injection**: sinifin ömrü boyunca lazım olan, həmişə mövcud olması lazım olan asılılıqlar üçün.
- **Method injection**: yalnız həmin method çağırıldığında lazım olan, action-specific asılılıqlar üçün.

Laravel-də controller action-larında method injection çox istifadə edilir — hər action-a özel Form Request, Service inject edilir, amma bunlar digər action-larda lazım deyil.

---

### S11: Circular dependency problemi necə həll edilir?

**Cavab:**
Circular dependency — A, B-yə; B isə A-ya ehtiyac duyduqda baş verir. Constructor injection ilə bu **compile time** xətasına səbəb olur (sonsuz loop).

Həll yolları:
1. **Interface segregation** — asılılığı daha kiçik interface-lərə böl
2. **Event-driven** — birbaşa asılılıq əvəzinə event/listener istifadə et
3. **Lazy injection** — `app()->make()` ilə lazy resolve et (son çarə)
4. **Refactor** — circular dependency adətən yanlış arxitekturanın əlamətidir

*4. **Refactor** — circular dependency adətən yanlış arxitekturanın əla üçün kod nümunəsi:*
```php
// Problematik
class A { public function __construct(B $b) {} }
class B { public function __construct(A $a) {} } // CircularDependencyException!

// Həll: Event-driven
class A {
    public function __construct(private EventDispatcher $events) {}
    public function doSomething(): void {
        $this->events->dispatch(new SomethingHappened());
    }
}
class B {
    public function handle(SomethingHappened $event): void { ... }
}
```

---

### S12: DI container olmadan DI mümkündürmü?

**Cavab:**
Bəli! DI pattern-i container-dan asılı deyil. Container sadəcə DI-ni avtomatlaşdırır.

Container olmadan (Pure DI / Composition Root):

*Container olmadan (Pure DI / Composition Root) üçün kod nümunəsi:*
```php
// bootstrap/app.php və ya entry point-də
$logger     = new FileLogger('/var/log/app.log');
$httpClient = new GuzzleHttpClient(timeout: 30);
$gateway    = new StripeGateway(apiKey: env('STRIPE_KEY'), client: $httpClient);
$orderRepo  = new EloquentOrderRepository();
$mailer     = new SmtpMailer(host: env('MAIL_HOST'));

$orderService = new OrderService(
    gateway:  $gateway,
    repo:     $orderRepo,
    mailer:   $mailer,
    logger:   $logger,
);
```

Bu "Pure DI" və ya "Composition Root" pattern-i adlanır. Kiçik layihələr üçün container-dan daha sadədir.

---

*Son yenilənmə: 2026-04-08*
