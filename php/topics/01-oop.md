# OOP (Object-Oriented Programming) (Junior)

## Mündəricat
1. [OOP nədir?](#oop-nədir)
2. [4 Əsas Prinsip](#4-əsas-prinsip)
3. [SOLID Prinsipləri](#solid-prinsipləri)
4. [Abstract Class vs Interface](#abstract-class-vs-interface)
5. [Trait-lər PHP-də](#trait-lər-php-də)
6. [Composition vs Inheritance](#composition-vs-inheritance)
7. [Late Static Binding](#late-static-binding)
8. [Magic Methods](#magic-methods)
9. [Type Hinting və Return Types](#type-hinting-və-return-types)
10. [PHP 8.1+ Yeniliklər](#php-81-yeniliklər)
11. [Laravel-də OOP İstifadəsi](#laraveldə-oop-istifadəsi)
12. [İntervyu Sualları](#intervyu-sualları)

---

## OOP nədir?

Object-Oriented Programming (OOP) — proqramlaşdırma paradiqmasıdır ki, burada proqram **obyektlər** ətrafında qurulur. Hər obyektin **xüsusiyyətləri** (properties) və **davranışları** (methods) olur. OOP real dünyanı modelləşdirməyə imkan verir.

PHP tam OOP dəstəkləyən dildir və Laravel framework tamamilə OOP prinsipləri üzərində qurulub.

*PHP tam OOP dəstəkləyən dildir və Laravel framework tamamilə OOP prins üçün kod nümunəsi:*
```php
// Ən sadə class nümunəsi
class User
{
    public string $name;
    public string $email;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function greet(): string
    {
        return "Salam, {$this->name}!";
    }
}

$user = new User('Orxan', 'orxan@example.com');
echo $user->greet(); // Salam, Orxan!
```

---

## 4 Əsas Prinsip

### 1. Encapsulation (İnkapsulyasiya)

Encapsulation — obyektin daxili məlumatlarını xaricdən gizlətmək və yalnız müəyyən metodlar vasitəsilə əlçatan etmək prinsipidir. Bu, məlumatın düzgün istifadəsini təmin edir.

**Access Modifiers:**
- `public` — hər yerdən əlçatandır
- `protected` — yalnız class daxilində və child class-larda
- `private` — yalnız class daxilində

*- `private` — yalnız class daxilində üçün kod nümunəsi:*
```php
// Bu kod encapsulation prinsipini bank hesabı nümunəsində göstərir
class BankAccount
{
    private float $balance = 0;
    private array $transactions = [];

    public function __construct(
        private readonly string $accountNumber,
        private readonly string $ownerName,
    ) {}

    // Public method - xaricdən əlçatan
    public function deposit(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Məbləğ müsbət olmalıdır.');
        }

        $this->balance += $amount;
        $this->recordTransaction('deposit', $amount);
    }

    public function withdraw(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Məbləğ müsbət olmalıdır.');
        }

        if ($amount > $this->balance) {
            throw new RuntimeException('Balansda kifayət qədər vəsait yoxdur.');
        }

        $this->balance -= $amount;
        $this->recordTransaction('withdrawal', $amount);
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    // Private method - yalnız daxildən
    private function recordTransaction(string $type, float $amount): void
    {
        $this->transactions[] = [
            'type' => $type,
            'amount' => $amount,
            'date' => now(),
            'balance_after' => $this->balance,
        ];
    }

    public function getTransactionHistory(): array
    {
        return $this->transactions;
    }
}

// İstifadə
$account = new BankAccount('AZ1234567890', 'Orxan');
$account->deposit(1000);
$account->withdraw(250);
echo $account->getBalance(); // 750

// Bu XƏTA verəcək - private property-yə birbaşa müraciət
// $account->balance = 999999; // Error!
```

**Laravel-də Encapsulation nümunəsi:**

```php
// app/Models/Order.php
class Order extends Model
{
    // Fillable ilə mass assignment-dan qorunma (encapsulation)
    protected $fillable = ['user_id', 'status', 'total'];

    // Hidden ilə serialization zamanı gizlətmə
    protected $hidden = ['internal_notes'];

    // Status dəyişdirmə yalnız method vasitəsilə
    public function markAsPaid(): void
    {
        if ($this->status !== 'pending') {
            throw new DomainException('Yalnız gözləyən sifarişlər ödənilə bilər.');
        }

        $this->status = 'paid';
        $this->paid_at = now();
        $this->save();

        event(new OrderPaid($this));
    }

    public function cancel(string $reason): void
    {
        if (in_array($this->status, ['shipped', 'delivered'])) {
            throw new DomainException('Göndərilmiş sifariş ləğv edilə bilməz.');
        }

        $this->status = 'cancelled';
        $this->cancellation_reason = $reason;
        $this->cancelled_at = now();
        $this->save();

        event(new OrderCancelled($this));
    }
}
```

### 2. Inheritance (Varislik)

Inheritance — bir class-ın başqa bir class-dan xüsusiyyət və metodları miras alması prinsipidir. Bu, kod təkrarını azaldır.

*Inheritance — bir class-ın başqa bir class-dan xüsusiyyət və metodları üçün kod nümunəsi:*
```php
// Base class
class Vehicle
{
    public function __construct(
        protected string $brand,
        protected string $model,
        protected int $year,
        protected float $fuelLevel = 100,
    ) {}

    public function start(): string
    {
        if ($this->fuelLevel <= 0) {
            throw new RuntimeException('Yanacaq yoxdur!');
        }
        return "{$this->brand} {$this->model} işə düşdü.";
    }

    public function stop(): string
    {
        return "{$this->brand} {$this->model} dayandırıldı.";
    }

    public function refuel(float $amount): void
    {
        $this->fuelLevel = min(100, $this->fuelLevel + $amount);
    }

    public function getInfo(): string
    {
        return "{$this->year} {$this->brand} {$this->model}";
    }
}

// Child class - Car
class Car extends Vehicle
{
    public function __construct(
        string $brand,
        string $model,
        int $year,
        private int $numberOfDoors = 4,
    ) {
        parent::__construct($brand, $model, $year);
    }

    public function openTrunk(): string
    {
        return "{$this->getInfo()} - baqaj açıldı.";
    }
}

// Child class - Truck
class Truck extends Vehicle
{
    public function __construct(
        string $brand,
        string $model,
        int $year,
        private float $loadCapacity,
    ) {
        parent::__construct($brand, $model, $year);
    }

    public function loadCargo(float $weight): string
    {
        if ($weight > $this->loadCapacity) {
            throw new RuntimeException("Yük tutumu aşılıb! Maks: {$this->loadCapacity} ton");
        }
        return "{$this->getInfo()} - {$weight} ton yükləndi.";
    }
}

// İstifadə
$car = new Car('BMW', 'X5', 2024, 4);
echo $car->start();      // BMW X5 işə düşdü.
echo $car->openTrunk();  // 2024 BMW X5 - baqaj açıldı.

$truck = new Truck('Mercedes', 'Actros', 2023, 25.0);
echo $truck->loadCargo(15); // 2023 Mercedes Actros - 15 ton yükləndi.
```

**Laravel-də Inheritance:**

```php
// Laravel-in öz inheritance strukturu
// Illuminate\Database\Eloquent\Model -> sizin Model-lər

// Base Model yaratmaq
abstract class BaseModel extends Model
{
    // Bütün model-lər üçün ümumi funksionallıq
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}

class Product extends BaseModel
{
    // BaseModel-in bütün funksionallığını miras alır
    // + öz xüsusiyyətləri

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

class Article extends BaseModel
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

// Base Controller
abstract class BaseApiController extends Controller
{
    protected function successResponse(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    protected function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
        ], $status);
    }
}

class ProductController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $products = Product::active()->recent()->paginate(20);
        return $this->successResponse($products);
    }
}
```

### 3. Polymorphism (Polimorfizm)

Polymorphism — eyni interfeys və ya base class-dan istifadə edərək fərqli davranışlar göstərmək qabiliyyətidir. İki növü var: compile-time (method overloading) və runtime (method overriding).

*Polymorphism — eyni interfeys və ya base class-dan istifadə edərək fər üçün kod nümunəsi:*
```php
// Interface ilə polymorphism
interface PaymentGateway
{
    public function charge(float $amount, array $options = []): PaymentResult;
    public function refund(string $transactionId, float $amount): RefundResult;
    public function getTransactionStatus(string $transactionId): string;
}

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $transactionId,
        public readonly string $message,
    ) {}
}

class RefundResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $refundId,
        public readonly string $message,
    ) {}
}

// Stripe implementation
class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function charge(float $amount, array $options = []): PaymentResult
    {
        // Stripe API ilə ödəniş
        $stripe = new \Stripe\StripeClient($this->apiKey);

        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => (int) ($amount * 100), // cent-ə çevir
            'currency' => $options['currency'] ?? 'usd',
            'payment_method' => $options['payment_method'],
            'confirm' => true,
        ]);

        return new PaymentResult(
            success: $paymentIntent->status === 'succeeded',
            transactionId: $paymentIntent->id,
            message: 'Ödəniş Stripe vasitəsilə həyata keçirildi.',
        );
    }

    public function refund(string $transactionId, float $amount): RefundResult
    {
        // Stripe refund logic
        return new RefundResult(true, 'ref_' . uniqid(), 'Geri qaytarma uğurlu oldu.');
    }

    public function getTransactionStatus(string $transactionId): string
    {
        return 'completed';
    }
}

// PayPal implementation
class PayPalGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function charge(float $amount, array $options = []): PaymentResult
    {
        // PayPal API ilə ödəniş - tamamilə fərqli implementasiya
        return new PaymentResult(
            success: true,
            transactionId: 'pp_' . uniqid(),
            message: 'Ödəniş PayPal vasitəsilə həyata keçirildi.',
        );
    }

    public function refund(string $transactionId, float $amount): RefundResult
    {
        return new RefundResult(true, 'ppref_' . uniqid(), 'PayPal geri qaytarma uğurlu.');
    }

    public function getTransactionStatus(string $transactionId): string
    {
        return 'completed';
    }
}

// İstifadə - polymorphism sayəsində hansı gateway olduğu fərq etmir
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function processPayment(Order $order): PaymentResult
    {
        $result = $this->gateway->charge($order->total, [
            'currency' => $order->currency,
            'payment_method' => $order->payment_method_id,
        ]);

        if ($result->success) {
            $order->markAsPaid($result->transactionId);
        }

        return $result;
    }
}

// Laravel Service Container ilə binding
// AppServiceProvider.php
public function register(): void
{
    $this->app->bind(PaymentGateway::class, function ($app) {
        return match(config('payment.default')) {
            'stripe' => new StripeGateway(config('payment.stripe.key')),
            'paypal' => new PayPalGateway(
                config('payment.paypal.client_id'),
                config('payment.paypal.secret'),
            ),
            default => throw new RuntimeException('Bilinməyən ödəniş gateway-i'),
        };
    });
}
```

### 4. Abstraction (Abstraksiya)

Abstraction — mürəkkəb implementasiya detallarını gizlədərək yalnız vacib funksionallığı göstərmək prinsipidir.

*Abstraction — mürəkkəb implementasiya detallarını gizlədərək yalnız va üçün kod nümunəsi:*
```php
// Abstract class ilə abstraction
abstract class NotificationChannel
{
    // Template method pattern - əsas axış müəyyəndir, detallar alt class-lara buraxılır
    public function send(Notifiable $user, Notification $notification): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException(static::class . ' hal-hazırda əlçatan deyil.');
        }

        $message = $this->formatMessage($notification);
        $this->deliver($user, $message);
        $this->logNotification($user, $notification);
    }

    // Concrete method - bütün child-lar üçün eyni
    private function logNotification(Notifiable $user, Notification $notification): void
    {
        NotificationLog::create([
            'user_id' => $user->id,
            'channel' => static::class,
            'notification_type' => get_class($notification),
            'sent_at' => now(),
        ]);
    }

    // Abstract methods - hər child class öz implementasiyasını verməlidir
    abstract protected function formatMessage(Notification $notification): string;
    abstract protected function deliver(Notifiable $user, string $message): void;
    abstract protected function isAvailable(): bool;
}

class EmailChannel extends NotificationChannel
{
    protected function formatMessage(Notification $notification): string
    {
        return $notification->toMail()->render();
    }

    protected function deliver(Notifiable $user, string $message): void
    {
        Mail::to($user->email)->send(new RawMessage($message));
    }

    protected function isAvailable(): bool
    {
        return config('mail.mailers.smtp.host') !== null;
    }
}

class SmsChannel extends NotificationChannel
{
    public function __construct(
        private readonly TwilioClient $twilio,
    ) {}

    protected function formatMessage(Notification $notification): string
    {
        return $notification->toSms();
    }

    protected function deliver(Notifiable $user, string $message): void
    {
        $this->twilio->messages->create($user->phone, [
            'from' => config('services.twilio.from'),
            'body' => $message,
        ]);
    }

    protected function isAvailable(): bool
    {
        return config('services.twilio.sid') !== null;
    }
}

class TelegramChannel extends NotificationChannel
{
    protected function formatMessage(Notification $notification): string
    {
        return $notification->toTelegram();
    }

    protected function deliver(Notifiable $user, string $message): void
    {
        Http::post("https://api.telegram.org/bot" . config('services.telegram.token') . "/sendMessage", [
            'chat_id' => $user->telegram_chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }

    protected function isAvailable(): bool
    {
        return config('services.telegram.token') !== null;
    }
}
```

---

## SOLID Prinsipləri

### S - Single Responsibility Principle (SRP)

Hər class-ın **yalnız bir məsuliyyəti** olmalıdır. Dəyişmək üçün yalnız bir səbəbi olmalıdır.

*Hər class-ın **yalnız bir məsuliyyəti** olmalıdır. Dəyişmək üçün yalnı üçün kod nümunəsi:*
```php
// YANLIŞ - bir class çox iş görür
class UserManager
{
    public function createUser(array $data): User
    {
        // Validation
        if (empty($data['email'])) {
            throw new ValidationException('Email tələb olunur.');
        }

        // User yaratma
        $user = User::create($data);

        // Email göndərmə
        Mail::to($user->email)->send(new WelcomeMail($user));

        // Log yazma
        Log::info("Yeni istifadəçi yaradıldı: {$user->id}");

        // Cache yeniləmə
        Cache::forget('users_count');

        return $user;
    }
}

// DOĞRU - hər class bir iş görür
class CreateUserAction
{
    public function __construct(
        private readonly UserValidator $validator,
        private readonly UserRepository $repository,
        private readonly UserNotificationService $notificationService,
        private readonly UserCacheService $cacheService,
    ) {}

    public function execute(CreateUserDTO $dto): User
    {
        $this->validator->validate($dto);

        $user = $this->repository->create($dto);

        $this->notificationService->sendWelcomeEmail($user);
        $this->cacheService->invalidate();

        return $user;
    }
}

class UserValidator
{
    public function validate(CreateUserDTO $dto): void
    {
        if (empty($dto->email)) {
            throw new ValidationException('Email tələb olunur.');
        }

        if (User::where('email', $dto->email)->exists()) {
            throw new ValidationException('Bu email artıq istifadə olunur.');
        }
    }
}

class UserRepository
{
    public function create(CreateUserDTO $dto): User
    {
        return User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
        ]);
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }
}

class UserNotificationService
{
    public function sendWelcomeEmail(User $user): void
    {
        Mail::to($user->email)->send(new WelcomeMail($user));
    }
}

class UserCacheService
{
    public function invalidate(): void
    {
        Cache::forget('users_count');
        Cache::forget('users_list');
    }
}
```

### O - Open/Closed Principle (OCP)

Class-lar **genişlənmə üçün açıq**, lakin **dəyişiklik üçün bağlı** olmalıdır.

*Class-lar **genişlənmə üçün açıq**, lakin **dəyişiklik üçün bağlı** ol üçün kod nümunəsi:*
```php
// YANLIŞ - yeni discount növü əlavə etmək üçün class-ı dəyişməliyik
class DiscountCalculator
{
    public function calculate(Order $order): float
    {
        if ($order->type === 'regular') {
            return $order->total * 0.05;
        } elseif ($order->type === 'premium') {
            return $order->total * 0.10;
        } elseif ($order->type === 'vip') {
            return $order->total * 0.20;
        }
        // Yeni tip əlavə etmək üçün bu class-ı dəyişməliyik!
        return 0;
    }
}

// DOĞRU - interface ilə genişlənmə
interface DiscountStrategy
{
    public function calculate(Order $order): float;
    public function supports(Order $order): bool;
}

class RegularDiscount implements DiscountStrategy
{
    public function calculate(Order $order): float
    {
        return $order->total * 0.05;
    }

    public function supports(Order $order): bool
    {
        return $order->customer_type === 'regular';
    }
}

class PremiumDiscount implements DiscountStrategy
{
    public function calculate(Order $order): float
    {
        return $order->total * 0.10;
    }

    public function supports(Order $order): bool
    {
        return $order->customer_type === 'premium';
    }
}

class VipDiscount implements DiscountStrategy
{
    public function calculate(Order $order): float
    {
        $baseDiscount = $order->total * 0.20;
        $loyaltyBonus = $order->customer->years_active > 5 ? $order->total * 0.05 : 0;
        return $baseDiscount + $loyaltyBonus;
    }

    public function supports(Order $order): bool
    {
        return $order->customer_type === 'vip';
    }
}

// Yeni discount əlavə etmək üçün sadəcə yeni class yaradırıq - heç nəyi dəyişmirik!
class SeasonalDiscount implements DiscountStrategy
{
    public function calculate(Order $order): float
    {
        return $order->total * 0.15;
    }

    public function supports(Order $order): bool
    {
        $month = now()->month;
        return in_array($month, [11, 12]); // Noyabr-Dekabr kampaniyası
    }
}

// Calculator artıq dəyişməyəcək
class DiscountCalculator
{
    /** @param DiscountStrategy[] $strategies */
    public function __construct(
        private readonly array $strategies,
    ) {}

    public function calculate(Order $order): float
    {
        $totalDiscount = 0;

        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($order)) {
                $totalDiscount += $strategy->calculate($order);
            }
        }

        // Discount total-dan çox ola bilməz
        return min($totalDiscount, $order->total);
    }
}

// Service Provider-da qeydiyyat
$this->app->bind(DiscountCalculator::class, function () {
    return new DiscountCalculator([
        new RegularDiscount(),
        new PremiumDiscount(),
        new VipDiscount(),
        new SeasonalDiscount(),
    ]);
});
```

### L - Liskov Substitution Principle (LSP)

Alt class-lar **üst class-ın yerinə istifadə oluna bilməlidir** və proqramın düzgün işləməsini pozmamalıdır.

*Alt class-lar **üst class-ın yerinə istifadə oluna bilməlidir** və pro üçün kod nümunəsi:*
```php
// YANLIŞ - LSP pozulur
class Bird
{
    public function fly(): string
    {
        return 'Uçuram!';
    }
}

class Penguin extends Bird
{
    public function fly(): string
    {
        // Pinqvin uça bilməz! LSP pozulur.
        throw new RuntimeException('Pinqvinlər uça bilməz!');
    }
}

// DOĞRU - düzgün abstraksiya
interface Bird
{
    public function move(): string;
    public function getName(): string;
}

interface FlyingBird extends Bird
{
    public function fly(): string;
}

interface SwimmingBird extends Bird
{
    public function swim(): string;
}

class Eagle implements FlyingBird
{
    public function move(): string
    {
        return $this->fly();
    }

    public function fly(): string
    {
        return 'Qartal uçur!';
    }

    public function getName(): string
    {
        return 'Qartal';
    }
}

class Penguin implements SwimmingBird
{
    public function move(): string
    {
        return $this->swim();
    }

    public function swim(): string
    {
        return 'Pinqvin üzür!';
    }

    public function getName(): string
    {
        return 'Pinqvin';
    }
}

// Laravel-də LSP nümunəsi - düzgün repository pattern
interface UserRepositoryInterface
{
    /** @return Collection<User> */
    public function all(): Collection;
    public function findById(int $id): ?User;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;
}

class EloquentUserRepository implements UserRepositoryInterface
{
    public function all(): Collection
    {
        return User::all();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user->fresh();
    }

    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }
}

class CachedUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly EloquentUserRepository $repository,
        private readonly int $ttl = 3600,
    ) {}

    public function all(): Collection
    {
        return Cache::remember('users.all', $this->ttl, fn () => $this->repository->all());
    }

    public function findById(int $id): ?User
    {
        return Cache::remember("users.{$id}", $this->ttl, fn () => $this->repository->findById($id));
    }

    public function create(array $data): User
    {
        $user = $this->repository->create($data);
        Cache::forget('users.all');
        return $user;
    }

    public function update(int $id, array $data): User
    {
        $user = $this->repository->update($id, $data);
        Cache::forget("users.{$id}");
        Cache::forget('users.all');
        return $user;
    }

    public function delete(int $id): bool
    {
        $result = $this->repository->delete($id);
        Cache::forget("users.{$id}");
        Cache::forget('users.all');
        return $result;
    }
}
```

### I - Interface Segregation Principle (ISP)

Client-lər istifadə etmədikləri interface-lərə **asılı olmamalıdır**. Böyük interface-lər kiçik, xüsusi interface-lərə bölünməlidir.

*Client-lər istifadə etmədikləri interface-lərə **asılı olmamalıdır**.  üçün kod nümunəsi:*
```php
// YANLIŞ - çox böyük interface
interface WorkerInterface
{
    public function work(): void;
    public function eat(): void;
    public function sleep(): void;
    public function code(): void;
    public function design(): void;
    public function managePeople(): void;
}

// Robot bu interface-i implement edə bilməz - yemək yemir, yatmır!

// DOĞRU - kiçik interface-lər
interface Workable
{
    public function work(): void;
}

interface Feedable
{
    public function eat(): void;
}

interface Sleepable
{
    public function sleep(): void;
}

interface Codeable
{
    public function code(): void;
}

interface Designable
{
    public function design(): void;
}

interface Manageable
{
    public function managePeople(): void;
}

class Developer implements Workable, Feedable, Sleepable, Codeable
{
    public function work(): void { /* ... */ }
    public function eat(): void { /* ... */ }
    public function sleep(): void { /* ... */ }
    public function code(): void { /* ... */ }
}

class Robot implements Workable, Codeable
{
    public function work(): void { /* ... */ }
    public function code(): void { /* ... */ }
}

// Laravel-də ISP nümunəsi
interface Searchable
{
    public function toSearchableArray(): array;
    public function getSearchableFields(): array;
}

interface Exportable
{
    public function toExportArray(): array;
    public function getExportHeaders(): array;
}

interface Auditable
{
    public function getAuditFields(): array;
    public function getLastAuditEntry(): ?AuditLog;
}

// Product bütün interface-ləri implement edir
class Product extends Model implements Searchable, Exportable, Auditable
{
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    public function getSearchableFields(): array
    {
        return ['name', 'description', 'sku'];
    }

    public function toExportArray(): array
    {
        return $this->only(['id', 'name', 'price', 'stock', 'created_at']);
    }

    public function getExportHeaders(): array
    {
        return ['ID', 'Ad', 'Qiymət', 'Stok', 'Yaradılma tarixi'];
    }

    public function getAuditFields(): array
    {
        return ['name', 'price', 'stock'];
    }

    public function getLastAuditEntry(): ?AuditLog
    {
        return $this->auditLogs()->latest()->first();
    }
}

// Setting yalnız Auditable-dır
class Setting extends Model implements Auditable
{
    public function getAuditFields(): array
    {
        return ['key', 'value'];
    }

    public function getLastAuditEntry(): ?AuditLog
    {
        return $this->auditLogs()->latest()->first();
    }
}
```

### D - Dependency Inversion Principle (DIP)

Yuxarı səviyyəli modullar aşağı səviyyəli modullara **asılı olmamalıdır**. Hər ikisi **abstraksiyalara** asılı olmalıdır.

*Yuxarı səviyyəli modullar aşağı səviyyəli modullara **asılı olmamalıdı üçün kod nümunəsi:*
```php
// YANLIŞ - yuxarı səviyyəli class konkret class-a asılıdır
class OrderService
{
    private MySqlOrderRepository $repository; // Konkret implementasiya!
    private StripePaymentService $payment;    // Konkret implementasiya!

    public function __construct()
    {
        $this->repository = new MySqlOrderRepository(); // new ilə yaradılır!
        $this->payment = new StripePaymentService();
    }
}

// DOĞRU - abstraksiyalara asılıdır
interface OrderRepositoryInterface
{
    public function save(Order $order): Order;
    public function findById(int $id): ?Order;
    public function findByUserId(int $userId): Collection;
}

interface PaymentServiceInterface
{
    public function charge(float $amount, string $paymentMethod): PaymentResult;
    public function refund(string $transactionId): RefundResult;
}

class OrderService
{
    // Interface-lərə asılıdır, konkret implementasiyalara yox
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly PaymentServiceInterface $payment,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function placeOrder(PlaceOrderDTO $dto): Order
    {
        $order = new Order(
            userId: $dto->userId,
            items: $dto->items,
            total: $dto->calculateTotal(),
        );

        $paymentResult = $this->payment->charge(
            $order->total,
            $dto->paymentMethod,
        );

        if (!$paymentResult->success) {
            throw new PaymentFailedException($paymentResult->message);
        }

        $order->transactionId = $paymentResult->transactionId;
        $savedOrder = $this->repository->save($order);

        $this->eventDispatcher->dispatch(new OrderPlaced($savedOrder));

        return $savedOrder;
    }
}

// Service Provider-da binding
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(PaymentServiceInterface::class, StripePaymentService::class);
        $this->app->bind(EventDispatcherInterface::class, LaravelEventDispatcher::class);
    }
}
```

---

## Abstract Class vs Interface

| Xüsusiyyət | Abstract Class | Interface |
|---|---|---|
| Metodların implementasiyası | Ola bilər (concrete + abstract) | PHP 8.0+ default methods, əks halda yalnız imza |
| Properties | Ola bilər | Yalnız constants |
| Constructor | Ola bilər | Yoxdur |
| Multiple inheritance | Yox (tək extends) | Bəli (çoxlu implements) |
| Access modifiers | public, protected, private | Yalnız public |
| İstifadə | "is-a" əlaqə, ümumi davranış | "can-do" müqavilə |

*həll yanaşmasını üçün kod nümunəsi:*
```php
// Abstract class - ortaq funksionallıq paylaşmaq üçün
abstract class CacheStore
{
    // Concrete method - bütün child-lar üçün eyni
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    // Abstract methods - hər implementation öz versiyasını verir
    abstract public function get(string $key): mixed;
    abstract public function put(string $key, mixed $value, int $ttl): bool;
    abstract public function delete(string $key): bool;
    abstract public function flush(): bool;
}

class RedisCacheStore extends CacheStore
{
    public function __construct(private readonly Redis $redis) {}

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : null;
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->redis->setex($key, $ttl, serialize($value));
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function flush(): bool
    {
        return $this->redis->flushDB();
    }
}

// Interface - müqavilə təyin etmək üçün
interface Renderable
{
    public function render(): string;
}

interface Cacheable
{
    public function getCacheKey(): string;
    public function getCacheTTL(): int;
}

// Bir class həm abstract class-dan miras ala, həm də interface implement edə bilər
class HtmlWidget extends CacheStore implements Renderable, Cacheable
{
    // ...
}
```

**Nə vaxt hansını istifadə etməli:**
- **Abstract class**: Ortaq kod/davranış paylaşmaq istədikdə, state (properties) lazım olduqda
- **Interface**: Müqavilə/kontrakt təyin etmək istədikdə, çoxlu implementation olacaqda, type hinting üçün

---

## Trait-lər PHP-də

Trait-lər — kod təkrarını azaltmaq üçün istifadə olunan mexanizmdir. PHP-nin multiple inheritance-ı dəstəkləmədiyini nəzərə alaraq, trait-lər ortaq funksionallığı class-lar arasında paylaşmağa imkan verir.

*Trait-lər — kod təkrarını azaltmaq üçün istifadə olunan mexanizmdir. P üçün kod nümunəsi:*
```php
// Əsas trait nümunəsi
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function findByUuid(string $uuid): ?static
    {
        return static::where('uuid', $uuid)->first();
    }
}

trait SoftDeletesWithUser
{
    use SoftDeletes;

    protected static function bootSoftDeletesWithUser(): void
    {
        static::deleting(function ($model) {
            if (auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }
}

trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            $model->slug = Str::slug($model->{$model->getSlugSource()});
        });
    }

    public function getSlugSource(): string
    {
        return 'name'; // Default, override edə bilər
    }

    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }
}

// Trait-ləri istifadə edən Model
class Product extends Model
{
    use HasUuid, SoftDeletesWithUser, HasSlug;

    public function getSlugSource(): string
    {
        return 'title'; // Override
    }
}

// Trait conflict resolution
trait A
{
    public function hello(): string
    {
        return 'A-dan salam';
    }
}

trait B
{
    public function hello(): string
    {
        return 'B-dən salam';
    }
}

class MyClass
{
    use A, B {
        A::hello insteadof B; // A-nın hello metodunu istifadə et
        B::hello as helloFromB; // B-nin hello metoduna yeni ad ver
    }
}

$obj = new MyClass();
echo $obj->hello();       // A-dan salam
echo $obj->helloFromB();  // B-dən salam

// Laravel-in öz trait-ləri
// HasFactory, Notifiable, SoftDeletes, HasApiTokens, Searchable ...
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
}
```

---

## Composition vs Inheritance

**Composition** — bir class-ın başqa class-ı öz property-si kimi saxlamasıdır. "Has-a" əlaqəsi.
**Inheritance** — bir class-ın başqa class-dan miras almasıdır. "Is-a" əlaqəsi.

**Qızıl qayda**: "Favor composition over inheritance" — mümkün qədər composition istifadə edin.

***Qızıl qayda**: "Favor composition over inheritance" — mümkün qədər c üçün kod nümunəsi:*
```php
// Inheritance ilə (problem yarada bilər)
class Animal
{
    public function breathe(): string { return 'Nəfəs alıram'; }
}

class Dog extends Animal
{
    public function bark(): string { return 'Hav hav!'; }
}

class RobotDog extends Dog
{
    // Problem! Robot nəfəs almır amma Animal-dan miras alır
    public function breathe(): string
    {
        throw new RuntimeException('Robot nəfəs almır!'); // LSP pozulur!
    }
}

// Composition ilə (daha yaxşı)
interface Breathable
{
    public function breathe(): string;
}

interface Barkable
{
    public function bark(): string;
}

class LungBreathing implements Breathable
{
    public function breathe(): string { return 'Ağciyərlərlə nəfəs alıram'; }
}

class DogBarking implements Barkable
{
    public function bark(): string { return 'Hav hav!'; }
}

class MechanicalBarking implements Barkable
{
    public function bark(): string { return '*Elektron hav hav*'; }
}

class RealDog
{
    public function __construct(
        private readonly Breathable $breathing,
        private readonly Barkable $barking,
    ) {}

    public function breathe(): string { return $this->breathing->breathe(); }
    public function bark(): string { return $this->barking->bark(); }
}

class RobotDog
{
    public function __construct(
        private readonly Barkable $barking,
        private readonly float $batteryLevel = 100,
    ) {}

    public function bark(): string { return $this->barking->bark(); }
    public function charge(): void { /* ... */ }
}

// İstifadə
$realDog = new RealDog(new LungBreathing(), new DogBarking());
$robotDog = new RobotDog(new MechanicalBarking());

// Laravel-də Composition nümunəsi
class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepo,      // Composition
        private readonly PaymentService $paymentService,   // Composition
        private readonly InventoryService $inventoryService, // Composition
        private readonly NotificationService $notifier,     // Composition
    ) {}

    public function placeOrder(PlaceOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $order = $this->orderRepo->create($dto);
            $this->inventoryService->reserve($order->items);
            $this->paymentService->charge($order);
            $this->notifier->orderPlaced($order);

            return $order;
        });
    }
}
```

---

## Late Static Binding

Late Static Binding — PHP-də `static::` keyword-ü istifadə etməklə metod çağırışının runtime-da həll edilməsidir. `self::` compile-time-da hansı class-da yazılıbsa ora aid olur, `static::` isə runtime-da faktiki class-a aid olur.

*Late Static Binding — PHP-də `static::` keyword-ü istifadə etməklə met üçün kod nümunəsi:*
```php
// Bu kod self:: və static:: arasındakı Late Static Binding fərqini göstərir
class ParentModel
{
    protected static string $table = 'parents';

    public static function getTableWithSelf(): string
    {
        return self::$table; // Həmişə 'parents' qaytaracaq
    }

    public static function getTableWithStatic(): string
    {
        return static::$table; // Runtime-da həll olunacaq
    }

    public static function create(array $data): static
    {
        // static return type - child class qaytaracaq
        $instance = new static(); // new static - child class yaradacaq
        // ...
        return $instance;
    }
}

class ChildModel extends ParentModel
{
    protected static string $table = 'children';
}

echo ParentModel::getTableWithSelf();   // 'parents'
echo ChildModel::getTableWithSelf();    // 'parents' (!) - self həmişə ParentModel-ə baxır

echo ParentModel::getTableWithStatic(); // 'parents'
echo ChildModel::getTableWithStatic();  // 'children' - static child-a baxır

// Laravel-də Late Static Binding çox istifadə olunur
// Model::query(), Model::create(), Model::find() hamısı static:: istifadə edir
// Məsələn, User::find(1) düzgün User obyekti qaytarır
```

---

## Magic Methods

PHP-nin xüsusi __method-ları avtomatik müəyyən hallarda çağırılır.

*PHP-nin xüsusi __method-ları avtomatik müəyyən hallarda çağırılır üçün kod nümunəsi:*
```php
// Bu kod PHP magic metodlarının istifadəsini göstərir
class MagicExample
{
    private array $data = [];
    private array $relations = [];

    // Constructor - obyekt yaradılanda çağırılır
    public function __construct(
        private string $name,
        private int $age,
    ) {
        // ...
    }

    // Destructor - obyekt məhv olanda çağırılır
    public function __destruct()
    {
        // Resursları azad et
    }

    // Mövcud olmayan property oxunanda
    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        // Relation lazy loading (Laravel kimi)
        if (method_exists($this, $name)) {
            return $this->relations[$name] ??= $this->$name();
        }

        throw new RuntimeException("Property '{$name}' mövcud deyil.");
    }

    // Mövcud olmayan property-yə yazanda
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    // isset() və ya empty() ilə yoxlayanda
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    // unset() çağırılanda
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    // Mövcud olmayan method çağırılanda
    public function __call(string $name, array $arguments): mixed
    {
        // Scope-lar (Laravel Query Builder kimi)
        if (str_starts_with($name, 'scope')) {
            $scope = lcfirst(substr($name, 5));
            return $this->applyScope($scope, $arguments);
        }

        throw new BadMethodCallException("Method '{$name}' mövcud deyil.");
    }

    // Static mövcud olmayan method çağırılanda
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return (new static())->$name(...$arguments);
    }

    // Obyekt string kimi istifadə olanda (echo, string concatenation)
    public function __toString(): string
    {
        return "{$this->name} ({$this->age} yaş)";
    }

    // Obyekt funksiya kimi çağırılanda $obj()
    public function __invoke(string $greeting): string
    {
        return "{$greeting}, {$this->name}!";
    }

    // serialize() zamanı
    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'age' => $this->age,
            'data' => $this->data,
        ];
    }

    // unserialize() zamanı
    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->age = $data['age'];
        $this->data = $data['data'];
    }

    // clone zamanı
    public function __clone(): void
    {
        // Deep copy lazım olan property-ləri kopyala
        $this->data = $this->data; // shallow copy
    }

    // var_dump() zamanı
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'age' => $this->age,
            'data_count' => count($this->data),
        ];
    }
}

$obj = new MagicExample('Orxan', 30);
echo $obj;                    // __toString: "Orxan (30 yaş)"
echo $obj('Salam');           // __invoke: "Salam, Orxan!"
$obj->email = 'test@x.com';  // __set
echo $obj->email;             // __get: "test@x.com"
isset($obj->email);           // __isset: true
```

---

## Type Hinting və Return Types

*Type Hinting və Return Types üçün kod nümunəsi:*
```php
// Scalar types
function add(int $a, int $b): int
{
    return $a + $b;
}

// Nullable types
function findUser(int $id): ?User
{
    return User::find($id); // User və ya null qaytara bilər
}

// Union types (PHP 8.0+)
function processInput(string|int|float $input): string
{
    return (string) $input;
}

// Intersection types (PHP 8.1+)
function processEntity(Countable&Iterator $collection): void
{
    // $collection həm Countable, həm Iterator olmalıdır
    echo count($collection);
    foreach ($collection as $item) {
        // ...
    }
}

// DNF types (PHP 8.2+) - Disjunctive Normal Form
function handle((Countable&Iterator)|null $collection): void
{
    // (Countable AND Iterator) OR null
}

// void return
function logMessage(string $message): void
{
    Log::info($message);
    // Heç nə qaytarmır
}

// never return (PHP 8.1+) - function heç vaxt normal bitmir
function throwError(string $message): never
{
    throw new RuntimeException($message);
    // Bu nöqtəyə heç vaxt çatmayacaq
}

// self, static, parent return types
class Builder
{
    public function where(string $column, mixed $value): static
    {
        // static - child class-ın tipini qaytarır
        $this->conditions[] = [$column, $value];
        return $this;
    }
}

// Class type hints
function sendNotification(User $user, Notification $notification): void
{
    // ...
}

// Interface type hints
function processPayment(PaymentGateway $gateway, float $amount): PaymentResult
{
    return $gateway->charge($amount);
}

// array shape (DocBlock ilə, PHP native dəstəkləmir)
/**
 * @param array{name: string, email: string, age?: int} $data
 * @return array{success: bool, user: User}
 */
function createUser(array $data): array
{
    // ...
}
```

---

## PHP 8.1+ Yeniliklər

### Readonly Properties

*Readonly Properties üçün kod nümunəsi:*
```php
// Bu kod PHP 8.1 readonly properties xüsusiyyətini göstərir
class UserProfile
{
    // Readonly - yalnız bir dəfə təyin oluna bilər (constructor-da)
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}

$profile = new UserProfile(1, 'Orxan', 'orxan@example.com', new DateTimeImmutable());
echo $profile->name; // 'Orxan' - oxumaq olar
// $profile->name = 'Yeni Ad'; // Error! Readonly property dəyişdirilə bilməz

// PHP 8.2+ readonly class
readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Valyutalar uyğun deyil.');
        }
        return new self($this->amount + $other->amount, $this->currency);
    }
}
```

### Enums

*Enums üçün kod nümunəsi:*
```php
// Basic enum
enum OrderStatus
{
    case Pending;
    case Processing;
    case Shipped;
    case Delivered;
    case Cancelled;
}

// Backed enum (string)
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    // Enum-da method ola bilər
    public function label(): string
    {
        return match($this) {
            self::Pending => 'Gözləyir',
            self::Paid => 'Ödənilib',
            self::Failed => 'Uğursuz',
            self::Refunded => 'Qaytarılıb',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Paid => 'green',
            self::Failed => 'red',
            self::Refunded => 'blue',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::Pending => in_array($newStatus, [self::Paid, self::Failed]),
            self::Paid => in_array($newStatus, [self::Refunded]),
            self::Failed => in_array($newStatus, [self::Pending]),
            self::Refunded => false,
        };
    }
}

// Backed enum (int)
enum UserRole: int
{
    case Admin = 1;
    case Editor = 2;
    case Author = 3;
    case Viewer = 4;

    public function permissions(): array
    {
        return match($this) {
            self::Admin => ['*'],
            self::Editor => ['read', 'write', 'edit', 'delete'],
            self::Author => ['read', 'write'],
            self::Viewer => ['read'],
        };
    }
}

// Enum ilə interface
interface HasColor
{
    public function color(): string;
}

enum Priority: int implements HasColor
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
    case Critical = 4;

    public function color(): string
    {
        return match($this) {
            self::Low => '#00FF00',
            self::Medium => '#FFFF00',
            self::High => '#FF8800',
            self::Critical => '#FF0000',
        };
    }
}

// Laravel Model-də enum istifadəsi
class Order extends Model
{
    protected $casts = [
        'status' => PaymentStatus::class,
        'priority' => Priority::class,
    ];
}

// İstifadə
$order = Order::find(1);
echo $order->status->label(); // 'Ödənilib'
echo $order->status->color(); // 'green'

// from() və tryFrom()
$status = PaymentStatus::from('paid'); // PaymentStatus::Paid
$status = PaymentStatus::tryFrom('invalid'); // null (error yox)

// Bütün dəyərləri almaq
$allStatuses = PaymentStatus::cases();
```

### Constructor Promotion

*Constructor Promotion üçün kod nümunəsi:*
```php
// Köhnə üsul
class Product
{
    private string $name;
    private float $price;
    private int $stock;

    public function __construct(string $name, float $price, int $stock)
    {
        $this->name = $name;
        $this->price = $price;
        $this->stock = $stock;
    }
}

// Constructor Promotion (PHP 8.0+) - qısa və təmiz
class Product
{
    public function __construct(
        private readonly string $name,
        private readonly float $price,
        private int $stock,
        private ?string $description = null,
    ) {
        // Əlavə logic lazımdırsa burada yaza bilərik
        if ($price < 0) {
            throw new InvalidArgumentException('Qiymət mənfi ola bilməz.');
        }
    }

    public function getName(): string
    {
        return $this->name;
    }
}
```

### Named Arguments

*Named Arguments üçün kod nümunəsi:*
```php
// Normal - sıra vacibdir
function createUser(string $name, string $email, int $age, bool $active = true): User
{
    // ...
}

// Named arguments (PHP 8.0+) - sıra vacib deyil, oxunaqlıdır
$user = createUser(
    name: 'Orxan',
    email: 'orxan@example.com',
    age: 30,
    active: true,
);

// Bəzi arqumentləri skip etmək olar
$user = createUser(
    name: 'Orxan',
    email: 'orxan@example.com',
    age: 30,
    // active default-u istifadə edəcək
);

// Laravel-də named arguments istifadəsi
Route::get('/users', action: [UserController::class, 'index'])
    ->name(name: 'users.index')
    ->middleware(middleware: ['auth', 'verified']);

// Collection-larda
$users = collect($items)->map(
    callback: fn (User $user) => $user->name,
);
```

---

## Laravel-də OOP İstifadəsi

### Service Class nümunəsi

*Service Class nümunəsi üçün kod nümunəsi:*
```php
// app/Services/OrderService.php
class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductService $productService,
        private readonly PaymentService $paymentService,
        private readonly OrderNotificationService $notificationService,
    ) {}

    public function placeOrder(PlaceOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            // 1. Stok yoxla
            foreach ($dto->items as $item) {
                $this->productService->checkAvailability($item->productId, $item->quantity);
            }

            // 2. Sifariş yarat
            $order = $this->orderRepository->create($dto);

            // 3. Stokdan düş
            foreach ($dto->items as $item) {
                $this->productService->decrementStock($item->productId, $item->quantity);
            }

            // 4. Ödəniş al
            $paymentResult = $this->paymentService->charge($order);

            if (!$paymentResult->success) {
                throw new PaymentFailedException($paymentResult->message);
            }

            // 5. Status yenilə
            $order->markAsPaid($paymentResult->transactionId);

            // 6. Bildiriş göndər
            $this->notificationService->orderPlaced($order);

            return $order;
        });
    }
}

// app/Http/Controllers/OrderController.php
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $dto = PlaceOrderDTO::fromRequest($request);

        try {
            $order = $this->orderService->placeOrder($dto);
            return response()->json([
                'message' => 'Sifariş uğurla yaradıldı.',
                'order' => new OrderResource($order),
            ], 201);
        } catch (PaymentFailedException $e) {
            return response()->json([
                'error' => 'Ödəniş uğursuz oldu: ' . $e->getMessage(),
            ], 422);
        }
    }
}
```

### Middleware nümunəsi

*Middleware nümunəsi üçün kod nümunəsi:*
```php
// app/Http/Middleware/EnsureApiVersion.php
class EnsureApiVersion
{
    public function handle(Request $request, Closure $next, string $version = 'v1'): Response
    {
        $requestedVersion = $request->header('X-API-Version', $version);

        if (!in_array($requestedVersion, ['v1', 'v2'])) {
            return response()->json([
                'error' => "API versiya '{$requestedVersion}' dəstəklənmir.",
            ], 400);
        }

        // Request-ə version əlavə et
        $request->merge(['api_version' => $requestedVersion]);

        $response = $next($request);

        // Response-a header əlavə et
        $response->headers->set('X-API-Version', $requestedVersion);

        return $response;
    }
}

// app/Http/Middleware/RateLimitByUser.php
class RateLimitByUser
{
    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next, int $maxAttempts = 60): Response
    {
        $key = 'api:' . ($request->user()?->id ?? $request->ip());

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'error' => 'Çox sayda sorğu göndərildi.',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter);
        }

        $this->limiter->hit($key, 60);

        return $next($request);
    }
}
```

### Action Class pattern

*Action Class pattern üçün kod nümunəsi:*
```php
// app/Actions/CreateInvoiceAction.php
class CreateInvoiceAction
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly TaxCalculator $taxCalculator,
        private readonly PdfGenerator $pdfGenerator,
    ) {}

    public function execute(Order $order): Invoice
    {
        $invoiceNumber = $this->numberGenerator->generate();
        $taxAmount = $this->taxCalculator->calculate($order->total, $order->taxRate);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'subtotal' => $order->total,
            'tax_amount' => $taxAmount,
            'total' => $order->total + $taxAmount,
            'issued_at' => now(),
        ]);

        // PDF yarat
        $pdfPath = $this->pdfGenerator->generate($invoice);
        $invoice->update(['pdf_path' => $pdfPath]);

        return $invoice;
    }
}

// Controller-da istifadə
class InvoiceController extends Controller
{
    public function store(Order $order, CreateInvoiceAction $action): JsonResponse
    {
        $invoice = $action->execute($order);

        return response()->json(new InvoiceResource($invoice), 201);
    }
}
```

---

## İntervyu Sualları

### 1. OOP-nin 4 əsas prinsipi nələrdir? Hər birini qısaca izah edin.
**Cavab**: Encapsulation (məlumatı gizlətmə), Inheritance (varislik), Polymorphism (çoxformalılıq), Abstraction (abstraksiya). Encapsulation - access modifiers ilə data-nı qorumaq; Inheritance - bir class-ın digərindən miras alması; Polymorphism - eyni interface ilə fərqli davranışlar; Abstraction - mürəkkəbliyi gizlədib sadə interfeys vermək.

### 2. Abstract class və Interface arasında fərq nədir? Nə vaxt hansını istifadə edərsiniz?
**Cavab**: Abstract class-da implementasiya ola bilər, properties ola bilər, bir class yalnız bir abstract class-dan miras ala bilər. Interface yalnız method imzalarını təyin edir (PHP 8+ default methods istisna), bir class çoxlu interface implement edə bilər. Abstract class ortaq kod paylaşmaq üçün, interface müqavilə/kontrakt təyin etmək üçün istifadə olunur.

### 3. SOLID prinsiplərini izah edin.
**Cavab**: S - Single Responsibility (hər class bir məsuliyyət), O - Open/Closed (genişlənmə üçün açıq, dəyişiklik üçün bağlı), L - Liskov Substitution (child class parent-in yerini tuta bilməli), I - Interface Segregation (böyük interface-lər kiçik olanlara bölünməli), D - Dependency Inversion (abstraksiyalara asılı ol, konkret implementasiyalara yox).

### 4. `self::` və `static::` arasında fərq nədir?
**Cavab**: `self::` compile-time-da həll olunur — həmişə təyin olunduğu class-a aiddir. `static::` runtime-da həll olunur — late static binding ilə faktiki (child) class-a aiddir. Məsələn, child class-da `self::create()` parent-i, `static::create()` child-ı qaytarır.

### 5. Composition və Inheritance arasında fərq nədir? Niyə composition üstünlük verilir?
**Cavab**: Inheritance "is-a" əlaqəsidir (Dog is-a Animal), Composition "has-a" əlaqəsidir (Car has-a Engine). Composition üstünlük verilir çünki: daha çevik (runtime-da dəyişdirilə bilər), coupling azaldır, LSP problemlərinin qarşısını alır, testing-i asanlaşdırır.

### 6. PHP-də Trait nədir? Nə vaxt istifadə etməli?
**Cavab**: Trait — class-lar arasında kod paylaşma mexanizmidir. PHP multiple inheritance dəstəkləmir, trait-lər bu boşluğu doldurur. Horizontal code reuse üçün istifadə olunur. Laravel-də HasFactory, SoftDeletes, Notifiable kimi trait-lər geniş istifadə olunur.

### 7. PHP 8.1-dəki Enum-lar haqqında danışın.
**Cavab**: Enums PHP 8.1-də gəldi. İki növü var: pure enums və backed enums (string/int). Enum-lar interface implement edə bilər, method-ları ola bilər, `from()` və `tryFrom()` ilə yaradıla bilər. Laravel-də model cast kimi istifadə olunur.

### 8. Magic method-lar nədir? Ən çox istifadə olunanları sadalayın.
**Cavab**: `__` ilə başlayan xüsusi PHP method-larıdır. Avtomatik çağırılırlar: `__construct` (yaradılanda), `__destruct` (məhv olanda), `__get`/`__set` (mövcud olmayan property-lərə müraciət), `__call`/`__callStatic` (mövcud olmayan method çağırışı), `__toString` (string-ə çevirmə), `__invoke` (obyekti funksiya kimi çağırma).

### 9. Readonly property nədir? Nə vaxt istifadə etməli?
**Cavab**: PHP 8.1-də gələn readonly property yalnız bir dəfə təyin oluna bilər (adətən constructor-da). Sonra dəyişdirilə bilməz. Value Objects, DTO-lar, immutable data structures üçün idealdır. PHP 8.2-dən `readonly class` da var.

### 10. Laravel-də Dependency Injection necə işləyir?
**Cavab**: Laravel Service Container vasitəsilə dependency-ləri avtomatik resolve edir. Constructor injection ilə interface-ləri bind edirik, Laravel avtomatik düzgün implementasiyanı inject edir. `$this->app->bind()`, `$this->app->singleton()` ilə qeydiyyat, type-hint ilə istifadə.

### 11. `never` return type nədir? Nə vaxt istifadə olunur?
**Cavab**: PHP 8.1-də gəldi. `never` return type — funcksiya/metodun heç vaxt normal şəkildə qayıtmayacağını bildirir: ya exception atır, ya da `exit()`/`die()` çağırır. `void`-dən fərqi: `void` metod çalışır və qayıdır (dəyər qaytarmadan), `never` isə heç vaxt qayıtmır. Kod analiz alətləri (PHPStan, Psalm) `never`-dən sonra kod yazılmaması lazım olduğunu bilir.
***Cavab**: PHP 8.1-də gəldi. `never` return type — funcksiya/metodun h üçün kod nümunəsi:*
```php
// Bu kod never return type-ın istifadəsini göstərir
function throwNotFound(string $message): never
{
    throw new NotFoundException($message); // həmişə exception atır
}
```

### 12. Intersection Type nədir? Union Type-dan fərqi nədir?
**Cavab**: PHP 8.1-də gəldi. Intersection type `A&B` — parametr/property həm A, həm də B interface-ini implement etməlidir. Union type `A|B` — ya A, ya da B olmalıdır. Fərq: Union — "ya bu, ya o"; Intersection — "həm bu, həm o". Intersection type-lar yalnız interface-lər (class deyil) ilə istifadə oluna bilər. PHP 8.2-dən DNF (Disjunctive Normal Form) types da var: `(Countable&Iterator)|null`.
***Cavab**: PHP 8.1-də gəldi. Intersection type `A&B` — parametr/proper üçün kod nümunəsi:*
```php
// Bu kod intersection type istifadəsini göstərir
function processCollection(Countable&Iterator $items): void { /* ... */ }
```

### 13. Covariance və Contravariance nədir?
**Cavab**: Covariance — override edilmiş metodun return type-ı parent-in return type-ının alt tipi ola bilər (daha spesifik). Contravariance — override edilmiş metodun parametr type-ı parent-in parametr type-ının üst tipi ola bilər (daha ümumi). PHP-də return type covariant, parametr type contravariant, property type isə invariant-dır (nə covariant, nə contravariant dəyişə bilər). Bu, LSP-nin düzgün implementasiyası üçün vacibdir.

### 14. PHP 8.3-də hansı yeniliklər var?
**Cavab**: Ən mühüm yeniliklər: (1) **Typed Class Constants** — sabitlərə tip verilə bilər: `const string VERSION = '1.0';` (2) **`#[Override]` Attribute** — metodun həqiqətən parent-dən override etdiyini kompilyasiya zamanı yoxlayır, yanlış metod adı yazıldıqda xəta verir (3) **`json_validate()`** funksiyası — JSON-u decode etmədən sürətlə yoxlamaq (4) **`mb_str_split()`** yaxşılaşdırmaları (5) **`array_find()`, `array_find_key()`** funksiyaları (PHP 8.4-də rəsmiləşdi).

### 15. PHP-də Fibers nədir?
**Cavab**: PHP 8.1-də gəldi. Fiber — bir funksiyonun icrasını dayandırıb (suspend) sonra davam etdirməyə imkan verən primitive-dir. `Fiber::start()`, `Fiber::suspend()`, `Fiber::resume()` metodları ilə idarə olunur. Async/await-in alt qatında dayanır. Laravel-də `Queue::fake()`, ReactPHP, Amp framework-lərinin foundation-ını təşkil edir. Fibers OS thread deyil — cooperative multitasking (iş özü suspend deməlidir, OS zorla kəsmir).

---

## Anti-patternlər

**1. God Object (Hər Şeyi Bilən Sinif)**
Bütün business logic-i tək bir class-a yığmaq — SRP pozulur, class minlərlə sətirə çatır, test etmək və dəyişdirmək çox çətin olur. Hər class-ın yalnız bir məsuliyyəti olmalıdır; böyük class-ları kiçik, fokuslanmış sinflərə bölün.

**2. Concrete Class-a Bağlılıq (new Keyword Sui-istifadəsi)**
Constructor-larda birbaşa `new ConcreteService()` yazmaq — tight coupling yaradır, test zamanı mock etmək mümkünsüz olur. Interface type-hint istifadə edin, dependency-ləri Service Container vasitəsilə inject edin.

**3. Inheritance-ı Composition Əvəzinə Aşırı İstifadə**
Kod paylaşmaq üçün dərin inheritance zənciri qurmaq — dəyişiklik bütün alt sinifləri pozur, Liskov Substitution pozulur. Composition və Trait-lərdən istifadə edin; inheritance yalnız "is-a" münasibəti olduqda tətbiq olunmalıdır.

**4. Anemic Domain Model**
Yalnız getter/setter-dən ibarət class-lar yaradıb bütün logic-i xarici service-lərə vermək — business qaydaları dağılır, domain anlaşılmazlaşır. Domain logic-i aid olduğu entity və value object-lərin içinə köçürün.

**5. Magic Method-ların Həddindən Artıq İstifadəsi**
`__get`, `__set`, `__call` ilə hər şeyi dinamik etmək — IDE dəstəyi itir, statik analiz mümkünsüzləşir, gizli xətalar yaranır. Açıq-aydın property və method-lar yazın; magic method yalnız çox zəruri hallarda istifadə olunmalıdır.

**6. Static Method Sui-istifadəsi**
Business logic-i static method-lara yerləşdirmək — global state yaranır, dependency injection mümkünsüz olur, test etmək çətin olur. Stateless utility funksiyalar istisna olmaqla, static method-dan çəkinin; əvəzinə injectable service class-lar yazın.
