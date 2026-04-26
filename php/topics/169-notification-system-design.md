# System Design: Notification System (Senior)

## Mündəricat
1. [Tələblər](#tələblər)
2. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
3. [Komponent Dizaynı](#komponent-dizaynı)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  Kanal dəstəyi: Push, Email, SMS, In-App
  Şablon idarəsi: dinamik şablonlar
  User preferences: kanalları seçmək
  Priority: urgent (dərhal) vs batch (toplu)
  Retry: çatdırılma uğursuz olduqda

Qeyri-funksional:
  Yüksək throughput: saniyədə 1M bildiriş
  Aşağı gecikmə: critical bildiriş < 1 saniyə
  At-least-once delivery (idempotent consumer)
  Analytics: delivery rate, open rate

Hesablamalar:
  10M user × 5 bildiriş/gün = 50M bildiriş/gün
  Saniyədə ~580 bildiriş (ortalama)
  Peak: ~5000/saniyə
```

---

## Yüksək Səviyyəli Dizayn

```
                     ┌─────────────────────────────────────┐
                     │         Notification Service         │
Events ─────────────►│  Validation → Routing → Enqueue     │
API calls ──────────►│                                      │
                     └──────────────────┬──────────────────┘
                                        │
                     ┌──────────────────▼──────────────────┐
                     │            Message Queue             │
                     │  high-priority  |  low-priority      │
                     │  (Kafka/RabbitMQ)                    │
                     └─────┬───────────────┬───────────────┘
                           │               │
              ┌────────────▼──┐    ┌───────▼────────┐
              │  Push Worker  │    │  Email Worker  │
              │  (FCM/APNs)   │    │  (SES/SendGrid)│
              └────────────┬──┘    └───────┬────────┘
                           │               │
              ┌────────────▼──┐    ┌───────▼────────┐
              │ SMS Worker    │    │ In-App Worker  │
              │ (Twilio)      │    │ (WebSocket)    │
              └───────────────┘    └────────────────┘
```

---

## Komponent Dizaynı

```
1. User Preferences Store:
   user_id → {email: true, sms: false, push: true}
   Redis (sürətli oxuma)

2. Template Engine:
   Şablon DB-də: "Salam {{name}}, sifarişiniz {{status}}"
   Variable inject + i18n (dil dəstəyi)

3. Priority Queue:
   Critical (bank, OTP): ayrı yüksək priority queue
   Marketing (promo): batch queue
   Gecə batch-ı: user uyku saatını nəzərə al (timezone)

4. Rate Limiting:
   Bir user-ə saniyədə max 10 bildiriş
   Flood prevention

5. Retry Strategy:
   Exponential backoff: 1s → 2s → 4s → 8s
   Max 3 cəhd
   Uğursuz → Dead Letter Queue

6. Idempotency:
   notification_id unique
   Eyni notification_id ikinci dəfə gəlsə ignore et

7. Delivery Tracking:
   sent, delivered, opened, clicked
   Webhook: FCM/APNs delivery confirmation

Bottleneck-lər:
  External provider rate limit (FCM: 600,000/min)
  Multiple provider → load balance
  Retry storm → backoff + jitter
```

---

## PHP İmplementasiyası

```php
<?php
// Notification Domain Object
namespace App\Notification\Domain;

class Notification
{
    private NotificationId $id;
    private UserId         $userId;
    private string         $type;   // order_confirmed, otp, promo
    private Priority       $priority;
    private array          $data;   // Template variables
    private array          $channels; // ['email', 'push']
    private NotificationStatus $status;

    public static function create(
        UserId   $userId,
        string   $type,
        array    $data,
        Priority $priority = Priority::NORMAL,
    ): self {
        $n           = new self();
        $n->id       = NotificationId::generate();
        $n->userId   = $userId;
        $n->type     = $type;
        $n->data     = $data;
        $n->priority = $priority;
        $n->status   = NotificationStatus::PENDING;
        return $n;
    }
}
```

```php
<?php
// Notification Router — user preference-ə görə kanal seçimi
class NotificationRouter
{
    public function __construct(
        private UserPreferenceRepository $preferences,
        private array $channelHandlers, // ['email' => EmailHandler, ...]
    ) {}

    public function route(Notification $notification): void
    {
        $prefs    = $this->preferences->findByUser($notification->getUserId());
        $channels = $this->determineChannels($notification->getType(), $prefs);

        foreach ($channels as $channel) {
            if (!isset($this->channelHandlers[$channel])) {
                continue;
            }

            $this->channelHandlers[$channel]->enqueue($notification);
        }
    }

    private function determineChannels(string $type, UserPreferences $prefs): array
    {
        // OTP həmişə SMS
        if ($type === 'otp') {
            return ['sms'];
        }

        // User preference-ə görə
        $channels = [];
        if ($prefs->isEmailEnabled()) $channels[] = 'email';
        if ($prefs->isPushEnabled())  $channels[] = 'push';
        if ($prefs->isSmsEnabled())   $channels[] = 'sms';

        return $channels;
    }
}
```

```php
<?php
// Email Channel Worker — retry ilə
class EmailNotificationWorker
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private EmailProviderInterface $provider,
        private TemplateEngine         $templates,
        private NotificationRepository $repository,
        private MessageQueue           $queue,
    ) {}

    public function process(NotificationJob $job): void
    {
        $notification = $this->repository->findById($job->notificationId);

        if ($notification->isAlreadySent()) {
            return; // Idempotency — iki dəfə göndərmə
        }

        try {
            $content = $this->templates->render(
                $notification->getType(),
                $notification->getData(),
            );

            $this->provider->send(
                to:      $notification->getUserEmail(),
                subject: $content->subject,
                body:    $content->body,
            );

            $notification->markSent();
            $this->repository->save($notification);

        } catch (TemporaryFailureException $e) {
            // Müvəqqəti xəta → retry
            $attempt = $job->attempt + 1;

            if ($attempt < self::MAX_RETRIES) {
                $delay = (2 ** $attempt) + random_int(0, 1000) / 1000; // jitter
                $this->queue->publishDelayed($job->withAttempt($attempt), $delay);
            } else {
                $notification->markFailed($e->getMessage());
                $this->repository->save($notification);
                $this->queue->publishToDlq($job); // Dead Letter Queue
            }

        } catch (PermanentFailureException $e) {
            // Permanent xəta → DLQ, retry yoxdur
            $notification->markFailed($e->getMessage());
            $this->repository->save($notification);
        }
    }
}
```

---

## İntervyu Sualları

- Saniyədə 1M bildiriş üçün arxitekturanı necə dizayn edərdiniz?
- Push, Email, SMS üçün ayrı worker-lar niyə lazımdır?
- Retry storm-u eksponensial backoff ilə necə önləyirsiniz?
- Idempotent notification delivery necə tətbiq edilir?
- User timezone-u nəzərə alaraq gecə bildirişi göndərməmək üçün?
- Critical vs marketing bildirişlərini necə ayırırsınız?
