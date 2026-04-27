# Factory and Abstract Factory (Middle ⭐⭐)

## İcmal
Factory pattern — object creation məntiqini client code-dan ayıran creational design pattern-dir. Birbaşa `new ClassName()` əvəzinə factory object yaratmanı idarə edir. Üç variant var: Simple Factory (texniki olaraq pattern sayılmır, amma çox istifadə olunur), Factory Method, Abstract Factory. Backend layihələrində driver-based sistemlər (payment, notification, storage, cache), test fixture yaratma, DI container-lər — hamısı factory konsepsinə söykənir.

## Niyə Vacibdir
Factory pattern-i bilmək OOP dizayn anlayışının əsasıdır. İnterviewer bu sualı verərkən yoxlayır: object creation mürəkkəbləşdikdə nə edirsiniz? OCP ilə necə birləşdirilir? Laravel-in `Storage::disk()`, `Mail::mailer()`, `Cache::driver()` — bunların hamısı factory pattern-dir. Bu kodu istifadə edirsiniz, daxilini anlamaq isə daha effektiv implementasiya imkanı verir.

---

## Əsas Anlayışlar

**Simple Factory:**
- Texniki olaraq pattern deyil, amma çox istifadə olunur
- Static ya da instance method ilə object yaradır; centralized creation logic
- Dezavantaj: OCP-ni pozur — yeni type əlavə etmək üçün factory-ni dəyişmək lazımdır
- Named Constructor Pattern — PHP-də populyar static factory method variant: `Money::ofUSD(1999)`

**Factory Method:**
- Parent class "object yarat" metodunu abstract/virtual kimi təyin edir; child class-lar hansı type-ın yaradılacağını qərar verir
- "Subclassing to vary object creation" — struktural yox, behavioral dəyişiklik
- OCP-ni dəstəkləyir — yeni type üçün yeni subclass; mövcud kodu dəyişmədən
- Template Method pattern ilə sıx əlaqəli — Template Method algorithm addımlarını, Factory Method object creation-ı abstract edir

**Abstract Factory:**
- İlgili object family-sini yaratmaq üçün interface — bir "family" içindəki bütün object-lər consistent olmalıdır
- GUI toolkit nümunəsi: `WindowsFactory` (WindowsButton, WindowsCheckbox), `MacFactory` (MacButton, MacCheckbox)
- Laravel-də: Test double-lar üçün `InMemoryFactory`, production üçün `EloquentFactory`
- Factory Method-dan fərq: bir object yaratmaq (FM) vs family of related objects (AF)

**Məsuliyyət ayrımı:**
- **Client:** Factory-ni istifadə edir, konkret type-ı bilmir; interface-ə depend edir
- **Factory:** Konkret object-i yaradır; creation logic buradadır
- **Product:** Yaradılan object-in interface-i; client yalnız bunu bilir

**Factory vs Constructor:**
- Constructor: Həmişə eyni type qaytarır; ad olmur (`new X()`); subclass qaytara bilmir
- Factory: Müxtəlif type qaytara bilər; mənalı adı var (`createFromRequest`, `makeWithDefaults`); lazy initialization, caching, pooling əlavə edilə bilər

**Static Factory Method vs Factory Class:**
- Static: Sadə, az boilerplate; test-də mock edilə bilmir; inheritance çətin
- Factory Class: Dependency inject edilə bilər; test-lərdə mock oluna bilər; daha SOLID

**Laravel-də factory pattern-lər:**
- `Cache::driver('redis')` — CacheManager (Abstract Factory)
- `Storage::disk('s3')` — FilesystemManager (Abstract Factory)
- `Log::channel('slack')` — LogManager
- `Mail::mailer('smtp')` — MailManager
- Model Factories (Eloquent) — test data yaratma; `User::factory()->create()`
- `DB::connection('mysql')` — DatabaseManager

**UML Strukturu:**
- Simple Factory: `Client → Factory.create(type) → Product`
- Factory Method: `Client → Creator.createProduct() [abstract]` → `ConcreteCreator.createProduct() → ConcreteProduct`
- Abstract Factory: `Client → AbstractFactory` interface; `ConcreteFactory1`, `ConcreteFactory2` implement edir

---

## Praktik Baxış

**Interview-da yanaşma:**
- Factory Method ilə Abstract Factory-nin fərqini aydın izah edin — çox tez-tez qarışdırılır
- Factory Method: bir tip object üçün subclass-da dəyişiklik; Abstract Factory: family of related objects
- Laravel-in driver sistemini nümunə çəkin — real-world istifadə nümunəsi

