<?php

declare(strict_types=1);

namespace Src\Product\Application\Commands\UpdateStock;

use Src\Shared\Application\Bus\CommandHandler;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Product\Domain\Repositories\ProductRepositoryInterface;
use Src\Product\Domain\ValueObjects\ProductId;

/**
 * UpdateStockHandler - Stok yeniləmə əmrini icra edən handler.
 *
 * Bu handler:
 * 1. Məhsulu repository-dən tapır.
 * 2. Stoku artırır və ya azaldır (type-a görə).
 * 3. Dəyişiklikləri saxlayır.
 *
 * Diqqət: Stok azaldıqda Product avtomatik olaraq StockDecreasedEvent
 * və lazım gəldikdə LowStockIntegrationEvent qeydə alır.
 * Handler bu event-lər barədə narahat olmur - bu, Domain-in işidir.
 */
final class UpdateStockHandler implements CommandHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
    ) {
    }

    /**
     * @param UpdateStockCommand $command Stok yeniləmə əmri
     * @throws DomainException Əgər məhsul tapılmadıqda və ya stok kifayət deyilsə
     */
    public function handle(UpdateStockCommand $command): void
    {
        // 1. Məhsulu tapırıq
        $product = $this->repository->findById(
            ProductId::fromString($command->productId)
        );

        if ($product === null) {
            throw new DomainException(
                "Məhsul tapılmadı. ID: {$command->productId}"
            );
        }

        // 2. Əməliyyat növünə görə stoku yeniləyirik
        match ($command->type) {
            'increase' => $product->increaseStock($command->amount),
            'decrease' => $product->decreaseStock($command->amount),
            default => throw new DomainException(
                "Yanlış əməliyyat növü: {$command->type}. "
                . "'increase' və ya 'decrease' olmalıdır."
            ),
        };

        // 3. Dəyişiklikləri saxlayırıq
        $this->repository->save($product);
    }
}
