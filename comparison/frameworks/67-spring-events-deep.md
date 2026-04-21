# Spring Events vs Laravel Events — Dərin Müqayisə

## Giriş

**Event-driven architecture** — kod bir-birindən ayrıca kompleks modullara bölünsün deyə istifadə olunan pattern. Bir hadisə baş verir (OrderPlaced), fərqli "dinləyicilər" (listener) bu hadisəyə reaksiya verir: email göndər, inventory azalt, analytics göndər. Publisher listener-ləri tanımır — yalnız event yayır. Bu coupling-i azaldır.

**Spring** built-in `ApplicationEventPublisher` təklif edir. `@EventListener` annotation ilə hər bean-da listener yarat, `@Async` ilə asenxron işlət, `@TransactionalEventListener` ilə transaction phase-inə bağla (BEFORE_COMMIT, AFTER_COMMIT, AFTER_ROLLBACK). **Laravel** `event()` helper və `Event::listen()`, `ShouldQueue` interface ilə listener-i queue-ya göndərmək, `dispatchAfterCommit()` ilə transaction bitəndən sonra göndərmək, Model Events (creating, created, updating, updated) və Observer-lər təklif edir.

Bu sənəddə Spring tərəfində `ApplicationEventPublisher`, `@EventListener` (sync, async, conditional SpEL, transactional phase), built-in event-lər (`ContextRefreshedEvent`, `ApplicationReadyEvent`, `AvailabilityChangeEvent`), Spring Modulith `@ApplicationModuleListener`-ni; Laravel tərəfində Events + Listeners, `ShouldQueue`, `afterCommit()`, Model Events + Observer-lər, Broadcasting-i araşdırırıq. Hər ikisində `OrderPlaced` → email + inventory + analytics nümunəsi göstəririk.

---

## Spring-də istifadəsi

### 1) ApplicationEventPublisher — event yayımı

```java
// Event class — Boot 3-də record kimi yazmaq olar
public record OrderPlacedEvent(
    Long orderId,
    Long userId,
    BigDecimal total,
    Instant placedAt
) { }

// Publisher
@Service
public class OrderService {

    private final OrderRepository orders;
    private final ApplicationEventPublisher publisher;

    public OrderService(OrderRepository orders, ApplicationEventPublisher pub) {
        this.orders = orders;
        this.publisher = pub;
    }

    @Transactional
    public Order placeOrder(CreateOrderCommand cmd) {
        Order order = new Order(cmd.userId(), cmd.items());
        orders.save(order);

        // Event yay — listener-lər avtomatik çağırılır
        publisher.publishEvent(new OrderPlacedEvent(
            order.getId(),
            order.getUserId(),
            order.getTotal(),
            Instant.now()
        ));

        return order;
    }
}
```

### 2) @EventListener — sync listener

```java
@Component
public class OrderEmailListener {

    private final MailService mail;
    private final UserRepository users;

    public OrderEmailListener(MailService mail, UserRepository users) {
        this.mail = mail;
        this.users = users;
    }

    @EventListener
    public void onOrderPlaced(OrderPlacedEvent event) {
        User user = users.findById(event.userId()).orElseThrow();
        mail.sendOrderConfirmation(user.getEmail(), event.orderId());
    }
}

@Component
public class InventoryListener {

    @EventListener
    public void reserveInventory(OrderPlacedEvent event) {
        // Inventory-dən çıxar
    }
}

@Component
public class AnalyticsListener {

    @EventListener
    public void trackOrder(OrderPlacedEvent event) {
        // Analytics API-yə göndər
    }
}
```

**Default:** `publishEvent()` synchronously çağırır — publisher metodu listener-lər bitənə qədər gözləyir, hamısı **eyni thread-də**, **eyni transaction-da** işləyir.

### 3) @Async @EventListener — asenxron

```java
@Configuration
@EnableAsync
public class AsyncConfig { }

@Component
public class AnalyticsListener {

    @Async
    @EventListener
    public void trackOrder(OrderPlacedEvent event) {
        // Ayrıca thread-də işləyir — publisher gözləmir
        analyticsApi.track("order_placed", event);
    }
}
```

**Diqqət:** `@Async @EventListener` yeni thread-də işləyir — `@Transactional` context itir, DB əməliyyatları başqa transaction olur.

### 4) @TransactionalEventListener — transaction phase

