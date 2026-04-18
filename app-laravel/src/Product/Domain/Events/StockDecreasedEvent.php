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
 *
 * DİQQƏT: parent::__construct() MÜTLƏQ çağırılmalıdır!
 * Çünki DomainEvent base class-ı eventId və occurredAt sahələrini əvvəlcədən təyin edir.
 * Bu olmadan event-i RabbitMQ-ya serialize edəndə crash olur —
 * eventId() null qaytarır, EloquentEventStore metadata yazanda xəta verir.
 *
 * eventName() və toArray() abstract metodlardır — implement olunmalıdır.
 * Əks halda PHP "must be declared abstract or implement all abstract methods" xətası verir.
 */
final class StockDecreasedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $productId,
        public readonly int $previousStock,
        public readonly int $newStock,
        public readonly int $decreasedBy,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'product.stock_decreased';
    }

    public function toArray(): array
    {
        return [
            'product_id'     => $this->productId,
            'previous_stock' => $this->previousStock,
            'new_stock'      => $this->newStock,
            'decreased_by'   => $this->decreasedBy,
        ];
    }
}
