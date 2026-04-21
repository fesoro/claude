<?php

declare(strict_types=1);

namespace Src\Order\Application\Commands\UpdateOrderStatus;

use Src\Order\Domain\Repositories\OrderRepositoryInterface;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Order\Domain\ValueObjects\OrderStatus;
use Src\Shared\Application\Bus\Command;
use Src\Shared\Application\Bus\CommandHandler;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * UPDATE ORDER STATUS HANDLER
 * ============================
 * Sifariş statusunu yeniləyən handler.
 *
 * AXIN:
 * UpdateOrderStatusCommand
 *   → UpdateOrderStatusHandler
 *     → Repository-dən Order-i tap
 *     → Yeni statusu müəyyən et
 *     → Order-in müvafiq metodunu çağır (confirm, markAsPaid, ship, deliver, cancel)
 *     → Repository-yə saxla
 *
 * DİQQƏT: Hər status keçidi üçün Order entity-nin xüsusi metodu çağırılır.
 * Birbaşa status dəyişmək olmaz — bu State Machine qaydalarını qoruyur.
 */
class UpdateOrderStatusHandler implements CommandHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @param Command $command UpdateOrderStatusCommand olmalıdır
     * @throws DomainException Sifariş tapılmadıqda və ya keçid mümkün olmadıqda
     */
    public function handle(Command $command): void
    {
        /** @var UpdateOrderStatusCommand $command */

        // 1. Sifarişi DB-dən tap
        $order = $this->orderRepository->findById(
            OrderId::fromString($command->orderId()),
        );

        if ($order === null) {
            throw new DomainException("Sifariş tapılmadı: {$command->orderId()}");
        }

        // 2. Yeni statusu OrderStatus Value Object-ə çevir (validasiya olunur)
        $newStatus = new OrderStatus($command->newStatus());

        // 3. Hər status üçün müvafiq domain metodunu çağır
        // Birbaşa $order->setStatus() etmirik — bu encapsulation-ı pozar!
        // Hər metod öz biznes qaydalarını yoxlayır və event qeydə alır.
        match ($newStatus->value()) {
            OrderStatus::CONFIRMED => $order->confirm(),
            OrderStatus::PAID      => $order->markAsPaid(),
            OrderStatus::SHIPPED   => $order->ship(),
            OrderStatus::DELIVERED => $order->deliver(),
            OrderStatus::CANCELLED => $order->cancel('Admin tərəfindən ləğv edildi'),
            default => throw new DomainException(
                "'{$newStatus->value()}' statusuna keçid dəstəklənmir."
            ),
        };

        // 4. DB-yə saxla
        $this->orderRepository->save($order);
    }
}
