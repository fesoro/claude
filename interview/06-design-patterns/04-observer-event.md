# Observer / Event Pattern (Middle ⭐⭐)

## İcmal
Observer pattern — bir object (subject/publisher) vəziyyəti dəyişdikdə ona subscribe olmuş bütün object-ləri (observers/subscribers) avtomatik bildirən behavioral design pattern-dir. Event-driven architecture-in əsasıdır. Laravel Events, DOM event listeners, Kafka consumer-lar, WebSocket message handlers — bunların hamısı Observer pattern-in müxtəlif abstraction səviyyəsindəki tətbiqlərdir.

## Niyə Vacibdir
Observer pattern — loose coupling-in ən güclü nümunələrindən biridir. Subject observer-ları tanımır, yalnız interface-ini bilir. User qeydiyyat olduqda email göndərmək, audit log yazmaq, CRM-ə push etmək — `UserRegistered` event ilə hamısı ayrı listener-larda handle olunur. Yeni action üçün mövcud koda toxunmaq lazım deyil. Interviewer bu mövzunu verəndə event-driven thinking-inizi yoxlayır.

---

## Əsas Anlayışlar

- **Subject (Publisher):** Vəziyyəti dəyişir, observer-ları saxlayır, xəbərdar edir — `attach()`, `detach()`, `notify()`
- **Observer (Subscriber):** `update()` metodu ilə bildiriş alır; subject-in interface-ini bilir, konkret class-ını yox
- **Push Model:** Subject hər observer-ə bütün data-nı göndərir — sadə, lakin observer lazımsız data alır
- **Pull Model:** Subject yalnız "dəyişdi" deyir, observer özü state-i soruşur — daha flexible, əlavə coupling
- **Synchronous Observer:** Observer-lar sırayla çağırılır; bir observer yavaş olsa hamı gözləyir — response time uzanır
- **Asynchronous Observer:** Observer-lar queue-ya göndərilir (Laravel `ShouldQueue`) — main flow bloklanmır; retry mümkündür
- **Event vs Observer:** Observer: direct object reference; Event: event bus/dispatcher vasitəsilə — publisher subscriber-ı tanımır
- **Event Bus:** Central dispatcher — event publish edilir, subscriber-lar register olur; pub/sub variant
- **Weak Reference:** Observer list güclü referans saxlarsa, observer GC tərəfindən silinə bilmir — memory leak; explicit `detach()` lazımdır
- **Cascade Notification:** Bir event çox observer trigger edir, hər observer başqa event trigger edir — cascade, infinite loop riski
- **Laravel Event System:** `Event::dispatch()`, `EventServiceProvider::$listen`, `ShouldQueue`, `ShouldBroadcast`
- **Domain Event vs Application Event:** Domain: business-significant (UserRegistered, OrderPlaced); Application: technical (CacheCleared, FileUploaded)
- **Event Sourcing:** Hər state dəyişikliyi event kimi saxlanılır — Observer pattern-in daha advanced tətbiqi; event log = audit trail
- **CQRS + Event:** Command yazır, event trigger edir, projection event dinləyir — read/write ayrılığı
- **Observer Mediator Fərqi:** Observer: direct notification; Mediator: central hub — observer-lar bir-birini tanımır, yalnız mediator-ı bilir
- **ShouldBroadcast:** Laravel event-ini WebSocket kanalına yayır — Pusher, Laravel Echo; real-time UI update
- **Event Payload Best Practice:** Eloquent model göndərmə — serialization/queue restart-da model dəyişmiş ola bilər; ID + minimal data göndər
- **Listener Priority:** Bəzi dispatcher-lar priority dəstəkləyir — Laravel default sıralı çağırır; PSR-14-da `ListenerProvider` order idarə edir

---

## Praktik Baxış

**Interview-da yanaşma:**
- "User qeydiyyat olduqda nə lazımdır?" sualı event-driven yanaşmanı göstərmək üçün ideal nümunədir
- Sync vs async listener fərqini mütləq izah edin — hansı halda ShouldQueue?
- OCP ilə əlaqəsini vurğulayın: yeni listener = yeni class, mövcud kod dəyişmir

