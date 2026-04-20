package az.ecommerce.order.domain.specification;

import az.ecommerce.order.domain.Order;
import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.shared.domain.Specification;

/**
 * Laravel: src/Order/Domain/Specifications/OrderCanBeCancelledSpec.php
 * Yalnız PENDING və CONFIRMED status-larında ləğv edilə bilər.
 * (PAID artıq → refund prosesi lazımdır, SHIPPED isə return prosesi)
 */
public class OrderCanBeCancelledSpec implements Specification<Order> {
    @Override
    public boolean isSatisfiedBy(Order order) {
        return order.status() == OrderStatusEnum.PENDING
            || order.status() == OrderStatusEnum.CONFIRMED;
    }
}
