# SOLID Principles (Middle ⭐⭐)

## İcmal
SOLID — Robert C. Martin (Uncle Bob) tərəfindən formalize edilmiş beş object-oriented design prinsipidir. Bu prinsiplər maintainable, extensible, testable kod yazmaq üçün əsas qaydalardır. Interview-larda SOLID demək olar ki, hər OOP-related sualda gəlir. "Bu kodun nə problemi var?" ya da "Bu kodu necə yaxşılaşdırarsınız?" suallarına cavab əksər hallarda SOLID prinsiplərinə bağlıdır.

## Niyə Vacibdir
SOLID prinsipləri niyə vacibdir sualına cavab "kod daha yaxşı olur" deyil. Konkret: SRP olmadan bir class-ın dəyişməsi üçün on nədən ola bilər — bu testi çətinləşdirir, merge conflict yaradır, unexpected side effect riski artır. Interviewer SOLID sualı verəndə yoxlayır: prinsipləri əzbərlədinizmi, yoksa real kod üzərindən tətbiq edə bilirsinizmi?

---

## Əsas Anlayışlar

**S — Single Responsibility Principle (SRP):**
- "A class should have only one reason to change" — bir class yalnız bir iş görməlidir
- Praktik test: "Bu class-ın dəyişmə səbəbi nə olardı?" — bir-dən artıqsa SRP pozulub
- Yanlış: `OrderController` həm HTTP handling, həm validation, həm email, həm DB edir
- Düzgün: `OrderController` → `OrderValidator` → `OrderService` → `OrderRepository` → `OrderMailer`
- Laravel-də: Request FormRequest (validation), Policy (authorization), Service (business logic), Model (data)

**O — Open/Closed Principle (OCP):**
- "Open for extension, closed for modification" — yeni davranış əlavə etmək üçün mövcud kodu dəyişmək lazım olmamalıdır
- Yanlış: `if ($type === 'paypal') ... elseif ($type === 'stripe')...` — yeni payment provider: bu kodu dəyiş
- Düzgün: `PaymentDriverInterface` → `PaypalDriver`, `StripeDriver` — yeni driver: yeni class, mövcud kod dəyişmir
- Strategy, Template Method, Plugin architecture OCP-ni dəstəkləyir

**L — Liskov Substitution Principle (LSP):**
- "Subtype must be substitutable for its base type" — child class parent-in yerinə keçə bilməlidir
- Klassik anti-nümunə: `Square extends Rectangle` — `setWidth()` `Square`-in invariant-ını pozur
- Praktik test: "Parent class-ın test-lərini child class-da run etsəm keçirmi?" — keçmirsa LSP pozulub
- Override method parent-in pre/post condition-larını dəyişdirməməlidir

**I — Interface Segregation Principle (ISP):**
- "Clients should not be forced to depend on interfaces they do not use"
- Yanlış: `Animal` interface-ında `fly()`, `swim()`, `run()`, `bark()` — penguin `fly()` implement etməlidir
- Düzgün: `Flyable`, `Swimmable`, `Runnable` — hər class ehtiyac duyduğu interface-i implement edir
- Fat interface-lər tight coupling yaradır; dəyişiklik ripple effect ilə yayılır

**D — Dependency Inversion Principle (DIP):**
- "High-level modules should not depend on low-level modules. Both should depend on abstractions"
- Yanlış: `OrderService` daxilindən `new MySQLOrderRepository()` — MySQL-ə tight coupling
- Düzgün: `OrderService` constructor-da `OrderRepositoryInterface` qəbul edir — DI container istənilən impl inject edə bilər
- Laravel `ServiceProvider`-da: `$this->app->bind(Interface::class, Implementation::class)`

**SOLID pozulmasının code smell-ləri:**
- **SRP:** 300+ sətirlik class, `and`/`also` olan method adları (`saveAndSendEmail`)
- **OCP:** `switch ($type)`, `if ($class instanceof X)` — yeni case əlavə etmək üçün bu fayla toxunmaq
- **LSP:** Child class-da `throw new NotImplementedException()`, empty override, parent davranışını kəskin dəyişdirən override
- **ISP:** Interface method-larının yarısı empty body ilə implement olunur
- **DIP:** `new ClassName()` class daxilindən, `static::` call-lar, global state

---

## Praktik Baxış

**Interview-da yanaşma:**
- Bütün 5-i sıralayıb izah etmək boring-dir; "Ən çox pozulan SRP-dir, çünki..." ilə başlayın
- Konkret kod nümunəsi olmadan "prinsip pozulub" demək zəif cavabdır — kod göstərin
- "SOLID həmişə tətbiq edilməlidirmi?" sualına hazır olun — YAGNI ilə balans

