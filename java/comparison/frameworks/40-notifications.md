# Bildiriş Sistemi (Notifications): Spring vs Laravel

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Bildirişlər (notifications) müasir tətbiqlərin əsas hissəsidir - istifadəçilərə e-poçt, SMS, Slack mesajı, verilənlər bazası bildirişi və digər kanallar vasitəsilə məlumat çatdırmaq lazım olur. Bu mövzuda **ən böyük fərq** budur: **Laravel-də tam daxili Notification sistemi mövcuddur**, **Spring-də isə belə bir sistem yoxdur**. Spring-də hər bildiriş kanalını ayrı-ayrılıqda, əl ilə inteqrasiya etmək lazımdır.

## Laravel-də istifadəsi

### Notification sistemi

Laravel-in Notification sistemi bildirişləri bir yerdən müxtəlif kanallara göndərməyə imkan verir. Bir notification sinfi yazırsınız, hansı kanallara göndəriləcəyini təyin edirsiniz, və Laravel qalanını idarə edir.

```bash
php artisan make:notification OrderShipped
```

```php
// app/Notifications/OrderShipped.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    // Hansı kanallar vasitəsilə göndəriləcək
    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        // İstifadəçinin Slack-ı varsa, ora da göndər
        if ($notifiable->slack_webhook_url) {
            $channels[] = 'slack';
        }

        // İstifadəçinin SMS seçimi aktivdirsə
        if ($notifiable->notify_sms) {
            $channels[] = 'vonage'; // SMS
        }

        return $channels;
    }

    // E-poçt bildirişi
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sifarişiniz göndərildi!')
            ->greeting('Salam, ' . $notifiable->name . '!')
            ->line('Sifarişiniz #' . $this->order->id . ' göndərildi.')
            ->line('Göndərmə tarixi: ' . $this->order->shipped_at->format('d.m.Y'))
            ->action('Sifarişi izlə', url('/orders/' . $this->order->id))
            ->line('Alış-veriş etdiyiniz üçün təşəkkür edirik!')
            ->salutation('Hörmətlə, Mağaza komandası');
    }

    // Verilənlər bazası bildirişi
    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'message' => 'Sifarişiniz #' . $this->order->id . ' göndərildi.',
            'amount' => $this->order->total,
            'tracking_number' => $this->order->tracking_number,
            'url' => '/orders/' . $this->order->id,
        ];
    }

    // Slack bildirişi
    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->success()
            ->content('Yeni sifariş göndərildi!')
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Sifariş #' . $this->order->id,
                        url('/admin/orders/' . $this->order->id))
                    ->fields([
                        'Müştəri' => $this->order->user->name,
                        'Məbləğ' => number_format($this->order->total, 2) . ' AZN',
                        'Məhsul sayı' => $this->order->items->count(),
                        'İzləmə nömrəsi' => $this->order->tracking_number,
                    ]);
            });
    }

    // SMS bildirişi (Vonage/Nexmo)
    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content('Sifarişiniz #' . $this->order->id .
                      ' göndərildi. İzləmə: ' . $this->order->tracking_number);
    }
}
```

### Notifiable Trait

```php
// app/Models/User.php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    // Slack webhook URL (əgər Slack istifadə edilirsə)
    public function routeNotificationForSlack(): string
    {
        return $this->slack_webhook_url;
    }

    // SMS üçün telefon nömrəsi
    public function routeNotificationForVonage(): string
    {
        return $this->phone_number;
    }

    // Custom kanal üçün
    public function routeNotificationForTelegram(): string
    {
        return $this->telegram_chat_id;
    }
}
```

### Bildiriş göndərmək

