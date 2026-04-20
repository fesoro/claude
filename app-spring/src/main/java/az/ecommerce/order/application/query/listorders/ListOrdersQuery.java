package az.ecommerce.order.application.query.listorders;

import az.ecommerce.order.application.dto.OrderDto;
import az.ecommerce.shared.application.bus.Query;

import java.util.List;
import java.util.UUID;

public record ListOrdersQuery(UUID userId) implements Query<List<OrderDto>> {}
