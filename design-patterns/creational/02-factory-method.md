# Factory Method (Junior ⭐)

## İcmal
Factory Method pattern bir superclass-da object yaratmaq üçün interface müəyyən edir, lakin hansı konkret class-ın yaradılacağını alt class-ların (subclasses) qərar verməsinə imkan verir. Beləliklə yaradılma (creation) məntiqi istifadə məntiqindən ayrılır.

## Niyə Vacibdir
Real layihələrdə tez-tez müxtəlif şərtlərə görə fərqli object növləri yaratmaq lazım olur: email bildirişi, SMS bildirişi, push notification. Bu şərtləri hər yerdə `if/else` ilə idarə etmək əvəzinə, Factory Method bu qərarı mərkəzləşdirir. Laravel-in özündə Model Factories (`User::factory()`) test data yaratmaq üçün bu pattern-i istifadə edir.

## Əsas Anlayışlar
- **Creator (abstract)**: Factory method elan edir — `abstract protected function createNotification(): NotificationInterface`
- **Concrete Creator**: `createNotification()` metodunu override edərək konkret class qaytarır
- **Product interface**: Yaradılan bütün obyektlər bu interface-i implement edir
- **Simple Factory vs Factory Method**: Simple Factory sadəcə bir static metod ilə obyekt seçir (pattern sayılmır, sadəcə helper-dir); Factory Method isə inheritance ilə override edilə bilən metod təqdim edir
- **Laravel Model Factory**: `User::factory()->create()` — test üçün fake data yaradan `Factory` class-ı; GoF Factory Method-dan fərqli məqsəd daşıyır amma eyni ideologiya

## Praktik Baxış
- **Real istifadə**: Notification channel-ları (Email/SMS/Push), Payment gateway-lər (Stripe/PayPal), Report export-ları (PDF/Excel/CSV), Log handler-lar
- **Trade-off-lar**: Hər yeni Product növü üçün yeni Creator subclass lazım olur — class sayı artır; amma yeni növ əlavə etmək mövcud kodu dəyişdirmir (Open/Closed Principle)
- **İstifadə etməmək**: Yaradılan object növü heç vaxt dəyişməyəcəksə; sadə `new ClassName()` kifayət edəndə; bir-iki sadə variation üçün (if/else daha aydın ola bilər)
- **Common mistakes**: Hər kiçik variation üçün factory yaratmaq (over-engineering); Factory-ni Strategy ilə qarışdırmaq — Factory yaradılma, Strategy davranış üçündür

### Anti-Pattern Nə Zaman Olur?

**1. Simple Factory-ni Factory Method kimi satmaq:**
```php
// Bu Factory Method DEYİL — sadəcə static helper-dir
class NotificationFactory
{
    public static function create(string $type): NotificationInterface
    {
        return match ($type) {
            'email' => new EmailNotification(),
            'sms'   => new SmsNotification(),
            default => throw new \InvalidArgumentException("Unknown type: {$type}"),
        };
    }
}
// Problem: yeni növ əlavə etmək bu class-ı dəyişdirir (OCP pozuntusu)
// Həll: ya Factory Method (subclass override), ya da Strategy+Registry istifadə et
```

**2. Factory-nin özü iş məntiqi icra edir:**
```php
// Pis: factory sadəcə yaratmalıdır, göndərməməlidir
class EmailNotificationCreator extends NotificationSender
{
    protected function createNotification(): NotificationInterface
    {
        $notification = new EmailNotification();
        $notification->send('admin@site.com', 'Log mesajı'); // YOX — factory yan effektlər etməməlidir
        return $notification;
    }
}
```

**3. Hər kiçik dəyişiklik üçün subclass:**
```php
// 10 müxtəlif email tipiniz varsa, 10 ayrı Creator class yazmaq over-engineering-dir
// Strategy pattern + konfiqurasiya daha uyğundur
class WelcomeEmailCreator extends NotificationSender { ... }
class PasswordResetEmailCreator extends NotificationSender { ... }
class InvoiceEmailCreator extends NotificationSender { ... }
// ... davam edir — bu artıq Factory Method-u yanlış istifadədir
```

**4. Yaradılma məntiqi Creator-da, Configuration isə Product-da:** Factory Method, yaradılma qərarını subclass-a buraxır — əgər qərar runtime parametrindən asılıdırsa (string, enum), Registry+Strategy daha uyğundur.

## Nümunələr

