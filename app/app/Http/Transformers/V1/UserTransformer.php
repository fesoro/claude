<?php

declare(strict_types=1);

namespace App\Http\Transformers\V1;

use App\Http\Transformers\TransformerInterface;

/**
 * V1 USER TRANSFORMER
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
            'created_at' => $data->created_at ?? $data['created_at'] ?? null,
        ];
    }

    public function transformCollection(iterable $items): array
    {
        return array_map(fn($item) => $this->transform($item), is_array($items) ? $items : iterator_to_array($items));
    }
}
