package az.ecommerce.user.domain.event;

import az.ecommerce.shared.domain.DomainEvent;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.domain.valueobject.UserId;

import java.time.Instant;
import java.util.UUID;

/**
 * Laravel: src/User/Domain/Events/UserRegisteredEvent.php
 * Domain Event — eyni context-də @EventListener-lər tetiklənir.
 */
public record UserRegisteredEvent(
        UUID eventId,
        Instant occurredAt,
        UserId userId,
        Email email,
        String name
) implements DomainEvent {

    public static UserRegisteredEvent of(UserId userId, Email email, String name) {
        return new UserRegisteredEvent(UUID.randomUUID(), Instant.now(), userId, email, name);
    }
}
