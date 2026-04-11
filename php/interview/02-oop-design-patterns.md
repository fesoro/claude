# OOP və Design Patterns

## 1. SOLID prinsipləri izah edin.

### S — Single Responsibility Principle
Hər sinifin yalnız bir məsuliyyəti olmalıdır.

```php
// Pis
class User {
    public function save() { /* DB-ə yaz */ }
    public function sendEmail() { /* Email göndər */ }
    public function generateReport() { /* Hesabat yarat */ }
}

// Yaxşı
class User { /* yalnız user data */ }
class UserRepository { public function save(User $user) {} }
class UserMailer { public function sendWelcome(User $user) {} }
class UserReportGenerator { public function generate(User $user) {} }
```

### O — Open/Closed Principle
Sinif genişlənmə üçün açıq, dəyişiklik üçün qapalı olmalıdır.

```php
// Pis — hər yeni ödəniş üsulu üçün sinfi dəyişməlisən
class PaymentProcessor {
    public function process(string $type, float $amount): void {
        if ($type === 'stripe') { /* ... */ }
        elseif ($type === 'paypal') { /* ... */ }
        // Hər yeni tip üçün buraya əlavə etməli
    }
}

// Yaxşı — yeni sinif əlavə et, mövcudu dəyişmə
interface PaymentGateway {
    public function charge(float $amount): bool;
}

class StripeGateway implements PaymentGateway {
    public function charge(float $amount): bool { /* ... */ }
}

class PayPalGateway implements PaymentGateway {
    public function charge(float $amount): bool { /* ... */ }
}

class PaymentProcessor {
    public function process(PaymentGateway $gateway, float $amount): bool {
        return $gateway->charge($amount);
    }
}
```

### L — Liskov Substitution Principle
Alt sinif üst sinifin yerini problemsiz tuta bilməlidir.

```php
// Pis — Rectangle/Square problemi
class Rectangle {
    public function __construct(
        protected int $width,
        protected int $height,
    ) {}

    public function setWidth(int $w): void { $this->width = $w; }
    public function setHeight(int $h): void { $this->height = $h; }
    public function area(): int { return $this->width * $this->height; }
}

class Square extends Rectangle {
    public function setWidth(int $w): void {
        $this->width = $w;
        $this->height = $w; // LSP pozuldu — davranış dəyişdi
    }
}

// Yaxşı — ayrı interfeys
interface Shape {
    public function area(): int;
}
class Rectangle implements Shape { /* ... */ }
class Square implements Shape { /* ... */ }
```

### I — Interface Segregation Principle
Böyük interfeyslər kiçik, spesifik interfeyslərə bölünməlidir.

```php
// Pis
interface Worker {
    public function code(): void;
    public function test(): void;
    public function design(): void;
    public function manage(): void;
}

// Yaxşı
interface Coder { public function code(): void; }
interface Tester { public function test(): void; }
interface Designer { public function design(): void; }

class FullStackDev implements Coder, Tester { /* ... */ }
class UiDesigner implements Designer { /* ... */ }
```

### D — Dependency Inversion Principle
Yüksək səviyyəli modullar aşağı səviyyəli modullara yox, abstraction-a bağlı olmalıdır.

```php
// Pis — yüksək səviyyə aşağıya asılıdır
class OrderService {
    private MySqlOrderRepository $repo; // konkret sinifə asılı
}

// Yaxşı — hər ikisi abstraction-a asılıdır
interface OrderRepositoryInterface {
    public function save(Order $order): void;
}

class MySqlOrderRepository implements OrderRepositoryInterface { /* ... */ }
class OrderService {
    public function __construct(
        private OrderRepositoryInterface $repo, // abstraksiyaya asılı
    ) {}
}
```

---

## 2. Repository Pattern nədir?

Data access məntiqini business logic-dən ayırır.

```php
interface UserRepositoryInterface {
    public function find(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function save(User $user): void;
    public function delete(User $user): void;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
}

class EloquentUserRepository implements UserRepositoryInterface {
    public function __construct(private User $model) {}

    public function find(int $id): ?User {
        return $this->model->find($id);
    }

    public function findByEmail(string $email): ?User {
        return $this->model->where('email', $email)->first();
    }

    public function save(User $user): void {
        $user->save();
    }

    public function delete(User $user): void {
        $user->delete();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator {
        return $this->model->paginate($perPage);
    }
}

// Service Provider-da bind et
$this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
```

---

## 3. Strategy Pattern nədir?

Algoritmi runtime-da dəyişmək imkanı verir.

```php
interface ShippingStrategy {
    public function calculate(Order $order): float;
}

class FreeShipping implements ShippingStrategy {
    public function calculate(Order $order): float {
        return 0;
    }
}

class FlatRateShipping implements ShippingStrategy {
    public function calculate(Order $order): float {
        return 9.99;
    }
}

class WeightBasedShipping implements ShippingStrategy {
    public function calculate(Order $order): float {
        return $order->getTotalWeight() * 0.5;
    }
}

class ShippingCalculator {
    public function __construct(private ShippingStrategy $strategy) {}

    public function calculate(Order $order): float {
        return $this->strategy->calculate($order);
    }
}

// İstifadə
$calculator = new ShippingCalculator(
    $order->getTotal() > 100
        ? new FreeShipping()
        : new WeightBasedShipping()
);
$cost = $calculator->calculate($order);
```

---

## 4. Observer Pattern nədir?

Bir obyektin vəziyyəti dəyişəndə digər obyektləri xəbərdar etmək.

