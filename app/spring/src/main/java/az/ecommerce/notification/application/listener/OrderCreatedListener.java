package az.ecommerce.notification.application.listener;

import az.ecommerce.notification.infrastructure.channel.EmailChannel;
import az.ecommerce.order.domain.event.OrderCreatedIntegrationEvent;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.amqp.rabbit.annotation.RabbitListener;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

import java.util.Map;

/**
 * Laravel: src/Notification/Application/Listeners/OrderCreatedListener.php
 *
 * Spring: 2 yolla işləyə bilər:
 *   1. @EventListener — eyni JVM (sinxron və ya @Async ilə asinxron)
 *   2. @RabbitListener — başqa servicedən RabbitMQ vasitəsilə
 *
 * QEYD: Real layihədə userEmail-i event-dən alınmalıdır. Bunun üçün ya
 * OrderCreatedIntegrationEvent-ə userEmail sahəsi əlavə edin, ya da
 * User servisindən userId ilə sorğu göndərin (ACL pattern).
 */
@Component
public class OrderCreatedListener {

    private static final Logger log = LoggerFactory.getLogger(OrderCreatedListener.class);

    private final EmailChannel emailChannel;

    @Value("${app.notification.customer-fallback-email:customer@example.com}")
    private String customerFallbackEmail;

    public OrderCreatedListener(EmailChannel emailChannel) {
        this.emailChannel = emailChannel;
    }

    @EventListener
    @Async
    public void onLocal(OrderCreatedIntegrationEvent event) {
        log.info("Sifariş yaradıldı (lokal): {}", event.orderId());
        send(event);
    }

    @RabbitListener(queues = "notifications.order.created")
    public void onRabbit(OrderCreatedIntegrationEvent event) {
        log.info("Sifariş yaradıldı (RabbitMQ): {}", event.orderId());
        send(event);
    }

    private void send(OrderCreatedIntegrationEvent event) {
        emailChannel.send(customerFallbackEmail, "Sifariş təsdiqi", "order-confirmation",
                Map.of("orderId", event.orderId(), "amount", event.totalAmount(),
                       "currency", event.currency()));
    }
}
