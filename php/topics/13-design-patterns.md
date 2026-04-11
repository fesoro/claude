# Design Patterns (Ümumi Baxış + Laravel Nümunələri)

## Mündəricat
1. [Creational Patterns](#creational-patterns)
   - [Singleton](#singleton-pattern)
   - [Factory Method](#factory-method-pattern)
   - [Abstract Factory](#abstract-factory-pattern)
   - [Builder](#builder-pattern)
   - [Prototype](#prototype-pattern)
2. [Structural Patterns](#structural-patterns)
   - [Adapter](#adapter-pattern)
   - [Bridge](#bridge-pattern)
   - [Composite](#composite-pattern)
   - [Decorator](#decorator-pattern)
   - [Facade](#facade-pattern)
   - [Proxy](#proxy-pattern)
   - [Flyweight](#flyweight-pattern)
3. [Behavioral Patterns](#behavioral-patterns)
   - [Observer](#observer-pattern)
   - [Strategy](#strategy-pattern)
   - [Chain of Responsibility](#chain-of-responsibility-pattern)
   - [Command](#command-pattern)
   - [Iterator](#iterator-pattern)
   - [Mediator](#mediator-pattern)
   - [State](#state-pattern)
   - [Template Method](#template-method-pattern)
   - [Visitor](#visitor-pattern)
   - [Memento](#memento-pattern)
   - [Null Object](#null-object-pattern)
4. [İntervyu Sualları](#intervyu-sualları)

---

# Creational Patterns

Creational pattern-lər object yaratma mexanizmləri ilə məşğul olur. Onlar object-ləri necə yaratmağı abstrakt edir, sistemin hansı concrete class istifadə etdiyindən asılı olmamağa kömək edir.

---

## Singleton Pattern

**Problem:** Bir class-ın yalnız bir instance-ının olmasını və bu instance-a global daxil olmağı təmin etmək.

**Həll:** Private constructor, static method ilə instance-ı idarə etmək.

***Həll:** Private constructor, static method ilə instance-ı idarə etmə üçün kod nümunəsi:*
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

    // Clone edilə bilməz
    private function __clone() {}

    // Unserialize edilə bilməz
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

// İstifadə
$db = DatabaseConnection::getInstance();
$users = $db->query("SELECT * FROM users");

// === Laravel-də Singleton ===

// Service Container ilə singleton binding:
// AppServiceProvider.php
$this->app->singleton(DatabaseConnection::class, function ($app) {
    return new DatabaseConnection(config('database.default'));
});

// app(DatabaseConnection::class) hər yerdə eyni instance qaytaracaq

// Laravel-in özündə Singleton istifadəsi:
// - DB::class (DatabaseManager) — singleton
// - config() — Repository singleton
// - app() — Application singleton
// - Cache::class — CacheManager singleton
// - Log::class — LogManager singleton
```

**Laravel-dəki real nümunə:**

```php
<?php

// Illuminate\Database\DatabaseManager — Singleton olaraq register olunur
// vendor/laravel/framework/src/Illuminate/Database/DatabaseServiceProvider.php

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton — bütün request boyunca eyni DatabaseManager
        $this->app->singleton('db', function ($app) {
            return new DatabaseManager($app, $app['db.factory']);
        });

        // Connection factory
        $this->app->singleton('db.factory', function ($app) {
            return new ConnectionFactory($app);
        });
    }
}
```

---

## Factory Method Pattern

**Problem:** Object yaratma məntiqini subclass-lara həvalə etmək. Hansı class-ın instance-ının yaradılacağını subclass müəyyən edir.

**Həll:** Yaratma üçün method müəyyən etmək, concrete class-lar bu method-u override edir.

***Həll:** Yaratma üçün method müəyyən etmək, concrete class-lar bu met üçün kod nümunəsi:*
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

class EmailTransport implements TransportInterface
{
    public function deliver(string $message): bool
    {
        // SMTP ilə göndər
        echo "Email göndərildi: $message\n";
        return true;
    }
}

class SMSTransport implements TransportInterface
{
    public function deliver(string $message): bool
    {
        echo "SMS göndərildi: $message\n";
        return true;
    }
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

// İstifadə
$notification = new EmailNotification();
$notification->send("Sifariş təsdiqləndi");

$smsNotification = new SMSNotification();
$smsNotification->send("OTP kodunuz: 123456");
```

**Laravel-dəki real nümunələr:**

```php
<?php

// 1. Eloquent Factories
// database/factories/UserFactory.php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ];
    }

    // State method-lar (Factory Method variant)
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withOrders(int $count = 3): static
    {
        return $this->has(Order::factory()->count($count));
    }
}

// İstifadə
$user = User::factory()->create();
$admin = User::factory()->admin()->create();
$users = User::factory()->count(10)->withOrders(5)->create();

// 2. Manager Classes (Factory pattern)
// Laravel-in Manager class-ları factory method pattern istifadə edir

// Illuminate\Cache\CacheManager
class CacheManager
{
    protected array $stores = [];

    public function store(?string $name = null): Repository
    {
        $name = $name ?? $this->getDefaultDriver();

        return $this->stores[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): Repository
    {
        $config = $this->getConfig($name);

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    // Factory method-lar
    protected function createRedisDriver(array $config): Repository
    {
        return $this->repository(new RedisStore(/*...*/));
    }

    protected function createMemcachedDriver(array $config): Repository
    {
        return $this->repository(new MemcachedStore(/*...*/));
    }

    protected function createFileDriver(array $config): Repository
    {
        return $this->repository(new FileStore(/*...*/));
    }

    protected function createArrayDriver(array $config): Repository
    {
        return $this->repository(new ArrayStore());
    }
}
```

---

## Abstract Factory Pattern

**Problem:** Əlaqəli object ailəsini yaratmaq üçün interface təmin etmək.

**Həll:** Əlaqəli məhsullar yaradacaq factory interface müəyyən etmək.

***Həll:** Əlaqəli məhsullar yaradacaq factory interface müəyyən etmək üçün kod nümunəsi:*
```php
<?php

// UI component ailəsi — hər platform üçün fərqli görünüş

interface ButtonInterface
{
    public function render(): string;
}

interface InputInterface
{
    public function render(): string;
}

interface SelectInterface
{
    public function render(): string;
}

// Abstract Factory
interface UIFactoryInterface
{
    public function createButton(string $label): ButtonInterface;
    public function createInput(string $name, string $type): InputInterface;
    public function createSelect(string $name, array $options): SelectInterface;
}

// Bootstrap implementation
class BootstrapButton implements ButtonInterface
{
    public function __construct(private string $label) {}
    public function render(): string
    {
        return "<button class='btn btn-primary'>$this->label</button>";
    }
}

class BootstrapInput implements InputInterface
{
    public function __construct(private string $name, private string $type) {}
    public function render(): string
    {
        return "<input class='form-control' type='$this->type' name='$this->name'>";
    }
}

class BootstrapSelect implements SelectInterface
{
    public function __construct(private string $name, private array $options) {}
    public function render(): string
    {
        $opts = implode('', array_map(
            fn($v, $k) => "<option value='$k'>$v</option>",
            $this->options,
            array_keys($this->options)
        ));
        return "<select class='form-select' name='$this->name'>$opts</select>";
    }
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

    public function createSelect(string $name, array $options): SelectInterface
    {
        return new BootstrapSelect($name, $options);
    }
}

// Tailwind implementation
class TailwindButton implements ButtonInterface
{
    public function __construct(private string $label) {}
    public function render(): string
    {
        return "<button class='bg-blue-500 text-white px-4 py-2 rounded'>$this->label</button>";
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

    public function createSelect(string $name, array $options): SelectInterface
    {
        return new TailwindSelect($name, $options);
    }
}

// Service Provider-dən binding
class UIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UIFactoryInterface::class, function () {
            $framework = config('ui.framework', 'bootstrap');
            return match ($framework) {
                'bootstrap' => new BootstrapUIFactory(),
                'tailwind' => new TailwindUIFactory(),
                default => throw new \InvalidArgumentException("Unknown UI framework: $framework"),
            };
        });
    }
}

// İstifadə — hansı UI framework olduğunu bilmədən
class FormBuilder
{
    public function __construct(
        private readonly UIFactoryInterface $ui
    ) {}

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

**Problem:** Mürəkkəb object-ləri addım-addım qurmaq. Eyni quruluş prosesi fərqli təmsilçiliklər yarada bilməlidir.

**Həll:** Object qurmağı ayrı Builder class-a ayırmaq, fluent interface ilə addım-addım konfiqurasiya.

***Həll:** Object qurmağı ayrı Builder class-a ayırmaq, fluent interfac üçün kod nümunəsi:*
```php
<?php

// === Laravel Query Builder (Ən məşhur nümunə) ===

// Laravel-in Query Builder-i Builder pattern-in əla nümunəsidir
$users = DB::table('users')
    ->select('name', 'email')
    ->where('active', true)
    ->where('age', '>=', 18)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Eloquent Builder
$orders = Order::query()
    ->with(['user', 'items.product'])
    ->where('status', 'pending')
    ->where('total', '>', 100)
    ->whereBetween('created_at', [now()->subDays(7), now()])
    ->orderByDesc('created_at')
    ->paginate(20);

// === Custom Builder nümunəsi ===

class QueryFilter
{
    private Builder $query;

    public function __construct(private readonly string $model)
    {
        $this->query = $model::query();
    }

    public function whereLike(string $column, string $value): self
    {
        $this->query->where($column, 'LIKE', "%$value%");
        return $this;
    }

    public function whereDateRange(string $column, ?string $from, ?string $to): self
    {
        if ($from) {
            $this->query->where($column, '>=', $from);
        }
        if ($to) {
            $this->query->where($column, '<=', $to);
        }
        return $this;
    }

    public function withRelations(array $relations): self
    {
        $this->query->with($relations);
        return $this;
    }

    public function sortBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query->paginate($perPage);
    }

    public function get(): Collection
    {
        return $this->query->get();
    }
}

// === Notification Builder (Laravel) ===

// Laravel Notification sistemi Builder pattern istifadə edir
use Illuminate\Notifications\Messages\MailMessage;

class OrderShippedNotification extends Notification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)       // Builder başlanğıcı
            ->subject('Sifarişiniz göndərildi!')
            ->greeting('Salam ' . $notifiable->name)
            ->line('Sifarişiniz #' . $this->order->id . ' göndərildi.')
            ->action('Sifarişi İzlə', url('/orders/' . $this->order->id))
            ->line('Təşəkkür edirik!')
            ->salutation('Hörmətlə, ' . config('app.name'));
    }
}

// === Custom Report Builder ===

class ReportBuilder
{
    private string $title = '';
    private string $dateRange = '';
    private array $columns = [];
    private array $filters = [];
    private ?string $groupBy = null;
    private string $format = 'pdf';
    private bool $includeCharts = false;
    private ?string $footer = null;

    public static function create(): self
    {
        return new self();
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function dateRange(string $from, string $to): self
    {
        $this->dateRange = "$from - $to";
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

    public function groupBy(string $column): self
    {
        $this->groupBy = $column;
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

    public function footer(string $footer): self
    {
        $this->footer = $footer;
        return $this;
    }

    public function build(): Report
    {
        return new Report(
            title: $this->title,
            dateRange: $this->dateRange,
            columns: $this->columns,
            filters: $this->filters,
            groupBy: $this->groupBy,
            format: $this->format,
            includeCharts: $this->includeCharts,
            footer: $this->footer
        );
    }
}

// İstifadə
$report = ReportBuilder::create()
    ->title('Aylıq Satış Hesabatı')
    ->dateRange('2024-01-01', '2024-01-31')
    ->columns(['product', 'quantity', 'revenue'])
    ->addFilter('category', 'electronics')
    ->addFilter('status', 'completed')
    ->groupBy('product')
    ->format('pdf')
    ->withCharts()
    ->footer('Gizlidir — yalnız daxili istifadə üçün')
    ->build();
```

---

## Prototype Pattern

**Problem:** Mövcud object-in surətini (clone) yaratmaq, yeni object yaratmağın bahalı olduğu hallarda.

***Problem:** Mövcud object-in surətini (clone) yaratmaq, yeni object y üçün kod nümunəsi:*
```php
<?php

class ReportTemplate
{
    private array $sections = [];

    public function __construct(
        private string $name,
        private array $headers,
        private array $styles
    ) {}

    public function addSection(string $title, array $data): void
    {
        $this->sections[] = ['title' => $title, 'data' => $data];
    }

    public function __clone()
    {
        // Deep copy — nested array-lar yeni kopyalanmalıdır
        $this->sections = array_map(
            fn($section) => [...$section],
            $this->sections
        );
    }
}

// Template yaradılır (bahalı əməliyyat)
$template = new ReportTemplate(
    'Aylıq Hesabat',
    ['Tarix', 'Məbləğ', 'Status'],
    ['font' => 'Arial', 'size' => 12, 'color' => '#333']
);

// Clone ilə sürətli surət (Prototype)
$januaryReport = clone $template;
$januaryReport->addSection('Yanvar', [/* ... */]);

$februaryReport = clone $template;
$februaryReport->addSection('Fevral', [/* ... */]);

// Laravel-də Eloquent model replicate:
$originalProduct = Product::find(1);
$newProduct = $originalProduct->replicate(); // Prototype pattern!
$newProduct->name = 'Yeni Məhsul (Kopya)';
$newProduct->save();

// replicate() seçilmiş sahələri nəzərə almaz:
$newProduct = $originalProduct->replicate(['id', 'created_at', 'updated_at']);
```

---

# Structural Patterns

Structural pattern-lər class-ları və object-ləri daha böyük strukturlara birləşdirmək ilə məşğul olur.

---

## Adapter Pattern

**Problem:** Uyğunsuz interface-ləri birlikdə işlətmək. Mövcud class-ın interface-ini başqa bir interface-ə çevirmək.

***Problem:** Uyğunsuz interface-ləri birlikdə işlətmək. Mövcud class-ı üçün kod nümunəsi:*
```php
<?php

// === Problem: Fərqli ödəniş API-ləri fərqli interface-lərə malikdir ===

// Stripe SDK (xarici kitabxana — dəyişə bilmərik)
class StripeSDK
{
    public function createCharge(array $params): object
    {
        return (object) [
            'id' => 'ch_' . uniqid(),
            'amount' => $params['amount'],
            'status' => 'succeeded',
        ];
    }
}

// PayPal SDK (xarici kitabxana)
class PayPalSDK
{
    public function makePayment(float $total, string $currency): array
    {
        return [
            'payment_id' => 'PAY-' . uniqid(),
            'total' => $total,
            'state' => 'approved',
        ];
    }
}

// Bizim interface
interface PaymentProcessorInterface
{
    public function pay(float $amount, string $currency): PaymentResponse;
}

class PaymentResponse
{
    public function __construct(
        public readonly string $transactionId,
        public readonly bool $success,
        public readonly float $amount
    ) {}
}

// Adapter-lər — xarici SDK-ları öz interface-imizə uyğunlaşdırır
class StripeAdapter implements PaymentProcessorInterface
{
    public function __construct(
        private readonly StripeSDK $stripe
    ) {}

    public function pay(float $amount, string $currency): PaymentResponse
    {
        $result = $this->stripe->createCharge([
            'amount' => (int) ($amount * 100), // Stripe cent-lərlə işləyir
            'currency' => strtolower($currency),
        ]);

        return new PaymentResponse(
            transactionId: $result->id,
            success: $result->status === 'succeeded',
            amount: $amount
        );
    }
}

class PayPalAdapter implements PaymentProcessorInterface
{
    public function __construct(
        private readonly PayPalSDK $paypal
    ) {}

    public function pay(float $amount, string $currency): PaymentResponse
    {
        $result = $this->paypal->makePayment($amount, $currency);

        return new PaymentResponse(
            transactionId: $result['payment_id'],
            success: $result['state'] === 'approved',
            amount: $amount
        );
    }
}

// Laravel-dəki real nümunələr:
// - Filesystem adapters (Local, S3, FTP, etc.)
// - Cache drivers (Redis, Memcached, File, Database)
// - Session drivers
// - Queue connections (Redis, SQS, Database, Beanstalkd)

// Laravel Filesystem Adapter nümunəsi:
// Illuminate\Filesystem\FilesystemAdapter League\Flysystem-i Laravel interface-inə adapt edir

use Illuminate\Support\Facades\Storage;

// Hamısı eyni interface — adapter arxada fərqli driver işlədir
Storage::disk('local')->put('file.txt', 'content');
Storage::disk('s3')->put('file.txt', 'content');
Storage::disk('ftp')->put('file.txt', 'content');
```

---

## Bridge Pattern

**Problem:** Abstraction və implementation-ı ayırmaq ki, hər ikisi müstəqil olaraq dəyişə bilsin.

***Problem:** Abstraction və implementation-ı ayırmaq ki, hər ikisi müs üçün kod nümunəsi:*
```php
<?php

// === Notification sistemi — Bridge Pattern ===

// Implementation interface (bridge-in bir tərəfi)
interface MessageSenderInterface
{
    public function send(string $to, string $subject, string $body): bool;
}

class EmailSender implements MessageSenderInterface
{
    public function send(string $to, string $subject, string $body): bool
    {
        echo "Email to $to: [$subject] $body\n";
        return true;
    }
}

class SMSSender implements MessageSenderInterface
{
    public function send(string $to, string $subject, string $body): bool
    {
        echo "SMS to $to: $body\n";
        return true;
    }
}

class SlackSender implements MessageSenderInterface
{
    public function send(string $to, string $subject, string $body): bool
    {
        echo "Slack to #$to: [$subject] $body\n";
        return true;
    }
}

// Abstraction (bridge-in digər tərəfi)
abstract class BaseNotification
{
    public function __construct(
        protected MessageSenderInterface $sender // Bridge
    ) {}

    abstract public function notify(string $recipient): void;
}

class OrderNotification extends BaseNotification
{
    public function __construct(
        MessageSenderInterface $sender,
        private readonly Order $order
    ) {
        parent::__construct($sender);
    }

    public function notify(string $recipient): void
    {
        $this->sender->send(
            $recipient,
            'Yeni Sifariş',
            "Sifariş #{$this->order->id} yaradıldı. Məbləğ: {$this->order->total} AZN"
        );
    }
}

class AlertNotification extends BaseNotification
{
    public function __construct(
        MessageSenderInterface $sender,
        private readonly string $alertMessage,
        private readonly string $severity
    ) {
        parent::__construct($sender);
    }

    public function notify(string $recipient): void
    {
        $this->sender->send(
            $recipient,
            "[{$this->severity}] Alert",
            $this->alertMessage
        );
    }
}

// İstifadə — istənilən notification + istənilən sender kombinasiyası
$emailOrder = new OrderNotification(new EmailSender(), $order);
$emailOrder->notify('admin@site.com');

$smsAlert = new AlertNotification(new SMSSender(), 'Server CPU 95%', 'CRITICAL');
$smsAlert->notify('+994501234567');

$slackOrder = new OrderNotification(new SlackSender(), $order);
$slackOrder->notify('orders');
```

---

## Composite Pattern

**Problem:** Object-ləri ağac strukturunda (tree) təşkil etmək və tək object-lərlə qrupları eyni şəkildə idarə etmək.

***Problem:** Object-ləri ağac strukturunda (tree) təşkil etmək və tək  üçün kod nümunəsi:*
```php
<?php

// === Menyu sistemi ===

interface MenuComponentInterface
{
    public function render(int $depth = 0): string;
    public function getUrl(): ?string;
}

class MenuItem implements MenuComponentInterface
{
    public function __construct(
        private readonly string $title,
        private readonly string $url,
        private readonly ?string $icon = null
    ) {}

    public function render(int $depth = 0): string
    {
        $indent = str_repeat('  ', $depth);
        $icon = $this->icon ? "<i class='{$this->icon}'></i> " : '';
        return "{$indent}<li><a href='{$this->url}'>{$icon}{$this->title}</a></li>\n";
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}

class MenuGroup implements MenuComponentInterface
{
    private array $children = [];

    public function __construct(
        private readonly string $title,
        private readonly ?string $icon = null
    ) {}

    public function add(MenuComponentInterface $item): self
    {
        $this->children[] = $item;
        return $this;
    }

    public function render(int $depth = 0): string
    {
        $indent = str_repeat('  ', $depth);
        $icon = $this->icon ? "<i class='{$this->icon}'></i> " : '';
        $html = "{$indent}<li>{$icon}{$this->title}\n";
        $html .= "{$indent}  <ul>\n";
        foreach ($this->children as $child) {
            $html .= $child->render($depth + 2);
        }
        $html .= "{$indent}  </ul>\n";
        $html .= "{$indent}</li>\n";
        return $html;
    }

    public function getUrl(): ?string
    {
        return null;
    }
}

// İstifadə
$menu = new MenuGroup('Ana Menyu');
$menu->add(new MenuItem('Ana Səhifə', '/', 'fa-home'));

$products = new MenuGroup('Məhsullar', 'fa-box');
$products->add(new MenuItem('Bütün Məhsullar', '/products'));
$products->add(new MenuItem('Kateqoriyalar', '/categories'));

$electronics = new MenuGroup('Elektronika');
$electronics->add(new MenuItem('Telefonlar', '/products/phones'));
$electronics->add(new MenuItem('Laptoplar', '/products/laptops'));
$products->add($electronics);

$menu->add($products);
$menu->add(new MenuItem('Əlaqə', '/contact', 'fa-envelope'));

echo $menu->render();

// Laravel-də Composite: Validation Rules, Permission trees
```

---

## Decorator Pattern

**Problem:** Object-ə dinamik olaraq yeni davranışlar əlavə etmək, inheritance-a alternativ olaraq.

***Problem:** Object-ə dinamik olaraq yeni davranışlar əlavə etmək, inh üçün kod nümunəsi:*
```php
<?php

// === Laravel Middleware — Ən məşhur Decorator nümunəsi ===

interface HandlerInterface
{
    public function handle(Request $request): Response;
}

class CoreHandler implements HandlerInterface
{
    public function handle(Request $request): Response
    {
        // Controller logic
        return new Response('OK', 200);
    }
}

// Middleware-lar Decorator pattern-dir
abstract class Middleware implements HandlerInterface
{
    public function __construct(
        protected HandlerInterface $next
    ) {}
}

class AuthenticationMiddleware extends Middleware
{
    public function handle(Request $request): Response
    {
        if (!$request->hasHeader('Authorization')) {
            return new Response('Unauthorized', 401);
        }
        return $this->next->handle($request);
    }
}

class LoggingMiddleware extends Middleware
{
    public function handle(Request $request): Response
    {
        $start = microtime(true);
        echo "Request started: {$request->getUri()}\n";

        $response = $this->next->handle($request);

        $duration = microtime(true) - $start;
        echo "Request completed in {$duration}s\n";

        return $response;
    }
}

class ThrottleMiddleware extends Middleware
{
    public function handle(Request $request): Response
    {
        // Rate limit yoxlaması
        if ($this->isRateLimited($request)) {
            return new Response('Too Many Requests', 429);
        }
        return $this->next->handle($request);
    }

    private function isRateLimited(Request $request): bool
    {
        return false;
    }
}

// Middleware chain qurulur — hər biri əvvəlkini "dekorasiya" edir
$handler = new CoreHandler();
$handler = new AuthenticationMiddleware($handler);
$handler = new ThrottleMiddleware($handler);
$handler = new LoggingMiddleware($handler);

$response = $handler->handle($request);
// LoggingMiddleware -> ThrottleMiddleware -> AuthenticationMiddleware -> CoreHandler

// === Service Decorator nümunəsi ===

interface CacheStoreInterface
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int $seconds): bool;
}

class RedisStore implements CacheStoreInterface
{
    public function get(string $key): mixed { return null; }
    public function put(string $key, mixed $value, int $seconds): bool { return true; }
}

class CacheMetricsDecorator implements CacheStoreInterface
{
    private int $hits = 0;
    private int $misses = 0;

    public function __construct(private readonly CacheStoreInterface $store) {}

    public function get(string $key): mixed
    {
        $result = $this->store->get($key);
        $result !== null ? $this->hits++ : $this->misses++;
        return $result;
    }

    public function put(string $key, mixed $value, int $seconds): bool
    {
        return $this->store->put($key, $value, $seconds);
    }
}

class CachePrefixDecorator implements CacheStoreInterface
{
    public function __construct(
        private readonly CacheStoreInterface $store,
        private readonly string $prefix
    ) {}

    public function get(string $key): mixed
    {
        return $this->store->get($this->prefix . $key);
    }

    public function put(string $key, mixed $value, int $seconds): bool
    {
        return $this->store->put($this->prefix . $key, $value, $seconds);
    }
}

// Decorator chain:
$cache = new RedisStore();
$cache = new CachePrefixDecorator($cache, 'myapp:');
$cache = new CacheMetricsDecorator($cache);
```

---

## Facade Pattern

**Problem:** Mürəkkəb subsystem-ə sadə interface təmin etmək.

***Problem:** Mürəkkəb subsystem-ə sadə interface təmin etmək üçün kod nümunəsi:*
```php
<?php

// === Laravel Facades — Struktural Facade pattern ===

// Laravel Facade pattern-i iki mənada istifadə edir:
// 1. Structural Facade: mürəkkəb subsystem-ə sadə interface
// 2. Proxy/Static accessor: Service Container-ə static syntax ilə daxil olma

// Structural Facade nümunəsi:

// Mürəkkəb subsystem
class InventorySystem
{
    public function checkStock(int $productId): int { return 10; }
    public function reserveStock(int $productId, int $qty): bool { return true; }
    public function releaseStock(int $productId, int $qty): bool { return true; }
}

class PaymentSystem
{
    public function authorize(float $amount, array $card): string { return 'auth_123'; }
    public function capture(string $authId): bool { return true; }
    public function void(string $authId): bool { return true; }
}

class ShippingSystem
{
    public function calculateRate(array $items, string $address): float { return 5.99; }
    public function createLabel(array $order): string { return 'LABEL-123'; }
}

class NotificationSystem
{
    public function sendEmail(string $to, string $template, array $data): bool { return true; }
    public function sendSMS(string $to, string $message): bool { return true; }
}

// Facade — bütün subsystem-ləri sadə interface arxasında gizlədir
class OrderFacade
{
    public function __construct(
        private readonly InventorySystem $inventory,
        private readonly PaymentSystem $payment,
        private readonly ShippingSystem $shipping,
        private readonly NotificationSystem $notification
    ) {}

    /**
     * Mürəkkəb order prosesini sadə bir metod ilə
     */
    public function placeOrder(array $orderData): OrderResult
    {
        // 1. Stock yoxla
        foreach ($orderData['items'] as $item) {
            $stock = $this->inventory->checkStock($item['product_id']);
            if ($stock < $item['quantity']) {
                throw new InsufficientStockException($item['product_id']);
            }
        }

        // 2. Stock reserve et
        foreach ($orderData['items'] as $item) {
            $this->inventory->reserveStock($item['product_id'], $item['quantity']);
        }

        // 3. Shipping hesabla
        $shippingCost = $this->shipping->calculateRate(
            $orderData['items'],
            $orderData['address']
        );

        // 4. Ödəniş
        $total = $orderData['total'] + $shippingCost;
        $authId = $this->payment->authorize($total, $orderData['card']);
        $this->payment->capture($authId);

        // 5. Shipping label
        $label = $this->shipping->createLabel($orderData);

        // 6. Bildiriş
        $this->notification->sendEmail(
            $orderData['email'],
            'order-confirmation',
            ['order_id' => $orderData['id'], 'total' => $total]
        );

        return new OrderResult(
            success: true,
            orderId: $orderData['id'],
            shippingLabel: $label,
            total: $total
        );
    }
}
```

---

## Proxy Pattern

**Problem:** Başqa bir object üçün əvəzedici (surrogate) təmin etmək. Access control, lazy loading, logging və s. üçün.

***Problem:** Başqa bir object üçün əvəzedici (surrogate) təmin etmək.  üçün kod nümunəsi:*
```php
<?php

// === Laravel Lazy Loading Relationships — Proxy Pattern ===

// Eloquent relationship-lər proxy pattern istifadə edir:
$user = User::find(1);
// Bu anda posts yüklənməyib

$posts = $user->posts; // İlk daxil olduqda SQL sorğu çalışır (lazy proxy)
// SELECT * FROM posts WHERE user_id = 1

// === Virtual Proxy nümunəsi ===

interface ImageInterface
{
    public function display(): string;
    public function getSize(): int;
}

// Real image — yüklənməsi ağır
class RealImage implements ImageInterface
{
    private string $data;

    public function __construct(private readonly string $path)
    {
        $this->loadFromDisk(); // Ağır əməliyyat
    }

    private function loadFromDisk(): void
    {
        echo "Loading image from: {$this->path}\n";
        $this->data = file_get_contents($this->path);
    }

    public function display(): string
    {
        return $this->data;
    }

    public function getSize(): int
    {
        return strlen($this->data);
    }
}

// Proxy — lazy loading
class LazyImageProxy implements ImageInterface
{
    private ?RealImage $realImage = null;

    public function __construct(
        private readonly string $path
    ) {}

    private function getRealImage(): RealImage
    {
        if ($this->realImage === null) {
            $this->realImage = new RealImage($this->path);
        }
        return $this->realImage;
    }

    public function display(): string
    {
        return $this->getRealImage()->display();
    }

    public function getSize(): int
    {
        // Disk-dən ölçünü oxuya bilərik — şəkli yükləmədən
        return filesize($this->path);
    }
}

// Protection Proxy
class SecureServiceProxy implements ServiceInterface
{
    public function __construct(
        private readonly ServiceInterface $service,
        private readonly AuthService $auth
    ) {}

    public function sensitiveOperation(): mixed
    {
        if (!$this->auth->isAdmin()) {
            throw new UnauthorizedException('Admin access required');
        }
        return $this->service->sensitiveOperation();
    }
}

// Cache Proxy
class CachedRepositoryProxy implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly CacheInterface $cache
    ) {}

    public function findById(int $id): ?User
    {
        $key = "user:$id";

        return $this->cache->remember($key, 3600, function () use ($id) {
            return $this->repository->findById($id);
        });
    }

    // ...digər method-lar
}
```

---

## Flyweight Pattern

**Problem:** Çoxlu sayda oxşar object-lərin yaddaş istifadəsini azaltmaq.

***Problem:** Çoxlu sayda oxşar object-lərin yaddaş istifadəsini azaltm üçün kod nümunəsi:*
```php
<?php

// Flyweight — paylaşılan (intrinsic) state
class CharacterStyle
{
    public function __construct(
        public readonly string $font,
        public readonly int $size,
        public readonly string $color
    ) {}
}

// Flyweight Factory
class StyleFactory
{
    private static array $styles = [];

    public static function getStyle(string $font, int $size, string $color): CharacterStyle
    {
        $key = "$font-$size-$color";

        if (!isset(self::$styles[$key])) {
            self::$styles[$key] = new CharacterStyle($font, $size, $color);
        }

        return self::$styles[$key]; // Eyni style object paylaşılır
    }

    public static function getStyleCount(): int
    {
        return count(self::$styles);
    }
}

// Context — unikal (extrinsic) state
class Character
{
    public function __construct(
        public readonly string $char,         // Extrinsic
        public readonly int $position,         // Extrinsic
        public readonly CharacterStyle $style  // Flyweight (shared)
    ) {}
}

// İstifadə — 1000 simvol, amma yalnız bir neçə style object
$characters = [];
for ($i = 0; $i < 1000; $i++) {
    $characters[] = new Character(
        chr(rand(65, 90)),
        $i,
        StyleFactory::getStyle('Arial', 12, '#000') // Eyni style paylaşılır
    );
}

echo StyleFactory::getStyleCount(); // 1 (1000 deyil!)

// Laravel-də Flyweight:
// - String interning
// - Route caching (eyni middleware group-ları paylaşılır)
// - View component-lərdə shared state
```

---

# Behavioral Patterns

Behavioral pattern-lər object-lər arasındakı ünsiyyət və məsuliyyət bölgüsü ilə məşğul olur.

---

## Observer Pattern

**Problem:** Object-in vəziyyəti dəyişdikdə asılı object-ləri avtomatik xəbərdar etmək.

***Problem:** Object-in vəziyyəti dəyişdikdə asılı object-ləri avtomati üçün kod nümunəsi:*
```php
<?php

// === Laravel Events & Observers ===

// 1. Event / Listener (Observer pattern)

// Event class
class OrderCreated
{
    public function __construct(
        public readonly Order $order
    ) {}
}

// Listener-lər (Observer-lər)
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

class NotifyAdmin
{
    public function handle(OrderCreated $event): void
    {
        Notification::send(
            User::admins()->get(),
            new NewOrderNotification($event->order)
        );
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
// və ya
event(new OrderCreated($order));

// 2. Eloquent Model Observers

class OrderObserver
{
    public function creating(Order $order): void
    {
        $order->order_number = $this->generateOrderNumber();
    }

    public function created(Order $order): void
    {
        event(new OrderCreated($order));
    }

    public function updating(Order $order): void
    {
        if ($order->isDirty('status')) {
            $order->status_changed_at = now();
        }
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status === 'shipped') {
            event(new OrderShipped($order));
        }
    }

    public function deleting(Order $order): void
    {
        // Soft delete yoxlama
        if (!$order->isPaid()) {
            $order->items()->delete();
        }
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . str_pad(
            Order::whereDate('created_at', today())->count() + 1,
            4, '0', STR_PAD_LEFT
        );
    }
}

// Observer qeydiyyatı
// AppServiceProvider boot() metodunda:
Order::observe(OrderObserver::class);
```

---

## Strategy Pattern

**Problem:** Alqoritm ailəsini müəyyən etmək, hər birini kapsullaşdırmaq və dəyişdirilə bilən etmək.

***Problem:** Alqoritm ailəsini müəyyən etmək, hər birini kapsullaşdırm üçün kod nümunəsi:*
```php
<?php

// === Laravel Cache/Mail/Queue Drivers — Strategy Pattern ===

// Strategy interface
interface PricingStrategyInterface
{
    public function calculate(float $basePrice, array $context = []): float;
}

// Concrete strategy-lər
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
            $quantity >= 100 => $basePrice * 0.70, // 30% endirim
            $quantity >= 50 => $basePrice * 0.80,  // 20% endirim
            $quantity >= 10 => $basePrice * 0.90,  // 10% endirim
            default => $basePrice,
        };
    }
}

class SeasonalPricing implements PricingStrategyInterface
{
    public function calculate(float $basePrice, array $context = []): float
    {
        $month = (int) date('m');

        // Yay endirimi
        if (in_array($month, [6, 7, 8])) {
            return $basePrice * 0.85;
        }

        // Yeni il endirimi
        if ($month === 12) {
            return $basePrice * 0.80;
        }

        return $basePrice;
    }
}

// Context
class PriceCalculator
{
    private PricingStrategyInterface $strategy;

    public function __construct(PricingStrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    public function setStrategy(PricingStrategyInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function getPrice(float $basePrice, array $context = []): float
    {
        return round($this->strategy->calculate($basePrice, $context), 2);
    }
}

// İstifadə
$calculator = new PriceCalculator(new RegularPricing());
echo $calculator->getPrice(100); // 100

$calculator->setStrategy(new PremiumMemberPricing());
echo $calculator->getPrice(100); // 90

$calculator->setStrategy(new WholesalePricing());
echo $calculator->getPrice(100, ['quantity' => 50]); // 80

// Laravel-dəki Strategy nümunələri:
// - Cache drivers: redis, memcached, file, database, array
// - Mail drivers: smtp, sendmail, mailgun, ses, postmark
// - Queue drivers: sync, database, redis, sqs
// - Session drivers: file, cookie, database, redis
// - Hashing drivers: bcrypt, argon, argon2id

// Laravel-in driver switching mexanizmi:
// config/cache.php-dən CACHE_DRIVER=redis -> RedisStore strategiyası seçilir
// config/mail.php-dən MAIL_MAILER=smtp -> SmtpTransport strategiyası seçilir
```

---

## Chain of Responsibility Pattern

**Problem:** Request-i bir neçə handler-dən keçirmək. Hər handler ya request-i emal edir, ya da növbəti handler-ə ötürür.

***Problem:** Request-i bir neçə handler-dən keçirmək. Hər handler ya r üçün kod nümunəsi:*
```php
<?php

// === Laravel Middleware Pipeline — Chain of Responsibility ===

// Laravel-in middleware sistemi bu pattern-in ən gözəl nümunəsidir:

// Kernel.php
protected $middleware = [
    \App\Http\Middleware\TrustProxies::class,
    \App\Http\Middleware\HandleCors::class,
    \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
    \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
    \App\Http\Middleware\TrimStrings::class,
];

// Hər middleware request-i handle edir və ya növbəti middleware-ə ötürür

class EnsureUserIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
            // Chain burada qırılır — növbəti handler-ə keçmir
        }

        return $next($request);
        // Chain davam edir — növbəti handler-ə ötürülür
    }
}

class CheckUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()->hasRole($role)) {
            abort(403, 'İcazə yoxdur');
        }

        return $next($request);
    }
}

// === Custom Chain of Responsibility ===

abstract class ValidationHandler
{
    private ?ValidationHandler $next = null;

    public function setNext(ValidationHandler $handler): ValidationHandler
    {
        $this->next = $handler;
        return $handler;
    }

    public function validate(array $data): ?string
    {
        $error = $this->check($data);

        if ($error !== null) {
            return $error;
        }

        if ($this->next !== null) {
            return $this->next->validate($data);
        }

        return null; // Bütün validasiya keçdi
    }

    abstract protected function check(array $data): ?string;
}

class EmailFormatHandler extends ValidationHandler
{
    protected function check(array $data): ?string
    {
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return 'Email formatı yanlışdır';
        }
        return null;
    }
}

class UniqueEmailHandler extends ValidationHandler
{
    protected function check(array $data): ?string
    {
        if (User::where('email', $data['email'])->exists()) {
            return 'Bu email artıq istifadə olunur';
        }
        return null;
    }
}

class PasswordStrengthHandler extends ValidationHandler
{
    protected function check(array $data): ?string
    {
        $password = $data['password'] ?? '';
        if (strlen($password) < 8) {
            return 'Parol minimum 8 simvol olmalıdır';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Parolda ən az bir böyük hərf olmalıdır';
        }
        return null;
    }
}

// Chain qurulması
$emailFormat = new EmailFormatHandler();
$uniqueEmail = new UniqueEmailHandler();
$passwordStrength = new PasswordStrengthHandler();

$emailFormat->setNext($uniqueEmail)->setNext($passwordStrength);

$error = $emailFormat->validate([
    'email' => 'test@example.com',
    'password' => 'weakpass',
]);
// "Parolda ən az bir böyük hərf olmalıdır"
```

---

## Command Pattern

**Problem:** Request-i bir object-ə çevirmək ki, onu queue-ya əlavə etmək, log etmək və ya geri almaq (undo) mümkün olsun.

***Problem:** Request-i bir object-ə çevirmək ki, onu queue-ya əlavə et üçün kod nümunəsi:*
```php
<?php

// === Laravel Artisan Commands və Queued Jobs ===

// 1. Artisan Command (Command pattern)
use Illuminate\Console\Command;

class GenerateMonthlyReport extends Command
{
    protected $signature = 'report:monthly
                            {month? : Ay (default: keçən ay)}
                            {--format=pdf : Hesabat formatı (pdf, excel, csv)}
                            {--email= : Hesabatı göndərmək üçün email}';

    protected $description = 'Aylıq satış hesabatı yaradır';

    public function handle(
        ReportService $reportService,
        PDFExporter $exporter
    ): int {
        $month = $this->argument('month') ?? now()->subMonth()->format('Y-m');
        $format = $this->option('format');

        $this->info("Hesabat yaradılır: $month ($format format)...");

        $report = $reportService->generateMonthly($month);

        $filePath = match ($format) {
            'pdf' => $exporter->toPdf($report),
            'excel' => $exporter->toExcel($report),
            'csv' => $exporter->toCsv($report),
        };

        $this->info("Hesabat yaradıldı: $filePath");

        if ($email = $this->option('email')) {
            Mail::to($email)->send(new ReportMail($filePath));
            $this->info("Email göndərildi: $email");
        }

        return Command::SUCCESS;
    }
}

// 2. Queued Jobs (Command pattern + queue)
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 120;

    public function __construct(
        private readonly Order $order
    ) {}

    public function handle(
        PaymentService $paymentService,
        InventoryService $inventoryService
    ): void {
        // 1. Ödəniş
        $paymentService->processPayment($this->order);

        // 2. Anbar yenilə
        $inventoryService->decrementStock($this->order->items);

        // 3. Email göndər
        $this->order->user->notify(new OrderProcessedNotification($this->order));
    }

    public function failed(\Throwable $exception): void
    {
        // İş uğursuz olduqda
        $this->order->update(['status' => 'failed']);
        Log::error("Order processing failed: {$this->order->id}", [
            'error' => $exception->getMessage(),
        ]);
    }
}

// Command-ı queue-ya əlavə et
ProcessOrder::dispatch($order);
ProcessOrder::dispatch($order)->onQueue('orders');
ProcessOrder::dispatch($order)->delay(now()->addMinutes(5));

// 3. Job chain (bir neçə command ardıcıl)
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ProcessPayment($order),
    new UpdateInventory($order),
    new SendConfirmation($order),
    new NotifyWarehouse($order),
])->onQueue('orders')->dispatch();
```

---

## Iterator Pattern

**Problem:** Collection-un daxili strukturunu açmadan elementlərə ardıcıl daxil olmaq.

***Problem:** Collection-un daxili strukturunu açmadan elementlərə ardı üçün kod nümunəsi:*
```php
<?php

// === Laravel Collections və LazyCollections ===

// Collection — Iterator pattern-in ən gözəl nümunəsi
use Illuminate\Support\Collection;

$users = collect([
    ['name' => 'Orxan', 'age' => 25, 'city' => 'Bakı'],
    ['name' => 'Aynur', 'age' => 30, 'city' => 'Gəncə'],
    ['name' => 'Əli', 'age' => 22, 'city' => 'Bakı'],
    ['name' => 'Leyla', 'age' => 28, 'city' => 'Bakı'],
]);

// Fluent iteration
$result = $users
    ->filter(fn($u) => $u['city'] === 'Bakı')
    ->map(fn($u) => $u['name'] . ' (' . $u['age'] . ')')
    ->sort()
    ->values()
    ->all();
// ['Leyla (28)', 'Orxan (25)', 'Əli (22)']

// LazyCollection — böyük data set-lər üçün memory efficient
use Illuminate\Support\LazyCollection;

LazyCollection::make(function () {
    $handle = fopen('huge-file.csv', 'r');
    while (($line = fgetcsv($handle)) !== false) {
        yield $line;
    }
    fclose($handle);
})
->filter(fn($row) => $row[2] > 1000)
->take(100)
->each(fn($row) => processRow($row));
// Yalnız lazımi sətirləri yaddaşda saxlayır

// Eloquent lazy() — minlərlə model üçün
User::where('active', true)
    ->lazy(200) // 200-lük chunk-larla
    ->each(function (User $user) {
        $user->sendNewsletter();
    });

// === Custom Iterator ===
class DateRangeIterator implements \Iterator
{
    private \DateTimeImmutable $current;
    private int $position = 0;

    public function __construct(
        private readonly \DateTimeImmutable $start,
        private readonly \DateTimeImmutable $end,
        private readonly string $interval = '+1 day'
    ) {
        $this->current = $start;
    }

    public function current(): \DateTimeImmutable { return $this->current; }
    public function key(): int { return $this->position; }

    public function next(): void
    {
        $this->current = $this->current->modify($this->interval);
        $this->position++;
    }

    public function rewind(): void
    {
        $this->current = $this->start;
        $this->position = 0;
    }

    public function valid(): bool
    {
        return $this->current <= $this->end;
    }
}

// İstifadə
$range = new DateRangeIterator(
    new \DateTimeImmutable('2024-01-01'),
    new \DateTimeImmutable('2024-01-31')
);

foreach ($range as $date) {
    echo $date->format('Y-m-d') . "\n";
}
```

---

## Mediator Pattern

**Problem:** Object-lər arasında birbaşa əlaqəni azaltmaq. Bütün kommunikasiya mediator vasitəsilə olur.

***Problem:** Object-lər arasında birbaşa əlaqəni azaltmaq. Bütün kommu üçün kod nümunəsi:*
```php
<?php

// === Chat Room nümunəsi ===

interface ChatMediatorInterface
{
    public function sendMessage(string $message, ChatUser $sender): void;
    public function addUser(ChatUser $user): void;
}

class ChatRoom implements ChatMediatorInterface
{
    private array $users = [];

    public function addUser(ChatUser $user): void
    {
        $this->users[$user->getName()] = $user;
        $user->setMediator($this);
    }

    public function sendMessage(string $message, ChatUser $sender): void
    {
        foreach ($this->users as $user) {
            if ($user !== $sender) {
                $user->receive($message, $sender->getName());
            }
        }
    }
}

class ChatUser
{
    private ?ChatMediatorInterface $mediator = null;

    public function __construct(
        private readonly string $name
    ) {}

    public function setMediator(ChatMediatorInterface $mediator): void
    {
        $this->mediator = $mediator;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function send(string $message): void
    {
        echo "{$this->name} göndərir: $message\n";
        $this->mediator->sendMessage($message, $this);
    }

    public function receive(string $message, string $from): void
    {
        echo "{$this->name} aldı ($from): $message\n";
    }
}

$room = new ChatRoom();
$orxan = new ChatUser('Orxan');
$aynur = new ChatUser('Aynur');
$ali = new ChatUser('Əli');

$room->addUser($orxan);
$room->addUser($aynur);
$room->addUser($ali);

$orxan->send('Salam hamıya!');
// Orxan göndərir: Salam hamıya!
// Aynur aldı (Orxan): Salam hamıya!
// Əli aldı (Orxan): Salam hamıya!

// Laravel-də Mediator:
// - Event Dispatcher (event-lər və listener-lər birbaşa əlaqədə deyil)
// - Laravel Broadcasting (WebSocket kommunikasiyası)
```

---

## State Pattern

**Problem:** Object-in davranışını daxili vəziyyətinə görə dəyişdirmək.

***Problem:** Object-in davranışını daxili vəziyyətinə görə dəyişdirmək üçün kod nümunəsi:*
```php
<?php

// === Order Status State Pattern ===

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
        // Ödəniş yoxlanılır
        $order->setState(new ProcessingState());
        echo "Sifariş emal olunmağa başladı\n";
    }

    public function cancel(Order $order): void
    {
        $order->setState(new CancelledState());
        echo "Sifariş ləğv edildi\n";
    }

    public function getStatus(): string { return 'pending'; }
}

class ProcessingState implements OrderStateInterface
{
    public function proceed(Order $order): void
    {
        $order->setState(new ShippedState());
        echo "Sifariş göndərildi\n";
    }

    public function cancel(Order $order): void
    {
        // Refund prosesi
        $order->setState(new CancelledState());
        echo "Sifariş ləğv edildi, geri ödəniş başladı\n";
    }

    public function getStatus(): string { return 'processing'; }
}

class ShippedState implements OrderStateInterface
{
    public function proceed(Order $order): void
    {
        $order->setState(new DeliveredState());
        echo "Sifariş çatdırıldı\n";
    }

    public function cancel(Order $order): void
    {
        throw new \RuntimeException('Göndərilmiş sifarişi ləğv etmək mümkün deyil');
    }

    public function getStatus(): string { return 'shipped'; }
}

class DeliveredState implements OrderStateInterface
{
    public function proceed(Order $order): void
    {
        throw new \RuntimeException('Sifariş artıq çatdırılıb');
    }

    public function cancel(Order $order): void
    {
        throw new \RuntimeException('Çatdırılmış sifarişi ləğv etmək mümkün deyil');
    }

    public function getStatus(): string { return 'delivered'; }
}

class CancelledState implements OrderStateInterface
{
    public function proceed(Order $order): void
    {
        throw new \RuntimeException('Ləğv edilmiş sifariş davam etdirilə bilməz');
    }

    public function cancel(Order $order): void
    {
        throw new \RuntimeException('Sifariş artıq ləğv edilib');
    }

    public function getStatus(): string { return 'cancelled'; }
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

    public function proceed(): void
    {
        $this->state->proceed($this);
    }

    public function cancel(): void
    {
        $this->state->cancel($this);
    }

    public function getStatus(): string
    {
        return $this->state->getStatus();
    }
}

$order = new Order();
echo $order->getStatus(); // "pending"

$order->proceed();        // "Sifariş emal olunmağa başladı"
echo $order->getStatus(); // "processing"

$order->proceed();        // "Sifariş göndərildi"
echo $order->getStatus(); // "shipped"

$order->proceed();        // "Sifariş çatdırıldı"
echo $order->getStatus(); // "delivered"

// $order->cancel(); // RuntimeException: Çatdırılmış sifarişi ləğv etmək mümkün deyil
```

---

## Template Method Pattern

**Problem:** Alqoritmin skeletini müəyyən etmək, bəzi addımları subclass-lara həvalə etmək.

***Problem:** Alqoritmin skeletini müəyyən etmək, bəzi addımları subcla üçün kod nümunəsi:*
```php
<?php

// === Data Import Template ===

abstract class DataImporter
{
    // Template Method — alqoritmin skeleti
    final public function import(string $source): ImportResult
    {
        $this->log("Import başladı: $source");

        $rawData = $this->readData($source);
        $this->log("Data oxundu: " . count($rawData) . " qeyd");

        $validData = $this->validateData($rawData);
        $this->log("Validation keçdi: " . count($validData) . " qeyd");

        $transformedData = $this->transformData($validData);
        $this->log("Transform olundu");

        $count = $this->saveData($transformedData);
        $this->log("Saxlandı: $count qeyd");

        $this->afterImport($count);

        return new ImportResult($count, count($rawData) - count($validData));
    }

    // Subclass-lar bunları implement etməlidir
    abstract protected function readData(string $source): array;
    abstract protected function validateData(array $data): array;
    abstract protected function transformData(array $data): array;
    abstract protected function saveData(array $data): int;

    // Hook method — override edilə bilər, amma lazım deyil
    protected function afterImport(int $count): void
    {
        // Default: heç nə etmə
    }

    protected function log(string $message): void
    {
        echo "[" . date('H:i:s') . "] $message\n";
    }
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
        return array_filter($data, function ($row) {
            return !empty($row['email'])
                && filter_var($row['email'], FILTER_VALIDATE_EMAIL)
                && !empty($row['name']);
        });
    }

    protected function transformData(array $data): array
    {
        return array_map(fn($row) => [
            'name' => trim($row['name']),
            'email' => strtolower(trim($row['email'])),
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

class JsonProductImporter extends DataImporter
{
    protected function readData(string $source): array
    {
        return json_decode(file_get_contents($source), true);
    }

    protected function validateData(array $data): array
    {
        return array_filter($data, fn($row) =>
            !empty($row['sku']) && isset($row['price']) && $row['price'] > 0
        );
    }

    protected function transformData(array $data): array
    {
        return array_map(fn($row) => [
            'sku' => $row['sku'],
            'name' => $row['name'],
            'price' => (float) $row['price'],
            'slug' => Str::slug($row['name']),
        ], $data);
    }

    protected function saveData(array $data): int
    {
        foreach ($data as $item) {
            Product::updateOrCreate(['sku' => $item['sku']], $item);
        }
        return count($data);
    }
}

// İstifadə
$importer = new CsvUserImporter();
$result = $importer->import('users.csv');

$productImporter = new JsonProductImporter();
$result = $productImporter->import('products.json');
```

---

## Visitor Pattern

**Problem:** Object strukturuna yeni əməliyyatlar əlavə etmək, class-ları dəyişdirmədən.

***Problem:** Object strukturuna yeni əməliyyatlar əlavə etmək, class-l üçün kod nümunəsi:*
```php
<?php

interface DocumentElementInterface
{
    public function accept(DocumentVisitorInterface $visitor): mixed;
}

interface DocumentVisitorInterface
{
    public function visitParagraph(Paragraph $p): mixed;
    public function visitImage(Image $img): mixed;
    public function visitTable(Table $table): mixed;
}

class Paragraph implements DocumentElementInterface
{
    public function __construct(public readonly string $text) {}

    public function accept(DocumentVisitorInterface $visitor): mixed
    {
        return $visitor->visitParagraph($this);
    }
}

class Image implements DocumentElementInterface
{
    public function __construct(
        public readonly string $src,
        public readonly int $width,
        public readonly int $height
    ) {}

    public function accept(DocumentVisitorInterface $visitor): mixed
    {
        return $visitor->visitImage($this);
    }
}

class Table implements DocumentElementInterface
{
    public function __construct(
        public readonly array $headers,
        public readonly array $rows
    ) {}

    public function accept(DocumentVisitorInterface $visitor): mixed
    {
        return $visitor->visitTable($this);
    }
}

// HTML Visitor
class HtmlExportVisitor implements DocumentVisitorInterface
{
    public function visitParagraph(Paragraph $p): string
    {
        return "<p>{$p->text}</p>";
    }

    public function visitImage(Image $img): string
    {
        return "<img src='{$img->src}' width='{$img->width}' height='{$img->height}'>";
    }

    public function visitTable(Table $table): string
    {
        $html = '<table><tr>';
        foreach ($table->headers as $h) {
            $html .= "<th>$h</th>";
        }
        $html .= '</tr>';
        foreach ($table->rows as $row) {
            $html .= '<tr>' . implode('', array_map(fn($c) => "<td>$c</td>", $row)) . '</tr>';
        }
        return $html . '</table>';
    }
}

// Word Count Visitor
class WordCountVisitor implements DocumentVisitorInterface
{
    public function visitParagraph(Paragraph $p): int
    {
        return str_word_count($p->text);
    }

    public function visitImage(Image $img): int { return 0; }
    public function visitTable(Table $table): int
    {
        $count = 0;
        foreach ($table->rows as $row) {
            foreach ($row as $cell) {
                $count += str_word_count($cell);
            }
        }
        return $count;
    }
}

$elements = [
    new Paragraph('Bu bir test paragrafıdır'),
    new Image('/img/photo.jpg', 800, 600),
    new Table(['Ad', 'Yaş'], [['Orxan', '25'], ['Aynur', '30']]),
];

$htmlVisitor = new HtmlExportVisitor();
$wordCountVisitor = new WordCountVisitor();

foreach ($elements as $el) {
    echo $el->accept($htmlVisitor) . "\n";
}

$totalWords = array_sum(array_map(fn($el) => $el->accept($wordCountVisitor), $elements));
echo "Cəmi söz: $totalWords\n";
```

---

## Memento Pattern

**Problem:** Object-in daxili vəziyyətini xaricdən saxlayıb, sonra bərpa etmək (undo/redo).

***Problem:** Object-in daxili vəziyyətini xaricdən saxlayıb, sonra bər üçün kod nümunəsi:*
```php
<?php

class EditorMemento
{
    public function __construct(
        private readonly string $content,
        private readonly int $cursorPosition,
        private readonly \DateTimeImmutable $timestamp
    ) {}

    public function getContent(): string { return $this->content; }
    public function getCursorPosition(): int { return $this->cursorPosition; }
    public function getTimestamp(): \DateTimeImmutable { return $this->timestamp; }
}

class TextEditor
{
    private string $content = '';
    private int $cursorPosition = 0;

    public function type(string $text): void
    {
        $this->content = substr($this->content, 0, $this->cursorPosition)
            . $text
            . substr($this->content, $this->cursorPosition);
        $this->cursorPosition += strlen($text);
    }

    public function delete(int $count = 1): void
    {
        $start = max(0, $this->cursorPosition - $count);
        $this->content = substr($this->content, 0, $start) . substr($this->content, $this->cursorPosition);
        $this->cursorPosition = $start;
    }

    public function getContent(): string { return $this->content; }

    public function save(): EditorMemento
    {
        return new EditorMemento($this->content, $this->cursorPosition, new \DateTimeImmutable());
    }

    public function restore(EditorMemento $memento): void
    {
        $this->content = $memento->getContent();
        $this->cursorPosition = $memento->getCursorPosition();
    }
}

class EditorHistory
{
    private array $history = [];
    private int $current = -1;

    public function push(EditorMemento $memento): void
    {
        $this->current++;
        $this->history = array_slice($this->history, 0, $this->current);
        $this->history[] = $memento;
    }

    public function undo(): ?EditorMemento
    {
        if ($this->current > 0) {
            $this->current--;
            return $this->history[$this->current];
        }
        return null;
    }

    public function redo(): ?EditorMemento
    {
        if ($this->current < count($this->history) - 1) {
            $this->current++;
            return $this->history[$this->current];
        }
        return null;
    }
}

$editor = new TextEditor();
$history = new EditorHistory();

$editor->type('Salam ');
$history->push($editor->save());

$editor->type('Dünya!');
$history->push($editor->save());

echo $editor->getContent(); // "Salam Dünya!"

$editor->restore($history->undo());
echo $editor->getContent(); // "Salam "

$editor->restore($history->redo());
echo $editor->getContent(); // "Salam Dünya!"
```

---

## Null Object Pattern

**Problem:** Null yoxlamalarından qaçınmaq üçün "heç nə etməyən" object istifadə etmək.

***Problem:** Null yoxlamalarından qaçınmaq üçün "heç nə etməyən" objec üçün kod nümunəsi:*
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
        // Heç nə etmir — bu məqsədlidir
    }
}

class OrderService
{
    public function __construct(
        private readonly LoggerInterface $logger // Null ola bilməz!
    ) {}

    public function process(): void
    {
        // null yoxlama lazım deyil
        $this->logger->log('Processing order...');
    }
}

// Production:
$service = new OrderService(new FileLogger());

// Test və ya logging lazım olmadığı halda:
$service = new OrderService(new NullLogger());

// Laravel-də Null Object nümunələri:
// - NullSessionHandler
// - NullLogger
// - Optional helper

$user = User::find(999); // null
// optional() — Null Object kimi işləyir
echo optional($user)->name; // null (exception yox)
echo optional($user)->getAddress()?->city; // null
```

---

## İntervyu Sualları

### Sual 1: Design Pattern nədir? Niyə istifadə olunur?
**Cavab:** Design Pattern — proqramlaşdırmada tez-tez qarşılaşılan problemlərə sübut edilmiş, təkrar istifadə oluna bilən həll yollarıdır. Kod deyil, template-dir. Gang of Four (GoF) 23 pattern müəyyən edib: Creational (5), Structural (7), Behavioral (11). Üstünlükləri: ortaq terminologiya, sübut edilmiş həllər, kodun oxunaqlılığı, maintainability. Pattern-lər over-engineering-ə yol açmamalıdır — yalnız real ehtiyac olduqda istifadə olunmalıdır.

### Sual 2: Laravel-də hansı Design Pattern-lər istifadə olunur?
**Cavab:** Laravel demək olar ki, bütün əsas pattern-ləri istifadə edir: Singleton (Service Container bindings), Factory (Eloquent Factories, Manager classes), Builder (Query Builder, Notification), Strategy (Cache/Mail/Queue drivers), Observer (Events, Model Observers), Decorator (Middleware), Facade (Laravel Facades), Chain of Responsibility (Middleware Pipeline), Command (Artisan Commands, Queued Jobs), Iterator (Collections, LazyCollection), Adapter (Filesystem adapters, Cache drivers), Proxy (Lazy loading relationships), Template Method (Notification channels — toMail, toSlack), Repository (optional — interface-based data access).

### Sual 3: Strategy və Factory pattern arasındakı fərq?
**Cavab:** Factory — object yaratma ilə məşğuldur. Hansı class-ın instance-ının yaradılacağını müəyyən edir. Strategy — davranış/alqoritm seçimi ilə məşğuldur. Runtime zamanı alqoritmi dəyişdirməyə imkan verir. Məsələn: Cache Manager factory ilə driver yaradır (createRedisDriver), yaradılmış driver isə strategy-dir (Redis vs Memcached vs File — eyni interface, fərqli davranış).

### Sual 4: Middleware nə pattern-dir?
**Cavab:** Laravel Middleware həm Decorator, həm də Chain of Responsibility pattern-dir. Decorator aspekti: hər middleware request/response-u əlavə funksionallıqla "sarır" (logging, auth, CORS). Chain of Responsibility aspekti: request bir neçə middleware-dən keçir, hər biri ya emal edir, ya da növbəti middleware-ə ötürür. `$next($request)` çağırışı chain-dəki növbəti halqaya keçidir.

### Sual 5: Observer və Event/Listener arasındakı fərq nədir?
**Cavab:** İkisi də Observer pattern-in implementasiyasıdır, amma fərqli səviyyələrdədir. Model Observer — Eloquent model-ə xas lifecycle hook-ları (creating, created, updating, deleted və s.) — sıx bağlıdır, yalnız model event-ləri üçündür. Event/Listener — application-level loose coupling — istənilən event fire oluna bilər, çoxlu listener ola bilər, queued ola bilər, cross-module kommunikasiya üçün idealdır.

### Sual 6: Builder Pattern ilə Factory Pattern-in fərqi nədir?
**Cavab:** Factory — object-i bir addımda yaradır, daxili təfsilatları gizlədir. Builder — mürəkkəb object-ləri addım-addım qurur, fluent interface ilə konfiqurasiya edir. Məsələn: `User::factory()->create()` Factory-dir. `DB::table('users')->where(...)->orderBy(...)->get()` Builder-dir. Builder daha çox method chain-i ilə əlaqəlidir.

### Sual 7: Adapter pattern nə zaman istifadə olunmalıdır?
**Cavab:** Adapter iki uyğunsuz interface-i birlikdə işlətmək üçündür. İstifadə halları: 1) Xarici API/SDK-nın interface-ini öz application interface-inə uyğunlaşdırmaq, 2) Legacy kod ilə yeni kod-u birləşdirmək, 3) Testing üçün — real service-i test double ilə əvəz etmək. Laravel-də: Filesystem adapters (Local, S3, FTP hamısı eyni interface), Cache drivers, Mail transports.

### Sual 8: Singleton pattern niyə anti-pattern hesab olunur?
**Cavab:** Classic Singleton: 1) Global state yaradır, 2) Test etmək çətindir (mock etmək olmur), 3) Tight coupling, 4) Single Responsibility pozur (həm iş görür, həm lifecycle idarə edir), 5) Concurrency problemləri. Əvəzində Service Container singleton binding istifadə olunmalıdır — test zamanı asanlıqla swap olunur, interface-ə bağlanır, DIP prinsipini pozmur.

### Sual 9: State Pattern ilə Strategy Pattern-in fərqi nədir?
**Cavab:** İkisi də davranışı dəyişdirmək üçündür, amma məqsəd fərqlidir. Strategy — xaricdən müəyyən edilir, client alqoritm seçir (cache driver, ödəniş metodu). State — daxili vəziyyətə görə avtomatik dəyişir, state transitions var (sifariş statusu: pending->processing->shipped). State-də state object-lər bir-birini bilirlər (transition), Strategy-də strategy-lər müstəqildir.

### Sual 10: Repository Pattern-i niyə bəziləri istifadə etmir?
**Cavab:** Eloquent Active Record pattern istifadə edir ki, bu, Repository-yə alternativdir. Repository əlavə layer yaradır, amma Eloquent artıq query builder, scopes, relationships kimi güclü abstraksiyalar təqdim edir. Repository lehləri: testability, implementation switching (Eloquent -> API), clean architecture. Əleyhləri: boilerplate kod, Eloquent-in gücünü daraltma. Orta yol: Query Object pattern, Action classes, Eloquent-i Repository daxilində istifadə.

### Sual 11: Pipeline Pattern nədir? Laravel-də harada istifadə olunur?

**Cavab:** Pipeline Pattern — bir obyekti ardıcıl "stage"-lər (mərhələlər) vasitəsilə ötürür, hər stage obyekti modifikasiya edə bilər. Hər stage bir `Closure` və ya `handle()` metodu olan class-dır. Zəncir kimi: `Input → Stage1 → Stage2 → Stage3 → Output`. Laravel-də: **Middleware Pipeline** (HTTP request-ləri), **artisan pipeline** (event dispatching). Birbaşa `Pipeline` facade ilə özünüz də istifadə edə bilərsiniz:
***Cavab:** Pipeline Pattern — bir obyekti ardıcıl "stage"-lər (mərhələ üçün kod nümunəsi:*
```php
// Sifariş emalı pipeline-u
$result = Pipeline::send($order)
    ->through([
        ValidateOrderStage::class,
        CalculateDiscountStage::class,
        ApplyTaxStage::class,
        ReserveInventoryStage::class,
    ])
    ->thenReturn();
// Hər Stage handle(Order $order, Closure $next): Order
```
Üstünlüyü: yeni addım əlavə etmək mövcud kodu dəyişdirmir (OCP). Middleware pattern-dən fərqi: Pipeline stage-ləri obyekti dəyişdirərək ötürür, Middleware isə request/response-u wrap edir.

### Sual 12: Specification Pattern nə zaman design pattern kimi faydalıdır?

**Cavab:** Specification Pattern — business qaydalarını ayrı class-larda kapsullaşdırır, `isSatisfiedBy($entity)` metodu qaytarır. Ən faydalı olduğu hallar: (1) Eyni qaydanın fərqli yerlərde (validation, filtering, authorization) istifadəsi (2) AND/OR/NOT ilə composable qaydalar lazım olduqda (3) Dynamic query building (repository-yə push edilir). Laravel-in Eloquent Scopes Specification-a bənzərdir amma daha az composable-dır. Mürəkkəb e-commerce business qaydaları, eligibility check-lər, promotion logic üçün idealdır.

---

## Anti-patternlər

**1. Pattern-i Məqsədsiz Tətbiq Etmək (Pattern-itis)**
Hər problem üçün design pattern axtarmaq — sadə `if/else` yerinə Strategy, `new` yerinə Factory yazmaq kod mürəkkəbliyini artırır, başqa developerlar dərhal başa düşmür. Pattern yalnız real mürəkkəbliyi azaldanda, genişlənəbilirlik lazım olduqda tətbiq edilməlidir.

**2. Classic Singleton Pattern İstifadəsi**
Static instance metodu ilə özünü idarə edən Singleton sinifləri yazmaq — global state, test mümkünsüzlüyü, tight coupling yaranır. Laravel Service Container-da `singleton()` binding istifadə edin; bu, eyni nəticəni test edilə bilən şəkildə verir.

**3. Observer-ı Zəncirvarı Yan Effekt Üçün İstifadə Etmək**
Model Observer-ın içindən başqa model-i yeniləmək, o model-in Observer-ının da başqa model-i yeniləməsi — gizli yan effektlər zənciri yaranır, debug çox çətin olur. Observer-lar sadə, izolə edilmiş məsuliyyət saxlamalıdır; mürəkkəb axınlar üçün Domain Event + Handler istifadə edin.

**4. Strategy Əvəzinə Uzun if/switch Zəncirləri**
Ödəniş metodunu, hesabat növünü, bildirim kanalını `if ($type === 'email')` zənciri ilə idarə etmək — yeni tip əlavə etmək mövcud kodu dəyişdirir (OCP pozulur), sinif nəhəngləşir. Strategy pattern ilə hər davranışı ayrı class-a çıxarın, yeni tip əlavə etmək mövcud kodu dəyişdirmir.

**5. Decorator Əvəzinə Inheritance ilə Genişlənmə**
Logging, caching, rate limiting əlavə etmək üçün base class-dan extend etmək — dərin inheritance ağacı yaranır, çoxlu kombinasiya lazım olduqda class sayı partlayır. Decorator pattern ilə wrapper class-lar yazın; funksionallıqları dinamik olaraq əlavə edin.

**6. Repository-ni Generic CRUD Interfeysinə Endirmək**
`findAll()`, `findById()`, `save()`, `delete()` metodlarından ibarət universal Repository — domain-specific sorğuları ifadə etmir, `findAll()` sonra PHP-də filter etmək lazım gəlir, performans aşağı düşür. Repository-lərə domain dilini əks etdirən metodlar yazın: `findActiveOrdersByCustomer()`, `findOverdueInvoices()`.
