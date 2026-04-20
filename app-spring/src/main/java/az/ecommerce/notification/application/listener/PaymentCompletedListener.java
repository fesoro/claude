package az.ecommerce.notification.application.listener;

import az.ecommerce.notification.infrastructure.channel.EmailChannel;
import az.ecommerce.payment.domain.event.PaymentCompletedIntegrationEvent;
import org.springframework.amqp.rabbit.annotation.RabbitListener;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

import java.util.Map;

@Component
public class PaymentCompletedListener {

    private final EmailChannel emailChannel;

    public PaymentCompletedListener(EmailChannel emailChannel) {
        this.emailChannel = emailChannel;
    }

    @EventListener
    @Async
    public void onLocal(PaymentCompletedIntegrationEvent event) {
        send(event);
    }

    @RabbitListener(queues = "notifications.payment.completed")
    public void onRabbit(PaymentCompletedIntegrationEvent event) {
        send(event);
    }

    private void send(PaymentCompletedIntegrationEvent event) {
        emailChannel.send("customer@example.com", "Ödəmə qəbzi", "payment-receipt",
                Map.of("orderId", event.orderId(), "paymentId", event.paymentId(),
                       "amount", event.amount()));
    }
}
