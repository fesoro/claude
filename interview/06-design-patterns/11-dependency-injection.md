# Dependency Injection (Senior ⭐⭐⭐)

## İcmal

Dependency Injection (DI) — bir class-ın özünün yaratmadığı, xaricdən alan asılılıqları (dependencies) idarə etmə texnikasıdır. "Don't call us, we'll call you" — Hollywood principle-in tərsidir. Class öz asılılığını `new` ilə yaratmır, constructor/method/property vasitəsilə alır. DI özü GoF design pattern deyil, lakin SOLID-in Dependency Inversion Principle-nin ən güclü tətbiqidir. Laravel-in Service Container-i tamamilə DI üzərindədir. Bu mövzu Senior interview-larında demək olar hər zaman çıxır — "IoC Container nədir?", "DI vs Service Locator fərqi?", "Circular dependency necə həll edilir?"

## Niyə Vacibdir

DI olmadan: `class UserService { private $db; public function __construct() { $this->db = new MySQLConnection(); } }` — service özü database yaradır. Test üçün real database lazımdır, başqa database tipi keçirmək mümkün deyil. DI ilə: `class UserService { public function __construct(private DatabaseInterface $db) {} }` — test-də fake database inject edilir, production-da real. Laravel-in Service Container — bu asılılıq qrafını avtomatik resolve edir. Interviewer DI mövzusunda yoxlayır: SOLID-i başa düşürsünüzmü, test isolation anlayışınız varmı, framework internals-ni bilirsinizmi?

## Əsas Anlayışlar

**DI üç forması:**
- **Constructor Injection**: Asılılıqlar constructor-da alınır. Ən çox tövsiyə olunan — class-ın tam işləmək üçün nəyə ehtiyacı olduğu aydın görünür
- **Setter Injection**: `setDependency()` metodu ilə. Optional asılılıqlar üçün istifadə olunur
- **Interface Injection**: Interface özü inject metodunu müəyyən edir. PHP-də nadir

**IoC (Inversion of Control):**
- Ənənəvi kod: Yüksək səviyyəli modul aşağı səviyyəlini çağırır (control flows down)
- IoC: Asılılıqlar xaricdən verilir, framework/container idarə edir (control inverted)
- DI — IoC-nin implementasiya üsullarından biridir (Service Locator da IoC-dir)

**DI Container (IoC Container):**
- Binding qeydiyyatı: `$container->bind(Interface::class, ConcreteClass::class)`
- Auto-wiring: Constructor type hint-lərinə görə asılılıqları avtomatik resolve edir
- Singleton-lər: `$container->singleton()` — eyni instance-i hər dəfə qaytarır
- Laravel-in `app()` helper, `resolve()` function, `$this->app->make()` — bunların hamısı Container-dən istifadə edir

**DI vs Service Locator:**
- **Service Locator**: Class öz asılılığını Container-dən özü alır — `$this->container->get(Logger::class)`
- **DI**: Asılılıq xaricdən verilir — class Container-i bilmir
- Service Locator hidden dependency yaradır — class signature-a baxanda hansı dependency lazım olduğu bəlli deyil
- Laravel Facades — Service Locator-un static interface formasıdır (buna görə test-lərdə `::shouldReceive()` lazımdır)

**Dependency Inversion Principle (DIP):**
- High-level module low-level module-a bağlı olmamalıdır — ikisi də abstraction-a bağlı olmalıdır
- `UserService` → `MySQLUserRepository` (yanlış — concrete-a bağlı)
- `UserService` → `UserRepositoryInterface` ← `EloquentUserRepository` (düzgün — abstraction-a bağlı)

**Circular dependency:**
- A → B → A — Container bunu resolve edə bilmir, infinite loop
- Həll: Interface extract edin, ya da dependency-ləri reorganizasiya edin
- Laravel: Circular dependency varsa `BindingResolutionException` atır

**Contextual binding:**
- Eyni interface-in fərqli class-lara bağlanması kontekstə görə:
- `$this->app->when(PhotoController::class)->needs(Filesystem::class)->give(fn() => Storage::disk('photos'))`
- Eyni interface, fərqli implementation, fərqli context

**Lazy injection:**
- Ağır dependency hər zaman lazım olmursa — `Closure` inject et: `function() { return $this->app->make(HeavyService::class); }`
- Laravel 11-də `Lazy` type: `public function __construct(private Lazy\HeavyService $service) {}`

**Singleton vs Transient vs Scoped:**
- **Singleton**: Container ömrü boyu bir instance — `->singleton()`
- **Transient**: Hər `make()` çağrısında yeni instance — `->bind()`
- **Scoped** (HTTP request-scoped): Request boyunca bir instance, yeni request — yeni instance (Laravel-də `->scoped()`)

