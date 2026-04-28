# Design Patterns (Ümumi Baxış + Laravel Nümunələri) (Middle)

## İcmal

Design Pattern — proqram təminatında tez-tez qarşılaşan problemlərə sübut edilmiş, təkrar istifadə oluna bilən həll şablonlarıdır. Kod deyil, ideyadır — kontekstə uyğun implementasiya dəyişir. Gang of Four (GoF) kitabında 23 klassik pattern 3 kateqoriyaya bölünür: Creational (yaratma), Structural (quruluş), Behavioral (davranış).

## Niyə Vacibdir

Pattern-lər ortaq dil yaradır: "Bu Facade-dir" demək, 5 dəqiqəlik izahatın əvəzini tutur. Sınaqdan keçmiş həllər olduğuna görə, oxşar problemləri sıfırdan düşünmək vaxtını azaldır. Laravel framework-ün özü onlarca pattern istifadə edir — bunu bilmək framework-i daha dərindən anlamağa kömək edir.

## Əsas Anlayışlar

- **Creational Patterns**: object yaratma mexanizmlərini abstrakt edir (Singleton, Factory, Builder, Prototype, Abstract Factory)
- **Structural Patterns**: class-ları və object-ləri daha böyük strukturlara birləşdirir (Adapter, Bridge, Composite, Decorator, Facade, Proxy, Flyweight)
- **Behavioral Patterns**: object-lər arasındakı ünsiyyət və məsuliyyət bölgüsünü idarə edir (Observer, Strategy, Chain of Responsibility, Command, Iterator, Mediator, State, Template Method, Visitor, Memento, Null Object)
- **GoF** (Gang of Four): Erich Gamma, Richard Helm, Ralph Johnson, John Vlissides — "Design Patterns" kitabının müəllifləri
- **Pattern vs Algorithm**: alqoritm addım-addım həllin özüdür; pattern isə ümumi dizayn şablonudur

## Praktik Baxış

- Pattern-lər nə zaman lazımdır: eyni problemi ikinci dəfə həll edəndə; dəyişiklik mümkün olmayan interface ilə işləyəndə; davranışı runtime-da dəyişdirməli olduqda
- Trade-off: pattern gətirdiyiniz mürəkkəblik real problemi həll etməlidir; əks halda yükdür
- Hansı hallarda istifadə etməmək lazımdır: cəmi bir implementasiya olacaqsa Factory lazım deyil; heç vaxt dəyişməyəcəksə Strategy pattern boş yerə interface yaradır
- Common mistakes: pattern adını öyrənib hər yerdə tətbiq etmək; pattern olmadan həll asan olduğu halda məcbur etmək

### Anti-Pattern Nə Zaman Olur?

**Pattern obsession** — hər problemi mütləq bir pattern ilə həll etmək istəyi. Əlamətlər: sadə bir metod üçün 4 class, interface, factory; komanda "bu nə pattern-dir?" sualına cavab verə bilmir; pattern mürəkkəbliyi əslindəki iş mürəkkəbliyini aşıb. Qayda: pattern problemi həll etməlidir, pattern üçün problem axtarmamalısınız.

---

# Creational Patterns

Creational pattern-lər object yaratma mexanizmləri ilə məşğul olur. Object-ləri necə yaratmağı abstrakt edir, sistemin hansı concrete class istifadə etdiyindən asılı olmamağa kömək edir.

---

## Singleton Pattern

**Problem:** Bir class-ın yalnız bir instance-ının olmasını və bu instance-a global daxil olmağı təmin etmək.

**Həll:** Private constructor, static method ilə instance-ı idarə etmək.

```php
<?php

// === Classic Singleton (Pure PHP) ===

class DatabaseConnection
{
    private static ?self $instance = null;
    private \PDO $pdo;

    // Constructor private — xaricdən new edilə bilməz
    private function __construct()
    {
        $this->pdo = new \PDO(
            'mysql:host=localhost;dbname=myapp',
            'root',
            'secret'
        );
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new \RuntimeException("Cannot unserialize singleton");
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function query(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll();
    }
}

// === Laravel-də Singleton ===

// Service Container ilə singleton binding:
$this->app->singleton(DatabaseConnection::class, function ($app) {
    return new DatabaseConnection(config('database.default'));
});

// Laravel-in özündə Singleton istifadəsi:
// - DB::class (DatabaseManager) — singleton
// - config() — Repository singleton
// - app() — Application singleton
// - Cache::class — CacheManager singleton
```

