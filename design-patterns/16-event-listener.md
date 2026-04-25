# Event-Listener Pattern (Middle ⭐⭐)

## İcmal
Event-Listener (Observer ilə qohum) pattern component-lər arasında loose coupling yaradır: bir şey baş verəndə ("event") digər component-lər xəbərdar olur, lakin birincisi onları tanımır. Event fire edən component yalnız "nə baş verdi"ni bilir, "kim nə edəcək"i bilmir.

## Niyə Vacibdir
Laravel-in event sistemi layihənin böyüməsini asanlaşdırır: "user registered" hadisəsinə yeni davranış əlavə etmək üçün listener yazmaq kifayətdir — mövcud koda toxunmaya bilərsiniz. Async processing (queued listeners), real-time updates (broadcasting) bunun üzərindədir.

## Əsas Anlayışlar
- **Event class**: nə baş verdiyini describe edən DTO-like class (data container); `UserRegistered`, `OrderPlaced`
- **Listener class**: event baş verdikdə çalışan handler (`SendWelcomeEmail`, `AssignFreeSubscription`)
- **EventServiceProvider**: event → listener mapping-i müəyyən edir
- **Queued listener**: `ShouldQueue` implement edərək async, queue worker-da işlənən listener
- **Broadcasting**: `ShouldBroadcast` implement edərək real-time WebSocket-ə push olunan event
- **Model events**: Eloquent model-lərin lifecycle hook-ları (creating, created, updating, deleting)
- **Event subscriber**: bir class-da birdən çox event-in listener-ini qruplaşdırmaq

## Praktik Baxış
- **Real istifadə**: post-registration workflow (welcome email + analytics + free trial), order processing side effects (inventory update + notification + invoice), audit logging, cache invalidation
- **Trade-off-lar**: event-driven kod izləmək çətin — "action at a distance" problemi; bir event-in 5 listener-i varsa debug mürəkkəbləşir; listener failure birini digərini bloklaya bilər
- **İstifadə etməmək**: çox sadə, tək yerdən çağırılan operasiyalarda; event-in listener sayısı birdən çoxdursa amma hamısı sync olarsa — direkt method call sadədir; test etmək çətin olacaqsa
- **Common mistakes**:
  1. Event-ə çox data qoymaq — yalnız minimal `id` ya da entity; listener lazım olanı yükləyir
  2. Listener-lərdə ağır DB query-lər sync etmək — `ShouldQueue` implement et
  3. Listener-dən yeni event fire etmək — event loop riski
  4. EventServiceProvider-da çox listener — subscriber class-a keçmək lazımdır

## Nümunələr

### Ümumi Nümunə
Böyük bir mağazada kassa sistemi düşünün. Satış aparıldıqda: anbar azalır, mühasibatlıq yazır, müştəriyə SMS gedir, sadaqət balı artır. Kassa "kimi nəyin etməsi lazım olduğunu" bilmir — sadəcə "satış oldu" elan edir. Hər şöbə öz işini özü edir.

### PHP/Laravel Nümunəsi

**Event class — data container:**

```php
<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $registrationSource = 'web', // 'web', 'api', 'oauth'
    ) {}
}

class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
```

**Listener class-lar:**

```php
namespace App\Listeners;

// Sync listener — event ilə eyni request-də çalışır
class LogUserRegistration
{
    public function handle(UserRegistered $event): void
    {
        Log::info('New user registered', [
            'user_id' => $event->user->id,
            'email'   => $event->user->email,
            'source'  => $event->registrationSource,
        ]);
    }
}

// Queued listener — async, queue worker-da çalışır
class SendWelcomeEmail implements ShouldQueue
{
    public string $queue = 'emails';     // hansı queue-ya atılsın
    public int    $delay = 30;           // 30 saniyə sonra işlənsin
    public int    $tries = 3;            // uğursuz olsa 3 dəfə cəhd et

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user)->send(new WelcomeMail($event->user));
    }

    // Queue-da fail olsa nə etsin
    public function failed(UserRegistered $event, Throwable $exception): void
    {
        Log::error('Failed to send welcome email', [
            'user_id' => $event->user->id,
            'error'   => $exception->getMessage(),
        ]);
    }
}

class AssignFreeSubscription implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        $event->user->subscriptions()->create([
            'plan'       => 'free',
            'expires_at' => now()->addDays(14), // 14 günlük trial
        ]);
    }
}

class TrackRegistrationInAnalytics implements ShouldQueue
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function handle(UserRegistered $event): void
    {
        $this->analytics->track('user_registered', [
            'user_id' => $event->user->id,
            'source'  => $event->registrationSource,
        ]);
    }
}
```

**EventServiceProvider — wiring:**

