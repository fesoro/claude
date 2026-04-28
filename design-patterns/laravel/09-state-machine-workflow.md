# State Machine & Workflow Engine (Senior ⭐⭐⭐)

## İcmal

State Machine pattern, bir entity-nin sonlu sayda state arasında idarə olunmasını mərkəziləşdirir. Yalnız müəyyən edilmiş transition-lara icazə verilir; icazəsiz keçidlər exception ilə bloklanır. PHP 8.1+ Enum, Spatie `laravel-model-states` paketi və Symfony Workflow Component — üç fərqli implementasiya yolu mövcuddur.

## Niyə Vacibdir

`if ($order->status === 'paid' && $order->status !== 'shipped')` kimi şərtlər hər yerə yayıldıqda illegal state transition-lar baş verir. "Cancelled order shipped oldu" kimi buglar state validation-ın olmadığı yerə işarə edir. State Machine bu validasiyanı bir yerə toplayır: hər transition açıq müəyyənləşdirilir, icazəsiz keçid compile/runtime-da aşkar olunur.

## Əsas Anlayışlar

- **State**: entity-nin cari vəziyyəti — `pending`, `paid`, `shipped`, `delivered`, `cancelled`
- **Transition**: bir state-dən digərinə keçid — yalnız müəyyən edilmiş cütlər icazəlidir
- **Guard**: transition üçün əlavə şərt — `total > 0`, `user is admin`
- **Action / Entry-Exit**: transition zamanı və ya state-ə girəndə/çıxanda çalışan callback
- **Final state**: daha heç bir transition icazəli deyil — `delivered`, `cancelled`
- **State Machine vs Workflow**: SM eyni anda bir state; Workflow (Petri net) eyni anda çox state
- **Marking store**: state-in persist olunduğu yer — Eloquent column, JSON field

## Praktik Baxış

### Real istifadə

| Domain | States | Transitions |
|--------|--------|-------------|
| E-commerce order | pending → paid → shipped → delivered | pay, ship, deliver, cancel |
| Blog post | draft → review → published → archived | submit, approve, reject, publish |
| Support ticket | open → in_progress → resolved → closed | assign, resolve, reopen, close |
| Subscription | trial → active → past_due → cancelled | activate, fail_charge, renew, cancel |
| Payment | initiated → authorized → captured → refunded | authorize, capture, refund |

### Trade-off-lar

- **State Machine**: explicit transitions, audit trail, business logic mərkəzləşir; amma yeni state əlavə etmək transition matrixini genişləndirir
- **Spatie paketi**: Laravel integration, Eloquent cast, qorunan transition-lar; amma third-party dependency, sadə Enum-a nisbətən overhead
- **Symfony Workflow**: guard, event, YAML config; Laravel-ə tam entegrasyon olmur — əlavə adapter lazımdır
- **Enum FSM**: dependency-free, type-safe; guard/action/event dəstəyi yoxdur — manual əlavə lazımdır

### İstifadə etməmək

- 2-3 state-li sadə toggle üçün (`active`/`inactive`) — boolean column kifayətdir
- State sayısı 10+ və transition sayısı 30+ olduqda — Camunda/Temporal kimi dedicated workflow engine düşünün
- Business analyst-lər transitions-ı müəyyən edirsə — BPMN visual tool daha uyğundur

### Common mistakes

1. State machine-i yalnız UI üçün istifadə etmək — backend-də də enforce edilməlidir
2. Transition-ı service-də yoxlamaq (`if ($order->status === 'paid')`) — state machine öz validasiyasını özü etməlidir
3. State history-ni saxlamamaq — "niyə cancelled oldu" sualına cavab verilə bilmir
4. Guard-da DB query etmək — N+1 riski; guard-ı sadə saxla, əvvəlcədən yüklə

### Anti-Pattern Nə Zaman Olur?

**Status hər yerdə yoxlanılır — state machine yoxdur:**

```php
// BAD — transition logic hər yerə yayılmışdır
class OrderController
{
    public function ship(Order $order): JsonResponse
    {
        // Bu yoxlama service-də də, job-da da, artisan command-da da var
        if ($order->status !== 'paid') {
            return response()->json(['error' => 'Cannot ship unpaid order'], 422);
        }
        $order->update(['status' => 'shipped']);
        return response()->json($order);
    }
}

class OrderService
{
    public function cancelFromAdmin(Order $order): void
    {
        // Eyni yoxlama burada da — DRY pozulur
        if (!in_array($order->status, ['pending', 'paid'])) {
            throw new \Exception('Cannot cancel');
        }
        $order->status = 'cancelled';
        $order->save();
    }
}
```

