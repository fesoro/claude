<?php

declare(strict_types=1);

namespace Src\Order\Application\Queries\ListOrders;

use Src\Shared\Application\Bus\Query;

/**
 * LIST ORDERS QUERY (CQRS Pattern)
 * ==================================
 * İstifadəçinin bütün sifarişlərini oxumaq üçün sorğu.
 *
 * Bu Query userId ilə filtrlənir — hər istifadəçi yalnız
 * öz sifarişlərini görə bilər (authorization nümunəsi).
 *
 * Səhifələmə (pagination) dəstəyi:
 * - $page: Cari səhifə nömrəsi (standart: 1)
 * - $perPage: Hər səhifədəki sifariş sayı (standart: 10)
 *
 * GƏLƏCƏKDƏKİ GENİŞLƏNMƏ:
 * - Sorting (sıralama): $sortBy, $sortDirection
 * - Filtering (filtrlər): $status, $dateFrom, $dateTo
 */
class ListOrdersQuery implements Query
{
    public function __construct(
        private readonly string $userId,
        /** Cari səhifə nömrəsi */
        private readonly int $page = 1,
        /** Hər səhifədəki element sayı */
        private readonly int $perPage = 10,
    ) {}

    public function userId(): string
    {
        return $this->userId;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }
}
