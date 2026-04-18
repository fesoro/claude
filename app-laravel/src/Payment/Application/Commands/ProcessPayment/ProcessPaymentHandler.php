<?php

declare(strict_types=1);

namespace Src\Payment\Application\Commands\ProcessPayment;

use Src\Payment\Application\ACL\PaymentGatewayACL;
use Src\Payment\Domain\Entities\Payment;
use Src\Payment\Domain\Repositories\PaymentRepositoryInterface;
use Src\Payment\Domain\Services\PaymentStrategyResolver;
use Src\Payment\Domain\ValueObjects\PaymentMethod;
use Src\Payment\Infrastructure\CircuitBreaker\CircuitBreaker;
use Src\Payment\Infrastructure\CircuitBreaker\RetryHandler;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Application\Bus\Command;
use Src\Shared\Application\Bus\CommandHandler;

/**
 * PROCESS PAYMENT HANDLER (Command Handler)
 * ==========================================
 * ProcessPaymentCommand əmrini emal edir — ödəniş prosesini idarə edir.
 *
 * BU HANDLER-DƏ İSTİFADƏ OLUNAN PATTERN-LƏR:
 * ─────────────────────────────────────────────
 * 1. STRATEGY PATTERN: PaymentStrategyResolver — ödəniş metoduna görə gateway seçir
 * 2. ANTI-CORRUPTION LAYER: PaymentGatewayACL — xarici API cavabını domain obyektinə çevirir
 * 3. CIRCUIT BREAKER: CircuitBreaker — gateway çöksə, gereksiz sorğu göndərmir
 * 4. RETRY PATTERN: RetryHandler — uğursuz olsa, bir neçə dəfə təkrar cəhd edir
 *
 * AXIN (Flow):
 * ┌──────────────┐     ┌──────────────────┐     ┌─────────────┐
 * │ Command gəlir │────→│ Strategy seçilir │────→│ ACL vasitəsilə │
 * └──────────────┘     └──────────────────┘     │ gateway çağırılır│
 *                                                └────────┬────────┘
 *                                                         │
 *                                          ┌──────────────┴──────────────┐
 *                                          │  CircuitBreaker + Retry ilə │
 *                                          │  gateway-ə sorğu göndərilir │
 *                                          └──────────────┬──────────────┘
 *                                                         │
 *                                               ┌─────────┴─────────┐
 *                                               │ Uğurlu    │ Uğursuz │
 *                                               │ complete()│ fail()  │
 *                                               └───────────┴─────────┘
 */
final class ProcessPaymentHandler implements CommandHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentStrategyResolver $strategyResolver,
        private readonly PaymentGatewayACL $gatewayACL,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly RetryHandler $retryHandler,
    ) {
    }

    /**
     * Ödəniş əmrini emal et.
     *
     * @param ProcessPaymentCommand $command
     * @return string Yaradılmış ödənişin ID-si
     */
    public function handle(Command $command): string
    {
        // 1. Value Object-ləri yarat — ham (raw) data-dan domain obyektlərinə çevir
        $money = new Money($command->amount, $command->currency);
        $method = PaymentMethod::fromString($command->paymentMethod);

        // 2. Payment Aggregate yaradılır — status: PENDING
        $payment = Payment::create(
            orderId: $command->orderId,
            amount: $money,
            method: $method,
        );

        // 3. Strategy pattern ilə uyğun gateway-i seç
        //    credit_card → CreditCardGateway, paypal → PayPalGateway və s.
        $gateway = $this->strategyResolver->resolve($method);

        // 4. Statusu PROCESSING-ə keçir — artıq emal olunur
        $payment->process();

        // 5. CircuitBreaker + Retry + ACL ilə gateway-ə sorğu göndər
        //    Bu üç pattern birlikdə xarici API çağırışını etibarlı edir:
        //    - CircuitBreaker: Gateway çökürsə, gereksiz sorğu göndərmir
        //    - Retry: Müvəqqəti xəta olsa, bir neçə dəfə təkrar cəhd edir
        //    - ACL: Xarici API cavabını bizim domain dilinə çevirir
        try {
            $result = $this->circuitBreaker->execute(function () use ($gateway, $money) {
                return $this->retryHandler->execute(function () use ($gateway, $money) {
                    return $this->gatewayACL->processCharge($gateway, $money);
                });
            });

            if ($result->isSuccess()) {
                // 6a. Uğurlu — ödəniş tamamlandı
                $payment->complete($result->transactionId());
            } else {
                // 6b. Uğursuz — ödəniş uğursuz oldu
                $payment->fail($result->errorMessage() ?? 'Bilinməyən xəta');
            }
        } catch (\Throwable $e) {
            // 6c. Exception baş verdi — CircuitBreaker açıq ola bilər və ya bütün retry-lar uğursuz olub
            $payment->fail('Sistem xətası: ' . $e->getMessage());
        }

        // 7. Payment-i verilənlər bazasına saxla
        //    Repository həmçinin Domain Event-ləri dispatch edəcək
        $this->paymentRepository->save($payment);

        return $payment->paymentId()->value();
    }
}
