<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Infrastructure\Audit\AuditService;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuditMiddleware - Bütün yazma əməliyyatlarını avtomatik audit log-a yazır.
 *
 * ================================================================
 * MIDDLEWARE NƏ CÜRDÜR MƏLUMATİ TUTUB?
 * ================================================================
 *
 * Laravel-də hər HTTP sorğusu middleware zəncirindən keçir:
 *
 *   İstifadəçi → [Auth] → [CORS] → [Audit] → Controller → Cavab
 *
 * Middleware sorğu (request) və cavab (response) haqqında məlumat tuta bilir:
 *
 * SORĞUDAN (Request):
 * - HTTP metodu: POST, PUT, PATCH, DELETE (yazma əməliyyatları)
 * - URL: /api/products/abc-123 (hansı resursa müraciət edilir)
 * - İstifadəçi: auth()->id() (kim müraciət edir)
 * - IP ünvanı: request->ip() (haradan müraciət edilir)
 * - User-Agent: request->userAgent() (hansı cihaz/brauzer)
 * - Gövdə (body): request->all() (hansı məlumatlar göndərilir)
 *
 * CAVABDAN (Response):
 * - Status kodu: 200 (uğurlu), 422 (validasiya xətası), 500 (server xətası)
 *
 * NİYƏ YALNIZ YAZMA ƏMƏLİYYATLARI?
 * ───────────────────────────────────
 * GET sorğuları məlumatı DƏYİŞMİR, sadəcə oxuyur.
 * Hər GET-i log-a yazsaq:
 *   1. Verilənlər bazası çox tez dolar (minlərlə oxuma sorğusu/saniyə)
 *   2. Audit log-da lazımsız "səs-küy" olur, vacib dəyişiklikləri tapmaq çətinləşir
 *   3. Performans aşağı düşür
 *
 * Yalnız POST, PUT, PATCH, DELETE — yəni məlumatı dəyişən əməliyyatları yazırıq.
 */
class AuditMiddleware
{
    /**
     * Audit log-a yazılmalı olan HTTP metodları.
     *
     * Bu metodlar "yazma" əməliyyatlarıdır — məlumatı dəyişir, yaradır və ya silir.
     * GET və OPTIONS kimi "oxuma" əməliyyatları buraya daxil deyil.
     */
    private const AUDITABLE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * HTTP sorğusunu emal edir və yazma əməliyyatlarını audit log-a yazır.
     *
     * Middleware-in iş axını:
     * 1. Sorğunun HTTP metodunu yoxlayır (GET-ləri keçir)
     * 2. Sorğunu növbəti middleware/controller-ə ötürür
     * 3. Cavab uğurlu olduqda (2xx) audit log-a yazır
     *
     * Vacib: Audit log cavabdan SONRA yazılır (after middleware).
     * Çünki əməliyyat uğursuz olsa (422, 500), log-a yazmağın mənası yoxdur.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** Sorğunu controller-ə ötürürük və cavabı alırıq */
        $response = $next($request);

        /**
         * Yalnız yazma əməliyyatlarını audit edirik.
         * in_array() — HTTP metodu siyahıda varmı yoxlayır.
         */
        if (!in_array($request->method(), self::AUDITABLE_METHODS, true)) {
            return $response;
        }

        /**
         * Yalnız uğurlu əməliyyatları log-a yazırıq.
         *
         * Status kodları:
         * - 2xx (200-299): Uğurlu — log-a yazılır
         * - 4xx (400-499): İstifadəçi xətası (validasiya, icazə yoxdur) — yazılmır
         * - 5xx (500-599): Server xətası — yazılmır (ayrıca error log-da olur)
         */
        if ($response->isSuccessful()) {
            /**
             * Sorğu URL-indən entity növünü və ID-sini çıxarırıq.
             *
             * Nümunə: /api/products/abc-123
             * - segments: ["api", "products", "abc-123"]
             * - entityType: "products"
             * - entityId: "abc-123"
             *
             * Bu, avtomatik şəkildə hansı resursa müraciət edildiyini müəyyən edir.
             */
            $segments = $request->segments();
            $entityType = $segments[1] ?? 'unknown';
            $entityId = $segments[2] ?? 'n/a';

            /**
             * HTTP metodunu audit action-a çeviririk.
             * POST = yaratma, PUT/PATCH = yeniləmə, DELETE = silmə.
             */
            $action = match ($request->method()) {
                'POST'   => 'created',
                'PUT'    => 'updated',
                'PATCH'  => 'updated',
                'DELETE' => 'deleted',
                default  => 'unknown',
            };

            $this->auditService->log(
                userId: $request->user()?->id ? (string) $request->user()->id : null,
                action: $action,
                entityType: $entityType,
                entityId: $entityId,
                oldValues: null,
                newValues: $request->except([
                    /**
                     * Həssas məlumatları HEÇVAXT audit log-a yazmırıq!
                     *
                     * Əgər audit log-a "password" yazsaq və audit_logs cədvəlinə
                     * birisi daxil olsa, bütün parolları görə bilər.
                     * Bu, təhlükəsizlik üçün böyük riskdir.
                     */
                    'password',
                    'password_confirmation',
                    'credit_card',
                    'cvv',
                    '_token',
                ]),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return $response;
    }
}
