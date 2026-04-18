<?php

declare(strict_types=1);

namespace Src\Product\Domain\Specifications;

use Src\Shared\Domain\Specification;
use Src\Product\Domain\Entities\Product;

/**
 * ProductIsInStockSpec - Məhsulun anbarda olub-olmadığını yoxlayan Specification.
 *
 * Specification Pattern nədir?
 * - Biznes qaydalarını ayrı siniflərə ayırır.
 * - Hər specification bir qaydanı yoxlayır.
 * - Təkrar istifadə oluna bilər (reusable).
 * - Birləşdirilə bilər: spec1->and(spec2)->or(spec3)
 *
 * Niyə Specification istifadə edirik?
 * - if/else şərtlərini sinif kimi ifadə edirik.
 * - Biznes qaydaları bir yerdə toplanır, koda səpələnmir.
 * - Test yazmaq asanlaşır - hər qaydanı ayrıca test edə bilərik.
 *
 * Bu specification: "Məhsulun stoku 0-dan böyükdür" qaydasını yoxlayır.
 */
final class ProductIsInStockSpec extends Specification
{
    /**
     * Məhsulun anbarda olub-olmadığını yoxlayır.
     *
     * @param Product $candidate Yoxlanacaq məhsul
     * @return bool true - anbarda var, false - tükənib
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        /** @var Product $candidate */
        return $candidate->stock()->quantity() > 0;
    }
}