---

## Factory Method Pattern

**Problem:** Object yaratma məntiqini subclass-lara həvalə etmək.

**Həll:** Yaratma üçün method müəyyən etmək, concrete class-lar bu method-u override edir.

```php
<?php

// === Classic Factory Method ===

abstract class Notification
{
    abstract protected function createTransport(): TransportInterface;

    public function send(string $message): bool
    {
        $transport = $this->createTransport(); // Factory Method
        return $transport->deliver($message);
    }
}

interface TransportInterface
{
    public function deliver(string $message): bool;
}

class EmailNotification extends Notification
{
    protected function createTransport(): TransportInterface
    {
        return new EmailTransport();
    }
}

class SMSNotification extends Notification
{
    protected function createTransport(): TransportInterface
    {
        return new SMSTransport();
    }
}

// === Laravel-də Factory Method ===

// Eloquent Factories
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'  => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    // State method — factory method variant
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => ['role' => 'admin']);
    }
}

// Laravel CacheManager — factory method pattern
class CacheManager
{
    protected function resolve(string $name): Repository
    {
        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';
        return $this->{$driverMethod}($config); // factory method dispatch
    }

    protected function createRedisDriver(array $config): Repository
    {
        return $this->repository(new RedisStore(/*...*/));
    }
}
```

---

## Abstract Factory Pattern

**Problem:** Əlaqəli object ailəsini yaratmaq üçün interface təmin etmək.

**Həll:** Əlaqəli məhsullar yaradacaq factory interface müəyyən etmək.

```php
<?php

interface UIFactoryInterface
{
    public function createButton(string $label): ButtonInterface;
    public function createInput(string $name, string $type): InputInterface;
}

class BootstrapUIFactory implements UIFactoryInterface
{
    public function createButton(string $label): ButtonInterface
    {
        return new BootstrapButton($label);
    }

    public function createInput(string $name, string $type = 'text'): InputInterface
    {
        return new BootstrapInput($name, $type);
    }
}

class TailwindUIFactory implements UIFactoryInterface
{
    public function createButton(string $label): ButtonInterface
    {
        return new TailwindButton($label);
    }

    public function createInput(string $name, string $type = 'text'): InputInterface
    {
        return new TailwindInput($name, $type);
    }
}

// Service Provider-dən binding — hansı UI framework olduğunu bilmədən
class UIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UIFactoryInterface::class, function () {
            return match (config('ui.framework', 'bootstrap')) {
                'bootstrap' => new BootstrapUIFactory(),
                'tailwind'  => new TailwindUIFactory(),
                default     => throw new \InvalidArgumentException("Unknown UI framework"),
            };
        });
    }
}

// FormBuilder — UIFactory konkret sinfini bilmir
class FormBuilder
{
    public function __construct(private readonly UIFactoryInterface $ui) {}

    public function buildLoginForm(): string
    {
        return implode("\n", [
            $this->ui->createInput('email', 'email')->render(),
            $this->ui->createInput('password', 'password')->render(),
            $this->ui->createButton('Daxil ol')->render(),
        ]);
    }
}
```

---

## Builder Pattern

**Problem:** Mürəkkəb object-ləri addım-addım qurmaq. Eyni quruluş prosesi fərqli nümunələr yarada bilsin.

**Həll:** Object qurmağı ayrı Builder class-a ayırmaq, fluent interface ilə addım-addım konfiqurasiya.

