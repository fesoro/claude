package az.ecommerce.payment.domain.event;

import az.ecommerce.shared.domain.IntegrationEvent;

import java.time.Instant;
import java.util.UUID;

public record PaymentFailedIntegrationEvent(
        UUID eventId, Instant occurredAt, UUID paymentId, UUID orderId, String reason
) implements IntegrationEvent {
    @Override public String routingKey() { return "payment.failed"; }
    public static PaymentFailedIntegrationEvent of(UUID p, UUID o, String r) {
        return new PaymentFailedIntegrationEvent(UUID.randomUUID(), Instant.now(), p, o, r);
    }
}
