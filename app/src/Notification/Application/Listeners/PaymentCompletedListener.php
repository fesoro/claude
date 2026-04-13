<?php

declare(strict_types=1);

namespace Src\Notification\Application\Listeners;

use Src\Notification\Application\Services\NotificationApplicationService;

/**
 * PAYMENT COMPLETED LISTENER (Observer Pattern - Ödəniş tamamlandı)
 * ===================================================================
 * Bu listener PaymentCompletedIntegrationEvent-i dinləyir.
 * Ödəniş uğurla tamamlandıqda müştəriyə qəbz (receipt) emaili göndərir.
 *
 * OBSERVER PATTERN KONTEKSTİNDƏ:
 * ================================
 * - Subject: Payment Bounded Context (ödəniş modulu)
 * - Event: PaymentCompletedIntegrationEvent (ödəniş tamamlandı hadisəsi)
 * - Observer: Bu class — PaymentCompletedListener (müşahidəçi)
 * - Reaksiya: Müştəriyə ödəniş qəbzi göndərilir
 *
 * AXIN (Flow):
 * ┌──────────────────┐    RabbitMQ    ┌───────────────────────────┐
 * │ Payment Context   │──────────────▶│  Notification Context      │
 * │                   │   (asinxron)  │                            │
 * │ PaymentCompleted  │               │  PaymentCompletedListener  │
 * │ IntegrationEvent  │               │  (bu class)                │
 * └──────────────────┘               └───────────────────────────┘
 *
 * 1. Müştəri ödəniş edir (Payment Context-də).
 * 2. Ödəniş uğurlu olur → PaymentCompletedIntegrationEvent yaranır.
 * 3. Event RabbitMQ-ya göndərilir.
 * 4. Bu listener RabbitMQ-dan event-i oxuyur.
 * 5. Müştəriyə ödəniş qəbzi emaili göndərilir.
 *
 * RABBITMQ QUEUE KONFIQURASIYASI:
 * ================================
 * - Exchange: e-commerce.events (topic exchange)
 * - Routing Key: payment.completed
 * - Queue: notification.payment.completed
 * - Bu queue yalnız "payment.completed" routing key-li mesajları alır.
 *
 * BİR EVENT — ÇOX LISTENER:
 * ===========================
 * PaymentCompletedIntegrationEvent-i yalnız bu listener dinləmir.
 * Digər context-lər də dinləyə bilər:
 * - Order Context: Sifarişin statusunu "ödənildi" olaraq yeniləyir.
 * - Inventory Context: Məhsulları göndərmə üçün hazırlayır.
 * - Notification Context: Müştəriyə qəbz göndərir (BU CLASS).
 * Hər biri öz queue-suna malik olur — bir-birinə mane olmur.
 */
class PaymentCompletedListener
{
    /**
     * Dependency Injection — service konstruktor vasitəsilə verilir.
     * Laravel Service Container avtomatik inject edir.
     */
    public function __construct(
        private readonly NotificationApplicationService $notificationService,
    ) {
    }

    /**
     * Ödəniş tamamlandı event-ini emal edir.
     *
     * Observer Pattern-dəki "update" metodu:
     * - Event gəlir (notify olunuruq).
     * - Data-nı çıxarırıq.
     * - Reaksiya veririk (email göndəririk).
     *
     * @param object $event RabbitMQ-dan gələn PaymentCompletedIntegrationEvent
     */
    public function handle(object $event): void
    {
        // Event-dən data çıxarırıq.
        $eventData = $event->toArray();

        // Ödəniş məlumatlarını alırıq.
        $orderId = $eventData['order_id'] ?? 'N/A';
        $customerEmail = $eventData['customer_email'] ?? '';
        $amount = $eventData['amount'] ?? 0;
        $paymentMethod = $eventData['payment_method'] ?? 'unknown';

        // Email mövzusu.
        $subject = "Ödəniş qəbzi - Sifariş #{$orderId}";

        // Email məzmunu — ödəniş qəbzi.
        $body = "Hörmətli müştəri,\n\n"
            . "Ödənişiniz uğurla qəbul edildi.\n\n"
            . "Sifariş nömrəsi: #{$orderId}\n"
            . "Ödəniş məbləği: {$amount} AZN\n"
            . "Ödəniş üsulu: {$paymentMethod}\n\n"
            . "Sifarişiniz hazırlanmağa başlayacaq.\n\n"
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
