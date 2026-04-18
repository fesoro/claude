<?php

declare(strict_types=1);

namespace App\Http\Transformers\V2;

use App\Http\Transformers\TransformerInterface;

/**
 * V2 PRODUCT TRANSFORMER
 * =======================
 * API v2 üçün məhsul formatı — daha zəngin, strukturlaşdırılmış.
 *
 * V2 FORMAT:
 * {
 *   "id": "abc-123",
 *   "name": "Laptop Pro",
 *   "price": {
 *     "amount": 999.99,
 *     "currency": "USD",
 *     "formatted": "$999.99"
 *   },
 *   "stock": {
 *     "quantity": 50,
 *     "available": true,
 *     "low_stock": false
 *   },
 *   "images": {
 *     "primary": "https://storage.../image.jpg",
 *     "count": 3
 *   },
 *   "metadata": {
 *     "created_at": "2024-01-15T10:30:00Z",
 *     "updated_at": "2024-01-20T14:00:00Z"
 *   }
 * }
 *
 * V1-DƏN FƏRQLƏR:
 * ═══════════════
 * 1. price: float → {amount, currency, formatted} obyekti
 *    Nəyə? Frontend formatlama işini özü görməsin
 *
 * 2. stock: int → {quantity, available, low_stock} obyekti
 *    Nəyə? Frontend "stokda var/yox" məntiqi özü yazmasın
 *
 * 3. images əlavə olundu (v1-də yox idi)
 *    Nəyə? Şəkil funksionallığı v2-də əlavə edildi
 *
 * 4. metadata wrapper əlavə olundu
 *    Nəyə? Texniki sahələr əsas datadan ayrılır
 *
 * GERİYƏ UYĞUNLUQ (Backward Compatibility):
 * v1 istifadəçiləri hələ köhnə formatı alır — onlar dəyişiklik hiss etmir.
 * v2 istifadəçiləri yeni formatdan istifadə edir.
 * İki versiya PARALELdir — eyni anda işləyir.
 */
class ProductTransformer implements TransformerInterface
{
    public function transform(mixed $data): array
    {
        $price = (float) ($data->price ?? $data['price'] ?? 0);
        $currency = $data->currency ?? $data['currency'] ?? 'USD';
        $stock = (int) ($data->stock ?? $data['stock'] ?? 0);

        return [
            'id' => $data->id ?? $data['id'],
            'name' => $data->name ?? $data['name'],
            'price' => [
                'amount' => $price,
                'currency' => $currency,
                'formatted' => $this->formatPrice($price, $currency),
            ],
            'stock' => [
                'quantity' => $stock,
                'available' => $stock > 0,
                'low_stock' => $stock > 0 && $stock < 5,
            ],
            'images' => [
                'primary' => $data->primaryImage?->url ?? null,
                'count' => $data->images?->count() ?? 0,
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

    private function formatPrice(float $amount, string $currency): string
    {
        $symbols = ['USD' => '$', 'EUR' => '€', 'AZN' => '₼'];
        $symbol = $symbols[$currency] ?? $currency;

        return $symbol . number_format($amount, 2);
    }
}
