<?php

declare(strict_types=1);

namespace Src\Payment\Application\Queries\GetPayment;

use Src\Shared\Application\Bus\Query;

/**
 * GET PAYMENT QUERY (CQRS Pattern)
 * ==================================
 * Bir ödənişin detallarını oxumaq üçün sorğu.
 *
 * GetOrderQuery ilə eyni pattern-i izləyir:
 * - Immutable (readonly) — yaradıldıqdan sonra dəyişdirilə bilməz.
 * - Side-effect free — heç bir şeyi dəyişmir, yalnız data qaytarır.
 * - Ayrı handler-ə yönləndirilir (GetPaymentHandler).
 */
class GetPaymentQuery implements Query
{
    public function __construct(
        private readonly string $paymentId,
    ) {}

    public function paymentId(): string
    {
        return $this->paymentId;
    }
}
