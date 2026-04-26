# Testing Email & Notifications (Middle)
## İcmal

Email və notification testing, istifadəçilərə göndərilən mesajların (email, SMS, Slack,
database notification və s.) düzgün format, alıcı və məzmunla göndərildiyini yoxlamaq
prosesidir. Real email SMTP server-ə göndərmək əvəzinə Laravel "fake" facade-lar ilə
assertion edirik.

Email testing iki səviyyədə olur:
1. **Mailable testing** - Mail obyektinin özü (subject, view, data) düzgündür?
2. **Dispatch testing** - Doğru yerdə Mail::send / notify çağırılır?

### Niyə Mail/Notification Testing Kritik?

1. **Customer-facing content** - Səhv email müştəriyə çatırsa, reputation zədəsi
2. **Compliance** - GDPR, unsubscribe linkləri qanun tələbidir
3. **Deliverability** - Test zamanı real email göndərmək spam score-a təsir edir
4. **Multi-channel** - Eyni notification bir neçə kanalda fərqli format olur

## Niyə Vacibdir

- **Müştəriyə çatan məzmunun keyfiyyəti** — Yanlış subject, broken link, və ya səhv alıcı olan email müştəri itkisinə və reputation zədəsinə səbəb olur; mailable unit testlər bunu production-dan əvvəl tutur.
- **Compliance tələblərinin yerinə yetirilməsi** — GDPR unsubscribe link-i, HIPAA-uyğun məlumat formatı kimi qanuni tələblər email testlərində yoxlanılmalıdır; test olmadan compliance pozuntusu gizli qalır.
- **Multi-channel notification mürəkkəbliyi** — Eyni notification mail, database, Slack kanallarında fərqli format tələb edir; hər kanalın ayrıca yoxlanması channel-spesifik bug-ları aşkar edir.
- **Real SMTP-yə göndərməkdən qaçınmaq** — Test zamanı real email göndərmək spam filter-lərin score-unu artırır, rate limit-ə çatır, xərc yaradır; `Mail::fake()` bütün bunları aradan qaldırır.
- **Negative path-ların əhəmiyyəti** — Unsubscribe etmiş, email_notifications=false olan istifadəçiyə email getməməsi kritikdir; bu path test edilmədikdə istifadəçi razısızlığı yaranır.

## Əsas Anlayışlar

### Fake Helpers

| Fake | Nə yoxlayır |
|------|-------------|
| `Mail::fake()` | `Mail::send`, `Mail::to(...)->send(...)` |
| `Notification::fake()` | `$user->notify()`, `Notification::send()` |

### Assertion Metodları

```php
// Mail
Mail::assertSent(OrderMail::class);
Mail::assertSent(OrderMail::class, fn ($m) => $m->hasTo('a@b.com'));
Mail::assertNotSent(OrderMail::class);
Mail::assertNothingSent();
Mail::assertQueued(OrderMail::class);
Mail::assertSentTimes(OrderMail::class, 1);

// Notification
Notification::assertSentTo($user, X::class);
Notification::assertSentToTimes($user, X::class, 2);
Notification::assertNotSentTo($user, X::class);
Notification::assertNothingSent();
Notification::assertCount(3);
Notification::assertSentOnDemand(X::class);
```

### Notification Kanalları

```
mail       → email (Markdown və ya HTML)
database   → notifications table-a yazılır
broadcast  → WebSocket event
sms/nexmo  → text message (Vonage)
slack      → Slack webhook
custom     → öz channel-ınız
```

## Praktik Baxış

### Best Practices

- **Mail content-i Mailable unit test-ində yoxlayın** - Feature test yalnız dispatch-ı yoxlasın
- **Closure ilə specific assertion** - `assertSent` + `hasTo`, `subject`, data yoxlanışı
- **Unsubscribe / preferences** - Hər email ötürməyən path-i test edin
- **Markdown rendering** - `->render()` ilə compile xətası tutulur
- **Fake-i lokal test-də saxlayın** - Global CI-də real mail getmir (`.env.testing`)

### Anti-Patterns

- **Real SMTP ilə test** - Spam, slow, flaky
- **`assertSent` olmadan dispatch yoxlaması** - Silent pass
- **Content-i Feature test-də yoxlamaq** - View değişəndə 100 test qırılır
- **Yalnız happy path** - Unsubscribe, invalid email, bounce test olunmur
- **Fake unutmaq** - `Mail::fake()` çağırılmazsa test production-a email göndərə bilər
- **Hardcoded email addresses** - `u@example.com` əvəzinə faker istifadə edin

## Nümunələr

### Mailable Testing

```php
public function test_welcome_mail_has_correct_subject_and_user_name(): void
{
    $user = User::factory()->create(['name' => 'Orkhan']);

    $mailable = new WelcomeMail($user);

    $mailable->assertHasSubject('Welcome to Our App!');
    $mailable->assertSeeInHtml('Orkhan');
    $mailable->assertSeeInHtml('https://app.com/getting-started');
}
```