```php
<?php

// === Laravel Query Builder — ən məşhur Builder nümunəsi ===

$orders = Order::query()
    ->with(['user', 'items.product'])
    ->where('status', 'pending')
    ->where('total', '>', 100)
    ->orderByDesc('created_at')
    ->paginate(20);

// === Custom Report Builder ===

class ReportBuilder
{
    private string $title = '';
    private array  $columns = [];
    private array  $filters = [];
    private string $format = 'pdf';
    private bool   $includeCharts = false;

    public static function create(): self { return new self(); }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function columns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function addFilter(string $key, mixed $value): self
    {
        $this->filters[$key] = $value;
        return $this;
    }

    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function withCharts(): self
    {
        $this->includeCharts = true;
        return $this;
    }

    public function build(): Report
    {
        return new Report(
            title:         $this->title,
            columns:       $this->columns,
            filters:       $this->filters,
            format:        $this->format,
            includeCharts: $this->includeCharts,
        );
    }
}

$report = ReportBuilder::create()
    ->title('Aylıq Satış Hesabatı')
    ->columns(['product', 'quantity', 'revenue'])
    ->addFilter('status', 'completed')
    ->format('pdf')
    ->withCharts()
    ->build();

// Laravel Notification — Builder pattern
class OrderShippedNotification extends Notification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sifarişiniz göndərildi!')
            ->line('Sifariş #' . $this->order->id . ' göndərildi.')
            ->action('Sifarişi İzlə', url('/orders/' . $this->order->id));
    }
}
```

---

## Prototype Pattern

**Problem:** Mövcud object-in surətini (clone) yaratmaq, yeni object yaratmağın bahalı olduğu hallarda.

```php
<?php

class ReportTemplate
{
    private array $sections = [];

    public function __construct(
        private string $name,
        private array  $headers,
        private array  $styles
    ) {}

    public function addSection(string $title, array $data): void
    {
        $this->sections[] = ['title' => $title, 'data' => $data];
    }

    public function __clone()
    {
        // Deep copy — nested array-lar yeni kopyalanmalıdır
        $this->sections = array_map(fn($s) => [...$s], $this->sections);
    }
}

$template = new ReportTemplate('Aylıq Hesabat', ['Tarix', 'Məbləğ'], ['font' => 'Arial']);

$januaryReport = clone $template; // Prototype
$januaryReport->addSection('Yanvar', [/* ... */]);

// Laravel-də Eloquent model replicate — Prototype pattern
$original = Product::find(1);
$copy = $original->replicate(['id', 'created_at', 'updated_at']);
$copy->name = 'Yeni Məhsul (Kopya)';
$copy->save();
```

---

# Structural Patterns

Structural pattern-lər class-ları və object-ləri daha böyük strukturlara birləşdirmək ilə məşğul olur.

---

## Adapter Pattern

**Problem:** Uyğunsuz interface-ləri birlikdə işlətmək. Mövcud class-ın interface-ini başqa bir interface-ə çevirmək.

```php
<?php

// Stripe SDK — dəyişdirə bilmirik
class StripeSDK
{
    public function createCharge(array $params): object
    {
        return (object) ['id' => 'ch_' . uniqid(), 'status' => 'succeeded'];
    }
}

// PayPal SDK — dəyişdirə bilmirik
class PayPalSDK
{
    public function makePayment(float $total, string $currency): array
    {
        return ['payment_id' => 'PAY-' . uniqid(), 'state' => 'approved'];
    }
}

// Bizim interface — tətbiqin içindən istifadə edirik
interface PaymentProcessorInterface
{
    public function pay(float $amount, string $currency): PaymentResponse;
}

// Adapter-lər — xarici SDK-ları öz interface-imizə uyğunlaşdırır
class StripeAdapter implements PaymentProcessorInterface
{
    public function __construct(private readonly StripeSDK $stripe) {}

    public function pay(float $amount, string $currency): PaymentResponse
    {
        $result = $this->stripe->createCharge([
            'amount'   => (int) ($amount * 100), // Stripe sent ilə işləyir
            'currency' => strtolower($currency),
        ]);

        return new PaymentResponse(
            transactionId: $result->id,
            success:       $result->status === 'succeeded',
            amount:        $amount
        );
    }
}

class PayPalAdapter implements PaymentProcessorInterface
{
    public function __construct(private readonly PayPalSDK $paypal) {}

    public function pay(float $amount, string $currency): PaymentResponse
    {
        $result = $this->paypal->makePayment($amount, $currency);

        return new PaymentResponse(
            transactionId: $result['payment_id'],
            success:       $result['state'] === 'approved',
            amount:        $amount
        );
    }
}

// Laravel-də Adapter nümunələri:
// - Storage::disk('local'), Storage::disk('s3') — eyni interface, fərqli adapter
// - Cache::driver('redis'), Cache::driver('file') — eyni interface, fərqli adapter
```

---

## Decorator Pattern

