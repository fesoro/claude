<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\ProcessPaymentRequest;
use Illuminate\Http\Request;
use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Application\Bus\QueryBus;
use Src\Payment\Application\Commands\ProcessPayment\ProcessPaymentCommand;

/**
 * PAYMENT CONTROLLER
 * ==================
 * Ödəniş əməliyyatları üçün HTTP endpoint-ləri.
 *
 * Bu controller arxasında çoxlu pattern işləyir:
 *
 * 1. STRATEGY PATTERN:
 *    payment_method-a görə müvafiq gateway seçilir (CreditCard, PayPal, BankTransfer)
 *
 * 2. ANTI-CORRUPTION LAYER (ACL):
 *    Xarici payment gateway-in cavabı domen dilinə çevrilir.
 *    Gateway "charge_status: ok" qaytarır → ACL bunu PaymentStatus::COMPLETED-ə çevirir.
 *
 * 3. CIRCUIT BREAKER:
 *    Gateway cavab vermirsə, ardıcıl uğursuz cəhdlərdən sonra sorğular bloklanır.
 *
 * 4. RETRY PATTERN:
 *    Müvəqqəti xətada (timeout, 503) exponential backoff ilə yenidən cəhd edilir.
 */
class PaymentController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {}

    /**
     * POST /api/payments/process
     * Ödənişi emal et.
     *
     * Request body:
     * {
     *   "order_id": "uuid",
     *   "amount": 59.98,
     *   "currency": "USD",
     *   "payment_method": "credit_card"  // credit_card | paypal | bank_transfer
     * }
     *
     * ARXITEKTURA AXINI:
     * 1. Controller → ProcessPaymentCommand
     * 2. Handler → PaymentStrategyResolver (Strategy Pattern)
     *    → payment_method = "credit_card" → CreditCardGateway seçilir
     * 3. PaymentGatewayACL → xarici gateway-ə sorğu göndərir (ACL Pattern)
     * 4. CircuitBreaker → gateway çökürsə, dərhal xəta qaytarır (Circuit Breaker)
     * 5. RetryHandler → müvəqqəti xətada yenidən cəhd edir (Retry Pattern)
     * 6. Uğurlu → PaymentCompletedIntegrationEvent → RabbitMQ
     * 7. Uğursuz → PaymentFailedIntegrationEvent → RabbitMQ
     *    → OrderSaga → sifarişi ləğv edir (Saga — compensating transaction)
     */
    public function process(ProcessPaymentRequest $request): JsonResponse
    {
        $command = new ProcessPaymentCommand(
            orderId: $request->input('order_id'),
            amount: (float) $request->input('amount'),
            currency: $request->input('currency', 'USD'),
            paymentMethod: $request->input('payment_method'),
        );

        $paymentId = $this->commandBus->dispatch($command);

        return ApiResponse::success(
            data: ['payment_id' => $paymentId],
            message: 'Ödəniş emal edilir'
        );
    }

    /**
     * GET /api/payments/{id}
     * Ödəniş detalları.
     */
    public function show(string $id): JsonResponse
    {
        /**
         * TODO: GetPaymentQuery implement et.
         * Hazırda placeholder — CQRS prinsipinə görə Query istifadə etmək lazımdır.
         *
         * Gələcəkdə belə olacaq:
         *   $query = new GetPaymentQuery(paymentId: $id);
         *   $payment = $this->queryBus->ask($query);
         *   return ApiResponse::success(new PaymentResource($payment));
         *
         * PaymentResource — when() ilə şərtli sahələr qaytarır:
         * - transaction_id → yalnız completed statusda
         * - failure_reason → yalnız failed statusda
         */
        return ApiResponse::error('PaymentQuery hələ implement edilməyib', code: 501);
    }
}
