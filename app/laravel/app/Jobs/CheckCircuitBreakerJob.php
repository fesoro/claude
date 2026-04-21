<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CİRCUİT BREAKER YOXLAMA JOB-U (Periodic/Scheduled Job)
 * =========================================================
 * Mütəmadi olaraq Circuit Breaker-lərin vəziyyətini yoxlayır
 * və bərpa vaxtı keçmişsə, onları sıfırlayır.
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SCHEDULED JOBS NECƏ İŞLƏYİR?                                        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  Laravel Scheduler — job-ları müəyyən vaxt intervallarında avtomatik   ║
 * ║  dispatch edir. Bu, Unix cron-un Laravel versiyasıdır.                ║
 * ║                                                                        ║
 * ║  QURAŞDIRMA:                                                          ║
 * ║  ──────────                                                            ║
 * ║  1. Server-də bir dənə cron entry əlavə et:                           ║
 * ║     * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null║
 * ║     Bu hər dəqiqə Laravel Scheduler-i çağırır.                        ║
 * ║                                                                        ║
 * ║  2. app/Console/Kernel.php-də schedule təyin et:                       ║
 * ║     protected function schedule(Schedule $schedule): void              ║
 * ║     {                                                                  ║
 * ║         // Hər 5 dəqiqədə Circuit Breaker-ləri yoxla                  ║
 * ║         $schedule->job(new CheckCircuitBreakerJob())                   ║
 * ║                  ->everyFiveMinutes()                                  ║
 * ║                  ->withoutOverlapping();                               ║
 * ║                                                                        ║
 * ║         // Hər dəqiqə Outbox mesajlarını göndər                       ║
 * ║         $schedule->job(new PublishOutboxMessagesJob())                 ║
 * ║                  ->everyMinute();                                      ║
 * ║     }                                                                  ║
 * ║                                                                        ║
 * ║  SCHEDULE VARIANTLARI:                                                 ║
 * ║  ─────────────────────                                                 ║
 * ║  ->everyMinute()         — hər dəqiqə                                 ║
 * ║  ->everyFiveMinutes()    — hər 5 dəqiqə                               ║
 * ║  ->hourly()              — hər saat                                    ║
 * ║  ->daily()               — hər gün                                     ║
 * ║  ->withoutOverlapping()  — əvvəlki bitməmiş olsa, yenisini başlatma   ║
 * ║  ->onOneServer()         — çox server olsa, yalnız birində işləsin    ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CIRCUIT BREAKER İLƏ SCHEDULED JOB — NƏYƏ LAZIMDIR?                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  CircuitBreaker class-ımız in-memory-dir (RAM-da).                    ║
 * ║  Real proyektdə Redis-də saxlanılır ki, bütün server-lər paylaşsın.  ║
 * ║                                                                        ║
 * ║  PROBLEM:                                                              ║
 * ║  Circuit Breaker OPEN vəziyyətdədir (Stripe çöküb).                   ║
 * ║  resetTimeout = 30 saniyə keçdi. Amma heç kim sorğu göndərmir.       ║
 * ║  HALF_OPEN-a keçid yalnız YENİ SORĞU GƏLDİKDƏ baş verir.            ║
 * ║  Əgər sorğu gəlmirsə, Circuit Breaker OPEN-da qalır — əbədi!        ║
 * ║                                                                        ║
 * ║  HƏLLİ:                                                                ║
 * ║  Bu Job hər 5 dəqiqədə Circuit Breaker-ləri yoxlayır.                ║
 * ║  Əgər resetTimeout keçibsə, vəziyyəti sıfırlayır.                    ║
 * ║  Beləliklə, Stripe bərpa olduqda yeni sorğular bloklanmaz.           ║
 * ║                                                                        ║
 * ║  REDIS-DƏ SAXLAMA SXEMI:                                              ║
 * ║  circuit_breaker:stripe → {state: 'open', failures: 5, last_failure: ...}║
 * ║  circuit_breaker:paypal → {state: 'closed', failures: 0}             ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * DISPATCH NÜMUNƏSI (Scheduler-dən):
 * $schedule->job(new CheckCircuitBreakerJob())->everyFiveMinutes();
 */
class CheckCircuitBreakerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Circuit Breaker yoxlaması sadə əməliyyatdır, 2 cəhd kifayətdir.
     */
    public int $tries = 2;

    /**
     * Timeout: 30 saniyə — Redis-dən oxuma tez olmalıdır.
     */
    public int $timeout = 30;

    /**
     * Yoxlanılacaq Circuit Breaker-lərin siyahısı.
     *
     * Hər birinin cache açarı və bərpa müddəti var.
     * Real proyektdə bu siyahı config faylından gələrdı:
     * config('circuit_breakers.services')
     *
     * @var array<string, int> Service adı => resetTimeout (saniyə)
     */
    private const CIRCUIT_BREAKERS = [
        'stripe' => 30,        // Stripe ödəniş gateway — 30 saniyə
        'paypal' => 60,        // PayPal — 60 saniyə
        'sendgrid' => 45,      // SendGrid email — 45 saniyə
        'rabbitmq' => 20,      // RabbitMQ bağlantısı — 20 saniyə
    ];

    /**
     * Circuit Breaker-ləri yoxla və lazım olsa sıfırla.
     *
     * HƏR BİR SERVİS ÜÇÜN:
     * 1. Redis-dən Circuit Breaker state-ini oxu
     * 2. Əgər OPEN-dırsa VƏ resetTimeout keçibsə:
     *    - State-i CLOSED-a sıfırla
     *    - Failure sayğacını sıfırla
     *    - Log yaz
     * 3. Əgər CLOSED və ya timeout keçməyibsə — heç nə etmə
     *
     * DİQQƏT: Bu "proactive health check" (aktiv sağlamlıq yoxlaması) deyil.
     * Xarici API-yə sorğu göndərmir! Yalnız timeout-a əsasən reset edir.
     * Real health check etmək istəsən, hər servisə ping göndərmək lazımdır.
     */
    public function handle(): void
    {
        Log::debug('Circuit Breaker vəziyyətləri yoxlanılır...');

        $resetCount = 0;

        foreach (self::CIRCUIT_BREAKERS as $service => $resetTimeoutSeconds) {
            $cacheKey = "circuit_breaker:{$service}";

            /** @var array|null $state Redis-dən oxunan Circuit Breaker state-i */
            $state = Cache::get($cacheKey);

            // State yoxdursa — Circuit Breaker heç vaxt aktivləşməyib, keç
            if ($state === null) {
                continue;
            }

            // OPEN deyilsə — problem yoxdur, keç
            if (($state['state'] ?? 'closed') !== 'open') {
                continue;
            }

            // Son uğursuzluqdan neçə saniyə keçib?
            $lastFailureTime = $state['last_failure_at'] ?? null;
            if ($lastFailureTime === null) {
                continue;
            }

            $secondsSinceFailure = time() - (int) $lastFailureTime;

            // resetTimeout keçibsə — Circuit Breaker-i sıfırla
            if ($secondsSinceFailure >= $resetTimeoutSeconds) {
                Cache::put($cacheKey, [
                    'state' => 'closed',
                    'failure_count' => 0,
                    'last_failure_at' => null,
                    'reset_at' => now()->toISOString(),
                    'reset_by' => 'CheckCircuitBreakerJob',
                ]);

                Log::info("Circuit Breaker sıfırlandı: {$service}", [
                    'service' => $service,
                    'was_open_for_seconds' => $secondsSinceFailure,
                    'reset_timeout' => $resetTimeoutSeconds,
                ]);

                $resetCount++;
            } else {
                $remainingSeconds = $resetTimeoutSeconds - $secondsSinceFailure;
                Log::debug("Circuit Breaker hələ OPEN: {$service}", [
                    'service' => $service,
                    'remaining_seconds' => $remainingSeconds,
                ]);
            }
        }

        if ($resetCount > 0) {
            Log::info("{$resetCount} Circuit Breaker sıfırlandı");
        } else {
            Log::debug('Sıfırlanacaq Circuit Breaker yoxdur');
        }
    }

    /**
     * Circuit Breaker yoxlaması uğursuz olsa, bu kritik deyil.
     * Növbəti scheduled run-da yenidən yoxlanacaq.
     */
    public function failed(\Throwable $exception): void
    {
        Log::warning('Circuit Breaker yoxlaması uğursuz oldu', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
