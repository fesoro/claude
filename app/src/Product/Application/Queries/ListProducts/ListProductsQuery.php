<?php

declare(strict_types=1);

namespace Src\Product\Application\Queries\ListProducts;

use Src\Shared\Application\Bus\Query;

/**
 * ListProductsQuery — Məhsul siyahısı sorğusu.
 *
 * SEÇİMLƏR:
 * - Səhifələmə: page, perPage
 * - Axtarış: search (məhsul adında)
 * - Filter: min_price, max_price, currency, in_stock
 * - Sıralama: sort_by, sort_dir
 *
 * NÜMUNƏ İSTİFADƏ:
 * GET /api/products?search=laptop&min_price=100&max_price=500&in_stock=true&sort_by=price&sort_dir=asc&page=2
 */
final class ListProductsQuery implements Query
{
    public function __construct(
        private readonly int $page = 1,
        private readonly int $perPage = 15,
        private readonly ?string $search = null,
        private readonly ?float $minPrice = null,
        private readonly ?float $maxPrice = null,
        private readonly ?string $currency = null,
        private readonly ?bool $inStock = null,
        private readonly string $sortBy = 'created_at',
        private readonly string $sortDir = 'desc',
    ) {}

    public function page(): int { return $this->page; }
    public function perPage(): int { return $this->perPage; }

    /**
     * Filter parametrlərini array olaraq qaytar — ProductFilter::apply() üçün.
     */
    public function filters(): array
    {
        return array_filter([
            'search' => $this->search,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'currency' => $this->currency,
            'in_stock' => $this->inStock,
            'sort_by' => $this->sortBy,
            'sort_dir' => $this->sortDir,
        ], fn($v) => $v !== null);
    }
}
