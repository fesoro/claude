package az.ecommerce.order.infrastructure.readmodel;

import az.ecommerce.order.domain.event.OrderCancelledEvent;
import az.ecommerce.order.domain.event.OrderCreatedEvent;
import az.ecommerce.order.domain.event.OrderPaidEvent;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

import java.time.Instant;

/**
 * Laravel: OrderListProjector → CQRS read model-i yeniləyir.
 * Spring: @EventListener — @Async ilə asinxron işləyir.
 *
 * Real Axon-based versiyada @EventHandler istifadə olunur.
 * Daxili biznes domeni üçün domain event tutub denormalize edir.
 */
@Component
public class OrderListProjector {

    private final OrderListRepository repository;

    public OrderListProjector(OrderListRepository repository) {
        this.repository = repository;
    }

    @EventListener
    @Async
    public void on(OrderCreatedEvent event) {
        OrderReadModelEntity entity = new OrderReadModelEntity();
        entity.setOrderId(event.orderId().value());
        entity.setUserId(event.userId());
        entity.setStatus("PENDING");
        entity.setTotalAmount(event.totalAmount());
        entity.setTotalCurrency(event.currency());
        entity.setItemCount(event.itemCount());
        entity.setLastUpdatedAt(Instant.now());
        repository.save(entity);
    }

    @EventListener
    @Async
    public void on(OrderPaidEvent event) {
        repository.findById(event.orderId().value()).ifPresent(e -> {
            e.setStatus("PAID");
            e.setLastUpdatedAt(Instant.now());
            repository.save(e);
        });
    }

    @EventListener
    @Async
    public void on(OrderCancelledEvent event) {
        repository.findById(event.orderId().value()).ifPresent(e -> {
            e.setStatus("CANCELLED");
            e.setLastUpdatedAt(Instant.now());
            repository.save(e);
        });
    }
}
