<?php

declare(strict_types=1);

namespace Src\Order\Application\Queries\ListOrders;

use Src\Order\Application\DTOs\OrderDTO;
use Src\Order\Infrastructure\Models\OrderModel;
use Src\Shared\Application\Bus\Query;
use Src\Shared\Application\Bus\QueryHandler;

/**
 * LIST ORDERS HANDLER (CQRS Pattern)
 * ====================================
 * ListOrdersQuery-ni emal edib səhifələnmiş OrderDTO siyahısı qaytarır.
 *
 * Bu handler istifadəçinin sifarişlərini səhifələyərək tapır və DTO-ya çevirir.
 * Heç sifariş yoxdursa, boş səhifələnmiş nəticə qaytarır (xəta vermir).
 *
 * Səhifələmə (pagination):
 * - perPage: Hər səhifədəki element sayı (standart: 10)
 * - page: Cari səhifə nömrəsi
 */
class ListOrdersHandler implements QueryHandler
{
    public function __construct() {}

    /**
     * @param Query $query ListOrdersQuery olmalıdır
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator Səhifələnmiş nəticə
     */
    public function handle(Query $query): mixed
    {
        /** @var ListOrdersQuery $query */

        // İstifadəçinin sifarişlərini səhifələnmiş şəkildə alırıq
        $paginated = OrderModel::forUser($query->userId())->paginate(
            perPage: $query->perPage(),
            page: $query->page(),
        );

        // Hər Order modelini DTO-ya çeviririk, səhifələmə məlumatını saxlayırıq
        $paginated->getCollection()->transform(
            fn ($order) => OrderDTO::fromModel($order),
        );

        return $paginated;
    }
}
