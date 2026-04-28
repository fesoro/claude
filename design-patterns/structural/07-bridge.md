# Bridge (Senior ⭐⭐⭐)

## İcmal
Bridge pattern, abstraction-ı implementation-dan ayırır; hər iki tərəf müstəqil olaraq dəyişə bilir. Inheritance ilə combination-ı dəyişdirir: subclass explosion əvəzinə iki ayrı hierarchy yaradılır, aralarında "körpü" (composition) qurulur.

## Niyə Vacibdir
PHP/Laravel layihələrində tez-tez ortaya çıxan problem: eyni feature-un müxtəlif "üsulları" ilə müxtəlif "növləri" var. Məsələn, Notification (urgent/regular) × Delivery Channel (email/SMS/push) = 2×3 = 6 subclass; yeni channel əlavə olduqda daha 2 subclass lazım olur. Bridge ilə 2+3=5 class kifayətdir, yeni channel üçün yalnız 1 yeni class. Notification type-ı channel-dən asılı deyil — hər biri müstəqil inkişaf edə bilər.

## Əsas Anlayışlar
- **Abstraction**: yüksək səviyyəli interface; implementation-a istinad edir, birbaşa işi etmir
- **Refined Abstraction**: Abstraction-ın konkret alt-versiyaları (UrgentNotification, RegularNotification)
- **Implementation Interface**: abstraction-ın istifadə etdiyi low-level əməliyyatlar (MessageSender)
- **Concrete Implementation**: Implementation interface-in real versiyaları (EmailSender, SMSSender)
- **Bridge (composition)**: Abstraction, Implementation interface-i field kimi saxlayır; runtime-da inject olunur

## Praktik Baxış
- **Real istifadə**: notification system (type × channel), report rendering (report type × output format), payment processing (payment type × gateway), logger (log level × storage backend), cross-platform UI components
- **Trade-off-lar**: class sayı azalır (2+3 vs 6); hər tərəf müstəqil test edilir; lakin sadə hallarda over-engineering; əlavə abstraction layer code-u oxumağı çətinləşdirir
- **İstifadə etməmək**: dimension sayı 2-dən azdırsa (sadə Strategy kifayətdir); class hierarchy-ləri sabitdirsə (değişmeyeceksə) Bridge qurulmasına dəymir
- **Common mistakes**: Bridge ilə Adapter-i qarışdırmaq (Bridge proactive — əvvəldən dizayn, Adapter reactive — uyğunsuzluğu düzəltmək); abstraction tərəfini boş saxlamaq (yalnız delegation edirsə, Strategy bəsdir)

### Anti-Pattern Nə Zaman Olur?
Bridge **over-engineering** və ya **DI ilə artıq həll edilmiş problemi** modelləşdirəndə anti-pattern olur:

- **Strategy kifayət edən yerdə Bridge qurmaq**: Yalnız bir abstraction hierarchy varsa (məs: yalnız Notification növləri, sender fərqliyi yoxdursa), Bridge əlavə abstraction layer-i artıq mürəkkəblik əlavə edir. Strategy pattern bəsdir.
- **DI ilə eyni nəticə alınan yer**: `new UrgentNotification(app(MessageSender::class))` sadəcə dependency injection-dur. Bridge adını vermək kodu daha "pattern-heavy" edir, amma real struktural fayda vermir. Əgər DI ilə problem həll olunursa — Bridge qurmaq over-engineering-dir.
- **İkinci hierarchy olmayan yerdə Bridge tətbiq etmək**: Bridge iki müstəqil hierarchy tələb edir (type × channel). Əgər channel sabitdirsə (həmişə email), Bridge lazım deyil — şişirtmə olur.
- **Abstract class-ı boş saxlamaq**: Əgər `Notification` abstract class yalnız `$this->sender->send(...)` edir və heç bir əlavə məntiq yoxdursa — bu Strategy-dir, Bridge deyil. Bridge-in abstraction tərəfi öz davranışını (formatting, priority, logging) əlavə etməlidir.
- **İkinci ölçü əvəzinə config flag istifadə etmək**: `if ($this->type === 'urgent')` şərti Bridge əvəzinə — bu, Bridge-i implement etməyib sadəcə if/else-ə qaçmaqdır. Type növləri artdıqca bu blok böyüyür.

