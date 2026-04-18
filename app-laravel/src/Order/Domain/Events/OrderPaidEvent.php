<?php

declare(strict_types=1);

namespace Src\Order\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * ORDER PAID EVENT (Domain Event)
 * ================================
 * "Sifariş ödənildi" hadisəsi — Order::markAsPaid() çağırıldıqda qeydə alınır.
 *
 * BU EVENT NƏ VAXT BAŞ VERİR?
 * - Payment bounded context ödənişi uğurla emal edib cavab göndərəndə.
 * - Saga pattern bu event-i alıb növbəti addıma keçir (göndərmə).
 *
 * BU EVENT-İ KİM DİNLƏYİR?
 * - Warehouse modulu: məhsulu hazırlamağa başlayır.
 * - Notification modulu: müştəriyə "ödənişiniz qəbul olundu" mesajı göndərir.
 * - Saga: prosesin növbəti addımına keçir.
 */
class OrderPaidEvent extends DomainEvent
{
    public function __construct(
        private readonly string $orderId,
        private readonly float $totalAmount,
    ) {
        parent::__construct();
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function totalAmount(): float
    {
        return $this->totalAmount;
    }

    public function eventName(): string
    {
        return 'order.paid';
    }

    public function toArray(): array
    {
        return [
            'order_id'     => $this->orderId,
            'total_amount' => $this->totalAmount,
        ];
    }
}
