# Scalable Notification System Dizaynı

## Problem

Böyük bir platformada istifadəçilərə müxtəlif kanallar vasitəsilə (email, SMS, push notification, in-app, Slack) bildirişlər göndərmək lazımdır. İstifadəçilər hansı kanaldan bildiriş almaq istədiklərini seçə bilməlidirlər. Eyni zamanda çoxlu bildirişlər göndərilərkən sistem yavaşlamamalı, istifadəçilər spam hiss etməməli və real-time bildirişlər dəstəklənməlidir.

**Real-world ssenari:**
- E-commerce platforması: sifariş statusu, kampaniya, qiymət düşməsi
- SaaS platforması: yeni comment, mention, task assignment
- Banking app: tranzaksiya bildirişi, təhlükəsizlik xəbərdarlığı

---

## 1. Database Schema Dizaynı

*Bu kod bildiriş tercihlərini, loglarını, throttle izləməni və template-ləri saxlayan cədvəl strukturunu göstərir:*

```sql
-- İstifadəçi notification preferences
CREATE TABLE notification_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(100) NOT NULL,  -- 'order_status', 'promotion', 'security'
    channel VARCHAR(50) NOT NULL,             -- 'email', 'sms', 'push', 'in_app', 'slack'
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pref (user_id, notification_type, channel),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notification-lar (in-app üçün)
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,               -- UUID
    type VARCHAR(255) NOT NULL,             -- Notification class adı
    notifiable_type VARCHAR(255) NOT NULL,  -- 'App\Models\User'
    notifiable_id BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,                     -- Notification məlumatları
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifiable (notifiable_type, notifiable_id),
    INDEX idx_read_at (read_at),
    INDEX idx_created_at (created_at)
);

-- Notification log (bütün kanallara göndərilən bildirişlərin logu)
CREATE TABLE notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(255) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    metadata JSON NULL,
    sent_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    failure_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_channel (user_id, channel),
    INDEX idx_status (status),
    INDEX idx_type_created (notification_type, created_at)
);

-- Notification throttle tracking
CREATE TABLE notification_throttles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(255) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    last_sent_at TIMESTAMP NOT NULL,
    count_in_window INT DEFAULT 1,
    window_start TIMESTAMP NOT NULL,
    UNIQUE KEY unique_throttle (user_id, notification_type, channel)
);

-- Notification templates
CREATE TABLE notification_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    subject_template TEXT NULL,
    body_template TEXT NOT NULL,
    channels JSON NOT NULL,           -- ['email', 'sms', 'push']
    variables JSON NULL,              -- template-də istifadə olunan dəyişənlər
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 2. Laravel Migration-lar

*Bu kod notification preferences cədvəlini unique constraint ilə yaradan Laravel migration-u göstərir:*

```php
// database/migrations/2024_01_01_create_notification_preferences_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type', 100);
            $table->string('channel', 50);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'notification_type', 'channel'], 'unique_pref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
