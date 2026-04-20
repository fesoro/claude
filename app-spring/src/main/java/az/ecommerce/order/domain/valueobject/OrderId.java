package az.ecommerce.order.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;

import java.util.UUID;

public record OrderId(UUID value) implements ValueObject {
    public OrderId { if (value == null) throw new IllegalArgumentException("OrderId null"); }
    public static OrderId generate() { return new OrderId(UUID.randomUUID()); }
    public static OrderId of(String s) { return new OrderId(UUID.fromString(s)); }
    @Override public String toString() { return value.toString(); }
}