```php
// Laravel-də Events/Listeners — Observer pattern-in implementasiyasıdır

// Event
class OrderPlaced {
    public function __construct(public readonly Order $order) {}
}

// Listeners
class SendOrderConfirmation {
    public function handle(OrderPlaced $event): void {
        Mail::to($event->order->user)->send(new OrderConfirmationMail($event->order));
    }
}

class UpdateInventory {
    public function handle(OrderPlaced $event): void {
        foreach ($event->order->items as $item) {
            $item->product->decrementStock($item->quantity);
        }
    }
}

class NotifyWarehouse {
    public function handle(OrderPlaced $event): void {
        // Anbara bildiriş göndər
    }
}

// EventServiceProvider
protected $listen = [
    OrderPlaced::class => [
        SendOrderConfirmation::class,
        UpdateInventory::class,
        NotifyWarehouse::class,
    ],
];

// Dispatch
OrderPlaced::dispatch($order);
```

---

## 5. Factory Pattern nədir?

Obyekt yaratma məntiqini mərkəzləşdirir.

```php
interface Notification {
    public function send(string $to, string $message): void;
}

class EmailNotification implements Notification { /* ... */ }
class SmsNotification implements Notification { /* ... */ }
class PushNotification implements Notification { /* ... */ }

class NotificationFactory {
    public static function create(string $channel): Notification {
        return match($channel) {
            'email' => new EmailNotification(),
            'sms' => new SmsNotification(),
            'push' => new PushNotification(),
            default => throw new InvalidArgumentException("Unknown channel: $channel"),
        };
    }
}

$notification = NotificationFactory::create('sms');
$notification->send('+994501234567', 'Salam!');
```

---

## 6. Decorator Pattern nədir?

Mövcud obyektə yeni funksionallıq əlavə etmək (wrapping).

```php
interface Logger {
    public function log(string $message): void;
}

class FileLogger implements Logger {
    public function log(string $message): void {
        file_put_contents('app.log', $message . PHP_EOL, FILE_APPEND);
    }
}

class TimestampLogger implements Logger {
    public function __construct(private Logger $inner) {}

    public function log(string $message): void {
        $this->inner->log('[' . date('Y-m-d H:i:s') . '] ' . $message);
    }
}

class JsonLogger implements Logger {
    public function __construct(private Logger $inner) {}

    public function log(string $message): void {
        $this->inner->log(json_encode(['message' => $message]));
    }
}

// Dekoratorları iç-içə istifadə et
$logger = new TimestampLogger(new JsonLogger(new FileLogger()));
$logger->log('User logged in');
```

---

## 7. Singleton Pattern nədir və niyə ehtiyatla istifadə olunmalıdır?

Bir sinifin yalnız bir instance-ının olmasını təmin edir.

```php
class Database {
    private static ?self $instance = null;

    private function __construct(private PDO $pdo) {}
    private function __clone() {}

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self(
                new PDO('mysql:host=localhost;dbname=app', 'root', '')
            );
        }
        return self::$instance;
    }
}
```

**Niyə ehtiyatla?**
- Global state yaradır — test etmək çətindir
- Tight coupling yaranır
- Dependency Injection ilə əvəz etmək daha yaxşıdır
- Laravel-in Service Container-i singleton davranışını DI ilə təmin edir:
```php
$this->app->singleton(Database::class, fn() => new Database(...));
```

---

## 8. DTO (Data Transfer Object) nədir?

Layerlər arası data daşımaq üçün sadə obyekt.

```php
class CreateUserDTO {
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $phone = null,
    ) {}

    public static function fromRequest(Request $request): self {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            phone: $request->validated('phone'),
        );
    }
}

class UserService {
    public function create(CreateUserDTO $dto): User {
        return User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'phone' => $dto->phone,
        ]);
    }
}

// Controller-da
public function store(CreateUserRequest $request): JsonResponse {
    $user = $this->userService->create(CreateUserDTO::fromRequest($request));
    return response()->json($user, 201);
}
```

---

## 9. Service Layer Pattern nədir?

Business logic-i controller-dən ayırmaq.

```php
class OrderService {
    public function __construct(
        private OrderRepositoryInterface $orders,
        private PaymentGateway $payment,
        private InventoryService $inventory,
        private NotificationService $notifications,
    ) {}

    public function place(PlaceOrderDTO $dto): Order {
        // 1. Stoku yoxla
        $this->inventory->checkAvailability($dto->items);

        // 2. Order yarat
        $order = $this->orders->create($dto);

        // 3. Ödəniş al
        $this->payment->charge($order->total, $dto->paymentMethod);

        // 4. Stoku azalt
        $this->inventory->reserve($order->items);

        // 5. Bildiriş göndər
        $this->notifications->orderPlaced($order);

        return $order;
    }
}

// Controller sadələşir
class OrderController {
    public function store(PlaceOrderRequest $request, OrderService $service): JsonResponse {
        $order = $service->place(PlaceOrderDTO::fromRequest($request));
        return new OrderResource($order);
    }
}
```

---

## 10. Composition over Inheritance nə deməkdir?

Inheritance əvəzinə composition istifadə etmək daha çevikdir.

```php
// Pis — inheritance ilə
class Animal { public function eat() {} }
class FlyingAnimal extends Animal { public function fly() {} }
class SwimmingAnimal extends Animal { public function swim() {} }
// FlyingSwimmingAnimal?? Multiple inheritance yoxdur!

// Yaxşı — composition ilə
interface CanFly { public function fly(): void; }
interface CanSwim { public function swim(): void; }

trait Flying {
    public function fly(): void { echo "Uçur"; }
}

trait Swimming {
    public function swim(): void { echo "Üzür"; }
}

class Duck implements CanFly, CanSwim {
    use Flying, Swimming;
}
```
