# State (Senior ⭐⭐⭐)

## İcmal
State pattern object-in daxili state-i dəyişəndə davranışının da dəyişməsinə imkan verir. Hər state üçün ayrı class var; object (Context) mövcud state-ə delegate edir. `if/switch` ilə dolu "God class" əvəzinə hər state öz davranışını özü müəyyən edir.

## Niyə Vacibdir
E-commerce order status, subscription lifecycle, user account state, document approval workflow — real Laravel layihələrinin çoxunda state machine lazımdır. `if ($order->status === 'pending') ... elseif ($order->status === 'processing')...` kodu getdikcə şişir. State pattern bunu idarə edilə bilən hala gətirir.

## Əsas Anlayışlar
- **Context**: state saxlayan object (Order, Subscription, Document); state-ə delegate edir
- **State interface**: hər state-in implement etdiyi ortaq interface (metodlar: canCancel, canShip, process)
- **ConcreteState**: konkret state class-ı (PendingState, ProcessingState, ShippedState)
- **State transition**: bir state başqa state-ə keçiş — ya State class-ında, ya da Context-də idarə olunur
- **Guard condition**: transition mümkündürsə izin ver, deyilsə exception at
- **Terminal state**: keçid olmayan son state (DeliveredState, CancelledState)

## Praktik Baxış
- **Real istifadə**: order lifecycle (pending → processing → shipped → delivered / cancelled), subscription (trial → active → past_due → cancelled), document approval (draft → submitted → under_review → approved / rejected), user account (active → suspended → banned)
- **Trade-off-lar**: state sayı artdıqca class sayı artır; transition logic scattered — bir state-in keçidi başqa state class-ında gizlənir; state-ləri DB-də saxlamaq üçün mapping lazımdır
- **İstifadə etməmək**: 2-3 sadə state olan hallarda (if/else kifayətdir); state-ə görə davranış fərqlənmirsə; state-lər arası transition qaydaları çox sadədirsə
- **Common mistakes**:
  1. Transition logic-i Context-də saxlamaq — state class-da olmalıdır
  2. State class-ına çox məsuliyyət yükləmək (business logic + transition + notification)
  3. Invalid transition-u exception atmadan susqun keçmək
  4. State object-ini stateful etmək — State class-lar ideally stateless olmalıdır
- **Anti-Pattern Nə Zaman Olur?**: State explosion — 8 state × 6 action = 48 metod kombinasiyası; hər state class-ında çox sayda `throw new InvalidStateTransitionException()` artdıqca maintenance çətin olur. Bu halda `spatie/laravel-model-states` kimi declarative package istifadə etmək daha oxunaqlıdır. Digər problem: state-lərin bir-birindən birbaşa asılı olması — `ShippedState` daxilindən `new DeliveredState()` yaratmaq; bütün state class-ları bir-birini tanıyır, yeni state əlavə etmək bütün mövcud state-ləri dəyişdirmək deməkdir. Transition mapping-i Context-ə (ya da ayrı Transition config-ə) köçür.

## Nümunələr

### Ümumi Nümunə
Trafik işığını düşünün. Qırmızı yandıqda keçmək qadağandır (canPass = false), sarı yandıqda hazırlaşmaq lazımdır, yaşıl yandıqda keçmək olar. Hər işıq rəngi özünəməxsus davranışa malikdir. "İşıq qırmızıdırsa..." əvəzinə hər rəng öz davranışını özü bilir.

### PHP/Laravel Nümunəsi

**State interface + ConcreteStates:**

```php
<?php

// State interface — hər state-in implement etməli olduğu metodlar
interface OrderState
{
    public function canCancel(): bool;
    public function canShip(): bool;
    public function canDeliver(): bool;
    public function process(Order $order): void;
    public function cancel(Order $order): void;
    public function ship(Order $order): void;
    public function deliver(Order $order): void;
    public function getStatusName(): string;
}

// Abstract base — default: invalid transition
abstract class AbstractOrderState implements OrderState
{
    public function canCancel(): bool  { return false; }
    public function canShip(): bool    { return false; }
    public function canDeliver(): bool { return false; }

    public function process(Order $order): void
    {
        throw new InvalidStateTransitionException(
            "Cannot process from state: " . $this->getStatusName()
        );
    }

    public function cancel(Order $order): void
    {
        throw new InvalidStateTransitionException(
            "Cannot cancel from state: " . $this->getStatusName()
        );
    }

    public function ship(Order $order): void
    {
        throw new InvalidStateTransitionException(
            "Cannot ship from state: " . $this->getStatusName()
        );
    }

    public function deliver(Order $order): void
    {
        throw new InvalidStateTransitionException(
            "Cannot deliver from state: " . $this->getStatusName()
        );
    }
}
```

