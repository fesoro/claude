<?php

declare(strict_types=1);

namespace Src\User\Domain\Events;

use Src\Shared\Domain\IntegrationEvent;

/**
 * USER REGISTERED INTEGRATION EVENT
 * ==================================
 * Bu event istifadəçi qeydiyyatı haqqında DİGƏR MODULLARA məlumat göndərir.
 *
 * DOMAIN EVENT vs INTEGRATION EVENT:
 * - UserRegisteredEvent (Domain): User modulu DAXİLİNDƏ istifadə olunur.
 *   Məsələn: xoş gəldin emaili göndərmək.
 *
 * - UserRegisteredIntegrationEvent (Integration): DİGƏR modullara göndərilir.
 *   Məsələn: Notification modulu yeni istifadəçi haqqında xəbərdar olur.
 *
 * AXIN:
 * 1. User::create() → UserRegisteredEvent (domain event) yaranır
 * 2. DomainEventListener → UserRegisteredIntegrationEvent yaradır
 * 3. Outbox Pattern → Bu event əvvəlcə bazaya yazılır (itməsin deyə)
 * 4. Outbox Publisher → RabbitMQ-ya göndərilir
 * 5. Notification modulu → RabbitMQ-dan oxuyub emal edir
 *
 * NƏYƏ İKİ AYRI EVENT?
 * - Domain Event modulun daxili işidir — digər modullar görməməlidir.
 * - Integration Event isə modullar arası kontraktdır — dəyişdirmək çətindir.
 * - Bu ayrılıq hər modulu müstəqil inkişaf etdirməyə imkan verir.
 */
final class UserRegisteredIntegrationEvent extends IntegrationEvent
{
    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        private readonly string $name,
    ) {
        parent::__construct();
    }

    /**
     * Bu event-in hansı bounded context-dən gəldiyini göstərir.
     * RabbitMQ routing key yaradılmasında istifadə olunur.
     * routingKey() = sourceContext() + "." + eventName() = "user.registered"
     */
    public function sourceContext(): string
    {
        return 'user';
    }

    public function eventName(): string
    {
        return 'registered';
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId(),
            'event_name' => $this->eventName(),
            'source_context' => $this->sourceContext(),
            'routing_key' => $this->routingKey(),
            'occurred_at' => $this->occurredAt()->format('Y-m-d H:i:s'),
            'user_id' => $this->userId,
            'email' => $this->email,
            'name' => $this->name,
        ];
    }
}
