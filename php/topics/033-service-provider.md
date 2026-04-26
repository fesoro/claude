# Laravel Service Provider (Dərin İzah) (Middle)

## Mündəricat
1. [Service Provider nədir?](#service-provider-nədir)
2. [Service Container (IoC Container)](#service-container-ioc-container)
3. [Dependency Injection](#dependency-injection)
4. [Binding Types](#binding-types)
5. [Contextual Binding](#contextual-binding)
6. [Tagging](#tagging)
7. [Extending Bindings](#extending-bindings)
8. [register() vs boot()](#register-vs-boot)
9. [Deferred Providers](#deferred-providers)
10. [Auto-Discovery](#auto-discovery)
11. [Custom Service Provider Yaratma](#custom-service-provider-yaratma)
12. [Real-World Nümunələr](#real-world-nümunələr)
13. [Facades Necə İşləyir](#facades-necə-işləyir)
14. [Real-time Facades](#real-time-facades)
15. [Interface-dən Implementation-a Binding](#interfacedən-implementationa-binding)
16. [Config-based Implementation Switching](#config-based-implementation-switching)
17. [Testing ilə Service Provider](#testing-ilə-service-provider)
18. [Laravel-in Öz Service Provider-ləri](#laravelin-öz-service-providerləri)
19. [Best Practices](#best-practices)
20. [İntervyu Sualları](#intervyu-sualları)

---

## Service Provider nədir?

Service Provider Laravel-in ən fundamental konseptlərindən biridir. Application-ın bootstrap prosesinin mərkəzi nöqtəsidir. Bütün core Laravel service-ləri (routing, database, queue, cache, mail və s.) Service Provider-lər vasitəsilə qeydiyyatdan keçirilir.

Service Provider iki əsas iş görür:
1. **register()** — Service Container-ə binding-lər qeydə alır
2. **boot()** — Application tam bootstrap olduqdan sonra çalışır (bütün service-lər artıq qeydiyyatdan keçib)

*2. **boot()** — Application tam bootstrap olduqdan sonra çalışır (bütü üçün kod nümunəsi:*
```php
// Bu kod register() və boot() metodları olan əsas service provider strukturunu göstərir
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Service Container-ə binding-lər qeydiyyatdan keçir.
     * Burada yalnız binding yapmaq lazımdır.
     * Digər service-lərə istinad etməyin, çünki onlar hələ register olmaya bilər.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\PaymentGateway::class,
            \App\Services\StripePaymentGateway::class
        );
    }

    /**
     * Bütün service-lər register olduqdan SONRA çağırılır.
     * Burada event listener-lər, route-lar, view composer-lər
     * və digər bootstrap əməliyyatları yerinə yetirilir.
     */
    public function boot(): void
    {
        // View composer
        view()->composer('layouts.app', function ($view) {
            $view->with('appName', config('app.name'));
        });

        // Custom validation rule
        \Illuminate\Support\Facades\Validator::extend('phone', function ($attribute, $value) {
            return preg_match('/^\+994\d{9}$/', $value);
        });
    }
}
```

### Application Bootstrap Prosesi

```
1. HTTP Request gəlir
2. public/index.php çalışır
3. Application instance yaradılır
4. Core Service Provider-lər register olunur (kernel-ə aid)
5. config/app.php-dəki providers arrayindəki bütün Service Provider-lərin register() metodu çağırılır
6. Bütün register() bitdikdən sonra hər birinin boot() metodu çağırılır
7. HTTP Kernel request-i handle edir
8. Middleware pipeline-dan keçir
9. Router request-i uyğun controller-ə yönləndirir
10. Response qaytarılır
```

---

## Service Container (IoC Container)

Service Container (Inversion of Control Container) — class dependency-lərini idarə edən və inject edən güclü bir alətdir. O, application-dəki class-ların yaradılmasını və dependency-lərin təmin edilməsini avtomatlaşdırır.

*Service Container (Inversion of Control Container) — class dependency- üçün kod nümunəsi:*
```php
// Bu kod Service Container-ə müxtəlif yollarla daxil olmağı göstərir
<?php

// Service Container-ə müxtəlif yollarla daxil olmaq:

// 1. app() helper
$service = app()->make(OrderService::class);
$service = app(OrderService::class); // Qısa versiya

// 2. $this->app (Service Provider daxilində)
class MyProvider extends ServiceProvider
{
    public function register(): void
    {
        $instance = $this->app->make(SomeClass::class);
    }
}

// 3. resolve() helper
$service = resolve(OrderService::class);

// 4. Constructor injection (ən çox tövsiyə olunan)
class OrderController
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}
}

// 5. Method injection (controller action-larda)
class OrderController
{
    public function store(StoreOrderRequest $request, OrderService $service): JsonResponse
    {
        // $service avtomatik inject olunur
    }
}
```

### Container-in Daxili Mexanizmi

*Container-in Daxili Mexanizmi üçün kod nümunəsi:*
```php
// Bu kod Service Container-in daxili data strukturlarını göstərir
<?php

// Container aşağıdakı data struktuları saxlayır:

class Container
{
    // Abstract -> Concrete mapping
    protected array $bindings = [
        // 'App\Contracts\PaymentGateway' => [
        //     'concrete' => Closure,
        //     'shared' => false,
        // ],
    ];

    // Singleton instances (artıq yaradılmış)
    protected array $instances = [
        // 'App\Services\Config' => Config instance,
    ];

    // Aliases
    protected array $aliases = [
        // 'payment' => 'App\Contracts\PaymentGateway',
    ];

    // Contextual bindings
    protected array $contextual = [
        // 'App\Http\Controllers\PhotoController' => [
        //     'App\Contracts\Filesystem' => Closure,
        // ],
    ];

    // Tags
    protected array $tags = [
        // 'reports' => [
        //     'App\Reports\MonthlyReport',
        //     'App\Reports\YearlyReport',
        // ],
    ];

    // Extending callbacks
    protected array $extenders = [
        // 'App\Services\Logger' => [Closure, Closure],
    ];

    // Resolved types tracking
    protected array $resolved = [
        // 'App\Services\OrderService' => true,
    ];

    // Rebinding callbacks
    protected array $reboundCallbacks = [];

    // Build stack (circular dependency detection)
    protected array $buildStack = [];
}
```

---

## Dependency Injection

Dependency Injection (DI) — class-ın ehtiyac duyduğu dependency-lərin xaricdən təmin edilməsi prinsipidir. DI üç formada olur:

### 1. Constructor Injection (ən yaxşı üsul)

*1. Constructor Injection (ən yaxşı üsul) üçün kod nümunəsi:*
```php
// Bu kod constructor injection ilə dependency-ləri inject etməyi göstərir
<?php

interface LoggerInterface
{
    public function log(string $message, string $level = 'info'): void;
}

class FileLogger implements LoggerInterface
{
    public function __construct(
        private readonly string $logPath = '/var/log/app.log'
    ) {}

    public function log(string $message, string $level = 'info'): void
    {
        $entry = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        file_put_contents($this->logPath, $entry, FILE_APPEND);
    }
}

class DatabaseLogger implements LoggerInterface
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    public function log(string $message, string $level = 'info'): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO logs (message, level) VALUES (?, ?)");
        $stmt->execute([$message, $level]);
    }
}

// Constructor Injection — dependency constructor vasitəsilə təmin olunur
class OrderService
{
    public function __construct(
        private readonly OrderRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly NotificationService $notifier
    ) {}

    public function createOrder(array $data): Order
    {
        $this->logger->log("Yeni sifariş yaradılır", 'info');

        $order = $this->repository->create($data);
        $this->paymentGateway->charge($order->total);
        $this->notifier->sendOrderConfirmation($order);

        $this->logger->log("Sifariş yaradıldı: #{$order->id}", 'info');

        return $order;
    }
}

// Service Provider-dən binding:
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoggerInterface::class, FileLogger::class);

        // Və ya Closure ilə:
        $this->app->bind(LoggerInterface::class, function ($app) {
            return new FileLogger(storage_path('logs/app.log'));
        });
    }
}
```

### 2. Method Injection

*2. Method Injection üçün kod nümunəsi:*
```php
// Bu kod controller metoduna birbaşa dependency inject etməyi göstərir
<?php

class ReportController
{
    // Laravel controller action-larında method injection avtomatikdir
    public function generate(
        Request $request,
        ReportService $reportService,    // Container-dən resolve olunur
        PDFExporter $exporter            // Container-dən resolve olunur
    ): Response {
        $report = $reportService->generate($request->input('type'));
        $pdf = $exporter->export($report);
        return response()->download($pdf);
    }
}

// Manual method injection (Container::call istifadə edərək)
class NotificationSender
{
    public function send(
        NotificationInterface $notification,
        UserRepository $userRepo,
        string $channel = 'email'
    ): void {
        $users = $userRepo->getSubscribedUsers();
        foreach ($users as $user) {
            $notification->sendTo($user, $channel);
        }
    }
}

// Container::call ilə çağırma
app()->call([new NotificationSender(), 'send'], [
    'notification' => new OrderNotification($order),
    'channel' => 'sms',
]);
// UserRepository avtomatik resolve olunacaq,
// notification və channel parametrləri manual verilir
```

### 3. Property Injection (Laravel-də nadir istifadə olunur)

*3. Property Injection (Laravel-də nadir istifadə olunur) üçün kod nümunəsi:*
```php
// Bu kod property injection nümunəsini göstərir
<?php

// Laravel-də property injection birbaşa dəstəklənmir,
// amma #[Inject] attribute ilə bəzi framework-larda mövcuddur

// Laravel-də əvəzinə setter injection və ya constructor injection istifadə olunur

class Service
{
    private ?LoggerInterface $logger = null;

    // Setter injection
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    // Və ya attribute ilə (Laravel native deyil, amma custom implement oluna bilər)
    // #[Inject]
    // private LoggerInterface $logger;
}

// Livewire-da property injection:
use Livewire\Component;

class UserList extends Component
{
    // Livewire mount() ilə inject edir
    public function mount(UserRepository $repository): void
    {
        $this->users = $repository->getAll();
    }
}
```

---

## Binding Types

### bind() — Hər dəfə yeni instance

*bind() — Hər dəfə yeni instance üçün kod nümunəsi:*
```php
// Bu kod bind() ilə hər çağırışda yeni instance yaratmağı göstərir
<?php

// 1. Class-dan class-a binding
$this->app->bind(PaymentGatewayInterface::class, StripeGateway::class);

// 2. Closure ilə binding
$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    return new StripeGateway(
        config('services.stripe.key'),
        config('services.stripe.secret'),
        $app->make(LoggerInterface::class)
    );
});

// 3. Hər resolve zamanı YENİ instance qaytarılır
$a = app(PaymentGatewayInterface::class);
$b = app(PaymentGatewayInterface::class);
var_dump($a === $b); // false — fərqli object-lərdir
```

### singleton() — Eyni instance

*singleton() — Eyni instance üçün kod nümunəsi:*
```php
// Bu kod singleton() ilə tək instance paylaşmağı göstərir
<?php

// 1. Class-dan class-a singleton
$this->app->singleton(CacheManager::class, RedisCacheManager::class);

// 2. Closure ilə singleton
$this->app->singleton(CacheManager::class, function ($app) {
    return new RedisCacheManager(
        config('cache.redis.host'),
        config('cache.redis.port')
    );
});

// 3. İlk dəfə resolve olunanda yaradılır, sonra həmişə eyni instance qaytarılır
$a = app(CacheManager::class);
$b = app(CacheManager::class);
var_dump($a === $b); // true — eyni object-dir

// Singleton nə vaxt istifadə etməli:
// - Database connection
// - Cache manager
// - Config instance
// - Logger (əgər state saxlamırsa)
// - External API client-lar
```

### scoped() — Request ərzində singleton

*scoped() — Request ərzində singleton üçün kod nümunəsi:*
```php
// Bu kod scoped() ilə hər HTTP request üçün ayrı singleton yaratmağı göstərir
<?php

// scoped binding — hər request üçün ayrı singleton yaradılır
// Əsasən queue worker, Octane, test zamanı faydalıdır

$this->app->scoped(CartService::class, function ($app) {
    return new CartService(
        $app->make(SessionManager::class)
    );
});

// Eyni request daxilində eyni instance qaytarılır
// Amma yeni request (və ya queue job) olduqda yeni instance yaradılır

// Laravel Octane-da çox vacibdir:
// Singleton — bütün request-lər üçün eyni (state leak riski)
// Scoped — hər request üçün ayrı (təhlükəsiz)

// Queue worker-da:
// Singleton — bütün job-lar üçün eyni
// Scoped — hər job üçün ayrı
```

### instance() — Mövcud object-i binding etmə

*instance() — Mövcud object-i binding etmə üçün kod nümunəsi:*
```php
// Bu kod instance() ilə mövcud obyekti container-ə qeydiyyatdan keçirməyi göstərir
<?php

// Artıq yaradılmış object-i container-ə qeydiyyatdan keçirmək
$config = new AppConfig([
    'debug' => true,
    'timezone' => 'Asia/Baku',
]);

$this->app->instance(AppConfig::class, $config);

// İndi hər yerdə eyni $config instance gələcək
$a = app(AppConfig::class);
var_dump($a === $config); // true

// instance() həmçinin singleton kimi işləyir —
// həmişə eyni object qaytarılır
```

### Digər Binding Üsulları

*Digər Binding Üsulları üçün kod nümunəsi:*
```php
// Bu kod contextual binding, tag və extend kimi əlavə binding üsullarını göstərir
<?php

// === when()->needs()->give() — Contextual Binding (aşağıda ətraflı) ===

// === bindIf() — Yalnız əgər binding mövcud deyilsə ===
$this->app->bindIf(LoggerInterface::class, FileLogger::class);
// Əgər artıq bind olunubsa, üstünə yazılmır

// === singletonIf() ===
$this->app->singletonIf(CacheManager::class, RedisCacheManager::class);

// === alias() — Alternativ ad vermək ===
$this->app->alias(PaymentGatewayInterface::class, 'payment');
// İndi app('payment') ilə də çağırmaq olar

// === rebinding ===
$this->app->rebinding(CacheManager::class, function ($app, $cache) {
    // CacheManager yenidən bind olunanda çağırılır
    // Mövcud instance-ları yeniləmək üçün istifadə olunur
});
```

---

## Contextual Binding

Eyni interface üçün fərqli context-lərdə fərqli implementation təmin etmək:

*Eyni interface üçün fərqli context-lərdə fərqli implementation təmin e üçün kod nümunəsi:*
```php
// Bu kod contextual binding ilə eyni interface-ə fərqli implementasiyalar bağlamağı göstərir
<?php

interface FilesystemInterface
{
    public function put(string $path, string $content): bool;
    public function get(string $path): string;
    public function delete(string $path): bool;
}

class LocalFilesystem implements FilesystemInterface
{
    public function put(string $path, string $content): bool
    {
        return file_put_contents($path, $content) !== false;
    }

    public function get(string $path): string
    {
        return file_get_contents($path);
    }

    public function delete(string $path): bool
    {
        return unlink($path);
    }
}

class S3Filesystem implements FilesystemInterface
{
    public function __construct(
        private readonly string $bucket,
        private readonly string $region
    ) {}

    public function put(string $path, string $content): bool
    {
        // S3-ə upload
        return true;
    }

    public function get(string $path): string
    {
        // S3-dən oxu
        return '';
    }

    public function delete(string $path): bool
    {
        // S3-dən sil
        return true;
    }
}

class ProfilePhotoController
{
    // Bu controller-ə Local filesystem inject olacaq
    public function __construct(
        private readonly FilesystemInterface $filesystem
    ) {}
}

class VideoController
{
    // Bu controller-ə S3 filesystem inject olacaq
    public function __construct(
        private readonly FilesystemInterface $filesystem
    ) {}
}

class BackupController
{
    // Bu controller-ə S3 filesystem inject olacaq
    public function __construct(
        private readonly FilesystemInterface $filesystem
    ) {}
}

// Service Provider-də contextual binding:
class FileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ProfilePhotoController üçün Local
        $this->app->when(ProfilePhotoController::class)
                   ->needs(FilesystemInterface::class)
                   ->give(function () {
                       return new LocalFilesystem();
                   });

        // VideoController üçün S3
        $this->app->when(VideoController::class)
                   ->needs(FilesystemInterface::class)
                   ->give(function () {
                       return new S3Filesystem(
                           config('filesystems.disks.s3.bucket'),
                           config('filesystems.disks.s3.region')
                       );
                   });

        // Bir neçə class üçün eyni binding
        $this->app->when([VideoController::class, BackupController::class])
                   ->needs(FilesystemInterface::class)
                   ->give(S3Filesystem::class);

        // Primitive dəyər üçün contextual binding
        $this->app->when(S3Filesystem::class)
                   ->needs('$bucket')
                   ->give(config('filesystems.disks.s3.bucket'));

        $this->app->when(S3Filesystem::class)
                   ->needs('$region')
                   ->give(config('filesystems.disks.s3.region'));

        // Config dəyəri ilə
        $this->app->when(MailService::class)
                   ->needs('$fromAddress')
                   ->giveConfig('mail.from.address');
    }
}
```

---

## Tagging

Eyni kateqoriyaya aid binding-ləri qruplaşdırmaq:

*Eyni kateqoriyaya aid binding-ləri qruplaşdırmaq üçün kod nümunəsi:*
```php
// Bu kod tag() ilə eyni kateqoriyalı binding-ləri qruplaşdırmağı göstərir
<?php

interface ReportInterface
{
    public function generate(array $data): string;
    public function getName(): string;
}

class SalesReport implements ReportInterface
{
    public function generate(array $data): string
    {
        return "Satış hesabatı: " . count($data) . " qeyd";
    }

    public function getName(): string
    {
        return 'Satış Hesabatı';
    }
}

class InventoryReport implements ReportInterface
{
    public function generate(array $data): string
    {
        return "Anbar hesabatı: " . count($data) . " qeyd";
    }

    public function getName(): string
    {
        return 'Anbar Hesabatı';
    }
}

class FinancialReport implements ReportInterface
{
    public function generate(array $data): string
    {
        return "Maliyyə hesabatı: " . count($data) . " qeyd";
    }

    public function getName(): string
    {
        return 'Maliyyə Hesabatı';
    }
}

class UserActivityReport implements ReportInterface
{
    public function generate(array $data): string
    {
        return "İstifadəçi aktivliyi hesabatı";
    }

    public function getName(): string
    {
        return 'İstifadəçi Aktivliyi';
    }
}

// Service Provider-də tagging:
class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('report.sales', SalesReport::class);
        $this->app->bind('report.inventory', InventoryReport::class);
        $this->app->bind('report.financial', FinancialReport::class);
        $this->app->bind('report.activity', UserActivityReport::class);

        // Hamısını "reports" tagi ilə qruplaşdır
        $this->app->tag([
            'report.sales',
            'report.inventory',
            'report.financial',
            'report.activity',
        ], 'reports');
    }
}

// İstifadə:
class ReportAggregator
{
    private array $reports;

    public function __construct()
    {
        // Tag ilə bütün report-ları al
        $this->reports = iterator_to_array(app()->tagged('reports'));
    }

    public function generateAll(array $data): array
    {
        $results = [];
        foreach ($this->reports as $report) {
            $results[$report->getName()] = $report->generate($data);
        }
        return $results;
    }
}

// Və ya constructor-da inject et
class ReportManager
{
    /**
     * @param ReportInterface[] $reports
     */
    public function __construct(
        private readonly iterable $reports
    ) {}

    public function getAvailableReports(): array
    {
        $names = [];
        foreach ($this->reports as $report) {
            $names[] = $report->getName();
        }
        return $names;
    }
}

// Binding:
$this->app->when(ReportManager::class)
           ->needs('$reports')
           ->giveTagged('reports');
```

---

## Extending Bindings

Mövcud binding-i dekorasiya etmək və ya modifikasiya etmək:

*Mövcud binding-i dekorasiya etmək və ya modifikasiya etmək üçün kod nümunəsi:*
```php
// Bu kod extend() ilə mövcud binding-i dekorasiya etməyi göstərir
<?php

interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): void;
}

class RedisCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        // Redis-dən oxu
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        // Redis-ə yaz
    }
}

class LoggingCacheDecorator implements CacheInterface
{
    public function __construct(
        private readonly CacheInterface $inner,
        private readonly LoggerInterface $logger
    ) {}

    public function get(string $key): mixed
    {
        $this->logger->log("Cache GET: $key");
        $value = $this->inner->get($key);
        $this->logger->log("Cache " . ($value !== null ? "HIT" : "MISS") . ": $key");
        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->logger->log("Cache SET: $key (TTL: $ttl)");
        $this->inner->set($key, $value, $ttl);
    }
}

class MetricsCacheDecorator implements CacheInterface
{
    private int $hits = 0;
    private int $misses = 0;

    public function __construct(
        private readonly CacheInterface $inner
    ) {}

    public function get(string $key): mixed
    {
        $value = $this->inner->get($key);
        $value !== null ? $this->hits++ : $this->misses++;
        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->inner->set($key, $value, $ttl);
    }

    public function getStats(): array
    {
        return ['hits' => $this->hits, 'misses' => $this->misses];
    }
}

// Service Provider-də extend:
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Əsas binding
        $this->app->singleton(CacheInterface::class, RedisCache::class);

        // Logging decorator əlavə et
        $this->app->extend(CacheInterface::class, function (CacheInterface $cache, $app) {
            return new LoggingCacheDecorator(
                $cache,
                $app->make(LoggerInterface::class)
            );
        });

        // Metrics decorator əlavə et (üstdən)
        $this->app->extend(CacheInterface::class, function (CacheInterface $cache, $app) {
            return new MetricsCacheDecorator($cache);
        });

        // Nəticə: MetricsCacheDecorator(LoggingCacheDecorator(RedisCache))
    }
}
```

---

## register() vs boot()

Bu fərqi anlamaq çox vacibdir!

*Bu fərqi anlamaq çox vacibdir! üçün kod nümunəsi:*
```php
// Bu kod register() və boot() metodları arasındakı fərqi göstərir
<?php

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * REGISTER — Yalnız container binding-lər!
     *
     * Bu metod çağırıldıqda digər Service Provider-lər
     * hələ register olmaya bilər. Ona görə burada:
     *
     * ✅ bind(), singleton(), instance(), alias()
     * ✅ mergeConfigFrom()
     *
     * ❌ Route qeydiyyatı
     * ❌ Event listener
     * ❌ View composer
     * ❌ Gate/Policy
     * ❌ Validation rule
     * ❌ Başqa service-lərə istinad (resolve)
     * ❌ Config dəyərləri oxuma (boot-da oxu)
     */
    public function register(): void
    {
        // Config faylını merge et
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/payment.php', 'payment'
        );

        // Interface -> Implementation binding
        $this->app->singleton(PaymentGatewayInterface::class, function ($app) {
            // Burada config oxumaq əslində təhlükəlidir,
            // amma mergeConfigFrom etdiyimiz üçün işləyir
            $driver = config('payment.default_driver', 'stripe');

            return match ($driver) {
                'stripe' => new StripeGateway(config('payment.stripe.key')),
                'paypal' => new PaypalGateway(config('payment.paypal.client_id')),
                default => throw new \InvalidArgumentException("Unknown payment driver: $driver"),
            };
        });

        // Digər binding-lər
        $this->app->bind(PaymentLogger::class, function ($app) {
            return new PaymentLogger(
                $app->make(LoggerInterface::class),
                'payments'
            );
        });
    }

    /**
     * BOOT — Bütün Service Provider-lər register olduqdan sonra çağırılır.
     *
     * Burada digər service-lərə istifad edə bilərik:
     *
     * ✅ Route qeydiyyatı
     * ✅ Event listener
     * ✅ View composer
     * ✅ Gate/Policy
     * ✅ Validation rule
     * ✅ Observer qeydiyyatı
     * ✅ Blade directive
     * ✅ Macro qeydiyyatı
     * ✅ Migration path əlavəsi
     * ✅ Publish ediləcək fayllar
     * ✅ Digər service-lərdən istifadə
     * ✅ Dependency injection (boot method parametrlərinə)
     */
    public function boot(): void
    {
        // Migration-lar
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'payment');

        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/payment.php');

        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/payment.php' => config_path('payment.php'),
        ], 'payment-config');

        // Event listener
        \Illuminate\Support\Facades\Event::listen(
            PaymentCompleted::class,
            SendPaymentReceipt::class
        );

        // Observer
        Payment::observe(PaymentObserver::class);

        // Custom validation rule
        \Illuminate\Support\Facades\Validator::extend(
            'valid_card',
            function ($attribute, $value) {
                return (new CardValidator())->validate($value);
            }
        );

        // Blade directive
        \Illuminate\Support\Facades\Blade::directive('money', function ($expression) {
            return "<?php echo number_format($expression, 2, '.', ',') . ' AZN'; ?>";
        });

        // Boot metodunda dependency injection:
        // public function boot(PaymentGatewayInterface $gateway): void
        // Laravel bu parametrləri avtomatik resolve edir
    }
}
```

### register() Qaydalarının Pozulma Nümunəsi

*register() Qaydalarının Pozulma Nümunəsi üçün kod nümunəsi:*
```php
// Bu kod register()-da başqa service-ə istinadın nə üçün yanlış olduğunu göstərir
<?php

// ❌ YANLIŞ — register()-da başqa service-ə istinad
class BadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bu, EventServiceProvider hələ register olmaya biləcəyi üçün xəta verə bilər
        Event::listen(SomeEvent::class, SomeListener::class);

        // Bu da problem yarada bilər
        $router = $this->app->make('router');
        $router->get('/api/test', function () { return 'test'; });

        // Bu da yanlışdır — config provider hələ register olmaya bilər
        $this->app->singleton(SomeService::class, function () {
            // config() hələ tam yüklənməyə bilər
            return new SomeService(config('some.key'));
        });
    }
}

// ✅ DÜZGÜN — Closure daxilində config oxuma (lazy evaluation)
class GoodServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Closure deyilənə qədər execute olmur,
        // o vaxta qədər config artıq yüklənmiş olur
        $this->app->singleton(SomeService::class, function ($app) {
            return new SomeService(config('some.key'));
        });
    }

    public function boot(): void
    {
        // Bütün "action" əməliyyatları burada
        Event::listen(SomeEvent::class, SomeListener::class);

        Route::middleware('api')->group(function () {
            Route::get('/api/test', function () { return 'test'; });
        });
    }
}
```

---

## Deferred Providers

Deferred Provider hər request-də deyil, yalnız lazım olduqda yüklənir. Bu, performance üçün vacibdir.

*Deferred Provider hər request-də deyil, yalnız lazım olduqda yüklənir üçün kod nümunəsi:*
```php
// Bu kod deferred provider ilə lazy loading implementasiyasını göstərir
<?php

use Illuminate\Contracts\Support\DeferrableProvider;

class HeavyServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bu metod yalnız provides() siyahısındakı
     * class-lardan biri resolve olunanda çağırılır.
     */
    public function register(): void
    {
        $this->app->singleton(PdfGenerator::class, function ($app) {
            // Ağır bir kitabxana yüklənir
            return new PdfGenerator(config('pdf.options'));
        });

        $this->app->singleton(ExcelExporter::class, function ($app) {
            return new ExcelExporter();
        });
    }

    /**
     * Bu Service Provider hansı binding-ləri təmin edir?
     * Container bu siyahıya baxaraq provider-i lazım olduqda yükləyir.
     */
    public function provides(): array
    {
        return [
            PdfGenerator::class,
            ExcelExporter::class,
        ];
    }
}

// Necə işləyir:
// 1. Application boot olarkən bu provider register olmur
// 2. app(PdfGenerator::class) çağırılanda:
//    a. Container binding tapmır
//    b. Deferred providers siyahısına baxır
//    c. PdfGenerator üçün HeavyServiceProvider tapır
//    d. HeavyServiceProvider->register() çağırılır
//    e. PdfGenerator resolve olunur

// provides() siyahısı bootstrap/cache/services.php faylında cache olunur
// php artisan clear-compiled bu cache-i silir
```

---

## Auto-Discovery

Laravel 5.5-dən etibarən package-lər avtomatik aşkar oluna bilir.

*Laravel 5.5-dən etibarən package-lər avtomatik aşkar oluna bilir üçün kod nümunəsi:*
```php
// Package-in composer.json faylında:
{
    "name": "vendor/my-package",
    "extra": {
        "laravel": {
            "providers": [
                "Vendor\\MyPackage\\MyPackageServiceProvider"
            ],
            "aliases": {
                "MyPackage": "Vendor\\MyPackage\\Facades\\MyPackage"
            }
        }
    }
}
```

*"MyPackage": "Vendor\\MyPackage\\Facades\\MyPackage" üçün kod nümunəsi:*
```php
// Bu kod package auto-discovery-ni söndürməyi göstərir
<?php

// Auto-discovery-ni söndürmək (config/app.php):
// Laravel 11-dən əvvəl:
'providers' => ServiceProvider::defaultProviders()->merge([
    // Manual əlavə olunan provider-lər
])->toArray(),

// Bəzi package-ləri auto-discovery-dən çıxarmaq:
// composer.json-da:
// {
//     "extra": {
//         "laravel": {
//             "dont-discover": [
//                 "vendor/specific-package"
//             ]
//         }
//     }
// }

// Bütün auto-discovery söndürmək:
// {
//     "extra": {
//         "laravel": {
//             "dont-discover": ["*"]
//         }
//     }
// }
```

---

## Custom Service Provider Yaratma

*Custom Service Provider Yaratma üçün kod nümunəsi:*
```bash
# Artisan əmri ilə yaratma
php artisan make:provider PaymentServiceProvider
```

*php artisan make:provider PaymentServiceProvider üçün kod nümunəsi:*
```php
// Bu kod artisan ilə yaradılan service provider-in skelet strukturunu göstərir
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SMSServiceInterface;
use App\Services\Payment\StripeGateway;
use App\Services\Payment\PaypalGateway;
use App\Services\Payment\PaymentManager;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/payment.php',
            'payment'
        );

        // Manager pattern
        $this->app->singleton('payment', function ($app) {
            return new PaymentManager($app);
        });

        // Default driver binding
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app['payment']->driver();
        });
    }

    public function boot(): void
    {
        // Config publish
        $this->publishes([
            __DIR__ . '/../../config/payment.php' => config_path('payment.php'),
        ], 'config');

        // Migration publish
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }
}
```

---

## Real-World Nümunələr

### 1. Payment Gateway Service Provider

*1. Payment Gateway Service Provider üçün kod nümunəsi:*
```php
// Bu kod ödəniş gateway-ni qeydiyyatdan keçirən service provider-i göstərir
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\RefundableInterface;
use App\Contracts\Payment\SubscriptionInterface;
use App\Services\Payment\StripeGateway;
use App\Services\Payment\PaypalGateway;
use App\Services\Payment\KapitalBankGateway;
use App\Services\Payment\PaymentManager;

// === Interface-lər ===

interface PaymentGatewayInterface
{
    public function charge(float $amount, string $currency, array $options = []): PaymentResult;
    public function verify(string $transactionId): PaymentStatus;
}

interface RefundableInterface
{
    public function refund(string $transactionId, ?float $amount = null): RefundResult;
}

interface SubscriptionInterface
{
    public function createSubscription(string $planId, array $customerData): Subscription;
    public function cancelSubscription(string $subscriptionId): bool;
}

// === Implementation-lar ===

class StripeGateway implements PaymentGatewayInterface, RefundableInterface, SubscriptionInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $webhookSecret,
        private readonly LoggerInterface $logger
    ) {}

    public function charge(float $amount, string $currency, array $options = []): PaymentResult
    {
        $this->logger->log("Stripe charge: $amount $currency");
        // Stripe API call...
        return new PaymentResult(true, 'txn_' . uniqid());
    }

    public function verify(string $transactionId): PaymentStatus
    {
        return new PaymentStatus('completed');
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        return new RefundResult(true);
    }

    public function createSubscription(string $planId, array $customerData): Subscription
    {
        return new Subscription('sub_' . uniqid());
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        return true;
    }
}

class PaypalGateway implements PaymentGatewayInterface, RefundableInterface
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly bool $sandbox = false
    ) {}

    public function charge(float $amount, string $currency, array $options = []): PaymentResult
    {
        // PayPal API call...
        return new PaymentResult(true, 'PAY-' . uniqid());
    }

    public function verify(string $transactionId): PaymentStatus
    {
        return new PaymentStatus('completed');
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        return new RefundResult(true);
    }
}

class KapitalBankGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $merchantId,
        private readonly string $terminalId,
        private readonly string $certPath
    ) {}

    public function charge(float $amount, string $currency, array $options = []): PaymentResult
    {
        // Kapital Bank E-Commerce API call...
        return new PaymentResult(true, 'KB-' . uniqid());
    }

    public function verify(string $transactionId): PaymentStatus
    {
        return new PaymentStatus('completed');
    }
}

// === Manager (Strategy Pattern) ===

class PaymentManager
{
    private array $drivers = [];

    public function __construct(
        private readonly \Illuminate\Contracts\Foundation\Application $app
    ) {}

    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    private function createDriver(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'stripe' => $this->createStripeDriver(),
            'paypal' => $this->createPaypalDriver(),
            'kapitalbank' => $this->createKapitalBankDriver(),
            default => throw new \InvalidArgumentException("Payment driver [$name] is not supported."),
        };
    }

    private function createStripeDriver(): StripeGateway
    {
        return new StripeGateway(
            apiKey: config('payment.drivers.stripe.key'),
            apiSecret: config('payment.drivers.stripe.secret'),
            webhookSecret: config('payment.drivers.stripe.webhook_secret'),
            logger: $this->app->make(LoggerInterface::class)
        );
    }

    private function createPaypalDriver(): PaypalGateway
    {
        return new PaypalGateway(
            clientId: config('payment.drivers.paypal.client_id'),
            clientSecret: config('payment.drivers.paypal.client_secret'),
            sandbox: config('payment.drivers.paypal.sandbox', true)
        );
    }

    private function createKapitalBankDriver(): KapitalBankGateway
    {
        return new KapitalBankGateway(
            merchantId: config('payment.drivers.kapitalbank.merchant_id'),
            terminalId: config('payment.drivers.kapitalbank.terminal_id'),
            certPath: config('payment.drivers.kapitalbank.cert_path')
        );
    }

    private function getDefaultDriver(): string
    {
        return config('payment.default', 'stripe');
    }
}

// === Service Provider ===

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payment.php', 'payment');

        // PaymentManager singleton olaraq
        $this->app->singleton(PaymentManager::class);
        $this->app->alias(PaymentManager::class, 'payment');

        // Default gateway interface binding
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentManager::class)->driver();
        });

        // RefundableInterface üçün (yalnız refund dəstəkləyən driver-lar)
        $this->app->bind(RefundableInterface::class, function ($app) {
            $driver = $app->make(PaymentManager::class)->driver();
            if (!$driver instanceof RefundableInterface) {
                throw new \RuntimeException(
                    "Current payment driver does not support refunds"
                );
            }
            return $driver;
        });

        // SubscriptionInterface üçün
        $this->app->bind(SubscriptionInterface::class, function ($app) {
            // Yalnız Stripe subscription dəstəkləyir
            return $app->make(PaymentManager::class)->driver('stripe');
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/payment.php' => config_path('payment.php'),
        ], 'payment-config');
    }
}

// === Config faylı (config/payment.php) ===
/*
return [
    'default' => env('PAYMENT_DRIVER', 'stripe'),

    'drivers' => [
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],
        'kapitalbank' => [
            'merchant_id' => env('KB_MERCHANT_ID'),
            'terminal_id' => env('KB_TERMINAL_ID'),
            'cert_path' => env('KB_CERT_PATH'),
        ],
    ],
];
*/

// === İstifadə ===

class CheckoutController
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway
    ) {}

    public function processPayment(Request $request): JsonResponse
    {
        $result = $this->gateway->charge(
            amount: $request->input('amount'),
            currency: 'AZN',
            options: ['description' => 'Order #' . $request->input('order_id')]
        );

        return response()->json([
            'success' => $result->success,
            'transaction_id' => $result->transactionId,
        ]);
    }
}

// Müəyyən driver istifadə etmək:
class AdminRefundController
{
    public function refund(Request $request): JsonResponse
    {
        $manager = app(PaymentManager::class);
        $gateway = $manager->driver('stripe'); // Konkret driver

        if ($gateway instanceof RefundableInterface) {
            $result = $gateway->refund($request->input('transaction_id'));
            return response()->json(['refunded' => $result->success]);
        }

        return response()->json(['error' => 'Refund not supported'], 400);
    }
}
```

### 2. SMS Service Provider

*2. SMS Service Provider üçün kod nümunəsi:*
```php
// Bu kod SMS göndərmə servisini qeydiyyatdan keçirən service provider-i göstərir
<?php

namespace App\Contracts;

interface SMSServiceInterface
{
    public function send(string $phone, string $message): SMSResult;
    public function sendBulk(array $phones, string $message): array;
    public function getBalance(): float;
}

class SMSResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null
    ) {}
}

// Implementation-lar

class TwilioSMSService implements SMSServiceInterface
{
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $fromNumber
    ) {}

    public function send(string $phone, string $message): SMSResult
    {
        // Twilio API call
        return new SMSResult(true, 'SM' . uniqid());
    }

    public function sendBulk(array $phones, string $message): array
    {
        return array_map(fn($phone) => $this->send($phone, $message), $phones);
    }

    public function getBalance(): float
    {
        return 100.0;
    }
}

class AzercellSMSService implements SMSServiceInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $sender
    ) {}

    public function send(string $phone, string $message): SMSResult
    {
        // Azercell SMS API call
        return new SMSResult(true, 'AZ' . uniqid());
    }

    public function sendBulk(array $phones, string $message): array
    {
        return array_map(fn($phone) => $this->send($phone, $message), $phones);
    }

    public function getBalance(): float
    {
        return 500.0;
    }
}

// Service Provider

class SMSServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sms.php', 'sms');

        $this->app->singleton(SMSServiceInterface::class, function ($app) {
            $driver = config('sms.driver', 'twilio');

            return match ($driver) {
                'twilio' => new TwilioSMSService(
                    accountSid: config('sms.twilio.account_sid'),
                    authToken: config('sms.twilio.auth_token'),
                    fromNumber: config('sms.twilio.from_number')
                ),
                'azercell' => new AzercellSMSService(
                    username: config('sms.azercell.username'),
                    password: config('sms.azercell.password'),
                    sender: config('sms.azercell.sender')
                ),
                default => throw new \InvalidArgumentException("Unknown SMS driver: $driver"),
            };
        });
    }
}

// İstifadə
class OTPService
{
    public function __construct(
        private readonly SMSServiceInterface $sms,
        private readonly CacheInterface $cache
    ) {}

    public function sendOTP(string $phone): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->cache->set("otp:$phone", $code, 300); // 5 dəqiqə
        $this->sms->send($phone, "Sizin OTP kodunuz: $code");
        return $code;
    }

    public function verifyOTP(string $phone, string $code): bool
    {
        $stored = $this->cache->get("otp:$phone");
        return $stored !== null && $stored === $code;
    }
}
```

### 3. External API Client Service Provider

*3. External API Client Service Provider üçün kod nümunəsi:*
```php
// Bu kod xarici API client-i qeydiyyatdan keçirən service provider-i göstərir
<?php

// API Client Interface
interface ExternalAPIClientInterface
{
    public function get(string $endpoint, array $params = []): array;
    public function post(string $endpoint, array $data = []): array;
}

// Concrete Implementation
class HttpAPIClient implements ExternalAPIClientInterface
{
    private ?string $token = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache
    ) {}

    public function get(string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $cacheKey = 'api:' . md5($url . serialize($params));

        // Cache-dən yoxla
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->logger->log("API Cache HIT: $url");
            return $cached;
        }

        $this->logger->log("API GET: $url");

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])
        ->timeout($this->timeout)
        ->get($url, $params);

        $data = $response->json();

        // Cache-ə yaz
        $this->cache->set($cacheKey, $data, 600);

        return $data;
    }

    public function post(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $this->logger->log("API POST: $url");

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])
        ->timeout($this->timeout)
        ->post($url, $data);

        return $response->json();
    }
}

// Rate-limited wrapper (Decorator pattern)
class RateLimitedAPIClient implements ExternalAPIClientInterface
{
    private array $requestLog = [];

    public function __construct(
        private readonly ExternalAPIClientInterface $client,
        private readonly int $maxRequests = 60,
        private readonly int $perSeconds = 60
    ) {}

    public function get(string $endpoint, array $params = []): array
    {
        $this->checkRateLimit();
        return $this->client->get($endpoint, $params);
    }

    public function post(string $endpoint, array $data = []): array
    {
        $this->checkRateLimit();
        return $this->client->post($endpoint, $data);
    }

    private function checkRateLimit(): void
    {
        $now = time();
        $this->requestLog = array_filter(
            $this->requestLog,
            fn($time) => $time > $now - $this->perSeconds
        );

        if (count($this->requestLog) >= $this->maxRequests) {
            throw new \RuntimeException('Rate limit exceeded');
        }

        $this->requestLog[] = $now;
    }
}

// Service Provider
class ExternalAPIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/external-api.php', 'external-api');

        $this->app->singleton(ExternalAPIClientInterface::class, function ($app) {
            $client = new HttpAPIClient(
                baseUrl: config('external-api.base_url'),
                apiKey: config('external-api.api_key'),
                timeout: config('external-api.timeout', 30),
                logger: $app->make(LoggerInterface::class),
                cache: $app->make(CacheInterface::class)
            );

            // Rate limiting aktiv isə dekorasiya et
            if (config('external-api.rate_limit.enabled', true)) {
                return new RateLimitedAPIClient(
                    client: $client,
                    maxRequests: config('external-api.rate_limit.max_requests', 60),
                    perSeconds: config('external-api.rate_limit.per_seconds', 60)
                );
            }

            return $client;
        });
    }
}
```

---

## Facades Necə İşləyir

Facade — Service Container-dəki service-lərə static-like syntax ilə daxil olmağın yoludur. Əslində static deyil!

*Facade — Service Container-dəki service-lərə static-like syntax ilə da üçün kod nümunəsi:*
```php
// Bu kod Facade-ın arxasındakı mexanizmi göstərir
<?php

// === Facade-ın arxasındakı mexanizm ===

// Addım 1: Cache::get('key') çağırılır
// Addım 2: Cache facade class-ına baxılır

namespace Illuminate\Support\Facades;

class Cache extends Facade
{
    /**
     * Service Container-dəki binding adını qaytarır
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cache'; // Container-dən 'cache' binding-ini istifadə et
    }
}

// Addım 3: Facade base class magic __callStatic ilə handle edir

abstract class Facade
{
    protected static array $resolvedInstance = [];

    /**
     * Magic method — static çağırış zamanı çağırılır
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        if (!$instance) {
            throw new RuntimeException('Facade root has not been set.');
        }

        // Static kimi görünən çağırış əslində instance method-dur!
        return $instance->$method(...$args);
    }

    /**
     * Container-dən real service instance-ını alır
     */
    public static function getFacadeRoot(): mixed
    {
        $name = static::getFacadeAccessor();

        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        $instance = app()[$name];

        // Singleton isə cache et
        static::$resolvedInstance[$name] = $instance;

        return $instance;
    }
}

// Addım 4: Nəticə
// Cache::get('key')
// ↓
// Cache::__callStatic('get', ['key'])
// ↓
// app('cache')->get('key')
// ↓
// CacheManager instance-ının get() metodu çağırılır

// === Custom Facade yaratma ===

// 1. Service class
namespace App\Services;

class CurrencyConverter
{
    public function __construct(
        private readonly ExternalAPIClientInterface $api
    ) {}

    public function convert(float $amount, string $from, string $to): float
    {
        $rate = $this->api->get("rates/$from/$to")['rate'];
        return $amount * $rate;
    }

    public function getRate(string $from, string $to): float
    {
        return $this->api->get("rates/$from/$to")['rate'];
    }
}

// 2. Facade class
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static float convert(float $amount, string $from, string $to)
 * @method static float getRate(string $from, string $to)
 *
 * @see \App\Services\CurrencyConverter
 */
class Currency extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\CurrencyConverter::class;
    }
}

// 3. Service Provider-dən binding
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\CurrencyConverter::class);
    }
}

// 4. İstifadə
use App\Facades\Currency;

$usdAmount = Currency::convert(100, 'AZN', 'USD');
$rate = Currency::getRate('AZN', 'EUR');
```

### Facade vs Dependency Injection

*Facade vs Dependency Injection üçün kod nümunəsi:*
```php
// Bu kod Facade və Dependency Injection arasındakı fərqi göstərir
<?php

// === Facade istifadəsi ===
class OrderService
{
    public function createOrder(array $data): Order
    {
        // Facade ilə
        Cache::put("order:{$data['id']}", $data, 3600);
        Log::info("Order created: {$data['id']}");
        Event::dispatch(new OrderCreated($data));

        return new Order($data);
    }
}

// === Dependency Injection istifadəsi (Daha yaxşı, daha test edilə bilən) ===
class OrderService
{
    public function __construct(
        private readonly \Illuminate\Contracts\Cache\Repository $cache,
        private readonly \Psr\Log\LoggerInterface $logger,
        private readonly \Illuminate\Contracts\Events\Dispatcher $events
    ) {}

    public function createOrder(array $data): Order
    {
        // DI ilə
        $this->cache->put("order:{$data['id']}", $data, 3600);
        $this->logger->info("Order created: {$data['id']}");
        $this->events->dispatch(new OrderCreated($data));

        return new Order($data);
    }
}

// DI üstünlükləri:
// 1. Test zamanı mock etmək asandır
// 2. Dependency-lər aydın görünür
// 3. Interface-ə bağlıdır, implementation dəyişə bilər
// 4. Static method çağırışları yoxdur

// Facade üstünlükləri:
// 1. Daha qısa kod
// 2. IDE autocomplete (@method annotation ilə)
// 3. Facade::fake() ilə asan test
```

---

## Real-time Facades

Laravel real-time facade ilə istənilən class-ı runtime-da facade-ə çevirə bilər:

*Laravel real-time facade ilə istənilən class-ı runtime-da facade-ə çev üçün kod nümunəsi:*
```php
// Bu kod real-time facade ilə istənilən class-ı facade-ə çevirməyi göstərir
<?php

namespace App\Services;

class Translator
{
    public function translate(string $text, string $to, string $from = 'az'): string
    {
        // Translation API call...
        return "[$to] $text";
    }
}

// Normal istifadə:
$translator = app(Translator::class);
$result = $translator->translate('Salam', 'en');

// Real-time Facade istifadəsi:
// Class adının başına "Facades\" əlavə et
use Facades\App\Services\Translator;

$result = Translator::translate('Salam', 'en');

// Arxada nə baş verir:
// 1. PHP autoloader "Facades\App\Services\Translator" class-ını axtarır
// 2. Laravel-in AliasLoader-i bunu tutur
// 3. Dinamik olaraq Facade class yaradır:
//    class Translator extends Facade {
//        protected static function getFacadeAccessor() {
//            return 'App\Services\Translator';
//        }
//    }
// 4. Container-dən App\Services\Translator resolve olunur
// 5. translate() metodu çağırılır

// Test zamanı mock etmə:
use Facades\App\Services\Translator;

Translator::shouldReceive('translate')
    ->with('Salam', 'en', 'az')
    ->once()
    ->andReturn('Hello');
```

---

## Interface-dən Implementation-a Binding

*Interface-dən Implementation-a Binding üçün kod nümunəsi:*
```php
// Bu kod layered arxitekturada interface-dən implementasiyaya binding-i göstərir
<?php

// === Layered Architecture ilə tam nümunə ===

// 1. Interface-lər (Contracts)
namespace App\Contracts;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
}

interface NotificationChannelInterface
{
    public function send(User $user, string $message, array $data = []): bool;
}

interface SearchServiceInterface
{
    public function search(string $query, array $filters = []): SearchResult;
    public function index(string $model, array $data): bool;
}

// 2. Eloquent Implementation
namespace App\Repositories;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model
    ) {}

    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->findById($id);
        $user->update($data);
        return $user->fresh();
    }

    public function delete(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->latest()->paginate($perPage);
    }
}

// 3. Notification channel-lar
class EmailNotificationChannel implements NotificationChannelInterface
{
    public function send(User $user, string $message, array $data = []): bool
    {
        Mail::to($user->email)->send(new GenericMail($message, $data));
        return true;
    }
}

class SMSNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly SMSServiceInterface $sms
    ) {}

    public function send(User $user, string $message, array $data = []): bool
    {
        $result = $this->sms->send($user->phone, $message);
        return $result->success;
    }
}

class PushNotificationChannel implements NotificationChannelInterface
{
    public function send(User $user, string $message, array $data = []): bool
    {
        // FCM / APNs call
        return true;
    }
}

// 4. Service Provider — Hamısını birləşdirmək
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository binding-lər
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);

        // Notification channel-lar (contextual binding)
        $this->app->when(OrderNotificationService::class)
                   ->needs(NotificationChannelInterface::class)
                   ->give(EmailNotificationChannel::class);

        $this->app->when(OTPService::class)
                   ->needs(NotificationChannelInterface::class)
                   ->give(SMSNotificationChannel::class);

        // Search service
        $this->app->bind(SearchServiceInterface::class, function ($app) {
            $driver = config('search.driver', 'elasticsearch');
            return match ($driver) {
                'elasticsearch' => $app->make(ElasticsearchService::class),
                'algolia' => $app->make(AlgoliaService::class),
                'meilisearch' => $app->make(MeilisearchService::class),
                default => $app->make(DatabaseSearchService::class),
            };
        });
    }
}

// 5. Controller — Yalnız interface-lər ilə işləyir
class UserController
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SearchServiceInterface $search
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->users->paginate());
    }

    public function search(Request $request): JsonResponse
    {
        $results = $this->search->search($request->input('q'));
        return response()->json($results);
    }
}
```

---

## Config-based Implementation Switching

*Config-based Implementation Switching üçün kod nümunəsi:*
```php
// Bu kod konfiqurasiyaya əsasən implementasiyanı dinamik dəyişməyi göstərir
<?php

// config/services.php
return [
    'cache' => [
        'driver' => env('CACHE_DRIVER', 'redis'),
        // 'redis', 'memcached', 'file', 'database', 'array'
    ],

    'mail' => [
        'driver' => env('MAIL_DRIVER', 'smtp'),
        // 'smtp', 'sendmail', 'mailgun', 'ses', 'postmark'
    ],

    'queue' => [
        'driver' => env('QUEUE_DRIVER', 'redis'),
        // 'sync', 'database', 'redis', 'sqs', 'beanstalkd'
    ],

    'search' => [
        'driver' => env('SEARCH_DRIVER', 'database'),
        // 'database', 'elasticsearch', 'algolia', 'meilisearch'
    ],
];

// Service Provider-də config-ə əsaslanan switching:
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SearchServiceInterface::class, function ($app) {
            $driver = config('services.search.driver');

            return match ($driver) {
                'elasticsearch' => new ElasticsearchService(
                    host: config('services.search.elasticsearch.host'),
                    port: config('services.search.elasticsearch.port'),
                    index: config('services.search.elasticsearch.index')
                ),

                'algolia' => new AlgoliaService(
                    appId: config('services.search.algolia.app_id'),
                    apiKey: config('services.search.algolia.api_key'),
                    index: config('services.search.algolia.index')
                ),

                'meilisearch' => new MeilisearchService(
                    host: config('services.search.meilisearch.host'),
                    apiKey: config('services.search.meilisearch.api_key')
                ),

                default => new DatabaseSearchService(
                    $app->make(\Illuminate\Database\DatabaseManager::class)
                ),
            };
        });
    }
}

// .env faylını dəyişərək implementation dəyişdirmək:
// SEARCH_DRIVER=elasticsearch  -> ElasticsearchService
// SEARCH_DRIVER=algolia        -> AlgoliaService
// SEARCH_DRIVER=database       -> DatabaseSearchService

// Heç bir kod dəyişikliyi lazım deyil!
```

---

## Testing ilə Service Provider

*Testing ilə Service Provider üçün kod nümunəsi:*
```php
// Bu kod service provider-ləri test zamanı override etməyi göstərir
<?php

use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    /**
     * Test üçün mock binding
     */
    public function test_order_creation(): void
    {
        // Mock PaymentGateway
        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockGateway->expects($this->once())
                    ->method('charge')
                    ->with(99.99, 'AZN')
                    ->willReturn(new PaymentResult(true, 'txn_123'));

        // Container-ə mock bind et
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        // Service-i resolve et (mock gateway inject olunacaq)
        $orderService = app(OrderService::class);
        $order = $orderService->createOrder([
            'amount' => 99.99,
            'currency' => 'AZN',
        ]);

        $this->assertTrue($order->isPaid());
    }

    /**
     * Facade mock
     */
    public function test_notification_sent(): void
    {
        // Facade::fake() ilə mock
        Notification::fake();

        $user = User::factory()->create();
        $orderService = app(OrderService::class);
        $orderService->createOrder(['user_id' => $user->id]);

        // Notification göndərildiyini yoxla
        Notification::assertSentTo($user, OrderConfirmation::class);
    }

    /**
     * Temporary binding override
     */
    public function test_with_different_implementation(): void
    {
        // Test üçün fərqli implementation bind et
        $this->app->bind(CacheInterface::class, ArrayCacheDriver::class);
        // ArrayCacheDriver real cache əvəzinə array istifadə edir

        $service = app(UserService::class);
        // ...assertions
    }

    /**
     * Swap metodu ilə temporary replacement
     */
    public function test_swap(): void
    {
        $mock = $this->mock(ExternalAPIClientInterface::class);
        $mock->shouldReceive('get')
             ->with('users/1')
             ->once()
             ->andReturn(['id' => 1, 'name' => 'Test']);

        // $this->mock() avtomatik olaraq container-ə bind edir
        $service = app(UserService::class);
        // ExternalAPIClientInterface mock olunmuş versiya olacaq
    }

    /**
     * partialMock — bəzi method-ları mock et, qalanları real
     */
    public function test_partial_mock(): void
    {
        $this->partialMock(PaymentManager::class, function ($mock) {
            $mock->shouldReceive('driver')
                 ->with('stripe')
                 ->andReturn(new FakeStripeGateway());
        });

        $manager = app(PaymentManager::class);
        // driver('stripe') mock-dur, digər method-lar real işləyir
    }
}

// === Fake Implementation Pattern ===

class FakePaymentGateway implements PaymentGatewayInterface
{
    private array $charges = [];

    public function charge(float $amount, string $currency, array $options = []): PaymentResult
    {
        $this->charges[] = compact('amount', 'currency', 'options');
        return new PaymentResult(true, 'fake_txn_' . count($this->charges));
    }

    public function verify(string $transactionId): PaymentStatus
    {
        return new PaymentStatus('completed');
    }

    // Test assertion helper-ləri
    public function assertCharged(float $amount): void
    {
        $found = collect($this->charges)->contains('amount', $amount);
        PHPUnit\Framework\Assert::assertTrue($found, "No charge found for amount: $amount");
    }

    public function assertChargeCount(int $count): void
    {
        PHPUnit\Framework\Assert::assertCount($count, $this->charges);
    }
}

// Test-də istifadə:
class PaymentTest extends TestCase
{
    public function test_checkout_charges_correct_amount(): void
    {
        $fakeGateway = new FakePaymentGateway();
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        $response = $this->postJson('/api/checkout', [
            'product_id' => 1,
            'quantity' => 2,
        ]);

        $response->assertOk();
        $fakeGateway->assertCharged(199.98);
        $fakeGateway->assertChargeCount(1);
    }
}
```

---

## Laravel-in Öz Service Provider-ləri

*Laravel-in Öz Service Provider-ləri üçün kod nümunəsi:*
```php
// Bu kod Laravel-in daxili RouteServiceProvider strukturunu göstərir
<?php

// === RouteServiceProvider ===
// Route-ları yükləyir, rate limiting, model binding konfiqurasiyası

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/dashboard';

    public function boot(): void
    {
        // Rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Custom rate limiter
        RateLimiter::for('uploads', function (Request $request) {
            return $request->user()->isPremium()
                ? Limit::none()
                : Limit::perMinute(10);
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Admin routes
            Route::middleware(['web', 'auth', 'admin'])
                ->prefix('admin')
                ->group(base_path('routes/admin.php'));
        });
    }
}

// === AuthServiceProvider ===
// Gate, Policy, Guard konfiqurasiyası

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Post::class => PostPolicy::class,
        Comment::class => CommentPolicy::class,
    ];

    public function boot(): void
    {
        // Gate-lər
        Gate::define('admin', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('manage-users', function (User $user) {
            return $user->hasPermission('manage-users');
        });

        // Super admin — bütün gate-lərdən keçir
        Gate::before(function (User $user) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        // Implicit model policy resolution
        // Gate::guessPolicyNamesUsing(function ($modelClass) { ... });
    }
}

// === EventServiceProvider ===
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\OrderCreated::class => [
            \App\Listeners\SendOrderConfirmation::class,
            \App\Listeners\UpdateInventory::class,
            \App\Listeners\NotifyAdmin::class,
        ],
        \App\Events\UserRegistered::class => [
            \App\Listeners\SendWelcomeEmail::class,
            \App\Listeners\CreateDefaultSettings::class,
        ],
    ];

    protected $subscribe = [
        \App\Listeners\UserEventSubscriber::class,
    ];

    // Auto-discovery aktiv etmək:
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }

    protected function discoverEventsWithin(): array
    {
        return [
            $this->app->path('Listeners'),
        ];
    }
}

// === BroadcastServiceProvider ===
class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
        require base_path('routes/channels.php');
    }
}
```

---

## Best Practices

### 1. Hər zaman Interface-ə bind edin

*1. Hər zaman Interface-ə bind edin üçün kod nümunəsi:*
```php
// Bu kod interface-ə bind etməyin concrete class-a bağlılıqdan üstünlüyünü göstərir
<?php

// ❌ Concrete class-a birbaşa asılılıq
class OrderService
{
    public function __construct(
        private readonly StripeGateway $gateway // Dəyişmək çətindir
    ) {}
}

// ✅ Interface-ə asılılıq
class OrderService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway // Asanlıqla dəyişdirilə bilər
    ) {}
}
```

### 2. register()-da yalnız binding, boot()-da hər şey digər

*2. register()-da yalnız binding, boot()-da hər şey digər üçün kod nümunəsi:*
```php
// Bu kod register()-da event listener qeyd etməyin nəyə görə yanlış olduğunu göstərir
<?php

// ❌ register()-da event listener
public function register(): void
{
    Event::listen(...); // Yanlış yer
}

// ✅ boot()-da
public function boot(): void
{
    Event::listen(...); // Düzgün yer
}
```

### 3. Singleton-ı yalnız lazım olduqda istifadə edin

*3. Singleton-ı yalnız lazım olduqda istifadə edin üçün kod nümunəsi:*
```php
<?php

// ✅ Singleton üçün uyğun: connection, config, cache manager
$this->app->singleton(DatabaseConnection::class);
$this->app->singleton(CacheManager::class);

// ❌ Singleton üçün uyğun deyil: state saxlayan service-lər
// Bu, request-lər arası state leak yarada bilər (xüsusilə Octane-da)
$this->app->singleton(CartService::class); // Yanlış! scoped() istifadə edin
```

### 4. Deferred Provider istifadə edin (ağır service-lər üçün)

*4. Deferred Provider istifadə edin (ağır service-lər üçün) üçün kod nümunəsi:*
```php
<?php

// Hər request-də lazım olmayan service-lər üçün
class PdfServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void { /* ... */ }

    public function provides(): array
    {
        return [PdfGenerator::class, PdfMerger::class];
    }
}
```

### 5. Config publish etmə imkanı verin

*5. Config publish etmə imkanı verin üçün kod nümunəsi:*
```php
<?php

public function boot(): void
{
    $this->publishes([
        __DIR__ . '/../config/my-package.php' => config_path('my-package.php'),
    ], 'config');
}

public function register(): void
{
    $this->mergeConfigFrom(__DIR__ . '/../config/my-package.php', 'my-package');
}
```

### 6. Circular Dependency-dən qaçının

*6. Circular Dependency-dən qaçının üçün kod nümunəsi:*
```php
<?php

// ❌ Circular
class A { public function __construct(B $b) {} }
class B { public function __construct(A $a) {} }

// ✅ Interface ilə ayırma və ya event ilə kommunikasiya
class A { public function __construct(BInterface $b) {} }
class B { /* A-dan asılı deyil, event dinləyir */ }
```

---

## İntervyu Sualları

### Sual 1: Service Container nədir?
**Cavab:** Service Container (IoC Container) Laravel-in class dependency-lərini idarə edən və inject edən mərkəzi komponentidir. O, class-ların yaradılmasını, dependency resolution-ını və lifecycle idarəsini avtomatlaşdırır. Container binding-lər (abstract -> concrete mapping) saxlayır, auto-wiring ilə constructor parametrlərini resolve edir, singleton/scoped instance-ları idarə edir. Bütün Laravel service-ləri Container vasitəsilə qeydiyyatdan keçir və resolve olunur.

### Sual 2: bind() və singleton() arasındakı fərq nədir?
**Cavab:** `bind()` hər `make()` çağırışında yeni instance yaradır — hər resolve fərqli object-dir. `singleton()` isə ilk dəfə resolve olunanda instance yaradır, sonrakı çağırışlarda eyni instance-ı qaytarır. `singleton()` database connection, cache manager kimi paylaşılan resurslar üçün istifadə olunur. `scoped()` isə hər request üçün ayrı singleton yaradır — Octane və queue worker-larda state leak-in qarşısını alır.

### Sual 3: register() və boot() arasındakı fərq nədir?
**Cavab:** `register()` — yalnız container-ə binding qeydə almaq üçündür, başqa service-lərə istinad etməməlisiniz çünki onlar hələ register olmaya bilər. `boot()` — bütün provider-lərin register() metodu tamamlandıqdan sonra çağırılır, burada event listener, route, view composer, gate/policy və s. qeydə ala bilərsiniz, digər service-lərdən istifadə edə bilərsiniz. boot()-da dependency injection də işləyir.

### Sual 4: Contextual Binding nədir?
**Cavab:** Eyni interface üçün fərqli class-larda fərqli concrete implementation inject etmə. `$this->app->when(PhotoController::class)->needs(Filesystem::class)->give(LocalFilesystem::class)` — PhotoController-ə local, VideoController-ə S3 filesystem verilir. Həmçinin primitive dəyərlər üçün də istifadə olunur: `->needs('$apiKey')->giveConfig('services.api.key')`.

### Sual 5: Facade nədir və necə işləyir?
**Cavab:** Facade static-like syntax ilə container service-lərinə daxil olmaq üçündür. `Cache::get('key')` çağırıldıqda: 1) `__callStatic` tutur, 2) `getFacadeAccessor()` ilə container binding adını alır, 3) Container-dən real instance resolve olunur, 4) Instance-ın `get()` metodu çağırılır. Facade əslində static deyil, proxy pattern-dir. Test zamanı `Facade::fake()` ilə mock oluna bilir. Alternativ: Real-time Facades — `Facades\App\Services\MyService` prefiks ilə istənilən class facade olur.

### Sual 6: Deferred Provider nədir?
**Cavab:** `DeferrableProvider` interface-ini implement edən provider hər request-də register olmur. Yalnız `provides()` siyahısındakı class-lardan biri resolve olunanda register olunur. Bu, performance üçün vacibdir — hər request-də lazım olmayan ağır service-lər (PDF generator, Excel exporter) üçün istifadə olunur. `provides()` metodu hansı binding-ləri təmin etdiyini qaytarmalıdır.

### Sual 7: Tagging nə üçün istifadə olunur?
**Cavab:** Tagging eyni kateqoriyaya aid binding-ləri qruplaşdırmaq üçündür. Məsələn, bütün report class-larını "reports" tagi ilə qruplaşdırıb, `app()->tagged('reports')` ilə hamısını almaq olar. Bu, plugin sistemi, report aggregator, notification channel-lar kimi çoxsaylı eyni tipli service-ləri idarə etmək üçün faydalıdır. `giveTagged('reports')` ilə contextual binding-də istifadə olunur.

### Sual 8: Service Provider-də config merge etmə niyə lazımdır?
**Cavab:** `mergeConfigFrom()` package-in default config dəyərlərini application config ilə birləşdirir. İstifadəçi config publish etməsə belə, default dəyərlər mövcud olur. Publish edilmiş config dəyərləri default-ları override edir. Bu, package development üçün standard praktikadır.

### Sual 9: extend() nə edir?
**Cavab:** `extend()` mövcud binding-in resolve nəticəsini modifikasiya edir (Decorator pattern). Məsələn, `$this->app->extend(Cache::class, fn($cache, $app) => new LoggingCache($cache))` — Cache resolve olunandan sonra LoggingCache wrapper-ına sarılır. Bir neçə extend chain oluna bilər. Bu, mövcud service-ə logging, metrics, caching əlavə etmək üçün istifadə olunur.

### Sual 10: Singleton pattern-i Service Container ilə necə fərqlənir?
**Cavab:** Classic Singleton pattern — class özü özünün tək instance-ını idarə edir (static method, private constructor). Service Container singleton — Container bir class-ın tək instance-ını saxlayır. Fərq: Container singleton test zamanı asanlıqla mock/swap oluna bilir, DIP prinsipini pozmir, interface-lərə bind oluna bilir. Classic Singleton isə global state-dir, test çətindir, tight coupling yaradır. Container singleton həmişə üstündür.

### Sual 11: Laravel Octane ilə Service Provider-lar arasında fərq nədir?

**Cavab:** Octane tətbiqi yalnız bir dəfə boot edir (Swoole/RoadRunner worker başladıqda), sonra çoxlu request-ləri eyni prosesdə emal edir. Bu, Service Provider-lar üçün mühüm nəticələr doğurur:
- `register()` və `boot()` yalnız **bir dəfə** çağırılır — request başlangıcında deyil, worker boot-da.
- `singleton()` ilə bind olunan service-lər request-lər arasında paylaşılır — request-specific state saxlayan singleton-lar state leak yaradır.
- `scoped()` binding istifadə edin — Octane hər request başlamadan əvvəl scoped binding-ləri sıfırlayır.
- `boot()` metodunda `request()` helper-i istifadə etməyin — boot zamanı request yoxdur.
- `OctaneServiceProvider` yaradaraq `flush()` metodunda request-specific state-i təmizləmək olar.

### Sual 12: `callAfterResolving` nədir?

**Cavab:** `callAfterResolving` — Container bir class resolve etdikdən sonra əlavə callback çalışdırır. Dekorasiya (decoration), event, hook əlavə etmək üçün faydalıdır. `resolving()` metoduna bənzər, amma ilk resolve-dan sonra da işləyir (already resolved instance-lara da tətbiq olunur). Məsələn, hər `Logger` instance resolve olduqda ona avtomatik context əlavə etmək:
***Cavab:** `callAfterResolving` — Container bir class resolve etdikdən üçün kod nümunəsi:*
```php
$this->app->callAfterResolving(Logger::class, function ($logger, $app) {
    $logger->pushProcessor(new RequestIdProcessor());
});
```

---

## Anti-patternlər

**1. `boot()` Metodunda Ağır Əməliyyatlar**
Service Provider `boot()` metodunda database sorğusu, API çağırışı, böyük fayl oxuması etmək — hər request-də provider boot olduğundan tətbiq yavaşlayır. Boot metodunda yalnız event listener, route, view composer qeydiyyatı edin; ağır iş üçün Deferred Provider istifadə edin.

**2. Hər Şeyi `AppServiceProvider`-da Yerləşdirmək**
Bütün binding-ləri, observer-ları, event-ləri tək `AppServiceProvider`-a toplamaq — sinif nəhəng olur, axtarmaq çətinləşir, komanda üzvləri conflict yaradır. Hər domain/modul üçün ayrı ServiceProvider yazın: `PaymentServiceProvider`, `NotificationServiceProvider`.

**3. Interface Olmadan Concrete Class Bind Etmək**
`$this->app->bind(OrderService::class, OrderService::class)` kimi bind etmək — implementasiyanı dəyişmək və ya test zamanı mock etmək çətin olur. Həmişə interface bind edin: `$this->app->bind(OrderServiceInterface::class, OrderService::class)`.

**4. Deferred Provider-ları Lazımsız Yerdə İstifadə Etmək**
Hər request-də lazım olan service-lər üçün Deferred Provider yazmaq — deferred resolve mexanizmi əlavə overhead yaradır, kod mürəkkəbləşir. Deferred Provider yalnız nadir istifadə olunan, ağır initialize olan service-lər (PDF generator, Excel exporter) üçün uyğundur.

**5. `app()` Helper ilə Service Locator Pattern**
Controller ya da domain sinifləri içindən `app(SomeService::class)` çağırmaq — gizli asılılıqlar yaranır, class-ın nəyə ehtiyac duyduğu constructor-dan anlaşılmır, test etmək çətinləşir. Həmişə constructor injection istifadə edin; `app()` yalnız ServiceProvider daxilindəki bootstrap kodunda istifadə olunmalıdır.

**6. `register()` Metodunda `boot()` Əməliyyatları Etmək**
`register()` daxilində event listener qoşmaq, route əlavə etmək — bu mərhələdə bütün provider-lar hələ register olmayıb, asılılıqlar mövcud olmaya bilər, exception yaranır. `register()` yalnız Container binding üçündür; hər başqa iş `boot()` metoduna aiddir.
