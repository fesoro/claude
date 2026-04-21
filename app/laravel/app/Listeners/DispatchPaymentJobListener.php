<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * DISPATCH PAYMENT JOB LISTENER
 * ===============================
 *
 * LISTENER NƏDİR? (ƏTRAFLİ İZAH)
 * ================================
 * Listener — müəyyən bir Event baş verdikdə avtomatik çağırılan class-dır.
 * "Dinləyici" deməkdir: event-i "dinləyir" və reaksiya göstərir.
 *
 * Real həyat analogiyası:
 * Event = "Qapı zəngi çalındı" (fakt)
 * Listener = "Qapını aç" (reaksiya)
 * Bir event-in BİR NEÇƏ listener-i ola bilər:
 *   → Qapını aç
 *   → Kameranı yoxla
 *   → Loqa yaz
 *
 * SYNC vs ASYNC (ShouldQueue):
 * ============================
 * 1. SYNC Listener (ShouldQueue olmadan):
 *    - Event dispatch olan anda, ELGƏ O ANDA icra olunur
 *    - HTTP request-in daxilində işləyir — istifadəçi gözləyir
 *    - Tez bitən işlər üçün uyğundur (log yazmaq, cache yeniləmək)
 *    - PROBLEM: Listener yavaşdırsa, istifadəçi gözləyəcək!
 *
 * 2. ASYNC Listener (ShouldQueue ilə — BU FAYL):
 *    - Event dispatch olan anda, Listener işi QUEUE-ya göndərir
 *    - İstifadəçi GÖZLƏMİR — cavab dərhal qayıdır
 *    - Queue worker arxa planda Listener-i icra edir
 *    - Yavaş işlər üçün İDEALDIR: email, ödəniş, API çağırışı
 *
 *    Necə işləyir?
 *    Request → Event dispatch → Listener queue-ya düşür → Response qaydır
 *    ... (arxa planda) → Queue worker Listener-i icra edir
 *
 * EVENT-LISTENER BINDING (bağlama):
 * =================================
 * Event və Listener bir-birinə AppServiceProvider-da bağlanır:
 *
 *   Event::listen(OrderPlacedEvent::class, DispatchPaymentJobListener::class);
 *
 * Bu o deməkdir: OrderPlacedEvent dispatch olanda,
 * DispatchPaymentJobListener-in handle() metodu çağırılacaq.
 *
 * Bir event-ə BİR NEÇƏ listener bağlamaq mümkündür:
 *   OrderPlacedEvent → [DispatchPaymentJobListener, SendOrderConfirmationListener]
 *
 * LISTENER vs OBSERVER FƏRQI:
 * ===========================
 * - OBSERVER: Yalnız MODEL LİFECYCLE event-ləri üçün (creating, created, updated...)
 *   Eloquent model yaradılanda/yeniləndikdə/silindikdə avtomatik işə düşür.
 *
 * - LISTENER: İSTƏNİLƏN event üçün (business event, custom event, Laravel event).
 *   Observer model lifecycle ilə bağlıdır, Listener isə daha genişdir.
 *
 * Məsələn:
 *   Observer: "Model DB-yə yazıldı" → OrderObserver::created()
 *   Listener: "Sifariş qeydə alındı, ödəniş başlat" → DispatchPaymentJobListener
 *
 * INTERACTS WITH QUEUE:
 * InteractsWithQueue trait-i queue ilə əlaqəli metodlar verir:
 * - $this->attempts() — neçənci cəhd olduğunu bildirir
 * - $this->release(30) — 30 saniyə sonra yenidən cəhd et
 * - $this->delete() — queue-dan sil, bir daha cəhd etmə
 * - $this->fail() — işi uğursuz kimi qeyd et
 */
class DispatchPaymentJobListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue adı — bu listener hansı queue-da işləyəcək.
     * Fərqli queue-lar üçün fərqli worker-lər ayırmaq mümkündür.
     * Məsələn: 'payments' queue-su yüksək prioritetli ola bilər.
     */
    public string $queue = 'payments';

    /**
     * Neçə dəfə cəhd edilsin?
     * Ödəniş uğursuz olsa, 3 dəfə yenidən cəhd edilir.
     */
    public int $tries = 3;

    /**
     * handle() — Listener-in əsas metodu.
     *
     * Laravel avtomatik olaraq Event obyektini inject edir.
     * $event->orderId, $event->userId kimi data-ya çata bilərsən.
     *
     * Burada ProcessPaymentJob dispatch olunmalıdır.
     * Job ödəniş gateway ilə əlaqə qurub ödənişi emal edəcək.
     */
    /**
     * Sifariş yaradılanda ödəniş prosesini avtomatik başladır.
     *
     * ProcessPaymentJob dispatch olunur — queue worker arxa planda
     * ödəniş gateway-ə (Stripe/PayPal) sorğu göndərir.
     *
     * NƏYƏ LİSTENER JOB DİSPATCH EDİR?
     * Listener özü ShouldQueue-dur, amma ödəniş prosesi üçün ayrı Job
     * istifadə edirik çünki:
     * 1. Job-un öz retry/backoff/timeout konfiqurasiyası var.
     * 2. Job chain-ə (zəncirə) qoşula bilər.
     * 3. Job uğursuz olanda öz failed() metodu var.
     * 4. Separation of Concerns: listener qərar verir, job icra edir.
     */
    public function handle(OrderPlacedEvent $event): void
    {
        Log::info('Sifariş üçün ödəniş prosesi başladılır', [
            'order_id' => $event->orderId,
            'user_id' => $event->userId,
            'total_amount' => $event->totalAmount,
        ]);

        \App\Jobs\ProcessPaymentJob::dispatch(
            orderId: $event->orderId,
            amount: (float) $event->totalAmount,
            currency: $event->currency,
            paymentMethod: $event->paymentMethod,
        );

        Log::info('ProcessPaymentJob dispatch olundu', [
            'order_id' => $event->orderId,
            'amount' => $event->totalAmount,
        ]);
    }

    /**
     * failed() — Listener queue-da uğursuz olduqda çağırılır.
     *
     * Bütün cəhdlər ($tries) bitdikdən sonra işə düşür.
     * Burada admin-ə bildiriş göndərmək, log yazmaq mümkündür.
     */
    public function failed(OrderPlacedEvent $event, \Throwable $exception): void
    {
        Log::error('Ödəniş listener-i uğursuz oldu', [
            'order_id' => $event->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
