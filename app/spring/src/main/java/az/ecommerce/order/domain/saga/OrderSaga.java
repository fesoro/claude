package az.ecommerce.order.domain.saga;

import az.ecommerce.order.application.command.cancelorder.CancelOrderCommand;
import az.ecommerce.order.application.command.updateorderstatus.UpdateOrderStatusCommand;
import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.order.domain.event.OrderCreatedIntegrationEvent;
import az.ecommerce.payment.application.command.processpayment.ProcessPaymentCommand;
import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.event.PaymentCompletedIntegrationEvent;
import az.ecommerce.payment.domain.event.PaymentFailedIntegrationEvent;
import az.ecommerce.shared.application.bus.CommandBus;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

/**
 * === SAGA PATTERN (Spring-native versiya) ===
 *
 * Laravel: src/Order/Domain/Sagas/OrderSaga.php
 *
 * Bu sadə Spring `@EventListener` əsaslı orchestration saga-dır.
 * Production üçün Axon Framework (@Saga) tövsiyə olunur — daha güclü:
 *   - Persistent saga state (process_manager_states cədvəli)
 *   - Saga association by ID
 *   - Re-entry on failure
 *   - Built-in compensating transactions
 *
 * Order Fulfillment flow:
 *   Step 1: OrderCreated     → ProcessPayment dispatch et
 *   Step 2: PaymentCompleted → Order PAID statusuna keçir
 *   Step 3: PaymentFailed    → Order ləğv et (compensation)
 */
@Component
public class OrderSaga {

    private static final Logger log = LoggerFactory.getLogger(OrderSaga.class);

    private final CommandBus commandBus;

    public OrderSaga(CommandBus commandBus) {
        this.commandBus = commandBus;
    }

    @EventListener
    @Async
    public void onOrderCreated(OrderCreatedIntegrationEvent event) {
        log.info("Saga step 1: order={} → ProcessPaymentCommand", event.orderId());
        try {
            commandBus.dispatch(new ProcessPaymentCommand(
                    event.orderId(), event.userId(),
                    event.totalAmount(), event.currency(),
                    PaymentMethodEnum.CREDIT_CARD));
        } catch (Exception ex) {
            log.error("Saga step 1 failed: {}", ex.getMessage(), ex);
        }
    }

    @EventListener
    @Async
    public void onPaymentCompleted(PaymentCompletedIntegrationEvent event) {
        log.info("Saga step 2: payment completed for order={} → mark order as PAID", event.orderId());
        try {
            commandBus.dispatch(new UpdateOrderStatusCommand(event.orderId(), OrderStatusEnum.PAID));
        } catch (Exception ex) {
            log.error("Saga step 2 failed: {}", ex.getMessage(), ex);
        }
    }

    @EventListener
    @Async
    public void onPaymentFailed(PaymentFailedIntegrationEvent event) {
        log.warn("Saga compensation: payment failed for order={} → cancel order. Reason: {}",
                event.orderId(), event.reason());
        try {
            commandBus.dispatch(new CancelOrderCommand(event.orderId(),
                    "Payment failed: " + event.reason()));
        } catch (Exception ex) {
            log.error("Saga compensation failed: {}", ex.getMessage(), ex);
        }
    }
}
