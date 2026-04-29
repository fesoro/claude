<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * SEND ORDER CONFIRMATION LISTENER
 * ==================================
 *
 * OrderPlacedEvent baş verdikdə müştəriyə sifariş təsdiq email-i göndərir.
 *
 * ShouldQueue — çünki email göndərmək yavaş əməliyyatdır (1-5 saniyə).
 * İstifadəçi gözləməməlidir — arxa planda göndərilir.
 *
 * BİR EVENT, İKİ LISTENER:
 * OrderPlacedEvent-ə iki listener bağlıdır:
 * 1. DispatchPaymentJobListener — ödəniş prosesini başladır
 * 2. SendOrderConfirmationListener — email göndərir (BU FAYL)
 *
 * Hər ikisi MÜSTƏQİL işləyir — biri uğursuz olsa, digərinə təsir etmir.
 * Bu "Separation of Concerns" prinsipidir — hər listener bir iş görür.
 */
class SendOrderConfirmationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Email göndərmə queue-su.
     * 'emails' queue-su ayrıca worker ilə idarə oluna bilər.
     */
    public string $queue = 'emails';

    public int $tries = 3;

    /**
     * Cəhdlər arasında gözləmə (saniyə).
     * Email server müvəqqəti əlçatmaz ola bilər — 60 saniyə gözlə, yenidən cəhd et.
     */
    public int $backoff = 60;

    /**
     * Sifariş yaradılanda müştəriyə təsdiq email-i göndərir.
     *
     * AXIN:
     * 1. OrderPlacedEvent baş verir (AppServiceProvider-da qeydiyyatdan keçib).
     * 2. Laravel Event Dispatcher bu listener-i çağırır.
     * 3. ShouldQueue olduğu üçün bu metod arxa planda (queue worker-də) işləyir.
     * 4. İstifadəçi gözləmir — cavab dərhal qayıdır.
     *
     * Mail::to()->queue() vs Mail::to()->send() fərqi:
     * - send(): Elə indi göndərir (sinxron, yavaş).
     * - queue(): Mail-i queue-yə yazır, worker göndərir (asinxron, sürətli).
     * Burada queue() istifadə edirik çünki listener onsuz da queue-dadır,
     * amma Mail::queue() əlavə olaraq mail-i ayrı job kimi idarə edir.
     */
    public function handle(OrderPlacedEvent $event): void
    {
        Log::info('Sifariş təsdiq email-i göndərilir', [
            'order_id' => $event->orderId,
            'user_id' => $event->userId,
        ]);

        $user = \Src\User\Infrastructure\Models\UserModel::find($event->userId);

        if ($user === null) {
            Log::warning('İstifadəçi tapılmadı, email göndərilə bilmir', [
                'user_id' => $event->userId,
            ]);
            return;
        }

        \Illuminate\Support\Facades\Mail::to($user->email)->queue(
            new \App\Mail\OrderConfirmationMail(
                orderId: $event->orderId,
                userEmail: $user->email,
                totalAmount: $event->totalAmount,
                items: [], // Order items ayrıca sorğu ilə alına bilər
            ),
        );

        Log::info('Sifariş təsdiq email-i queue-yə əlavə olundu', [
            'order_id' => $event->orderId,
            'email' => $user->email,
        ]);
    }

    public function failed(OrderPlacedEvent $event, \Throwable $exception): void
    {
        Log::error('Sifariş təsdiq email-i göndərilə bilmədi', [
            'order_id' => $event->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