## Nümunələr

### Ümumi Nümunə
Televizor pult düşünün. Pultun "növü" (smart remote / basic remote) abstraction-dır. Televizorun "markası" (Samsung / LG / Sony) implementation-dır. Hər pult hər televizor ilə işləyə bilər — abstraction (pult) implementation-ı (TV) composition ilə tutur. Yeni bir pult növü əlavə etdikdə bütün TV markaları üçün yeni subclass lazım deyil.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Notifications;

// Implementation Interface — "necə çatdırılır?"
interface MessageSender
{
    public function send(string $recipient, string $subject, string $body): void;
}

// Concrete Implementations — yalnız göndərmə mexanizmi ilə məşğuldur
class EmailSender implements MessageSender
{
    public function __construct(private \Illuminate\Mail\Mailer $mailer) {}

    public function send(string $recipient, string $subject, string $body): void
    {
        $this->mailer->raw($body, function ($message) use ($recipient, $subject) {
            $message->to($recipient)->subject($subject);
        });
    }
}

class SmsSender implements MessageSender
{
    public function __construct(private \Twilio\Rest\Client $twilio, private string $from) {}

    public function send(string $recipient, string $subject, string $body): void
    {
        // SMS-də subject istifadə olunmur
        $this->twilio->messages->create($recipient, [
            'from' => $this->from,
            'body' => "[{$subject}] {$body}",
        ]);
    }
}

class PushNotificationSender implements MessageSender
{
    public function send(string $recipient, string $subject, string $body): void
    {
        // Firebase/APNs push notification
        \App\Services\FirebaseService::send(token: $recipient, title: $subject, message: $body);
    }
}

// Abstraction — "nə göndərilir, necə formatlanır?"
abstract class Notification
{
    public function __construct(
        protected MessageSender $sender,  // Bridge: composition ilə implementation-ı tutur
    ) {}

    abstract public function send(User $user, string $message): void;
}

// Refined Abstractions
class UrgentNotification extends Notification
{
    public function send(User $user, string $message): void
    {
        // Urgent: prefix əlavə edir, uppercase, LOG yazır
        $formattedBody = strtoupper("[URGENT] {$message}");

        \Log::warning("Urgent notification sent to user {$user->id}");

        $this->sender->send(
            recipient: $user->contact,
            subject: 'URGENT: Action Required',
            body: $formattedBody
        );
    }
}

class RegularNotification extends Notification
{
    public function send(User $user, string $message): void
    {
        $this->sender->send(
            recipient: $user->contact,
            subject: 'Notification',
            body: $message
        );
    }
}

class DigestNotification extends Notification
{
    private array $messages = [];

    public function queue(string $message): void
    {
        $this->messages[] = $message;
    }

    public function send(User $user, string $message): void
    {
        $this->messages[] = $message;
        $digest = implode("\n---\n", $this->messages);

        $this->sender->send(
            recipient: $user->email,
            subject: 'Your daily digest',
            body: $digest,
        );
    }
}
```

**Service Provider — Bridge wiring:**

```php
<?php

// AppServiceProvider-da
public function register(): void
{
    // Runtime-da sender swap oluna bilər — test/prod fərqli sender
    $this->app->bind(MessageSender::class, function () {
        return match (config('notifications.default_channel')) {
            'sms'  => new SmsSender(app(\Twilio\Rest\Client::class), config('services.twilio.from')),
            'push' => new PushNotificationSender(),
            default => new EmailSender(app(\Illuminate\Mail\Mailer::class)),
        };
    });
}
```

**Usage — abstraction ilə implementation müstəqil:**

```php
<?php

