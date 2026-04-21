package az.ecommerce.config;

import org.springframework.amqp.core.*;
import org.springframework.amqp.support.converter.Jackson2JsonMessageConverter;
import org.springframework.amqp.support.converter.MessageConverter;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

/**
 * Laravel: config/rabbitmq.php — exchange "domain_events", topic type.
 *
 * Bütün integration event-lər bu exchange-ə publish olunur.
 * Routing pattern: <context>.<entity>.<action>
 *   order.created, payment.completed, product.stock.low, user.registered
 */
@Configuration
public class RabbitMQConfig {

    public static final String DOMAIN_EXCHANGE = "domain_events";
    public static final String DLX_EXCHANGE = "domain_events.dlx";

    @Bean
    public TopicExchange domainEventsExchange() {
        return ExchangeBuilder.topicExchange(DOMAIN_EXCHANGE).durable(true).build();
    }

    @Bean
    public TopicExchange dlxExchange() {
        return ExchangeBuilder.topicExchange(DLX_EXCHANGE).durable(true).build();
    }

    // === ORDER queues ===
    @Bean
    public Queue orderCreatedQueue() {
        return QueueBuilder.durable("notifications.order.created")
                .deadLetterExchange(DLX_EXCHANGE).build();
    }
    @Bean
    public Binding bindOrderCreated(@org.springframework.beans.factory.annotation.Qualifier("orderCreatedQueue") Queue q,
                                     TopicExchange domainEventsExchange) {
        return BindingBuilder.bind(q).to(domainEventsExchange).with("order.created");
    }

    // === PAYMENT queues ===
    @Bean
    public Queue paymentCompletedQueue() {
        return QueueBuilder.durable("notifications.payment.completed").deadLetterExchange(DLX_EXCHANGE).build();
    }
    @Bean
    public Binding bindPaymentCompleted(@org.springframework.beans.factory.annotation.Qualifier("paymentCompletedQueue") Queue q,
                                         TopicExchange domainEventsExchange) {
        return BindingBuilder.bind(q).to(domainEventsExchange).with("payment.completed");
    }

    @Bean
    public Queue paymentFailedQueue() {
        return QueueBuilder.durable("notifications.payment.failed").deadLetterExchange(DLX_EXCHANGE).build();
    }
    @Bean
    public Binding bindPaymentFailed(@org.springframework.beans.factory.annotation.Qualifier("paymentFailedQueue") Queue q,
                                      TopicExchange domainEventsExchange) {
        return BindingBuilder.bind(q).to(domainEventsExchange).with("payment.failed");
    }

    // === PRODUCT queues ===
    @Bean
    public Queue lowStockQueue() {
        return QueueBuilder.durable("notifications.product.stock.low").deadLetterExchange(DLX_EXCHANGE).build();
    }
    @Bean
    public Binding bindLowStock(@org.springframework.beans.factory.annotation.Qualifier("lowStockQueue") Queue q,
                                 TopicExchange domainEventsExchange) {
        return BindingBuilder.bind(q).to(domainEventsExchange).with("product.stock.low");
    }

    // === DLQ ===
    @Bean
    public Queue deadLetterQueue() {
        return QueueBuilder.durable("dead_letter_queue").build();
    }
    @Bean
    public Binding bindDLQ(@org.springframework.beans.factory.annotation.Qualifier("deadLetterQueue") Queue dlq,
                            TopicExchange dlxExchange) {
        return BindingBuilder.bind(dlq).to(dlxExchange).with("#");
    }

    @Bean
    public MessageConverter jsonMessageConverter() {
        return new Jackson2JsonMessageConverter();
    }
}
