package az.ecommerce.webhook;

import az.ecommerce.order.domain.event.OrderCreatedIntegrationEvent;
import az.ecommerce.payment.domain.event.PaymentCompletedIntegrationEvent;
import az.ecommerce.payment.domain.event.PaymentFailedIntegrationEvent;
import az.ecommerce.product.domain.event.LowStockIntegrationEvent;
import az.ecommerce.shared.infrastructure.webhook.WebhookService;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

/**
 * Laravel: app/Jobs/SendWebhookJob.php (queue: webhooks, tries: 3)
 *
 * Spring: integration event-ləri tutub WebhookService.deliver() çağırır.
 * @Async ilə paralel işlənir.
 */
@Component
public class SendWebhookJob {

    private static final Logger log = LoggerFactory.getLogger(SendWebhookJob.class);

    private final WebhookService webhookService;
    private final ObjectMapper objectMapper;

    public SendWebhookJob(WebhookService webhookService, ObjectMapper objectMapper) {
        this.webhookService = webhookService;
        this.objectMapper = objectMapper;
    }

    @EventListener
    @Async
    public void onOrderCreated(OrderCreatedIntegrationEvent event) {
        send("order.created", event);
    }

    @EventListener
    @Async
    public void onPaymentCompleted(PaymentCompletedIntegrationEvent event) {
        send("payment.completed", event);
    }

    @EventListener
    @Async
    public void onPaymentFailed(PaymentFailedIntegrationEvent event) {
        send("payment.failed", event);
    }

    @EventListener
    @Async
    public void onLowStock(LowStockIntegrationEvent event) {
        send("product.stock.low", event);
    }

    private void send(String eventType, Object payload) {
        try {
            String json = objectMapper.writeValueAsString(payload);
            webhookService.deliver(eventType, json);
        } catch (Exception ex) {
            log.error("Webhook serialization xətası: {}", ex.getMessage(), ex);
        }
    }
}
