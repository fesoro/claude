<?php

declare(strict_types=1);

namespace Src\Order\Application\Commands\UpdateOrderStatus;

use Src\Shared\Application\Bus\Command;

/**
 * UPDATE ORDER STATUS COMMAND
 * ============================
 * Sifarişin statusunu yeniləmək üçün əmr.
 *
 * BU COMMAND NƏ VAXT İSTİFADƏ OLUNUR?
 * - Admin paneldən status dəyişdirəndə.
 * - Digər bounded context-lərdən event gələndə (məs: Payment-dən "ödəniş edildi").
 * - Karqo şirkətindən webhook gələndə (məs: "göndərildi", "çatdırıldı").
 *
 * DİQQƏT: Status keçidinin düzgünlüyü Order entity-daxilində yoxlanılır
 * (State Machine pattern — OrderStatus.canTransitionTo()).
 */
class UpdateOrderStatusCommand implements Command
{
    /**
     * @param string $orderId  Sifarişin ID-si
     * @param string $newStatus Yeni status dəyəri (pending, confirmed, paid, shipped, delivered, cancelled)
     */
    public function __construct(
        private readonly string $orderId,
        private readonly string $newStatus,
    ) {}

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function newStatus(): string
    {
        return $this->newStatus;
    }
}
