package az.ecommerce.notification.application.listener;

import az.ecommerce.notification.infrastructure.channel.EmailChannel;
import az.ecommerce.payment.domain.event.PaymentFailedIntegrationEvent;
import org.springframework.amqp.rabbit.annotation.RabbitListener;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

import java.util.Map;

/**
 * Həm in-process (@EventListener), həm distributed (@RabbitListener) işləyir.
 */
@Component
public class PaymentFailedListener {

    private final EmailChannel emailChannel;

    @Value("${app.notification.customer-fallback-email:customer@example.com}")
    private String customerFallbackEmail;

    public PaymentFailedListener(EmailChannel emailChannel) {
        this.emailChannel = emailChannel;
    }

    @EventListener
    @Async
    public void onLocal(PaymentFailedIntegrationEvent event) {
        send(event);
    }

    @RabbitListener(queues = "notifications.payment.failed")
    public void onRabbit(PaymentFailedIntegrationEvent event) {
        send(event);
    }

    private void send(PaymentFailedIntegrationEvent event) {
        emailChannel.send(customerFallbackEmail, "Ödəniş uğursuz oldu", "payment-failed",
                Map.of("orderId", event.orderId(), "reason", event.reason()));
    }
}