```php
class OrderController extends Controller
{
    public function ship(Order $order)
    {
        $order->update([
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);

        // Üsul 1: Notifiable model üzərindən
        $order->user->notify(new OrderShipped($order));

        // Üsul 2: Notification facade ilə (bir neçə istifadəçiyə)
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new OrderShipped($order));

        // Üsul 3: Gecikmə ilə göndərmək
        $order->user->notify(
            (new OrderShipped($order))->delay(now()->addMinutes(5))
        );

        // Üsul 4: Kanal üzrə gecikmə
        $order->user->notify(
            (new OrderShipped($order))->delay([
                'mail' => now()->addMinutes(5),
                'sms' => now()->addMinutes(10),
            ])
        );

        return redirect()->route('orders.show', $order)
            ->with('success', 'Sifariş göndərildi və müştəri məlumatlandırıldı.');
    }
}
```

### Database Notifications (Verilənlər bazası bildirişləri)

```bash
php artisan notifications:table
php artisan migrate
```

Yaradılan cədvəl:

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type');
    $table->morphs('notifiable'); // notifiable_type, notifiable_id
    $table->text('data');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});
```

İstifadəçinin bildirişlərini oxumaq:

```php
class NotificationController extends Controller
{
    // Bütün bildirişlər
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    // Oxunmamış bildirişlər
    public function unread(Request $request)
    {
        $unread = $request->user()->unreadNotifications;

        return response()->json([
            'count' => $unread->count(),
            'notifications' => $unread->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => class_basename($notification->type),
                    'data' => $notification->data,
                    'created_at' => $notification->created_at->diffForHumans(),
                ];
            }),
        ]);
    }

    // Bildirişi oxunmuş kimi işarələmək
    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        // data-dakı URL-ə yönləndir
        return redirect($notification->data['url'] ?? '/');
    }

    // Hamısını oxunmuş kimi işarələmək
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back()
            ->with('success', 'Bütün bildirişlər oxunmuş kimi işarələndi.');
    }
}
```

```php
{{-- Blade şablonunda --}}
<div class="notifications-bell">
    @if(auth()->user()->unreadNotifications->count() > 0)
        <span class="badge">{{ auth()->user()->unreadNotifications->count() }}</span>
    @endif

    <div class="dropdown">
        @foreach(auth()->user()->unreadNotifications->take(5) as $notification)
            <a href="{{ route('notifications.read', $notification->id) }}">
                <p>{{ $notification->data['message'] }}</p>
                <small>{{ $notification->created_at->diffForHumans() }}</small>
            </a>
        @endforeach
    </div>
</div>
```

### On-Demand Notifications (Qeydiyyatsız istifadəçilərə)

```php
use Illuminate\Notifications\AnonymousNotifiable;

// Qeydiyyatdan keçməmiş istifadəçiyə bildiriş göndərmək
Notification::route('mail', 'orxan@example.com')
    ->route('slack', 'https://hooks.slack.com/services/xxx')
    ->route('vonage', '+994501234567')
    ->notify(new OrderShipped($order));

// ContactForm callback - ziyarətçiyə cavab
class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'message' => 'required',
        ]);

        // Ziyarətçiyə təşəkkür mesajı
        Notification::route('mail', $validated['email'])
            ->notify(new ContactReceived($validated));

        // Admin-ə bildiriş
        $admin = User::where('role', 'admin')->first();
        $admin->notify(new NewContactMessage($validated));

        return redirect()->back()->with('success', 'Mesajınız göndərildi!');
    }
}
```

### Queued Notifications (Növbəyə qoyulmuş bildirişlər)

```php
// Notification sinfinə ShouldQueue əlavə etmək kifayətdir
class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;

        // Hansı queue-da işləsin
        $this->onQueue('notifications');

        // Hansı connection-da
        $this->onConnection('redis');

        // Neçə dəfə cəhd etsin
        $this->tries = 3;

        // Maksimum vaxt
        $this->timeout = 30;
    }

    // Göndərilməli olub-olmadığını yoxlamaq
    public function shouldSend(object $notifiable, string $channel): bool
    {
        // Sifariş ləğv edilibsə göndərmə
        if ($this->order->status === 'cancelled') {
            return false;
        }

        // İstifadəçi bu tip bildirişləri söndürübsə
        if (!$notifiable->wantsNotification('order_shipped', $channel)) {
            return false;
        }

        return true;
    }

    // via, toMail, toDatabase, toSlack metodları...
}
```

### Müxtəlif notification növləri

```php
// Sadə bildiriş - yalnız verilənlər bazası
class NewFollower extends Notification
{
    public function __construct(public User $follower) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'message' => $this->follower->name . ' sizi izləməyə başladı.',
            'follower_id' => $this->follower->id,
            'follower_avatar' => $this->follower->avatar_url,
            'url' => '/users/' . $this->follower->id,
        ];
    }
}