**Concrete State class-ları:**

```php
// State 1: Pending — ilkin state
class PendingState extends AbstractOrderState
{
    public function canCancel(): bool { return true; }

    public function process(Order $order): void
    {
        // Payment yoxla, inventory rezerv et
        app(PaymentService::class)->charge($order);
        app(InventoryService::class)->reserve($order);

        $order->transitionTo(new ProcessingState(), 'processing');
        event(new OrderProcessingStarted($order));
    }

    public function cancel(Order $order): void
    {
        $order->transitionTo(new CancelledState(), 'cancelled');
        event(new OrderCancelled($order, 'cancelled_by_user'));
    }

    public function getStatusName(): string { return 'pending'; }
}

// State 2: Processing
class ProcessingState extends AbstractOrderState
{
    public function canCancel(): bool { return true; }
    public function canShip(): bool   { return true; }

    public function cancel(Order $order): void
    {
        // Ödəniş geri qaytar
        app(PaymentService::class)->refund($order);
        app(InventoryService::class)->release($order);

        $order->transitionTo(new CancelledState(), 'cancelled');
        event(new OrderCancelled($order, 'cancelled_during_processing'));
    }

    public function ship(Order $order): void
    {
        $trackingNumber = app(ShippingService::class)->createShipment($order);
        $order->tracking_number = $trackingNumber;

        $order->transitionTo(new ShippedState(), 'shipped');
        event(new OrderShipped($order));
    }

    public function getStatusName(): string { return 'processing'; }
}

// State 3: Shipped
class ShippedState extends AbstractOrderState
{
    public function canDeliver(): bool { return true; }

    // Shipped state-dən cancel mümkün deyil — abstract-dan gəlir (exception)

    public function deliver(Order $order): void
    {
        $order->delivered_at = now();
        $order->transitionTo(new DeliveredState(), 'delivered');
        event(new OrderDelivered($order));
    }

    public function getStatusName(): string { return 'shipped'; }
}

// State 4: Delivered — terminal state
class DeliveredState extends AbstractOrderState
{
    // Heç bir transition yoxdur — hamısı exception atar (abstract-dan)
    public function getStatusName(): string { return 'delivered'; }
}

// State 5: Cancelled — terminal state
class CancelledState extends AbstractOrderState
{
    public function getStatusName(): string { return 'cancelled'; }
}
```

**Context — Order class:**

```php
class Order extends Model
{
    protected $casts = [
        'status' => OrderStatusEnum::class, // Eloquent enum cast
    ];

    // DB status → State object mapping
    private static array $stateMap = [
        'pending'    => PendingState::class,
        'processing' => ProcessingState::class,
        'shipped'    => ShippedState::class,
        'delivered'  => DeliveredState::class,
        'cancelled'  => CancelledState::class,
    ];

    // Mövcud state object-ini al
    public function getState(): OrderState
    {
        $stateClass = self::$stateMap[$this->status]
            ?? throw new \RuntimeException("Unknown state: {$this->status}");

        return new $stateClass();
    }

    // State transition — DB-ni yenilə + state yenilə
    public function transitionTo(OrderState $newState, string $statusValue): void
    {
        $this->update([
            'status'               => $statusValue,
            'status_changed_at'    => now(),
            'previous_status'      => $this->status,
        ]);
    }

    // Convenient delegate metodlar
    public function process(): void  { $this->getState()->process($this); }
    public function cancel(): void   { $this->getState()->cancel($this); }
    public function ship(): void     { $this->getState()->ship($this); }
    public function deliver(): void  { $this->getState()->deliver($this); }

    public function canCancel(): bool  { return $this->getState()->canCancel(); }
    public function canShip(): bool    { return $this->getState()->canShip(); }
    public function canDeliver(): bool { return $this->getState()->canDeliver(); }
}
```

**State transition diagram:**

```
         ┌──────────────────────┐
         │                      │
[Pending] ──process()──▶ [Processing] ──ship()──▶ [Shipped] ──deliver()──▶ [Delivered]
    │                       │
    └──cancel()──▶ [Cancelled] ◀──cancel()──┘

Terminal states: Delivered, Cancelled (heç bir transition yoxdur)
```

**İstifadəsi:**

```php
// Controller — sadə, State pattern arxasında gizlənib
class OrderController
{
    public function process(Order $order): JsonResponse
    {
        try {
            $order->process(); // State-in process() metodunu çağırır
            return response()->json(['status' => $order->fresh()->status]);
        } catch (InvalidStateTransitionException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Order $order): JsonResponse
    {
        if (!$order->canCancel()) {
            return response()->json(['error' => 'Order cannot be cancelled at this stage'], 422);
        }

        $order->cancel();
        return response()->json(['status' => $order->fresh()->status]);
    }
}
```

