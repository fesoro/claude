package az.ecommerce.payment.domain.event;

import az.ecommerce.payment.domain.valueobject.PaymentId;
import az.ecommerce.shared.domain.DomainEvent;

import java.time.Instant;
import java.util.UUID;

public record PaymentCreatedEvent(UUID eventId, Instant occurredAt, PaymentId paymentId, UUID orderId) implements DomainEvent {
    public static PaymentCreatedEvent of(PaymentId id, UUID orderId) {
        return new PaymentCreatedEvent(UUID.randomUUID(), Instant.now(), id, orderId);
    }
}
