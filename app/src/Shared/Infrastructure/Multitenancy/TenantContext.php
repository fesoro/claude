<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Multitenancy;

/**
 * TENANT CONTEXT (Singleton)
 * ==========================
 * Cari request-in hansı tenant-a aid olduğunu saxlayır.
 *
 * NECƏ İŞLƏYİR?
 * 1. TenantMiddleware request-dən tenant-ı müəyyən edir
 * 2. TenantContext::set($tenant) ilə saxlayır
 * 3. Bütün model-lər TenantContext::id() ilə tenant_id alır
 * 4. Request bitdikdə TenantContext::clear() ilə təmizlənir
 *
 * NƏYƏ SİNGLETON?
 * Bir request ərzində tenant dəyişmir — bir dəfə təyin olunur, hər yerdə istifadə olunur.
 * Laravel-in container-ində singleton kimi qeydiyyat olunur.
 */
class TenantContext
{
    private static ?TenantModel $tenant = null;

    public static function set(TenantModel $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function get(): ?TenantModel
    {
        return self::$tenant;
    }

    public static function id(): ?string
    {
        return self::$tenant?->id;
    }

    public static function clear(): void
    {
        self::$tenant = null;
    }

    public static function isSet(): bool
    {
        return self::$tenant !== null;
    }
}
