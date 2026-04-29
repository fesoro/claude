<?php

declare(strict_types=1);

namespace Src\Product\Application\DTOs;

use Src\Product\Domain\Entities\Product;
use Src\Product\Infrastructure\Models\ProductModel;

/**
 * ProductDTO - Məhsul məlumatlarını daşıyan Data Transfer Object.
 *
 * DTO (Data Transfer Object) nədir?
 * - Məlumatları bir qatdan digərinə daşımaq üçün istifadə olunan obyektdir.
 * - Heç bir biznes məntiqi ehtiva etmir - yalnız məlumat saxlayır.
 * - readonly - yaradıldıqdan sonra dəyişdirilə bilməz.
 *
 * Niyə DTO istifadə edirik?
 * - Domain Entity-ni birbaşa xaricə (API, UI) göndərmirik.
 * - Entity-nin daxili strukturunu gizləyirik (encapsulation).
 * - API cavabının formatını Entity-dən asılı olmadan dəyişə bilərik.
 *
 * fromEntity() - Factory Method:
 * - Domain Entity-dən DTO yaradır.
 * - Entity -> DTO çevrilməsini bir yerdə saxlayır.
 */
final readonly class ProductDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public int $priceAmount,
        public string $priceCurrency,
        public int $stock,
    ) {
    }

    /**
     * Product Entity-sindən DTO yaradır.
     *
     * Bu metod Domain qatı ilə Application qatı arasında "körpü" rolunu oynayır.
     * Entity-nin ValueObject-lərini sadə tiplərə (string, int) çevirir.
     */
    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->id()->value(),
            name: $product->name()->value(),
            priceAmount: $product->price()->amount(),
            priceCurrency: $product->price()->currency(),
            stock: $product->stock()->quantity(),
        );
    }

    /**
     * ProductModel Eloquent model-indən DTO yaradır.
     *
     * ListProductsHandler kimi query handler-lər ProductModel::query() ilə
     * birbaşa Eloquent-dən oxuyur. Bu metod həmin model-i DTO-ya çevirir.
     */
    public static function fromModel(ProductModel $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            priceAmount: (int) $model->price,
            priceCurrency: $model->currency,
            stock: $model->stock,
        );
    }
}