```php
// GOOD — transition logic bir yerdə, entity özü qoruyur
class Order extends Model
{
    use HasStates;

    protected $casts = ['status' => OrderState::class];

    public function ship(): void
    {
        // TransitionNotAllowed exception — hər yerdə eyni qaydalar
        $this->status->transitionTo(Shipped::class);
        $this->save();
    }

    public function cancel(): void
    {
        $this->status->transitionTo(Cancelled::class);
        $this->save();
    }
}
```

**Over-engineering sadə toggle üçün:**

```php
// BAD — `is_active` boolean üçün state machine qurmaq
abstract class UserState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Active::class)
            ->allowTransition(Active::class, Inactive::class)
            ->allowTransition(Inactive::class, Active::class);
    }
}
// Bu sadəcə boolean column-dur: $user->is_active = true; $user->save();

// GOOD — state machine yalnız 3+ state və non-trivial transition matrix olduqda
```

## Nümunələr

### Ümumi Nümunə

Hava limanı düşünün. Uçuş `scheduled` → `boarding` → `departed` → `arrived` keçidlərindən keçir. `cancelled` istənilən yerə keçə bilər, amma `arrived` uçuş heç bir state-ə keçə bilməz (final). "Departed uçuşu boarding-ə qaytarmaq" — mümkün olmamalıdır; State Machine bunu avtomatik bloklamalıdır.

### PHP/Laravel Nümunəsi

**Variant 1: PHP 8.1+ Enum — dependency-free, sadə layihələr üçün:**

```php
<?php

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** Bu state-dən hansı state-lərə keçmək icazəlidir */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending   => [self::Paid, self::Cancelled],
            self::Paid      => [self::Shipped, self::Cancelled],
            self::Shipped   => [self::Delivered],
            // Final state-lər — heç bir keçid yoxdur
            self::Delivered,
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), strict: true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Gözlənilir',
            self::Paid      => 'Ödənilmişdir',
            self::Shipped   => 'Göndərilmişdir',
            self::Delivered => 'Çatdırılmışdır',
            self::Cancelled => 'Ləğv edilmişdir',
        };
    }
}

// Order entity — transition-ı özü idarə edir
class Order extends Model
{
    protected $casts = ['status' => OrderStatus::class];

    public function transitionTo(OrderStatus $next): void
    {
        if (!$this->status->canTransitionTo($next)) {
            throw new \DomainException(sprintf(
                'Cannot transition from "%s" to "%s"',
                $this->status->value,
                $next->value
            ));
        }

        $from = $this->status;
        $this->status = $next;
        $this->save();

        // Audit log
        $this->stateTransitions()->create([
            'from_state' => $from->value,
            'to_state'   => $next->value,
            'user_id'    => auth()->id(),
        ]);
    }

    public function stateTransitions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderStateTransition::class);
    }
}

// Servis — transition çağırır, validation etmir (entity özü edir)
class OrderService
{
    public function pay(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->transitionTo(OrderStatus::Paid);
            event(new OrderPaid($order));
        });
    }

    public function ship(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->transitionTo(OrderStatus::Shipped);
            event(new OrderShipped($order));
        });
    }
}
```

**Variant 2: Spatie laravel-model-states — Laravel-specific, daha feature-rich:**

```bash
composer require spatie/laravel-model-states
```

```php
<?php

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use Spatie\ModelStates\Transition;

// Base state class
abstract class OrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class,            Paid::class,       PayOrder::class)
            ->allowTransition(Paid::class,               Shipped::class,    ShipOrder::class)
            ->allowTransition(Shipped::class,            Delivered::class,  DeliverOrder::class)
            ->allowTransition([Pending::class, Paid::class], Cancelled::class, CancelOrder::class);
    }

    // Her state shared behavior təyin edə bilər
    abstract public function color(): string;
    abstract public function canRefund(): bool;
}

class Pending extends OrderState
{
    public function color(): string { return 'yellow'; }
    public function canRefund(): bool { return false; }
}

class Paid extends OrderState
{
    public function color(): string { return 'blue'; }
    public function canRefund(): bool { return true; }
}

class Shipped extends OrderState
{
    public function color(): string { return 'orange'; }
    public function canRefund(): bool { return true; }
}

class Delivered extends OrderState
{
    public function color(): string { return 'green'; }
    public function canRefund(): bool { return false; } // Refund window closed
}

class Cancelled extends OrderState
{
    public function color(): string { return 'red'; }
    public function canRefund(): bool { return false; }
}

// Typed Transition class — guard + action bir yerdə
class PayOrder extends Transition
{
    public function __construct(
        private readonly Order $order,
        private readonly string $paymentMethod,
    ) {}

    // Guard — transition şərti
    public function canTransition(): bool
    {
        return $this->order->total > 0;
    }

    // Action — transition zamanı əlavə iş
    public function handle(): Order
    {
        $this->order->payment_method = $this->paymentMethod;
        $this->order->paid_at = now();
        $this->order->save();

        event(new OrderPaid($this->order));

        return $this->order;
    }
}

// Model
class Order extends Model
{
    use \Spatie\ModelStates\HasStates;

    protected $casts = [
        'status' => OrderState::class,
    ];
}

// Servis istifadəsi
class OrderService
{
    public function pay(Order $order, string $paymentMethod): Order
    {
        // TransitionNotAllowed — icazəsiz keçid exception atar
        return $order->transitioning(to: Paid::class)
                     ->via(new PayOrder($order, $paymentMethod))
                     ->transition();
    }

    public function ship(Order $order): Order
    {
        return $order->transitioning(to: Shipped::class)
                     ->via(new ShipOrder($order))
                     ->transition();
    }
}

// Controller
class OrderController extends Controller
{
    public function pay(PayOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('pay', $order);

        try {
            $order = $this->orderService->pay($order, $request->payment_method);
            return response()->json(new OrderResource($order));
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotAllowed $e) {
            return response()->json(['error' => 'Invalid transition: ' . $e->getMessage()], 422);
        }
    }
}
```

