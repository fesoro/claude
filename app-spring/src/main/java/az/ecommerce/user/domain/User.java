package az.ecommerce.user.domain;

import az.ecommerce.shared.domain.AggregateRoot;
import az.ecommerce.shared.domain.exception.DomainException;
import az.ecommerce.user.domain.event.UserRegisteredEvent;
import az.ecommerce.user.domain.event.UserRegisteredIntegrationEvent;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.domain.valueobject.Password;
import az.ecommerce.user.domain.valueobject.UserId;

import java.util.List;

/**
 * === USER AGGREGATE ROOT ===
 *
 * Laravel: src/User/Domain/Entities/User.php (Aggregate Root)
 * Spring: domain layer-də saf POJO (JPA annotation-ları YOXDUR — onlar
 * infrastructure layer-dəki UserEntity-də olacaq).
 *
 * Bu, "Persistence Ignorance" prinsipidir — domain framework-dən asılı deyil.
 *
 * Business metodları:
 *   - register() — factory method, event record edir
 *   - changePassword(), enableTwoFactor(), confirmTwoFactor()
 *   - verifyPassword()
 */
public class User extends AggregateRoot {

    private final UserId id;
    private final Email email;
    private String name;
    private Password password;
    private boolean twoFactorEnabled;
    private String twoFactorSecret;
    private List<String> backupCodes;

    private User(UserId id, String name, Email email, Password password) {
        this.id = id;
        this.name = name;
        this.email = email;
        this.password = password;
        this.twoFactorEnabled = false;
    }

    /** Laravel: User::register(...) — factory method */
    public static User register(String name, Email email, Password password) {
        if (name == null || name.isBlank()) {
            throw new DomainException("Ad boş ola bilməz");
        }
        UserId id = UserId.generate();
        User user = new User(id, name, email, password);
        UserRegisteredEvent event = UserRegisteredEvent.of(id, email, name);
        user.recordEvent(event);
        user.recordEvent(UserRegisteredIntegrationEvent.fromDomain(event));
        return user;
    }

    public static User reconstitute(UserId id, String name, Email email, Password password,
                                    boolean twoFactorEnabled, String twoFactorSecret,
                                    List<String> backupCodes) {
        User user = new User(id, name, email, password);
        user.twoFactorEnabled = twoFactorEnabled;
        user.twoFactorSecret = twoFactorSecret;
        user.backupCodes = backupCodes;
        return user;
    }

    public void changePassword(Password newPassword) {
        this.password = newPassword;
    }

    public void enableTwoFactor(String secret, List<String> backupCodes) {
        if (this.twoFactorEnabled) {
            throw new DomainException("2FA artıq aktivdir");
        }
        this.twoFactorEnabled = true;
        this.twoFactorSecret = secret;
        this.backupCodes = backupCodes;
    }

    public void disableTwoFactor() {
        this.twoFactorEnabled = false;
        this.twoFactorSecret = null;
        this.backupCodes = null;
    }

    public boolean verifyPassword(String plaintext) {
        return password.matches(plaintext);
    }

    public UserId id() { return id; }
    public Email email() { return email; }
    public String name() { return name; }
    public Password password() { return password; }
    public boolean twoFactorEnabled() { return twoFactorEnabled; }
    public String twoFactorSecret() { return twoFactorSecret; }
    public List<String> backupCodes() { return backupCodes; }
}
