<?php

declare(strict_types=1);

namespace Src\Payment\Infrastructure\CircuitBreaker;

/**
 * CIRCUIT BREAKER PATTERN
 * =======================
 * Circuit Breaker — xarici xidmət (API) çökdükdə sistemi qoruyan pattern-dir.
 *
 * REAL HƏYAT ANALOGİYASI — ELEKTRİK AÇARI (Circuit Breaker):
 * ──────────────────────────────────────────────────────────────
 * Evin elektrik sistemi:
 * - Normal vəziyyət: Elektrik axır, cihazlar işləyir (CLOSED)
 * - Qısa qapanma olur: Elektrik açarı açılır, elektrik kəsilir (OPEN)
 *   Nəyə? Yanğın olmasın deyə! Davamlı xəta sistemi sıradan çıxara bilər.
 * - Bir müddət gözlədikdən sonra, usta yoxlamaq üçün açarı sınayır (HALF_OPEN)
 * - Düzəlibsə, elektrik bərpa olunur (CLOSED-a qayıdır)
 *
 * PROQRAMLAŞDIRMADA:
 * ───────────────────
 * Stripe API çöküb. Hər ödəniş sorğusu 30 saniyə gözlənir, sonra timeout olur.
 * 1000 müştəri eyni anda ödəniş edir → 1000 sorğu 30 saniyə gözləyir → SİSTEM ÇÖKÜR!
 *
 * Circuit Breaker həlli:
 * - 5 sorğu uğursuz oldu → Circuit Breaker AÇILIR (OPEN)
 * - Növbəti sorğuları Stripe-a göndərmir, dərhal "xidmət əlçatmazdır" qaytarır
 * - 30 saniyə sonra BİR sorğu göndərərək yoxlayır (HALF_OPEN)
 * - Uğurludursa → CLOSED (normal rejimə qayıdır)
 * - Uğursuzdursa → OPEN (yenə gözləyir)
 *
 * ÜÇ STATE (VƏZİYYƏT):
 * =====================
 *
 * 1. CLOSED (Qapalı — Normal Rejim)
 *    ┌──────────────────────────────────────────┐
 *    │ Bütün sorğular xarici API-yə göndərilir │
 *    │ Hər uğursuzluq sayılır (failure count)   │
 *    │ Uğurlu olsa, sayğac sıfırlanır           │
 *    └──────────────────────────────────────────┘
 *    │
 *    │ Uğursuzluq sayı >= threshold (həddi)
 *    ▼
 * 2. OPEN (Açıq — Qoruma Rejimi)
 *    ┌──────────────────────────────────────────────────┐
 *    │ HEÇ BİR SORĞU xarici API-yə göndərilmir!       │
 *    │ Dərhal CircuitBreakerOpenException atılır         │
 *    │ Bu, xarici API-ni "narahat etməmək" üçündür      │
 *    │ Həmçinin bizim sistemin resurslarını qoruyur      │
 *    └──────────────────────────────────────────────────┘
 *    │
 *    │ resetTimeout vaxtı keçdi
 *    ▼
 * 3. HALF_OPEN (Yarım Açıq — Sınaq Rejimi)
 *    ┌──────────────────────────────────────────────────────┐
 *    │ BİR sorğu göndərilir — xarici API düzəlibmi yoxla  │
 *    │ Uğurlu → CLOSED-a qayıt (normal rejim)              │
 *    │ Uğursuz → OPEN-a qayıt (yenə gözlə)                │
 *    └──────────────────────────────────────────────────────┘
 *
 * KONFIQURASIYA PARAMETRLƏRİ:
 * ===========================
 * - failureThreshold: Neçə uğursuzluqdan sonra OPEN olsun? (default: 5)
 * - resetTimeoutSeconds: OPEN-dan HALF_OPEN-a neçə saniyə sonra keçsin? (default: 30)
 *
 * NÜMUNƏ SSENARI:
 * ═══════════════
 * Threshold = 3, Timeout = 30 saniyə
 *
 * Sorğu 1: Stripe → Uğursuz (failure: 1) — CLOSED
 * Sorğu 2: Stripe → Uğursuz (failure: 2) — CLOSED
 * Sorğu 3: Stripe → Uğursuz (failure: 3) — THRESHOLD AŞILDI → OPEN!
 * Sorğu 4: Stripe-a GÖNDƏRİLMİR → "Xidmət əlçatmazdır" (OPEN)
 * Sorğu 5: Stripe-a GÖNDƏRİLMİR → "Xidmət əlçatmazdır" (OPEN)
 * ... 30 saniyə gözlə ...
 * Sorğu 6: Stripe → Bir dənə sınaq sorğusu göndər (HALF_OPEN)
 *   - Uğurlu → CLOSED (normal rejimə qayıt!)
 *   - Uğursuz → OPEN (daha 30 saniyə gözlə)
 *
 * REAL PROYEKTDƏ İSTİFADƏ:
 * ========================
 * - Ödəniş gateway-ləri (Stripe, PayPal)
 * - Email servisi (SendGrid, Mailgun)
 * - SMS servisi (Twilio)
 * - Hər hansı xarici API çağırışı
 *
 * DİQQƏT: Bu in-memory implementasiyadır (RAM-da saxlanılır).
 * Server restart olsa, state sıfırlanır.
 * Real proyektdə Redis istifadə olunardı ki, state paylaşıla bilsin.
 */
