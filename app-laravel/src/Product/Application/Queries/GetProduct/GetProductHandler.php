<?php

declare(strict_types=1);

namespace Src\Product\Application\Queries\GetProduct;

use Src\Shared\Application\Bus\QueryHandler;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Product\Application\DTOs\ProductDTO;
use Src\Product\Domain\Repositories\ProductRepositoryInterface;
use Src\Product\Domain\ValueObjects\ProductId;

/**
 * GetProductHandler - Tək məhsul sorğusunu icra edən handler.
 *
 * QueryHandler Command-dan fərqli olaraq nəticə qaytarır.
 * Bu handler ProductDTO qaytarır - Domain Entity-ni birbaşa qaytarmırıq.
 *
 * Niyə Entity əvəzinə DTO qaytarırıq?
 * - Entity-nin daxili strukturunu gizləyirik.
 * - API cavabını Entity-dən asılı olmadan formatlaya bilərik.
 * - Entity-nin metodlarına xaricdən müraciət edilməsinin qarşısını alırıq.
 */
final class GetProductHandler implements QueryHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    /**
     * @param GetProductQuery $query Məhsul sorğusu
     * @return ProductDTO Tapılan məhsulun DTO-su
     * @throws DomainException Əgər məhsul tapılmadıqda
     */
    public function handle(GetProductQuery $query): ProductDTO
    {
        $product = $this->repository->findById(
            ProductId::fromString($query->productId)
        );

        if ($product === null) {
            throw new DomainException(
                "Məhsul tapılmadı. ID: {$query->productId}"
            );
        }

        // Entity-dən DTO-ya çeviririk
        return ProductDTO::fromEntity($product);
    }
}