`@Transactional` metod daxilindən event yayırıqsa, listener həmin transaction bitəndən sonra işləsin istəyə bilərik. Məsələn, email yalnız DB commit olunduqdan sonra getməlidir — rollback olarsa email gedib qalmasın.

```java
@Component
public class OrderEmailListener {

    // Fazalar: BEFORE_COMMIT, AFTER_COMMIT (default), AFTER_ROLLBACK, AFTER_COMPLETION
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
    public void sendConfirmation(OrderPlacedEvent event) {
        // Yalnız commit uğurludursa
        mail.sendOrderConfirmation(event.userId(), event.orderId());
    }

    @TransactionalEventListener(phase = TransactionPhase.AFTER_ROLLBACK)
    public void logRollback(OrderPlacedEvent event) {
        log.warn("Order rolled back: {}", event.orderId());
    }

    @TransactionalEventListener(phase = TransactionPhase.BEFORE_COMMIT)
    public void validateBeforeCommit(OrderPlacedEvent event) {
        // Transaction commit olmadan əvvəl — hələ rollback mümkündür
    }
}
```

**Faza siyahısı:**

| Phase | Haçan | İstifadə |
|---|---|---|
| `BEFORE_COMMIT` | Commit-dən əvvəl | Son validation, audit |
| `AFTER_COMMIT` | Commit uğurlu | Email, notification (default) |
| `AFTER_ROLLBACK` | Rollback baş verdi | Compensation, log |
| `AFTER_COMPLETION` | Hər halda (commit və ya rollback) | Metric, cleanup |

### 5) Conditional listener — SpEL

```java
@Component
public class VipCustomerListener {

    @EventListener(condition = "#event.total.compareTo(T(java.math.BigDecimal).valueOf(1000)) > 0")
    public void onLargeOrder(OrderPlacedEvent event) {
        // Yalnız 1000-dən böyük sifarişlər
        vipService.notifyAccountManager(event);
    }

    @EventListener(condition = "#event.userId == 42")
    public void onSpecificUser(OrderPlacedEvent event) {
        // Yalnız user 42 üçün
    }
}
```

### 6) Generic event — `ApplicationEvent<T>` yox, sadə record

Spring 4.2-dən bəri event custom class-ı extend etməyə ehtiyac yoxdur — istənilən obyekt event ola bilər.

```java
// Legacy — Spring <4.2
public class OrderPlacedEventOld extends ApplicationEvent {
    private final Order order;
    public OrderPlacedEventOld(Object source, Order order) {
        super(source);
        this.order = order;
    }
}

// Modern — Spring 4.2+
public record OrderPlacedEvent(Long orderId, BigDecimal total) { }

publisher.publishEvent(new OrderPlacedEvent(1L, BigDecimal.TEN));
```

### 7) Generic payload with PayloadApplicationEvent

```java
// Typed listener — yalnız String payload
@EventListener
public void onStringEvent(PayloadApplicationEvent<String> event) {
    String msg = event.getPayload();
}

// Publish
publisher.publishEvent("Hello");    // PayloadApplicationEvent<String>-ə sarılır
```

### 8) Event hierarchy

```java
public abstract sealed class AuditEvent
    permits UserCreatedEvent, UserDeletedEvent {
    public abstract Long userId();
}

public record UserCreatedEvent(Long userId, String email) extends AuditEvent { }
public record UserDeletedEvent(Long userId) extends AuditEvent { }

@Component
public class AuditListener {

    // Hər AuditEvent-i tut
    @EventListener
    public void onAnyAudit(AuditEvent event) {
        auditLog.write(event);
    }

    // Yalnız UserCreatedEvent
    @EventListener
    public void onUserCreated(UserCreatedEvent event) {
        mail.sendWelcome(event.userId());
    }
}
```

### 9) Built-in Spring events

Spring Framework öz həyat dövrü hadisələrini yayır:

```java
@Component
public class LifecycleLogger {

    @EventListener
    public void onContextRefreshed(ContextRefreshedEvent event) {
        // Context bootstrap olub, bütün bean-lar hazırdır
    }

    @EventListener
    public void onApplicationReady(ApplicationReadyEvent event) {
        // HTTP server aktiv, tətbiq sorğu qəbul etməyə hazırdır
        log.info("App ready on port {}", event.getWebServer().getPort());
    }

    @EventListener
    public void onContextClosed(ContextClosedEvent event) {
        // Shutdown başlayır
    }

    @EventListener
    public void onAvailabilityChange(AvailabilityChangeEvent<?> event) {
        // LivenessState / ReadinessState dəyişdi
        log.info("Availability: {}", event.getState());
    }
}
```

