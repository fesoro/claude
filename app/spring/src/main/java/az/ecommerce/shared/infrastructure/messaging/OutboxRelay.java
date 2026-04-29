package az.ecommerce.shared.infrastructure.messaging;

import az.ecommerce.shared.infrastructure.bus.EventDispatcher;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;
import org.springframework.transaction.event.TransactionPhase;
import org.springframework.transaction.event.TransactionalEventListener;

/**
 * Laravel: src/Shared/Infrastructure/Messaging/OutboxRelay.php
 *
 * Spring: 2 hissədən ibarətdir:
 * 1. EventDispatcher.OutboxEvent-i tutub outbox_messages cədvəlinə yazır
 *    (transactional — eyni biznes tranzaksiya ilə)
 * 2. OutboxPublisher (@Scheduled) periodic olaraq published=false mesajları
 *    RabbitMQ-yə publish edir.
 *
 * Beləliklə dual-write problem həll olunur:
 *   - DB-yə yazılma uğursuz olarsa, message də yazılmır.
 *   - RabbitMQ down olarsa, message DB-də qalır, sonra retry olunur.
 */
@Component
public class OutboxRelay {

    private static final Logger log = LoggerFactory.getLogger(OutboxRelay.class);

    private final OutboxRepository outboxRepository;
    private final ObjectMapper objectMapper;

    public OutboxRelay(OutboxRepository outboxRepository, ObjectMapper objectMapper) {
        this.outboxRepository = outboxRepository;
        this.objectMapper = objectMapper;
    }

    /**
     * BEFORE_COMMIT phase: eyni aktiv transaction-da işləyir.
     * Hansı bounded context event publish edirsə, həmin contextin
     * transaction-ı daxilində outbox-a yazılır — ayrı TX manager lazım deyil.
     */
    @TransactionalEventListener(phase = TransactionPhase.BEFORE_COMMIT)
    public void onOutboxEvent(EventDispatcher.OutboxEvent wrapper) {
        try {
            var event = wrapper.integrationEvent();
            OutboxMessageEntity entity = new OutboxMessageEntity();
            entity.setMessageId(event.eventId());
            entity.setEventType(event.eventName());
            entity.setRoutingKey(event.routingKey());
            entity.setPayload(objectMapper.writeValueAsString(event));
            entity.setPublished(false);
            outboxRepository.save(entity);
            log.debug("Outbox-a yazıldı: {} ({})", event.eventName(), event.eventId());
        } catch (Exception ex) {
            log.error("Outbox yazılışı xətası: {}", ex.getMessage(), ex);
            throw new RuntimeException(ex);
        }
    }
}