**Follow-up suallar:**
1. "Factory ilə Service Locator fərqi nədir?" — Service Locator anti-pattern sayılır (hidden dependencies, global state); Factory explicit dependency-dir
2. "Laravel-in service container factory ilə necə əlaqəlidir?" — Container `bind()` + `make()` factory pattern-in DI ilə birləşməsidir
3. "Test zamanı factory-ni necə istifadə edirsiniz?" — Fake/In-memory implementation inject etmək üçün factory interface-indən istifadə; `InMemoryNotificationFactory`
4. "Abstract Factory nə zaman Factory Method-dan üstündür?" — Eyni "family" içindəki object-lərin consistent olması lazım olduqda
5. "Named constructor nə vaxt factory class-dan üstündür?" — Dependency yoxdursa, sadə value object yaratmada; `Money::ofUSD()`, `Email::from()`
6. "Factory pattern-in dezavantajları?" — Əlavə abstraction qatı; debugging çətin; yeni başlayanlar üçün kod oxumaq çətin

**Real framework implementasiyaları:**
- **Laravel:** `Illuminate\Support\Manager` abstract class — bütün driver-based sistem bundan extend edir; `createRedisDriver()`, `createMemcachedDriver()` metodları
- **Spring:** `BeanFactory`, `ApplicationContext` — DI container özü factory pattern-dir
- **Doctrine:** `EntityManagerFactory` — connection pool ilə EntityManager yaratma
- **Symfony:** `ContainerBuilder` — service definitions-ı factory kimi işləyir

**Anti-patterns:**
- Simple Factory-ni Factory Method kimi tanıtmaq — fərqi bilin
- Over-factory: Hər class üçün factory yaratmaq — əksər hallarda constructor kifayətdir
- Factory-dən business logic ayrılmayıb — factory yalnız object yaratmalıdır, business rule yox
- `instanceof` check-ləri factory daxilindən — type-safety itirilir

---

## Nümunələr

### Tipik Interview Sualı
"You have a notification system that supports email, SMS, and push notifications. New channels may be added in the future. How would you design this using factory patterns?"

### Güclü Cavab
Bu use-case üçün Factory Method pattern — yeni channel əlavə etmək üçün mövcud kodu dəyişməmək — ideal həlldir. `NotificationChannel` interface-i `send()` metodunu təyin edir. `EmailChannel`, `SmsChannel`, `PushChannel` bu interface-i implement edir.

OCP-ə uyğun driver-based factory: `NotificationChannelFactory`-yə `extend()` metodu əlavə edirik — yeni channel yeni class + bir registration sətridir. Laravel-in `Cache::extend()` mexanizmi ilə eyni yanaşma.

Abstract Factory nə vaxt lazımdır: Hər channel-ın yalnız "sender" deyil, həm "formatter" həm "logger" kimi related object-lər family-si olduğunda — bütün family-ni konsistent saxlamaq üçün.

### Kod Nümunəsi

```php
// ── Simple Factory — centralized creation ──────────────────────
interface NotificationChannel
{
    public function send(string $message, string $recipient): void;
    public function name(): string;
}

class EmailChannel implements NotificationChannel
{
    public function __construct(private readonly Mailer $mailer) {}

    public function send(string $message, string $recipient): void
    {
        $this->mailer->to($recipient)->raw($message, 'Notification');
    }

    public function name(): string { return 'email'; }
}

class SmsChannel implements NotificationChannel
{
    public function __construct(private readonly SmsClient $client) {}

    public function send(string $message, string $recipient): void
    {
        $this->client->send($recipient, $message);
    }

    public function name(): string { return 'sms'; }
}

// Simple Factory — OCP-ni pozur (yeni channel → bu faylı dəyiş)
class SimpleNotificationFactory
{
    public function __construct(
        private readonly Mailer    $mailer,
        private readonly SmsClient $smsClient,
    ) {}

    public function make(string $channel): NotificationChannel
    {
        return match ($channel) {
            'email' => new EmailChannel($this->mailer),
            'sms'   => new SmsChannel($this->smsClient),
            default => throw new \InvalidArgumentException("Unknown channel: {$channel}"),
        };
    }
}
```

