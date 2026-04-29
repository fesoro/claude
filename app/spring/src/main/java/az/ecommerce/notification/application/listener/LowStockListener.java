package az.ecommerce.notification.application.listener;

import az.ecommerce.notification.infrastructure.channel.EmailChannel;
import az.ecommerce.product.domain.event.LowStockIntegrationEvent;
import org.springframework.amqp.rabbit.annotation.RabbitListener;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

import java.util.Map;

@Component
public class LowStockListener {

    private final EmailChannel emailChannel;

    @Value("${app.notification.admin-email:admin@ecommerce.az}")
    private String adminEmail;

    public LowStockListener(EmailChannel emailChannel) {
        this.emailChannel = emailChannel;
    }

    @EventListener
    @Async
    public void onLocal(LowStockIntegrationEvent event) {
        send(event);
    }

    @RabbitListener(queues = "notifications.product.stock.low")
    public void onRabbit(LowStockIntegrationEvent event) {
        send(event);
    }

    private void send(LowStockIntegrationEvent event) {
        emailChannel.send(adminEmail, "Az qalıq xəbərdarlığı", "low-stock-alert",
                Map.of("productName", event.productName(), "currentStock", event.currentStock()));
    }
}
