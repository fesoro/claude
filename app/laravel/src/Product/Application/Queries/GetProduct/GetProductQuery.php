<?php

declare(strict_types=1);

namespace Src\Product\Application\Queries\GetProduct;

use Src\Shared\Application\Bus\Query;

/**
 * GetProductQuery - Tək bir məhsulun məlumatlarını sorğulayan Query.
 *
 * Query nədir? (CQRS pattern-in oxuma hissəsi)
 * - Sistemdən məlumat oxumaq üçün istifadə olunur.
 * - Heç bir dəyişiklik etmir (side-effect yoxdur).
 * - Nəticə qaytarır (Command-dan fərqli olaraq).
 */
final class GetProductQuery implements Query
{
    /**
     * @param string $productId Sorğulanan məhsulun ID-si
     */
    public function __construct(
        public readonly string $productId,
    ) {
    }
}