**Auto-wiring limits:**
- Primitiv parametrlər (string, int, array) — container avtomatik resolve edə bilmir
- Həll: `$this->app->when(Service::class)->needs('$apiKey')->give(config('services.api_key'))`

## Praktik Baxış

**Interview-da yanaşma:**
DI-ı əvvəlcə problem üzərindən izah edin: "Constructor-da `new` istifadəsi test-ability-ni öldürür." Sonra DI ilə həlli göstərin. Laravel Container-in auto-wiring-ini, `bind()` vs `singleton()` fərqini, Facade vs DI trade-off-unu izah edin.

**"Nə vaxt Singleton binding istifadə edərdiniz?" sualına cavab:**
- Database connection pool — hər dəfə yeni connection israf
- Logger — state saxlamır, paylaşıla bilər
- Config reader — immutable, bir dəfə yüklənir
- Singleton istifadə etmərdiniz: Request-specific data saxlayan service-lər — cross-request contamination

**Anti-pattern-lər:**
- Constructor-da `new` ilə dependency yaratmaq — hardcoded dependency
- Service Locator kimi `app()` istifadə etmək service-in ortasında — hidden dependency
- Çox dependency inject etmək (5+) — single responsibility pozulur, class bölünməlidir
- Interface olmadan concrete class inject etmək — DIP pozulur
- Singleton-da mutable state saxlamaq — thread/request contamination

**Follow-up suallar:**
- "Laravel Facade-ləri DI-dan daha rahatdır. Niyə DI istifadə edəsiniz?" → Test isolation: `Cache::shouldReceive()` əvəzinə real mock inject etmək, IDE type-checking, explicit dependency graph
- "100 class-ın dependency-sini Container-ə əl ilə register etmək mümkündürmü?" → Auto-wiring: Type hint varsa avtomatik resolve olur. Yalnız interface→concrete binding-lər lazımdır
- "DI Container-i özünüz yazın desəm necə yazardınız?" → Reflection ilə constructor parametrlərini analiz et, type-ları resolve et, inject et

## Nümunələr

### Tipik Interview Sualı

"Your OrderService creates its dependencies with `new` inside the constructor. It's impossible to unit test without a real database and real email server. How would you refactor it using Dependency Injection?"

### Güclü Cavab

Problemin köküdür: `new EloquentOrderRepository()`, `new SmtpMailer()` — service özü dependency-lərini yaradır. Test üçün real infrastructure lazımdır.

Addım 1: Interface-lər çıxarın — `OrderRepositoryInterface`, `MailerInterface`.

Addım 2: Constructor injection-a keçin — service interface-ləri alır, concrete class-ları bilmir.

Addım 3: ServiceProvider-da binding — `OrderRepositoryInterface` → `EloquentOrderRepository`, `MailerInterface` → `SmtpMailer`.

Test-lərdə: `new OrderService(new InMemoryOrderRepository(), new FakeMailer())` — real infrastructure lazım deyil. Testlər saniyələrlə bitir.

### Kod Nümunəsi

```php
// ===== BEFORE: Kötü praktika =====
class OrderService
{
    private EloquentOrderRepository $orders;  // Concrete!
    private SmtpMailer $mailer;               // Concrete!
    private StripePayment $payment;           // Concrete!

    public function __construct()
    {
        // Service özü yaradır — hardcoded dependencies
        $this->orders  = new EloquentOrderRepository(DB::connection());
        $this->mailer  = new SmtpMailer(config('mail'));
        $this->payment = new StripePayment(config('stripe.key'));
    }
}

// ===== AFTER: DI ilə =====

// Interface-lər — abstraction layer
interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;
    public function save(Order $order): Order;
    public function findPendingByUser(int $userId): Collection;
}

interface MailerInterface
{
    public function send(Mailable $mail): void;
}

interface PaymentGatewayInterface
{
    public function charge(Money $amount, PaymentMethod $method): PaymentResult;
}

// Constructor Injection — dependencies xaricdən alınır
class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,   // Interface
        private readonly MailerInterface $mailer,            // Interface
        private readonly PaymentGatewayInterface $payment,  // Interface
        private readonly EventDispatcher $events,
    ) {}

    public function placeOrder(PlaceOrderDTO $dto): Order
    {
        $result = $this->payment->charge($dto->amount, $dto->paymentMethod);

        if ($result->isFailed()) {
            throw new PaymentFailedException($result->message());
        }

        $order = new Order([
            'user_id'        => $dto->userId,
            'items'          => $dto->items,
            'total'          => $dto->amount->amount(),
            'transaction_id' => $result->transactionId(),
            'status'         => 'placed',
        ]);

        $saved = $this->orders->save($order);
        $this->mailer->send(new OrderConfirmationMail($saved));
        $this->events->dispatch(new OrderPlaced($saved->id));

        return $saved;
    }
}

// Laravel Service Container — binding registration
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Interface → Concrete binding
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(MailerInterface::class, LaravelMailer::class);
        $this->app->bind(PaymentGatewayInterface::class, StripePaymentAdapter::class);

        // Singleton — bir instance kafi
        $this->app->singleton(EventDispatcher::class);

        // Contextual binding — fərqli controller-lara fərqli Filesystem
        $this->app->when(PhotoController::class)
            ->needs(FilesystemInterface::class)
            ->give(fn() => Storage::disk('photos'));

        $this->app->when(DocumentController::class)
            ->needs(FilesystemInterface::class)
            ->give(fn() => Storage::disk('documents'));

        // Primitive bind etmək
        $this->app->when(StripePaymentAdapter::class)
            ->needs('$apiKey')
            ->give(config('services.stripe.key'));
    }
}

// Auto-wiring — Container avtomatik resolve edir
// OrderService constructor-da: OrderRepositoryInterface, MailerInterface, etc.
// Container type hint-lərə görə avtomatik inject edir
// Əl ilə: $app->make(OrderService::class) — bütün dependencies avtomatik həll olunur
```