// Controller və ya Service-də
class AlertService
{
    public function sendSystemAlert(User $admin, string $message): void
    {
        // Urgent notification, email ilə
        $notification = new UrgentNotification(new EmailSender(app(\Illuminate\Mail\Mailer::class)));
        $notification->send($admin, $message);
    }

    public function sendMarketingMessage(User $user, string $message): void
    {
        // Regular notification, SMS ilə — abstraction + implementation azad kombinasiya
        $notification = new RegularNotification(new SmsSender(/* ... */));
        $notification->send($user, $message);
    }
}

// Test — real sender yerinə mock inject edirik
class NotificationTest extends TestCase
{
    public function test_urgent_notification_uppercases_message(): void
    {
        $senderMock = $this->createMock(MessageSender::class);
        $senderMock->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('URGENT: Action Required'),
                $this->stringContains('[URGENT]')
            );

        $notification = new UrgentNotification($senderMock);
        $notification->send(new User(['contact' => 'test@example.com']), 'server is down');
    }
}
```

## Praktik Tapşırıqlar
1. Laravel layihənizdə mövcud notification sistemini nəzərdən keçirin — `if ($user->prefers_sms)` tipli şərtlər bridge ilə necə ayrıla bilər? Refactor edin
2. Report export sistemi qurun: `ReportAbstraction` (SalesReport, InventoryReport) × `ExportImplementation` (PdfExporter, CsvExporter, JsonExporter) — Bridge ilə 2+3=5 class
3. Logger bridge-i yaradın: `LogLevel` abstraction (DebugLog, ErrorLog, AuditLog) × `LogStorage` implementation (FileStorage, DatabaseStorage, CloudwatchStorage); hər kombinasiyanı test edin
4. `PaymentProcessor` abstraction + `PaymentGateway` implementation (StripeGateway, PayPalGateway); `RecurringPayment` vs `OneTimePayment` abstraction fərqini modelləyin

## Əlaqəli Mövzular
- [02-adapter.md](02-adapter.md) — Adapter uyğunsuzluğu geriyə dönük düzəldir (reactive), Bridge əvvəldən planlanır (proactive)
- [../behavioral/02-strategy.md](../behavioral/02-strategy.md) — Strategy yalnız algorithm dəyişdirir; Bridge-də abstraction tərəfinin öz hierarchy-si var
- [../creational/03-abstract-factory.md](../creational/03-abstract-factory.md) — Abstract Factory implementation-ları yarada bilər; Bridge composition üçün
- [../creational/04-builder.md](../creational/04-builder.md) — Builder Bridge-in abstraction × implementation kombinasiyalarını qurmaq üçün istifadə olunur
- [03-decorator.md](03-decorator.md) — hər ikisi composition istifadə edir; lakin Decorator responsibility əlavə edir, Bridge iki hierarchy-ni ayırır
- [../laravel/02-service-layer.md](../laravel/02-service-layer.md) — Service layer Bridge abstraction tərəfi kimi davrana bilər
- [../laravel/06-di-vs-service-locator.md](../laravel/06-di-vs-service-locator.md) — DI container Bridge-in implementation tərəfini inject edir
- [../behavioral/01-observer.md](../behavioral/01-observer.md) — Bridge abstraction-ı Observer-ə event fire edə bilər; notification sistemlərindəki birgə istifadə
- [../behavioral/07-state.md](../behavioral/07-state.md) — State pattern-i Bridge ilə birgə istifadə: abstraction state-ə görə implementation seçir
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Open/Closed: yeni notification type və ya sender əlavə etmək mövcud kodu dəyişmir
- [../architecture/05-hexagonal-architecture.md](../architecture/05-hexagonal-architecture.md) — Hexagonal arxitekturada port/adapter cütü Bridge ilə struktur baxımından eynidir
