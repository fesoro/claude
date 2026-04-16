<?php

declare(strict_types=1);

namespace Src\Notification\Application\Listeners;

use Src\Notification\Application\Services\NotificationApplicationService;

/**
 * ORDER CREATED LISTENER (Observer Pattern - Əsas nümunə)
 * =========================================================
 *
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                        OBSERVER PATTERN (Müşahidəçi Nümunəsi)             ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║                                                                            ║
 * ║  Observer Pattern — bir obyektdə (Subject) dəyişiklik olduqda,            ║
 * ║  onu izləyən bütün obyektləri (Observers) avtomatik xəbərdar edir.        ║
 * ║                                                                            ║
 * ║  REAL HƏYAT NÜMUNƏLƏRİ:                                                  ║
 * ║  ─────────────────────────                                                 ║
 * ║  1. YouTube Abunəliyi:                                                     ║
 * ║     - Kanal = Subject (mövzu, izlənilən)                                  ║
 * ║     - Abunəçilər = Observers (müşahidəçilər)                              ║
 * ║     - Yeni video = Event (hadisə)                                          ║
 * ║     - Bildiriş = Notification (xəbərdarlıq)                               ║
 * ║     Kanal video yükləyir → bütün abunəçilərə bildiriş gedir.             ║
 * ║                                                                            ║
 * ║  2. Qəzet Abunəliyi:                                                      ║
 * ║     - Qəzet redaksiyası = Subject                                          ║
 * ║     - Abunəçilər = Observers                                               ║
 * ║     - Yeni buraxılış = Event                                               ║
 * ║     Yeni qəzet çıxır → bütün abunəçilərin qapısına gedir.                ║
 * ║                                                                            ║
 * ║  3. Hava Proqnozu:                                                         ║
 * ║     - Meteorologiya stansiyası = Subject                                   ║
 * ║     - Televiziya, radio, tətbiq = Observers                                ║
 * ║     - Hava dəyişikliyi = Event                                             ║
 * ║     Hava dəyişir → bütün kanallar yenilənir.                              ║
 * ║                                                                            ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║                                                                            ║
 * ║  BU PROYEKTDƏ OBSERVER PATTERN:                                            ║
 * ║  ──────────────────────────────────                                         ║
 * ║                                                                            ║
 * ║  Subject (Mövzu): Order Bounded Context                                    ║
 * ║  Event (Hadisə):  OrderCreatedIntegrationEvent                             ║
 * ║  Observer (Müşahidəçi): Bu class — OrderCreatedListener                    ║
 * ║  Reaksiya: Müştəriyə təsdiq emaili göndərilir                             ║
 * ║                                                                            ║
 * ║  AXIN (Flow):                                                              ║
 * ║  ┌─────────────────┐    RabbitMQ    ┌──────────────────────┐               ║
 * ║  │  Order Context   │──────────────▶│  Notification Context │              ║
 * ║  │                  │   (asinxron)  │                       │              ║
 * ║  │ OrderCreated     │               │ OrderCreatedListener  │              ║
 * ║  │ IntegrationEvent │               │ (bu class)            │              ║
 * ║  └─────────────────┘               └──────────────────────┘               ║
 * ║                                                                            ║
 * ║  1. Müştəri sifariş yaradır (Order Context-də)                            ║
 * ║  2. OrderCreatedIntegrationEvent yaranır                                   ║
 * ║  3. Event RabbitMQ-ya göndərilir (asinxron — gözləmədən)                  ║
 * ║  4. Notification Context RabbitMQ-dan event-i oxuyur                       ║
 * ║  5. OrderCreatedListener event-i emal edir                                 ║
 * ║  6. Müştəriyə təsdiq emaili göndərilir                                    ║
 * ║                                                                            ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║                                                                            ║
 * ║  OBSERVER PATTERN-İN ÜSTÜNLÜKLƏRİ:                                       ║
 * ║  ────────────────────────────────────                                       ║
 * ║  1. Loose Coupling (Zəif bağlılıq):                                       ║
 * ║     - Order modulu Notification modulunu tanımır.                          ║
 * ║     - Sadəcə event göndərir, kim dinləyir — bilmir.                       ║
 * ║                                                                            ║
 * ║  2. Open/Closed Principle (Açıq/Qapalı prinsipi):                         ║
 * ║     - Yeni observer (listener) əlavə etmək üçün                           ║
 * ║       mövcud kodu dəyişmək lazım deyil.                                   ║
 * ║     - Məsələn: SMS listener əlavə et — Order moduluna toxunma.            ║
 * ║                                                                            ║
 * ║  3. Scalability (Miqyaslanma):                                             ║
 * ║     - Bir event-ə 1 listener və ya 100 listener əlavə edə bilərsən.      ║
 * ║     - RabbitMQ bunu asinxron idarə edir.                                  ║
 * ║                                                                            ║
 * ║  OBSERVER PATTERN-İN ÇATIŞMAZLIQLARI:                                     ║
 * ║  ──────────────────────────────────────                                     ║
 * ║  1. Debugging çətinliyi:                                                   ║
 * ║     - Event göndərilir, amma kim dinləyir? Kodu izləmək çətin ola bilər.  ║
 * ║  2. Sıralama problemi:                                                     ║
 * ║     - Listener-lərin işləmə sırası həmişə aydın olmur.                    ║
 * ║  3. Performans:                                                            ║
 * ║     - Çox listener olsa, hamısının işləməsi vaxt ala bilər.               ║
 * ║                                                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 *
 * RABBITMQ ilə İŞLƏMƏ:
 * =====================
 * RabbitMQ — mesaj broker-dir (mesaj vasitəçisi).
 * Modullar arası mesaj (event) göndərmək üçün istifadə olunur.
 *
 * Necə işləyir?
 * - Producer (istehsalçı): Event-i RabbitMQ-ya göndərən modul. (Order Context)
 * - Queue (növbə): Mesajların saxlandığı yer. (notification.order.created queue)
 * - Consumer (istehlakçı): Queue-dan mesaj oxuyan modul. (Bu Listener)
 *
 * Nəyə RabbitMQ?
 * - Asinxron: Order modulu gözləmir ki, email göndərilsin.
 * - Etibarlılıq: Mesaj itmir, queue-da saxlanır.
 * - Retry: Xəta olarsa, mesaj yenidən göndərilə bilər.
 *
 * Laravel-də bu listener ShouldQueue interface-i ilə asinxron işləyir.
 * RabbitMQ driver kimi konfiqurasiya olunduqda, Laravel avtomatik
 * mesajları RabbitMQ-ya göndərir və queue worker-lər emal edir.
 */
