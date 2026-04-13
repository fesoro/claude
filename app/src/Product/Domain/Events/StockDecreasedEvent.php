<?php

declare(strict_types=1);

namespace Src\Product\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * StockDecreasedEvent - Məhsulun stoku azaldıqda baş verən hadisə.
 *
 * Bu event stok azaldıqda qeydə alınır (record edilir).
 * Digər bounded context-lər bu hadisəni dinləyərək müvafiq əməliyyatlar edə bilər.
 * Məsələn: Sifariş sistemi stok dəyişikliyini izləyə bilər.
 */
final class StockDecreasedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $productId,
        public readonly int $previousStock,
        public readonly int $newStock,
        public readonly int $decreasedBy,
    ) {
    }
}
