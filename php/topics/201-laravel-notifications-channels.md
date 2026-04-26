# Laravel Notifications və Kanallar (Middle)

## İcmal
Laravel Notification sistemi, tətbiqdən müxtəlif kanallar (mail, SMS, Slack, database, broadcast) vasitəsilə bildiriş göndərməyə imkan verir. Bir Notification class yazıb onu istənilən kanala yönləndirmək mümkündür — bu, çox kanallı bildiriş sistemlərini sadə edir.

## Niyə Vacibdir
Hər real layihədə email, push notification, SMS, Slack bildirişi mövcuddur. Notification sistemi olmadan hər kanal üçün ayrı kod yazmaq lazım gəlir. Laravel-in built-in sistemi isə bu məntiqi birləşdirir, queue ilə işləyir, test etməyə imkan verir.

## Əsas Anlayışlar

### Notification Anatomy
```php
php artisan make:notification InvoicePaid

class InvoicePaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Invoice $invoice) {}

    // Hansı kanallardan göndərilsin
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    // Mail üçün
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invoice #' . $this->invoice->id . ' Ödənildi')
            ->greeting('Salam ' . $notifiable->name . '!')
            ->line('Invoice uğurla ödənildi.')
            ->action('Invoice-ə Bax', url('/invoices/' . $this->invoice->id))
            ->line('Təşəkkür edirik!');
    }

    // Database kanalı üçün
    public function toDatabase(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'amount'     => $this->invoice->amount,
        ];
    }
}
```

### Göndərmə üsulları
```php
// 1. Notifiable modelə birbaşa
$user->notify(new InvoicePaid($invoice));

// 2. Facade — çoxlu alıcı
Notification::send(User::all(), new WeeklyReport());

// 3. Queue-suz (ShouldQueue olsa belə)
$user->notifyNow(new InvoicePaid($invoice));

// 4. On-demand — Notifiable modeli olmayan alıcı
Notification::route('mail', 'admin@example.com')
    ->route('vonage', '0505001122')
    ->notify(new InvoicePaid($invoice));
```

### Notifiable Trait
User modelinə `HasNotifications` (Notifiable) trait əlavə olunmalıdır:
```php
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    
    // Mail kanalı default olaraq $notifiable->email istifadə edir
    // Override etmək üçün:
    public function routeNotificationForMail(): string
    {
        return $this->work_email ?? $this->email;
    }
    
    // SMS üçün
    public function routeNotificationForVonage(): string
    {
        return $this->phone_number;
    }
}
```

## Kanallar

### Mail Kanalı
```php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Başlıq')
        ->from('noreply@app.com', 'MyApp')
        ->cc('manager@app.com')
        ->bcc('audit@app.com')
        ->replyTo('support@app.com')
        ->greeting('Salam!')
        ->line('Birinci sətir.')
        ->action('Düymə Mətni', 'https://example.com')
        ->line('Son sətir.')
        ->salutation('Hörmətlə, Komanda')
        ->attach(storage_path('invoices/' . $this->invoice->id . '.pdf'))
        ->attachFromStorage('invoices/1.pdf', 'faktura.pdf');
}

// Markdown template istifadəsi
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->markdown('mail.invoices.paid', ['invoice' => $this->invoice]);
}
```

### Database Kanalı
```php
// Migration: notifications table
php artisan notifications:table
php artisan migrate

public function toDatabase(object $notifiable): array
{
    return [
        'type'    => 'invoice_paid',
        'data'    => ['invoice_id' => $this->invoice->id],
    ];
}

// İstifadə
$user->unreadNotifications;          // oxunmamış
$user->readNotifications;            // oxunmuş
$user->notifications->first()->markAsRead();
$user->unreadNotifications->markAsRead();
```

### Broadcast Kanalı — Real-time
```php
use Illuminate\Notifications\Messages\BroadcastMessage;

public function toBroadcast(object $notifiable): BroadcastMessage
{
    return new BroadcastMessage([
        'invoice_id' => $this->invoice->id,
        'message'    => 'Invoice ödənildi',
    ]);
}

// Frontend (Pusher/Reverb)
Echo.private('App.Models.User.' + userId)
    .notification((notification) => {
        console.log(notification);
    });
```

### Slack Kanalı
```php
// composer require laravel/slack-notification-channel

public function via(object $notifiable): array
{
    return ['slack'];
}

public function toSlack(object $notifiable): SlackMessage
{
    return (new SlackMessage)
        ->content('Invoice #' . $this->invoice->id . ' ödənildi!')
        ->attachment(function ($attachment) {
            $attachment->title('Invoice Details')
                       ->fields(['Məbləğ' => $this->invoice->amount]);
        });
}
```

