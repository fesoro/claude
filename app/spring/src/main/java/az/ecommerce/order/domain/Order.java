package az.ecommerce.order.domain;

import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.order.domain.event.*;
import az.ecommerce.order.domain.valueobject.Address;
import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.order.domain.valueobject.OrderItem;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import az.ecommerce.shared.domain.AggregateRoot;
import az.ecommerce.shared.domain.exception.DomainException;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.UUID;

/**
 * === ORDER AGGREGATE ROOT ===
 *
 * Laravel: src/Order/Domain/Entities/Order.php
 *
 * Bu, layihənin ən kompleks aggregate-idir:
 * - State machine (OrderStatusEnum)
 * - Domain events
 * - Integration events
 * - Embedded VO-lar (Address, Money)
 * - Entity collection (OrderItem-lər)
 */
public class Order extends AggregateRoot {

    private final OrderId id;
    private final UUID userId;
    private final List<OrderItem> items;
    private OrderStatusEnum status;
    private Money totalAmount;
    private final Address address;

    private Order(OrderId id, UUID userId, List<OrderItem> items, Address address, Currency currency) {
        if (items.isEmpty()) {
            throw new DomainException("Sifariş ən azı 1 məhsuldan ibarət olmalıdır");
        }
        this.id = id;
        this.userId = userId;
        this.items = new ArrayList<>(items);
        this.address = address;
        this.status = OrderStatusEnum.PENDING;
        this.totalAmount = calculateTotal(items, currency);
    }

    public static Order create(UUID userId, List<OrderItem> items, Address address, Currency currency) {
        OrderId id = OrderId.generate();
        Order order = new Order(id, userId, items, address, currency);
        order.recordEvent(OrderCreatedEvent.of(id, userId, order.totalAmount.amount(), currency, items.size()));
        order.recordEvent(new OrderCreatedIntegrationEvent(
                UUID.randomUUID(), java.time.Instant.now(),
                id.value(), userId, order.totalAmount.amount(), currency.name()));
        return order;
    }

    public static Order reconstitute(OrderId id, UUID userId, List<OrderItem> items,
                                     Address address, OrderStatusEnum status, Money totalAmount) {
        Order order = new Order(id, userId, items, address, totalAmount.currency());
        order.status = status;
        order.totalAmount = totalAmount;
        return order;
    }

    public void confirm() {
        status.requireTransitionTo(OrderStatusEnum.CONFIRMED);
        this.status = OrderStatusEnum.CONFIRMED;
        recordEvent(OrderConfirmedEvent.of(id));
    }

    public void markAsPaid() {
        status.requireTransitionTo(OrderStatusEnum.PAID);
        this.status = OrderStatusEnum.PAID;
        recordEvent(OrderPaidEvent.of(id));
    }

    public void ship() {
        status.requireTransitionTo(OrderStatusEnum.SHIPPED);
        this.status = OrderStatusEnum.SHIPPED;
        recordEvent(OrderShippedEvent.of(id));
    }

    public void deliver() {
        status.requireTransitionTo(OrderStatusEnum.DELIVERED);
        this.status = OrderStatusEnum.DELIVERED;
        recordEvent(OrderDeliveredEvent.of(id));
    }

    public void cancel(String reason) {
        if (status == OrderStatusEnum.DELIVERED) {
            throw new DomainException("Çatdırılmış sifarişi ləğv etmək olmaz");
        }
        if (status == OrderStatusEnum.CANCELLED) {
            throw new DomainException("Sifariş artıq ləğv edilib");
        }
        this.status = OrderStatusEnum.CANCELLED;
        recordEvent(OrderCancelledEvent.of(id, reason));
    }

    private Money calculateTotal(List<OrderItem> items, Currency currency) {
        Money total = Money.zero(currency);
        for (OrderItem item : items) {
            total = total.add(item.lineTotal());
        }
        return total;
    }

    public OrderId id() { return id; }
    public UUID userId() { return userId; }
    public List<OrderItem> items() { return Collections.unmodifiableList(items); }
    public OrderStatusEnum status() { return status; }
    public Money totalAmount() { return totalAmount; }
    public Address address() { return address; }
}
