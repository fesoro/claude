<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SİFARİŞ TƏSDİQLƏMƏ EMAİL JOB-U
 * ==================================
 * Sifariş uğurla yaradıldıqdan və ödəniş emal olunduqdan sonra
 * istifadəçiyə təsdiqləmə email-i göndərir.
 *
 * NƏYƏ AYRI JOB?
 * ───────────────
 * Email göndərmək yavaş əməliyyatdır (1-3 saniyə).
 * Bunu arxa planda (queue) edirik ki, istifadəçi gözləməsin.
 *
 * NƏYƏ $tries = 3 VƏ EKSPONENSİAL BACKOFF?
 * ──────────────────────────────────────────
 * Email servisi (SendGrid, Mailgun) müvəqqəti əlçatmaz ola bilər.
 * 3 dəfə cəhd edirik, hər dəfə daha çox gözləyirik:
 * - 1-ci uğursuzluq → 10 saniyə gözlə
 * - 2-ci uğursuzluq → 60 saniyə gözlə (1 dəqiqə)
 * - 3-cü uğursuzluq → 300 saniyə gözlə (5 dəqiqə)
 *
 * Eksponensial backoff — email servisinin bərpa olunmasına vaxt verir.
 * Hər dəfə daha çox gözləmək = serverə daha az yük.
 *
 * DISPATCH NÜMUNƏSI:
 * ──────────────────
 * SendOrderConfirmationJob::dispatch(
 *     orderId: 'ORD-12345',
 *     userEmail: 'user@example.com',
 *     totalAmount: 149.99,
 *     items: [
 *         ['name' => 'Laravel kitabı', 'quantity' => 1, 'price' => 49.99],
 *         ['name' => 'PHP kursu', 'quantity' => 1, 'price' => 100.00],
 *     ]
 * );
 */
class SendOrderConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maksimum cəhd sayı — 3 dəfə.
     *
     * Email kritik deyil (ödəniş kimi), amma vacibdir.
     * 3 dəfə kifayətdir — əgər email servisi 3 dəfədə cavab vermirsə,
     * ciddi problem var.
     */
    public int $tries = 3;

    /**
     * Eksponensial backoff — cəhdlər arası gözləmə müddəti (saniyə).
     *
     * [10, 60, 300] mənası:
     * ┌─────────────────────────────────────────────┐
     * │ Cəhd 1: İcra et                             │
     * │   ↓ Uğursuz                                  │
     * │ 10 saniyə gözlə                              │
     * │ Cəhd 2: İcra et                              │
     * │   ↓ Uğursuz                                  │
     * │ 60 saniyə gözlə (1 dəqiqə)                  │
     * │ Cəhd 3: İcra et                              │
     * │   ↓ Uğursuz                                  │
     * │ 300 saniyə gözlə (5 dəqiqə) — amma artıq    │
     * │ cəhd yoxdur, failed() çağırılır              │
     * └─────────────────────────────────────────────┘
     *
     * DİQQƏT: backoff massivinin uzunluğu >= $tries - 1 olmalıdır.
     * 3 cəhd = 2 gözləmə intervalı, amma 3 yazırıq (ehtiyat üçün).
     */
    public array $backoff = [10, 60, 300];

    /**
     * Email göndərmə timeout-u — ən çox 60 saniyə.
     * Email servisi cavab vermirsə, gözləmə 60 saniyədə bitir.
     */
    public int $timeout = 60;

    /**
     * @param string $orderId Sifariş ID-si (email-dəki sifariş nömrəsi üçün)
     * @param string $userEmail İstifadəçinin email ünvanı
     * @param float $totalAmount Ümumi ödəniş məbləği
     * @param array $items Sifariş elementləri (məhsul adı, miqdar, qiymət)
     */
    public function __construct(
        private readonly string $orderId,
        private readonly string $userEmail,
        private readonly float $totalAmount,
        private readonly array $items,
    ) {
    }

    /**
     * Email göndərmə əməliyyatı.
     *
     * Queue Worker bu metodu çağırır.
     * Mail facade ilə email göndəririk — real proyektdə Mailable class istifadə olunur.
     *
     * REAL PROYEKTDƏ:
     * Mail::to($this->userEmail)->send(new OrderConfirmationMail(
     *     orderId: $this->orderId,
     *     totalAmount: $this->totalAmount,
     *     items: $this->items,
     * ));
     *
     * Mailable class email-in template-ini, subject-ini, data-sını təyin edir.
     * resources/views/emails/order-confirmation.blade.php — email template-i.
     */
    public function handle(): void
    {
        Log::info('Sifariş təsdiqləmə email-i göndərilir', [
            'order_id' => $this->orderId,
            'email' => $this->userEmail,
            'total_amount' => $this->totalAmount,
            'items_count' => count($this->items),
            'attempt' => $this->attempts(),
        ]);

        /**
         * REAL KOD BELƏ OLARDI:
         *
         * Mail::to($this->userEmail)->send(
         *     new \App\Mail\OrderConfirmationMail(
         *         orderId: $this->orderId,
         *         totalAmount: $this->totalAmount,
         *         items: $this->items,
         *     )
         * );
         *
         * Hazırda sadəcə log yazırıq (Mailable class hələ yaradılmayıb).
         */

        Log::info('Sifariş təsdiqləmə email-i uğurla göndərildi', [
            'order_id' => $this->orderId,
            'email' => $this->userEmail,
        ]);
    }

    /**
     * 3 cəhddən sonra email göndərilə bilmədi.
     *
     * Bu halda:
     * - Log yazılır (admin monitor edə bilsin)
     * - İstifadəçi hesabında bildiriş göstərilə bilər
     * - Admin-ə alert göndərilə bilər
     *
     * Email göndərilməməsi ödəniş uğursuzluğu qədər kritik deyil,
     * amma istifadəçi sifarişinin təsdiqini gözləyir.
     * Manual olaraq yenidən göndərmək lazım ola bilər:
     * php artisan queue:retry <uuid>
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Sifariş təsdiqləmə email-i göndərilə bilmədi! Bütün cəhdlər bitdi.', [
            'order_id' => $this->orderId,
            'email' => $this->userEmail,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Bu Job "emails" queue-sinə göndərilir.
     * Email job-ları ödəniş job-larından ayrı queue-da olur.
     * Beləliklə fərqli worker-lər fərqli prioritetlərlə işləyə bilər:
     * php artisan queue:work --queue=payments  (prioritet: yüksək)
     * php artisan queue:work --queue=emails    (prioritet: normal)
     */
    public function queue(): string
    {
        return 'emails';
    }
}