```php
namespace App\Providers;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            LogUserRegistration::class,        // sync: dərhal log
            SendWelcomeEmail::class,            // async: queue-da email
            AssignFreeSubscription::class,      // async: subscription
            TrackRegistrationInAnalytics::class, // async: analytics
        ],

        OrderPlaced::class => [
            SendOrderConfirmation::class,
            UpdateInventory::class,
            NotifyWarehouse::class,
            GenerateInvoice::class,
        ],
    ];
}
```

**Event fire etmək:**

```php
class UserService
{
    public function register(RegisterUserData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            return User::create([
                'name'     => $data->name,
                'email'    => $data->email,
                'password' => Hash::make($data->password),
            ]);
        });

        // Event fire — yalnız "nə oldu" bildirir, "kim nə edəcək" bilmir
        UserRegistered::dispatch($user, $data->source);
        // və ya:
        // event(new UserRegistered($user, $data->source));

        return $user;
    }
}
```

**Model events — Eloquent lifecycle hook-ları:**

```php
// Option 1: Model-in boot() metodu
class Order extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        // Hər yeni order yaradılanda
        static::created(function (Order $order): void {
            $order->update(['order_number' => 'ORD-' . str_pad($order->id, 8, '0', STR_PAD_LEFT)]);
        });

        // Silinməzdən əvvəl yoxla
        static::deleting(function (Order $order): void {
            if ($order->status === 'shipped') {
                throw new CannotDeleteShippedOrderException();
            }
        });
    }
}

// Option 2: Observer class (daha təmiz)
class OrderObserver
{
    public function created(Order $order): void
    {
        $order->update(['order_number' => 'ORD-' . str_pad($order->id, 8, '0', STR_PAD_LEFT)]);
    }

    public function deleting(Order $order): void
    {
        if ($order->status === 'shipped') {
            throw new CannotDeleteShippedOrderException();
        }
    }

    public function updated(Order $order): void
    {
        // Status dəyişibsə cache invalidate et
        if ($order->wasChanged('status')) {
            Cache::forget("order:{$order->id}");
        }
    }
}

// ServiceProvider-da qeydiyyat
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Order::observe(OrderObserver::class);
    }
}
```

**Broadcasting — real-time events:**

```php
// Event client-ə push olunur (Pusher/Ably/Soketi)
class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    // Hansı channel-a broadcast edilsin
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orders.{$this->order->user_id}"),
        ];
    }

    // Client-ə hansı data göndərilsin
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status'   => $this->order->status,
            'updated_at' => $this->order->updated_at->toIso8601String(),
        ];
    }

    // Frontend- də event adı
    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }
}

// JavaScript (Laravel Echo)
// Echo.private(`orders.${userId}`)
//     .listen('.order.status.updated', (data) => {
//         updateOrderUI(data.order_id, data.status);
//     });
```

**Event Subscriber — qruplaşdırılmış listener-lər:**

```php
// Bir class-da çox event → çox listener
class OrderEventSubscriber
{
    public function onOrderPlaced(OrderPlaced $event): void
    {
        // ...
    }

    public function onOrderShipped(OrderShipped $event): void
    {
        // ...
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        // ...
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class    => 'onOrderPlaced',
            OrderShipped::class   => 'onOrderShipped',
            OrderCancelled::class => 'onOrderCancelled',
        ];
    }
}

// EventServiceProvider-da
protected $subscribe = [
    OrderEventSubscriber::class,
];
```

**Testing events:**

```php
class UserServiceTest extends TestCase
{
    public function test_register_dispatches_user_registered_event(): void
    {
        Event::fake([UserRegistered::class]);

        $service = app(UserService::class);
        $user    = $service->register(new RegisterUserData(
            name:     'Alice',
            email:    'alice@example.com',
            password: 'secret',
        ));

        Event::assertDispatched(UserRegistered::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_listener_sends_welcome_email(): void
    {
        Mail::fake();

        $listener = new SendWelcomeEmail();
        $user     = User::factory()->create();

        $listener->handle(new UserRegistered($user));

        Mail::assertSent(WelcomeMail::class, fn($mail) => $mail->hasTo($user->email));
    }
}
```

## Praktik Tapşırıqlar
1. `ProductOutOfStock` event yaradın; 3 listener: admin-ə email, Slack notification, restock task — ikisi queued olsun
2. `Order` model-i üçün Observer yazın: created → order number assign et; updated `status` dəyişəndə → audit log yaz; deleting → shipped-i silinməsin
3. `OrderStatusUpdated` broadcastable event yaradın; Laravel Echo ilə frontend-ə push edin (Soketi ilə test edin)

## Əlaqəli Mövzular
- [10-observer.md](10-observer.md) — Observer pattern: Event-Listener-in ata pattern-i
- [11-command.md](11-command.md) — Event handler-lar Command+Handler kimi qurmaq
- [15-service-layer.md](15-service-layer.md) — Service-dən event fire etmək
- [19-chain-of-responsibility.md](19-chain-of-responsibility.md) — Listener pipeline qurmaq
