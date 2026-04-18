<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Infrastructure\FeatureFlags\FeatureFlag;
use Symfony\Component\HttpFoundation\Response;

/**
 * FeatureFlagMiddleware - Route səviyyəsində xüsusiyyət bayraqlarını yoxlayan middleware.
 *
 * ================================================================
 * ROUTE-SƏVİYYƏSİNDƏ FEATURE GATING (Xüsusiyyət qapılaması)
 * ================================================================
 *
 * Bu middleware route-a giriş zamanı feature flag-ı yoxlayır.
 * Flag deaktivdirsə, istifadəçi həmin route-a daxil ola bilmir.
 *
 * İstifadə nümunəsi (routes/api.php):
 *
 *   // PayPal ödənişi yalnız flag aktiv olduqda işləyir
 *   Route::post('/payments/paypal', PayPalController::class)
 *       ->middleware('feature:payment_paypal_enabled');
 *
 *   // Yeni sifariş axını yalnız flag aktiv olduqda
 *   Route::post('/orders/new-flow', NewOrderController::class)
 *       ->middleware('feature:new_order_flow');
 *
 * Bu yanaşmanın üstünlükləri:
 * ─────────────────────────────
 * 1. Controller-ə toxunmuruq — if/else yazmağa ehtiyac yoxdur.
 * 2. Route səviyyəsində deaktiv edirik — controller koduna çatmır belə.
 * 3. Bir sətir ilə istənilən route-u feature flag-a bağlayırıq.
 *
 * Alternativ: Controller daxilində yoxlamaq:
 *   if (!$featureFlag->isEnabled('payment_paypal_enabled')) {
 *       abort(404);
 *   }
 * Bu işləyir amma hər controller-ə əlavə etmək lazımdır (DRY pozulur).
 * Middleware ilə bir dəfə yazırıq, hər yerdə istifadə edirik.
 *
 * NİYƏ 404 QAYTARIRIQ (403 deyil)?
 * ───────────────────────────────────
 * 403 (Forbidden) — "bu resurs var amma icazən yoxdur" deməkdir.
 * 404 (Not Found) — "bu resurs mövcud deyil" deməkdir.
 *
 * Deaktiv edilmiş xüsusiyyət üçün 404 daha məntiqlidir:
 * - İstifadəçi bilməməlidir ki, belə bir endpoint mövcuddur.
 * - Təhlükəsizlik baxımından endpoint-in varlığını gizlədirik.
 * - Bu, "security through obscurity" deyil, sadəcə lazımsız informasiya vermirik.
 */
class FeatureFlagMiddleware
{
    public function __construct(
        private readonly FeatureFlag $featureFlag,
    ) {
    }

    /**
     * Route-a giriş zamanı feature flag-ı yoxlayır.
     *
     * @param Request $request  HTTP sorğusu
     * @param Closure $next     Növbəti middleware/controller
     * @param string  $feature  Feature flag adı (route-dan gəlir)
     *
     * Route tərəfdən parametr ötürülməsi:
     *   ->middleware('feature:payment_paypal_enabled')
     *                         ^^^^^^^^^^^^^^^^^^^^^^^^
     *                         Bu hissə $feature parametrinə düşür
     *
     * Laravel middleware parametrlərini ":" ilə ayırır.
     * Birden çox parametr olsa, "," ilə ayrılır:
     *   ->middleware('role:admin,editor')
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        /**
         * Feature flag deaktivdirsə, 404 qaytarırıq.
         *
         * abort(404) — Symfony HttpException atır, Laravel bunu tutub
         * 404 səhifəsi göstərir (API-da JSON cavab qaytarır).
         */
        if (!$this->featureFlag->isEnabled($feature)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        /** Flag aktivdir — sorğunu controller-ə ötürürük */
        return $next($request);
    }
}
