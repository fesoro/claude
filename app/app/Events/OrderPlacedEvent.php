<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ORDER PLACED EVENT — Laravel Event
 * ====================================
 *
 * ÖNƏMLİ: LARAVEL EVENT vs DOMAIN EVENT FƏRQI
 * =============================================
 *
 * Bu proyektdə İKİ növ event var — bunları qarışdırmaq olmaz:
 *
 * 1. DOMAIN EVENT (src/Order/Domain/Events/ qovluğunda):
 *    - OrderCreatedEvent, OrderPaidEvent və s.
 *    - Bounded context DAxİLİNDƏ istifadə olunur
 *    - Laravel-dən asılı DEYİL — sadə PHP class-dır
 *    - Domain Layer-in hissəsidir (biznes qaydaları)
 *    - Məsələn: Aggregate Root daxilində "sifariş yaradıldı" faktını qeyd edir
 *    - Yalnız eyni bounded context-dəki digər domain logic-lər üçün nəzərdə tutulub
 *
 * 2. LARAVEL EVENT (app/Events/ qovluğunda — BU FAYL):
 *    - Laravel-in Event/Listener sisteminə məxsusdur
 *    - Infrastructure Layer-in hissəsidir
 *    - Laravel ekosistemine körpü rolunu oynayır:
 *      → Broadcasting (WebSocket ilə real-time bildiriş)
 *      → Queued Listeners (arxa planda iş görən dinləyicilər)
 *      → Mail, Notification, Job dispatch və s.
 *    - Bounded context-LƏR ARASI əlaqə üçün istifadə olunur
 *
 * AXIN NÜMUNƏSİ:
 * OrderModel::create() → Observer::created() → OrderPlacedEvent dispatch
 *   → DispatchPaymentJobListener (ödəniş işə salınsın)
 *   → SendOrderConfirmationListener (email göndərilsin)
 *
 * TRAIT-LƏR:
 * - Dispatchable: OrderPlacedEvent::dispatch(...) yazmağa imkan verir
 * - InteractsWithSockets: Broadcasting üçün lazımdır (WebSocket)
 * - SerializesModels: Eloquent model-ləri queue-da düzgün serialize edir
 *   (model-in özünü deyil, ID-sini saxlayır — queue işləyəndə DB-dən yenidən oxuyur)
 */
class OrderPlacedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Event-in daşıdığı məlumatlar.
     *
     * public property — Listener-lər $event->orderId şəklində birbaşa oxuya bilir.
     * Laravel event-lərində data adətən constructor-dan public property-lərə yazılır.
     *
     * NƏYƏ MODEL YOX, ID?
     * Model ötürsək, queue-da serialize problemi ola bilər.
     * ID ötürüb, Listener daxilində DB-dən yenidən oxumaq daha etibarlıdır.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $userId,
        public readonly float $totalAmount,
    ) {}
}
