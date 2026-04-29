package az.ecommerce.user.application.service;

import az.ecommerce.notification.infrastructure.channel.EmailChannel;
import az.ecommerce.shared.domain.exception.DomainException;
import az.ecommerce.user.domain.User;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.domain.valueobject.Password;
import az.ecommerce.user.infrastructure.persistence.PasswordResetTokenEntity;
import az.ecommerce.user.infrastructure.persistence.PasswordResetTokenRepository;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.security.SecureRandom;
import java.util.Base64;
import java.util.Map;
import java.util.Optional;

/**
 * Laravel: AuthController::forgotPassword + resetPassword
 *
 * Flow:
 *   1. forgotPassword(email) → token yaradır + email göndərir
 *   2. resetPassword(email, token, newPassword) → yoxlayır, şifrəni dəyişir
 */
@Service
public class PasswordResetService {

    private final UserRepository userRepository;
    private final PasswordResetTokenRepository tokenRepository;
    private final EmailChannel emailChannel;
    private final BCryptPasswordEncoder encoder = new BCryptPasswordEncoder();
    private final SecureRandom secureRandom = new SecureRandom();

    @Value("${app.reset-password-url:https://app.ecommerce.az/reset-password}")
    private String resetPasswordUrl;

    public PasswordResetService(UserRepository userRepository,
                                 PasswordResetTokenRepository tokenRepository,
                                 EmailChannel emailChannel) {
        this.userRepository = userRepository;
        this.tokenRepository = tokenRepository;
        this.emailChannel = emailChannel;
    }

    @Transactional(transactionManager = "userTransactionManager")
    public void requestReset(String email) {
        // Email enumeration qoruması: istifadəçi tapılmasa da uğur qaytarırıq
        Optional<User> userOpt = userRepository.findByEmail(new Email(email));
        if (userOpt.isEmpty()) {
            return;
        }
        User user = userOpt.get();

        String rawToken = generateToken();
        PasswordResetTokenEntity entity = new PasswordResetTokenEntity();
        entity.setEmail(email);
        entity.setToken(encoder.encode(rawToken));   // hash store
        tokenRepository.save(entity);

        String resetUrl = resetPasswordUrl + "?email=" + email + "&token=" + rawToken;
        emailChannel.send(email, "Şifrə bərpası", "password-reset",
                Map.of("userName", user.name(), "resetUrl", resetUrl));
    }

    @Transactional(transactionManager = "userTransactionManager")
    public void resetPassword(String email, String token, String newPassword) {
        PasswordResetTokenEntity entity = tokenRepository.findById(email)
                .orElseThrow(() -> new DomainException("Token tapılmadı"));

        if (entity.isExpired()) {
            throw new DomainException("Token vaxtı bitib (1 saat keçib)");
        }
        if (!encoder.matches(token, entity.getToken())) {
            throw new DomainException("Token yanlışdır");
        }

        User user = userRepository.findByEmail(new Email(email))
                .orElseThrow(() -> new EntityNotFoundException("User", email));
        user.changePassword(Password.fromPlaintext(newPassword));
        userRepository.save(user);
        tokenRepository.deleteById(email);
    }

    private String generateToken() {
        byte[] bytes = new byte[32];
        secureRandom.nextBytes(bytes);
        return Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
    }
}
