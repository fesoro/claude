<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * DOMAIN EVENT (DDD + Event Driven Pattern)
 * ==========================================
 * Domain Event — domendə baş verən vacib hadisəni təmsil edir.
 *
 * NƏDİR?
 * - Keçmişdə baş vermiş bir şey: "Sifariş yaradıldı", "Ödəniş tamamlandı".
 * - Past tense (keçmiş zaman) ilə adlandırılır: OrderCreated, PaymentCompleted.
 * - Immutable-dir — baş vermiş hadisəni dəyişmək olmaz.
 *
 * DOMAIN EVENT vs INTEGRATION EVENT:
 * - Domain Event: Eyni bounded context daxilində istifadə olunur.
 *   Məsələn: OrderCreatedEvent → eyni modulda OrderTotalCalculator dinləyir.
 *
 * - Integration Event: Fərqli bounded context-lər arası, RabbitMQ ilə göndərilir.
 *   Məsələn: OrderCreatedIntegrationEvent → Payment moduluna RabbitMQ ilə gedir.
 *
 * NƏYƏ LAZIMDIR?
 * - Modullar arası loose coupling (zəif bağlılıq) təmin edir.
 * - Bir modul digərini birbaşa çağırmır, event göndərir.
 */
abstract class DomainEvent
{
    private string $eventId;
    private \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->eventId = uuid_create();
        $this->occurredAt = new \DateTimeImmutable();
    }

    /**
     * Hər event-in unikal ID-si — event tracking və idempotency üçün.
     */
    public function eventId(): string
    {
        return $this->eventId;
    }

    /**
     * Event-in baş vermə vaxtı.
     */
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Event-in adı — RabbitMQ routing key kimi istifadə olunur.
     * Məsələn: "order.created", "payment.completed"
     */
    abstract public function eventName(): string;

    /**
     * Event-i array-ə çevir — serialization üçün (RabbitMQ-ya göndərmək üçün).
     */
    abstract public function toArray(): array;
}