class OrderCreatedListener
{
    /**
     * Dependency Injection — NotificationApplicationService konstruktor vasitəsilə verilir.
     *
     * Bu, SOLID prinsiplərindən "D" — Dependency Inversion-dur:
     * - Listener özü service yaratmır (new etmir).
     * - Laravel Service Container avtomatik inject edir.
     * - Test zamanı mock (saxta) service vermək asanlaşır.
     */
    public function __construct(
        private readonly NotificationApplicationService $notificationService,
    ) {
    }

    /**
     * Event-i emal edir — müştəriyə sifariş təsdiq emaili göndərir.
     *
     * Bu metod Observer Pattern-dəki "update" metodunun ekvivalentidir:
     * - Klassik Observer Pattern-də: observer.update(data)
     * - Laravel-də: listener.handle(event)
     * - Funksionallıq eynidir: event gəlir, listener reaksiya verir.
     *
     * @param object $event RabbitMQ-dan gələn OrderCreatedIntegrationEvent.
     *                      Object tipi istifadə olunur çünki event digər context-dən gəlir
     *                      və biz həmin class-dan birbaşa asılı olmaq istəmirik (loose coupling).
     *
     * AXIN:
     * 1. RabbitMQ-dan event gəlir (JSON → PHP object).
     * 2. Event-dən orderId, customerEmail, totalAmount çıxarılır.
     * 3. Email mətni hazırlanır.
     * 4. NotificationApplicationService vasitəsilə email göndərilir.
     *
     * @throws \Src\Shared\Domain\Exceptions\DomainException Bildiriş göndərilə bilmədikdə
     */
    public function handle(object $event): void
    {
        // Event-dən lazımi data-ları çıxarırıq.
        // toArray() metodu event-in data-sını array formatında qaytarır.
        $eventData = $event->toArray();

        // Sifariş məlumatlarını event-dən alırıq.
        $orderId = $eventData['order_id'] ?? 'N/A';
        $totalAmount = $eventData['total_amount'] ?? 0;

        /**
         * MÜŞTƏRİ EMAİL-İNİ TAPMAQ
         * ===========================
         * Integration Event-də customer_email OLMUR — çünki bu, User context-ə aiddir.
         * Bounded context-lər arasında yalnız ID paylaşılır, şəxsi data deyil.
         * Bu, DDD-nin "minimal data in integration events" prinsipinə uyğundur.
         *
         * Email-i user_id vasitəsilə öz DB-mizdən tapırıq.
         * Əgər tapılmasa — bildiriş göndərilə bilməz, log yazıb skip edirik.
         */
        $userId = $eventData['user_id'] ?? null;
        $customerEmail = '';

        if ($userId !== null) {
            $user = \Src\User\Infrastructure\Models\UserModel::find($userId);
            $customerEmail = $user?->email ?? '';
        }

        if (empty($customerEmail)) {
            \Illuminate\Support\Facades\Log::warning('Müştəri email-i tapılmadı, bildiriş göndərilə bilmir', [
                'order_id' => $orderId,
                'user_id' => $userId,
            ]);
            return;
        }

        // Email mövzusunu hazırlayırıq.
        $subject = "Sifariş təsdiqi - #{$orderId}";

        // Email məzmununu hazırlayırıq.
        // Müştəriyə sifarişinin uğurla yaradıldığını bildiririk.
        $body = "Hörmətli müştəri,\n\n"
            . "Sifarişiniz #{$orderId} uğurla yaradıldı.\n"
            . "Ümumi məbləğ: {$totalAmount} AZN\n\n"
            . "Sifarişinizin statusu dəyişdikdə sizə bildiriş göndəriləcək.\n\n"
            . "Təşəkkürlər!";

        // NotificationApplicationService vasitəsilə email göndəririk.
        // "email" kanalı istifadə olunur çünki bu sifariş təsdiq bildirişidir.
        $this->notificationService->send(
            to: $customerEmail,
            subject: $subject,
            body: $body,
            channel: 'email',
        );
    }
}
