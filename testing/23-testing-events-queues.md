# Testing Events & Queues

## Nədir? (What is it?)

Event və queue testing, async kodun düzgün işlədiyini yoxlamaq üçün istifadə olunan
texnikalardır. Real application-da bəzi əməliyyatlar background-da (asynchronous) icra
olunur — email göndərmək, notification dispatch etmək, heavy processing. Test zamanı
bu job-ları həqiqətən işlətmək istəmirik; əvəzinə onların **dispatch olunub-olunmadığını**
yoxlayırıq.

Laravel bu məqsəd üçün güclü "fake" facade-lar təqdim edir: `Event::fake()`, `Queue::fake()`,
`Bus::fake()`, `Notification::fake()`. Bu fake-lər real dispatch-i dayandırır və bizə
assertion etmək imkanı verir.

### Niyə Async Testing Vacibdir?

1. **Speed** - Queue-nu real işlətmək test-i yavaşladır
2. **Isolation** - Əgər external system (email, SMS) varsa, onu vurmuruq
3. **Determinism** - Async timing flaky test yarada bilər
4. **Focus** - Biz dispatch məntiqini test edirik, job daxilini ayrı test edirik

## Əsas Konseptlər (Key Concepts)

### Fake Facades

| Fake | Məqsəd |
|------|--------|
| `Event::fake()` | Event dispatch-i yoxlamaq |
| `Queue::fake()` | Job push-u yoxlamaq |
| `Bus::fake()` | Dispatch (Bus) komandalarını yoxlamaq |
| `Notification::fake()` | Notification göndərilməsini yoxlamaq |
| `Mail::fake()` | Mail göndərilməsini yoxlamaq |

### Event Testing Flow

```
Request → Controller → Event::dispatch() → Listener(lar)
                ↓
            Test: Event::fake() əvvəlcədən çağırılır
            Assertion: Event::assertDispatched(UserRegistered::class)
```

### Queue Testing Flow

```
Request → Controller → Job::dispatch() → Queue → Worker → Handle
                ↓
            Test: Queue::fake() çağırılır (job queue-ya düşür amma icra olunmur)
            Assertion: Queue::assertPushed(ProcessOrderJob::class)
```

### Scheduled Task Testing

Laravel scheduler-də `Schedule::command('foo:bar')->daily()` kimi yazılanları test etmək
üçün adətən komanda-nı birbaşa çağırırıq və schedule registration-u ayrı yoxlayırıq.

## Praktiki Nümunələr (Practical Examples)

### Event Test

```php
public function test_user_registration_fires_event(): void
{
    Event::fake();

    $this->post('/register', [
        'email' => 'test@example.com',
        'password' => 'secret123',
    ]);

    Event::assertDispatched(UserRegistered::class);
}
```

### Queue Test

```php
public function test_order_creation_queues_job(): void
{
    Queue::fake();

    $this->postJson('/orders', ['product_id' => 1, 'quantity' => 2]);

    Queue::assertPushed(ProcessOrderJob::class);
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### 1. Event Testing - Ətraflı

```php
// app/Events/OrderPlaced.php
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}

// app/Http/Controllers/OrderController.php
public function store(Request $request)
{
    $order = Order::create($request->validated());
    event(new OrderPlaced($order));

    return response()->json($order, 201);
}

// tests/Feature/OrderControllerTest.php
namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_placing_order_dispatches_event(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/orders', [
                'product_id' => 1,
                'quantity'   => 3,
            ])
            ->assertCreated();

        Event::assertDispatched(OrderPlaced::class, function ($event) {
            return $event->order->quantity === 3;
        });
    }

    public function test_event_is_dispatched_exactly_once(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'product_id' => 1, 'quantity' => 1,
        ]);

        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    public function test_no_event_dispatched_on_validation_failure(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/orders', []) // empty payload
            ->assertUnprocessable();

        Event::assertNotDispatched(OrderPlaced::class);
    }

    public function test_listener_is_attached_to_event(): void
    {
        Event::fake();

        Event::assertListening(
            OrderPlaced::class,
            SendOrderConfirmationListener::class,
        );
    }
}
```

### 2. Selective Event Fake

```php
public function test_fake_only_specific_events(): void
{
    // Yalnız OrderPlaced fake olunur, digər event-lər real işləyir
    Event::fake([OrderPlaced::class]);

    $this->postJson('/api/orders', [...]);

    Event::assertDispatched(OrderPlaced::class);
}

