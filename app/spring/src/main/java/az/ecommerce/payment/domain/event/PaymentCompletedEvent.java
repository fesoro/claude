package az.ecommerce.payment.domain.event;

import az.ecommerce.payment.domain.valueobject.PaymentId;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record PaymentCompletedEvent(UUID eventId, Instant occurredAt, PaymentId paymentId, UUID orderId) implements DomainEvent {
    public static PaymentCompletedEvent of(PaymentId id, UUID orderId) {
        return new PaymentCompletedEvent(UUID.randomUUID(), Instant.now(), id, orderId);
    }
}
