<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ORDER ITEM RESOURCE
 * ===================
 * Sifariş qələmini (order item) API formatına çevirir.
 *
 * Hər sifarişdə bir neçə qələm (item) olur:
 * - Məhsul A × 2 ədəd = 59.98
 * - Məhsul B × 1 ədəd = 15.00
 *
 * Bu Resource hər bir qələmi formatlaşdırır və
 * "total" sahəsini hesablayır (price × quantity).
 */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => (int) $this->quantity,
            'price' => (float) $this->price,
            'currency' => $this->currency,

            /**
             * lineTotal — OrderItemDTO-da hazır hesablanmış sahədir.
             */
            'line_total' => (float) $this->lineTotal,
        ];
    }
}
