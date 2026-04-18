<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * INTEGRATION EVENT (Event Driven Architecture)
 * ==============================================
 * Integration Event — fərqli bounded context-lər arasında əlaqə quran hadisədir.
 *
 * DOMAIN EVENT-dən FƏRQI:
 * - Domain Event: Modul daxilində, sinxron (eyni anda) işləyir.
 * - Integration Event: Modullər arası, asinxron (RabbitMQ ilə) göndərilir.
 *
 * AXIN:
 * 1. Order modulu → OrderCreatedEvent (domain event) yaranır
 * 2. Event Listener → OrderCreatedIntegrationEvent yaradır
 * 3. Outbox Pattern → Əvvəlcə DB-yə yazılır (itməməsi üçün)
 * 4. Outbox Publisher → RabbitMQ-ya göndərilir
 * 5. Payment modulu → RabbitMQ-dan oxuyub emal edir
 *
 * NƏYƏ LAZIMDIR?
 * - Modullər bir-birini birbaşa tanımır (loose coupling).
 * - Bir modul çöksə belə, digərləri işləməyə davam edir.
 * - RabbitMQ mesajı saxlayır, modul geri qayıdanda emal edir.
 */
abstract class IntegrationEvent extends DomainEvent
{
    /**
     * Bu event-in hansı bounded context-dən gəldiyini göstərir.
     * Məsələn: "order", "payment", "notification"
     */
    abstract public function sourceContext(): string;

    /**
     * RabbitMQ routing key — mesajın hansı queue-ya getməsini müəyyən edir.
     * Məsələn: "order.created" → payment queue-su bunu dinləyir.
     */
    public function routingKey(): string
    {
        return $this->sourceContext() . '.' . $this->eventName();
    }
}
