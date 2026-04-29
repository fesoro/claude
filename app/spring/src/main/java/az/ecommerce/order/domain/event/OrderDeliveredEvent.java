package az.ecommerce.order.domain.event;

import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record OrderDeliveredEvent(UUID eventId, Instant occurredAt, OrderId orderId) implements DomainEvent {
    public static OrderDeliveredEvent of(OrderId id) {
        return new OrderDeliveredEvent(UUID.randomUUID(), Instant.now(), id);
    }
}