```php
// ── Driver-based Factory — OCP-ə uyğun (Laravel-vari) ───────────
class ExtensibleChannelFactory
{
    /** @var array<string, \Closure> */
    private array $drivers = [];

    // Yeni driver qeydiyyatı — mövcud kodu dəyişmədən
    public function extend(string $name, \Closure $resolver): void
    {
        $this->drivers[$name] = $resolver;
    }

    public function make(string $channel): NotificationChannel
    {
        if (!isset($this->drivers[$channel])) {
            throw new \InvalidArgumentException("Driver [{$channel}] not registered.");
        }

        return ($this->drivers[$channel])();
    }

    public function has(string $channel): bool
    {
        return isset($this->drivers[$channel]);
    }
}

// ServiceProvider-da — framework-i dəyişmədən yeni driver
class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExtensibleChannelFactory::class, function ($app) {
            $factory = new ExtensibleChannelFactory();

            // Built-in driver-lər
            $factory->extend('email', fn() => new EmailChannel($app->make(Mailer::class)));
            $factory->extend('sms',   fn() => new SmsChannel($app->make(SmsClient::class)));
            $factory->extend('push',  fn() => new PushChannel($app->make(PushClient::class)));

            return $factory;
        });
    }
}

// Başqa package-dən yeni driver — bir sətir
$factory->extend('slack',    fn() => new SlackChannel(config('services.slack.token')));
$factory->extend('telegram', fn() => new TelegramChannel(config('services.telegram.bot_token')));
$factory->extend('discord',  fn() => new DiscordChannel(config('services.discord.webhook')));
```

```php
// ── Abstract Factory — Related Object Family ─────────────────────
// Hər notification channel-ının üç component-i var: sender, formatter, logger

interface ChannelSender    { public function send(string $msg, string $to): void; }
interface ChannelFormatter { public function format(string $raw): string; }
interface ChannelLogger    { public function log(string $event, array $data): void; }

// Abstract Factory — family interface-i
interface NotificationChannelFactory
{
    public function createSender(): ChannelSender;
    public function createFormatter(): ChannelFormatter;
    public function createLogger(): ChannelLogger;
}

// Bir family — Email
class EmailChannelFactory implements NotificationChannelFactory
{
    public function createSender(): ChannelSender
    {
        return new EmailSender(app(Mailer::class));
    }

    public function createFormatter(): ChannelFormatter
    {
        return new HtmlEmailFormatter(); // Email HTML format istifadə edir
    }

    public function createLogger(): ChannelLogger
    {
        return new EmailDeliveryLogger(); // Email delivery tracking
    }
}

// Başqa family — SMS
class SmsChannelFactory implements NotificationChannelFactory
{
    public function createSender(): ChannelSender
    {
        return new SmsSender(app(SmsClient::class));
    }

    public function createFormatter(): ChannelFormatter
    {
        return new PlainTextFormatter(maxLength: 160); // SMS 160 char limit
    }

    public function createLogger(): ChannelLogger
    {
        return new SmsDeliveryLogger(); // SMS delivery tracking
    }
}

// Client — factory-ni bilir, konkret class-ları bilmir
class NotificationOrchestrator
{
    public function send(
        NotificationChannelFactory $factory,
        string $rawMessage,
        string $recipient
    ): void {
        $sender    = $factory->createSender();
        $formatter = $factory->createFormatter();
        $logger    = $factory->createLogger();

        $formatted = $formatter->format($rawMessage);
        $sender->send($formatted, $recipient);
        $logger->log('sent', ['to' => $recipient, 'channel' => $factory::class]);
    }
}
```

```php
// ── Named Constructor Pattern (Static Factory Method) ────────────
final class Money
{
    private function __construct(
        private readonly int    $amount,   // Minor unit (cents)
        private readonly string $currency,
    ) {}

    // Mənalı adlarla object yaratma
    public static function ofUSD(int $cents): self
    {
        return new self($cents, 'USD');
    }

    public static function ofEUR(int $cents): self
    {
        return new self($cents, 'EUR');
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    // Fərqli constructor parametrləri — eyni class
    public static function fromFloat(float $amount, string $currency): self
    {
        return new self((int)round($amount * 100), $currency);
    }

    public static function fromString(string $value): self
    {
        if (!preg_match('/^(\d+(?:\.\d{1,2})?)\s([A-Z]{3})$/', $value, $m)) {
            throw new \InvalidArgumentException("Invalid format: {$value}");
        }
        return self::fromFloat((float)$m[1], $m[2]);
    }

    public function amount(): int    { return $this->amount; }
    public function currency(): string { return $this->currency; }
    public function format(): string { return number_format($this->amount / 100, 2) . ' ' . $this->currency; }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \RuntimeException('Currency mismatch');
        }
        return new self($this->amount + $other->amount, $this->currency);
    }
}

// İstifadə — `new Money(1999, 'USD')` deyil, mənalı adlar
$price    = Money::ofUSD(1999);           // $19.99
$fee      = Money::fromFloat(5.00, 'EUR');
$total    = $price->add(Money::ofUSD(100));
$parsed   = Money::fromString('29.99 USD');

echo $total->format(); // "20.99 USD"
```

