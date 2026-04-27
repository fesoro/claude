# Notification System (Middle)

## İcmal

Notification system istifadəçilərə müxtəlif kanallar vasitəsilə (email, SMS, push,
in-app, WebSocket) xəbərdarlıq göndərən sistemdir. Yaxşı dizayn edilmiş notification
system yüksək throughput, priority-based delivery, və reliable message delivery təmin edir.

Sadə dillə: poçt sistemi kimi düşünün - fərqli kanallar (email = məktub, SMS = teleqram,
push = qapı zəngi) vasitəsilə mesaj çatdırılır.

```
Event Trigger
      │
      ▼
┌──────────────┐     ┌──────────┐     ┌─────────────┐
│ Notification │────▶│ Priority │────▶│  Channel    │
│   Service    │     │  Queue   │     │  Router     │
└──────────────┘     └──────────┘     └──────┬──────┘
                                             │
                          ┌──────────────────┼──────────────────┐
                          │                  │                  │
                     ┌────┴────┐        ┌────┴────┐       ┌────┴────┐
                     │  Email  │        │  Push   │       │   SMS   │
                     │ Sender  │        │ Sender  │       │ Sender  │
                     └─────────┘        └─────────┘       └─────────┘
```


## Niyə Vacibdir

İstifadəçi engagement üçün real-time bildiriş hər consumer app-in əsas tələbidir. Push, email, SMS kanallarını reliable şəkildə idarə etmək — retry, deduplication, user preference — ayrı bir sistem tələb edir. Laravel Notifications bu pattern-i abstrakt edir, amma scale-da öz infrastruktur lazımdır.

## Əsas Anlayışlar

### Notification Channels

**Email:**
- Uzun content, marketing, receipts
- Async delivery (seconds to minutes)
- High volume, low cost
- Bounce handling, spam prevention

**SMS:**
- Qısa, urgent mesajlar, 2FA
- Phone number lazımdır
- Bahalıdır, character limit var
- Carrier reliability issues

**Push Notifications:**
- Mobile/desktop app notifications
- Firebase Cloud Messaging (FCM), APNs
- Device token lazımdır
- Background delivery mümkündür

**WebSocket / In-App:**
- Real-time, istifadəçi online olarkən
- Ən sürətli delivery
- Connection lazımdır
- Missed notifications üçün fallback

**Webhook:**
- System-to-system notifications
- HTTP POST callback
- Retry mechanism lazımdır

### Priority System

```
Priority Levels:
  CRITICAL (P0): Security alerts, payment failures → immediate, all channels
  HIGH (P1):     Order updates, shipping → within seconds, push + email
  MEDIUM (P2):   Promotions, reminders → within minutes, email
  LOW (P3):      Weekly digest, suggestions → batched, email

Queue Architecture:
  ┌──────────┐
  │ Critical │ ──▶ Dedicated workers (high concurrency)
  │  Queue   │
  └──────────┘
  ┌──────────┐
  │   High   │ ──▶ Standard workers
  │  Queue   │
  └──────────┘
  ┌──────────┐
  │  Medium  │ ──▶ Batch workers
  │  Queue   │
  └──────────┘
  ┌──────────┐
  │   Low    │ ──▶ Scheduled batch processing
  │  Queue   │
  └──────────┘
```

### Notification Preferences

```
User Preferences:
  user_id: 123
  channels:
    email: enabled
    sms: disabled (only critical)
    push: enabled
  quiet_hours: 22:00 - 08:00
  frequency: immediate (not digest)
  categories:
    marketing: email_only
    security: all_channels
    orders: push + email
```

### Rate Limiting və Throttling

```
Rules:
  - Max 5 push notifications per hour per user
  - Max 3 SMS per day per user
  - Max 20 emails per day per user
  - Aggregate similar notifications (e.g., "5 new likes" instead of 5 separate)
```

## Arxitektura

### Full Notification System

