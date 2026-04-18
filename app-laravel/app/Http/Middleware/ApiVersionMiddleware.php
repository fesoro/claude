<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API VERSİYALAMA MİDDLEWARE-İ
 * ================================
 * Bu middleware API versiyasını təyin edib config-ə yazır.
 *
 * ═══════════════════════════════════════════════════════════════════
 * API VERSİYALAMA STRATEGİYALARI
 * ═══════════════════════════════════════════════════════════════════
 *
 * API versiyalama — backend-in köhnə klinetləri qırmadan yeni funksionallıq
 * əlavə etməsinə imkan verən mexanizmdir. 3 əsas yanaşma var:
 *
 * 1. URL PATH VERSİYALAMA (Bu proyektdə istifadə olunur):
 *    Nümunə: /api/v1/products, /api/v2/products
 *    ÜSTÜNLÜKLƏRİ:
 *      - Ən sadə və ən çox istifadə olunan yanaşma
 *      - URL-dən versiyanı görmək asandır (debugging üçün rahat)
 *      - Cache proxy-lər (CDN, Varnish) URL-ə görə cache edə bilər
 *      - Browser-da birbaşa test etmək olur
 *    ÇATIŞMAZLIQLARI:
 *      - URL dəyişir — köhnə linklər işləməz
 *      - REST puristi-lər deyir: "resurs eynidir, URL dəyişməməlidir"
 *      - Hər versiya üçün ayrı route faylı lazımdır
 *
 * 2. HEADER VERSİYALAMA:
 *    Nümunə: X-API-Version: 2 və ya Accept: application/vnd.myapp.v2+json
 *    ÜSTÜNLÜKLƏRİ:
 *      - URL təmiz qalır (/api/products hər zaman eynidir)
 *      - REST prinsiplərinə daha uyğundur
 *      - Daha çevik — eyni URL-dən fərqli versiyalar
 *    ÇATIŞMAZLIQLARI:
 *      - Browser-da test etmək çətindir (header göndərmək lazımdır)
 *      - Cache etmək çətinləşir (Vary header lazımdır)
 *      - Sənədləşdirmə daha mürəkkəbdir
 *
 * 3. QUERY PARAMETER VERSİYALAMA:
 *    Nümunə: /api/products?version=2
 *    ÜSTÜNLÜKLƏRİ:
 *      - URL strukturu dəyişmir
 *      - İmplementasiya asandır
 *    ÇATIŞMAZLIQLARI:
 *      - Query string-lər optional-dır — unutmaq asan
 *      - Cache üçün query string problemlidir
 *      - Ən az istifadə olunan yanaşma
 *
 * BU PROYEKTDƏ:
 * URL path əsas metod kimi istifadə olunur (/api/v1/products).
 * Əlavə olaraq X-API-Version header-i də dəstəklənir (fallback kimi).
 * Header üstünlük almır — URL-dəki versiya əsasdır.
 *
 * ═══════════════════════════════════════════════════════════════════
 * NECƏ İŞLƏYİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. Middleware URL-dən versiyanı oxuyur: /api/v1/... → "v1"
 * 2. URL-də versiya yoxdursa, X-API-Version header-ə baxır
 * 3. Heç biri yoxdursa, default versiya (v1) istifadə olunur
 * 4. Tapılan versiya config('api.version')-ə yazılır
 * 5. Həmçinin response header-ə X-API-Version əlavə olunur
 *
 * Bu sayədə istənilən yerdə (Controller, Service, Handler)
 * config('api.version') ilə cari versiyanı öyrənmək mümkündür.
 */
class ApiVersionMiddleware
{
    /**
     * Dəstəklənən API versiyaları.
     * Yeni versiya əlavə edəndə bu siyahını yeniləyin.
     *
     * @var string[]
     */
    private const SUPPORTED_VERSIONS = ['v1'];

