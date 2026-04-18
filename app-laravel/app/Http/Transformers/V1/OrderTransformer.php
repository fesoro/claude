<?php

declare(strict_types=1);

namespace App\Http\Transformers\V1;

use App\Http\Transformers\TransformerInterface;

/**
 * V1 ORDER TRANSFORMER
 * ====================
 * API v1 üçün sifariş formatı — sadə, düz struktur.
 */
class OrderTransformer implements TransformerInterface
{
    public function transform(mixed $data): array
    {
        return [
            'id' => $data->id ?? $data['id'],
            'user_id' => $data->user_id ?? $data['user_id'],
            'status' => $data->status ?? $data['status'],
            'total_amount' => (float) ($data->total_amount ?? $data['total_amount'] ?? 0),
            'currency' => $data->currency ?? $data['currency'] ?? 'USD',
            'items_count' => is_countable($data->items ?? $data['items'] ?? [])
                ? count($data->items ?? $data['items'] ?? [])
                : 0,
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