**Problem:** Object-ə dinamik olaraq yeni davranışlar əlavə etmək, inheritance-a alternativ olaraq.

```php
<?php

// === Laravel Middleware — ən məşhur Decorator nümunəsi ===

// Hər middleware request/response-u "sarar" — Decorator pattern
class AuthenticationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect('/login'); // Chain qırılır
        }
        return $next($request); // Növbəti Decorator-a keçir
    }
}

class LoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;
        Log::info("Request: {$request->getUri()} [{$duration}s]");
        return $response;
    }
}

// === Cache Decorator — service-i dəyişmədən cache əlavə et ===

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
}

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id); // Hər dəfə DB sorğusu
    }
}

// Decorator — interface-i implement edir, original service-i wraps edir
class CachedUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $repository, // wraps edir
        private readonly CacheInterface          $cache
    ) {}

    public function findById(int $id): ?User
    {
        return $this->cache->remember("user:{$id}", 3600, function () use ($id) {
            return $this->repository->findById($id); // keşdə yoxdursa DB-yə keçir
        });
    }
}

// Service Provider-da Decorator chain
$this->app->bind(UserRepositoryInterface::class, function ($app) {
    return new CachedUserRepository(
        new EloquentUserRepository(),
        $app['cache']
    );
});
```

---

## Facade Pattern

**Problem:** Mürəkkəb subsystem-ə sadə interface təmin etmək.

```php
<?php

// Mürəkkəb subsystem-lər
class InventorySystem
{
    public function checkStock(int $productId): int { return 10; }
    public function reserveStock(int $productId, int $qty): bool { return true; }
}

class PaymentSystem
{
    public function authorize(float $amount, array $card): string { return 'auth_123'; }
    public function capture(string $authId): bool { return true; }
}

class ShippingSystem
{
    public function calculateRate(array $items, string $address): float { return 5.99; }
    public function createLabel(array $order): string { return 'LABEL-123'; }
}

// Facade — bütün subsystem-ləri sadə bir interface arxasında gizlədir
class OrderFacade
{
    public function __construct(
        private readonly InventorySystem $inventory,
        private readonly PaymentSystem   $payment,
        private readonly ShippingSystem  $shipping
    ) {}

    public function placeOrder(array $orderData): OrderResult
    {
        // 1. Stock yoxla
        foreach ($orderData['items'] as $item) {
            if ($this->inventory->checkStock($item['product_id']) < $item['quantity']) {
                throw new InsufficientStockException($item['product_id']);
            }
        }
        // 2. Stock reserve et, ödəniş al, shipping label yarat
        foreach ($orderData['items'] as $item) {
            $this->inventory->reserveStock($item['product_id'], $item['quantity']);
        }
        $shippingCost = $this->shipping->calculateRate($orderData['items'], $orderData['address']);
        $authId       = $this->payment->authorize($orderData['total'] + $shippingCost, $orderData['card']);
        $this->payment->capture($authId);
        $label        = $this->shipping->createLabel($orderData);

        return new OrderResult(success: true, shippingLabel: $label);
    }
}

// Laravel Facades — static proxy + Facade pattern birləşməsi
// Cache::get('key') → Service Container-dən CacheManager alınır → get() çağırılır
```

---

## Proxy Pattern

**Problem:** Başqa bir object üçün əvəzedici (surrogate) təmin etmək.

```php
<?php

// === Virtual Proxy — lazy loading ===

interface ImageInterface
{
    public function display(): string;
}

class RealImage implements ImageInterface
{
    private string $data;

    public function __construct(private readonly string $path)
    {
        $this->data = file_get_contents($path); // Ağır əməliyyat — constructor-da
    }

    public function display(): string { return $this->data; }
}

class LazyImageProxy implements ImageInterface
{
    private ?RealImage $realImage = null;

    public function __construct(private readonly string $path) {}

    public function display(): string
    {
        // İlk daxil olduqda yüklər — lazy
        $this->realImage ??= new RealImage($this->path);
        return $this->realImage->display();
    }
}

// Laravel-də lazy loading — Proxy pattern
$user  = User::find(1);
$posts = $user->posts; // İlk daxil olduqda SQL çalışır — virtual proxy

// === Cache Proxy ===

class CachedRepositoryProxy implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly CacheInterface          $cache
    ) {}

    public function findById(int $id): ?User
    {
        return $this->cache->remember("user:{$id}", 3600, fn() =>
            $this->repository->findById($id)
        );
    }
}
```

