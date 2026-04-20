package az.ecommerce.user.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;
import az.ecommerce.shared.domain.exception.DomainException;

import java.util.regex.Pattern;

/**
 * Laravel: src/User/Domain/ValueObjects/Email.php
 *   - constructor-da format validation
 *   - domain() helper
 *
 * Spring: Java record + compact constructor (validation).
 */
public record Email(String value) implements ValueObject {

    private static final Pattern PATTERN = Pattern.compile(
            "^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$");

    public Email {
        if (value == null || value.isBlank()) {
            throw new DomainException("Email boş ola bilməz");
        }
        if (!PATTERN.matcher(value).matches()) {
            throw new DomainException("Email düzgün formatda deyil: " + value);
        }
    }

    public String domain() {
        return value.substring(value.indexOf('@') + 1);
    }

    @Override
    public String toString() {
        return value;
    }
}