### Ümumi Nümunə
Bildiriş sistemi düşün: `EmailNotification`, `SmsNotification`, `PushNotification` — hər birinin fərqli göndərmə məntiqi var. `NotificationSender` abstract class-ı `createNotification()` factory metodunu elan edir. `EmailNotificationSender`, `SmsNotificationSender` bu metodu override edərək lazımi növü qaytarır. `send()` metodu isə factory metodunu çağırır — hansı konkret növ yaradıldığını bilməyə ehtiyac yoxdur.

### PHP/Laravel Nümunəsi

```php
// ===== Product interface =====
interface NotificationInterface
{
    public function send(string $recipient, string $message): bool;
    public function getChannel(): string;
}

// ===== Concrete Products =====
class EmailNotification implements NotificationInterface
{
    public function send(string $recipient, string $message): bool
    {
        Mail::to($recipient)->send(new GenericMailable($message));
        return true;
    }

    public function getChannel(): string
    {
        return 'email';
    }
}

class SmsNotification implements NotificationInterface
{
    public function send(string $recipient, string $message): bool
    {
        // Twilio/Vonage API
        app(SmsService::class)->send($recipient, $message);
        return true;
    }

    public function getChannel(): string
    {
        return 'sms';
    }
}

class PushNotification implements NotificationInterface
{
    public function send(string $recipient, string $message): bool
    {
        // Firebase Cloud Messaging
        app(FcmService::class)->push($recipient, $message);
        return true;
    }

    public function getChannel(): string
    {
        return 'push';
    }
}

// ===== Abstract Creator =====
abstract class NotificationSender
{
    // Factory Method — subclass override edir
    abstract protected function createNotification(): NotificationInterface;

    // Template method — factory metodunu istifadə edir
    public function notify(string $recipient, string $message): bool
    {
        $notification = $this->createNotification();

        Log::info("Sending {$notification->getChannel()} notification to {$recipient}");

        $result = $notification->send($recipient, $message);

        if ($result) {
            Log::info("Notification sent successfully via {$notification->getChannel()}");
        }

        return $result;
    }
}

// ===== Concrete Creators =====
class EmailNotificationSender extends NotificationSender
{
    protected function createNotification(): NotificationInterface
    {
        return new EmailNotification();
    }
}

class SmsNotificationSender extends NotificationSender
{
    protected function createNotification(): NotificationInterface
    {
        return new SmsNotification();
    }
}

class PushNotificationSender extends NotificationSender
{
    protected function createNotification(): NotificationInterface
    {
        return new PushNotification();
    }
}

// ===== İstifadə =====
class UserController extends Controller
{
    public function sendWelcome(User $user): JsonResponse
    {
        $channel = $user->preferred_channel; // 'email', 'sms', 'push'

        $sender = match ($channel) {
            'email' => new EmailNotificationSender(),
            'sms'   => new SmsNotificationSender(),
            'push'  => new PushNotificationSender(),
            default => new EmailNotificationSender(),
        };

        $sender->notify($user->contact, 'Xoş gəldiniz!');

        return response()->json(['status' => 'sent']);
    }
}


// ===== Laravel Model Factory (test context) =====
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'     => $this->faker->name(),
            'email'    => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }

    // State — Factory Method ideyası: eyni factory, fərqli state
    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function suspended(): static
    {
        return $this->state(['suspended_at' => now()]);
    }
}

// Test-də
$user  = User::factory()->create();
$admin = User::factory()->admin()->create();
$users = User::factory()->count(10)->suspended()->create();
```

## Praktik Tapşırıqlar
1. `ReportExporter` abstract class yaz: `createFormatter()` factory metodu `FormatterInterface` qaytarır; `PdfExporter`, `ExcelExporter`, `CsvExporter` concrete creator-lar yaz; `export(array $data)` template metodu factory metodunu çağırır
2. Laravel Model Factory yaz: `Product` modeli üçün `ProductFactory` — `definition()`, `outOfStock()` state, `featured()` state; test-də `Product::factory()->outOfStock()->count(5)->create()` ilə istifadə et
3. Mövcud bir layihədə (özünün və ya açıq mənbəli) `if/else` ilə object yaradılan kod tap — Factory Method ilə refactor et

## Əlaqəli Mövzular
- [Abstract Factory](03-abstract-factory.md) — Bir neçə əlaqəli factory method-un birləşməsi
- [Builder](04-builder.md) — Mürəkkəb object-i addım-addım yaratmaq üçün
- [Strategy](../behavioral/02-strategy.md) — Factory Method yaradılma, Strategy davranış üçündür
- [Service Layer](../laravel/02-service-layer.md) — Factory Method ilə yaranan obyektlər Service Layer-dən çağırılır
