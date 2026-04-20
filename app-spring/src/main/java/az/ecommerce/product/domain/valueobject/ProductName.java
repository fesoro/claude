package az.ecommerce.product.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;
import az.ecommerce.shared.domain.exception.DomainException;

public record ProductName(String value) implements ValueObject {
    public ProductName {
        if (value == null || value.isBlank()) {
            throw new DomainException("Məhsul adı boş ola bilməz");
        }
        if (value.length() > 255) {
            throw new DomainException("Məhsul adı 255 simvoldan uzun ola bilməz");
        }
    }
    @Override public String toString() { return value; }
}