```

*Bu kod göndərilən bildirişlərin statusunu və səbəbini izləyən notification_logs cədvəlini yaradır:*

```php
// database/migrations/2024_01_02_create_notification_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type');
            $table->string('channel', 50);
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'channel']);
            $table->index('status');
            $table->index(['notification_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
```

---

## 3. Notification Preferences Model və Service

*Bu kod istifadəçinin kanal üzrə bildiriş seçimlərini idarə edən Eloquent modelini göstərir:*

```php
// app/Models/NotificationPreference.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

*Bu kod istifadəçinin bildiriş tercihlərini cache ilə oxuyan və yeniləyən service-i göstərir:*

```php
// app/Services/NotificationPreferenceService.php
<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NotificationPreferenceService
{
    /**
     * Bütün notification type-ları və default kanalları.
     * Yeni notification əlavə edəndə buranı yeniləmək lazımdır.
     */
    private const DEFAULT_PREFERENCES = [
        'order_status' => ['email', 'push', 'in_app'],
        'promotion'    => ['email'],
        'security'     => ['email', 'sms', 'push', 'in_app'],
        'comment'      => ['push', 'in_app'],
        'mention'      => ['email', 'push', 'in_app'],
        'price_drop'   => ['push', 'in_app'],
    ];

    /**
     * İstifadəçinin müəyyən notification üçün aktiv kanallarını qaytarır.
     */
    public function getEnabledChannels(User $user, string $notificationType): array
    {
        $cacheKey = "user:{$user->id}:notification_prefs:{$notificationType}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($user, $notificationType) {
            $preferences = NotificationPreference::where('user_id', $user->id)
                ->where('notification_type', $notificationType)
                ->get();

            // Əgər istifadəçi heç bir preference set etməyibsə, default-ları qaytarırıq
            if ($preferences->isEmpty()) {
                return self::DEFAULT_PREFERENCES[$notificationType] ?? ['in_app'];
            }

            return $preferences
                ->where('is_enabled', true)
                ->pluck('channel')
                ->toArray();
        });
    }

    /**
     * İstifadəçinin preference-ini yeniləyir.
     */
    public function updatePreference(
        User $user,
        string $notificationType,
        string $channel,
        bool $isEnabled
    ): NotificationPreference {
        $pref = NotificationPreference::updateOrCreate(
            [
                'user_id'           => $user->id,
                'notification_type' => $notificationType,
                'channel'           => $channel,
            ],
            ['is_enabled' => $isEnabled]
        );

        // Cache-i təmizləyirik
        Cache::forget("user:{$user->id}:notification_prefs:{$notificationType}");

        return $pref;
    }

    /**
     * İstifadəçinin bütün preference-lərini toplu şəkildə yeniləyir.
     */
    public function bulkUpdatePreferences(User $user, array $preferences): void
    {
        // $preferences formatı: ['order_status' => ['email' => true, 'sms' => false], ...]
        foreach ($preferences as $type => $channels) {
            foreach ($channels as $channel => $isEnabled) {
                $this->updatePreference($user, $type, $channel, $isEnabled);
            }
        }
    }

    /**
     * İstifadəçi müəyyən kanaldan bildiriş almaq istəyirmi?
     */
    public function isChannelEnabled(User $user, string $notificationType, string $channel): bool
    {
        $enabledChannels = $this->getEnabledChannels($user, $notificationType);
        return in_array($channel, $enabledChannels);
    }
}
```

---

## 4. Base Notification Class və Channel Routing

*Bu kod istifadəçi tercihlərinə görə kanal seçən və priority əsaslı queue-ya yönləndirən base notification sinifini göstərir:*

```php
// app/Notifications/BaseNotification.php
<?php

namespace App\Notifications;

use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Notification type-ı (preference system-də istifadə olunur).
     */
    abstract public function notificationType(): string;

    /**
     * İstifadəçinin preference-lərinə görə kanalları müəyyən edir.
     * Laravel-in via() metodu hər notification üçün çağırılır.
     */
    public function via(object $notifiable): array
    {
        $preferenceService = app(NotificationPreferenceService::class);
        $enabledChannels = $preferenceService->getEnabledChannels(
            $notifiable,
            $this->notificationType()
        );

        // Channel adlarını Laravel driver adlarına çeviririk
        return collect($enabledChannels)->map(function ($channel) {
            return match ($channel) {
                'email'  => 'mail',
                'sms'    => 'vonage',     // və ya Twilio
                'push'   => 'fcm',        // Firebase Cloud Messaging
                'in_app' => 'database',
                'slack'  => 'slack',
                default  => null,
            };
        })->filter()->values()->toArray();
    }

    /**
     * Queue connection-u və queue adını təyin edirik.
     * Yüksək prioritetli bildirişlər (security) ayrı queue-da olmalıdır.
     */
    public function viaQueues(): array
    {
        $queue = $this->isHighPriority() ? 'notifications-high' : 'notifications';

        return [
            'mail'     => $queue,
            'vonage'   => $queue,
            'fcm'      => $queue,
            'database' => $queue,
            'slack'    => $queue,
        ];
    }

    protected function isHighPriority(): bool
    {
        return false;
    }
}
```

---

## 5. Konkret Notification Nümunələri

### Sifariş Status Bildirişi

*Bu kod email, SMS və in-app kanalları üçün sifariş status bildirişini göstərir:*

```php
// app/Notifications/OrderStatusNotification.php
<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;

class OrderStatusNotification extends BaseNotification
{
    public function __construct(
        private Order $order,
        private string $newStatus
    ) {
        // Queue-a əlavə seçimlər
        $this->afterCommit();  // DB transaction bitdikdən sonra göndər
    }

    public function notificationType(): string
    {
        return 'order_status';
    }

    /**
     * Email formatı
     */
    public function toMail(object $notifiable): MailMessage
    {
        $statusText = $this->getStatusText();

        return (new MailMessage())
            ->subject("Sifariş #{$this->order->id} - {$statusText}")
            ->greeting("Salam {$notifiable->name}!")
            ->line("Sifarişinizin statusu yeniləndi: **{$statusText}**")
            ->line("Sifariş nömrəsi: #{$this->order->id}")
            ->line("Məbləğ: {$this->order->total_amount} AZN")
            ->action('Sifarişə bax', url("/orders/{$this->order->id}"))
            ->line('Təşəkkür edirik!');
    }

    /**
     * SMS formatı (qısa olmalıdır)
     */
    public function toVonage(object $notifiable): VonageMessage
    {
        $statusText = $this->getStatusText();

        return (new VonageMessage())
            ->content("Sifariş #{$this->order->id}: {$statusText}. Bax: " . url("/orders/{$this->order->id}"));
    }

    /**
     * Database (in-app) formatı
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id'   => $this->order->id,
            'status'     => $this->newStatus,
            'status_text'=> $this->getStatusText(),
            'amount'     => $this->order->total_amount,
            'message'    => "Sifariş #{$this->order->id} statusu: {$this->getStatusText()}",
            'url'        => "/orders/{$this->order->id}",
        ];
    }

    /**
     * Real-time broadcast (WebSocket)
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id'          => $this->id,
            'type'        => 'order_status',
            'order_id'    => $this->order->id,
            'status'      => $this->newStatus,
            'status_text' => $this->getStatusText(),
            'message'     => "Sifariş #{$this->order->id}: {$this->getStatusText()}",
            'created_at'  => now()->toISOString(),
        ]);
    }

    private function getStatusText(): string
    {
        return match ($this->newStatus) {
            'confirmed'  => 'Sifariş təsdiqləndi',
            'processing' => 'Hazırlanır',
            'shipped'    => 'Göndərildi',
            'delivered'  => 'Çatdırıldı',
            'cancelled'  => 'Ləğv edildi',
            default      => $this->newStatus,
        };
    }
}
```

### Təhlükəsizlik Bildirişi (Yüksək Prioritet)

*Bu kod yüksək prioritetli ayrı queue-da gedən, email və SMS ilə təhlükəsizlik xəbərdarlığı bildirişini göstərir:*

```php
// app/Notifications/SecurityAlertNotification.php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;

class SecurityAlertNotification extends BaseNotification
{
    public function __construct(
        private string $alertType,
        private array $details
    ) {
        // Təhlükəsizlik bildirişləri dərhal göndərilməlidir
        $this->onQueue('notifications-high');
    }

    public function notificationType(): string
    {
        return 'security';
    }

    protected function isHighPriority(): bool
    {
        return true;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Təhlükəsizlik Xəbərdarlığı')
            ->error()  // Qırmızı button
            ->greeting("Salam {$notifiable->name}!")
            ->line($this->getAlertMessage())
            ->line("IP ünvan: {$this->details['ip'] ?? 'Naməlum'}")
            ->line("Vaxt: " . now()->format('d.m.Y H:i:s'))
            ->action('Hesab ayarlarını yoxla', url('/settings/security'))
            ->line('Əgər bu siz deyilsinizsə, dərhal parolunuzu dəyişin.');
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage())
            ->content("XƏBƏRDARLIQ: {$this->getAlertMessage()}. Hesabınızı yoxlayın.");
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'alert_type' => $this->alertType,
            'message'    => $this->getAlertMessage(),
            'details'    => $this->details,
            'severity'   => 'high',
            'url'        => '/settings/security',
        ];
    }

    private function getAlertMessage(): string
    {
        return match ($this->alertType) {
            'new_login'         => 'Hesabınıza yeni cihazdan daxil olundu',
            'password_changed'  => 'Parolunuz dəyişdirildi',
            'two_fa_disabled'   => 'İki faktorlu autentifikasiya söndürüldü',
            'suspicious_activity' => 'Şübhəli fəaliyyət aşkarlandı',
            default             => 'Təhlükəsizlik xəbərdarlığı',
        };
    }
}
```

---

## 6. Notification Throttling / Digest Service

Throttling — istifadəçiyə çox tez-tez bildiriş göndərməmək üçün istifadə olunur. Digest isə çoxlu bildirişləri toplayıb bir dənə göndərir.

*Bu kod Redis ilə istifadəçiyə göndərilən bildiriş sayını məhdudlaşdıran throttle service-ini göstərir:*

```php
// app/Services/NotificationThrottleService.php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

class NotificationThrottleService
{
    /**
     * Hər notification type üçün throttle qaydaları.
     * [maksimum_say, müddət_saniyə]
     */
    private const THROTTLE_RULES = [
        'promotion'   => [1, 86400],    // Gündə 1 dəfə
        'comment'     => [5, 3600],     // Saatda 5 dəfə
        'mention'     => [10, 3600],    // Saatda 10 dəfə
        'price_drop'  => [3, 86400],    // Gündə 3 dəfə
        'order_status'=> [20, 3600],    // Saatda 20 dəfə (limit yoxdur demək olar)
        'security'    => [100, 3600],   // Təhlükəsizlik — demək olar limit yoxdur
    ];

    /**
     * Notification göndərilə bilərmi?
     * Redis-dən istifadə edirik çünki çox sürətlidir və atomic əməliyyatlar dəstəkləyir.
     */
    public function canSend(User $user, string $notificationType, string $channel): bool
    {
        $rule = self::THROTTLE_RULES[$notificationType] ?? null;

        // Əgər throttle qaydası yoxdursa, icazə veririk
        if ($rule === null) {
            return true;
        }

        [$maxCount, $windowSeconds] = $rule;

        $key = "notification_throttle:{$user->id}:{$notificationType}:{$channel}";
        $currentCount = (int) Redis::get($key);

        return $currentCount < $maxCount;
    }

    /**
     * Notification göndərildikdən sonra counter-i artırırıq.
     */
    public function recordSend(User $user, string $notificationType, string $channel): void
    {
        $rule = self::THROTTLE_RULES[$notificationType] ?? null;
        if ($rule === null) {
            return;
        }

        [, $windowSeconds] = $rule;

        $key = "notification_throttle:{$user->id}:{$notificationType}:{$channel}";

        // INCR atomic-dir — race condition olmur
        $newCount = Redis::incr($key);

        // İlk dəfə yazılırsa, TTL qoyuruq
        if ($newCount === 1) {
            Redis::expire($key, $windowSeconds);
        }
    }

    /**
     * Throttle olunmuş bildirişləri digest üçün saxlayırıq.
     */
    public function queueForDigest(User $user, string $notificationType, array $data): void
    {
        $key = "notification_digest:{$user->id}:{$notificationType}";

        Redis::rpush($key, json_encode([
            'data'       => $data,
            'created_at' => now()->toISOString(),
        ]));

        // 24 saatdan sonra avtomatik silinir
        Redis::expire($key, 86400);
    }

    /**
     * Digest üçün yığılmış bildirişləri oxuyuruq.
     */
    public function getDigestItems(User $user, string $notificationType): array
    {
        $key = "notification_digest:{$user->id}:{$notificationType}";

        $items = Redis::lrange($key, 0, -1);
        Redis::del($key);

        return array_map(fn ($item) => json_decode($item, true), $items);
    }
}
```

### Digest Notification Göndərən Scheduled Command

*Bu kod yığılmış bildirişləri hər saat toplu şəkildə digest kimi göndərən artıq komandanı göstərir:*

```php
// app/Console/Commands/SendNotificationDigest.php
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DigestNotification;
use App\Services\NotificationThrottleService;
use Illuminate\Console\Command;

class SendNotificationDigest extends Command
{
    protected $signature = 'notifications:send-digest';
    protected $description = 'Yığılmış bildirişləri digest şəklində göndərir';

    public function handle(NotificationThrottleService $throttleService): void
    {
        $notificationTypes = ['comment', 'mention', 'promotion'];

        User::chunk(500, function ($users) use ($throttleService, $notificationTypes) {
            foreach ($users as $user) {
                foreach ($notificationTypes as $type) {
                    $items = $throttleService->getDigestItems($user, $type);

                    if (count($items) > 0) {
                        $user->notify(new DigestNotification($type, $items));
                        $this->info("Digest göndərildi: User #{$user->id}, Type: {$type}, Count: " . count($items));
                    }
                }
            }
        });
    }
}

// app/Console/Kernel.php (schedule)
// $schedule->command('notifications:send-digest')->hourly();
```

---

## 7. Notification Dispatcher (Throttle ilə inteqrasiya)

*Bu kod throttle yoxlaması, digest qeydiyyatı və loglama ilə bildiriş göndərən dispatcher service-ini göstərir:*

```php
// app/Services/NotificationDispatcher.php
<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public function __construct(
        private NotificationPreferenceService $preferenceService,
        private NotificationThrottleService $throttleService,
        private NotificationLogService $logService
    ) {}

    /**
     * Notification-u göndərir (bütün yoxlamaları edir).
     */
    public function dispatch(User $user, BaseNotification $notification): void
    {
        $notificationType = $notification->notificationType();
        $enabledChannels = $this->preferenceService->getEnabledChannels($user, $notificationType);

        foreach ($enabledChannels as $channel) {
            // Throttle yoxlaması
            if (!$this->throttleService->canSend($user, $notificationType, $channel)) {
                Log::info("Notification throttled", [
                    'user_id' => $user->id,
                    'type'    => $notificationType,
                    'channel' => $channel,
                ]);

                // Digest üçün saxlayırıq
                $this->throttleService->queueForDigest($user, $notificationType, [
                    'notification_class' => get_class($notification),
                    'channel'            => $channel,
                ]);

                continue;
            }

            // Throttle counter-i artırırıq
            $this->throttleService->recordSend($user, $notificationType, $channel);

            // Log yazırıq
            $this->logService->log($user, $notificationType, $channel, 'pending');
        }

        // Laravel-in notification system-i göndərir
        // via() metodu artıq preference-lərə görə kanalları qaytaracaq
        $user->notify($notification);
    }

    /**
     * Bir neçə istifadəçiyə eyni notification-u göndərir.
     * Batch processing — çox sayda istifadəçiyə göndərmək üçün.
     */
    public function dispatchToMany(array $users, BaseNotification $notification): void
    {
        foreach ($users as $user) {
            try {
                $this->dispatch($user, $notification);
            } catch (\Throwable $e) {
                Log::error("Notification dispatch failed", [
                    'user_id' => $user->id,
                    'type'    => $notification->notificationType(),
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
```

---

## 8. In-App Notifications: Read/Unread Tracking

*Bu kod in-app bildirişləri listələmək, oxunmuş işarələmək üçün controller-i göstərir:*

```php
// app/Http/Controllers/NotificationController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * İstifadəçinin bildirişlərini siyahılayır (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->query('unread_only'), function ($query) {
                $query->whereNull('read_at');
            })
            ->when($request->query('type'), function ($query, $type) {
                $query->where('type', 'like', "%{$type}%");
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Tək bir bildirişi oxunmuş kimi işarələyir.
     */
    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($notificationId);

        $notification->markAsRead();

        return response()->json([
            'message'      => 'Bildiriş oxunmuş kimi işarələndi',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Bütün bildirişləri oxunmuş kimi işarələyir.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message'      => 'Bütün bildirişlər oxunmuş kimi işarələndi',
            'unread_count' => 0,
        ]);
    }

    /**
     * Bildirişi silir (soft delete deyil, tam silir).
     */
    public function destroy(Request $request, string $notificationId): JsonResponse
    {
        $request->user()
            ->notifications()
            ->findOrFail($notificationId)
            ->delete();

        return response()->json(['message' => 'Bildiriş silindi']);
    }

    /**
     * Oxunmamış bildiriş sayını qaytarır (polling və ya ilkin yükləmə üçün).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
```

### Notification Routes

*Notification Routes üçün kod nümunəsi:*
```php
// routes/api.php

Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
});
```

---

## 9. Real-time Notifications (Broadcasting)

### Laravel Broadcasting Config

*Laravel Broadcasting Config üçün kod nümunəsi:*
```php
// config/broadcasting.php
'connections' => [
    'reverb' => [
        'driver'  => 'reverb',
        'key'     => env('REVERB_APP_KEY'),
        'secret'  => env('REVERB_APP_SECRET'),
        'app_id'  => env('REVERB_APP_ID'),
        'options' => [
            'host'   => env('REVERB_HOST', '0.0.0.0'),
            'port'   => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'https'),
        ],
    ],
],
```

### Private Channel Authorization

*Private Channel Authorization üçün kod nümunəsi:*
```php
// routes/channels.php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Notification üçün xüsusi kanal
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

### Frontend (JavaScript - Laravel Echo ilə)

*Frontend (JavaScript - Laravel Echo ilə) üçün kod nümunəsi:*
```javascript
// resources/js/notifications.js
import Echo from 'laravel-echo';

// Laravel Echo konfiqurasiyası
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

const userId = document.querySelector('meta[name="user-id"]').content;

// Private kanalda notification-ları dinləyirik
window.Echo.private(`App.Models.User.${userId}`)
    .notification((notification) => {
        console.log('Yeni bildiriş:', notification);

        // Notification counter-i yeniləyirik
        updateNotificationBadge();

        // Toast/popup göstəririk
        showNotificationToast({
            title: notification.type,
            message: notification.message,
            url: notification.url,
        });
    });

function updateNotificationBadge() {
    fetch('/api/notifications/unread-count', {
        headers: {
            'Authorization': `Bearer ${getToken()}`,
            'Accept': 'application/json',
        }
    })
    .then(res => res.json())
    .then(data => {
        const badge = document.getElementById('notification-badge');
        badge.textContent = data.unread_count;
        badge.style.display = data.unread_count > 0 ? 'inline' : 'none';
    });
}

function showNotificationToast({ title, message, url }) {
    // Browser notification (icazə lazımdır)
    if (Notification.permission === 'granted') {
        const n = new Notification(title, { body: message });
        n.onclick = () => window.open(url);
    }
}
```

---

## 10. Notification Template System

Dinamik template-lər admin paneldən idarə oluna bilər.

*Dinamik template-lər admin paneldən idarə oluna bilər üçün kod nümunəsi:*
```php
// app/Services/NotificationTemplateService.php
<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationTemplateService
{
    /**
     * Template-i render edir — dəyişənləri əvəz edir.
     *
     * Template nümunəsi: "Salam {{name}}, sifarişiniz #{{order_id}} {{status}} statusundadır."
     */
    public function render(string $templateName, array $variables): array
    {
        $template = $this->getTemplate($templateName);

        if (!$template) {
            throw new \RuntimeException("Notification template tapılmadı: {$templateName}");
        }

        $subject = $this->replaceVariables($template->subject_template, $variables);
        $body = $this->replaceVariables($template->body_template, $variables);

        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }

    private function getTemplate(string $name): ?NotificationTemplate
    {
        return Cache::remember(
            "notification_template:{$name}",
            now()->addHours(6),
            fn () => NotificationTemplate::where('name', $name)->where('is_active', true)->first()
        );
    }

    private function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{" . $key . "}}", (string) $value, $template);
        }

        // İstifadə olunmamış dəyişənləri təmizləyirik
        $template = preg_replace('/\{\{[a-zA-Z_]+\}\}/', '', $template);

        return trim($template);
    }
}
```

---

## 11. Notification Log Service

*11. Notification Log Service üçün kod nümunəsi:*
```php
// app/Services/NotificationLogService.php
<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;

