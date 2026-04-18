<?php

declare(strict_types=1);

namespace Src\Payment\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;

/**
 * PAYMENT METHOD (Value Object)
 * =============================
 * Ödəniş üsulunu təmsil edir: kredit kartı, PayPal, bank köçürməsi.
 *
 * STRATEGY PATTERN İLƏ ƏLAQƏSİ:
 * PaymentMethod dəyərinə görə fərqli ödəniş gateway-i seçilir.
 * Bu, Strategy pattern-in əsasıdır:
 * - CREDIT_CARD → CreditCardGateway istifadə olunur
 * - PAYPAL → PayPalGateway istifadə olunur
 * - BANK_TRANSFER → BankTransferGateway istifadə olunur
 *
 * PaymentStrategyResolver sinfi bu əlaqəni həyata keçirir.
 */
final class PaymentMethod extends ValueObject
{
    public const CREDIT_CARD = 'credit_card';
    public const PAYPAL = 'paypal';
    public const BANK_TRANSFER = 'bank_transfer';

    private const VALID_METHODS = [
        self::CREDIT_CARD,
        self::PAYPAL,
        self::BANK_TRANSFER,
    ];

    private function __construct(
        private readonly string $value,
    ) {
    }

    /**
     * @throws \InvalidArgumentException Yanlış ödəniş üsulu verildikdə
     */
    public static function fromString(string $value): self
    {
        if (!in_array($value, self::VALID_METHODS, true)) {
            throw new \InvalidArgumentException(
                "Yanlış ödəniş üsulu: '{$value}'. İcazə verilən üsullar: " . implode(', ', self::VALID_METHODS)
            );
        }

        return new self($value);
    }

    public static function creditCard(): self
    {
        return new self(self::CREDIT_CARD);
    }

    public static function paypal(): self
    {
        return new self(self::PAYPAL);
    }

    public static function bankTransfer(): self
    {
        return new self(self::BANK_TRANSFER);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isCreditCard(): bool
    {
        return $this->value === self::CREDIT_CARD;
    }

    public function isPaypal(): bool
    {
        return $this->value === self::PAYPAL;
    }

    public function isBankTransfer(): bool
    {
        return $this->value === self::BANK_TRANSFER;
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
