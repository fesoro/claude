package az.ecommerce.unit.user;

import az.ecommerce.shared.domain.exception.DomainException;
import az.ecommerce.user.domain.valueobject.Email;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.*;

class EmailValueObjectTest {

    @Test
    void shouldAcceptValidEmail() {
        Email email = new Email("user@example.com");
        assertEquals("user@example.com", email.value());
        assertEquals("example.com", email.domain());
    }

    @Test
    void shouldRejectBlankEmail() {
        assertThrows(DomainException.class, () -> new Email(""));
    }

    @Test
    void shouldRejectInvalidFormat() {
        assertThrows(DomainException.class, () -> new Email("not-an-email"));
        assertThrows(DomainException.class, () -> new Email("@example.com"));
        assertThrows(DomainException.class, () -> new Email("user@"));
    }
}
