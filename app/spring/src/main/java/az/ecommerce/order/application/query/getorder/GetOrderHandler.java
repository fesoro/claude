package az.ecommerce.order.application.query.getorder;

import az.ecommerce.order.application.dto.OrderDto;
import az.ecommerce.order.domain.repository.OrderRepository;
import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.shared.application.bus.QueryHandler;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import org.springframework.stereotype.Service;

@Service
public class GetOrderHandler implements QueryHandler<GetOrderQuery, OrderDto> {
    private final OrderRepository repository;
    public GetOrderHandler(OrderRepository r) { this.repository = r; }
    @Override public OrderDto handle(GetOrderQuery q) {
        return repository.findById(new OrderId(q.orderId()))
                .map(OrderDto::fromDomain)
                .orElseThrow(() -> new EntityNotFoundException("Order", q.orderId().toString()));
    }
}