public function test_fake_except_certain_events(): void
{
    // Hər şey fake-dir, amma UserActivity real işləyir
    Event::fakeExcept([UserActivityLogged::class]);

    // ...
}
```

### 3. Queue / Job Testing

```php
// app/Jobs/SendWelcomeEmail.php
class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function handle(Mailer $mailer): void
    {
        $mailer->to($this->user->email)->send(new WelcomeMail($this->user));
    }
}

// tests/Feature/RegisterTest.php
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_queues_welcome_email_job(): void
    {
        Queue::fake();

        $this->postJson('/api/register', [
            'name'     => 'Orkhan',
            'email'    => 'orkhan@example.com',
            'password' => 'password',
        ])->assertCreated();

        Queue::assertPushed(SendWelcomeEmail::class, function ($job) {
            return $job->user->email === 'orkhan@example.com';
        });
    }

    public function test_job_is_pushed_to_specific_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/register', [...]);

        Queue::assertPushedOn('emails', SendWelcomeEmail::class);
    }

    public function test_job_has_correct_delay(): void
    {
        Queue::fake();

        SendWelcomeEmail::dispatch($user)->delay(now()->addMinutes(5));

        Queue::assertPushed(SendWelcomeEmail::class, function ($job) {
            return $job->delay !== null;
        });
    }

    public function test_no_job_pushed_when_email_invalid(): void
    {
        Queue::fake();

        $this->postJson('/api/register', [
            'email' => 'not-email',
        ])->assertUnprocessable();

        Queue::assertNothingPushed();
    }
}
```

### 4. Testing Job Handle Logic (Unit-like)

```php
public function test_send_welcome_email_job_sends_mail(): void
{
    Mail::fake();

    $user = User::factory()->create(['email' => 'u@example.com']);

    // Job-u birbaşa icra edirik, handle() metodu çağırılır
    (new SendWelcomeEmail($user))->handle(app('mailer'));

    Mail::assertSent(WelcomeMail::class, fn ($m) => $m->hasTo('u@example.com'));
}
```

### 5. Bus::fake — Dispatch Chain & Batch

```php
// app/Services/CheckoutService.php
public function checkout(Order $order): void
{
    Bus::chain([
        new ChargeCustomer($order),
        new GenerateInvoice($order),
        new NotifyWarehouse($order),
    ])->dispatch();
}

// tests/Feature/CheckoutTest.php
public function test_checkout_dispatches_job_chain(): void
{
    Bus::fake();
    $order = Order::factory()->create();

    (new CheckoutService())->checkout($order);

    Bus::assertChained([
        ChargeCustomer::class,
        GenerateInvoice::class,
        NotifyWarehouse::class,
    ]);
}

public function test_checkout_dispatches_batch(): void
{
    Bus::fake();

    $service = new BatchImportService();
    $service->import([1, 2, 3]);

    Bus::assertBatched(function (PendingBatch $batch) {
        return $batch->jobs->count() === 3
            && $batch->name === 'product-import';
    });
}
```

### 6. Notification::fake

```php
public function test_password_reset_sends_notification(): void
{
    Notification::fake();
    $user = User::factory()->create(['email' => 'u@example.com']);

    $this->postJson('/password/email', ['email' => 'u@example.com']);

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function ($notification) {
            return str_contains($notification->token, '');
        }
    );
}

public function test_notification_sent_via_correct_channels(): void
{
    Notification::fake();
    $user = User::factory()->create();

    $user->notify(new OrderShippedNotification($order));

    Notification::assertSentTo($user, OrderShippedNotification::class,
        function ($notification, array $channels) {
            return in_array('mail', $channels) && in_array('database', $channels);
        }
    );
}
```

### 7. Testing Scheduled Tasks

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('reports:daily')->dailyAt('01:00');
}

// tests/Feature/ScheduleTest.php
public function test_daily_report_command_is_scheduled(): void
{
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())->filter(
        fn ($e) => str_contains($e->command, 'reports:daily')
    );

    $this->assertCount(1, $events);
    $this->assertSame('0 1 * * *', $events->first()->expression);
}

public function test_daily_report_command_runs_successfully(): void
{
    Queue::fake();

    $this->artisan('reports:daily')->assertExitCode(0);

    Queue::assertPushed(GenerateReportJob::class);
}
```

