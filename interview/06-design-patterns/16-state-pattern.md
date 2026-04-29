# State Pattern (Lead ⭐⭐⭐⭐)

## İcmal

State Pattern — bir object-in daxili state-i dəyişdikdə onun davranışının da dəyişməsinə imkan verir. Object sanki öz class-ını dəyişmiş kimi görünür. State-ə aid bütün davranış ayrı class-lara köçürülür, main object isə yalnız cari state-ə delegation edir.

Gang of Four (GoF) behavioral pattern-lərindən biridir.

---

## Niyə Vacibdir

- **State machine complexity**: `if/switch` ilə state idarəetməsi mürəkkəbləşdikcə oxunması çətinləşir
- **Open/Closed Principle**: yeni state əlavə etmək mövcud kodu dəyişdirmir
- **State-specific logic isolation**: hər state-in öz davranışı ayrı class-da, test edilmək asandır
- **Illegal transition prevention**: yalnız icazəli keçidlər mümkündür, invalid state-lər compile/runtime-da tutulur

Real müsahibədə suallar:
- "Order state machine-ni necə implement edərdiniz?"
- "Strategy Pattern ilə State Pattern fərqi nədir?"
- "Saga Pattern-i state machine ilə birlikdə necə istifadə etmişsiniz?"

---

## Əsas Anlayışlar

**Context** — state-i saxlayan əsas object. Client bu object ilə işləyir.

**State interface** — bütün concrete state-lərin implement etdiyi interface. Context-in çağıra biləcəyi metodları müəyyən edir.

**Concrete State** — hər state üçün ayrı class. State-ə özəl davranışı ehtiva edir.

**State transition** — cari state-i dəyişdirmək. Ya Context, ya da Concrete State özü həyata keçirir.

```
Context ──────────────────────────────────────────────────────────────────────
│  - state: StateInterface                                                    │
│  + request(): void        ────────────────> StateInterface                  │
│  + setState(s): void                        + handle(context): void         │
│                                                    ▲                        │
│                                         ┌──────────┼──────────┐            │
│                                   PendingState  ActiveState  ClosedState   │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Strategy vs State fərqi:**
- **Strategy**: client özü strategy seçir, dəyişmir (stateless)
- **State**: state özünü dəyişdirir (state-driven transitions)

---

## Praktik Baxış

**Nə vaxt istifadə etmək:**
- Object-in davranışı state-dən asılıdır (order, payment, subscription, user account)
- State sayı 3+ olduqda və hər state-ə özəl davranış varsa
- Transition qaydaları mürəkkəbdirsə (yalnız `pending`-dən `processing`-ə keçid mümkündür)

**Nə vaxt istifadə etməmək:**
- Sadə 2-state toggle (active/inactive) — boolean kifayət edir
- State-ə özəl davranış yoxdursa — enum + switch daha sadədir
- State sayı çox azdırsa — over-engineering olur

**Trade-offs:**

| + Üstünlüklər | - Çatışmazlıqlar |
|---|---|
| State logic izolasiyası | Çox class yaranır |
| Yeni state əlavəsi asandır | Kiçik state machine üçün həddindən artıq |
| Illegal transition-ları önləyir | State arasında shared data idarəsi çətin ola bilər |
| Test edilmək asandır | |

**Common mistakes:**
- State-i string/int kimi saxlamaq (`'pending'`, `1`) — type safety yoxdur
- Transition logic-i Context-də saxlamaq — State-ə köçür
- State object-i mutable etmək — state-lər stateless olmalıdır (Context-i parametr kimi alırlar)

---

## Nümunələr

### Tipik Interview Sualı

"Order statusunu manage etmək üçün hansı pattern istifadə edərdiniz? Statusun yanlış keçid etməsinin qarşısını necə alarsınız?"

### Güclü Cavab

State Pattern istifadə edərdim. Hər status (`pending`, `processing`, `shipped`, `delivered`, `cancelled`) ayrı class-da implement olunur. `Order` context object yalnız cari state-ə delegation edir — hansı keçidin icazəli olduğunu bilmir.

İllegal transition avtomatik bloklanır: `PendingState.ship()` çağırılsa `LogicException` throw edir — controller-da `if ($order->status === 'processing')` yoxlamaq lazım gəlmir. Yeni state əlavə edəndə mövcud state class-larına toxunmuram — Open/Closed Principle.

Veritabanı ilə inteqrasiya: `status` string kimi saxlanılır, oxuyanda `match($this->status)` ilə doğru State class-ı qaytarılır.

### Anti-Pattern Nümunəsi

```php
// ❌ Anti-pattern — switch/if ilə state idarəetməsi
class Order
{
    public string $status = 'pending';