**Eloquent Enum cast:**

```php
// PHP 8.1+ Backed Enum
enum OrderStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Shipped    = 'shipped';
    case Delivered  = 'delivered';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'Pending Payment',
            self::Processing => 'Being Processed',
            self::Shipped    => 'On the Way',
            self::Delivered  => 'Delivered',
            self::Cancelled  => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled]);
    }
}

// Model cast
class Order extends Model
{
    protected $casts = [
        'status' => OrderStatus::class,
    ];
}

// İstifadəsi
$order->status;          // OrderStatus enum instance
$order->status->value;   // 'pending' string
$order->status->label(); // 'Pending Payment'
$order->status === OrderStatus::Pending; // true
```

**Laravel packages — production-ready state machines:**

```php
// Package 1: spatie/laravel-model-states
use Spatie\ModelStates\HasStates;
use Spatie\ModelStates\Transition;

class Order extends Model
{
    use HasStates;

    protected function registerStates(): void
    {
        $this->addState('status', OrderStatus::class)
            ->default(PendingOrderStatus::class)
            ->allowTransition(PendingOrderStatus::class, ProcessingOrderStatus::class)
            ->allowTransition(ProcessingOrderStatus::class, ShippedOrderStatus::class)
            ->allowTransition(ShippedOrderStatus::class, DeliveredOrderStatus::class)
            ->allowTransition([PendingOrderStatus::class, ProcessingOrderStatus::class], CancelledOrderStatus::class);
    }
}

// Transition object — transition logic burada
class PendingToProcessing extends Transition
{
    public function handle(): Order
    {
        $this->model->update(['processed_at' => now()]);
        event(new OrderProcessingStarted($this->model));
        return $this->model;
    }
}

// İstifadəsi
$order->status->transitionTo(ProcessingOrderStatus::class);
// Və ya:
$order->status->transition(new PendingToProcessing($order));

// Package 2: asantibanez/laravel-eloquent-state-machines
use Asantibanez\LaravelEloquentStateMachines\Traits\HasStateMachines;

class Order extends Model
{
    use HasStateMachines;

    public function stateMachines(): array
    {
        return ['status' => OrderStatusStateMachine::class];
    }
}
```

**Without State pattern — anti-pattern (God class):**

```php
// BAD: bütün state logic bir yerdə — mürəkkəbləşir
class Order extends Model
{
    public function process(): void
    {
        if ($this->status === 'pending') {
            // ... 20 sətir
        } elseif ($this->status === 'processing') {
            throw new Exception('Already processing');
        } elseif ($this->status === 'shipped') {
            throw new Exception('Already shipped');
        } // ... davam edir

        // Yeni state əlavə etmək üçün bütün bu metodları dəyişmək lazımdır
    }

    public function cancel(): void
    {
        if ($this->status === 'pending') {
            // ...
        } elseif ($this->status === 'processing') {
            // refund logic + ...
        } elseif ($this->status === 'shipped') {
            throw new Exception('Cannot cancel shipped order');
        } elseif ($this->status === 'delivered') {
            throw new Exception('Cannot cancel delivered order');
        }
    }
}
```

## Praktik Tapşırıqlar
1. `Subscription` model üçün state machine qurun: `TrialState` → `ActiveState` → `PastDueState` → `CancelledState`; hər state-in `canUpgrade()`, `canDowngrade()`, `expire()` metodları olsun
2. `spatie/laravel-model-states` istifadə edərək mövcud `Order` modelini migrate edin; transition event-lərini queue-ya atın
3. `DocumentApproval` state machine yazın: `DraftState` → `SubmittedState` → `UnderReviewState` → `ApprovedState`/`RejectedState`; rejected state-dən `DraftState`-ə qayıtmaq mümkün olsun

## Əlaqəli Mövzular
- [06-chain-of-responsibility.md](06-chain-of-responsibility.md) — State transition zamanı validation chain
- [../laravel/05-event-listener.md](../laravel/05-event-listener.md) — State dəyişikliyinə görə event fire etmək
- [02-strategy.md](02-strategy.md) — State vs Strategy: hər ikisi davranışı dəyişir amma fərqli məqsədlə
- [../laravel/02-service-layer.md](../laravel/02-service-layer.md) — State transition logic-ini service-ə köçürmək
- [../laravel/09-state-machine-workflow.md](../laravel/09-state-machine-workflow.md) — Laravel State Machine production tətbiqi
