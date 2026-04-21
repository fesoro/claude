<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentProcessedEvent;
use Illuminate\Support\Facades\Log;
use Src\Order\Infrastructure\Models\OrderModel;

/**
 * UPDATE ORDER ON PAYMENT LISTENER
 * ==================================
 *
 * PaymentProcessedEvent baş verdikdə sifarişin statusunu yeniləyir.
 *
 * Bu Listener ShouldQueue DEYİL — sinxron işləyir.
 * SƏBƏB: Sifariş statusunun dərhal yenilənməsi vacibdir.
 * İstifadəçi ödəniş etdikdən sonra sifarişin statusunu görmək istəyir.
 *
 * MƏNTİQ:
 * - Ödəniş uğurludursa (completed) → sifariş statusu 'paid' olur
 * - Ödəniş uğursuzdursa (failed) → sifariş statusu 'payment_failed' olur
 *
 * SINXRON LISTENER NƏ ZAMAN İSTİFADƏ EDİLİR?
 * - Nəticənin DƏRHal lazım olduğu hallarda
 * - Çox tez bitən əməliyyatlar üçün (DB update = 1-5ms)
 * - İstifadəçinin gözləməsinin məqbul olduğu hallarda
 */
class UpdateOrderOnPaymentListener
{
    public function handle(PaymentProcessedEvent $event): void
    {
        /** @var OrderModel|null $order */
        $order = OrderModel::find($event->orderId);

        if ($order === null) {
            Log::error('Ödəniş üçün sifariş tapılmadı', [
                'order_id' => $event->orderId,
                'payment_id' => $event->paymentId,
            ]);
            return;
        }

        /**
         * Ödəniş statusuna görə sifarişi yenilə.
         *
         * QEYD: Bu update OrderObserver::updating() hook-unu da trigger edəcək —
         * orada status dəyişikliyi log-lanır.
         */
        if ($event->status === 'completed') {
            $order->update(['status' => 'paid']);

            Log::info('Sifariş ödənildi', [
                'order_id' => $event->orderId,
                'amount' => $event->amount,
            ]);
        } elseif ($event->status === 'failed') {
            $order->update(['status' => 'payment_failed']);

            Log::warning('Sifariş ödənişi uğursuz oldu — sifariş ləğv edilə bilər', [
                'order_id' => $event->orderId,
                'payment_id' => $event->paymentId,
            ]);
        }
    }
}
