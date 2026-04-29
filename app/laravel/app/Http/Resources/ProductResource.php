<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PRODUCT API RESOURCE
 * ====================
 * Məhsul datanı API cavab formatına çevirir.
 *
 * DİQQƏT: Burada qiyməti sadə ədəd (29.99) yox, obyekt {amount, currency}
 * formatında qaytarırıq. Bu yaxşı API dizayn praktikasdır — çünki:
 * - Fronted bilir ki, qiymət hansı valyutadadır
 * - Fərqli valyutalar üçün eyni format işləyir
 * - API sənədləşdirməsi aydın olur
 *
 * Həmçinin "is_in_stock" — hesablanmış (computed) sahədir:
 * DB-də belə sahə yoxdur, stock > 0 olduqda true qaytarırıq.
 * Resource-un gücü məhz bundadır — DB strukturundan asılı olmadan
 * API-yə lazımi formada data çatdıra bilirik.
 */
class ProductResource extends JsonResource
{
    /**
     * Məhsul datasını API formatına çevir.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            /**
             * QİYMƏT OBYEKTİ:
             * Qiyməti düz ədəd (29.99) qaytarmaq əvəzinə, obyekt qaytarırıq.
             * Bu API best practice-dir — valyuta məlumatı həmişə qiymətlə birlikdə olmalıdır.
             *
             * Nəticə:
             * "price": {
             *   "amount": 29.99,
             *   "currency": "USD"
             * }
             */
            'price' => [
                'amount' => (float) $this->priceAmount,
                'currency' => $this->priceCurrency ?? 'USD',
            ],

            'stock' => (int) $this->stock,

            /**
             * HESABLANMIŞ SAHƏ (Computed Field):
             * Bu sahə DB-də yoxdur — Resource özü hesablayır.
             * stock > 0 olduqda true, əks halda false.
             * Frontend üçün çox rahatdır — özü hesablamaq məcburiyyətində qalmır.
             */
            'is_in_stock' => $this->stock > 0,

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
