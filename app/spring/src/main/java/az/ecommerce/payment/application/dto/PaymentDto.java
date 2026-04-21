package az.ecommerce.payment.application.dto;

import az.ecommerce.payment.domain.Payment;

import java.util.UUID;

public record PaymentDto(
        UUID id, UUID orderId, UUID userId,
        long amount, String currency,
        String method, String status,
        String transactionId, String failureReason
) {
    public static PaymentDto fromDomain(Payment p) {
        return new PaymentDto(
                p.id().value(), p.orderId(), p.userId(),
                p.amount().amount(), p.amount().currency().name(),
                p.method().name(), p.status().name(),
                p.transactionId(), p.failureReason());
    }
}
