package az.ecommerce.order.domain.event;

import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record OrderPaidEvent(UUID eventId, Instant occurredAt, OrderId orderId) implements DomainEvent {
    public static OrderPaidEvent of(OrderId id) {
        return new OrderPaidEvent(UUID.randomUUID(), Instant.now(), id);
    }
}
