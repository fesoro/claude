<?php

declare(strict_types=1);

namespace App\Http\Transformers\V2;

use App\Http\Transformers\TransformerInterface;

/**
 * V2 PAYMENT TRANSFORMER
 * =======================
 * V2-də əlavələr:
 * - amount obyekt olaraq (formatted daxil)
 * - method label (Azerbaijani)
 * - status label + is_final flag
 * - transaction_id (yalnız completed olanda)
 * - failure_reason (yalnız failed olanda)
 */
class PaymentTransformer implements TransformerInterface
{
    public function transform(mixed $data): array
    {
        $amount = (float) ($data->amount ?? $data['amount'] ?? 0);
        $currency = $data->currency ?? $data['currency'] ?? 'USD';
        $status = $data->status ?? $data['status'] ?? 'pending';
        $method = $data->method ?? $data['method'] ?? '';

        return [
            'id' => $data->id ?? $data['id'],
            'order_id' => $data->order_id ?? $data['order_id'],
            'amount' => [
                'value' => $amount,
                'currency' => $currency,
                'formatted' => $this->formatPrice($amount, $currency),
            ],
            'method' => [
                'code' => $method,
                'label' => $this->methodLabel($method),
            ],
            'status' => [
                'code' => $status,
                'label' => $this->statusLabel($status),
                'is_final' => in_array($status, ['completed', 'failed', 'refunded']),
            ],
            // Şərtli sahələr — yalnız müvafiq statusda görünür
            'transaction_id' => $status === 'completed'
                ? ($data->transaction_id ?? $data['transaction_id'] ?? null)
                : null,
            'failure_reason' => $status === 'failed'
                ? ($data->failure_reason ?? $data['failure_reason'] ?? null)
                : null,
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

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'credit_card' => 'Kredit kartı',
            'paypal' => 'PayPal',
            'bank_transfer' => 'Bank köçürməsi',
            default => $method,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Gözləyir',
            'processing' => 'Emal olunur',
            'completed' => 'Tamamlandı',
            'failed' => 'Uğursuz',
            'refunded' => 'Geri qaytarıldı',
            default => $status,
        };
    }

    private function formatPrice(float $amount, string $currency): string
    {
        $symbols = ['USD' => '$', 'EUR' => '€', 'AZN' => '₼'];
        return ($symbols[$currency] ?? $currency) . number_format($amount, 2);
    }
}