**Sıra:**
```
1. ApplicationStartingEvent
2. ApplicationEnvironmentPreparedEvent
3. ApplicationContextInitializedEvent
4. ApplicationPreparedEvent
5. ContextRefreshedEvent
6. ApplicationStartedEvent
7. AvailabilityChangeEvent (LIVE)
8. ApplicationReadyEvent           ← sorğu qəbul etməyə hazır
9. AvailabilityChangeEvent (READY)
...
10. ContextClosedEvent             ← shutdown
```

### 10) Event publishing from any bean

Hər bean `ApplicationEventPublisherAware` implement edə bilər və ya publisher-i inject edə bilər:

```java
@Component
public class UserService {

    private final ApplicationEventPublisher publisher;

    public UserService(ApplicationEventPublisher pub) {
        this.publisher = pub;
    }

    public void deleteUser(Long id) {
        userRepo.deleteById(id);
        publisher.publishEvent(new UserDeletedEvent(id));
    }
}
```

### 11) Spring Modulith — @ApplicationModuleListener

Spring Modulith modular monolit-lər üçün ayrıca abstraction təqdim edir. `@ApplicationModuleListener` = `@Async + @TransactionalEventListener(AFTER_COMMIT)` + event publication registry.

```java
@ApplicationModuleListener
public void onOrderPlaced(OrderPlacedEvent event) {
    // 1. Ayrıca thread-də (async)
    // 2. Yalnız commit uğurludursa (after_commit)
    // 3. Event publication registry-də qeyd olunur — bir də işlənsin deyə
    //    retry mümkündür (outbox pattern)
    mail.sendOrderConfirmation(event);
}
```

Bu, **outbox pattern**-ə bənzəyir — listener işləyə bilməzsə event DB-də qalır, sonra yenidən cəhd olunur.

### 12) Event sourcing vs event publishing

**Event publishing** — bu sənəddəki mövzu. In-memory, coupling azaltmaq üçün.

**Event sourcing** — state-i hadisələr siyahısı kimi saxlamaq. `Order` obyektinin cari vəziyyəti `OrderCreated`, `ItemAdded`, `OrderShipped` hadisələrinin yığılmasından gəlir. Axios Framework, EventStoreDB kimi ayrıca framework-lər bununla məşğuldur.

Spring event publishing sadə in-memory bus-dur — tətbiq restart olsa event-lər itir (Modulith-də olduğu kimi outbox olmadıqca).

### 13) Tam nümunə — OrderPlaced workflow

```java
// Event
public record OrderPlacedEvent(Long orderId, Long userId, BigDecimal total) { }

// Publisher
@Service
public class OrderService {
    private final OrderRepository orders;
    private final ApplicationEventPublisher publisher;

    public OrderService(OrderRepository o, ApplicationEventPublisher p) {
        this.orders = o;
        this.publisher = p;
    }

    @Transactional
    public Order placeOrder(CreateOrderCommand cmd) {
        Order order = new Order(cmd.userId(), cmd.items());
        orders.save(order);
        publisher.publishEvent(new OrderPlacedEvent(order.getId(), order.getUserId(), order.getTotal()));
        return order;
    }
}

// Listener 1: email (after commit, async)
@Component
public class EmailListener {
    private final MailService mail;

    public EmailListener(MailService m) { this.mail = m; }

    @Async
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
    public void sendConfirmation(OrderPlacedEvent event) {
        mail.send("Order " + event.orderId() + " confirmed", event.userId());
    }
}

// Listener 2: inventory (sync, same transaction!)
@Component
public class InventoryListener {
    private final InventoryService inventory;

    public InventoryListener(InventoryService i) { this.inventory = i; }

    @EventListener
    public void reserve(OrderPlacedEvent event) {
        inventory.reserve(event.orderId());    // eyni tx — uğursuz olsa rollback
    }
}

// Listener 3: analytics (after commit, async, VIP üçün xüsusi)
@Component
public class AnalyticsListener {
    private final AnalyticsApi api;

    public AnalyticsListener(AnalyticsApi a) { this.api = a; }

    @Async
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
    public void track(OrderPlacedEvent event) {
        api.track("order_placed", Map.of(
            "order_id", event.orderId(),
            "user_id", event.userId(),
            "total", event.total()
        ));
    }

    @Async
    @TransactionalEventListener(
        phase = TransactionPhase.AFTER_COMMIT,
        condition = "#event.total.compareTo(T(java.math.BigDecimal).valueOf(1000)) > 0"
    )
    public void trackVip(OrderPlacedEvent event) {
        api.track("vip_order", Map.of("order_id", event.orderId()));
    }
}
```

