<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Resilience;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * BULKHEAD PATTERN — Kaskad Uğursuzluqdan Qorunma
 * ====================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Gəmi dizaynında "bulkhead" — gəminin gövdəsindəki bölmə divarlarıdır.
 * Bir bölmə su ilə dolsa, divar digər bölmələrə suyun keçməsini əngəlləyir.
 * Əgər bölmə olmasaydı — bir deşik bütün gəmini batırardı.
 *
 * Proqram təminatında da eyni problem var:
 *
 * PROBLEM SENARİSİ:
 *   - Payment Gateway yavaşlayır (timeout: 30 san).
 *   - Hər request bir thread/connection istifadə edir.
 *   - 100 connection pool var.
 *   - Payment request-ləri 30 san gözləyir × 100 = bütün connection-lar dolur!
 *   - İndi Product API-ya da sorğu göndərmək olmur — connection yoxdur!
 *   - BÜTÜN SİSTEM ÇÖKÜR — çünki Payment-in problemi Product-a da təsir etdi.
 *
 * Bu, "cascading failure" (kaskad uğursuzluq) adlanır.
 * Netflix 2012-ci ildə buna görə 3 saat down oldu.
 *
 * HƏLLİ — BULKHEAD PATTERN:
 * ===========================
 * Hər xidmət/resurs üçün AYRI limit qoyuruq:
 *
 *   ┌────────────────────────────┐
 *   │         SİSTEM             │
 *   │  ┌──────────┐ ┌─────────┐ │
 *   │  │ Payment  │ │ Product │ │
 *   │  │ max: 20  │ │ max: 30 │ │
 *   │  │ conn.    │ │ conn.   │ │
 *   │  └──────────┘ └─────────┘ │
 *   │  ┌──────────┐ ┌─────────┐ │
 *   │  │ Notif.   │ │ Search  │ │
 *   │  │ max: 10  │ │ max: 15 │ │
 *   │  │ conn.    │ │ conn.   │ │
 *   │  └──────────┘ └─────────┘ │
 *   └────────────────────────────┘
 *
 * Payment 20 connection-u doldursa — yalnız Payment request-ləri rədd olunur.
 * Product, Notification, Search — işləməyə davam edir.
 * "Bir bölmə batsa da, gəmi üzməyə davam edir."
 *
 * CİRCUİT BREAKER vs BULKHEAD:
 * ==============================
 * Circuit Breaker: "Xidmət cavab vermir → qapını bağla, sorğu göndərmə."
 *   → Uğursuzluğu AŞKAR EDƏNDƏ reaksiya göstərir.
 *
 * Bulkhead: "Bu xidmət üçün max 20 eyni vaxtlı sorğu. 21-ci rədd olsun."
 *   → Uğursuzluğu QARŞISINI ALIR (resurs izolyasiyası ilə).
 *
 * İkisi birlikdə istifadə olunur:
 *   Bulkhead: Resursları izolyasiya edir → kaskad uğursuzluğun qarşısını alır.
 *   Circuit Breaker: Uğursuz xidməti aşkar edir → sorğuları dayandırır.
 *
 * BULKHEAD NÖVL RI:
 * ==================
 * 1. Thread Pool Bulkhead: Hər xidmət üçün ayrı thread pool.
 *    Java (Hystrix/Resilience4j) dünyasında populyardır.
 *    PHP-də thread yoxdur, ona görə bu variant uyğun deyil.
 *
 * 2. Semaphore Bulkhead: Eyni vaxtlı request sayını limitləmək. ← BU VARIANT
 *    Counter ilə izləyirik: "hazırda neçə request var?"
 *    Limit dolubsa → yeni request rədd olunur.
 *    PHP/Laravel üçün Redis counter ilə implementasiya.
 *
 * 3. Process Bulkhead: Hər xidmət üçün ayrı process/container.
 *    Kubernetes pod-level izolyasiya — infra səviyyəsində.
 *
 * RATE LİMİTİNG vs BULKHEAD:
 * ============================
 * Rate Limiting: "Dəqiqədə max 100 request." → Zaman əsaslı limit.
 * Bulkhead: "Eyni anda max 20 request." → Concurrency əsaslı limit.
 *
 * Rate Limiting: Suistifadəni (abuse) əngəlləyir.
 * Bulkhead: Resurs tükənməsini əngəlləyir.
 *
 * İkisi fərqli problemləri həll edir, ikisi də lazımdır.
 *
 * REAL DÜNYA NÜMUNƏLƏRİ:
 * ========================
 * Amazon: Hər microservice üçün ayrı connection pool.
 * Netflix: Hystrix library ilə thread pool bulkhead.
 * Kubernetes: Pod resource limits (CPU, memory) — container səviyyəsində bulkhead.
 */
