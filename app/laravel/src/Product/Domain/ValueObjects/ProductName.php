<?php

declare(strict_types=1);

namespace Src\Product\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * ProductName - Məhsulun adını təmsil edən ValueObject.
 *
 * Bu sinif məhsul adının biznes qaydalarına uyğun olmasını təmin edir.
 * Biznes qaydası: Məhsul adı minimum 3 simvol olmalıdır.
 *
 * Niyə string əvəzinə ValueObject istifadə edirik?
 * - Validasiya məntiqini bir yerdə saxlayırıq (DRY prinsipi).
 * - Yanlış dəyərin yaradılmasının qarşısını alırıq.
 * - Kodun oxunaqlılığını artırırıq: "string $name" əvəzinə "ProductName $name" yazırıq.
 * - Tip təhlükəsizliyi (type safety) təmin edirik - səhvən başqa string göndərmək olmur.
 */
final class ProductName extends ValueObject
{
    /** Minimum icazə verilən simvol sayı */
    private const MIN_LENGTH = 3;

    /**
     * @param string $value Məhsulun adı
     * @throws DomainException Əgər ad 3 simvoldan azdırsa
     */
    public function __construct(
        private readonly string $value
    ) {
        // Boşluqları kəsirik və uzunluğu yoxlayırıq
        $trimmed = trim($value);

        if (mb_strlen($trimmed) < self::MIN_LENGTH) {
            throw new DomainException(
                "Məhsul adı minimum " . self::MIN_LENGTH . " simvol olmalıdır. "
                . "Verilən dəyər: '{$trimmed}' (" . mb_strlen($trimmed) . " simvol)"
            );
        }

        // mb_strlen istifadə edirik çünki Azərbaycan dilində xüsusi hərflər var (ə, ö, ü, ş, ç, ğ, ı)
        $this->value = $trimmed;
    }

    /**
     * Ad dəyərini qaytarır.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * İki ProductName-in bərabər olub-olmadığını yoxlayır.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
