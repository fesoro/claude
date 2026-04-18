<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Multitenancy;

use Illuminate\Database\Eloquent\Builder;

/**
 * BELONGS TO TENANT TRAİT (Global Scope Pattern)
 * ================================================
 * Bu trait-i model-ə əlavə etdikdə, bütün sorğulara avtomatik
 * WHERE tenant_id = :current_tenant_id filteri əlavə olunur.
 *
 * GLOBAL SCOPE NƏDİR?
 * Eloquent-in "addGlobalScope" mexanizmi — model-ə qoşulan hər sorğuya
 * avtomatik WHERE şərti əlavə edir. Developer unuda bilməz — həmişə filtrlənir.
 *
 * NÜMUNƏ:
 * ProductModel::all() → SELECT * FROM products WHERE tenant_id = 'abc'
 * ProductModel::find($id) → SELECT * FROM products WHERE id = ? AND tenant_id = 'abc'
 *
 * Scope olmadan developer hər sorğuda manual filter yazmalıdır:
 * ProductModel::where('tenant_id', $tenantId)->get()
 * Bu unudula bilər → BÖYÜK TƏHLÜKƏSİZLİK BOŞLUĞU (data leak!)
 *
 * SCOPE-U KEÇMƏK (bypass):
 * Bəzən admin panel-də bütün tenant-ların datasına baxmaq lazımdır:
 * ProductModel::withoutGlobalScope('tenant')->get()
 */
trait BelongsToTenant
{
    /**
     * Model boot olanda Global Scope əlavə et.
     * "booted" metodu model ilk dəfə istifadə olanda bir dəfə çağırılır.
     */
    protected static function bootBelongsToTenant(): void
    {
        // OXUMA: Bütün sorğulara tenant filter əlavə et
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (TenantContext::isSet()) {
                $builder->where('tenant_id', TenantContext::id());
            }
        });

        // YAZMA: Yeni model yaradılanda avtomatik tenant_id təyin et
        static::creating(function ($model) {
            if (TenantContext::isSet() && empty($model->tenant_id)) {
                $model->tenant_id = TenantContext::id();
            }
        });
    }
}
