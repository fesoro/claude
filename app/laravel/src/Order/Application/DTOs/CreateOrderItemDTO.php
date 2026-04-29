<?php

declare(strict_types=1);

namespace Src\Order\Application\DTOs;

/**
 * Sifariş sətri (item) üçün input DTO.
 * Hər məhsulun ID-si, miqdarı, qiyməti (AZN-də) və valyutası.
 *
 * price — AZN-də float (məs: 29.99). OrderFactory Money-ə çevirəndə qəpiyə (cent) çevirir.
 */
readonly class CreateOrderItemDTO
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public float $price,
        public string $currency = 'AZN',
    ) {}
}
