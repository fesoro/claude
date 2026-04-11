# Əksik Design Patterns

## 1. Adapter Pattern

Uyğunsuz interfeysi uyğun hala gətirmək. Xarici library/API-ni öz sisteminə uyğunlaşdırmaq.

```php
// Problеm: Müxtəlif payment gateway-lərin müxtəlif API-ləri var

// Bizim unified interfeys
interface PaymentGateway {
    public function charge(float $amount, string $currency, array $card): PaymentResult;
    public function refund(string $transactionId, float $amount): RefundResult;
}

// Stripe-ın öz SDK-sı fərqli işləyir
class StripeAdapter implements PaymentGateway {
    public function __construct(private \Stripe\StripeClient $stripe) {}

    public function charge(float $amount, string $currency, array $card): PaymentResult {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => (int) ($amount * 100), // Stripe cents gözləyir
            'currency' => strtolower($currency),
            'payment_method_data' => [
                'type' => 'card',
                'card' => ['number' => $card['number'], /* ... */],
            ],
            'confirm' => true,
        ]);

        return new PaymentResult(
            success: $intent->status === 'succeeded',
            transactionId: $intent->id,
            amount: $amount,
        );
    }

    public function refund(string $transactionId, float $amount): RefundResult {
        $refund = $this->stripe->refunds->create([
            'payment_intent' => $transactionId,
            'amount' => (int) ($amount * 100),
        ]);

        return new RefundResult(
            success: $refund->status === 'succeeded',
            refundId: $refund->id,
        );
    }
}

// PayPal tamam fərqli API istifadə edir
class PayPalAdapter implements PaymentGateway {
    public function __construct(private PayPalHttpClient $client) {}

    public function charge(float $amount, string $currency, array $card): PaymentResult {
        $request = new OrdersCreateRequest();
        $request->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => ['value' => number_format($amount, 2), 'currency_code' => $currency],
            ]],
        ];

        $response = $this->client->execute($request);

        return new PaymentResult(
            success: $response->result->status === 'COMPLETED',
            transactionId: $response->result->id,
            amount: $amount,
        );
    }

    public function refund(string $transactionId, float $amount): RefundResult {
        // PayPal-ın fərqli refund API-si...
    }
}

// Service Provider-da
$this->app->bind(PaymentGateway::class, function () {
    return match(config('payment.default')) {
        'stripe' => new StripeAdapter(new \Stripe\StripeClient(config('services.stripe.secret'))),
        'paypal' => new PayPalAdapter(new PayPalHttpClient(/* ... */)),
    };
});

// İstifadə — gateway hansı olduğunu bilmirik, əhəmiyyəti yoxdur
class OrderService {
    public function __construct(private PaymentGateway $gateway) {}

    public function pay(Order $order): PaymentResult {
        return $this->gateway->charge($order->total, 'USD', $order->card);
    }
}
```

**Nə vaxt istifadə olunur?**
- Xarici SDK/library-ni öz interfeysinizə uyğunlaşdırmaq
- Köhnə kodu yeni sistemlə inteqrasiya etmək
- Vendor lock-in-dən qaçmaq

---

## 2. Chain of Responsibility Pattern

Request-i ardıcıl handler-lərdən keçirmək. Hər handler ya emal edir, ya növbətiyə ötürür.

