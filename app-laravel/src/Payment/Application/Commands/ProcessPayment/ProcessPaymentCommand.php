<?php

declare(strict_types=1);

namespace Src\Payment\Application\Commands\ProcessPayment;

use Src\Shared\Application\Bus\Command;

/**
 * PROCESS PAYMENT COMMAND (CQRS Pattern)
 * =======================================
 * "Ödənişi emal et!" əmri. Command — niyyəti (intent) ifadə edir.
 *
 * COMMAND XÜSUSIYYƏTLƏRI:
 * 1. Immutable (readonly) — yaradıldıqdan sonra dəyişmir
 * 2. Sadəcə data daşıyır — heç bir logika yoxdur
 * 3. Handler tərəfindən emal olunur (ProcessPaymentHandler)
 *
 * AXIN:
 * Controller → ProcessPaymentCommand → CommandBus → ProcessPaymentHandler
 */
final readonly class ProcessPaymentCommand implements Command
{
    public function __construct(
        /** Hansı sifariş üçün ödəniş edilir */
        public string $orderId,

        /** Ödəniş məbləği (məsələn: 99.99) */
        public float $amount,

        /** Valyuta kodu (məsələn: 'USD', 'AZN') */
        public string $currency,

        /** Ödəniş üsulu: 'credit_card', 'paypal', 'bank_transfer' */
        public string $paymentMethod,
    ) {
    }
}
