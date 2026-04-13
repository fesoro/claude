<?php

declare(strict_types=1);

namespace Src\Notification\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Notification\Application\Listeners\LowStockListener;
use Src\Notification\Application\Listeners\OrderCreatedListener;
use Src\Notification\Application\Listeners\PaymentCompletedListener;
use Src\Notification\Application\Listeners\PaymentFailedListener;
use Src\Notification\Application\Services\NotificationApplicationService;
use Src\Notification\Domain\Services\NotificationServiceInterface;
use Src\Notification\Infrastructure\Channels\EmailChannel;
use Src\Notification\Infrastructure\Channels\NotificationChannelFactory;
use Src\Notification\Infrastructure\Channels\SmsChannel;

/**
 * NOTIFICATION SERVICE PROVIDER (Laravel Service Provider)
 * ==========================================================
 * Bu class Notification Bounded Context-in bütün service-lərini,
 * listener-lərini və binding-lərini Laravel-ə qeydiyyatdan keçirir.
 *
 * SERVICE PROVIDER NƏDİR?
 * ========================
 * Service Provider — Laravel-in "başlanğıc nöqtəsi"dir.
 * Tətbiq başlayanda Laravel bütün provider-ləri yükləyir.
 * Hər provider öz modulunun class-larını və konfiqurasiyalarını qeyd edir.
 *
 * Real həyat nümunəsi:
 * - Yeni işçi işə başlayır (modul aktivləşir).
 * - HR departamenti (Service Provider) onu sistemə qeyd edir:
 *   - Kimlik kartı verir (binding).
 *   - Hansı şöbədə işləyəcəyini təyin edir (listener qeydiyyatı).
 *   - Lazımi alətləri verir (dependency injection).
 *
 * OBSERVER PATTERN — LİSTENER QEYDİYYATI:
 * =========================================
 * Observer Pattern-in vacib hissəsi: observer-ləri subject-ə qeyd etmək.
 * Laravel-də bu, EventServiceProvider və ya ServiceProvider-da edilir.
 *
 * Bu provider-də:
 * - OrderCreatedIntegrationEvent → OrderCreatedListener
 * - PaymentCompletedIntegrationEvent → PaymentCompletedListener
 * - PaymentFailedIntegrationEvent → PaymentFailedListener
 * - LowStockIntegrationEvent → LowStockListener
 *
 * RabbitMQ queue worker event-i oxuduqda, Laravel avtomatik
 * uyğun listener-i tapıb handle() metodunu çağırır.
 *
 * DEPENDENCY INJECTION BINDING-LƏR:
 * ===================================
 * Laravel Service Container-ə deyirik:
 * - "NotificationServiceInterface istədikdə, mənə bu implementation-ı ver."
 * - Bu, Dependency Inversion Principle-i təmin edir.
 * - Test zamanı fərqli implementation (mock) vermək asanlaşır.
 *
 * AXIN:
 * 1. Laravel başlayır → bu provider-in register() metodu çağırılır.
 * 2. Binding-lər qeyd olunur (interface → implementation).
 * 3. boot() metodu çağırılır — listener-lər event-lərə bağlanır.
 * 4. RabbitMQ-dan event gəldikdə Laravel uyğun listener-i çağırır.
 */
class NotificationServiceProvider extends ServiceProvider
{
    /**
     * EVENT → LISTENER XƏRİTƏSİ (Observer Pattern qeydiyyatı)
     * ==========================================================
     * Bu xəritə hansı event-in hansı listener tərəfindən dinlənildiyini göstərir.
     *
     * Observer Pattern-də:
     * - Sol tərəf (key) = Subject-in göndərdiyi event.
     * - Sağ tərəf (value) = Observer-lər (listener-lər) siyahısı.
     *
     * Bir event-ə birdən çox listener bağlana bilər:
     * OrderCreatedIntegrationEvent → [EmailListener, SmsListener, LogListener]
     *
     * Bu Integration Event-lərdir — RabbitMQ vasitəsilə digər context-lərdən gəlir.
     * Namespace string olaraq yazılır çünki həmin class-lar bu context-də mövcud deyil
     * (digər bounded context-ə aiddir). Bu, loose coupling təmin edir.
     */
    private array $listen = [
        // Order Context-dən gələn event → sifariş təsdiq bildirişi göndər.
        'Src\Order\Domain\Events\OrderCreatedIntegrationEvent' => [
            OrderCreatedListener::class,
        ],

        // Payment Context-dən gələn event-lər.
        'Src\Payment\Domain\Events\PaymentCompletedIntegrationEvent' => [
            PaymentCompletedListener::class,
        ],
        'Src\Payment\Domain\Events\PaymentFailedIntegrationEvent' => [
            PaymentFailedListener::class,
        ],

        // Inventory Context-dən gələn event → admin-ə stok xəbərdarlığı.
        'Src\Inventory\Domain\Events\LowStockIntegrationEvent' => [
            LowStockListener::class,
        ],
    ];