```php
// Laravel Middleware — əslində Chain of Responsibility-dir!
// Amma business logic-də də istifadə olunur:

abstract class ApprovalHandler {
    private ?self $next = null;

    public function setNext(self $handler): self {
        $this->next = $handler;
        return $handler; // Fluent chain
    }

    public function handle(PurchaseRequest $request): ApprovalResult {
        if ($this->canHandle($request)) {
            return $this->approve($request);
        }

        if ($this->next) {
            return $this->next->handle($request);
        }

        return ApprovalResult::rejected('Heç bir approval level bu məbləği təsdiq edə bilmir.');
    }

    abstract protected function canHandle(PurchaseRequest $request): bool;
    abstract protected function approve(PurchaseRequest $request): ApprovalResult;
}

class TeamLeadApproval extends ApprovalHandler {
    protected function canHandle(PurchaseRequest $request): bool {
        return $request->amount <= 1000;
    }

    protected function approve(PurchaseRequest $request): ApprovalResult {
        return ApprovalResult::approved('Team Lead', $request->amount);
    }
}

class ManagerApproval extends ApprovalHandler {
    protected function canHandle(PurchaseRequest $request): bool {
        return $request->amount <= 10000;
    }

    protected function approve(PurchaseRequest $request): ApprovalResult {
        return ApprovalResult::approved('Manager', $request->amount);
    }
}

class DirectorApproval extends ApprovalHandler {
    protected function canHandle(PurchaseRequest $request): bool {
        return $request->amount <= 100000;
    }

    protected function approve(PurchaseRequest $request): ApprovalResult {
        return ApprovalResult::approved('Director', $request->amount);
    }
}

class CEOApproval extends ApprovalHandler {
    protected function canHandle(PurchaseRequest $request): bool {
        return true; // CEO hər şeyi təsdiq edə bilər
    }

    protected function approve(PurchaseRequest $request): ApprovalResult {
        return ApprovalResult::approved('CEO', $request->amount);
    }
}

// Chain qur
$teamLead = new TeamLeadApproval();
$teamLead
    ->setNext(new ManagerApproval())
    ->setNext(new DirectorApproval())
    ->setNext(new CEOApproval());

$result = $teamLead->handle(new PurchaseRequest(amount: 5000));
// "Manager tərəfindən təsdiqləndi"

$result = $teamLead->handle(new PurchaseRequest(amount: 50000));
// "Director tərəfindən təsdiqləndi"
```

**Real-world istifadə:**
- Discount hesablama zənciri
- Validation zənciri
- Log/notification routing
- Laravel Pipeline (bu pattern-in daha sadə implementasiyası)

---

## 3. Command Pattern

Əməliyyatı obyekt kimi kapsullaşdırmaq. Undo/Redo, queue, logging üçün.

```php
interface Command {
    public function execute(): void;
    public function undo(): void;
}

class ChangeOrderStatusCommand implements Command {
    private ?string $previousStatus = null;

    public function __construct(
        private Order $order,
        private string $newStatus,
    ) {}

    public function execute(): void {
        $this->previousStatus = $this->order->status;
        $this->order->update(['status' => $this->newStatus]);

        AuditLog::record('status_changed', $this->order, [
            'from' => $this->previousStatus,
            'to' => $this->newStatus,
        ]);
    }

    public function undo(): void {
        if ($this->previousStatus !== null) {
            $this->order->update(['status' => $this->previousStatus]);

            AuditLog::record('status_reverted', $this->order, [
                'from' => $this->newStatus,
                'to' => $this->previousStatus,
            ]);
        }
    }
}

class ApplyDiscountCommand implements Command {
    private float $previousDiscount = 0;

    public function __construct(
        private Order $order,
        private float $discount,
    ) {}

    public function execute(): void {
        $this->previousDiscount = $this->order->discount;
        $this->order->update([
            'discount' => $this->discount,
            'total' => $this->order->subtotal - $this->discount + $this->order->tax,
        ]);
    }

    public function undo(): void {
        $this->order->update([
            'discount' => $this->previousDiscount,
            'total' => $this->order->subtotal - $this->previousDiscount + $this->order->tax,
        ]);
    }
}

// Command invoker — undo/redo dəstəyi
class CommandHistory {
    private array $history = [];
    private int $pointer = -1;

    public function execute(Command $command): void {
        $command->execute();

        // Undo-dan sonra yeni command icra olunursa, irəlini sil
        $this->history = array_slice($this->history, 0, $this->pointer + 1);
        $this->history[] = $command;
        $this->pointer++;
    }

    public function undo(): void {
        if ($this->pointer >= 0) {
            $this->history[$this->pointer]->undo();
            $this->pointer--;
        }
    }

    public function redo(): void {
        if ($this->pointer < count($this->history) - 1) {
            $this->pointer++;
            $this->history[$this->pointer]->execute();
        }
    }
}

// İstifadə
$history = new CommandHistory();
$history->execute(new ChangeOrderStatusCommand($order, 'processing'));
$history->execute(new ApplyDiscountCommand($order, 15.00));
$history->undo(); // Discount geri qaytarıldı
$history->undo(); // Status geri qaytarıldı
$history->redo(); // Status yenidən processing
```

---

## 4. State Machine Pattern

