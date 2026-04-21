<?php

declare(strict_types=1);

namespace Src\Payment\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;

/**
 * PAYMENT STATUS (Value Object + Enum Pattern)
 * =============================================
 * Ödənişin hansı vəziyyətdə olduğunu göstərir.
 *
 * STATUS-LAR VƏ KEÇİDLƏR (State Transitions):
 * ┌─────────┐    process()    ┌────────────┐
 * │ PENDING │───────────────→│ PROCESSING │
 * └─────────┘                └────────────┘
 *                               │       │
 *                    complete() │       │ fail()
 *                               ▼       ▼
 *                        ┌───────────┐ ┌────────┐
 *                        │ COMPLETED │ │ FAILED │
 *                        └───────────┘ └────────┘
 *                               │
 *                      refund() │
 *                               ▼
 *                        ┌──────────┐
 *                        │ REFUNDED │
 *                        └──────────┘
 *
 * NƏYƏ STRING İSTİFADƏ ETMİRİK?
 * - "pending", "Pending", "PENDINGG" — string-lə typo ola bilər.
 * - Value Object ilə yalnız düzgün dəyərlər qəbul olunur.
 * - Hər status keçidinin qaydası var: PENDING → COMPLETED keçid ola bilməz (əvvəlcə PROCESSING olmalıdır).
 *
 * PHP 8.1 ENUM:
 * PHP 8.1-dən enum var, amma biz Value Object pattern-i öyrənmək üçün class istifadə edirik.
 * Real proyektdə enum istifadə etmək daha yaxşıdır.
 */
final class PaymentStatus extends ValueObject
{
    // Ödəniş yaradılıb, hələ emal olunmayıb
    public const PENDING = 'pending';

    // Ödəniş emal olunur (gateway-ə göndərilib, cavab gözlənilir)
    public const PROCESSING = 'processing';

    // Ödəniş uğurla tamamlandı
    public const COMPLETED = 'completed';

    // Ödəniş uğursuz oldu (kart rədd edildi, balans çatmır və s.)
    public const FAILED = 'failed';

    // Ödəniş geri qaytarıldı (müştəriyə pul geri göndərildi)
    public const REFUNDED = 'refunded';

    /**
     * İcazə verilən bütün status-ların siyahısı.
     * Yeni status əlavə edəndə buraya da əlavə etmək lazımdır.
     */
    private const VALID_STATUSES = [
        self::PENDING,
        self::PROCESSING,
        self::COMPLETED,
        self::FAILED,
        self::REFUNDED,
    ];

    private function __construct(
        private readonly string $value,
    ) {
    }

    /**
     * Status yarat.
     * Yalnız icazə verilən dəyərlər qəbul olunur.
     *
     * @throws \InvalidArgumentException Yanlış status dəyəri verildikdə
     */
    public static function fromString(string $value): self
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Yanlış ödəniş statusu: '{$value}'. İcazə verilən dəyərlər: " . implode(', ', self::VALID_STATUSES)
            );
        }

        return new self($value);
    }

    /**
     * Hər status üçün factory metodları.
     * PaymentStatus::pending() yazmaq PaymentStatus::fromString('pending')-dən daha oxunaqlıdır.
     */
    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function processing(): self
    {
        return new self(self::PROCESSING);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function refunded(): self
    {
        return new self(self::REFUNDED);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Status yoxlama metodları.
     * if ($status->isPending()) yazmaq if ($status->value() === 'pending')-dən daha təmizdir.
     */
    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->value === self::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->value === self::REFUNDED;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