class BulkheadPattern
{
    /**
     * @param string $name        Bulkhead adı (məs: "payment-gateway", "notification-service")
     * @param int    $maxConcurrent Max eyni vaxtlı request sayı
     * @param int    $maxWaitSeconds Request gözləmə müddəti (queue overflow zamanı)
     */
    public function __construct(
        private readonly string $name,
        private readonly int $maxConcurrent = 20,
        private readonly int $maxWaitSeconds = 5,
    ) {}

    /**
     * BULKHEAD DAXİLİNDƏ İCRA ET
     * =============================
     * Əgər limit dolmayıbsa — callback-i icra edir.
     * Limit dolubsa — BulkheadRejectedException atır.
     *
     * @template T
     * @param callable(): T $callback İcra olunacaq əməliyyat
     * @return T Callback-in nəticəsi
     *
     * @throws BulkheadRejectedException Limit dolubsa
     *
     * İSTİFADƏ:
     * ```php
     * $bulkhead = new BulkheadPattern('payment-gateway', maxConcurrent: 20);
     *
     * $result = $bulkhead->execute(function () use ($paymentData) {
     *     return Http::timeout(15)->post('https://payment.api/charge', $paymentData);
     * });
     * ```
     */
    public function execute(callable $callback): mixed
    {
        $cacheKey = "bulkhead:{$this->name}:concurrent";

        // Cari eyni vaxtlı request sayını al
        $current = (int) Cache::get($cacheKey, 0);

        if ($current >= $this->maxConcurrent) {
            Log::warning("Bulkhead rədd etdi — limit dolub", [
                'bulkhead' => $this->name,
                'current' => $current,
                'max' => $this->maxConcurrent,
            ]);

            throw new BulkheadRejectedException($this->name, $this->maxConcurrent);
        }

        // Counter-i artır (atomic əməliyyat)
        Cache::increment($cacheKey);

        /**
         * TTL (Time To Live) — counter-in avtomatik silinmə müddəti.
         * Əgər proses çöksə və counter azaldılmazsa (finally bloku işləməzsə),
         * TTL keçdikdə counter avtomatik sıfırlanır.
         * Bu, "stuck counter" probleminin qarşısını alır.
         *
         * maxWaitSeconds + 60: əlavə buffer — normal halda finally counter-i azaldır.
         * TTL yalnız "emergency reset" üçündür.
         */
        Cache::put($cacheKey, Cache::get($cacheKey, 1), $this->maxWaitSeconds + 60);

        try {
            return $callback();
        } finally {
            /**
             * FINALLY BLOKU — həmişə icra olunur (exception olsa belə).
             * Counter-i azaldır ki, növbəti request üçün yer açılsın.
             *
             * Əgər finally işləməzsə (proses kill olunsa):
             * → TTL keçəndən sonra counter sıfırlanır.
             * → Bu, self-healing (özünü bərpa) mexanizmidir.
             */
            Cache::decrement($cacheKey);
        }
    }

    /**
     * Bulkhead-in cari vəziyyəti — monitoring üçün.
     */
    public function status(): array
    {
        $cacheKey = "bulkhead:{$this->name}:concurrent";
        $current = (int) Cache::get($cacheKey, 0);

        return [
            'name' => $this->name,
            'current_concurrent' => $current,
            'max_concurrent' => $this->maxConcurrent,
            'utilization_percent' => $this->maxConcurrent > 0
                ? round(($current / $this->maxConcurrent) * 100, 1)
                : 0,
            'is_full' => $current >= $this->maxConcurrent,
        ];
    }

    /**
     * Counter-i sıfırla — admin/debug əməliyyatı.
     * Production-da ehtiyatla istifadə et!
     */
    public function reset(): void
    {
        Cache::forget("bulkhead:{$this->name}:concurrent");
    }
}

/**
 * BULKHEAD REJECTED EXCEPTION
 * =============================
 * Bulkhead limiti dolduqda atılır.
 *
 * HTTP CAVABI: 503 Service Unavailable
 * "Server çox yüklüdür, bir az sonra yenidən cəhd edin."
 *
 * Klient tərəfdə: Retry with exponential backoff.
 */
final class BulkheadRejectedException extends \RuntimeException
{
    public function __construct(
        public readonly string $bulkheadName,
        public readonly int $maxConcurrent,
    ) {
        parent::__construct(
            "Bulkhead '{$bulkheadName}' limiti dolub (max: {$maxConcurrent}). " .
            "Sistem həddindən artıq yüklüdür. Bir az sonra yenidən cəhd edin."
        );
    }
}
