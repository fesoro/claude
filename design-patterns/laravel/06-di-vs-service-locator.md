# Dependency Injection vs Service Locator (Middle ⭐⭐)

## İcmal

Dependency Injection (DI) — bir obyektin ehtiyac duyduğu asılılıqların (dependencies) həmin obyektin özü tərəfindən yaradılmaq əvəzinə, xaricdən ötürülməsi prinsipidir. Service Locator isə əksinə — sinif asılılıqlarını özü "axtarıb tapır". DI daha testable, daha explicit, daha maintainable kod yaradır; Service Locator isə gizli asılılıqlar yaradır.

## Niyə Vacibdir

Laravel-in güclü IoC Container-i var. Bu container-i doğru istifadə etmək — constructor injection, contextual binding — test yazmağı asanlaşdırır, coupling-i azaldır. `app()` hər yerdə çağırmaq isə hidden dependencies yaradır, test etmək çətinləşir.

## Əsas Anlayışlar

- **Constructor Injection**: asılılıqlar constructor-a verilir; ən tövsiyə olunan yanaşma
- **Method Injection**: controller action-lara, Artisan command `handle()`-a inject etmək
- **IoC Container**: Laravel-in DI container-i; sinifləri və asılılıqlarını avtomatik quran sistem
- **Auto-wiring**: container Reflection API ilə type-hint-ə görə asılılıqları avtomatik resolve edir
- **Binding**: interface → implementation mapping; `bind()`, `singleton()`, `scoped()`
- **Contextual Binding**: eyni interface-i fərqli siniflərdə fərqli implementation-la resolve etmək
- **Service Locator**: `app(SomeClass::class)` ilə asılılıq əldə etmək — gizli dependency yaradır

## Praktik Baxış

- **Real istifadə**: DI hər service, repository, handler class-ında; contextual binding payment gateway-lər, storage adapter-ları üçün
- **Trade-off-lar**: DI ilə constructor uzun ola bilər (çox asılılıq = God Class əlaməti); auto-wiring reflection overhead yaradır (caching ilə həll olunur)
- **İstifadə etməmək**: Service Locator-u business logic içindən çağırmayın; yalnız ServiceProvider, framework infrastructure kodunda məqbuldur

- **Common mistakes**:
  1. `app()` və ya `resolve()` service siniflərinin içində istifadə etmək — hidden dependency
  2. `new SomeDependency()` constructor-da etmək — test etmək mümkün deyil
  3. `static` method-lar — dependency inject etmək olmur
  4. Facade-ları core business logic-də — hidden coupling

### Anti-Pattern Nə Zaman Olur?

**Service Locator hər yerdə:**
```php
// BAD — OrderService-in nəyə ehtiyacı olduğunu bilmirsiniz
class OrderService
{
    public function process(Order $order): void
    {
        $gateway  = app(PaymentGateway::class);   // gizli asılılıq
        $mailer   = app(Mailer::class);            // gizli asılılıq
        $repo     = app(OrderRepository::class);   // gizli asılılıq
        // Constructor-a baxsanız, heç nə görməzsiniz
    }
}

// GOOD — asılılıqlar açıq şəkildə görünür
class OrderService
{
    public function __construct(
        private readonly PaymentGateway  $gateway,   // açıq
        private readonly Mailer          $mailer,    // açıq
        private readonly OrderRepository $repo,      // açıq
    ) {}
}
```

**Static calls disguised as service locator:**
```php
// BAD — Facade hər yerdə; test etmək çətin, hidden dependency
class InvoiceService
{
    public function generate(int $orderId): string
    {
        $order = DB::table('orders')->find($orderId); // static
        $user  = Auth::user();                        // static, request-ə bağlı
        $pdf   = PDF::loadView('invoice', compact('order', 'user')); // static
        Log::info("Invoice generated for order {$orderId}"); // static
        return $pdf->output();
    }
}

// GOOD — DI ilə; test etmək asandır
class InvoiceService
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly PdfGenerator   $pdfGenerator,
        private readonly LoggerInterface $logger,
    ) {}

    public function generate(int $orderId, User $currentUser): string
    {
        $order = $this->orderRepo->findOrFail($orderId);
        $this->logger->info("Invoice generated for order {$orderId}");
        return $this->pdfGenerator->fromView('invoice', compact('order') + ['user' => $currentUser]);
    }
}
```

## Nümunələr

### Ümumi Nümunə

DI ilə class-ın nəyə ehtiyacı olduğu constructor-a baxmaqla dərhal görsənir. Test zamanı mock-lar birbaşa constructor-a verilir — container-i manipulasiya etmək lazım deyil. Service Locator ilə bunu bilmək üçün bütün method-ları oxumaq lazımdır.

### PHP/Laravel Nümunəsi

**Constructor Injection:**

