package az.ecommerce.user.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;

import java.util.UUID;

/**
 * Laravel: src/User/Domain/ValueObjects/UserId.php
 * Spring: Java record — immutable, equals/hashCode auto.
 */
public record UserId(UUID value) implements ValueObject {

    public UserId {
        if (value == null) {
            throw new IllegalArgumentException("UserId null ola bilməz");
        }
    }

    public static UserId generate() {
        return new UserId(UUID.randomUUID());
    }

    public static UserId of(String uuid) {
        return new UserId(UUID.fromString(uuid));
    }

    @Override
    public String toString() {
        return value.toString();
    }
}
