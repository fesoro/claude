package az.ecommerce.payment.domain.enums;

import az.ecommerce.shared.domain.exception.DomainException;
import java.util.Map;
import java.util.Set;

public enum PaymentStatusEnum {
    PENDING, PROCESSING, COMPLETED, FAILED, REFUNDED;

    private static final Map<PaymentStatusEnum, Set<PaymentStatusEnum>> ALLOWED = Map.of(
            PENDING, Set.of(PROCESSING),
            PROCESSING, Set.of(COMPLETED, FAILED),
            COMPLETED, Set.of(REFUNDED),
            FAILED, Set.of(),
            REFUNDED, Set.of()
    );

    public void requireTransitionTo(PaymentStatusEnum target) {
        if (!ALLOWED.get(this).contains(target)) {
            throw new DomainException("Yanlış payment status keçidi: " + this + " → " + target);
        }
    }
}
