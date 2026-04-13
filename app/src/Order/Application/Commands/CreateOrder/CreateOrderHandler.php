<?php

declare(strict_types=1);

namespace Src\Order\Application\Commands\CreateOrder;

use Src\Order\Application\DTOs\OrderDTO;
use Src\Order\Domain\Events\OrderCreatedIntegrationEvent;
use Src\Order\Domain\Factories\OrderFactory;
use Src\Order\Domain\Repositories\OrderRepositoryInterface;
use Src\Order\Infrastructure\Outbox\OutboxMessage;
use Src\Order\Infrastructure\Outbox\OutboxRepository;
use Src\Shared\Application\Bus\Command;
use Src\Shared\Application\Bus\CommandHandler;

/**
 * CREATE ORDER HANDLER (CQRS + Outbox Pattern)
 * ==============================================
 * CreateOrderCommand-ı emal edən handler.
 *
 * BU HANDLER-İN VƏZİFƏSİ:
 * 1. OrderFactory ilə yeni Order aggregate yaratmaq.
 * 2. Repository-yə saxlamaq (DB-yə yazmaq).
 * 3. Integration Event-i Outbox-a yazmaq (RabbitMQ üçün).
 *
 * HANDLER QAYDALARI (xatırlatma):
 * - Handler biznes logikası ehtiva ETMİR — onu Domain layer-ə buraxır.
 * - Handler yalnız KOORDİNASİYA edir: factory çağır, repository-yə saxla, event göndər.
 * - Hər Command-ın BİR handler-i olur (1:1).
 *
 * OUTBOX PATTERN BURADA:
 * - Integration Event birbaşa RabbitMQ-ya göndərilmir.
 * - Əvvəlcə Outbox cədvəlinə yazılır (eyni DB transaction-da).
 * - Sonra OutboxPublisher (cron job) onu RabbitMQ-ya göndərir.
 * - Bu "at-least-once delivery" və data consistency təmin edir.
 *
 * AXIN:
 * CreateOrderCommand
 *   → CreateOrderHandler
 *     → OrderFactory.createFromDTO() — Order yaradılır
 *     → OrderRepository.save() — DB-yə yazılır
 *     → OutboxRepository.save() — Integration Event Outbox-a yazılır
 *     → return OrderDTO — nəticə qaytarılır
 */
class CreateOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly OrderFactory $orderFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OutboxRepository $outboxRepository,
    ) {}

    /**
     * Sifariş yaratma əmrini emal et.
     *
     * @param Command $command CreateOrderCommand olmalıdır
     * @return OrderDTO Yaradılmış sifarişin DTO-su
     */
    public function handle(Command $command): OrderDTO
    {
        /** @var CreateOrderCommand $command */

        // 1. Command-ı validate et — yanlış data DB-yə getməsin
        $command->validate();

        // 2. Factory ilə Order aggregate yarat
        // Factory: DTO-dan Address, OrderItem-lər yaradır və Order-ə əlavə edir
        $order = $this->orderFactory->createFromDTO($command->dto());

        // 3. Order-i DB-yə saxla
        // Repository daxilində Domain Event-lər dispatch olunacaq
        $this->orderRepository->save($order);

        // 4. Integration Event-i Outbox-a yaz
        // DİQQƏT: Bu eyni DB transaction-da olmalıdır!
        // Əgər Order saxlanıb amma Outbox yazılmayıbsa — data inconsistency olacaq.
        $integrationEvent = new OrderCreatedIntegrationEvent(
            orderId: $order->orderId()->value(),
            userId: $order->userId(),
            totalAmount: $order->totalAmount()->amount(),
        );

        $outboxMessage = OutboxMessage::create(
            eventName: $integrationEvent->eventName(),
            payload: $integrationEvent->toArray(),
            routingKey: $integrationEvent->routingKey(),
        );

        $this->outboxRepository->save($outboxMessage);

        // 5. DTO qaytarır — domain obyekti xaricə çıxmır
        return OrderDTO::fromEntity($order);
    }
}
