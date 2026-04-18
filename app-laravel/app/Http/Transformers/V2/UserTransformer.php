<?php

declare(strict_types=1);

namespace App\Http\Transformers\V2;

use App\Http\Transformers\TransformerInterface;

/**
 * V2 USER TRANSFORMER
 * ====================
 * V2-də əlavələr:
 * - 2FA statusu (aktiv/deaktiv)
 * - Tenant məlumatı
 * - Hesab yaşı
 */
class UserTransformer implements TransformerInterface
{
    public function transform(mixed $data): array
    {
        return [
            'id' => $data->id ?? $data['id'],
            'name' => $data->name ?? $data['name'],
            'email' => $data->email ?? $data['email'],
            'is_active' => (bool) ($data->is_active ?? $data['is_active'] ?? true),
            'security' => [
                'two_factor_enabled' => (bool) ($data->two_factor_enabled ?? false),
            ],
            'tenant_id' => $data->tenant_id ?? null,
            'metadata' => [
                'created_at' => $data->created_at ?? $data['created_at'] ?? null,
                'updated_at' => $data->updated_at ?? $data['updated_at'] ?? null,
            ],
        ];
    }

    public function transformCollection(iterable $items): array
    {
        return array_map(fn($item) => $this->transform($item), is_array($items) ? $items : iterator_to_array($items));
    }
}
