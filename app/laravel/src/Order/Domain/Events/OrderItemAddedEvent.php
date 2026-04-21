<?php

declare(strict_types=1);

namespace Src\Order\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * ORDER ITEM ADDED EVENT (Domain Event)
 * ======================================
 * "Sifarişə məhsul əlavə edildi" hadisəsi.
 *
 * Event Sourcing-də hər dəyişiklik event kimi qeydə alınmalıdır.
 * Adi Order class-ında addItem() sadəcə array-ə push edirdi,
 * amma EventSourcedOrder-da bu əməliyyat da event yaradır.
 *
 * ANALOGİYA:
 * Supermarketdə alış-veriş səbətinə məhsul qoyursunuz.
 * Adi yanaşma: yalnız səbətin son vəziyyətini bilirsiniz.
 * Event Sourcing: hər məhsulun nə vaxt, hansı sırayla əlavə edildiyini bilirsiniz.
 */
class OrderItemAddedEvent extends DomainEvent
{
    public function __construct(
        private readonly string $orderId,
        private readonly string $productId,
        private readonly int $quantity,
        private readonly int $priceAmount,
        private readonly string $priceCurrency,
    ) {
        parent::__construct();
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function priceAmount(): int
    {
        return $this->priceAmount;
    }

    public function priceCurrency(): string
    {
        return $this->priceCurrency;
    }

    public function eventName(): string
    {
        return 'order.item_added';
    }

    public function toArray(): array
    {
        return [
            'order_id'       => $this->orderId,
            'product_id'     => $this->productId,
            'quantity'        => $this->quantity,
            'price_amount'   => $this->priceAmount,
            'price_currency' => $this->priceCurrency,
        ];
    }
}
