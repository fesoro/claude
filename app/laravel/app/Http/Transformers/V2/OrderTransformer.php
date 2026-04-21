<?php

declare(strict_types=1);

namespace App\Http\Transformers\V2;

use App\Http\Transformers\TransformerInterface;

/**
 * V2 ORDER TRANSFORMER
 * ====================
 * API v2 üçün sifariş formatı — zəngin, strukturlaşdırılmış.
 *
 * V2-DƏ ƏLAVƏLƏR:
 * - total: {amount, currency, formatted} obyekti
 * - status: {code, label, can_cancel} obyekti (frontend məntiqi azalır)
 * - items: tam item siyahısı (v1-də yalnız count var idi)
 * - address: strukturlaşdırılmış ünvan
 * - timeline: status tarixçəsi (gələcək üçün hazırlıq)
 */
class OrderTransformer implements TransformerInterface
{
    public function transform(mixed $data): array
    {
        $amount = (float) ($data->total_amount ?? $data['total_amount'] ?? 0);
        $currency = $data->currency ?? $data['currency'] ?? 'USD';
        $status = $data->status ?? $data['status'] ?? 'pending';

        return [
            'id' => $data->id ?? $data['id'],
            'user_id' => $data->user_id ?? $data['user_id'],
            'status' => [
                'code' => $status,
                'label' => $this->statusLabel($status),
                'can_cancel' => in_array($status, ['pending', 'confirmed']),
                'can_refund' => $status === 'paid',
            ],
            'total' => [
                'amount' => $amount,
                'currency' => $currency,
                'formatted' => $this->formatPrice($amount, $currency),
            ],
            'items' => $this->transformItems($data->items ?? $data['items'] ?? []),
            'address' => [
                'street' => $data->address_street ?? $data['address_street'] ?? null,
                'city' => $data->address_city ?? $data['address_city'] ?? null,
                'zip' => $data->address_zip ?? $data['address_zip'] ?? null,
                'country' => $data->address_country ?? $data['address_country'] ?? null,
            ],
            'metadata' => [
                'created_at' => $data->created_at ?? $data['created_at'] ?? null,
                'updated_at' => $data->updated_at ?? $data['updated_at'] ?? null,
            ],
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

    private function transformItems(iterable $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'product_id' => $item->product_id ?? $item['product_id'],
                'quantity' => (int) ($item->quantity ?? $item['quantity']),
                'price' => [
                    'unit' => (float) ($item->price ?? $item['price']),
                    'total' => (float) ($item->price ?? $item['price']) * (int) ($item->quantity ?? $item['quantity']),
                ],
            ];
        }
        return $result;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Gözləyir',
            'confirmed' => 'Təsdiqlənib',
            'paid' => 'Ödənilib',
            'shipped' => 'Göndərilib',
            'delivered' => 'Çatdırılıb',
            'cancelled' => 'Ləğv edilib',
            default => $status,
        };
    }

    private function formatPrice(float $amount, string $currency): string
    {
        $symbols = ['USD' => '$', 'EUR' => '€', 'AZN' => '₼'];
        return ($symbols[$currency] ?? $currency) . number_format($amount, 2);
    }
}
