<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\PaymentProcessedEvent;
use Illuminate\Support\Facades\Log;
use Src\Payment\Infrastructure\Models\PaymentModel;

/**
 * PAYMENT OBSERVER — Eloquent Observer
 * ======================================
 *
 * PaymentModel-in lifecycle event-lərini izləyir.
 *
 * ƏSAS VƏZİFƏSİ:
 * Ödəniş statusu dəyişdikdə (completed/failed) PaymentProcessedEvent dispatch edir.
 * Bu event UpdateOrderOnPaymentListener-i trigger edir →
 * sifariş statusu avtomatik yenilənir.
 *
 * TAM ZƏNCİR:
 * Payment gateway cavab verir → PaymentModel update olunur
 *   → PaymentObserver::updated()
 *     → PaymentProcessedEvent dispatch
 *       → UpdateOrderOnPaymentListener → OrderModel status yenilənir
 *         → OrderObserver::updating() → log yazılır
 *
 * Gördüyün kimi, Observer-lər və Listener-lər bir-birinə zəncir kimi bağlanır.
 * Hər biri yalnız ÖZ İŞİNİ görür — bu Single Responsibility prinsipidir.
 */
class PaymentObserver
{
    /**
     * updated() — Ödəniş yeniləndikdən SONRA çağırılır.
     *
     * Yalnız status dəyişdikdə VƏ son status completed/failed olduqda reaksiya göstərir.
     * Digər sahə dəyişiklikləri (məsələn transaction_id) bu event-i trigger ETMİR.
     */
    public function updated(PaymentModel $payment): void
    {
        /**
         * wasChanged() — updated() hook-unda istifadə olunur.
         * status sahəsi dəyişibmi yoxlayır (artıq DB-yə yazılıb).
         */
        if (!$payment->wasChanged('status')) {
            return;
        }

        $newStatus = $payment->status;

        /**
         * Yalnız son statuslar üçün event dispatch et.
         * 'pending' → 'processing' keçidində event lazım DEYİL.
         * 'processing' → 'completed' və ya 'failed' olduqda dispatch et.
         */
        if (!in_array($newStatus, ['completed', 'failed'], true)) {
            return;
        }

        Log::info('Ödəniş statusu dəyişdi — event dispatch edilir', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'status' => $newStatus,
        ]);

        PaymentProcessedEvent::dispatch(
            paymentId: $payment->id,
            orderId: $payment->order_id,
            status: $newStatus,
            amount: (float) $payment->amount,
        );
    }
}
