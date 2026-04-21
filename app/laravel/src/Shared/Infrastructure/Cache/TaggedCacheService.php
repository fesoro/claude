<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * TaggedCacheService - Tag əsaslı cache idarəetmə xidməti.
 *
 * ================================================================
 * CACHE İNVALİDASİYA STRATEGİYALARI (Ətraflı izah)
 * ================================================================
 *
 * "There are only two hard things in Computer Science:
 *  cache invalidation and naming things." — Phil Karlton
 *
 * Cache-in ən çətin hissəsi ONU NƏ VAXT SİLMƏKdir. Üç əsas strategiya var:
 *
 * ─────────────────────────────────────────────
 * 1. TTL (Time-To-Live) — Vaxt əsaslı
 * ─────────────────────────────────────────────
 * Cache-ə müddət veririk: "1 saat sonra özü silinsin."
 *
 * Üstünlüyü: Ən sadə yanaşma, heç bir əlavə məntiq lazım deyil.
 * Çatışmazlığı: TTL bitənədək köhnə məlumat qaytarıla bilər (stale data).
 *
 * Nümunə: Məhsul qiyməti 100 AZN → admin 150 AZN edir → amma cache-də
 * hələ 100 AZN göstərir (TTL bitənədək). İstifadəçi 100 AZN görür,
 * amma kassada 150 AZN ödəyir — pis istifadəçi təcrübəsi!
 *
 * Nə vaxt istifadə etməli: Nadir dəyişən, az kritik məlumatlar üçün.
 * Məs: "Ən populyar məhsullar" siyahısı — 5 dəqiqə köhnə olsa problem deyil.
 *
 * ─────────────────────────────────────────────
 * 2. HADISƏ ƏSASLI (Event-Based)
 * ─────────────────────────────────────────────
 * Məlumat dəyişdikdə event atılır, event listener cache-i silir.
 *
 *   ProductUpdated event → CacheInvalidationListener → Cache::forget()
 *
 * Üstünlüyü: Məlumat dəyişən kimi cache yenilənir (real-time).
 * Çatışmazlığı: Hər entity üçün event + listener yazmalıyıq. Hansı cache
 * açarlarını silməli olduğumuzu bilməliyik (bu çətinləşə bilər).
 *
 * Nümunə problem: Məhsul yeniləndikdə silməli olduğumuz cache açarları:
 *   - product:{id} (tək məhsul)
 *   - product:all (siyahı)
 *   - category:{cat_id}:products (kateqoriya məhsulları)
 *   - search:results:... (axtarış nəticələri)
 *   Birini unutsaq, köhnə məlumat qalır!
 *
 * ─────────────────────────────────────────────
 * 3. TAG ƏSASLI (Tag-Based) — ƏN YAXŞI YANAŞMA
 * ─────────────────────────────────────────────
 * Cache açarlarını qruplara (tag-lara) ayırırıq. Tag-ı silmək
 * həmin tag-a aid BÜTÜN cache-ləri silir.
 *
 * Nümunə:
 *   Cache::tags(['products'])->remember('product:abc', ...)
 *   Cache::tags(['products'])->remember('product:all', ...)
 *   Cache::tags(['products', 'categories'])->remember('category:1:products', ...)
 *
 *   // Bir sətir ilə bütün product cache-ləri silinir:
 *   Cache::tags(['products'])->flush();
 *
 * Üstünlüyü:
 *   - Hansı açarların olduğunu bilməyə ehtiyac yoxdur
 *   - Bir tag ilə əlaqəli bütün cache-ləri bir əməliyyatla silirik
 *   - Yeni cache açarı əlavə etdikdə invalidasiya məntiqi pozulmur
 *
 * Çatışmazlığı:
 *   - Yalnız Redis, Memcached kimi tag dəstəkləyən driver-lərdə işləyir
 *   - File və Database cache driver-ləri tag dəstəkləmir
 *
 * ================================================================
 * BU XİDMƏT NƏ EDİR?
 * ================================================================
 *
 * Laravel-in Cache::tags() funksionallığını sarmalayır (wrap edir) və
 * tətbiqin hər yerində vahid interfeys təqdim edir.
 *
 * Niyə birbaşa Cache::tags() istifadə etmirik?
 * 1. Test yazmaq asanlaşır (mock edə bilərik)
 * 2. Cache driver dəyişsə, yalnız bu sinfi dəyişirik
 * 3. Əlavə funksionallıq əlavə edə bilərik (logging, metrics)
 */
class TaggedCacheService
{
    /**
     * Tag-lı cache-dən oxuyur, yoxdursa callback-i icra edib cache-ə yazır.
     *
     * @param array    $tags     Cache tag-ları (məs: ['products'], ['products', 'categories'])
     * @param string   $key      Cache açarı (məs: 'product:abc-123')
     * @param int      $ttl      Cache müddəti saniyə ilə (məs: 3600 = 1 saat)
     * @param callable $callback Cache-də tapılmadıqda icra ediləcək funksiya
     * @return mixed             Cache-dəki və ya callback-dən gələn dəyər
     *
     * Nümunə:
     *   $product = $cacheService->remember(
     *       tags: ['products'],
     *       key: 'product:abc-123',
     *       ttl: 3600,
     *       callback: fn() => $repository->findById('abc-123')
     *   );
     */
    public function remember(array $tags, string $key, int $ttl, callable $callback): mixed
    {
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Verilən tag-a aid BÜTÜN cache yazılarını silir.
     *
     * Bu, tag-based invalidation-ın əsas üstünlüyüdür:
     * Hansı açarların olduğunu bilməyə ehtiyac yoxdur!
     *
     * @param string $tag Silinəcək tag adı (məs: 'products')
     *
     * Nümunə: Məhsul yeniləndikdə:
     *   $cacheService->invalidateTag('products');
     *   // Bu, aşağıdakı BÜTÜN cache-ləri silir:
     *   // - product:abc-123
     *   // - product:all
     *   // - product:featured
     *   // - ... 'products' tag-ı olan hər şey
     */
    public function invalidateTag(string $tag): void
    {
        Cache::tags([$tag])->flush();
    }

    /**
     * BÜTÜN tag-lı cache-ləri silir.
     *
     * DİQQƏT: Bu metod çox "ağır" əməliyyatdır!
     * Yalnız aşağıdakı hallarda istifadə edin:
     *   - Böyük data migration-dan sonra
     *   - Sistem yeniləməsindən sonra
     *   - Debug/inkişaf zamanı
     *
     * Produksiyada çox nadir istifadə olunmalıdır!
     */
    public function flush(): void
    {
        Cache::flush();
    }
}
