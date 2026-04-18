<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Resilience;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GRACEFUL DEGRADATION — Zərif Deqradasiya Strategiyası
 * =======================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Sistem bir neçə xarici servisdən asılıdır:
 * - Ödəniş gateway (Stripe) — ödəniş emalı üçün.
 * - Email servisi (SendGrid) — bildiriş göndərmək üçün.
 * - Axtarış (Elasticsearch) — məhsul axtarışı üçün.
 * - Cache (Redis) — performans üçün.
 *
 * Əgər biri çöksə NƏ OLUR?
 *
 * Graceful Degradation OLMADAN:
 *   Stripe çökdü → bütün sayt 500 qaytarır → heç kim heç nə edə bilmir.
 *   Bu, "cascade failure" adlanır — bir xəta bütün sistemi çökdürür.
 *
 * Graceful Degradation İLƏ:
 *   Stripe çökdü → ödəniş hissəsi deaktiv olur, amma:
 *   - Məhsulları görmək olar ✓
 *   - Səbətə əlavə etmək olar ✓
 *   - Axtarış işləyir ✓
 *   - "Ödəniş müvəqqəti əlçatmazdır, sonra yenidən cəhd edin" mesajı göstərilir.
 *
 * ANALOGİYA:
 * ===========
 * Restoranda mətbəxdəki soba xarab oldu.
 * Graceful Degradation OLMADAN: Restoran bağlanır.
 * Graceful Degradation İLƏ: Soyuq yeməklər (salat, desert) hələ də satılır.
 *   "İsti yeməklər müvəqqəti əlçatan deyil" bildirişi asılır.
 *
 * CIRCUIT BREAKER İLƏ ƏLAQƏSİ:
 * ==============================
 * Circuit Breaker: "Bu servis çökübmü?" yoxlayır (detection).
 * Graceful Degradation: "Çöküb, bəs nə edim?" strategiyasını müəyyən edir (reaction).
 *
 * Circuit Breaker detects → Graceful Degradation reacts.
 *
 * DEQRADASİYA STRATEGİYALARI:
 * ============================
 * 1. FALLBACK: Əvəzedici cavab qaytar (cache-dən köhnə data).
 * 2. DEFAULT: Sabit default dəyər qaytar.
 * 3. SKIP: Bu funksiyanı tamamilə deaktiv et.
 * 4. QUEUE: İşi queue-yə yazıb sonra cəhd et.
 * 5. PARTIAL: Mümkün olan hissəni qaytar, qalanını skip et.
 */
class GracefulDegradation
{
    /**
     * Servislərin vəziyyəti — sağlamdırmı, deqrade olubmu?
     * Cache-də saxlanılır ki, hər sorğuda yoxlama olmasın.
     *
     * @var array<string, bool>
     */
    private const CACHE_PREFIX = 'service_health:';
    private const CACHE_TTL_SECONDS = 30;

    /**
     * FALLBACK STRATEGİYASI İLƏ İCRA ET
     * ====================================
     * Primary əməliyyatı icra etməyə çalışır.
     * Uğursuz olsa — fallback-ı icra edir.
     *
     * Bu, ən çox istifadə olunan degradation strategiyasıdır.
     *
     * @param string   $serviceName Primary servisin adı (log/monitoring üçün)
     * @param callable $primary     Əsas əməliyyat: fn() => $stripe->charge(100)
     * @param callable $fallback    Əvəzedici: fn() => ['status' => 'queued']
     *
     * @return mixed Primary və ya fallback-ın nəticəsi
     *
     * İSTİFADƏ NÜMUNƏSİ:
     * $result = GracefulDegradation::withFallback(
     *     'stripe',
     *     fn() => $stripe->charge($amount),
     *     fn() => $this->queueForLater($orderId, $amount),
     * );
     */
    public static function withFallback(string $serviceName, callable $primary, callable $fallback): mixed
    {
        // Əvvəlcə servisin sağlam olub-olmadığını yoxla
        if (self::isServiceDown($serviceName)) {
            Log::info("Graceful Degradation: {$serviceName} çökübdür, fallback istifadə olunur", [
                'service' => $serviceName,
                'strategy' => 'fallback',
            ]);

            return $fallback();
        }

        try {
            $result = $primary();

            // Uğurlu — servisi "sağlam" olaraq qeyd et
            self::markServiceUp($serviceName);

            return $result;
        } catch (\Throwable $e) {
            Log::warning("Graceful Degradation: {$serviceName} xəta verdi, fallback-a keçilir", [
                'service' => $serviceName,
                'error' => $e->getMessage(),
                'strategy' => 'fallback',
            ]);

            // Servisi "çöküb" olaraq qeyd et
            self::markServiceDown($serviceName);

            return $fallback();
        }
    }

    /**
     * DEFAULT DƏYƏR İLƏ İCRA ET
     * ===========================
     * Uğursuz olsa — sabit default dəyər qaytarır.
     * Fallback-dan fərqi: fallback callable-dır (iş görür), default isə statik dəyərdir.
     *
     * İSTİFADƏ NÜMUNƏSİ:
     * $products = GracefulDegradation::withDefault(
     *     'elasticsearch',
     *     fn() => $elastic->search('laptop'),
     *     [],  // Axtarış çöküb → boş nəticə
     * );
     */
    public static function withDefault(string $serviceName, callable $primary, mixed $default): mixed
    {
        return self::withFallback($serviceName, $primary, fn () => $default);
    }

    /**
     * SİSTEM SAĞLAMLIQ XƏRİTƏSİ
     * ============================
     * Bütün servislərin cari vəziyyətini qaytarır.
     * Health Check endpoint-i və admin dashboard üçün.
     *
     * @param array<string, callable> $healthChecks Servis adı → yoxlama funksiyası
     *
     * @return array<string, array{status: string, message: string}>
     */
    public static function systemHealthMap(array $healthChecks): array
    {
        $results = [];

        foreach ($healthChecks as $serviceName => $check) {
            try {
                $check();
                $results[$serviceName] = [
                    'status' => 'healthy',
                    'message' => 'Əlçatandır',
                ];
                self::markServiceUp($serviceName);
            } catch (\Throwable $e) {
                $results[$serviceName] = [
                    'status' => 'degraded',
                    'message' => $e->getMessage(),
                ];
                self::markServiceDown($serviceName);
            }
        }

        return $results;
    }

    /**
     * Servis çöküb mü?
     */
    public static function isServiceDown(string $serviceName): bool
    {
        return Cache::get(self::CACHE_PREFIX . $serviceName) === 'down';
    }

    private static function markServiceDown(string $serviceName): void
    {
        Cache::put(self::CACHE_PREFIX . $serviceName, 'down', self::CACHE_TTL_SECONDS);
    }

    private static function markServiceUp(string $serviceName): void
    {
        Cache::forget(self::CACHE_PREFIX . $serviceName);
    }
}
