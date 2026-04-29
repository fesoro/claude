package az.ecommerce.payment.application.command.processpayment;

import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.shared.application.bus.Command;
import az.ecommerce.shared.infrastructure.validation.ValidCurrency;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Positive;

import java.util.UUID;

public record ProcessPaymentCommand(
        @NotNull UUID orderId,
        @NotNull UUID userId,
        @Positive long amount,
        @NotNull @ValidCurrency String currency,
        @NotNull PaymentMethodEnum method
) implements Command<UUID> {}