### 8. Testing Job with Database Interactions

```php
public function test_process_order_job_marks_order_as_processed(): void
{
    $order = Order::factory()->create(['status' => 'pending']);

    (new ProcessOrderJob($order))->handle();

    $this->assertDatabaseHas('orders', [
        'id'     => $order->id,
        'status' => 'processed',
    ]);
}

public function test_job_retries_on_failure(): void
{
    $job = new ProcessOrderJob($order = Order::factory()->create());

    $this->assertSame(3, $job->tries);
    $this->assertSame(60, $job->backoff);
}
```

## Interview Sualları

**Q1: `Event::fake()` nə edir?**
A: Event dispatcher-i fake implementation ilə əvəz edir. `event()` çağırıldıqda
listener-lər işləmir, amma event-lər registered olur və assertion etmək mümkün olur.

**Q2: `Event::fake()` və `Event::fakeExcept()` arasında fərq nədir?**
A: `fake()` bütün event-ləri fake edir. `fakeExcept([X::class])` X-dən başqa hər şeyi
fake edir — yəni X real işləyir.

**Q3: Queue::fake() istifadə edərkən job-un handle() metodu test olunur?**
A: Xeyr. Queue::fake() yalnız dispatch-i yoxlayır. Job-un daxili məntiqini test etmək üçün
job-u manual instantiate edib `handle()` metodunu çağırmaq lazımdır.

**Q4: `Bus::chain` və `Bus::batch` arasında fərq nədir?**
A: **Chain** — job-lar ardıcıl işləyir, biri uğursuz olsa chain dayanır. **Batch** — job-lar
paralel işləyir, hamısı bitəndən sonra callback çağırılır.

**Q5: Notification testing-də `assertSentTo` və `assertSentToSecret` fərqi?**
A: `assertSentTo(User, NotificationClass)` - istifadəçi notifiable-dir.
`assertSentToSecret()` yoxdur. Amma `assertNothingSent` və `assertCount` mövcuddur.

**Q6: Async kodu niyə production-dakı kimi test etmirik (real queue ilə)?**
A: Slow, flaky, non-deterministic, external dependency. Unit səviyyəsində dispatch-ı,
inteqrasiya səviyyəsində handle-ı ayrı test edirik.

**Q7: `ShouldQueue` interface olmayan event-i necə async edə bilərik?**
A: Listener-də `ShouldQueue` istifadə olunur. Event özü sync-dir; listener async icra olunur.

**Q8: Scheduled task-ı necə test edirsiniz?**
A: İki ayrı test: (1) registration test — cron expression yoxlanılır; (2) command logic test —
`artisan()` helper ilə komanda çağırılır.

**Q9: `Event::fake()`-dən sonra manual `Event::dispatch()` işləyirmi?**
A: Dispatch olunur (fake registry-ə düşür), amma listener-lər çağırılmır.

**Q10: Production-da queue failed olarsa necə debug edirik (test strategiyası)?**
A: Failed job table-ı yoxlayırıq. Test-də `job->fail($exception)` simulyasiya edib
retry logic və failed handler-i yoxlayırıq.

## Best Practices / Anti-Patterns

### Best Practices

- **Fake-i test-in başında çağırın** - Əks halda əvvəlki dispatch-lər qeyd olunmaz
- **Selective fake** - Lazım olan event/queue-ları fake edin, qalanları real işləsin
- **Job handle-ı ayrı test edin** - Dispatch və handle iki fərqli test olmalıdır
- **Closure-da specific payload yoxlayın** - Yalnız class deyil, data-nı da yoxlayın
- **`assertNothingDispatched`** - Validation xətası zamanı event getməməlidir

### Anti-Patterns

- **Real queue worker ilə test** - Slow, flaky, CI-də problem
- **Fake çağırmadan assertion** - Silent failure, test həmişə keçir
- **Yalnız class yoxlamaq** - `Event::assertDispatched(X::class)` - amma data səhvdirsə?
- **Job-un daxilini dispatch test-ində yoxlamaq** - Tək responsibility prinsipi pozulur
- **Fake-i global setUp-a qoymaq** - Bəzi test-lər real dispatch istəyə bilər
