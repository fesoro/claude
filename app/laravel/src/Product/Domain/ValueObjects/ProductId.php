<?php

declare(strict_types=1);

namespace Src\Product\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;
use Ramsey\Uuid\Uuid;

/**
 * ProductId - Məhsulun unikal identifikatoru.
 *
 * Bu sinif UUID (Universally Unique Identifier) istifadə edir.
 * UUID - hər yerdə unikal olan identifikatordur, məsələn: "550e8400-e29b-41d4-a716-446655440000".
 *
 * Niyə UUID istifadə edirik?
 * - Verilənlər bazasından asılı olmadan ID yarada bilərik (auto-increment-dən fərqli olaraq).
 * - Fərqli sistemlər arasında ID toqquşması olmur.
 * - DDD-də Entity-lər verilənlər bazasından əvvəl yaradılır, ona görə ID əvvəlcədən lazımdır.
 *
 * ValueObject nədir?
 * - Dəyərinə görə müqayisə olunan obyektdir (identifikasiyaya görə deyil).
 * - Dəyişməzdir (immutable) - yaradıldıqdan sonra dəyişdirilə bilməz.
 * - Məsələn: iki ProductId eyni UUID-ə malikdirsə, onlar bərabərdir.
 */
final class ProductId extends ValueObject
{
    /**
     * @param string $value UUID formatında identifikator
     */
    public function __construct(
        private readonly string $value
    ) {
        // UUID formatını yoxlayırıq - düzgün format deyilsə, xəta atılır
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(
                "ProductId düzgün UUID formatında olmalıdır. Verilən dəyər: {$value}"
            );
        }
    }

    /**
     * Yeni unikal ProductId yaradır.
     * Factory Method pattern - obyekt yaratmaq üçün statik metod.
     */
    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    /**
     * Mövcud UUID string-dən ProductId yaradır.
     * Məsələn: verilənlər bazasından oxuyanda istifadə olunur.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * UUID dəyərini string olaraq qaytarır.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * İki ProductId-nin bərabər olub-olmadığını yoxlayır.
     * ValueObject-lərdə bərabərlik dəyərə görə müəyyən olunur.
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