// Markdown e-poçt bildirişi
class InvoicePaid extends Notification
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Faktura ödənildi')
            ->markdown('emails.invoice.paid', [
                'invoice' => $this->invoice,
                'user' => $notifiable,
                'url' => url('/invoices/' . $this->invoice->id),
            ]);
    }
}

// resources/views/emails/invoice/paid.blade.php (Markdown)
// @component('mail::message')
// # Faktura Ödənildi
//
// Salam {{ $user->name }},
//
// Fakturanız **#{{ $invoice->id }}** uğurla ödənildi.
//
// | Məhsul | Qiymət |
// |:-------|-------:|
// @foreach($invoice->items as $item)
// | {{ $item->name }} | {{ $item->price }} AZN |
// @endforeach
// | **Cəmi** | **{{ $invoice->total }} AZN** |
//
// @component('mail::button', ['url' => $url])
// Fakturaya bax
// @endcomponent
//
// Təşəkkürlər,<br>
// {{ config('app.name') }}
// @endcomponent
```

## Spring-də istifadəsi

Spring-də vahid notification sistemi yoxdur. Hər kanalı ayrı-ayrılıqda inteqrasiya etmək lazımdır:

### E-poçt göndərmə (JavaMailSender)

```java
// pom.xml
// spring-boot-starter-mail dependency lazımdır

// application.properties
// spring.mail.host=smtp.gmail.com
// spring.mail.port=587
// spring.mail.username=app@example.com
// spring.mail.password=secret
// spring.mail.properties.mail.smtp.auth=true
// spring.mail.properties.mail.smtp.starttls.enable=true
```

```java
@Service
public class EmailNotificationService {

    @Autowired
    private JavaMailSender mailSender;

    @Autowired
    private TemplateEngine templateEngine; // Thymeleaf

    // Sadə e-poçt
    public void sendSimpleEmail(String to, String subject, String body) {
        SimpleMailMessage message = new SimpleMailMessage();
        message.setTo(to);
        message.setSubject(subject);
        message.setText(body);
        message.setFrom("app@example.com");
        mailSender.send(message);
    }

    // HTML e-poçt (Thymeleaf şablonu ilə)
    public void sendOrderShippedEmail(User user, Order order) {
        Context context = new Context();
        context.setVariable("user", user);
        context.setVariable("order", order);
        context.setVariable("trackingUrl",
            "https://example.com/orders/" + order.getId());

        String htmlContent = templateEngine.process(
            "emails/order-shipped", context);

        MimeMessage message = mailSender.createMimeMessage();
        try {
            MimeMessageHelper helper = new MimeMessageHelper(message, true, "UTF-8");
            helper.setTo(user.getEmail());
            helper.setSubject("Sifarişiniz #" + order.getId() + " göndərildi");
            helper.setText(htmlContent, true); // true = HTML
            helper.setFrom("app@example.com");

            mailSender.send(message);
        } catch (MessagingException e) {
            throw new RuntimeException("E-poçt göndərilə bilmədi", e);
        }
    }
}
```

### Slack bildirişi (Webhook)

```java
@Service
public class SlackNotificationService {

    @Value("${slack.webhook.url}")
    private String webhookUrl;

    private final RestTemplate restTemplate = new RestTemplate();

