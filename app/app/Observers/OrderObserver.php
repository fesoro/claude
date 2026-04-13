<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\OrderPlacedEvent;
use Illuminate\Support\Facades\Log;
use Src\Order\Infrastructure\Models\OrderModel;

/**
 * ORDER OBSERVER — Eloquent Observer
 * ====================================
 *
 * OrderModel-in lifecycle event-lərini izləyir.
 *
 * ƏSAS VƏZİFƏSİ:
 * 1. created() — sifariş yaradılanda OrderPlacedEvent dispatch edir.
 *    Bu event Listener-ləri trigger edir:
 *    → DispatchPaymentJobListener (ödəniş başlat)
 *    → SendOrderConfirmationListener (email göndər)
 *
 * 2. updating() — sifariş statusu dəyişəndə log yazır.
 *    Audit trail üçün vacibdir — kim, nə vaxt, nə dəyişdirib.
 *
 * OBSERVER → EVENT → LISTENER ZƏNCİRİ:
 * =====================================
 * OrderModel::create([...])
 *   → OrderObserver::created()         (Observer)
 *     → OrderPlacedEvent::dispatch()   (Event)
 *       → DispatchPaymentJobListener   (Listener 1)
 *       → SendOrderConfirmationListener (Listener 2)
 *
 * Hər addım bir-birindən ASILIDIR, amma Listener-lər bir-birindən MÜSTƏQİLDİR.
 */
class OrderObserver
{
    /**
     * created() — Sifariş DB-yə yazıldıqdan SONRA çağırılır.
     *
     * NƏYƏ creating() deyil, created()?
     * creating() — hələ DB-yə yazılmayıb (ID yoxdur).
     * created()  — artıq DB-dədir, ID var → event dispatch etmək təhlükəsizdir.
     */
    public function created(OrderModel $order): void
    {
        Log::info('Yeni sifariş yaradıldı', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'total_amount' => $order->total_amount,
        ]);

        /**
         * Laravel Event dispatch — Observer-dən Listener dünyasına keçid.
         * Bu nöqtədə Observer işini bitirdi — bundan sonra Listener-lər işləyəcək.
         */
        OrderPlacedEvent::dispatch(
            orderId: $order->id,
            userId: $order->user_id,
            totalAmount: (float) $order->total_amount,
        );
    }

    /**
     * updating() — Sifariş yenilənməmişdən ƏVVƏL çağırılır.
     *
     * isDirty('status') — status sahəsi dəyişibmi? (hələ DB-yə yazılmayıb)
     * getOriginal('status') — dəyişiklikdən əvvəlki dəyər.
     * $order->status — yeni dəyər (hələ DB-yə yazılmayıb).
     *
     * Bu audit log üçün çox vacibdir:
     * "Sifariş #123 statusu pending → paid oldu"
     */
    public function updating(OrderModel $order): void
    {
        if ($order->isDirty('status')) {
            $oldStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            Log::info('Sifariş statusu dəyişir', [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }
    }
}
