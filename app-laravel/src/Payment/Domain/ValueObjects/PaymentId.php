<?php

declare(strict_types=1);

namespace Src\Payment\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;

/**
 * PAYMENT ID (Value Object)
 * =========================
 * Ödəniş-in unikal identifikatoru — UUID formatında.
 *
 * NƏYƏ VALUE OBJECT?
 * - PaymentId sadəcə string deyil, UUID formatında olmalıdır.
 * - Əgər sadəcə string istifadə etsək, "abc123" kimi yanlış dəyər keçə bilər.
 * - PaymentId Value Object yaradılanda format yoxlanılır — yanlış dəyər keçə bilməz.
 *
 * UUID (Universally Unique Identifier) nədir?
 * - 128-bit unikal rəqəm: "550e8400-e29b-41d4-a716-446655440000"
 * - Eyni ID-nin təkrarlanma ehtimalı demək olar ki sıfırdır.
 * - Verilənlər bazasında auto-increment əvəzinə istifadə olunur.
 *   Üstünlüyü: ID-ni əvvəlcədən (DB-yə yazmadan) yaratmaq olur.
 */
final class PaymentId extends ValueObject
{
    /**
     * @param string $value UUID formatında ödəniş identifikatoru
     */
    private function __construct(
        private readonly string $value,
    ) {
    }

    /**
     * Yeni UUID ilə PaymentId yarat.
     * uuid_create() PHP-nin built-in funksiyasıdır.
     */
    public static function generate(): self
    {
        return new self(uuid_create());
    }

    /**
     * Mövcud UUID string-dən PaymentId yarat.
     * Məsələn: verilənlər bazasından oxuyanda istifadə olunur.
     *
     * @throws \InvalidArgumentException UUID formatı yanlışdırsa
     */
    public static function fromString(string $value): self
    {
        // UUID formatını yoxla — boş və ya yanlış formatlı olmamalıdır
        if (empty($value)) {
            throw new \InvalidArgumentException('PaymentId boş ola bilməz.');
        }

        return new self($value);
    }

    /**
     * UUID dəyərini string olaraq qaytar.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * İki PaymentId-ni müqayisə et.
     * Value Object-lər dəyərlərinə görə müqayisə olunur.
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
