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
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => (int) $this->quantity,
            'price' => (float) $this->price,

            /**
             * HESABLANMIŞ SAHƏ — total = price × quantity
             * DB-də saxlanmaya bilər, Resource özü hesablayır.
             *
             * round(x, 2) — ondalık hissəni 2 rəqəmə yuvarlaqlaşdırır.
             * Məsələn: 29.99 × 3 = 89.97 (89.97000000001 yox)
             */
            'total' => round((float) $this->price * (int) $this->quantity, 2),
        ];
    }
}
