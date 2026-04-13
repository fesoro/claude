<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ÖDƏNİŞ NƏTİCƏSİ BİLDİRİŞ JOB-U
 * ====================================
 * Ödəniş nəticəsini (uğurlu və ya uğursuz) istifadəçiyə bildirən Job.
 *
 * İSTİFADƏ SSENARILARI:
 * ─────────────────────
 * 1. Ödəniş uğurlu olduqda:
 *    SendPaymentNotificationJob::dispatch(
 *        orderId: 'ORD-123',
 *        userEmail: 'user@test.com',
 *        success: true,
 *        amount: 99.99,
 *        failureReason: null,  // uğurlu olduğu üçün null
 *    );
 *
 * 2. Ödəniş uğursuz olduqda:
 *    SendPaymentNotificationJob::dispatch(
 *        orderId: 'ORD-123',
 *        userEmail: 'user@test.com',
 *        success: false,
 *        amount: 99.99,
 *        failureReason: 'Kartda kifayət qədər vəsait yoxdur',
 *    );
 *
 * NƏYƏ AYRI JOB (SendOrderConfirmationJob-dan fərqli)?
 * ────────────────────────────────────────────────────
 * - SendOrderConfirmationJob: Sifariş detalları ilə təsdiqləmə email-i
 * - SendPaymentNotificationJob: Ödəniş nəticəsi bildirişi (uğurlu/uğursuz)
 * - Fərqli template, fərqli kontekst, fərqli vaxt göndərilir
 * - Single Responsibility Principle — hər Job bir iş görür
 */
class SendPaymentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Bildiriş göndərmə üçün 3 cəhd kifayətdir.
     */
    public int $tries = 3;

    /**
     * Cəhdlər arası gözləmə: 5, 15, 45 saniyə.
     * Bildiriş çox kritik deyil, ona görə qısa backoff.
     */
    public array $backoff = [5, 15, 45];

    /**
     * Timeout: 30 saniyə.
     * Bildiriş göndərmə sadə əməliyyatdır, 30 saniyə bəs edir.
     */
    public int $timeout = 30;

    /**
     * @param string $orderId Sifariş ID-si
     * @param string $userEmail İstifadəçinin email ünvanı
     * @param bool $success Ödəniş uğurludurmu?
     * @param float $amount Ödəniş məbləği
     * @param string|null $failureReason Uğursuzluq səbəbi (yalnız $success = false olduqda)
     */
    public function __construct(
        private readonly string $orderId,
        private readonly string $userEmail,
        private readonly bool $success,
        private readonly float $amount,
        private readonly ?string $failureReason = null,
    ) {
    }

    /**
     * Bildiriş göndərmə əməliyyatı.
     *
     * Ödəniş nəticəsinə görə fərqli email template göndərilir:
     * - Uğurlu: "Ödənişiniz qəbul olundu! Məbləğ: 99.99 AZN"
     * - Uğursuz: "Ödəniş uğursuz oldu. Səbəb: ..."
     *
     * REAL PROYEKTDƏ:
     * if ($this->success) {
     *     Mail::to($this->userEmail)->send(new PaymentSuccessMail(...));
     * } else {
     *     Mail::to($this->userEmail)->send(new PaymentFailedMail(...));
     * }
     *
     * Həmçinin push notification (mobil), SMS, və ya in-app notification
     * göndərilə bilər.
     */
    public function handle(): void
    {
        if ($this->success) {
            Log::info('Uğurlu ödəniş bildirişi göndərilir', [
                'order_id' => $this->orderId,
                'email' => $this->userEmail,
                'amount' => $this->amount,
            ]);

            /**
             * REAL KOD:
             * Mail::to($this->userEmail)->send(
             *     new \App\Mail\PaymentSuccessMail(
             *         orderId: $this->orderId,
             *         amount: $this->amount,
             *     )
             * );
             */
        } else {
            Log::warning('Uğursuz ödəniş bildirişi göndərilir', [
                'order_id' => $this->orderId,
                'email' => $this->userEmail,
                'amount' => $this->amount,
                'failure_reason' => $this->failureReason,
            ]);

            /**
             * REAL KOD:
             * Mail::to($this->userEmail)->send(
             *     new \App\Mail\PaymentFailedMail(
             *         orderId: $this->orderId,
             *         amount: $this->amount,
             *         reason: $this->failureReason,
             *     )
             * );
             */
        }
    }

    /**
     * Bildiriş göndərilə bilmədi.
     * Bu kritik deyil, amma log-a yazırıq ki, admin bilsin.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Ödəniş bildirişi göndərilə bilmədi', [
            'order_id' => $this->orderId,
            'email' => $this->userEmail,
            'success' => $this->success,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Bildirişlər "notifications" queue-sinə göndərilir.
     */
    public function queue(): string
    {
        return 'notifications';
    }
}
