package az.ecommerce.notification.application.listener;

import az.ecommerce.notification.infrastructure.channel.EmailChannel;
import az.ecommerce.payment.domain.event.PaymentFailedIntegrationEvent;
import org.springframework.amqp.rabbit.annotation.RabbitListener;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

import java.util.Map;

/**
 * Həm in-process (@EventListener), həm distributed (@RabbitListener) işləyir.
 * Modulith setup-da @EventListener, microservice ayrılsa @RabbitListener qalır.
 */
@Component
public class PaymentFailedListener {

    private final EmailChannel emailChannel;

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
        emailChannel.send("customer@example.com", "Ödəniş uğursuz oldu", "payment-failed",
                Map.of("orderId", event.orderId(), "reason", event.reason()));
    }
}
