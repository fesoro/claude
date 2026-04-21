<?php

declare(strict_types=1);

namespace Src\Order\Domain\ValueObjects;

use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObject;

/**
 * ORDER ITEM (Value Object)
 * =========================
 * Sifarişdəki bir məhsul sətrini təmsil edir.
 *
 * NƏYƏ VALUE OBJECT, ENTITY DEYİL?
 * - OrderItem-in öz müstəqil həyat dövrü yoxdur.
 * - O, yalnız Order daxilində mövcuddur.
 * - Eyni məhsul, eyni miqdar, eyni qiymət = eyni OrderItem.
 * - Dəyişmək lazımdırsa, köhnəsi silinir, yenisi yaradılır.
 *
 * DİQQƏT: Bəzi DDD tətbiqlərində OrderItem Entity kimi modelləşdirilir
 * (əgər onun ID-si və müstəqil davranışı varsa). Bu sadələşdirilmiş versiyada
 * Value Object olaraq istifadə edirik çünki öyrənmə məqsədlidir.
 *
 * MONEY VALUE OBJECT:
 * - Qiyməti "float $price" əvəzinə "Money $price" olaraq saxlayırıq.
 * - Money valyutanı (AZN, USD) və dəqiqliyi idarə edir.
 * - Float ilə pul hesablamaq XƏTALIDİR (0.1 + 0.2 !== 0.3 JavaScript-dəki kimi).
 */
class OrderItem extends ValueObject
{
    /**
     * @param string $productId Məhsulun ID-si (Product bounded context-dən)
     * @param int    $quantity  Miqdar (ədəd sayı)
     * @param Money  $price     Vahid qiyməti (bir ədədin qiyməti)
     *
     * @throws \InvalidArgumentException Miqdar 0 və ya mənfi olduqda
     */
    public function __construct(
        private readonly string $productId,
        private readonly int $quantity,
        private readonly Money $price,
    ) {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                "Məhsul miqdarı müsbət olmalıdır. Daxil edilən: {$quantity}"
            );
        }
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function price(): Money
    {
        return $this->price;
    }

    /**
     * Bu sətrin cəmi qiymətini hesabla: vahid qiyməti x miqdar.
     *
     * Məsələn: qiymət 10 AZN, miqdar 3 → cəmi 30 AZN
     * Money::multiply() metodu istifadə olunur ki, valyuta düzgün hesablansın.
     */
    public function lineTotal(): Money
    {
        return $this->price->multiply($this->quantity);
    }

    /**
     * OrderItem-i array formatına çevir — serialization üçün.
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity'   => $this->quantity,
            'price'      => $this->price->toArray(),
        ];
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->productId === $other->productId
            && $this->quantity === $other->quantity
            && $this->price->equals($other->price);
    }

    public function __toString(): string
    {
        return "Məhsul: {$this->productId}, Miqdar: {$this->quantity}, Qiymət: {$this->price}";
    }
}
