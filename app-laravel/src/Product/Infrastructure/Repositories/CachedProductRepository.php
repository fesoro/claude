<?php

declare(strict_types=1);

namespace Src\Product\Infrastructure\Repositories;

use Src\Product\Domain\Entities\Product;
use Src\Product\Domain\Repositories\ProductRepositoryInterface;
use Src\Product\Domain\ValueObjects\ProductId;
use Src\Shared\Infrastructure\Cache\TaggedCacheService;

/**
 * CachedProductRepository - Decorator pattern ilə cache əlavə edən repository.
 *
 * ========================================
 * DECORATOR PATTERN (Bəzəyici pattern-i)
 * ========================================
 *
 * Decorator pattern nədir?
 * - Mövcud obyektə YENİ funksionallıq əlavə edir, onu DƏYİŞDİRMƏDƏN.
 * - "Sarğı" (wrapper) kimi düşünün: hədiyyəni kağıza bükürük, hədiyyə dəyişmir.
 *
 * Bu sinifdə necə işləyir?
 * 1. CachedProductRepository eyni interfeysi implementasiya edir (ProductRepositoryInterface).
 * 2. Daxilində "əsl" repository-ni saxlayır (EloquentProductRepository).
 * 3. Hər sorğuda əvvəlcə cache-ə baxır.
 * 4. Cache-də tapılmadıqda, əsl repository-yə müraciət edir.
 * 5. Nəticəni cache-ə yazır.
 *
 * Niyə Decorator istifadə edirik?
 * - EloquentProductRepository-ni dəyişmirik (Open/Closed Principle - OCP).
 * - Cache-siz işləmək istəsək, sadəcə Decorator-u çıxarırıq.
 * - Müxtəlif "qatlar" əlavə edə bilərik: Cache -> Logging -> Eloquent.
 *
 * Vizual olaraq:
 *   Controller -> CachedProductRepository -> EloquentProductRepository -> DB
 *                 (cache qatı)               (əsl məlumat mənbəyi)
 *
 * Alternativ: Əgər Decorator olmasaydı, cache məntiqini birbaşa
 * EloquentProductRepository-yə yazmalı idik (Single Responsibility pozulur).
 *
 * ========================================
 * REFAKTOR: Cache Facade → TaggedCacheService
 * ========================================
 *
 * Əvvəlki versiyada birbaşa Cache facade istifadə edirdik.
 * Problem: Hər cache açarını ayrı-ayrı silməli idik:
 *   Cache::forget('product:abc-123');
 *   Cache::forget('product:all');
 *   // Yeni cache açarı əlavə etsək, buranı da yeniləməli idik!
 *
 * İndi TaggedCacheService ilə 'products' tag-ı istifadə edirik:
 *   $this->cache->invalidateTag('products');
 *   // BÜTÜN product cache-ləri silinir — heç bir açarı yadda saxlamaq lazım deyil!
 *
 * Bu yanaşmanın üstünlükləri:
 * 1. Yeni cache açarı əlavə etdikdə invalidasiya məntiqi pozulmur
 * 2. Bir əməliyyat ilə əlaqəli bütün cache-lər silinir
 * 3. Cache açarlarının siyahısını idarə etmək lazım deyil
 * 4. Test yazmaq asanlaşır (TaggedCacheService mock edilə bilər)
 */
class CachedProductRepository implements ProductRepositoryInterface
{
    /** Cache-in müddəti (saniyə ilə). 3600 = 1 saat */
    private const CACHE_TTL = 3600;

    /** Cache açarı prefiksi - digər cache-lərlə toqquşmanın qarşısını alır */
    private const CACHE_PREFIX = 'product:';

    /** Cache tag-ı — bütün product cache-lərini qruplaşdırır */
    private const CACHE_TAG = 'products';

    /**
     * @param ProductRepositoryInterface $inner Əsl repository (Eloquent) — Decorator-un "bükdüyü" obyekt
     * @param TaggedCacheService         $cache Tag əsaslı cache xidməti
     */
    public function __construct(
        private readonly ProductRepositoryInterface $inner,
        private readonly TaggedCacheService $cache,
    ) {
    }

    /**
     * Əvvəlcə cache-dən axtarır, tapılmadıqda əsl repository-yə müraciət edir.
     *
     * TaggedCacheService::remember() belə işləyir:
     * 1. Verilən tag və açarla (key) cache-ə baxır.
     * 2. Tapıldısa, cache-dəki dəyəri qaytarır (DB-yə müraciət etmir).
     * 3. Tapılmadısa, callback-i icra edir, nəticəni tag ilə cache-ə yazır və qaytarır.
     *
     * Tag istifadəsinin faydası: Bu cache yazısı 'products' tag-ına bağlıdır.
     * invalidateTag('products') çağırıldıqda bu cache də avtomatik silinəcək.
     */
    public function findById(ProductId $id): ?Product
    {
        $cacheKey = self::CACHE_PREFIX . $id->value();

        return $this->cache->remember(
            tags: [self::CACHE_TAG],
            key: $cacheKey,
            ttl: self::CACHE_TTL,
            callback: fn() => $this->inner->findById($id),
        );
    }

    /**
     * Məhsulu saxlayır və 'products' tag-ına aid BÜTÜN cache-i təmizləyir.
     *
     * ƏVVƏLKİ YANAŞMA (Cache facade ilə):
     *   Cache::forget('product:' . $id);     // tək məhsul
     *   Cache::forget('product:all');          // siyahı
     *   // Başqa cache açarı varsa, onu da əl ilə silməli idik!
     *
     * YENİ YANAŞMA (Tag əsaslı):
     *   $this->cache->invalidateTag('products');
     *   // Bütün product cache-ləri bir əməliyyatla silinir!
     *
     * "There are only two hard things in Computer Science:
     *  cache invalidation and naming things." — Phil Karlton
     *
     * Tag əsaslı invalidasiya bu "çətin problemi" xeyli asanlaşdırır.
     */
    public function save(Product $product): void
    {
        /** Əsl repository-yə saxlayırıq */
        $this->inner->save($product);

        /**
         * 'products' tag-ına aid BÜTÜN cache-ləri silirik.
         *
         * Bu, həm product:{id}, həm product:all, həm də gələcəkdə
         * əlavə ediləcək istənilən product cache-ini əhatə edir.
         */
        $this->cache->invalidateTag(self::CACHE_TAG);
    }

    /**
     * Bütün məhsulları cache-dən oxuyur, yoxdursa DB-dən alır.
     *
     * @return Product[]
     */
    public function findAll(): array
    {
        return $this->cache->remember(
            tags: [self::CACHE_TAG],
            key: self::CACHE_PREFIX . 'all',
            ttl: self::CACHE_TTL,
            callback: fn() => $this->inner->findAll(),
        );
    }
}
