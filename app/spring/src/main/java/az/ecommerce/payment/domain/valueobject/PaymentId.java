package az.ecommerce.payment.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;
import java.util.UUID;

public record PaymentId(UUID value) implements ValueObject {
    public PaymentId { if (value == null) throw new IllegalArgumentException("PaymentId null"); }
    public static PaymentId generate() { return new PaymentId(UUID.randomUUID()); }
    @Override public String toString() { return value.toString(); }
}
