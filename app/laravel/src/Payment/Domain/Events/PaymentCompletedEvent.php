<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * PAYMENT COMPLETED EVENT (Domain Event)
 * =======================================
 * Ödəniş uğurla tamamlananda baş verən hadisə.
 *
 * Bu event-dən sonra adətən:
 * - PaymentCompletedIntegrationEvent yaradılır və Order moduluna göndərilir
 * - Order modulu sifarişin statusunu "ödənildi" olaraq dəyişir
 */
final class PaymentCompletedEvent extends DomainEvent
{
    public function __construct(
        private readonly string $paymentId,
        private readonly string $orderId,
        private readonly string $transactionId,
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
        ];
    }
}
