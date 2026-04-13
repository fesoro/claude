<?php

declare(strict_types=1);

namespace App\Http\Transformers\V1;

use App\Http\Transformers\TransformerInterface;

/**
 * V1 PRODUCT TRANSFORMER
 * =======================
 * API v1 üçün məhsul formatı.
 *
 * V1 FORMAT (sadə, düz):
 * {
 *   "id": "abc-123",
 *   "name": "Laptop Pro",
 *   "price": 999.99,
 *   "currency": "USD",
 *   "stock": 50,
 *   "created_at": "2024-01-15T10:30:00Z"
 * }
 *
 * V1-in xüsusiyyətləri:
 * - price düz rəqəmdir (float)
 * - currency ayrı sahədir
 * - stock düz rəqəmdir
 * - Şəkil URL-i yoxdur (v1-də şəkil dəstəyi yox idi)
 */
class ProductTransformer implements TransformerInterface
{
    public function transform(mixed $data): array
    {
        return [
            'id' => $data->id ?? $data['id'],
            'name' => $data->name ?? $data['name'],
            'price' => (float) ($data->price ?? $data['price']),
            'currency' => $data->currency ?? $data['currency'] ?? 'USD',
            'stock' => (int) ($data->stock ?? $data['stock'] ?? 0),
            'created_at' => $data->created_at ?? $data['created_at'] ?? null,
        ];
    }

    public function transformCollection(iterable $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = $this->transform($item);
        }
        return $result;
    }
}
