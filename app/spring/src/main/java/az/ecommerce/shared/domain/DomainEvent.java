package az.ecommerce.shared.domain;

import java.time.Instant;
import java.util.UUID;

/**
 * Laravel: src/Shared/Domain/DomainEvent.php (abstract)
 *   - eventId, occurredAt, eventName, eventVersion
 *
 * Spring: interface, hər concrete event Java record olur.
 * Sinxron, eyni transaction daxilində tetiklənir.
 */
public interface DomainEvent {

    UUID eventId();

    Instant occurredAt();

    /**
     * Schema versioning üçün (EventUpcaster bunu istifadə edir)
     */
    default int eventVersion() {
        return 1;
    }

    /**
     * Routing key üçün (RabbitMQ-da hansı queue-ya yönəldiləcək)
     */
    default String eventName() {
        return getClass().getSimpleName();
    }
}
