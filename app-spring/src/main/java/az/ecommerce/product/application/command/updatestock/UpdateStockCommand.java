package az.ecommerce.product.application.command.updatestock;

import az.ecommerce.shared.application.bus.Command;
import jakarta.validation.constraints.*;

import java.util.UUID;

public record UpdateStockCommand(
        @NotNull UUID productId,
        @Positive int amount,
        @NotBlank @Pattern(regexp = "increase|decrease") String type
) implements Command<Void> {}
