package az.ecommerce.product.domain.event;

import az.ecommerce.shared.domain.IntegrationEvent;

import java.time.Instant;
import java.util.UUID;

/** Notification context bunu dinləyir → admin-ə email göndərir */
public record LowStockIntegrationEvent(
        UUID eventId, Instant occurredAt,
        UUID productId, String productName, int currentStock
) implements IntegrationEvent {
    @Override public String routingKey() { return "product.stock.low"; }
    public static LowStockIntegrationEvent of(UUID productId, String name, int stock) {
        return new LowStockIntegrationEvent(UUID.randomUUID(), Instant.now(), productId, name, stock);
    }
}
