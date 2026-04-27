# PSR-14 Event Dispatcher (Middle)

## Mündəricat
1. [PSR-14 nədir?](#psr-14-nədir)
2. [Interface-lər](#interface-lər)
3. [Implementations](#implementations)
4. [Symfony EventDispatcher](#symfony-eventdispatcher)
5. [Laravel Event vs PSR-14](#laravel-event-vs-psr-14)
6. [Event vs Listener vs Subscriber](#event-vs-listener-vs-subscriber)
7. [Stoppable events](#stoppable-events)
8. [Async events (queue inteqrasiya)](#async-events-queue-inteqrasiya)
9. [Domain events (DDD)](#domain-events-ddd)
10. [Best practices](#best-practices)
11. [İntervyu Sualları](#intervyu-sualları)

---

## PSR-14 nədir?

```
PSR-14 — PHP-FIG event dispatcher standartı (2019).
"Event-driven kod framework-independent yazsın"

Niyə lazımdır?
  Symfony events vs Laravel events vs Doctrine events — fərqli interface
  PSR-14 ortaq abstraksiya verir.

Əsas konseptlər:
  - Event:    nə baş verdi (POPO obyekt)
  - Listener: nə etmək lazımdır (callable)
  - Dispatcher: event-i listener-lərə paylayan
  - ListenerProvider: hansı listener event-ə uyğundur
```

---

## Interface-lər

```php
<?php
namespace Psr\EventDispatcher;

// Dispatcher
interface EventDispatcherInterface
{
    public function dispatch(object $event): object;
    // Event yenidən qaytarılır (modify edilə bilər listener-lər tərəfindən)
}

// Listener provider
interface ListenerProviderInterface
{
    public function getListenersForEvent(object $event): iterable;
    // Event tipinə görə listener-lər siyahısı
}

// Optional — stoppable
interface StoppableEventInterface
{
    public function isPropagationStopped(): bool;
    // true → dispatcher daha listener çağırmır
}
```

---

## Implementations

```bash
# Symfony EventDispatcher (PSR-14 uyumlu)
composer require symfony/event-dispatcher

# Crell/Tukio — PSR-14 reference impl
composer require crell/tukio

# Yii Events
composer require yiisoft/event-dispatcher

# League Event (3.0+ PSR-14)
composer require league/event
```

---

## Symfony EventDispatcher

```php
<?php
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

// Event class
class UserRegistered extends Event
{
    public const NAME = 'user.registered';
    
    public function __construct(public readonly User $user) {}
}

// Listener
$dispatcher = new EventDispatcher();

$dispatcher->addListener(UserRegistered::class, function (UserRegistered $event) {
    Mail::to($event->user)->send(new WelcomeEmail());
});

$dispatcher->addListener(UserRegistered::class, function (UserRegistered $event) {
    Analytics::track('signup', ['user_id' => $event->user->id]);
}, priority: 10);   // higher priority first

// Dispatch
$user = new User('Ali', 'a@b.com');
$dispatcher->dispatch(new UserRegistered($user));
```

```php
<?php
// Event Subscriber — bir class çox event-ə listen edir
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailService $mail,
        private LoggerInterface $log,
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistered::class    => ['onUserRegistered', 10],
            UserDeleted::class       => 'onUserDeleted',
            UserUpdated::class       => [
                ['onUserUpdatedFirst', 100],
                ['onUserUpdatedSecond', 50],
            ],
        ];
    }
    
    public function onUserRegistered(UserRegistered $event): void
    {
        $this->mail->sendWelcome($event->user);
    }
    
    public function onUserDeleted(UserDeleted $event): void
    {
        $this->log->info('User deleted', ['id' => $event->user->id]);
    }
}

$dispatcher->addSubscriber(new UserSubscriber($mail, $log));
```

---

## Laravel Event vs PSR-14

```php
<?php
// LARAVEL native — string event name + listener resolve
namespace App\Events;

class UserRegistered
{
    public function __construct(public User $user) {}
}

namespace App\Listeners;

class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        // ...
    }
}

// EventServiceProvider
protected $listen = [
    UserRegistered::class => [
        SendWelcomeEmail::class,
        TrackAnalytics::class,
    ],
];

// Dispatch
event(new UserRegistered($user));

// Inline listener (closure)
Event::listen(UserRegistered::class, function ($event) {
    // ...
});
```

```
Laravel events — PSR-14 uyğun deyil:
  - String-based name + class name
  - Listener "handle()" method (PSR-14 callable)
  - Event class container resolution
  - Wildcards (`Event::listen('user.*', fn() => ...)`)
  - Queueable listeners (ShouldQueue)
  - Tags, observers (Eloquent)

Laravel-də PSR-14 istifadə:
  Manual binding ilə Symfony EventDispatcher-i çək, ya da:
  Laravel Eloquent observer + custom dispatcher pattern
```

---

## Event vs Listener vs Subscriber

```
EVENT
  - Immutable data (POPO)
  - "Bu baş verdi" — past tense
  - Hər event ən az 1 listener gözləyir (yoxsa ehtiyac yoxdur)

LISTENER
  - Tək callable
  - Bir event-ə bir listener
  - Sadə, fokuslanmış

SUBSCRIBER
  - Class-based, çox listener bir yerdə
  - Eyni domain hadisələri qruplaşdırır
  - DI ilə dependency-lərə çatış asan
  - getSubscribedEvents() — declarative

Hansı nə vaxt?
  Sadə hook (1 reaction)         → listener
  Domain logic (5+ reaction)      → subscriber
  Cross-cutting (logging, metrics) → subscriber
```

---

## Stoppable events

```php
<?php
use Psr\EventDispatcher\StoppableEventInterface;

class ValidationEvent implements StoppableEventInterface
{
    private bool $stopped = false;
    public array $errors = [];
    
    public function __construct(public readonly array $data) {}
    
    public function addError(string $err): void
    {
        $this->errors[] = $err;
        $this->stopped = true;   // ilk error-da dayan
    }
    
    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}

$dispatcher->addListener(ValidationEvent::class, function ($event) {
    if (empty($event->data['email'])) {
        $event->addError('Email required');
    }
});

$dispatcher->addListener(ValidationEvent::class, function ($event) {
    // Bu listener çağırılmayacaq əgər əvvəlki addError ediblərsə
    if (empty($event->data['name'])) {
        $event->addError('Name required');
    }
});

$event = new ValidationEvent(['email' => '']);
$dispatcher->dispatch($event);
// $event->errors = ['Email required']
```

---

## Async events (queue inteqrasiya)

```php
<?php
// PSR-14 native async deyil — queue listener manual
class AsyncListener
{
    public function __construct(private Queue $queue) {}
    
    public function __invoke(SomeEvent $event): void
    {
        // Eyni anda etmə, queue-ya at
        $this->queue->push(new ProcessEventJob($event));
    }
}

// Laravel — built-in
class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable;
    
    public int $tries = 3;
    public int $backoff = 60;
    
    public function handle(UserRegistered $event): void
    {
        // Bu queue-da işləyir, sync deyil
    }
}

// Symfony — Messenger ilə inteqrasiya
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\Event;

class AsyncListener
{
    public function __construct(private MessageBusInterface $bus) {}
    
    public function __invoke(Event $event): void
    {
        $this->bus->dispatch($event);   // Messenger queue-ya
    }
}
```

---

## Domain events (DDD)

```php
<?php
// Aggregate domain event-lər toplayır
abstract class AggregateRoot
{
    private array $events = [];
    
    protected function recordEvent(object $event): void
    {
        $this->events[] = $event;
    }
    
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        return $events;
    }
}

class Order extends AggregateRoot
{
    public function pay(): void
    {
        $this->status = OrderStatus::Paid;
        $this->recordEvent(new OrderPaid($this->id, $this->total));
    }
}

// Repository — save zamanı event-ləri dispatch
class OrderRepository
{
    public function __construct(
        private EntityManager $em,
        private EventDispatcherInterface $dispatcher,
    ) {}
    
    public function save(Order $order): void
    {
        $this->em->persist($order);
        $this->em->flush();
        
        // Save sonra, transaction commit-dən SONRA dispatch
        foreach ($order->pullEvents() as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}

// VARIANT — outbox pattern (transaction-da saxla, Debezium/CDC göndərir)
public function save(Order $order): void
{
    $this->em->wrapInTransaction(function () use ($order) {
        $this->em->persist($order);
        
        foreach ($order->pullEvents() as $event) {
            $this->em->persist(OutboxEntry::fromEvent($event));
        }
    });
    // Outbox-dan async publish (CDC və ya cron)
}
```

---

## Best practices

```
✓ Event POPO immutable (readonly properties)
✓ Event adı PAST TENSE — UserRegistered, OrderPaid (NOT RegisterUser)
✓ Listener idempotent — eyni event 2 dəfə gəlsə də problem yoxdur
✓ Listener fast — async heavy work
✓ Listener exception → event flow-u pozur (try/catch lazım)
✓ Subscriber — domain bounded contexts üçün
✓ Domain event repository.save() içərisində commit sonrası
✓ Wildcard listener — yalnız debugging/logging
✓ Type-safe (event class hint, no string magic)

❌ Event-də heavy logic (event obyekt sadə data olmalıdır)
❌ Listener içində birbaşa başqa event dispatch (gizli flow)
❌ Sync-də external API call (latency)
❌ DB transaction içində event dispatch (rollback olarsa email gedib)
❌ Circular event chains (A → B → A)
```

---

## İntervyu Sualları

- PSR-14 hansı problemi həll edir?
- Event-i niyə past tense ilə adlandırırsınız?
- Listener vs Subscriber arasındakı fərq?
- Stoppable event nə vaxt istifadə olunur?
- Listener priority necə işləyir?
- Domain event-ləri nə vaxt dispatch etmək lazımdır (save-dən əvvəl/sonra)?
- Event-driven kod necə test edilir?
- Async event üçün PSR-14-ün native dəstəyi varmı?
- Laravel events PSR-14 uyumludur?
- Wildcard event listening təhlükəlidir?
- Event-də mutability niyə anti-pattern?
- Event Sourcing və PSR-14 events arasında fərq?
