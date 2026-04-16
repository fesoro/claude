<?php

declare(strict_types=1);

namespace Src\Shared\Application\Middleware;

use Illuminate\Support\Facades\Log;
use Src\Shared\Application\Bus\Command;
use Src\Shared\Domain\ConcurrencyException;

/**
 * RETRY ON CONCURRENCY MIDDLEWARE — Avtomatik Yenidən Cəhd
 * ===========================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Optimistic Locking ConcurrencyException atanda nə etməli?
 *
 * VARIANT 1 (Pis): İstifadəçiyə xəta göstər → "Sifariş dəyişdirilib, yeniləyin."
 *   Problem: Pis UX — istifadəçi nə etdiyini bilmir.
 *
 * VARIANT 2 (Yaxşı): Avtomatik retry → aggregate-i yenidən oxu → əməliyyatı təkrarla.
 *   Əgər 2-ci cəhddə version uyğun gəlirsə → uğurlu! İstifadəçi heç bilmir ki conflict oldu.
 *
 * BU MIDDLEWARE:
 * ==============
 * Command Bus pipeline-ında işləyir:
 *   Request → Logging → Idempotency → Validation → Transaction → [RETRY] → Handler
 *
 * ConcurrencyException tutduqda:
 * 1. Handler-i yenidən çağırır (aggregate təzədən oxunacaq).
 * 2. Max retry sayına çatsa → xətanı yuxarıya atır.
 * 3. Hər retry arasında kiçik gecikm qoyur (jitter).
 *
 * NƏYƏ JİTTER LAZIMDIR?
 * =======================
 * 100 request eyni anda conflict yaşayır → hamısı eyni anda retry edir → yenə conflict!
 * Buna "thundering herd" (gürləyən sürü) problemi deyilir.
 *
 * Jitter: Hər retry-a random gecikm əlavə edir (0-100ms).
 * Request-lər fərqli vaxtlarda retry edir → conflict azalır.
 *
 *   JİTTER-SİZ:   |retry|retry|retry|retry|  → hamısı eyni anda → yenə conflict
 *   JİTTER İLƏ:   |.retry|..retry|retry|...retry| → fərqli vaxtlarda → az conflict
 *
 * RETRY SAYI NƏ QƏDƏR OLMALIDIR?
 * ================================
 * 1 retry: Əksər hallarda kifayətdir (2 istifadəçi eyni anda → biri keçir).
 * 3 retry: High-contention ssenariləri üçün (flash sale, bilet satışı).
 * 5+ retry: Çox nadirdir — əgər 5 dəfə conflict varsa, problem başqa yerdədir.
 *
 * Bu middleware default 3 retry istifadə edir.
 *
 * COMMAND BUS MİDDLEWARE PATTERNİ:
 * ==================================
 * Middleware — command bus-da "boru" (pipe) kimidir:
 *
 *   Command → [Middleware 1] → [Middleware 2] → [Middleware 3] → Handler
 *
 * Hər middleware command-ı emal edib növbətiyə ötürür.
 * İstəsə command-ı dəyişə bilər, dayandıra bilər, və ya wrap edə bilər.
 *
 * Bu, "Chain of Responsibility" + "Decorator" pattern-inin kombinasiyasıdır.
 * ASP.NET, NestJS, Symfony Messenger — hamısında eyni pattern var.
 */
class RetryOnConcurrencyMiddleware implements Middleware
{
    public function __construct(
        private readonly int $maxRetries = 3,
        private readonly int $baseDelayMs = 50,
    ) {}

    /**
     * COMMAND-I EMAL ET — ConcurrencyException olsa retry et
     *
     * @param object   $command  Emal olunacaq command
     * @param callable $next     Növbəti middleware/handler
     * @return mixed   Handler-in nəticəsi
     *
     * @throws ConcurrencyException Max retry-dan sonra hələ conflict varsa
     */
    public function handle(Command $command, callable $next): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                $attempt++;
                return $next($command);

            } catch (ConcurrencyException $e) {
                if ($attempt >= $this->maxRetries) {
                    Log::error("Concurrency conflict — max retry-a çatdı", [
                        'command' => get_class($command),
                        'attempts' => $attempt,
                        'aggregate_id' => $e->aggregateId,
                        'expected_version' => $e->expectedVersion,
                        'actual_version' => $e->actualVersion,
                    ]);

                    throw $e;
                }

                Log::info("Concurrency conflict — retry edilir", [
                    'command' => get_class($command),
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'aggregate_id' => $e->aggregateId,
                ]);

                /**
                 * JİTTER İLƏ GECİKMƏ:
                 * Base delay × attempt sayı + random jitter
                 *
                 * Cəhd 1: 50ms × 1 + random(0-50) = 50-100ms
                 * Cəhd 2: 50ms × 2 + random(0-50) = 100-150ms
                 * Cəhd 3: 50ms × 3 + random(0-50) = 150-200ms
                 *
                 * usleep() microsecond ilə işləyir → ms × 1000
                 */
                $delay = ($this->baseDelayMs * $attempt + random_int(0, $this->baseDelayMs)) * 1000;
                usleep($delay);
            }
        }
    }
}