### Real-World Nümunə

```php
// Laravel CacheManager — Abstract Factory pattern
// vendor/laravel/framework/src/Illuminate/Cache/CacheManager.php

// CacheManager = Context; hər driver = Concrete Strategy
// store() metodu = factory method

Cache::driver('redis')->put('key', 'value', 300);
// ↓ internally:
// CacheManager::store('redis') → createRedisDriver() → new RedisStore(...)

// Yeni driver əlavə etmək — CacheManager-i dəyişmədən:
Cache::extend('dynamodb', function ($app) {
    return Cache::repository(
        new DynamoDbStore(
            $app->make(DynamoDbClient::class),
            config('cache.stores.dynamodb.table')
        )
    );
});

// config/cache.php-da default dəyiş:
// 'default' => env('CACHE_DRIVER', 'dynamodb')

// Bu qədər — mövcud kod dəyişmdi, yeni driver işləyir (OCP!)
```

### Anti-Pattern Nümunəsi

```php
// ❌ Anti-pattern: Factory-də business logic
class OrderFactory
{
    public function create(array $data): Order
    {
        // Bu YANLIŞ — factory business logic bilməməlidir
        if ($data['total'] > 1000) {
            $data['discount'] = 0.1; // Business rule factory-dədir!
        }

        if (!$this->inventoryService->isAvailable($data['product_id'])) {
            throw new OutOfStockException(); // Domain exception factory-dən!
        }

        // Email göndərmə factory-dən — YANLIŞ
        Mail::to($data['customer_email'])->send(new OrderConfirmation());

        return Order::create($data);
    }
}

// ✅ Düzgün: Factory yalnız object yaradır
class OrderFactory
{
    public function create(array $validatedData): Order
    {
        // Yalnız object construction — business logic yoxdur
        return new Order(
            id:         Str::uuid(),
            customerId: $validatedData['customer_id'],
            productId:  $validatedData['product_id'],
            quantity:   $validatedData['quantity'],
            total:      Money::fromFloat($validatedData['total'], 'USD'),
            status:     OrderStatus::PENDING,
            createdAt:  now(),
        );
    }
}

// Business logic ayrı service-də
class PlaceOrderService
{
    public function __construct(
        private readonly OrderFactory        $factory,
        private readonly InventoryService    $inventory,
        private readonly OrderRepository     $repository,
        private readonly DiscountCalculator  $discount,
    ) {}

    public function place(PlaceOrderDTO $dto): Order
    {
        $this->inventory->ensureAvailable($dto->productId, $dto->quantity);

        $total = $this->discount->apply($dto->baseTotal, $dto->customerId);
        $order = $this->factory->create([...$dto->toArray(), 'total' => $total]);

        $this->repository->save($order);
        OrderPlaced::dispatch($order); // Email listener-dən göndərilər

        return $order;
    }
}
```

---

## Praktik Tapşırıqlar

1. Laravel-in `Cache::driver()` və `Storage::disk()` source kodunu oxuyun — `Illuminate\Support\Manager` class-ını araşdırın
2. Payment provider factory qurun: Stripe, PayPal, Braintree — `extend()` ilə yeni provider tək sətir qeydiyyat olsun
3. Named constructor (`fromArray`, `fromRequest`, `withDefaults`) ilə Value Object yaradın
4. Abstract Factory vs Factory Method fərqini öz sözlərinizlə izah edən test kodu yazın
5. Eloquent Model Factory-lərini araşdırın: `User::factory()->has(Order::factory()->count(5))->create()` mexanizmi
6. `SimpleFactory`-ni `ExtensibleFactory` (driver-based)-ə refactor edin; OCP testini yazın: yeni type əlavə etmək mövcud test-ləri pozmur
7. Test environment üçün `InMemoryPaymentGateway` implement edin, production-da `StripeGateway` istifadə olunur
8. `Closure`-based factory vs class-based factory müqayisə edin: type safety, testability, refactoring trade-off-ları

## Əlaqəli Mövzular
- [SOLID Principles](01-solid-principles.md) — Factory OCP tətbiqi
- [Strategy Pattern](05-strategy-pattern.md) — Factory + Strategy birlikdə
- [Singleton Pattern](03-singleton-pattern.md) — Factory tərəfindən idarə olunan singleton
- [Observer/Event Pattern](04-observer-event.md) — Event listener factory