**Follow-up suallar:**
1. "Observer vs Mediator fərqi?" — Observer: direct notification, observer-lar subject-i bilə bilər; Mediator: central hub, observer-lar bir-birini tanımır
2. "Laravel event-ləri sync vs async nə zaman?" — Email, SMS, external API → async (ShouldQueue); audit log, cache invalidation, critical path → sync ola bilər
3. "Event sourcing nədir?" — Hər state dəyişikliyi event kimi saxlanılır; current state = event-lərin replay-i; audit trail pulsuz gəlir
4. "Domain events vs application events?" — Domain: business logic; Application: infrastructure (file uploaded, cache cleared)
5. "Event payload-da Eloquent model göndərmək niyə problematik?" — Queue restart-da model DB-dən yenidən yüklənir; queue dispatch-dan sonra model dəyişmişsə listener köhnə data görür; serialization size böyük
6. "Observer cascade problemi necə həll olunur?" — Event guard: listener-in event trigger etməsini idarə et; `once` flag; max depth limit; circular detection

**Code review red flags:**
- `ShouldQueue` olmadan uzun email/external API call-ı sync listener-da
- Listener-da `Event::dispatch(new AnotherEvent())` — cascade risk
- Event payload-da tam Eloquent model göndərmə — serialization/freshness problem
- Listener-larda business logic paylaşımı — hər listener müstəqil olmalıdır
- Observer-da `detach()` çağırılmır — memory leak

**Production debugging ssenariləri:**
- Queue worker durdu, listener-lər işləmir — event dispatch edildi amma process yoxdur; monitoring gərəkdir
- Listener retry storm: email provider down, 1000 job retry edib mail server-i DDos edir — exponential backoff, max tries
- Cascade event infinite loop: `OrderUpdated` → `RecalculateInventory` → `OrderUpdated` → ... — stack overflow ya memory exhaustion
- Event payload stale data: Queue-da bekleyen listener `user->email` oxuyur, artıq dəyişib; ID saxla, listener-da reload et

---

## Nümunələr

### Tipik Interview Sualı
"In your UserService, after a user registers, you need to: send a welcome email, create a CRM contact, assign a free trial, and log the event. How would you design this?"

### Güclü Cavab
Bu use-case Observer/Event pattern-in parlaq nümunəsidir. Bütün bu action-ları `UserService::register()` içinə yazmaq SRP pozur, gələcəkdə yeni action əlavə etmək üçün bu metoda toxunmaq lazım olacaq.

Event-driven yanaşma: `UserRegistered` event dispatch edilir. Hər action ayrı listener-dadır: `SendWelcomeEmail`, `CreateCrmContact`, `AssignFreeTrial`, `LogRegistration` — hər biri müstəqil, ayrıca test edilə bilən class-lardır.

Email göndərmə `ShouldQueue` ilə async olur — user dərhal cavab alır, email arxa planda göndərilir. Yeni action lazım olduqda (məs: `SendToSlackChannel`) — sadəcə yeni Listener yazılır, mövcud koda toxunulmur. OCP tam tətbiq olundu.

### Kod Nümunəsi