Obyektin vəziyyət keçidlərini kontrol etmək.

```php
// Misal: Order Status Machine

interface OrderState {
    public function confirm(Order $order): void;
    public function ship(Order $order): void;
    public function deliver(Order $order): void;
    public function cancel(Order $order): void;
    public function getStatus(): string;
}

class PendingState implements OrderState {
    public function confirm(Order $order): void {
        $order->transitionTo(new ConfirmedState());
    }

    public function ship(Order $order): void {
        throw new InvalidTransitionException('Pending order göndərilə bilməz. Əvvəlcə təsdiqləyin.');
    }

    public function deliver(Order $order): void {
        throw new InvalidTransitionException('Pending order çatdırıla bilməz.');
    }

    public function cancel(Order $order): void {
        $order->transitionTo(new CancelledState());
        // Stoku geri qaytar
        app(InventoryService::class)->releaseReservation($order);
    }

    public function getStatus(): string { return 'pending'; }
}

class ConfirmedState implements OrderState {
    public function confirm(Order $order): void {
        throw new InvalidTransitionException('Artıq təsdiqlənib.');
    }

    public function ship(Order $order): void {
        $order->transitionTo(new ShippedState());
        OrderShipped::dispatch($order);
    }

    public function deliver(Order $order): void {
        throw new InvalidTransitionException('Əvvəlcə göndərilməlidir.');
    }

    public function cancel(Order $order): void {
        $order->transitionTo(new CancelledState());
        app(PaymentService::class)->refund($order);
        app(InventoryService::class)->releaseReservation($order);
    }

    public function getStatus(): string { return 'confirmed'; }
}

class ShippedState implements OrderState {
    public function confirm(Order $order): void {
        throw new InvalidTransitionException('Göndərilmiş order təsdiqlənə bilməz.');
    }

    public function ship(Order $order): void {
        throw new InvalidTransitionException('Artıq göndərilib.');
    }

    public function deliver(Order $order): void {
        $order->transitionTo(new DeliveredState());
        OrderDelivered::dispatch($order);
    }

    public function cancel(Order $order): void {
        throw new InvalidTransitionException('Göndərilmiş order ləğv edilə bilməz. Return prosesi başladın.');
    }

    public function getStatus(): string { return 'shipped'; }
}

// Order model-də
class Order extends Model {
    public function getStateObject(): OrderState {
        return match($this->status) {
            'pending' => new PendingState(),
            'confirmed' => new ConfirmedState(),
            'shipped' => new ShippedState(),
            'delivered' => new DeliveredState(),
            'cancelled' => new CancelledState(),
        };
    }

    public function transitionTo(OrderState $state): void {
        $oldStatus = $this->status;
        $this->update(['status' => $state->getStatus()]);

        OrderStatusChanged::dispatch($this, $oldStatus, $state->getStatus());
    }

    // Proxy methods
    public function confirm(): void { $this->getStateObject()->confirm($this); }
    public function ship(): void { $this->getStateObject()->ship($this); }
    public function deliver(): void { $this->getStateObject()->deliver($this); }
    public function cancel(): void { $this->getStateObject()->cancel($this); }
}

// Controller-da
public function ship(Order $order): JsonResponse {
    try {
        $order->ship();
        return response()->json(new OrderResource($order->fresh()));
    } catch (InvalidTransitionException $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
}
```

**Daha sadə yanaşma — Enum ilə:**
```php
enum OrderStatus: string {
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function transitions(): array {
        return match($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool {
        return in_array($next, $this->transitions());
    }

    public function transitionTo(self $next): self {
        if (!$this->canTransitionTo($next)) {
            throw new InvalidTransitionException(
                "{$this->value} → {$next->value} keçidi mümkün deyil."
            );
        }
        return $next;
    }
}
```

---

## 5. Mediator Pattern

Obyektlər arası birbaşa əlaqəni azaltmaq. Laravel-in Event/Listener sistemi əslində Mediator-dur.

