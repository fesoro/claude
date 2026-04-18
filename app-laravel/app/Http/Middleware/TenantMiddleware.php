<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Infrastructure\Multitenancy\TenantContext;
use Src\Shared\Infrastructure\Multitenancy\TenantModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * TENANT MIDDLEWARE
 * =================
 * Hər request-də cari tenant-ı müəyyən edir və TenantContext-ə yazır.
 *
 * TENANT MÜƏYYƏN ETMƏ STRATEGİYALARI:
 *
 * 1. HEADER əsaslı: X-Tenant-ID header-i (API üçün ideal)
 *    Authorization: Bearer token123
 *    X-Tenant-ID: acme-corp
 *
 * 2. SUBDOMAIN əsaslı: acme.app.com → tenant slug = "acme"
 *    SaaS platformlarda populyardır (Slack, Notion kimi).
 *
 * 3. URL PATH əsaslı: /api/tenants/acme/products
 *    Daha az istifadə olunur, URL uzanır.
 *
 * 4. İSTİFADƏÇİ əsaslı: Login olmuş user-in tenant_id-si istifadə olunur.
 *    Ən sadə yanaşma — user artıq tenant-a bağlıdır.
 *
 * BU LAYİHƏDƏ:
 * Əvvəlcə X-Tenant-ID header-ə baxırıq.
 * Yoxdursa, autentifikasiya olunmuş user-in tenant_id-sinə baxırıq.
 *
 * MIDDLEWARE EXECUTION ORDER:
 * Request → [ForceJson] → [Auth:Sanctum] → [TENANT] → Controller
 * Auth-dan sonra gəlir çünki user lazımdır (fallback üçün).
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        // Strategiya 1: X-Tenant-ID header
        $tenantSlug = $request->header('X-Tenant-ID');
        if ($tenantSlug) {
            $tenant = TenantModel::where('slug', $tenantSlug)
                ->where('is_active', true)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => "Tenant tapılmadı: {$tenantSlug}",
                ], 404);
            }
        }

        // Strategiya 2: User-in tenant_id-si (fallback)
        if (!$tenant && $request->user() && $request->user()->tenant_id) {
            $tenant = TenantModel::find($request->user()->tenant_id);
        }

        // Tenant tapıldısa context-ə yaz
        if ($tenant) {
            TenantContext::set($tenant);
        }

        try {
            return $next($request);
        } finally {
            // Request bitdikdə context-i təmizlə (memory leak qoruması)
            TenantContext::clear();
        }
    }
}
