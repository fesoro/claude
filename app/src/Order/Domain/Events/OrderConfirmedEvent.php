<?php

declare(strict_types=1);

namespace Src\Order\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * ORDER CONFIRMED EVENT (Domain Event)
 * =====================================
 * "Sifariş təsdiqləndi" hadisəsi — Order::confirm() çağırıldıqda qeydə alınır.
 *
 * BU EVENT NƏ VAXT BAŞ VERİR?
 * - Admin sifarişi yoxlayıb təsdiqləyəndə (PENDING → CONFIRMED).
 * - Bu addımdan sonra ödəniş prosesi başlaya bilər.
 *
 * BU EVENT-İ KİM DİNLƏYİR?
 * - Payment modulu: ödəniş prosesini başladır.
 * - Notification modulu: müştəriyə "sifarişiniz təsdiqləndi" mesajı göndərir.
 */
class OrderConfirmedEvent extends DomainEvent
{
    public function __construct(
        private readonly string $orderId,
    ) {
        parent::__construct();
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function eventName(): string
    {
        return 'order.confirmed';
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
        ];
    }
}