```php
// ── Event Class — minimal payload ────────────────────────────────
// DÜZGÜN: ID + minimal data göndər — Eloquent model yox
class UserRegistered
{
    public function __construct(
        public readonly int    $userId,       // Tam model deyil, ID
        public readonly string $email,
        public readonly string $name,
        public readonly \Carbon\Carbon $registeredAt,
    ) {}
}

// ── Async Listener: Email ─────────────────────────────────────────
class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue    = 'notifications';
    public int    $tries    = 3;
    public int    $backoff  = 60; // Retry-lər arası 60s

    public function handle(UserRegistered $event): void
    {
        // Listener-da yenidən yüklə — ID saxladığımız üçün mümkün
        $user = User::findOrFail($event->userId);
        Mail::to($event->email)->send(new WelcomeMail($user));
    }

    public function failed(UserRegistered $event, \Throwable $e): void
    {
        Log::error('Welcome email failed', [
            'user_id' => $event->userId,
            'error'   => $e->getMessage(),
        ]);
        // Notify DevOps ya da DLQ-ya göndər
        Notification::route('slack', '#errors')->notify(
            new ListenerFailedNotification('SendWelcomeEmail', $e)
        );
    }
}

// ── Async Listener: CRM Integration ─────────────────────────────
class CreateCrmContact implements ShouldQueue
{
    public string $queue    = 'integrations';
    public int    $tries    = 5;
    public array  $backoff  = [30, 60, 120]; // Exponential backoff

    public function handle(UserRegistered $event): void
    {
        retry(3, function () use ($event) {
            app(CrmService::class)->createContact([
                'email'  => $event->email,
                'name'   => $event->name,
                'source' => 'registration',
                'date'   => $event->registeredAt->toIso8601String(),
            ]);
        }, sleepMilliseconds: 500);
    }
}

// ── Async Listener: Free Trial ────────────────────────────────────
class AssignFreeTrial implements ShouldQueue
{
    public string $queue = 'billing';

    public function handle(UserRegistered $event): void
    {
        SubscriptionService::assignTrial(
            userId: $event->userId,
            plan:   'free',
            days:   14,
        );
    }
}

// ── Sync Listener: Audit Log — ani yazılmalıdır ────────────────────
class LogRegistrationEvent
{
    public function handle(UserRegistered $event): void
    {
        AuditLog::create([
            'event'       => 'user.registered',
            'user_id'     => $event->userId,
            'occurred_at' => $event->registeredAt,
            'metadata'    => ['source' => 'web'],
        ]);
    }
}

// ── EventServiceProvider: mapping ─────────────────────────────────
protected $listen = [
    UserRegistered::class => [
        SendWelcomeEmail::class,
        CreateCrmContact::class,
        AssignFreeTrial::class,
        LogRegistrationEvent::class, // Sync — ShouldQueue yoxdur
    ],
];

// ── Service: yalnız event dispatch ────────────────────────────────
class UserRegistrationService
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {}

    public function register(RegisterUserDTO $dto): User
    {
        $user = DB::transaction(function () use ($dto) {
            return $this->repository->create([
                'name'     => $dto->name,
                'email'    => $dto->email,
                'password' => Hash::make($dto->password),
            ]);
        });

        // Transaction commit-dən sonra dispatch et
        // (afterCommit()) — rollback-da event atılmasın
        UserRegistered::dispatch(
            $user->id,
            $user->email,
            $user->name,
            now()
        );

        return $user;
    }
}
```

```php
// ── Custom Observable — PHP native implementation ─────────────────
interface EventListener
{
    public function handle(string $event, array $data): void;
    public function supports(string $event): bool;
}

interface EventEmitterInterface
{
    public function on(string $event, EventListener $listener): void;
    public function off(string $event, EventListener $listener): void;
    public function emit(string $event, array $data = []): void;
}

class EventEmitter implements EventEmitterInterface
{
    /** @var array<string, EventListener[]> */
    private array $listeners = [];

    public function on(string $event, EventListener $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function off(string $event, EventListener $listener): void
    {
        $this->listeners[$event] = array_filter(
            $this->listeners[$event] ?? [],
            fn($l) => $l !== $listener
        );
    }

    public function emit(string $event, array $data = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            if ($listener->supports($event)) {
                $listener->handle($event, $data);
            }
        }
    }
}

// Concrete Listener
class SlackNotifier implements EventListener
{
    public function __construct(private readonly SlackClient $slack) {}

    public function handle(string $event, array $data): void
    {
        $this->slack->post('#alerts', "Event: {$event} — " . json_encode($data));
    }

    public function supports(string $event): bool
    {
        return str_starts_with($event, 'order.');
    }
}

// İstifadə
$emitter = new EventEmitter();
$emitter->on('order.placed', new SlackNotifier(app(SlackClient::class)));
$emitter->on('order.placed', new EmailNotifier(app(Mailer::class)));

$emitter->emit('order.placed', ['order_id' => 42, 'total' => 99.99]);
```

```php
// ── ShouldBroadcast: Real-time WebSocket ──────────────────────────
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderStatusUpdated implements ShouldBroadcast
{
    public function __construct(
        public readonly int    $orderId,
        public readonly string $status,
        public readonly string $message,
    ) {}

    // Hansı WebSocket kanalına broadcast edilsin
    public function broadcastOn(): Channel
    {
        return new Channel("orders.{$this->orderId}");
    }

    // Broadcast-da hansı data göndərilsin (public property-lər avtomatik)
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'status'   => $this->status,
            'message'  => $this->message,
            'time'     => now()->toIso8601String(),
        ];
    }

    // Event adı (default: class name)
    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }
}

// Frontend (Laravel Echo + Pusher):
// Echo.channel('orders.42').listen('order.status.updated', (data) => {
//     updateOrderStatus(data.status, data.message);
// });
```

