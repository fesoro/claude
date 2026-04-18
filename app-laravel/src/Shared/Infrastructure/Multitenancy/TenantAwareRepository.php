<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Multitenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * TENANT-AWARE REPOSITORY — Multi-Tenancy at Data Level
 * ========================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Multi-tenant tətbiqdə bir neçə şirkət (tenant) eyni sistemi istifadə edir.
 * Hər tenant yalnız ÖZ datasını görməlidir — başqa tenant-ın datasını görməməlidir.
 *
 * Əgər tenant filteri unudulsa:
 *   SELECT * FROM orders → BÜTÜN tenant-ların sifarişləri qaytarılır!
 *   Bu, BÖYÜK TƏHLÜKƏSİZLİK BOŞLUĞUDUR (data breach).
 *
 * HƏLLİ — ROW-LEVEL SECURITY (RLS):
 * ===================================
 * Hər sorğuya avtomatik WHERE tenant_id = :current_tenant əlavə edirik.
 * Developer heç vaxt "unuda bilməz" — sistem avtomatik filtrləyir.
 *
 * BelongsToTenant trait artıq var — amma yalnız Eloquent Model üçün işləyir.
 * Bu class Repository pattern-ə multi-tenancy əlavə edir.
 *
 * MULTİ-TENANCY ARXİTEKTURA TİPLƏRİ:
 * =====================================
 * 1. SHARED DATABASE, SHARED SCHEMA (bu layihə):
 *    Bütün tenant-lar eyni DB, eyni cədvəllərdə.
 *    tenant_id sütunu ilə ayrılır.
 *    + Ucuzdur, idarəsi asandır.
 *    - Cross-tenant data leak riski var.
 *    - Böyük tenant-lar kiçik tenant-ları yavaşlada bilər.
 *
 * 2. SHARED DATABASE, SEPARATE SCHEMA:
 *    Eyni DB server, amma hər tenant üçün ayrı schema (namespace).
 *    Məs: tenant1.orders, tenant2.orders
 *    + Daha yaxşı izolasiya.
 *    - Schema migration hər tenant üçün ayrı-ayrı.
 *
 * 3. SEPARATE DATABASE:
 *    Hər tenant üçün tamamilə ayrı DB.
 *    + Maksimal izolasiya, performance qeyri-məhdud.
 *    - Ən bahalı, idarəsi ən çətin.
 *    - Migration hər DB-yə ayrıca tətbiq olunmalıdır.
 *
 * Bu layihədə Tip 1 istifadə edirik — ən çox yayılmış yanaşma.
 *
 * ANALOGİYA:
 * ===========
 * Tip 1 = Kommunal mənzil. Hamı eyni mətbəxdə, amma hər kəsin öz şkafı var.
 * Tip 2 = Çoxmərtəbəli bina. Hər ailənin öz mənzili, amma eyni bina.
 * Tip 3 = Ayrı evlər. Tamamilə müstəqil.
 */
class TenantAwareRepository
{
    /**
     * SORĞUYA TENANT FİLTERİ ƏLAVƏ ET
     * ==================================
     * Eloquent Builder-ə avtomatik WHERE tenant_id = ? əlavə edir.
     *
     * BelongsToTenant trait-dən fərqi:
     * - Trait: Global Scope ilə model səviyyəsində işləyir.
     * - Bu: Repository səviyyəsində işləyir — daha explicit, daha kontrol olunan.
     *
     * @param Builder $query Eloquent query builder
     *
     * @return Builder Tenant filteri əlavə olunmuş query
     *
     * @throws \RuntimeException Tenant konteksti təyin olunmayıbsa
     */
    public static function applyTenantScope(Builder $query): Builder
    {
        if (!TenantContext::isSet()) {
            /**
             * Tenant konteksti yoxdursa — bu, middleware-in işləmədiyini göstərir.
             * Production-da bu HEÇVAXT baş verməməlidir.
             * Əgər baş verirsə — təhlükəsizlik problemidir!
             *
             * İki yanaşma var:
             * 1. Exception at → sorğu bloklanır (daha təhlükəsiz).
             * 2. Boş nəticə qaytar → heç bir data göstərilmir.
             * Biz 1-ci yanaşmanı seçirik — "fail loud" prinsipi.
             */
            Log::critical('Tenant konteksti olmadan sorğu edildi! TenantMiddleware işləmir.', [
                'query' => $query->toSql(),
            ]);

            throw new \RuntimeException(
                'Tenant konteksti təyin olunmayıb. ' .
                'TenantMiddleware aktiv olduğundan əmin olun.'
            );
        }

        return $query->where('tenant_id', TenantContext::id());
    }

    /**
     * TENANT KONTEKSTİNİ KEÇ (Admin əməliyyatları üçün)
     * =====================================================
     * Bəzən admin bütün tenant-ların datasına baxmalıdır.
     * Bu, callback daxilində tenant filterini deaktiv edir.
     *
     * DİQQƏT: Bu metod yalnız admin/system əməliyyatları üçündür!
     * İstifadəçi sorğularında HEÇVAXT istifadə edilməməlidir.
     *
     * @param callable $callback Tenant filteri olmadan icra olunacaq kod
     *
     * @return mixed Callback-in nəticəsi
     *
     * İSTİFADƏ NÜMUNƏSİ:
     * $allOrders = TenantAwareRepository::withoutTenant(
     *     fn() => Order::all()  // Bütün tenant-ların sifarişləri
     * );
     */
    public static function withoutTenant(callable $callback): mixed
    {
        $previousTenant = TenantContext::get();

        TenantContext::clear();

        try {
            $result = $callback();
        } finally {
            // Əvvəlki tenant-ı bərpa et — finally ilə, exception olsa belə
            if ($previousTenant !== null) {
                TenantContext::set($previousTenant);
            }
        }

        return $result;
    }

    /**
     * BAŞQA TENANT-IN KONTEKSTİNDƏ İCRA ET
     * =======================================
     * Müvəqqəti olaraq başqa tenant kimi işləyir.
     * Admin paneldə "bu tenant-ın datası"na baxmaq üçün istifadə olunur.
     *
     * @param TenantModel $tenant  Müvəqqəti keçiləcək tenant
     * @param callable    $callback Bu tenant kimi icra olunacaq kod
     *
     * @return mixed Callback-in nəticəsi
     */
    public static function asTenant(TenantModel $tenant, callable $callback): mixed
    {
        $previousTenant = TenantContext::get();

        TenantContext::set($tenant);

        try {
            return $callback();
        } finally {
            if ($previousTenant !== null) {
                TenantContext::set($previousTenant);
            } else {
                TenantContext::clear();
            }
        }
    }
}
