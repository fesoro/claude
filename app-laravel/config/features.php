<?php

/**
 * Feature Flags (Xüsusiyyət Bayraqları) konfiqurasiyası.
 *
 * ================================================================
 * BU FAYL NƏ ÜÇÜNDÜR?
 * ================================================================
 *
 * Bu fayl xüsusiyyət bayraqlarının DEFOLT dəyərlərini saxlayır.
 * FeatureFlag xidməti əvvəlcə cache/Redis-ə baxır, tapılmadıqda
 * bu fayldakı dəyərləri istifadə edir.
 *
 * Dəyərlərin prioritet sırası:
 *   1. Cache/Redis (admin override) — ən yüksək prioritet
 *   2. Bu fayl (config/features.php) — defolt dəyər
 *   3. false — heç yerdə tapılmadıqda (təhlükəsiz defolt)
 *
 * Yeni xüsusiyyət əlavə etmək:
 * ─────────────────────────────
 * 1. Bu fayla açar-dəyər əlavə edin: 'yeni_feature' => false
 * 2. Defolt olaraq false qoyun (təhlükəsizlik prinsipi)
 * 3. Route-a middleware əlavə edin: ->middleware('feature:yeni_feature')
 * 4. Test etdikdən sonra true edin və ya admin paneldən aktiv edin
 *
 * ADINLANDIRMA QAYDALARI:
 * ───────────────────────
 * - snake_case istifadə edin: 'payment_paypal_enabled' (doğru)
 * - Qısa və aydın olsun: 'new_order_flow' (doğru)
 * - Boolean xarakter daşısın: '_enabled', '_active' sonluqları
 */

return [
    /**
     * PayPal ödəniş metodu aktiv/deaktiv.
     *
     * true = istifadəçilər PayPal ilə ödəniş edə bilər.
     * false = PayPal ödəniş seçimi gizlənir, endpoint 404 qaytarır.
     *
     * Kill switch ssenarisi: PayPal API-da problem varsa,
     * bu flag-ı söndürərək istifadəçiləri digər ödəniş üsullarına yönləndiririk.
     */
    'payment_paypal_enabled' => true,

    /**
     * Yeni sifariş axını (order flow).
     *
     * false = köhnə sifariş prosesi işləyir (stabil).
     * true = yeni sifariş prosesi aktiv olur (test mərhələsində).
     *
     * Bu, tədricən yayılma (gradual rollout) nümunəsidir:
     * - İlk olaraq daxili komanda test edir (false → yalnız dev mühitdə true)
     * - Sonra staging-də true edilir
     * - Nəhayət produksiyada true edilir
     */
    'new_order_flow' => false,

    /**
     * E-poçt bildirişləri aktiv/deaktiv.
     *
     * true = sifariş statusu dəyişdikdə istifadəçiyə e-poçt göndərilir.
     * false = heç bir e-poçt göndərilmir.
     *
     * Söndürmə ssenarisi: E-poçt provayderində (Mailgun, SES) problem varsa,
     * bu flag-ı söndürərək queue-da yığılmanın qarşısını alırıq.
     */
    'email_notifications' => true,

    /**
     * Aşağı stok xəbərdarlıqları.
     *
     * true = məhsul stoku minimum həddə çatdıqda admin bildiriş alır.
     * false = xəbərdarlıq göndərilmir.
     *
     * Bu, kiçik mağazalar üçün lazımsız ola bilər, ona görə flag ilə
     * idarə edirik — hər mağaza öz ehtiyacına görə aktiv/deaktiv edir.
     */
    'low_stock_alerts' => true,
];
