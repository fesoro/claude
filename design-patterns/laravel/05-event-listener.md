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

### Anti-Pattern Nə Zaman Olur?

**Event spaghetti** — hər şey event ilə:
```php
// BAD — hər əməliyyat üçün event
class UserController
{
    public function update(Request $request, User $user): JsonResponse
    {
        // Bu 3 sətirlik sadə update üçün event fire etmək — overkill
        event(new UserNameChanging($user));
        $user->update($request->validated());
        event(new UserNameChanged($user));
        return response()->json($user);
    }
}
// Event yalnız "domain-də mühüm hadisə" baş verəndə işlənir

// GOOD — yalnız mühüm business event-lər
class UserService
{
    public function register(RegisterUserData $data): User
    {
        $user = $this->users->save(new User($data->toArray()));
        event(new UserRegistered($user)); // Mühümdür — bir çox system xəbərdar olmalıdır
        return $user;
    }
}
```

**Synchronous listeners blocking response:**
```php
// BAD — HTTP response user upload-u gözləyir
class ProcessAvatar
{
    // ShouldQueue yoxdur — sync işlənir, HTTP response bloklanır!
    public function handle(UserRegistered $event): void
    {
        // 3 saniyə sürür, user bu müddət gözləyir
        $this->imageProcessor->resize($event->user->avatar, [200, 200]);
        $this->imageProcessor->resize($event->user->avatar, [50, 50]);
    }
}

// GOOD — queue-da async
class ProcessAvatar implements ShouldQueue
{
    public string $queue = 'media';

    public function handle(UserRegistered $event): void
    {
        $this->imageProcessor->resize($event->user->avatar, [200, 200]);
        $this->imageProcessor->resize($event->user->avatar, [50, 50]);
    }
}
```

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
        // Context məlumatı — listener-lər qərar vermək üçün istifadə edə bilər
        public readonly string $registrationSource = 'web', // 'web', 'api', 'oauth'
    ) {}
}

class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        // Minimal data — listener lazım olan əlavə məlumatı özü yükləyir
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
        // Logging sync olmalıdır — audit trail-i gec yazmaq olmaz
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
    public int    $delay = 30;           // 30 saniyə sonra işlənsin (user UI-da görsün)
    public int    $tries = 3;            // uğursuz olsa 3 dəfə cəhd et

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user)->send(new WelcomeMail($event->user));
    }

    // Queue-da fail olsa nə etsin — silent fail deyil
    public function failed(UserRegistered $event, Throwable $exception): void
    {
        Log::error('Failed to send welcome email', [
            'user_id' => $event->user->id,
            'error'   => $exception->getMessage(),
        ]);
        // Burada notification yaza bilərsiniz: Slack alert, PagerDuty, etc.
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
    // Constructor injection listener-lərdə də işləyir
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
            LogUserRegistration::class,         // sync: dərhal log — audit trail
            SendWelcomeEmail::class,             // async: queue-da email
            AssignFreeSubscription::class,       // async: subscription
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

        // Transaction bitdikdən SONRA event — transaction rollback olsa event getmir
        UserRegistered::dispatch($user, $data->source);

        return $user;
    }
}
```

**Model events — Eloquent lifecycle hook-ları:**

```php
// Option 2: Observer class (daha təmiz — Model-i yüklü saxlamır)
class OrderObserver
{
    public function created(Order $order): void
    {
        // Order number assign etmək — business rule, trigger yox
        $order->update(['order_number' => 'ORD-' . str_pad($order->id, 8, '0', STR_PAD_LEFT)]);
    }

    public function deleting(Order $order): void
    {
        // Silindikdə business rule — shipped order silinə bilməz
        if ($order->status === 'shipped') {
            throw new CannotDeleteShippedOrderException();
        }
    }

    public function updated(Order $order): void
    {
        // wasChanged() — yalnız status dəyişibsə cache invalidate et
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
class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    // Hansı channel-a broadcast edilsin — user-ə specific private channel
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orders.{$this->order->user_id}"),
        ];
    }

    // Client-ə yalnız lazım olan data — bütün order deyil
    public function broadcastWith(): array
    {
        return [
            'order_id'   => $this->order->id,
            'status'     => $this->order->status,
            'updated_at' => $this->order->updated_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated'; // Frontend-də event adı
    }
}
```

**Testing events:**

```php
class UserServiceTest extends TestCase
{
    public function test_register_dispatches_user_registered_event(): void
    {
        // Event::fake() — real listener-ları çalışdırmır, yalnız dispatch-i yoxlayır
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

        // Listener-i birbaşa test etmək — Event::fake() lazım deyil
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

- [../behavioral/01-observer.md](../behavioral/01-observer.md) — Observer pattern: Event-Listener-in ata pattern-i
- [07-policy-handler-pattern.md](07-policy-handler-pattern.md) — Event listener-dan Policy+Handler-ə keçid
- [08-command-query-bus.md](08-command-query-bus.md) — Event vs Command fərqi; bus ilə integration
- [02-service-layer.md](02-service-layer.md) — Service-dən event fire etmək
- [../behavioral/03-command.md](../behavioral/03-command.md) — Event handler-lar Command+Handler kimi qurmaq
- [../ddd/05-ddd-domain-events.md](../ddd/05-ddd-domain-events.md) — Domain event-lər; application event-lərdən fərqi
- [../integration/02-event-sourcing.md](../integration/02-event-sourcing.md) — Event sourcing: event-ləri əsas state kimi saxlamaq
- [../integration/04-outbox-pattern.md](../integration/04-outbox-pattern.md) — Transactional event publishing; at-least-once delivery
