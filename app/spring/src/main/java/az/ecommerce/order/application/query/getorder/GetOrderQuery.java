package az.ecommerce.order.application.query.getorder;

import az.ecommerce.order.application.dto.OrderDto;
import az.ecommerce.shared.application.bus.Query;

import java.util.UUID;

public record GetOrderQuery(UUID orderId) implements Query<OrderDto> {}
