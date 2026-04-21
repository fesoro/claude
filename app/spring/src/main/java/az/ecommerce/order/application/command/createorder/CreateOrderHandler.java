package az.ecommerce.order.application.command.createorder;

import az.ecommerce.order.domain.Order;
import az.ecommerce.order.domain.repository.OrderRepository;
import az.ecommerce.order.domain.valueobject.Address;
import az.ecommerce.order.domain.valueobject.OrderItem;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import az.ecommerce.shared.application.bus.CommandHandler;
import az.ecommerce.shared.infrastructure.bus.EventDispatcher;
import org.springframework.stereotype.Service;

import java.util.List;
import java.util.UUID;

@Service
public class CreateOrderHandler implements CommandHandler<CreateOrderCommand, UUID> {

    private final OrderRepository repository;
    private final EventDispatcher eventDispatcher;

    public CreateOrderHandler(OrderRepository repository, EventDispatcher eventDispatcher) {
        this.repository = repository;
        this.eventDispatcher = eventDispatcher;
    }

    @Override
    public UUID handle(CreateOrderCommand cmd) {
        Currency currency = Currency.of(cmd.currency());
        List<OrderItem> items = cmd.items().stream().map(i -> new OrderItem(
                i.productId(), i.productName(),
                Money.of(i.unitPriceAmount(), Currency.of(i.currency())),
                i.quantity())).toList();

        Address address = new Address(cmd.address().street(), cmd.address().city(),
                cmd.address().zip(), cmd.address().country());

        Order order = Order.create(cmd.userId(), items, address, currency);
        repository.save(order);
        eventDispatcher.dispatchAll(order);
        return order.id().value();
    }
}
