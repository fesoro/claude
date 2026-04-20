package az.ecommerce.user.domain.event;

import az.ecommerce.shared.domain.IntegrationEvent;

import java.time.Instant;
import java.util.UUID;

/**
 * Laravel: UserRegisteredIntegrationEvent.php
 * Bounded context-lər arası — Notification context bunu dinləyir
 * (welcome email göndərmək üçün).
 */
public record UserRegisteredIntegrationEvent(
        UUID eventId,
        Instant occurredAt,
        UUID userId,
        String email,
        String name
) implements IntegrationEvent {

    @Override
    public String routingKey() {
        return "user.registered";
    }

    public static UserRegisteredIntegrationEvent fromDomain(UserRegisteredEvent ev) {
        return new UserRegisteredIntegrationEvent(
                UUID.randomUUID(), Instant.now(),
                ev.userId().value(), ev.email().value(), ev.name());
    }
}
