<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Events;

use Src\Shared\Domain\IntegrationEvent;

/**
 * PAYMENT FAILED INTEGRATION EVENT
 * =================================
 * Ödəniş uğursuz olduqda başqa modullara göndərilən Integration Event.
 *
 * Bu event Order moduluna RabbitMQ ilə göndərilir ki:
 * - Sifariş statusu "ödəniş uğursuz" olaraq yenilənsin
 * - Rezerv olunmuş stok geri qaytarılsın
 * - Müştəriyə bildiriş göndərilsin
 */
final class PaymentFailedIntegrationEvent extends IntegrationEvent
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

    public function sourceContext(): string
    {
        return 'payment';
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