---

## Composite Pattern

**Problem:** Object-ləri ağac strukturunda (tree) təşkil etmək və tək object-lərlə qrupları eyni şəkildə idarə etmək.

```php
<?php

interface MenuComponentInterface
{
    public function render(int $depth = 0): string;
}

class MenuItem implements MenuComponentInterface
{
    public function __construct(
        private readonly string  $title,
        private readonly string  $url,
        private readonly ?string $icon = null
    ) {}

    public function render(int $depth = 0): string
    {
        $indent = str_repeat('  ', $depth);
        $icon   = $this->icon ? "<i class='{$this->icon}'></i> " : '';
        return "{$indent}<li><a href='{$this->url}'>{$icon}{$this->title}</a></li>\n";
    }
}

class MenuGroup implements MenuComponentInterface
{
    private array $children = [];

    public function __construct(private readonly string $title) {}

    public function add(MenuComponentInterface $item): self
    {
        $this->children[] = $item;
        return $this;
    }

    public function render(int $depth = 0): string
    {
        $indent = str_repeat('  ', $depth);
        $html   = "{$indent}<li>{$this->title}<ul>\n";
        foreach ($this->children as $child) {
            $html .= $child->render($depth + 1);
        }
        return $html . "{$indent}</ul></li>\n";
    }
}

$menu = new MenuGroup('Ana Menyu');
$menu->add(new MenuItem('Ana Səhifə', '/'));

$products = new MenuGroup('Məhsullar');
$products->add(new MenuItem('Bütün Məhsullar', '/products'));
$products->add(new MenuItem('Kateqoriyalar', '/categories'));

$menu->add($products);
echo $menu->render();
```

---

# Behavioral Patterns

Behavioral pattern-lər object-lər arasındakı ünsiyyət və məsuliyyət bölgüsü ilə məşğul olur.

---

## Observer Pattern

**Problem:** Object-in vəziyyəti dəyişdikdə asılı object-ləri avtomatik xəbərdar etmək.

```php
<?php

// === Laravel Events & Observers ===

class OrderCreated
{
    public function __construct(public readonly Order $order) {}
}

class SendOrderConfirmationEmail
{
    public function handle(OrderCreated $event): void
    {
        Mail::to($event->order->user->email)
            ->send(new OrderConfirmationMail($event->order));
    }
}

class UpdateInventory
{
    public function handle(OrderCreated $event): void
    {
        foreach ($event->order->items as $item) {
            $item->product->decrement('stock', $item->quantity);
        }
    }
}

// EventServiceProvider-dən qeydiyyat
protected $listen = [
    OrderCreated::class => [
        SendOrderConfirmationEmail::class,
        UpdateInventory::class,
        NotifyAdmin::class,
    ],
];

// Event fire etmə
OrderCreated::dispatch($order);

// === Eloquent Model Observer ===

class OrderObserver
{
    public function creating(Order $order): void
    {
        $order->order_number = 'ORD-' . date('Ymd') . '-' . uniqid();
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status === 'shipped') {
            event(new OrderShipped($order));
        }
    }
}

Order::observe(OrderObserver::class);
```

---

## Strategy Pattern

**Problem:** Alqoritm ailəsini müəyyən etmək, hər birini kapsullaşdırmaq və dəyişdirilə bilən etmək.

```php
<?php

interface PricingStrategyInterface
{
    public function calculate(float $basePrice, array $context = []): float;
}

class RegularPricing implements PricingStrategyInterface
{
    public function calculate(float $basePrice, array $context = []): float
    {
        return $basePrice;
    }
}

class PremiumMemberPricing implements PricingStrategyInterface
{
    public function calculate(float $basePrice, array $context = []): float
    {
        return $basePrice * 0.90; // 10% endirim
    }
}

class WholesalePricing implements PricingStrategyInterface
{
    public function calculate(float $basePrice, array $context = []): float
    {
        $quantity = $context['quantity'] ?? 1;
        return match (true) {
            $quantity >= 100 => $basePrice * 0.70,
            $quantity >= 50  => $basePrice * 0.80,
            $quantity >= 10  => $basePrice * 0.90,
            default          => $basePrice,
        };
    }
}

class PriceCalculator
{
    public function __construct(private PricingStrategyInterface $strategy) {}

    public function setStrategy(PricingStrategyInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function getPrice(float $basePrice, array $context = []): float
    {
        return round($this->strategy->calculate($basePrice, $context), 2);
    }
}

// Laravel-də Strategy nümunələri:
// - Cache::driver('redis'), Cache::driver('file') — fərqli caching strategy
// - Mail::mailer('smtp'), Mail::mailer('ses') — fərqli mail strategy
// - Hash::make() → bcrypt/argon — hashing strategy
```