---

## Laravel-də istifadəsi

### 1) Event + Listener yaratmaq

```bash
php artisan make:event OrderPlaced
php artisan make:listener SendOrderConfirmation --event=OrderPlaced
php artisan make:listener ReserveInventory --event=OrderPlaced
php artisan make:listener TrackOrderAnalytics --event=OrderPlaced
```

Event class:

```php
namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
```

Listener class:

```php
namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->user->email)
            ->send(new OrderConfirmation($event->order));
    }
}
```

### 2) Event-Listener bind

Laravel 11-də event discovery avtomatikdir — `App\Listeners\*` scan olunur və `handle(EventClass $event)` signature-ə görə qoşulur.

Manual bind (legacy və ya xüsusi hallar üçün):

```php
// app/Providers/EventServiceProvider.php (Laravel 10)
protected $listen = [
    OrderPlaced::class => [
        SendOrderConfirmation::class,
        ReserveInventory::class,
        TrackOrderAnalytics::class,
    ],
];

// Laravel 11+ AppServiceProvider::boot
Event::listen(OrderPlaced::class, SendOrderConfirmation::class);
Event::listen(OrderPlaced::class, ReserveInventory::class);

// Və ya closure
Event::listen(OrderPlaced::class, function (OrderPlaced $event) {
    Log::info('Order placed: ' . $event->order->id);
});
```

### 3) Event yaymaq (dispatch)

```php
// event() helper
event(new OrderPlaced($order));

// Dispatchable trait
OrderPlaced::dispatch($order);

// Facade
Event::dispatch(new OrderPlaced($order));
```

### 4) ShouldQueue — async listener

Spring-də `@Async @EventListener` var. Laravel-də `ShouldQueue` interface:

```php
namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TrackOrderAnalytics implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;
    public string $queue = 'analytics';

    public function handle(OrderPlaced $event): void
    {
        // Queue worker-də ayrıca prosesdə işləyir
        AnalyticsApi::track('order_placed', [
            'order_id' => $event->order->id,
            'total' => $event->order->total,
        ]);
    }

    public function failed(OrderPlaced $event, \Throwable $e): void
    {
        Log::error('Analytics failed', ['order' => $event->order->id, 'e' => $e]);
    }
}
```

Queue worker lazımdır: `php artisan queue:work`.

### 5) afterCommit() — transaction sonrası

Spring-də `@TransactionalEventListener(phase = AFTER_COMMIT)` var. Laravel-də iki yol:

**Yol 1:** Listener class-da `$afterCommit` property:

```php
class SendOrderConfirmation implements ShouldQueue
{
    public bool $afterCommit = true;       // Commit olmayanda işləməsin

    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->user->email)->send(new OrderConfirmation($event->order));
    }
}
```

**Yol 2:** Event yayanda:

```php
DB::transaction(function () use ($orderData) {
    $order = Order::create($orderData);

    // Transaction bitmədən event yayılmasın
    OrderPlaced::dispatch($order)->afterCommit();
});
```

**Yol 3:** Job üçün `dispatchAfterCommit()`:

```php
SendOrderEmail::dispatch($order)->afterCommit();
```

### 6) Queueable listener detalları

```php
class ReserveInventory implements ShouldQueue
{
    use InteractsWithQueue;

    public function viaConnection(): string { return 'redis'; }
    public function viaQueue(): string { return 'inventory'; }

    public function backoff(): array { return [10, 30, 60]; }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    public function shouldQueue(OrderPlaced $event): bool
    {
        // Conditional queueing
        return $event->order->total > 100;
    }

    public function handle(OrderPlaced $event): void { /* ... */ }
}
```

### 7) Conditional listener (Spring SpEL-ə bənzər)

