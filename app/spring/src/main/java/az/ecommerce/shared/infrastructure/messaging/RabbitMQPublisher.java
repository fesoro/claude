package az.ecommerce.shared.infrastructure.messaging;

import az.ecommerce.shared.domain.IntegrationEvent;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.amqp.core.MessageProperties;
import org.springframework.amqp.rabbit.core.RabbitTemplate;
import org.springframework.stereotype.Component;

/**
 * Laravel: src/Shared/Infrastructure/Messaging/RabbitMQPublisher.php
 *   - php-amqplib istifadə edir
 *
 * Spring: spring-boot-starter-amqp + RabbitTemplate.
 * Exchange: "domain_events" (topic)
 * Routing key: integrationEvent.routingKey() — məsələn "order.created"
 */
@Component
public class RabbitMQPublisher {

    private static final Logger log = LoggerFactory.getLogger(RabbitMQPublisher.class);
    public static final String EXCHANGE = "domain_events";

    private final RabbitTemplate rabbitTemplate;
    private final ObjectMapper objectMapper;

    public RabbitMQPublisher(RabbitTemplate rabbitTemplate, ObjectMapper objectMapper) {
        this.rabbitTemplate = rabbitTemplate;
        this.objectMapper = objectMapper;
    }

    public void publish(IntegrationEvent event) {
        try {
            String payload = objectMapper.writeValueAsString(event);
            rabbitTemplate.convertAndSend(EXCHANGE, event.routingKey(), payload, msg -> {
                msg.getMessageProperties().setMessageId(event.eventId().toString());
                msg.getMessageProperties().setHeader("event-type", event.eventName());
                msg.getMessageProperties().setHeader("event-version", event.eventVersion());
                msg.getMessageProperties().setContentType(MessageProperties.CONTENT_TYPE_JSON);
                return msg;
            });
            log.info("Published integration event: {} → {}", event.eventName(), event.routingKey());
        } catch (Exception ex) {
            log.error("Failed to publish event {}: {}", event.eventName(), ex.getMessage(), ex);
            throw new RuntimeException("RabbitMQ publish error", ex);
        }
    }
}
