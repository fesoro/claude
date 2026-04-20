package az.ecommerce.notification.application.listener;

import az.ecommerce.notification.infrastructure.channel.EmailChannel;
import az.ecommerce.order.domain.event.OrderCreatedIntegrationEvent;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.amqp.rabbit.annotation.RabbitListener;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

import java.util.Map;

/**
 * Laravel: src/Notification/Application/Listeners/OrderCreatedListener.php
 *   - Hə @Async, queue: 'emails'
 *
 * Spring: 2 yolla işləyə bilər:
 *   1. @EventListener — eyni JVM (sinxron və ya @Async ilə asinxron)
 *   2. @RabbitListener — başqa serviceden RabbitMQ vasitəsilə
 *
 * Burada hər ikisini göstəririk (real layihədə birini seçəcəksiniz).
 */
@Component
public class OrderCreatedListener {

    private static final Logger log = LoggerFactory.getLogger(OrderCreatedListener.class);
    private final EmailChannel emailChannel;

    public OrderCreatedListener(EmailChannel emailChannel) {
        this.emailChannel = emailChannel;
    }

    @EventListener
    @Async
    public void onLocal(OrderCreatedIntegrationEvent event) {
        log.info("Sifariş yaradıldı (lokal): {}", event.orderId());
        // Real layihədə user-i query edib email-i alırıq
        emailChannel.send("customer@example.com", "Sifariş təsdiqi", "order-confirmation",
                Map.of("orderId", event.orderId(), "amount", event.totalAmount(), "currency", event.currency()));
    }

    @RabbitListener(queues = "notifications.order.created")
    public void onRabbit(OrderCreatedIntegrationEvent event) {
        log.info("Sifariş yaradıldı (RabbitMQ): {}", event.orderId());
        // Eyni iş — başqa servicedən gəldikdə
    }
}