Laravel-də birbaşa annotation yoxdur. İki yol:

**1. `shouldQueue()` metod:**

```php
public function shouldQueue(OrderPlaced $event): bool
{
    return $event->order->total > 1000;
}
```

**2. `handle()` daxilində early return:**

```php
public function handle(OrderPlaced $event): void
{
    if ($event->order->total <= 1000) {
        return;
    }
    vipService()->notify($event->order);
}
```

### 8) Model Events — Eloquent avtomatik event-lər

Laravel Model-lər həyat dövrlərinin hər mərhələsində event yayır:

```
retrieved  ← DB-dən çıxarıldı
creating   ← insert-dən əvvəl
created    ← insert-dən sonra
updating   ← update-dən əvvəl
updated    ← update-dən sonra
saving     ← creating + updating
saved      ← created + updated
deleting   ← delete-dən əvvəl
deleted    ← delete-dən sonra
trashed    ← soft delete
restoring, restored
replicating
```

Manual listen:

```php
// AppServiceProvider::boot
Order::created(function (Order $order) {
    OrderPlaced::dispatch($order);
});

Order::deleting(function (Order $order) {
    if ($order->is_shipped) {
        throw new \LogicException('Shipped order deletion forbidden');
    }
});
```

### 9) Observer — Model event-lər üçün class

```bash
php artisan make:observer OrderObserver --model=Order
```

```php
namespace App\Observers;

use App\Models\Order;
use App\Events\OrderPlaced;

class OrderObserver
{
    public function created(Order $order): void
    {
        OrderPlaced::dispatch($order);
    }

    public function deleting(Order $order): bool
    {
        if ($order->is_shipped) {
            return false;       // delete-i ləğv et
        }
        return true;
    }
}

// Model-də qeyd et (Laravel 11+ attribute üsulu)
#[ObservedBy([OrderObserver::class])]
class Order extends Model { }

// Və ya AppServiceProvider::boot
Order::observe(OrderObserver::class);
```

Spring-də Eloquent Model Events-a birbaşa ekvivalent yoxdur — JPA-da `@PrePersist`, `@PostPersist`, `@PreUpdate`, `@PostUpdate`, `@PreRemove`, `@PostRemove` annotation-ları var, amma onlar entity class daxilində işləyir.

### 10) Broadcasting — frontend-ə real-time event

Broadcasting ayrıca bir konseptdir — event-i WebSocket-lə frontend-ə göndərmək. Laravel `ShouldBroadcast` interface istifadə edir:

```php
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders.' . $this->order->user_id)];
    }

    public function broadcastAs(): string { return 'order.updated'; }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->order->id, 'status' => $this->order->status];
    }
}
```

Driver: Pusher, Reverb (self-host), Ably. Spring-də buna uyğun `@SendTo` (STOMP/WebSocket) var, amma konfiqurasiya daha açıqdır.

### 11) Event::fake() — test

```php
public function test_placing_order_dispatches_event(): void
{
    Event::fake();

    $this->post('/api/orders', ['items' => [...]]);

    Event::assertDispatched(OrderPlaced::class);
    Event::assertDispatched(function (OrderPlaced $event) {
        return $event->order->total > 0;
    });

    Event::assertNotDispatched(OrderCancelled::class);
}
```

Spring-də `@RecordApplicationEvents` + `ApplicationEvents` inject ilə oxşar:

```java
@SpringBootTest
@RecordApplicationEvents
class OrderServiceTest {
    @Autowired ApplicationEvents events;

    @Test
    void placeOrderPublishesEvent() {
        orderService.placeOrder(cmd);

        assertThat(events.stream(OrderPlacedEvent.class)).hasSize(1);
    }
}
```

### 12) Tam nümunə — OrderPlaced workflow

