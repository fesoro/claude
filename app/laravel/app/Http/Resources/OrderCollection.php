<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * ORDER RESOURCE COLLECTION
 * =========================
 * Sifarişlərin siyahısını API formatına çevirir.
 *
 * ProductCollection-da ətraflı izah etdik — burada eyni prinsiplər işləyir:
 * - Hər bir sifariş OrderResource ilə formatlanır
 * - Pagination metadata avtomatik əlavə olunur
 * - Əlavə meta-data (ümumi saylar) əlavə edə bilərik
 */
class OrderCollection extends ResourceCollection
{
    /**
     * Hər bir elementi OrderResource ilə formatla.
     */
    public $collects = OrderResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    /**
     * Sifariş siyahısına xas əlavə meta-data.
     *
     * Məsələn: statuslara görə say göstərmək faydalı ola bilər.
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        /**
         * Hər status üzrə neçə sifariş olduğunu hesablayırıq.
         * Nəticə: { "pending": 5, "shipped": 3, "delivered": 12 }
         *
         * countBy() — Laravel Collection metodu, verilmiş sahəyə görə qruplayıb sayır.
         */
        $default['meta']['status_counts'] = $this->collection
            ->countBy(fn ($order) => $order->status)
            ->toArray();

        return $default;
    }
}