    /**
     * REGISTER METODU — Binding-ləri qeyd edir.
     * ============================================
     * Bu metod Laravel başlayanda çağırılır.
     * Service Container-ə deyirik: "Bu interface istədikdə, bu class-ı ver."
     *
     * Dependency Injection mexanizmi:
     * - Hər hansı class konstruktorunda NotificationServiceInterface istəsə,
     *   Laravel avtomatik olaraq burada bind etdiyimiz implementation-ı verir.
     * - Bu "auto-wiring" adlanır — Laravel type-hint-ə baxıb lazımi class-ı tapır.
     */
    public function register(): void
    {
        // ─── CHANNEL-LƏRİ SİNGLETON OLARAQ QEYDİYYAT ───
        // Singleton — tətbiq boyu yalnız BİR instansiya yaradılır.
        // Hər dəfə yeni obyekt yaratmaq əvəzinə, eyni obyekt istifadə olunur.
        // Bu, performansı yaxşılaşdırır və resurs qənaəti edir.

        $this->app->singleton(EmailChannel::class, function () {
            return new EmailChannel();
        });

        $this->app->singleton(SmsChannel::class, function () {
            return new SmsChannel();
        });

        // ─── FACTORY QEYDİYYATI ───
        // NotificationChannelFactory — düzgün kanalı yaradan factory.
        // Singleton olaraq qeyd edirik — tətbiq boyu bir instansiya kifayətdir.
        $this->app->singleton(NotificationChannelFactory::class, function ($app) {
            return new NotificationChannelFactory(
                emailChannel: $app->make(EmailChannel::class),
                smsChannel: $app->make(SmsChannel::class),
            );
        });

        // ─── INTERFACE → IMPLEMENTATION BINDING ───
        // NotificationServiceInterface istədikdə, Factory vasitəsilə
        // NotificationChannelFactory-ni istifadə edən wrapper veririk.
        //
        // Burada default kanal olaraq EmailChannel bind edirik.
        // Real göndərmə zamanı NotificationApplicationService Factory-dən
        // düzgün kanalı alır. Bu binding yalnız birbaşa interface injection üçündür.
        $this->app->bind(
            NotificationServiceInterface::class,
            EmailChannel::class,
        );

        // ─── APPLICATION SERVICE QEYDİYYATI ───
        // NotificationApplicationService — listener-lərin istifadə etdiyi service.
        $this->app->singleton(NotificationApplicationService::class, function ($app) {
            return new NotificationApplicationService(
                notificationService: $app->make(NotificationServiceInterface::class),
            );
        });
    }

    /**
     * BOOT METODU — Listener-ləri event-lərə bağlayır.
     * ===================================================
     * Bu metod register()-dən sonra çağırılır.
     * Bütün binding-lər artıq mövcuddur, indi listener-ləri qeyd edə bilərik.
     *
     * Observer Pattern qeydiyyatı burada baş verir:
     * - Hər event üçün uyğun listener-ləri Laravel Event Dispatcher-ə əlavə edirik.
     * - RabbitMQ-dan event gəldikdə, Dispatcher avtomatik uyğun listener-i çağırır.
     *
     * Event Dispatcher necə işləyir?
     * 1. Bu metod: "OrderCreatedIntegrationEvent gəldikdə OrderCreatedListener-i çağır" deyir.
     * 2. RabbitMQ worker event-i oxuyur.
     * 3. Laravel Event Dispatcher event-in class adına baxır.
     * 4. $listen xəritəsindən uyğun listener-i tapır.
     * 5. Listener-in handle() metodunu çağırır.
     */
    public function boot(): void
    {
        // $listen xəritəsindəki hər event-listener cütünü qeyd edirik.
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                // Laravel Event fasadı ilə listener-i event-ə bağlayırıq.
                // Event::listen() — Observer Pattern-dəki "subscribe" əməliyyatıdır.
                \Illuminate\Support\Facades\Event::listen($event, $listener);
            }
        }
    }
}
