package az.ecommerce.shared.domain;

/**
 * Laravel: src/Shared/Domain/IntegrationEvent.php
 *   - Bounded context-lər arası async ünsiyyət üçün
 *   - RabbitMQ-yə publish olunur (Outbox vasitəsilə)
 *
 * Spring: marker interface — Domain event-dən fərqi:
 *   - DomainEvent: eyni context-də @EventListener-lər tetiklənir (sinxron)
 *   - IntegrationEvent: RabbitMQ-yə yazılır, başqa context oxuyur (asinxron)
 *
 * Adlandırma qaydası: <Bounded><Action>IntegrationEvent
 *   məsələn: OrderCreatedIntegrationEvent, PaymentCompletedIntegrationEvent
 */
public interface IntegrationEvent extends DomainEvent {

    /**
     * RabbitMQ routing key — exchange topic pattern-i
     * Format: <context>.<entity>.<action>
     * Misal: "order.order.created", "payment.payment.completed"
     */
    String routingKey();
}
