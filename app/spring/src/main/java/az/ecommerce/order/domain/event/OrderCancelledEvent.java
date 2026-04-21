package az.ecommerce.order.domain.event;

import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record OrderCancelledEvent(UUID eventId, Instant occurredAt, OrderId orderId, String reason) implements DomainEvent {
    public static OrderCancelledEvent of(OrderId id, String reason) {
        return new OrderCancelledEvent(UUID.randomUUID(), Instant.now(), id, reason);
    }
}