```php
// app/Events/OrderPlaced.php
class OrderPlaced
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly Order $order) {}
}

// app/Listeners/SendOrderConfirmation.php (queue, after commit)
class SendOrderConfirmation implements ShouldQueue
{
    public bool $afterCommit = true;
    public string $queue = 'emails';

    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->user->email)->send(new OrderConfirmation($event->order));
    }
}

// app/Listeners/ReserveInventory.php (sync — eyni request)
class ReserveInventory
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(OrderPlaced $event): void
    {
        $this->inventory->reserve($event->order);
    }
}

// app/Listeners/TrackOrderAnalytics.php (queue, VIP shərti)
class TrackOrderAnalytics implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(OrderPlaced $event): void
    {
        AnalyticsApi::track('order_placed', [
            'order_id' => $event->order->id,
            'total' => $event->order->total,
        ]);

        if ($event->order->total > 1000) {
            AnalyticsApi::track('vip_order', ['order_id' => $event->order->id]);
        }
    }
}

// app/Services/OrderService.php
class OrderService
{
    public function placeOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create($data);
            OrderPlaced::dispatch($order);       // afterCommit listener-lərdə işarə
            return $order;
        });
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Publisher | `ApplicationEventPublisher.publishEvent()` | `event()`, `Event::dispatch()` |
| Listener | `@EventListener` metod | `handle()` metodlu class |
| Discovery | `@Component` + `@EventListener` avtomatik | Laravel 11 avtomatik scan |
| Event class | Plain POJO / record (4.2+) | Class + `Dispatchable` trait |
| Sync default | Bəli | Bəli |
| Async | `@Async @EventListener` | `implements ShouldQueue` |
| Queue-based async | Yoxdur (thread pool) | Var — Redis/SQS/DB queue |
| Transaction phase | `@TransactionalEventListener(phase = ...)` | `$afterCommit = true` |
| 4 faza | BEFORE_COMMIT, AFTER_COMMIT, AFTER_ROLLBACK, AFTER_COMPLETION | Yalnız `afterCommit` |
| Conditional | SpEL `condition = "..."` | `shouldQueue()` metod |
| Generic event | `PayloadApplicationEvent<T>` | Yox |
| Event hierarchy | Bəli — super class listener super-tip tutur | Yox |
| Built-in lifecycle | `ContextRefreshedEvent`, `ApplicationReadyEvent`, ... | `booted()` callback |
| Model events | JPA `@PrePersist` entity-də | Eloquent model event-lər çoxsaylı |
| Observer | Yox | `Model::observe(Observer::class)` |
| Broadcasting | `@SendTo` STOMP | `ShouldBroadcast` + Pusher/Reverb |
| Retry | Manual | Queue worker `$tries`, `$backoff` |
| Test | `@RecordApplicationEvents` | `Event::fake()` |
| Modulith / outbox | Spring Modulith `@ApplicationModuleListener` | Manual (`spatie/laravel-transactional-listeners`) |

---

## Niyə belə fərqlər var?

**JVM vs PHP runtime.** Spring event-lər in-memory thread-lərdə işləyir — `@Async` thread pool, `publishEvent()` blocking. Laravel-də PHP process-per-request modeldir — `ShouldQueue` əslində job kimi Redis-ə yazılır, ayrıca worker prosesdə işləyir. Spring-də queue-a ehtiyac varsa Kafka/RabbitMQ ayrıca bağlanır; Laravel-də queue default-dur.

**Transaction phase-lər.** Spring-də 4 faza (BEFORE_COMMIT, AFTER_COMMIT, AFTER_ROLLBACK, AFTER_COMPLETION) — enterprise Java tətbiqlərində transaction idarəetməsi önəmlidir. Laravel-də yalnız `afterCommit` var — çünki PHP-də uzun-uzadı nested transaction nadir haldır.

**Conditional filtering.** Spring SpEL ilə güclü filter təklif edir (`condition = "#event.total > 1000"`). Laravel-də bu `if` statement-də yazılır — daha az deklarativ, amma PHP təbiətinə yaxın.

**Generic vs plain.** Spring 4.2-dən əvvəl `ApplicationEvent` extend etmək məcburi idi. İndi istənilən obyekt event ola bilər. Laravel həmişə event class yaradır — trait-lərlə zənginləşir (`Dispatchable`, `SerializesModels`).

**Model Events (Eloquent) vs JPA hooks.** Eloquent 11+ hadisə yayır, Laravel-də Observer class ayrıca yazılır — model class-ı sadə saxlanır. Spring/JPA tərəfində `@PrePersist`, `@PostPersist` entity daxilində yazılır, bəzən Entity Listener class ayrıca.

**Broadcasting fərqi.** Laravel-in "broadcast" konsepti frontend real-time-a yönəlib (Pusher/Reverb). Spring-də WebSocket/STOMP ayrıca stack-dir, event-dən fərqlidir.

**Spring Modulith / outbox.** Spring Modulith event publication registry ilə "outbox pattern"-i built-in edir — event DB-də yazılır, commit olunur, sonra işlənir. Laravel-də analoji paket `spatie/laravel-event-sourcing` və ya custom outbox kod.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- 4 transaction phase (BEFORE_COMMIT, AFTER_COMMIT, AFTER_ROLLBACK, AFTER_COMPLETION)
- SpEL ilə `@EventListener(condition = "...")` filtering
- Event hierarchy — super-class listener subclass event-ləri tutur
- `PayloadApplicationEvent<T>` generic typed event
- Built-in lifecycle event-lər (`ContextRefreshedEvent`, `ApplicationReadyEvent`, `AvailabilityChangeEvent`)
- Spring Modulith `@ApplicationModuleListener` (async + tx + outbox)
- `@RecordApplicationEvents` test utility
- `ApplicationListener<SpecificEvent>` interface alternativ
- Thread-based async (`@Async` pool)
- Plain POJO event (4.2+) vs `ApplicationEvent` extend

**Yalnız Laravel-də:**
- Queue-based async (Redis/SQS/DB) — `ShouldQueue`
- Model Events: creating, created, updating, updated, saving, saved, deleting, deleted, retrieved, trashed, restoring, restored
- Observer class ilə model event-ləri bir yerə yığmaq
- `#[ObservedBy]` attribute (Laravel 11)
- `Event::fake()` — test-də bütün listener-ləri söndür
- `ShouldBroadcast` — frontend-ə real-time
- `SerializesModels` trait — queue-da model-i id ilə saxla
- `$afterCommit` property + `dispatchAfterCommit()`
- Wildcard listener: `Event::listen('user.*', fn ($event, $payload) => ...)`
- Event subscriber — bir class-da çoxlu event listener bind