---

## Command Pattern

**Problem:** Request-i bir object-ə çevirmək ki, onu queue-ya əlavə etmək, log etmək və ya geri almaq (undo) mümkün olsun.

```php
<?php

// === Laravel Queued Jobs — Command pattern ===

class ProcessOrder implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(private readonly Order $order) {}

    public function handle(
        PaymentService   $paymentService,
        InventoryService $inventoryService
    ): void {
        $paymentService->processPayment($this->order);
        $inventoryService->decrementStock($this->order->items);
        $this->order->user->notify(new OrderProcessedNotification($this->order));
    }

    public function failed(\Throwable $exception): void
    {
        $this->order->update(['status' => 'failed']);
        Log::error("Order processing failed: {$this->order->id}");
    }
}

// Queue-ya əlavə et
ProcessOrder::dispatch($order)->onQueue('orders');

// Job chain — bir neçə command ardıcıl
Bus::chain([
    new ProcessPayment($order),
    new UpdateInventory($order),
    new SendConfirmation($order),
])->onQueue('orders')->dispatch();
```

---

## State Pattern

**Problem:** Object-in davranışını daxili vəziyyətinə görə dəyişdirmək.

```php
<?php

interface OrderStateInterface
{
    public function proceed(Order $order): void;
    public function cancel(Order $order): void;
    public function getStatus(): string;
}

class PendingState implements OrderStateInterface
{
    public function proceed(Order $order): void
    {
        $order->setState(new ProcessingState());
    }

    public function cancel(Order $order): void
    {
        $order->setState(new CancelledState());
    }

    public function getStatus(): string { return 'pending'; }
}

class ShippedState implements OrderStateInterface
{
    public function proceed(Order $order): void
    {
        $order->setState(new DeliveredState());
    }

    public function cancel(Order $order): void
    {
        // Göndərilmiş sifarişi ləğv etmək mümkün deyil
        throw new \RuntimeException('Göndərilmiş sifarişi ləğv etmək mümkün deyil');
    }

    public function getStatus(): string { return 'shipped'; }
}

class Order
{
    private OrderStateInterface $state;

    public function __construct()
    {
        $this->state = new PendingState();
    }

    public function setState(OrderStateInterface $state): void
    {
        $this->state = $state;
    }

    public function proceed(): void { $this->state->proceed($this); }
    public function cancel(): void  { $this->state->cancel($this); }
    public function getStatus(): string { return $this->state->getStatus(); }
}
```

---

## Template Method Pattern

**Problem:** Alqoritmin skeletini müəyyən etmək, bəzi addımları subclass-lara həvalə etmək.

```php
<?php

abstract class DataImporter
{
    // Template Method — alqoritmin skeleti; final olduğuna görə override edilmir
    final public function import(string $source): ImportResult
    {
        $rawData       = $this->readData($source);
        $validData     = $this->validateData($rawData);
        $transformed   = $this->transformData($validData);
        $count         = $this->saveData($transformed);
        $this->afterImport($count); // hook — override edilə bilər
        return new ImportResult($count, count($rawData) - count($validData));
    }

    abstract protected function readData(string $source): array;
    abstract protected function validateData(array $data): array;
    abstract protected function transformData(array $data): array;
    abstract protected function saveData(array $data): int;

    // Hook method — optional override
    protected function afterImport(int $count): void {}
}

class CsvUserImporter extends DataImporter
{
    protected function readData(string $source): array
    {
        $data = [];
        $handle = fopen($source, 'r');
        $headers = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = array_combine($headers, $row);
        }
        fclose($handle);
        return $data;
    }

    protected function validateData(array $data): array
    {
        return array_filter($data, fn($row) =>
            !empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)
        );
    }

    protected function transformData(array $data): array
    {
        return array_map(fn($row) => [
            'name'       => trim($row['name']),
            'email'      => strtolower(trim($row['email'])),
            'created_at' => now(),
        ], $data);
    }

    protected function saveData(array $data): int
    {
        User::insert($data);
        return count($data);
    }

    protected function afterImport(int $count): void
    {
        Cache::forget('users:count');
        event(new UsersImported($count));
    }
}
```

