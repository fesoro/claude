<?php

declare(strict_types=1);

namespace Src\Order\Application\DTOs;

/**
 * ORDER ITEM DTO (Data Transfer Object)
 * =======================================
 * Sifariş sətrinin (item) datası — domain layer-dən application/presentation layer-ə
 * data ötürmək üçün istifadə olunur.
 *
 * DTO QAYDALARI (xatırlatma):
 * - readonly: yaradıldıqdan sonra dəyişdirilə bilməz.
 * - Biznes logikası YOXDUR — yalnız data daşıyır.
 * - Entity/Value Object-dən fərqlənir: validasiya yoxdur, sadəcə data.
 */
readonly class OrderItemDTO
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public float $price,
        public string $currency,
        public float $lineTotal,
    ) {}
}
