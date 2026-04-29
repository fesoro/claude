package az.ecommerce.order.domain.event;

import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record OrderCreatedEvent(UUID eventId, Instant occurredAt, OrderId orderId, UUID userId,
                                long totalAmount, String currency, int itemCount) implements DomainEvent {
    public static OrderCreatedEvent of(OrderId id, UUID userId, long totalAmount, Currency currency, int itemCount) {
        return new OrderCreatedEvent(UUID.randomUUID(), Instant.now(), id, userId, totalAmount, currency.name(), itemCount);
    }
}
