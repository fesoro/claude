<?php

declare(strict_types=1);

namespace Src\Order\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;

/**
 * ORDER ID (Value Object)
 * =======================
 * Sifarişin unikal identifikatoru — UUID formatında.
 *
 * NƏYƏ UUID İSTİFADƏ EDİRİK?
 * - Auto-increment (1, 2, 3...) əvəzinə UUID istifadə edirik çünki:
 *   1. DB-yə yazmadan ƏVVƏL ID yarada bilərik (client-side generation).
 *   2. Fərqli serverlər eyni ID yaratmaz (distributed systems üçün vacib).
 *   3. ID-dən sifariş sayını bilmək mümkün deyil (təhlükəsizlik).
 *
 * NƏYƏ STRING DƏ PRİMİTİV DƏYİL?
 * - "string $orderId" yazsaq, hər hansı string (məs: "hello") göndərmək olar.
 * - "OrderId $orderId" yazsaq, yalnız düzgün formatlı UUID qəbul olunur.
 * - Bu "Primitive Obsession" anti-pattern-dən qoruyur.
 */
class OrderId extends ValueObject
{
    /**
     * @param string $value UUID formatında sifariş ID-si
     * @throws \InvalidArgumentException UUID formatı yanlışdırsa
     */
    public function __construct(
        private readonly string $value,
    ) {
        // UUID formatını yoxla (v4 UUID: 8-4-4-4-12 hex simvollar)
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException(
                "OrderId düzgün UUID v4 formatında olmalıdır. Daxil edilən: {$value}"
            );
        }
    }

    /**
     * Yeni unikal OrderId yarat.
     * Factory method — obyekti yaratmağın rahat yolu.
     */
    public static function generate(): self
    {
        return new self(uuid_create());
    }

    /**
     * Mövcud UUID-dən OrderId yarat.
     * Məsələn: DB-dən oxuyanda mövcud ID ilə yaratmaq üçün.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * İki OrderId-ni müqayisə et.
     * Dəyərləri (UUID string-ləri) eyni olarsa, bərabərdir.
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
