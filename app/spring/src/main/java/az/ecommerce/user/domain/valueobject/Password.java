package az.ecommerce.user.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;
import az.ecommerce.shared.domain.exception.DomainException;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;

/**
 * Laravel: src/User/Domain/ValueObjects/Password.php
 *   - plaintext-i constructor-da validate edir, hash-də saxlayır
 *
 * Spring: BCryptPasswordEncoder ilə hashing (Spring Security).
 * Two factory methods:
 *   - fromPlaintext(): yeni qeydiyyat zamanı (hash)
 *   - fromHash(): DB-dən yükləmə zamanı (artıq hash-li)
 */
public final class Password implements ValueObject {

    private static final BCryptPasswordEncoder ENCODER = new BCryptPasswordEncoder(12);
    private static final int MIN_LENGTH = 8;

    private final String hash;

    private Password(String hash) {
        this.hash = hash;
    }

    public static Password fromPlaintext(String plaintext) {
        if (plaintext == null || plaintext.length() < MIN_LENGTH) {
            throw new DomainException("Şifrə ən azı " + MIN_LENGTH + " simvol olmalıdır");
        }
        return new Password(ENCODER.encode(plaintext));
    }

    public static Password fromHash(String hash) {
        if (hash == null || hash.isBlank()) {
            throw new DomainException("Hash boş ola bilməz");
        }
        return new Password(hash);
    }

    public boolean matches(String plaintext) {
        return ENCODER.matches(plaintext, this.hash);
    }

    public String hash() {
        return hash;
    }
}