### Dispatch Testing

```php
public function test_registration_sends_welcome_email(): void
{
    Mail::fake();

    $this->postJson('/register', [
        'email' => 'new@example.com',
        'password' => 'password',
        'name' => 'New',
    ])->assertCreated();

    Mail::assertSent(WelcomeMail::class, fn ($m) => $m->hasTo('new@example.com'));
}
```

## Praktik Tapşırıqlar

### 1. Mailable - Content Testing

```php
// app/Mail/OrderShipped.php
class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your order #' . $this->order->id . ' has shipped!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.shipped',
            with: [
                'order'       => $this->order,
                'trackingUrl' => $this->order->tracking_url,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(storage_path("invoices/{$this->order->id}.pdf"))
                ->as('invoice.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

// tests/Unit/Mail/OrderShippedTest.php
namespace Tests\Unit\Mail;

use App\Mail\OrderShipped;
use App\Models\Order;
use Tests\TestCase;

class OrderShippedTest extends TestCase
{
    public function test_mail_has_expected_subject(): void
    {
        $order = Order::factory()->make(['id' => 1001]);
        $mail  = new OrderShipped($order);

        $mail->assertHasSubject('Your order #1001 has shipped!');
    }

    public function test_mail_renders_order_details(): void
    {
        $order = Order::factory()->make([
            'id' => 42,
            'tracking_url' => 'https://carrier.com/track/abc',
        ]);

        $mail = new OrderShipped($order);

        $mail->assertSeeInHtml('#42');
        $mail->assertSeeInHtml('https://carrier.com/track/abc');
    }

    public function test_mail_has_invoice_attachment(): void
    {
        $order = Order::factory()->make(['id' => 1]);
        $mail  = new OrderShipped($order);

        $mail->assertHasAttachment(
            Attachment::fromPath(storage_path('invoices/1.pdf'))->as('invoice.pdf')
        );
    }
}
```

### 2. Feature Test - Send Mail

```php
namespace Tests\Feature;

use App\Mail\OrderShipped;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShipOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_order_sends_email_to_customer(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for($customer = User::factory()->create())->create();

        $this->actingAs($admin)
            ->postJson("/api/orders/{$order->id}/ship")
            ->assertOk();

        Mail::assertSent(OrderShipped::class, function ($mail) use ($order, $customer) {
            return $mail->hasTo($customer->email)
                && $mail->order->is($order);
        });
    }

    public function test_email_is_queued_not_sent_immediately(): void
    {
        Mail::fake();

        $this->actingAs($admin = User::factory()->admin()->create())
            ->postJson("/api/orders/{$order->id}/ship");

        // ShouldQueue interface varsa - assertQueued
        Mail::assertQueued(OrderShipped::class);
        Mail::assertNotSent(OrderShipped::class); // immediate deyil
    }

    public function test_no_email_sent_when_order_already_shipped(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'shipped']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/orders/{$order->id}/ship")
            ->assertStatus(422);

        Mail::assertNothingSent();
    }
}
```

### 3. Notification - Multiple Channels

```php
// app/Notifications/InvoicePaid.php
class InvoicePaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'slack'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment received')
            ->line("Your invoice #{$this->invoice->number} has been paid.")
            ->action('View Invoice', url("/invoices/{$this->invoice->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'amount'     => $this->invoice->amount,
        ];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->success()
            ->content("Invoice #{$this->invoice->number} paid");
    }
}

// tests/Feature/InvoicePaidNotificationTest.php
class InvoicePaidNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_sent_on_payment(): void
    {
        Notification::fake();

        $user    = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->create(['status' => 'pending']);

        $this->actingAs($user)
            ->postJson("/api/invoices/{$invoice->id}/pay", ['method' => 'card']);

        Notification::assertSentTo($user, InvoicePaid::class,
            function ($notification, array $channels) use ($invoice) {
                return $notification->invoice->id === $invoice->id
                    && in_array('mail', $channels)
                    && in_array('database', $channels)
                    && in_array('slack', $channels);
            }
        );
    }

    public function test_notification_not_sent_if_payment_fails(): void
    {
        Notification::fake();

        // simulate gateway failure
        $this->mock(PaymentGateway::class)
            ->shouldReceive('charge')->andThrow(new PaymentException);

        $this->actingAs($user = User::factory()->create())
            ->postJson("/api/invoices/1/pay", ['method' => 'card'])
            ->assertStatus(500);

        Notification::assertNothingSent();
    }
}
```

### 4. On-Demand Notification (Anonymous)

