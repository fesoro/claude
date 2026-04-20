package az.ecommerce.order.application.command.updateorderstatus;

import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.shared.application.bus.Command;
import jakarta.validation.constraints.NotNull;

import java.util.UUID;

public record UpdateOrderStatusCommand(@NotNull UUID orderId, @NotNull OrderStatusEnum target) implements Command<Void> {}