class NotificationLogService
{
    public function log(User $user, string $type, string $channel, string $status, ?array $metadata = null): NotificationLog
    {
        return NotificationLog::create([
            'user_id'           => $user->id,
            'notification_type' => $type,
            'channel'           => $channel,
            'status'            => $status,
            'metadata'          => $metadata,
            'sent_at'           => $status === 'sent' ? now() : null,
            'failed_at'         => $status === 'failed' ? now() : null,
        ]);
    }

    public function markAsSent(int $logId): void
    {
        NotificationLog::where('id', $logId)->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(int $logId, string $reason): void
    {
        NotificationLog::where('id', $logId)->update([
            'status'         => 'failed',
            'failed_at'      => now(),
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Admin dashboard üçün statistikalar.
     */
    public function getStats(string $period = 'today'): array
    {
        $query = NotificationLog::query();

        match ($period) {
            'today'     => $query->whereDate('created_at', today()),
            'this_week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()]),
            'this_month'=> $query->whereBetween('created_at', [now()->startOfMonth(), now()]),
            default     => null,
        };

        return [
            'total'   => (clone $query)->count(),
            'sent'    => (clone $query)->where('status', 'sent')->count(),
            'failed'  => (clone $query)->where('status', 'failed')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'by_channel' => (clone $query)
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
        ];
    }
}
```

---

## 12. Notification Grouping/Batching

Eyni tipli çoxlu notification-ları qruplaşdırıb bir mesaj kimi göndərmək.

*Eyni tipli çoxlu notification-ları qruplaşdırıb bir mesaj kimi göndərm üçün kod nümunəsi:*
```php
// app/Notifications/BatchCommentNotification.php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class BatchCommentNotification extends BaseNotification
{
    /**
     * @param array $comments Qruplaşdırılmış comment-lər
     */
    public function __construct(
        private array $comments,
        private string $postTitle
    ) {}

    public function notificationType(): string
    {
        return 'comment';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->comments);
        $mail = (new MailMessage())
            ->subject("{$count} yeni şərh: \"{$this->postTitle}\"")
            ->greeting("Salam {$notifiable->name}!")
            ->line("**\"{$this->postTitle}\"** yazınıza {$count} yeni şərh yazıldı:");

        // Hər comment-i əlavə edirik (max 5)
        $displayComments = array_slice($this->comments, 0, 5);
        foreach ($displayComments as $comment) {
            $mail->line("- **{$comment['author']}**: \"{$comment['excerpt']}\"");
        }

        if ($count > 5) {
            $mail->line("... və {$count - 5} şərh daha.");
        }

        $mail->action('Bütün şərhləri gör', url("/posts/{$this->comments[0]['post_id']}#comments"));

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'batch_comment',
            'post_title' => $this->postTitle,
            'count'      => count($this->comments),
            'comments'   => array_slice($this->comments, 0, 5),
            'message'    => count($this->comments) . " yeni şərh: \"{$this->postTitle}\"",
            'url'        => "/posts/{$this->comments[0]['post_id']}#comments",
        ];
    }
}
```

---

## 13. Controller-dən İstifadə Nümunəsi

*13. Controller-dən İstifadə Nümunəsi üçün kod nümunəsi:*
```php
// app/Http/Controllers/OrderController.php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Notifications\OrderStatusNotification;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private NotificationDispatcher $notificationDispatcher
    ) {}

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:confirmed,processing,shipped,delivered,cancelled',
        ]);

        $order->update(['status' => $validated['status']]);

        // Notification göndəririk — bütün yoxlamalar (preference, throttle) avtomatik olur
        $this->notificationDispatcher->dispatch(
            $order->user,
            new OrderStatusNotification($order, $validated['status'])
        );

        return response()->json([
            'message' => 'Sifariş statusu yeniləndi',
            'order'   => $order->fresh(),
        ]);
    }
}
```

---

## 14. Notification Preferences API

*14. Notification Preferences API üçün kod nümunəsi:*
```php
// app/Http/Controllers/NotificationPreferenceController.php
<?php

