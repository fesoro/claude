package az.ecommerce.shared.infrastructure.messaging;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.amqp.core.Message;
import org.springframework.amqp.rabbit.annotation.RabbitListener;
import org.springframework.stereotype.Component;

import java.nio.charset.StandardCharsets;
import java.util.UUID;

/**
 * Laravel: DeadLetterQueue → mesajları işləyən consumer.
 * Spring: dead_letter_queue-dan oxuyub dead_letter_messages cədvəlinə yazır.
 */
@Component
public class DeadLetterListener {

    private static final Logger log = LoggerFactory.getLogger(DeadLetterListener.class);
    private final DeadLetterRepository repository;

    public DeadLetterListener(DeadLetterRepository repository) {
        this.repository = repository;
    }

    @RabbitListener(queues = "dead_letter_queue")
    public void on(Message message) {
        DeadLetterMessageEntity entity = new DeadLetterMessageEntity();
        try {
            entity.setOriginalMessageId(UUID.fromString(message.getMessageProperties().getMessageId()));
        } catch (Exception ignore) {}
        entity.setQueueName((String) message.getMessageProperties().getHeaders()
                .getOrDefault("x-first-death-queue", "unknown"));
        entity.setEventType((String) message.getMessageProperties().getHeaders().getOrDefault("event-type", "unknown"));
        entity.setPayload(new String(message.getBody(), StandardCharsets.UTF_8));
        entity.setErrorMessage("Max retry attempts exceeded");
        repository.save(entity);
        log.warn("Mesaj DLQ-yə düşdü: {}", entity.getEventType());
    }
}
