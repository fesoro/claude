package az.ecommerce.order.domain.repository;

import az.ecommerce.order.domain.Order;
import az.ecommerce.order.domain.valueobject.OrderId;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface OrderRepository {
    Order save(Order order);
    Optional<Order> findById(OrderId id);
    List<Order> findByUserId(UUID userId);
}
