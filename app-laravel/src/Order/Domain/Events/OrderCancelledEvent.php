<?php

declare(strict_types=1);

namespace Src\Order\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * ORDER CANCELLED EVENT (Domain Event)
 * =====================================
 * "Sifariş ləğv edildi" hadisəsi — Order::cancel() çağırıldıqda qeydə alınır.
 *
 * BU EVENT NƏ VAXT BAŞ VERİR?
 * - Müştəri sifarişi ləğv edəndə.
 * - Ödəniş uğursuz olanda (Saga compensating transaction).
 * - Admin sifarişi ləğv edəndə.
 *
 * COMPENSATING TRANSACTION (əks əməliyyat):
 * Saga pattern-də əgər bir addım uğursuz olarsa, əvvəlki addımlar geri alınır.
 * Sifariş ləğv etmə — ən əsas compensating transaction-dır.
 * Məsələn: ödəniş uğursuz → sifariş ləğv edilir → stok geri qaytarılır.
 *
 * BU EVENT-İ KİM DİNLƏYİR?
 * - Inventory modulu: stoku geri qaytarır (reserve-i açır).
 * - Payment modulu: əgər ödəniş edilibsə, refund başladır.
 * - Notification modulu: müştəriyə "sifarişiniz ləğv edildi" mesajı göndərir.
 */
class OrderCancelledEvent extends DomainEvent
{
    public function __construct(
        private readonly string $orderId,
        private readonly string $reason,
    ) {
        parent::__construct();
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
        return 'order.cancelled';
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'reason'   => $this->reason,
        ];
    }
}
