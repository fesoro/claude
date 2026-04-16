<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Resilience;

use Illuminate\Support\Facades\Log;

/**
 * RETRYABLE REPOSITORY DECORATOR — Dayanıqlı Repository Wrapper
 * ================================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Production mühitdə verilənlər bazası həmişə əlçatan olmur:
 * - Connection timeout (şəbəkə problemləri).
 * - Deadlock (iki proses eyni sətri kilidləyib bir-birini gözləyir).
 * - "Too many connections" (çox sayda bağlantı).
 * - Master-slave replication lag (yazma olub, oxuma hələ köhnə data qaytarır).
 *
 * Bu xətalar MÜVƏQQƏTI-dir (transient) — bir az gözləsən düzələr.
 * Amma əgər ilk cəhddə uğursuz olsa, istifadəçiyə 500 qaytarmaq pis təcrübədir.
 *
 * HƏLLİ — RETRY PATTERN + DECORATOR:
 * ===================================
 * Repository-ni "retry decorator" ilə sarıyırıq (wrap edirik).
 * Əgər DB sorğusu uğursuz olsa, müəyyən gecikmədən sonra yenidən cəhd edir.
 *
 * Bu, Decorator Pattern + Retry Pattern birləşməsidir:
 *
 * Decorator Pattern: CachedProductRepository → EloquentProductRepository
 * Retry Pattern: RetryableRepository → CachedProductRepository → EloquentProductRepository
 *
 * ANALOGİYA:
 * =========
 * Telefon zəngi etdin, cavab yoxdur. Birbaşa "yox" deyib imtina etmirsən —
 * 30 saniyə sonra yenidən zəng edirsən. 3 dəfə cəhd edib sonra imtina edirsən.
 * Bu, retry pattern-dir.
 *
 * EKSPONENSİAL BACKOFF + JİTTER:
 * ================================
 * Sadə retry: 1 san, 1 san, 1 san → server hələ bərpa olunmayıb, faydasızdır.
 * Eksponensial: 1 san, 2 san, 4 san → hər dəfə daha çox gözlə.
 * + Jitter: 1±0.5 san, 2±1 san, 4±2 san → təsadüfi gecikmə əlavə et.
 *
 * NƏYƏ JİTTER?
 * 100 server eyni anda DB-yə bağlanmaq istəyir. Hamısı eyni vaxtda retry etsə,
 * DB yenə şişər! Jitter hər serverin fərqli vaxtda retry etməsini təmin edir.
 * Bu, "thundering herd" probleminin həllidir.
 *
 * THUNDERING HERD (sürü davranışı):
 * Cache expire oldu → bütün serverlər eyni anda DB-yə sorğu göndərir → DB çökür.
 * Jitter ilə: hər server fərqli vaxtda retry edir → yük paylanır.
 *
 * TRANSİENT vs PERMANENT XƏTA:
 * ==============================
 * Transient (müvəqqəti): Timeout, deadlock, connection refused → RETRY ET
 * Permanent (daimi): Syntax error, table not found, permission denied → RETRY ETMƏ
 * Bu class yalnız transient xətaları retry edir.
 */
class RetryableRepository
{
    /**
     * Default: 3 cəhd (1 əsas + 2 retry).
     */
    private const DEFAULT_MAX_ATTEMPTS = 3;

    /**
     * Default baza gecikmə: 100ms.
     * Eksponensial artım: 100ms → 200ms → 400ms.
     */
    private const DEFAULT_BASE_DELAY_MS = 100;

    /**
     * Müvəqqəti (transient) xətaları təyin edən pattern-lər.
     * Bu xətalar retry üçün uyğundur — bir az gözləsən düzələr.
     *
     * Permanent xətalar burada YOXdur: syntax error, table not found, etc.
     * Onları retry etmək mənasızdır.
     */
    private const TRANSIENT_ERROR_PATTERNS = [
        'deadlock',
        'lock wait timeout',
        'too many connections',
        'connection refused',
        'connection timed out',
        'gone away',
        'server has gone away',
        'lost connection',
        'broken pipe',
    ];

    /**
     * İSTƏNİLƏN REPOSITORY ƏMƏLİYYATINI RETRY İLƏ İCRA ET
     * =======================================================
     *
     * @param callable $operation   İcra ediləcək əməliyyat: fn() => $repo->findById($id)
     * @param int      $maxAttempts Maksimum cəhd sayı (default: 3)
     * @param int      $baseDelayMs Baza gecikmə millisaniyə (default: 100)
     * @param bool     $useJitter   Təsadüfi gecikmə əlavə etsin? (default: true)
     *
     * @return mixed Əməliyyatın nəticəsi
     * @throws \Throwable Son uğursuz cəhdin exception-ı
     *
     * İSTİFADƏ NÜMUNƏSİ:
     * $product = RetryableRepository::execute(
     *     fn() => $this->productRepo->findById($id),
     *     maxAttempts: 3,
     * );
     */
    public static function execute(
        callable $operation,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $baseDelayMs = self::DEFAULT_BASE_DELAY_MS,
        bool $useJitter = true,
    ): mixed {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                /**
                 * Əgər xəta transient deyilsə — retry etmə, dərhal at.
                 * "Table not found" xətasını 3 dəfə cəhd etməyin mənası yoxdur.
                 */
                if (!self::isTransient($e)) {
                    Log::warning('Permanent xəta — retry edilmir', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                /**
                 * Son cəhd idisə — artıq retry yoxdur, exception-ı at.
                 */
                if ($attempt === $maxAttempts) {
                    Log::error('Bütün retry cəhdləri bitdi', [
                        'total_attempts' => $maxAttempts,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }

                /**
                 * EKSPONENSİAL BACKOFF HESABLAMASI:
                 * delay = baseDelay * 2^(attempt - 1)
                 *
                 * Attempt 1: 100ms * 2^0 = 100ms
                 * Attempt 2: 100ms * 2^1 = 200ms
                 * Attempt 3: 100ms * 2^2 = 400ms
                 *
                 * Jitter: ±50% təsadüfi sapma əlavə edir.
                 * 200ms + jitter → 100ms-300ms arasında olur.
                 */
                $delayMs = $baseDelayMs * (2 ** ($attempt - 1));

                if ($useJitter) {
                    $jitter = random_int(0, (int) ($delayMs * 0.5));
                    $delayMs = $delayMs + $jitter - (int) ($delayMs * 0.25);
                }

                Log::info('Transient xəta — retry edilir', [
                    'attempt' => $attempt,
                    'next_attempt' => $attempt + 1,
                    'delay_ms' => $delayMs,
                    'error' => $e->getMessage(),
                ]);

                usleep($delayMs * 1000); // millisaniyəni mikrosaniyəyə çevir
            }
        }

        throw $lastException;
    }

    /**
     * XƏTA MÜVƏQQƏTİ (TRANSİENT) DİRMİ?
     * ======================================
     * Xəta mesajını yoxlayır — transient pattern-lərdən biri varsa, true qaytarır.
     * Kiçik hərflə müqayisə edirik ki, "Deadlock" və "deadlock" eyni tutulsun.
     */
    public static function isTransient(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        foreach (self::TRANSIENT_ERROR_PATTERNS as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
