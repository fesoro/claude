package az.ecommerce.payment.application.query.getpayment;

import az.ecommerce.payment.application.dto.PaymentDto;
import az.ecommerce.shared.application.bus.Query;

import java.util.UUID;

public record GetPaymentQuery(UUID paymentId) implements Query<PaymentDto> {}