```
┌───────────────────────────────────────────────────────┐
│                    Event Sources                       │
│  Order Service | Payment Service | User Service        │
└───────────┬───────────────────────────────────────────┘
            │
     ┌──────┴──────┐
     │  Event Bus  │
     │  (Kafka)    │
     └──────┬──────┘
            │
     ┌──────┴──────────────┐
     │ Notification Service │
     │                      │
     │  ┌────────────────┐ │
     │  │ Template Engine│ │
     │  └────────────────┘ │
     │  ┌────────────────┐ │
     │  │ Preference Svc │ │
     │  └────────────────┘ │
     │  ┌────────────────┐ │
     │  │ Rate Limiter   │ │
     │  └────────────────┘ │
     └──────┬──────────────┘
            │
     ┌──────┴──────┐
     │  Priority   │
     │   Queues    │
     └──────┬──────┘
            │
  ┌─────────┼─────────┬──────────┐
  │         │         │          │
┌─┴──┐  ┌──┴──┐  ┌───┴──┐  ┌───┴────┐
│Email│  │Push │  │ SMS  │  │WebSocket│
│     │  │     │  │      │  │        │
│SES/ │  │FCM/ │  │Twilio│  │Reverb/ │
│SMTP │  │APNs │  │      │  │Pusher  │
└──┬──┘  └──┬──┘  └──┬───┘  └───┬────┘
   │        │        │          │
   └────────┼────────┼──────────┘
            │
     ┌──────┴──────┐
     │  Delivery   │
     │  Tracking   │
     │  Database   │
     └─────────────┘
```

## Nümunələr

### Laravel Notifications

```php
// app/Notifications/OrderShippedNotification.php
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Fcm\FcmMessage;

class OrderShippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $queue = 'notifications-high';

    public function __construct(
        private Order $order,
        private string $trackingNumber
    ) {}

    // Determine which channels to use based on user preferences
    public function via(object $notifiable): array
    {
        $channels = ['database']; // Always store in DB

        $prefs = $notifiable->notificationPreferences;

        if ($prefs->email_enabled) {
            $channels[] = 'mail';
        }
        if ($prefs->push_enabled && $notifiable->deviceTokens()->exists()) {
            $channels[] = 'fcm';
        }
        if ($prefs->sms_enabled && $notifiable->phone) {
            $channels[] = 'vonage';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your order #{$this->order->number} has shipped!")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Great news! Your order has been shipped.")
            ->line("Tracking number: {$this->trackingNumber}")
            ->action('Track Order', url("/orders/{$this->order->id}/tracking"))
            ->line('Thank you for shopping with us!');
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return FcmMessage::create()
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('Order Shipped!')
                ->setBody("Your order #{$this->order->number} is on its way"))
            ->setData([
                'order_id' => (string) $this->order->id,
                'type' => 'order_shipped',
            ]);
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content("Your order #{$this->order->number} shipped! Track: {$this->trackingNumber}");
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'order_shipped',
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'tracking_number' => $this->trackingNumber,
            'message' => "Your order #{$this->order->number} has been shipped.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
```

### Custom Notification Channel

```php
// app/Channels/WebSocketChannel.php
class WebSocketChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toBroadcast')) {
            return;
        }

        $data = $notification->toBroadcast($notifiable);

        broadcast(new NotificationSent($notifiable->id, $data))->toOthers();
    }
}

// app/Channels/SlackChannel.php
class SlackChannel
{
    public function __construct(private HttpClient $http) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toSlack($notifiable);

        $this->http->post($notifiable->slack_webhook_url, [
            'json' => $message,
        ]);
    }
}
```

### Notification Aggregation

```php
// Instead of sending 50 "new like" notifications, aggregate them
class NotificationAggregator
{
    public function shouldAggregate(string $userId, string $type): bool
    {
        $recentCount = Cache::get("notif_count:{$userId}:{$type}", 0);
        return $recentCount >= 3;
    }

    public function aggregate(string $userId, string $type, array $data): void
    {
        $key = "notif_agg:{$userId}:{$type}";

        Cache::increment("notif_count:{$userId}:{$type}");

        $existing = Cache::get($key, []);
        $existing[] = $data;
        Cache::put($key, $existing, now()->addMinutes(30));

        // Schedule aggregated notification
        SendAggregatedNotification::dispatch($userId, $type)
            ->delay(now()->addMinutes(5))
            ->onQueue('notifications-low');
    }
}

// "John, Sarah, and 8 others liked your post"
class AggregatedLikeNotification extends Notification
{
    public function __construct(private array $likes) {}

    public function toArray(object $notifiable): array
    {
        $count = count($this->likes);
        $names = collect($this->likes)->take(2)->pluck('user_name');

        $message = match (true) {
            $count === 1 => "{$names[0]} liked your post",
            $count === 2 => "{$names[0]} and {$names[1]} liked your post",
            default => "{$names[0]}, {$names[1]}, and " . ($count - 2) . " others liked your post",
        };

        return ['message' => $message, 'count' => $count];
    }
}
```

### Notification Preferences Management