    public void sendOrderNotification(Order order) {
        Map<String, Object> payload = Map.of(
            "text", "Yeni sifariş göndərildi!",
            "attachments", List.of(
                Map.of(
                    "color", "#36a64f",
                    "title", "Sifariş #" + order.getId(),
                    "title_link", "https://example.com/admin/orders/" + order.getId(),
                    "fields", List.of(
                        Map.of("title", "Müştəri",
                               "value", order.getUser().getName(),
                               "short", true),
                        Map.of("title", "Məbləğ",
                               "value", order.getTotal() + " AZN",
                               "short", true),
                        Map.of("title", "İzləmə",
                               "value", order.getTrackingNumber(),
                               "short", false)
                    )
                )
            )
        );

        restTemplate.postForEntity(webhookUrl, payload, String.class);
    }
}
```

### Database bildirişi (manual)

```java
// Entity
@Entity
@Table(name = "notifications")
public class DatabaseNotification {
    @Id
    @GeneratedValue(strategy = GenerationType.UUID)
    private String id;

    @Column(nullable = false)
    private String type;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "user_id")
    private User user;

    @Column(columnDefinition = "TEXT")
    private String data; // JSON

    @Column(name = "read_at")
    private LocalDateTime readAt;

    @Column(name = "created_at")
    private LocalDateTime createdAt = LocalDateTime.now();

    // getters, setters...
}

// Repository
@Repository
public interface NotificationRepository extends JpaRepository<DatabaseNotification, String> {

    List<DatabaseNotification> findByUserAndReadAtIsNullOrderByCreatedAtDesc(User user);

    long countByUserAndReadAtIsNull(User user);

    @Modifying
    @Query("UPDATE DatabaseNotification n SET n.readAt = :now WHERE n.user = :user AND n.readAt IS NULL")
    void markAllAsRead(@Param("user") User user, @Param("now") LocalDateTime now);
}

// Service
@Service
public class DatabaseNotificationService {

    @Autowired
    private NotificationRepository repository;

    @Autowired
    private ObjectMapper objectMapper;

    public void createNotification(User user, String type, Map<String, Object> data) {
        DatabaseNotification notification = new DatabaseNotification();
        notification.setType(type);
        notification.setUser(user);
        try {
            notification.setData(objectMapper.writeValueAsString(data));
        } catch (JsonProcessingException e) {
            throw new RuntimeException(e);
        }
        repository.save(notification);
    }

    public List<DatabaseNotification> getUnread(User user) {
        return repository.findByUserAndReadAtIsNullOrderByCreatedAtDesc(user);
    }

    public void markAsRead(String id) {
        DatabaseNotification notification = repository.findById(id).orElseThrow();
        notification.setReadAt(LocalDateTime.now());
        repository.save(notification);
    }

    @Transactional
    public void markAllAsRead(User user) {
        repository.markAllAsRead(user, LocalDateTime.now());
    }
}
```

### Hamısını birləşdirən Notification Service

Spring-də Laravel-in notification sisteminə bənzər bir şey yaratmaq üçün əl ilə fasad yazmaq lazımdır:

```java
@Service
public class NotificationService {

    @Autowired
    private EmailNotificationService emailService;

    @Autowired
    private SlackNotificationService slackService;

    @Autowired
    private DatabaseNotificationService databaseService;

    @Autowired
    private SmsService smsService;

    // Sifariş göndərildi bildirişi - bütün kanallara
    @Async // asinxron icra
    public void notifyOrderShipped(User user, Order order) {
        // E-poçt
        try {
            emailService.sendOrderShippedEmail(user, order);
        } catch (Exception e) {
            log.error("E-poçt göndərilə bilmədi: {}", e.getMessage());
        }

        // Verilənlər bazası
        try {
            databaseService.createNotification(user, "OrderShipped", Map.of(
                "order_id", order.getId(),
                "message", "Sifarişiniz #" + order.getId() + " göndərildi.",
                "tracking_number", order.getTrackingNumber(),
                "url", "/orders/" + order.getId()
            ));
        } catch (Exception e) {
            log.error("DB bildirişi yaradıla bilmədi: {}", e.getMessage());
        }

        // Slack (admin kanalına)
        try {
            slackService.sendOrderNotification(order);
        } catch (Exception e) {
            log.error("Slack bildirişi göndərilə bilmədi: {}", e.getMessage());
        }

        // SMS (əgər istifadəçi aktivləşdiribsə)
        if (user.isSmsNotificationsEnabled()) {
            try {
                smsService.sendSms(user.getPhone(),
                    "Sifarişiniz #" + order.getId() + " göndərildi.");
            } catch (Exception e) {
                log.error("SMS göndərilə bilmədi: {}", e.getMessage());
            }
        }
    }