**Follow-up suallar:**
1. "SOLID prinsipləri həmişə tətbiq edilməlidirmi?" — Xeyr. YAGNI (You Aren't Gonna Need It) ilə balanslaşdırın; MVP mərhələsində over-engineering pis olar
2. "DIP ilə Dependency Injection eyni şeydirmi?" — DIP prinsipdir, DI onun implementasiya pattern-idir
3. "Laravel ServiceContainer SOLID-ə necə kömək edir?" — DIP-i tətbiq edir: interface-ə bind, container inject edir
4. "SRP-nin 'bir method' mənasına gəldiyini düşünürsünüzmü?" — Xeyr; bir "reason to change" — business domain baxımından bir məsuliyyət
5. "ISP vs SRP fərqi nədir?" — SRP class-ın məsuliyyəti; ISP interface-in genişliyi haqqındadır
6. "LSP-nin praktik testi nədir?" — Barbra Liskov test: "Parent-ın test suite-ini child-da run et, keçməlidir"

**Real framework implementasiyaları:**
- **SRP:** Laravel FormRequest (validation), Policy (authorization), Resource (presentation) — hər class bir iş
- **OCP:** Laravel Macro (`Collection::macro()`), custom driver (`Cache::extend()`)
- **LSP:** Eloquent Builder-i extend etmək — base builder-in davranışını pozmadan
- **ISP:** Laravel Contract-lar ayrı interface-lər: `Illuminate\Contracts\Queue\Queue`, `\Mail\Mailer`, etc.
- **DIP:** Laravel DI Container, constructor injection, `bind()` / `singleton()` / `make()`

**Anti-patterns:**
- Over-engineering: Sadə 50 sətirlik app üçün 10 interface, 15 class yaratmaq
- "Fake OCP": Interface var, amma driver əlavə etmək üçün hələ də `config/app.php`-ni dəyişmək lazımdır
- "SOLID for SOLID's sake": Test edilməyən, ya real istifadə olmayan abstraction-lar

---

## Nümunələr

### Tipik Interview Sualı
"Here's this UserService class with 500 lines. It handles registration, login, email sending, and PDF reports. What SOLID principles are violated and how would you refactor it?"

### Güclü Cavab
Bu class açıq-aşkar SRP pozur — ən azı dörd "reason to change" var: authentication logic dəyişsə, email template dəyişsə, PDF library dəyişsə, registration validation dəyişsə — hamısı bu class-a toxunur. Hər dəyişiklik digərlərini inadvertently etkiləyə bilər, test etmək çətindir, merge conflict-lər olur.

Refactor: `AuthService` (login/logout/token), `UserRegistrationService` (registration + validation), `UserNotificationService` (email), `UserReportService` (PDF). Hər birinin bir "reason to change" var.

OCP baxımından: `if ($notificationType === 'email') ... elseif (...)` — yeni notification type əlavə etmək: `NotificationChannel` interface + konkret implementation-lar.

DIP baxımından: Service-lər bir-birinə birbaşa `new` etmədən, interface vasitəsilə depend etməlidir — test etmək üçün mock inject etmək mümkün olur.

### Kod Nümunəsi

```php
// ── SRP Pozulması vs Düzgün Bölünmə ─────────────────────────────

// ❌ SRP pozulması — çox responsibility
class UserService
{
    public function register(array $data): User
    {
        // Registration + validation + email + logging = 4 responsibility
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email');
        }

        $user = User::create([
            'email'    => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        // Email sending — başqa responsibility!
        Mail::to($user->email)->send(new WelcomeMail($user));

        // PDF generation — başqa responsibility!
        $pdf = PDF::loadView('reports.welcome', ['user' => $user]);
        Storage::put("reports/{$user->id}.pdf", $pdf->output());

        // Audit log — başqa responsibility!
        AuditLog::create(['action' => 'user.registered', 'user_id' => $user->id]);

        return $user;
    }
}

// ✅ SRP — hər class bir responsibility
class UserRegistrationService
{
    public function __construct(
        private readonly UserRepository          $repository,
        private readonly PasswordHasher          $hasher,
        private readonly UserRegistrationValidator $validator,
    ) {}

    public function register(RegisterUserDTO $dto): User
    {
        $this->validator->validate($dto);

        $user = $this->repository->create([
            'email'    => $dto->email,
            'password' => $this->hasher->hash($dto->password),
        ]);

        UserRegistered::dispatch($user); // Event → listener-lər öz işlərini görür

        return $user;
    }
}

// Hər listener bir responsibility
class SendWelcomeEmail implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)->send(new WelcomeMail($event->user));
    }
}

class GenerateWelcomeReport implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        dispatch(new GenerateUserReportJob($event->user->id));
    }
}
```

```php
// ── OCP: Strategy pattern ilə notification ──────────────────────

// ❌ OCP pozulması — yeni channel: bu faylı dəyiş
class NotificationService
{
    public function send(string $type, string $message, User $user): void
    {
        if ($type === 'email') {
            Mail::to($user->email)->raw($message);
        } elseif ($type === 'sms') {
            SmsGateway::send($user->phone, $message);
        } elseif ($type === 'push') {
            PushService::notify($user->device_token, $message);
            // Yeni channel lazım oldu → bu kodu dəyiş (OCP pozulur)
        }
    }
}

// ✅ OCP — yeni channel: yeni class, mövcud kod dəyişmir
interface NotificationChannel
{
    public function send(string $message, User $user): void;
    public function supports(string $type): bool;
}

class EmailChannel implements NotificationChannel
{
    public function send(string $message, User $user): void
    {
        Mail::to($user->email)->raw($message);
    }

    public function supports(string $type): bool { return $type === 'email'; }
}

class SmsChannel implements NotificationChannel
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(string $message, User $user): void
    {
        $this->gateway->send($user->phone, $message);
    }

    public function supports(string $type): bool { return $type === 'sms'; }
}

// Slack channel lazım oldu → yeni class yazdıq, heç bir mövcud kodu dəyişmədik
class SlackChannel implements NotificationChannel
{
    public function __construct(private readonly SlackClient $slack) {}

    public function send(string $message, User $user): void
    {
        $this->slack->postMessage($user->slack_id, $message);
    }

    public function supports(string $type): bool { return $type === 'slack'; }
}

class NotificationDispatcher
{
    /** @param NotificationChannel[] $channels */
    public function __construct(private readonly array $channels) {}

    public function send(string $type, string $message, User $user): void
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($type)) {
                $channel->send($message, $user);
                return;
            }
        }
        throw new \RuntimeException("No channel for type: {$type}");
    }
}
```

```php
// ── DIP: Interface-ə depend et ───────────────────────────────────

// ❌ DIP pozulması — MySQL-ə tight coupling
class OrderService
{
    private MySQLOrderRepository $repo;

    public function __construct()
    {
        $this->repo = new MySQLOrderRepository(); // Hardcoded!
        // Test: mock inject etmək mümkün deyil
        // Switch to Redis: bu class-ı dəyiş
    }

    public function getOrder(int $id): Order
    {
        return $this->repo->findById($id);
    }
}

// ✅ DIP — abstraction-a depend et
interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;
    public function save(Order $order): void;
    public function delete(int $id): void;
}

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    public function save(Order $order): void
    {
        $order->save();
    }

    public function delete(int $id): void
    {
        Order::destroy($id);
    }
}

// Test üçün in-memory implementasiya
class InMemoryOrderRepository implements OrderRepositoryInterface
{
    private array $orders = [];

    public function findById(int $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }

    public function save(Order $order): void
    {
        $this->orders[$order->id] = $order;
    }

    public function delete(int $id): void
    {
        unset($this->orders[$id]);
    }
}

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $repo, // Abstract-a depend et
    ) {}

    public function getOrder(int $id): Order
    {
        $order = $this->repo->findById($id);
        if (!$order) throw new OrderNotFoundException($id);
        return $order;
    }
}

// ServiceProvider — binding
$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);

// Test — mock inject etmək asandır
$repo    = new InMemoryOrderRepository();
$service = new OrderService($repo);
```

```php
// ── LSP: Square-Rectangle anti-nümunəsi ─────────────────────────

// ❌ LSP pozulması
class Rectangle
{
    protected int $width;
    protected int $height;

    public function setWidth(int $w): void  { $this->width = $w; }
    public function setHeight(int $h): void { $this->height = $h; }
    public function area(): int             { return $this->width * $this->height; }
}

class Square extends Rectangle
{
    // Square-də width=height invariant-ı var
    public function setWidth(int $w): void
    {
        $this->width  = $w;
        $this->height = $w; // height-i də dəyişirik — parent kontraktı pozulur!
    }

    public function setHeight(int $h): void
    {
        $this->width  = $h;
        $this->height = $h;
    }
}

// Test — LSP: parent test-ləri child-da run et
function testRectangleArea(Rectangle $rect): void
{
    $rect->setWidth(5);
    $rect->setHeight(4);
    assert($rect->area() === 20, "Expected 20"); // Square-də FAIL! area = 16
}

testRectangleArea(new Rectangle()); // ✓
testRectangleArea(new Square());    // ✗ — LSP pozulub

// ✅ LSP — composition + shared interface
interface Shape
{
    public function area(): int;
}

class Rectangle implements Shape
{
    public function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {}

    public function area(): int { return $this->width * $this->height; }
}

class Square implements Shape
{
    public function __construct(private readonly int $side) {}

    public function area(): int { return $this->side * $this->side; }
}

// Hər ikisi Shape interface-ini implement edir — LSP qorunur
```

### Real-World Nümunə

```php
// Laravel-in OCP tətbiqi: Custom Cache Driver
// Larvel-in CacheManager class-ını dəyişmədən yeni driver əlavə etmək

// 1. Strategy implement et
class DynamoDbStore implements Store
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly string         $table,
    ) {}

    public function get($key): mixed
    {
        $result = $this->client->getItem([
            'TableName' => $this->table,
            'Key'       => ['cache_key' => ['S' => $key]],
        ]);
        return $result['Item']['value']['S'] ?? null;
    }

    public function put($key, $value, $seconds): bool
    {
        $this->client->putItem([
            'TableName' => $this->table,
            'Item'      => [
                'cache_key' => ['S' => $key],
                'value'     => ['S' => serialize($value)],
                'ttl'       => ['N' => (string)(time() + $seconds)],
            ],
        ]);
        return true;
    }
    // ... digər metodlar
}

// 2. ServiceProvider-da register — mövcud kod dəyişmir (OCP)
Cache::extend('dynamodb', function ($app) {
    return Cache::repository(
        new DynamoDbStore(
            $app->make(DynamoDbClient::class),
            config('cache.stores.dynamodb.table')
        )
    );
});

// 3. İstifadə
Cache::driver('dynamodb')->put('key', 'value', 300);
```

### Anti-Pattern Nümunəsi

```php
// YANLŞ: Over-engineering — sadə use case üçün həddən artıq abstraction
// 2 payment provider üçün 5 interface, 3 abstract class, 7 class
interface PaymentHandlerInterface {}
interface PaymentProcessorInterface {}
interface PaymentValidatorInterface {}
abstract class AbstractPaymentHandler implements PaymentHandlerInterface {}
abstract class AbstractPaymentProcessor extends AbstractPaymentHandler {}
class StripePaymentProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface {}
// ...

// Sadəcə Stripe istifadə olunur, heç vaxt başqası əlavə edilməyəcək
// YAGNI: Bu abstraction-a ehtiyac yoxdur

// DÜZGÜN: Sadə başla, mürəkkəblik lazım olanda əlavə et
class StripePaymentService
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function charge(int $cents, string $currency, string $token): PaymentResult
    {
        // Sadə, oxunaqlı, test edilə bilər
        $charge = $this->stripe->charges()->create([
            'amount'   => $cents,
            'currency' => $currency,
            'source'   => $token,
        ]);
        return PaymentResult::fromStripeCharge($charge);
    }
}
// PayPal lazım olanda refactor et — YAGNI
```

---

## Praktik Tapşırıqlar

1. Mövcud Laravel layihənizdəki ən böyük class-ı götürün — neçə "reason to change" var? SRP-ə görə bölün
2. Notification sistemini OCP-ə uyğun refactor edin: email, SMS, push — yeni channel əlavə etmək tək fayl yaratmaqla olsun
3. Interface olmadan yazılmış bir service-i unit test etməyə çalışın — niyə çətin olduğunu müşahidə edin
4. LSP test: Base class-ın test suite-ini child class-da run edin — uğursuz olarsa LSP pozulub
5. `new ClassName()` olan bir class tapın, constructor injection-a refactor edin, test yazın
6. Bir interface-i iki hissəyə bölün (ISP): implement edən class-ların yarısı boş metodlar saxlayırdısa
7. `if ($type === '...')` chain-i Strategy pattern-ə çevirin, yeni type tək class yazmaqla əlavə edin
8. Laravel ServiceProvider-da interface → implementation binding yazın, test-də mock inject edin

## Əlaqəli Mövzular
- [Dependency Injection](11-dependency-injection.md) — DIP-in implementasiyası
- [Strategy Pattern](05-strategy-pattern.md) — OCP tətbiqi
- [Factory Patterns](02-factory-patterns.md) — Object creation ilə SRP
- [Observer/Event Pattern](04-observer-event.md) — SRP + OCP birlikdə (event-driven)
