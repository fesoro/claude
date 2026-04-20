package az.ecommerce.product.application.command.createproduct;

import az.ecommerce.shared.application.bus.Command;
import jakarta.validation.constraints.*;

import java.util.UUID;

public record CreateProductCommand(
        @NotBlank @Size(max = 255) String name,
        @Size(max = 5000) String description,
        @PositiveOrZero long priceAmount,
        @NotBlank @Pattern(regexp = "USD|EUR|AZN") String currency,
        @PositiveOrZero int stockQuantity
) implements Command<UUID> {}