    public function ship(): void
    {
        switch ($this->status) {
            case 'pending':
                throw new \LogicException('Confirm first.');
            case 'processing':
                $this->status = 'shipped';
                break;
            case 'shipped':
                throw new \LogicException('Already shipped.');
            case 'delivered':
                throw new \LogicException('Already delivered.');
            case 'cancelled':
                throw new \LogicException('Cancelled.');
        }
    }

    public function deliver(): void
    {
        switch ($this->status) {
            case 'shipped':
                $this->status = 'delivered';
                break;
            default:
                throw new \LogicException("Cannot deliver from {$this->status}.");
        }
    }

    // Problem 1: Yeni status əlavə etmək → bütün switch-lərə toxunmaq (OCP pozulur)
    // Problem 2: Hər metod bütün possible state-ləri bilməlidir
    // Problem 3: Test zamanı state-ə görə hər metodu ayrıca test etmək çətindir
    // Problem 4: 5+ status + 5+ transition → 25+ switch case, spaghetti kod
}
```

### Order State Machine

```php
// State interface
interface OrderState
{
    public function confirm(Order $order): void;
    public function ship(Order $order): void;
    public function deliver(Order $order): void;
    public function cancel(Order $order): void;
    public function getName(): string;
}

// Context
class Order
{
    private OrderState $state;

    public function __construct(private string $id)
    {
        $this->state = new PendingState();
    }

    public function setState(OrderState $state): void
    {
        $this->state = $state;
    }

    public function confirm(): void  { $this->state->confirm($this); }
    public function ship(): void     { $this->state->ship($this); }
    public function deliver(): void  { $this->state->deliver($this); }
    public function cancel(): void   { $this->state->cancel($this); }

    public function getStateName(): string
    {
        return $this->state->getName();
    }
}

// Concrete States
class PendingState implements OrderState
{
    public function confirm(Order $order): void
    {
        $order->setState(new ProcessingState());
    }

    public function ship(Order $order): void
    {
        throw new \LogicException('Pending order cannot be shipped directly.');
    }

    public function deliver(Order $order): void
    {
        throw new \LogicException('Pending order cannot be delivered.');
    }

    public function cancel(Order $order): void
    {
        $order->setState(new CancelledState());
    }

    public function getName(): string { return 'pending'; }
}

class ProcessingState implements OrderState
{
    public function confirm(Order $order): void
    {
        throw new \LogicException('Order already confirmed.');
    }

    public function ship(Order $order): void
    {
        $order->setState(new ShippedState());
    }

    public function deliver(Order $order): void
    {
        throw new \LogicException('Must be shipped before delivered.');
    }

    public function cancel(Order $order): void
    {
        $order->setState(new CancelledState());
    }

    public function getName(): string { return 'processing'; }
}

class ShippedState implements OrderState
{
    public function confirm(Order $order): void
    {
        throw new \LogicException('Already confirmed.');
    }

    public function ship(Order $order): void
    {
        throw new \LogicException('Already shipped.');
    }

    public function deliver(Order $order): void
    {
        $order->setState(new DeliveredState());
    }

    public function cancel(Order $order): void
    {
        throw new \LogicException('Cannot cancel shipped order.');
    }

    public function getName(): string { return 'shipped'; }
}

class DeliveredState implements OrderState
{
    public function confirm(Order $order): void { throw new \LogicException('Already delivered.'); }
    public function ship(Order $order): void    { throw new \LogicException('Already delivered.'); }
    public function deliver(Order $order): void { throw new \LogicException('Already delivered.'); }
    public function cancel(Order $order): void  { throw new \LogicException('Cannot cancel delivered order.'); }

    public function getName(): string { return 'delivered'; }
}

class CancelledState implements OrderState
{
    public function confirm(Order $order): void { throw new \LogicException('Cancelled order cannot be confirmed.'); }
    public function ship(Order $order): void    { throw new \LogicException('Cancelled.'); }
    public function deliver(Order $order): void { throw new \LogicException('Cancelled.'); }
    public function cancel(Order $order): void  { throw new \LogicException('Already cancelled.'); }

