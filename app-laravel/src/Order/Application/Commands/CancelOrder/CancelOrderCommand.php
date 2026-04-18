<?php

declare(strict_types=1);

namespace Src\Order\Application\Commands\CancelOrder;

use Src\Shared\Application\Bus\Command;

/**
 * CANCEL ORDER COMMAND
 * =====================
 * "Sifarişi ləğv et" əmri.
 *
 * BU COMMAND NƏ VAXT İSTİFADƏ OLUNUR?
 * 1. Müştəri sifarişi ləğv etmək istəyəndə.
 * 2. Admin sifarişi ləğv edəndə.
 * 3. Saga compensating transaction zamanı (ödəniş uğursuz olduqda).
 *
 * DİQQƏT: Handler-da OrderCanBeCancelledSpec istifadə olunur —
 * yalnız müəyyən statuslarda ləğv etmək mümkündür.
 */
class CancelOrderCommand implements Command
{
    public function __construct(
        private readonly string $orderId,
        private readonly string $reason = '',
    ) {}

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
