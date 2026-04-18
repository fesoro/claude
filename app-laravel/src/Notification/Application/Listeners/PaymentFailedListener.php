<?php

declare(strict_types=1);

namespace Src\Notification\Application\Listeners;

use Src\Notification\Application\Services\NotificationApplicationService;

/**
 * PAYMENT FAILED LISTENER (Observer Pattern - Ödəniş uğursuz oldu)
 * ==================================================================
 * Bu listener PaymentFailedIntegrationEvent-i dinləyir.
 * Ödəniş uğursuz olduqda müştəriyə xəbərdarlıq bildirişi göndərir.
 *
 * OBSERVER PATTERN KONTEKSTİNDƏ:
 * ================================
 * - Subject: Payment Bounded Context
 * - Event: PaymentFailedIntegrationEvent (ödəniş uğursuz hadisəsi)
 * - Observer: Bu class — PaymentFailedListener
 * - Reaksiya: Müştəriyə ödəniş uğursuzluğu haqqında email göndərilir
 *
 * AXIN (Flow):
 * ┌──────────────────┐    RabbitMQ    ┌─────────────────────────┐
 * │ Payment Context   │──────────────▶│  Notification Context    │
 * │                   │   (asinxron)  │                          │
 * │ PaymentFailed     │               │  PaymentFailedListener   │
 * │ IntegrationEvent  │               │  (bu class)              │
 * └──────────────────┘               └─────────────────────────┘
 *
 * 1. Müştəri ödəniş etməyə çalışır.
 * 2. Bank kartı rədd edir və ya xəta baş verir.
 * 3. PaymentFailedIntegrationEvent yaranır.
 * 4. RabbitMQ vasitəsilə bu listener-ə çatdırılır.
 * 5. Müştəriyə "ödəniş uğursuz oldu" emaili göndərilir.
 *
 * ERROR HANDLING (Xəta idarəetməsi):
 * ====================================
 * Ödəniş uğursuz olduqda müştəriyə bunları bildirmək vacibdir:
 * - Ödənişin nəyə görə uğursuz olduğu (mümkün olan halda).
 * - Yenidən cəhd etmək imkanı.
 * - Dəstək xidməti ilə əlaqə məlumatları.
 *
 * RABBITMQ RETRY MEXANİZMİ:
 * ===========================
 * Əgər bu listener-in özü xəta verərsə (məsələn email server cavab vermir):
 * - RabbitMQ mesajı yenidən queue-ya qaytarır (requeue).
 * - Müəyyən sayda təkrar cəhd edilir (retry count).
 * - Bütün cəhdlər uğursuz olarsa, Dead Letter Queue-ya (DLQ) göndərilir.
 * - DLQ — "ölü məktub qutusu"dur, uğursuz mesajlar orada saxlanır.
 */
class PaymentFailedListener
{
    /**
     * Dependency Injection — service konstruktor vasitəsilə verilir.
     */
    public function __construct(
        private readonly NotificationApplicationService $notificationService,
    ) {
    }

    /**
     * Ödəniş uğursuzluğu event-ini emal edir.
     *
     * @param object $event RabbitMQ-dan gələn PaymentFailedIntegrationEvent
     */
    public function handle(object $event): void
    {
        // Event-dən data çıxarırıq.
        $eventData = $event->toArray();

        // Ödəniş və sifariş məlumatlarını alırıq.
        $orderId = $eventData['order_id'] ?? 'N/A';
        $customerEmail = $eventData['customer_email'] ?? '';
        $reason = $eventData['failure_reason'] ?? 'Naməlum xəta';
        $amount = $eventData['amount'] ?? 0;

        // Email mövzusu — aydın şəkildə uğursuzluğu göstərir.
        $subject = "Ödəniş uğursuz oldu - Sifariş #{$orderId}";

        // Email məzmunu — müştəriyə vəziyyəti izah edir.
        $body = "Hörmətli müştəri,\n\n"
            . "Təəssüf ki, sifarişiniz #{$orderId} üçün ödəniş uğursuz oldu.\n\n"
            . "Məbləğ: {$amount} AZN\n"
            . "Səbəb: {$reason}\n\n"
            . "Zəhmət olmasa aşağıdakıları yoxlayın:\n"
            . "- Kart balansınız kifayət qədərdir?\n"
            . "- Kart məlumatları düzgündür?\n"
            . "- Bankınız onlayn ödənişlərə icazə verir?\n\n"
            . "Yenidən cəhd etmək üçün saytımıza daxil olun.\n"
            . "Problemləriniz davam edərsə, dəstək xidmətimizlə əlaqə saxlayın.\n\n"
            . "Təşəkkürlər!";

        // Email kanalı ilə bildiriş göndəririk.
        $this->notificationService->send(
            to: $customerEmail,
            subject: $subject,
            body: $body,
            channel: 'email',
        );
    }
}
