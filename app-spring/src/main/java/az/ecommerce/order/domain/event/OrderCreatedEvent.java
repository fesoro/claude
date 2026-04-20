package az.ecommerce.order.domain.event;

import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record OrderCreatedEvent(UUID eventId, Instant occurredAt, OrderId orderId, UUID userId) implements DomainEvent {
    public static OrderCreatedEvent of(OrderId id, UUID userId) {
        return new OrderCreatedEvent(UUID.randomUUID(), Instant.now(), id, userId);
    }
}
