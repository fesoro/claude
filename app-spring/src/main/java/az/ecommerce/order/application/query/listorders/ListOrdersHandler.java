package az.ecommerce.order.application.query.listorders;

import az.ecommerce.order.application.dto.OrderDto;
import az.ecommerce.order.domain.repository.OrderRepository;
import az.ecommerce.shared.application.bus.QueryHandler;
import org.springframework.stereotype.Service;

import java.util.List;

@Service
public class ListOrdersHandler implements QueryHandler<ListOrdersQuery, List<OrderDto>> {
    private final OrderRepository repository;
    public ListOrdersHandler(OrderRepository r) { this.repository = r; }
    @Override public List<OrderDto> handle(ListOrdersQuery q) {
        return repository.findByUserId(q.userId()).stream().map(OrderDto::fromDomain).toList();
    }
}