**State history audit log:**

```php
<?php

// Migration
Schema::create('order_state_transitions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->string('from_state');
    $table->string('to_state');
    $table->foreignId('user_id')->nullable()->constrained();
    $table->json('metadata')->nullable(); // ip, reason, payment_method
    $table->timestamp('transitioned_at');
});

// OrderStateTransition model
class OrderStateTransition extends Model
{
    public $timestamps = false;

    protected $casts = [
        'metadata'        => 'array',
        'transitioned_at' => 'datetime',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

// "Niyə bu order cancelled oldu?" sualına cavab:
OrderStateTransition::where('order_id', 42)
    ->orderBy('transitioned_at')
    ->get()
    ->each(function ($t) {
        echo "{$t->from_state} → {$t->to_state} ({$t->user->name}, {$t->transitioned_at})";
    });
// pending → paid       (Ali Əliyev, 2024-01-10 10:00)
// paid    → cancelled  (Admin, 2024-01-10 10:30) [reason: duplicate order]
```

**State-specific behavior — State pattern ilə birləşmə:**

```php
<?php

// State class-ı özü davranış təyin edir — if/else chain yoxdur
abstract class OrderState extends State
{
    // Her state özünün "nə göstərilsin" bilir
    abstract public function getAvailableActions(): array;

    // State-specific label
    abstract public function getStatusBadge(): array; // ['label' => ..., 'color' => ...]
}

class Paid extends OrderState
{
    public function getAvailableActions(): array
    {
        return ['ship', 'cancel']; // paid-dan ship və ya cancel edə bilərsən
    }

    public function getStatusBadge(): array
    {
        return ['label' => 'Ödənilib', 'color' => 'blue'];
    }
}

class Shipped extends OrderState
{
    public function getAvailableActions(): array
    {
        return ['deliver']; // yalnız deliver edə bilərsən
    }

    public function getStatusBadge(): array
    {
        return ['label' => 'Göndərilib', 'color' => 'orange'];
    }
}

// Controller — state-i bilmir; state özü cavab verir
class OrderController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'order'             => new OrderResource($order),
            'available_actions' => $order->status->getAvailableActions(),
            'status_badge'      => $order->status->getStatusBadge(),
        ]);
    }
}
```

## Praktik Tapşırıqlar

1. `BlogPost` state machine yazın: `draft → review → published → archived`; `rejected` istənilən state-dən `draft`-a qaytara bilsin; Enum ilə implement edin; `transitionTo()` içinə audit log əlavə edin
2. `spatie/laravel-model-states` ilə `Subscription` state machine qurun: `trial → active → past_due → cancelled`; `PaySubscription` transition class-ı guard ilə (`subscription_price > 0`); state history-ni `subscription_transitions` cədvəlində saxlayın
3. `OrderState` abstract class-ına `canRefund(): bool` abstract method əlavə edin; hər state özü implement etsin; `RefundService` state-i bilmədən `$order->status->canRefund()` çağırsın
4. `OrderStateTransition` seed data ilə audit log test edin: bir order-ı bir neçə state-dən keçirin, tam tarixi `transitioned_at` ilə yoxlayın

## Əlaqəli Mövzular

- [../behavioral/07-state.md](../behavioral/07-state.md) — State design pattern: state-specific behavior encapsulation
- [05-event-listener.md](05-event-listener.md) — State transition-da event fire etmək
- [07-policy-handler-pattern.md](07-policy-handler-pattern.md) — Transition event → Policy → Command zənciri
- [14-unit-of-work.md](14-unit-of-work.md) — Transition + yan effektlər atomik transaction içindədir
- [../ddd/04-ddd-aggregates.md](../ddd/04-ddd-aggregates.md) — Aggregate öz state transition-larını idarə edir
- [../ddd/05-ddd-domain-events.md](../ddd/05-ddd-domain-events.md) — Hər transition domain event fire edir
