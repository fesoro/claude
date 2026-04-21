<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FORCE JSON RESPONSE MIDDLEWARE
 * ==============================
 * Bu middleware bütün API sorğularında cavabın JSON formatında olmasını təmin edir.
 *
 * ═══════════════════════════════════════════════════════════════════
 * PROBLEM: NƏYƏ BU MİDDLEWARE LAZIMDIR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * Laravel default olaraq request-in "Accept" header-inə baxır:
 * - Accept: application/json → JSON cavab qaytarır.
 * - Accept: text/html (və ya heç nə) → HTML cavab qaytarır.
 *
 * PROBLEM BUDUR:
 * API client (Postman, mobil tətbiq) "Accept" header-i göndərməsə,
 * Laravel xəta baş verdikdə HTML səhifə qaytarır (404, 500 və s.).
 * Bu, API client üçün faydasızdır — JSON gözləyir, HTML alır.
 *
 * HƏLL:
 * Bu middleware HƏR sorğuya avtomatik "Accept: application/json" əlavə edir.
 * Beləliklə, Laravel HƏMİŞƏ JSON formatında cavab qaytarır — hətta xəta olsa belə.
 *
 * ═══════════════════════════════════════════════════════════════════
 * NÜMUNƏ (middleware OLMADAN):
 * ═══════════════════════════════════════════════════════════════════
 *
 * GET /api/users/999 (mövcud olmayan istifadəçi)
 * Accept header yoxdur:
 *
 * CAVAB: HTML 404 səhifəsi (Laravel-in default Blade template-i)
 * <!DOCTYPE html><html>...Sorry, the page you are looking for could not be found...</html>
 *
 * ═══════════════════════════════════════════════════════════════════
 * NÜMUNƏ (middleware İLƏ):
 * ═══════════════════════════════════════════════════════════════════
 *
 * GET /api/users/999
 * Middleware avtomatik Accept: application/json əlavə edir:
 *
 * CAVAB: JSON formatında xəta
 * { "message": "Not Found" }
 *
 * ═══════════════════════════════════════════════════════════════════
 * MİDDLEWARE NECƏ İŞLƏYİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * Middleware — HTTP sorğusunun "süzgəc"idir.
 * Sorğu Controller-ə çatmadan əvvəl middleware-lərdən keçir:
 *
 * Request → [ForceJsonResponse] → [EnsureApiVersion] → [auth:sanctum] → Controller
 *                                                                          ↓
 * Response ← [ForceJsonResponse] ← [EnsureApiVersion] ← [auth:sanctum] ← Controller
 *
 * handle() metodu $next($request) çağırır — bu, növbəti middleware-ə keçid deməkdir.
 * $next-dən əvvəl olan kod: Request işlənir (sorğu Controller-ə getməzdən əvvəl).
 * $next-dən sonra olan kod: Response işlənir (Controller cavab qaytardıqdan sonra).
 */
class ForceJsonResponse
{
    /**
     * Gələn sorğunu işlə.
     *
     * @param Request $request — HTTP sorğusu
     * @param Closure $next — Növbəti middleware-ə keçid funksiyası
     * @return Response — HTTP cavabı
     */
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * Request-in "Accept" header-ini "application/json" olaraq təyin edirik.
         * Bu, Laravel-ə deyir: "Bu client JSON cavab gözləyir."
         *
         * headers->set() mövcud header-i əvəz edir və ya yenisini əlavə edir.
         * Beləliklə, client nə göndərsə göndərsin, server JSON qaytaracaq.
         */
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