---

## Chain of Responsibility Pattern

**Problem:** Request-i bir neçə handler-dən keçirmək. Hər handler ya request-i emal edir, ya da növbəti handler-ə ötürür.

```php
<?php

// Laravel Middleware Pipeline — Chain of Responsibility pattern

class EnsureUserIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect('/login'); // Chain burada qırılır
        }
        return $next($request); // Növbəti handler-ə ötür
    }
}

class CheckUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()->hasRole($role)) {
            abort(403);
        }
        return $next($request);
    }
}
```

---

## Iterator Pattern

**Problem:** Collection-un daxili strukturunu açmadan elementlərə ardıcıl daxil olmaq.

```php
<?php

// Laravel Collections — Iterator pattern
$result = collect($users)
    ->filter(fn($u) => $u['city'] === 'Bakı')
    ->map(fn($u) => $u['name'])
    ->sort()
    ->values()
    ->all();

// LazyCollection — böyük data set-lər üçün memory efficient
User::where('active', true)
    ->lazy(200) // 200-lük chunk-larla
    ->each(function (User $user) {
        $user->sendNewsletter();
    });
```

---

## Null Object Pattern

**Problem:** Null yoxlamalarından qaçınmaq üçün "heç nə etməyən" object istifadə etmək.

```php
<?php

interface LoggerInterface
{
    public function log(string $message, string $level = 'info'): void;
}

class FileLogger implements LoggerInterface
{
    public function log(string $message, string $level = 'info'): void
    {
        file_put_contents('app.log', "[$level] $message\n", FILE_APPEND);
    }
}

// Null Object — heç nə etmir, amma interface-ə uyğundur
class NullLogger implements LoggerInterface
{
    public function log(string $message, string $level = 'info'): void
    {
        // Məqsədli olaraq heç nə etmir
    }
}

class OrderService
{
    // LoggerInterface tələb edir — null yoxlama lazım deyil
    public function __construct(private readonly LoggerInterface $logger) {}

    public function process(): void
    {
        $this->logger->log('Processing order...');
    }
}

// Production:
$service = new OrderService(new FileLogger());

// Test və ya logging lazım olmadıqda:
$service = new OrderService(new NullLogger());
```

---

## Praktik Tapşırıqlar

1. Laravel layihənizdə mövcud bir `Controller` götürün; hansi GoF pattern-lər artıq işləndiyini müəyyən edin (middleware → Decorator/Chain, Query Builder → Builder, Eloquent events → Observer)
2. Payment metodlarını Strategy pattern ilə implement edin: `PaymentStrategyInterface`, `StripeStrategy`, `PayPalStrategy`; Service Provider-da config-ə görə bind edin
3. `Cache::remember()` istifadə edərək bir Repository üçün Proxy Decorator yazın; test-də real cache olmadan istifadə edin
4. Pipeline pattern ilə sifariş prosesini modelləşdirin: `ValidateOrder → CalculateDiscount → ApplyTax → ReserveInventory` — hər stage ayrı class-da

## Əlaqəli Mövzular

- [SOLID Prinsipləri](02-solid-principles.md) — pattern-lərin düzgün tətbiqi SOLID-ə əsaslanır
- [GRASP Prinsipləri](03-grasp-principles.md) — məsuliyyətin doğru yerə verilməsi
- [Repository Pattern](../laravel/01-repository-pattern.md) — tez-tez Observer/Decorator ilə birlikdə istifadə olunur
- [Service Layer](../laravel/02-service-layer.md) — Facade pattern-in tətbiq sahəsi
- [Strategy](../behavioral/02-strategy.md) — bu folderdə daha dərin izah
- [Observer](../behavioral/01-observer.md) — bu folderdə daha dərin izah
