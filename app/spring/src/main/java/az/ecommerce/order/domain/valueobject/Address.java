package az.ecommerce.order.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;
import az.ecommerce.shared.domain.exception.DomainException;

public record Address(String street, String city, String zip, String country) implements ValueObject {

    public Address {
        if (street == null || street.isBlank()) throw new DomainException("Küçə boş ola bilməz");
        if (city == null || city.isBlank()) throw new DomainException("Şəhər boş ola bilməz");
        if (zip == null || zip.isBlank()) throw new DomainException("Zip boş ola bilməz");
        if (country == null || country.isBlank()) throw new DomainException("Ölkə boş ola bilməz");
    }

    public String summary() {
        return String.format("%s, %s %s, %s", street, city, zip, country);
    }
}