```php
// Migration
Schema::create('notification_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('category'); // orders, marketing, security
    $table->boolean('email_enabled')->default(true);
    $table->boolean('push_enabled')->default(true);
    $table->boolean('sms_enabled')->default(false);
    $table->string('quiet_start')->nullable(); // "22:00"
    $table->string('quiet_end')->nullable();   // "08:00"
    $table->timestamps();
    $table->unique(['user_id', 'category']);
});

// Service
class NotificationPreferenceService
{
    public function getChannelsForUser(User $user, string $category): array
    {
        $pref = $user->notificationPreferences()
            ->where('category', $category)
            ->first();

        if (!$pref) {
            return $this->getDefaultChannels($category);
        }

        // Check quiet hours
        if ($this->isQuietHours($pref)) {
            return ['database']; // Store only, deliver later
        }

        $channels = ['database'];
        if ($pref->email_enabled) $channels[] = 'mail';
        if ($pref->push_enabled) $channels[] = 'fcm';
        if ($pref->sms_enabled) $channels[] = 'vonage';

        return $channels;
    }

    private function isQuietHours(NotificationPreference $pref): bool
    {
        if (!$pref->quiet_start || !$pref->quiet_end) {
            return false;
        }

        $now = now()->format('H:i');
        return $now >= $pref->quiet_start || $now < $pref->quiet_end;
    }
}
```

## Real-World Nümunələr

1. **WhatsApp** - 100 billion+ messages per day, end-to-end encryption
2. **Slack** - Multi-channel (desktop, mobile, email digest), threading
3. **Facebook** - Aggregated notifications, relevance ranking
4. **Amazon** - Order lifecycle notifications across email, push, SMS
5. **GitHub** - Watch/subscribe model, notification settings per repo

## Praktik Tapşırıqlar

**S1: Notification system necə scale edilir?**
C: Priority-based queues, horizontal scaling of workers, channel-specific
rate limiting, batching, async processing, database sharding by user_id.

**S2: Duplicate notification-ların qarşısını necə alırsınız?**
C: Idempotency key (notification_id + channel + user_id), deduplication
window (5 min), distributed lock ilə exactly-once delivery.

**S3: Notification delivery failure necə idarə olunur?**
C: Exponential backoff ilə retry, dead letter queue, fallback channels
(push fail → email), delivery status tracking, alerting on high failure rates.

**S4: Milyonlarla istifadəçiyə eyni anda notification göndərmək lazımdırsa?**
C: Fan-out pattern: notification-u queue-ya qoyun, batch processing ilə
user segment-lərə bölün, rate limiting ilə provider limit-lərinə riayət edin.
Progressive delivery (small batch → monitor → full rollout).

**S5: Quiet hours və timezone necə idarə olunur?**
C: User timezone-u saxlayın, notification göndərmədən əvvəl local vaxtı yoxlayın,
quiet hours zamanı gələn notification-ları schedule edin (quiet hours bitdikdən sonra).

**S6: Real-time in-app notification necə implement olunur?**
C: WebSocket connection ilə. User online olarkən birbaşa push, offline olarkən
database-ə saxla. User reconnect edəndə unread notifications-ı yüklə.
Laravel Broadcasting + Echo + Reverb ilə implement olunur.

## Praktik Baxış

1. **Async Processing** - Notification göndərməni heç vaxt sync etməyin
2. **Priority Queues** - Critical vs marketing fərqli queue-larda
3. **User Preferences** - İstifadəçiyə kanal seçimi verin
4. **Rate Limiting** - Hər kanal üçün limit qoyun
5. **Template Engine** - Notification content-ini template ilə idarə edin
6. **Delivery Tracking** - Hər notification-un statusunu track edin
7. **Aggregation** - Oxşar notification-ları birləşdirin
8. **Fallback Channels** - Bir kanal uğursuz olarsa digərinə keçin
9. **Unsubscribe** - Hər email-də unsubscribe link olsun
10. **A/B Testing** - Notification content və timing test edin


## Əlaqəli Mövzular

- [Message Queues](05-message-queues.md) — notification async delivery
- [Push Notification Backend](79-push-notification-backend.md) — APNs/FCM fan-out
- [Real-Time Systems](17-real-time-systems.md) — WebSocket/SSE ilə anlıq bildiriş
- [Webhook Delivery](82-webhook-delivery-system.md) — üçüncü tərəfə event bildirişi
- [Pub/Sub](81-pubsub-system-design.md) — notification fan-out arxitekturası
