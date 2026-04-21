package az.ecommerce.product.domain.event;

import az.ecommerce.product.domain.valueobject.Money;
import az.ecommerce.product.domain.valueobject.ProductId;
import az.ecommerce.product.domain.valueobject.ProductName;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record ProductCreatedEvent(
        UUID eventId, Instant occurredAt,
        ProductId productId, ProductName name, Money price
) implements DomainEvent {
    public static ProductCreatedEvent of(ProductId id, ProductName name, Money price) {
        return new ProductCreatedEvent(UUID.randomUUID(), Instant.now(), id, name, price);
    }
}