### Real-World Nümunə

```php
// Laravel-in Eloquent Observer — Model lifecycle events
class OrderObserver
{
    public function creating(Order $order): void
    {
        // UUID assign — create-dən əvvəl
        $order->uuid = Str::uuid();
    }

    public function created(Order $order): void
    {
        // Order yarandıqdan sonra — OCP: OrderObserver yazıldı, Order model-i dəyişmdi
        OrderCreated::dispatch($order->id, $order->total);
    }

    public function updating(Order $order): void
    {
        // Status dəyişirsə audit yaz
        if ($order->isDirty('status')) {
            OrderStatusChanged::dispatch(
                $order->id,
                $order->getOriginal('status'),
                $order->status
            );
        }
    }

    public function deleting(Order $order): void
    {
        // Soft delete-dən əvvəl inventory-ni geri ver
        InventoryService::restore($order->items);
    }
}

// AppServiceProvider-da register:
Order::observe(OrderObserver::class);
```

### Anti-Pattern Nümunəsi

```php
// ❌ Anti-pattern 1: Hər şey üçün event — over-engineering
class ProductController
{
    public function getPrice(int $productId): Response
    {
        // YANLIŞ: Sadə getter üçün event overkill
        event(new ProductPriceRequested($productId)); // Niyə?
        $product = Product::find($productId);
        event(new ProductFound($product));            // Niyə?
        return response()->json(['price' => $product->price]);
    }
}

// ❌ Anti-pattern 2: Sync listener-da uzun iş
class SendNewsletterToAllUsers // ShouldQueue YOX!
{
    public function handle(UserRegistered $event): void
    {
        // 10.000 user-ə email — bu response-u 5 dəqiqə gec qaytarır
        User::all()->each(fn($u) => Mail::to($u)->send(new Newsletter()));
    }
}

// ❌ Anti-pattern 3: Cascade event — infinite loop riski
class UpdateInventory
{
    public function handle(OrderUpdated $event): void
    {
        $order = Order::find($event->orderId);
        $order->status = 'processing';
        $order->save(); // Bu save() yenidən OrderUpdated dispatch edir!
        // Infinite loop: OrderUpdated → UpdateInventory → $order->save() → OrderUpdated → ...
    }
}

// ✅ Düzgün: Guard ilə cascade qarşısını al
class UpdateInventory
{
    private static bool $processing = false;

    public function handle(OrderUpdated $event): void
    {
        if (self::$processing) return; // Already processing — çıx
        self::$processing = true;

        try {
            Order::withoutEvents(fn() => // Bu blokda event-lər deaktiv
                Order::where('id', $event->orderId)->update(['status' => 'processing'])
            );
        } finally {
            self::$processing = false;
        }
    }
}
```

---

## Praktik Tapşırıqlar

1. Laravel-də `UserRegistered` event qurun: 3 async listener (email, CRM, free trial) + 1 sync listener (audit)
2. `ShouldQueue` listener-da job failure-ı handle edin: `failed()` metodu, DLQ, Slack notification
3. Event payload-da Eloquent model göndərməyin niyə problematik olduğunu test edin: queue restart simulyasiyası
4. Domain event vs application event üçün folder structure qurun: `app/Events/Domain/`, `app/Events/Application/`
5. Observer cascade problemi: `OrderUpdated` → listener → `order->save()` → `OrderUpdated` — infinite loop reproduce edib `withoutEvents()` ilə həll edin
6. Custom Observable implement edin: `EventEmitter` class-ı, 3 listener register edin, detach test edin
7. `ShouldBroadcast` ilə real-time order status update implement edin: Laravel Echo frontend-də dinləsin
8. PSR-14 (PHP Event Dispatcher standard) araşdırın: `EventDispatcherInterface`, `ListenerProviderInterface` — Laravel-in implementasiyası ilə müqayisə edin

## Əlaqəli Mövzular
- [SOLID Principles](01-solid-principles.md) — OCP: yeni listener = yeni class
- [Strategy Pattern](05-strategy-pattern.md) — Listener selection strategy
- [Factory Patterns](02-factory-patterns.md) — Event listener factory
- [Singleton Pattern](03-singleton-pattern.md) — EventEmitter singleton kimi istifadə