---

## Best Practices

1. **Event-i immutable et.** Spring-də record istifadə et. Laravel-də `readonly` property.
2. **Event kiçik və spesifik olsun.** `OrderPlaced`, `OrderCancelled`, `OrderShipped` — bir "god event" yaratma.
3. **Spring-də `@TransactionalEventListener(AFTER_COMMIT)` + `@Async`** kombinasiyası default ən yaxşı seçimdir (email, notification üçün).
4. **Laravel-də `$afterCommit = true`** qoy ki, rollback olarsa email göndərilməsin.
5. **Listener idempotent olsun.** Queue worker retry edə bilər — eyni email iki dəfə getməsin deyə idempotency key istifadə et.
6. **Spring-də sync listener eyni transaction-da işləyir.** DB dəyişikliyi listener-də atılmalıdırsa, sync istifadə et. Əks halda AFTER_COMMIT + async.
7. **Laravel-də `ShouldQueue` istifadə et** — email, API call, slow iş queue-ya.
8. **Event name-də keçmiş zaman: `OrderPlaced`, `UserCreated`** — hadisə artıq baş verib deməkdir.
9. **Spring Modulith** modular monolit-lərdə `@ApplicationModuleListener` istifadə et — outbox built-in.
10. **Event test et.** Spring: `@RecordApplicationEvents`. Laravel: `Event::fake()` + `assertDispatched`.
11. **Broadcasting-i Laravel-də ehtiyatlı et.** Hər event public gedirsə — sensitive data leak ola bilər.
12. **Spring built-in event-ləri (`ApplicationReadyEvent`) warm-up üçün işlət** — cache, connection pool hazırla.

---

## Yekun

Spring `ApplicationEventPublisher` + `@EventListener` sistem ciddi, dəqiq, transaction-aware: 4 faza, SpEL filtering, event hierarchy, built-in lifecycle event-ləri, Modulith ilə outbox. Laravel Events + Listeners + Observer daha sadə və queue-first: `ShouldQueue`, `afterCommit`, Eloquent model event-ləri, Broadcasting frontend-ə.

JVM-də thread-based async sürətlidir, PHP-də queue-based async davamlıdır. Spring-də `@TransactionalEventListener(AFTER_COMMIT) + @Async` ən güclü nümunədir; Laravel-də `ShouldQueue + $afterCommit = true`. Hər ikisi eyni pattern-i yerinə yetirir, amma fərqli runtime modellərindən faydalanır. Əsas qayda: listener idempotent, event immutable, transaction-safe dispatching.
