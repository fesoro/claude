package az.ecommerce.payment.domain.event;

import az.ecommerce.shared.domain.IntegrationEvent;

import java.time.Instant;
import java.util.UUID;

public record PaymentCompletedIntegrationEvent(
        UUID eventId, Instant occurredAt, UUID paymentId, UUID orderId, long amount
) implements IntegrationEvent {
    @Override public String routingKey() { return "payment.completed"; }
    public static PaymentCompletedIntegrationEvent of(UUID p, UUID o, long a) {
        return new PaymentCompletedIntegrationEvent(UUID.randomUUID(), Instant.now(), p, o, a);
    }
}
