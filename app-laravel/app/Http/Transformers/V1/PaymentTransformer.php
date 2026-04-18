<?php

declare(strict_types=1);

namespace App\Http\Transformers\V1;

use App\Http\Transformers\TransformerInterface;

/**
 * V1 PAYMENT TRANSFORMER
 */
class PaymentTransformer implements TransformerInterface
{
    public function transform(mixed $data): array
    {
        return [
            'id' => $data->id ?? $data['id'],
            'order_id' => $data->order_id ?? $data['order_id'],
            'amount' => (float) ($data->amount ?? $data['amount']),
            'currency' => $data->currency ?? $data['currency'] ?? 'USD',
            'method' => $data->method ?? $data['method'],
            'status' => $data->status ?? $data['status'],
            'created_at' => $data->created_at ?? $data['created_at'] ?? null,
        ];
    }

    public function transformCollection(iterable $items): array
    {
        return array_map(fn($item) => $this->transform($item), is_array($items) ? $items : iterator_to_array($items));
    }
}
