package az.ecommerce.unit.order;

import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.shared.domain.exception.DomainException;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Laravel: tests/Unit/Order/OrderStatusTest.php — state machine
 */
class OrderStatusTest {

    @Test
    void pendingCanGoToConfirmed() {
        assertTrue(OrderStatusEnum.PENDING.canTransitionTo(OrderStatusEnum.CONFIRMED));
    }

    @Test
    void pendingCannotGoToShipped() {
        assertFalse(OrderStatusEnum.PENDING.canTransitionTo(OrderStatusEnum.SHIPPED));
    }

    @Test
    void deliveredIsFinalState() {
        assertTrue(OrderStatusEnum.DELIVERED.isFinal());
        assertFalse(OrderStatusEnum.DELIVERED.canTransitionTo(OrderStatusEnum.CANCELLED));
    }

    @Test
    void requireTransitionThrowsOnInvalid() {
        assertThrows(DomainException.class,
                () -> OrderStatusEnum.PENDING.requireTransitionTo(OrderStatusEnum.DELIVERED));
    }

    @Test
    void shouldHaveAzerbaijaniLabel() {
        assertEquals("Çatdırılıb", OrderStatusEnum.DELIVERED.label());
    }
}
