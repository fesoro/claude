<?php

declare(strict_types=1);

namespace Src\Product\Application\Queries\ListProducts;

use Src\Product\Application\DTOs\ProductDTO;
use Src\Product\Application\Queries\ListProducts\ListProductsQuery;
use Src\Product\Infrastructure\Models\ProductModel;
use Src\Shared\Application\Bus\Query;
use Src\Shared\Application\Bus\QueryHandler;

/**
 * ListProductsHandler — Filtrlənmiş və səhifələnmiş məhsul siyahısı.
 *
 * AXIN:
 * 1. Query-dən filter parametrlərini al
 * 2. ProductFilter::apply() ilə query-yə filterləri tətbiq et
 * 3. Paginate et
 * 4. Hər məhsulu ProductDTO-ya çevir
 */
final class ListProductsHandler implements QueryHandler
{
    public function handle(ListProductsQuery $query): mixed
    {
        // Eloquent query builder başlat
        $eloquentQuery = ProductModel::query();

        // Filterləri tətbiq et (search, price range, stock, sort)
        ProductFilter::apply($eloquentQuery, $query->filters());

        // Səhifələ
        $paginated = $eloquentQuery->paginate(
            perPage: $query->perPage(),
            page: $query->page(),
        );

        // DTO-ya çevir
        $paginated->getCollection()->transform(
            fn($product) => ProductDTO::fromModel($product),
        );

        return $paginated;
    }
}
