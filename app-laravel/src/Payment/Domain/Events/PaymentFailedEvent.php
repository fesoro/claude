<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * PAYMENT FAILED EVENT (Domain Event)
 * ====================================
 * Ödəniş uğursuz olduqda baş verən hadisə.
 *
 * Ödəniş nəyə görə uğursuz ola bilər?
 * - Kredit kartının balansı çatmır
 * - Kartın müddəti bitib
 * - Gateway xətası (texniki problem)
 * - Fraud detection (saxtakarlıq aşkarlanması)
 *
 * Bu event-dən sonra:
 * - Müştəriyə "ödəniş uğursuz oldu" bildirişi göndərilə bilər
 * - PaymentFailedIntegrationEvent ilə Order moduluna məlumat verilər
 */
final class PaymentFailedEvent extends DomainEvent
{
    public function __construct(
        private readonly string $paymentId,
        private readonly string $orderId,
        private readonly string $reason,
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

    public function reason(): string
    {
        return $this->reason;
    }

    public function eventName(): string
    {
        return 'payment.failed';
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'reason' => $this->reason,
        ];
    }
}
