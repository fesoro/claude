package az.ecommerce.payment.infrastructure.persistence;

import az.ecommerce.payment.domain.Payment;
import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.enums.PaymentStatusEnum;
import az.ecommerce.payment.domain.repository.PaymentRepository;
import az.ecommerce.payment.domain.valueobject.PaymentId;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import org.springframework.stereotype.Repository;

import java.util.Optional;

@Repository
public class PaymentRepositoryImpl implements PaymentRepository {

    private final JpaPaymentRepository jpa;

    public PaymentRepositoryImpl(JpaPaymentRepository jpa) { this.jpa = jpa; }

    @Override
    public Payment save(Payment p) {
        PaymentEntity e = jpa.findById(p.id().value()).orElseGet(PaymentEntity::new);
        e.setId(p.id().value());
        e.setOrderId(p.orderId());
        e.setUserId(p.userId());
        e.setAmount(p.amount().amount());
        e.setCurrency(p.amount().currency().name());
        e.setPaymentMethod(p.method().name());
        e.setStatus(p.status().name());
        e.setTransactionId(p.transactionId());
        e.setFailureReason(p.failureReason());
        jpa.save(e);
        return p;
    }

    @Override
    public Optional<Payment> findById(PaymentId id) {
        return jpa.findById(id.value()).map(this::toDomain);
    }

    private Payment toDomain(PaymentEntity e) {
        return Payment.reconstitute(
                new PaymentId(e.getId()),
                e.getOrderId(), e.getUserId(),
                Money.of(e.getAmount(), Currency.of(e.getCurrency())),
                PaymentMethodEnum.valueOf(e.getPaymentMethod()),
                PaymentStatusEnum.valueOf(e.getStatus()),
                e.getTransactionId(), e.getFailureReason());
    }
}
