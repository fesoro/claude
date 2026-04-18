<?php

declare(strict_types=1);

namespace Src\Order\Application\DTOs;

use Src\Order\Domain\Entities\Order;

/**
 * ORDER DTO (Data Transfer Object)
 * ==================================
 * Sifariş datasını domain layer-dən xaricə (API cavabı, controller) ötürür.
 *
 * NƏYƏ DTO LAZIMDIR?
 * ┌──────────────────────────────────────────────────────────────┐
 * │ YANLIŞ: Controller-ə birbaşa Order entity qaytarmaq          │
 * │ return $order; // ✗ Domain obyekti xaricə çıxır!            │
 * │                                                              │
 * │ PROBLEMLƏR:                                                  │
 * │ 1. Domain dəyişsə, API cavabı da dəyişər (coupling).       │
 * │ 2. Entity-dəki private sahələr serializasiya olunmaz.       │
 * │ 3. Lazımsız data (domainEvents) xaricə sızar.              │
 * └──────────────────────────────────────────────────────────────┘
 *
 * ┌──────────────────────────────────────────────────────────────┐
 * │ DÜZGÜN: DTO ilə qaytarmaq                                   │
 * │ return OrderDTO::fromEntity($order); // ✓ Yalnız lazımi data│
 * │                                                              │
 * │ ÜSTÜNLÜKLƏR:                                                │
 * │ 1. Domain dəyişsə belə, DTO sabit qala bilər.              │
 * │ 2. Yalnız lazımi sahələr daxil olur.                        │
 * │ 3. API versiyalanması asanlaşır (V1DTO, V2DTO).             │
 * └──────────────────────────────────────────────────────────────┘
 */
readonly class OrderDTO
{
    /**
     * @param OrderItemDTO[] $items
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $status,
        public float $totalAmount,
        public string $currency,
        public string $street,
        public string $city,
        public string $zip,
        public string $country,
        public array $items,
        public string $createdAt,
    ) {}

    /**
     * Order entity-sindən DTO yarat.
     *
     * Bu metod domain obyektini DTO-ya çevirir:
     * - Entity-dəki Value Object-ləri sadə tiplərə (string, float) çevirir.
     * - Lazımsız sahələri (domainEvents) daxil etmir.
     * - API cavabı üçün hazır data qaytarır.
     *
     * @param Order $order Domain entity-si
     * @return self DTO
     */
    public static function fromEntity(Order $order): self
    {
        // Hər OrderItem Value Object-i OrderItemDTO-ya çevir
        $itemDTOs = array_map(
            fn ($item) => new OrderItemDTO(
                productId: $item->productId(),
                quantity: $item->quantity(),
                price: $item->price()->amount(),
                currency: $item->price()->currency(),
                lineTotal: $item->lineTotal()->amount(),
            ),
            $order->items(),
        );

        return new self(
            id: $order->orderId()->value(),
            userId: $order->userId(),
            status: $order->status()->value(),
            totalAmount: $order->totalAmount()->amount(),
            currency: $order->totalAmount()->currency(),
            street: $order->address()->street(),
            city: $order->address()->city(),
            zip: $order->address()->zip(),
            country: $order->address()->country(),
            items: $itemDTOs,
            createdAt: $order->createdAt()->format('c'),
        );
    }
}
