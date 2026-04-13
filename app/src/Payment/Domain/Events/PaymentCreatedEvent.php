<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * PAYMENT CREATED EVENT (Domain Event)
 * =====================================
 * Bu event ödəniş yaradılanda baş verir.
 *
 * Bu Domain Event-dir — yalnız Payment bounded context daxilində istifadə olunur.
 * Başqa modullara göndərilmir. Əgər başqa modul bilməlidirsə, IntegrationEvent yaradılmalıdır.
 *
 * İSTİFADƏ NÜMUNƏSİ:
 * - Ödəniş yaradıldı → log yazılsın
 * - Ödəniş yaradıldı → admin-ə bildiriş göndərilsin (eyni modul daxilində)
 */
final class PaymentCreatedEvent extends DomainEvent
{
    public function __construct(
        private readonly string $paymentId,
        private readonly string $orderId,
        private readonly float $amount,
        private readonly string $currency,
        private readonly string $method,
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

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function eventName(): string
    {
        return 'payment.created';
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
        ];
    }
}
