<?php

declare(strict_types=1);

namespace Src\Order\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * ORDER CREATED EVENT (Domain Event)
 * ===================================
 * "Sifariş yaradıldı" hadisəsi — Order::create() çağırıldıqda qeydə alınır.
 *
 * BU EVENT-İ KİM DİNLƏYİR?
 * - Eyni bounded context daxilində:
 *   1. Loqqer — "yeni sifariş yaradıldı" logu yazır.
 *   2. Statistika servisi — gündəlik sifariş sayını artırır.
 *
 * - Fərqli bounded context-lər üçün bu event Integration Event-ə çevrilir:
 *   OrderCreatedEvent → OrderCreatedIntegrationEvent → RabbitMQ → digər modullar
 *
 * DOMAIN EVENT QAYDALARI (xatırlatma):
 * 1. Keçmiş zamanda adlandırılır: "Created" (yaradıldı), "Confirmed" (təsdiqləndi).
 * 2. Immutable-dir — baş vermiş hadisəni dəyişmək olmaz.
 * 3. Yalnız lazımi data daşıyır — bütün Order obyektini deyil.
 */
class OrderCreatedEvent extends DomainEvent
{
    public function __construct(
        private readonly string $orderId,
        private readonly string $userId,
    ) {
        parent::__construct();
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    /**
     * Event adı — RabbitMQ routing key-in bir hissəsi olacaq.
     * "order.created" → payment queue bu mesajı dinləyir.
     */
    public function eventName(): string
    {
        return 'order.created';
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'user_id'  => $this->userId,
        ];
    }
}