    public function getName(): string { return 'cancelled'; }
}
```

İstifadəsi:

```php
$order = new Order('ORD-001');
echo $order->getStateName(); // pending

$order->confirm();
echo $order->getStateName(); // processing

$order->ship();
echo $order->getStateName(); // shipped

$order->deliver();
echo $order->getStateName(); // delivered

// Invalid transition
$order->cancel(); // throws LogicException: Cannot cancel delivered order.
```

---

### Laravel Eloquent ilə State Pattern

Veritabanında state persist etmək üçün:

```php
// Model
class Order extends Model
{
    protected $fillable = ['status'];

    public function getStateObject(): OrderState
    {
        return match ($this->status) {
            'pending'    => new PendingState(),
            'processing' => new ProcessingState(),
            'shipped'    => new ShippedState(),
            'delivered'  => new DeliveredState(),
            'cancelled'  => new CancelledState(),
            default      => throw new \InvalidArgumentException("Unknown status: {$this->status}"),
        };
    }

    public function transitionTo(string $action): void
    {
        $state = $this->getStateObject();
        $state->$action($this);
        $this->save();
    }
}

// State-lər artıq model-i update edir
class ProcessingState implements OrderState
{
    public function ship(Order $order): void
    {
        $order->status = 'shipped';
        // $order->save() → transitionTo() handle edir
    }
    // ...
}

// Controller
class OrderController extends Controller
{
    public function ship(Order $order): JsonResponse
    {
        $order->transitionTo('ship');
        return response()->json(['status' => $order->status]);
    }
}
```

---

### State Machine ilə Event Dispatch

```php
class ProcessingState implements OrderState
{
    public function ship(Order $order): void
    {
        $order->status = 'shipped';
        event(new OrderShipped($order));
    }
}

class ShippedState implements OrderState
{
    public function deliver(Order $order): void
    {
        $order->status = 'delivered';
        event(new OrderDelivered($order));
    }
}
```

---

## Praktik Tapşırıqlar

**Tapşırıq 1 — Payment State Machine**

Aşağıdakı state-ləri implement et:
- `pending` → `authorized` → `captured` → `refunded`
- `pending` → `failed`
- `authorized` → `voided`

Hər transition üçün qayda:
- `captured` state-dən yalnız `refunded`-ə keçid mümkündür
- `failed` state-dən heç bir keçid yoxdur (terminal state)

```php
interface PaymentState
{
    public function authorize(Payment $payment): void;
    public function capture(Payment $payment): void;
    public function refund(Payment $payment): void;
    public function void(Payment $payment): void;
}
```

---

**Tapşırıq 2 — User Account States**

`active` → `suspended` → `banned`  
`suspended` → `active` (reactivation)  
`banned` terminal state-dir (heç bir keçid yoxdur)

Test yaz: hər invalid transition `\LogicException` throw etdiyini assert et.

---

**Tapşırıq 3 — State-i database-dən hydrate et**

Mövcud order-ı DB-dən çəkib state-ə görə doğru `OrderState` object-ini qaytaran factory metodu yaz. `status` sütunu string saxlayır (`'pending'`, `'shipped'` və s.).

---

**Tapşırıq 4 — Audit log**

Hər state transition-ını `order_status_logs` cədvəlinə qeyd et:
```
order_id | from_status | to_status | changed_at
```

Bu logic-i harada saxlamaq daha düzgündür — Context-dəmi, Concrete State-dəmi, yoxsa Observer/Event-dəmi? Mühazirə edin.

---

## Əlaqəli Mövzular

- [05-strategy-pattern.md](05-strategy-pattern.md) — State vs Strategy fərqi; hər ikisi delegation istifadə edir
- [06-command-pattern.md](06-command-pattern.md) — Command + State kombinasiyası (undo/redo, CQRS)
- [04-observer-event.md](04-observer-event.md) — State transition-larında event dispatch
- [14-chain-of-responsibility.md](14-chain-of-responsibility.md) — State transition validation pipeline
- [15-specification-pattern.md](15-specification-pattern.md) — Transition guard-larını specification ilə ifadə etmək
