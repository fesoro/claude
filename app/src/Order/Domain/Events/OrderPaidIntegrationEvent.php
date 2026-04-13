<?php

declare(strict_types=1);

namespace Src\Order\Domain\Events;

use Src\Shared\Domain\IntegrationEvent;

/**
 * ORDER PAID INTEGRATION EVENT
 * =============================
 * Sifarişin ödənildiyi barədə digər bounded context-lərə göndərilən event.
 *
 * BU EVENT HARAYA GEDİR?
 * - Warehouse modulu: məhsulları hazırlamağa başlayır (picking, packing).
 * - Notification modulu: müştəriyə email/SMS göndərir.
 * - Analytics modulu: gəlir hesabatını yeniləyir.
 *
 * OUTBOX PATTERN İLƏ GÖNDƏRİLMƏ:
 * Bu event birbaşa RabbitMQ-ya göndərilmir. Əvvəlcə Outbox cədvəlinə yazılır,
 * sonra OutboxPublisher onu RabbitMQ-ya göndərir. Bu "at-least-once delivery"
 * (ən azı bir dəfə çatdırılma) təmin edir.
 */
class OrderPaidIntegrationEvent extends IntegrationEvent
{
    public function __construct(
        private readonly string $orderId,
        private readonly float $totalAmount,
        private readonly string $userId,
    ) {
        parent::__construct();
    }

    public function sourceContext(): string
    {
        return 'order';
    }

    public function eventName(): string
    {
        return 'order.paid';
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function totalAmount(): float
    {
        return $this->totalAmount;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function toArray(): array
    {
        return [
            'order_id'     => $this->orderId,
            'total_amount' => $this->totalAmount,
            'user_id'      => $this->userId,
        ];
    }
}
