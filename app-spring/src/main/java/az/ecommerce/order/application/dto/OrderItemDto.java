package az.ecommerce.order.application.dto;

import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Positive;

import java.util.UUID;

public record OrderItemDto(
        @NotNull UUID productId,
        @NotNull String productName,
        @Positive long unitPriceAmount,
        @NotNull String currency,
        @Positive int quantity
) {}
