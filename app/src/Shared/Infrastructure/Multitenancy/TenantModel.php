<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Multitenancy;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * TENANT MODEL
 * ============
 * Hər tenant bir şirkət/mağaza/workspace-dir.
 *
 * settings JSON sahəsində tenant-a xas konfiqurasiya saxlanılır:
 * {
 *   "currency": "AZN",
 *   "timezone": "Asia/Baku",
 *   "logo_url": "https://...",
 *   "notifications_enabled": true
 * }
 */
class TenantModel extends Model
{
    use HasUuids;

    protected $connection = 'user_db';
    protected $table = 'tenants';

    protected $fillable = [
        'id', 'name', 'slug', 'domain', 'plan', 'settings', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