## Praktik Baxış

### Queue ilə işləmək
```php
class InvoicePaid extends Notification implements ShouldQueue
{
    use Queueable;
    
    public $queue = 'notifications';   // xüsusi queue
    public $delay = 60;                // 60 saniyə gecikmə
    public $tries = 3;
    
    // Xəta olduqda
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification failed', [
            'notification' => static::class,
            'error'        => $exception->getMessage(),
        ]);
    }
}

// Müəyyən kanalı delay ilə göndər
$user->notify((new InvoicePaid($invoice))->delay(now()->addMinutes(10)));

// Hər kanal üçün fərqli delay
$user->notify((new InvoicePaid($invoice))->delay([
    'mail'     => now()->addSeconds(5),
    'database' => now()->addSeconds(0),
]));
```

### Locale üzrə bildiriş
```php
// İstifadəçinin locale-si ilə göndər
$user->notify((new InvoicePaid($invoice))->locale('az'));

// Çoxlu istifadəçi
Notification::locale('az')->send($users, new InvoicePaid($invoice));
```

### Notification Events
```php
// NotificationSending → göndərmədən əvvəl (false qaytarılsa ləğv olunur)
// NotificationSent    → göndərildikdən sonra

// EventServiceProvider-da listen
NotificationSending::class => [LogNotificationSendingListener::class],
NotificationSent::class    => [LogNotificationSentListener::class],
```

### Custom Kanal yaratmaq
```php
class PushNotificationChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toPush($notifiable);
        
        // External push service çağır
        app(PushService::class)->send(
            $notifiable->device_token,
            $message
        );
    }
}

// Notification-da istifadə
public function via(object $notifiable): array
{
    return [PushNotificationChannel::class];
}

public function toPush(object $notifiable): array
{
    return ['title' => 'Bildiriş', 'body' => 'Invoice ödənildi'];
}
```

### Trade-off-lar
- **Database channel + queue**: Ən çox istifadə olunan kombinasiya. Database-dəki bildirişlər real-time göstərmək üçün polling lazımdır (ya Reverb/Pusher broadcast ilə birləşdirilir).
- **Broadcast yalnız**: Real-time, amma istifadəçi offline olduqda bildirim itir — database ilə kombinasiya et.
- **On-demand notifications**: Autentifikasiya olmayan alıcılara göndərmək üçün (əlaqə forması, admin bildirişi).

### Common Mistakes
- `ShouldQueue` olmadan yüzlərlə mail göndərmək → request timeout
- `via()` metodunda hər zaman bütün kanalları qaytarmaq → user preference-ə bax
- Database kanalı üçün `toDatabase()` yerinə `toArray()` yazmaq → hər ikisi işləyir amma `toDatabase()` daha explicit

## Nümunələr

### Real Layihə: Order Status Bildirişi
```php
class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Order $order,
        private string $previousStatus
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        
        if ($notifiable->email_notifications) {
            $channels[] = 'mail';
        }
        if ($notifiable->sms_notifications && $this->order->isDelivered()) {
            $channels[] = 'vonage';
        }
        
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sifarişin statusu: ' . $this->order->status->label())
            ->markdown('mail.orders.status-changed', [
                'order'          => $this->order,
                'previousStatus' => $this->previousStatus,
                'user'           => $notifiable,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id'        => $this->order->id,
            'status'          => $this->order->status,
            'previous_status' => $this->previousStatus,
        ];
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content('Sifariş #' . $this->order->id . ' çatdırıldı!');
    }
}
```

## Praktik Tapşırıqlar

1. `UserRegistered` notification yaz — email ilə xoş gəlmisiniz məktubu + database-də bildiriş
2. Custom `PushChannel` yaz ki, FCM-ə (Firebase) http request göndərsin
3. `via()` metodunda user preferences cədvəlindən kanal siyahısını dinamik qur
4. Notification preference middleware yaz: user `email_notifications=false`-sa mail kanalını skip et
5. Frontend-də `Echo.private().notification()` ilə database bildirişlərini real-time göstər

## Əlaqəli Mövzular
- [Queues & Jobs](057-queues.md)
- [Laravel Horizon](058-laravel-horizon-queue.md)
- [Email Delivery Deep Dive](203-email-delivery-deep-dive.md)
- [WebSockets & Reverb](154-websockets-deep-dive.md)
- [Notification System Design](169-notification-system-design.md)