final class CircuitBreaker
{
    /** Circuit Breaker-in üç vəziyyəti */
    private const STATE_CLOSED = 'closed';       // Normal — sorğular göndərilir
    private const STATE_OPEN = 'open';           // Qoruma — sorğular bloklanır
    private const STATE_HALF_OPEN = 'half_open'; // Sınaq — bir sorğu göndərilir

    /** Hazırkı vəziyyət */
    private string $state = self::STATE_CLOSED;

    /** Ardıcıl uğursuzluq sayı */
    private int $failureCount = 0;

    /** Sonuncu uğursuzluğun vaxtı — OPEN-dan HALF_OPEN-a keçid üçün */
    private ?\DateTimeImmutable $lastFailureTime = null;

    /**
     * @param int $failureThreshold Neçə uğursuzluqdan sonra OPEN olsun (default: 5)
     * @param int $resetTimeoutSeconds OPEN-dan HALF_OPEN-a neçə saniyə sonra keçsin (default: 30)
     */
    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $resetTimeoutSeconds = 30,
    ) {
    }

    /**
     * Əməliyyatı Circuit Breaker vasitəsilə icra et.
     *
     * @template T
     * @param callable(): T $operation İcra olunacaq əməliyyat (məsələn: gateway->charge())
     * @return T Əməliyyatın nəticəsi
     * @throws CircuitBreakerOpenException Circuit açıqdırsa (OPEN state)
     */
    public function execute(callable $operation): mixed
    {
        // 1. Hazırkı state-i yoxla və lazım olsa HALF_OPEN-a keçir
        $this->evaluateState();

        // 2. Əgər OPEN-dırsa, sorğunu blokla — xarici API-ni narahat etmə
        if ($this->isOpen()) {
            throw new CircuitBreakerOpenException(
                "Circuit Breaker açıqdır (OPEN). Xarici xidmət əlçatmazdır. "
                . "{$this->resetTimeoutSeconds} saniyə sonra yenidən cəhd olunacaq."
            );
        }

        try {
            // 3. Əməliyyatı icra et (CLOSED və ya HALF_OPEN state-də)
            $result = $operation();

            // 4. Uğurlu — uğursuzluq sayğacını sıfırla və CLOSED-a keçir
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            // 5. Uğursuz — uğursuzluğu qeyd et
            $this->recordFailure();

            // Exception-ı yenidən at ki, çağıran kod xətanı bilsin
            throw $e;
        }
    }

    /**
     * State-i qiymətləndir — OPEN-dan HALF_OPEN-a keçid vaxtı gəlibmi?
     *
     * Əgər OPEN state-dəyiksə VƏ resetTimeout vaxtı keçibsə,
     * HALF_OPEN-a keçirik — bir sınaq sorğusu göndərmək üçün.
     */
    private function evaluateState(): void
    {
        if ($this->state !== self::STATE_OPEN) {
            return;
        }

        // Sonuncu uğursuzluqdan neçə saniyə keçib?
        if ($this->lastFailureTime === null) {
            return;
        }

        $secondsSinceLastFailure = (new \DateTimeImmutable())->getTimestamp()
            - $this->lastFailureTime->getTimestamp();

        // Timeout vaxtı keçibsə, HALF_OPEN-a keçir — bir sorğu göndərərək yoxla
        if ($secondsSinceLastFailure >= $this->resetTimeoutSeconds) {
            $this->state = self::STATE_HALF_OPEN;
        }
    }

    /**
     * Uğurlu əməliyyatı qeyd et.
     * Sayğacı sıfırla və CLOSED (normal) state-ə keçir.
     */
    private function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = self::STATE_CLOSED;
        $this->lastFailureTime = null;
    }

    /**
     * Uğursuz əməliyyatı qeyd et.
     * Sayğacı artır. Threshold-u aşıbsa, OPEN state-ə keçir.
     */
    private function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = new \DateTimeImmutable();

        // Threshold aşıldı — Circuit Breaker-i aç (OPEN)
        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
        }
    }

    // ─── STATE YOXLAMA METODLARı ───────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function failureCount(): int
    {
        return $this->failureCount;
    }
}
