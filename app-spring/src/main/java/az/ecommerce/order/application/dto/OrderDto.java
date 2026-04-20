package az.ecommerce.order.application.dto;

import az.ecommerce.order.domain.Order;

import java.util.List;
import java.util.UUID;

public record OrderDto(
        UUID id, UUID userId, String status, String statusLabel,
        long totalAmount, String currency,
        List<OrderItemDto> items, AddressDto address
) {
    public static OrderDto fromDomain(Order o) {
        List<OrderItemDto> items = o.items().stream().map(i -> new OrderItemDto(
                i.productId(), i.productName(), i.unitPrice().amount(),
                i.unitPrice().currency().name(), i.quantity())).toList();
        AddressDto addr = new AddressDto(o.address().street(), o.address().city(),
                o.address().zip(), o.address().country());
        return new OrderDto(o.id().value(), o.userId(), o.status().name(), o.status().label(),
                o.totalAmount().amount(), o.totalAmount().currency().name(), items, addr);
    }
}
