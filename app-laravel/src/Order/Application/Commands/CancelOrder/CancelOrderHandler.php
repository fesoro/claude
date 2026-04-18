<?php

declare(strict_types=1);

namespace Src\Order\Application\Commands\CancelOrder;

use Src\Order\Domain\Repositories\OrderRepositoryInterface;
use Src\Order\Domain\Specifications\OrderCanBeCancelledSpec;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Shared\Application\Bus\Command;
use Src\Shared\Application\Bus\CommandHandler;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * CANCEL ORDER HANDLER (CQRS + Specification Pattern)
 * =====================================================
 * CancelOrderCommand-ı emal edən handler.
 *
 * BU HANDLER-DA SPECIFICATION PATTERN İSTİFADƏ OLUNUR:
 * - OrderCanBeCancelledSpec sifarişin ləğv edilə bilib-bilməyəcəyini yoxlayır.
 * - Handler birbaşa if/else yazmır — Specification-ı çağırır.
 * - Bu SRP (Single Responsibility Principle) prinsipinə uyğundur:
 *   Handler koordinasiya edir, Specification qaydanı yoxlayır.
 *
 * AXIN:
 * CancelOrderCommand
 *   → CancelOrderHandler
 *     → Repository-dən Order-i tap
 *     → OrderCanBeCancelledSpec ilə yoxla
 *     → Order.cancel() çağır
 *     → Repository-yə saxla
 */
class CancelOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCanBeCancelledSpec $cancelSpec,
    ) {}

    /**
     * Sifariş ləğv etmə əmrini emal et.
     *
     * @param Command $command CancelOrderCommand olmalıdır
     * @throws DomainException Sifariş tapılmadıqda və ya ləğv edilə bilmədikdə
     */
    public function handle(Command $command): void
    {
        /** @var CancelOrderCommand $command */

        // 1. Sifarişi DB-dən tap
        $order = $this->orderRepository->findById(
            OrderId::fromString($command->orderId()),
        );

        if ($order === null) {
            throw new DomainException("Sifariş tapılmadı: {$command->orderId()}");
        }

        // 2. Specification ilə yoxla — ləğv etmək olarmı?
        // Bu if/else əvəzinə Specification istifadə etməyə nümunədir:
        // ✗ if ($order->status()->isPending() || $order->status()->isConfirmed())
        // ✓ if ($this->cancelSpec->isSatisfiedBy($order))
        if (!$this->cancelSpec->isSatisfiedBy($order)) {
            throw new DomainException(
                "Sifariş ləğv edilə bilməz. Cari status: {$order->status()->value()}. " .
                "Yalnız 'pending' və ya 'confirmed' statusunda ləğv etmək olar."
            );
        }

        // 3. Sifarişi ləğv et — domain metodu çağırılır
        // Order::cancel() daxilində OrderCancelledEvent qeydə alınır
        $order->cancel($command->reason());

        // 4. DB-yə saxla — domain event-lər dispatch olunacaq
        $this->orderRepository->save($order);
    }
}