```php
// Service code
Notification::route('mail', 'support@example.com')
    ->route('slack', env('SLACK_WEBHOOK'))
    ->notify(new SystemAlert('DB down'));

// Test
public function test_system_alert_sends_to_support_channels(): void
{
    Notification::fake();

    AlertService::dbDown();

    Notification::assertSentOnDemand(SystemAlert::class,
        function ($notification, array $channels, AnonymousNotifiable $notifiable) {
            return $notifiable->routes['mail'] === 'support@example.com';
        }
    );
}
```

### 5. Database Notification Test

```php
public function test_notification_stored_in_database(): void
{
    $user = User::factory()->create();

    $user->notify(new InvoicePaid($invoice = Invoice::factory()->create()));

    $this->assertDatabaseHas('notifications', [
        'notifiable_id'   => $user->id,
        'notifiable_type' => User::class,
        'type'            => InvoicePaid::class,
    ]);

    $this->assertCount(1, $user->fresh()->notifications);
}
```

### 6. Unsubscribe / Opt-Out Logic Test

```php
public function test_user_with_unsubscribed_preference_does_not_receive_mail(): void
{
    Mail::fake();

    $user = User::factory()->create(['email_notifications' => false]);

    NewsletterDispatcher::dispatch($user);

    Mail::assertNotSent(NewsletterMail::class);
}
```

### 7. Multiple Recipients

```php
public function test_mail_cc_and_bcc_set(): void
{
    Mail::fake();

    Mail::to('a@example.com')
        ->cc('cc@example.com')
        ->bcc('bcc@example.com')
        ->send(new OrderShipped($order));

    Mail::assertSent(OrderShipped::class, function ($mail) {
        return $mail->hasTo('a@example.com')
            && $mail->hasCc('cc@example.com')
            && $mail->hasBcc('bcc@example.com');
    });
}
```

### 8. Markdown Mailable Test

```php
public function test_markdown_mail_renders_correctly(): void
{
    $order = Order::factory()->make(['total' => 99.99]);

    $rendered = (new OrderShipped($order))->render();

    $this->assertStringContainsString('99.99', $rendered);
    $this->assertStringContainsString('View Order', $rendered);
}
```

## Ətraflı Qeydlər

**Q1: `Mail::fake()` çağırıldıqdan sonra email həqiqətən göndərilir?**
A: Xeyr. Mail facade fake-ə yönlənir; SMTP-yə getmir, amma göndərmə qeydə alınır.

**Q2: `Mail::assertSent` və `Mail::assertQueued` fərqi?**
A: `assertSent` sync mail üçün (Mailable `ShouldQueue` implement etmir).
`assertQueued` async mail üçün (ShouldQueue implement edir).

**Q3: Notification kanalını necə test edirik?**
A: `assertSentTo` closure-ında ikinci parametr `array $channels` olur; orada `in_array('mail', $channels)` yoxlayırıq.

**Q4: Email content-i (subject, body) test etmək üçün hansı yanaşma?**
A: Mailable-ın özü ilə: `$mail->assertHasSubject()`, `$mail->assertSeeInHtml()`, `$mail->render()`.

**Q5: On-demand notification nədir və necə test olunur?**
A: Notifiable model olmadan göndərilən notification (`Notification::route('mail', 'x@y')->notify(...)`).
Test: `Notification::assertSentOnDemand(X::class)`.

**Q6: `assertNothingSent()` nə vaxt istifadə olunur?**
A: Negative scenario-larda — validation xətası və ya business rule pozulan hallarda heç bir email getməməlidir.

**Q7: Queue + Mail fake eyni anda?**
A: Bəli, ikisini də fake edə bilərsiniz. Amma adətən `Mail::fake()` kifayətdir, çünki queued mail də Mail fake tərəfindən tutulur.

**Q8: Real SMTP üçün staging-də necə test edirik?**
A: Mailtrap, Mailhog, MailCatcher kimi tool-lar SMTP əvəzinə istifadə olunur. Production database-ə email getmir.

**Q9: Email-in attachments-ini necə assertion edirik?**
A: `$mail->assertHasAttachment(Attachment::fromPath(...))` və ya `assertHasAttachedData()`.

**Q10: `notify()` ilə `Notification::send()` fərqi?**
A: `$user->notify()` tək istifadəçi üçün. `Notification::send([$u1, $u2], $notification)` collection üçün (broadcast kanalı işləmir `sendNow` lazımdır).

## Əlaqəli Mövzular

- [Mocking (Middle)](07-mocking.md)
- [Testing Events & Queues (Middle)](15-testing-events-queues.md)
- [Testing Authentication & Authorization (Middle)](18-testing-authentication.md)
- [Testing Third-Party Services (Senior)](28-testing-third-party.md)
- [Contract Testing (Senior)](24-contract-testing.md)
- [Testing Best Practices (Senior)](30-testing-best-practices.md)