    /**
     * Default versiya — əgər heç bir versiya təyin olunmayıbsa.
     * Yeni klientlər avtomatik ən son stabil versiyaya yönləndirilir.
     */
    private const DEFAULT_VERSION = 'v1';

    /**
     * Sorğunu emal et.
     *
     * MIDDLEWARE AXINI:
     * Request → ApiVersionMiddleware → Controller → Response
     *                ↓
     *        config('api.version') = 'v1'
     *                ↓
     *        Response header: X-API-Version: v1
     *
     * @param Request $request Gələn HTTP sorğusu
     * @param Closure $next Növbəti middleware və ya controller
     * @return Response HTTP cavabı
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1-ci ADDIM: URL-dən versiyanı çıxart
        // /api/v1/products → 'v1', /api/v2/orders → 'v2'
        // preg_match() — regex ilə URL-dən v1, v2, v3 kimi versiya nömrəsini tapır
        $version = $this->extractVersionFromUrl($request);

        // 2-ci ADDIM: URL-də versiya yoxdursa, header-ə bax
        // Bəzi klientlər (mobil app-lər) header ilə versiya göndərə bilər
        if ($version === null) {
            $version = $this->extractVersionFromHeader($request);
        }

        // 3-cü ADDIM: Heç biri yoxdursa, default versiya
        if ($version === null) {
            $version = self::DEFAULT_VERSION;
        }

        // 4-cü ADDIM: Versiyanın dəstəkləndiyini yoxla
        // Dəstəklənməyən versiya istənirsə, 400 xətası qaytar
        if (!in_array($version, self::SUPPORTED_VERSIONS, true)) {
            return response()->json([
                'success' => false,
                'message' => "API versiyası '{$version}' dəstəklənmir. Dəstəklənən versiyalar: "
                    . implode(', ', self::SUPPORTED_VERSIONS),
            ], 400);
        }

        // 5-ci ADDIM: Versiyanı config-ə yaz
        // config() runtime-da dəyər set etməyə imkan verir
        // Bundan sonra istənilən yerdə config('api.version') ilə oxumaq olar
        config(['api.version' => $version]);

        // 6-cı ADDIM: Sorğunu növbəti middleware/controller-ə ötür
        /** @var Response $response */
        $response = $next($request);

        // 7-ci ADDIM: Response header-ə versiyanı əlavə et
        // Klient hansı versiyada cavab aldığını bilsin
        $response->headers->set('X-API-Version', $version);

        return $response;
    }

    /**
     * URL path-dən versiyanı çıxart.
     *
     * REGEX İZAHI: /api\/(v\d+)/
     *   api\/  → 'api/' mətni (/ escape olunub)
     *   (v\d+) → 'v' hərfi + bir və ya daha çox rəqəm, qrup kimi tutulur
     *
     * Nümunələr:
     *   /api/v1/products  → 'v1'
     *   /api/v2/orders    → 'v2'
     *   /api/products     → null (versiya yoxdur)
     *
     * @param Request $request HTTP sorğusu
     * @return string|null Tapılan versiya və ya null
     */
    private function extractVersionFromUrl(Request $request): ?string
    {
        $path = $request->path();

        if (preg_match('/api\/(v\d+)/', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * X-API-Version header-dən versiyanı oxu.
     *
     * Header formatı: X-API-Version: v1 və ya X-API-Version: 1
     * Əgər sadəcə rəqəm göndərilibsə (1, 2), avtomatik 'v' prefix əlavə olunur.
     *
     * @param Request $request HTTP sorğusu
     * @return string|null Header-dəki versiya və ya null
     */
    private function extractVersionFromHeader(Request $request): ?string
    {
        $headerValue = $request->header('X-API-Version');

        if ($headerValue === null) {
            return null;
        }

        // Əgər sadəcə rəqəm göndərilibsə (1, 2), 'v' əlavə et
        // is_numeric() — '1', '2' kimi string rəqəmləri tanıyır
        if (is_numeric($headerValue)) {
            return 'v' . $headerValue;
        }

        return $headerValue;
    }
}
