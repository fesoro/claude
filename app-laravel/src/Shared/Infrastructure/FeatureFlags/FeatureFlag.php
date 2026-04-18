<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\FeatureFlags;

use Illuminate\Support\Facades\Cache;

/**
 * FeatureFlag - Xüsusiyyət bayraqları (Feature Flags) xidməti.
 *
 * ================================================================
 * FEATURE FLAGS NƏDİR? (Ətraflı izah)
 * ================================================================
 *
 * Feature Flag — kodda xüsusiyyətləri AKTIV və ya DEAKTİV etmək üçün
 * istifadə olunan "açar-söndürücü" mexanizmidir.
 *
 * Real həyat analogiyası:
 * Evinizdəki işıq açarı kimi düşünün. İşığı söndürsəniz, lampa yerindədir
 * amma işləmir. Feature flag da belədir — kod deploy edilib amma aktiv deyil.
 *
 * ─────────────────────────────────────────────
 * 1. TƏDRICƏN YAYILMA (Gradual Rollout)
 * ─────────────────────────────────────────────
 * Yeni xüsusiyyəti hamıya birden açmaq risklidir.
 * Feature flag ilə:
 *   - Əvvəlcə yalnız daxili komandaya açırıq
 *   - Sonra istifadəçilərin 10%-nə
 *   - Problem yoxdursa 50%-ə, sonra 100%-ə
 *
 * Nümunə: Instagram yeni "Reels" funksiyasını əvvəlcə yalnız
 * Braziliyada açdı, sonra dünyaya yaydı. Bu, feature flag ilə olur.
 *
 * ─────────────────────────────────────────────
 * 2. KILL SWITCH (Təcili söndürmə)
 * ─────────────────────────────────────────────
 * Yeni ödəniş sistemi problrm yaradırsa, deploy etmədən söndürə bilərik:
 *   - Admin paneldən: "PayPal ödənişi: OFF"
 *   - 1 saniyəyə problem həll olur (yeni deploy lazım deyil!)
 *
 * Deploy etmək 5-30 dəqiqə çəkir. Kill switch 1 saniyədir.
 * Produksiyada bu fərq böyük itkilərin qarşısını ala bilər.
 *
 * ─────────────────────────────────────────────
 * 3. A/B TESTİ
 * ─────────────────────────────────────────────
 * İstifadəçilərin yarısına yeni dizaynı, yarısına köhnəni göstəririk:
 *   - A qrupu: köhnə sifariş forması
 *   - B qrupu: yeni sifariş forması
 *   - Hansı daha çox satış gətirir? Statistikaya baxırıq.
 *
 * Bu, məlumat əsaslı qərar verməyə imkan verir (data-driven decisions).
 *
 * ─────────────────────────────────────────────
 * ARXITEKTURA: CONFIG + CACHE LAYİHƏSİ
 * ─────────────────────────────────────────────
 *
 * Bu xidmət iki mənbədən oxuyur:
 *
 * 1. config/features.php — Defolt dəyərlər (koda yazılıb, deploy ilə dəyişir)
 * 2. Cache/Redis — Override dəyərləri (deploy etmədən dəyişir)
 *
 * Oxuma sırası:
 *   Cache-ə bax → Tapıldı? → Cache-dəki dəyəri qaytar
 *                → Tapılmadı? → Config-dəki defolt dəyəri qaytar
 *
 * Bu yanaşma ilə:
 *   - Deploy etmədən flag-ı dəyişə bilərik (cache vasitəsilə)
 *   - Cache boşaldıqda config-dəki defolt dəyər işləyir (təhlükəsiz fallback)
 */
class FeatureFlag
{
    /** Cache açarı prefiksi — digər cache-lərlə toqquşmanın qarşısını alır */
    private const CACHE_PREFIX = 'feature_flag:';

    /**
     * Xüsusiyyətin aktiv olub-olmadığını yoxlayır.
     *
     * @param string $feature Xüsusiyyətin adı (məs: 'payment_paypal_enabled')
     * @return bool Aktiv = true, Deaktiv = false
     *
     * Nümunə istifadə:
     *   if ($featureFlag->isEnabled('payment_paypal_enabled')) {
     *       // PayPal ödənişi aktiv — göstər
     *   } else {
     *       // PayPal söndürülüb — gizlət
     *   }
     */
    public function isEnabled(string $feature): bool
    {
        /**
         * Əvvəlcə cache-ə baxırıq — admin override etmiş ola bilər.
         *
         * Cache::get() tapılmadıqda null qaytarır.
         * null !== null yanlışdır, ona görə config-ə keçirik.
         */
        $cached = Cache::get(self::CACHE_PREFIX . $feature);

        if ($cached !== null) {
            /** Cache-dən boolean-a çeviririk (cache string saxlaya bilər) */
            return (bool) $cached;
        }

        /**
         * Cache-də yoxdursa, config/features.php-dən oxuyurıq.
         * Config-də də yoxdursa, defolt olaraq false qaytarırıq.
         *
         * Niyə defolt false? Təhlükəsizlik prinsipi:
         * "Naməlum xüsusiyyət = deaktiv" — yanlışlıqla aktiv olmasın.
         */
        return (bool) config("features.{$feature}", false);
    }

    /**
     * Xüsusiyyəti aktiv edir (cache-ə yazır).
     *
     * Bu metod deploy etmədən flag-ı dəyişməyə imkan verir.
     * Cache-ə yazırıq, config fayla toxunmuruq.
     *
     * İstifadə ssenarisi: Admin paneldən "PayPal aktiv et" düyməsi.
     *
     * @param string $feature Xüsusiyyətin adı
     */
    public function enable(string $feature): void
    {
        /**
         * Cache-ə true (1) yazırıq.
         * Cache::forever() — müddətsiz saxlayır (TTL yoxdur).
         *
         * Niyə forever? Flag-lar nadirən dəyişir, TTL ilə vaxtı
         * keçəndə config-dəki defolt dəyərə qayıdacaq ki, bu gözlənilməzdir.
         */
        Cache::forever(self::CACHE_PREFIX . $feature, true);
    }

    /**
     * Xüsusiyyəti deaktiv edir (cache-ə yazır).
     *
     * KILL SWITCH ssenarisi: Problem yaranan xüsusiyyəti 1 saniyəyə söndürürük.
     *
     * Nümunə:
     *   // Produksiyada PayPal problem yaradır!
     *   $featureFlag->disable('payment_paypal_enabled');
     *   // 1 saniyəyə PayPal söndürüldü, deploy lazım deyil!
     *
     * @param string $feature Xüsusiyyətin adı
     */
    public function disable(string $feature): void
    {
        Cache::forever(self::CACHE_PREFIX . $feature, false);
    }
}
