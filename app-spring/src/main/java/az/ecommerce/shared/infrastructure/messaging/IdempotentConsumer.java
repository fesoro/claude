package az.ecommerce.shared.infrastructure.messaging;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.UUID;
import java.util.function.Consumer;

/**
 * Laravel: src/Shared/Infrastructure/Messaging/IdempotentConsumer.php
 *
 * Spring: RabbitMQ at-least-once delivery → eyni message 2 dəfə gələ bilər.
 * IdempotentConsumer message_id-ni inbox_messages-də yoxlayır:
 *   - əgər var → ignore
 *   - əgər yox → handler işləyir, message_id INSERT olunur (eyni transaction)
 *
 * NÜMUNƏ:
 *   @RabbitListener(queues = "...")
 *   public void on(Message msg) {
 *       UUID id = UUID.fromString(msg.getMessageProperties().getMessageId());
 *       idempotentConsumer.processOnce(id, "OrderCreated", msg.getBody(), payload -> {
 *           // real iş
 *       });
 *   }
 */
@Service
public class IdempotentConsumer {

    private static final Logger log = LoggerFactory.getLogger(IdempotentConsumer.class);

    private final InboxRepository inboxRepository;

    public IdempotentConsumer(InboxRepository inboxRepository) {
        this.inboxRepository = inboxRepository;
    }

    @Transactional(transactionManager = "orderTransactionManager")
    public void processOnce(UUID messageId, String eventType, String payload, Consumer<String> handler) {
        if (inboxRepository.existsByMessageId(messageId)) {
            log.debug("Duplicate message ignore edildi: {}", messageId);
            return;
        }

        InboxMessageEntity entity = new InboxMessageEntity();
        entity.setMessageId(messageId);
        entity.setEventType(eventType);
        entity.setPayload(payload);
        inboxRepository.save(entity);

        handler.accept(payload);
        entity.markProcessed();
    }
}
