package az.ecommerce.product.domain.event;

import az.ecommerce.product.domain.valueobject.ProductId;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record StockDecreasedEvent(
        UUID eventId, Instant occurredAt,
        ProductId productId, int amount, int newQuantity
) implements DomainEvent {
    public static StockDecreasedEvent of(ProductId id, int amount, int newQty) {
        return new StockDecreasedEvent(UUID.randomUUID(), Instant.now(), id, amount, newQty);
    }
}