```php
// ===== UNIT TEST — real infrastructure yoxdur =====
class OrderServiceTest extends TestCase
{
    public function test_place_order_successfully(): void
    {
        // Fake implementations inject edilir — DB, SMTP, Stripe yoxdur
        $orders  = new InMemoryOrderRepository();
        $mailer  = $this->createMock(MailerInterface::class);
        $payment = new FakePaymentGateway(PaymentResult::success('txn_123'));
        $events  = new FakeEventDispatcher();

        $service = new OrderService($orders, $mailer, $payment, $events);

        // Mail göndərildi?
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(OrderConfirmationMail::class));

        $dto = new PlaceOrderDTO(
            userId:        1,
            items:         [['product_id' => 5, 'qty' => 2]],
            amount:        new Money(5000, 'usd'),
            paymentMethod: new FakePaymentMethod(),
        );

        $order = $service->placeOrder($dto);

        $this->assertEquals('placed', $order->status);
        $this->assertEquals('txn_123', $order->transaction_id);
        $this->assertTrue($events->wasDispatched(OrderPlaced::class));
        // Bütün bunlar real DB/Stripe/SMTP olmadan — millisaniyələrdə bitir
    }

    public function test_failed_payment_throws_exception(): void
    {
        $payment = new FakePaymentGateway(PaymentResult::failure('Card declined'));

        $service = new OrderService(
            new InMemoryOrderRepository(),
            $this->createMock(MailerInterface::class),
            $payment,
            new FakeEventDispatcher()
        );

        $this->expectException(PaymentFailedException::class);
        $service->placeOrder(new PlaceOrderDTO(/* ... */));
    }
}

// InMemory implementation — test üçün
class InMemoryOrderRepository implements OrderRepositoryInterface
{
    private array $orders = [];
    private int $nextId = 1;

    public function findById(int $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }

    public function save(Order $order): Order
    {
        if (!$order->id) {
            $order->id = $this->nextId++;
        }
        $this->orders[$order->id] = $order;
        return $order;
    }

    public function findPendingByUser(int $userId): Collection
    {
        return collect($this->orders)
            ->filter(fn($o) => $o->user_id === $userId && $o->status === 'pending')
            ->values();
    }
}
```

## Praktik Tapşırıqlar

- Mövcud class-da `new` istifadəsini tapıb DI-a refactor edin — hər asılılıq üçün interface çıxarın
- Laravel-in `php artisan make:provider` ilə ServiceProvider yazın — 3 interface-i concrete-a bind edin
- Contextual binding nümunəsi: Eyni `Logger` interface, fərqli class-lara fərqli log channel
- Mini DI Container yazın: `bind()`, `singleton()`, `make()` metodları — auto-wiring daxil
- Test izolasiyası: `OrderService` üçün 5 test yazın — database olmadan, real-ish scenarios

## Əlaqəli Mövzular

- [SOLID Principles](01-solid-principles.md) — DIP: DI-nin nəzəri əsası
- [Factory Patterns](02-factory-patterns.md) — Container Factory kimi işləyir
- [Repository Pattern](07-repository-pattern.md) — DI ilə inject edilən repository
- [Adapter and Facade](09-adapter-facade.md) — Adapter-lər DI container ilə bind edilir
- [Proxy Pattern](13-proxy-pattern.md) — Container lazy proxy yarada bilir
