# Abstract Factory (Middle ⭐⭐)

## İcmal
Abstract Factory pattern bir-biri ilə əlaqəli obyektlər ailəsini (family of related objects) yaratmaq üçün interface təqdim edir. Konkret class-ları göstərmədən, eyni "tema" altında bir neçə fərqli obyektin birlikdə yaradılmasını təmin edir.

## Niyə Vacibdir
Böyük Laravel layihələrində müxtəlif "rejim"lər üçün bir-biri ilə uyğun komponentlər lazım olur: test mühiti üçün fake mailer + fake SMS + fake payment, production üçün isə real olanlar. Hər birini ayrı-ayrı seçsən, uyğunsuz kombinasiya riski var (test mailer + real payment). Abstract Factory bu ailəni bir yerdə yaratmağı təmin edir.

## Əsas Anlayışlar
- **Abstract Factory interface**: Əlaqəli obyektlər ailəsi üçün factory method-larını elan edir
- **Concrete Factory**: Abstract Factory-ni implement edərək bir "ailəyə" aid obyektlər yaradır
- **Abstract Product**: Hər product növü üçün interface
- **Concrete Product**: Hər ailəyə (family) aid konkret product implement-ləri
- **Fərq Factory Method-dan**: Factory Method tək bir product yaradır; Abstract Factory bir neçə əlaqəli product-ı birlikdə yaradır

## Praktik Baxış
- **Real istifadə**: Notification infrastructure (template + sender + logger), test doubles vs production services, multi-tenant app-da tenant-a görə fərqli service ailəsi, UI theme-lər (Button + Modal + Input — Dark/Light tema)
- **Trade-off-lar**: Yeni product növü əlavə etmək (məs: `createTracker()` əlavə etmək) bütün Concrete Factory class-larını dəyişdirir — bu böyük dezavantajdır; Concrete Factory sayı artdıqca maintenance çətinləşir
- **İstifadə etməmək**: Sadəcə bir product növü lazım olduqda (Factory Method kifayətdir); product ailəsi konsepti yoxdursa; çox nadir dəyişiklik olacaq sistemlərdə
- **Common mistakes**: Hər dəyişiklik üçün yeni Factory yaratmaq; Abstract Factory-ni Strategy ilə qarışdırmaq — ikisi fərqli problemdir

## Nümunələr

### Ümumi Nümunə
Notification sistemini düşün: hər notification channel üçün üç əlaqəli komponent lazımdır — `MessageTemplate` (mətnin formatlanması), `Sender` (göndərmə), `DeliveryLogger` (log). Email channel-ı üçün bu üçlük birlikdə işləməlidir. SMS channel-ı üçün isə fərqli üçlük. Abstract Factory hər channel üçün bu üçlüyü bir yerdə yaradır.

### PHP/Laravel Nümunəsi

