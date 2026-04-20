package az.ecommerce.order.application.command.cancelorder;

import az.ecommerce.shared.application.bus.Command;
import jakarta.validation.constraints.NotNull;

import java.util.UUID;

public record CancelOrderCommand(@NotNull UUID orderId, String reason) implements Command<Void> {}
