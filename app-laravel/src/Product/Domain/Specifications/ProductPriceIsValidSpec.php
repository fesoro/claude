<?php

declare(strict_types=1);

namespace Src\Product\Domain\Specifications;

use Src\Shared\Domain\Specification;
use Src\Product\Domain\Entities\Product;

/**
 * ProductPriceIsValidSpec - Məhsulun qiymətinin düzgün olmasını yoxlayan Specification.
 *
 * Biznes qaydası: Məhsulun qiyməti 0-dan böyük olmalıdır.
 * Pulsuz məhsul satıla bilməz (bu, biznes qərarıdır).
 *
 * İstifadə nümunəsi:
 *   $spec = new ProductPriceIsValidSpec();
 *   if (!$spec->isSatisfiedBy($product)) {
 *       throw new DomainException("Qiymət düzgün deyil!");
 *   }
 */
final class ProductPriceIsValidSpec extends Specification
{
    /**
     * Məhsulun qiymətinin 0-dan böyük olmasını yoxlayır.
     *
     * @param Product $candidate Yoxlanacaq məhsul
     * @return bool true - qiymət düzgündür, false - qiymət yanlışdır
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        /** @var Product $candidate */
        return $candidate->price()->amount() > 0;
    }
}
