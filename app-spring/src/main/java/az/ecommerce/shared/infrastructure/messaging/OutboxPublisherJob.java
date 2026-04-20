package az.ecommerce.shared.infrastructure.messaging;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.amqp.rabbit.core.RabbitTemplate;
import org.springframework.data.domain.PageRequest;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;
import org.springframework.transaction.annotation.Transactional;

import java.time.Instant;
import java.util.List;

/**
 * Laravel: PublishOutboxMessagesJob (queue: outbox, ShouldBeUnique)
 * Spring: @Scheduled hər dəqiqə avtomatik işləyir.
 *
 * application.yml-də: app.outbox.publish-interval = PT1M
 */
@Component
public class OutboxPublisherJob {

    private static final Logger log = LoggerFactory.getLogger(OutboxPublisherJob.class);
    private static final int BATCH_SIZE = 100;

    private final OutboxRepository repository;
    private final RabbitTemplate rabbitTemplate;
    private final ObjectMapper objectMapper;

    public OutboxPublisherJob(OutboxRepository repository, RabbitTemplate rabbitTemplate, ObjectMapper objectMapper) {
        this.repository = repository;
        this.rabbitTemplate = rabbitTemplate;
        this.objectMapper = objectMapper;
    }

    /**
     * Hər dəqiqə işləyir. fixedDelayString application.yml-dən gəlir.
     * Laravel: $schedule->job(...)->everyMinute()->withoutOverlapping()
     */
    @Scheduled(fixedDelayString = "${app.outbox.publish-interval:PT1M}")
    @Transactional(transactionManager = "orderTransactionManager")
    public void publish() {
        List<OutboxMessageEntity> pending = repository
                .findByPublishedFalseOrderByCreatedAtAsc(PageRequest.of(0, BATCH_SIZE));

        if (pending.isEmpty()) return;

        log.info("Outbox: {} mesaj yayımlanır", pending.size());
        for (OutboxMessageEntity msg : pending) {
            try {
                rabbitTemplate.convertAndSend(RabbitMQPublisher.EXCHANGE, msg.getRoutingKey(),
                        msg.getPayload(), m -> {
                            m.getMessageProperties().setMessageId(msg.getMessageId().toString());
                            m.getMessageProperties().setHeader("event-type", msg.getEventType());
                            return m;
                        });
                msg.setPublished(true);
                msg.setPublishedAt(Instant.now());
            } catch (Exception ex) {
                msg.incrementRetry();
                msg.setLastError(ex.getMessage());
                log.warn("Outbox mesajı yayımlana bilmədi {}: {}", msg.getMessageId(), ex.getMessage());
            }
        }
        repository.saveAll(pending);
    }
}
