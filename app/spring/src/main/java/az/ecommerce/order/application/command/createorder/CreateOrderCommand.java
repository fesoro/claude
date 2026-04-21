package az.ecommerce.order.application.command.createorder;

import az.ecommerce.order.application.dto.AddressDto;
import az.ecommerce.order.application.dto.OrderItemDto;
import az.ecommerce.shared.application.bus.Command;
import jakarta.validation.Valid;
import jakarta.validation.constraints.NotEmpty;
import jakarta.validation.constraints.NotNull;

import java.util.List;
import java.util.UUID;

public record CreateOrderCommand(
        @NotNull UUID userId,
        @NotEmpty @Valid List<OrderItemDto> items,
        @NotNull @Valid AddressDto address,
        @NotNull String currency
) implements Command<UUID> {}
