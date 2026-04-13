<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\RateLimiting;

use Illuminate\Http\Request;
use Src\Shared\Infrastructure\Multitenancy\TenantContext;

/**
 * PLAN-BASED RATE LIMITER (Abunəlik Əsaslı Sorğu Limiti)
 * ========================================================
 * Hər tenant-ın abunəlik planına görə fərqli rate limit tətbiq edir.
 *
 * NƏYƏ LAZIMDIR?
 * SaaS platformlarda müştərilər fərqli plan ödəyir:
 * - Free plan: Məhdud istifadə (100 sorğu/saat)
 * - Pro plan: Daha çox (1000 sorğu/saat)
 * - Enterprise plan: Limitsiz və ya çox yüksək (10000 sorğu/saat)
 *
 * REAL NÜMUNƏLƏR:
 * - GitHub API: Free = 60/saat, Authenticated = 5000/saat
 * - Stripe API: Test = 25/san, Live = 100/san
 * - OpenAI API: Plan-a görə token/dəqiqə limiti
 *
 * PLAN-LAR:
 * ┌──────────────┬──────────────┬──────────────────┐
 * │ Plan         │ Sorğu/saat   │ Sorğu/dəqiqə     │
 * ├──────────────┼──────────────┼──────────────────┤
 * │ free         │ 100          │ ~2               │
 * │ starter      │ 500          │ ~8               │
 * │ pro          │ 2000         │ ~33              │
 * │ enterprise   │ 10000        │ ~167             │
 * └──────────────┴──────────────┴──────────────────┘
 *
 * İMPLEMENTASİYA:
 * Laravel-in RateLimiter::for() metodu ilə plan-a görə Limit qaytarırıq.
 * Tenant-ın planı TenantContext-dən, və ya user-dən alınır.
 */
class PlanBasedRateLimiter
{
    /**
     * Plan-a görə saatlıq limit qaytarır.
     */
    public static function limitForPlan(?string $plan): int
    {
        return match ($plan) {
            'enterprise' => 10000,
            'pro' => 2000,
            'starter' => 500,
            'free', null => 100,
            default => 100,
        };
    }

    /**
     * Plan-a görə dəqiqəlik limit qaytarır.
     */
    public static function perMinuteLimitForPlan(?string $plan): int
    {
        return match ($plan) {
            'enterprise' => 200,
            'pro' => 60,
            'starter' => 15,
            'free', null => 5,
            default => 5,
        };
    }

    /**
     * Cari request üçün planı müəyyən et.
     * 1. TenantContext varsa → tenant planı
     * 2. User varsa → user-in tenant planı
     * 3. Heç biri yoxsa → "free"
     */
    public static function resolvePlan(Request $request): string
    {
        if (TenantContext::isSet()) {
            return TenantContext::get()->plan ?? 'free';
        }

        $user = $request->user();
        if ($user && $user->tenant_id) {
            // Tenant-ı cache-dən və ya DB-dən al
            $tenant = \Src\Shared\Infrastructure\Multitenancy\TenantModel::find($user->tenant_id);
            return $tenant?->plan ?? 'free';
        }

        return 'free';
    }

    /**
     * Rate limit key — limit kimin üçün hesablanır.
     * Tenant varsa → tenant_id (bütün tenant istifadəçiləri paylaşır)
     * Yoxsa → user_id və ya IP
     */
    public static function resolveKey(Request $request): string
    {
        if (TenantContext::isSet()) {
            return 'tenant:' . TenantContext::id();
        }

        return 'user:' . ($request->user()?->id ?: $request->ip());
    }
}
