package az.ecommerce.order.infrastructure.persistence;

import az.ecommerce.order.domain.Order;
import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.order.domain.repository.OrderRepository;
import az.ecommerce.order.domain.valueobject.Address;
import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.order.domain.valueobject.OrderItem;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class OrderRepositoryImpl implements OrderRepository {

    private final JpaOrderRepository jpa;

    public OrderRepositoryImpl(JpaOrderRepository jpa) { this.jpa = jpa; }

    @Override
    public Order save(Order order) {
        OrderEntity e = jpa.findById(order.id().value()).orElseGet(OrderEntity::new);
        e.setId(order.id().value());
        e.setUserId(order.userId());
        e.setStatus(order.status().name());
        e.setTotalAmount(order.totalAmount().amount());
        e.setTotalCurrency(order.totalAmount().currency().name());
        e.setAddressStreet(order.address().street());
        e.setAddressCity(order.address().city());
        e.setAddressZip(order.address().zip());
        e.setAddressCountry(order.address().country());

        e.getItems().clear();
        for (OrderItem item : order.items()) {
            OrderItemEntity ie = new OrderItemEntity();
            ie.setId(UUID.randomUUID());
            ie.setOrder(e);
            ie.setProductId(item.productId());
            ie.setProductName(item.productName());
            ie.setUnitPriceAmount(item.unitPrice().amount());
            ie.setUnitPriceCurrency(item.unitPrice().currency().name());
            ie.setQuantity(item.quantity());
            ie.setLineTotal(item.lineTotal().amount());
            e.getItems().add(ie);
        }

        jpa.save(e);
        return order;
    }

    @Override
    public Optional<Order> findById(OrderId id) {
        return jpa.findById(id.value()).map(this::toDomain);
    }

    @Override
    public List<Order> findByUserId(UUID userId) {
        return jpa.findByUserIdOrderByCreatedAtDesc(userId).stream().map(this::toDomain).toList();
    }

    private Order toDomain(OrderEntity e) {
        Currency currency = Currency.of(e.getTotalCurrency());
        List<OrderItem> items = e.getItems().stream().map(ie -> new OrderItem(
                ie.getProductId(), ie.getProductName(),
                Money.of(ie.getUnitPriceAmount(), Currency.of(ie.getUnitPriceCurrency())),
                ie.getQuantity())).toList();
        Address address = new Address(e.getAddressStreet(), e.getAddressCity(),
                e.getAddressZip(), e.getAddressCountry());
        return Order.reconstitute(
                new OrderId(e.getId()), e.getUserId(), items, address,
                OrderStatusEnum.valueOf(e.getStatus()),
                Money.of(e.getTotalAmount(), currency));
    }
}
