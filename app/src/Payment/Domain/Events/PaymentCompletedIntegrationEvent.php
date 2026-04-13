<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Events;

use Src\Shared\Domain\IntegrationEvent;

/**
 * PAYMENT COMPLETED INTEGRATION EVENT
 * ====================================
 * Bu Integration Event-dir — Payment modulundan başqa modullara (Order, Notification)
 * RabbitMQ vasitəsilə göndərilir.
 *
 * DOMAIN EVENT vs INTEGRATION EVENT:
 * - PaymentCompletedEvent (Domain) → Payment modulu daxilində istifadə olunur
 * - PaymentCompletedIntegrationEvent (Integration) → Order moduluna RabbitMQ ilə gedir
 *
 * AXIN:
 * 1. Payment tamamlanır → PaymentCompletedEvent (domain) yaranır
 * 2. Event Listener PaymentCompletedEvent-i dinləyir
 * 3. Listener PaymentCompletedIntegrationEvent yaradır
 * 4. Outbox Pattern ilə RabbitMQ-ya göndərilir
 * 5. Order modulu dinləyir və sifarişin statusunu yeniləyir
 *
 * sourceContext() = "payment" — bu event-in Payment modulundan gəldiyini göstərir.
 * routingKey() = "payment.payment.completed" — RabbitMQ routing key.
 */
final class PaymentCompletedIntegrationEvent extends IntegrationEvent
{
    public function __construct(
        private readonly string $paymentId,
        private readonly string $orderId,
        private readonly string $transactionId,
        private readonly float $amount,
        private readonly string $currency,
    ) {
        parent::__construct();
    }

    public function paymentId(): string
    {
        return $this->paymentId;
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function transactionId(): string
    {
        return $this->transactionId;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Bu event-in hansı bounded context-dən gəldiyini göstərir.
     */
    public function sourceContext(): string
    {
        return 'payment';
    }

    public function eventName(): string
    {
        return 'payment.completed';
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
