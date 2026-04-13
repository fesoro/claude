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

    public function handle(OrderPlacedEvent $event): void
    {
        Log::info('Sifariş təsdiq email-i göndərilir', [
            'order_id' => $event->orderId,
            'user_id' => $event->userId,
        ]);

        /**
         * TODO: SendOrderConfirmationJob və ya Mailable dispatch et.
         *
         * Nümunə:
         * $user = UserModel::findOrFail($event->userId);
         * Mail::to($user->email)->queue(new OrderConfirmationMail($event->orderId));
         *
         * və ya:
         * SendOrderConfirmationJob::dispatch($event->orderId, $event->userId);
         */
        Log::info('SendOrderConfirmationJob dispatch edilməlidir (TODO)', [
            'order_id' => $event->orderId,
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
