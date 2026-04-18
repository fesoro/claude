<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Strategies;

/**
 * GATEWAY RESULT (Value Object)
 * =============================
 * Ödəniş gateway-inin charge() əməliyyatının nəticəsini təmsil edir.
 *
 * NƏYƏ AYRICA SİNİF?
 * Gateway-dən gələn cavab mürəkkəbdir: uğurlu/uğursuz, transactionId, xəta mesajı.
 * Array əvəzinə obyekt istifadə etmək daha təhlükəsiz və oxunaqlıdır.
 * $result['success'] əvəzinə $result->isSuccess() — typo ola bilməz.
 */
final readonly class GatewayResult
{
    private function __construct(
        private bool $success,
        private ?string $transactionId,
        private ?string $errorMessage,
    ) {
    }

    /**
     * Uğurlu nəticə yarat.
     */
    public static function success(string $transactionId): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            errorMessage: null,
        );
    }

    /**
     * Uğursuz nəticə yarat.
     */
    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            transactionId: null,
            errorMessage: $errorMessage,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function transactionId(): ?string
    {
        return $this->transactionId;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
