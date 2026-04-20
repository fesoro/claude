package az.ecommerce.order.application.command.cancelorder;

import az.ecommerce.order.domain.Order;
import az.ecommerce.order.domain.repository.OrderRepository;
import az.ecommerce.order.domain.specification.OrderCanBeCancelledSpec;
import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.shared.application.bus.CommandHandler;
import az.ecommerce.shared.domain.exception.DomainException;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import az.ecommerce.shared.infrastructure.bus.EventDispatcher;
import org.springframework.stereotype.Service;

@Service
public class CancelOrderHandler implements CommandHandler<CancelOrderCommand, Void> {

    private final OrderRepository repository;
    private final EventDispatcher eventDispatcher;

    public CancelOrderHandler(OrderRepository repository, EventDispatcher eventDispatcher) {
        this.repository = repository;
        this.eventDispatcher = eventDispatcher;
    }

    @Override
    public Void handle(CancelOrderCommand cmd) {
        Order order = repository.findById(new OrderId(cmd.orderId()))
                .orElseThrow(() -> new EntityNotFoundException("Order", cmd.orderId().toString()));

        if (!new OrderCanBeCancelledSpec().isSatisfiedBy(order)) {
            throw new DomainException("Bu sifariş hazırkı statusda ləğv edilə bilməz: " + order.status());
        }

        order.cancel(cmd.reason() != null ? cmd.reason() : "İstifadəçi tərəfindən");
        repository.save(order);
        eventDispatcher.dispatchAll(order);
        return null;
    }
}
