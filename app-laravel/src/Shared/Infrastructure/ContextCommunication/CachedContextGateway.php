<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\ContextCommunication;

use Illuminate\Support\Facades\Cache;

/**
 * CachedContextGateway - Kontekstlər arası çağırışları Redis cache ilə sarğılayan Decorator.
 *
 * ================================================================
 * CROSS-CONTEXT ÇAĞIRIŞLARIN CACHE-LƏNMƏSİ
 * ================================================================
 *
 * Niyə kontekstlər arası çağırışları cache-ləyirik?
 * ─────────────────────────────────────────────────
 *
 * 1. PERFORMANS:
 *    Hər sifariş səhifəsində istifadəçi adı, məhsul adı lazımdır.
 *    Hər dəfə DB sorğusu göndərmək əvəzinə, cache-dən oxuyuruq.
 *    - DB sorğusu: ~2-5ms
 *    - Redis cache: ~0.5ms
 *    - Fərq kiçik görünür, amma saniyədə 1000 sorğuda: 5 saniyə vs 0.5 saniyə.
 *
 * 2. MİCROSERVİCE HAZIRLIĞI:
 *    Gələcəkdə microservice-ə keçsək, hər çağırış HTTP olacaq (~50-200ms).
 *    Cache olmadan sistem çox yavaşlayar.
 *    Cache ilə: əksər çağırışlar ~0.5ms qalır (Redis-dən).
 *
 * 3. DİGƏR KONTEKSTIN YÜKÜNÜ AZALTMA:
 *    Product kontekstinə 100 fərqli kontekstdən sorğu gəlirsə,
 *    cache olmadan Product-ın DB-si çökə bilər.
 *    Cache ilə: əksər sorğular DB-yə çatmır.
 *
 * 4. DAYANIQLIIQ (Resilience):
 *    Microservice-də digər servis "düşə" bilər.
 *    Cache-dəki məlumat köhnə olsa da, heç olmasa bir şey qaytara bilərik
 *    (stale cache better than no data — köhnə cache heç nədən yaxşıdır).
 *
 * DECORATOR PATTERN (Bəzəyici Pattern-i):
 * ────────────────────────────────────────
 * Bu sinif InternalContextGateway-i "bürüyür" (wrap edir):
 *
 *   Controller
 *     → CachedContextGateway (cache qatı)
 *       → InternalContextGateway (DB sorğusu)
 *         → DB
 *
 * Əgər cache-də var: Controller → CachedContextGateway → Redis → Cavab (DB-yə getmir!)
 * Əgər cache-də yox: Controller → CachedContextGateway → InternalContextGateway → DB → Redis-ə yaz → Cavab
 *
 * Cache TTL (Time To Live — yaşama müddəti):
 * ───────────────────────────────────────────
 * - Çox qısa (10 san): Həmişə təzə, amma cache-in faydası az.
 * - Çox uzun (1 saat): Sürətli, amma köhnə məlumat riski var.
 * - 300 saniyə (5 dəqiqə): Yaxşı balans — əksər hallarda məqbuldur.
 *
 * ƏSAS QAYDA: Cross-context məlumatlar adətən "oxumaq üçün"dür (read-only).
 * Dəyişdirmə yalnız məlumatın "sahibi" olan kontekst tərəfindən olmalıdır.
 * Bu səbəbdən cache-ləmək təhlükəsizdir — çox tez-tez dəyişmir.
 */
class CachedContextGateway implements ContextGateway
{
    /**
     * Cache müddəti (saniyə). 300 = 5 dəqiqə.
     *
     * Niyə 5 dəqiqə?
     * - İstifadəçi adı, məhsul qiyməti hər saniyə dəyişmir.
     * - 5 dəqiqəlik köhnəlik əksər hallarda məqbuldur.
     * - Microservice-ə keçdikdə bu dəyəri artıra bilərik (HTTP yavaş olduğu üçün).
     */
    private const CACHE_TTL = 300;

    /** Cache açar prefiksi — digər cache-lərlə qarışmasın deyə */
    private const CACHE_PREFIX = 'context:';

    /**
     * @param ContextGateway $inner Daxili gateway (InternalContextGateway)
     *
     * Niyə ContextGateway interfeysi qəbul edir, InternalContextGateway yox?
     * - Liskov Substitution Principle: hər hansı implementasiyanı ötürə bilərik.
     * - Test-də mock ötürə bilərik.
     * - Gələcəkdə HttpContextGateway-i də cache-ləyə bilərik.
     */
    public function __construct(
        private readonly ContextGateway $inner,
    ) {
    }

    /**
     * İstifadəçi məlumatını cache-dən oxuyur, yoxdursa daxili gateway-dən alır.
     *
     * Cache açarı formatı: "context:user:{userId}"
     * Bu format unikaldır və digər cache açarları ilə toqquşmur.
     */
    public function getUserById(string $userId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . "user:{$userId}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->inner->getUserById($userId)
        );
    }

    /**
     * Məhsul məlumatını cache-dən oxuyur, yoxdursa daxili gateway-dən alır.
     *
     * Misal:
     * - İlk çağırış: Redis-də yoxdur → DB-dən oxuyur → Redis-ə yazır → qaytarır.
     * - 2-ci çağırış (5 dəq ərzində): Redis-dən oxuyur → qaytarır (DB-yə getmir).
     * - 5 dəqiqədən sonra: TTL bitir → Redis silir → yenidən DB-dən oxuyur.
     */
    public function getProductById(string $productId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . "product:{$productId}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->inner->getProductById($productId)
        );
    }

    /**
     * Sifariş məlumatını cache-dən oxuyur, yoxdursa daxili gateway-dən alır.
     *
     * Diqqət: Sifariş statusu tez-tez dəyişə bilər (pending → paid → shipped).
     * Bu halda TTL-i daha qısa etmək olar, və ya event-based invalidation
     * istifadə etmək olar (sifariş statusu dəyişdikdə cache-i silmək).
     */
    public function getOrderById(string $orderId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . "order:{$orderId}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->inner->getOrderById($orderId)
        );
    }
}
