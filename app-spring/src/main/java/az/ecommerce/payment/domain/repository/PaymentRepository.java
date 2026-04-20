package az.ecommerce.payment.domain.repository;

import az.ecommerce.payment.domain.Payment;
import az.ecommerce.payment.domain.valueobject.PaymentId;

import java.util.Optional;

public interface PaymentRepository {
    Payment save(Payment payment);
    Optional<Payment> findById(PaymentId id);
}
