package az.ecommerce.shared.infrastructure.bus;

import az.ecommerce.shared.domain.AggregateRoot;
import az.ecommerce.shared.domain.DomainEvent;
import az.ecommerce.shared.domain.IntegrationEvent;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.stereotype.Component;

/**
 * Laravel: src/Shared/Infrastructure/Bus/EventDispatcher.php
 *
 * Spring-də 2 tip event yayımlayırıq:
 * 1. DomainEvent → Spring ApplicationEventPublisher (sinxron, eyni JVM)
 *    @EventListener-lər sinxron tetiklənir.
 *
 * 2. IntegrationEvent → Spring Modulith Event Publication Registry (Outbox)
 *    avtomatik DB-yə yazılır, sonra RabbitMQ-yə publish olunur.
 *
 * Repository.save()-dən dərhal sonra çağrılır:
 *    eventDispatcher.dispatchAll(aggregate);
 */
@Component
public class EventDispatcher {

    private final ApplicationEventPublisher publisher;

    public EventDispatcher(ApplicationEventPublisher publisher) {
        this.publisher = publisher;
    }

    public void dispatchAll(AggregateRoot aggregate) {
        for (DomainEvent event : aggregate.pullDomainEvents()) {
            dispatch(event);
        }
    }

    public void dispatch(DomainEvent event) {
        // Spring sinxron yayımı (ApplicationEventPublisher)
        publisher.publishEvent(event);

        // IntegrationEvent isə əlavə olaraq Outbox-a yazılacaq
        // (Spring Modulith @ApplicationModuleListener bunu avtomatik edir,
        //  yaxud manual OutboxRelay)
        if (event instanceof IntegrationEvent integrationEvent) {
            publisher.publishEvent(new OutboxEvent(integrationEvent));
        }
    }

    /** Wrapper — OutboxRelay bu event-ləri tutub DB-yə yazır */
    public record OutboxEvent(IntegrationEvent integrationEvent) { }
}
