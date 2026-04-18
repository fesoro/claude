<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PAYMENT PROCESSED EVENT — Laravel Event
 * =========================================
 *
 * Ödəniş emal edildikdən sonra dispatch olunur.
 * Ödəniş uğurlu (completed) və ya uğursuz (failed) ola bilər — hər iki halda fire olur.
 *
 * Bu event-i PaymentObserver::updated() dispatch edir —
 * yəni PaymentModel-in status sahəsi dəyişəndə avtomatik çağırılır.
 *
 * Listener-lər bu event-ə əsasən müxtəlif işlər görür:
 * - Ödəniş uğurludursa → sifarişin statusunu 'paid' edir
 * - Ödəniş uğursuzdursa → sifarişi ləğv edə bilər
 *
 * $status DƏYƏRLƏR:
 * - 'completed' — ödəniş uğurla tamamlandı
 * - 'failed' — ödəniş uğursuz oldu (kart rədd edildi, balans yoxdur və s.)
 */
class PaymentProcessedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $paymentId,
        public readonly string $orderId,
        public readonly string $status,
        public readonly float $amount,
    ) {}
}
