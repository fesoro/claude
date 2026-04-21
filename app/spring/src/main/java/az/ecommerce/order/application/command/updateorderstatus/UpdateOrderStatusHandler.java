package az.ecommerce.order.application.command.updateorderstatus;

import az.ecommerce.order.domain.Order;
import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.order.domain.repository.OrderRepository;
import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.shared.application.bus.CommandHandler;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import az.ecommerce.shared.infrastructure.bus.EventDispatcher;
import org.springframework.stereotype.Service;

@Service
public class UpdateOrderStatusHandler implements CommandHandler<UpdateOrderStatusCommand, Void> {

    private final OrderRepository repository;
    private final EventDispatcher eventDispatcher;

    public UpdateOrderStatusHandler(OrderRepository repository, EventDispatcher eventDispatcher) {
        this.repository = repository;
        this.eventDispatcher = eventDispatcher;
    }

    @Override
    public Void handle(UpdateOrderStatusCommand cmd) {
        Order order = repository.findById(new OrderId(cmd.orderId()))
                .orElseThrow(() -> new EntityNotFoundException("Order", cmd.orderId().toString()));

        switch (cmd.target()) {
            case CONFIRMED -> order.confirm();
            case PAID -> order.markAsPaid();
            case SHIPPED -> order.ship();
            case DELIVERED -> order.deliver();
            case CANCELLED -> order.cancel("Status keçidi ilə ləğv edildi");
            default -> throw new IllegalArgumentException("Bu status tetiklənə bilməz: " + cmd.target());
        }

        repository.save(order);
        eventDispatcher.dispatchAll(order);
        return null;
    }
}