```php
<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\OrderRepositoryInterface;

class OrderService
{
    // Constructor-a baxıb dərhal nəyə ehtiyacı olduğunu görürsünüz
    public function __construct(
        private readonly OrderRepositoryInterface     $orderRepository,
        private readonly PaymentGatewayInterface      $paymentGateway,
        private readonly NotificationServiceInterface $notificationService,
    ) {}

    public function processOrder(Order $order): PaymentResult
    {
        $result = $this->paymentGateway->charge(
            amount:   $order->total,
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

**ServiceProvider-da binding:**

```php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Interface → implementation; konkret class dəyişsə yalnız burada
        $this->app->bind(
            OrderRepositoryInterface::class,
            EloquentOrderRepository::class
        );

        $this->app->bind(
            PaymentGatewayInterface::class,
            StripePaymentGateway::class
        );

        // Singleton — bir instance yetər (connection, registry)
        $this->app->singleton(
            DatabaseConnection::class,
            fn($app) => new DatabaseConnection(config('database'))
        );

        // Scoped — request boyunca tək instance (Octane ilə vacibdir)
        $this->app->scoped(
            RequestContext::class,
            fn($app) => new RequestContext(Str::uuid()->toString(), now())
        );
    }
}
```

**Contextual Binding:**

```php
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

        // Primitive dəyər — config-dən
        $this->app
            ->when(StripeService::class)
            ->needs('$apiUrl')
            ->give(fn() => config('services.stripe.url'));
    }
}
```

**DI ilə test — sadə və izolə edilmiş:**

```php
<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase; // Laravel-siz pure unit test!

class OrderServiceTest extends TestCase
{
    public function test_successful_payment_marks_order_as_paid(): void
    {
        // Mock-ları birbaşa constructor-a veririk — container lazım deyil
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects($this->once())
                ->method('charge')
                ->with(amount: 100.00, currency: 'USD')
                ->willReturn(new PaymentResult(successful: true));

        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->expects($this->once())->method('markAsPaid');

        $notifier = $this->createMock(NotificationServiceInterface::class);
        $notifier->expects($this->once())->method('sendReceipt');

        $service = new OrderService($repo, $gateway, $notifier);
        $service->processOrder(new Order(['total' => 100.00, 'currency' => 'USD']));
    }

    public function test_failed_payment_does_not_mark_order(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('charge')
                ->willReturn(new PaymentResult(successful: false));

        $repo = $this->createMock(OrderRepositoryInterface::class);
        // Ödəniş uğursuz — markAsPaid HEÇ VAXT çağırılmamalıdır
        $repo->expects($this->never())->method('markAsPaid');

        $notifier = $this->createMock(NotificationServiceInterface::class);
        $notifier->expects($this->never())->method('sendReceipt');

        $service = new OrderService($repo, $gateway, $notifier);
        $service->processOrder(new Order(['total' => 50.00, 'currency' => 'USD']));
    }
}
```

**Tagged Bindings — eyni tipdən çox implementasiya:**

```php
class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PdfReportGenerator::class);
        $this->app->bind(CsvReportGenerator::class);
        $this->app->bind(ExcelReportGenerator::class);

        // Hamısını tag-la
        $this->app->tag(
            [PdfReportGenerator::class, CsvReportGenerator::class, ExcelReportGenerator::class],
            'report.generators'
        );
    }
}

class ReportManager
{
    private array $generators;

    public function __construct(Application $app)
    {
        // Bütün tagged-lər bir dəfəyə resolve olunur
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

**Laravel Facade haqqında dürüst qiymətləndirmə:**

```php
// Facade — texniki olaraq Service Locator-dur, lakin DI-nin bəzi zəifliklərini kompensasiya edir

// Business logic-də Facade istifadəsi — BAD
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

// DI ilə — GOOD
class OrderService
{
    public function __construct(
        private readonly CacheInterface  $cache,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcher $events,
    ) {}
    // Test-də hər birini mock edə bilərik
}

// Facade məqbul olan yerlər:
// 1. ServiceProvider daxilindəki kod
// 2. Blade views-da helper funksiyalar (route(), asset())
// 3. routes/web.php-də route helpers
// 4. Artisan command-ların daxilindəki quick script-lər
```

## Praktik Tapşırıqlar

1. Mövcud bir service class-da `app()` istifadəsini tapın; constructor injection-a keçirin; unit test yazın
2. `PaymentGatewayInterface` üçün contextual binding yazın: local/test env-da `FakeGateway`, production-da `StripeGateway`
3. Tagged binding ilə `NotificationChannel` (email, sms, push) registry qurun; `NotificationManager` bütün channel-ları inject etsin

## Əlaqəli Mövzular

- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — DI Dependency Inversion Principle-in tətbiqidir
- [02-service-layer.md](02-service-layer.md) — Service-lər DI ilə qurulur
- [01-repository-pattern.md](01-repository-pattern.md) — Repository interface-ləri DI ilə inject edilir
- [../creational/01-singleton.md](../creational/01-singleton.md) — Container-da singleton binding
- [../structural/01-facade.md](../structural/01-facade.md) — Laravel Facade: Service Locator-un sintaktik şəkəri
- [../structural/04-proxy.md](../structural/04-proxy.md) — Lazy-loading proxy ilə dependency defer etmək
- [../general/02-code-smells-refactoring.md](../general/02-code-smells-refactoring.md) — `app()` hər yerdə: hidden dependency code smell
