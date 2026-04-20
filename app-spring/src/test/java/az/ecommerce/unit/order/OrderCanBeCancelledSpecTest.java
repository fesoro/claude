package az.ecommerce.unit.order;

import az.ecommerce.order.domain.Order;
import az.ecommerce.order.domain.specification.OrderCanBeCancelledSpec;
import az.ecommerce.order.domain.valueobject.Address;
import az.ecommerce.order.domain.valueobject.OrderItem;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import org.junit.jupiter.api.Test;

import java.util.List;
import java.util.UUID;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Laravel: tests/Unit/Order/OrderCanBeCancelledSpecTest.php
 */
class OrderCanBeCancelledSpecTest {

    private final OrderCanBeCancelledSpec spec = new OrderCanBeCancelledSpec();

    @Test
    void pendingOrderCanBeCancelled() {
        Order order = createOrder();
        assertTrue(spec.isSatisfiedBy(order));
    }

    @Test
    void confirmedOrderCanBeCancelled() {
        Order order = createOrder();
        order.confirm();
        assertTrue(spec.isSatisfiedBy(order));
    }

    @Test
    void paidOrderCannotBeCancelled() {
        Order order = createOrder();
        order.confirm();
        order.markAsPaid();
        assertFalse(spec.isSatisfiedBy(order));
    }

    @Test
    void shippedOrderCannotBeCancelled() {
        Order order = createOrder();
        order.confirm();
        order.markAsPaid();
        order.ship();
        assertFalse(spec.isSatisfiedBy(order));
    }

    private Order createOrder() {
        return Order.create(
                UUID.randomUUID(),
                List.of(new OrderItem(UUID.randomUUID(), "Test", Money.of(1000, Currency.AZN), 2)),
                new Address("st", "city", "1000", "AZ"),
                Currency.AZN);
    }
}
