<?php

declare(strict_types=1);

namespace Src\Product\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Stock - Məhsulun anbardakı miqdarını təmsil edən ValueObject.
 *
 * Biznes qaydası: Stok mənfi ola bilməz (non-negative integer).
 * Yəni: 0 ola bilər (tükənib), amma -1 ola bilməz.
 *
 * Money kimi, bu da immutable-dir (dəyişməzdir):
 * - decrease() və increase() yeni Stock qaytarır.
 * - Köhnə obyekt dəyişməz qalır.
 */
final class Stock extends ValueObject
{
    /**
     * @param int $quantity Anbardakı məhsul sayı
     * @throws DomainException Əgər miqdar mənfidirsə
     */
    public function __construct(
        private readonly int $quantity
    ) {
        if ($quantity < 0) {
            throw new DomainException(
                "Stok miqdarı mənfi ola bilməz. Verilən dəyər: {$quantity}"
            );
        }
    }

    /**
     * Miqdarı qaytarır.
     */
    public function quantity(): int
    {
        return $this->quantity;
    }

    /**
     * Stoku azaldır və YENİ Stock qaytarır.
     *
     * @param int $amount Azaldılacaq miqdar
     * @throws DomainException Əgər azaldan miqdar mövcud stokdan çoxdursa
     */
    public function decrease(int $amount): self
    {
        if ($amount <= 0) {
            throw new DomainException(
                "Azaldılacaq miqdar müsbət olmalıdır. Verilən: {$amount}"
            );
        }

        if ($amount > $this->quantity) {
            throw new DomainException(
                "Anbarda kifayət qədər məhsul yoxdur. "
                . "Mövcud: {$this->quantity}, Tələb olunan: {$amount}"
            );
        }

        return new self($this->quantity - $amount);
    }

    /**
     * Stoku artırır və YENİ Stock qaytarır.
     *
     * @param int $amount Artırılacaq miqdar
     */
    public function increase(int $amount): self
    {
        if ($amount <= 0) {
            throw new DomainException(
                "Artırılacaq miqdar müsbət olmalıdır. Verilən: {$amount}"
            );
        }

        return new self($this->quantity + $amount);
    }

    /**
     * Stokun boş olub-olmadığını yoxlayır.
     */
    public function isEmpty(): bool
    {
        return $this->quantity === 0;
    }

    /**
     * İki Stock obyektinin bərabər olub-olmadığını yoxlayır.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->quantity === $other->quantity;
    }

    public function __toString(): string
    {
        return (string) $this->quantity;
    }
}