    // Yeni izləyici bildirişi - yalnız DB
    @Async
    public void notifyNewFollower(User user, User follower) {
        databaseService.createNotification(user, "NewFollower", Map.of(
            "message", follower.getName() + " sizi izləməyə başladı.",
            "follower_id", follower.getId(),
            "url", "/users/" + follower.getId()
        ));
    }
}
```

### Spring-də SMS göndərmə (Twilio nümunəsi)

```java
// pom.xml: com.twilio.sdk:twilio dependency

@Service
public class SmsService {

    @Value("${twilio.account.sid}")
    private String accountSid;

    @Value("${twilio.auth.token}")
    private String authToken;

    @Value("${twilio.phone.number}")
    private String fromNumber;

    @PostConstruct
    public void init() {
        Twilio.init(accountSid, authToken);
    }

    public void sendSms(String to, String body) {
        Message message = Message.creator(
            new PhoneNumber(to),
            new PhoneNumber(fromNumber),
            body
        ).create();

        log.info("SMS göndərildi, SID: {}", message.getSid());
    }
}
```

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Daxili notification sistemi | **Yoxdur** | **Tam daxili** |
| Vahid Notification sinfi | Yoxdur - hər kanal ayrı service | Bir sinif, bütün kanallar |
| `via()` metodu | Yoxdur | Hansı kanallara göndərəcəyini təyin edir |
| `toMail()` | Yoxdur - JavaMailSender əl ilə | Daxili fluent API |
| `toDatabase()` | Yoxdur - manual entity/repository | Array qaytarmaq kifayətdir |
| `toSlack()` | Yoxdur - REST webhook əl ilə | Daxili SlackMessage |
| Notifiable trait | Yoxdur | `$user->notify()` bir metod çağırışı |
| On-demand notifications | Yoxdur | `Notification::route()` |
| Queued notifications | `@Async` (sadə) / custom queue | `ShouldQueue` interface |
| `shouldSend()` | Yoxdur | Göndərmə öncəsi şərt yoxlaması |
| Database notifications | Manual entity + repository | Migration + hazır API |
| `unreadNotifications` | Manual query | `$user->unreadNotifications` |
| `markAsRead()` | Manual implementasiya | Daxili metod |
| Markdown e-poçt | Yoxdur (Thymeleaf əl ilə) | Daxili Markdown şablonlar |
| Kod miqdarı (bir bildiriş üçün) | ~100-200 sətir | ~30-50 sətir |

## Niyə belə fərqlər var?

### Laravel: Notification birinci sinif vətəndaşdır

Laravel-in yaradıcısı Taylor Otwell hesab edir ki, notification göndərmək hər tətbiqin əsas ehtiyacıdır. E-poçt təsdiqləmə, sifariş yeniləmələri, admin xəbərdarlıqları - bunlar praktiki olaraq hər layihədə lazımdır. Buna görə Laravel vahid, tutarlı bir API təqdim edir: bir `Notification` sinfi yazırsınız, `via()` ilə kanalları seçirsiniz, hər kanal üçün `toMail()`, `toDatabase()`, `toSlack()` kimi metodlar təyin edirsiniz. Bu, Single Responsibility prinsipini qoruyur - hər notification sinfi bir hadisə ilə bağlıdır, lakin müxtəlif kanallara çatdırıla bilir.

### Spring: Notification framework-ün vəzifəsi deyil

Spring ekosistemində notification vahid abstraksiya kimi görülmür. Spring-in yanaşması belədir: e-poçt üçün `JavaMailSender` var, qalanı üçüncü tərəf API-lərdir. Spring hesab edir ki:

1. **Hər layihənin ehtiyacları fərqlidir** - bəzisinə yalnız e-poçt lazımdır, bəzisinə Firebase Push, bəzisinə Apache Kafka events
2. **Notification çox vaxt microservice-dir** - böyük layihələrdə ayrı notification service olur
3. **Spring Cloud Stream / Spring Integration** - event-driven arxitektura ilə notification-lar idarə oluna bilər

Bu fərq Java və PHP ekosistemləri arasındakı fəlsəfi fərqi əks etdirir. Java dünyasında enterprise pattern-lər, microservice-lər və event-driven arxitektura hakim olduğundan, notification kimi xüsusiyyətlər ayrı service-lər kimi dizayn edilir. PHP/Laravel dünyasında isə monolitik tətbiq üçün hər şeyin bir framework-dən əldə edilə bilməsi hədəflənir.

### Queuing fərqi

Laravel-də `implements ShouldQueue` əlavə etmək bildirişi queue-ya göndərmək üçün kifayətdir. Framework avtomatik olaraq notification-u serialize edir, queue-ya qoyur, worker tərəfindən emal edilir və uğursuz olduqda retry mexanizmi işə düşür.

Spring-də `@Async` annotasiyası sadə asinxron icra təmin edir, lakin bu əsl queue deyil - uğursuz olduqda retry yoxdur, server yenidən başladıqda itir. Əsl queue üçün Spring AMQP (RabbitMQ) və ya Spring Kafka istifadə etmək lazımdır ki, bu da əhəmiyyətli əlavə kod tələb edir.

## Hansı framework-də var, hansında yoxdur?

### Yalnız Laravel-də olan xüsusiyyətlər:
- **Vahid Notification sinfi** - bir sinif, çoxlu kanallar
- **`Notifiable` trait** - `$user->notify()` ilə bir sətirdə bildiriş
- **`via()` metodu** - kanalları dinamik seçmək
- **`toMail()`** - fluent e-poçt builder
- **`toDatabase()`** - array qaytarmaqla DB bildirişi
- **`toSlack()`** - daxili Slack inteqrasiyası
- **Database notifications cədvəli** - hazır migration və API
- **`$user->unreadNotifications`** - oxunmamış bildirişlərə birbaşa müraciət
- **`markAsRead()` / `markAllAsRead()`** - hazır metodlar
- **On-demand notifications** - `Notification::route()` ilə qeydiyyatsız istifadəçilərə
- **`shouldSend()`** - göndərmə öncəsi şərt yoxlaması
- **`ShouldQueue`** - bir söz ilə queue-ya göndərmək
- **Markdown mail şablonları** - cədvəl, düymə, panel dəstəyi
- **Notification events** - `NotificationSending`, `NotificationSent`
- **`$notification->delay()`** - kanal üzrə gecikmə

### Yalnız Spring-də olan xüsusiyyətlər:
- **JavaMailSender** - Java standart mail API (daha aşağı səviyyəli nəzarət)
- **`MimeMessageHelper`** - attachment, inline image dəstəyi (daha ətraflı)
- **Spring Integration** - mürəkkəb mesajlaşma workflow-ları (notification-dan daha geniş)
- **`@Async` + custom executor** - thread pool konfiqurasiyası ilə asinxron icra
- **Thymeleaf e-poçt şablonları** - HTML e-poçtlar üçün tam template engine gücü
- **Spring Cloud Stream** - Kafka/RabbitMQ ilə event-driven notification arxitekturası

### Əsas nəticə

Bu mövzu Spring və Laravel arasındakı ən böyük fərqlərdən birini göstərir. Laravel "bu xüsusiyyət hər tətbiqdə lazımdır, gəlin ən yaxşı həlli framework-ə daxil edək" deyir. Spring isə "hər tətbiqin ehtiyacları fərqlidir, biz alətlər təqdim edək, developer öz həllini qursun" deyir. Nəticədə, Laravel-də 30 sətir kodla edilən iş Spring-də 150+ sətir kod tələb edir, lakin Spring-in yanaşması daha çevik və enterprise mühitlərə daha uyğundur.