```php
// ===== Abstract Products =====
interface MessageTemplateInterface
{
    public function format(string $subject, string $body): string;
}

interface SenderInterface
{
    public function send(string $recipient, string $content): bool;
}

interface DeliveryLoggerInterface
{
    public function log(string $channel, string $recipient, bool $success): void;
}

// ===== Abstract Factory =====
interface NotificationFactoryInterface
{
    public function createTemplate(): MessageTemplateInterface;
    public function createSender(): SenderInterface;
    public function createLogger(): DeliveryLoggerInterface;
}

// ===== Email Family =====
class EmailTemplate implements MessageTemplateInterface
{
    public function format(string $subject, string $body): string
    {
        return "<html><head><title>{$subject}</title></head><body>{$body}</body></html>";
    }
}

class EmailSender implements SenderInterface
{
    public function send(string $recipient, string $content): bool
    {
        Mail::to($recipient)->html($content);
        return true;
    }
}

class EmailDeliveryLogger implements DeliveryLoggerInterface
{
    public function log(string $channel, string $recipient, bool $success): void
    {
        Log::channel('email')->info("Email delivery", [
            'channel'   => $channel,
            'recipient' => $recipient,
            'success'   => $success,
        ]);
    }
}

class EmailNotificationFactory implements NotificationFactoryInterface
{
    public function createTemplate(): MessageTemplateInterface
    {
        return new EmailTemplate();
    }

    public function createSender(): SenderInterface
    {
        return new EmailSender();
    }

    public function createLogger(): DeliveryLoggerInterface
    {
        return new EmailDeliveryLogger();
    }
}

// ===== SMS Family =====
class SmsTemplate implements MessageTemplateInterface
{
    public function format(string $subject, string $body): string
    {
        // SMS üçün qısa format — HTML yoxdur
        return mb_substr("{$subject}: {$body}", 0, 160);
    }
}

class SmsSender implements SenderInterface
{
    public function send(string $recipient, string $content): bool
    {
        app(TwilioClient::class)->messages->create($recipient, [
            'from' => config('services.twilio.from'),
            'body' => $content,
        ]);
        return true;
    }
}

class SmsDeliveryLogger implements DeliveryLoggerInterface
{
    public function log(string $channel, string $recipient, bool $success): void
    {
        Log::channel('sms')->info("SMS delivery", compact('channel', 'recipient', 'success'));
    }
}

class SmsNotificationFactory implements NotificationFactoryInterface
{
    public function createTemplate(): MessageTemplateInterface
    {
        return new SmsTemplate();
    }

    public function createSender(): SenderInterface
    {
        return new SmsSender();
    }

    public function createLogger(): DeliveryLoggerInterface
    {
        return new SmsDeliveryLogger();
    }
}

// ===== Fake Family (test üçün) =====
class FakeNotificationFactory implements NotificationFactoryInterface
{
    public function createTemplate(): MessageTemplateInterface
    {
        return new class implements MessageTemplateInterface {
            public function format(string $subject, string $body): string
            {
                return "[FAKE] {$subject}: {$body}";
            }
        };
    }

    public function createSender(): SenderInterface
    {
        return new class implements SenderInterface {
            public array $sent = [];

            public function send(string $recipient, string $content): bool
            {
                $this->sent[] = compact('recipient', 'content');
                return true;
            }
        };
    }

    public function createLogger(): DeliveryLoggerInterface
    {
        return new class implements DeliveryLoggerInterface {
            public function log(string $channel, string $recipient, bool $success): void
            {
                // test-də log yazma
            }
        };
    }
}

// ===== Client — factory-ni bilir, konkret class-ları bilmir =====
class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationFactoryInterface $factory
    ) {}

    public function dispatch(string $recipient, string $subject, string $body): bool
    {
        $template = $this->factory->createTemplate();
        $sender   = $this->factory->createSender();
        $logger   = $this->factory->createLogger();

        $content = $template->format($subject, $body);
        $success = $sender->send($recipient, $content);

        $logger->log(get_class($this->factory), $recipient, $success);

        return $success;
    }
}

// ===== ServiceProvider-də bind et =====
class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationFactoryInterface::class, function () {
            return match (config('notifications.channel')) {
                'sms'  => new SmsNotificationFactory(),
                'fake' => new FakeNotificationFactory(),
                default => new EmailNotificationFactory(),
            };
        });
    }
}

// ===== Controller-də istifadə =====
class UserController extends Controller
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $this->dispatcher->dispatch(
            recipient: $user->email,
            subject: 'Xoş gəldiniz',
            body: "Salam {$user->name}, qeydiyyatınız tamamlandı."
        );

        return response()->json(['id' => $user->id], 201);
    }
}
```

## Praktik Tapşırıqlar
1. `PaymentFactory` abstract factory yaz: `createGateway()` + `createReceiptGenerator()` + `createFraudChecker()` — `StripePaymentFactory` və `PayPalPaymentFactory` concrete factory-lər; test üçün `FakePaymentFactory`
2. Multi-tenant layihədə tenant config-ə görə fərqli factory seç: `TenantA` → `PremiumNotificationFactory`, `TenantB` → `BasicNotificationFactory` — ServiceProvider-də `tenant()` helper-ə görə bind et
3. Mövcud `EmailNotificationFactory`-yə `createTracker(): TrackerInterface` metodu əlavə et — bütün Concrete Factory-lərin dəyişməli olduğunu gözlə; bu trade-off-u kod reviewdə necə izah edərdin?

## Əlaqəli Mövzular
- [03-factory-method.md](03-factory-method.md) — Abstract Factory, bir neçə Factory Method-un ailəsidir
- [05-builder.md](05-builder.md) — Builder mürəkkəb tək obyekt yaradır, Abstract Factory bir-biri ilə uyğun obyektlər ailəsi
- [10-strategy.md](10-strategy.md) — Concrete Factory-lər runtime-da Strategy kimi dəyişdirilə bilər
