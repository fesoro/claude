<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API VERSİYALAMA MIDDLEWARE-İ (Header-Based Versioning)
 * ======================================================
 * Bu middleware API versiyasını "X-API-Version" header-indən oxuyur
 * və sorğuya əlavə edir ki, Controller və ya Service istifadə edə bilsin.
 *
 * ═══════════════════════════════════════════════════════════════════
 * API VERSİYALAMA NƏDİR VƏ NƏYƏ LAZIMDIR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * API dəyişdikdə köhnə client-lərin işini pozmamaq üçün versiyalama istifadə olunur.
 *
 * Məsələn: v1-də istifadəçi cavabı belə idi:
 *   { "name": "Orxan" }
 *
 * v2-də dəyişdirdik:
 *   { "first_name": "Orxan", "last_name": "..." }
 *
 * Versiyalama olmasa, köhnə mobil tətbiqlər "name" sahəsini gözləyir, amma yoxdur — xəta!
 * Versiyalama ilə: v1 client köhnə formatı alır, v2 client yenisini.
 *
 * ═══════════════════════════════════════════════════════════════════
 * VERSİYALAMA ÜSULLARI:
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. URL-DƏ VERSİYA (Path-based):
 *    /api/v1/users, /api/v2/users
 *    Üstünlüyü: Aydın və sadə, brauzerdə test etmək asan.
 *    Çatışmazlığı: URL dəyişir, cache problemləri yarana bilər.
 *
 * 2. HEADER-DƏ VERSİYA (Header-based) ← BİZ BUNU İSTİFADƏ EDİRİK:
 *    X-API-Version: v1
 *    Üstünlüyü: URL təmiz qalır, RESTful prinsiplərə uyğundur.
 *    Çatışmazlığı: Brauzerdə birbaşa test etmək çətindir (Postman lazım).
 *
 * 3. QUERY PARAMETER:
 *    /api/users?version=1
 *    Üstünlüyü: Sadə implementasiya.
 *    Çatışmazlığı: Cache problemləri, URL-i "çirkləndirir".
 *
 * 4. ACCEPT HEADER (Content Negotiation):
 *    Accept: application/vnd.myapp.v1+json
 *    Üstünlüyü: HTTP standartlarına ən uyğun.
 *    Çatışmazlığı: Mürəkkəb implementasiya.
 *
 * ═══════════════════════════════════════════════════════════════════
 * BU MİDDLEWARE NECƏ İŞLƏYİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. Client sorğuda "X-API-Version: v2" header-i göndərir.
 * 2. Middleware bu header-i oxuyur.
 * 3. Header yoxdursa, default "v1" qəbul edir (geriyə uyğunluq).
 * 4. Versiyanı request attribute-na əlavə edir.
 * 5. Controller-də $request->attributes->get('api_version') ilə istifadə olunur.
 * 6. Cavab header-inə də versiya əlavə edir ki, client hansı versiyada
 *    cavab aldığını bilsin.
 */
class EnsureApiVersion
{
    /**
     * Default API versiyası.
     * Əgər client "X-API-Version" header-i göndərməsə, bu versiya istifadə olunur.
     * Beləliklə, köhnə client-lər heç bir dəyişiklik etmədən işləməyə davam edir.
     */
    private const string DEFAULT_VERSION = 'v1';

    /**
     * Dəstəklənən API versiyaları.
     * Yeni versiya əlavə etdikdə bu siyahıya daxil edin.
     * Dəstəklənməyən versiya göndərilərsə, default versiyaya düşür.
     */
    private const array SUPPORTED_VERSIONS = ['v1', 'v2'];

    /**
     * Gələn sorğunu işlə.
     *
     * @param Request $request — HTTP sorğusu
     * @param Closure $next — Növbəti middleware-ə keçid
     * @return Response — HTTP cavabı
     */
    public function handle(\Illuminate\Http\Request $request, Closure $next): Response
    {
        /**
         * X-API-Version header-ini oxuyuruq.
         * Əgər header yoxdursa və ya dəstəklənməyən versiya göndərilibsə,
         * default versiyaya (v1) düşürük.
         *
         * "X-" prefiksi — xüsusi (custom) header-lər üçün istifadə olunur.
         * RFC 6648-ə görə "X-" prefiksi artıq tövsiyə olunmur,
         * amma praktikada hələ də geniş istifadə olunur.
         */
        $version = $request->header('X-API-Version', self::DEFAULT_VERSION);

        /**
         * Dəstəklənməyən versiya göndərilibsə, default-a düşür.
         * Alternativ: 400 Bad Request qaytarmaq olar, amma geriyə uyğunluq
         * üçün default versiyaya düşmək daha yaxşı istifadəçi təcrübəsi verir.
         */
        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            $version = self::DEFAULT_VERSION;
        }

        /**
         * Versiyanı request attribute-na əlavə edirik.
         * attributes — request-ə əlavə metadata yerləşdirmək üçündür.
         * Controller-də belə istifadə olunur:
         *   $version = $request->attributes->get('api_version'); // "v1"
         *
         * Attribute header-dən fərqlidir:
         * - Header: Client tərəfindən göndərilir.
         * - Attribute: Server tərəfindən əlavə olunur (daxili istifadə üçün).
         */
        $request->attributes->set('api_version', $version);

        /** @var Response $response */
        $response = $next($request);

        /**
         * Cavab header-inə versiyanı əlavə edirik.
         * Client hansı versiyada cavab aldığını bilsin deyə.
         * Debugging zamanı da faydalıdır — Postman-da görünür.
         */
        $response->headers->set('X-API-Version', $version);

        return $response;
    }
}
