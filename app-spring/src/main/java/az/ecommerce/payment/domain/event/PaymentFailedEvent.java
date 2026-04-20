package az.ecommerce.payment.domain.event;

import az.ecommerce.payment.domain.valueobject.PaymentId;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record PaymentFailedEvent(UUID eventId, Instant occurredAt, PaymentId paymentId, UUID orderId, String reason) implements DomainEvent {
    public static PaymentFailedEvent of(PaymentId id, UUID orderId, String reason) {
        return new PaymentFailedEvent(UUID.randomUUID(), Instant.now(), id, orderId, reason);
    }
}
