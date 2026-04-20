# State Machine & Workflow Engine

## Mündəricat
1. [State machine nədir?](#state-machine-nədir)
2. [Finite State Machine (FSM)](#finite-state-machine-fsm)
3. [State vs Workflow](#state-vs-workflow)
4. [Symfony Workflow](#symfony-workflow)
5. [Laravel alternativləri](#laravel-alternativləri)
6. [Enum-based state machine (PHP 8.1+)](#enum-based-state-machine-php-81)
7. [Guards, actions, transitions](#guards-actions-transitions)
8. [Persist state (DB)](#persist-state-db)
9. [Long-running workflow (Temporal, Camunda)](#long-running-workflow-temporal-camunda)
10. [Real-world nümunələr](#real-world-nümunələr)
11. [İntervyu Sualları](#intervyu-sualları)

---

## State machine nədir?

```
State Machine — obyekti sonlu sayda STATE arasında idarə edən model.
Bir vaxtda yalnız 1 state-də olur, transition-lar açıq tanımlı.

Niyə lazımdır?
  "Bu order status-u 'shipped' idi, sonra 'cancelled' oldu — ola bilər?"
  "Payment 'failed'-dən 'completed'-ə keçə bilərmi?"
  "Draft post publish-ə keçmədən Archive-a keçə bilərmi?"

Bu "iş məntiqi" kodda pərakəndə yazılırsa — bug yaranır.
State machine bunu mərkəziləşdirir.

Nümunə: Order state machine

    pending ──pay──▶ paid ──ship──▶ shipped ──deliver──▶ delivered
       │              │               │
       │              │               └────return──▶ returned
       │              │
       └──cancel──▶ cancelled ◀──cancel──┘
```

---

## Finite State Machine (FSM)

```
FSM üç əsas element:
  1. States (sonlu sayda)
  2. Events / Transitions (event X state A → state B-yə keçirir)
  3. Initial state, final state(s)

Formal tərif:
  M = (Q, Σ, δ, q0, F)
  Q  = states {pending, paid, shipped, delivered, cancelled}
  Σ  = events {pay, ship, deliver, cancel}
  δ  = transition funksiyası
  q0 = initial state (pending)
  F  = final states {delivered, cancelled}

Deterministic vs Non-deterministic:
  DFSM: bir state + bir event → DƏQİQ 1 keçid
  NFSM: bir state + bir event → çox keçid (nadir istifadə)

İş aləmində əksər FSM deterministic-dir.
```

---

## State vs Workflow

```
Symfony terminologiyası:

State Machine:
  Obyekt BİR state-də olur (single marking)
  Order: pending OR paid OR shipped — eyni anda yalnız 1

Workflow (Petri net):
  Obyekt BİRDƏN ÇOX state-də ola bilər (multiple markings)
  Order: "waiting_approval" + "payment_pending" eyni anda

Fərq:
  State machine — sadə, ardıcıl flow
  Workflow     — paralel branch-lar (approval + payment ayrı yollarda)

Əksər case-lərdə state machine kifayət edir.
Kompleks business process (ERP) — workflow lazım ola bilər.
```

---

## Symfony Workflow

```bash
composer require symfony/workflow
```

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        order:
            type: state_machine     # və ya workflow
            marking_store:
                type: 'method'
                property: 'status'
            supports:
                - App\Entity\Order
            initial_marking: pending
            places:
                - pending
                - paid
                - shipped
                - delivered
                - cancelled
                - returned
            transitions:
                pay:
                    from: pending
                    to:   paid
                cancel:
                    from: [pending, paid]
                    to:   cancelled
                ship:
                    from: paid
                    to:   shipped
                deliver:
                    from: shipped
                    to:   delivered
                return:
                    from: shipped
                    to:   returned
```

```php
<?php
namespace App\Entity;

class Order
{
    public string $status = 'pending';
    public int $id;
    public float $total;
}

// Service-də istifadə
use Symfony\Component\Workflow\WorkflowInterface;

class OrderService
{
    public function __construct(
        private WorkflowInterface $orderStateMachine,
    ) {}
    
    public function pay(Order $order): void
    {
        if (!$this->orderStateMachine->can($order, 'pay')) {
            throw new \LogicException(
                "Cannot pay in state: {$order->status}"
            );
        }
        
        $this->orderStateMachine->apply($order, 'pay');
        // $order->status artıq 'paid'
    }
    
    public function cancel(Order $order): void
    {
        // can() false qaytarır şayəd keçid icazəsi yoxdursa
        if ($this->orderStateMachine->can($order, 'cancel')) {
            $this->orderStateMachine->apply($order, 'cancel');
        }
    }
    
    public function availableTransitions(Order $order): array
    {
        $transitions = $this->orderStateMachine->getEnabledTransitions($order);
        return array_map(fn($t) => $t->getName(), $transitions);
    }
}
```

---

## Laravel alternativləri

```bash
# spatie/laravel-model-states
composer require spatie/laravel-model-states
```

```php
<?php
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

// Base state
abstract class OrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Paid::class)
            ->allowTransition(Paid::class, Shipped::class)
            ->allowTransition(Shipped::class, Delivered::class)
            ->allowTransition([Pending::class, Paid::class], Cancelled::class);
    }
    
    abstract public function color(): string;
}

class Pending extends OrderState {
    public function color(): string { return 'yellow'; }
}
class Paid extends OrderState {
    public function color(): string { return 'blue'; }
}
class Shipped extends OrderState {
    public function color(): string { return 'green'; }
}
class Delivered extends OrderState {
    public function color(): string { return 'darkgreen'; }
}
class Cancelled extends OrderState {
    public function color(): string { return 'red'; }
}

// Model
class Order extends Model
{
    use HasStates;
    
    protected $casts = [
        'status' => OrderState::class,
    ];
}

// İstifadə
$order = Order::find(1);
$order->status;              // Pending instance
$order->status->color();     // 'yellow'

$order->status->canTransitionTo(Paid::class);  // true
$order->status->transitionTo(Paid::class);
$order->save();

// İcazə olmayan keçid:
$order->status->transitionTo(Delivered::class);  // TransitionNotAllowed!
```

---

## Enum-based state machine (PHP 8.1+)

```php
<?php
// PHP 8.1+ Enum — sadə state machine (dependency-free)
enum OrderStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    
    public function canTransitionTo(self $next): bool
    {
        return match($this) {
            self::Pending   => in_array($next, [self::Paid, self::Cancelled]),
            self::Paid      => in_array($next, [self::Shipped, self::Cancelled]),
            self::Shipped   => $next === self::Delivered,
            self::Delivered,
            self::Cancelled => false,
        };
    }
    
    public function transitionsFrom(): array
    {
        return match($this) {
            self::Pending   => [self::Paid, self::Cancelled],
            self::Paid      => [self::Shipped, self::Cancelled],
            self::Shipped   => [self::Delivered],
            default         => [],
        };
    }
    
    public function isFinal(): bool
    {
        return match($this) {
            self::Delivered, self::Cancelled => true,
            default => false,
        };
    }
}

// İstifadə
class Order
{
    public function __construct(public OrderStatus $status = OrderStatus::Pending) {}
    
    public function changeStatus(OrderStatus $next): void
    {
        if (!$this->status->canTransitionTo($next)) {
            throw new \DomainException(
                "Cannot transition from {$this->status->value} to {$next->value}"
            );
        }
        $this->status = $next;
    }
}

// Bu pattern çox sadədir, kiçik-orta layihələrdə əladır.
// Mürəkkəb guard/action/event lazımdırsa — Symfony Workflow istifadə et.
```

---

## Guards, actions, transitions

```
Güclü state machine xüsusiyyətləri:

1. GUARD — transition şərti
   "Yalnız user admin-dirsə bu keçidi icazə ver"

2. ACTION — transition zamanı icra olunan iş
   "paid-ə keçəndə email göndər, inventory azalt"

3. EVENT — transition adı (verb)
   "pay", "ship", "cancel"

4. ENTRY/EXIT ACTION — state daxil/çıxanda
   "shipped state-inə girəndə kuryerə bildir"
```

```yaml
# Symfony Workflow — guards + events
framework:
    workflows:
        order:
            # ... yuxarıdakı config
            transitions:
                pay:
                    guard: "subject.total > 0 and is_granted('ROLE_USER')"
                    from: pending
                    to:   paid
                cancel:
                    guard: "is_granted('ROLE_USER') and (subject.total < 100 or is_granted('ROLE_ADMIN'))"
                    from: [pending, paid]
                    to:   cancelled
```

```php
<?php
// Event listener — transition hadisələri
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\Event;

class OrderStateListener
{
    public function __construct(
        private EmailService $email,
        private InventoryService $inventory,
    ) {}
    
    #[AsEventListener(event: 'workflow.order.transition.pay')]
    public function onPay(Event $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();
        $this->email->sendReceipt($order);
    }
    
    #[AsEventListener(event: 'workflow.order.entered.shipped')]
    public function onShipped(Event $event): void
    {
        $order = $event->getSubject();
        $this->inventory->decrement($order->items);
    }
    
    // Guard event — programmatic
    #[AsEventListener(event: 'workflow.order.guard.cancel')]
    public function onCancelGuard(GuardEvent $event): void
    {
        $order = $event->getSubject();
        if ($order->is_locked_for_audit) {
            $event->setBlocked(true, 'Order locked for audit');
        }
    }
}
```

---

## Persist state (DB)

```php
<?php
// State DB-də saxlanır
// Migration
Schema::create('orders', function (Blueprint $t) {
    $t->id();
    $t->enum('status', [
        'pending', 'paid', 'shipped', 'delivered', 'cancelled'
    ])->default('pending');
    $t->jsonb('status_history');  // transition audit
    $t->timestamps();
});

// Audit log — hər transition DB-də qeydiyyat
class StateTransitionAuditor
{
    public function log(Order $order, string $event, string $from, string $to): void
    {
        OrderStateLog::create([
            'order_id'  => $order->id,
            'event'     => $event,
            'from_state'=> $from,
            'to_state'  => $to,
            'user_id'   => auth()->id(),
            'metadata'  => ['ip' => request()->ip()],
        ]);
    }
}

// Use case: "Niyə bu order cancelled oldu?"
OrderStateLog::where('order_id', 42)->orderBy('created_at')->get();
//  pending → paid       (user 1, 10:00)
//  paid    → cancelled  (admin 5, 10:30)
```

---

## Long-running workflow (Temporal, Camunda)

```
Simple FSM bir HTTP request-də yerləşir.
Amma bəzi "workflow"-lar GÜNLƏRLƏ, HAFTALARLA davam edir.

Order fulfillment:
  Day 1: Paid
  Day 2: Shipped (warehouse)
  Day 5: At customs
  Day 7: Delivered
  Day 30: Return window closes

Həllər:
  1. Cron + state polling (sadə, amma fragile)
  2. Temporal.io
  3. Camunda BPMN
  4. AWS Step Functions
  5. Netflix Conductor

Temporal.io (Go/Java/Python):
  - Durable workflow execution
  - Automatic retry, timeouts
  - Exactly-once event processing
  - Replay-based recovery

Camunda BPMN:
  - Visual BPMN 2.0 editor
  - Business analyst + developer shared model
  - Human task (approval)
  - Java-based, REST API
  - PHP client var (camunda-platform-7-php-client)

Nümunə workflow:
  [Start] → [Payment] → [Check inventory]
                         ├─(stocked)→ [Ship] → [Deliver] → [End]
                         └─(out of stock)→ [Backorder] → [Notify customer] → ...
```

---

## Real-world nümunələr

```
Use case                   | States                                   | Events
──────────────────────────────────────────────────────────────────────────────
E-commerce order           | pending, paid, shipped, delivered, ...   | pay, ship, deliver
Subscription               | trial, active, past_due, cancelled       | renew, fail_charge, cancel
Document approval          | draft, submitted, approved, rejected     | submit, approve, reject
Blog post                  | draft, scheduled, published, archived    | publish, archive
Support ticket             | open, in_progress, resolved, closed      | assign, resolve, reopen
User registration          | pending_email, active, locked, deleted   | verify, login, lock
Restaurant order           | placed, preparing, ready, served, paid   | prepare, serve, close
Delivery                   | assigned, picked_up, in_transit, delivered | pickup, transit, drop
Payment                    | initiated, authorized, captured, refunded | authorize, capture, refund
Insurance claim            | submitted, reviewing, approved, paid, rejected | review, approve, pay
```

---

## İntervyu Sualları

- State machine ilə workflow arasındakı fərq nədir?
- Symfony Workflow-da `state_machine` və `workflow` type fərqi?
- FSM-i kod-hardcode vs library ilə yazmağın fərqi?
- Guard nə üçündür? Nümunə ilə izah edin.
- State-in DB-də saxlanması — enum vs string column?
- Long-running workflow-u niyə Temporal kimi alət lazımdır?
- Order state machine-də "illegal transition" necə qarşısı alınır?
- PHP 8.1 Enum-da state machine necə qurulur?
- Spatie Laravel Model States paketinin üstünlüyü nədir?
- Event listener state transition-larda nəyi handle etməlidir?
- Audit log niyə state machine-nin vacib bir hissəsidir?
- Paralel branch (iki state eyni anda) nə zaman lazımdır?
