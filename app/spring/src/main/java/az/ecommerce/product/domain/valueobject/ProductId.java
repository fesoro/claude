package az.ecommerce.product.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;

import java.util.UUID;

public record ProductId(UUID value) implements ValueObject {
    public ProductId {
        if (value == null) throw new IllegalArgumentException("ProductId null ola bilməz");
    }
    public static ProductId generate() { return new ProductId(UUID.randomUUID()); }
    public static ProductId of(String s) { return new ProductId(UUID.fromString(s)); }
    @Override public String toString() { return value.toString(); }
}