namespace App\Http\Controllers;

use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private NotificationPreferenceService $preferenceService
    ) {}

    /**
     * İstifadəçinin bütün notification preference-lərini qaytarır.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $allTypes = ['order_status', 'promotion', 'security', 'comment', 'mention', 'price_drop'];
        $allChannels = ['email', 'sms', 'push', 'in_app', 'slack'];

        $preferences = [];
        foreach ($allTypes as $type) {
            $enabledChannels = $this->preferenceService->getEnabledChannels($user, $type);
            $preferences[$type] = [];
            foreach ($allChannels as $channel) {
                $preferences[$type][$channel] = in_array($channel, $enabledChannels);
            }
        }

        return response()->json(['preferences' => $preferences]);
    }

    /**
     * İstifadəçinin preference-lərini toplu yeniləyir.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*' => 'array',
            'preferences.*.*' => 'boolean',
        ]);

        $this->preferenceService->bulkUpdatePreferences(
            $request->user(),
            $validated['preferences']
        );

        return response()->json(['message' => 'Bildiriş seçimləri yeniləndi']);
    }
}
```

---

## 15. Test Nümunələri

*15. Test Nümunələri üçün kod nümunəsi:*
```php
// tests/Feature/NotificationSystemTest.php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use App\Services\NotificationDispatcher;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationThrottleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_respects_user_preferences(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        // İstifadəçi yalnız email istəyir
        $prefService = app(NotificationPreferenceService::class);
        $prefService->updatePreference($user, 'order_status', 'email', true);
        $prefService->updatePreference($user, 'order_status', 'sms', false);
        $prefService->updatePreference($user, 'order_status', 'push', false);
        $prefService->updatePreference($user, 'order_status', 'in_app', false);

        $notification = new OrderStatusNotification($order, 'shipped');
        $channels = $notification->via($user);

        $this->assertEquals(['mail'], $channels);
    }

    public function test_notification_throttling_works(): void
    {
        Redis::flushall();

        $user = User::factory()->create();
        $throttleService = app(NotificationThrottleService::class);

        // Promotion üçün limit gündə 1-dir
        $this->assertTrue($throttleService->canSend($user, 'promotion', 'email'));

        $throttleService->recordSend($user, 'promotion', 'email');

        // İkinci dəfə göndərmək olmaz
        $this->assertFalse($throttleService->canSend($user, 'promotion', 'email'));
    }

    public function test_in_app_notifications_crud(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Notification yaradırıq
        $order = Order::factory()->create(['user_id' => $user->id]);
        $user->notify(new OrderStatusNotification($order, 'shipped'));

        // Siyahı
        $response = $this->getJson('/api/notifications');
        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);

        // Oxunmuş kimi işarələ
        $notificationId = $response->json('notifications.data.0.id');
        $this->patchJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }
}
```

---

## Interview Sualları və Cavablar

**S: Niyə notification-ları queue-a qoymaq lazımdır?**
C: Email/SMS göndərmək I/O-intensive əməliyyatlardır. Queue istifadə etməsək, istifadəçi HTTP response-u gözləyəcək. Queue ilə notification asinxron göndərilir, response dərhal qaytarılır.

**S: Throttling niyə Redis-dədir, database-də deyil?**
C: Redis in-memory-dir, çox sürətlidir. Hər notification göndərməzdən əvvəl throttle yoxlaması aparılır — bu yoxlama çox tez olmalıdır. Database-ə hər dəfə sorğu göndərmək performansa mənfi təsir edər.

**S: Notification preference-lər dəyişəndə köhnə queue-dakı notification-lar nə olur?**
C: `via()` metodu notification işlənəndə (queue-dan çıxanda) çağırılır, yaradılanda deyil. Bu o deməkdir ki, istifadəçi preference-ini queue-dakı notification işlənməzdən əvvəl dəyişsə, yeni preference tətbiq olunacaq.

**S: Real-time notification üçün WebSocket vs polling?**
C: WebSocket (Laravel Reverb/Pusher) real-time-dır və daha effektivdir — server bildiriş olduqda push edir. Polling isə müəyyən intervalla server-ə sorğu göndərir, bu isə lazımsız trafik yaradır. Lakin WebSocket daha çox infrastruktur tələb edir.

**S: Digest notification nə üçündür?**
C: Çox aktiv istifadəçilərə (məs. populyar post müəllifi) hər comment üçün ayrı email göndərmək spam kimidir. Digest ilə "Son 1 saatda 15 yeni şərh" kimi bir email göndərilir.

**S: Notification channel uğursuz olduqda (email bounce) nə etmək lazımdır?**
C: Çoxpilləli strategiya: (1) Failed notification-ı log-la, (2) exponential backoff ilə retry et (3 cəhd: 5dəq, 30dəq, 2saat), (3) bütün retry-lar uğursuzdursa, fallback kanala keç (email → push), (4) müntəzəm bounce-ları olan user-lər üçün kanalı deaktiv et (email invalid olduqda yenidən göndərməyin mənası yoxdur).

**S: 1 milyonluq user base-inə kampaniya bildirişi necə göndərilir?**
C: Eyni anda 1M notification queue-a atmaq yanlışdır — worker-lar tıxanır, digər kritik notification-lar gecikir. Həll: batching + rate limiting. Hər batch 1000 user, aralarında 100ms gözləmə. Ayrı `notifications` queue worker-ları (kampaniya üçün) vs `critical` queue (tranzaksiya bildirişləri üçün). Bu şəkildə kampaniya ilə ödəniş bildirişləri bir-birinə mane olmur.

---

## Anti-patterns

**1. Notification-ı synchronous göndərmək**
Request-in içindən email/SMS göndərmək — 500ms+ latency, timeout riski. Həmişə queue-a dispatch et.

**2. User preference-i yoxlamadan göndərmək**
User email notification-ı söndürüb, amma kod yenə göndərir — spam, unsubscribe, GDPR şikayəti. `via()` metodunda preference yoxlanmalıdır.

**3. Notification throttling yoxdur**
Viral post — 1000 comment → 1000 ayrı email. Throttle olmadan email provider limit-ə çatır, delivery rate düşür. Rate limiting + digest pattern mütləqdir.

**4. Failed notification-ları izləməmək**
Email bounces, SMS failures silent şəkildə silinir. Retry mexanizmi, failed delivery tracking, provider fallback (Mailgun → SES) lazımdır.

**5. Notification template-ləri hardcode etmək**
Mətn dəyişməsi üçün deploy lazım olur. DB-dən idarə olunan template-lər (admin panel) daha çevik, non-technical team-in dəyişlik etməsinə imkan verir.