```php
// Misal: Chat room — istifadəçilər birbaşa yox, mediator vasitəsilə ünsiyyət qurur

interface ChatMediator {
    public function sendMessage(string $message, ChatUser $sender): void;
    public function addUser(ChatUser $user): void;
}

class ChatRoom implements ChatMediator {
    private array $users = [];

    public function addUser(ChatUser $user): void {
        $this->users[] = $user;
        $user->setMediator($this);
    }

    public function sendMessage(string $message, ChatUser $sender): void {
        foreach ($this->users as $user) {
            if ($user !== $sender) {
                $user->receive($message, $sender->getName());
            }
        }
    }
}

class ChatUser {
    private ChatMediator $mediator;

    public function __construct(private string $name) {}

    public function setMediator(ChatMediator $mediator): void {
        $this->mediator = $mediator;
    }

    public function send(string $message): void {
        $this->mediator->sendMessage($message, $this);
    }

    public function receive(string $message, string $from): void {
        echo "[{$this->name}] {$from}-dən mesaj: {$message}\n";
    }

    public function getName(): string { return $this->name; }
}

// Laravel-dəki analoqu:
// Event::dispatch(new MessageSent($message));
// → Mediator bütün listener-lərə çatdırır
```

---

## 6. Proxy Pattern

Obyektə giriş üzərində kontrol. Lazy loading, caching, access control.

```php
// Caching Proxy
interface UserRepository {
    public function find(int $id): ?User;
    public function findByEmail(string $email): ?User;
}

class EloquentUserRepository implements UserRepository {
    public function find(int $id): ?User {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User {
        return User::where('email', $email)->first();
    }
}

class CachedUserRepository implements UserRepository {
    public function __construct(
        private UserRepository $repository,
        private int $ttl = 3600,
    ) {}

    public function find(int $id): ?User {
        return Cache::remember(
            "user:{$id}",
            $this->ttl,
            fn () => $this->repository->find($id),
        );
    }

    public function findByEmail(string $email): ?User {
        return Cache::remember(
            "user:email:{$email}",
            $this->ttl,
            fn () => $this->repository->findByEmail($email),
        );
    }
}

// Logging Proxy
class LoggingUserRepository implements UserRepository {
    public function __construct(private UserRepository $repository) {}

    public function find(int $id): ?User {
        Log::debug("UserRepository::find called", ['id' => $id]);
        $start = microtime(true);

        $result = $this->repository->find($id);

        Log::debug("UserRepository::find completed", [
            'id' => $id,
            'found' => $result !== null,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return $result;
    }

    public function findByEmail(string $email): ?User {
        // eyni pattern...
    }
}

// Service Provider — proxy-ləri iç-içə bağla
$this->app->bind(UserRepository::class, function () {
    return new CachedUserRepository(
        new LoggingUserRepository(
            new EloquentUserRepository()
        )
    );
});
// Sorğu: Cache → Log → Eloquent
```

---

## 7. Builder Pattern

Mürəkkəb obyektləri addım-addım qurmaq.

```php
class QueryReportBuilder {
    private Builder $query;
    private array $columns = ['*'];
    private array $filters = [];
    private ?string $sortBy = null;
    private string $sortDir = 'desc';
    private ?int $limit = null;
    private string $format = 'collection';

    public function __construct(string $model) {
        $this->query = $model::query();
    }

    public static function for(string $model): self {
        return new self($model);
    }

    public function select(array $columns): self {
        $this->columns = $columns;
        return $this;
    }

    public function whereDateBetween(string $column, Carbon $from, Carbon $to): self {
        $this->query->whereBetween($column, [$from, $to]);
        return $this;
    }

    public function whereStatus(string ...$statuses): self {
        $this->query->whereIn('status', $statuses);
        return $this;
    }

    public function sortBy(string $column, string $direction = 'desc'): self {
        $this->sortBy = $column;
        $this->sortDir = $direction;
        return $this;
    }

    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    public function groupByDay(string $column = 'created_at'): self {
        $this->query->selectRaw("DATE($column) as date, COUNT(*) as count, SUM(total) as revenue");
        $this->query->groupByRaw("DATE($column)");
        return $this;
    }

    public function get(): Collection {
        $query = $this->query->select($this->columns);

        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDir);
        }

        if ($this->limit) {
            $query->limit($this->limit);
        }

        return $query->get();
    }
}

// İstifadə — oxunaqlı, fluent API
$report = QueryReportBuilder::for(Order::class)
    ->whereDateBetween('created_at', now()->subMonth(), now())
    ->whereStatus('completed', 'shipped')
    ->groupByDay()
    ->sortBy('date')
    ->get();
```
