<?php

declare(strict_types=1);

namespace Src\Product\Application\Commands\CreateProduct;

use Src\Shared\Application\Bus\CommandHandler;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Product\Domain\Entities\Product;
use Src\Product\Domain\ValueObjects\ProductId;
use Src\Product\Domain\ValueObjects\ProductName;
use Src\Product\Domain\ValueObjects\Money;
use Src\Product\Domain\ValueObjects\Stock;
use Src\Product\Domain\Repositories\ProductRepositoryInterface;
use Src\Product\Domain\Specifications\ProductPriceIsValidSpec;

/**
 * CreateProductHandler - CreateProductCommand-ı icra edən handler.
 *
 * CommandHandler nədir?
 * - Hər Command üçün bir Handler olur (1:1 əlaqə).
 * - Handler Command-ı alır və lazımi əməliyyatları yerinə yetirir.
 * - Application Service rolunu oynayır.
 *
 * Bu handler nə edir?
 * 1. DTO-dan ValueObject-lər yaradır.
 * 2. Product Entity yaradır (Factory Method ilə).
 * 3. Specification ilə biznes qaydalarını yoxlayır.
 * 4. Repository vasitəsilə saxlayır.
 *
 * Dependency Injection (DI):
 * - Handler öz asılılıqlarını constructor-da alır.
 * - ProductRepositoryInterface istifadə edir (konkret sinif deyil).
 * - Bu, test yazmanı və implementasiyanı dəyişməyi asanlaşdırır.
 */
final class CreateProductHandler implements CommandHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    /**
     * Əmri icra edir - yeni məhsul yaradır.
     *
     * @param CreateProductCommand $command Yaradılacaq məhsulun məlumatları
     * @throws DomainException Əgər biznes qaydaları pozularsa
     */
    public function handle(CreateProductCommand $command): string
    {
        $dto = $command->dto;

        // 1. ValueObject-lər yaradırıq - hər biri öz validasiyasını edir
        $id = ProductId::generate();
        $name = new ProductName($dto->name);
        $price = new Money($dto->priceAmount, $dto->currency);
        $stock = new Stock($dto->stock);

        // 2. Product yaradırıq (Factory Method)
        $product = Product::create($id, $name, $price, $stock);

        // 3. Specification ilə biznes qaydalarını yoxlayırıq
        $priceSpec = new ProductPriceIsValidSpec();
        if (!$priceSpec->isSatisfiedBy($product)) {
            throw new DomainException(
                "Məhsulun qiyməti 0-dan böyük olmalıdır."
            );
        }

        // 4. Repository vasitəsilə saxlayırıq
        $this->repository->save($product);

        return $id->value();
    }
}
