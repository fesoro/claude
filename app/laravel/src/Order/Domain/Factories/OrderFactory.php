<?php

declare(strict_types=1);

namespace Src\Order\Domain\Factories;

use Src\Order\Application\DTOs\CreateOrderDTO;
use Src\Order\Domain\Entities\Order;
use Src\Order\Domain\ValueObjects\Address;
use Src\Order\Domain\ValueObjects\OrderItem;
use Src\Product\Domain\ValueObjects\Money;

/**
 * ORDER FACTORY (Factory Pattern)
 * ================================
 * Mürəkkəb Order aggregate-ini yaratmaq üçün Factory class-ı.
 *
 * ═══════════════════════════════════════════════════════════════
 * FACTORY PATTERN NƏDİR?
 * ═══════════════════════════════════════════════════════════════
 *
 * Factory Pattern — obyekt yaratma məntiqini ayrı bir class-a çıxarır.
 * Bu "creational" (yaradıcı) design pattern-lərdəndir.
 *
 * PROBLEM (Factory olmadan):
 * ┌──────────────────────────────────────────────────────────────┐
 * │ // Handler-da çoxlu yaratma logikası — çirkin və təkrarlanan │
 * │ $address = new Address($dto->street, $dto->city, ...);      │
 * │ $order = Order::create($dto->userId, $address);             │
 * │ foreach ($dto->items as $itemData) {                        │
 * │     $money = new Money($itemData->price, $itemData->currency); │
 * │     $item = new OrderItem($itemData->productId, ...);       │
 * │ }                                                           │
 * │ // Bu kod hər yerdə təkrarlanır...                          │
 * └──────────────────────────────────────────────────────────────┘
 *
 * HƏLL (Factory ilə):
 * ┌──────────────────────────────────────────────────────────────┐
 * │ // Handler-da tək bir sətir — təmiz və oxunaqlı              │
 * │ $order = $this->orderFactory->createFromDTO($dto);          │
 * └──────────────────────────────────────────────────────────────┘
 *
 * FACTORY-NİN ÜSTÜNLÜYÜ:
 * 1. Single Responsibility: Yaratma logikası bir yerdədir.
 * 2. DRY (Don't Repeat Yourself): Kod təkrarlanmır.
 * 3. Encapsulation: Yaratma detalları gizlədilir.
 * 4. Testability: Factory-ni mock edib test edə bilərsən.
 * 5. Flexibility: Yaratma logikası dəyişsə, yalnız Factory dəyişir.
 *
 * DDD-DƏ FACTORY:
 * - Aggregate Root yaratmaq mürəkkəb olanda Factory istifadə olunur.
 * - Sadə yaratma üçün Entity-nin öz static factory method-u kifayətdir.
 * - Order yaratma mürəkkəbdir: Address, OrderItem-lər, Money — hamısı lazımdır.
 *   Ona görə ayrı Factory class-ı yaradırıq.
 *
 * ═══════════════════════════════════════════════════════════════
 * FACTORY NÖVLƏR:
 * ═══════════════════════════════════════════════════════════════
 * 1. Simple Factory: Bu class — ən sadə forma. Metod çağırırsan, obyekt alırsan.
 * 2. Factory Method: Abstract method — alt class-lar müəyyən edir nə yaradılacaq.
 * 3. Abstract Factory: Əlaqəli obyektlər ailəsi yaradır.
 *
 * Biz burada Simple Factory istifadə edirik çünki öyrənmə məqsədlidir.
 */
class OrderFactory
{
    /**
     * DTO-dan Order aggregate yarad.
     *
     * AXIN:
     * 1. DTO-dan Address Value Object yaradılır.
     * 2. Order::create() ilə yeni sifariş yaradılır (PENDING statusunda).
     * 3. Hər item üçün OrderItem Value Object yaradılır və sifarişə əlavə olunur.
     * 4. Hər addımda cəmi məbləğ avtomatik yenidən hesablanır.
     *
     * @param CreateOrderDTO $dto Sifariş yaratmaq üçün lazım olan data
     * @return Order Yaradılmış sifariş aggregate-i (event-ləri ilə birlikdə)
     */
    public function createFromDTO(CreateOrderDTO $dto): Order
    {
        // 1. Çatdırılma ünvanını yarat
        $address = new Address(
            street: $dto->street,
            city: $dto->city,
            zip: $dto->zip,
            country: $dto->country,
        );

        // 2. Yeni sifariş yarat (PENDING statusunda)
        // Order::create() daxilində OrderCreatedEvent qeydə alınır
        $order = Order::create(
            userId: $dto->userId,
            address: $address,
        );

        // 3. Hər məhsulu sifarişə əlavə et
        foreach ($dto->items as $itemData) {
            $item = new OrderItem(
                productId: $itemData->productId,
                quantity: $itemData->quantity,
                price: new Money((int) round($itemData->price * 100), $itemData->currency),
            );

            // addItem() daxilində:
            // - Status yoxlanılır (PENDING olmalıdır)
            // - Cəmi məbləğ yenidən hesablanır
            $order->addItem($item);
        }

        return $order;
    }
}
