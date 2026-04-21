<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Bus;

use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\IntegrationEvent;
use Src\Shared\Infrastructure\Messaging\RabbitMQPublisher;

/**
 * EVENT DISPATCHER
 * ================
 * Domain Event-ləri dinləyicilərə (listener) çatdırır.
 *
 * İKİ TİP EVENT:
 * 1. Domain Event → Laravel event dispatcher ilə sinxron göndərilir (eyni modul daxilində).
 * 2. Integration Event → RabbitMQ ilə asinxron göndərilir (digər modullara).
 *
 * AXIN:
 * AggregateRoot →pullDomainEvents()→ EventDispatcher
 *   ├─ Domain Event → Laravel Listener (sinxron)
 *   └─ Integration Event → RabbitMQ Publisher (asinxron)
 */
class EventDispatcher
{
    /** @var array<string, callable[]> */
    private array $listeners = [];

    public function __construct(
        private ?RabbitMQPublisher $rabbitMQPublisher = null,
    ) {}

    /**
     * Event-ə listener əlavə et.
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Event-ləri dispatch et.
     *
     * @param DomainEvent[] $events
     */
    public function dispatch(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatchSingle($event);
        }
    }

    private function dispatchSingle(DomainEvent $event): void
    {
        $eventClass = get_class($event);

        // 1. Sinxron listener-ləri çağır
        if (isset($this->listeners[$eventClass])) {
            foreach ($this->listeners[$eventClass] as $listener) {
                $listener($event);
            }
        }

        // 2. Integration Event-dirsə, RabbitMQ-ya göndər
        if ($event instanceof IntegrationEvent && $this->rabbitMQPublisher) {
            $this->rabbitMQPublisher->publish($event);
        }
    }
}
