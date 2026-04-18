<?php

declare(strict_types=1);

namespace Src\Order\Domain\Events;

use Src\Shared\Domain\IntegrationEvent;

/**
 * ORDER CREATED INTEGRATION EVENT
 * ================================
 * Bu event fərqli bounded context-lərə (Payment, Notification) göndərilir.
 *
 * DOMAIN EVENT vs INTEGRATION EVENT (vacib fərq):
 * ┌─────────────────────────────────────────────────────────────┐
 * │ Domain Event (OrderCreatedEvent):                          │
 * │ - Eyni modul daxilində işləyir.                            │
 * │ - Sinxron (eyni anda) emal olunur.                         │
 * │ - Eyni database transaction-da baş verir.                  │
 * │ - Uğursuz olarsa, bütün əməliyyat geri alınır (rollback). │
 * └─────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │ Integration Event (OrderCreatedIntegrationEvent):           │
 * │ - Fərqli modullara RabbitMQ ilə göndərilir.               │
 * │ - Asinxron (ayrı vaxtda) emal olunur.                     │
 * │ - Outbox Pattern ilə əvvəlcə DB-yə yazılır.              │
 * │ - Sonra ayrıca proses RabbitMQ-ya göndərir.               │
 * └─────────────────────────────────────────────────────────────┘
 *
 * AXIN:
 * 1. Order::create() → OrderCreatedEvent (domain, sinxron)
 * 2. Event Listener → OrderCreatedIntegrationEvent yaradır
 * 3. Outbox → DB-yə yazılır (eyni transaction-da)
 * 4. OutboxPublisher (cron job) → RabbitMQ-ya göndərir
 * 5. Payment modulu → RabbitMQ-dan oxuyub ödəniş prosesini başladır
 */
class OrderCreatedIntegrationEvent extends IntegrationEvent
{
    public function __construct(
        private readonly string $orderId,
        private readonly string $userId,
        private readonly float $totalAmount,
    ) {
        parent::__construct();
    }

    /**
     * Bu event-in mənbəyi — "order" bounded context-i.
     * RabbitMQ routing key: "order.order.created" olacaq.
     */
    public function sourceContext(): string
    {
        return 'order';
    }

    public function eventName(): string
    {
        return 'order.created';
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function totalAmount(): float
    {
        return $this->totalAmount;
    }

    public function toArray(): array
    {
        return [
            'order_id'     => $this->orderId,
            'user_id'      => $this->userId,
            'total_amount' => $this->totalAmount,
        ];
    }
}
