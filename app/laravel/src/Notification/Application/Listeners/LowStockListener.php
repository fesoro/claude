<?php

declare(strict_types=1);

namespace Src\Notification\Application\Listeners;

use Src\Notification\Application\Services\NotificationApplicationService;

/**
 * LOW STOCK LISTENER (Observer Pattern - Stok azalma xəbərdarlığı)
 * ==================================================================
 * Bu listener LowStockIntegrationEvent-i dinləyir.
 * Məhsulun stoku az olduqda admin-ə (idarəçiyə) bildiriş göndərir.
 *
 * OBSERVER PATTERN KONTEKSTİNDƏ:
 * ================================
 * - Subject: Inventory/Catalog Bounded Context (anbar modulu)
 * - Event: LowStockIntegrationEvent (stok azaldı hadisəsi)
 * - Observer: Bu class — LowStockListener
 * - Reaksiya: Admin-ə email VƏ SMS göndərilir (iki kanal!)
 *
 * AXIN (Flow):
 * ┌──────────────────┐    RabbitMQ    ┌─────────────────────────┐
 * │ Inventory Context │──────────────▶│  Notification Context    │
 * │                   │   (asinxron)  │                          │
 * │ LowStock          │               │  LowStockListener        │
 * │ IntegrationEvent  │               │  (bu class)              │
 * └──────────────────┘               └─────────────────────────┘
 *
 * 1. Məhsul satılır və stok azalır (Inventory Context-də).
 * 2. Stok minimum həddən aşağı düşür.
 * 3. LowStockIntegrationEvent yaranır.
 * 4. RabbitMQ vasitəsilə bu listener-ə çatdırılır.
 * 5. Admin-ə həm email, həm SMS göndərilir.
 *
 * ÇOXKANALLI BİLDİRİŞ (Multi-Channel Notification):
 * ===================================================
 * Bu listener digərlərindən fərqlidir çünki İKİ kanal istifadə edir:
 * - Email: Ətraflı məlumat üçün (hansı məhsul, neçə ədəd qalıb).
 * - SMS: Təcili xəbərdarlıq üçün (admin dərhal görsün).
 *
 * Real həyat nümunəsi:
 * - Bankdan gələn təcili bildiriş: həm SMS, həm email alırsan.
 * - Təcili olduğu üçün yalnız email kifayət deyil — SMS də göndərilir.
 *
 * BU LISTENER MÜŞTƏRİYƏ DEYIL, ADMİN-Ə GÖNDƏRİR:
 * ====================================================
 * Digər listener-lər müştəriyə göndərir (customerEmail).
 * Bu listener isə admin-ə göndərir — çünki stok idarəetməsi admin işidir.
 * Admin-in emaili konfiqurasiyadan (config/env) oxunur.
 *
 * RABBITMQ QUEUE:
 * ===============
 * - Exchange: e-commerce.events
 * - Routing Key: inventory.low_stock
 * - Queue: notification.inventory.low_stock
 */
class LowStockListener
{
    private string $adminEmail;
    private string $adminPhone;

    public function __construct(
        private readonly NotificationApplicationService $notificationService,
    ) {
        $this->adminEmail = config('notification.admin_email', 'admin@ecommerce.az');
        $this->adminPhone = config('notification.admin_phone', '+994501234567');
    }

    /**
     * Stok azalma event-ini emal edir — admin-ə email VƏ SMS göndərir.
     *
     * Bu metod iki dəfə notificationService.send() çağırır:
     * biri email üçün, biri SMS üçün. Bu, Observer Pattern-in
     * çevikliyini göstərir — bir event-ə istənilən reaksiya vermək olar.
     *
     * @param object $event RabbitMQ-dan gələn LowStockIntegrationEvent
     */
    public function handle(object $event): void
    {
        // Event-dən data çıxarırıq.
        $eventData = $event->toArray();

        // Məhsul məlumatlarını alırıq.
        $productId = $eventData['product_id'] ?? 'N/A';
        $productName = $eventData['product_name'] ?? 'Naməlum məhsul';
        $currentStock = $eventData['current_stock'] ?? 0;
        $minimumStock = $eventData['minimum_stock'] ?? 0;

        // ─── 1-ci KANAL: EMAIL (ətraflı məlumat) ───

        $emailSubject = "XƏBƏRDARLIQ: Az stok - {$productName}";

        $emailBody = "DİQQƏT: Məhsulun stoku az!\n\n"
            . "Məhsul: {$productName}\n"
            . "Məhsul ID: #{$productId}\n"
            . "Cari stok: {$currentStock} ədəd\n"
            . "Minimum stok həddi: {$minimumStock} ədəd\n\n"
            . "Zəhmət olmasa təchizatçıdan yeni sifariş verin.\n"
            . "Stok tükəndikdə müştərilər sifariş verə bilməyəcək.\n\n"
            . "Bu avtomatik bildirişdir.";

        // Email göndəririk — admin-ə ətraflı məlumat.
        $this->notificationService->send(
            to: $this->adminEmail,
            subject: $emailSubject,
            body: $emailBody,
            channel: 'email',
        );

        // ─── 2-ci KANAL: SMS (qısa, təcili xəbərdarlıq) ───

        $smsBody = "AZ STOK: {$productName} - cəmi {$currentStock} ədəd qalıb! "
            . "Təcili sifariş verin.";

        $this->notificationService->send(
            to: $this->adminPhone,
            subject: 'Az Stok Xəbərdarlığı',
            body: $smsBody,
            channel: 'sms',
        );
    }
}
