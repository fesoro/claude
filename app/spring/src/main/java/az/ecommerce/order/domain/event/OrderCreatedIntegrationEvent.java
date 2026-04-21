package az.ecommerce.order.domain.event;

import az.ecommerce.shared.domain.IntegrationEvent;

import java.time.Instant;
import java.util.UUID;

public record OrderCreatedIntegrationEvent(
        UUID eventId, Instant occurredAt,
        UUID orderId, UUID userId, long totalAmount, String currency
) implements IntegrationEvent {
    @Override public String routingKey() { return "order.created"; }
}
