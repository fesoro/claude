<?php

declare(strict_types=1);

namespace Src\User\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * USER ID VALUE OBJECT
 * ====================
 * UserId — istifadəçinin unikal identifikatorunu təmsil edən Value Object-dir.
 *
 * NƏYƏ UUID?
 * - Auto-increment ID (1, 2, 3...) əvəzinə UUID istifadə edirik.
 * - UUID-nin üstünlükləri:
 *   1. Bazaya yazmadan ƏVVƏL ID yarada bilərsən.
 *   2. Fərqli serverlər eyni anda ID yarada bilər (collision riski yoxdur).
 *   3. ID-dən məlumat sızmır (1001-ci user olduğunu bilmək olmaz).
 *
 * NƏYƏ STRING DEYİL?
 * - string $userId əvəzinə UserId $userId yazmaq tip təhlükəsizliyi verir.
 * - Yanlışlıqla orderId-ni userId yerinə göndərmək mümkünsüz olur.
 * - Bu DDD-də "Strongly Typed ID" pattern adlanır.
 */
final class UserId extends ValueObject
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Yeni unikal UserId yarat.
     * uuid_create() — PHP-nin UUID yaratma funksiyasıdır.
     * Hər çağırışda unikal ID verir.
     */
    public static function generate(): self
    {
        return new self(uuid_create());
    }

    /**
     * Mövcud UUID string-dən UserId yarat.
     * Bazadan oxunan və ya API-dən gələn ID-lər üçün istifadə olunur.
     *
     * @throws DomainException UUID formatı yanlış olduqda
     */
    public static function fromString(string $id): self
    {
        /**
         * uuid_is_valid() — string-in düzgün UUID formatında olub-olmadığını yoxlayır.
         * Yanlış format: "abc123" → Exception
         * Düzgün format: "550e8400-e29b-41d4-a716-446655440000"
         */
        if (!uuid_is_valid($id)) {
            throw new DomainException(
                "Yanlış UserId formatı: '{$id}'. UUID formatında olmalıdır."
            );
        }

        return new self($id);
    }

    /**
     * ID dəyərini string olaraq qaytar.
     */
    public function value(): string
    {
        return $this->value;
    }

    public function equals(ValueObject $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
