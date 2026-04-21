<?php

declare(strict_types=1);

namespace Src\Payment\Infrastructure\CircuitBreaker;

/**
 * RETRY PATTERN + EXPONENTIAL BACKOFF
 * ====================================
 *
 * RETRY PATTERN NƏDİR?
 * =====================
 * Uğursuz əməliyyatı avtomatik olaraq bir neçə dəfə təkrar cəhd edən pattern-dir.
 *
 * NƏYƏ LAZIMDIR?
 * ───────────────
 * Xarici API-lər müvəqqəti (transient) xətalar verə bilər:
 * - Network timeout (şəbəkə gecikmasi)
 * - 503 Service Unavailable (xidmət müvəqqəti əlçatmazdır)
 * - Rate limiting (çox sorğu göndərdin, bir az gözlə)
 *
 * Bu xətalar keçicidir — bir neçə saniyə sonra düzələ bilər.
 * Retry pattern bu halda avtomatik olaraq yenidən cəhd edir.
 *
 * EXPONENTIAL BACKOFF NƏDİR?
 * ===========================
 * Hər uğursuz cəhddən sonra gözləmə müddətini ARTIRAN strategiyadır.
 *
 * SADƏ RETRY (pis yanaşma):
 *   Cəhd 1: Xəta → dərhal təkrar
 *   Cəhd 2: Xəta → dərhal təkrar
 *   Cəhd 3: Xəta → dərhal təkrar
 *   Problem: Xarici API artıq yüklüdür, daha çox sorğu göndərmək vəziyyəti pisləşdirir!
 *
 * EXPONENTIAL BACKOFF (yaxşı yanaşma):
 *   Cəhd 1: Xəta → 1 saniyə gözlə
 *   Cəhd 2: Xəta → 2 saniyə gözlə (2x artdı)
 *   Cəhd 3: Xəta → 4 saniyə gözlə (2x artdı)
 *   Cəhd 4: Xəta → 8 saniyə gözlə (2x artdı)
 *   Üstünlük: Hər dəfə daha çox gözləyirik, API-yə "nəfəs almağa" imkan veririk.
 *
 * HESABLAMA FORMULU:
 * ──────────────────
 * gözləmə = baseDelay × (2 ^ cəhd_nömrəsi)
 *
 * baseDelay = 100ms (0.1 saniyə) olarsa:
 * - Cəhd 0: 100ms × 2⁰ = 100ms  (0.1 saniyə)
 * - Cəhd 1: 100ms × 2¹ = 200ms  (0.2 saniyə)
 * - Cəhd 2: 100ms × 2² = 400ms  (0.4 saniyə)
 * - Cəhd 3: 100ms × 2³ = 800ms  (0.8 saniyə)
 *
 * JITTER (təsadüfi gecikməl):
 * ────────────────────────────
 * Əgər 1000 müştəri eyni anda retry edirsə, hamısı eyni vaxtda sorğu göndərər.
 * Bu, "thundering herd" (sürüsünün gurultusu) problemidir.
 * Jitter — hər retry-a kiçik təsadüfi gecikməl əlavə edir ki, sorğular paylaşılsın.
 *
 * REAL PROYEKTDƏ İSTİFADƏ:
 * - HTTP API çağırışları (Stripe, PayPal)
 * - Verilənlər bazası bağlantıları
 * - Mesaj göndərmə (RabbitMQ, Redis)
 * - Fayl yükləmə (S3, Google Cloud Storage)
 *
 * CİRCUİT BREAKER İLƏ BİRLİKDƏ:
 * ───────────────────────────────
 * Retry + Circuit Breaker birlikdə işləyir:
 * - Retry: Müvəqqəti xətalar üçün bir neçə dəfə cəhd edir
 * - Circuit Breaker: Davamlı xətalar üçün (retry-lar da kömək etmir) sorğuları bloklayır
 *
 * AXIN:
 * CircuitBreaker.execute(
 *   RetryHandler.execute(
 *     gateway.charge(money)   ← əsl əməliyyat
 *   )
 * )
 */
final class RetryHandler
{
    /**
     * @param int $maxRetries Maksimum təkrar cəhd sayı (default: 3)
     * @param int $baseDelayMs Baza gecikməl millisaniyələrlə (default: 100ms)
     * @param bool $useJitter Təsadüfi gecikməl əlavə etsin? (default: true)
     */
    public function __construct(
        private readonly int $maxRetries = 3,
        private readonly int $baseDelayMs = 100,
        private readonly bool $useJitter = true,
    ) {
    }

    /**
     * Əməliyyatı retry ilə icra et.
     *
     * @template T
     * @param callable(): T $operation İcra olunacaq əməliyyat
     * @return T Uğurlu nəticə
     * @throws \Throwable Bütün cəhdlər uğursuz olduqda sonuncu exception atılır
     */
    public function execute(callable $operation): mixed
    {
        $lastException = null;

        // maxRetries + 1 cəhd olacaq (ilk cəhd + retry-lar)
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // Əməliyyatı icra et
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                // Sonuncu cəhd idisə, daha retry etmə
                if ($attempt === $this->maxRetries) {
                    break;
                }

                // Exponential backoff ilə gözlə
                $delayMs = $this->calculateDelay($attempt);

                // Gözləmə müddəti (millisaniyələri mikrosaniyələrə çevirik)
                // usleep() mikrosaniyə ilə işləyir: 1ms = 1000μs
                usleep($delayMs * 1000);
            }
        }

        // Bütün cəhdlər uğursuz oldu — sonuncu exception-ı at
        throw $lastException;
    }

    /**
     * Exponential backoff + jitter ilə gecikməl hesabla.
     *
     * @param int $attempt Cəhd nömrəsi (0-dan başlayır)
     * @return int Gözləmə müddəti millisaniyələrlə
     */
    private function calculateDelay(int $attempt): int
    {
        // Exponential: baseDelay × 2^attempt
        // Məsələn: 100 × 2⁰ = 100ms, 100 × 2¹ = 200ms, 100 × 2² = 400ms
        $delay = $this->baseDelayMs * (2 ** $attempt);

        if ($this->useJitter) {
            // Jitter: 0 ilə delay arasında təsadüfi dəyər əlavə et
            // Bu, eyni anda retry edən sorğuları paylaşdırır
            $jitter = random_int(0, (int) ($delay * 0.5));
            $delay += $jitter;
        }

        return (int) $delay;
    }
}
